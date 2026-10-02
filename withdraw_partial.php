<?php
session_start();
include("db_connect.php");
$user_id = $_SESSION['user_id'] ?? 0;

$wdHistoryStmt = $conn->prepare("SELECT id, amount, status, processed_at FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$wdHistoryStmt->bind_param("i",$user_id);
$wdHistoryStmt->execute();
$result = $wdHistoryStmt->get_result();
$withdrawals = $result->fetch_all(MYSQLI_ASSOC);
$wdHistoryStmt->close();

if(count($withdrawals)===0){
    echo "<p>No withdrawals yet.</p>";
} else {
    echo "<table><tr><th>ID</th><th>Amount</th><th>Status</th><th>Processed At</th></tr>";
    foreach($withdrawals as $wd){
        echo "<tr>
        <td>{$wd['id']}</td>
        <td>Ksh ".number_format($wd['amount'],2)."</td>
        <td>".ucfirst($wd['status'])."</td>
        <td>".($wd['processed_at'] ?: '-')."</td>
        </tr>";
    }
    echo "</table>";
}
?>
