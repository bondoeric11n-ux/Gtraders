<?php
session_start();
include_once "db_connect.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'src/Exception.php';
require_once 'src/PHPMailer.php';
require_once 'src/SMTP.php';

$toast_message = "";
$toast_type = "info";

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// --- Handle Login ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Upgraded to prepared statement for enhanced security
    $stmt = $conn->prepare("SELECT id, password, name, username FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            header("Location: dashboard.php");
            exit();
        } else {
            $toast_message = "Invalid password. Please try again.";
            $toast_type = "error";
        }
    } else {
        $toast_message = "Email not found. Please check your email or register.";
        $toast_type = "error";
    }
    $stmt->close();
}

// --- Handle Password Reset Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset'])) {
    $email = trim($_POST['reset_email']);
    
    $stmt = $conn->prepare("SELECT id, name, username FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $userSql = $stmt->get_result();
    
    if ($userSql->num_rows === 1) {
        $user = $userSql->fetch_assoc();
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Create password_resets table if not exists
        $conn->query("
            CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Insert reset token
        $insertStmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $insertStmt->bind_param("iss", $user['id'], $token, $expires);
        $insertStmt->execute();
        $insertStmt->close();

        // Send reset email
        $resetLink = "https://gtraders.gt.tc/reset_password.php?token=$token";
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'gibal.ltd@gmail.com';
            $mail->Password   = 'dkbcereljkmvzfqy'; // App password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->setFrom('gibal.ltd@gmail.com', 'GIBAL LTD');
            $mail->addAddress($email, $user['name'] ?: $user['username']);
            $mail->isHTML(true);
            $mail->Subject = "Password Reset Request — GIBAL LTD";
            
            $userName = htmlspecialchars($user['name'] ?: $user['username']);
            $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 30px; background: #0f1420; color: #f1f5f9; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 16px;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <div style='width: 50px; height: 50px; background: linear-gradient(135deg, #d4af37 0%, #b8941f 100%); border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; color: #0a0e1a; font-weight: 800; font-size: 1.5rem;'>G</div>
                </div>
                <h2 style='text-align: center; color: #d4af37; margin-bottom: 20px;'>Password Reset Request</h2>
                <p>Hi <strong>{$userName}</strong>,</p>
                <p>We received a request to reset your password. Click the button below to reset it. This link will expire in 1 hour.</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetLink}' style='display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #d4af37 0%, #b8941f 100%); color: #0a0e1a; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 1rem;'>Reset Password</a>
                </p>
                <p style='color: #94a3b8; font-size: 0.9rem;'>If you didn't request this, you can safely ignore this email. Your password will remain unchanged.</p>
                <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(148, 163, 184, 0.1); color: #94a3b8; font-size: 0.85rem;'>Thank you,<br><strong style='color: #d4af37;'>GIBAL LTD</strong></p>
            </div>";
            
            $mail->AltBody = "Hi {$userName}, reset your password using this link: {$resetLink} (expires in 1 hour).";
            $mail->send();
            
            $toast_message = "Password reset email sent successfully. Please check your inbox.";
            $toast_type = "success";
        } catch(Exception $e) {
            $toast_message = "Failed to send reset email. Please try again later.";
            $toast_type = "error";
            error_log("Password reset email error: " . $mail->ErrorInfo);
        }
    } else {
        // Security best practice: Don't reveal if email exists or not
        $toast_message = "If an account with that email exists, a reset link has been sent.";
        $toast_type = "info";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | GIBAL LTD</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ============================================
   PREMIUM FINTECH DESIGN SYSTEM
   ============================================ */
:root {
    --bg-primary: #0a0e1a;
    --bg-secondary: #0f1420;
    --bg-tertiary: #151b2b;
    --bg-card: rgba(17, 24, 39, 0.75);
    --bg-elevated: rgba(30, 41, 59, 0.5);
    
    --gold: #d4af37;
    --gold-light: #f4d03f;
    --gold-dark: #b8941f;
    --gold-glow: rgba(212, 175, 55, 0.15);
    --gold-border: rgba(212, 175, 55, 0.25);
    
    --text-primary: #f1f5f9;
    --text-secondary: #94a3b8;
    --text-tertiary: #64748b;
    
    --success: #10b981;
    --success-bg: rgba(16, 185, 129, 0.12);
    --success-border: rgba(16, 185, 129, 0.25);
    --danger: #ef4444;
    --danger-bg: rgba(239, 68, 68, 0.12);
    --danger-border: rgba(239, 68, 68, 0.25);
    --info: #3b82f6;
    --info-bg: rgba(59, 130, 246, 0.12);
    --info-border: rgba(59, 130, 246, 0.25);
    
    --border-subtle: rgba(148, 163, 184, 0.08);
    --shadow-lg: 0 20px 50px rgba(0, 0, 0, 0.5);
    --shadow-gold: 0 8px 30px rgba(212, 175, 55, 0.15);
    
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--bg-primary);
    color: var(--text-primary);
    height: 100vh;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    background-image: 
        radial-gradient(ellipse at top left, rgba(212, 175, 55, 0.06) 0%, transparent 50%),
        radial-gradient(ellipse at bottom right, rgba(59, 130, 246, 0.04) 0%, transparent 50%);
}

/* Animated Star Background */
#starCanvas {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%;
    z-index: 0;
    opacity: 0.6;
    pointer-events: none;
}

/* Login Card */
.login-wrapper {
    position: relative;
    z-index: 10;
    width: 90%;
    max-width: 440px;
    animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.login-card {
    background: var(--bg-card);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--gold-border);
    border-radius: var(--radius-lg);
    padding: 40px 32px;
    box-shadow: var(--shadow-lg), 0 0 60px rgba(212, 175, 55, 0.08);
    text-align: center;
}

.brand-logo {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--bg-primary);
    font-weight: 800;
    font-size: 1.5rem;
    margin: 0 auto 20px;
    box-shadow: var(--shadow-gold);
}

.login-card h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--text-primary);
}

.login-card .subtitle {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-bottom: 28px;
}

/* Forms */
.form-group { margin-bottom: 18px; text-align: left; }
.form-label {
    display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary);
    margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em;
}
.form-input {
    width: 100%; padding: 14px 16px;
    background: var(--bg-elevated); border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm); color: var(--text-primary);
    font-size: 0.95rem; transition: all 0.2s ease; font-family: inherit;
}
.form-input:focus {
    outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px var(--gold-glow);
}
.form-input::placeholder { color: var(--text-tertiary); }

.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px 24px; border-radius: var(--radius-sm); font-weight: 600;
    font-size: 0.95rem; cursor: pointer; transition: all 0.2s ease; border: none;
    text-decoration: none; width: 100%; margin-top: 8px;
}
.btn-primary {
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-primary);
}
.btn-primary:hover {
    transform: translateY(-2px); box-shadow: var(--shadow-gold);
}

.btn-secondary {
    background: transparent; border: 1px solid var(--border-subtle);
    color: var(--text-secondary); margin-top: 12px;
}
.btn-secondary:hover {
    background: var(--bg-elevated); color: var(--text-primary); border-color: var(--gold-border);
}

/* Links */
.forgot-link {
    display: block; margin-top: 16px; color: var(--gold);
    text-decoration: none; font-size: 0.85rem; font-weight: 500;
    transition: opacity 0.2s;
}
.forgot-link:hover { opacity: 0.8; text-decoration: underline; }

.register-text {
    margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-subtle);
    color: var(--text-secondary); font-size: 0.9rem;
}
.register-text a {
    color: var(--gold); text-decoration: none; font-weight: 600;
    transition: opacity 0.2s;
}
.register-text a:hover { opacity: 0.8; text-decoration: underline; }

/* Toggle Forms */
.form-section { display: none; animation: fadeIn 0.3s ease; }
.form-section.active { display: block; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Toast Notifications */
.toast-container {
    position: fixed; top: 24px; right: 24px; z-index: 9999;
    display: flex; flex-direction: column; gap: 10px; pointer-events: none;
}
.toast {
    background: var(--bg-secondary); border: 1px solid var(--gold-border);
    border-left: 4px solid var(--gold); border-radius: var(--radius-md);
    padding: 14px 18px; box-shadow: var(--shadow-lg);
    display: flex; align-items: center; gap: 12px; min-width: 300px; max-width: 400px;
    transform: translateX(450px); opacity: 0; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: auto;
}
.toast.show { transform: translateX(0); opacity: 1; }
.toast-icon {
    width: 36px; height: 36px; border-radius: 50%; background: var(--gold-glow);
    display: flex; align-items: center; justify-content: center; color: var(--gold); flex-shrink: 0;
}
.toast-content { flex: 1; text-align: left; }
.toast-title { font-weight: 600; font-size: 0.9rem; color: var(--text-primary); margin-bottom: 2px; }
.toast-message { font-size: 0.8rem; color: var(--text-secondary); }
.toast-close {
    background: none; border: none; color: var(--text-tertiary); cursor: pointer;
    padding: 4px; font-size: 1rem; transition: color 0.2s;
}
.toast-close:hover { color: var(--text-primary); }

/* Responsive */
@media (max-width: 480px) {
    .login-card { padding: 32px 24px; }
    .toast-container { right: 12px; left: 12px; }
    .toast { min-width: auto; max-width: 100%; }
}
</style>
<link rel="icon" type="image/png" href="favicon.png">
</head>
<body>

<canvas id="starCanvas"></canvas>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Login Wrapper -->
<div class="login-wrapper">
    <div class="login-card">
        <div class="brand-logo">G</div>
        
        <!-- Login Form Section -->
        <div id="loginSection" class="form-section active">
            <h2>Welcome Back</h2>
            <p class="subtitle">Sign in to access your investment portfolio</p>
            
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="name@example.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <a href="#" class="forgot-link" onclick="toggleForm('resetSection'); return false;">
                Forgot your password?
           a>
            
            <div class="register-text">
                Don't have an account? <a href="register.php">Create Account</a>
            </div>
        </div>

        <!-- Password Reset Form Section -->
        <div id="resetSection" class="form-section">
            <h2>Reset Password</h2>
            <p class="subtitle">Enter your email to receive a reset link</p>
            
            <form method="POST" id="resetForm">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="reset_email" class="form-input" placeholder="name@example.com" required>
                </div>
                <button type="submit" name="reset" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>
            
            <button type="button" class="btn btn-secondary" onclick="toggleForm('loginSection')">
                <i class="fas fa-arrow-left"></i> Back to Login
            </button>
        </div>
        
    </div>
</div>

<script>
// Toggle between Login and Reset forms
function toggleForm(sectionId) {
    document.querySelectorAll('.form-section').forEach(el => el.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
    
    // Clear toast messages when switching forms
    document.getElementById('toastContainer').innerHTML = '';
}

// Toast Notification System
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast';
    
    const icons = {
        info: 'fa-info-circle',
        success: 'fa-check-circle',
        warning: 'fa-exclamation-triangle',
        error: 'fa-times-circle'
    };
    
    toast.innerHTML = `
        <div class="toast-icon"><i class="fas ${icons[type] || icons.info}"></i></div>
        <div class="toast-content">
            <div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 5000);
}

// Trigger Toast on Page Load if PHP set a message
<?php if (!empty($toast_message)): ?>
window.addEventListener('load', () => {
    showToast("<?php echo addslashes($toast_message); ?>", "<?php echo $toast_type; ?>");
});
<?php endif; ?>

// Premium Starry Background Animation
const canvas = document.getElementById('starCanvas');
const ctx = canvas.getContext('2d');

function resizeCanvas() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
}
resizeCanvas();
window.addEventListener('resize', resizeCanvas);

let stars = [];
const starCount = window.innerWidth < 768 ? 80 : 150;

for (let i = 0; i < starCount; i++) {
    stars.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        radius: Math.random() * 1.2,
        alpha: Math.random(),
        dalpha: 0.003 + Math.random() * 0.008,
        dx: (Math.random() - 0.5) * 0.1,
        dy: (Math.random() - 0.5) * 0.1
    });
}

function drawStars() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    stars.forEach(s => {
        ctx.beginPath();
        ctx.arc(s.x, s.y, s.radius, 0, 2 * Math.PI);
        ctx.fillStyle = `rgba(212, 175, 55, ${s.alpha * 0.6})`; // Gold-tinted stars
        ctx.fill();
        
        s.alpha += s.dalpha;
        if (s.alpha > 1 || s.alpha < 0.2) s.dalpha *= -1;
        
        s.x += s.dx;
        s.y += s.dy;
        
        if (s.x > canvas.width) s.x = 0;
        if (s.x < 0) s.x = canvas.width;
        if (s.y > canvas.height) s.y = 0;
        if (s.y < 0) s.y = canvas.height;
    });
    requestAnimationFrame(drawStars);
}
drawStars();
</script>
</body>
</html>