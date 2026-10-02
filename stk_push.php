<?php
// stk_push.php
// Initiates STK push (sandbox) and creates a pending row in btc_deposits.

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . '/db_connect.php';      // your DB connection (must set $conn)
require_once __DIR__ . '/access_token.php';    // provides getMpesaAccessToken()

date_default_timezone_set('Africa/Nairobi');

// Sandbox settings
$shortcode = "174379"; // sandbox PayBill
$passkey   = "bfb279f9aa9bdbcf158e97dd71a467cd2e0d72fc54edc09f3f64c8e36b5b41e5";
$stk_endpoint = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";

header('Content-Type: application/json');

// require logged in user
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}
$user_id = (int) $_SESSION['user_id'];

// read input (supports form POST or JSON)
$input = $_POST;
if (empty($input) && ($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
}

$amount = isset($input['amount']) ? floatval($input['amount']) : 0;
$phone  = isset($input['phone']) ? trim($input['phone']) : '254708374149'; // default sandbox test phone
$currency = isset($input['currency']) ? strtoupper($input['currency']) : 'USD';

// basic validation
if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid amount']);
    exit;
}

// If amount provided in USD, convert to KES using a simple api (fallback)
function usdToKes($usd) {
    $api = "https://api.exchangerate.host/latest?base=USD&symbols=KES";
    $resp = @file_get_contents($api);
    if ($resp) {
        $j = json_decode($resp, true);
        if (!empty($j['rates']['KES'])) return floatval($usd * $j['rates']['KES']);
    }
    return floatval($usd * 125); // fallback
}

if ($currency === 'USD') {
    $amount_kes = round(usdToKes($amount), 2);
    $amount_for_mpesa = $amount_kes; // STK amount in KES
} else {
    $amount_for_mpesa = round($amount, 2);
    $amount_kes = $amount_for_mpesa;
}

// get token
$token = getMpesaAccessToken();
if (!$token) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to fetch Mpesa access token']);
    exit;
}

$timestamp = date("YmdHis");
$password = base64_encode($shortcode . $passkey . $timestamp);

$payload = [
    "BusinessShortCode" => $shortcode,
    "Password"          => $password,
    "Timestamp"         => $timestamp,
    "TransactionType"   => "CustomerPayBillOnline",
    "Amount"            => (string)$amount_for_mpesa,
    "PartyA"            => $phone,
    "PartyB"            => $shortcode,
    "PhoneNumber"       => $phone,
    "CallBackURL"       => (isset($input['callback']) ? $input['callback'] : ( (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/callback.php' )),
    "AccountReference"  => "GTraders_Deposit_{$user_id}",
    "TransactionDesc"   => "Deposit"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $stk_endpoint);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer {$token}"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErr) {
    error_log("STK request curl error: {$curlErr}");
    http_response_code(500);
    echo json_encode(['error' => 'cURL error while sending STK request', 'detail' => $curlErr]);
    exit;
}

$respJson = json_decode($response, true);

// If Safaricom accepted the request, it returns CheckoutRequestID & MerchantRequestID
$checkoutId = $respJson['CheckoutRequestID'] ?? null;
$merchantId = $respJson['MerchantRequestID'] ?? null;

// Save pending deposit row
try {
    // use your existing table structure (btc_deposits)
    $stmt = $conn->prepare("INSERT INTO btc_deposits (user_id, invoice_id, amount, amount_ksh, status, btc_address, deposit_method, credited, created_at) VALUES (?, ?, ?, ?, 'pending', ?, 'mpesa', 0, NOW())");
    $inv = $checkoutId ?? ($merchantId ?? null);
    $btc_address_for_row = $phone;
    $stmt->bind_param("isdds", $user_id, $inv, $amount, $amount_kes, $btc_address_for_row);
    $stmt->execute();
    $insertId = $stmt->insert_id;
    $stmt->close();
} catch (Exception $e) {
    error_log("DB insert error (stk push): " . $e->getMessage());
    // continue, respond with STK response to client
    $insertId = null;
}

// Respond with raw Safaricom response + our inserted id
echo json_encode([
    'http_code' => $httpCode,
    'saf_response' => $respJson,
    'deposit_row_id' => $insertId
]);
