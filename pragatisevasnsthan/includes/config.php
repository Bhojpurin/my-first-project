<?php
/**
 * config.php - loads .env, constants, timezone, error handling.
 * Included first by bootstrap.php. Nothing here touches the database.
 */
define('ROOT_PATH', dirname(__DIR__));

/** Read a value from .env (cached). env('DB_NAME', 'default') */
function env(string $key, $default = null)
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = ROOT_PATH . '/.env';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                [$k, $v] = explode('=', $line, 2);
                $v = trim($v);
                if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
                    $v = substr($v, 1, -1);
                }
                $vars[trim($k)] = $v;
            }
        }
    }
    return $vars[$key] ?? $default;
}

define('APP_DEBUG', env('APP_DEBUG', '0') === '1');
define('APP_URL', rtrim((string) env('APP_URL', ''), '/'));
define('SESSION_IDLE', max(5, (int) env('SESSION_IDLE_MIN', 30)) * 60);

date_default_timezone_set('Asia/Kolkata');
ini_set('default_charset', 'UTF-8');

// --- Errors: show on local, log only on live ---
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT_PATH . '/storage/logs/php-error.log');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) {
    error_log('[' . date('c') . '] ' . $e);
    http_response_code(500);
    if (APP_DEBUG) {
        echo '<pre style="padding:20px;white-space:pre-wrap">' . htmlspecialchars((string) $e) . '</pre>';
    } else {
        echo 'Kuch galat ho gaya. Kripya thodi der baad try karein.';
    }
    exit;
});
