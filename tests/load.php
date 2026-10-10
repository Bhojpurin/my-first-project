<?php
// tests/load.php — concurrent load test against a real web server (nginx + PHP-FPM) and MySQL/MariaDB.
//   BUS_TEST_DB=sszone php tests/load.php [--base=http://127.0.0.1:8200] [--secs=30] [--admins=500] [--parents=2000] [--gps=200]
// Seeds 50 schools × 40 buses × 40 students (80,000 students, 2,000 buses, all on a running trip), then runs at the
// same time: GPS from every bus, admins refreshing the live map, parents polling their bus. Prints req/s, latency
// percentiles and errors per kind. WARNING: writes a lot of test data into the test database.
if (PHP_SAPI !== 'cli') exit;
$opt = function ($k, $d) { foreach ($GLOBALS['argv'] as $a) if (strpos($a, "--$k=") === 0) return substr($a, strlen($k) + 3); return $d; };
$BASE = $opt('base', 'http://127.0.0.1:8200'); $SECS = (int)$opt('secs', 30);
$NADM = (int)$opt('admins', 500); $NPAR = (int)$opt('parents', 2000); $NGPS = (int)$opt('gps', 200);
// --real: every client at its real pace (each bus every 8 s, admin every 15 s, parent every 10 s) instead of flat out
$REAL = in_array('--real', $GLOBALS['argv'], true);
$PACE = ['gps' => 8.0, 'admin' => 15.0, 'parent' => 10.0];
$DB = getenv('BUS_TEST_DB') ?: 'sszone';
$pdo = new PDO("mysql:host=localhost;dbname=$DB;charset=utf8mb4", getenv('BUS_TEST_USER') ?: 'ss', getenv('BUS_TEST_PASS') ?: 'ss',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("SET time_zone = '+05:30'");

$SCH = range(100, 149); $BPS = 40; $SPB = 40;
if (!(int)$pdo->query("SELECT COUNT(*) FROM school_buses WHERE school_id=149")->fetchColumn()) {
    echo "Seeding 50 schools / 2000 buses / 80000 students …\n";
    foreach ($SCH as $sc) {
        $b = []; $r = []; $a = []; $st = []; $sv = []; $hm = []; $ps = []; $tr = []; $lv = [];
        for ($i = 1; $i <= $BPS; $i++) {
            $bid = $sc * 100 + $i;
            $b[] = "($bid,$sc,'Bus $i','UP $sc $i','lk$bid" . str_repeat('x', 30) . "','active')";
            $r[] = "($bid,$sc,'R$i')";
            $a[] = "($sc,$bid,$bid,1,'07:00','13:30','Mon,Tue,Wed,Thu,Fri,Sat,Sun','active')";
            $tr[] = "($sc,$bid,1,'pickup',NOW())";
            $lv[] = "($bid,$sc,28.6,77.2,20,0,NOW())";
            for ($k = 1; $k <= $SPB; $k++) {
                $sid = $bid * 100 + $k;
                $st[] = "($sid,$sc,'S$sid',1,1,'active')"; $sv[] = "($sid,$bid,$sc)";
                $hm[] = "($sid,$sc," . (28.6 + $k * 0.002) . "," . (77.2 + $i * 0.01) . ",500,1,NOW())";
                $ps[] = "($sid,$sc,'e$sid',SHA2('e$sid',256),'p','a')";
            }
        }
        $pdo->exec("INSERT INTO school_buses (id,school_id,bus_name,bus_number,gps_api_key,status) VALUES " . implode(',', $b));
        $pdo->exec("INSERT INTO van_routes (id,school_id,route_name) VALUES " . implode(',', $r));
        $pdo->exec("INSERT INTO bus_route_assignments (school_id,route_id,bus_id,shift_count,pickup_time,drop_time,days,status) VALUES " . implode(',', $a));
        $pdo->exec("INSERT INTO students (id,school_id,name,class_id,section_id,status) VALUES " . implode(',', $st));
        $pdo->exec("INSERT INTO student_van_assignments (student_id,van_route_id,school_id) VALUES " . implode(',', $sv));
        $pdo->exec("INSERT INTO student_home_locations (student_id,school_id,lat,lng,alert_radius,push_enabled,updated_at) VALUES " . implode(',', $hm));
        $pdo->exec("INSERT INTO push_subscriptions (student_id,school_id,endpoint,endpoint_hash,p256dh,auth) VALUES " . implode(',', $ps));
        $pdo->exec("INSERT INTO bus_trips (school_id,bus_id,shift_no,kind,started_at) VALUES " . implode(',', $tr));
        $pdo->exec("INSERT INTO bus_live (bus_id,school_id,lat,lng,speed,heading,recorded_at) VALUES " . implode(',', $lv) . " ON DUPLICATE KEY UPDATE recorded_at=NOW()");
    }
}
$pdo->exec("DELETE FROM bus_rate_limits");

function login(string $q): array {
    global $BASE;
    $jar = tempnam(sys_get_temp_dir(), 'lj');
    $ch = curl_init("$BASE/test_login.php?$q");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    $j = json_decode((string)curl_exec($ch), true); curl_close($ch);
    return [$jar, $j['csrf'] ?? ''];
}
echo "Logging in $NADM admins and $NPAR parents …\n";
$admins = []; for ($i = 0; $i < $NADM; $i++) $admins[] = login('as=school_admin&school=' . $SCH[$i % 50]);
$parents = []; for ($i = 0; $i < $NPAR; $i++) { $sc = $SCH[$i % 50]; $bid = $sc * 100 + 1 + intdiv($i, 50) % $BPS; $parents[] = [login("as=student&school=$sc&id=" . ($bid * 100 + 1 + $i % $SPB)), $sc, $bid]; }
$buses = []; foreach ($SCH as $sc) for ($i = 1; $i <= $BPS; $i++) $buses[] = $sc * 100 + $i;

// ── run everything at once ─────────────────────────────────────────────────────
$mh = curl_multi_init();
curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, $NADM + $NPAR + $NGPS + 50);
$stat = []; $jobs = []; $end = microtime(true) + $SECS; $gpsI = 0; $pos = [];
$next = function (string $kind, int $slot) use (&$gpsI, &$pos, $buses, $admins, $parents, $BASE) {
    $ch = curl_init();
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_PRIVATE => "$kind:$slot"];
    if ($kind === 'gps') {
        $bid = $GLOBALS['REAL'] ? $buses[$slot] : $buses[$gpsI++ % count($buses)];
        $pos[$bid] = ($pos[$bid] ?? 0) + 1;
        $o[CURLOPT_URL] = "$BASE/api/gps_update.php"; $o[CURLOPT_POST] = true;
        $o[CURLOPT_POSTFIELDS] = http_build_query(['key' => "lk$bid" . str_repeat('x', 30), 'lat' => 28.6 + $pos[$bid] * 0.0008, 'lng' => 77.2, 'speed' => 30, 'acc' => 8]);
    } elseif ($kind === 'admin') {
        [$jar, $csrf] = $admins[$slot];
        $o[CURLOPT_URL] = "$BASE/api/bus_actions.php"; $o[CURLOPT_POST] = true; $o[CURLOPT_COOKIEFILE] = $jar;
        $o[CURLOPT_POSTFIELDS] = http_build_query(['action' => 'get_live_locations', 'csrf_token' => $csrf]);
    } else {
        [[$jar], $sc, $bid] = $parents[$slot];
        $o[CURLOPT_URL] = "$BASE/api/bus_location.php?bus_id=$bid&school_id=$sc"; $o[CURLOPT_COOKIEFILE] = $jar;
    }
    curl_setopt_array($ch, $o);
    return $ch;
};
$add = function ($kind, $slot) use ($mh, $next, &$jobs) { $ch = $next($kind, $slot); $jobs[(int)$ch] = microtime(true); curl_multi_add_handle($mh, $ch); };
$due = [];   // real mode: [time, kind, slot] waiting for their next turn
if ($REAL) {
    $now = microtime(true);
    foreach ([['gps', count($buses)], ['admin', $NADM], ['parent', $NPAR]] as [$k, $n])
        for ($i = 0; $i < $n; $i++) $due[] = [$now + mt_rand(0, (int)($PACE[$k] * 1000)) / 1000, $k, $i];   // spread over one interval
} else {
    for ($i = 0; $i < $NGPS; $i++) $add('gps', $i);
    for ($i = 0; $i < $NADM; $i++) $add('admin', $i);
    for ($i = 0; $i < $NPAR; $i++) $add('parent', $i);
}
$q0 = (int)$pdo->query("SHOW GLOBAL STATUS LIKE 'Questions'")->fetch()['Value'];
$t0 = microtime(true);
echo $REAL ? "Running for {$SECS}s at REAL pace: " . count($buses) . " buses every 8 s, $NADM admins every 15 s, $NPAR parents every 10 s …\n"
           : "Running for {$SECS}s: $NGPS GPS senders, $NADM admins, $NPAR parents, all at once (flat out) …\n";
do {
    if ($REAL) {
        $now = microtime(true); $keep = [];
        foreach ($due as $d) { if ($d[0] <= $now && $now < $end) $add($d[1], $d[2]); elseif ($d[0] > $now) $keep[] = $d; }
        $due = $keep;
    }
    curl_multi_exec($mh, $running);
    curl_multi_select($mh, 0.05);
    while ($info = curl_multi_info_read($mh)) {
        $ch = $info['handle']; [$kind, $slot] = explode(':', curl_getinfo($ch, CURLINFO_PRIVATE));
        $ms = (microtime(true) - $jobs[(int)$ch]) * 1000; unset($jobs[(int)$ch]);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $body = curl_multi_getcontent($ch);
        $j = json_decode((string)$body, true);
        $good = $code === 200 && is_array($j) && (($j['ok'] ?? null) === true || ($j['success'] ?? null) === true
              || ($kind === 'gps' && preg_match('/Too frequent/', $j['msg'] ?? '')));
        $stat[$kind]['lat'][] = $ms; $stat[$kind]['n'] = ($stat[$kind]['n'] ?? 0) + 1;
        if (!$good) { $stat[$kind]['err'] = ($stat[$kind]['err'] ?? 0) + 1; $stat[$kind]['errs'][$code . ' ' . substr((string)($j['msg'] ?? $j['message'] ?? $body), 0, 60)] = 1; }
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
        if ($REAL) { $due[] = [microtime(true) + $PACE[$kind], $kind, (int)$slot]; }
        elseif (microtime(true) < $end) $add($kind, (int)$slot);
    }
} while ($running || $jobs || ($REAL && microtime(true) < $end));
$el = microtime(true) - $t0;
$qps = ((int)$pdo->query("SHOW GLOBAL STATUS LIKE 'Questions'")->fetch()['Value'] - $q0) / $el;
$pct = function (array $a, float $p) { sort($a); return $a[min(count($a) - 1, (int)floor(count($a) * $p))]; };
printf("\n%-8s %9s %9s %9s %9s %9s %8s\n", 'kind', 'requests', 'req/s', 'p50 ms', 'p95 ms', 'p99 ms', 'errors');
foreach (['gps', 'admin', 'parent'] as $k) {
    $s = $stat[$k] ?? ['n' => 0, 'lat' => [0]];
    printf("%-8s %9d %9.0f %9.0f %9.0f %9.0f %8d\n", $k, $s['n'], $s['n'] / $el, $pct($s['lat'], .5), $pct($s['lat'], .95), $pct($s['lat'], .99), $s['err'] ?? 0);
    if (!empty($s['errs'])) echo "         e.g. ", implode(' | ', array_slice(array_keys($s['errs']), 0, 3)), "\n";
}
printf("database: %.0f queries/s over %.1f s\n", $qps, $el);
