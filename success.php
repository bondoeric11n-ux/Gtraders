<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include_once("db_connect.php");
// PHPMailer setup
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'src/Exception.php';
require_once 'src/PHPMailer.php';
require_once 'src/SMTP.php';
// Config
$mailFromName = 'GIBAL LTD';
// ===============================
// Email Notification Function
// ===============================
function sendEmailNotification($to, $subject, $messageHtml) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'gibal.ltd@gmail.com'; 
        $mail->Password   = 'dkbcereljkmvzfqy';  
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('gibal.ltd@gmail.com', 'GIBAL LIMITED');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $messageHtml;
        $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
    }
}
// Get live forex rate
$forex_api = "https://api.exchangerate.host/latest?base=USD&symbols=KES";
$forex_response = @file_get_contents($forex_api);
if ($forex_response) {
    $fx_data = json_decode($forex_response, true);
    $usd_to_kes = $fx_data['rates']['KES'] ?? 124;
} else {
    $usd_to_kes = 124; // fallback
}
$min_usd = 5;
// BTCPay Configuration
$btcpay_url = "https://gibal-u59690.vm.elestio.app";
$store_id   = "J14zt767UrjCXL8p8HqexyrBnbz5G1sfWGcCEc1pieRy";
$api_key    = "d01224547e0fd5bf4c547aac9d1965ea7f485fda"; // secure later
// PayPal Configuration
define('PAYPAL_CLIENT_ID', 'ARTr0U0zqBEBDmBB8p6ncDRs5JYMbEZQo9c2BIdFoJnxg88EtPz3MupY9gSJMWH2z7jYhKypZ6CLrdOC');
define('PAYPAL_SECRET', 'EG9ajCfJ0eazr9807eVJMZ8hn9PGwQqwxg2hNhm0QNcimfkPFyewr29Z1b3QB4JlyDF9_-sYXzxvmaEJ');
define('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com'); // change to live for production

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';
// Fetch user info
$stmtUser = $conn->prepare("SELECT id, username, name, email, account_balance FROM users WHERE id = ? LIMIT 1");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$userRes = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();
if (!$userRes) die("User not found.");
$userName = $userRes['name'] ?: $userRes['username'];
$userBalance = floatval($userRes['account_balance'] ?? 0);
// Flash messages
if (isset($_SESSION['deposit_flash']) && is_array($_SESSION['deposit_flash'])) {
    $flash = $_SESSION['deposit_flash'];
    $success = $flash['message'] ?? 'Invoice created successfully.';
    unset($_SESSION['deposit_flash']);
}
// Handle Deposit Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    $amount_raw = str_replace(',', '', $_POST['amount']);
    $amount_usd = floatval($amount_raw);
    $method = $_POST['method'] ?? 'btcpay'; // detect payment method
    if ($amount_usd < $min_usd) {
        $error = "Minimum deposit is $min_usd USD.";
    } else {
        $amount_kes = $amount_usd * $usd_to_kes;
        // ---------------- BTC PAY ----------------
        if($method === 'btcpay'){
            $invoice_data = [
                "amount" => number_format($amount_usd, 2, '.', ''),
                "currency" => "USD",
                "metadata" => [
                    "user_id" => $user_id,
                    "email"   => $userRes['email'],
                    "purpose" => "GTraders Deposit"
                ],
                "checkout" => [
                    "speedPolicy" => "HighSpeed",
                    "redirectURL" => "https://gtraders.gt.tc/dashboard.php",
                    "defaultLanguage" => "en"
                ]
            ];
            $ch = curl_init("$btcpay_url/api/v1/stores/$store_id/invoices");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: token $api_key"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoice_data));
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpcode == 200 || $httpcode == 201) {
                $invoice = json_decode($response, true);
                $invoice_id = $invoice['id'];
                $btc_address = $invoice['checkoutLink'] ?? 'N/A';
                $stmt = $conn->prepare("INSERT INTO btc_deposits (user_id, invoice_id, amount, amount_ksh, status, btc_address, deposit_method, credited) VALUES (?, ?, ?, ?, 'pending', ?, 'btcpay', 0)");
                $stmt->bind_param("isdds", $user_id, $invoice_id, $amount_usd, $amount_kes, $btc_address);
                $stmt->execute();
                $stmt->close();
                $subject = "BTC Deposit Created — Invoice #$invoice_id";
                $messageHtml = "<h3>BTC Deposit Request</h3><p>Dear <strong>{$userName}</strong>,</p><p>Your BTC deposit request has been created successfully.</p><p><strong>Amount:</strong> \${$amount_usd} (KES ".number_format($amount_kes,2).")<br><strong>Invoice ID:</strong> {$invoice_id}</p><p>Click the link below to open your invoice:</p><p><a href='{$btc_address}' target='_blank' style='color:#FFD700;font-weight:bold;'>Open BTCPay Invoice</a></p><p>Once payment is confirmed, your account will be automatically credited.</p><br><p>Thank you for using GTraders!</p>";
                sendEmailNotification($userRes['email'], $subject, $messageHtml);
                $_SESSION['deposit_flash'] = [
                    'message' => "✅ Invoice created successfully!<br><a href='".htmlspecialchars($btc_address, ENT_QUOTES)."' target='_blank' style='color:#FFD700;text-decoration:underline;font-weight:bold;'>Open BTCPay Invoice</a><br><small>Copy the address or scan the QR code in your BTCPay window to complete payment.</small>",
                    'invoice_id' => $invoice_id,
                    'btc_address' => $btc_address,
                    'amount_usd' => $amount_usd,
                    'amount_kes' => $amount_kes
                ];
                header("Location: deposit.php");
                exit();
            } else {
                $error = "Failed to create BTCPay invoice. Server returned HTTP $httpcode.";
            }
        }
        // ---------------- PAYPAL ----------------
        if($method === 'paypal'){
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
            if(!$access_token){ $error="Failed to get PayPal access token"; }
            if(!$error){
                // Step 2: Create order
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, PAYPAL_BASE_URL."/v2/checkout/orders");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "Authorization: Bearer $access_token"
                ]);
                curl_setopt($ch, CURLOPT_POST, true);
                $payload = json_encode([
                    "intent"=>"CAPTURE",
                    "purchase_units"=>[["amount"=>["currency_code"=>"USD","value"=>number_format($amount_usd,2,'.','')]]],
                    "application_context"=>[
                        "return_url"=>"https://gtraders.gt.tc/paypal_success.php",
                        "cancel_url"=>"https://gtraders.gt.tc/deposit.php",
                    ]
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $resp = curl_exec($ch);
                curl_close($ch);
                $order = json_decode($resp,true);
                $order_id = $order['id'] ?? '';
                $approve_link = '';
                if(isset($order['links'])){
                    foreach($order['links'] as $l){
                        if($l['rel']=='approve'){ $approve_link=$l['href']; break; }
                    }
                }
                if($order_id && $approve_link){
                    $stmt = $conn->prepare("INSERT INTO paypal_deposits (user_id, order_id, amount, amount_ksh, status, credited) VALUES (?, ?, ?, ?, 'pending', 0)");
                    $stmt->bind_param("isdd",$user_id,$order_id,$amount_usd,$amount_kes);
                    $stmt->execute();
                    $stmt->close();
                    $subject = "PayPal Deposit Created — Order #$order_id";
                    $messageHtml = "<p>Dear <strong>{$userName}</strong>,</p><p>Your PayPal deposit request has been created successfully.</p><p><strong>Amount:</strong> \${$amount_usd} (KES ".number_format($amount_kes,2).")<br><strong>Order ID:</strong> {$order_id}</p><p><a href='$approve_link' target='_blank'>Approve Payment</a></p>";
                    sendEmailNotification($userRes['email'],$subject,$messageHtml);
                    $_SESSION['deposit_flash'] = [
                        'message' => "✅ PayPal order created! <a href='$approve_link' target='_blank'>Approve Payment</a>",
                    ];
                    header("Location: deposit.php");
                    exit();
                } else { $error="Failed to create PayPal order"; }
            }
        }
    }
}
// Fetch deposits for display
$stmt = $conn->prepare("SELECT id, invoice_id, amount, amount_ksh, status, btc_address, deposit_method, created_at FROM btc_deposits WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$deposits = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
// Handle AJAX refresh
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $userBalance = floatval($conn->query("SELECT account_balance FROM users WHERE id=$user_id")->fetch_assoc()['account_balance'] ?? 0);
    $stmt = $conn->prepare("SELECT id, invoice_id, amount, amount_ksh, status, btc_address, deposit_method, created_at FROM btc_deposits WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $latestDeposits = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode([
        'balance' => number_format($userBalance, 2),
        'deposits' => $latestDeposits
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Deposit Funds — <?=htmlspecialchars($mailFromName)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* keep all your previous styles exactly as-is */
body, html {margin:0;padding:0;font-family:Arial,sans-serif;background:black;color:#FFD700;overflow-x:hidden;}
#stars {position:fixed;width:100%;height:100%;top:0;left:0;z-index:-1;}
.container {max-width:700px;margin:40px auto;padding:28px;background:rgba(0,0,0,0.85);border-radius:12px;position:relative;z-index:1;}
h1 {color:#FFD700;margin-bottom:18px;text-align:center;}
input, button {border-radius:8px;border:none;padding:10px;width:100%;margin-bottom:10px;}
button {font-weight:700;cursor:pointer;display:flex;justify-content:center;align-items:center;gap:8px;}
button.btc {background:#FFD700;color:#000;}
button.btc:hover {background:#FFA500;}
button.paypal {background:#003087;color:#fff;}
button.paypal:hover {background:#0056b3;}
button.mpesa {background:#00A859;color:#fff;cursor:not-allowed;}
button.airtel {background:#E60000;color:#fff;cursor:not-allowed;}
.table-container {overflow-x:auto;margin-top:18px;}
.table {width:100%;border-collapse:collapse;min-width:700px;}
th, td {padding:10px;text-align:center;border:1px solid #FFD700;white-space:nowrap;}
th {background:#222;}
.approved {background:#D4EDDA;color:#155724;font-weight:bold;}
.rejected {background:#F8D7DA;color:#721C24;font-weight:bold;}
.pending {background:#FFF3CD;color:#856404;font-weight:bold;}
.back-btn {display:inline-block;margin-top:20px;padding:12px 25px;background:#FFD700;color:#000;border-radius:8px;text-decoration:none;font-weight:bold;}
.back-btn:hover {background:#FFA500;}
.alert {border-radius:8px;padding:10px;margin-bottom:10px;}
.spinner {display:none;margin:0 auto 10px;border:4px solid #f3f3f3;border-top:4px solid #FFD700;border-radius:50%;width:30px;height:30px;animation:spin 1s linear infinite;}
@keyframes spin {100%{transform:rotate(360deg);}}
.copy-btn {background:#FFD700;border:none;padding:5px 10px;border-radius:5px;color:black;font-size:12px;margin-left:6px;}
.copy-btn:hover {background:#FFA500;}
</style>
</head>
<body>
<div id="stars"></div>
<div class="container">
    <div style="text-align:center;margin-bottom:15px;">
        <img src="https://btcpayserver.org/img/icons/btcpay-logo.svg" alt="BTCPay Server" width="120" style="margin:0 10px;">
        <img src="https://trustwallet.com/assets/images/media-kit/logo.png" alt="Trust Wallet" width="120" style="margin:0 10px;">
    </div>
    <h1>Deposit Funds</h1>
    <p><strong>Your current balance:</strong> KES <span id="userBalance"><?=number_format($userBalance,2)?></span></p>
    <?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><?=$success?></div><?php endif; ?>
    <form id="depositForm" method="POST" data-method="btcpay">
        <input type="number" name="amount" min="5" step="0.01" placeholder="Amount (USD)" required>
        <input type="hidden" name="method" value="btcpay">
        <div class="spinner" id="spinner"></div>
        <button type="submit" class="btc">Generate BTC Invoice</button>
        <button type="submit" class="paypal" onclick="this.form.method.value='paypal';">Deposit via PayPal</button>
    </form>
    <p><strong>How to deposit:</strong></p>
    <ul style="margin-left:20px;">
        <li>Download and install <strong>Trust Wallet</strong> or any BTC wallet.</li>
        <li>Deposit BTC into your wallet (e.g., from Binance, Coinbase, etc.).</li>
        <li>Click “Generate BTC Invoice” — your personal BTCPay invoice will appear.</li>
        <li>Copy the address or scan the QR code to send BTC.</li>
        <li>Once confirmed, your funds will reflect in your GIBAL account.</li>
    </ul>
    <p>Other methods (coming soon):</p>
    <button class="mpesa">M-Pesa Paybill</button>
    <button class="airtel">Airtel Money</button>
    <h3>Recent Deposits</h3>
    <div class="table-container">
        <table class="table" id="depositsTable">
            <tr><th>ID</th><th>Amount (USD)</th><th>Amount (KES)</th><th>Status</th><th>Method</th><th>Invoice/Order</th><th>Date</th></tr>
            <?php foreach($deposits as $d): ?>
                <?php
                    $statusClass = strtolower($d['status']);
                    $link = htmlspecialchars($d['btc_address'] ?: $d['invoice_id'], ENT_QUOTES);
                ?>
                <tr class="<?=$statusClass?>">
                    <td><?=$d['id']?></td>
                    <td><?=$d['amount']?></td>
                    <td><?=number_format($d['amount_ksh'],2)?></td>
                    <td><?=$d['status']?></td>
                    <td><?=$d['deposit_method']?></td>
                    <td><a href="<?=$link?>" target="_blank">View</a></td>
                    <td><?=$d['created_at']?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
</div>
<script>
function refreshDeposits(){
    fetch("deposit.php?ajax=1")
    .then(res=>res.json())
    .then(data=>{
        document.getElementById("userBalance").innerText = data.balance;
        let tbl = document.getElementById("depositsTable");
        tbl.innerHTML = '<tr><th>ID</th><th>Amount (USD)</th><th>Amount (KES)</th><th>Status</th><th>Method</th><th>Invoice/Order</th><th>Date</th></tr>';
        data.deposits.forEach(d=>{
            let tr = document.createElement("tr");
            tr.className=d.status.toLowerCase();
            tr.innerHTML=`<td>${d.id}</td><td>${d.amount}</td><td>${d.amount_ksh.toFixed(2)}</td><td>${d.status}</td><td>${d.deposit_method}</td><td><a href='${d.btc_address || d.invoice_id}' target='_blank'>View</a></td><td>${d.created_at}</td>`;
            tbl.appendChild(tr);
        });
    }).catch(console.error);
}
setInterval(refreshDeposits,10000);
</script>
</body>
</html>