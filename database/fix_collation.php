<?php
/**
 * Fix Collation Issues
 * Updates academic tables to use the same collation as the database
 */

require_once __DIR__ . '/../backend/config/db.php';

echo "========================================\n";
echo "Fixing Collation Issues\n";
echo "========================================\n\n";

try {
    $db = getDB();
    echo "[OK] Database connection established\n\n";

    // Get the database's default collation
    $stmt = $db->query("SELECT @@character_set_database as charset, @@collation_database as collation");
    $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $targetCollation = $dbInfo['collation'];
    $targetCharset = $dbInfo['charset'];

    echo "Database charset: $targetCharset\n";
    echo "Database collation: $targetCollation\n\n";

    // Tables to fix
    $tables = ['academic_classes', 'academic_sections', 'academic_subjects', 'academic_class_subjects'];

    foreach ($tables as $table) {
        echo "Fixing $table...\n";
        try {
            $db->exec("ALTER TABLE `$table` CONVERT TO CHARACTER SET $targetCharset COLLATE $targetCollation");
            echo "[OK] $table converted to $targetCollation\n";
        } catch (Exception $e) {
            echo "[WARN] $table: " . $e->getMessage() . "\n";
        }
    }

    // Also fix the students table if it exists (for comparison queries)
    echo "\nFixing students table...\n";
    try {
        $db->exec("ALTER TABLE `students` CONVERT TO CHARACTER SET $targetCharset COLLATE $targetCollation");
        echo "[OK] students table converted\n";
    } catch (Exception $e) {
        echo "[WARN] students: " . $e->getMessage() . "\n";
    }

    // Fix teachers table too
    echo "Fixing teachers table...\n";
    try {
        $db->exec("ALTER TABLE `teachers` CONVERT TO CHARACTER SET $targetCharset COLLATE $targetCollation");
        echo "[OK] teachers table converted\n";
    } catch (Exception $e) {
        echo "[WARN] teachers: " . $e->getMessage() . "\n";
    }

    echo "\n[SUCCESS] Collation fix completed!\n";

} catch (Exception $e) {
    echo "[ERROR] Fix failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
