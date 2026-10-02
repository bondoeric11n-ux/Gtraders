<?php
session_start();
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

$host="localhost"; $user="root"; $pass=""; $dbname="gtraders";
$conn = new mysqli($host,$user,$pass,$dbname);
if($conn->connect_error) die("DB connection failed: ".$conn->connect_error);

// Fetch all users
$userResult = $conn->query("SELECT id, username, name, email, account_balance, invested_balance, profits, bonus, total_interest FROM users ORDER BY id ASC");
$users = $userResult->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Users</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#0b0b0b;color:#FFD700;overflow-x:hidden;}
.container{max-width:1200px;margin:100px auto;padding:0 20px;}
.floating-menu{position:fixed;top:0;width:100%;background:#111;display:flex;justify-content:center;z-index:1000;box-shadow:0 2px 10px rgba(0,0,0,0.7);}
.floating-menu a{display:block;padding:15px 20px;color:#FFD700;text-decoration:none;transition:0.3s;font-weight:bold;}
.floating-menu a:hover{background:#FFD700;color:#000;border-radius:5px 5px 0 0;transform:translateY(-2px);box-shadow:0 4px 10px rgba(255,215,0,0.4);}
h1{text-align:center;margin-bottom:40px;}
table{width:100%;border-collapse:collapse;margin-bottom:50px;background:#1a1a1a;color:#FFD700;border-radius:10px;overflow:hidden;}
th,td{border:1px solid #FFD700;padding:12px;text-align:center;}
th{background:#222;color:#FFD700;}
tr:hover{background:#333;cursor:pointer;}
button{padding:8px 12px;background:#FFD700;border:none;border-radius:5px;font-weight:bold;cursor:pointer;transition:0.3s;margin:2px;}
button:hover{background:#FFA500;}
</style>
</head>
<body>

<div class="floating-menu">
<a href="admin_dashboard.php">Dashboard</a>
<a href="admin_withdrawals.php">Withdrawals</a>
<a href="admin_users.php">Users</a>
<a href="logout.php">Logout</a>
</div>

<div class="container">
<h1>All Users</h1>

<?php if(count($users) === 0): ?>
<p style="text-align:center;font-size:1.2em;">No users found.</p>
<?php else: ?>
<table>
<tr>
<th>ID</th>
<th>Username</th>
<th>Name</th>
<th>Email</th>
<th>Account Balance</th>
<th>Invested Balance</th>
<th>Profits</th>
<th>Bonus</th>
<th>Total Interest</th>
<th>Actions</th>
</tr>
<?php foreach($users as $user): ?>
<tr>
<td><?php echo $user['id']; ?></td>
<td><?php echo htmlspecialchars($user['username']); ?></td>
<td><?php echo htmlspecialchars($user['name']); ?></td>
<td><?php echo htmlspecialchars($user['email']); ?></td>
<td>Ksh <?php echo number_format($user['account_balance'],2); ?></td>
<td>Ksh <?php echo number_format($user['invested_balance'],2); ?></td>
<td>Ksh <?php echo number_format($user['profits'],2); ?></td>
<td>Ksh <?php echo number_format($user['bonus'],2); ?></td>
<td>Ksh <?php echo number_format($user['total_interest'],2); ?></td>
<td>
<a href="admin_user_profile.php?user_id=<?php echo $user['id']; ?>"><button>View</button></a>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

</div>
</body>
</html>
