<?php
$pdo = new PDO('mysql:host=localhost;dbname=edumanage_pro', 'root', '');
echo "=== ALL DROPDOWN VALUES IN DATABASE ===\n\n";

$stmt = $pdo->query("
    SELECT dc.category_name, dc.category_key,
           COALESCE(it.name, 'Common') as institution,
           COUNT(dv.id) as total_values
    FROM dropdown_categories dc
    LEFT JOIN dropdown_values dv ON dc.id = dv.category_id
    LEFT JOIN institution_types it ON dc.institution_type_id = it.id
    GROUP BY dc.id
    ORDER BY institution, dc.category_name
");

$current = '';
$grandTotal = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($current !== $row['institution']) {
        $current = $row['institution'];
        echo "\n=== " . strtoupper($current) . " ===\n";
    }
    echo $row['category_name'] . ": " . $row['total_values'] . " values\n";
    $grandTotal += $row['total_values'];
}

echo "\n================================\n";
echo "GRAND TOTAL: " . $grandTotal . " values\n";
?>
