<?php
/**
 * Cleanup Duplicate Dropdown Values
 * Keeps only one instance of each value per category
 */

echo "=== Cleaning Up Duplicate Dropdown Values ===\n\n";

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=edumanage_pro;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Find and remove duplicates (keep the one with lowest ID)
    $stmt = $pdo->query("
        SELECT dv1.id
        FROM dropdown_values dv1
        INNER JOIN dropdown_values dv2
        ON dv1.category_id = dv2.category_id
        AND dv1.value = dv2.value
        AND dv1.id > dv2.id
    ");
    $duplicateIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($duplicateIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($duplicateIds), '?'));
        $stmt = $pdo->prepare("DELETE FROM dropdown_values WHERE id IN ($placeholders)");
        $stmt->execute($duplicateIds);
        echo "Removed " . count($duplicateIds) . " duplicate values.\n";
    } else {
        echo "No duplicates found.\n";
    }

    // Show updated counts
    echo "\n=== Updated Counts ===\n";

    // School
    echo "\n--- SCHOOL ---\n";
    $stmt = $pdo->query("
        SELECT dc.category_name, COUNT(dv.id) as cnt
        FROM dropdown_categories dc
        JOIN institution_types it ON dc.institution_type_id = it.id
        LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
        WHERE it.name = 'School'
        GROUP BY dc.id
        ORDER BY dc.category_name
    ");
    while ($row = $stmt->fetch()) {
        echo "  {$row['category_name']}: {$row['cnt']}\n";
    }

    // College
    echo "\n--- COLLEGE ---\n";
    $stmt = $pdo->query("
        SELECT dc.category_name, COUNT(dv.id) as cnt
        FROM dropdown_categories dc
        JOIN institution_types it ON dc.institution_type_id = it.id
        LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
        WHERE it.name = 'College'
        GROUP BY dc.id
        ORDER BY dc.category_name
    ");
    while ($row = $stmt->fetch()) {
        echo "  {$row['category_name']}: {$row['cnt']}\n";
    }

    // Show class values specifically
    echo "\n=== Class Values (School) ===\n";
    $stmt = $pdo->query("
        SELECT dv.value, dv.display_order
        FROM dropdown_values dv
        JOIN dropdown_categories dc ON dv.category_id = dc.id
        WHERE dc.category_key = 'class' AND dv.is_active = 1
        ORDER BY dv.display_order
    ");
    while ($row = $stmt->fetch()) {
        echo "  {$row['display_order']}. {$row['value']}\n";
    }

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dropdown_values WHERE is_active = 1");
    echo "\n=== Total Active Values: " . $stmt->fetch()['total'] . " ===\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
