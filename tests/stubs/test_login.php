<?php // TEST STUB (sandbox only, never deployed): log in as admin/teacher/student of a school
// Hard lock: works ONLY under PHP's built-in test server started by tests/integration.php — never on Apache/Nginx/FPM.
// (The load test runs it under PHP-FPM with BUS_LOADTEST=1 set in its own test pool.)
if ((PHP_SAPI !== 'cli-server' && getenv('BUS_LOADTEST') !== '1') || getenv('BUS_TEST_PUSHLOG') === false) { http_response_code(404); exit; }
session_start();
require_once __DIR__ . '/includes/functions.php';
$_SESSION = [];
if (($_GET['as'] ?? '') === 'student') { $_SESSION['stu'] = ['id' => (int)$_GET['id'], 'school_id' => (int)$_GET['school']]; }
else { $_SESSION += ['logged_in' => true, 'role' => $_GET['as'] === 'teacher' ? 'teacher' : 'school_admin', 'school_id' => (int)$_GET['school'], 'user_id' => 1, 'name' => 'T']; }
echo json_encode(['csrf' => csrfToken()]);
