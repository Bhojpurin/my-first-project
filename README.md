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
| `api/bus_trip.php`, `includes/bus_trips.php` | **new** trip start/stop, "bus nikal gayi" push, trip summary |
| `database/bus_tracking.sql` | **new** tables: `bus_live`, `bus_watchdog_alerts`, `bus_trips` |

`config/` (db.php, constants.php), `includes/session.php`, `functions.php`, `push_sender.php` and `student/stu_guard.php`
are not in this repo yet.

## Upgrade steps
1. Run `database/bus_tracking.sql`.
2. Cron: `* * * * * php /path/to/tools/bus_watchdog_cron.php`
3. Optional: `define('BUS_WATCHDOG_WEBHOOK', 'https://...');` in `config/constants.php` for instant messages.

## Trips
Driver page: **Trip Shuru** (starts tracking + pushes "bus nikal gayi" to that shift's students) → **Trip Khatam**
(stores km, max speed, stops, path). Admin → **Trips** tab: filter by date/bus, map of the route, CSV export.
Forgotten trips are closed automatically by the cron job. To send "Bus nearby" alerts only during a real trip,
set `BUS_ALERT_REQUIRE_TRIP = true` in `includes/bus_proximity.php` (off by default so nothing stops working).

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
