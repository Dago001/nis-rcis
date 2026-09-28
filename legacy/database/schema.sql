-- =======================================================
-- NIS RESIDENCE CARD ISSUANCE SYSTEM (NIS-RCIS)
-- Database Schema for MySQL / MariaDB
-- =======================================================

CREATE DATABASE IF NOT EXISTS `nis_rcis_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nis_rcis_db`;

-- -------------------------------------------------------
-- Table: users
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `fullname` VARCHAR(100) NOT NULL,
    `service_number` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('SuperAdmin', 'ApprovingOfficer', 'IssuingOfficer', 'Inspector', 'Auditor') NOT NULL DEFAULT 'IssuingOfficer',
    `command` VARCHAR(100) NOT NULL DEFAULT 'Abuja Central Enrollment Center',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: residence_cards
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `residence_cards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `card_number` VARCHAR(50) NOT NULL UNIQUE,
    `booklet_number` VARCHAR(50) NOT NULL,
    `issuing_country` VARCHAR(100) NOT NULL DEFAULT 'FEDERAL REPUBLIC OF NIGERIA',
    `statutory_protocol` VARCHAR(150) NOT NULL DEFAULT '',
    `decision_reference` VARCHAR(100) NOT NULL DEFAULT '',
    `decision_date` DATE NOT NULL,
    `approving_authority` VARCHAR(100) NOT NULL DEFAULT 'COMPTROLLER GENERAL OF IMMIGRATION',
    
    -- Page 3: Biometrics & Profile
    `surname` VARCHAR(100) NOT NULL,
    `forenames` VARCHAR(100) NOT NULL,
    `photo_path` VARCHAR(255) NULL,
    `nationality` VARCHAR(100) NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `place_of_birth` VARCHAR(100) NOT NULL,
    `sex` VARCHAR(20) NOT NULL,
    `height` VARCHAR(30) NOT NULL,
    `complexion` VARCHAR(50) NOT NULL,
    `eye_color` VARCHAR(50) NOT NULL,
    `hair_color` VARCHAR(50) NOT NULL,
    `distinguished_features` VARCHAR(150) NOT NULL DEFAULT 'NONE',
    `profession` VARCHAR(100) NOT NULL,
    `domicile` TEXT NOT NULL,
    `passport_number` VARCHAR(50) NOT NULL,
    `national_id_number` VARCHAR(50) NULL,
    `tax_id_number` VARCHAR(50) NULL,
    
    -- Page 4: Emergency & Endorsement
    `emergency_contact_name` VARCHAR(100) NOT NULL,
    `emergency_contact_relation` VARCHAR(50) NOT NULL,
    `emergency_contact_phone` VARCHAR(50) NOT NULL,
    `emergency_contact_address` TEXT NOT NULL,
    `blood_group` VARCHAR(20) NOT NULL DEFAULT 'UNKNOWN',
    `change_of_address` TEXT NULL,
    `issuing_officer_name` VARCHAR(100) NOT NULL,
    `issuing_officer_service_no` VARCHAR(50) NOT NULL,
    `issuing_officer_signature` TEXT NULL,
    
    -- Page 5: Validity & Renewals
    `issued_on` DATE NOT NULL,
    `issued_at` VARCHAR(100) NOT NULL,
    `expires_on` DATE NOT NULL,
    `postage_stamp_code` VARCHAR(50) NULL,
    `authority_signature` VARCHAR(100) NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'ISSUED',
    `verification_token` VARCHAR(64) NOT NULL UNIQUE,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: card_renewals
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `card_renewals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `card_id` INT NOT NULL,
    `renewal_number` INT NOT NULL DEFAULT 1,
    `from_date` DATE NOT NULL,
    `to_date` DATE NOT NULL,
    `renewed_at` VARCHAR(100) NOT NULL,
    `endorsing_officer` VARCHAR(100) NOT NULL,
    `officer_service_no` VARCHAR(50) NOT NULL,
    `fee_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `receipt_number` VARCHAR(50) NULL,
    `remarks` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`card_id`) REFERENCES `residence_cards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: audit_logs
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `username` VARCHAR(50) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `card_id` INT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: notifications
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notif_user_read` (`user_id`, `is_read`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: applications (Public Online Applications)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `applications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `application_number` VARCHAR(50) NOT NULL UNIQUE,
    `reference_number` VARCHAR(50) NOT NULL UNIQUE,
    `surname` VARCHAR(100) NOT NULL,
    `forenames` VARCHAR(100) NOT NULL,
    `nationality` VARCHAR(100) NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `place_of_birth` VARCHAR(100) NOT NULL,
    `sex` VARCHAR(20) NOT NULL,
    `height` VARCHAR(30) NULL,
    `complexion` VARCHAR(50) NULL,
    `eye_color` VARCHAR(50) NULL,
    `hair_color` VARCHAR(50) NULL,
    `profession` VARCHAR(100) NOT NULL,
    `domicile` TEXT NOT NULL,
    `change_of_address` TEXT NULL,
    `passport_number` VARCHAR(50) NOT NULL,
    `passport_expiry` DATE NULL,
    `national_id_number` VARCHAR(50) NULL,
    `tax_id_number` VARCHAR(50) NULL,
    `phone` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `emergency_contact_name` VARCHAR(100) NOT NULL,
    `emergency_contact_relation` VARCHAR(50) NOT NULL,
    `emergency_contact_phone` VARCHAR(50) NOT NULL,
    `emergency_contact_address` TEXT NOT NULL,
    `blood_group` VARCHAR(20) DEFAULT 'UNKNOWN',
    `enrollment_center` VARCHAR(100) NOT NULL DEFAULT 'Abuja Central Enrollment Center',
    `appointment_date` DATE NOT NULL,
    `appointment_time` VARCHAR(20) NOT NULL DEFAULT '09:00 AM',
    `fee_amount` DECIMAL(10,2) NOT NULL DEFAULT 35000.00,
    `payment_status` VARCHAR(20) NOT NULL DEFAULT 'PAID',
    `payment_date` DATETIME NULL,
    `payment_reference` VARCHAR(50) NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'PENDING_APPROVAL',
    `approved_by` INT NULL,
    `approved_at` DATETIME NULL,
    `approval_notes` TEXT NULL,
    `photo_path` VARCHAR(255) NULL,
    `signature_path` VARCHAR(255) NULL,
    `fingerprint_data` TEXT NULL,
    `doc_passport_copy` VARCHAR(255) NULL,
    `doc_residence_visa` VARCHAR(255) NULL,
    `doc_quota_approval` VARCHAR(255) NULL,
    `doc_domicile_proof` VARCHAR(255) NULL,
    `doc_additional` VARCHAR(255) NULL,
    `biometrics_captured_by` INT NULL,
    `biometrics_captured_at` DATETIME NULL,
    `card_id` INT NULL,
    `card_ready_notified` TINYINT(1) DEFAULT 0,
    `card_ready_notified_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_app_status` (`status`),
    INDEX `idx_app_passport` (`passport_number`),
    INDEX `idx_app_appnum` (`application_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
