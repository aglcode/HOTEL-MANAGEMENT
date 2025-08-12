<?php
// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "hotel_db";
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the POST data exists
if (isset($_POST['roomNumber']) && isset($_POST['status'])) {
    $roomNumber = $_POST['roomNumber'];
    $status = $_POST['status'];

    // Sanitize inputs
    $roomNumber = $conn->real_escape_string($roomNumber);
    $status = $conn->real_escape_string($status);

    // Prepare the SQL query to update room status
    $sql = "UPDATE rooms SET status = '$status' WHERE room_number = '$roomNumber'";

    // Execute the query
    if ($conn->query($sql) === TRUE) {
        echo "Room status updated successfully.";
    } else {
        echo "Error updating room status: " . $conn->error;
    }
} else {
    echo "Invalid request. Room number and status are required.";
}

// Close the database connection
$conn->close();
?>
