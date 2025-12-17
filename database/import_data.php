<?php
/**
 * Import Sample Data Script
 * Run this file once to populate the database with sample data
 */

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'edumanage_pro';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Connected to database successfully.\n";

    // Read and execute the SQL file
    $sqlFile = __DIR__ . '/sample_data.sql';

    if (!file_exists($sqlFile)) {
        die("SQL file not found: $sqlFile\n");
    }

    $sql = file_get_contents($sqlFile);

    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $successCount = 0;
    $errorCount = 0;

    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        try {
            $pdo->exec($statement);
            $successCount++;
        } catch (PDOException $e) {
            // Skip duplicate key errors, log others
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                echo "Error: " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }
    }

    echo "\nImport completed!\n";
    echo "Successful statements: $successCount\n";
    echo "Errors: $errorCount\n";

    // Verify data
    echo "\n--- Data Verification ---\n";

    $tables = ['students', 'teachers', 'attendance', 'fee_payments', 'library_books', 'transport_routes', 'hostel_blocks', 'exams'];

    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "$table: $count records\n";
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}
?>
