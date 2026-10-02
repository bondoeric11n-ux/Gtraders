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
$w_stmt = $conn->prepare("SELECT user_id, amount, status FROM withdrawals WHERE id=? LIMIT 1");
$w_stmt->bind_param("i", $withdraw_id);
$w_stmt->execute();
$w_stmt->bind_result($user_id, $amount, $status);
$w_stmt->fetch();
$w_stmt->close();

if (!$user_id || $status !== 'pending') {
    exit("Withdrawal not found or already processed");
}

$conn->begin_transaction();
try {
    if ($action === 'approve') {
        // Deduct total_balance only; available_balance was reduced at request
        $stmt = $conn->prepare("UPDATE users SET total_balance = total_balance - ?, pending_withdrawals = pending_withdrawals - ? WHERE id = ?");
        $stmt->bind_param("ddi", $amount, $amount, $user_id);
        $stmt->execute();
        $stmt->close();

        // Optionally collect admin fee
        $fee = $amount * 0.03; // 3% fee
        $stmt = $conn->prepare("UPDATE admin_wallet SET fees = fees + ? WHERE id=1");
        $stmt->bind_param("d", $fee);
        $stmt->execute();
        $stmt->close();

        // Mark withdrawal as approved
        $stmt = $conn->prepare("UPDATE withdrawals SET status='approved', withdrawn_at=NOW() WHERE id=?");
        $stmt->bind_param("i", $withdraw_id);
        $stmt->execute();
        $stmt->close();

    } else { // reject
        // Return funds to available_balance and reduce pending_withdrawals
        $stmt = $conn->prepare("UPDATE users SET available_balance = available_balance + ?, pending_withdrawals = pending_withdrawals - ? WHERE id = ?");
        $stmt->bind_param("ddi", $amount, $amount, $user_id);
        $stmt->execute();
        $stmt->close();

        // Mark withdrawal as rejected
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
?>
