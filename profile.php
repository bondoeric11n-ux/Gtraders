<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include_once("db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$toast_message = "";
$toast_type = "info"; // success, error, warning, info

// --- SYSTEM MAINTENANCE NOTICE ---
$maintenance_notice = "";
$maint_q = $conn->query("SELECT mode, message FROM system_status LIMIT 1");
if ($maint_q && $row = $maint_q->fetch_assoc()) {
    if ($row['mode'] === 'maintenance') {
        $maintenance_notice = $row['message'] ?: 'The system is currently under maintenance.';
    }
}

// --- HANDLE PROFILE UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Update profile fields
    if (isset($_POST['name'])) {
        $name = trim($_POST['name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        $update = $conn->prepare("UPDATE users SET name=?, username=?, email=?, phone=? WHERE id=?");
        $update->bind_param("ssssi", $name, $username, $email, $phone, $user_id);
        
        if ($update->execute()) {
            $toast_message = "Profile updated successfully.";
            $toast_type = "success";
            $_SESSION['username'] = $username;
        } else {
            $toast_message = "Update failed: " . $conn->error;
            $toast_type = "error";
        }
    }

    // 2. Set who referred the user (one-time only)
    if (isset($_POST['referred_by_code'])) {
        $referred_by_code = strtoupper(trim($_POST['referred_by_code']));
        if ($referred_by_code) {
            $check_stmt = $conn->prepare("SELECT referred_by FROM users WHERE id=?");
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check = $check_stmt->get_result()->fetch_assoc()['referred_by'];
            $check_stmt->close();

            if (!$check) {
                $ref_stmt = $conn->prepare("SELECT id FROM users WHERE referral_code=?");
                $ref_stmt->bind_param("s", $referred_by_code);
                $ref_stmt->execute();
                $referrer = $ref_stmt->get_result()->fetch_assoc();
                $ref_stmt->close();

                if ($referrer && $referrer['id'] != $user_id) {
                    $update_ref = $conn->prepare("UPDATE users SET referred_by=? WHERE id=?");
                    $update_ref->bind_param("ii", $referrer['id'], $user_id);
                    $update_ref->execute();
                    $update_ref->close();
                    $toast_message = "Referrer set successfully.";
                    $toast_type = "success";
                } else {
                    $toast_message = "Invalid referral code.";
                    $toast_type = "error";
                }
            } else {
                $toast_message = "You have already been referred. Cannot change.";
                $toast_type = "warning";
            }
        }
    }

    // 3. Withdraw referral wallet to account balance
    if (isset($_POST['withdraw_referral'])) {
        if (empty($maintenance_notice)) {
            $wallet_stmt = $conn->prepare("SELECT referral_wallet FROM users WHERE id=?");
            $wallet_stmt->bind_param("i", $user_id);
            $wallet_stmt->execute();
            $wallet_amount = floatval($wallet_stmt->get_result()->fetch_assoc()['referral_wallet']);
            $wallet_stmt->close();

            if ($wallet_amount >= 1500) {
                $withdraw_stmt = $conn->prepare("UPDATE users SET account_balance=account_balance+?, referral_wallet=0 WHERE id=?");
                $withdraw_stmt->bind_param("di", $wallet_amount, $user_id);
                $withdraw_stmt->execute();
                $withdraw_stmt->close();
                $toast_message = "Referral wallet withdrawn to your account balance.";
                $toast_type = "success";
            } else {
                $toast_message = "Minimum Ksh 1,500 required to withdraw from referral wallet.";
                $toast_type = "warning";
            }
        } else {
            $toast_message = "System under maintenance. Cannot withdraw referral wallet now.";
            $toast_type = "warning";
        }
    }
}

// --- FETCH USER INFO ---
$userQuery = $conn->prepare("SELECT name, username, email, phone, account_balance, invested_balance, referral_wallet, referred_by, referral_code FROM users WHERE id=?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userQuery->bind_result($name, $username, $email, $phone, $account_balance, $invested_balance, $referral_wallet, $referred_by, $referral_code);
$userQuery->fetch();
$userQuery->close();

// Ensure variables are not null
$name = $name ?: "";
$username = $username ?: "";
$email = $email ?: "";
$phone = $phone ?: "";
$account_balance = floatval($account_balance ?: 0);
$invested_balance = floatval($invested_balance ?: 0);
$referral_wallet = floatval($referral_wallet ?: 0);

// --- GENERATE UNIQUE REFERRAL CODE ---
if (!$referral_code) {
    do {
        $referral_code = strtoupper(bin2hex(random_bytes(4)));
        $check = $conn->prepare("SELECT id FROM users WHERE referral_code=?");
        $check->bind_param("s", $referral_code);
        $check->execute();
        $check_rows = $check->get_result()->num_rows;
        $check->close();
    } while ($check_rows > 0);
    
    $update_code = $conn->prepare("UPDATE users SET referral_code=? WHERE id=?");
    $update_code->bind_param("si", $referral_code, $user_id);
    $update_code->execute();
    $update_code->close();
}

// --- Generate referral link ---
$referral_link = "https://gtraders.gt.tc/register.php?ref=" . $referral_code;

// --- FETCH RECENT ACTIVITIES ---
$activities = [];
$activityQuery = $conn->prepare("
    SELECT id, 'Deposit' AS type, amount, status, created_at FROM deposits WHERE user_id=?
    UNION ALL
    SELECT id, 'Withdrawal' AS type, amount, status, created_at FROM withdrawals WHERE user_id=?
    UNION ALL
    SELECT id, 'Investment' AS type, net_amount AS amount, status, created_at FROM investments WHERE user_id=?
    ORDER BY created_at DESC
    LIMIT 50
");
$activityQuery->bind_param("iii", $user_id, $user_id, $user_id);
$activityQuery->execute();
$result = $activityQuery->get_result();
$activities = $result->fetch_all(MYSQLI_ASSOC);
$activityQuery->close();

// --- FETCH REFERRAL BONUSES ---
$bonuses_q = $conn->prepare("
    SELECT b.*, u.username AS referred_user
    FROM referral_bonus b
    JOIN users u ON b.referred_user_id = u.id
    WHERE b.referrer_id = ?
    ORDER BY b.created_at DESC
");
$bonuses_q->bind_param("i", $user_id);
$bonuses_q->execute();
$bonuses_result = $bonuses_q->get_result();

// Total referral earnings
$total_bonus_stmt = $conn->prepare("SELECT SUM(bonus_amount) as total FROM referral_bonus WHERE referrer_id=?");
$total_bonus_stmt->bind_param("i", $user_id);
$total_bonus_stmt->execute();
$total_bonus = floatval($total_bonus_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$total_bonus_stmt->close();

// --- FETCH USERS REFERRED BY THIS USER ---
$referred_users_q = $conn->prepare("SELECT username, id, created_at FROM users WHERE referred_by=? ORDER BY created_at DESC");
$referred_users_q->bind_param("i", $user_id);
$referred_users_q->execute();
$referred_users_result = $referred_users_q->get_result();
$num_referred = $referred_users_result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile & Referrals | GIBAL LTD</title>
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
    max-width: 1280px; margin: 0 auto; padding: 100px 24px 60px;
}

.page-header {
    margin-bottom: 32px;
}
.page-header h1 {
    font-size: 1.8rem; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 8px;
}
.page-header p { color: var(--text-secondary); font-size: 0.95rem; }

.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

/* Cards */
.card {
    background: var(--bg-card);
    backdrop-filter: blur(12px);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    padding: 24px;
    margin-bottom: 24px;
}

.card-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border-subtle);
}
.card-title {
    font-size: 1.1rem; font-weight: 700; color: var(--text-primary);
    display: flex; align-items: center; gap: 10px;
}
.card-title i { color: var(--gold); }

/* Forms */
.form-group { margin-bottom: 16px; }
.form-label {
    display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);
    margin-bottom: 6px;
}
.form-input {
    width: 100%; padding: 12px 16px;
    background: var(--bg-elevated); border: 1px solid var(--border-medium);
    border-radius: var(--radius-sm); color: var(--text-primary);
    font-size: 0.95rem; transition: all 0.2s ease;
}
.form-input:focus {
    outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px var(--gold-glow);
}
.form-input::placeholder { color: var(--text-tertiary); }

.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 12px 20px; border-radius: var(--radius-sm); font-weight: 600;
    font-size: 0.95rem; cursor: pointer; transition: all 0.2s ease; border: none;
    text-decoration: none;
}
.btn-primary {
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-primary); width: 100%;
}
.btn-primary:hover {
    transform: translateY(-2px); box-shadow: var(--shadow-gold);
}
.btn-primary:disabled {
    background: var(--bg-elevated); color: var(--text-tertiary); cursor: not-allowed;
    transform: none; box-shadow: none;
}

/* Referral Specifics */
.referral-link-box {
    display: flex; align-items: center; gap: 12px;
    background: var(--bg-elevated); border: 1px solid var(--border-medium);
    border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 20px;
}
.referral-link-text {
    flex: 1; font-size: 0.9rem; color: var(--text-primary);
    word-break: break-all; font-family: monospace;
}
.btn-copy {
    padding: 8px 16px; background: var(--bg-tertiary); border: 1px solid var(--border-medium);
    border-radius: var(--radius-sm); color: var(--gold); font-weight: 600;
    font-size: 0.85rem; cursor: pointer; transition: all 0.2s; white-space: nowrap;
}
.btn-copy:hover { background: var(--gold); color: var(--bg-primary); border-color: var(--gold); }

.stat-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;
}
.stat-box {
    background: var(--bg-elevated); border-radius: var(--radius-md); padding: 16px; text-align: center;
}
.stat-label { font-size: 0.75rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
.stat-value { font-size: 1.3rem; font-weight: 700; color: var(--text-primary); }
.stat-value.gold { color: var(--gold); }
.stat-value.green { color: var(--success); }

/* Tables */
.table-container {
    border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-subtle);
}
.table-scroll { max-height: 300px; overflow-y: auto; }
.table-scroll::-webkit-scrollbar { width: 6px; }
.table-scroll::-webkit-scrollbar-thumb { background: var(--border-medium); border-radius: 3px; }

table { width: 100%; border-collapse: collapse; min-width: 500px; }
thead { position: sticky; top: 0; z-index: 5; background: var(--bg-secondary); }
th {
    padding: 12px 16px; text-align: left; font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-tertiary);
    border-bottom: 1px solid var(--border-subtle);
}
td {
    padding: 12px 16px; font-size: 0.88rem; color: var(--text-primary);
    border-bottom: 1px solid var(--border-subtle);
}
tbody tr:hover { background: var(--bg-elevated); }

/* Badges */
.badge {
    display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px;
    border-radius: 50px; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;
}
.badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.badge-approved, .badge-completed { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border); }
.badge-rejected, .badge-failed { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger-border); }
.badge-pending, .badge-processing { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning-border); }
.badge-active { background: var(--info-bg); color: var(--info); border: 1px solid var(--info-border); }

/* Maintenance Alert */
.maintenance-alert {
    background: var(--danger-bg); border: 1px solid var(--danger-border);
    border-left: 4px solid var(--danger); padding: 16px 20px; margin-bottom: 24px;
    border-radius: var(--radius-md); display: flex; align-items: center; gap: 12px; color: var(--text-primary);
}
.maintenance-alert i { color: var(--danger); font-size: 1.2rem; }

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
@media (max-width: 968px) {
    .profile-grid { grid-template-columns: 1fr; }
    .footer-content { grid-template-columns: 1fr; gap: 30px; }
}
@media (max-width: 768px) {
    .navbar { padding: 0 16px; height: 64px; }
    .nav-links { display: none; }
    .user-name { display: none; }
    .container { padding: 84px 16px 40px; }
    .stat-row { grid-template-columns: 1fr; }
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
        <a href="profile.php" class="nav-link active"><i class="fas fa-user-circle"></i> Profile</a>
    </div>
    <div class="user-menu">
        <div class="user-trigger" onclick="toggleUserMenu()">
            <div class="user-avatar"><?php echo strtoupper(substr($username ?: $name, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username ?: $name); ?></span>
            <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: var(--text-tertiary);"></i>
        </div>
        <div class="user-dropdown" id="userDropdown">
            <div class="dropdown-header">
                <div class="name"><?php echo htmlspecialchars($username ?: $name); ?></div>
                <div class="email">Member ID: #<?php echo $user_id; ?></div>
            </div>
            <a href="dashboard.php" class="dropdown-item"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="deposit.php" class="dropdown-item"><i class="fas fa-wallet"></i> Deposit Funds</a>
            <a href="withdraw.php" class="dropdown-item"><i class="fas fa-money-check-alt"></i> Withdraw</a>
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
        <h1>Profile & Referrals</h1>
        <p>Manage your account details, track your referral earnings, and view your activity history.</p>
    </div>

    <?php if (!empty($maintenance_notice)): ?>
    <div class="maintenance-alert">
        <i class="fas fa-exclamation-triangle"></i>
        <div><strong>System Maintenance:</strong> <?php echo htmlspecialchars($maintenance_notice); ?></div>
    </div>
    <?php endif; ?>

    <div class="profile-grid">
        
        <!-- LEFT COLUMN: Profile & Activities -->
        <div class="col-left">
            
            <!-- Profile Form -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-user-edit"></i> Account Details</div>
                </div>
                <form method="post" action="">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-input" value="<?=htmlspecialchars($name)?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" value="<?=htmlspecialchars($username)?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" value="<?=htmlspecialchars($email)?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-input" value="<?=htmlspecialchars($phone)?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary" <?php if(!empty($maintenance_notice)) echo 'disabled'; ?>>
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>

            <!-- Recent Activities -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-history"></i> Recent Activities</div>
                </div>
                <?php if (empty($activities)): ?>
                    <div style="text-align: center; padding: 30px; color: var(--text-tertiary);">
                        <i class="fas fa-receipt" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>No recent activities found.</p>
                    </div>
                <?php else: ?>
                <div class="table-container">
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr><th>Type</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $act): 
                                    $status_class = 'badge-' . strtolower($act['status']);
                                    $date_formatted = date('M d, Y', strtotime($act['created_at']));
                                ?>
                                <tr>
                                    <td><strong><?=htmlspecialchars($act['type'])?></strong></td>
                                    <td>Ksh <?=number_format($act['amount'], 2)?></td>
                                    <td><span class="badge <?=$status_class?>"><?=ucfirst($act['status'])?></span></td>
                                    <td style="color: var(--text-secondary); font-size: 0.85rem;"><?=$date_formatted?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- RIGHT COLUMN: Referral Program -->
        <div class="col-right">
            
            <!-- Referral Overview -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-users"></i> Referral Program</div>
                </div>

                <div class="stat-row">
                    <div class="stat-box">
                        <div class="stat-label">Total Earnings</div>
                        <div class="stat-value gold">Ksh <?=number_format($total_bonus, 2)?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Wallet Balance</div>
                        <div class="stat-value green">Ksh <?=number_format($referral_wallet, 2)?></div>
                    </div>
                </div>

                <label class="form-label">Your Referral Link</label>
                <div class="referral-link-box">
                    <span class="referral-link-text" id="referralLink"><?=htmlspecialchars($referral_link)?></span>
                    <button type="button" class="btn-copy" onclick="copyReferral()">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>

                <?php if (!$referred_by): ?>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-subtle);">
                    <label class="form-label">Have a referrer? Enter their code below:</label>
                    <form method="post" style="display: flex; gap: 10px;">
                        <input type="text" name="referred_by_code" class="form-input" placeholder="e.g., A1B2C3D4" required style="text-transform: uppercase;">
                        <button type="submit" class="btn btn-primary" style="width: auto; padding: 12px 24px;" <?php if(!empty($maintenance_notice)) echo 'disabled'; ?>>
                            Set
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div style="margin-top: 20px; padding: 12px; background: var(--info-bg); border: 1px solid var(--info-border); border-radius: var(--radius-sm); color: var(--info); font-size: 0.9rem; text-align: center;">
                    <i class="fas fa-check-circle"></i> You were referred by User ID: <strong><?=$referred_by?></strong>
                </div>
                <?php endif; ?>

                <form method="post" style="margin-top: 20px;">
                    <button type="submit" name="withdraw_referral" class="btn btn-primary" 
                        <?php if(!empty($maintenance_notice) || $referral_wallet < 1500) echo 'disabled'; ?>
                        title="<?= $referral_wallet < 1500 ? 'Minimum Ksh 1,500 required' : '' ?>">
                        <i class="fas fa-wallet"></i> Withdraw Referral Wallet to Balance
                    </button>
                    <?php if ($referral_wallet < 1500 && empty($maintenance_notice)): ?>
                        <p style="text-align: center; font-size: 0.8rem; color: var(--text-tertiary); margin-top: 8px;">
                            <i class="fas fa-info-circle"></i> Minimum Ksh 1,500 required to withdraw
                        </p>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Referred Users -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-user-friends"></i> Users You Referred (<?=$num_referred?>)</div>
                </div>
                <?php if ($num_referred > 0): ?>
                <div class="table-container">
                    <div class="table-scroll">
                        <table>
                            <thead><tr><th>Username</th><th>ID</th><th>Joined</th></tr></thead>
                            <tbody>
                                <?php while ($ru = $referred_users_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?=htmlspecialchars($ru['username'])?></td>
                                    <td>#<?=$ru['id']?></td>
                                    <td style="color: var(--text-secondary); font-size: 0.85rem;"><?=date('M d, Y', strtotime($ru['created_at']))?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 20px; color: var(--text-tertiary);">
                    <p>No users referred yet. Share your link to start earning!</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Bonus History -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-gift"></i> Referral Bonus History</div>
                </div>
                <?php if ($bonuses_result->num_rows > 0): ?>
                <div class="table-container">
                    <div class="table-scroll">
                        <table>
                            <thead><tr><th>User</th><th>Investment</th><th>Bonus</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php while ($b = $bonuses_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?=htmlspecialchars($b['referred_user'])?></td>
                                    <td>#<?=$b['investment_id']?></td>
                                    <td class="money-positive" style="color: var(--success); font-weight: 600;">+Ksh <?=number_format($b['bonus_amount'], 2)?></td>
                                    <td style="color: var(--text-secondary); font-size: 0.85rem;"><?=date('M d, Y', strtotime($b['created_at']))?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 20px; color: var(--text-tertiary);">
                    <p>No bonus history yet.</p>
                </div>
                <?php endif; ?>
            </div>

        </div>
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
                <li><a href="withdraw.php">Withdraw</a></li>
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

// Copy Referral Link
function copyReferral() {
    const copyText = document.getElementById("referralLink").textContent;
    navigator.clipboard.writeText(copyText).then(function() {
        showToast("Referral link copied to clipboard!", "success");
    }).catch(function() {
        showToast("Failed to copy link. Please copy manually.", "error");
    });
}

// Trigger Toast on Page Load if PHP set a message
<?php if (!empty($toast_message)): ?>
window.addEventListener('load', () => {
    showToast("<?php echo addslashes($toast_message); ?>", "<?php echo $toast_type; ?>");
});
<?php endif; ?>
</script>
</body>
</html>