<?php
// admin_monitor_access.php
session_start();
include("db_connect.php");

// ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Grant immediate super admin access without password
$_SESSION['super_admin_granted'] = time();
header("Location: admin_monitor.php");
exit();
?>

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_pass = $_POST['super_admin_pass'] ?? '';
    $admin_id = intval($_SESSION['admin_id']);

    // 1) Try current admin row first
    $row = $conn->query("SELECT super_admin_pass, role FROM admins WHERE id=$admin_id")->fetch_assoc();
    $hash = $row['super_admin_pass'] ?? '';

    // 2) fallback: if current admin has no hash, try to find a dedicated superadmin row
    if (empty($hash)) {
        $row2 = $conn->query("SELECT id, super_admin_pass FROM admins WHERE role='superadmin' AND super_admin_pass IS NOT NULL LIMIT 1")->fetch_assoc();
        if ($row2 && !empty($row2['super_admin_pass'])) {
            $hash = $row2['super_admin_pass'];
        } else {
            // 3) fallback: pick any admin with a non-null super_admin_pass (not recommended but helpful)
            $row3 = $conn->query("SELECT id, super_admin_pass FROM admins WHERE super_admin_pass IS NOT NULL LIMIT 1")->fetch_assoc();
            if ($row3) $hash = $row3['super_admin_pass'];
        }
    }

    if (empty($hash)) {
        $msg = "No super admin password is configured. Ask your system admin to set one.";
    } else {
        if (password_verify($input_pass, $hash)) {
            // grant short-lived super access
            $_SESSION['super_admin_granted'] = time();

            // optional: log access event (create admin_logs table entry)
            $stmt_log = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, details, ip_address, action_time) VALUES (?, 'super_access', ?, ?, NOW())");
            $detail = "Super admin access granted";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $stmt_log->bind_param("iss", $admin_id, $detail, $ip);
            $stmt_log->execute();
            $stmt_log->close();

            header("Location: admin_monitor.php");
            exit();
        } else {
            $msg = "Incorrect super admin password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Super Admin Access</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0f1720;color:#fff;font-family:Arial,sans-serif; display:flex; justify-content:center; align-items:center; height:100vh;}
.card{padding:20px; border-radius:10px; background:#1f2937; width:420px;}
.btn-gold{background:#D4AF37;color:#000;border:none;}
</style>
</head>
<body>
<div class="card">
<h4>Super Admin Access</h4>
<?php if($msg): ?><div class="alert alert-danger"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<form method="post" autocomplete="off">
<div class="mb-3">
    <label>Password</label>
    <input type="password" name="super_admin_pass" class="form-control" required autofocus>
</div>
<button type="submit" class="btn btn-gold w-100">Enter</button>
</form>
<div class="mt-3 text-muted small">
  <strong>Notes:</strong> If you cannot log in, confirm a super admin password hash is stored in the <code>admins.super_admin_pass</code> field.
</div>
</div>
</body>
</html>
