<?php
session_start();
include_once("db_connect.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacy & AML Policy | GIBAL LTD</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg-primary: #0a0e1a; --bg-secondary: #0f1420; --bg-tertiary: #151b2b;
    --bg-card: rgba(17, 24, 39, 0.65); --bg-elevated: rgba(30, 41, 59, 0.5);
    --gold: #d4af37; --gold-light: #f4d03f; --gold-dark: #b8941f; --gold-glow: rgba(212, 175, 55, 0.15); --gold-border: rgba(212, 175, 55, 0.2);
    --text-primary: #f1f5f9; --text-secondary: #94a3b8; --text-tertiary: #64748b;
    --border-subtle: rgba(148, 163, 184, 0.08); --border-medium: rgba(148, 163, 184, 0.15);
    --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; background-image: radial-gradient(ellipse at top left, rgba(212, 175, 55, 0.04) 0%, transparent 50%); }

/* Navbar */
.navbar { position: fixed; top: 0; width: 100%; background: rgba(10, 14, 26, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-subtle); z-index: 1000; padding: 0 24px; height: 70px; display: flex; align-items: center; justify-content: space-between; }
.nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--text-primary); font-weight: 700; font-size: 1.15rem; }
.nav-brand-logo { width: 38px; height: 38px; background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--bg-primary); font-weight: 800; }
.nav-brand-text span { color: var(--gold); }
.nav-links { display: flex; gap: 8px; }
.nav-link { padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-weight: 500; font-size: 0.9rem; border-radius: var(--radius-sm); transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
.nav-link:hover { color: var(--text-primary); background: var(--bg-elevated); }
.nav-link.active { color: var(--gold); background: var(--gold-glow); }

.container { max-width: 900px; margin: 0 auto; padding: 100px 24px 60px; }

/* Header */
.page-header { text-align: center; margin-bottom: 50px; }
.page-header h1 { font-size: 2.2rem; font-weight: 800; margin-bottom: 12px; color: var(--text-primary); }
.page-header p { color: var(--text-tertiary); font-size: 0.9rem; }

/* Legal Content */
.legal-card { background: var(--bg-card); backdrop-filter: blur(12px); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 40px; margin-bottom: 30px; }
.legal-card h2 { font-size: 1.3rem; font-weight: 700; color: var(--gold); margin-bottom: 15px; margin-top: 35px; display: flex; align-items: center; gap: 10px; }
.legal-card h2:first-child { margin-top: 0; }
.legal-card h2 i { font-size: 1rem; }
.legal-card p, .legal-card li { color: var(--text-secondary); line-height: 1.7; margin-bottom: 15px; font-size: 0.95rem; }
.legal-card ul { padding-left: 20px; margin-bottom: 20px; }
.legal-card strong { color: var(--text-primary); font-weight: 600; }

/* Footer */
.footer { background: var(--bg-secondary); border-top: 1px solid var(--border-subtle); padding: 40px 24px 20px; margin-top: 60px; text-align: center; color: var(--text-tertiary); font-size: 0.85rem; }
.footer a { color: var(--gold); text-decoration: none; }

@media (max-width: 768px) {
    .nav-links { display: none; }
    .container { padding: 84px 16px 40px; }
    .legal-card { padding: 24px; }
    .page-header h1 { font-size: 1.6rem; }
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
        <a href="about.php" class="nav-link"><i class="fas fa-info-circle"></i> About</a>
        <a href="dashboard.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> Login</a>
    </div>
</nav>

<div class="container">
    
    <div class="page-header">
        <h1>Privacy & AML Policy</h1>
        <p>Last Updated: <?php echo date('F d, Y'); ?></p>
    </div>

    <div class="legal-card">
        <h2><i class="fas fa-user-shield"></i> 1. Introduction</h2>
        <p>At GIBAL LTD ("we", "our", or "us"), protecting your privacy and ensuring the security of your financial data is our top priority. This Privacy & Anti-Money Laundering (AML) Policy outlines how we collect, use, and safeguard your personal information when you use our platform, in strict compliance with the Kenya Data Protection Act (2019) and international financial standards.</p>

        <h2><i class="fas fa-database"></i> 2. Information We Collect</h2>
        <p>To provide our investment and withdrawal services, we collect the following types of information:</p>
        <ul>
            <li><strong>Personal Identification Data:</strong> Name, username, email address, phone number, and physical address (required for KYC verification).</li>
            <li><strong>Financial Data:</strong> M-Pesa/Airtel phone numbers, PayPal email addresses, Bitcoin wallet addresses, deposit/withdrawal history, and investment portfolio details.</li>
            <li><strong>Technical Data:</strong> IP address, browser type, device information, and login timestamps to ensure account security.</li>
        </ul>

        <h2><i class="fas fa-cogs"></i> 3. How We Use Your Information</h2>
        <p>We use your data strictly for the following purposes:</p>
        <ul>
            <li>To create, manage, and secure your user account.</li>
            <li>To process your deposits, execute your investments, and facilitate withdrawal requests.</li>
            <li>To communicate with you regarding your account, support tickets, and platform updates.</li>
            <li>To comply with legal obligations, including fraud prevention and AML regulations.</li>
        </ul>

        <h2><i class="fas fa-balance-scale"></i> 4. Anti-Money Laundering (AML) & KYC Policy</h2>
        <p><strong>GIBAL LTD maintains a strict zero-tolerance policy toward money laundering, terrorist financing, and financial fraud.</strong> To protect our users and comply with global financial regulations, we enforce the following measures:</p>
        <ul>
            <li><strong>Know Your Customer (KYC):</strong> We reserve the right to request official government-issued identification (National ID, Passport, or Driver's License) and proof of address before processing large withdrawals or if suspicious activity is detected on your account.</li>
            <li><strong>Transaction Monitoring:</strong> Our system automatically monitors deposits and withdrawals for unusual patterns, rapid turnover of funds, or transactions originating from high-risk jurisdictions.</li>
            <li><strong>Source of Funds:</strong> For significant deposits, we may request documentation proving the legitimate source of your funds.</li>
            <li><strong>Reporting:</strong> If we suspect that an account is being used for illegal activities, we are legally obligated to freeze the account and report the activity to the relevant authorities, such as the Financial Reporting Centre (FRC) in Kenya.</li>
        </ul>

        <h2><i class="fas fa-share-alt"></i> 5. Data Sharing & Disclosure</h2>
        <p>We do not sell, trade, or rent your personal information to third parties. We only share data in the following specific circumstances:</p>
        <ul>
            <li><strong>Payment Processors:</strong> To facilitate your transactions, necessary data is shared with secure third-party processors (e.g., Safaricom for M-Pesa, PayPal, or Bitcoin blockchain networks).</li>
            <li><strong>Legal Requirements:</strong> If required by law, court order, or government regulation, we will disclose your information to the appropriate legal authorities.</li>
        </ul>

        <h2><i class="fas fa-lock"></i> 6. Data Security</h2>
        <p>We employ industry-standard security measures to protect your data from unauthorized access, alteration, or destruction. This includes 256-bit SSL encryption for all data transmitted between your browser and our servers, secure database architecture, and restricted internal access to user data.</p>

        <h2><i class="fas fa-user-check"></i> 7. Your Rights</h2>
        <p>Under applicable data protection laws, you have the right to:</p>
        <ul>
            <li>Request access to the personal data we hold about you.</li>
            <li>Request corrections to any inaccurate or incomplete data.</li>
            <li>Request the deletion of your account and personal data, subject to our legal obligation to retain financial transaction records for a minimum period as required by AML laws.</li>
        </ul>

        <h2><i class="fas fa-envelope"></i> 8. Contact Us</h2>
        <p>If you have any questions, concerns, or requests regarding this Privacy & AML Policy, or if you wish to exercise your data rights, please contact our support team:</p>
        <p>
            <strong>Email:</strong> <a href="mailto:gibal.ltd@gmail.com" style="color: var(--gold); text-decoration: none;">gibal.ltd@gmail.com</a><br>
            <strong>Phone/WhatsApp:</strong> +254 703 834 247
        </p>
    </div>

</div>

<footer class="footer">
    <p>&copy; <?php echo date('Y'); ?> GIBAL LTD. All rights reserved. | <a href="terms.php">Terms & Conditions</a> | <a href="privacy.php">Privacy Policy</a></p>
    <p style="margin-top: 10px;">Contact: <a href="mailto:gibal.ltd@gmail.com">gibal.ltd@gmail.com</a> | +254 703 834 247</p>
</footer>

</body>
</html>