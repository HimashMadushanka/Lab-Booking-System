<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require 'db.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Initialize variables
$total_bookings = 0;
$approved_bookings = 0;
$upcoming = null;
$labs_result = null;
$calendar_data = [];

try {
    // Fetch statistics
    $result = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE user_id=$user_id");
    if ($result) {
        $total_bookings = $result->fetch_assoc()['cnt'];
    }

    $result = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE user_id=$user_id AND status='approved'");
    if ($result) {
        $approved_bookings = $result->fetch_assoc()['cnt'];
    }

    // Fetch upcoming bookings
    $stmt = $mysqli->prepare("
        SELECT b.*, c.code, l.name as lab_name 
        FROM bookings b 
        JOIN computers c ON c.id=b.computer_id 
        JOIN labs l ON c.lab_id=l.id
        WHERE b.user_id=? AND b.date >= CURDATE() 
        ORDER BY b.date, b.start_time LIMIT 5
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $upcoming = $stmt->get_result();
    }

    // Fetch all bookings for calendar
    $calendar_bookings_query = "
        SELECT b.date, b.start_time, b.end_time, b.status, c.code as computer_code
        FROM bookings b
        JOIN computers c ON c.id = b.computer_id
        WHERE b.user_id = $user_id 
        AND b.date >= DATE_FORMAT(NOW(), '%Y-%m-01')
        AND b.date <= LAST_DAY(DATE_ADD(NOW(), INTERVAL 2 MONTH))
        ORDER BY b.date, b.start_time
    ";
    $calendar_bookings_result = $mysqli->query($calendar_bookings_query);
    if ($calendar_bookings_result) {
        while($booking = $calendar_bookings_result->fetch_assoc()) {
            $date = $booking['date'];
            if(!isset($calendar_data[$date])) {
                $calendar_data[$date] = [];
            }
            $calendar_data[$date][] = $booking;
        }
    }

    // Fetch labs
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
    $labs_result = $mysqli->query($labs_query);

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Dashboard | LabEase</title>
<style>
/* Include all the CSS from your original dashboard.php here */
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
}

.sidebar-menu a:hover {
  background: rgba(255,255,255,0.05);
  color: white;
  padding-left: 30px;
}

.sidebar-menu a.active {
  background: #3b82f6;
  color: white;
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
  transition: background 0.3s ease;
}

.logout-btn a:hover {
  background: #b91c1c;
}

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
}

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

.stat-card:nth-child(1) { border-left-color: #3b82f6; }
.stat-card:nth-child(2) { border-left-color: #10b981; }
.stat-card:nth-child(3) { border-left-color: #8b5cf6; cursor: pointer; }

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
}

.stat-card:nth-child(1) .stat-icon { background: #dbeafe; color: #3b82f6; }
.stat-card:nth-child(2) .stat-icon { background: #d1fae5; color: #10b981; }
.stat-card:nth-child(3) .stat-icon { background: #ede9fe; color: #8b5cf6; }

.labs-section, .bookings-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  margin-bottom: 30px;
}

.section-header {
  padding: 25px 30px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.btn-primary {
  padding: 10px 20px;
  background: #3b82f6;
  color: white;
  text-decoration: none;
  border-radius: 8px;
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
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  transition: all 0.3s ease;
}

.lab-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

table th, table td {
  padding: 16px 25px;
  text-align: left;
  border-bottom: 1px solid #f1f5f9;
}

.status-badge {
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-approved { background: #d1fae5; color: #065f46; }

.empty-state {
  padding: 60px 30px;
  text-align: center;
}

/* Calendar Modal Styles */
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
  max-width: 900px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
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

.close-calendar {
  background: rgba(255,255,255,0.2);
  border: none;
  width: 35px;
  height: 35px;
  border-radius: 8px;
  cursor: pointer;
  color: white;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 10px;
  padding: 20px;
}

.calendar-day-header {
  text-align: center;
  padding: 12px;
  font-weight: 700;
  color: #64748b;
}

.calendar-day {
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  cursor: pointer;
  background: #f8fafc;
}

.calendar-day.today {
  border: 2px solid #3b82f6;
  background: #dbeafe;
}

.calendar-day.has-bookings {
  background: #fef3c7;
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
    <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
    <li><a href="calendar.php"><span>📅</span> Calendar View</a></li>
    <li><a href="create.php">➕ Book a Lab</a></li>
    <li><a href="my_bookings.php">📋 My Bookings</a></li>
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
    
    <div class="stat-card" onclick="openCalendar()" style="cursor: pointer;">
      <div class="stat-icon">📅</div>
      <div class="stat-info">
        <h3>Calendar</h3>
        <p>View Lab Schedule</p>
      </div>
    </div>
  </div>

  <!-- Available Labs Section -->
  <div class="labs-section">
    <div class="section-header">
      <h2>🏢 Available Labs</h2>
      <a href="create.php" class="btn-primary">Book a Lab</a>
    </div>
    
    <div class="labs-grid">
      <?php if ($labs_result && $labs_result->num_rows > 0): 
          while($lab = $labs_result->fetch_assoc()): 
              $available_percentage = $lab['total_computers'] > 0 ? 
                  round(($lab['available_computers'] / $lab['total_computers']) * 100) : 0;
              
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
        <div class="lab-header">
          <div class="lab-title">
            <h3><?= htmlspecialchars($lab['name']) ?></h3>
            <p>📍 <?= htmlspecialchars($lab['location']) ?></p>
          </div>
          <div class="lab-status" style="background: <?= $status_color ?>20; color: <?= $status_color ?>;">
            <?= $status_text ?>
          </div>
        </div>
        
        <div class="capacity-info">
          <div class="capacity-row">
            <span>Available Now:</span>
            <span><strong><?= $lab['available_computers'] ?>/<?= $lab['total_computers'] ?></strong></span>
          </div>
        </div>
        
        <a href="create.php?lab=<?= $lab['id'] ?>" class="btn-primary" style="display: block; text-align: center; margin-top: 15px;">
          🖥️ Book This Lab
        </a>
      </div>
      <?php endwhile; ?>
      <?php else: ?>
      <div class="empty-state">
        <div>🏢</div>
        <h3>No Labs Available</h3>
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
      <?php if($upcoming && $upcoming->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Lab</th>
              
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while($b = $upcoming->fetch_assoc()): ?>
            <tr>
              <td><strong><?= htmlspecialchars($b['lab_name']) ?></strong></td>
              
              <td><?= date('M d, Y', strtotime($b['date'])) ?></td>
              <td><?= date('g:i A', strtotime($b['start_time'])) ?> - <?= date('g:i A', strtotime($b['end_time'])) ?></td>
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
          <div>📭</div>
          <p>You don't have any upcoming bookings</p>
          <a href="create.php" class="btn-primary">Book Your First Lab</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Calendar Modal -->
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
      
      <div class="calendar-grid" id="calendarDays"></div>
    </div>
  </div>
</div>

<script>
const calendarData = <?= json_encode($calendar_data) ?>;
let currentDate = new Date();

function openCalendar() {
  document.getElementById('calendarModal').classList.add('active');
  renderCalendar();
}

function closeCalendar() {
  document.getElementById('calendarModal').classList.remove('active');
}

function renderCalendar() {
  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();
  
  const monthNames = ["January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"];
  document.getElementById('currentMonthYear').textContent = `${monthNames[month]} ${year}`;
  
  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  
  const calendarDaysContainer = document.getElementById('calendarDays');
  calendarDaysContainer.innerHTML = '';
  
  // Add day headers
  const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  days.forEach(day => {
    const dayHeader = document.createElement('div');
    dayHeader.className = 'calendar-day-header';
    dayHeader.textContent = day;
    calendarDaysContainer.appendChild(dayHeader);
  });
  
  const today = new Date();
  
  // Add days
  for (let day = 1; day <= daysInMonth; day++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const dayDiv = document.createElement('div');
    dayDiv.className = 'calendar-day';
    
    if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
      dayDiv.classList.add('today');
    }
    
    if (calendarData[dateStr]) {
      dayDiv.classList.add('has-bookings');
    }
    
    dayDiv.textContent = day;
    calendarDaysContainer.appendChild(dayDiv);
  }
}

function previousMonth() {
  currentDate.setMonth(currentDate.getMonth() - 1);
  renderCalendar();
}

function nextMonth() {
  currentDate.setMonth(currentDate.getMonth() + 1);
  renderCalendar();
}

// Close modal when clicking outside
document.getElementById('calendarModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeCalendar();
  }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeCalendar();
  }
});
</script>
</body>
</html>