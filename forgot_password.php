<?php
session_start();
include_once "db_connect.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

$message = "";

if($_SERVER['REQUEST_METHOD']=="POST"){
    $email = $conn->real_escape_string($_POST['email']);
    $stmt = $conn->prepare("SELECT id, name, username FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if($user){
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", time() + 3600); // 1 hour
        $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)");
        $stmt->bind_param("iss",$user['id'],$token,$expires);
        $stmt->execute();
        $stmt->close();

        // Send email using styled template
        $mail = new PHPMailer(true);
        try{
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'gibal.ltd@gmail.com';
            $mail->Password = 'dkbcereljkmvzfqy';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->setFrom('gibal.ltd@gmail.com','GIBAL LTD');
            $mail->addAddress($email, $user['name'] ?: $user['username']);
            $mail->isHTML(true);

            $resetLink = "https://gtraders.gt.tc/reset_password.php?token=$token";

            $mail->Subject = "Password Reset Request — GIBAL LTD";
            $mail->Body = "
            <div style='font-family:Arial,sans-serif; max-width:600px; margin:auto; padding:20px; border:2px solid #FFD700; border-radius:12px; background:#000; color:#FFD700;'>
                <h2 style='text-align:center; margin-bottom:20px;'>Password Reset Request</h2>
                <p>Hi <strong>".htmlspecialchars($user['name'] ?: $user['username'])."</strong>,</p>
                <p>We received a request to reset your password. Click the button below to set a new password. This link expires in 1 hour.</p>
                <p style='text-align:center; margin:25px 0;'>
                    <a href='$resetLink' style='display:inline-block; padding:12px 25px; background:#FFD700; color:#000; border-radius:6px; text-decoration:none; font-weight:bold;'>Reset Password</a>
                </p>
                <p>If you did not request this, please ignore this email.</p>
                <p style='margin-top:20px;'>Thank you for trusting <strong>GIBAL LTD</strong>!</p>
            </div>";

            $mail->AltBody = "Hi ".$user['name'].", Your password reset link: $resetLink. This link expires in 1 hour. If you didn't request this, ignore this email. GIBAL LTD";

            $mail->send();
            $message = "Password reset link sent to your email.";
        }catch(Exception $e){
            $message = "Error sending email: ".$mail->ErrorInfo;
        }
    }else{
        $message = "Email not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password</title>
<link rel="icon" type="image/png" href="favicon.png">
<style>
body{font-family:Arial,sans-serif; background:#141e30; display:flex; justify-content:center; align-items:center; height:100vh;}
.login-box{background: rgba(0,0,0,0.75); padding:30px; border-radius:12px; width:90%; max-width:350px; text-align:center; color:#FFD700;}
input, button{width:100%; padding:12px; margin:10px 0; border:none; border-radius:6px;}
button{background:#FFD700; font-weight:bold; cursor:pointer;}
button:hover{background:#ff4444; color:#fff;}
.error{color:#ff4444;}
</style>
</head>
<body>
<div class="login-box">
<h2>Forgot Password</h2>
<form method="POST">
    <input type="email" name="email" placeholder="Enter your email" required>
    <button type="submit">Send Reset Link</button>
</form>
<?php if($message) echo "<p class='error'>$message</p>"; ?>
</div>
</body>
</html>