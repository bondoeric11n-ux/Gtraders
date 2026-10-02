<?php
// check_balance.php
session_start();
include 'config.php'; // your DB connection

// Replace with the user ID you want to check
$user_id = 1;

// Fetch user balance
$stmt = $conn->prepare("SELECT account_balance FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

// Fetch last 5 withdrawals for this user
$wdStmt = $conn->prepare("SELECT id, amount, status, processed_at FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$wdStmt->bind_param("i", $user_id);
$wdStmt->execute();
$wdResult = $wdStmt->get_result();
$withdrawals = $wdResult->fetch_all(MYSQLI_ASSOC);
$wdStmt->close();

echo "<h2>User ID: $user_id</h2>";
echo "<p><strong>Current Balance:</strong> Ksh " . number_format($balance, 2) . "</p>";
echo "<h3>Last 5 Withdrawals:</h3>";
echo "<table border='1' cellpadding='8'>
<tr><th>ID</th><th>Amount</th><th>Status</th><th>Processed At</th></tr>";
foreach($withdrawals as $wd){
    echo "<tr>
    <td>{$wd['id']}</td>
    <td>Ksh ".number_format($wd['amount'],2)."</td>
    <td>{$wd['status']}</td>
    <td>{$wd['processed_at']}</td>
    </tr>";
}
echo "</table>";
?>
