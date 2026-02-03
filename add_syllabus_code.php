<?php
// Include centralized environment configuration (Remote Hostinger DB only)
require_once __DIR__ . '/backend/config/env.php';
validateRemoteDatabase();

$pdo = new PDO(getDbDsn(), DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== ADDING SYLLABUS_CODE COLUMN (Remote Hostinger) ===\n\n";

// Check if column exists
$stmt = $pdo->query("SHOW COLUMNS FROM syllabus LIKE 'syllabus_code'");
if ($stmt->rowCount() == 0) {
    $pdo->exec("ALTER TABLE syllabus ADD COLUMN syllabus_code VARCHAR(50) UNIQUE AFTER id");
    echo "[OK] Added syllabus_code column\n";

    // Add index
    $pdo->exec("ALTER TABLE syllabus ADD INDEX idx_syllabus_code (syllabus_code)");
    echo "[OK] Added index on syllabus_code\n";
} else {
    echo "[EXISTS] syllabus_code column already exists\n";
}

// Update existing records with generated codes
$stmt = $pdo->query("SELECT id, class, section, subject, academic_year FROM syllabus WHERE syllabus_code IS NULL OR syllabus_code = ''");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) > 0) {
    echo "\nUpdating " . count($rows) . " existing records...\n";

    $updateStmt = $pdo->prepare("UPDATE syllabus SET syllabus_code = :code WHERE id = :id");

    foreach ($rows as $row) {
        // Generate code: SYL-{CLASS}-{SECTION}-{SUBJECTCODE}-{YEAR}
        $classNum = preg_replace('/[^0-9]/', '', $row['class']);
        $classNum = str_pad($classNum, 2, '0', STR_PAD_LEFT);
        $section = strtoupper(substr($row['section'] ?: 'A', 0, 1));

        // Subject code (first 3-4 chars)
        $subjectCodes = [
            'mathematics' => 'MATH',
            'science' => 'SCI',
            'english' => 'ENG',
            'hindi' => 'HIN',
            'social' => 'SST',
            'physics' => 'PHY',
            'chemistry' => 'CHE',
            'biology' => 'BIO',
            'computer' => 'CS',
            'history' => 'HIS',
            'geography' => 'GEO',
            'economics' => 'ECO'
        ];

        $subjectLower = strtolower($row['subject']);
        $subjectCode = 'SUB';
        foreach ($subjectCodes as $key => $code) {
            if (strpos($subjectLower, $key) !== false) {
                $subjectCode = $code;
                break;
            }
        }
        if ($subjectCode === 'SUB') {
            $subjectCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $row['subject']), 0, 4));
        }

        // Year code (last 2 digits of each year)
        $yearParts = explode('-', $row['academic_year']);
        $yearCode = substr($yearParts[0], -2) . substr($yearParts[1] ?? $yearParts[0], -2);

        $syllabusCode = "SYL-{$classNum}-{$section}-{$subjectCode}-{$yearCode}";

        $updateStmt->execute([':code' => $syllabusCode, ':id' => $row['id']]);
        echo "  Updated {$row['id']} -> {$syllabusCode}\n";
    }
}

echo "\n=== DONE ===\n";
