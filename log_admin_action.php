<?php
function log_admin_action($conn, $admin_id, $action_type, $details, $target_user_id = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, details, target_user_id, ip_address, action_time) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issis", $admin_id, $action_type, $details, $target_user_id, $ip);
    $stmt->execute();
    $stmt->close();
}
?>
