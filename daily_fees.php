<?php
session_start();
include("db_connect.php");

// Admin protection
if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit();
}

// Default date = today
$selected_date = $_GET['date'] ?? date('Y-m-d');

$fees_q = $conn->prepare("
    SELECT df.id, df.user_id, df.total_fee, df.withdrawn, u.username
    FROM daily_fees df
    JOIN users u ON u.id = df.user_id
    WHERE df.date = ?
    ORDER BY df.id ASC
");
$fees_q->bind_param("s", $selected_date);
$fees_q->execute();
$fees_result = $fees_q->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<title>Daily Fees</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:#0f1720;color:#fff;font-family:Arial,Helvetica,sans-serif;padding:30px;}
  .btn-green{background:#00ff00;color:#000;border:none;}
  .btn-green:hover{opacity:0.9;}
</style>
</head>
<body>
<h2>Fees Collected for <?=htmlspecialchars($selected_date)?></h2>
<table class="table table-dark table-striped">
<thead>
<tr>
<th>ID</th><th>User</th><th>Fee Amount</th><th>Withdrawn</th><th>Action</th>
</tr>
</thead>
<tbody>
<?php while($fee = $fees_result->fetch_assoc()): ?>
<tr>
<td><?=intval($fee['id'])?></td>
<td><?=htmlspecialchars($fee['username'])?></td>
<td>Ksh <?=number_format((float)$fee['total_fee'],2)?></td>
<td><?= $fee['withdrawn'] ? 'Yes' : 'No' ?></td>
<td>
<?php if(!$fee['withdrawn']): ?>
<a href="withdraw_fee.php?id=<?=intval($fee['id'])?>" class="btn btn-sm btn-green">Withdraw</a>
<?php else: ?>
—
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</body>
</html>
