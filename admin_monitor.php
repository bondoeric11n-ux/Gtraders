<?php
@mysqli_report(MYSQLI_REPORT_OFF);

$ADMIN_SESSION_TIMEOUT = 1800;
session_start();
include("db_connect.php");

if (function_exists('syslog_ensure_tables')) {
    @syslog_ensure_tables($conn);
}

function em_try_query($conn, $sql)
{
    try {
        return $conn->query($sql);
    } catch (\Throwable $e) {
        return false;
    }
}

function em_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function em_table_exists($conn, $table)
{
    static $cache = [];

    $table = (string)$table;

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return $cache[$table] = false;
    }

    $res = em_try_query($conn, "SELECT 1 FROM `$table` LIMIT 1");
    return $cache[$table] = ($res !== false);
}

function em_column_exists($conn, $table, $column)
{
    static $cache = [];

    $table = (string)$table;
    $column = (string)$column;
    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    if (
        !preg_match('/^[A-Za-z0-9_]+$/', $table) ||
        !preg_match('/^[A-Za-z0-9_]+$/', $column)
    ) {
        return $cache[$key] = false;
    }

    $res = em_try_query($conn, "SELECT `$column` FROM `$table` LIMIT 1");
    return $cache[$key] = ($res !== false);
}

function em_pick_column($conn, $table, array $candidates)
{
    foreach ($candidates as $column) {
        if (em_column_exists($conn, $table, $column)) {
            return $column;
        }
    }

    return null;
}

function em_ensure_table($conn)
{
    if (function_exists('syslog_ensure_tables')) {
        @syslog_ensure_tables($conn);
        return;
    }

    em_try_query($conn, "
        CREATE TABLE IF NOT EXISTS system_event_log (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            event_uid VARCHAR(255) NOT NULL,
            event_type VARCHAR(120) NOT NULL DEFAULT 'event',
            category VARCHAR(50) NOT NULL DEFAULT 'system',
            actor_type VARCHAR(20) NOT NULL DEFAULT 'system',
            actor_id BIGINT NOT NULL DEFAULT 0,
            actor_label VARCHAR(255) NOT NULL DEFAULT '',
            target_type VARCHAR(80) NOT NULL DEFAULT '',
            target_id BIGINT NOT NULL DEFAULT 0,
            target_label VARCHAR(255) NOT NULL DEFAULT '',
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(80) NOT NULL DEFAULT '',
            details MEDIUMTEXT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            source_table VARCHAR(80) NOT NULL DEFAULT '',
            source_id BIGINT NOT NULL DEFAULT 0,
            occurred_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_event_uid (event_uid),
            KEY idx_occurred (occurred_at),
            KEY idx_category (category),
            KEY idx_actor (actor_type, actor_id),
            KEY idx_source (source_table, source_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function em_write_event($conn, array $data)
{
    if (function_exists('syslog_write')) {
        return syslog_write($conn, $data);
    }

    em_ensure_table($conn);

    $defaults = [
        'event_uid' => '',
        'event_type' => 'event',
        'category' => 'system',
        'actor_type' => 'admin',
        'actor_id' => 0,
        'actor_label' => 'System',
        'target_type' => '',
        'target_id' => 0,
        'target_label' => '',
        'amount' => 0,
        'status' => '',
        'details' => '',
        'ip_address' => '',
        'user_agent' => '',
        'source_table' => 'admin_monitor',
        'source_id' => 0,
    ];

    $data = array_merge($defaults, $data);

    if ($data['event_uid'] === '') {
        try {
            $rand = bin2hex(random_bytes(12));
        } catch (\Throwable $e) {
            $rand = md5(uniqid('', true) . mt_rand());
        }

        $data['event_uid'] = 'em:' . $rand . ':' . microtime(true);
    }

    $uid = preg_replace('/[^A-Za-z0-9:_.-]+/', '_', (string)$data['event_uid']);
    $uid = substr($uid, 0, 250);

    $e = function ($v) use ($conn) {
        try {
            return $conn->real_escape_string((string)$v);
        } catch (\Throwable $ex) {
            return addslashes((string)$v);
        }
    };

    $sql = "
        INSERT IGNORE INTO system_event_log (
            event_uid, event_type, category, actor_type, actor_id, actor_label,
            target_type, target_id, target_label, amount, status, details,
            ip_address, user_agent, source_table, source_id, occurred_at, created_at
        ) VALUES (
            '" . $e($uid) . "',
            '" . $e($data['event_type']) . "',
            '" . $e($data['category']) . "',
            '" . $e($data['actor_type']) . "',
            " . (int)$data['actor_id'] . ",
            '" . $e($data['actor_label']) . "',
            '" . $e($data['target_type']) . "',
            " . (int)$data['target_id'] . ",
            '" . $e($data['target_label']) . "',
            " . (float)$data['amount'] . ",
            '" . $e($data['status']) . "',
            '" . $e(substr((string)$data['details'], 0, 6000)) . "',
            '" . $e($data['ip_address']) . "',
            '" . $e($data['user_agent']) . "',
            '" . $e($data['source_table']) . "',
            " . (int)$data['source_id'] . ",
            NOW(),
            NOW()
        )
    ";

    return em_try_query($conn, $sql) !== false;
}

function em_category_class($category)
{
    $category = strtolower(trim((string)$category));
    $allowed = ['users', 'finance', 'support', 'admin', 'security', 'system'];

    return in_array($category, $allowed, true) ? 'cat-' . $category : 'cat-system';
}

function em_status_class($status)
{
    $status = strtolower(trim((string)$status));

    if (in_array($status, ['approved', 'completed', 'resolved', 'success', 'successful', 'paid', 'active', 'operational'], true)) {
        return 'status-ok';
    }

    if (in_array($status, ['pending', 'open', 'requested', 'in_progress', 'queued'], true)) {
        return 'status-warn';
    }

    if (in_array($status, ['rejected', 'failed', 'cancelled', 'canceled', 'maintenance'], true)) {
        return 'status-danger';
    }

    return 'status-info';
}

function em_excerpt($text, $limit = 240)
{
    $text = strip_tags((string)$text);
    $text = preg_replace('/\s+/', ' ', trim($text));

    if (function_exists('mb_substr')) {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '...' : $text;
    }

    return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
}

function em_row_html(array $e)
{
    $occurred = $e['occurred_at'] ?? $e['created_at'] ?? '';
    $time = $occurred ? date('M d, H:i:s', strtotime($occurred)) : '—';

    $amountHtml = '';
    if ((float)($e['amount'] ?? 0) > 0) {
        $amountHtml = '<div class="em-money">Ksh ' . number_format((float)$e['amount'], 2) . '</div>';
    }

    $statusHtml = '';
    if (!empty($e['status'])) {
        $statusHtml = '<span class="em-badge ' . em_status_class($e['status']) . '">' .
            em_h(ucfirst(str_replace('_', ' ', $e['status']))) .
            '</span>';
    }

    return '
    <tr>
        <td data-label="Time">
            <div class="em-time">' . em_h($time) . '</div>
            <div class="em-sub">' . em_h($e['source_table'] ?? '') . ' #' . (int)($e['source_id'] ?? 0) . '</div>
        </td>
        <td data-label="Category">
            <span class="em-badge ' . em_category_class($e['category'] ?? '') . '">' . em_h(ucfirst($e['category'] ?? 'system')) . '</span>
        </td>
        <td data-label="Actor">
            <div class="em-strong">' . em_h($e['actor_label'] ?? '') . '</div>
            <div class="em-sub">' . em_h(ucfirst($e['actor_type'] ?? 'system')) . ' #' . (int)($e['actor_id'] ?? 0) . '</div>
        </td>
        <td data-label="Event">
            <div class="em-strong">' . em_h(ucfirst(str_replace('_', ' ', $e['event_type'] ?? 'event'))) . '</div>
        </td>
        <td data-label="Target">
            <div>' . em_h($e['target_label'] ?? '') . '</div>
            <div class="em-sub">' . em_h($e['target_type'] ?? '') . ' #' . (int)($e['target_id'] ?? 0) . '</div>
        </td>
        <td data-label="Amount">' . $amountHtml . '</td>
        <td data-label="Status">' . $statusHtml . '</td>
        <td data-label="Details">
            <div class="em-details">' . em_h(em_excerpt($e['details'] ?? '', 240)) . '</div>
        </td>
        <td data-label="IP">
            <div class="em-mono">' . em_h($e['ip_address'] ?: '—') . '</div>
        </td>
    </tr>';
}

function em_summary($conn)
{
    $summary = [
        'total' => 0,
        'users' => 0,
        'finance' => 0,
        'support' => 0,
        'admin' => 0,
        'security' => 0,
        'system' => 0,
    ];

    em_ensure_table($conn);

    $res = em_try_query($conn, "
        SELECT category, COUNT(*) AS c
        FROM system_event_log
        WHERE DATE(occurred_at) = CURDATE()
        GROUP BY category
    ");

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cat = $row['category'];
            $count = (int)$row['c'];
            $summary['total'] += $count;

            if (isset($summary[$cat])) {
                $summary[$cat] += $count;
            }
        }
    }

    return $summary;
}

function em_build_where($conn, array $filters, $lastId = 0, $incremental = false)
{
    $where = [];

    if ($incremental && $lastId > 0) {
        $where[] = "id > " . (int)$lastId;
    }

    if (!empty($filters['category']) && $filters['category'] !== 'all') {
        $where[] = "category = '" . $conn->real_escape_string($filters['category']) . "'";
    }

    if (!empty($filters['actor_type']) && $filters['actor_type'] !== 'all') {
        $where[] = "actor_type = '" . $conn->real_escape_string($filters['actor_type']) . "'";
    }

    if (!empty($filters['search'])) {
        $s = '%' . $conn->real_escape_string($filters['search']) . '%';
        $where[] = "(
            event_type LIKE '$s'
            OR actor_label LIKE '$s'
            OR target_label LIKE '$s'
            OR details LIKE '$s'
            OR source_table LIKE '$s'
            OR CAST(source_id AS CHAR) LIKE '$s'
            OR ip_address LIKE '$s'
        )";
    }

    if (!empty($filters['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) {
        $where[] = "occurred_at >= '" . $conn->real_escape_string($filters['from'] . ' 00:00:00') . "'";
    }

    if (!empty($filters['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) {
        $where[] = "occurred_at <= '" . $conn->real_escape_string($filters['to'] . ' 23:59:59') . "'";
    }

    return $where ? ('WHERE ' . implode(' AND ', $where)) : '';
}

// ============================================
// ADMIN AUTH
// ============================================
if (isset($_SESSION['admin_last_activity']) && time() - $_SESSION['admin_last_activity'] > $ADMIN_SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header("Location: admin_login.php?timeout=1");
    exit();
}

$_SESSION['admin_last_activity'] = time();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_id = (int)$_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'] ?? 'Super Admin';

if (empty($_SESSION['em_csrf'])) {
    $_SESSION['em_csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['em_csrf'];

em_ensure_table($conn);

// ============================================
// AJAX SYNC - scans tables for new/updated rows
// ============================================
if (isset($_GET['em_ajax']) && $_GET['em_ajax'] === 'sync') {
    header('Content-Type: application/json; charset=UTF-8');

    $available = function_exists('syslog_scan_all');

    $stats = [
        'seen' => 0,
        'inserted' => 0,
        'baselines' => 0,
        'tables' => [],
    ];

    if ($available) {
        $stats = syslog_scan_all($conn, 25);
    }

    echo json_encode([
        'success' => $available,
        'message' => $available ? '' : 'system_logger.php not loaded',
        'stats' => $stats,
        'updated_at' => date('H:i:s'),
    ]);

    exit();
}

// ============================================
// AJAX FEED
// ============================================
if (isset($_GET['em_ajax']) && $_GET['em_ajax'] === 'feed') {
    header('Content-Type: application/json; charset=UTF-8');

    $filters = [
        'category' => $_GET['category'] ?? 'all',
        'actor_type' => $_GET['actor_type'] ?? 'all',
        'search' => trim($_GET['search'] ?? ''),
        'from' => trim($_GET['from'] ?? ''),
        'to' => trim($_GET['to'] ?? ''),
    ];

    $lastId = (int)($_GET['last_id'] ?? 0);
    $incremental = $lastId > 0 && $filters['search'] === '' && $filters['from'] === '' && $filters['to'] === '';

    $where = em_build_where($conn, $filters, $lastId, $incremental);

    $res = em_try_query($conn, "
        SELECT *
        FROM system_event_log
        $where
        ORDER BY id DESC
        LIMIT 100
    ");

    $html = '';
    $maxId = $lastId;
    $count = 0;

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $html .= em_row_html($row);
            $maxId = max($maxId, (int)$row['id']);
            $count++;
        }
    }

    if (!$incremental && $count === 0) {
        $html = '<tr><td colspan="9" class="em-empty">No events found yet. Use the system, then wait for scan/feed refresh.</td></tr>';
    }

    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => $count,
        'max_id' => $maxId,
        'incremental' => $incremental,
        'summary' => em_summary($conn),
        'updated_at' => date('H:i:s'),
    ]);

    exit();
}

// ============================================
// CSV EXPORT
// ============================================
if (isset($_GET['em_ajax']) && $_GET['em_ajax'] === 'export') {
    $filters = [
        'category' => $_GET['category'] ?? 'all',
        'actor_type' => $_GET['actor_type'] ?? 'all',
        'search' => trim($_GET['search'] ?? ''),
        'from' => trim($_GET['from'] ?? ''),
        'to' => trim($_GET['to'] ?? ''),
    ];

    $where = em_build_where($conn, $filters, 0, false);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="system-event-log-' . date('Y-m-d-His') . '.csv"');

    $out = fopen('php://output', 'w');

    fputcsv($out, [
        'ID',
        'Occurred At',
        'Created At',
        'Category',
        'Event Type',
        'Actor Type',
        'Actor ID',
        'Actor',
        'Target Type',
        'Target ID',
        'Target',
        'Amount',
        'Status',
        'Details',
        'IP',
        'Source Table',
        'Source ID',
    ]);

    $res = em_try_query($conn, "
        SELECT *
        FROM system_event_log
        $where
        ORDER BY id DESC
        LIMIT 10000
    ");

    if ($res) {
        while ($e = $res->fetch_assoc()) {
            fputcsv($out, [
                $e['id'],
                $e['occurred_at'],
                $e['created_at'],
                $e['category'],
                $e['event_type'],
                $e['actor_type'],
                $e['actor_id'],
                $e['actor_label'],
                $e['target_type'],
                $e['target_id'],
                $e['target_label'],
                $e['amount'],
                $e['status'],
                $e['details'],
                $e['ip_address'],
                $e['source_table'],
                $e['source_id'],
            ]);
        }
    }

    fclose($out);
    exit();
}

// ============================================
// POST ACTIONS
// ============================================
$toast_message = $_SESSION['em_toast'] ?? '';
$toast_type = $_SESSION['em_toast_type'] ?? 'info';
unset($_SESSION['em_toast'], $_SESSION['em_toast_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $_SESSION['em_toast'] = "Security token mismatch. Refresh and try again.";
        $_SESSION['em_toast_type'] = "error";
        header("Location: admin_monitor.php");
        exit();
    }

    $emAction = $_POST['em_action'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    if ($emAction === 'toggle_maintenance') {
        $mode = ($_POST['mode'] ?? '') === 'maintenance' ? 'maintenance' : 'operational';
        $message = trim($_POST['maintenance_message'] ?? '');

        if (!em_table_exists($conn, 'system_status')) {
            em_try_query($conn, "
                CREATE TABLE IF NOT EXISTS system_status (
                    id INT PRIMARY KEY,
                    mode VARCHAR(30) NOT NULL DEFAULT 'operational',
                    message TEXT NULL,
                    updated_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            em_try_query($conn, "INSERT IGNORE INTO system_status (id, mode, message, updated_at) VALUES (1, 'operational', '', NOW())");
        }

        $stmt = @$conn->prepare("UPDATE system_status SET mode=?, message=?, updated_at=NOW() WHERE id=1");

        if ($stmt) {
            $stmt->bind_param("ss", $mode, $message);
            $stmt->execute();
            $stmt->close();
        }

        em_write_event($conn, [
            'event_type' => 'admin_system_status_update',
            'category' => 'system',
            'actor_type' => 'admin',
            'actor_id' => $admin_id,
            'actor_label' => $admin_username,
            'target_type' => 'system_status',
            'target_id' => 1,
            'target_label' => 'System Status',
            'status' => $mode,
            'details' => 'System mode changed to ' . $mode . '. Message: ' . em_excerpt($message, 240),
            'ip_address' => $ip,
            'user_agent' => $agent,
            'source_table' => 'admin_monitor',
            'source_id' => $admin_id,
        ]);

        $_SESSION['em_toast'] = "System status updated to " . ucfirst($mode) . ".";
        $_SESSION['em_toast_type'] = "success";
        header("Location: admin_monitor.php?tab=system");
        exit();
    }

    if ($emAction === 'approve_password') {
        $reqId = (int)($_POST['req_id'] ?? 0);

        if (
            em_table_exists($conn, 'password_change_requests') &&
            em_table_exists($conn, 'admins')
        ) {
            $passwordColumn = em_pick_column($conn, 'admins', ['password', 'password_hash', 'admin_password', 'pass']);

            if (!$passwordColumn) {
                $_SESSION['em_toast'] = "Could not find password column in admins table.";
                $_SESSION['em_toast_type'] = "error";
            } else {
                $stmt = @$conn->prepare("SELECT admin_id, new_password_hash FROM password_change_requests WHERE id=? AND status='pending_approval' LIMIT 1");

                if ($stmt) {
                    $stmt->bind_param("i", $reqId);
                    $stmt->execute();

                    $res = method_exists($stmt, 'get_result') ? $stmt->get_result() : null;
                    $req = $res ? $res->fetch_assoc() : null;
                    $stmt->close();

                    if ($req) {
                        $requestAdminId = (int)$req['admin_id'];
                        $newHash = $req['new_password_hash'];

                        $update = @$conn->prepare("UPDATE admins SET `$passwordColumn` = ? WHERE id = ?");

                        if ($update) {
                            $update->bind_param("si", $newHash, $requestAdminId);

                            if ($update->execute()) {
                                em_try_query($conn, "UPDATE password_change_requests SET status='approved' WHERE id=" . (int)$reqId);

                                em_write_event($conn, [
                                    'event_type' => 'admin_password_change_approved',
                                    'category' => 'security',
                                    'actor_type' => 'admin',
                                    'actor_id' => $admin_id,
                                    'actor_label' => $admin_username,
                                    'target_type' => 'admin',
                                    'target_id' => $requestAdminId,
                                    'target_label' => 'Admin #' . $requestAdminId,
                                    'status' => 'approved',
                                    'details' => 'Approved password change request #' . $reqId,
                                    'ip_address' => $ip,
                                    'user_agent' => $agent,
                                    'source_table' => 'password_change_requests',
                                    'source_id' => $reqId,
                                ]);

                                $_SESSION['em_toast'] = "Password change approved.";
                                $_SESSION['em_toast_type'] = "success";
                            } else {
                                $_SESSION['em_toast'] = "Failed to update password: " . $update->error;
                                $_SESSION['em_toast_type'] = "error";
                            }

                            $update->close();
                        }
                    } else {
                        $_SESSION['em_toast'] = "Pending password request not found.";
                        $_SESSION['em_toast_type'] = "error";
                    }
                }
            }
        }

        header("Location: admin_monitor.php?tab=security");
        exit();
    }

    if ($emAction === 'reject_password') {
        $reqId = (int)($_POST['req_id'] ?? 0);

        if (em_table_exists($conn, 'password_change_requests')) {
            em_try_query($conn, "UPDATE password_change_requests SET status='rejected' WHERE id=" . (int)$reqId);

            em_write_event($conn, [
                'event_type' => 'admin_password_change_rejected',
                'category' => 'security',
                'actor_type' => 'admin',
                'actor_id' => $admin_id,
                'actor_label' => $admin_username,
                'target_type' => 'password_request',
                'target_id' => $reqId,
                'target_label' => 'Password Request #' . $reqId,
                'status' => 'rejected',
                'details' => 'Rejected password change request #' . $reqId,
                'ip_address' => $ip,
                'user_agent' => $agent,
                'source_table' => 'password_change_requests',
                'source_id' => $reqId,
            ]);

            $_SESSION['em_toast'] = "Password change request rejected.";
            $_SESSION['em_toast_type'] = "warning";
        }

        header("Location: admin_monitor.php?tab=security");
        exit();
    }

    if ($emAction === 'cleanup_logs') {
        $days = (int)($_POST['days'] ?? 90);
        $days = max(1, min(3650, $days));

        em_ensure_table($conn);
        em_try_query($conn, "DELETE FROM system_event_log WHERE occurred_at < DATE_SUB(NOW(), INTERVAL $days DAY)");

        em_write_event($conn, [
            'event_type' => 'admin_event_log_cleanup',
            'category' => 'admin',
            'actor_type' => 'admin',
            'actor_id' => $admin_id,
            'actor_label' => $admin_username,
            'target_type' => 'system_event_log',
            'target_id' => 0,
            'target_label' => 'Event Log Cleanup',
            'details' => "Deleted event logs older than $days days.",
            'ip_address' => $ip,
            'user_agent' => $agent,
            'source_table' => 'admin_monitor',
            'source_id' => $admin_id,
        ]);

        $_SESSION['em_toast'] = "Old logs cleaned. Retained last $days days.";
        $_SESSION['em_toast_type'] = "success";

        header("Location: admin_monitor.php?tab=settings");
        exit();
    }
}

// ============================================
// PAGE DATA
// ============================================
$active_tab = $_GET['tab'] ?? 'feed';
$allowed_tabs = ['feed', 'summary', 'security', 'system', 'settings'];
if (!in_array($active_tab, $allowed_tabs, true)) {
    $active_tab = 'feed';
}

$filters = [
    'category' => $_GET['category'] ?? 'all',
    'actor_type' => $_GET['actor_type'] ?? 'all',
    'search' => trim($_GET['search'] ?? ''),
    'from' => trim($_GET['from'] ?? ''),
    'to' => trim($_GET['to'] ?? ''),
];

$summary = em_summary($conn);

$where = em_build_where($conn, $filters, 0, false);

$initialRes = em_try_query($conn, "
    SELECT *
    FROM system_event_log
    $where
    ORDER BY id DESC
    LIMIT 100
");

$initialHtml = '';
$initialMaxId = 0;
$initialCount = 0;

if ($initialRes) {
    while ($row = $initialRes->fetch_assoc()) {
        $initialHtml .= em_row_html($row);
        $initialMaxId = max($initialMaxId, (int)$row['id']);
        $initialCount++;
    }
}

if ($initialCount === 0) {
    $initialHtml = '<tr><td colspan="9" class="em-empty">No events found yet. Use the system, then wait for scan/feed refresh.</td></tr>';
}

$sys_status = null;
if (em_table_exists($conn, 'system_status')) {
    $res = em_try_query($conn, "SELECT * FROM system_status LIMIT 1");
    $sys_status = $res ? $res->fetch_assoc() : null;
}

$current_mode = $sys_status['mode'] ?? 'operational';
$current_msg = $sys_status['message'] ?? '';

$pending_passwords = [];
if (em_table_exists($conn, 'password_change_requests')) {
    $res = em_try_query($conn, "SELECT * FROM password_change_requests WHERE status='pending_approval' ORDER BY created_at DESC");
    if ($res) {
        $pending_passwords = $res->fetch_all(MYSQLI_ASSOC);
    }
}

$log_stats = ['total' => 0, 'oldest' => null, 'newest' => null];
em_ensure_table($conn);
$res = em_try_query($conn, "SELECT COUNT(*) AS total, MIN(occurred_at) AS oldest, MAX(occurred_at) AS newest FROM system_event_log");
if ($res && $row = $res->fetch_assoc()) {
    $log_stats = [
        'total' => (int)$row['total'],
        'oldest' => $row['oldest'],
        'newest' => $row['newest'],
    ];
}

$export_query = http_build_query(array_merge(['em_ajax' => 'export'], $filters));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Super Admin Monitor | GIBAL LTD</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg-base:#0b1120;--bg-surface:#111827;--bg-elevated:#1f2937;--bg-card:rgba(17,24,39,.7);--accent:#06b6d4;--accent-light:#22d3ee;--accent-dark:#0891b2;--accent-glow:rgba(6,182,212,.15);--accent-border:rgba(6,182,212,.25);--gold:#d4af37;--text-primary:#f1f5f9;--text-secondary:#cbd5e1;--text-tertiary:#64748b;--success:#10b981;--success-bg:rgba(16,185,129,.12);--success-border:rgba(16,185,129,.3);--warning:#f59e0b;--warning-bg:rgba(245,158,11,.12);--warning-border:rgba(245,158,11,.3);--danger:#ef4444;--danger-bg:rgba(239,68,68,.12);--danger-border:rgba(239,68,68,.3);--info:#3b82f6;--info-bg:rgba(59,130,246,.12);--info-border:rgba(59,130,246,.3);--purple:#8b5cf6;--purple-bg:rgba(139,92,246,.12);--border-subtle:rgba(148,163,184,.08);--border-medium:rgba(148,163,184,.15);--border-strong:rgba(148,163,184,.25);--radius-sm:6px;--radius-md:10px;--radius-lg:14px}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,sans-serif;background:var(--bg-base);color:var(--text-primary);min-height:100vh;background-image:linear-gradient(rgba(6,182,212,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(6,182,212,.02) 1px,transparent 1px);background-size:40px 40px,40px 40px}
.topbar{position:sticky;top:0;z-index:1000;background:rgba(11,17,32,.95);backdrop-filter:blur(20px);border-bottom:1px solid var(--accent-border);padding:0 24px;height:64px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{display:flex;align-items:center;gap:12px;color:var(--text-primary);text-decoration:none;font-weight:700;min-width:0}
.brand-icon{width:38px;height:38px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--bg-base);font-weight:800;flex:0 0 auto}
.brand-text{display:flex;flex-direction:column;line-height:1.2;min-width:0}
.brand-title{font-size:1rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.brand-subtitle{font-size:.7rem;color:var(--accent);text-transform:uppercase;letter-spacing:.1em}
.topbar-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.admin-info{color:var(--text-secondary);font-size:.85rem;padding:6px 12px;background:var(--bg-elevated);border-radius:50px;border:1px solid var(--border-subtle)}
.admin-info strong{color:var(--accent-light)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:9px 14px;border-radius:var(--radius-sm);font-weight:800;font-size:.78rem;cursor:pointer;transition:.2s;border:none;text-decoration:none;text-transform:uppercase;white-space:nowrap}
.btn:hover{transform:translateY(-1px)}
.btn-outline{background:transparent;border:1px solid var(--border-strong);color:var(--text-secondary)}
.btn-outline:hover{background:var(--bg-elevated);color:var(--accent-light)}
.btn-danger-outline{background:transparent;border:1px solid var(--danger-border);color:var(--danger)}
.btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:var(--bg-base)}
.btn-success{background:var(--success-bg);color:var(--success);border:1px solid var(--success-border)}
.btn-danger{background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger-border)}
.btn-sm{padding:7px 11px;font-size:.72rem}
.container-xl{max-width:1500px;margin:0 auto;padding:24px 24px 80px}
.page-header{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:22px;padding-bottom:18px;border-bottom:1px solid var(--border-subtle);flex-wrap:wrap}
.page-header h2{font-size:1.5rem;display:flex;align-items:center;gap:12px}
.page-header h2 i{color:var(--accent)}
.live-pill{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;background:var(--success-bg);border:1px solid var(--success-border);border-radius:999px;font-size:.75rem;font-weight:800;color:var(--success)}
.live-pill::before{content:'';width:8px;height:8px;border-radius:50%;background:var(--success);animation:pulse 1s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.35}}
.tabs{display:flex;gap:8px;margin-bottom:22px;border-bottom:1px solid var(--border-subtle);overflow-x:auto}
.tab-btn{padding:12px 18px;background:transparent;border:none;color:var(--text-tertiary);font-weight:800;font-size:.88rem;cursor:pointer;transition:.2s;border-bottom:2px solid transparent;white-space:nowrap;display:flex;align-items:center;gap:8px}
.tab-btn:hover{color:var(--text-primary)}
.tab-btn.active{color:var(--accent-light);border-bottom-color:var(--accent)}
.tab-content{display:none;animation:fadeIn .3s ease}
.tab-content.active{display:block}
@keyframes fadeIn{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:translateY(0)}}
.card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-lg);margin-bottom:20px;overflow:hidden}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border-subtle);background:rgba(6,182,212,.03);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.card-title{font-size:.92rem;font-weight:900;display:flex;align-items:center;gap:10px;text-transform:uppercase}
.card-title i{color:var(--accent)}
.card-body{padding:20px}
.form-group{margin-bottom:18px}
.form-label{display:block;font-size:.73rem;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:var(--text-tertiary);margin-bottom:8px}
.form-input,.form-select,.form-textarea{width:100%;padding:11px 13px;background:var(--bg-elevated);border:1px solid var(--border-medium);border-radius:var(--radius-sm);color:var(--text-primary);font-size:.9rem;font-family:inherit}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
.form-textarea{resize:vertical;min-height:90px}
.filters-grid{display:grid;grid-template-columns:1fr 180px 180px 170px 170px auto;gap:12px;align-items:end}
.summary-grid{display:grid;grid-template-columns:repeat(7,minmax(160px,1fr));gap:12px;margin-bottom:20px}
.summary-card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-md);padding:14px;position:relative;overflow:hidden}
.summary-card::before{content:'';position:absolute;left:0;top:0;width:4px;height:100%;background:var(--accent)}
.summary-card.users::before{background:var(--info)}
.summary-card.finance::before{background:var(--success)}
.summary-card.support::before{background:var(--accent)}
.summary-card.admin::before{background:var(--purple)}
.summary-card.security::before{background:var(--danger)}
.summary-card.system::before{background:var(--warning)}
.summary-label{font-size:.68rem;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--text-tertiary);margin-bottom:8px}
.summary-value{font-family:'JetBrains Mono',monospace;font-size:1.25rem;font-weight:900}
.table-responsive{overflow-x:auto}
.scrollable-table{max-height:650px;overflow-y:auto}
.scrollable-table::-webkit-scrollbar{width:8px}
.scrollable-table::-webkit-scrollbar-thumb{background:var(--border-strong);border-radius:4px}
table{width:100%;border-collapse:collapse;min-width:1150px}
thead{position:sticky;top:0;z-index:5;background:var(--bg-surface)}
th{padding:12px 14px;text-align:left;font-size:.7rem;font-weight:900;text-transform:uppercase;color:var(--accent-light);border-bottom:2px solid var(--accent-border);white-space:nowrap}
td{padding:12px 14px;font-size:.86rem;color:var(--text-secondary);border-bottom:1px solid var(--border-subtle);vertical-align:top}
tbody tr:hover{background:var(--bg-elevated)}
.em-empty{text-align:center;padding:40px!important;color:var(--text-tertiary)}
.em-time{font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--text-primary);white-space:nowrap}
.em-sub{font-size:.74rem;color:var(--text-tertiary);margin-top:3px}
.em-strong{font-weight:800;color:var(--text-primary)}
.em-mono{font-family:'JetBrains Mono',monospace;font-size:.8rem}
.em-money{font-family:'JetBrains Mono',monospace;font-weight:900;color:var(--gold);white-space:nowrap}
.em-details{max-width:420px;line-height:1.45;white-space:pre-wrap}
.em-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:.68rem;font-weight:900;text-transform:uppercase;letter-spacing:.05em;border:1px solid transparent;white-space:nowrap}
.cat-users{background:var(--info-bg);color:var(--info);border-color:var(--info-border)}
.cat-finance{background:var(--success-bg);color:var(--success);border-color:var(--success-border)}
.cat-support{background:var(--accent-glow);color:var(--accent-light);border-color:var(--accent-border)}
.cat-admin{background:var(--purple-bg);color:var(--purple);border-color:rgba(139,92,246,.3)}
.cat-security{background:var(--danger-bg);color:var(--danger);border-color:var(--danger-border)}
.cat-system{background:var(--warning-bg);color:var(--warning);border-color:var(--warning-border)}
.status-ok{background:var(--success-bg);color:var(--success);border-color:var(--success-border)}
.status-warn{background:var(--warning-bg);color:var(--warning);border-color:var(--warning-border)}
.status-danger{background:var(--danger-bg);color:var(--danger);border-color:var(--danger-border)}
.status-info{background:var(--info-bg);color:var(--info);border-color:var(--info-border)}
.toast-container{position:fixed;top:84px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px}
.toast{background:var(--bg-surface);border:1px solid var(--accent-border);border-left:4px solid var(--accent);border-radius:var(--radius-md);padding:14px 16px;min-width:300px;max-width:420px;transform:translateX(460px);opacity:0;transition:.35s;box-shadow:0 18px 40px rgba(0,0,0,.35);font-weight:700}
.toast.show{transform:translateX(0);opacity:1}
.toast.success{border-left-color:var(--success)}
.toast.error{border-left-color:var(--danger)}
.toast.warning{border-left-color:var(--warning)}
.alert{padding:14px 16px;border-radius:12px;margin-bottom:18px;border:1px solid rgba(59,130,246,.25);background:rgba(59,130,246,.1);color:#dbeafe;font-weight:700}
.notice{padding:14px 16px;border-radius:12px;margin-bottom:18px;border:1px solid rgba(245,158,11,.25);background:rgba(245,158,11,.1);color:#fde68a;font-weight:700}
@media(max-width:1300px){.summary-grid{grid-template-columns:repeat(4,minmax(160px,1fr))}.filters-grid{grid-template-columns:1fr 1fr 1fr}}
@media(max-width:980px){.summary-grid{grid-template-columns:repeat(2,minmax(160px,1fr))}.filters-grid{grid-template-columns:1fr 1fr}}
@media(max-width:900px){
.container-xl{padding:18px 14px 90px}.topbar{padding:0 14px}.brand-subtitle{display:none}
table{min-width:0}thead{display:none}table,tbody,tr,td{display:block;width:100%}
tbody tr{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;margin:0 0 14px 0;padding:6px}
td{border:none;border-bottom:1px solid rgba(148,163,184,.07);padding:11px;display:flex;justify-content:space-between;align-items:flex-start;gap:14px}
td:last-child{border-bottom:none}
td:before{content:attr(data-label);font-size:.7rem;font-weight:900;text-transform:uppercase;color:var(--accent-light);flex:0 0 95px}
.em-details{max-width:100%}
}
@media(max-width:620px){.summary-grid{grid-template-columns:1fr}.filters-grid{grid-template-columns:1fr}.topbar-actions .btn span{display:none}}
</style>
</head>
<body>

<div class="topbar">
    <a href="admin_dashboard.php" class="brand">
        <div class="brand-icon"><i class="fas fa-terminal"></i></div>
        <div class="brand-text">
            <span class="brand-title">GIBAL SUPER ADMIN</span>
            <span class="brand-subtitle">Full System Monitor</span>
        </div>
    </a>

    <div class="topbar-actions">
        <div class="admin-info">
            <i class="fas fa-user-shield" style="color:var(--accent);margin-right:6px;"></i>
            <strong><?= em_h($admin_username) ?></strong>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> <span>Dashboard</span></a>
        <a href="admin_logout.php" class="btn btn-danger-outline btn-sm"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<div class="container-xl">
    <div class="page-header">
        <h2><i class="fas fa-eye"></i> Live System Event Monitor</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <div class="live-pill">Live 1s · Updated <span id="emUpdatedAt"><?= date('H:i:s') ?></span></div>
            <button class="btn btn-primary btn-sm" onclick="emSync(true)"><i class="fas fa-rotate"></i> Scan Now</button>
            <button class="btn btn-outline btn-sm" onclick="emLoadFeed(true)"><i class="fas fa-refresh"></i> Refresh Feed</button>
            <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>

    <?php if (!empty($toast_message)): ?>
        <div class="alert"><?= em_h($toast_message) ?></div>
    <?php endif; ?>

    <?php if ($log_stats['total'] === 0): ?>
        <div class="notice">
            No events stored yet. Open a user page, login, create a ticket, deposit, withdrawal, or admin action, then click Scan Now.
        </div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab-btn active" data-tab="feed"><i class="fas fa-stream"></i> Live Feed</button>
        <button class="tab-btn" data-tab="summary"><i class="fas fa-chart-simple"></i> Today Summary</button>
        <button class="tab-btn" data-tab="security">
            <i class="fas fa-shield-alt"></i> Security Approvals
            <span style="background:var(--danger);color:white;padding:2px 6px;border-radius:10px;font-size:.7rem;margin-left:5px;"><?= count($pending_passwords) ?></span>
        </button>
        <button class="tab-btn" data-tab="system"><i class="fas fa-server"></i> System Control</button>
        <button class="tab-btn" data-tab="settings"><i class="fas fa-sliders-h"></i> Log Settings</button>
    </div>

    <!-- Live Feed -->
    <div class="tab-content active" id="tab-feed">
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Total Events Today</div>
                <div class="summary-value" id="emSumTotal"><?= number_format($summary['total']) ?></div>
            </div>
            <div class="summary-card users">
                <div class="summary-label">Users</div>
                <div class="summary-value" id="emSumUsers"><?= number_format($summary['users']) ?></div>
            </div>
            <div class="summary-card finance">
                <div class="summary-label">Finance</div>
                <div class="summary-value" id="emSumFinance"><?= number_format($summary['finance']) ?></div>
            </div>
            <div class="summary-card support">
                <div class="summary-label">Support</div>
                <div class="summary-value" id="emSumSupport"><?= number_format($summary['support']) ?></div>
            </div>
            <div class="summary-card admin">
                <div class="summary-label">Admin</div>
                <div class="summary-value" id="emSumAdmin"><?= number_format($summary['admin']) ?></div>
            </div>
            <div class="summary-card security">
                <div class="summary-label">Security</div>
                <div class="summary-value" id="emSumSecurity"><?= number_format($summary['security']) ?></div>
            </div>
            <div class="summary-card system">
                <div class="summary-label">System</div>
                <div class="summary-value" id="emSumSystem"><?= number_format($summary['system']) ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-stream"></i> System Event Feed</div>
                <a class="btn btn-outline btn-sm" href="admin_monitor.php?<?= em_h($export_query) ?>"><i class="fas fa-file-csv"></i> Export CSV</a>
            </div>

            <div class="card-body">
                <form method="GET" action="admin_monitor.php" class="filters-grid">
                    <input type="hidden" name="tab" value="feed">

                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-input" value="<?= em_h($filters['search']) ?>" placeholder="User, admin, event, details, IP, source ID...">
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="all" <?= $filters['category'] === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="users" <?= $filters['category'] === 'users' ? 'selected' : '' ?>>Users</option>
                            <option value="finance" <?= $filters['category'] === 'finance' ? 'selected' : '' ?>>Finance</option>
                            <option value="support" <?= $filters['category'] === 'support' ? 'selected' : '' ?>>Support</option>
                            <option value="admin" <?= $filters['category'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="security" <?= $filters['category'] === 'security' ? 'selected' : '' ?>>Security</option>
                            <option value="system" <?= $filters['category'] === 'system' ? 'selected' : '' ?>>System</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Actor</label>
                        <select name="actor_type" class="form-select">
                            <option value="all" <?= $filters['actor_type'] === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="user" <?= $filters['actor_type'] === 'user' ? 'selected' : '' ?>>User</option>
                            <option value="admin" <?= $filters['actor_type'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="system" <?= $filters['actor_type'] === 'system' ? 'selected' : '' ?>>System / DB</option>
                            <option value="guest" <?= $filters['actor_type'] === 'guest' ? 'selected' : '' ?>>Guest</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label class="form-label">From</label>
                        <input type="date" name="from" class="form-input" value="<?= em_h($filters['from']) ?>">
                    </div>

                    <div class="form-group" style="margin:0;">
                        <label class="form-label">To</label>
                        <input type="date" name="to" class="form-input" value="<?= em_h($filters['to']) ?>">
                    </div>

                    <div class="form-group" style="margin:0;">
                        <button type="submit" class="btn btn-primary" style="width:100%;height:43px;"><i class="fas fa-filter"></i> Apply</button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <div class="scrollable-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Category</th>
                                <th>Actor</th>
                                <th>Event</th>
                                <th>Target</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Details</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody id="emFeedBody">
                            <?= $initialHtml ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Today Summary -->
    <div class="tab-content" id="tab-summary">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-chart-simple"></i> Today Overview</div>
            </div>
            <div class="card-body">
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-label">Total Events</div>
                        <div class="summary-value" id="emSumTotal2"><?= number_format($summary['total']) ?></div>
                    </div>
                    <div class="summary-card users">
                        <div class="summary-label">User Events</div>
                        <div class="summary-value" id="emSumUsers2"><?= number_format($summary['users']) ?></div>
                    </div>
                    <div class="summary-card finance">
                        <div class="summary-label">Finance Events</div>
                        <div class="summary-value" id="emSumFinance2"><?= number_format($summary['finance']) ?></div>
                    </div>
                    <div class="summary-card support">
                        <div class="summary-label">Support Events</div>
                        <div class="summary-value" id="emSumSupport2"><?= number_format($summary['support']) ?></div>
                    </div>
                    <div class="summary-card admin">
                        <div class="summary-label">Admin Events</div>
                        <div class="summary-value" id="emSumAdmin2"><?= number_format($summary['admin']) ?></div>
                    </div>
                    <div class="summary-card security">
                        <div class="summary-label">Security Events</div>
                        <div class="summary-value" id="emSumSecurity2"><?= number_format($summary['security']) ?></div>
                    </div>
                    <div class="summary-card system">
                        <div class="summary-label">System Events</div>
                        <div class="summary-value" id="emSumSystem2"><?= number_format($summary['system']) ?></div>
                    </div>
                </div>

                <div style="color:var(--text-tertiary);font-size:.9rem;line-height:1.6;">
                    This monitor combines HTTP request logging, admin actions, user actions, support tickets, chats, deposits, withdrawals, investments, security events, and database table scanning into one live feed.
                </div>
            </div>
        </div>
    </div>

    <!-- Security Approvals -->
    <div class="tab-content" id="tab-security">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-shield-alt"></i> Pending Password Approvals</div>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($pending_passwords)): ?>
                    <div style="text-align:center;padding:40px;color:var(--text-tertiary);">
                        <i class="fas fa-check-circle" style="font-size:2rem;margin-bottom:10px;color:var(--success);"></i>
                        <p>No pending security requests.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Admin ID</th>
                                    <th>Requested At</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_passwords as $req): ?>
                                <tr>
                                    <td class="em-mono">#<?= (int)$req['id'] ?></td>
                                    <td>Admin #<?= (int)$req['admin_id'] ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($req['created_at'])) ?></td>
                                    <td><span class="em-badge status-warn">Pending Approval</span></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= em_h($csrf_token) ?>">
                                            <input type="hidden" name="em_action" value="approve_password">
                                            <input type="hidden" name="req_id" value="<?= (int)$req['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this password change?');">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>

                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= em_h($csrf_token) ?>">
                                            <input type="hidden" name="em_action" value="reject_password">
                                            <input type="hidden" name="req_id" value="<?= (int)$req['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this password change?');">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- System Control -->
    <div class="tab-content" id="tab-system">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-server"></i> System Maintenance Mode</div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= em_h($csrf_token) ?>">
                    <input type="hidden" name="em_action" value="toggle_maintenance">

                    <div class="form-group">
                        <label class="form-label">System Status</label>
                        <select name="mode" class="form-select">
                            <option value="operational" <?= $current_mode === 'operational' ? 'selected' : '' ?>>Operational (Live)</option>
                            <option value="maintenance" <?= $current_mode === 'maintenance' ? 'selected' : '' ?>>Maintenance Mode</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Maintenance Message (Shown to users)</label>
                        <textarea name="maintenance_message" class="form-textarea" placeholder="e.g., We are performing scheduled maintenance."><?= em_h($current_msg) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update System Status</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Settings -->
    <div class="tab-content" id="tab-settings">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-database"></i> Event Log Status</div>
            </div>
            <div class="card-body">
                <p><strong>Total events stored:</strong> <?= number_format($log_stats['total']) ?></p>
                <p><strong>Oldest event:</strong> <?= em_h($log_stats['oldest'] ?: '—') ?></p>
                <p><strong>Newest event:</strong> <?= em_h($log_stats['newest'] ?: '—') ?></p>

                <div class="alert" style="margin-top:16px;">
                    InfinityFree does not allow MySQL triggers. This system uses PHP request logging plus table scanning instead.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-trash-alt"></i> Log Retention</div>
            </div>
            <div class="card-body">
                <form method="POST" onsubmit="return confirm('Delete old logs? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= em_h($csrf_token) ?>">
                    <input type="hidden" name="em_action" value="cleanup_logs">

                    <div class="form-group">
                        <label class="form-label">Keep logs for</label>
                        <select name="days" class="form-select" style="max-width:240px;">
                            <option value="7">7 days</option>
                            <option value="30">30 days</option>
                            <option value="90" selected>90 days</option>
                            <option value="180">180 days</option>
                            <option value="365">365 days</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Clean Old Logs</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const EM_FEED_INTERVAL_MS = 1000;
const EM_SYNC_INTERVAL_MS = 5000;

const emFilters = <?= json_encode([
    'category' => $filters['category'],
    'actor_type' => $filters['actor_type'],
    'search' => $filters['search'],
    'from' => $filters['from'],
    'to' => $filters['to']
]) ?>;

const emActiveTab = <?= json_encode($active_tab) ?>;

let emLastId = <?= (int)$initialMaxId ?>;
let emLoading = false;
let emSyncing = false;

function emActivateTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.tab === tab);
    });

    document.querySelectorAll('.tab-content').forEach(c => {
        c.classList.toggle('active', c.id === 'tab-' + tab);
    });
}

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        emActivateTab(btn.dataset.tab);

        const url = new URL(window.location.href);
        url.searchParams.set('tab', btn.dataset.tab);
        window.history.replaceState({}, '', url.toString());
    });
});

function emSetText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

function emRenderSummary(summary) {
    if (!summary) return;

    const pairs = [
        ['emSumTotal', 'emSumTotal2', summary.total],
        ['emSumUsers', 'emSumUsers2', summary.users],
        ['emSumFinance', 'emSumFinance2', summary.finance],
        ['emSumSupport', 'emSumSupport2', summary.support],
        ['emSumAdmin', 'emSumAdmin2', summary.admin],
        ['emSumSecurity', 'emSumSecurity2', summary.security],
        ['emSumSystem', 'emSumSystem2', summary.system],
    ];

    pairs.forEach(([a, b, value]) => {
        const text = Number(value || 0).toLocaleString();
        emSetText(a, text);
        emSetText(b, text);
    });
}

function emTrimRows(max = 250) {
    const tbody = document.getElementById('emFeedBody');
    if (!tbody) return;

    while (tbody.children.length > max) {
        tbody.removeChild(tbody.lastElementChild);
    }
}

async function emLoadFeed(manual = false) {
    if (emLoading) return;
    emLoading = true;

    try {
        const useIncremental = emLastId > 0 && !emFilters.search && !emFilters.from && !emFilters.to;

        const params = new URLSearchParams({
            em_ajax: 'feed',
            category: emFilters.category,
            actor_type: emFilters.actor_type,
            search: emFilters.search,
            from: emFilters.from,
            to: emFilters.to
        });

        if (useIncremental) {
            params.set('last_id', emLastId);
        }

        const res = await fetch('admin_monitor.php?' + params.toString(), {
            cache: 'no-store'
        });

        const data = await res.json();

        if (data.success) {
            const tbody = document.getElementById('emFeedBody');

            if (tbody) {
                if (data.incremental) {
                    if (data.html.trim() !== '') {
                        tbody.insertAdjacentHTML('afterbegin', data.html);
                    }
                } else {
                    tbody.innerHTML = data.html;
                }

                emTrimRows(250);
            }

            if (data.max_id) {
                emLastId = Math.max(emLastId, Number(data.max_id));
            }

            emRenderSummary(data.summary);
            emSetText('emUpdatedAt', data.updated_at || new Date().toLocaleTimeString());

            if (manual && data.count === 0) {
                emToast('No new events found.', 'info');
            }
        }
    } catch (err) {
        console.error('Event feed refresh failed:', err);
    } finally {
        emLoading = false;
    }
}

async function emSync(manual = false) {
    if (emSyncing) return;
    emSyncing = true;

    try {
        const res = await fetch('admin_monitor.php?em_ajax=sync', {
            cache: 'no-store'
        });

        const data = await res.json();

        if (data.success) {
            const stats = data.stats || {};

            if (manual) {
                emToast(
                    'Scan complete. Seen: ' + (stats.seen || 0) +
                    ' | New events: ' + (stats.inserted || 0) +
                    ' | Baselines: ' + (stats.baselines || 0),
                    'success'
                );
            }

            await emLoadFeed(false);
        } else if (manual) {
            emToast(data.message || 'Sync unavailable.', 'error');
        }
    } catch (err) {
        console.error('Event sync failed:', err);

        if (manual) {
            emToast('Sync failed. Check console.', 'error');
        }
    } finally {
        emSyncing = false;
    }
}

function emToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 350);
    }, 4200);
}

window.addEventListener('load', async () => {
    emActivateTab(emActiveTab || 'feed');

    await emSync(false);
    await emLoadFeed(false);

    setInterval(() => {
        if (!document.hidden) {
            emLoadFeed(false);
        }
    }, EM_FEED_INTERVAL_MS);

    setInterval(() => {
        if (!document.hidden) {
            emSync(false);
        }
    }, EM_SYNC_INTERVAL_MS);
});

<?php if (!empty($toast_message)): ?>
window.addEventListener('load', () => {
    emToast(<?= json_encode($toast_message) ?>, <?= json_encode($toast_type) ?>);
});
<?php endif; ?>
</script>

</body>
</html>