<?php
    ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'config.php';

// Admin credentials
$username = "Briz";
$email = "ngelecheibrian89@gmail.com";
$passwordPlain = "Brian@2001";

// Hash the password
$passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);

// 1️⃣ Check if "email" column exists in admins table
$checkColumn = $conn->query("SHOW COLUMNS FROM admins LIKE 'email'");
if ($checkColumn->num_rows == 0) {
    echo "ℹ️ 'email' column not found, creating it...<br>";
    $alter = $conn->query("ALTER TABLE admins ADD COLUMN email VARCHAR(255) UNIQUE AFTER username");
    if ($alter) {
        echo "✅ 'email' column added successfully.<br>";
    } else {
        die("❌ Failed to add 'email' column: " . $conn->error);
    }
}

// 2️⃣ Check if this admin already exists (by username OR email)
$sql = "SELECT id FROM admins WHERE username = ? OR email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing admin
    $sql = "UPDATE admins SET password=?, email=? WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $passwordHash, $email, $username);

    if ($stmt->execute()) {
        echo "🔄 Admin updated successfully!<br>";
    } else {
        die("❌ Update failed: " . $stmt->error);
    }
} else {
    // Insert new admin
    $sql = "INSERT INTO admins (username, email, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $email, $passwordHash);

    if ($stmt->execute()) {
        echo "✅ Admin created successfully!<br>";
    } else {
        die("❌ Insert failed: " . $stmt->error);
    }
}

echo "👉 Username: $username<br>";
echo "👉 Email: $email<br>";
echo "👉 Password: $passwordPlain<br>";

$stmt->close();
$conn->close();
?>
