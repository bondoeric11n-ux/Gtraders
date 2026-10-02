<?php
$ADMIN_SESSION_TIMEOUT = 1800;
session_start();
if (isset($_SESSION['admin_last_activity']) && time() - $_SESSION['admin_last_activity'] > $ADMIN_SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header("Location: admin_login.php?timeout=1");
    exit();
}
$_SESSION['admin_last_activity'] = time();
include("db_connect.php");
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// --- MILESTONE CONFIGURATION ---
$milestone_target = 20000; // Ksh 20,000 per milestone

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    // 1. Total Users
    $total_users = (int)($conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0);

    // 2. Total Account Balance (All Users)
    $total_account = (float)($conn->query("SELECT SUM(COALESCE(account_balance,0)) AS s FROM users")->fetch_assoc()['s'] ?? 0.00);

    // 3. Total Amount Invested (Active Principal)
    $total_invested = (float)($conn->query("SELECT COALESCE(SUM(net_amount), 0) AS s FROM investments WHERE LOWER(status)='active'")->fetch_assoc()['s'] ?? 0.00);

    // 4. Invested + Profits Combined (Fixed: uses expected_interest)
    $total_with_profit = (float)($conn->query("SELECT COALESCE(SUM(net_amount + expected_interest), 0) AS s FROM investments WHERE LOWER(status)='active'")->fetch_assoc()['s'] ?? 0.00);

    // 5. Amount Ready for Withdrawal (Total Balance - Pending Withdrawals)
    $pending_wd_amount = (float)($conn->query("SELECT COALESCE(SUM(amount),0) AS s FROM withdrawals WHERE LOWER(status)='pending'")->fetch_assoc()['s'] ?? 0.00);
    $ready_for_withdrawal = $total_account - $pending_wd_amount;

    // 6. Next Available Maturity
    $next_maturity = $conn->query("SELECT MIN(end_date) AS next_maturity FROM investments WHERE LOWER(status)='active' AND end_date > NOW()")->fetch_assoc()['next_maturity'] ?? null;

    // 7. Milestone Math
    $milestones_reached = floor($total_invested / $milestone_target);
    $current_milestone_progress = $total_invested % $milestone_target;
    $milestone_percentage = $milestone_target > 0 ? ($current_milestone_progress / $milestone_target) * 100 : 0;
    $next_milestone_amount = ($milestones_reached + 1) * $milestone_target;

    // Original Fee Stats
    $total_fees_collected = (float)($conn->query("SELECT COALESCE(SUM(fee_amount),0) AS s FROM daily_fees")->fetch_assoc()['s'] ?? 0.00);
    $total_fees_withdrawn = (float)($conn->query("SELECT COALESCE(SUM(amount),0) AS s FROM admin_wallet_log")->fetch_assoc()['s'] ?? 0.00);
    $remaining_fees = $total_fees_collected - $total_fees_withdrawn;
    $pending_withdrawals = (int)($conn->query("SELECT COUNT(*) AS c FROM withdrawals WHERE LOWER(status)='pending'")->fetch_assoc()['c'] ?? 0);
    $pending_deposits = (int)($conn->query("SELECT COUNT(*) AS c FROM btc_deposits WHERE LOWER(status)='pending'")->fetch_assoc()['c'] ?? 0);

    // --- TABLES HTML ---
    $users_html = '';
    $users_q = $conn->query("SELECT u.id, u.username, u.name, u.email, u.phone, u.account_balance, COALESCE((SELECT SUM(net_amount) FROM investments WHERE user_id=u.id AND LOWER(status)='active'),0) AS invested_balance, u.created_at FROM users u ORDER BY u.created_at DESC LIMIT 1000");
    while ($u = $users_q->fetch_assoc()) {
        $users_html .= '<tr><td class="cell-username">'.htmlspecialchars($u['username']).'</td><td>'.htmlspecialchars($u['name']).'</td><td class="cell-email">'.htmlspecialchars($u['email']).'</td><td>'.htmlspecialchars($u['phone']).'</td><td class="cell-money">Ksh '.number_format($u['account_balance'],2).'</td><td class="cell-money">Ksh '.number_format($u['invested_balance'],2).'</td><td class="cell-date">'.htmlspecialchars($u['created_at']).'</td><td><a href="edit_user.php?id='.$u['id'].'" class="btn-action btn-edit"><i class="fas fa-pencil-alt"></i></a></td></tr>';
    }

    $invest_html = '';
    $pending_q = $conn->query("SELECT i.id, i.user_id, i.plan_id, i.amount, i.net_amount, i.fee, i.expected_interest, i.status, i.start_date, i.end_date, u.username FROM investments i JOIN users u ON u.id = i.user_id WHERE LOWER(i.status)='active' ORDER BY i.end_date ASC LIMIT 1000");
    while ($i = $pending_q->fetch_assoc()) {
        $invest_html .= '<tr><td class="cell-username">'.htmlspecialchars($i['username']).'</td><td><span class="badge badge-info">Plan #'.$i['plan_id'].'</span></td><td class="cell-money">Ksh '.number_format($i['amount'],2).'</td><td class="cell-money">Ksh '.number_format($i['net_amount'],2).'</td><td class="cell-money cell-fee">Ksh '.number_format($i['fee'],2).'</td><td class="cell-money cell-interest">+Ksh '.number_format($i['expected_interest'],2).'</td><td><span class="badge badge-active">'.htmlspecialchars($i['status']).'</span></td><td class="cell-date">'.htmlspecialchars($i['start_date']).'</td><td class="cell-date">'.htmlspecialchars($i['end_date']).'</td></tr>';
    }

    $withdraw_html = '';
    $withdraw_q = $conn->query("SELECT w.id, w.user_id, u.username, w.amount, w.status, w.created_at FROM withdrawals w JOIN users u ON u.id = w.user_id ORDER BY w.created_at DESC LIMIT 10");
    while ($w = $withdraw_q->fetch_assoc()) {
        $badge_class = $w['status'] === 'approved' ? 'badge-approved' : ($w['status'] === 'rejected' ? 'badge-rejected' : 'badge-pending');
        $withdraw_html .= '<tr><td class="cell-username">'.htmlspecialchars($w['username']).'</td><td class="cell-money">Ksh '.number_format($w['amount'],2).'</td><td><span class="badge '.$badge_class.'">'.ucfirst($w['status']).'</span></td><td class="cell-date">'.htmlspecialchars($w['created_at']).'</td></tr>';
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_users' => $total_users,
            'total_account' => number_format($total_account,2),
            'total_invested' => number_format($total_invested, 2),
            'total_with_profit' => number_format($total_with_profit, 2),
            'ready_for_withdrawal' => number_format($ready_for_withdrawal, 2),
            'next_maturity' => $next_maturity ? date('M d, H:i', strtotime($next_maturity)) : 'None',
            'milestones_reached' => $milestones_reached,
            'milestone_percentage' => round($milestone_percentage, 1),
            'next_milestone_amount' => number_format($next_milestone_amount, 0),
            'total_fees_collected' => number_format($total_fees_collected,2),
            'total_fees_withdrawn' => number_format($total_fees_withdrawn,2),
            'remaining_fees' => number_format($remaining_fees,2),
            'pending_withdrawals' => $pending_withdrawals,
            'pending_deposits' => $pending_deposits
        ],
        'tables' => ['users' => $users_html, 'investments' => $invest_html, 'withdrawals' => $withdraw_html]
    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit();
}

// --- STANDARD PAGE QUERIES FOR INITIAL LOAD ---
$total_users = (int)($conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0);
$total_account = (float)($conn->query("SELECT SUM(COALESCE(account_balance,0)) AS s FROM users")->fetch_assoc()['s'] ?? 0.00);
$total_invested = (float)($conn->query("SELECT COALESCE(SUM(net_amount), 0) AS s FROM investments WHERE LOWER(status)='active'")->fetch_assoc()['s'] ?? 0.00);
$total_with_profit = (float)($conn->query("SELECT COALESCE(SUM(net_amount + expected_interest), 0) AS s FROM investments WHERE LOWER(status)='active'")->fetch_assoc()['s'] ?? 0.00);

$pending_wd_amount = (float)($conn->query("SELECT COALESCE(SUM(amount),0) AS s FROM withdrawals WHERE LOWER(status)='pending'")->fetch_assoc()['s'] ?? 0.00);
$ready_for_withdrawal = $total_account - $pending_wd_amount;
$next_maturity = $conn->query("SELECT MIN(end_date) AS next_maturity FROM investments WHERE LOWER(status)='active' AND end_date > NOW()")->fetch_assoc()['next_maturity'] ?? null;

// Milestone Math
$milestones_reached = floor($total_invested / $milestone_target);
$current_milestone_progress = $total_invested % $milestone_target;
$milestone_percentage = $milestone_target > 0 ? ($current_milestone_progress / $milestone_target) * 100 : 0;
$next_milestone_amount = ($milestones_reached + 1) * $milestone_target;

$total_fees_collected = (float)($conn->query("SELECT COALESCE(SUM(fee_amount),0) AS s FROM daily_fees")->fetch_assoc()['s'] ?? 0.00);
$total_fees_withdrawn = (float)($conn->query("SELECT COALESCE(SUM(amount),0) AS s FROM admin_wallet_log")->fetch_assoc()['s'] ?? 0.00);
$remaining_fees = $total_fees_collected - $total_fees_withdrawn;
$pending_withdrawals = (int)($conn->query("SELECT COUNT(*) AS c FROM withdrawals WHERE LOWER(status)='pending'")->fetch_assoc()['c'] ?? 0);
$pending_deposits = (int)($conn->query("SELECT COUNT(*) AS c FROM btc_deposits WHERE LOWER(status)='pending'")->fetch_assoc()['c'] ?? 0);

$users_q = $conn->query("SELECT u.id, u.username, u.name, u.email, u.phone, u.account_balance, COALESCE((SELECT SUM(net_amount) FROM investments WHERE user_id=u.id AND LOWER(status)='active'),0) AS invested_balance, u.created_at FROM users u ORDER BY u.created_at DESC LIMIT 1000");
$pending_q = $conn->query("SELECT i.id, i.user_id, i.plan_id, i.amount, i.net_amount, i.fee, i.expected_interest, i.status, i.start_date, i.end_date, u.username FROM investments i JOIN users u ON u.id = i.user_id WHERE LOWER(i.status)='active' ORDER BY i.end_date ASC LIMIT 1000");
$withdraw_q = $conn->query("SELECT w.id, w.user_id, u.username, w.amount, w.status, w.created_at FROM withdrawals w JOIN users u ON u.id = w.user_id ORDER BY w.created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Admin Command Center | GIBAL LTD</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg-base: #0b1120; --bg-surface: #111827; --bg-elevated: #1f2937;
    --bg-card: rgba(17, 24, 39, 0.7); --bg-card-hover: rgba(31, 41, 55, 0.9);
    --accent: #06b6d4; --accent-light: #22d3ee; --accent-dark: #0891b2;
    --accent-glow: rgba(6, 182, 212, 0.15); --accent-border: rgba(6, 182, 212, 0.25);
    --accent-2: #8b5cf6; --accent-2-glow: rgba(139, 92, 246, 0.15);
    --text-primary: #f1f5f9; --text-secondary: #cbd5e1; --text-tertiary: #64748b; --text-muted: #475569;
    --success: #10b981; --success-bg: rgba(16, 185, 129, 0.12); --success-border: rgba(16, 185, 129, 0.3);
    --warning: #f59e0b; --warning-bg: rgba(245, 158, 11, 0.12); --warning-border: rgba(245, 158, 11, 0.3);
    --danger: #ef4444; --danger-bg: rgba(239, 68, 68, 0.12); --danger-border: rgba(239, 68, 68, 0.3);
    --info: #3b82f6; --info-bg: rgba(59, 130, 246, 0.12); --info-border: rgba(59, 130, 246, 0.3);
    --border-subtle: rgba(148, 163, 184, 0.08); --border-medium: rgba(148, 163, 184, 0.15); --border-strong: rgba(148, 163, 184, 0.25);
    --radius-sm: 6px; --radius-md: 10px; --radius-lg: 14px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: 'Inter', sans-serif; background: var(--bg-base); color: var(--text-primary); overflow-x: hidden; line-height: 1.5; min-height: 100vh;
    background-image: linear-gradient(rgba(6, 182, 212, 0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(6, 182, 212, 0.02) 1px, transparent 1px);
    background-size: 40px 40px, 40px 40px;
}
.cell-money, .stat-value, .badge-live { font-family: 'JetBrains Mono', monospace; font-weight: 600; letter-spacing: -0.02em; }

.topbar { position: sticky; top: 0; z-index: 1000; background: rgba(11, 17, 32, 0.95); backdrop-filter: blur(20px); border-bottom: 1px solid var(--accent-border); padding: 0 32px; height: 64px; display: flex; align-items: center; justify-content: space-between; }
.brand { display: flex; align-items: center; gap: 12px; color: var(--text-primary); text-decoration: none; font-weight: 700; }
.brand-icon { width: 36px; height: 36px; background: linear-gradient(135deg, var(--accent), var(--accent-dark)); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--bg-base); font-weight: 800; }
.brand-text { display: flex; flex-direction: column; line-height: 1.2; }
.brand-title { font-size: 1rem; } .brand-subtitle { font-size: 0.7rem; color: var(--accent); text-transform: uppercase; letter-spacing: 0.1em; }
.topbar-actions { display: flex; align-items: center; gap: 12px; }
.admin-info { color: var(--text-secondary); font-size: 0.85rem; padding: 6px 12px; background: var(--bg-elevated); border-radius: 50px; border: 1px solid var(--border-subtle); }
.admin-info strong { color: var(--accent-light); }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: var(--radius-sm); font-weight: 600; font-size: 0.8rem; cursor: pointer; transition: 0.2s; border: none; text-decoration: none; text-transform: uppercase; }
.btn-outline { background: transparent; border: 1px solid var(--border-strong); color: var(--text-secondary); }
.btn-outline:hover { background: var(--bg-elevated); color: var(--accent-light); }
.btn-danger-outline { background: transparent; border: 1px solid var(--danger-border); color: var(--danger); }

.container-xl { max-width: 1600px; margin: 0 auto; padding: 24px 32px 60px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid var(--border-subtle); }
.page-header h2 { font-size: 1.5rem; display: flex; align-items: center; gap: 12px; }
.page-header h2 i { color: var(--accent); }
.live-indicator { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; background: var(--success-bg); border: 1px solid var(--success-border); border-radius: 50px; font-size: 0.75rem; font-weight: 600; color: var(--success); }
.live-indicator::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--success); animation: pulse 2s infinite; }
@keyframes pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 50% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); } }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 16px; transition: 0.2s; position: relative; overflow: hidden; text-decoration: none; color: inherit; display: block; }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--accent); opacity: 0.6; }
.stat-card:hover { transform: translateY(-2px); border-color: var(--accent-border); background: var(--bg-card-hover); }
.stat-card:hover::before { opacity: 1; }
.stat-card.accent-2::before { background: var(--accent-2); }
.stat-card.accent-success::before { background: var(--success); }
.stat-card.accent-warning::before { background: var(--warning); }
.stat-card.accent-gold::before { background: #fbbf24; }

.stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.stat-label { font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-tertiary); }
.stat-icon { width: 28px; height: 28px; border-radius: 6px; background: var(--accent-glow); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; }
.stat-card.accent-2 .stat-icon { background: var(--accent-2-glow); color: var(--accent-2); }
.stat-card.accent-success .stat-icon { background: var(--success-bg); color: var(--success); }
.stat-card.accent-warning .stat-icon { background: var(--warning-bg); color: var(--warning); }
.stat-card.accent-gold .stat-icon { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }

.stat-value { font-size: 1.3rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; line-height: 1.2; }
.stat-value.accent { color: var(--accent-light); }
.stat-value.accent-2 { color: var(--accent-2); }
.stat-value.success { color: var(--success); }
.stat-value.warning { color: var(--warning); }
.stat-value.gold { color: #fbbf24; }
.stat-hint { font-size: 0.65rem; color: var(--text-muted); }

/* Milestone Progress Bar */
.milestone-progress-bg { width: 100%; height: 6px; background: var(--bg-elevated); border-radius: 3px; margin-top: 10px; overflow: hidden; }
.milestone-progress-fill { height: 100%; background: linear-gradient(90deg, #fbbf24, #f59e0b); border-radius: 3px; transition: width 0.5s ease; }
.milestone-text { display: flex; justify-content: space-between; font-size: 0.65rem; color: var(--text-tertiary); margin-top: 6px; font-weight: 600; }

.action-bar { display: flex; justify-content: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
.action-btn { display: inline-flex; align-items: center; gap: 12px; background: var(--bg-elevated); border: 1px solid var(--accent-border); color: var(--accent-light); padding: 14px 24px; border-radius: var(--radius-md); font-weight: 600; font-size: 0.9rem; text-decoration: none; transition: 0.2s; }
.action-btn:hover { background: var(--accent-glow); border-color: var(--accent); transform: translateY(-2px); }
.badge-live { background: var(--accent); color: var(--bg-base); border-radius: 50px; padding: 3px 10px; font-size: 0.75rem; font-weight: 700; }

.search-container { margin-bottom: 24px; position: relative; max-width: 500px; }
.search-input { width: 100%; padding: 12px 16px 12px 44px; background: var(--bg-elevated); border: 1px solid var(--border-medium); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.9rem; }
.search-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
.search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-tertiary); }

.card-section { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); margin-bottom: 28px; overflow: hidden; }
.section-header { display: flex; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid var(--border-subtle); background: rgba(6, 182, 212, 0.03); }
.section-title { font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 10px; text-transform: uppercase; }
.section-title i { color: var(--accent); }
.section-count { padding: 4px 10px; background: var(--bg-elevated); border-radius: 50px; font-size: 0.75rem; font-weight: 600; }

.table-responsive { overflow-x: auto; }
.scrollable-table { max-height: 500px; overflow-y: auto; }
.scrollable-table::-webkit-scrollbar { width: 8px; }
.scrollable-table::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 4px; }
table { width: 100%; border-collapse: collapse; min-width: 900px; }
thead { position: sticky; top: 0; z-index: 10; background: var(--bg-surface); }
th { padding: 12px 16px; text-align: left; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--accent-light); border-bottom: 2px solid var(--accent-border); }
td { padding: 12px 16px; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); }
tbody tr:hover { background: var(--bg-elevated); }
tbody tr:hover td { color: var(--text-primary); }
.cell-username { color: var(--text-primary); font-weight: 600; }
.cell-email { font-size: 0.82rem; }
.cell-money { color: var(--text-primary); font-weight: 600; }
.cell-fee { color: var(--text-muted); }
.cell-interest { color: var(--success); font-weight: 700; }
.cell-date { color: var(--text-muted); font-size: 0.8rem; font-family: 'JetBrains Mono', monospace; }
.btn-action { width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
.btn-edit { background: var(--accent-glow); color: var(--accent); border: 1px solid var(--accent-border); }
.btn-edit:hover { background: var(--accent); color: var(--bg-base); }
.badge { padding: 4px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; text-decoration: none !important; }
.badge-pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning-border); }
.badge-approved, .badge-active { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border); }
.badge-rejected { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger-border); }
.badge-info { background: var(--info-bg); color: var(--info); border: 1px solid var(--info-border); }

@media (max-width: 768px) {
    .topbar { padding: 0 16px; } .admin-info { display: none; }
    .container-xl { padding: 20px 16px 40px; } .stats-grid { grid-template-columns: 1fr; }
    .action-bar { flex-direction: column; } .action-btn { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<div class="topbar">
    <a href="admin_dashboard.php" class="brand">
        <div class="brand-icon"><i class="fas fa-terminal"></i></div>
        <div class="brand-text"><span class="brand-title">GIBAL ADMIN</span><span class="brand-subtitle">Command Center</span></div>
    </a>
    <div class="topbar-actions">
        <div class="admin-info"><i class="fas fa-user-shield" style="color: var(--accent); margin-right: 6px;"></i><strong><?=htmlspecialchars($_SESSION['admin_username'] ?? 'admin')?></strong></div>
        <a href="admin_settings.php" class="btn btn-outline"><i class="fas fa-cog"></i> Settings</a>
        <a href="admin_logout.php" class="btn btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container-xl">
    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> Financial Operations Overview</h2>
        <div class="live-indicator">Live Data · Auto-refresh 15s</div>
    </div>

    <div class="stats-grid" id="statsRow">
        <!-- NEW: Total Amount Invested -->
        <div class="stat-card">
            <div class="stat-header"><div class="stat-label">Total Invested</div><div class="stat-icon"><i class="fas fa-coins"></i></div></div>
            <div id="stat_total_invested" class="stat-value accent">Ksh <?=number_format((float)$total_invested, 2)?></div>
            <div class="stat-hint">Active principal capital</div>
        </div>

        <!-- NEW: Invested + Profits Combined -->
        <div class="stat-card accent-2">
            <div class="stat-header"><div class="stat-label">Invested + Profits</div><div class="stat-icon"><i class="fas fa-chart-pie"></i></div></div>
            <div id="stat_total_with_profit" class="stat-value accent-2">Ksh <?=number_format((float)$total_with_profit, 2)?></div>
            <div class="stat-hint">Total projected payout</div>
        </div>

        <!-- NEW: Amount Ready for Withdrawal -->
        <div class="stat-card accent-success">
            <div class="stat-header"><div class="stat-label">Ready for Withdrawal</div><div class="stat-icon"><i class="fas fa-wallet"></i></div></div>
            <div id="stat_ready_for_withdrawal" class="stat-value success">Ksh <?=number_format((float)$ready_for_withdrawal, 2)?></div>
            <div class="stat-hint">Total user available balance</div>
        </div>

        <!-- NEW: Next Available Maturity -->
        <div class="stat-card accent-warning">
            <div class="stat-header"><div class="stat-label">Next Trade Maturity</div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
            <div id="stat_next_maturity" class="stat-value warning" style="font-size: 1.1rem;"><?= $next_maturity ? date('M d, H:i', strtotime($next_maturity)) : 'None' ?></div>
            <div class="stat-hint">Earliest active trade ending</div>
        </div>

        <!-- NEW: Milestone Tracker -->
        <div class="stat-card accent-gold" style="grid-column: span 2;">
            <div class="stat-header"><div class="stat-label">Investment Milestone (Every 20k)</div><div class="stat-icon"><i class="fas fa-trophy"></i></div></div>
            <div class="stat-value gold" style="font-size: 1.1rem;">Level <span id="milestone_level"><?=$milestones_reached?></span> Reached</div>
            <div class="milestone-progress-bg">
                <div class="milestone-progress-fill" id="milestone_bar" style="width: <?=$milestone_percentage?>%;"></div>
            </div>
            <div class="milestone-text">
                <span id="milestone_current"><?=number_format($current_milestone_progress, 0)?> / 20,000</span>
                <span>Next: Ksh <span id="milestone_next"><?=$next_milestone_amount?></span></span>
            </div>
        </div>

        <!-- ORIGINAL: Total Users -->
        <div class="stat-card">
            <div class="stat-header"><div class="stat-label">Total Users</div><div class="stat-icon"><i class="fas fa-users"></i></div></div>
            <div id="stat_total_users" class="stat-value"><?=number_format($total_users)?></div>
            <div class="stat-hint">Registered accounts</div>
        </div>
        
        <!-- ORIGINAL: Total Account Balance -->
        <div class="stat-card">
            <div class="stat-header"><div class="stat-label">Total Account Balance</div><div class="stat-icon"><i class="fas fa-university"></i></div></div>
            <div id="stat_total_account" class="stat-value accent">Ksh <?=number_format((float)$total_account, 2)?></div>
            <div class="stat-hint">Across all users</div>
        </div>
        
        <!-- ORIGINAL: Fees Collected -->
        <a href="user_fees.php" class="stat-card">
            <div class="stat-header"><div class="stat-label">Fees Collected</div><div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div></div>
            <div id="stat_total_fees_collected" class="stat-value accent">Ksh <?=number_format($total_fees_collected, 2)?></div>
            <div class="stat-hint">Click to view breakdown →</div>
        </a>
        
        <!-- ORIGINAL: Fees Withdrawn -->
        <a href="admin_wallet.php" class="stat-card accent-success">
            <div class="stat-header"><div class="stat-label">Fees Withdrawn</div><div class="stat-icon"><i class="fas fa-arrow-circle-down"></i></div></div>
            <div id="stat_total_fees_withdrawn" class="stat-value success">Ksh <?=number_format($total_fees_withdrawn, 2)?></div>
            <div class="stat-hint">Click to view wallet log →</div>
        </a>
        
        <!-- ORIGINAL: Remaining Fees -->
        <a href="admin_wallet.php" class="stat-card">
            <div class="stat-header"><div class="stat-label">Remaining Fees</div><div class="stat-icon"><i class="fas fa-piggy-bank"></i></div></div>
            <div id="stat_remaining_fees" class="stat-value accent">Ksh <?=number_format($remaining_fees, 2)?></div>
            <div class="stat-hint">Available in admin wallet</div>
        </a>
    </div>

    <div class="action-bar">
        <a href="withdrawals_requested.php" class="action-btn"><i class="fas fa-money-check-alt"></i> <span>Withdrawals Pending</span><span id="pendingCount" class="badge-live"><?=$pending_withdrawals?></span></a>
        <a href="admin_deposits.php" class="action-btn"><i class="fas fa-wallet"></i> <span>Deposits Pending</span><span id="pendingDepositsCount" class="badge-live"><?=$pending_deposits?></span></a>
    </div>

    <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="userSearchInput" placeholder="Search users..." class="search-input" autocomplete="off">
    </div>

    <div class="card-section">
        <div class="section-header"><div class="section-title"><i class="fas fa-users"></i> Registered Users</div><div class="section-count"><?=number_format($total_users)?> total</div></div>
        <div class="table-responsive"><div class="scrollable-table">
            <table><thead><tr><th>Username</th><th>Name</th><th>Email</th><th>Phone</th><th>Balance</th><th>Invested</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody id="users_tbody"><?php while($u = $users_q->fetch_assoc()): ?>
                <tr><td class="cell-username"><?=htmlspecialchars($u['username'])?></td><td><?=htmlspecialchars($u['name'])?></td><td class="cell-email"><?=htmlspecialchars($u['email'])?></td><td><?=htmlspecialchars($u['phone'])?></td><td class="cell-money">Ksh <?=number_format($u['account_balance'],2)?></td><td class="cell-money">Ksh <?=number_format($u['invested_balance'],2)?></td><td class="cell-date"><?=htmlspecialchars($u['created_at'])?></td><td><a href="edit_user.php?id=<?=$u['id']?>" class="btn-action btn-edit"><i class="fas fa-pencil-alt"></i></a></td></tr>
            <?php endwhile; ?></tbody></table>
        </div></div>
    </div>

    <div class="card-section">
        <div class="section-header"><div class="section-title"><i class="fas fa-chart-line"></i> Active Investments</div><div class="section-count">Projected: Ksh <?=number_format($total_with_profit, 2)?></div></div>
        <div class="table-responsive"><div class="scrollable-table">
            <table><thead><tr><th>User</th><th>Plan</th><th>Amount</th><th>Net</th><th>Fee</th><th>Expected Profit</th><th>Status</th><th>Start</th><th>End</th></tr></thead>
            <tbody id="investments_tbody"><?php while($i = $pending_q->fetch_assoc()): ?>
                <tr><td class="cell-username"><?=htmlspecialchars($i['username'])?></td><td><span class="badge badge-info">#<?=$i['plan_id']?></span></td><td class="cell-money">Ksh <?=number_format($i['amount'],2)?></td><td class="cell-money">Ksh <?=number_format($i['net_amount'],2)?></td><td class="cell-money cell-fee">Ksh <?=number_format($i['fee'],2)?></td><td class="cell-money cell-interest">+Ksh <?=number_format($i['expected_interest'],2)?></td><td><span class="badge badge-active"><?=htmlspecialchars($i['status'])?></span></td><td class="cell-date"><?=htmlspecialchars($i['start_date'])?></td><td class="cell-date"><?=htmlspecialchars($i['end_date'])?></td></tr>
            <?php endwhile; ?></tbody></table>
        </div></div>
    </div>

    <div class="card-section">
        <div class="section-header"><div class="section-title"><i class="fas fa-history"></i> Latest Withdrawals</div><div class="section-count">Last 10</div></div>
        <div class="table-responsive"><div class="scrollable-table">
            <table><thead><tr><th>User</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead>
            <tbody id="withdrawals_tbody"><?php while($w = $withdraw_q->fetch_assoc()): 
                $badge_class = $w['status'] === 'approved' ? 'badge-approved' : ($w['status'] === 'rejected' ? 'badge-rejected' : 'badge-pending');
            ?>
                <tr><td class="cell-username"><?=htmlspecialchars($w['username'])?></td><td class="cell-money">Ksh <?=number_format($w['amount'],2)?></td><td><span class="badge <?=$badge_class?>"><?=ucfirst($w['status'])?></span></td><td class="cell-date"><?=htmlspecialchars($w['created_at'])?></td></tr>
            <?php endwhile; ?></tbody></table>
        </div></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const searchInput = document.getElementById('userSearchInput');
const usersTbody = document.getElementById('users_tbody');
searchInput.addEventListener('input', ()=>{
  const filter = searchInput.value.toLowerCase();
  Array.from(usersTbody.rows).forEach(row=>{
    row.style.display = Array.from(row.cells).some(td=>td.textContent.toLowerCase().includes(filter)) ? '' : 'none';
  });
});

setInterval(()=>{ 
    fetch('admin_dashboard.php?ajax=1').then(r=>r.json()).then(data=>{
        if(data.success){
            document.getElementById('stat_total_invested').textContent = 'Ksh ' + data.stats.total_invested;
            document.getElementById('stat_total_with_profit').textContent = 'Ksh ' + data.stats.total_with_profit;
            document.getElementById('stat_ready_for_withdrawal').textContent = 'Ksh ' + data.stats.ready_for_withdrawal;
            document.getElementById('stat_next_maturity').textContent = data.stats.next_maturity;
            document.getElementById('stat_total_users').textContent = data.stats.total_users;
            document.getElementById('stat_total_account').textContent = 'Ksh ' + data.stats.total_account;
            document.getElementById('stat_total_fees_collected').textContent = 'Ksh ' + data.stats.total_fees_collected;
            document.getElementById('stat_total_fees_withdrawn').textContent = 'Ksh ' + data.stats.total_fees_withdrawn;
            document.getElementById('stat_remaining_fees').textContent = 'Ksh ' + data.stats.remaining_fees;
            document.getElementById('pendingCount').textContent = data.stats.pending_withdrawals;
            document.getElementById('pendingDepositsCount').textContent = data.stats.pending_deposits;
            
            // Update Milestone
            document.getElementById('milestone_level').textContent = data.stats.milestones_reached;
            document.getElementById('milestone_bar').style.width = data.stats.milestone_percentage + '%';
            document.getElementById('milestone_next').textContent = data.stats.next_milestone_amount;
            // Calculate current progress for text display
            let nextM = parseFloat(data.stats.next_milestone_amount.replace(/,/g, ''));
            let target = 20000;
            let currentProg = nextM - target;
            document.getElementById('milestone_current').textContent = currentProg.toLocaleString() + ' / 20,000';

            if(data.tables){
                document.getElementById('users_tbody').innerHTML = data.tables.users;
                document.getElementById('investments_tbody').innerHTML = data.tables.investments;
                document.getElementById('withdrawals_tbody').innerHTML = data.tables.withdrawals;
            }
        }
    }).catch(err => console.error("AJAX refresh failed:", err));
}, 15000);
</script>
</body>
</html>