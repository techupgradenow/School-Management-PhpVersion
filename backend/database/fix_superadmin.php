<?php
// Fix superadmin password to be properly hashed

$host = 'localhost';
$dbname = 'edumanage_pro';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
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
