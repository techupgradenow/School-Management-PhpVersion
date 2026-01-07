-- ============================================================================
-- COMPLETE END-TO-END DROPDOWN MIGRATION WITH DEFAULT VALUES
-- ============================================================================
-- This script performs complete migration including:
-- 1. Schema updates (dropdown_id support)
-- 2. All default dropdown categories
-- 3. All default dropdown values
-- 4. Views and stored procedures
-- 5. Data verification
-- ============================================================================

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

-- ============================================================================
-- STEP 1: ENSURE TABLES EXIST
-- ============================================================================

-- Create institution_types table if not exists
CREATE TABLE IF NOT EXISTS institution_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default institution types
INSERT IGNORE INTO institution_types (id, name) VALUES
(1, 'School'),
(2, 'College'),
(3, 'University');

-- Create institution_settings table if not exists
CREATE TABLE IF NOT EXISTS institution_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default institution type setting
INSERT INTO institution_settings (setting_key, setting_value)
VALUES ('institution_type', 'School')
ON DUPLICATE KEY UPDATE setting_value = 'School';

-- Create dropdown_categories table if not exists
CREATE TABLE IF NOT EXISTS dropdown_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_key VARCHAR(100) NOT NULL,
    category_name VARCHAR(255) NOT NULL,
    institution_type_id INT DEFAULT 1,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_category_institution (category_key, institution_type_id),
    FOREIGN KEY (institution_type_id) REFERENCES institution_types(id)
);

-- Create dropdown_values table if not exists
CREATE TABLE IF NOT EXISTS dropdown_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    value VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    parent_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES dropdown_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES dropdown_values(id) ON DELETE SET NULL
);

-- ============================================================================
-- STEP 2: ADD DROPDOWN_ID COLUMN
-- ============================================================================

-- Add dropdown_id column if not exists
SET @dbname = DATABASE();
SET @tablename = 'dropdown_categories';
SET @columnname = 'dropdown_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT NULL UNIQUE COMMENT ''Unique numeric ID for configurable dynamic dropdowns'' AFTER id')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index if not exists
SET @indexname = 'idx_dropdown_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (index_name = @indexname)) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD INDEX ', @indexname, ' (dropdown_id)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================================================
-- STEP 3: ADD IS_DYNAMIC COMPUTED COLUMN
-- ============================================================================

SET @tablename = 'dropdown_values';
SET @columnname = 'is_dynamic';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' BOOLEAN GENERATED ALWAYS AS ((SELECT dropdown_id FROM dropdown_categories dc WHERE dc.id = dropdown_values.category_id) IS NOT NULL) STORED COMMENT ''Auto-computed: TRUE if category has dropdown_id''')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index on is_dynamic
SET @indexname = 'idx_is_dynamic';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (index_name = @indexname)) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD INDEX ', @indexname, ' (is_dynamic)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================================================
-- STEP 4: INSERT ALL DROPDOWN CATEGORIES WITH DROPDOWN IDS
-- ============================================================================

-- DYNAMIC DROPDOWNS (with dropdown_id)
INSERT INTO dropdown_categories (dropdown_id, category_key, category_name, institution_type_id, display_order, is_active)
VALUES
-- ID 455: User Roles/Relations
(455, 'user_role', 'User Role', 1, 10, 1),
(455, 'relation', 'Relation', 1, 11, 1),

-- ID 456: Academic Departments
(456, 'department', 'Department', 1, 20, 1),

-- ID 457: Designations
(457, 'designation', 'Designation', 1, 30, 1),

-- ID 458: Blood Groups
(458, 'blood_group', 'Blood Group', 1, 40, 1),

-- ID 459: Religions
(459, 'religion', 'Religion', 1, 50, 1),

-- ID 460: Castes/Categories
(460, 'caste', 'Caste/Category', 1, 60, 1),

-- ID 461: Languages
(461, 'language', 'Language', 1, 70, 1),

-- ID 462: Nationalities
(462, 'nationality', 'Nationality', 1, 80, 1),

-- ID 463: Marital Status
(463, 'marital_status', 'Marital Status', 1, 90, 1),

-- ID 464: Employment Types
(464, 'employment_type', 'Employment Type', 1, 100, 1),

-- ID 465: Qualifications
(465, 'qualification', 'Qualification', 1, 110, 1),

-- ID 466: Fee Categories
(466, 'fee_category', 'Fee Category', 1, 120, 1),

-- ID 467: Payment Methods
(467, 'payment_method', 'Payment Method', 1, 130, 1),

-- ID 468: Document Types
(468, 'document_type', 'Document Type', 1, 140, 1),

-- ID 469: Leave Types
(469, 'leave_type', 'Leave Type', 1, 150, 1),

-- ID 470: Exam Types
(470, 'exam_type', 'Exam Type', 1, 160, 1)

ON DUPLICATE KEY UPDATE
    dropdown_id = VALUES(dropdown_id),
    display_order = VALUES(display_order),
    is_active = VALUES(is_active);

-- HARDCODED DROPDOWNS (dropdown_id = NULL - managed in code)
INSERT INTO dropdown_categories (dropdown_id, category_key, category_name, institution_type_id, display_order, is_active)
VALUES
(NULL, 'gender', 'Gender', 1, 1, 1),
(NULL, 'subject', 'Subject', 1, 2, 1),
(NULL, 'class', 'Class', 1, 3, 1),
(NULL, 'section', 'Section', 1, 4, 1),
(NULL, 'status', 'Status', 1, 5, 1)
ON DUPLICATE KEY UPDATE
    display_order = VALUES(display_order),
    is_active = VALUES(is_active);

-- ============================================================================
-- STEP 5: INSERT ALL DEFAULT VALUES FOR DYNAMIC DROPDOWNS
-- ============================================================================

-- Get category IDs for each dropdown_id
SET @cat_455 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 455 LIMIT 1);
SET @cat_456 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 456 LIMIT 1);
SET @cat_457 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 457 LIMIT 1);
SET @cat_458 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 458 LIMIT 1);
SET @cat_459 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 459 LIMIT 1);
SET @cat_460 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 460 LIMIT 1);
SET @cat_461 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 461 LIMIT 1);
SET @cat_462 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 462 LIMIT 1);
SET @cat_463 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 463 LIMIT 1);
SET @cat_464 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 464 LIMIT 1);
SET @cat_465 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 465 LIMIT 1);
SET @cat_466 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 466 LIMIT 1);
SET @cat_467 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 467 LIMIT 1);
SET @cat_468 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 468 LIMIT 1);
SET @cat_469 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 469 LIMIT 1);
SET @cat_470 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 470 LIMIT 1);

-- Dropdown ID 455: User Roles/Relations
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_455, 'Teacher', 1, 1 WHERE @cat_455 IS NOT NULL
UNION ALL SELECT @cat_455, 'Parent', 2, 1 WHERE @cat_455 IS NOT NULL
UNION ALL SELECT @cat_455, 'Guardian', 3, 1 WHERE @cat_455 IS NOT NULL
UNION ALL SELECT @cat_455, 'Student', 4, 1 WHERE @cat_455 IS NOT NULL
UNION ALL SELECT @cat_455, 'Father', 5, 1 WHERE @cat_455 IS NOT NULL
UNION ALL SELECT @cat_455, 'Mother', 6, 1 WHERE @cat_455 IS NOT NULL
UNION ALL SELECT @cat_455, 'Sibling', 7, 1 WHERE @cat_455 IS NOT NULL
UNION ALL SELECT @cat_455, 'Grandparent', 8, 1 WHERE @cat_455 IS NOT NULL
UNION ALL SELECT @cat_455, 'Uncle/Aunt', 9, 1 WHERE @cat_455 IS NOT NULL
UNION ALL SELECT @cat_455, 'Legal Guardian', 10, 1 WHERE @cat_455 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 456: Departments
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_456, 'Science', 1, 1 WHERE @cat_456 IS NOT NULL
UNION ALL SELECT @cat_456, 'Mathematics', 2, 1 WHERE @cat_456 IS NOT NULL
UNION ALL SELECT @cat_456, 'English', 3, 1 WHERE @cat_456 IS NOT NULL
UNION ALL SELECT @cat_456, 'Hindi', 4, 1 WHERE @cat_456 IS NOT NULL
UNION ALL SELECT @cat_456, 'Social Science', 5, 1 WHERE @cat_456 IS NOT NULL
UNION ALL SELECT @cat_456, 'Commerce', 6, 1 WHERE @cat_456 IS NOT NULL
UNION ALL SELECT @cat_456, 'Arts', 7, 1 WHERE @cat_456 IS NOT NULL
UNION ALL SELECT @cat_456, 'Computer Science', 8, 1 WHERE @cat_456 IS NOT NULL
UNION ALL SELECT @cat_456, 'Physical Education', 9, 1 WHERE @cat_456 IS NOT NULL
UNION ALL SELECT @cat_456, 'Music & Arts', 10, 1 WHERE @cat_456 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 457: Designations
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_457, 'Principal', 1, 1 WHERE @cat_457 IS NOT NULL
UNION ALL SELECT @cat_457, 'Vice Principal', 2, 1 WHERE @cat_457 IS NOT NULL
UNION ALL SELECT @cat_457, 'Head of Department', 3, 1 WHERE @cat_457 IS NOT NULL
UNION ALL SELECT @cat_457, 'Senior Teacher', 4, 1 WHERE @cat_457 IS NOT NULL
UNION ALL SELECT @cat_457, 'Teacher', 5, 1 WHERE @cat_457 IS NOT NULL
UNION ALL SELECT @cat_457, 'Assistant Teacher', 6, 1 WHERE @cat_457 IS NOT NULL
UNION ALL SELECT @cat_457, 'Lab Assistant', 7, 1 WHERE @cat_457 IS NOT NULL
UNION ALL SELECT @cat_457, 'Librarian', 8, 1 WHERE @cat_457 IS NOT NULL
UNION ALL SELECT @cat_457, 'Sports Instructor', 9, 1 WHERE @cat_457 IS NOT NULL
UNION ALL SELECT @cat_457, 'Counselor', 10, 1 WHERE @cat_457 IS NOT NULL
UNION ALL SELECT @cat_457, 'Administrative Staff', 11, 1 WHERE @cat_457 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 458: Blood Groups
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_458, 'A+', 1, 1 WHERE @cat_458 IS NOT NULL
UNION ALL SELECT @cat_458, 'A-', 2, 1 WHERE @cat_458 IS NOT NULL
UNION ALL SELECT @cat_458, 'B+', 3, 1 WHERE @cat_458 IS NOT NULL
UNION ALL SELECT @cat_458, 'B-', 4, 1 WHERE @cat_458 IS NOT NULL
UNION ALL SELECT @cat_458, 'AB+', 5, 1 WHERE @cat_458 IS NOT NULL
UNION ALL SELECT @cat_458, 'AB-', 6, 1 WHERE @cat_458 IS NOT NULL
UNION ALL SELECT @cat_458, 'O+', 7, 1 WHERE @cat_458 IS NOT NULL
UNION ALL SELECT @cat_458, 'O-', 8, 1 WHERE @cat_458 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 459: Religions
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_459, 'Hinduism', 1, 1 WHERE @cat_459 IS NOT NULL
UNION ALL SELECT @cat_459, 'Islam', 2, 1 WHERE @cat_459 IS NOT NULL
UNION ALL SELECT @cat_459, 'Christianity', 3, 1 WHERE @cat_459 IS NOT NULL
UNION ALL SELECT @cat_459, 'Sikhism', 4, 1 WHERE @cat_459 IS NOT NULL
UNION ALL SELECT @cat_459, 'Buddhism', 5, 1 WHERE @cat_459 IS NOT NULL
UNION ALL SELECT @cat_459, 'Jainism', 6, 1 WHERE @cat_459 IS NOT NULL
UNION ALL SELECT @cat_459, 'Other', 7, 1 WHERE @cat_459 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 460: Castes/Categories
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_460, 'General', 1, 1 WHERE @cat_460 IS NOT NULL
UNION ALL SELECT @cat_460, 'OBC', 2, 1 WHERE @cat_460 IS NOT NULL
UNION ALL SELECT @cat_460, 'SC', 3, 1 WHERE @cat_460 IS NOT NULL
UNION ALL SELECT @cat_460, 'ST', 4, 1 WHERE @cat_460 IS NOT NULL
UNION ALL SELECT @cat_460, 'EWS', 5, 1 WHERE @cat_460 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 461: Languages
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_461, 'English', 1, 1 WHERE @cat_461 IS NOT NULL
UNION ALL SELECT @cat_461, 'Hindi', 2, 1 WHERE @cat_461 IS NOT NULL
UNION ALL SELECT @cat_461, 'Tamil', 3, 1 WHERE @cat_461 IS NOT NULL
UNION ALL SELECT @cat_461, 'Telugu', 4, 1 WHERE @cat_461 IS NOT NULL
UNION ALL SELECT @cat_461, 'Kannada', 5, 1 WHERE @cat_461 IS NOT NULL
UNION ALL SELECT @cat_461, 'Malayalam', 6, 1 WHERE @cat_461 IS NOT NULL
UNION ALL SELECT @cat_461, 'Bengali', 7, 1 WHERE @cat_461 IS NOT NULL
UNION ALL SELECT @cat_461, 'Marathi', 8, 1 WHERE @cat_461 IS NOT NULL
UNION ALL SELECT @cat_461, 'Gujarati', 9, 1 WHERE @cat_461 IS NOT NULL
UNION ALL SELECT @cat_461, 'Punjabi', 10, 1 WHERE @cat_461 IS NOT NULL
UNION ALL SELECT @cat_461, 'Urdu', 11, 1 WHERE @cat_461 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 462: Nationalities
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_462, 'Indian', 1, 1 WHERE @cat_462 IS NOT NULL
UNION ALL SELECT @cat_462, 'American', 2, 1 WHERE @cat_462 IS NOT NULL
UNION ALL SELECT @cat_462, 'British', 3, 1 WHERE @cat_462 IS NOT NULL
UNION ALL SELECT @cat_462, 'Canadian', 4, 1 WHERE @cat_462 IS NOT NULL
UNION ALL SELECT @cat_462, 'Australian', 5, 1 WHERE @cat_462 IS NOT NULL
UNION ALL SELECT @cat_462, 'Other', 6, 1 WHERE @cat_462 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 463: Marital Status
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_463, 'Single', 1, 1 WHERE @cat_463 IS NOT NULL
UNION ALL SELECT @cat_463, 'Married', 2, 1 WHERE @cat_463 IS NOT NULL
UNION ALL SELECT @cat_463, 'Divorced', 3, 1 WHERE @cat_463 IS NOT NULL
UNION ALL SELECT @cat_463, 'Widowed', 4, 1 WHERE @cat_463 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 464: Employment Types
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_464, 'Permanent', 1, 1 WHERE @cat_464 IS NOT NULL
UNION ALL SELECT @cat_464, 'Contract', 2, 1 WHERE @cat_464 IS NOT NULL
UNION ALL SELECT @cat_464, 'Temporary', 3, 1 WHERE @cat_464 IS NOT NULL
UNION ALL SELECT @cat_464, 'Part-Time', 4, 1 WHERE @cat_464 IS NOT NULL
UNION ALL SELECT @cat_464, 'Visiting', 5, 1 WHERE @cat_464 IS NOT NULL
UNION ALL SELECT @cat_464, 'Guest Faculty', 6, 1 WHERE @cat_464 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 465: Qualifications
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_465, 'High School', 1, 1 WHERE @cat_465 IS NOT NULL
UNION ALL SELECT @cat_465, 'Diploma', 2, 1 WHERE @cat_465 IS NOT NULL
UNION ALL SELECT @cat_465, 'Under Graduate (UG)', 3, 1 WHERE @cat_465 IS NOT NULL
UNION ALL SELECT @cat_465, 'Post Graduate (PG)', 4, 1 WHERE @cat_465 IS NOT NULL
UNION ALL SELECT @cat_465, 'M.Phil', 5, 1 WHERE @cat_465 IS NOT NULL
UNION ALL SELECT @cat_465, 'PhD (Doctorate)', 6, 1 WHERE @cat_465 IS NOT NULL
UNION ALL SELECT @cat_465, 'B.Ed', 7, 1 WHERE @cat_465 IS NOT NULL
UNION ALL SELECT @cat_465, 'M.Ed', 8, 1 WHERE @cat_465 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 466: Fee Categories
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_466, 'Tuition Fee', 1, 1 WHERE @cat_466 IS NOT NULL
UNION ALL SELECT @cat_466, 'Admission Fee', 2, 1 WHERE @cat_466 IS NOT NULL
UNION ALL SELECT @cat_466, 'Exam Fee', 3, 1 WHERE @cat_466 IS NOT NULL
UNION ALL SELECT @cat_466, 'Library Fee', 4, 1 WHERE @cat_466 IS NOT NULL
UNION ALL SELECT @cat_466, 'Lab Fee', 5, 1 WHERE @cat_466 IS NOT NULL
UNION ALL SELECT @cat_466, 'Sports Fee', 6, 1 WHERE @cat_466 IS NOT NULL
UNION ALL SELECT @cat_466, 'Transport Fee', 7, 1 WHERE @cat_466 IS NOT NULL
UNION ALL SELECT @cat_466, 'Hostel Fee', 8, 1 WHERE @cat_466 IS NOT NULL
UNION ALL SELECT @cat_466, 'Development Fee', 9, 1 WHERE @cat_466 IS NOT NULL
UNION ALL SELECT @cat_466, 'Late Fee', 10, 1 WHERE @cat_466 IS NOT NULL
UNION ALL SELECT @cat_466, 'Miscellaneous', 11, 1 WHERE @cat_466 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 467: Payment Methods
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_467, 'Cash', 1, 1 WHERE @cat_467 IS NOT NULL
UNION ALL SELECT @cat_467, 'Cheque', 2, 1 WHERE @cat_467 IS NOT NULL
UNION ALL SELECT @cat_467, 'Demand Draft', 3, 1 WHERE @cat_467 IS NOT NULL
UNION ALL SELECT @cat_467, 'Online Transfer', 4, 1 WHERE @cat_467 IS NOT NULL
UNION ALL SELECT @cat_467, 'UPI', 5, 1 WHERE @cat_467 IS NOT NULL
UNION ALL SELECT @cat_467, 'Debit Card', 6, 1 WHERE @cat_467 IS NOT NULL
UNION ALL SELECT @cat_467, 'Credit Card', 7, 1 WHERE @cat_467 IS NOT NULL
UNION ALL SELECT @cat_467, 'Net Banking', 8, 1 WHERE @cat_467 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 468: Document Types
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_468, 'Birth Certificate', 1, 1 WHERE @cat_468 IS NOT NULL
UNION ALL SELECT @cat_468, 'Aadhaar Card', 2, 1 WHERE @cat_468 IS NOT NULL
UNION ALL SELECT @cat_468, 'PAN Card', 3, 1 WHERE @cat_468 IS NOT NULL
UNION ALL SELECT @cat_468, 'Transfer Certificate', 4, 1 WHERE @cat_468 IS NOT NULL
UNION ALL SELECT @cat_468, 'Mark Sheet', 5, 1 WHERE @cat_468 IS NOT NULL
UNION ALL SELECT @cat_468, 'Degree Certificate', 6, 1 WHERE @cat_468 IS NOT NULL
UNION ALL SELECT @cat_468, 'Passport', 7, 1 WHERE @cat_468 IS NOT NULL
UNION ALL SELECT @cat_468, 'Caste Certificate', 8, 1 WHERE @cat_468 IS NOT NULL
UNION ALL SELECT @cat_468, 'Income Certificate', 9, 1 WHERE @cat_468 IS NOT NULL
UNION ALL SELECT @cat_468, 'Medical Certificate', 10, 1 WHERE @cat_468 IS NOT NULL
UNION ALL SELECT @cat_468, 'Photo ID', 11, 1 WHERE @cat_468 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 469: Leave Types
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_469, 'Casual Leave', 1, 1 WHERE @cat_469 IS NOT NULL
UNION ALL SELECT @cat_469, 'Sick Leave', 2, 1 WHERE @cat_469 IS NOT NULL
UNION ALL SELECT @cat_469, 'Earned Leave', 3, 1 WHERE @cat_469 IS NOT NULL
UNION ALL SELECT @cat_469, 'Maternity Leave', 4, 1 WHERE @cat_469 IS NOT NULL
UNION ALL SELECT @cat_469, 'Paternity Leave', 5, 1 WHERE @cat_469 IS NOT NULL
UNION ALL SELECT @cat_469, 'Compensatory Leave', 6, 1 WHERE @cat_469 IS NOT NULL
UNION ALL SELECT @cat_469, 'Study Leave', 7, 1 WHERE @cat_469 IS NOT NULL
UNION ALL SELECT @cat_469, 'Special Leave', 8, 1 WHERE @cat_469 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Dropdown ID 470: Exam Types
INSERT INTO dropdown_values (category_id, value, display_order, is_active)
SELECT @cat_470, 'Unit Test', 1, 1 WHERE @cat_470 IS NOT NULL
UNION ALL SELECT @cat_470, 'Mid Term', 2, 1 WHERE @cat_470 IS NOT NULL
UNION ALL SELECT @cat_470, 'Final Term', 3, 1 WHERE @cat_470 IS NOT NULL
UNION ALL SELECT @cat_470, 'Half Yearly', 4, 1 WHERE @cat_470 IS NOT NULL
UNION ALL SELECT @cat_470, 'Annual', 5, 1 WHERE @cat_470 IS NOT NULL
UNION ALL SELECT @cat_470, 'Quarterly', 6, 1 WHERE @cat_470 IS NOT NULL
UNION ALL SELECT @cat_470, 'Pre-Board', 7, 1 WHERE @cat_470 IS NOT NULL
UNION ALL SELECT @cat_470, 'Board Exam', 8, 1 WHERE @cat_470 IS NOT NULL
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- ============================================================================
-- STEP 6: CREATE VIEWS
-- ============================================================================

-- Drop views if exist
DROP VIEW IF EXISTS vw_dynamic_dropdowns;
DROP VIEW IF EXISTS vw_hardcoded_dropdowns;
DROP VIEW IF EXISTS vw_dropdown_values_with_id;

-- View 1: All Dynamic Dropdowns (those with Dropdown IDs)
CREATE VIEW vw_dynamic_dropdowns AS
SELECT
    dc.dropdown_id,
    dc.id AS category_id,
    dc.category_key,
    dc.category_name,
    dc.institution_type_id,
    it.name AS institution_type,
    COUNT(dv.id) AS value_count,
    dc.display_order,
    dc.is_active
FROM dropdown_categories dc
LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
LEFT JOIN institution_types it ON dc.institution_type_id = it.id
WHERE dc.dropdown_id IS NOT NULL
GROUP BY dc.id, dc.dropdown_id, dc.category_key, dc.category_name,
         dc.institution_type_id, it.name, dc.display_order, dc.is_active
ORDER BY dc.dropdown_id;

-- View 2: All Hardcoded Dropdowns (those WITHOUT Dropdown IDs)
CREATE VIEW vw_hardcoded_dropdowns AS
SELECT
    dc.id AS category_id,
    dc.category_key,
    dc.category_name,
    dc.institution_type_id,
    it.name AS institution_type,
    COUNT(dv.id) AS value_count,
    dc.display_order,
    dc.is_active
FROM dropdown_categories dc
LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
LEFT JOIN institution_types it ON dc.institution_type_id = it.id
WHERE dc.dropdown_id IS NULL
GROUP BY dc.id, dc.category_key, dc.category_name,
         dc.institution_type_id, it.name, dc.display_order, dc.is_active
ORDER BY dc.category_key;

-- View 3: Dropdown Values with Dropdown ID
CREATE VIEW vw_dropdown_values_with_id AS
SELECT
    dc.dropdown_id,
    dc.category_key,
    dc.category_name,
    dv.id AS value_id,
    dv.value,
    dv.display_order,
    dv.is_active,
    dv.is_dynamic,
    dv.created_at,
    dv.updated_at
FROM dropdown_values dv
INNER JOIN dropdown_categories dc ON dv.category_id = dc.id
WHERE dc.dropdown_id IS NOT NULL
ORDER BY dc.dropdown_id, dv.display_order, dv.value;

-- ============================================================================
-- STEP 7: CREATE STORED PROCEDURES
-- ============================================================================

DELIMITER $$

-- Drop procedures if exist
DROP PROCEDURE IF EXISTS sp_get_values_by_dropdown_id$$
DROP PROCEDURE IF EXISTS sp_add_value_by_dropdown_id$$
DROP PROCEDURE IF EXISTS sp_delete_value_by_dropdown_id$$
DROP FUNCTION IF EXISTS fn_is_dynamic_dropdown$$

-- Procedure 1: Get values by Dropdown ID
CREATE PROCEDURE sp_get_values_by_dropdown_id(IN p_dropdown_id INT)
BEGIN
    SELECT
        dv.id,
        dv.value,
        dv.display_order,
        dv.is_active,
        dc.category_key,
        dc.category_name
    FROM dropdown_values dv
    INNER JOIN dropdown_categories dc ON dv.category_id = dc.id
    WHERE dc.dropdown_id = p_dropdown_id
    AND dv.is_active = 1
    ORDER BY dv.display_order, dv.value;
END$$

-- Procedure 2: Add value to dropdown by Dropdown ID
CREATE PROCEDURE sp_add_value_by_dropdown_id(
    IN p_dropdown_id INT,
    IN p_value VARCHAR(255),
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(500),
    OUT p_value_id INT
)
BEGIN
    DECLARE v_category_id INT;
    DECLARE v_exists INT;
    DECLARE v_max_order INT;

    SET p_success = FALSE;
    SET p_message = '';
    SET p_value_id = NULL;

    SELECT id INTO v_category_id
    FROM dropdown_categories
    WHERE dropdown_id = p_dropdown_id
    LIMIT 1;

    IF v_category_id IS NULL THEN
        SET p_message = CONCAT('Dropdown ID ', p_dropdown_id, ' does not exist');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_message;
    END IF;

    SELECT COUNT(*) INTO v_exists
    FROM dropdown_values
    WHERE category_id = v_category_id
    AND LOWER(TRIM(value)) = LOWER(TRIM(p_value));

    IF v_exists > 0 THEN
        SET p_message = 'Value already exists in this dropdown';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_message;
    END IF;

    SELECT COALESCE(MAX(display_order), 0) + 1 INTO v_max_order
    FROM dropdown_values
    WHERE category_id = v_category_id;

    INSERT INTO dropdown_values (category_id, value, display_order, is_active)
    VALUES (v_category_id, TRIM(p_value), v_max_order, 1);

    SET p_value_id = LAST_INSERT_ID();
    SET p_success = TRUE;
    SET p_message = 'Value added successfully';
END$$

-- Procedure 3: Delete value from dropdown by Dropdown ID
CREATE PROCEDURE sp_delete_value_by_dropdown_id(
    IN p_dropdown_id INT,
    IN p_value VARCHAR(255),
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(500)
)
BEGIN
    DECLARE v_category_id INT;
    DECLARE v_value_id INT;

    SET p_success = FALSE;
    SET p_message = '';

    SELECT id INTO v_category_id
    FROM dropdown_categories
    WHERE dropdown_id = p_dropdown_id
    LIMIT 1;

    IF v_category_id IS NULL THEN
        SET p_message = CONCAT('Dropdown ID ', p_dropdown_id, ' does not exist');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_message;
    END IF;

    SELECT id INTO v_value_id
    FROM dropdown_values
    WHERE category_id = v_category_id
    AND value = p_value
    LIMIT 1;

    IF v_value_id IS NULL THEN
        SET p_message = 'Value not found';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_message;
    END IF;

    UPDATE dropdown_values
    SET is_active = 0
    WHERE id = v_value_id;

    SET p_success = TRUE;
    SET p_message = 'Value deleted successfully';
END$$

-- Function: Check if dropdown is dynamic
CREATE FUNCTION fn_is_dynamic_dropdown(p_category_key VARCHAR(100))
RETURNS BOOLEAN
DETERMINISTIC
BEGIN
    DECLARE v_dropdown_id INT;

    SELECT dropdown_id INTO v_dropdown_id
    FROM dropdown_categories
    WHERE category_key = p_category_key
    LIMIT 1;

    RETURN v_dropdown_id IS NOT NULL;
END$$

DELIMITER ;

-- ============================================================================
-- STEP 8: VERIFICATION & SUMMARY
-- ============================================================================

SELECT '========================================' AS '';
SELECT 'MIGRATION COMPLETED SUCCESSFULLY' AS 'STATUS';
SELECT '========================================' AS '';

SELECT 'Dynamic Dropdowns Summary:' AS '';
SELECT
    dropdown_id AS 'Dropdown ID',
    category_name AS 'Category',
    value_count AS 'Total Values',
    CASE WHEN is_active = 1 THEN 'Active' ELSE 'Inactive' END AS 'Status'
FROM vw_dynamic_dropdowns
ORDER BY dropdown_id;

SELECT '' AS '';
SELECT 'Sample Values from Dropdown ID 455 (User Roles):' AS '';
CALL sp_get_values_by_dropdown_id(455);

-- Reset SQL modes
SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

SELECT '' AS '';
SELECT '========================================' AS '';
SELECT 'MIGRATION VERIFICATION COMPLETE' AS '';
SELECT 'All dynamic dropdowns are ready to use!' AS '';
SELECT '========================================' AS '';

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
