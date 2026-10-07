<?php
/** helpers.php - small utilities used everywhere. */

/** Escape for HTML output. ALWAYS use e() when printing user/DB data. */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Full URL for a path: url('admin/login.php') */
function url(string $path = ''): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    $to = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $to);
    exit;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    return env('TRUST_PROXY', '0') === '1'
        && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** Visitor IP. Proxy headers are trusted ONLY if TRUST_PROXY=1 in .env */
function client_ip(): string
{
    if (env('TRUST_PROXY', '0') === '1') {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return substr($_SERVER['HTTP_CF_CONNECTING_IP'], 0, 45);
    }
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

// ---- flash messages (shown once on next page) ----
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flash_get(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Argon2id if this PHP has it, else bcrypt. password_verify() works with both. */
function hash_password(string $plain): string
{
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    return password_hash($plain, $algo);
}

/** Site setting from `settings` table: setting('site_name_en') */
function setting(string $key, string $default = ''): string
{
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (db_rows('SELECT skey, svalue FROM settings') as $r) $all[$r['skey']] = (string) $r['svalue'];
    }
    return $all[$key] ?? $default;
}

/** Indian money format: 1860000 -> ₹18,60,000 */
function inr($n, bool $paise = false): string
{
    $n = (float) $n;
    $neg = $n < 0;
    [$int, $dec] = explode('.', number_format(abs($n), 2, '.', ''));
    $last3 = substr($int, -3);
    $rest  = substr($int, 0, -3);
    if ($rest !== '') {
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',';
    }
    $out = '₹' . ($neg ? '-' : '') . $rest . $last3;
    if ($paise || $dec !== '00') $out .= '.' . $dec;
    return $out;
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');
}
