<?php
/**
 * Check Attendance Data
 * Quick script to verify database setup
 */

require_once __DIR__ . '/../config/db.php';

try {
    $db = getDB();

    // Check students
    $stmt = $db->query('SELECT COUNT(*) as count FROM students');
    $studentsCount = $stmt->fetch()['count'];
    echo "Students in DB: $studentsCount\n";

    // Check attendance
    $stmt2 = $db->query('SELECT COUNT(*) as count FROM attendance');
    $attendanceCount = $stmt2->fetch()['count'];
    echo "Attendance records in DB: $attendanceCount\n\n";

    // Show sample students
    echo "Sample students:\n";
    $stmt3 = $db->query('SELECT id, name, class, section, roll_no FROM students LIMIT 5');
    while($row = $stmt3->fetch()) {
        echo "  - {$row['id']}: {$row['name']} (Class {$row['class']}-{$row['section']}, Roll: {$row['roll_no']})\n";
    }

    // Check if attendance table has correct structure
    echo "\nAttendance table structure:\n";
    $stmt4 = $db->query('DESCRIBE attendance');
    while($row = $stmt4->fetch()) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
