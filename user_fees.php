<?php
    ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

$toast_message = "";
$toast_type = "info";

// Date range filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Validate dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ACTION 1: Manually add fee entry
    if (isset($_POST['add_fee'])) {
        $fee_amount = floatval($_POST['fee_amount']);
        $fee_date = $_POST['fee_date'];
        $description = trim($_POST['description']);
        $source = trim($_POST['source']);

        if ($fee_amount <= 0) {
            $toast_message = "Fee amount must be greater than zero.";
            $toast_type = "error";
        } else {
            $insert = $conn->prepare("INSERT INTO daily_fees (fee_amount, fee_date, description, source, created_at) VALUES (?, ?, ?, ?, NOW())");
            $insert->bind_param("dsss", $fee_amount, $fee_date, $description, $source);
            if ($insert->execute()) {
                $toast_message = "Fee entry added successfully.";
                $toast_type = "success";
                logAdminAction("ADD_FEE", "Manually added fee of Ksh $fee_amount for $fee_date. Source: $source");
            } else {
                $toast_message = "Failed to add fee: " . $conn->error;
                $toast_type = "error";
            }
        }
    }

    // ACTION 2: Delete fee entry
    if (isset($_POST['delete_fee'])) {
        $fee_id = (int)$_POST['fee_id'];
        $delete = $conn->prepare("DELETE FROM daily_fees WHERE id = ?");
        $delete->bind_param("i", $fee_id);
        if ($delete->execute()) {
            $toast_message = "Fee entry deleted successfully.";
            $toast_type = "success";
            logAdminAction("DELETE_FEE", "Deleted fee entry #$fee_id");
        } else {
            $toast_message = "Failed to delete fee.";
            $toast_type = "error";
        }
    }
}

// Create daily_fees table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS daily_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fee_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    fee_date DATE NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    source VARCHAR(100) DEFAULT 'investment',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fee_date (fee_date)
)");

// Fetch fees within date range
$fees_stmt = $conn->prepare("SELECT * FROM daily_fees WHERE fee_date BETWEEN ? AND ? ORDER BY fee_date DESC, id DESC");
$fees_stmt->bind_param("ss", $start_date, $end_date);
$fees_stmt->execute();
$fees = $fees_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$fees_stmt->close();

// Calculate totals
$total_fees_range = 0;
foreach ($fees as $fee) {
    $total_fees_range += floatval($fee['fee_amount']);
}

// All-time totals
$total_fees_all = $conn->query("SELECT COALESCE(SUM(fee_amount),0) AS total FROM daily_fees")->fetch_assoc()['total'];

// Today's fees
$today = date('Y-m-d');
$todays_fees = $conn->query("SELECT COALESCE(SUM(fee_amount),0) AS total FROM daily_fees WHERE fee_date = '$today'")->fetch_assoc()['total'];

// This week's fees
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_fees = $conn->query("SELECT COALESCE(SUM(fee_amount),0) AS total FROM daily_fees WHERE fee_date >= '$week_start'")->fetch_assoc()['total'];

// This month's fees
$month_start = date('Y-m-01');
$month_fees = $conn->query("SELECT COALESCE(SUM(fee_amount),0) AS total FROM daily_fees WHERE fee_date >= '$month_start'")->fetch_assoc()['total'];

// Fees withdrawn from admin wallet
$total_fees_withdrawn = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM admin_wallet_log")->fetch_assoc()['total'];
$remaining_fees = floatval($total_fees_all) - floatval($total_fees_withdrawn);

// Daily aggregation for chart
$daily_chart = [];
$daily_stmt = $conn->prepare("SELECT fee_date, SUM(fee_amount) AS total FROM daily_fees WHERE fee_date BETWEEN ? AND ? GROUP BY fee_date ORDER BY fee_date ASC");
$daily_stmt->bind_param("ss", $start_date, $end_date);
$daily_stmt->execute();
$daily_result = $daily_stmt->get_result();
while ($row = $daily_result->fetch_assoc()) {
    $daily_chart[$row['fee_date']] = floatval($row['total']);
}
$daily_stmt->close();

// Fill in missing dates with 0
$chart_labels = [];
$chart_data = [];
$current = new DateTime($start_date);
$end = new DateTime($end_date);
while ($current <= $end) {
    $date_str = $current->format('Y-m-d');
    $chart_labels[] = $current->format('M d');
    $chart_data[] = $daily_chart[$date_str] ?? 0;
    $current->modify('+1 day');
}

// Source breakdown
$source_breakdown = [];
$source_stmt = $conn->prepare("SELECT source, SUM(fee_amount) AS total FROM daily_fees WHERE fee_date BETWEEN ? AND ? GROUP BY source ORDER BY total DESC");
$source_stmt->bind_param("ss", $start_date, $end_date);
$source_stmt->execute();
$source_result = $source_stmt->get_result();
while ($row = $source_result->fetch_assoc()) {
    $source_breakdown[$row['source']] = floatval($row['total']);
}
$source_stmt->close();

// Helper function for audit logging
function logAdminAction($action, $details) {
    global $conn;
    $admin_id = $_SESSION['admin_id'] ?? 0;
    $admin_username = $_SESSION['admin_username'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $conn->query("CREATE TABLE IF NOT EXISTS admin_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT,
        admin_username VARCHAR(100),
        action VARCHAR(100),
        details TEXT,
        ip_address VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $conn->prepare("INSERT INTO admin_audit_log (admin_id, admin_username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $admin_id, $admin_username, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Fee Management | GIBAL Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* Admin Command Center Theme */
:root {
    --bg-base: #0b1120;
    --bg-surface: #111827;
    --bg-elevated: #1f2937;
    --bg-card: rgba(17, 24, 39, 0.7);
    --bg-card-hover: rgba(31, 41, 55, 0.9);
    --accent: #06b6d4;
    --accent-light: #22d3ee;
    --accent-dark: #0891b2;
    --accent-glow: rgba(6, 182, 212, 0.15);
    --accent-border: rgba(6, 182, 212, 0.25);
    --accent-2: #8b5cf6;
    --accent-2-glow: rgba(139, 92, 246, 0.15);
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --text-tertiary: #64748b;
    --text-muted: #475569;
    --success: #10b981;
    --success-bg: rgba(16, 185, 129, 0.12);
    --success-border: rgba(16, 185, 129, 0.3);
    --warning: #f59e0b;
    --warning-bg: rgba(245, 158, 11, 0.12);
    --warning-border: rgba(245, 158, 11, 0.3);
    --danger: #ef4444;
    --danger-bg: rgba(239, 68, 68, 0.12);
    --danger-border: rgba(239, 68, 68, 0.3);
    --info: #3b82f6;
    --info-bg: rgba(59, 130, 246, 0.12);
    --info-border: rgba(59, 130, 246, 0.3);
    --border-subtle: rgba(148, 163, 184, 0.08);
    --border-medium: rgba(148, 163, 184, 0.15);
    --border-strong: rgba(148, 163, 184, 0.25);
    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 14px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--bg-base);
    color: var(--text-primary);
    overflow-x: hidden;
    line-height: 1.5;
    min-height: 100vh;
    background-image: 
        linear-gradient(rgba(6, 182, 212, 0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(6, 182, 212, 0.02) 1px, transparent 1px),
        radial-gradient(ellipse at top, rgba(6, 182, 212, 0.05) 0%, transparent 50%);
    background-size: 40px 40px, 40px 40px, 100% 100%;
}
.cell-money, .stat-value {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 600;
    letter-spacing: -0.02em;
}

/* Topbar */
.topbar {
    position: sticky; top: 0; z-index: 1000;
    background: rgba(11, 17, 32, 0.95);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--accent-border);
    padding: 0 32px; height: 64px;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}
.brand {
    display: flex; align-items: center; gap: 12px;
    color: var(--text-primary); text-decoration: none; font-weight: 700; font-size: 1.1rem;
}
.brand-icon {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
    border-radius: 8px; display: flex; align-items: center; justify-content: center;
    color: var(--bg-base); font-weight: 800; font-size: 1rem;
    box-shadow: 0 0 20px var(--accent-glow);
}
.brand-text { display: flex; flex-direction: column; line-height: 1.2; }
.brand-title { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.brand-subtitle { font-size: 0.7rem; color: var(--accent); text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600; }
.topbar-actions { display: flex; align-items: center; gap: 12px; }
.admin-info { 
    color: var(--text-secondary); font-size: 0.85rem; 
    padding: 6px 12px; background: var(--bg-elevated); border-radius: 50px;
    border: 1px solid var(--border-subtle);
}
.admin-info strong { color: var(--accent-light); }
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: var(--radius-sm); font-weight: 600;
    font-size: 0.8rem; cursor: pointer; transition: all 0.2s ease; border: none;
    text-decoration: none; text-transform: uppercase; letter-spacing: 0.05em;
}
.btn-outline { background: transparent; border: 1px solid var(--border-strong); color: var(--text-secondary); }
.btn-outline:hover { background: var(--bg-elevated); color: var(--accent-light); border-color: var(--accent); }
.btn-danger-outline { background: transparent; border: 1px solid var(--danger-border); color: var(--danger); }
.btn-danger-outline:hover { background: var(--danger-bg); color: var(--danger); }

/* Container */
.container-xl { max-width: 1500px; margin: 0 auto; padding: 24px 32px 60px; }

/* Breadcrumb */
.breadcrumb {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.85rem; color: var(--text-tertiary); margin-bottom: 20px;
}
.breadcrumb a { color: var(--accent-light); text-decoration: none; transition: opacity 0.2s; }
.breadcrumb a:hover { opacity: 0.8; }
.breadcrumb i { font-size: 0.7rem; }

/* Page Header */
.page-header { 
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 28px; flex-wrap: wrap; gap: 16px;
}
.page-header h2 { 
    font-size: 1.5rem; font-weight: 700; color: var(--text-primary); 
    display: flex; align-items: center; gap: 12px;
}
.page-header h2 i { color: var(--accent); }

/* Stats Grid */
.stats-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
    gap: 16px; margin-bottom: 28px; 
}
.stat-card {
    background: var(--bg-card);
    backdrop-filter: blur(12px);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    padding: 20px;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 4px; height: 100%;
    background: var(--accent);
    opacity: 0.6;
}
.stat-card:hover { 
    transform: translateY(-2px); 
    border-color: var(--accent-border); 
    box-shadow: 0 8px 24px rgba(6, 182, 212, 0.15);
}
.stat-card.purple::before { background: var(--accent-2); }
.stat-card.purple:hover { box-shadow: 0 8px 24px var(--accent-2-glow); }
.stat-card.green::before { background: var(--success); }
.stat-card.green:hover { box-shadow: 0 8px 24px rgba(16, 185, 129, 0.15); }
.stat-card.orange::before { background: var(--warning); }
.stat-card.orange:hover { box-shadow: 0 8px 24px rgba(245, 158, 11, 0.15); }
.stat-card.red::before { background: var(--danger); }
.stat-card.red:hover { box-shadow: 0 8px 24px rgba(239, 68, 68, 0.15); }

.stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.stat-label { 
    font-size: 0.7rem; font-weight: 600; text-transform: uppercase; 
    letter-spacing: 0.08em; color: var(--text-tertiary); 
}
.stat-icon {
    width: 32px; height: 32px; border-radius: 8px;
    background: var(--accent-glow); color: var(--accent);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem;
}
.stat-card.purple .stat-icon { background: var(--accent-2-glow); color: var(--accent-2); }
.stat-card.green .stat-icon { background: var(--success-bg); color: var(--success); }
.stat-card.orange .stat-icon { background: var(--warning-bg); color: var(--warning); }
.stat-card.red .stat-icon { background: var(--danger-bg); color: var(--danger); }

.stat-value { 
    font-size: 1.5rem; font-weight: 700; color: var(--text-primary); 
    margin-bottom: 4px; line-height: 1.2;
}
.stat-value.accent { color: var(--accent-light); }
.stat-value.purple { color: var(--accent-2); }
.stat-value.green { color: var(--success); }
.stat-value.orange { color: var(--warning); }
.stat-hint { font-size: 0.72rem; color: var(--text-muted); font-weight: 500; }

/* Filter Bar */
.filter-bar {
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    padding: 20px 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-label {
    font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.08em; color: var(--text-tertiary);
}
.filter-input {
    padding: 10px 14px;
    background: var(--bg-elevated); border: 1px solid var(--border-medium);
    border-radius: var(--radius-sm); color: var(--text-primary); font-size: 0.9rem;
    transition: all 0.2s ease; font-family: 'Inter', sans-serif;
}
.filter-input:focus { 
    outline: none; border-color: var(--accent); 
    box-shadow: 0 0 0 3px var(--accent-glow); 
}
.btn-primary {
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
    color: var(--bg-base); padding: 10px 20px;
    font-weight: 700; border: none;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px var(--accent-glow);
}
.btn-secondary {
    background: var(--bg-elevated); color: var(--text-secondary);
    border: 1px solid var(--border-medium); padding: 10px 20px;
}
.btn-secondary:hover { background: var(--bg-card-hover); color: var(--text-primary); }

/* Cards */
.card {
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    margin-bottom: 24px;
    overflow: hidden;
}
.card-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border-subtle);
    background: rgba(6, 182, 212, 0.03);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px;
}
.card-title { 
    font-size: 0.95rem; font-weight: 700; color: var(--text-primary); 
    display: flex; align-items: center; gap: 10px;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.card-title i { color: var(--accent); }
.card-body { padding: 24px; }

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}
.chart-container {
    position: relative;
    height: 320px;
}

/* Forms */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-label {
    font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.08em; color: var(--text-tertiary);
}
.form-input, .form-select {
    padding: 10px 14px;
    background: var(--bg-elevated); border: 1px solid var(--border-medium);
    border-radius: var(--radius-sm); color: var(--text-primary); font-size: 0.9rem;
    transition: all 0.2s ease; font-family: inherit;
}
.form-input:focus, .form-select:focus {
    outline: none; border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}
.form-select { cursor: pointer; }
.form-select option { background: var(--bg-surface); }

/* Tables */
.table-responsive { overflow-x: auto; }
.scrollable-table { max-height: 500px; overflow-y: auto; }
.scrollable-table::-webkit-scrollbar { width: 8px; }
.scrollable-table::-webkit-scrollbar-track { background: var(--bg-surface); }
.scrollable-table::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 4px; }
table { width: 100%; border-collapse: collapse; min-width: 800px; }
thead { position: sticky; top: 0; z-index: 5; background: var(--bg-surface); }
th {
    padding: 12px 16px; text-align: left;
    font-size: 0.7rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.08em;
    color: var(--accent-light);
    border-bottom: 2px solid var(--accent-border);
    white-space: nowrap;
}
td {
    padding: 12px 16px; font-size: 0.85rem; color: var(--text-secondary);
    border-bottom: 1px solid var(--border-subtle);
}
tbody tr { transition: background 0.15s ease; }
tbody tr:hover { background: var(--bg-elevated); }
tbody tr:hover td { color: var(--text-primary); }

.cell-money { color: var(--text-primary); font-weight: 600; font-family: 'JetBrains Mono', monospace; }
.cell-date { color: var(--text-muted); font-size: 0.8rem; font-family: 'JetBrains Mono', monospace; }
.cell-source {
    display: inline-block; padding: 3px 8px;
    background: var(--bg-elevated); border-radius: 4px;
    font-size: 0.75rem; font-weight: 600; color: var(--accent-light);
    text-transform: uppercase; letter-spacing: 0.05em;
}

/* Action buttons in table */
.btn-action {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 6px;
    transition: all 0.2s ease; text-decoration: none;
    border: none; cursor: pointer;
}
.btn-delete {
    background: var(--danger-bg); color: var(--danger);
    border: 1px solid var(--danger-border);
}
.btn-delete:hover { 
    background: var(--danger); color: white;
}

/* Empty State */
.empty-state {
    text-align: center; padding: 40px 20px; color: var(--text-tertiary);
}
.empty-state i { font-size: 2.5rem; margin-bottom: 15px; opacity: 0.4; }

/* Toast */
.toast-container {
    position: fixed; top: 90px; right: 24px; z-index: 9999;
    display: flex; flex-direction: column; gap: 10px; pointer-events: none;
}
.toast {
    background: var(--bg-surface); border: 1px solid var(--accent-border);
    border-left: 4px solid var(--accent); border-radius: var(--radius-md);
    padding: 14px 18px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
    display: flex; align-items: center; gap: 12px; min-width: 300px; max-width: 400px;
    transform: translateX(450px); opacity: 0; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: auto;
}
.toast.show { transform: translateX(0); opacity: 1; }
.toast.success { border-left-color: var(--success); }
.toast.error { border-left-color: var(--danger); }
.toast-icon {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--accent-glow); color: var(--accent);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.toast.success .toast-icon { background: var(--success-bg); color: var(--success); }
.toast.error .toast-icon { background: var(--danger-bg); color: var(--danger); }
.toast-content { flex: 1; text-align: left; }
.toast-title { font-weight: 600; font-size: 0.9rem; color: var(--text-primary); margin-bottom: 2px; }
.toast-message { font-size: 0.8rem; color: var(--text-secondary); }
.toast-close {
    background: none; border: none; color: var(--text-tertiary);
    cursor: pointer; padding: 4px; font-size: 1rem;
}
.toast-close:hover { color: var(--text-primary); }

/* Responsive */
@media (max-width: 1024px) {
    .charts-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .topbar { padding: 0 16px; height: 60px; }
    .admin-info { display: none; }
    .brand-subtitle { display: none; }
    .container-xl { padding: 20px 16px 40px; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filter-group { width: 100%; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <a href="admin_dashboard.php" class="brand">
        <div class="brand-icon"><i class="fas fa-terminal"></i></div>
        <div class="brand-text">
            <span class="brand-title">GIBAL ADMIN</span>
            <span class="brand-subtitle">Fee Management</span>
        </div>
    </a>
    <div class="topbar-actions">
        <div class="admin-info">
            <i class="fas fa-user-shield" style="color: var(--accent); margin-right: 6px;"></i>
            <strong><?=htmlspecialchars($_SESSION['admin_username'] ?? 'admin')?></strong>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <a href="admin_logout.php" class="btn btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Main Content -->
<div class="container-xl">
    
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <i class="fas fa-chevron-right"></i>
        <span>Fee Management</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <h2><i class="fas fa-coins"></i> Fee Collection Overview</h2>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">Today's Fees</div>
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            </div>
            <div class="stat-value accent">Ksh <?=number_format($todays_fees, 2)?></div>
            <div class="stat-hint"><?=date('M d, Y')?></div>
        </div>
        
        <div class="stat-card purple">
            <div class="stat-header">
                <div class="stat-label">This Week</div>
                <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
            </div>
            <div class="stat-value purple">Ksh <?=number_format($week_fees, 2)?></div>
            <div class="stat-hint">Since <?=date('M d', strtotime($week_start))?></div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-header">
                <div class="stat-label">This Month</div>
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            </div>
            <div class="stat-value green">Ksh <?=number_format($month_fees, 2)?></div>
            <div class="stat-hint">Since <?=date('M d', strtotime($month_start))?></div>
        </div>
        
        <div class="stat-card orange">
            <div class="stat-header">
                <div class="stat-label">Selected Range</div>
                <div class="stat-icon"><i class="fas fa-filter"></i></div>
            </div>
            <div class="stat-value orange">Ksh <?=number_format($total_fees_range, 2)?></div>
            <div class="stat-hint"><?=count($fees)?> entries</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">All-Time Total</div>
                <div class="stat-icon"><i class="fas fa-infinity"></i></div>
            </div>
            <div class="stat-value accent">Ksh <?=number_format($total_fees_all, 2)?></div>
            <div class="stat-hint">Lifetime collection</div>
        </div>
        
        <div class="stat-card red">
            <div class="stat-header">
                <div class="stat-label">Withdrawn to Wallet</div>
                <div class="stat-icon"><i class="fas fa-arrow-circle-down"></i></div>
            </div>
            <div class="stat-value" style="color: var(--danger);">Ksh <?=number_format($total_fees_withdrawn, 2)?></div>
            <div class="stat-hint">Transferred out</div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-header">
                <div class="stat-label">Remaining in System</div>
                <div class="stat-icon"><i class="fas fa-piggy-bank"></i></div>
            </div>
            <div class="stat-value green">Ksh <?=number_format($remaining_fees, 2)?></div>
            <div class="stat-hint">Available balance</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; width: 100%;">
            <div class="filter-group">
                <label class="filter-label">Start Date</label>
                <input type="date" name="start_date" class="filter-input" value="<?=htmlspecialchars($start_date)?>" required>
            </div>
            <div class="filter-group">
                <label class="filter-label">End Date</label>
                <input type="date" name="end_date" class="filter-input" value="<?=htmlspecialchars($end_date)?>" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filter</button>
            <a href="user_fees.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
        </form>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-chart-area"></i> Daily Fee Collection</div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-chart-pie"></i> Source Breakdown</div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="sourceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Fee Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-plus-circle"></i> Add Manual Fee Entry</div>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Amount (Ksh)</label>
                        <input type="number" step="0.01" min="0.01" name="fee_amount" class="form-input" placeholder="e.g., 150.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" name="fee_date" class="form-input" value="<?=date('Y-m-d')?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Source</label>
                        <select name="source" class="form-select" required>
                            <option value="investment">Investment Fee</option>
                            <option value="withdrawal">Withdrawal Fee</option>
                            <option value="deposit">Deposit Fee</option>
                            <option value="manual">Manual Adjustment</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-input" placeholder="Optional note...">
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" name="add_fee" class="btn btn-primary"><i class="fas fa-plus"></i> Add Fee Entry</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Fee Entries Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-list"></i> Fee Entries (<?=date('M d', strtotime($start_date))?> - <?=date('M d, Y', strtotime($end_date))?>)</div>
            <div style="color: var(--text-tertiary); font-size: 0.85rem;">
                Total: <strong style="color: var(--accent-light); font-family: 'JetBrains Mono', monospace;">Ksh <?=number_format($total_fees_range, 2)?></strong>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($fees)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No fee entries found for the selected date range.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <div class="scrollable-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Source</th>
                                <th>Description</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fees as $fee): ?>
                            <tr>
                                <td style="color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">#<?=$fee['id']?></td>
                                <td class="cell-date"><?=htmlspecialchars($fee['fee_date'])?></td>
                                <td class="cell-money">Ksh <?=number_format($fee['fee_amount'], 2)?></td>
                                <td><span class="cell-source"><?=htmlspecialchars(ucfirst($fee['source'] ?? 'investment'))?></span></td>
                                <td style="color: var(--text-secondary);"><?=htmlspecialchars($fee['description'] ?? '—')?></td>
                                <td class="cell-date"><?=date('M d, H:i', strtotime($fee['created_at']))?></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this fee entry? This cannot be undone.');">
                                        <input type="hidden" name="fee_id" value="<?=$fee['id']?>">
                                        <button type="submit" name="delete_fee" class="btn-action btn-delete" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
// Daily Chart
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
const dailyChart = new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: <?=json_encode($chart_labels)?>,
        datasets: [{
            label: 'Daily Fees (Ksh)',
            data: <?=json_encode($chart_data)?>,
            borderColor: '#06b6d4',
            backgroundColor: 'rgba(6, 182, 212, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#06b6d4',
            pointBorderColor: '#0b1120',
            pointBorderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { 
                labels: { 
                    color: '#cbd5e1', 
                    font: { family: 'Inter', size: 12 }
                } 
            },
            tooltip: {
                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                titleColor: '#f1f5f9',
                bodyColor: '#cbd5e1',
                borderColor: 'rgba(6, 182, 212, 0.3)',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        return 'Ksh ' + context.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: { 
            x: { 
                ticks: { color: '#64748b', font: { family: 'Inter', size: 10 } }, 
                grid: { color: 'rgba(148, 163, 184, 0.05)' } 
            }, 
            y: { 
                ticks: { 
                    color: '#64748b', 
                    font: { family: 'JetBrains Mono', size: 10 },
                    callback: function(value) {
                        return 'Ksh ' + value.toLocaleString();
                    }
                }, 
                grid: { color: 'rgba(148, 163, 184, 0.05)' } 
            } 
        }
    }
});

// Source Breakdown Chart
const sourceCtx = document.getElementById('sourceChart').getContext('2d');
const sourceLabels = <?=json_encode(array_keys($source_breakdown))?>;
const sourceData = <?=json_encode(array_values($source_breakdown))?>;
const sourceColors = ['#06b6d4', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#3b82f6'];

const sourceChart = new Chart(sourceCtx, {
    type: 'doughnut',
    data: {
        labels: sourceLabels.map(l => l.charAt(0).toUpperCase() + l.slice(1)),
        datasets: [{
            data: sourceData,
            backgroundColor: sourceColors.slice(0, sourceLabels.length),
            borderColor: '#0b1120',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#cbd5e1',
                    font: { family: 'Inter', size: 11 },
                    padding: 15,
                    usePointStyle: true
                }
            },
            tooltip: {
                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                titleColor: '#f1f5f9',
                bodyColor: '#cbd5e1',
                borderColor: 'rgba(6, 182, 212, 0.3)',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': Ksh ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Toast System
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    
    const icons = {
        info: 'fa-info-circle',
        success: 'fa-check-circle',
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
    }, 5000);
}

<?php if (!empty($toast_message)): ?>
window.addEventListener('load', () => {
    showToast("<?=addslashes($toast_message)?>", "<?=$toast_type?>");
});
<?php endif; ?>
</script>
</body>
</html>