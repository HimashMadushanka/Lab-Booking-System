<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
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
                VALUES ('$user_id', '$computer_id', '$date', '$start_time', '$end_time', 'Booked')
            ");

            if ($insert) {
                $message = "✅ Booking successful!";
            } else {
                $message = "❌ Database Error: " . $conn->error;
            }
        } else {
            $message = "❌ No available computers in this lab for the selected time.";
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
<title>Book a Lab | Lab Management</title>
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
  background: white;
  padding: 20px;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
form {
  display: flex;
  flex-direction: column;
  gap: 15px;
}
input, select, button {
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 5px;
}
button {
  background: #2980b9;
  color: white;
  cursor: pointer;
}
button:hover {
  background: #1f6391;
}
.message {
  text-align: center;
  margin-bottom: 15px;
  font-weight: bold;
}
.nav {
  text-align: center;
  margin-top: 20px;
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
  <h1>Book a Lab</h1>
  <p>Select a lab and time to book your computer</p>
</div>

<div class="container">
  <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
  <?php endif; ?>

  <form method="POST">
    <label for="lab_id">Select Lab:</label>
    <select name="lab_id" id="lab_id" required>
      <option value="">-- Choose Lab --</option>
      <?php while ($lab = $labs->fetch_assoc()): ?>
        <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['name']) ?></option>
      <?php endwhile; ?>
    </select>

    <label for="date">Select Date:</label>
    <input type="date" name="date" id="date" required>

    <label for="start_time">Start Time:</label>
    <input type="time" name="start_time" id="start_time" required>

    <label for="end_time">End Time:</label>
    <input type="time" name="end_time" id="end_time" required>

    <button type="submit">Book Lab</button>
  </form>

  <div class="nav">
    <a href="index.php">⬅ Back to Dashboard</a> |
    <a href="my_bookings.php">📋 View My Bookings</a>
  </div>
</div>

</body>
</html>
