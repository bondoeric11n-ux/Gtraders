<?php
$servername = "localhost";
$username = "root"; // your MySQL username
$password = "";     // your MySQL password
$dbname = "gtraders";
$projectPath = __DIR__; // current folder

$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Create DB if missing
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname") or die($conn->error);
$conn->select_db($dbname) or die($conn->error);

// Scan PHP files for table names
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectPath));
$tables = [];

foreach ($files as $file) {
    if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($file->getRealPath());
        // Look for patterns like "FROM table_name", "INSERT INTO table_name", "UPDATE table_name"
        if (preg_match_all("/\b(?:FROM|INTO|UPDATE|JOIN)\s+`?([a-zA-Z0-9_]+)`?/i", $content, $matches)) {
            foreach ($matches[1] as $table) {
                $tables[$table] = true;
            }
        }
    }
}

// Function to create a generic table if missing
function createTable($conn, $table) {
    $sql = "CREATE TABLE IF NOT EXISTS `$table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql) or die("Error creating table $table: " . $conn->error);
    echo "Table `$table` ensured.<br>";
}

// Create all detected tables
foreach ($tables as $table => $_) {
    createTable($conn, $table);
}

$conn->close();
echo "<br>All detected tables are now ensured!";
?>
