<?php
/**
 * Migration Script: UUID-based User System with Separate Permissions Table
 * EduManage Pro - School Management System
 *
 * Run this script once to migrate the database structure
 * Usage: php run_migration.php
 */

// Database configuration
$host = 'localhost';
$dbname = 'edumanage_pro';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "Connected to database successfully.\n\n";

    // Check if migration already done
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'uuid'");
    $uuidExists = $stmt->fetch();

    if ($uuidExists) {
        echo "UUID column already exists. Checking if migration is complete...\n";
    } else {
        echo "Step 1: Adding UUID column to users table...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN uuid CHAR(36) NULL AFTER id");
        echo "Done.\n";
    }

    // Generate UUIDs for existing users
    echo "Step 2: Generating UUIDs for existing users...\n";
    $pdo->exec("UPDATE users SET uuid = UUID() WHERE uuid IS NULL");
    echo "Done.\n";

    // Make UUID NOT NULL if not already
    echo "Step 3: Ensuring UUID is NOT NULL...\n";
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN uuid CHAR(36) NOT NULL");
    } catch (PDOException $e) {
        // Column might already be NOT NULL
    }

    // Add unique index if not exists
    echo "Step 4: Adding unique index on UUID...\n";
    try {
        $pdo->exec("ALTER TABLE users ADD UNIQUE INDEX idx_users_uuid (uuid)");
    } catch (PDOException $e) {
        echo "Index already exists or error: " . $e->getMessage() . "\n";
    }
    echo "Done.\n";

    // Create user_permissions table
    echo "Step 5: Creating user_permissions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_uuid CHAR(36) NOT NULL,
            module VARCHAR(100) NOT NULL,
            can_view TINYINT(1) NOT NULL DEFAULT 0,
            can_create TINYINT(1) NOT NULL DEFAULT 0,
            can_edit TINYINT(1) NOT NULL DEFAULT 0,
            can_delete TINYINT(1) NOT NULL DEFAULT 0,
            can_export TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_module (user_uuid, module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Done.\n";

    // Add foreign key if not exists
    echo "Step 6: Adding foreign key constraint...\n";
    try {
        $pdo->exec("
            ALTER TABLE user_permissions
            ADD CONSTRAINT fk_permissions_user FOREIGN KEY (user_uuid)
            REFERENCES users(uuid) ON DELETE CASCADE ON UPDATE CASCADE
        ");
    } catch (PDOException $e) {
        echo "Foreign key might already exist: " . $e->getMessage() . "\n";
    }
    echo "Done.\n";

    // Add indexes
    echo "Step 7: Adding indexes...\n";
    try {
        $pdo->exec("CREATE INDEX idx_permissions_user_uuid ON user_permissions(user_uuid)");
    } catch (PDOException $e) {
        // Index might already exist
    }
    try {
        $pdo->exec("CREATE INDEX idx_permissions_module ON user_permissions(module)");
    } catch (PDOException $e) {
        // Index might already exist
    }
    echo "Done.\n";

    // Migrate existing JSON permissions to new table
    echo "Step 8: Migrating existing JSON permissions to user_permissions table...\n";

    $stmt = $pdo->query("SELECT uuid, permissions FROM users WHERE permissions IS NOT NULL AND permissions != ''");
    $users = $stmt->fetchAll();

    $insertStmt = $pdo->prepare("
        INSERT INTO user_permissions (user_uuid, module, can_view, can_create, can_edit, can_delete, can_export)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            can_view = VALUES(can_view),
            can_create = VALUES(can_create),
            can_edit = VALUES(can_edit),
            can_delete = VALUES(can_delete),
            can_export = VALUES(can_export)
    ");

    $migratedCount = 0;
    foreach ($users as $user) {
        $permissions = json_decode($user['permissions'], true);
        if (!$permissions) continue;

        foreach ($permissions as $module => $perms) {
            $canView = isset($perms['view']) ? ($perms['view'] ? 1 : 0) : 0;
            $canCreate = isset($perms['create']) ? ($perms['create'] ? 1 : 0) : 0;
            $canEdit = isset($perms['edit']) ? ($perms['edit'] ? 1 : 0) : 0;
            $canDelete = isset($perms['delete']) ? ($perms['delete'] ? 1 : 0) : 0;
            $canExport = isset($perms['export']) ? ($perms['export'] ? 1 : 0) : 0;

            $insertStmt->execute([
                $user['uuid'],
                $module,
                $canView,
                $canCreate,
                $canEdit,
                $canDelete,
                $canExport
            ]);
            $migratedCount++;
        }
    }
    echo "Migrated $migratedCount permission records.\n";

    // Verify migration
    echo "\n=== Migration Complete ===\n\n";

    echo "Users table structure:\n";
    $stmt = $pdo->query("DESCRIBE users");
    while ($row = $stmt->fetch()) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }

    echo "\nuser_permissions table structure:\n";
    $stmt = $pdo->query("DESCRIBE user_permissions");
    while ($row = $stmt->fetch()) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }

    echo "\nCurrent users with UUIDs:\n";
    $stmt = $pdo->query("SELECT id, uuid, name, username FROM users");
    while ($row = $stmt->fetch()) {
        echo "  - {$row['id']}: {$row['name']} (UUID: {$row['uuid']})\n";
    }

    echo "\nPermissions in new table:\n";
    $stmt = $pdo->query("
        SELECT u.id, u.name, COUNT(p.id) as perm_count
        FROM users u
        LEFT JOIN user_permissions p ON u.uuid = p.user_uuid
        GROUP BY u.uuid
    ");
    while ($row = $stmt->fetch()) {
        echo "  - {$row['id']} ({$row['name']}): {$row['perm_count']} permissions\n";
    }

    echo "\n=== Migration Successful! ===\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
