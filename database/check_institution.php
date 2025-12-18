<?php
$pdo = new PDO('mysql:host=localhost;dbname=edumanage_pro', 'root', '');

echo "=== institution_settings table ===\n";
$stmt = $pdo->query("SELECT * FROM institution_settings WHERE setting_key = 'institution_type'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    print_r($row);
} else {
    echo "institution_type NOT FOUND in institution_settings\n";
}

echo "\n=== settings table ===\n";
$stmt = $pdo->query("SELECT * FROM settings LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['setting_key']} = {$row['setting_value']}\n";
}

echo "\n=== Check if institution_type exists in settings ===\n";
$stmt = $pdo->query("SELECT * FROM settings WHERE setting_key LIKE '%institution%' OR setting_key LIKE '%type%'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['setting_key']} = {$row['setting_value']}\n";
}
?>
