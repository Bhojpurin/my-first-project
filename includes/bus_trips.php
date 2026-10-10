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

// ─────────────────────────────────────────────────────────────────────────────
// Stops: students of one shift with their marked home location (for the driver's map)
// ─────────────────────────────────────────────────────────────────────────────

const BUS_DRIVER_FULL_NAMES = false;   // false: driver sees "Rahul K." instead of the full name (the link has no login)

/** "Rahul Kumar Singh" → "Rahul K." (privacy: the driver page is protected only by the bus key). */
function busShortName(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if (BUS_DRIVER_FULL_NAMES || $name === '') return $name;
    $w = explode(' ', $name);
    return count($w) > 1 ? $w[0] . ' ' . mb_strtoupper(mb_substr($w[1], 0, 1)) . '.' : $w[0];
}

/** SQL for "this student's effective shift": unassigned → 1, a shift the route no longer has → 1 (same rule as alerts). */
const BUS_EFFECTIVE_SHIFT_SQL = "CASE WHEN COALESCE(bss.shift_no,1) BETWEEN 1 AND GREATEST(1,bra.shift_count) THEN COALESCE(bss.shift_no,1) ELSE 1 END";

/** Shifts this bus runs, merged over its active routes: [{no, pickup, drop, students, with_home}] */
function busShiftList(PDO $pdo, int $busId, int $schoolId): array
{
    $a = $pdo->prepare("SELECT * FROM bus_route_assignments WHERE bus_id=? AND school_id=? AND status='active' ORDER BY id");
    $a->execute([$busId, $schoolId]);
    $rows = $a->fetchAll();
    $max = 1;
    foreach ($rows as $r) $max = max($max, min(5, (int)$r['shift_count']));

    $cnt = $pdo->prepare("
        SELECT " . BUS_EFFECTIVE_SHIFT_SQL . " AS sh, COUNT(DISTINCT s.id) AS n, COUNT(DISTINCT hl.student_id) AS h
        FROM bus_route_assignments bra
        JOIN student_van_assignments sva ON sva.van_route_id=bra.route_id AND sva.school_id=bra.school_id
        JOIN students s ON s.id=sva.student_id AND s.status='active'
        LEFT JOIN bus_student_shifts bss ON bss.student_id=sva.student_id AND bss.route_id=sva.van_route_id AND bss.school_id=sva.school_id
        LEFT JOIN student_home_locations hl ON hl.student_id=s.id AND hl.school_id=sva.school_id
        WHERE bra.bus_id=? AND bra.school_id=? AND bra.status='active'
        GROUP BY sh");
    $cnt->execute([$busId, $schoolId]);
    $counts = [];
    foreach ($cnt->fetchAll() as $c) $counts[(int)$c['sh']] = $c;

    $out = [];
    for ($n = 1; $n <= $max; $n++) {
        $sfx = $n === 1 ? '' : (string)$n;
        $pick = null; $drop = null;
        foreach ($rows as $r) {
            if ((int)$r['shift_count'] < $n) continue;
            $pick = $pick ?: ($r['pickup_time' . $sfx] ?? null);
            $drop = $drop ?: ($r['drop_time' . $sfx] ?? null);
        }
        $out[] = ['no' => $n, 'pickup' => $pick ? substr($pick, 0, 5) : null, 'drop' => $drop ? substr($drop, 0, 5) : null,
                  'students' => (int)($counts[$n]['n'] ?? 0), 'with_home' => (int)($counts[$n]['h'] ?? 0)];
    }
    return $out;
}

/**
 * Students of one shift of this bus. Returns ['stops' => [...with home...], 'missing' => [...no home yet...]].
 * With $tripId, each stop also carries that trip's status / planned seq.
 */
function busStopsForShift(PDO $pdo, int $busId, int $schoolId, int $shift, ?int $tripId = null): array
{
    $st = $pdo->prepare("
        SELECT s.id, s.name, c.class_name, sec.section_name, hl.lat, hl.lng
        FROM bus_route_assignments bra
        JOIN student_van_assignments sva ON sva.van_route_id=bra.route_id AND sva.school_id=bra.school_id
        JOIN students s ON s.id=sva.student_id AND s.status='active'
        LEFT JOIN classes c ON c.id=s.class_id
        LEFT JOIN sections sec ON sec.id=s.section_id
        LEFT JOIN bus_student_shifts bss ON bss.student_id=sva.student_id AND bss.route_id=sva.van_route_id AND bss.school_id=sva.school_id
        LEFT JOIN student_home_locations hl ON hl.student_id=s.id AND hl.school_id=sva.school_id
        WHERE bra.bus_id=? AND bra.school_id=? AND bra.status='active' AND " . BUS_EFFECTIVE_SHIFT_SQL . " = ?
        ORDER BY s.name");
    $st->execute([$busId, $schoolId, $shift]);

    $marks = [];
    if ($tripId) {
        $m = $pdo->prepare("SELECT student_id, seq, status, marked_by, marked_at FROM bus_trip_stops WHERE trip_id=?");
        $m->execute([$tripId]);
        foreach ($m->fetchAll() as $r) $marks[(int)$r['student_id']] = $r;
    }

    $stops = []; $missing = []; $seen = [];
    foreach ($st->fetchAll() as $r) {
        $id = (int)$r['id'];
        if (isset($seen[$id])) continue;          // student on two routes of the same bus → once
        $seen[$id] = true;
        $cls = trim(($r['class_name'] ?? '') . ($r['section_name'] ? '-' . $r['section_name'] : ''));
        $lat = $r['lat'] !== null ? (float)$r['lat'] : null; $lng = $r['lng'] !== null ? (float)$r['lng'] : null;
        if ($lat === null || $lng === null || ($lat == 0 && $lng == 0)) { $missing[] = ['name' => busShortName($r['name']), 'cls' => $cls]; continue; }
        $mk = $marks[$id] ?? null;
        $stops[] = ['id' => $id, 'name' => busShortName($r['name']), 'cls' => $cls,
                    'lat' => round($lat, 6), 'lng' => round($lng, 6),
                    'status' => $mk['status'] ?? 'pending', 'seq' => isset($mk['seq']) ? (int)$mk['seq'] : null,
                    'by' => $mk['marked_by'] ?? null, 'at' => $mk['marked_at'] ?? null];
    }
    return ['stops' => $stops, 'missing' => $missing];
}

/** Driver marks a stop. Only students of the trip's own shift can be marked. */
function busTripMarkStop(PDO $pdo, array $trip, int $studentId, string $status, string $by): bool
{
    if (!in_array($status, ['pending', 'done', 'absent'], true)) return false;
    $by = $by === 'auto' ? 'auto' : 'driver';
    $list = busStopsForShift($pdo, (int)$trip['bus_id'], (int)$trip['school_id'], (int)$trip['shift_no']);
    if (!in_array($studentId, array_column($list['stops'], 'id'), true)) return false;
    $pdo->prepare("INSERT INTO bus_trip_stops (trip_id, student_id, status, marked_by, marked_at) VALUES (?,?,?,?,NOW())
                   ON DUPLICATE KEY UPDATE status=VALUES(status), marked_by=VALUES(marked_by), marked_at=VALUES(marked_at)")
        ->execute([(int)$trip['id'], $studentId, $status, $status === 'pending' ? null : $by]);
    return true;
}

/** Store the driver's planned order (list of student ids, first = next). Unknown ids are ignored. */
function busTripSetOrder(PDO $pdo, array $trip, array $ids): int
{
    $list  = busStopsForShift($pdo, (int)$trip['bus_id'], (int)$trip['school_id'], (int)$trip['shift_no']);
    $valid = array_flip(array_column($list['stops'], 'id'));
    $up = $pdo->prepare("INSERT INTO bus_trip_stops (trip_id, student_id, seq) VALUES (?,?,?) ON DUPLICATE KEY UPDATE seq=VALUES(seq)");
    $n = 0; $done = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if (!isset($valid[$id]) || isset($done[$id])) continue;
        $done[$id] = true;
        $up->execute([(int)$trip['id'], $id, ++$n]);
    }
    return $n;
}
