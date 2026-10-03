<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Get booking ID from URL
if (!isset($_GET['id'])) {
    header("Location: my_bookings.php");
    exit;
}

$booking_id = $_GET['id'];

// Verify the booking belongs to the user and fetch current data
$sql = "
SELECT 
    b.*,
    c.code AS computer_code,
    l.name AS lab_name,
    l.id AS lab_id,
    c.id AS computer_id
FROM bookings b
JOIN computers c ON b.computer_id = c.id
JOIN labs l ON c.lab_id = l.id
WHERE b.id = ? AND b.user_id = ?
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: my_bookings.php");
    exit;
}

$booking = $result->fetch_assoc();

// Fetch available labs and computers
$labs_sql = "SELECT * FROM labs ORDER BY name";
$labs_result = $mysqli->query($labs_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_id = $_POST['lab_id'];
    $computer_id = $_POST['computer_id'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    
    // Check for conflicts
    $conflict_sql = "
    SELECT id FROM bookings 
    WHERE computer_id = ? 
    AND date = ? 
    AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?))
    AND id != ?
    AND status != 'rejected'
    ";
    
    $conflict_stmt = $mysqli->prepare($conflict_sql);
    $conflict_stmt->bind_param("isssssi", $computer_id, $date, $end_time, $start_time, $start_time, $end_time, $booking_id);
    $conflict_stmt->execute();
    $conflict_result = $conflict_stmt->get_result();
    
    if ($conflict_result->num_rows > 0) {
        $message = "<div class='alert alert-error'>❌ Selected time slot is already booked for this computer.</div>";
    } else {
        // Update booking
        $update_sql = "
        UPDATE bookings 
        SET computer_id = ?, date = ?, start_time = ?, end_time = ?, status = 'pending'
        WHERE id = ? AND user_id = ?
        ";
        
        $update_stmt = $mysqli->prepare($update_sql);
        $update_stmt->bind_param("isssii", $computer_id, $date, $start_time, $end_time, $booking_id, $user_id);
        
        if ($update_stmt->execute()) {
            $message = "<div class='alert alert-success'>✅ Booking updated successfully! Status reset to pending for admin approval.</div>";
            // Refresh booking data
            $stmt->execute();
            $result = $stmt->get_result();
            $booking = $result->fetch_assoc();
        } else {
            $message = "<div class='alert alert-error'>⚠️ Failed to update booking. Try again.</div>";
        }
    }
}

// Fetch computers for selected lab (default to current lab)
$current_lab_id = $booking['lab_id'];
$computers_sql = "SELECT * FROM computers WHERE lab_id = ? AND status = 'available' ORDER BY code";
$computers_stmt = $mysqli->prepare($computers_sql);
$computers_stmt->bind_param("i", $current_lab_id);
$computers_stmt->execute();
$computers_result = $computers_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Booking | LabEase</title>
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
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 30px 20px;
    }

    .booking-container {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      width: 100%;
      max-width: 800px;
      min-height: auto;
    }

    .page-header {
      margin-bottom: 20px;
      text-align: center;
    }

    .page-header h2 {
      font-size: 26px;
      color: #1e293b;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .page-header p {
      color: #64748b;
      font-size: 14px;
    }

    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      line-height: 1.4;
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

    .current-booking {
      background: #f0f9ff;
      border: 1px solid #bae6fd;
      border-radius: 8px;
      padding: 18px;
      margin-bottom: 20px;
    }

    .current-booking h3 {
      color: #0369a1;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 16px;
    }

    .current-booking p {
      margin: 6px 0;
      color: #0c4a6e;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
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
      font-size: 15px;
    }

    .info-box ul {
      margin-left: 20px;
      color: #475569;
      font-size: 13px;
    }

    .info-box li {
      margin-bottom: 4px;
    }

    form {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    label {
      font-weight: 600;
      color: #334155;
      font-size: 14px;
    }

    select, input {
      width: 100%;
      padding: 10px 14px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 14px;
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

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }

    .time-group {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }

    .status-badge {
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 600;
      text-transform: capitalize;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .status-badge.pending {
      background: #fef3c7;
      color: #92400e;
    }

    .status-badge.approved {
      background: #d1fae5;
      color: #065f46;
    }

    .status-badge.rejected {
      background: #fee2e2;
      color: #991b1b;
    }

    .form-actions {
      display: flex;
      gap: 12px;
      margin-top: 20px;
    }

    .btn-secondary {
      flex: 1;
      padding: 12px;
      background: #6b7280;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    .btn-secondary:hover {
      background: #4b5563;
      transform: translateY(-1px);
    }

    button {
      flex: 2;
      padding: 12px;
      background: #3b82f6;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    button:hover {
      background: #2563eb;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    @media (max-width: 768px) {
      body {
        padding: 20px 15px;
        align-items: flex-start;
      }

      .booking-container {
        padding: 25px 20px;
        max-width: 100%;
      }

      .page-header h2 {
        font-size: 22px;
      }

      .form-row,
      .time-group {
        grid-template-columns: 1fr;
      }

      .form-actions {
        flex-direction: column;
      }
    }

    @media (max-width: 480px) {
      body {
        padding: 15px 10px;
      }

      .booking-container {
        padding: 20px 15px;
      }

      .current-booking,
      .info-box {
        padding: 15px;
      }
    }
  </style>
</head>
<body>

<div class="booking-container">
  
  <div class="page-header">
    <h2>✏️ Edit Booking</h2>
    <p>Modify your lab booking details</p>
  </div>

  <?php echo $message; ?>

  <!-- Current Booking Info -->
  <div class="current-booking">
    <h3>📋 Current Booking Details</h3>
    <p><strong>🏢 Lab:</strong> <?= htmlspecialchars($booking['lab_name']) ?></p>
    <p><strong>💻 Computer:</strong> <?= htmlspecialchars($booking['computer_code']) ?></p>
    <p><strong>📆 Date:</strong> <?= date('M d, Y', strtotime($booking['date'])) ?></p>
    <p><strong>🕐 Time:</strong> <?= date('g:i A', strtotime($booking['start_time'])) ?> - <?= date('g:i A', strtotime($booking['end_time'])) ?></p>
    <p><strong>📊 Status:</strong> <span class="status-badge <?= strtolower($booking['status']) ?>"><?= ucfirst($booking['status']) ?></span></p>
  </div>

  <div class="info-box">
    <h4>ℹ️ Editing Rules</h4>
    <ul>
      <li>Editing will reset your booking status to "Pending"</li>
      <li>Admin approval is required after editing</li>
      <li>Make sure the new time slot is available</li>
      <li>You cannot edit rejected or cancelled bookings</li>
    </ul>
  </div>

  <form method="POST" action="">
    <div class="form-row">
      <div class="form-group">
        <label for="lab_id">🏢 Select Lab</label>
        <select name="lab_id" id="lab_id" required>
          <option value="">-- Choose Lab --</option>
          <?php
          mysqli_data_seek($labs_result, 0);
          while($lab = $labs_result->fetch_assoc()):
            $selected = ($lab['id'] == $booking['lab_id']) ? 'selected' : '';
            echo "<option value='{$lab['id']}' $selected>{$lab['name']} - {$lab['location']}</option>";
          endwhile;
          ?>
        </select>
      </div>

      <div class="form-group">
        <label for="computer_id">💻 Select Computer</label>
        <select name="computer_id" id="computer_id" required>
          <option value="">-- Choose Computer --</option>
          <?php
          mysqli_data_seek($computers_result, 0);
          while($computer = $computers_result->fetch_assoc()):
            $selected = ($computer['id'] == $booking['computer_id']) ? 'selected' : '';
            echo "<option value='{$computer['id']}' $selected>{$computer['code']}</option>";
          endwhile;
          ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="date">📆 Date</label>
      <input type="date" name="date" id="date" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($booking['date']) ?>">
    </div>

    <div class="time-group">
      <div class="form-group">
        <label for="start_time">🕐 Start Time</label>
        <input type="time" name="start_time" id="start_time" required value="<?= substr($booking['start_time'], 0, 5) ?>">
      </div>

      <div class="form-group">
        <label for="end_time">🕐 End Time</label>
        <input type="time" name="end_time" id="end_time" required value="<?= substr($booking['end_time'], 0, 5) ?>">
      </div>
    </div>

    <div class="form-actions">
      <a href="my_bookings.php" class="btn-secondary">← Back to Bookings</a>
      <button type="submit">💾 Update Booking</button>
    </div>
  </form>

</div>

<script>
// Add client-side validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const dateInput = document.getElementById('date');
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const labSelect = document.getElementById('lab_id');
    const computerSelect = document.getElementById('computer_id');
    
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
    
    // Update computers when lab changes
    labSelect.addEventListener('change', function() {
        const labId = this.value;
        
        if (labId) {
            fetch(`get_computers.php?lab_id=${labId}`)
                .then(response => response.json())
                .then(computers => {
                    computerSelect.innerHTML = '<option value="">-- Choose Computer --</option>';
                    computers.forEach(computer => {
                        computerSelect.innerHTML += `<option value="${computer.id}">${computer.code}</option>`;
                    });
                })
                .catch(error => {
                    console.error('Error fetching computers:', error);
                    computerSelect.innerHTML = '<option value="">-- Error loading computers --</option>';
                });
        } else {
            computerSelect.innerHTML = '<option value="">-- Choose Computer --</option>';
        }
    });
    
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