<?php
/** thank_you.php?o=<order id> - result page: success (receipt link) / still confirming / failed. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/donations.php';

$order = get_str('o', 40);
$don = db_query('SELECT n.*, d.name FROM donations n JOIN donors d ON d.id = n.donor_id WHERE n.razorpay_order_id = ?', [$order])->fetch();
if (!$don) redirect('donate.php');
$rc = $don['status'] === 'success' ? receipt_by_donation((int) $don['id']) : null;

$page_title = tx('धन्यवाद', 'Thank you'); $nav_active = '';
require ROOT_PATH . '/includes/public_header.php'; ?>
<section class="sec"><div class="wrap" style="max-width:620px;text-align:center">
  <div class="donate-box">
  <?php if ($don['status'] === 'success'): ?>
    <div style="font-size:48px">🙏</div>
    <h1><?= e(tx('धन्यवाद', 'Thank you')) ?>, <?= e(explode(' ', trim($don['name']))[0]) ?>!</h1>
    <p><?= e(tx('आपका ' . inr($don['amount']) . ' का दान प्राप्त हुआ।', 'We received your donation of ' . inr($don['amount']) . '.')) ?></p>
    <?php if ($rc): ?>
      <p><strong><?= e(tx('रसीद सं.', 'Receipt No.')) ?>: <?= e($rc['receipt_no']) ?></strong></p>
      <p><a class="btn btn-amber" target="_blank" href="<?= e(url('receipt.php?c=' . $rc['verify_code'])) ?>"><?= e(tx('रसीद देखें / प्रिंट करें', 'View / print receipt')) ?></a></p>
      <p class="meta"><?= e(tx('रसीद आपके ईमेल पर भी भेजी जा रही है।', 'A copy is also being e-mailed to you.')) ?></p>
    <?php endif; ?>
  <?php elseif (isset($_GET['e'])): ?>
    <h1><?= e(tx('भुगतान सत्यापित नहीं हो सका', 'Payment could not be verified')) ?></h1>
    <p><?= e(tx('यदि आपके खाते से पैसे कटे हैं तो वह अपने-आप वापस हो जाएँगे या हम आपसे संपर्क करेंगे।', 'If money was debited it will be auto-refunded, or we will contact you.')) ?></p>
    <a class="btn btn-amber" href="<?= e(url('donate.php')) ?>"><?= e(tx('फिर कोशिश करें', 'Try again')) ?></a>
  <?php elseif ($don['status'] === 'pending'): ?>
    <h1><?= e(tx('भुगतान की पुष्टि हो रही है…', 'Confirming your payment…')) ?></h1>
    <p><?= e(tx('कुछ सेकंड बाद यह पेज रिफ्रेश करें।', 'Please refresh this page in a few seconds.')) ?></p>
    <a class="btn btn-ghost" href="">↻ Refresh</a> <a class="btn btn-amber" href="<?= e(url('pay.php?o=' . urlencode($order))) ?>"><?= e(tx('फिर से भुगतान करें', 'Pay again')) ?></a>
  <?php else: ?>
    <h1><?= e(tx('भुगतान पूरा नहीं हुआ', 'Payment was not completed')) ?></h1>
    <a class="btn btn-amber" href="<?= e(url('pay.php?o=' . urlencode($order))) ?>"><?= e(tx('फिर से भुगतान करें', 'Try payment again')) ?></a>
  <?php endif; ?>
  </div>
</div></section>
<?php require ROOT_PATH . '/includes/public_footer.php';
