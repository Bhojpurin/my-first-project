<?php
// tools/bus_selfcheck.php — run after every deploy:   php tools/bus_selfcheck.php
// Checks PHP, the panel files the bus module needs, the database tables/columns, settings and the cron.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$ok = 0; $bad = 0; $warn = 0;
function chk(bool $c, string $m, string $fix = '', bool $hard = true): void {
    global $ok, $bad, $warn;
    if ($c) { $ok++; echo "  ✔ $m\n"; return; }
    if ($hard) { $bad++; echo "  ✖ $m" . ($fix ? "  → $fix" : '') . "\n"; } else { $warn++; echo "  ! $m" . ($fix ? "  → $fix" : '') . "\n"; }
}
$R = dirname(__DIR__);
echo "PHP\n";
chk(version_compare(PHP_VERSION, '7.4', '>='), 'PHP ' . PHP_VERSION . ' (7.4+ needed)', 'upgrade PHP');
foreach (['pdo_mysql', 'mbstring', 'json', 'openssl'] as $e) chk(extension_loaded($e), "extension $e", "enable $e in php.ini");

echo "Panel files used by the bus module\n";
foreach (['config/db.php', 'config/constants.php', 'includes/session.php', 'includes/functions.php', 'includes/activity_log.php',
          'includes/push_sender.php', 'includes/school_message_helper.php', 'student/stu_guard.php', 'student/sw.js'] as $f)
    chk(is_file("$R/$f"), $f, 'missing — the bus module expects it here');
foreach (['includes/bus_db.php', 'includes/bus_cache.php', 'includes/bus_schema.php', 'api/bus_trip.php', 'api/driver_tracker.php', 'api/driver_sw.js', 'api/gps_update.php', 'api/bus_actions.php', 'api/bus_location.php',
          'api/student_bus.php', 'includes/bus_security.php', 'includes/bus_trips.php', 'includes/bus_alerts.php', 'includes/bus_halt.php',
          'includes/bus_notify.php', 'includes/bus_message_hook.php', 'includes/bus_privacy.php', 'includes/bus_proximity.php',
          'includes/bus_watchdog.php'] as $f)
    chk(is_file("$R/$f"), $f, 'not uploaded');

require_once "$R/config/db.php";
require_once "$R/config/constants.php";
require_once "$R/includes/bus_db.php";
echo "Settings\n";
chk(defined('BASE_URL'), 'BASE_URL defined');
$base = defined('BASE_URL') ? (string)BASE_URL : '';
chk($base === '' || stripos($base, 'https://') === 0 || $base[0] === '/', "BASE_URL = '$base'", 'use https:// — phones only give GPS on HTTPS', false);
chk(defined('ROLE_SCHOOL_ADMIN') && defined('ROLE_TEACHER'), 'role constants');
chk(defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY, 'VAPID key for push', 'push alerts need it', false);
$router = defined('BUS_ROUTER_URL') ? BUS_ROUTER_URL : 'https://router.project-osrm.org (default public server)';
chk(true, "road routing: $router");

echo "Cache\n";
require_once "$R/includes/bus_cache.php";
$backend = busRedis() ? 'Redis' : (function_exists('apcu_fetch') && ini_get('apc.enabled') ? 'APCu (web workers)' : 'files');
chk(true, "cache backend (this CLI): $backend");
chk(defined('BUS_REDIS_HOST') || true, 'more than one web server? then set BUS_REDIS_HOST (see SCALING.md)', '', false);

echo "Database\n";
try { $pdo = busDb(Database::connect()); chk(true, 'connected'); }
catch (\Throwable $e) { chk(false, 'connect: ' . $e->getMessage()); echo "\n$bad problem(s)\n"; exit(1); }
$tables = ['school_buses', 'bus_gps_locations', 'bus_route_assignments', 'student_home_locations', 'push_subscriptions', 'school_messages',
    'bus_live', 'bus_watchdog_alerts', 'bus_trips', 'bus_trip_stops', 'bus_route_learn', 'bus_rate_limits', 'bus_pair_codes',
    'bus_driver_devices', 'bus_alert_settings', 'bus_alerts', 'bus_alert_state', 'bus_halt_reports', 'bus_absences'];
$have = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) chk(in_array($t, $have, true), "table $t", 'run: php tools/migrate.php');
$cols = ['bus_live.still_since', 'bus_gps_locations.accuracy', 'bus_trip_stops.eta_at', 'bus_trip_stops.eta_notified', 'bus_trips.kind',
         'student_home_locations.note', 'student_home_locations.bus_lost_at', 'bus_alerts.ref_id', 'bus_alerts.broadcast_at',
         'bus_alert_settings.halt_ask_min', 'bus_alert_state.halt_alerted_for', 'bus_route_learn.kind', 'school_messages.is_auto', 'school_messages.auto_event'];
$q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
foreach ($cols as $c) { [$t, $col] = explode('.', $c); $q->execute([$t, $col]); chk((bool)$q->fetchColumn(), "column $c", 'run: php tools/migrate.php'); }
$tz = $pdo->query("SELECT TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), NOW())")->fetchColumn();
chk(abs($tz - 330) <= 1 || defined('BUS_DB_TIMEZONE'), 'bus module database clock: UTC' . ($tz >= 0 ? '+' : '') . round($tz / 60, 2) . ' h', 'expected +5.5 (IST)', false);

echo "Cron (watchdog / auto-close / privacy)\n";
try {
    $last = $pdo->query("SELECT MAX(updated_at) FROM student_home_locations WHERE bus_lost_at IS NOT NULL")->fetchColumn();
    $open = (int)$pdo->query("SELECT COUNT(*) FROM bus_trips WHERE ended_at IS NULL AND started_at < NOW() - INTERVAL 15 HOUR")->fetchColumn();
    chk($open === 0, 'no trips older than 15 h left open', 'cron not running? add: * * * * * php ' . $R . '/tools/bus_watchdog_cron.php', false);
} catch (\Throwable $e) {}

echo "\n" . ($bad ? "$bad problem(s), " : '') . ($warn ? "$warn warning(s), " : '') . "$ok OK\n";
exit($bad ? 1 : 0);
