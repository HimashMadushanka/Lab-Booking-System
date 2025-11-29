<?php
// db.php - Database Connection
$host = 'localhost';
$username = 'root'; // Your MySQL username
$password = ''; // Your MySQL password
$database = 'lab_mgmt'; // Your database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8mb4");

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>