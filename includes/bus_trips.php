<?php
// includes/bus_trips.php — trip lifecycle: start (notifies students), finish (stores a summary), auto-close.
// A "trip" is one run of a bus. Drivers start/stop it from api/driver_tracker.php (via api/bus_trip.php).

const BUS_TRIP_MAX_HOURS   = 14;    // an unfinished trip older than this is closed automatically
const BUS_TRIP_SILENT_MIN  = 30;    // ...and so is one whose bus has sent nothing for this long
const BUS_TRIP_STOP_SEC    = 120;   // a stand-still of at least this long counts as a "stop"
const BUS_TRIP_PATH_POINTS = 200;

function busTripDist(float $la1, float $lo1, float $la2, float $lo2): float
{
    $r = M_PI / 180; $dp = ($la2 - $la1) * $r; $dl = ($lo2 - $lo1) * $r;
    $a = sin($dp / 2) ** 2 + cos($la1 * $r) * cos($la2 * $r) * sin($dl / 2) ** 2;
    return 2 * 6371000 * asin(min(1.0, sqrt($a)));
}

function busTripGetOpen(PDO $pdo, int $busId): ?array
{
    $st = $pdo->prepare("SELECT * FROM bus_trips WHERE bus_id=? AND ended_at IS NULL ORDER BY id DESC LIMIT 1");
    $st->execute([$busId]);
    return $st->fetch() ?: null;
}

/**
 * Pure summary of GPS rows (each: lat,lng,speed,recorded_at as unix 't'), oldest first.
 * Parked buses only refresh the timestamp of their last row, so a stop shows up as a long time gap
 * with (almost) no movement between two rows.
 */
function busTripSummarize(array $rows): array
{
    $dist = 0.0; $max = 0.0; $stops = []; $n = count($rows);
    for ($i = 0; $i < $n; $i++) {
        $max = max($max, (float)$rows[$i]['speed']);
        if ($i === 0) continue;
        $a = $rows[$i - 1]; $b = $rows[$i];
        $d  = busTripDist((float)$a['lat'], (float)$a['lng'], (float)$b['lat'], (float)$b['lng']);
        $dt = max(1, $b['t'] - $a['t']);
        if ($d > 1500 && $dt < 60) continue;          // GPS jump, not driving
        if ($d >= 3) $dist += $d;
        if ($dt >= BUS_TRIP_STOP_SEC && ($d / $dt) * 3.6 < 5) {
            $stops[] = ['lat' => round((float)$a['lat'], 6), 'lng' => round((float)$a['lng'], 6),
                        'at' => date('Y-m-d H:i:s', $a['t']), 'min' => (int)round($dt / 60)];
        }
    }
    $dur = $n > 1 ? max(1, $rows[$n - 1]['t'] - $rows[0]['t']) : 0;
    $moving = max(1, $dur - array_sum(array_map(function ($s) { return $s['min'] * 60; }, $stops)));
    $step = max(1, (int)ceil($n / BUS_TRIP_PATH_POINTS));
    $path = [];
    foreach ($rows as $i => $r) if ($i % $step === 0 || $i === $n - 1) $path[] = [round((float)$r['lat'], 5), round((float)$r['lng'], 5)];
    return [
        'distance_m' => (int)round($dist),
        'max_speed'  => round($max, 1),
        'avg_speed'  => $dur ? round(($dist / $moving) * 3.6, 1) : 0.0,   // average while moving
        'points'     => $n,
        'stops'      => $stops,
        'path'       => $path,
    ];
}

/** Close a trip and store its summary. Returns the summary (or null if nothing to close). */
function busTripFinish(PDO $pdo, int $tripId, string $reason): ?array
{
    $t = $pdo->prepare("SELECT * FROM bus_trips WHERE id=? AND ended_at IS NULL");
    $t->execute([$tripId]);
    $trip = $t->fetch();
    if (!$trip) return null;

    $g = $pdo->prepare("SELECT lat, lng, speed, UNIX_TIMESTAMP(recorded_at) AS t FROM bus_gps_locations
                        WHERE school_id=? AND bus_id=? AND recorded_at >= ? ORDER BY recorded_at, id LIMIT 20000");
    $g->execute([$trip['school_id'], $trip['bus_id'], $trip['started_at']]);
    $rows = array_map(function ($r) { $r['t'] = (int)$r['t']; return $r; }, $g->fetchAll());
    $sum  = busTripSummarize($rows);

    $pdo->prepare("UPDATE bus_trips SET ended_at=NOW(), end_reason=?, distance_m=?, max_speed_kmh=?, avg_speed_kmh=?,
                   points=?, stops_json=?, path_json=? WHERE id=? AND ended_at IS NULL")
        ->execute([$reason, $sum['distance_m'], $sum['max_speed'], $sum['avg_speed'], $sum['points'],
                   json_encode($sum['stops']), json_encode($sum['path']), $tripId]);
    return $sum;
}

/** Start a trip (idempotent: returns the already-open one) and push "bus has left" to the students. */
function busTripStart(PDO $pdo, int $busId, int $schoolId, int $shift): array
{
    $open = busTripGetOpen($pdo, $busId);
    if ($open && strtotime($open['started_at']) > time() - BUS_TRIP_MAX_HOURS * 3600 - 7200) {
        return ['trip' => $open, 'already' => true, 'notified' => 0];
    }
    if ($open) busTripFinish($pdo, (int)$open['id'], 'auto_old');

    $pdo->prepare("INSERT INTO bus_trips (school_id, bus_id, shift_no, started_at) VALUES (?,?,?,NOW())")
        ->execute([$schoolId, $busId, $shift]);
    $trip = busTripGetOpen($pdo, $busId);
    return ['trip' => $trip, 'already' => false, 'notified' => busTripNotifyStart($pdo, $busId, $schoolId, $shift)];
}

/** Push to every subscribed student of this bus (and, when shifts are used, of this shift). */
function busTripNotifyStart(PDO $pdo, int $busId, int $schoolId, int $shift): int
{
    $sent = 0;
    try {
        $bn = $pdo->prepare("SELECT bus_name FROM school_buses WHERE id=?");
        $bn->execute([$busId]);
        $name = $bn->fetchColumn() ?: 'Bus';

        $st = $pdo->prepare("
            SELECT DISTINCT ps.id, ps.endpoint, ps.p256dh, ps.auth
            FROM bus_route_assignments bra
            JOIN student_van_assignments sva ON sva.van_route_id=bra.route_id AND sva.school_id=bra.school_id
            LEFT JOIN bus_student_shifts bss ON bss.student_id=sva.student_id AND bss.route_id=sva.van_route_id AND bss.school_id=sva.school_id
            JOIN push_subscriptions ps ON ps.student_id=sva.student_id AND ps.school_id=sva.school_id
            WHERE bra.bus_id=? AND bra.school_id=? AND bra.status='active' AND COALESCE(bss.shift_no,1)=?");
        $st->execute([$busId, $schoolId, $shift]);

        require_once __DIR__ . '/push_sender.php';
        foreach ($st->fetchAll() as $sub) {
            try {
                $res = sendProximityPush(
                    ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                    ['title' => 'Bus nikal gayi 🚌', 'body' => $name . ' ki trip shuru ho gayi hai. Live location dekhne ke liye kholein.',
                     'url' => (defined('BASE_URL') ? BASE_URL : '') . '/student/index.php#bus']
                );
                if (!empty($res['expired'])) $pdo->prepare("DELETE FROM push_subscriptions WHERE id=?")->execute([$sub['id']]);
                else $sent++;
            } catch (\Throwable $e) { error_log('bus_trip push: ' . $e->getMessage()); }
        }
    } catch (\Throwable $e) { error_log('bus_trip notify: ' . $e->getMessage()); }
    return $sent;
}

/** Close trips that were forgotten open (driver never pressed stop / phone died). Cron + watchdog call this. */
function busTripAutoClose(PDO $pdo): int
{
    $n = 0;
    try {
        $st = $pdo->query("
            SELECT t.id,
                   TIMESTAMPDIFF(HOUR, t.started_at, NOW()) AS hrs,
                   TIMESTAMPDIFF(MINUTE, COALESCE(l.recorded_at, t.started_at), NOW()) AS silent_min
            FROM bus_trips t LEFT JOIN bus_live l ON l.bus_id=t.bus_id
            WHERE t.ended_at IS NULL");
        foreach ($st->fetchAll() as $t) {
            if ((int)$t['hrs'] >= BUS_TRIP_MAX_HOURS) { busTripFinish($pdo, (int)$t['id'], 'auto_old'); $n++; }
            elseif ((int)$t['silent_min'] >= BUS_TRIP_SILENT_MIN) { busTripFinish($pdo, (int)$t['id'], 'auto_silent'); $n++; }
        }
    } catch (\Throwable $e) { error_log('bus_trip autoclose: ' . $e->getMessage()); }
    return $n;
}
