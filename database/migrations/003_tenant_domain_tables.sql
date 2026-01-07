-- ============================================================================
-- MULTI-TENANT SAAS ARCHITECTURE - TENANT DOMAIN TABLES
-- Migration: 003_tenant_domain_tables.sql
-- Description: Creates all tenant-aware domain/business tables
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- ACADEMIC STRUCTURE TABLES
-- ============================================================================

-- 1. ACADEMIC YEARS
CREATE TABLE IF NOT EXISTS mt_academic_years (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    year_code VARCHAR(20) NOT NULL COMMENT 'Display ID like AY2024-25',
    name VARCHAR(100) NOT NULL COMMENT 'e.g., 2024-2025',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,

    is_current BOOLEAN DEFAULT FALSE,
    status ENUM('upcoming', 'active', 'completed') DEFAULT 'upcoming',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_year_code (tenant_id, year_code),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_is_current (is_current),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CLASSES/GRADES
CREATE TABLE IF NOT EXISTS mt_classes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    class_code VARCHAR(20) NOT NULL COMMENT 'Display ID like CLS001',
    name VARCHAR(100) NOT NULL COMMENT 'e.g., Class 10, Grade 5',
    numeric_value INT DEFAULT NULL COMMENT 'For sorting: 1, 2, 3...',
    description TEXT DEFAULT NULL,

    -- Fees default
    default_monthly_fee DECIMAL(10, 2) DEFAULT 0.00,
    default_annual_fee DECIMAL(10, 2) DEFAULT 0.00,

    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_class_code (tenant_id, class_code),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. SECTIONS
CREATE TABLE IF NOT EXISTS mt_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    section_code VARCHAR(20) NOT NULL COMMENT 'Display ID like SEC001',
    name VARCHAR(50) NOT NULL COMMENT 'e.g., A, B, C',

    capacity INT UNSIGNED DEFAULT 40,
    room_number VARCHAR(20) DEFAULT NULL,

    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_section_code (tenant_id, section_code),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. CLASS-SECTION MAPPING (per academic year)
CREATE TABLE IF NOT EXISTS mt_class_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED NOT NULL,

    -- Class teacher assignment
    class_teacher_id INT UNSIGNED DEFAULT NULL,

    capacity INT UNSIGNED DEFAULT 40,
    room_number VARCHAR(20) DEFAULT NULL,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_year_class_section (academic_year_id, class_id, section_id),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES mt_academic_years(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES mt_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES mt_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. SUBJECTS
CREATE TABLE IF NOT EXISTS mt_subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    subject_code VARCHAR(20) NOT NULL COMMENT 'Display ID like SUB001',
    name VARCHAR(100) NOT NULL,
    short_name VARCHAR(20) DEFAULT NULL,
    description TEXT DEFAULT NULL,

    -- Type
    subject_type ENUM('theory', 'practical', 'both') DEFAULT 'theory',
    is_optional BOOLEAN DEFAULT FALSE,
    is_language BOOLEAN DEFAULT FALSE,

    -- Marks configuration
    max_marks INT UNSIGNED DEFAULT 100,
    passing_marks INT UNSIGNED DEFAULT 35,

    -- Credit system
    credit_hours DECIMAL(3, 1) DEFAULT NULL,

    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_subject_code (tenant_id, subject_code),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. CLASS-SUBJECT MAPPING
CREATE TABLE IF NOT EXISTS mt_class_subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,

    is_mandatory BOOLEAN DEFAULT TRUE,
    weekly_periods INT UNSIGNED DEFAULT 5,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_class_subject (class_id, subject_id),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES mt_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES mt_subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PEOPLE TABLES
-- ============================================================================

-- 7. STUDENTS
CREATE TABLE IF NOT EXISTS mt_students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    student_code VARCHAR(20) NOT NULL COMMENT 'Admission/Roll number like STU001',
    admission_number VARCHAR(50) DEFAULT NULL,
    roll_number VARCHAR(20) DEFAULT NULL,

    -- Link to user account (optional)
    user_id INT UNSIGNED DEFAULT NULL,

    -- Personal Info
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) NOT NULL,
    full_name VARCHAR(300) GENERATED ALWAYS AS (
        CONCAT_WS(' ', first_name, middle_name, last_name)
    ) STORED,

    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    blood_group VARCHAR(5) DEFAULT NULL,
    religion VARCHAR(50) DEFAULT NULL,
    caste VARCHAR(100) DEFAULT NULL,
    nationality VARCHAR(50) DEFAULT 'Indian',

    -- Contact
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address_line1 VARCHAR(255) DEFAULT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    country VARCHAR(100) DEFAULT 'India',
    postal_code VARCHAR(20) DEFAULT NULL,

    -- Documents
    photo_url VARCHAR(500) DEFAULT NULL,
    aadhaar_number VARCHAR(12) DEFAULT NULL,

    -- Academic
    admission_date DATE NOT NULL,
    current_academic_year_id INT UNSIGNED DEFAULT NULL,
    current_class_id INT UNSIGNED DEFAULT NULL,
    current_section_id INT UNSIGNED DEFAULT NULL,

    -- Previous School
    previous_school VARCHAR(255) DEFAULT NULL,
    previous_class VARCHAR(50) DEFAULT NULL,
    transfer_certificate_no VARCHAR(50) DEFAULT NULL,

    -- Status
    status ENUM('active', 'inactive', 'graduated', 'transferred', 'dropped') DEFAULT 'active',

    -- Metadata
    metadata JSON DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    UNIQUE KEY uk_tenant_student_code (tenant_id, student_code),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_status (status),
    INDEX idx_current_class (current_class_id),
    INDEX idx_full_name (full_name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES mt_users(id) ON DELETE SET NULL,
    FOREIGN KEY (current_academic_year_id) REFERENCES mt_academic_years(id) ON DELETE SET NULL,
    FOREIGN KEY (current_class_id) REFERENCES mt_classes(id) ON DELETE SET NULL,
    FOREIGN KEY (current_section_id) REFERENCES mt_sections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. STUDENT GUARDIANS/PARENTS
CREATE TABLE IF NOT EXISTS mt_student_guardians (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,

    -- Relationship
    relationship ENUM('father', 'mother', 'guardian', 'other') NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,

    -- Personal Info
    name VARCHAR(255) NOT NULL,
    occupation VARCHAR(100) DEFAULT NULL,
    qualification VARCHAR(100) DEFAULT NULL,
    annual_income DECIMAL(12, 2) DEFAULT NULL,

    -- Contact
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) NOT NULL,
    alternate_phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,

    -- Documents
    photo_url VARCHAR(500) DEFAULT NULL,
    aadhaar_number VARCHAR(12) DEFAULT NULL,

    -- Emergency contact
    is_emergency_contact BOOLEAN DEFAULT FALSE,

    -- User account (for parent portal)
    user_id INT UNSIGNED DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_id (tenant_id),
    INDEX idx_student_id (student_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES mt_students(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES mt_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. TEACHERS/STAFF
CREATE TABLE IF NOT EXISTS mt_teachers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    teacher_code VARCHAR(20) NOT NULL COMMENT 'Employee ID like TCH001',
    employee_id VARCHAR(50) DEFAULT NULL,

    -- Link to user account
    user_id INT UNSIGNED DEFAULT NULL,

    -- Personal Info
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) NOT NULL,
    full_name VARCHAR(300) GENERATED ALWAYS AS (
        CONCAT_WS(' ', first_name, middle_name, last_name)
    ) STORED,

    date_of_birth DATE DEFAULT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    blood_group VARCHAR(5) DEFAULT NULL,
    marital_status ENUM('single', 'married', 'divorced', 'widowed') DEFAULT NULL,

    -- Contact
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    alternate_phone VARCHAR(20) DEFAULT NULL,
    address_line1 VARCHAR(255) DEFAULT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    country VARCHAR(100) DEFAULT 'India',
    postal_code VARCHAR(20) DEFAULT NULL,

    -- Documents
    photo_url VARCHAR(500) DEFAULT NULL,
    aadhaar_number VARCHAR(12) DEFAULT NULL,
    pan_number VARCHAR(10) DEFAULT NULL,

    -- Employment
    department VARCHAR(100) DEFAULT NULL,
    designation VARCHAR(100) DEFAULT NULL,
    joining_date DATE NOT NULL,
    confirmation_date DATE DEFAULT NULL,
    experience_years DECIMAL(4, 1) DEFAULT 0,
    qualification VARCHAR(255) DEFAULT NULL,
    specialization VARCHAR(255) DEFAULT NULL,

    -- Employment Type
    employment_type ENUM('permanent', 'contract', 'part_time', 'visiting') DEFAULT 'permanent',

    -- Salary
    basic_salary DECIMAL(12, 2) DEFAULT 0.00,
    salary_grade VARCHAR(20) DEFAULT NULL,
    bank_account_no VARCHAR(30) DEFAULT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,
    bank_branch VARCHAR(100) DEFAULT NULL,
    ifsc_code VARCHAR(11) DEFAULT NULL,

    -- Status
    status ENUM('active', 'inactive', 'on_leave', 'resigned', 'terminated') DEFAULT 'active',
    resignation_date DATE DEFAULT NULL,
    exit_date DATE DEFAULT NULL,
    exit_reason TEXT DEFAULT NULL,

    -- Metadata
    metadata JSON DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    UNIQUE KEY uk_tenant_teacher_code (tenant_id, teacher_code),
    UNIQUE KEY uk_tenant_email (tenant_id, email),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_status (status),
    INDEX idx_department (department),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES mt_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. TEACHER-SUBJECT ASSIGNMENT
CREATE TABLE IF NOT EXISTS mt_teacher_subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    class_section_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,

    is_primary BOOLEAN DEFAULT FALSE COMMENT 'Primary teacher for this subject',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_teacher_class_subject (teacher_id, class_section_id, subject_id),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES mt_teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (class_section_id) REFERENCES mt_class_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES mt_subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ATTENDANCE TABLES
-- ============================================================================

-- 11. ATTENDANCE (Students)
CREATE TABLE IF NOT EXISTS mt_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    student_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    class_section_id INT UNSIGNED NOT NULL,

    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'half_day', 'excused', 'holiday') NOT NULL,

    -- Period-wise (optional)
    period_number INT UNSIGNED DEFAULT NULL,
    subject_id INT UNSIGNED DEFAULT NULL,

    remarks TEXT DEFAULT NULL,
    marked_by INT UNSIGNED DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_student_date_period (student_id, attendance_date, period_number),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_attendance_date (attendance_date),
    INDEX idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES mt_students(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES mt_academic_years(id) ON DELETE CASCADE,
    FOREIGN KEY (class_section_id) REFERENCES mt_class_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES mt_subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (marked_by) REFERENCES mt_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. TEACHER ATTENDANCE
CREATE TABLE IF NOT EXISTS mt_teacher_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    teacher_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'half_day', 'on_leave', 'holiday') NOT NULL,

    check_in_time TIME DEFAULT NULL,
    check_out_time TIME DEFAULT NULL,

    leave_type VARCHAR(50) DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    marked_by INT UNSIGNED DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_teacher_date (teacher_id, attendance_date),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_attendance_date (attendance_date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES mt_teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES mt_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- EXAMINATION TABLES
-- ============================================================================

-- 13. EXAM TYPES
CREATE TABLE IF NOT EXISTS mt_exam_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    exam_code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'e.g., Unit Test, Mid Term, Final',
    description TEXT DEFAULT NULL,

    weightage DECIMAL(5, 2) DEFAULT 100.00 COMMENT 'Percentage weightage',

    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_exam_code (tenant_id, exam_code),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. EXAMS
CREATE TABLE IF NOT EXISTS mt_exams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    exam_code VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    exam_type_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,

    start_date DATE NOT NULL,
    end_date DATE NOT NULL,

    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',

    description TEXT DEFAULT NULL,
    instructions TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_exam_code (tenant_id, exam_code),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_academic_year (academic_year_id),
    INDEX idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_type_id) REFERENCES mt_exam_types(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES mt_academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. EXAM SCHEDULES
CREATE TABLE IF NOT EXISTS mt_exam_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    exam_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,

    exam_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration_minutes INT UNSIGNED DEFAULT 180,

    max_marks INT UNSIGNED NOT NULL DEFAULT 100,
    passing_marks INT UNSIGNED NOT NULL DEFAULT 35,

    room_number VARCHAR(20) DEFAULT NULL,
    invigilator_id INT UNSIGNED DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_exam_class_subject (exam_id, class_id, subject_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_exam_date (exam_date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES mt_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES mt_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES mt_subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (invigilator_id) REFERENCES mt_teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. EXAM MARKS/RESULTS
CREATE TABLE IF NOT EXISTS mt_exam_marks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    exam_schedule_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,

    marks_obtained DECIMAL(6, 2) DEFAULT NULL,
    grade VARCHAR(5) DEFAULT NULL,
    grade_points DECIMAL(4, 2) DEFAULT NULL,

    is_absent BOOLEAN DEFAULT FALSE,
    is_pass BOOLEAN DEFAULT NULL,

    remarks TEXT DEFAULT NULL,
    entered_by INT UNSIGNED DEFAULT NULL,
    verified_by INT UNSIGNED DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_schedule_student (exam_schedule_id, student_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_student_id (student_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_schedule_id) REFERENCES mt_exam_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES mt_students(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES mt_users(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES mt_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FEE MANAGEMENT TABLES
-- ============================================================================

-- 17. FEE CATEGORIES
CREATE TABLE IF NOT EXISTS mt_fee_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    category_code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'e.g., Tuition, Transport, Lab',
    description TEXT DEFAULT NULL,

    fee_type ENUM('mandatory', 'optional', 'one_time', 'recurring') DEFAULT 'mandatory',
    frequency ENUM('one_time', 'monthly', 'quarterly', 'half_yearly', 'yearly') DEFAULT 'monthly',

    is_refundable BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_category_code (tenant_id, category_code),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. FEE STRUCTURES
CREATE TABLE IF NOT EXISTS mt_fee_structures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    academic_year_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    fee_category_id INT UNSIGNED NOT NULL,

    amount DECIMAL(12, 2) NOT NULL,
    due_date DATE DEFAULT NULL,
    late_fee DECIMAL(10, 2) DEFAULT 0.00,
    late_fee_type ENUM('fixed', 'percentage', 'per_day') DEFAULT 'fixed',

    description TEXT DEFAULT NULL,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_year_class_category (academic_year_id, class_id, fee_category_id),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES mt_academic_years(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES mt_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_category_id) REFERENCES mt_fee_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. FEE INVOICES
CREATE TABLE IF NOT EXISTS mt_fee_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    invoice_number VARCHAR(50) NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,

    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,

    subtotal DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(12, 2) DEFAULT 0.00,
    late_fee DECIMAL(12, 2) DEFAULT 0.00,
    total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12, 2) DEFAULT 0.00,
    balance_amount DECIMAL(12, 2) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,

    status ENUM('draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',

    notes TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_invoice (tenant_id, invoice_number),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_student_id (student_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES mt_students(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES mt_academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. FEE INVOICE ITEMS
CREATE TABLE IF NOT EXISTS mt_fee_invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    fee_structure_id INT UNSIGNED DEFAULT NULL,
    fee_category_id INT UNSIGNED NOT NULL,

    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    quantity INT DEFAULT 1,
    total DECIMAL(12, 2) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant_id (tenant_id),
    INDEX idx_invoice_id (invoice_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES mt_fee_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_structure_id) REFERENCES mt_fee_structures(id) ON DELETE SET NULL,
    FOREIGN KEY (fee_category_id) REFERENCES mt_fee_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. FEE PAYMENTS
CREATE TABLE IF NOT EXISTS mt_fee_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    payment_number VARCHAR(50) NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,

    payment_date DATE NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,

    payment_method ENUM('cash', 'cheque', 'card', 'bank_transfer', 'upi', 'online') NOT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    cheque_number VARCHAR(50) DEFAULT NULL,
    cheque_date DATE DEFAULT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,

    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'completed',

    receipt_number VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    received_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_payment (tenant_id, payment_number),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_student_id (student_id),
    INDEX idx_payment_date (payment_date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES mt_fee_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES mt_students(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES mt_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TIMETABLE
-- ============================================================================

-- 22. TIMETABLE PERIODS
CREATE TABLE IF NOT EXISTS mt_timetable_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,

    period_number INT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL COMMENT 'e.g., Period 1, Lunch Break',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,

    is_break BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_period (tenant_id, period_number),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. TIMETABLE
CREATE TABLE IF NOT EXISTS mt_timetable (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    academic_year_id INT UNSIGNED NOT NULL,
    class_section_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    period_id INT UNSIGNED NOT NULL,

    day_of_week TINYINT UNSIGNED NOT NULL COMMENT '1=Monday, 7=Sunday',

    room_number VARCHAR(20) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_class_day_period (class_section_id, day_of_week, period_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_teacher_id (teacher_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES mt_academic_years(id) ON DELETE CASCADE,
    FOREIGN KEY (class_section_id) REFERENCES mt_class_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES mt_subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES mt_teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (period_id) REFERENCES mt_timetable_periods(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TRANSPORT
-- ============================================================================

-- 24. VEHICLES
CREATE TABLE IF NOT EXISTS mt_vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    vehicle_code VARCHAR(20) NOT NULL,
    registration_number VARCHAR(20) NOT NULL,
    vehicle_type ENUM('bus', 'van', 'car', 'auto') DEFAULT 'bus',

    make VARCHAR(50) DEFAULT NULL,
    model VARCHAR(50) DEFAULT NULL,
    year INT DEFAULT NULL,
    capacity INT UNSIGNED DEFAULT 40,

    driver_name VARCHAR(100) DEFAULT NULL,
    driver_phone VARCHAR(20) DEFAULT NULL,
    driver_license VARCHAR(50) DEFAULT NULL,

    insurance_number VARCHAR(50) DEFAULT NULL,
    insurance_expiry DATE DEFAULT NULL,
    fitness_expiry DATE DEFAULT NULL,
    permit_expiry DATE DEFAULT NULL,

    gps_enabled BOOLEAN DEFAULT FALSE,
    gps_device_id VARCHAR(50) DEFAULT NULL,

    status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_vehicle_code (tenant_id, vehicle_code),
    UNIQUE KEY uk_tenant_registration (tenant_id, registration_number),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 25. TRANSPORT ROUTES
CREATE TABLE IF NOT EXISTS mt_transport_routes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    route_code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,

    vehicle_id INT UNSIGNED DEFAULT NULL,

    start_location VARCHAR(255) DEFAULT NULL,
    end_location VARCHAR(255) DEFAULT NULL,
    total_distance_km DECIMAL(6, 2) DEFAULT NULL,

    monthly_fee DECIMAL(10, 2) DEFAULT 0.00,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_route_code (tenant_id, route_code),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES mt_vehicles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 26. ROUTE STOPS
CREATE TABLE IF NOT EXISTS mt_route_stops (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    route_id INT UNSIGNED NOT NULL,

    stop_name VARCHAR(100) NOT NULL,
    stop_order INT UNSIGNED NOT NULL,
    pickup_time TIME DEFAULT NULL,
    drop_time TIME DEFAULT NULL,

    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_id (tenant_id),
    INDEX idx_route_id (route_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES mt_transport_routes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 27. STUDENT TRANSPORT
CREATE TABLE IF NOT EXISTS mt_student_transport (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    route_id INT UNSIGNED NOT NULL,
    stop_id INT UNSIGNED DEFAULT NULL,

    transport_type ENUM('pickup', 'drop', 'both') DEFAULT 'both',
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_id (tenant_id),
    INDEX idx_student_id (student_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES mt_students(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES mt_transport_routes(id) ON DELETE CASCADE,
    FOREIGN KEY (stop_id) REFERENCES mt_route_stops(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- HOSTEL
-- ============================================================================

-- 28. HOSTELS
CREATE TABLE IF NOT EXISTS mt_hostels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    hostel_code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('boys', 'girls', 'co_ed') NOT NULL,

    address TEXT DEFAULT NULL,
    warden_id INT UNSIGNED DEFAULT NULL,
    warden_phone VARCHAR(20) DEFAULT NULL,

    total_rooms INT UNSIGNED DEFAULT 0,
    total_beds INT UNSIGNED DEFAULT 0,

    monthly_fee DECIMAL(10, 2) DEFAULT 0.00,
    mess_fee DECIMAL(10, 2) DEFAULT 0.00,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_hostel_code (tenant_id, hostel_code),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (warden_id) REFERENCES mt_teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 29. HOSTEL ROOMS
CREATE TABLE IF NOT EXISTS mt_hostel_rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,
    hostel_id INT UNSIGNED NOT NULL,

    room_number VARCHAR(20) NOT NULL,
    room_type ENUM('single', 'double', 'triple', 'dormitory') DEFAULT 'double',
    floor_number INT DEFAULT 0,
    capacity INT UNSIGNED NOT NULL,
    occupied INT UNSIGNED DEFAULT 0,

    has_ac BOOLEAN DEFAULT FALSE,
    has_bathroom BOOLEAN DEFAULT TRUE,

    monthly_fee DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Override hostel fee if set',

    status ENUM('available', 'full', 'maintenance') DEFAULT 'available',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_hostel_room (hostel_id, room_number),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (hostel_id) REFERENCES mt_hostels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 30. HOSTEL ALLOCATIONS
CREATE TABLE IF NOT EXISTS mt_hostel_allocations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    student_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NOT NULL,
    bed_number INT UNSIGNED DEFAULT NULL,

    academic_year_id INT UNSIGNED NOT NULL,
    allocation_date DATE NOT NULL,
    vacate_date DATE DEFAULT NULL,

    status ENUM('active', 'vacated', 'transferred') DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_id (tenant_id),
    INDEX idx_student_id (student_id),
    INDEX idx_room_id (room_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES mt_students(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES mt_hostel_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES mt_academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- LIBRARY
-- ============================================================================

-- 31. LIBRARY BOOKS
CREATE TABLE IF NOT EXISTS mt_library_books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    book_code VARCHAR(20) NOT NULL COMMENT 'Accession number',
    isbn VARCHAR(20) DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    publisher VARCHAR(255) DEFAULT NULL,
    edition VARCHAR(50) DEFAULT NULL,
    publication_year INT DEFAULT NULL,

    category VARCHAR(100) DEFAULT NULL,
    subject VARCHAR(100) DEFAULT NULL,
    language VARCHAR(50) DEFAULT 'English',

    total_copies INT UNSIGNED DEFAULT 1,
    available_copies INT UNSIGNED DEFAULT 1,

    rack_number VARCHAR(20) DEFAULT NULL,
    shelf_number VARCHAR(20) DEFAULT NULL,

    price DECIMAL(10, 2) DEFAULT 0.00,
    cover_image_url VARCHAR(500) DEFAULT NULL,

    status ENUM('available', 'all_issued', 'lost', 'damaged') DEFAULT 'available',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_book_code (tenant_id, book_code),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_title (title),
    INDEX idx_author (author),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 32. BOOK ISSUES
CREATE TABLE IF NOT EXISTS mt_book_issues (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    book_id INT UNSIGNED NOT NULL,
    issued_to_type ENUM('student', 'teacher', 'staff') NOT NULL,
    issued_to_id INT UNSIGNED NOT NULL,

    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE DEFAULT NULL,

    fine_amount DECIMAL(10, 2) DEFAULT 0.00,
    fine_paid BOOLEAN DEFAULT FALSE,

    status ENUM('issued', 'returned', 'overdue', 'lost') DEFAULT 'issued',

    issued_by INT UNSIGNED DEFAULT NULL,
    received_by INT UNSIGNED DEFAULT NULL,

    remarks TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_id (tenant_id),
    INDEX idx_book_id (book_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES mt_library_books(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES mt_users(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES mt_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PAYROLL
-- ============================================================================

-- 33. SALARY COMPONENTS
CREATE TABLE IF NOT EXISTS mt_salary_components (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    component_code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'e.g., Basic, HRA, DA, PF',
    component_type ENUM('earning', 'deduction') NOT NULL,

    calculation_type ENUM('fixed', 'percentage_of_basic', 'percentage_of_gross') DEFAULT 'fixed',
    default_value DECIMAL(12, 2) DEFAULT 0.00,
    percentage DECIMAL(5, 2) DEFAULT NULL,

    is_taxable BOOLEAN DEFAULT TRUE,
    is_mandatory BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_component_code (tenant_id, component_code),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 34. TEACHER SALARY STRUCTURE
CREATE TABLE IF NOT EXISTS mt_teacher_salary_structure (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    component_id INT UNSIGNED NOT NULL,

    amount DECIMAL(12, 2) NOT NULL,

    effective_from DATE NOT NULL,
    effective_to DATE DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_id (tenant_id),
    INDEX idx_teacher_id (teacher_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES mt_teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES mt_salary_components(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 35. PAYROLL
CREATE TABLE IF NOT EXISTS mt_payroll (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    payroll_code VARCHAR(20) NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,

    pay_month INT UNSIGNED NOT NULL COMMENT '1-12',
    pay_year INT UNSIGNED NOT NULL,

    working_days INT UNSIGNED DEFAULT 0,
    present_days INT UNSIGNED DEFAULT 0,
    leave_days INT UNSIGNED DEFAULT 0,

    gross_salary DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_earnings DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_deductions DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    net_salary DECIMAL(12, 2) NOT NULL DEFAULT 0.00,

    payment_date DATE DEFAULT NULL,
    payment_method ENUM('bank_transfer', 'cheque', 'cash') DEFAULT 'bank_transfer',
    transaction_id VARCHAR(100) DEFAULT NULL,

    status ENUM('draft', 'processed', 'approved', 'paid', 'cancelled') DEFAULT 'draft',

    approved_by INT UNSIGNED DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_payroll_code (tenant_id, payroll_code),
    UNIQUE KEY uk_teacher_month_year (teacher_id, pay_month, pay_year),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES mt_teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES mt_users(id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES mt_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 36. PAYROLL DETAILS
CREATE TABLE IF NOT EXISTS mt_payroll_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    payroll_id INT UNSIGNED NOT NULL,
    component_id INT UNSIGNED NOT NULL,

    component_type ENUM('earning', 'deduction') NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant_id (tenant_id),
    INDEX idx_payroll_id (payroll_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_id) REFERENCES mt_payroll(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES mt_salary_components(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- EVENTS & CALENDAR
-- ============================================================================

-- 37. EVENTS
CREATE TABLE IF NOT EXISTS mt_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    event_code VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,

    event_type ENUM('holiday', 'exam', 'meeting', 'sports', 'cultural', 'other') DEFAULT 'other',

    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    is_all_day BOOLEAN DEFAULT TRUE,

    location VARCHAR(255) DEFAULT NULL,

    -- Visibility
    is_public BOOLEAN DEFAULT TRUE,
    visible_to JSON DEFAULT NULL COMMENT 'Roles/classes that can see this event',

    -- Recurring
    is_recurring BOOLEAN DEFAULT FALSE,
    recurrence_rule VARCHAR(255) DEFAULT NULL,

    color VARCHAR(7) DEFAULT '#3b82f6',
    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_event_code (tenant_id, event_code),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_start_date (start_date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES mt_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 38. NOTICES/ANNOUNCEMENTS
CREATE TABLE IF NOT EXISTS mt_notices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED NOT NULL,

    notice_code VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,

    notice_type ENUM('general', 'academic', 'administrative', 'urgent') DEFAULT 'general',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',

    publish_date DATE NOT NULL,
    expiry_date DATE DEFAULT NULL,

    -- Visibility
    is_public BOOLEAN DEFAULT FALSE,
    visible_to JSON DEFAULT NULL COMMENT 'Roles/classes that can see this notice',

    -- Attachments
    attachments JSON DEFAULT NULL,

    is_pinned BOOLEAN DEFAULT FALSE,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_tenant_notice_code (tenant_id, notice_code),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_publish_date (publish_date),
    INDEX idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES mt_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- END OF MIGRATION 003
-- ============================================================================
