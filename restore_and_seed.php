<?php
include 'config.php'; // Make sure DB connection is correct

// ---------------------
// 1️⃣ Define all expected columns
// ---------------------
$columns_to_add = [

    'users' => [
        'account_balance'  => "DECIMAL(15,2) DEFAULT 0",
        'pending_balance'  => "DECIMAL(15,2) DEFAULT 0 AFTER account_balance",
        'invested_balance' => "DECIMAL(15,2) DEFAULT 0 AFTER pending_balance",
        'created_at'       => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER invested_balance"
    ],

    'investments' => [
        'user_id'   => "INT NOT NULL",
        'amount'    => "DECIMAL(15,2) NOT NULL",
        'plan'      => "VARCHAR(50)",
        'status'    => "ENUM('active','completed','cancelled') DEFAULT 'active'",
        'fees'      => "DECIMAL(15,2) DEFAULT 0 AFTER amount",
        'created_at'=> "TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status"
    ],

    'withdrawals' => [
        'user_id'     => "INT NOT NULL",
        'amount'      => "DECIMAL(15,2) NOT NULL",
        'fees'        => "DECIMAL(15,2) DEFAULT 0 AFTER amount",
        'status'      => "ENUM('pending','approved','rejected') DEFAULT 'pending'",
        'requested_at'=> "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        'processed_at'=> "TIMESTAMP NULL"
    ],

    'transactions' => [
        'user_id'    => "INT NOT NULL",
        'type'       => "ENUM('deposit','withdrawal','investment','bonus') NOT NULL",
        'amount'     => "DECIMAL(15,2) NOT NULL",
        'description'=> "VARCHAR(255)",
        'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ],

    'referrals' => [
        'referrer_id'=> "INT NOT NULL",
        'referee_id' => "INT NOT NULL",
        'bonus'      => "DECIMAL(15,2) DEFAULT 0",
        'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ],

    'admins' => [
        'username'   => "VARCHAR(50) NOT NULL UNIQUE",
        'email'      => "VARCHAR(100) NOT NULL UNIQUE",
        'password'   => "VARCHAR(255) NOT NULL",
        'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ],

    'plans' => [
        'name'           => "VARCHAR(50) NOT NULL",
        'duration_hours' => "INT NOT NULL",
        'interest_percent'=> "DECIMAL(5,2) NOT NULL",
        'created_at'     => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ]
];

// ---------------------
// 2️⃣ Add missing columns
// ---------------------
foreach ($columns_to_add as $table => $columns) {
    foreach ($columns as $col => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($check->num_rows == 0) {
            $sql = "ALTER TABLE `$table` ADD `$col` $definition";
            if ($conn->query($sql) === TRUE) {
                echo "Added column `$col` to table `$table` successfully.<br>";
            } else {
                echo "Error adding `$col` to `$table`: " . $conn->error . "<br>";
            }
        } else {
            echo "Column `$col` already exists in table `$table`.<br>";
        }
    }
}

// ---------------------
// 3️⃣ Seed default admin
// ---------------------
$adminUsername = "admin";
$adminEmail = "admin@gtraders.com";
$adminPassword = password_hash("admin123", PASSWORD_DEFAULT);

$result = $conn->query("SELECT * FROM admins WHERE username='$adminUsername' LIMIT 1");
if ($result->num_rows == 0) {
    $sql = "INSERT INTO admins (username, email, password) VALUES ('$adminUsername','$adminEmail','$adminPassword')";
    if ($conn->query($sql) === TRUE) {
        echo "Default admin created.<br>";
    } else {
        echo "Error creating admin: " . $conn->error . "<br>";
    }
} else {
    echo "Admin already exists.<br>";
}

// ---------------------
// 4️⃣ Seed sample users
// ---------------------
$sample_users = [
    ['username'=>'user1','email'=>'user1@gtraders.com','password'=>'pass123','account_balance'=>1000],
    ['username'=>'user2','email'=>'user2@gtraders.com','password'=>'pass123','account_balance'=>500]
];

foreach ($sample_users as $u) {
    $check = $conn->query("SELECT * FROM users WHERE username='{$u['username']}' LIMIT 1");
    if ($check->num_rows == 0) {
        $pass = password_hash($u['password'], PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username,email,password,account_balance) 
                VALUES ('{$u['username']}','{$u['email']}','$pass',{$u['account_balance']})";
        if ($conn->query($sql) === TRUE) {
            echo "User {$u['username']} created.<br>";
        } else {
            echo "Error creating user {$u['username']}: " . $conn->error . "<br>";
        }
    } else {
        echo "User {$u['username']} already exists.<br>";
    }
}

$conn->close();
echo "<br>Database fully restored and seeded!";
?>
