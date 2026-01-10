-- ============================================
-- Syllabus Management Module - Database Schema
-- EduManage Pro - School Management System
-- ============================================

-- Syllabus main table
CREATE TABLE IF NOT EXISTS syllabus (
    id VARCHAR(50) PRIMARY KEY,
    class VARCHAR(50) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('Draft', 'Active', 'Archived') DEFAULT 'Draft',
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_syllabus (class, subject, academic_year),
    INDEX idx_class (class),
    INDEX idx_subject (subject),
    INDEX idx_status (status),
    INDEX idx_academic_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chapters table
CREATE TABLE IF NOT EXISTS syllabus_chapters (
    id VARCHAR(50) PRIMARY KEY,
    syllabus_id VARCHAR(50) NOT NULL,
    chapter_no INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    estimated_hours DECIMAL(4,1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (syllabus_id) REFERENCES syllabus(id) ON DELETE CASCADE,
    UNIQUE KEY unique_chapter (syllabus_id, chapter_no),
    INDEX idx_syllabus (syllabus_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topics table
CREATE TABLE IF NOT EXISTS syllabus_topics (
    id VARCHAR(50) PRIMARY KEY,
    chapter_id VARCHAR(50) NOT NULL,
    topic_no INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    learning_objectives TEXT,
    estimated_minutes INT DEFAULT 45,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chapter_id) REFERENCES syllabus_chapters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_topic (chapter_id, topic_no),
    INDEX idx_chapter (chapter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progress tracking table
CREATE TABLE IF NOT EXISTS syllabus_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id VARCHAR(50) NOT NULL,
    class VARCHAR(50) NOT NULL,
    section VARCHAR(10) NOT NULL,
    is_completed TINYINT(1) DEFAULT 0,
    completed_at DATETIME,
    completed_by VARCHAR(50),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_progress (topic_id, class, section),
    FOREIGN KEY (topic_id) REFERENCES syllabus_topics(id) ON DELETE CASCADE,
    INDEX idx_topic (topic_id),
    INDEX idx_class_section (class, section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
