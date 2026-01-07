<?php
/**
 * Check User Permissions Script
 * Shows permissions stored in the database
 */

$pdo = new PDO('mysql:host=localhost;dbname=edumanage_pro', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

echo "=== USER PERMISSIONS CHECK ===\n\n";

// Show all users with their permissions
$stmt = $pdo->query("
    SELECT u.id, u.name, u.role, up.module, up.can_view, up.can_create, up.can_edit, up.can_delete, up.can_export
    FROM users u
    LEFT JOIN user_permissions up ON u.uuid = up.user_uuid
    ORDER BY u.id DESC, up.module
");

$currentUser = null;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($currentUser !== $row['id']) {
        echo "\n--- {$row['id']}: {$row['name']} ({$row['role']}) ---\n";
        $currentUser = $row['id'];
    }

    if ($row['module']) {
        $perms = [];
        if ($row['can_view']) $perms[] = 'VIEW';
        if ($row['can_create']) $perms[] = 'CREATE';
        if ($row['can_edit']) $perms[] = 'EDIT';
        if ($row['can_delete']) $perms[] = 'DELETE';
        if ($row['can_export']) $perms[] = 'EXPORT';

        echo "  {$row['module']}: " . (empty($perms) ? 'NO PERMISSIONS' : implode(', ', $perms)) . "\n";
    } else {
        echo "  (No permissions set)\n";
    }
}

echo "\n\n=== PERMISSION COUNT BY USER ===\n";
$stmt = $pdo->query("
    SELECT u.id, u.name, COUNT(up.id) as perm_count
    FROM users u
    LEFT JOIN user_permissions up ON u.uuid = up.user_uuid
    GROUP BY u.id, u.name
    ORDER BY u.id
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['id']} ({$row['name']}): {$row['perm_count']} modules\n";
}
