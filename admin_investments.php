<?php
session_start();
include 'config.php';

// Optional: restrict only admins
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Fetch all investments
$query = "
    SELECT i.id, i.user_id, u.username, i.plan_name, i.amount, i.net_amount, 
           i.start_date, i.maturity_date
    FROM investments i
    JOIN users u ON i.user_id = u.id
    ORDER BY i.start_date DESC
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Manage Investments</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background: linear-gradient(135deg, #111 0%, #222 100%);
        color: #FFD700;
        margin: 0;
        padding: 20px;
    }
    .container {
        max-width: 1200px;
        margin: auto;
        background: rgba(0,0,0,0.85);
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 0 15px rgba(255,215,0,0.3);
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    table th, table td {
        padding: 10px;
        border: 1px solid #444;
        text-align: center;
    }
    table th {
        background: #333;
        color: #FFD700;
    }
    .countdown {
        font-weight: bold;
        color: #00ff00;
    }
    .matured {
        font-weight: bold;
        color: #ff4444;
    }
</style>
<script>
// Countdown timers
function startCountdown(id, maturityDate) {
    function update() {
        let now = new Date().getTime();
        let distance = new Date(maturityDate).getTime() - now;

        let el = document.getElementById("countdown-" + id);

        if (distance <= 0) {
            el.innerHTML = "<span class='matured'>Matured</span>";
            clearInterval(timer);
        } else {
            let days = Math.floor(distance / (1000 * 60 * 60 * 24));
            let hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            let minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            let seconds = Math.floor((distance % (1000 * 60)) / 1000);
            el.innerHTML = days + "d " + hours + "h " + minutes + "m " + seconds + "s";
        }
    }
    update();
    let timer = setInterval(update, 1000);
}
</script>
</head>
<body>
<div class="container">
    <h2>Admin - Active Investments</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Plan</th>
            <th>Amount</th>
            <th>Net Amount</th>
            <th>Start Date</th>
            <th>Maturity Date</th>
            <th>Time Left</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['plan_name']) ?></td>
                <td>Ksh <?= number_format($row['amount'], 2) ?></td>
                <td>Ksh <?= number_format($row['net_amount'], 2) ?></td>
                <td><?= htmlspecialchars($row['start_date']) ?></td>
                <td><?= htmlspecialchars($row['maturity_date']) ?></td>
                <td id="countdown-<?= $row['id'] ?>" class="countdown"></td>
            </tr>
            <script>
                startCountdown(<?= $row['id'] ?>, "<?= $row['maturity_date'] ?>");
            </script>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
