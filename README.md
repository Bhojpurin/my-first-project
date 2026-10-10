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
| `admin/bus_tracker.php`, `student/index.php`, `student/sw.js` | UI (paths assumed — adjust if yours differ) |
| `tools/gps_simulator.php` | **new** fake bus for testing |
| `tools/bus_watchdog_cron.php` | **new** run every minute |
| `database/bus_tracking.sql` | **new** tables: `bus_live`, `bus_watchdog_alerts` |

`config/` (db.php, constants.php), `includes/session.php`, `functions.php`, `push_sender.php` and `student/stu_guard.php`
are not in this repo yet.

## Upgrade steps
1. Run `database/bus_tracking.sql`.
2. Cron: `* * * * * php /path/to/tools/bus_watchdog_cron.php`
3. Optional: `define('BUS_WATCHDOG_WEBHOOK', 'https://...');` in `config/constants.php` for instant messages.

## Test without a bus
```
php tools/gps_simulator.php --url=https://YOUR-DOMAIN/api/gps_update.php --key=BUS_KEY --home=LAT,LNG
php tools/gps_simulator.php ... --scenario=offline|glitch|stopgo|fast|badkey
php tools/gps_simulator.php --dry-run          # no network
```
Status: not run against a real PHP+MySQL server yet. Only syntax checks, the simulator in `--dry-run`, and the
watchdog time-window logic were verified.
