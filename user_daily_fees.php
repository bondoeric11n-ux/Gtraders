<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$user_id = intval($_GET['user_id'] ?? 0);
if (!$user_id) {
    die("User not found.");
}

// fetch user info
$user_q = $conn->prepare("SELECT username FROM users WHERE id=?");
$user_q->bind_param("i",$user_id);
$user_q->execute();
$user_res = $user_q->get_result();
if(!$user = $user_res->fetch_assoc()) die("User not found.");

// fetch daily fees
$fees_q = $conn->prepare("
    SELECT id, fee_date, total_fee, withdrawn
    FROM daily_fees
    WHERE user_id=?
    ORDER BY fee_date DESC
");
$fees_q->bind_param("i",$user_id);
$fees_q->execute();
$fees_res = $fees_q->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Daily Fees — <?=htmlspecialchars($user['username'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0f1720;color:#fff;font-family:Arial,sans-serif;padding:20px;}
.table thead th{color:#D4AF37;}
.btn-withdraw{background:#0f0;color:#000;font-weight:bold;border:none;padding:5px 10px;border-radius:5px;}
.btn-withdraw:hover{opacity:0.85;cursor:pointer;}
</style>
</head>
<body>

<h2>Daily Fees for <?=htmlspecialchars($user['username'])?></h2>
<p><a href="user_fees.php" style="color:#FFD700;">← Back to Users Fees</a></p>

<table class="table table-borderless">
<thead>
<tr>
<th>Date</th>
<th>Fee Collected (Ksh)</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php while($f = $fees_res->fetch_assoc()): ?>
<tr>
<td><?=htmlspecialchars($f['fee_date'])?></td>
<td>Ksh <?=number_format((float)$f['total_fee'],2)?></td>
<td><?=($f['withdrawn'] ? 'Withdrawn':'Pending')?></td>
<td>
<?php if(!$f['withdrawn']): ?>
<form method="post" action="withdraw_fee.php" style="display:inline;">
<input type="hidden" name="fee_id" value="<?=intval($f['id'])?>">
<button type="submit" class="btn-withdraw">Withdraw</button>
</form>
<?php else: ?>—
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</body>
</html>
