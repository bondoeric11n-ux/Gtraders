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

$toast_message = "";
$toast_type = "info";

// Validate user ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit();
}
$user_id = (int)$_GET['id'];

// Fetch user data
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$user) {
    header("Location: admin_dashboard.php");
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $toast_message = "Invalid request. Please try again.";
        $toast_type = "error";
    } else {
        // Regenerate token after use
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // ACTION 1: Update Profile Info
        if (isset($_POST['update_profile'])) {
            $name = trim($_POST['name']);
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);

            if (empty($name) || empty($username) || empty($email)) {
                $toast_message = "Name, username, and email are required.";
                $toast_type = "error";
            } else {
                $update = $conn->prepare("UPDATE users SET name=?, username=?, email=?, phone=? WHERE id=?");
                $update->bind_param("ssssi", $name, $username, $email, $phone, $user_id);
                if ($update->execute()) {
                    // Log action
                    logAdminAction("UPDATE_PROFILE", "Updated profile for user #$user_id");
                    $toast_message = "Profile updated successfully.";
                    $toast_type = "success";
                    // Refresh user data
                    $user['name'] = $name;
                    $user['username'] = $username;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                } else {
                    $toast_message = "Failed to update profile: " . $conn->error;
                    $toast_type = "error";
                }
            }
        }

        // ACTION 2: Adjust Balances
        if (isset($_POST['adjust_balance'])) {
            $new_account_balance = floatval($_POST['account_balance']);
            $new_invested_balance = floatval($_POST['invested_balance']);
            $new_referral_wallet = floatval($_POST['referral_wallet']);
            $reason = trim($_POST['reason']);

            if ($new_account_balance < 0 || $new_invested_balance < 0 || $new_referral_wallet < 0) {
                $toast_message = "Balances cannot be negative.";
                $toast_type = "error";
            } else {
                $conn->begin_transaction();
                try {
                    $update = $conn->prepare("UPDATE users SET account_balance=?, invested_balance=?, referral_wallet=? WHERE id=?");
                    $update->bind_param("dddi", $new_account_balance, $new_invested_balance, $new_referral_wallet, $user_id);
                    $update->execute();

                    // Log the balance change with reason
                    logAdminAction("ADJUST_BALANCE", "Adjusted balances for user #$user_id. Reason: $reason | Account: {$user['account_balance']}→$new_account_balance | Invested: {$user['invested_balance']}→$new_invested_balance | Referral: {$user['referral_wallet']}→$new_referral_wallet");

                    $conn->commit();
                    $toast_message = "Balances adjusted successfully.";
                    $toast_type = "success";

                    // Refresh user data
                    $user['account_balance'] = $new_account_balance;
                    $user['invested_balance'] = $new_invested_balance;
                    $user['referral_wallet'] = $new_referral_wallet;
                } catch (Exception $e) {
                    $conn->rollback();
                    $toast_message = "Failed to adjust balances: " . $e->getMessage();
                    $toast_type = "error";
                }
            }
        }

        // ACTION 3: Toggle Account Status
        if (isset($_POST['toggle_status'])) {
            $new_status = $_POST['new_status'];
            if (!in_array($new_status, ['active', 'suspended', 'banned'])) {
                $toast_message = "Invalid status.";
                $toast_type = "error";
            } else {
                $update = $conn->prepare("UPDATE users SET status=? WHERE id=?");
                $update->bind_param("si", $new_status, $user_id);
                if ($update->execute()) {
                    logAdminAction("CHANGE_STATUS", "Changed status of user #$user_id to '$new_status'");
                    $toast_message = "Account status changed to " . ucfirst($new_status) . ".";
                    $toast_type = "success";
                    $user['status'] = $new_status;
                } else {
                    $toast_message = "Failed to change status.";
                    $toast_type = "error";
                }
            }
        }

        // ACTION 4: Reset Password
        if (isset($_POST['reset_password'])) {
            $new_password = $_POST['new_password'];
            if (strlen($new_password) < 6) {
                $toast_message = "Password must be at least 6 characters.";
                $toast_type = "error";
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $update->bind_param("si", $hashed, $user_id);
                if ($update->execute()) {
                    logAdminAction("RESET_PASSWORD", "Reset password for user #$user_id");
                    $toast_message = "Password reset successfully. User should be notified.";
                    $toast_type = "success";
                } else {
                    $toast_message = "Failed to reset password.";
                    $toast_type = "error";
                }
            }
        }
    }
}

// Fetch related data
// User's investments
$inv_stmt = $conn->prepare("SELECT i.*, p.name AS plan_name FROM investments i LEFT JOIN plans p ON i.plan_id = p.id WHERE i.user_id = ? ORDER BY i.start_date DESC LIMIT 20");
$inv_stmt->bind_param("i", $user_id);
$inv_stmt->execute();
$investments = $inv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$inv_stmt->close();

// User's withdrawals
$wd_stmt = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$wd_stmt->bind_param("i", $user_id);
$wd_stmt->execute();
$withdrawals = $wd_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$wd_stmt->close();

// User's deposits
$dep_stmt = $conn->prepare("SELECT * FROM btc_deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$dep_stmt->bind_param("i", $user_id);
$dep_stmt->execute();
$deposits = $dep_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dep_stmt->close();

// Totals
$total_deposits = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM btc_deposits WHERE user_id=$user_id AND status='completed'")->fetch_assoc()['total'];
$total_withdrawals = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM withdrawals WHERE user_id=$user_id AND status='approved'")->fetch_assoc()['total'];

// Helper function for audit logging
function logAdminAction($action, $details) {
    global $conn;
    $admin_id = $_SESSION['admin_id'] ?? 0;
    $admin_username = $_SESSION['admin_username'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Create table if not exists
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

// Status badge helper
function getStatusBadge($status) {
    $status = strtolower($status ?: 'active');
    $classes = [
        'active' => 'badge-active',
        'suspended' => 'badge-warning',
        'banned' => 'badge-danger',
        'pending' => 'badge-pending',
        'approved' => 'badge-approved',
        'rejected' => 'badge-rejected',
        'completed' => 'badge-approved',
        'matured' => 'badge-info'
    ];
    $class = $classes[$status] ?? 'badge-pending';
    return '<span class="badge ' . $class . '">' . htmlspecialchars(ucfirst($status)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Edit User #<?=$user_id?> | GIBAL Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* Same Admin Command Center Theme */
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
.cell-money, .stat-value, .badge-live {
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
.container-xl { max-width: 1400px; margin: 0 auto; padding: 24px 32px 60px; }

/* Breadcrumb */
.breadcrumb {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.85rem; color: var(--text-tertiary); margin-bottom: 20px;
}
.breadcrumb a { color: var(--accent-light); text-decoration: none; transition: opacity 0.2s; }
.breadcrumb a:hover { opacity: 0.8; }
.breadcrumb i { font-size: 0.7rem; }

/* User Header Card */
.user-header {
    background: var(--bg-card);
    backdrop-filter: blur(12px);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    padding: 28px;
    margin-bottom: 24px;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 24px;
    align-items: center;
    position: relative;
    overflow: hidden;
}
.user-header::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 4px;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
}
.user-avatar {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
    display: flex; align-items: center; justify-content: center;
    color: var(--bg-base); font-weight: 800; font-size: 2rem;
    box-shadow: 0 0 30px var(--accent-glow);
}
.user-info h1 {
    font-size: 1.5rem; font-weight: 700; color: var(--text-primary);
    margin-bottom: 4px; display: flex; align-items: center; gap: 12px;
}
.user-meta {
    display: flex; flex-wrap: wrap; gap: 16px;
    font-size: 0.85rem; color: var(--text-secondary);
}
.user-meta-item { display: flex; align-items: center; gap: 6px; }
.user-meta-item i { color: var(--accent); font-size: 0.85rem; }
.user-actions { display: flex; flex-direction: column; gap: 8px; }

/* Quick Stats */
.quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px; margin-bottom: 24px;
}
.quick-stat {
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    padding: 16px 20px;
    position: relative;
    overflow: hidden;
}
.quick-stat::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 3px; height: 100%;
    background: var(--accent);
}
.quick-stat.purple::before { background: var(--accent-2); }
.quick-stat.green::before { background: var(--success); }
.quick-stat.orange::before { background: var(--warning); }
.quick-stat-label {
    font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.08em; color: var(--text-tertiary); margin-bottom: 6px;
}
.quick-stat-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.3rem; font-weight: 700; color: var(--text-primary);
}
.quick-stat-value.accent { color: var(--accent-light); }
.quick-stat-value.purple { color: var(--accent-2); }
.quick-stat-value.green { color: var(--success); }

/* Tabs */
.tabs {
    display: flex; gap: 4px;
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    padding: 6px; margin-bottom: 24px;
    overflow-x: auto;
}
.tab {
    flex: 1; min-width: 140px;
    padding: 10px 16px;
    background: transparent; border: none;
    color: var(--text-secondary);
    font-weight: 600; font-size: 0.85rem;
    cursor: pointer; border-radius: var(--radius-sm);
    transition: all 0.2s ease;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    text-transform: uppercase; letter-spacing: 0.03em;
}
.tab:hover { background: var(--bg-elevated); color: var(--text-primary); }
.tab.active {
    background: var(--accent-glow);
    color: var(--accent-light);
    border: 1px solid var(--accent-border);
}

/* Tab Content */
.tab-content { display: none; animation: fadeIn 0.3s ease; }
.tab-content.active { display: block; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Cards */
.card {
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    margin-bottom: 20px;
    overflow: hidden;
}
.card-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border-subtle);
    background: rgba(6, 182, 212, 0.03);
    display: flex; align-items: center; justify-content: space-between;
}
.card-title {
    font-size: 0.95rem; font-weight: 700; color: var(--text-primary);
    display: flex; align-items: center; gap: 10px;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.card-title i { color: var(--accent); }
.card-body { padding: 24px; }

/* Forms */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}
.form-group { margin-bottom: 20px; }
.form-group.full-width { grid-column: 1 / -1; }
.form-label {
    display: block; font-size: 0.75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--text-tertiary); margin-bottom: 8px;
}
.form-input, .form-select, .form-textarea {
    width: 100%; padding: 12px 14px;
    background: var(--bg-elevated); border: 1px solid var(--border-medium);
    border-radius: var(--radius-sm); color: var(--text-primary);
    font-size: 0.9rem; transition: all 0.2s ease; font-family: inherit;
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none; border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}
.form-input::placeholder { color: var(--text-muted); }
.form-textarea { resize: vertical; min-height: 80px; }
.form-hint {
    font-size: 0.75rem; color: var(--text-muted); margin-top: 6px;
    display: flex; align-items: center; gap: 6px;
}
.form-hint i { color: var(--accent); }

/* Buttons */
.btn-primary {
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
    color: var(--bg-base); padding: 12px 24px;
    font-weight: 700; border: none;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px var(--accent-glow);
}
.btn-secondary {
    background: var(--bg-elevated); color: var(--text-secondary);
    border: 1px solid var(--border-medium); padding: 12px 24px;
}
.btn-secondary:hover { background: var(--bg-card-hover); color: var(--text-primary); }
.btn-danger {
    background: var(--danger-bg); color: var(--danger);
    border: 1px solid var(--danger-border); padding: 12px 24px;
}
.btn-danger:hover { background: var(--danger); color: white; }
.btn-warning {
    background: var(--warning-bg); color: var(--warning);
    border: 1px solid var(--warning-border); padding: 12px 24px;
}
.btn-warning:hover { background: var(--warning); color: var(--bg-base); }
.btn-success {
    background: var(--success-bg); color: var(--success);
    border: 1px solid var(--success-border); padding: 12px 24px;
}
.btn-success:hover { background: var(--success); color: var(--bg-base); }

.btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; }

/* Tables */
.table-responsive { overflow-x: auto; }
.scrollable-table { max-height: 400px; overflow-y: auto; }
.scrollable-table::-webkit-scrollbar { width: 8px; }
.scrollable-table::-webkit-scrollbar-track { background: var(--bg-surface); }
.scrollable-table::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 4px; }
table { width: 100%; border-collapse: collapse; min-width: 700px; }
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
tbody tr:hover { background: var(--bg-elevated); }
tbody tr:hover td { color: var(--text-primary); }

.cell-money { color: var(--text-primary); font-weight: 600; font-family: 'JetBrains Mono', monospace; }
.cell-interest { color: var(--success); font-weight: 700; }
.cell-fee { color: var(--text-muted); }
.cell-date { color: var(--text-muted); font-size: 0.8rem; font-family: 'JetBrains Mono', monospace; }

/* Badges */
.badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 4px;
    font-size: 0.7rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em;
    font-family: 'JetBrains Mono', monospace;
}
.badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.badge-active, .badge-approved, .badge-completed { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border); }
.badge-pending, .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning-border); }
.badge-rejected, .badge-failed, .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger-border); }
.badge-info, .badge-matured { background: var(--info-bg); color: var(--info); border: 1px solid var(--info-border); }

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
.toast.warning { border-left-color: var(--warning); }
.toast-icon {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--accent-glow); color: var(--accent);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.toast.success .toast-icon { background: var(--success-bg); color: var(--success); }
.toast.error .toast-icon { background: var(--danger-bg); color: var(--danger); }
.toast.warning .toast-icon { background: var(--warning-bg); color: var(--warning); }
.toast-content { flex: 1; text-align: left; }
.toast-title { font-weight: 600; font-size: 0.9rem; color: var(--text-primary); margin-bottom: 2px; }
.toast-message { font-size: 0.8rem; color: var(--text-secondary); }
.toast-close {
    background: none; border: none; color: var(--text-tertiary);
    cursor: pointer; padding: 4px; font-size: 1rem;
}
.toast-close:hover { color: var(--text-primary); }

/* Empty State */
.empty-state {
    text-align: center; padding: 40px 20px; color: var(--text-tertiary);
}
.empty-state i { font-size: 2.5rem; margin-bottom: 15px; opacity: 0.4; }

/* Responsive */
@media (max-width: 1024px) {
    .user-header { grid-template-columns: 1fr; text-align: center; }
    .user-avatar { margin: 0 auto; }
    .user-meta { justify-content: center; }
    .user-actions { flex-direction: row; justify-content: center; }
}
@media (max-width: 768px) {
    .topbar { padding: 0 16px; height: 60px; }
    .admin-info { display: none; }
    .brand-subtitle { display: none; }
    .container-xl { padding: 20px 16px 40px; }
    .tabs { flex-wrap: nowrap; }
    .tab { min-width: 120px; font-size: 0.75rem; }
    .quick-stats { grid-template-columns: 1fr 1fr; }
    .form-grid { grid-template-columns: 1fr; }
    .btn-group { flex-direction: column; }
    .btn-group .btn { width: 100%; justify-content: center; }
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
            <span class="brand-subtitle">User Management</span>
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
        <a href="admin_dashboard.php">Users</a>
        <i class="fas fa-chevron-right"></i>
        <span>Edit User #<?=$user_id?></span>
    </div>

    <!-- User Header -->
    <div class="user-header">
        <div class="user-avatar"><?=strtoupper(substr($user['name'] ?: $user['username'], 0, 1))?></div>
        <div class="user-info">
            <h1>
                <?=htmlspecialchars($user['name'] ?: $user['username'])?>
                <?=getStatusBadge($user['status'] ?? 'active')?>
            </h1>
            <div class="user-meta">
                <div class="user-meta-item"><i class="fas fa-at"></i> <?=htmlspecialchars($user['email'])?></div>
                <div class="user-meta-item"><i class="fas fa-user"></i> @<?=htmlspecialchars($user['username'])?></div>
                <div class="user-meta-item"><i class="fas fa-phone"></i> <?=htmlspecialchars($user['phone'] ?: 'N/A')?></div>
                <div class="user-meta-item"><i class="fas fa-calendar"></i> Joined <?=date('M d, Y', strtotime($user['created_at']))?></div>
                <div class="user-meta-item"><i class="fas fa-fingerprint"></i> ID: #<?=$user_id?></div>
            </div>
        </div>
        <div class="user-actions">
            <a href="mailto:<?=htmlspecialchars($user['email'])?>" class="btn btn-outline"><i class="fas fa-envelope"></i> Email</a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="quick-stat">
            <div class="quick-stat-label">Account Balance</div>
            <div class="quick-stat-value accent">Ksh <?=number_format($user['account_balance'] ?? 0, 2)?></div>
        </div>
        <div class="quick-stat purple">
            <div class="quick-stat-label">Invested Balance</div>
            <div class="quick-stat-value purple">Ksh <?=number_format($user['invested_balance'] ?? 0, 2)?></div>
        </div>
        <div class="quick-stat green">
            <div class="quick-stat-label">Referral Wallet</div>
            <div class="quick-stat-value green">Ksh <?=number_format($user['referral_wallet'] ?? 0, 2)?></div>
        </div>
        <div class="quick-stat orange">
            <div class="quick-stat-label">Total Deposits</div>
            <div class="quick-stat-value">Ksh <?=number_format($total_deposits, 2)?></div>
        </div>
        <div class="quick-stat">
            <div class="quick-stat-label">Total Withdrawals</div>
            <div class="quick-stat-value">Ksh <?=number_format($total_withdrawals, 2)?></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" data-tab="profile"><i class="fas fa-user-edit"></i> Profile</button>
        <button class="tab" data-tab="finances"><i class="fas fa-coins"></i> Finances</button>
        <button class="tab" data-tab="status"><i class="fas fa-shield-alt"></i> Status</button>
        <button class="tab" data-tab="investments"><i class="fas fa-chart-line"></i> Investments</button>
        <button class="tab" data-tab="transactions"><i class="fas fa-exchange-alt"></i> Transactions</button>
    </div>

    <!-- Tab 1: Profile -->
    <div class="tab-content active" id="tab-profile">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user-edit"></i> Edit Profile Information</div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-input" value="<?=htmlspecialchars($user['name'] ?? '')?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-input" value="<?=htmlspecialchars($user['username'] ?? '')?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input" value="<?=htmlspecialchars($user['email'] ?? '')?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-input" value="<?=htmlspecialchars($user['phone'] ?? '')?>">
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                        <a href="admin_dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab 2: Finances -->
    <div class="tab-content" id="tab-finances">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-coins"></i> Adjust User Balances</div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Account Balance (Ksh)</label>
                            <input type="number" step="0.01" name="account_balance" class="form-input" value="<?=number_format($user['account_balance'] ?? 0, 2, '.', '')?>" required>
                            <div class="form-hint"><i class="fas fa-info-circle"></i> Current: Ksh <?=number_format($user['account_balance'] ?? 0, 2)?></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Invested Balance (Ksh)</label>
                            <input type="number" step="0.01" name="invested_balance" class="form-input" value="<?=number_format($user['invested_balance'] ?? 0, 2, '.', '')?>" required>
                            <div class="form-hint"><i class="fas fa-info-circle"></i> Current: Ksh <?=number_format($user['invested_balance'] ?? 0, 2)?></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Referral Wallet (Ksh)</label>
                            <input type="number" step="0.01" name="referral_wallet" class="form-input" value="<?=number_format($user['referral_wallet'] ?? 0, 2, '.', '')?>" required>
                            <div class="form-hint"><i class="fas fa-info-circle"></i> Current: Ksh <?=number_format($user['referral_wallet'] ?? 0, 2)?></div>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Reason for Adjustment (Required)</label>
                            <textarea name="reason" class="form-textarea" placeholder="e.g., Bonus credit, correction, manual adjustment..." required></textarea>
                            <div class="form-hint"><i class="fas fa-shield-alt"></i> This will be logged in the audit trail.</div>
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="adjust_balance" class="btn btn-primary" onclick="return confirm('Are you sure you want to adjust this user\\'s balances? This action will be logged.');"><i class="fas fa-save"></i> Apply Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab 3: Status -->
    <div class="tab-content" id="tab-status">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-shield-alt"></i> Account Status</div>
            </div>
            <div class="card-body">
                <p style="color: var(--text-secondary); margin-bottom: 20px;">
                    Current Status: <?=getStatusBadge($user['status'] ?? 'active')?>
                </p>
                <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                    <input type="hidden" name="new_status" value="active">
                    <button type="submit" name="toggle_status" class="btn btn-success" <?=($user['status'] ?? 'active') === 'active' ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''?>>
                        <i class="fas fa-check-circle"></i> Activate Account
                    </button>
                </form>
                <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                    <input type="hidden" name="new_status" value="suspended">
                    <button type="submit" name="toggle_status" class="btn btn-warning" <?=($user['status'] ?? 'active') === 'suspended' ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''?>>
                        <i class="fas fa-pause-circle"></i> Suspend Account
                    </button>
                </form>
                <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                    <input type="hidden" name="new_status" value="banned">
                    <button type="submit" name="toggle_status" class="btn btn-danger" onclick="return confirm('Are you sure you want to BAN this user? They will lose access immediately.');" <?=($user['status'] ?? 'active') === 'banned' ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''?>>
                        <i class="fas fa-ban"></i> Ban User
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-key"></i> Reset Password</div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" placeholder="Enter new password (min 6 characters)" minlength="6" required>
                        <div class="form-hint"><i class="fas fa-exclamation-triangle"></i> User will need to be notified of the new password.</div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="reset_password" class="btn btn-danger" onclick="return confirm('Reset this user\\'s password?');"><i class="fas fa-key"></i> Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab 4: Investments -->
    <div class="tab-content" id="tab-investments">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-chart-line"></i> User Investments</div>
                <span class="badge badge-info"><?=count($investments)?> total</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($investments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <p>No investments found for this user.</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <div class="scrollable-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Plan</th>
                                    <th>Amount</th>
                                    <th>Net</th>
                                    <th>Fee</th>
                                    <th>Interest</th>
                                    <th>Status</th>
                                    <th>Start</th>
                                    <th>End</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($investments as $inv): 
                                    $expected_interest = round(($inv['net_amount'] * ($inv['interest_rate'] ?? 0)) / 100, 2);
                                ?>
                                <tr>
                                    <td><?=htmlspecialchars($inv['plan_name'] ?? 'N/A')?></td>
                                    <td class="cell-money">Ksh <?=number_format($inv['amount'], 2)?></td>
                                    <td class="cell-money">Ksh <?=number_format($inv['net_amount'], 2)?></td>
                                    <td class="cell-money cell-fee">Ksh <?=number_format($inv['fee'] ?? 0, 2)?></td>
                                    <td class="cell-money cell-interest">+Ksh <?=number_format($expected_interest, 2)?></td>
                                    <td><?=getStatusBadge($inv['status'])?></td>
                                    <td class="cell-date"><?=htmlspecialchars($inv['start_date'])?></td>
                                    <td class="cell-date"><?=htmlspecialchars($inv['end_date'] ?? 'N/A')?></td>
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

    <!-- Tab 5: Transactions -->
    <div class="tab-content" id="tab-transactions">
        <!-- Deposits -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-arrow-down"></i> Recent Deposits</div>
                <span class="badge badge-info"><?=count($deposits)?> shown</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($deposits)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No deposits found.</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <div class="scrollable-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deposits as $dep): ?>
                                <tr>
                                    <td>#<?=$dep['id']?></td>
                                    <td class="cell-money">Ksh <?=number_format($dep['amount'], 2)?></td>
                                    <td><?=htmlspecialchars(ucfirst($dep['deposit_method'] ?? 'N/A'))?></td>
                                    <td><?=getStatusBadge($dep['status'])?></td>
                                    <td class="cell-date"><?=htmlspecialchars($dep['created_at'])?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Withdrawals -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-arrow-up"></i> Recent Withdrawals</div>
                <span class="badge badge-info"><?=count($withdrawals)?> shown</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($withdrawals)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No withdrawals found.</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <div class="scrollable-table">
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
                                <?php foreach ($withdrawals as $wd): ?>
                                <tr>
                                    <td>#<?=$wd['id']?></td>
                                    <td class="cell-money">Ksh <?=number_format($wd['amount'], 2)?></td>
                                    <td><?=getStatusBadge($wd['status'])?></td>
                                    <td class="cell-date"><?=htmlspecialchars($wd['created_at'])?></td>
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

</div>

<script>
// Tab Switching
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
    });
});

// Toast System
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    
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
    }, 5000);
}

// Show toast on page load if PHP set a message
<?php if (!empty($toast_message)): ?>
window.addEventListener('load', () => {
    showToast("<?=addslashes($toast_message)?>", "<?=$toast_type?>");
});
<?php endif; ?>
</script>
</body>
</html>