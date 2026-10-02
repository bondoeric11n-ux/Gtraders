<?php
include_once("../paypal_config.php");
include_once("../config.php");

header('Content-Type: application/json');

$order_id = $_GET['orderID'] ?? null;
if (!$order_id) {
    echo json_encode(["error" => "Missing order ID"]);
    exit;
}

// Capture payment
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, PAYPAL_BASE_URL . "/v2/checkout/orders/$order_id/capture");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Basic " . base64_encode(PAYPAL_CLIENT_ID . ":" . PAYPAL_SECRET)
]);
curl_setopt($ch, CURLOPT_POST, true);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['status']) && $data['status'] === 'COMPLETED') {
    $capture_id = $data['purchase_units'][0]['payments']['captures'][0]['id'];
    $amount = $data['purchase_units'][0]['payments']['captures'][0]['amount']['value'];

    // Update transaction status
    $stmt = $conn->prepare("UPDATE transactions SET status='COMPLETED', paypal_txn_id=? WHERE paypal_txn_id=?");
    $stmt->bind_param("ss", $capture_id, $order_id);
    $stmt->execute();

    // Get user ID for balance update
    $result = $conn->query("SELECT user_id FROM transactions WHERE paypal_txn_id='$capture_id' LIMIT 1");
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];

    // Update user balance
    $stmt = $conn->prepare("UPDATE users SET account_balance = account_balance + ? WHERE id = ?");
    $stmt->bind_param("di", $amount, $user_id);
    $stmt->execute();

    echo json_encode(["success" => true, "message" => "Payment captured successfully."]);
} else {
    $stmt = $conn->prepare("UPDATE transactions SET status='FAILED' WHERE paypal_txn_id=?");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();

    echo json_encode(["error" => "Payment capture failed."]);
}
?>