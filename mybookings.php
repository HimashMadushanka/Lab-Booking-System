<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch all bookings for this user
$sql = "
SELECT b.id, c.code AS computer_code, l.name AS lab_name, b.date, b.start_time, b.end_time, b.status
FROM bookings b
JOIN computers c ON b.computer_id = c.id
JOIN labs l ON c.lab_id = l.id
WHERE b.user_id = ?
ORDER BY b.date DESC, b.start_time DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Bookings | Lab Management</title>
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
table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}
th, td {
  border: 1px solid #ccc;
  padding: 10px;
  text-align: center;
}
th {
  background: #2980b9;
  color: white;
}
tr:nth-child(even) {
  background: #f9f9f9;
}
.nav {
  margin-top: 20px;
  text-align: center;
}
.nav a {
  background: #2980b9;
  color: white;
  padding: 10px 20px;
  text-decoration: none;
  border-radius: 5px;
}
.nav a:hover {
  background: #1f6391;
}
</style>
</head>
<body>

<div class="header">
  <h1>My Bookings</h1>
  <p>View all your past and upcoming lab bookings</p>
</div>

<div class="container">
<table>
  <tr>
    <th>#</th>
    <th>Lab</th>
    <th>Computer</th>
    <th>Date</th>
    <th>Start Time</th>
    <th>End Time</th>
    <th>Status</th>
  </tr>
  <?php if ($result->num_rows > 0): ?>
    <?php $i = 1; while($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['lab_name']) ?></td>
        <td><?= htmlspecialchars($row['computer_code']) ?></td>
        <td><?= htmlspecialchars($row['date']) ?></td>
        <td><?= htmlspecialchars($row['start_time']) ?></td>
        <td><?= htmlspecialchars($row['end_time']) ?></td>
        <td><?= htmlspecialchars($row['status']) ?></td>
      </tr>
    <?php endwhile; ?>
  <?php else: ?>
    <tr><td colspan="7">No bookings found.</td></tr>
  <?php endif; ?>
</table>

<div class="nav">
  <a href="index.php">⬅ Back to Dashboard</a>
</div>
</div>
</body>
</html>
