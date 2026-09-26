<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Database Connection & Migration Bootstrapper (PDO Dual-Engine: MySQL & SQLite)
 */

require_once __DIR__ . '/constants.php';

class Database {
    private static ?PDO $instance = null;
    private static string $driverUsed = 'mysql';

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        // 1. Attempt MySQL / MariaDB connection first
        $mysqlHost = '127.0.0.1';
        $mysqlPort = '3306';
        $mysqlDb   = 'nis_rcis_db';
        $mysqlUser = 'root';
        $mysqlPass = '';

        try {
            // First connect without DB to check / create DB if not exists
            $pdoRoot = new PDO(
                "mysql:host={$mysqlHost};port={$mysqlPort};charset=utf8mb4",
                $mysqlUser,
                $mysqlPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 2
                ]
            );
            $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$mysqlDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            unset($pdoRoot);

            // Connect to the specific database
            $pdo = new PDO(
                "mysql:host={$mysqlHost};port={$mysqlPort};dbname={$mysqlDb};charset=utf8mb4",
                $mysqlUser,
                $mysqlPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            self::$driverUsed = 'mysql';
            self::$instance = $pdo;
            self::initializeTables($pdo, 'mysql');
            return self::$instance;
        } catch (Throwable $e) {
            // MySQL unavailable or offline -> Fallback to SQLite seamlessly!
            $sqliteDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
            if (!is_dir($sqliteDir)) {
                @mkdir($sqliteDir, 0777, true);
            }
            $sqliteFile = $sqliteDir . DIRECTORY_SEPARATOR . 'rcis.sqlite';

            $pdo = new PDO("sqlite:" . $sqliteFile, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            self::$driverUsed = 'sqlite';
            self::$instance = $pdo;
            self::initializeTables($pdo, 'sqlite');
            return self::$instance;
        }
    }

    public static function getDriver(): string {
        return self::$driverUsed;
    }

    private static function initializeTables(PDO $pdo, string $driver): void {
        if ($driver === 'sqlite') {
            $pdo->exec("PRAGMA foreign_keys = ON;");
            
            // Users table
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                fullname TEXT NOT NULL,
                service_number TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'IssuingOfficer',
                command TEXT NOT NULL DEFAULT 'Abuja Central Enrollment Center',
                is_active INTEGER NOT NULL DEFAULT 1,
                photo_path TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME NULL
            );");

            // Residence Cards table
            $pdo->exec("CREATE TABLE IF NOT EXISTS residence_cards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                card_number TEXT NOT NULL UNIQUE,
                booklet_number TEXT NOT NULL,
                issuing_country TEXT NOT NULL DEFAULT 'FEDERAL REPUBLIC OF NIGERIA',
                statutory_protocol TEXT NOT NULL DEFAULT '',
                decision_reference TEXT NOT NULL DEFAULT '',
                decision_date TEXT NOT NULL,
                approving_authority TEXT NOT NULL DEFAULT 'COMPTROLLER GENERAL OF IMMIGRATION',
                surname TEXT NOT NULL,
                forenames TEXT NOT NULL,
                photo_path TEXT NULL,
                nationality TEXT NOT NULL,
                date_of_birth TEXT NOT NULL,
                place_of_birth TEXT NOT NULL,
                sex TEXT NOT NULL,
                height TEXT NOT NULL,
                complexion TEXT NOT NULL,
                eye_color TEXT NOT NULL,
                hair_color TEXT NOT NULL,
                distinguished_features TEXT NOT NULL DEFAULT 'NONE',
                profession TEXT NOT NULL,
                domicile TEXT NOT NULL,
                passport_number TEXT NOT NULL,
                national_id_number TEXT NULL,
                tax_id_number TEXT NULL,
                emergency_contact_name TEXT NOT NULL,
                emergency_contact_relation TEXT NOT NULL,
                emergency_contact_phone TEXT NOT NULL,
                emergency_contact_address TEXT NOT NULL,
                blood_group TEXT NOT NULL DEFAULT 'UNKNOWN',
                change_of_address TEXT NULL,
                issuing_officer_name TEXT NOT NULL,
                issuing_officer_service_no TEXT NOT NULL,
                issuing_officer_signature TEXT NULL,
                issued_on TEXT NOT NULL,
                issued_at TEXT NOT NULL,
                expires_on TEXT NOT NULL,
                postage_stamp_code TEXT NULL,
                authority_signature TEXT NULL,
                status TEXT NOT NULL DEFAULT 'ISSUED',
                verification_token TEXT NOT NULL UNIQUE,
                revocation_reason TEXT NULL,
                revoked_by INTEGER NULL,
                revoked_at DATETIME NULL,
                is_watchlisted INTEGER NOT NULL DEFAULT 0,
                watchlist_reason TEXT NULL,
                watchlisted_by INTEGER NULL,
                watchlisted_at DATETIME NULL,
                approved_by INTEGER NULL,
                approved_at DATETIME NULL,
                rejection_reason TEXT NULL,
                created_by INTEGER NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            );");

            // Card Renewals table
            $pdo->exec("CREATE TABLE IF NOT EXISTS card_renewals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                card_id INTEGER NOT NULL,
                renewal_number INTEGER NOT NULL DEFAULT 1,
                from_date TEXT NOT NULL,
                to_date TEXT NOT NULL,
                renewed_at TEXT NOT NULL,
                endorsing_officer TEXT NOT NULL,
                officer_service_no TEXT NOT NULL,
                fee_paid REAL NOT NULL DEFAULT 0.00,
                receipt_number TEXT NULL,
                remarks TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (card_id) REFERENCES residence_cards(id) ON DELETE CASCADE
            );");

            // Audit Logs table
            $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                username TEXT NOT NULL,
                action TEXT NOT NULL,
                card_id INTEGER NULL,
                details TEXT NULL,
                ip_address TEXT NOT NULL,
                user_agent TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );");

            // Notifications table
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                link TEXT NULL,
                is_read INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );");

            // Public Online Applications table
            $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                application_number TEXT NOT NULL UNIQUE,
                reference_number TEXT NOT NULL UNIQUE,
                surname TEXT NOT NULL,
                forenames TEXT NOT NULL,
                nationality TEXT NOT NULL,
                date_of_birth TEXT NOT NULL,
                place_of_birth TEXT NOT NULL,
                sex TEXT NOT NULL,
                height TEXT NULL,
                complexion TEXT NULL,
                eye_color TEXT NULL,
                hair_color TEXT NULL,
                profession TEXT NOT NULL,
                domicile TEXT NOT NULL,
                change_of_address TEXT NULL,
                passport_number TEXT NOT NULL,
                passport_issue_date TEXT NULL,
                passport_expiry TEXT NULL,
                national_id_number TEXT NULL,
                tax_id_number TEXT NULL,
                phone TEXT NOT NULL,
                email TEXT NOT NULL,
                emergency_contact_name TEXT NOT NULL,
                emergency_contact_relation TEXT NOT NULL,
                emergency_contact_phone TEXT NOT NULL,
                emergency_contact_address TEXT NOT NULL,
                blood_group TEXT DEFAULT 'UNKNOWN',
                enrollment_center TEXT NOT NULL DEFAULT 'Abuja Central Enrollment Center',
                appointment_date TEXT NOT NULL,
                appointment_time TEXT NOT NULL DEFAULT '09:00 AM',
                fee_amount REAL NOT NULL DEFAULT 35000.00,
                payment_status TEXT NOT NULL DEFAULT 'PAID',
                payment_method TEXT NULL,
                payment_date DATETIME NULL,
                payment_reference TEXT NULL,
                status TEXT NOT NULL DEFAULT 'PENDING_APPROVAL',
                approved_by INTEGER NULL,
                approved_at DATETIME NULL,
                approval_notes TEXT NULL,
                photo_path TEXT NULL,
                signature_path TEXT NULL,
                fingerprint_data TEXT NULL,
                doc_passport_copy TEXT NULL,
                doc_residence_visa TEXT NULL,
                doc_quota_approval TEXT NULL,
                doc_domicile_proof TEXT NULL,
                doc_additional TEXT NULL,
                biometrics_captured_by INTEGER NULL,
                biometrics_captured_at DATETIME NULL,
                card_id INTEGER NULL,
                card_ready_notified INTEGER DEFAULT 0,
                card_ready_notified_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );");

        } else {
            // MySQL tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `fullname` VARCHAR(100) NOT NULL,
                `service_number` VARCHAR(50) NOT NULL UNIQUE,
                `email` VARCHAR(100) NOT NULL,
                `password_hash` VARCHAR(255) NOT NULL,
                `role` VARCHAR(50) NOT NULL DEFAULT 'IssuingOfficer',
                `command` VARCHAR(100) NOT NULL DEFAULT 'Abuja Central Enrollment Center',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `photo_path` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `last_login` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `residence_cards` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `card_number` VARCHAR(50) NOT NULL UNIQUE,
                `booklet_number` VARCHAR(50) NOT NULL,
                `issuing_country` VARCHAR(100) NOT NULL DEFAULT 'FEDERAL REPUBLIC OF NIGERIA',
                `statutory_protocol` VARCHAR(150) NOT NULL DEFAULT '',
                `decision_reference` VARCHAR(100) NOT NULL DEFAULT '',
                `decision_date` DATE NOT NULL,
                `approving_authority` VARCHAR(100) NOT NULL DEFAULT 'COMPTROLLER GENERAL OF IMMIGRATION',
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
                `emergency_contact_name` VARCHAR(100) NOT NULL,
                `emergency_contact_relation` VARCHAR(50) NOT NULL,
                `emergency_contact_phone` VARCHAR(50) NOT NULL,
                `emergency_contact_address` TEXT NOT NULL,
                `blood_group` VARCHAR(20) NOT NULL DEFAULT 'UNKNOWN',
                `change_of_address` TEXT NULL,
                `issuing_officer_name` VARCHAR(100) NOT NULL,
                `issuing_officer_service_no` VARCHAR(50) NOT NULL,
                `issuing_officer_signature` TEXT NULL,
                `issued_on` DATE NOT NULL,
                `issued_at` VARCHAR(100) NOT NULL,
                `expires_on` DATE NOT NULL,
                `postage_stamp_code` VARCHAR(50) NULL,
                `authority_signature` VARCHAR(100) NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'ISSUED',
                `verification_token` VARCHAR(64) NOT NULL UNIQUE,
                `revocation_reason` VARCHAR(255) NULL,
                `revoked_by` INT NULL,
                `revoked_at` DATETIME NULL,
                `is_watchlisted` TINYINT(1) NOT NULL DEFAULT 0,
                `watchlist_reason` VARCHAR(255) NULL,
                `watchlisted_by` INT NULL,
                `watchlisted_at` DATETIME NULL,
                `approved_by` INT NULL,
                `approved_at` DATETIME NULL,
                `rejection_reason` VARCHAR(255) NULL,
                `created_by` INT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `card_renewals` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NULL,
                `username` VARCHAR(50) NOT NULL,
                `action` VARCHAR(100) NOT NULL,
                `card_id` INT NULL,
                `details` TEXT NULL,
                `ip_address` VARCHAR(45) NOT NULL,
                `user_agent` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Notifications table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `type` VARCHAR(50) NOT NULL,
                `title` VARCHAR(150) NOT NULL,
                `message` TEXT NOT NULL,
                `link` VARCHAR(255) NULL,
                `is_read` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_notif_user_read` (`user_id`, `is_read`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Public Online Applications table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `applications` (
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
                `passport_issue_date` DATE NULL,
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
                `payment_method` VARCHAR(50) NULL,
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }

        // Migration: Ensure photo_path column exists in users table
        try {
            if ($driver === 'sqlite') {
                $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
                $hasPhoto = false;
                foreach ($cols as $col) {
                    if (($col['name'] ?? '') === 'photo_path') { $hasPhoto = true; break; }
                }
                if (!$hasPhoto) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN photo_path TEXT NULL;");
                }
            } else {
                $cols = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'photo_path'")->fetchAll(PDO::FETCH_ASSOC);
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE `users` ADD COLUMN `photo_path` VARCHAR(255) NULL;");
                }
            }
        } catch (Throwable $e) {
            // Ignore if already exists
        }

        // Migration: Ensure approval, revocation, and watchlist columns exist in residence_cards table
        try {
            $newCardCols = [
                'revocation_reason' => ['sqlite' => 'TEXT NULL', 'mysql' => 'VARCHAR(255) NULL'],
                'revoked_by'        => ['sqlite' => 'INTEGER NULL', 'mysql' => 'INT NULL'],
                'revoked_at'        => ['sqlite' => 'DATETIME NULL', 'mysql' => 'DATETIME NULL'],
                'is_watchlisted'    => ['sqlite' => 'INTEGER NOT NULL DEFAULT 0', 'mysql' => 'TINYINT(1) NOT NULL DEFAULT 0'],
                'watchlist_reason'  => ['sqlite' => 'TEXT NULL', 'mysql' => 'VARCHAR(255) NULL'],
                'watchlisted_by'    => ['sqlite' => 'INTEGER NULL', 'mysql' => 'INT NULL'],
                'watchlisted_at'    => ['sqlite' => 'DATETIME NULL', 'mysql' => 'DATETIME NULL'],
                'approved_by'       => ['sqlite' => 'INTEGER NULL', 'mysql' => 'INT NULL'],
                'approved_at'       => ['sqlite' => 'DATETIME NULL', 'mysql' => 'DATETIME NULL'],
                'rejection_reason'  => ['sqlite' => 'TEXT NULL', 'mysql' => 'VARCHAR(255) NULL'],
            ];

            if ($driver === 'sqlite') {
                $cols = $pdo->query("PRAGMA table_info(residence_cards)")->fetchAll(PDO::FETCH_ASSOC);
                $existingCols = array_column($cols, 'name');
                foreach ($newCardCols as $colName => $typeDef) {
                    if (!in_array($colName, $existingCols, true)) {
                        $pdo->exec("ALTER TABLE residence_cards ADD COLUMN {$colName} {$typeDef['sqlite']};");
                    }
                }
            } else {
                $cols = $pdo->query("SHOW COLUMNS FROM `residence_cards`")->fetchAll(PDO::FETCH_ASSOC);
                $existingCols = array_column($cols, 'Field');
                foreach ($newCardCols as $colName => $typeDef) {
                    if (!in_array($colName, $existingCols, true)) {
                        $pdo->exec("ALTER TABLE `residence_cards` ADD COLUMN `{$colName}` {$typeDef['mysql']};");
                    }
                }
            }
        } catch (Throwable $e) {
            // Ignore if migration fails
        }

        // Migration: Ensure supporting document upload and change_of_address columns exist in applications table
        try {
            $appDocCols = [
                'change_of_address'  => ['sqlite' => 'TEXT NULL', 'mysql' => 'TEXT NULL'],
                'doc_passport_copy'  => ['sqlite' => 'TEXT NULL', 'mysql' => 'VARCHAR(255) NULL'],
                'doc_residence_visa' => ['sqlite' => 'TEXT NULL', 'mysql' => 'VARCHAR(255) NULL'],
                'doc_quota_approval' => ['sqlite' => 'TEXT NULL', 'mysql' => 'VARCHAR(255) NULL'],
                'doc_domicile_proof' => ['sqlite' => 'TEXT NULL', 'mysql' => 'VARCHAR(255) NULL'],
                'doc_additional'     => ['sqlite' => 'TEXT NULL', 'mysql' => 'VARCHAR(255) NULL'],
            ];

            if ($driver === 'sqlite') {
                $cols = $pdo->query("PRAGMA table_info(applications)")->fetchAll(PDO::FETCH_ASSOC);
                $existingCols = array_column($cols, 'name');
                foreach ($appDocCols as $colName => $typeDef) {
                    if (!in_array($colName, $existingCols, true)) {
                        $pdo->exec("ALTER TABLE applications ADD COLUMN {$colName} {$typeDef['sqlite']};");
                    }
                }
            } else {
                $cols = $pdo->query("SHOW COLUMNS FROM `applications`")->fetchAll(PDO::FETCH_ASSOC);
                $existingCols = array_column($cols, 'Field');
                foreach ($appDocCols as $colName => $typeDef) {
                    if (!in_array($colName, $existingCols, true)) {
                        $pdo->exec("ALTER TABLE `applications` ADD COLUMN `{$colName}` {$typeDef['mysql']};");
                    }
                }
            }
        } catch (Throwable $e) {
            // Ignore if migration fails
        }

        // Migration: Ensure applicant profile table and applicant_id in applications exist
        try {
            if ($driver === 'sqlite') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS applicants (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    surname TEXT NOT NULL,
                    forenames TEXT NOT NULL,
                    nationality TEXT NOT NULL,
                    passport_number TEXT NOT NULL,
                    phone TEXT NOT NULL,
                    photo_path TEXT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );");

                $cols = $pdo->query("PRAGMA table_info(applications)")->fetchAll(PDO::FETCH_ASSOC);
                $existingCols = array_column($cols, 'name');
                if (!in_array('applicant_id', $existingCols, true)) {
                    $pdo->exec("ALTER TABLE applications ADD COLUMN applicant_id INTEGER NULL;");
                }
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `applicants` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `email` VARCHAR(191) NOT NULL UNIQUE,
                    `password_hash` VARCHAR(255) NOT NULL,
                    `surname` VARCHAR(100) NOT NULL,
                    `forenames` VARCHAR(100) NOT NULL,
                    `nationality` VARCHAR(100) NOT NULL,
                    `passport_number` VARCHAR(50) NOT NULL,
                    `phone` VARCHAR(50) NOT NULL,
                    `photo_path` VARCHAR(255) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_applicant_email` (`email`),
                    INDEX `idx_applicant_passport` (`passport_number`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                $cols = $pdo->query("SHOW COLUMNS FROM `applications` LIKE 'applicant_id'")->fetchAll(PDO::FETCH_ASSOC);
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE `applications` ADD COLUMN `applicant_id` INT NULL;");
                }
            }
        } catch (Throwable $e) {
            // Ignore if migration fails
        }

        // Automatic rank sanitation for default user accounts
        try {
            $pdo->exec("UPDATE users SET fullname = 'Ibrahim Musa' WHERE service_number = '24820'");
            $pdo->exec("UPDATE users SET fullname = 'Ngozi Adeleke' WHERE service_number = '19102'");
            $pdo->exec("UPDATE users SET fullname = 'Chinedu Okoro' WHERE service_number = '17731'");
        } catch (Throwable $e) {
            // Ignore
        }

        // Seed Default Users if none exist
        $userCheck = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ((int)$userCheck === 0) {
            $defaultUsers = [
                [
                    'username' => '39031',
                    'fullname' => 'Adamma Eze',
                    'service_number' => '39031',
                    'email' => 'ezeab.nis@gmail.com',
                    'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
                    'role' => ROLE_SUPER_ADMIN,
                    'command' => 'Abuja Central Enrollment Center'
                ],
                [
                    'username' => '24820',
                    'fullname' => 'Ibrahim Musa',
                    'service_number' => '24820',
                    'email' => 'i.musa@immigration.gov.ng',
                    'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
                    'role' => ROLE_APPROVING_OFFICER,
                    'command' => 'Abuja Central Enrollment Center'
                ],
                [
                    'username' => '19102',
                    'fullname' => 'Ngozi Adeleke',
                    'service_number' => '19102',
                    'email' => 'n.adeleke@immigration.gov.ng',
                    'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
                    'role' => ROLE_ISSUING_OFFICER,
                    'command' => 'Lagos State Enrollment Center, Alausa'
                ],
                [
                    'username' => '17731',
                    'fullname' => 'Chinedu Okoro',
                    'service_number' => '17731',
                    'email' => 'c.okoro@immigration.gov.ng',
                    'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
                    'role' => ROLE_INSPECTOR,
                    'command' => 'Seme Border Control Post'
                ]
            ];

            $stmt = $pdo->prepare("INSERT INTO users (username, fullname, service_number, email, password_hash, role, command) 
                                   VALUES (:username, :fullname, :service_number, :email, :password_hash, :role, :command)");
            foreach ($defaultUsers as $u) {
                $stmt->execute($u);
            }
        }

        // Seed Sample Residence Card from Uploaded Template (Card No. 389107)
        $cardCheck = $pdo->query("SELECT COUNT(*) FROM residence_cards")->fetchColumn();
        if ((int)$cardCheck === 0) {
            $sampleCard = [
                'card_number' => '389107',
                'booklet_number' => 'RC-389107/19',
                'issuing_country' => 'FEDERAL REPUBLIC OF NIGERIA',
                'statutory_protocol' => '',
                'decision_reference' => '',
                'decision_date' => '2026-01-15',
                'approving_authority' => 'COMPTROLLER GENERAL OF IMMIGRATION',
                'surname' => 'DIOP',
                'forenames' => 'MAMADOU LAMINE',
                'photo_path' => null,
                'nationality' => 'SENEGAL',
                'date_of_birth' => '1988-04-12',
                'place_of_birth' => 'DAKAR',
                'sex' => 'MALE',
                'height' => '1.82 M',
                'complexion' => 'DARK',
                'eye_color' => 'BLACK',
                'hair_color' => 'BLACK',
                'distinguished_features' => 'SMALL SCAR NEAR LEFT EYEBROW',
                'profession' => 'CIVIL ENGINEER',
                'domicile' => '14 ADEMOLA ADETOKUNBO CRESCENT, WUSE II, ABUJA, FCT',
                'passport_number' => 'SN1984021A',
                'national_id_number' => 'NIN-77492019482',
                'tax_id_number' => 'TIN-09482103-0001',
                'emergency_contact_name' => 'AISSATOU DIOP',
                'emergency_contact_relation' => 'SPOUSE',
                'emergency_contact_phone' => '+234 803 456 7890',
                'emergency_contact_address' => '14 ADEMOLA ADETOKUNBO CRESCENT, WUSE II, ABUJA, FCT',
                'blood_group' => 'O+',
                'change_of_address' => 'NO CHANGE RECORDED',
                'issuing_officer_name' => 'NGOZI ADELEKE',
                'issuing_officer_service_no' => '19102',
                'issuing_officer_signature' => 'SIGNED & SEALED',
                'issued_on' => '2026-01-20',
                'issued_at' => 'Abuja Central Enrollment Center',
                'expires_on' => '2028-01-19',
                'postage_stamp_code' => 'NIS-NSPMC-2026-778',
                'authority_signature' => 'IBRAHIM MUSA',
                'status' => 'ISSUED',
                'verification_token' => bin2hex(random_bytes(16)),
                'created_by' => 1
            ];

            $cols = array_keys($sampleCard);
            $colList = implode(', ', $cols);
            $paramList = ':' . implode(', :', $cols);
            $stmt = $pdo->prepare("INSERT INTO residence_cards ({$colList}) VALUES ({$paramList})");
            $stmt->execute($sampleCard);

            $cardId = (int)$pdo->lastInsertId();

            // Insert a renewal sample
            $stmtRenew = $pdo->prepare("INSERT INTO card_renewals (card_id, renewal_number, from_date, to_date, renewed_at, endorsing_officer, officer_service_no, fee_paid, receipt_number, remarks)
                                        VALUES (:card_id, 1, '2028-01-20', '2030-01-19', 'Abuja Central Enrollment Center', 'IBRAHIM MUSA', '24820', 25000.00, 'RRR-2901-8492-1049', 'FIRST RENEWAL GRANTED')");
            $stmtRenew->execute([':card_id' => $cardId]);
        }
    }
}
