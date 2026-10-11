<?php
// Integration tests for the attendance fixes: the real API files run against a real MySQL/MariaDB
// (database `sz` is DROPPED and recreated), with the framework includes replaced by tests/attendance/stubs.
// Run: tests/attendance/run_tests.sh   (or ATT_TEST_SOCK=/path/to/mysql.sock php tests/attendance/test.php)
$HERE = __DIR__;
$REPO = dirname(__DIR__, 2);
$SOCK = getenv('ATT_TEST_SOCK') ?: '/tmp/att/my.sock';
putenv("ATT_TEST_SOCK=$SOCK");
$SITE = sys_get_temp_dir() . '/att_test_site';
foreach (['api', 'includes', 'cron'] as $d) @mkdir("$SITE/$d", 0777, true);
foreach (glob("$HERE/stubs/includes/*.php") as $f) copy($f, "$SITE/includes/" . basename($f));
copy("$HERE/stubs/run.php", "$SITE/run.php");
foreach (['api/attendance_scan.php', 'api/attendance_actions.php', 'includes/attendance_core.php', 'cron/attendance_notify_worker.php'] as $f) copy("$REPO/$f", "$SITE/$f");
$my = 'mariadb --socket=' . escapeshellarg($SOCK);
shell_exec("$my < $HERE/base_schema.sql && $my sz < $REPO/sql/attendance_fixes_v3.sql");
$pdo = new PDO("mysql:unix_socket=$SOCK;dbname=sz", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$q = fn($sql, $a = []) => (function () use ($pdo, $sql, $a) { $s = $pdo->prepare($sql); $s->execute($a); return $s; })();

const ADMIN   = ['logged_in' => 1, 'role' => 'school_admin', 'school_id' => 1, 'user_id' => 900, 'name' => 'Admin'];
const TEACHER = ['logged_in' => 1, 'role' => 'teacher', 'school_id' => 1, 'user_id' => 901, 'name' => 'Teacher'];

function req(string $file, array $post, array $sess = TEACHER, array $env = []): array {
    global $SITE;
    $e = 'SESS=' . escapeshellarg(json_encode($sess));
    foreach ($env as $k => $v) $e .= " $k=" . escapeshellarg($v);
    $out = shell_exec("$e php $SITE/run.php $file " . escapeshellarg(json_encode($post)) . ' 2>&1');
    $d = json_decode((string)$out, true);
    if (!is_array($d)) { echo "  !! non-JSON output from $file: $out\n"; return ['success' => false, 'message' => $out]; }
    return $d;
}
function scan(string $code, array $extra = [], array $sess = TEACHER, array $env = []): array {
    return req('api/attendance_scan.php', $extra + ['action' => 'scan', 'code' => $code, 'client_uid' => bin2hex(random_bytes(8)),
        'age_ms' => '40', 'scanned_at' => date('Y-m-d H:i:s'), 'source' => 'camera'], $sess, $env);
}
$pass = 0; $fail = 0;
function ok(bool $c, string $what, $dbg = null) { global $pass, $fail; if ($c) { $pass++; echo "  ✔ $what\n"; } else { $fail++; echo "  ✘ $what " . ($dbg !== null ? json_encode($dbg, JSON_UNESCAPED_UNICODE) : '') . "\n"; } }
$today = date('Y-m-d');
$status = fn(int $sid, string $d = null) => $q("SELECT status FROM attendance WHERE student_id=? AND date=?", [$sid, $d ?? $today])->fetchColumn();

echo "1. Device clock is not trusted\n";
$r = scan('A100', ['scanned_at' => date('Y-m-d H:i:s', time() - 300), 'age_ms' => '50']);
$row = $q("SELECT scanned_at, is_offline_sync, client_age_sec FROM attendance_scan_log WHERE id=?", [$r['scan_id'] ?? 0])->fetch(PDO::FETCH_ASSOC);
ok($r['success'] && $r['event'] === 'in', 'scan accepted', $r);
ok($row && abs(strtotime($row['scanned_at']) - time()) <= 3 && (int)$row['is_offline_sync'] === 0, 'phone clock 5 min behind → still recorded at server time, not offline', $row);
$r = scan('A102', ['age_ms' => (string)(3600 * 1000)]);
$row = $q("SELECT scanned_at, is_offline_sync FROM attendance_scan_log WHERE id=?", [$r['scan_id'] ?? 0])->fetch(PDO::FETCH_ASSOC);
ok($row && abs(strtotime($row['scanned_at']) - (time() - 3600)) <= 3 && (int)$row['is_offline_sync'] === 1, 'real offline scan 1h old → dated 1h back, flagged offline', $row);
$r = scan('A103', ['age_ms' => (string)(40 * 3600 * 1000)]);
ok(!$r['success'] && $status(4) === false, '40h-old offline scan refused, nothing written', $r);

echo "2. Same code for a student and a staff member\n";
$r = scan('101');
ok(!$r['success'] && ($r['event'] ?? '') === 'ambiguous' && $status(2) === false
   && !$q("SELECT 1 FROM staff_attendance WHERE teacher_id=7")->fetchColumn(), 'plain "101" refused as ambiguous, nobody marked', $r);
$r = scan('S:101', ['source' => 'search']);
ok($r['success'] && $r['type'] === 'student' && $r['name'] === 'Priya Singh', 'S:101 → student Priya', $r);
$r = scan('T:101', ['source' => 'search']);
ok($r['success'] && $r['type'] === 'staff' && $r['name'] === 'Sunita Sharma', 'T:101 → staff Sunita', $r);
$r = req('api/attendance_scan.php', ['action' => 'search', 'q' => 'Priya']);
ok(($r['results'][0]['scan_code'] ?? '') === 'S:101', 'name search returns typed code S:101', $r);

echo "3. Multi-column QR fallback never guesses\n";
$q("DELETE FROM attendance WHERE student_id=1"); $q("UPDATE attendance_scan_log SET voided_at=NOW() WHERE entity_type='student' AND entity_id=1");
$r = scan('Name Arun A100 Class 5');
ok($r['success'] && ($r['person_id'] ?? 0) === 1, 'QR "Name Arun A100 Class 5" → only one person matches → Arun', $r);
$r = scan('A100 A104');
ok(!$r['success'] && ($r['event'] ?? '') === 'ambiguous', 'QR containing two people\'s codes refused', $r);
$r = scan('A100', [], ['school_id' => 2, 'user_id' => 1] + TEACHER);
ok($r['success'] && ($r['person_id'] ?? 0) === 9, 'same code in another school matches only that school\'s student', $r);

echo "4. Signed QR cards\n";
$r = req('api/attendance_scan.php', ['action' => 'card_payload', 'type' => 'student', 'ids' => '5'], ADMIN);
$payload = $r['cards'][0]['payload'] ?? '';
ok(str_starts_with($payload, 'SZ1|S|1|A104|'), 'admin gets signed payload', $r);
ok(!req('api/attendance_scan.php', ['action' => 'card_payload', 'type' => 'student', 'ids' => '5'])['success'], 'teacher cannot get payloads');
$fake = preg_replace('/A104/', 'A103', $payload);
$r = scan($fake);
ok(!$r['success'] && $status(4) === false, 'forged card (code changed, old signature) refused', $r);
$r = scan($payload);
ok($r['success'] && ($r['person_id'] ?? 0) === 5, 'genuine signed card accepted', $r);
$r = req('api/attendance_scan.php', ['action' => 'revoke_card', 'code' => 'A104'], ADMIN);
$newPayload = $r['payload'] ?? '';
ok($r['success'] && str_starts_with($newPayload, 'SZ1|S|2|A104|'), 'lost card blocked, v2 issued', $r);
$r = scan($payload);
ok(!$r['success'] && str_contains($r['message'], 'blocked'), 'old (v1) card now refused', $r);
$r = req('api/attendance_scan.php', ['action' => 'save_settings', 'require_signed' => '1'], ADMIN);
ok($r['success'], 'require signed cards switched on', $r);
$r = scan('A103');
ok(!$r['success'] && $status(4) === false, 'unsigned old card refused at camera when secure mode on', $r);
$r = scan('A103', ['source' => 'manual']);
ok($r['success'], 'teacher manual entry still works in secure mode', $r);
req('api/attendance_scan.php', ['action' => 'save_settings', 'require_signed' => '0'], ADMIN);

echo "5. Exact undo + undo does not overwrite later changes\n";
$q("DELETE FROM attendance WHERE student_id=4"); $q("UPDATE attendance_scan_log SET voided_at=NOW() WHERE entity_type='student' AND entity_id=4");
$r = req('api/attendance_actions.php', ['date' => $today, 'att_4' => 'leave']);
ok($r['success'] && $status(4) === 'leave', 'Neha marked on leave manually', $r);
$r = scan('A103', ['source' => 'manual']);
ok($r['success'] && $status(4) === 'present', 'Neha scans in → present', $r);
$u = req('api/attendance_scan.php', ['action' => 'undo', 'scan_id' => $r['scan_id']]);
ok($u['success'] && $status(4) === 'leave', 'undo restores "leave" (old code deleted the row)', [$u, $status(4)]);
$q("DELETE FROM attendance WHERE student_id=5"); $q("UPDATE attendance_scan_log SET voided_at=NOW() WHERE entity_type='student' AND entity_id=5");
$r = scan($newPayload);
$attId = (int)$q("SELECT id FROM attendance WHERE student_id=5 AND date=?", [$today])->fetchColumn();
req('api/attendance_actions.php', ['action' => 'edit_single', 'attendance_id' => $attId, 'status' => 'absent', 'reason' => 'left after assembly']);
$u = req('api/attendance_scan.php', ['action' => 'undo', 'scan_id' => $r['scan_id']]);
ok(!$u['success'] && $status(5) === 'absent', 'undo refused after a manual edit; manual value kept', [$u, $status(5)]);

echo "6. Stale register cannot wipe a gate scan\n";
ok($status(1) === 'present', 'Arun is present from scan');
$r = req('api/attendance_actions.php', ['date' => $today, 'att_1' => 'absent', 'att_3' => 'present', 'class_id' => '999']);
ok($r['success'] && $status(1) === 'present' && ($r['kept_scan'] ?? 0) === 1, 'bulk "absent" did not overwrite the scan; teacher told', $r);
ok((int)$q("SELECT class_id FROM attendance WHERE student_id=3 AND date=?", [$today])->fetchColumn() === 10, 'class_id taken from student record, not the form (999 ignored)');
$r = req('api/attendance_actions.php', ['date' => $today, 'att_1' => 'absent', 'override_scan' => '1']);
ok($status(1) === 'absent', 'override_scan=1 does change it on purpose', $r);

echo "7. Edit lock + date validation\n";
$old = date('Y-m-d', strtotime('-10 days'));
ok(!req('api/attendance_actions.php', ['date' => $old, 'att_3' => 'present'])['success'], 'teacher cannot change 10-day-old attendance (default lock 7)');
ok(req('api/attendance_actions.php', ['date' => $old, 'att_3' => 'present'], ADMIN)['success'], 'admin can');
ok(!req('api/attendance_actions.php', ['date' => date('Y-m-d', strtotime('+1 day')), 'att_3' => 'present'], ADMIN)['success'], 'nobody can mark a future date');
ok(!req('api/attendance_actions.php', ['date' => '2026-02-31', 'att_3' => 'present'], ADMIN)['success'], 'impossible date 2026-02-31 refused');

echo "8. Weekly off (2nd / 4th Saturday)\n";
require_once "$SITE/includes/functions.php";
require_once "$SITE/includes/attendance_core.php";
ok(AttCore::isWeeklyOff('2026-10-10', '7,6:2,6:4') && !AttCore::isWeeklyOff('2026-10-03', '7,6:2,6:4') && AttCore::isWeeklyOff('2026-10-24', '7,6:2,6:4') && AttCore::isWeeklyOff('2026-10-11', '7,6:2,6:4'),
   '10 Oct (2nd Sat) & 24 Oct (4th Sat) & Sundays off, 3 Oct (1st Sat) working');
ok(!req('api/attendance_scan.php', ['action' => 'save_settings', 'weekly_off' => '7,9'], ADMIN)['success'], 'invalid weekly off rejected');
ok(req('api/attendance_scan.php', ['action' => 'save_settings', 'weekly_off' => '7,6:2,6:4'], ADMIN)['success'], 'weekly off saved');

echo "9. 6-day absence alert also from 'mark remaining absent', queued, sent after response, once\n";
// Ravi (3): absent on the 6 working days ending today-ish. Use the real working-day calculator.
$q("DELETE FROM attendance WHERE student_id=3");
$pdoC = Database::connect();
$days = AttCore::schoolDaysBack($pdoC, 1, $today, 6, false);
foreach ($days as $d) $q("INSERT INTO attendance (school_id, student_id, date, status, marked_by) VALUES (1,3,?,'absent',1)", [$d]);
// The alert window ends on the finalize day; today is Sunday (weekly off), so "today" is not counted
// and the 6 previous working days are all absent → alert expected.
$r = req('api/attendance_scan.php', ['action' => 'finalize_absent', 'type' => 'both', 'force' => '1'], ADMIN);
ok($r['success'], 'finalize ran', $r);
$queued = $q("SELECT status FROM attendance_notify_queue WHERE dedupe_key='absent6:3'")->fetchAll(PDO::FETCH_COLUMN);
ok($queued === ['sent'], '6-day alert for Ravi queued and already sent by the after-response flush', $queued);
ok((int)$q("SELECT COUNT(*) FROM sent_log WHERE event_type='student_absent_6days'")->fetchColumn() === 1, 'exactly one message sent');
req('api/attendance_actions.php', ['date' => $today, 'att_3' => 'absent'], ADMIN);
ok((int)$q("SELECT COUNT(*) FROM attendance_notify_queue WHERE dedupe_key='absent6:3'")->fetchColumn() === 1, 'marking again does not send a second alert within 7 days');
ok($status(1) === 'absent' && $status(2) === 'present', 'finalize kept existing records (Priya present stays present)');

echo "10. Parent check-in messages are queued, retried, never block the scan\n";
req('api/attendance_scan.php', ['action' => 'save_settings', 'notify_parents' => '1'], ADMIN);
$q("DELETE FROM attendance WHERE student_id=1"); $q("UPDATE attendance_scan_log SET voided_at=NOW() WHERE entity_type='student' AND entity_id=1");
$t0 = microtime(true);
$r = scan('A100', [], TEACHER, ['FAIL_NOTIFY' => '1']);
ok($r['success'], 'scan succeeds even when the SMS gateway is down', $r);
$job = $q("SELECT * FROM attendance_notify_queue WHERE event_type='student_checkin' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok($job && $job['status'] === 'pending' && (int)$job['attempts'] === 1 && $job['last_error'] === 'gateway down', 'failed send stays queued with the error for retry', $job);
$q("UPDATE attendance_notify_queue SET available_at = NOW() - INTERVAL 1 MINUTE WHERE id=?", [$job['id']]);
$out = shell_exec("php $SITE/cron/attendance_notify_worker.php 2>&1");
ok($q("SELECT status FROM attendance_notify_queue WHERE id=?", [$job['id']])->fetchColumn() === 'sent', 'cron worker retried and sent it', $out);

echo "11. Replay, history, staff check-in/out\n";
$uid = 'replay-test-1';
$a = scan('E200', ['client_uid' => $uid, 'age_ms' => (string)(10 * 60 * 1000)]);
$b = scan('E200', ['client_uid' => $uid, 'age_ms' => (string)(10 * 60 * 1000)]);
ok($a['success'] && !empty($b['replayed']) && (int)$q("SELECT COUNT(*) FROM attendance_scan_log WHERE client_uid=?", [$uid])->fetchColumn() === 1, 'same client_uid twice → one record, replayed');
$c = scan('E200');
ok($c['success'] && $c['event'] === 'out', 'staff check-out 10 minutes later', $c);
$h = $q("SELECT source, COUNT(*) FROM attendance_history WHERE school_id=1 GROUP BY source")->fetchAll(PDO::FETCH_KEY_PAIR);
ok(isset($h['scan'], $h['manual'], $h['finalize'], $h['undo']), 'history has scan / manual / finalize / undo entries', $h);
$e = $q("SELECT old_status, new_status, reason FROM attendance_history WHERE entity_id=5 AND source='manual' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok($e == ['old_status' => 'present', 'new_status' => 'absent', 'reason' => 'left after assembly'], 'single edit recorded old → new + reason', $e);

echo "12. Other actions still work\n";
$s = req('api/attendance_scan.php', ['action' => 'summary']);
ok($s['success'] && $s['students']['total'] === 5, 'summary', $s['students'] ?? $s);
$n = req('api/attendance_scan.php', ['action' => 'not_arrived', 'type' => 'staff']);
ok($n['success'], 'not_arrived', $n);
$g = req('api/attendance_scan.php', ['action' => 'get_settings']);
ok($g['success'] && $g['settings']['weekly_off'] === '7,6:2,6:4' && $g['settings']['edit_lock_days'] === 7, 'get_settings has new rules', $g['settings'] ?? $g);
$rc = req('api/attendance_scan.php', ['action' => 'recent']);
ok($rc['success'] && count($rc['items']) > 0, 'recent');

echo "13. Deadlock detection\n";
$ex = new PDOException('Deadlock found'); $ex->errorInfo = ['40001', 1213, 'Deadlock'];
ok(AttCore::isRetryable($ex) && !AttCore::isRetryable(new RuntimeException('x')), 'deadlock is retried, other errors are not');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
