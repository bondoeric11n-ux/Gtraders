<?php
session_start();
include_once("../config.php");

if (!isset($_GET['token'])) {
    die("Missing PayPal order token.");
}

$order_id = $_GET['token'];
$user_email = $_SESSION['email'] ?? null;

if (!$user_email) {
    die("Session expired or user not logged in.");
}

// Update transaction as cancelled
$stmt = $conn->prepare("UPDATE transactions SET status='CANCELLED' WHERE transaction_id=?");
$stmt->bind_param("s", $order_id);
$stmt->execute();

// Optional: log for admin
$desc = "User cancelled PayPal deposit";
$type = "Deposit";
$stmt = $conn->prepare("INSERT INTO user_activity (user_email, type, description, amount) VALUES (?, ?, ?, 0)");
$stmt->bind_param("sss", $user_email, $type, $desc);
$stmt->execute();

?>

<html>
<head>
    <meta http-equiv="refresh" content="5;url=../deposit.php">
    <style>
        body { background:#0a0a0a; color:#fff; font-family:Arial; text-align:center; padding-top:100px;}
        .box { background:#111; border-radius:12px; padding:30px; display:inline-block; box-shadow:0 0 20px rgba(255,0,0,0.4);}
        h2 { color:#ff4444; }
        p { color:#ccc; }
    </style>
</head>
<body>
    <div class="box">
        <h2>⚠️ Deposit Cancelled</h2>
        <p>You cancelled the PayPal payment.</p>
        <p>Redirecting back to the deposit page in 5 seconds...</p>
    </div>
</body>
</html>