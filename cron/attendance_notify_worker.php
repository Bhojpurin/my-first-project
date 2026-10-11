<?php
// cron/attendance_notify_worker.php — sends queued attendance messages (check-in / check-out / 6-day absence)
// and retries failed ones (up to 5 tries, waiting 1, 4, 9, 16 minutes).
//
// Messages are also sent right after each request, so this is the safety net for retries and
// for servers without PHP-FPM. Run every minute:
//   * * * * * php /path/to/site/cron/attendance_notify_worker.php >/dev/null 2>&1
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/attendance_core.php';

$pdo   = Database::connect();
$total = 0;
$until = time() + 50;   // stay inside one cron minute
do {
    $n = AttCore::processQueue($pdo, 100);
    $total += $n;
} while ($n > 0 && time() < $until);

if ($total) echo date('Y-m-d H:i:s') . " sent $total attendance messages\n";
