<?php
include 'db_connect.php'; // connect to gtraders DB

function ensureTableExists($conn, $table, $columns) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    if ($res->num_rows === 0) {
        $cols = implode(", ", $columns);
        $conn->query("CREATE TABLE `$table` ($cols) ENGINE=InnoDB;");
        echo "Created table `$table`.<br>";
    }
}

function addColumnIfMissing($conn, $table, $column, $definition) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($res->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD `$column` $definition");
        echo "Added column `$column` to `$table`.<br>";
    }
}

function ensureDefaultRow($conn, $table, $data, $conditionColumn, $conditionValue) {
    $res = $conn->query("SELECT * FROM `$table` WHERE `$conditionColumn`='$conditionValue'");
    if ($res->num_rows === 0) {
        $columns = implode(", ", array_keys($data));
        $values = implode(", ", array_map(function($v) use ($conn){
            return is_numeric($v) ? $v : "'" . $conn->real_escape_string($v) . "'";
        }, array_values($data)));
        $conn->query("INSERT INTO `$table` ($columns) VALUES ($values)");
        echo "Inserted default row in `$table`.<br>";
    }
}

// ---------- USERS TABLE ----------
ensureTableExists($conn, "users", [
    "id INT AUTO_INCREMENT PRIMARY KEY",
    "username VARCHAR(50) NOT NULL UNIQUE",
    "email VARCHAR(100) UNIQUE",
    "phone VARCHAR(20)",
    "account_balance DECIMAL(15,2) DEFAULT 0",
    "created_at DATETIME DEFAULT CURRENT_TIMESTAMP"
]);

// Ensure users table has expected columns
$usersColumns = [
    "name" => "VARCHAR(100)",
    "invested_balance" => "DECIMAL(15,2) DEFAULT 0",
    "pending_balance" => "DECIMAL(15,2) DEFAULT 0"
];

foreach ($usersColumns as $col => $def) {
    addColumnIfMissing($conn, "users", $col, $def);
}

// Ensure default admin user exists
ensureDefaultRow($conn, "users", [
    "id" => 1,
    "username" => "admin",
    "name" => "Admin User",
    "email" => "admin@example.com",
    "phone" => "0000000000",
    "account_balance" => 0,
    "invested_balance" => 0,
    "pending_balance" => 0,
    "created_at" => date("Y-m-d H:i:s")
], "id", 1);

// ---------- ADMIN WALLET ----------
ensureTableExists($conn, "admin_wallet", ["id INT AUTO_INCREMENT PRIMARY KEY"]);
$adminColumns = [
    "fees" => "DECIMAL(15,2) DEFAULT 0",
    "withdrawn_fees" => "DECIMAL(15,2) DEFAULT 0"
];
foreach ($adminColumns as $col => $def) {
    addColumnIfMissing($conn, "admin_wallet", $col, $def);
}
ensureDefaultRow($conn, "admin_wallet", ["id"=>1, "fees"=>0, "withdrawn_fees"=>0], "id", 1);

// ---------- DAILY FEES ----------
ensureTableExists($conn, "daily_fees", ["id INT AUTO_INCREMENT PRIMARY KEY"]);
$dailyColumns = [
    "user_id" => "INT NOT NULL",
    "fee_amount" => "DECIMAL(15,2) DEFAULT 0",
    "created_at" => "DATETIME DEFAULT CURRENT_TIMESTAMP"
];
foreach ($dailyColumns as $col => $def) {
    addColumnIfMissing($conn, "daily_fees", $col, $def);
}

// ---------- ADMIN WALLET LOG ----------
ensureTableExists($conn, "admin_wallet_log", ["id INT AUTO_INCREMENT PRIMARY KEY"]);
$logColumns = [
    "amount" => "DECIMAL(15,2) DEFAULT 0",
    "created_at" => "DATETIME DEFAULT CURRENT_TIMESTAMP"
];
foreach ($logColumns as $col => $def) {
    addColumnIfMissing($conn, "admin_wallet_log", $col, $def);
}

// ---------- TRANSACTIONS ----------
ensureTableExists($conn, "transactions", ["id INT AUTO_INCREMENT PRIMARY KEY"]);
$txColumns = [
    "user_id" => "INT NOT NULL",
    "type" => "VARCHAR(50) NOT NULL",
    "amount" => "DECIMAL(15,2) DEFAULT 0",
    "created_at" => "DATETIME DEFAULT CURRENT_TIMESTAMP"
];
foreach ($txColumns as $col => $def) {
    addColumnIfMissing($conn, "transactions", $col, $def);
}

// ---------- INVESTMENTS ----------
ensureTableExists($conn, "investments", ["id INT AUTO_INCREMENT PRIMARY KEY"]);
$invColumns = [
    "user_id" => "INT NOT NULL",
    "plan_id" => "INT NOT NULL",
    "amount" => "DECIMAL(15,2) DEFAULT 0",
    "interest" => "DECIMAL(10,2) DEFAULT 0",
    "status" => "VARCHAR(20) DEFAULT 'pending'",
    "start_date" => "DATETIME",
    "end_date" => "DATETIME"
];
foreach ($invColumns as $col => $def) {
    addColumnIfMissing($conn, "investments", $col, $def);
}

// ---------- WITHDRAWALS ----------
ensureTableExists($conn, "withdrawals", ["id INT AUTO_INCREMENT PRIMARY KEY"]);
$wdColumns = [
    "user_id" => "INT NOT NULL",
    "amount" => "DECIMAL(15,2) DEFAULT 0",
    "status" => "VARCHAR(20) DEFAULT 'pending'",
    "created_at" => "DATETIME DEFAULT CURRENT_TIMESTAMP"
];
foreach ($wdColumns as $col => $def) {
    addColumnIfMissing($conn, "withdrawals", $col, $def);
}

echo "<h3>Database self-healing run complete.</h3>";
echo "<p>All required tables, columns, and default rows are ensured.</p>";
?>
