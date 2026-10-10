<?php
// includes/bus_cache.php — tiny shared cache for hot, rarely-changing data (schema version, school alert settings,
// learned paths, "is this student allowed to see this bus", a bus's latest position for its parents …).
//
// Backends, first available wins:
//   1. Redis   — define('BUS_REDIS_HOST', '10.0.0.5') (+ optional BUS_REDIS_PORT / BUS_REDIS_PASS) in config/constants.php.
//                REQUIRED when the panel runs on more than one web server (shared cache: a removed phone stops
//                everywhere at once, rate limits count across servers).
//   2. APCu    — one web server (`apt install php-apcu`), shared by all PHP-FPM workers.
//   3. Files   — small files in the system temp folder (XAMPP / shared hosting).
// Values are plain PHP data.
// Keys are always built from ids (bus / school / student), so cached data of one school never answers another.

function busCacheDir(): string
{
    static $d = null;
    if ($d === null) {
        $d = rtrim(sys_get_temp_dir(), '/\\') . '/sszone_bus_cache_' . substr(md5(__DIR__), 0, 8);
        if (!is_dir($d)) @mkdir($d, 0700, true);
    }
    return $d;
}

/** Shared Redis connection (or null). */
function busRedis(): ?Redis
{
    static $r = false;
    if ($r !== false) return $r;
    $r = null;
    if (defined('BUS_REDIS_HOST') && BUS_REDIS_HOST && class_exists('Redis')) {
        try {
            $x = new Redis();
            if ($x->connect((string)BUS_REDIS_HOST, defined('BUS_REDIS_PORT') ? (int)BUS_REDIS_PORT : 6379, 1.0)) {
                if (defined('BUS_REDIS_PASS') && BUS_REDIS_PASS) $x->auth((string)BUS_REDIS_PASS);
                $r = $x;
            }
        } catch (\Throwable $e) { error_log('bus_cache redis: ' . $e->getMessage()); }
    }
    return $r;
}

function busApcu(): bool { static $a = null; return $a ?? ($a = function_exists('apcu_fetch') && ini_get('apc.enabled') && (PHP_SAPI !== 'cli' || ini_get('apc.enable_cli'))); }

function busCacheGet(string $key, &$hit = null)
{
    $hit = false;
    if ($rd = busRedis()) {
        try { $raw = $rd->get('bus:' . $key); } catch (\Throwable $e) { $raw = false; }
        if ($raw === false) return null;
        $hit = true;
        return @unserialize($raw, ['allowed_classes' => false]);
    }
    if (busApcu()) {
        $v = apcu_fetch('bus:' . $key, $hit);
        return $hit ? $v : null;
    }
    $f = busCacheDir() . '/' . md5($key);
    $raw = @file_get_contents($f);
    if ($raw === false) return null;
    $d = @unserialize($raw, ['allowed_classes' => false]);
    if (!is_array($d) || $d['e'] < time()) return null;
    $hit = true;
    return $d['v'];
}

function busCacheSet(string $key, $value, int $ttl): void
{
    if ($rd = busRedis()) { try { $rd->setex('bus:' . $key, max(1, $ttl), serialize($value)); } catch (\Throwable $e) {} return; }
    if (busApcu()) { apcu_store('bus:' . $key, $value, $ttl); return; }
    $f = busCacheDir() . '/' . md5($key);
    $tmp = $f . '.' . getmypid() . mt_rand();
    if (@file_put_contents($tmp, serialize(['e' => time() + $ttl, 'v' => $value])) !== false) @rename($tmp, $f);
}

function busCacheDel(string $key): void
{
    if ($rd = busRedis()) { try { $rd->del('bus:' . $key); } catch (\Throwable $e) {} return; }
    if (busApcu()) { apcu_delete('bus:' . $key); return; }
    @unlink(busCacheDir() . '/' . md5($key));
}

/** get-or-compute */
function busCached(string $key, int $ttl, callable $fn)
{
    $v = busCacheGet($key, $hit);
    if ($hit) return $v;
    $v = $fn();
    busCacheSet($key, $v, $ttl);
    return $v;
}

/** Send the response now and keep working (notifications etc.) — the phone / browser does not wait. */
function busFinishResponse(): void
{
    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); return; }
    if (function_exists('litespeed_finish_request')) { litespeed_finish_request(); return; }
    @ob_flush(); @flush();
}
