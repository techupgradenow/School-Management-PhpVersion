-- ============================================================================
-- MULTI-TENANT SAAS ARCHITECTURE - TENANT LEVEL RBAC TABLES
-- Migration: 002_tenant_rbac_tables.sql
-- Description: Creates tenant-level user management and RBAC tables
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. USERS TABLE - Tenant users (replaces existing users table)
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    user_code VARCHAR(20) NOT NULL COMMENT 'Display ID like USR001, USR002',
    tenant_id INT UNSIGNED NOT NULL,

    -- Identity
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    username VARCHAR(100) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,

    -- Profile
    avatar_url VARCHAR(500) DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,
    gender ENUM('male', 'female', 'other') DEFAULT NULL,
    address TEXT DEFAULT NULL,

    -- Employment (for staff)
    employee_id VARCHAR(50) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    designation VARCHAR(100) DEFAULT NULL,
    joining_date DATE DEFAULT NULL,

    -- Security
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    email_verified_at DATETIME DEFAULT NULL,
    phone_verified_at DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    last_login_ip VARCHAR(45) DEFAULT NULL,
    last_activity_at DATETIME DEFAULT NULL,

    -- Status
    status ENUM('active', 'inactive', 'suspended', 'pending') DEFAULT 'pending',
    failed_login_attempts INT DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,

    -- Tokens
    remember_token VARCHAR(100) DEFAULT NULL,
    password_reset_token VARCHAR(255) DEFAULT NULL,
    password_reset_expires_at DATETIME DEFAULT NULL,
    email_verification_token VARCHAR(255) DEFAULT NULL,

    -- Preferences
    preferences JSON DEFAULT NULL,
    notification_settings JSON DEFAULT NULL,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    UNIQUE KEY uk_tenant_email (tenant_id, email),
    UNIQUE KEY uk_tenant_username (tenant_id, username),
    UNIQUE KEY uk_tenant_user_code (tenant_id, user_code),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_status (status),
    INDEX idx_email (email),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. ROLES TABLE - Tenant-specific roles
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    role_code VARCHAR(50) NOT NULL COMMENT 'e.g., admin, teacher, accountant',
    tenant_id INT UNSIGNED NOT NULL,

    -- Details
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#6b7280' COMMENT 'Badge color',

    -- Hierarchy
    hierarchy_level INT DEFAULT 0 COMMENT 'Higher = more privileges',
    parent_role_id INT UNSIGNED DEFAULT NULL,

    -- Template Reference
    system_role_id INT UNSIGNED DEFAULT NULL COMMENT 'If created from system template',

    -- Flags
    is_default BOOLEAN DEFAULT FALSE COMMENT 'Default role for new users',
    is_protected BOOLEAN DEFAULT FALSE COMMENT 'Cannot be deleted',
    can_be_assigned BOOLEAN DEFAULT TRUE COMMENT 'Can be assigned to users',

    -- Status
    is_active BOOLEAN DEFAULT TRUE,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_role_code (tenant_id, role_code),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_hierarchy_level (hierarchy_level),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_role_id) REFERENCES mt_roles(id) ON DELETE SET NULL,
    FOREIGN KEY (system_role_id) REFERENCES system_roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. PERMISSIONS TABLE - Master permission definitions per tenant
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    -- Permission Identity
    module_id INT UNSIGNED NOT NULL,
    action_id INT UNSIGNED NOT NULL,

    -- Permission Key (derived: module_code.action_code e.g., students.create)
    permission_key VARCHAR(100) NOT NULL,

    -- Display
    name VARCHAR(150) NOT NULL COMMENT 'e.g., Create Students',
    description TEXT DEFAULT NULL,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_permission (tenant_id, module_id, action_id),
    UNIQUE KEY uk_tenant_permission_key (tenant_id, permission_key),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_module_id (module_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES system_modules(id) ON DELETE CASCADE,
    FOREIGN KEY (action_id) REFERENCES system_actions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. ROLE_PERMISSIONS TABLE - Permissions assigned to roles
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_role_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,

    -- Grant/Deny
    is_granted BOOLEAN DEFAULT TRUE,

    -- Conditions (for advanced ABAC)
    conditions JSON DEFAULT NULL COMMENT 'e.g., {"own_records_only": true}',

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    granted_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_role_permission (role_id, permission_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_role_id (role_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES mt_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES mt_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. USER_ROLES TABLE - Users can have multiple roles
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_user_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,

    -- Primary role flag
    is_primary BOOLEAN DEFAULT FALSE,

    -- Validity period (optional)
    valid_from DATETIME DEFAULT NULL,
    valid_until DATETIME DEFAULT NULL,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    assigned_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_user_role (user_id, role_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user_id (user_id),
    INDEX idx_role_id (role_id),
    INDEX idx_is_primary (is_primary),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES mt_users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES mt_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. USER_PERMISSIONS TABLE - Direct user permission overrides
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_user_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,

    -- Override type: grant gives permission, deny removes it
    override_type ENUM('grant', 'deny') NOT NULL DEFAULT 'grant',

    -- Conditions
    conditions JSON DEFAULT NULL,

    -- Reason for override
    reason TEXT DEFAULT NULL,

    -- Validity period
    valid_from DATETIME DEFAULT NULL,
    valid_until DATETIME DEFAULT NULL,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    granted_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_user_permission (user_id, permission_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user_id (user_id),
    INDEX idx_override_type (override_type),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES mt_users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES mt_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. TENANT_MODULES TABLE - Modules enabled for each tenant
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_tenant_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,

    -- Status
    is_enabled BOOLEAN DEFAULT TRUE,

    -- Customization
    custom_name VARCHAR(100) DEFAULT NULL COMMENT 'Tenant can rename modules',
    custom_icon VARCHAR(50) DEFAULT NULL,
    sort_order INT DEFAULT 0,

    -- Feature limits/config
    config JSON DEFAULT NULL,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    enabled_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_module (tenant_id, module_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_is_enabled (is_enabled),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES system_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. TENANT_SETTINGS TABLE - Tenant-specific settings
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_tenant_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,

    -- Setting
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json', 'encrypted') DEFAULT 'string',

    -- Categorization
    category VARCHAR(50) DEFAULT 'general',

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_setting (tenant_id, setting_key),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_category (category),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. USER_SESSIONS TABLE - Active user sessions
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_user_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,

    -- Session
    session_token VARCHAR(255) NOT NULL UNIQUE,
    refresh_token VARCHAR(255) DEFAULT NULL,

    -- Device Info
    device_type VARCHAR(50) DEFAULT NULL COMMENT 'desktop, mobile, tablet',
    device_name VARCHAR(100) DEFAULT NULL,
    browser VARCHAR(100) DEFAULT NULL,
    os VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,

    -- Location
    location VARCHAR(255) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    last_activity_at DATETIME DEFAULT NULL,
    expires_at DATETIME NOT NULL,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_is_active (is_active),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES mt_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. PASSWORD_HISTORY TABLE - Password history for security
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_password_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES mt_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- END OF MIGRATION 002
-- ============================================================================
