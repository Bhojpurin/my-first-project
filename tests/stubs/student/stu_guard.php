<?php // TEST STUB
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
function stuLoggedIn(): bool { return !empty($_SESSION['stu']); }
function stuInfo(): array { return $_SESSION['stu'] ?? []; }
