<?php
// tests/integration.php — end-to-end tests of the bus module against a REAL MySQL/MariaDB.
//
//   BUS_TEST_DB=sszone BUS_TEST_USER=ss BUS_TEST_PASS=ss php tests/integration.php
//
// Builds a sandbox (this repo + tests/stubs for the panel's core files), resets the test database, runs
// tools/migrate.php, seeds TWO schools and checks — over real HTTP — that one school can never reach the other
// school's data, plus pairing, rate limits, GPS, trips, ETA, notes, alerts and learning.
// WARNING: drops and recreates the test database. Never point it at a real database.

if (PHP_SAPI !== 'cli') exit;
$ROOT = dirname(__DIR__);
$DB = getenv('BUS_TEST_DB') ?: 'sszone';
if (in_array(strtolower($DB), ['', 'mysql', 'information_schema', 'performance_schema', 'sys'], true)) exit("bad test db\n");
$SB = sys_get_temp_dir() . '/bus_sandbox';
$PORT = 8130;
$BASE = "http://127.0.0.1:$PORT";
$PUSHLOG = sys_get_temp_dir() . '/bus_push.log';
putenv("BUS_TEST_PUSHLOG=$PUSHLOG");

// ── sandbox ──────────────────────────────────────────────────────────────────
exec('rm -rf ' . escapeshellarg($SB));
function clearCache(): void { exec('rm -rf ' . escapeshellarg(sys_get_temp_dir()) . '/sszone_bus_cache_*'); if (getenv('BUS_TEST_REDIS')) exec('redis-cli -h ' . escapeshellarg(getenv('BUS_TEST_REDIS')) . ' --scan --pattern "bus:*" | xargs -r redis-cli -h ' . escapeshellarg(getenv('BUS_TEST_REDIS')) . ' del >/dev/null'); }
clearCache();
mkdir($SB);
foreach (['api', 'includes', 'student', 'admin', 'tools', 'database'] as $d) exec('cp -r ' . escapeshellarg("$ROOT/$d") . ' ' . escapeshellarg("$SB/$d"));
exec('cp -r --update=none ' . escapeshellarg("$ROOT/tests/stubs") . '/. ' . escapeshellarg($SB));   // -n: never overwrite real module files
@unlink($PUSHLOG);

// ── database ─────────────────────────────────────────────────────────────────
$root = new PDO('mysql:host=localhost;charset=utf8mb4', getenv('BUS_TEST_USER') ?: 'ss', getenv('BUS_TEST_PASS') ?: 'ss', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach ($root->query("SHOW TABLES FROM `$DB`")->fetchAll(PDO::FETCH_COLUMN) as $t) $root->exec("DROP TABLE `$DB`.`$t`");
$pdo = new PDO("mysql:host=localhost;dbname=$DB;charset=utf8mb4", getenv('BUS_TEST_USER') ?: 'ss', getenv('BUS_TEST_PASS') ?: 'ss',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("SET time_zone = '+05:30'");   // same session time zone as the module (includes/bus_db.php)
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', file_get_contents("$ROOT/tests/fixtures/base_schema.sql"))))) as $q) $pdo->exec($q);


// ── tiny test framework ──────────────────────────────────────────────────────
$pass = 0; $fail = 0;
function ok(bool $c, string $name, $info = null): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "  \033[32m✔\033[0m $name\n"; }
    else { $fail++; echo "  \033[31m✖ $name\033[0m" . ($info !== null ? '  → ' . (is_string($info) ? $info : json_encode($info)) : '') . "\n"; }
}
function section(string $t): void { echo "\n\033[1m$t\033[0m\n"; }
/** HTTP request. $jar = cookie file (session), $hdr = extra headers. Returns [status, json|null, raw]. */
function req(string $method, string $path, array $data = [], ?string $jar = null, array $hdr = []): array {
    global $BASE;
    $ch = curl_init();
    $url = $BASE . $path;
    if ($method === 'GET' && $data) $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_map(function ($k, $v) { return "$k: $v"; }, array_keys($hdr), $hdr)]);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); }
    if ($jar) { curl_setopt($ch, CURLOPT_COOKIEJAR, $jar); curl_setopt($ch, CURLOPT_COOKIEFILE, $jar); }
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code, json_decode((string)$raw, true), $raw];
}
function login(string $as, int $school, int $id = 0): array {
    $jar = tempnam(sys_get_temp_dir(), 'jar');
    [, $j] = req('GET', '/test_login.php', ['as' => $as, 'school' => $school, 'id' => $id], $jar);
    return [$jar, $j['csrf'] ?? ''];
}
function admin(array $sess, string $action, array $data = []): array {   // bus_actions.php as admin/teacher
    [$jar, $csrf] = $sess;
    return req('POST', '/api/bus_actions.php', $data + ['action' => $action, 'csrf_token' => $csrf], $jar);
}
function trip(string $token, string $action, array $data = []): array {
    return req('POST', '/api/bus_trip.php', $data + ['action' => $action], null, ['X-Device-Token' => $token]);
}
function gps(array $auth, array $pt, float $speedKmh = 30, int $acc = 8): array {
    return req('POST', '/api/gps_update.php', ['lat' => $pt[0], 'lng' => $pt[1], 'speed' => $speedKmh, 'acc' => $acc, 'heading' => 0] + ($auth['key'] ?? []), null, $auth['hdr'] ?? []);
}
function pushes(): array { global $PUSHLOG; return is_file($PUSHLOG) ? array_map('json_decode', file($PUSHLOG, FILE_IGNORE_NEW_LINES)) : []; }
function clearPushes(): void { global $PUSHLOG; @unlink($PUSHLOG); }
function off(float $m, float $e = 0): array { return [28.6 + $m / 111320, 77.2 + $e / 97700]; }


// ── seed two schools (only the panel's own tables exist — no bus tables yet, no migrate.php run) ──
$pdo->exec("INSERT INTO classes (id, class_name) VALUES (1,'5'),(2,'7'); INSERT INTO sections (id, section_name) VALUES (1,'A')");
$pdo->exec("INSERT INTO school_buses (id, school_id, bus_name, bus_number, gps_api_key, status) VALUES
   (1, 1, 'A-Bus', 'UP32 A', 'keyschoolA0000000000000000000000000000000000000', 'active'),
   (2, 2, 'B-Bus', 'UP32 B', 'keyschoolB0000000000000000000000000000000000000', 'active')");
$pdo->exec("INSERT INTO van_routes (id, school_id, route_name) VALUES (1,1,'A-Route'),(2,2,'B-Route')");
$pdo->exec("INSERT INTO bus_route_assignments (school_id, route_id, bus_id, shift_count, pickup_time, drop_time, days, status) VALUES
   (1,1,1,1,'07:00','13:30','Mon,Tue,Wed,Thu,Fri,Sat,Sun','active'), (2,2,2,1,'07:00','13:30','Mon,Tue,Wed,Thu,Fri,Sat,Sun','active')");
$pdo->exec("INSERT INTO students (id, school_id, name, class_id, section_id) VALUES
   (11,1,'Aman Singh',1,1),(12,1,'Riya Kumari',1,1),(13,1,'Zoya Parveen Khan',2,1),(21,2,'Other School Kid',1,1)");
$pdo->exec("INSERT INTO student_van_assignments (student_id, van_route_id, school_id) VALUES (11,1,1),(12,1,1),(13,1,1),(21,2,2)");
[$h11, $h12, $h21] = [off(300), off(700), off(300)];
$pdo->exec("INSERT INTO student_home_locations (student_id, school_id, lat, lng, alert_radius, push_enabled, updated_at) VALUES
   (11,1,{$h11[0]},{$h11[1]},500,1,NOW()), (12,1,{$h12[0]},{$h12[1]},500,1,NOW()), (21,2,{$h21[0]},{$h21[1]},500,1,NOW())");
$pdo->exec("INSERT INTO push_subscriptions (student_id, school_id, endpoint, endpoint_hash, p256dh, auth) VALUES
   (11,1,'ep-11',SHA2('ep-11',256),'p','a'),(12,1,'ep-12',SHA2('ep-12',256),'p','a'),(13,1,'ep-13',SHA2('ep-13',256),'p','a'),(21,2,'ep-21',SHA2('ep-21',256),'p','a')");

// ── server ───────────────────────────────────────────────────────────────────
$srv = proc_open(['php', '-d', 'apc.enabled=0', '-S', "127.0.0.1:$PORT", '-t', $SB], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pp,
                 null, ['BUS_TEST_PUSHLOG' => $PUSHLOG] + getenv());
usleep(800000);

try {
    $A = login('school_admin', 1); $B = login('school_admin', 2); $T = login('teacher', 1);

    section('Automatic database setup (no command line)');
    ok(!$pdo->query("SHOW TABLES LIKE 'bus_trips'")->fetchColumn(), 'fresh deploy: bus tables do not exist yet');
    // Something on this server blocks one step (here: a view with a bus table's name) → admin must SEE why
    $pdo->exec("CREATE VIEW bus_trip_stops AS SELECT 1 AS x");
    [, $j] = admin($A, 'schema_status');
    ok($j['ready'] === false && $j['errors'] && strpos(implode(' ', $j['errors']), 'bus_trip_stops') !== false, 'a failed setup step is shown to the admin with its reason', $j);
    [, $j] = admin($T, 'schema_status');
    ok($j['ready'] === false && $j['errors'] === [], 'teachers see that it is not ready, but no technical details');
    $pdo->exec("DROP VIEW bus_trip_stops");
    [, $j] = admin($A, 'schema_status', ['retry' => 1]);
    ok($j['ready'] === true, '"Dobara koshish" after the cause is fixed → setup completes', $j);
    $pdo->exec("DROP TABLE IF EXISTS bus_schema_version");   // and run the normal first-request path from scratch below
    clearCache();
    $par = [];
    for ($i = 0; $i < 3; $i++) $par[] = curl_init();   // three admins open the page at the same moment
    $mh = curl_multi_init();
    foreach ($par as $ch) { curl_setopt_array($ch, [CURLOPT_URL => "$BASE/api/bus_actions.php", CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query(['action' => 'get_fleet']), CURLOPT_COOKIEFILE => $A[0], CURLOPT_TIMEOUT => 60]); curl_multi_add_handle($mh, $ch); }
    do { curl_multi_exec($mh, $run); curl_multi_select($mh); } while ($run);
    $okAll = true; foreach ($par as $ch) { $j = json_decode(curl_multi_getcontent($ch), true); $okAll = $okAll && !empty($j['success']); }
    ok($okAll, 'first requests (3 at once) succeed while the module sets up its tables');
    $sv = (int)$pdo->query("SELECT MAX(v) FROM bus_schema_version")->fetchColumn();
    $need = ['bus_live', 'bus_watchdog_alerts', 'bus_trips', 'bus_trip_stops', 'bus_route_learn', 'bus_rate_limits', 'bus_pair_codes',
             'bus_driver_devices', 'bus_alert_settings', 'bus_alerts', 'bus_alert_state', 'bus_halt_reports', 'bus_absences', 'bus_schema_version'];
    $have = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
    ok($sv >= 8 && !array_diff($need, $have), "all 14 bus tables created automatically (schema v$sv)", array_values(array_diff($need, $have)));
    ok((bool)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_home_locations' AND COLUMN_NAME='note'")->fetchColumn(), "the panel's own tables got the new columns");
    $out1 = shell_exec('php ' . escapeshellarg("$SB/tools/migrate.php") . ' 2>&1');
    ok(strpos($out1, '✖') === false && strpos($out1, 'Done') !== false, 'tools/migrate.php afterwards: nothing breaks (idempotent)', $out1);

    section('Driver phone pairing (no key in any link)');
    [, $j] = admin($T, 'driver_pair_link', ['bus_id' => 1]);
    ok(empty($j['success']), 'a teacher cannot create a pairing link', $j);
    [, $j] = admin($B, 'driver_pair_link', ['bus_id' => 1]);
    ok(empty($j['success']), "school B cannot create a pairing link for school A's bus", $j);
    [, $j] = admin($A, 'driver_pair_link', ['bus_id' => 1]);
    $code = $j['code'] ?? '';
    ok(!empty($j['success']) && preg_match('/^[a-f0-9]{32}$/', $code), 'school A creates a one-time pairing code');
    ok(!$pdo->query("SELECT COUNT(*) FROM bus_pair_codes WHERE code_hash='$code'")->fetchColumn(), 'only the hash of the code is stored');
    [$s, $j] = req('POST', '/api/bus_trip.php', ['action' => 'pair', 'code' => $code]);
    $tokA = $j['token'] ?? '';
    ok($s === 200 && strlen($tokA) === 64, 'phone redeems the code for its own device token');
    [$s] = req('POST', '/api/bus_trip.php', ['action' => 'pair', 'code' => $code]);
    ok($s === 400, 'the same link does not work a second time');
    [, $j] = admin($A, 'driver_pair_link', ['bus_id' => 1]); $old = $j['code'];
    [, $j] = admin($A, 'driver_pair_link', ['bus_id' => 1]); $new = $j['code'];
    [$s] = req('POST', '/api/bus_trip.php', ['action' => 'pair', 'code' => $old]);
    ok($s === 400, 'creating a new link kills the older unused link');
    $pdo->exec("UPDATE bus_pair_codes SET expires_at = NOW() - INTERVAL 1 SECOND");
    [$s] = req('POST', '/api/bus_trip.php', ['action' => 'pair', 'code' => $new]);
    ok($s === 400, 'an expired link does not work');
    [, $j] = admin($B, 'driver_pair_link', ['bus_id' => 2]);
    [, $j] = req('POST', '/api/bus_trip.php', ['action' => 'pair', 'code' => $j['code']]);
    $tokB = $j['token'] ?? '';
    ok(strlen($tokB) === 64, 'school B pairs its own phone');

    section('School isolation (driver phone)');
    [$s, $j] = trip($tokA, 'stops', ['shift' => 1]);
    $ids = array_column($j['stops'] ?? [], 'id');
    ok($s === 200 && $ids == [11, 12], "phone A sees only school A's students with a home", $ids);
    ok(in_array('Zoya P.', array_column($j['missing'] ?? [], 'name'), true), 'student without home is listed by short name only');
    ok(!in_array('Aman Singh', array_column($j['stops'], 'name'), true) && in_array('Aman S.', array_column($j['stops'], 'name'), true), 'driver sees short names, not full names');
    [$s, $j] = trip($tokA, 'start', ['shift' => 1]);
    ok(!empty($j['ok']), 'trip starts');
    $ist = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $st0 = strtotime($pdo->query("SELECT started_at FROM bus_trips WHERE ended_at IS NULL")->fetchColumn());
    ok(abs($st0 - strtotime($ist->format('Y-m-d H:i:s'))) < 120, 'trip start time is stored in school time (IST) even though MySQL runs in UTC', [$ist->format('H:i'), date('H:i', $st0)]);
    [$s, $j] = trip($tokA, 'mark_stop', ['student_id' => 21, 'status' => 'done']);
    ok(empty($j['ok']), "phone A cannot mark school B's student", $j);
    [$s, $j] = trip($tokA, 'set_order', ['order' => '21,12,11']);
    ok(($j['saved'] ?? -1) === 2, 'a foreign student id in the order is ignored', $j);
    [$s, $j] = trip($tokA, 'eta', ['etas' => '21:100,11:900,12:1500']);
    ok(($j['saved'] ?? -1) === 2, 'a foreign student id in the ETAs is ignored', $j);
    $tokBad = str_repeat('a', 64);
    [$s, $j] = trip($tokBad, 'stops');
    ok($s === 401 && ($j['code'] ?? '') === 'unpaired', 'an unknown token gets 401');
    [$s] = req('POST', '/api/bus_trip.php', ['action' => 'stops', 'key' => 'keyschoolA0000000000000000000000000000000000000'], null, ['X-API-Key' => 'keyschoolA0000000000000000000000000000000000000']);
    ok($s === 401, 'the GPS API key can NOT read student data');
    [$s] = req('GET', '/api/bus_trip.php', ['action' => 'stops'], null, ['X-Device-Token' => $tokA]);
    ok($s === 405, 'GET is refused (POST only)');
    [$s] = req('POST', '/api/bus_trip.php', ['action' => 'stops'], null, ['X-Device-Token' => $tokA, 'Sec-Fetch-Site' => 'cross-site']);
    ok($s === 403, 'cross-site requests are refused');

    section('School isolation (admin panel)');
    [, $j] = admin($B, 'get_bus_live_detail', ['bus_id' => 1]);
    ok(empty($j['success']), "school B admin cannot open school A's bus detail", $j);
    [, $j] = admin($B, 'get_fleet');
    ok(array_column($j['buses'] ?? [], 'id') == [2], 'fleet shows only own buses', $j['buses'] ?? $j);
    [, $j] = admin($B, 'get_live_locations');
    ok(array_column($j['buses'] ?? [], 'id') == [2], 'live map shows only own buses');
    [, $j] = admin($B, 'driver_devices');
    ok(count($j['devices'] ?? []) === 1 && (int)$j['devices'][0]['bus_id'] === 2, 'device list shows only own phones');
    $devA = (int)$pdo->query("SELECT id FROM bus_driver_devices WHERE bus_id=1")->fetchColumn();
    admin($B, 'driver_revoke', ['id' => $devA]);
    [$s] = trip($tokA, 'status');
    ok($s === 200, "school B cannot unpair school A's phone");
    [, $j] = admin($B, 'get_trips', ['from' => date('Y-m-d', strtotime('-1 day')), 'to' => date('Y-m-d')]);
    ok(count($j['trips'] ?? []) === 0, "school B sees none of school A's trips");
    [, $j] = admin($T, 'save_alert_settings', ['overspeed_kmh' => 90]);
    ok(empty($j['success']), 'a teacher cannot change alert settings');
    [, $j] = req('POST', '/api/bus_actions.php', ['action' => 'save_alert_settings', 'overspeed_kmh' => 90, 'csrf_token' => 'forged'], $A[0]);
    ok(empty($j['success']), 'a forged CSRF token is refused', $j);
    [, $j] = req('POST', '/api/bus_actions.php', ['action' => 'driver_pair_link', 'bus_id' => 1, 'csrf_token' => $B[1]], $A[0]);
    ok(empty($j['success']), "school B's CSRF token does not work in school A's session", $j);

    section('School isolation (student portal)');
    $S11 = login('student', 1, 11); $S21 = login('student', 2, 21);
    [$s, $j] = req('GET', '/api/bus_location.php', ['bus_id' => 1, 'school_id' => 1], $S21[0]);
    ok($s === 403, "a school B student cannot read school A's bus");
    [$s, $j] = req('GET', '/api/bus_location.php', ['bus_id' => 2, 'school_id' => 2], $S11[0]);
    ok($s === 403, "a school A student cannot read school B's bus");

    section('GPS ingestion + rate limits');
    $dev = ['hdr' => ['X-Device-Token' => $tokA]];
    [$s, $j] = gps($dev, off(0));
    ok(!empty($j['ok']), 'phone sends GPS with its token (no key)');
    [$s, $j] = gps(['key' => ['key' => 'keyschoolB0000000000000000000000000000000000000']], off(0));
    ok(!empty($j['ok']), 'hardware sends GPS with the bus key');
    $codes = [];
    for ($i = 0; $i < 21; $i++) { [$s] = gps(['key' => ['key' => 'wrongkey' . $i]], off(0)); $codes[] = $s; }
    ok(count(array_filter($codes, function ($c) { return $c === 429; })) === 1 && end($codes) === 429, '20 wrong keys are answered, the 21st from that IP gets 429', $codes);
    [$s] = gps(['key' => ['key' => 'keyschoolB0000000000000000000000000000000000000']], off(0));
    ok($s === 429, 'while blocked, that IP cannot even use a right key (no guessing window)');
    $pdo->exec("DELETE FROM bus_rate_limits");

    section('Home note (student → driver)');
    [, $j] = req('POST', '/api/student_bus.php', ['action' => 'set_note', 'note' => "<b>Mandir</b> ke saamne,\n neela gate " . str_repeat('x', 200)], $S11[0]);
    ok(!empty($j['success']) && $j['note'] === 'Mandir ke saamne, neela gate ' . str_repeat('x', 91), 'note is stripped of HTML, one line, max 120 chars', $j['note'] ?? $j);
    [, $j] = trip($tokA, 'stops');
    $n11 = array_values(array_filter($j['stops'], function ($s) { return $s['id'] === 11; }))[0]['note'] ?? '';
    ok(strpos($n11, 'Mandir ke saamne') === 0, 'driver sees the note');

    section('Trip, ETA for parents, auto messages');
    clearPushes();
    [, $j] = trip($tokA, 'eta', ['etas' => '11:240,12:900']);
    $p = pushes();
    ok(count($p) === 1 && $p[0]->endpoint === 'ep-11' && strpos($p[0]->body, '4 min') !== false, 'parent of the student ~4 min away gets ONE push', $p);
    trip($tokA, 'eta', ['etas' => '11:200,12:880']);
    ok(count(pushes()) === 1, 'no duplicate ETA push on the next update');
    [, $j] = req('GET', '/api/bus_location.php', ['bus_id' => 1, 'school_id' => 1], $S11[0]);
    ok(($j['trip']['eta_min'] ?? null) !== null && $j['trip']['eta_min'] <= 4, 'student portal shows the ETA', $j['trip'] ?? $j);
    ok(!array_filter(pushes(), function ($x) { return $x->endpoint === 'ep-21'; }), 'no push ever went to the other school');
    $msgs = $pdo->query("SELECT school_id, recipient_id, auto_event, is_auto FROM school_messages WHERE auto_event='Bus ETA'")->fetchAll();
    ok(count($msgs) === 1 && (int)$msgs[0]['school_id'] === 1 && (int)$msgs[0]['recipient_id'] === 11 && (int)$msgs[0]['is_auto'] === 1,
       'the ETA also lands in that parent\'s Messages tab as an Auto message', $msgs);
    $start = $pdo->query("SELECT COUNT(*) FROM school_messages WHERE auto_event='Bus trip shuru' AND school_id=1")->fetchColumn();
    ok((int)$start === 3, '"trip started" auto message for every student of the shift (incl. the one without home)', $start);
    ok(!(int)$pdo->query("SELECT COUNT(*) FROM school_messages WHERE school_id=2 OR recipient_id=21")->fetchColumn(), 'no message ever went to the other school');

    section('Safety alerts');
    $pdo->exec("INSERT INTO bus_alert_settings (school_id, overspeed_kmh, overspeed_sec, school_lat, school_lng, school_radius_m, deviation_m, deviation_sec, notify_parents, updated_at)
                VALUES (1, 40, 5, " . off(2000)[0] . ", " . off(2000)[1] . ", 150, 300, 5, 1, NOW())");
    clearCache();   // (written straight into the DB here; the admin's Save clears the settings cache the same way)
    gps($dev, off(100)); sleep(3); gps($dev, off(200), 70); sleep(3); gps($dev, off(350), 72); sleep(3); gps($dev, off(500), 75); sleep(1);
    $al = $pdo->query("SELECT type, school_id, message FROM bus_alerts WHERE type='overspeed'")->fetchAll();
    ok(count($al) === 1 && (int)$al[0]['school_id'] === 1, 'sustained overspeed → exactly one alert, for the right school', $al);
    [, $j] = admin($A, 'get_live_locations');
    ok(count($j['alerts'] ?? []) >= 1, 'admin A sees the alert in the live feed');
    [, $j] = admin($B, 'get_alerts');
    ok(count($j['alerts'] ?? []) === 0, 'admin B does not see school A alerts');
    // geofence: drive into the school gate (pickup trip → parents of children on board)
    trip($tokA, 'mark_stop', ['student_id' => 11, 'status' => 'done']);
    clearPushes();
    for ($m = 1100; $m <= 2000; $m += 100) { gps($dev, off($m), 25); sleep(3); }
    $ar = $pdo->query("SELECT COUNT(*) FROM bus_alerts WHERE type='school_arrive'")->fetchColumn();
    $kind = $pdo->query("SELECT kind FROM bus_trips WHERE ended_at IS NULL")->fetchColumn();
    ok((int)$ar === 1, 'bus entering the school gate → school_arrive alert');
    $p = array_map(function ($x) { return $x->endpoint; }, array_filter(pushes(), function ($x) { return strpos($x->title, 'school') !== false; }));
    if ($kind === 'pickup') ok($p == ['ep-11'], 'only the parent of the child on board is told "bus reached school"', $p);
    else ok($p == [], 'on a drop-time trip parents are not told "bus reached school"', [$kind, $p]);

    section('Unplanned halt → reason → one-click parent message');
    for ($m = 2100; $m <= 2500; $m += 100) { gps($dev, off($m), 25); sleep(3); }      // away from stops and the school gate
    gps($dev, off(2500), 0); sleep(3);
    $pdo->exec("UPDATE bus_live SET still_since = NOW() - INTERVAL 10 MINUTE WHERE bus_id=1");
    gps($dev, off(2500), 0); sleep(3); gps($dev, off(2500), 0);
    $h = $pdo->query("SELECT COUNT(*) FROM bus_alerts WHERE type='halt' AND school_id=1")->fetchColumn();
    ok((int)$h === 1, 'standing 10 min on the road with no reason → exactly one admin alert', $h);
    [, $j] = trip($tokA, 'halt', ['reason' => 'puncture', 'text' => '<script>x</script>Tyre badal rahe', 'delay' => 20, 'lat' => off(2500)[0], 'lng' => off(2500)[1], 'halt_sec' => 600]);
    ok(!empty($j['ok']), 'driver reports the reason from the popup');
    $al = $pdo->query("SELECT id, message, ref_id, trip_id FROM bus_alerts WHERE type='halt_report' ORDER BY id DESC LIMIT 1")->fetch();
    ok($al && strpos($al['message'], 'Tyre puncture') !== false && strpos($al['message'], '<script>') === false && $al['ref_id'], 'admin gets it (cleaned text, linked report)', $al);
    [, $j] = admin($B, 'broadcast_preview', ['alert_id' => $al['id']]);
    ok(empty($j['success']), "school B cannot even preview school A's alert");
    [, $j] = admin($B, 'broadcast', ['alert_id' => $al['id'], 'text' => 'hack hack hack']);
    ok(empty($j['success']), "school B cannot message school A's parents");
    [, $j] = admin($T, 'broadcast', ['alert_id' => $al['id'], 'text' => 'teacher message']);
    ok(empty($j['success']), 'a teacher cannot broadcast');
    [, $j] = admin($A, 'broadcast_preview', ['alert_id' => $al['id']]);
    ok(!empty($j['success']) && $j['count'] === 3 && strpos($j['text'], 'tyre puncture') !== false && strpos($j['text'], '20 min') !== false,
       'preview: ready-made message + 3 parents of the shift', $j);
    clearPushes();
    [, $j] = admin($A, 'broadcast', ['alert_id' => $al['id'], 'text' => $j['text']]);
    $eps = array_map(function ($x) { return $x->endpoint; }, pushes()); sort($eps);
    ok(!empty($j['success']) && $eps == ['ep-11', 'ep-12', 'ep-13'], 'one click → push to every parent of that shift', [$j, $eps]);
    ok((int)$pdo->query("SELECT COUNT(*) FROM school_messages WHERE auto_event='Bus suchna' AND school_id=1")->fetchColumn() === 3
       && !(int)$pdo->query("SELECT COUNT(*) FROM school_messages WHERE school_id=2")->fetchColumn(), '…and into their Messages tab, nothing to school B');
    [, $j] = admin($A, 'broadcast', ['alert_id' => $al['id'], 'text' => 'again right away']);
    ok(empty($j['success']), 'the same alert cannot be re-sent within a minute (no double click spam)');
    trip($tokA, 'mark_stop', ['student_id' => 12, 'status' => 'absent']);
    [, $j] = admin($A, 'broadcast_preview', ['bus_id' => 1]);
    ok(($j['count'] ?? 0) === 2, 'a student marked absent is left out', $j);
    sleep(2); gps($dev, off(2600), 30); sleep(1);
    $rs = $pdo->query("SELECT COUNT(*) FROM bus_alerts WHERE type='halt_resolved' AND school_id=1")->fetchColumn();
    ok((int)$rs === 1 && $pdo->query("SELECT resolved_at FROM bus_halt_reports ORDER BY id DESC LIMIT 1")->fetchColumn(), 'bus moves again → "phir chal padi" alert, report closed');

    section('Trip end + learning');
    [, $j] = trip($tokA, 'stop');
    ok(!empty($j['ok']) && isset($j['summary']), 'trip ends with a summary');
    $lr = $pdo->query("SELECT kind, learned_order FROM bus_route_learn WHERE bus_id=1")->fetch();
    ok(!$lr, 'a trip with < 2 picked students teaches nothing', $lr);

    section('Parent: "aaj bachcha nahi aayega"');
    trip($tokA, 'stop');
    $S12 = login('student', 1, 12);
    $today = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
    [, $j] = req('POST', '/api/student_bus.php', ['action' => 'set_absence', 'date' => date('Y-m-d', strtotime($today . ' -1 day')), 'kind' => 'both'], $S12[0]);
    ok(empty($j['success']), 'a past date is refused');
    [, $j] = req('POST', '/api/student_bus.php', ['action' => 'set_absence', 'date' => date('Y-m-d', strtotime($today . ' +20 day')), 'kind' => 'both'], $S12[0]);
    ok(empty($j['success']), 'more than 14 days ahead is refused');
    [, $j] = req('POST', '/api/student_bus.php', ['action' => 'set_absence', 'date' => $today, 'kind' => 'both', 'note' => 'Bukhar hai'], $S12[0]);
    ok(!empty($j['success']), 'parent marks "not today"');
    [, $j] = trip($tokA, 'stops');
    $r12 = array_values(array_filter($j['stops'], function ($s) { return $s['id'] === 12; }))[0];
    ok($r12['absence'] === 'both' && $r12['absence_note'] === 'Bukhar hai', 'driver sees it before the trip starts', $r12);
    trip($tokA, 'start', ['shift' => 1]);
    $t3 = (int)$pdo->query("SELECT id FROM bus_trips WHERE bus_id=1 AND ended_at IS NULL")->fetchColumn();
    $st = $pdo->query("SELECT status, marked_by FROM bus_trip_stops WHERE trip_id=$t3 AND student_id=12")->fetch();
    ok($st && $st['status'] === 'absent' && $st['marked_by'] === 'parent', 'trip start pre-marks the stop ✖ (by parent)', $st);
    [, $j] = req('POST', '/api/student_bus.php', ['action' => 'set_absence', 'date' => $today, 'kind' => 'both'], $S11[0]);
    $st = $pdo->query("SELECT status, marked_by FROM bus_trip_stops WHERE trip_id=$t3 AND student_id=11")->fetch();
    ok($st && $st['status'] === 'absent' && $st['marked_by'] === 'parent', 'a note during a running trip marks it at once', $st);
    req('POST', '/api/student_bus.php', ['action' => 'cancel_absence', 'date' => $today], $S11[0]);
    $st = $pdo->query("SELECT status FROM bus_trip_stops WHERE trip_id=$t3 AND student_id=11")->fetchColumn();
    ok($st === 'pending', 'cancelling during the trip brings the stop back', $st);
    trip($tokA, 'mark_stop', ['student_id' => 11, 'status' => 'absent']);
    req('POST', '/api/student_bus.php', ['action' => 'cancel_absence', 'date' => $today], $S11[0]);
    $st = $pdo->query("SELECT status, marked_by FROM bus_trip_stops WHERE trip_id=$t3 AND student_id=11")->fetch();
    ok($st['status'] === 'absent' && $st['marked_by'] === 'driver', "a parent's cancel never undoes the driver's own ✖", $st);
    [, $j] = req('GET', '/api/student_bus.php', ['action' => 'list_absences'], $S12[0]);
    ok(count($j['absences'] ?? []) === 1 && $j['absences'][0]['note'] === 'Bukhar hai', 'parent sees the upcoming note');
    $S21b = login('student', 2, 21);
    req('POST', '/api/student_bus.php', ['action' => 'set_absence', 'date' => $today, 'kind' => 'both'], $S21b[0]);
    ok((int)$pdo->query("SELECT COUNT(*) FROM bus_trip_stops WHERE trip_id=$t3 AND student_id=21")->fetchColumn() === 0, "school B's note never touches school A's trip");
    req('POST', '/api/student_bus.php', ['action' => 'cancel_absence', 'date' => $today], $S12[0]);
    trip($tokA, 'stop');

    section('Learning from a real trip');
    trip($tokA, 'start', ['shift' => 1]);
    $t2 = (int)$pdo->query("SELECT id FROM bus_trips WHERE bus_id=1 AND ended_at IS NULL")->fetchColumn();
    sleep(4);
    trip($tokA, 'mark_stop', ['student_id' => 12, 'status' => 'done', 'ago' => 2]);      // made offline 2 s ago
    trip($tokA, 'mark_stop', ['student_id' => 11, 'status' => 'done', 'ago' => 3600]);   // claims 1 h ago (before the trip)
    $mk = $pdo->query("SELECT s.student_id, TIMESTAMPDIFF(SECOND, s.marked_at, NOW()) AS ago, TIMESTAMPDIFF(SECOND, t.started_at, s.marked_at) AS after_start
                       FROM bus_trip_stops s JOIN bus_trips t ON t.id=s.trip_id WHERE s.trip_id=$t2 AND s.status='done' ORDER BY s.student_id")->fetchAll();
    ok((int)$mk[1]['ago'] >= 2 && (int)$mk[1]['ago'] <= 4, 'an offline mark is stored at its real (earlier) time', $mk);
    ok((int)$mk[0]['after_start'] === 0, 'a mark can never be dated before the trip started', $mk);
    trip($tokA, 'mark_stop', ['student_id' => 11, 'status' => 'done']);   // and now 11 for real, after 12
    trip($tokA, 'stop');
    $lr = $pdo->query("SELECT kind, learned_order, confidence FROM bus_route_learn WHERE bus_id=1")->fetch();
    ok($lr && json_decode($lr['learned_order']) == [12, 11] && in_array($lr['kind'], ['pickup', 'drop'], true), 'the real visit order is learned per direction', $lr);
    [, $j] = trip($tokA, 'stops');
    ok(isset($j['learned']['trips']) && $j['learned']['trips'] === 1 && $j['learned']['active'] === false, 'one trip is not enough to become the default (needs 4)', $j['learned'] ?? null);

    section('Every admin read action works on a real database');
    foreach (['get_fleet', 'get_assignments', 'get_live_locations', 'get_route_students', 'get_drivers', 'get_unassigned_routes',
              'get_trips', 'get_alert_settings', 'get_alerts', 'driver_devices'] as $act) {
        [$s, $j] = admin($A, $act);
        ok($s === 200 && !empty($j['success']), "admin: $act", $j ?: $s);
    }
    foreach (['wizard_check', 'wizard_status', 'get_bus_live_detail', 'get_bus_trail'] as $act) {
        [$s, $j] = admin($A, $act, ['bus_id' => 1]);
        ok($s === 200 && !empty($j['success']), "admin: $act (bus 1)", $j ?: $s);
    }
    [$s, $j] = admin($A, 'get_bus_live_detail', ['bus_id' => 1]);
    ok(isset($j['stops'][0]['note']) && strpos($j['stops'][0]['note'] . ($j['stops'][1]['note'] ?? ''), 'Mandir') !== false, 'admin detail carries the home note');
    $tid = (int)$pdo->query("SELECT MAX(id) FROM bus_trips WHERE bus_id=1")->fetchColumn();
    [$s, $j] = admin($A, 'get_trip', ['id' => $tid]);
    ok(!empty($j['success']), 'admin: get_trip');
    [$s, $j] = admin($B, 'get_trip', ['id' => $tid]);
    ok(empty($j['success']), "school B cannot open school A's trip by id");
    [$s, , $raw] = req('GET', '/api/bus_actions.php', ['action' => 'export_trips'], $A[0]);
    ok($s === 200 && strpos($raw, 'A-Bus') !== false && strpos($raw, 'B-Bus') === false, 'CSV export contains only own buses');
    [$s, , $raw] = req('GET', '/api/bus_actions.php', ['action' => 'export_trips'], $B[0]);
    ok(strpos($raw, 'A-Bus') === false, "school B's CSV has nothing of school A");
    [, $j] = admin($A, 'save_alert_settings', ['overspeed_kmh' => 45, 'school_lat' => '91', 'school_lng' => '77']);
    ok(empty($j['success']), 'invalid school coordinates are refused');
    [, $j] = req('GET', '/api/student_bus.php', ['action' => 'get_home'], $S11[0]);
    ok(!empty($j['success']) && strpos($j['note'], 'Mandir') === 0, 'student: get_home returns the note');

    section('Data privacy');
    $pdo->exec("INSERT INTO students (id, school_id, name, status) VALUES (14,1,'Left School',  'active'), (15,1,'No Bus Kid','active')");
    $pdo->exec("INSERT INTO student_home_locations (student_id, school_id, lat, lng, updated_at) VALUES (14,1,28.6,77.2,NOW()), (15,1,28.6,77.2,NOW())");
    $pdo->exec("INSERT INTO student_van_assignments (student_id, van_route_id, school_id) VALUES (14,1,1)");
    $pdo->exec("INSERT INTO push_subscriptions (student_id, school_id, endpoint, endpoint_hash, p256dh, auth) VALUES (14,1,'ep-14',SHA2('ep-14',256),'p','a')");
    $pdo->exec("INSERT INTO bus_absences (student_id, on_date, school_id, kind, created_at) VALUES (14, CURDATE(), 1, 'both', NOW()), (11, CURDATE() - INTERVAL 40 DAY, 1, 'both', NOW())");
    $pdo->exec("UPDATE students SET status='inactive' WHERE id=14");          // TC / admission ended
    shell_exec('php ' . escapeshellarg("$SB/tools/bus_privacy_cleanup.php"));
    $cnt = function ($t, $id) use ($pdo) { return (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE student_id=$id")->fetchColumn(); };
    ok(!$cnt('student_home_locations', 14) && !$cnt('push_subscriptions', 14) && !$cnt('bus_absences', 14), 'admission ended → home, push and notes deleted');
    ok($cnt('student_home_locations', 11) === 1 && $cnt('push_subscriptions', 11) === 1, 'active bus students are untouched');
    ok(!(int)$pdo->query("SELECT COUNT(*) FROM bus_absences WHERE on_date < CURDATE() - INTERVAL 30 DAY")->fetchColumn(), 'old absence notes are purged');
    ok($cnt('student_home_locations', 15) === 1 && $pdo->query("SELECT bus_lost_at FROM student_home_locations WHERE student_id=15")->fetchColumn(), 'no bus → kept for the grace period, clock started');
    $pdo->exec("UPDATE student_home_locations SET bus_lost_at = NOW() - INTERVAL 31 DAY WHERE student_id=15");
    shell_exec('php ' . escapeshellarg("$SB/tools/bus_privacy_cleanup.php"));
    ok($cnt('student_home_locations', 15) === 0, '…and deleted after 30 days without a bus');
    ok($cnt('student_home_locations', 21) === 1, "school B's data untouched by school A's changes");
    $S11c = login('student', 1, 11);
    [, $j] = req('POST', '/api/student_bus.php', ['action' => 'delete_my_data'], $S11c[0]);
    ok(!empty($j['success']) && !$cnt('student_home_locations', 11) && !$cnt('push_subscriptions', 11) && $cnt('student_home_locations', 12) === 1,
       'parent erases own data — only their own');

    section('Unpair / revoke');
    admin($A, 'driver_revoke', ['id' => $devA]);
    [$s] = trip($tokA, 'status');
    ok($s === 401, "after the admin removes the phone it gets 401");
    [$s, $j] = gps($dev, off(0));
    ok($s === 401, 'and cannot send GPS any more');
    admin($B, 'save_bus', ['id' => 2, 'bus_name' => 'B-Bus', 'bus_number' => 'UP32 B', 'status' => 'inactive']);
    [$s] = trip($tokB, 'status');
    ok($s === 401, 'an inactive bus locks its phones out');

    section('Watchdog');
    $pdo->exec("UPDATE school_buses SET status='active' WHERE id=2");
    $nowIst = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('H:i:s');   // school time zone
    $pdo->exec("UPDATE bus_route_assignments SET pickup_time = '$nowIst', drop_time = NULL WHERE bus_id=2");
    $pdo->exec("UPDATE bus_live SET recorded_at = NOW() - INTERVAL 20 MINUTE WHERE bus_id=2");
    shell_exec('php ' . escapeshellarg("$SB/tools/bus_watchdog_cron.php"));
    $w = $pdo->query("SELECT school_id, type FROM bus_alerts WHERE type='silent'")->fetchAll();
    ok(count($w) === 1 && (int)$w[0]['school_id'] === 2, 'silent bus → alert only in its own school feed', $w);
} finally {
    proc_terminate($srv);
}

echo "\n" . ($fail ? "\033[31m$fail failed\033[0m, " : '') . "\033[32m$pass passed\033[0m\n";
exit($fail ? 1 : 0);
