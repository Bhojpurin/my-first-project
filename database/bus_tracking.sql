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
