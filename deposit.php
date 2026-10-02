<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include_once("db_connect.php");

// SAFE PHPMAILER LOADING
if (@file_exists('src/PHPMailer.php')) {
    @require_once 'src/Exception.php';
    @require_once 'src/PHPMailer.php';
    @require_once 'src/SMTP.php';
    function sendEmailNotification($to, $subject, $messageHtml) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
            $mail->Username = 'gibal.ltd@gmail.com'; $mail->Password = 'dkbcereljkmvzfqy';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = 587;
            $mail->setFrom('gibal.ltd@gmail.com', 'GIBAL LTD');
            $mail->addAddress($to); $mail->isHTML(true); $mail->Subject = $subject;
            $mail->Body = "<div style='font-family:Arial;background:#050810;color:#fff;padding:20px;'>$messageHtml</div>";
            $mail->send();
        } catch (Exception $e) { error_log("Email failed: " . $mail->ErrorInfo); }
    }
} else {
    function sendEmailNotification($to, $subject, $messageHtml) { error_log("PHPMailer missing."); }
}

$forex_api = "https://api.exchangerate.host/latest?base=USD&symbols=KES";
$forex_response = @file_get_contents($forex_api);
$usd_to_kes = $forex_response ? (json_decode($forex_response, true)['rates']['KES'] ?? 125) : 125;
$min_usd = 5;

$btcpay_url = "https://gibal-u59690.vm.elestio.app";
$store_id   = "J14zt767UrjCXL8p8HqexyrBnbz5G1sfWGcCEc1pieRy";
$api_key    = "d01224547e0fd5bf4c547aac9d1965ea7f485fda";

define('PAYPAL_CLIENT_ID', 'AYFnuwN6CBxi7CGKF-1AxfdaHoUolVHGBTwX3L7swEwSYotPNSoaTLJh2sGJH-viAyI3HFbLFUCkum1L');
define('PAYPAL_SECRET', 'EOOmmRCm5x_xhE6CrfLAk0ZaLoWXcwapyhr1br6LQOlLq7P1Z4Rhq1ZT0AXQ70QWHH5qXaRA5Zuxi0DD');
define('PAYPAL_BASE_URL', 'https://api-m.paypal.com');
$paystack_public_key = 'pk_test_dea838cfe9f447dd0235d1444ab3ce46138713a4';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = (int)$_SESSION['user_id'];

$stmtUser = $conn->prepare("SELECT id, username, name, email, account_balance FROM users WHERE id = ? LIMIT 1");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$userRes = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();
if (!$userRes) die("User not found.");

$userName = $userRes['name'] ?: $userRes['username'];
$userBalance = floatval($userRes['account_balance'] ?? 0);

$flash_message = '';
$flash_type = 'info';
if (isset($_SESSION['deposit_flash']) && is_array($_SESSION['deposit_flash'])) {
    $flash_message = $_SESSION['deposit_flash']['message'] ?? '';
    $flash_type = $_SESSION['deposit_flash']['type'] ?? 'info';
    unset($_SESSION['deposit_flash']);
} elseif (isset($_GET['status']) && isset($_GET['msg'])) {
    $flash_message = htmlspecialchars(urldecode($_GET['msg']));
    $flash_type = $_GET['status'] === 'success' ? 'success' : 'error';
}

// Handle Dispute Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dispute'])) {
    $tx_ref = trim($_POST['tx_ref']);
    $amount = floatval($_POST['dispute_amount']);
    $message = trim($_POST['dispute_message']);
    
    if (!empty($tx_ref) && $amount > 0 && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO disputes (user_id, transaction_ref, amount, message, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("isds", $user_id, $tx_ref, $amount, $message);
        if ($stmt->execute()) {
            $_SESSION['deposit_flash'] = ['message' => "✅ Dispute submitted successfully! Our team will review it shortly.", 'type' => 'success'];
        } else {
            $_SESSION['deposit_flash'] = ['message' => "❌ Failed to submit dispute. Please try again.", 'type' => 'error'];
        }
        header("Location: deposit.php");
        exit();
    } else {
        $_SESSION['deposit_flash'] = ['message' => "❌ Please fill in all required fields.", 'type' => 'error'];
        header("Location: deposit.php");
        exit();
    }
}

// Handle POST submissions (PayPal & BTCPay)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount']) && !isset($_POST['submit_dispute'])) {
    $amount_usd = floatval(str_replace(',', '', $_POST['amount']));
    $method = $_POST['method'] ?? 'btcpay';

    if ($amount_usd < $min_usd) {
        header("Location: deposit.php?status=error&msg=Minimum+deposit+is+$min_usd+USD"); exit();
    }
    $amount_kes = $amount_usd * $usd_to_kes;

    if ($method === 'btcpay') {
        $invoice_data = ["amount" => number_format($amount_usd, 2, '.', ''), "currency" => "USD", "metadata" => ["user_id" => $user_id], "checkout" => ["speedPolicy" => "HighSpeed", "redirectURL" => "https://gtraders.gt.tc/deposit.php"]];
        $ch = curl_init("$btcpay_url/api/v1/stores/$store_id/invoices");
        curl_setopt_array($ch, [CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: token $api_key"], CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($invoice_data)]);
        $response = curl_exec($ch); $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);

        if ($httpcode == 200 || $httpcode == 201) {
            $invoice = json_decode($response, true);
            $stmt = $conn->prepare("INSERT INTO btc_deposits (user_id, invoice_id, amount, amount_ksh, status, btc_address, deposit_method, credited) VALUES (?, ?, ?, ?, 'pending', ?, 'btcpay', 0)");
            $stmt->bind_param("isdds", $user_id, $invoice['id'], $amount_usd, $amount_kes, $invoice['checkoutLink']);
            $stmt->execute(); $stmt->close();
            sendEmailNotification($userRes['email'], "BTC Deposit Created", "<p>Dear $userName,</p><p>BTC Invoice ready. Amount: \${$amount_usd}.</p><p><a href='{$invoice['checkoutLink']}'>Click to pay</a></p>");
            header("Location: deposit.php?status=success&msg=BTC+Invoice+created!"); exit();
        } else {
            header("Location: deposit.php?status=error&msg=Failed+to+create+BTC+invoice"); exit();
        }
    }

    if ($method === 'paypal') {
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => PAYPAL_BASE_URL."/v1/oauth2/token", CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => PAYPAL_CLIENT_ID.":".PAYPAL_SECRET, CURLOPT_POSTFIELDS => "grant_type=client_credentials", CURLOPT_POST => true]);
        $tokenData = json_decode(curl_exec($ch), true); curl_close($ch);
        $access_token = $tokenData['access_token'] ?? '';

        if ($access_token) {
            $order_data = ["intent" => "CAPTURE", "purchase_units" => [["amount" => ["currency_code" => "USD", "value" => number_format($amount_usd,2,'.','')]]], "application_context" => ["return_url" => "https://gtraders.gt.tc/deposit.php", "cancel_url" => "https://gtraders.gt.tc/deposit.php"]];
            $ch = curl_init();
            curl_setopt_array($ch, [CURLOPT_URL => PAYPAL_BASE_URL."/v2/checkout/orders", CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bearer $access_token"], CURLOPT_POSTFIELDS => json_encode($order_data)]);
            $order = json_decode(curl_exec($ch), true); curl_close($ch);
            
            $paypalLink = $order['links'][1]['href'] ?? '#';
            $stmt = $conn->prepare("INSERT INTO btc_deposits (user_id, invoice_id, amount, amount_ksh, status, btc_address, deposit_method, credited) VALUES (?, ?, ?, ?, 'pending', ?, 'paypal', 0)");
            $stmt->bind_param("isdds", $user_id, $order['id'], $amount_usd, $amount_kes, $paypalLink);
            $stmt->execute(); $stmt->close();
            
            sendEmailNotification($userRes['email'], "PayPal Deposit Created", "<p>Dear $userName,</p><p>PayPal order ready. Amount: \${$amount_usd}.</p><p><a href='$paypalLink'>Click to approve</a></p>");
            header("Location: deposit.php?status=success&msg=PayPal+order+created!"); exit();
        } else {
            header("Location: deposit.php?status=error&msg=Failed+to+connect+to+PayPal"); exit();
        }
    }
}

// UNIFIED HISTORY QUERY: Fetches from BOTH tables to ensure nothing is missed
$deposits = [];
$res1 = @$conn->query("SELECT * FROM btc_deposits WHERE user_id=$user_id ORDER BY id DESC LIMIT 20");
if($res1){ while($row=$res1->fetch_assoc()) $deposits[]=$row; }

$res2 = @$conn->query("SELECT * FROM deposits WHERE user_id=$user_id ORDER BY id DESC LIMIT 20");
if($res2){ while($row=$res2->fetch_assoc()) $deposits[]=$row; }

// Sort combined results by date (newest first)
usort($deposits, function($a, $b) {
    return strtotime($b['created_at'] ?? 'now') <=> strtotime($a['created_at'] ?? 'now');
});
$deposits = array_slice($deposits, 0, 20); // Keep only top 20
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deposit Funds | GIBAL LTD</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://js.paystack.co/v1/inline.js"></script>
<style>
:root { --bg-primary: #050810; --bg-card: rgba(17, 24, 39, 0.7); --gold: #d4af37; --text-primary: #f1f5f9; --text-secondary: #94a3b8; --success: #10b981; --danger: #ef4444; --mobile-color: #00A859; --paypal-color: #003087; --btc-color: #F7931A; --radius-lg: 20px; --radius-md: 14px; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; overflow-x: hidden; position: relative; }
.bg-orb { position: fixed; border-radius: 50%; filter: blur(100px); opacity: 0.12; z-index: 0; animation: float 15s infinite ease-in-out; pointer-events: none; }
.orb-1 { width: 400px; height: 400px; background: var(--mobile-color); top: -100px; left: -100px; }
.orb-2 { width: 350px; height: 350px; background: var(--paypal-color); bottom: -50px; right: -50px; animation-delay: 5s; }
.orb-3 { width: 300px; height: 300px; background: var(--btc-color); top: 40%; left: 60%; animation-delay: 10s; }
@keyframes float { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(30px, -30px) scale(1.1); } }
.container { max-width: 900px; margin: 0 auto; padding: 100px 24px 60px; position: relative; z-index: 10; }
.header { text-align: center; margin-bottom: 40px; }
.header h1 { font-size: 2.2rem; font-weight: 800; margin-bottom: 8px; background: linear-gradient(135deg, #fff 0%, var(--gold) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.header p { color: var(--text-secondary); font-size: 1.05rem; }
.back-link { display: inline-flex; align-items: center; gap: 6px; margin-top: 16px; color: var(--text-secondary); text-decoration: none; font-size: 0.9rem; transition: color 0.3s; }
.back-link:hover { color: var(--gold); }
.balance-card { background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(17, 24, 39, 0.8) 100%); backdrop-filter: blur(16px); border: 1px solid rgba(212, 175, 55, 0.2); border-radius: var(--radius-lg); padding: 28px; text-align: center; margin-bottom: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
.balance-label { font-size: 0.85rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; font-weight: 600; }
.balance-value { font-size: 2.5rem; font-weight: 800; color: var(--gold); font-family: 'JetBrains Mono', monospace; }
.method-selector { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 32px; }
.method-card { background: var(--bg-card); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); border-radius: var(--radius-md); padding: 24px 16px; text-align: center; cursor: pointer; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; overflow: hidden; }
.method-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: transparent; transition: background 0.3s; }
.method-card:hover { transform: translateY(-5px); }
.method-card.active { transform: translateY(-5px) scale(1.02); }
.method-card.mobile.active { border-color: var(--mobile-color); box-shadow: 0 10px 30px rgba(0, 168, 89, 0.2); }
.method-card.mobile.active::before { background: var(--mobile-color); }
.method-card.mobile.active .method-icon { color: var(--mobile-color); }
.method-card.paypal.active { border-color: var(--paypal-color); box-shadow: 0 10px 30px rgba(0, 48, 135, 0.2); }
.method-card.paypal.active::before { background: var(--paypal-color); }
.method-card.paypal.active .method-icon { color: var(--paypal-color); }
.method-card.btc.active { border-color: var(--btc-color); box-shadow: 0 10px 30px rgba(247, 147, 26, 0.2); }
.method-card.btc.active::before { background: var(--btc-color); }
.method-card.btc.active .method-icon { color: var(--btc-color); }
.method-icon { font-size: 2.2rem; margin-bottom: 12px; color: var(--text-secondary); transition: color 0.3s; }
.method-name { font-weight: 700; font-size: 1rem; color: var(--text-primary); }
.method-card input { display: none; }
.form-card { background: var(--bg-card); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.05); border-radius: var(--radius-lg); padding: 36px; margin-bottom: 32px; display: none; animation: slideUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.form-card.active { display: block; }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.form-group { margin-bottom: 24px; }
.form-label { display: block; font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 10px; }
.form-input { width: 100%; padding: 16px; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(148, 163, 184, 0.15); border-radius: var(--radius-md); color: var(--text-primary); font-size: 1.1rem; transition: all 0.3s; font-family: inherit; }
.form-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1); }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 10px; padding: 16px 24px; border-radius: var(--radius-md); font-weight: 700; font-size: 1.05rem; cursor: pointer; transition: all 0.3s; border: none; width: 100%; text-decoration: none; }
.btn-mobile { background: linear-gradient(135deg, #00A859 0%, #008f4c 100%); color: white; }
.btn-mobile:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 168, 89, 0.4); }
.btn-paypal { background: linear-gradient(135deg, #003087 0%, #001c5d 100%); color: white; }
.btn-paypal:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 48, 135, 0.4); }
.btn-btc { background: linear-gradient(135deg, #F7931A 0%, #d47d0f 100%); color: white; }
.btn-btc:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(247, 147, 26, 0.4); }
.info-box { background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); border-left: 4px solid #3b82f6; padding: 18px 22px; border-radius: var(--radius-md); margin-bottom: 28px; color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6; }
.info-box strong { color: #60a5fa; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 1rem; }
.table-container { background: var(--bg-card); border: 1px solid rgba(255,255,255,0.05); border-radius: var(--radius-lg); overflow: hidden; margin-top: 40px; }
.table-scroll { overflow-x: auto; max-height: 400px; }
table { width: 100%; border-collapse: collapse; min-width: 700px; }
th { padding: 16px; text-align: left; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); background: rgba(15, 23, 42, 0.8); border-bottom: 1px solid rgba(255,255,255,0.05); position: sticky; top: 0; }
td { padding: 16px; font-size: 0.95rem; color: var(--text-primary); border-bottom: 1px solid rgba(255,255,255,0.05); }
tbody tr:hover { background: rgba(255,255,255,0.02); }
.badge { display: inline-flex; padding: 5px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
.badge-completed { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.badge-pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.toast-container { position: fixed; top: 90px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 12px; }
.toast { background: rgba(17, 24, 39, 0.95); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); border-left: 4px solid var(--gold); border-radius: var(--radius-md); padding: 16px 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); display: flex; align-items: center; gap: 14px; min-width: 320px; transform: translateX(120%); opacity: 0; transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.toast.show { transform: translateX(0); opacity: 1; }
.toast.success { border-left-color: var(--success); }
.toast.error { border-left-color: var(--danger); }
.toast-close { background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.1rem; margin-left: auto; }
@media (max-width: 768px) { .container { padding: 84px 16px 40px; } .method-selector { grid-template-columns: 1fr; } .toast-container { right: 12px; left: 12px; } .toast { min-width: auto; } }

/* Dispute Modal Styles */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); z-index: 9998; display: none; align-items: center; justify-content: center; }
.modal-overlay.active { display: flex; }
.modal-box { background: var(--bg-card); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-lg); padding: 32px; width: 90%; max-width: 500px; position: relative; animation: slideUp 0.3s ease; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.modal-header h3 { color: var(--danger); margin: 0; display: flex; align-items: center; gap: 10px; }
.modal-close { background: none; border: none; color: var(--text-secondary); font-size: 1.5rem; cursor: pointer; transition: color 0.2s; }
.modal-close:hover { color: var(--text-primary); }
</style>
</head>
<body>
<div class="bg-orb orb-1"></div><div class="bg-orb orb-2"></div><div class="bg-orb orb-3"></div>
<div class="toast-container" id="toastContainer"></div>

<div class="container">
    <div class="header">
        <h1>Fund Your Account</h1>
        <p>Choose a fast, secure, and automated payment method below.</p>
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="balance-card">
        <div class="balance-label">Current Available Balance</div>
        <div class="balance-value">Ksh <?=number_format($userBalance, 2)?></div>
    </div>

    <div class="method-selector">
        <label class="method-card mobile active" onclick="selectMethod('mobile', this)"><input type="radio" name="method" value="mobile" checked><div class="method-icon"><i class="fas fa-mobile-screen-button"></i></div><div class="method-name">Mobile Money</div></label>
        <label class="method-card paypal" onclick="selectMethod('paypal', this)"><input type="radio" name="method" value="paypal"><div class="method-icon"><i class="fab fa-paypal"></i></div><div class="method-name">PayPal</div></label>
        <label class="method-card btc" onclick="selectMethod('btc', this)"><input type="radio" name="method" value="btc"><div class="method-icon"><i class="fab fa-bitcoin"></i></div><div class="method-name">BTC Pay</div></label>
    </div>

    <!-- Mobile Money (Paystack) Form -->
    <div class="form-card active" id="form-mobile">
        <div class="info-box" style="border-left-color: var(--mobile-color); background: rgba(0, 168, 89, 0.08);">
            <strong style="color: var(--mobile-color);"><i class="fas fa-bolt"></i> Instant & Automated:</strong>
            Pay securely using M-Pesa, Airtel Money, Visa, or Mastercard. Your account is credited <strong>instantly</strong> upon successful payment.
        </div>
        <div class="form-group">
            <label class="form-label">Amount to Deposit (USD)</label>
            <input type="number" id="mobileAmount" class="form-input" placeholder="e.g., 10" min="5" step="0.01">
        </div>
        <div style="text-align: right; color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 20px; font-family: 'JetBrains Mono', monospace;">≈ Ksh <span id="mobileKes" style="color: var(--mobile-color); font-weight: 700;">0.00</span></div>
        <button type="button" class="btn btn-mobile" onclick="payWithPaystack()"><i class="fas fa-lock"></i> Pay Securely Now</button>
    </div>

    <!-- PayPal Form -->
    <div class="form-card" id="form-paypal">
        <div class="info-box" style="border-left-color: var(--paypal-color); background: rgba(0, 48, 135, 0.08);">
            <strong style="color: #60a5fa;"><i class="fas fa-shield-alt"></i> PayPal Checkout:</strong>
            Clicking below will generate a secure PayPal order. You will be seamlessly redirected to PayPal to approve the payment.
        </div>
        <form method="POST"><input type="hidden" name="method" value="paypal">
            <div class="form-group"><label class="form-label">Amount to Deposit (USD)</label><input type="number" name="amount" class="form-input" placeholder="e.g., 10" min="5" step="0.01" required></div>
            <button type="submit" class="btn btn-paypal"><i class="fab fa-paypal"></i> Create PayPal Order</button>
        </form>
    </div>

    <!-- BTC Pay Form -->
    <div class="form-card" id="form-btc">
        <div class="info-box" style="border-left-color: var(--btc-color); background: rgba(247, 147, 26, 0.08);">
            <strong style="color: var(--btc-color);"><i class="fab fa-bitcoin"></i> Bitcoin Deposit:</strong>
            A secure invoice will be generated. Pay using any Bitcoin wallet. The system will auto-credit your account upon blockchain confirmation.
        </div>
        <form method="POST"><input type="hidden" name="method" value="btcpay">
            <div class="form-group"><label class="form-label">Amount to Deposit (USD)</label><input type="number" name="amount" class="form-input" placeholder="e.g., 50" min="5" step="0.01" required></div>
            <button type="submit" class="btn btn-btc"><i class="fas fa-file-invoice"></i> Generate BTC Invoice</button>
        </form>
    </div>

    <!-- Dispute Button -->
    <div style="text-align: center; margin-top: 20px;">
        <button type="button" class="btn" style="width: auto; background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.3);" onclick="document.getElementById('disputeModal').classList.add('active')">
            <i class="fas fa-exclamation-triangle"></i> Report a Missing Deposit
        </button>
    </div>

    <!-- Recent Deposits Table -->
    <h3 style="margin-bottom: 20px; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; color: var(--text-primary); margin-top: 40px;"><i class="fas fa-history" style="color: var(--gold);"></i> Recent Deposits</h3>
    <div class="table-container"><div class="table-scroll"><table>
        <thead><tr><th>Method</th><th>Amount (USD)</th><th>Amount (KES)</th><th>Status</th><th>Reference / Action</th><th>Date</th></tr></thead>
        <tbody>
            <?php if (empty($deposits)): ?><tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">No deposits found yet.</td></tr>
            <?php else: foreach($deposits as $d): 
                $cls = strtolower($d['status'] ?? 'pending'); 
                $ref = $d['invoice_id'] ?? $d['btc_address'] ?? ($d['mpesa_receipt'] ?? 'N/A'); 
                $method = strtoupper($d['deposit_method'] ?? 'UNKNOWN');
                $linkHtml = '';
                if ($method === 'PAYPAL' && !empty($ref) && $cls === 'pending') $linkHtml = '<a class="btn" style="width: auto; padding: 8px 16px; font-size: 0.8rem; background: #003087; color: #fff;" href="'.htmlspecialchars($ref).'" target="_blank">Approve</a>';
                elseif ($method === 'BTCPAY' && !empty($ref) && $cls === 'pending') $linkHtml = '<a class="btn" style="width: auto; padding: 8px 16px; font-size: 0.8rem; background: #F7931A; color: #fff;" href="'.htmlspecialchars($ref).'" target="_blank">Pay</a>';
                else $linkHtml = '<span style="font-family: monospace; color: var(--text-secondary);">'.htmlspecialchars(substr($ref, 0, 20)).(strlen($ref) > 20 ? '...' : '').'</span>';
            ?>
                <tr>
                    <td><i class="fas <?= $method === 'PAYPAL' ? 'fa-paypal' : ($method === 'BTCPAY' ? 'fa-bitcoin' : 'fa-mobile-screen-button') ?>" style="margin-right: 8px; color: <?= $method === 'PAYPAL' ? '#003087' : ($method === 'BTCPAY' ? '#F7931A' : '#00A859') ?>;"></i> <?= $method ?></td>
                    <td style="font-family: 'JetBrains Mono', monospace;">$<?=number_format($d['amount'], 2)?></td>
                    <td style="font-family: 'JetBrains Mono', monospace;">Ksh <?=number_format($d['amount_ksh'] ?? 0, 2)?></td>
                    <td><span class="badge badge-<?=$cls?>"><?=ucfirst($cls)?></span></td>
                    <td><?=$linkHtml?></td>
                    <td style="font-size: 0.85rem; color: var(--text-secondary);"><?=date('M d, Y H:i', strtotime($d['created_at'] ?? 'now'))?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table></div></div>
</div>

<!-- Dispute Modal -->
<div class="modal-overlay" id="disputeModal" onclick="if(event.target === this) this.classList.remove('active')">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Report Missing Deposit</h3>
            <button class="modal-close" onclick="document.getElementById('disputeModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Transaction Reference / Code</label>
                <input type="text" name="tx_ref" class="form-input" placeholder="e.g., Paystack Ref or M-Pesa Code" required>
            </div>
            <div class="form-group">
                <label class="form-label">Amount (Ksh)</label>
                <input type="number" name="dispute_amount" class="form-input" placeholder="e.g., 1000" step="0.01" required>
            </div>
            <div class="form-group">
                <label class="form-label">Describe the Issue</label>
                <textarea name="dispute_message" class="form-input" rows="4" placeholder="My money was deducted but not reflected in my balance..." required></textarea>
            </div>
            <button type="submit" name="submit_dispute" class="btn" style="background: var(--danger); color: white;"><i class="fas fa-paper-plane"></i> Submit Dispute</button>
        </form>
    </div>
</div>

<script>
const usdToKesRate = <?=json_encode($usd_to_kes)?>;
function selectMethod(method, element) {
    document.querySelectorAll('.method-card').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.form-card').forEach(c => c.classList.remove('active'));
    document.getElementById('form-' + method).classList.add('active');
    element.classList.add('active');
}
document.getElementById('mobileAmount').addEventListener('input', function() {
    const usd = parseFloat(this.value) || 0;
    document.getElementById('mobileKes').innerText = (usd * usdToKesRate).toFixed(2);
});
function payWithPaystack() {
    const amount = parseFloat(document.getElementById('mobileAmount').value);
    if (!amount || amount < 5) { showToast('Minimum deposit amount is $5 USD', 'error'); return; }
    const handler = PaystackPop.setup({
        key: '<?=$paystack_public_key?>', email: '<?=htmlspecialchars($userRes['email'])?>',
        amount: (amount * usdToKesRate) * 100, currency: 'KES',
        ref: 'GIBAL_' + Math.floor((Math.random() * 1000000000) + 1),
        callback: function(response) { window.location.href = 'paystack_verify.php?reference=' + response.reference + '&user_id=<?=$user_id?>'; },
        onClose: function() { showToast('Payment window closed', 'error'); }
    });
    handler.openIframe();
}
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    const icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle');
    const color = type === 'success' ? 'var(--success)' : (type === 'error' ? 'var(--danger)' : 'var(--gold)');
    toast.innerHTML = `<i class="fas ${icon}" style="font-size: 1.3rem; color: ${color};"></i><div style="flex: 1; font-weight: 600; font-size: 0.95rem; line-height: 1.4;">${message}</div><button class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
    container.appendChild(toast);
    requestAnimationFrame(() => { toast.classList.add('show'); });
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 500); }, 6000);
}
<?php if (!empty($flash_message)): ?>
window.addEventListener('load', () => { showToast(<?=json_encode($flash_message)?>, "<?=$flash_type?>"); });
<?php endif; ?>
</script>
</body>
</html>