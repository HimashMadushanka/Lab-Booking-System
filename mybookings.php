<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_id = $_POST['lab_id'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    // ✅ Step 1: Get total computers in this lab
    $lab_query = $conn->query("SELECT COUNT(*) AS total FROM computers WHERE lab_id = '$lab_id'");
    $lab_data = $lab_query->fetch_assoc();
    $total_computers = $lab_data['total'];

    if ($total_computers == 0) {
        $message = "❌ This lab has no computers added yet!";
    } else {
        // ✅ Step 2: Check how many computers are already booked for that time
        $check_query = $conn->query("
            SELECT COUNT(*) AS booked 
            FROM bookings b
            JOIN computers c ON b.computer_id = c.id
            WHERE c.lab_id = '$lab_id'
            AND b.date = '$date'
            AND (
                ('$start_time' BETWEEN b.start_time AND b.end_time)
                OR ('$end_time' BETWEEN b.start_time AND b.end_time)
                OR (b.start_time BETWEEN '$start_time' AND '$end_time')
            )
        ");

        $check = $check_query->fetch_assoc();
        $booked = $check['booked'];

        // ✅ Step 3: If available, assign a free computer
        if ($booked < $total_computers) {
            $free_computer_query = $conn->query("
                SELECT id FROM computers WHERE lab_id = '$lab_id'
                AND id NOT IN (
                    SELECT computer_id FROM bookings 
                    WHERE date = '$date'
                    AND (
                        ('$start_time' BETWEEN start_time AND end_time)
                        OR ('$end_time' BETWEEN start_time AND end_time)
                        OR (start_time BETWEEN '$start_time' AND '$end_time')
                    )
                )
                LIMIT 1
            ");
            $free_computer = $free_computer_query->fetch_assoc();
            $computer_id = $free_computer['id'];

            // ✅ Step 4: Insert booking
            $insert = $conn->query("
                INSERT INTO bookings (user_id, computer_id, date, start_time, end_time, status)
                VALUES ('$user_id', '$computer_id', '$date', '$start_time', '$end_time', 'pending')
            ");

            if ($insert) {
                $message = "✅ Booking successful!";
                $message_type = "success";
            } else {
                $message = "❌ Database Error: " . $conn->error;
                $message_type = "error";
            }
        } else {
            $message = "❌ No available computers in this lab for the selected time.";
            $message_type = "error";
        }
    }
}

// ✅ Fetch available labs for dropdown
$labs = $conn->query("SELECT * FROM labs");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book a Lab | Lab Management</title>
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: #f8f9fa;
  min-height: 100vh;
}

/* Sidebar Navigation */
.sidebar {
  position: fixed;
  left: 0;
  top: 0;
  width: 260px;
  height: 100vh;
  background: #1e293b;
  padding: 30px 0;
  z-index: 100;
}

.sidebar-logo {
  padding: 0 25px 30px;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  margin-bottom: 30px;
}

.sidebar-logo h2 {
  color: white;
  font-size: 22px;
  font-weight: 700;
}

.sidebar-logo p {
  color: #94a3b8;
  font-size: 13px;
  margin-top: 5px;
}

.sidebar-menu {
  list-style: none;
}

.sidebar-menu li {
  margin-bottom: 5px;
}

.sidebar-menu a {
  display: flex;
  align-items: center;
  padding: 14px 25px;
  color: #cbd5e1;
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 15px;
}

.sidebar-menu a:hover {
  background: rgba(255,255,255,0.05);
  color: white;
  padding-left: 30px;
}

.sidebar-menu a.active {
  background: #3b82f6;
  color: white;
  border-left: 4px solid #60a5fa;
}

.sidebar-menu a span {
  margin-right: 12px;
  font-size: 18px;
}

.logout-btn {
  position: absolute;
  bottom: 30px;
  left: 25px;
  right: 25px;
}

.logout-btn a {
  display: block;
  padding: 12px 20px;
  background: #dc2626;
  color: white;
  text-align: center;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  transition: background 0.3s ease;
}

.logout-btn a:hover {
  background: #b91c1c;
}

/* Main Content */
.main-content {
  margin-left: 260px;
  padding: 40px;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.booking-container {
  background: white;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  width: 100%;
  max-width: 600px;
}

.page-header {
  margin-bottom: 30px;
  text-align: center;
}

.page-header h2 {
  font-size: 28px;
  color: #1e293b;
  font-weight: 700;
  margin-bottom: 8px;
}

.page-header p {
  color: #64748b;
  font-size: 15px;
}

.alert {
  padding: 14px 18px;
  border-radius: 8px;
  margin-bottom: 25px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  line-height: 1.5;
}

.alert-error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.alert-success {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

label {
  font-weight: 600;
  color: #334155;
  font-size: 14px;
}

select, input {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 15px;
  color: #1e293b;
  transition: all 0.3s ease;
  outline: none;
  background: white;
}

select:focus, input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

select {
  cursor: pointer;
}

input[type="date"]::-webkit-calendar-picker-indicator,
input[type="time"]::-webkit-calendar-picker-indicator {
  cursor: pointer;
}

.time-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

button {
  width: 100%;
  padding: 14px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 10px;
}

button:hover {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

button:active {
  transform: translateY(0);
}

.nav-links {
  display: flex;
  justify-content: center;
  gap: 15px;
  margin-top: 25px;
  flex-wrap: wrap;
}

.nav-links a {
  color: #64748b;
  text-decoration: none;
  font-size: 14px;
  transition: color 0.3s ease;
  padding: 8px 12px;
  border-radius: 6px;
}

.nav-links a:hover {
  color: #3b82f6;
  background: #f1f5f9;
}

/* Responsive */
@media (max-width: 1024px) {
  .sidebar {
    transform: translateX(-100%);
  }
  
  .main-content {
    margin-left: 0;
  }
}

@media (max-width: 768px) {
  .main-content {
    padding: 20px;
  }

  .booking-container {
    padding: 30px 25px;
  }

  .page-header h2 {
    font-size: 24px;
  }

  .time-group {
    grid-template-columns: 1fr;
  }

  .nav-links {
    flex-direction: column;
    gap: 10px;
  }

  .nav-links a {
    text-align: center;
  }
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <div class="sidebar-logo">
    <h2>🖥️ Lab Manager</h2>
    <p>Computer Lab System</p>
  </div>
  
  <ul class="sidebar-menu">
    <li><a href="index.php"><span>📊</span> Dashboard</a></li>
    <li><a href="book_lab.php"><span>➕</span> Book a Lab</a></li>
    <li><a href="my_bookings.php"><span>📋</span> My Bookings</a></li>
    <li><a href="feedback.php"><span>💬</span>Give Feedback</a>
  </ul>
  
  <div class="logout-btn">
    <a href="../logout.php">Logout</a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content">
  <div class="booking-container">
    
    <div class="page-header">
      <h2>📅 Book a Lab</h2>
      <p>Select your preferred lab and time slot</p>
    </div>

    <?php if (!empty($message)): ?>
      <div class="alert alert-<?= isset($message_type) ? $message_type : 'error' ?>">
        <?= $message ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="lab_id">🏢 Select Lab</label>
        <select name="lab_id" id="lab_id" required>
          <option value="">-- Choose Lab --</option>
          <?php while ($lab = $labs->fetch_assoc()): ?>
            <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="date">📆 Select Date</label>
        <input type="date" name="date" id="date" required min="<?= date('Y-m-d') ?>">
      </div>

      <div class="time-group">
        <div class="form-group">
          <label for="start_time">🕐 Start Time</label>
          <input type="time" name="start_time" id="start_time" required>
        </div>

        <div class="form-group">
          <label for="end_time">🕐 End Time</label>
          <input type="time" name="end_time" id="end_time" required>
        </div>
      </div>

      <button type="submit">🎯 Book Lab</button>
    </form>

    <div class="nav-links">
      <a href="index.php">← Back to Dashboard</a>
      <a href="my_bookings.php">📋 View My Bookings</a>
    </div>
  </div>
</div>
</body>
</html>
