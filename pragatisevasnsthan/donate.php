<?php
/**
 * donate.php - donation form. POST creates donor + pending donation + Razorpay order, then sends the
 * donor to pay.php (Razorpay Checkout). The amount is ALWAYS taken from our DB row, never from the browser later.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';
require_once ROOT_PATH . '/includes/razorpay.php';
require_once ROOT_PATH . '/includes/donations.php';

$campaigns = db_rows("SELECT id, slug, title_en, title_hi FROM campaigns WHERE status = 'active' ORDER BY sort_order, id DESC");
$camp = null;
$wantSlug = get_str('campaign', 160) ?: post_str('campaign', 160);
foreach ($campaigns as $c) if ($c['slug'] === $wantSlug) $camp = $c;

$presets = array_values(array_filter(array_map('intval', explode(',', setting('donation_presets', '501,1100,2100,5100')))));
$minAmt  = max(1, (int) setting('min_donation', '100'));
const MAX_DONATION = 1000000;
$online  = rzp_configured();

$f = ['amount' => '', 'name' => '', 'mobile' => '', 'email' => '', 'pan' => '', 'address' => '', 'city' => '', 'state' => '',
      'pincode' => '', 'dedication' => '', 'is_anonymous' => 0];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name' => 120, 'mobile' => 20, 'email' => 150, 'pan' => 10, 'address' => 255, 'city' => 100, 'state' => 100, 'pincode' => 10, 'dedication' => 200] as $k => $max) {
        $f[$k] = post_str($k, $max);
    }
    $f['is_anonymous'] = isset($_POST['is_anonymous']) ? 1 : 0;
    $f['pan']    = strtoupper($f['pan']);
    $f['mobile'] = mobile_clean($f['mobile']);
    $chosen      = post_str('amount_choice', 12);
    $f['amount'] = $chosen === 'other' ? post_str('amount_other', 10) : $chosen;
    $amount      = is_numeric($f['amount']) ? round((float) $f['amount'], 2) : 0;

    if (post_str('website', 50) !== '')            $errors[] = 'Invalid request.';   // honeypot (bots fill it)
    if (!$online)                                   $errors[] = tx('ऑनलाइन भुगतान अभी चालू नहीं है।', 'Online payment is not enabled yet.');
    if ($amount < $minAmt || $amount > MAX_DONATION) $errors[] = tx("राशि ₹$minAmt से ₹" . MAX_DONATION . ' के बीच होनी चाहिए।', "Amount must be between ₹$minAmt and ₹" . MAX_DONATION . '.');
    if (mb_strlen($f['name']) < 2)                  $errors[] = tx('कृपया अपना नाम लिखें।', 'Please enter your name.');
    if (!preg_match('/^[6-9]\d{9}$/', $f['mobile'])) $errors[] = tx('सही 10 अंकों का मोबाइल नंबर लिखें।', 'Enter a valid 10-digit mobile number.');
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = tx('सही ईमेल लिखें (रसीद यहीं भेजी जाएगी)।', 'Enter a valid email (your receipt is sent here).');
    if ($f['pan'] !== '' && !pan_valid($f['pan'])) $errors[] = tx('PAN सही नहीं है (जैसे ABCDE1234F)।', 'PAN looks wrong (example ABCDE1234F).');
    if ($f['pincode'] !== '' && !preg_match('/^\d{6}$/', $f['pincode'])) $errors[] = tx('पिनकोड 6 अंकों का होना चाहिए।', 'Pincode must be 6 digits.');
    if (empty($_POST['consent']))                   $errors[] = tx('कृपया सहमति पर टिक करें।', 'Please accept the consent.');
    if (!$errors && (int) db_value('SELECT COUNT(*) FROM donations WHERE ip = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)', [client_ip()]) >= 8) {
        $errors[] = tx('बहुत अधिक प्रयास हुए, कुछ देर बाद फिर कोशिश करें।', 'Too many attempts, please try again in a few minutes.');
    }

    if (!$errors) {
        $donationId = donation_create($f + ['amount' => $amount, 'campaign_id' => $camp['id'] ?? null, 'mode' => 'online']);
        try {
            $order = rzp_create_order($amount, 'DON' . $donationId, ['donation_id' => (string) $donationId, 'donor' => $f['name']]);
            db_query('UPDATE donations SET razorpay_order_id = ? WHERE id = ?', [$order['id'], $donationId]);
            redirect('pay.php?o=' . urlencode($order['id']));
        } catch (Throwable $e) {
            error_log('order create failed: ' . $e->getMessage());
            db_query("UPDATE donations SET status = 'failed', note = ? WHERE id = ?", [substr($e->getMessage(), 0, 250), $donationId]);
            $errors[] = tx('भुगतान शुरू नहीं हो सका। कृपया थोड़ी देर बाद प्रयास करें।', 'Could not start the payment. Please try again shortly.');
        }
    }
}

$bank = ['bank_account_name' => 'Account name', 'bank_name' => 'Bank', 'bank_account_no' => 'Account no.', 'bank_ifsc' => 'IFSC', 'upi_id' => 'UPI ID'];
$hasBank = false;
foreach ($bank as $k => $_) if (setting($k) !== '') $hasBank = true;

$page_title = t('donate_now'); $nav_active = '';
require ROOT_PATH . '/includes/public_header.php'; ?>
<section class="sec"><div class="wrap" style="max-width:760px">
  <h1><?= e(t('donate_now')) ?></h1>
  <?php foreach ($errors as $err): ?><div class="alert-err"><?= e($err) ?></div><?php endforeach; ?>

  <?php if (!$online): ?>
    <div class="notice"><?= e(t('online_soon')) ?></div>
  <?php else: ?>
  <form method="post" class="donate-box dform" autocomplete="on">
    <?= csrf_field() ?>
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
    <label class="lbl"><?= e(tx('राशि चुनें', 'Choose amount')) ?></label>
    <div class="presets">
      <?php foreach ($presets as $p): ?>
        <label class="chip"><input type="radio" name="amount_choice" value="<?= (int) $p ?>" <?= (string) $f['amount'] === (string) $p ? 'checked' : '' ?>><span><?= e(inr($p)) ?></span></label>
      <?php endforeach; ?>
      <label class="chip"><input type="radio" name="amount_choice" value="other" <?= $f['amount'] !== '' && !in_array((int) $f['amount'], $presets, true) ? 'checked' : '' ?>><span><?= e(tx('अन्य', 'Other')) ?></span></label>
    </div>
    <input type="number" name="amount_other" min="<?= (int) $minAmt ?>" max="<?= MAX_DONATION ?>" step="1" placeholder="<?= e(tx('अपनी राशि (₹)', 'Your amount (₹)')) ?>"
           value="<?= e(!in_array((int) $f['amount'], $presets, true) ? $f['amount'] : '') ?>">

    <?php if ($campaigns): ?>
      <label class="lbl"><?= e(tx('किस उद्देश्य के लिए', 'Purpose')) ?></label>
      <select name="campaign">
        <option value=""><?= e(tx('सामान्य कोष', 'General fund')) ?></option>
        <?php foreach ($campaigns as $c): ?><option value="<?= e($c['slug']) ?>" <?= $camp && $camp['id'] === $c['id'] ? 'selected' : '' ?>><?= e(pick($c, 'title')) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>

    <div class="two">
      <div><label class="lbl"><?= e(tx('पूरा नाम', 'Full name')) ?> *</label><input name="name" required maxlength="120" value="<?= e($f['name']) ?>"></div>
      <div><label class="lbl"><?= e(tx('मोबाइल', 'Mobile')) ?> *</label><input name="mobile" required inputmode="numeric" maxlength="14" value="<?= e($f['mobile']) ?>"></div>
      <div><label class="lbl">Email *</label><input type="email" name="email" required maxlength="150" value="<?= e($f['email']) ?>"></div>
      <div><label class="lbl">PAN <?= setting('reg_80g_no') !== '' ? e(tx('(80G के लिए)', '(for 80G)')) : '' ?></label><input name="pan" maxlength="10" style="text-transform:uppercase" value="<?= e($f['pan']) ?>"></div>
    </div>
    <label class="lbl"><?= e(tx('पता', 'Address')) ?></label><input name="address" maxlength="255" value="<?= e($f['address']) ?>">
    <div class="two three">
      <div><label class="lbl"><?= e(tx('शहर', 'City')) ?></label><input name="city" maxlength="100" value="<?= e($f['city']) ?>"></div>
      <div><label class="lbl"><?= e(tx('राज्य', 'State')) ?></label><input name="state" maxlength="100" value="<?= e($f['state']) ?>"></div>
      <div><label class="lbl"><?= e(tx('पिनकोड', 'Pincode')) ?></label><input name="pincode" inputmode="numeric" maxlength="6" value="<?= e($f['pincode']) ?>"></div>
    </div>
    <label class="lbl"><?= e(tx('समर्पण (वैकल्पिक)', 'In memory / honour of (optional)')) ?></label><input name="dedication" maxlength="200" value="<?= e($f['dedication']) ?>">
    <label class="ck"><input type="checkbox" name="is_anonymous" value="1" <?= $f['is_anonymous'] ? 'checked' : '' ?>> <?= e(tx('मेरा नाम सार्वजनिक सूची में न दिखाएँ', 'Keep my name anonymous on public lists')) ?></label>
    <label class="ck"><input type="checkbox" name="consent" value="1" required> <?= e(tx('मैं अपनी जानकारी रसीद और संपर्क के लिए उपयोग किए जाने से सहमत हूँ।', 'I agree my details may be used for the receipt and communication.')) ?>
      (<a href="<?= e(url('page.php?slug=privacy-policy')) ?>" target="_blank"><?= e(t('privacy')) ?></a>, <a href="<?= e(url('page.php?slug=refund-policy')) ?>" target="_blank"><?= e(t('refund')) ?></a>)</label>
    <button class="btn btn-amber" type="submit" style="width:100%;margin-top:12px"><?= e(tx('सुरक्षित भुगतान करें', 'Proceed to secure payment')) ?></button>
    <p class="meta" style="text-align:center">🔒 Razorpay · UPI / Card / Netbanking</p>
  </form>
  <?php endif; ?>

  <?php if ($hasBank): ?>
  <div class="donate-box" style="margin-top:20px">
    <h3><?= e(t('bank_details')) ?></h3>
    <dl class="kv"><?php foreach ($bank as $k => $label): if (setting($k) === '') continue; ?><dt><?= e($label) ?></dt><dd><?= e(setting($k)) ?></dd><?php endforeach; ?></dl>
  </div>
  <?php endif; ?>
</div></section>
<?php require ROOT_PATH . '/includes/public_footer.php';
