<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch all bookings for this user
$sql = "
SELECT 
    b.id, 
    c.code AS computer_code, 
    l.name AS lab_name, 
    b.date, 
    b.start_time, 
    b.end_time, 
    b.status
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
<title>My Bookings | Lab Management System</title>
<style>
body {
  font-family: Arial, sans-serif;
  background-color: #f4f6f9;
  margin: 0;
  padding: 0;
}
.header {
  background-color: #2c3e50;
  color: white;
  padding: 20px;
  text-align: center;
}
.container {
  width: 90%;
  max-width: 1000px;
  margin: 30px auto;
  background: white;
  padding: 20px;
  border-radius: 10px;
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
h2 {
  text-align: center;
  color: #2c3e50;
}
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}
th, td {
  border: 1px solid #ddd;
  padding: 10px;
  text-align: center;
}
th {
  background-color: #3498db;
  color: white;
}
tr:nth-child(even) {
  background-color: #f9f9f9;
}
tr:hover {
  background-color: #f1f1f1;
}
.status {
  font-weight: bold;
  padding: 5px 10px;
  border-radius: 5px;
}
.status.pending { color: #f39c12; }
.status.approved { color: #27ae60; }
.status.rejected { color: #e74c3c; }
.nav {
  text-align: center;
  margin-top: 30px;
}
.nav a {
  text-decoration: none;
  background-color: #3498db;
  color: white;
  padding: 10px 20px;
  border-radius: 5px;
}
.nav a:hover {
  background-color: #217dbb;
}
</style>
</head>
<body>

<div class="header">
  <h1>My Bookings</h1>
  <p>View all your lab bookings here</p>
</div>

<div class="container">
  <h2>Booking History</h2>
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
          <td>
            <span class="status <?= strtolower($row['status']) ?>">
              <?= ucfirst($row['status']) ?>
            </span>
          </td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr>
        <td colspan="7">No bookings found.</td>
      </tr>
    <?php endif; ?>
  </table>

  <div class="nav">
    <a href="index.php">⬅ Back to Dashboard</a>
  </div>
</div>

</body>
</html>
