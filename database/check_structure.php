<?php
$pdo = new PDO('mysql:host=localhost;dbname=edumanage_pro', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Institution Types ===\n";
$stmt = $pdo->query('SELECT * FROM institution_types');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']}, Name: {$row['name']}\n";
}

echo "\n=== Dropdown Categories Structure ===\n";
$stmt = $pdo->query('DESCRIBE dropdown_categories');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\n=== Sample Categories with Institution Type ===\n";
$stmt = $pdo->query('
    SELECT dc.id, dc.category_key, dc.category_name, dc.institution_type_id, it.name as inst_type
    FROM dropdown_categories dc
    LEFT JOIN institution_types it ON dc.institution_type_id = it.id
    ORDER BY dc.category_key
');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $type = $row['inst_type'] ?? 'Common (Both)';
    echo "{$row['category_key']}: {$type}\n";
}

echo "\n=== Current Institution Setting ===\n";
$stmt = $pdo->query("SELECT * FROM institution_settings WHERE setting_key = 'institution_type'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "Current: {$row['setting_value']}\n";
} else {
    echo "Not set\n";
}
?>
