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
body { font-family: Arial; background: #f4f4f4; }
table { border-collapse: collapse; width: 100%; background: white; }
th, td { border: 1px solid #ccc; padding: 10px; text-align: center; }
th { background: #2c3e50; color: white; }
a.button { padding: 5px 10px; color: white; text-decoration: none; border-radius: 5px; }
.approve { background: #27ae60; }
.reject { background: #c0392b; }
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
    <?php endif; ?>
  </td>
</tr>
<?php endwhile; ?>
</table>
</body>
</html>
