<?php
$ADMIN_SESSION_TIMEOUT = 1800;
session_start();

if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'] ?? 'info';
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

if (isset($_SESSION['admin_last_activity']) && time() - $_SESSION['admin_last_activity'] > $ADMIN_SESSION_TIMEOUT) {
    session_unset(); session_destroy(); header("Location: admin_login.php?timeout=1"); exit();
}
$_SESSION['admin_last_activity'] = time();
include("db_connect.php");
require_once 'src/Exception.php';
require_once 'src/PHPMailer.php';
require_once 'src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }

$admin_id = (int)$_SESSION['admin_id'];
$today = date('Y-m-d');
$admin_email = '';
$aq = $conn->prepare("SELECT email FROM admins WHERE id=?");
$aq->bind_param("i", $admin_id);
$aq->execute();
$ar = $aq->get_result()->fetch_assoc();
$admin_email = $ar['email'] ?? '';
$aq->close();

// ============================================
// AJAX HANDLER - Returns JSON for auto-refresh
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    $unread_admin_msgs = (int)($conn->query("SELECT COUNT(*) as c FROM admin_chats WHERE sender_type='user' AND is_read=0")->fetch_assoc()['c'] ?? 0);
    $latest_chat_html = '';
    $selected_chat_user = isset($_GET['chat_user']) ? (int)$_GET['chat_user'] : 0;
    if ($selected_chat_user > 0) {
        $hist_q = $conn->prepare("SELECT * FROM admin_chats WHERE user_id=? ORDER BY created_at ASC");
        $hist_q->bind_param("i", $selected_chat_user);
        $hist_q->execute();
        $chat_history = $hist_q->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($chat_history as $msg) {
            $latest_chat_html .= '<div class="chat-bubble '.$msg['sender_type'].'">'.htmlspecialchars($msg['message']).'<div style="font-size: 0.65rem; opacity: 0.7; margin-top: 4px; text-align: right;">'.date('H:i', strtotime($msg['created_at'])).'</div></div>';
        }
        $hist_q->close();
    }
    $unread_users_q = $conn->query("SELECT DISTINCT c.user_id, u.username, MAX(c.created_at) as last_msg FROM admin_chats c JOIN users u ON c.user_id = u.id WHERE c.sender_type='user' AND c.is_read=0 GROUP BY c.user_id ORDER BY last_msg DESC LIMIT 5");
    $unread_users = $unread_users_q ? $unread_users_q->fetch_all(MYSQLI_ASSOC) : [];
    $open_complaints = (int)($conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='open'")->fetch_assoc()['c'] ?? 0);
    $pending_disputes = (int)($conn->query("SELECT COUNT(*) as c FROM disputes WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
    echo json_encode(['success' => true, 'unread_msgs' => $unread_admin_msgs, 'chat_html' => $latest_chat_html, 'unread_users' => $unread_users, 'open_complaints' => $open_complaints, 'pending_disputes' => $pending_disputes]);
    exit();
}

// ============================================
// AJAX HANDLER - Send chat message without reload
// ============================================
if (isset($_POST['ajax_send_chat'])) {
    header('Content-Type: application/json');
    $chat_user_id = (int)$_POST['chat_user_id'];
    $chat_msg = trim($_POST['chat_message']);
    if (!empty($chat_msg) && $chat_user_id > 0) {
        $stmt = $conn->prepare("INSERT INTO admin_chats (admin_id, user_id, message, sender_type) VALUES (?, ?, ?, 'admin')");
        $stmt->bind_param("iis", $admin_id, $chat_user_id, $chat_msg);
        if ($stmt->execute()) { echo json_encode(['success' => true, 'message' => 'Message sent']); }
        else { echo json_encode(['success' => false, 'message' => 'Failed to send']); }
        $stmt->close();
    } else { echo json_encode(['success' => false, 'message' => 'Invalid data']); }
    exit();
}

// ============================================
// STANDARD POST HANDLERS
// ============================================
$toast_message = ""; $toast_type = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_send_chat'])) {

    if (isset($_POST['request_password_change'])) {
        $new_pass = $_POST['new_password'];
        if (strlen($new_pass) < 8) { $toast_message = "Password must be at least 8 characters."; $toast_type = "error"; }
        elseif (empty($admin_email)) { $toast_message = "Admin email not found."; $toast_type = "error"; }
        else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $conn->prepare("INSERT INTO password_change_requests (admin_id, new_password_hash, twofa_code, status) VALUES (?, ?, ?, 'pending_2fa')");
            $stmt->bind_param("iss", $admin_id, $hash, $code);
            if ($stmt->execute()) {
                $_SESSION['pending_req_id'] = $conn->insert_id;
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
                    $mail->Username = 'gibal.ltd@gmail.com'; $mail->Password = 'dkbcereljkmvzfqy';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = 587;
                    $mail->setFrom('gibal.ltd@gmail.com', 'GIBAL LTD Security');
                    $mail->addAddress($admin_email); $mail->isHTML(true);
                    $mail->Subject = "Your 2FA Verification Code";
                    $mail->Body = "<div style='font-family: Arial; padding: 20px; background: #0b1120; color: #f1f5f9; border-radius: 8px;'><h2 style='color: #22d3ee;'>GIBAL LTD Security</h2><p>Your 2FA code is:</p><p style='font-size: 24px; font-weight: bold; letter-spacing: 4px; color: #fbbf24;'>$code</p></div>";
                    $mail->send();
                    $toast_message = "✅ 6-digit code sent to <strong>$admin_email</strong>."; $toast_type = "success";
                } catch (Exception $e) { $toast_message = "Failed to send email."; $toast_type = "error"; }
            }
            $stmt->close();
        }
    }

    if (isset($_POST['verify_2fa'])) {
        $req_id = $_SESSION['pending_req_id'] ?? 0;
        $input_code = $_POST['twofa_code'];
        $stmt = $conn->prepare("SELECT id FROM password_change_requests WHERE id=? AND twofa_code=? AND status='pending_2fa'");
        $stmt->bind_param("is", $req_id, $input_code);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt2 = $conn->prepare("UPDATE password_change_requests SET status='pending_approval' WHERE id=?");
            $stmt2->bind_param("i", $req_id);
            $stmt2->execute();
            $stmt2->close();
            $toast_message = "✅ Code verified! Request sent to Super Admin."; $toast_type = "success";
            unset($_SESSION['pending_req_id']);
        } else { $toast_message = "Invalid 2FA Code."; $toast_type = "error"; }
        $stmt->close();
    }

    if (isset($_POST['resolve_complaint'])) {
        $comp_id = (int)$_POST['complaint_id'];
        $response = trim($_POST['admin_response']);
        $resolved_status = "resolved";
        $stmt = $conn->prepare("UPDATE complaints SET status=?, admin_response=? WHERE id=?");
        $stmt->bind_param("ssi", $resolved_status, $response, $comp_id);
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "✅ Complaint resolved successfully.";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "❌ DB Error: " . $stmt->error;
            $_SESSION['toast_type'] = "error";
        }
        $stmt->close();
        header("Location: admin_settings.php?view_ticket=complaint-{$comp_id}#tab-complaints");
        exit();
    }

    if (isset($_POST['resolve_dispute'])) {
        $dispute_id = (int)$_POST['dispute_id'];
        $response = trim($_POST['dispute_response']);
        $status = $_POST['dispute_status'] ?? 'resolved';
        $stmt = $conn->prepare("UPDATE disputes SET status=?, admin_response=? WHERE id=?");
        $stmt->bind_param("ssi", $status, $response, $dispute_id);
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "✅ Dispute marked as {$status}.";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "❌ DB Error: " . $stmt->error;
            $_SESSION['toast_type'] = "error";
        }
        $stmt->close();
        header("Location: admin_settings.php?view_ticket=dispute-{$dispute_id}#tab-complaints");
        exit();
    }

    if (isset($_POST['admin_ticket_followup'])) {
        $ticket_type = trim($_POST['ticket_type'] ?? '');
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $followup_msg = trim($_POST['admin_message'] ?? '');

        if (!empty($followup_msg) && $ticket_id > 0 && !empty($ticket_type)) {
            $stmt = $conn->prepare("INSERT INTO ticket_followups (ticket_type, ticket_id, admin_id, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siis", $ticket_type, $ticket_id, $admin_id, $followup_msg);
            $insert_ok = $stmt->execute();
            $insert_error = $stmt->error;
            $stmt->close();

            if ($insert_ok) {
                $new_status = "in_progress";
                if ($ticket_type === 'complaint') {
                    $stmt2 = $conn->prepare("UPDATE complaints SET status=? WHERE id=?");
                } else {
                    $stmt2 = $conn->prepare("UPDATE disputes SET status=? WHERE id=?");
                }
                $stmt2->bind_param("si", $new_status, $ticket_id);
                $update_ok = $stmt2->execute();
                $update_error = $stmt2->error;
                $stmt2->close();

                if ($update_ok) {
                    $_SESSION['toast_message'] = "✅ Response sent and status updated to In Progress.";
                    $_SESSION['toast_type'] = "success";
                } else {
                    $_SESSION['toast_message'] = "⚠️ Message saved but status update failed: " . $update_error;
                    $_SESSION['toast_type'] = "error";
                }
            } else {
                $_SESSION['toast_message'] = "❌ Failed to save message: " . $insert_error;
                $_SESSION['toast_type'] = "error";
            }
        } else {
            $_SESSION['toast_message'] = "❌ Message cannot be empty.";
            $_SESSION['toast_type'] = "error";
        }
        header("Location: admin_settings.php?view_ticket={$ticket_type}-{$ticket_id}#tab-complaints");
        exit();
    }

    if (isset($_POST['send_notification'])) {
        $title = trim($_POST['notif_title']);
        $message = trim($_POST['notif_message']);
        $type = $_POST['notif_type'] ?? 'info';
        $target = $_POST['notif_target'] ?? 'all';
        $target_user_id = ($target === 'single' && !empty($_POST['notif_target_user'])) ? (int)$_POST['notif_target_user'] : null;
        if (!empty($title) && !empty($message)) {
            $stmt = $conn->prepare("INSERT INTO broadcast_notifications (title, message, type, created_by, target_user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $title, $message, $type, $admin_id, $target_user_id);
            if ($stmt->execute()) {
                $toast_message = $target_user_id ? "✅ Notification sent to specific user!" : "✅ Broadcast sent to all users!";
                $toast_type = "success";
            }
            $stmt->close();
        }
    }

    if (isset($_POST['send_bulk_email'])) {
        $subject = trim($_POST['email_subject']);
        $body = trim($_POST['email_body']);
        $target = $_POST['email_target'] ?? 'all';
        $target_user_id = ($target === 'single' && !empty($_POST['email_target_user'])) ? (int)$_POST['email_target_user'] : null;
        if (!empty($subject) && !empty($body)) {
            if ($target_user_id) {
                $stmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
                $stmt->bind_param("i", $target_user_id);
                $stmt->execute();
                $users_list = [$stmt->get_result()->fetch_assoc()];
                $stmt->close();
            } else {
                $users_list = $conn->query("SELECT email, name FROM users WHERE email IS NOT NULL AND email != ''")->fetch_all(MYSQLI_ASSOC);
            }
            $sent = 0; $failed = 0;
            foreach ($users_list as $u) {
                if (empty($u['email'])) continue;
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
                    $mail->Username = 'gibal.ltd@gmail.com'; $mail->Password = 'dkbcereljkmvzfqy';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = 587;
                    $mail->setFrom('gibal.ltd@gmail.com', 'GIBAL LTD');
                    $mail->addAddress($u['email'], $u['name']); $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $personalized_body = str_replace(['{{name}}', '{{email}}'], [htmlspecialchars($u['name']), htmlspecialchars($u['email'])], $body);
                    $mail->Body = "<div style='font-family: Arial; max-width: 600px; margin: auto; padding: 24px; background: #0a0e1a; color: #f1f5f9; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 12px;'><h2 style='color: #d4af37; text-align: center;'>GIBAL LTD</h2><div style='line-height: 1.6;'>$personalized_body</div></div>";
                    $mail->send(); $sent++;
                } catch (Exception $e) { $failed++; }
            }
            $toast_message = "✅ Emails sent: <strong>$sent</strong>. Failed: <strong>$failed</strong>."; $toast_type = $failed > 0 ? "warning" : "success";
        }
    }
}

// ============================================
// FETCH DATA FOR PAGE LOAD - DAILY PERFORMANCE (ROBUST)
// ============================================
function dp_id($name) {
    return (is_string($name) && preg_match('/^[A-Za-z0-9_]+$/', $name)) ? '`' . $name . '`' : null;
}

function dp_table_exists($conn, $table) {
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $table);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($exists) return true;
    }

    $tid = dp_id($table);
    if (!$tid) return false;

    $res = @$conn->query("SELECT 1 FROM $tid LIMIT 1");
    return $res !== false;
}

function dp_column_exists($conn, $table, $column) {
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ss", $table, $column);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($exists) return true;
    }

    $tid = dp_id($table);
    $cid = dp_id($column);
    if (!$tid || !$cid) return false;

    $res = @$conn->query("SELECT $cid FROM $tid LIMIT 1");
    return $res !== false;
}

function dp_pick_table($conn, array $candidates) {
    foreach ($candidates as $t) {
        if (dp_table_exists($conn, $t)) return $t;
    }
    return null;
}

function dp_pick_column($conn, $table, array $candidates) {
    foreach ($candidates as $c) {
        if (dp_column_exists($conn, $table, $c)) return $c;
    }
    return null;
}

function dp_query_value($conn, $sql) {
    $res = @$conn->query($sql);
    if (!$res) return 0;

    $row = $res->fetch_assoc();
    return $row ? floatval(reset($row)) : 0;
}

function dp_sum_raw($conn, $table, $amount_col, $date_col, $status_col = null, array $statuses = []) {
    $tid = dp_id($table);
    $aid = dp_id($amount_col);
    $did = dp_id($date_col);

    if (!$tid || !$aid || !$did) return 0;

    $where = "$did >= CURDATE() AND $did < CURDATE() + INTERVAL 1 DAY";

    if ($status_col && !empty($statuses)) {
        $sid = dp_id($status_col);
        if ($sid) {
            $escaped = [];
            foreach ($statuses as $s) {
                $escaped[] = "'" . $conn->real_escape_string($s) . "'";
            }
            $where .= " AND $sid IN (" . implode(',', $escaped) . ")";
        }
    }

    return dp_query_value($conn, "SELECT COALESCE(SUM($aid),0) AS total FROM $tid WHERE $where");
}

function dp_sum_today($conn, $table, $amount_col, $date_col, $status_col = null, array $statuses = [], $fallback_all_if_zero = true) {
    $val = dp_sum_raw($conn, $table, $amount_col, $date_col, $status_col, $statuses);

    if ($val > 0 || !$fallback_all_if_zero || !$status_col || empty($statuses)) {
        return $val;
    }

    return dp_sum_raw($conn, $table, $amount_col, $date_col);
}

function dp_count_today($conn, $table, $date_col) {
    $tid = dp_id($table);
    $did = dp_id($date_col);

    if (!$tid || !$did) return 0;

    $res = @$conn->query("SELECT COUNT(*) AS total FROM $tid WHERE $did >= CURDATE() AND $did < CURDATE() + INTERVAL 1 DAY");
    if (!$res) return 0;

    $row = $res->fetch_assoc();
    return $row ? (int)$row['total'] : 0;
}

function dp_calculate_stats($conn) {
    $deposits = 0;
    $dep_table = dp_pick_table($conn, ['deposits', 'user_deposits', 'payment_deposits', 'deposit_requests']);
    if ($dep_table) {
        $dep_amount = dp_pick_column($conn, $dep_table, ['amount', 'deposit_amount', 'total_amount', 'paid_amount', 'txn_amount']);
        $dep_date = dp_pick_column($conn, $dep_table, ['created_at', 'date', 'timestamp', 'deposit_date', 'payment_date']);
        $dep_status = dp_pick_column($conn, $dep_table, ['status', 'state', 'payment_status', 'transaction_status']);

        if ($dep_amount && $dep_date) {
            $deposits = dp_sum_today(
                $conn,
                $dep_table,
                $dep_amount,
                $dep_date,
                $dep_status,
                ['completed', 'complete', 'success', 'successful', 'approved', 'confirmed', 'paid', 'credited'],
                true
            );
        }
    }

    $withdrawals = 0;
    $wd_table = dp_pick_table($conn, ['withdrawals', 'user_withdrawals', 'withdrawal_requests']);
    if ($wd_table) {
        $wd_amount = dp_pick_column($conn, $wd_table, ['amount', 'withdraw_amount', 'withdrawal_amount', 'total_amount', 'requested_amount']);
        $wd_date = dp_pick_column($conn, $wd_table, ['created_at', 'date', 'timestamp', 'withdrawal_date', 'processed_at', 'updated_at']);
        $wd_status = dp_pick_column($conn, $wd_table, ['status', 'state', 'withdrawal_status', 'transaction_status']);

        if ($wd_amount && $wd_date) {
            $withdrawals = dp_sum_today(
                $conn,
                $wd_table,
                $wd_amount,
                $wd_date,
                $wd_status,
                ['approved', 'completed', 'processed', 'paid', 'success', 'successful', 'sent', 'credited'],
                true
            );
        }
    }

    $fees = 0;
    $fee_table = dp_pick_table($conn, ['daily_fees', 'fees', 'platform_fees', 'fee_collection']);
    if ($fee_table) {
        $fee_amount = dp_pick_column($conn, $fee_table, ['fee_amount', 'amount', 'total_fee', 'fees', 'charge_amount']);
        $fee_date = dp_pick_column($conn, $fee_table, ['fee_date', 'date', 'created_at', 'timestamp']);

        if ($fee_amount && $fee_date) {
            $fees = dp_sum_raw($conn, $fee_table, $fee_amount, $fee_date);
        }
    }

    $matured = 0;
    $inv_table = dp_pick_table($conn, ['investments', 'user_investments', 'investment_records']);
    if ($inv_table) {
        $inv_amount = dp_pick_column($conn, $inv_table, ['expected_interest', 'interest', 'profit', 'earnings', 'maturity_interest', 'amount']);
        $inv_date = dp_pick_column($conn, $inv_table, ['end_date', 'maturity_date', 'completion_date', 'created_at']);
        $inv_status = dp_pick_column($conn, $inv_table, ['status', 'state', 'investment_status']);

        if ($inv_amount && $inv_date) {
            $matured = dp_sum_today(
                $conn,
                $inv_table,
                $inv_amount,
                $inv_date,
                $inv_status,
                ['matured', 'completed', 'finished', 'paid', 'redeemed'],
                true
            );
        }
    }

    $new_users = 0;
    $user_table = dp_pick_table($conn, ['users', 'user_accounts', 'accounts']);
    if ($user_table) {
        $user_date = dp_pick_column($conn, $user_table, ['created_at', 'registration_date', 'joined_at', 'date', 'timestamp']);
        if ($user_date) {
            $new_users = dp_count_today($conn, $user_table, $user_date);
        }
    }

    return [
        'deposits' => floatval($deposits),
        'withdrawals' => floatval($withdrawals),
        'fees' => floatval($fees),
        'matured' => floatval($matured),
        'profit' => floatval($fees + $matured),
        'new_users' => intval($new_users)
    ];
}

if (isset($_GET['dp_json']) && $_GET['dp_json'] === '1') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode([
        'success' => true,
        'stats' => dp_calculate_stats($conn),
        'updated_at' => date('H:i:s')
    ]);
    exit();
}

$dp_stats = dp_calculate_stats($conn);
$dep_today = $dp_stats['deposits'];
$wd_today = $dp_stats['withdrawals'];
$fees_today = $dp_stats['fees'];
$matured_today = $dp_stats['matured'];
$total_profit_today = $dp_stats['profit'];
$new_users_today = $dp_stats['new_users'];

$complaints_q = $conn->query("SELECT c.*, u.email, u.username FROM complaints c LEFT JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC");
$complaints = $complaints_q ? $complaints_q->fetch_all(MYSQLI_ASSOC) : [];

$disputes_q = $conn->query("SELECT d.*, u.email, u.username FROM disputes d LEFT JOIN users u ON d.user_id = u.id ORDER BY d.created_at DESC");
$disputes = $disputes_q ? $disputes_q->fetch_all(MYSQLI_ASSOC) : [];

$chat_users_q = $conn->query("SELECT id, username, name, email, account_balance FROM users ORDER BY username ASC");
$chat_users = $chat_users_q ? $chat_users_q->fetch_all(MYSQLI_ASSOC) : [];

$selected_chat_user = isset($_GET['chat_user']) ? (int)$_GET['chat_user'] : 0;
$chat_history = [];
$chat_user_details = null;
if ($selected_chat_user > 0) {
    $conn->query("UPDATE admin_chats SET is_read=1 WHERE user_id=$selected_chat_user AND sender_type='user'");
    $hist_q = $conn->prepare("SELECT * FROM admin_chats WHERE user_id=? ORDER BY created_at ASC");
    $hist_q->bind_param("i", $selected_chat_user);
    $hist_q->execute();
    $chat_history = $hist_q->get_result()->fetch_all(MYSQLI_ASSOC);
    $hist_q->close();
    $user_q = $conn->prepare("SELECT id, username, name, email, account_balance, created_at FROM users WHERE id=?");
    $user_q->bind_param("i", $selected_chat_user);
    $user_q->execute();
    $chat_user_details = $user_q->get_result()->fetch_assoc();
    $user_q->close();
}

$view_ticket = null;
$view_ticket_type = null;
$ticket_followups = [];
$ticket_user_details = null;
$max_followup_id = 0;

if (isset($_GET['view_ticket'])) {
    $parts = explode('-', $_GET['view_ticket']);
    if (count($parts) === 2) {
        $view_ticket_type = $parts[0];
        $ticket_id = (int)$parts[1];
        if ($view_ticket_type === 'complaint') {
            $stmt = $conn->prepare("SELECT c.*, u.username, u.email, u.account_balance FROM complaints c JOIN users u ON c.user_id = u.id WHERE c.id=?");
        } elseif ($view_ticket_type === 'dispute') {
            $stmt = $conn->prepare("SELECT d.*, u.username, u.email, u.account_balance FROM disputes d JOIN users u ON d.user_id = u.id WHERE d.id=?");
        }
        if (isset($stmt)) {
            $stmt->bind_param("i", $ticket_id);
            $stmt->execute();
            $view_ticket = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        if ($view_ticket) {
            $ticket_user_details = $view_ticket;
            $stmt = $conn->prepare("SELECT tf.*, a.username as admin_name, u.username as user_name FROM ticket_followups tf LEFT JOIN admins a ON tf.admin_id = a.id LEFT JOIN users u ON tf.user_id = u.id WHERE tf.ticket_type=? AND tf.ticket_id=? ORDER BY tf.id ASC");
            $stmt->bind_param("si", $view_ticket_type, $ticket_id);
            $stmt->execute();
            $ticket_followups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($ticket_followups as $f) {
                $max_followup_id = max($max_followup_id, (int)$f['id']);
            }
        }
    }
}

$unread_admin_msgs = (int)($conn->query("SELECT COUNT(*) as c FROM admin_chats WHERE sender_type='user' AND is_read=0")->fetch_assoc()['c'] ?? 0);
$unread_users_q = $conn->query("SELECT DISTINCT c.user_id, u.username, MAX(c.created_at) as last_msg FROM admin_chats c JOIN users u ON c.user_id = u.id WHERE c.sender_type='user' AND c.is_read=0 GROUP BY c.user_id ORDER BY last_msg DESC LIMIT 5");
$unread_users = $unread_users_q ? $unread_users_q->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Admin Settings | GIBAL LTD</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root { --bg-base: #0b1120; --bg-surface: #111827; --bg-elevated: #1f2937; --bg-card: rgba(17, 24, 39, 0.7); --accent: #06b6d4; --accent-light: #22d3ee; --accent-dark: #0891b2; --accent-glow: rgba(6, 182, 212, 0.15); --accent-border: rgba(6, 182, 212, 0.25); --accent-2: #8b5cf6; --text-primary: #f1f5f9; --text-secondary: #cbd5e1; --text-tertiary: #64748b; --text-muted: #475569; --success: #10b981; --success-bg: rgba(16, 185, 129, 0.12); --success-border: rgba(16, 185, 129, 0.3); --warning: #f59e0b; --warning-bg: rgba(245, 158, 11, 0.12); --warning-border: rgba(245, 158, 11, 0.3); --danger: #ef4444; --danger-bg: rgba(239, 68, 68, 0.12); --danger-border: rgba(239, 68, 68, 0.3); --info: #3b82f6; --info-bg: rgba(59, 130, 246, 0.12); --info-border: rgba(59, 130, 246, 0.3); --border-subtle: rgba(148, 163, 184, 0.08); --border-medium: rgba(148, 163, 184, 0.15); --border-strong: rgba(148, 163, 184, 0.25); --radius-sm: 6px; --radius-md: 10px; --radius-lg: 14px; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: var(--bg-base); color: var(--text-primary); min-height: 100vh; background-image: linear-gradient(rgba(6, 182, 212, 0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(6, 182, 212, 0.02) 1px, transparent 1px); background-size: 40px 40px, 40px 40px; }
.topbar { position: sticky; top: 0; z-index: 1000; background: rgba(11, 17, 32, 0.95); backdrop-filter: blur(20px); border-bottom: 1px solid var(--accent-border); padding: 0 32px; height: 64px; display: flex; align-items: center; justify-content: space-between; }
.brand { display: flex; align-items: center; gap: 12px; color: var(--text-primary); text-decoration: none; font-weight: 700; }
.brand-icon { width: 36px; height: 36px; background: linear-gradient(135deg, var(--accent), var(--accent-dark)); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--bg-base); font-weight: 800; }
.brand-text { display: flex; flex-direction: column; line-height: 1.2; }
.brand-title { font-size: 1rem; } .brand-subtitle { font-size: 0.7rem; color: var(--accent); text-transform: uppercase; letter-spacing: 0.1em; }
.topbar-actions { display: flex; align-items: center; gap: 12px; }
.admin-info { color: var(--text-secondary); font-size: 0.85rem; padding: 6px 12px; background: var(--bg-elevated); border-radius: 50px; border: 1px solid var(--border-subtle); cursor: pointer; position: relative; }
.admin-info:hover { background: var(--bg-card); }
.admin-info strong { color: var(--accent-light); }
.sound-toggle { background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: 50px; padding: 6px 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: var(--text-secondary); font-size: 0.8rem; font-weight: 600; transition: all 0.2s; }
.sound-toggle:hover { background: var(--bg-card); color: var(--text-primary); }
.sound-toggle.active { background: var(--accent-glow); border-color: var(--accent); color: var(--accent-light); }
.sound-toggle i { font-size: 0.9rem; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: var(--radius-sm); font-weight: 600; font-size: 0.8rem; cursor: pointer; transition: 0.2s; border: none; text-decoration: none; text-transform: uppercase; }
.btn-outline { background: transparent; border: 1px solid var(--border-strong); color: var(--text-secondary); }
.btn-outline:hover { background: var(--bg-elevated); color: var(--accent-light); }
.btn-danger-outline { background: transparent; border: 1px solid var(--danger-border); color: var(--danger); }
.container-xl { max-width: 1400px; margin: 0 auto; padding: 24px 32px 60px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid var(--border-subtle); }
.page-header h2 { font-size: 1.5rem; display: flex; align-items: center; gap: 12px; }
.page-header h2 i { color: var(--accent); }
.live-indicator { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; background: var(--success-bg); border: 1px solid var(--success-border); border-radius: 50px; font-size: 0.75rem; font-weight: 600; color: var(--success); }
.live-indicator::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--success); animation: pulse 2s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid var(--border-subtle); overflow-x: auto; }
.tab-btn { padding: 12px 20px; background: transparent; border: none; color: var(--text-tertiary); font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: 0.2s; border-bottom: 2px solid transparent; white-space: nowrap; display: flex; align-items: center; gap: 8px; }
.tab-btn:hover { color: var(--text-primary); }
.tab-btn.active { color: var(--accent-light); border-bottom-color: var(--accent); }
.tab-content { display: none; animation: fadeIn 0.3s ease; }
.tab-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 16px; position: relative; overflow: hidden; }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--accent); opacity: 0.6; }
.stat-card.accent-2::before { background: var(--accent-2); } .stat-card.accent-success::before { background: var(--success); } .stat-card.accent-warning::before { background: var(--warning); }
.stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.stat-label { font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-tertiary); }
.stat-icon { width: 28px; height: 28px; border-radius: 6px; background: var(--accent-glow); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; }
.stat-card.accent-2 .stat-icon { background: rgba(139, 92, 246, 0.15); color: var(--accent-2); }
.stat-card.accent-success .stat-icon { background: var(--success-bg); color: var(--success); }
.stat-card.accent-warning .stat-icon { background: var(--warning-bg); color: var(--warning); }
.stat-value { font-size: 1.3rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; font-family: 'JetBrains Mono', monospace; }
.stat-value.accent { color: var(--accent-light); } .stat-value.accent-2 { color: var(--accent-2); } .stat-value.success { color: var(--success); } .stat-value.warning { color: var(--warning); }
.stat-hint { font-size: 0.65rem; color: var(--text-muted); }
.card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); margin-bottom: 24px; overflow: hidden; }
.card-header { padding: 18px 24px; border-bottom: 1px solid var(--border-subtle); background: rgba(6, 182, 212, 0.03); display: flex; justify-content: space-between; align-items: center; }
.card-title { font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 10px; text-transform: uppercase; }
.card-title i { color: var(--accent); }
.card-body { padding: 24px; }
.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-tertiary); margin-bottom: 8px; }
.form-input, .form-select, .form-textarea { width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border-medium); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 0.9rem; font-family: inherit; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
.form-textarea { resize: vertical; min-height: 80px; }
.btn-primary { background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: var(--bg-base); padding: 12px 24px; font-weight: 700; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px var(--accent-glow); }
.btn-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border); padding: 12px 24px; }
.table-responsive { overflow-x: auto; }
.scrollable-table { max-height: 400px; overflow-y: auto; }
.scrollable-table::-webkit-scrollbar { width: 8px; }
.scrollable-table::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 4px; }
table { width: 100%; border-collapse: collapse; min-width: 700px; }
thead { position: sticky; top: 0; z-index: 5; background: var(--bg-surface); }
th { padding: 12px 16px; text-align: left; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--accent-light); border-bottom: 2px solid var(--accent-border); }
td { padding: 12px 16px; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); }
tbody tr:hover { background: var(--bg-elevated); }
.badge { padding: 4px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
.badge-pending { background: var(--warning-bg); color: var(--warning); }
.badge-resolved, .badge-approved { background: var(--success-bg); color: var(--success); }
.badge-open { background: var(--info-bg); color: var(--info); }
.badge-rejected { background: var(--danger-bg); color: var(--danger); }
.badge-in_progress { background: var(--accent-glow); color: var(--accent-light); }
.chat-layout { display: grid; grid-template-columns: 250px 1fr; gap: 20px; height: 550px; }
.chat-sidebar { background: var(--bg-elevated); border-radius: var(--radius-md); overflow-y: auto; border: 1px solid var(--border-subtle); }
.chat-user-item { padding: 12px 16px; border-bottom: 1px solid var(--border-subtle); cursor: pointer; transition: 0.2s; text-decoration: none; color: var(--text-secondary); display: block; position: relative; }
.chat-user-item:hover, .chat-user-item.active { background: var(--accent-glow); color: var(--accent-light); }
.chat-user-item .unread-dot { position: absolute; top: 12px; right: 12px; width: 8px; height: 8px; background: var(--danger); border-radius: 50%; animation: pulse 2s infinite; }
.chat-main { display: flex; flex-direction: column; background: var(--bg-elevated); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); overflow: hidden; }
.chat-user-header { padding: 16px; border-bottom: 1px solid var(--border-subtle); background: rgba(6, 182, 212, 0.05); }
.chat-user-name { font-weight: 700; color: var(--accent-light); font-size: 1.1rem; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
.chat-user-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; font-size: 0.85rem; }
.chat-user-detail-item { display: flex; flex-direction: column; gap: 2px; }
.chat-user-detail-label { font-size: 0.7rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.05em; }
.chat-user-detail-value { color: var(--text-primary); font-weight: 600; }
.chat-messages { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
.chat-bubble { max-width: 70%; padding: 10px 14px; border-radius: 12px; font-size: 0.9rem; animation: slideIn 0.3s ease; }
@keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.chat-bubble.admin { align-self: flex-end; background: var(--accent); color: var(--bg-base); border-bottom-right-radius: 2px; }
.chat-bubble.user { align-self: flex-start; background: var(--bg-surface); color: var(--text-primary); border: 1px solid var(--border-subtle); border-bottom-left-radius: 2px; }
.chat-input-area { padding: 16px; border-top: 1px solid var(--border-subtle); display: flex; gap: 10px; }
.chat-input-area .form-input { flex: 1; margin: 0; }
.toast-container { position: fixed; top: 90px; right: 24px; z-index: 9999; }
.toast { background: var(--bg-surface); border: 1px solid var(--accent-border); border-left: 4px solid var(--accent); border-radius: var(--radius-md); padding: 14px 18px; margin-bottom: 10px; min-width: 300px; transform: translateX(450px); opacity: 0; transition: 0.4s; }
.toast.show { transform: translateX(0); opacity: 1; }
.toast.success { border-left-color: var(--success); } .toast.error { border-left-color: var(--danger); } .toast.warning { border-left-color: var(--warning); }
.user-dropdown { position: absolute; top: calc(100% + 8px); right: 0; background: var(--bg-surface); border: 1px solid var(--border-medium); border-radius: var(--radius-md); box-shadow: 0 20px 50px rgba(0,0,0,0.5); min-width: 280px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s ease; overflow: hidden; z-index: 1001; }
.user-dropdown.active { opacity: 1; visibility: visible; transform: translateY(0); }
.dropdown-header { padding: 16px; border-bottom: 1px solid var(--border-subtle); font-weight: 700; color: var(--text-primary); }
.dropdown-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; color: var(--text-secondary); text-decoration: none; font-size: 0.9rem; transition: all 0.15s ease; border-left: 3px solid transparent; }
.dropdown-item:hover { background: var(--bg-elevated); color: var(--text-primary); border-left-color: var(--accent); }
.dropdown-item i { width: 18px; color: var(--text-tertiary); margin-top: 3px; }
.radio-group { display: flex; gap: 20px; margin-bottom: 12px; }
.radio-label { display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); font-size: 0.9rem; }
.radio-label input { accent-color: var(--accent); width: 16px; height: 16px; cursor: pointer; }
.dispute-amount { font-family: 'JetBrains Mono', monospace; color: var(--warning); font-weight: 700; }
.ticket-detail-section { background: var(--bg-elevated); padding: 20px; border-radius: var(--radius-md); margin-bottom: 20px; }
.ticket-detail-section h4 { color: var(--accent-light); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.ticket-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
.ticket-info-item { display: flex; flex-direction: column; gap: 4px; }
.ticket-info-label { font-size: 0.75rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.05em; }
.ticket-info-value { font-weight: 600; color: var(--text-primary); }
.followup-thread { margin-bottom: 20px; }
.followup-item { padding: 16px; border-radius: var(--radius-md); margin-bottom: 12px; border-left: 3px solid; }
.followup-item.user { background: rgba(212, 175, 55, 0.1); border-left-color: var(--accent); }
.followup-item.admin { background: rgba(6, 182, 212, 0.1); border-left-color: var(--info); }
.followup-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.8rem; }
.followup-author { font-weight: 600; }
.followup-date { color: var(--text-tertiary); }
.followup-message { color: var(--text-primary); line-height: 1.6; white-space: pre-wrap; }
.type-feedback { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }
.ticket-chat {
    height: 380px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px;
    border-radius: 12px;
    background: rgba(15, 23, 42, 0.55);
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
    background: var(--accent);
    color: var(--bg-base);
}
.ticket-msg.theirs {
    align-self: flex-start;
    background: var(--bg-surface);
    border: 1px solid var(--border-subtle);
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
.ticket-msg-body {
    line-height: 1.5;
    white-space: pre-wrap;
}
.chat-empty {
    text-align: center;
    color: var(--text-tertiary);
    padding: 30px 10px;
}
.status-controls {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
</style>
</head>
<body>
<div class="topbar">
    <a href="admin_dashboard.php" class="brand">
        <div class="brand-icon">G</div>
        <div class="brand-text"><span class="brand-title">GIBAL LTD</span><span class="brand-subtitle">Admin Portal</span></div>
    </a>
    <div class="topbar-actions">
        <button class="sound-toggle active" id="soundToggle" onclick="toggleSound()">
            <i class="fas fa-bell" id="soundIcon"></i>
            <span id="soundLabel">Sound ON</span>
        </button>
        <div class="admin-info" onclick="toggleAdminNotif()" style="position: relative;">
            <i class="fas fa-bell" style="color: var(--accent); margin-right: 6px;"></i>
            <strong><?=htmlspecialchars($_SESSION['admin_username'] ?? 'admin')?></strong>
            <span class="badge" id="unreadBadge" style="position: absolute; top: -6px; right: -6px; background: var(--danger); color: white; padding: 2px 6px; border-radius: 50px; font-size: 0.65rem; display: <?= $unread_admin_msgs > 0 ? 'inline-block' : 'none' ?>;"><?=$unread_admin_msgs?></span>
        </div>
        <a href="admin_logout.php" class="btn btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <div class="user-dropdown" id="adminNotifDropdown">
            <div class="dropdown-header">New Messages</div>
            <div id="unreadUsersList">
                <?php if (empty($unread_users)): ?>
                    <div style="padding: 16px; text-align: center; color: var(--text-tertiary); font-size: 0.85rem;">No new messages</div>
                <?php else: ?>
                    <?php foreach ($unread_users as $u): ?>
                        <a href="?chat_user=<?=$u['user_id']?>#tab-chat" onclick="toggleAdminNotif()" class="dropdown-item">
                            <i class="fas fa-user" style="color: var(--accent);"></i>
                            <div>
                                <div style="font-weight: 600; color: var(--text-primary);"><?=htmlspecialchars($u['username'])?></div>
                                <div style="font-size: 0.75rem; color: var(--text-tertiary);">Sent a new message</div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<div class="container-xl">
    <div class="page-header">
        <h2><i class="fas fa-cogs"></i> Admin Settings & Operations</h2>
        <div class="live-indicator"><i class="fas fa-circle" style="font-size: 0.5rem;"></i> Live · Auto-refresh 5s</div>
    </div>

    <div class="tabs">
        <button class="tab-btn active" data-tab="performance"><i class="fas fa-chart-line"></i> Daily Performance</button>
        <button class="tab-btn" data-tab="complaints"><i class="fas fa-headset"></i> Support & Disputes <span id="supportBadge" style="background: var(--danger); color: white; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem;"><?=count(array_filter($complaints, fn($c) => $c['status'] === 'open')) + count(array_filter($disputes, fn($d) => $d['status'] === 'pending'))?></span></button>
        <button class="tab-btn" data-tab="chat"><i class="fas fa-comments"></i> Live Chat <span id="chatBadge" style="background: var(--danger); color: white; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; display: <?= $unread_admin_msgs > 0 ? 'inline-block' : 'none' ?>;"><?=$unread_admin_msgs?></span></button>
        <button class="tab-btn" data-tab="security"><i class="fas fa-shield-alt"></i> Admin Security</button>
        <button class="tab-btn" data-tab="broadcast"><i class="fas fa-bullhorn"></i> Broadcast & Email</button>
    </div>

    <!-- Tab 1: Daily Performance -->
    <div class="tab-content active" id="tab-performance">
        <div class="stats-grid">
            <div class="stat-card accent-success"><div class="stat-header"><div class="stat-label">Deposits Today</div><div class="stat-icon"><i class="fas fa-arrow-down"></i></div></div><div class="stat-value success">Ksh <?=number_format($dep_today, 2)?></div><div class="stat-hint">Completed transactions</div></div>
            <div class="stat-card accent-warning"><div class="stat-header"><div class="stat-label">Withdrawals Today</div><div class="stat-icon"><i class="fas fa-arrow-up"></i></div></div><div class="stat-value warning">Ksh <?=number_format($wd_today, 2)?></div><div class="stat-hint">Processed today</div></div>
            <div class="stat-card"><div class="stat-header"><div class="stat-label">Platform Profit Today</div><div class="stat-icon"><i class="fas fa-coins"></i></div></div><div class="stat-value accent">Ksh <?=number_format($total_profit_today, 2)?></div><div class="stat-hint">Fees + Matured Interest</div></div>
            <div class="stat-card accent-2"><div class="stat-header"><div class="stat-label">New Users Today</div><div class="stat-icon"><i class="fas fa-user-plus"></i></div></div><div class="stat-value accent-2"><?=number_format($new_users_today)?></div><div class="stat-hint">Registered accounts</div></div>
        </div>
    </div>

    <!-- Tab 2: Support & Disputes -->
    <div class="tab-content" id="tab-complaints">
        <?php if (!$view_ticket): ?>
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-comment-dots"></i> Complaints & Feedback</div></div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($complaints)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-tertiary);"><i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px;"></i><p>No complaints or feedback found.</p></div>
                    <?php else: ?>
                    <div class="table-responsive"><div class="scrollable-table">
                        <table>
                            <thead><tr><th>Type</th><th>User</th><th>Subject</th><th>Status</th><th>Follow-ups</th><th>Date</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($complaints as $c):
                                    $category = $c['category'] ?? 'complaint';
                                    $label = $category === 'feedback' ? 'Feedback' : 'Complaint';
                                    $type_class = $category === 'feedback' ? 'type-feedback' : '';
                                    $badge_class = 'badge-' . strtolower($c['status']);

                                    $cid = (int)$c['id'];
                                    $fu_q = $conn->prepare("SELECT COUNT(*) as c FROM ticket_followups WHERE ticket_type='complaint' AND ticket_id=?");
                                    $fu_q->bind_param("i", $cid);
                                    $fu_q->execute();
                                    $followup_count = (int)$fu_q->get_result()->fetch_assoc()['c'];
                                    $fu_q->close();
                                ?>
                                <tr>
                                    <td><span class="ticket-type <?= $type_class ?>"><?= htmlspecialchars($label) ?></span></td>
                                    <td><?=htmlspecialchars($c['username'] ?? 'Unknown')?></td>
                                    <td><?=htmlspecialchars($c['subject'])?></td>
                                    <td><span class="badge <?=$badge_class?>"><?=ucfirst(str_replace('_',' ',$c['status']))?></span></td>
                                    <td><span style="color: var(--accent-light); font-weight: 600;"><?=$followup_count?> messages</span></td>
                                    <td style="font-size: 0.8rem;"><?=date('M d, H:i', strtotime($c['created_at']))?></td>
                                    <td><a href="?view_ticket=complaint-<?=$c['id']?>#tab-complaints" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.75rem;"><i class="fas fa-eye"></i> View</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-exclamation-triangle"></i> Missing Deposit Disputes</div></div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($disputes)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-tertiary);"><i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px; color: var(--success);"></i><p>No disputes found.</p></div>
                    <?php else: ?>
                    <div class="table-responsive"><div class="scrollable-table">
                        <table>
                            <thead><tr><th>User</th><th>Transaction Ref</th><th>Amount</th><th>Status</th><th>Follow-ups</th><th>Date</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($disputes as $d):
                                    $badge_class = 'badge-' . strtolower($d['status']);

                                    $did = (int)$d['id'];
                                    $fu_q = $conn->prepare("SELECT COUNT(*) as c FROM ticket_followups WHERE ticket_type='dispute' AND ticket_id=?");
                                    $fu_q->bind_param("i", $did);
                                    $fu_q->execute();
                                    $followup_count = (int)$fu_q->get_result()->fetch_assoc()['c'];
                                    $fu_q->close();
                                ?>
                                <tr>
                                    <td><?=htmlspecialchars($d['username'] ?? 'Unknown')?></td>
                                    <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem;"><?=htmlspecialchars($d['transaction_ref'])?></td>
                                    <td class="dispute-amount">Ksh <?=number_format($d['amount'], 2)?></td>
                                    <td><span class="badge <?=$badge_class?>"><?=ucfirst(str_replace('_',' ',$d['status']))?></span></td>
                                    <td><span style="color: var(--accent-light); font-weight: 600;"><?=$followup_count?> messages</span></td>
                                    <td style="font-size: 0.8rem;"><?=date('M d, H:i', strtotime($d['created_at']))?></td>
                                    <td><a href="?view_ticket=dispute-<?=$d['id']?>#tab-complaints" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.75rem;"><i class="fas fa-eye"></i> View & Manage</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else:
            $ticket_label = $view_ticket_type === 'dispute'
                ? 'Dispute'
                : (($view_ticket['category'] ?? 'complaint') === 'feedback' ? 'Feedback' : 'Complaint');
        ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-ticket-alt"></i> Ticket #<?=(int)$view_ticket['id']?> - <?=htmlspecialchars($ticket_label)?></div>
                    <a href="admin_settings.php#tab-complaints" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.75rem;">← Back to List</a>
                </div>
                <div class="card-body">
                    <div class="ticket-detail-section">
                        <h4><i class="fas fa-user"></i> User Information</h4>
                        <div class="ticket-info-grid">
                            <div class="ticket-info-item"><div class="ticket-info-label">Username</div><div class="ticket-info-value"><?=htmlspecialchars($ticket_user_details['username'] ?? 'N/A')?></div></div>
                            <div class="ticket-info-item"><div class="ticket-info-label">Email</div><div class="ticket-info-value"><?=htmlspecialchars($ticket_user_details['email'] ?? 'N/A')?></div></div>
                            <div class="ticket-info-item"><div class="ticket-info-label">Current Balance</div><div class="ticket-info-value" style="color: var(--accent-light);">Ksh <?=number_format($ticket_user_details['account_balance'] ?? 0, 2)?></div></div>
                            <div class="ticket-info-item">
                                <div class="ticket-info-label">Status</div>
                                <div>
                                    <span id="adminTicketStatus" class="badge badge-<?=strtolower($view_ticket['status'])?>">
                                        <?=ucfirst(str_replace('_',' ',$view_ticket['status']))?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ticket-detail-section">
                        <h4><i class="fas fa-info-circle"></i> Ticket Details</h4>
                        <?php if ($view_ticket_type === 'complaint'): ?>
                            <div style="margin-bottom: 12px;"><strong>Subject:</strong> <?=htmlspecialchars($view_ticket['subject'])?></div>
                        <?php else: ?>
                            <div style="margin-bottom: 12px;"><strong>Transaction Ref:</strong> <span style="font-family: 'JetBrains Mono', monospace;"><?=htmlspecialchars($view_ticket['transaction_ref'])?></span></div>
                            <div style="margin-bottom: 12px;"><strong>Amount:</strong> <span style="color: var(--warning); font-weight: 700;">Ksh <?=number_format($view_ticket['amount'], 2)?></span></div>
                        <?php endif; ?>
                        <div style="margin-bottom: 12px;"><strong>Submitted:</strong> <?=date('M d, Y H:i', strtotime($view_ticket['created_at']))?></div>
                        <div style="margin-top: 16px; padding: 16px; background: rgba(30, 41, 59, 0.5); border-radius: 8px;">
                            <strong>Original Message:</strong><br><?=nl2br(htmlspecialchars($view_ticket['message']))?>
                        </div>
                        <?php if (!empty($view_ticket['admin_response'])): ?>
                            <div style="margin-top: 16px; padding: 16px; background: rgba(59, 130, 246, 0.1); border-radius: 8px; border-left: 3px solid var(--info);">
                                <strong style="color: var(--info);">Last Admin Response:</strong><br><?=nl2br(htmlspecialchars($view_ticket['admin_response']))?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="ticket-detail-section">
                        <h4><i class="fas fa-comments"></i> Live Conversation</h4>
                        <div class="ticket-chat" id="adminTicketChat">
                            <?php if (empty($ticket_followups)): ?>
                                <div class="chat-empty">No messages yet. Send the first response below.</div>
                            <?php else: foreach ($ticket_followups as $f): ?>
                                <?php
                                $is_admin_msg = !empty($f['admin_id']);
                                $class = $is_admin_msg ? 'mine' : 'theirs';
                                $sender = $is_admin_msg ? ($f['admin_name'] ?: 'Admin') : ($f['user_name'] ?: 'User');
                                ?>
                                <div class="ticket-msg <?= $class ?>" data-id="<?= (int)$f['id'] ?>">
                                    <div class="ticket-msg-meta">
                                        <span><?=htmlspecialchars($sender)?></span>
                                        <span><?=date('M d, Y H:i', strtotime($f['created_at']))?></span>
                                    </div>
                                    <div class="ticket-msg-body"><?=nl2br(htmlspecialchars($f['message']))?></div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>

                        <form id="adminTicketReplyForm" method="POST" action="admin_settings.php">
                            <input type="hidden" name="ticket_type" value="<?=htmlspecialchars($view_ticket_type)?>">
                            <input type="hidden" name="ticket_id" value="<?= (int)$view_ticket['id'] ?>">
                            <div class="form-group">
                                <textarea name="admin_message" id="adminTicketReplyInput" class="form-textarea" rows="4" placeholder="Type your response to the user..." required></textarea>
                            </div>
                            <button type="submit" name="admin_ticket_followup" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Response</button>
                        </form>
                    </div>

                    <div class="ticket-detail-section">
                        <h4><i class="fas fa-sliders-h"></i> Ticket Controls</h4>
                        <div class="status-controls">
                            <?php if ($view_ticket_type === 'dispute'): ?>
                                <button type="button" class="btn btn-outline status-btn" data-status="pending">Pending</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline status-btn" data-status="open">Open</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline status-btn" data-status="in_progress">In Progress</button>
                            <button type="button" class="btn btn-success status-btn" data-status="resolved">Resolve</button>
                            <button type="button" class="btn btn-danger-outline status-btn" data-status="rejected">Reject</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab 3: Live Chat -->
    <div class="tab-content" id="tab-chat">
        <div class="card"><div class="card-header"><div class="card-title"><i class="fas fa-comments"></i> Direct User Communication</div></div><div class="card-body"><div class="chat-layout">
            <div class="chat-sidebar">
                <div style="padding: 12px 16px; font-weight: 700; color: var(--text-tertiary); font-size: 0.8rem; text-transform: uppercase;">Select User</div>
                <?php foreach ($chat_users as $u):
                    $has_unread = false;
                    foreach ($unread_users as $uu) { if ($uu['user_id'] == $u['id']) { $has_unread = true; break; } }
                ?>
                    <a href="?chat_user=<?=$u['id']?>#tab-chat" class="chat-user-item <?=$selected_chat_user == $u['id'] ? 'active' : ''?>">
                        <div style="font-weight: 600; color: var(--text-primary);"><?=htmlspecialchars($u['username'])?></div>
                        <div style="font-size: 0.75rem; color: var(--text-tertiary);"><?=htmlspecialchars($u['name'])?></div>
                        <?php if ($has_unread): ?><span class="unread-dot"></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="chat-main">
                <?php if ($selected_chat_user > 0 && $chat_user_details): ?>
                    <div class="chat-user-header">
                        <div class="chat-user-name"><i class="fas fa-user-circle"></i> <?=htmlspecialchars($chat_user_details['username'])?></div>
                        <div class="chat-user-details">
                            <div class="chat-user-detail-item"><span class="chat-user-detail-label">Full Name</span><span class="chat-user-detail-value"><?=htmlspecialchars($chat_user_details['name'])?></span></div>
                            <div class="chat-user-detail-item"><span class="chat-user-detail-label">Email</span><span class="chat-user-detail-value"><?=htmlspecialchars($chat_user_details['email'])?></span></div>
                            <div class="chat-user-detail-item"><span class="chat-user-detail-label">Balance</span><span class="chat-user-detail-value" style="color: var(--accent-light);">Ksh <?=number_format($chat_user_details['account_balance'], 2)?></span></div>
                            <div class="chat-user-detail-item"><span class="chat-user-detail-label">Member Since</span><span class="chat-user-detail-value"><?=date('M d, Y', strtotime($chat_user_details['created_at']))?></span></div>
                        </div>
                    </div>
                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($chat_history)): ?>
                            <div style="text-align: center; color: var(--text-tertiary); margin-top: 40px;">No messages yet. Start the conversation!</div>
                        <?php else: ?>
                            <?php foreach ($chat_history as $msg): ?>
                                <div class="chat-bubble <?=$msg['sender_type']?>">
                                    <?=htmlspecialchars($msg['message'])?>
                                    <div style="font-size: 0.65rem; opacity: 0.7; margin-top: 4px; text-align: right;"><?=date('H:i', strtotime($msg['created_at']))?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <form id="chatForm" class="chat-input-area" onsubmit="sendChatMessage(event)">
                        <input type="hidden" name="chat_user_id" value="<?=$selected_chat_user?>">
                        <input type="text" id="chatMessageInput" class="form-input" placeholder="Type your message..." required autocomplete="off">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
                    </form>
                <?php else: ?>
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-tertiary);">
                        <div style="text-align: center;"><i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i><p>Select a user from the left to start chatting.</p></div>
                    </div>
                <?php endif; ?>
            </div>
        </div></div></div>
    </div>

    <!-- Tab 4: Admin Security -->
    <div class="tab-content" id="tab-security">
        <div class="card"><div class="card-header"><div class="card-title"><i class="fas fa-shield-alt"></i> Secure Password Change (Email 2FA + Supervisor)</div></div><div class="card-body">
            <div class="info-box" style="background: var(--info-bg); border: 1px solid var(--info-border); border-left: 4px solid var(--info); padding: 16px; border-radius: var(--radius-sm); margin-bottom: 20px; color: var(--text-secondary); font-size: 0.9rem;"><strong style="color: var(--info);"><i class="fas fa-info-circle"></i> Security Protocol:</strong> 1. Enter your new password. A 6-digit code will be emailed to <strong><?=htmlspecialchars($admin_email ?: 'your registered email')?></strong>.<br>2. Enter the code to verify.<br>3. Request sent to Super Admin for final approval.</div>
            <?php if (isset($_SESSION['pending_req_id'])): ?>
                <h4 style="margin-bottom: 15px; color: var(--warning);"><i class="fas fa-envelope"></i> Step 2: Verify Email Code</h4>
                <form method="POST"><div class="form-group"><label class="form-label">Enter 6-Digit Code from Email</label><input type="text" name="twofa_code" class="form-input" placeholder="e.g., 123456" maxlength="6" required style="font-family: 'JetBrains Mono'; font-size: 1.2rem; letter-spacing: 4px; text-align: center;"></div><button type="submit" name="verify_2fa" class="btn btn-primary"><i class="fas fa-check-circle"></i> Verify & Submit to Supervisor</button></form>
            <?php else: ?>
                <h4 style="margin-bottom: 15px; color: var(--accent-light);"><i class="fas fa-lock"></i> Step 1: Request Password Change</h4>
                <form method="POST"><div class="form-group"><label class="form-label">New Admin Password</label><input type="password" name="new_password" class="form-input" placeholder="Minimum 8 characters" required minlength="8"></div><button type="submit" name="request_password_change" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send 2FA Code to Email</button></form>
            <?php endif; ?>
        </div></div>
    </div>

    <!-- Tab 5: Broadcast & Email -->
    <div class="tab-content" id="tab-broadcast">
        <div class="card"><div class="card-header"><div class="card-title"><i class="fas fa-bell"></i> Send Platform Notification</div></div><div class="card-body">
            <div class="info-box" style="background: var(--info-bg); border: 1px solid var(--info-border); border-left: 4px solid var(--info); padding: 16px; border-radius: var(--radius-sm); margin-bottom: 20px; color: var(--text-secondary); font-size: 0.9rem;"><strong style="color: var(--info);"><i class="fas fa-info-circle"></i> Note:</strong> This notification will appear as a banner at the top of the user's dashboard.</div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Send To</label>
                    <div class="radio-group">
                        <label class="radio-label"><input type="radio" name="notif_target" value="all" checked onchange="toggleTargetSelect('notif')"> All Users</label>
                        <label class="radio-label"><input type="radio" name="notif_target" value="single" onchange="toggleTargetSelect('notif')"> Specific User</label>
                    </div>
                    <select name="notif_target_user" id="notif_target_user" class="form-select" style="display: none;">
                        <option value="">-- Select User --</option>
                        <?php foreach ($chat_users as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['name']) ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Notification Type</label><select name="notif_type" class="form-select"><option value="info">ℹ️ Info (Blue)</option><option value="success">✅ Success (Green)</option><option value="warning">⚠️ Warning (Amber)</option><option value="urgent">🚨 Urgent (Red)</option></select></div>
                <div class="form-group"><label class="form-label">Title</label><input type="text" name="notif_title" class="form-input" placeholder="e.g., New Investment Plan Available!" required maxlength="100"></div>
                <div class="form-group"><label class="form-label">Message</label><textarea name="notif_message" class="form-textarea" placeholder="Write your announcement here..." required></textarea></div>
                <button type="submit" name="send_notification" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Notification</button>
            </form>
        </div></div>
        <div class="card"><div class="card-header"><div class="card-title"><i class="fas fa-envelope-open-text"></i> Send Email</div></div><div class="card-body">
            <div class="info-box" style="background: var(--warning-bg); border: 1px solid var(--warning-border); border-left: 4px solid var(--warning); padding: 16px; border-radius: var(--radius-sm); margin-bottom: 20px; color: var(--text-secondary); font-size: 0.9rem;"><strong style="color: var(--warning);"><i class="fas fa-exclamation-triangle"></i> Placeholders:</strong> Use <code style="background: var(--bg-elevated); padding: 2px 6px; border-radius: 4px;">{{name}}</code> and <code style="background: var(--bg-elevated); padding: 2px 6px; border-radius: 4px;">{{email}}</code> in your email body.</div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Send To</label>
                    <div class="radio-group">
                        <label class="radio-label"><input type="radio" name="email_target" value="all" checked onchange="toggleTargetSelect('email')"> All Users</label>
                        <label class="radio-label"><input type="radio" name="email_target" value="single" onchange="toggleTargetSelect('email')"> Specific User</label>
                    </div>
                    <select name="email_target_user" id="email_target_user" class="form-select" style="display: none;">
                        <option value="">-- Select User --</option>
                        <?php foreach ($chat_users as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['name']) ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Email Subject</label><input type="text" name="email_subject" class="form-input" placeholder="e.g., Important Account Update" required></div>
                <div class="form-group"><label class="form-label">Email Body (HTML supported)</label><textarea name="email_body" class="form-textarea" style="min-height: 180px;" placeholder="Hello {{name}},<br><br>Your message here..." required></textarea></div>
                <button type="submit" name="send_bulk_email" class="btn btn-primary" onclick="return confirm('Are you sure you want to send this email?');"><i class="fas fa-envelope"></i> Send Email</button>
            </form>
        </div></div>
    </div>
</div>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
});

window.addEventListener('load', () => {
    const hash = window.location.hash;
    const urlParams = new URLSearchParams(window.location.search);
    let targetTab = '';
    if (urlParams.has('view_ticket')) {
        targetTab = 'complaints';
    } else if (hash) {
        targetTab = hash.replace('#tab-', '');
    }
    if (targetTab) {
        const tabBtn = document.querySelector('[data-tab="' + targetTab + '"]');
        if (tabBtn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            tabBtn.classList.add('active');
            document.getElementById('tab-' + targetTab).classList.add('active');
        }
    }
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
});

function toggleAdminNotif() { document.getElementById('adminNotifDropdown').classList.toggle('active'); }
document.addEventListener('click', function(e) {
    if (!e.target.closest('.admin-info') && !e.target.closest('#adminNotifDropdown')) {
        document.getElementById('adminNotifDropdown').classList.remove('active');
    }
});
function toggleTargetSelect(type) {
    const isSingle = document.querySelector('input[name="' + type + '_target"]:checked').value === 'single';
    document.getElementById(type + '_target_user').style.display = isSingle ? 'block' : 'none';
}
let soundEnabled = localStorage.getItem('adminSoundEnabled') !== 'false';
function toggleSound() {
    soundEnabled = !soundEnabled;
    localStorage.setItem('adminSoundEnabled', soundEnabled);
    updateSoundButton();
    if (soundEnabled) playNotificationSound();
}
function updateSoundButton() {
    const btn = document.getElementById('soundToggle');
    const icon = document.getElementById('soundIcon');
    const label = document.getElementById('soundLabel');
    if (soundEnabled) { btn.classList.add('active'); icon.className = 'fas fa-bell'; label.textContent = 'Sound ON'; }
    else { btn.classList.remove('active'); icon.className = 'fas fa-bell-slash'; label.textContent = 'Sound OFF'; }
}
function playNotificationSound() {
    if (!soundEnabled) return;
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        oscillator.connect(gainNode); gainNode.connect(audioCtx.destination);
        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(880, audioCtx.currentTime);
        oscillator.frequency.setValueAtTime(1108.73, audioCtx.currentTime + 0.15);
        gainNode.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.4);
        oscillator.start(audioCtx.currentTime); oscillator.stop(audioCtx.currentTime + 0.4);
    } catch (e) { console.log('Sound playback failed:', e); }
}
updateSoundButton();
const selectedChatUser = <?=$selected_chat_user?>;
function sendChatMessage(event) {
    event.preventDefault();
    const input = document.getElementById('chatMessageInput');
    const message = input.value.trim();
    if (!message || !selectedChatUser) return;
    const chatMessages = document.getElementById('chatMessages');
    const emptyMsg = chatMessages.querySelector('div[style*="text-align: center"]');
    if (emptyMsg) emptyMsg.remove();
    const now = new Date();
    const timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
    const newBubble = document.createElement('div');
    newBubble.className = 'chat-bubble admin';
    newBubble.innerHTML = escapeHtml(message) + '<div style="font-size: 0.65rem; opacity: 0.7; margin-top: 4px; text-align: right;">' + timeStr + '</div>';
    chatMessages.appendChild(newBubble);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    input.value = '';
    const formData = new FormData();
    formData.append('ajax_send_chat', '1');
    formData.append('chat_user_id', selectedChatUser);
    formData.append('chat_message', message);
    fetch('admin_settings.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) { showToast('Message sent', 'success'); }
        else { showToast('Failed to send message', 'error'); newBubble.remove(); input.value = message; }
    })
    .catch(err => { console.error(err); showToast('Network error', 'error'); });
}
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
let previousUnreadCount = <?=$unread_admin_msgs?>;
function autoRefresh() {
    const url = 'admin_settings.php?ajax=1&chat_user=' + selectedChatUser;
    fetch(url).then(r => r.json()).then(data => {
        if (!data.success) return;
        const unreadBadge = document.getElementById('unreadBadge');
        if (data.unread_msgs > 0) { unreadBadge.style.display = 'inline-block'; unreadBadge.textContent = data.unread_msgs; }
        else { unreadBadge.style.display = 'none'; }
        const chatBadge = document.getElementById('chatBadge');
        if (data.unread_msgs > 0) { chatBadge.style.display = 'inline-block'; chatBadge.textContent = data.unread_msgs; }
        else { chatBadge.style.display = 'none'; }
        const supportBadge = document.getElementById('supportBadge');
        const totalSupport = data.open_complaints + data.pending_disputes;
        if (totalSupport > 0) { supportBadge.style.display = 'inline-block'; supportBadge.textContent = totalSupport; }
        else { supportBadge.style.display = 'none'; }
        if (selectedChatUser > 0 && data.chat_html) {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                const currentCount = chatMessages.querySelectorAll('.chat-bubble').length;
                const newCount = (data.chat_html.match(/chat-bubble/g) || []).length;
                if (newCount > currentCount) {
                    chatMessages.innerHTML = data.chat_html;
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                    if (soundEnabled) playNotificationSound();
                    showToast('💬 New message received', 'info');
                }
            }
        }
        const unreadUsersList = document.getElementById('unreadUsersList');
        if (unreadUsersList && data.unread_users) {
            if (data.unread_users.length === 0) {
                unreadUsersList.innerHTML = '<div style="padding: 16px; text-align: center; color: var(--text-tertiary); font-size: 0.85rem;">No new messages</div>';
            } else {
                let html = '';
                data.unread_users.forEach(u => {
                    html += '<a href="?chat_user=' + u.user_id + '#tab-chat" onclick="toggleAdminNotif()" class="dropdown-item"><i class="fas fa-user" style="color: var(--accent);"></i><div><div style="font-weight: 600; color: var(--text-primary);">' + escapeHtml(u.username) + '</div><div style="font-size: 0.75rem; color: var(--text-tertiary);">Sent a new message</div></div></a>';
                });
                unreadUsersList.innerHTML = html;
            }
        }
        if (data.unread_msgs > previousUnreadCount && soundEnabled) playNotificationSound();
        previousUnreadCount = data.unread_msgs;
    }).catch(err => console.error('Auto-refresh failed:', err));
}
setInterval(autoRefresh, 5000);
function showToast(message, type) {
    type = type || 'info';
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<div style="font-weight: 600; font-size: 0.9rem;">' + message + '</div>';
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 4000);
}
<?php if (!empty($toast_message)): ?>
window.addEventListener('load', () => showToast('<?=addslashes($toast_message)?>', '<?=$toast_type?>'));
<?php endif; ?>

// ============================================
// LIVE TICKET CHAT FOR ADMIN
// ============================================
const adminTicketApiType = '<?= isset($view_ticket_type) ? htmlspecialchars($view_ticket_type) : '' ?>';
const adminTicketId = <?= isset($view_ticket) && $view_ticket ? (int)$view_ticket['id'] : 0 ?>;
let adminTicketLastId = <?= isset($max_followup_id) ? (int)$max_followup_id : 0 ?>;

function prettyAdminStatus(status) {
    return status.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
}

function adminTicketAppend(html) {
    const chat = document.getElementById('adminTicketChat');
    if (!chat) return;
    const empty = chat.querySelector('.chat-empty');
    if (empty) empty.remove();
    chat.insertAdjacentHTML('beforeend', html);
    chat.scrollTop = chat.scrollHeight;
}

function adminTicketSetStatus(status) {
    const badge = document.getElementById('adminTicketStatus');
    if (!badge) return;
    badge.className = 'badge badge-' + status;
    badge.textContent = prettyAdminStatus(status);
}

async function adminTicketPoll() {
    if (!adminTicketId || document.hidden) return;

    try {
        const res = await fetch('ticket_api.php?action=messages&context=admin&ticket_type=' + encodeURIComponent(adminTicketApiType) + '&ticket_id=' + adminTicketId + '&last_id=' + adminTicketLastId);
        const data = await res.json();

        if (data.success) {
            if (data.html) {
                adminTicketAppend(data.html);
                if (soundEnabled) playNotificationSound();
                showToast('💬 New ticket message', 'info');
            }
            if (data.last_id) adminTicketLastId = data.last_id;
            if (data.status) adminTicketSetStatus(data.status);
        }
    } catch (e) {
        console.error('Ticket poll failed', e);
    }
}

if (adminTicketId > 0) {
    setInterval(adminTicketPoll, 3000);
}

const adminTicketReplyForm = document.getElementById('adminTicketReplyForm');
if (adminTicketReplyForm) {
    adminTicketReplyForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const input = document.getElementById('adminTicketReplyInput');
        const message = input.value.trim();
        if (!message) return;

        input.disabled = true;

        try {
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('context', 'admin');
            fd.append('ticket_type', adminTicketApiType);
            fd.append('ticket_id', adminTicketId);
            fd.append('message', message);

            const res = await fetch('ticket_api.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                adminTicketAppend(data.html);
                adminTicketLastId = data.last_id;
                adminTicketSetStatus(data.status);
                input.value = '';
                showToast('✅ Response sent', 'success');
            } else {
                showToast('❌ ' + (data.message || 'Failed to send'), 'error');
            }
        } catch (err) {
            showToast('❌ Network error', 'error');
        } finally {
            input.disabled = false;
            input.focus();
        }
    });
}

document.querySelectorAll('.status-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
        if (!adminTicketId) return;

        const status = this.dataset.status;
        this.disabled = true;

        try {
            const fd = new FormData();
            fd.append('action', 'update_status');
            fd.append('context', 'admin');
            fd.append('ticket_type', adminTicketApiType);
            fd.append('ticket_id', adminTicketId);
            fd.append('status', status);

            const res = await fetch('ticket_api.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                adminTicketSetStatus(data.status);
                showToast('✅ Status updated to ' + prettyAdminStatus(data.status), 'success');
            } else {
                showToast('❌ ' + (data.message || 'Failed to update status'), 'error');
            }
        } catch (err) {
            showToast('❌ Network error', 'error');
        } finally {
            this.disabled = false;
        }
    });
});
</script>
</body>
</html>