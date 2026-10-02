<?php
session_start();
include 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['withdraw_id'])) {
    $action = strtolower($_POST['action']);
    $withdraw_id = intval($_POST['withdraw_id']);

    // Fetch withdrawal details
    $stmt = $conn->prepare("SELECT user_id, amount, status FROM withdrawals WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $withdraw_id);
    $stmt->execute();
    $stmt->bind_result($user_id, $amount, $status);
    $stmt->fetch();
    $stmt->close();

    if ($status !== 'pending') {
        $_SESSION['msg'] = "Withdrawal already processed.";
        header("Location: admin_withdrawals.php");
        exit();
    }

    $conn->begin_transaction();
    try {
        if ($action === 'approve') {
            // Deduct total_balance only; available_balance was reduced at request
            $upd = $conn->prepare("
                UPDATE users 
                SET total_balance = total_balance - ?, 
                    pending_withdrawals = pending_withdrawals - ?
                WHERE id = ?
            ");
            $upd->bind_param("ddi", $amount, $amount, $user_id);
            $upd->execute();
            $upd->close();

            // Mark withdrawal as approved
            $upd_w = $conn->prepare("UPDATE withdrawals SET status='approved', processed_at=NOW() WHERE id=?");
            $upd_w->bind_param("i", $withdraw_id);
            $upd_w->execute();
            $upd_w->close();

        } elseif ($action === 'reject') {
            // Return funds to available_balance and reduce pending_withdrawals
            $refund = $conn->prepare("
                UPDATE users 
                SET available_balance = available_balance + ?, 
                    pending_withdrawals = pending_withdrawals - ?
                WHERE id = ?
            ");
            $refund->bind_param("ddi", $amount, $amount, $user_id);
            $refund->execute();
            $refund->close();

            // Mark withdrawal as rejected
            $upd_w = $conn->prepare("UPDATE withdrawals SET status='rejected', processed_at=NOW() WHERE id=?");
            $upd_w->bind_param("i", $withdraw_id);
            $upd_w->execute();
            $upd_w->close();
        }

        $conn->commit();
        $_SESSION['msg'] = "Withdrawal processed successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg'] = "Error: " . $e->getMessage();
    }

    header("Location: admin_withdrawals.php");
    exit();
}

// Fetch all withdrawals with usernames
$withdrawals = $conn->query("
    SELECT w.id, u.username, w.amount, w.status, w.created_at, w.processed_at
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    ORDER BY w.created_at DESC
");
?>
