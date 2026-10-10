<?php
// api/gps_update.php — GPS device location update endpoint
// Usage: GET or POST /api/gps_update.php?key=API_KEY&lat=X&lng=Y[&speed=S&heading=H&acc=A&su=ms&age=N]
//   key      per-bus API key (also accepted as "X-API-Key" header, which keeps it out of access logs)
//   lat,lng  position ("lon" also accepted)           speed   km/h, or m/s when su=ms
//   heading  0-360 ("dir" also accepted)              acc     accuracy in metres (fixes worse than 150 m are ignored)
//   age      seconds since the fix was taken — used to back-fill points buffered while the phone had no internet
//   info=1   only validate the key and return the bus name (no location needed)
// Auth: GPS hardware / GPSLogger use the per-bus API key (write-only: it can only add positions).
//       A paired driver phone sends its device token in the "X-Device-Token" header instead (no key in any URL).
// Abuse protection: wrong keys/tokens are rate-limited per IP (20 per 10 min), so keys cannot be guessed.

ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bus_db.php';
require_once __DIR__ . '/../includes/bus_security.php';
ob_end_clean();

const GPS_KEEP_HOURS        = 48;      // how long location history is kept
const GPS_MIN_INTERVAL_SEC  = 2;       // ignore live updates arriving faster than this per bus
const GPS_STILL_METERS      = 5;       // movement below this = "parked": refresh last row instead of adding one
const GPS_MAX_BACKFILL_SEC  = 21600;   // buffered points older than 6 h are dropped
const GPS_BACKFILL_MIN_SEC  = 20;      // 'age' below this is treated as a normal live point
const GPS_MAX_JUMP_MS       = 70.0;    // m/s (~250 km/h): faster than this between two fixes = GPS glitch
const GPS_HIST_EVERY_M      = 100;     // history row at least every 100 m …
const GPS_HIST_EVERY_SEC    = 15;      // … or every 15 s while driving …
const GPS_HIST_STILL_SEC    = 120;     // … or every 2 min while standing (live position still updates every fix)

ignore_user_abort(true); // finish background work even if the device hangs up

busApiHeaders();
header('Access-Control-Allow-Origin: *'); // GPS devices post from anywhere; there are no cookies, so this exposes nothing
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-Device-Token');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; } // CORS preflight

function jOut(array $d): void { echo json_encode($d); exit; }

// Send the response now and keep working afterwards (proximity push, cleanup),
// so the GPS device never waits on slow background work.
function jDone(array $d): void {
    echo json_encode($d);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        @ob_flush();
        @flush();
    }
}

function numOrNull($v): ?float { return ($v !== null && is_numeric($v)) ? (float)$v : null; }

function distanceM(float $la1, float $lo1, float $la2, float $lo2): float {
    $p1 = deg2rad($la1); $p2 = deg2rad($la2);
    $dp = $p2 - $p1;     $dl = deg2rad($lo2 - $lo1);
    $a  = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return 2 * 6371000 * asin(min(1.0, sqrt($a)));
}

// Insert one GPS row. Works before AND after the optional "accuracy" column is added to the table.
// $backfillAge = seconds in the past (buffered point) or null for "now".
function insertFix(PDO $pdo, int $busId, int $schoolId, float $lat, float $lng, float $speed, float $heading, ?float $acc, ?int $backfillAge): void {
    $at = $backfillAge !== null ? 'DATE_SUB(NOW(), INTERVAL ' . (int)$backfillAge . ' SECOND)' : 'NOW()';
    $ok = false;
    try {
        $st = $pdo->prepare("INSERT INTO bus_gps_locations (bus_id, school_id, lat, lng, speed, heading, accuracy, recorded_at)
                             VALUES (?,?,?,?,?,?,?,$at)");
        $ok = $st->execute([$busId, $schoolId, $lat, $lng, $speed, $heading, $acc]);
    } catch (\PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1054) throw $e;   // 1054 = unknown column (migration not run yet)
    }
    if ($ok) return;
    $pdo->prepare("INSERT INTO bus_gps_locations (bus_id, school_id, lat, lng, speed, heading, recorded_at)
                   VALUES (?,?,?,?,?,?,$at)")
        ->execute([$busId, $schoolId, $lat, $lng, $speed, $heading]);
}

// Keep the one-row-per-bus "latest position" table (bus_live) current. Readers (student portal, admin map,
// watchdog) use it instead of scanning the big history table. Silently skipped until the table exists.
// $still: the bus is standing (keeps the time it stopped in still_since → admin sees "stopped for 6 min").
function upsertLive(PDO $pdo, int $busId, int $schoolId, float $lat, float $lng, float $speed, float $heading, ?float $acc, bool $still = false): void {
    try {
        $pdo->prepare("INSERT INTO bus_live (bus_id, school_id, lat, lng, speed, heading, accuracy, recorded_at, still_since)
                       VALUES (?,?,?,?,?,?,?,NOW(),NULL)
                       ON DUPLICATE KEY UPDATE school_id=VALUES(school_id), lat=VALUES(lat), lng=VALUES(lng), speed=VALUES(speed),
                           heading=VALUES(heading), accuracy=VALUES(accuracy), recorded_at=NOW(),
                           still_since=IF(?, COALESCE(still_since, NOW()), NULL)")
            ->execute([$busId, $schoolId, $lat, $lng, $speed, $heading, $acc, $still ? 1 : 0]);
        return;
    } catch (\Throwable $e) {
        $code = (int)($e->errorInfo[1] ?? 0);
        if ($code === 1146) return;                                   // table missing (migration not run)
        if ($code !== 1054) { error_log('gps_update bus_live: ' . $e->getMessage()); return; }
    }
    try {   // 1054: still_since column not added yet → old form
        $pdo->prepare("INSERT INTO bus_live (bus_id, school_id, lat, lng, speed, heading, accuracy, recorded_at)
                       VALUES (?,?,?,?,?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE school_id=VALUES(school_id), lat=VALUES(lat), lng=VALUES(lng), speed=VALUES(speed),
                           heading=VALUES(heading), accuracy=VALUES(accuracy), recorded_at=NOW()")
            ->execute([$busId, $schoolId, $lat, $lng, $speed, $heading, $acc]);
    } catch (\Throwable $e) { error_log('gps_update bus_live: ' . $e->getMessage()); }
}

$key   = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ($_REQUEST['key'] ?? '')));
$token = busDeviceTokenFromRequest();
$lat = numOrNull($_REQUEST['lat'] ?? null);
$lng = numOrNull($_REQUEST['lng'] ?? ($_REQUEST['lon'] ?? null)); // GPS apps use 'lon' or 'lng'

if ($key === '' && $token === '') jOut(['ok'=>false,'msg'=>'API key required']);

// Resolve the bus (and its school) from the credential. Wrong credentials are rate-limited per IP.
function gpsAuth(PDO $pdo, string $key, string $token): array {
    $ip = busClientIp();
    if (busRateBlocked($pdo, 'gpsfail:' . $ip, 20, 600)) busTooMany();
    $bus = $token !== '' ? busByDeviceToken($pdo, $token) : busByApiKey($pdo, $key);
    if (!$bus) {
        busRateHit($pdo, 'gpsfail:' . $ip, 20, 600);
        if ($token !== '') { http_response_code(401); jOut(['ok'=>false,'code'=>'unpaired','msg'=>'Phone not paired']); }
        jOut(['ok'=>false,'msg'=>'Invalid or inactive bus key']);
    }
    return $bus;
}

// ?info=1 : validate the credential and return the bus name. Stores nothing.
if (($_REQUEST['info'] ?? '') === '1') {
    try {
        $b = gpsAuth(busDb(Database::connect()), $key, $token);
        jOut(['ok'=>true,'bus_name'=>$b['bus_name'],'bus_number'=>$b['bus_number']]);
    } catch (\Throwable $e) {
        error_log('gps_update info: '.$e->getMessage());
        jOut(['ok'=>false,'msg'=>'Server error.']);
    }
}

if ($lat === null || $lng === null)     jOut(['ok'=>false,'msg'=>'lat and lng are required']);
if (abs($lat) > 90 || abs($lng) > 180)  jOut(['ok'=>false,'msg'=>'Invalid coordinates']);
if ($lat === 0.0 && $lng === 0.0)       jOut(['ok'=>false,'msg'=>'Zero coordinates rejected']);

// Optional accuracy (metres): ignore very poor fixes that would make the bus "jump".
$acc = numOrNull($_REQUEST['acc'] ?? null);
if ($acc !== null && $acc > 150)        jOut(['ok'=>false,'msg'=>'Low GPS accuracy ('.round($acc).' m) — ignored']);
if ($acc !== null && $acc <= 0)         $acc = null;   // 0 = "unknown" in some apps

$speedRaw = numOrNull($_REQUEST['speed'] ?? null) ?? 0.0;
if (($_REQUEST['su'] ?? '') === 'ms') $speedRaw *= 3.6;   // GPSLogger & browsers report m/s; we store km/h
$speed   = round(min(400.0, max(0.0, $speedRaw)), 2);
$heading = fmod(numOrNull($_REQUEST['heading'] ?? ($_REQUEST['dir'] ?? null)) ?? 0.0, 360.0);
if ($heading < 0) $heading += 360.0;
$heading = round($heading, 2);

// Buffered point from a phone that was offline: how many seconds ago the fix was taken
$ageSec = numOrNull($_REQUEST['age'] ?? null);
$backfill = null;
if ($ageSec !== null && $ageSec >= GPS_BACKFILL_MIN_SEC) {
    if ($ageSec > GPS_MAX_BACKFILL_SEC) jOut(['ok'=>false,'msg'=>'Point too old — dropped']);
    $backfill = (int)round($ageSec);
}

try {
    $pdo = busDb(Database::connect());

    $bus = gpsAuth($pdo, $key, $token);
    $busId    = (int)$bus['id'];
    $schoolId = (int)$bus['school_id'];

    // Buffered point: just store it at its real time. No rate-limit / parked-refresh / push for old points.
    if ($backfill !== null) {
        insertFix($pdo, $busId, $schoolId, $lat, $lng, $speed, $heading, $acc, $backfill);
        jOut(['ok'=>true,'bus_id'=>$busId,'saved'=>'backfilled']);
    }

    // Last accepted position of this bus: from the cache (no query), else from bus_live (1-row lookup).
    // The big history table is never read on the hot path.
    $now  = time();
    $last = busCacheGet('gpslast:' . $busId);
    if (!$last) {
        try {
            $q = $pdo->prepare("SELECT lat, lng, heading, UNIX_TIMESTAMP(recorded_at) AS t FROM bus_live WHERE bus_id=? AND school_id=?");
            $q->execute([$busId, $schoolId]);
            if ($r = $q->fetch()) $last = ['lat' => (float)$r['lat'], 'lng' => (float)$r['lng'], 't' => (int)$r['t'], 'hdg' => (float)$r['heading'],
                                         'hlat' => (float)$r['lat'], 'hlng' => (float)$r['lng'], 'ht' => (int)$r['t'], 'hdg_h' => (float)$r['heading']];
        } catch (\Throwable $e) { $last = null; }
    }
    $age = $last ? max(0, $now - (int)$last['t']) : null;

    if ($last && $age < GPS_MIN_INTERVAL_SEC) {
        jOut(['ok'=>false,'msg'=>'Too frequent — minimum '.GPS_MIN_INTERVAL_SEC.'s between updates']);
    }
    // Per-bus flood guard (a stolen key cannot fill the database), counted in the cache
    if (!busRateHitFast('gpsbus:' . $busId, 60, 60)) busTooMany();

    $dist = $last ? distanceM($last['lat'], $last['lng'], $lat, $lng) : 0.0;

    // Glitch filter: a teleport (> ~250 km/h) shortly after the previous fix is a bad GPS reading.
    // The window is short (2 min), so a genuinely moved device is never blocked for long.
    if ($last && $age < 120 && $dist > 300 && $dist / max(1, $age) > GPS_MAX_JUMP_MS) {
        jOut(['ok'=>false,'msg'=>'Implausible jump ignored']);
    }

    // History (trail, trip report) keeps a row only when it adds information: a turn, 100 m of road,
    // 15 s of driving or 2 min of standing. Every fix still updates bus_live (what everyone sees live).
    $hDist = $last ? distanceM($last['hlat'], $last['hlng'], $lat, $lng) : INF;
    $hAge  = $last ? $now - (int)$last['ht'] : PHP_INT_MAX;
    $turn  = $last ? abs(fmod(abs($heading - (float)$last['hdg_h']) + 180, 360) - 180) : 0;
    $store = !$last || $hDist >= GPS_HIST_EVERY_M || ($speed >= 3 && $hAge >= GPS_HIST_EVERY_SEC)
          || $hAge >= GPS_HIST_STILL_SEC || ($turn >= 30 && $hDist >= 20 && $speed >= 3);
    $mode = 'live';
    if ($store) { insertFix($pdo, $busId, $schoolId, $lat, $lng, $speed, $heading, $acc, null); $mode = 'inserted'; }

    $still = $speed < 3 && $dist < 25;
    upsertLive($pdo, $busId, $schoolId, $lat, $lng, $speed, $heading, $acc, $still);
    busCacheSet('gpslast:' . $busId, ['lat' => $lat, 'lng' => $lng, 't' => $now, 'hdg' => $heading,
        'hlat' => $store ? $lat : $last['hlat'], 'hlng' => $store ? $lng : $last['hlng'], 'ht' => $store ? $now : (int)$last['ht'],
        'hdg_h' => $store ? $heading : (float)$last['hdg_h']], 3600);

    jDone(['ok'=>true,'bus_id'=>$busId,'ts'=>date('Y-m-d H:i:s'),'saved'=>$mode]);
} catch (\Throwable $e) {
    error_log('gps_update: '.$e->getMessage());
    jOut(['ok'=>false,'msg'=>'Server error.']);
}

// ── Everything below runs AFTER the device already got its response ──────────

// Proximity push — never let a push/crypto hiccup break GPS ingestion. At most every 8 s per bus.
try {
    if (!busCacheGet('prox:' . $busId)) {
        busCacheSet('prox:' . $busId, 1, 8);
        require_once __DIR__ . '/../includes/bus_proximity.php';
        checkBusProximityPush($pdo, $busId, $schoolId, $lat, $lng, $speed, $acc);
    }
} catch (\Throwable $e) {
    error_log('gps_update proximity: ' . $e->getMessage());
}

// Safety alerts: overspeed, school gate in/out, leaving the everyday road (per-school settings)
try {
    require_once __DIR__ . '/../includes/bus_alerts.php';
    busAlertCheck($pdo, $busId, $schoolId, $lat, $lng, $speed, $acc);
} catch (\Throwable $e) {
    error_log('gps_update alerts: ' . $e->getMessage());
}

// Retention: delete history older than GPS_KEEP_HOURS. Runs on ~1 in 50 requests,
// in small batches, so it never slows down or locks the table on a single update.
try {
    if (mt_rand(1, 200) === 1) {
        $pdo->prepare("
            DELETE FROM bus_gps_locations
            WHERE school_id=? AND bus_id=? AND recorded_at < (NOW() - INTERVAL ".GPS_KEEP_HOURS." HOUR)
            LIMIT 5000
        ")->execute([$schoolId, $busId]);
    }
} catch (\Throwable $e) {
    error_log('gps_update prune: ' . $e->getMessage());
}