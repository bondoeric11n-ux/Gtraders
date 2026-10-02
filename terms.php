<?php
session_start();
include_once("db_connect.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terms & Conditions | GIBAL LTD</title>
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

.navbar { position: fixed; top: 0; width: 100%; background: rgba(10, 14, 26, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-subtle); z-index: 1000; padding: 0 24px; height: 70px; display: flex; align-items: center; justify-content: space-between; }
.nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--text-primary); font-weight: 700; font-size: 1.15rem; }
.nav-brand-logo { width: 38px; height: 38px; background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--bg-primary); font-weight: 800; }
.nav-brand-text span { color: var(--gold); }
.nav-links { display: flex; gap: 8px; }
.nav-link { padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-weight: 500; font-size: 0.9rem; border-radius: var(--radius-sm); transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
.nav-link:hover { color: var(--text-primary); background: var(--bg-elevated); }
.nav-link.active { color: var(--gold); background: var(--gold-glow); }

.container { max-width: 900px; margin: 0 auto; padding: 100px 24px 60px; }
.page-header { text-align: center; margin-bottom: 50px; }
.page-header h1 { font-size: 2.2rem; font-weight: 800; margin-bottom: 12px; color: var(--text-primary); }
.page-header p { color: var(--text-tertiary); font-size: 0.9rem; }

.legal-card { background: var(--bg-card); backdrop-filter: blur(12px); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 40px; margin-bottom: 30px; }
.legal-card h2 { font-size: 1.3rem; font-weight: 700; color: var(--gold); margin-bottom: 15px; margin-top: 30px; display: flex; align-items: center; gap: 10px; }
.legal-card h2:first-child { margin-top: 0; }
.legal-card h2 i { font-size: 1rem; }
.legal-card p, .legal-card li { color: var(--text-secondary); line-height: 1.7; margin-bottom: 15px; font-size: 0.95rem; }
.legal-card ul { padding-left: 20px; margin-bottom: 20px; }
.legal-card strong { color: var(--text-primary); font-weight: 600; }

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
        <h1>Terms & Conditions</h1>
        <p>Last Updated: <?php echo date('F d, Y'); ?></p>
    </div>

    <div class="legal-card">
        <h2><i class="fas fa-file-contract"></i> 1. Acceptance of Terms</h2>
        <p>By accessing and using the GIBAL LTD platform ("the Platform"), you agree to be bound by these Terms and Conditions. If you do not agree with any part of these terms, you must not use our services. We reserve the right to update these terms at any time.</p>

        <h2><i class="fas fa-user-check"></i> 2. Eligibility & Account Registration</h2>
        <ul>
            <li>You must be at least <strong>18 years old</strong> and possess the legal capacity to enter into binding contracts.</li>
            <li>You agree to provide accurate, current, and complete information during registration.</li>
            <li>You are solely responsible for maintaining the confidentiality of your account credentials. Any activity under your account is your responsibility.</li>
        </ul>

        <h2><i class="fas fa-wallet"></i> 3. Deposits, Investments, and Withdrawals</h2>
        <ul>
            <li><strong>Deposits:</strong> We accept deposits via M-Pesa, Airtel Money, PayPal, and Bitcoin. Funds are credited once the transaction is verified on our end.</li>
            <li><strong>Investments:</strong> When you allocate funds to an investment plan, those funds are locked for the duration of the plan's term. Early withdrawal may not be permitted or may incur penalties.</li>
            <li><strong>Withdrawals:</strong> Withdrawal requests are processed manually by our admin team during operational hours (Monday – Friday, 9:00 AM – 5:00 PM EAT). We reserve the right to request KYC (Know Your Customer) documentation before processing large withdrawals to comply with Anti-Money Laundering (AML) regulations.</li>
        </ul>

        <h2><i class="fas fa-exclamation-triangle"></i> 4. Investment Strategies & Risk Disclosure</h2>
        <p><strong>Please read this section carefully:</strong></p>
        <p>GIBAL LTD generates returns by allocating user funds across a diversified portfolio, which includes:</p>
        <ul>
            <li><strong>Forex & Copy Trading:</strong> We partner with vetted professional traders to execute trades in the foreign exchange market. Forex trading carries a high level of risk and may not be suitable for all investors. Leverage can work against you as well as for you.</li>
            <li><strong>SME Micro-Lending:</strong> We provide small business loans to verified local enterprises. While these are asset-backed or guarantor-backed, there is an inherent risk of borrower default.</li>
        </ul>
        <p><strong>Disclaimer:</strong> All investments carry inherent risks. The "Projected" or "Target" returns displayed on our investment plans are estimates based on historical performance and current market conditions, and are <strong>not guaranteed</strong>. Past performance is not indicative of future results. You acknowledge that you are investing voluntarily and understand these risks. GIBAL LTD shall not be held liable for losses incurred due to market fluctuations.</p>

        <h2><i class="fas fa-ban"></i> 5. Prohibited Activities</h2>
        <p>The following activities are strictly prohibited and will result in immediate account termination:</p>
        <ul>
            <li>Using the platform for money laundering, terrorist financing, or any illegal financial activities.</li>
            <li>Creating multiple accounts to exploit referral bonuses or promotional offers.</li>
            <li>Attempting to hack, exploit, or disrupt the Platform's servers or security protocols.</li>
        </ul>

        <h2><i class="fas fa-balance-scale"></i> 6. Limitation of Liability</h2>
        <p>To the maximum extent permitted by applicable law, GIBAL LTD, its directors, employees, and partners shall not be liable for any indirect, incidental, special, or consequential damages arising out of your use of the Platform.</p>

        <h2><i class="fas fa-gavel"></i> 7. Governing Law & Dispute Resolution</h2>
        <p>These Terms shall be governed by the laws of the <strong>Republic of Kenya</strong>. Any disputes shall first be attempted to be resolved amicably through our internal support ticket system. If unresolved, the dispute shall be subject to the exclusive jurisdiction of the courts of Kenya.</p>

        <h2><i class="fas fa-envelope"></i> 8. Contact Information</h2>
        <p>If you have any questions regarding these Terms, please contact our support team:</p>
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