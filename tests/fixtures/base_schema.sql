-- tests/fixtures/base_schema.sql — the panel's EXISTING tables, reconstructed from the queries in this module
-- (only the columns the bus module uses). Test-only: the real panel already has these tables.
CREATE TABLE school_buses (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT NOT NULL, bus_name VARCHAR(80), bus_number VARCHAR(40),
  capacity INT DEFAULT 40, gps_device_id VARCHAR(80) NULL, gps_api_key VARCHAR(128) NULL, notes TEXT NULL, status VARCHAR(10) DEFAULT 'active',
  UNIQUE KEY (gps_api_key));
CREATE TABLE bus_gps_locations (id INT AUTO_INCREMENT PRIMARY KEY, bus_id INT, school_id INT, lat DECIMAL(10,7), lng DECIMAL(10,7),
  speed DECIMAL(6,2), heading DECIMAL(6,2), accuracy DECIMAL(8,2) NULL, recorded_at DATETIME, KEY (bus_id, recorded_at));
CREATE TABLE van_routes (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, route_name VARCHAR(80), from_location VARCHAR(80), to_location VARCHAR(80),
  distance_km DECIMAL(6,2) NULL, is_active TINYINT DEFAULT 1);
CREATE TABLE bus_route_assignments (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, route_id INT, bus_id INT, driver_id INT NULL, shift_count TINYINT DEFAULT 1,
  pickup_time TIME NULL, drop_time TIME NULL, pickup_time2 TIME NULL, drop_time2 TIME NULL, pickup_time3 TIME NULL, drop_time3 TIME NULL,
  pickup_time4 TIME NULL, drop_time4 TIME NULL, pickup_time5 TIME NULL, drop_time5 TIME NULL, days VARCHAR(40), status VARCHAR(10) DEFAULT 'active');
CREATE TABLE classes (id INT AUTO_INCREMENT PRIMARY KEY, class_name VARCHAR(20));
CREATE TABLE sections (id INT AUTO_INCREMENT PRIMARY KEY, section_name VARCHAR(10));
CREATE TABLE students (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, name VARCHAR(80), admission_no VARCHAR(20), photo VARCHAR(120) NULL,
  class_id INT NULL, section_id INT NULL, status VARCHAR(10) DEFAULT 'active');
CREATE TABLE student_van_assignments (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT, van_route_id INT, school_id INT);
CREATE TABLE bus_student_shifts (school_id INT, route_id INT, student_id INT, shift_no TINYINT, PRIMARY KEY (school_id, route_id, student_id));
CREATE TABLE student_home_locations (student_id INT PRIMARY KEY, school_id INT, lat DECIMAL(10,7), lng DECIMAL(10,7), alert_radius INT DEFAULT 500,
  push_enabled TINYINT DEFAULT 0, in_radius_since DATETIME NULL, updated_at DATETIME NULL);
CREATE TABLE push_subscriptions (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT, school_id INT, endpoint TEXT, endpoint_hash CHAR(64) UNIQUE,
  p256dh VARCHAR(200), auth VARCHAR(100));
CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80), phone VARCHAR(20) NULL);
CREATE TABLE teachers (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, school_id INT, status VARCHAR(10) DEFAULT 'active', post VARCHAR(40),
  employee_code VARCHAR(20) NULL, photo VARCHAR(120) NULL);
CREATE TABLE school_messages (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT NOT NULL, sender_type VARCHAR(10), sender_id INT,
  recipient_type VARCHAR(20), recipient_id INT, body TEXT, image VARCHAR(200) NULL, is_auto TINYINT NOT NULL DEFAULT 0,
  auto_event VARCHAR(60) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
