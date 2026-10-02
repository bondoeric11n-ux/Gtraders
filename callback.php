<?php
// callback.php
// Safaricom STK Push callback endpoint - sandbox
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db_connect.php';
date_default_timezone_set('Africa/Nairobi');

$raw = file_get_contents('php://input');
if ($raw === false) $raw = '';

@file_put_contents(__DIR__ . '/mpesa_callback.log', date('c') . " RAW:\n" . $raw . "\n\n", FILE_APPEND);

// parse JSON
$data = json_decode($raw, true);
if (!$data) {
    // respond OK to avoid retries
    header('Content-Type: application/json');
    echo json_encode(["ResultCode"=>0,"ResultDesc"=>"No JSON received"]);
    exit;
}

// Navigate to callback structure
$stkCallback = $data['Body']['stkCallback'] ?? null;
if (!$stkCallback) {
    header('Content-Type: application/json');
    echo json_encode(["ResultCode"=>0,"ResultDesc"=>"Unsupported payload"]);
    exit;
}

$merchantRequestID = $stkCallback['MerchantRequestID'] ?? null;
$checkoutRequestID = $stkCallback['CheckoutRequestID'] ?? null;
$resultCode = isset($stkCallback['ResultCode']) ? intval($stkCallback['ResultCode']) : 1;
$resultDesc = $stkCallback['ResultDesc'] ?? 'Unknown';

// prepare metadata extraction
$callbackAmount = null;
$mpesaReceipt = null;
$phone = null;

if (!empty($stkCallback['CallbackMetadata']['Item']) && is_array($stkCallback['CallbackMetadata']['Item'])) {
    foreach ($stkCallback['CallbackMetadata']['Item'] as $item) {
        $name = $item['Name'] ?? '';
        if ($name === 'Amount') $callbackAmount = floatval($item['Value']);
        if ($name === 'MpesaReceiptNumber') $mpesaReceipt = $item['Value'];
        if ($name === 'PhoneNumber') $phone = $item['Value'];
    }
}

// Log parsed info
$logLine = date('c') . " callback parsed: CheckoutRequestID={$checkoutRequestID} MerchantRequestID={$merchantRequestID} ResultCode={$resultCode} Amount={$callbackAmount} Receipt={$mpesaReceipt} Phone={$phone} Desc={$resultDesc}\n";
@file_put_contents(__DIR__ . '/mpesa_callback.log', $logLine, FILE_APPEND);

// Try to find the deposit by CheckoutRequestID (invoice_id) OR MerchantRequestID
$found = false;
try {
    if ($checkoutRequestID) {
        $stmt = $conn->prepare("SELECT id, user_id, amount, amount_ksh, status FROM btc_deposits WHERE invoice_id = ? LIMIT 1");
        $stmt->bind_param("s", $checkoutRequestID);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if ($row) { $found = $row; }
    }
    if (!$found && $merchantRequestID) {
        $stmt = $conn->prepare("SELECT id, user_id, amount, amount_ksh, status FROM btc_deposits WHERE invoice_id = ? LIMIT 1");
        $stmt->bind_param("s", $merchantRequestID);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if ($row) { $found = $row; }
    }
} catch (Exception $e) {
    error_log("DB lookup error in callback: " . $e->getMessage());
    $found = false;
}

if (!$found) {
    // Not found - still respond OK to Safaricom. Log for manual handling.
    @file_put_contents(__DIR__ . '/mpesa_callback.log', date('c') . " WARNING: deposit not found for CheckoutRequestID {$checkoutRequestID}\n", FILE_APPEND);
    header('Content-Type: application/json');
    echo json_encode(["ResultCode"=>0,"ResultDesc"=>"Received"]);
    exit;
}

// Process result
$depositId = intval($found['id']);
$userId = intval($found['user_id']);

if ($resultCode === 0) {
    // success: update deposit status, store receipt, and credit user
    // prefer callbackAmount if available; else fallback to stored amount_ksh
    $creditedAmount = $callbackAmount ?: floatval($found['amount_ksh']);

    // Begin transaction
    $conn->begin_transaction();
    try {
        // Update deposit row
        $stmt = $conn->prepare("UPDATE btc_deposits SET status = 'credited', credited = 1, btc_address = ?, amount_ksh = ?, updated_at = NOW() WHERE id = ?");
        $mpesaReceiptForRow = $mpesaReceipt ?? $checkoutRequestID;
        $stmt->bind_param("sdi", $mpesaReceiptForRow, $creditedAmount, $depositId);
        $stmt->execute();
        $stmt->close();

        // Update user's account_balance (we assume account_balance is in KES)
        $stmt = $conn->prepare("UPDATE users SET account_balance = account_balance + ? WHERE id = ?");
        $stmt->bind_param("di", $creditedAmount, $userId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        @file_put_contents(__DIR__ . '/mpesa_callback.log', date('c') . " Deposit {$depositId} credited KES {$creditedAmount} to user {$userId}\n", FILE_APPEND);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error processing callback for deposit {$depositId}: " . $e->getMessage());
        @file_put_contents(__DIR__ . '/mpesa_callback.log', date('c') . " ERROR processing deposit {$depositId}: " . $e->getMessage() . "\n", FILE_APPEND);
    }
} else {
    // failed or cancelled - update status to 'rejected' and store reason
    try {
        $stmt = $conn->prepare("UPDATE btc_deposits SET status = 'rejected', btc_address = ?, updated_at = NOW() WHERE id = ?");
        $reason = $resultDesc;
        $stmt->bind_param("si", $reason, $depositId);
        $stmt->execute();
        $stmt->close();

        @file_put_contents(__DIR__ . '/mpesa_callback.log', date('c') . " Deposit {$depositId} marked rejected. Reason: {$resultDesc}\n", FILE_APPEND);
    } catch (Exception $e) {
        error_log("Error updating deposit status to rejected: " . $e->getMessage());
    }
}

// respond OK to Safaricom
header('Content-Type: application/json');
echo json_encode(["ResultCode" => 0, "ResultDesc" => "Received"]);
exit;
