<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Load config ---
$config = require __DIR__ . '/config.php';
$dbcfg = $config['db'];
$smtpcfg = $config['smtp'];

// --- Include PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/src/Exception.php';
require __DIR__ . '/src/PHPMailer.php';
require __DIR__ . '/src/SMTP.php';

// --- Database Connection ---
$mysqli = new mysqli($dbcfg['host'], $dbcfg['user'], $dbcfg['pass'], $dbcfg['name']);
if ($mysqli->connect_error) {
    error_log("DB connect error: " . $mysqli->connect_error);
    die("Unable to connect to database.");
}

// --- Helpers ---
function clean($s) { return trim($s); }
function ip_address() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// --- CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_csrf)) {
        $message = 'Invalid form submission. Please refresh and try again.';
    } elseif (!isset($_POST['accept_terms'])) {
        $message = 'You must accept the Terms & Conditions.';
    } else {
        $fullname = clean($_POST['fullname'] ?? '');
        $username = clean($_POST['username'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $ref = isset($_GET['ref']) ? strtoupper(trim($_GET['ref'])) : null;

        if ($fullname === '' || $username === '' || $email === '' || $phone === '' || $password === '') {
            $message = 'Please fill all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email.';
        } elseif ($password !== $confirm) {
            $message = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/^(?:\+254|0)(7\d{8}|1\d{8})$/', $phone)) {
            $message = 'Invalid Kenyan phone number format.';
        } else {
            $mysqli->begin_transaction();
            try {
                // --- Check email ---
                $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $message = 'Email already registered.';
                    $stmt->close();
                    $mysqli->rollback();
                } else {
                    $stmt->close();

                    // --- Generate unique referral code ---
                    do {
                        $my_ref_code = strtoupper(bin2hex(random_bytes(4)));
                        $chk = $mysqli->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
                        $chk->bind_param('s', $my_ref_code);
                        $chk->execute();
                        $chk->store_result();
                        $exists = $chk->num_rows;
                        $chk->close();
                    } while ($exists > 0);

                    // --- Referral Lookup ---
                    $referred_by = null;
                    if ($ref) {
                        $stmt = $mysqli->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
                        $stmt->bind_param('s', $ref);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($row = $res->fetch_assoc()) $referred_by = $row['id'];
                        $stmt->close();
                    }

                    // --- Hash password ---
                    $hashed = password_hash($password, PASSWORD_BCRYPT);

                    // --- Record acceptance info ---
                    $accepted_terms_at = date('Y-m-d H:i:s');
                    $accepted_ip = ip_address();
                    $accepted_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);

                    // --- Insert user ---
                    $insert_sql = "INSERT INTO users 
                        (name, username, email, phone, password, referred_by, referral_code, accepted_terms_at, accepted_terms_ip, accepted_terms_user_agent)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $mysqli->prepare($insert_sql);

                    // Always bind 10 parameters (to match 10 placeholders)
                    // Use 'i' for referred_by (int) or 's' if null, but cast null safely
                    $ref_param = $referred_by ?? null;

                    // Use 's' for nullable — MySQL will store it as NULL automatically when param = null
                    $stmt->bind_param(
                        'ssssssssss',
                        $fullname,
                        $username,
                        $email,
                        $phone,
                        $hashed,
                        $ref_param,
                        $my_ref_code,
                        $accepted_terms_at,
                        $accepted_ip,
                        $accepted_agent
                    );

                    if (!$stmt->execute()) {
                        throw new Exception("Insert failed: " . $stmt->error);
                    }
                    $stmt->close();
                    $mysqli->commit();

                   // --- Send welcome email ---
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpcfg['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtpcfg['username'];
    $mail->Password = $smtpcfg['password'];
    $mail->SMTPSecure = $smtpcfg['encryption'] === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtpcfg['port'];

    $mail->setFrom($smtpcfg['from_email'], $smtpcfg['from_name']);
    $mail->addAddress($email, $fullname);
    $mail->isHTML(true);
    $mail->Subject = 'Welcome to GIBAL LTD';

    // ✅ Recommended HTML Email Body
    $mail->Body = '
    <html>
      <body style="font-family:Arial, Helvetica, sans-serif; background-color:#f7f7f7; margin:0; padding:0;">
        <div style="max-width:600px;margin:20px auto;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
          <div style="background:#0f172a;color:#ffffff;padding:20px;text-align:center;">
            <h2 style="margin:0;">Welcome to GIBAL LTD!</h2>
          </div>
          <div style="padding:25px;color:#333;">
            <p>Dear <strong>' . htmlspecialchars($fullname) . '</strong>,</p>
            <p>We’re excited to welcome you to <strong>GIBAL LTD</strong> — your trusted platform for growth, innovation, and investment success.</p>
            
            <p>Your account has been created successfully. Here are your login details:</p>
            <table style="width:100%;border-collapse:collapse;">
              <tr>
                <td style="padding:8px 0;"><strong>Username:</strong></td>
                <td style="padding:8px 0;">' . htmlspecialchars($username) . '</td>
              </tr>
              <tr>
                <td style="padding:8px 0;"><strong>Email:</strong></td>
                <td style="padding:8px 0;">' . htmlspecialchars($email) . '</td>
              </tr>
            </table>

            <p>Keep your credentials safe and log in at any time to manage your account and explore the latest opportunities.</p>
            
            <div style="text-align:center;margin:30px 0;">
              <a href="https://gtraders.gt.tc/login.php" 
                 style="background:#0f172a;color:#fff;text-decoration:none;padding:12px 25px;
                        border-radius:5px;display:inline-block;font-weight:bold;">
                Go to Dashboard
              </a>
            </div>

            <p>If you need any help, feel free to contact our support team at 
            <a href="mailto:gibal.ltd@gmail.com">gibal.ltd@gmail.com</a> or call <strong>+254 703 834 247</strong>.</p>

            <p>We’re delighted to have you with us. Here’s to your success!</p>

            <p style="margin-top:30px;">Warm regards,<br>
            <strong>The GIBAL LTD Team</strong></p>
          </div>
          <div style="background:#f0f0f0;text-align:center;padding:10px;font-size:12px;color:#666;">
            © ' . date('Y') . ' GIBAL LTD. All rights reserved.
          </div>
        </div>
      </body>
    </html>
    ';

    $mail->AltBody = "Welcome to GIBAL LTD, $fullname!\n\nYour account has been created successfully.\nUsername: $username\nEmail: $email\n\nLogin at: https://gtraders.gt.tc/login.php\n\nFor support, contact gibal.ltd@gmail.com or call +254 703 834 247.";

    $mail->send();
} catch (Exception $e) {
    error_log('Mail error: ' . $e->getMessage());
}


                    // --- Success message + redirect ---
                    echo '<div style="position:fixed;top:20px;left:50%;transform:translateX(-50%);
                        background:#00ff00;color:#000;padding:15px;border-radius:8px;
                        font-weight:bold;z-index:1000;">Account created successfully! Redirecting...</div>';
                    echo '<script>setTimeout(function(){window.location="login.php";},2000);</script>';
                    exit;
                }
            } catch (Exception $ex) {
                $mysqli->rollback();
                error_log("Registration error: " . $ex->getMessage());
                $message = "Registration failed. Try again later.";
            }
        }
    }
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Register - GIBAL LTD</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
/* keep your existing styling (trimmed for brevity) */
body{font-family:Arial,Helvetica,sans-serif;background:linear-gradient(270deg,#ffd700,#1e90ff,#ff4500);background-size:600% 600%;animation:gradientShift 15s ease infinite}
@keyframes gradientShift{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
.register-container{background:rgba(0,0,0,0.85);padding:40px;border-radius:12px;color:#fff;width:400px;margin:40px auto}
.register-container input{width:100%;padding:12px;margin:8px 0;border-radius:6px;border:none;font-size:16px}
.register-container button{width:100%;padding:12px;margin-top:12px;border-radius:6px;border:none;background:#ffd700;color:#000;font-weight:bold}
a{color:#ffd700}
.message{color:#ff5555;font-weight:bold}
</style>
</head>
<body>
<div class="register-container">
  <h2>Create Account</h2>
  <?php if (!empty($message)) echo "<p class='message'>" . htmlspecialchars($message) . "</p>"; ?>

  <form method="post" action="">
    <input type="text" name="fullname" placeholder="Full name" required>
    <input type="text" name="username" placeholder="Username" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="text" name="phone" placeholder="Phone (+254...)" required>
    <input type="password" name="password" placeholder="Password (min 8 chars)" required>
    <input type="password" name="confirm_password" placeholder="Confirm password" required>

    <label style="display:block;text-align:left;margin-top:10px;">
      <input type="checkbox" name="accept_terms" value="1" required>
      I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a>, <a href="privacy.php" target="_blank">Privacy Policy</a> and <a href="rules.php" target="_blank">Code of Conduct</a>.
    </label>

    <!-- CSRF token -->
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

    <button type="submit">Register</button>
  </form>

  <p style="margin-top:10px">Already have an account? <a href="login.php">Login</a></p>
</div>
</body>
</html>
