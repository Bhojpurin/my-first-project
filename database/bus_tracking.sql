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
