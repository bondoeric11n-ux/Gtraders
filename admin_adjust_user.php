<?php
session_start();
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

$host="localhost"; $user="root"; $pass=""; $dbname="gtraders";
$conn = new mysqli($host,$user,$pass,$dbname);
if($conn->connect_error) die("DB connection failed: ".$conn->connect_error);

$message = '';

if(isset($_POST['adjust'])) {
    $user_id = intval($_POST['user_id']);
    $amount = floatval($_POST['amount']);
    $action = $_POST['action']; // 'credit' or 'debit'
    
    // Fetch current balances
    $stmt = $conn->prepare("SELECT total_balance, available_balance, username FROM users WHERE id=?");
    $stmt->bind_param("i",$user_id);
    $stmt->execute();
    $stmt->bind_result($total_balance, $available_balance, $username);
    if(!$stmt->fetch()) {
        $message = "User not found!";
        $stmt->close();
    } else {
        $stmt->close();

        if($action=='credit'){
            // Credit increases both available and total balances
            $available_balance += $amount;
            $total_balance += $amount;
            $description = "Admin credited Ksh ".number_format($amount,2)." to user account.";
        } elseif($action=='debit') {
            // Debit reduces only available balance
            if($amount > $available_balance){
                $message = "Cannot debit more than available balance!";
            } else {
                $available_balance -= $amount;
                $description = "Admin debited Ksh ".number_format($amount,2)." from user account.";
            }
        } else {
            $message = "Invalid action!";
        }

        if(empty($message)){
            // Update balances in DB
            $upd = $conn->prepare("UPDATE users SET total_balance=?, available_balance=? WHERE id=?");
            $upd->bind_param("ddi",$total_balance,$available_balance,$user_id);
            $upd->execute();
            $upd->close();

            // Log transaction
            $tx = $conn->prepare("INSERT INTO transactions (user_id,type,amount,description,date) VALUES (?,?,?,NOW())");
            $tx_type = ($action=='credit') ? 'admin_credit' : 'admin_debit';
            $tx->bind_param("isds",$user_id,$tx_type,$amount,$description);
            $tx->execute();
            $tx->close();

            header("Location: ".$_SERVER['PHP_SELF']."?success=1");
            exit;
        }
    }
}

// Fetch all users for the dropdown
$users = $conn->query("SELECT id, username, available_balance FROM users ORDER BY username ASC");

$success = isset($_GET['success']) ? "User balance updated successfully." : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Adjust User Balance</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#111;color:#FFD700;overflow-x:hidden;}
.container{max-width:900px;margin:80px auto;padding:20px;background:#1a1a1a;border-radius:15px;box-shadow:0 4px 20px rgba(0,0,0,0.6);}
h2{text-align:center;margin-bottom:30px;}
form{display:flex;flex-direction:column;}
label{margin:10px 0 5px;}
input, select{padding:10px;border-radius:5px;border:none;margin-bottom:15px;}
button{padding:10px 20px;background:#FFD700;color:#000;border:none;border-radius:5px;font-weight:bold;cursor:pointer;transition:0.3s;}
button:hover{background:#FFA500;}
.message{margin:20px auto;text-align:center;font-weight:bold;color:#0f0;}
.balance-display{margin:10px 0;padding:10px;background:#222;border-radius:5px;text-align:center;}
</style>
<script>
function showBalance() {
    const select = document.getElementById('user_id');
    const balanceDisplay = document.getElementById('current_balance');
    const selectedOption = select.options[select.selectedIndex];
    const balance = selectedOption.dataset.balance || 0;
    balanceDisplay.textContent = "Available Balance: Ksh " + parseFloat(balance).toFixed(2);
}
</script>
</head>
<body>
<div class="container">
<h2>Admin Adjust User Balance</h2>
<?php if($message) echo "<div class='message' style='color:#f55;'>{$message}</div>"; ?>
<?php if($success) echo "<div class='message'>{$success}</div>"; ?>

<form method="POST">
    <label>User:</label>
    <select name="user_id" id="user_id" onchange="showBalance()" required>
        <option value="">Select User</option>
        <?php while($u = $users->fetch_assoc()): ?>
            <option value="<?php echo $u['id']; ?>" data-balance="<?php echo $u['available_balance']; ?>">
                <?php echo htmlspecialchars($u['username']); ?>
            </option>
        <?php endwhile; ?>
    </select>

    <div class="balance-display" id="current_balance">Available Balance: 0.00</div>

    <label>Amount (Ksh):</label>
    <input type="number" step="0.01" name="amount" required>

    <label>Action:</label>
    <select name="action" required>
        <option value="credit">Credit (Add)</option>
        <option value="debit">Debit (Subtract)</option>
    </select>

    <button type="submit" name="adjust">Submit Adjustment</button>
</form>
</div>
</body>
</html>
