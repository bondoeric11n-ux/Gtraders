<?php
include 'config.php';

// Get all pending investments older than 24hrs
$sql = "SELECT * FROM investments WHERE status='pending' AND TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 24";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($inv = $result->fetch_assoc()) {
        $user_id = $inv['user_id'];
        $amount  = $inv['amount'];
        $interest = $inv['interest'];
        $total_return = $amount + $interest;

        // Update user balance
        $sql_update = "UPDATE users SET balance = balance + ? WHERE id=?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("di", $total_return, $user_id);
        $stmt->execute();

        // Mark investment as completed
        $sql_done = "UPDATE investments SET status='completed' WHERE id=?";
        $stmt2 = $conn->prepare($sql_done);
        $stmt2->bind_param("i", $inv['id']);
        $stmt2->execute();

        echo "✅ User $user_id investment ID " . $inv['id'] . " matured. Added $total_return to balance.<br>";
    }
} else {
    echo "⏳ No matured investments found.";
}
?>
