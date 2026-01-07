-- ============================================================================
-- MULTI-TENANT SAAS ARCHITECTURE - SYSTEM LEVEL TABLES
-- Migration: 001_system_level_tables.sql
-- Description: Creates all system-level tables for multi-tenant SaaS platform
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. TENANTS TABLE - Core tenant/institution management
-- ============================================================================
CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Display ID like TEN001, TEN002',
    name VARCHAR(255) NOT NULL COMMENT 'Institution/Organization name',
    slug VARCHAR(100) NOT NULL UNIQUE COMMENT 'URL-friendly identifier',
    domain VARCHAR(255) DEFAULT NULL COMMENT 'Custom domain if any',

    -- Contact Information
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    country VARCHAR(100) DEFAULT 'India',
    postal_code VARCHAR(20) DEFAULT NULL,

    -- Branding
    logo_url VARCHAR(500) DEFAULT NULL,
    primary_color VARCHAR(7) DEFAULT '#3b82f6',
    secondary_color VARCHAR(7) DEFAULT '#1e40af',

    -- Subscription & Plan
    plan_id INT UNSIGNED DEFAULT NULL,
    subscription_status ENUM('trial', 'active', 'suspended', 'cancelled', 'expired') DEFAULT 'trial',
    trial_ends_at DATETIME DEFAULT NULL,
    subscription_starts_at DATETIME DEFAULT NULL,
    subscription_ends_at DATETIME DEFAULT NULL,

    -- Limits (can override plan limits)
    max_users INT UNSIGNED DEFAULT NULL,
    max_students INT UNSIGNED DEFAULT NULL,
    max_storage_mb INT UNSIGNED DEFAULT NULL,

    -- Status & Metadata
    status ENUM('active', 'inactive', 'suspended', 'pending') DEFAULT 'pending',
    settings JSON DEFAULT NULL COMMENT 'Tenant-specific settings JSON',
    metadata JSON DEFAULT NULL COMMENT 'Additional metadata',

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    INDEX idx_tenant_code (tenant_code),
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_subscription_status (subscription_status),
    INDEX idx_plan_id (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. SUBSCRIPTION_PLANS TABLE - Available subscription plans
-- ============================================================================
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    plan_code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Display ID like PLAN001',
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,

    -- Pricing
    price_monthly DECIMAL(10, 2) DEFAULT 0.00,
    price_yearly DECIMAL(10, 2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'INR',

    -- Limits
    max_users INT UNSIGNED DEFAULT 10,
    max_students INT UNSIGNED DEFAULT 100,
    max_teachers INT UNSIGNED DEFAULT 20,
    max_storage_mb INT UNSIGNED DEFAULT 1024 COMMENT '1GB default',

    -- Features
    features JSON DEFAULT NULL COMMENT 'List of included features',

    -- Trial
    trial_days INT UNSIGNED DEFAULT 14,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    is_public BOOLEAN DEFAULT TRUE COMMENT 'Visible on pricing page',
    sort_order INT DEFAULT 0,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_plan_code (plan_code),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. SYSTEM_MODULES TABLE - All available system modules
-- ============================================================================
CREATE TABLE IF NOT EXISTS system_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    module_code VARCHAR(50) NOT NULL UNIQUE COMMENT 'e.g., students, teachers, fees',
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT NULL COMMENT 'Icon class name',

    -- Categorization
    category VARCHAR(50) DEFAULT 'general' COMMENT 'e.g., academic, finance, hr',
    parent_module_id INT UNSIGNED DEFAULT NULL COMMENT 'For sub-modules',

    -- Display
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    is_core BOOLEAN DEFAULT FALSE COMMENT 'Core modules cannot be disabled',

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_module_code (module_code),
    INDEX idx_category (category),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (parent_module_id) REFERENCES system_modules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. SYSTEM_ACTIONS TABLE - Available actions per module
-- ============================================================================
CREATE TABLE IF NOT EXISTS system_actions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    action_code VARCHAR(50) NOT NULL COMMENT 'e.g., view, create, edit, delete, export',
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_action_code (action_code),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. PLAN_MODULES TABLE - Modules included in each plan
-- ============================================================================
CREATE TABLE IF NOT EXISTS plan_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,

    -- Feature flags for this module in this plan
    is_enabled BOOLEAN DEFAULT TRUE,
    feature_limits JSON DEFAULT NULL COMMENT 'Module-specific limits',

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_plan_module (plan_id, module_id),
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES system_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. SYSTEM_ROLES TABLE - Pre-defined system roles (templates)
-- ============================================================================
CREATE TABLE IF NOT EXISTS system_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    role_code VARCHAR(50) NOT NULL UNIQUE COMMENT 'e.g., superadmin, admin, teacher, student',
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,

    -- Hierarchy
    hierarchy_level INT DEFAULT 0 COMMENT 'Higher = more privileges',

    -- Type
    is_system BOOLEAN DEFAULT FALSE COMMENT 'System roles cannot be modified by tenants',
    is_template BOOLEAN DEFAULT TRUE COMMENT 'Can be used as template for tenant roles',

    -- Default permissions JSON structure
    default_permissions JSON DEFAULT NULL,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_role_code (role_code),
    INDEX idx_is_system (is_system),
    INDEX idx_hierarchy_level (hierarchy_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. SYSTEM_ADMINS TABLE - Platform administrators (not tenant users)
-- ============================================================================
CREATE TABLE IF NOT EXISTS system_admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    admin_code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Display ID like SADM001',

    -- Identity
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,

    -- Role
    role ENUM('super_admin', 'admin', 'support', 'viewer') DEFAULT 'admin',

    -- Security
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    last_login_ip VARCHAR(45) DEFAULT NULL,

    -- Status
    status ENUM('active', 'inactive', 'locked') DEFAULT 'active',
    failed_login_attempts INT DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,

    -- Tokens
    remember_token VARCHAR(100) DEFAULT NULL,
    password_reset_token VARCHAR(255) DEFAULT NULL,
    password_reset_expires_at DATETIME DEFAULT NULL,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,

    INDEX idx_admin_code (admin_code),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. SYSTEM_SETTINGS TABLE - Global platform settings
-- ============================================================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json', 'encrypted') DEFAULT 'string',

    -- Categorization
    category VARCHAR(50) DEFAULT 'general',
    description TEXT DEFAULT NULL,

    -- Validation
    is_required BOOLEAN DEFAULT FALSE,
    default_value TEXT DEFAULT NULL,
    validation_rules JSON DEFAULT NULL,

    -- Access
    is_public BOOLEAN DEFAULT FALSE COMMENT 'Can be accessed without auth',
    is_editable BOOLEAN DEFAULT TRUE,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED DEFAULT NULL,

    INDEX idx_setting_key (setting_key),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. SYSTEM_AUDIT_LOG TABLE - System-level audit trail
-- ============================================================================
CREATE TABLE IF NOT EXISTS system_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,

    -- Actor
    actor_type ENUM('system_admin', 'system', 'api', 'cron') NOT NULL,
    actor_id INT UNSIGNED DEFAULT NULL,
    actor_email VARCHAR(255) DEFAULT NULL,

    -- Action
    action VARCHAR(100) NOT NULL COMMENT 'e.g., tenant.created, plan.updated',
    resource_type VARCHAR(100) DEFAULT NULL COMMENT 'e.g., tenant, plan, module',
    resource_id VARCHAR(50) DEFAULT NULL,

    -- Details
    description TEXT DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    metadata JSON DEFAULT NULL,

    -- Request Info
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    request_url VARCHAR(500) DEFAULT NULL,

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_actor (actor_type, actor_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. TENANT_INVITATIONS TABLE - Pending tenant invitations
-- ============================================================================
CREATE TABLE IF NOT EXISTS tenant_invitations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,

    -- Invitation Details
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    organization_name VARCHAR(255) DEFAULT NULL,

    -- Plan
    plan_id INT UNSIGNED DEFAULT NULL,

    -- Token
    invitation_token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,

    -- Status
    status ENUM('pending', 'accepted', 'expired', 'cancelled') DEFAULT 'pending',
    accepted_at DATETIME DEFAULT NULL,
    tenant_id INT UNSIGNED DEFAULT NULL COMMENT 'Set when accepted',

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,

    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_token (invitation_token),
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- END OF MIGRATION 001
-- ============================================================================
