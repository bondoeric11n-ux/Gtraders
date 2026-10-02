<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include_once("db_connect.php");

// PayPal Live Credentials
define('PAYPAL_CLIENT_ID', 'YOUR_LIVE_CLIENT_ID_HERE');
define('PAYPAL_SECRET', 'YOUR_LIVE_SECRET_HERE');
define('PAYPAL_BASE_URL', 'https://api-m.paypal.com'); // Live

if (!isset($_GET['token'])) {
    die("Invalid request. Missing PayPal token.");
}

$order_id = $_GET['token'];

// Step 1: Get access token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, PAYPAL_BASE_URL."/v1/oauth2/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID.":".PAYPAL_SECRET);
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
curl_setopt($ch, CURLOPT_POST, true);
$resp = curl_exec($ch);
curl_close($ch);
$tokenData = json_decode($resp,true);
$access_token = $tokenData['access_token'] ?? '';
if(!$access_token) die("Failed to get PayPal access token.");

// Step 2: Capture the order
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, PAYPAL_BASE_URL."/v2/checkout/orders/$order_id/capture");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $access_token"
]);
curl_setopt($ch, CURLOPT_POST, true);
$resp = curl_exec($ch);
curl_close($ch);

$captureData = json_decode($resp,true);
$status = $captureData['status'] ?? '';
$purchase_unit = $captureData['purchase_units'][0]['payments']['captures'][0] ?? null;

if ($status === 'COMPLETED' && $purchase_unit) {
    // Fetch deposit
    $stmt = $conn->prepare("SELECT user_id, amount, amount_ksh, credited FROM btc_deposits WHERE invoice_id=? AND deposit_method='paypal' LIMIT 1");
    $stmt->bind_param("s",$order_id);
    $stmt->execute();
    $deposit = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($deposit && !$deposit['credited']) {
        $user_id = $deposit['user_id'];
        $amount_ksh = floatval($deposit['amount_ksh']);

        // Update user balance
        $conn->query("UPDATE users SET account_balance = account_balance + $amount_ksh WHERE id = $user_id");

        // Mark deposit as approved
        $stmt = $conn->prepare("UPDATE btc_deposits SET status='approved', credited=1, processed_at=NOW() WHERE invoice_id=? AND deposit_method='paypal'");
        $stmt->bind_param("s",$order_id);
        $stmt->execute();
        $stmt->close();
    }

    $_SESSION['deposit_flash'] = [
        'message' => "✅ PayPal payment completed successfully! Your account has been credited."
    ];
    header("Location: deposit.php");
    exit();
} else {
    $_SESSION['deposit_flash'] = [
        'message' => "❌ PayPal payment failed or not completed. Please try again."
    ];
    header("Location: deposit.php");
    exit();
}