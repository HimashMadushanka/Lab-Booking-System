<?php
session_start();
require '../db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Handle messages
$message = '';
$error = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Manage Labs</title>
  <style>
    body { 
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #b8eaf8ff; 
      margin: 0;
      padding: 20px;
    }

    .header { 
      background: #2c3e50; 
      color: white; 
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 30px;
      text-align: center;
      position: relative;
    }

    .header h1 {
      margin-bottom: 10px;
    }

    .back-btn, .logout-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      color: white;
      text-decoration: none;
      padding: 10px 20px;
      border-radius: 5px;
      font-weight: 600;
    }

    .back-btn {
      left: 20px;
      background: #3498db;
    }

    .logout-btn {
      right: 20px;
      background: #e74c3c;
    }

    .back-btn:hover, .logout-btn:hover {
      opacity: 0.9;
    }

    .management-section {
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      margin-bottom: 30px;
    }

    .management-section h2 {
      color: #2c3e50;
      margin-bottom: 20px;
      text-align: center;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: bold;
      color: #2c3e50;
    }

    .form-group input {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 14px;
      transition: border-color 0.3s;
    }

    .form-group input:focus {
      border-color: #3498db;
      outline: none;
    }

    .btn {
      padding: 12px 25px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: bold;
      font-size: 14px;
      transition: all 0.3s;
    }

    .btn-primary {
      background: #2980b9;
      color: white;
    }

    .btn-primary:hover {
      background: #3498db;
    }

    .btn-danger {
      background: #e74c3c;
      color: white;
    }

    .btn-danger:hover {
      background: #c0392b;
    }

    .btn-success {
      background: #27ae60;
      color: white;
    }

    .btn-success:hover {
      background: #2ecc71;
    }

    .labs-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .labs-table th,
    .labs-table td {
      padding: 15px;
      text-align: left;
      border-bottom: 1px solid #ecf0f1;
    }

    .labs-table th {
      background: #34495e;
      color: white;
      font-weight: 600;
    }

    .labs-table tr:hover {
      background: #f8f9fa;
    }

    .action-buttons {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .message {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 5px;
      text-align: center;
      font-weight: bold;
    }

    .success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .form-container {
      background: #f8f9fa;
      padding: 25px;
      border-radius: 8px;
      margin-bottom: 30px;
    }

    .computers-info {
      background: #e8f4fc;
      padding: 10px 15px;
      border-radius: 5px;
      margin-top: 10px;
      font-size: 14px;
      color: #2c3e50;
    }
  </style>
</head>
<body>
<div class="header">
  <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
  <h1>🏢 Lab Management</h1>
  <a href="logout.php" class="logout-btn">Logout</a>
</div>

<?php if ($message): ?>
    <div class="message success"><?= $message ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="message error"><?= $error ?></div>
<?php endif; ?>

<div class="management-section">
  <!-- Add New Lab Form -->
  <div class="form-container">
    <h2>Add New Lab</h2>
    <form method="POST" action="add_lab.php">
      <div class="form-group">
        <label for="lab_name">Lab Name:</label>
        <input type="text" id="lab_name" name="lab_name" required placeholder="e.g., FOC Lab F">
      </div>
      <div class="form-group">
        <label for="location">Location:</label>
        <input type="text" id="location" name="location" required placeholder="e.g., Building C - Room 302">
      </div>
      <div class="form-group">
        <label for="capacity">Capacity (Number of Computers):</label>
        <input type="number" id="capacity" name="capacity" required min="1" max="50" placeholder="e.g., 25">
        <div class="computers-info">
          💡 This will automatically create the specified number of computers for this lab.
        </div>
      </div>
      <button type="submit" class="btn btn-primary">➕ Add Lab</button>
    </form>
  </div>

  <!-- Existing Labs -->
  <h2>Existing Labs</h2>
  <?php
  $labs_result = $conn->query("SELECT l.*, COUNT(c.id) as computer_count FROM labs l LEFT JOIN computers c ON l.id = c.lab_id GROUP BY l.id ORDER BY l.name");
  if ($labs_result->num_rows > 0): ?>
    <table class="labs-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Lab Name</th>
          <th>Location</th>
          <th>Capacity</th>
          <!-- <th>Computers</th> -->
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while($lab = $labs_result->fetch_assoc()): ?>
        <tr>
          <td><?= $lab['id'] ?></td>
          <td><strong><?= htmlspecialchars($lab['name']) ?></strong></td>
          <td><?= htmlspecialchars($lab['location']) ?></td>
          <td><?= $lab['capacity'] ?></td>
          <!-- <td><?= $lab['computer_count'] ?> computers</td> -->
          <td>
            <div class="action-buttons">
              <!-- Update Capacity Form -->
              <form method="POST" action="edit_lab.php" style="display: inline;">
                <input type="hidden" name="lab_id" value="<?= $lab['id'] ?>">
                <input type="number" name="new_capacity" value="<?= $lab['capacity'] ?>" min="1" max="50" style="width: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <button type="submit" class="btn btn-success" style="padding: 8px 15px;">Update Capacity</button>
              </form>
              <!-- Delete Lab Form -->
              <form method="POST" action="delete_lab.php" style="display: inline;" onsubmit="return confirm('⚠️ Are you sure you want to delete <?= htmlspecialchars($lab['name']) ?>? This will also remove all associated computers and bookings!')">
                <input type="hidden" name="lab_id" value="<?= $lab['id'] ?>">
                <button type="submit" class="btn btn-danger" style="padding: 8px 15px;">Delete Lab</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div style="text-align: center; padding: 40px; color: #7f8c8d;">
      <div style="font-size: 48px; margin-bottom: 10px;">🏢</div>
      <h3>No Labs Found</h3>
      <p>Get started by adding your first lab using the form above.</p>
    </div>
  <?php endif; ?>
</div>

</body>
</html>