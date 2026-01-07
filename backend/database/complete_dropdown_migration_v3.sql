-- ============================================================================
-- COMPLETE DROPDOWN MIGRATION WITH DEFAULT VALUES - V3
-- ============================================================================
-- This migration adds Dropdown ID support and inserts all default values
-- Compatible with actual database structure (no display_order in categories)
-- ============================================================================

-- Step 1: Ensure dropdown_id column exists (it already does, but safe to run)
ALTER TABLE dropdown_categories
ADD COLUMN IF NOT EXISTS dropdown_id INT NULL UNIQUE COMMENT 'Unique numeric ID for dynamic dropdowns' AFTER id;

-- Step 2: Add index for dropdown_id if it doesn't exist
-- Note: Index may already exist from previous migration attempts
-- Skipping index creation to avoid errors

-- Step 3: Insert dynamic dropdown categories (using INSERT IGNORE to avoid duplicates)
INSERT IGNORE INTO dropdown_categories (dropdown_id, category_key, category_name, institution_type_id, is_system, is_active) VALUES
(455, 'user_role', 'User Role', 1, 0, 1),
(456, 'department', 'Department', 1, 0, 1),
(457, 'designation', 'Designation', 1, 0, 1),
(458, 'blood_group_dynamic', 'Blood Group', 1, 0, 1),
(459, 'religion', 'Religion', 1, 0, 1),
(460, 'caste', 'Caste/Category', 1, 0, 1),
(461, 'language', 'Language', 1, 0, 1),
(462, 'nationality', 'Nationality', 1, 0, 1),
(463, 'marital_status_dynamic', 'Marital Status', 1, 0, 1),
(464, 'employment_type_dynamic', 'Employment Type', 1, 0, 1),
(465, 'qualification_dynamic', 'Qualification', 1, 0, 1),
(466, 'fee_category', 'Fee Category', 1, 0, 1),
(467, 'payment_method', 'Payment Method', 1, 0, 1),
(468, 'document_type', 'Document Type', 1, 0, 1),
(469, 'leave_type', 'Leave Type', 1, 0, 1),
(470, 'exam_type', 'Exam Type', 1, 0, 1);

-- Step 4: Get category IDs for each dropdown (using variables)
SET @cat_455 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 455);
SET @cat_456 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 456);
SET @cat_457 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 457);
SET @cat_458 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 458);
SET @cat_459 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 459);
SET @cat_460 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 460);
SET @cat_461 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 461);
SET @cat_462 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 462);
SET @cat_463 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 463);
SET @cat_464 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 464);
SET @cat_465 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 465);
SET @cat_466 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 466);
SET @cat_467 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 467);
SET @cat_468 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 468);
SET @cat_469 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 469);
SET @cat_470 = (SELECT id FROM dropdown_categories WHERE dropdown_id = 470);

-- Step 5: Insert default values for each dropdown
-- Using INSERT IGNORE to avoid duplicates if script is run multiple times

-- Dropdown 455: User Role
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_455, 'Teacher', 1, 1),
(@cat_455, 'Parent', 2, 1),
(@cat_455, 'Guardian', 3, 1),
(@cat_455, 'Student', 4, 1),
(@cat_455, 'Admin Staff', 5, 1),
(@cat_455, 'Principal', 6, 1),
(@cat_455, 'Vice Principal', 7, 1),
(@cat_455, 'Coordinator', 8, 1),
(@cat_455, 'Librarian', 9, 1),
(@cat_455, 'Other', 10, 1);

-- Dropdown 456: Department
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_456, 'Science', 1, 1),
(@cat_456, 'Mathematics', 2, 1),
(@cat_456, 'English', 3, 1),
(@cat_456, 'Social Studies', 4, 1),
(@cat_456, 'Languages', 5, 1),
(@cat_456, 'Physical Education', 6, 1),
(@cat_456, 'Arts', 7, 1),
(@cat_456, 'Music', 8, 1),
(@cat_456, 'Administration', 9, 1),
(@cat_456, 'Library', 10, 1);

-- Dropdown 457: Designation
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_457, 'Principal', 1, 1),
(@cat_457, 'Vice Principal', 2, 1),
(@cat_457, 'Senior Teacher', 3, 1),
(@cat_457, 'Teacher', 4, 1),
(@cat_457, 'Junior Teacher', 5, 1),
(@cat_457, 'Department Head', 6, 1),
(@cat_457, 'Subject Coordinator', 7, 1),
(@cat_457, 'Lab Assistant', 8, 1),
(@cat_457, 'Admin Officer', 9, 1),
(@cat_457, 'Librarian', 10, 1),
(@cat_457, 'Other', 11, 1);

-- Dropdown 458: Blood Group
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_458, 'A+', 1, 1),
(@cat_458, 'A-', 2, 1),
(@cat_458, 'B+', 3, 1),
(@cat_458, 'B-', 4, 1),
(@cat_458, 'AB+', 5, 1),
(@cat_458, 'AB-', 6, 1),
(@cat_458, 'O+', 7, 1),
(@cat_458, 'O-', 8, 1);

-- Dropdown 459: Religion
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_459, 'Hinduism', 1, 1),
(@cat_459, 'Islam', 2, 1),
(@cat_459, 'Christianity', 3, 1),
(@cat_459, 'Sikhism', 4, 1),
(@cat_459, 'Buddhism', 5, 1),
(@cat_459, 'Jainism', 6, 1),
(@cat_459, 'Other', 7, 1);

-- Dropdown 460: Caste/Category
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_460, 'General', 1, 1),
(@cat_460, 'OBC', 2, 1),
(@cat_460, 'SC', 3, 1),
(@cat_460, 'ST', 4, 1),
(@cat_460, 'Other', 5, 1);

-- Dropdown 461: Language
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_461, 'English', 1, 1),
(@cat_461, 'Hindi', 2, 1),
(@cat_461, 'Bengali', 3, 1),
(@cat_461, 'Tamil', 4, 1),
(@cat_461, 'Telugu', 5, 1),
(@cat_461, 'Marathi', 6, 1),
(@cat_461, 'Gujarati', 7, 1),
(@cat_461, 'Kannada', 8, 1),
(@cat_461, 'Malayalam', 9, 1),
(@cat_461, 'Punjabi', 10, 1),
(@cat_461, 'Other', 11, 1);

-- Dropdown 462: Nationality
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_462, 'Indian', 1, 1),
(@cat_462, 'American', 2, 1),
(@cat_462, 'British', 3, 1),
(@cat_462, 'Canadian', 4, 1),
(@cat_462, 'Australian', 5, 1),
(@cat_462, 'Other', 6, 1);

-- Dropdown 463: Marital Status
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_463, 'Single', 1, 1),
(@cat_463, 'Married', 2, 1),
(@cat_463, 'Divorced', 3, 1),
(@cat_463, 'Widowed', 4, 1);

-- Dropdown 464: Employment Type
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_464, 'Permanent', 1, 1),
(@cat_464, 'Temporary', 2, 1),
(@cat_464, 'Contract', 3, 1),
(@cat_464, 'Part-time', 4, 1),
(@cat_464, 'Full-time', 5, 1),
(@cat_464, 'Probation', 6, 1);

-- Dropdown 465: Qualification
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_465, 'B.Ed', 1, 1),
(@cat_465, 'M.Ed', 2, 1),
(@cat_465, 'B.A', 3, 1),
(@cat_465, 'M.A', 4, 1),
(@cat_465, 'B.Sc', 5, 1),
(@cat_465, 'M.Sc', 6, 1),
(@cat_465, 'Ph.D', 7, 1),
(@cat_465, 'Other', 8, 1);

-- Dropdown 466: Fee Category
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_466, 'Tuition Fee', 1, 1),
(@cat_466, 'Library Fee', 2, 1),
(@cat_466, 'Lab Fee', 3, 1),
(@cat_466, 'Sports Fee', 4, 1),
(@cat_466, 'Transport Fee', 5, 1),
(@cat_466, 'Examination Fee', 6, 1),
(@cat_466, 'Development Fee', 7, 1),
(@cat_466, 'Computer Fee', 8, 1),
(@cat_466, 'Activity Fee', 9, 1),
(@cat_466, 'Admission Fee', 10, 1),
(@cat_466, 'Other', 11, 1);

-- Dropdown 467: Payment Method
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_467, 'Cash', 1, 1),
(@cat_467, 'Cheque', 2, 1),
(@cat_467, 'Demand Draft', 3, 1),
(@cat_467, 'Credit Card', 4, 1),
(@cat_467, 'Debit Card', 5, 1),
(@cat_467, 'Online Transfer', 6, 1),
(@cat_467, 'UPI', 7, 1),
(@cat_467, 'Net Banking', 8, 1);

-- Dropdown 468: Document Type
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_468, 'Birth Certificate', 1, 1),
(@cat_468, 'Transfer Certificate', 2, 1),
(@cat_468, 'Mark Sheet', 3, 1),
(@cat_468, 'Aadhar Card', 4, 1),
(@cat_468, 'Passport', 5, 1),
(@cat_468, 'Address Proof', 6, 1),
(@cat_468, 'Income Certificate', 7, 1),
(@cat_468, 'Caste Certificate', 8, 1),
(@cat_468, 'Medical Certificate', 9, 1),
(@cat_468, 'Photo ID', 10, 1),
(@cat_468, 'Other', 11, 1);

-- Dropdown 469: Leave Type
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_469, 'Sick Leave', 1, 1),
(@cat_469, 'Casual Leave', 2, 1),
(@cat_469, 'Earned Leave', 3, 1),
(@cat_469, 'Maternity Leave', 4, 1),
(@cat_469, 'Paternity Leave', 5, 1),
(@cat_469, 'Medical Leave', 6, 1),
(@cat_469, 'Emergency Leave', 7, 1),
(@cat_469, 'Other', 8, 1);

-- Dropdown 470: Exam Type
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active) VALUES
(@cat_470, 'Half Yearly', 1, 1),
(@cat_470, 'Annual', 2, 1),
(@cat_470, 'Unit Test', 3, 1),
(@cat_470, 'Weekly Test', 4, 1),
(@cat_470, 'Monthly Test', 5, 1),
(@cat_470, 'Terminal Exam', 6, 1),
(@cat_470, 'Practical Exam', 7, 1),
(@cat_470, 'Other', 8, 1);

-- Step 6: Create views for easy querying
CREATE OR REPLACE VIEW vw_dynamic_dropdowns AS
SELECT
    dc.dropdown_id,
    dc.id AS category_id,
    dc.category_key,
    dc.category_name,
    dc.institution_type_id,
    COUNT(dv.id) AS value_count,
    dc.is_active
FROM dropdown_categories dc
LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
WHERE dc.dropdown_id IS NOT NULL
GROUP BY dc.id, dc.dropdown_id, dc.category_key, dc.category_name,
         dc.institution_type_id, dc.is_active
ORDER BY dc.dropdown_id;

CREATE OR REPLACE VIEW vw_hardcoded_dropdowns AS
SELECT
    dc.id AS category_id,
    dc.category_key,
    dc.category_name,
    dc.institution_type_id,
    COUNT(dv.id) AS value_count,
    dc.is_active
FROM dropdown_categories dc
LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
WHERE dc.dropdown_id IS NULL
GROUP BY dc.id, dc.category_key, dc.category_name,
         dc.institution_type_id, dc.is_active
ORDER BY dc.category_key;

-- ============================================================================
-- VERIFICATION QUERIES (commented out)
-- ============================================================================
-- SELECT * FROM vw_dynamic_dropdowns;
-- SELECT dropdown_id, category_name, value_count FROM vw_dynamic_dropdowns ORDER BY dropdown_id;

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
