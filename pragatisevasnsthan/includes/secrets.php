<?php
/**
 * secrets.php - encrypted credentials + config lookup.
 *
 * Credentials (Razorpay secret, SMTP password ...) are typed by the Super Admin in
 * Admin > Settings and stored ENCRYPTED (AES-256-GCM) in the `settings` table.
 * The encryption key = ENCRYPTION_KEY from .env, or (if empty) an auto-created file storage/app.key.
 *
 *   cfg('razorpay_key_id')      -> DB setting, else .env (RAZORPAY_KEY_ID), decrypted if needed
 *   secret_encrypt($plain) / secret_decrypt($stored)
 */
function app_key(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $env = (string) env('ENCRYPTION_KEY', '');
    if ($env !== '') return $key = hash('sha256', $env, true);

    $file = ROOT_PATH . '/storage/app.key';
    if (!is_file($file)) {
        $dir = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (@file_put_contents($file, base64_encode(random_bytes(32)), LOCK_EX) === false) {
            throw new RuntimeException('storage/app.key bana nahi paya - storage folder writable karein ya .env me ENCRYPTION_KEY daalein.');
        }
        @chmod($file, 0600);
    }
    return $key = hash('sha256', trim((string) file_get_contents($file)), true);
}

/** Encrypt text -> "enc:v1:<base64(iv|tag|cipher)>" */
function secret_encrypt(string $plain): string
{
    if ($plain === '') return '';
    $iv  = random_bytes(12);
    $tag = '';
    $ct  = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) throw new RuntimeException('Encryption fail');
    return 'enc:v1:' . base64_encode($iv . $tag . $ct);
}

/** Decrypt a stored value. Plain (not 'enc:v1:') values are returned as they are. Bad data -> ''. */
function secret_decrypt(string $stored): string
{
    if (strncmp($stored, 'enc:v1:', 7) !== 0) return $stored;
    $raw = base64_decode(substr($stored, 7), true);
    if ($raw === false || strlen($raw) < 29) return '';
    $pt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $pt === false ? '' : $pt;
}

/** Binary encrypt for DB columns (PAN): iv|tag|cipher */
function bin_encrypt(string $plain): string
{
    return base64_decode(substr(secret_encrypt($plain), 7));
}
function bin_decrypt(?string $raw): string
{
    return $raw === null || $raw === '' ? '' : secret_decrypt('enc:v1:' . base64_encode($raw));
}

/** Config value: Admin > Settings first, then .env (UPPERCASE key). Secrets come back decrypted. */
function cfg(string $key, string $default = ''): string
{
    $v = secret_decrypt(setting($key, ''));
    if ($v !== '') return $v;
    $e = (string) env(strtoupper($key), '');
    return $e !== '' ? $e : $default;
}

/** Keys that are stored encrypted and never shown back in the admin form. */
const SECRET_KEYS = ['razorpay_key_secret', 'razorpay_webhook_secret', 'smtp_pass'];

/** Signed token for private links (secret, not guessable): link_token('receipt|12') */
function link_token(string $data): string
{
    return substr(hash_hmac('sha256', $data, app_key()), 0, 24);
}
