<?php
/**
 * Create Teacher Absence and Substitution Tables
 * Run this script to set up required database tables for absence management
 */

// Include centralized environment configuration (Remote Hostinger DB only)
require_once __DIR__ . '/../config/env.php';
validateRemoteDatabase();

$pdo = new PDO(getDbDsn(), DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== CREATING ABSENCE MANAGEMENT TABLES (Remote Hostinger) ===\n\n";

// 1. Create teacher_absences table
$tables = [
    "teacher_absences" => "
        CREATE TABLE IF NOT EXISTS teacher_absences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id VARCHAR(50) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            absence_type ENUM('SICK_LEAVE', 'CASUAL_LEAVE', 'EMERGENCY', 'TRAINING', 'OTHER') NOT NULL DEFAULT 'OTHER',
            reason TEXT,
            status ENUM('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
            approved_by VARCHAR(50) DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_teacher_id (teacher_id),
            INDEX idx_date_range (start_date, end_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    "substitution_records" => "
        CREATE TABLE IF NOT EXISTS substitution_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            absence_id INT,
            timetable_entry_id INT DEFAULT NULL,
            original_teacher_id VARCHAR(50) NOT NULL,
            substitute_teacher_id VARCHAR(50) DEFAULT NULL,
            substitution_date DATE NOT NULL,
            slot_id INT NOT NULL COMMENT 'Period number',
            class_id VARCHAR(50) NOT NULL,
            section_id VARCHAR(50) DEFAULT NULL,
            subject_id VARCHAR(50) DEFAULT NULL,
            status ENUM('PENDING', 'ASSIGNED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
            assignment_type ENUM('AUTO', 'MANUAL') DEFAULT 'MANUAL',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (absence_id) REFERENCES teacher_absences(id) ON DELETE CASCADE,
            INDEX idx_substitution_date (substitution_date),
            INDEX idx_original_teacher (original_teacher_id),
            INDEX idx_substitute_teacher (substitute_teacher_id),
            INDEX idx_status (status),
            UNIQUE KEY unique_sub (substitution_date, slot_id, class_id, section_id, original_teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

foreach ($tables as $name => $sql) {
    try {
        // Check if table exists
        $check = $pdo->query("SHOW TABLES LIKE '$name'");
        if ($check->rowCount() > 0) {
            echo "[EXISTS] Table: $name\n";
        } else {
            $pdo->exec($sql);
            echo "[CREATED] Table: $name\n";
        }
    } catch (PDOException $e) {
        echo "[ERROR] Table: $name - " . $e->getMessage() . "\n";
    }
}

// 2. Check and update timetable table for consistent field names
echo "\n--- Checking Timetable Table ---\n";

try {
    // Check if timetable table exists
    $check = $pdo->query("SHOW TABLES LIKE 'timetable'");
    if ($check->rowCount() > 0) {
        // Check column names
        $columns = $pdo->query("SHOW COLUMNS FROM timetable");
        $columnNames = [];
        while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
            $columnNames[] = $col['Field'];
        }

        // Check for period_number vs period_no
        if (in_array('period_number', $columnNames)) {
            echo "[OK] timetable has 'period_number' column\n";
        } elseif (in_array('period_no', $columnNames)) {
            echo "[INFO] timetable has 'period_no' column - renaming to 'period_number'\n";
            $pdo->exec("ALTER TABLE timetable CHANGE period_no period_number INT NOT NULL");
            echo "[FIXED] Renamed period_no to period_number\n";
        } else {
            echo "[WARNING] timetable missing period column - adding 'period_number'\n";
            $pdo->exec("ALTER TABLE timetable ADD COLUMN period_number INT NOT NULL DEFAULT 1 AFTER day_of_week");
            echo "[ADDED] Added period_number column\n";
        }

        // Check for day_of_week
        if (in_array('day_of_week', $columnNames)) {
            echo "[OK] timetable has 'day_of_week' column\n";
        } else {
            echo "[WARNING] timetable missing day_of_week column - adding it\n";
            $pdo->exec("ALTER TABLE timetable ADD COLUMN day_of_week VARCHAR(20) NOT NULL DEFAULT 'Monday' AFTER section");
            echo "[ADDED] Added day_of_week column\n";
        }

        echo "[OK] Timetable table is ready\n";
    } else {
        // Create timetable table if not exists
        $pdo->exec("
            CREATE TABLE timetable (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class VARCHAR(50) NOT NULL,
                section VARCHAR(10) DEFAULT 'A',
                day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
                period_number INT NOT NULL,
                subject_id VARCHAR(50),
                teacher_id VARCHAR(50),
                room VARCHAR(50),
                status ENUM('SCHEDULED', 'FREE', 'STUDY_HOUR', 'SUBSTITUTED', 'CANCELLED') DEFAULT 'SCHEDULED',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_class_section (class, section),
                INDEX idx_day (day_of_week),
                INDEX idx_teacher (teacher_id),
                UNIQUE KEY unique_slot (class, section, day_of_week, period_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "[CREATED] Timetable table\n";
    }
} catch (PDOException $e) {
    echo "[ERROR] Timetable check failed: " . $e->getMessage() . "\n";
}

// 3. Verify classes table exists
echo "\n--- Checking Classes Table ---\n";
try {
    $check = $pdo->query("SHOW TABLES LIKE 'classes'");
    if ($check->rowCount() > 0) {
        echo "[OK] Classes table exists\n";

        // Check for class_name column
        $columns = $pdo->query("SHOW COLUMNS FROM classes");
        $hasClassName = false;
        while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
            if ($col['Field'] === 'class_name') {
                $hasClassName = true;
                break;
            }
        }
        if (!$hasClassName) {
            echo "[WARNING] Classes table missing class_name column\n";
        }
    } else {
        echo "[WARNING] Classes table not found - creating it\n";
        $pdo->exec("
            CREATE TABLE classes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_name VARCHAR(50) NOT NULL,
                capacity INT DEFAULT 40,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insert default classes
        for ($i = 1; $i <= 12; $i++) {
            $pdo->exec("INSERT INTO classes (class_name) VALUES ('Class $i')");
        }
        echo "[CREATED] Classes table with default classes\n";
    }
} catch (PDOException $e) {
    echo "[ERROR] Classes check failed: " . $e->getMessage() . "\n";
}

// 4. Verify periods table exists
echo "\n--- Checking Periods Table ---\n";
try {
    $check = $pdo->query("SHOW TABLES LIKE 'periods'");
    if ($check->rowCount() > 0) {
        echo "[OK] Periods table exists\n";
    } else {
        echo "[WARNING] Periods table not found - creating it\n";
        $pdo->exec("
            CREATE TABLE periods (
                id INT AUTO_INCREMENT PRIMARY KEY,
                period_number INT NOT NULL,
                name VARCHAR(50),
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                is_break TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insert default periods
        $periods = [
            [1, 'Period 1', '08:00:00', '08:45:00', 0],
            [2, 'Period 2', '08:45:00', '09:30:00', 0],
            [3, 'Period 3', '09:30:00', '10:15:00', 0],
            [4, 'Break', '10:15:00', '10:30:00', 1],
            [5, 'Period 4', '10:30:00', '11:15:00', 0],
            [6, 'Period 5', '11:15:00', '12:00:00', 0],
            [7, 'Period 6', '12:00:00', '12:45:00', 0],
            [8, 'Lunch', '12:45:00', '13:30:00', 1],
            [9, 'Period 7', '13:30:00', '14:15:00', 0],
            [10, 'Period 8', '14:15:00', '15:00:00', 0]
        ];

        $stmt = $pdo->prepare("INSERT INTO periods (period_number, name, start_time, end_time, is_break) VALUES (?, ?, ?, ?, ?)");
        foreach ($periods as $p) {
            $stmt->execute($p);
        }
        echo "[CREATED] Periods table with default periods\n";
    }
} catch (PDOException $e) {
    echo "[ERROR] Periods check failed: " . $e->getMessage() . "\n";
}

echo "\n=== MIGRATION COMPLETE ===\n";
echo "\nRun this command to execute:\n";
echo "php C:\\xampp\\htdocs\\School-Management-PhpVersion\\backend\\database\\create_absence_tables.php\n";
