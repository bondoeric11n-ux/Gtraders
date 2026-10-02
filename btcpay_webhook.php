<?php
// btcpay_webhook.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once("db_connect.php");

// === CONFIG ===
$btcpay_secret = "2vUYZVYfFj6oqes57qri6LfUM8Z5"; //

// === READ AND VERIFY SIGNATURE ===
$raw_payload = file_get_contents("php://input");
$btcpay_sig = $_SERVER['HTTP_BTCPAY_SIG'] ?? '';
$computed_sig = 'sha256=' . hash_hmac('sha256', $raw_payload, $btcpay_secret);

if (!hash_equals($computed_sig, $btcpay_sig)) {
    http_response_code(400);
    file_put_contents("btcpay_log.txt", date("Y-m-d H:i:s") . " - Invalid signature\n", FILE_APPEND);
    exit("Invalid signature");
}

// === DECODE PAYLOAD ===
$data = json_decode($raw_payload, true);
if (!$data || !isset($data['type'])) {
    http_response_code(400);
    exit("Invalid payload");
}

// === LOG THE PAYLOAD ===
file_put_contents("btcpay_log.txt", date("Y-m-d H:i:s") . " - Event: {$data['type']} | Invoice: {$data['invoiceId']}\n", FILE_APPEND);

// === HANDLE RELEVANT EVENTS ===
$event_type = $data['type'] ?? '';
if (!in_array($event_type, ['InvoiceSettled', 'InvoicePaidInFull', 'InvoiceReceivedPayment'])) {
    http_response_code(200);
    exit("Ignored event");
}

// === GET INVOICE ID ===
$invoice_id = $data['invoiceId'] ?? '';
if (!$invoice_id) {
    http_response_code(400);
    exit("Missing invoiceId");
}

// === FETCH DEPOSIT RECORD ===
$stmt = $conn->prepare("SELECT * FROM btc_deposits WHERE invoice_id=? LIMIT 1");
$stmt->bind_param("s", $invoice_id);
$stmt->execute();
$deposit = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$deposit) {
    http_response_code(404);
    exit("Invoice not found in database");
}

// Already credited? Skip
if ($deposit['status'] === 'credited' || $deposit['credited'] == 1) {
    http_response_code(200);
    exit("Already processed");
}

$user_id = intval($deposit['user_id']);
$amount_usd = floatval($deposit['amount']);

// === FETCH LIVE USD→KES RATE ===
$forex_api = "https://api.exchangerate.host/latest?base=USD&symbols=KES";
$forex_response = @file_get_contents($forex_api);
if ($forex_response) {
    $fx_data = json_decode($forex_response, true);
    $usd_to_kes = $fx_data['rates']['KES'] ?? 124;
} else {
    $usd_to_kes = 124; // fallback rate
}

$amount_kes = round($amount_usd * $usd_to_kes, 2);

// === UPDATE DEPOSIT RECORD ===
$stmt = $conn->prepare("UPDATE btc_deposits SET status='credited', credited=1, converted_amount=?, processed_at=NOW() WHERE id=?");
$stmt->bind_param("di", $amount_kes, $deposit['id']);
$stmt->execute();
$stmt->close();

// === CREDIT USER ACCOUNT BALANCE ===
$stmt = $conn->prepare("UPDATE users SET account_balance = account_balance + ? WHERE id=?");
$stmt->bind_param("di", $amount_kes, $user_id);
$stmt->execute();
$stmt->close();

// === LOG SUCCESS ===
file_put_contents("btcpay_log.txt", date("Y-m-d H:i:s") . " - Deposit credited successfully: Invoice $invoice_id, User $user_id, Amount KES $amount_kes\n", FILE_APPEND);

http_response_code(200);
echo "✅ Deposit credited successfully for invoice $invoice_id ($amount_kes KES)";
?>