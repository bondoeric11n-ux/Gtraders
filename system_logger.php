<?php
if (function_exists('mysqli_report')) {
    @mysqli_report(MYSQLI_REPORT_OFF);
}

if (!function_exists('syslog_ensure_tables')) {

function syslog_safe_name($name)
{
    $name = (string)$name;

    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        return null;
    }

    return '`' . str_replace('`', '``', $name) . '`';
}

function syslog_try_query($conn, $sql)
{
    try {
        return $conn->query($sql);
    } catch (\Throwable $e) {
        return false;
    }
}

function syslog_try_prepare($conn, $sql)
{
    try {
        return $conn->prepare($sql);
    } catch (\Throwable $e) {
        return false;
    }
}

function syslog_table_exists($conn, $table)
{
    static $cache = [];

    $table = (string)$table;

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return $cache[$table] = false;
    }

    $exists = false;

    $stmt = syslog_try_prepare($conn, "
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("s", $table);
        $stmt->execute();

        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            $exists = $res && $res->num_rows > 0;
        } else {
            $exists = $stmt->affected_rows > 0;
        }

        $stmt->close();
    }

    if (!$exists) {
        $res = syslog_try_query($conn, "SELECT 1 FROM `$table` LIMIT 1");
        $exists = ($res !== false);
    }

    return $cache[$table] = $exists;
}

function syslog_column_exists($conn, $table, $column)
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

    $exists = false;

    $stmt = syslog_try_prepare($conn, "
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
        LIMIT 1
    ");

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

    if (!$exists) {
        $res = syslog_try_query($conn, "SELECT `$column` FROM `$table` LIMIT 1");
        $exists = ($res !== false);
    }

    return $cache[$key] = $exists;
}

function syslog_pick_column($conn, $table, array $candidates)
{
    foreach ($candidates as $column) {
        if (syslog_column_exists($conn, $table, $column)) {
            return $column;
        }
    }

    return null;
}

function syslog_ensure_tables($conn)
{
    static $done = false;

    if ($done || !$conn) {
        return;
    }

    $done = true;

    syslog_try_query($conn, "
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

    syslog_try_query($conn, "
        CREATE TABLE IF NOT EXISTS system_event_scan_state (
            source_table VARCHAR(80) PRIMARY KEY,
            last_source_id BIGINT NOT NULL DEFAULT 0,
            last_updated_at DATETIME NULL,
            last_synced_at DATETIME NULL,
            baseline_done TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function syslog_esc($conn, $value)
{
    try {
        return $conn->real_escape_string((string)$value);
    } catch (\Throwable $e) {
        return addslashes((string)$value);
    }
}

function syslog_write($conn, array $data)
{
    if (!$conn) {
        return false;
    }

    syslog_ensure_tables($conn);

    $defaults = [
        'event_uid' => '',
        'event_type' => 'event',
        'category' => 'system',
        'actor_type' => 'system',
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
        'source_table' => 'manual',
        'source_id' => 0,
        'occurred_at' => date('Y-m-d H:i:s'),
    ];

    $data = array_merge($defaults, $data);

    if (!empty($data['details']) && is_array($data['details'])) {
        $data['details'] = json_encode($data['details']);
    }

    $data['details'] = substr((string)$data['details'], 0, 6000);

    if ($data['event_uid'] === '') {
        try {
            $rand = bin2hex(random_bytes(12));
        } catch (\Throwable $e) {
            $rand = md5(uniqid('', true) . mt_rand());
        }

        $data['event_uid'] = 'evt:' . $rand . ':' . microtime(true);
    }

    $uid = preg_replace('/[^A-Za-z0-9:_.-]+/', '_', (string)$data['event_uid']);
    $uid = substr($uid, 0, 250);

    $occurredSql = (!empty($data['occurred_at']) && strtotime((string)$data['occurred_at']))
        ? "'" . syslog_esc($conn, $data['occurred_at']) . "'"
        : "NOW()";

    $sql = "
        INSERT IGNORE INTO system_event_log (
            event_uid,
            event_type,
            category,
            actor_type,
            actor_id,
            actor_label,
            target_type,
            target_id,
            target_label,
            amount,
            status,
            details,
            ip_address,
            user_agent,
            source_table,
            source_id,
            occurred_at,
            created_at
        ) VALUES (
            '" . syslog_esc($conn, $uid) . "',
            '" . syslog_esc($conn, $data['event_type']) . "',
            '" . syslog_esc($conn, $data['category']) . "',
            '" . syslog_esc($conn, $data['actor_type']) . "',
            " . (int)$data['actor_id'] . ",
            '" . syslog_esc($conn, $data['actor_label']) . "',
            '" . syslog_esc($conn, $data['target_type']) . "',
            " . (int)$data['target_id'] . ",
            '" . syslog_esc($conn, $data['target_label']) . "',
            " . (float)$data['amount'] . ",
            '" . syslog_esc($conn, $data['status']) . "',
            '" . syslog_esc($conn, $data['details']) . "',
            '" . syslog_esc($conn, $data['ip_address']) . "',
            '" . syslog_esc($conn, $data['user_agent']) . "',
            '" . syslog_esc($conn, $data['source_table']) . "',
            " . (int)$data['source_id'] . ",
            $occurredSql,
            NOW()
        )
    ";

    $res = syslog_try_query($conn, $sql);

    return $res !== false;
}

function syslog_ip()
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

function syslog_agent()
{
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function syslog_session_actor()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!empty($_SESSION['admin_id'])) {
            $id = (int)$_SESSION['admin_id'];
            $label = $_SESSION['admin_username'] ?? ($_SESSION['username'] ?? ('Admin #' . $id));
            return ['admin', $id, (string)$label];
        }

        if (!empty($_SESSION['user_id'])) {
            $id = (int)$_SESSION['user_id'];
            $label = $_SESSION['username'] ?? ($_SESSION['user_username'] ?? ($_SESSION['name'] ?? ('User #' . $id)));
            return ['user', $id, (string)$label];
        }
    }

    return ['guest', 0, 'Guest'];
}

function syslog_is_sensitive($key)
{
    $key = strtolower((string)$key);

    return (bool)preg_match(
        '/(password|pass|pwd|token|csrf|secret|otp|twofa|two_factor|authorization|api_key|apikey|session|cookie|hash|new_password_hash|twofa_code)/',
        $key
    );
}

function syslog_safe_value($key, $value)
{
    if (syslog_is_sensitive($key)) {
        return '***';
    }

    if (is_array($value)) {
        return substr(json_encode($value), 0, 180);
    }

    return substr((string)$value, 0, 180);
}

function syslog_safe_array($arr)
{
    $out = [];

    if (!is_array($arr)) {
        return $out;
    }

    foreach ($arr as $k => $v) {
        $out[$k] = syslog_safe_value($k, $v);
    }

    return $out;
}

function syslog_row_summary($row)
{
    $out = [];

    if (!is_array($row)) {
        return '';
    }

    foreach ($row as $k => $v) {
        $out[$k] = syslog_safe_value($k, $v);
    }

    return substr(json_encode($out), 0, 5000);
}

function syslog_category_for_uri($uri, $method, $post)
{
    $uri = strtolower((string)$uri);

    if (strpos($uri, 'admin') !== false) {
        return 'admin';
    }

    if (preg_match('/(login|logout|register|password|2fa|twofa|auth|security)/', $uri)) {
        return 'security';
    }

    if (preg_match('/(deposit|withdraw|invest|investment|payment|transaction|topup|fund)/', $uri)) {
        return 'finance';
    }

    if (preg_match('/(ticket|complaint|dispute|chat|support|feedback)/', $uri)) {
        return 'support';
    }

    if (preg_match('/(user|dashboard|profile|account)/', $uri)) {
        return 'users';
    }

    if ($method === 'POST' && !empty($post['action'])) {
        $a = strtolower((string)$post['action']);

        if (preg_match('/(approve|reject|release|deduct|dispute)/', $a)) {
            return 'finance';
        }
    }

    return 'system';
}

function syslog_event_type_for_request($method, $uri, $post)
{
    $method = strtoupper((string)$method);
    $base = 'http_' . strtolower($method);

    if ($method === 'POST') {
        $candidate = '';

        foreach (['action', 'em_action', 'wr_action', 'ticket_action', 'login_action', 'create_ticket', 'admin_ticket_followup', 'resolve_complaint', 'resolve_dispute', 'send_notification', 'send_bulk_email', 'toggle_maintenance', 'approve_password', 'reject_password'] as $key) {
            if (!empty($post[$key]) && is_scalar($post[$key])) {
                $candidate = (string)$post[$key];
                break;
            }
        }

        if ($candidate !== '') {
            $candidate = preg_replace('/[^A-Za-z0-9_]+/', '_', strtolower($candidate));
            return 'http_post_' . substr($candidate, 0, 80);
        }
    }

    if (preg_match('/login/i', $uri)) {
        return 'auth_login_request';
    }

    if (preg_match('/register/i', $uri)) {
        return 'auth_register_request';
    }

    if (preg_match('/logout/i', $uri)) {
        return 'auth_logout_request';
    }

    return $base;
}

function syslog_boot($conn)
{
    static $done = false;

    if ($done || !$conn || PHP_SAPI === 'cli') {
        return;
    }

    $done = true;

    syslog_ensure_tables($conn);

    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');

    if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)(\?|$)/i', $uri)) {
        return;
    }

    if (stripos($uri, 'install_event_logging.php') !== false) {
        return;
    }

    if (stripos($uri, 'syslog_beacon.php') !== false) {
        return;
    }

    if ($method === 'GET' && preg_match('/[?&](em_ajax|ajax|dp_json|heartbeat)=|action=messages|ticket_api\.php\?action=messages/i', $uri)) {
        return;
    }

    $actor = syslog_session_actor();

    $getPost = syslog_safe_array($_GET ?? []);
    $postPost = syslog_safe_array($_POST ?? []);

    $details = $method . ' ' . $uri;

    if (!empty($getPost)) {
        $details .= ' | GET: ' . json_encode($getPost);
    }

    if (!empty($postPost)) {
        $details .= ' | POST: ' . json_encode($postPost);
    }

    $category = syslog_category_for_uri($uri, $method, $_POST ?? []);
    $eventType = syslog_event_type_for_request($method, $uri, $_POST ?? []);

    syslog_write($conn, [
        'event_type' => $eventType,
        'category' => $category,
        'actor_type' => $actor[0],
        'actor_id' => $actor[1],
        'actor_label' => $actor[2],
        'target_type' => 'http_request',
        'target_id' => 0,
        'target_label' => basename($script ?: 'unknown.php'),
        'details' => $details,
        'ip_address' => syslog_ip(),
        'user_agent' => syslog_agent(),
        'source_table' => 'http_request',
        'source_id' => 0,
    ]);
}

function syslog_action($conn, $eventType, $category, $details, array $extra = [])
{
    $actor = syslog_session_actor();

    return syslog_write($conn, array_merge([
        'event_type' => $eventType,
        'category' => $category,
        'actor_type' => $actor[0],
        'actor_id' => $actor[1],
        'actor_label' => $actor[2],
        'details' => $details,
        'ip_address' => syslog_ip(),
        'user_agent' => syslog_agent(),
        'source_table' => 'manual_action',
    ], $extra));
}

function syslog_category_for_table($table)
{
    $map = [
        'users' => 'users',
        'admins' => 'admin',
        'admin_audit_log' => 'admin',

        'deposits' => 'finance',
        'withdrawals' => 'finance',
        'investments' => 'finance',
        'transactions' => 'finance',
        'daily_fees' => 'finance',

        'admin_chats' => 'support',
        'complaints' => 'support',
        'disputes' => 'support',
        'ticket_followups' => 'support',

        'password_change_requests' => 'security',
        'login_attempts' => 'security',
        'user_logins' => 'security',
        'admin_logins' => 'security',

        'plans' => 'system',
        'broadcast_notifications' => 'system',
        'system_status' => 'system',
    ];

    return $map[$table] ?? 'system';
}

function syslog_sources()
{
    return [
        'users',
        'admins',
        'admin_audit_log',
        'deposits',
        'withdrawals',
        'investments',
        'transactions',
        'daily_fees',
        'plans',
        'admin_chats',
        'complaints',
        'disputes',
        'ticket_followups',
        'broadcast_notifications',
        'password_change_requests',
        'system_status',
        'login_attempts',
        'user_logins',
        'admin_logins',
    ];
}

function syslog_get_state($conn, $table)
{
    $res = syslog_try_query($conn, "
        SELECT *
        FROM system_event_scan_state
        WHERE source_table = '" . syslog_esc($conn, $table) . "'
        LIMIT 1
    ");

    if ($res && $row = $res->fetch_assoc()) {
        return $row;
    }

    return null;
}

function syslog_set_state($conn, $table, $lastId, $lastUpdated, $baselineDone = 1)
{
    $lastUpdatedSql = $lastUpdated ? "'" . syslog_esc($conn, $lastUpdated) . "'" : "NULL";

    $sql = "
        INSERT INTO system_event_scan_state
            (source_table, last_source_id, last_updated_at, last_synced_at, baseline_done)
        VALUES
            ('" . syslog_esc($conn, $table) . "', " . (int)$lastId . ", $lastUpdatedSql, NOW(), " . (int)$baselineDone . ")
        ON DUPLICATE KEY UPDATE
            last_source_id = " . (int)$lastId . ",
            last_updated_at = $lastUpdatedSql,
            last_synced_at = NOW(),
            baseline_done = " . (int)$baselineDone . "
    ";

    return syslog_try_query($conn, $sql) !== false;
}

function syslog_make_scan_event($conn, $table, array $row, $idCol, $createdCol, $updatedCol)
{
    $id = (int)($row[$idCol] ?? 0);

    if ($id <= 0) {
        return null;
    }

    $occurred = null;

    if ($updatedCol && !empty($row[$updatedCol])) {
        $occurred = $row[$updatedCol];
    } elseif ($createdCol && !empty($row[$createdCol])) {
        $occurred = $row[$createdCol];
    }

    if (!$occurred || !strtotime((string)$occurred)) {
        $occurred = date('Y-m-d H:i:s');
    }

    $status = '';
    foreach (['status', 'state', 'mode', 'payment_status', 'withdrawal_status', 'investment_status', 'transaction_status', 'type'] as $k) {
        if (!empty($row[$k])) {
            $status = (string)$row[$k];
            break;
        }
    }

    $amount = 0;
    foreach (['amount', 'deposit_amount', 'withdrawal_amount', 'fee_amount', 'expected_interest', 'total_amount'] as $k) {
        if (isset($row[$k]) && is_numeric($row[$k])) {
            $amount = (float)$row[$k];
            break;
        }
    }

    $actorType = 'system';
    $actorId = 0;
    $actorLabel = 'Database Change';

    if ($table === 'users') {
        $actorType = 'user';
        $actorId = $id;
        $actorLabel = 'User #' . $id;
    } elseif ($table === 'admins') {
        $actorType = 'admin';
        $actorId = $id;
        $actorLabel = 'Admin #' . $id;
    } else {
        $adminCandidate = 0;
        foreach (['admin_id', 'created_by', 'approved_by', 'supervisor_id'] as $k) {
            if (!empty($row[$k])) {
                $adminCandidate = (int)$row[$k];
                break;
            }
        }

        $userCandidate = 0;
        foreach (['user_id', 'uid', 'member_id', 'target_user_id'] as $k) {
            if (!empty($row[$k])) {
                $userCandidate = (int)$row[$k];
                break;
            }
        }

        if ($adminCandidate > 0) {
            $actorType = 'admin';
            $actorId = $adminCandidate;
            $actorLabel = 'Admin #' . $adminCandidate;
        } elseif ($userCandidate > 0) {
            $actorType = 'user';
            $actorId = $userCandidate;
            $actorLabel = 'User #' . $userCandidate;
        }
    }

    $details = syslog_row_summary($row);

    try {
        $hash = md5($table . ':' . $id . ':' . $occurred . ':' . $details);
    } catch (\Throwable $e) {
        $hash = uniqid('', true);
    }

    return [
        'event_uid' => 'scan:' . $table . ':' . $id . ':' . substr($hash, 0, 32),
        'event_type' => 'db_change',
        'category' => syslog_category_for_table($table),
        'actor_type' => $actorType,
        'actor_id' => $actorId,
        'actor_label' => $actorLabel,
        'target_type' => $table,
        'target_id' => $id,
        'target_label' => ucfirst(str_replace('_', ' ', $table)) . ' #' . $id,
        'amount' => $amount,
        'status' => $status,
        'details' => $details,
        'ip_address' => '',
        'user_agent' => '',
        'source_table' => $table,
        'source_id' => $id,
        'occurred_at' => $occurred,
    ];
}

function syslog_scan_table($conn, $table, $limit = 25)
{
    $result = [
        'table' => $table,
        'seen' => 0,
        'inserted' => 0,
        'baseline' => false,
        'skipped' => false,
    ];

    syslog_ensure_tables($conn);

    if (!syslog_table_exists($conn, $table)) {
        $result['skipped'] = true;
        return $result;
    }

    $idCol = syslog_pick_column($conn, $table, [
        'id',
        'withdrawal_id',
        'deposit_id',
        'investment_id',
        'transaction_id',
        'request_id',
        'log_id',
        'event_id',
    ]);

    if (!$idCol) {
        $result['skipped'] = true;
        return $result;
    }

    $createdCol = syslog_pick_column($conn, $table, [
        'created_at',
        'date',
        'timestamp',
        'created',
        'requested_at',
        'sent_at',
        'fee_date',
    ]);

    $updatedCol = syslog_pick_column($conn, $table, [
        'updated_at',
        'modified_at',
        'last_updated',
        'processed_at',
        'paid_at',
        'completed_at',
    ]);

    $state = syslog_get_state($conn, $table);

    if (!$state || !(int)($state['baseline_done'] ?? 0)) {
        $maxSql = "SELECT COALESCE(MAX(`$idCol`),0) AS max_id";

        if ($updatedCol) {
            $maxSql .= ", MAX(`$updatedCol`) AS max_upd";
        }

        $maxSql .= " FROM `$table`";

        $maxRes = syslog_try_query($conn, $maxSql);
        $maxId = 0;
        $maxUpd = null;

        if ($maxRes && $maxRow = $maxRes->fetch_assoc()) {
            $maxId = (int)($maxRow['max_id'] ?? 0);

            if ($updatedCol) {
                $maxUpd = $maxRow['max_upd'] ?? null;
            }
        }

        syslog_set_state($conn, $table, $maxId, $maxUpd, 1);

        $result['baseline'] = true;
        return $result;
    }

    $lastId = (int)($state['last_source_id'] ?? 0);
    $lastUpdated = $state['last_updated_at'] ?? null;

    $where = "`$idCol` > $lastId";

    if ($updatedCol && $lastUpdated) {
        $where .= " OR `$updatedCol` > '" . syslog_esc($conn, $lastUpdated) . "'";
    }

    $sql = "
        SELECT *
        FROM `$table`
        WHERE $where
        ORDER BY `$idCol` ASC
        LIMIT " . (int)$limit;

    $res = syslog_try_query($conn, $sql);

    if (!$res) {
        $result['skipped'] = true;
        return $result;
    }

    $maxId = $lastId;
    $maxUpdated = $lastUpdated;

    while ($row = $res->fetch_assoc()) {
        $result['seen']++;

        $rid = (int)($row[$idCol] ?? 0);

        if ($rid > $maxId) {
            $maxId = $rid;
        }

        $rowTs = null;

        if ($updatedCol && !empty($row[$updatedCol])) {
            $rowTs = $row[$updatedCol];
        } elseif ($createdCol && !empty($row[$createdCol])) {
            $rowTs = $row[$createdCol];
        }

        if ($rowTs && ($maxUpdated === null || $rowTs > $maxUpdated)) {
            $maxUpdated = $rowTs;
        }

        $event = syslog_make_scan_event($conn, $table, $row, $idCol, $createdCol, $updatedCol);

        if ($event && syslog_write($conn, $event)) {
            $result['inserted']++;
        }
    }

    syslog_set_state($conn, $table, $maxId, $maxUpdated, 1);

    return $result;
}

function syslog_scan_all($conn, $limitPerTable = 25)
{
    $stats = [
        'seen' => 0,
        'inserted' => 0,
        'baselines' => 0,
        'tables' => [],
        'updated_at' => date('H:i:s'),
    ];

    foreach (syslog_sources() as $table) {
        $r = syslog_scan_table($conn, $table, $limitPerTable);

        $stats['seen'] += (int)$r['seen'];
        $stats['inserted'] += (int)$r['inserted'];

        if (!empty($r['baseline'])) {
            $stats['baselines']++;
        }

        $stats['tables'][$table] = $r;
    }

    return $stats;
}

}