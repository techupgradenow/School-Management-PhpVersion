-- ============================================================================
-- MULTI-TENANT SCHOOL MANAGEMENT SYSTEM
-- Database Schema with UUID-based Architecture
-- Version: 2.0
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ============================================================================
-- 1. SUBSCRIPTION PLANS
-- Defines what features are available at each tier
-- ============================================================================

CREATE TABLE IF NOT EXISTS `plans` (
    `id` CHAR(36) PRIMARY KEY,                    -- UUID
    `code` VARCHAR(50) NOT NULL UNIQUE,           -- 'free', 'basic', 'premium', 'enterprise'
    `name` VARCHAR(100) NOT NULL,                 -- Display name
    `description` TEXT NULL,
    `price_monthly` DECIMAL(10,2) DEFAULT 0.00,
    `price_yearly` DECIMAL(10,2) DEFAULT 0.00,
    `currency` VARCHAR(3) DEFAULT 'INR',
    `max_students` INT DEFAULT 100,               -- NULL = unlimited
    `max_teachers` INT DEFAULT 10,
    `max_users` INT DEFAULT 20,
    `max_storage_gb` INT DEFAULT 1,               -- Storage limit in GB
    `is_active` TINYINT(1) DEFAULT 1,
    `is_default` TINYINT(1) DEFAULT 0,            -- Default plan for new schools
    `trial_days` INT DEFAULT 0,                   -- Free trial period
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_code` (`code`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. FEATURES / MODULES
-- All available features in the system
-- ============================================================================

CREATE TABLE IF NOT EXISTS `features` (
    `id` CHAR(36) PRIMARY KEY,                    -- UUID
    `code` VARCHAR(50) NOT NULL UNIQUE,           -- 'students', 'attendance', 'fees', 'library'
    `name` VARCHAR(100) NOT NULL,                 -- Display name
    `description` TEXT NULL,
    `category` VARCHAR(50) NULL,                  -- 'core', 'academic', 'finance', 'communication'
    `icon` VARCHAR(50) NULL,                      -- Font Awesome icon class
    `route` VARCHAR(100) NULL,                    -- Frontend route/path
    `api_endpoint` VARCHAR(100) NULL,             -- API base endpoint
    `parent_id` CHAR(36) NULL,                    -- For sub-features/modules
    `is_premium` TINYINT(1) DEFAULT 0,            -- Quick check if premium-only
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_code` (`code`),
    INDEX `idx_category` (`category`),
    INDEX `idx_parent` (`parent_id`),
    INDEX `idx_premium` (`is_premium`),
    FOREIGN KEY (`parent_id`) REFERENCES `features`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. PLAN_FEATURES
-- Maps which features are available in which plans
-- ============================================================================

CREATE TABLE IF NOT EXISTS `plan_features` (
    `id` CHAR(36) PRIMARY KEY,
    `plan_id` CHAR(36) NOT NULL,
    `feature_id` CHAR(36) NOT NULL,
    `is_enabled` TINYINT(1) DEFAULT 1,
    `usage_limit` INT NULL,                       -- NULL = unlimited, else max usage
    `limit_type` ENUM('daily', 'monthly', 'yearly', 'total') NULL,
    `custom_config` JSON NULL,                    -- Plan-specific feature config
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `unique_plan_feature` (`plan_id`, `feature_id`),
    INDEX `idx_plan` (`plan_id`),
    INDEX `idx_feature` (`feature_id`),
    FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`feature_id`) REFERENCES `features`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. SCHOOLS (Tenants)
-- Each school is a separate tenant
-- ============================================================================

CREATE TABLE IF NOT EXISTS `schools` (
    `id` CHAR(36) PRIMARY KEY,                    -- UUID (tenant_id)
    `code` VARCHAR(20) NOT NULL UNIQUE,           -- Short unique code: 'SCH001'
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) UNIQUE NULL,              -- URL-friendly: 'delhi-public-school'
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `website` VARCHAR(255) NULL,
    `logo` TEXT NULL,                             -- Logo URL/base64
    `address` TEXT NULL,
    `city` VARCHAR(100) NULL,
    `state` VARCHAR(100) NULL,
    `country` VARCHAR(100) DEFAULT 'India',
    `pincode` VARCHAR(20) NULL,
    `timezone` VARCHAR(50) DEFAULT 'Asia/Kolkata',
    `currency` VARCHAR(3) DEFAULT 'INR',
    `academic_year_start` TINYINT DEFAULT 4,      -- April
    `academic_year_end` TINYINT DEFAULT 3,        -- March

    -- Plan & Subscription
    `plan_id` CHAR(36) NULL,
    `subscription_status` ENUM('trial', 'active', 'expired', 'cancelled', 'suspended') DEFAULT 'trial',
    `subscription_start` DATE NULL,
    `subscription_end` DATE NULL,
    `trial_ends_at` DATE NULL,
    `is_premium` TINYINT(1) GENERATED ALWAYS AS (
        CASE WHEN `subscription_status` = 'active' AND `subscription_end` >= CURDATE() THEN 1 ELSE 0 END
    ) STORED,

    -- Settings
    `settings` JSON NULL,                         -- School-specific settings
    `custom_domain` VARCHAR(255) NULL,            -- Custom domain if any
    `is_active` TINYINT(1) DEFAULT 1,
    `is_verified` TINYINT(1) DEFAULT 0,
    `verified_at` TIMESTAMP NULL,

    -- Metadata
    `created_by` CHAR(36) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,                  -- Soft delete

    INDEX `idx_code` (`code`),
    INDEX `idx_slug` (`slug`),
    INDEX `idx_plan` (`plan_id`),
    INDEX `idx_status` (`subscription_status`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_premium` (`is_premium`),
    FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. SCHOOL_FEATURE_OVERRIDES
-- Custom feature overrides per school (beyond their plan)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `school_feature_overrides` (
    `id` CHAR(36) PRIMARY KEY,
    `school_id` CHAR(36) NOT NULL,
    `feature_id` CHAR(36) NOT NULL,
    `is_enabled` TINYINT(1) DEFAULT 1,            -- Override enable/disable
    `usage_limit` INT NULL,                       -- Custom limit for this school
    `valid_from` DATE NULL,
    `valid_until` DATE NULL,                      -- Temporary access
    `reason` VARCHAR(255) NULL,                   -- Why this override exists
    `granted_by` CHAR(36) NULL,                   -- Super admin who granted
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `unique_school_feature` (`school_id`, `feature_id`),
    INDEX `idx_school` (`school_id`),
    INDEX `idx_feature` (`feature_id`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`feature_id`) REFERENCES `features`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. GLOBAL PERMISSIONS
-- All possible permissions in the system
-- ============================================================================

CREATE TABLE IF NOT EXISTS `permissions` (
    `id` CHAR(36) PRIMARY KEY,
    `code` VARCHAR(100) NOT NULL UNIQUE,          -- 'students.view', 'students.create'
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `feature_id` CHAR(36) NULL,                   -- Link to feature/module
    `action` ENUM('view', 'create', 'edit', 'delete', 'export', 'import', 'approve', 'manage') NOT NULL,
    `resource` VARCHAR(50) NOT NULL,              -- 'students', 'teachers', 'fees'
    `is_system` TINYINT(1) DEFAULT 0,             -- System permissions can't be modified
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_code` (`code`),
    INDEX `idx_feature` (`feature_id`),
    INDEX `idx_resource_action` (`resource`, `action`),
    FOREIGN KEY (`feature_id`) REFERENCES `features`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. ROLES (School-specific or Global)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `roles` (
    `id` CHAR(36) PRIMARY KEY,
    `school_id` CHAR(36) NULL,                    -- NULL = global/system role
    `code` VARCHAR(50) NOT NULL,                  -- 'super_admin', 'school_admin', 'teacher'
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_system` TINYINT(1) DEFAULT 0,             -- System roles can't be deleted
    `is_default` TINYINT(1) DEFAULT 0,            -- Default role for new users
    `hierarchy_level` INT DEFAULT 100,            -- Lower = more powerful (1=super admin)
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `unique_school_role` (`school_id`, `code`),
    INDEX `idx_school` (`school_id`),
    INDEX `idx_code` (`code`),
    INDEX `idx_hierarchy` (`hierarchy_level`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. ROLE_PERMISSIONS
-- Maps permissions to roles
-- ============================================================================

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` CHAR(36) PRIMARY KEY,
    `role_id` CHAR(36) NOT NULL,
    `permission_id` CHAR(36) NOT NULL,
    `is_granted` TINYINT(1) DEFAULT 1,
    `conditions` JSON NULL,                       -- Conditional permissions (e.g., own data only)
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`),
    INDEX `idx_role` (`role_id`),
    INDEX `idx_permission` (`permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. GLOBAL USERS
-- All users across all schools
-- ============================================================================

CREATE TABLE IF NOT EXISTS `global_users` (
    `id` CHAR(36) PRIMARY KEY,                    -- UUID
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NULL,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NULL,
    `full_name` VARCHAR(255) GENERATED ALWAYS AS (
        CONCAT(`first_name`, IFNULL(CONCAT(' ', `last_name`), ''))
    ) STORED,
    `avatar` TEXT NULL,
    `email_verified_at` TIMESTAMP NULL,
    `phone_verified_at` TIMESTAMP NULL,
    `is_super_admin` TINYINT(1) DEFAULT 0,        -- Platform super admin
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login_at` TIMESTAMP NULL,
    `last_login_ip` VARCHAR(45) NULL,
    `password_changed_at` TIMESTAMP NULL,
    `remember_token` VARCHAR(100) NULL,
    `two_factor_enabled` TINYINT(1) DEFAULT 0,
    `two_factor_secret` VARCHAR(255) NULL,
    `preferences` JSON NULL,                      -- User preferences
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,

    INDEX `idx_email` (`email`),
    INDEX `idx_phone` (`phone`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_super_admin` (`is_super_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. SCHOOL_USERS
-- Links users to schools with roles (a user can belong to multiple schools)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `school_users` (
    `id` CHAR(36) PRIMARY KEY,
    `school_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `role_id` CHAR(36) NOT NULL,
    `employee_id` VARCHAR(50) NULL,               -- School-specific ID
    `user_type` ENUM('admin', 'teacher', 'staff', 'student', 'parent', 'accountant') NOT NULL,
    `department` VARCHAR(100) NULL,
    `designation` VARCHAR(100) NULL,
    `joining_date` DATE NULL,
    `is_primary_school` TINYINT(1) DEFAULT 1,     -- User's primary school
    `is_active` TINYINT(1) DEFAULT 1,
    `permissions_override` JSON NULL,             -- User-specific permission overrides
    `settings` JSON NULL,                         -- User-specific settings for this school
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. SUBSCRIPTION_HISTORY
-- Track plan changes and payments
-- ============================================================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 12. FEATURE_USAGE_LOG
-- Track feature usage for analytics and limits
-- ============================================================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 13. AUDIT_LOG
-- Track all important actions
-- ============================================================================

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `school_id` CHAR(36) NULL,
    `user_id` CHAR(36) NULL,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,           -- 'student', 'fee', 'user'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 14. SESSIONS (for tracking active sessions)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` CHAR(36) PRIMARY KEY,
    `user_id` CHAR(36) NOT NULL,
    `school_id` CHAR(36) NULL,                    -- Current active school
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- SEED DATA: Default Plans
-- ============================================================================

INSERT INTO `plans` (`id`, `code`, `name`, `description`, `price_monthly`, `price_yearly`, `max_students`, `max_teachers`, `max_users`, `max_storage_gb`, `is_active`, `is_default`, `trial_days`, `sort_order`) VALUES
(UUID(), 'free', 'Free', 'Basic features for small schools', 0, 0, 50, 5, 10, 1, 1, 1, 0, 1),
(UUID(), 'basic', 'Basic', 'Essential features for growing schools', 999, 9990, 200, 20, 50, 5, 1, 0, 14, 2),
(UUID(), 'premium', 'Premium', 'Advanced features for established schools', 2499, 24990, 1000, 100, 200, 25, 1, 0, 14, 3),
(UUID(), 'enterprise', 'Enterprise', 'Unlimited features for large institutions', 4999, 49990, NULL, NULL, NULL, 100, 1, 0, 30, 4);

-- ============================================================================
-- SEED DATA: Features/Modules
-- ============================================================================

INSERT INTO `features` (`id`, `code`, `name`, `category`, `icon`, `route`, `is_premium`, `sort_order`) VALUES
-- Core Features (Free)
(UUID(), 'dashboard', 'Dashboard', 'core', 'fa-dashboard', '/dashboard', 0, 1),
(UUID(), 'students', 'Student Management', 'core', 'fa-user-graduate', '/students', 0, 2),
(UUID(), 'teachers', 'Teacher Management', 'core', 'fa-chalkboard-teacher', '/teachers', 0, 3),
(UUID(), 'classes', 'Class Management', 'core', 'fa-school', '/classes', 0, 4),
(UUID(), 'attendance', 'Attendance', 'academic', 'fa-calendar-check', '/attendance', 0, 5),

-- Academic Features (Basic+)
(UUID(), 'timetable', 'Timetable', 'academic', 'fa-clock', '/timetable', 0, 6),
(UUID(), 'exams', 'Examinations', 'academic', 'fa-file-alt', '/exams', 0, 7),
(UUID(), 'syllabus', 'Syllabus Management', 'academic', 'fa-book', '/syllabus', 0, 8),
(UUID(), 'assignments', 'Assignments', 'academic', 'fa-tasks', '/assignments', 1, 9),

-- Finance Features (Basic+)
(UUID(), 'fees', 'Fee Management', 'finance', 'fa-money-bill', '/fees', 0, 10),
(UUID(), 'payroll', 'Payroll', 'finance', 'fa-wallet', '/payroll', 1, 11),
(UUID(), 'expenses', 'Expense Tracking', 'finance', 'fa-receipt', '/expenses', 1, 12),

-- Premium Features
(UUID(), 'library', 'Library Management', 'resources', 'fa-book-reader', '/library', 1, 13),
(UUID(), 'transport', 'Transport Management', 'resources', 'fa-bus', '/transport', 1, 14),
(UUID(), 'hostel', 'Hostel Management', 'resources', 'fa-building', '/hostel', 1, 15),
(UUID(), 'inventory', 'Inventory', 'resources', 'fa-boxes', '/inventory', 1, 16),

-- Communication (Premium)
(UUID(), 'notifications', 'Notifications', 'communication', 'fa-bell', '/notifications', 0, 17),
(UUID(), 'sms', 'SMS Gateway', 'communication', 'fa-sms', '/sms', 1, 18),
(UUID(), 'email', 'Email Campaigns', 'communication', 'fa-envelope', '/email', 1, 19),
(UUID(), 'whatsapp', 'WhatsApp Integration', 'communication', 'fa-whatsapp', '/whatsapp', 1, 20),

-- Reports (Premium)
(UUID(), 'reports', 'Basic Reports', 'analytics', 'fa-chart-bar', '/reports', 0, 21),
(UUID(), 'advanced_reports', 'Advanced Analytics', 'analytics', 'fa-chart-line', '/analytics', 1, 22),
(UUID(), 'export', 'Data Export', 'analytics', 'fa-download', '/export', 1, 23),

-- Enterprise Features
(UUID(), 'api_access', 'API Access', 'integration', 'fa-plug', '/api', 1, 24),
(UUID(), 'multi_branch', 'Multi-Branch', 'enterprise', 'fa-sitemap', '/branches', 1, 25),
(UUID(), 'custom_branding', 'Custom Branding', 'enterprise', 'fa-palette', '/branding', 1, 26);

-- ============================================================================
-- SEED DATA: Global Roles
-- ============================================================================

INSERT INTO `roles` (`id`, `school_id`, `code`, `name`, `description`, `is_system`, `hierarchy_level`) VALUES
(UUID(), NULL, 'super_admin', 'Super Administrator', 'Platform super admin with full access', 1, 1),
(UUID(), NULL, 'school_admin', 'School Administrator', 'Full access to school management', 1, 10),
(UUID(), NULL, 'principal', 'Principal', 'School principal with oversight access', 1, 20),
(UUID(), NULL, 'vice_principal', 'Vice Principal', 'Vice principal with limited admin access', 1, 25),
(UUID(), NULL, 'teacher', 'Teacher', 'Teacher with class and student access', 1, 50),
(UUID(), NULL, 'class_teacher', 'Class Teacher', 'Class teacher with additional responsibilities', 1, 45),
(UUID(), NULL, 'accountant', 'Accountant', 'Access to financial modules only', 1, 60),
(UUID(), NULL, 'librarian', 'Librarian', 'Access to library module only', 1, 70),
(UUID(), NULL, 'staff', 'Staff', 'General staff with limited access', 1, 80),
(UUID(), NULL, 'parent', 'Parent', 'Parent with view access to their children', 1, 90),
(UUID(), NULL, 'student', 'Student', 'Student with self-service access', 1, 100);

-- ============================================================================
-- SEED DATA: Permissions
-- ============================================================================

INSERT INTO `permissions` (`id`, `code`, `name`, `action`, `resource`) VALUES
-- Student Permissions
(UUID(), 'students.view', 'View Students', 'view', 'students'),
(UUID(), 'students.create', 'Create Students', 'create', 'students'),
(UUID(), 'students.edit', 'Edit Students', 'edit', 'students'),
(UUID(), 'students.delete', 'Delete Students', 'delete', 'students'),
(UUID(), 'students.export', 'Export Students', 'export', 'students'),

-- Teacher Permissions
(UUID(), 'teachers.view', 'View Teachers', 'view', 'teachers'),
(UUID(), 'teachers.create', 'Create Teachers', 'create', 'teachers'),
(UUID(), 'teachers.edit', 'Edit Teachers', 'edit', 'teachers'),
(UUID(), 'teachers.delete', 'Delete Teachers', 'delete', 'teachers'),

-- Attendance Permissions
(UUID(), 'attendance.view', 'View Attendance', 'view', 'attendance'),
(UUID(), 'attendance.create', 'Mark Attendance', 'create', 'attendance'),
(UUID(), 'attendance.edit', 'Edit Attendance', 'edit', 'attendance'),

-- Fee Permissions
(UUID(), 'fees.view', 'View Fees', 'view', 'fees'),
(UUID(), 'fees.create', 'Create Fee Records', 'create', 'fees'),
(UUID(), 'fees.edit', 'Edit Fees', 'edit', 'fees'),
(UUID(), 'fees.delete', 'Delete Fees', 'delete', 'fees'),
(UUID(), 'fees.collect', 'Collect Fees', 'manage', 'fees'),

-- Exam Permissions
(UUID(), 'exams.view', 'View Exams', 'view', 'exams'),
(UUID(), 'exams.create', 'Create Exams', 'create', 'exams'),
(UUID(), 'exams.edit', 'Edit Exams', 'edit', 'exams'),
(UUID(), 'exams.delete', 'Delete Exams', 'delete', 'exams'),
(UUID(), 'exams.results', 'Manage Results', 'manage', 'exams'),

-- Report Permissions
(UUID(), 'reports.view', 'View Reports', 'view', 'reports'),
(UUID(), 'reports.export', 'Export Reports', 'export', 'reports'),

-- Settings Permissions
(UUID(), 'settings.view', 'View Settings', 'view', 'settings'),
(UUID(), 'settings.edit', 'Edit Settings', 'edit', 'settings'),

-- User Management
(UUID(), 'users.view', 'View Users', 'view', 'users'),
(UUID(), 'users.create', 'Create Users', 'create', 'users'),
(UUID(), 'users.edit', 'Edit Users', 'edit', 'users'),
(UUID(), 'users.delete', 'Delete Users', 'delete', 'users'),
(UUID(), 'users.roles', 'Manage Roles', 'manage', 'users');

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
