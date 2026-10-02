<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("db_connect.php");

if (file_exists(__DIR__ . '/log_admin_action.php')) {
    include_once __DIR__ . '/log_admin_action.php';
}

$phpmailer_ready = false;
foreach ([
    'src/Exception.php',
    'src/PHPMailer.php',
    'src/SMTP.php'
] as $pm_file) {
    if (file_exists(__DIR__ . '/' . $pm_file)) {
        require_once __DIR__ . '/' . $pm_file;
    }
}

$phpmailer_ready = class_exists('PHPMailer\PHPMailer\PHPMailer');

// Protect admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_id = (int)$_SESSION['admin_id'];

// CSRF token
if (empty($_SESSION['wr_csrf'])) {
    $_SESSION['wr_csrf'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['wr_csrf'];

// Filters
$allowed_statuses = ['all', 'pending', 'approved', 'rejected', 'dispute'];
$status_filter = strtolower(trim($_GET['status'] ?? 'all'));
if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = 'all';
}

$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

if ($start_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = '';
}

if ($end_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = '';
}

$redirect_query = http_build_query([
    'status' => $status_filter,
    'start_date' => $start_date,
    'end_date' => $end_date
]);

// ================================
// EMAIL SETTINGS
// ================================
// Set to true only if Gmail SMTP is confirmed working on your hosting.
// If approvals feel stuck/slow, keep this false.
$send_withdrawal_emails = false;

$mailUsername = 'gibal.ltd@gmail.com';
$mailPassword = 'dkbcereljkmvzfqy';
$mailFromName = 'GIBAL LTD';
$supportEmail = 'gibal.ltd@gmail.com';
$supportPhone = '+254703834247';

function wr_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function wr_time_ago($value)
{
    $ts = strtotime((string)$value);
    if (!$ts) {
        return '—';
    }

    $diff = time() - $ts;
    if ($diff < 0) {
        $diff = 0;
    }

    if ($diff < 60) {
        return $diff . 's ago';
    }

    $mins = (int)floor($diff / 60);
    if ($mins < 60) {
        return $mins . 'm ago';
    }

    $hours = (int)floor($mins / 60);
    if ($hours < 24) {
        return $hours . 'h ago';
    }

    $days = (int)floor($hours / 24);
    return $days . 'd ago';
}

function wr_elapsed_minutes($value)
{
    $ts = strtotime((string)$value);
    if (!$ts) {
        return null;
    }

    return (time() - $ts) / 60;
}

function wr_column_exists($conn, $table, $column)
{
    static $cache = [];

    $table = (string)$table;
    $column = (string)$column;
    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $exists = false;

    if (
        !preg_match('/^[A-Za-z0-9_]+$/', $table) ||
        !preg_match('/^[A-Za-z0-9_]+$/', $column)
    ) {
        $cache[$key] = false;
        return false;
    }

    if (is_object($conn) && method_exists($conn, 'prepare')) {
        try {
            $stmt = @$conn->prepare(
                "SELECT 1
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND column_name = ?
                 LIMIT 1"
            );

            if ($stmt) {
                $stmt->bind_param("ss", $table, $column);
                $stmt->execute();

                if (method_exists($stmt, 'get_result')) {
                    $res = $stmt->get_result();
                    $exists = $res && $res->num_rows > 0;
                } else {
                    $exists = $stmt->affected_rows > 0;
                }

                $stmt->close();
            }
        } catch (\Throwable $e) {
            $exists = false;
        }
    }

    if (!$exists && is_object($conn) && method_exists($conn, 'query')) {
        try {
            $res = @$conn->query("SELECT `$column` FROM `$table` LIMIT 1");
            $exists = ($res !== false);
        } catch (\Throwable $e) {
            $exists = false;
        }
    }

    $cache[$key] = $exists;
    return $exists;
}

function wr_fetch_stmt($stmt)
{
    if (!$stmt) {
        return null;
    }

    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        return $res ? $res->fetch_assoc() : null;
    }

    return null;
}

function wr_row_classes($status, $created_at)
{
    $status = strtolower(trim((string)$status));
    $classes = ['wr-row', 'status-' . $status];

    if ($status === 'pending') {
        $mins = wr_elapsed_minutes($created_at);

        if ($mins !== null && $mins > 20) {
            $classes[] = 'flash-red';
        } elseif ($mins !== null && $mins > 10) {
            $classes[] = 'flash-yellow';
        } else {
            $classes[] = 'fresh-pending';
        }
    } elseif ($status === 'approved') {
        $classes[] = 'approved-row';
    } elseif ($status === 'rejected') {
        $classes[] = 'rejected-row';
    } elseif ($status === 'dispute') {
        $classes[] = 'dispute-row';
    }

    return implode(' ', $classes);
}

function sendWithdrawalEmail($userEmail, $userName, $amount, $withdraw_id, $status)
{
    global $phpmailer_ready, $send_withdrawal_emails;
    global $mailUsername, $mailPassword, $mailFromName, $supportEmail, $supportPhone;

    if (!$phpmailer_ready || !$send_withdrawal_emails || empty($userEmail)) {
        return;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailUsername;
        $mail->Password   = $mailPassword;

        // 587/STARTTLS is usually more compatible than 465 on shared hosting.
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 8;

        $mail->setFrom($mailUsername, $mailFromName);
        $mail->addAddress($userEmail, $userName);
        $mail->isHTML(true);

        $tz = new DateTimeZone('Africa/Nairobi');
        $now = new DateTime('now', $tz);
        $formattedDate = $now->format('l, d F Y H:i:s T');

        switch ($status) {
            case 'pending':
                $subject = "Withdrawal Request Received — GIBAL LTD";
                $color = "#FFD700";
                $statusText = "Pending Review";
                $header = "Withdrawal Request Received";
                $bodyText = "We have received your withdrawal request and it is currently <strong>pending review</strong>.";
                break;

            case 'approved':
                $subject = "Withdrawal Approved — GIBAL LTD";
                $color = "#28a745";
                $statusText = "Approved";
                $header = "Withdrawal Approved";
                $bodyText = "Your withdrawal request has been <strong>approved</strong> and funds have been processed.";
                break;

            case 'rejected':
                $subject = "Withdrawal Rejected — GIBAL LTD";
                $color = "#dc3545";
                $statusText = "Rejected";
                $header = "Withdrawal Rejected";
                $bodyText = "We regret to inform you that your withdrawal request has been <strong>rejected</strong>.";
                break;

            default:
                return;
        }

        $body = "<div style='font-family:Arial,sans-serif; max-width:600px; margin:auto; padding:20px; border:2px solid {$color}; border-radius:12px; background:#000; color:{$color};'>";
        $body .= "<h2 style='text-align:center; margin-bottom:20px;'>" . wr_h($header) . "</h2>";
        $body .= "<p>Hi <strong>" . wr_h($userName) . "</strong>,</p>";
        $body .= "<p>" . $bodyText . "</p>";

        $body .= "<table style='width:100%; margin-top:15px; border-collapse:collapse;'>";
        $body .= "<tr><td style='padding:8px; border-bottom:1px solid {$color};'>Amount</td><td style='padding:8px; border-bottom:1px solid {$color};'>Ksh " . number_format($amount, 2) . "</td></tr>";
        $body .= "<tr><td style='padding:8px; border-bottom:1px solid {$color};'>Reference ID</td><td style='padding:8px; border-bottom:1px solid {$color};'>" . (int)$withdraw_id . "</td></tr>";
        $body .= "<tr><td style='padding:8px;'>Date & Time</td><td style='padding:8px;'>" . wr_h($formattedDate) . "</td></tr>";
        $body .= "<tr><td style='padding:8px;'>Status</td><td style='padding:8px;'>" . wr_h($statusText) . "</td></tr>";
        $body .= "</table>";

        $body .= "<p style='text-align:center; margin-top:25px;'>";
        $body .= "<a href='https://yourdomain.com/dashboard.php' style='display:inline-block; padding:12px 25px; background:{$color}; color:#000; border-radius:6px; text-decoration:none; font-weight:bold;'>Go to Dashboard</a>";
        $body .= "</p>";

        if ($status === 'rejected') {
            $body .= "<p>If you believe this is an error, contact us at <a href='mailto:" . wr_h($supportEmail) . "'>" . wr_h($supportEmail) . "</a> or call " . wr_h($supportPhone) . ".</p>";
        }

        $body .= "<p>Thank you for trusting <strong>GIBAL LTD</strong>!</p></div>";

        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = "Hi " . $userName . ",\n\nYour withdrawal request of Ksh " . number_format($amount, 2) . " (Reference ID: " . $withdraw_id . ") is " . $statusText . " on " . $formattedDate . ".\nGo to Dashboard: https://yourdomain.com/dashboard.php\n\nThank you, GIBAL LTD";

        $mail->send();
    } catch (\Throwable $e) {
        error_log("Withdrawal email error ({$status}): " . $e->getMessage());
    }
}

function wr_send_email_for_action($conn, $email_data)
{
    global $send_withdrawal_emails;

    if (!$send_withdrawal_emails || empty($email_data)) {
        return;
    }

    $user_id = (int)($email_data['user_id'] ?? 0);
    $amount = (float)($email_data['amount'] ?? 0);
    $status = (string)($email_data['status'] ?? '');

    if ($user_id <= 0 || $status === '') {
        return;
    }

    $stmt = $conn->prepare("SELECT username, name, email FROM users WHERE id=? LIMIT 1");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $user = wr_fetch_stmt($stmt);
    $stmt->close();

    if (!$user) {
        return;
    }

    $display_name = !empty($user['name']) ? $user['name'] : ($user['username'] ?? ('User #' . $user_id));
    $email = $user['email'] ?? '';

    if (!empty($email)) {
        sendWithdrawalEmail($email, $display_name, $amount, $user_id, $status);
    }
}

function wr_process_action($conn, $admin_id, $wid, $action, &$msg, &$email_data)
{
    $email_data = [];
    $wid = (int)$wid;

    $allowed_actions = ['approve', 'reject', 'dispute', 'release', 'deduct'];

    if ($wid <= 0) {
        $msg = "Invalid withdrawal ID.";
        return false;
    }

    if (!in_array($action, $allowed_actions, true)) {
        $msg = "Invalid action.";
        return false;
    }

    $stmt = $conn->prepare("SELECT user_id, amount, status FROM withdrawals WHERE id=? LIMIT 1");
    if (!$stmt) {
        $msg = "Database error: " . $conn->error;
        return false;
    }

    $stmt->bind_param("i", $wid);
    $stmt->execute();

    $w_row = wr_fetch_stmt($stmt);
    $stmt->close();

    if (!$w_row) {
        $msg = "Withdrawal not found.";
        return false;
    }

    $user_id = (int)($w_row['user_id'] ?? 0);
    $amount = (float)($w_row['amount'] ?? 0);
    $current_status = strtolower(trim((string)($w_row['status'] ?? '')));

    $new_status = '';
    $should_deduct = false;

    if ($action === 'approve' && $current_status === 'pending') {
        $new_status = 'approved';
        $should_deduct = true;
    } elseif ($action === 'reject' && $current_status === 'pending') {
        $new_status = 'rejected';
    } elseif ($action === 'dispute' && $current_status === 'pending') {
        $new_status = 'dispute';
    } elseif ($action === 'release' && $current_status === 'dispute') {
        $new_status = 'approved';
        $should_deduct = true;
    } elseif ($action === 'deduct' && $current_status === 'dispute') {
        $new_status = 'rejected';
    }

    if ($new_status === '') {
        $msg = "This withdrawal is no longer in a valid state for that action.";
        return false;
    }

    $has_processed_at = wr_column_exists($conn, 'withdrawals', 'processed_at');
    $in_transaction = false;

    try {
        if (method_exists($conn, 'begin_transaction')) {
            $conn->begin_transaction();
            $in_transaction = true;
        }

        if ($should_deduct) {
            $balance_stmt = $conn->prepare("SELECT account_balance FROM users WHERE id=? LIMIT 1");
            if (!$balance_stmt) {
                throw new \Exception($conn->error);
            }

            $balance_stmt->bind_param("i", $user_id);
            $balance_stmt->execute();

            $balance_row = wr_fetch_stmt($balance_stmt);
            $balance_stmt->close();

            $current_balance = $balance_row ? (float)$balance_row['account_balance'] : 0;

            if ($current_balance < $amount) {
                throw new \Exception("Insufficient account balance to approve this withdrawal.");
            }

            $deduct_stmt = $conn->prepare("UPDATE users SET account_balance = account_balance - ? WHERE id=? AND account_balance >= ?");
            if (!$deduct_stmt) {
                throw new \Exception($conn->error);
            }

            $deduct_stmt->bind_param("did", $amount, $user_id, $amount);
            $deduct_stmt->execute();

            if ($deduct_stmt->affected_rows === 0) {
                $deduct_stmt->close();
                throw new \Exception("Balance changed before the withdrawal could be approved.");
            }

            $deduct_stmt->close();
        }

        $processed_sql = $has_processed_at ? ", processed_at=NOW()" : "";
        $update_sql = "UPDATE withdrawals SET status=?{$processed_sql} WHERE id=? AND status=?";

        $update_stmt = $conn->prepare($update_sql);
        if (!$update_stmt) {
            throw new \Exception($conn->error);
        }

        $update_stmt->bind_param("sis", $new_status, $wid, $current_status);
        $update_stmt->execute();

        if ($update_stmt->affected_rows === 0) {
            $update_stmt->close();
            throw new \Exception("Withdrawal status changed before the action completed.");
        }

        $update_stmt->close();

        if (function_exists('log_admin_action')) {
            @log_admin_action(
                $conn,
                $admin_id,
                $action,
                ucfirst($action) . " withdrawal ID {$wid}",
                $user_id
            );
        }

        if ($in_transaction && method_exists($conn, 'commit')) {
            $conn->commit();
        }

        $in_transaction = false;

        $msg = "Withdrawal {$action} successful.";
        $email_data = [
            'user_id' => $user_id,
            'amount' => $amount,
            'status' => $new_status
        ];

        return true;
    } catch (\Throwable $e) {
        if ($in_transaction && method_exists($conn, 'rollback')) {
            @$conn->rollback();
        }

        $msg = "Error: " . $e->getMessage();
        return false;
    }
}

// ================================
// NORMAL POST ACTION
// ================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $_SESSION['msg'] = "Security token mismatch. Please refresh and try again.";
        header("Location: withdrawals_requested.php?" . $redirect_query);
        exit();
    }

    $wid = intval($_POST['withdraw_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $msg = '';
    $email_data = [];

    $ok = wr_process_action($conn, $admin_id, $wid, $action, $msg, $email_data);

    // Close session early so email sending does not lock the session.
    if (function_exists('session_write_close')) {
        session_write_close();
    }

    if ($ok) {
        wr_send_email_for_action($conn, $email_data);
    }

    $_SESSION['msg'] = $msg;
    header("Location: withdrawals_requested.php?" . $redirect_query);
    exit();
}

// ================================
// AJAX REFRESH ENDPOINT
// ================================
if (isset($_GET['ajax'])) {
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=UTF-8');

    $stats = [
        'pending_count' => 0,
        'pending_amount' => 0,
        'approved_count' => 0,
        'approved_amount' => 0,
        'rejected_count' => 0,
        'rejected_amount' => 0,
        'dispute_count' => 0,
        'dispute_amount' => 0,
        'today_count' => 0,
        'today_amount' => 0,
    ];

    $stats_where = [];

    if ($start_date !== '') {
        $stats_where[] = "DATE(created_at) >= '" . $conn->real_escape_string($start_date) . "'";
    }

    if ($end_date !== '') {
        $stats_where[] = "DATE(created_at) <= '" . $conn->real_escape_string($end_date) . "'";
    }

    $stats_where_sql = $stats_where ? ("WHERE " . implode(" AND ", $stats_where)) : "";

    $stats_sql = "
        SELECT
            SUM(CASE WHEN LOWER(status)='pending' THEN 1 ELSE 0 END) AS pending_count,
            COALESCE(SUM(CASE WHEN LOWER(status)='pending' THEN amount ELSE 0 END),0) AS pending_amount,

            SUM(CASE WHEN LOWER(status)='approved' THEN 1 ELSE 0 END) AS approved_count,
            COALESCE(SUM(CASE WHEN LOWER(status)='approved' THEN amount ELSE 0 END),0) AS approved_amount,

            SUM(CASE WHEN LOWER(status)='rejected' THEN 1 ELSE 0 END) AS rejected_count,
            COALESCE(SUM(CASE WHEN LOWER(status)='rejected' THEN amount ELSE 0 END),0) AS rejected_amount,

            SUM(CASE WHEN LOWER(status)='dispute' THEN 1 ELSE 0 END) AS dispute_count,
            COALESCE(SUM(CASE WHEN LOWER(status)='dispute' THEN amount ELSE 0 END),0) AS dispute_amount,

            SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 ELSE 0 END) AS today_count,
            COALESCE(SUM(CASE WHEN DATE(created_at)=CURDATE() THEN amount ELSE 0 END),0) AS today_amount
        FROM withdrawals
        {$stats_where_sql}
    ";

    $stats_res = @$conn->query($stats_sql);

    if ($stats_res && $stats_row = $stats_res->fetch_assoc()) {
        $stats = [
            'pending_count' => (int)$stats_row['pending_count'],
            'pending_amount' => (float)$stats_row['pending_amount'],
            'approved_count' => (int)$stats_row['approved_count'],
            'approved_amount' => (float)$stats_row['approved_amount'],
            'rejected_count' => (int)$stats_row['rejected_count'],
            'rejected_amount' => (float)$stats_row['rejected_amount'],
            'dispute_count' => (int)$stats_row['dispute_count'],
            'dispute_amount' => (float)$stats_row['dispute_amount'],
            'today_count' => (int)$stats_row['today_count'],
            'today_amount' => (float)$stats_row['today_amount'],
        ];
    }

    $where = [];

    if ($status_filter !== 'all') {
        $where[] = "LOWER(w.status)='" . $conn->real_escape_string($status_filter) . "'";
    }

    if ($start_date !== '') {
        $where[] = "DATE(w.created_at) >= '" . $conn->real_escape_string($start_date) . "'";
    }

    if ($end_date !== '') {
        $where[] = "DATE(w.created_at) <= '" . $conn->real_escape_string($end_date) . "'";
    }

    $where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

    $has_users_table = wr_column_exists($conn, 'users', 'id');
    $has_username = $has_users_table && wr_column_exists($conn, 'users', 'username');
    $has_name = $has_users_table && wr_column_exists($conn, 'users', 'name');
    $has_email = $has_users_table && wr_column_exists($conn, 'users', 'email');
    $has_phone = $has_users_table && wr_column_exists($conn, 'users', 'phone');
    $has_balance = $has_users_table && wr_column_exists($conn, 'users', 'account_balance');

    if ($has_users_table) {
        $username_select = $has_username ? "u.username AS username" : "CONCAT('User #', w.user_id) AS username";
        $name_select = $has_name ? "u.name AS full_name" : "NULL AS full_name";
        $email_select = $has_email ? "u.email AS email" : "NULL AS email";
        $phone_select = $has_phone ? "u.phone AS phone" : "NULL AS phone";
        $balance_select = $has_balance ? "u.account_balance AS account_balance" : "0 AS account_balance";
        $join = "JOIN users u ON u.id = w.user_id";
    } else {
        $username_select = "CONCAT('User #', w.user_id) AS username";
        $name_select = "NULL AS full_name";
        $email_select = "NULL AS email";
        $phone_select = "NULL AS phone";
        $balance_select = "0 AS account_balance";
        $join = "";
    }

    $sql = "
        SELECT
            w.id,
            w.user_id,
            {$username_select},
            {$name_select},
            {$email_select},
            {$phone_select},
            {$balance_select},
            w.amount,
            w.status,
            w.created_at
        FROM withdrawals w
        {$join}
        {$where_sql}
        ORDER BY
            CASE LOWER(w.status)
                WHEN 'pending' THEN 1
                WHEN 'dispute' THEN 2
                ELSE 3
            END ASC,
            w.created_at ASC
    ";

    $withdraw_q = @$conn->query($sql);

    ob_start();

    if (!$withdraw_q) {
        echo '<tr><td colspan="7" class="wr-empty-row">Could not load withdrawals. Database error: ' . wr_h($conn->error) . '</td></tr>';
    } else {
        while ($w = $withdraw_q->fetch_assoc()) {
            $status = strtolower(trim((string)$w['status']));
            $row_class = wr_row_classes($status, $w['created_at']);

            $username = $w['username'] ?? ('User #' . (int)$w['user_id']);
            $full_name = $w['full_name'] ?? '';
            $email = $w['email'] ?? '';
            $phone = $w['phone'] ?? '';
            $balance = (float)$w['account_balance'];
            $amount = (float)$w['amount'];
            $wid = (int)$w['id'];
            $initial = strtoupper(substr($username, 0, 1));
            $created_label = wr_time_ago($w['created_at']);
            ?>
            <tr class="<?= wr_h($row_class) ?>">
                <td data-label="User">
                    <div class="wr-user-cell">
                        <div class="wr-avatar"><?= wr_h($initial) ?></div>
                        <div class="wr-user-info">
                            <a class="wr-username" href="edit_user.php?id=<?= (int)$w['user_id'] ?>">
                                <?= wr_h($username) ?>
                            </a>
                            <div class="wr-sub">
                                <?= $full_name !== '' ? wr_h($full_name) : 'User #' . (int)$w['user_id'] ?>
                            </div>
                        </div>
                    </div>
                </td>

                <td data-label="Contact">
                    <div class="wr-contact">
                        <?php if ($email !== ''): ?>
                            <div class="wr-line"><?= wr_h($email) ?></div>
                        <?php endif; ?>

                        <?php if ($phone !== ''): ?>
                            <div class="wr-line muted"><?= wr_h($phone) ?></div>
                        <?php endif; ?>

                        <?php if ($email === '' && $phone === ''): ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </div>
                </td>

                <td data-label="Balance">
                    <div class="wr-money">Ksh <?= number_format($balance, 2) ?></div>
                </td>

                <td data-label="Amount">
                    <div class="wr-amount">Ksh <?= number_format($amount, 2) ?></div>
                    <div class="wr-sub">Requested <?= wr_h($created_label) ?></div>
                </td>

                <td data-label="Status">
                    <span class="wr-pill status-<?= wr_h($status) ?>">
                        <?= wr_h(ucfirst($status)) ?>
                    </span>
                </td>

                <td data-label="Waiting">
                    <div class="wr-waiting"><?= wr_h($created_label) ?></div>
                </td>

                <td data-label="Actions" class="wr-actions">
                    <?php if ($status === 'pending'): ?>
                        <form method="post" action="withdrawals_requested.php?<?= wr_h($redirect_query) ?>" class="wr-action-form">
                            <input type="hidden" name="csrf_token" value="<?= wr_h($csrf_token) ?>">
                            <input type="hidden" name="withdraw_id" value="<?= $wid ?>">
                            <input type="hidden" name="action" value="">

                            <button type="submit" name="action" value="approve" class="btn btn-approve"
                                onclick="return wrSubmit(event, this, 'approve', 'Approve withdrawal #<?= $wid ?>?')">
                                Approve
                            </button>

                            <button type="submit" name="action" value="reject" class="btn btn-reject"
                                onclick="return wrSubmit(event, this, 'reject', 'Reject withdrawal #<?= $wid ?>?')">
                                Reject
                            </button>

                            <button type="submit" name="action" value="dispute" class="btn btn-dispute"
                                onclick="return wrSubmit(event, this, 'dispute', 'Mark withdrawal #<?= $wid ?> as dispute?')">
                                Dispute
                            </button>
                        </form>

                    <?php elseif ($status === 'dispute'): ?>
                        <form method="post" action="withdrawals_requested.php?<?= wr_h($redirect_query) ?>" class="wr-action-form">
                            <input type="hidden" name="csrf_token" value="<?= wr_h($csrf_token) ?>">
                            <input type="hidden" name="withdraw_id" value="<?= $wid ?>">
                            <input type="hidden" name="action" value="">

                            <button type="submit" name="action" value="release" class="btn btn-approve"
                                onclick="return wrSubmit(event, this, 'release', 'Release disputed withdrawal #<?= $wid ?>?')">
                                Release
                            </button>

                            <button type="submit" name="action" value="deduct" class="btn btn-reject"
                                onclick="return wrSubmit(event, this, 'deduct', 'Deduct/reject disputed withdrawal #<?= $wid ?>?')">
                                Deduct
                            </button>
                        </form>

                    <?php else: ?>
                        <span class="muted">No action</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
    }

    $rows_html = ob_get_clean();

    echo json_encode([
        'rows' => $rows_html,
        'stats' => $stats,
        'updated' => date('H:i:s')
    ]);

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Withdrawal Queue | GIBAL LTD</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg-1: #070b14;
    --bg-2: #0b1120;
    --surface: rgba(17, 24, 39, 0.72);
    --surface-2: rgba(15, 23, 42, 0.78);
    --border: rgba(148, 163, 184, 0.12);
    --border-strong: rgba(148, 163, 184, 0.22);
    --text: #f1f5f9;
    --text-2: #cbd5e1;
    --text-3: #64748b;
    --gold: #d4af37;
    --gold-2: #f4d03f;
    --cyan: #22d3ee;
    --green: #10b981;
    --red: #ef4444;
    --amber: #f59e0b;
    --blue: #3b82f6;
    --purple: #8b5cf6;
    --shadow: 0 18px 50px rgba(0,0,0,0.35);
    --radius: 16px;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: Inter, Arial, sans-serif;
    color: var(--text);
    background:
        radial-gradient(circle at top left, rgba(212,175,55,0.08), transparent 30%),
        radial-gradient(circle at bottom right, rgba(34,211,238,0.07), transparent 28%),
        linear-gradient(180deg, var(--bg-1), var(--bg-2));
}

a {
    color: inherit;
}

.muted {
    color: var(--text-3);
}

.wr-topbar {
    position: sticky;
    top: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 14px 20px;
    background: rgba(7, 11, 20, 0.82);
    backdrop-filter: blur(18px);
    border-bottom: 1px solid rgba(212,175,55,0.16);
}

.wr-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.wr-brand-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, var(--gold), #8b6b12);
    color: #0b0b0b;
    font-weight: 900;
    box-shadow: 0 10px 25px rgba(212,175,55,0.18);
}

.wr-brand-text {
    display: flex;
    flex-direction: column;
    line-height: 1.15;
}

.wr-brand-title {
    font-size: 1rem;
}

.wr-brand-sub {
    font-size: 0.72rem;
    color: var(--gold);
    text-transform: uppercase;
    letter-spacing: 0.12em;
}

.wr-top-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.wr-container {
    max-width: 1500px;
    margin: 0 auto;
    padding: 24px 20px 80px;
}

.wr-page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.wr-page-head h1 {
    margin: 0;
    font-size: 1.7rem;
    font-weight: 900;
    letter-spacing: -0.03em;
    display: flex;
    align-items: center;
    gap: 12px;
}

.wr-page-head h1 i {
    color: var(--gold);
}

.wr-live {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 14px;
    border-radius: 999px;
    background: rgba(16,185,129,0.1);
    border: 1px solid rgba(16,185,129,0.24);
    color: var(--green);
    font-size: 0.8rem;
    font-weight: 800;
}

.wr-live::before {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--green);
    animation: wrPulse 1.8s infinite;
}

@keyframes wrPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.45; transform: scale(0.9); }
}

.wr-alert {
    padding: 14px 16px;
    border-radius: 14px;
    margin-bottom: 18px;
    border: 1px solid rgba(59,130,246,0.25);
    background: rgba(59,130,246,0.1);
    color: #dbeafe;
    font-weight: 700;
}

.wr-summary {
    display: grid;
    grid-template-columns: repeat(5, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.wr-stat {
    position: relative;
    overflow: hidden;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    box-shadow: var(--shadow);
}

.wr-stat::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 4px;
    height: 100%;
    background: var(--cyan);
    opacity: 0.85;
}

.wr-stat.pending::before { background: var(--amber); }
.wr-stat.approved::before { background: var(--green); }
.wr-stat.rejected::before { background: var(--red); }
.wr-stat.dispute::before { background: var(--purple); }
.wr-stat.today::before { background: var(--gold); }

.wr-stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}

.wr-stat-label {
    font-size: 0.7rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: var(--text-3);
}

.wr-stat-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: rgba(34,211,238,0.12);
    color: var(--cyan);
}

.wr-stat.pending .wr-stat-icon { background: rgba(245,158,11,0.12); color: var(--amber); }
.wr-stat.approved .wr-stat-icon { background: rgba(16,185,129,0.12); color: var(--green); }
.wr-stat.rejected .wr-stat-icon { background: rgba(239,68,68,0.12); color: var(--red); }
.wr-stat.dispute .wr-stat-icon { background: rgba(139,92,246,0.12); color: var(--purple); }
.wr-stat.today .wr-stat-icon { background: rgba(212,175,55,0.12); color: var(--gold); }

.wr-stat-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.35rem;
    font-weight: 800;
    margin-bottom: 4px;
}

.wr-stat-sub {
    font-size: 0.82rem;
    color: var(--text-2);
}

.wr-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow);
}

.wr-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    padding: 16px 18px;
    border-bottom: 1px solid var(--border);
    background: rgba(6,182,212,0.03);
}

.wr-filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.wr-filter-btn {
    border: 1px solid var(--border-strong);
    background: transparent;
    color: var(--text-2);
    padding: 9px 14px;
    border-radius: 999px;
    font-weight: 800;
    font-size: 0.78rem;
    cursor: pointer;
    transition: 0.2s;
}

.wr-filter-btn:hover {
    border-color: var(--gold);
    color: var(--gold-2);
}

.wr-filter-btn.active {
    background: linear-gradient(135deg, var(--gold), #9a7411);
    color: #0b0b0b;
    border-color: transparent;
}

.wr-tools-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.wr-date-form {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.wr-input {
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid var(--border-strong);
    background: rgba(15,23,42,0.8);
    color: var(--text);
    font-family: inherit;
    font-size: 0.88rem;
    outline: none;
}

.wr-input:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(212,175,55,0.12);
}

.wr-search {
    min-width: 240px;
}

.wr-table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1180px;
}

thead {
    background: rgba(15,23,42,0.92);
    position: sticky;
    top: 0;
    z-index: 5;
}

th {
    text-align: left;
    padding: 14px 16px;
    font-size: 0.72rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--gold);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}

td {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(148,163,184,0.08);
    vertical-align: middle;
    color: var(--text-2);
    font-size: 0.92rem;
}

tbody tr:hover {
    background: rgba(212,175,55,0.045);
}

.wr-empty-row td {
    text-align: center;
    padding: 40px;
    color: var(--text-3);
}

.wr-user-cell {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 220px;
}

.wr-avatar {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, rgba(212,175,55,0.22), rgba(34,211,238,0.16));
    border: 1px solid rgba(212,175,55,0.18);
    color: var(--gold-2);
    font-weight: 900;
}

.wr-user-info {
    min-width: 0;
}

.wr-username {
    display: block;
    color: var(--gold);
    font-weight: 800;
    text-decoration: none;
}

.wr-username:hover {
    text-decoration: underline;
}

.wr-sub {
    color: var(--text-3);
    font-size: 0.8rem;
    margin-top: 2px;
}

.wr-contact .wr-line {
    font-size: 0.88rem;
    margin-bottom: 2px;
}

.wr-money {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 700;
    color: var(--text);
    white-space: nowrap;
}

.wr-amount {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 900;
    color: var(--gold-2);
    font-size: 1rem;
    white-space: nowrap;
}

.wr-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 1px solid transparent;
    white-space: nowrap;
}

.wr-pill.status-pending {
    background: rgba(245,158,11,0.12);
    color: var(--amber);
    border-color: rgba(245,158,11,0.22);
}

.wr-pill.status-approved {
    background: rgba(16,185,129,0.14);
    color: var(--green);
    border-color: rgba(16,185,129,0.24);
}

.wr-pill.status-rejected {
    background: rgba(239,68,68,0.12);
    color: var(--red);
    border-color: rgba(239,68,68,0.22);
}

.wr-pill.status-dispute {
    background: rgba(139,92,246,0.12);
    color: var(--purple);
    border-color: rgba(139,92,246,0.22);
}

.wr-waiting {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.85rem;
    color: var(--text-2);
    white-space: nowrap;
}

.wr-actions {
    min-width: 260px;
}

.wr-action-form {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    border: none;
    border-radius: 10px;
    padding: 9px 13px;
    font-weight: 900;
    font-size: 0.76rem;
    cursor: pointer;
    transition: 0.2s;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.btn:hover {
    transform: translateY(-1px);
}

.btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none;
}

.btn-approve {
    background: linear-gradient(135deg, var(--green), #059669);
    color: #052e1f;
}

.btn-reject {
    background: linear-gradient(135deg, var(--red), #b91c1c);
    color: #fff7f7;
}

.btn-dispute {
    background: linear-gradient(135deg, var(--amber), #b45309);
    color: #2b1a02;
}

.btn-gold {
    background: linear-gradient(135deg, var(--gold), #9a7411);
    color: #0b0b0b;
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--border-strong);
    color: var(--text-2);
}

.btn-outline:hover {
    border-color: var(--gold);
    color: var(--gold-2);
}

.highlight {
    background: var(--gold);
    color: #0b0b0b;
    border-radius: 4px;
    padding: 0 2px;
}

/* Row colors */
.wr-row.fresh-pending {
    background: rgba(34, 211, 238, 0.06);
}

.wr-row.approved-row {
    background: rgba(16, 185, 129, 0.10) !important;
}

.wr-row.approved-row td {
    border-bottom-color: rgba(16, 185, 129, 0.16);
}

.wr-row.rejected-row {
    background: rgba(239, 68, 68, 0.06);
    opacity: 0.88;
}

.wr-row.dispute-row {
    background: rgba(139, 92, 246, 0.08);
}

.flash-yellow {
    animation: wrFlashYellow 1.2s infinite;
}

.flash-red {
    animation: wrFlashRed 1.2s infinite;
}

@keyframes wrFlashYellow {
    0% { background: rgba(245,158,11,0.08); }
    50% { background: rgba(245,158,11,0.18); }
    100% { background: rgba(245,158,11,0.08); }
}

@keyframes wrFlashRed {
    0% { background: rgba(239,68,68,0.08); }
    50% { background: rgba(239,68,68,0.18); }
    100% { background: rgba(239,68,68,0.08); }
}

@media (max-width: 1300px) {
    .wr-summary {
        grid-template-columns: repeat(3, minmax(200px, 1fr));
    }
}

@media (max-width: 980px) {
    .wr-summary {
        grid-template-columns: repeat(2, minmax(200px, 1fr));
    }
}

@media (max-width: 900px) {
    .wr-container {
        padding: 18px 14px 90px;
    }

    .wr-topbar {
        padding: 12px 14px;
    }

    .wr-brand-sub {
        display: none;
    }

    .wr-toolbar {
        align-items: stretch;
    }

    .wr-tools-right {
        width: 100%;
    }

    .wr-date-form {
        width: 100%;
    }

    .wr-search {
        width: 100%;
        min-width: 0;
    }

    table {
        min-width: 0;
    }

    thead {
        display: none;
    }

    table, tbody, tr, td {
        display: block;
        width: 100%;
    }

    tbody tr {
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: 16px;
        margin: 0 0 14px 0;
        padding: 6px;
    }

    td {
        border: none;
        border-bottom: 1px solid rgba(148,163,184,0.07);
        padding: 12px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
    }

    td:last-child {
        border-bottom: none;
    }

    td:before {
        content: attr(data-label);
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        color: var(--gold);
        flex: 0 0 100px;
    }

    td.wr-actions {
        display: block;
        padding-top: 14px;
    }

    td.wr-actions:before {
        display: none;
    }

    .wr-user-cell {
        min-width: 0;
    }

    .wr-action-form {
        justify-content: flex-end;
    }
}

@media (max-width: 620px) {
    .wr-summary {
        grid-template-columns: 1fr;
    }

    .wr-page-head h1 {
        font-size: 1.3rem;
    }

    .wr-top-actions .btn span {
        display: none;
    }
}
</style>
</head>
<body>

<header class="wr-topbar">
    <div class="wr-brand">
        <div class="wr-brand-icon">G</div>
        <div class="wr-brand-text">
            <div class="wr-brand-title">GIBAL LTD</div>
            <div class="wr-brand-sub">Withdrawal Queue</div>
        </div>
    </div>

    <div class="wr-top-actions">
        <a href="admin_dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> <span>Dashboard</span></a>
        <a href="admin_settings.php#tab-complaints" class="btn btn-outline"><i class="fas fa-headset"></i> <span>Support</span></a>
        <a href="admin_logout.php" class="btn btn-reject"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>
</header>

<main class="wr-container">
    <section class="wr-page-head">
        <h1><i class="fas fa-money-check-alt"></i> Withdrawal Requests</h1>
        <div class="wr-live">
            Live queue · Updated <span id="wrUpdatedAt"><?= date('H:i:s') ?></span>
        </div>
    </section>

    <?php if (!empty($_SESSION['msg'])): ?>
        <div class="wr-alert">
            <?= wr_h($_SESSION['msg']) ?>
            <?php unset($_SESSION['msg']); ?>
        </div>
    <?php endif; ?>

    <section class="wr-summary">
        <div class="wr-stat pending">
            <div class="wr-stat-top">
                <div class="wr-stat-label">Pending</div>
                <div class="wr-stat-icon"><i class="fas fa-hourglass-half"></i></div>
            </div>
            <div class="wr-stat-value" id="wrPendingCount">0</div>
            <div class="wr-stat-sub">Ksh <span id="wrPendingAmount">0.00</span></div>
        </div>

        <div class="wr-stat approved">
            <div class="wr-stat-top">
                <div class="wr-stat-label">Approved</div>
                <div class="wr-stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="wr-stat-value" id="wrApprovedCount">0</div>
            <div class="wr-stat-sub">Ksh <span id="wrApprovedAmount">0.00</span></div>
        </div>

        <div class="wr-stat rejected">
            <div class="wr-stat-top">
                <div class="wr-stat-label">Rejected</div>
                <div class="wr-stat-icon"><i class="fas fa-times-circle"></i></div>
            </div>
            <div class="wr-stat-value" id="wrRejectedCount">0</div>
            <div class="wr-stat-sub">Ksh <span id="wrRejectedAmount">0.00</span></div>
        </div>

        <div class="wr-stat dispute">
            <div class="wr-stat-top">
                <div class="wr-stat-label">Dispute</div>
                <div class="wr-stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
            </div>
            <div class="wr-stat-value" id="wrDisputeCount">0</div>
            <div class="wr-stat-sub">Ksh <span id="wrDisputeAmount">0.00</span></div>
        </div>

        <div class="wr-stat today">
            <div class="wr-stat-top">
                <div class="wr-stat-label">Requested Today</div>
                <div class="wr-stat-icon"><i class="fas fa-calendar-day"></i></div>
            </div>
            <div class="wr-stat-value" id="wrTodayCount">0</div>
            <div class="wr-stat-sub">Ksh <span id="wrTodayAmount">0.00</span></div>
        </div>
    </section>

    <section class="wr-panel">
        <div class="wr-toolbar">
            <div class="wr-filters">
                <button class="wr-filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>" onclick="wrSetStatus('all')">All</button>
                <button class="wr-filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>" onclick="wrSetStatus('pending')">Pending</button>
                <button class="wr-filter-btn <?= $status_filter === 'approved' ? 'active' : '' ?>" onclick="wrSetStatus('approved')">Approved</button>
                <button class="wr-filter-btn <?= $status_filter === 'rejected' ? 'active' : '' ?>" onclick="wrSetStatus('rejected')">Rejected</button>
                <button class="wr-filter-btn <?= $status_filter === 'dispute' ? 'active' : '' ?>" onclick="wrSetStatus('dispute')">Dispute</button>
            </div>

            <div class="wr-tools-right">
                <form method="get" class="wr-date-form">
                    <input type="hidden" name="status" value="<?= wr_h($status_filter) ?>">
                    <input class="wr-input" type="date" name="start_date" value="<?= wr_h($start_date) ?>">
                    <input class="wr-input" type="date" name="end_date" value="<?= wr_h($end_date) ?>">
                    <button type="submit" class="btn btn-gold">Filter</button>
                </form>

                <input id="wrSearchInput" class="wr-input wr-search" type="text" placeholder="Search user, email, phone, amount...">
            </div>
        </div>

        <div class="wr-table-wrap">
            <table id="wrTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Contact</th>
                        <th>Balance</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Waiting</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="wrTableBody">
                    <tr>
                        <td colspan="7" class="wr-empty-row">Loading withdrawals...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
const wrFilters = <?= json_encode([
    'status' => $status_filter,
    'start_date' => $start_date,
    'end_date' => $end_date
]) ?>;

let wrSubmitting = false;

function wrSetStatus(status) {
    const url = new URL(window.location.href);
    url.searchParams.set('status', status);
    window.location.href = url.toString();
}

function wrEscapeHtml(text) {
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function wrEscapeRegex(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function wrFormatMoney(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function wrFormatCount(value) {
    return Number(value || 0).toLocaleString();
}

function wrSetText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

function wrUpdateStats(stats) {
    if (!stats) return;

    wrSetText('wrPendingCount', wrFormatCount(stats.pending_count));
    wrSetText('wrPendingAmount', wrFormatMoney(stats.pending_amount));

    wrSetText('wrApprovedCount', wrFormatCount(stats.approved_count));
    wrSetText('wrApprovedAmount', wrFormatMoney(stats.approved_amount));

    wrSetText('wrRejectedCount', wrFormatCount(stats.rejected_count));
    wrSetText('wrRejectedAmount', wrFormatMoney(stats.rejected_amount));

    wrSetText('wrDisputeCount', wrFormatCount(stats.dispute_count));
    wrSetText('wrDisputeAmount', wrFormatMoney(stats.dispute_amount));

    wrSetText('wrTodayCount', wrFormatCount(stats.today_count));
    wrSetText('wrTodayAmount', wrFormatMoney(stats.today_amount));
}

function wrStoreOriginalCells() {
    document.querySelectorAll('#wrTableBody td:not(.wr-actions)').forEach(td => {
        if (!td.dataset.originalHtml) {
            td.dataset.originalHtml = td.innerHTML;
        }
    });
}

function wrApplySearch() {
    const input = document.getElementById('wrSearchInput');
    if (!input) return;

    const filter = input.value.trim().toLowerCase();

    document.querySelectorAll('#wrTableBody tr').forEach(row => {
        const cells = Array.from(row.querySelectorAll('td:not(.wr-actions)'));
        if (!cells.length) return;

        const rowText = cells.map(td => td.textContent.toLowerCase()).join(' ');
        const matches = !filter || rowText.includes(filter);

        row.style.display = matches ? '' : 'none';

        cells.forEach(td => {
            if (!td.dataset.originalHtml) {
                td.dataset.originalHtml = td.innerHTML;
            }

            const original = td.dataset.originalHtml;

            if (filter && matches) {
                const regex = new RegExp('(' + wrEscapeRegex(filter) + ')', 'gi');
                td.innerHTML = original.replace(regex, '<span class="highlight">$1</span>');
            } else {
                td.innerHTML = original;
            }
        });
    });
}

function wrSubmit(event, btn, action, message) {
    if (event) {
        event.preventDefault();
    }

    if (!window.confirm(message)) {
        return false;
    }

    const form = btn.closest('form');
    if (!form) {
        return false;
    }

    const hiddenAction = form.querySelector('input[name="action"]');
    if (hiddenAction) {
        hiddenAction.value = action;
    }

    wrSubmitting = true;

    form.querySelectorAll('button').forEach(b => {
        b.disabled = true;
    });

    btn.textContent = 'Working...';

    setTimeout(() => {
        form.submit();
    }, 80);

    return false;
}

async function wrRefreshQueue() {
    if (wrSubmitting) return;

    try {
        const params = new URLSearchParams({
            ajax: '1',
            status: wrFilters.status,
            start_date: wrFilters.start_date,
            end_date: wrFilters.end_date
        });

        const res = await fetch('withdrawals_requested.php?' + params.toString(), {
            cache: 'no-store',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await res.json();

        const tbody = document.getElementById('wrTableBody');
        if (tbody && typeof data.rows === 'string') {
            tbody.innerHTML = data.rows;
            wrStoreOriginalCells();
            wrApplySearch();
        }

        wrUpdateStats(data.stats);
        wrSetText('wrUpdatedAt', data.updated || new Date().toLocaleTimeString());
    } catch (err) {
        console.error('Withdrawal queue refresh failed:', err);
    }
}

document.getElementById('wrSearchInput')?.addEventListener('keyup', wrApplySearch);

wrRefreshQueue();
setInterval(wrRefreshQueue, 5000);
</script>

</body>
</html>