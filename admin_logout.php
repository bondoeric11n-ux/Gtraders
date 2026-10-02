<?php
session_start();
include("db_connect.php"); 
include_once 'log_admin_action.php'; // helper you already use

if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    $admin_name = $_SESSION['admin_name'] ?? "Unknown";

    // Record logout in admin_monitor
    log_admin_action($conn, $admin_id, "Logout", "Admin $admin_name logged out");
}

// Clear session data
$_SESSION = [];
session_unset();
session_destroy();

// Redirect to login page
header("Location: admin_login.php");
exit();
