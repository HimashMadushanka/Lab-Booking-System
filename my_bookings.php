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

// Handle delete booking
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Verify the booking belongs to the user
    $verify_sql = "SELECT id FROM bookings WHERE id = ? AND user_id = ?";
    $verify_stmt = $mysqli->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $delete_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        $delete_sql = "DELETE FROM bookings WHERE id = ?";
        $delete_stmt = $mysqli->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $message = "<div class='alert alert-success'>Booking deleted successfully!</div>";
        } else {
            $message = "<div class='alert alert-error'>Error deleting booking.</div>";
        }
    } else {
        $message = "<div class='alert alert-error'>Unauthorized action.</div>";
    }
}

// Fetch all bookings for this user
$sql = "
SELECT 
    b.id, 
    c.code AS computer_code, 
    l.name AS lab_name, 
    l.id AS lab_id,
    c.id AS computer_id,
    b.date, 
    b.start_time, 
    b.end_time, 
    b.status
FROM bookings b
JOIN computers c ON b.computer_id = c.id
JOIN labs l ON c.lab_id = l.id
WHERE b.user_id = ?
ORDER BY b.date DESC, b.start_time DESC
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Bookings | Lab Management System</title>
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

/* Alert Messages */
.alert {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 20px;
  font-weight: 500;
}

.alert-success {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.alert-error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
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
  padding: 30px 40px;
}

.page-header {
  background: white;
  padding: 25px 30px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  margin-bottom: 30px;
}

.page-header h1 {
  font-size: 28px;
  color: #1e293b;
  font-weight: 700;
  margin-bottom: 5px;
}

.page-header p {
  color: #64748b;
  font-size: 15px;
}

/* Stats Summary */
.stats-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-box {
  background: white;
  padding: 20px;
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  text-align: center;
  border-left: 4px solid #3b82f6;
}

.stat-box.approved {
  border-left-color: #10b981;
}

.stat-box.pending {
  border-left-color: #f59e0b;
}

.stat-box.rejected {
  border-left-color: #ef4444;
}

.stat-box h3 {
  font-size: 32px;
  color: #1e293b;
  font-weight: 700;
  margin-bottom: 5px;
}

.stat-box p {
  color: #64748b;
  font-size: 14px;
  font-weight: 500;
}

/* Bookings Table */
.bookings-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  overflow: hidden;
}

.section-header {
  padding: 25px 30px;
  border-bottom: 1px solid #e2e8f0;
}

.section-header h2 {
  font-size: 20px;
  color: #1e293b;
  font-weight: 700;
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

table thead {
  background: #f8fafc;
}

table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #e2e8f0;
}

table td {
  padding: 18px 20px;
  color: #334155;
  font-size: 14px;
  border-bottom: 1px solid #f1f5f9;
}

table tbody tr {
  transition: background 0.2s ease;
}

table tbody tr:hover {
  background: #f8fafc;
}

table tbody tr:last-child td {
  border-bottom: none;
}

.status-badge {
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
  display: inline-block;
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

.row-number {
  color: #94a3b8;
  font-weight: 600;
}

.lab-name {
  font-weight: 600;
  color: #1e293b;
}

.computer-code {
  font-family: 'Courier New', monospace;
  background: #f1f5f9;
  padding: 4px 8px;
  border-radius: 4px;
  color: #3b82f6;
  font-weight: 600;
  font-size: 13px;
}

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s ease;
}

.btn-edit {
  background: #dbeafe;
  color: #1d4ed8;
}

.btn-edit:hover {
  background: #bfdbfe;
  color: #1e40af;
}

.btn-delete {
  background: #fee2e2;
  color: #dc2626;
}

.btn-delete:hover {
  background: #fecaca;
  color: #b91c1c;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.empty-state {
  padding: 60px 30px;
  text-align: center;
}

.empty-state-icon {
  font-size: 64px;
  margin-bottom: 15px;
  opacity: 0.3;
}

.empty-state p {
  color: #94a3b8;
  font-size: 15px;
  margin-bottom: 20px;
}

.empty-state a {
  display: inline-block;
  padding: 12px 24px;
  background: #3b82f6;
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-weight: 600;
  transition: background 0.3s ease;
}

.empty-state a:hover {
  background: #2563eb;
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
  
  .stats-summary {
    grid-template-columns: 1fr;
  }
  
  table th, table td {
    padding: 12px 15px;
    font-size: 13px;
  }
  
  .computer-code {
    font-size: 12px;
  }
  
  .action-buttons {
    flex-direction: column;
  }
  
  .btn {
    padding: 10px 12px;
    font-size: 12px;
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
    <li><a href="create.php"><span>➕</span> Book a Lab</a></li>
    <li><a href="my_bookings.php" class="active"><span>📋</span> My Bookings</a></li>
    <li><a href="analytics.php"><span>📈</span> Analytics</a></li>
    <li><a href="chat.php"><span>💬</span> Chat with Admin</a></li>
    <li><a href="notifications.php"><span>🔔</span> Notifications</a></li>
    <li><a href="feedback.php"><span>💬</span> Give Feedback</a></li>
    <li><a href="logout.php">🚪 Logout</a></li>
  </ul>
  
  <div class="logout-btn">
    <a href="logout.php">Logout</a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content">
  
  <!-- Page Header -->
  <div class="page-header">
    <h1>📋 My Bookings</h1>
    <p>View and manage all your lab bookings</p>
  </div>

  <?php echo $message; ?>

  <!-- Stats Summary -->
  <?php
  $total = $result->num_rows;
  $approved = 0;
  $pending = 0;
  $rejected = 0;
  
  mysqli_data_seek($result, 0);
  while($row = $result->fetch_assoc()) {
    if($row['status'] == 'approved') $approved++;
    elseif($row['status'] == 'pending') $pending++;
    elseif($row['status'] == 'rejected') $rejected++;
  }
  mysqli_data_seek($result, 0);
  ?>
  
  <div class="stats-summary">
    <div class="stat-box">
      <h3><?= $total ?></h3>
      <p>Total Bookings</p>
    </div>
    <div class="stat-box approved">
      <h3><?= $approved ?></h3>
      <p>Approved</p>
    </div>
    <div class="stat-box pending">
      <h3><?= $pending ?></h3>
      <p>Pending</p>
    </div>
    <div class="stat-box rejected">
      <h3><?= $rejected ?></h3>
      <p>Rejected</p>
    </div>
  </div>

  <!-- Bookings Table -->
  <div class="bookings-section">
    <div class="section-header">
      <h2>Booking History</h2>
    </div>
    
    <div class="table-wrapper">
      <?php if ($result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Lab</th>
              <th>Computer</th>
              <th>Date</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1; while($row = $result->fetch_assoc()): ?>
              <tr>
                <td class="row-number"><?= $i++ ?></td>
                <td class="lab-name"><?= htmlspecialchars($row['lab_name']) ?></td>
                <td><span class="computer-code"><?= htmlspecialchars($row['computer_code']) ?></span></td>
                <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                <td><?= date('g:i A', strtotime($row['start_time'])) ?></td>
                <td><?= date('g:i A', strtotime($row['end_time'])) ?></td>
                <td>
                  <span class="status-badge <?= strtolower($row['status']) ?>">
                    <?= ucfirst($row['status']) ?>
                  </span>
                </td>
                <td>
                  <div class="action-buttons">
                    <a href="edit_booking.php?id=<?= $row['id'] ?>" class="btn btn-edit">
                      ✏️ Edit
                    </a>
                    <a href="my_bookings.php?delete_id=<?= $row['id'] ?>" 
                       class="btn btn-delete" 
                       onclick="return confirm('Are you sure you want to delete this booking?')">
                      🗑️ Delete
                    </a>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-state-icon">📭</div>
          <p>You don't have any bookings yet</p>
          <a href="create.php">Make Your First Booking</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

</body>
</html>