# School Bus Tracker

PHP + MySQL school bus tracking: GPS in (driver phone link / GPSLogger / hardware device), live admin map,
shifts, student portal, push alerts when the bus is near home.

| Path | Purpose |
|---|---|
| `api/gps_update.php` | GPS ingestion (per-bus API key) |
| `api/driver_tracker.php` | browser GPS sender for the driver |
| `api/bus_actions.php` | admin API (fleet, routes, live map, trails) |
| `api/bus_location.php`, `api/student_bus.php` | student portal APIs |
| `includes/bus_proximity.php` | "bus is near" push logic |
| `includes/bus_watchdog.php` | **new** silent-bus alerts |
| `admin/bus_wizard.php` | **new** Bus Setup Wizard + per-bus health check (included by `bus_tracker.php`) |
| `admin/bus_tracker.php`, `student/index.php`, `student/sw.js` | UI (paths assumed — adjust if yours differ) |
| `tools/gps_simulator.php` | **new** fake bus for testing |
| `tools/bus_watchdog_cron.php` | **new** run every minute |
| `includes/bus_security.php` | **new** pairing, device tokens, rate limits |
| `includes/bus_alerts.php`, `includes/bus_notify.php` | **new** safety alerts, own-channel notifications |
| `api/driver_sw.js` | **new** offline cache for the driver page |
| `tests/` | **new** integration tests (real DB, two schools) |
| `api/bus_trip.php`, `includes/bus_trips.php` | **new** trip start/stop, "bus nikal gayi" push, trip summary |
| `database/bus_tracking.sql` | **new** tables: `bus_live`, `bus_watchdog_alerts`, `bus_trips` |

`config/` (db.php, constants.php), `includes/session.php`, `functions.php`, `push_sender.php` and `student/stu_guard.php`
are not in this repo yet.

## Upgrade steps
1. Run `php tools/migrate.php` (XAMPP: `C:\xampp\php\php.exe tools\migrate.php`). It runs `database/bus_tracking.sql`
   and adds new columns to tables created by older versions. Safe to run again after every update.
2. Cron: `* * * * * php /path/to/tools/bus_watchdog_cron.php`

## Trips
Driver page: **Trip Shuru** (starts tracking + pushes "bus nikal gayi" to that shift's students) → **Trip Khatam**
(stores km, max speed, stops, path). Admin → **Trips** tab: filter by date/bus, map of the route, CSV export.
Forgotten trips are closed automatically by the cron job. To send "Bus nearby" alerts only during a real trip,
set `BUS_ALERT_REQUIRE_TRIP = true` in `includes/bus_proximity.php` (off by default so nothing stops working).

## Driver page: shift + student stops
`api/driver_tracker.php?key=...`: the driver ticks a shift → the map shows every student of that shift who marked their home
(student portal → Bus tab), numbered in a planned order (nearest-neighbour + 2-opt from the bus). Next-stop card with distance,
ETA and Google Maps directions; voice + vibration at 300 m and on arrival; ✔ picked up / ✖ did not come (auto ✔ after the bus
stood ≥ 8 s at the stop and drove on). Marks work offline and survive reloads. They show up in the student portal
("2 stops before yours" / "marked done at 07:42") and in the admin Trips report (Students ✔/✖).
Privacy: the link has no login, so the driver sees short names ("Rahul K.") + class only (`BUS_DRIVER_FULL_NAMES` in
`includes/bus_trips.php`). The page sends `Referrer-Policy: no-referrer` so the key never leaks to map tiles / Google Maps.

## Real roads, learning, offline (driver page)
- **Road routing:** the visiting order and the line on the map come from OSRM (real roads; `router.project-osrm.org` by
  default — for many buses set `define('BUS_ROUTER_URL', 'https://your-osrm');` in `config/constants.php`, or `''` to switch
  it off). Leaving the planned road for 3 fixes re-routes from the current position. Distance / ETA to the next stop are
  measured along the road. Without internet: the last saved road route, else straight lines.
- **Learning:** when a trip ends, the order in which the students were really marked ✔ and the road the bus really drove
  are stored per bus + shift + direction (morning pickup and afternoon drop are learned separately). After 4 trips that
  agree ≥ 60 %, that order becomes the default on the driver's phone and its path is drawn as "roz ka raasta". New students
  are inserted where they add the least distance. Admin can reset it (Live Map → bus → "Seekha hua raasta hatayein").
- **Offline:** `api/driver_sw.js` keeps the page, Leaflet and every map tile the driver has seen (max ~3000, refreshed after
  14 days). Students list, route and learned path are kept on the phone too. GPS points and ✔/✖ marks wait offline and are
  sent with their real time; marks work even before the server confirmed the trip.
- **Admin Live Map:** every bus shows the running shift, ✔ picked / ✖ absent / ⏳ remaining, next stop and "⏸ stopped for
  N min". Clicking a bus shows each student on the map with status and time, where the bus stood (2+ min) today, today's
  path and the learned route.

## Security (multi-school panel)
- **Every query is scoped to the caller's school.** Admin/teacher APIs take `school_id` only from the session; the driver
  phone's school comes only from its device token; GPS devices' school only from their key. `tests/integration.php` checks
  this with two schools over real HTTP (fleet, live map, trips, CSV, alerts, devices, student portal, driver phone).
- **No key in any link.** Driver phones are paired with a one-time link `…/api/driver_tracker.php#p=CODE` (24 h, single use;
  a newer link kills older unused ones). The code is after `#`, so it never reaches server logs or referrers; it is removed
  from the address bar immediately. The phone gets its own 256-bit token (stored hashed); admin sees every paired phone
  (Driver Links) and can remove it — the phone then wipes all bus data. Old `?key=` links show "link band ho chuka hai".
- **The bus API key is write-only** (GPS hardware / GPSLogger): it can add positions but never read students or homes.
- **Rate limits:** 20 wrong GPS keys / 10 min / IP, 10 wrong pairing codes / 15 min / IP, 30 bad tokens / 15 min / IP
  (then 429), 60 GPS requests / min / bus, 240 trip calls / min / phone.
- **Headers:** driver page has a strict Content-Security-Policy, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`
  (map tiles get only the site origin); JSON APIs send `nosniff`; `bus_trip.php` is POST-only, same-origin, rejects
  cross-site requests. Admin writes need the session's CSRF token; teachers are read-only.
- **No third-party messaging / webhooks:** the old global webhook was removed (it would have mixed schools' alerts).

## Alerts & parent messages (own channels only)
`includes/bus_notify.php` sends to parents by Web Push **and** the panel's own Messages (create
`includes/bus_message_hook.php` from the `.example` file to connect your messages table). Admin alerts go to the Live Map feed
(sound + desktop notification while open). Per-school settings: Live Map → **Alert settings**.
| Event | Admin | Parents |
|---|---|---|
| Trip started | – | "Bus nikal gayi" (that shift) |
| Bus ~5 min from home (driver phone's road ETA) | – | once per trip |
| Bus within the alert radius | – | once per approach |
| Overspeed above limit for N sec | ✔ | – |
| School gate: arrived (pickup run) / left (drop run) | ✔ | children on board / that shift |
| Off the learned everyday road for N sec | ✔ | – |
| Bus should run but is silent 5 min | ✔ | – |
Student portal shows "Bus will reach your home in about N min" and parents can add a landmark note for the driver
("mandir ke saamne, neela gate"), shown in the driver's popup / next-stop card and read out on arrival.

## Tests
`php tests/integration.php` (needs MySQL/MariaDB; env `BUS_TEST_DB`, `BUS_TEST_USER`, `BUS_TEST_PASS`; it DROPS and
recreates that database). `tests/stubs/` stand in for the panel's core files, `tests/fixtures/base_schema.sql` holds the
existing tables as used by this module.

## Setup Wizard
Admin → Fleet → **Setup Wizard**: 1 Bus → 2 Tracking method (driver link with QR + WhatsApp share / GPSLogger / hardware device)
→ 3 **Live test** (turns green only after 3 *new* fixes arrive, shows accuracy and a mini map, troubleshooting tips after 60 s)
→ 4 Route, driver, running days and up to 5 shifts → 5 **Health check** (GPS, route, driver, shift times, students, how many parents
set home + enabled alerts, trips, open watchdog alert) with a score and fix links. Each bus card also has a **Check** button
that opens the health check directly. The QR code uses `qrcode-generator` from cdnjs (renders locally; falls back to the plain link).

## Test without a bus
```
php tools/gps_simulator.php --url=https://YOUR-DOMAIN/api/gps_update.php --key=BUS_KEY --home=LAT,LNG
php tools/gps_simulator.php ... --scenario=offline|glitch|stopgo|fast|badkey --trip
php tools/gps_simulator.php --dry-run          # no network
```
Status: not run against a real PHP+MySQL server yet. Only syntax checks, the simulator in `--dry-run`, and the
watchdog time-window logic were verified.
