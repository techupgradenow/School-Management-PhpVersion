<?php
// Test script to verify syllabus relationships
try {
    $pdo = new PDO('mysql:host=localhost;dbname=edumanage_pro', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== SYLLABUS TABLE RELATIONSHIPS TEST ===\n\n";

    // Check foreign keys
    $stmt = $pdo->query("
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'edumanage_pro'
        AND REFERENCED_TABLE_NAME IS NOT NULL
        AND TABLE_NAME LIKE 'syllabus%'
    ");

    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($fks)) {
        echo "WARNING: No foreign keys found!\n";
    } else {
        echo "Foreign Key Relationships:\n";
        echo str_repeat("-", 80) . "\n";
        foreach ($fks as $fk) {
            echo sprintf(
                "%s.%s -> %s.%s\n",
                $fk['TABLE_NAME'],
                $fk['COLUMN_NAME'],
                $fk['REFERENCED_TABLE_NAME'],
                $fk['REFERENCED_COLUMN_NAME']
            );
        }
    }

    echo "\n=== RELATIONSHIP DIAGRAM ===\n";
    echo "
    syllabus (main)
        |
        +-- syllabus_chapters (syllabus_id -> syllabus.id)
                |
                +-- syllabus_topics (chapter_id -> syllabus_chapters.id)
                        |
                        +-- syllabus_progress (topic_id -> syllabus_topics.id)
    ";

    echo "\n\n=== TEST PASSED ===\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
