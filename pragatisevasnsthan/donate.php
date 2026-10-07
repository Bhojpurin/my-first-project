<?php
/**
 * donate.php - donation info page (Step 4).
 * Online payment (Razorpay) comes in the donation step; for now it shows presets + bank / UPI details.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';

$camp = null;
if (isset($_GET['campaign'])) {
    $camp = db_query("SELECT * FROM campaigns WHERE slug = ? AND status = 'active'", [get_str('campaign', 160)])->fetch() ?: null;
}
$presets = array_filter(array_map('intval', explode(',', setting('donation_presets', '501,1100,2100,5100'))));
$bank = [
    'bank_account_name' => 'Account name', 'bank_name' => 'Bank', 'bank_account_no' => 'Account no.',
    'bank_ifsc' => 'IFSC', 'upi_id' => 'UPI ID',
];
$hasBank = false;
foreach ($bank as $k => $_) if (setting($k) !== '') $hasBank = true;

$page_title = t('donate_now'); $nav_active = '';
require ROOT_PATH . '/includes/public_header.php'; ?>
<section class="sec"><div class="wrap" style="max-width:760px">
  <h1><?= e(t('donate_now')) ?><?= $camp ? ' — ' . e(pick($camp, 'title')) : '' ?></h1>
  <div class="donate-box">
    <div class="presets"><?php foreach ($presets as $a): ?><span><?= e(inr($a)) ?></span><?php endforeach; ?></div>
    <div class="notice"><?= e(t('online_soon')) ?></div>
    <?php if ($hasBank): ?>
      <h3><?= e(t('bank_details')) ?></h3>
      <dl class="kv"><?php foreach ($bank as $k => $label): if (setting($k) === '') continue; ?>
        <dt><?= e($label) ?></dt><dd><?= e(setting($k)) ?></dd><?php endforeach; ?></dl>
    <?php endif; ?>
  </div>
</div></section>
<?php require ROOT_PATH . '/includes/public_footer.php';
