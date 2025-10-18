<?php
session_start();
require '../db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Stats
$total_users = $conn->query("SELECT COUNT(*) AS cnt FROM users")->fetch_assoc()['cnt'];
$total_bookings = $conn->query("SELECT COUNT(*) AS cnt FROM bookings")->fetch_assoc()['cnt'];
$pending = $conn->query("SELECT COUNT(*) AS cnt FROM bookings WHERE status='pending'")->fetch_assoc()['cnt'];
$computers = $conn->query("SELECT COUNT(*) AS cnt FROM computers")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
  <style>
    body { font-family: Arial; background: #ecf0f1; margin: 0; }
    .header { background: #2c3e50; color: white; padding: 15px; text-align: center; }
    .cards { display: flex; justify-content: space-around; margin: 30px; flex-wrap: wrap; }
    .card {
      background: white; padding: 20px; width: 22%; text-align: center;
      border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .card h2 { margin: 0; color: #2c3e50; }
    .links { text-align: center; margin-top: 20px; }
    .links a {
      text-decoration: none; background: #2980b9; color: white;
      padding: 10px 20px; border-radius: 5px; margin: 0 5px;
    }
  </style>
</head>
<body>
<div class="header">
  <h1>Admin Dashboard</h1>
  <a href="logout.php" style="color:white; float:right; margin-top:-30px;">Logout</a>
</div>

<div class="cards">
  <div class="card"><h2><?= $total_users ?></h2><p>Users</p></div>
  <div class="card"><h2><?= $computers ?></h2><p>Computers</p></div>
  <div class="card"><h2><?= $total_bookings ?></h2><p>Total Bookings</p></div>
  <div class="card"><h2><?= $pending ?></h2><p>Pending Approvals</p></div>
</div>

<div class="links">
  <a href="manage_bookings.php">Manage Bookings</a>
  <a href="manage_users.php">Manage Users</a>
  <a href="manage_computers.php">Manage Computers</a>
</div>

</body>
</html>
