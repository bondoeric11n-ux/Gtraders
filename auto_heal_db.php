<?php
include 'db_connect.php'; // make sure this connects to your gtraders DB

$projectPath = __DIR__; // gtraders folder path

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
                // Remove table aliases (i.plan_name -> plan_name)
                if (strpos($c, '.') !== false) {
                    $parts = explode('.', $c);
                    $c = trim(end($parts));
                }
                // Remove AS aliases
                $c = preg_replace("/\s+AS\s+.*$/i", "", $c);
                // Remove backticks, quotes, parentheses
                $c = preg_replace("/[()`'\" ]/","",$c);
                if ($c && !in_array($c, ['*'])) $columns[] = $c;
            }
        }
    }
    return array_unique($columns);
}

// --- Scan all PHP files ---
$files = scanPHPFiles($projectPath);
$tableColumns = []; // table => columns
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
            $type = preg_match("/balance|amount|fee|interest|withdrawn/i", $col) ? "DECIMAL(15,2) DEFAULT 0" : "VARCHAR(255)";
            $conn->query("ALTER TABLE `$table` ADD `$col` $type");
            echo "Added column `$col` to `$table`.<br>";
        }
    }
}

// --- Default rows for critical tables ---

// Users table default admin
$adminCheck = $conn->query("SELECT * FROM users WHERE id=1");
if ($adminCheck->num_rows === 0) {
    $conn->query("INSERT INTO users (id, username, name, email, phone, account_balance, invested_balance, pending_balance, created_at)
        VALUES (1,'admin','Admin User','admin@example.com','0000000000',0,0,0,NOW())");
    echo "Inserted default admin user.<br>";
}

// Admin wallet
$walletCheck = $conn->query("SELECT * FROM admin_wallet WHERE id=1");
if ($walletCheck->num_rows === 0) {
    $conn->query("INSERT INTO admin_wallet (id, fees, withdrawn_fees) VALUES (1,0,0)");
    echo "Inserted default admin wallet row.<br>";
}

echo "<h3>Auto-healing completed successfully!</h3>";
echo "<p>All tables, columns, and essential rows are now ensured.</p>";
?>
