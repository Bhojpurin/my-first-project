<?php
// includes/bus_db.php — every bus-module database connection runs in the SCHOOL's time zone.
// Shift times (07:00 pickup …), trip start times, "today" for absence notes and every time shown to admins/parents
// come from MySQL NOW(). Many servers run MySQL in UTC; without this, a 07:00 trip would be stored as 01:30.
// Only this connection's session is changed (SET time_zone); the panel's other modules are not affected.
// Override with define('BUS_DB_TIMEZONE', '+04:00') in config/constants.php for schools outside India.
// It also makes sure the bus tables exist and are up to date (includes/bus_schema.php).

function busDb(PDO $pdo): PDO
{
    static $done = [];
    $id = spl_object_id($pdo);
    if (!isset($done[$id])) {
        $done[$id] = true;
        $tz = defined('BUS_DB_TIMEZONE') ? (string)BUS_DB_TIMEZONE : '+05:30';
        if (preg_match('/^[+-](0\d|1[0-4]):[0-5]\d$/', $tz)) {
            // If MySQL already runs in the school's zone (set default_time_zone in my.cnf), skip the extra query.
            require_once __DIR__ . '/bus_cache.php';
            $same = busCacheGet('dbtz:' . $tz, $hit);
            if (!$hit) {
                try {
                    $off = (int)$pdo->query("SELECT TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), NOW())")->fetchColumn();
                    [$h, $m] = explode(':', substr($tz, 1));
                    $same = $off === ($tz[0] === '-' ? -1 : 1) * ((int)$h * 60 + (int)$m);
                    busCacheSet('dbtz:' . $tz, $same, 3600);
                } catch (\Throwable $e) { $same = false; }
            }
            if (!$same) {
                try { $pdo->exec("SET time_zone = '$tz'"); } catch (\Throwable $e) { error_log('bus_db tz: ' . $e->getMessage()); }
            }
        }
        // Tables are created / upgraded automatically on the first request after a deploy
        require_once __DIR__ . '/bus_schema.php';
        busEnsureSchema($pdo);
    }
    return $pdo;
}
