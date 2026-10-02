<?php
session_start();
include("db_connect.php");
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Admin — Wallet</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
    --bg1:#0f1720; 
    --bg2:#111217; 
    --accent:#D4AF37; 
}
body{
    background: linear-gradient(180deg,var(--bg1),var(--bg2)); 
    color:#eef2f6; 
    min-height:100vh; 
    font-family:Inter,Arial,Helvetica,sans-serif;
}
.topbar{
    background:rgba(0,0,0,0.25);
    backdrop-filter:blur(6px);
    padding:12px 20px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    border-bottom:1px solid rgba(212,175,55,0.06);
}
.brand {font-weight:700; color:var(--accent); letter-spacing:0.6px}
.container-xl{padding-top:20px;padding-bottom:40px;}
.stat-card{
    background: rgba(0,0,0,0.12);
    border-radius:10px;
    padding:20px;
    text-align:center;
    transition: all 0.3s ease;
}
.stat-card:hover{
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 8px 20px rgba(212,175,55,0.3);
}
table {background:transparent; color:#fff;}
thead th{background:rgba(255,255,255,0.05); color:var(--accent);}
.scrollable-table{
    max-height:300px;
    overflow-y:auto;
    background: rgba(255,255,255,0.02);
    border-radius:8px;
}
.scrollable-table table{margin:0;}
.scrollable-table tr:hover{background:rgba(212,175,55,0.08);}
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">GTraders — Admin Wallet</div>
  <div>
    <span class="small-muted me-3">Signed in as <strong><?=htmlspecialchars($_SESSION['admin_username'] ?? 'admin')?></strong></span>
    <a href="admin_dashboard.php" class="btn btn-sm btn-outline-light me-2">Dashboard</a>
    <a href="admin_logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
  </div>
</div>

<div class="container-xl">
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="stat-card">
        <div class="small-muted">Total Fees Collected</div>
        <div id="totalFees" style="font-size:1.5rem;font-weight:700;color:#FFD700">Ksh 0.00</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card">
        <div class="small-muted">Total Fees Withdrawn</div>
        <div id="totalWithdrawn" style="font-size:1.5rem;font-weight:700;color:#00ff00">Ksh 0.00</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card">
        <div class="small-muted">Available Balance</div>
        <div id="availableBalance" style="font-size:1.5rem;font-weight:700;color:#D4AF37">Ksh 0.00</div>
      </div>
    </div>
  </div>

  <div class="card-section scrollable-table">
    <h5>Withdrawals Log</h5>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Amount</th>
            <th>Date Withdrawn</th>
          </tr>
        </thead>
        <tbody id="withdrawalsBody">
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
function refreshWallet() {
    $.getJSON('admin_wallet_data.php', function(data){
        $('#totalFees').text('Ksh ' + data.total_fees);
        $('#totalWithdrawn').text('Ksh ' + data.total_withdrawn);
        $('#availableBalance').text('Ksh ' + data.balance);

        let html = '';
        data.withdrawals.forEach(function(w){
            html += '<tr>'+
                    '<td>'+w.id+'</td>'+
                    '<td>'+parseFloat(w.amount).toLocaleString('en-US', {minimumFractionDigits:2})+'</td>'+
                    '<td>'+w.withdrawn_at+'</td>'+
                    '</tr>';
        });
        $('#withdrawalsBody').html(html);
    });
}

// Refresh every 5 seconds
setInterval(refreshWallet, 5000);
refreshWallet();
</script>

</body>
</html>
