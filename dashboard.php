<?php
include_once("db_connect.php");
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$maintenance_notice = '';
$sys = $conn->query("SELECT mode, message FROM system_status LIMIT 1");
if ($sys && $sys->num_rows === 1) {
    $sys_data = $sys->fetch_assoc();
    if ($sys_data['mode'] === 'maintenance') {
        $maintenance_notice = $sys_data['message'] ?: 'The system is currently under maintenance.';
    }
}

$user_id = $_SESSION['user_id'] ?? 1;

// === HANDLE USER CHAT MESSAGES ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_send_chat'])) {
    $msg = trim($_POST['user_chat_message']);
    if (!empty($msg)) {
        $stmt = $conn->prepare("INSERT INTO admin_chats (user_id, message, sender_type) VALUES (?, ?, 'user')");
        $stmt->bind_param("is", $user_id, $msg);
        $stmt->execute();
        header("Location: dashboard.php");
        exit();
    }
}

// === HANDLE NOTIFICATION DISMISSAL ===
if (isset($_GET['dismiss_notif'])) {
    $notif_id = (int)$_GET['dismiss_notif'];
    $stmt = $conn->prepare("INSERT IGNORE INTO user_notification_reads (user_id, notification_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $notif_id);
    $stmt->execute();
    header("Location: dashboard.php");
    exit();
}

// === FETCH LATEST UNREAD NOTIFICATION (Updated for Single/All Users) ===
$current_notif = null;
$notif_q = $conn->prepare("
    SELECT bn.* FROM broadcast_notifications bn
    LEFT JOIN user_notification_reads unr ON bn.id = unr.notification_id AND unr.user_id = ?
    WHERE unr.id IS NULL AND (bn.target_user_id IS NULL OR bn.target_user_id = ?)
    ORDER BY bn.created_at DESC LIMIT 1
");
$notif_q->bind_param("ii", $user_id, $user_id);
$notif_q->execute();
$current_notif = $notif_q->get_result()->fetch_assoc();

// === FETCH UNREAD CHAT COUNT FOR BELL ===
$unread_msgs = (int)($conn->query("SELECT COUNT(*) as c FROM admin_chats WHERE user_id=$user_id AND sender_type='admin' AND is_read=0")->fetch_assoc()['c'] ?? 0);

// === FETCH CHAT HISTORY (Oldest to Newest) ===
$chat_q = $conn->prepare("SELECT * FROM admin_chats WHERE user_id=? ORDER BY created_at ASC");
$chat_q->bind_param("i", $user_id);
$chat_q->execute();
$user_chat_history = $chat_q->get_result()->fetch_all(MYSQLI_ASSOC);

// Mark admin messages as read when page loads
$conn->query("UPDATE admin_chats SET is_read=1 WHERE user_id=$user_id AND sender_type='admin'");

$tz = new DateTimeZone('Africa/Nairobi');
$now = new DateTime('now', $tz);

// === PROCESS MATURED INVESTMENTS ===
$mature_stmt = $conn->prepare("SELECT i.id, i.user_id, i.amount, i.start_date, p.interest_rate, p.duration_hours, p.duration_days FROM investments i LEFT JOIN plans p ON i.plan_id = p.id WHERE i.status='active'");
$mature_stmt->execute();
$res_mature = $mature_stmt->get_result();
while($inv = $res_mature->fetch_assoc()){
    $duration_hours = $inv['duration_hours'] ? floatval($inv['duration_hours']) : floatval($inv['duration_days'])*24;
    $start_dt = new DateTime($inv['start_date'], $tz);
    $end_dt = clone $start_dt;
    $whole_hours = floor($duration_hours);
    $fractional_minutes = ($duration_hours - $whole_hours) * 60;
    $end_dt->modify("+{$whole_hours} hours +{$fractional_minutes} minutes");
    if($now >= $end_dt){
        $fee = round($inv['amount'] * 0.035,2);
        $net = $inv['amount'] - $fee;
        $interest = round(($net * $inv['interest_rate'])/100,2);
        $total_return = $net + $interest;
        $conn->query("UPDATE users SET account_balance = account_balance + $total_return, invested_balance = invested_balance - $net, total_expected_interest = total_expected_interest - $interest WHERE id={$inv['user_id']}");
        $conn->query("UPDATE investments SET status='matured' WHERE id={$inv['id']}");
    }
}
$mature_stmt->close();

// === FETCH USER INFO ===
$stmt = $conn->prepare("SELECT name, username, account_balance, invested_balance, total_expected_interest FROM users WHERE id=?");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$stmt->bind_result($name,$username,$account_balance,$invested_balance,$total_expected_interest);
$stmt->fetch();
$stmt->close();
$user_name = $username ?: "User";

// === TIME-BASED GREETING ===
$hour = (int)date('H');
if ($hour < 12) { $time_greeting = "Good Morning"; $greeting_icon = "☀️"; } 
elseif ($hour < 18) { $time_greeting = "Good Afternoon"; $greeting_icon = "🌤️"; } 
else { $time_greeting = "Good Evening"; $greeting_icon = "🌙"; }

// === FETCH PENDING WITHDRAWALS ===
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE user_id=? AND status='pending'");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$stmt->bind_result($pending_withdrawals);
$stmt->fetch();
$stmt->close();

$available_balance = $account_balance - $pending_withdrawals;
$total_account_balance = $account_balance + $invested_balance;

// === FETCH INVESTMENTS ===
$stmt = $conn->prepare("SELECT i.*, p.name AS plan_name, p.duration_days, p.duration_hours, p.interest_rate FROM investments i LEFT JOIN plans p ON i.plan_id=p.id WHERE i.user_id=? ORDER BY i.start_date DESC");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res = $stmt->get_result();
$investments = []; $active_plans = 0; $total_investments = 0; $total_interest = 0;
while($inv = $res->fetch_assoc()){
    $inv['fee'] = round($inv['amount'] * 0.035,2);
    $inv['net_amount'] = $inv['amount'] - $inv['fee'];
    $inv['expected_interest'] = round(($inv['net_amount'] * $inv['interest_rate'])/100,2);
    $duration_hours = $inv['duration_hours'] ? floatval($inv['duration_hours']) : floatval($inv['duration_days'])*24;
    $start_dt = new DateTime($inv['start_date'],$tz);
    $end_dt = clone $start_dt;
    $whole_hours = floor($duration_hours);
    $fractional_minutes = ($duration_hours - $whole_hours) * 60;
    $end_dt->modify("+{$whole_hours} hours +{$fractional_minutes} minutes");
    $inv['end_date'] = $end_dt;
    $inv['current_status'] = ($inv['status']==='matured' || $now >= $end_dt) ? "Matured" : "Active";
    if($inv['current_status'] === "Active") $active_plans++;
    $total_investments++;
    $total_interest += $inv['expected_interest'];
    $investments[] = $inv;
}
$stmt->close();

usort($investments, function($a, $b){
    if($a['current_status']==='Active' && $b['current_status']!=='Active') return -1;
    if($a['current_status']!=='Active' && $b['current_status']==='Active') return 1;
    return 0;
});

// === FETCH TRANSACTIONS & WITHDRAWALS ===
$stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY date DESC LIMIT 20");
$stmt->bind_param("i",$user_id); $stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

$stmt = $conn->prepare("SELECT * FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param("i",$user_id); $stmt->execute();
$withdrawals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

$planRes = $conn->query("SELECT * FROM plans ORDER BY min_amount ASC");
$plans = $planRes->fetch_all(MYSQLI_ASSOC);

// === DAILY SUMMARY (Fixes Blank Chart) ===
$summary = [];
foreach($transactions as $tx){
    $day = date('Y-m-d', strtotime($tx['date']));
    if(!isset($summary[$day])) $summary[$day] = ['investments'=>0,'deposits'=>0,'withdrawals'=>0];
    if($tx['type']=='deposit') $summary[$day]['deposits'] += floatval($tx['amount']);
    if($tx['type']=='withdraw') $summary[$day]['withdrawals'] += floatval($tx['amount']);
}
foreach($investments as $inv){
    $day = date('Y-m-d', strtotime($inv['start_date']));
    if(!isset($summary[$day])) $summary[$day] = ['investments'=>0,'deposits'=>0,'withdrawals'=>0];
    $summary[$day]['investments'] += floatval($inv['amount']);
}
ksort($summary);

$health_score = ($total_account_balance > 0) ? min(100, round(($invested_balance / $total_account_balance) * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | GIBAL LTD</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root { --bg-primary: #0a0e1a; --bg-secondary: #0f1420; --bg-tertiary: #151b2b; --bg-card: rgba(17, 24, 39, 0.65); --bg-card-hover: rgba(22, 30, 46, 0.8); --bg-elevated: rgba(30, 41, 59, 0.5); --gold: #d4af37; --gold-light: #f4d03f; --gold-dark: #b8941f; --gold-glow: rgba(212, 175, 55, 0.15); --gold-border: rgba(212, 175, 55, 0.2); --text-primary: #f1f5f9; --text-secondary: #94a3b8; --text-tertiary: #64748b; --text-muted: #475569; --success: #10b981; --success-bg: rgba(16, 185, 129, 0.12); --success-border: rgba(16, 185, 129, 0.25); --warning: #f59e0b; --warning-bg: rgba(245, 158, 11, 0.12); --warning-border: rgba(245, 158, 11, 0.25); --danger: #ef4444; --danger-bg: rgba(239, 68, 68, 0.12); --danger-border: rgba(239, 68, 68, 0.25); --info: #3b82f6; --info-bg: rgba(59, 130, 246, 0.12); --info-border: rgba(59, 130, 246, 0.25); --border-subtle: rgba(148, 163, 184, 0.08); --border-medium: rgba(148, 163, 184, 0.15); --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.2); --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.3); --shadow-lg: 0 20px 50px rgba(0, 0, 0, 0.4); --shadow-gold: 0 8px 30px rgba(212, 175, 55, 0.15); --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px; --radius-xl: 20px; }
* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; scroll-padding-top: 100px; }
body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg-primary); color: var(--text-primary); overflow-x: hidden; line-height: 1.6; font-variant-numeric: tabular-nums; background-image: radial-gradient(ellipse at top left, rgba(212, 175, 55, 0.04) 0%, transparent 50%), radial-gradient(ellipse at bottom right, rgba(59, 130, 246, 0.03) 0%, transparent 50%); min-height: 100vh; }
.money, .card p, td, .stat-value { font-variant-numeric: tabular-nums; font-feature-settings: "tnum"; }
#starCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; opacity: 0.5; pointer-events: none; }
.navbar { position: fixed; top: 0; width: 100%; background: rgba(10, 14, 26, 0.85); backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px) saturate(180%); border-bottom: 1px solid var(--border-subtle); z-index: 1000; padding: 0 24px; height: 70px; display: flex; align-items: center; justify-content: space-between; }
.nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--text-primary); }
.nav-brand-logo { width: 38px; height: 38px; background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--bg-primary); font-weight: 800; font-size: 1.1rem; box-shadow: var(--shadow-gold); }
.nav-brand-text { font-weight: 700; font-size: 1.15rem; letter-spacing: -0.02em; }
.nav-brand-text span { color: var(--gold); }
.nav-links { display: flex; align-items: center; gap: 8px; }
.nav-link { padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-weight: 500; font-size: 0.9rem; border-radius: var(--radius-sm); transition: all 0.2s ease; display: flex; align-items: center; gap: 8px; cursor: pointer; }
.nav-link:hover { color: var(--text-primary); background: var(--bg-elevated); }
.nav-link.active { color: var(--gold); background: var(--gold-glow); }
.user-menu { position: relative; margin-left: 16px; display: flex; align-items: center; gap: 16px; }
.user-trigger { display: flex; align-items: center; gap: 10px; padding: 6px 12px 6px 6px; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: 50px; cursor: pointer; transition: all 0.2s ease; }
.user-trigger:hover { background: var(--bg-card-hover); border-color: var(--gold-border); }
.user-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%); display: flex; align-items: center; justify-content: center; color: var(--bg-primary); font-weight: 700; font-size: 0.9rem; }
.user-name { font-weight: 600; font-size: 0.85rem; color: var(--text-primary); max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.user-dropdown { position: absolute; top: calc(100% + 8px); right: 0; background: var(--bg-secondary); border: 1px solid var(--border-medium); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); min-width: 280px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s ease; overflow: hidden; z-index: 1001; }
.user-dropdown.active { opacity: 1; visibility: visible; transform: translateY(0); }
.dropdown-header { padding: 16px; border-bottom: 1px solid var(--border-subtle); font-weight: 700; color: var(--text-primary); }
.dropdown-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; color: var(--text-secondary); text-decoration: none; font-size: 0.9rem; transition: all 0.15s ease; border-left: 3px solid transparent; }
.dropdown-item:hover { background: var(--bg-elevated); color: var(--text-primary); border-left-color: var(--gold); }
.dropdown-item i { width: 18px; color: var(--text-tertiary); margin-top: 3px; }
.dropdown-item:hover i { color: var(--gold); }
.dropdown-divider { height: 1px; background: var(--border-subtle); margin: 4px 0; }
.toast-container { position: fixed; top: 90px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
.toast { background: var(--bg-secondary); border: 1px solid var(--gold-border); border-left: 4px solid var(--gold); border-radius: var(--radius-md); padding: 14px 18px; box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 12px; min-width: 300px; max-width: 400px; transform: translateX(450px); opacity: 0; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); pointer-events: auto; }
.toast.show { transform: translateX(0); opacity: 1; }
.toast-icon { width: 36px; height: 36px; border-radius: 50%; background: var(--gold-glow); display: flex; align-items: center; justify-content: center; color: var(--gold); flex-shrink: 0; }
.toast-content { flex: 1; }
.toast-title { font-weight: 600; font-size: 0.9rem; color: var(--text-primary); margin-bottom: 2px; }
.toast-message { font-size: 0.8rem; color: var(--text-secondary); }
.toast-close { background: none; border: none; color: var(--text-tertiary); cursor: pointer; padding: 4px; font-size: 1rem; transition: color 0.2s; }
.toast-close:hover { color: var(--text-primary); }
.container { max-width: 1280px; margin: 0 auto; padding: 100px 24px 60px; }
.welcome-section { margin-bottom: 32px; }
.welcome-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px; margin-bottom: 24px; }
.welcome-text h1 { font-size: 2rem; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 8px; background: linear-gradient(135deg, var(--text-primary) 0%, var(--gold-light) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.welcome-text .subtitle { color: var(--text-secondary); font-size: 0.95rem; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.welcome-meta { display: flex; align-items: center; gap: 16px; font-size: 0.85rem; color: var(--text-tertiary); }
.welcome-meta-item { display: flex; align-items: center; gap: 6px; }
.welcome-meta-item i { color: var(--gold); font-size: 0.9rem; }
.security-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: var(--success-bg); border: 1px solid var(--success-border); border-radius: 50px; color: var(--success); font-size: 0.75rem; font-weight: 600; }
.security-badge i { font-size: 0.7rem; }
.quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 32px; }
.action-btn { display: flex; align-items: center; gap: 12px; padding: 16px 20px; background: var(--bg-card); backdrop-filter: blur(12px); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
.action-btn::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, transparent 0%, rgba(212, 175, 55, 0.08) 100%); opacity: 0; transition: opacity 0.3s; }
.action-btn:hover { transform: translateY(-2px); border-color: var(--gold-border); box-shadow: var(--shadow-gold); }
.action-btn:hover::before { opacity: 1; }
.action-btn.primary { background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%); color: var(--bg-primary); border: none; }
.action-btn.primary:hover { box-shadow: var(--shadow-gold); transform: translateY(-2px); }
.action-btn.primary::before { background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%); }
.action-btn i { font-size: 1.1rem; position: relative; z-index: 1; }
.action-btn span { position: relative; z-index: 1; }
.maintenance-alert { background: var(--danger-bg); border: 1px solid var(--danger-border); border-left: 4px solid var(--danger); padding: 16px 20px; margin-bottom: 24px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 12px; color: var(--text-primary); }
.maintenance-alert i { color: var(--danger); font-size: 1.2rem; }
.maintenance-alert strong { color: var(--danger); }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 32px; }
.stat-card { background: var(--bg-card); backdrop-filter: blur(12px); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 22px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 2px; background: linear-gradient(90deg, transparent, var(--gold), transparent); opacity: 0.4; }
.stat-card:hover { transform: translateY(-3px); border-color: var(--gold-border); box-shadow: var(--shadow-md); }
.stat-card:hover::before { opacity: 1; }
.stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.stat-label { font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-tertiary); }
.stat-icon { width: 40px; height: 40px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.stat-icon.gold { background: var(--gold-glow); color: var(--gold); }
.stat-icon.green { background: var(--success-bg); color: var(--success); }
.stat-icon.blue { background: var(--info-bg); color: var(--info); }
.stat-icon.orange { background: var(--warning-bg); color: var(--warning); }
.stat-icon.red { background: var(--danger-bg); color: var(--danger); }
.stat-value { font-size: 1.7rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; letter-spacing: -0.02em; }
.stat-change { font-size: 0.8rem; color: var(--text-tertiary); display: flex; align-items: center; gap: 4px; }
.stat-change.positive { color: var(--success); }
.health-card { background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(59, 130, 246, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 32px; display: grid; grid-template-columns: 1fr auto; gap: 24px; align-items: center; }
.health-info h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 6px; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
.health-info p { color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 16px; }
.health-bar { height: 8px; background: rgba(255, 255, 255, 0.05); border-radius: 50px; overflow: hidden; margin-bottom: 8px; }
.health-bar-fill { height: 100%; background: linear-gradient(90deg, var(--success) 0%, var(--info) 100%); border-radius: 50px; transition: width 1s cubic-bezier(0.4, 0, 0.2, 1); }
.health-bar-label { display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-tertiary); }
.health-score { width: 90px; height: 90px; border-radius: 50%; background: conic-gradient(var(--success) calc(var(--score) * 1%), rgba(255,255,255,0.05) 0); display: flex; align-items: center; justify-content: center; position: relative; }
.health-score::before { content: ''; position: absolute; inset: 6px; background: var(--bg-secondary); border-radius: 50%; }
.health-score-value { position: relative; font-size: 1.4rem; font-weight: 700; color: var(--success); }
.info-box { background: var(--info-bg); border: 1px solid var(--info-border); border-left: 4px solid var(--info); padding: 18px 22px; margin-bottom: 32px; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; line-height: 1.7; }
.info-box strong { color: var(--info); display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-size: 0.95rem; }
.info-box ul { margin: 8px 0 0 0; padding-left: 20px; }
.info-box li { margin-bottom: 4px; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin: 48px 0 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border-subtle); }
.section-title { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 10px; letter-spacing: -0.01em; }
.section-title::before { content: ''; width: 4px; height: 20px; background: linear-gradient(180deg, var(--gold) 0%, var(--gold-dark) 100%); border-radius: 2px; }
.plans-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; margin-bottom: 32px; }
.plan-card { background: var(--bg-card); backdrop-filter: blur(12px); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; display: flex; flex-direction: column; }
.plan-card:hover { transform: translateY(-4px); border-color: var(--gold-border); box-shadow: var(--shadow-md); }
.plan-badge { position: absolute; top: 16px; right: 16px; padding: 4px 10px; background: var(--gold-glow); border: 1px solid var(--gold-border); border-radius: 50px; color: var(--gold); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
.plan-name { font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-bottom: 16px; padding-right: 70px; }
.plan-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; padding: 16px; background: var(--bg-elevated); border-radius: var(--radius-md); }
.plan-stat { display: flex; flex-direction: column; gap: 2px; }
.plan-stat-label { font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
.plan-stat-value { font-size: 0.95rem; font-weight: 700; color: var(--text-primary); }
.plan-stat-value.interest { color: var(--success); }
.plan-range { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 16px; display: flex; align-items: center; gap: 6px; }
.plan-range i { color: var(--gold); }
.plan-btn { width: 100%; padding: 12px; background: var(--bg-elevated); border: 1px solid var(--border-medium); border-radius: var(--radius-sm); color: var(--text-primary); font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; margin-top: auto; }
.plan-btn:hover { background: var(--gold); border-color: var(--gold); color: var(--bg-primary); box-shadow: var(--shadow-gold); }
.plan-btn:disabled { background: var(--bg-elevated); color: var(--text-tertiary); cursor: not-allowed; border-color: var(--border-subtle); }
.table-container { background: var(--bg-card); backdrop-filter: blur(12px); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; margin-bottom: 32px; }
.table-scroll { overflow-x: auto; max-height: 500px; }
.table-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
.table-scroll::-webkit-scrollbar-track { background: var(--bg-secondary); }
.table-scroll::-webkit-scrollbar-thumb { background: var(--border-medium); border-radius: 4px; }
table { width: 100%; border-collapse: collapse; min-width: 700px; }
thead { position: sticky; top: 0; z-index: 5; background: var(--bg-secondary); }
th { padding: 14px 18px; text-align: left; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-tertiary); border-bottom: 1px solid var(--border-subtle); white-space: nowrap; }
td { padding: 16px 18px; font-size: 0.88rem; color: var(--text-primary); border-bottom: 1px solid var(--border-subtle); }
tbody tr { transition: background 0.15s ease; }
tbody tr:hover { background: var(--bg-elevated); }
tbody tr:last-child td { border-bottom: none; }
.badge { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 50px; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
.badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.badge-active { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border); }
.badge-matured { background: var(--info-bg); color: var(--info); border: 1px solid var(--info-border); }
.badge-deposit { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border); }
.badge-withdraw { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger-border); }
.badge-pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning-border); }
.badge-approved { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border); }
.badge-rejected { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger-border); }
.time-left { font-weight: 700; color: var(--gold); font-variant-numeric: tabular-nums; }
.money-positive { color: var(--success); font-weight: 600; }
.empty-state { padding: 60px 20px; text-align: center; }
.empty-state-icon { width: 70px; height: 70px; margin: 0 auto 20px; background: var(--bg-elevated); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: var(--text-tertiary); }
.empty-state h4 { color: var(--text-primary); font-size: 1.05rem; margin-bottom: 8px; font-weight: 600; }
.empty-state p { color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 20px; max-width: 400px; margin-left: auto; margin-right: auto; }
.empty-state-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: var(--gold); color: var(--bg-primary); border-radius: var(--radius-sm); text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.2s ease; }
.empty-state-btn:hover { background: var(--gold-light); transform: translateY(-1px); box-shadow: var(--shadow-gold); }
.chart-container { background: var(--bg-card); backdrop-filter: blur(12px); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 32px; position: relative; height: 380px; }
.footer { background: var(--bg-secondary); border-top: 1px solid var(--border-subtle); padding: 40px 24px 20px; margin-top: 60px; }
.footer-content { max-width: 1280px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 40px; margin-bottom: 30px; }
.footer-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.footer-brand-text { font-weight: 700; font-size: 1.1rem; color: var(--text-primary); }
.footer-brand-text span { color: var(--gold); }
.footer-about { color: var(--text-secondary); font-size: 0.88rem; line-height: 1.6; margin-bottom: 16px; }
.footer-contact { display: flex; flex-direction: column; gap: 8px; font-size: 0.85rem; color: var(--text-secondary); }
.footer-contact a { color: var(--gold); text-decoration: none; transition: opacity 0.2s; }
.footer-contact a:hover { opacity: 0.8; }
.footer-heading { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-primary); margin-bottom: 16px; }
.footer-links { list-style: none; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.footer-links a { color: var(--text-secondary); text-decoration: none; font-size: 0.88rem; transition: color 0.2s; }
.footer-links a:hover { color: var(--gold); }
.footer-bottom { max-width: 1280px; margin: 0 auto; padding-top: 20px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 0.82rem; color: var(--text-tertiary); }
.footer-bottom a { color: var(--text-secondary); text-decoration: none; }
.footer-bottom a:hover { color: var(--gold); }
.modal-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(8px); z-index: 99998; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
.modal-overlay.active { opacity: 1; visibility: visible; }
.modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.9); background: var(--bg-secondary); border: 1px solid var(--gold-border); border-radius: var(--radius-xl); padding: 0; width: 90%; max-width: 440px; z-index: 99999; opacity: 0; visibility: hidden; transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); overflow: hidden; box-shadow: var(--shadow-lg), 0 0 60px rgba(212, 175, 55, 0.15); }
.modal.active { opacity: 1; visibility: visible; transform: translate(-50%, -50%) scale(1); }
.modal-header { background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%); padding: 30px 24px; text-align: center; color: var(--bg-primary); position: relative; }
.modal-header::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 60%); animation: shimmer 3s ease-in-out infinite; }
@keyframes shimmer { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(20%, 20%); } }
.modal-icon { width: 70px; height: 70px; margin: 0 auto 16px; background: rgba(0, 0, 0, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; position: relative; z-index: 1; }
.modal-title { font-size: 1.4rem; font-weight: 800; margin-bottom: 6px; position: relative; z-index: 1; }
.modal-subtitle { font-size: 0.9rem; opacity: 0.9; position: relative; z-index: 1; }
.modal-body { padding: 28px 24px; text-align: center; }
.modal-body p { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6; margin-bottom: 24px; }
.modal-features { display: flex; flex-direction: column; gap: 10px; margin-bottom: 24px; text-align: left; }
.modal-feature { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--bg-elevated); border-radius: var(--radius-sm); font-size: 0.88rem; color: var(--text-primary); }
.modal-feature i { color: var(--gold); font-size: 1rem; }
.modal-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%); border: none; border-radius: var(--radius-sm); color: var(--bg-primary); font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: all 0.2s ease; margin-bottom: 10px; }
.modal-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-gold); }
.modal-btn-secondary { width: 100%; padding: 12px; background: transparent; border: 1px solid var(--border-medium); border-radius: var(--radius-sm); color: var(--text-secondary); font-weight: 600; font-size: 0.88rem; cursor: pointer; transition: all 0.2s ease; }
.modal-btn-secondary:hover { background: var(--bg-elevated); color: var(--text-primary); }

/* NOTIFICATION BAR & FLOATING CHAT */
.notif-bar { position: sticky; top: 70px; z-index: 900; padding: 14px 24px; display: flex; align-items: center; gap: 14px; border-bottom: 1px solid; backdrop-filter: blur(10px); animation: slideDown 0.4s ease; }
@keyframes slideDown { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.notif-bar.info { background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.4); color: #93c5fd; }
.notif-bar.success { background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.4); color: #6ee7b7; }
.notif-bar.warning { background: rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.4); color: #fcd34d; }
.notif-bar.urgent { background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.4); color: #fca5a5; }
.notif-bar .notif-icon { font-size: 1.3rem; }
.notif-bar .notif-content { flex: 1; }
.notif-bar .notif-title { font-weight: 700; font-size: 0.95rem; color: var(--text-primary); margin-bottom: 2px; }
.notif-bar .notif-message { font-size: 0.85rem; opacity: 0.9; }
.notif-bar .notif-dismiss { background: transparent; border: 1px solid currentColor; color: inherit; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: 0.2s; }
.notif-bar .notif-dismiss:hover { background: rgba(255,255,255,0.1); }

.chat-float-btn { position: fixed; bottom: 100px; right: 30px; z-index: 9998; width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #d4af37, #b8941f); color: #0a0e1a; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 26px; box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4); transition: all 0.3s ease; animation: pulse-gold-chat 2.5s infinite; }
@keyframes pulse-gold-chat { 0% { box-shadow: 0 0 0 0 rgba(212, 175, 55, 0.6); } 70% { box-shadow: 0 0 0 15px rgba(212, 175, 55, 0); } 100% { box-shadow: 0 0 0 0 rgba(212, 175, 55, 0); } }
.chat-float-btn:hover { transform: scale(1.1); }
.chat-float-btn .chat-badge { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; border-radius: 50%; width: 24px; height: 24px; font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; justify-content: center; border: 2px solid #0a0e1a; }
.chat-panel { position: fixed; bottom: 170px; right: 30px; z-index: 9998; width: 360px; max-width: calc(100vw - 40px); height: 500px; max-height: calc(100vh - 200px); background: rgba(17, 24, 39, 0.98); backdrop-filter: blur(20px); border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 16px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6); display: none; flex-direction: column; overflow: hidden; animation: chatSlideUp 0.3s ease; }
.chat-panel.open { display: flex; }
@keyframes chatSlideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.chat-panel-header { padding: 16px 20px; background: linear-gradient(135deg, rgba(212, 175, 55, 0.15), rgba(184, 148, 31, 0.05)); border-bottom: 1px solid rgba(212, 175, 55, 0.2); display: flex; align-items: center; justify-content: space-between; }
.chat-panel-header h4 { color: #d4af37; margin: 0; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
.chat-panel-header .online-dot { width: 8px; height: 8px; background: #10b981; border-radius: 50%; animation: pulse 2s infinite; }
.chat-panel-close { background: transparent; border: none; color: #94a3b8; font-size: 1.2rem; cursor: pointer; }
.chat-panel-close:hover { color: #f1f5f9; }
.chat-panel-body { flex: 1; padding: 16px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
.chat-panel-body::-webkit-scrollbar { width: 6px; }
.chat-panel-body::-webkit-scrollbar-thumb { background: rgba(212, 175, 55, 0.3); border-radius: 3px; }
.chat-bubble { max-width: 80%; padding: 10px 14px; border-radius: 12px; font-size: 0.88rem; line-height: 1.4; word-wrap: break-word; }
.chat-bubble.admin { align-self: flex-start; background: rgba(6, 182, 212, 0.15); color: #22d3ee; border: 1px solid rgba(6, 182, 212, 0.3); border-bottom-left-radius: 2px; }
.chat-bubble.user { align-self: flex-end; background: rgba(212, 175, 55, 0.15); color: #f4d03f; border: 1px solid rgba(212, 175, 55, 0.3); border-bottom-right-radius: 2px; }
.chat-bubble .chat-time { font-size: 0.65rem; opacity: 0.6; margin-top: 4px; text-align: right; }
.chat-panel-footer { padding: 12px; border-top: 1px solid rgba(148, 163, 184, 0.1); display: flex; gap: 8px; }
.chat-panel-footer input { flex: 1; padding: 10px 12px; background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(148, 163, 184, 0.15); border-radius: 8px; color: #f1f5f9; font-size: 0.9rem; }
.chat-panel-footer input:focus { outline: none; border-color: #d4af37; }
.chat-panel-footer button { padding: 10px 16px; background: linear-gradient(135deg, #d4af37, #b8941f); color: #0a0e1a; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; }
.chat-empty { text-align: center; color: #64748b; padding: 40px 20px; font-size: 0.9rem; }

@media (max-width: 968px) { .footer-content { grid-template-columns: 1fr; gap: 30px; } .health-card { grid-template-columns: 1fr; } .health-score { margin: 0 auto; } }
@media (max-width: 768px) {
    .navbar { padding: 0 16px; height: 64px; } .nav-links { display: none; } .user-name { display: none; } .container { padding: 84px 16px 40px; }
    .welcome-text h1 { font-size: 1.5rem; } .welcome-header { flex-direction: column; align-items: flex-start; } .stats-grid { grid-template-columns: 1fr; }
    .quick-actions { grid-template-columns: 1fr; } .plans-grid { grid-template-columns: 1fr; } .section-header { flex-direction: column; align-items: flex-start; gap: 8px; }
    .toast-container { right: 12px; left: 12px; } .toast { min-width: auto; max-width: 100%; } .footer-bottom { flex-direction: column; text-align: center; }
    .chat-float-btn { bottom: 90px; right: 20px; width: 55px; height: 55px; font-size: 22px; } .chat-panel { bottom: 155px; right: 20px; width: calc(100vw - 40px); }
}
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards; opacity: 0; }
.delay-1 { animation-delay: 0.1s; } .delay-2 { animation-delay: 0.2s; } .delay-3 { animation-delay: 0.3s; } .delay-4 { animation-delay: 0.4s; }
</style>
<link rel="icon" type="image/png" href="favicon.png">
</head>
<body>
<canvas id="starCanvas"></canvas>

<nav class="navbar">
    <a href="index.php" class="nav-brand">
        <div class="nav-brand-logo">G</div>
        <div class="nav-brand-text">GIBAL <span>LTD</span></div>
    </a>
    <div class="nav-links">
        <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
        <a href="dashboard.php" class="nav-link active"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i> Profile</a>
    </div>
    <div class="user-menu">
        <!-- NOTIFICATION BELL -->
        <div class="nav-link" style="position: relative;" onclick="toggleNotifDropdown()">
            <i class="fas fa-bell"></i>
            <?php if ($unread_msgs > 0): ?>
                <span class="chat-badge" style="position: absolute; top: 2px; right: 2px; width: 18px; height: 18px; font-size: 0.65rem;"><?=$unread_msgs?></span>
            <?php endif; ?>
        </div>
        
        <div class="user-trigger" onclick="toggleUserMenu()">
            <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
            <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: var(--text-tertiary);"></i>
        </div>
        
        <!-- USER DROPDOWN -->
        <div class="user-dropdown" id="userDropdown">
            <div class="dropdown-header">
                <div class="name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="email">Member since <?php echo date('Y'); ?></div>
            </div>
            <a href="profile.php" class="dropdown-item"><i class="fas fa-user"></i> My Profile</a>
            <a href="deposit.php" class="dropdown-item"><i class="fas fa-wallet"></i> Deposit Funds</a>
            <a href="withdraw.php" class="dropdown-item"><i class="fas fa-money-check-alt"></i> Withdraw</a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" class="dropdown-item" style="color: var(--danger);"><i class="fas fa-sign-out-alt" style="color: var(--danger);"></i> Sign Out</a>
        </div>

        <!-- NOTIFICATION DROPDOWN -->
        <div class="user-dropdown" id="notifDropdown" style="right: 60px;">
            <div class="dropdown-header">Notifications</div>
            <?php if ($unread_msgs > 0): ?>
                <a href="javascript:void(0)" onclick="toggleChatPanel(); toggleNotifDropdown();" class="dropdown-item">
                    <i class="fas fa-comments" style="color: var(--info);"></i> 
                    <div>
                        <div style="font-weight: 600; color: var(--text-primary);">New message from Support</div>
                        <div style="font-size: 0.75rem; color: var(--text-tertiary);">Click to open chat</div>
                    </div>
                </a>
            <?php endif; ?>
            <a href="#" class="dropdown-item">
                <i class="fas fa-envelope" style="color: var(--gold);"></i>
                <div>
                    <div style="font-weight: 600; color: var(--text-primary);">Check your email</div>
                    <div style="font-size: 0.75rem; color: var(--text-tertiary);">Important update from GIBAL LTD</div>
                </div>
            </a>
        </div>
    </div>
</nav>

<?php if ($current_notif): 
    $icon_map = ['info'=>'fa-info-circle', 'success'=>'fa-check-circle', 'warning'=>'fa-exclamation-triangle', 'urgent'=>'fa-exclamation-circle'];
    $icon = $icon_map[$current_notif['type']] ?? 'fa-info-circle';
?>
<div class="notif-bar <?=$current_notif['type']?>">
    <i class="fas <?=$icon?> notif-icon"></i>
    <div class="notif-content">
        <div class="notif-title"><?=htmlspecialchars($current_notif['title'])?></div>
        <div class="notif-message"><?=htmlspecialchars($current_notif['message'])?></div>
    </div>
    <a href="?dismiss_notif=<?=$current_notif['id']?>" class="notif-dismiss"><i class="fas fa-times"></i> Dismiss</a>
</div>
<?php endif; ?>

<div class="toast-container" id="toastContainer"></div>

<div class="container">
    <div class="welcome-section fade-in-up">
        <div class="welcome-header">
            <div class="welcome-text">
                <h1><?php echo $greeting_icon; ?> <?php echo $time_greeting; ?>, <?php echo htmlspecialchars($user_name); ?></h1>
                <div class="subtitle">
                    <span>Here's your investment overview for today.</span>
                    <span class="security-badge"><i class="fas fa-shield-alt"></i> SSL Secured</span>
                </div>
            </div>
            <div class="welcome-meta">
                <div class="welcome-meta-item"><i class="fas fa-clock"></i><span id="digitalClock">--:--:--</span></div>
                <div class="welcome-meta-item"><i class="fas fa-calendar"></i><span id="currentDate"><?php echo date('M d, Y'); ?></span></div>
            </div>
        </div>
    </div>

    <?php if (!empty($maintenance_notice)): ?>
    <div class="maintenance-alert fade-in-up delay-1">
        <i class="fas fa-exclamation-triangle"></i>
        <div><strong>System Maintenance:</strong> <?php echo htmlspecialchars($maintenance_notice); ?></div>
    </div>
    <?php endif; ?>

    <div class="quick-actions fade-in-up delay-1">
        <a href="deposit.php" class="action-btn primary" <?php if(!empty($maintenance_notice)) echo 'onclick="showToast(\'System under maintenance.\', \'warning\'); return false;"'; ?>>
            <i class="fas fa-plus-circle"></i><span>Deposit Funds</span>
        </a>
        <a href="withdraw.php" class="action-btn" <?php if(!empty($maintenance_notice)) echo 'onclick="showToast(\'System under maintenance.\', \'warning\'); return false;"'; ?>>
            <i class="fas fa-money-check-alt"></i><span>Withdraw</span>
        </a>
        <a href="#investments" class="action-btn"><i class="fas fa-chart-line"></i><span>My Investments</span></a>
        <a href="#plans" class="action-btn"><i class="fas fa-layer-group"></i><span>Explore Plans</span></a>
        <a href="tickets.php" class="nav-link"><i class="fas fa-ticket-alt"></i> My Tickets</a>
    </div>

    <div class="stats-grid fade-in-up delay-2">
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Total Balance</span><div class="stat-icon gold"><i class="fas fa-coins"></i></div></div>
            <div class="stat-value">Ksh <?php echo number_format($total_account_balance, 2); ?></div>
            <div class="stat-change positive"><i class="fas fa-arrow-up"></i> Combined portfolio</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Available Balance</span><div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div></div>
            <div class="stat-value">Ksh <?php echo number_format($available_balance, 2); ?></div>
            <div class="stat-change"><i class="fas fa-info-circle"></i> Ready to invest</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Invested Balance</span><div class="stat-icon blue"><i class="fas fa-hand-holding-usd"></i></div></div>
            <div class="stat-value">Ksh <?php echo number_format($invested_balance, 2); ?></div>
            <div class="stat-change positive"><i class="fas fa-chart-pie"></i> <?php echo $active_plans; ?> active plan<?php echo $active_plans !== 1 ? 's' : ''; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Expected Interest</span><div class="stat-icon orange"><i class="fas fa-chart-line"></i></div></div>
            <div class="stat-value">Ksh <?php echo number_format($total_expected_interest, 2); ?></div>
            <div class="stat-change positive"><i class="fas fa-trending-up"></i> Projected earnings</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Pending Withdrawals</span><div class="stat-icon red"><i class="fas fa-clock"></i></div></div>
            <div class="stat-value">Ksh <?php echo number_format($pending_withdrawals, 2); ?></div>
            <div class="stat-change"><i class="fas fa-hourglass-half"></i> Awaiting processing</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Total Investments</span><div class="stat-icon gold"><i class="fas fa-piggy-bank"></i></div></div>
            <div class="stat-value"><?php echo $total_investments; ?></div>
            <div class="stat-change"><i class="fas fa-star"></i> All time</div>
        </div>
    </div>

    <?php if ($total_account_balance > 0): ?>
    <div class="health-card fade-in-up delay-3">
        <div class="health-info">
            <h3><i class="fas fa-heartbeat" style="color: var(--success);"></i> Portfolio Health</h3>
            <p>Your portfolio allocation is optimized. Keep diversifying to maximize returns.</p>
            <div class="health-bar"><div class="health-bar-fill" style="width: <?php echo $health_score; ?>%;"></div></div>
            <div class="health-bar-label"><span>Invested: <?php echo $health_score; ?>%</span><span>Available: <?php echo 100 - $health_score; ?>%</span></div>
        </div>
        <div class="health-score" style="--score: <?php echo $health_score; ?>;"><div class="health-score-value"><?php echo $health_score; ?>%</div></div>
    </div>
    <?php endif; ?>

    <div class="info-box fade-in-up delay-3">
        <strong><i class="fas fa-info-circle"></i> Withdrawal Rules & Processing</strong>
        <ul>
            <li>Withdrawals are processed within operational hours (Mon – Fri, 9:00 AM – 5:00 PM EAT)</li>
            <li>Delays may occur during verification or system maintenance</li>
            <li>Pending withdrawals are temporarily deducted from your available balance</li>
        </ul>
    </div>

    <div class="section-header fade-in-up"><h2 class="section-title" id="plans">Available Investment Plans</h2></div>
    <div class="plans-grid">
        <?php foreach($plans as $plan): ?>
        <div class="plan-card fade-in-up">
            <div class="plan-badge">Popular</div>
            <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
            <div class="plan-stats">
                <div class="plan-stat"><span class="plan-stat-label">Duration</span><span class="plan-stat-value"><?php echo $plan['duration_hours']<24 ? $plan['duration_hours'].' hrs' : ($plan['duration_hours']/24).' days'; ?></span></div>
                <div class="plan-stat"><span class="plan-stat-label">Interest</span><span class="plan-stat-value interest"><?php echo $plan['interest_rate']; ?>%</span></div>
            </div>
            <div class="plan-range"><i class="fas fa-coins"></i><span>Ksh <?php echo number_format($plan['min_amount']); ?> - <?php echo number_format($plan['max_amount']); ?></span></div>
            <button class="plan-btn" onclick="<?php if(!empty($maintenance_notice)) { echo "showToast('System under maintenance.', 'warning'); return false;"; } else { echo "window.location.href='invest.php?plan_id={$plan['id']}'"; } ?>" <?php if(!empty($maintenance_notice)) echo 'disabled'; ?>>
                <i class="fas fa-arrow-right"></i> Invest Now
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section-header fade-in-up"><h2 class="section-title" id="investments">Your Investments</h2></div>
    <div class="table-container fade-in-up">
        <?php if(empty($investments)): ?>
            <div class="empty-state"><div class="empty-state-icon"><i class="fas fa-folder-open"></i></div><h4>No investments yet</h4><p>Start building your portfolio by choosing from our premium investment plans.</p><a href="#plans" class="empty-state-btn"><i class="fas fa-rocket"></i> Browse Plans</a></div>
        <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Plan</th><th>Amount</th><th>Fee</th><th>Net</th><th>Interest</th><th>Status</th><th>Start</th><th>End</th><th>Time Left</th></tr></thead>
                <tbody>
                    <?php foreach($investments as $inv): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($inv['plan_name']); ?></strong></td>
                        <td>Ksh <?php echo number_format($inv['amount'], 2); ?></td>
                        <td>Ksh <?php echo number_format($inv['fee'], 2); ?></td>
                        <td>Ksh <?php echo number_format($inv['net_amount'], 2); ?></td>
                        <td class="money-positive">+Ksh <?php echo number_format($inv['expected_interest'], 2); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($inv['current_status']); ?>"><?php echo htmlspecialchars($inv['current_status']); ?></span></td>
                        <td><?php echo htmlspecialchars($inv['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($inv['end_date']->format('Y-m-d H:i')); ?></td>
                        <td class="time-left" data-end="<?php echo $inv['end_date']->format('c'); ?>">Calculating...</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="section-header fade-in-up"><h2 class="section-title">Recent Transactions</h2></div>
    <div class="table-container fade-in-up">
        <?php if(empty($transactions)): ?>
            <div class="empty-state"><div class="empty-state-icon"><i class="fas fa-receipt"></i></div><h4>No transactions yet</h4><p>Your transaction history will appear here once you make your first deposit.</p><a href="deposit.php" class="empty-state-btn"><i class="fas fa-plus-circle"></i> Make Deposit</a></div>
        <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead><tr><th>ID</th><th>Type</th><th>Amount</th><th>Description</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach($transactions as $tx): ?>
                    <tr>
                        <td>#<?php echo $tx['id']; ?></td>
                        <td><span class="badge badge-<?php echo strtolower($tx['type']); ?>"><?php echo ucfirst($tx['type']); ?></span></td>
                        <td>Ksh <?php echo number_format($tx['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($tx['description']); ?></td>
                        <td><span class="local-time" data-utc="<?php echo $tx['date']; ?>"></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="section-header fade-in-up"><h2 class="section-title">Withdrawal Requests</h2></div>
    <div class="table-container fade-in-up">
        <?php if(empty($withdrawals)): ?>
            <div class="empty-state"><div class="empty-state-icon"><i class="fas fa-money-check-alt"></i></div><h4>No withdrawal requests</h4><p>When you request a withdrawal, it will appear here for tracking.</p></div>
        <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead><tr><th>ID</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach($withdrawals as $wd): ?>
                    <tr>
                        <td>#<?php echo $wd['id']; ?></td>
                        <td>Ksh <?php echo number_format($wd['amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($wd['status']); ?>"><?php echo ucfirst($wd['status']); ?></span></td>
                        <td><span class="local-time" data-utc="<?php echo $wd['created_at']; ?>"></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="section-header fade-in-up"><h2 class="section-title">Performance Summary</h2></div>
    <div class="chart-container fade-in-up"><canvas id="summaryChart"></canvas></div>
</div>

<footer class="footer">
    <div class="footer-content">
        <div>
            <div class="footer-brand"><div class="nav-brand-logo">G</div><div class="footer-brand-text">GIBAL <span>LTD</span></div></div>
            <p class="footer-about">GIBAL LTD is a registered investment platform operating in Kenya, providing secure and transparent investment opportunities for our clients.</p>
            <div class="footer-contact">
                <div><i class="fas fa-envelope" style="color: var(--gold); margin-right: 8px;"></i> <a href="mailto:gibal.ltd@gmail.com">gibal.ltd@gmail.com</a></div>
                <div><i class="fas fa-clock" style="color: var(--gold); margin-right: 8px;"></i> Mon – Fri, 9:00 AM – 5:00 PM (EAT)</div>
            </div>
        </div>
        <div>
            <h4 class="footer-heading">Quick Links</h4>
            <ul class="footer-links">
                <li><a href="index.php">Home</a></li><li><a href="dashboard.php">Dashboard</a></li><li><a href="deposit.php">Deposit</a></li><li><a href="withdraw.php">Withdraw</a></li><li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        <div>
            <h4 class="footer-heading">Legal</h4>
            <ul class="footer-links">
                <li><a href="terms.php">Terms & Conditions</a></li><li><a href="privacy.php">Privacy Policy</a></li><li><a href="code_of_conduct.php">Code of Conduct</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div>&copy; <?php echo date('Y'); ?> GIBAL LTD. All rights reserved.</div>
        <div><i class="fas fa-shield-alt" style="color: var(--success); margin-right: 6px;"></i> Secured with 256-bit SSL encryption</div>
    </div>
</footer>

<!-- FLOATING CHAT WIDGET -->
<button class="chat-float-btn" id="chatFloatBtn" onclick="toggleChatPanel()">
    <i class="fas fa-comments"></i>
    <?php if ($unread_msgs > 0): ?><span class="chat-badge"><?=$unread_msgs?></span><?php endif; ?>
</button>

<div class="chat-panel" id="chatPanel">
    <div class="chat-panel-header">
        <h4><span class="online-dot"></span> GIBAL Support</h4>
        <button class="chat-panel-close" onclick="toggleChatPanel()"><i class="fas fa-times"></i></button>
    </div>
    <div class="chat-panel-body" id="chatBody">
        <?php if (empty($user_chat_history)): ?>
            <div class="chat-empty">
                <i class="fas fa-headset" style="font-size: 2rem; margin-bottom: 10px; color: #d4af37;"></i>
                <p>Hi <?=htmlspecialchars($user_name)?>! 👋<br>How can we help you today?</p>
            </div>
        <?php else: ?>
            <?php foreach ($user_chat_history as $c): ?>
                <div class="chat-bubble <?=$c['sender_type']?>">
                    <?=htmlspecialchars($c['message'])?>
                    <div class="chat-time"><?=date('H:i', strtotime($c['created_at']))?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <form method="POST" class="chat-panel-footer" id="chatForm">
        <input type="text" name="user_chat_message" id="chatInput" placeholder="Type a message..." required autocomplete="off">
        <button type="submit" name="user_send_chat"><i class="fas fa-paper-plane"></i></button>
    </form>
</div>

<!-- BONUS POPUP MODAL -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal()"></div>
<div class="modal" id="bonusModal">
    <div class="modal-header">
        <div class="modal-icon"><i class="fas fa-gift"></i></div>
        <div class="modal-title">25% Deposit Bonus</div>
        <div class="modal-subtitle">Limited Time Offer</div>
    </div>
    <div class="modal-body">
        <p>Deposit today and instantly qualify for an extra 5% reward on your investment. Grow your wealth faster with GIBAL LTD!</p>
        <div class="modal-features">
            <div class="modal-feature"><i class="fas fa-check-circle"></i><span>Instant bonus credit on deposit</span></div>
            <div class="modal-feature"><i class="fas fa-check-circle"></i><span>No minimum deposit required</span></div>
            <div class="modal-feature"><i class="fas fa-check-circle"></i><span>Valid for all investment plans</span></div>
        </div>
        <button class="modal-btn" onclick="window.location.href='deposit.php'"><i class="fas fa-rocket"></i> Claim Bonus Now</button>
        <button class="modal-btn-secondary" onclick="closeModal()">Maybe Later</button>
    </div>
</div>

<script>
function toggleUserMenu() { document.getElementById('userDropdown').classList.toggle('active'); }
function toggleNotifDropdown() { document.getElementById('notifDropdown').classList.toggle('active'); document.getElementById('userDropdown').classList.remove('active'); }
document.addEventListener('click', function(e) {
    const userMenu = document.querySelector('.user-menu');
    if (!userMenu.contains(e.target)) { 
        document.getElementById('userDropdown').classList.remove('active'); 
        document.getElementById('notifDropdown').classList.remove('active'); 
    }
});

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast';
    const icons = { info: 'fa-info-circle', success: 'fa-check-circle', warning: 'fa-exclamation-triangle', error: 'fa-times-circle' };
    toast.innerHTML = `<div class="toast-icon"><i class="fas ${icons[type] || icons.info}"></i></div><div class="toast-content"><div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div><div class="toast-message">${message}</div></div><button class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 4000);
}

function closeModal() {
    document.getElementById('bonusModal').classList.remove('active');
    document.getElementById('modalOverlay').classList.remove('active');
    sessionStorage.setItem('bonusPopupClosed', 'true');
}
window.addEventListener('load', () => {
    if (!sessionStorage.getItem('bonusPopupClosed')) {
        setTimeout(() => {
            document.getElementById('bonusModal').classList.add('active');
            document.getElementById('modalOverlay').classList.add('active');
        }, 2000);
    }
});

function updateClock() {
    const now = new Date();
    document.getElementById('digitalClock').textContent = now.toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
}
setInterval(updateClock, 1000); updateClock();

document.querySelectorAll('.local-time').forEach(el => {
    const utc = new Date(el.dataset.utc);
    el.textContent = utc.toLocaleString('en-KE', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });
});

function updateTimeLeft() {
    document.querySelectorAll('.time-left').forEach(td => {
        const end = new Date(td.dataset.end);
        const diff = end - new Date();
        if (diff <= 0) { td.innerHTML = '<span class="badge badge-matured">Matured</span>'; return; }
        const d = Math.floor(diff / 86400000);
        const h = Math.floor((diff % 86400000) / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        let text = '';
        if (d > 0) text += `${d}d `;
        text += `${h}h ${m}m ${s}s`;
        td.textContent = text;
    });
}
setInterval(updateTimeLeft, 1000); updateTimeLeft();

// CHART.JS (Fixed to render correctly)
const summaryData = <?php echo json_encode($summary); ?>;
const labels = Object.keys(summaryData).sort();
if (labels.length > 0 && document.getElementById('summaryChart')) {
    new Chart(document.getElementById('summaryChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                { label: 'Investments', data: labels.map(d => summaryData[d].investments), borderColor: '#d4af37', backgroundColor: 'rgba(212, 175, 55, 0.1)', borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: '#d4af37', pointBorderColor: '#0a0e1a', pointBorderWidth: 2 },
                { label: 'Deposits', data: labels.map(d => summaryData[d].deposits), borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.08)', borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: '#10b981', pointBorderColor: '#0a0e1a', pointBorderWidth: 2 },
                { label: 'Withdrawals', data: labels.map(d => summaryData[d].withdrawals), borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.08)', borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: '#ef4444', pointBorderColor: '#0a0e1a', pointBorderWidth: 2 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { labels: { color: '#94a3b8', font: { family: 'Inter', size: 12, weight: '500' }, usePointStyle: true, padding: 20 } }, tooltip: { backgroundColor: 'rgba(15, 20, 32, 0.95)', titleColor: '#f1f5f9', bodyColor: '#94a3b8', borderColor: 'rgba(212, 175, 55, 0.3)', borderWidth: 1, padding: 12, cornerRadius: 8 } },
            scales: { x: { ticks: { color: '#64748b', font: { family: 'Inter', size: 11 } }, grid: { color: 'rgba(148, 163, 184, 0.05)', drawBorder: false } }, y: { ticks: { color: '#64748b', font: { family: 'Inter', size: 11 }, callback: function(value) { return 'Ksh ' + value.toLocaleString(); } }, grid: { color: 'rgba(148, 163, 184, 0.05)', drawBorder: false } } }
        }
    });
}

const canvasStar = document.getElementById('starCanvas');
const ctxStar = canvasStar.getContext('2d');
function resizeStar() { canvasStar.width = window.innerWidth; canvasStar.height = window.innerHeight; }
resizeStar(); window.addEventListener('resize', resizeStar);
let stars = [];
const starCount = window.innerWidth < 768 ? 100 : 200;
for (let i = 0; i < starCount; i++) {
    stars.push({ x: Math.random() * canvasStar.width, y: Math.random() * canvasStar.height, radius: Math.random() * 1.2, alpha: Math.random(), dalpha: 0.003 + Math.random() * 0.008, dx: (Math.random() - 0.5) * 0.1, dy: (Math.random() - 0.5) * 0.1 });
}
function drawStars() {
    ctxStar.clearRect(0, 0, canvasStar.width, canvasStar.height);
    stars.forEach(s => {
        ctxStar.beginPath(); ctxStar.arc(s.x, s.y, s.radius, 0, 2 * Math.PI);
        ctxStar.fillStyle = `rgba(212, 175, 55, ${s.alpha * 0.5})`; ctxStar.fill();
        s.alpha += s.dalpha; if (s.alpha > 1 || s.alpha < 0.2) s.dalpha *= -1;
        s.x += s.dx; s.y += s.dy;
        if (s.x > canvasStar.width) s.x = 0; if (s.x < 0) s.x = canvasStar.width;
        if (s.y > canvasStar.height) s.y = 0; if (s.y < 0) s.y = canvasStar.height;
    });
    requestAnimationFrame(drawStars);
}
drawStars();

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => { if (entry.isIntersecting) entry.target.style.animationPlayState = 'running'; });
}, { threshold: 0.1 });
document.querySelectorAll('.fade-in-up').forEach(el => observer.observe(el));

window.addEventListener('load', () => {
    setTimeout(() => { showToast(`Welcome back, <?php echo htmlspecialchars($user_name); ?>! Your portfolio is performing well.`, 'success'); }, 800);
});

/* ============================================
   SEAMLESS CHAT AUTO-REFRESH (No Page Reload)
   ============================================ */
let lastChatHtml = '';
function toggleChatPanel() {
    const panel = document.getElementById('chatPanel');
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
        document.getElementById('chatInput').focus();
        scrollChatToBottom();
    }
}
function scrollChatToBottom() {
    const body = document.getElementById('chatBody');
    body.scrollTop = body.scrollHeight;
}
scrollChatToBottom();

// Auto-refresh chat every 5 seconds without leaving the page
setInterval(() => {
    fetch('chat_refresh.php?user_id=<?=$user_id?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.html !== lastChatHtml) {
                lastChatHtml = data.html;
                const body = document.getElementById('chatBody');
                body.innerHTML = data.html;
                // Only scroll to bottom if panel is open or it's a new message
                if (document.getElementById('chatPanel').classList.contains('open')) {
                    scrollChatToBottom();
                } else {
                    // Update badge if new message arrived while closed
                    const badge = document.querySelector('.chat-float-btn .chat-badge');
                    if (!badge) {
                        document.querySelector('.chat-float-btn').innerHTML = '<i class="fas fa-comments"></i><span class="chat-badge">1</span>';
                    } else {
                        badge.textContent = parseInt(badge.textContent) + 1;
                    }
                }
            }
        })
        .catch(err => console.error(err));
}, 5000);
</script>
</body>
</html>