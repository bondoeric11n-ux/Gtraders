<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        header("Location: admin_login.php");
        exit();
    }
}

require_once 'db_connect.php';

$message = "";
$message_type = "success";
$admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1;

// 1. HANDLE STATUS UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $ticket_type = $_POST['ticket_type'] ?? '';
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    try {
        if ($ticket_type === 'complaint') {
            $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $ticket_id);
            $stmt->execute();
        } elseif ($ticket_type === 'dispute') {
            $stmt = $conn->prepare("UPDATE disputes SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $ticket_id);
            $stmt->execute();
        }
        $message = "✅ Ticket status updated successfully.";
    } catch (Exception $e) {
        $message = "❌ Error updating status: " . $e->getMessage();
        $message_type = "error";
        error_log("Ticket Status Update Error: " . $e->getMessage());
    }
}

// 2. HANDLE ADMIN REPLY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    $ticket_type = $_POST['ticket_type'] ?? '';
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $reply_message = trim($_POST['reply_message'] ?? '');

    if (!empty($reply_message) && $ticket_id > 0) {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("INSERT INTO ticket_followups (ticket_type, ticket_id, admin_id, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siis", $ticket_type, $ticket_id, $admin_id, $reply_message);
            $stmt->execute();

            $auto_status = "in_progress";
            if ($ticket_type === 'complaint') {
                $stmt2 = $conn->prepare("UPDATE complaints SET status = ?, admin_response = ? WHERE id = ?");
                $stmt2->bind_param("ssi", $auto_status, $reply_message, $ticket_id);
                $stmt2->execute();
                $stmt2->close();
            } elseif ($ticket_type === 'dispute') {
                $stmt2 = $conn->prepare("UPDATE disputes SET status = ?, admin_response = ? WHERE id = ?");
                $stmt2->bind_param("ssi", $auto_status, $reply_message, $ticket_id);
                $stmt2->execute();
                $stmt2->close();
            }

            $conn->commit();
            $message = "✅ Reply sent successfully and ticket marked as In Progress.";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "❌ Error sending reply: " . $e->getMessage();
            $message_type = "error";
            error_log("Ticket Reply Error: " . $e->getMessage());
        }
    } else {
        $message = "❌ Reply message cannot be empty.";
        $message_type = "error";
    }
}

// 3. FETCH ALL TICKETS
$complaints = [];
$res_c = $conn->query("SELECT c.*, u.username, u.email FROM complaints c LEFT JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC");
if ($res_c) { while ($row = $res_c->fetch_assoc()) { $complaints[] = $row; } }

$disputes = [];
$res_d = $conn->query("SELECT d.*, u.username, u.email FROM disputes d LEFT JOIN users u ON d.user_id = u.id ORDER BY d.created_at DESC");
if ($res_d) { while ($row = $res_d->fetch_assoc()) { $disputes[] = $row; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Tickets | GIBAL LTD</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { --bg-primary: #0a0e1a; --bg-card: rgba(17, 24, 39, 0.8); --gold: #d4af37; --text-primary: #f1f5f9; --text-secondary: #94a3b8; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --info: #3b82f6; --border-subtle: rgba(148, 163, 184, 0.15); }
body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); margin: 0; padding: 20px; }
.container { max-width: 1200px; margin: 0 auto; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
.header h1 { font-size: 1.8rem; color: var(--gold); margin: 0; }
.back-btn { color: var(--text-secondary); text-decoration: none; display: flex; align-items: center; gap: 8px; font-weight: 500; }
.back-btn:hover { color: var(--gold); }
.message { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
.message.success { background: rgba(16, 185, 129, 0.15); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.3); }
.message.error { background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.3); }
.ticket-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 24px; margin-bottom: 20px; backdrop-filter: blur(10px); }
.ticket-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
.ticket-type { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 4px 12px; border-radius: 50px; }
.type-complaint { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.type-dispute { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
.status-badge { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 4px 12px; border-radius: 50px; }
.status-open, .status-pending { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.status-in_progress { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.status-resolved { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.status-rejected { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
.ticket-meta { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 15px; line-height: 1.6; }
.ticket-meta strong { color: var(--text-primary); }
.ticket-message { background: rgba(15, 23, 42, 0.6); padding: 15px; border-radius: 8px; border-left: 3px solid var(--gold); margin-bottom: 15px; color: var(--text-primary); line-height: 1.6; white-space: pre-wrap; }
.ticket-actions { display: flex; gap: 15px; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-subtle); flex-wrap: wrap; align-items: flex-end; }
.form-group { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 200px; }
.form-group label { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }
select, textarea { background: rgba(15, 23, 42, 0.8); border: 1px solid var(--border-subtle); color: var(--text-primary); padding: 10px 12px; border-radius: 8px; font-family: inherit; font-size: 0.9rem; }
select:focus, textarea:focus { outline: none; border-color: var(--gold); }
textarea { width: 100%; min-height: 80px; resize: vertical; }
.btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; font-size: 0.9rem; }
.btn-primary { background: var(--gold); color: #0a0e1a; }
.btn-primary:hover { background: #f4d03f; transform: translateY(-1px); }
.btn-secondary { background: rgba(255,255,255,0.05); color: var(--text-primary); border: 1px solid var(--border-subtle); }
.btn-secondary:hover { background: rgba(255,255,255,0.1); }
.section-title { font-size: 1.2rem; font-weight: 700; color: var(--gold); margin: 40px 0 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-subtle); }
.empty-state { text-align: center; padding: 40px; color: var(--text-secondary); background: var(--bg-card); border-radius: 12px; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-headset"></i> Support Tickets</h1>
        <a href="admin_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="message <?= htmlspecialchars($message_type) ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="section-title"><i class="fas fa-flag"></i> User Complaints</div>
    <?php if (empty($complaints)): ?>
        <div class="empty-state"><i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.3;"></i><p>No complaints found.</p></div>
    <?php else: ?>
        <?php foreach ($complaints as $ticket): ?>
        <div class="ticket-card">
            <div class="ticket-header">
                <div>
                    <span class="ticket-type type-complaint">Complaint</span>
                    <span class="status-badge status-<?= strtolower(htmlspecialchars($ticket['status'] ?? 'open')) ?>"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($ticket['status'] ?? 'open'))) ?></span>
                </div>
                <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= date('M d, Y H:i', strtotime($ticket['created_at'] ?? 'now')) ?></span>
            </div>
            <div class="ticket-meta">
                <strong>User:</strong> <?= htmlspecialchars($ticket['username'] ?? 'Unknown') ?> (<?= htmlspecialchars($ticket['email'] ?? 'No Email') ?>)<br>
                <strong>Subject:</strong> <?= htmlspecialchars($ticket['subject'] ?? 'No Subject') ?>
            </div>
            <div class="ticket-message"><?= htmlspecialchars($ticket['message'] ?? 'No message') ?></div>
            
            <?php if (!empty($ticket['admin_response'])): ?>
                <div style="background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 8px; border-left: 3px solid var(--info); margin-bottom: 15px;">
                    <strong style="color: var(--info); font-size: 0.85rem;"><i class="fas fa-user-shield"></i> Previous Admin Response:</strong>
                    <p style="margin: 8px 0 0; color: var(--text-primary);"><?= nl2br(htmlspecialchars($ticket['admin_response'])) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="ticket-actions">
                <input type="hidden" name="ticket_type" value="complaint">
                <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($ticket['id'] ?? 0) ?>">
                <div class="form-group" style="max-width: 200px;">
                    <label>Update Status</label>
                    <select name="new_status">
                        <option value="open" <?= ($ticket['status'] ?? '') === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="in_progress" <?= ($ticket['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="resolved" <?= ($ticket['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                        <option value="rejected" <?= ($ticket['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <button type="submit" name="update_status" class="btn btn-secondary">Save Status</button>
                <div class="form-group" style="flex: 2; min-width: 300px;">
                    <label>Reply to User</label>
                    <textarea name="reply_message" placeholder="Type your response here..."></textarea>
                </div>
                <button type="submit" name="submit_reply" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Reply</button>
            </form>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="section-title"><i class="fas fa-exclamation-triangle"></i> Deposit Disputes</div>
    <?php if (empty($disputes)): ?>
        <div class="empty-state"><i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.3;"></i><p>No disputes found.</p></div>
    <?php else: ?>
        <?php foreach ($disputes as $ticket): ?>
        <div class="ticket-card">
            <div class="ticket-header">
                <div>
                    <span class="ticket-type type-dispute">Dispute</span>
                    <span class="status-badge status-<?= strtolower(htmlspecialchars($ticket['status'] ?? 'pending')) ?>"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($ticket['status'] ?? 'pending'))) ?></span>
                </div>
                <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= date('M d, Y H:i', strtotime($ticket['created_at'] ?? 'now')) ?></span>
            </div>
            <div class="ticket-meta">
                <strong>User:</strong> <?= htmlspecialchars($ticket['username'] ?? 'Unknown') ?> (<?= htmlspecialchars($ticket['email'] ?? 'No Email') ?>)<br>
                <strong>Amount:</strong> <span style="color: var(--gold); font-weight: 700;">Ksh <?= number_format($ticket['amount'] ?? 0, 2) ?></span> | 
                <strong>Ref:</strong> <span style="font-family: monospace;"><?= htmlspecialchars($ticket['transaction_ref'] ?? 'N/A') ?></span>
            </div>
            <div class="ticket-message"><?= htmlspecialchars($ticket['message'] ?? 'No message') ?></div>

            <?php if (!empty($ticket['admin_response'])): ?>
                <div style="background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 8px; border-left: 3px solid var(--info); margin-bottom: 15px;">
                    <strong style="color: var(--info); font-size: 0.85rem;"><i class="fas fa-user-shield"></i> Previous Admin Response:</strong>
                    <p style="margin: 8px 0 0; color: var(--text-primary);"><?= nl2br(htmlspecialchars($ticket['admin_response'])) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="ticket-actions">
                <input type="hidden" name="ticket_type" value="dispute">
                <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($ticket['id'] ?? 0) ?>">
                <div class="form-group" style="max-width: 200px;">
                    <label>Update Status</label>
                    <select name="new_status">
                        <option value="pending" <?= ($ticket['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="in_progress" <?= ($ticket['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="resolved" <?= ($ticket['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                        <option value="rejected" <?= ($ticket['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <button type="submit" name="update_status" class="btn btn-secondary">Save Status</button>
                <div class="form-group" style="flex: 2; min-width: 300px;">
                    <label>Reply to User</label>
                    <textarea name="reply_message" placeholder="Type your response here..."></textarea>
                </div>
                <button type="submit" name="submit_reply" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Reply</button>
            </form>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>