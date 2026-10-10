<?php
// api/student_bus.php — Student-authenticated bus actions
// Allows a logged-in student to save/retrieve their home location.

header('Content-Type: application/json; charset=utf-8'); // set first, so even the "not logged in" reply is JSON
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../student/stu_guard.php';

if (!stuLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Not logged in']); exit;
}

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// A request made from another website (cross-site) must never change a student's home/alert settings
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Cross-site request blocked']); exit;
}

$action   = $_REQUEST['action'] ?? '';
$stu      = stuInfo();
$stuId    = (int)($stu['id']        ?? 0);
$schoolId = (int)($stu['school_id'] ?? 0);

if (!$stuId || !$schoolId) {
    echo json_encode(['success'=>false,'message'=>'Session error']); exit;
}

$pdo = Database::connect();


// ── set_home ──────────────────────────────────────────────────────────────────
if ($action === 'set_home') {
    $lat    = (float)($_POST['lat']    ?? 0);
    $lng    = (float)($_POST['lng']    ?? 0);
    $radius = max(100, min(2000, (int)($_POST['radius'] ?? 500)));

    if (abs($lat) > 90 || abs($lng) > 180 || ($lat === 0.0 && $lng === 0.0)) {
        echo json_encode(['success'=>false,'message'=>'Invalid location']); exit;
    }

    $pdo->prepare("
        INSERT INTO student_home_locations (student_id, school_id, lat, lng, alert_radius)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng),
            alert_radius=VALUES(alert_radius), in_radius_since=NULL, updated_at=NOW()
    ")->execute([$stuId, $schoolId, $lat, $lng, $radius]);

    echo json_encode(['success'=>true,'message'=>'Home location saved.']); exit;
}

// Landmark note for the driver ("mandir ke saamne, neela gate"): plain text, one line, max 120 chars.
function cleanHomeNote($v): string {
    $v = strip_tags((string)$v);
    $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v);
    $v = trim(preg_replace('/\s+/u', ' ', $v));
    return mb_substr($v, 0, 120);
}

// ── set_note ──────────────────────────────────────────────────────────────────
if ($action === 'set_note') {
    $note = cleanHomeNote($_POST['note'] ?? '');
    try {
        $u = $pdo->prepare("UPDATE student_home_locations SET note=?, updated_at=NOW() WHERE student_id=? AND school_id=?");
        $u->execute([$note !== '' ? $note : null, $stuId, $schoolId]);
        if (!$u->rowCount()) {
            $c = $pdo->prepare("SELECT 1 FROM student_home_locations WHERE student_id=? AND school_id=?");
            $c->execute([$stuId, $schoolId]);
            if (!$c->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Please set your home location first.']); exit; }
        }
    } catch (\Throwable $e) {
        echo json_encode(['success'=>false,'message'=>'Note feature is not installed yet.']); exit;
    }
    echo json_encode(['success'=>true,'message'=>'Note saved — the driver will see it.','note'=>$note]); exit;
}

// ── Absence: "my child will not take the bus" (today … +14 days; pickup / drop / both) ──
require_once __DIR__ . '/../includes/bus_trips.php';

/** The student's bus + effective shift (same rule as everywhere), or null. */
function stuBusShift(PDO $pdo, int $stuId, int $schoolId): ?array {
    $q = $pdo->prepare("SELECT bra.bus_id, " . BUS_EFFECTIVE_SHIFT_SQL . " AS shift FROM bus_route_assignments bra
        JOIN student_van_assignments sva ON sva.van_route_id=bra.route_id AND sva.school_id=bra.school_id
        LEFT JOIN bus_student_shifts bss ON bss.student_id=sva.student_id AND bss.route_id=sva.van_route_id AND bss.school_id=sva.school_id
        WHERE sva.student_id=? AND sva.school_id=? AND bra.status='active' LIMIT 1");
    $q->execute([$stuId, $schoolId]);
    return $q->fetch() ?: null;
}

/** Apply / undo today's absence on a trip that is already running for this student's shift. */
function stuSyncRunningTrip(PDO $pdo, int $stuId, int $schoolId, bool $absent): void {
    $bs = stuBusShift($pdo, $stuId, $schoolId);
    if (!$bs) return;
    $trip = busTripGetOpen($pdo, (int)$bs['bus_id']);
    if (!$trip || (int)$trip['school_id'] !== $schoolId || (int)$trip['shift_no'] !== (int)$bs['shift']) return;
    if ($absent) { busApplyAbsences($pdo, $trip); return; }
    // cancelled: only undo a ✖ the PARENT made (never the driver's own marks)
    $pdo->prepare("UPDATE bus_trip_stops SET status='pending', marked_by=NULL, marked_at=NULL WHERE trip_id=? AND student_id=? AND status='absent' AND marked_by='parent'")
        ->execute([(int)$trip['id'], $stuId]);
}

if ($action === 'set_absence') {
    $date = (string)($_POST['date'] ?? '');
    $kind = in_array($_POST['kind'] ?? '', ['pickup', 'drop', 'both'], true) ? $_POST['kind'] : 'both';
    $note = cleanHomeNote($_POST['note'] ?? '');
    $today = busToday();
    $max = (new DateTime($today))->modify('+14 days')->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today || $date > $max) {
        echo json_encode(['success'=>false,'message'=>'Aaj se 14 din tak ki tareekh chunein.']); exit;
    }
    if (!stuBusShift($pdo, $stuId, $schoolId)) { echo json_encode(['success'=>false,'message'=>'Aapko koi bus assign nahi hai.']); exit; }
    try {
        $pdo->prepare("INSERT INTO bus_absences (student_id, on_date, school_id, kind, note, created_at) VALUES (?,?,?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE kind=VALUES(kind), note=VALUES(note), created_at=NOW()")
            ->execute([$stuId, $date, $schoolId, $kind, $note !== '' ? $note : null]);
        if ($date === $today) stuSyncRunningTrip($pdo, $stuId, $schoolId, true);
    } catch (\Throwable $e) { echo json_encode(['success'=>false,'message'=>'Feature not installed yet.']); exit; }
    echo json_encode(['success'=>true,'message'=>'Driver ko bata diya jayega — us din bus aapke stop par nahi rukegi.']); exit;
}

if ($action === 'cancel_absence') {
    $date = (string)($_POST['date'] ?? '');
    try {
        $pdo->prepare("DELETE FROM bus_absences WHERE student_id=? AND school_id=? AND on_date=?")->execute([$stuId, $schoolId, $date]);
        if ($date === busToday()) stuSyncRunningTrip($pdo, $stuId, $schoolId, false);
    } catch (\Throwable $e) {}
    echo json_encode(['success'=>true,'message'=>'Cancel ho gaya — bus aapke stop par aayegi.']); exit;
}

if ($action === 'list_absences') {
    try {
        $q = $pdo->prepare("SELECT on_date, kind, note FROM bus_absences WHERE student_id=? AND school_id=? AND on_date >= ? ORDER BY on_date LIMIT 20");
        $q->execute([$stuId, $schoolId, busToday()]);
        echo json_encode(['success'=>true,'absences'=>$q->fetchAll(),'today'=>busToday()]); exit;
    } catch (\Throwable $e) { echo json_encode(['success'=>true,'absences'=>[],'today'=>busToday()]); exit; }
}

// ── delete_my_data: parent erases home, note, alerts and absence notes (privacy) ──
if ($action === 'delete_my_data') {
    require_once __DIR__ . '/../includes/bus_privacy.php';
    busEraseStudentData($pdo, $schoolId, $stuId);
    echo json_encode(['success'=>true,'message'=>'Aapki ghar ki location aur bus alerts ka data hata diya gaya.']); exit;
}

// ── get_home ──────────────────────────────────────────────────────────────────
if ($action === 'get_home') {
    try {
        $s = $pdo->prepare("SELECT lat, lng, alert_radius, push_enabled, updated_at, note FROM student_home_locations WHERE student_id=? AND school_id=?");
        $s->execute([$stuId, $schoolId]);
    } catch (\Throwable $e) {   // note column not migrated yet
        $s = $pdo->prepare("SELECT lat, lng, alert_radius, push_enabled, updated_at, NULL AS note FROM student_home_locations WHERE student_id=? AND school_id=?");
        $s->execute([$stuId, $schoolId]);
    }
    $row = $s->fetch();
    if ($row) {
        echo json_encode([
            'success'      => true,
            'lat'          => (float)$row['lat'],
            'lng'          => (float)$row['lng'],
            'radius'       => (int)$row['alert_radius'],
            'push_enabled' => (bool)$row['push_enabled'],
            'note'         => (string)($row['note'] ?? ''),
            'updated'      => $row['updated_at'],
        ]);
    } else {
        echo json_encode(['success'=>false,'msg'=>'Not set']);
    }
    exit;
}

// ── subscribe_push ───────────────────────────────────────────────────────────
// Saves the browser's PushSubscription so background proximity alerts can be
// sent even while the app is closed. Requires a home location to already exist.
if ($action === 'subscribe_push') {
    $endpoint = trim($_POST['endpoint'] ?? '');
    $p256dh   = trim($_POST['p256dh']   ?? '');
    $auth     = trim($_POST['auth']     ?? '');

    if (!$endpoint || !$p256dh || !$auth) {
        echo json_encode(['success'=>false,'message'=>'Invalid subscription data.']); exit;
    }

    $home = $pdo->prepare("SELECT student_id FROM student_home_locations WHERE student_id=? AND school_id=?");
    $home->execute([$stuId, $schoolId]);
    if (!$home->fetch()) {
        echo json_encode(['success'=>false,'message'=>'Please set your home location first.']); exit;
    }

    $endpointHash = hash('sha256', $endpoint);
    $pdo->prepare("
        INSERT INTO push_subscriptions (student_id, school_id, endpoint, endpoint_hash, p256dh, auth)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE student_id=VALUES(student_id), school_id=VALUES(school_id),
            p256dh=VALUES(p256dh), auth=VALUES(auth)
    ")->execute([$stuId, $schoolId, $endpoint, $endpointHash, $p256dh, $auth]);

    $pdo->prepare("
        UPDATE student_home_locations SET push_enabled=1, in_radius_since=NULL
        WHERE student_id=? AND school_id=?
    ")->execute([$stuId, $schoolId]);

    echo json_encode(['success'=>true,'message'=>'Background alerts enabled.']); exit;
}

// ── unsubscribe_push ─────────────────────────────────────────────────────────
if ($action === 'unsubscribe_push') {
    $endpoint = trim($_POST['endpoint'] ?? '');
    if ($endpoint) {
        $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash=? AND student_id=?")
            ->execute([hash('sha256', $endpoint), $stuId]);
    } else {
        // No endpoint available (e.g. permission was revoked) — drop everything for this student.
        $pdo->prepare("DELETE FROM push_subscriptions WHERE student_id=? AND school_id=?")
            ->execute([$stuId, $schoolId]);
    }
    $pdo->prepare("UPDATE student_home_locations SET push_enabled=0, in_radius_since=NULL WHERE student_id=? AND school_id=?")
        ->execute([$stuId, $schoolId]);

    echo json_encode(['success'=>true,'message'=>'Background alerts disabled.']); exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);