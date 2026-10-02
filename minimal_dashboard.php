<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
session_start();

$conn = new mysqli("localhost","root","","gtraders");
if($conn->connect_error) die("DB fail: ".$conn->connect_error);

$result = $conn->query("SELECT * FROM investments WHERE user_id=1");
if(!$result) die("Query failed: ".$conn->error);

while($row = $result->fetch_assoc()){
    echo "<pre>"; print_r($row); echo "</pre>";
}
?>
