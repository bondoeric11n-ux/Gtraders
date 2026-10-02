<?php
session_start();
include("db_connect.php");
if (!isset($_SESSION['admin_id'])) exit('Unauthorized');

// Fetch totals
$fees_row = $conn->query("SELECT COALESCE(SUM(fee_amount),0) AS total_fees FROM daily_fees")->fetch_assoc();
$admin_fees_total = $fees_row['total_fees'] ?? 0.00;

$withdrawn_row = $conn->query("SELECT COALESCE(SUM(amount),0) AS total_withdrawn FROM admin_wallet_log")->fetch_assoc();
$admin_withdrawn_total = $withdrawn_row['total_withdrawn'] ?? 0.00;

$admin_balance = $admin_fees_total - $admin_withdrawn_total;

// Withdrawals log
$withdraw_log_q = $conn->query("SELECT id, amount, created_at AS withdrawn_at FROM admin_wallet_log ORDER BY created_at DESC");

$withdrawals = [];
while($w = $withdraw_log_q->fetch_assoc()) $withdrawals[] = $w;

// Return JSON
echo json_encode([
    'total_fees' => number_format($admin_fees_total, 2),
    'total_withdrawn' => number_format($admin_withdrawn_total, 2),
    'balance' => number_format($admin_balance, 2),
    'withdrawals' => $withdrawals
]);
