<?php
/**
 * Reset Class Dropdown Values
 * Clean and standardized class values for School
 */

echo "=== Resetting Class Dropdown Values ===\n\n";

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

    // Get class category ID
    $stmt = $pdo->query("SELECT id FROM dropdown_categories WHERE category_key = 'class'");
    $categoryId = $stmt->fetch()['id'];

    if (!$categoryId) {
        die("Class category not found!\n");
    }

    echo "Class Category ID: $categoryId\n";

    // Delete all existing class values
    $stmt = $pdo->prepare("DELETE FROM dropdown_values WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    echo "Deleted existing class values.\n";

    // Insert clean, standardized values
    $classValues = [
        'LKG',
        'UKG',
        '1st Standard',
        '2nd Standard',
        '3rd Standard',
        '4th Standard',
        '5th Standard',
        '6th Standard',
        '7th Standard',
        '8th Standard',
        '9th Standard',
        '10th Standard',
        '11th Standard',
        '12th Standard'
    ];

    $stmt = $pdo->prepare("
        INSERT INTO dropdown_values (category_id, value, display_order, is_active)
        VALUES (?, ?, ?, 1)
    ");

    foreach ($classValues as $order => $value) {
        $stmt->execute([$categoryId, $value, $order + 1]);
    }

    echo "Inserted " . count($classValues) . " class values.\n";

    // Verify
    echo "\n=== Class Values (Verified) ===\n";
    $stmt = $pdo->prepare("
        SELECT value, display_order FROM dropdown_values
        WHERE category_id = ? AND is_active = 1
        ORDER BY display_order
    ");
    $stmt->execute([$categoryId]);
    while ($row = $stmt->fetch()) {
        echo "  {$row['display_order']}. {$row['value']}\n";
    }

    echo "\n=== Done ===\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
