<?php
// includes/bus_alerts.php — safety alerts from live GPS. Called by api/gps_update.php after each LIVE fix
// (never for back-filled points), after the device already got its answer. Never throws.
//
//   overspeed      speed above the school's limit for at least N seconds (one GPS spike never alerts)
//   school_arrive  bus entered the school gate radius   → admin + parents of children on board (pickup trip)
//   school_leave   bus left the school gate radius      → admin + parents of this shift (drop trip)
//   deviation      during a trip, the bus is far from its learned everyday road for N seconds
//
// One event = one alert: the running state per bus lives in bus_alert_state. Settings are per school
// (bus_alert_settings), so every school decides its own limits. Everything is scoped by the bus's school_id.

require_once __DIR__ . '/bus_notify.php';

const BUS_ALERT_DEFAULTS = [
    'overspeed_kmh' => 50, 'overspeed_sec' => 20, 'school_lat' => null, 'school_lng' => null,
    'school_radius_m' => 150, 'deviation_m' => 400, 'deviation_sec' => 90, 'notify_parents' => 1,
];

function busAlertSettings(PDO $pdo, int $schoolId): array
{
    try {
        $q = $pdo->prepare("SELECT * FROM bus_alert_settings WHERE school_id=?");
        $q->execute([$schoolId]);
        $r = $q->fetch();
    } catch (\Throwable $e) { $r = null; }
    $s = BUS_ALERT_DEFAULTS;
    if ($r) foreach ($s as $k => $v) if (array_key_exists($k, $r)) $s[$k] = $r[$k];
    foreach (['overspeed_kmh', 'overspeed_sec', 'school_radius_m', 'deviation_m', 'deviation_sec', 'notify_parents'] as $k) $s[$k] = (int)$s[$k];
    $s['school_lat'] = $s['school_lat'] !== null ? (float)$s['school_lat'] : null;
    $s['school_lng'] = $s['school_lng'] !== null ? (float)$s['school_lng'] : null;
    return $s;
}

/** Shortest distance (m) from a point to a polyline [[lat,lng],...] (local flat-earth projection; fine for < 50 km). */
function busDistToPath(float $lat, float $lng, array $path): float
{
    $kx = cos(deg2rad($lat)) * 111320; $ky = 110540; $best = INF; $n = count($path);
    if ($n === 1) return hypot(($path[0][1] - $lng) * $kx, ($path[0][0] - $lat) * $ky);
    for ($i = 0; $i < $n - 1; $i++) {
        $ax = ($path[$i][1] - $lng) * $kx;     $ay = ($path[$i][0] - $lat) * $ky;
        $bx = ($path[$i + 1][1] - $lng) * $kx; $by = ($path[$i + 1][0] - $lat) * $ky;
        $dx = $bx - $ax; $dy = $by - $ay; $l2 = $dx * $dx + $dy * $dy;
        $t = $l2 > 0 ? max(0.0, min(1.0, -($ax * $dx + $ay * $dy) / $l2)) : 0.0;
        $best = min($best, hypot($ax + $t * $dx, $ay + $t * $dy));
    }
    return $best;
}

function busAlertCheck(PDO $pdo, int $busId, int $schoolId, float $lat, float $lng, float $speedKmh, ?float $accM): void
{
    try {
        if ($accM !== null && $accM > 60) return;               // rough fix: no alert decisions on it
        $set = busAlertSettings($pdo, $schoolId);

        $pdo->prepare("INSERT IGNORE INTO bus_alert_state (bus_id, school_id) VALUES (?,?)")->execute([$busId, $schoolId]);
        $q = $pdo->prepare("SELECT *, TIMESTAMPDIFF(SECOND, over_since, NOW()) AS over_s, TIMESTAMPDIFF(SECOND, off_since, NOW()) AS off_s
                            FROM bus_alert_state WHERE bus_id=? AND school_id=?");
        $q->execute([$busId, $schoolId]);
        $st = $q->fetch();
        if (!$st) return;

        $b = $pdo->prepare("SELECT bus_name, bus_number FROM school_buses WHERE id=? AND school_id=?");
        $b->execute([$busId, $schoolId]);
        $bus = $b->fetch() ?: ['bus_name' => 'Bus', 'bus_number' => ''];
        $label = $bus['bus_name'] . ($bus['bus_number'] ? ' (' . $bus['bus_number'] . ')' : '');

        require_once __DIR__ . '/bus_trips.php';
        $trip = busTripGetOpen($pdo, $busId);
        $tripId = $trip && (int)$trip['school_id'] === $schoolId ? (int)$trip['id'] : null;

        // ── Overspeed ───────────────────────────────────────────────────────
        $lim = max(10, $set['overspeed_kmh']);
        if ($speedKmh > $lim && $speedKmh < 200) {
            if ($st['over_since'] === null) {
                $pdo->prepare("UPDATE bus_alert_state SET over_since=NOW(), over_max=?, over_alerted=0 WHERE bus_id=?")->execute([$speedKmh, $busId]);
            } else {
                $max = max((float)$st['over_max'], $speedKmh);
                $pdo->prepare("UPDATE bus_alert_state SET over_max=? WHERE bus_id=?")->execute([$max, $busId]);
                if (!(int)$st['over_alerted'] && (int)$st['over_s'] >= $set['overspeed_sec']) {
                    busAdminAlert($pdo, $schoolId, $busId, $tripId, 'overspeed',
                        '⚠️ ' . $label . ' tez chal rahi hai: ' . round($max) . ' km/h (limit ' . $lim . ') — ' . (int)$st['over_s'] . ' sec se',
                        $lat, $lng, $max);
                    $pdo->prepare("UPDATE bus_alert_state SET over_alerted=1 WHERE bus_id=?")->execute([$busId]);
                }
            }
        } elseif ($st['over_since'] !== null && $speedKmh <= $lim - 5) {
            if ((int)$st['over_alerted']) {   // store the real top speed of that episode on its alert
                $pdo->prepare("UPDATE bus_alerts SET value=GREATEST(COALESCE(value,0), ?) WHERE bus_id=? AND school_id=? AND type='overspeed' ORDER BY id DESC LIMIT 1")
                    ->execute([(float)$st['over_max'], $busId, $schoolId]);
            }
            $pdo->prepare("UPDATE bus_alert_state SET over_since=NULL, over_max=NULL, over_alerted=0 WHERE bus_id=?")->execute([$busId]);
        }

        // ── School gate geofence (with hysteresis: in ≤ r, out > 1.5 r) ───────
        if ($set['school_lat'] !== null && $set['school_lng'] !== null) {
            $r = max(50, $set['school_radius_m']);
            $d = busTripDistM($lat, $lng, $set['school_lat'], $set['school_lng']);
            $was = $st['at_school'] === null ? null : (int)$st['at_school'];
            $now = $d <= $r ? 1 : ($d > $r * 1.5 ? 0 : $was);
            if ($now !== null && $now !== $was) {
                $pdo->prepare("UPDATE bus_alert_state SET at_school=? WHERE bus_id=?")->execute([$now, $busId]);
                if ($was !== null) busGeofenceEvent($pdo, $set, $schoolId, $busId, $trip, $tripId, $label, $now === 1, $lat, $lng);
            }
        }

        // ── Route deviation (only during a trip with a learned everyday road) ─
        $off = false;
        if ($trip && $tripId) {
            $kind = !empty($trip['kind']) ? (string)$trip['kind'] : busTripKind($pdo, $busId, $schoolId, (int)$trip['shift_no'], (string)$trip['started_at']);
            $lp = busLearnedProfile($pdo, $busId, (int)$trip['shift_no'], $kind);
            if ($lp && count($lp['path']) >= 10) {
                $dist = busDistToPath($lat, $lng, $lp['path']);
                $off = $dist > max(150, $set['deviation_m']);
                if ($off && $st['off_since'] === null) {
                    $pdo->prepare("UPDATE bus_alert_state SET off_since=NOW(), off_alerted=0 WHERE bus_id=?")->execute([$busId]);
                } elseif ($off && !(int)$st['off_alerted'] && (int)$st['off_s'] >= $set['deviation_sec']) {
                    busAdminAlert($pdo, $schoolId, $busId, $tripId, 'deviation',
                        '🧭 ' . $label . ' roz ke raaste se ' . round($dist) . ' m door chal rahi hai (' . (int)$st['off_s'] . ' sec se)', $lat, $lng, $dist);
                    $pdo->prepare("UPDATE bus_alert_state SET off_alerted=1 WHERE bus_id=?")->execute([$busId]);
                }
            }
        }
        if (!$off && $st['off_since'] !== null) {
            $pdo->prepare("UPDATE bus_alert_state SET off_since=NULL, off_alerted=0 WHERE bus_id=?")->execute([$busId]);
        }
    } catch (\Throwable $e) {
        error_log('bus_alerts: ' . $e->getMessage());
    }
}

function busTripDistM(float $la1, float $lo1, float $la2, float $lo2): float
{
    return busTripDist($la1, $lo1, $la2, $lo2);
}

/** Bus reached / left school: admin feed + (optionally) parents of the running trip. */
function busGeofenceEvent(PDO $pdo, array $set, int $schoolId, int $busId, ?array $trip, ?int $tripId, string $label,
                          bool $arrived, float $lat, float $lng): void
{
    $hm = date('H:i');
    busAdminAlert($pdo, $schoolId, $busId, $tripId, $arrived ? 'school_arrive' : 'school_leave',
        ($arrived ? '🏫 ' . $label . ' school pahunch gayi' : '🚌 ' . $label . ' school se nikal gayi') . ' (' . $hm . ')', $lat, $lng, null, 5);
    if (!$trip || !$tripId || !$set['notify_parents']) return;

    // Parents only where it means something: arriving on the morning pickup run, leaving on the afternoon drop run.
    // (An empty bus leaving after the pickup run, or arriving for the drop run, is not news for parents.)
    $kind = !empty($trip['kind']) ? (string)$trip['kind'] : busTripKind($pdo, $busId, $schoolId, (int)$trip['shift_no'], (string)$trip['started_at']);
    if ($arrived ? $kind === 'drop' : $kind !== 'drop') return;
    $list = busStopsForShift($pdo, $busId, $schoolId, (int)$trip['shift_no'], $tripId);
    if ($arrived) {
        // Children on board = marked ✔ on this trip
        $ids = array_column(array_filter($list['stops'], function ($s) { return $s['status'] === 'done'; }), 'id');
        if ($ids) busNotifyStudents($pdo, $schoolId, $ids, 'Bus school pahunch gayi 🏫', $label . ' ' . $hm . ' par school pahunch gayi.', 'Bus school pahunchi');
    } else {
        // Leaving school on the way home: everyone of the shift who is not marked absent
        $ids = array_merge(array_column(array_filter($list['stops'], function ($s) { return $s['status'] !== 'absent'; }), 'id'), array_column($list['missing'], 'id'));
        if ($ids) busNotifyStudents($pdo, $schoolId, $ids, 'Bus school se nikal gayi 🚌', $label . ' ' . $hm . ' par school se nikal gayi.', 'Bus school se nikli');
    }
}
