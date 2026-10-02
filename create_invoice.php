<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
include_once("db_connect.php");

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$amount = floatval($_POST['amount'] ?? 0);

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid amount"]);
    exit();
}

// Coinbase Commerce API key
$api_key = "8e6c7b11-bee2-4c9e-9306-fce2a6ac6bb3";

// Coinbase endpoint
$url = "https://api.commerce.coinbase.com/charges";

// Metadata for callback
$metadata = [
    "user_id" => $user_id
];

// Prepare data for Coinbase API
$data = [
    "name" => "GTraders Deposit",
    "description" => "Deposit to your GTraders account",
    "pricing_type" => "fixed_price",
    "local_price" => [
        "amount" => number_format($amount, 2, '.', ''),
        "currency" => "BTC" // switched from USD to BTC to ensure support
    ],
    "metadata" => $metadata,
    "redirect_url" => "https://gtraders.gt.tc/deposit_success.php",
"cancel_url" => "https://gtraders.gt.tc/deposit_failed.php"
];

// Send to Coinbase API
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "X-CC-Api-Key: $api_key",
    "X-CC-Version: 2018-03-22"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(["error" => "cURL error: " . curl_error($ch)]);
    exit();
}
curl_close($ch);

// Save response for debugging
file_put_contents('coinbase_debug.json', $response);

$result = json_decode($response, true);

if (!isset($result['data']['id']) || !isset($result['data']['hosted_url'])) {
    echo json_encode([
        "error" => "Coinbase response missing invoice ID or hosted_url",
        "response" => $result
    ]);
    exit();
}

$invoice_id = $result['data']['id'];
$hosted_url = $result['data']['hosted_url'];

// Save to database
$stmt = $conn->prepare("INSERT INTO btc_deposits (user_id, invoice_id, amount, status) VALUES (?, ?, ?, 'pending')");
$stmt->bind_param('isd', $user_id, $invoice_id, $amount);
$stmt->execute();
$stmt->close();

echo json_encode(["success" => true, "url" => $hosted_url]);
?>
