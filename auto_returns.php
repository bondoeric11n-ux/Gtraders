<?php
include 'config.php';

// Fetch all active investments
$sql = "SELECT * FROM transactions WHERE type='investment' AND status='active'";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $investment_id = $row['id'];
    $user_id = $row['user_id'];
    $amount = $row['amount'];
    $plan = $row['plan'];
    $created_at = strtotime($row['created_at']);
    $now = time();

    // Plan-based profit percentage
    if ($plan == "12hr") {
        $time_limit = 12 * 3600; // 12 hours
        $profit_rate = 0.15;     // 15%
    } elseif ($plan == "24hr") {
        $time_limit = 24 * 3600; // 24 hours
        $profit_rate = 0.35;     // 35%
    } else {
        continue; // Skip invalid plans
    }

    // Check if enough time has passed
    if ($now - $created_at >= $time_limit) {
        $profit = $amount * $profit_rate;
        $total_return = $amount + $profit;

        // Deduct 5% facilitation fee
        $final_credit = $total_return - ($total_return * 0.05);

        // Add return to user balance
        $update_balance = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id=?");
        $update_balance->bind_param("di", $final_credit, $user_id);
        $update_balance->execute();

        // Mark investment as completed
        $update_tx = $conn->prepare("UPDATE transactions SET status='completed' WHERE id=?");
        $update_tx->bind_param("i", $investment_id);
        $update_tx->execute();

        // Log profit as transaction
        $sql = "INSERT INTO transactions (user_id, type, amount, plan, created_at, status) 
                VALUES (?, 'profit', ?, ?, NOW(), 'completed')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ids", $user_id, $profit, $plan);
        $stmt->execute();

        // Log facilitation fee as transaction
        $fee = $total_return * 0.05;
        $sql_fee = "INSERT INTO transactions (user_id, type, amount, plan, created_at, status) 
                    VALUES (?, 'fee', ?, ?, NOW(), 'completed')";
        $stmt_fee = $conn->prepare($sql_fee);
        $stmt_fee->bind_param("ids", $user_id, $fee, $plan);
        $stmt_fee->execute();
    }
}
?>
