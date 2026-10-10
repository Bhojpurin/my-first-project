<?php
// includes/bus_db.php — every bus-module database connection runs in the SCHOOL's time zone.
// Shift times (07:00 pickup …), trip start times, "today" for absence notes and every time shown to admins/parents
// come from MySQL NOW(). Many servers run MySQL in UTC; without this, a 07:00 trip would be stored as 01:30.
// Only this connection's session is changed (SET time_zone); the panel's other modules are not affected.
// Override with define('BUS_DB_TIMEZONE', '+04:00') in config/constants.php for schools outside India.

function busDb(PDO $pdo): PDO
{
    static $done = [];
    $id = spl_object_id($pdo);
    if (!isset($done[$id])) {
        $done[$id] = true;
        $tz = defined('BUS_DB_TIMEZONE') ? (string)BUS_DB_TIMEZONE : '+05:30';
        if (preg_match('/^[+-](0\d|1[0-4]):[0-5]\d$/', $tz)) {
            try { $pdo->exec("SET time_zone = '$tz'"); } catch (\Throwable $e) { error_log('bus_db tz: ' . $e->getMessage()); }
        }
    }
    return $pdo;
}
