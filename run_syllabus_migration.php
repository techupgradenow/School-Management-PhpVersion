<?php
/**
 * Run Syllabus Schema v2 Migration
 */

$pdo = new PDO('mysql:host=localhost;dbname=edumanage_pro', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== SYLLABUS SCHEMA V2 MIGRATION ===\n\n";

$migrations = [
    // Add new columns to syllabus table
    [
        "name" => "Add section column",
        "sql" => "ALTER TABLE syllabus ADD COLUMN section VARCHAR(10) DEFAULT NULL AFTER class",
        "check" => "SHOW COLUMNS FROM syllabus LIKE 'section'"
    ],
    [
        "name" => "Add board_type column",
        "sql" => "ALTER TABLE syllabus ADD COLUMN board_type ENUM('CBSE', 'ICSE', 'State Board', 'Other') DEFAULT 'CBSE' AFTER academic_year",
        "check" => "SHOW COLUMNS FROM syllabus LIKE 'board_type'"
    ],
    [
        "name" => "Add medium column",
        "sql" => "ALTER TABLE syllabus ADD COLUMN medium ENUM('English', 'Hindi', 'Tamil', 'Telugu', 'Other') DEFAULT 'English' AFTER board_type",
        "check" => "SHOW COLUMNS FROM syllabus LIKE 'medium'"
    ],
    [
        "name" => "Add total_marks column",
        "sql" => "ALTER TABLE syllabus ADD COLUMN total_marks INT DEFAULT NULL AFTER medium",
        "check" => "SHOW COLUMNS FROM syllabus LIKE 'total_marks'"
    ],
    [
        "name" => "Add exam_pattern column",
        "sql" => "ALTER TABLE syllabus ADD COLUMN exam_pattern TEXT DEFAULT NULL AFTER total_marks",
        "check" => "SHOW COLUMNS FROM syllabus LIKE 'exam_pattern'"
    ],
    [
        "name" => "Add version column",
        "sql" => "ALTER TABLE syllabus ADD COLUMN version INT DEFAULT 1 AFTER exam_pattern",
        "check" => "SHOW COLUMNS FROM syllabus LIKE 'version'"
    ],
    [
        "name" => "Add updated_by column",
        "sql" => "ALTER TABLE syllabus ADD COLUMN updated_by VARCHAR(50) DEFAULT NULL AFTER created_by",
        "check" => "SHOW COLUMNS FROM syllabus LIKE 'updated_by'"
    ],
    [
        "name" => "Add is_deleted column",
        "sql" => "ALTER TABLE syllabus ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER status",
        "check" => "SHOW COLUMNS FROM syllabus LIKE 'is_deleted'"
    ],
    // Add new columns to chapters table
    [
        "name" => "Add topics_covered column to chapters",
        "sql" => "ALTER TABLE syllabus_chapters ADD COLUMN topics_covered TEXT DEFAULT NULL AFTER description",
        "check" => "SHOW COLUMNS FROM syllabus_chapters LIKE 'topics_covered'"
    ],
    [
        "name" => "Add marks_weightage column to chapters",
        "sql" => "ALTER TABLE syllabus_chapters ADD COLUMN marks_weightage INT DEFAULT NULL AFTER estimated_hours",
        "check" => "SHOW COLUMNS FROM syllabus_chapters LIKE 'marks_weightage'"
    ],
    [
        "name" => "Add term column to chapters",
        "sql" => "ALTER TABLE syllabus_chapters ADD COLUMN term ENUM('Term-1', 'Term-2', 'Full Year') DEFAULT 'Full Year' AFTER marks_weightage",
        "check" => "SHOW COLUMNS FROM syllabus_chapters LIKE 'term'"
    ],
    [
        "name" => "Add status column to chapters",
        "sql" => "ALTER TABLE syllabus_chapters ADD COLUMN status ENUM('Active', 'Inactive') DEFAULT 'Active' AFTER term",
        "check" => "SHOW COLUMNS FROM syllabus_chapters LIKE 'status'"
    ],
];

// Run column migrations
foreach ($migrations as $m) {
    $stmt = $pdo->query($m['check']);
    if ($stmt->rowCount() == 0) {
        try {
            $pdo->exec($m['sql']);
            echo "[OK] " . $m['name'] . "\n";
        } catch (PDOException $e) {
            echo "[SKIP] " . $m['name'] . " - " . $e->getMessage() . "\n";
        }
    } else {
        echo "[EXISTS] " . $m['name'] . "\n";
    }
}

// Create new tables
$tables = [
    "syllabus_files" => "
        CREATE TABLE IF NOT EXISTS syllabus_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            syllabus_id VARCHAR(50) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_type VARCHAR(50) NOT NULL,
            file_size INT NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            uploaded_by VARCHAR(50),
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_deleted TINYINT(1) DEFAULT 0,
            FOREIGN KEY (syllabus_id) REFERENCES syllabus(id) ON DELETE CASCADE,
            INDEX idx_syllabus_file (syllabus_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    "syllabus_versions" => "
        CREATE TABLE IF NOT EXISTS syllabus_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            syllabus_id VARCHAR(50) NOT NULL,
            version INT NOT NULL,
            data JSON NOT NULL,
            changed_by VARCHAR(50),
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            change_summary TEXT,
            FOREIGN KEY (syllabus_id) REFERENCES syllabus(id) ON DELETE CASCADE,
            INDEX idx_syllabus_version (syllabus_id, version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    "syllabus_activity_logs" => "
        CREATE TABLE IF NOT EXISTS syllabus_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            syllabus_id VARCHAR(50) NOT NULL,
            action ENUM('CREATE', 'UPDATE', 'DELETE', 'PUBLISH', 'ARCHIVE', 'UPLOAD', 'DOWNLOAD') NOT NULL,
            user_id VARCHAR(50),
            user_name VARCHAR(100),
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (syllabus_id) REFERENCES syllabus(id) ON DELETE CASCADE,
            INDEX idx_syllabus_log (syllabus_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    "syllabus_teacher_assignments" => "
        CREATE TABLE IF NOT EXISTS syllabus_teacher_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            syllabus_id VARCHAR(50) NOT NULL,
            teacher_id VARCHAR(50) NOT NULL,
            can_edit TINYINT(1) DEFAULT 0,
            assigned_by VARCHAR(50),
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (syllabus_id) REFERENCES syllabus(id) ON DELETE CASCADE,
            UNIQUE KEY unique_assignment (syllabus_id, teacher_id),
            INDEX idx_teacher (teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

echo "\n--- Creating New Tables ---\n";
foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "[OK] Table: $name\n";
    } catch (PDOException $e) {
        echo "[ERROR] Table: $name - " . $e->getMessage() . "\n";
    }
}

// Create uploads directory
$uploadDir = __DIR__ . '/uploads/syllabus';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    echo "\n[OK] Created uploads directory: $uploadDir\n";
}

echo "\n=== MIGRATION COMPLETE ===\n";
