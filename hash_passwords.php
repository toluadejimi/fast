<?php
$host = 'localhost';
$db   = 'emzysmsv_testers';
$user = 'emzysmsv_testers'; // Keep your database user here
$pass = 'emzysmsv_testers'; // Keep your database password here
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Connection failed: " . $e->getMessage());
}

// 1. Count how many users still need encryption
$countStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE password = email");
$remaining = $countStmt->fetchColumn();

if ($remaining == 0) {
    echo "<h2>🎉 Success! All user passwords have been encrypted!</h2>";
    echo "<p><strong>CRITICAL: Delete the hash_passwords.php file from your server right now!</strong></p>";
    exit;
}

echo "Remaining users to process: <strong>$remaining</strong>...<br>";
echo "Processing a batch of 100 users, please wait...<br>";
flush();

// 2. Grab just 100 users for this quick turn
$stmt = $pdo->query("SELECT id, email FROM users WHERE password = email LIMIT 100");
$users = $stmt->fetchAll();

$updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");

foreach ($users as $user) {
    $newHash = password_hash($user['email'], PASSWORD_BCRYPT, ['cost' => 10]); // Faster cost setting
    $updateStmt->execute([
        'password' => $newHash,
        'id' => $user['id']
    ]);
}

// 3. Automatically reload the page instantly to do the next batch
echo "<script>
    setTimeout(function(){
        window.location.reload();
    }, 500);
</script>";
?>
