<?php
include 'db_connect.php'; // make sure this connects to gtraders DB

function addColumnIfMissing($conn, $table, $column, $definition) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($res->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD `$column` $definition");
        echo "Added column `$column` to `$table`.<br>";
    }
}

// ---------- USERS TABLE ----------
$conn->query("
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    account_balance DECIMAL(15,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
");

// Add missing users columns
addColumnIfMissing($conn, "users", "name", "VARCHAR(100)");
addColumnIfMissing($conn, "users", "invested_balance", "DECIMAL(15,2) DEFAULT 0");
addColumnIfMissing($conn, "users", "pending_balance", "DECIMAL(15,2) DEFAULT 0");

// ---------- INVESTMENTS TABLE ----------
$conn->query("
CREATE TABLE IF NOT EXISTS investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    amount DECIMAL(15,2) DEFAULT 0,
    interest DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    start_date DATETIME,
    end_date DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
");

// ---------- WITHDRAWALS TABLE ----------
$conn->query("
CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
");

// ---------- ADMIN WALLET TABLE ----------
$conn->query("
CREATE TABLE IF NOT EXISTS admin_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY
) ENGINE=InnoDB;
");

addColumnIfMissing($conn, "admin_wallet", "fees", "DECIMAL(15,2) DEFAULT 0");
addColumnIfMissing($conn, "admin_wallet", "withdrawn_fees", "DECIMAL(15,2) DEFAULT 0");

// Ensure default row
$conn->query("
INSERT INTO admin_wallet (id, fees, withdrawn_fees)
SELECT 1, 0, 0
WHERE NOT EXISTS (SELECT * FROM admin_wallet WHERE id=1)
");

// ---------- DAILY FEES TABLE ----------
$conn->query("
CREATE TABLE IF NOT EXISTS daily_fees (
    id INT AUTO_INCREMENT PRIMARY KEY
) ENGINE=InnoDB;
");

addColumnIfMissing($conn, "daily_fees", "user_id", "INT NOT NULL");
addColumnIfMissing($conn, "daily_fees", "fee_amount", "DECIMAL(15,2) DEFAULT 0");
addColumnIfMissing($conn, "daily_fees", "created_at", "DATETIME DEFAULT CURRENT_TIMESTAMP");

// ---------- ADMIN WALLET LOG TABLE ----------
$conn->query("
CREATE TABLE IF NOT EXISTS admin_wallet_log (
    id INT AUTO_INCREMENT PRIMARY KEY
) ENGINE=InnoDB;
");

addColumnIfMissing($conn, "admin_wallet_log", "amount", "DECIMAL(15,2) DEFAULT 0");
addColumnIfMissing($conn, "admin_wallet_log", "created_at", "DATETIME DEFAULT CURRENT_TIMESTAMP");

// ---------- TRANSACTIONS TABLE ----------
$conn->query("
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY
) ENGINE=InnoDB;
");

addColumnIfMissing($conn, "transactions", "user_id", "INT NOT NULL");
addColumnIfMissing($conn, "transactions", "type", "VARCHAR(50) NOT NULL");
addColumnIfMissing($conn, "transactions", "amount", "DECIMAL(15,2) DEFAULT 0");
addColumnIfMissing($conn, "transactions", "created_at", "DATETIME DEFAULT CURRENT_TIMESTAMP");

// ---------- Optional: Default Admin User ----------
$conn->query("
INSERT INTO users (id, username, name, email, phone, account_balance, invested_balance, pending_balance, created_at)
SELECT 1, 'admin', 'Admin User', 'admin@example.com', '0000000000', 0, 0, 0, NOW()
WHERE NOT EXISTS (SELECT * FROM users WHERE id=1)
");

echo "<h3>All tables and missing columns have been ensured!</h3>";
echo "<p>You can now reload <strong>admin_dashboard.php</strong> safely without column errors.</p>";
?>
