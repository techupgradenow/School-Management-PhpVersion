<?php
// Include centralized environment configuration (Remote Hostinger DB only)
require_once __DIR__ . '/backend/config/env.php';
validateRemoteDatabase();

$pdo = new PDO(getDbDsn(), DB_USER, DB_PASS);

echo "=== DATA MAPPING FOR SYLLABUS (Remote Hostinger) ===\n\n";

echo "1. CLASSES in Students table:\n";
$stmt = $pdo->query("SELECT DISTINCT class FROM students ORDER BY class");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "   - " . $row['class'] . PHP_EOL;
}

echo "\n2. SECTIONS in Students table:\n";
$stmt = $pdo->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL ORDER BY section");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "   - " . $row['section'] . PHP_EOL;
}

echo "\n3. SUBJECTS in Teachers table:\n";
$stmt = $pdo->query("SELECT DISTINCT subject FROM teachers WHERE subject IS NOT NULL AND subject != '' ORDER BY subject");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "   - " . $row['subject'] . PHP_EOL;
}

echo "\n4. EXAMS table structure:\n";
$stmt = $pdo->query("SELECT DISTINCT class, subject FROM exams ORDER BY class, subject LIMIT 15");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "   - Class: " . $row['class'] . " | Subject: " . $row['subject'] . PHP_EOL;
}

echo "\n5. TIMETABLE table check:\n";
$stmt = $pdo->query("SHOW TABLES LIKE 'timetable%'");
while($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo "   - Table: " . $row[0] . PHP_EOL;
}

echo "\n=== RECOMMENDATION ===\n";
echo "Syllabus should use the same class/subject values as:\n";
echo "- Students table (class, section)\n";
echo "- Teachers table (subject)\n";
echo "- Exams table (class, subject)\n";
