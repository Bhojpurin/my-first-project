<?php
/**
 * donations.php - everything about a donation's life:
 *   donation_create()     pending row + donor (before payment)
 *   donation_finalize()   payment confirmed -> success + receipt number  (safe to call twice)
 *   donation_send_receipt_email(), receipt_html(), amount_in_words(), PAN helpers
 */

function pan_valid(string $pan): bool { return (bool) preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan); }

function mobile_clean(string $m): string
{
    $m = preg_replace('/\D+/', '', $m);
    return strlen($m) === 12 && str_starts_with($m, '91') ? substr($m, 2) : $m;
}

/** Financial year label for a date: 2026-04-01 -> "2026-27", 2027-02-10 -> "2026-27" */
function fy_for(string $date): string
{
    $t = strtotime($date);
    $y = (int) date('Y', $t);
    if ((int) date('n', $t) < 4) $y--;
    return $y . '-' . substr((string) ($y + 1), -2);
}

/**
 * Create donor + pending donation. $d keys: name, mobile, email, pan, address, city, state, pincode,
 * amount, campaign_id, is_anonymous, dedication, note, mode (online|offline), offline_method, reference_no, created_by.
 * Returns donation id.
 */
function donation_create(array $d): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pan = strtoupper(trim((string) ($d['pan'] ?? '')));
        // same mobile + email = same donor (profile refreshed with latest details)
        $donor = db_query('SELECT id FROM donors WHERE mobile = ? AND COALESCE(email,"") = ? LIMIT 1', [$d['mobile'], (string) ($d['email'] ?? '')])->fetch();
        $vals = [$d['name'], $d['mobile'], $d['email'] ?: null,
                 $pan !== '' ? bin_encrypt($pan) : null, $pan !== '' ? substr($pan, -4) : null,
                 $d['address'] ?? null, $d['city'] ?? null, $d['state'] ?? null, $d['pincode'] ?? null];
        if ($donor) {
            $donorId = (int) $donor['id'];
            db_query('UPDATE donors SET name=?, mobile=?, email=?, pan_enc=COALESCE(?, pan_enc), pan_last4=COALESCE(?, pan_last4),
                      address=COALESCE(?, address), city=COALESCE(?, city), state=COALESCE(?, state), pincode=COALESCE(?, pincode) WHERE id=?',
                     array_merge($vals, [$donorId]));
        } else {
            db_query('INSERT INTO donors (name, mobile, email, pan_enc, pan_last4, address, city, state, pincode) VALUES (?,?,?,?,?,?,?,?,?)', $vals);
            $donorId = (int) $pdo->lastInsertId();
        }
        db_query('INSERT INTO donations (donor_id, campaign_id, amount, mode, offline_method, reference_no, is_anonymous, dedication, note, ip, created_by)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                 [$donorId, $d['campaign_id'] ?: null, $d['amount'], $d['mode'] ?? 'online', $d['offline_method'] ?? null,
                  $d['reference_no'] ?? null, !empty($d['is_anonymous']) ? 1 : 0, $d['dedication'] ?? null, $d['note'] ?? null,
                  client_ip(), $d['created_by'] ?? null]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Payment confirmed -> mark success, issue receipt number. Idempotent: a second call changes nothing.
 * $info: razorpay_payment_id, payment_method, paid_amount (rupees, optional check), paid_at (offline back-dating).
 * @return array|null  receipt row (null if donation does not exist / amount mismatch)
 */
function donation_finalize(int $donationId, array $info = []): ?array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $don = db_query('SELECT * FROM donations WHERE id = ? FOR UPDATE', [$donationId])->fetch();
        if (!$don) { $pdo->rollBack(); return null; }

        $existing = db_query('SELECT * FROM receipts WHERE donation_id = ?', [$donationId])->fetch();
        if ($don['status'] === 'success' && $existing) { $pdo->commit(); return $existing; }
        if (in_array($don['status'], ['cancelled', 'refunded'], true)) { $pdo->rollBack(); return null; }

        if (isset($info['paid_amount']) && abs((float) $info['paid_amount'] - (float) $don['amount']) > 0.009) {
            $pdo->rollBack();
            error_log("donation $donationId: paid amount {$info['paid_amount']} != expected {$don['amount']}");
            return null;
        }

        $paidAt = $info['paid_at'] ?? date('Y-m-d H:i:s');
        db_query("UPDATE donations SET status='success', paid_at=?, razorpay_payment_id=COALESCE(?, razorpay_payment_id),
                  payment_method=COALESCE(?, payment_method) WHERE id=?",
                 [$paidAt, $info['razorpay_payment_id'] ?? null, $info['payment_method'] ?? null, $donationId]);

        // gapless receipt number per financial year
        $fy = fy_for($paidAt);
        db_query('INSERT IGNORE INTO receipt_sequences (fy, last_no) VALUES (?, 0)', [$fy]);
        $seq = (int) db_value('SELECT last_no FROM receipt_sequences WHERE fy = ? FOR UPDATE', [$fy]) + 1;
        db_query('UPDATE receipt_sequences SET last_no = ? WHERE fy = ?', [$seq, $fy]);

        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper(setting('receipt_prefix', 'PSS'))) ?: 'PSS';
        $receiptNo = sprintf('%s/%s/%04d', $prefix, $fy, $seq);
        $is80g = setting('reg_80g_no') !== '' ? 1 : 0;
        db_query('INSERT INTO receipts (donation_id, receipt_no, fy, seq, verify_code, is_80g) VALUES (?,?,?,?,?,?)',
                 [$donationId, $receiptNo, $fy, $seq, bin2hex(random_bytes(8)), $is80g]);

        if ($don['campaign_id']) campaign_recalc((int) $don['campaign_id']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    // e-mail receipt in background queue (+ try right now so the donor gets it in seconds)
    $donor = db_query('SELECT d.email FROM donors d JOIN donations n ON n.donor_id = d.id WHERE n.id = ?', [$donationId])->fetch();
    if (!empty($donor['email'])) {
        require_once ROOT_PATH . '/includes/jobs.php';
        job_enqueue('send_receipt_email', ['donation_id' => $donationId]);
        if (mail_configured_safe()) { try { jobs_run(3); } catch (Throwable $e) { error_log($e->getMessage()); } }
    }
    return db_query('SELECT * FROM receipts WHERE donation_id = ?', [$donationId])->fetch() ?: null;
}

function mail_configured_safe(): bool
{
    require_once ROOT_PATH . '/includes/mailer.php';
    return mail_configured();
}

function campaign_recalc(int $campaignId): void
{
    db_query("UPDATE campaigns SET raised_cache = (SELECT COALESCE(SUM(amount),0) FROM donations WHERE campaign_id = ? AND status = 'success') WHERE id = ?",
             [$campaignId, $campaignId]);
}

/** Receipt + donation + donor (+campaign) by public verify code, or null. */
function receipt_by_code(string $code): ?array
{
    if (!preg_match('/^[a-f0-9]{16}$/', $code)) return null;
    return receipt_query('r.verify_code = ?', [$code]);
}
function receipt_by_donation(int $donationId): ?array
{
    return receipt_query('r.donation_id = ?', [$donationId]);
}
function receipt_query(string $where, array $params): ?array
{
    $row = db_query("SELECT r.*, n.amount, n.mode, n.offline_method, n.reference_no, n.payment_method, n.razorpay_payment_id, n.paid_at, n.dedication,
                            n.is_anonymous, n.campaign_id, d.name AS donor_name, d.email AS donor_email, d.mobile AS donor_mobile, d.pan_last4,
                            d.address AS donor_address, d.city AS donor_city, d.state AS donor_state, d.pincode AS donor_pincode,
                            c.title_en AS campaign_en, c.title_hi AS campaign_hi
                     FROM receipts r JOIN donations n ON n.id = r.donation_id JOIN donors d ON d.id = n.donor_id
                     LEFT JOIN campaigns c ON c.id = n.campaign_id WHERE $where LIMIT 1", $params)->fetch();
    return $row ?: null;
}

function donation_send_receipt_email(int $donationId): void
{
    require_once ROOT_PATH . '/includes/mailer.php';
    $r = receipt_by_donation($donationId);
    if (!$r || $r['status'] !== 'issued') return;
    if (empty($r['donor_email'])) return;
    $link  = url('receipt.php?c=' . $r['verify_code']);
    $trust = setting('site_name_en');
    $html  = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#1b2420">'
           . '<h2 style="color:#0f2a21">Thank you, ' . e($r['donor_name']) . '!</h2>'
           . '<p>We have received your donation of <strong>' . e(inr($r['amount'])) . '</strong> to ' . e($trust) . '.</p>'
           . '<p>Receipt No: <strong>' . e($r['receipt_no']) . '</strong><br>Date: ' . e(date('d M Y', strtotime($r['paid_at']))) . '</p>'
           . '<p><a href="' . e($link) . '" style="background:#e8891c;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none">View / print receipt</a></p>'
           . ($r['is_80g'] ? '<p style="font-size:13px;color:#555">This donation is eligible for deduction under section 80G (PAN required on the receipt).</p>' : '')
           . '<p style="font-size:13px;color:#555">If the button does not work, open: ' . e($link) . '</p></div>';
    mail_send($r['donor_email'], 'Donation receipt ' . $r['receipt_no'] . ' - ' . $trust, $html);
    db_query('UPDATE receipts SET emailed_at = NOW() WHERE id = ?', [$r['id']]);
}

/** 1860000 -> "Eighteen Lakh Sixty Thousand Rupees Only" (Indian system) */
function amount_in_words(float $amount): string
{
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen',
             'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = fn(int $n) => $n < 20 ? $ones[$n] : trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
    $three = fn(int $n) => trim(($n >= 100 ? $ones[intdiv($n, 100)] . ' Hundred ' : '') . $two($n % 100));

    $rupees = (int) floor($amount);
    $paise  = (int) round(($amount - $rupees) * 100);
    if ($rupees === 0 && $paise === 0) return 'Zero Rupees Only';
    $parts = [];
    foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$div, $name]) {
        $q = intdiv($rupees, $div);
        if ($q > 0) { $parts[] = ($q < 100 ? $two($q) : $three($q)) . ' ' . $name; $rupees -= $q * $div; }
    }
    if ($rupees > 0) $parts[] = $three($rupees);
    $out = trim(implode(' ', $parts)) ?: 'Zero';
    $out .= ' Rupees';
    if ($paise > 0) $out .= ' and ' . $two($paise) . ' Paise';
    return $out . ' Only';
}

/** Printable receipt block (used by receipt.php). Everything printed through e(). */
function receipt_html(array $r): string
{
    $trust = setting('site_name_en');
    $trustHi = setting('site_name_hi');
    $addr = [$r['donor_address'], $r['donor_city'], $r['donor_state'], $r['donor_pincode']];
    $addr = implode(', ', array_filter(array_map('trim', array_map('strval', $addr))));
    $method = $r['mode'] === 'offline'
        ? ucfirst(str_replace('_', ' ', (string) $r['offline_method'])) . ($r['reference_no'] ? ' (' . $r['reference_no'] . ')' : '')
        : strtoupper((string) ($r['payment_method'] ?: 'Online')) . ($r['razorpay_payment_id'] ? ' (' . $r['razorpay_payment_id'] . ')' : '');
    $row = fn(string $l, string $v) => '<tr><th>' . e($l) . '</th><td>' . e($v) . '</td></tr>';
    $o  = '<div class="rcpt">';
    if ($r['status'] === 'cancelled') $o .= '<div class="rcpt-void">CANCELLED</div>';
    $o .= '<div class="rcpt-head"><h1>' . e($trust) . '</h1>' . ($trustHi ? '<div>' . e($trustHi) . '</div>' : '')
        . (setting('address') ? '<div class="sm">' . e(setting('address')) . '</div>' : '')
        . '<div class="sm">';
    foreach ([['registration_no', 'Reg. No.'], ['pan_no', 'PAN'], ['reg_12a_no', '12A'], ['reg_80g_no', '80G']] as [$k, $l]) {
        if (setting($k) !== '') $o .= e($l) . ': ' . e(setting($k)) . ' &nbsp; ';
    }
    if ($r['is_80g'] && setting('reg_80g_valid_to') !== '') $o .= '(80G valid till ' . e(setting('reg_80g_valid_to')) . ')';
    $o .= '</div></div><h2>DONATION RECEIPT / दान रसीद</h2><table class="rcpt-t">'
        . $row('Receipt No. / रसीद सं.', $r['receipt_no'])
        . $row('Date / दिनांक', date('d M Y', strtotime($r['paid_at'])))
        . $row('Received from / दानदाता', $r['donor_name'])
        . ($addr !== '' ? $row('Address / पता', $addr) : '')
        . ($r['pan_last4'] ? $row('PAN', 'XXXXXX' . $r['pan_last4']) : '')
        . $row('Amount / राशि', inr($r['amount']))
        . $row('In words', amount_in_words((float) $r['amount']))
        . $row('Payment mode', $method)
        . ($r['campaign_en'] ? $row('Purpose / उद्देश्य', $r['campaign_en'] . ($r['campaign_hi'] ? ' / ' . $r['campaign_hi'] : '')) : '')
        . ($r['dedication'] ? $row('Dedication', $r['dedication']) : '')
        . '</table>';
    if ($r['is_80g']) {
        $o .= '<p class="sm">Donations to this trust are eligible for deduction under section 80G of the Income-tax Act, subject to the donor\'s PAN being furnished.</p>';
    }
    $o .= '<p class="sm">This is a computer generated receipt. Verify: ' . e(url('receipt.php?c=' . $r['verify_code'])) . '</p>';
    if (setting('receipt_signatory') !== '') $o .= '<div class="rcpt-sign">' . e(setting('receipt_signatory')) . '<br><span class="sm">Authorised signatory</span></div>';
    return $o . '</div>';
}
