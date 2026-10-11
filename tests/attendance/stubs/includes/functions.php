<?php
if (!defined('ROLE_SCHOOL_ADMIN')) { define('ROLE_SCHOOL_ADMIN', 'school_admin'); define('ROLE_TEACHER', 'teacher'); define('BASE_URL', ''); }
class Database { static ?PDO $p = null; static function connect(): PDO {
  return self::$p ??= new PDO('mysql:unix_socket=' . (getenv('ATT_TEST_SOCK') ?: '/tmp/att/my.sock') . ';dbname=sz;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } }
function jsonResponse($ok, $msg, $data = [], $code = 200) { http_response_code($code); echo json_encode(['success' => $ok, 'message' => $msg] + $data, JSON_UNESCAPED_UNICODE); exit; }
function hasPermission($m, $a) { $deny = explode(',', getenv('DENY') ?: ''); return !in_array("$m.$a", $deny, true); }
function verifyCsrf($t) { return $t === 'ok'; }
function csrfToken() { return 'ok'; }
function e($s) { return htmlspecialchars((string)$s); }
