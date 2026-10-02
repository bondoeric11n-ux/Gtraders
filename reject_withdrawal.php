<?php
session_start();
include 'config.php';

// Restrict access to admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_id'])) {
    $withdraw_id = intval($_POST['withdraw_id']);

    // Fetch withdrawal info
    $stmt = $conn->prepare("SELECT user_id, amount, status FROM withdrawals WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $withdraw_id);
    $stmt->execute();
    $stmt->bind_result($user_id, $amount, $status);
    $stmt->fetch();
    $stmt->close();

    if ($status === 'pending') {
        $conn->begin_transaction();
        try {
            // Reduce pending withdrawals for the user
            $upd_user = $conn->prepare("UPDATE users SET pending_withdrawals = pending_withdrawals - ? WHERE id=?");
            $upd_user->bind_param("di", $amount, $user_id);
            $upd_user->execute();
            $upd_user->close();

            // Mark withdrawal rejected
            $upd_w = $conn->prepare("UPDATE withdrawals SET status='rejected', processed_at=NOW() WHERE id=?");
            $upd_w->bind_param("i", $withdraw_id);
            $upd_w->execute();
            $upd_w->close();

            $conn->commit();
            $_SESSION['msg'] = "Withdrawal ID $withdraw_id has been rejected.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg'] = "Error rejecting withdrawal: " . $e->getMessage();
        }
    } else {
        $_SESSION['msg'] = "Withdrawal already processed.";
    }

    header("Location: admin_withdrawals.php");
    exit();
}

// If accessed directly without POST
header("Location: admin_withdrawals.php");
exit();
