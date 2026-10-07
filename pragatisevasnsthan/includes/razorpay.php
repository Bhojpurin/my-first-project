<?php
/**
 * razorpay.php - tiny Razorpay REST client (no composer). Keys come from Admin > Settings (cfg()).
 * API base can be overridden with RAZORPAY_API_BASE in .env (used only for local testing).
 */
function rzp_key_id(): string     { return trim(cfg('razorpay_key_id')); }
function rzp_key_secret(): string { return trim(cfg('razorpay_key_secret')); }
function rzp_configured(): bool   { return rzp_key_id() !== '' && rzp_key_secret() !== ''; }
function rzp_mode(): string       { return str_starts_with(rzp_key_id(), 'rzp_live_') ? 'live' : 'test'; }

/** @return array [http_status, decoded_json|null] */
function rzp_request(string $method, string $path, ?array $body = null, ?array $creds = null): array
{
    $base = rtrim((string) env('RAZORPAY_API_BASE', 'https://api.razorpay.com'), '/');
    [$id, $secret] = $creds ?? [rzp_key_id(), rzp_key_secret()];
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $id . ':' . $secret,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $out = curl_exec($ch);
    if ($out === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Razorpay se connect nahi ho paya: ' . $err);
    }
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode((string) $out, true)];
}

/** Create a Razorpay order for $amountRupees. Returns the order array. */
function rzp_create_order(float $amountRupees, string $receipt, array $notes = []): array
{
    [$code, $d] = rzp_request('POST', '/v1/orders', [
        'amount'   => (int) round($amountRupees * 100),
        'currency' => 'INR',
        'receipt'  => substr($receipt, 0, 40),
        'notes'    => $notes,
    ]);
    if ($code !== 200 || empty($d['id'])) {
        throw new RuntimeException('Razorpay order nahi bana (' . $code . '): ' . ($d['error']['description'] ?? 'unknown error'));
    }
    return $d;
}

function rzp_fetch_payment(string $paymentId): ?array
{
    [$code, $d] = rzp_request('GET', '/v1/payments/' . rawurlencode($paymentId));
    return $code === 200 ? $d : null;
}

function rzp_capture(string $paymentId, int $amountPaise): bool
{
    [$code] = rzp_request('POST', '/v1/payments/' . rawurlencode($paymentId) . '/capture', ['amount' => $amountPaise, 'currency' => 'INR']);
    return $code === 200;
}

/** Checkout success signature: HMAC_SHA256(order_id|payment_id, key_secret) */
function rzp_verify_payment_signature(string $orderId, string $paymentId, string $signature): bool
{
    $secret = rzp_key_secret();
    if ($secret === '' || $signature === '') return false;
    return hash_equals(hash_hmac('sha256', $orderId . '|' . $paymentId, $secret), $signature);
}

/** Webhook signature: HMAC_SHA256(raw body, webhook_secret) */
function rzp_verify_webhook(string $rawBody, string $signature): bool
{
    $secret = trim(cfg('razorpay_webhook_secret'));
    if ($secret === '' || $signature === '') return false;
    return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
}
