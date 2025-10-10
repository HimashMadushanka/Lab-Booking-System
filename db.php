<?php
// db.php
session_start();

$host = 'localhost';
$db   = 'lab_mgmt';
$user = 'root';
$pass = ''; // XAMPP default

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// helper: use prepared statements everywhere
function esc($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
