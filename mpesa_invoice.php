<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once "db_connect.php";

// Ensure user logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$deposit_id = (int)($_GET['id'] ?? 0);

if ($deposit_id <= 0) {
    die("Invalid invoice ID.");
}

// Fetch deposit
$stmt = $conn->prepare("
    SELECT id, invoice_id, amount, amount_ksh, status, created_at 
    FROM btc_deposits 
    WHERE id=? AND user_id=? AND deposit_method='mpesa' 
    LIMIT 1
");
$stmt->bind_param("ii", $deposit_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$invoice = $res->fetch_assoc();
$stmt->close();

if (!$invoice) {
    die("Invoice not found or access denied.");
}

function e($v){
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>M-Pesa Invoice</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#000;color:#FFD700;font-family:Arial;}
.container{max-width:700px;margin-top:40px;background:#111;padding:30px;border-radius:12px;border:1px solid #333;}
h2{color:#FFD700;}
.label{color:#bbb;}
</style>
</head>
<body>

<div class="container">
    <h2>M-Pesa Deposit Invoice</h2>
    <hr>
    
    <p><span class="label">Transaction Code:</span><br>
    <strong><?= e($invoice['invoice_id']); ?></strong></p>

    <p><span class="label">Amount (USD):</span><br>
    <strong><?= number_format($invoice['amount'], 2); ?></strong></p>

    <p><span class="label">Amount (KES):</span><br>
    <strong><?= number_format($invoice['amount_ksh'], 2); ?></strong></p>

    <p><span class="label">Status:</span><br>
    <strong><?= strtoupper(e($invoice['status'])); ?></strong></p>

    <p><span class="label">Submitted On:</span><br>
    <strong><?= e($invoice['created_at']); ?></strong></p>

    <hr>
    <a href="deposit.php" class="btn btn-warning">Back to Deposits</a>
</div>

</body>
</html>
