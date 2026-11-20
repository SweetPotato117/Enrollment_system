-- Run once in a fresh database using a client that stops on errors.

CREATE DATABASE IF NOT EXISTS `enrollment_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `enrollment_system`;

SET NAMES utf8mb4;

SET SESSION time_zone = '+00:00';

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(254) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=5;

CREATE TABLE `sections` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `curriculum` enum('regular','first','spict','ste','spj','spa','ict') NOT NULL,
  `code` varchar(64) NOT NULL,
  `display_name` varchar(128) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_curriculum_code` (`curriculum`,`code`),
  KEY `idx_sections_curriculum` (`curriculum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=107;

CREATE TABLE `applicants` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `lrn` char(12) NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `middle_name` varchar(120) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `age` int(3) DEFAULT NULL,
  `gender` enum('Male','Female','Other','Prefer not to say') DEFAULT 'Male',
  `citizenship` varchar(100) DEFAULT NULL,
  `current_school` varchar(255) DEFAULT NULL,
  `current_school_address` text DEFAULT NULL,
  `year_level` varchar(32) DEFAULT NULL,
  `curriculum` enum('regular','first','spict','ste','spj','spa','ict') NOT NULL DEFAULT 'regular',
  `section_id` int(10) UNSIGNED DEFAULT NULL,
  `section_text` varchar(128) DEFAULT NULL,
  `father_first` varchar(120) DEFAULT NULL,
  `father_last` varchar(120) DEFAULT NULL,
  `father_contact` varchar(50) DEFAULT NULL,
  `mother_first` varchar(120) DEFAULT NULL,
  `mother_last` varchar(120) DEFAULT NULL,
  `mother_contact` varchar(50) DEFAULT NULL,
  `guardian_name` varchar(180) DEFAULT NULL,
  `guardian_relationship` varchar(80) DEFAULT NULL,
  `guardian_contact` varchar(50) DEFAULT NULL,
  `status` enum('pending','passed','failed','non-attendee','enrolled') DEFAULT 'pending',
  `password_hash` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `is_indigenous` tinyint(1) NOT NULL DEFAULT 0,
  `indigenous_specify` varchar(255) DEFAULT NULL,
  `is_4ps` tinyint(1) NOT NULL DEFAULT 0,
  `fourps_household_id` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `exam_date` date DEFAULT NULL,
  `place_of_birth` varchar(255) DEFAULT NULL,
  `religion` varchar(100) DEFAULT NULL,
  `house_no` varchar(100) DEFAULT NULL,
  `barangay` varchar(255) DEFAULT NULL,
  `municipality` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `report_card_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `report_card_remark` text DEFAULT NULL,
  `moral_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `moral_remark` text DEFAULT NULL,
  `health_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `health_remark` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_lrn_applicants` (`lrn`),
  KEY `idx_applicant_curr_section` (`curriculum`,`section_id`),
  KEY `fk_applicant_section` (`section_id`),
  CONSTRAINT `fk_applicant_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=15;

CREATE TABLE `exam_schedules` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_id` int(10) UNSIGNED NOT NULL,
  `exam_date` date NOT NULL,
  `exam_time` time NOT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `created_by_admin_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_schedule_section` (`section_id`),
  KEY `fk_exam_created_by_admin` (`created_by_admin_id`),
  CONSTRAINT `fk_exam_created_by_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_schedule_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=6;

CREATE TABLE `applicant_curricula` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `curriculum` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_applicant_curriculum` (`applicant_id`,`curriculum`),
  KEY `idx_applicant` (`applicant_id`),
  KEY `idx_curriculum` (`curriculum`),
  CONSTRAINT `fk_applicant_curricula_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=53;

CREATE TABLE `applicant_documents` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `doc_type` enum('report_card','moral_character','health_declaration','other') NOT NULL,
  `file_path` varchar(512) DEFAULT NULL,
  `file_blob` longblob DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_applicant_idx` (`applicant_id`),
  CONSTRAINT `fk_applicant_documents_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `applicant_exam_assignments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_applicant_schedule` (`applicant_id`,`schedule_id`),
  KEY `fk_assign_schedule` (`schedule_id`),
  CONSTRAINT `fk_assign_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `exam_schedules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `students` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `lrn` char(12) NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `middle_name` varchar(120) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` enum('Male','Female','Other','Prefer not to say') DEFAULT 'Male',
  `curriculum` enum('regular','first','spict','ste','spj','spa','ict') NOT NULL DEFAULT 'regular',
  `section_id` int(10) UNSIGNED DEFAULT NULL,
  `year_level` varchar(32) DEFAULT NULL,
  `enrolled_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_lrn_students` (`lrn`),
  KEY `idx_student_curr_section` (`curriculum`,`section_id`),
  KEY `fk_student_section` (`section_id`),
  CONSTRAINT `fk_student_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sections` (`id`, `curriculum`, `code`, `display_name`) VALUES
(1, 'first', 'first-01', 'FIRST Section 1'),
(16, 'ict', 'ict-01', 'ICT Section 1'),
(31, 'regular', 'regular-01-old', 'REGULAR Section 1 (old)'),
(46, 'spa', 'spa-01', 'SPA Section 1'),
(61, 'spict', 'spict-01', 'SPICT Section 1'),
(76, 'spj', 'spj-01', 'SPJ Section 1'),
(91, 'ste', 'ste-01', 'STE Section 1'),
(106, 'regular', 'regular-01', 'REGULAR Section 1');
