<?php
// db.php

// Start session only if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
