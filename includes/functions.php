<?php
// includes/functions.php
// SSZone — Common helper functions

require_once __DIR__ . '/../config/constants.php';

// Sanitize output (XSS safe). Accepts null/int/float too — DB columns such as
// a category's icon or name can come back NULL, and a strict `string` type here
// turned that into a fatal TypeError that blanked the whole page (e.g. the
// editor) instead of just printing an empty value.
function e($str): string {
    if ($str === null || is_array($str) || is_object($str)) return '';
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Shared password strength policy — matches what includes/auth.php's
 * registerUser() and resetPassword() already enforce (min 8 chars, at least
 * one letter and one number). Returns null when the password passes, or a
 * user-facing error string when it doesn't, so every password-set endpoint
 * can share one consistent standard instead of each hand-rolling its own
 * (most of the rest of the codebase still only checks "6 characters, no
 * complexity" — this exists so new/updated call sites can adopt the
 * stronger rule without duplicating the regex).
 */
function validatePasswordStrength(string $password): ?string {
    if (strlen($password) > 1000) return 'Password is too long (max 1000 characters).';
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must be at least 8 characters and contain a letter and a number.';
    }
    return null;
}

// JSON response helper for AJAX endpoints — clears any stray output (PHP warnings, etc.)
// $httpStatus defaults to 200 (every existing call site is unaffected) — pass
// 401 specifically for "you're not logged in / your session died" responses
// so the offline write-queue (assets/js/offline-queue.js) can reliably tell
// "please re-authenticate" apart from an ordinary business-rule rejection
// (bad input, blocked subscription, etc.) via res.status instead of fragile
// message-text matching alone.
function jsonResponse(bool $success, string $message = '', array $extra = [], int $httpStatus = 200): void {
    // Discard any PHP warning/notice output that would corrupt JSON
    if (ob_get_level() > 0) {
        $stray = ob_get_clean();
        if ($stray !== '' && $stray !== false) {
            error_log('jsonResponse stray output discarded: ' . substr($stray, 0, 500));
        }
    }
    http_response_code($httpStatus);
    header('Content-Type: application/json');
    // Every API response here is personalized/session-based (fee balances, subscription
    // status, etc.) and must never be cached by an intermediary proxy (e.g. Varnish) —
    // otherwise a genuinely-successful write (like a subscription renewal) can appear to
    // "not take effect" because a stale cached response keeps being served afterward.
    header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
    header('Pragma: no-cache');
    // INVALID_UTF8_SUBSTITUTE: one bad byte in any string (e.g. a pasted name)
    // used to make json_encode() return false, so the client got an empty body
    // and showed a generic "Network error" instead of the real result.
    $json = json_encode(
        array_merge(['success' => $success, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($json === false) {
        error_log('jsonResponse encode failed: ' . json_last_error_msg());
        $json = json_encode(['success' => false, 'message' => 'Server could not encode the response.']);
    }
    echo $json;
    exit;
}

// CSRF token generate
function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Receipt token — HMAC for public receipt URL access (no DB storage needed)
// New links are signed with a key that includes the server-side secret (not derivable
// from source); links issued before this change stay valid via receiptTokenLegacy().
//
// Rotation: put the new value in SSZONE_LINK_SECRET and move the previous one to
// SSZONE_LINK_SECRET_OLD — links signed with either keep working, so nothing already
// shared with parents/staff breaks. (SSZONE_LINK_SECRET defaults to PAYSLIP_SECRET.)
function linkSecrets(): array {
    static $s = null;
    if ($s === null) {
        $cur = function_exists('sszone_secret') ? sszone_secret('SSZONE_LINK_SECRET') : '';
        if ($cur === '' && defined('PAYSLIP_SECRET')) $cur = (string)PAYSLIP_SECRET;
        $old = function_exists('sszone_secret') ? sszone_secret('SSZONE_LINK_SECRET_OLD') : '';
        $s = ['cur' => $cur, 'old' => $old];
    }
    return $s;
}
// $kind = 'receipt' | 'payslip'. Secret-keyed when a secret exists, else the legacy key.
function signedLinkToken(string $kind, string $data, string $secret): string {
    $key = $secret !== ''
        ? hash_hmac('sha256', "sszone_{$kind}_v2", $secret)
        : md5(SITE_NAME . SESSION_LIFETIME . "sszone_{$kind}_v1");
    return substr(hash_hmac('sha256', $data, $key), 0, 40);
}
function signedLinkValid(string $kind, string $data, string $tok): bool {
    if ($tok === '') return false;
    $s  = linkSecrets();
    $ok = hash_equals(signedLinkToken($kind, $data, $s['cur']), $tok);
    if (!$ok && $s['old'] !== '') $ok = hash_equals(signedLinkToken($kind, $data, $s['old']), $tok);
    // Links issued before the secret-keyed scheme existed.
    return $ok || hash_equals(signedLinkToken($kind, $data, ''), $tok);
}
// ── Receipt links: expiring + revocable ─────────────────────────────────────
// Token = 8 hex chars (expiry, unix time) + 40 hex chars (HMAC over payment id, receipt no,
// the payment's link version and that expiry). All-hex, so it is URL-safe as-is.
//  • Expiry  : RECEIPT_LINK_TTL_DAYS after issue; tampering with the expiry breaks the HMAC.
//  • Revoke  : fee_actions 'revoke_receipt_link' bumps the payment's version in
//              receipt_link_ver — every link issued before that stops working at once, and
//              any link generated afterwards automatically carries the new version.
// Links are regenerated on every fetch (receipt lists, student portal), so a link only
// "goes stale" if it was copied out (e.g. WhatsApp) and the school has not resent it.
// Old-format (40-char, non-expiring) links are rejected unless SSZONE_LEGACY_RECEIPT_LINKS=1
// is set — an emergency switch, not meant to stay on.
if (!defined('RECEIPT_LINK_TTL_DAYS')) define('RECEIPT_LINK_TTL_DAYS', 180);

function receiptLinkVersion(int $paymentId, bool $refresh = false): int {
    static $map = null;
    if ($refresh) $map = null;
    if ($map === null) {
        $map = [];
        try {
            if (class_exists('Database')) {
                foreach (Database::connect()->query("SELECT payment_id, ver FROM receipt_link_ver") as $r) {
                    $map[(int)$r['payment_id']] = (int)$r['ver'];
                }
            }
        } catch (\Throwable $e) { /* table not created yet = nothing revoked */ }
    }
    return $map[$paymentId] ?? 0;
}
function receiptToken(int $paymentId, string $receiptNo): string {
    $exp = time() + RECEIPT_LINK_TTL_DAYS * 86400;
    $ver = receiptLinkVersion($paymentId);
    return sprintf('%08x', $exp)
         . signedLinkToken('receipt', "{$paymentId}:{$receiptNo}:{$ver}:{$exp}", linkSecrets()['cur']);
}
function receiptTokenValid(int $paymentId, string $receiptNo, string $tok): bool {
    if ($tok === '') return false;
    $why = 'bad-signature';
    if (strlen($tok) === 48 && ctype_xdigit($tok)) {
        $exp = hexdec(substr($tok, 0, 8));
        $sig = substr($tok, 8);
        if ($exp < time()) {
            $why = 'expired';
        } else {
            $ver  = receiptLinkVersion($paymentId);
            $data = "{$paymentId}:{$receiptNo}:{$ver}:{$exp}";
            $s    = linkSecrets();
            $ok   = hash_equals(signedLinkToken('receipt', $data, $s['cur']), $sig);
            if (!$ok && $s['old'] !== '') $ok = hash_equals(signedLinkToken('receipt', $data, $s['old']), $sig);
            if ($ok) return true;
            if ($ver > 0) $why = 'revoked-or-bad-signature';
        }
    } elseif (function_exists('sszone_secret') && sszone_secret('SSZONE_LEGACY_RECEIPT_LINKS') === '1'
              && receiptLinkVersion($paymentId) === 0) {
        if (signedLinkValid('receipt', "{$paymentId}:{$receiptNo}", $tok)) return true;
        $why = 'legacy-bad-signature';
    } else {
        $why = 'old-format';
    }
    error_log("RECEIPT-LINK-DENIED payment={$paymentId} reason={$why} ip=" . ($_SERVER['REMOTE_ADDR'] ?? '-'));
    return false;
}
function payslipToken(int $teacherId, string $salaryMonth): string {
    return signedLinkToken('payslip', "{$teacherId}:{$salaryMonth}", linkSecrets()['cur']);
}
function payslipTokenValid(int $teacherId, string $salaryMonth, string $tok): bool {
    return signedLinkValid('payslip', "{$teacherId}:{$salaryMonth}", $tok);
}

// CSRF verify
function verifyCsrf(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF check that needs no client-side change: passes if a valid csrf token was
 * sent (POST csrf_token / X-CSRF-Token header), OR the browser itself vouches the
 * request is same-origin. Sec-Fetch-Site and Origin are set by the browser and
 * cannot be forged or stripped by a cross-site page, unlike the request body.
 * Requests carrying none of these (curl, servers) cannot be driven by a victim's
 * browser, so they are not a CSRF vector and are allowed.
 */
function isSameOriginOrCsrf(): bool {
    $tok = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($tok !== '' && verifyCsrf($tok)) return true;

    $sfs = strtolower($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    if ($sfs !== '') return $sfs === 'same-origin';

    $hosts = array_filter([
        strtolower($_SERVER['HTTP_HOST'] ?? ''),
        strtolower($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''),
        strtolower((string)parse_url(SITE_URL, PHP_URL_HOST)),
    ]);
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
        if (!empty($_SERVER[$h])) {
            $host = strtolower((string)parse_url($_SERVER[$h], PHP_URL_HOST));
            $port = parse_url($_SERVER[$h], PHP_URL_PORT);
            $hp   = $port ? "$host:$port" : $host;
            return in_array($host, $hosts, true) || in_array($hp, $hosts, true);
        }
    }
    return true;
}

// Generate a unique Sales Partner (agent) code — short, unambiguous alphabet
// (no 0/O/1/I/L) so it reads back correctly over phone/WhatsApp. Retries on
// the rare collision since users.agent_code is UNIQUE.
function generateAgentCode(PDO $pdo): string {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $code = 'SS';
        for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $chk = $pdo->prepare("SELECT id FROM users WHERE agent_code = ?");
        $chk->execute([$code]);
        if (!$chk->fetchColumn()) return $code;
    }
    throw new \RuntimeException('Could not generate a unique agent code.');
}

// Validate identifier — email only (mobile login removed: the platform has no
// SMS delivery, so a phone-number identifier could never receive its OTP)
function isValidIdentifier(string $value): bool {
    return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
}

// Redirect helper
function redirectTo(string $path): never {
    header("Location: $path");
    exit;
}
