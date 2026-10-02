<?php
// auto_mature_investments.php
include("db_connect.php");

// PHPMailer imports
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';

// Ensure 'processed' column exists
$conn->query("ALTER TABLE investments ADD COLUMN IF NOT EXISTS processed TINYINT(1) DEFAULT 0");

// 1️⃣ Fix missing interest for old investments
$conn->query("
    UPDATE investments i
    JOIN plans p ON i.plan_id = p.id
    SET i.interest = i.amount * (p.interest_rate / 100)
    WHERE i.interest = 0
");

// 2️⃣ Fetch all matured, active, unprocessed investments
$result = $conn->query("
    SELECT i.id, i.user_id, i.amount, i.interest, u.email, u.name, p.name AS plan_name
    FROM investments i
    JOIN users u ON i.user_id = u.id
    JOIN plans p ON i.plan_id = p.id
    WHERE i.status = 'active' 
    AND i.end_date <= NOW() 
    AND (i.processed IS NULL OR i.processed = 0)
");

if ($result->num_rows === 0) {
    echo "✅ No matured investments to process.\n";
    exit;
}

while ($inv = $result->fetch_assoc()) {
    $user_id = $inv['user_id'];
    $email   = $inv['email'];
    $name    = $inv['name'] ?? 'Investor';
    $total   = $inv['amount'] + $inv['interest'];
    $plan_name = $inv['plan_name'];

    $conn->begin_transaction();
    try {
        // Mark investment as matured and processed
        $stmt = $conn->prepare("UPDATE investments SET status='matured', processed=1 WHERE id=?");
        $stmt->bind_param("i", $inv['id']);
        $stmt->execute();
        $stmt->close();

        // Update user balances
        $stmt2 = $conn->prepare("
            UPDATE users 
            SET account_balance = account_balance + ?, 
                invested_balance = invested_balance - ? 
            WHERE id = ?
        ");
        $stmt2->bind_param("ddi", $total, $inv['amount'], $user_id);
        $stmt2->execute();
        $stmt2->close();

        // Log transaction
        $desc = "Investment matured: Principal Ksh {$inv['amount']} + Interest Ksh {$inv['interest']}";
        $stmt3 = $conn->prepare("
            INSERT INTO transactions (user_id, type, amount, description, created_at)
            VALUES (?, 'investment_matured', ?, ?, NOW())
        ");
        $stmt3->bind_param("ids", $user_id, $total, $desc);
        $stmt3->execute();
        $stmt3->close();

        // In-app notification
        $msg = "✅ Your investment in plan '{$plan_name}' of Ksh {$inv['amount']} + interest Ksh {$inv['interest']} has matured and been credited.";
        $stmt4 = $conn->prepare("INSERT INTO user_notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
        $stmt4->bind_param("is", $user_id, $msg);
        $stmt4->execute();
        $stmt4->close();

        $conn->commit();

        // Send email notification
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'gibal.ltd@gmail.com';
            $mail->Password   = 'dkbcereljkmvzfqy';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->setFrom('gibal.ltd@gmail.com', 'GIBAL LTD');
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = "💰 Your Investment has Matured!";
            $mail->Body = "
                <h2>Congratulations, {$name}!</h2>
                <p>Your investment in <strong>{$plan_name}</strong> has matured successfully.</p>
                <ul>
                    <li>Principal: Ksh ".number_format($inv['amount'],2)."</li>
                    <li>Interest: Ksh ".number_format($inv['interest'],2)."</li>
                    <li>Total Credited: Ksh ".number_format($total,2)." 💰</li>
                    <li>Date: ".date('Y-m-d H:i:s')."</li>
                </ul>
                <p>Funds have been added to your account balance. Thank you for investing with <strong>GIBAL LTD</strong>.</p>
            ";
            $mail->send();
            echo "📧 Email sent to {$email}\n";
        } catch (Exception $e) {
            error_log("❌ Email to {$email} failed: " . $mail->ErrorInfo);
        }

    } catch (Exception $e) {
        $conn->rollback();
        error_log("❌ Transaction failed for investment ID {$inv['id']}: " . $e->getMessage());
    }
}

echo "✅ Automatic maturity process completed successfully with email notifications.\n";