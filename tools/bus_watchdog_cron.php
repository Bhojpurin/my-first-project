<?php
// tools/bus_watchdog_cron.php — run every minute:   * * * * * php /path/to/tools/bus_watchdog_cron.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bus_watchdog.php';
$r = runBusWatchdog(Database::connect(), 0);
echo date('c'), " opened={$r['opened']} resolved={$r['resolved']}\n";
