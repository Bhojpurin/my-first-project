<?php
// includes/bus_proximity.php — detects "bus just entered a student's alert radius"
// and fires a background Web Push notification. Called from api/gps_update.php
// right after a new GPS point is stored. Never throws to its caller.
//
// Safeguards against false / repeated alerts:
//  - ignores GPS fixes that are too inaccurate
//  - only alerts during the student's own shift window (and on the route's running days)
//  - hysteresis: after an alert the bus must move clearly OUTSIDE the radius before it can alert again
//    (a bus hovering around the 500 m line no longer sends a stream of notifications)
//  - one failing student/device never blocks the others

const BUS_ALERT_TZ            = 'Asia/Kolkata'; // school timezone, independent of the server's default
const BUS_ALERT_MAX_ACC_M     = 100;            // ignore bus fixes less accurate than this (metres)
const BUS_ALERT_REARM_FACTOR  = 1.25;           // must leave radius x1.25 before the next alert
const BUS_ALERT_REQUIRE_TRIP  = false;          // true = "Bus nearby" alerts only while the driver has an open trip (api/bus_trip.php)
const BUS_ALERT_BEFORE_MIN    = 90;             // alert window: this many minutes before the shift's pickup/drop time...
const BUS_ALERT_AFTER_MIN     = 150;            // ...and this many minutes after it

function haversineMetersPhp(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $R = 6371000;
    $rad = M_PI / 180;
    $phi1 = $lat1 * $rad;
    $phi2 = $lat2 * $rad;
    $dphi = ($lat2 - $lat1) * $rad;
    $dlambda = ($lng2 - $lng1) * $rad;
    $a = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlambda / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Is "now" inside the window in which this student's bus normally runs?
 * Uses the student's own shift (1-5) of the route assignment. If no times are set, or the
 * "days" value isn't understood, that part is not restricted (alerts keep working).
 */
function busAlertInWindow(array $row): bool
{
    $now = new DateTime('now', new DateTimeZone(BUS_ALERT_TZ));

    // Running days (e.g. "Mon,Tue,Wed,Thu,Fri,Sat")
    $known = ['mon','tue','wed','thu','fri','sat','sun'];
    $days  = array_map(function ($d) { return strtolower(substr(trim($d), 0, 3)); }, explode(',', (string)($row['days'] ?? '')));
    $days  = array_values(array_intersect($days, $known));
    if ($days && !in_array(strtolower($now->format('D')), $days, true)) return false;

    // This student's shift
    $shiftCount = max(1, (int)($row['shift_count'] ?? 1));
    $shift = (int)($row['shift_no'] ?? 1);
    if ($shift < 1 || $shift > $shiftCount) $shift = 1;
    $sfx = $shift === 1 ? '' : (string)$shift;

    $times = array_filter([$row['pickup_time' . $sfx] ?? null, $row['drop_time' . $sfx] ?? null]);
    if (!$times) return true;   // no times configured → no time restriction

    $nowMin = (int)$now->format('H') * 60 + (int)$now->format('i');
    foreach ($times as $t) {
        $p  = explode(':', (string)$t);
        $tm = (int)$p[0] * 60 + (int)($p[1] ?? 0);
        $d  = $nowMin - $tm;
        if ($d >= -BUS_ALERT_BEFORE_MIN && $d <= BUS_ALERT_AFTER_MIN) return true;
    }
    return false;
}

/**
 * Checks every student riding this bus who has background alerts enabled.
 * Fires exactly one push per "entry" into the radius (re-arms once the bus
 * has clearly left again), so a lingering/parked bus doesn't spam notifications.
 */
function checkBusProximityPush(PDO $pdo, int $busId, int $schoolId, float $busLat, float $busLng,
                               ?float $speedKmh = null, ?float $accuracyM = null): void
{
    if ($accuracyM !== null && $accuracyM > BUS_ALERT_MAX_ACC_M) return;   // rough fix: could be a false "entry"

    if (BUS_ALERT_REQUIRE_TRIP) {
        try {
            $ot = $pdo->prepare("SELECT 1 FROM bus_trips WHERE bus_id=? AND ended_at IS NULL LIMIT 1");
            $ot->execute([$busId]);
            if (!$ot->fetchColumn()) return;          // no running trip → bus is parked/at depot, stay quiet
        } catch (\Throwable $e) { /* bus_trips not created yet → do not block alerts */ }
    }

    $stmt = $pdo->prepare("
        SELECT hl.student_id, hl.lat, hl.lng, hl.alert_radius, hl.in_radius_since,
               bra.*, bss.shift_no
        FROM bus_route_assignments bra
        JOIN student_van_assignments sva ON sva.van_route_id = bra.route_id AND sva.school_id = bra.school_id
        JOIN student_home_locations hl  ON hl.student_id = sva.student_id AND hl.school_id = sva.school_id
        LEFT JOIN bus_student_shifts bss ON bss.student_id = sva.student_id AND bss.route_id = sva.van_route_id AND bss.school_id = sva.school_id
        WHERE bra.bus_id = ? AND bra.school_id = ? AND bra.status = 'active' AND hl.push_enabled = 1
    ");
    $stmt->execute([$busId, $schoolId]);
    $students = $stmt->fetchAll();
    if (!$students) return;

    $bnq = $pdo->prepare("SELECT bus_name FROM school_buses WHERE id=? AND school_id=?");
    $bnq->execute([$busId, $schoolId]);
    $busName = $bnq->fetchColumn() ?: 'Your bus';

    require_once __DIR__ . '/bus_notify.php';

    foreach ($students as $s) {
        try {
            $dist   = haversineMetersPhp($busLat, $busLng, (float)$s['lat'], (float)$s['lng']);
            $radius = (int)$s['alert_radius'];
            if ($radius < 50) $radius = 500;                       // missing/zero radius → default
            $inRadius    = $dist <= $radius;
            $clearlyOut  = $dist > $radius * BUS_ALERT_REARM_FACTOR;
            $wasInRadius = $s['in_radius_since'] !== null;

            if ($inRadius && !$wasInRadius) {
                // Entered the radius. Outside this student's run window we only remember it
                // (silently) so a bus parked nearby doesn't alert later; it alerts on the next real approach.
                if (busAlertInWindow($s)) {
                    $body = $busName . ' is about ' . (int)(round($dist / 10) * 10) . ' m from home';
                    if ($speedKmh !== null && $speedKmh >= 8) {
                        $eta = max(1, (int)round(($dist / 1000) / $speedKmh * 60));
                        $body .= ' (about ' . $eta . ' min away)';
                    }
                    $body .= '.';

                    // Push + the panel's own Messages (includes/bus_notify.php), this school only
                    busNotifyStudents($pdo, $schoolId, [(int)$s['student_id']], 'Bus Nearby! 🚌', $body, 'Bus nazdeek');
                }

                $pdo->prepare("UPDATE student_home_locations SET in_radius_since=NOW() WHERE student_id=? AND school_id=?")
                    ->execute([$s['student_id'], $schoolId]);
            } elseif ($wasInRadius && $clearlyOut) {
                // Clearly left the radius — re-arm so the next approach alerts again.
                $pdo->prepare("UPDATE student_home_locations SET in_radius_since=NULL WHERE student_id=? AND school_id=?")
                    ->execute([$s['student_id'], $schoolId]);
            }
        } catch (\Throwable $e) {
            error_log('bus_proximity student ' . ($s['student_id'] ?? '?') . ': ' . $e->getMessage());
        }
    }
}