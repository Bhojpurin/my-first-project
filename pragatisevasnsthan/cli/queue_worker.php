<?php
/**
 * cli/queue_worker.php - send queued e-mails / retry failed ones.
 * Live server cron (every minute):   * * * * * php /path/to/pragatisevasnsthan/cli/queue_worker.php
 * XAMPP test:                        C:\xampp\php\php.exe cli\queue_worker.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/secrets.php';
require_once __DIR__ . '/../includes/jobs.php';
echo 'Jobs done: ' . jobs_run(50) . PHP_EOL;
