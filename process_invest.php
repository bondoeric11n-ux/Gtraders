<?php
// process_matured.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

include("db_connect.php");

// --- Settings ---
$tz = new DateTimeZone('Africa/Nairobi');
$now = new DateTime('now', $tz);

// --- Fetch matured investments ---
$stmt = $conn->prepare("
    SELECT i.id, i.user_id, i.plan_name, i.amount, i.interest, u.email, u.username
    FROM investments i
    JOIN users u ON u.id = i.user_id
    WHERE i.status='active' AND i.maturity_date <= NOW()
");
$stmt->execute();
$result = $stmt->get_result();
$matured_investments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if(empty($matured_investments)){
    echo "No matured investments at this time.";
    exit;
}

$conn->begin_transaction();
try {
    foreach($matured_investments as $inv){
        $total_credit = $inv['amount'] + $inv['interest'];

        // 1. Update investment status
        $stmt1 = $conn->prepare("UPDATE investments SET status='matured', matured_at=NOW() WHERE id=?");
        $stmt1->bind_param("i", $inv['id']);
        $stmt1->execute();
        $stmt1->close();

        // 2. Credit user's balance
        $stmt2 = $conn->prepare("UPDATE users SET available_balance = available_balance + ? WHERE id=?");
        $stmt2->bind_param("di", $total_credit, $inv['user_id']);
        $stmt2->execute();
        $stmt2->close();

        // 3. Log transaction
        $desc = "Matured investment in {$inv['plan_name']}: principal Ksh ".number_format($inv['amount'],2)." + interest Ksh ".number_format($inv['interest'],2);
        $stmt3 = $conn->prepare("INSERT INTO transactions (user_id,type,amount,description,created_at) VALUES (?, 'credit', ?, ?, NOW())");
        $stmt3->bind_param("ids", $inv['user_id'], $total_credit, $desc);
        $stmt3->execute();
        $stmt3->close();

        // 4. Send email notification
        $mail = new PHPMailer(true);
        try {
            // SMTP settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'gibal.ltd@gmail.com'; // your email
            $mail->Password = 'dkbcereljkmvzfqy';    // app password
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('gibal.ltd@gmail.com', 'GIBAL LTD');
            $mail->addAddress($inv['email'], $inv['username']);
            $mail->isHTML(true);
            $mail->Subject = "💰 Investment Matured: {$inv['plan_name']}";

            $mailContent = "
            <div style='font-family:Arial,sans-serif; background:#111217; color:#eef2f6; padding:20px; border-radius:10px; max-width:600px; margin:auto;'>
                <h2 style='color:#D4AF37;'>💰 Congratulations, {$inv['username']}!</h2>
                <p>Your investment in <strong>{$inv['plan_name']}</strong> has matured.</p>
                <table style='width:100%; border-collapse:collapse; color:#000; background:#D4AF37; margin-top:10px;'>
                    <tr><td style='padding:8px;'>Principal</td><td style='padding:8px;'>Ksh ".number_format($inv['amount'],2)."</td></tr>
                    <tr><td style='padding:8px;'>Interest</td><td style='padding:8px;'>Ksh ".number_format($inv['interest'],2)."</td></tr>
                    <tr><td style='padding:8px;'>Total Credited</td><td style='padding:8px;'>Ksh ".number_format($total_credit,2)." 💰</td></tr>
                </table>
                <p style='margin-top:15px;'>The amount has been credited to your account. You can view it in your dashboard.</p>
                <p>Thank you for investing with <strong>GIBAL LTD</strong>!</p>
            </div>
            ";

            $mail->Body = $mailContent;
            $mail->send();
        } catch (Exception $e) {
            // Log but continue
            error_log("Email error for user {$inv['user_id']}: ".$mail->ErrorInfo);
        }
    }
    $conn->commit();
    echo count($matured_investments)." investment(s) processed and notifications sent.";
} catch(Exception $e){
    $conn->rollback();
    echo "Error processing matured investments: ".$e->getMessage();
}