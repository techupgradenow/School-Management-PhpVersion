<?php
/**
 * Cleanup Duplicate Subjects
 * EduManage Pro - School Management System
 *
 * This script removes duplicate subjects from the academic_subjects table,
 * keeping only the first entry of each subject_code.
 *
 * Run with: php database/cleanup_duplicate_subjects.php
 */

require_once __DIR__ . '/../backend/config/db.php';

echo "=============================================================\n";
echo "  Cleanup Duplicate Subjects\n";
echo "=============================================================\n\n";

try {
    $db = getDB();
    echo "[OK] Database connection established\n\n";

    // Find duplicates
    echo "[INFO] Checking for duplicate subject codes...\n";

    $stmt = $db->query("
        SELECT subject_code, COUNT(*) as count, GROUP_CONCAT(id ORDER BY id) as ids
        FROM academic_subjects
        GROUP BY subject_code, school_id
        HAVING COUNT(*) > 1
    ");

    $duplicates = $stmt->fetchAll();

    if (empty($duplicates)) {
        echo "[OK] No duplicate subjects found!\n\n";
    } else {
        echo "[FOUND] " . count($duplicates) . " duplicate subject code(s)\n\n";

        $totalRemoved = 0;

        foreach ($duplicates as $dup) {
            $ids = explode(',', $dup['ids']);
            $keepId = array_shift($ids); // Keep the first one
            $removeIds = $ids;

            echo "  Subject Code: {$dup['subject_code']}\n";
            echo "    - Keeping ID: {$keepId}\n";
            echo "    - Removing IDs: " . implode(', ', $removeIds) . "\n";

            // Remove duplicates
            if (!empty($removeIds)) {
                $placeholders = str_repeat('?,', count($removeIds) - 1) . '?';
                $deleteStmt = $db->prepare("DELETE FROM academic_subjects WHERE id IN ({$placeholders})");
                $deleteStmt->execute($removeIds);
                $removed = $deleteStmt->rowCount();
                $totalRemoved += $removed;
                echo "    - Removed: {$removed} duplicate(s)\n";
            }
            echo "\n";
        }

        echo "=============================================================\n";
        echo "  CLEANUP COMPLETE\n";
        echo "=============================================================\n";
        echo "  Total duplicates removed: {$totalRemoved}\n";
        echo "=============================================================\n\n";
    }

    // Show current subjects count
    $countStmt = $db->query("SELECT COUNT(*) FROM academic_subjects");
    $count = $countStmt->fetchColumn();
    echo "[INFO] Total subjects in database: {$count}\n\n";

    // List all subjects
    echo "[INFO] Current subjects list:\n";
    $listStmt = $db->query("
        SELECT id, subject_code, subject_name, subject_type, status
        FROM academic_subjects
        ORDER BY subject_code
    ");
    $subjects = $listStmt->fetchAll();

    foreach ($subjects as $subject) {
        $status = $subject['status'] ?? 'Active';
        echo "  [{$subject['id']}] {$subject['subject_code']} - {$subject['subject_name']} ({$subject['subject_type']}) [{$status}]\n";
    }

    echo "\n[SUCCESS] Cleanup completed!\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
?>
