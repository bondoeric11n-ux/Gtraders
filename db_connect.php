<?php
$host = "sql104.infinityfree.com";
$user = "if0_40063076";
$pass = "y89JNgwraWUgZ24";
$dbname = "if0_40063076_Gtraders";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
require_once __DIR__ . '/system_logger.php';
syslog_boot($conn);
$conn->set_charset("utf8mb4");

?>