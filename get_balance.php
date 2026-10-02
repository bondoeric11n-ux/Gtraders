<?php
// get_balance.php
session_start();
include("db_connect.php");

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch account_balance and total_balance
$stmt = $conn->prepare("SELECT account_balance, total_balance FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($account_balance, $total_balance);
$stmt->fetch();
$stmt->close();

// Pending withdrawals
$wdStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE user_id=? AND status='pending'");
$wdStmt->bind_param("i", $user_id);
$wdStmt->execute();
$wdStmt->bind_result($pending_withdrawals);
$wdStmt->fetch();
$wdStmt->close();

// Correct available balance
$available_balance = max($total_balance - $pending_withdrawals, 0);

echo json_encode([
    'account_balance' => $account_balance,
    'total_balance' => $total_balance,
    'pending_withdrawals' => $pending_withdrawals,
    'available_balance' => $available_balance
]);
?>
