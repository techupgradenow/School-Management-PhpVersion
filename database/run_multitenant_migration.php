<?php
/**
 * Multi-Tenant Schema Migration
 * Runs the multi-tenant schema on Hostinger database
 */

require_once __DIR__ . '/../backend/config/env.php';

echo "=== MULTI-TENANT SCHEMA MIGRATION ===\n\n";

try {
    $pdo = new PDO(getDbDsn(), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 120
    ]);
    echo "[OK] Connected to Hostinger: " . DB_HOST . "\n\n";

    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

    echo "Creating multi-tenant tables...\n\n";

    // Define tables individually
    $tables = [
        'plans' => "
            CREATE TABLE IF NOT EXISTS `plans` (
                `id` CHAR(36) PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT NULL,
                `price_monthly` DECIMAL(10,2) DEFAULT 0.00,
                `price_yearly` DECIMAL(10,2) DEFAULT 0.00,
                `currency` VARCHAR(3) DEFAULT 'INR',
                `max_students` INT DEFAULT 100,
                `max_teachers` INT DEFAULT 10,
                `max_users` INT DEFAULT 20,
                `max_storage_gb` INT DEFAULT 1,
                `is_active` TINYINT(1) DEFAULT 1,
                `is_default` TINYINT(1) DEFAULT 0,
                `trial_days` INT DEFAULT 0,
                `sort_order` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_code` (`code`),
                INDEX `idx_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'features' => "
            CREATE TABLE IF NOT EXISTS `features` (
                `id` CHAR(36) PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT NULL,
                `category` VARCHAR(50) NULL,
                `icon` VARCHAR(50) NULL,
                `route` VARCHAR(100) NULL,
                `api_endpoint` VARCHAR(100) NULL,
                `parent_id` CHAR(36) NULL,
                `is_premium` TINYINT(1) DEFAULT 0,
                `is_active` TINYINT(1) DEFAULT 1,
                `sort_order` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_code` (`code`),
                INDEX `idx_category` (`category`),
                INDEX `idx_parent` (`parent_id`),
                INDEX `idx_premium` (`is_premium`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'plan_features' => "
            CREATE TABLE IF NOT EXISTS `plan_features` (
                `id` CHAR(36) PRIMARY KEY,
                `plan_id` CHAR(36) NOT NULL,
                `feature_id` CHAR(36) NOT NULL,
                `is_enabled` TINYINT(1) DEFAULT 1,
                `usage_limit` INT NULL,
                `limit_type` ENUM('daily', 'monthly', 'yearly', 'total') NULL,
                `custom_config` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_plan_feature` (`plan_id`, `feature_id`),
                INDEX `idx_plan` (`plan_id`),
                INDEX `idx_feature` (`feature_id`),
                FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`feature_id`) REFERENCES `features`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'schools' => "
            CREATE TABLE IF NOT EXISTS `schools` (
                `id` CHAR(36) PRIMARY KEY,
                `code` VARCHAR(20) NOT NULL UNIQUE,
                `name` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) UNIQUE NULL,
                `email` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(20) NULL,
                `website` VARCHAR(255) NULL,
                `logo` TEXT NULL,
                `address` TEXT NULL,
                `city` VARCHAR(100) NULL,
                `state` VARCHAR(100) NULL,
                `country` VARCHAR(100) DEFAULT 'India',
                `pincode` VARCHAR(20) NULL,
                `timezone` VARCHAR(50) DEFAULT 'Asia/Kolkata',
                `currency` VARCHAR(3) DEFAULT 'INR',
                `academic_year_start` TINYINT DEFAULT 4,
                `academic_year_end` TINYINT DEFAULT 3,
                `plan_id` CHAR(36) NULL,
                `subscription_status` ENUM('trial', 'active', 'expired', 'cancelled', 'suspended') DEFAULT 'trial',
                `subscription_start` DATE NULL,
                `subscription_end` DATE NULL,
                `trial_ends_at` DATE NULL,
                `settings` JSON NULL,
                `custom_domain` VARCHAR(255) NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `is_verified` TINYINT(1) DEFAULT 0,
                `verified_at` TIMESTAMP NULL,
                `created_by` CHAR(36) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `deleted_at` TIMESTAMP NULL,
                INDEX `idx_code` (`code`),
                INDEX `idx_slug` (`slug`),
                INDEX `idx_plan` (`plan_id`),
                INDEX `idx_status` (`subscription_status`),
                INDEX `idx_active` (`is_active`),
                FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'school_feature_overrides' => "
            CREATE TABLE IF NOT EXISTS `school_feature_overrides` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `feature_id` CHAR(36) NOT NULL,
                `is_enabled` TINYINT(1) DEFAULT 1,
                `usage_limit` INT NULL,
                `valid_from` DATE NULL,
                `valid_until` DATE NULL,
                `reason` VARCHAR(255) NULL,
                `granted_by` CHAR(36) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_school_feature` (`school_id`, `feature_id`),
                INDEX `idx_school` (`school_id`),
                INDEX `idx_feature` (`feature_id`),
                FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`feature_id`) REFERENCES `features`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'permissions' => "
            CREATE TABLE IF NOT EXISTS `permissions` (
                `id` CHAR(36) PRIMARY KEY,
                `code` VARCHAR(100) NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT NULL,
                `feature_id` CHAR(36) NULL,
                `action` ENUM('view', 'create', 'edit', 'delete', 'export', 'import', 'approve', 'manage') NOT NULL,
                `resource` VARCHAR(50) NOT NULL,
                `is_system` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_code` (`code`),
                INDEX `idx_feature` (`feature_id`),
                INDEX `idx_resource_action` (`resource`, `action`),
                FOREIGN KEY (`feature_id`) REFERENCES `features`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'roles' => "
            CREATE TABLE IF NOT EXISTS `roles` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NULL,
                `code` VARCHAR(50) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT NULL,
                `is_system` TINYINT(1) DEFAULT 0,
                `is_default` TINYINT(1) DEFAULT 0,
                `hierarchy_level` INT DEFAULT 100,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_school_role` (`school_id`, `code`),
                INDEX `idx_school` (`school_id`),
                INDEX `idx_code` (`code`),
                INDEX `idx_hierarchy` (`hierarchy_level`),
                FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'role_permissions' => "
            CREATE TABLE IF NOT EXISTS `role_permissions` (
                `id` CHAR(36) PRIMARY KEY,
                `role_id` CHAR(36) NOT NULL,
                `permission_id` CHAR(36) NOT NULL,
                `is_granted` TINYINT(1) DEFAULT 1,
                `conditions` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`),
                INDEX `idx_role` (`role_id`),
                INDEX `idx_permission` (`permission_id`),
                FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'global_users' => "
            CREATE TABLE IF NOT EXISTS `global_users` (
                `id` CHAR(36) PRIMARY KEY,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `phone` VARCHAR(20) NULL,
                `password` VARCHAR(255) NOT NULL,
                `first_name` VARCHAR(100) NOT NULL,
                `last_name` VARCHAR(100) NULL,
                `avatar` TEXT NULL,
                `email_verified_at` TIMESTAMP NULL,
                `phone_verified_at` TIMESTAMP NULL,
                `is_super_admin` TINYINT(1) DEFAULT 0,
                `is_active` TINYINT(1) DEFAULT 1,
                `last_login_at` TIMESTAMP NULL,
                `last_login_ip` VARCHAR(45) NULL,
                `password_changed_at` TIMESTAMP NULL,
                `remember_token` VARCHAR(100) NULL,
                `two_factor_enabled` TINYINT(1) DEFAULT 0,
                `two_factor_secret` VARCHAR(255) NULL,
                `preferences` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `deleted_at` TIMESTAMP NULL,
                INDEX `idx_email` (`email`),
                INDEX `idx_phone` (`phone`),
                INDEX `idx_active` (`is_active`),
                INDEX `idx_super_admin` (`is_super_admin`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'school_users' => "
            CREATE TABLE IF NOT EXISTS `school_users` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `user_id` CHAR(36) NOT NULL,
                `role_id` CHAR(36) NOT NULL,
                `employee_id` VARCHAR(50) NULL,
                `user_type` ENUM('admin', 'teacher', 'staff', 'student', 'parent', 'accountant') NOT NULL,
                `department` VARCHAR(100) NULL,
                `designation` VARCHAR(100) NULL,
                `joining_date` DATE NULL,
                `is_primary_school` TINYINT(1) DEFAULT 1,
                `is_active` TINYINT(1) DEFAULT 1,
                `permissions_override` JSON NULL,
                `settings` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `deactivated_at` TIMESTAMP NULL,
                UNIQUE KEY `unique_school_user` (`school_id`, `user_id`),
                INDEX `idx_school` (`school_id`),
                INDEX `idx_user` (`user_id`),
                INDEX `idx_role` (`role_id`),
                INDEX `idx_type` (`user_type`),
                INDEX `idx_active` (`is_active`),
                FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`user_id`) REFERENCES `global_users`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'subscription_history' => "
            CREATE TABLE IF NOT EXISTS `subscription_history` (
                `id` CHAR(36) PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `plan_id` CHAR(36) NOT NULL,
                `previous_plan_id` CHAR(36) NULL,
                `action` ENUM('subscribe', 'upgrade', 'downgrade', 'renew', 'cancel', 'expire') NOT NULL,
                `billing_cycle` ENUM('monthly', 'yearly') NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL,
                `currency` VARCHAR(3) DEFAULT 'INR',
                `payment_method` VARCHAR(50) NULL,
                `payment_reference` VARCHAR(255) NULL,
                `starts_at` DATE NOT NULL,
                `ends_at` DATE NOT NULL,
                `created_by` CHAR(36) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_plan` (`plan_id`),
                INDEX `idx_action` (`action`),
                FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'feature_usage_log' => "
            CREATE TABLE IF NOT EXISTS `feature_usage_log` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `school_id` CHAR(36) NOT NULL,
                `feature_id` CHAR(36) NOT NULL,
                `user_id` CHAR(36) NULL,
                `action` VARCHAR(50) NOT NULL,
                `usage_count` INT DEFAULT 1,
                `metadata` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school_feature` (`school_id`, `feature_id`),
                INDEX `idx_date` (`created_at`),
                FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`feature_id`) REFERENCES `features`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'audit_log' => "
            CREATE TABLE IF NOT EXISTS `audit_log` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `school_id` CHAR(36) NULL,
                `user_id` CHAR(36) NULL,
                `action` VARCHAR(50) NOT NULL,
                `entity_type` VARCHAR(50) NOT NULL,
                `entity_id` VARCHAR(50) NULL,
                `old_values` JSON NULL,
                `new_values` JSON NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_school` (`school_id`),
                INDEX `idx_user` (`user_id`),
                INDEX `idx_entity` (`entity_type`, `entity_id`),
                INDEX `idx_date` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        'user_sessions' => "
            CREATE TABLE IF NOT EXISTS `user_sessions` (
                `id` CHAR(36) PRIMARY KEY,
                `user_id` CHAR(36) NOT NULL,
                `school_id` CHAR(36) NULL,
                `token_hash` VARCHAR(255) NOT NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` TEXT NULL,
                `device_type` VARCHAR(50) NULL,
                `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expires_at` TIMESTAMP NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_user` (`user_id`),
                INDEX `idx_token` (`token_hash`),
                INDEX `idx_expires` (`expires_at`),
                FOREIGN KEY (`user_id`) REFERENCES `global_users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];

    // Create tables
    foreach ($tables as $name => $sql) {
        try {
            $pdo->exec($sql);
            echo "  [+] Created: {$name}\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "  [=] Exists: {$name}\n";
            } else {
                echo "  [!] Error creating {$name}: " . $e->getMessage() . "\n";
            }
        }
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\n--- Inserting seed data ---\n\n";

    // Seed Plans
    $plansSeed = [
        ['free', 'Free', 'Basic features for small schools', 0, 0, 50, 5, 10, 1, 1, 1, 0],
        ['basic', 'Basic', 'Essential features for growing schools', 999, 9990, 200, 20, 50, 5, 1, 0, 14],
        ['premium', 'Premium', 'Advanced features for established schools', 2499, 24990, 1000, 100, 200, 25, 1, 0, 14],
        ['enterprise', 'Enterprise', 'Unlimited features for large institutions', 4999, 49990, null, null, null, 100, 1, 0, 30]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO plans (id, code, name, description, price_monthly, price_yearly, max_students, max_teachers, max_users, max_storage_gb, is_active, is_default, trial_days, sort_order)
        VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $sortOrder = 1;
    foreach ($plansSeed as $plan) {
        try {
            $stmt->execute([...$plan, $sortOrder++]);
            echo "  [+] Seeded plan: {$plan[0]}\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "  [=] Plan exists: {$plan[0]}\n";
            } else {
                echo "  [!] Error: " . $e->getMessage() . "\n";
            }
        }
    }

    // Seed Features
    $featuresSeed = [
        ['dashboard', 'Dashboard', 'core', 'fa-dashboard', '/dashboard', 0, 1],
        ['students', 'Student Management', 'core', 'fa-user-graduate', '/students', 0, 2],
        ['teachers', 'Teacher Management', 'core', 'fa-chalkboard-teacher', '/teachers', 0, 3],
        ['classes', 'Class Management', 'core', 'fa-school', '/classes', 0, 4],
        ['attendance', 'Attendance', 'academic', 'fa-calendar-check', '/attendance', 0, 5],
        ['timetable', 'Timetable', 'academic', 'fa-clock', '/timetable', 0, 6],
        ['exams', 'Examinations', 'academic', 'fa-file-alt', '/exams', 0, 7],
        ['syllabus', 'Syllabus Management', 'academic', 'fa-book', '/syllabus', 0, 8],
        ['assignments', 'Assignments', 'academic', 'fa-tasks', '/assignments', 1, 9],
        ['fees', 'Fee Management', 'finance', 'fa-money-bill', '/fees', 0, 10],
        ['payroll', 'Payroll', 'finance', 'fa-wallet', '/payroll', 1, 11],
        ['expenses', 'Expense Tracking', 'finance', 'fa-receipt', '/expenses', 1, 12],
        ['library', 'Library Management', 'resources', 'fa-book-reader', '/library', 1, 13],
        ['transport', 'Transport Management', 'resources', 'fa-bus', '/transport', 1, 14],
        ['hostel', 'Hostel Management', 'resources', 'fa-building', '/hostel', 1, 15],
        ['inventory', 'Inventory', 'resources', 'fa-boxes', '/inventory', 1, 16],
        ['notifications', 'Notifications', 'communication', 'fa-bell', '/notifications', 0, 17],
        ['sms', 'SMS Gateway', 'communication', 'fa-sms', '/sms', 1, 18],
        ['email', 'Email Campaigns', 'communication', 'fa-envelope', '/email', 1, 19],
        ['whatsapp', 'WhatsApp Integration', 'communication', 'fa-whatsapp', '/whatsapp', 1, 20],
        ['reports', 'Basic Reports', 'analytics', 'fa-chart-bar', '/reports', 0, 21],
        ['advanced_reports', 'Advanced Analytics', 'analytics', 'fa-chart-line', '/analytics', 1, 22],
        ['export', 'Data Export', 'analytics', 'fa-download', '/export', 1, 23],
        ['api_access', 'API Access', 'integration', 'fa-plug', '/api', 1, 24],
        ['multi_branch', 'Multi-Branch', 'enterprise', 'fa-sitemap', '/branches', 1, 25],
        ['custom_branding', 'Custom Branding', 'enterprise', 'fa-palette', '/branding', 1, 26]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO features (id, code, name, category, icon, route, is_premium, sort_order)
        VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($featuresSeed as $feature) {
        try {
            $stmt->execute($feature);
            echo "  [+] Seeded feature: {$feature[0]}\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "  [=] Feature exists: {$feature[0]}\n";
            }
        }
    }

    // Seed Roles
    $rolesSeed = [
        ['super_admin', 'Super Administrator', 'Platform super admin with full access', 1, 1],
        ['school_admin', 'School Administrator', 'Full access to school management', 1, 10],
        ['principal', 'Principal', 'School principal with oversight access', 1, 20],
        ['vice_principal', 'Vice Principal', 'Vice principal with limited admin access', 1, 25],
        ['teacher', 'Teacher', 'Teacher with class and student access', 1, 50],
        ['class_teacher', 'Class Teacher', 'Class teacher with additional responsibilities', 1, 45],
        ['accountant', 'Accountant', 'Access to financial modules only', 1, 60],
        ['librarian', 'Librarian', 'Access to library module only', 1, 70],
        ['staff', 'Staff', 'General staff with limited access', 1, 80],
        ['parent', 'Parent', 'Parent with view access to their children', 1, 90],
        ['student', 'Student', 'Student with self-service access', 1, 100]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO roles (id, school_id, code, name, description, is_system, hierarchy_level)
        VALUES (UUID(), NULL, ?, ?, ?, ?, ?)
    ");

    foreach ($rolesSeed as $role) {
        try {
            $stmt->execute($role);
            echo "  [+] Seeded role: {$role[0]}\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "  [=] Role exists: {$role[0]}\n";
            }
        }
    }

    // Seed Permissions
    $permissionsSeed = [
        ['students.view', 'View Students', 'view', 'students'],
        ['students.create', 'Create Students', 'create', 'students'],
        ['students.edit', 'Edit Students', 'edit', 'students'],
        ['students.delete', 'Delete Students', 'delete', 'students'],
        ['students.export', 'Export Students', 'export', 'students'],
        ['teachers.view', 'View Teachers', 'view', 'teachers'],
        ['teachers.create', 'Create Teachers', 'create', 'teachers'],
        ['teachers.edit', 'Edit Teachers', 'edit', 'teachers'],
        ['teachers.delete', 'Delete Teachers', 'delete', 'teachers'],
        ['attendance.view', 'View Attendance', 'view', 'attendance'],
        ['attendance.create', 'Mark Attendance', 'create', 'attendance'],
        ['attendance.edit', 'Edit Attendance', 'edit', 'attendance'],
        ['fees.view', 'View Fees', 'view', 'fees'],
        ['fees.create', 'Create Fee Records', 'create', 'fees'],
        ['fees.edit', 'Edit Fees', 'edit', 'fees'],
        ['fees.delete', 'Delete Fees', 'delete', 'fees'],
        ['fees.collect', 'Collect Fees', 'manage', 'fees'],
        ['exams.view', 'View Exams', 'view', 'exams'],
        ['exams.create', 'Create Exams', 'create', 'exams'],
        ['exams.edit', 'Edit Exams', 'edit', 'exams'],
        ['exams.delete', 'Delete Exams', 'delete', 'exams'],
        ['exams.results', 'Manage Results', 'manage', 'exams'],
        ['reports.view', 'View Reports', 'view', 'reports'],
        ['reports.export', 'Export Reports', 'export', 'reports'],
        ['settings.view', 'View Settings', 'view', 'settings'],
        ['settings.edit', 'Edit Settings', 'edit', 'settings'],
        ['users.view', 'View Users', 'view', 'users'],
        ['users.create', 'Create Users', 'create', 'users'],
        ['users.edit', 'Edit Users', 'edit', 'users'],
        ['users.delete', 'Delete Users', 'delete', 'users'],
        ['users.roles', 'Manage Roles', 'manage', 'users']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO permissions (id, code, name, action, resource)
        VALUES (UUID(), ?, ?, ?, ?)
    ");

    foreach ($permissionsSeed as $permission) {
        try {
            $stmt->execute($permission);
            echo "  [+] Seeded permission: {$permission[0]}\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "  [=] Permission exists: {$permission[0]}\n";
            }
        }
    }

    // Show summary
    echo "\n=== MULTI-TENANT TABLES SUMMARY ===\n\n";

    $multiTenantTables = array_keys($tables);
    foreach ($multiTenantTables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "  {$table}: {$count} rows\n";
        } catch (PDOException $e) {
            echo "  {$table}: [ERROR]\n";
        }
    }

    echo "\n[SUCCESS] Multi-tenant schema migration complete!\n";
    echo "=== MIGRATION COMPLETE ===\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
?>
