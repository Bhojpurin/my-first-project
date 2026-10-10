<?php
// tests/perf.php — how much database work does each request do? (run after tests/integration.php built the sandbox)
//   BUS_TEST_DB=sszone php tests/perf.php
// Seeds one big school (20 buses, 800 students with homes), then measures DB queries + time per request type.
// WARNING: resets the test database's bus data.
if (PHP_SAPI !== 'cli') exit;
$DB = getenv('BUS_TEST_DB') ?: 'sszone';
$SB = sys_get_temp_dir() . '/bus_sandbox';
$PORT = 8160; $BASE = "http://127.0.0.1:$PORT";
if (!is_dir($SB)) exit("run tests/integration.php first (it builds the sandbox)\n");
exec('cp -r ' . escapeshellarg(dirname(__DIR__) . '/api') . '/. ' . escapeshellarg("$SB/api"));
exec('cp -r ' . escapeshellarg(dirname(__DIR__) . '/includes') . '/. ' . escapeshellarg("$SB/includes"));
$pdo = new PDO("mysql:host=localhost;dbname=$DB;charset=utf8mb4", getenv('BUS_TEST_USER') ?: 'ss', getenv('BUS_TEST_PASS') ?: 'ss',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("SET time_zone = '+05:30'");
$S = 9; // school id
foreach (['school_buses', 'van_routes', 'bus_route_assignments', 'students', 'student_van_assignments', 'student_home_locations', 'push_subscriptions'] as $t)
    $pdo->exec("DELETE FROM $t WHERE school_id=$S");
$pdo->exec("DELETE FROM bus_rate_limits");
for ($b = 1; $b <= 20; $b++) {
    $bid = 900 + $b;
    $pdo->exec("INSERT INTO school_buses (id, school_id, bus_name, bus_number, gps_api_key, status) VALUES ($bid, $S, 'Bus $b', 'UP $b', 'perfkey$bid" . str_repeat('0', 30) . "', 'active')");
    $pdo->exec("INSERT INTO van_routes (id, school_id, route_name) VALUES ($bid, $S, 'R$b')");
    $pdo->exec("INSERT INTO bus_route_assignments (school_id, route_id, bus_id, shift_count, pickup_time, drop_time, days, status) VALUES ($S,$bid,$bid,2,'07:00','13:30','Mon,Tue,Wed,Thu,Fri,Sat,Sun','active')");
    $vals = []; $sva = []; $home = []; $push = [];
    for ($k = 1; $k <= 40; $k++) {
        $sid = 90000 + $b * 100 + $k;
        $vals[] = "($sid, $S, 'Student $sid', 1, 1, 'active')";
        $sva[] = "($sid, $bid, $S)";
        $home[] = "($sid, $S, " . (28.6 + $k * 0.002) . ", " . (77.2 + $b * 0.01) . ", 500, 1, NOW())";
        $push[] = "($sid, $S, 'ep$sid', SHA2('ep$sid',256), 'p', 'a')";
    }
    $pdo->exec("INSERT INTO students (id, school_id, name, class_id, section_id, status) VALUES " . implode(',', $vals));
    $pdo->exec("INSERT INTO student_van_assignments (student_id, van_route_id, school_id) VALUES " . implode(',', $sva));
    $pdo->exec("INSERT INTO student_home_locations (student_id, school_id, lat, lng, alert_radius, push_enabled, updated_at) VALUES " . implode(',', $home));
    $pdo->exec("INSERT INTO push_subscriptions (student_id, school_id, endpoint, endpoint_hash, p256dh, auth) VALUES " . implode(',', $push));
}
$srv = proc_open(['php', '-d', 'apc.enabled=0', '-S', "127.0.0.1:$PORT", '-t', $SB], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pp, null, ['BUS_TEST_PUSHLOG' => '/dev/null'] + getenv());
usleep(800000);
function req(string $method, string $path, array $data = [], ?string $jar = null, array $hdr = []): array {
    global $BASE; $ch = curl_init(); $url = $BASE . $path;
    if ($method === 'GET' && $data) $url .= '?' . http_build_query($data);
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => array_map(function ($k, $v) { return "$k: $v"; }, array_keys($hdr), $hdr)]);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); }
    if ($jar) { curl_setopt($ch, CURLOPT_COOKIEJAR, $jar); curl_setopt($ch, CURLOPT_COOKIEFILE, $jar); }
    $raw = curl_exec($ch); curl_close($ch); return json_decode((string)$raw, true) ?: [];
}
function questions(PDO $pdo): int { return (int)$pdo->query("SHOW GLOBAL STATUS LIKE 'Questions'")->fetch()['Value']; }
function measure(string $name, callable $fn, int $n = 5): void {
    global $pdo;
    $fn(); // warm-up
    $q0 = questions($pdo); $t0 = microtime(true);
    for ($i = 0; $i < $n; $i++) $fn();
    $q = (questions($pdo) - $q0 - 1) / $n; $ms = (microtime(true) - $t0) * 1000 / $n;
    printf("  %-44s %6.1f queries  %7.1f ms\n", $name, $q, $ms);
}
try {
    $jar = tempnam(sys_get_temp_dir(), 'j');
    $csrf = req('GET', '/test_login.php', ['as' => 'school_admin', 'school' => $S], $jar)['csrf'];
    $code = req('POST', '/api/bus_actions.php', ['action' => 'driver_pair_link', 'bus_id' => 901, 'csrf_token' => $csrf], $jar)['code'] ?? '';
    $tok = req('POST', '/api/bus_trip.php', ['action' => 'pair', 'code' => $code])['token'] ?? '';
    if (!$tok) throw new Exception('pairing failed');
    for ($b = 901; $b <= 920; $b++) $pdo->exec("INSERT INTO bus_live (bus_id, school_id, lat, lng, speed, heading, recorded_at) VALUES ($b,$S,28.6,77.2,20,0,NOW()) ON DUPLICATE KEY UPDATE recorded_at=NOW()");
    $H = ['X-Device-Token' => $tok];
    req('POST', '/api/bus_trip.php', ['action' => 'start', 'shift' => 1], null, $H);
    $sjar = tempnam(sys_get_temp_dir(), 'j');
    req('GET', '/test_login.php', ['as' => 'student', 'school' => $S, 'id' => 90101], $sjar);
    $lat = 28.60;
    echo "Per request (one school: 20 buses, 800 students):\n";
    measure('GPS update from a moving bus', function () use (&$lat, $H) { $lat += 0.0004; usleep(2100000); req('POST', '/api/gps_update.php', ['lat' => $lat, 'lng' => 77.21, 'speed' => 30, 'acc' => 8], null, $H); }, 3);
    measure('GPS update, rejected (too frequent)', function () use ($lat, $H) { req('POST', '/api/gps_update.php', ['lat' => $lat, 'lng' => 77.21, 'speed' => 30, 'acc' => 8], null, $H); });
    measure('Admin live map refresh (20 buses)', function () use ($jar, $csrf) { req('POST', '/api/bus_actions.php', ['action' => 'get_live_locations', 'csrf_token' => $csrf], $jar); });
    measure('Parent bus poll (bus_location)', function () use ($sjar, $S) { req('GET', '/api/bus_location.php', ['bus_id' => 901, 'school_id' => $S], $sjar); });
    measure('Driver phone: stops list', function () use ($H) { req('POST', '/api/bus_trip.php', ['action' => 'stops'], null, $H); });
    measure('Driver phone: ETA post (40 students)', function () use ($H) {
        $e = []; for ($k = 1; $k <= 40; $k++) $e[] = (90100 + $k) . ':' . (600 + $k * 30); req('POST', '/api/bus_trip.php', ['action' => 'eta', 'etas' => implode(',', $e)], null, $H); });
} finally { proc_terminate($srv); }
