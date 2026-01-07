-- =====================================================
-- ACTIVITY HISTORY TRACKING SYSTEM
-- Centralized audit logging for all modules
-- =====================================================

-- Drop existing table if exists (for fresh install)
DROP TABLE IF EXISTS `activity_history`;

-- Create the main activity history table
CREATE TABLE `activity_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module` VARCHAR(50) NOT NULL COMMENT 'Module name: students, teachers, fees, etc.',
    `entity_id` VARCHAR(50) NOT NULL COMMENT 'Primary key of the affected record',
    `entity_name` VARCHAR(255) DEFAULT NULL COMMENT 'Human-readable identifier (e.g., student name)',
    `action` ENUM('ADD', 'UPDATE', 'DELETE', 'RESTORE', 'LOGIN', 'LOGOUT', 'EXPORT', 'IMPORT') NOT NULL,
    `old_data` JSON DEFAULT NULL COMMENT 'Data before change (UPDATE/DELETE)',
    `new_data` JSON DEFAULT NULL COMMENT 'Data after change (ADD/UPDATE)',
    `changed_fields` JSON DEFAULT NULL COMMENT 'List of fields that were modified',
    `performed_by` VARCHAR(50) DEFAULT NULL COMMENT 'User ID who performed the action',
    `performed_by_name` VARCHAR(100) DEFAULT NULL COMMENT 'Username/Name of performer',
    `user_role` VARCHAR(50) DEFAULT NULL COMMENT 'Role: Super Admin, Admin, Teacher, etc.',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 address',
    `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'Browser/client information',
    `session_id` VARCHAR(100) DEFAULT NULL COMMENT 'Session identifier',
    `request_method` VARCHAR(10) DEFAULT NULL COMMENT 'HTTP method: GET, POST, PUT, DELETE',
    `request_url` VARCHAR(500) DEFAULT NULL COMMENT 'Full request URL',
    `description` TEXT DEFAULT NULL COMMENT 'Human-readable description of the action',
    `status` ENUM('SUCCESS', 'FAILED', 'PENDING') DEFAULT 'SUCCESS',
    `error_message` TEXT DEFAULT NULL COMMENT 'Error details if status is FAILED',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Indexes for fast querying
    INDEX `idx_module` (`module`),
    INDEX `idx_entity_id` (`entity_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_performed_by` (`performed_by`),
    INDEX `idx_user_role` (`user_role`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_module_entity` (`module`, `entity_id`),
    INDEX `idx_module_action` (`module`, `action`),
    INDEX `idx_date_range` (`created_at`, `module`),

    -- Full-text search on description
    FULLTEXT INDEX `ft_description` (`description`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Centralized activity history tracking for all modules';

-- =====================================================
-- ACTIVITY HISTORY SUMMARY VIEW
-- For quick reporting and dashboard
-- =====================================================

CREATE OR REPLACE VIEW `activity_history_summary` AS
SELECT
    DATE(created_at) as activity_date,
    module,
    action,
    COUNT(*) as action_count,
    COUNT(DISTINCT performed_by) as unique_users
FROM activity_history
WHERE status = 'SUCCESS'
GROUP BY DATE(created_at), module, action
ORDER BY activity_date DESC, module, action;

-- =====================================================
-- RECENT ACTIVITY VIEW
-- Last 100 activities for dashboard
-- =====================================================

CREATE OR REPLACE VIEW `recent_activity` AS
SELECT
    id,
    module,
    entity_id,
    entity_name,
    action,
    performed_by_name,
    user_role,
    description,
    ip_address,
    created_at
FROM activity_history
WHERE status = 'SUCCESS'
ORDER BY created_at DESC
LIMIT 100;

-- =====================================================
-- USER ACTIVITY VIEW
-- Activity grouped by user
-- =====================================================

CREATE OR REPLACE VIEW `user_activity_summary` AS
SELECT
    performed_by,
    performed_by_name,
    user_role,
    COUNT(*) as total_actions,
    SUM(CASE WHEN action = 'ADD' THEN 1 ELSE 0 END) as adds,
    SUM(CASE WHEN action = 'UPDATE' THEN 1 ELSE 0 END) as updates,
    SUM(CASE WHEN action = 'DELETE' THEN 1 ELSE 0 END) as deletes,
    MAX(created_at) as last_activity
FROM activity_history
WHERE status = 'SUCCESS' AND performed_by IS NOT NULL
GROUP BY performed_by, performed_by_name, user_role
ORDER BY total_actions DESC;

-- =====================================================
-- STORED PROCEDURE: Get Entity History
-- Retrieves complete history for a specific entity
-- =====================================================

DELIMITER //

CREATE PROCEDURE IF NOT EXISTS `GetEntityHistory`(
    IN p_module VARCHAR(50),
    IN p_entity_id VARCHAR(50),
    IN p_limit INT
)
BEGIN
    SET p_limit = IFNULL(p_limit, 50);

    SELECT
        id,
        action,
        old_data,
        new_data,
        changed_fields,
        performed_by_name,
        user_role,
        ip_address,
        description,
        created_at
    FROM activity_history
    WHERE module = p_module
      AND entity_id = p_entity_id
      AND status = 'SUCCESS'
    ORDER BY created_at DESC
    LIMIT p_limit;
END //

-- =====================================================
-- STORED PROCEDURE: Get Module Statistics
-- Returns activity statistics for a module
-- =====================================================

CREATE PROCEDURE IF NOT EXISTS `GetModuleStatistics`(
    IN p_module VARCHAR(50),
    IN p_days INT
)
BEGIN
    SET p_days = IFNULL(p_days, 30);

    SELECT
        action,
        COUNT(*) as count,
        DATE(created_at) as date
    FROM activity_history
    WHERE module = p_module
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL p_days DAY)
      AND status = 'SUCCESS'
    GROUP BY action, DATE(created_at)
    ORDER BY date DESC, action;
END //

-- =====================================================
-- STORED PROCEDURE: Cleanup Old History
-- Removes history older than specified days
-- =====================================================

CREATE PROCEDURE IF NOT EXISTS `CleanupOldHistory`(
    IN p_days INT
)
BEGIN
    SET p_days = IFNULL(p_days, 365);

    DELETE FROM activity_history
    WHERE created_at < DATE_SUB(CURDATE(), INTERVAL p_days DAY);

    SELECT ROW_COUNT() as deleted_records;
END //

DELIMITER ;

-- =====================================================
-- TRIGGER: Auto-cleanup (optional - runs monthly)
-- Uncomment if you want automatic cleanup
-- =====================================================

-- CREATE EVENT IF NOT EXISTS `cleanup_old_history`
-- ON SCHEDULE EVERY 1 MONTH
-- DO CALL CleanupOldHistory(365);

-- =====================================================
-- SAMPLE DATA (for testing)
-- =====================================================

-- INSERT INTO activity_history (module, entity_id, entity_name, action, new_data, performed_by, performed_by_name, user_role, ip_address, description)
-- VALUES ('system', 'SETUP', 'Activity History System', 'ADD', '{"version": "1.0", "installed": true}', 'SYSTEM', 'System', 'System', '127.0.0.1', 'Activity history tracking system installed');

SELECT 'Activity History Schema Created Successfully!' as status;
