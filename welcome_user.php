<?php
// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php'; // Make sure PHPMailer is installed via Composer

// --- User data (replace with actual registration values) ---
$user_name  = "Eric Mbondo";         // Get from registration form
$user_email = "user@example.com";    // Get from registration form
$user_phone = "+254795520828";       // WhatsApp number with country code

// ------------------------
// 1. Send WhatsApp Message
// ------------------------

// WhatsApp API endpoint (example uses Twilio or similar)
$wa_api_url = "https://api.whatsappprovider.com/sendMessage"; // Replace with your provider
$wa_api_key = "YOUR_API_KEY"; // Replace with your API key
$wa_message = "Hi $user_name, welcome to GTraders! 🎉\nWe're thrilled to have you onboard. Start exploring investment plans today: https://yourwebsite.com";

$wa_data = [
    "to" => $user_phone,
    "message" => $wa_message
];

$ch = curl_init($wa_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($wa_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $wa_api_key
]);
$wa_response = curl_exec($ch);
curl_close($ch);

// Optional: log the response
file_put_contents("wa_log.txt", date("Y-m-d H:i:s") . " - $user_phone - $wa_response\n", FILE_APPEND);

// ------------------------
// 2. Send Email Message
// ------------------------
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.example.com';    // Your SMTP server
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gibal.ltd@gmail.com'; // Your SMTP username
    $mail->Password   = 'YOUR_SMTP_PASSWORD';  // Your SMTP password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('gibal.ltd@gmail.com', 'GTraders');
    $mail->addAddress($user_email, $user_name);

    $mail->isHTML(true);
    $mail->Subject = 'Welcome to GTraders!';
    $mail->Body    = "
        <h2>Hi $user_name,</h2>
        <p>Welcome to <strong>GTraders</strong>! 🎉</p>
        <p>We're thrilled to have you on board. Explore our platform and start your investment journey today.</p>
        <p><a href='https://yourwebsite.com'>Go to Dashboard</a></p>
        <br>
        <p>Cheers,<br>Team GTraders</p>
    ";

    $mail->send();
    echo "Welcome messages sent successfully!";
} catch (Exception $e) {
    echo "Email could not be sent: {$mail->ErrorInfo}";
}
?>
