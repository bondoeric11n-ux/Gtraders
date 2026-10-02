<?php
session_start();
include("db_connect.php");
header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$user_id = (int)$_GET['user_id'];

// Fetch messages oldest to newest
$stmt = $conn->prepare("SELECT * FROM admin_chats WHERE user_id=? ORDER BY created_at ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Mark admin messages as read when user's browser fetches the chat
$conn->query("UPDATE admin_chats SET is_read=1 WHERE user_id=$user_id AND sender_type='admin'");

$html = '';
if (empty($messages)) {
    $username = $conn->query("SELECT username FROM users WHERE id=$user_id")->fetch_assoc()['username'] ?? 'there';
    $html = '<div class="chat-empty"><i class="fas fa-headset" style="font-size: 2rem; margin-bottom: 10px; color: #d4af37;"></i><p>Hi '.htmlspecialchars($username).'! 👋<br>How can we help you today?</p></div>';
} else {
    foreach ($messages as $c) {
        $html .= '<div class="chat-bubble ' . $c['sender_type'] . '">'
            . htmlspecialchars($c['message'])
            . '<div class="chat-time">' . date('H:i', strtotime($c['created_at'])) . '</div>'
            . '</div>';
    }
}

echo json_encode(['success' => true, 'html' => $html]);