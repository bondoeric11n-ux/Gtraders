<?php
session_start();
include("db_connect.php");

// --- Protect access ---
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: admin_login.php");
    exit();
}

$msg = '';

// --- Handle Create Admin ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'] === 'super_admin' ? 'super_admin' : 'admin';
    $can_edit_balances = isset($_POST['can_edit_balances']) ? 1 : 0;

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO admins (username,email,password,role,can_edit_balances,created_at) VALUES (?,?,?,?,?,NOW())");
    $stmt->bind_param("ssssi", $username, $email, $hashed_password, $role, $can_edit_balances);

    if($stmt->execute()){
        $msg = "Admin created successfully.";
    } else {
        $msg = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// --- Fetch all admins ---
$admins = $conn->query("SELECT id, username, email, role, can_edit_balances, created_at, last_login FROM admins ORDER BY id ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Super Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#121212; color:#FFD700; font-family:Arial,sans-serif; }
.card { background:#1c1c1c; border:1px solid #FFD700; border-radius:10px; padding:20px; margin-bottom:20px; }
table, th, td { border:1px solid #FFD700; color:#FFD700; border-collapse:collapse; }
th, td { padding:10px; text-align:center; }
</style>
</head>
<body>
<div class="container mt-4">
<h2>Super Admin Dashboard</h2>
<?php if($msg) echo '<div class="alert alert-success">'.$msg.'</div>'; ?>

<div class="card mb-3">
<h5>Create New Admin</h5>
<form method="post" class="row g-3">
<input type="hidden" name="create_admin" value="1">
<div class="col-md-3"><input type="text" name="username" class="form-control" placeholder="Username" required></div>
<div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
<div class="col-md-3"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
<div class="col-md-2">
<select name="role" class="form-select">
<option value="admin">Admin</option>
<option value="super_admin">Super Admin</option>
</select>
</div>
<div class="col-md-1 form-check">
<input type="checkbox" name="can_edit_balances" class="form-check-input" id="editBalance">
<label class="form-check-label" for="editBalance" style="color:#FFD700;">Can Edit Balances</label>
</div>
<div class="col-12"><button type="submit" class="btn btn-warning">Create Admin</button></div>
</form>
</div>

<div class="card">
<h5>All Admins</h5>
<table class="table table-sm table-dark">
<thead><tr>
<th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Can Edit Balances</th><th>Created</th><th>Last Login</th><th>Actions</th>
</tr></thead>
<tbody>
<?php while($a = $admins->fetch_assoc()): ?>
<tr>
<td><?=intval($a['id'])?></td>
<td><?=htmlspecialchars($a['username'])?></td>
<td><?=htmlspecialchars($a['email'])?></td>
<td><?=htmlspecialchars($a['role'])?></td>
<td><?= $a['can_edit_balances'] ? 'Yes' : 'No' ?></td>
<td><?=htmlspecialchars($a['created_at'])?></td>
<td><?=htmlspecialchars($a['last_login'])?></td>
<td>
<a href="edit_admin.php?id=<?=intval($a['id'])?>" class="btn btn-sm btn-primary">Edit</a>
<a href="delete_admin.php?id=<?=intval($a['id'])?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this admin?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</body>
</html>
