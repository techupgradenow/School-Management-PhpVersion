<?php
/**
 * Performance Optimization - Add Database Indexes
 * EduManage Pro - School Management System
 *
 * This migration adds indexes to improve query performance.
 * Run with: php database/migrations/add_performance_indexes.php
 */

require_once __DIR__ . '/../../backend/config/db.php';

echo "=============================================================\n";
echo "  Performance Optimization - Adding Database Indexes\n";
echo "=============================================================\n\n";

try {
    $db = getDB();
    echo "[OK] Database connection established\n\n";

    // Define indexes to add
    $indexes = [
        // Students table indexes
        ['students', 'idx_students_class_section', '(class, section)'],
        ['students', 'idx_students_school_id', '(school_id)'],
        ['students', 'idx_students_status', '(status)'],
        ['students', 'idx_students_class_section_school', '(class, section, school_id)'],

        // Teachers table indexes
        ['teachers', 'idx_teachers_school_id', '(school_id)'],
        ['teachers', 'idx_teachers_status', '(status)'],

        // Attendance table indexes
        ['attendance', 'idx_attendance_date', '(date)'],
        ['attendance', 'idx_attendance_student_date', '(student_id, date)'],
        ['attendance', 'idx_attendance_school_date', '(school_id, date)'],
        ['attendance', 'idx_attendance_status', '(status)'],
        ['attendance', 'idx_attendance_composite', '(school_id, date, status)'],

        // Academic classes indexes
        ['academic_classes', 'idx_classes_school_status', '(school_id, status)'],
        ['academic_classes', 'idx_classes_numeric_order', '(numeric_value, display_order)'],

        // Academic sections indexes
        ['academic_sections', 'idx_sections_class_status', '(class_id, status)'],
        ['academic_sections', 'idx_sections_school_id', '(school_id)'],

        // Academic subjects indexes
        ['academic_subjects', 'idx_subjects_school_status', '(school_id, status)'],
        ['academic_subjects', 'idx_subjects_code', '(subject_code)'],

        // Fees table indexes
        ['fees', 'idx_fees_student_id', '(student_id)'],
        ['fees', 'idx_fees_school_id', '(school_id)'],
        ['fees', 'idx_fees_status', '(status)'],
        ['fees', 'idx_fees_due_date', '(due_date)'],

        // Exams table indexes
        ['exams', 'idx_exams_school_id', '(school_id)'],
        ['exams', 'idx_exams_date', '(date)'],
        ['exams', 'idx_exams_class', '(class)'],

        // Timetable indexes
        ['timetable', 'idx_timetable_class_section', '(class, section)'],
        ['timetable', 'idx_timetable_day', '(day)'],
        ['timetable', 'idx_timetable_school_id', '(school_id)'],

        // Users table indexes
        ['users', 'idx_users_role', '(role)'],
        ['users', 'idx_users_status', '(status)'],
        ['users', 'idx_users_school_id', '(school_id)'],

        // Activity logs indexes (if table exists)
        ['activity_logs', 'idx_activity_user_id', '(user_id)'],
        ['activity_logs', 'idx_activity_created_at', '(created_at)'],
        ['activity_logs', 'idx_activity_module', '(module)'],
    ];

    $added = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($indexes as $indexInfo) {
        [$table, $indexName, $columns] = $indexInfo;

        // Check if table exists
        $tableCheck = $db->query("SHOW TABLES LIKE '$table'");
        if ($tableCheck->rowCount() === 0) {
            echo "  [SKIP] Table '$table' does not exist\n";
            $skipped++;
            continue;
        }

        // Check if index already exists
        $indexCheck = $db->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
        if ($indexCheck->rowCount() > 0) {
            echo "  [SKIP] Index '$indexName' already exists on '$table'\n";
            $skipped++;
            continue;
        }

        // Add the index
        try {
            $sql = "ALTER TABLE `$table` ADD INDEX `$indexName` $columns";
            $db->exec($sql);
            echo "  [OK] Added index '$indexName' on '$table' $columns\n";
            $added++;
        } catch (PDOException $e) {
            // Check if it's a duplicate key error (already exists)
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "  [SKIP] Index '$indexName' already exists (duplicate)\n";
                $skipped++;
            } else {
                echo "  [ERROR] Failed to add '$indexName': " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }

    // Analyze tables for query optimizer
    echo "\n[INFO] Analyzing tables for query optimizer...\n";
    $tables = ['students', 'teachers', 'attendance', 'academic_classes', 'academic_sections', 'fees', 'exams'];

    foreach ($tables as $table) {
        try {
            $tableCheck = $db->query("SHOW TABLES LIKE '$table'");
            if ($tableCheck->rowCount() > 0) {
                $db->exec("ANALYZE TABLE `$table`");
                echo "  [OK] Analyzed '$table'\n";
            }
        } catch (Exception $e) {
            // Ignore analysis errors
        }
    }

    echo "\n=============================================================\n";
    echo "  INDEX MIGRATION COMPLETE\n";
    echo "=============================================================\n";
    echo "  Indexes Added:   $added\n";
    echo "  Indexes Skipped: $skipped\n";
    echo "  Errors:          $errors\n";
    echo "=============================================================\n\n";

    if ($added > 0) {
        echo "[SUCCESS] Performance indexes have been added!\n";
        echo "Your queries should now be significantly faster.\n\n";
    } else if ($skipped > 0 && $errors === 0) {
        echo "[INFO] All indexes already exist. No changes needed.\n\n";
    }

} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
