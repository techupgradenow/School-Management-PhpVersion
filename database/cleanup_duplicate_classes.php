<?php
/**
 * Cleanup Duplicate Academic Classes
 * Removes duplicate classes keeping only the first entry
 */

require_once __DIR__ . '/../backend/config/db.php';

echo "========================================\n";
echo "Cleanup Duplicate Academic Classes\n";
echo "========================================\n\n";

try {
    $db = getDB();
    echo "[OK] Database connection established\n\n";

    // Find duplicates
    echo "Checking for duplicate classes...\n";
    $stmt = $db->query("
        SELECT class_name, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids
        FROM academic_classes
        GROUP BY class_name
        HAVING COUNT(*) > 1
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($duplicates)) {
        echo "[OK] No duplicate classes found!\n";
    } else {
        echo "Found " . count($duplicates) . " class names with duplicates:\n\n";

        $totalDeleted = 0;

        foreach ($duplicates as $dup) {
            $ids = explode(',', $dup['ids']);
            $keepId = array_shift($ids); // Keep the first one
            $deleteIds = $ids;

            echo "  - {$dup['class_name']}: {$dup['cnt']} entries\n";
            echo "    Keeping ID: $keepId, Deleting IDs: " . implode(', ', $deleteIds) . "\n";

            if (!empty($deleteIds)) {
                // Delete duplicate sections first (foreign key constraint)
                $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                $stmt = $db->prepare("DELETE FROM academic_sections WHERE class_id IN ($placeholders)");
                $stmt->execute($deleteIds);
                $sectionsDeleted = $stmt->rowCount();

                // Delete duplicate classes
                $stmt = $db->prepare("DELETE FROM academic_classes WHERE id IN ($placeholders)");
                $stmt->execute($deleteIds);
                $classesDeleted = $stmt->rowCount();

                $totalDeleted += $classesDeleted;
                echo "    Deleted $classesDeleted duplicate classes and $sectionsDeleted sections\n\n";
            }
        }

        echo "[OK] Cleanup complete! Deleted $totalDeleted duplicate classes.\n";
    }

    // Show final count
    $stmt = $db->query("SELECT COUNT(*) FROM academic_classes");
    echo "\nTotal classes remaining: " . $stmt->fetchColumn() . "\n";

    $stmt = $db->query("SELECT COUNT(*) FROM academic_sections");
    echo "Total sections remaining: " . $stmt->fetchColumn() . "\n";

    $stmt = $db->query("SELECT COUNT(*) FROM academic_subjects");
    echo "Total subjects remaining: " . $stmt->fetchColumn() . "\n";

    echo "\n[SUCCESS] Cleanup completed!\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
?>
