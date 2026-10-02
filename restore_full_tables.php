<?php
$servername = "localhost";
$username = "root"; // MySQL username
$password = "";     // MySQL password
$dbname = "gtraders";

$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Create database if missing
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname") or die($conn->error);
$conn->select_db($dbname) or die($conn->error);

// Define all expected tables and columns
$tables = [

    'users' => "
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        account_balance DECIMAL(15,2) DEFAULT 0,
        pending_balance DECIMAL(15,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ",

    'investments' => "
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        plan VARCHAR(50),
        status ENUM('active','completed','cancelled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ",

    'withdrawals' => "
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ",

    'transactions' => "
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('deposit','withdrawal','investment','bonus') NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ",

    'referrals' => "
        id INT AUTO_INCREMENT PRIMARY KEY,
        referrer_id INT NOT NULL,
        referee_id INT NOT NULL,
        bonus DECIMAL(15,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (referee_id) REFERENCES users(id) ON DELETE CASCADE
    ",

    'admins' => "
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ",

    'plans' => "
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        duration_hours INT NOT NULL,
        interest_percent DECIMAL(5,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    "
];

// Create each table if missing
foreach ($tables as $table => $columns) {
    $sql = "CREATE TABLE IF NOT EXISTS `$table` ($columns)";
    $conn->query($sql) or die("Error creating table `$table`: " . $conn->error);
    echo "Table `$table` restored.<br>";
}

echo "<br>All tables have been fully restored with expected columns!";
$conn->close();
?>
