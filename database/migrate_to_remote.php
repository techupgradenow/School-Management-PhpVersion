<?php
/**
 * Database Migration Script: Local to Remote Hostinger
 * EduManage Pro - School Management System
 *
 * This script migrates all data from a local MySQL database
 * to the remote Hostinger MySQL database.
 *
 * IMPORTANT: Run this script ONCE to migrate existing data.
 *
 * Usage: php migrate_to_remote.php
 *
 * Steps:
 * 1. Export schema and data from local database
 * 2. Import to remote Hostinger database
 * 3. Verify data integrity
 */

echo "=============================================================\n";
echo "  EduManage Pro - Database Migration to Remote Hostinger\n";
echo "=============================================================\n\n";

// Remote Hostinger Database Configuration (destination)
$remoteConfig = [
    'host' => '193.203.184.194',
    'port' => '3306',
    'database' => 'u282002960_upgradeschool',
    'username' => 'u282002960_upgradeschool',
    'password' => 'UpgradeSchool@33',
    'charset' => 'utf8mb4'
];

// Local Database Configuration (source) - ONLY FOR MIGRATION
$localConfig = [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'edumanage_pro',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

/**
 * Connect to database
 */
function connectDB($config, $name) {
    try {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 60
        ]);

        echo "[OK] Connected to {$name} database ({$config['host']})\n";
        return $pdo;

    } catch (PDOException $e) {
        echo "[ERROR] Failed to connect to {$name}: " . $e->getMessage() . "\n";
        return null;
    }
}

/**
 * Get all tables from database
 */
function getTables($pdo) {
    $stmt = $pdo->query("SHOW TABLES");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get table create statement
 */
function getTableSchema($pdo, $table) {
    $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
    $result = $stmt->fetch();
    return $result['Create Table'] ?? null;
}

/**
 * Get row count for table
 */
function getRowCount($pdo, $table) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
    return (int) $stmt->fetchColumn();
}

/**
 * Migrate table data
 */
function migrateTable($localPdo, $remotePdo, $table, $batchSize = 1000) {
    $rowCount = getRowCount($localPdo, $table);

    if ($rowCount === 0) {
        echo "  [SKIP] {$table}: No data to migrate\n";
        return ['migrated' => 0, 'errors' => 0];
    }

    echo "  [MIGRATE] {$table}: {$rowCount} rows...\n";

    $migrated = 0;
    $errors = 0;
    $offset = 0;

    // Get columns
    $stmt = $localPdo->query("DESCRIBE `{$table}`");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $columnList = implode('`, `', $columns);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));

    // Prepare insert statement
    $insertSql = "INSERT INTO `{$table}` (`{$columnList}`) VALUES ({$placeholders})";

    // Disable foreign key checks for migration
    $remotePdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    while ($offset < $rowCount) {
        $stmt = $localPdo->query("SELECT * FROM `{$table}` LIMIT {$batchSize} OFFSET {$offset}");
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            try {
                $stmt = $remotePdo->prepare($insertSql);
                $stmt->execute(array_values($row));
                $migrated++;
            } catch (PDOException $e) {
                // Skip duplicate key errors
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    $errors++;
                    if ($errors <= 3) {
                        echo "    [ERROR] " . substr($e->getMessage(), 0, 100) . "\n";
                    }
                }
            }
        }

        $offset += $batchSize;
        $percent = round(($offset / $rowCount) * 100);
        echo "\r    Progress: {$percent}%";
    }

    // Re-enable foreign key checks
    $remotePdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\r    Migrated: {$migrated}/{$rowCount}";
    if ($errors > 0) {
        echo " (Errors: {$errors})";
    }
    echo "\n";

    return ['migrated' => $migrated, 'errors' => $errors];
}

// ============================================================
// MAIN MIGRATION PROCESS
// ============================================================

// Step 1: Connect to local database
echo "\n[Step 1] Connecting to LOCAL database...\n";
$localPdo = connectDB($localConfig, 'LOCAL');

if (!$localPdo) {
    echo "\n[WARNING] Local database not available. Nothing to migrate.\n";
    echo "The application is already configured to use ONLY the remote Hostinger database.\n";
    echo "If you need to create fresh tables, run the schema migrations on the remote database.\n\n";
    exit(0);
}

// Step 2: Connect to remote Hostinger database
echo "\n[Step 2] Connecting to REMOTE Hostinger database...\n";
$remotePdo = connectDB($remoteConfig, 'REMOTE');

if (!$remotePdo) {
    echo "\n[FATAL] Cannot connect to remote Hostinger database.\n";
    echo "Please check:\n";
    echo "  1. Your IP is whitelisted in Hostinger's Remote MySQL settings\n";
    echo "  2. The credentials are correct\n";
    echo "  3. Port 3306 is not blocked by your firewall\n\n";
    exit(1);
}

// Step 3: Get tables from local database
echo "\n[Step 3] Analyzing local database...\n";
$localTables = getTables($localPdo);
echo "  Found " . count($localTables) . " tables in local database\n";

// Step 4: Create schema on remote (if tables don't exist)
echo "\n[Step 4] Creating schema on remote database...\n";
$remoteTables = getTables($remotePdo);
$tablesCreated = 0;

$remotePdo->exec("SET FOREIGN_KEY_CHECKS = 0");

foreach ($localTables as $table) {
    if (!in_array($table, $remoteTables)) {
        $schema = getTableSchema($localPdo, $table);
        if ($schema) {
            try {
                $remotePdo->exec($schema);
                echo "  [CREATED] {$table}\n";
                $tablesCreated++;
            } catch (PDOException $e) {
                echo "  [ERROR] Could not create {$table}: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "  [EXISTS] {$table}\n";
    }
}

$remotePdo->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "  Tables created: {$tablesCreated}\n";

// Step 5: Migrate data
echo "\n[Step 5] Migrating data to remote database...\n";
$totalMigrated = 0;
$totalErrors = 0;

// Define migration order (tables with foreign keys should come after their parents)
$migrationOrder = [
    // Core tables first
    'institution_types',
    'institution_settings',
    'settings',
    'dropdown_categories',
    'dropdown_values',
    'users',
    'user_permissions',
    'classes',
    'sections',
    'subjects',
    'teachers',
    'students',
    'student_documents',
    // Dependent tables
    'attendance',
    'fee_structures',
    'fee_payments',
    'exams',
    'exam_marks',
    'library_books',
    'library_issues',
    'transport_routes',
    'transport_stops',
    'transport_assignments',
    'hostel_blocks',
    'hostel_rooms',
    'hostel_allocations',
    'timetable',
    'syllabus',
    'syllabus_chapters',
    'syllabus_topics',
    'syllabus_progress',
    'notifications',
    'activity_history',
    'payroll',
    // Multi-tenant tables
    'tenants',
    'subscription_plans',
    'plan_modules',
    'system_modules',
    'system_actions',
    'system_roles',
    'system_admins',
    'system_settings',
    'system_audit_log',
    'tenant_invitations',
    '_migrations'
];

// Add any remaining tables not in the order list
foreach ($localTables as $table) {
    if (!in_array($table, $migrationOrder)) {
        $migrationOrder[] = $table;
    }
}

foreach ($migrationOrder as $table) {
    if (in_array($table, $localTables)) {
        $result = migrateTable($localPdo, $remotePdo, $table);
        $totalMigrated += $result['migrated'];
        $totalErrors += $result['errors'];
    }
}

// Step 6: Verify migration
echo "\n[Step 6] Verifying migration...\n";
$verificationPassed = true;

foreach ($localTables as $table) {
    $localCount = getRowCount($localPdo, $table);
    $remoteCount = getRowCount($remotePdo, $table);

    $status = ($localCount === $remoteCount) ? '[OK]' : '[DIFF]';
    if ($localCount !== $remoteCount) {
        $verificationPassed = false;
    }

    echo "  {$status} {$table}: Local={$localCount}, Remote={$remoteCount}\n";
}

// Summary
echo "\n=============================================================\n";
echo "  MIGRATION SUMMARY\n";
echo "=============================================================\n";
echo "  Tables processed: " . count($localTables) . "\n";
echo "  Tables created: {$tablesCreated}\n";
echo "  Total rows migrated: {$totalMigrated}\n";
echo "  Total errors: {$totalErrors}\n";
echo "  Verification: " . ($verificationPassed ? 'PASSED' : 'DIFFERENCES FOUND') . "\n";
echo "=============================================================\n\n";

if ($verificationPassed && $totalErrors === 0) {
    echo "[SUCCESS] Migration completed successfully!\n";
    echo "Your application is now using the remote Hostinger database.\n";
    echo "\nIMPORTANT: You can safely remove or rename your local database.\n";
} else {
    echo "[WARNING] Migration completed with some issues.\n";
    echo "Please review the differences and errors above.\n";
}

echo "\n";
?>
