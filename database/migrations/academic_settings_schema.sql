-- ============================================================================
-- Academic Settings Schema Migration
-- EduManage Pro - School Management System
--
-- This migration creates the centralized master data tables for:
-- - Classes (academic_classes)
-- - Sections (academic_sections) - dependent on classes
-- - Subjects (academic_subjects)
-- - Class-Subject mapping (academic_class_subjects)
--
-- Run this migration on the remote Hostinger database
-- ============================================================================

-- Disable foreign key checks during migration
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. ACADEMIC CLASSES TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS `academic_classes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `uuid` CHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
    `class_name` VARCHAR(50) NOT NULL COMMENT 'Display name: Class 1, LKG, Grade 10',
    `numeric_value` INT NOT NULL DEFAULT 0 COMMENT 'Sorting order: LKG=-1, UKG=0, Class 1=1, etc.',
    `description` TEXT NULL,
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `display_order` INT DEFAULT 0,
    `school_id` CHAR(36) NULL COMMENT 'FK to schools for multi-tenant',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` VARCHAR(50) NULL,

    -- Indexes for performance
    INDEX `idx_school_id` (`school_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_numeric_value` (`numeric_value`),
    INDEX `idx_display_order` (`display_order`),

    -- Unique class name per school
    UNIQUE KEY `unique_class_per_school` (`school_id`, `class_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Master table for academic classes';

-- ============================================================================
-- 2. ACADEMIC SECTIONS TABLE (DEPENDENT ON CLASSES)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `academic_sections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `uuid` CHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
    `class_id` INT NOT NULL COMMENT 'FK to academic_classes - KEY RELATIONSHIP',
    `section_name` VARCHAR(10) NOT NULL COMMENT 'Section identifier: A, B, C, etc.',
    `capacity` INT DEFAULT 40 COMMENT 'Maximum students in section',
    `class_teacher_id` VARCHAR(50) NULL COMMENT 'FK to teachers table',
    `room_number` VARCHAR(20) NULL,
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `display_order` INT DEFAULT 0,
    `school_id` CHAR(36) NULL COMMENT 'Denormalized for query efficiency',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` VARCHAR(50) NULL,

    -- Indexes
    INDEX `idx_class_id` (`class_id`),
    INDEX `idx_school_id` (`school_id`),
    INDEX `idx_class_teacher` (`class_teacher_id`),
    INDEX `idx_status` (`status`),

    -- Unique section per class
    UNIQUE KEY `unique_section_per_class` (`class_id`, `section_name`),

    -- Foreign key to academic_classes (CASCADE DELETE)
    CONSTRAINT `fk_section_class` FOREIGN KEY (`class_id`)
        REFERENCES `academic_classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sections table - dependent on academic_classes';

-- ============================================================================
-- 3. ACADEMIC SUBJECTS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS `academic_subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `uuid` CHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
    `subject_code` VARCHAR(20) NOT NULL COMMENT 'Unique code: MATH, ENG101, PHY',
    `subject_name` VARCHAR(100) NOT NULL COMMENT 'Full name: Mathematics, English',
    `short_name` VARCHAR(20) NULL COMMENT 'Short display: Math, Eng',
    `subject_type` ENUM('Theory', 'Practical', 'Lab', 'Elective', 'Core', 'Activity') NOT NULL DEFAULT 'Theory',
    `description` TEXT NULL,
    `max_marks` INT DEFAULT 100,
    `pass_marks` INT DEFAULT 33,
    `credit_hours` DECIMAL(3,1) NULL,
    `color_code` VARCHAR(7) NULL COMMENT 'Hex color for timetable display',
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `display_order` INT DEFAULT 0,
    `school_id` CHAR(36) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` VARCHAR(50) NULL,

    -- Indexes
    INDEX `idx_school_id` (`school_id`),
    INDEX `idx_subject_code` (`subject_code`),
    INDEX `idx_status` (`status`),
    INDEX `idx_subject_type` (`subject_type`),

    -- Unique code per school
    UNIQUE KEY `unique_subject_code_per_school` (`school_id`, `subject_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Master table for academic subjects';

-- ============================================================================
-- 4. ACADEMIC CLASS-SUBJECTS MAPPING (MANY-TO-MANY)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `academic_class_subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `uuid` CHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
    `class_id` INT NOT NULL COMMENT 'FK to academic_classes',
    `subject_id` INT NOT NULL COMMENT 'FK to academic_subjects',
    `is_mandatory` TINYINT(1) DEFAULT 1 COMMENT '1=Compulsory, 0=Optional',
    `weekly_periods` INT DEFAULT 5 COMMENT 'Periods per week for timetable',
    `assigned_teacher_id` VARCHAR(50) NULL COMMENT 'Default teacher for this class-subject',
    `school_id` CHAR(36) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX `idx_class_id` (`class_id`),
    INDEX `idx_subject_id` (`subject_id`),
    INDEX `idx_school_id` (`school_id`),
    INDEX `idx_teacher` (`assigned_teacher_id`),

    -- Unique mapping per class-subject
    UNIQUE KEY `unique_class_subject` (`class_id`, `subject_id`),

    -- Foreign keys
    CONSTRAINT `fk_cs_class` FOREIGN KEY (`class_id`)
        REFERENCES `academic_classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cs_subject` FOREIGN KEY (`subject_id`)
        REFERENCES `academic_subjects`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Maps subjects to classes with metadata';

-- ============================================================================
-- 5. VIEWS FOR EASY QUERYING
-- ============================================================================

-- View: Classes with section count and student count
CREATE OR REPLACE VIEW `vw_classes_summary` AS
SELECT
    c.id,
    c.uuid,
    c.class_name,
    c.numeric_value,
    c.status,
    c.display_order,
    c.school_id,
    COUNT(DISTINCT s.id) AS section_count,
    COALESCE(SUM(
        (SELECT COUNT(*) FROM students st
         WHERE st.class = c.class_name
         AND st.section = s.section_name
         AND COALESCE(st.school_id, '') = COALESCE(c.school_id, ''))
    ), 0) AS total_students
FROM academic_classes c
LEFT JOIN academic_sections s ON s.class_id = c.id
WHERE c.status = 'Active'
GROUP BY c.id, c.uuid, c.class_name, c.numeric_value, c.status, c.display_order, c.school_id
ORDER BY c.numeric_value, c.display_order;

-- View: Sections with student count
CREATE OR REPLACE VIEW `vw_sections_with_count` AS
SELECT
    s.id,
    s.uuid,
    s.class_id,
    c.class_name,
    s.section_name,
    s.capacity,
    s.class_teacher_id,
    t.name AS class_teacher_name,
    s.room_number,
    s.status,
    s.school_id,
    (SELECT COUNT(*) FROM students st
     WHERE st.class = c.class_name
     AND st.section = s.section_name
     AND COALESCE(st.school_id, '') = COALESCE(s.school_id, '')) AS student_count
FROM academic_sections s
INNER JOIN academic_classes c ON c.id = s.class_id
LEFT JOIN teachers t ON t.id = s.class_teacher_id
WHERE s.status = 'Active'
ORDER BY c.numeric_value, s.display_order;

-- View: Subjects with assigned classes
CREATE OR REPLACE VIEW `vw_subjects_with_classes` AS
SELECT
    sub.id,
    sub.uuid,
    sub.subject_code,
    sub.subject_name,
    sub.short_name,
    sub.subject_type,
    sub.max_marks,
    sub.pass_marks,
    sub.status,
    sub.school_id,
    GROUP_CONCAT(DISTINCT c.class_name ORDER BY c.numeric_value SEPARATOR ', ') AS assigned_classes,
    GROUP_CONCAT(DISTINCT cs.class_id ORDER BY c.numeric_value) AS class_ids
FROM academic_subjects sub
LEFT JOIN academic_class_subjects cs ON cs.subject_id = sub.id
LEFT JOIN academic_classes c ON c.id = cs.class_id
WHERE sub.status = 'Active'
GROUP BY sub.id, sub.uuid, sub.subject_code, sub.subject_name, sub.short_name,
         sub.subject_type, sub.max_marks, sub.pass_marks, sub.status, sub.school_id
ORDER BY sub.display_order, sub.subject_name;

-- ============================================================================
-- 6. SEED DEFAULT DATA
-- ============================================================================

-- Insert default classes (Class 1 to Class 12 + Pre-primary)
INSERT INTO `academic_classes` (`class_name`, `numeric_value`, `display_order`, `status`) VALUES
('Nursery', -2, 1, 'Active'),
('LKG', -1, 2, 'Active'),
('UKG', 0, 3, 'Active'),
('Class 1', 1, 4, 'Active'),
('Class 2', 2, 5, 'Active'),
('Class 3', 3, 6, 'Active'),
('Class 4', 4, 7, 'Active'),
('Class 5', 5, 8, 'Active'),
('Class 6', 6, 9, 'Active'),
('Class 7', 7, 10, 'Active'),
('Class 8', 8, 11, 'Active'),
('Class 9', 9, 12, 'Active'),
('Class 10', 10, 13, 'Active'),
('Class 11', 11, 14, 'Active'),
('Class 12', 12, 15, 'Active')
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Insert default sections for each class
INSERT INTO `academic_sections` (`class_id`, `section_name`, `capacity`, `display_order`, `status`)
SELECT
    c.id,
    sec.section_name,
    40,
    sec.display_order,
    'Active'
FROM academic_classes c
CROSS JOIN (
    SELECT 'A' AS section_name, 1 AS display_order UNION ALL
    SELECT 'B', 2 UNION ALL
    SELECT 'C', 3
) sec
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Insert default subjects
INSERT INTO `academic_subjects` (`subject_code`, `subject_name`, `short_name`, `subject_type`, `max_marks`, `pass_marks`, `color_code`, `display_order`, `status`) VALUES
('MATH', 'Mathematics', 'Math', 'Core', 100, 33, '#3B82F6', 1, 'Active'),
('ENG', 'English', 'Eng', 'Core', 100, 33, '#10B981', 2, 'Active'),
('HIN', 'Hindi', 'Hindi', 'Core', 100, 33, '#F59E0B', 3, 'Active'),
('SCI', 'Science', 'Sci', 'Core', 100, 33, '#8B5CF6', 4, 'Active'),
('SST', 'Social Studies', 'SST', 'Core', 100, 33, '#EF4444', 5, 'Active'),
('PHY', 'Physics', 'Phy', 'Theory', 100, 33, '#06B6D4', 6, 'Active'),
('CHEM', 'Chemistry', 'Chem', 'Theory', 100, 33, '#EC4899', 7, 'Active'),
('BIO', 'Biology', 'Bio', 'Theory', 100, 33, '#22C55E', 8, 'Active'),
('CS', 'Computer Science', 'CS', 'Theory', 100, 33, '#6366F1', 9, 'Active'),
('PE', 'Physical Education', 'PE', 'Activity', 100, 33, '#F97316', 10, 'Active'),
('ART', 'Art & Craft', 'Art', 'Activity', 100, 33, '#A855F7', 11, 'Active'),
('MUS', 'Music', 'Music', 'Activity', 100, 33, '#14B8A6', 12, 'Active'),
('EVS', 'Environmental Studies', 'EVS', 'Core', 100, 33, '#84CC16', 13, 'Active'),
('GK', 'General Knowledge', 'GK', 'Core', 100, 33, '#0EA5E9', 14, 'Active')
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Assign subjects to classes (Primary: 1-5)
INSERT INTO `academic_class_subjects` (`class_id`, `subject_id`, `is_mandatory`, `weekly_periods`)
SELECT c.id, s.id, 1,
    CASE
        WHEN s.subject_code IN ('MATH', 'ENG', 'HIN') THEN 6
        WHEN s.subject_code IN ('EVS', 'GK') THEN 4
        WHEN s.subject_code IN ('PE', 'ART', 'MUS') THEN 2
        ELSE 5
    END
FROM academic_classes c
CROSS JOIN academic_subjects s
WHERE c.numeric_value BETWEEN 1 AND 5
AND s.subject_code IN ('MATH', 'ENG', 'HIN', 'EVS', 'GK', 'PE', 'ART', 'MUS')
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Assign subjects to classes (Middle: 6-8)
INSERT INTO `academic_class_subjects` (`class_id`, `subject_id`, `is_mandatory`, `weekly_periods`)
SELECT c.id, s.id, 1,
    CASE
        WHEN s.subject_code IN ('MATH', 'ENG', 'HIN', 'SCI', 'SST') THEN 6
        WHEN s.subject_code IN ('CS') THEN 3
        WHEN s.subject_code IN ('PE', 'ART') THEN 2
        ELSE 5
    END
FROM academic_classes c
CROSS JOIN academic_subjects s
WHERE c.numeric_value BETWEEN 6 AND 8
AND s.subject_code IN ('MATH', 'ENG', 'HIN', 'SCI', 'SST', 'CS', 'PE', 'ART')
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Assign subjects to classes (Secondary: 9-10)
INSERT INTO `academic_class_subjects` (`class_id`, `subject_id`, `is_mandatory`, `weekly_periods`)
SELECT c.id, s.id, 1,
    CASE
        WHEN s.subject_code IN ('MATH', 'ENG', 'SCI', 'SST') THEN 6
        WHEN s.subject_code = 'HIN' THEN 5
        WHEN s.subject_code = 'CS' THEN 3
        ELSE 4
    END
FROM academic_classes c
CROSS JOIN academic_subjects s
WHERE c.numeric_value BETWEEN 9 AND 10
AND s.subject_code IN ('MATH', 'ENG', 'HIN', 'SCI', 'SST', 'CS', 'PE')
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Assign subjects to classes (Senior Secondary: 11-12)
INSERT INTO `academic_class_subjects` (`class_id`, `subject_id`, `is_mandatory`, `weekly_periods`)
SELECT c.id, s.id,
    CASE WHEN s.subject_code IN ('ENG', 'PE') THEN 1 ELSE 0 END,
    CASE
        WHEN s.subject_code IN ('PHY', 'CHEM', 'MATH', 'BIO', 'CS') THEN 7
        WHEN s.subject_code = 'ENG' THEN 5
        ELSE 3
    END
FROM academic_classes c
CROSS JOIN academic_subjects s
WHERE c.numeric_value BETWEEN 11 AND 12
AND s.subject_code IN ('ENG', 'PHY', 'CHEM', 'MATH', 'BIO', 'CS', 'PE')
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
SELECT 'Academic Settings Schema Migration Complete!' AS status;
SELECT COUNT(*) AS total_classes FROM academic_classes;
SELECT COUNT(*) AS total_sections FROM academic_sections;
SELECT COUNT(*) AS total_subjects FROM academic_subjects;
SELECT COUNT(*) AS total_class_subjects FROM academic_class_subjects;
