<?php
/**
 * Institution Setup Database Migration
 * EduManage Pro - School/College Management System
 *
 * Run this script to create institution and dropdown management tables
 */

echo "=== Institution Setup Database Migration ===\n\n";

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=edumanage_pro;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "Connected to database successfully.\n\n";

    // Create institution_types table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS institution_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description VARCHAR(255) NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Created institution_types table.\n";

    // Insert default institution types
    $pdo->exec("
        INSERT IGNORE INTO institution_types (name, description) VALUES
        ('School', 'Primary and Secondary Education Institution'),
        ('College', 'Higher Education Institution')
    ");
    echo "Inserted institution types.\n";

    // Create institution_settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS institution_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "Created institution_settings table.\n";

    // Insert default settings
    $pdo->exec("
        INSERT INTO institution_settings (setting_key, setting_value) VALUES
        ('institution_type', 'School'),
        ('institution_name', 'EduManage Pro'),
        ('institution_address', ''),
        ('institution_phone', ''),
        ('institution_email', ''),
        ('institution_logo', ''),
        ('academic_year', '2024-2025')
        ON DUPLICATE KEY UPDATE setting_key = setting_key
    ");
    echo "Inserted default settings.\n";

    // Create dropdown_categories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dropdown_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_key VARCHAR(50) NOT NULL,
            category_name VARCHAR(100) NOT NULL,
            institution_type_id INT NULL,
            description VARCHAR(255) NULL,
            is_system TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_category (category_key, institution_type_id)
        )
    ");
    echo "Created dropdown_categories table.\n";

    // Insert dropdown categories for School (type_id = 1)
    $pdo->exec("
        INSERT IGNORE INTO dropdown_categories (category_key, category_name, institution_type_id, description, is_system) VALUES
        ('class', 'Class', 1, 'Academic classes for school', 1),
        ('section', 'Section', 1, 'Class sections', 1),
        ('subject', 'Subject', 1, 'Academic subjects for school', 1)
    ");
    echo "Inserted school categories.\n";

    // Insert common categories (no institution type)
    $pdo->exec("
        INSERT IGNORE INTO dropdown_categories (category_key, category_name, institution_type_id, description, is_system) VALUES
        ('gender', 'Gender', NULL, 'Gender options', 1),
        ('blood_group', 'Blood Group', NULL, 'Blood group options', 1),
        ('religion', 'Religion', NULL, 'Religion options', 1),
        ('category', 'Category', NULL, 'Student category', 1),
        ('designation', 'Designation', NULL, 'Staff designation', 1),
        ('fee_type', 'Fee Type', NULL, 'Types of fees', 1),
        ('payment_mode', 'Payment Mode', NULL, 'Payment methods', 1),
        ('exam_type', 'Exam Type', NULL, 'Types of examinations', 1),
        ('grade', 'Grade', NULL, 'Grading system', 1),
        ('status', 'Status', NULL, 'Active/Inactive status', 1)
    ");
    echo "Inserted common categories.\n";

    // Insert dropdown categories for College (type_id = 2)
    $pdo->exec("
        INSERT IGNORE INTO dropdown_categories (category_key, category_name, institution_type_id, description, is_system) VALUES
        ('department', 'Department', 2, 'Academic departments for college', 1),
        ('course', 'Course', 2, 'Courses/Programs offered', 1),
        ('semester', 'Semester', 2, 'Academic semesters', 1),
        ('subject_college', 'Subject', 2, 'Academic subjects for college', 1),
        ('faculty', 'Faculty', 2, 'Faculty members', 1),
        ('degree', 'Degree', 2, 'Degree types', 1),
        ('specialization', 'Specialization', 2, 'Course specializations', 1)
    ");
    echo "Inserted college categories.\n";

    // Create dropdown_values table
    $pdo->exec("
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
            UNIQUE KEY unique_value (category_id, value)
        )
    ");
    echo "Created dropdown_values table.\n";

    // Get category IDs
    $categories = [];
    $stmt = $pdo->query("SELECT id, category_key, institution_type_id FROM dropdown_categories");
    while ($row = $stmt->fetch()) {
        $key = $row['category_key'] . '_' . ($row['institution_type_id'] ?? 'null');
        $categories[$key] = $row['id'];
    }

    // Insert School Classes
    $classId = $categories['class_1'] ?? null;
    if ($classId) {
        $classes = ['Nursery', 'LKG', 'UKG', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5',
                    'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($classes as $i => $class) {
            $stmt->execute([$classId, $class, $i + 1]);
        }
        echo "Inserted school classes.\n";
    }

    // Insert Sections
    $sectionId = $categories['section_1'] ?? null;
    if ($sectionId) {
        $sections = ['A', 'B', 'C', 'D', 'E'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($sections as $i => $section) {
            $stmt->execute([$sectionId, $section, $i + 1]);
        }
        echo "Inserted sections.\n";
    }

    // Insert School Subjects
    $subjectId = $categories['subject_1'] ?? null;
    if ($subjectId) {
        $subjects = ['Mathematics', 'English', 'Science', 'Social Studies', 'Hindi',
                     'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Physical Education'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($subjects as $i => $subject) {
            $stmt->execute([$subjectId, $subject, $i + 1]);
        }
        echo "Inserted school subjects.\n";
    }

    // Insert Gender
    $genderId = $categories['gender_null'] ?? null;
    if ($genderId) {
        $genders = ['Male', 'Female', 'Other'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($genders as $i => $gender) {
            $stmt->execute([$genderId, $gender, $i + 1]);
        }
        echo "Inserted genders.\n";
    }

    // Insert Blood Groups
    $bloodGroupId = $categories['blood_group_null'] ?? null;
    if ($bloodGroupId) {
        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($bloodGroups as $i => $bg) {
            $stmt->execute([$bloodGroupId, $bg, $i + 1]);
        }
        echo "Inserted blood groups.\n";
    }

    // Insert Categories (reservation)
    $categoryId = $categories['category_null'] ?? null;
    if ($categoryId) {
        $cats = ['General', 'OBC', 'SC', 'ST', 'EWS'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($cats as $i => $cat) {
            $stmt->execute([$categoryId, $cat, $i + 1]);
        }
        echo "Inserted student categories.\n";
    }

    // Insert Fee Types
    $feeTypeId = $categories['fee_type_null'] ?? null;
    if ($feeTypeId) {
        $feeTypes = ['Tuition Fee', 'Admission Fee', 'Exam Fee', 'Transport Fee', 'Library Fee', 'Lab Fee', 'Sports Fee'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($feeTypes as $i => $ft) {
            $stmt->execute([$feeTypeId, $ft, $i + 1]);
        }
        echo "Inserted fee types.\n";
    }

    // Insert Payment Modes
    $paymentModeId = $categories['payment_mode_null'] ?? null;
    if ($paymentModeId) {
        $paymentModes = ['Cash', 'Cheque', 'Online Transfer', 'UPI', 'Card', 'DD'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($paymentModes as $i => $pm) {
            $stmt->execute([$paymentModeId, $pm, $i + 1]);
        }
        echo "Inserted payment modes.\n";
    }

    // Insert Exam Types
    $examTypeId = $categories['exam_type_null'] ?? null;
    if ($examTypeId) {
        $examTypes = ['Unit Test', 'Mid-Term', 'Quarterly', 'Half-Yearly', 'Annual', 'Pre-Board'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($examTypes as $i => $et) {
            $stmt->execute([$examTypeId, $et, $i + 1]);
        }
        echo "Inserted exam types.\n";
    }

    // Insert Designations
    $designationId = $categories['designation_null'] ?? null;
    if ($designationId) {
        $designations = ['Principal', 'Vice Principal', 'HOD', 'Senior Teacher', 'Teacher',
                         'Assistant Teacher', 'Lab Assistant', 'Clerk', 'Accountant', 'Librarian'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($designations as $i => $d) {
            $stmt->execute([$designationId, $d, $i + 1]);
        }
        echo "Inserted designations.\n";
    }

    // Insert Religions
    $religionId = $categories['religion_null'] ?? null;
    if ($religionId) {
        $religions = ['Hindu', 'Muslim', 'Christian', 'Sikh', 'Buddhist', 'Jain', 'Other'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($religions as $i => $r) {
            $stmt->execute([$religionId, $r, $i + 1]);
        }
        echo "Inserted religions.\n";
    }

    // Insert College Departments
    $deptId = $categories['department_2'] ?? null;
    if ($deptId) {
        $departments = ['Computer Science', 'Electronics', 'Mechanical', 'Civil', 'Electrical',
                        'Information Technology', 'Chemical', 'Biotechnology'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($departments as $i => $d) {
            $stmt->execute([$deptId, $d, $i + 1]);
        }
        echo "Inserted college departments.\n";
    }

    // Insert College Courses
    $courseId = $categories['course_2'] ?? null;
    if ($courseId) {
        $courses = ['B.Tech', 'M.Tech', 'BCA', 'MCA', 'B.Sc', 'M.Sc', 'BBA', 'MBA', 'B.Com', 'M.Com'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($courses as $i => $c) {
            $stmt->execute([$courseId, $c, $i + 1]);
        }
        echo "Inserted college courses.\n";
    }

    // Insert College Semesters
    $semesterId = $categories['semester_2'] ?? null;
    if ($semesterId) {
        $semesters = ['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4',
                      'Semester 5', 'Semester 6', 'Semester 7', 'Semester 8'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        foreach ($semesters as $i => $s) {
            $stmt->execute([$semesterId, $s, $i + 1]);
        }
        echo "Inserted college semesters.\n";
    }

    echo "\n=== Verifying Tables ===\n";

    $tables = ['institution_types', 'institution_settings', 'dropdown_categories', 'dropdown_values'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "Table '$table': $count rows\n";
    }

    // Show institution types
    echo "\n=== Institution Types ===\n";
    $stmt = $pdo->query("SELECT * FROM institution_types");
    while ($row = $stmt->fetch()) {
        echo "- {$row['name']}: {$row['description']}\n";
    }

    // Show dropdown categories count by type
    echo "\n=== Dropdown Categories ===\n";
    $stmt = $pdo->query("
        SELECT it.name as institution, COUNT(dc.id) as count
        FROM dropdown_categories dc
        LEFT JOIN institution_types it ON dc.institution_type_id = it.id
        GROUP BY dc.institution_type_id
    ");
    while ($row = $stmt->fetch()) {
        $inst = $row['institution'] ?? 'Common';
        echo "- {$inst}: {$row['count']} categories\n";
    }

    echo "\n=== Migration Complete! ===\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
?>
