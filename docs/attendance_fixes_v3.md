# Auto attendance: bug fixes v3

## Deploy order

1. Back up the database.
2. Run `sql/attendance_fixes_v3.sql` once in phpMyAdmin or the mysql CLI, after `attendance_scan_v2.sql`. It is safe to run again.
3. Upload these files:
   - `includes/attendance_core.php` (new)
   - `api/attendance_actions.php`
   - `api/attendance_scan.php`
   - `school/auto_attendance.php`
   - `cron/attendance_notify_worker.php` (new)
4. Optional, recommended: add a cron job that retries failed parent messages.
   `* * * * * php /path/to/site/cron/attendance_notify_worker.php`

The new PHP files use the new columns and tables, so step 2 must come before step 3.

## What changed

| # | Problem | Fix |
|---|---|---|
| 1 | A gate phone with a wrong clock shifted every scan's time and marked it "offline". The time could also be backdated from devtools. | The client sends `age_ms`, the time the scan waited on the device. The server uses its own clock minus that wait. Offline scans older than 36 h are refused instead of being re-dated to "now". |
| 2 | When a student and a staff member had the same code (e.g. `101`), staff were always marked as the student. | A code that matches more than one person is refused. Name search sends `S:code` or `T:code`. The multi-column QR fallback only accepts a result when exactly one person matches. |
| 3 | Anyone could make a QR with a friend's admission number. | Signed cards use the format `SZ1\|S\|ver\|code\|hmac`, with a secret key per school. Admins can block a lost card, which bumps its version. An optional setting makes the gate accept only signed cards. |
| 4 | The 6-day absence alert never fired for schools using the gate ("Mark remaining absent"), and it sent the SMS inside the teacher's Save request. | The alert now runs from manual marking, single edit and finalize. It only queues the message. |
| 5 | Parent check-in SMS/WhatsApp was sent inside the scan request, so a slow gateway slowed the gate. | Messages go to the `attendance_notify_queue` table and are sent after the response (PHP-FPM/LiteSpeed), retried by cron, and give up after 5 tries. |
| 6 | No audit trail. Undo deleted the row. A stale register could overwrite a scan. Any date could be edited. | Every write goes through `AttCore::write()`, which records `attendance_history` (old → new, source, who, reason). Undo restores the exact previous status and refuses if the record was changed after the scan. Bulk "absent" no longer overwrites a gate scan unless `override_scan=1`. Teachers are limited to N days back (default 7, configurable); future and invalid dates are refused. |
| 7 | Smaller issues | Weekly off is configurable (e.g. 2nd/4th Saturday). `not_arrived` uses the configured ID column. Deadlocks are retried. `summary` uses prepared statements. Settings are no longer read twice. The parent message shows the scan's date. |

## Not covered here (files not in this repo)

- **Card printing:** the ID card print page must put `AttCore::cardPayload($pdo, $schoolId, 'student'|'staff', $id, $code)` into the QR. Until then, use the `card_payload` API (admin).
- **Other code that writes attendance:** the staff manual attendance page, imports and the leave module still write directly to the tables. Those changes have `source = NULL` and no history. Route them through `AttCore::write()`.
- **Leave in "Mark remaining absent":** students on approved leave are only skipped if the leave module has already written a `leave` row.

## Tests

`tests/attendance/run_tests.sh` starts a throw-away MariaDB, runs the real API files against it with stubbed framework includes, and checks 52 cases covering every fix above. It needs `mariadb-server` and `php` with `pdo_mysql`.
