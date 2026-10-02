<?php
session_start();
include("db_connect.php");

if(!isset($_SESSION['admin_id'])) {
    die("Unauthorized");
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $action = $_POST['action'] ?? '';

    if($user_id <= 0 || $amount <= 0 || !in_array($action, ['credit','debit'])) {
        die("Invalid input");
    }

    // Prepare description
    $desc = $action === 'credit' 
        ? "Account credited by admin: Ksh $amount" 
        : "Account debited by admin: Ksh $amount";

    // Update user's balance safely
    if($action === 'credit') {
        $stmt = $conn->prepare("UPDATE users SET account_balance = account_balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $user_id);
    } else {
        // Make sure we don't allow negative balance
        $stmt = $conn->prepare("UPDATE users SET account_balance = GREATEST(account_balance - ?, 0) WHERE id = ?");
        $stmt->bind_param("di", $amount, $user_id);
    }

    if($stmt->execute()) {
        $stmt->close();

        // Insert transaction record
        $stmt2 = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt2->bind_param("isds", $user_id, $action, $amount, $desc);
        $stmt2->execute();
        $stmt2->close();

        // Redirect back to edit page with a success message
        header("Location: edit_user.php?id=$user_id&msg=Balance updated successfully");
        exit;
    } else {
        die("Error updating balance: " . $stmt->error);
    }
}
?>
