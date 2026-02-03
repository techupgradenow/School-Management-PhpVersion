<?php
/**
 * Complete System Migration
 * 1. Populate plan_features
 * 2. Populate role_permissions
 * 3. Create default school
 * 4. Migrate users to global_users
 * 5. Add school_id to domain tables
 * 6. Create missing HIGH priority tables
 */

require_once __DIR__ . '/../../backend/config/env.php';

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         COMPLETE SYSTEM MIGRATION                            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo = new PDO(getDbDsn(), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 300
    ]);
    echo "[OK] Connected to Hostinger\n\n";

    // ========================================================================
    // STEP 1: POPULATE PLAN_FEATURES
    // ========================================================================
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "STEP 1: POPULATING PLAN_FEATURES\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    // Get all plans
    $plans = $pdo->query("SELECT id, code FROM plans")->fetchAll(PDO::FETCH_ASSOC);
    $planMap = array_column($plans, 'id', 'code');

    // Get all features
    $features = $pdo->query("SELECT id, code, is_premium FROM features")->fetchAll(PDO::FETCH_ASSOC);

    // Define which features each plan gets
    $planFeatures = [
        'free' => ['dashboard', 'students', 'teachers', 'classes', 'attendance', 'notifications', 'reports'],
        'basic' => ['dashboard', 'students', 'teachers', 'classes', 'attendance', 'timetable', 'exams', 'syllabus', 'fees', 'notifications', 'reports'],
        'premium' => ['dashboard', 'students', 'teachers', 'classes', 'attendance', 'timetable', 'exams', 'syllabus', 'assignments', 'fees', 'payroll', 'expenses', 'library', 'transport', 'hostel', 'notifications', 'sms', 'email', 'reports', 'advanced_reports', 'export'],
        'enterprise' => [] // Gets everything
    ];

    // Clear existing
    $pdo->exec("DELETE FROM plan_features");

    $stmt = $pdo->prepare("INSERT INTO plan_features (id, plan_id, feature_id, is_enabled, created_by) VALUES (UUID(), ?, ?, 1, 'system')");

    foreach ($plans as $plan) {
        $planCode = $plan['code'];
        $planId = $plan['id'];

        foreach ($features as $feature) {
            // Enterprise gets all features
            if ($planCode === 'enterprise') {
                $stmt->execute([$planId, $feature['id']]);
                continue;
            }

            // Other plans get specific features
            if (in_array($feature['code'], $planFeatures[$planCode])) {
                $stmt->execute([$planId, $feature['id']]);
            }
        }
        echo "  [+] Mapped features for plan: {$planCode}\n";
    }

    $count = $pdo->query("SELECT COUNT(*) FROM plan_features")->fetchColumn();
    echo "\n  [OK] plan_features populated: {$count} rows\n\n";

    // ========================================================================
    // STEP 2: POPULATE ROLE_PERMISSIONS
    // ========================================================================
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "STEP 2: POPULATING ROLE_PERMISSIONS\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    // Get all roles
    $roles = $pdo->query("SELECT id, code FROM roles WHERE school_id IS NULL")->fetchAll(PDO::FETCH_ASSOC);
    $roleMap = array_column($roles, 'id', 'code');

    // Get all permissions
    $permissions = $pdo->query("SELECT id, code FROM permissions")->fetchAll(PDO::FETCH_ASSOC);
    $permMap = array_column($permissions, 'id', 'code');

    // Define role permissions
    $rolePerms = [
        'super_admin' => ['*'], // All permissions
        'school_admin' => ['*'], // All permissions
        'principal' => ['students.*', 'teachers.*', 'attendance.*', 'fees.*', 'exams.*', 'reports.*', 'settings.view'],
        'vice_principal' => ['students.*', 'teachers.view', 'attendance.*', 'exams.*', 'reports.view'],
        'class_teacher' => ['students.view', 'students.edit', 'attendance.*', 'exams.view', 'exams.results'],
        'teacher' => ['students.view', 'attendance.view', 'attendance.create', 'exams.view'],
        'accountant' => ['fees.*', 'reports.view', 'reports.export'],
        'librarian' => ['students.view'],
        'staff' => ['students.view', 'attendance.view'],
        'parent' => ['students.view', 'attendance.view', 'fees.view', 'exams.view', 'reports.view'],
        'student' => ['attendance.view', 'fees.view', 'exams.view']
    ];

    // Clear existing
    $pdo->exec("DELETE FROM role_permissions");

    $stmt = $pdo->prepare("INSERT INTO role_permissions (id, role_id, permission_id, is_granted, created_by) VALUES (UUID(), ?, ?, 1, 'system')");

    foreach ($rolePerms as $roleCode => $perms) {
        if (!isset($roleMap[$roleCode])) continue;
        $roleId = $roleMap[$roleCode];

        if (in_array('*', $perms)) {
            // Grant all permissions
            foreach ($permissions as $perm) {
                $stmt->execute([$roleId, $perm['id']]);
            }
        } else {
            foreach ($perms as $permPattern) {
                if (strpos($permPattern, '*') !== false) {
                    // Wildcard: students.* means all students permissions
                    $resource = str_replace('.*', '', $permPattern);
                    foreach ($permissions as $perm) {
                        if (strpos($perm['code'], $resource . '.') === 0) {
                            $stmt->execute([$roleId, $perm['id']]);
                        }
                    }
                } else {
                    // Exact permission
                    if (isset($permMap[$permPattern])) {
                        $stmt->execute([$roleId, $permMap[$permPattern]]);
                    }
                }
            }
        }
        echo "  [+] Assigned permissions to role: {$roleCode}\n";
    }

    $count = $pdo->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
    echo "\n  [OK] role_permissions populated: {$count} rows\n\n";

    // ========================================================================
    // STEP 3: CREATE DEFAULT SCHOOL
    // ========================================================================
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "STEP 3: CREATING DEFAULT SCHOOL\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    // Get premium plan ID
    $premiumPlanId = $pdo->query("SELECT id FROM plans WHERE code = 'premium'")->fetchColumn();

    // Check if school exists
    $schoolExists = $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn();

    if ($schoolExists == 0) {
        $schoolId = null;
        $stmt = $pdo->prepare("
            INSERT INTO schools (id, code, name, slug, email, phone, plan_id, subscription_status, subscription_start, subscription_end, is_active, is_verified, created_by)
            VALUES (UUID(), 'SCH001', 'Default School', 'default-school', 'admin@school.com', '+91-9999999999', ?, 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 1, 1, 'system')
        ");
        $stmt->execute([$premiumPlanId]);

        $schoolId = $pdo->query("SELECT id FROM schools WHERE code = 'SCH001'")->fetchColumn();
        echo "  [+] Created default school: SCH001\n";
        echo "  [+] School ID: {$schoolId}\n";
    } else {
        $schoolId = $pdo->query("SELECT id FROM schools LIMIT 1")->fetchColumn();
        echo "  [=] School already exists: {$schoolId}\n";
    }

    // ========================================================================
    // STEP 4: MIGRATE USERS TO GLOBAL_USERS
    // ========================================================================
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "STEP 4: MIGRATING USERS TO GLOBAL_USERS\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    // Check existing users in old table
    $oldUsers = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);

    // Get school_admin role
    $schoolAdminRoleId = $pdo->query("SELECT id FROM roles WHERE code = 'school_admin' AND school_id IS NULL")->fetchColumn();

    foreach ($oldUsers as $user) {
        // Check if already migrated
        $exists = $pdo->prepare("SELECT id FROM global_users WHERE email = ?");
        $exists->execute([$user['email'] ?? $user['username'] . '@school.com']);

        if ($exists->fetchColumn()) {
            echo "  [=] User exists: {$user['username']}\n";
            continue;
        }

        // Insert into global_users
        $stmt = $pdo->prepare("
            INSERT INTO global_users (id, email, password, first_name, last_name, is_super_admin, is_active, created_by)
            VALUES (UUID(), ?, ?, ?, '', ?, 1, 'migration')
        ");
        $email = $user['email'] ?? $user['username'] . '@school.com';
        $isSuperAdmin = ($user['role'] ?? '') === 'admin' ? 1 : 0;
        $stmt->execute([$email, $user['password'], $user['username'], $isSuperAdmin]);

        $globalUserId = $pdo->query("SELECT id FROM global_users WHERE email = '{$email}'")->fetchColumn();

        // Link to school
        $stmt = $pdo->prepare("
            INSERT INTO school_users (id, school_id, user_id, role_id, user_type, is_active, created_by)
            VALUES (UUID(), ?, ?, ?, 'admin', 1, 'migration')
        ");
        $stmt->execute([$schoolId, $globalUserId, $schoolAdminRoleId]);

        echo "  [+] Migrated user: {$user['username']}\n";
    }

    // Create default super admin if no users
    $globalUserCount = $pdo->query("SELECT COUNT(*) FROM global_users")->fetchColumn();
    if ($globalUserCount == 0) {
        $stmt = $pdo->prepare("
            INSERT INTO global_users (id, email, password, first_name, is_super_admin, is_active, created_by)
            VALUES (UUID(), 'superadmin@school.com', ?, 'Super Admin', 1, 1, 'system')
        ");
        $stmt->execute([password_hash('Admin@123', PASSWORD_DEFAULT)]);

        $superAdminId = $pdo->query("SELECT id FROM global_users WHERE email = 'superadmin@school.com'")->fetchColumn();
        $superAdminRoleId = $pdo->query("SELECT id FROM roles WHERE code = 'super_admin'")->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO school_users (id, school_id, user_id, role_id, user_type, is_active, created_by)
            VALUES (UUID(), ?, ?, ?, 'admin', 1, 'system')
        ");
        $stmt->execute([$schoolId, $superAdminId, $superAdminRoleId]);

        echo "  [+] Created default super admin: superadmin@school.com / Admin@123\n";
    }

    echo "\n  [OK] User migration complete\n\n";

    // ========================================================================
    // STEP 5: ADD SCHOOL_ID TO DOMAIN TABLES
    // ========================================================================
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "STEP 5: ADDING SCHOOL_ID TO DOMAIN TABLES\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $domainTables = [
        'students', 'teachers', 'classes', 'sections', 'subjects',
        'attendance', 'exams', 'exam_marks', 'fee_structures', 'fee_payments',
        'timetable', 'syllabus', 'syllabus_chapters', 'syllabus_topics', 'syllabus_progress',
        'library_books', 'library_issues', 'notifications', 'payroll',
        'hostel_blocks', 'hostel_rooms', 'hostel_allocations',
        'transport_routes', 'transport_stops', 'transport_assignments',
        'teacher_absences', 'teacher_substitutions', 'student_documents'
    ];

    foreach ($domainTables as $table) {
        try {
            // Check if column exists
            $cols = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'school_id'")->fetchAll();

            if (empty($cols)) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `school_id` CHAR(36) NULL AFTER `id`");
                $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `idx_school_id` (`school_id`)");
                echo "  [+] Added school_id to: {$table}\n";

                // Update existing records with default school
                $pdo->exec("UPDATE `{$table}` SET school_id = '{$schoolId}' WHERE school_id IS NULL");
            } else {
                echo "  [=] school_id exists: {$table}\n";
            }
        } catch (PDOException $e) {
            echo "  [!] Error on {$table}: " . $e->getMessage() . "\n";
        }
    }

    echo "\n  [OK] school_id added to domain tables\n\n";

    // ========================================================================
    // STEP 6: CREATE MISSING HIGH PRIORITY TABLES
    // ========================================================================
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "STEP 6: CREATING MISSING HIGH PRIORITY TABLES\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $missingTables = [
        // Student Extended
        'student_parents' => "
            CREATE TABLE IF NOT EXISTS `student_parents` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `student_id` INT NOT NULL,
                `relation` ENUM('father', 'mother', 'guardian') NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `phone` VARCHAR(20),
                `email` VARCHAR(100),
                `occupation` VARCHAR(100),
                `annual_income` DECIMAL(12,2),
                `address` TEXT,
                `is_primary_contact` TINYINT(1) DEFAULT 0,
                `is_emergency_contact` TINYINT(1) DEFAULT 0,
                `can_pickup` TINYINT(1) DEFAULT 1,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_student` (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'student_emergency_contacts' => "
            CREATE TABLE IF NOT EXISTS `student_emergency_contacts` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `student_id` INT NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `relation` VARCHAR(50),
                `phone` VARCHAR(20) NOT NULL,
                `alternate_phone` VARCHAR(20),
                `priority` INT DEFAULT 1,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_student` (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'student_medical' => "
            CREATE TABLE IF NOT EXISTS `student_medical` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `student_id` INT NOT NULL,
                `blood_group` VARCHAR(10),
                `height_cm` DECIMAL(5,2),
                `weight_kg` DECIMAL(5,2),
                `allergies` TEXT,
                `medical_conditions` TEXT,
                `medications` TEXT,
                `doctor_name` VARCHAR(100),
                `doctor_phone` VARCHAR(20),
                `hospital_preference` VARCHAR(200),
                `insurance_provider` VARCHAR(100),
                `insurance_number` VARCHAR(50),
                `last_checkup_date` DATE,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                UNIQUE KEY `unique_student` (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'student_siblings' => "
            CREATE TABLE IF NOT EXISTS `student_siblings` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `student_id` INT NOT NULL,
                `sibling_student_id` INT NULL,
                `sibling_name` VARCHAR(100),
                `sibling_class` VARCHAR(50),
                `sibling_school` VARCHAR(200),
                `relation` ENUM('brother', 'sister') NOT NULL,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_student` (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'student_previous_school' => "
            CREATE TABLE IF NOT EXISTS `student_previous_school` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `student_id` INT NOT NULL,
                `school_name` VARCHAR(200) NOT NULL,
                `board` VARCHAR(100),
                `medium` VARCHAR(50),
                `class_studied` VARCHAR(50),
                `year_of_passing` YEAR,
                `percentage` DECIMAL(5,2),
                `tc_number` VARCHAR(50),
                `tc_date` DATE,
                `reason_for_leaving` TEXT,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_student` (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        // Academic Year & Calendar
        'academic_years' => "
            CREATE TABLE IF NOT EXISTS `academic_years` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `name` VARCHAR(50) NOT NULL,
                `code` VARCHAR(20) NOT NULL,
                `start_date` DATE NOT NULL,
                `end_date` DATE NOT NULL,
                `is_current` TINYINT(1) DEFAULT 0,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                UNIQUE KEY `unique_school_code` (`school_id`, `code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'academic_terms' => "
            CREATE TABLE IF NOT EXISTS `academic_terms` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `academic_year_id` CHAR(36) NOT NULL,
                `name` VARCHAR(50) NOT NULL,
                `start_date` DATE NOT NULL,
                `end_date` DATE NOT NULL,
                `is_current` TINYINT(1) DEFAULT 0,
                `sort_order` INT DEFAULT 0,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_year` (`academic_year_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'academic_calendar' => "
            CREATE TABLE IF NOT EXISTS `academic_calendar` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `academic_year_id` CHAR(36) NOT NULL,
                `title` VARCHAR(200) NOT NULL,
                `description` TEXT,
                `event_type` ENUM('holiday', 'exam', 'event', 'meeting', 'activity', 'other') NOT NULL,
                `start_date` DATE NOT NULL,
                `end_date` DATE,
                `is_holiday` TINYINT(1) DEFAULT 0,
                `applies_to` ENUM('all', 'students', 'teachers', 'staff') DEFAULT 'all',
                `color` VARCHAR(20),
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_year` (`academic_year_id`),
                INDEX `idx_date` (`start_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'class_promotions' => "
            CREATE TABLE IF NOT EXISTS `class_promotions` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `from_academic_year_id` CHAR(36) NOT NULL,
                `to_academic_year_id` CHAR(36) NOT NULL,
                `from_class_id` INT NOT NULL,
                `to_class_id` INT NOT NULL,
                `min_percentage` DECIMAL(5,2),
                `min_attendance` DECIMAL(5,2),
                `is_active` TINYINT(1) DEFAULT 1,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'student_promotions' => "
            CREATE TABLE IF NOT EXISTS `student_promotions` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `student_id` INT NOT NULL,
                `from_academic_year_id` CHAR(36) NOT NULL,
                `to_academic_year_id` CHAR(36) NOT NULL,
                `from_class_id` INT NOT NULL,
                `from_section_id` INT,
                `to_class_id` INT NOT NULL,
                `to_section_id` INT,
                `status` ENUM('promoted', 'detained', 'transferred', 'withdrawn') NOT NULL,
                `percentage` DECIMAL(5,2),
                `attendance_percentage` DECIMAL(5,2),
                `remarks` TEXT,
                `promoted_by` VARCHAR(100),
                `promoted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `created_by` VARCHAR(100),
                INDEX `idx_school` (`school_id`),
                INDEX `idx_student` (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        // Admission Module
        'admission_enquiries' => "
            CREATE TABLE IF NOT EXISTS `admission_enquiries` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `enquiry_number` VARCHAR(20) NOT NULL,
                `academic_year_id` CHAR(36),
                `student_name` VARCHAR(100) NOT NULL,
                `date_of_birth` DATE,
                `gender` ENUM('male', 'female', 'other'),
                `applying_for_class` INT,
                `parent_name` VARCHAR(100) NOT NULL,
                `parent_phone` VARCHAR(20) NOT NULL,
                `parent_email` VARCHAR(100),
                `address` TEXT,
                `previous_school` VARCHAR(200),
                `source` ENUM('walk-in', 'website', 'referral', 'advertisement', 'other'),
                `referred_by` VARCHAR(100),
                `status` ENUM('new', 'contacted', 'scheduled', 'visited', 'applied', 'enrolled', 'lost') DEFAULT 'new',
                `follow_up_date` DATE,
                `notes` TEXT,
                `assigned_to` CHAR(36),
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_status` (`status`),
                UNIQUE KEY `unique_enquiry` (`school_id`, `enquiry_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'admission_applications' => "
            CREATE TABLE IF NOT EXISTS `admission_applications` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `application_number` VARCHAR(20) NOT NULL,
                `enquiry_id` CHAR(36),
                `academic_year_id` CHAR(36) NOT NULL,
                `student_name` VARCHAR(100) NOT NULL,
                `date_of_birth` DATE NOT NULL,
                `gender` ENUM('male', 'female', 'other') NOT NULL,
                `applying_for_class` INT NOT NULL,
                `nationality` VARCHAR(50) DEFAULT 'Indian',
                `religion` VARCHAR(50),
                `caste` VARCHAR(50),
                `mother_tongue` VARCHAR(50),
                `aadhaar_number` VARCHAR(20),
                `father_name` VARCHAR(100),
                `father_phone` VARCHAR(20),
                `father_email` VARCHAR(100),
                `father_occupation` VARCHAR(100),
                `mother_name` VARCHAR(100),
                `mother_phone` VARCHAR(20),
                `mother_email` VARCHAR(100),
                `mother_occupation` VARCHAR(100),
                `guardian_name` VARCHAR(100),
                `guardian_phone` VARCHAR(20),
                `guardian_relation` VARCHAR(50),
                `address` TEXT,
                `city` VARCHAR(100),
                `state` VARCHAR(100),
                `pincode` VARCHAR(10),
                `previous_school` VARCHAR(200),
                `previous_class` VARCHAR(50),
                `previous_percentage` DECIMAL(5,2),
                `status` ENUM('draft', 'submitted', 'under_review', 'documents_pending', 'interview_scheduled', 'approved', 'rejected', 'enrolled', 'withdrawn') DEFAULT 'draft',
                `submission_date` TIMESTAMP,
                `review_notes` TEXT,
                `reviewed_by` CHAR(36),
                `reviewed_at` TIMESTAMP,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_status` (`status`),
                INDEX `idx_year` (`academic_year_id`),
                UNIQUE KEY `unique_application` (`school_id`, `application_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'admission_documents' => "
            CREATE TABLE IF NOT EXISTS `admission_documents` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `application_id` CHAR(36) NOT NULL,
                `document_type` VARCHAR(50) NOT NULL,
                `document_name` VARCHAR(200),
                `file_path` TEXT,
                `file_size` INT,
                `is_verified` TINYINT(1) DEFAULT 0,
                `verified_by` VARCHAR(100),
                `verified_at` TIMESTAMP,
                `remarks` TEXT,
                `created_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_application` (`application_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        // Grade & Report Card
        'grade_scales' => "
            CREATE TABLE IF NOT EXISTS `grade_scales` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `name` VARCHAR(50) NOT NULL,
                `min_percentage` DECIMAL(5,2) NOT NULL,
                `max_percentage` DECIMAL(5,2) NOT NULL,
                `grade` VARCHAR(10) NOT NULL,
                `grade_point` DECIMAL(3,2),
                `description` VARCHAR(100),
                `is_pass` TINYINT(1) DEFAULT 1,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'report_cards' => "
            CREATE TABLE IF NOT EXISTS `report_cards` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `student_id` INT NOT NULL,
                `academic_year_id` CHAR(36) NOT NULL,
                `term_id` CHAR(36),
                `class_id` INT NOT NULL,
                `section_id` INT,
                `total_marks` DECIMAL(10,2),
                `obtained_marks` DECIMAL(10,2),
                `percentage` DECIMAL(5,2),
                `grade` VARCHAR(10),
                `rank` INT,
                `attendance_percentage` DECIMAL(5,2),
                `teacher_remarks` TEXT,
                `principal_remarks` TEXT,
                `result` ENUM('pass', 'fail', 'promoted', 'detained') DEFAULT 'pass',
                `generated_at` TIMESTAMP,
                `generated_by` VARCHAR(100),
                `published` TINYINT(1) DEFAULT 0,
                `published_at` TIMESTAMP,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_student` (`student_id`),
                INDEX `idx_year` (`academic_year_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'report_card_subjects' => "
            CREATE TABLE IF NOT EXISTS `report_card_subjects` (
                `id` CHAR(36) PRIMARY KEY,
                `report_card_id` CHAR(36) NOT NULL,
                `subject_id` INT NOT NULL,
                `max_marks` DECIMAL(10,2),
                `obtained_marks` DECIMAL(10,2),
                `grade` VARCHAR(10),
                `remarks` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_report` (`report_card_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        // Assignments
        'assignments' => "
            CREATE TABLE IF NOT EXISTS `assignments` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `class_id` INT NOT NULL,
                `section_id` INT,
                `subject_id` INT NOT NULL,
                `teacher_id` INT NOT NULL,
                `title` VARCHAR(200) NOT NULL,
                `description` TEXT,
                `instructions` TEXT,
                `attachment_path` TEXT,
                `max_marks` DECIMAL(10,2),
                `assigned_date` DATE NOT NULL,
                `due_date` DATE NOT NULL,
                `due_time` TIME,
                `is_graded` TINYINT(1) DEFAULT 1,
                `allow_late_submission` TINYINT(1) DEFAULT 0,
                `late_submission_penalty` DECIMAL(5,2),
                `status` ENUM('draft', 'published', 'closed') DEFAULT 'draft',
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_class` (`class_id`),
                INDEX `idx_teacher` (`teacher_id`),
                INDEX `idx_due_date` (`due_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'assignment_submissions' => "
            CREATE TABLE IF NOT EXISTS `assignment_submissions` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `assignment_id` CHAR(36) NOT NULL,
                `student_id` INT NOT NULL,
                `submission_text` TEXT,
                `attachment_path` TEXT,
                `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `is_late` TINYINT(1) DEFAULT 0,
                `marks_obtained` DECIMAL(10,2),
                `grade` VARCHAR(10),
                `feedback` TEXT,
                `graded_by` VARCHAR(100),
                `graded_at` TIMESTAMP,
                `status` ENUM('submitted', 'graded', 'returned', 'resubmit') DEFAULT 'submitted',
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                INDEX `idx_school` (`school_id`),
                INDEX `idx_assignment` (`assignment_id`),
                INDEX `idx_student` (`student_id`),
                UNIQUE KEY `unique_submission` (`assignment_id`, `student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        // HR & Leave Management
        'leave_types' => "
            CREATE TABLE IF NOT EXISTS `leave_types` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `name` VARCHAR(50) NOT NULL,
                `code` VARCHAR(20) NOT NULL,
                `description` TEXT,
                `days_allowed` INT,
                `is_paid` TINYINT(1) DEFAULT 1,
                `is_carry_forward` TINYINT(1) DEFAULT 0,
                `max_carry_forward` INT,
                `applies_to` ENUM('all', 'teachers', 'staff') DEFAULT 'all',
                `is_active` TINYINT(1) DEFAULT 1,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                UNIQUE KEY `unique_code` (`school_id`, `code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'leave_applications' => "
            CREATE TABLE IF NOT EXISTS `leave_applications` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `user_id` CHAR(36) NOT NULL,
                `leave_type_id` CHAR(36) NOT NULL,
                `from_date` DATE NOT NULL,
                `to_date` DATE NOT NULL,
                `days` DECIMAL(4,1) NOT NULL,
                `reason` TEXT NOT NULL,
                `attachment_path` TEXT,
                `status` ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
                `approved_by` CHAR(36),
                `approved_at` TIMESTAMP,
                `rejection_reason` TEXT,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_user` (`user_id`),
                INDEX `idx_status` (`status`),
                INDEX `idx_dates` (`from_date`, `to_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'leave_balances' => "
            CREATE TABLE IF NOT EXISTS `leave_balances` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `user_id` CHAR(36) NOT NULL,
                `leave_type_id` CHAR(36) NOT NULL,
                `academic_year_id` CHAR(36) NOT NULL,
                `total_days` DECIMAL(4,1) DEFAULT 0,
                `used_days` DECIMAL(4,1) DEFAULT 0,
                `pending_days` DECIMAL(4,1) DEFAULT 0,
                `balance_days` DECIMAL(4,1) GENERATED ALWAYS AS (`total_days` - `used_days` - `pending_days`) STORED,
                `carry_forward_days` DECIMAL(4,1) DEFAULT 0,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_user` (`user_id`),
                UNIQUE KEY `unique_balance` (`user_id`, `leave_type_id`, `academic_year_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'staff_attendance' => "
            CREATE TABLE IF NOT EXISTS `staff_attendance` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `user_id` CHAR(36) NOT NULL,
                `date` DATE NOT NULL,
                `check_in` TIME,
                `check_out` TIME,
                `status` ENUM('present', 'absent', 'half_day', 'late', 'on_leave') NOT NULL,
                `leave_application_id` CHAR(36),
                `remarks` TEXT,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_user` (`user_id`),
                INDEX `idx_date` (`date`),
                UNIQUE KEY `unique_attendance` (`user_id`, `date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        // Fee Extended
        'fee_discounts' => "
            CREATE TABLE IF NOT EXISTS `fee_discounts` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `code` VARCHAR(20) NOT NULL,
                `discount_type` ENUM('percentage', 'fixed') NOT NULL,
                `discount_value` DECIMAL(10,2) NOT NULL,
                `applies_to` ENUM('all', 'sibling', 'merit', 'staff_child', 'scholarship', 'custom') DEFAULT 'custom',
                `description` TEXT,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                UNIQUE KEY `unique_code` (`school_id`, `code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'student_fee_discounts' => "
            CREATE TABLE IF NOT EXISTS `student_fee_discounts` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `student_id` INT NOT NULL,
                `discount_id` CHAR(36) NOT NULL,
                `academic_year_id` CHAR(36) NOT NULL,
                `approved_by` VARCHAR(100),
                `reason` TEXT,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_student` (`student_id`),
                INDEX `idx_discount` (`discount_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'invoices' => "
            CREATE TABLE IF NOT EXISTS `invoices` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `invoice_number` VARCHAR(30) NOT NULL,
                `student_id` INT NOT NULL,
                `academic_year_id` CHAR(36) NOT NULL,
                `invoice_date` DATE NOT NULL,
                `due_date` DATE NOT NULL,
                `subtotal` DECIMAL(12,2) NOT NULL,
                `discount_amount` DECIMAL(12,2) DEFAULT 0,
                `tax_amount` DECIMAL(12,2) DEFAULT 0,
                `total_amount` DECIMAL(12,2) NOT NULL,
                `paid_amount` DECIMAL(12,2) DEFAULT 0,
                `balance_amount` DECIMAL(12,2) GENERATED ALWAYS AS (`total_amount` - `paid_amount`) STORED,
                `status` ENUM('draft', 'sent', 'paid', 'partial', 'overdue', 'cancelled') DEFAULT 'draft',
                `notes` TEXT,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_student` (`student_id`),
                INDEX `idx_status` (`status`),
                UNIQUE KEY `unique_invoice` (`school_id`, `invoice_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'invoice_items' => "
            CREATE TABLE IF NOT EXISTS `invoice_items` (
                `id` CHAR(36) PRIMARY KEY,
                `invoice_id` CHAR(36) NOT NULL,
                `fee_structure_id` INT,
                `description` VARCHAR(200) NOT NULL,
                `quantity` INT DEFAULT 1,
                `unit_price` DECIMAL(12,2) NOT NULL,
                `discount` DECIMAL(12,2) DEFAULT 0,
                `amount` DECIMAL(12,2) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_invoice` (`invoice_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        // Communication
        'message_templates' => "
            CREATE TABLE IF NOT EXISTS `message_templates` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `type` ENUM('sms', 'email', 'whatsapp', 'notification') NOT NULL,
                `subject` VARCHAR(200),
                `body` TEXT NOT NULL,
                `variables` JSON,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'announcements' => "
            CREATE TABLE IF NOT EXISTS `announcements` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `title` VARCHAR(200) NOT NULL,
                `content` TEXT NOT NULL,
                `type` ENUM('general', 'urgent', 'event', 'holiday', 'exam') DEFAULT 'general',
                `target_audience` ENUM('all', 'students', 'teachers', 'parents', 'staff') DEFAULT 'all',
                `target_classes` JSON,
                `attachment_path` TEXT,
                `publish_date` DATE,
                `expiry_date` DATE,
                `is_published` TINYINT(1) DEFAULT 0,
                `is_pinned` TINYINT(1) DEFAULT 0,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_published` (`is_published`),
                INDEX `idx_date` (`publish_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'sms_logs' => "
            CREATE TABLE IF NOT EXISTS `sms_logs` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `phone` VARCHAR(20) NOT NULL,
                `message` TEXT NOT NULL,
                `template_id` CHAR(36),
                `status` ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
                `provider_response` TEXT,
                `message_id` VARCHAR(100),
                `sent_at` TIMESTAMP,
                `delivered_at` TIMESTAMP,
                `cost` DECIMAL(10,4),
                `created_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_status` (`status`),
                INDEX `idx_phone` (`phone`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        // Certificates
        'certificate_templates' => "
            CREATE TABLE IF NOT EXISTS `certificate_templates` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `type` ENUM('tc', 'bonafide', 'character', 'achievement', 'participation', 'custom') NOT NULL,
                `content` TEXT NOT NULL,
                `header_html` TEXT,
                `footer_html` TEXT,
                `variables` JSON,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_by` VARCHAR(100),
                `updated_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",

        'certificates_issued' => "
            CREATE TABLE IF NOT EXISTS `certificates_issued` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `certificate_number` VARCHAR(30) NOT NULL,
                `template_id` CHAR(36) NOT NULL,
                `student_id` INT NOT NULL,
                `issue_date` DATE NOT NULL,
                `reason` TEXT,
                `content_snapshot` TEXT,
                `issued_by` VARCHAR(100),
                `created_by` VARCHAR(100),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_student` (`student_id`),
                UNIQUE KEY `unique_cert` (`school_id`, `certificate_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        "
    ];

    foreach ($missingTables as $name => $sql) {
        try {
            $pdo->exec($sql);
            echo "  [+] Created: {$name}\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "  [=] Exists: {$name}\n";
            } else {
                echo "  [!] Error: " . substr($e->getMessage(), 0, 80) . "\n";
            }
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // ========================================================================
    // STEP 7: CREATE DEFAULT ACADEMIC YEAR
    // ========================================================================
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "STEP 7: CREATING DEFAULT ACADEMIC YEAR\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $yearExists = $pdo->query("SELECT COUNT(*) FROM academic_years")->fetchColumn();
    if ($yearExists == 0) {
        $pdo->exec("
            INSERT INTO academic_years (id, school_id, name, code, start_date, end_date, is_current, is_active, created_by)
            VALUES (UUID(), '{$schoolId}', '2024-2025', '2024-25', '2024-04-01', '2025-03-31', 1, 1, 'system')
        ");
        echo "  [+] Created academic year: 2024-2025\n";
    }

    // ========================================================================
    // STEP 8: CREATE DEFAULT GRADE SCALE
    // ========================================================================
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "STEP 8: CREATING DEFAULT GRADE SCALE\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $gradeExists = $pdo->query("SELECT COUNT(*) FROM grade_scales")->fetchColumn();
    if ($gradeExists == 0) {
        $grades = [
            ['A+', 90, 100, 10.0, 'Outstanding', 1],
            ['A', 80, 89.99, 9.0, 'Excellent', 1],
            ['B+', 70, 79.99, 8.0, 'Very Good', 1],
            ['B', 60, 69.99, 7.0, 'Good', 1],
            ['C+', 50, 59.99, 6.0, 'Above Average', 1],
            ['C', 40, 49.99, 5.0, 'Average', 1],
            ['D', 33, 39.99, 4.0, 'Below Average', 1],
            ['F', 0, 32.99, 0, 'Fail', 0]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO grade_scales (id, school_id, grade, min_percentage, max_percentage, grade_point, description, is_pass, created_by)
            VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, 'system')
        ");

        foreach ($grades as $g) {
            $stmt->execute([$schoolId, $g[0], $g[1], $g[2], $g[3], $g[4], $g[5]]);
        }
        echo "  [+] Created default grade scale (A+ to F)\n";
    }

    // ========================================================================
    // STEP 9: CREATE DEFAULT LEAVE TYPES
    // ========================================================================
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "STEP 9: CREATING DEFAULT LEAVE TYPES\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $leaveExists = $pdo->query("SELECT COUNT(*) FROM leave_types")->fetchColumn();
    if ($leaveExists == 0) {
        $leaveTypes = [
            ['Casual Leave', 'CL', 12, 1, 0, 'all'],
            ['Sick Leave', 'SL', 12, 1, 1, 'all'],
            ['Earned Leave', 'EL', 15, 1, 1, 'all'],
            ['Maternity Leave', 'ML', 180, 1, 0, 'all'],
            ['Paternity Leave', 'PL', 15, 1, 0, 'all'],
            ['Unpaid Leave', 'UL', null, 0, 0, 'all']
        ];

        $stmt = $pdo->prepare("
            INSERT INTO leave_types (id, school_id, name, code, days_allowed, is_paid, is_carry_forward, applies_to, created_by)
            VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, 'system')
        ");

        foreach ($leaveTypes as $lt) {
            $stmt->execute([$schoolId, $lt[0], $lt[1], $lt[2], $lt[3], $lt[4], $lt[5]]);
        }
        echo "  [+] Created default leave types\n";
    }

    // ========================================================================
    // FINAL SUMMARY
    // ========================================================================
    echo "\n╔══════════════════════════════════════════════════════════════╗\n";
    echo "║                    MIGRATION COMPLETE                         ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n\n";

    // Count tables
    $tableCount = $pdo->query("SHOW TABLES")->rowCount();
    echo "Total Tables: {$tableCount}\n\n";

    // Show key counts
    $counts = [
        'plans' => $pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn(),
        'features' => $pdo->query("SELECT COUNT(*) FROM features")->fetchColumn(),
        'plan_features' => $pdo->query("SELECT COUNT(*) FROM plan_features")->fetchColumn(),
        'roles' => $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn(),
        'permissions' => $pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn(),
        'role_permissions' => $pdo->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn(),
        'schools' => $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn(),
        'global_users' => $pdo->query("SELECT COUNT(*) FROM global_users")->fetchColumn(),
        'school_users' => $pdo->query("SELECT COUNT(*) FROM school_users")->fetchColumn(),
    ];

    echo "Key Table Counts:\n";
    foreach ($counts as $table => $count) {
        echo "  {$table}: {$count}\n";
    }

    echo "\n[SUCCESS] All migrations completed!\n";

} catch (Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
?>
