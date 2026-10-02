<?php
session_start();
include 'config.php';

// Restrict access to admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_dashboard.php");
    exit();
}

$id = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($id <= 0 || !in_array($action, ['approve','reject'])) {
    $_SESSION['msg'] = "Invalid withdrawal request.";
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch withdrawal
$stmt = $conn->prepare("SELECT user_id, amount, status FROM withdrawals WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($user_id, $amount, $status);
$stmt->fetch();
$stmt->close();

if ($status !== 'pending') {
    $_SESSION['msg'] = "Withdrawal already processed.";
    header("Location: admin_dashboard.php");
    exit();
}

$conn->begin_transaction();
try {
    if ($action === 'approve') {
        // Fetch user's account balance
        $uStmt = $conn->prepare("SELECT account_balance FROM users WHERE id=? LIMIT 1");
        $uStmt->bind_param("i", $user_id);
        $uStmt->execute();
        $uStmt->bind_result($user_wallet);
        $uStmt->fetch();
        $uStmt->close();

        if ((float)$user_wallet < (float)$amount) {
            // Not enough funds → reject
            $r = $conn->prepare("UPDATE withdrawals SET status='rejected', processed_at=NOW() WHERE id=?");
            $r->bind_param("i", $id);
            $r->execute();
            $r->close();

            $conn->commit();
            $_SESSION['msg'] = "Withdrawal ID $id rejected: insufficient user balance.";
            header("Location: admin_dashboard.php");
            exit();
        }

        // Deduct balance
        $d = $conn->prepare("UPDATE users SET account_balance = account_balance - ? WHERE id=?");
        $d->bind_param("di", $amount, $user_id);
        $d->execute();
        $d->close();

        // Mark withdrawal as approved
        $a = $conn->prepare("UPDATE withdrawals SET status='approved', processed_at=NOW() WHERE id=?");
        $a->bind_param("i", $id);
        $a->execute();
        $a->close();

        $_SESSION['msg'] = "Withdrawal ID $id approved successfully.";

    } else {
        // Reject withdrawal
        $r = $conn->prepare("UPDATE withdrawals SET status='rejected', processed_at=NOW() WHERE id=?");
        $r->bind_param("i", $id);
        $r->execute();
        $r->close();

        $_SESSION['msg'] = "Withdrawal ID $id rejected.";
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['msg'] = "Error processing withdrawal: " . $e->getMessage();
}

header("Location: admin_dashboard.php");
exit();
?>
