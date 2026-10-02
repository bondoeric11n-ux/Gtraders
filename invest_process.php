<?php
session_start();
include('dbconnect.php');

$user_id = $_SESSION['user_id'] ?? 0;
$plan_id = intval($_POST['plan_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);

if($user_id <= 0 || $plan_id <= 0 || $amount <= 0){
    die("Invalid request");
}

// Fetch user balance
$user_q = $conn->prepare("SELECT account_balance FROM users WHERE id=?");
$user_q->bind_param("i",$user_id);
$user_q->execute();
$user_res = $user_q->get_result()->fetch_assoc();
$user_balance = floatval($user_res['account_balance'] ?? 0);
$user_q->close();

if($user_balance < $amount){
    die("Insufficient balance to invest.");
}

// Fetch plan details
$plan_q = $conn->prepare("SELECT interest_rate, duration_days FROM plans WHERE id=?");
$plan_q->bind_param("i",$plan_id);
$plan_q->execute();
$plan_res = $plan_q->get_result()->fetch_assoc();
$interest_rate = floatval($plan_res['interest_rate'] ?? 0);
$duration_days = intval($plan_res['duration_days'] ?? 0);
$plan_q->close();

// Deduct 3.5% gas fee
$gas_percent = 3.5;
$net_amount = $amount * (1 - $gas_percent / 100);

// Calculate interest
$interest = $net_amount * ($interest_rate / 100);

// Insert investment
$insert_inv = $conn->prepare("
    INSERT INTO investments 
    (user_id, plan_id, amount, net_amount, interest, status, start_date, end_date, created_at)
    VALUES (?, ?, ?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), NOW())
");
$insert_inv->bind_param("iidddi",$user_id,$plan_id,$amount,$net_amount,$interest,$duration_days);
$insert_inv->execute();
$insert_inv->close();

// Update user balances
$conn->query("UPDATE users SET account_balance = account_balance - $amount, invested_balance = invested_balance + $net_amount WHERE id=$user_id");

// Optional: record transaction
$conn->query("INSERT INTO transactions (user_id,type,amount,created_at) VALUES ($user_id,'Invest', $net_amount, NOW())");

header("Location: dashboard.php?msg=Investment successful");
exit;
?>
