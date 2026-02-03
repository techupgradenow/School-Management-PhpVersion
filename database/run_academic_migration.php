<?php
/**
 * Run Academic Settings Migration
 * Creates the academic_classes, academic_sections, academic_subjects, and academic_class_subjects tables
 */

require_once __DIR__ . '/../backend/config/db.php';

echo "========================================\n";
echo "Academic Settings Migration\n";
echo "========================================\n\n";

try {
    $db = getDB();
    echo "[OK] Database connection established\n\n";

    // Check if tables already exist
    $tables = ['academic_classes', 'academic_sections', 'academic_subjects', 'academic_class_subjects'];
    $existingTables = [];

    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->fetch()) {
            $existingTables[] = $table;
        }
    }

    if (!empty($existingTables)) {
        echo "Existing tables found: " . implode(', ', $existingTables) . "\n";
        echo "Migration will add missing data without overwriting.\n\n";
    }

    // Disable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Create academic_classes table
    echo "Creating academic_classes table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `academic_classes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `uuid` CHAR(36) NOT NULL UNIQUE,
        `class_name` VARCHAR(50) NOT NULL,
        `numeric_value` INT NOT NULL DEFAULT 0,
        `description` TEXT NULL,
        `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
        `display_order` INT DEFAULT 0,
        `school_id` CHAR(36) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_by` VARCHAR(50) NULL,
        INDEX `idx_school_id` (`school_id`),
        INDEX `idx_status` (`status`),
        INDEX `idx_numeric_value` (`numeric_value`),
        INDEX `idx_display_order` (`display_order`),
        UNIQUE KEY `unique_class_per_school` (`school_id`, `class_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] academic_classes table ready\n";

    // Create academic_sections table
    echo "Creating academic_sections table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `academic_sections` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `uuid` CHAR(36) NOT NULL UNIQUE,
        `class_id` INT NOT NULL,
        `section_name` VARCHAR(10) NOT NULL,
        `capacity` INT DEFAULT 40,
        `class_teacher_id` VARCHAR(50) NULL,
        `room_number` VARCHAR(20) NULL,
        `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
        `display_order` INT DEFAULT 0,
        `school_id` CHAR(36) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_by` VARCHAR(50) NULL,
        INDEX `idx_class_id` (`class_id`),
        INDEX `idx_school_id` (`school_id`),
        INDEX `idx_class_teacher` (`class_teacher_id`),
        INDEX `idx_status` (`status`),
        UNIQUE KEY `unique_section_per_class` (`class_id`, `section_name`),
        CONSTRAINT `fk_section_class` FOREIGN KEY (`class_id`)
            REFERENCES `academic_classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] academic_sections table ready\n";

    // Create academic_subjects table
    echo "Creating academic_subjects table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `academic_subjects` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `uuid` CHAR(36) NOT NULL UNIQUE,
        `subject_code` VARCHAR(20) NOT NULL,
        `subject_name` VARCHAR(100) NOT NULL,
        `short_name` VARCHAR(20) NULL,
        `subject_type` ENUM('Theory', 'Practical', 'Lab', 'Elective', 'Core', 'Activity') NOT NULL DEFAULT 'Theory',
        `description` TEXT NULL,
        `max_marks` INT DEFAULT 100,
        `pass_marks` INT DEFAULT 33,
        `credit_hours` DECIMAL(3,1) NULL,
        `color_code` VARCHAR(7) NULL,
        `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
        `display_order` INT DEFAULT 0,
        `school_id` CHAR(36) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_by` VARCHAR(50) NULL,
        INDEX `idx_school_id` (`school_id`),
        INDEX `idx_subject_code` (`subject_code`),
        INDEX `idx_status` (`status`),
        INDEX `idx_subject_type` (`subject_type`),
        UNIQUE KEY `unique_subject_code_per_school` (`school_id`, `subject_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] academic_subjects table ready\n";

    // Create academic_class_subjects table
    echo "Creating academic_class_subjects table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `academic_class_subjects` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `uuid` CHAR(36) NOT NULL UNIQUE,
        `class_id` INT NOT NULL,
        `subject_id` INT NOT NULL,
        `is_mandatory` TINYINT(1) DEFAULT 1,
        `weekly_periods` INT DEFAULT 5,
        `assigned_teacher_id` VARCHAR(50) NULL,
        `school_id` CHAR(36) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_class_id` (`class_id`),
        INDEX `idx_subject_id` (`subject_id`),
        INDEX `idx_school_id` (`school_id`),
        INDEX `idx_teacher` (`assigned_teacher_id`),
        UNIQUE KEY `unique_class_subject` (`class_id`, `subject_id`),
        CONSTRAINT `fk_cs_class` FOREIGN KEY (`class_id`)
            REFERENCES `academic_classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_cs_subject` FOREIGN KEY (`subject_id`)
            REFERENCES `academic_subjects`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] academic_class_subjects table ready\n\n";

    // Function to generate UUID
    function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    // Seed default classes
    echo "Seeding default classes...\n";
    $classes = [
        ['Nursery', -2, 1],
        ['LKG', -1, 2],
        ['UKG', 0, 3],
        ['Class 1', 1, 4],
        ['Class 2', 2, 5],
        ['Class 3', 3, 6],
        ['Class 4', 4, 7],
        ['Class 5', 5, 8],
        ['Class 6', 6, 9],
        ['Class 7', 7, 10],
        ['Class 8', 8, 11],
        ['Class 9', 9, 12],
        ['Class 10', 10, 13],
        ['Class 11', 11, 14],
        ['Class 12', 12, 15]
    ];

    $insertClass = $db->prepare("INSERT INTO academic_classes (uuid, class_name, numeric_value, display_order, status)
        VALUES (?, ?, ?, ?, 'Active')
        ON DUPLICATE KEY UPDATE updated_at = NOW()");

    $classesAdded = 0;
    foreach ($classes as $cls) {
        try {
            $insertClass->execute([generateUUID(), $cls[0], $cls[1], $cls[2]]);
            $classesAdded++;
        } catch (Exception $e) {
            // Class already exists, skip
        }
    }
    echo "[OK] $classesAdded classes processed\n";

    // Seed default sections for each class
    echo "Seeding default sections...\n";
    $sections = ['A', 'B', 'C'];

    $getClasses = $db->query("SELECT id, class_name FROM academic_classes");
    $allClasses = $getClasses->fetchAll(PDO::FETCH_ASSOC);

    $insertSection = $db->prepare("INSERT INTO academic_sections (uuid, class_id, section_name, capacity, display_order, status)
        VALUES (?, ?, ?, 40, ?, 'Active')
        ON DUPLICATE KEY UPDATE updated_at = NOW()");

    $sectionsAdded = 0;
    foreach ($allClasses as $cls) {
        $order = 1;
        foreach ($sections as $sec) {
            try {
                $insertSection->execute([generateUUID(), $cls['id'], $sec, $order]);
                $sectionsAdded++;
            } catch (Exception $e) {
                // Section already exists
            }
            $order++;
        }
    }
    echo "[OK] $sectionsAdded sections processed\n";

    // Seed default subjects
    echo "Seeding default subjects...\n";
    $subjects = [
        ['MATH', 'Mathematics', 'Math', 'Core', '#3B82F6', 1],
        ['ENG', 'English', 'Eng', 'Core', '#10B981', 2],
        ['HIN', 'Hindi', 'Hindi', 'Core', '#F59E0B', 3],
        ['SCI', 'Science', 'Sci', 'Core', '#8B5CF6', 4],
        ['SST', 'Social Studies', 'SST', 'Core', '#EF4444', 5],
        ['PHY', 'Physics', 'Phy', 'Theory', '#06B6D4', 6],
        ['CHEM', 'Chemistry', 'Chem', 'Theory', '#EC4899', 7],
        ['BIO', 'Biology', 'Bio', 'Theory', '#22C55E', 8],
        ['CS', 'Computer Science', 'CS', 'Theory', '#6366F1', 9],
        ['PE', 'Physical Education', 'PE', 'Activity', '#F97316', 10],
        ['ART', 'Art & Craft', 'Art', 'Activity', '#A855F7', 11],
        ['MUS', 'Music', 'Music', 'Activity', '#14B8A6', 12],
        ['EVS', 'Environmental Studies', 'EVS', 'Core', '#84CC16', 13],
        ['GK', 'General Knowledge', 'GK', 'Core', '#0EA5E9', 14]
    ];

    $insertSubject = $db->prepare("INSERT INTO academic_subjects (uuid, subject_code, subject_name, short_name, subject_type, color_code, display_order, status, max_marks, pass_marks)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', 100, 33)
        ON DUPLICATE KEY UPDATE updated_at = NOW()");

    $subjectsAdded = 0;
    foreach ($subjects as $sub) {
        try {
            $insertSubject->execute([generateUUID(), $sub[0], $sub[1], $sub[2], $sub[3], $sub[4], $sub[5]]);
            $subjectsAdded++;
        } catch (Exception $e) {
            // Subject already exists
        }
    }
    echo "[OK] $subjectsAdded subjects processed\n";

    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Show final counts
    echo "\n========================================\n";
    echo "MIGRATION SUMMARY\n";
    echo "========================================\n";

    $stmt = $db->query("SELECT COUNT(*) FROM academic_classes");
    echo "Total Classes: " . $stmt->fetchColumn() . "\n";

    $stmt = $db->query("SELECT COUNT(*) FROM academic_sections");
    echo "Total Sections: " . $stmt->fetchColumn() . "\n";

    $stmt = $db->query("SELECT COUNT(*) FROM academic_subjects");
    echo "Total Subjects: " . $stmt->fetchColumn() . "\n";

    $stmt = $db->query("SELECT COUNT(*) FROM academic_class_subjects");
    echo "Total Class-Subject Mappings: " . $stmt->fetchColumn() . "\n";

    echo "\n[SUCCESS] Migration completed successfully!\n";

} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
?>
