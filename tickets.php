<?php
session_start();
include_once("db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$toast = $_SESSION['ticket_toast'] ?? '';
$toast_type = $_SESSION['ticket_toast_type'] ?? 'success';
unset($_SESSION['ticket_toast'], $_SESSION['ticket_toast_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $choice = $_POST['ticket_choice'] ?? 'complaint';
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['message'] ?? '');

    if ($choice === 'dispute') {
        $ref = trim($_POST['transaction_ref'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);

        if ($ref === '' || $amount <= 0 || $body === '') {
            $_SESSION['ticket_toast'] = '❌ Please fill transaction reference, amount, and message.';
            $_SESSION['ticket_toast_type'] = 'error';
            header("Location: tickets.php");
            exit();
        }

        if ($subject === '') {
            $subject = 'Missing Deposit: ' . $ref;
        }

        $stmt = $conn->prepare("INSERT INTO disputes (user_id, transaction_ref, amount, message, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("isds", $user_id, $ref, $amount, $body);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $_SESSION['ticket_toast'] = '✅ Dispute submitted successfully.';
            $_SESSION['ticket_toast_type'] = 'success';
            header("Location: tickets.php?view=dispute-" . $new_id);
            exit();
        } else {
            $_SESSION['ticket_toast'] = '❌ Failed to submit dispute: ' . $stmt->error;
            $_SESSION['ticket_toast_type'] = 'error';
            header("Location: tickets.php");
            exit();
        }
    } else {
        if ($subject === '' || $body === '') {
            $_SESSION['ticket_toast'] = '❌ Please fill subject and message.';
            $_SESSION['ticket_toast_type'] = 'error';
            header("Location: tickets.php");
            exit();
        }

        $category = $choice === 'feedback' ? 'feedback' : 'complaint';

        $stmt = $conn->prepare("INSERT INTO complaints (user_id, subject, message, category, status) VALUES (?, ?, ?, ?, 'open')");
        $stmt->bind_param("isss", $user_id, $subject, $body, $category);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $_SESSION['ticket_toast'] = '✅ Ticket submitted successfully.';
            $_SESSION['ticket_toast_type'] = 'success';
            header("Location: tickets.php?view=complaint-" . $new_id);
            exit();
        } else {
            $_SESSION['ticket_toast'] = '❌ Failed to submit ticket: ' . $stmt->error;
            $_SESSION['ticket_toast_type'] = 'error';
            header("Location: tickets.php");
            exit();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_followup'])) {
    $ticket_type = $_POST['ticket_type'] ?? '';
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $message = trim($_POST['followup_message'] ?? '');

    if (!in_array($ticket_type, ['complaint', 'dispute'], true) || $ticket_id <= 0 || $message === '') {
        $_SESSION['ticket_toast'] = '❌ Invalid follow-up request.';
        $_SESSION['ticket_toast_type'] = 'error';
        header("Location: tickets.php");
        exit();
    }

    if ($ticket_type === 'complaint') {
        $chk = $conn->prepare("SELECT id FROM complaints WHERE id = ? AND user_id = ?");
    } else {
        $chk = $conn->prepare("SELECT id FROM disputes WHERE id = ? AND user_id = ?");
    }

    $chk->bind_param("ii", $ticket_id, $user_id);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$exists) {
        $_SESSION['ticket_toast'] = '❌ Ticket not found.';
        $_SESSION['ticket_toast_type'] = 'error';
        header("Location: tickets.php");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO ticket_followups (ticket_type, ticket_id, user_id, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siis", $ticket_type, $ticket_id, $user_id, $message);

    if ($stmt->execute()) {
        $new_status = $ticket_type === 'dispute' ? 'pending' : 'open';

        if ($ticket_type === 'complaint') {
            $upd = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
        } else {
            $upd = $conn->prepare("UPDATE disputes SET status = ? WHERE id = ?");
        }

        $upd->bind_param("si", $new_status, $ticket_id);
        $upd->execute();
        $upd->close();

        $_SESSION['ticket_toast'] = '✅ Follow-up sent.';
        $_SESSION['ticket_toast_type'] = 'success';
        header("Location: tickets.php?view=" . $ticket_type . "-" . $ticket_id);
        exit();
    } else {
        $_SESSION['ticket_toast'] = '❌ Failed to send follow-up.';
        $_SESSION['ticket_toast_type'] = 'error';
        header("Location: tickets.php?view=" . $ticket_type . "-" . $ticket_id);
        exit();
    }
}

$complaints = [];
$stmt = $conn->prepare("SELECT * FROM complaints WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $complaints[] = $row;
}
$stmt->close();

$disputes = [];
$stmt = $conn->prepare("SELECT * FROM disputes WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $disputes[] = $row;
}
$stmt->close();

$view_ticket = null;
$view_type = null;
$followups = [];
$max_followup_id = 0;

if (isset($_GET['view'])) {
    $parts = explode('-', $_GET['view']);
    if (count($parts) === 2) {
        $view_type = $parts[0];
        $ticket_id = (int)$parts[1];

        if ($view_type === 'complaint') {
            $stmt = $conn->prepare("SELECT * FROM complaints WHERE id = ? AND user_id = ?");
        } elseif ($view_type === 'dispute') {
            $stmt = $conn->prepare("SELECT * FROM disputes WHERE id = ? AND user_id = ?");
        }

        if (isset($stmt)) {
            $stmt->bind_param("ii", $ticket_id, $user_id);
            $stmt->execute();
            $view_ticket = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if ($view_ticket) {
            $stmt = $conn->prepare("
                SELECT tf.*,
                       COALESCE(a.username, 'Admin') AS admin_name,
                       COALESCE(u.username, 'User') AS user_name
                FROM ticket_followups tf
                LEFT JOIN admins a ON tf.admin_id = a.id
                LEFT JOIN users u ON tf.user_id = u.id
                WHERE tf.ticket_type = ? AND tf.ticket_id = ?
                ORDER BY tf.id ASC
            ");
            $stmt->bind_param("si", $view_type, $ticket_id);
            $stmt->execute();
            $followups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($followups as $f) {
                $max_followup_id = max($max_followup_id, (int)$f['id']);
            }
        }
    }
}

if (isset($_GET['view']) && !$view_ticket) {
    header("Location: tickets.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Tickets | GIBAL LTD</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg-primary: #0a0e1a;
    --bg-card: rgba(17, 24, 39, 0.75);
    --gold: #d4af37;
    --gold-dark: #b8941f;
    --text-primary: #f1f5f9;
    --text-secondary: #94a3b8;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --purple: #8b5cf6;
    --border-subtle: rgba(148, 163, 184, 0.15);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Inter', sans-serif;
    background: var(--bg-primary);
    color: var(--text-primary);
    min-height: 100vh;
    padding: 24px;
}
.container { max-width: 1000px; margin: 0 auto; }
.header { text-align: center; margin-bottom: 30px; }
.header h1 {
    font-size: 2rem;
    background: linear-gradient(135deg, #fff 0%, var(--gold) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 8px;
}
.header p { color: var(--text-secondary); }
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    text-decoration: none;
    margin-bottom: 20px;
}
.back-link:hover { color: var(--gold); }
.message {
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: 600;
}
.message.success {
    background: rgba(16, 185, 129, 0.12);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.3);
}
.message.error {
    background: rgba(239, 68, 68, 0.12);
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.card {
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 20px;
}
.card h2 {
    color: var(--gold);
    margin-bottom: 18px;
    font-size: 1.15rem;
}
.form-group { margin-bottom: 16px; }
.form-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--border-subtle);
    background: rgba(15, 23, 42, 0.8);
    color: var(--text-primary);
    font-family: inherit;
    font-size: 0.95rem;
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--gold);
}
.form-textarea { min-height: 110px; resize: vertical; }
.btn {
    padding: 12px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 700;
    cursor: pointer;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: #0a0e1a;
}
.btn:hover { transform: translateY(-1px); }
.ticket-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}
.ticket-card {
    display: block;
    text-decoration: none;
    color: inherit;
    background: var(--bg-card);
    border: 1px solid var(--border-subtle);
    border-radius: 14px;
    padding: 18px;
    transition: 0.2s;
}
.ticket-card:hover {
    transform: translateY(-3px);
    border-color: var(--gold);
}
.ticket-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.ticket-type {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 50px;
}
.type-complaint { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.type-feedback { background: rgba(139, 92, 246, 0.15); color: var(--purple); }
.type-dispute { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
.ticket-status {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 50px;
}
.ticket-status.open, .ticket-status.pending { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.ticket-status.in_progress { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.ticket-status.resolved { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.ticket-status.rejected { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
.ticket-title { font-weight: 700; margin-bottom: 8px; }
.ticket-preview { color: var(--text-secondary); font-size: 0.88rem; line-height: 1.5; }
.ticket-meta {
    display: flex;
    justify-content: space-between;
    margin-top: 12px;
    font-size: 0.78rem;
    color: var(--text-secondary);
}
.ticket-amount { color: var(--gold); font-weight: 700; }
.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 20px;
}
.detail-title { font-size: 1.25rem; font-weight: 700; color: var(--gold); }
.ticket-chat {
    height: 380px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px;
    border-radius: 12px;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid var(--border-subtle);
    margin-bottom: 16px;
}
.ticket-msg {
    max-width: 75%;
    padding: 10px 14px;
    border-radius: 12px;
}
.ticket-msg.mine {
    align-self: flex-end;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: #0a0e1a;
}
.ticket-msg.theirs {
    align-self: flex-start;
    background: rgba(59, 130, 246, 0.15);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: var(--text-primary);
}
.ticket-msg-meta {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 0.7rem;
    opacity: 0.85;
    margin-bottom: 4px;
}
.ticket-msg-body { line-height: 1.5; white-space: pre-wrap; }
.chat-empty {
    text-align: center;
    color: var(--text-secondary);
    padding: 30px 10px;
}
.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}
</style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <div class="header">
        <h1>My Support Tickets</h1>
        <p>Submit complaints, disputes, or feedback and chat with support</p>
    </div>

    <?php if ($toast): ?>
        <div class="message <?= htmlspecialchars($toast_type) ?>"><?= htmlspecialchars($toast) ?></div>
    <?php endif; ?>

    <?php if (!$view_ticket): ?>
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Create New Ticket</h2>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Ticket Type</label>
                    <select name="ticket_choice" id="ticketChoice" class="form-select" required>
                        <option value="complaint">Complaint</option>
                        <option value="dispute">Missing Deposit Dispute</option>
                        <option value="feedback">Feedback</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <input type="text" name="subject" class="form-input" placeholder="Brief title" required>
                </div>

                <div id="disputeFields" style="display:none;">
                    <div class="form-group">
                        <label class="form-label">Transaction Reference</label>
                        <input type="text" name="transaction_ref" class="form-input" placeholder="e.g. M-Pesa code">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (Ksh)</label>
                        <input type="number" step="0.01" name="amount" class="form-input" placeholder="0.00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea name="message" class="form-textarea" placeholder="Describe your issue clearly..." required></textarea>
                </div>

                <button type="submit" name="create_ticket" class="btn"><i class="fas fa-paper-plane"></i> Submit Ticket</button>
            </form>
        </div>

        <div class="ticket-grid">
            <?php
            $all_tickets = [];
            foreach ($complaints as $c) {
                $category = $c['category'] ?? 'complaint';
                $all_tickets[] = [
                    'api_type' => 'complaint',
                    'label' => $category === 'feedback' ? 'Feedback' : 'Complaint',
                    'type_class' => $category === 'feedback' ? 'type-feedback' : 'type-complaint',
                    'id' => $c['id'],
                    'title' => $c['subject'],
                    'message' => $c['message'],
                    'status' => $c['status'],
                    'amount' => null,
                    'date' => $c['created_at']
                ];
            }
            foreach ($disputes as $d) {
                $all_tickets[] = [
                    'api_type' => 'dispute',
                    'label' => 'Dispute',
                    'type_class' => 'type-dispute',
                    'id' => $d['id'],
                    'title' => 'Missing Deposit: ' . $d['transaction_ref'],
                    'message' => $d['message'],
                    'status' => $d['status'],
                    'amount' => $d['amount'],
                    'date' => $d['created_at']
                ];
            }

            usort($all_tickets, function ($a, $b) {
                return strtotime($b['date']) <=> strtotime($a['date']);
            });

            if (empty($all_tickets)):
            ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.3; margin-bottom: 10px;"></i>
                    <p>You have no tickets yet.</p>
                </div>
            <?php else: foreach ($all_tickets as $t): ?>
                <a href="?view=<?= htmlspecialchars($t['api_type']) ?>-<?= (int)$t['id'] ?>" class="ticket-card">
                    <div class="ticket-top">
                        <span class="ticket-type <?= $t['type_class'] ?>"><?= htmlspecialchars($t['label']) ?></span>
                        <span class="ticket-status <?= strtolower(htmlspecialchars($t['status'])) ?>"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($t['status']))) ?></span>
                    </div>
                    <div class="ticket-title"><?= htmlspecialchars($t['title']) ?></div>
                    <div class="ticket-preview"><?= htmlspecialchars(substr($t['message'], 0, 100)) ?>...</div>
                    <div class="ticket-meta">
                        <span><?= date('M d, Y', strtotime($t['date'])) ?></span>
                        <?php if ($t['amount']): ?>
                            <span class="ticket-amount">Ksh <?= number_format($t['amount'], 2) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; endif; ?>
        </div>

    <?php else: ?>
        <?php
        $is_feedback = $view_type === 'complaint' && ($view_ticket['category'] ?? 'complaint') === 'feedback';
        $ticket_label = $view_type === 'dispute' ? 'Dispute' : ($is_feedback ? 'Feedback' : 'Complaint');
        $ticket_title = $view_type === 'dispute'
            ? 'Missing Deposit: ' . htmlspecialchars($view_ticket['transaction_ref'])
            : htmlspecialchars($view_ticket['subject']);
        ?>
        <a href="tickets.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to All Tickets</a>

        <div class="card">
            <div class="detail-header">
                <div>
                    <div class="detail-title"><?= $ticket_title ?></div>
                    <div style="color: var(--text-secondary); font-size: 0.88rem; margin-top: 6px;">
                        <?= htmlspecialchars($ticket_label) ?> • Submitted <?= date('M d, Y H:i', strtotime($view_ticket['created_at'])) ?>
                    </div>
                </div>
                <span id="ticketStatusBadge" class="ticket-status <?= strtolower($view_ticket['status']) ?>">
                    <?= ucfirst(str_replace('_', ' ', $view_ticket['status'])) ?>
                </span>
            </div>

            <?php if ($view_type === 'dispute'): ?>
                <div style="margin-bottom: 16px;">
                    <strong>Amount:</strong>
                    <span style="color: var(--gold); font-weight: 700;">Ksh <?= number_format($view_ticket['amount'], 2) ?></span>
                </div>
            <?php endif; ?>

            <div style="background: rgba(30, 41, 59, 0.45); padding: 16px; border-radius: 10px; margin-bottom: 18px;">
                <strong>Original Message</strong>
                <p style="margin-top: 8px; line-height: 1.6;"><?= nl2br(htmlspecialchars($view_ticket['message'])) ?></p>
            </div>

            <div class="ticket-chat" id="ticketChat">
                <?php if (empty($followups)): ?>
                    <div class="chat-empty">No messages yet. Start the conversation below.</div>
                <?php else: foreach ($followups as $f): ?>
                    <?php
                    $is_mine = !empty($f['user_id']) && (int)$f['user_id'] === $user_id;
                    $sender = $is_mine ? 'You' : ($f['admin_name'] ?: 'Admin');
                    ?>
                    <div class="ticket-msg <?= $is_mine ? 'mine' : 'theirs' ?>" data-id="<?= (int)$f['id'] ?>">
                        <div class="ticket-msg-meta">
                            <span><?= htmlspecialchars($sender) ?></span>
                            <span><?= date('M d, Y H:i', strtotime($f['created_at'])) ?></span>
                        </div>
                        <div class="ticket-msg-body"><?= nl2br(htmlspecialchars($f['message'])) ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <form id="ticketReplyForm" method="POST">
                <input type="hidden" name="ticket_type" value="<?= htmlspecialchars($view_type) ?>">
                <input type="hidden" name="ticket_id" value="<?= (int)$view_ticket['id'] ?>">
                <div class="form-group">
                    <textarea name="followup_message" id="ticketReplyInput" class="form-textarea" placeholder="Type your message..." required></textarea>
                </div>
                <button type="submit" name="submit_followup" class="btn"><i class="fas fa-paper-plane"></i> Send Message</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('ticketChoice')?.addEventListener('change', function () {
    document.getElementById('disputeFields').style.display = this.value === 'dispute' ? 'block' : 'none';
});

(function () {
    const chat = document.getElementById('ticketChat');
    if (!chat) return;

    const apiType = '<?= htmlspecialchars($view_type ?? '') ?>';
    const ticketId = <?= (int)($view_ticket['id'] ?? 0) ?>;
    let lastId = <?= (int)$max_followup_id ?>;

    const form = document.getElementById('ticketReplyForm');
    const input = document.getElementById('ticketReplyInput');
    const badge = document.getElementById('ticketStatusBadge');

    function prettyStatus(status) {
        return status.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
    }

    function setStatus(status) {
        if (!badge) return;
        badge.className = 'ticket-status ' + status;
        badge.textContent = prettyStatus(status);
    }

    function appendHtml(html) {
        const empty = chat.querySelector('.chat-empty');
        if (empty) empty.remove();
        chat.insertAdjacentHTML('beforeend', html);
        chat.scrollTop = chat.scrollHeight;
    }

    async function pollMessages() {
        if (document.hidden) return;
        try {
            const res = await fetch('ticket_api.php?action=messages&context=user&ticket_type=' + encodeURIComponent(apiType) + '&ticket_id=' + ticketId + '&last_id=' + lastId);
            const data = await res.json();
            if (data.success) {
                if (data.html) appendHtml(data.html);
                if (data.last_id) lastId = data.last_id;
                if (data.status) setStatus(data.status);
            }
        } catch (e) {
            console.error(e);
        }
    }

    setInterval(pollMessages, 3000);

    form?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const message = input.value.trim();
        if (!message) return;

        input.disabled = true;

        try {
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('context', 'user');
            fd.append('ticket_type', apiType);
            fd.append('ticket_id', ticketId);
            fd.append('message', message);

            const res = await fetch('ticket_api.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                appendHtml(data.html);
                lastId = data.last_id;
                setStatus(data.status);
                input.value = '';
            } else {
                alert(data.message || 'Failed to send message');
            }
        } catch (err) {
            alert('Network error. Please try again.');
        } finally {
            input.disabled = false;
            input.focus();
        }
    });

    chat.scrollTop = chat.scrollHeight;
})();
</script>
</body>
</html>