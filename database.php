<?php
// Database connection
// $host = 'localhost';
// $db_name = 'hotel_db';
// $username = 'root';
// $password = '';

// Railway
$host = getenv('MYSQLHOST');
$db_name = getenv('MYSQLDATABASE');
$username = getenv('MYSQLUSER');
$password = getenv('MYSQLPASSWORD');

echo "HOST: [$host] DB: [$db_name] USER: [$username] PASS_SET: " . (!empty($password) ? 'yes' : 'no');
die();

$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
