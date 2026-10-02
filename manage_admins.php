<?php
// manage_admins.php
session_start();
include("db_connect.php");

// ---------- protect route ----------
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$me_admin_id = intval($_SESSION['admin_id']);

// fetch current admin role
$stmt = $conn->prepare("SELECT id, username, role FROM admins WHERE id=? LIMIT 1");
$stmt->bind_param("i", $me_admin_id);
$stmt->execute();
$current_admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current_admin) {
    echo "Current admin not found.";
    exit();
}

if ($current_admin['role'] !== 'super_admin') {
    echo "Access denied. Only super admins can manage admins.";
    exit();
}

// ---------- handle POST actions ----------
$msg = "";
$err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // create new admin
    if (isset($_POST['create_admin'])) {
        $username = trim($_POST['username'] ?? "");
        $email = trim($_POST['email'] ?? "");
        $password = $_POST['password'] ?? "";
        $role = ($_POST['role'] ?? 'standard');
        if ($username === "" || $email === "" || $password === "") {
            $err = "Please fill all required fields for creating an admin.";
        } else {
            // check username/email uniqueness
            $check = $conn->prepare("SELECT id FROM admins WHERE username=? OR email=? LIMIT 1");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();
            if ($exists) {
                $err = "An admin with that username or email already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO admins (username, email, password, role, created_at) VALUES (?,?,?,?,NOW())");
                $ins->bind_param("ssss", $username, $email, $hash, $role);
                if ($ins->execute()) {
                    $msg = "Admin account created successfully.";
                } else {
                    $err = "Failed to create admin: " . $ins->error;
                }
                $ins->close();
            }
        }
    }

    // toggle role (promote/demote)
    if (isset($_POST['toggle_role']) && isset($_POST['admin_id'])) {
        $target_id = intval($_POST['admin_id']);
        // prevent changing self role
        if ($target_id === $me_admin_id) {
            $err = "You cannot change your own role.";
        } else {
            // fetch current role
            $q = $conn->prepare("SELECT role FROM admins WHERE id=? LIMIT 1");
            $q->bind_param("i", $target_id);
            $q->execute();
            $r = $q->get_result()->fetch_assoc();
            $q->close();
            if (!$r) {
                $err = "Admin not found.";
            } else {
                $new_role = ($r['role'] === 'super_admin') ? 'standard' : 'super_admin';
                $u = $conn->prepare("UPDATE admins SET role=? WHERE id=?");
                $u->bind_param("si", $new_role, $target_id);
                if ($u->execute()) {
                    $msg = "Admin role updated successfully.";
                } else {
                    $err = "Failed to update role: " . $u->error;
                }
                $u->close();
            }
        }
    }

    // delete admin
    if (isset($_POST['delete_admin']) && isset($_POST['admin_id'])) {
        $target_id = intval($_POST['admin_id']);
        if ($target_id === $me_admin_id) {
            $err = "You cannot delete your own account.";
        } else {
            $d = $conn->prepare("DELETE FROM admins WHERE id=?");
            $d->bind_param("i", $target_id);
            if ($d->execute()) {
                $msg = "Admin deleted successfully.";
            } else {
                $err = "Failed to delete admin: " . $d->error;
            }
            $d->close();
        }
    }
}

// ---------- fetch all admins ----------
$admins = [];
$res = $conn->query("SELECT id, username, email, role, created_at, last_login, last_ip FROM admins ORDER BY id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $admins[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Admins</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0b0b0b;color:#FFD700;font-family:Arial,sans-serif;}
.container{max-width:1100px;margin:60px auto;padding:20px;}
.card{background:#111;border:1px solid #FFD700;border-radius:8px;padding:18px;margin-bottom:18px;}
.table thead th{background:#1b1b1b;color:#FFD700;}
.btn-gold{background:#FFD700;color:#000;border:none;}
.small-muted{color:#bbb;}
</style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Admin Management</h3>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>

    <?php if($msg): ?>
        <div class="alert alert-success"><?=htmlspecialchars($msg)?></div>
    <?php endif;?>
    <?php if($err): ?>
        <div class="alert alert-danger"><?=htmlspecialchars($err)?></div>
    <?php endif; ?>

    <div class="card">
        <h5>Create new admin</h5>
        <form method="post" class="row g-2">
            <div class="col-md-3">
                <input name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="col-md-3">
                <input name="email" type="email" class="form-control" placeholder="Email" required>
            </div>
            <div class="col-md-3">
                <input name="password" type="password" class="form-control" placeholder="Password" required>
            </div>
            <div class="col-md-2">
                <select name="role" class="form-select">
                    <option value="standard">Standard</option>
                    <option value="super_admin">Super Admin</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-gold w-100" name="create_admin" value="1" type="submit">Create</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h5>Active admins</h5>
        <div class="table-responsive">
            <table class="table table-sm table-dark text-white">
                <thead>
                    <tr>
                        <th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th><th>Last login (IP)</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($admins as $a): ?>
                    <tr>
                        <td><?=intval($a['id'])?></td>
                        <td><?=htmlspecialchars($a['username'])?></td>
                        <td><?=htmlspecialchars($a['email'])?></td>
                        <td><?=htmlspecialchars($a['role'])?></td>
                        <td><?=htmlspecialchars($a['created_at'])?></td>
                        <td><?=htmlspecialchars(($a['last_login'] ?? '') . ' ' . ($a['last_ip'] ?? ''))?></td>
                        <td>
                            <?php if(intval($a['id']) !== $me_admin_id): ?>
                                <form method="post" style="display:inline-block;margin-right:6px;">
                                    <input type="hidden" name="admin_id" value="<?=intval($a['id'])?>">
                                    <button class="btn btn-sm btn-outline-light" name="toggle_role" value="1" type="submit">
                                        <?= $a['role'] === 'super_admin' ? 'Demote' : 'Promote' ?>
                                    </button>
                                </form>
                                <form method="post" style="display:inline-block;" onsubmit="return confirm('Delete this admin?');">
                                    <input type="hidden" name="admin_id" value="<?=intval($a['id'])?>">
                                    <button class="btn btn-sm btn-danger" name="delete_admin" value="1" type="submit">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="small-muted">You</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="small text-muted">Note: Only super admins can access this page. Use caution when granting super admin privileges.</div>
</div>
</body>
</html>
