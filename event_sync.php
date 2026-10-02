<?php
@mysqli_report(MYSQLI_REPORT_OFF);
ini_set('display_errors', 0);

require_once 'db_connect.php';

header('Content-Type: application/json; charset=UTF-8');

// CHANGE THIS SECRET BEFORE USING
$sync_secret = 'ChangeThisEventSyncSecret2026Brian!';

if (!hash_equals($sync_secret, $_GET['secret'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

if (!function_exists('syslog_scan_all')) {
    echo json_encode(['success' => false, 'message' => 'system_logger.php not loaded']);
    exit();
}

$stats = syslog_scan_all($conn, 25);

echo json_encode([
    'success' => true,
    'stats' => $stats,
    'updated_at' => date('c')
]);