<?php
// api/bus_location.php — latest GPS position of ONE bus.
// The student portal polls this every 10 s to move the bus on the map.
//
// Privacy: a school bus's live position (children on board) is only returned to a logged-in
// student of that school who actually rides that bus. Set BUS_LOCATION_REQUIRE_LOGIN to false
// only if some other page/app must read it without a student session.

ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
ob_end_clean();

const BUS_LOCATION_REQUIRE_LOGIN = true;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$busId    = (int)($_GET['bus_id']    ?? 0);
$schoolId = (int)($_GET['school_id'] ?? 0);

if (!$busId || !$schoolId) { echo json_encode(['ok'=>false]); exit; }

$stuId = 0;
if (BUS_LOCATION_REQUIRE_LOGIN) {
    require_once __DIR__ . '/../student/stu_guard.php';
    if (!stuLoggedIn()) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'msg'=>'Not logged in']); exit;
    }
    $stu   = stuInfo();
    $stuId = (int)($stu['id'] ?? 0);
    if ((int)($stu['school_id'] ?? 0) !== $schoolId || !$stuId) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'msg'=>'Not allowed']); exit;
    }
}

try {
    $pdo = Database::connect();

    // Bus must be active, belong to the school — and (when login is required) be this student's bus
    if (BUS_LOCATION_REQUIRE_LOGIN) {
        $check = $pdo->prepare("
            SELECT b.id
            FROM school_buses b
            JOIN bus_route_assignments bra ON bra.bus_id=b.id AND bra.school_id=b.school_id AND bra.status='active'
            JOIN student_van_assignments sva ON sva.van_route_id=bra.route_id AND sva.school_id=bra.school_id
            WHERE b.id=? AND b.school_id=? AND b.status='active' AND sva.student_id=?
            LIMIT 1
        ");
        $check->execute([$busId, $schoolId, $stuId]);
    } else {
        $check = $pdo->prepare("SELECT id FROM school_buses WHERE id=? AND school_id=? AND status='active'");
        $check->execute([$busId, $schoolId]);
    }
    if (!$check->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Bus not found']); exit; }

    // Age is computed by MySQL itself, so it is correct even when PHP and MySQL use different timezones
    // (the old PHP strtotime() version showed a stale bus as "live" when PHP's clock zone was behind MySQL's).
    // Fast path: bus_live holds exactly one row per bus. Falls back to the history table
    // (before the migration, or before the bus has sent its first fix since it was created).
    $loc = false;
    try {
        $q = $pdo->prepare("SELECT l.*, TIMESTAMPDIFF(SECOND, l.recorded_at, NOW()) AS age_sec FROM bus_live l WHERE l.school_id=? AND l.bus_id=?");
        $q->execute([$schoolId, $busId]);
        $loc = $q->fetch();
    } catch (\Throwable $e) { $loc = false; }
    if (!$loc) {
        $q = $pdo->prepare("
            SELECT g.*, TIMESTAMPDIFF(SECOND, g.recorded_at, NOW()) AS age_sec
            FROM bus_gps_locations g
            WHERE g.school_id=? AND g.bus_id=?
            ORDER BY g.recorded_at DESC, g.id DESC
            LIMIT 1
        ");
        $q->execute([$schoolId, $busId]);
        $loc = $q->fetch();
    }

    if (!$loc) { echo json_encode(['ok'=>false,'msg'=>'No GPS data yet']); exit; }

    $age = max(0, (int)$loc['age_sec']);

    // Running trip of this bus, and — for the logged-in student — their own stop on it
    // (picked up / marked absent / how many stops the driver still has before theirs).
    $trip = null;
    try {
        require_once __DIR__ . '/../includes/bus_trips.php';
        $tq = $pdo->prepare("SELECT id, shift_no, started_at FROM bus_trips WHERE bus_id=? AND school_id=? AND ended_at IS NULL ORDER BY id DESC LIMIT 1");
        $tq->execute([$busId, $schoolId]);
        if ($t = $tq->fetch()) {
            $trip = ['running' => true, 'shift' => (int)$t['shift_no'], 'started_at' => $t['started_at'], 'mine' => null];
            if ($stuId) {
                $sq = $pdo->prepare("SELECT " . BUS_EFFECTIVE_SHIFT_SQL . " FROM bus_route_assignments bra
                    JOIN student_van_assignments sva ON sva.van_route_id=bra.route_id AND sva.school_id=bra.school_id
                    LEFT JOIN bus_student_shifts bss ON bss.student_id=sva.student_id AND bss.route_id=sva.van_route_id AND bss.school_id=sva.school_id
                    WHERE bra.bus_id=? AND bra.school_id=? AND bra.status='active' AND sva.student_id=? LIMIT 1");
                $sq->execute([$busId, $schoolId, $stuId]);
                $myShift = (int)$sq->fetchColumn();
                $trip['mine'] = $myShift === (int)$t['shift_no'];
                if ($trip['mine']) {
                    $mq = $pdo->prepare("SELECT seq, status, marked_at FROM bus_trip_stops WHERE trip_id=? AND student_id=?");
                    $mq->execute([(int)$t['id'], $stuId]);
                    $me = $mq->fetch() ?: [];
                    $trip['status'] = $me['status'] ?? 'pending';
                    $trip['marked_at'] = $me['marked_at'] ?? null;
                    $trip['before'] = null;
                    if ($trip['status'] === 'pending' && isset($me['seq'])) {
                        $bq = $pdo->prepare("SELECT COUNT(*) FROM bus_trip_stops WHERE trip_id=? AND status='pending' AND seq IS NOT NULL AND seq < ?");
                        $bq->execute([(int)$t['id'], (int)$me['seq']]);
                        $trip['before'] = (int)$bq->fetchColumn();
                    }
                }
            }
        }
    } catch (\Throwable $e) { $trip = null; }   // trip tables not installed yet

    echo json_encode([
        'ok'    => true,
        'lat'   => (float)$loc['lat'],
        'lng'   => (float)$loc['lng'],
        'speed' => (float)$loc['speed'],
        'hdg'   => (float)$loc['heading'],
        'acc'   => isset($loc['accuracy']) ? (float)$loc['accuracy'] : null,   // metres (column is optional)
        'time'  => $loc['recorded_at'],
        'age'   => $age,
        'live'  => $age < 300,  // online = location in last 5 min
        'trip'  => $trip,
    ]);
} catch (\Throwable $e) {
    error_log('bus_location: ' . $e->getMessage());
    echo json_encode(['ok'=>false,'msg'=>'Error']);
}