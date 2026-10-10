<?php
// api/bus_trip.php — the driver phone's API for ONE bus. POST only.
//
// Auth: a paired-phone device token in the "X-Device-Token" header (see includes/bus_security.php).
//       The bus API key is NOT accepted here: it is a write-only GPS credential and must never unlock
//       student names or home locations. The bus and its school always come from the token, never from
//       the request, so one school can never reach another school's data.
//
//   action=pair   code=…                one-time pairing code from the admin's link → {token} (no auth needed)
//   action=status                       bus name, number, shifts, and the open trip (if any)
//   action=start  [shift=1..5]          open a trip (idempotent) and push "bus has left" to the students
//   action=stop                         close the open trip and return its summary (km, max speed, stops)
//   action=stops  [shift=N]             students of that shift with their marked home + note (open trip's shift wins)
//   action=mark_stop student_id= status=done|absent|pending [by=auto] [ago=sec]   (needs an open trip)
//   action=set_order order=12,5,9       planned stop order of the open trip (shown to parents as "N stops before you")
//   action=eta    etas=12:340,5:610     seconds until the bus reaches each home (parent ETA)
//   action=halt   reason= text= delay= lat= lng= halt_sec=   why the bus stopped on the road (→ admin alert)
//   action=unpair                       this phone gives up its token

ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bus_db.php';
require_once __DIR__ . '/../includes/bus_trips.php';
require_once __DIR__ . '/../includes/bus_security.php';
ob_end_clean();

busApiHeaders();   // same-origin only: no CORS headers on purpose

function tOut(array $d, int $code = 200): void { if ($code !== 200) http_response_code($code); echo json_encode($d); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') tOut(['ok' => false, 'msg' => 'POST required'], 405);
// A request started by another website must never act for this phone
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') tOut(['ok' => false, 'msg' => 'Cross-site request blocked'], 403);

$action = (string)($_POST['action'] ?? 'status');
$ip     = busClientIp();

try {
    $pdo = busDb(Database::connect());

    // ── Pairing (the only action without a token) ────────────────────────────
    if ($action === 'pair') {
        if (busRateBlocked($pdo, 'pairfail:' . $ip, 10, 900)) busTooMany();   // 10 wrong codes / 15 min / IP
        $r = busRedeemPairCode($pdo, strtolower(trim((string)($_POST['code'] ?? ''))), busDeviceLabel());
        if (!$r) {
            busRateHit($pdo, 'pairfail:' . $ip, 10, 900);
            tOut(['ok' => false, 'msg' => 'Ye link istemaal ho chuka hai ya expire ho gaya. School admin se naya link lein.'], 400);
        }
        $b = busByDeviceToken($pdo, $r['token']);
        tOut(['ok' => true, 'token' => $r['token'], 'bus_name' => $b['bus_name'] ?? '', 'bus_number' => $b['bus_number'] ?? '']);
    }

    // ── Everything else: paired phone only ───────────────────────────────────
    if (busRateBlocked($pdo, 'tokfail:' . $ip, 30, 900)) busTooMany();
    $bus = busByDeviceToken($pdo, busDeviceTokenFromRequest());
    if (!$bus) {
        busRateHit($pdo, 'tokfail:' . $ip, 30, 900);
        tOut(['ok' => false, 'code' => 'unpaired', 'msg' => 'Ye phone pair nahi hai ya admin ne hata diya hai. School admin se naya link lein.'], 401);
    }
    // Generous per-phone limit (a normal trip makes a few requests a minute)
    if (!busRateHitFast('dev:' . $bus['device_id'], 240, 60)) busTooMany();

    $busId = (int)$bus['id']; $schoolId = (int)$bus['school_id'];
    $fmt = function (?array $t) {
        return $t ? ['id' => (int)$t['id'], 'shift' => (int)$t['shift_no'], 'started_at' => $t['started_at']] : null;
    };
    $maxShift = function () use ($pdo, $busId, $schoolId) {
        $s = $pdo->prepare("SELECT MAX(shift_count) FROM bus_route_assignments WHERE bus_id=? AND school_id=? AND status='active'");
        $s->execute([$busId, $schoolId]);
        return max(1, (int)$s->fetchColumn());
    };

    if ($action === 'unpair') {
        busForgetDevices($pdo, ['id=?', 'school_id=?'], [(int)$bus['device_id'], $schoolId]);
        $pdo->prepare("UPDATE bus_driver_devices SET revoked_at=NOW() WHERE id=? AND school_id=?")->execute([(int)$bus['device_id'], $schoolId]);
        tOut(['ok' => true]);
    }

    if ($action === 'status') {
        tOut(['ok' => true, 'bus_name' => $bus['bus_name'], 'bus_number' => $bus['bus_number'],
              'shift_count' => $maxShift(), 'shifts' => busShiftList($pdo, $busId, $schoolId),
              'trip' => $fmt(busTripGetOpen($pdo, $busId))]);
    }

    if ($action === 'start') {
        $shift = (int)($_POST['shift'] ?? 1);
        if ($shift < 1 || $shift > $maxShift()) $shift = 1;
        $r = busTripStart($pdo, $busId, $schoolId, $shift, false);
        echo json_encode(['ok' => true, 'trip' => $fmt($r['trip']), 'already' => $r['already'], 'notified' => $r['notified']]);
        if (!empty($r['notify_later'])) {   // the driver is not kept waiting while parents are notified
            busFinishResponse();
            @ignore_user_abort(true);
            busTripNotifyStart($pdo, $busId, $schoolId, $shift);
        }
        exit;
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

    if ($action === 'halt') {
        if (!busRateHit($pdo, 'halt:' . $bus['device_id'], 12, 3600)) busTooMany();
        require_once __DIR__ . '/../includes/bus_halt.php';
        $aid = busHaltReport($pdo, $bus, $_POST);
        tOut(['ok' => (bool)$aid, 'msg' => $aid ? 'School ko bata diya gaya.' : 'Save nahi hua.']);
    }

    if ($action === 'stops') {
        $open  = busTripGetOpen($pdo, $busId);
        $shift = $open ? (int)$open['shift_no'] : max(1, min(5, (int)($_POST['shift'] ?? 1)));
        $list  = busStopsForShift($pdo, $busId, $schoolId, $shift, $open ? (int)$open['id'] : null);
        $kind  = busTripKind($pdo, $busId, $schoolId, $shift, $open ? (string)$open['started_at'] : (string)$pdo->query("SELECT NOW()")->fetchColumn());
        require_once __DIR__ . '/../includes/bus_alerts.php';
        $as = busAlertSettings($pdo, $schoolId);
        tOut(['ok' => true, 'shift' => $shift, 'kind' => $kind, 'trip' => $fmt($open), 'learned' => busLearnedProfile($pdo, $busId, $shift, $kind),
              'halt' => ['ask_min' => $as['halt_ask_min'], 'school' => $as['school_lat'] !== null
                  ? ['lat' => $as['school_lat'], 'lng' => $as['school_lng'], 'r' => $as['school_radius_m']] : null]] + $list);
    }

    if (in_array($action, ['mark_stop', 'set_order', 'eta'], true)) {
        $open = busTripGetOpen($pdo, $busId);
        if (!$open) tOut(['ok' => false, 'msg' => 'Pehle trip shuru karein']);
        if ($action === 'mark_stop') {
            $ok = busTripMarkStop($pdo, $open, (int)($_POST['student_id'] ?? 0), (string)($_POST['status'] ?? ''),
                                  (string)($_POST['by'] ?? 'driver'), (int)($_POST['ago'] ?? 0));
            tOut($ok ? ['ok' => true] : ['ok' => false, 'msg' => 'Ye student is shift mein nahi hai']);
        }
        if ($action === 'set_order') {
            $ids = array_filter(array_map('intval', explode(',', (string)($_POST['order'] ?? ''))));
            tOut(['ok' => true, 'saved' => busTripSetOrder($pdo, $open, array_slice($ids, 0, 300))]);
        }
        $etas = [];
        foreach (explode(',', (string)($_POST['etas'] ?? '')) as $pair) {
            [$id, $sec] = array_pad(explode(':', $pair, 2), 2, null);
            if ((int)$id > 0 && is_numeric($sec)) $etas[(int)$id] = (int)$sec;
            if (count($etas) >= 300) break;
        }
        tOut(['ok' => true, 'saved' => busTripSetEtas($pdo, $open, $etas)]);
    }

    tOut(['ok' => false, 'msg' => 'Unknown action'], 400);
} catch (\Throwable $e) {
    error_log('bus_trip: ' . $e->getMessage());
    tOut(['ok' => false, 'msg' => 'Server error.'], 500);
}
