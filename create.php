<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch labs for dropdown
$labs = $conn->query("SELECT * FROM labs ORDER BY id");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
    $lab_id = $_POST['lab_id'] ?? null;
    $date = $_POST['date'] ?? null;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;

    if (!$lab_id || !$date || !$start_time || !$end_time) {
        $error = "⚠️ All fields are required.";
    } else {
        // Find an available computer automatically
       
$find = $conn->prepare("
    SELECT id, code FROM computers
    WHERE lab_id=? AND status='available'
    AND id NOT IN (
        SELECT computer_id FROM bookings
        WHERE date=?
        AND status IN ('pending','approved')
        AND (
            ? < end_time AND ? > start_time
        )
    )
    LIMIT 1
");
$find->bind_param('isss', $lab_id, $date, $start_time, $end_time);

        $find->execute();
        $result = $find->get_result();

        if ($result->num_rows == 0) {
            $error = "❌ No available computers in this lab for the selected time.";
        } else {
            $row = $result->fetch_assoc();
            $computer_id = $row['id'];
            $computer_code = $row['code'];

            // Insert booking
            $ins = $conn->prepare("
                INSERT INTO bookings (user_id, computer_id, date, start_time, end_time, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $ins->bind_param('iisss', $_SESSION['user_id'], $computer_id, $date, $start_time, $end_time);

            if ($ins->execute()) {
                $success = "✅ Booking requested successfully. Assigned computer: <b>$computer_code</b>";
            } else {
                $error = "⚠️ Failed to create booking. Try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book a Computer | Lab Management</title>
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
    <li><a href="create.php" class="active"><span>➕</span> Book a Lab</a></li>
    <li><a href="mybookings.php"><span>📋</span> My Bookings</a></li>
  </ul>
  
  <div class="logout-btn">
    <a href="logout.php">Logout</a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content">
  <div class="booking-container">
    
    <div class="page-header">
      <h2>📅 Book a Computer</h2>
      <p>Select your preferred lab and time slot</p>
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
            echo "<option value='{$lab['id']}'>{$lab['name']}</option>";
          endwhile;
          ?>
        </select>
      </div>

      <div class="form-group">
        <label for="date">📆 Date</label>
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

      <button type="submit" name="book">🎯 Book Computer</button>
    </form>

    <div class="back-link">
      <a href="index.php">← Back to Dashboard</a>
    </div>

  </div>
</div>

</body>
</html>