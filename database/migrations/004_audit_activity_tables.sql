-- ============================================================================
-- MULTI-TENANT SAAS ARCHITECTURE - AUDIT & ACTIVITY TABLES
-- Migration: 004_audit_activity_tables.sql
-- Description: Creates audit logging and activity tracking tables
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. ACTIVITY LOGS - Comprehensive activity tracking per tenant
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,

    -- Actor (who performed the action)
    user_id INT UNSIGNED DEFAULT NULL,
    user_name VARCHAR(255) DEFAULT NULL,
    user_email VARCHAR(255) DEFAULT NULL,
    user_role VARCHAR(100) DEFAULT NULL,

    -- Action details
    action VARCHAR(100) NOT NULL COMMENT 'e.g., created, updated, deleted, viewed, exported',
    action_category VARCHAR(50) DEFAULT NULL COMMENT 'e.g., user_management, student, fee, attendance',

    -- Resource (what was affected)
    resource_type VARCHAR(100) NOT NULL COMMENT 'e.g., student, teacher, fee_payment',
    resource_id VARCHAR(50) DEFAULT NULL,
    resource_name VARCHAR(255) DEFAULT NULL COMMENT 'Human readable identifier',

    -- Change details
    description TEXT DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    changes JSON DEFAULT NULL COMMENT 'Only changed fields',

    -- Request context
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    request_method VARCHAR(10) DEFAULT NULL,
    request_url VARCHAR(500) DEFAULT NULL,
    request_id VARCHAR(36) DEFAULT NULL COMMENT 'Correlation ID for request tracing',

    -- Location (optional, from IP)
    country VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,

    -- Metadata
    metadata JSON DEFAULT NULL,
    tags JSON DEFAULT NULL COMMENT 'For filtering/categorization',

    -- Performance
    execution_time_ms INT UNSIGNED DEFAULT NULL,

    -- Status
    status ENUM('success', 'failed', 'warning') DEFAULT 'success',
    error_message TEXT DEFAULT NULL,

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes for common queries
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_action_category (action_category),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at),
    INDEX idx_tenant_date (tenant_id, created_at),
    INDEX idx_user_date (user_id, created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
    PARTITION p202501 VALUES LESS THAN (202502),
    PARTITION p202502 VALUES LESS THAN (202503),
    PARTITION p202503 VALUES LESS THAN (202504),
    PARTITION p202504 VALUES LESS THAN (202505),
    PARTITION p202505 VALUES LESS THAN (202506),
    PARTITION p202506 VALUES LESS THAN (202507),
    PARTITION p202507 VALUES LESS THAN (202508),
    PARTITION p202508 VALUES LESS THAN (202509),
    PARTITION p202509 VALUES LESS THAN (202510),
    PARTITION p202510 VALUES LESS THAN (202511),
    PARTITION p202511 VALUES LESS THAN (202512),
    PARTITION p202512 VALUES LESS THAN (202601),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- ============================================================================
-- 2. LOGIN HISTORY - Track all login attempts
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_login_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    tenant_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL for system admin logins',

    -- User (can be tenant user or system admin)
    user_type ENUM('user', 'system_admin') NOT NULL DEFAULT 'user',
    user_id INT UNSIGNED DEFAULT NULL,
    user_email VARCHAR(255) NOT NULL,

    -- Login details
    login_type ENUM('password', 'sso', 'oauth', 'api_key', 'magic_link', '2fa') DEFAULT 'password',
    provider VARCHAR(50) DEFAULT NULL COMMENT 'For OAuth: google, microsoft, etc.',

    -- Status
    status ENUM('success', 'failed', 'blocked', 'mfa_required', 'mfa_failed') NOT NULL,
    failure_reason VARCHAR(255) DEFAULT NULL COMMENT 'e.g., invalid_password, account_locked',

    -- Device/Browser info
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT DEFAULT NULL,
    device_type VARCHAR(50) DEFAULT NULL COMMENT 'desktop, mobile, tablet',
    device_name VARCHAR(100) DEFAULT NULL,
    browser VARCHAR(100) DEFAULT NULL,
    browser_version VARCHAR(50) DEFAULT NULL,
    os VARCHAR(100) DEFAULT NULL,
    os_version VARCHAR(50) DEFAULT NULL,

    -- Location (from IP geolocation)
    country VARCHAR(100) DEFAULT NULL,
    country_code CHAR(2) DEFAULT NULL,
    region VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,

    -- Session
    session_id VARCHAR(255) DEFAULT NULL,
    session_duration_seconds INT UNSIGNED DEFAULT NULL,
    logout_at DATETIME DEFAULT NULL,
    logout_type ENUM('manual', 'timeout', 'forced', 'session_limit') DEFAULT NULL,

    -- Security
    is_suspicious BOOLEAN DEFAULT FALSE,
    risk_score INT DEFAULT 0 COMMENT '0-100, higher = more risky',
    risk_factors JSON DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user (user_type, user_id),
    INDEX idx_email (user_email),
    INDEX idx_status (status),
    INDEX idx_ip_address (ip_address),
    INDEX idx_created_at (created_at),
    INDEX idx_suspicious (is_suspicious),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. DATA CHANGE AUDIT - Detailed record of data changes (for compliance)
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_data_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,

    -- Change info
    table_name VARCHAR(100) NOT NULL,
    record_id VARCHAR(50) NOT NULL,
    operation ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,

    -- Actor
    user_id INT UNSIGNED DEFAULT NULL,
    user_email VARCHAR(255) DEFAULT NULL,

    -- Data
    old_data JSON DEFAULT NULL,
    new_data JSON DEFAULT NULL,
    changed_fields JSON DEFAULT NULL COMMENT 'List of field names that changed',

    -- Context
    ip_address VARCHAR(45) DEFAULT NULL,
    request_id VARCHAR(36) DEFAULT NULL,
    source VARCHAR(50) DEFAULT 'web' COMMENT 'web, api, import, system',

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_operation (operation),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. API REQUEST LOGS - Track API usage (optional, for debugging/billing)
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_api_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    tenant_id INT UNSIGNED DEFAULT NULL,

    -- Request
    request_id VARCHAR(36) NOT NULL,
    method VARCHAR(10) NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    query_params JSON DEFAULT NULL,
    request_body JSON DEFAULT NULL COMMENT 'Sanitized, no passwords',
    request_headers JSON DEFAULT NULL,

    -- Response
    status_code INT NOT NULL,
    response_body JSON DEFAULT NULL COMMENT 'Optional, can be large',
    response_time_ms INT UNSIGNED NOT NULL,

    -- Auth
    auth_type VARCHAR(50) DEFAULT NULL COMMENT 'bearer, api_key, session',
    user_id INT UNSIGNED DEFAULT NULL,
    api_key_id INT UNSIGNED DEFAULT NULL,

    -- Client
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT DEFAULT NULL,

    -- Rate limiting
    rate_limit_remaining INT DEFAULT NULL,

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_request_id (request_id),
    INDEX idx_endpoint (endpoint(255)),
    INDEX idx_status_code (status_code),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. NOTIFICATION LOGS - Track sent notifications
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_notification_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,

    -- Notification details
    notification_type ENUM('email', 'sms', 'push', 'in_app', 'whatsapp') NOT NULL,
    template_id VARCHAR(50) DEFAULT NULL,
    subject VARCHAR(255) DEFAULT NULL,

    -- Recipient
    recipient_type ENUM('user', 'student', 'guardian', 'teacher', 'external') NOT NULL,
    recipient_id INT UNSIGNED DEFAULT NULL,
    recipient_email VARCHAR(255) DEFAULT NULL,
    recipient_phone VARCHAR(20) DEFAULT NULL,

    -- Content
    content TEXT DEFAULT NULL,
    variables JSON DEFAULT NULL COMMENT 'Template variables used',

    -- Delivery status
    status ENUM('pending', 'sent', 'delivered', 'failed', 'bounced', 'opened', 'clicked') DEFAULT 'pending',
    sent_at DATETIME DEFAULT NULL,
    delivered_at DATETIME DEFAULT NULL,
    opened_at DATETIME DEFAULT NULL,
    clicked_at DATETIME DEFAULT NULL,

    -- Error handling
    error_message TEXT DEFAULT NULL,
    retry_count INT DEFAULT 0,
    next_retry_at DATETIME DEFAULT NULL,

    -- Provider info
    provider VARCHAR(50) DEFAULT NULL COMMENT 'sendgrid, twilio, etc.',
    provider_message_id VARCHAR(100) DEFAULT NULL,

    -- Metadata
    metadata JSON DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_type (notification_type),
    INDEX idx_recipient (recipient_type, recipient_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. SECURITY EVENTS - Track security-related events
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_security_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    tenant_id INT UNSIGNED DEFAULT NULL,

    -- Event type
    event_type VARCHAR(100) NOT NULL COMMENT 'e.g., failed_login, password_reset, role_change, permission_escalation',
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'low',

    -- Actor
    user_id INT UNSIGNED DEFAULT NULL,
    user_email VARCHAR(255) DEFAULT NULL,
    actor_type ENUM('user', 'system_admin', 'system', 'api', 'unknown') DEFAULT 'unknown',

    -- Target
    target_type VARCHAR(100) DEFAULT NULL,
    target_id VARCHAR(50) DEFAULT NULL,

    -- Details
    description TEXT NOT NULL,
    details JSON DEFAULT NULL,

    -- Risk assessment
    risk_score INT DEFAULT 0,
    is_blocked BOOLEAN DEFAULT FALSE,
    requires_review BOOLEAN DEFAULT FALSE,
    reviewed_at DATETIME DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    review_notes TEXT DEFAULT NULL,

    -- Request context
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_event_type (event_type),
    INDEX idx_severity (severity),
    INDEX idx_user_id (user_id),
    INDEX idx_requires_review (requires_review),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. FILE UPLOADS LOG - Track all file uploads
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_file_uploads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    -- File info
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL COMMENT 'Size in bytes',
    file_hash VARCHAR(64) DEFAULT NULL COMMENT 'SHA-256 hash',

    -- Storage
    storage_disk VARCHAR(50) DEFAULT 'local' COMMENT 'local, s3, gcs',
    storage_url VARCHAR(500) DEFAULT NULL,
    cdn_url VARCHAR(500) DEFAULT NULL,

    -- Association
    uploadable_type VARCHAR(100) DEFAULT NULL COMMENT 'e.g., student, teacher, document',
    uploadable_id INT UNSIGNED DEFAULT NULL,
    category VARCHAR(50) DEFAULT NULL COMMENT 'avatar, document, attachment',

    -- Access
    is_public BOOLEAN DEFAULT FALSE,
    access_token VARCHAR(100) DEFAULT NULL,
    download_count INT UNSIGNED DEFAULT 0,

    -- Uploader
    uploaded_by INT UNSIGNED DEFAULT NULL,

    -- Status
    status ENUM('pending', 'processing', 'completed', 'failed', 'deleted') DEFAULT 'completed',

    -- Virus scan (if enabled)
    scan_status ENUM('pending', 'clean', 'infected', 'error') DEFAULT NULL,
    scanned_at DATETIME DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_uploadable (uploadable_type, uploadable_id),
    INDEX idx_category (category),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES mt_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. SCHEDULED JOBS LOG - Track scheduled/background jobs
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_job_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    tenant_id INT UNSIGNED DEFAULT NULL,

    -- Job info
    job_name VARCHAR(100) NOT NULL,
    job_type VARCHAR(50) DEFAULT 'scheduled' COMMENT 'scheduled, queued, manual',
    queue VARCHAR(50) DEFAULT 'default',

    -- Execution
    started_at DATETIME NOT NULL,
    completed_at DATETIME DEFAULT NULL,
    execution_time_ms INT UNSIGNED DEFAULT NULL,

    -- Status
    status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    progress INT DEFAULT 0 COMMENT 'Percentage 0-100',

    -- Input/Output
    input_data JSON DEFAULT NULL,
    output_data JSON DEFAULT NULL,

    -- Error handling
    error_message TEXT DEFAULT NULL,
    error_stack TEXT DEFAULT NULL,
    retry_count INT DEFAULT 0,
    max_retries INT DEFAULT 3,

    -- Triggered by
    triggered_by INT UNSIGNED DEFAULT NULL,
    trigger_type ENUM('schedule', 'user', 'system', 'api') DEFAULT 'schedule',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_job_name (job_name),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. EXPORT LOGS - Track data exports (for compliance)
-- ============================================================================
CREATE TABLE IF NOT EXISTS mt_export_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    -- Export details
    export_type VARCHAR(50) NOT NULL COMMENT 'e.g., students, fees, reports',
    export_format ENUM('csv', 'xlsx', 'pdf', 'json') NOT NULL,

    -- Filters applied
    filters JSON DEFAULT NULL,
    date_range_start DATE DEFAULT NULL,
    date_range_end DATE DEFAULT NULL,

    -- Result
    record_count INT UNSIGNED DEFAULT 0,
    file_path VARCHAR(500) DEFAULT NULL,
    file_size BIGINT UNSIGNED DEFAULT NULL,

    -- User
    exported_by INT UNSIGNED NOT NULL,

    -- Reason (for compliance)
    reason TEXT DEFAULT NULL,

    -- Status
    status ENUM('pending', 'processing', 'completed', 'failed', 'expired') DEFAULT 'pending',
    expires_at DATETIME DEFAULT NULL COMMENT 'When download link expires',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,

    -- Indexes
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_export_type (export_type),
    INDEX idx_exported_by (exported_by),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (exported_by) REFERENCES mt_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- END OF MIGRATION 004
-- ============================================================================
