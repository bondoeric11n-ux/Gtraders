<?php
include 'config.php'; // Your DB connection

// Define expected columns for each table: table => [column => definition]
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
        'created_at'=> "TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status"
    ],

    'withdrawals' => [
        'user_id'   => "INT NOT NULL",
        'amount'    => "DECIMAL(15,2) NOT NULL",
        'status'    => "ENUM('pending','approved','rejected') DEFAULT 'pending'",
        'requested_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        'processed_at' => "TIMESTAMP NULL"
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

// Iterate over each table and column
foreach ($columns_to_add as $table => $columns) {
    foreach ($columns as $col => $definition) {
        // Check if column exists
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($check->num_rows == 0) {
            // Column missing, add it
            $sql = "ALTER TABLE `$table` ADD `$col` $definition";
            if ($conn->query($sql) === TRUE) {
                echo "Added column `$col` to table `$table` successfully.<br>";
            } else {
                echo "Error adding `$col` to table `$table`: " . $conn->error . "<br>";
            }
        } else {
            echo "Column `$col` already exists in table `$table`.<br>";
        }
    }
}

$conn->close();
echo "<br>All tables are now synchronized with expected columns!";
?>
