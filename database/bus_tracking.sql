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
