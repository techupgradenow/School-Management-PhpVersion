<?php
/**
 * Hostinger Database Setup - Creates all tables
 */

require_once __DIR__ . '/../backend/config/env.php';

echo "=== HOSTINGER DATABASE SETUP ===\n\n";

try {
    $pdo = new PDO(getDbDsn(), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 60
    ]);
    echo "[OK] Connected to Hostinger: " . DB_HOST . "\n\n";

    // Read schema file
    $schemaFile = __DIR__ . '/hostinger_complete_schema.sql';
    if (!file_exists($schemaFile)) {
        die("[ERROR] Schema file not found!\n");
    }

    $sql = file_get_contents($schemaFile);
    echo "[OK] Schema file loaded\n\n";

    echo "Creating tables...\n";

    // Split and execute statements
    $statements = preg_split('/;\s*$/m', $sql);
    $success = 0;
    $errors = 0;

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt) || strpos($stmt, '--') === 0) continue;

        try {
            $pdo->exec($stmt);
            $success++;

            // Show progress for CREATE TABLE
            if (preg_match('/CREATE TABLE.*?`(\w+)`/i', $stmt, $m)) {
                echo "  [+] Created: {$m[1]}\n";
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                if (preg_match('/CREATE TABLE.*?`(\w+)`/i', $stmt, $m)) {
                    echo "  [=] Exists: {$m[1]}\n";
                }
            } else {
                $errors++;
            }
        }
    }

    echo "\n[OK] Executed: $success statements\n";
    if ($errors > 0) {
        echo "[!] Errors: $errors\n";
    }

    // Show all tables
    echo "\n=== TABLES IN DATABASE ===\n\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Total tables: " . count($tables) . "\n\n";

    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "  $table: $count rows\n";
    }

    echo "\n=== SETUP COMPLETE ===\n";
    echo "Your Hostinger database is ready!\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
?>
