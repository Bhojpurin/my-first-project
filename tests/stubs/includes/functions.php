<?php // TEST STUB
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
function csrfToken(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function verifyCsrf($t): bool { return is_string($t) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }
