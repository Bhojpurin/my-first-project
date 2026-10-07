<?php
/**
 * admin/settings.php - site settings + CREDENTIALS panel (Razorpay keys, SMTP).
 * Needs permission settings.manage; the Razorpay / Email sections need Super Admin.
 * Secret fields are stored AES-256-GCM encrypted and are never printed back (blank = keep saved value).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/razorpay.php';
require_once ROOT_PATH . '/includes/mailer.php';
require_once ROOT_PATH . '/includes/forms.php';
require_perm('settings.manage');

$page_title = 'Settings';
$active     = 'settings.php';
$super      = is_super_admin();

// section => [title, super_only, intro, fields]. field = [key, label, type(text|textarea|select|secret), hint, options]
$sections = [
  'profile' => ['Trust profile', false, 'Ye details website footer aur donation receipt par chhapti hain.', [
      ['site_name_en', 'Trust name (English)', 'text'], ['site_name_hi', 'Trust name (हिन्दी)', 'text'],
      ['site_tagline', 'Tagline', 'text'], ['contact_email', 'Contact email', 'text'], ['contact_phone', 'Contact phone', 'text'],
      ['address', 'Address', 'textarea'],
      ['registration_no', 'Registration No.', 'text'], ['pan_no', 'Trust PAN', 'text'],
      ['reg_12a_no', '12A No.', 'text'], ['reg_80g_no', '80G No.', 'text', 'Khali = receipt par 80G nahi likha jayega'],
      ['reg_80g_valid_to', '80G valid till', 'text'], ['receipt_prefix', 'Receipt prefix', 'text', 'Jaise PSS -> PSS/2026-27/0001'],
      ['receipt_signatory', 'Receipt signatory name', 'text'],
      ['social_facebook', 'Facebook URL', 'text'], ['social_instagram', 'Instagram URL', 'text'], ['social_youtube', 'YouTube URL', 'text'],
  ]],
  'donation' => ['Donation & bank details', false, 'Donate page par dikhne wali raashi aur direct bank / UPI details.', [
      ['min_donation', 'Minimum donation (₹)', 'text'], ['donation_presets', 'Preset amounts', 'text', 'Comma se: 501,1100,2100,5100'],
      ['bank_account_name', 'Account name', 'text'], ['bank_name', 'Bank', 'text'], ['bank_account_no', 'Account no.', 'text'],
      ['bank_ifsc', 'IFSC', 'text'], ['upi_id', 'UPI ID', 'text'],
  ]],
  'razorpay' => ['Razorpay (online payment)', true, 'Razorpay Dashboard > Account & Settings > API Keys se Key ID aur Key Secret lein. Webhook ke liye niche ka URL dashboard me daalein.', [
      ['razorpay_key_id', 'Key ID', 'text', 'rzp_test_... (test) ya rzp_live_... (live)'],
      ['razorpay_key_secret', 'Key Secret', 'secret'],
      ['razorpay_webhook_secret', 'Webhook Secret', 'secret', 'Dashboard > Webhooks me jo secret aapne banaya'],
  ]],
  'email' => ['Email (SMTP) - receipt bhejne ke liye', true, 'Gmail ho to smtp.gmail.com, port 587, TLS, aur Google "App password" use karein (normal password nahi).', [
      ['smtp_host', 'SMTP host', 'text'], ['smtp_port', 'Port', 'text', '587 (TLS) / 465 (SSL)'],
      ['smtp_secure', 'Security', 'select', '', ['tls' => 'TLS / STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None (sirf local test)']],
      ['smtp_user', 'SMTP username', 'text'], ['smtp_pass', 'SMTP password', 'secret'],
      ['smtp_from_email', 'From email', 'text'], ['smtp_from_name', 'From name', 'text'],
  ]],
];

function settings_save(string $key, string $value): void
{
    db_query('INSERT INTO settings (skey, svalue, updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), updated_by = VALUES(updated_by)',
             [$key, $value, $_SESSION['uid'] ?? null]);
}

$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do  = $_POST['do'] ?? '';
    $sec = $_POST['section'] ?? '';
    if (!isset($sections[$sec]) || ($sections[$sec][1] && !$super)) forbidden();

    if ($do === 'save') {
        $changed = [];   // for the audit log (secrets never logged)
        $writes  = [];   // key => value to store; written only if EVERY field in the section is valid
        $bad = [];
        foreach ($sections[$sec][3] as $fld) {
            [$key, $label, $type] = $fld;
            $isSecret = $type === 'secret';
            $val = $isSecret ? trim((string) ($_POST[$key] ?? '')) : post_str($key, $type === 'textarea' ? 500 : 255);

            if ($isSecret) {
                if (!empty($_POST['clear_' . $key])) { $writes[$key] = ''; $changed[$key] = '[cleared]'; }
                elseif ($val !== '')                 { $writes[$key] = secret_encrypt($val); $changed[$key] = '[changed]'; }
                continue;                              // blank = keep the saved secret
            }
            if ($type === 'select' && !isset($fld[4][$val])) { $bad[] = "$label galat hai."; continue; }
            if ($key === 'razorpay_key_id' && $val !== '' && !preg_match('/^rzp_(test|live)_[A-Za-z0-9]+$/', $val)) $bad[] = 'Key ID rzp_test_ ya rzp_live_ se shuru hona chahiye.';
            if ($key === 'smtp_from_email' && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) $bad[] = 'From email galat hai.';
            if ($key === 'smtp_port' && $val !== '' && !ctype_digit($val)) $bad[] = 'Port number hona chahiye.';
            if ($key === 'min_donation' && $val !== '' && !ctype_digit($val)) $bad[] = 'Minimum donation number hona chahiye.';
            if ($key === 'donation_presets' && $val !== '' && !preg_match('/^\d+(\s*,\s*\d+)*$/', $val)) $bad[] = 'Preset amounts comma se alag numbers hone chahiye.';
            if ($key === 'receipt_prefix' && $val !== '' && !preg_match('/^[A-Za-z0-9]{2,8}$/', $val)) $bad[] = 'Receipt prefix 2-8 letters/numbers ka ho.';
            if (str_starts_with($key, 'social_') && $val !== '' && !preg_match('#^https?://#i', $val)) $bad[] = "$label http(s):// se shuru ho.";
            $old = setting($key);
            if ($val !== $old) { $writes[$key] = $val; $changed[$key] = $val; }
        }
        if ($bad) { foreach ($bad as $b) flash('error', $b); flash('error', 'Kuch save nahi hua - galtiyan theek karke dobara save karein.'); }
        else { foreach ($writes as $k => $v) settings_save($k, $v); audit('update', 'settings', $sec, null, $changed); flash('success', $sections[$sec][0] . ' save ho gaya.'); }
        redirect('admin/settings.php#' . $sec);
    }

    if ($do === 'test_razorpay' && $super) {
        try {
            [$code, $d] = rzp_request('GET', '/v1/orders?count=1');
            $testResult = ['razorpay', $code === 200, $code === 200
                ? 'Connected ✔ (' . strtoupper(rzp_mode()) . ' mode keys sahi hain)'
                : 'Fail (' . $code . '): ' . ($d['error']['description'] ?? 'Key ID / Secret check karein')];
        } catch (Throwable $e) { $testResult = ['razorpay', false, $e->getMessage()]; }
    }
    if ($do === 'test_email' && $super) {
        $to = post_str('test_to', 150);
        try {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Test email address sahi likhein.');
            mail_send($to, 'Test email - ' . setting('site_name_en'), '<p>SMTP settings sahi kaam kar rahi hain ✔</p>');
            $testResult = ['email', true, "Test mail $to par bhej di gayi."];
        } catch (Throwable $e) { $testResult = ['email', false, $e->getMessage()]; }
    }
}

require ROOT_PATH . '/includes/admin_header.php';
if ($testResult): ?><div class="alert alert-<?= $testResult[1] ? 'success' : 'error' ?>"><?= e($testResult[2]) ?></div><?php endif; ?>
<p class="muted">Passwords / secret keys yahan encrypted save hote hain aur dobara dikhaye nahi jaate. Naya daalne par hi badalte hain; khali chhodne par purana rehta hai.</p>

<?php foreach ($sections as $sid => [$title, $superOnly, $intro, $fields]):
    if ($superOnly && !$super) continue; ?>
  <form method="post" class="card form" id="<?= e($sid) ?>">
    <?= csrf_field() ?><input type="hidden" name="section" value="<?= e($sid) ?>"><input type="hidden" name="do" value="save">
    <h2><?= e($title) ?> <?= $superOnly ? '<span class="badge b-amber">Super Admin</span>' : '' ?></h2>
    <p class="muted"><?= e($intro) ?></p>
    <div class="grid2">
    <?php foreach ($fields as $fld): [$key, $label, $type] = $fld; $hint = $fld[3] ?? ''; ?>
      <div class="field" <?= $type === 'textarea' ? 'style="grid-column:1/-1"' : '' ?>>
        <label><?= e($label) ?></label>
        <?php if ($type === 'secret'): $has = setting($key) !== ''; ?>
          <input type="password" name="<?= e($key) ?>" autocomplete="new-password" placeholder="<?= $has ? '•••••••• saved - naya daalne par hi badlega' : 'Yahan paste karein' ?>">
          <?php if ($has): ?><label class="check"><input type="checkbox" name="clear_<?= e($key) ?>" value="1"> Saved value hata do</label><?php endif; ?>
        <?php elseif ($type === 'select'): ?>
          <select name="<?= e($key) ?>"><?php foreach ($fld[4] as $k => $l): ?><option value="<?= e($k) ?>" <?= (setting($key) ?: array_key_first($fld[4])) === (string) $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <?php elseif ($type === 'textarea'): ?>
          <textarea name="<?= e($key) ?>" rows="3"><?= e(setting($key)) ?></textarea>
        <?php else: ?>
          <input name="<?= e($key) ?>" value="<?= e(setting($key)) ?>" maxlength="255" autocomplete="off">
        <?php endif; ?>
        <?php if ($hint !== ''): ?><span class="hint"><?= e($hint) ?></span><?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
    <?php if ($sid === 'razorpay'): ?>
      <div class="alert alert-info">
        <strong>Webhook URL (Razorpay Dashboard > Webhooks me daalein):</strong><br><code><?= e(url('razorpay_webhook.php')) ?></code><br>
        Events select karein: <code>payment.captured</code>, <code>payment.failed</code>, <code>order.paid</code>, <code>refund.processed</code>.
        Status: <?= rzp_configured() ? '<strong>Keys saved (' . e(strtoupper(rzp_mode())) . ')</strong>' : '<strong>Keys abhi nahi daali</strong>' ?>
        · Webhook secret: <?= trim(cfg('razorpay_webhook_secret')) !== '' ? 'saved' : 'missing' ?>
      </div>
    <?php endif; ?>
    <div class="form-actions"><button class="btn btn-amber" type="submit">Save <?= e($title) ?></button></div>
  </form>

  <?php if ($sid === 'razorpay'): ?>
    <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="section" value="razorpay"><input type="hidden" name="do" value="test_razorpay">
      <button class="btn btn-ghost" type="submit">Test Razorpay connection (saved keys se)</button></form>
  <?php elseif ($sid === 'email'): ?>
    <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="section" value="email"><input type="hidden" name="do" value="test_email">
      <div class="field"><label>Test email bhejein</label><input type="email" name="test_to" placeholder="aapka@email.com" required></div>
      <button class="btn btn-ghost" type="submit">Send test email</button></form>
  <?php endif; ?>
<?php endforeach; ?>
<?php require ROOT_PATH . '/includes/admin_footer.php';
