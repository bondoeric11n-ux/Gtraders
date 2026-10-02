<?php
session_start();
include("db_connect.php");

if(!isset($_SESSION['user_id'])){
    echo json_encode(['status'=>'error','msg'=>'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$amount = round((float)($_POST['amount'] ?? 0), 2);

if($amount <= 0){
    echo json_encode(['status'=>'error','msg'=>'Invalid withdrawal amount.']);
    exit;
}

$conn->begin_transaction();
try {
    // Lock user row
    $stmt = $conn->prepare("SELECT account_balance, pending_withdrawals FROM users WHERE id=? FOR UPDATE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($account_balance, $pending_withdrawals);
    if(!$stmt->fetch()) throw new Exception("User not found.");
    $stmt->close();

    $available_balance = $account_balance - $pending_withdrawals;

    if($amount > $available_balance){
        $conn->rollback();
        echo json_encode(['status'=>'error','msg'=>'Insufficient available balance.']);
        exit;
    }

    // Prevent duplicate withdrawal in last 30s
    $dup = $conn->prepare("SELECT COUNT(*) FROM withdrawals WHERE user_id=? AND status='pending' AND amount=? AND created_at > (NOW() - INTERVAL 30 SECOND)");
    $dup->bind_param("id", $user_id, $amount);
    $dup->execute();
    $dup->bind_result($cnt);
    $dup->fetch();
    $dup->close();

    if($cnt > 0){
        $conn->rollback();
        echo json_encode(['status'=>'error','msg'=>'A similar withdrawal is already pending.']);
        exit;
    }

    // Insert withdrawal
    $insert = $conn->prepare("INSERT INTO withdrawals (user_id, amount, status, created_at) VALUES (?, ?, 'pending', NOW())");
    $insert->bind_param("id", $user_id, $amount);
    if(!$insert->execute()) throw new Exception("Failed to submit withdrawal.");
    $insert->close();

    // Update pending withdrawals in users table
    $upd = $conn->prepare("UPDATE users SET pending_withdrawals = pending_withdrawals + ? WHERE id=?");
    $upd->bind_param("di", $amount, $user_id);
    $upd->execute();
    $upd->close();

    $conn->commit();
    $available_balance -= $amount;

    echo json_encode(['status'=>'success','msg'=>'Withdrawal request submitted. Awaiting admin approval.','new_balance'=>$available_balance]);

} catch(Exception $e){
    $conn->rollback();
    echo json_encode(['status'=>'error','msg'=>$e->getMessage()]);
}
?>
