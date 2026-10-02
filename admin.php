```php
<?php
session_start();
require_once 'config.php';

// ✅ Restrict access to admins only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

$message = "";

// Handle approval
if (isset($_GET['approve'])) {
    $id = (int) $_GET['approve'];

    $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $withdrawal = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($withdrawal && $withdrawal['status'] === 'pending') {
        $conn->begin_transaction();
        try {
            // Deduct from user balance
            $stmt = $conn->prepare("UPDATE users SET account_balance = account_balance - ? WHERE id=?");
            $stmt->bind_param("di", $withdrawal['amount'], $withdrawal['user_id']);
            $stmt->execute();
            $stmt->close();

            // Update withdrawal status
            $stmt = $conn->prepare("UPDATE withdrawals SET status='approved' WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $message = "✅ Withdrawal ID $id approved.";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "❌ Error approving withdrawal: " . $e->getMessage();
        }
    }
}

// Handle rejection
if (isset($_GET['reject'])) {
    $id = (int) $_GET['reject'];
    $stmt = $conn->prepare("UPDATE withdrawals SET status='rejected' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $message = "❌ Withdrawal ID $id rejected.";
}

// Fetch pending withdrawals
$sql = "SELECT w.id, u.username, w.amount, w.status, w.created_at 
        FROM withdrawals w
        JOIN users u ON w.user_id = u.id
        WHERE w.status='pending'
        ORDER BY w.id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <style>
        body {
            background: #0a0a0a;
            color: #f5d142;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        header {
            background: #111;
            padding: 1rem;
            text-align: center;
            font-size: 1.5rem;
            border-bottom: 2px solid #f5d142;
        }
        .container {
            padding: 2rem;
        }
        .message {
            text-align: center;
            margin-bottom: 1rem;
            font-weight: bold;
            color: #e63946;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: #1a1a1a;
            border: 1px solid #f5d142;
        }
        th, td {
            padding: 0.75rem;
            border: 1px solid #f5d142;
            text-align: center;
        }
        th {
            background: #222;
        }
        a {
            color: #f5d142;
            text-decoration: none;
            padding: 5px 10px;
            border: 1px solid #f5d142;
            border-radius: 5px;
        }
        a:hover {
            background: #f5d142;
            color: #111;
        }
    </style>
</head>
<body>
    <header>Admin Withdrawal Panel</header>
    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <table>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Requested At</th>
                <th>Action</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']); ?></td>
                    <td><?= htmlspecialchars($row['username']); ?></td>
                    <td><?= htmlspecialchars($row['amount']); ?></td>
                    <td><?= htmlspecialchars($row['status']); ?></td>
                    <td><?= htmlspecialchars($row['created_at']); ?></td>
                    <td>
                        <a href="?approve=<?= $row['id']; ?>">Approve</a>
                        <a href="?reject=<?= $row['id']; ?>">Reject</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
```
