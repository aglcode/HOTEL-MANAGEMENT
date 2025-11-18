<?php
// Database connection
$host = 'localhost';
$db_name = 'u594297719_hotel_db';
$username = 'u594297719_hotel_db_root';
$password = '7s#Y0xn#4Hv';

$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}