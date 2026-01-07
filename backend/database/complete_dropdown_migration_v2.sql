-- ============================================================================
-- COMPLETE END-TO-END DROPDOWN MIGRATION - VERSION 2 (MySQL Compatible)
-- ============================================================================

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

-- Create institution_types
CREATE TABLE IF NOT EXISTS institution_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO institution_types (id, name) VALUES
(1, 'School'), (2, 'College'), (3, 'University');

-- Create institution_settings
CREATE TABLE IF NOT EXISTS institution_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO institution_settings (setting_key, setting_value)
VALUES ('institution_type', 'School')
ON DUPLICATE KEY UPDATE setting_value = 'School';

-- Create dropdown_categories
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

-- Create dropdown_values
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

-- Add dropdown_id column
ALTER TABLE dropdown_categories
ADD COLUMN IF NOT EXISTS dropdown_id INT NULL UNIQUE COMMENT 'Unique numeric ID for dynamic dropdowns' AFTER id,
ADD INDEX IF NOT EXISTS idx_dropdown_id (dropdown_id);

-- Insert dynamic dropdown categories
INSERT INTO dropdown_categories (dropdown_id, category_key, category_name, institution_type_id, display_order, is_active) VALUES
(455, 'user_role', 'User Role', 1, 10, 1),
(456, 'department', 'Department', 1, 20, 1),
(457, 'designation', 'Designation', 1, 30, 1),
(458, 'blood_group_dynamic', 'Blood Group', 1, 40, 1),
(459, 'religion', 'Religion', 1, 50, 1),
(460, 'caste', 'Caste/Category', 1, 60, 1),
(461, 'language', 'Language', 1, 70, 1),
(462, 'nationality', 'Nationality', 1, 80, 1),
(463, 'marital_status_dynamic', 'Marital Status', 1, 90, 1),
(464, 'employment_type_dynamic', 'Employment Type', 1, 100, 1),
(465, 'qualification_dynamic', 'Qualification', 1, 110, 1),
(466, 'fee_category', 'Fee Category', 1, 120, 1),
(467, 'payment_method', 'Payment Method', 1, 130, 1),
(468, 'document_type', 'Document Type', 1, 140, 1),
(469, 'leave_type', 'Leave Type', 1, 150, 1),
(470, 'exam_type', 'Exam Type', 1, 160, 1)
ON DUPLICATE KEY UPDATE
    dropdown_id = VALUES(dropdown_id),
    display_order = VALUES(display_order);

-- Insert hard coded categories
INSERT INTO dropdown_categories (dropdown_id, category_key, category_name, institution_type_id, display_order, is_active) VALUES
(NULL, 'gender', 'Gender', 1, 1, 1),
(NULL, 'subject', 'Subject', 1, 2, 1),
(NULL, 'class', 'Class', 1, 3, 1),
(NULL, 'section', 'Section', 1, 4, 1),
(NULL, 'status', 'Status', 1, 5, 1)
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);

-- Insert default values for all dynamic dropdowns
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

-- ID 455: User Roles
DELETE FROM dropdown_values WHERE category_id = @cat_455;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_455, 'Teacher', 1, 1), (@cat_455, 'Parent', 2, 1), (@cat_455, 'Guardian', 3, 1),
(@cat_455, 'Student', 4, 1), (@cat_455, 'Father', 5, 1), (@cat_455, 'Mother', 6, 1),
(@cat_455, 'Sibling', 7, 1), (@cat_455, 'Grandparent', 8, 1), (@cat_455, 'Uncle/Aunt', 9, 1),
(@cat_455, 'Legal Guardian', 10, 1);

-- ID 456: Departments
DELETE FROM dropdown_values WHERE category_id = @cat_456;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_456, 'Science', 1, 1), (@cat_456, 'Mathematics', 2, 1), (@cat_456, 'English', 3, 1),
(@cat_456, 'Hindi', 4, 1), (@cat_456, 'Social Science', 5, 1), (@cat_456, 'Commerce', 6, 1),
(@cat_456, 'Arts', 7, 1), (@cat_456, 'Computer Science', 8, 1), (@cat_456, 'Physical Education', 9, 1),
(@cat_456, 'Music & Arts', 10, 1);

-- ID 457: Designations
DELETE FROM dropdown_values WHERE category_id = @cat_457;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_457, 'Principal', 1, 1), (@cat_457, 'Vice Principal', 2, 1), (@cat_457, 'Head of Department', 3, 1),
(@cat_457, 'Senior Teacher', 4, 1), (@cat_457, 'Teacher', 5, 1), (@cat_457, 'Assistant Teacher', 6, 1),
(@cat_457, 'Lab Assistant', 7, 1), (@cat_457, 'Librarian', 8, 1), (@cat_457, 'Sports Instructor', 9, 1),
(@cat_457, 'Counselor', 10, 1), (@cat_457, 'Administrative Staff', 11, 1);

-- ID 458: Blood Groups
DELETE FROM dropdown_values WHERE category_id = @cat_458;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_458, 'A+', 1, 1), (@cat_458, 'A-', 2, 1), (@cat_458, 'B+', 3, 1), (@cat_458, 'B-', 4, 1),
(@cat_458, 'AB+', 5, 1), (@cat_458, 'AB-', 6, 1), (@cat_458, 'O+', 7, 1), (@cat_458, 'O-', 8, 1);

-- ID 459: Religions
DELETE FROM dropdown_values WHERE category_id = @cat_459;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_459, 'Hinduism', 1, 1), (@cat_459, 'Islam', 2, 1), (@cat_459, 'Christianity', 3, 1),
(@cat_459, 'Sikhism', 4, 1), (@cat_459, 'Buddhism', 5, 1), (@cat_459, 'Jainism', 6, 1), (@cat_459, 'Other', 7, 1);

-- ID 460: Castes
DELETE FROM dropdown_values WHERE category_id = @cat_460;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_460, 'General', 1, 1), (@cat_460, 'OBC', 2, 1), (@cat_460, 'SC', 3, 1), (@cat_460, 'ST', 4, 1), (@cat_460, 'EWS', 5, 1);

-- ID 461: Languages
DELETE FROM dropdown_values WHERE category_id = @cat_461;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_461, 'English', 1, 1), (@cat_461, 'Hindi', 2, 1), (@cat_461, 'Tamil', 3, 1), (@cat_461, 'Telugu', 4, 1),
(@cat_461, 'Kannada', 5, 1), (@cat_461, 'Malayalam', 6, 1), (@cat_461, 'Bengali', 7, 1), (@cat_461, 'Marathi', 8, 1),
(@cat_461, 'Gujarati', 9, 1), (@cat_461, 'Punjabi', 10, 1), (@cat_461, 'Urdu', 11, 1);

-- ID 462: Nationalities
DELETE FROM dropdown_values WHERE category_id = @cat_462;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_462, 'Indian', 1, 1), (@cat_462, 'American', 2, 1), (@cat_462, 'British', 3, 1),
(@cat_462, 'Canadian', 4, 1), (@cat_462, 'Australian', 5, 1), (@cat_462, 'Other', 6, 1);

-- ID 463: Marital Status
DELETE FROM dropdown_values WHERE category_id = @cat_463;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_463, 'Single', 1, 1), (@cat_463, 'Married', 2, 1), (@cat_463, 'Divorced', 3, 1), (@cat_463, 'Widowed', 4, 1);

-- ID 464: Employment Types
DELETE FROM dropdown_values WHERE category_id = @cat_464;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_464, 'Permanent', 1, 1), (@cat_464, 'Contract', 2, 1), (@cat_464, 'Temporary', 3, 1),
(@cat_464, 'Part-Time', 4, 1), (@cat_464, 'Visiting', 5, 1), (@cat_464, 'Guest Faculty', 6, 1);

-- ID 465: Qualifications
DELETE FROM dropdown_values WHERE category_id = @cat_465;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_465, 'High School', 1, 1), (@cat_465, 'Diploma', 2, 1), (@cat_465, 'Under Graduate (UG)', 3, 1),
(@cat_465, 'Post Graduate (PG)', 4, 1), (@cat_465, 'M.Phil', 5, 1), (@cat_465, 'PhD (Doctorate)', 6, 1),
(@cat_465, 'B.Ed', 7, 1), (@cat_465, 'M.Ed', 8, 1);

-- ID 466: Fee Categories
DELETE FROM dropdown_values WHERE category_id = @cat_466;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_466, 'Tuition Fee', 1, 1), (@cat_466, 'Admission Fee', 2, 1), (@cat_466, 'Exam Fee', 3, 1),
(@cat_466, 'Library Fee', 4, 1), (@cat_466, 'Lab Fee', 5, 1), (@cat_466, 'Sports Fee', 6, 1),
(@cat_466, 'Transport Fee', 7, 1), (@cat_466, 'Hostel Fee', 8, 1), (@cat_466, 'Development Fee', 9, 1),
(@cat_466, 'Late Fee', 10, 1), (@cat_466, 'Miscellaneous', 11, 1);

-- ID 467: Payment Methods
DELETE FROM dropdown_values WHERE category_id = @cat_467;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_467, 'Cash', 1, 1), (@cat_467, 'Cheque', 2, 1), (@cat_467, 'Demand Draft', 3, 1),
(@cat_467, 'Online Transfer', 4, 1), (@cat_467, 'UPI', 5, 1), (@cat_467, 'Debit Card', 6, 1),
(@cat_467, 'Credit Card', 7, 1), (@cat_467, 'Net Banking', 8, 1);

-- ID 468: Document Types
DELETE FROM dropdown_values WHERE category_id = @cat_468;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_468, 'Birth Certificate', 1, 1), (@cat_468, 'Aadhaar Card', 2, 1), (@cat_468, 'PAN Card', 3, 1),
(@cat_468, 'Transfer Certificate', 4, 1), (@cat_468, 'Mark Sheet', 5, 1), (@cat_468, 'Degree Certificate', 6, 1),
(@cat_468, 'Passport', 7, 1), (@cat_468, 'Caste Certificate', 8, 1), (@cat_468, 'Income Certificate', 9, 1),
(@cat_468, 'Medical Certificate', 10, 1), (@cat_468, 'Photo ID', 11, 1);

-- ID 469: Leave Types
DELETE FROM dropdown_values WHERE category_id = @cat_469;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_469, 'Casual Leave', 1, 1), (@cat_469, 'Sick Leave', 2, 1), (@cat_469, 'Earned Leave', 3, 1),
(@cat_469, 'Maternity Leave', 4, 1), (@cat_469, 'Paternity Leave', 5, 1), (@cat_469, 'Compensatory Leave', 6, 1),
(@cat_469, 'Study Leave', 7, 1), (@cat_469, 'Special Leave', 8, 1);

-- ID 470: Exam Types
DELETE FROM dropdown_values WHERE category_id = @cat_470;
INSERT INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_470, 'Unit Test', 1, 1), (@cat_470, 'Mid Term', 2, 1), (@cat_470, 'Final Term', 3, 1),
(@cat_470, 'Half Yearly', 4, 1), (@cat_470, 'Annual', 5, 1), (@cat_470, 'Quarterly', 6, 1),
(@cat_470, 'Pre-Board', 7, 1), (@cat_470, 'Board Exam', 8, 1);

-- Create views
CREATE OR REPLACE VIEW vw_dynamic_dropdowns AS
SELECT
    dc.dropdown_id,
    dc.id AS category_id,
    dc.category_key,
    dc.category_name,
    COUNT(dv.id) AS value_count
FROM dropdown_categories dc
LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
WHERE dc.dropdown_id IS NOT NULL
GROUP BY dc.id
ORDER BY dc.dropdown_id;

-- Create stored procedures
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_get_values_by_dropdown_id$$
CREATE PROCEDURE sp_get_values_by_dropdown_id(IN p_dropdown_id INT)
BEGIN
    SELECT dv.id, dv.value, dv.display_order, dc.category_name
    FROM dropdown_values dv
    INNER JOIN dropdown_categories dc ON dv.category_id = dc.id
    WHERE dc.dropdown_id = p_dropdown_id AND dv.is_active = 1
    ORDER BY dv.display_order;
END$$

DELIMITER ;

-- Show results
SELECT '========================================' AS '';
SELECT 'MIGRATION COMPLETED SUCCESSFULLY!' AS 'STATUS';
SELECT '========================================' AS '';
SELECT * FROM vw_dynamic_dropdowns;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
