<?php
// includes/bus_schema.php — the bus module creates / upgrades its own tables. No command line needed:
// the first request after a deploy (admin opening Bus Tracker, a driver phone, a GPS device, the cron) runs it.
//
// • A version number in bus_schema_version says whether anything is to do — normal requests cost one tiny query.
// • GET_LOCK makes sure only one request migrates even if many arrive at the same moment.
// • Every step is idempotent (CREATE IF NOT EXISTS, columns added only when missing), so a half-finished run
//   simply continues next time. The version is only raised when every step succeeded.
// • Bump BUS_SCHEMA_VERSION whenever database/bus_tracking.sql or the column list below changes.

const BUS_SCHEMA_VERSION = 8;

// Last migration problems of this request (shown to the admin on the Bus Tracker page).
$GLOBALS['BUS_SCHEMA_ERRORS'] = [];

/** Table definitions. Kept here (not only in database/bus_tracking.sql) so a missing .sql upload cannot break setup.
 *  database/bus_tracking.sql is an identical copy for phpMyAdmin imports (tests check they stay identical). */
function busSchemaSql(): string
{
    return <<<'SQL'
-- database/bus_tracking.sql — NEW tables for the bus tracker upgrade. Safe to run more than once.
-- (The existing tables — school_buses, bus_gps_locations, bus_route_assignments, ... — are untouched.)

-- One row per bus: its latest position. Written by api/gps_update.php, read by the student portal,
-- admin map and watchdog instead of scanning the large bus_gps_locations history.
CREATE TABLE IF NOT EXISTS bus_live (
  bus_id      INT          NOT NULL PRIMARY KEY,
  school_id   INT          NOT NULL,
  lat         DECIMAL(10,7) NOT NULL,
  lng         DECIMAL(10,7) NOT NULL,
  speed       DECIMAL(6,2) NOT NULL DEFAULT 0,
  heading     DECIMAL(6,2) NOT NULL DEFAULT 0,
  accuracy    DECIMAL(8,2) NULL,
  recorded_at DATETIME     NOT NULL,
  still_since DATETIME     NULL,                 -- bus standing still since (NULL = moving)
  KEY idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fill it once from existing history so buses show up immediately after the upgrade.
INSERT IGNORE INTO bus_live (bus_id, school_id, lat, lng, speed, heading, accuracy, recorded_at)
SELECT g.bus_id, g.school_id, g.lat, g.lng, g.speed, g.heading, g.accuracy, g.recorded_at
FROM bus_gps_locations g
JOIN (SELECT bus_id, MAX(id) AS mid FROM bus_gps_locations GROUP BY bus_id) x ON x.mid = g.id;

-- Silent-bus alerts (see includes/bus_watchdog.php). One open row per bus at a time.
CREATE TABLE IF NOT EXISTS bus_watchdog_alerts (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  school_id       INT      NOT NULL,
  bus_id          INT      NOT NULL,
  opened_at       DATETIME NOT NULL,
  last_seen_at    DATETIME NULL,
  resolved_at     DATETIME NULL,
  resolved_reason VARCHAR(20) NULL,
  KEY idx_open (bus_id, resolved_at),
  KEY idx_school (school_id, resolved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trips: one row per run of a bus (driver presses "Trip shuru" ... "Trip khatam").
-- The summary (distance, speed, stops, a thinned path) is stored when the trip ends, so reports keep
-- working long after bus_gps_locations history (48 h) has been pruned.
CREATE TABLE IF NOT EXISTS bus_trips (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  school_id     INT      NOT NULL,
  bus_id        INT      NOT NULL,
  shift_no      TINYINT  NOT NULL DEFAULT 1,
  kind          VARCHAR(6) NULL,          -- pickup | drop | any (from the start time vs the shift's times)
  started_at    DATETIME NOT NULL,
  ended_at      DATETIME NULL,
  end_reason    VARCHAR(20) NULL,          -- driver | auto_silent | auto_old
  distance_m    INT      NULL,
  max_speed_kmh DECIMAL(6,1) NULL,
  avg_speed_kmh DECIMAL(6,1) NULL,
  points        INT      NULL,
  stops_json    MEDIUMTEXT NULL,           -- [{lat,lng,at,min}, ...]
  path_json     MEDIUMTEXT NULL,           -- [[lat,lng], ...] (max ~200 pts)
  KEY idx_bus_open (bus_id, ended_at),
  KEY idx_school_time (school_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stops of a trip = the students of that trip's shift who marked their home. One row per student per trip:
-- the driver's planned order (seq) and what happened (done = picked up / dropped, absent = did not come).
CREATE TABLE IF NOT EXISTS bus_trip_stops (
  trip_id    INT         NOT NULL,
  student_id INT         NOT NULL,
  seq        SMALLINT    NULL,                       -- planned order on the driver's phone (1 = first)
  status     VARCHAR(10) NOT NULL DEFAULT 'pending', -- pending | done | absent
  marked_by  VARCHAR(10) NULL,                       -- driver | auto
  marked_at  DATETIME    NULL,
  eta_at     DATETIME    NULL,                       -- driver phone's estimate when the bus reaches this home
  eta_notified TINYINT   NOT NULL DEFAULT 0,         -- "bus ~5 min away" already sent to this parent
  PRIMARY KEY (trip_id, student_id),
  KEY idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Learned route per bus + shift + direction (morning pickup and afternoon drop run in opposite order): the order in which the driver really picks the students up (last 7 trips)
-- and the road path the bus really drives. After BUS_LEARN_MIN_TRIPS consistent trips it becomes the default
-- order on the driver's phone, and its path is drawn as "roz ka raasta" (works offline).
CREATE TABLE IF NOT EXISTS bus_route_learn (
  bus_id        INT      NOT NULL,
  shift_no      TINYINT  NOT NULL,
  kind          VARCHAR(6) NOT NULL DEFAULT 'any',   -- pickup (home → school) | drop (school → home) | any
  school_id     INT      NOT NULL,
  trips_json    MEDIUMTEXT NULL,      -- [{t: trip_id, d: date, o: [student ids in visit order]}, ...]
  learned_order MEDIUMTEXT NULL,      -- [student ids]
  confidence    DECIMAL(4,2) NULL,    -- 0..1: how consistently the driver follows that order
  path_json     MEDIUMTEXT NULL,      -- [[lat,lng], ...] real road path of a good recent trip
  updated_at    DATETIME NOT NULL,
  PRIMARY KEY (bus_id, shift_no, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Security ────────────────────────────────────────────────────────────────
-- Rate limiting (hashed bucket → hits in the current window)
CREATE TABLE IF NOT EXISTS bus_rate_limits (
  k   CHAR(64) NOT NULL PRIMARY KEY,
  win INT      NOT NULL,
  n   INT      NOT NULL,
  KEY idx_win (win)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

-- One-time pairing links for driver phones (only the SHA-256 of the code is stored)
CREATE TABLE IF NOT EXISTS bus_pair_codes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  code_hash  CHAR(64) NOT NULL UNIQUE,
  school_id  INT      NOT NULL,
  bus_id     INT      NOT NULL,
  created_by INT      NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  KEY idx_bus (bus_id, used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Paired driver phones (token stored as SHA-256 only; revocable)
CREATE TABLE IF NOT EXISTS bus_driver_devices (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  school_id    INT      NOT NULL,
  bus_id       INT      NOT NULL,
  token_hash   CHAR(64) NOT NULL UNIQUE,
  label        VARCHAR(80) NULL,
  created_at   DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  last_ip      VARCHAR(45) NULL,
  revoked_at   DATETIME NULL,
  KEY idx_bus (school_id, bus_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Safety alerts ───────────────────────────────────────────────────────────
-- Per-school settings (each school sets its own speed limit and school gate location)
CREATE TABLE IF NOT EXISTS bus_alert_settings (
  school_id        INT          NOT NULL PRIMARY KEY,
  overspeed_kmh    SMALLINT     NOT NULL DEFAULT 50,
  overspeed_sec    SMALLINT     NOT NULL DEFAULT 20,   -- must last this long (no alert for one GPS spike)
  school_lat       DECIMAL(10,7) NULL,
  school_lng       DECIMAL(10,7) NULL,
  school_radius_m  SMALLINT     NOT NULL DEFAULT 150,
  deviation_m      SMALLINT     NOT NULL DEFAULT 400,  -- this far from the learned everyday road = off route
  deviation_sec    SMALLINT     NOT NULL DEFAULT 90,
  notify_parents   TINYINT      NOT NULL DEFAULT 1,    -- "bus reached school / left school" to parents
  halt_ask_min     SMALLINT     NOT NULL DEFAULT 3,    -- stopped on the road this long → driver is asked why
  halt_admin_min   SMALLINT     NOT NULL DEFAULT 8,    -- ...and this long without an answer → admin alert
  updated_at       DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Alert log (admin feed on the live map + history)
CREATE TABLE IF NOT EXISTS bus_alerts (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  school_id   INT         NOT NULL,
  bus_id      INT         NOT NULL,
  trip_id     INT         NULL,
  type        VARCHAR(20) NOT NULL,        -- overspeed | school_arrive | school_leave | deviation | silent
  lat         DECIMAL(10,7) NULL,
  lng         DECIMAL(10,7) NULL,
  value       DECIMAL(8,1) NULL,           -- km/h for overspeed, metres for deviation
  message     VARCHAR(255) NOT NULL,
  ref_id      INT         NULL,            -- bus_halt_reports.id for halt alerts
  created_at  DATETIME    NOT NULL,
  seen_at     DATETIME    NULL,
  broadcast_at DATETIME   NULL,            -- admin sent it to the parents of that shift
  broadcast_n INT         NOT NULL DEFAULT 0,
  KEY idx_school_time (school_id, created_at),
  KEY idx_bus_time (bus_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Running state per bus for the alert engine (so one event = one alert, not one per GPS point)
CREATE TABLE IF NOT EXISTS bus_alert_state (
  bus_id       INT      NOT NULL PRIMARY KEY,
  school_id    INT      NOT NULL,
  over_since   DATETIME NULL,
  over_max     DECIMAL(6,1) NULL,
  over_alerted TINYINT  NOT NULL DEFAULT 0,
  at_school    TINYINT  NULL,              -- NULL = unknown yet (no alert on the first fix)
  off_since    DATETIME NULL,
  off_alerted  TINYINT  NOT NULL DEFAULT 0,
  halt_alerted_for DATETIME NULL           -- still_since of the halt already reported to the admin
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Why did the bus stop on the road? Reported by the driver from the popup (or the "problem" button).
CREATE TABLE IF NOT EXISTS bus_halt_reports (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  school_id   INT         NOT NULL,
  bus_id      INT         NOT NULL,
  trip_id     INT         NULL,
  device_id   INT         NULL,
  reason_code VARCHAR(12) NOT NULL,        -- puncture | breakdown | traffic | fuel | road | police | other
  reason_text VARCHAR(200) NULL,
  delay_min   SMALLINT    NULL,
  lat         DECIMAL(10,7) NULL,
  lng         DECIMAL(10,7) NULL,
  halt_sec    INT         NULL,
  created_at  DATETIME    NOT NULL,
  resolved_at DATETIME    NULL,            -- the bus moved on
  KEY idx_bus_open (bus_id, resolved_at),
  KEY idx_school_time (school_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parent: "my child will not take the bus on this day" (pickup / drop / both). The stop is pre-marked ✖ on the
-- driver's phone. One row per student per day. Deleted automatically when old (privacy cleanup).
CREATE TABLE IF NOT EXISTS bus_absences (
  student_id INT         NOT NULL,
  on_date    DATE        NOT NULL,
  school_id  INT         NOT NULL,
  kind       VARCHAR(6)  NOT NULL DEFAULT 'both',   -- pickup | drop | both
  note       VARCHAR(120) NULL,
  created_at DATETIME    NOT NULL,
  PRIMARY KEY (student_id, on_date),
  KEY idx_school_date (school_id, on_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
}

/** Columns added after the first release (tables created by older versions get them here). */
const BUS_SCHEMA_ADD_COLUMNS = [
    ['bus_live', 'still_since', 'DATETIME NULL'],
    ['bus_trip_stops', 'eta_at', 'DATETIME NULL'],
    ['bus_trip_stops', 'eta_notified', 'TINYINT NOT NULL DEFAULT 0'],
    ['bus_trips', 'kind', 'VARCHAR(6) NULL'],
    ['bus_alert_settings', 'halt_ask_min', 'SMALLINT NOT NULL DEFAULT 3'],
    ['bus_alert_settings', 'halt_admin_min', 'SMALLINT NOT NULL DEFAULT 8'],
    ['bus_alerts', 'ref_id', 'INT NULL'],
    ['bus_alerts', 'broadcast_at', 'DATETIME NULL'],
    ['bus_alerts', 'broadcast_n', 'INT NOT NULL DEFAULT 0'],
    ['bus_alert_state', 'halt_alerted_for', 'DATETIME NULL'],
    ['student_home_locations', 'note', 'VARCHAR(120) NULL'],          // landmark for the driver
    ['student_home_locations', 'bus_lost_at', 'DATETIME NULL'],       // privacy grace period
];

function busColumnExists(PDO $pdo, string $t, string $c): bool
{
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->execute([$t, $c]);
    return (bool)$q->fetchColumn();
}

/**
 * Run every migration step. $log receives one line per step (✔ / ✖). Returns true when all steps succeeded.
 */
function busRunMigration(PDO $pdo, ?callable $log = null): bool
{
    $log = $log ?: function ($l) {};
    $ok = true;
    $mode = $pdo->getAttribute(PDO::ATTR_ERRMODE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        // The panel's own GPS table needs "accuracy" before bus_live is filled from it
        try {
            if (!busColumnExists($pdo, 'bus_gps_locations', 'accuracy')) { $pdo->exec("ALTER TABLE `bus_gps_locations` ADD COLUMN `accuracy` DECIMAL(8,2) NULL"); $log('✔ added bus_gps_locations.accuracy'); }
        } catch (\Throwable $e) { $ok = false; $log('✖ bus_gps_locations.accuracy → ' . $e->getMessage()); }

        $sql = preg_replace('/^\s*--.*$/m', '', busSchemaSql());
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            try { $pdo->exec($stmt); $log('✔ ' . strtok($stmt, "\n")); }
            catch (\Throwable $e) { $ok = false; $log('✖ ' . strtok($stmt, "\n") . ' → ' . $e->getMessage()); }
        }
        foreach (BUS_SCHEMA_ADD_COLUMNS as [$t, $c, $def]) {
            try {
                if (busColumnExists($pdo, $t, $c)) { $log("✔ $t.$c exists"); continue; }
                $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
                $log("✔ added $t.$c");
            } catch (\Throwable $e) { $ok = false; $log("✖ $t.$c → " . $e->getMessage()); }
        }
        // bus_route_learn got a "kind" (pickup/drop) column in its primary key
        try {
            $q = $pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE()
                              AND TABLE_NAME='bus_route_learn' AND CONSTRAINT_NAME='PRIMARY' AND COLUMN_NAME='kind'");
            if (!(int)$q->fetchColumn()) {
                if (!busColumnExists($pdo, 'bus_route_learn', 'kind')) $pdo->exec("ALTER TABLE bus_route_learn ADD COLUMN kind VARCHAR(6) NOT NULL DEFAULT 'any' AFTER shift_no");
                $pdo->exec("ALTER TABLE bus_route_learn DROP PRIMARY KEY, ADD PRIMARY KEY (bus_id, shift_no, kind)");
                $log('✔ bus_route_learn primary key upgraded');
            }
        } catch (\Throwable $e) { $ok = false; $log('✖ bus_route_learn key → ' . $e->getMessage()); }
    } finally {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, $mode);
    }
    return $ok;
}

/** Make sure the bus tables are up to date. Cheap when they are (one query per request). Never throws. */
function busEnsureSchema(PDO $pdo, bool $force = false): void
{
    static $checked = false;
    if ($checked && !$force) return;
    $checked = true;
    try {
        $v = (int)$pdo->query("SELECT MAX(v) FROM bus_schema_version")->fetchColumn();
        if ($v >= BUS_SCHEMA_VERSION) return;
    } catch (\Throwable $e) { /* table missing → first run */ }

    try {
        if ((int)$pdo->query("SELECT GET_LOCK('bus_schema_migrate', 25)")->fetchColumn() !== 1) return;   // someone else is on it
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS bus_schema_version (v INT NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB");
            $v = (int)$pdo->query("SELECT MAX(v) FROM bus_schema_version")->fetchColumn();
            if ($v >= BUS_SCHEMA_VERSION) return;                                       // done while we waited
            $lines = [];
            $okRun = busRunMigration($pdo, function ($l) use (&$lines) { $lines[] = $l; });
            $GLOBALS['BUS_SCHEMA_ERRORS'] = array_values(array_filter($lines, function ($l) { return strpos($l, '✖') === 0; }));
            if ($okRun) {
                $pdo->exec("DELETE FROM bus_schema_version");
                $pdo->prepare("INSERT INTO bus_schema_version (v, updated_at) VALUES (?, NOW())")->execute([BUS_SCHEMA_VERSION]);
                error_log('bus_schema: upgraded to v' . BUS_SCHEMA_VERSION);
            } else {
                error_log("bus_schema: migration incomplete —\n" . implode("\n", array_filter($lines, function ($l) { return strpos($l, '✖') === 0; })));
            }
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('bus_schema_migrate')");
        }
    } catch (\Throwable $e) {
        $GLOBALS['BUS_SCHEMA_ERRORS'][] = '✖ ' . $e->getMessage();
        error_log('bus_schema: ' . $e->getMessage());
    }
}

/** Current schema version in the database (0 = not set up). */
function busSchemaVersion(PDO $pdo): int
{
    try { return (int)$pdo->query("SELECT MAX(v) FROM bus_schema_version")->fetchColumn(); } catch (\Throwable $e) { return 0; }
}
