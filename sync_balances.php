<?php
session_start();
include("db_connect.php");

// 1️⃣ Backfill interest for active investments
$conn->query("
    UPDATE investments i
    JOIN plans p ON i.plan_id = p.id
    SET i.interest = i.amount * (p.interest_rate / 100)
    WHERE i.status = 'active'
");

// 2️⃣ Process all matured investments not yet updated
$mature_q = $conn->query("
    SELECT i.id, i.user_id, i.amount, i.interest
    FROM investments i
    WHERE i.status='active' AND i.end_date <= NOW()
");

while ($inv = $mature_q->fetch_assoc()) {
    $principal  = $inv['amount'];
    $interest   = $inv['interest'];
    $payout     = $principal + $interest;

    // Update balances
    $stmt = $conn->prepare("
        UPDATE users
        SET account_balance = account_balance + ?,
            invested_balance  = invested_balance - ?
        WHERE id = ?
    ");
    $stmt->bind_param("ddi", $payout, $principal, $inv['user_id']);
    $stmt->execute();
    $stmt->close();

    // Mark investment as matured
    $stmt2 = $conn->prepare("UPDATE investments SET status='matured' WHERE id=?");
    $stmt2->bind_param("i", $inv['id']);
    $stmt2->execute();
    $stmt2->close();

    // Record transaction
    $desc = "Matured investment: Principal Ksh $principal + Interest Ksh $interest";
    $stmt3 = $conn->prepare("
        INSERT INTO transactions (user_id, type, amount, description, created_at)
        VALUES (?, 'investment_matured', ?, ?, NOW())
    ");
    $stmt3->bind_param("ids", $inv['user_id'], $payout, $desc);
    $stmt3->execute();
    $stmt3->close();

    // Notification
    $msg = "✅ Your investment of Ksh $principal plus interest Ksh $interest has matured and credited to your account.";
    $stmt4 = $conn->prepare("
        INSERT INTO user_notifications (user_id, message, created_at)
        VALUES (?, ?, NOW())
    ");
    $stmt4->bind_param("is", $inv['user_id'], $msg);
    $stmt4->execute();
    $stmt4->close();
}

// 3️⃣ Optional: Recalculate balances from scratch for safety
$conn->query("UPDATE users SET account_balance = 0, invested_balance = 0");

// Add all matured investments to account_balance
$conn->query("
    UPDATE users u
    JOIN investments i ON u.id = i.user_id
    SET u.account_balance = u.account_balance + i.amount + i.interest
    WHERE i.status = 'matured'
");

// Add all active investments to invested_balance
$conn->query("
    UPDATE users u
    JOIN investments i ON u.id = i.user_id
    SET u.invested_balance = u.invested_balance + i.amount
    WHERE i.status = 'active'
");

echo "✅ All balances synced successfully.";
?>
