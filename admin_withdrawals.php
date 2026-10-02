<?php
session_start();
include("db_connect.php");

// Restrict access to admins only
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Handle approval/rejection via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['withdraw_id'])) {
    $action = strtolower($_POST['action']);
    $withdraw_id = intval($_POST['withdraw_id']);

    // Fetch withdrawal + user info
    $stmt = $conn->prepare("SELECT w.user_id, w.amount, w.status, u.balance 
                            FROM withdrawals w 
                            JOIN users u ON w.user_id = u.id 
                            WHERE w.id=? LIMIT 1");
    $stmt->bind_param("i", $withdraw_id);
    $stmt->execute();
    $stmt->bind_result($user_id, $amount, $status, $user_balance);
    $stmt->fetch();
    $stmt->close();

    if ($status === 'pending') {
        $conn->begin_transaction();
        try {
            if ($action === 'approve') {
                // Deduct user balance safely
                $upd_user = $conn->prepare("
                    UPDATE users 
                    SET balance = balance - ? 
                    WHERE id=? AND balance >= ?
                ");
                $upd_user->bind_param("d i d", $amount, $user_id, $amount);
                $upd_user->execute();

                if ($upd_user->affected_rows === 0) {
                    throw new Exception("User balance insufficient.");
                }
                $upd_user->close();

                // Mark withdrawal approved
                $upd_w = $conn->prepare("UPDATE withdrawals SET status='approved', processed_at=NOW() WHERE id=?");
                $upd_w->bind_param("i", $withdraw_id);
                $upd_w->execute();
                $upd_w->close();

            } elseif ($action === 'reject') {
                // Mark withdrawal rejected
                $upd_w = $conn->prepare("UPDATE withdrawals SET status='rejected', processed_at=NOW() WHERE id=?");
                $upd_w->bind_param("i", $withdraw_id);
                $upd_w->execute();
                $upd_w->close();
            }

            $conn->commit();
            $_SESSION['msg'] = "Withdrawal ID $withdraw_id processed successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg'] = "Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['msg'] = "Withdrawal already processed.";
    }

    header("Location: admin_withdrawals.php");
    exit();
}

// Fetch all withdrawals
$withdraw_q = $conn->query("
    SELECT w.id, w.user_id, u.username, w.amount, w.status, w.created_at, u.balance
    FROM withdrawals w
    JOIN users u ON u.id = w.user_id
    ORDER BY w.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin — Withdrawals</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0b0b0b;color:#FFD700;font-family:Arial,sans-serif;padding:20px;}
.table-container{background:rgba(0,0,0,0.25); padding:16px; border-radius:10px;}
table {width:100%; border-collapse:collapse; color:#fff;}
thead th{color:#FFD700;}
td, th{padding:8px;border-bottom:1px solid #555;}
.btn-approve{background:#4CAF50;color:#fff;border:none;padding:4px 8px;border-radius:5px;}
.btn-reject{background:#e74c3c;color:#fff;border:none;padding:4px 8px;border-radius:5px;}
.message{color:#0f0;font-weight:bold;margin-bottom:15px;}
</style>
</head>
<body>

<h2>Withdrawal Requests</h2>
<?php if(isset($_SESSION['msg'])) { echo "<p class='message'>" . $_SESSION['msg'] . "</p>"; unset($_SESSION['msg']); } ?>

<div class="table-container">
<?php if(!$withdraw_q || $withdraw_q->num_rows === 0): ?>
<p>No withdrawal requests yet.</p>
<?php else: ?>
<table class="table table-dark table-striped">
<thead>
<tr>
<th>ID</th>
<th>User</th>
<th>Amount</th>
<th>Available Balance</th>
<th>Status</th>
<th>Date</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php while($w = $withdraw_q->fetch_assoc()): ?>
<tr>
<td><?=intval($w['id'])?></td>
<td><?=htmlspecialchars($w['username'])?></td>
<td><?=number_format($w['amount'],2)?></td>
<td><?=number_format($w['balance'],2)?></td>
<td><?=ucfirst($w['status'])?></td>
<td><?=htmlspecialchars(substr($w['created_at'],0,16))?></td>
<td>
<?php if(strtolower($w['status'])==='pending'): ?>
<form method="POST" style="display:inline;">
<input type="hidden" name="withdraw_id" value="<?=intval($w['id'])?>">
<button type="submit" name="action" value="approve" class="btn-approve btn-sm">Approve</button>
<button type="submit" name="action" value="reject" class="btn-reject btn-sm">Reject</button>
</form>
<?php else: ?>-<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
<?php endif; ?>
</div>

</body>
</html>
