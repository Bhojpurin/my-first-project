# Scaling: 500 schools / 20 lakh students

## Measured (not estimated)
Test machine: **4 CPU cores**, nginx + PHP-FPM (40 workers) + APCu + MariaDB on the same box, load generator also on it.
Data: 50 schools, 2,000 buses, 80,000 students, every bus on a running trip (`tests/load.php`).

**Real pace** (each bus every 8 s, 500 admins every 15 s, 2,000 parents every 10 s — all at the same time):

| | req/s | p50 | p95 | p99 | errors |
|---|---|---|---|---|---|
| GPS | 250 | 5 ms | 12 ms | 19 ms | 0 |
| Admin live map | 33 | 18 ms | 33 ms | 45 ms | 0 |
| Parent poll | 200 | 4 ms | 11 ms | 19 ms | 0 |

**Flat out** (2,700 clients firing without pause): ~1,030 req/s, 0 errors, nothing hangs — requests queue and wait.

**500 school admins opening the panel at the same moment:** fine. The admin live map costs a fixed ~11 queries per refresh
(any number of buses), and the automatic database setup never makes requests wait (non-blocking lock).

### Database work per request (after optimisation, `tests/perf.php`)
| Request | before | now |
|---|---|---|
| GPS update | 19 | 3 |
| Admin live map (20 buses) | 31 + 3 per running bus | 11 |
| Parent poll | 10 | 4 (≈1 when cached) |
| Driver ETA post (40 students) | 51 | 7 |

What changed: shared cache (Redis / APCu / files) for credentials, schema version, settings, learned routes, a bus's
position for all its parents; the GPS hot path never reads the big history table; history keeps a row only every
100 m / 15 s / turn / 2 min standing (bus_live still updates every fix); batch queries for the admin map; one multi-row
insert for ETAs; notifications are sent after the phone/admin already got the answer; indexes on the panel tables.

## What 20 lakh students means
~50,000 buses, peak = the morning pickup hour.

| Traffic at peak | per second |
|---|---|
| GPS (every bus every ~8 s) | ~6,250 |
| Parents watching the map (assume 5 % of 20 lakh at once, poll every 10 s; 30 s when no trip) | ~10,000 |
| Admins (500, every 15 s) | ~35 |
| **Total** | **~16,000 req/s** |

From the measured ~250 req/s per core (with headroom) this needs roughly **60–70 PHP cores** and a database doing
roughly **60–100k simple queries/s** at peak. That is **not one server** — it is a small cluster:

| Stage | Schools / students | Setup |
|---|---|---|
| 1 | up to ~50 / 2 lakh | 1 VPS, 8 cores, 16 GB, NVMe; PHP-FPM + APCu + MySQL on it |
| 2 | up to ~200 / 8 lakh | 2–3 web servers (8 cores each) behind a load balancer + **Redis** + 1 dedicated MySQL (16 cores, 64 GB, NVMe) |
| 3 | 500 / 20 lakh | 5–6 web servers (16 cores) + Redis + MySQL primary (32 cores, 128 GB, NVMe) + 1 read replica for reports; own OSRM server |

## Settings for production
- **More than one web server ⇒ Redis is required** (`define('BUS_REDIS_HOST', '10.0.0.5');` in `config/constants.php`).
  Without it each server has its own cache: a removed phone could keep working on another server for up to 30 s.
- PHP: `php-apcu` (single server) or `php-redis`; OPcache on; PHP-FPM `pm = static`, children ≈ 2 × cores (more if DB is remote).
- MySQL: `default_time_zone = '+05:30'` (saves one query per request), `innodb_buffer_pool_size` ≈ 60–70 % of RAM,
  `innodb_flush_log_at_trx_commit = 2`, `max_connections` ≥ total PHP-FPM children of all web servers.
- After a deploy on a big database run `php tools/migrate.php` once from the command line: indexes on big panel
  tables (e.g. GPS history) are only added there, never inside a web request.
- `bus_gps_locations` (history, kept 48 h): at stage 3 consider daily partitions and drop old ones instead of DELETE.
- Road routing: the public OSRM server is not allowed for this volume — run your own (`BUS_ROUTER_URL`).
- Web Push: notifications go out after the response; at stage 3 move them to a queue worker.

## Re-run the measurements
```
php tests/integration.php                 # correctness (118 checks, two schools)
php tests/perf.php                        # queries per request
php tests/load.php --real --secs=60       # real-pace load (needs nginx + PHP-FPM, see the file header)
```
