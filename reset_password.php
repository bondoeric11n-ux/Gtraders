<?php
session_start();
include_once "db_connect.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

$message = "";
$token = $_GET['token'] ?? '';

if(!$token){
    die("Invalid password reset link.");
}

$stmt = $conn->prepare("SELECT pr.user_id, pr.expires_at, u.email, u.name, u.username 
                        FROM password_resets pr 
                        JOIN users u ON u.id = pr.user_id 
                        WHERE pr.token=? LIMIT 1");
$stmt->bind_param("s",$token);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$user_data || strtotime($user_data['expires_at']) < time()){
    die("This reset link has expired or is invalid.");
}

if($_SERVER['REQUEST_METHOD']=="POST"){
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if($password !== $confirm){
        $message = "Passwords do not match.";
    }else{
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si",$hash,$user_data['user_id']);
        $stmt->execute();
        $stmt->close();

        // Delete used token
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE token=?");
        $stmt->bind_param("s",$token);
        $stmt->execute();
        $stmt->close();

        // Send styled confirmation email
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
            $mail->addAddress($user_data['email'], $user_data['name'] ?: $user_data['username']);
            $mail->isHTML(true);

            $mail->Subject = "Password Successfully Changed — GIBAL LTD";
            $mail->Body = "
            <div style='font-family:Arial,sans-serif; max-width:600px; margin:auto; padding:20px; border:2px solid #28a745; border-radius:12px; background:#000; color:#28a745;'>
                <h2 style='text-align:center; margin-bottom:20px;'>Password Successfully Changed</h2>
                <p>Hi <strong>".htmlspecialchars($user_data['name'] ?: $user_data['username'])."</strong>,</p>
                <p>Your account password has been successfully updated.</p>
                <p style='text-align:center; margin:25px 0;'>
                    <a href='https://gtraders.gt.tc/login.php' style='display:inline-block; padding:12px 25px; background:#28a745; color:#000; border-radius:6px; text-decoration:none; font-weight:bold;'>Login Now</a>
                </p>
                <p>If you did not perform this change, contact support immediately at <a href='mailto:gibal.ltd@gmail.com'>gibal.ltd@gmail.com</a>.</p>
                <p style='margin-top:20px;'>Thank you for trusting <strong>GIBAL LTD</strong>!</p>
            </div>";
            $mail->AltBody = "Hi ".$user_data['name'].", Your password has been changed successfully. If you didn't do this, contact gibal.ltd@gmail.com. Login: https://gtraders.gt.tc/login.php";

            $mail->send();
            $message = "Password updated successfully! You can now login.";
        }catch(Exception $e){
            $message = "Password changed but email not sent: ".$mail->ErrorInfo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password | GTraders</title>
<style>
body{font-family:Arial,sans-serif; background:#141e30; display:flex; justify-content:center; align-items:center; height:100vh;}
.reset-box{background: rgba(0,0,0,0.75); padding:30px; border-radius:12px; width:90%; max-width:350px; text-align:center; color:#FFD700;}
input, button{width:100%; padding:12px; margin:10px 0; border:none; border-radius:6px;}
button{background:#FFD700; font-weight:bold; cursor:pointer;}
button:hover{background:#ff4444; color:#fff;}
.error{color:#ff4444;}
</style>
</head>
<body>
<div class="reset-box">
<h2>Reset Password</h2>
<form method="POST">
    <input type="password" name="password" placeholder="New Password" required>
    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
    <button type="submit">Update Password</button>
</form>
<?php if($message) echo "<p class='error'>$message</p>"; ?>
</div>
</body>
</html>