-- ============================================================
-- IT DEPARTMENT ATTENDANCE MANAGEMENT SYSTEM
-- Database Schema - Production Ready
-- Institution: Hannah School of Health Sciences (IT Faculty)
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- Start from a clean slate every time this script is run. This makes the
-- script safe to re-import after a partial/failed run — DDL statements
-- (CREATE TABLE/TRIGGER/VIEW) commit implicitly in MySQL regardless of
-- any surrounding transaction, so a failure partway through a previous
-- import can otherwise leave the database in a half-seeded state.
DROP DATABASE IF EXISTS `it_attendance_db`;

-- Create and use database
CREATE DATABASE IF NOT EXISTS `it_attendance_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `it_attendance_db`;

-- ============================================================
-- TABLE: programs
-- IT Department programs offered
-- ============================================================
CREATE TABLE `programs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(20)  NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `description` TEXT,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_program_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IT Department Academic Programs';

-- ============================================================
-- TABLE: users
-- Super Admin (Dean) + Lecturers
-- ============================================================
CREATE TABLE `users` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `employee_id`         VARCHAR(20)   NOT NULL,
  `first_name`          VARCHAR(80)   NOT NULL,
  `last_name`           VARCHAR(80)   NOT NULL,
  `email`               VARCHAR(191)  NOT NULL,
  `phone`               VARCHAR(20)   DEFAULT NULL,
  `password_hash`       VARCHAR(255)  NOT NULL,
  `role`                ENUM('super_admin','lecturer') NOT NULL DEFAULT 'lecturer',
  `program_id`          INT UNSIGNED  DEFAULT NULL COMMENT 'NULL for super_admin',
  `profile_photo`       VARCHAR(255)  DEFAULT NULL,
  `is_active`           TINYINT(1)    NOT NULL DEFAULT 1,
  `last_login`          TIMESTAMP     NULL DEFAULT NULL,
  `login_attempts`      TINYINT       NOT NULL DEFAULT 0,
  `locked_until`        TIMESTAMP     NULL DEFAULT NULL,
  `password_reset_token`VARCHAR(64)   DEFAULT NULL,
  `reset_token_expires` TIMESTAMP     NULL DEFAULT NULL,
  `created_by`          INT UNSIGNED  DEFAULT NULL,
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_id` (`employee_id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_program` (`program_id`),
  KEY `fk_user_created_by` (`created_by`),
  CONSTRAINT `fk_user_program`
    FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_user_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='System users: Super Admin (Dean) and Lecturers';

-- ============================================================
-- TABLE: course_units
-- Course units per program, assigned to lecturers
-- ============================================================
CREATE TABLE `course_units` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(30)   NOT NULL,
  `name`         VARCHAR(200)  NOT NULL,
  `credit_units` TINYINT       NOT NULL DEFAULT 3,
  `program_id`   INT UNSIGNED  NOT NULL,
  `semester`     ENUM('I','II') NOT NULL DEFAULT 'I',
  `year_of_study`TINYINT        NOT NULL DEFAULT 1 COMMENT '1-4',
  `description`  TEXT,
  `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_code_program` (`code`, `program_id`),
  KEY `idx_program_id` (`program_id`),
  CONSTRAINT `fk_course_program`
    FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Course units offered per program';

-- ============================================================
-- TABLE: lecturer_course_assignments
-- Maps lecturers to the course units they teach
-- ============================================================
CREATE TABLE `lecturer_course_assignments` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lecturer_id`    INT UNSIGNED NOT NULL,
  `course_unit_id` INT UNSIGNED NOT NULL,
  `academic_year`  VARCHAR(9)   NOT NULL COMMENT 'e.g. 2025/2026',
  `semester`       ENUM('I','II') NOT NULL DEFAULT 'I',
  `assigned_by`    INT UNSIGNED DEFAULT NULL,
  `assigned_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lecturer_course_year_sem` (`lecturer_id`,`course_unit_id`,`academic_year`,`semester`),
  KEY `idx_course_unit` (`course_unit_id`),
  KEY `fk_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_lca_lecturer`
    FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_lca_course`
    FOREIGN KEY (`course_unit_id`) REFERENCES `course_units` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_lca_assigned_by`
    FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Maps lecturers to course units they teach';

-- ============================================================
-- TABLE: students
-- Student records managed by lecturers
-- ============================================================
CREATE TABLE `students` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `student_number`  VARCHAR(30)   NOT NULL,
  `first_name`      VARCHAR(80)   NOT NULL,
  `last_name`       VARCHAR(80)   NOT NULL,
  `email`           VARCHAR(191)  DEFAULT NULL,
  `phone`           VARCHAR(20)   DEFAULT NULL,
  `gender`          ENUM('Male','Female','Other') DEFAULT NULL,
  `program_id`      INT UNSIGNED  NOT NULL,
  `year_of_study`   TINYINT       NOT NULL DEFAULT 1,
  `semester`        ENUM('I','II') NOT NULL DEFAULT 'I',
  `academic_year`   VARCHAR(9)    NOT NULL COMMENT 'e.g. 2025/2026',
  `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `is_flagged`      TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Auto-flagged after 4 absences',
  `flag_reason`     TEXT          DEFAULT NULL,
  `created_by`      INT UNSIGNED  NOT NULL COMMENT 'Lecturer who added student',
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_number` (`student_number`),
  KEY `idx_program_id` (`program_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_flagged` (`is_flagged`),
  KEY `idx_academic_year` (`academic_year`),
  FULLTEXT KEY `ft_student_search` (`first_name`,`last_name`,`student_number`,`email`),
  CONSTRAINT `fk_student_program`
    FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_student_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Student records per program';

-- ============================================================
-- TABLE: lecture_sessions
-- Each scheduled lecture session per course unit
-- ============================================================
CREATE TABLE `lecture_sessions` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `course_unit_id`  INT UNSIGNED  NOT NULL,
  `lecturer_id`     INT UNSIGNED  NOT NULL,
  `session_date`    DATE          NOT NULL,
  `start_time`      TIME          NOT NULL,
  `end_time`        TIME          NOT NULL,
  `topic`           VARCHAR(255)  DEFAULT NULL,
  `venue`           VARCHAR(100)  DEFAULT NULL,
  `academic_year`   VARCHAR(9)    NOT NULL,
  `semester`        ENUM('I','II') NOT NULL DEFAULT 'I',
  `notes`           TEXT,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_course_unit` (`course_unit_id`),
  KEY `idx_lecturer_id` (`lecturer_id`),
  KEY `idx_session_date` (`session_date`),
  KEY `idx_academic_year_sem` (`academic_year`, `semester`),
  CONSTRAINT `fk_session_course`
    FOREIGN KEY (`course_unit_id`) REFERENCES `course_units` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_session_lecturer`
    FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Individual lecture sessions per course unit';

-- ============================================================
-- TABLE: attendance_records
-- Per-student attendance per lecture session
-- ============================================================
CREATE TABLE `attendance_records` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `session_id`     INT UNSIGNED   NOT NULL,
  `student_id`     INT UNSIGNED   NOT NULL,
  `status`         ENUM('present','absent','late','excused') NOT NULL DEFAULT 'absent',
  `remarks`        VARCHAR(255)   DEFAULT NULL,
  `marked_by`      INT UNSIGNED   NOT NULL,
  `marked_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_student` (`session_id`, `student_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_status` (`status`),
  KEY `idx_marked_by` (`marked_by`),
  CONSTRAINT `fk_att_session`
    FOREIGN KEY (`session_id`) REFERENCES `lecture_sessions` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_att_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_att_marked_by`
    FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-session attendance records';

-- ============================================================
-- TABLE: student_course_enrollments
-- Links students to course units
-- ============================================================
CREATE TABLE `student_course_enrollments` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`     INT UNSIGNED NOT NULL,
  `course_unit_id` INT UNSIGNED NOT NULL,
  `academic_year`  VARCHAR(9)   NOT NULL,
  `semester`       ENUM('I','II') NOT NULL DEFAULT 'I',
  `enrolled_by`    INT UNSIGNED DEFAULT NULL,
  `enrolled_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_course_year_sem` (`student_id`,`course_unit_id`,`academic_year`,`semester`),
  KEY `idx_course_unit_id` (`course_unit_id`),
  KEY `fk_enroll_by` (`enrolled_by`),
  CONSTRAINT `fk_enroll_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enroll_course`
    FOREIGN KEY (`course_unit_id`) REFERENCES `course_units` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enroll_by`
    FOREIGN KEY (`enrolled_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Student enrollment in course units';

-- ============================================================
-- TABLE: audit_logs
-- Security and activity audit trail
-- ============================================================
CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED    DEFAULT NULL,
  `action`      VARCHAR(100)    NOT NULL,
  `entity_type` VARCHAR(50)     DEFAULT NULL,
  `entity_id`   INT UNSIGNED    DEFAULT NULL,
  `old_values`  JSON            DEFAULT NULL,
  `new_values`  JSON            DEFAULT NULL,
  `ip_address`  VARCHAR(45)     DEFAULT NULL,
  `user_agent`  VARCHAR(512)    DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_audit_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit trail for all system actions';

-- ============================================================
-- TRIGGER: Auto-flag student after 4 absences per course
-- ============================================================
DELIMITER $$

CREATE TRIGGER `trg_check_absences_after_insert`
AFTER INSERT ON `attendance_records`
FOR EACH ROW
BEGIN
  DECLARE v_absences INT DEFAULT 0;
  DECLARE v_course_id INT DEFAULT 0;

  -- Get course from session
  SELECT course_unit_id INTO v_course_id
  FROM lecture_sessions WHERE id = NEW.session_id;

  IF NEW.status = 'absent' THEN
    SELECT COUNT(*) INTO v_absences
    FROM attendance_records ar
    JOIN lecture_sessions ls ON ar.session_id = ls.id
    WHERE ar.student_id = NEW.student_id
      AND ls.course_unit_id = v_course_id
      AND ar.status = 'absent';

    IF v_absences >= 4 THEN
      UPDATE students
      SET is_flagged  = 1,
          flag_reason = CONCAT('Flagged: ', v_absences, ' absences in course unit #', v_course_id, ' as of ', NOW())
      WHERE id = NEW.student_id;
    END IF;
  END IF;
END$$

CREATE TRIGGER `trg_check_absences_after_update`
AFTER UPDATE ON `attendance_records`
FOR EACH ROW
BEGIN
  DECLARE v_absences INT DEFAULT 0;
  DECLARE v_course_id INT DEFAULT 0;

  SELECT course_unit_id INTO v_course_id
  FROM lecture_sessions WHERE id = NEW.session_id;

  SELECT COUNT(*) INTO v_absences
  FROM attendance_records ar
  JOIN lecture_sessions ls ON ar.session_id = ls.id
  WHERE ar.student_id = NEW.student_id
    AND ls.course_unit_id = v_course_id
    AND ar.status = 'absent';

  IF v_absences >= 4 THEN
    UPDATE students SET is_flagged = 1,
      flag_reason = CONCAT('Flagged: ', v_absences, ' absences in course unit #', v_course_id)
    WHERE id = NEW.student_id;
  ELSE
    -- Unflag if absences drop below threshold (e.g., status corrected)
    UPDATE students SET is_flagged = 0, flag_reason = NULL
    WHERE id = NEW.student_id AND is_flagged = 1;
  END IF;
END$$

DELIMITER ;

-- ============================================================
-- VIEWS
-- ============================================================

-- Attendance summary per student per course
CREATE OR REPLACE VIEW `vw_student_attendance_summary` AS
SELECT
  s.id                                          AS student_id,
  s.student_number,
  CONCAT(s.first_name,' ',s.last_name)          AS student_name,
  s.program_id,
  p.name                                        AS program_name,
  cu.id                                         AS course_unit_id,
  cu.code                                       AS course_code,
  cu.name                                       AS course_name,
  ls.academic_year,
  ls.semester,
  COUNT(ar.id)                                  AS total_sessions,
  SUM(ar.status = 'present')                    AS present_count,
  SUM(ar.status = 'absent')                     AS absent_count,
  SUM(ar.status = 'late')                       AS late_count,
  SUM(ar.status = 'excused')                    AS excused_count,
  ROUND(
    SUM(ar.status IN ('present','late')) /
    NULLIF(COUNT(ar.id), 0) * 100, 2
  )                                             AS attendance_pct,
  s.is_flagged
FROM students s
JOIN programs p              ON s.program_id = p.id
JOIN attendance_records ar   ON ar.student_id = s.id
JOIN lecture_sessions ls     ON ar.session_id = ls.id
JOIN course_units cu         ON ls.course_unit_id = cu.id
GROUP BY
  s.id, s.student_number, student_name,
  s.program_id, p.name,
  cu.id, cu.code, cu.name,
  ls.academic_year, ls.semester, s.is_flagged;

-- Program attendance overview
CREATE OR REPLACE VIEW `vw_program_attendance_overview` AS
SELECT
  p.id          AS program_id,
  p.name        AS program_name,
  ls.academic_year,
  ls.semester,
  COUNT(DISTINCT s.id)                          AS total_students,
  COUNT(DISTINCT ls.id)                         AS total_sessions,
  COUNT(ar.id)                                  AS total_records,
  SUM(ar.status = 'present')                    AS total_present,
  SUM(ar.status = 'absent')                     AS total_absent,
  ROUND(
    SUM(ar.status IN ('present','late')) /
    NULLIF(COUNT(ar.id), 0) * 100, 2
  )             AS overall_attendance_pct,
  SUM(s.is_flagged)                             AS flagged_students
FROM programs p
JOIN students s              ON s.program_id = p.id
JOIN attendance_records ar   ON ar.student_id = s.id
JOIN lecture_sessions ls     ON ar.session_id = ls.id
GROUP BY p.id, p.name, ls.academic_year, ls.semester;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Programs
INSERT INTO `programs` (`code`, `name`, `description`) VALUES
  ('IT',  'Information Technology',   'Bachelor of Information Technology'),
  ('SE',  'Software Engineering',     'Bachelor of Software Engineering'),
  ('CS',  'Computer Science',         'Bachelor of Computer Science'),
  ('IS',  'Information Systems',      'Bachelor of Information Systems and Technology');

-- Super Admin (Dean) — password: Admin@2026! (bcrypt)
INSERT INTO `users` (`employee_id`,`first_name`,`last_name`,`email`,`password_hash`,`role`,`program_id`) VALUES
  ('DEAN-001','IT Faculty','Dean','dean@university.ac.ug',
   '$2y$12$l1rWyCDT6B130OIyepp/QePZI6gDnI83FsUF6WGTyK2mS60odICg6',
   'super_admin', NULL);

-- Sample course units for IT program (program_id = 1)
INSERT INTO `course_units` (`code`,`name`,`credit_units`,`program_id`,`semester`,`year_of_study`) VALUES
  ('IT1101','Introduction to Programming',         4, 1, 'I',  1),
  ('IT1102','Computer Fundamentals',               3, 1, 'I',  1),
  ('IT1103','Mathematics for Computing',           3, 1, 'I',  1),
  ('IT2201','Data Structures & Algorithms',        4, 1, 'I',  2),
  ('IT2202','Database Systems',                    4, 1, 'II', 2),
  ('IT3301','Web Technologies',                    4, 1, 'I',  3),
  ('IT3302','Network Administration',              3, 1, 'I',  3),
  ('IT4401','Software Project Management',         3, 1, 'I',  4),
  ('SE1101','Object-Oriented Programming',         4, 2, 'I',  1),
  ('SE2201','Software Design & Architecture',      4, 2, 'I',  2),
  ('CS1101','Discrete Mathematics',                3, 3, 'I',  1),
  ('CS2201','Algorithms & Complexity',             4, 3, 'I',  2),
  ('IS1101','Information Systems Fundamentals',    3, 4, 'I',  1),
  ('IS2201','Systems Analysis & Design',           4, 4, 'I',  2);

-- ============================================================
-- DEMO SEED DATA — Lecturers, Students, Sessions & Attendance
-- Lets you log in and see a fully populated system immediately.
-- All lecturer demo accounts share the password: Lecturer@123
-- ============================================================

-- ── Demo Lecturers (one per program) ───────────────────────────
-- IT  -> John Mukasa     (john.mukasa@university.ac.ug)
-- SE  -> Grace Nakato    (grace.nakato@university.ac.ug)
-- CS  -> Peter Okello    (peter.okello@university.ac.ug)
-- IS  -> Sarah Among     (sarah.among@university.ac.ug)
INSERT INTO `users` (`employee_id`,`first_name`,`last_name`,`email`,`phone`,`password_hash`,`role`,`program_id`,`created_by`) VALUES
  ('LEC-2026-0001','John','Mukasa','john.mukasa@university.ac.ug','+256700111222',
   '$2y$12$MdzoyLLNiWbFtSWzeYsHTOQelB2qYJ1wlVnrrbVCUl4Ty7aJ.f1VS','lecturer',1,1),
  ('LEC-2026-0002','Grace','Nakato','grace.nakato@university.ac.ug','+256700222333',
   '$2y$12$.Ev6VDhrs01H29eeyO0w4e39azVeTPzfVSC8U2Tqmaw4elOVWRCUi','lecturer',2,1),
  ('LEC-2026-0003','Peter','Okello','peter.okello@university.ac.ug','+256700333444',
   '$2y$12$hMpqJVdEeEn015UVwwHe6u6v3thcwiQCcUoz572EED/BVHS.R9XES','lecturer',3,1),
  ('LEC-2026-0004','Sarah','Among','sarah.among@university.ac.ug','+256700444555',
   '$2y$12$q7TM8oL.H7AsOd8MubdlYeT4EJLpCMWJ.CE8u4jjm4CvcL87XwwOS','lecturer',4,1);

-- ── Lecturer ↔ Course Unit assignments (Academic Year 2025/2026, Sem I) ─
INSERT INTO `lecturer_course_assignments` (`lecturer_id`,`course_unit_id`,`academic_year`,`semester`,`assigned_by`) VALUES
  -- John Mukasa (IT) teaches Intro to Programming & Data Structures
  (2, (SELECT id FROM course_units WHERE code='IT1101'), '2025/2026', 'I', 1),
  (2, (SELECT id FROM course_units WHERE code='IT2201'), '2025/2026', 'I', 1),
  -- Grace Nakato (SE) teaches OOP
  (3, (SELECT id FROM course_units WHERE code='SE1101'), '2025/2026', 'I', 1),
  -- Peter Okello (CS) teaches Discrete Mathematics
  (4, (SELECT id FROM course_units WHERE code='CS1101'), '2025/2026', 'I', 1),
  -- Sarah Among (IS) teaches Information Systems Fundamentals
  (5, (SELECT id FROM course_units WHERE code='IS1101'), '2025/2026', 'I', 1);

-- ── Demo Students (IT program, Year 1, added by John Mukasa = user id 2) ─
INSERT INTO `students` (`student_number`,`first_name`,`last_name`,`email`,`phone`,`gender`,`program_id`,`year_of_study`,`semester`,`academic_year`,`created_by`) VALUES
  ('IT20260001','Allan','Ssemwogerere','allan.ssem@student.ac.ug','+256701000001','Male',  1,1,'I','2025/2026',2),
  ('IT20260002','Brenda','Achieng',     'brenda.achieng@student.ac.ug','+256701000002','Female',1,1,'I','2025/2026',2),
  ('IT20260003','Collins','Tumwine',    'collins.tumwine@student.ac.ug','+256701000003','Male',  1,1,'I','2025/2026',2),
  ('IT20260004','Diana','Nabwire',      'diana.nabwire@student.ac.ug','+256701000004','Female',1,1,'I','2025/2026',2),
  ('IT20260005','Edwin','Kato',         'edwin.kato@student.ac.ug','+256701000005','Male',  1,1,'I','2025/2026',2),
  ('IT20260006','Faith','Nansubuga',    'faith.nansubuga@student.ac.ug','+256701000006','Female',1,1,'I','2025/2026',2);

-- ── Enroll all 6 IT students into IT1101 (Intro to Programming) ─────────
INSERT INTO `student_course_enrollments` (`student_id`,`course_unit_id`,`academic_year`,`semester`,`enrolled_by`)
SELECT s.id, (SELECT id FROM course_units WHERE code='IT1101'), '2025/2026', 'I', 2
FROM students s WHERE s.student_number LIKE 'IT2026%';

-- ── 6 lecture sessions for IT1101, spread across recent weeks ──────────
INSERT INTO `lecture_sessions` (`course_unit_id`,`lecturer_id`,`session_date`,`start_time`,`end_time`,`topic`,`venue`,`academic_year`,`semester`) VALUES
  ((SELECT id FROM course_units WHERE code='IT1101'), 2, CURDATE() - INTERVAL 25 DAY, '08:00:00','10:00:00','Introduction to Variables','Lab 1','2025/2026','I'),
  ((SELECT id FROM course_units WHERE code='IT1101'), 2, CURDATE() - INTERVAL 18 DAY, '08:00:00','10:00:00','Conditional Statements','Lab 1','2025/2026','I'),
  ((SELECT id FROM course_units WHERE code='IT1101'), 2, CURDATE() - INTERVAL 12 DAY, '08:00:00','10:00:00','Loops and Iteration','Lab 1','2025/2026','I'),
  ((SELECT id FROM course_units WHERE code='IT1101'), 2, CURDATE() - INTERVAL 7  DAY, '08:00:00','10:00:00','Functions and Scope','Lab 1','2025/2026','I'),
  ((SELECT id FROM course_units WHERE code='IT1101'), 2, CURDATE() - INTERVAL 3  DAY, '08:00:00','10:00:00','Arrays and Lists','Lab 1','2025/2026','I'),
  ((SELECT id FROM course_units WHERE code='IT1101'), 2, CURDATE(),                   '08:00:00','10:00:00','Introduction to Loops Recap','Lab 1','2025/2026','I');

-- ── Attendance records ──────────────────────────────────────────────────
-- Allan, Brenda, Collins, Diana: consistently present (healthy attendance)
-- Edwin: misses exactly 4 sessions -> trigger auto-flags him
-- Faith: misses all 6 sessions -> trigger auto-flags her, worse reason
--
-- NOTE: We resolve all session/student IDs into variables first, then run
-- plain literal INSERT statements. This avoids MySQL error #1442
-- ("Can't update table 'students' in stored function/trigger because it
-- is already used by statement which invoked this stored function/trigger"),
-- which occurs if a single INSERT...SELECT both reads from `students` and
-- triggers an UPDATE on `students` within the same statement.

SET @cu_it1101 := (SELECT id FROM course_units WHERE code='IT1101');

SET @s_allan   := (SELECT id FROM students WHERE student_number='IT20260001');
SET @s_brenda  := (SELECT id FROM students WHERE student_number='IT20260002');
SET @s_collins := (SELECT id FROM students WHERE student_number='IT20260003');
SET @s_diana   := (SELECT id FROM students WHERE student_number='IT20260004');
SET @s_edwin   := (SELECT id FROM students WHERE student_number='IT20260005');
SET @s_faith   := (SELECT id FROM students WHERE student_number='IT20260006');

SET @sess1 := (SELECT id FROM lecture_sessions WHERE course_unit_id=@cu_it1101 AND topic='Introduction to Variables');
SET @sess2 := (SELECT id FROM lecture_sessions WHERE course_unit_id=@cu_it1101 AND topic='Conditional Statements');
SET @sess3 := (SELECT id FROM lecture_sessions WHERE course_unit_id=@cu_it1101 AND topic='Loops and Iteration');
SET @sess4 := (SELECT id FROM lecture_sessions WHERE course_unit_id=@cu_it1101 AND topic='Functions and Scope');
SET @sess5 := (SELECT id FROM lecture_sessions WHERE course_unit_id=@cu_it1101 AND topic='Arrays and Lists');
SET @sess6 := (SELECT id FROM lecture_sessions WHERE course_unit_id=@cu_it1101 AND topic='Introduction to Loops Recap');

-- Allan, Brenda, Collins, Diana — present at every session
INSERT INTO `attendance_records` (`session_id`,`student_id`,`status`,`marked_by`) VALUES
  (@sess1, @s_allan,   'present', 2), (@sess2, @s_allan,   'present', 2), (@sess3, @s_allan,   'present', 2),
  (@sess4, @s_allan,   'present', 2), (@sess5, @s_allan,   'present', 2), (@sess6, @s_allan,   'present', 2),

  (@sess1, @s_brenda,  'present', 2), (@sess2, @s_brenda,  'present', 2), (@sess3, @s_brenda,  'present', 2),
  (@sess4, @s_brenda,  'present', 2), (@sess5, @s_brenda,  'present', 2), (@sess6, @s_brenda,  'present', 2),

  (@sess1, @s_collins, 'present', 2), (@sess2, @s_collins, 'present', 2), (@sess3, @s_collins, 'present', 2),
  (@sess4, @s_collins, 'present', 2), (@sess5, @s_collins, 'present', 2), (@sess6, @s_collins, 'present', 2),

  (@sess1, @s_diana,   'present', 2), (@sess2, @s_diana,   'present', 2), (@sess3, @s_diana,   'present', 2),
  (@sess4, @s_diana,   'present', 2), (@sess5, @s_diana,   'present', 2), (@sess6, @s_diana,   'present', 2);

-- Edwin — absent for the first 4 sessions (hits the 4-absence flag threshold), present for the last 2
INSERT INTO `attendance_records` (`session_id`,`student_id`,`status`,`marked_by`) VALUES
  (@sess1, @s_edwin, 'absent',  2),
  (@sess2, @s_edwin, 'absent',  2),
  (@sess3, @s_edwin, 'absent',  2),
  (@sess4, @s_edwin, 'absent',  2),
  (@sess5, @s_edwin, 'present', 2),
  (@sess6, @s_edwin, 'present', 2);

-- Faith — absent for every session (never attended)
INSERT INTO `attendance_records` (`session_id`,`student_id`,`status`,`marked_by`) VALUES
  (@sess1, @s_faith, 'absent', 2),
  (@sess2, @s_faith, 'absent', 2),
  (@sess3, @s_faith, 'absent', 2),
  (@sess4, @s_faith, 'absent', 2),
  (@sess5, @s_faith, 'absent', 2),
  (@sess6, @s_faith, 'absent', 2);
