<?php
// =======================================
// ERROR HANDLING
// =======================================
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
include_once 'db_connect.php';
include_once 'log_admin_action.php';

// PHPMailer setup
require_once 'src/Exception.php';
require_once 'src/PHPMailer.php';
require_once 'src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;

$mailUsername   = 'gibal.ltd@gmail.com';
$mailPassword   = 'dkbcereljkmvzfqy';
$mailFromName   = 'GIBAL LTD';

$error = "";

// =======================================
// LOGIN PROCESS
// =======================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $identifier = trim($_POST['identifier']);
    $password   = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin  = $result->fetch_assoc();

    if ($admin && password_verify($password, $admin['password'])) {

        // Generate OTP
        $otp = random_int(100000, 999999);

        // Store OTP in session
        $_SESSION['pending_admin_id']   = $admin['id'];
        $_SESSION['pending_admin_username'] = $admin['username'];
        $_SESSION['pending_otp'] = $otp;
        $_SESSION['otp_expiry']  = time() + 300; // expires in 5 min

        // Send OTP email to admin's own email
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
            $mail->addAddress($admin['email']); // send to the admin’s own email

            $mail->Subject = "Your Admin Login OTP";
            $mail->Body    = "Hello {$admin['username']},\n\nYour login OTP is: $otp\nIt expires in 5 minutes.";

            $mail->send();

            // Redirect to OTP verification page
            header("Location: verify_otp.php");
            exit();

        } catch (Throwable $e) {
            $error = "Failed to send OTP email: " . $mail->ErrorInfo;
        }

    } else {
        $error = "❌ Invalid username/email or password.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    body { margin:0; font-family: Arial,sans-serif; background:#0a0a0a; color:#eee; height:100vh; display:flex; justify-content:center; align-items:center; padding:20px; }
    .login-container { width:100%; max-width:400px; background:#1b1b1b; padding:25px; border-radius:12px; box-shadow:0 0 25px rgba(255,215,0,0.2); }
    h1 { color:gold; text-align:center; margin-bottom:20px; }
    input { width:100%; padding:12px; margin:10px 0; border:none; background:#2a2a2a; color:#fff; border-radius:6px; font-size:16px; }
    button { width:100%; padding:12px; background:gold; border:none; border-radius:6px; cursor:pointer; font-size:16px; color:#111; font-weight:bold; margin-top:10px; transition:0.2s; }
    button:hover { background:#e60000; color:white; }
    .error { color:#ff4444; text-align:center; margin-bottom:10px; font-weight:bold; }
</style>
</head>
<body>
<div class="login-container">
    <h1>Admin Login</h1>
    <?php if(!empty($error)) echo "<p class='error'>".htmlspecialchars($error)."</p>"; ?>
    <form method="POST">
        <input type="text" name="identifier" placeholder="Email or Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
