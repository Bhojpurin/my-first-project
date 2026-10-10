<?php
// tools/bus_privacy_cleanup.php — run the privacy cleanup by hand (it also runs every 15 min from the watchdog cron).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bus_db.php';
require_once __DIR__ . '/../includes/bus_privacy.php';
$r = busPrivacyCleanup(busDb(Database::connect()));
echo date('c'), ' ', $r ? json_encode($r) : 'nothing to delete', "\n";
