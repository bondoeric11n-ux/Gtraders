<?php
session_start();
include_once "db_connect.php";
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$investment_fee_percent = 3.5;
$msg = '';
$success_msg = '';
$success = false;

// ------------------ TIMEZONE ------------------
$tz = new DateTimeZone('Africa/Nairobi');
$now = new DateTime('now', $tz);

// ------------------ PROCESS MATURED INVESTMENTS ------------------
$mature_stmt = $conn->prepare("
    SELECT i.id, i.amount, i.net_amount, i.expected_interest, i.plan_id, p.name AS plan_name, p.duration_days, p.duration_hours, i.start_date, u.email, u.name
    FROM investments i
    LEFT JOIN plans p ON i.plan_id=p.id
    LEFT JOIN users u ON i.user_id=u.id
    WHERE i.user_id=? AND i.status='active'
");
$mature_stmt->bind_param("i", $user_id);
$mature_stmt->execute();
$res_mature = $mature_stmt->get_result();
$matured = [];
while($inv = $res_mature->fetch_assoc()){
    $duration_hours = $inv['duration_hours'] ? floatval($inv['duration_hours']) : floatval($inv['duration_days'])*24;
    $start_dt = new DateTime($inv['start_date'],$tz);
    $end_dt = clone $start_dt;
    $whole_hours = floor($duration_hours);
    $fraction_minutes = ($duration_hours - $whole_hours)*60;
    $end_dt->modify("+{$whole_hours} hours +{$fraction_minutes} minutes");
    if($now >= $end_dt){
        $matured[] = $inv;
    }
}
$mature_stmt->close();

// ------------------ HANDLE MATURED INVESTMENTS ------------------
if(!empty($matured)){
    $conn->begin_transaction();
    try{
        foreach($matured as $inv){
            $net = floatval($inv['net_amount']);
            $profit = floatval($inv['expected_interest']);
            $total = $net + $profit;

            // Update user balances
            $conn->query("UPDATE users SET account_balance=account_balance+$total, invested_balance=invested_balance-$net, total_expected_interest=total_expected_interest-$profit WHERE id=$user_id");

            // Update investment status
            $conn->query("UPDATE investments SET status='matured' WHERE id={$inv['id']}");

            // Insert transaction
            $desc = "Investment in plan '{$inv['plan_name']}' matured: principal Ksh ".number_format($net,2)." + interest Ksh ".number_format($profit,2);
            $stmt_tx = $conn->prepare("INSERT INTO transactions (user_id,type,amount,description,created_at) VALUES (?,?,?,NOW())");
            $type='deposit';
            $stmt_tx->bind_param("isd",$user_id,$type,$total,$desc);
            $stmt_tx->execute();
            $stmt_tx->close();

            // ------------------ SEND MATURITY EMAIL ------------------
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'gibal.ltd@gmail.com';
                $mail->Password   = 'dkbcereljkmvzfqy';
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->setFrom('gibal.ltd@gmail.com', 'GIBAL LTD');
                $mail->addAddress($inv['email'], $inv['name']);
                $mail->isHTML(true);
                $mail->Subject = "💰 Investment Matured!";
                $credited_at = $now->format('Y-m-d H:i:s');
                $plan_name = $inv['plan_name'];
                $mail->Body = "
                    <div style='max-width:600px; margin:auto; padding:20px; font-family:Arial,sans-serif; border:1px solid #e0e0e0; border-radius:10px; background:#f4f4f4; color:#333;'>
                        <h2 style='text-align:center; color:#0288d1;'>GIBAL LTD — Investment Matured</h2>
                        <p>Hello {$inv['name']},</p>
                        <p>Your investment in plan <strong>{$plan_name}</strong> has matured successfully.</p>
                        <ul>
                            <li>Principal: Ksh ".number_format($net,2)."</li>
                            <li>Profit: Ksh ".number_format($profit,2)."</li>
                            <li>Total Credited: Ksh ".number_format($total,2)." 💰</li>
                            <li>Date: {$credited_at}</li>
                        </ul>
                        <p style='text-align:center; margin-top:20px;'>
                            <a href='https://gtraders.gt.tc/dashboard.php' style='display:inline-block; padding:12px 25px; background:#0288d1; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;'>Go to Dashboard</a>
                        </p>
                        <p style='margin-top:20px; font-size:14px; color:#555;'>Thank you for investing with GIBAL LTD.</p>
                    </div>
                ";
                $mail->send();
            } catch (Exception $e) {
                error_log("Maturity email failed: ".$mail->ErrorInfo);
            }
        }
        $conn->commit();
    }catch(Exception $e){
        $conn->rollback();
        $msg="Error processing matured investments: ".$e->getMessage();
    }
}

// ------------------ FETCH USER BALANCES ------------------
$stmt = $conn->prepare("SELECT account_balance, invested_balance, total_expected_interest, email, name FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$user=$stmt->get_result()->fetch_assoc();
$stmt->close();

// Pending withdrawals
$stmt=$conn->prepare("SELECT COALESCE(SUM(amount),0) AS pending FROM withdrawals WHERE user_id=? AND status='pending'");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$pending=$stmt->get_result()->fetch_assoc()['pending'] ?? 0;
$stmt->close();

$available_balance = ($user['account_balance'] ?? 0) - $pending;
$invested_balance = $user['invested_balance'] ?? 0;
$total_expected = $user['total_expected_interest'] ?? 0;

// Active investments count
$stmt=$conn->prepare("SELECT COUNT(*) as cnt FROM investments WHERE user_id=? AND status='active'");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$active_count=$stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

// ------------------ HANDLE NEW INVESTMENT ------------------
if($_SERVER['REQUEST_METHOD']==='POST'){
    $plan_id=intval($_POST['plan_id'] ?? 0);
    $amount=floatval($_POST['amount'] ?? 0);
    if($plan_id<=0 || $amount<=0){ 
        $msg="Invalid request."; 
    } else {
        $stmt=$conn->prepare("SELECT * FROM plans WHERE id=? LIMIT 1");
        $stmt->bind_param("i",$plan_id);
        $stmt->execute();
        $plan=$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if(!$plan) $msg="Plan not found.";
        elseif($amount>$available_balance) $msg="Insufficient balance!";
        elseif($amount<$plan['min_amount']) $msg="Amount below plan minimum (Ksh ".number_format($plan['min_amount'],2).")";
        elseif($amount>$plan['max_amount']) $msg="Amount exceeds plan maximum (Ksh ".number_format($plan['max_amount'],2).")";

        if(!$msg){
            $fee = round($amount*($investment_fee_percent/100),2);
            $net = $amount-$fee;
            $expected_profit = round(($net*$plan['interest_rate'])/100,2);
            $start_dt=new DateTime('now',$tz);
            $duration_hours = $plan['duration_hours']?$plan['duration_hours']:$plan['duration_days']*24;
            $whole_hours=floor($duration_hours);
            $fraction_minutes=($duration_hours-$whole_hours)*60;
            $end_dt=clone $start_dt;
            $end_dt->modify("+{$whole_hours} hours +{$fraction_minutes} minutes");
            $start_str=$start_dt->format('Y-m-d H:i:s');
            $end_str=$end_dt->format('Y-m-d H:i:s');

            $conn->begin_transaction();
            try{
                // Update user balances
                $stmt1=$conn->prepare("UPDATE users SET account_balance=account_balance-?, invested_balance=invested_balance+?, total_expected_interest=total_expected_interest+? WHERE id=?");
                $stmt1->bind_param("dddi",$amount,$net,$expected_profit,$user_id);
                $stmt1->execute();
                $stmt1->close();

                // Insert investment
                $stmt2=$conn->prepare("INSERT INTO investments (user_id, plan_id, amount, net_amount, fee, expected_interest, start_date, end_date) VALUES (?,?,?,?,?,?,?,?)");
                $stmt2->bind_param("iiidddss",$user_id,$plan_id,$amount,$net,$fee,$expected_profit,$start_str,$end_str);
                $stmt2->execute();
                $investment_id = $conn->insert_id;
                $stmt2->close();

                // Referral bonus
                $referrer_id = $conn->query("SELECT referred_by FROM users WHERE id=$user_id")->fetch_assoc()['referred_by'] ?? null;
                if($referrer_id){
                    $bonus_amount = round($net * 0.015, 2); // 1.5% of net investment
                    $conn->query("UPDATE users SET referral_wallet = referral_wallet + $bonus_amount WHERE id=$referrer_id");
                    $stmt_ref = $conn->prepare("INSERT INTO referral_bonus (referrer_id, referred_user_id, investment_id, bonus_amount, created_at) VALUES (?,?,?,?,NOW())");
                    $stmt_ref->bind_param("iiid", $referrer_id, $user_id, $investment_id, $bonus_amount);
                    $stmt_ref->execute();
                    $stmt_ref->close();
                }

                // Insert transaction
                $desc="Invested Ksh ".number_format($net,2)." in plan {$plan['name']}";
                $stmt3=$conn->prepare("INSERT INTO transactions (user_id,type,amount,description,created_at) VALUES (?,?,?,?,'$start_str')");
                $type='debit';
                $stmt3->bind_param("isds",$user_id,$type,$net,$desc);
                $stmt3->execute();
                $stmt3->close();

                // Record fee
                $stmt4=$conn->prepare("INSERT INTO daily_fees (user_id,fee_amount,fee_date) VALUES (?,?,?)");
                $stmt4->bind_param("ids",$user_id,$fee,$start_str);
                $stmt4->execute();
                $stmt4->close();

                $conn->commit();
                $success_msg="Investment successful! Fee Ksh ".number_format($fee,2)." collected. Expected profit: Ksh ".number_format($expected_profit,2);

                // ------------------ SEND NEW INVESTMENT EMAIL ------------------
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'gibal.ltd@gmail.com';
                    $mail->Password   = 'dkbcereljkmvzfqy';
                    $mail->SMTPSecure = 'tls';
                    $mail->Port       = 587;
                    $mail->setFrom('gibal.ltd@gmail.com', 'GIBAL LTD');
                    $mail->addAddress($user['email'], $user['name']);
                    $mail->isHTML(true);
                    $mail->Subject = "✅ Investment Confirmation";

                    $available_after_invest = $available_balance - $amount;

                    $mail->Body = "
                    <div style='max-width:600px; margin:auto; padding:20px; font-family:Arial,sans-serif; border:1px solid #e0e0e0; border-radius:10px; background:#f4f4f4; color:#333;'>
                        <h2 style='text-align:center; color:#0288d1;'>GIBAL LTD — Investment Confirmation</h2>
                        <p>Dear <strong>{$user['name']}</strong>,</p>
                        <p>Your investment has been successfully processed. Below are the details:</p>
                        <table style='width:100%; border-collapse:collapse; margin-top:10px;'>
                            <tr><td style='padding:8px; border-bottom:1px solid #ccc;'>Plan Name:</td><td style='padding:8px; border-bottom:1px solid #ccc;'>{$plan['name']}</td></tr>
                            <tr><td style='padding:8px; border-bottom:1px solid #ccc;'>Amount Invested:</td><td style='padding:8px; border-bottom:1px solid #ccc;'>Ksh ".number_format($amount,2)."</td></tr>
                            <tr><td style='padding:8px; border-bottom:1px solid #ccc;'>Fee Deducted:</td><td style='padding:8px; border-bottom:1px solid #ccc;'>Ksh ".number_format($fee,2)."</td></tr>
                            <tr><td style='padding:8px; border-bottom:1px solid #ccc;'>Net Amount Invested:</td><td style='padding:8px; border-bottom:1px solid #ccc;'>Ksh ".number_format($net,2)."</td></tr>
                            <tr><td style='padding:8px; border-bottom:1px solid #ccc;'>Expected Profit:</td><td style='padding:8px; border-bottom:1px solid #ccc;'>Ksh ".number_format($expected_profit,2)."</td></tr>
                            <tr><td style='padding:8px; border-bottom:1px solid #ccc;'>Balance After Investment:</td><td style='padding:8px; border-bottom:1px solid #ccc;'>Ksh ".number_format($available_after_invest,2)."</td></tr>
                            <tr><td style='padding:8px; border-bottom:1px solid #ccc;'>Investment Start:</td><td style='padding:8px; border-bottom:1px solid #ccc;'>{$start_str}</td></tr>
                            <tr><td style='padding:8px;'>Maturity Date:</td><td style='padding:8px;'>{$end_str}</td></tr>
                        </table>
                        <p style='text-align:center; margin-top:20px;'>
                            <a href='https://gtraders.gt.tc/dashboard.php' style='display:inline-block; padding:12px 25px; background:#0288d1; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;'>Go to Dashboard</a>
                        </p>
                        <p style='margin-top:20px; font-size:14px; color:#555;'>Thank you for investing with GIBAL LTD.</p>
                    </div>
                    ";
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Investment confirmation email failed: ".$mail->ErrorInfo);
                }

                header("Location: invest.php"); exit();
            }catch(Exception $e){
                $conn->rollback();
                $msg="Investment failed: ".$e->getMessage();
            }
        }
    }
}

// ------------------ FETCH PLANS FOR FRONT-END ------------------
$plans=$conn->query("SELECT * FROM plans ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invest — User</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {margin:0; font-family:Arial,sans-serif; min-height:100vh; overflow-x:hidden; display:flex; flex-direction:column; align-items:center; justify-content:center; position:relative; background:linear-gradient(120deg,#1e3c72,#2a5298); color:#fff;}
#bubbles-container {position:absolute; top:0; left:0; width:100%; height:100%; z-index:1;}
.bubble {position:absolute; border-radius:50%; background:radial-gradient(circle at 30% 30%, rgba(173,216,230,0.8), rgba(173,216,230,0.1)); box-shadow:0 0 8px rgba(173,216,230,0.4),0 0 15px rgba(173,216,230,0.2) inset; animation: floatUp linear infinite, sparkle 2s infinite alternate;}
@keyframes floatUp {0% {transform:translateY(0) scale(0.5); opacity:0.5;}100% {transform:translateY(-1000px) scale(1); opacity:0;}}
@keyframes sparkle {0% {transform: scale(0.8); opacity:0.6;}100% {transform: scale(1.2); opacity:1;}}
.container {position:relative; z-index:2; width:95%; max-width:550px; background: rgba(255,255,255,0.1); padding:30px; border-radius:15px; box-shadow:0 15px 35px rgba(0,0,0,0.25); text-align:center;}
h2 {margin-bottom:20px; color:#e0f7fa;}
form input, form select {margin-bottom:15px; padding:12px; width:100%; border-radius:8px; border:none;}
.btn-gold {background:#FFD700; color:#000; border:none; width:100%; padding:12px; border-radius:6px; font-weight:bold;}
.btn-gold:hover {opacity:0.9;}
.msg {margin-bottom:15px; font-weight:bold; color:#ffd;}
.balance, .active_invest, .expected_total {font-size:16px; margin-bottom:10px; font-weight:bold; color:#b3e5fc;}
.plan-info {display:flex; flex-direction:column; padding:15px; border-radius:10px; font-weight:bold; color:#000; transition:0.3s; margin-bottom:15px;}
.plan-info table {width:100%; border-collapse:collapse; text-align:center;}
.plan-info td {padding:8px; border-bottom:1px solid rgba(0,0,0,0.1);}
#balanceWarning {color:red; font-weight:bold;}
#successOverlay {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); backdrop-filter:blur(5px); justify-content:center; align-items:center; z-index:9999;}
#successMessage {background:#28a745; color:#fff; padding:20px 30px; border-radius:10px; font-size:18px; font-weight:bold; text-align:center; max-width:80%; box-shadow:0 5px 20px rgba(0,0,0,0.3);}
</style>
    <link rel="icon" type="image/png" href="favicon.png">
</head>
<body>
<div id="bubbles-container"></div>
<div class="container">
    <h2>Invest in a Plan</h2>
    <div class="balance">Available Balance: Ksh <?=number_format($available_balance,2)?></div>
    <div class="active_invest">Active Investments: <?=$active_count?></div>
    <div class="expected_total">Expected Profit: Ksh <?=number_format($total_expected,2)?></div>
    <?php if($msg && !$success): ?>
        <div class="msg"><?=htmlspecialchars($msg)?></div>
    <?php endif; ?>
    <form method="post">
        <select name="plan_id" id="planSelect" required>
            <option value="">Select a Plan</option>
            <?php while($p = $plans->fetch_assoc()): ?>
                <option value="<?=intval($p['id'])?>" 
                        data-min="<?=floatval($p['min_amount'])?>" 
                        data-max="<?=floatval($p['max_amount'])?>" 
                        data-name="<?=htmlspecialchars($p['name'])?>" 
                        data-interest="<?=floatval($p['interest_rate'])?>"
                        data-duration="<?=floatval($p['duration_hours']??($p['duration_days']*24))?>"
                        data-color="<?=$p['color']?>"
                        >
                    <?=htmlspecialchars($p['name'])?>
                </option>
            <?php endwhile; ?>
        </select>
        <div class="plan-info" id="planInfo" style="display:none;">
            <table>
                <tr><td id="planName"></td></tr>
                <tr><td id="planMin"></td></tr>
                <tr><td id="planMax"></td></tr>
                <tr><td id="planInterest"></td></tr>
                <tr><td id="planDuration"></td></tr>
                <tr><td id="feeDisplay"></td></tr>
                <tr><td id="netDisplay"></td></tr>
                <tr><td id="balanceWarning"></td></tr>
            </table>
        </div>
        <input type="number" step="0.01" name="amount" id="amountInput" placeholder="Enter amount to invest" max="<?=$available_balance?>" required>
        <button type="submit" class="btn-gold">Invest Now</button>
    </form>
</div>
<div style="width:95%; max-width:500px; margin-top:15px; text-align:center; z-index:3; position:relative;">
    <a href="dashboard.php" class="btn-gold" style="display:inline-block; z-index:3; position:relative;">Back to Dashboard</a>
</div>
<div id="successOverlay"><div id="successMessage"></div></div>
<script>
const bubblesContainer = document.getElementById('bubbles-container');
const numBubbles = 50;
for(let i=0;i<numBubbles;i++){
    const bubble = document.createElement('div');
    bubble.classList.add('bubble');
    const size = Math.random()*12 + 4;
    bubble.style.width = bubble.style.height = size + 'px';
    bubble.style.left = Math.random()*window.innerWidth + 'px';
    bubble.style.animationDuration = (Math.random()*8 + 6) + 's';
    bubble.style.animationDelay = (Math.random()*5) + 's';
    bubblesContainer.appendChild(bubble);
}
const planSelect = document.getElementById('planSelect');
const planInfo = document.getElementById('planInfo');
const planName = document.getElementById('planName');
const planMin = document.getElementById('planMin');
const planMax = document.getElementById('planMax');
const planInterest = document.getElementById('planInterest');
const planDuration = document.getElementById('planDuration');
const feeDisplay = document.getElementById('feeDisplay');
const netDisplay = document.getElementById('netDisplay');
const balanceWarning = document.getElementById('balanceWarning');
const amountInput = document.getElementById('amountInput');
const investmentFeePercent = <?= $investment_fee_percent ?>;
planSelect.addEventListener('change', function(){
    const selected = this.options[this.selectedIndex];
    if(selected.value){
        planInfo.style.display = 'flex';
        planName.innerHTML = "Plan: "+selected.dataset.name;
        planMin.innerHTML = "Min Amount: Ksh "+parseFloat(selected.dataset.min).toFixed(2);
        planMax.innerHTML = "Max Amount: Ksh "+parseFloat(selected.dataset.max).toFixed(2);
        planInterest.innerHTML = "Interest: "+parseFloat(selected.dataset.interest).toFixed(2)+"%";
        let duration = parseFloat(selected.dataset.duration);
        if(duration < 24){
            planDuration.innerHTML = "Duration: "+duration+" hours";
        } else {
            let days = Math.floor(duration/24);
            let hours = duration % 24;
            planDuration.innerHTML = "Duration: "+days+" day(s)"+(hours>0?" "+hours+" hour(s)":"");
        }
        planInfo.style.backgroundColor = selected.dataset.color;
        planInfo.style.color = '#000';
        const minAmt = parseFloat(selected.dataset.min);
        if(<?=$available_balance?> < minAmt){
            balanceWarning.innerHTML = "Your balance is lower than the minimum required for this plan!";
        } else balanceWarning.innerHTML = "";
        updateFeeDisplay();
    } else {
        planInfo.style.display = 'none';
        feeDisplay.textContent = "";
        netDisplay.textContent = "";
    }
});
amountInput.addEventListener('input', updateFeeDisplay);
function updateFeeDisplay(){
    const amt = parseFloat(amountInput.value) || 0;
    const fee = amt * (investmentFeePercent/100);
    const net = amt - fee;
    feeDisplay.textContent = "Fee: Ksh "+fee.toFixed(2);
    netDisplay.textContent = "Net Invested: Ksh "+net.toFixed(2);
}
// ----------------- SUCCESS OVERLAY -----------------
<?php if($success_msg): ?>
window.addEventListener('load', function(){
    const overlay = document.getElementById('successOverlay');
    const messageDiv = document.getElementById('successMessage');
    messageDiv.textContent = "<?=htmlspecialchars($success_msg)?>";
    overlay.style.display = "flex";
    setTimeout(() => {
        overlay.style.opacity = "0";
        setTimeout(()=> location.reload(), 600);
    }, 3000);
});
<?php endif; ?>
</script>
</body>
</html>