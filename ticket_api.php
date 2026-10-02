<?php
session_start();
include('db_connect.php');
header('Content-Type: application/json');

function fail($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

function ok($data) {
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

$context = $_REQUEST['context'] ?? '';
$role = null;
$user_id = 0;
$admin_id = 0;

if ($context === 'user') {
    if (!isset($_SESSION['user_id'])) fail('Unauthorized');
    $role = 'user';
    $user_id = (int)$_SESSION['user_id'];
} elseif ($context === 'admin') {
    if (!isset($_SESSION['admin_id'])) fail('Unauthorized');
    $role = 'admin';
    $admin_id = (int)$_SESSION['admin_id'];
} else {
    if (isset($_SESSION['admin_id'])) {
        $role = 'admin';
        $admin_id = (int)$_SESSION['admin_id'];
    } elseif (isset($_SESSION['user_id'])) {
        $role = 'user';
        $user_id = (int)$_SESSION['user_id'];
    } else {
        fail('Unauthorized');
    }
}

$action = $_REQUEST['action'] ?? '';
$ticket_type = $_REQUEST['ticket_type'] ?? '';
$ticket_id = (int)($_REQUEST['ticket_id'] ?? 0);

if (!in_array($ticket_type, ['complaint', 'dispute'], true) || $ticket_id <= 0) {
    fail('Invalid ticket');
}

function get_ticket($conn, $ticket_type, $ticket_id) {
    if ($ticket_type === 'dispute') {
        $stmt = $conn->prepare("SELECT id, user_id, status FROM disputes WHERE id = ?");
    } else {
        $stmt = $conn->prepare("SELECT id, user_id, status FROM complaints WHERE id = ?");
    }
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

function set_ticket_status($conn, $ticket_type, $ticket_id, $status) {
    $table = $ticket_type === 'dispute' ? 'disputes' : 'complaints';
    $stmt = $conn->prepare("UPDATE {$table} SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $ticket_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function render_ticket_message($msg, $role) {
    $is_mine = ($role === 'admin' && !empty($msg['admin_id'])) || ($role === 'user' && !empty($msg['user_id']));
    $class = $is_mine ? 'mine' : 'theirs';

    if ($role === 'user') {
        $sender = $is_mine ? 'You' : ($msg['admin_name'] ?: 'Admin');
    } else {
        $sender = $is_mine ? ($msg['admin_name'] ?: 'Admin') : ($msg['user_name'] ?: 'User');
    }

    $time = date('M d, Y H:i', strtotime($msg['created_at']));

    return '<div class="ticket-msg ' . $class . '" data-id="' . (int)$msg['id'] . '">'
        . '<div class="ticket-msg-meta"><span>' . htmlspecialchars($sender) . '</span><span>' . htmlspecialchars($time) . '</span></div>'
        . '<div class="ticket-msg-body">' . nl2br(htmlspecialchars($msg['message'])) . '</div>'
        . '</div>';
}

$ticket = get_ticket($conn, $ticket_type, $ticket_id);
if (!$ticket) fail('Ticket not found');

if ($role === 'user' && (int)$ticket['user_id'] !== $user_id) {
    fail('Forbidden');
}

if ($action === 'messages') {
    $last_id = (int)($_REQUEST['last_id'] ?? 0);

    $stmt = $conn->prepare("
        SELECT tf.id, tf.message, tf.admin_id, tf.user_id, tf.created_at,
               COALESCE(a.username, 'Admin') AS admin_name,
               COALESCE(u.username, 'User') AS user_name
        FROM ticket_followups tf
        LEFT JOIN admins a ON tf.admin_id = a.id
        LEFT JOIN users u ON tf.user_id = u.id
        WHERE tf.ticket_type = ? AND tf.ticket_id = ? AND tf.id > ?
        ORDER BY tf.id ASC
    ");
    $stmt->bind_param("sii", $ticket_type, $ticket_id, $last_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $html = '';
    $new_last_id = $last_id;

    while ($m = $result->fetch_assoc()) {
        $html .= render_ticket_message($m, $role);
        $new_last_id = (int)$m['id'];
    }

    $stmt->close();

    ok([
        'html' => $html,
        'last_id' => $new_last_id,
        'status' => $ticket['status']
    ]);
}

if ($action === 'send_message') {
    $message = trim($_POST['message'] ?? '');
    if ($message === '') fail('Message cannot be empty');

    if ($role === 'user') {
        $stmt = $conn->prepare("INSERT INTO ticket_followups (ticket_type, ticket_id, user_id, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siis", $ticket_type, $ticket_id, $user_id, $message);
    } else {
        $stmt = $conn->prepare("INSERT INTO ticket_followups (ticket_type, ticket_id, admin_id, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siis", $ticket_type, $ticket_id, $admin_id, $message);
    }

    if (!$stmt->execute()) {
        fail('Failed to send message: ' . $stmt->error);
    }

    $new_id = $conn->insert_id;
    $stmt->close();

    $msg = [
        'id' => $new_id,
        'message' => $message,
        'admin_id' => $role === 'admin' ? $admin_id : 0,
        'user_id' => $role === 'user' ? $user_id : 0,
        'created_at' => date('Y-m-d H:i:s'),
        'admin_name' => $_SESSION['admin_username'] ?? 'Admin',
        'user_name' => 'You'
    ];

    $html = render_ticket_message($msg, $role);
    $current_status = $ticket['status'];

    if ($role === 'user') {
        $new_status = $ticket_type === 'dispute' ? 'pending' : 'open';
        if (!set_ticket_status($conn, $ticket_type, $ticket_id, $new_status)) {
            fail('Message sent but status update failed');
        }
        $current_status = $new_status;
    } elseif (in_array($current_status, ['open', 'pending'], true)) {
        if (!set_ticket_status($conn, $ticket_type, $ticket_id, 'in_progress')) {
            fail('Message sent but status update failed');
        }
        $current_status = 'in_progress';
    }

    ok([
        'html' => $html,
        'last_id' => $new_id,
        'status' => $current_status
    ]);
}

if ($action === 'update_status') {
    if ($role !== 'admin') fail('Only admin can update status');

    $status = $_POST['status'] ?? '';
    $allowed = $ticket_type === 'dispute'
        ? ['pending', 'in_progress', 'resolved', 'rejected']
        : ['open', 'in_progress', 'resolved', 'rejected'];

    if (!in_array($status, $allowed, true)) fail('Invalid status');

    if (!set_ticket_status($conn, $ticket_type, $ticket_id, $status)) {
        fail('Failed to update status: ' . $conn->error);
    }

    ok(['status' => $status]);
}

fail('Invalid action');