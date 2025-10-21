<?php
require '../db.php';
$id = $_GET['id'];
$conn->query("DELETE FROM bookings WHERE id='$id'");
header("Location: manage_bookings.php");
exit;
?>
