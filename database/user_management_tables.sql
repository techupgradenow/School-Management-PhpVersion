-- ========================================
-- User Management Additional Tables
-- EduManage Pro
-- ========================================

USE edumanage_pro;

-- Role Permissions Table
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL UNIQUE,
    permissions JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login Activity Table
CREATE TABLE IF NOT EXISTS login_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    action ENUM('login', 'logout', 'failed_login') NOT NULL DEFAULT 'login',
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Access Logs Table
CREATE TABLE IF NOT EXISTS access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    action VARCHAR(50) NOT NULL,
    module VARCHAR(50) NOT NULL,
    target_id VARCHAR(50),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_module (module),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Default Permissions for Each Role
INSERT INTO role_permissions (role, permissions) VALUES
('Admin', '{
    "Dashboard": {"view": true, "add": true, "edit": true, "delete": true},
    "Students": {"view": true, "add": true, "edit": true, "delete": true},
    "Teachers": {"view": true, "add": true, "edit": true, "delete": true},
    "Attendance": {"view": true, "add": true, "edit": true, "delete": true},
    "Exams & Grades": {"view": true, "add": true, "edit": true, "delete": true},
    "Timetable": {"view": true, "add": true, "edit": true, "delete": true},
    "Fee Management": {"view": true, "add": true, "edit": true, "delete": true},
    "Payroll": {"view": true, "add": true, "edit": true, "delete": true},
    "Library": {"view": true, "add": true, "edit": true, "delete": true},
    "Reports": {"view": true, "add": true, "edit": true, "delete": false},
    "Settings": {"view": true, "add": true, "edit": true, "delete": false},
    "User Management": {"view": true, "add": true, "edit": true, "delete": false}
}'),
('Teacher', '{
    "Dashboard": {"view": true, "add": false, "edit": false, "delete": false},
    "Students": {"view": true, "add": false, "edit": false, "delete": false},
    "Teachers": {"view": false, "add": false, "edit": false, "delete": false},
    "Attendance": {"view": true, "add": true, "edit": true, "delete": false},
    "Exams & Grades": {"view": true, "add": true, "edit": true, "delete": false},
    "Timetable": {"view": true, "add": false, "edit": false, "delete": false},
    "Fee Management": {"view": false, "add": false, "edit": false, "delete": false},
    "Payroll": {"view": false, "add": false, "edit": false, "delete": false},
    "Library": {"view": true, "add": false, "edit": false, "delete": false},
    "Reports": {"view": true, "add": false, "edit": false, "delete": false},
    "Settings": {"view": false, "add": false, "edit": false, "delete": false},
    "User Management": {"view": false, "add": false, "edit": false, "delete": false}
}'),
('Student', '{
    "Dashboard": {"view": true, "add": false, "edit": false, "delete": false},
    "Students": {"view": false, "add": false, "edit": false, "delete": false},
    "Teachers": {"view": false, "add": false, "edit": false, "delete": false},
    "Attendance": {"view": true, "add": false, "edit": false, "delete": false},
    "Exams & Grades": {"view": true, "add": false, "edit": false, "delete": false},
    "Timetable": {"view": true, "add": false, "edit": false, "delete": false},
    "Fee Management": {"view": true, "add": false, "edit": false, "delete": false},
    "Payroll": {"view": false, "add": false, "edit": false, "delete": false},
    "Library": {"view": true, "add": false, "edit": false, "delete": false},
    "Reports": {"view": false, "add": false, "edit": false, "delete": false},
    "Settings": {"view": false, "add": false, "edit": false, "delete": false},
    "User Management": {"view": false, "add": false, "edit": false, "delete": false}
}'),
('Parent', '{
    "Dashboard": {"view": true, "add": false, "edit": false, "delete": false},
    "Students": {"view": true, "add": false, "edit": false, "delete": false},
    "Teachers": {"view": false, "add": false, "edit": false, "delete": false},
    "Attendance": {"view": true, "add": false, "edit": false, "delete": false},
    "Exams & Grades": {"view": true, "add": false, "edit": false, "delete": false},
    "Timetable": {"view": true, "add": false, "edit": false, "delete": false},
    "Fee Management": {"view": true, "add": false, "edit": false, "delete": false},
    "Payroll": {"view": false, "add": false, "edit": false, "delete": false},
    "Library": {"view": false, "add": false, "edit": false, "delete": false},
    "Reports": {"view": false, "add": false, "edit": false, "delete": false},
    "Settings": {"view": false, "add": false, "edit": false, "delete": false},
    "User Management": {"view": false, "add": false, "edit": false, "delete": false}
}')
ON DUPLICATE KEY UPDATE permissions = VALUES(permissions);

-- Add some sample login activity
INSERT INTO login_activity (user_id, action, ip_address, user_agent) VALUES
('USR001', 'login', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'),
('USR001', 'login', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0');

-- Add some sample access logs
INSERT INTO access_logs (user_id, action, module, target_id, details, ip_address) VALUES
('USR001', 'create', 'Students', 'STU001', 'Added new student: Test Student', '192.168.1.100'),
('USR001', 'update', 'Settings', NULL, 'Modified institution settings', '192.168.1.100'),
('USR001', 'view', 'Reports', NULL, 'Viewed attendance report', '192.168.1.100');

SELECT 'User Management tables created successfully!' AS status;
