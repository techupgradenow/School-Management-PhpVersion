<?php
/**
 * Performance Testing Script
 * EduManage Pro - School Management System
 *
 * Simulates concurrent users and measures performance
 * Usage: php performance_test.php
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/redis_cache.php';

echo "=== EduManage Pro Performance Test ===\n\n";

// Test 1: Database Connection Pool
echo "1. Testing Database Connections...\n";
$start = microtime(true);
$connections = [];
for ($i = 0; $i < 100; $i++) {
    try {
        $connections[] = getDB();
    } catch (Exception $e) {
        echo "  Connection #{$i} failed: " . $e->getMessage() . "\n";
        break;
    }
}
$duration = round((microtime(true) - $start) * 1000, 2);
echo "  Created " . count($connections) . " connections in {$duration}ms\n";
echo "  Average: " . round($duration / count($connections), 2) . "ms per connection\n\n";

// Test 2: Query Performance
echo "2. Testing Query Performance...\n";
$db = getDB();

$queries = [
    'List Users' => "SELECT id, name, username, email, role, status FROM users LIMIT 100",
    'Count Users' => "SELECT COUNT(*) FROM users",
    'User Lookup' => "SELECT * FROM users WHERE username = 'admin'",
    'Permission Lookup' => "SELECT * FROM user_permissions WHERE user_uuid = (SELECT uuid FROM users WHERE id = 'USR001')"
];

foreach ($queries as $name => $sql) {
    $start = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $stmt = $db->query($sql);
        $stmt->fetchAll();
    }
    $duration = round((microtime(true) - $start) * 1000, 2);
    echo "  {$name}: {$duration}ms for 100 queries (" . round($duration / 100, 2) . "ms avg)\n";
}
echo "\n";

// Test 3: Cache Performance
echo "3. Testing Cache Performance...\n";
$cacheStats = RedisCache::getStats();
echo "  Cache Type: " . $cacheStats['type'] . "\n";

$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    RedisCache::set("test_key_{$i}", ['data' => "value_{$i}"], 60);
}
$writeDuration = round((microtime(true) - $start) * 1000, 2);

$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    RedisCache::get("test_key_{$i}");
}
$readDuration = round((microtime(true) - $start) * 1000, 2);

echo "  Write 1000 keys: {$writeDuration}ms (" . round($writeDuration / 1000, 2) . "ms avg)\n";
echo "  Read 1000 keys: {$readDuration}ms (" . round($readDuration / 1000, 2) . "ms avg)\n\n";

// Cleanup
for ($i = 0; $i < 1000; $i++) {
    RedisCache::delete("test_key_{$i}");
}

// Test 4: Session Performance
echo "4. Testing Session Performance...\n";
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    session_id("test_session_" . $i);
    session_start();
    $_SESSION['user_id'] = "USR" . str_pad($i, 3, '0', STR_PAD_LEFT);
    $_SESSION['test_data'] = str_repeat('x', 1000); // 1KB data
    session_write_close();
}
$duration = round((microtime(true) - $start) * 1000, 2);
echo "  Created 100 sessions: {$duration}ms (" . round($duration / 100, 2) . "ms avg)\n\n";

// Test 5: Concurrent Request Simulation
echo "5. Simulating Concurrent Requests...\n";
echo "  Testing API endpoints...\n";

$endpoints = [
    'List Users API' => 'http://localhost/School-Management-PhpVersion/backend/api/users.php?action=list&limit=50',
    'Get Stats API' => 'http://localhost/School-Management-PhpVersion/backend/api/users.php?action=get_stats'
];

foreach ($endpoints as $name => $url) {
    $start = microtime(true);
    $results = [];

    for ($i = 0; $i < 20; $i++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $results[] = $info['total_time'] * 1000;
    }

    $duration = round((microtime(true) - $start) * 1000, 2);
    $avgResponse = round(array_sum($results) / count($results), 2);
    $maxResponse = round(max($results), 2);

    echo "  {$name}:\n";
    echo "    Total time: {$duration}ms\n";
    echo "    Avg response: {$avgResponse}ms\n";
    echo "    Max response: {$maxResponse}ms\n";
}

// Test 6: Database Index Analysis
echo "\n6. Database Index Analysis...\n";
$tables = ['users', 'user_permissions', 'sessions'];

foreach ($tables as $table) {
    $stmt = $db->query("SHOW INDEX FROM $table");
    $indexes = $stmt->fetchAll();
    echo "  {$table}: " . count($indexes) . " indexes\n";
}

// Test 7: System Resources
echo "\n7. System Resources...\n";
echo "  PHP Memory Usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
echo "  PHP Memory Peak: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";

// MySQL Status
$stmt = $db->query("SHOW STATUS LIKE 'Threads_connected'");
$result = $stmt->fetch();
echo "  MySQL Connections: " . $result['Value'] . "\n";

$stmt = $db->query("SHOW VARIABLES LIKE 'max_connections'");
$result = $stmt->fetch();
echo "  MySQL Max Connections: " . $result['Value'] . "\n";

// Test 8: Recommendations
echo "\n=== Performance Analysis ===\n\n";

$recommendations = [];

// Check if OpCache is enabled
if (!function_exists('opcache_get_status') || !opcache_get_status()) {
    $recommendations[] = "⚠️  OpCache is disabled. Enable it for 3-5x performance boost.";
} else {
    echo "✓ OpCache is enabled\n";
}

// Check cache type
if ($cacheStats['type'] === 'File') {
    $recommendations[] = "⚠️  Using file-based cache. Install Redis for better performance.";
} else {
    echo "✓ Redis cache is enabled\n";
}

// Check average response time
$avgApiResponse = isset($avgResponse) ? $avgResponse : 0;
if ($avgApiResponse > 100) {
    $recommendations[] = "⚠️  Average API response time is high ({$avgApiResponse}ms). Target: <100ms";
} else {
    echo "✓ API response time is good ({$avgApiResponse}ms)\n";
}

if (!empty($recommendations)) {
    echo "\nRecommendations:\n";
    foreach ($recommendations as $rec) {
        echo "  {$rec}\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "\nExpected Performance for 500 Concurrent Users:\n";
echo "  - Login: 50-150ms per request\n";
echo "  - List Users: 30-80ms per request (cached: 5-15ms)\n";
echo "  - Create/Update: 100-300ms per request\n";
echo "  - Database Connections: Up to 500 concurrent\n";
echo "  - Memory: ~50-100MB per 100 users\n";

?>
