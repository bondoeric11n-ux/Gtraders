<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include_once("db_connect.php"); 

$secret_key = 'sk_test_77d96fa76f6012940e24f7e107fefe152ac1618c'; 

if (!isset($_GET['reference']) || !isset($_GET['user_id'])) {
    header("Location: deposit.php?status=error&msg=Invalid+request");
    exit();
}

$reference = $_GET['reference'];
$user_id = (int)$_GET['user_id'];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer " . $secret_key],
    CURLOPT_TIMEOUT => 30
]);
$response = curl_exec($curl);
curl_close($curl);

$result = json_decode($response, true);

if (isset($result['data']) && $result['data']['status'] === 'success') {
    $amount_paid = $result['data']['amount'] / 100; 
    $paystack_ref = $result['data']['reference'];
    
    // 1. Check existing btc_deposits table using invoice_id
    $check = $conn->query("SELECT id FROM btc_deposits WHERE invoice_id = '$paystack_ref'");
    
    if ($check->num_rows === 0) {
        // 2. Insert into btc_deposits matching your original schema
        $insert = $conn->prepare("INSERT INTO btc_deposits (user_id, invoice_id, amount, amount_ksh, status, btc_address, deposit_method, credited) VALUES (?, ?, ?, ?, 'completed', ?, 'paystack', 1)");
        $insert->bind_param("isdds", $user_id, $paystack_ref, $amount_paid, $amount_paid, $paystack_ref);
        
        if ($insert->execute()) {
            // 3. Update user balance
            $update = $conn->prepare("UPDATE users SET account_balance = account_balance + ? WHERE id = ?");
            $update->bind_param("di", $amount_paid, $user_id);
            
            if ($update->execute() && $update->affected_rows > 0) {
                $msg = urlencode("Deposit of Ksh " . number_format($amount_paid, 2) . " successful!");
                header("Location: deposit.php?status=success&msg=$msg");
                exit();
            } else {
                $error_msg = "Balance update failed for User $user_id. SQL Error: " . $conn->error;
                file_put_contents('paystack_error.log', date('Y-m-d H:i:s') . " - " . $error_msg . "\n", FILE_APPEND);
                header("Location: deposit.php?status=error&msg=Payment+received+but+balance+update+failed.+Check+paystack_error.log");
                exit();
            }
        } else {
            $error_msg = "Insert failed: " . $conn->error;
            file_put_contents('paystack_error.log', date('Y-m-d H:i:s') . " - " . $error_msg . "\n", FILE_APPEND);
            header("Location: deposit.php?status=error&msg=Failed+to+record+deposit");
            exit();
        }
    } else {
        header("Location: deposit.php?status=success&msg=Deposit+already+processed");
        exit();
    }
} else {
    header("Location: deposit.php?status=error&msg=Payment+failed+at+Paystack");
    exit();
}
?>