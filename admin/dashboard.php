<?php
session_start();
require '../db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Stats - Fixed $conn to $mysqli
$total_users = $mysqli->query("SELECT COUNT(*) AS cnt FROM users")->fetch_assoc()['cnt'];
$total_bookings = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings")->fetch_assoc()['cnt'];
$pending = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE status='pending'")->fetch_assoc()['cnt'];
$total_labs = $mysqli->query("SELECT COUNT(*) AS cnt FROM labs")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
  <style>
    body { 
      font-family:Arial; 
      background: #b8eaf8ff; 
      margin: 0; 
    }

    .header { 
      background: #2c3e50; 
      color: white; 
      padding: 15px; 
      text-align: center; 
      position: relative; 
    }

    .header .logout-btn {
      color: white;
      text-decoration: none;
      background: linear-gradient(135deg, #c31717ff 0%, #e84a4aff 100%);
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 600;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
    }

    .header .logout-btn:hover {
      transform: translateY(-50%) translateY(-2px);
      box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }

    .cards { 
      display: flex; 
      justify-content: space-around; 
      margin: 30px; 
      flex-wrap: wrap; 
    }

    .card {
      background: white; 
      padding: 20px; 
      width: 22%; 
      text-align: center;
      border-radius: 10px; 
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    .card h2 { 
      margin: 0; 
      color: #2c3e50; 
    }

    .links { 
      text-align: center; 
      margin-top: 20px; 
    }

    .links a {
      text-decoration: none; 
      background: #2980b9; 
      color: white;
      padding: 10px 20px; 
      border-radius: 5px; 
      margin: 0 5px;
      display: inline-block;
      margin-bottom: 10px;
    }

    .links a:hover {
      background: #3498db;
    }

    .pdf-section {
      text-align: center; 
      margin: 40px auto; 
      padding: 40px; 
      background: white; 
      border-radius: 10px; 
      box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
      width: 300px;
    }
  </style>
</head>
<body>
<div class="header">
  <h1>Admin Dashboard</h1>
  <a href="logout.php" class="logout-btn">Logout</a>
</div>

<div class="cards">
  <div class="card"><h2><?= $total_users ?></h2><p>Users</p></div>
  <div class="card"><h2><?= $total_labs ?></h2><p>Labs</p></div>
  <div class="card"><h2><?= $total_bookings ?></h2><p>Total Bookings</p></div>
  <div class="card"><h2><?= $pending ?></h2><p>Pending Approvals</p></div>
</div>

<div class="links">
  <a href="manage_bookings.php">Manage Bookings</a>
  <a href="admin_labs.php">Manage Labs</a>
  <a href="admin_chat.php">Chat</a>
  <a href="create_notification.php">Notifications</a>

  <a href="analytics.php">View Analytics</a>
  <a href="admin_feedback.php">View User Feedback</a>
</div>

<div class="pdf-section">
    <h3>📊 Export PDF Reports</h3>
    <p style="color: #7f8c8d; margin-bottom: 15px;">Generate detailed reports with custom date ranges</p>
    <a href="pdf_selector.php" style="background: #a759d1ff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
        📋 Generate Custom PDF Report
    </a>
</div>

</body>
</html>