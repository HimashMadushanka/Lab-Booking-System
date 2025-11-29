<?php
// index.php - User Dashboard with Calendar
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

// --- NEW: Fetch all bookings for calendar (current month and next 2 months) ---
$calendar_bookings_query = "
    SELECT b.date, b.start_time, b.end_time, b.status, c.code as computer_code
    FROM bookings b
    JOIN computers c ON c.id = b.computer_id
    WHERE b.date >= DATE_FORMAT(NOW(), '%Y-%m-01')
    AND b.date <= LAST_DAY(DATE_ADD(NOW(), INTERVAL 2 MONTH))
    ORDER BY b.date, b.start_time
";
$calendar_bookings_result = $conn->query($calendar_bookings_query);
$calendar_data = [];
while($booking = $calendar_bookings_result->fetch_assoc()) {
    $date = $booking['date'];
    if(!isset($calendar_data[$date])) {
        $calendar_data[$date] = [];
    }
    $calendar_data[$date][] = $booking;
}

// --- NEW: Fetch labs for the Available Labs section ---
$labs_query = "
    SELECT l.*, 
           COUNT(c.id) as total_computers,
           SUM(CASE WHEN c.status = 'available' THEN 1 ELSE 0 END) as available_computers
    FROM labs l 
    LEFT JOIN computers c ON l.id = c.lab_id 
    GROUP BY l.id 
    ORDER BY available_computers DESC, l.name
    LIMIT 4
";
$labs_result = $conn->query($labs_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Dashboard | LabEase</title>

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

.stat-card:nth-child(3) {
  border-left-color: #8b5cf6;
  cursor: pointer;
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
  transition: all 0.3s ease;
}

.stat-card:nth-child(1) .stat-icon {
  background: #dbeafe;
  color: #3b82f6;
}

.stat-card:nth-child(2) .stat-icon {
  background: #d1fae5;
  color: #10b981;
}

.stat-card:nth-child(3) .stat-icon {
  background: #ede9fe;
  color: #8b5cf6;
  cursor: pointer;
}

.stat-card:nth-child(3) .stat-icon:hover {
  transform: scale(1.1);
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

/* NEW: Available Labs Section */
.labs-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  overflow: hidden;
  margin-bottom: 30px;
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

.labs-grid {
  padding: 25px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

.lab-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  transition: all 0.3s ease;
  cursor: default;
}

.lab-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.lab-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 15px;
}

.lab-title h3 {
  font-size: 18px;
  color: #1e293b;
  font-weight: 700;
  margin-bottom: 5px;
}

.lab-title p {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

.lab-status {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.capacity-info {
  margin-bottom: 15px;
}

.capacity-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.capacity-label {
  font-size: 14px;
  color: #64748b;
  font-weight: 500;
}

.capacity-value {
  font-size: 14px;
  color: #1e293b;
  font-weight: 600;
}

.progress-bar {
  width: 100%;
  height: 8px;
  background: #f1f5f9;
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 8px;
}

.progress-fill {
  height: 100%;
  border-radius: 10px;
  transition: width 0.3s ease;
}

.stats-grid-mini {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 15px;
}

.stat-mini {
  text-align: center;
  padding: 10px;
  background: #f8fafc;
  border-radius: 8px;
}

.stat-mini-number {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 2px;
}

.stat-mini-label {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
  text-transform: uppercase;
}

.lab-action {
  display: block;
  text-align: center;
  padding: 10px 15px;
  background: #3b82f6;
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  transition: background 0.3s ease;
}

.lab-action:hover {
  background: #2563eb;
}

.empty-labs {
  grid-column: 1 / -1;
  text-align: center;
  padding: 40px;
  color: #94a3b8;
}

.empty-labs-icon {
  font-size: 48px;
  margin-bottom: 10px;
  opacity: 0.3;
}

/* NEW: Calendar Modal Styles */
.calendar-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.5);
  z-index: 1000;
  align-items: center;
  justify-content: center;
}

.calendar-modal.active {
  display: flex;
}

.calendar-container {
  background: white;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
  max-width: 900px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    transform: translateY(50px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.calendar-header {
  padding: 25px 30px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 16px 16px 0 0;
}

.calendar-header h2 {
  font-size: 24px;
  color: white;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 10px;
}

.close-calendar {
  background: rgba(255,255,255,0.2);
  border: none;
  width: 35px;
  height: 35px;
  border-radius: 8px;
  font-size: 20px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
}

.close-calendar:hover {
  background: rgba(255,255,255,0.3);
  transform: rotate(90deg);
}

.calendar-body {
  padding: 30px;
}

.calendar-nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.calendar-nav h3 {
  font-size: 20px;
  color: #1e293b;
  font-weight: 700;
}

.calendar-nav-buttons {
  display: flex;
  gap: 10px;
}

.calendar-nav button {
  background: #f1f5f9;
  border: none;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 14px;
  cursor: pointer;
  font-weight: 600;
  color: #475569;
  transition: all 0.3s ease;
}

.calendar-nav button:hover {
  background: #667eea;
  color: white;
  transform: translateY(-2px);
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 10px;
}

.calendar-day-header {
  text-align: center;
  padding: 12px;
  font-size: 13px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
}

.calendar-day {
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  border: 2px solid transparent;
  background: #f8fafc;
}

.calendar-day:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.calendar-day.other-month {
  color: #cbd5e1;
  cursor: default;
  background: transparent;
}

.calendar-day.other-month:hover {
  transform: none;
  box-shadow: none;
}

.calendar-day.today {
  border-color: #3b82f6;
  background: #dbeafe;
  color: #3b82f6;
  font-weight: 700;
}

.calendar-day.has-bookings {
  background: #fef3c7;
  color: #92400e;
  font-weight: 700;
}

.calendar-day.has-bookings:hover .day-tooltip {
  display: block;
}

/* NEW: Tooltip styles */
.day-tooltip {
  display: none;
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  margin-top: 8px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px;
  min-width: 250px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.2);
  z-index: 10;
  text-align: left;
}

.day-tooltip h4 {
  font-size: 13px;
  color: #64748b;
  margin-bottom: 8px;
  text-transform: uppercase;
  font-weight: 700;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 5px;
}

.time-slot {
  padding: 8px 10px;
  border-radius: 6px;
  font-size: 13px;
  margin-bottom: 6px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 600;
}

.time-slot:last-child {
  margin-bottom: 0;
}

.time-slot.booked {
  background: #fee2e2;
  color: #991b1b;
  border-left: 3px solid #dc2626;
}

.time-slot.free {
  background: #d1fae5;
  color: #065f46;
  border-left: 3px solid #10b981;
}

.time-slot-time {
  font-weight: 600;
}

.time-slot-status {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 700;
  text-transform: uppercase;
}

.time-slot.booked .time-slot-status {
  background: #dc2626;
  color: white;
}

.time-slot.free .time-slot-status {
  background: #10b981;
  color: white;
}

.calendar-legend {
  display: flex;
  justify-content: center;
  gap: 25px;
  margin-top: 25px;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #64748b;
  font-weight: 600;
}

.legend-color {
  width: 20px;
  height: 20px;
  border-radius: 4px;
}

.legend-color.booked {
  background: #fee2e2;
  border: 2px solid #dc2626;
}

.legend-color.free {
  background: #d1fae5;
  border: 2px solid #10b981;
}

/* Bookings Section */
.bookings-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  overflow: hidden;
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
  
  .labs-grid {
    grid-template-columns: 1fr;
  }
  
  table th, table td {
    padding: 12px 15px;
    font-size: 13px;
  }
  
  .calendar-container {
    width: 95%;
    max-height: 85vh;
  }
  
  .calendar-grid {
    gap: 5px;
  }
  
  .calendar-day {
    font-size: 12px;
  }
  
  .day-tooltip {
    min-width: 200px;
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
    <li><a href="index.php" class="active"><span>📊</span> Dashboard</a></li>
    <li><a href="calendar.php"><span>📅</span> Calendar View</a></li>
    <li><a href="create.php"><span>➕</span> Book a Lab</a></li>
    <li><a href="my_bookings.php"><span>📋</span> My Bookings</a></li>
    <li><a href="feedback.php"><span>💬</span>Give Feedback</a></li>
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

  <!-- Stats Cards - ADDED CALENDAR CARD -->
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
    
    <!-- NEW CALENDAR CARD -->
    <div class="stat-card" onclick="openCalendar()" style="cursor: pointer;">
      <div class="stat-icon" title="Click to view calendar">📅</div>
      <div class="stat-info">
        <h3 style="cursor: pointer;">Calendar</h3>
        <p>View Lab Schedule</p>
      </div>
    </div>
  </div>

  <!-- NEW: Available Labs Section -->
  <div class="labs-section">
    <div class="section-header">
      <h2>🏢 Available Labs</h2>
      <a href="new labs.php" class="btn-primary">View All Labs</a>
    </div>
    
    <div class="labs-grid">
      <?php if ($labs_result->num_rows > 0): 
          while($lab = $labs_result->fetch_assoc()): 
              $available_percentage = $lab['total_computers'] > 0 ? 
                  round(($lab['available_computers'] / $lab['total_computers']) * 100) : 0;
              
              // Determine status color based on availability
              if ($available_percentage >= 70) {
                  $status_color = '#10b981';
                  $status_text = 'High Availability';
              } elseif ($available_percentage >= 30) {
                  $status_color = '#f59e0b';
                  $status_text = 'Moderate Availability';
              } else {
                  $status_color = '#ef4444';
                  $status_text = 'Low Availability';
              }
      ?>
      <div class="lab-card">
        <!-- Lab Header -->
        <div class="lab-header">
          <div class="lab-title">
            <h3><?= htmlspecialchars($lab['name']) ?></h3>
            <p>📍 <?= htmlspecialchars($lab['location']) ?></p>
          </div>
          <div class="lab-status" style="background: <?= $status_color ?>20; color: <?= $status_color ?>; border: 1px solid <?= $status_color ?>40;">
            <?= $status_text ?>
          </div>
        </div>
        
        <!-- Capacity Info -->
        <div class="capacity-info">
          <div class="capacity-row">
            <span class="capacity-label">Total Capacity:</span>
            <span class="capacity-value"><?= $lab['capacity'] ?> computers</span>
          </div>
          
          <!-- Availability Progress Bar -->
          <div style="margin-bottom: 8px;">
            <div class="capacity-row">
              <span class="capacity-label">Available Now:</span>
              <span class="capacity-value"><?= $lab['available_computers'] ?>/<?= $lab['total_computers'] ?></span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill" style="width: <?= $available_percentage ?>%; background: <?= $status_color ?>;"></div>
            </div>
          </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="stats-grid-mini">
          <div class="stat-mini">
            <div class="stat-mini-number" style="color: #3b82f6;"><?= $lab['available_computers'] ?></div>
            <div class="stat-mini-label">Available</div>
          </div>
          <div class="stat-mini">
            <div class="stat-mini-number" style="color: #8b5cf6;"><?= $lab['total_computers'] - $lab['available_computers'] ?></div>
            <div class="stat-mini-label">In Use</div>
          </div>
        </div>
        
        <!-- Quick Action Button -->
        <a href="create.php?lab=<?= $lab['id'] ?>" class="lab-action">
          🖥️ Book This Lab
        </a>
      </div>
      <?php endwhile; ?>
      <?php else: ?>
      <div class="empty-labs">
        <div class="empty-labs-icon">🏢</div>
        <h3 style="color: #64748b; margin-bottom: 10px;">No Labs Available</h3>
        <p>There are currently no labs configured in the system.</p>
      </div>
      <?php endif; ?>
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

<!-- NEW: Calendar Modal -->
<div class="calendar-modal" id="calendarModal">
  <div class="calendar-container">
    <div class="calendar-header">
      <h2>📅 Lab Booking Calendar</h2>
      <button class="close-calendar" onclick="closeCalendar()">✕</button>
    </div>
    
    <div class="calendar-body">
      <div class="calendar-nav">
        <h3 id="currentMonthYear"></h3>
        <div class="calendar-nav-buttons">
          <button onclick="previousMonth()">← Previous</button>
          <button onclick="nextMonth()">Next →</button>
        </div>
      </div>
      
      <div class="calendar-grid">
        <div class="calendar-day-header">Sun</div>
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>
      </div>
      <div id="calendarDays"></div>
      
      <div class="calendar-legend">
        <div class="legend-item">
          <div class="legend-color booked"></div>
          <span>Booked Time Slots</span>
        </div>
        <div class="legend-item">
          <div class="legend-color free"></div>
          <span>Free Time Slots</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- NEW: Calendar JavaScript -->
<script>
// Calendar data from PHP
const calendarData = <?= json_encode($calendar_data) ?>;
let currentDate = new Date();

// Working hours for the lab (8 AM to 8 PM)
const workingHours = [
  { start: '08:00:00', end: '09:00:00', label: '8:00 AM - 9:00 AM' },
  { start: '09:00:00', end: '10:00:00', label: '9:00 AM - 10:00 AM' },
  { start: '10:00:00', end: '11:00:00', label: '10:00 AM - 11:00 AM' },
  { start: '11:00:00', end: '12:00:00', label: '11:00 AM - 12:00 PM' },
  { start: '12:00:00', end: '13:00:00', label: '12:00 PM - 1:00 PM' },
  { start: '13:00:00', end: '14:00:00', label: '1:00 PM - 2:00 PM' },
  { start: '14:00:00', end: '15:00:00', label: '2:00 PM - 3:00 PM' },
  { start: '15:00:00', end: '16:00:00', label: '3:00 PM - 4:00 PM' },
  { start: '16:00:00', end: '17:00:00', label: '4:00 PM - 5:00 PM' },
  { start: '17:00:00', end: '18:00:00', label: '5:00 PM - 6:00 PM' },
  { start: '18:00:00', end: '19:00:00', label: '6:00 PM - 7:00 PM' },
  { start: '19:00:00', end: '20:00:00', label: '7:00 PM - 8:00 PM' }
];

function openCalendar() {
  document.getElementById('calendarModal').classList.add('active');
  renderCalendar();
}

function closeCalendar() {
  document.getElementById('calendarModal').classList.remove('active');
}

// Close modal when clicking outside
document.getElementById('calendarModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeCalendar();
  }
});

function renderCalendar() {
  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();
  
  // Update month/year display
  const monthNames = ["January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"];
  document.getElementById('currentMonthYear').textContent = `${monthNames[month]} ${year}`;
  
  // Get first day of month and number of days
  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const daysInPrevMonth = new Date(year, month, 0).getDate();
  
  const calendarDaysContainer = document.getElementById('calendarDays');
  calendarDaysContainer.innerHTML = '';
  calendarDaysContainer.className = 'calendar-grid';
  calendarDaysContainer.style.gridColumn = '1 / -1';
  
  const today = new Date();
  
  // Previous month days
  for (let i = firstDay - 1; i >= 0; i--) {
    const day = daysInPrevMonth - i;
    const dayDiv = document.createElement('div');
    dayDiv.className = 'calendar-day other-month';
    dayDiv.textContent = day;
    calendarDaysContainer.appendChild(dayDiv);
  }
  
  // Current month days
  for (let day = 1; day <= daysInMonth; day++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const dayDiv = document.createElement('div');
    dayDiv.className = 'calendar-day';
    
    // Check if it's today
    if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
      dayDiv.classList.add('today');
    }
    
    dayDiv.textContent = day;
    
    // Check if this date has bookings
    if (calendarData[dateStr]) {
      dayDiv.classList.add('has-bookings');
      
      // Create tooltip with time slots
      const tooltip = document.createElement('div');
      tooltip.className = 'day-tooltip';
      
      const tooltipTitle = document.createElement('h4');
      tooltipTitle.textContent = formatDate(dateStr);
      tooltip.appendChild(tooltipTitle);
      
      // Get all time slots and check which are booked
      const bookedSlots = getBookedSlots(dateStr);
      
      workingHours.forEach(slot => {
        const slotDiv = document.createElement('div');
        const isBooked = isTimeSlotBooked(slot, bookedSlots);
        
        slotDiv.className = `time-slot ${isBooked ? 'booked' : 'free'}`;
        
        const timeSpan = document.createElement('span');
        timeSpan.className = 'time-slot-time';
        timeSpan.textContent = slot.label;
        
        const statusSpan = document.createElement('span');
        statusSpan.className = 'time-slot-status';
        statusSpan.textContent = isBooked ? 'Booked' : 'Free';
        
        slotDiv.appendChild(timeSpan);
        slotDiv.appendChild(statusSpan);
        tooltip.appendChild(slotDiv);
      });
      
      dayDiv.appendChild(tooltip);
    }
    
    calendarDaysContainer.appendChild(dayDiv);
  }
  
  // Next month days to fill the grid
  const totalCells = calendarDaysContainer.children.length;
  const remainingCells = 42 - totalCells; // 6 rows × 7 days
  
  for (let day = 1; day <= remainingCells; day++) {
    const dayDiv = document.createElement('div');
    dayDiv.className = 'calendar-day other-month';
    dayDiv.textContent = day;
    calendarDaysContainer.appendChild(dayDiv);
  }
}

function getBookedSlots(dateStr) {
  if (!calendarData[dateStr]) return [];
  return calendarData[dateStr];
}

function isTimeSlotBooked(slot, bookedSlots) {
  // Check if any booking overlaps with this time slot
  return bookedSlots.some(booking => {
    const bookingStart = booking.start_time;
    const bookingEnd = booking.end_time;
    
    // Check if there's any overlap
    return (bookingStart < slot.end && bookingEnd > slot.start) ||
           (bookingStart >= slot.start && bookingStart < slot.end) ||
           (bookingEnd > slot.start && bookingEnd <= slot.end);
  });
}

function formatDate(dateStr) {
  const date = new Date(dateStr + 'T00:00:00');
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  return date.toLocaleDateString('en-US', options);
}

function previousMonth() {
  currentDate.setMonth(currentDate.getMonth() - 1);
  renderCalendar();
}

function nextMonth() {
  currentDate.setMonth(currentDate.getMonth() + 1);
  renderCalendar();
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeCalendar();
  }
});
</script>

</body>
</html>