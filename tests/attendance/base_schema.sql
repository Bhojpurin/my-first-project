DROP DATABASE IF EXISTS sz; CREATE DATABASE sz CHARACTER SET utf8mb4; USE sz;
CREATE TABLE schools (id INT PRIMARY KEY, school_name VARCHAR(100));
CREATE TABLE school_settings (school_id INT, setting_key VARCHAR(64), setting_val TEXT, PRIMARY KEY(school_id, setting_key));
CREATE TABLE school_holidays (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, date DATE);
CREATE TABLE classes (id INT PRIMARY KEY, school_id INT, class_name VARCHAR(20));
CREATE TABLE sections (id INT PRIMARY KEY, school_id INT, section_name VARCHAR(20));
CREATE TABLE students (id INT PRIMARY KEY, school_id INT, name VARCHAR(100), admission_no VARCHAR(40), rfid_uid VARCHAR(40), photo VARCHAR(100),
  class_id INT, section_id INT, guardian_phone VARCHAR(20), email VARCHAR(100), dob DATE, status VARCHAR(10) DEFAULT 'active');
CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100));
CREATE TABLE teachers (id INT PRIMARY KEY, school_id INT, user_id INT, employee_code VARCHAR(40), photo VARCHAR(100), post VARCHAR(40), status VARCHAR(10) DEFAULT 'active');
CREATE TABLE attendance (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, student_id INT, class_id INT NULL, section_id INT NULL, date DATE,
  status VARCHAR(16), marked_by INT, UNIQUE KEY uq_att (student_id, date));
CREATE TABLE staff_attendance (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, teacher_id INT, date DATE, status VARCHAR(16), marked_by INT,
  UNIQUE KEY uq_satt (teacher_id, date));
CREATE TABLE attendance_scan_log (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, entity_type VARCHAR(10), entity_id INT, scan_type VARCHAR(5),
  raw_payload VARCHAR(255), matched_code VARCHAR(60), scanned_at DATETIME, device_info VARCHAR(255), ip_address VARCHAR(45), scanned_by INT,
  client_uid VARCHAR(40) NULL, gate_name VARCHAR(60) NULL, source VARCHAR(10), status_set VARCHAR(16), is_offline_sync TINYINT DEFAULT 0,
  result_json TEXT NULL, voided_at DATETIME NULL, voided_by INT NULL, UNIQUE KEY uq_client (school_id, client_uid));
CREATE TABLE attendance_scan_reject (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, raw_payload VARCHAR(255), reason VARCHAR(40), gate_name VARCHAR(60), scanned_by INT, created_at DATETIME);
CREATE TABLE school_notification_log (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, event_type VARCHAR(50), recipient VARCHAR(100), sent_at DATETIME);
CREATE TABLE sent_log (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, event_type VARCHAR(50), payload TEXT);
INSERT INTO schools VALUES (1,'SS Zone Public School'),(2,'Other School');
INSERT INTO classes VALUES (10,1,'Class 5'); INSERT INTO sections VALUES (20,1,'A');
INSERT INTO students (id,school_id,name,admission_no,class_id,section_id,guardian_phone,email) VALUES
 (1,1,'Arun Kumar','A100',10,20,'9000000001',''),(2,1,'Priya Singh','101',10,20,'9000000002',''),
 (3,1,'Ravi Das','A102',10,20,'9000000003',''),(4,1,'Neha Gupta','A103',10,20,'',''),(9,2,'Other Kid','A100',NULL,NULL,'9',''),
 (5,1,'Amit Roy','A104',10,20,'9000000005','');
INSERT INTO users VALUES (50,'Sunita Sharma'),(51,'Rakesh Verma');
INSERT INTO teachers VALUES (7,1,50,'101',NULL,'TGT','active'),(8,1,51,'E200',NULL,'PGT','active');
