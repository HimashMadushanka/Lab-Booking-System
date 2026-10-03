<?php
session_start();
require '../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$id = intval($_GET['id']);

// Get the booking details first
$booking_query = $mysqli->prepare("
    SELECT computer_id, date, start_time, end_time 
    FROM bookings 
    WHERE id = ?
");
$booking_query->bind_param('i', $id);
$booking_query->execute();
$booking_result = $booking_query->get_result();

if ($booking_result->num_rows === 0) {
    $_SESSION['error'] = "Booking not found.";
    header("Location: manage_bookings.php");
    exit;
}

$booking = $booking_result->fetch_assoc();

// Check if there's already an approved booking for the same computer and time slot
$conflict_check = $mysqli->prepare("
    SELECT id, user_id 
    FROM bookings 
    WHERE computer_id = ? 
    AND date = ? 
    AND status = 'approved'
    AND (
        (start_time < ? AND end_time > ?)
    )
    AND id != ?
");
$conflict_check->bind_param('isssi', 
    $booking['computer_id'], 
    $booking['date'], 
    $booking['end_time'],    // start_time < ?
    $booking['start_time'],  // end_time > ?
    $id
);
$conflict_check->execute();
$conflict_result = $conflict_check->get_result();

if ($conflict_result->num_rows > 0) {
    $conflict = $conflict_result->fetch_assoc();
    
    // Get user info for the conflicting booking
    $user_query = $mysqli->prepare("SELECT name FROM users WHERE id = ?");
    $user_query->bind_param('i', $conflict['user_id']);
    $user_query->execute();
    $user_result = $user_query->get_result();
    $conflict_user = $user_result->fetch_assoc();
    
    $_SESSION['error'] = "Cannot approve booking. Time slot already approved for user: " . $conflict_user['name'];
    header("Location: manage_bookings.php");
    exit;
}

// If no conflict, approve the booking
$update = $mysqli->prepare("UPDATE bookings SET status = 'approved' WHERE id = ?");
$update->bind_param('i', $id);

if ($update->execute()) {
    $_SESSION['success'] = "Booking approved successfully!";
} else {
    $_SESSION['error'] = "Failed to approve booking.";
}

header("Location: manage_bookings.php");
exit;
?>