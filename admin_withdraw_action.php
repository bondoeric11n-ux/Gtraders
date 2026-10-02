<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Get POST values
$id = intval($_POST['id'] ?? 0);
$action = strtolower($_POST['action'] ?? '');

if (!$id || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Lock the withdrawal row for update
    $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $w = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$w || strtolower($w['status']) !== 'pending') {
        throw new Exception("Withdrawal not found or already processed");
    }

    $user_id = intval($w['user_id']);
    $amount = floatval($w['amount']);

    if ($action === 'approve') {
        // Deduct from total_balance only; available_balance was reduced at request
        $upd = $conn->prepare("
            UPDATE users 
            SET total_balance = total_balance - ?, 
                pending_withdrawals = pending_withdrawals - ? 
            WHERE id = ?
        ");
        $upd->bind_param("ddi", $amount, $amount, $user_id);
        $upd->execute();
        $upd->close();

        // Log transaction
        $desc = "Withdrawal approved by admin: Ksh " . number_format($amount, 2);
        $tx_type = 'debit';
        $tx = $conn->prepare("INSERT INTO transactions(user_id, type, amount, description, date) VALUES (?, ?, ?, ?, NOW())");
        $tx->bind_param("isds", $user_id, $tx_type, $amount, $desc);
        $tx->execute();
        $tx->close();

    } elseif ($action === 'reject') {
        // Return funds to available_balance and reduce pending_withdrawals
        $upd = $conn->prepare("
            UPDATE users 
            SET available_balance = available_balance + ?, 
                pending_withdrawals = pending_withdrawals - ? 
            WHERE id = ?
        ");
        $upd->bind_param("ddi", $amount, $amount, $user_id);
        $upd->execute();
        $upd->close();
    }

    // Update withdrawal status
    $updStatus = $conn->prepare("UPDATE withdrawals SET status = ? WHERE id = ?");
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    $updStatus->bind_param("si", $new_status, $id);
    $updStatus->execute();
    $updStatus->close();

    $conn->commit();

    echo json_encode(['success' => true, 'new_status' => $new_status]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
