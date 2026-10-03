<?php
session_start();
require '../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$id = intval($_GET['id']);

// Use $mysqli instead of $conn
$update = $mysqli->prepare("UPDATE bookings SET status = 'rejected' WHERE id = ?");
$update->bind_param('i', $id);

if ($update->execute()) {
    $_SESSION['success'] = "Booking rejected successfully!";
} else {
    $_SESSION['error'] = "Failed to reject booking.";
}

header("Location: manage_bookings.php");
exit;
?>