<?php
// includes/bus_trips.php — trip lifecycle: start (notifies students), finish (stores a summary), auto-close.
// A "trip" is one run of a bus. Drivers start/stop it from api/driver_tracker.php (via api/bus_trip.php).

const BUS_TRIP_MAX_HOURS   = 14;    // an unfinished trip older than this is closed automatically
const BUS_TRIP_SILENT_MIN  = 30;    // ...and so is one whose bus has sent nothing for this long
const BUS_TRIP_STOP_SEC    = 120;   // a stand-still of at least this long counts as a "stop"
const BUS_SCHOOL_TZ        = 'Asia/Kolkata';   // "today" for parents' absence notes
const BUS_TRIP_PATH_POINTS = 400;   // enough to draw the real road shape of a school route
const BUS_LEARN_MIN_TRIPS  = 4;     // after this many consistent trips the learned order becomes the default
const BUS_LEARN_KEEP_TRIPS = 7;     // learn from the last N trips (old habits fade out)
const BUS_LEARN_MIN_CONF   = 0.6;   // ...and only if the driver follows it at least this consistently

function busTripDist(float $la1, float $lo1, float $la2, float $lo2): float
{
    $r = M_PI / 180; $dp = ($la2 - $la1) * $r; $dl = ($lo2 - $lo1) * $r;
    $a = sin($dp / 2) ** 2 + cos($la1 * $r) * cos($la2 * $r) * sin($dl / 2) ** 2;
    return 2 * 6371000 * asin(min(1.0, sqrt($a)));
}

/** Open trip, cached for 15 s (GPS updates call this on every fix). Start/stop clear the cache at once. */
function busTripGetOpenCached(PDO $pdo, int $busId): ?array
{
    require_once __DIR__ . '/bus_cache.php';
    $v = busCacheGet('trip:' . $busId, $hit);
    if ($hit) return $v ?: null;
    $t = busTripGetOpen($pdo, $busId);
    busCacheSet('trip:' . $busId, $t ?: false, 15);
    return $t;
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
            // A long stand-still is stored as one row every few minutes: join those pieces into ONE stop
            $k = count($stops) - 1;
            if ($k >= 0 && $stops[$k]['_end'] === $a['t'] && busTripDist($stops[$k]['lat'], $stops[$k]['lng'], (float)$a['lat'], (float)$a['lng']) < 40) {
                $stops[$k]['_sec'] += $dt; $stops[$k]['_end'] = $b['t']; $stops[$k]['min'] = (int)round($stops[$k]['_sec'] / 60);
            } else {
                $stops[] = ['lat' => round((float)$a['lat'], 6), 'lng' => round((float)$a['lng'], 6),
                            'at' => date('Y-m-d H:i:s', $a['t']), 'min' => (int)round($dt / 60), '_sec' => $dt, '_end' => $b['t']];
            }
        }
    }
    $stops = array_map(function ($s) { unset($s['_sec'], $s['_end']); return $s; }, $stops);
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

    require_once __DIR__ . '/bus_cache.php';
    busCacheDel('trip:' . (int)$trip['bus_id']);
    $pdo->prepare("UPDATE bus_trips SET ended_at=NOW(), end_reason=?, distance_m=?, max_speed_kmh=?, avg_speed_kmh=?,
                   points=?, stops_json=?, path_json=? WHERE id=? AND ended_at IS NULL")
        ->execute([$reason, $sum['distance_m'], $sum['max_speed'], $sum['avg_speed'], $sum['points'],
                   json_encode($sum['stops']), json_encode($sum['path']), $tripId]);
    try { busLearnFromTrip($pdo, $trip, $sum['path']); } catch (\Throwable $e) { error_log('bus_learn: ' . $e->getMessage()); }
    return $sum;
}

/** Start a trip (idempotent: returns the already-open one) and push "bus has left" to the students. */
function busTripStart(PDO $pdo, int $busId, int $schoolId, int $shift, bool $notifyNow = true): array
{
    $open = busTripGetOpen($pdo, $busId);
    if ($open && strtotime($open['started_at']) > time() - BUS_TRIP_MAX_HOURS * 3600 - 7200) {
        return ['trip' => $open, 'already' => true, 'notified' => 0];
    }
    if ($open) busTripFinish($pdo, (int)$open['id'], 'auto_old');

    $kind = busTripKind($pdo, $busId, $schoolId, $shift, (string)$pdo->query("SELECT NOW()")->fetchColumn());
    try {
        $pdo->prepare("INSERT INTO bus_trips (school_id, bus_id, shift_no, kind, started_at) VALUES (?,?,?,?,NOW())")
            ->execute([$schoolId, $busId, $shift, $kind]);
    } catch (\PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1054) throw $e;    // "kind" column not migrated yet
        $pdo->prepare("INSERT INTO bus_trips (school_id, bus_id, shift_no, started_at) VALUES (?,?,?,NOW())")
            ->execute([$schoolId, $busId, $shift]);
    }
    require_once __DIR__ . '/bus_cache.php';
    busCacheDel('trip:' . $busId);
    $trip = busTripGetOpen($pdo, $busId);
    busApplyAbsences($pdo, $trip);   // parents' "not today" → ✖ before the driver even starts
    if (!$notifyNow) {   // caller answers the phone first, then calls busTripNotifyStart()
        $l = busStopsForShift($pdo, $busId, $schoolId, $shift);
        return ['trip' => $trip, 'already' => false, 'notified' => count($l['stops']) + count($l['missing']), 'notify_later' => true];
    }
    return ['trip' => $trip, 'already' => false, 'notified' => busTripNotifyStart($pdo, $busId, $schoolId, $shift)];
}

function busToday(): string { return (new DateTime('now', new DateTimeZone(BUS_SCHOOL_TZ)))->format('Y-m-d'); }

/** Does a parent's absence kind (pickup/drop/both) cover a trip of this direction? */
function busAbsenceCovers(?string $absence, string $tripKind): bool
{
    if (!$absence) return false;
    return $absence === 'both' || $tripKind === 'any' || $absence === $tripKind;
}

/** Mark today's parent-reported absences of the trip's shift as ✖ (by 'parent'), if still pending. */
function busApplyAbsences(PDO $pdo, ?array $trip): int
{
    if (!$trip) return 0;
    $kind = !empty($trip['kind']) ? (string)$trip['kind'] : busTripKind($pdo, (int)$trip['bus_id'], (int)$trip['school_id'], (int)$trip['shift_no'], (string)$trip['started_at']);
    $l = busStopsForShift($pdo, (int)$trip['bus_id'], (int)$trip['school_id'], (int)$trip['shift_no'], (int)$trip['id']);
    $n = 0;
    foreach (array_merge($l['stops'], $l['missing']) as $s) {
        if (($s['status'] ?? 'pending') !== 'pending' || !busAbsenceCovers($s['absence'] ?? null, $kind)) continue;
        $pdo->prepare("INSERT INTO bus_trip_stops (trip_id, student_id, status, marked_by, marked_at) VALUES (?,?,'absent','parent',NOW())
                       ON DUPLICATE KEY UPDATE status=IF(status='pending','absent',status), marked_by=IF(status='absent' AND marked_by IS NULL,'parent',marked_by),
                         marked_at=COALESCE(marked_at, NOW())")
            ->execute([(int)$trip['id'], (int)$s['id']]);
        $n++;
    }
    return $n;
}

/** "Bus has left" to every student of this bus + shift — push and the panel's own Messages (bus_notify.php). */
function busTripNotifyStart(PDO $pdo, int $busId, int $schoolId, int $shift): int
{
    try {
        require_once __DIR__ . '/bus_notify.php';
        $bn = $pdo->prepare("SELECT bus_name FROM school_buses WHERE id=? AND school_id=?");
        $bn->execute([$busId, $schoolId]);
        $name = $bn->fetchColumn() ?: 'Bus';
        $ids = array_merge(array_column(($l = busStopsForShift($pdo, $busId, $schoolId, $shift))['stops'], 'id'), array_column($l['missing'], 'id'));
        return busNotifyStudents($pdo, $schoolId, $ids, 'Bus nikal gayi 🚌',
            $name . ' ki trip shuru ho gayi hai. Live location aur ETA ke liye Bus tab kholein.', 'Bus trip shuru');
    } catch (\Throwable $e) { error_log('bus_trip notify: ' . $e->getMessage()); return 0; }
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
function busStopsForShift(PDO $pdo, int $busId, int $schoolId, int $shift, ?int $tripId = null, bool $fullNames = false): array
{
    $sql = "
        SELECT s.id, s.name, c.class_name, sec.section_name, hl.lat, hl.lng, hl.note
        FROM bus_route_assignments bra
        JOIN student_van_assignments sva ON sva.van_route_id=bra.route_id AND sva.school_id=bra.school_id
        JOIN students s ON s.id=sva.student_id AND s.status='active'
        LEFT JOIN classes c ON c.id=s.class_id
        LEFT JOIN sections sec ON sec.id=s.section_id
        LEFT JOIN bus_student_shifts bss ON bss.student_id=sva.student_id AND bss.route_id=sva.van_route_id AND bss.school_id=sva.school_id
        LEFT JOIN student_home_locations hl ON hl.student_id=s.id AND hl.school_id=sva.school_id
        WHERE bra.bus_id=? AND bra.school_id=? AND bra.status='active' AND " . BUS_EFFECTIVE_SHIFT_SQL . " = ?
        ORDER BY s.name";
    try {
        $st = $pdo->prepare($sql);
        $st->execute([$busId, $schoolId, $shift]);
    } catch (\PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1054) throw $e;          // "note" column not migrated yet
        $st = $pdo->prepare(str_replace('hl.note', 'NULL AS note', $sql));
        $st->execute([$busId, $schoolId, $shift]);
    }

    // Parents' notes for today ("will not take the bus")
    $absent = [];
    try {
        $a = $pdo->prepare("SELECT student_id, kind, note FROM bus_absences WHERE school_id=? AND on_date=?");
        $a->execute([$schoolId, busToday()]);
        foreach ($a->fetchAll() as $r) $absent[(int)$r['student_id']] = $r;
    } catch (\Throwable $e) {}   // table not migrated yet

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
        $nm  = $fullNames ? trim((string)$r['name']) : busShortName((string)$r['name']);
        if ($lat === null || $lng === null || ($lat == 0 && $lng == 0)) { $missing[] = ['id' => $id, 'name' => $nm, 'cls' => $cls, 'absence' => $absent[$id]['kind'] ?? null]; continue; }
        $mk = $marks[$id] ?? null;
        $stops[] = ['id' => $id, 'name' => $nm, 'cls' => $cls,
                    'lat' => round($lat, 6), 'lng' => round($lng, 6),
                    'note' => trim((string)($r['note'] ?? '')),
                    'absence' => $absent[$id]['kind'] ?? null, 'absence_note' => $absent[$id]['note'] ?? null,
                    'status' => $mk['status'] ?? 'pending', 'seq' => isset($mk['seq']) ? (int)$mk['seq'] : null,
                    'by' => $mk['marked_by'] ?? null, 'at' => $mk['marked_at'] ?? null];
    }
    return ['stops' => $stops, 'missing' => $missing];
}

/**
 * Driver marks a stop. Only students of the trip's own shift can be marked.
 * $agoSec: the mark was made this long ago on the phone (it waited offline) — stored at its real time.
 */
function busTripMarkStop(PDO $pdo, array $trip, int $studentId, string $status, string $by, int $agoSec = 0): bool
{
    $agoSec = max(0, min(6 * 3600, $agoSec));
    if (!in_array($status, ['pending', 'done', 'absent'], true)) return false;
    $by = in_array($by, ['auto', 'parent'], true) ? $by : 'driver';
    $list = busStopsForShift($pdo, (int)$trip['bus_id'], (int)$trip['school_id'], (int)$trip['shift_no']);
    if (!in_array($studentId, array_column($list['stops'], 'id'), true)) return false;
    $pdo->prepare("INSERT INTO bus_trip_stops (trip_id, student_id, status, marked_by, marked_at)
                   VALUES (?,?,?,?, IF(?='pending', NULL, GREATEST(?, NOW() - INTERVAL ? SECOND)))
                   ON DUPLICATE KEY UPDATE status=VALUES(status), marked_by=VALUES(marked_by), marked_at=VALUES(marked_at)")
        ->execute([(int)$trip['id'], $studentId, $status, $status === 'pending' ? null : $by, $status, $trip['started_at'], $agoSec]);
    return true;
}

/** Store the driver phone's ETA (seconds from now) per student of the open trip. Unknown ids are ignored. */
function busTripSetEtas(PDO $pdo, array $trip, array $etas): int
{
    if (!$etas) return 0;
    $list  = busStopsForShift($pdo, (int)$trip['bus_id'], (int)$trip['school_id'], (int)$trip['shift_no']);
    $valid = array_flip(array_column($list['stops'], 'id'));
    // One multi-row statement for the whole bus (not one query per student)
    $vals = []; $par = [];
    foreach ($etas as $id => $sec) {
        if (!isset($valid[(int)$id])) continue;
        $vals[] = '(?,?, NOW() + INTERVAL ? SECOND)';
        array_push($par, (int)$trip['id'], (int)$id, max(0, min(7200, (int)$sec)));
    }
    $n = count($vals);
    if ($n) {
        try {
            $pdo->prepare("INSERT INTO bus_trip_stops (trip_id, student_id, eta_at) VALUES " . implode(',', $vals) . " ON DUPLICATE KEY UPDATE eta_at=VALUES(eta_at)")
                ->execute($par);
        } catch (\PDOException $e) { if ((int)($e->errorInfo[1] ?? 0) === 1054) return 0; throw $e; }   // eta_at not migrated
    }
    busEtaNotify($pdo, $trip);
    return $n;
}

const BUS_ETA_NOTIFY_SEC = 300;   // "bus ~5 min mein aapke ghar" — once per student per trip

/** Parents whose bus is now about BUS_ETA_NOTIFY_SEC away get one push + auto message. */
function busEtaNotify(PDO $pdo, array $trip): void
{
    try {
        $q = $pdo->prepare("SELECT student_id, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), eta_at)) AS s FROM bus_trip_stops
                            WHERE trip_id=? AND status='pending' AND eta_notified=0 AND eta_at IS NOT NULL
                              AND eta_at <= NOW() + INTERVAL " . (int)BUS_ETA_NOTIFY_SEC . " SECOND");
        $q->execute([(int)$trip['id']]);
        $rows = $q->fetchAll();
        if (!$rows) return;
        require_once __DIR__ . '/bus_notify.php';
        $bn = $pdo->prepare("SELECT bus_name FROM school_buses WHERE id=? AND school_id=?");
        $bn->execute([(int)$trip['bus_id'], (int)$trip['school_id']]);
        $name = $bn->fetchColumn() ?: 'Bus';
        $mark = $pdo->prepare("UPDATE bus_trip_stops SET eta_notified=1 WHERE trip_id=? AND student_id=?");
        foreach ($rows as $r) {
            $mark->execute([(int)$trip['id'], (int)$r['student_id']]);   // mark first: never send twice
            $min = max(1, (int)round($r['s'] / 60));
            busNotifyStudents($pdo, (int)$trip['school_id'], [(int)$r['student_id']], 'Bus aa rahi hai 🚌',
                $name . ' lagbhag ' . $min . ' min mein aapke ghar pahunchegi. Tayyar rahein.', 'Bus ETA');
        }
    } catch (\Throwable $e) { error_log('bus_eta_notify: ' . $e->getMessage()); }
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

// ─────────────────────────────────────────────────────────────────────────────
// Route learning: "the order the driver really follows" + "the road the bus really drives"
// ─────────────────────────────────────────────────────────────────────────────

/** Consensus order of several visit orders: average relative position of each student (0 = first, 1 = last). */
function busLearnConsensus(array $orders): array
{
    $sum = []; $cnt = [];
    foreach ($orders as $o) {
        $n = count($o);
        foreach (array_values($o) as $i => $id) {
            $pos = $n > 1 ? $i / ($n - 1) : 0.5;
            $sum[$id] = ($sum[$id] ?? 0) + $pos; $cnt[$id] = ($cnt[$id] ?? 0) + 1;
        }
    }
    $ids = array_keys($sum);
    usort($ids, function ($a, $b) use ($sum, $cnt) {
        $d = $sum[$a] / $cnt[$a] <=> $sum[$b] / $cnt[$b];
        return $d ?: ($cnt[$b] <=> $cnt[$a]) ?: ($a <=> $b);
    });
    return $ids;
}

/** 0..1 — share of student pairs that each trip visited in the same relative order as $learned (Kendall agreement). */
function busLearnConfidence(array $orders, array $learned): float
{
    $rank = array_flip($learned); $agree = 0; $pairs = 0;
    foreach ($orders as $o) {
        $o = array_values(array_filter($o, function ($id) use ($rank) { return isset($rank[$id]); }));
        for ($i = 0; $i < count($o); $i++) for ($j = $i + 1; $j < count($o); $j++) {
            $pairs++; if ($rank[$o[$i]] < $rank[$o[$j]]) $agree++;
        }
    }
    return $pairs ? round($agree / $pairs, 2) : 0.0;
}

/**
 * Direction of a trip from its start time: closer to the shift's pickup time → 'pickup', to its drop time → 'drop'.
 * The two run in opposite order, so they are learned separately. No times set → 'any'.
 */
function busTripKind(PDO $pdo, int $busId, int $schoolId, int $shift, string $at): string
{
    $sh = null;
    foreach (busShiftList($pdo, $busId, $schoolId) as $s) if ($s['no'] === $shift) $sh = $s;
    if (!$sh || (!$sh['pickup'] && !$sh['drop'])) return 'any';
    $m = function ($hm) { $p = explode(':', $hm); return (int)$p[0] * 60 + (int)($p[1] ?? 0); };
    $now = $m(substr($at, 11, 5));
    $dp = $sh['pickup'] ? abs($now - $m($sh['pickup'])) : PHP_INT_MAX;
    $dd = $sh['drop']   ? abs($now - $m($sh['drop']))   : PHP_INT_MAX;
    return $dp <= $dd ? 'pickup' : 'drop';
}

/** Called when a trip ends: add its real visit order (and road path) to the bus+shift+direction profile. */
function busLearnFromTrip(PDO $pdo, array $trip, array $path): void
{
    $kind = !empty($trip['kind']) ? (string)$trip['kind'] : busTripKind($pdo, (int)$trip['bus_id'], (int)$trip['school_id'], (int)$trip['shift_no'], (string)$trip['started_at']);
    $q = $pdo->prepare("SELECT student_id FROM bus_trip_stops WHERE trip_id=? AND status='done' AND marked_at IS NOT NULL ORDER BY marked_at, seq, student_id");
    $q->execute([(int)$trip['id']]);
    $order = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    if (count($order) < 2) return;                          // nothing to learn from

    $p = $pdo->prepare("SELECT trips_json, path_json FROM bus_route_learn WHERE bus_id=? AND shift_no=? AND kind=?");
    $p->execute([(int)$trip['bus_id'], (int)$trip['shift_no'], $kind]);
    $row   = $p->fetch() ?: [];
    $trips = json_decode($row['trips_json'] ?? '[]', true) ?: [];
    $trips = array_values(array_filter($trips, function ($t) use ($trip) { return (int)$t['t'] !== (int)$trip['id']; }));
    $trips[] = ['t' => (int)$trip['id'], 'd' => substr((string)$trip['started_at'], 0, 10), 'o' => $order];
    $trips = array_slice($trips, -BUS_LEARN_KEEP_TRIPS);

    $orders  = array_column($trips, 'o');
    $learned = busLearnConsensus($orders);
    $conf    = busLearnConfidence($orders, $learned);

    // Keep the road path of the trip that covered most students (the newest one on a tie).
    $bestN = max(array_map('count', $orders));
    $usePath = count($order) >= $bestN && count($path) >= 10;
    $pathJson = $usePath ? json_encode($path) : ($row['path_json'] ?? null);

    $pdo->prepare("INSERT INTO bus_route_learn (bus_id, shift_no, kind, school_id, trips_json, learned_order, confidence, path_json, updated_at)
                   VALUES (?,?,?,?,?,?,?,?,NOW())
                   ON DUPLICATE KEY UPDATE trips_json=VALUES(trips_json), learned_order=VALUES(learned_order),
                       confidence=VALUES(confidence), path_json=VALUES(path_json), updated_at=NOW()")
        ->execute([(int)$trip['bus_id'], (int)$trip['shift_no'], $kind, (int)$trip['school_id'], json_encode($trips),
                   json_encode($learned), $conf, $pathJson]);
}

/** Learned profile for the driver page / admin map. 'active' = strong enough to be the default order. */
function busLearnedProfile(PDO $pdo, int $busId, int $shift, string $kind): ?array
{
    try {
        $p = $pdo->prepare("SELECT trips_json, learned_order, confidence, path_json, updated_at FROM bus_route_learn WHERE bus_id=? AND shift_no=? AND kind=?");
        $p->execute([$busId, $shift, $kind]);
        $r = $p->fetch();
    } catch (\Throwable $e) { return null; }   // table not created yet
    if (!$r) return null;
    $trips = json_decode($r['trips_json'] ?: '[]', true) ?: [];
    $conf  = (float)$r['confidence'];
    return [
        'kind'       => $kind,
        'active'     => count($trips) >= BUS_LEARN_MIN_TRIPS && $conf >= BUS_LEARN_MIN_CONF,
        'trips'      => count($trips),
        'need'       => BUS_LEARN_MIN_TRIPS,
        'confidence' => $conf,
        'order'      => array_map('intval', json_decode($r['learned_order'] ?: '[]', true) ?: []),
        'path'       => json_decode($r['path_json'] ?: '[]', true) ?: [],
        'updated_at' => $r['updated_at'],
    ];
}
