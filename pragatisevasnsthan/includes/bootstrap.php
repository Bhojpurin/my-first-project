<?php
/**
 * bootstrap.php - include this ONE file at the top of every page:
 *     require_once __DIR__ . '/includes/bootstrap.php';        (root pages)
 *     require_once __DIR__ . '/../includes/bootstrap.php';     (admin pages)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/secrets.php';

// --- secure session ---
session_name('PSSSESS');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

send_security_headers();

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/auth.php';
