<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$withdraw_id = intval($_POST['withdraw_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($withdraw_id <= 0 || !in_array($action, ['approve','reject'])) {
    exit("Invalid request");
}

// Fetch withdrawal
$w = $conn->query("SELECT * FROM withdrawals WHERE id=$withdraw_id")->fetch_assoc();
if (!$w) exit("Withdrawal not found");

$user_id = $w['user_id'];
$amount = $w['amount'];

$conn->begin_transaction();
try {
    if ($action === 'approve') {
        // Deduct total balance (available was already reduced on withdrawal request)
        $stmt = $conn->prepare("UPDATE users SET total_balance = total_balance - ?, pending_withdrawals = pending_withdrawals - ? WHERE id = ?");
        $stmt->bind_param("ddi", $amount, $amount, $user_id);
        $stmt->execute();
        $stmt->close();

        // Collect fee if needed (example 3%)
        $fee = $amount * 0.03;
        $stmt = $conn->prepare("UPDATE admin_wallet SET fees = fees + ? WHERE id=1");
        $stmt->bind_param("d", $fee);
        $stmt->execute();
        $stmt->close();

        // Mark withdrawal approved
        $stmt = $conn->prepare("UPDATE withdrawals SET status='approved', withdrawn_at=NOW() WHERE id=?");
        $stmt->bind_param("i", $withdraw_id);
        $stmt->execute();
        $stmt->close();

    } else { // reject
        // Return funds to available balance
        $stmt = $conn->prepare("UPDATE users SET available_balance = available_balance + ?, pending_withdrawals = pending_withdrawals - ? WHERE id=?");
        $stmt->bind_param("ddi", $amount, $amount, $user_id);
        $stmt->execute();
        $stmt->close();

        // Mark withdrawal rejected
        $stmt = $conn->prepare("UPDATE withdrawals SET status='rejected', withdrawn_at=NOW() WHERE id=?");
        $stmt->bind_param("i", $withdraw_id);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    echo "Success";
} catch(Exception $e) {
    $conn->rollback();
    exit("Failed: ".$e->getMessage());
}
