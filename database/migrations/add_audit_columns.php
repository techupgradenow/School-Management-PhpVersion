<?php
/**
 * Add Audit Columns Migration
 * Adds created_by and updated_by columns to all tables
 */

require_once __DIR__ . '/../../backend/config/env.php';

echo "=== ADD AUDIT COLUMNS MIGRATION ===\n\n";

try {
    $pdo = new PDO(getDbDsn(), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 120
    ]);
    echo "[OK] Connected to Hostinger: " . DB_HOST . "\n\n";

    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($tables) . " tables\n\n";

    $success = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($tables as $table) {
        echo "Processing: {$table}\n";

        // Check existing columns
        $columns = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);

        // Add created_by if not exists
        if (!in_array('created_by', $columns)) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `created_by` VARCHAR(100) NULL COMMENT 'User who created this record'");
                echo "  [+] Added: created_by\n";
                $success++;
            } catch (PDOException $e) {
                echo "  [!] Error adding created_by: " . $e->getMessage() . "\n";
                $errors++;
            }
        } else {
            echo "  [=] Exists: created_by\n";
            $skipped++;
        }

        // Add updated_by if not exists
        if (!in_array('updated_by', $columns)) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `updated_by` VARCHAR(100) NULL COMMENT 'User who last updated this record'");
                echo "  [+] Added: updated_by\n";
                $success++;
            } catch (PDOException $e) {
                echo "  [!] Error adding updated_by: " . $e->getMessage() . "\n";
                $errors++;
            }
        } else {
            echo "  [=] Exists: updated_by\n";
            $skipped++;
        }

        // Ensure created_at exists
        if (!in_array('created_at', $columns)) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                echo "  [+] Added: created_at\n";
                $success++;
            } catch (PDOException $e) {
                $errors++;
            }
        }

        // Ensure updated_at exists
        if (!in_array('updated_at', $columns)) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                echo "  [+] Added: updated_at\n";
                $success++;
            } catch (PDOException $e) {
                $errors++;
            }
        }

        echo "\n";
    }

    echo "=== SUMMARY ===\n";
    echo "Added: {$success} columns\n";
    echo "Skipped: {$skipped} (already exist)\n";
    echo "Errors: {$errors}\n\n";

    // Show sample table structure
    echo "=== SAMPLE TABLE STRUCTURE (students) ===\n\n";
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            if (in_array($col['Field'], ['created_by', 'updated_by', 'created_at', 'updated_at'])) {
                echo "  {$col['Field']}: {$col['Type']}\n";
            }
        }
    } catch (PDOException $e) {
        echo "  (students table not found)\n";
    }

    echo "\n[SUCCESS] Migration complete!\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
?>
