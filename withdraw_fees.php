<?php
session_start();
include("db_connect.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$fee_id = intval($_POST['fee_id'] ?? 0);

if(!$fee_id){
    die("Invalid request.");
}

// Check if fee exists and not already withdrawn
$fee_q = $conn->prepare("SELECT total_fee, withdrawn FROM daily_fees WHERE id=? AND user_id=? LIMIT 1");
$fee_q->bind_param("ii", $fee_id, $user_id);
$fee_q->execute();
$fee_res = $fee_q->get_result();
if(!$fee = $fee_res->fetch_assoc()){
    die("Fee not found.");
}

if($fee['withdrawn']){
    die("Fee already withdrawn.");
}

// Insert withdrawal request (status = pending), do NOT touch user balance
$insert = $conn->prepare("INSERT INTO withdrawals(user_id, amount, status, created_at) VALUES(?,?, 'pending', NOW())");
$insert->bind_param("id", $user_id, $fee['total_fee']);
$insert->execute();
$insert->close();

// Mark daily fee as requested to prevent duplicate requests
$upd_fee = $conn->prepare("UPDATE daily_fees SET withdrawn=1 WHERE id=?");
$upd_fee->bind_param("i", $fee_id);
$upd_fee->execute();
$upd_fee->close();

header("Location: user_daily_fees.php?user_id=".$user_id."&requested=1");
exit();
