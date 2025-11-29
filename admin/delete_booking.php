<?php
session_start();
require '../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$id = intval($_GET['id']);

$delete = $conn->prepare("DELETE FROM bookings WHERE id = ?");
$delete->bind_param('i', $id);

if ($delete->execute()) {
    $_SESSION['success'] = "Booking deleted successfully!";
} else {
    $_SESSION['error'] = "Failed to delete booking.";
}

header("Location: manage_bookings.php");
exit;
?>