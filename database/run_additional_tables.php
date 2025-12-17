<?php
/**
 * Run Additional Tables Script
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'edumanage_pro';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Connected to database successfully.\n";

    // Create notifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
            icon VARCHAR(50) DEFAULT 'fa-bell',
            target_user_id INT NULL,
            target_role VARCHAR(50) NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Created notifications table.\n";

    // Create activity_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(255) NOT NULL,
            module VARCHAR(100),
            details JSON,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Created activity_logs table.\n";

    // Create settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            category VARCHAR(50) DEFAULT 'general',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "Created settings table.\n";

    // Create periods table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS periods (
            id INT AUTO_INCREMENT PRIMARY KEY,
            period_number INT NOT NULL UNIQUE,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            name VARCHAR(50),
            is_break TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Created periods table.\n";

    // Create timetable table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS timetable (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class VARCHAR(50) NOT NULL,
            section VARCHAR(10) NOT NULL,
            day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
            period_number INT NOT NULL,
            subject_id INT,
            teacher_id VARCHAR(20),
            room VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_slot (class, section, day_of_week, period_number)
        )
    ");
    echo "Created timetable table.\n";

    // Insert default periods
    $pdo->exec("
        INSERT IGNORE INTO periods (period_number, start_time, end_time, name, is_break) VALUES
        (1, '08:00:00', '08:45:00', 'Period 1', 0),
        (2, '08:45:00', '09:30:00', 'Period 2', 0),
        (3, '09:30:00', '10:15:00', 'Period 3', 0),
        (4, '10:15:00', '10:30:00', 'Short Break', 1),
        (5, '10:30:00', '11:15:00', 'Period 4', 0),
        (6, '11:15:00', '12:00:00', 'Period 5', 0),
        (7, '12:00:00', '12:45:00', 'Lunch Break', 1),
        (8, '12:45:00', '13:30:00', 'Period 6', 0),
        (9, '13:30:00', '14:15:00', 'Period 7', 0),
        (10, '14:15:00', '15:00:00', 'Period 8', 0)
    ");
    echo "Inserted default periods.\n";

    // Insert default settings
    $pdo->exec("
        INSERT IGNORE INTO settings (setting_key, setting_value, category) VALUES
        ('school_name', 'EduManage Pro School', 'school'),
        ('school_address', '123 Education Street, City, Country', 'school'),
        ('school_phone', '+91 1234567890', 'school'),
        ('school_email', 'info@edumanagepro.com', 'school'),
        ('school_website', 'www.edumanagepro.com', 'school'),
        ('principal_name', 'Dr. John Smith', 'school'),
        ('established_year', '2000', 'school'),
        ('academic_year', '2024-2025', 'academic'),
        ('session_start', '2024-04-01', 'academic'),
        ('session_end', '2025-03-31', 'academic'),
        ('grading_system', 'percentage', 'academic'),
        ('pass_percentage', '33', 'academic'),
        ('working_days', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 'academic'),
        ('currency', 'INR', 'general'),
        ('currency_symbol', '₹', 'general'),
        ('date_format', 'd-m-Y', 'general'),
        ('time_format', 'h:i A', 'general')
    ");
    echo "Inserted default settings.\n";

    // Insert sample notifications
    $pdo->exec("
        INSERT IGNORE INTO notifications (title, message, type, icon) VALUES
        ('Welcome to EduManage Pro', 'Your school management system is ready to use.', 'success', 'fa-check-circle'),
        ('New Academic Year', 'Academic year 2024-2025 has started.', 'info', 'fa-calendar'),
        ('Fee Reminder', 'Fee collection for Q1 is in progress.', 'warning', 'fa-exclamation-triangle')
    ");
    echo "Inserted sample notifications.\n";

    // Create subjects table if not exists (INT id version for compatibility)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) NOT NULL UNIQUE,
            class VARCHAR(50) NULL,
            description TEXT NULL,
            status ENUM('Active', 'Inactive') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Created subjects table.\n";

    // Insert sample subjects
    $pdo->exec("
        INSERT IGNORE INTO subjects (name, code, class, description, status) VALUES
        ('Mathematics', 'MATH', NULL, 'Core mathematics subject', 'Active'),
        ('English', 'ENG', NULL, 'English language and literature', 'Active'),
        ('Science', 'SCI', NULL, 'General science for classes 1-8', 'Active'),
        ('Physics', 'PHY', NULL, 'Physics for classes 9-12', 'Active'),
        ('Chemistry', 'CHEM', NULL, 'Chemistry for classes 9-12', 'Active'),
        ('Biology', 'BIO', NULL, 'Biology for classes 9-12', 'Active'),
        ('Social Studies', 'SST', NULL, 'History, Geography and Civics', 'Active'),
        ('Computer Science', 'CS', NULL, 'Computer fundamentals and programming', 'Active'),
        ('Physical Education', 'PE', NULL, 'Sports and physical fitness', 'Active'),
        ('Hindi', 'HINDI', NULL, 'Hindi language', 'Active')
    ");
    echo "Inserted sample subjects.\n";

    // Insert sample exams
    $pdo->exec("
        INSERT IGNORE INTO exams (id, name, class, subject_id, exam_date, start_time, end_time, max_marks, pass_marks, description, status) VALUES
        ('EXM001', 'Mid Term Examination - Mathematics', 'Class 10', 1, '2025-12-20', '09:00:00', '12:00:00', 100, 33, 'Mid term exam for Mathematics', 'Scheduled'),
        ('EXM002', 'Mid Term Examination - English', 'Class 10', 2, '2025-12-21', '09:00:00', '12:00:00', 100, 33, 'Mid term exam for English', 'Scheduled'),
        ('EXM003', 'Mid Term Examination - Science', 'Class 10', 3, '2025-12-22', '09:00:00', '12:00:00', 100, 33, 'Mid term exam for Science', 'Scheduled'),
        ('EXM004', 'Unit Test 1 - Physics', 'Class 12', 4, '2025-11-25', '10:00:00', '11:30:00', 50, 17, 'First unit test for Physics', 'Completed'),
        ('EXM005', 'Quarterly Exam - Chemistry', 'Class 11', 5, '2025-12-05', '09:00:00', '12:00:00', 100, 33, 'Quarterly exam for Chemistry', 'Scheduled'),
        ('EXM006', 'Final Exam - Computer Science', 'Class 9', 8, '2026-03-15', '09:00:00', '11:00:00', 100, 33, 'Annual exam for Computer Science', 'Scheduled')
    ");
    echo "Inserted sample exams.\n";

    echo "\n=== All tables created successfully! ===\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
