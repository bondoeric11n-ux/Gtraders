<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include_once("db_connect.php");

// PHPMailer setup
require_once 'src/Exception.php';
require_once 'src/PHPMailer.php';
require_once 'src/SMTP.php';

$mailUsername   = 'gibal.ltd@gmail.com';
$mailPassword   = 'dkbcereljkmvzfqy';
$mailFromName   = 'GIBAL LTD';
$supportEmail   = 'gibal.ltd@gmail.com';
$supportPhone   = '+254703834247';

// --- Ensure user is logged in ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = (int)$_SESSION['user_id'];
$error = "";
$showSuccessModal = false;

// --- Fetch user info ---
$stmtUser = $conn->prepare("SELECT id, username, name, email, account_balance, invested_balance FROM users WHERE id=? LIMIT 1");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$userRes = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();
if (!$userRes) die("User not found.");
$userEmail = $userRes['email'];
$userName  = $userRes['name'] ?: $userRes['username'];

// --- Handle withdrawal submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['withdraw_token']) || !hash_equals($_SESSION['withdraw_token'] ?? '', $_POST['withdraw_token'])) {
        $error = "Invalid or duplicate form submission.";
    } else {
        unset($_SESSION['withdraw_token']);
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        if ($amount <= 0) {
            $error = "Invalid withdrawal amount.";
        } else {
            $conn->begin_transaction();
            try {
                // Lock user row
                $stmt = $conn->prepare("SELECT account_balance, invested_balance FROM users WHERE id = ? FOR UPDATE");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->bind_result($account_balance, $invested_balance);
                if (!$stmt->fetch()) throw new Exception("User not found.");
                $stmt->close();

                // Pending withdrawals
                $wdStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE user_id=? AND status='pending'");
                $wdStmt->bind_param("i", $user_id);
                $wdStmt->execute();
                $wdStmt->bind_result($pending_withdrawals);
                $wdStmt->fetch();
                $wdStmt->close();

                $available_balance = round($account_balance - $pending_withdrawals, 2);

                if ($amount > $available_balance) {
                    $conn->rollback();
                    $error = "You only have Ksh " . number_format($available_balance, 2) . " available for withdrawal.";
                } else {
                    // Duplicate prevention
                    $dup = $conn->prepare("
                        SELECT COUNT(*) FROM withdrawals 
                        WHERE user_id = ? AND status = 'pending' AND amount = ? AND created_at > (NOW() - INTERVAL 30 SECOND)
                    ");
                    $dup->bind_param("id", $user_id, $amount);
                    $dup->execute();
                    $dup->bind_result($cnt);
                    $dup->fetch();
                    $dup->close();

                    if ($cnt > 0) {
                        $conn->rollback();
                        $error = "A similar withdrawal request is already pending. Please wait.";
                    } else {
                        // Insert withdrawal
                        $insert = $conn->prepare("INSERT INTO withdrawals (user_id, amount, status, created_at) VALUES (?, ?, 'pending', NOW())");
                        $insert->bind_param("id", $user_id, $amount);
                        $insert->execute();
                        $withdraw_id = $insert->insert_id;
                        $insert->close();
                        $conn->commit();

                        // Send professional email (pending)
                        sendWithdrawalEmail($userEmail, $userName, $amount, $withdraw_id, 'pending');
                        header("Location: withdraw.php?success=1");
                        exit();
                    }
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Something went wrong. " . $e->getMessage();
            }
        }
    }
}

// --- Generate new CSRF token ---
$_SESSION['withdraw_token'] = bin2hex(random_bytes(16));

// --- Fetch balances for display ---
$stmt = $conn->prepare("SELECT account_balance, invested_balance FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($account_balance, $invested_balance);
$stmt->fetch();
$stmt->close();

// Pending withdrawals
$wdStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE user_id=? AND status='pending'");
$wdStmt->bind_param("i", $user_id);
$wdStmt->execute();
$wdStmt->bind_result($pending_withdrawals);
$wdStmt->fetch();
$wdStmt->close();

$available_balance = round($account_balance - $pending_withdrawals, 2);
$total_balance = round($available_balance + $invested_balance + $pending_withdrawals, 2);

// Recent withdrawals
$wdHistoryStmt = $conn->prepare("SELECT id, amount, status, created_at FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$wdHistoryStmt->bind_param("i", $user_id);
$wdHistoryStmt->execute();
$wdHistoryResult = $wdHistoryStmt->get_result();
$withdrawals = $wdHistoryResult->fetch_all(MYSQLI_ASSOC);
$wdHistoryStmt->close();
$showSuccessModal = (isset($_GET['success']) && $_GET['success']==1);

// --- Function to send professional emails ---
function sendWithdrawalEmail($userEmail, $userName, $amount, $withdraw_id, $status) {
    global $mailUsername, $mailPassword, $mailFromName, $supportEmail, $supportPhone;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailUsername;
        $mail->Password   = $mailPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->setFrom($mailUsername, $mailFromName);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);

        // User timezone
        $tz = new DateTimeZone('Africa/Nairobi');
        $now = new DateTime('now', $tz);
        $formattedDate = $now->format('l, d F Y H:i:s T');

        switch ($status) {
            case 'pending':
                $mail->Subject = "Withdrawal Request Received — GIBAL LTD";
                $color = "#FFD700";
                $statusText = "Pending Review";
                $header = "Withdrawal Request Received";
                $bodyText = "We have received your withdrawal request and it is currently <strong>pending review</strong>.";
                break;
            case 'approved':
                $mail->Subject = "Withdrawal Approved — GIBAL LTD";
                $color = "#28a745";
                $statusText = "Approved";
                $header = "Withdrawal Approved";
                $bodyText = "Your withdrawal request has been <strong>approved</strong> and funds have been credited to your account.";
                break;
            case 'rejected':
                $mail->Subject = "Withdrawal Rejected — GIBAL LTD";
                $color = "#dc3545";
                $statusText = "Rejected";
                $header = "Withdrawal Rejected";
                $bodyText = "We regret to inform you that your withdrawal request has been <strong>rejected</strong>.";
                break;
            default:
                return;
        }

        $mail->Body = "
        <div style='font-family:Arial,sans-serif; max-width:600px; margin:auto; padding:20px; border:2px solid {$color}; border-radius:12px; background:#000; color:{$color};'>
            <h2 style='text-align:center; margin-bottom:20px;'>{$header}</h2>
            <p>Hi <strong>{$userName}</strong>,</p>
            <p>{$bodyText}</p>
            <table style='width:100%; margin-top:15px; border-collapse:collapse;'>
                <tr><td style='padding:8px; border-bottom:1px solid {$color};'>Amount</td><td style='padding:8px; border-bottom:1px solid {$color};'>Ksh ".number_format($amount,2)."</td></tr>
                <tr><td style='padding:8px; border-bottom:1px solid {$color};'>Reference ID</td><td style='padding:8px; border-bottom:1px solid {$color};'>{$withdraw_id}</td></tr>
                <tr><td style='padding:8px;'>Date & Time</td><td style='padding:8px;'>{$formattedDate}</td></tr>
                <tr><td style='padding:8px;'>Status</td><td style='padding:8px;'>{$statusText}</td></tr>
            </table>
            <p style='text-align:center; margin-top:25px;'>
                <a href='https://gtraders.gt.tc/dashboard.php' style='display:inline-block; padding:12px 25px; background:{$color}; color:#000; border-radius:6px; text-decoration:none; font-weight:bold;'>Go to Dashboard</a>
            </p>";

        if ($status == 'rejected') {
            $mail->Body .= "<p>If you believe this is an error, contact us at <a href='mailto:$supportEmail'>$supportEmail</a> or call $supportPhone.</p>";
        }

        $mail->Body .= "<p>Thank you for trusting <strong>GIBAL LTD</strong>!</p></div>";

        $mail->AltBody = "Hi {$userName},\n\nYour withdrawal request of Ksh ".number_format($amount,2)." (Reference ID: {$withdraw_id}) is {$statusText} on {$formattedDate}.\nGo to Dashboard: https://gtraders.gt.tc/dashboard.php\n\nThank you, GIBAL LTD";

        $mail->send();
    } catch (Exception $e) {
        error_log("Withdrawal email error ({$status}): ".$mail->ErrorInfo);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Withdraw Funds | GIBAL LTD</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ============================================
   PREMIUM FINTECH DESIGN SYSTEM (Shared)
   ============================================ */
:root {
    --bg-primary: #0a0e1a;
    --bg-secondary: #0f1420;
    --bg-tertiary: #151b2b;
    --bg-card: rgba(17, 24, 39, 0.65);
    --bg-card-hover: rgba(22, 30, 46, 0.8);
    --bg-elevated: rgba(30, 41, 59, 0.5);
    
    --gold: #d4af37;
    --gold-light: #f4d03f;
    --gold-dark: #b8941f;
    --gold-glow: rgba(212, 175, 55, 0.15);
    --gold-border: rgba(212, 175, 55, 0.2);
    
    --text-primary: #f1f5f9;
    --text-secondary: #94a3b8;
    --text-tertiary: #64748b;
    
    --success: #10b981;
    --success-bg: rgba(16, 185, 129, 0.12);
    --success-border: rgba(16, 185, 129, 0.25);
    --warning: #f59e0b;
    --warning-bg: rgba(245, 158, 11, 0.12);
    --warning-border: rgba(245, 158, 11, 0.25);
    --danger: #ef4444;
    --danger-bg: rgba(239, 68, 68, 0.12);
    --danger-border: rgba(239, 68, 68, 0.25);
    --info: #3b82f6;
    --info-bg: rgba(59, 130, 246, 0.12);
    --info-border: rgba(59, 130, 246, 0.25);
    
    --border-subtle: rgba(148, 163, 184, 0.08);
    --border-medium: rgba(148, 163, 184, 0.15);
    --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.2);
    --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.3);
    --shadow-lg: 0 20px 50px rgba(0, 0, 0, 0.4);
    --shadow-gold: 0 8px 30px rgba(212, 175, 55, 0.15);
    
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-xl: 20px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--bg-primary);
    color: var(--text-primary);
    overflow-x: hidden;
    line-height: 1.6;
    font-variant-numeric: tabular-nums;
    background-image: 
        radial-gradient(ellipse at top left, rgba(212, 175, 55, 0.04) 0%, transparent 50%),
        radial-gradient(ellipse at bottom right, rgba(59, 130, 246, 0.03) 0%, transparent 50%);
    min-height: 100vh;
}

/* Navbar (Shared) */
.navbar {
    position: fixed; top: 0; width: 100%;
    background: rgba(10, 14, 26, 0.85);
    backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid var(--border-subtle);
    z-index: 1000; padding: 0 24px; height: 70px;
    display: flex; align-items: center; justify-content: space-between;
}
.nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--text-primary); }
.nav-brand-logo {
    width: 38px; height: 38px;
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    border-radius: 10px; display: flex; align-items: center; justify-content: center;
    color: var(--bg-primary); font-weight: 800; font-size: 1.1rem;
}
.nav-brand-text { font-weight: 700; font-size: 1.15rem; letter-spacing: -0.02em; }
.nav-brand-text span { color: var(--gold); }
.nav-links { display: flex; align-items: center; gap: 8px; }
.nav-link {
    padding: 10px 16px; color: var(--text-secondary); text-decoration: none;
    font-weight: 500; font-size: 0.9rem; border-radius: var(--radius-sm);
    transition: all 0.2s ease; display: flex; align-items: center; gap: 8px;
}
.nav-link:hover { color: var(--text-primary); background: var(--bg-elevated); }
.nav-link.active { color: var(--gold); background: var(--gold-glow); }

.user-menu { position: relative; margin-left: 16px; }
.user-trigger {
    display: flex; align-items: center; gap: 10px; padding: 6px 12px 6px 6px;
    background: var(--bg-elevated); border: 1px solid var(--border-subtle);
    border-radius: 50px; cursor: pointer; transition: all 0.2s ease;
}
.user-trigger:hover { background: var(--bg-card-hover); border-color: var(--gold-border); }
.user-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    display: flex; align-items: center; justify-content: center;
    color: var(--bg-primary); font-weight: 700; font-size: 0.9rem;
}
.user-name {
    font-weight: 600; font-size: 0.85rem; color: var(--text-primary);
    max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.user-dropdown {
    position: absolute; top: calc(100% + 8px); right: 0;
    background: var(--bg-secondary); border: 1px solid var(--border-medium);
    border-radius: var(--radius-md); box-shadow: var(--shadow-lg);
    min-width: 220px; opacity: 0; visibility: hidden; transform: translateY(-10px);
    transition: all 0.2s ease; overflow: hidden;
}
.user-dropdown.active { opacity: 1; visibility: visible; transform: translateY(0); }
.dropdown-header { padding: 16px; border-bottom: 1px solid var(--border-subtle); }
.dropdown-header .name { font-weight: 600; color: var(--text-primary); margin-bottom: 2px; }
.dropdown-header .email { font-size: 0.8rem; color: var(--text-tertiary); }
.dropdown-item {
    display: flex; align-items: center; gap: 12px; padding: 12px 16px;
    color: var(--text-secondary); text-decoration: none; font-size: 0.9rem;
    transition: all 0.15s ease; border-left: 3px solid transparent;
}
.dropdown-item:hover { background: var(--bg-elevated); color: var(--text-primary); border-left-color: var(--gold); }
.dropdown-item i { width: 18px; color: var(--text-tertiary); }
.dropdown-item:hover i { color: var(--gold); }
.dropdown-divider { height: 1px; background: var(--border-subtle); margin: 4px 0; }

/* Toast Notifications */
.toast-container {
    position: fixed; top: 90px; right: 24px; z-index: 9999;
    display: flex; flex-direction: column; gap: 10px; pointer-events: none;
}
.toast {
    background: var(--bg-secondary); border: 1px solid var(--gold-border);
    border-left: 4px solid var(--gold); border-radius: var(--radius-md);
    padding: 14px 18px; box-shadow: var(--shadow-lg);
    display: flex; align-items: center; gap: 12px; min-width: 300px; max-width: 400px;
    transform: translateX(450px); opacity: 0; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: auto;
}
.toast.show { transform: translateX(0); opacity: 1; }
.toast-icon {
    width: 36px; height: 36px; border-radius: 50%; background: var(--gold-glow);
    display: flex; align-items: center; justify-content: center; color: var(--gold); flex-shrink: 0;
}
.toast-content { flex: 1; }
.toast-title { font-weight: 600; font-size: 0.9rem; color: var(--text-primary); margin-bottom: 2px; }
.toast-message { font-size: 0.8rem; color: var(--text-secondary); }
.toast-close {
    background: none; border: none; color: var(--text-tertiary); cursor: pointer;
    padding: 4px; font-size: 1rem; transition: color 0.2s;
}
.toast-close:hover { color: var(--text-primary); }

/* Main Layout */
.container {
    max-width: 800px; margin: 0 auto; padding: 100px 24px 60px;
}

.page-header {
    margin-bottom: 32px; text-align: center;
}
.page-header h1 {
    font-size: 2rem; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 8px;
}
.page-header p { color: var(--text-secondary); font-size: 1rem; max-width: 600px; margin: 0 auto; }

/* Cards */
.card {
    background: var(--bg-card);
    backdrop-filter: blur(12px);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    padding: 32px;
    margin-bottom: 24px;
}

.card-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border-subtle);
}
.card-title {
    font-size: 1.2rem; font-weight: 700; color: var(--text-primary);
    display: flex; align-items: center; gap: 10px;
}
.card-title i { color: var(--gold); }

/* Balance Display */
.balance-display {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px;
}
.balance-box {
    background: var(--bg-elevated); border-radius: var(--radius-md); padding: 20px; text-align: center;
    border: 1px solid var(--border-subtle); transition: all 0.3s ease;
}
.balance-box.highlight {
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(212, 175, 55, 0.05) 100%);
    border-color: var(--gold-border);
}
.balance-label { font-size: 0.75rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; font-weight: 600; }
.balance-value { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); }
.balance-value.gold { color: var(--gold); }

/* Forms */
.form-group { margin-bottom: 20px; }
.form-label {
    display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);
    margin-bottom: 8px;
}
.form-input {
    width: 100%; padding: 14px 16px;
    background: var(--bg-elevated); border: 1px solid var(--border-medium);
    border-radius: var(--radius-sm); color: var(--text-primary);
    font-size: 1.1rem; font-weight: 600; transition: all 0.2s ease; font-family: inherit;
}
.form-input:focus {
    outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px var(--gold-glow);
}
.form-input::placeholder { color: var(--text-tertiary); font-weight: 400; }

.form-hint { font-size: 0.8rem; color: var(--text-tertiary); margin-top: 8px; display: flex; align-items: center; gap: 6px; }

.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px 24px; border-radius: var(--radius-sm); font-weight: 600;
    font-size: 1rem; cursor: pointer; transition: all 0.2s ease; border: none;
    text-decoration: none; width: 100%;
}
.btn-primary {
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-primary);
}
.btn-primary:hover {
    transform: translateY(-2px); box-shadow: var(--shadow-gold);
}
.btn-primary:disabled {
    background: var(--bg-elevated); color: var(--text-tertiary); cursor: not-allowed;
    transform: none; box-shadow: none;
}

/* Info Box */
.info-box {
    background: var(--info-bg); border: 1px solid var(--info-border);
    border-left: 4px solid var(--info); padding: 16px 20px;
    border-radius: var(--radius-md); margin-bottom: 24px;
    color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6;
}
.info-box strong { color: var(--info); display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 0.95rem; }
.info-box ul { margin: 8px 0 0 0; padding-left: 20px; }
.info-box li { margin-bottom: 4px; }

/* Tables */
.table-container {
    border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-subtle);
}
.table-scroll { max-height: 350px; overflow-y: auto; }
.table-scroll::-webkit-scrollbar { width: 6px; }
.table-scroll::-webkit-scrollbar-thumb { background: var(--border-medium); border-radius: 3px; }

table { width: 100%; border-collapse: collapse; min-width: 500px; }
thead { position: sticky; top: 0; z-index: 5; background: var(--bg-secondary); }
th {
    padding: 14px 18px; text-align: left; font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-tertiary);
    border-bottom: 1px solid var(--border-subtle);
}
td {
    padding: 16px 18px; font-size: 0.9rem; color: var(--text-primary);
    border-bottom: 1px solid var(--border-subtle);
}
tbody tr:hover { background: var(--bg-elevated); }

/* Badges */
.badge {
    display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px;
    border-radius: 50px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;
}
.badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.badge-approved, .badge-completed { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border); }
.badge-rejected, .badge-failed { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger-border); }
.badge-pending, .badge-processing { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning-border); }

/* Footer (Shared) */
.footer {
    background: var(--bg-secondary); border-top: 1px solid var(--border-subtle);
    padding: 40px 24px 20px; margin-top: 60px;
}
.footer-content {
    max-width: 1280px; margin: 0 auto; display: grid;
    grid-template-columns: 2fr 1fr 1fr; gap: 40px; margin-bottom: 30px;
}
.footer-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.footer-brand-text { font-weight: 700; font-size: 1.1rem; color: var(--text-primary); }
.footer-brand-text span { color: var(--gold); }
.footer-about { color: var(--text-secondary); font-size: 0.88rem; line-height: 1.6; margin-bottom: 16px; }
.footer-contact { display: flex; flex-direction: column; gap: 8px; font-size: 0.85rem; color: var(--text-secondary); }
.footer-contact a { color: var(--gold); text-decoration: none; }
.footer-heading {
    font-size: 0.85rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; color: var(--text-primary); margin-bottom: 16px;
}
.footer-links { list-style: none; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.footer-links a { color: var(--text-secondary); text-decoration: none; font-size: 0.88rem; transition: color 0.2s; }
.footer-links a:hover { color: var(--gold); }
.footer-bottom {
    max-width: 1280px; margin: 0 auto; padding-top: 20px;
    border-top: 1px solid var(--border-subtle); display: flex;
    justify-content: space-between; align-items: center; flex-wrap: wrap;
    gap: 12px; font-size: 0.82rem; color: var(--text-tertiary);
}

/* Responsive */
@media (max-width: 768px) {
    .navbar { padding: 0 16px; height: 64px; }
    .nav-links { display: none; }
    .user-name { display: none; }
    .container { padding: 84px 16px 40px; }
    .balance-display { grid-template-columns: 1fr; }
    .card { padding: 24px 20px; }
    .footer-content { grid-template-columns: 1fr; gap: 30px; }
    .footer-bottom { flex-direction: column; text-align: center; }
}
</style>
<link rel="icon" type="image/png" href="favicon.png">
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <a href="index.php" class="nav-brand">
        <div class="nav-brand-logo">G</div>
        <div class="nav-brand-text">GIBAL <span>LTD</span></div>
    </a>
    <div class="nav-links">
        <a href="dashboard.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="withdraw.php" class="nav-link active"><i class="fas fa-money-check-alt"></i> Withdraw</a>
    </div>
    <div class="user-menu">
        <div class="user-trigger" onclick="toggleUserMenu()">
            <div class="user-avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
            <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: var(--text-tertiary);"></i>
        </div>
        <div class="user-dropdown" id="userDropdown">
            <div class="dropdown-header">
                <div class="name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="email">Member ID: #<?php echo $user_id; ?></div>
            </div>
            <a href="dashboard.php" class="dropdown-item"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="profile.php" class="dropdown-item"><i class="fas fa-user"></i> My Profile</a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" class="dropdown-item" style="color: var(--danger);">
                <i class="fas fa-sign-out-alt" style="color: var(--danger);"></i> Sign Out
            </a>
        </div>
    </div>
</nav>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Main Content -->
<div class="container">
    
    <div class="page-header">
        <h1>Withdraw Funds</h1>
        <p>Request a withdrawal from your available balance. Funds are processed securely and promptly.</p>
    </div>

    <!-- Balance Overview -->
    <div class="balance-display">
        <div class="balance-box highlight">
            <div class="balance-label">Available for Withdrawal</div>
            <div class="balance-value gold">Ksh <?=number_format($available_balance, 2)?></div>
        </div>
        <div class="balance-box">
            <div class="balance-label">Pending Withdrawals</div>
            <div class="balance-value">Ksh <?=number_format($pending_withdrawals, 2)?></div>
        </div>
        <div class="balance-box">
            <div class="balance-label">Total Account Balance</div>
            <div class="balance-value">Ksh <?=number_format($total_balance, 2)?></div>
        </div>
    </div>

    <!-- Withdrawal Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-paper-plane"></i> New Withdrawal Request</div>
        </div>

        <form method="POST" class="withdraw-form">
            <div class="form-group">
                <label class="form-label">Amount to Withdraw (Ksh)</label>
                <input type="number" step="0.01" name="amount" class="form-input" max="<?=$available_balance?>" placeholder="e.g., 5000.00" required>
                <div class="form-hint"><i class="fas fa-info-circle"></i> You can withdraw up to your available balance. Pending requests are temporarily deducted.</div>
            </div>
            
            <input type="hidden" name="withdraw_token" value="<?=htmlspecialchars($_SESSION['withdraw_token'])?>">
            
            <button type="submit" class="btn btn-primary" <?php if($available_balance <= 0) echo 'disabled'; ?>>
                <i class="fas fa-lock"></i> Submit Withdrawal Request
            </button>
        </form>
    </div>

    <!-- Withdrawal Rules -->
    <div class="info-box">
        <strong><i class="fas fa-shield-alt"></i> Withdrawal Processing Rules</strong>
        <ul>
            <li>Withdrawals are processed manually within operational hours (Mon – Fri, 9:00 AM – 5:00 PM EAT).</li>
            <li>Requested amounts are immediately deducted from your <strong>Available Balance</strong> and marked as "Pending".</li>
            <li>If a withdrawal is rejected, the funds will be instantly refunded to your available balance.</li>
            <li>Ensure your registered account details match the withdrawal destination to prevent verification delays.</li>
        </ul>
    </div>

    <!-- Recent Withdrawals -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-history"></i> Recent Withdrawal Requests</div>
        </div>
        
        <?php if (count($withdrawals) === 0): ?>
            <div style="text-align: center; padding: 40px 20px; color: var(--text-tertiary);">
                <i class="fas fa-inbox" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.4;"></i>
                <p>No withdrawal requests found. Your history will appear here.</p>
            </div>
        <?php else: ?>
        <div class="table-container">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($withdrawals as $wd):
                            $status_class = 'badge-' . strtolower($wd['status']);
                            $date_formatted = date('M d, Y', strtotime($wd['created_at']));
                            $time_formatted = date('H:i', strtotime($wd['created_at']));
                        ?>
                        <tr>
                            <td style="font-family: monospace; color: var(--text-secondary);">#<?=str_pad($wd['id'], 5, '0', STR_PAD_LEFT)?></td>
                            <td style="font-weight: 600;">Ksh <?=number_format($wd['amount'], 2)?></td>
                            <td><span class="badge <?=$status_class?>"><?=ucfirst($wd['status'])?></span></td>
                            <td style="color: var(--text-secondary); font-size: 0.85rem;"><?=$date_formatted?> <span style="opacity: 0.7;"><?=$time_formatted?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Footer -->
<footer class="footer">
    <div class="footer-content">
        <div>
            <div class="footer-brand">
                <div class="nav-brand-logo">G</div>
                <div class="footer-brand-text">GIBAL <span>LTD</span></div>
            </div>
            <p class="footer-about">GIBAL LTD is a registered investment platform operating in Kenya, providing secure and transparent investment opportunities for our clients.</p>
            <div class="footer-contact">
                <div><i class="fas fa-envelope" style="color: var(--gold); margin-right: 8px;"></i> <a href="mailto:gibal.ltd@gmail.com">gibal.ltd@gmail.com</a></div>
                <div><i class="fas fa-clock" style="color: var(--gold); margin-right: 8px;"></i> Mon – Fri, 9:00 AM – 5:00 PM (EAT)</div>
            </div>
        </div>
        <div>
            <h4 class="footer-heading">Quick Links</h4>
            <ul class="footer-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="deposit.php">Deposit</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        <div>
            <h4 class="footer-heading">Legal</h4>
            <ul class="footer-links">
                <li><a href="terms.php">Terms & Conditions</a></li>
                <li><a href="privacy.php">Privacy Policy</a></li>
                <li><a href="rules.php">User Rules</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div>&copy; <?php echo date('Y'); ?> GIBAL LTD. All rights reserved.</div>
        <div><i class="fas fa-shield-alt" style="color: var(--success); margin-right: 6px;"></i> Secured with 256-bit SSL encryption</div>
    </div>
</footer>

<script>
// User Dropdown Toggle
function toggleUserMenu() {
    document.getElementById('userDropdown').classList.toggle('active');
}
document.addEventListener('click', function(e) {
    const userMenu = document.querySelector('.user-menu');
    if (!userMenu.contains(e.target)) {
        document.getElementById('userDropdown').classList.remove('active');
    }
});

// Toast Notification System
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast';
    
    const icons = {
        info: 'fa-info-circle',
        success: 'fa-check-circle',
        warning: 'fa-exclamation-triangle',
        error: 'fa-times-circle'
    };
    
    toast.innerHTML = `
        <div class="toast-icon"><i class="fas ${icons[type] || icons.info}"></i></div>
        <div class="toast-content">
            <div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 4000);
}

// Trigger Toast on Page Load if PHP set an error
<?php if (!empty($error)): ?>
window.addEventListener('load', () => {
    showToast("<?php echo addslashes($error); ?>", "error");
});
<?php endif; ?>

// Trigger Success Toast and Redirect
<?php if ($showSuccessModal): ?>
window.addEventListener('load', () => {
    showToast("Withdrawal request submitted successfully!", "success");
    setTimeout(() => { window.location.href = 'withdraw.php'; }, 2000);
});
<?php endif; ?>
</script>
</body>
</html>