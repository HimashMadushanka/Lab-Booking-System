<?php
session_start();
require '../db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$result = $conn->query("
    SELECT b.*, u.name AS user_name, c.code AS computer_code
    FROM bookings b
    JOIN users u ON b.user_id=u.id
    JOIN computers c ON b.computer_id=c.id
    ORDER BY b.date DESC
");
?>
<!DOCTYPE html>
<html>
<head>
<title>Manage Bookings</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background:#b8eaf8ff;
    min-height: 100vh;
    padding: 40px 20px;
}

h2 {
    text-align: center;
    color: black;
    margin-bottom: 30px;
    font-size: 2.5em;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
    text-decoration: underline;
}

table { 
    border-collapse: collapse;
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    background: white;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    border-radius: 10px;
    overflow: hidden;
}

th, td { 
    border: none;
    padding: 15px 10px;
    text-align: center;
}

th { 
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.9em;
}

tr {
    transition: all 0.3s ease;
}

tr:nth-child(even) {
    background-color: #f8f9fa;
}

tr:hover {
    background-color: #e3f2fd;
    transform: scale(1.01);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

td {
    color: #333;
    font-size: 0.95em;
}

a.button { 
    display: inline-block;
    padding: 8px 16px;
    color: white;
    text-decoration: none;
    border-radius: 25px;
    margin: 0 3px;
    font-size: 0.85em;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.approve { 
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
}

.approve:hover {
    background: linear-gradient(135deg, #229954 0%, #27ae60 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
}

.reject { 
    background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
}

.reject:hover {
    background: linear-gradient(135deg, #a93226 0%, #c0392b 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(192, 57, 43, 0.4);
}

.delete { 
    background: linear-gradient(135deg, #e67e22 0%, #f39c12 100%);
}

.delete:hover {
    background: linear-gradient(135deg, #d68910 0%, #e67e22 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(230, 126, 34, 0.4);
}

td:last-child {
    white-space: nowrap;
}

@media (max-width: 768px) {
    body {
        padding: 20px 10px;
    }
    
    h2 {
        font-size: 1.8em;
        margin-bottom: 20px;
    }
    
    table {
        font-size: 0.85em;
    }
    
    th, td {
        padding: 10px 5px;
    }
    
    a.button {
        padding: 6px 12px;
        font-size: 0.75em;
        margin: 2px;
    }
}
    .links { 
       text-align: left; 
       margin-top: 40px; 
       margin-left: 40px;
    }

    .links a {
      text-decoration: none; 
      background: #2980b9; 
      color: white;
      padding: 10px 20px; 
      border-radius: 5px; 
      margin: 0 5px;
    }

</style>
</head>
<body>
<h2>Manage Bookings</h2>
<table>
<tr><th>ID</th><th>User</th><th>Computer</th><th>Date</th><th>Time</th><th>Status</th><th>Action</th></tr>
<?php while($row = $result->fetch_assoc()): ?>
<tr>
  <td><?= $row['id'] ?></td>
  <td><?= $row['user_name'] ?></td>
  <td><?= $row['computer_code'] ?></td>
  <td><?= $row['date'] ?></td>
  <td><?= $row['start_time'].' - '.$row['end_time'] ?></td>
  <td><?= $row['status'] ?></td>
  <td>
    <?php if($row['status'] == 'pending'): ?>
      <a href="approve.php?id=<?= $row['id'] ?>" class="button approve">Approve</a>
      <a href="reject.php?id=<?= $row['id'] ?>" class="button reject">Reject</a>
    <?php else: ?>
      <?= ucfirst($row['status']) ?>
      <a href="delete_booking.php?id=<?= $row['id'] ?>" class="button delete" onclick="return confirm('Are you sure you want to delete this booking?')">Delete</a>
    <?php endif; ?>
  </td>
</tr>
<?php endwhile; ?>
</table>
<div class="links">
  <a href="dashboard.php">← Back to dashboard</a>
  </div>
</body>
</html>