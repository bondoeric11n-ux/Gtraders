<?php
session_start();
include('db_connect.php');

$user_id = $_SESSION['user_id'] ?? 0;
if(!$user_id) exit("Access denied");

// Fetch referral earnings
$bonuses_q = $conn->query("
    SELECT b.*, u.username AS referred_user
    FROM referral_bonus b
    JOIN users u ON b.referred_user_id = u.id
    WHERE b.referrer_id = $user_id
    ORDER BY b.created_at DESC
");

// Total earned
$total_bonus = $conn->query("SELECT SUM(bonus_amount) as total FROM referral_bonus WHERE referrer_id=$user_id")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Referral Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#111; color:#fff; font-family:Arial, sans-serif;}
.card{background:#222; padding:20px; border-radius:10px; margin-bottom:20px;}
table{color:#fff;}
thead th{color:#D4AF37;}
</style>
</head>
<body>
<div class="container mt-4">
    <div class="card">
        <h4>Your Referral Earnings</h4>
        <p><strong>Total Earned:</strong> $<?=number_format($total_bonus,2)?></p>
    </div>

    <div class="card">
        <h5>Referral Bonus History</h5>
        <div style="max-height:400px; overflow-y:auto;">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Referred User</th>
                        <th>Investment ID</th>
                        <th>Bonus Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($b = $bonuses_q->fetch_assoc()): ?>
                    <tr>
                        <td><?=$b['id']?></td>
                        <td><?=htmlspecialchars($b['referred_user'])?></td>
                        <td><?=$b['investment_id']?></td>
                        <td><?=number_format($b['bonus_amount'],2)?></td>
                        <td><?=$b['created_at']?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
