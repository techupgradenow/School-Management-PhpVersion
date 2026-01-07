-- ============================================================================
-- MULTI-TENANT SAAS ARCHITECTURE - SYSTEM DATA SEEDER
-- Seeder: 001_seed_system_data.sql
-- Description: Seeds initial system-level data (modules, actions, plans, roles)
-- ============================================================================

-- ============================================================================
-- 1. SEED SYSTEM ACTIONS (Standard CRUD + extras)
-- ============================================================================
INSERT INTO system_actions (uuid, action_code, name, description, sort_order, is_active) VALUES
(UUID(), 'view', 'View', 'Permission to view/read records', 1, TRUE),
(UUID(), 'create', 'Create', 'Permission to create new records', 2, TRUE),
(UUID(), 'edit', 'Edit', 'Permission to edit/update existing records', 3, TRUE),
(UUID(), 'delete', 'Delete', 'Permission to delete records', 4, TRUE),
(UUID(), 'export', 'Export', 'Permission to export data to files', 5, TRUE),
(UUID(), 'import', 'Import', 'Permission to import data from files', 6, TRUE),
(UUID(), 'print', 'Print', 'Permission to print records', 7, TRUE),
(UUID(), 'approve', 'Approve', 'Permission to approve pending items', 8, TRUE),
(UUID(), 'manage', 'Manage', 'Full management permissions', 9, TRUE)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ============================================================================
-- 2. SEED SYSTEM MODULES
-- ============================================================================

-- Core Modules (cannot be disabled)
INSERT INTO system_modules (uuid, module_code, name, description, icon, category, sort_order, is_active, is_core) VALUES
(UUID(), 'dashboard', 'Dashboard', 'Main dashboard with overview and analytics', 'fas fa-tachometer-alt', 'core', 1, TRUE, TRUE),
(UUID(), 'settings', 'Settings', 'System and tenant configuration settings', 'fas fa-cog', 'core', 99, TRUE, TRUE),
(UUID(), 'users', 'User Management', 'Manage system users and their access', 'fas fa-users-cog', 'core', 98, TRUE, TRUE);

-- Academic Modules
INSERT INTO system_modules (uuid, module_code, name, description, icon, category, sort_order, is_active, is_core) VALUES
(UUID(), 'students', 'Students', 'Student registration and management', 'fas fa-user-graduate', 'academic', 10, TRUE, FALSE),
(UUID(), 'teachers', 'Teachers', 'Teacher/Staff management', 'fas fa-chalkboard-teacher', 'academic', 11, TRUE, FALSE),
(UUID(), 'classes', 'Classes', 'Class and section management', 'fas fa-school', 'academic', 12, TRUE, FALSE),
(UUID(), 'subjects', 'Subjects', 'Subject/Course management', 'fas fa-book', 'academic', 13, TRUE, FALSE),
(UUID(), 'attendance', 'Attendance', 'Student and teacher attendance tracking', 'fas fa-calendar-check', 'academic', 14, TRUE, FALSE),
(UUID(), 'timetable', 'Timetable', 'Class timetable management', 'fas fa-calendar-alt', 'academic', 15, TRUE, FALSE);

-- Examination Modules
INSERT INTO system_modules (uuid, module_code, name, description, icon, category, sort_order, is_active, is_core) VALUES
(UUID(), 'exams', 'Examinations', 'Exam scheduling and management', 'fas fa-file-alt', 'examination', 20, TRUE, FALSE),
(UUID(), 'marks', 'Marks Entry', 'Enter and manage exam marks', 'fas fa-edit', 'examination', 21, TRUE, FALSE),
(UUID(), 'reportcard', 'Report Cards', 'Generate and print report cards', 'fas fa-id-card', 'examination', 22, TRUE, FALSE),
(UUID(), 'admitcard', 'Admit Cards', 'Generate and print admit cards', 'fas fa-address-card', 'examination', 23, TRUE, FALSE);

-- Finance Modules
INSERT INTO system_modules (uuid, module_code, name, description, icon, category, sort_order, is_active, is_core) VALUES
(UUID(), 'fees', 'Fee Management', 'Fee structure, invoicing and collection', 'fas fa-rupee-sign', 'finance', 30, TRUE, FALSE),
(UUID(), 'payroll', 'Payroll', 'Staff salary and payroll management', 'fas fa-money-check-alt', 'finance', 31, TRUE, FALSE),
(UUID(), 'accounts', 'Accounts', 'Financial accounts and reporting', 'fas fa-calculator', 'finance', 32, TRUE, FALSE),
(UUID(), 'expenses', 'Expenses', 'Track and manage expenses', 'fas fa-receipt', 'finance', 33, TRUE, FALSE);

-- Facility Modules
INSERT INTO system_modules (uuid, module_code, name, description, icon, category, sort_order, is_active, is_core) VALUES
(UUID(), 'transport', 'Transport', 'Vehicle and route management', 'fas fa-bus', 'facility', 40, TRUE, FALSE),
(UUID(), 'hostel', 'Hostel', 'Hostel and room management', 'fas fa-building', 'facility', 41, TRUE, FALSE),
(UUID(), 'library', 'Library', 'Book inventory and issue management', 'fas fa-book-reader', 'facility', 42, TRUE, FALSE),
(UUID(), 'inventory', 'Inventory', 'Asset and inventory management', 'fas fa-boxes', 'facility', 43, TRUE, FALSE);

-- Communication Modules
INSERT INTO system_modules (uuid, module_code, name, description, icon, category, sort_order, is_active, is_core) VALUES
(UUID(), 'notices', 'Notices', 'Notice board and announcements', 'fas fa-bullhorn', 'communication', 50, TRUE, FALSE),
(UUID(), 'events', 'Events', 'Event and calendar management', 'fas fa-calendar', 'communication', 51, TRUE, FALSE),
(UUID(), 'messages', 'Messages', 'Internal messaging system', 'fas fa-envelope', 'communication', 52, TRUE, FALSE),
(UUID(), 'sms', 'SMS', 'SMS notifications and alerts', 'fas fa-sms', 'communication', 53, TRUE, FALSE);

-- Reports Module
INSERT INTO system_modules (uuid, module_code, name, description, icon, category, sort_order, is_active, is_core) VALUES
(UUID(), 'reports', 'Reports', 'Analytics and report generation', 'fas fa-chart-bar', 'reports', 60, TRUE, FALSE);

-- HR Modules
INSERT INTO system_modules (uuid, module_code, name, description, icon, category, sort_order, is_active, is_core) VALUES
(UUID(), 'leave', 'Leave Management', 'Staff leave applications and approval', 'fas fa-plane-departure', 'hr', 70, TRUE, FALSE),
(UUID(), 'staff_attendance', 'Staff Attendance', 'Staff check-in/check-out tracking', 'fas fa-clock', 'hr', 71, TRUE, FALSE);

-- ============================================================================
-- 3. SEED SUBSCRIPTION PLANS
-- ============================================================================
INSERT INTO subscription_plans (uuid, plan_code, name, description, price_monthly, price_yearly, max_users, max_students, max_teachers, max_storage_mb, trial_days, is_active, is_public, sort_order, features) VALUES
(UUID(), 'PLAN_FREE', 'Free', 'Basic plan for small institutions', 0.00, 0.00, 5, 50, 10, 512, 0, TRUE, TRUE, 1,
 JSON_OBJECT('modules', JSON_ARRAY('dashboard', 'students', 'teachers', 'classes', 'attendance'), 'support', 'email', 'branding', FALSE)),

(UUID(), 'PLAN_STARTER', 'Starter', 'For small to medium schools', 999.00, 9990.00, 20, 300, 30, 2048, 14, TRUE, TRUE, 2,
 JSON_OBJECT('modules', JSON_ARRAY('dashboard', 'students', 'teachers', 'classes', 'attendance', 'fees', 'exams', 'marks', 'reportcard'), 'support', 'email', 'branding', FALSE)),

(UUID(), 'PLAN_PRO', 'Professional', 'Complete solution for medium schools', 2499.00, 24990.00, 50, 1000, 100, 10240, 14, TRUE, TRUE, 3,
 JSON_OBJECT('modules', JSON_ARRAY('all'), 'support', 'priority', 'branding', TRUE, 'api_access', TRUE)),

(UUID(), 'PLAN_ENTERPRISE', 'Enterprise', 'Full-featured for large institutions', 4999.00, 49990.00, 200, 5000, 500, 51200, 30, TRUE, TRUE, 4,
 JSON_OBJECT('modules', JSON_ARRAY('all'), 'support', 'dedicated', 'branding', TRUE, 'api_access', TRUE, 'sso', TRUE, 'white_label', TRUE)),

(UUID(), 'PLAN_CUSTOM', 'Custom', 'Tailored solution for special needs', 0.00, 0.00, 999999, 999999, 999999, 102400, 30, TRUE, FALSE, 5,
 JSON_OBJECT('modules', JSON_ARRAY('all'), 'support', 'dedicated', 'branding', TRUE, 'api_access', TRUE, 'sso', TRUE, 'white_label', TRUE, 'custom_development', TRUE))
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ============================================================================
-- 4. SEED PLAN-MODULE MAPPINGS
-- ============================================================================

-- Free Plan Modules
INSERT INTO plan_modules (plan_id, module_id, is_enabled)
SELECT
    (SELECT id FROM subscription_plans WHERE plan_code = 'PLAN_FREE'),
    id,
    TRUE
FROM system_modules
WHERE module_code IN ('dashboard', 'students', 'teachers', 'classes', 'attendance', 'settings', 'users');

-- Starter Plan Modules
INSERT INTO plan_modules (plan_id, module_id, is_enabled)
SELECT
    (SELECT id FROM subscription_plans WHERE plan_code = 'PLAN_STARTER'),
    id,
    TRUE
FROM system_modules
WHERE module_code IN ('dashboard', 'students', 'teachers', 'classes', 'subjects', 'attendance', 'fees', 'exams', 'marks', 'reportcard', 'admitcard', 'notices', 'settings', 'users');

-- Pro and Enterprise Plans - All Modules
INSERT INTO plan_modules (plan_id, module_id, is_enabled)
SELECT
    (SELECT id FROM subscription_plans WHERE plan_code = 'PLAN_PRO'),
    id,
    TRUE
FROM system_modules;

INSERT INTO plan_modules (plan_id, module_id, is_enabled)
SELECT
    (SELECT id FROM subscription_plans WHERE plan_code = 'PLAN_ENTERPRISE'),
    id,
    TRUE
FROM system_modules;

INSERT INTO plan_modules (plan_id, module_id, is_enabled)
SELECT
    (SELECT id FROM subscription_plans WHERE plan_code = 'PLAN_CUSTOM'),
    id,
    TRUE
FROM system_modules;

-- ============================================================================
-- 5. SEED SYSTEM ROLES (Templates)
-- ============================================================================
INSERT INTO system_roles (uuid, role_code, name, description, hierarchy_level, is_system, is_template, is_active, default_permissions) VALUES

-- Super Admin - Highest level, all permissions
(UUID(), 'superadmin', 'Super Admin', 'Full system access with all permissions', 100, TRUE, FALSE, TRUE,
 JSON_OBJECT('all_modules', TRUE, 'all_actions', TRUE)),

-- Admin - Institution level admin
(UUID(), 'admin', 'Administrator', 'Institution administrator with broad permissions', 90, FALSE, TRUE, TRUE,
 JSON_OBJECT(
     'dashboard', JSON_ARRAY('view', 'manage'),
     'students', JSON_ARRAY('view', 'create', 'edit', 'delete', 'export', 'import'),
     'teachers', JSON_ARRAY('view', 'create', 'edit', 'delete', 'export'),
     'classes', JSON_ARRAY('view', 'create', 'edit', 'delete'),
     'subjects', JSON_ARRAY('view', 'create', 'edit', 'delete'),
     'attendance', JSON_ARRAY('view', 'create', 'edit', 'export'),
     'fees', JSON_ARRAY('view', 'create', 'edit', 'delete', 'export', 'approve'),
     'exams', JSON_ARRAY('view', 'create', 'edit', 'delete'),
     'marks', JSON_ARRAY('view', 'create', 'edit', 'approve'),
     'users', JSON_ARRAY('view', 'create', 'edit', 'delete'),
     'settings', JSON_ARRAY('view', 'edit'),
     'reports', JSON_ARRAY('view', 'export')
 )),

-- Principal
(UUID(), 'principal', 'Principal', 'School principal with oversight permissions', 85, FALSE, TRUE, TRUE,
 JSON_OBJECT(
     'dashboard', JSON_ARRAY('view'),
     'students', JSON_ARRAY('view', 'export'),
     'teachers', JSON_ARRAY('view', 'export'),
     'attendance', JSON_ARRAY('view', 'export'),
     'fees', JSON_ARRAY('view', 'approve'),
     'exams', JSON_ARRAY('view', 'approve'),
     'marks', JSON_ARRAY('view', 'approve'),
     'reports', JSON_ARRAY('view', 'export')
 )),

-- Teacher
(UUID(), 'teacher', 'Teacher', 'Teaching staff with class-related permissions', 50, FALSE, TRUE, TRUE,
 JSON_OBJECT(
     'dashboard', JSON_ARRAY('view'),
     'students', JSON_ARRAY('view'),
     'attendance', JSON_ARRAY('view', 'create', 'edit'),
     'marks', JSON_ARRAY('view', 'create', 'edit'),
     'timetable', JSON_ARRAY('view'),
     'notices', JSON_ARRAY('view')
 )),

-- Accountant
(UUID(), 'accountant', 'Accountant', 'Finance and accounts staff', 40, FALSE, TRUE, TRUE,
 JSON_OBJECT(
     'dashboard', JSON_ARRAY('view'),
     'students', JSON_ARRAY('view'),
     'fees', JSON_ARRAY('view', 'create', 'edit', 'export'),
     'payroll', JSON_ARRAY('view', 'create', 'edit'),
     'accounts', JSON_ARRAY('view', 'create', 'edit', 'export'),
     'expenses', JSON_ARRAY('view', 'create', 'edit'),
     'reports', JSON_ARRAY('view', 'export')
 )),

-- Librarian
(UUID(), 'librarian', 'Librarian', 'Library management staff', 35, FALSE, TRUE, TRUE,
 JSON_OBJECT(
     'dashboard', JSON_ARRAY('view'),
     'students', JSON_ARRAY('view'),
     'teachers', JSON_ARRAY('view'),
     'library', JSON_ARRAY('view', 'create', 'edit', 'delete')
 )),

-- Transport Manager
(UUID(), 'transport_manager', 'Transport Manager', 'Transport and vehicle management', 35, FALSE, TRUE, TRUE,
 JSON_OBJECT(
     'dashboard', JSON_ARRAY('view'),
     'students', JSON_ARRAY('view'),
     'transport', JSON_ARRAY('view', 'create', 'edit', 'delete')
 )),

-- Hostel Warden
(UUID(), 'hostel_warden', 'Hostel Warden', 'Hostel management staff', 35, FALSE, TRUE, TRUE,
 JSON_OBJECT(
     'dashboard', JSON_ARRAY('view'),
     'students', JSON_ARRAY('view'),
     'hostel', JSON_ARRAY('view', 'create', 'edit')
 )),

-- Receptionist/Front Office
(UUID(), 'receptionist', 'Receptionist', 'Front office and basic data entry', 30, FALSE, TRUE, TRUE,
 JSON_OBJECT(
     'dashboard', JSON_ARRAY('view'),
     'students', JSON_ARRAY('view', 'create'),
     'fees', JSON_ARRAY('view', 'create'),
     'notices', JSON_ARRAY('view')
 )),

-- Student (for student portal)
(UUID(), 'student', 'Student', 'Student portal access', 10, FALSE, TRUE, TRUE,
 JSON_OBJECT(
     'dashboard', JSON_ARRAY('view'),
     'attendance', JSON_ARRAY('view'),
     'marks', JSON_ARRAY('view'),
     'fees', JSON_ARRAY('view'),
     'timetable', JSON_ARRAY('view'),
     'notices', JSON_ARRAY('view'),
     'library', JSON_ARRAY('view')
 )),

-- Parent/Guardian (for parent portal)
(UUID(), 'parent', 'Parent/Guardian', 'Parent portal access', 5, FALSE, TRUE, TRUE,
 JSON_OBJECT(
     'dashboard', JSON_ARRAY('view'),
     'attendance', JSON_ARRAY('view'),
     'marks', JSON_ARRAY('view'),
     'fees', JSON_ARRAY('view'),
     'notices', JSON_ARRAY('view')
 ))

ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ============================================================================
-- 6. SEED SYSTEM SETTINGS
-- ============================================================================
INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description, is_required, is_public, is_editable) VALUES

-- Application Settings
('app_name', 'EduManage Pro', 'string', 'application', 'Application name', TRUE, TRUE, TRUE),
('app_tagline', 'Complete School Management Solution', 'string', 'application', 'Application tagline', FALSE, TRUE, TRUE),
('app_logo', NULL, 'string', 'application', 'Application logo URL', FALSE, TRUE, TRUE),
('app_favicon', NULL, 'string', 'application', 'Application favicon URL', FALSE, TRUE, TRUE),
('app_version', '2.0.0', 'string', 'application', 'Application version', FALSE, TRUE, FALSE),

-- Security Settings
('password_min_length', '8', 'number', 'security', 'Minimum password length', TRUE, FALSE, TRUE),
('password_require_uppercase', 'true', 'boolean', 'security', 'Require uppercase in password', FALSE, FALSE, TRUE),
('password_require_number', 'true', 'boolean', 'security', 'Require number in password', FALSE, FALSE, TRUE),
('password_require_special', 'false', 'boolean', 'security', 'Require special character in password', FALSE, FALSE, TRUE),
('session_timeout_minutes', '60', 'number', 'security', 'Session timeout in minutes', TRUE, FALSE, TRUE),
('max_login_attempts', '5', 'number', 'security', 'Maximum failed login attempts before lockout', TRUE, FALSE, TRUE),
('lockout_duration_minutes', '15', 'number', 'security', 'Account lockout duration in minutes', TRUE, FALSE, TRUE),
('two_factor_enabled', 'false', 'boolean', 'security', 'Enable two-factor authentication globally', FALSE, FALSE, TRUE),

-- Email Settings
('smtp_host', NULL, 'string', 'email', 'SMTP server host', FALSE, FALSE, TRUE),
('smtp_port', '587', 'number', 'email', 'SMTP server port', FALSE, FALSE, TRUE),
('smtp_username', NULL, 'string', 'email', 'SMTP username', FALSE, FALSE, TRUE),
('smtp_password', NULL, 'encrypted', 'email', 'SMTP password', FALSE, FALSE, TRUE),
('smtp_encryption', 'tls', 'string', 'email', 'SMTP encryption (tls/ssl)', FALSE, FALSE, TRUE),
('mail_from_address', NULL, 'string', 'email', 'Default from email address', FALSE, FALSE, TRUE),
('mail_from_name', 'EduManage Pro', 'string', 'email', 'Default from name', FALSE, FALSE, TRUE),

-- Storage Settings
('storage_driver', 'local', 'string', 'storage', 'Storage driver (local/s3/gcs)', TRUE, FALSE, TRUE),
('max_upload_size_mb', '10', 'number', 'storage', 'Maximum file upload size in MB', TRUE, FALSE, TRUE),
('allowed_file_types', '["jpg","jpeg","png","gif","pdf","doc","docx","xls","xlsx"]', 'json', 'storage', 'Allowed file extensions', TRUE, FALSE, TRUE),

-- Notification Settings
('notification_email_enabled', 'true', 'boolean', 'notifications', 'Enable email notifications', FALSE, FALSE, TRUE),
('notification_sms_enabled', 'false', 'boolean', 'notifications', 'Enable SMS notifications', FALSE, FALSE, TRUE),
('notification_push_enabled', 'false', 'boolean', 'notifications', 'Enable push notifications', FALSE, FALSE, TRUE),

-- Regional Settings
('default_timezone', 'Asia/Kolkata', 'string', 'regional', 'Default timezone', TRUE, FALSE, TRUE),
('default_date_format', 'DD/MM/YYYY', 'string', 'regional', 'Default date format', TRUE, FALSE, TRUE),
('default_currency', 'INR', 'string', 'regional', 'Default currency code', TRUE, FALSE, TRUE),
('default_language', 'en', 'string', 'regional', 'Default language', TRUE, FALSE, TRUE),

-- Maintenance
('maintenance_mode', 'false', 'boolean', 'maintenance', 'Enable maintenance mode', FALSE, FALSE, TRUE),
('maintenance_message', 'System is under maintenance. Please try again later.', 'string', 'maintenance', 'Maintenance mode message', FALSE, FALSE, TRUE)

ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ============================================================================
-- 7. SEED DEFAULT SYSTEM ADMIN
-- ============================================================================
INSERT INTO system_admins (uuid, admin_code, name, email, password, role, status) VALUES
(UUID(), 'SADM001', 'System Administrator', 'admin@edumanage.pro', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);
-- Note: Default password is 'password' - MUST be changed on first login

-- ============================================================================
-- END OF SEEDER 001
-- ============================================================================
