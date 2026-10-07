<?php
/** pay.php?o=<razorpay order id> - opens Razorpay Checkout for a pending donation. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/razorpay.php';

$order = get_str('o', 40);
$don = preg_match('/^order_[A-Za-z0-9]+$/', $order)
    ? db_query('SELECT n.*, d.name, d.email, d.mobile FROM donations n JOIN donors d ON d.id = n.donor_id WHERE n.razorpay_order_id = ?', [$order])->fetch()
    : false;
if (!$don) { http_response_code(404); redirect('donate.php'); }
if ($don['status'] === 'success') redirect('thank_you.php?o=' . urlencode($order));
if (!in_array($don['status'], ['pending', 'failed'], true) || !rzp_configured()) redirect('donate.php');

$opts = [
    'key' => rzp_key_id(), 'amount' => (int) round($don['amount'] * 100), 'currency' => 'INR', 'order_id' => $order,
    'name' => setting('site_name_en'), 'description' => tx('दान', 'Donation'),
    'prefill' => ['name' => $don['name'], 'email' => (string) $don['email'], 'contact' => $don['mobile']],
    'theme' => ['color' => '#0f2a21'],
];
$page_title = tx('भुगतान', 'Payment'); $nav_active = '';
require ROOT_PATH . '/includes/public_header.php'; ?>
<section class="sec"><div class="wrap" style="max-width:520px;text-align:center">
  <div class="donate-box">
    <h2><?= e(inr($don['amount'])) ?></h2>
    <p><?= e(tx('भुगतान विंडो खुल रही है…', 'Opening the secure payment window…')) ?></p>
    <button id="payBtn" class="btn btn-amber" type="button"><?= e(tx('अभी भुगतान करें', 'Pay now')) ?></button>
    <p class="meta"><a href="<?= e(url('donate.php')) ?>">← <?= e(t('back')) ?></a></p>
  </div>
  <form id="vf" method="post" action="<?= e(url('payment_verify.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="razorpay_order_id" value="<?= e($order) ?>">
    <input type="hidden" name="razorpay_payment_id" id="rp"><input type="hidden" name="razorpay_signature" id="rs">
  </form>
</div></section>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
  var opts = <?= json_encode($opts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
  opts.handler = function (r) {
    document.getElementById('rp').value = r.razorpay_payment_id;
    document.getElementById('rs').value = r.razorpay_signature;
    document.getElementById('vf').submit();
  };
  function openPay() { if (window.Razorpay) { new Razorpay(opts).open(); } else { alert('Payment script load nahi hua. Internet check karke dobara try karein.'); } }
  document.getElementById('payBtn').addEventListener('click', openPay);
  window.addEventListener('load', openPay);
</script>
<?php require ROOT_PATH . '/includes/public_footer.php';
