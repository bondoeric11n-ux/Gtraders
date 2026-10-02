<?php
include 'db_connect.php'; // Ensure this connects to your gtraders database

$projectPath = __DIR__; // gtraders folder path

// --- Helper functions ---
function scanPHPFiles($dir) {
    $files = [];
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $f;
        if (is_dir($path)) {
            $files = array_merge($files, scanPHPFiles($path));
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $files[] = $path;
        }
    }
    return $files;
}

function extractSQLColumns($content) {
    $columns = [];
    preg_match_all("/SELECT\s+(.*?)\s+FROM|INSERT\s+INTO\s+\w+\s*\((.*?)\)/is", $content, $matches);

    foreach (array_merge($matches[1], $matches[2]) as $m) {
        if ($m) {
            $cols = preg_split("/,/", $m);
            foreach ($cols as $c) {
                $c = trim($c);
                // Skip PHP variables
                if (preg_match("/^\$/", $c)) continue;
                // Remove table prefixes
                if (strpos($c, '.') !== false) {
                    $parts = explode('.', $c);
                    $c = trim(end($parts));
                }
                // Remove AS aliases
                $c = preg_replace("/\s+AS\s+.*$/i", "", $c);
                // Remove backticks, quotes, parentheses
                $c = preg_replace("/[()`'\" ]/", "", $c);
                // Only valid SQL column names
                if ($c && preg_match("/^[a-zA-Z_][a-zA-Z0-9_]*$/", $c)) {
                    $columns[] = $c;
                }
            }
        }
    }
    return array_unique($columns);
}

// --- Scan all PHP files ---
$files = scanPHPFiles($projectPath);
$tableColumns = [];
foreach ($files as $file) {
    $content = file_get_contents($file);
    preg_match_all("/FROM\s+([a-zA-Z_0-9]+)/i", $content, $fromMatches);
    preg_match_all("/JOIN\s+([a-zA-Z_0-9]+)/i", $content, $joinMatches);
    $tables = array_merge($fromMatches[1], $joinMatches[1]);
    foreach ($tables as $table) {
        $cols = extractSQLColumns($content);
        if (!isset($tableColumns[$table])) $tableColumns[$table] = [];
        $tableColumns[$table] = array_unique(array_merge($tableColumns[$table], $cols));
    }
}

// --- Ensure tables and columns exist ---
foreach ($tableColumns as $table => $columns) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    if ($res->num_rows === 0) {
        $conn->query("CREATE TABLE `$table` (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB;");
        echo "Created table `$table`.<br>";
    }

    foreach ($columns as $col) {
        $colCheck = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($colCheck->num_rows === 0) {
            // Column type logic
            if (preg_match("/balance|amount|fee|interest|withdrawn/i", $col)) {
                $type = "DECIMAL(15,2) DEFAULT 0";
            } elseif (preg_match("/name|email|username|phone/i", $col)) {
                $type = "TEXT";
            } else {
                $type = "VARCHAR(100)";
            }
            $conn->query("ALTER TABLE `$table` ADD `$col` $type");
            echo "Added column `$col` to `$table`.<br>";
        }
    }
}

// --- Insert default rows if missing ---

// Users table default admin
$adminCheck = $conn->query("SELECT * FROM users WHERE id=1");
if ($adminCheck->num_rows === 0) {
    $conn->query("INSERT INTO users (id, username, name, email, phone, account_balance, invested_balance, pending_balance, created_at)
        VALUES (1,'admin','Admin User','admin@example.com','0000000000',0,0,0,NOW())");
    echo "Inserted default admin user.<br>";
}

// Admin wallet default
$walletCheck = $conn->query("SELECT * FROM admin_wallet WHERE id=1");
if ($walletCheck->num_rows === 0) {
    $conn->query("INSERT INTO admin_wallet (id, fees, withdrawn_fees) VALUES (1,0,0)");
    echo "Inserted default admin wallet row.<br>";
}

// Daily fees default
$dailyFeesCheck = $conn->query("SELECT * FROM daily_fees WHERE id=1");
if ($dailyFeesCheck->num_rows === 0) {
    $conn->query("INSERT INTO daily_fees (id, user_id, fee_amount, created_at) VALUES (1,1,0,NOW())");
    echo "Inserted default daily fees row.<br>";
}

// Empty rows for transactions, investments, withdrawals
$tables = ['transactions','investments','withdrawals'];
foreach ($tables as $t) {
    $check = $conn->query("SELECT * FROM $t LIMIT 1");
    if ($check->num_rows === 0) {
        $conn->query("INSERT INTO $t (id, user_id, amount, created_at) VALUES (1,1,0,NOW())");
        echo "Inserted default row into `$t`.<br>";
    }
}

echo "<h3>Auto-healing completed successfully!</h3>";
echo "<p>All tables, columns, and essential rows are now ensured.</p>";
?>
