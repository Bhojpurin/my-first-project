<?php
// api/bus_trip.php — trip start / stop / status for ONE bus, authenticated by the bus API key
// (same key as gps_update.php; sent as ?key=, POST field, or X-API-Key header).
//   action=status                 bus name, number, shifts, and the open trip (if any)
//   action=start  [shift=1..5]    open a trip (idempotent) and push "bus has left" to the students
//   action=stop                   close the open trip and return its summary (km, max speed, stops)
//   action=stops  [shift=N]       students of that shift with their marked home (the open trip's shift wins)
//   action=mark_stop student_id=  status=done|absent|pending [by=auto]   (needs an open trip)
//   action=set_order order=12,5,9 planned stop order of the open trip (shown to students as "N stops before you")

ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bus_trips.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

function tOut(array $d): void { echo json_encode($d); exit; }

$key = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ($_REQUEST['key'] ?? '')));
if ($key === '' || strlen($key) > 128) tOut(['ok' => false, 'msg' => 'API key required']);
$action = (string)($_REQUEST['action'] ?? 'status');

try {
    $pdo = Database::connect();
    $q = $pdo->prepare("SELECT id, school_id, bus_name, bus_number FROM school_buses WHERE gps_api_key=? AND status='active' LIMIT 1");
    $q->execute([$key]);
    $bus = $q->fetch();
    if (!$bus) tOut(['ok' => false, 'msg' => 'Invalid or inactive bus key']);
    $busId = (int)$bus['id']; $schoolId = (int)$bus['school_id'];

    $fmt = function (?array $t) {
        return $t ? ['id' => (int)$t['id'], 'shift' => (int)$t['shift_no'], 'started_at' => $t['started_at']] : null;
    };

    if ($action === 'status') {
        $s = $pdo->prepare("SELECT MAX(shift_count) FROM bus_route_assignments WHERE bus_id=? AND school_id=? AND status='active'");
        $s->execute([$busId, $schoolId]);
        tOut(['ok' => true, 'bus_name' => $bus['bus_name'], 'bus_number' => $bus['bus_number'],
              'shift_count' => max(1, (int)$s->fetchColumn()), 'shifts' => busShiftList($pdo, $busId, $schoolId),
              'trip' => $fmt(busTripGetOpen($pdo, $busId))]);
    }

    if ($action === 'start') {
        $s = $pdo->prepare("SELECT MAX(shift_count) FROM bus_route_assignments WHERE bus_id=? AND school_id=? AND status='active'");
        $s->execute([$busId, $schoolId]);
        $shift = (int)($_REQUEST['shift'] ?? 1);
        if ($shift < 1 || $shift > max(1, (int)$s->fetchColumn())) $shift = 1;
        $r = busTripStart($pdo, $busId, $schoolId, $shift);
        tOut(['ok' => true, 'trip' => $fmt($r['trip']), 'already' => $r['already'], 'notified' => $r['notified']]);
    }

    if ($action === 'stop') {
        $open = busTripGetOpen($pdo, $busId);
        if (!$open) tOut(['ok' => true, 'trip' => null, 'msg' => 'No open trip']);
        $sum = busTripFinish($pdo, (int)$open['id'], 'driver');
        tOut(['ok' => true, 'summary' => $sum ? [
            'distance_km' => round($sum['distance_m'] / 1000, 1), 'max_speed' => $sum['max_speed'],
            'stops' => count($sum['stops']), 'minutes' => max(1, (int)round((time() - strtotime($open['started_at'])) / 60)),
        ] : null]);
    }

    if ($action === 'stops') {
        $open  = busTripGetOpen($pdo, $busId);
        $shift = $open ? (int)$open['shift_no'] : max(1, min(5, (int)($_REQUEST['shift'] ?? 1)));
        $list  = busStopsForShift($pdo, $busId, $schoolId, $shift, $open ? (int)$open['id'] : null);
        tOut(['ok' => true, 'shift' => $shift, 'trip' => $fmt($open)] + $list);
    }

    if ($action === 'mark_stop' || $action === 'set_order') {
        $open = busTripGetOpen($pdo, $busId);
        if (!$open) tOut(['ok' => false, 'msg' => 'Pehle trip shuru karein']);
        if ($action === 'mark_stop') {
            $ok = busTripMarkStop($pdo, $open, (int)($_REQUEST['student_id'] ?? 0), (string)($_REQUEST['status'] ?? ''), (string)($_REQUEST['by'] ?? 'driver'));
            tOut($ok ? ['ok' => true] : ['ok' => false, 'msg' => 'Ye student is shift mein nahi hai']);
        }
        $ids = array_filter(array_map('intval', explode(',', (string)($_REQUEST['order'] ?? ''))));
        tOut(['ok' => true, 'saved' => busTripSetOrder($pdo, $open, array_slice($ids, 0, 300))]);
    }

    tOut(['ok' => false, 'msg' => 'Unknown action']);
} catch (\Throwable $e) {
    error_log('bus_trip: ' . $e->getMessage());
    tOut(['ok' => false, 'msg' => 'Server error.']);
}
