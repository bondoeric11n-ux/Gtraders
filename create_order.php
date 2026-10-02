<?php
include_once("../paypal_config.php");
include_once("../config.php");

header('Content-Type: application/json');

$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if ($amount <= 0 || !$user_id) {
    echo json_encode(["error" => "Invalid amount or missing user ID"]);
    exit;
}

// Create PayPal Order
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, PAYPAL_BASE_URL . "/v2/checkout/orders");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Basic " . base64_encode(PAYPAL_CLIENT_ID . ":" . PAYPAL_SECRET)
]);

$data = [
    "intent" => "CAPTURE",
    "purchase_units" => [[
        "amount" => [
            "currency_code" => "USD",
            "value" => number_format($amount, 2, '.', '')
        ],
        "description" => "GTraders Account Top-Up"
    ]],
    "application_context" => [
        "return_url" => "https://yourdomain.com/paypal/success.php",
        "cancel_url" => "https://yourdomain.com/paypal/cancel.php"
    ]
];

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['id'])) {
    $paypal_id = $result['id'];
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description, payment_method, paypal_txn_id, status)
                            VALUES (?, 'deposit', ?, 'PayPal Deposit', 'PayPal', ?, 'PENDING')");
    $stmt->bind_param("ids", $user_id, $amount, $paypal_id);
    $stmt->execute();
}

echo $response;
?>