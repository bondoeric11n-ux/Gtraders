<?php
include 'db_connect.php';

// Coinbase webhook shared secret
$shared_secret = '845e72c0-6f00-4718-83d1-9e383700f02c';

// Get webhook body and signature
$body = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'] ?? '';

// Verify HMAC signature
$computed_hmac = hash_hmac('sha256', $body, $shared_secret);
if (!hash_equals($computed_hmac, $signature)) {
    http_response_code(400);
    die("Invalid signature");
}

// Decode webhook payload
$event = json_decode($body, true);
$type = $event['event']['type'] ?? '';
$data = $event['event']['data'] ?? [];
$metadata = $data['metadata'] ?? [];

$user_id = intval($metadata['user_id'] ?? 0);
$invoice_id = $data['id'] ?? '';
$amount = floatval($data['pricing']['local']['amount'] ?? 0);

// Only process confirmed charges
if ($type === 'charge:confirmed' && $user_id > 0 && $invoice_id) {
    // Update deposit status
    $stmt = $conn->prepare("UPDATE btc_deposits SET status='credited' WHERE invoice_id=? AND user_id=?");
    $stmt->bind_param('si', $invoice_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // Credit user balance
    $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id=?");
    $stmt->bind_param('di', $amount, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Always respond 200 to Coinbase
http_response_code(200);
echo "Webhook processed successfully";
?>
