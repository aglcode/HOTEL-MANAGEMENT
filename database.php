<?php
// Database connection
$host = 'localhost';
$db_name = 'hotel_db';
$username = 'root';
$password = '';

$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}