<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$error = "";
$success = "";

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = round((float)($_POST['amount'] ?? 0), 2);

    // Fetch user balance and pending withdrawals
    $stmt = $conn->prepare("SELECT account_balance FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($account_balance);
    $stmt->fetch();
    $stmt->close();

    $wdStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE user_id=? AND status='pending'");
    $wdStmt->bind_param("i", $user_id);
    $wdStmt->execute();
    $wdStmt->bind_result($pending_withdrawals);
    $wdStmt->fetch();
    $wdStmt->close();

    // Calculate available balance
    $invStmt = $conn->prepare("SELECT COALESCE(SUM(amount - amount*0.035),0) FROM investments WHERE user_id=? AND status='active'");
    $invStmt->bind_param("i", $user_id);
    $invStmt->execute();
    $invStmt->bind_result($invested_balance);
    $invStmt->fetch();
    $invStmt->close();

    $available_balance = round($account_balance - $invested_balance, 2);

    if ($amount <= 0 || $amount > $available_balance) {
        $error = "Invalid withdrawal amount. You can withdraw up to Ksh " . number_format($available_balance, 2);
    } else {
        // Prevent duplicates within 30 seconds
        $dup = $conn->prepare("SELECT COUNT(*) FROM withdrawals WHERE user_id=? AND amount=? AND status='pending' AND created_at > (NOW() - INTERVAL 30 SECOND)");
        $dup->bind_param("id", $user_id, $amount);
        $dup->execute();
        $dup->bind_result($cnt);
        $dup->fetch();
        $dup->close();

        if ($cnt > 0) {
            $error = "A similar withdrawal request is already pending. Please wait a few seconds.";
        } else {
            $insert = $conn->prepare("INSERT INTO withdrawals (user_id, amount, status, created_at) VALUES (?, ?, 'pending', NOW())");
            $insert->bind_param("id", $user_id, $amount);
            if ($insert->execute()) {
                $success = "Withdrawal request submitted successfully. Awaiting admin approval.";
                $available_balance -= $amount;
            } else {
                $error = "Failed to submit withdrawal request. Please try again.";
            }
            $insert->close();
        }
    }
}

// Fetch withdrawal history
$wdHistoryStmt = $conn->prepare("SELECT id, amount, status, created_at FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
$wdHistoryStmt->bind_param("i", $user_id);
$wdHistoryStmt->execute();
$wdResult = $wdHistoryStmt->get_result();
$withdrawals = $wdResult->fetch_all(MYSQLI_ASSOC);
$wdHistoryStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Withdrawals</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: Arial,sans-serif; background:#0f1720; color:#FFD700; min-height:100vh; padding:30px; }
.container { max-width:800px; margin:auto; background:rgba(0,0,0,0.85); padding:30px; border-radius:20px; box-shadow:0 0 30px rgba(255,215,0,0.3); }
input, button { padding:10px; margin:8px 0; border-radius:8px; border:none; width:100%; }
button { background:#FFD700; color:#000; font-weight:bold; cursor:pointer; transition:0.3s; }
button:hover { background:#FFA500; }
.error { background:#FFF3CD; color:#856404; padding:10px; margin-bottom:15px; border-radius:8px; box-shadow:0 0 15px #f90; }
.success { background:#D4EDDA; color:#155724; padding:10px; margin-bottom:15px; border-radius:8px; box-shadow:0 0 15px #0a0; }
table { width:100%; border-collapse:collapse; margin-top:20px; background:#1a1a1a; color:#FFD700; border-radius:12px; overflow:hidden; }
th, td { border:1px solid #FFD700; padding:10px; text-align:center; }
th { background:#222; }
.approved { background:#D4EDDA; color:#155724; font-weight:bold; }
.rejected { background:#F8D7DA; color:#721C24; font-weight:bold; }
.pending { background:#FFF3CD; color:#856404; font-weight:bold; }
.back-btn { margin-top:20px; background:#FFD700; color:#000; font-weight:bold; padding:10px 15px; border-radius:8px; display:inline-block; text-decoration:none; text-align:center; transition:0.3s;}
.back-btn:hover { background:#FFA500; }
</style>
</head>
<body>
<div class="container">
<h1 class="text-center mb-4">Withdraw Funds</h1>

<?php if($error) echo "<div class='error'>$error</div>"; ?>
<?php if($success) echo "<div class='success'>$success</div>"; ?>

<form method="POST">
    <label>Amount to Withdraw:</label>
    <input type="number" step="0.01" name="amount" max="<?php echo htmlspecialchars($available_balance, ENT_QUOTES); ?>" required>
    <button type="submit">Request Withdrawal</button>
</form>

<h3 class="mt-4">Recent Withdrawals</h3>
<?php if(empty($withdrawals)): ?>
<p>No withdrawals yet.</p>
<?php else: ?>
<table>
<tr><th>ID</th><th>Amount</th><th>Status</th><th>Date</th></tr>
<?php foreach($withdrawals as $wd): 
    $status_class = strtolower($wd['status']); ?>
<tr class="<?php echo $status_class; ?>">
<td><?php echo $wd['id']; ?></td>
<td>Ksh <?php echo number_format($wd['amount'],2); ?></td>
<td><?php echo ucfirst($wd['status']); ?></td>
<td><?php echo $wd['created_at']; ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<a href="dashboard.php" class="back-btn">Back to Dashboard</a>
</div>
</body>
</html>
