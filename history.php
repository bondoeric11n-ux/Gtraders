<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch investments
$sql = "SELECT * FROM investments WHERE user_id=? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Investment History - Gtraders</title>
</head>
<body>
    <h1>Investment History</h1>
    <table border="1" cellpadding="8">
        <tr>
            <th>ID</th>
            <th>Amount</th>
            <th>Interest</th>
            <th>Status</th>
            <th>Date</th>
        </tr>
        <?php while($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['amount'] ?></td>
            <td><?= $row['interest'] ?></td>
            <td><?= ucfirst($row['status']) ?></td>
            <td><?= $row['created_at'] ?></td>
        </tr>
        <?php } ?>
    </table>

    <br>
    <a href="dashboard.php">⬅ Back to Dashboard</a>
</body>
<
