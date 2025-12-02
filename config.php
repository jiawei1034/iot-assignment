<?php
$servername = "localhost"; 
$db_username = "root";
$db_password = "";
$db_name = "intruderSystem";
$port = 3306;

$conn = new mysqli($servername, $db_username, $db_password, $db_name, $port);

// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }
// echo "Database connected successfully!";
// ?>
