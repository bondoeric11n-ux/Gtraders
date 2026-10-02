<?php
session_start();
include("db_connect.php");

// Protect admin access
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Forbidden"]);
    exit;
}

// Fetch latest chats, order by timestamp
$result = $conn->query("SELECT * FROM admin_chats ORDER BY timestamp ASC");
$chats = [];
while ($row = $result->fetch_assoc()) {
    $chats[] = $row;
}

header('Content-Type: application/json');
echo json_encode($chats);
exit;
?>