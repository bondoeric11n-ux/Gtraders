<?php
include("db_connect.php");

// Super admin details
$username = "GIBAL";
$email = "gkhalibson11@gmail.com";
$password = "Marine@254!!";

// Check if admin already exists
$stmt = $conn->prepare("SELECT id FROM admins WHERE username=? OR email=? LIMIT 1");
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$exists){
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'super_admin';
    $ins = $conn->prepare("INSERT INTO admins (username, email, password, role, created_at) VALUES (?,?,?,?,NOW())");
    $ins->bind_param("ssss", $username, $email, $hash, $role);
    if($ins->execute()){
        echo "Super admin '{$username}' created successfully.";
    } else {
        echo "Failed to create {$username}: ".$ins->error;
    }
    $ins->close();
} else {
    echo "Admin '{$username}' or email '{$email}' already exists.";
}
?>
