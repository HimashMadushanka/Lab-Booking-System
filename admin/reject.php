<?php
require '../db.php';
$id = $_GET['id'];
$conn->query("UPDATE bookings SET status='rejected' WHERE id=$id");
header("Location: manage_bookings.php");
