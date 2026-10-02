<?php
session_start();
include_once("db_connect.php");
include_once("paypal_config.php");

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$user_id = $_SESSION['user_id'];
$order_id = $data['order_id'];
$amount = floatval($data['amount']);

$stmt = $conn->prepare("INSERT INTO paypal_deposits (user_id, paypal_order_id, amount, status) VALUES (?, ?, ?, 'approved')");
$stmt->bind_param("isd", $user_id, $order_id, $amount);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Credit user balance
    $conn->query("UPDATE users SET account_balance = account_balance + $amount WHERE id = $user_id");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
$stmt->close();