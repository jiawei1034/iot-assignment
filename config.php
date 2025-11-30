<?php
$servername = "13.213.14.97";
$db_username = "admin";
$db_password = "P@ssword";
$db_name = "intruderSystem";
$port = 3306;

$conn = new mysqli($servername, $db_username, $db_password, $db_name, $port);

// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }
// echo "Database connected successfully!";
?>
