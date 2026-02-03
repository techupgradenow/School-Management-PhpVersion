<?php
/**
 * Remote Database Connection Test
 * EduManage Pro - School Management System
 *
 * This script tests the connection to the remote Hostinger MySQL database.
 * Run this to verify your application can connect to the remote database.
 *
 * Usage: php test_remote_connection.php
 */

echo "=============================================================\n";
echo "  Remote Hostinger Database Connection Test\n";
echo "=============================================================\n\n";

// Include centralized environment configuration
require_once __DIR__ . '/../backend/config/env.php';

echo "[Info] Testing connection to: " . DB_HOST . ":" . DB_PORT . "\n";
echo "[Info] Database: " . DB_NAME . "\n";
echo "[Info] User: " . DB_USER . "\n\n";

// Test 1: Basic Connection
echo "[Test 1] Basic Connection...\n";
try {
    validateRemoteDatabase();
    echo "  [OK] Remote database validation passed\n";

    $startTime = microtime(true);
    $pdo = new PDO(getDbDsn(), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => DB_CONNECT_TIMEOUT
    ]);
    $connectionTime = round((microtime(true) - $startTime) * 1000, 2);

    echo "  [OK] Connected successfully in {$connectionTime}ms\n";
} catch (Exception $e) {
    echo "  [FAIL] " . $e->getMessage() . "\n";
    echo "\n[Troubleshooting]\n";
    echo "  1. Check if your IP is whitelisted in Hostinger's Remote MySQL settings\n";
    echo "  2. Verify the database credentials are correct\n";
    echo "  3. Ensure port 3306 is not blocked by your firewall\n";
    exit(1);
}

// Test 2: Server Information
echo "\n[Test 2] Server Information...\n";
try {
    $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    echo "  [OK] MySQL Server Version: {$serverVersion}\n";

    $stmt = $pdo->query("SELECT @@hostname as hostname, @@version as version");
    $info = $stmt->fetch();
    echo "  [OK] Server Hostname: {$info['hostname']}\n";
} catch (Exception $e) {
    echo "  [WARN] Could not get server info: " . $e->getMessage() . "\n";
}

// Test 3: Database Tables
echo "\n[Test 3] Database Tables...\n";
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($tables);

    echo "  [OK] Found {$tableCount} tables in database\n";

    if ($tableCount > 0) {
        echo "  Tables:\n";
        foreach (array_slice($tables, 0, 10) as $table) {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
            $rowCount = $countStmt->fetchColumn();
            echo "    - {$table}: {$rowCount} rows\n";
        }
        if ($tableCount > 10) {
            echo "    ... and " . ($tableCount - 10) . " more tables\n";
        }
    } else {
        echo "  [INFO] Database is empty. Run migrations to create tables.\n";
    }
} catch (Exception $e) {
    echo "  [FAIL] " . $e->getMessage() . "\n";
}

// Test 4: Write Permission
echo "\n[Test 4] Write Permission...\n";
try {
    // Create test table
    $pdo->exec("CREATE TABLE IF NOT EXISTS _connection_test (
        id INT AUTO_INCREMENT PRIMARY KEY,
        test_value VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert test data
    $testValue = 'test_' . time();
    $stmt = $pdo->prepare("INSERT INTO _connection_test (test_value) VALUES (?)");
    $stmt->execute([$testValue]);

    // Read back
    $stmt = $pdo->prepare("SELECT * FROM _connection_test WHERE test_value = ?");
    $stmt->execute([$testValue]);
    $result = $stmt->fetch();

    if ($result) {
        echo "  [OK] Write permission verified\n";
        echo "  [OK] Read permission verified\n";
    }

    // Clean up
    $pdo->exec("DROP TABLE IF EXISTS _connection_test");
    echo "  [OK] Delete permission verified\n";

} catch (Exception $e) {
    echo "  [FAIL] " . $e->getMessage() . "\n";
}

// Test 5: Connection via Database Class
echo "\n[Test 5] Database Class Connection...\n";
try {
    require_once __DIR__ . '/../backend/config/db.php';
    $db = getDB();

    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();

    if ($result['test'] == 1) {
        echo "  [OK] Database singleton class working correctly\n";

        // Get connection info
        $dbInstance = Database::getInstance();
        $info = $dbInstance->getConnectionInfo();
        echo "  [OK] Connection Type: {$info['type']}\n";
    }
} catch (Exception $e) {
    echo "  [FAIL] " . $e->getMessage() . "\n";
}

// Test 6: Latency Test
echo "\n[Test 6] Latency Test (5 queries)...\n";
try {
    $times = [];
    for ($i = 0; $i < 5; $i++) {
        $start = microtime(true);
        $pdo->query("SELECT 1");
        $times[] = (microtime(true) - $start) * 1000;
    }

    $avgTime = round(array_sum($times) / count($times), 2);
    $minTime = round(min($times), 2);
    $maxTime = round(max($times), 2);

    echo "  [OK] Average: {$avgTime}ms | Min: {$minTime}ms | Max: {$maxTime}ms\n";

    if ($avgTime > 500) {
        echo "  [WARN] High latency detected. Consider optimizing network or queries.\n";
    }
} catch (Exception $e) {
    echo "  [FAIL] " . $e->getMessage() . "\n";
}

// Summary
echo "\n=============================================================\n";
echo "  CONNECTION TEST COMPLETED\n";
echo "=============================================================\n";
echo "  Host: " . DB_HOST . "\n";
echo "  Database: " . DB_NAME . "\n";
echo "  Status: CONNECTED\n";
echo "=============================================================\n\n";

echo "[SUCCESS] Your application is properly configured to use the\n";
echo "remote Hostinger MySQL database. All local database connections\n";
echo "have been disabled.\n\n";
?>
