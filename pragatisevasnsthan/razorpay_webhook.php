<?php
/**
 * razorpay_webhook.php - Razorpay -> our server. Set in Razorpay Dashboard > Webhooks:
 *   URL: https://YOURSITE/razorpay_webhook.php   Secret: same as Admin > Settings > Razorpay > Webhook secret
 *   Events: payment.captured, payment.failed, order.paid, refund.processed
 * Signature is verified on the RAW body; every event is stored once (event id unique) so replays do nothing.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/secrets.php';
require_once __DIR__ . '/includes/razorpay.php';
require_once __DIR__ . '/includes/donations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$raw = (string) file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
if (!rzp_verify_webhook($raw, $sig)) { http_response_code(400); echo 'bad signature'; exit; }

$ev = json_decode($raw, true);
if (!is_array($ev) || empty($ev['event'])) { http_response_code(400); exit; }
$eventId = $_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] ?? ('h' . hash('sha256', $raw));
$pay     = $ev['payload']['payment']['entity'] ?? [];
$orderId = $pay['order_id'] ?? ($ev['payload']['order']['entity']['id'] ?? null);
$payId   = $pay['id'] ?? null;

try {
    db_query('INSERT INTO payment_events (event_id, event_type, order_id, payment_id, signature_valid, payload) VALUES (?,?,?,?,1,?)',
             [$eventId, substr($ev['event'], 0, 60), $orderId, $payId, $raw]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') { echo 'duplicate'; exit; }   // already received
    throw $e;
}
$eventRowId = (int) db()->lastInsertId();

try {
    $don = $orderId ? db_query('SELECT * FROM donations WHERE razorpay_order_id = ?', [$orderId])->fetch() : false;
    if ($don) {
        switch ($ev['event']) {
            case 'payment.captured':
            case 'order.paid':
                $amountPaise = (int) ($pay['amount'] ?? ($ev['payload']['order']['entity']['amount_paid'] ?? 0));
                donation_finalize((int) $don['id'], [
                    'razorpay_payment_id' => $payId, 'payment_method' => isset($pay['method']) ? substr($pay['method'], 0, 30) : null,
                    'paid_amount' => $amountPaise / 100,
                ]);
                break;
            case 'payment.failed':
                db_query("UPDATE donations SET status='failed' WHERE id = ? AND status = 'pending'", [$don['id']]);
                break;
            case 'refund.processed':
                db_query("UPDATE donations SET status='refunded' WHERE id = ? AND status = 'success'", [$don['id']]);
                if ($don['campaign_id']) campaign_recalc((int) $don['campaign_id']);
                break;
        }
    }
    db_query('UPDATE payment_events SET processed = 1, processed_at = NOW() WHERE id = ?', [$eventRowId]);
    echo 'ok';
} catch (Throwable $e) {
    db_query('UPDATE payment_events SET error = ? WHERE id = ?', [substr($e->getMessage(), 0, 500), $eventRowId]);
    db_query('DELETE FROM payment_events WHERE id = ?', [$eventRowId]);   // let Razorpay retry this event
    error_log('webhook error: ' . $e);
    http_response_code(500);
}
