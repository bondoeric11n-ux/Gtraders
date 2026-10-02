<?php
include("db_connect.php");

// Change these
$super_admin_id = 13;
$new_password = "MyStrongPassword123"; // choose a strong one

$hash = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE admins SET super_admin_pass=? WHERE id=?");
$stmt->bind_param("si", $hash, $super_admin_id);
$stmt->execute();
$stmt->close();

echo "Super admin password set!";
?>
