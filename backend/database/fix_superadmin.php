<?php
// Fix superadmin password to be properly hashed

// Include centralized environment configuration (Remote Hostinger DB only)
require_once __DIR__ . '/../config/env.php';
validateRemoteDatabase();

try {
    $pdo = new PDO(
        getDbDsn(),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => DB_CONNECT_TIMEOUT]
    );

    // Hash the password properly
    $hashedPassword = password_hash('super@123', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = 'USR000'");
    $stmt->execute([$hashedPassword]);

    echo "SuperAdmin password updated successfully.\n";
    echo "Hash: $hashedPassword\n";

    // Verify
    $stmt = $pdo->query("SELECT password FROM users WHERE id = 'USR000'");
    $result = $stmt->fetch();
    echo "Stored hash: " . $result['password'] . "\n";
    echo "Verification: " . (password_verify('super@123', $result['password']) ? 'OK' : 'FAILED') . "\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
