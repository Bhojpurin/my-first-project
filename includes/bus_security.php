<?php
// includes/bus_security.php — shared security helpers for the bus module (multi-school SaaS).
//
//  • Rate limiting (per IP / per key) backed by MySQL, so it works on shared hosting and across PHP workers.
//  • Driver phone pairing: the admin creates a ONE-TIME link (24 h). The phone exchanges it for its own
//    device token; the link is dead afterwards. The page never carries the bus API key, so a forwarded link,
//    browser history or a screenshot cannot be used to read children's home locations.
//    Tokens are stored only as SHA-256 hashes; each paired phone can be revoked by the admin.
//  • The bus API key (GPS hardware / GPSLogger) can only WRITE positions; it can never read student data.
//
// Every lookup returns the bus together with ITS school_id; callers must use that school_id and nothing
// from the request — that is what keeps one school's data away from another school.

require_once __DIR__ . '/bus_cache.php';

const BUS_PAIR_TTL_SEC     = 86400;   // pairing link valid for 24 h, single use
const BUS_DEVICE_IDLE_DAYS = 120;     // a paired phone unused this long stops working (re-pair)

function busClientIp(): string
{
    // REMOTE_ADDR only: X-Forwarded-For is user-controlled unless a trusted proxy sets it.
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Count one hit in a fixed window. Returns true while under $limit.
 * $bucket is any string (e.g. "gpsfail:1.2.3.4"); it is hashed, so keys/IPs are not stored in clear text.
 * Fails OPEN (returns true) if the table is missing, so a missing migration never takes GPS down.
 */
function busRateHit(PDO $pdo, string $bucket, int $limit, int $windowSec): bool
{
    try {
        $k = hash('sha256', $bucket);
        $now = time(); $win = $now - ($now % $windowSec);
        $pdo->prepare("INSERT INTO bus_rate_limits (k, win, n) VALUES (?,?,1)
                       ON DUPLICATE KEY UPDATE n = IF(win = VALUES(win), n + 1, 1), win = VALUES(win)")
            ->execute([$k, $win]);
        $q = $pdo->prepare("SELECT n FROM bus_rate_limits WHERE k=?");
        $q->execute([$k]);
        $n = (int)$q->fetchColumn();
        // Remember "blocked" in the cache, so blocked checks on every request cost no database query
        if ($n >= $limit) busCacheSet('rlb:' . $k . ':' . $win, 1, $windowSec);
        return $n <= $limit;
    } catch (\Throwable $e) { return true; }
}

/** Has this bucket already used up its $limit in the current window? (read-only check: hit #limit+1 is refused) */
function busRateBlocked(PDO $pdo, string $bucket, int $limit, int $windowSec): bool
{
    // No database query: busRateHit() writes the "blocked" flag into the cache when the limit is reached.
    $now = time(); $win = $now - ($now % $windowSec);
    busCacheGet('rlb:' . hash('sha256', $bucket) . ':' . $win, $hit);
    return $hit;
}

/** High-frequency per-phone limits: counted in the cache only (no database write per request). */
function busRateHitFast(string $bucket, int $limit, int $windowSec): bool
{
    $now = time(); $win = $now - ($now % $windowSec);
    $k = 'rlf:' . hash('sha256', $bucket) . ':' . $win;
    if ($rd = busRedis()) {   // atomic across all web servers
        try { $n = $rd->incr('bus:' . $k); if ($n === 1) $rd->expire('bus:' . $k, $windowSec); return $n <= $limit; } catch (\Throwable $e) { return true; }
    }
    if (busApcu()) {
        apcu_add('bus:' . $k, 0, $windowSec);
        return apcu_inc('bus:' . $k) <= $limit;
    }
    $n = (int)busCacheGet($k) + 1;
    busCacheSet($k, $n, $windowSec);
    return $n <= $limit;
}

function busTooMany(): void
{
    http_response_code(429);
    header('Retry-After: 600');
    echo json_encode(['ok' => false, 'msg' => 'Too many attempts. Try again later.']);
    exit;
}

/** Drop cached device lookups of these device ids (after revoke / unpair / bus deactivated). */
function busForgetDevices(PDO $pdo, array $where, array $params): void
{
    try {
        $q = $pdo->prepare("SELECT token_hash FROM bus_driver_devices WHERE " . implode(' AND ', $where));
        $q->execute($params);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $h) busCacheDel('dev:' . $h);
    } catch (\Throwable $e) {}
}

/** Common headers for JSON APIs. */
function busApiHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
}

/** Active bus for a GPS API key (write-only credential), or null. Keys are compared in constant time. */
function busByApiKey(PDO $pdo, string $key): ?array
{
    if ($key === '' || strlen($key) > 128 || !preg_match('/^[A-Za-z0-9_\-]+$/', $key)) return null;
    $ck = 'key:' . hash('sha256', $key);
    $c = busCacheGet($ck, $hit);
    if ($hit && $c) return $c;   // ≤ 30 s; a regenerated key stops working within 30 s
    $q = $pdo->prepare("SELECT id, school_id, bus_name, bus_number, gps_api_key FROM school_buses WHERE gps_api_key=? AND status='active' LIMIT 1");
    $q->execute([$key]);
    $b = $q->fetch();
    if (!$b || !hash_equals((string)$b['gps_api_key'], $key)) return null;
    unset($b['gps_api_key']);
    busCacheSet($ck, $b, 30);
    return $b;
}

/** Active bus for a paired-phone device token, or null. Also refreshes last_seen (at most once a minute). */
function busByDeviceToken(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $ck = 'dev:' . hash('sha256', $token);
    $c = busCacheGet($ck, $hit);
    if ($hit && $c) return $c;   // cached ≤ 30 s; revoke/unpair also clears it at once (busForgetDevice)
    try {
        $q = $pdo->prepare("
            SELECT d.id AS device_id, d.last_seen_at, b.id, b.school_id, b.bus_name, b.bus_number
            FROM bus_driver_devices d
            JOIN school_buses b ON b.id=d.bus_id AND b.school_id=d.school_id AND b.status='active'
            WHERE d.token_hash=? AND d.revoked_at IS NULL
              AND d.last_seen_at > (NOW() - INTERVAL " . (int)BUS_DEVICE_IDLE_DAYS . " DAY)
            LIMIT 1");
        $q->execute([hash('sha256', $token)]);
        $b = $q->fetch();
        if (!$b) return null;
        if (strtotime((string)$b['last_seen_at']) < time() - 60) {
            $pdo->prepare("UPDATE bus_driver_devices SET last_seen_at=NOW(), last_ip=? WHERE id=?")->execute([busClientIp(), (int)$b['device_id']]);
        }
        $b['token_cache_key'] = $ck;
        busCacheSet($ck, $b, 30);
        return $b;
    } catch (\Throwable $e) { return null; }
}

function busDeviceTokenFromRequest(): string
{
    return trim((string)($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? ''));
}

/** Admin: create a one-time pairing code for a bus of THIS school. Returns the raw code (shown once). */
function busCreatePairCode(PDO $pdo, int $schoolId, int $busId, int $userId): string
{
    $code = bin2hex(random_bytes(16));
    // Older unused codes of this bus are invalidated: only the newest link works.
    $pdo->prepare("UPDATE bus_pair_codes SET used_at=NOW() WHERE bus_id=? AND school_id=? AND used_at IS NULL")->execute([$busId, $schoolId]);
    $pdo->prepare("INSERT INTO bus_pair_codes (code_hash, school_id, bus_id, created_by, expires_at) VALUES (?,?,?,?, NOW() + INTERVAL " . (int)BUS_PAIR_TTL_SEC . " SECOND)")
        ->execute([hash('sha256', $code), $schoolId, $busId, $userId]);
    return $code;
}

/** Phone: redeem a pairing code → new device token (raw, returned once) + bus. Single use, atomic. */
function busRedeemPairCode(PDO $pdo, string $code, string $label): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $code)) return null;
    $h = hash('sha256', $code);
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare("SELECT c.id, c.school_id, c.bus_id FROM bus_pair_codes c
                            JOIN school_buses b ON b.id=c.bus_id AND b.school_id=c.school_id AND b.status='active'
                            WHERE c.code_hash=? AND c.used_at IS NULL AND c.expires_at > NOW() FOR UPDATE");
        $q->execute([$h]);
        $c = $q->fetch();
        if (!$c) { $pdo->rollBack(); return null; }
        $pdo->prepare("UPDATE bus_pair_codes SET used_at=NOW() WHERE id=?")->execute([(int)$c['id']]);
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("INSERT INTO bus_driver_devices (school_id, bus_id, token_hash, label, created_at, last_seen_at, last_ip)
                       VALUES (?,?,?,?,NOW(),NOW(),?)")
            ->execute([(int)$c['school_id'], (int)$c['bus_id'], hash('sha256', $token), mb_substr($label, 0, 80), busClientIp()]);
        $pdo->commit();
        return ['token' => $token, 'bus_id' => (int)$c['bus_id'], 'school_id' => (int)$c['school_id']];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Short, harmless device label from the User-Agent (e.g. "Android 13 · Chrome"). */
function busDeviceLabel(): string
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $os = preg_match('/Android [\d.]+/', $ua, $m) ? $m[0] : (stripos($ua, 'iPhone') !== false ? 'iPhone' : (stripos($ua, 'Windows') !== false ? 'Windows' : 'Phone'));
    $br = stripos($ua, 'SamsungBrowser') !== false ? 'Samsung' : (stripos($ua, 'Chrome') !== false ? 'Chrome' : (stripos($ua, 'Firefox') !== false ? 'Firefox' : (stripos($ua, 'Safari') !== false ? 'Safari' : 'Browser')));
    return $os . ' · ' . $br;
}
