<?php
// index.php - User Dashboard
require 'db.php';

// Check if logged in and is user
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user'){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// --- Fetch statistics ---
$total_computers = $conn->query("SELECT COUNT(*) AS cnt FROM computers WHERE status='available'")->fetch_assoc()['cnt'];
$total_bookings = $conn->query("SELECT COUNT(*) AS cnt FROM bookings WHERE user_id=$user_id")->fetch_assoc()['cnt'];
$approved_bookings = $conn->query("SELECT COUNT(*) AS cnt FROM bookings WHERE user_id=$user_id AND status='approved'")->fetch_assoc()['cnt'];

// --- Fetch upcoming bookings ---
$stmt = $conn->prepare("
    SELECT b.*, c.code 
    FROM bookings b 
    JOIN computers c ON c.id=b.computer_id 
    WHERE b.user_id=? AND b.date >= CURDATE() 
    ORDER BY b.date, b.start_time LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Dashboard | Lab Management</title>
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
  text-align: center;
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

.top-bar {
  background: white;
  padding: 20px 30px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  margin-bottom: 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.top-bar h1 {
  font-size: 28px;
  color: #1e293b;
  font-weight: 700;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-avatar {
  width: 45px;
  height: 45px;
  border-radius: 50%;
  background: linear-gradient(135deg, #3b82f6, #8b5cf6);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 18px;
}

.user-details h3 {
  font-size: 15px;
  color: #1e293b;
  font-weight: 600;
}

.user-details p {
  font-size: 13px;
  color: #64748b;
}

/* Stats Cards */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 25px;
  margin-bottom: 35px;
}

.stat-card {
  background: white;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  display: flex;
  align-items: center;
  gap: 20px;
  transition: all 0.3s ease;
  border-left: 4px solid transparent;
}

.stat-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  transform: translateY(-2px);
}

.stat-card:nth-child(1) {
  border-left-color: #3b82f6;
}

.stat-card:nth-child(2) {
  border-left-color: #10b981;
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  flex-shrink: 0;
}

.stat-card:nth-child(1) .stat-icon {
  background: #dbeafe;
  color: #3b82f6;
}

.stat-card:nth-child(2) .stat-icon {
  background: #d1fae5;
  color: #10b981;
}

.stat-info h3 {
  font-size: 32px;
  color: #1e293b;
  font-weight: 700;
  margin-bottom: 5px;
}

.stat-info p {
  font-size: 14px;
  color: #64748b;
  font-weight: 500;
}

/* Bookings Section */
.bookings-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  overflow: hidden;
}

.section-header {
  padding: 25px 30px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-header h2 {
  font-size: 20px;
  color: #1e293b;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-primary {
  padding: 10px 20px;
  background: #3b82f6;
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  transition: background 0.3s ease;
}

.btn-primary:hover {
  background: #2563eb;
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
  padding: 16px 25px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #e2e8f0;
}

table td {
  padding: 18px 25px;
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
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
  display: inline-block;
}

.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.status-approved {
  background: #d1fae5;
  color: #065f46;
}

.status-rejected {
  background: #fee2e2;
  color: #991b1b;
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
  
  .top-bar {
    flex-direction: column;
    gap: 15px;
    text-align: center;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  table th, table td {
    padding: 12px 15px;
    font-size: 13px;
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
    <li><a href="index.php" class="active"><span>📊</span> Dashboard</a></li>
    <li><a href="calendar.php"><span>📅</span> Calendar View</a></li>
    <li><a href="create.php"><span>➕</span> Book a Lab</a></li>
    <li><a href="my_bookings.php"><span>📋</span> My Bookings</a></li>
    <li><a href="feedback.php"><span>💬</span>Give Feedback</a>

  </ul>
  
  <div class="logout-btn">
    <a href="logout.php">Logout</a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content">
  
  <!-- Top Bar -->
  <div class="top-bar">
    <h1>Dashboard</h1>
    <div class="user-info">
      <div class="user-avatar">
        <?= strtoupper(substr($user_name, 0, 1)) ?>
      </div>
      <div class="user-details">
        <h3><?= htmlspecialchars($user_name) ?></h3>
        <p>Student</p>
      </div>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">📚</div>
      <div class="stat-info">
        <h3><?= $total_bookings ?></h3>
        <p>Total Bookings</p>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon">✅</div>
      <div class="stat-info">
        <h3><?= $approved_bookings ?></h3>
        <p>Approved Bookings</p>
      </div>
    </div>
  </div>

  <!-- Upcoming Bookings -->
  <div class="bookings-section">
    <div class="section-header">
      <h2>📅 Upcoming Bookings</h2>
      <a href="create.php" class="btn-primary">+ New Booking</a>
    </div>
    
    <div class="table-wrapper">
      <?php if($upcoming->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Computer Lab</th>
              <th>Date</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while($b = $upcoming->fetch_assoc()): ?>
            <tr>
              <td><strong><?= htmlspecialchars($b['code']) ?></strong></td>
              <td><?= date('M d, Y', strtotime($b['date'])) ?></td>
              <td><?= date('g:i A', strtotime($b['start_time'])) ?></td>
              <td><?= date('g:i A', strtotime($b['end_time'])) ?></td>
              <td>
                <span class="status-badge status-<?= strtolower($b['status']) ?>">
                  <?= htmlspecialchars($b['status']) ?>
                </span>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-state-icon">📭</div>
          <p>You don't have any upcoming bookings</p>
          <a href="create.php">Book Your First Lab</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

</body>
</html>