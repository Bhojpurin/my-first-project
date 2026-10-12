<?php
// api/img_proxy.php — Proxy external images to avoid CORS in the canvas editor
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['logged_in'])) { http_response_code(403); exit; }

$url = trim($_GET['url'] ?? '');
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
if (filter_var($host, FILTER_VALIDATE_IP)) {
    $resolvedIp = $host;
} else {
    $resolvedIp = null;
    foreach ([DNS_A, DNS_AAAA] as $type) {
        $records = @dns_get_record($host, $type);
        if ($records) { $resolvedIp = $records[0][$type === DNS_A ? 'ip' : 'ipv6']; break; }
    }
    if (!$resolvedIp) {
        $gbn = @gethostbyname($host);
        $resolvedIp = ($gbn !== $host) ? $gbn : null; // gethostbyname() returns the input unchanged on failure
    }
}
if (!$resolvedIp) { http_response_code(400); exit; } // resolution failed
if (ipIsBlocked($resolvedIp)) { http_response_code(403); exit; }

// Fetch by the IP we just validated (CURLOPT_RESOLVE pins the connection to
// it while keeping the original Host header / TLS SNI) — a plain
// file_get_contents($url) would re-resolve $host itself at request time,
// which is exactly the TOCTOU window a DNS-rebinding attack needs: pass the
// check with a public IP on the first lookup, then have the real fetch
// resolve to 127.0.0.1 or the cloud metadata address on the second one.
$port = parse_url($url, PHP_URL_PORT) ?: (str_starts_with(strtolower($url), 'https') ? 443 : 80);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_FOLLOWLOCATION => false, // no redirects — a redirect could point anywhere, re-opening the SSRF window
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; SSZone-Editor/1.0)',
    CURLOPT_HTTPHEADER     => ['Accept: image/*'],
    CURLOPT_RESOLVE        => [$host . ':' . $port . ':' . $resolvedIp],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    CURLOPT_BUFFERSIZE     => 65536,
]);
$data = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($data === false || $httpCode !== 200 || strlen($data) < 10 || strlen($data) > 10 * 1024 * 1024) {
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
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=3600');
echo $data;
