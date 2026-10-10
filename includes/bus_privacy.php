<?php
// includes/bus_privacy.php — data minimisation for the bus module (children's home locations are personal data).
//
//  • A student who is no longer active (TC / admission ended / deleted / moved school): home location, landmark
//    note, push subscriptions and absence notes are deleted at the next cleanup run.
//  • An active student who no longer uses any bus: after BUS_PRIVACY_NO_BUS_DAYS (grace period, in case they
//    were removed by mistake) the home location is deleted.
//  • Old operational data is purged after its retention period (below).
//  • A parent can erase their own bus data at any time (student portal → "Meri location hatayein").
// Run by tools/bus_watchdog_cron.php every 15 minutes; also by hand: php tools/bus_privacy_cleanup.php

const BUS_PRIVACY_NO_BUS_DAYS = 30;
const BUS_KEEP_TRIP_DAYS      = 365;   // trips + per-student pickup marks (attendance-like record, no locations)
const BUS_KEEP_ALERT_DAYS     = 180;
const BUS_KEEP_ABSENCE_DAYS   = 30;    // past "not today" notes

/** @return array<string,int> rows removed per kind */
function busPrivacyCleanup(PDO $pdo): array
{
    $n = [];
    $run = function (string $k, string $sql, array $p = []) use ($pdo, &$n) {
        try { $st = $pdo->prepare($sql); $st->execute($p); $n[$k] = ($n[$k] ?? 0) + $st->rowCount(); }
        catch (\Throwable $e) { error_log("bus_privacy $k: " . $e->getMessage()); }
    };
    // Students that no longer exist / are not active / belong to another school now
    foreach (['student_home_locations' => 'home', 'push_subscriptions' => 'push', 'bus_absences' => 'absence'] as $t => $k) {
        $run($k . '_inactive', "DELETE t FROM $t t LEFT JOIN students s ON s.id=t.student_id AND s.school_id=t.school_id
                                WHERE s.id IS NULL OR s.status <> 'active'");
    }

    // Active students without a bus: start the grace clock, stop it when they get a bus again, delete after it ran out
    $hasBus = "EXISTS (SELECT 1 FROM student_van_assignments sva JOIN bus_route_assignments bra
                ON bra.route_id=sva.van_route_id AND bra.school_id=sva.school_id AND bra.status='active'
                WHERE sva.student_id=h.student_id AND sva.school_id=h.school_id)";
    $run('home_nobus_mark',  "UPDATE student_home_locations h SET bus_lost_at=NOW() WHERE bus_lost_at IS NULL AND NOT $hasBus");
    $run('home_nobus_clear', "UPDATE student_home_locations h SET bus_lost_at=NULL WHERE bus_lost_at IS NOT NULL AND $hasBus");
    $run('home_nobus_delete', "DELETE FROM student_home_locations WHERE bus_lost_at IS NOT NULL AND bus_lost_at < NOW() - INTERVAL " . (int)BUS_PRIVACY_NO_BUS_DAYS . " DAY");

    // Retention
    $run('absence_old', "DELETE FROM bus_absences WHERE on_date < CURDATE() - INTERVAL " . (int)BUS_KEEP_ABSENCE_DAYS . " DAY");
    $run('trip_stops_old', "DELETE ts FROM bus_trip_stops ts JOIN bus_trips t ON t.id=ts.trip_id WHERE t.started_at < NOW() - INTERVAL " . (int)BUS_KEEP_TRIP_DAYS . " DAY");
    $run('trips_old', "DELETE FROM bus_trips WHERE started_at < NOW() - INTERVAL " . (int)BUS_KEEP_TRIP_DAYS . " DAY");
    $run('halts_old', "DELETE FROM bus_halt_reports WHERE created_at < NOW() - INTERVAL " . (int)BUS_KEEP_TRIP_DAYS . " DAY");
    $run('alerts_old', "DELETE FROM bus_alerts WHERE created_at < NOW() - INTERVAL " . (int)BUS_KEEP_ALERT_DAYS . " DAY");
    $run('watchdog_old', "DELETE FROM bus_watchdog_alerts WHERE opened_at < NOW() - INTERVAL " . (int)BUS_KEEP_ALERT_DAYS . " DAY");
    $run('pair_codes_old', "DELETE FROM bus_pair_codes WHERE (used_at IS NOT NULL AND used_at < NOW() - INTERVAL 7 DAY) OR expires_at < NOW() - INTERVAL 7 DAY");
    $run('devices_old', "DELETE FROM bus_driver_devices WHERE revoked_at IS NOT NULL AND revoked_at < NOW() - INTERVAL 90 DAY");
    $run('rate_old', "DELETE FROM bus_rate_limits WHERE win < ?", [time() - 86400]);
    return array_filter($n);
}

/** Parent's "delete my bus data": home, note, alerts subscription, absence notes of THIS student only. */
function busEraseStudentData(PDO $pdo, int $schoolId, int $studentId): void
{
    foreach (['student_home_locations', 'push_subscriptions', 'bus_absences'] as $t) {
        try { $pdo->prepare("DELETE FROM $t WHERE student_id=? AND school_id=?")->execute([$studentId, $schoolId]); }
        catch (\Throwable $e) { error_log('bus_privacy erase: ' . $e->getMessage()); }
    }
}
