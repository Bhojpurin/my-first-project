<?php
// api/bus_actions.php — School Bus Fleet & Route Management API
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/activity_log.php';

if (empty($_SESSION['logged_in']) || !in_array($_SESSION['role'], [ROLE_SCHOOL_ADMIN, ROLE_TEACHER])) {
    ob_end_clean(); header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Permission denied.']); exit;
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$action   = $_REQUEST['action'] ?? '';
$schoolId = (int)$_SESSION['school_id'];
$userId   = (int)$_SESSION['user_id'];
$isAdmin  = ($_SESSION['role'] === ROLE_SCHOOL_ADMIN);
$pdo      = Database::connect();

function jBus(bool $ok, string $msg = '', array $extra = []): void {
    echo json_encode(array_merge(['success'=>$ok,'message'=>$msg], $extra)); exit;
}

function csrfBus(): void {
    if (!verifyCsrf($_POST['csrf_token'] ?? ''))
        jBus(false, 'Session expired. Please reload.');
}

const BUS_MAX_SHIFTS = 5;

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
        $st->execute([$schoolId]);
        $out = [];
        foreach ($st->fetchAll() as $r) $out[(int)$r['bus_id']] = $r;
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
    }
    unset($r);

    // Silent-bus watchdog: piggy-backs on the admin map poll (max once a minute per session);
    // the cron job (tools/bus_watchdog_cron.php) covers the times nobody has the map open.
    require_once __DIR__ . '/../includes/bus_watchdog.php';
    if (time() - (int)($_SESSION['bus_wd_last'] ?? 0) >= 60) {
        $_SESSION['bus_wd_last'] = time();
        runBusWatchdog($pdo, $schoolId);
    }
    jBus(true, '', ['buses' => $rows, 'watchdog' => busWatchdogOpenAlerts($pdo, $schoolId)]);
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

jBus(false, 'Unknown action.');