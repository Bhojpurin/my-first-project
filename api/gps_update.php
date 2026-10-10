<?php
// api/gps_update.php — GPS device location update endpoint
// Usage: GET or POST /api/gps_update.php?key=API_KEY&lat=X&lng=Y[&speed=S&heading=H&acc=A&su=ms&age=N]
//   key      per-bus API key (also accepted as "X-API-Key" header, which keeps it out of access logs)
//   lat,lng  position ("lon" also accepted)           speed   km/h, or m/s when su=ms
//   heading  0-360 ("dir" also accepted)              acc     accuracy in metres (fixes worse than 150 m are ignored)
//   age      seconds since the fix was taken — used to back-fill points buffered while the phone had no internet
//   info=1   only validate the key and return the bus name (no location needed)
// GPS hardware authenticates via per-bus API key — no user session needed.

ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
ob_end_clean();

const GPS_KEEP_HOURS        = 48;      // how long location history is kept
const GPS_MIN_INTERVAL_SEC  = 2;       // ignore live updates arriving faster than this per bus
const GPS_STILL_METERS      = 5;       // movement below this = "parked": refresh last row instead of adding one
const GPS_MAX_BACKFILL_SEC  = 21600;   // buffered points older than 6 h are dropped
const GPS_BACKFILL_MIN_SEC  = 20;      // 'age' below this is treated as a normal live point
const GPS_MAX_JUMP_MS       = 70.0;    // m/s (~250 km/h): faster than this between two fixes = GPS glitch

ignore_user_abort(true); // finish background work even if the device hangs up

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *'); // GPS device may call from any IP
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

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
function upsertLive(PDO $pdo, int $busId, int $schoolId, float $lat, float $lng, float $speed, float $heading, ?float $acc): void {
    try {
        $pdo->prepare("INSERT INTO bus_live (bus_id, school_id, lat, lng, speed, heading, accuracy, recorded_at)
                       VALUES (?,?,?,?,?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE school_id=VALUES(school_id), lat=VALUES(lat), lng=VALUES(lng), speed=VALUES(speed),
                           heading=VALUES(heading), accuracy=VALUES(accuracy), recorded_at=NOW()")
            ->execute([$busId, $schoolId, $lat, $lng, $speed, $heading, $acc]);
    } catch (\Throwable $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1146) error_log('gps_update bus_live: ' . $e->getMessage());   // 1146 = table missing
    }
}

$key = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ($_REQUEST['key'] ?? '')));
$lat = numOrNull($_REQUEST['lat'] ?? null);
$lng = numOrNull($_REQUEST['lng'] ?? ($_REQUEST['lon'] ?? null)); // GPS apps use 'lon' or 'lng'

if ($key === '' || strlen($key) > 128)  jOut(['ok'=>false,'msg'=>'API key required']);

// ?info=1&key=... : validate a key and return the bus name (used by driver_tracker.php). Stores nothing.
if (($_REQUEST['info'] ?? '') === '1') {
    try {
        $pdo = Database::connect();
        $q = $pdo->prepare("SELECT bus_name, bus_number FROM school_buses WHERE gps_api_key=? AND status='active' LIMIT 1");
        $q->execute([$key]);
        $b = $q->fetch();
        if (!$b) jOut(['ok'=>false,'msg'=>'Invalid or inactive bus key']);
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
    $pdo = Database::connect();

    // Find active bus by API key
    $s = $pdo->prepare("SELECT id, school_id FROM school_buses WHERE gps_api_key=? AND status='active' LIMIT 1");
    $s->execute([$key]);
    $bus = $s->fetch();
    if (!$bus) jOut(['ok'=>false,'msg'=>'Invalid or inactive bus key']);
    $busId    = (int)$bus['id'];
    $schoolId = (int)$bus['school_id'];

    // Buffered point: just store it at its real time. No rate-limit / parked-refresh / push for old points.
    if ($backfill !== null) {
        insertFix($pdo, $busId, $schoolId, $lat, $lng, $speed, $heading, $acc, $backfill);
        jOut(['ok'=>true,'bus_id'=>$busId,'saved'=>'backfilled']);
    }

    // Latest stored fix for this bus (used for rate-limit, glitch filter and parked-bus detection)
    $ls = $pdo->prepare("
        SELECT id, lat, lng, TIMESTAMPDIFF(SECOND, recorded_at, NOW()) AS age
        FROM bus_gps_locations
        WHERE school_id=? AND bus_id=?
        ORDER BY recorded_at DESC, id DESC
        LIMIT 1
    ");
    $ls->execute([$schoolId, $busId]);
    $last = $ls->fetch();

    if ($last && (int)$last['age'] < GPS_MIN_INTERVAL_SEC) {
        jOut(['ok'=>false,'msg'=>'Too frequent — minimum '.GPS_MIN_INTERVAL_SEC.'s between updates']);
    }

    $dist = $last ? distanceM((float)$last['lat'], (float)$last['lng'], $lat, $lng) : 0.0;

    // Glitch filter: a teleport (> ~250 km/h) shortly after the previous fix is a bad GPS reading.
    // The window is short (2 min), so a genuinely moved device is never blocked for long.
    if ($last && (int)$last['age'] < 120 && $dist > 300 && $dist / max(1, (int)$last['age']) > GPS_MAX_JUMP_MS) {
        jOut(['ok'=>false,'msg'=>'Implausible jump ignored']);
    }

    // Parked bus: just refresh the timestamp of the last row instead of piling up identical rows.
    $mode = 'inserted';
    if ($last && (int)$last['age'] < 300 && $dist < GPS_STILL_METERS) {
        $pdo->prepare("UPDATE bus_gps_locations SET recorded_at=NOW(), speed=?, heading=? WHERE id=?")
            ->execute([$speed, $heading, (int)$last['id']]);
        $mode = 'refreshed';
    } else {
        insertFix($pdo, $busId, $schoolId, $lat, $lng, $speed, $heading, $acc, null);
    }

    upsertLive($pdo, $busId, $schoolId, $lat, $lng, $speed, $heading, $acc);

    jDone(['ok'=>true,'bus_id'=>$busId,'ts'=>date('Y-m-d H:i:s'),'saved'=>$mode]);
} catch (\Throwable $e) {
    error_log('gps_update: '.$e->getMessage());
    jOut(['ok'=>false,'msg'=>'Server error.']);
}

// ── Everything below runs AFTER the device already got its response ──────────

// Proximity push — never let a push/crypto hiccup break GPS ingestion.
try {
    require_once __DIR__ . '/../includes/bus_proximity.php';
    checkBusProximityPush($pdo, $busId, $schoolId, $lat, $lng, $speed, $acc);
} catch (\Throwable $e) {
    error_log('gps_update proximity: ' . $e->getMessage());
}

// Retention: delete history older than GPS_KEEP_HOURS. Runs on ~1 in 50 requests,
// in small batches, so it never slows down or locks the table on a single update.
try {
    if (mt_rand(1, 50) === 1) {
        $pdo->prepare("
            DELETE FROM bus_gps_locations
            WHERE school_id=? AND bus_id=? AND recorded_at < (NOW() - INTERVAL ".GPS_KEEP_HOURS." HOUR)
            LIMIT 5000
        ")->execute([$schoolId, $busId]);
    }
} catch (\Throwable $e) {
    error_log('gps_update prune: ' . $e->getMessage());
}