<?php
// index.php - User Dashboard
require 'db.php';

// Check if logged in and is user
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user'){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// --- Fetch statistics ---
$total_computers = $conn->query("SELECT COUNT(*) AS cnt FROM computers WHERE status='available'")->fetch_assoc()['cnt'];
$total_bookings = $conn->query("SELECT COUNT(*) AS cnt FROM bookings WHERE user_id=$user_id")->fetch_assoc()['cnt'];
$approved_bookings = $conn->query("SELECT COUNT(*) AS cnt FROM bookings WHERE user_id=$user_id AND status='approved'")->fetch_assoc()['cnt'];

// --- Fetch upcoming bookings ---
$stmt = $conn->prepare("
    SELECT b.*, c.code 
    FROM bookings b 
    JOIN computers c ON c.id=b.computer_id 
    WHERE b.user_id=? AND b.date >= CURDATE() 
    ORDER BY b.date, b.start_time LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Dashboard | Lab Management</title>
<style>
body {
  font-family: Arial, sans-serif;
  background: #f4f4f9;
  margin: 0;
  padding: 0;
}
.header {
  background: #34495e;
  color: white;
  padding: 15px;
  text-align: center;
}
.container {
  width: 80%;
  margin: 30px auto;
}
.cards {
  display: flex;
  justify-content: space-around;
  gap: 20px;
  flex-wrap: wrap;
}
.card {
  background: white;
  padding: 20px;
  width: 30%;
  border-radius: 10px;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
  text-align: center;
}
.card h2 { margin: 0; color: #2c3e50; }
.card p { font-size: 14px; color: #666; }

h2.section-title {
  margin-top: 40px;
  color: #2c3e50;
}
table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}
table th, table td {
  padding: 10px;
  border: 1px solid #ccc;
  text-align: center;
}
.nav-links {
  margin-top: 20px;
  text-align: center;
}
.nav-links a {
  text-decoration: none;
  background: #2980b9;
  color: white;
  padding: 10px 20px;
  border-radius: 5px;
  margin: 0 5px;
  display: inline-block;
}
.nav-links a:hover {
  background: #1f6391;
}
.logout {
  background: #c0392b !important;
}
</style>
</head>
<body>

<div class="header">
  <h1>Welcome, <?= htmlspecialchars($user_name) ?> 👋</h1>
  <p>Computer Lab Management System</p>
</div>

<div class="container">

  <!-- Dashboard Summary Cards -->
  <div class="cards">
    <div class="card">
      <h2><?= $total_computers ?></h2>
      <p>Available Time</p>
    </div>
    <div class="card">
      <h2><?= $total_bookings ?></h2>
      <p>Total Bookings</p>
    </div>
    <div class="card">
      <h2><?= $approved_bookings ?></h2>
      <p>Approved Bookings</p>
    </div>
  </div>

  <!-- Upcoming Bookings Table -->
  <h2 class="section-title">📅 Upcoming Bookings</h2>
  <table>
    <tr>
      <th>Computer_lab</th>
      <th>Date</th>
      <th>Start</th>
      <th>End</th>
      <th>Status</th>
    </tr>
    <?php if($upcoming->num_rows > 0): ?>
      <?php while($b = $upcoming->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($b['code']) ?></td>
        <td><?= htmlspecialchars($b['date']) ?></td>
        <td><?= htmlspecialchars($b['start_time']) ?></td>
        <td><?= htmlspecialchars($b['end_time']) ?></td>
        <td><?= htmlspecialchars($b['status']) ?></td>
      </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="5">No upcoming bookings.</td></tr>
    <?php endif; ?>
  </table>

  <!-- Navigation Links -->
  <div class="nav-links">
    <a href="create.php">Book a Lab</a>
    <a href="mybookings.php">My Bookings</a>
    <a href="logout.php" class="logout">Logout</a>
  </div>

</div>
</body>
</html>
