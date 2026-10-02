<?php
session_start();
include_once 'db_connect.php';
include_once 'log_admin_action.php';

// PHPMailer setup for resend OTP
require_once 'src/Exception.php';
require_once 'src/PHPMailer.php';
require_once 'src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;

$mailUsername = 'gibal.ltd@gmail.com';
$mailPassword = 'dkbcereljkmvzfqy';
$mailFromName = 'GIBAL LTD';

$error = "";

// If no pending admin session, redirect
if (!isset($_SESSION['pending_admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Initialize OTP attempt counter
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}

// Handle OTP submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp_code'])) {
    $entered_otp = trim($_POST['otp_code']);

    if (!isset($_SESSION['pending_otp']) || !isset($_SESSION['otp_expiry'])) {
        $error = "Session expired. Please login again.";
        session_destroy();
    } elseif (time() > $_SESSION['otp_expiry']) {
        $error = "OTP expired. Please login again.";
        session_destroy();
    } elseif ($entered_otp != $_SESSION['pending_otp']) {
        $_SESSION['otp_attempts']++;

        // Log failed attempt
        log_admin_action($conn, $_SESSION['pending_admin_id'], "2FA Failed", "Incorrect OTP attempt {$_SESSION['otp_attempts']}");

        if ($_SESSION['otp_attempts'] >= 3) {
            $error = "Too many incorrect OTP attempts. Please login again.";
            session_destroy();
        } else {
            $error = "Incorrect OTP. Attempt {$_SESSION['otp_attempts']} of 3.";
        }
    } else {
        // OTP is correct: complete login
        $_SESSION['admin_id']       = $_SESSION['pending_admin_id'];
        $_SESSION['admin_username'] = $_SESSION['pending_admin_username'];

        // Clear temporary session variables
        unset($_SESSION['pending_admin_id'], $_SESSION['pending_admin_username'], $_SESSION['pending_otp'], $_SESSION['otp_expiry'], $_SESSION['otp_attempts']);

        // Log successful OTP verification
        log_admin_action($conn, $_SESSION['admin_id'], "2FA Verified", "Admin passed email OTP verification");

        header("Location: admin_dashboard.php");
        exit();
    }
}

// Handle resend OTP request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    $otp = random_int(100000, 999999);
    $_SESSION['pending_otp'] = $otp;
    $_SESSION['otp_expiry']  = time() + 300; // reset 5 min expiry
    $_SESSION['otp_attempts'] = 0;

    // Fetch admin email
    $stmt = $conn->prepare("SELECT email, username FROM admins WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['pending_admin_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin  = $result->fetch_assoc();

    if ($admin) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $mailUsername;
            $mail->Password   = $mailPassword;
            $mail->SMTPSecure = 'ssl';
            $mail->Port       = 465;

            $mail->setFrom($mailUsername, $mailFromName);
            $mail->addAddress($admin['email']);
            $mail->Subject = "Your Admin Login OTP (Resent)";
            $mail->Body    = "Hello {$admin['username']},\n\nYour new OTP is: $otp\nIt expires in 5 minutes.";

            $mail->send();
            $error = "✅ New OTP sent to your email.";

            // Log resend
            log_admin_action($conn, $_SESSION['pending_admin_id'], "2FA OTP Resent", "OTP resent to admin");

        } catch (Throwable $e) {
            $error = "Failed to resend OTP: " . $mail->ErrorInfo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify OTP</title>
<style>
body { margin:0; font-family: Arial,sans-serif; background:#0a0a0a; color:#eee; height:100vh; display:flex; justify-content:center; align-items:center; padding:20px; }
.otp-container { width:100%; max-width:400px; background:#1b1b1b; padding:25px; border-radius:12px; box-shadow:0 0 25px rgba(255,215,0,0.2); }
h1 { color:gold; text-align:center; margin-bottom:20px; }
input { width:100%; padding:12px; margin:10px 0; border:none; background:#2a2a2a; color:#fff; border-radius:6px; font-size:16px; }
button { width:100%; padding:12px; background:gold; border:none; border-radius:6px; cursor:pointer; font-size:16px; color:#111; font-weight:bold; margin-top:10px; transition:0.2s; }
button:hover { background:#e60000; color:white; }
.error { color:#ff4444; text-align:center; margin-bottom:10px; font-weight:bold; }
.success { color:#4caf50; text-align:center; margin-bottom:10px; font-weight:bold; }
</style>
</head>
<body>
<div class="otp-container">
    <h1>Enter OTP</h1>
    <?php if(!empty($error)) {
        $cls = strpos($error,'✅')===0?'success':'error';
        echo "<p class='$cls'>".htmlspecialchars($error)."</p>";
    } ?>
    <form method="POST">
        <input type="text" name="otp_code" placeholder="6-digit code" required>
        <button type="submit">Verify Login</button>
    </form>
    <form method="POST" style="margin-top:10px;">
        <button type="submit" name="resend_otp">Resend OTP</button>
    </form>
</div>
</body>
</html>
