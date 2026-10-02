<?php
// process_mature_investments.php
// CLI/Cron-friendly script to process matured investments
date_default_timezone_set('Africa/Nairobi');

include("db_connect.php");

// PHPMailer setup
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'src/PHPMailer.php';
require_once 'src/SMTP.php';
require_once 'src/Exception.php';

// Initialize PHPMailer once
$mailer = new PHPMailer(true);
try {
    $mailer->isSMTP();
    $mailer->Host = 'smtp.gmail.com';
    $mailer->SMTPAuth = true;
    $mailer->Username = 'gibal.ltd@gmail.com';
    $mailer->Password = 'dkbcereljkmvzfqy'; // Correct app password
    $mailer->SMTPSecure = 'tls';
    $mailer->Port = 587;
    $mailer->setFrom('gibal.ltd@gmail.com', 'GIBAL LTD');
    $mailer->isHTML(true);
} catch (Exception $e) {
    error_log("PHPMailer init failed: " . $e->getMessage());
    exit("Mailer initialization failed.\n");
}

// Fetch matured investments that haven't been processed
$stmt = $conn->prepare("
    SELECT i.id, i.user_id, i.plan_name, i.amount, i.net_amount, i.expected_interest, u.email, u.name
    FROM investments i
    JOIN users u ON u.id = i.user_id
    WHERE i.status='active' AND i.end_date <= NOW() AND (i.processed IS NULL OR i.processed=0)
");
$stmt->execute();
$investments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($investments)) {
    echo "[" . date('Y-m-d H:i:s') . "] No matured investments.\n";
    exit;
}

foreach ($investments as $inv) {
    $user_id = $inv['user_id'];
    $inv_id = $inv['id'];
    $net_amount = (float)$inv['net_amount'];
    $profit = (float)$inv['expected_interest'];
    $total_credit = $net_amount + $profit;

    $conn->begin_transaction();
    try {
        // Update user balances
        $stmt1 = $conn->prepare("
            UPDATE users
            SET account_balance = account_balance + ?,
                invested_balance = invested_balance - ?,
                total_expected_interest = total_expected_interest - ?
            WHERE id = ?
        ");
        $stmt1->bind_param("dddi", $total_credit, $net_amount, $profit, $user_id);
        $stmt1->execute();
        $stmt1->close();

        // Mark investment as processed and matured
        $stmt2 = $conn->prepare("
            UPDATE investments
            SET status='matured', processed=1, interest = ?
            WHERE id = ?
        ");
        $stmt2->bind_param("di", $profit, $inv_id);
        $stmt2->execute();
        $stmt2->close();

        // Log transaction
        $desc = "Investment matured in plan '{$inv['plan_name']}': principal Ksh ".number_format($net_amount,2)." + interest Ksh ".number_format($profit,2);
        $stmt3 = $conn->prepare("
            INSERT INTO transactions (user_id, type, amount, description, created_at)
            VALUES (?, 'investment_matured', ?, NOW())
        ");
        $stmt3->bind_param("id", $user_id, $total_credit);
        $stmt3->execute();
        $stmt3->close();

        $conn->commit();

        // Send email
        try {
            $mailer->clearAddresses();
            $mailer->addAddress($inv['email'], $inv['name']);
            $mailer->Subject = "💰 Investment Matured: {$inv['plan_name']}";
            $mailer->Body = "
            <div style='font-family:Arial,sans-serif; background:#f7f7f7; padding:20px; border-radius:10px;'>
                <h2 style='color:#D4AF37;'>Congratulations, {$inv['name']}!</h2>
                <p>Your investment in <strong>{$inv['plan_name']}</strong> has matured.</p>
                <table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width:100%;'>
                    <tr><td>Principal</td><td>Ksh ".number_format($net_amount,2)."</td></tr>
                    <tr><td>Interest</td><td>Ksh ".number_format($profit,2)."</td></tr>
                    <tr><td><strong>Total Credited</strong></td><td>Ksh ".number_format($total_credit,2)." 💰</td></tr>
                </table>
                <p>The amount has been credited to your account. You can view it in your dashboard.</p>
                <p>Thank you for investing with <strong>GIBAL LTD</strong>!</p>
            </div>";
            $mailer->send();
        } catch (Exception $e) {
            error_log("Email failed for user {$user_id}: " . $mailer->ErrorInfo);
        }

        echo "[" . date('Y-m-d H:i:s') . "] Processed investment ID {$inv_id} for user {$user_id}.\n";

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error processing investment ID {$inv_id}: " . $e->getMessage());
    }
}

echo "[" . date('Y-m-d H:i:s') . "] All matured investments processed.\n";
?>