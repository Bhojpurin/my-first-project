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
copy("$HERE/stubs/run_get.php", "$SITE/run_get.php");
@mkdir("$SITE/assets/uploads/photos", 0777, true);
file_put_contents("$SITE/assets/uploads/photos/arun.jpg", "\xFF\xD8\xFF fake-jpeg-bytes");
foreach (['api/attendance_scan.php', 'api/attendance_actions.php', 'api/attendance_photo.php', 'api/attendance_reports.php', 'includes/attendance_core.php', 'cron/attendance_notify_worker.php'] as $f) copy("$REPO/$f", "$SITE/$f");
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

echo "14. Gate device keeps scanning without a login, but can only scan\n";
$NOSESS = ['logged_in' => 0];
$r = req('api/attendance_scan.php', ['action' => 'device_register', 'name' => 'Main Gate']);
ok(!$r['success'], 'teacher cannot register a gate device', $r);
$r = req('api/attendance_scan.php', ['action' => 'device_register', 'name' => 'Main Gate'], ADMIN);
$tok = $r['token'] ?? '';
ok($r['success'] && preg_match('/^gd_\d+_[0-9a-f]{48}$/', $tok) && !$q("SELECT 1 FROM attendance_gate_device WHERE token_hash=?", [$tok])->fetchColumn(),
   'admin registers device; only a hash is stored', $r);
$q("DELETE FROM attendance WHERE student_id=4"); $q("UPDATE attendance_scan_log SET voided_at=NOW() WHERE entity_type='student' AND entity_id=4");
$r = scan('A103', ['gate' => ''], $NOSESS);
ok(!$r['success'], 'without session and without token: refused', $r);
$r = scan('A103', ['gate' => '', 'csrf_token' => 'stale'], $NOSESS, ['GATE' => $tok]);
$row = $q("SELECT scanned_by, gate_device_id, gate_name FROM attendance_scan_log WHERE id=?", [$r['scan_id'] ?? 0])->fetch(PDO::FETCH_ASSOC);
ok($r['success'] && $status(4) === 'present', 'session expired + stale CSRF: gate device still scans', $r);
ok($row && (int)$row['scanned_by'] === 900 && (int)$row['gate_device_id'] > 0 && $row['gate_name'] === 'Main Gate', 'scan attributed to the device (and the admin who registered it)', $row);
foreach (['undo' => ['scan_id' => $r['scan_id'] ?? 0], 'finalize_absent' => ['force' => '1'], 'save_settings' => ['min_gap' => '9'],
          'card_payload' => ['type' => 'student', 'ids' => '1'], 'device_register' => ['name' => 'x']] as $act => $extra) {
    $x = req('api/attendance_scan.php', ['action' => $act] + $extra, $NOSESS, ['GATE' => $tok]);
    ok(!$x['success'], "gate device cannot $act", $x);
}
ok(req('api/attendance_scan.php', ['action' => 'summary'], $NOSESS, ['GATE' => $tok])['success'], 'gate device can see summary');
ok(!req('api/attendance_scan.php', ['action' => 'summary'], $NOSESS, ['GATE' => substr($tok, 0, -1) . 'x'])['success'], 'tampered token refused');
$other = req('api/attendance_scan.php', ['action' => 'device_list'], ['school_id' => 2] + ADMIN);
ok($other['success'] && $other['devices'] === [], "another school's admin does not see this device");
$devId = (int)explode('_', $tok)[1];
ok(!req('api/attendance_scan.php', ['action' => 'device_revoke', 'id' => $devId], ['school_id' => 2] + ADMIN)['success'], "another school's admin cannot remove it");
ok(req('api/attendance_scan.php', ['action' => 'device_revoke', 'id' => $devId], ADMIN)['success'], 'admin removes the device');
$x = scan('A100', [], $NOSESS, ['GATE' => $tok]);
ok(!$x['success'] && !empty($x['device_revoked']), 'removed device can no longer scan', $x);

echo "15. Photos only through signed, expiring links\n";
$q("UPDATE students SET photo='arun.jpg' WHERE id=1");
$s1 = req('api/attendance_scan.php', ['action' => 'search', 'q' => 'Arun']);
$url = $s1['results'][0]['photo'] ?? '';
ok(str_contains($url, '/api/attendance_photo.php?') && !str_contains($url, 'uploads'), 'search returns signed photo link, not the public uploads path', $url);
$get = function (string $qs) use ($SITE) {
    $p = proc_open(['php', "$SITE/run_get.php", 'api/attendance_photo.php', $qs], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp, null, ['ATT_TEST_SOCK' => getenv('ATT_TEST_SOCK')]);
    $body = stream_get_contents($pp[1]); $err = stream_get_contents($pp[2]); proc_close($p);
    preg_match('/HTTP (\d+)/', $err, $m); return [(int)($m[1] ?? 0), $body];
};
$qs = (string)parse_url($url, PHP_URL_QUERY);
[$c, $b] = $get($qs);
ok($c === 200 && $b === "\xFF\xD8\xFF fake-jpeg-bytes", 'valid link serves the photo', [$c]);
[$c] = $get(preg_replace('/id=1&/', 'id=2&', $qs));
ok($c === 403, 'changing the student id breaks the signature (403)', $c);
parse_str($qs, $pq); $pq['e'] = time() - 10;
[$c] = $get(http_build_query($pq));
ok($c === 410, 'expired link refused (410)', $c);
$q("UPDATE students SET photo='../../../../etc/passwd' WHERE id=1");
$s1 = req('api/attendance_scan.php', ['action' => 'search', 'q' => 'Arun']);
[$c, $b] = $get((string)parse_url($s1['results'][0]['photo'] ?? '', PHP_URL_QUERY));
ok($c === 404 && $b === '', 'path traversal in photo name cannot read other files', $c);

echo "16. 'Not reached school' message: once a day, only when safe\n";
$pdoC = Database::connect();
ok(req('api/attendance_scan.php', ['action' => 'save_settings', 'absent_alert' => '1', 'absent_alert_time' => '10:00'], ADMIN)['success'], 'admin switches it on (one checkbox + time)');
ok(!req('api/attendance_scan.php', ['action' => 'save_settings', 'absent_alert_time' => '25:00'], ADMIN)['success'], 'invalid time refused');
AttCore::rules($pdoC, 1, true);
$wed = '2026-10-07';                       // a normal Wednesday
$q("DELETE FROM attendance WHERE date=?", [$wed]);
$q("INSERT INTO attendance (school_id, student_id, date, status, marked_by) VALUES (1,1,?,'present',1),(1,2,?,'leave',1)", [$wed, $wed]);
ok(AttCore::notArrivedAlert($pdoC, 1, strtotime("$wed 09:59")) === 'too_early', 'before 10:00: nothing');
$res = AttCore::notArrivedAlert($pdoC, 1, strtotime("$wed 10:20"));
$keys = $q("SELECT dedupe_key FROM attendance_notify_queue WHERE event_type='student_not_arrived' ORDER BY dedupe_key")->fetchAll(PDO::FETCH_COLUMN);
ok($res === 'queued:2' && $keys === ["notarr:3:$wed", "notarr:5:$wed"], 'at 10:20: Ravi + Amit queued; present, on-leave and no-phone students skipped', [$res, $keys]);
ok(AttCore::notArrivedAlert($pdoC, 1, strtotime("$wed 10:40")) === 'done', 'second run the same day does nothing');
$thu = '2026-10-08';
$q("DELETE FROM attendance WHERE date=?", [$thu]);
$q("INSERT INTO attendance (school_id, student_id, date, status, marked_by) VALUES (1,1,?,'present',1)", [$thu]);
ok(AttCore::notArrivedAlert($pdoC, 1, strtotime("$thu 10:20")) === 'too_few_marked', 'only 1 of 5 marked (attendance not really taken) → no mass message');
ok(AttCore::notArrivedAlert($pdoC, 1, strtotime("2026-10-10 10:20")) === 'off_day', '2nd Saturday (weekly off) → nothing');
$q("INSERT INTO school_holidays (school_id, date) VALUES (1, '2026-10-02')");
ok(AttCore::notArrivedAlert($pdoC, 1, strtotime("2026-10-02 10:20")) === 'off_day', 'school holiday → nothing');
ok(AttCore::notArrivedAlert($pdoC, 1, strtotime("2026-10-06 14:00")) === 'too_late', 'more than 3 hours late → skipped, not sent late');
ok((int)$q("SELECT COUNT(*) FROM attendance_notify_queue WHERE event_type='student_not_arrived'")->fetchColumn() === 2, 'total messages: exactly 2');

echo "17. Needs attention list\n";
$days = AttCore::schoolDaysBack($pdoC, 1, $today, 30, false);
$q("DELETE FROM attendance WHERE student_id IN (1,5) AND date IN (" . implode(',', array_fill(0, count($days), '?')) . ")", array_merge($days));
foreach (array_slice($days, 0, 4) as $d) $q("INSERT INTO attendance (school_id, student_id, date, status, marked_by) VALUES (1,3,?,'absent',1)
                                              ON DUPLICATE KEY UPDATE status='absent'", [$d]);
foreach (array_slice($days, 5, 5) as $d) $q("INSERT INTO attendance (school_id, student_id, date, status, marked_by) VALUES (1,1,?,'late',1)", [$d]);
foreach (array_slice($days, 0, 12) as $i => $d) $q("INSERT INTO attendance (school_id, student_id, date, status, marked_by) VALUES (1,5,?,?,1)", [$d, ($i % 3 === 0) ? 'present' : 'absent']);
$r = req('api/attendance_scan.php', ['action' => 'insights']);
$by = []; foreach ($r['rows'] ?? [] as $x) $by[$x['name']] = array_column($x['why'], 1, 0);
ok($r['success'] && ($r['rows'][0]['name'] ?? '') === 'Ravi Das' && ($by['Ravi Das']['streak'] ?? 0) >= 3, 'Ravi (absent many days in a row) is first', $r['rows'] ?? $r);
ok(isset($by['Amit Roy']['low']) && $by['Amit Roy']['low'] < 75 && !isset($by['Amit Roy']['streak']), 'Amit: below 75% flagged (not a streak)', $by['Amit Roy'] ?? null);
ok(($by['Arun Kumar']['late'] ?? 0) === 5, 'Arun: late 5 times flagged', $by['Arun Kumar'] ?? null);
ok(!isset($by['Priya Singh']), 'Priya (regular) not in the list');
ok(!req('api/attendance_scan.php', ['action' => 'insights'], ['logged_in' => 0], ['GATE' => 'gd_1_' . str_repeat('a', 48)])['success'], 'gate device cannot see it');

echo "18. Monthly register\n";
$c = req('api/attendance_reports.php', ['action' => 'classes']);
ok($c['success'] && ($c['classes'][0]['value'] ?? '') === '10:20' && $c['classes'][0]['count'] === 5, 'class list from real data', $c);
$month = substr($wed, 0, 7);
$g = req('api/attendance_reports.php', ['action' => 'register', 'class' => '10:20', 'month' => $month]);
$names = array_column($g['rows'] ?? [], 'name');
$cellOf = function ($name, $day) use ($g) { foreach ($g['rows'] as $r) if ($r['name'] === $name) return $r['cells'][$day - 1]; return null; };
ok($g['success'] && count($g['rows']) === 5 && $names === ['Amit Roy', 'Arun Kumar', 'Neha Gupta', 'Priya Singh', 'Ravi Das'], '5 students, sorted by name', $names);
$lateDay = (int)substr($days[5], 8, 2);   // Arun was late that day (test 17); late counts as P
ok((substr($days[5], 0, 7) !== $month || $cellOf('Arun Kumar', $lateDay) === 'P') && $cellOf('Priya Singh', 7) === 'Lv' && $cellOf('Neha Gupta', 7) === '',
   'late → P, leave → Lv, not marked → blank');
ok($cellOf('Ravi Das', 4) === 'W' && $cellOf('Ravi Das', 10) === 'W' && $cellOf('Ravi Das', 2) === 'H', 'Sunday & 2nd Saturday = W, holiday = H');
$day4 = $g['days'][3]; $workingExpected = 0;
foreach ($g['days'] as $d) if (!$d['off'] && !$d['future']) $workingExpected++;
ok($g['working'] === $workingExpected && $day4['off'] === 'W', 'working days counted on the server', [$g['working'], $workingExpected]);
$s = req('api/attendance_reports.php', ['action' => 'register', 'class' => 'staff', 'month' => $month]);
ok($s['success'] && count($s['rows']) === 2, 'staff register', $s['rows'] ?? $s);
ok(!req('api/attendance_reports.php', ['action' => 'register', 'class' => '10:20', 'month' => '2099-01'])['success'], 'future month refused');
ok(!req('api/attendance_reports.php', ['action' => 'register', 'class' => "10:20' OR 1", 'month' => $month])['success'], 'bad class value refused');
$x = req('api/attendance_reports.php', ['action' => 'register', 'class' => '10:20', 'month' => $month], ['school_id' => 2] + ADMIN);
ok($x['success'] && $x['rows'] === [], "another school sees none of these students");

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
