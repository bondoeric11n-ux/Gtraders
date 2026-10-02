<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once("db_connect.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'src/Exception.php';
require_once 'src/PHPMailer.php';
require_once 'src/SMTP.php';

// Fetch all users
$query = "SELECT email FROM users WHERE email IS NOT NULL AND email != ''";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    die("No users found.");
}

while ($row = $result->fetch_assoc()) {

    $email = $row['email'];

    // PHPMailer setup
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'gibal.ltd@gmail.com'; // your email
        $mail->Password   = 'dkbcereljkmvzfqy';    // your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('gibal.ltd@gmail.com', 'GIBAL Team - GIBAL LTD');
        $mail->addAddress($email);

        // Styled corporate HTML email
        $mail->isHTML(true);
        $mail->Subject = "APPRECIATION: GIBAL TEAM";

        $mail->Body = '
<div style="font-family: Arial, sans-serif; background-color: #f4f7fb; padding: 30px;">
    <div style="max-width: 600px; margin: auto; background: #ffffff; border-radius: 10px; overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
        
        <!-- Header -->
        <div style="background: #0a3d62; padding: 20px; text-align: center; color: #ffffff;">
            <h2 style="margin: 0; font-weight: 600;">GIBAL LTD</h2>
            <p style="margin: 0; opacity: 0.9;">Client Relations Division</p>
        </div>

        <!-- Body Content -->
        <div style="padding: 30px; color: #333333; font-size: 15px; line-height: 1.6;">
            <p>Dear Valued Member,</p>

            <p>
                We would like to take a moment to express our appreciation for having you with us.
                Your presence in our community means a great deal, and we are glad to have you as part of our growing team.
            </p>

            <p>
                As we continue to expand our services and improve our platform, your trust and participation are what make
                this journey possible. We remain committed to providing a secure, transparent, and reliable experience
                for all our users.
            </p>

            <p>
                Should you ever require assistance, guidance, or support, our team is always ready to help.
            </p>

            <p>Thank you for being part of GIBAL LTD.</p>

            <p>
                Warm regards,<br>
                <strong>GIBAL LTD Team</strong>
            </p>
        </div>

        <!-- Footer -->
        <div style="background: #f0f3f8; padding: 15px; text-align: center; font-size: 13px; color: #555;">
            © ' . date("Y") . ' GIBAL LTD — All Rights Reserved
        </div>

    </div>
</div>';

        $mail->send();
        echo "Email sent to: $email<br>";

    } catch (Exception $e) {
        echo "Failed to send to $email. Error: {$mail->ErrorInfo}<br>";
    }
}
?>
