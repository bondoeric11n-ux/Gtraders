<?php
session_start();
include("db_connect.php");

// Protect admin
if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit();
}

// Handle withdraw
if(isset($_POST['withdraw'])){
    $amount = floatval($_POST['amount']);
    if($amount > 0){
        // Update admin_wallet: increase total_withdrawn and decrease available fees
        $conn->query("UPDATE admin_wallet SET total_withdrawn = total_withdrawn + $amount WHERE id=1");
        $conn->query("UPDATE admin_wallet SET total_collected = total_collected - $amount WHERE id=1");
        $message = "Withdrawn Ksh ".number_format($amount,2)." successfully.";
    }
}

// Get admin wallet info
$wallet = $conn->query("SELECT total_collected, total_withdrawn FROM admin_wallet WHERE id=1")->fetch_assoc();

// Fetch fees per user
$fees_q = $conn->query("
    SELECT u.username, SUM(fee_amount) AS user_total
    FROM user_fees uf
    JOIN users u ON u.id = uf.user_id
    GROUP BY u.id
    ORDER BY user_total DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Fees</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0f1720;color:#FFD700;font-family:Arial,sans-serif;padding:30px;}
.container{max-width:900px;margin:auto;}
.card{background:#111217;padding:20px;border-radius:10px;margin-bottom:20px;}
.card h3{color:#00ff00;}
table{color:#fff;}
thead th{color:#D4AF37;}
button{background:#00ff00;color:#000;border:none;padding:10px 15px;border-radius:6px;cursor:pointer;}
button:hover{opacity:0.9;}
.message{color:#00ff00;font-weight:bold;margin-bottom:10px;}
</style>
</head>
<body>
<div class="container">

<h2>Admin Fees</h2>

<?php if(isset($message)) echo "<p class='message'>$message</p>"; ?>

<div class="card">
<h3>Total Fees Collected: Ksh <?=number_format((float)$wallet['total_collected'],2)?></h3>
<h5>Total Withdrawn: Ksh <?=number_format((float)$wallet['total_withdrawn'],2)?></h5>

<form method="post">
<input type="number" step="0.01" name="amount" placeholder="Enter amount to withdraw">
<button type="submit" name="withdraw">Withdraw Fees</button>
</form>
</div>

<div class="card">
<h4>Fees per User</h4>
<table class="table table-borderless">
<thead>
<tr><th>Username</th><th>Fees Contributed (Ksh)</th></tr>
</thead>
<tbody>
<?php while($f = $fees_q->fetch_assoc()): ?>
<tr>
<td><?=htmlspecialchars($f['username'])?></td>
<td>Ksh <?=number_format((float)$f['user_total'],2)?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

</div>
</body>
</html>
