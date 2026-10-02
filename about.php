<?php
session_start();
include_once("db_connect.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About Us | GIBAL LTD</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg-primary: #0a0e1a; --bg-secondary: #0f1420; --bg-tertiary: #151b2b;
    --bg-card: rgba(17, 24, 39, 0.65); --bg-elevated: rgba(30, 41, 59, 0.5);
    --gold: #d4af37; --gold-light: #f4d03f; --gold-dark: #b8941f; --gold-glow: rgba(212, 175, 55, 0.15); --gold-border: rgba(212, 175, 55, 0.2);
    --text-primary: #f1f5f9; --text-secondary: #94a3b8; --text-tertiary: #64748b;
    --success: #10b981; --info: #3b82f6;
    --border-subtle: rgba(148, 163, 184, 0.08); --border-medium: rgba(148, 163, 184, 0.15);
    --shadow-lg: 0 20px 50px rgba(0, 0, 0, 0.4); --shadow-gold: 0 8px 30px rgba(212, 175, 55, 0.15);
    --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; background-image: radial-gradient(ellipse at top left, rgba(212, 175, 55, 0.04) 0%, transparent 50%), radial-gradient(ellipse at bottom right, rgba(59, 130, 246, 0.03) 0%, transparent 50%); }

.navbar { position: fixed; top: 0; width: 100%; background: rgba(10, 14, 26, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-subtle); z-index: 1000; padding: 0 24px; height: 70px; display: flex; align-items: center; justify-content: space-between; }
.nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--text-primary); font-weight: 700; font-size: 1.15rem; }
.nav-brand-logo { width: 38px; height: 38px; background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--bg-primary); font-weight: 800; }
.nav-brand-text span { color: var(--gold); }
.nav-links { display: flex; gap: 8px; }
.nav-link { padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-weight: 500; font-size: 0.9rem; border-radius: var(--radius-sm); transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
.nav-link:hover { color: var(--text-primary); background: var(--bg-elevated); }
.nav-link.active { color: var(--gold); background: var(--gold-glow); }

.container { max-width: 1000px; margin: 0 auto; padding: 100px 24px 60px; }
.hero { text-align: center; margin-bottom: 60px; }
.hero h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 16px; background: linear-gradient(135deg, #fff 0%, var(--gold) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.hero p { font-size: 1.1rem; color: var(--text-secondary); max-width: 700px; margin: 0 auto; line-height: 1.6; }

.section { margin-bottom: 50px; }
.section h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; color: var(--text-primary); }
.section h2 i { color: var(--gold); }
.section p, .section li { color: var(--text-secondary); line-height: 1.7; margin-bottom: 15px; font-size: 1rem; }
.section ul { padding-left: 20px; }

.feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 30px; }
.feature-card { background: var(--bg-card); backdrop-filter: blur(12px); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; transition: all 0.3s ease; }
.feature-card:hover { transform: translateY(-4px); border-color: var(--gold-border); box-shadow: var(--shadow-gold); }
.feature-icon { width: 50px; height: 50px; border-radius: 12px; background: var(--gold-glow); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 16px; }
.feature-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 10px; color: var(--text-primary); }
.feature-card p { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5; margin: 0; }

.cta-box { background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(15, 20, 32, 0.8) 100%); border: 1px solid var(--gold-border); border-radius: var(--radius-lg); padding: 40px; text-align: center; margin-top: 60px; }
.cta-box h2 { justify-content: center; margin-bottom: 15px; }
.cta-box p { max-width: 600px; margin: 0 auto 25px; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 14px 32px; border-radius: var(--radius-sm); font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.2s; border: none; text-decoration: none; }
.btn-primary { background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%); color: var(--bg-primary); }
.btn-primary:hover { transform: translateY(-2px); box-shadow: var(--shadow-gold); }

.footer { background: var(--bg-secondary); border-top: 1px solid var(--border-subtle); padding: 40px 24px 20px; margin-top: 60px; text-align: center; color: var(--text-tertiary); font-size: 0.85rem; }
.footer a { color: var(--gold); text-decoration: none; }

@media (max-width: 768px) {
    .nav-links { display: none; }
    .container { padding: 84px 16px 40px; }
    .hero h1 { font-size: 1.8rem; }
    .cta-box { padding: 24px; }
}
</style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand">
        <div class="nav-brand-logo">G</div>
        <div class="nav-brand-text">GIBAL <span>LTD</span></div>
    </a>
    <div class="nav-links">
        <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
        <a href="about.php" class="nav-link active"><i class="fas fa-info-circle"></i> About</a>
        <a href="dashboard.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> Login</a>
    </div>
</nav>

<div class="container">
    
    <div class="hero">
        <h1>Empowering Global Kenyans to Build Wealth Securely</h1>
        <p>GIBAL LTD is a premier digital investment platform designed for Kenyans at home and in the Diaspora. We combine cutting-edge financial technology with transparent, real-time tracking to help you grow your wealth back home.</p>
    </div>

    <div class="section">
        <h2><i class="fas fa-cogs"></i> How We Generate Your Returns</h2>
        <p>Transparency is our core value. We don't just hold your money; we put it to work through a diversified, carefully managed portfolio. Your investments are strategically allocated across three primary, income-generating channels:</p>
        
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3>Vetted Copy Trading & Forex</h3>
                <p>We partner with highly vetted, professional forex traders and copy-trading experts with proven, audited track records. By mirroring their strategic trades, we capture market opportunities while strictly managing risk through stop-loss protocols.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-hand-holding-usd"></i></div>
                <h3>SME Micro-Financing</h3>
                <p>We provide small, short-term business loans to verified, legitimate local enterprises and small businesses in Kenya. This generates steady, asset-backed interest returns while actively empowering the local economy.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-users"></i></div>
                <h3>Strategic Institutional Investing</h3>
                <p>A portion of our capital pool is allocated to low-risk, diversified investment vehicles and joint ventures, ensuring stability and consistent yield generation to balance our higher-growth trading activities.</p>
            </div>
        </div>
    </div>

    <div class="section">
        <h2><i class="fas fa-shield-alt"></i> Our Commitment to Security & Transparency</h2>
        <p>Millions of Kenyans living abroad work incredibly hard to build a future back home, but informal investments often lack transparency. GIBAL LTD eliminates this friction.</p>
        <ul>
            <li><strong>Real-Time Tracking:</strong> Our premium dashboard provides live updates on your portfolio, transaction history, and projected returns.</li>
            <li><strong>Strict Due Diligence:</strong> Every trader we copy and every business we finance undergoes rigorous background checks and performance auditing.</li>
            <li><strong>Dedicated Support:</strong> Our built-in ticketing system ensures your queries are routed directly to our admin team for fast, personal resolution.</li>
        </ul>
    </div>

    <div class="cta-box">
        <h2>Ready to Take Control of Your Financial Future?</h2>
        <p>Join smart investors growing their wealth securely with GIBAL LTD. Create your free account in under 2 minutes and start building your portfolio today.</p>
        <a href="register.php" class="btn btn-primary"><i class="fas fa-rocket"></i> Create Free Account</a>
    </div>

</div>

<footer class="footer">
    <p>&copy; <?php echo date('Y'); ?> GIBAL LTD. All rights reserved. | <a href="terms.php">Terms & Conditions</a> | <a href="privacy.php">Privacy Policy</a></p>
    <p style="margin-top: 10px;">Contact: <a href="mailto:gibal.ltd@gmail.com">gibal.ltd@gmail.com</a> | +254 703 834 247</p>
</footer>

</body>
</html>