<?php
// tools/bus_watchdog_cron.php — run every minute:   * * * * * php /path/to/tools/bus_watchdog_cron.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bus_watchdog.php';
require_once __DIR__ . '/../includes/bus_trips.php';
$pdo = Database::connect();
$r = runBusWatchdog($pdo, 0);
$closed = busTripAutoClose($pdo);
echo date('c'), " opened={$r['opened']} resolved={$r['resolved']} trips_closed=$closed\n";
// Privacy cleanup (home locations of students who left, retention) every 15 minutes
if ((int)date('i') % 15 === 0 || in_array('--privacy', $argv ?? [], true)) {
    require_once __DIR__ . '/../includes/bus_privacy.php';
    $p = busPrivacyCleanup($pdo);
    if ($p) echo date('c'), ' privacy ', json_encode($p), "\n";
}
