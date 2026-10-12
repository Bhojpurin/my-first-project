<?php
// api/img_proxy.php — Proxy external images to avoid CORS in the canvas editor
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['logged_in'])) { http_response_code(403); exit; }
// Release the session lock: a slow remote image (up to 8 s) used to freeze every
// other request from the same user (saving, autosave…) until it finished.
session_write_close();

$url = trim($_GET['url'] ?? '');
if (strlen($url) > 2048) { http_response_code(400); exit; }
if (!$url) { http_response_code(400); exit; }

// Only allow http/https
if (!preg_match('#^https?://#i', $url)) { http_response_code(400); exit; }

$host = parse_url($url, PHP_URL_HOST);
if (!$host) { http_response_code(400); exit; }

/**
 * Reject any IP in a private/reserved/link-local range — this must cover
 * 169.254.0.0/16 (cloud metadata services like 169.254.169.254 hand out
 * IAM credentials on AWS/GCP/Azure with zero auth — the single highest-impact
 * SSRF target, and the previous version of this file didn't block it at all)
 * plus the usual RFC1918 ranges and IPv6 equivalents.
 */
function ipIsBlocked(string $ip): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long = ip2long($ip);
        if ($long === false) return true; // unparsable — fail closed
        $ranges = [
            ['0.0.0.0', '0.255.255.255'],       // "this" network
            ['10.0.0.0', '10.255.255.255'],     // RFC1918
            ['100.64.0.0', '100.127.255.255'],  // CGNAT
            ['127.0.0.0', '127.255.255.255'],   // loopback
            ['169.254.0.0', '169.254.255.255'], // link-local / cloud metadata
            ['172.16.0.0', '172.31.255.255'],   // RFC1918
            ['192.0.0.0', '192.0.0.255'],       // IETF protocol assignments
            ['192.168.0.0', '192.168.255.255'], // RFC1918
            ['198.18.0.0', '198.19.255.255'],   // benchmarking
            ['224.0.0.0', '255.255.255.255'],   // multicast + reserved
        ];
        foreach ($ranges as [$lo, $hi]) {
            if ($long >= ip2long($lo) && $long <= ip2long($hi)) return true;
        }
        return false;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $bin = @inet_pton($ip);
        if ($bin === false) return true;
        // ::1 (loopback), ::ffff:0:0/96 (IPv4-mapped — re-check the mapped v4
        // address so e.g. ::ffff:127.0.0.1 doesn't slip past the v4 checks
        // above), fc00::/7 (unique local), fe80::/10 (link-local).
        if ($ip === '::1') return true;
        if (str_starts_with(bin2hex($bin), '00000000000000000000ffff')) {
            $mapped = long2ip(hexdec(bin2hex(substr($bin, 12, 4))));
            return ipIsBlocked($mapped);
        }
        $firstByte = ord($bin[0]);
        if (($firstByte & 0xfe) === 0xfc) return true; // fc00::/7
        if ($firstByte === 0xfe && (ord($bin[1]) & 0xc0) === 0x80) return true; // fe80::/10
        return false;
    }
    return true; // not a valid IP at all — fail closed
}

// A URL can legitimately give an IP literal as the host (no DNS involved at
// all) — treat that IP as already "resolved" rather than trying to look it
// up (gethostbyname() on an IP literal just echoes it back, which used to be
// misread here as "resolution failed" and blocked every literal-IP URL,
// public ones included).
/**
 * Resolve + validate a host, then fetch with the connection pinned to that IP
 * (CURLOPT_RESOLVE keeps the Host header / TLS SNI) — re-resolving at request
 * time is exactly the DNS-rebinding TOCTOU window. Returns [body, http code,
 * Location header]. The body is capped while downloading, not after.
 */
function resolveSafe(string $host): ?string {
    if (filter_var($host, FILTER_VALIDATE_IP)) return ipIsBlocked($host) ? null : $host;
    $ips = [];
    foreach ([DNS_A, DNS_AAAA] as $type) {
        foreach ((array)@dns_get_record($host, $type) as $r) {
            if (!empty($r['ip']))   $ips[] = $r['ip'];
            if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
        }
    }
    if (!$ips) { $g = @gethostbyname($host); if ($g !== $host) $ips[] = $g; }
    if (!$ips) return null;
    // If ANY address of the name is private, refuse (avoids round-robin tricks).
    foreach ($ips as $ip) if (ipIsBlocked($ip)) return null;
    return $ips[0];
}
function fetchPinned(string $url, string $host, string $ip): array {
    $port = parse_url($url, PHP_URL_PORT) ?: (str_starts_with(strtolower($url), 'https') ? 443 : 80);
    $isV6 = (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    $max  = 10 * 1024 * 1024;
    $buf  = '';
    $location = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => false, // redirects are followed manually below, re-validated each hop
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; SSZone-Editor/1.0)',
        CURLOPT_HTTPHEADER     => ['Accept: image/avif,image/webp,image/png,image/jpeg,image/*;q=0.8'],
        CURLOPT_RESOLVE        => [$host . ':' . $port . ':' . ($isV6 ? '[' . $ip . ']' : $ip)],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_IPRESOLVE      => $isV6 ? CURL_IPRESOLVE_V6 : CURL_IPRESOLVE_V4,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$location) {
            if (stripos($h, 'location:') === 0) $location = trim(substr($h, 9));
            return strlen($h);
        },
        // Abort as soon as the download passes 10 MB (it used to download all of it first).
        CURLOPT_WRITEFUNCTION  => function ($c, $chunk) use (&$buf, $max) {
            $buf .= $chunk;
            return strlen($buf) > $max ? 0 : strlen($chunk);
        },
    ]);
    $ok   = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($ok === false && $code === 0) return [false, 0, ''];
    return [strlen($buf) > $max ? false : $buf, $code, $location];
}

// Follow up to 3 redirects (most "copy image link" URLs redirect once — those
// always failed before), validating every hop exactly like the first one.
$data = false; $httpCode = 0;
for ($hop = 0; $hop <= 3; $hop++) {
    if (!preg_match('#^https?://#i', $url)) { http_response_code(400); exit; }
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) { http_response_code(400); exit; }
    $host = trim($host, '[]');
    $ip = resolveSafe($host);
    if (!$ip) { http_response_code(403); exit; }
    [$data, $httpCode, $loc] = fetchPinned($url, $host, $ip);
    if (in_array($httpCode, [301, 302, 303, 307, 308], true) && $loc !== '') {
        // Resolve a relative Location against the current URL.
        if (!preg_match('#^https?://#i', $loc)) {
            $p = parse_url($url);
            $base = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
            $loc = str_starts_with($loc, '/') ? $base . $loc : $base . rtrim(dirname($p['path'] ?? '/'), '/') . '/' . $loc;
        }
        $url = $loc;
        continue;
    }
    break;
}

if ($data === false || $httpCode !== 200 || strlen($data) < 10) {
    http_response_code(502); exit;
}

// Detect MIME from magic bytes — SVG blocked (can contain XSS scripts)
$mime = '';
if     (str_starts_with($data, "\x89PNG"))  $mime = 'image/png';
elseif (str_starts_with($data, "GIF8"))     $mime = 'image/gif';
elseif (str_starts_with($data, "\xFF\xD8")) $mime = 'image/jpeg';
elseif (str_starts_with($data, "RIFF") && substr($data, 8, 4) === 'WEBP') $mime = 'image/webp';

// Only serve raster images — reject SVG and any non-image content
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
    http_response_code(400); exit;
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
// Same-origin only (the editor is on this site); a wildcard CORS header on a
// logged-in proxy was unnecessary. Private: the response is per-user.
header('Cache-Control: private, max-age=3600');
echo $data;
