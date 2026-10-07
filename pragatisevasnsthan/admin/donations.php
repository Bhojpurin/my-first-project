<?php
/**
 * admin/donations.php - donations list, filters, CSV export, detail + resend receipt, offline (cash/cheque/bank) entry.
 * Permissions: donations.view | donations.offline_add | donations.export | receipts.resend
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/donations.php';
require_once ROOT_PATH . '/includes/jobs.php';
require_perm('donations.view');

$page_title = 'Donations';
$active     = 'donations.php';
$action     = $_GET['action'] ?? 'list';          // list | view | offline
$errors     = [];
$f          = ['name' => '', 'mobile' => '', 'email' => '', 'pan' => '', 'address' => '', 'city' => '', 'state' => '', 'pincode' => '',
               'amount' => '', 'campaign_id' => '', 'offline_method' => 'cash', 'reference_no' => '', 'paid_on' => date('Y-m-d'), 'dedication' => ''];

// ---------- POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';

    if ($do === 'resend') {
        if (!can('receipts.resend')) forbidden();
        $id = (int) ($_POST['id'] ?? 0);
        $rc = receipt_by_donation($id);
        if (!$rc || $rc['status'] !== 'issued') { flash('error', 'Receipt nahi mili.'); redirect('admin/donations.php'); }
        if (empty($rc['donor_email'])) { flash('error', 'Donor ka email nahi hai.'); redirect('admin/donations.php?action=view&id=' . $id); }
        job_enqueue('send_receipt_email', ['donation_id' => $id]);
        audit('resend_receipt', 'donations', $id);
        try { jobs_run(3); flash('success', 'Receipt email queue me gayi / bhej di gayi. Na pahunche to Settings > Email check karein.'); }
        catch (Throwable $e) { flash('error', 'Email abhi nahi gayi: ' . $e->getMessage()); }
        redirect('admin/donations.php?action=view&id=' . $id);
    }

    if ($do === 'offline') {
        if (!can('donations.offline_add')) forbidden();
        $action = 'offline';
        foreach (['name' => 120, 'mobile' => 20, 'email' => 150, 'pan' => 10, 'address' => 255, 'city' => 100, 'state' => 100, 'pincode' => 10,
                  'reference_no' => 80, 'dedication' => 200, 'paid_on' => 10] as $k => $max) $f[$k] = post_str($k, $max);
        $f['pan'] = strtoupper($f['pan']); $f['mobile'] = mobile_clean($f['mobile']);
        $f['amount'] = post_str('amount', 12); $f['campaign_id'] = (int) ($_POST['campaign_id'] ?? 0) ?: '';
        $f['offline_method'] = in_array($_POST['offline_method'] ?? '', ['cash', 'cheque', 'bank_transfer', 'upi', 'other'], true) ? $_POST['offline_method'] : 'cash';
        $amount = is_numeric($f['amount']) ? round((float) $f['amount'], 2) : 0;

        if (mb_strlen($f['name']) < 2) $errors[] = 'Donor ka naam likhein.';
        if (!preg_match('/^[6-9]\d{9}$/', $f['mobile'])) $errors[] = 'Mobile 10 digit ka sahi number ho.';
        if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email sahi nahi hai.';
        if ($f['pan'] !== '' && !pan_valid($f['pan'])) $errors[] = 'PAN sahi nahi hai.';
        if ($amount <= 0 || $amount > 100000000) $errors[] = 'Amount sahi likhein.';
        $ts = strtotime($f['paid_on'] . ' 12:00:00');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['paid_on']) || !$ts || $ts > time() + 86400) $errors[] = 'Date sahi ho (aaj ya pehle ki).';
        if ($f['campaign_id'] && !db_value('SELECT COUNT(*) FROM campaigns WHERE id = ?', [$f['campaign_id']])) $errors[] = 'Campaign galat hai.';

        if (!$errors) {
            $id = donation_create($f + ['amount' => $amount, 'mode' => 'offline', 'created_by' => $_SESSION['uid']]);
            $rc = donation_finalize($id, ['paid_at' => date('Y-m-d H:i:s', $ts)]);
            audit('create', 'donations', $id, null, ['amount' => $amount, 'mode' => 'offline', 'method' => $f['offline_method'], 'receipt' => $rc['receipt_no'] ?? null]);
            flash('success', 'Offline donation record ho gaya. Receipt: ' . ($rc['receipt_no'] ?? '-'));
            redirect('admin/donations.php?action=view&id=' . $id);
        }
    }
}

// ---------- filters (shared by list + CSV) ----------
$q = get_str('q'); $fs = get_str('status', 12); $fm = get_str('mode', 8);
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';
$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(d.name LIKE ? OR d.mobile LIKE ? OR d.email LIKE ? OR r.receipt_no LIKE ? OR n.razorpay_payment_id LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
if (in_array($fs, ['pending', 'success', 'failed', 'refunded', 'cancelled'], true)) { $where[] = 'n.status = ?'; $params[] = $fs; }
if (in_array($fm, ['online', 'offline'], true)) { $where[] = 'n.mode = ?'; $params[] = $fm; }
if ($from !== '') { $where[] = 'DATE(COALESCE(n.paid_at, n.created_at)) >= ?'; $params[] = $from; }
if ($to !== '')   { $where[] = 'DATE(COALESCE(n.paid_at, n.created_at)) <= ?'; $params[] = $to; }
$whereSql = implode(' AND ', $where);
$baseSql = "FROM donations n JOIN donors d ON d.id = n.donor_id LEFT JOIN receipts r ON r.donation_id = n.id
            LEFT JOIN campaigns c ON c.id = n.campaign_id WHERE $whereSql";
$cols = 'n.id, n.amount, n.status, n.mode, n.offline_method, n.payment_method, n.paid_at, n.created_at, n.razorpay_payment_id, n.reference_no,
         d.name, d.mobile, d.email, d.pan_last4, r.receipt_no, r.is_80g, c.title_en AS campaign';

if ($action === 'list' && ($_GET['export'] ?? '') === '1') {
    if (!can('donations.export')) forbidden();
    audit('export', 'donations', null, null, ['filters' => $_GET]);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="donations-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Date', 'Receipt No', 'Donor', 'Mobile', 'Email', 'PAN (last4)', 'Amount', 'Status', 'Mode', 'Method', 'Reference', 'Campaign']);
    $safe = fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") ? "'" . $v : $v;     // CSV formula-injection guard
    $st = db_query("SELECT $cols $baseSql ORDER BY COALESCE(n.paid_at, n.created_at) DESC", $params);
    while ($r = $st->fetch()) {
        fputcsv($out, array_map($safe, [$r['paid_at'] ?: $r['created_at'], $r['receipt_no'], $r['name'], $r['mobile'], $r['email'],
            $r['pan_last4'] ? 'XXXXXX' . $r['pan_last4'] : '', $r['amount'], $r['status'], $r['mode'],
            $r['mode'] === 'offline' ? $r['offline_method'] : $r['payment_method'], $r['reference_no'] ?: $r['razorpay_payment_id'], $r['campaign']]));
    }
    exit;
}

$campaigns = db_rows('SELECT id, title_en FROM campaigns ORDER BY title_en');
if ($action === 'list') {
    $pg   = paginate((int) db_value("SELECT COUNT(*) $baseSql", $params), 20, (int) ($_GET['page'] ?? 1));
    $rows = db_rows("SELECT $cols $baseSql ORDER BY n.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
    $sum  = (float) db_value("SELECT COALESCE(SUM(n.amount),0) $baseSql AND n.status = 'success'", $params);
}
if ($action === 'view') {
    $id  = (int) ($_GET['id'] ?? 0);
    $don = db_query("SELECT n.*, d.name, d.mobile, d.email, d.pan_last4, d.address, d.city, d.state, d.pincode, c.title_en AS campaign, u.name AS admin_name
                     FROM donations n JOIN donors d ON d.id = n.donor_id LEFT JOIN campaigns c ON c.id = n.campaign_id
                     LEFT JOIN users u ON u.id = n.created_by WHERE n.id = ?", [$id])->fetch();
    if (!$don) { flash('error', 'Donation nahi mili.'); redirect('admin/donations.php'); }
    $rc = receipt_by_donation($id);
}

$badge = fn($s) => '<span class="badge ' . ['success' => 'b-green', 'pending' => 'b-amber', 'failed' => 'b-grey', 'refunded' => 'b-blue', 'cancelled' => 'b-grey'][$s] . '">' . e(ucfirst($s)) . '</span>';
require ROOT_PATH . '/includes/admin_header.php';

if ($action === 'list'): ?>
  <div class="toolbar">
    <form method="get" class="filters">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Name / mobile / receipt / payment id">
      <select name="status"><option value="">All status</option>
        <?php foreach (['success', 'pending', 'failed', 'refunded', 'cancelled'] as $s): ?><option value="<?= $s ?>" <?= $fs === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
      <select name="mode"><option value="">Online + Offline</option><option value="online" <?= $fm === 'online' ? 'selected' : '' ?>>Online</option><option value="offline" <?= $fm === 'offline' ? 'selected' : '' ?>>Offline</option></select>
      <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
      <button class="btn btn-ghost" type="submit">Filter</button>
    </form>
    <span>
      <?php if (can('donations.export')): ?><a class="btn btn-ghost" href="?<?= e(http_build_query(array_filter(['q' => $q, 'status' => $fs, 'mode' => $fm, 'from' => $from, 'to' => $to]) + ['export' => 1])) ?>">⬇ CSV</a><?php endif; ?>
      <?php if (can('donations.offline_add')): ?><a class="btn btn-amber" href="?action=offline">+ Offline entry</a><?php endif; ?>
    </span>
  </div>
  <div class="card">
    <p class="muted">Is filter me successful total: <strong><?= e(inr($sum)) ?></strong> · <?= (int) $pg['total'] ?> records</p>
    <?php if (!$rows): ?><p class="muted">Koi donation nahi mili.</p><?php else: ?>
    <table class="t"><tr><th>Date</th><th>Receipt</th><th>Donor</th><th>Amount</th><th>Purpose</th><th>Mode</th><th>Status</th><th></th></tr>
      <?php foreach ($rows as $r): ?>
        <tr><td><?= e(date('d M Y, h:i A', strtotime($r['paid_at'] ?: $r['created_at']))) ?></td>
          <td><?= e($r['receipt_no'] ?? '-') ?></td>
          <td><strong><?= e($r['name']) ?></strong><br><span class="muted"><?= e($r['mobile']) ?></span></td>
          <td><?= e(inr($r['amount'])) ?></td><td><?= e($r['campaign'] ?? 'General') ?></td>
          <td><?= e($r['mode'] === 'offline' ? 'Offline: ' . str_replace('_', ' ', (string) $r['offline_method']) : 'Online ' . $r['payment_method']) ?></td>
          <td><?= $badge($r['status']) ?></td>
          <td><a class="btn btn-ghost sm" href="?action=view&id=<?= (int) $r['id'] ?>">View</a></td></tr>
      <?php endforeach; ?></table>
    <?= pager_html($pg, ['q' => $q, 'status' => $fs, 'mode' => $fm, 'from' => $from, 'to' => $to]) ?>
    <?php endif; ?>
  </div>

<?php elseif ($action === 'view'): ?>
  <div class="card">
    <h2>Donation #<?= (int) $don['id'] ?> <?= $badge($don['status']) ?></h2>
    <table class="t">
      <tr><th style="width:200px">Amount</th><td><strong><?= e(inr($don['amount'])) ?></strong></td></tr>
      <tr><th>Donor</th><td><?= e($don['name']) ?> · <?= e($don['mobile']) ?> · <?= e($don['email'] ?? '-') ?></td></tr>
      <tr><th>PAN</th><td><?= $don['pan_last4'] ? 'XXXXXX' . e($don['pan_last4']) : '-' ?></td></tr>
      <tr><th>Address</th><td><?= e(implode(', ', array_filter([$don['address'], $don['city'], $don['state'], $don['pincode']]))) ?: '-' ?></td></tr>
      <tr><th>Purpose</th><td><?= e($don['campaign'] ?? 'General fund') ?></td></tr>
      <tr><th>Mode</th><td><?= e($don['mode']) ?><?= $don['offline_method'] ? ' (' . e(str_replace('_', ' ', $don['offline_method'])) . ')' : '' ?><?= $don['admin_name'] ? ' · entered by ' . e($don['admin_name']) : '' ?></td></tr>
      <tr><th>Razorpay</th><td>Order: <?= e($don['razorpay_order_id'] ?? '-') ?><br>Payment: <?= e($don['razorpay_payment_id'] ?? '-') ?></td></tr>
      <?php if ($don['reference_no']): ?><tr><th>Reference</th><td><?= e($don['reference_no']) ?></td></tr><?php endif; ?>
      <tr><th>Created / Paid</th><td><?= e($don['created_at']) ?> / <?= e($don['paid_at'] ?? '-') ?></td></tr>
      <?php if ($don['dedication']): ?><tr><th>Dedication</th><td><?= e($don['dedication']) ?></td></tr><?php endif; ?>
      <?php if ($don['note']): ?><tr><th>Note</th><td><?= e($don['note']) ?></td></tr><?php endif; ?>
      <tr><th>Receipt</th><td><?php if ($rc): ?><strong><?= e($rc['receipt_no']) ?></strong> (<?= e($rc['status']) ?>)<?= $rc['is_80g'] ? ' · 80G' : '' ?>
          · emailed: <?= e($rc['emailed_at'] ?? 'not yet') ?>
          · <a target="_blank" href="<?= e(url('receipt.php?c=' . $rc['verify_code'])) ?>">Open / print</a><?php else: ?>-<?php endif; ?></td></tr>
    </table>
    <div class="form-actions">
      <?php if ($rc && can('receipts.resend')): ?>
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="resend"><input type="hidden" name="id" value="<?= (int) $don['id'] ?>">
          <button class="btn btn-amber" type="submit">Resend receipt email</button></form>
      <?php endif; ?>
      <a class="btn btn-ghost" href="donations.php">← Back</a>
    </div>
  </div>

<?php elseif ($action === 'offline'):
  if (!can('donations.offline_add')) forbidden();
  foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="do" value="offline">
    <h2>Offline donation entry (cash / cheque / bank / UPI)</h2>
    <div class="grid2">
      <div class="field"><label>Donor name *</label><input name="name" required value="<?= e($f['name']) ?>"></div>
      <div class="field"><label>Mobile *</label><input name="mobile" required value="<?= e($f['mobile']) ?>"></div>
      <div class="field"><label>Email (receipt bhejne ke liye)</label><input type="email" name="email" value="<?= e($f['email']) ?>"></div>
      <div class="field"><label>PAN</label><input name="pan" maxlength="10" value="<?= e($f['pan']) ?>"></div>
      <div class="field"><label>Address</label><input name="address" value="<?= e($f['address']) ?>"></div>
      <div class="field"><label>City / State / Pincode</label>
        <input name="city" placeholder="City" value="<?= e($f['city']) ?>" style="margin-bottom:6px"><input name="state" placeholder="State" value="<?= e($f['state']) ?>" style="margin-bottom:6px"><input name="pincode" placeholder="Pincode" value="<?= e($f['pincode']) ?>"></div>
      <div class="field"><label>Amount (₹) *</label><input type="number" name="amount" min="1" step="0.01" required value="<?= e($f['amount']) ?>"></div>
      <div class="field"><label>Date received *</label><input type="date" name="paid_on" required max="<?= date('Y-m-d') ?>" value="<?= e($f['paid_on']) ?>"></div>
      <div class="field"><label>Method</label><select name="offline_method"><?php foreach (['cash' => 'Cash', 'cheque' => 'Cheque', 'bank_transfer' => 'Bank transfer', 'upi' => 'UPI', 'other' => 'Other'] as $k => $l): ?>
        <option value="<?= $k ?>" <?= $f['offline_method'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Cheque no. / UTR</label><input name="reference_no" value="<?= e($f['reference_no']) ?>"></div>
      <div class="field"><label>Purpose</label><select name="campaign_id"><option value="">General fund</option>
        <?php foreach ($campaigns as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) $f['campaign_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['title_en']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Dedication</label><input name="dedication" value="<?= e($f['dedication']) ?>"></div>
    </div>
    <div class="form-actions"><button class="btn btn-amber" type="submit">Save &amp; issue receipt</button> <a class="btn btn-ghost" href="donations.php">Cancel</a></div>
  </form>
<?php endif;
require ROOT_PATH . '/includes/admin_footer.php';
