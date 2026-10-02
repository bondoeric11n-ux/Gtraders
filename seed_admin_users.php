<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gtraders";

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ===== Add default admin =====
$adminUsername = "admin";
$adminEmail = "admin@gtraders.com";
$adminPassword = password_hash("admin123", PASSWORD_DEFAULT); // default password

$sql = "INSERT INTO admins (username, email, password)
        SELECT * FROM (SELECT '$adminUsername','$adminEmail','$adminPassword') AS tmp
        WHERE NOT EXISTS (
            SELECT username FROM admins WHERE username = '$adminUsername'
        ) LIMIT 1";
$conn->query($sql) or die("Error inserting default admin: " . $conn->error);
echo "Default admin ensured.<br>";

// ===== Optional: Add sample users =====
$users = [
    ['username' => 'user1', 'email' => 'user1@gtraders.com', 'password' => password_hash('pass123', PASSWORD_DEFAULT), 'account_balance' => 1000],
    ['username' => 'user2', 'email' => 'user2@gtraders.com', 'password' => password_hash('pass123', PASSWORD_DEFAULT), 'account_balance' => 500],
];

foreach ($users as $u) {
    $sql = "INSERT INTO users (username, email, password, account_balance)
            SELECT * FROM (SELECT '{$u['username']}','{$u['email']}','{$u['password']}', {$u['account_balance']}) AS tmp
            WHERE NOT EXISTS (
                SELECT username FROM users WHERE username = '{$u['username']}'
            ) LIMIT 1";
    $conn->query($sql) or die("Error inserting user {$u['username']}: " . $conn->error);
    echo "User {$u['username']} ensured.<br>";
}

echo "<br>Seeder completed successfully!";
$conn->close();
?>
