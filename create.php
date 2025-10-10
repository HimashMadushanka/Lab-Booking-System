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
                AND NOT (end_time <= ? OR start_time >= ?)
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
  <title>Book a Computer | Lab Management</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f9;
      padding: 20px;
    }
    h2 { color: #2c3e50; }
    form {
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      width: 400px;
    }
    label {
      font-weight: bold;
      display: block;
      margin-top: 10px;
    }
    select, input, button {
      width: 100%;
      padding: 8px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 5px;
    }
    button {
      background: #2980b9;
      color: white;
      border: none;
      cursor: pointer;
      margin-top: 15px;
    }
    button:hover {
      background: #1f6391;
    }
    p {
      padding: 10px;
      border-radius: 5px;
    }
    .error { background: #ffe5e5; color: #c0392b; }
    .success { background: #e5ffe8; color: #27ae60; }
  </style>
</head>
<body>

<h2>Book a Computer</h2>

<?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
<?php if (!empty($success)) echo "<p class='success'>$success</p>"; ?>

<form method="post">
  <label>Select Lab:</label>
  <select name="lab_id" required>
    <option value="">-- Choose Lab --</option>
    <?php
    mysqli_data_seek($labs, 0);
    while($lab = $labs->fetch_assoc()):
      echo "<option value='{$lab['id']}'>{$lab['name']}</option>";
    endwhile;
    ?>
  </select>

  <label>Date:</label>
  <input type="date" name="date" required>

  <label>Start Time:</label>
  <input type="time" name="start_time" required>

  <label>End Time:</label>
  <input type="time" name="end_time" required>

  <button type="submit" name="book">Book Computer</button>
</form>

</body>
</html>
