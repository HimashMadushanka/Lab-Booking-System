<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch labs for dropdown
$labs = $mysqli->query("SELECT * FROM labs ORDER BY id");

$error = '';
$success = '';
$lab_id = '';
$date = '';
$start_time = '';
$end_time = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
    $lab_id = intval($_POST['lab_id'] ?? 0);
    $date = $mysqli->real_escape_string($_POST['date'] ?? '');
    $start_time = $mysqli->real_escape_string($_POST['start_time'] ?? '');
    $end_time = $mysqli->real_escape_string($_POST['end_time'] ?? '');

    // Validation
    if (!$lab_id || !$date || !$start_time || !$end_time) {
        $error = "⚠️ All fields are required.";
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error = "❌ End time must be after start time.";
    } else {
        // Get lab details first
        $lab_stmt = $mysqli->prepare("SELECT name, location FROM labs WHERE id = ?");
        $lab_stmt->bind_param('i', $lab_id);
        $lab_stmt->execute();
        $lab_result = $lab_stmt->get_result();
        $lab = $lab_result->fetch_assoc();
        $lab_stmt->close();

        if (!$lab) {
            $error = "❌ Selected lab not found.";
        } else {
            $lab_name = $lab['name'];
            $lab_location = $lab['location'];

            // Find available computers in the selected lab
            $find = $mysqli->prepare("
                SELECT id, code FROM computers 
                WHERE lab_id = ? AND status = 'available'
            ");
            $find->bind_param('i', $lab_id);
            $find->execute();
            $result = $find->get_result();

            if ($result->num_rows == 0) {
                $error = "❌ No computers available in this lab.";
            } else {
                $computers = [];
                while ($row = $result->fetch_assoc()) {
                    $computers[] = $row;
                }
                
                // Try to find a computer without any conflicting bookings (approved or pending)
                $available_computer = null;
                $available_computer_code = null;
                
                foreach ($computers as $computer) {
                    $computer_id = $computer['id'];
                    $computer_code = $computer['code'];
                    
                    // Check for any conflicting bookings (both approved AND pending)
                    $check_conflicts = $mysqli->prepare("
                        SELECT id FROM bookings 
                        WHERE computer_id = ? 
                        AND date = ? 
                        AND status IN ('approved', 'pending')
                        AND (
                            (start_time < ? AND end_time > ?) OR
                            (start_time < ? AND end_time > ?) OR
                            (start_time >= ? AND end_time <= ?)
                        )
                    ");
                    $check_conflicts->bind_param('isssssss', 
                        $computer_id, $date, 
                        $end_time, $start_time,
                        $start_time, $end_time,
                        $start_time, $end_time
                    );
                    $check_conflicts->execute();
                    $conflict_result = $check_conflicts->get_result();
                    
                    if ($conflict_result->num_rows == 0) {
                        $available_computer = $computer_id;
                        $available_computer_code = $computer_code;
                        break; // Found an available computer
                    }
                    $check_conflicts->close();
                }

                if (!$available_computer) {
                    $error = "❌ No available time slots for this lab. All computers have pending or approved bookings during this time.";
                } else {
                    // Check if user already has a pending booking for the same lab and time
                    $check_user_booking = $mysqli->prepare("
                        SELECT b.id 
                        FROM bookings b 
                        JOIN computers c ON b.computer_id = c.id 
                        WHERE b.user_id = ? 
                        AND c.lab_id = ?
                        AND b.date = ? 
                        AND b.status = 'pending'
                        AND (
                            (b.start_time < ? AND b.end_time > ?) OR
                            (b.start_time < ? AND b.end_time > ?)
                        )
                    ");
                    $check_user_booking->bind_param('iisssss', 
                        $_SESSION['user_id'], $lab_id, $date,
                        $end_time, $start_time,
                        $start_time, $end_time
                    );
                    $check_user_booking->execute();
                    $user_booking_result = $check_user_booking->get_result();
                    
                    if ($user_booking_result->num_rows > 0) {
                        $error = "❌ You already have a pending booking for this lab during this time slot.";
                    } else {
                        // Insert booking as pending
                        $ins = $mysqli->prepare("
                            INSERT INTO bookings (user_id, computer_id, date, start_time, end_time, status)
                            VALUES (?, ?, ?, ?, ?, 'pending')
                        ");
                        $ins->bind_param('iisss', $_SESSION['user_id'], $available_computer, $date, $start_time, $end_time);

                        if ($ins->execute()) {
                            $success = "✅ Booking requested successfully for <b>$lab_name</b> ($lab_location). Waiting for admin approval.";
                            // Clear form fields
                            $lab_id = '';
                            $date = '';
                            $start_time = '';
                            $end_time = '';
                        } else {
                            $error = "⚠️ Failed to create booking. Try again.";
                        }
                        $ins->close();
                    }
                    $check_user_booking->close();
                }
            }
        }
    }
}

// Get pending bookings count for notification
$pending_count = 0;
if ($_SESSION['role'] === 'admin') {
    $pending_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    $pending_data = $pending_result->fetch_assoc();
    $pending_count = $pending_data['count'];
    $pending_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book a Computer | LabEase</title>
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
      position: relative;
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

    .pending-badge {
      position: absolute;
      right: 20px;
      top: 50%;
      transform: translateY(-50%);
      background: #ef4444;
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: bold;
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

    .alert-info {
      background: #dbeafe;
      color: #1e40af;
      border: 1px solid #93c5fd;
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

    .back-link {
      text-align: center;
      margin-top: 20px;
    }

    .back-link a {
      color: #64748b;
      text-decoration: none;
      font-size: 14px;
      transition: color 0.3s ease;
    }

    .back-link a:hover {
      color: #3b82f6;
    }

    .info-box {
      background: #f0f9ff;
      border: 1px solid #bae6fd;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 20px;
    }

    .info-box h4 {
      color: #0369a1;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .info-box ul {
      margin-left: 20px;
      color: #475569;
    }

    .info-box li {
      margin-bottom: 5px;
    }

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
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <div class="sidebar-logo">
    <h2>🖥️ LabEase</h2>
    <p>Computer Lab Booking System</p>
  </div>
  
  <ul class="sidebar-menu">
    <li><a href="dashboard.php" ><span>📊</span> Dashboard</a></li>
    <li><a href="calendar.php"><span>📅</span> Calendar View</a></li>
    <li><a href="create.php" class="active"><span>➕</span> Book a Lab</a></li>
    <li><a href="my_bookings.php"><span>📋</span> My Bookings</a></li>
    <li><a href="analytics.php"><span>📈</span> Analytics</a></li>
    <li><a href="chat.php"><span>💬</span> Chat with Admin</a></li>
    <li><a href="notifications.php"><span>🔔</span> Notifications</a></li>


    <?php if ($_SESSION['role'] === 'admin'): ?>
    <li>
      <a href="admin_pending.php">
        <span>👨‍💼</span> Pending Approvals
        <?php if ($pending_count > 0): ?>
          <span class="pending-badge"><?= $pending_count ?></span>
        <?php endif; ?>
      </a>
    </li>
    <?php endif; ?>
    <li><a href="feedback.php"><span>💬</span> Give Feedback</a></li>
    <li><a href="logout.php">🚪 Logout</a></li>
  </ul>
  
  <div class="logout-btn">
    <a href="logout.php">Logout</a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content">
  


  <div class="booking-container">
    
    <div class="page-header">
      <h2>📅 Book a Lab</h2>
      <p>Select your preferred lab and time slot</p>
    </div>

<div class="booking-container">

    <div class="info-box">
      <h4>ℹ️ Booking Rules</h4>
      <ul>
        <li>Only one booking per time slot can be approved</li>
        <li>Pending bookings block the time slot</li>
        <li>You cannot book overlapping time slots</li>
        <li>Admin will approve the most suitable booking</li>
      </ul>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label for="lab_id">🏢 Select Lab</label>
        <select name="lab_id" id="lab_id" required>
          <option value="">-- Choose Lab --</option>
          <?php
          mysqli_data_seek($labs, 0);
          while($lab = $labs->fetch_assoc()):
            $selected = ($lab_id == $lab['id']) ? 'selected' : '';
            echo "<option value='{$lab['id']}' $selected>{$lab['name']} - {$lab['location']}</option>";
          endwhile;
          ?>
        </select>
      </div>

      <div class="form-group">
        <label for="date">📆 Date</label>
        <input type="date" name="date" id="date" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($date ?? '') ?>">
      </div>

      <div class="time-group">
        <div class="form-group">
          <label for="start_time">🕐 Start Time</label>
          <input type="time" name="start_time" id="start_time" required value="<?= htmlspecialchars($start_time ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="end_time">🕐 End Time</label>
          <input type="time" name="end_time" id="end_time" required value="<?= htmlspecialchars($end_time ?? '') ?>">
        </div>
      </div>

      <button type="submit" name="book">🎯 Book Lab</button>
    </form>

    <div class="back-link">
      <a href="dashboard.php">← Back to Dashboard</a>
    </div>

  </div>
</div>

<script>
// Add some client-side validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const dateInput = document.getElementById('date');
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
    
    form.addEventListener('submit', function(e) {
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;
        
        if (startTime && endTime && startTime >= endTime) {
            e.preventDefault();
            alert('❌ End time must be after start time.');
            endTimeInput.focus();
        }
    });
    
    // Real-time validation for time inputs
    startTimeInput.addEventListener('change', validateTimes);
    endTimeInput.addEventListener('change', validateTimes);
    
    function validateTimes() {
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;
        
        if (startTime && endTime && startTime >= endTime) {
            endTimeInput.style.borderColor = '#dc2626';
            startTimeInput.style.borderColor = '#dc2626';
        } else {
            endTimeInput.style.borderColor = '#e2e8f0';
            startTimeInput.style.borderColor = '#e2e8f0';
        }
    }
});
</script>

</body>
</html>