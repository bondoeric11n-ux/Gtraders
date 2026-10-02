<?php
// send_daily_audit.php
require_once 'db_connect.php';
require_once 'src/Exception.php';
require_once 'src/PHPMailer.php';
require_once 'src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Fetch today's audit logs
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT * FROM admin_audit_log WHERE DATE(created_at) = ? ORDER BY created_at DESC");
$stmt->bind_param("s", $today);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 2. Build HTML Email Body
$html_body = "
    <div style='font-family: Arial, sans-serif; max-width: 800px; margin: auto; padding: 20px; background: #0b1120; color: #f1f5f9; border: 1px solid rgba(6, 182, 212, 0.3); border-radius: 12px;'>
        <h2 style='text-align: center; color: #22d3ee;'>GIBAL LTD - Daily Audit Report</h2>
        <p style='text-align: center; color: #cbd5e1;'>Date: " . date('F d, Y') . "</p>
        <p style='color: #cbd5e1;'>Total actions recorded today: <strong>" . count($logs) . "</strong></p>
        
        <table style='width: 100%; border-collapse: collapse; margin-top: 20px; background: #111827; border-radius: 8px; overflow: hidden;'>
            <thead>
                <tr style='background: #1f2937; color: #22d3ee;'>
                    <th style='padding: 12px; text-align: left;'>Admin</th>
                    <th style='padding: 12px; text-align: left;'>Action</th>
                    <th style='padding: 12px; text-align: left;'>Details</th>
                    <th style='padding: 12px; text-align: left;'>IP Address</th>
                    <th style='padding: 12px; text-align: left;'>Time</th>
                </tr>
            </thead>
            <tbody>";

if (empty($logs)) {
    $html_body .= "<tr><td colspan='5' style='padding: 20px; text-align: center; color: #64748b;'>No admin actions recorded today.</td></tr>";
} else {
    foreach ($logs as $log) {
        $html_body .= "
                <tr style='border-bottom: 1px solid rgba(148, 163, 184, 0.1);'>
                    <td style='padding: 12px;'>" . htmlspecialchars($log['admin_username']) . "</td>
                    <td style='padding: 12px;'><span style='background: rgba(59, 130, 246, 0.12); color: #3b82f6; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 700;'>" . htmlspecialchars($log['action']) . "</span></td>
                    <td style='padding: 12px;'>" . htmlspecialchars($log['details']) . "</td>
                    <td style='padding: 12px; font-family: monospace; font-size: 0.85rem;'>" . htmlspecialchars($log['ip_address']) . "</td>
                    <td style='padding: 12px; font-size: 0.85rem;'>" . date('H:i:s', strtotime($log['created_at'])) . "</td>
                </tr>";
    }
}

$html_body .= "
            </tbody>
        </table>
        <p style='margin-top: 30px; text-align: center; color: #64748b; font-size: 0.85rem;'>This is an automated daily security report from GIBAL LTD.</p>
    </div>";

// 3. Send Email via PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gibal.ltd@gmail.com';
    $mail->Password   = 'dkbcereljkmvzfqy'; // Your App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->setFrom('gibal.ltd@gmail.com', 'GIBAL LTD Security');
    $mail->addAddress('gibal.ltd@gmail.com', 'GIBAL Super Admin');
    $mail->isHTML(true);
    $mail->Subject = "🔒 Daily Audit Report - " . date('F d, Y');
    $mail->Body    = $html_body;
    $mail->AltBody = "Daily Audit Report for " . date('F d, Y') . ". Total actions: " . count($logs) . ". Please view this email in an HTML-compatible client.";
    
    $mail->send();
    echo "Daily audit report sent successfully.";
} catch (Exception $e) {
    error_log("Daily Audit Email Failed: " . $mail->ErrorInfo);
    echo "Failed to send email. Check error log.";
}
?>