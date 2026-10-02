<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch account_balance and total_balance
$stmt = $conn->prepare("SELECT account_balance, total_balance FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($account_balance, $total_balance);
$stmt->fetch();
$stmt->close();

// Fetch pending withdrawals
$wdStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE user_id=? AND status='pending'");
$wdStmt->bind_param("i", $user_id);
$wdStmt->execute();
$wdStmt->bind_result($pending_withdrawals);
$wdStmt->fetch();
$wdStmt->close();

// Correct available balance
$available_balance = max($total_balance - $pending_withdrawals, 0);

// Fetch last 5 withdrawals
$wdHistoryStmt = $conn->prepare("SELECT id, amount, status, created_at FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$wdHistoryStmt->bind_param("i", $user_id);
$wdHistoryStmt->execute();
$wdHistoryResult = $wdHistoryStmt->get_result();
$withdrawals = $wdHistoryResult->fetch_all(MYSQLI_ASSOC);
$wdHistoryStmt->close();

echo json_encode([
    'account_balance' => $account_balance,
    'total_balance' => $total_balance,
    'pending_withdrawals' => $pending_withdrawals,
    'available_balance' => $available_balance,
    'withdrawals' => $withdrawals
]);
exit();
