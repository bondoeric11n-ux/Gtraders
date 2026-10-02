<?php
$host="localhost";
$user="root";
$password="";
$dbname="gtraders";

$conn = new mysqli($host,$user,$password,$dbname);
if($conn->connect_error){
    die("Connection failed: ".$conn->connect_error);
} else {
    echo "Database connected successfully!";
}
?>
