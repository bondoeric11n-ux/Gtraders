<?php
session_start();
// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="GIBAL LTD - Secure, transparent digital investment platform for Kenyans at home and in the Diaspora. Grow your wealth with vetted Forex copy-trading and SME micro-financing.">
<title>GIBAL LTD | Secure Digital Investments</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ============================================
   PREMIUM FINTECH DESIGN SYSTEM
   ============================================ */
:root {
    --bg-primary: #0a0e1a; --bg-secondary: #0f1420; --bg-tertiary: #151b2b;
    --bg-card: rgba(17, 24, 39, 0.65); --bg-elevated: rgba(30, 41, 59, 0.5);
    --gold: #d4af37; --gold-light: #f4d03f; --gold-dark: #b8941f; 
    --gold-glow: rgba(212, 175, 55, 0.15); --gold-border: rgba(212, 175, 55, 0.25);
    --text-primary: #f1f5f9; --text-secondary: #94a3b8; --text-tertiary: #64748b;
    --success: #10b981; --info: #3b82f6; --danger: #ef4444;
    --border-subtle: rgba(148, 163, 184, 0.08); --border-medium: rgba(148, 163, 184, 0.15);
    --shadow-lg: 0 20px 50px rgba(0, 0, 0, 0.5); --shadow-gold: 0 8px 30px rgba(212, 175, 55, 0.15);
    --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--bg-primary);
    color: var(--text-primary);
    overflow-x: hidden;
    line-height: 1.6;
}

/* Dynamic Trading Chart Background */
#chartCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; opacity: 0.3; pointer-events: none; }

/* Navbar */
.navbar {
    position: fixed; top: 0; width: 100%;
    background: rgba(10, 14, 26, 0.85);
    backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid var(--border-subtle);
    z-index: 1000; padding: 0 24px; height: 70px;
    display: flex; align-items: center; justify-content: space-between;
}
.nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--text-primary); font-weight: 700; font-size: 1.15rem; }
.nav-brand-logo {
    width: 38px; height: 38px;
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    border-radius: 10px; display: flex; align-items: center; justify-content: center;
    color: var(--bg-primary); font-weight: 800; font-size: 1.1rem;
}
.nav-brand-text span { color: var(--gold); }
.nav-links { display: flex; gap: 8px; align-items: center; }
.nav-link {
    padding: 10px 16px; color: var(--text-secondary); text-decoration: none;
    font-weight: 500; font-size: 0.9rem; border-radius: var(--radius-sm); transition: all 0.2s;
}
.nav-link:hover { color: var(--text-primary); background: var(--bg-elevated); }
.btn-nav {
    padding: 10px 20px; background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-primary); font-weight: 700; border-radius: var(--radius-sm); text-decoration: none;
    transition: all 0.2s; border: none; font-size: 0.9rem;
}
.btn-nav:hover { transform: translateY(-2px); box-shadow: var(--shadow-gold); }

/* Main Container */
.container { max-width: 1200px; margin: 0 auto; padding: 0 24px; position: relative; z-index: 10; }

/* Hero Section */
.hero {
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    text-align: center; padding-top: 70px;
}
.hero-content { max-width: 800px; animation: fadeInUp 0.8s ease-out; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

.badge-hero {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 16px; background: var(--gold-glow); border: 1px solid var(--gold-border);
    border-radius: 50px; color: var(--gold-light); font-size: 0.85rem; font-weight: 600;
    margin-bottom: 24px;
}
.hero h1 {
    font-size: 3.5rem; font-weight: 800; line-height: 1.1; margin-bottom: 24px;
    background: linear-gradient(135deg, #fff 0%, var(--gold-light) 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.hero p { font-size: 1.2rem; color: var(--text-secondary); margin-bottom: 40px; max-width: 600px; margin-left: auto; margin-right: auto; }
.hero-buttons { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
.btn-primary {
    padding: 16px 32px; background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-primary); font-weight: 700; border-radius: var(--radius-md); text-decoration: none;
    transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; font-size: 1rem;
}
.btn-primary:hover { transform: translateY(-3px); box-shadow: var(--shadow-gold); }
.btn-secondary {
    padding: 16px 32px; background: var(--bg-elevated); border: 1px solid var(--border-medium);
    color: var(--text-primary); font-weight: 600; border-radius: var(--radius-md); text-decoration: none;
    transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; font-size: 1rem;
}
.btn-secondary:hover { background: var(--bg-card); border-color: var(--gold-border); }

/* Stats Bar */
.stats-bar {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px;
    background: var(--bg-card); backdrop-filter: blur(12px);
    border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);
    padding: 32px; margin-top: -60px; position: relative; z-index: 20;
    box-shadow: var(--shadow-lg);
}
.stat-item { text-align: center; }
.stat-value { font-size: 2rem; font-weight: 800; color: var(--gold); margin-bottom: 4px; }
.stat-label { font-size: 0.85rem; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }

/* Sections */
.section { padding: 100px 0; }
.section-header { text-align: center; margin-bottom: 60px; }
.section-header h2 { font-size: 2.2rem; font-weight: 700; margin-bottom: 16px; }
.section-header h2 span { color: var(--gold); }
.section-header p { color: var(--text-secondary); font-size: 1.1rem; max-width: 600px; margin: 0 auto; }

/* How It Works */
.steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }
.step-card {
    background: var(--bg-card); border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg); padding: 32px; text-align: center;
    transition: all 0.3s ease; position: relative;
}
.step-card:hover { transform: translateY(-5px); border-color: var(--gold-border); box-shadow: var(--shadow-gold); }
.step-number {
    width: 50px; height: 50px; background: var(--gold-glow); color: var(--gold);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; font-weight: 800; margin: 0 auto 20px;
}
.step-card h3 { font-size: 1.2rem; font-weight: 700; margin-bottom: 12px; }
.step-card p { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6; }

/* Investment Plans */
.plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; }
.plan-card {
    background: var(--bg-card); border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg); padding: 32px 24px; text-align: center;
    transition: all 0.3s ease; position: relative; overflow: hidden;
}
.plan-card:hover { transform: translateY(-5px); border-color: var(--gold-border); box-shadow: var(--shadow-gold); }
.plan-card.featured { border-color: var(--gold); background: linear-gradient(180deg, rgba(212, 175, 55, 0.08) 0%, var(--bg-card) 100%); }
.plan-badge {
    position: absolute; top: 16px; right: 16px;
    background: var(--gold); color: var(--bg-primary);
    padding: 4px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
}
.plan-name { font-size: 1.3rem; font-weight: 700; margin-bottom: 16px; color: var(--text-primary); }
.plan-roi { font-size: 3rem; font-weight: 800; color: var(--gold); margin-bottom: 8px; }
.plan-roi span { font-size: 1rem; font-weight: 500; color: var(--text-secondary); }
.plan-duration { color: var(--text-secondary); margin-bottom: 24px; font-size: 0.95rem; }
.plan-range {
    background: var(--bg-elevated); padding: 12px; border-radius: var(--radius-sm);
    margin-bottom: 24px; font-size: 0.9rem; color: var(--text-secondary);
}
.plan-range strong { color: var(--text-primary); }

/* Trust Section */
.trust-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; }
.trust-card {
    display: flex; align-items: flex-start; gap: 16px;
    background: var(--bg-card); border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md); padding: 24px;
}
.trust-icon {
    width: 48px; height: 48px; min-width: 48px;
    background: var(--gold-glow); color: var(--gold);
    border-radius: 12px; display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
}
.trust-card h4 { font-size: 1.05rem; font-weight: 700; margin-bottom: 8px; }
.trust-card p { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5; }

/* CTA Section */
.cta-section {
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(15, 20, 32, 0.9) 100%);
    border: 1px solid var(--gold-border); border-radius: var(--radius-lg);
    padding: 60px 40px; text-align: center; margin-bottom: 60px;
}
.cta-section h2 { font-size: 2rem; font-weight: 700; margin-bottom: 16px; }
.cta-section p { color: var(--text-secondary); margin-bottom: 32px; max-width: 600px; margin-left: auto; margin-right: auto; }

/* Footer */
.footer { background: var(--bg-secondary); border-top: 1px solid var(--border-subtle); padding: 60px 0 30px; }
.footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 40px; }
.footer-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.footer-brand-text { font-weight: 700; font-size: 1.1rem; color: var(--text-primary); }
.footer-brand-text span { color: var(--gold); }
.footer-desc { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6; margin-bottom: 20px; }
.footer-contact { display: flex; flex-direction: column; gap: 10px; color: var(--text-secondary); font-size: 0.9rem; }
.footer-contact a { color: var(--gold); text-decoration: none; }
.footer-heading { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-primary); margin-bottom: 20px; }
.footer-links { list-style: none; display: flex; flex-direction: column; gap: 12px; }
.footer-links a { color: var(--text-secondary); text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
.footer-links a:hover { color: var(--gold); }
.footer-bottom { border-top: 1px solid var(--border-subtle); padding-top: 24px; text-align: center; color: var(--text-tertiary); font-size: 0.85rem; }

/* Floating WhatsApp */
.whatsapp-float {
    position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px;
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-primary); border-radius: 50%; display: flex; align-items: center;
    justify-content: center; font-size: 28px; text-decoration: none;
    box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4); z-index: 99999;
    transition: all 0.3s ease; animation: pulse-gold 2.5s infinite;
}
.whatsapp-float:hover { transform: scale(1.1) translateY(-5px); }
.whatsapp-float::before {
    content: 'Chat with GIBAL Support'; position: absolute; right: 75px;
    background: var(--bg-secondary); color: var(--text-primary); padding: 8px 14px;
    border-radius: 8px; font-size: 0.85rem; font-weight: 600; white-space: nowrap;
    opacity: 0; visibility: hidden; transform: translateX(10px); transition: all 0.3s ease;
    border: 1px solid var(--gold-border); pointer-events: none;
}
.whatsapp-float:hover::before { opacity: 1; visibility: visible; transform: translateX(0); }
@keyframes pulse-gold {
    0% { box-shadow: 0 0 0 0 rgba(212, 175, 55, 0.6); }
    70% { box-shadow: 0 0 0 15px rgba(212, 175, 55, 0); }
    100% { box-shadow: 0 0 0 0 rgba(212, 175, 55, 0); }
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-bar { grid-template-columns: repeat(2, 1fr); }
    .footer-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
    .nav-links { display: none; }
    .hero h1 { font-size: 2.2rem; }
    .hero p { font-size: 1rem; }
    .stats-bar { grid-template-columns: 1fr; margin-top: -30px; }
    .steps-grid { grid-template-columns: 1fr; }
    .footer-grid { grid-template-columns: 1fr; gap: 30px; }
    .whatsapp-float { bottom: 20px; right: 20px; width: 55px; height: 55px; font-size: 24px; }
    .whatsapp-float::before { display: none; }
}
</style>
<link rel="icon" type="image/png" href="favicon.png">
</head>
<body>

<canvas id="chartCanvas"></canvas>

<!-- Navbar -->
<nav class="navbar">
    <a href="index.php" class="nav-brand">
        <div class="nav-brand-logo">G</div>
        <div class="nav-brand-text">GIBAL <span>LTD</span></div>
    </a>
    <div class="nav-links">
        <a href="index.php" class="nav-link" style="color: var(--gold);">Home</a>
        <a href="about.php" class="nav-link">About</a>
        <a href="#plans" class="nav-link">Plans</a>
        <a href="terms.php" class="nav-link">Terms</a>
        <a href="login.php" class="nav-link">Login</a>
        <a href="register.php" class="btn-nav">Get Started</a>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <div class="badge-hero">
                <i class="fas fa-shield-alt"></i> Secure & Transparent Investing
            </div>
            <h1>Grow Your Wealth Securely from Anywhere in the World</h1>
            <p>The premier digital investment platform for Kenyans at home and in the Diaspora. Track your portfolio in real-time, fund via M-Pesa, PayPal, or BTC, and watch your money work for you.</p>
            <div class="hero-buttons">
                <a href="register.php" class="btn-primary"><i class="fas fa-rocket"></i> Create Free Account</a>
                <a href="#plans" class="btn-secondary"><i class="fas fa-chart-line"></i> View Plans</a>
            </div>
        </div>
    </div>
</section>

<!-- Stats Bar -->
<div class="container">
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-value">24/7</div>
            <div class="stat-label">Platform Access</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">4</div>
            <div class="stat-label">Flexible Plans</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">100%</div>
            <div class="stat-label">Transparent Tracking</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">Global</div>
            <div class="stat-label">Payment Methods</div>
        </div>
    </div>
</div>

<!-- How It Works -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>How It <span>Works</span></h2>
            <p>Start building your financial future in three simple, secure steps.</p>
        </div>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">1</div>
                <h3>Create Account</h3>
                <p>Sign up in under 2 minutes. Verify your email and access your secure, personalized investment dashboard.</p>
            </div>
            <div class="step-card">
                <div class="step-number">2</div>
                <h3>Fund & Invest</h3>
                <p>Deposit securely via M-Pesa, Airtel Money, PayPal, or Bitcoin. Choose a plan that fits your financial goals.</p>
            </div>
            <div class="step-card">
                <div class="step-number">3</div>
                <h3>Track & Withdraw</h3>
                <p>Monitor your returns in real-time. Request withdrawals anytime, processed swiftly to your preferred account.</p>
            </div>
        </div>
    </div>
</section>

<!-- Investment Plans -->
<section class="section" id="plans" style="background: var(--bg-secondary);">
    <div class="container">
        <div class="section-header">
            <h2>Choose Your <span>Investment Plan</span></h2>
            <p>Transparent returns generated through vetted Forex copy-trading and verified local SME micro-financing.</p>
        </div>
        <div class="plans-grid">
            <!-- Silver -->
            <div class="plan-card">
                <div class="plan-name">Silver</div>
                <div class="plan-roi">25% <span>ROI</span></div>
                <div class="plan-duration">Duration: 1 Day (24 Hours)</div>
                <div class="plan-range">Ksh <strong>800</strong> - Ksh <strong>3,500</strong></div>
                <a href="register.php" class="btn-primary" style="width: 100%; justify-content: center;">Get Started</a>
            </div>
            <!-- Gold -->
            <div class="plan-card featured">
                <div class="plan-badge">Most Popular</div>
                <div class="plan-name">Gold</div>
                <div class="plan-roi">35% <span>ROI</span></div>
                <div class="plan-duration">Duration: 3 Days (72 Hours)</div>
                <div class="plan-range">Ksh <strong>5,000</strong> - Ksh <strong>8,500</strong></div>
                <a href="register.php" class="btn-primary" style="width: 100%; justify-content: center;">Get Started</a>
            </div>
            <!-- Lotus -->
            <div class="plan-card">
                <div class="plan-name">Lotus</div>
                <div class="plan-roi">45% <span>ROI</span></div>
                <div class="plan-duration">Duration: 7 Days (168 Hours)</div>
                <div class="plan-range">Ksh <strong>10,000</strong> - Ksh <strong>25,000</strong></div>
                <a href="register.php" class="btn-primary" style="width: 100%; justify-content: center;">Get Started</a>
            </div>
            <!-- VIP -->
            <div class="plan-card">
                <div class="plan-name">VIP</div>
                <div class="plan-roi">55% <span>ROI</span></div>
                <div class="plan-duration">Duration: 21 Days (504 Hours)</div>
                <div class="plan-range">Ksh <strong>40,000</strong> - Ksh <strong>100,000</strong></div>
                <a href="register.php" class="btn-primary" style="width: 100%; justify-content: center;">Get Started</a>
            </div>
        </div>
    </div>
</section>

<!-- Trust & Security -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>Why Investors <span>Trust Us</span></h2>
            <p>Built with security, transparency, and your financial growth at the core.</p>
        </div>
        <div class="trust-grid">
            <div class="trust-card">
                <div class="trust-icon"><i class="fas fa-lock"></i></div>
                <div>
                    <h4>Bank-Grade Security</h4>
                    <p>256-bit SSL encryption protects your data and transactions at all times.</p>
                </div>
            </div>
            <div class="trust-card">
                <div class="trust-icon"><i class="fas fa-chart-pie"></i></div>
                <div>
                    <h4>Transparent Model</h4>
                    <p>Returns are generated from audited Forex copy-trading and local SME micro-loans.</p>
                </div>
            </div>
            <div class="trust-card">
                <div class="trust-icon"><i class="fas fa-headset"></i></div>
                <div>
                    <h4>Dedicated Support</h4>
                    <p>Built-in ticketing system and WhatsApp support for fast, personal assistance.</p>
                </div>
            </div>
            <div class="trust-card">
                <div class="trust-icon"><i class="fas fa-globe-africa"></i></div>
                <div>
                    <h4>Diaspora Friendly</h4>
                    <p>Fund your account from anywhere in the world using PayPal, BTC, or M-Pesa.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<div class="container">
    <div class="cta-section">
        <h2>Ready to Take Control of Your Financial Future?</h2>
        <p>Join smart investors growing their wealth securely with GIBAL LTD. Create your free account in under 2 minutes and claim your welcome bonus.</p>
        <a href="register.php" class="btn-primary"><i class="fas fa-user-plus"></i> Create Free Account</a>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="footer-brand">
                    <div class="nav-brand-logo" style="width: 32px; height: 32px; font-size: 0.9rem;">G</div>
                    <div class="footer-brand-text">GIBAL <span>LTD</span></div>
                </div>
                <p class="footer-desc">Empowering global Kenyans to build wealth securely through transparent, technology-driven investment solutions.</p>
                <div class="footer-contact">
                    <span><i class="fas fa-envelope" style="color: var(--gold); margin-right: 8px;"></i> <a href="mailto:gibal.ltd@gmail.com">gibal.ltd@gmail.com</a></span>
                    <span><i class="fab fa-whatsapp" style="color: var(--gold); margin-right: 8px;"></i> <a href="https://wa.me/254703834247">+254 703 834 247</a></span>
                </div>
            </div>
            <div>
                <h4 class="footer-heading">Platform</h4>
                <ul class="footer-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="#plans">Investment Plans</a></li>
                    <li><a href="register.php">Register</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-heading">Legal</h4>
                <ul class="footer-links">
                    <li><a href="terms.php">Terms & Conditions</a></li>
                    <li><a href="privacy.php">Privacy & AML Policy</a></li>
                    <li><a href="code_of_conduct.php">Code of Conduct</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-heading">Support</h4>
                <ul class="footer-links">
                    <li><a href="https://wa.me/254703834247">WhatsApp Support</a></li>
                    <li><a href="mailto:gibal.ltd@gmail.com">Email Us</a></li>
                    <li><a href="login.php">Login to Dashboard</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> GIBAL LTD. All rights reserved. Investing involves risk. Target returns are not guaranteed.</p>
        </div>
    </div>
</footer>

<!-- Floating WhatsApp Button -->
<a href="https://wa.me/254703834247?text=Hello%20GIBAL%20LTD,%20I%20would%20like%20to%20inquire%20about%20investments." 
   class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="Chat with GIBAL Support on WhatsApp">
    <i class="fab fa-whatsapp"></i>
</a>

<script>
// ============================================
// DYNAMIC TRADING CHART BACKGROUND ANIMATION
// ============================================
const canvas = document.getElementById('chartCanvas');
const ctx = canvas.getContext('2d');

function resize() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
resize();
window.addEventListener('resize', resize);

class ChartLine {
    constructor(color, speed, amplitude, yOffset) {
        this.color = color; this.speed = speed; this.amplitude = amplitude; this.yOffset = yOffset;
        this.points = [];
        for (let i = 0; i < canvas.width / 10 + 50; i++) { this.points.push(Math.random() * this.amplitude); }
    }
    update() {
        let last = this.points[this.points.length - 1];
        let change = (Math.random() - 0.5) * this.amplitude * 0.3;
        let next = Math.max(-this.amplitude, Math.min(this.amplitude, last + change));
        this.points.push(next);
        if (this.points.length > canvas.width / 10 + 50) this.points.shift();
    }
    draw() {
        ctx.beginPath(); ctx.strokeStyle = this.color; ctx.lineWidth = 1.5; ctx.lineJoin = 'round';
        for (let i = 0; i < this.points.length; i++) {
            let x = i * 10 - (this.points.length * 10 - canvas.width);
            let y = canvas.height / 2 + this.yOffset + this.points[i];
            if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
        }
        ctx.stroke();
        ctx.lineTo(canvas.width, canvas.height); ctx.lineTo(0, canvas.height); ctx.closePath();
        let gradient = ctx.createLinearGradient(0, canvas.height/2 + this.yOffset - this.amplitude, 0, canvas.height);
        gradient.addColorStop(0, this.color.replace(')', ', 0.05)').replace('rgb', 'rgba'));
        gradient.addColorStop(1, 'transparent');
        ctx.fillStyle = gradient; ctx.fill();
    }
}

const lines = [
    new ChartLine('rgba(212, 175, 55, 0.6)', 2, 80, -100),
    new ChartLine('rgba(6, 182, 212, 0.5)', 1.5, 60, 50),
    new ChartLine('rgba(139, 92, 246, 0.4)', 2.5, 100, 0)
];

function drawGrid() {
    ctx.strokeStyle = 'rgba(148, 163, 184, 0.05)'; ctx.lineWidth = 1;
    for (let y = 0; y < canvas.height; y += 50) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(canvas.width, y); ctx.stroke(); }
    for (let x = 0; x < canvas.width; x += 50) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, canvas.height); ctx.stroke(); }
}

function animate() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    drawGrid();
    lines.forEach(line => { line.update(); line.draw(); });
    requestAnimationFrame(animate);
}
animate();
</script>
</body>
</html>