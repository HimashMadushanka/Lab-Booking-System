<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Fetch comprehensive analytics data
$total_bookings = 0;
$approved_bookings = 0;
$pending_bookings = 0;
$rejected_bookings = 0;
$completed_sessions = 0;
$upcoming_bookings = 0;
$monthly_stats = [];
$lab_usage = [];
$hourly_usage = [];
$weekday_usage = [];

try {
    // 1. Fetch comprehensive statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_bookings,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_bookings,
            SUM(CASE WHEN status = 'approved' AND date < CURDATE() THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN status = 'approved' AND date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_bookings
        FROM bookings 
        WHERE user_id = ?
    ";
    $stats_stmt = $mysqli->prepare($stats_sql);
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    
    if ($stats_row = $stats_result->fetch_assoc()) {
        $total_bookings = $stats_row['total_bookings'];
        $approved_bookings = $stats_row['approved_bookings'];
        $pending_bookings = $stats_row['pending_bookings'];
        $rejected_bookings = $stats_row['rejected_bookings'];
        $completed_sessions = $stats_row['completed_sessions'];
        $upcoming_bookings = $stats_row['upcoming_bookings'];
    }

    // 2. Fetch monthly statistics for chart
    $monthly_sql = "
        SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM bookings 
        WHERE user_id = ? 
        AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month
    ";
    $monthly_stmt = $mysqli->prepare($monthly_sql);
    $monthly_stmt->bind_param("i", $user_id);
    $monthly_stmt->execute();
    $monthly_result = $monthly_stmt->get_result();
    
    while($month = $monthly_result->fetch_assoc()) {
        $monthly_stats[] = $month;
    }

    // 3. Fetch lab usage statistics
    $lab_usage_sql = "
        SELECT 
            l.name as lab_name,
            COUNT(b.id) as booking_count
        FROM bookings b
        JOIN computers c ON b.computer_id = c.id
        JOIN labs l ON c.lab_id = l.id
        WHERE b.user_id = ?
        GROUP BY l.id, l.name
        ORDER BY booking_count DESC
        LIMIT 8
    ";
    $lab_usage_stmt = $mysqli->prepare($lab_usage_sql);
    $lab_usage_stmt->bind_param("i", $user_id);
    $lab_usage_stmt->execute();
    $lab_usage_result = $lab_usage_stmt->get_result();
    
    while($lab = $lab_usage_result->fetch_assoc()) {
        $lab_usage[] = $lab;
    }

    // 4. Fetch hourly usage patterns
    $hourly_sql = "
        SELECT 
            HOUR(start_time) as hour,
            COUNT(*) as booking_count
        FROM bookings 
        WHERE user_id = ? AND status = 'approved'
        GROUP BY HOUR(start_time)
        ORDER BY hour
    ";
    $hourly_stmt = $mysqli->prepare($hourly_sql);
    $hourly_stmt->bind_param("i", $user_id);
    $hourly_stmt->execute();
    $hourly_result = $hourly_stmt->get_result();
    
    // Initialize all hours
    for($i = 8; $i <= 20; $i++) {
        $hourly_usage[$i] = 0;
    }
    
    while($hour = $hourly_result->fetch_assoc()) {
        $hourly_usage[$hour['hour']] = $hour['booking_count'];
    }

    // 5. Fetch weekday usage patterns
    $weekday_sql = "
        SELECT 
            DAYOFWEEK(date) as weekday,
            COUNT(*) as booking_count
        FROM bookings 
        WHERE user_id = ? AND status = 'approved'
        GROUP BY DAYOFWEEK(date)
        ORDER BY weekday
    ";
    $weekday_stmt = $mysqli->prepare($weekday_sql);
    $weekday_stmt->bind_param("i", $user_id);
    $weekday_stmt->execute();
    $weekday_result = $weekday_stmt->get_result();
    
    // Initialize all weekdays
    $weekday_names = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    for($i = 1; $i <= 7; $i++) {
        $weekday_usage[$weekday_names[$i]] = 0;
    }
    
    while($day = $weekday_result->fetch_assoc()) {
        $weekday_usage[$weekday_names[$day['weekday']]] = $day['booking_count'];
    }

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics Dashboard | LabEase</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: #f8f9fc;
  min-height: 100vh;
  color: #1f2937;
}

/* Sidebar */
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

/* Header */
.page-header {
  background: white;
  padding: 25px 30px;
  border-radius: 16px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
  margin-bottom: 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.page-header h1 {
  font-size: 28px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 5px;
}

.page-header p {
  color: #6b7280;
  font-size: 15px;
}

.back-link {
  color: #3b82f6;
  text-decoration: none;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #dbeafe;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.back-link:hover {
  background: #3b82f6;
  color: white;
}

/* Stats Overview */
.stats-overview {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: white;
  padding: 25px;
  border-radius: 16px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
  text-align: center;
  border-left: 4px solid #3b82f6;
  transition: transform 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-5px);
}

.stat-card.total { border-left-color: #3b82f6; }
.stat-card.completed { border-left-color: #10b981; }
.stat-card.upcoming { border-left-color: #f59e0b; }
.stat-card.rejected { border-left-color: #ef4444; }
.stat-card.pending { border-left-color: #8b5cf6; }
.stat-card.approved { border-left-color: #06b6d4; }

.stat-card h3 {
  font-size: 32px;
  font-weight: 800;
  color: #111827;
  margin-bottom: 8px;
}

.stat-card p {
  color: #6b7280;
  font-size: 14px;
  font-weight: 500;
}

/* Charts Grid */
.charts-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 25px;
  margin-bottom: 30px;
}

.chart-container {
  background: white;
  border-radius: 16px;
  padding: 25px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
  grid-column: span 1;
}

.chart-container.full-width {
  grid-column: 1 / -1;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.chart-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #1f2937;
  display: flex;
  align-items: center;
  gap: 10px;
}

.chart-wrapper {
  position: relative;
  height: 300px;
  width: 100%;
}

/* Insights Section */
.insights-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 25px;
  margin-bottom: 30px;
}

.insight-card {
  background: white;
  padding: 25px;
  border-radius: 16px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

.insight-card h4 {
  font-size: 16px;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.insight-list {
  list-style: none;
}

.insight-item {
  padding: 12px 0;
  border-bottom: 1px solid #f3f4f6;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.insight-item:last-child {
  border-bottom: none;
}

.insight-label {
  color: #6b7280;
  font-size: 14px;
}

.insight-value {
  font-weight: 600;
  color: #1f2937;
}

/* Export Section */
.export-section {
  background: white;
  padding: 25px;
  border-radius: 16px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
  text-align: center;
}

.export-buttons {
  display: flex;
  gap: 15px;
  justify-content: center;
  margin-top: 20px;
}

.export-btn {
  padding: 12px 24px;
  border: 2px solid #3b82f6;
  background: white;
  color: #3b82f6;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
}

.export-btn:hover {
  background: #3b82f6;
  color: white;
}

.export-btn.pdf {
  border-color: #ef4444;
  color: #ef4444;
}

.export-btn.pdf:hover {
  background: #ef4444;
  color: white;
}

.loading-indicator {
  display: none;
  margin-top: 15px;
  padding: 15px;
  background: #dbeafe;
  border-radius: 8px;
  border-left: 4px solid #3b82f6;
}

.loading-indicator p {
  color: #1e40af;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  justify-content: center;
}

/* Responsive */
@media (max-width: 1200px) {
  .charts-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
  }
  
  .main-content {
    margin-left: 0;
    padding: 20px;
  }
  
  .page-header {
    flex-direction: column;
    gap: 15px;
    text-align: center;
  }
  
  .stats-overview {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .export-buttons {
    flex-direction: column;
  }
}

@media (max-width: 480px) {
  .stats-overview {
    grid-template-columns: 1fr;
  }
  
  .chart-wrapper {
    height: 250px;
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
        <li><a href="calendar.php"><span>📅</span> Calendar View</a></li>
        <li><a href="create.php"><span>➕</span> Book a Lab</a></li>
        <li><a href="my_bookings.php"><span>📋</span> My Bookings</a></li>
        <li><a href="analytics.php" class="active"><span>📈</span> Analytics</a></li>
           <li><a href="notifications.php"><span>🔔</span> Notifications</a></li>
        <li><a href="feedback.php" ><span>💬</span> Give Feedback</a></li>
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
    <div>
      <h1>📈 Analytics Dashboard</h1>
      <p>Comprehensive insights into your lab booking patterns and usage</p>
    </div>
    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
  </div>

  <!-- Stats Overview -->
  <div class="stats-overview">
    <div class="stat-card total">
      <h3><?= $total_bookings ?></h3>
      <p>Total Bookings</p>
    </div>
    
    <div class="stat-card completed">
      <h3><?= $completed_sessions ?></h3>
      <p>Completed Sessions</p>
    </div>
    
    <div class="stat-card upcoming">
      <h3><?= $upcoming_bookings ?></h3>
      <p>Upcoming Bookings</p>
    </div>
    
    <div class="stat-card approved">
      <h3><?= $approved_bookings ?></h3>
      <p>Approved</p>
    </div>
    
    <div class="stat-card pending">
      <h3><?= $pending_bookings ?></h3>
      <p>Pending Approval</p>
    </div>
    
    <div class="stat-card rejected">
      <h3><?= $rejected_bookings ?></h3>
      <p>Rejected</p>
    </div>
  </div>

  <!-- Charts Grid -->
  <div class="charts-grid">
    <!-- Booking Trends Chart -->
    <div class="chart-container full-width">
      <div class="chart-header">
        <h3>📊 Booking Trends (Last 6 Months)</h3>
      </div>
      <div class="chart-wrapper">
        <canvas id="bookingTrendsChart"></canvas>
      </div>
    </div>

    <!-- Status Distribution -->
    <div class="chart-container">
      <div class="chart-header">
        <h3>📈 Status Distribution</h3>
      </div>
      <div class="chart-wrapper">
        <canvas id="statusDistributionChart"></canvas>
      </div>
    </div>

    <!-- Lab Usage -->
    <div class="chart-container">
      <div class="chart-header">
        <h3>🏢 Most Used Labs</h3>
      </div>
      <div class="chart-wrapper">
        <canvas id="labUsageChart"></canvas>
      </div>
    </div>

    <!-- Hourly Usage -->
    <div class="chart-container">
      <div class="chart-header">
        <h3>🕐 Hourly Booking Patterns</h3>
      </div>
      <div class="chart-wrapper">
        <canvas id="hourlyUsageChart"></canvas>
      </div>
    </div>

    <!-- Weekday Usage -->
    <div class="chart-container">
      <div class="chart-header">
        <h3>📅 Weekday Distribution</h3>
      </div>
      <div class="chart-wrapper">
        <canvas id="weekdayUsageChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Insights Section -->
  <div class="insights-grid">
    <!-- Popular Labs -->
    <div class="insight-card">
      <h4>🏆 Most Booked Labs</h4>
      <ul class="insight-list">
        <?php foreach(array_slice($lab_usage, 0, 5) as $lab): ?>
        <li class="insight-item">
          <span class="insight-label"><?= htmlspecialchars($lab['lab_name']) ?></span>
          <span class="insight-value"><?= $lab['booking_count'] ?> bookings</span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- Booking Hours -->
    <div class="insight-card">
      <h4>⏰ Preferred Booking Times</h4>
      <ul class="insight-list">
        <?php 
        arsort($hourly_usage);
        $top_hours = array_slice($hourly_usage, 0, 5, true);
        foreach($top_hours as $hour => $count): 
            if($count > 0):
        ?>
        <li class="insight-item">
          <span class="insight-label"><?= $hour ?>:00 - <?= $hour+1 ?>:00</span>
          <span class="insight-value"><?= $count ?> sessions</span>
        </li>
        <?php endif; endforeach; ?>
      </ul>
    </div>

    <!-- Success Rate -->
    <div class="insight-card">
      <h4>📊 Booking Success Metrics</h4>
      <ul class="insight-list">
        <li class="insight-item">
          <span class="insight-label">Approval Rate</span>
          <span class="insight-value"><?= $total_bookings > 0 ? round(($approved_bookings/$total_bookings)*100) : 0 ?>%</span>
        </li>
        <li class="insight-item">
          <span class="insight-label">Completion Rate</span>
          <span class="insight-value"><?= $approved_bookings > 0 ? round(($completed_sessions/$approved_bookings)*100) : 0 ?>%</span>
        </li>
        <li class="insight-item">
          <span class="insight-label">Rejection Rate</span>
          <span class="insight-value"><?= $total_bookings > 0 ? round(($rejected_bookings/$total_bookings)*100) : 0 ?>%</span>
        </li>
        <li class="insight-item">
          <span class="insight-label">Avg Monthly</span>
          <span class="insight-value"><?= count($monthly_stats) > 0 ? round(array_sum(array_column($monthly_stats, 'total'))/count($monthly_stats)) : 0 ?> bookings</span>
        </li>
      </ul>
    </div>
  </div>

  <!-- Export Section -->
  <div class="export-section">
    <h3>📤 Export Analytics Report</h3>
    <p>Download your booking analytics for offline analysis or reporting</p>
    <div class="export-buttons">
      <button class="export-btn" onclick="exportToCSV()">
        📄 Export as CSV
      </button>
      <button class="export-btn pdf" onclick="exportToPDF()">
        📊 Export as PDF
      </button>
      <button class="export-btn" onclick="printAnalytics()">
        🖨️ Print Report
      </button>
    </div>
    
    <!-- Loading indicator -->
    <div id="pdfLoading" class="loading-indicator">
      <p>🔄 Generating PDF report... This may take a few seconds.</p>
    </div>
  </div>

</div>

<script>
// Initialize Charts
document.addEventListener('DOMContentLoaded', function() {
    // Booking Trends Chart
    const trendsCtx = document.getElementById('bookingTrendsChart').getContext('2d');
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(function($m) { 
                return date('M Y', strtotime($m['month'] . '-01')); 
            }, $monthly_stats)) ?>,
            datasets: [
                {
                    label: 'Total Bookings',
                    data: <?= json_encode(array_column($monthly_stats, 'total')) ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3
                },
                {
                    label: 'Approved',
                    data: <?= json_encode(array_column($monthly_stats, 'approved')) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Status Distribution Chart
    const statusCtx = document.getElementById('statusDistributionChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Upcoming', 'Pending', 'Rejected'],
            datasets: [{
                data: [
                    <?= $completed_sessions ?>,
                    <?= $upcoming_bookings ?>,
                    <?= $pending_bookings ?>,
                    <?= $rejected_bookings ?>
                ],
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                borderWidth: 3,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Lab Usage Chart
    const labCtx = document.getElementById('labUsageChart').getContext('2d');
    new Chart(labCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($lab_usage, 'lab_name')) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode(array_column($lab_usage, 'booking_count')) ?>,
                backgroundColor: '#8b5cf6',
                borderWidth: 0,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Hourly Usage Chart
    const hourlyCtx = document.getElementById('hourlyUsageChart').getContext('2d');
    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($hourly_usage)) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode(array_values($hourly_usage)) ?>,
                backgroundColor: '#06b6d4',
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Weekday Usage Chart
    const weekdayCtx = document.getElementById('weekdayUsageChart').getContext('2d');
    new Chart(weekdayCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($weekday_usage)) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode(array_values($weekday_usage)) ?>,
                backgroundColor: '#f59e0b',
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});

// Real CSV Export Function
function exportToCSV() {
    try {
        // Create CSV content
        let csvContent = "LabEase Analytics Report\n\n";
        csvContent += "User," + "<?= htmlspecialchars($user_name) ?>" + "\n";
        csvContent += "Report Date," + new Date().toLocaleDateString() + "\n";
        csvContent += "Student ID,#<?= $user_id ?>\n\n";
        
        // Statistics Section
        csvContent += "OVERALL STATISTICS\n";
        csvContent += "Metric,Count\n";
        csvContent += "Total Bookings,<?= $total_bookings ?>\n";
        csvContent += "Completed Sessions,<?= $completed_sessions ?>\n";
        csvContent += "Upcoming Bookings,<?= $upcoming_bookings ?>\n";
        csvContent += "Approved Bookings,<?= $approved_bookings ?>\n";
        csvContent += "Pending Approval,<?= $pending_bookings ?>\n";
        csvContent += "Rejected Bookings,<?= $rejected_bookings ?>\n";
        csvContent += "Approval Rate,<?= $total_bookings > 0 ? round(($approved_bookings/$total_bookings)*100) : 0 ?>%\n";
        csvContent += "Completion Rate,<?= $approved_bookings > 0 ? round(($completed_sessions/$approved_bookings)*100) : 0 ?>%\n\n";
        
        // Monthly Trends
        csvContent += "MONTHLY BOOKING TRENDS\n";
        csvContent += "Month,Total Bookings,Approved,Rejected\n";
        <?php foreach($monthly_stats as $month): ?>
        csvContent += "<?= date('M Y', strtotime($month['month'] . '-01')) ?>,<?= $month['total'] ?>,<?= $month['approved'] ?>,<?= $month['rejected'] ?>\n";
        <?php endforeach; ?>
        csvContent += "\n";
        
        // Lab Usage
        csvContent += "LAB USAGE STATISTICS\n";
        csvContent += "Lab Name,Booking Count\n";
        <?php foreach($lab_usage as $lab): ?>
        csvContent += "<?= htmlspecialchars($lab['lab_name']) ?>,<?= $lab['booking_count'] ?>\n";
        <?php endforeach; ?>
        csvContent += "\n";
        
        // Hourly Patterns
        csvContent += "HOURLY BOOKING PATTERNS\n";
        csvContent += "Hour,Booking Count\n";
        <?php foreach($hourly_usage as $hour => $count): ?>
        csvContent += "<?= $hour ?>:00 - <?= $hour+1 ?>:00,<?= $count ?>\n";
        <?php endforeach; ?>
        csvContent += "\n";
        
        // Weekday Patterns
        csvContent += "WEEKDAY DISTRIBUTION\n";
        csvContent += "Day,Booking Count\n";
        <?php foreach($weekday_usage as $day => $count): ?>
        csvContent += "<?= $day ?>,<?= $count ?>\n";
        <?php endforeach; ?>

        // Create and download CSV file
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `LabEase_Analytics_<?= $user_name ?>_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Show success message
        showExportSuccess('CSV');
        
    } catch (error) {
        console.error('CSV export error:', error);
        alert('Error generating CSV file. Please try again.');
    }
}

// Real PDF Export Function
async function exportToPDF() {
    try {
        // Show loading indicator
        const loadingIndicator = document.getElementById('pdfLoading');
        loadingIndicator.style.display = 'block';
        
        // Use jsPDF
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');
        
        // Add header
        doc.setFillColor(59, 130, 246);
        doc.rect(0, 0, 210, 30, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(20);
        doc.setFont('helvetica', 'bold');
        doc.text('LabEase Analytics Report', 105, 15, { align: 'center' });
        
        doc.setFontSize(12);
        doc.text(`Generated for: <?= htmlspecialchars($user_name) ?>`, 15, 40);
        doc.text(`Student ID: #<?= $user_id ?>`, 15, 47);
        doc.text(`Report Date: ${new Date().toLocaleDateString()}`, 15, 54);
        
        // Add statistics section
        doc.setTextColor(0, 0, 0);
        doc.setFontSize(16);
        doc.setFont('helvetica', 'bold');
        doc.text('Overall Statistics', 15, 70);
        
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        let yPos = 80;
        
        const stats = [
            ['Total Bookings', '<?= $total_bookings ?>'],
            ['Completed Sessions', '<?= $completed_sessions ?>'],
            ['Upcoming Bookings', '<?= $upcoming_bookings ?>'],
            ['Approved Bookings', '<?= $approved_bookings ?>'],
            ['Pending Approval', '<?= $pending_bookings ?>'],
            ['Rejected Bookings', '<?= $rejected_bookings ?>'],
            ['Approval Rate', '<?= $total_bookings > 0 ? round(($approved_bookings/$total_bookings)*100) : 0 ?>%'],
            ['Completion Rate', '<?= $approved_bookings > 0 ? round(($completed_sessions/$approved_bookings)*100) : 0 ?>%']
        ];
        
        stats.forEach(([label, value], index) => {
            if (yPos > 270) {
                doc.addPage();
                yPos = 20;
            }
            doc.text(`${label}:`, 20, yPos);
            doc.text(value, 80, yPos);
            yPos += 7;
        });
        
        // Add insights section
        yPos += 10;
        if (yPos > 250) {
            doc.addPage();
            yPos = 20;
        }
        
        doc.setFontSize(16);
        doc.setFont('helvetica', 'bold');
        doc.text('Key Insights', 15, yPos);
        yPos += 15;
        
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        
        // Most booked labs
        doc.text('Most Booked Labs:', 20, yPos);
        yPos += 7;
        <?php foreach(array_slice($lab_usage, 0, 3) as $lab): ?>
        if (yPos > 270) {
            doc.addPage();
            yPos = 20;
        }
        doc.text(`• <?= htmlspecialchars($lab['lab_name']) ?> (<?= $lab['booking_count'] ?> bookings)`, 25, yPos);
        yPos += 5;
        <?php endforeach; ?>
        
        yPos += 5;
        
        // Preferred times
        if (yPos > 250) {
            doc.addPage();
            yPos = 20;
        }
        doc.text('Preferred Booking Times:', 20, yPos);
        yPos += 7;
        <?php 
        arsort($hourly_usage);
        $top_hours = array_slice($hourly_usage, 0, 3, true);
        foreach($top_hours as $hour => $count): 
            if($count > 0):
        ?>
        if (yPos > 270) {
            doc.addPage();
            yPos = 20;
        }
        doc.text(`• <?= $hour ?>:00 - <?= $hour+1 ?>:00 (<?= $count ?> sessions)`, 25, yPos);
        yPos += 5;
        <?php endif; endforeach; ?>
        
        // Add footer
        const pageCount = doc.internal.getNumberOfPages();
        for(let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(8);
            doc.setTextColor(128, 128, 128);
            doc.text(`Page ${i} of ${pageCount}`, 105, 290, { align: 'center' });
            doc.text('Generated by LabEase System', 195, 290, { align: 'right' });
        }
        
        // Save the PDF
        doc.save(`LabEase_Analytics_<?= $user_name ?>_${new Date().toISOString().split('T')[0]}.pdf`);
        
        // Hide loading indicator
        loadingIndicator.style.display = 'none';
        
        // Show success message
        showExportSuccess('PDF');
        
    } catch (error) {
        console.error('PDF export error:', error);
        document.getElementById('pdfLoading').style.display = 'none';
        alert('Error generating PDF file. Please try again.');
    }
}

// Print Function
function printAnalytics() {
    window.print();
}

// Show export success message
function showExportSuccess(format) {
    const originalText = event.target.innerHTML;
    event.target.innerHTML = `✅ ${format} Exported!`;
    event.target.style.background = '#10b981';
    event.target.style.borderColor = '#10b981';
    event.target.style.color = 'white';
    
    setTimeout(() => {
        event.target.innerHTML = originalText;
        event.target.style.background = '';
        event.target.style.borderColor = '';
        event.target.style.color = '';
    }, 2000);
}
</script>

</body>
</html>