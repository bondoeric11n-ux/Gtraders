<?php
session_start();
include 'config.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$user_id = intval($_GET['user_id'] ?? 0);

// Fetch user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$message = "";

// Handle form submission
if($_SERVER['REQUEST_METHOD']=='POST'){
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $account_balance = $_POST['account_balance'];
    $invested_balance = $_POST['invested_balance'];

    $update = $conn->prepare("UPDATE users SET name=?, username=?, email=?, phone=?, account_balance=?, invested_balance=? WHERE id=?");
    $update->bind_param("ssssddi", $name, $username, $email, $phone, $account_balance, $invested_balance, $user_id);
    if($update->execute()){
        $message = "User updated successfully!";
        // Refresh user data
        $user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
    } else {
        $message = "Error: ".$update->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <style>
        body{font-family:Arial,sans-serif;background:#000;color:#FFD700;padding:20px;}
        input, button {padding:10px;margin:5px 0;width:100%;}
        button {background:#FFD700;color:#000;border:none;cursor:pointer;font-weight:bold;}
        button:hover{background:#FFA500;}
        .container{max-width:500px;margin:auto;}
        .message{color:#ff5555;font-weight:bold;}
    </style>
</head>
<body>
<div class="container">
    <h2>Edit User: <?= htmlspecialchars($user['username']) ?></h2>
    <?php if($message) echo "<p class='message'>$message</p>"; ?>
    <form method="post">
        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" placeholder="Full Name" required>
        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" placeholder="Username" required>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" placeholder="Email" required>
        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" placeholder="Phone">
        <input type="number" step="0.01" name="account_balance" value="<?= $user['account_balance'] ?>" placeholder="Account Balance" required>
        <input type="number" step="0.01" name="invested_balance" value="<?= $user['invested_balance'] ?>" placeholder="Invested Balance" required>
        <button type="submit">Save Changes</button>
    </form>
    <p><a href="admin_dashboard.php" style="color:#FFD700;">Back to Dashboard</a></p>
</div>
</body>
</html>
