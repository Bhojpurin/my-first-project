-- sql/attendance_fixes_v3.sql
-- Run ONCE (after attendance_scan_v2.sql) BEFORE uploading the new
--   includes/attendance_core.php, api/attendance_actions.php, api/attendance_scan.php, school/auto_attendance.php
-- Safe to run again: every column / index / table is only added when it is missing.
-- Works on MySQL 5.7+, MySQL 8 and MariaDB 10.3+.

DROP PROCEDURE IF EXISTS _att_add_col;
DROP PROCEDURE IF EXISTS _att_add_idx;
DROP PROCEDURE IF EXISTS _att_uq_client_uid;
DELIMITER //
CREATE PROCEDURE _att_add_col(IN t VARCHAR(64), IN c VARCHAR(64), IN def TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = t AND column_name = c) THEN
    SET @s = CONCAT('ALTER TABLE `', t, '` ADD COLUMN `', c, '` ', def);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END//
CREATE PROCEDURE _att_add_idx(IN t VARCHAR(64), IN i VARCHAR(64), IN def TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = t AND index_name = i) THEN
    SET @s = CONCAT('ALTER TABLE `', t, '` ADD ', def);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END//
CREATE PROCEDURE _att_uq_client_uid()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = 'attendance_scan_log'
                   AND column_name = 'client_uid' AND non_unique = 0) THEN
    ALTER TABLE attendance_scan_log ADD UNIQUE INDEX `uq_asl_client_uid` (school_id, client_uid);
  END IF;
END//
DELIMITER ;

-- 1. Where did each attendance row come from, and when was it last changed?
--    source: scan | manual | finalize | undo   (NULL = written before this update)
CALL _att_add_col('attendance',       'source',     'VARCHAR(16) NULL');
CALL _att_add_col('attendance',       'updated_at', 'DATETIME NULL');
CALL _att_add_col('staff_attendance', 'source',     'VARCHAR(16) NULL');
CALL _att_add_col('staff_attendance', 'updated_at', 'DATETIME NULL');

-- 2. Scan log: what the attendance was before the scan (exact undo), how long the scan waited
--    on the device, and when the server received it.
CALL _att_add_col('attendance_scan_log', 'prev_status',    'VARCHAR(16) NULL');
CALL _att_add_col('attendance_scan_log', 'client_age_sec', 'INT NULL');
CALL _att_add_col('attendance_scan_log', 'received_at',    'DATETIME NULL');

-- 3. Indexes the scan path depends on. FOR UPDATE without the first one locks far too many rows.
CALL _att_add_idx('attendance_scan_log', 'idx_asl_person_day', 'INDEX `idx_asl_person_day` (school_id, entity_type, entity_id, scanned_at)');
CALL _att_add_idx('attendance_scan_log', 'idx_asl_school_day', 'INDEX `idx_asl_school_day` (school_id, scanned_at)');
-- client_uid must be UNIQUE for the offline replay protection. Only added when no unique key on
-- client_uid exists yet (attendance_scan_v2.sql may already have one under another name).
-- If it fails with "Duplicate entry", old duplicate rows exist; find them with:
--   SELECT school_id, client_uid, COUNT(*) FROM attendance_scan_log WHERE client_uid IS NOT NULL GROUP BY 1,2 HAVING COUNT(*) > 1;
CALL _att_uq_client_uid();

-- 4. Full change history: every attendance change, old → new, who, why.
CREATE TABLE IF NOT EXISTS attendance_history (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  school_id   INT NOT NULL,
  entity_type ENUM('student','staff') NOT NULL,
  entity_id   INT NOT NULL,
  date        DATE NOT NULL,
  old_status  VARCHAR(16) NULL,
  new_status  VARCHAR(16) NULL,          -- NULL = row removed
  source      VARCHAR(16) NOT NULL,      -- scan | manual | finalize | undo
  reason      VARCHAR(255) NULL,
  changed_by  INT NOT NULL,
  changed_at  DATETIME NOT NULL,
  KEY idx_ah_person (school_id, entity_type, entity_id, date),
  KEY idx_ah_school_date (school_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Outgoing parent messages. Requests only insert here; sending happens after the response
--    and from cron/attendance_notify_worker.php, with retries.
CREATE TABLE IF NOT EXISTS attendance_notify_queue (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  school_id    INT NOT NULL,
  event_type   VARCHAR(50) NOT NULL,
  dedupe_key   VARCHAR(100) NULL,
  payload_json TEXT NOT NULL,
  status       ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error   VARCHAR(255) NULL,
  available_at DATETIME NOT NULL,
  created_at   DATETIME NOT NULL,
  updated_at   DATETIME NULL,
  sent_at      DATETIME NULL,
  KEY idx_anq_due (status, available_at),
  KEY idx_anq_school_due (school_id, status, available_at),
  KEY idx_anq_dedupe (school_id, dedupe_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Signed ID card versions. No row = version 1. Blocking a lost card bumps the version.
CREATE TABLE IF NOT EXISTS attendance_card (
  school_id   INT NOT NULL,
  entity_type ENUM('student','staff') NOT NULL,
  entity_id   INT NOT NULL,
  version     INT UNSIGNED NOT NULL DEFAULT 1,
  revoked_at  DATETIME NULL,
  PRIMARY KEY (school_id, entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Registered gate phones / tablets. They keep scanning after a teacher's session expires,
--    but can only scan and see today's numbers. Only a hash of the token is stored.
CREATE TABLE IF NOT EXISTS attendance_gate_device (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  school_id    INT NOT NULL,
  name         VARCHAR(60) NOT NULL,
  token_hash   CHAR(64) NOT NULL,
  created_by   INT NOT NULL,
  created_at   DATETIME NOT NULL,
  last_seen_at DATETIME NULL,
  last_ip      VARCHAR(45) NULL,
  revoked_at   DATETIME NULL,
  revoked_by   INT NULL,
  KEY idx_agd_school (school_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CALL _att_add_col('attendance_scan_log', 'gate_device_id', 'INT UNSIGNED NULL');

DROP PROCEDURE IF EXISTS _att_add_col;
DROP PROCEDURE IF EXISTS _att_add_idx;
DROP PROCEDURE IF EXISTS _att_uq_client_uid;
