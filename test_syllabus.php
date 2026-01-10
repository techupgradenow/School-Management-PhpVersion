<?php
// Test script to verify syllabus tables exist
try {
    $pdo = new PDO('mysql:host=localhost;dbname=edumanage_pro', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== SYLLABUS TABLES CHECK ===\n";

    $stmt = $pdo->query("SHOW TABLES LIKE 'syllabus%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "No syllabus tables found!\n";
    } else {
        echo "Found " . count($tables) . " syllabus tables:\n";
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
    }

    // Check each table structure
    $expectedTables = ['syllabus', 'syllabus_chapters', 'syllabus_topics', 'syllabus_progress'];
    echo "\n=== TABLE STRUCTURE CHECK ===\n";
    foreach ($expectedTables as $table) {
        $stmt = $pdo->query("SHOW COLUMNS FROM $table");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "$table: " . count($columns) . " columns\n";
    }

    echo "\n=== ALL CHECKS PASSED ===\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
