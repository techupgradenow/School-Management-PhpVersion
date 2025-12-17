-- ========================================
-- EduManage Pro - Database Schema
-- School Management System
-- ========================================

-- Drop existing database if exists
DROP DATABASE IF EXISTS edumanage_pro;

-- Create database
CREATE DATABASE edumanage_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edumanage_pro;

-- ========================================
-- USERS & AUTHENTICATION
-- ========================================

CREATE TABLE users (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('SuperAdmin', 'Admin', 'Teacher', 'Student', 'Parent') NOT NULL DEFAULT 'Admin',
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    last_login DATETIME NULL,
    permissions JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default users
INSERT INTO users (id, name, username, email, password, role, status) VALUES
('USR000', 'Super Admin', 'superadmin', 'superadmin@edumanage.edu', 'super@123', 'SuperAdmin', 'Active'),
('USR001', 'School Admin', 'admin', 'admin@edumanage.edu', 'admin123', 'Admin', 'Active');

-- ========================================
-- STUDENTS
-- ========================================

CREATE TABLE students (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    class VARCHAR(50) NOT NULL,
    section VARCHAR(10) NOT NULL,
    parent_name VARCHAR(255) NOT NULL,
    contact VARCHAR(50) NOT NULL,
    email VARCHAR(255) NULL,
    address TEXT NULL,
    dob DATE NULL,
    joining_date DATE NULL,
    blood_group VARCHAR(10) NULL,
    photo TEXT NULL,
    status ENUM('Active', 'Inactive', 'Graduated', 'Transferred') NOT NULL DEFAULT 'Active',
    admission_no VARCHAR(100) UNIQUE NULL,
    roll_no VARCHAR(50) NULL,
    nationality VARCHAR(100) DEFAULT 'Indian',
    religion VARCHAR(100) NULL,
    category VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_class (class),
    INDEX idx_section (section),
    INDEX idx_status (status),
    INDEX idx_name (name),
    INDEX idx_admission_no (admission_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- STUDENT DOCUMENTS
-- ========================================

CREATE TABLE student_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_data LONGTEXT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TEACHERS
-- ========================================

CREATE TABLE teachers (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    contact VARCHAR(50) NOT NULL,
    email VARCHAR(255) NULL,
    address TEXT NULL,
    qualification VARCHAR(255) NULL,
    experience INT NULL,
    joining_date DATE NULL,
    salary DECIMAL(10,2) NULL,
    photo TEXT NULL,
    status ENUM('Active', 'Inactive', 'Resigned') NOT NULL DEFAULT 'Active',
    employee_id VARCHAR(100) UNIQUE NULL,
    department VARCHAR(100) NULL,
    designation VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subject (subject),
    INDEX idx_status (status),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- CLASSES & SECTIONS
-- ========================================

CREATE TABLE classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_name VARCHAR(50) NOT NULL,
    class_teacher_id VARCHAR(50) NULL,
    capacity INT DEFAULT 40,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    UNIQUE KEY unique_class (class_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    section_name VARCHAR(10) NOT NULL,
    capacity INT DEFAULT 40,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_section (class_id, section_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- SUBJECTS
-- ========================================

CREATE TABLE subjects (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    class VARCHAR(50) NOT NULL,
    teacher_id VARCHAR(50) NULL,
    description TEXT NULL,
    max_marks INT DEFAULT 100,
    pass_marks INT DEFAULT 33,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    INDEX idx_class (class),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- ATTENDANCE
-- ========================================

CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Half Day', 'Leave') NOT NULL,
    remarks TEXT NULL,
    marked_by VARCHAR(50) NULL,
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (student_id, date),
    INDEX idx_date (date),
    INDEX idx_student (student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- EXAMS
-- ========================================

CREATE TABLE exams (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    class VARCHAR(50) NOT NULL,
    subject_id VARCHAR(50) NOT NULL,
    exam_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    max_marks INT NOT NULL DEFAULT 100,
    pass_marks INT NOT NULL DEFAULT 33,
    description TEXT NULL,
    status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    INDEX idx_class (class),
    INDEX idx_date (exam_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE exam_marks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id VARCHAR(50) NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    marks_obtained DECIMAL(5,2) NOT NULL,
    remarks TEXT NULL,
    entered_by VARCHAR(50) NULL,
    entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_exam_student (exam_id, student_id),
    INDEX idx_exam (exam_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- ADMIT CARDS
-- ========================================

CREATE TABLE admit_cards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(50) NOT NULL,
    exam_id VARCHAR(50) NOT NULL,
    admit_card_no VARCHAR(100) UNIQUE NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_admit_card (student_id, exam_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TIMETABLE
-- ========================================

CREATE TABLE timetable (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class VARCHAR(50) NOT NULL,
    section VARCHAR(10) NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    period_no INT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    subject_id VARCHAR(50) NOT NULL,
    teacher_id VARCHAR(50) NULL,
    room_no VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    UNIQUE KEY unique_timetable (class, section, day_of_week, period_no),
    INDEX idx_class_section (class, section),
    INDEX idx_day (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- FEES MANAGEMENT
-- ========================================

CREATE TABLE fee_structures (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class VARCHAR(50) NOT NULL,
    fee_type VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    frequency ENUM('Monthly', 'Quarterly', 'Annually', 'One Time') NOT NULL DEFAULT 'Monthly',
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_class (class),
    INDEX idx_type (fee_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fee_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(50) NOT NULL,
    fee_structure_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_mode ENUM('Cash', 'Cheque', 'Online', 'Card') NOT NULL DEFAULT 'Cash',
    transaction_id VARCHAR(255) NULL,
    receipt_no VARCHAR(100) UNIQUE NOT NULL,
    remarks TEXT NULL,
    collected_by VARCHAR(50) NULL,
    status ENUM('Paid', 'Pending', 'Overdue', 'Partial') NOT NULL DEFAULT 'Paid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id) ON DELETE CASCADE,
    FOREIGN KEY (collected_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student (student_id),
    INDEX idx_date (payment_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- LIBRARY
-- ========================================

CREATE TABLE library_books (
    id VARCHAR(50) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(50) UNIQUE NULL,
    category VARCHAR(100) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    available INT NOT NULL DEFAULT 1,
    publisher VARCHAR(255) NULL,
    publication_year INT NULL,
    language VARCHAR(50) DEFAULT 'English',
    shelf_no VARCHAR(50) NULL,
    price DECIMAL(10,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_title (title),
    INDEX idx_author (author),
    INDEX idx_category (category),
    INDEX idx_isbn (isbn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE library_issues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    book_id VARCHAR(50) NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    status ENUM('Issued', 'Returned', 'Overdue', 'Lost') NOT NULL DEFAULT 'Issued',
    fine_amount DECIMAL(10,2) DEFAULT 0.00,
    remarks TEXT NULL,
    issued_by VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES library_books(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_book (book_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_dates (issue_date, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TRANSPORT
-- ========================================

CREATE TABLE transport_routes (
    id VARCHAR(50) PRIMARY KEY,
    route_name VARCHAR(255) NOT NULL,
    route_no VARCHAR(50) UNIQUE NOT NULL,
    vehicle_no VARCHAR(50) NOT NULL,
    driver_name VARCHAR(255) NOT NULL,
    driver_contact VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    fare DECIMAL(10,2) NOT NULL,
    status ENUM('Active', 'Inactive', 'Maintenance') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_route_no (route_no),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transport_stops (
    id INT PRIMARY KEY AUTO_INCREMENT,
    route_id VARCHAR(50) NOT NULL,
    stop_name VARCHAR(255) NOT NULL,
    stop_order INT NOT NULL,
    pickup_time TIME NOT NULL,
    drop_time TIME NOT NULL,
    FOREIGN KEY (route_id) REFERENCES transport_routes(id) ON DELETE CASCADE,
    INDEX idx_route (route_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transport_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(50) NOT NULL,
    route_id VARCHAR(50) NOT NULL,
    stop_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES transport_routes(id) ON DELETE CASCADE,
    FOREIGN KEY (stop_id) REFERENCES transport_stops(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_route (route_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- HOSTEL
-- ========================================

CREATE TABLE hostel_blocks (
    id VARCHAR(50) PRIMARY KEY,
    block_name VARCHAR(255) NOT NULL,
    block_type ENUM('Boys', 'Girls', 'Mixed') NOT NULL,
    total_rooms INT NOT NULL,
    warden_name VARCHAR(255) NULL,
    warden_contact VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (block_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hostel_rooms (
    id VARCHAR(50) PRIMARY KEY,
    block_id VARCHAR(50) NOT NULL,
    room_no VARCHAR(50) NOT NULL,
    room_type VARCHAR(100) NOT NULL,
    capacity INT NOT NULL,
    occupied INT DEFAULT 0,
    floor INT NOT NULL,
    monthly_fee DECIMAL(10,2) NOT NULL,
    status ENUM('Available', 'Full', 'Under Maintenance') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (block_id) REFERENCES hostel_blocks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room (block_id, room_no),
    INDEX idx_block (block_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hostel_allocations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(50) NOT NULL,
    room_id VARCHAR(50) NOT NULL,
    allocation_date DATE NOT NULL,
    checkout_date DATE NULL,
    status ENUM('Active', 'Checked Out', 'Transferred') NOT NULL DEFAULT 'Active',
    remarks TEXT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES hostel_rooms(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_room (room_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- NOTIFICATIONS
-- ========================================

CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') NOT NULL DEFAULT 'info',
    icon VARCHAR(50) DEFAULT 'fa-bell',
    target_role VARCHAR(50) NULL,
    target_user_id VARCHAR(50) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_target_user (target_user_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- ACTIVITY LOGS
-- ========================================

CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(50) NULL,
    action VARCHAR(255) NOT NULL,
    module VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_module (module),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- SYSTEM SETTINGS
-- ========================================

CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    setting_type VARCHAR(50) DEFAULT 'text',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO system_settings (setting_key, setting_value, setting_type) VALUES
('school_name', 'EduManage Pro School', 'text'),
('school_email', 'admin@edumanage.pro', 'email'),
('school_phone', '+91 9876543210', 'text'),
('school_address', '123 Education Street, City, Country', 'textarea'),
('academic_year', '2023-2024', 'text'),
('currency', 'INR', 'text'),
('currency_symbol', '₹', 'text'),
('timezone', 'Asia/Kolkata', 'text');

-- ========================================
-- END OF SCHEMA
-- ========================================
