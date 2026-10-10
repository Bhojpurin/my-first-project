<?php
// api/bus_actions.php — School Bus Fleet & Route Management API
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bus_db.php';
require_once __DIR__ . '/../includes/activity_log.php';

if (empty($_SESSION['logged_in']) || !in_array($_SESSION['role'], [ROLE_SCHOOL_ADMIN, ROLE_TEACHER])) {
    ob_end_clean(); header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Permission denied.']); exit;
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$action   = $_REQUEST['action'] ?? '';
$schoolId = (int)$_SESSION['school_id'];
$userId   = (int)$_SESSION['user_id'];
$isAdmin  = ($_SESSION['role'] === ROLE_SCHOOL_ADMIN);
$pdo      = busDb(Database::connect());

function jBus(bool $ok, string $msg = '', array $extra = []): void {
    echo json_encode(array_merge(['success'=>$ok,'message'=>$msg], $extra)); exit;
}

function csrfBus(): void {
    if (!verifyCsrf($_POST['csrf_token'] ?? ''))
        jBus(false, 'Session expired. Please reload.');
}

const BUS_MAX_SHIFTS = 5;
const BUS_DB_SETUP_MSG = 'Database update nahi ho paaya — server ke error log mein "bus_schema" dekhein (DB user ko CREATE/ALTER ki permission chahiye).';

// Normalise a time input ("HH:MM" or "HH:MM:SS") to "HH:MM:SS", or null if empty/invalid.
function busTime($v): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $v, $m)) return null;
    return sprintf('%02d:%02d:%02d', (int)$m[1], (int)$m[2], (int)($m[3] ?? 0));
}

// Column name for shift N: shift 1 => pickup_time, shift 2 => pickup_time2, ...
function busCol(string $base, int $n): string {
    return $n === 1 ? $base : $base . $n;
}

// Latest GPS fix per bus (one row per bus, even if two fixes share a timestamp).
function busLatestLocations(PDO $pdo, int $schoolId): array {
    // Fast path: bus_live (one row per bus). Falls back to scanning history if the table is missing/empty.
    try {
        $st = $pdo->prepare("SELECT l.*, TIMESTAMPDIFF(SECOND, l.recorded_at, NOW()) AS age_seconds FROM bus_live l WHERE l.school_id=?");
        // still_since (when the bus stopped) exists from migration v2 on; read it separately so older tables keep working
        try {
            $ss = $pdo->prepare("SELECT bus_id, TIMESTAMPDIFF(SECOND, still_since, NOW()) AS s FROM bus_live WHERE school_id=? AND still_since IS NOT NULL");
            $ss->execute([$schoolId]);
            $stillSec = array_column($ss->fetchAll(), 's', 'bus_id');
        } catch (\Throwable $e) { $stillSec = []; }
        $st->execute([$schoolId]);
        $out = [];
        foreach ($st->fetchAll() as $r) { $r['still_seconds'] = $stillSec[$r['bus_id']] ?? null; $out[(int)$r['bus_id']] = $r; }
        if ($out) return $out;
    } catch (\Throwable $e) { /* table not created yet */ }

    $st = $pdo->prepare("
        SELECT g.*,
               TIMESTAMPDIFF(SECOND, g.recorded_at, NOW()) AS age_seconds
        FROM bus_gps_locations g
        JOIN (
            SELECT bus_id, MAX(recorded_at) AS m
            FROM bus_gps_locations WHERE school_id=? GROUP BY bus_id
        ) x ON x.bus_id=g.bus_id AND x.m=g.recorded_at
        WHERE g.school_id=?
    ");
    $st->execute([$schoolId, $schoolId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $k = (int)$r['bus_id'];
        if (!isset($out[$k])) $out[$k] = $r;
    }
    return $out;
}

function busGpsStatus(?int $age): string {
    if ($age === null) return 'never';
    return $age < 120 ? 'live' : ($age < 300 ? 'recent' : 'offline');
}

function _slog(string $activity, string $type = 'other'): void {
    global $schoolId, $userId;
    logSchoolActivity((int)$schoolId, (int)$userId,
        $_SESSION['name']??'', $_SESSION['role']??'', $activity, $type);
}


// ─────────────────────────────────────────────────────────────────────────────
// FLEET
// ─────────────────────────────────────────────────────────────────────────────

// ── get_fleet ─────────────────────────────────────────────────────────────────
if ($action === 'get_fleet') {
    $buses = $pdo->prepare("
        SELECT b.*,
            (SELECT COUNT(*) FROM bus_route_assignments a WHERE a.bus_id=b.id AND a.school_id=b.school_id AND a.status='active') AS route_count
        FROM school_buses b
        WHERE b.school_id=?
        ORDER BY b.bus_name
    ");
    $buses->execute([$schoolId]);
    $rows = $buses->fetchAll();
    $loc  = busLatestLocations($pdo, $schoolId);
    foreach ($rows as &$r) {
        $l   = $loc[(int)$r['id']] ?? null;
        $age = $l ? max(0, (int)$l['age_seconds']) : null;
        $r['gps_api_key'] = $isAdmin ? $r['gps_api_key'] : null; // hide key from teachers
        $r['last_lat']    = $l['lat'] ?? null;
        $r['last_lng']    = $l['lng'] ?? null;
        $r['last_seen']   = $l['recorded_at'] ?? null;
        $r['gps_status']  = busGpsStatus($age);
        $r['gps_age']     = $age;
    }
    unset($r);
    jBus(true, '', ['buses' => $rows]);
}

// ── save_bus ──────────────────────────────────────────────────────────────────
if ($action === 'save_bus') {
    if (!$isAdmin) jBus(false, 'Admin only.');
    csrfBus();
    $id         = (int)($_POST['id']           ?? 0);
    $name       = trim($_POST['bus_name']       ?? '');
    $number     = trim($_POST['bus_number']     ?? '');
    $capacity   = max(1, (int)($_POST['capacity'] ?? 40));
    $deviceId   = trim($_POST['gps_device_id'] ?? '');
    $notes      = trim($_POST['notes']          ?? '');
    $status     = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

    if ($name === '')   jBus(false, 'Bus name is required.');
    if ($number === '') jBus(false, 'Bus number is required.');

    if ($id > 0) {
        $chk = $pdo->prepare("SELECT id FROM school_buses WHERE id=? AND school_id=?");
        $chk->execute([$id, $schoolId]);
        if (!$chk->fetch()) jBus(false, 'Bus not found.');
        $pdo->prepare("UPDATE school_buses SET bus_name=?,bus_number=?,capacity=?,gps_device_id=?,notes=?,status=? WHERE id=? AND school_id=?")
            ->execute([$name, $number, $capacity, $deviceId ?: null, $notes ?: null, $status, $id, $schoolId]);
        _slog("Bus updated: \"$name\" ($number)", 'update');
        jBus(true, 'Bus updated successfully.');
    } else {
        // Generate unique API key for GPS device
        $apiKey = bin2hex(random_bytes(24));
        $pdo->prepare("INSERT INTO school_buses (school_id,bus_name,bus_number,capacity,gps_device_id,gps_api_key,notes,status) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$schoolId, $name, $number, $capacity, $deviceId ?: null, $apiKey, $notes ?: null, $status]);
        $newId = (int)$pdo->lastInsertId();
        _slog("Bus added: \"$name\" ($number)", 'create');
        jBus(true, 'Bus added successfully.', ['id'=>$newId,'gps_api_key'=>$apiKey]);
    }
}

// ── delete_bus ────────────────────────────────────────────────────────────────
if ($action === 'delete_bus') {
    if (!$isAdmin) jBus(false, 'Admin only.');
    csrfBus();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jBus(false, 'Invalid ID.');
    $chk = $pdo->prepare("SELECT id FROM school_buses WHERE id=? AND school_id=?");
    $chk->execute([$id, $schoolId]);
    if (!$chk->fetch()) jBus(false, 'Bus not found.');

    $used = $pdo->prepare("SELECT COUNT(*) FROM bus_route_assignments WHERE bus_id=? AND school_id=? AND status='active'");
    $used->execute([$id, $schoolId]);
    if ((int)$used->fetchColumn() > 0) jBus(false, 'Cannot delete: bus is assigned to active routes. Remove assignments first.');

    $pdo->prepare("DELETE FROM bus_gps_locations WHERE bus_id=? AND school_id=?")->execute([$id, $schoolId]);
    try { $pdo->prepare("DELETE FROM bus_live WHERE bus_id=? AND school_id=?")->execute([$id, $schoolId]); } catch (\Throwable $e) {}
    try {   // a deleted bus must not leave working phones or links behind
        $pdo->prepare("UPDATE bus_driver_devices SET revoked_at=NOW() WHERE bus_id=? AND school_id=? AND revoked_at IS NULL")->execute([$id, $schoolId]);
        $pdo->prepare("UPDATE bus_pair_codes SET used_at=NOW() WHERE bus_id=? AND school_id=? AND used_at IS NULL")->execute([$id, $schoolId]);
    } catch (\Throwable $e) {}
    $pdo->prepare("DELETE FROM school_buses WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
    _slog("Bus deleted: ID #$id", 'delete');
    jBus(true, 'Bus deleted.');
}

// ── regen_key ─────────────────────────────────────────────────────────────────
if ($action === 'regen_key') {
    if (!$isAdmin) jBus(false, 'Admin only.');
    csrfBus();
    $id = (int)($_POST['id'] ?? 0);
    $chk = $pdo->prepare("SELECT id FROM school_buses WHERE id=? AND school_id=?");
    $chk->execute([$id, $schoolId]);
    if (!$chk->fetch()) jBus(false, 'Bus not found.');
    $newKey = bin2hex(random_bytes(24));
    $pdo->prepare("UPDATE school_buses SET gps_api_key=? WHERE id=? AND school_id=?")->execute([$newKey, $id, $schoolId]);
    _slog("GPS API key regenerated for bus #$id", 'update');
    jBus(true, 'API key regenerated.', ['gps_api_key'=>$newKey]);
}

// ─────────────────────────────────────────────────────────────────────────────
// DRIVER PHONES (one-time pairing links, paired devices, revoke)
// ─────────────────────────────────────────────────────────────────────────────

// ── driver_pair_link: new ONE-TIME link for a bus of this school (24 h). Older unused links stop working. ──
if ($action === 'driver_pair_link') {
    if (!$isAdmin) jBus(false, 'Admin only.');
    csrfBus();
    require_once __DIR__ . '/../includes/bus_security.php';
    $busId = (int)($_POST['bus_id'] ?? 0);
    $chk = $pdo->prepare("SELECT id FROM school_buses WHERE id=? AND school_id=? AND status='active'");
    $chk->execute([$busId, $schoolId]);
    if (!$chk->fetch()) jBus(false, 'Bus not found or inactive.');
    if (!busRateHit($pdo, 'pairmk:' . $schoolId, 60, 3600)) jBus(false, 'Too many links created. Try again later.');
    try { $code = busCreatePairCode($pdo, $schoolId, $busId, $userId); }
    catch (\Throwable $e) { error_log('bus pair: ' . $e->getMessage()); jBus(false, BUS_DB_SETUP_MSG); }
    _slog("Driver pairing link created for bus #$busId", 'update');
    jBus(true, '', ['code' => $code, 'valid_hours' => (int)(BUS_PAIR_TTL_SEC / 3600)]);
}

// ── driver_devices: paired phones of this school ─────────────────────────────
if ($action === 'driver_devices') {
    try {
        $st = $pdo->prepare("SELECT d.id, d.bus_id, d.label, d.created_at, d.last_seen_at,
                                    TIMESTAMPDIFF(MINUTE, d.last_seen_at, NOW()) AS idle_min
                             FROM bus_driver_devices d WHERE d.school_id=? AND d.revoked_at IS NULL ORDER BY d.bus_id, d.last_seen_at DESC");
        $st->execute([$schoolId]);
        jBus(true, '', ['devices' => $st->fetchAll()]);
    } catch (\Throwable $e) { jBus(true, '', ['devices' => []]); }
}

// ── driver_revoke: unpair one phone (or all phones of a bus with bus_id) ─────
if ($action === 'driver_revoke') {
    if (!$isAdmin) jBus(false, 'Admin only.');
    csrfBus();
    $id = (int)($_POST['id'] ?? 0); $busId = (int)($_POST['bus_id'] ?? 0);
    if ($id) $pdo->prepare("UPDATE bus_driver_devices SET revoked_at=NOW() WHERE id=? AND school_id=? AND revoked_at IS NULL")->execute([$id, $schoolId]);
    elseif ($busId) $pdo->prepare("UPDATE bus_driver_devices SET revoked_at=NOW() WHERE bus_id=? AND school_id=? AND revoked_at IS NULL")->execute([$busId, $schoolId]);
    else jBus(false, 'Invalid request.');
    _slog($id ? "Driver phone #$id unpaired" : "All driver phones of bus #$busId unpaired", 'update');
    jBus(true, 'Phone hata diya gaya. Ab wo is bus ka data nahi dekh sakta.');
}

// ─────────────────────────────────────────────────────────────────────────────
// ROUTE ASSIGNMENTS
// ─────────────────────────────────────────────────────────────────────────────

// ── get_assignments ───────────────────────────────────────────────────────────
if ($action === 'get_assignments') {
    $rows = $pdo->prepare("
        SELECT a.*,
            vr.route_name, vr.from_location, vr.to_location, vr.distance_km,
            b.bus_name, b.bus_number, b.capacity,
            u.name AS driver_name,
            u.phone AS driver_phone, t.employee_code AS driver_emp,
            (SELECT COUNT(*) FROM student_van_assignments sv WHERE sv.van_route_id=a.route_id AND sv.school_id=a.school_id) AS student_count
        FROM bus_route_assignments a
        JOIN van_routes vr ON vr.id=a.route_id
        JOIN school_buses b ON b.id=a.bus_id
        LEFT JOIN teachers t ON t.id=a.driver_id
        LEFT JOIN users u ON u.id=t.user_id
        WHERE a.school_id=?
        ORDER BY vr.route_name
    ");
    $rows->execute([$schoolId]);
    jBus(true, '', ['assignments' => $rows->fetchAll()]);
}

// ── save_assignment ───────────────────────────────────────────────────────────
if ($action === 'save_assignment') {
    if (!$isAdmin) jBus(false, 'Admin only.');
    csrfBus();
    $id          = (int)($_POST['id']          ?? 0);
    $routeId     = (int)($_POST['route_id']    ?? 0);
    $busId       = (int)($_POST['bus_id']      ?? 0);
    $driverId    = (int)($_POST['driver_id']   ?? 0) ?: null;
    $shiftCount  = (int)($_POST['shift_count'] ?? 1);
    if ($shiftCount < 1 || $shiftCount > BUS_MAX_SHIFTS) $shiftCount = 1;
    $days        = trim($_POST['days'] ?? 'Mon,Tue,Wed,Thu,Fri,Sat');
    $status      = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

    if (!$routeId || !$busId) jBus(false, 'Route and bus are required.');

    // Shift times: shifts beyond $shiftCount are stored as NULL
    $times = [];
    for ($n = 1; $n <= BUS_MAX_SHIFTS; $n++) {
        $pk = busCol('pickup_time', $n);
        $dr = busCol('drop_time',   $n);
        $times[$pk] = $n <= $shiftCount ? busTime($_POST[$pk] ?? '') : null;
        $times[$dr] = $n <= $shiftCount ? busTime($_POST[$dr] ?? '') : null;
    }

    // Verify bus belongs to school
    $bc = $pdo->prepare("SELECT id FROM school_buses WHERE id=? AND school_id=?");
    $bc->execute([$busId, $schoolId]);
    if (!$bc->fetch()) jBus(false, 'Bus not found.');

    // Verify route belongs to school
    $rc = $pdo->prepare("SELECT id FROM van_routes WHERE id=? AND school_id=?");
    $rc->execute([$routeId, $schoolId]);
    if (!$rc->fetch()) jBus(false, 'Route not found.');

    // Verify driver belongs to school (if given)
    if ($driverId) {
        $dc = $pdo->prepare("SELECT id FROM teachers WHERE id=? AND school_id=?");
        $dc->execute([$driverId, $schoolId]);
        if (!$dc->fetch()) jBus(false, 'Driver not found.');
    }

    // One assignment per route (prevents duplicate rows / duplicate students in lists)
    $dup = $pdo->prepare("SELECT id FROM bus_route_assignments WHERE route_id=? AND school_id=? AND id<>? LIMIT 1");
    $dup->execute([$routeId, $schoolId, $id]);
    if ($dup->fetch()) jBus(false, 'This route already has a bus assigned. Edit the existing assignment instead.');

    $cols = array_merge(
        ['route_id'=>$routeId, 'bus_id'=>$busId, 'driver_id'=>$driverId, 'shift_count'=>$shiftCount],
        $times,
        ['days'=>$days, 'status'=>$status]
    );

    if ($id > 0) {
        $chk = $pdo->prepare("SELECT id FROM bus_route_assignments WHERE id=? AND school_id=?");
        $chk->execute([$id, $schoolId]);
        if (!$chk->fetch()) jBus(false, 'Assignment not found.');

        $set = implode('=?,', array_keys($cols)) . '=?';
        $pdo->prepare("UPDATE bus_route_assignments SET $set WHERE id=? AND school_id=?")
            ->execute(array_merge(array_values($cols), [$id, $schoolId]));

        // Shift count reduced: move students from removed shifts back to shift 1
        $pdo->prepare("UPDATE bus_student_shifts SET shift_no=1 WHERE school_id=? AND route_id=? AND shift_no>?")
            ->execute([$schoolId, $routeId, $shiftCount]);

        _slog("Bus route assignment updated (route #$routeId, bus #$busId, $shiftCount shift(s))", 'update');
        jBus(true, 'Assignment updated.');
    } else {
        $names = implode(',', array_keys($cols));
        $ph    = implode(',', array_fill(0, count($cols) + 1, '?'));
        $pdo->prepare("INSERT INTO bus_route_assignments (school_id,$names) VALUES ($ph)")
            ->execute(array_merge([$schoolId], array_values($cols)));
        _slog("Bus assigned to route #$routeId (bus #$busId, $shiftCount shift(s))", 'create');
        jBus(true, 'Route assigned.', ['id'=>(int)$pdo->lastInsertId()]);
    }
}

// ── delete_assignment ─────────────────────────────────────────────────────────
if ($action === 'delete_assignment') {
    if (!$isAdmin) jBus(false, 'Admin only.');
    csrfBus();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jBus(false, 'Invalid ID.');
    $chk = $pdo->prepare("SELECT id FROM bus_route_assignments WHERE id=? AND school_id=?");
    $chk->execute([$id, $schoolId]);
    if (!$chk->fetch()) jBus(false, 'Assignment not found.');
    $rt = $pdo->prepare("SELECT route_id FROM bus_route_assignments WHERE id=? AND school_id=?");
    $rt->execute([$id, $schoolId]);
    $routeOfAssign = (int)$rt->fetchColumn();
    $pdo->prepare("DELETE FROM bus_route_assignments WHERE id=? AND school_id=?")->execute([$id, $schoolId]);
    if ($routeOfAssign) {
        $pdo->prepare("DELETE FROM bus_student_shifts WHERE school_id=? AND route_id=?")->execute([$schoolId, $routeOfAssign]);
    }
    _slog("Bus route assignment removed: ID #$id", 'delete');
    jBus(true, 'Assignment removed.');
}

// ─────────────────────────────────────────────────────────────────────────────
// LIVE TRACKING
// ─────────────────────────────────────────────────────────────────────────────

// ── get_live_locations ────────────────────────────────────────────────────────
// One row per bus. A bus on several active routes gets its routes/drivers merged
// into a single comma-separated value instead of producing duplicate rows.
if ($action === 'get_live_locations') {
    $st = $pdo->prepare("
        SELECT b.id, b.bus_name, b.bus_number, b.status AS bus_status,
            GROUP_CONCAT(DISTINCT vr.route_name ORDER BY vr.route_name SEPARATOR ', ') AS route_name,
            GROUP_CONCAT(DISTINCT a.route_id ORDER BY a.route_id) AS route_ids,
            COUNT(DISTINCT a.route_id) AS route_count,
            GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ', ') AS driver_name
        FROM school_buses b
        LEFT JOIN bus_route_assignments a ON a.bus_id=b.id AND a.school_id=b.school_id AND a.status='active'
        LEFT JOIN van_routes vr ON vr.id=a.route_id
        LEFT JOIN teachers t ON t.id=a.driver_id
        LEFT JOIN users u ON u.id=t.user_id
        WHERE b.school_id=? AND b.status='active'
        GROUP BY b.id, b.bus_name, b.bus_number, b.status
        ORDER BY b.bus_name
    ");
    $st->execute([$schoolId]);
    $rows = $st->fetchAll();
    $loc  = busLatestLocations($pdo, $schoolId);
    foreach ($rows as &$r) {
        $l   = $loc[(int)$r['id']] ?? null;
        $age = $l ? max(0, (int)$l['age_seconds']) : null;
        $r['lat']         = $l['lat']         ?? null;
        $r['lng']         = $l['lng']         ?? null;
        $r['speed']       = $l['speed']       ?? null;
        $r['heading']     = $l['heading']     ?? null;
        $r['accuracy']    = $l['accuracy']    ?? null;   // metres (column is optional)
        $r['recorded_at'] = $l['recorded_at'] ?? null;
        $r['age_seconds'] = $age;
        $r['gps_status']  = busGpsStatus($age);
        $r['still_seconds'] = isset($l['still_seconds']) ? (int)$l['still_seconds'] : null;   // standing still for…
        $r['trip'] = busTripProgress($pdo, (int)$r['id'], $schoolId);
    }
    unset($r);

    // Silent-bus watchdog: piggy-backs on the admin map poll (max once a minute per session);
    // the cron job (tools/bus_watchdog_cron.php) covers the times nobody has the map open.
    require_once __DIR__ . '/../includes/bus_watchdog.php';
    if (time() - (int)($_SESSION['bus_wd_last'] ?? 0) >= 60) {
        $_SESSION['bus_wd_last'] = time();
        runBusWatchdog($pdo, $schoolId);
    }
    $alerts = [];
    try {   // newest alerts of the last 12 h for the live feed (this school only)
        $al = $pdo->prepare("SELECT a.id, a.bus_id, a.type, a.lat, a.lng, a.value, a.message, a.created_at, a.seen_at, a.broadcast_at, a.trip_id
                             FROM bus_alerts a WHERE a.school_id=? AND a.created_at > (NOW() - INTERVAL 12 HOUR) ORDER BY a.id DESC LIMIT 30");
        $al->execute([$schoolId]);
        $alerts = $al->fetchAll();
    } catch (\Throwable $e) {}
    jBus(true, '', ['buses' => $rows, 'watchdog' => busWatchdogOpenAlerts($pdo, $schoolId), 'alerts' => $alerts]);
}

// Running trip of one bus with pickup progress: {id, shift, started_at, total, done, absent, pending, missing, next}
function busTripProgress(PDO $pdo, int $busId, int $schoolId): ?array {
    static $ready = null;
    if ($ready === null) { require_once __DIR__ . '/../includes/bus_trips.php'; $ready = true; }
    try {
        $t = busTripGetOpen($pdo, $busId);
        if (!$t || (int)$t['school_id'] !== $schoolId) return null;
        $list = busStopsForShift($pdo, $busId, $schoolId, (int)$t['shift_no'], (int)$t['id'], true);
        $c = ['done' => 0, 'absent' => 0, 'pending' => 0]; $next = null;
        foreach ($list['stops'] as $s) {
            $c[$s['status']] = ($c[$s['status']] ?? 0) + 1;
            if ($s['status'] === 'pending' && $s['seq'] !== null && (!$next || $s['seq'] < $next['seq'])) $next = $s;
        }
        return ['id' => (int)$t['id'], 'shift' => (int)$t['shift_no'], 'started_at' => $t['started_at'],
                'total' => count($list['stops']) + count($list['missing']), 'done' => $c['done'], 'absent' => $c['absent'],
                'pending' => $c['pending'] + count($list['missing']), 'missing' => count($list['missing']),
                'next' => $next ? $next['name'] : null];
    } catch (\Throwable $e) { return null; }   // trip tables not installed yet
}

// ── get_bus_live_detail ───────────────────────────────────────────────────────
// Everything the admin map needs for ONE bus: students of the running shift with status, where the bus stood
// (halts) and the path driven so far, plus the learned everyday route.
if ($action === 'get_bus_live_detail') {
    require_once __DIR__ . '/../includes/bus_trips.php';
    $busId = (int)($_REQUEST['bus_id'] ?? 0);
    $chk = $pdo->prepare("SELECT id, bus_name, bus_number FROM school_buses WHERE id=? AND school_id=?");
    $chk->execute([$busId, $schoolId]);
    $bus = $chk->fetch();
    if (!$bus) jBus(false, 'Bus not found.');
    try {
        $trip  = busTripGetOpen($pdo, $busId);
        $shift = $trip ? (int)$trip['shift_no'] : max(1, (int)($_REQUEST['shift'] ?? 1));
        $list  = busStopsForShift($pdo, $busId, $schoolId, $shift, $trip ? (int)$trip['id'] : null, true);
        $halts = []; $path = [];
        if ($trip) {
            $g = $pdo->prepare("SELECT lat, lng, speed, UNIX_TIMESTAMP(recorded_at) AS t FROM bus_gps_locations
                                WHERE school_id=? AND bus_id=? AND recorded_at >= ? ORDER BY recorded_at, id LIMIT 20000");
            $g->execute([$schoolId, $busId, $trip['started_at']]);
            $rows = array_map(function ($r) { $r['t'] = (int)$r['t']; return $r; }, $g->fetchAll());
            $sum = busTripSummarize($rows);
            $halts = $sum['stops']; $path = $sum['path'];
        }
        $kind  = busTripKind($pdo, $busId, $schoolId, $shift, $trip ? (string)$trip['started_at'] : (string)$pdo->query("SELECT NOW()")->fetchColumn());
        $learn = busLearnedProfile($pdo, $busId, $shift, $kind);
    } catch (\Throwable $e) {
        error_log('bus detail: ' . $e->getMessage()); jBus(false, BUS_DB_SETUP_MSG);
    }
    jBus(true, '', ['bus' => $bus, 'shift' => $shift, 'kind' => $kind,
        'trip' => $trip ? ['id' => (int)$trip['id'], 'started_at' => $trip['started_at']] : null,
        'stops' => $list['stops'], 'missing' => $list['missing'], 'halts' => $halts, 'path' => $path,
        'learned' => $learn ? array_diff_key($learn, ['order' => 1]) + ['order_len' => count($learn['order'])] : null]);
}

// ── learn_reset (forget a learned route, e.g. after the route changed) ────────
if ($action === 'learn_reset') {
    if (!$isAdmin) jBus(false, 'Admin only.');
    csrfBus();
    $busId = (int)($_POST['bus_id'] ?? 0); $shift = (int)($_POST['shift'] ?? 0);
    $kind  = in_array($_POST['kind'] ?? '', ['pickup', 'drop', 'any'], true) ? $_POST['kind'] : 'any';
    $pdo->prepare("DELETE FROM bus_route_learn WHERE bus_id=? AND shift_no=? AND kind=? AND school_id=?")->execute([$busId, $shift, $kind, $schoolId]);
    _slog("Learned route reset for bus #$busId shift $shift ($kind)", 'update');
    jBus(true, 'Seekha hua raasta hata diya. Agli trips se phir seekhega.');
}

// ─────────────────────────────────────────────────────────────────────────────
// SAFETY ALERTS (per-school settings + alert feed)
// ─────────────────────────────────────────────────────────────────────────────

if ($action === 'get_alert_settings') {
    require_once __DIR__ . '/../includes/bus_alerts.php';
    jBus(true, '', ['settings' => busAlertSettings($pdo, $schoolId)]);
}

if ($action === 'save_alert_settings') {
    if (!$isAdmin) jBus(false, 'Admin only.');
    csrfBus();
    $n = function ($k, $min, $max, $def) { $v = (int)($_POST[$k] ?? $def); return max($min, min($max, $v)); };
    $lat = trim((string)($_POST['school_lat'] ?? '')); $lng = trim((string)($_POST['school_lng'] ?? ''));
    if ($lat !== '' || $lng !== '') {
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float)$lat) > 90 || abs((float)$lng) > 180 || ((float)$lat == 0 && (float)$lng == 0))
            jBus(false, 'School location galat hai.');
        $lat = (float)$lat; $lng = (float)$lng;
    } else { $lat = null; $lng = null; }
    $askMin = $n('halt_ask_min', 1, 30, 3);
    try {
        $pdo->prepare("INSERT INTO bus_alert_settings (school_id, overspeed_kmh, overspeed_sec, school_lat, school_lng, school_radius_m, deviation_m, deviation_sec, notify_parents, halt_ask_min, halt_admin_min, updated_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE overspeed_kmh=VALUES(overspeed_kmh), overspeed_sec=VALUES(overspeed_sec), school_lat=VALUES(school_lat),
                         school_lng=VALUES(school_lng), school_radius_m=VALUES(school_radius_m), deviation_m=VALUES(deviation_m),
                         deviation_sec=VALUES(deviation_sec), notify_parents=VALUES(notify_parents),
                         halt_ask_min=VALUES(halt_ask_min), halt_admin_min=VALUES(halt_admin_min), updated_at=NOW()")
            ->execute([$schoolId, $n('overspeed_kmh', 20, 120, 50), $n('overspeed_sec', 5, 300, 20), $lat, $lng,
                       $n('school_radius_m', 50, 1000, 150), $n('deviation_m', 150, 3000, 400), $n('deviation_sec', 30, 900, 90),
                       !empty($_POST['notify_parents']) ? 1 : 0, $askMin, max($askMin + 1, $n('halt_admin_min', 2, 60, 8))]);
    } catch (\Throwable $e) { error_log('bus alert settings: ' . $e->getMessage()); jBus(false, BUS_DB_SETUP_MSG); }
    _slog('Bus alert settings updated', 'update');
    jBus(true, 'Alert settings save ho gayi.');
}

// ── get_alerts: recent alerts of this school (newest first) ─────────────────
if ($action === 'get_alerts') {
    $hours = max(1, min(720, (int)($_REQUEST['hours'] ?? 24)));
    try {
        $st = $pdo->prepare("SELECT a.id, a.bus_id, b.bus_name, a.type, a.lat, a.lng, a.value, a.message, a.created_at, a.seen_at
                             FROM bus_alerts a JOIN school_buses b ON b.id=a.bus_id AND b.school_id=a.school_id
                             WHERE a.school_id=? AND a.created_at > (NOW() - INTERVAL $hours HOUR)
                             ORDER BY a.id DESC LIMIT 200");
        $st->execute([$schoolId]);
        jBus(true, '', ['alerts' => $st->fetchAll()]);
    } catch (\Throwable $e) { jBus(true, '', ['alerts' => []]); }
}

// ── broadcast_preview: suggested parent message + how many parents it reaches ─
if ($action === 'broadcast_preview') {
    require_once __DIR__ . '/../includes/bus_halt.php';
    $alertId = (int)($_REQUEST['alert_id'] ?? 0); $busId = (int)($_REQUEST['bus_id'] ?? 0);
    $trip = null; $text = '';
    if ($alertId) {
        $a = $pdo->prepare("SELECT * FROM bus_alerts WHERE id=? AND school_id=?");
        $a->execute([$alertId, $schoolId]);
        $alert = $a->fetch();
        if (!$alert) jBus(false, 'Alert nahi mila.');
        $text = busBroadcastDefault($pdo, $alert);
        $busId = (int)$alert['bus_id'];
        if ($alert['trip_id']) { $t = $pdo->prepare("SELECT * FROM bus_trips WHERE id=? AND school_id=?"); $t->execute([(int)$alert['trip_id'], $schoolId]); $trip = $t->fetch() ?: null; }
    }
    $b = $pdo->prepare("SELECT id, bus_name, bus_number FROM school_buses WHERE id=? AND school_id=?");
    $b->execute([$busId, $schoolId]);
    $bus = $b->fetch();
    if (!$bus) jBus(false, 'Bus nahi mili.');
    if (!$trip) $trip = busTripGetOpen($pdo, $busId);
    if (!$text) $text = 'Suchna: ' . $bus['bus_name'] . ' (' . $bus['bus_number'] . ') — ';
    jBus(true, '', ['text' => $text, 'bus' => $bus, 'shift' => $trip ? (int)$trip['shift_no'] : null,
                    'count' => $trip ? count(busTripRecipients($pdo, $trip)) : 0,
                    'sent_before' => !empty($alert['broadcast_at']) ? $alert['broadcast_at'] : null]);
}

// ── broadcast: send to every parent of that trip's shift (push + Messages) ───
if ($action === 'broadcast') {
    if (!$isAdmin) jBus(false, 'Admin only.');
    csrfBus();
    require_once __DIR__ . '/../includes/bus_halt.php';
    require_once __DIR__ . '/../includes/bus_security.php';
    if (!busRateHit($pdo, 'bcast:' . $schoolId, 30, 3600)) jBus(false, 'Ek ghante mein bahut zyada messages — thodi der baad.');
    $r = busBroadcast($pdo, $schoolId, (int)($_POST['alert_id'] ?? 0) ?: null, (int)($_POST['bus_id'] ?? 0) ?: null, (string)($_POST['text'] ?? ''));
    if ($r['ok']) _slog('Bus message sent to ' . $r['sent'] . ' parents', 'other');
    jBus($r['ok'], $r['msg'], ['sent' => $r['sent']]);
}

// ── alerts_seen: mark all alerts up to an id as seen ─────────────────────────
if ($action === 'alerts_seen') {
    csrfBus();
    try {
        $pdo->prepare("UPDATE bus_alerts SET seen_at=NOW() WHERE school_id=? AND id<=? AND seen_at IS NULL")->execute([$schoolId, (int)($_POST['upto'] ?? 0)]);
    } catch (\Throwable $e) {}
    jBus(true, '');
}

// ── get_bus_trail ─────────────────────────────────────────────────────────────
// Recent path of one bus (oldest → newest), for drawing a trail on the live map.
if ($action === 'get_bus_trail') {
    $busId   = (int)($_REQUEST['bus_id'] ?? 0);
    $minutes = min(1440, max(5, (int)($_REQUEST['minutes'] ?? 120)));
    if ($busId <= 0) jBus(false, 'Invalid bus.');
    $chk = $pdo->prepare("SELECT id FROM school_buses WHERE id=? AND school_id=?");
    $chk->execute([$busId, $schoolId]);
    if (!$chk->fetch()) jBus(false, 'Bus not found.');

    $st = $pdo->prepare("
        SELECT lat, lng, speed, recorded_at
        FROM bus_gps_locations
        WHERE school_id=? AND bus_id=? AND recorded_at >= (NOW() - INTERVAL $minutes MINUTE)
        ORDER BY recorded_at DESC
        LIMIT 3000
    ");
    $st->execute([$schoolId, $busId]);
    $pts = array_reverse($st->fetchAll());

    // Thin out to ~500 points so long trails stay light for the browser
    $max = 500;
    if (count($pts) > $max) {
        $step = (int)ceil(count($pts) / $max);
        $thin = [];
        foreach ($pts as $i => $pt) { if ($i % $step === 0) $thin[] = $pt; }
        $thin[] = end($pts); // always keep the newest point
        $pts = $thin;
    }
    jBus(true, '', ['points' => $pts]);
}

// ─────────────────────────────────────────────────────────────────────────────
// STUDENTS
// ─────────────────────────────────────────────────────────────────────────────

// ── get_route_students ────────────────────────────────────────────────────────
if ($action === 'get_route_students') {
    $routeId = (int)($_REQUEST['route_id'] ?? 0);
    $where = ['sva.school_id=?'];
    $params = [$schoolId];
    if ($routeId) { $where[] = 'sva.van_route_id=?'; $params[] = $routeId; }

    $rows = $pdo->prepare("
        SELECT s.id AS student_id, s.name, s.admission_no, s.photo,
            c.class_name, sec.section_name,
            sva.van_route_id AS route_id,
            vr.route_name,
            bss.shift_no,
            ba.bus_id, b.bus_name, b.bus_number,
            ba.pickup_time, ba.drop_time, ba.pickup_time2, ba.drop_time2,
            ba.pickup_time3, ba.drop_time3, ba.pickup_time4, ba.drop_time4, ba.pickup_time5, ba.drop_time5,
            ba.shift_count,
            u.name AS driver_name,
            (SELECT COUNT(*) FROM student_home_locations hl WHERE hl.student_id=s.id) AS has_home
        FROM student_van_assignments sva
        JOIN students s ON s.id=sva.student_id AND s.status='active'
        LEFT JOIN classes c ON c.id=s.class_id
        LEFT JOIN sections sec ON sec.id=s.section_id
        LEFT JOIN van_routes vr ON vr.id=sva.van_route_id
        LEFT JOIN bus_route_assignments ba ON ba.route_id=sva.van_route_id AND ba.school_id=sva.school_id AND ba.status='active'
        LEFT JOIN school_buses b ON b.id=ba.bus_id
        LEFT JOIN teachers t ON t.id=ba.driver_id
        LEFT JOIN users u ON u.id=t.user_id
        LEFT JOIN bus_student_shifts bss ON bss.student_id=s.id AND bss.route_id=sva.van_route_id AND bss.school_id=sva.school_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY vr.route_name, s.name
    ");
    $rows->execute($params);
    jBus(true, '', ['students'=>$rows->fetchAll()]);
}

// ── set_student_shift ─────────────────────────────────────────────────────────
if ($action === 'set_student_shift') {
    if (!$isAdmin) jBus(false, 'Admin only.');
    csrfBus();
    $studentId = (int)($_POST['student_id'] ?? 0);
    $routeId   = (int)($_POST['route_id']   ?? 0);
    $shiftNo   = (int)($_POST['shift_no']   ?? 1);
    if (!$studentId || !$routeId) jBus(false, 'Invalid parameters.');
    if ($shiftNo < 1 || $shiftNo > BUS_MAX_SHIFTS) jBus(false, 'Invalid shift.');

    // Student must be on this route in this school
    $sc = $pdo->prepare("SELECT 1 FROM student_van_assignments WHERE student_id=? AND van_route_id=? AND school_id=? LIMIT 1");
    $sc->execute([$studentId, $routeId, $schoolId]);
    if (!$sc->fetchColumn()) jBus(false, 'Student is not assigned to this route.');

    // Shift must exist on the route's bus assignment
    $ac = $pdo->prepare("SELECT shift_count FROM bus_route_assignments WHERE route_id=? AND school_id=? AND status='active' LIMIT 1");
    $ac->execute([$routeId, $schoolId]);
    $maxShift = (int)$ac->fetchColumn();
    if ($maxShift && $shiftNo > $maxShift) jBus(false, "This route has only $maxShift shift(s).");

    $pdo->prepare("
        INSERT INTO bus_student_shifts (school_id, route_id, student_id, shift_no)
        VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE shift_no=VALUES(shift_no)
    ")->execute([$schoolId, $routeId, $studentId, $shiftNo]);
    jBus(true, 'Shift updated.');
}

// ── get_drivers ───────────────────────────────────────────────────────────────
if ($action === 'get_drivers') {
    $rows = $pdo->prepare("
        SELECT t.id, u.name, u.phone, t.employee_code, t.photo
        FROM teachers t JOIN users u ON u.id=t.user_id
        WHERE t.school_id=? AND t.status='active' AND LOWER(t.post)='driver'
        ORDER BY u.name
    ");
    $rows->execute([$schoolId]);
    jBus(true, '', ['drivers'=>$rows->fetchAll()]);
}

// ── get_unassigned_routes ─────────────────────────────────────────────────────
if ($action === 'get_unassigned_routes') {
    $rows = $pdo->prepare("
        SELECT vr.id, vr.route_name, vr.from_location, vr.to_location,
            (SELECT COUNT(*) FROM student_van_assignments sv WHERE sv.van_route_id=vr.id AND sv.school_id=vr.school_id) AS student_count
        FROM van_routes vr
        LEFT JOIN bus_route_assignments a ON a.route_id=vr.id AND a.school_id=vr.school_id
        WHERE vr.school_id=? AND vr.is_active=1 AND a.id IS NULL
        ORDER BY vr.route_name
    ");
    $rows->execute([$schoolId]);
    jBus(true, '', ['routes'=>$rows->fetchAll()]);
}

// ─────────────────────────────────────────────────────────────────────────────
// SETUP WIZARD / BUS HEALTH
// ─────────────────────────────────────────────────────────────────────────────

// ── wizard_check ──────────────────────────────────────────────────────────────
// Light poll used by the wizard's live test: the newest fix of one bus (no key is returned).
if ($action === 'wizard_check') {
    $busId = (int)($_REQUEST['bus_id'] ?? 0);
    $chk = $pdo->prepare("SELECT id FROM school_buses WHERE id=? AND school_id=?");
    $chk->execute([$busId, $schoolId]);
    if (!$chk->fetch()) jBus(false, 'Bus not found.');
    $l = busLatestLocations($pdo, $schoolId)[$busId] ?? null;
    if (!$l) jBus(true, '', ['fix' => null]);
    jBus(true, '', ['fix' => [
        'lat' => (float)$l['lat'], 'lng' => (float)$l['lng'],
        'speed' => (float)$l['speed'], 'accuracy' => isset($l['accuracy']) ? (float)$l['accuracy'] : null,
        'recorded_at' => $l['recorded_at'], 'age' => max(0, (int)$l['age_seconds']),
    ]]);
}

// ── wizard_status ─────────────────────────────────────────────────────────────
// Health checklist for one bus: what works, what is missing, and how to fix it.
if ($action === 'wizard_status') {
    $busId = (int)($_REQUEST['bus_id'] ?? 0);
    $bq = $pdo->prepare("SELECT id, bus_name, bus_number, status FROM school_buses WHERE id=? AND school_id=?");
    $bq->execute([$busId, $schoolId]);
    $bus = $bq->fetch();
    if (!$bus) jBus(false, 'Bus not found.');

    $checks = [];
    $add = function (string $key, string $level, string $title, string $hint = '', string $fix = '') use (&$checks) {
        $checks[] = ['key' => $key, 'level' => $level, 'title' => $title, 'hint' => $hint, 'fix' => $fix];   // level: ok | warn | bad
    };

    // GPS
    $l   = busLatestLocations($pdo, $schoolId)[$busId] ?? null;
    $age = $l ? max(0, (int)$l['age_seconds']) : null;
    $gs  = busGpsStatus($age);
    $acc = ($l && isset($l['accuracy'])) ? (float)$l['accuracy'] : null;
    if ($gs === 'live') {
        if ($acc !== null && $acc > 60) $add('gps', 'warn', 'GPS chal raha hai, par accuracy kamzor hai (±' . round($acc) . ' m)', 'Phone ko dashboard par shishe ke paas, khule mein lagayein.', 'method');
        else $add('gps', 'ok', 'GPS live hai' . ($acc !== null ? ' (±' . round($acc) . ' m)' : ''));
    } elseif ($gs === 'recent') $add('gps', 'warn', 'Aakhri location ' . round($age / 60) . ' min pehle aayi', 'Check karein ki tracking abhi bhi chal rahi hai.', 'test');
    elseif ($gs === 'offline')  $add('gps', 'bad', 'Location band hai (' . round($age / 60) . ' min se)', 'Phone/device band ya internet nahi.', 'test');
    else                        $add('gps', 'bad', 'Abhi tak koi location nahi aayi', 'Tracking ka tareeka chunkar test karein.', 'method');

    // Assignments
    $aq = $pdo->prepare("
        SELECT a.*, vr.route_name, u.name AS driver_name,
          (SELECT COUNT(*) FROM student_van_assignments sv WHERE sv.van_route_id=a.route_id AND sv.school_id=a.school_id) AS students,
          (SELECT COUNT(*) FROM student_van_assignments sv JOIN student_home_locations hl ON hl.student_id=sv.student_id AND hl.school_id=sv.school_id
             WHERE sv.van_route_id=a.route_id AND sv.school_id=a.school_id) AS with_home,
          (SELECT COUNT(*) FROM student_van_assignments sv JOIN student_home_locations hl ON hl.student_id=sv.student_id AND hl.school_id=sv.school_id
             WHERE sv.van_route_id=a.route_id AND sv.school_id=a.school_id AND hl.push_enabled=1) AS with_push
        FROM bus_route_assignments a
        JOIN van_routes vr ON vr.id=a.route_id
        LEFT JOIN teachers t ON t.id=a.driver_id LEFT JOIN users u ON u.id=t.user_id
        WHERE a.bus_id=? AND a.school_id=? AND a.status='active'");
    $aq->execute([$busId, $schoolId]);
    $asg = $aq->fetchAll();

    if (!$asg) {
        $add('route', 'bad', 'Koi route assign nahi hai', 'Bina route ke students ko is bus ka alert/map nahi milega.', 'route');
    } else {
        $add('route', 'ok', count($asg) . ' route assigned: ' . implode(', ', array_column($asg, 'route_name')));
        $noDriver = array_filter($asg, function ($a) { return !$a['driver_id']; });
        $noDriver ? $add('driver', 'warn', 'Driver assign nahi: ' . implode(', ', array_column($noDriver, 'route_name')), 'Optional, par admin ko pata rehta hai kaun chala raha hai.', 'route')
                  : $add('driver', 'ok', 'Driver: ' . implode(', ', array_unique(array_column($asg, 'driver_name'))));
        $noTimes = array_filter($asg, function ($a) { return !$a['pickup_time'] && !$a['drop_time']; });
        $noTimes ? $add('times', 'warn', 'Pickup/Drop time set nahi', 'Time ke bina "bus chup hai" alert aur time-window wala proximity alert kaam nahi karte.', 'route')
                 : $add('times', 'ok', 'Shift timings set hain');
        $stu = array_sum(array_column($asg, 'students'));
        $home = array_sum(array_column($asg, 'with_home'));
        $push = array_sum(array_column($asg, 'with_push'));
        if ($stu === 0)             $add('students', 'warn', 'Is route par abhi koi student nahi', 'Fee → Van Setup mein students ko route par daalein.', 'students');
        else {
            $add('students', 'ok', $stu . ' students route par');
            $pctHome = round($home / $stu * 100); $pctPush = round($push / $stu * 100);
            $add('home', $pctHome >= 50 ? 'ok' : 'warn', "$home / $stu students ne ghar ki location set ki ($pctHome%)", $pctHome >= 50 ? '' : 'Parents ko student portal → Bus tab mein ghar set karne ko kahein, tabhi "bus nazdeek hai" alert milega.', 'students');
            $add('push', $pctPush >= 30 ? 'ok' : 'warn', "$push / $stu students ke phone par alerts ON ($pctPush%)", $pctPush >= 30 ? '' : 'Parents ko Bus tab mein "Background alerts" ON karne ko kahein.', 'students');
        }
    }

    // Open watchdog alert / trips
    try {
        $w = $pdo->prepare("SELECT 1 FROM bus_watchdog_alerts WHERE bus_id=? AND resolved_at IS NULL LIMIT 1");
        $w->execute([$busId]);
        if ($w->fetchColumn()) $add('watchdog', 'bad', 'Watchdog alert khula hai: bus chalni chahiye par chup hai', '', 'test');
    } catch (\Throwable $e) {}
    try {
        $t = $pdo->prepare("SELECT COUNT(*) FROM bus_trips WHERE bus_id=?");
        $t->execute([$busId]);
        (int)$t->fetchColumn() > 0 ? $add('trip', 'ok', 'Trip shuru/khatam pehle ho chuki hai')
                                   : $add('trip', 'warn', 'Abhi tak koi trip nahi chali', 'Driver ko link kholkar "Trip Shuru Karein" dabane ko kahein.', 'method');
    } catch (\Throwable $e) {}

    $score = 0; foreach ($checks as $c) $score += $c['level'] === 'ok' ? 2 : ($c['level'] === 'warn' ? 1 : 0);
    jBus(true, '', ['bus' => $bus, 'checks' => $checks, 'score' => (int)round($score / (2 * max(1, count($checks))) * 100)]);
}

// ─────────────────────────────────────────────────────────────────────────────
// TRIP HISTORY
// ─────────────────────────────────────────────────────────────────────────────

function tripFilters(): array {
    global $schoolId;
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_REQUEST['from'] ?? '') ? $_REQUEST['from'] : date('Y-m-d', strtotime('-6 days'));
    $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_REQUEST['to']   ?? '') ? $_REQUEST['to']   : date('Y-m-d');
    $where = ['t.school_id=?', 't.started_at >= ?', 't.started_at < DATE_ADD(?, INTERVAL 1 DAY)'];
    $par   = [$schoolId, $from . ' 00:00:00', $to];
    $bus   = (int)($_REQUEST['bus_id'] ?? 0);
    if ($bus) { $where[] = 't.bus_id=?'; $par[] = $bus; }
    return [implode(' AND ', $where), $par];
}

const TRIP_SELECT = "SELECT t.id, t.bus_id, b.bus_name, b.bus_number, t.shift_no, t.started_at, t.ended_at, t.end_reason,
        t.distance_m, t.max_speed_kmh, t.avg_speed_kmh, t.points, t.stops_json,
        (SELECT COUNT(*) FROM bus_trip_stops ts WHERE ts.trip_id=t.id AND ts.status='done')   AS picked,
        (SELECT COUNT(*) FROM bus_trip_stops ts WHERE ts.trip_id=t.id AND ts.status='absent') AS absent,
        TIMESTAMPDIFF(MINUTE, t.started_at, COALESCE(t.ended_at, NOW())) AS minutes
    FROM bus_trips t JOIN school_buses b ON b.id=t.bus_id";

// ── get_trips ─────────────────────────────────────────────────────────────────
if ($action === 'get_trips') {
    [$w, $p] = tripFilters();
    try {
        $st = $pdo->prepare(TRIP_SELECT . " WHERE $w ORDER BY t.started_at DESC LIMIT 500");
        $st->execute($p);
        $rows = $st->fetchAll();
    } catch (\Throwable $e) { jBus(false, BUS_DB_SETUP_MSG); }
    foreach ($rows as &$r) {
        $r['stop_count'] = $r['stops_json'] ? count(json_decode($r['stops_json'], true) ?: []) : 0;
        unset($r['stops_json']);
    }
    unset($r);
    jBus(true, '', ['trips' => $rows]);
}

// ── get_trip (one trip with path + stops for the map) ─────────────────────────
if ($action === 'get_trip') {
    $id = (int)($_REQUEST['id'] ?? 0);
    $st = $pdo->prepare("SELECT t.path_json, t.stops_json FROM bus_trips t WHERE t.id=? AND t.school_id=?");
    $st->execute([$id, $schoolId]);
    $r = $st->fetch();
    if (!$r) jBus(false, 'Trip not found.');
    jBus(true, '', ['path' => json_decode($r['path_json'] ?: '[]', true), 'stops' => json_decode($r['stops_json'] ?: '[]', true)]);
}

// ── export_trips (CSV download) ───────────────────────────────────────────────
if ($action === 'export_trips') {
    [$w, $p] = tripFilters();
    $st = $pdo->prepare(TRIP_SELECT . " WHERE $w ORDER BY t.started_at");
    $st->execute($p);
    header_remove('Content-Type');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bus_trips_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // Excel-friendly UTF-8
    fputcsv($out, ['Bus', 'Number', 'Shift', 'Start', 'End', 'Minutes', 'Distance (km)', 'Max speed (km/h)', 'Avg moving speed (km/h)', 'Stops', 'Students done', 'Students absent', 'Ended by']);
    foreach ($st->fetchAll() as $r) {
        $csvSafe = function ($v) { return is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v; };   // no spreadsheet formulas
        fputcsv($out, [$csvSafe($r['bus_name']), $csvSafe($r['bus_number']), $r['shift_no'], $r['started_at'], $r['ended_at'], $r['minutes'],
            $r['distance_m'] !== null ? round($r['distance_m'] / 1000, 1) : '', $r['max_speed_kmh'], $r['avg_speed_kmh'],
            $r['stops_json'] ? count(json_decode($r['stops_json'], true) ?: []) : 0, $r['picked'], $r['absent'], $r['end_reason'] ?: 'running']);
    }
    exit;
}

jBus(false, 'Unknown action.');