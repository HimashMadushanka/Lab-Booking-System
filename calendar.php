<?php
// calendar.php - Enhanced Calendar View for All Bookings
session_start();
require 'db.php';

// Check if logged in
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'User'; // Fixed undefined user_name

// --- Fetch statistics ---
$total_computers = $mysqli->query("SELECT COUNT(*) AS cnt FROM computers WHERE status='available'")->fetch_assoc()['cnt'];
$total_bookings = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE user_id=$user_id")->fetch_assoc()['cnt'];
$approved_bookings = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE user_id=$user_id AND status='approved'")->fetch_assoc()['cnt'];

// Get selected date or default to today
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

// Get month and year from GET parameters if set
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('m', strtotime($selected_date));
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y', strtotime($selected_date));

// Validate month and year
if ($selected_month < 1 || $selected_month > 12) {
    $selected_month = date('m', strtotime($selected_date));
}
if ($selected_year < 2000 || $selected_year > 2100) {
    $selected_year = date('Y', strtotime($selected_date));
}

// Update selected_date based on month/year selection
if (isset($_GET['month']) || isset($_GET['year'])) {
    $selected_date = date('Y-m-d', strtotime("$selected_year-$selected_month-01"));
}

// Get month navigation parameters
$current_view_date = new DateTime($selected_date);
$prev_month = clone $current_view_date;
$prev_month->modify('-1 month');
$next_month = clone $current_view_date;
$next_month->modify('+1 month');

// Get bookings for the selected date
$stmt = $mysqli->prepare("
    SELECT b.*, l.name as lab_name, c.code as computer_code, u.name as user_name 
    FROM bookings b 
    JOIN computers c ON c.id = b.computer_id 
    JOIN labs l ON l.id = c.lab_id
    JOIN users u ON u.id = b.user_id
    WHERE b.date = ? 
    ORDER BY b.start_time
");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$bookings = $stmt->get_result();

// Get bookings count for each day in current month for calendar
$current_month = date('Y-m', strtotime($selected_date));
$bookings_count_stmt = $mysqli->prepare("
    SELECT date, COUNT(*) as booking_count,
           SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved_count,
           SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count,
           SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM bookings 
    WHERE DATE_FORMAT(date, '%Y-%m') = ? 
    GROUP BY date
");
$bookings_count_stmt->bind_param("s", $current_month);
$bookings_count_stmt->execute();
$bookings_count_result = $bookings_count_stmt->get_result();

$bookings_per_day = [];
while($row = $bookings_count_result->fetch_assoc()) {
    $bookings_per_day[$row['date']] = $row;
}

// Get detailed bookings for calendar
$month_bookings_stmt = $mysqli->prepare("
    SELECT b.date, b.start_time, b.end_time, b.status, 
           l.name as lab_name, 
           c.code as computer_code,
           u.name as user_name, 
           b.user_id as booking_user_id
    FROM bookings b 
    JOIN computers c ON c.id = b.computer_id 
    JOIN labs l ON l.id = c.lab_id
    JOIN users u ON u.id = b.user_id
    WHERE DATE_FORMAT(b.date, '%Y-%m') = ? 
    ORDER BY b.date, b.start_time
");
$month_bookings_stmt->bind_param("s", $current_month);
$month_bookings_stmt->execute();
$month_bookings_result = $month_bookings_stmt->get_result();

$detailed_bookings = [];
while($row = $month_bookings_result->fetch_assoc()) {
    $date = $row['date'];
    if (!isset($detailed_bookings[$date])) {
        $detailed_bookings[$date] = [];
    }
    $detailed_bookings[$date][] = $row;
}

// Generate year and month options for dropdowns
$current_year = date('Y');
$years = range($current_year - 5, $current_year + 5);
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Calendar View | LabEase</title>
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
  border-left-color: #f59e0b;
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

.stat-card:nth-child(3) .stat-icon {
  background: #fef3c7;
  color: #f59e0b;
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

/* Calendar Section */
.calendar-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  overflow: hidden;
  margin-bottom: 30px;
}

.calendar-header-bar {
  padding: 25px 30px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
}

.calendar-title {
  font-size: 24px;
  color: #1e293b;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 10px;
}

.calendar-controls {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-wrap: wrap;
}

.month-navigator {
  display: flex;
  align-items: center;
  gap: 15px;
  background: #f8fafc;
  padding: 8px 15px;
  border-radius: 8px;
}

.nav-btn {
  background: white;
  border: 1px solid #e2e8f0;
  color: #475569;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
  text-decoration: none;
  font-weight: 600;
  font-size: 18px;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
}

.nav-btn:hover {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

.current-month {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  min-width: 140px;
  text-align: center;
}

.today-btn {
  padding: 10px 20px;
  background: #f1f5f9;
  color: #475569;
  text-decoration: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.3s ease;
  border: 1px solid #e2e8f0;
}

.today-btn:hover {
  background: #e2e8f0;
  color: #1e293b;
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
  border: none;
  cursor: pointer;
}

.btn-primary:hover {
  background: #2563eb;
}

/* Enhanced Year/Month Selector */
.year-month-selector {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-left: 15px;
  position: relative;
}

.year-month-selector select {
  padding: 8px 35px 8px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: white;
  color: #1e293b;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23475569' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
}

.year-month-selector select:hover {
  border-color: #3b82f6;
}

.year-month-selector select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Quick Year Jump */
.year-jump-btn {
  padding: 8px 12px;
  background: white;
  border: 1px solid #e2e8f0;
  color: #475569;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  transition: all 0.2s ease;
}

.year-jump-btn:hover {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

/* View Toggle */
.view-toggle {
  display: flex;
  gap: 5px;
  background: #f1f5f9;
  padding: 4px;
  border-radius: 8px;
}

.view-btn {
  padding: 8px 16px;
  background: transparent;
  border: none;
  color: #64748b;
  font-size: 13px;
  font-weight: 600;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.view-btn.active {
  background: white;
  color: #3b82f6;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Calendar Grid */
.calendar-wrapper {
  padding: 25px;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 12px;
}

.calendar-day-header {
  text-align: center;
  font-weight: 700;
  color: #64748b;
  padding: 12px 0;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.calendar-day {
  min-height: 130px;
  border: 2px solid #e5e7eb;
  border-radius: 10px;
  padding: 10px;
  cursor: pointer;
  transition: all 0.3s ease;
  background: white;
  overflow: hidden;
  position: relative;
  display: flex;
  flex-direction: column;
}

.calendar-day:hover {
  border-color: #3b82f6;
  transform: translateY(-3px);
  box-shadow: 0 8px 16px rgba(59, 130, 246, 0.15);
}

.calendar-day.today {
  border-color: #f59e0b;
  background: #fffbeb;
}

.calendar-day.selected {
  background: #dbeafe;
  border-color: #3b82f6;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.calendar-day.other-month {
  background: #f9fafb;
  opacity: 0.5;
}

.calendar-day.other-month .day-number {
  color: #9ca3af;
}

.calendar-day.has-bookings {
  background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%);
}

.day-number {
  font-weight: 700;
  font-size: 16px;
  color: #1e293b;
  margin-bottom: 8px;
}

.calendar-day.today .day-number {
  background: #f59e0b;
  color: white;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}

.day-bookings {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.booking-preview {
  background: #3b82f6;
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.4;
}

.booking-preview.status-pending {
  background: #f59e0b;
}

.booking-preview.status-rejected {
  background: #ef4444;
}

.booking-count-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: #3b82f6;
  color: white;
  border-radius: 12px;
  padding: 2px 8px;
  font-size: 11px;
  font-weight: 700;
}

/* Status Mini Indicators */
.day-status-indicators {
  display: flex;
  gap: 3px;
  margin-top: 4px;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.status-dot.approved {
  background: #10b981;
}

.status-dot.pending {
  background: #f59e0b;
}

.status-dot.rejected {
  background: #ef4444;
}

.more-bookings {
  font-size: 10px;
  color: #6b7280;
  text-align: center;
  padding: 3px;
  background: #f3f4f6;
  border-radius: 3px;
  margin-top: 2px;
}

/* Filter Pills */
.filter-pills {
  display: flex;
  gap: 8px;
  padding: 15px 30px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  flex-wrap: wrap;
}

.filter-pill {
  padding: 6px 12px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  color: #64748b;
}

.filter-pill:hover {
  border-color: #3b82f6;
  color: #3b82f6;
}

.filter-pill.active {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

/* Legend */
.calendar-legend {
  padding: 20px 30px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  gap: 25px;
  flex-wrap: wrap;
  background: #f8fafc;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #64748b;
}

.legend-color {
  width: 24px;
  height: 24px;
  border-radius: 6px;
  border: 2px solid;
}

.legend-color.today {
  background: #fffbeb;
  border-color: #f59e0b;
}

.legend-color.selected {
  background: #dbeafe;
  border-color: #3b82f6;
}

.legend-color.has-bookings {
  background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%);
  border-color: #3b82f6;
}

/* Bookings List */
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

.section-stats {
  display: flex;
  gap: 15px;
  font-size: 13px;
}

.section-stat {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  background: #f8fafc;
  border-radius: 6px;
}

.section-stat .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.section-stat.approved .dot {
  background: #10b981;
}

.section-stat.pending .dot {
  background: #f59e0b;
}

.section-stat.rejected .dot {
  background: #ef4444;
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
  
  .calendar-grid {
    gap: 8px;
  }
  
  .calendar-day {
    min-height: 110px;
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
  
  .calendar-header-bar {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .calendar-controls {
    width: 100%;
    justify-content: space-between;
  }
  
  .calendar-grid {
    gap: 6px;
  }
  
  .calendar-day {
    min-height: 90px;
    padding: 6px;
  }
  
  .day-number {
    font-size: 14px;
  }
  
  .booking-preview {
    font-size: 9px;
    padding: 3px 6px;
  }
  
  table th, table td {
    padding: 12px 15px;
    font-size: 13px;
  }
  
  .year-month-selector {
    margin-left: 0;
    margin-top: 10px;
    width: 100%;
    justify-content: center;
  }
}

@media (max-width: 640px) {
  .calendar-day {
    min-height: 75px;
  }
  
  .day-bookings {
    display: none;
  }
  
  .booking-count-badge {
    position: static;
    display: inline-block;
    margin-top: 4px;
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
    <li><a href="dashboard.php"><span>📊</span> Dashboard</a></li>
    <li><a href="calendar.php" class="active"><span>📅</span> Calendar View</a></li>
    <li><a href="create.php"><span>➕</span> Book a Lab</a></li>
    <li><a href="my_bookings.php"><span>📋</span> My Bookings</a></li>
    <li><a href="feedback.php"><span>💬</span> Give Feedback</a></li>
       <li><a href="logout.php">🚪 Logout</a></li>
  </ul>
  
  <div class="logout-btn">
    <a href="logout.php">Logout</a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content">
  
  <!-- Top Bar -->
  <div class="top-bar">
    <h1>📅 Calendar View</h1>
    <div class="user-info">

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

  <!-- Calendar Section -->
  <div class="calendar-section">
    <div class="calendar-header-bar">
      <div class="calendar-title">
        Lab Booking Calendar
      </div>
      <div class="calendar-controls">
        <div class="month-navigator">
          <a href="?date=<?= $prev_month->format('Y-m-d') ?>" class="nav-btn" title="Previous Month">‹</a>
          <span class="current-month"><?= $current_view_date->format('F Y') ?></span>
          <a href="?date=<?= $next_month->format('Y-m-d') ?>" class="nav-btn" title="Next Month">›</a>
        </div>
        
        <!-- Enhanced Year/Month Selector with Quick Jump -->
        <div class="year-month-selector">
          <button class="year-jump-btn" onclick="jumpYears(-1)" title="Previous Year">‹‹</button>
          <select id="year-select" onchange="goToYearMonth()">
            <?php foreach($years as $year): ?>
              <option value="<?= $year ?>" <?= $year == $selected_year ? 'selected' : '' ?>>
                <?= $year ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select id="month-select" onchange="goToYearMonth()">
            <?php foreach($months as $num => $name): ?>
              <option value="<?= $num ?>" <?= $num == $selected_month ? 'selected' : '' ?>>
                <?= $name ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="year-jump-btn" onclick="jumpYears(1)" title="Next Year">››</button>
        </div>
        
        <a href="?date=<?= date('Y-m-d') ?>" class="today-btn">Today</a>
        <a href="create.php" class="btn-primary">+ New Booking</a>
      </div>
    </div>
    
    <!-- Filter Pills -->
    <div class="filter-pills">
      <div class="filter-pill active" data-filter="all">All Bookings</div>
      <div class="filter-pill" data-filter="approved">Approved Only</div>
      <div class="filter-pill" data-filter="pending">Pending Only</div>
      <div class="filter-pill" data-filter="my-bookings">My Bookings</div>
    </div>
    
    <div class="calendar-wrapper">
      <div class="calendar-grid">
        <!-- Calendar headers -->
        <div class="calendar-day-header">Sun</div>
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>
        
        <!-- Calendar days populated by JavaScript -->
        <div id="calendar-days" style="display: contents;"></div>
      </div>
    </div>
    
    <div class="calendar-legend">
      <div class="legend-item">
        <div class="legend-color today"></div>
        <span>Today</span>
      </div>
      <div class="legend-item">
        <div class="legend-color selected"></div>
        <span>Selected Date</span>
      </div>
      <div class="legend-item">
        <div class="legend-color has-bookings"></div>
        <span>Has Bookings</span>
      </div>
      <div class="legend-item">
        <div class="status-dot approved"></div>
        <span>Approved</span>
      </div>
      <div class="legend-item">
        <div class="status-dot pending"></div>
        <span>Pending</span>
      </div>
      <div class="legend-item">
        <div class="status-dot rejected"></div>
        <span>Rejected</span>
      </div>
    </div>
  </div>

  <!-- Bookings for Selected Date -->
  <div class="bookings-section">
    <div class="section-header">
      <h2>📋 Bookings for <?= date('F j, Y', strtotime($selected_date)) ?></h2>
      <?php
        $day_bookings = $bookings->fetch_all(MYSQLI_ASSOC);
        $approved = count(array_filter($day_bookings, fn($b) => $b['status'] == 'approved'));
        $pending = count(array_filter($day_bookings, fn($b) => $b['status'] == 'pending'));
        $rejected = count(array_filter($day_bookings, fn($b) => $b['status'] == 'rejected'));
      ?>
      <div class="section-stats">
        <div class="section-stat approved">
          <div class="dot"></div>
          <span><?= $approved ?> Approved</span>
        </div>
        <div class="section-stat pending">
          <div class="dot"></div>
          <span><?= $pending ?> Pending</span>
        </div>
        <div class="section-stat rejected">
          <div class="dot"></div>
          <span><?= $rejected ?> Rejected</span>
        </div>
      </div>
    </div>
    
    <div class="table-wrapper">
      <?php if(count($day_bookings) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Computer Lab</th>
              <th>Booked By</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($day_bookings as $b): ?>
            <tr>
              <td><strong><?= htmlspecialchars($b['lab_name']) ?></strong></td>
              <td>
                <?= htmlspecialchars($b['user_name']) ?>
                <?php if($b['user_id'] == $user_id): ?>
                  <span style="color: #3b82f6; font-size: 12px; font-weight: 600;">(You)</span>
                <?php endif; ?>
              </td>
              <td><?= date('g:i A', strtotime($b['start_time'])) ?></td>
              <td><?= date('g:i A', strtotime($b['end_time'])) ?></td>
              <td>
                <span class="status-badge status-<?= strtolower($b['status']) ?>">
                  <?= htmlspecialchars($b['status']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-state-icon">📭</div>
          <p>No bookings found for <?= date('F j, Y', strtotime($selected_date)) ?></p>
          <a href="create.php">Book a Lab Now</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
// Enhanced JavaScript for calendar generation with filtering
let currentFilter = 'all';

document.addEventListener('DOMContentLoaded', function() {
    const selectedDate = new Date('<?= $selected_date ?>');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const calendarDays = document.getElementById('calendar-days');
    
    // Get first and last day of month
    const firstDay = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
    const lastDay = new Date(selectedDate.getFullYear(), selectedDate.getMonth() + 1, 0);
    const daysInMonth = lastDay.getDate();
    const firstDayOfWeek = firstDay.getDay();
    
    // Get previous month's last days
    const prevMonthLastDay = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 0);
    const prevMonthDays = prevMonthLastDay.getDate();
    
    // PHP data
    const bookingsPerDay = <?= json_encode($bookings_per_day) ?>;
    const detailedBookings = <?= json_encode($detailed_bookings) ?>;
    const currentUserId = <?= $user_id ?>;
    
    // Filter functionality
    const filterPills = document.querySelectorAll('.filter-pill');
    filterPills.forEach(pill => {
        pill.addEventListener('click', function() {
            filterPills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.getAttribute('data-filter');
            renderCalendar();
        });
    });
    
    function renderCalendar() {
        // Clear calendar
        calendarDays.innerHTML = '';
        
        // Add previous month's trailing days
        for (let i = firstDayOfWeek - 1; i >= 0; i--) {
            const prevDay = prevMonthDays - i;
            const dayElement = createDayElement(prevDay, true);
            calendarDays.appendChild(dayElement);
        }
        
        // Add current month's days
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${selectedDate.getFullYear()}-${String(selectedDate.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), day);
            dayDate.setHours(0, 0, 0, 0);
            
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            
            // Check if today
            if (dayDate.getTime() === today.getTime()) {
                dayElement.classList.add('today');
            }
            
            // Check if selected
            if (dateStr === '<?= $selected_date ?>') {
                dayElement.classList.add('selected');
            }
            
            // Filter bookings based on current filter
            let filteredBookings = detailedBookings[dateStr] || [];
            if (currentFilter === 'approved') {
                filteredBookings = filteredBookings.filter(b => b.status === 'approved');
            } else if (currentFilter === 'pending') {
                filteredBookings = filteredBookings.filter(b => b.status === 'pending');
            } else if (currentFilter === 'my-bookings') {
                filteredBookings = filteredBookings.filter(b => b.booking_user_id == currentUserId);
            }
            
            const bookingCount = filteredBookings.length;
            
            // Check if has bookings
            if (bookingCount > 0) {
                dayElement.classList.add('has-bookings');
            }
            
            // Click handler
            dayElement.onclick = function() {
                window.location.href = `calendar.php?date=${dateStr}`;
            };
            
            // Day number
            const dayNumber = document.createElement('div');
            dayNumber.className = 'day-number';
            dayNumber.textContent = day;
            dayElement.appendChild(dayNumber);
            
            // Booking count badge
            if (bookingCount > 0) {
                const badge = document.createElement('div');
                badge.className = 'booking-count-badge';
                badge.textContent = bookingCount;
                dayElement.appendChild(badge);
            }
            
            // Day bookings container
            const dayBookings = document.createElement('div');
            dayBookings.className = 'day-bookings';
            
            if (filteredBookings.length > 0) {
                const displayLimit = 3;
                
                // Show first few bookings
                filteredBookings.slice(0, displayLimit).forEach(booking => {
                    const bookingPreview = document.createElement('div');
                    bookingPreview.className = `booking-preview status-${booking.status.toLowerCase()}`;
                    
                    const startTime = formatTime(booking.start_time);
                    const isUserBooking = booking.booking_user_id == currentUserId;
                    
                    bookingPreview.textContent = `${booking.lab_name} ${startTime}${isUserBooking ? ' ★' : ''}`;
                    bookingPreview.title = `${booking.lab_name}: ${startTime} - ${formatTime(booking.end_time)}\nBooked by: ${booking.user_name}\nStatus: ${booking.status}`;
                    
                    dayBookings.appendChild(bookingPreview);
                });
                
                // Show "more" indicator
                if (filteredBookings.length > displayLimit) {
                    const moreBookings = document.createElement('div');
                    moreBookings.className = 'more-bookings';
                    moreBookings.textContent = `+${filteredBookings.length - displayLimit} more`;
                    dayBookings.appendChild(moreBookings);
                }
            }
            
            // Add status indicators
            if (bookingsPerDay[dateStr]) {
                const indicators = document.createElement('div');
                indicators.className = 'day-status-indicators';
                
                const stats = bookingsPerDay[dateStr];
                if (stats.approved_count > 0) {
                    for (let i = 0; i < Math.min(stats.approved_count, 3); i++) {
                        const dot = document.createElement('div');
                        dot.className = 'status-dot approved';
                        indicators.appendChild(dot);
                    }
                }
                if (stats.pending_count > 0) {
                    for (let i = 0; i < Math.min(stats.pending_count, 3); i++) {
                        const dot = document.createElement('div');
                        dot.className = 'status-dot pending';
                        indicators.appendChild(dot);
                    }
                }
                if (stats.rejected_count > 0) {
                    for (let i = 0; i < Math.min(stats.rejected_count, 3); i++) {
                        const dot = document.createElement('div');
                        dot.className = 'status-dot rejected';
                        indicators.appendChild(dot);
                    }
                }
                
                dayElement.appendChild(indicators);
            }
            
            dayElement.appendChild(dayBookings);
            calendarDays.appendChild(dayElement);
        }
        
        // Add next month's leading days
        const totalCells = firstDayOfWeek + daysInMonth;
        const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
        
        for (let day = 1; day <= remainingCells; day++) {
            const dayElement = createDayElement(day, true);
            calendarDays.appendChild(dayElement);
        }
    }
    
    // Helper function to create day element for other months
    function createDayElement(day, isOtherMonth) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        if (isOtherMonth) {
            dayElement.classList.add('other-month');
        }
        
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = day;
        dayElement.appendChild(dayNumber);
        
        return dayElement;
    }
    
    // Helper function to format time
    function formatTime(timeStr) {
        const [hours, minutes] = timeStr.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        return `${displayHour}:${minutes}${ampm}`;
    }
    
    // Initial render
    renderCalendar();
});

// Function to navigate to selected year and month
function goToYearMonth() {
    const year = document.getElementById('year-select').value;
    const month = document.getElementById('month-select').value;
    window.location.href = `calendar.php?year=${year}&month=${month}`;
}

// Quick year jump function
function jumpYears(direction) {
    const yearSelect = document.getElementById('year-select');
    const currentYear = parseInt(yearSelect.value);
    const newYear = currentYear + direction;
    
    // Check if new year exists in options
    const options = Array.from(yearSelect.options);
    const yearExists = options.some(option => parseInt(option.value) === newYear);
    
    if (yearExists) {
        yearSelect.value = newYear;
        goToYearMonth();
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Alt + Left Arrow: Previous month
    if (e.altKey && e.key === 'ArrowLeft') {
        e.preventDefault();
        document.querySelector('.month-navigator .nav-btn:first-child').click();
    }
    // Alt + Right Arrow: Next month
    else if (e.altKey && e.key === 'ArrowRight') {
        e.preventDefault();
        document.querySelector('.month-navigator .nav-btn:last-child').click();
    }
    // Alt + T: Go to today
    else if (e.altKey && e.key === 't') {
        e.preventDefault();
        document.querySelector('.today-btn').click();
    }
});
</script>

</body>
</html>