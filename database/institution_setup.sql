-- Institution and Dynamic Dropdown Management Tables
-- EduManage Pro - School/College Management System

-- Institution Types Table
CREATE TABLE IF NOT EXISTS institution_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default institution types
INSERT IGNORE INTO institution_types (name, description) VALUES
('School', 'Primary and Secondary Education Institution'),
('College', 'Higher Education Institution');

-- Institution Settings Table (stores current institution configuration)
CREATE TABLE IF NOT EXISTS institution_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO institution_settings (setting_key, setting_value) VALUES
('institution_type', 'School'),
('institution_name', 'EduManage Pro'),
('institution_address', ''),
('institution_phone', ''),
('institution_email', ''),
('institution_logo', ''),
('academic_year', '2024-2025')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Dropdown Categories Table
CREATE TABLE IF NOT EXISTS dropdown_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_key VARCHAR(50) NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    institution_type_id INT NULL,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_category (category_key, institution_type_id),
    FOREIGN KEY (institution_type_id) REFERENCES institution_types(id) ON DELETE SET NULL
);

-- Insert dropdown categories for School
INSERT INTO dropdown_categories (category_key, category_name, institution_type_id, description, is_system) VALUES
('class', 'Class', 1, 'Academic classes for school', 1),
('section', 'Section', 1, 'Class sections', 1),
('subject', 'Subject', 1, 'Academic subjects for school', 1),
('gender', 'Gender', NULL, 'Gender options', 1),
('blood_group', 'Blood Group', NULL, 'Blood group options', 1),
('religion', 'Religion', NULL, 'Religion options', 1),
('category', 'Category', NULL, 'Student category (General, OBC, etc.)', 1),
('designation', 'Designation', NULL, 'Staff designation', 1),
('fee_type', 'Fee Type', NULL, 'Types of fees', 1),
('payment_mode', 'Payment Mode', NULL, 'Payment methods', 1),
('exam_type', 'Exam Type', NULL, 'Types of examinations', 1),
('grade', 'Grade', NULL, 'Grading system', 1),
('status', 'Status', NULL, 'Active/Inactive status', 1)
ON DUPLICATE KEY UPDATE category_key = category_key;

-- Insert dropdown categories for College
INSERT INTO dropdown_categories (category_key, category_name, institution_type_id, description, is_system) VALUES
('department', 'Department', 2, 'Academic departments for college', 1),
('course', 'Course', 2, 'Courses/Programs offered', 1),
('semester', 'Semester', 2, 'Academic semesters', 1),
('subject_college', 'Subject', 2, 'Academic subjects for college', 1),
('faculty', 'Faculty', 2, 'Faculty members', 1),
('degree', 'Degree', 2, 'Degree types (B.Tech, M.Tech, etc.)', 1),
('specialization', 'Specialization', 2, 'Course specializations', 1)
ON DUPLICATE KEY UPDATE category_key = category_key;

-- Dropdown Values Table
CREATE TABLE IF NOT EXISTS dropdown_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    value VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    parent_id INT NULL,
    metadata JSON NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES dropdown_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES dropdown_values(id) ON DELETE SET NULL,
    UNIQUE KEY unique_value (category_id, value)
);

-- Insert default dropdown values for School classes
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Nursery', 1 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'LKG', 2 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'UKG', 3 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 3;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Class 1', 4 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 4;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Class 2', 5 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 5;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Class 3', 6 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 6;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Class 4', 7 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 7;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Class 5', 8 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 8;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Class 6', 9 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 9;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Class 7', 10 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 10;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Class 8', 11 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 11;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Class 9', 12 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 12;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Class 10', 13 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 13;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Class 11', 14 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 14;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Class 12', 15 FROM dropdown_categories WHERE category_key = 'class' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 15;

-- Insert default sections
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'A', 1 FROM dropdown_categories WHERE category_key = 'section' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'B', 2 FROM dropdown_categories WHERE category_key = 'section' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'C', 3 FROM dropdown_categories WHERE category_key = 'section' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 3;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'D', 4 FROM dropdown_categories WHERE category_key = 'section' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 4;

-- Insert default subjects for school
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Mathematics', 1 FROM dropdown_categories WHERE category_key = 'subject' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'English', 2 FROM dropdown_categories WHERE category_key = 'subject' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Science', 3 FROM dropdown_categories WHERE category_key = 'subject' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 3;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Social Studies', 4 FROM dropdown_categories WHERE category_key = 'subject' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 4;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Hindi', 5 FROM dropdown_categories WHERE category_key = 'subject' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 5;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Physics', 6 FROM dropdown_categories WHERE category_key = 'subject' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 6;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Chemistry', 7 FROM dropdown_categories WHERE category_key = 'subject' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 7;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Biology', 8 FROM dropdown_categories WHERE category_key = 'subject' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 8;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Computer Science', 9 FROM dropdown_categories WHERE category_key = 'subject' AND institution_type_id = 1
ON DUPLICATE KEY UPDATE display_order = 9;

-- Insert default gender values (common)
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Male', 1 FROM dropdown_categories WHERE category_key = 'gender' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Female', 2 FROM dropdown_categories WHERE category_key = 'gender' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Other', 3 FROM dropdown_categories WHERE category_key = 'gender' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 3;

-- Insert default blood groups (common)
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'A+', 1 FROM dropdown_categories WHERE category_key = 'blood_group' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'A-', 2 FROM dropdown_categories WHERE category_key = 'blood_group' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'B+', 3 FROM dropdown_categories WHERE category_key = 'blood_group' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 3;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'B-', 4 FROM dropdown_categories WHERE category_key = 'blood_group' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 4;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'O+', 5 FROM dropdown_categories WHERE category_key = 'blood_group' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 5;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'O-', 6 FROM dropdown_categories WHERE category_key = 'blood_group' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 6;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'AB+', 7 FROM dropdown_categories WHERE category_key = 'blood_group' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 7;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'AB-', 8 FROM dropdown_categories WHERE category_key = 'blood_group' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 8;

-- Insert default categories (common)
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'General', 1 FROM dropdown_categories WHERE category_key = 'category' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'OBC', 2 FROM dropdown_categories WHERE category_key = 'category' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'SC', 3 FROM dropdown_categories WHERE category_key = 'category' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 3;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'ST', 4 FROM dropdown_categories WHERE category_key = 'category' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 4;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'EWS', 5 FROM dropdown_categories WHERE category_key = 'category' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 5;

-- Insert College departments
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Computer Science', 1 FROM dropdown_categories WHERE category_key = 'department' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Electronics', 2 FROM dropdown_categories WHERE category_key = 'department' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Mechanical', 3 FROM dropdown_categories WHERE category_key = 'department' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 3;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Civil', 4 FROM dropdown_categories WHERE category_key = 'department' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 4;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Electrical', 5 FROM dropdown_categories WHERE category_key = 'department' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 5;

-- Insert College courses
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'B.Tech', 1 FROM dropdown_categories WHERE category_key = 'course' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'M.Tech', 2 FROM dropdown_categories WHERE category_key = 'course' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'BCA', 3 FROM dropdown_categories WHERE category_key = 'course' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 3;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'MCA', 4 FROM dropdown_categories WHERE category_key = 'course' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 4;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'B.Sc', 5 FROM dropdown_categories WHERE category_key = 'course' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 5;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'M.Sc', 6 FROM dropdown_categories WHERE category_key = 'course' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 6;

-- Insert College semesters
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Semester 1', 1 FROM dropdown_categories WHERE category_key = 'semester' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Semester 2', 2 FROM dropdown_categories WHERE category_key = 'semester' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Semester 3', 3 FROM dropdown_categories WHERE category_key = 'semester' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 3;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Semester 4', 4 FROM dropdown_categories WHERE category_key = 'semester' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 4;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Semester 5', 5 FROM dropdown_categories WHERE category_key = 'semester' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 5;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Semester 6', 6 FROM dropdown_categories WHERE category_key = 'semester' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 6;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Semester 7', 7 FROM dropdown_categories WHERE category_key = 'semester' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 7;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Semester 8', 8 FROM dropdown_categories WHERE category_key = 'semester' AND institution_type_id = 2
ON DUPLICATE KEY UPDATE display_order = 8;

-- Insert fee types (common)
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Tuition Fee', 1 FROM dropdown_categories WHERE category_key = 'fee_type' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Admission Fee', 2 FROM dropdown_categories WHERE category_key = 'fee_type' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Exam Fee', 3 FROM dropdown_categories WHERE category_key = 'fee_type' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 3;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Transport Fee', 4 FROM dropdown_categories WHERE category_key = 'fee_type' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 4;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Library Fee', 5 FROM dropdown_categories WHERE category_key = 'fee_type' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 5;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Lab Fee', 6 FROM dropdown_categories WHERE category_key = 'fee_type' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 6;

-- Insert payment modes (common)
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Cash', 1 FROM dropdown_categories WHERE category_key = 'payment_mode' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Cheque', 2 FROM dropdown_categories WHERE category_key = 'payment_mode' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Online Transfer', 3 FROM dropdown_categories WHERE category_key = 'payment_mode' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 3;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'UPI', 4 FROM dropdown_categories WHERE category_key = 'payment_mode' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 4;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Card', 5 FROM dropdown_categories WHERE category_key = 'payment_mode' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 5;

-- Insert exam types (common)
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Unit Test', 1 FROM dropdown_categories WHERE category_key = 'exam_type' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Mid-Term', 2 FROM dropdown_categories WHERE category_key = 'exam_type' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Quarterly', 3 FROM dropdown_categories WHERE category_key = 'exam_type' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 3;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Half-Yearly', 4 FROM dropdown_categories WHERE category_key = 'exam_type' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 4;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Annual', 5 FROM dropdown_categories WHERE category_key = 'exam_type' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 5;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Pre-Board', 6 FROM dropdown_categories WHERE category_key = 'exam_type' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 6;

-- Insert designations (common)
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Principal', 1 FROM dropdown_categories WHERE category_key = 'designation' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 1;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Vice Principal', 2 FROM dropdown_categories WHERE category_key = 'designation' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 2;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'HOD', 3 FROM dropdown_categories WHERE category_key = 'designation' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 3;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Senior Teacher', 4 FROM dropdown_categories WHERE category_key = 'designation' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 4;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Teacher', 5 FROM dropdown_categories WHERE category_key = 'designation' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 5;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Assistant Teacher', 6 FROM dropdown_categories WHERE category_key = 'designation' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 6;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Lab Assistant', 7 FROM dropdown_categories WHERE category_key = 'designation' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 7;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Clerk', 8 FROM dropdown_categories WHERE category_key = 'designation' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 8;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Accountant', 9 FROM dropdown_categories WHERE category_key = 'designation' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 9;
INSERT INTO dropdown_values (category_id, value, display_order)
SELECT id, 'Librarian', 10 FROM dropdown_categories WHERE category_key = 'designation' AND institution_type_id IS NULL
ON DUPLICATE KEY UPDATE display_order = 10;
