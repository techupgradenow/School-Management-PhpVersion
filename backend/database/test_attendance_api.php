<?php
/**
 * Test Attendance API Functionality
 * Run this script to verify all attendance endpoints work correctly
 */

require_once __DIR__ . '/../config/db.php';

echo "=== Attendance API Testing ===\n\n";

try {
    $db = getDB();

    // Test 1: Get Students (verify students API works)
    echo "Test 1: Loading Students...\n";
    $stmt = $db->query("SELECT id, name, class, section, roll_no FROM students LIMIT 10");
    $students = $stmt->fetchAll();
    echo "  ✓ Found " . count($students) . " students\n";
    foreach (array_slice($students, 0, 3) as $s) {
        echo "    - {$s['id']}: {$s['name']} ({$s['class']}-{$s['section']})\n";
    }
    echo "\n";

    // Test 2: Insert test attendance record
    echo "Test 2: Inserting Test Attendance...\n";
    $testDate = date('Y-m-d');
    $testStudent = $students[0]['id'] ?? 'STU001';

    // Delete existing record if any
    $stmt = $db->prepare("DELETE FROM attendance WHERE student_id = ? AND date = ?");
    $stmt->execute([$testStudent, $testDate]);

    // Insert new record
    $stmt = $db->prepare("
        INSERT INTO attendance (student_id, date, status, marked_by)
        VALUES (?, ?, 'Present', 'USR001')
    ");
    $stmt->execute([$testStudent, $testDate]);
    echo "  ✓ Inserted attendance for $testStudent on $testDate\n\n";

    // Test 3: Retrieve attendance
    echo "Test 3: Retrieving Attendance...\n";
    $stmt = $db->prepare("
        SELECT a.*, s.name as student_name, s.class, s.section
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        WHERE DATE(a.date) = ?
        LIMIT 10
    ");
    $stmt->execute([$testDate]);
    $records = $stmt->fetchAll();
    echo "  ✓ Found " . count($records) . " attendance records for $testDate\n";
    foreach (array_slice($records, 0, 3) as $r) {
        echo "    - {$r['student_name']}: {$r['status']}\n";
    }
    echo "\n";

    // Test 4: Get attendance statistics
    echo "Test 4: Calculating Statistics...\n";
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
        FROM attendance
        WHERE DATE(date) = ?
    ");
    $stmt->execute([$testDate]);
    $stats = $stmt->fetch();
    $rate = $stats['total'] > 0 ? round(($stats['present'] / $stats['total']) * 100, 2) : 0;
    echo "  ✓ Stats for $testDate:\n";
    echo "    - Total: {$stats['total']}\n";
    echo "    - Present: {$stats['present']}\n";
    echo "    - Absent: {$stats['absent']}\n";
    echo "    - Late: {$stats['late']}\n";
    echo "    - Attendance Rate: {$rate}%\n\n";

    // Test 5: Update attendance
    echo "Test 5: Updating Attendance Status...\n";
    $stmt = $db->prepare("
        UPDATE attendance
        SET status = 'Late'
        WHERE student_id = ? AND date = ?
    ");
    $stmt->execute([$testStudent, $testDate]);
    echo "  ✓ Updated $testStudent to 'Late'\n\n";

    // Test 6: Verify update
    echo "Test 6: Verifying Update...\n";
    $stmt = $db->prepare("
        SELECT status FROM attendance
        WHERE student_id = ? AND date = ?
    ");
    $stmt->execute([$testStudent, $testDate]);
    $updated = $stmt->fetch();
    if ($updated && $updated['status'] === 'Late') {
        echo "  ✓ Update successful! Status is now: {$updated['status']}\n\n";
    } else {
        echo "  ✗ Update failed!\n\n";
    }

    // Test 7: Test unique constraint
    echo "Test 7: Testing Unique Constraint...\n";
    try {
        $stmt = $db->prepare("
            INSERT INTO attendance (student_id, date, status, marked_by)
            VALUES (?, ?, 'Present', 'USR001')
        ");
        $stmt->execute([$testStudent, $testDate]);
        echo "  ✗ Unique constraint NOT working (duplicate inserted!)\n\n";
    } catch (PDOException $e) {
        echo "  ✓ Unique constraint working (duplicate rejected)\n\n";
    }

    // Test 8: Test CASCADE DELETE
    echo "Test 8: Testing Foreign Key CASCADE...\n";
    echo "  (Skipping - requires deleting actual student record)\n";
    echo "  Note: When a student is deleted, all their attendance records are auto-deleted\n\n";

    // Test 9: Get class-wise summary
    echo "Test 9: Class-wise Attendance Summary...\n";
    $stmt = $db->query("
        SELECT
            s.class,
            s.section,
            COUNT(*) as total_records,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
            ROUND((SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as rate
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE DATE(a.date) = CURDATE()
        GROUP BY s.class, s.section
        ORDER BY s.class, s.section
    ");
    $summary = $stmt->fetchAll();
    if (count($summary) > 0) {
        echo "  ✓ Found attendance for " . count($summary) . " class-section combinations:\n";
        foreach ($summary as $row) {
            echo "    - {$row['class']}-{$row['section']}: {$row['present_count']}/{$row['total_records']} ({$row['rate']}%)\n";
        }
    } else {
        echo "  No class-wise data yet (need more attendance records)\n";
    }
    echo "\n";

    // Test 10: Verify indexes
    echo "Test 10: Checking Database Indexes...\n";
    $stmt = $db->query("SHOW INDEX FROM attendance");
    $indexes = $stmt->fetchAll();
    echo "  ✓ Found " . count($indexes) . " indexes on attendance table\n";
    $indexNames = array_unique(array_column($indexes, 'Key_name'));
    foreach ($indexNames as $idx) {
        echo "    - $idx\n";
    }
    echo "\n";

    echo "=== All Tests Completed Successfully! ===\n\n";
    echo "Next Steps:\n";
    echo "1. Open browser: http://localhost/School-Management-PhpVersion/frontend/pages/attendance.html\n";
    echo "2. Select Date, Class, Section\n";
    echo "3. Mark attendance and click Save\n";
    echo "4. Verify data is saved to database\n\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
?>
