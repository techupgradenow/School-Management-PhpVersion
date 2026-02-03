<?php
require_once __DIR__ . '/../backend/config/env.php';

$pdo = new PDO(getDbDsn(), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

echo "=== CREATING ALL TABLES ON HOSTINGER ===\n\n";

$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS `users` (
        `id` VARCHAR(50) PRIMARY KEY,
        `uuid` CHAR(36) NULL,
        `name` VARCHAR(255) NOT NULL,
        `username` VARCHAR(100) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('SuperAdmin', 'Admin', 'Teacher', 'Student', 'Parent') NOT NULL DEFAULT 'Admin',
        `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
        `last_login` DATETIME NULL,
        `permissions` JSON NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "teachers" => "CREATE TABLE IF NOT EXISTS `teachers` (
        `id` VARCHAR(50) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `contact` VARCHAR(50) NOT NULL,
        `email` VARCHAR(255) NULL,
        `address` TEXT NULL,
        `qualification` VARCHAR(255) NULL,
        `experience` INT NULL,
        `joining_date` DATE NULL,
        `salary` DECIMAL(10,2) NULL,
        `photo` TEXT NULL,
        `status` ENUM('Active', 'Inactive', 'Resigned') NOT NULL DEFAULT 'Active',
        `employee_id` VARCHAR(100) NULL,
        `department` VARCHAR(100) NULL,
        `designation` VARCHAR(100) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "students" => "CREATE TABLE IF NOT EXISTS `students` (
        `id` VARCHAR(50) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
        `class` VARCHAR(50) NOT NULL,
        `section` VARCHAR(10) NOT NULL,
        `parent_name` VARCHAR(255) NOT NULL,
        `contact` VARCHAR(50) NOT NULL,
        `email` VARCHAR(255) NULL,
        `address` TEXT NULL,
        `dob` DATE NULL,
        `joining_date` DATE NULL,
        `blood_group` VARCHAR(10) NULL,
        `photo` TEXT NULL,
        `status` ENUM('Active', 'Inactive', 'Graduated', 'Transferred') NOT NULL DEFAULT 'Active',
        `admission_no` VARCHAR(100) NULL,
        `roll_no` VARCHAR(50) NULL,
        `nationality` VARCHAR(100) DEFAULT 'Indian',
        `religion` VARCHAR(100) NULL,
        `category` VARCHAR(50) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "classes" => "CREATE TABLE IF NOT EXISTS `classes` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `class_name` VARCHAR(50) NOT NULL,
        `class_teacher_id` VARCHAR(50) NULL,
        `capacity` INT DEFAULT 40,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "subjects" => "CREATE TABLE IF NOT EXISTS `subjects` (
        `id` VARCHAR(50) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `code` VARCHAR(50) NOT NULL,
        `class` VARCHAR(50) NOT NULL,
        `teacher_id` VARCHAR(50) NULL,
        `description` TEXT NULL,
        `max_marks` INT DEFAULT 100,
        `pass_marks` INT DEFAULT 33,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "attendance" => "CREATE TABLE IF NOT EXISTS `attendance` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `student_id` VARCHAR(50) NOT NULL,
        `date` DATE NOT NULL,
        `status` ENUM('Present', 'Absent', 'Late', 'Half Day', 'Leave') NOT NULL,
        `remarks` TEXT NULL,
        `marked_by` VARCHAR(50) NULL,
        `marked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "exams" => "CREATE TABLE IF NOT EXISTS `exams` (
        `id` VARCHAR(50) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `class` VARCHAR(50) NOT NULL,
        `subject` VARCHAR(100) NULL,
        `subject_id` VARCHAR(50) NULL,
        `exam_date` DATE NOT NULL,
        `start_time` TIME NULL,
        `end_time` TIME NULL,
        `max_marks` INT NOT NULL DEFAULT 100,
        `pass_marks` INT NOT NULL DEFAULT 33,
        `description` TEXT NULL,
        `status` ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Scheduled',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "timetable" => "CREATE TABLE IF NOT EXISTS `timetable` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `class` VARCHAR(50) NOT NULL,
        `section` VARCHAR(10) NOT NULL,
        `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
        `period_no` INT NOT NULL,
        `start_time` TIME NOT NULL,
        `end_time` TIME NOT NULL,
        `subject_id` VARCHAR(50) NULL,
        `subject` VARCHAR(100) NULL,
        `teacher_id` VARCHAR(50) NULL,
        `room_no` VARCHAR(50) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "fee_structures" => "CREATE TABLE IF NOT EXISTS `fee_structures` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `class` VARCHAR(50) NOT NULL,
        `fee_type` VARCHAR(100) NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `frequency` ENUM('Monthly', 'Quarterly', 'Annually', 'One Time') NOT NULL DEFAULT 'Monthly',
        `description` TEXT NULL,
        `is_active` BOOLEAN DEFAULT TRUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "library_books" => "CREATE TABLE IF NOT EXISTS `library_books` (
        `id` VARCHAR(50) PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `author` VARCHAR(255) NOT NULL,
        `isbn` VARCHAR(50) NULL,
        `category` VARCHAR(100) NOT NULL,
        `quantity` INT NOT NULL DEFAULT 1,
        `available` INT NOT NULL DEFAULT 1,
        `publisher` VARCHAR(255) NULL,
        `publication_year` INT NULL,
        `language` VARCHAR(50) DEFAULT 'English',
        `shelf_no` VARCHAR(50) NULL,
        `price` DECIMAL(10,2) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "transport_routes" => "CREATE TABLE IF NOT EXISTS `transport_routes` (
        `id` VARCHAR(50) PRIMARY KEY,
        `route_name` VARCHAR(255) NOT NULL,
        `route_no` VARCHAR(50) NOT NULL,
        `vehicle_no` VARCHAR(50) NOT NULL,
        `driver_name` VARCHAR(255) NOT NULL,
        `driver_contact` VARCHAR(50) NOT NULL,
        `capacity` INT NOT NULL,
        `fare` DECIMAL(10,2) NOT NULL,
        `status` ENUM('Active', 'Inactive', 'Maintenance') NOT NULL DEFAULT 'Active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "hostel_blocks" => "CREATE TABLE IF NOT EXISTS `hostel_blocks` (
        `id` VARCHAR(50) PRIMARY KEY,
        `block_name` VARCHAR(255) NOT NULL,
        `block_type` ENUM('Boys', 'Girls', 'Mixed') NOT NULL,
        `total_rooms` INT NOT NULL,
        `warden_name` VARCHAR(255) NULL,
        `warden_contact` VARCHAR(50) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "payroll" => "CREATE TABLE IF NOT EXISTS `payroll` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `payroll_id` VARCHAR(20) NOT NULL,
        `employee_id` VARCHAR(20) NOT NULL,
        `employee_name` VARCHAR(100) NOT NULL,
        `designation` VARCHAR(100),
        `pay_period` VARCHAR(7) NOT NULL,
        `basic_salary` DECIMAL(12,2) DEFAULT 0,
        `hra` DECIMAL(12,2) DEFAULT 0,
        `da` DECIMAL(12,2) DEFAULT 0,
        `transport_allowance` DECIMAL(12,2) DEFAULT 0,
        `medical_allowance` DECIMAL(12,2) DEFAULT 0,
        `other_allowances` DECIMAL(12,2) DEFAULT 0,
        `pf` DECIMAL(12,2) DEFAULT 0,
        `professional_tax` DECIMAL(12,2) DEFAULT 0,
        `tds` DECIMAL(12,2) DEFAULT 0,
        `other_deductions` DECIMAL(12,2) DEFAULT 0,
        `leave_days` INT DEFAULT 0,
        `leave_deduction` DECIMAL(12,2) DEFAULT 0,
        `loan_emi` DECIMAL(12,2) DEFAULT 0,
        `gross_earnings` DECIMAL(12,2) DEFAULT 0,
        `total_deductions` DECIMAL(12,2) DEFAULT 0,
        `net_salary` DECIMAL(12,2) DEFAULT 0,
        `payment_mode` VARCHAR(50),
        `status` ENUM('pending', 'processing', 'paid', 'hold') DEFAULT 'pending',
        `paid_date` DATE NULL,
        `remarks` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "notifications" => "CREATE TABLE IF NOT EXISTS `notifications` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `message` TEXT NOT NULL,
        `type` ENUM('info', 'success', 'warning', 'danger', 'error') NOT NULL DEFAULT 'info',
        `icon` VARCHAR(50) DEFAULT 'fa-bell',
        `target_role` VARCHAR(50) NULL,
        `target_user_id` VARCHAR(50) NULL,
        `is_read` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "settings" => "CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `setting_key` VARCHAR(100) NOT NULL,
        `setting_value` TEXT NULL,
        `setting_type` VARCHAR(50) DEFAULT 'text',
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "user_permissions" => "CREATE TABLE IF NOT EXISTS `user_permissions` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `user_uuid` CHAR(36) NOT NULL,
        `module` VARCHAR(50) NOT NULL,
        `can_view` TINYINT(1) DEFAULT 0,
        `can_create` TINYINT(1) DEFAULT 0,
        `can_edit` TINYINT(1) DEFAULT 0,
        `can_delete` TINYINT(1) DEFAULT 0,
        `can_export` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "student_documents" => "CREATE TABLE IF NOT EXISTS `student_documents` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `student_id` VARCHAR(50) NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `type` VARCHAR(100) NOT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `file_type` VARCHAR(100) NOT NULL,
        `file_data` LONGTEXT NOT NULL,
        `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "activity_logs" => "CREATE TABLE IF NOT EXISTS `activity_logs` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `user_id` VARCHAR(50) NULL,
        `action` VARCHAR(255) NOT NULL,
        `module` VARCHAR(100) NOT NULL,
        `details` TEXT NULL,
        `ip_address` VARCHAR(50) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "institution_types" => "CREATE TABLE IF NOT EXISTS `institution_types` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(50) NOT NULL,
        `description` VARCHAR(255) NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "dropdown_categories" => "CREATE TABLE IF NOT EXISTS `dropdown_categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `category_key` VARCHAR(50) NOT NULL,
        `category_name` VARCHAR(100) NOT NULL,
        `institution_type_id` INT NULL,
        `description` VARCHAR(255) NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "syllabus" => "CREATE TABLE IF NOT EXISTS `syllabus` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `syllabus_code` VARCHAR(50) NULL,
        `class` VARCHAR(50) NOT NULL,
        `section` VARCHAR(10) NULL,
        `subject` VARCHAR(100) NOT NULL,
        `academic_year` VARCHAR(20) NOT NULL,
        `board_type` ENUM('CBSE', 'ICSE', 'State Board', 'Other') DEFAULT 'CBSE',
        `medium` ENUM('English', 'Hindi', 'Tamil', 'Telugu', 'Other') DEFAULT 'English',
        `total_marks` INT NULL,
        `total_chapters` INT DEFAULT 0,
        `description` TEXT NULL,
        `status` ENUM('Draft', 'Active', 'Archived') DEFAULT 'Draft',
        `created_by` VARCHAR(50) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "teacher_absences" => "CREATE TABLE IF NOT EXISTS `teacher_absences` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `teacher_id` VARCHAR(50) NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `absence_type` ENUM('SICK_LEAVE', 'CASUAL_LEAVE', 'EMERGENCY', 'TRAINING', 'OTHER') NOT NULL DEFAULT 'OTHER',
        `reason` TEXT,
        `status` ENUM('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
        `approved_by` VARCHAR(50) DEFAULT NULL,
        `approved_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "_migrations" => "CREATE TABLE IF NOT EXISTS `_migrations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(255) NOT NULL,
        `batch` INT NOT NULL,
        `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "  [+] $name\n";
    } catch (PDOException $e) {
        echo "  [!] $name: " . $e->getMessage() . "\n";
    }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// Insert default data
echo "\nInserting default data...\n";

$pdo->exec("INSERT IGNORE INTO users (id, name, username, email, password, role, status) VALUES
    ('USR000', 'Super Admin', 'superadmin', 'superadmin@edumanage.edu', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SuperAdmin', 'Active'),
    ('USR001', 'School Admin', 'admin', 'admin@edumanage.edu', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Active')");
echo "  [+] Default users\n";

$pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('school_name', 'EduManage Pro School'),
    ('academic_year', '2024-2025'),
    ('institution_type', 'School')");
echo "  [+] Default settings\n";

$pdo->exec("INSERT IGNORE INTO institution_types (name, description) VALUES
    ('School', 'K-12 School'),
    ('College', 'Higher Education')");
echo "  [+] Institution types\n";

// Show all tables
echo "\n=== ALL TABLES CREATED ===\n\n";
$result = $pdo->query('SHOW TABLES');
$count = 0;
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $count++;
    echo "  $count. {$row[0]}\n";
}
echo "\nTotal: $count tables\n";
echo "\n=== MIGRATION COMPLETE ===\n";
?>
