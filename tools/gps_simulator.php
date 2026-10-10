<?php
// tools/gps_simulator.php — fake bus that drives a route and posts GPS fixes to api/gps_update.php.
// Lets you test the whole flow (live map, trail, proximity push, offline back-fill, glitch filter)
// without a real bus or phone.
//
//   php tools/gps_simulator.php --url=http://localhost:8000/api/gps_update.php --key=BUS_API_KEY \
//        --home=28.6150,77.2100 [--scenario=normal] [--speed=30] [--interval=3] [--speedup=1]
//
// Route: by default the bus starts ~2 km from --home and drives to it along a slightly curved road,
// so the proximity alert (default radius 500 m) fires on the way. Use --route=file.json
// ([[lat,lng],...] or GeoJSON LineString) to drive a real road instead.
//
// Scenarios:
//   normal   plain drive
//   stopgo   traffic: stops for ~20 s every ~400 m
//   offline  loses internet for the middle part, then back-fills it with age= (tests the offline buffer)
//   glitch   injects a 40 km teleport + a 300 m-accuracy fix (both must be rejected by the server)
//   fast     bursts faster than the server's 2 s limit (expects "Too frequent")
//   badkey   uses a wrong key (expects "Invalid or inactive bus key")
//
// Options: --dry-run prints fixes without sending · --loop repeats the route · --no-color
// --speedup=N plays N× faster (the *clock* is compressed, so use it only with --dry-run or tolerant servers).

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const EARTH_R = 6371000.0;

function opt(string $k, $def = null) {
    global $argv;
    foreach ($argv as $a) {
        if ($a === "--$k") return true;
        if (strpos($a, "--$k=") === 0) return substr($a, strlen($k) + 3);
    }
    return $def;
}
function c(string $s, string $code): string { return opt('no-color') ? $s : "\033[{$code}m$s\033[0m"; }

function hav(array $a, array $b): float {
    $r = M_PI / 180; $dp = ($b[0] - $a[0]) * $r; $dl = ($b[1] - $a[1]) * $r;
    $h = sin($dp / 2) ** 2 + cos($a[0] * $r) * cos($b[0] * $r) * sin($dl / 2) ** 2;
    return 2 * EARTH_R * asin(min(1.0, sqrt($h)));
}
function bearing(array $a, array $b): float {
    $r = M_PI / 180; $y = sin(($b[1] - $a[1]) * $r) * cos($b[0] * $r);
    $x = cos($a[0] * $r) * sin($b[0] * $r) - sin($a[0] * $r) * cos($b[0] * $r) * cos(($b[1] - $a[1]) * $r);
    return fmod(atan2($y, $x) / $r + 360, 360);
}
function dest(array $p, float $bearingDeg, float $m): array {
    $r = M_PI / 180; $d = $m / EARTH_R; $br = $bearingDeg * $r; $la = $p[0] * $r; $lo = $p[1] * $r;
    $la2 = asin(sin($la) * cos($d) + cos($la) * sin($d) * cos($br));
    $lo2 = $lo + atan2(sin($br) * sin($d) * cos($la), cos($d) - sin($la) * sin($la2));
    return [$la2 / $r, $lo2 / $r];
}

/** Built-in demo road: starts $lengthM metres west-south-west of $home and ends at $home, gently curved. */
function demoRoute(array $home, float $lengthM = 2200, float $stepM = 25): array {
    $start = dest($home, 235, $lengthM);
    $pts = [];
    $n = (int)ceil($lengthM / $stepM);
    for ($i = 0; $i <= $n; $i++) {
        $t = $i / $n;
        $base = [$start[0] + ($home[0] - $start[0]) * $t, $start[1] + ($home[1] - $start[1]) * $t];
        $pts[] = dest($base, 90, sin($t * M_PI * 2) * 120);   // sideways wiggle (up to 120 m)
    }
    $pts[count($pts) - 1] = $home;
    return $pts;
}

function loadRoute(string $file): array {
    $j = json_decode((string)@file_get_contents($file), true);
    if (!is_array($j)) fail("Cannot read route file: $file");
    if (isset($j['features'][0]['geometry']['coordinates'])) $j = $j['features'][0]['geometry']['coordinates'] ?? [];
    elseif (isset($j['coordinates'])) $j = $j['coordinates'];
    elseif (isset($j['geometry']['coordinates'])) $j = $j['geometry']['coordinates'];
    $pts = [];
    foreach ($j as $p) {
        if (!is_array($p) || count($p) < 2) continue;
        // GeoJSON is [lng,lat]; a plain list is [lat,lng]. Decide by value range (India: lat<38, lng>60).
        $pts[] = (abs($p[0]) > abs($p[1]) && abs($p[1]) <= 90 && $p[0] > 60 || abs($p[0]) > 90) ? [(float)$p[1], (float)$p[0]] : [(float)$p[0], (float)$p[1]];
    }
    if (count($pts) < 2) fail('Route needs at least 2 points.');
    return $pts;
}

/** Densify a polyline so consecutive points are at most $stepM apart. */
function densify(array $pts, float $stepM = 25): array {
    $out = [$pts[0]];
    for ($i = 1; $i < count($pts); $i++) {
        $d = hav($pts[$i - 1], $pts[$i]);
        $k = max(1, (int)ceil($d / $stepM));
        for ($j = 1; $j <= $k; $j++) {
            $t = $j / $k;
            $out[] = [$pts[$i - 1][0] + ($pts[$i][0] - $pts[$i - 1][0]) * $t, $pts[$i - 1][1] + ($pts[$i][1] - $pts[$i - 1][1]) * $t];
        }
    }
    return $out;
}

function fail(string $m): void { fwrite(STDERR, c("✖ $m\n", '31')); exit(1); }

/** POST one fix. Returns [httpOk(bool), decodedJson|null, rawError|null]. */
function send(string $url, array $fix, string $key): array {
    $body = http_build_query($fix + ['key' => $key]);
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 12, 'ignore_errors' => true,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n",
        'content' => $body,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return [false, null, 'network error'];
    $j = json_decode($raw, true);
    return [is_array($j), $j, is_array($j) ? null : 'bad response: ' . substr($raw, 0, 120)];
}

// ─── setup ───────────────────────────────────────────────────────────────────
if (opt('help') || opt('h')) {
    $self = file(__FILE__); foreach (array_slice($self, 1, 28) as $l) { if (strpos($l, '//') !== 0) break; echo substr($l, 3); }
    exit(0);
}

$url      = (string)opt('url', 'http://localhost:8000/api/gps_update.php');
$key      = (string)opt('key', '');
$dry      = (bool)opt('dry-run');
$scenario = (string)opt('scenario', 'normal');
$speedKmh = max(5.0, (float)opt('speed', 30));
$interval = max(1.0, (float)opt('interval', 3));
$speedup  = max(1.0, (float)opt('speedup', 1));
$loop     = (bool)opt('loop');
$homeArg  = (string)opt('home', '28.6139,77.2090');   // default: New Delhi
$home     = array_map('floatval', explode(',', $homeArg));
if (count($home) !== 2) fail('--home must be "lat,lng"');
if (!in_array($scenario, ['normal', 'stopgo', 'offline', 'glitch', 'fast', 'badkey'], true)) fail("Unknown scenario: $scenario");
if (!$dry && $key === '') fail('--key=BUS_API_KEY is required (or use --dry-run).');
if ($scenario === 'badkey') $key = 'wrong-key-' . bin2hex(random_bytes(4));

$route = opt('route') ? loadRoute((string)opt('route')) : demoRoute($home);
$route = densify($route);
$total = 0.0; for ($i = 1; $i < count($route); $i++) $total += hav($route[$i - 1], $route[$i]);

echo c("🚌 GPS simulator", '1;36'), " · scenario ", c($scenario, '1'), " · ", count($route), " pts · ", round($total / 1000, 2), " km @ ", $speedKmh, " km/h\n";
echo $dry ? c("   dry-run: nothing is sent\n", '33') : "   → $url\n";

// Pre-flight: validate the key (same call driver_tracker.php makes)
if (!$dry && $scenario !== 'badkey') {
    [$ok, $j, $err] = send($url . (strpos($url, '?') === false ? '?' : '&') . 'info=1', [], $key);
    if (!$ok || empty($j['ok'])) fail('Key check failed: ' . ($err ?: ($j['msg'] ?? 'unknown')));
    echo "   bus: ", c(($j['bus_name'] ?? '?') . ' ' . ($j['bus_number'] ?? ''), '1'), "\n";
}

// ─── drive ───────────────────────────────────────────────────────────────────
$stats = ['sent' => 0, 'ok' => 0, 'rejected' => 0, 'failed' => 0, 'backfilled' => 0];
$buffer = [];   // fixes taken while "offline": [fix, takenAtUnix]

$run = function () use (&$stats, &$buffer, $route, $url, $key, $dry, $scenario, $speedKmh, $interval, $speedup, $home) {
    $pos = $route[0]; $idx = 1; $t = 0.0; $sinceStop = 0.0; $stopLeft = 0.0; $n = 0;
    $mps = $speedKmh / 3.6; $lastHdg = bearing($route[0], $route[1]);
    $offFrom = $scenario === 'offline' ? 0.35 : 2; $offTo = $scenario === 'offline' ? 0.65 : 2; // fraction of route
    $totalLen = 0; for ($i = 1; $i < count($route); $i++) $totalLen += hav($route[$i - 1], $route[$i]);
    $travelled = 0.0;

    while ($idx < count($route)) {
        // advance $mps * $interval metres along the route
        $step = $stopLeft > 0 ? 0.0 : $mps * $interval;
        if ($stopLeft > 0) $stopLeft -= $interval;
        while ($step > 0 && $idx < count($route)) {
            $d = hav($pos, $route[$idx]);
            if ($d <= $step) { $step -= $d; $travelled += $d; $pos = $route[$idx]; $idx++; }
            else { $hdg = bearing($pos, $route[$idx]); $pos = dest($pos, $hdg, $step); $travelled += $step; $step = 0; }
        }
        if ($idx < count($route)) $lastHdg = bearing($pos, $route[min($idx, count($route) - 1)]);
        $moving = $stopLeft <= 0;
        if ($scenario === 'stopgo' && $moving) { $sinceStop += $mps * $interval; if ($sinceStop > 400) { $sinceStop = 0; $stopLeft = 20; } }

        // GPS noise: ±4 m, accuracy 6-14 m
        $noisy = dest($pos, mt_rand(0, 359), mt_rand(0, 40) / 10);
        $fix = [
            'lat' => round($noisy[0], 6), 'lng' => round($noisy[1], 6),
            'speed' => round($moving ? $mps + mt_rand(-10, 10) / 10 : 0, 2), 'su' => 'ms',
            'heading' => round($lastHdg, 1), 'acc' => mt_rand(6, 14),
        ];
        $frac = $totalLen > 0 ? $travelled / $totalLen : 1;
        $n++;
        $distHome = hav($pos, $home);
        $line = sprintf('#%03d %5.1f%%  %s,%s  %3d km/h  home %5.0f m', $n, $frac * 100, $fix['lat'], $fix['lng'], round($fix['speed'] * 3.6), $distHome);

        $isOffline = $frac >= $offFrom && $frac < $offTo;
        if ($isOffline) {
            $buffer[] = [$fix, time()];
            echo c("$line  📴 offline (buffered " . count($buffer) . ")\n", '33');
        } else {
            // coming back online: flush the buffer with age=
            while ($buffer) {
                [$bf, $ts] = array_shift($buffer);
                $age = max(21, time() - $ts);
                $r = $dry ? [true, ['ok' => true, 'saved' => 'backfilled(dry)'], null] : send($url, $bf + ['age' => $age], $key);
                $stats['sent']++; $stats['backfilled']++;
                echo c("      ↺ back-fill age=" . $age . "s → " . ($r[1]['saved'] ?? $r[1]['msg'] ?? $r[2]) . "\n", '36');
                if (!$dry) usleep(150000);
            }
            $extra = [];
            if ($scenario === 'glitch' && $n % 12 === 5) { $tp = dest($pos, 45, 40000); $fix['lat'] = round($tp[0], 6); $fix['lng'] = round($tp[1], 6); $line .= '  ⚡teleport'; }
            elseif ($scenario === 'glitch' && $n % 12 === 9) { $fix['acc'] = 300; $line .= '  ⚡acc=300'; }
            $r = $dry ? [true, ['ok' => true, 'saved' => 'dry'], null] : send($url, $fix, $key);
            $stats['sent']++;
            if (!$r[0]) { $stats['failed']++; echo c("$line  ✖ {$r[2]}\n", '31'); }
            elseif (!empty($r[1]['ok'])) { $stats['ok']++; echo c("$line  ✔ ", '32'), $r[1]['saved'] ?? 'ok', "\n"; }
            else { $stats['rejected']++; echo c("$line  ⚠ " . ($r[1]['msg'] ?? '?') . "\n", '35'); }
            if ($scenario === 'fast') { // second hit 0.3 s later — server must answer "Too frequent"
                usleep(300000);
                $r = $dry ? [true, ['ok' => false, 'msg' => 'dry'], null] : send($url, $fix, $key); $stats['sent']++;
                if (!empty($r[1]['ok'])) $stats['ok']++; else $stats['rejected']++;
                echo c("      ⏱ burst → " . ($r[1]['msg'] ?? ($r[1]['saved'] ?? $r[2])) . "\n", '90');
            }
        }
        if (!$dry) usleep((int)($interval / $speedup * 1e6)); else usleep((int)(30000));
    }
};

do { $run(); if ($loop) echo c("— route finished, restarting —\n", '1'); } while ($loop);

echo "\n", c('Summary', '1;36'), ": sent {$stats['sent']} · ok {$stats['ok']} · rejected {$stats['rejected']} · failed {$stats['failed']} · back-filled {$stats['backfilled']}\n";
$expect = [
    'normal'  => 'expect: all ✔; proximity push fires once when ≤ radius from --home',
    'stopgo'  => 'expect: "refreshed" while stopped, speed 0, no extra alerts',
    'offline' => 'expect: gap on the map, then back-filled points appear in the trail',
    'glitch'  => 'expect: "Implausible jump ignored" and "Low GPS accuracy" rejections',
    'fast'    => 'expect: every burst answered with "Too frequent"',
    'badkey'  => 'expect: every request rejected with "Invalid or inactive bus key"',
][$scenario];
echo "   $expect\n";
exit($stats['failed'] > 0 ? 2 : 0);
