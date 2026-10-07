<?php
/** payment_verify.php - Razorpay Checkout posts here after payment. Signature is verified server-side. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/razorpay.php';
require_once ROOT_PATH . '/includes/donations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('donate.php');
csrf_check();

$order = post_str('razorpay_order_id', 40);
$pay   = post_str('razorpay_payment_id', 40);
$sig   = post_str('razorpay_signature', 128);
$don   = db_query('SELECT * FROM donations WHERE razorpay_order_id = ?', [$order])->fetch();
if (!$don || !preg_match('/^pay_[A-Za-z0-9]+$/', $pay)) redirect('donate.php');

if (!rzp_verify_payment_signature($order, $pay, $sig)) {
    error_log("bad payment signature for $order");
    redirect('thank_you.php?o=' . urlencode($order) . '&e=sig');
}

$info = ['razorpay_payment_id' => $pay];
try {   // best effort: confirm with Razorpay, learn payment method, capture if only authorized
    $p = rzp_fetch_payment($pay);
    if ($p) {
        if (($p['order_id'] ?? '') !== $order) redirect('thank_you.php?o=' . urlencode($order) . '&e=sig');
        $info['payment_method'] = substr((string) ($p['method'] ?? ''), 0, 30) ?: null;
        $info['paid_amount']    = ((int) ($p['amount'] ?? 0)) / 100;
        if (($p['status'] ?? '') === 'authorized') rzp_capture($pay, (int) $p['amount']);
        elseif (!in_array($p['status'] ?? '', ['captured', 'authorized'], true)) { redirect('thank_you.php?o=' . urlencode($order)); }
    }
} catch (Throwable $e) { error_log('payment fetch failed: ' . $e->getMessage()); }

try { donation_finalize((int) $don['id'], $info); }
catch (Throwable $e) { error_log('finalize failed: ' . $e->getMessage()); }   // webhook will complete it
redirect('thank_you.php?o=' . urlencode($order));
