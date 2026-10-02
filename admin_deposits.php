<?php
session_start();
include("db_connect.php");
include_once 'log_admin_action.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}
$admin_id = $_SESSION['admin_id'];
$btc_to_kes_rate = 124;

// Approve/Reject deposit via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit_id'], $_POST['action'])) {
    $dep_id = intval($_POST['deposit_id']);
    $action = $_POST['action'];
    if ($dep_id > 0 && in_array($action, ['approve','reject'])) {
        $stmt = $conn->prepare("SELECT user_id, amount, status FROM btc_deposits WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $dep_id);
        $stmt->execute();
        $dep_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($dep_row && $dep_row['status'] === 'pending') {
            $user_id = $dep_row['user_id'];
            $amount = $dep_row['amount'];
            try {
                $conn->begin_transaction();
                if ($action === 'approve') {
                    $stmt = $conn->prepare("UPDATE btc_deposits SET status='credited', processed_at=NOW() WHERE id=?");
                    $stmt->bind_param("i",$dep_id);
                    $stmt->execute();
                    $stmt->close();

                    $amount_kes = $amount * $btc_to_kes_rate;
                    $stmt = $conn->prepare("UPDATE users SET account_balance = account_balance + ? WHERE id=?");
                    $stmt->bind_param("di", $amount_kes, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    log_admin_action($conn, $admin_id, 'approve', "Approved deposit ID $dep_id", $user_id);
                    $status_updated = 'credited';
                } elseif ($action === 'reject') {
                    $stmt = $conn->prepare("UPDATE btc_deposits SET status='rejected', processed_at=NOW() WHERE id=?");
                    $stmt->bind_param("i",$dep_id);
                    $stmt->execute();
                    $stmt->close();

                    log_admin_action($conn, $admin_id, 'reject', "Rejected deposit ID $dep_id", $user_id);
                    $status_updated = 'rejected';
                }
                $conn->commit();

                // Return updated row info
                $stmt = $conn->prepare("
                    SELECT d.id,d.transaction_code,d.invoice_id,d.user_id,u.username,u.email,u.phone,u.account_balance,
                           d.amount,d.status,d.created_at
                    FROM btc_deposits d
                    JOIN users u ON u.id = d.user_id
                    WHERE d.id=?
                    LIMIT 1
                ");
                $stmt->bind_param("i",$dep_id);
                $stmt->execute();
                $updated_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $response = ['success'=>true, 'row'=>$updated_row];
            } catch(Exception $e) {
                $conn->rollback();
                $response = ['success'=>false,'error'=>$e->getMessage()];
            }
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode($response);
                exit();
            }
        }
    }
}

// Fetch deposits + pending count for AJAX
if(isset($_GET['ajax'])) {
    $status_filter = $_GET['status'] ?? 'all';
    $where = [];
    if ($status_filter !== 'all') $where[] = "LOWER(d.status)='".strtolower($status_filter)."'";
    $where_sql = $where ? "WHERE ".implode(" AND ",$where) : "";
    $dep_q = $conn->query("
        SELECT d.id,d.transaction_code,d.invoice_id,d.user_id,u.username,u.email,u.phone,u.account_balance,
               d.amount,d.status,d.created_at
        FROM btc_deposits d
        JOIN users u ON u.id = d.user_id
        $where_sql
        ORDER BY 
            CASE WHEN d.status='pending' THEN 1
                 WHEN d.status='credited' THEN 2
                 WHEN d.status='rejected' THEN 3
                 ELSE 4 END ASC,
            d.created_at DESC
    ");
    $pending_count = $conn->query("SELECT COUNT(*) AS cnt FROM btc_deposits WHERE LOWER(status)='pending'")->fetch_assoc()['cnt'] ?? 0;
    $rows = '';
    while($d = $dep_q->fetch_assoc()){
        $status_lower = strtolower($d['status']);
        $status_class = 'status-' . $status_lower;
        $amount_kes_display = number_format($d['amount'] * $btc_to_kes_rate,2);
        $transaction_display = htmlspecialchars($d['transaction_code'] ?: $d['invoice_id'] ?: '—');
        $rows .= '<tr id="deposit-row-'.$d['id'].'" class="'.$status_class.'">';
        $rows .= '<td><a href="edit_user.php?id='.$d['user_id'].'" style="color:#D4AF37;">'.htmlspecialchars($d['username']).'</a></td>';
        $rows .= '<td>'.htmlspecialchars($d['email']).'</td>';
        $rows .= '<td>'.htmlspecialchars($d['phone']).'</td>';
        $rows .= '<td>'.number_format($d['account_balance'],2).'</td>';
        $rows .= '<td>'.$amount_kes_display.' KES</td>';
        $rows .= '<td>'.ucfirst($d['status']).'</td>';
        $rows .= '<td>'.$d['created_at'].'</td>';
        $rows .= '<td title="Click to view invoice">'.$transaction_display.'</td>';
        $rows .= '<td>';
        if($status_lower==='pending'){
            $rows .= '<button class="btn btn-gold btn-sm" onclick="updateStatus('.$d['id'].',\'approve\')">Approve</button>';
            $rows .= '<button class="btn btn-danger btn-sm" onclick="updateStatus('.$d['id'].',\'reject\')">Reject</button>';
        } else {
            $rows .= '-';
        }
        $rows .= '</td></tr>';
    }
    echo json_encode(['rows'=>$rows,'pending_count'=>$pending_count]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Deposits Requested — GTraders</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --bg1:#0f1720; --bg2:#111217; --accent:#D4AF37; }
body { background: linear-gradient(180deg,var(--bg1),var(--bg2)); color:#eef2f6; font-family:Inter,Arial,sans-serif; min-height:100vh; overflow-x:hidden; position:relative;}
#stars { position:fixed; width:100%; height:100%; z-index:0; top:0; left:0; background:#000; }
.star { position:absolute; background:#fff; border-radius:50%; opacity:0.8; }
.topbar{background:rgba(0,0,0,0.25);backdrop-filter:blur(6px);padding:12px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(212,175,55,0.06);z-index:2;position:relative;}
.brand{font-weight:700;color:var(--accent);}
.container-xl{padding:20px;position:relative;z-index:2;}
.card-section{background: rgba(0,0,0,0.12);padding:20px;border-radius:10px;position:relative;z-index:2;}
.scrollable-table{overflow-y:auto; overflow-x:auto;}
table{color:#fff;width:100%;border-collapse:collapse; min-width:900px;}
thead th{background:rgba(255,255,255,0.05); color:var(--accent);text-align:center;}
td{text-align:center;}
tr:hover{background:rgba(212,175,55,0.08);}
.btn-gold{background:var(--accent);color:#0b0b0b;border:none;padding:5px 10px;margin-right:4px;}
.btn-gold:hover{opacity:0.9;}
.status-credited { background:#d4edda !important; color:#155724; }
.status-rejected { background:#f8d7da !important; color:#721c24; }
.status-pending  { background:#fff3cd !important; color:#856404; }
#searchInput{margin-bottom:10px;padding:6px 10px;border-radius:6px;width:100%;border:none;}
.highlight{background-color:yellow;color:black;}
.badge {font-size:0.85rem;margin-left:4px;}
</style>
</head>
<body>
<div id="stars"></div>
<div class="topbar">
  <div class="brand">GTraders — Deposits Requested</div>
  <div>
    <a href="admin_dashboard.php" class="btn btn-sm btn-gold">Back to Dashboard</a>
    <a href="admin_logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
  </div>
</div>
<div class="container-xl">
  <div class="card-section">
    <h4>Deposits</h4>
    <div class="mb-3">
      <button class="btn btn-secondary btn-sm" onclick="filterStatus('all')">All</button>
      <button class="btn btn-warning btn-sm btn-pending-badge" onclick="filterStatus('pending')">
        Pending <span class="badge bg-warning">0</span>
      </button>
      <button class="btn btn-success btn-sm" onclick="filterStatus('credited')">Credited</button>
      <button class="btn btn-danger btn-sm" onclick="filterStatus('rejected')">Rejected</button>
    </div>
    <input type="text" id="searchInput" placeholder="Search..." />
    <div class="scrollable-table">
      <table class="table table-hover" id="depositTable">
        <thead>
          <tr>
            <th>User</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Balance</th>
            <th>Amount (KES)</th>
            <th>Status</th>
            <th>Requested At</th>
            <th>Transaction Code</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
<script>
// Stars
const starsContainer = document.getElementById('stars');
for(let i=0;i<100;i++){
    let star = document.createElement('div');
    star.className='star';
    star.style.width=star.style.height=(Math.random()*2+1)+'px';
    star.style.top=Math.random()*100+'%';
    star.style.left=Math.random()*100+'%';
    star.style.opacity=Math.random();
    starsContainer.appendChild(star);
}

// AJAX fetch deposits
let statusFilter = 'all';
let lastPendingCount = 0;

function fetchDeposits() {
    fetch(`admin_deposits.php?ajax=1&status=${statusFilter}&t=${Date.now()}`)
        .then(res => res.json())
        .then(data => {
            const tbody = document.querySelector('#depositTable tbody');
            tbody.innerHTML = data.rows;

            const scrollable = document.querySelector('.scrollable-table');
            const rowCount = tbody.querySelectorAll('tr').length;
            if(rowCount > 20){
                scrollable.style.maxHeight = '500px';
                scrollable.style.overflowY = 'auto';
            } else {
                scrollable.style.maxHeight = 'none';
                scrollable.style.overflowY = 'visible';
            }

            const btn = document.querySelector('.btn-pending-badge .badge');
            if(btn && lastPendingCount !== data.pending_count){
                btn.textContent = data.pending_count;
                lastPendingCount = data.pending_count;
            }
        });
}

function updateStatus(id, action){
    let formData = new FormData();
    formData.append('deposit_id', id);
    formData.append('action', action);
    formData.append('ajax', 1);

    fetch('admin_deposits.php', {method:'POST', body:formData})
        .then(r => r.json())
        .then(data => {
            if(data.success && data.row){
                let r = data.row;
                let row = document.getElementById('deposit-row-' + r.id);
                if(row){
                    row.className = 'status-' + r.status.toLowerCase();
                    row.cells[3].textContent = parseFloat(r.account_balance).toFixed(2);
                    row.cells[5].textContent = r.status.charAt(0).toUpperCase() + r.status.slice(1);
                    row.cells[7].textContent = r.transaction_code || r.invoice_id || '—';
                    row.cells[8].innerHTML = (r.status==='pending') ?
                        `<button class="btn btn-gold btn-sm" onclick="updateStatus(${r.id},'approve')">Approve</button>
                         <button class="btn btn-danger btn-sm" onclick="updateStatus(${r.id},'reject')">Reject</button>` : '-';
                }
                fetchDeposits();
            }
        });
}

function filterStatus(status){
    statusFilter = status;
    fetchDeposits();
}

// Initial load & auto-refresh
fetchDeposits();
setInterval(fetchDeposits, 5000);

// Search filter
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#depositTable tbody tr');
    rows.forEach(row => {
        let cells = Array.from(row.querySelectorAll('td'));
        let rowText = cells.map(c => c.textContent.toLowerCase()).join(' ');
        if(rowText.includes(filter)){
            row.style.display = '';
            cells.forEach(c => {
                let original = c.textContent;
                if(filter){
                    let regex = new RegExp(`(${filter})`,'gi');
                    c.innerHTML = original.replace(regex,'<span class="highlight">$1</span>');
                } else {
                    c.textContent = original;
                }
            });
        } else { row.style.display = 'none'; }
    });
});
</script>
</body>
</html>