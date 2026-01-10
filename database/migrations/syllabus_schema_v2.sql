-- ============================================
-- Syllabus Management Module - Enhanced Schema v2
-- EduManage Pro - School Management System
-- ============================================

-- Add new columns to syllabus table
ALTER TABLE syllabus
    ADD COLUMN section VARCHAR(10) DEFAULT NULL AFTER class,
    ADD COLUMN board_type ENUM('CBSE', 'ICSE', 'State Board', 'Other') DEFAULT 'CBSE' AFTER academic_year,
    ADD COLUMN medium ENUM('English', 'Hindi', 'Tamil', 'Telugu', 'Other') DEFAULT 'English' AFTER board_type,
    ADD COLUMN total_marks INT DEFAULT NULL AFTER medium,
    ADD COLUMN exam_pattern TEXT DEFAULT NULL AFTER total_marks,
    ADD COLUMN version INT DEFAULT 1 AFTER exam_pattern,
    ADD COLUMN updated_by VARCHAR(50) DEFAULT NULL AFTER created_by,
    ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER status,
    ADD INDEX idx_section (section),
    ADD INDEX idx_board_type (board_type),
    ADD INDEX idx_is_deleted (is_deleted);

-- Add new columns to chapters table
ALTER TABLE syllabus_chapters
    ADD COLUMN topics_covered TEXT DEFAULT NULL AFTER description,
    ADD COLUMN marks_weightage INT DEFAULT NULL AFTER estimated_hours,
    ADD COLUMN term ENUM('Term-1', 'Term-2', 'Full Year') DEFAULT 'Full Year' AFTER marks_weightage,
    ADD COLUMN status ENUM('Active', 'Inactive') DEFAULT 'Active' AFTER term,
    ADD INDEX idx_term (term),
    ADD INDEX idx_chapter_status (status);

-- Syllabus files table for document uploads
CREATE TABLE IF NOT EXISTS syllabus_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    syllabus_id VARCHAR(50) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_by VARCHAR(50),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0,
    FOREIGN KEY (syllabus_id) REFERENCES syllabus(id) ON DELETE CASCADE,
    INDEX idx_syllabus_file (syllabus_id),
    INDEX idx_file_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Syllabus versions table for version tracking
CREATE TABLE IF NOT EXISTS syllabus_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    syllabus_id VARCHAR(50) NOT NULL,
    version INT NOT NULL,
    data JSON NOT NULL,
    changed_by VARCHAR(50),
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    change_summary TEXT,
    FOREIGN KEY (syllabus_id) REFERENCES syllabus(id) ON DELETE CASCADE,
    INDEX idx_syllabus_version (syllabus_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Syllabus activity logs table
CREATE TABLE IF NOT EXISTS syllabus_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    syllabus_id VARCHAR(50) NOT NULL,
    action ENUM('CREATE', 'UPDATE', 'DELETE', 'PUBLISH', 'ARCHIVE', 'UPLOAD', 'DOWNLOAD') NOT NULL,
    user_id VARCHAR(50),
    user_name VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (syllabus_id) REFERENCES syllabus(id) ON DELETE CASCADE,
    INDEX idx_syllabus_log (syllabus_id),
    INDEX idx_action (action),
    INDEX idx_log_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher syllabus assignments (for role-based access)
CREATE TABLE IF NOT EXISTS syllabus_teacher_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    syllabus_id VARCHAR(50) NOT NULL,
    teacher_id VARCHAR(50) NOT NULL,
    can_edit TINYINT(1) DEFAULT 0,
    assigned_by VARCHAR(50),
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (syllabus_id) REFERENCES syllabus(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (syllabus_id, teacher_id),
    INDEX idx_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
