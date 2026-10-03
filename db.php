<?php
// db.php - Database Connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'lab_mgmt';

// Create connection
$mysqli = new mysqli($host, $username, $password, $database);

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Set charset to utf8
$mysqli->set_charset("utf8mb4");

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>