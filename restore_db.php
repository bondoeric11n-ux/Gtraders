<?php
// restore_db.php — auto-create missing tables/columns

include 'db_connect.php'; // Make sure this connects to gtraders DB

$queries = [];

// -------- users table --------
$queries[] = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    account_balance DECIMAL(15,2) DEFAULT 0,
    invested_balance DECIMAL(15,2) DEFAULT 0,
    pending_balance DECIMAL(15,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
";

// -------- investments table --------
$queries[] = "
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
";

// -------- withdrawals table --------
$queries[] = "
CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";

// -------- admin_wallet table --------
$queries[] = "
CREATE TABLE IF NOT EXISTS admin_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fees DECIMAL(15,2) DEFAULT 0,
    withdrawn_fees DECIMAL(15,2) DEFAULT 0
) ENGINE=InnoDB;
";

// -------- daily_fees table --------
$queries[] = "
CREATE TABLE IF NOT EXISTS daily_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    fee_amount DECIMAL(15,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";

// -------- admin_wallet_log table --------
$queries[] = "
CREATE TABLE IF NOT EXISTS admin_wallet_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(15,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
";

// -------- transactions table --------
$queries[] = "
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    amount DECIMAL(15,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";

// -------- Execute all queries --------
foreach ($queries as $q) {
    if ($conn->query($q) === TRUE) {
        echo "Query executed successfully.<br>";
    } else {
        echo "Error: " . $conn->error . "<br>";
    }
}

echo "<br>Database restored successfully!";
?>
