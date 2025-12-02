<?php
session_start();
require '../db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Stats
$total_users = $mysqli->query("SELECT COUNT(*) AS cnt FROM users")->fetch_assoc()['cnt'];
$total_bookings = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings")->fetch_assoc()['cnt'];
$pending = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE status='pending'")->fetch_assoc()['cnt'];
$total_labs = $mysqli->query("SELECT COUNT(*) AS cnt FROM labs")->fetch_assoc()['cnt'];
$approved = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE status='approved'")->fetch_assoc()['cnt'];
$rejected = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE status='rejected'")->fetch_assoc()['cnt'];

// Analytics
$status_distribution = $mysqli->query("SELECT status, COUNT(*) as count FROM bookings GROUP BY status");
$status_data = [];
while($row = $status_distribution->fetch_assoc()) {
    $status_data[] = $row;
}

$daily_bookings = $mysqli->query("
    SELECT DATE(date) as booking_date, COUNT(*) as count 
    FROM bookings 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(date) 
    ORDER BY booking_date
");
$daily_data = [];
while($row = $daily_bookings->fetch_assoc()) {
    $daily_data[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body { 
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #b8eaf8 0%, #9dd9ed 100%);
      height: 100vh;
      overflow: hidden;
    }

    .dashboard {
      height: 100vh;
      display: grid;
      grid-template-rows: auto 1fr auto;
      padding: 15px;
      gap: 15px;
    }

    .header {
      background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
      border-radius: 16px;
      padding: 20px 30px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .logo {
      width: 50px;
      height: 50px;
      background: rgba(255,255,255,0.15);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      backdrop-filter: blur(10px);
    }

    .header-title h1 {
      color: white;
      font-size: 22px;
      font-weight: 700;
      letter-spacing: -0.3px;
    }

    .header-title p {
      color: rgba(255,255,255,0.75);
      font-size: 12px;
      margin-top: 2px;
    }

    .header-actions {
      display: flex;
      gap: 10px;
    }

    .header-btn {
      padding: 10px 20px;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 600;
      font-size: 13px;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .analytics-btn {
      background: linear-gradient(135deg, #79a1deff 0%, #2d77f6ff 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(167,89,209,0.35);
    }

    .analytics-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(167,89,209,0.45);
    }

    .logout-btn {
      background: rgba(246, 93, 93, 0.92);
      color: white;
      backdrop-filter: blur(10px);
    }

    .logout-btn:hover {
      background: rgba(255,255,255,0.25);
    }

    .content-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      grid-template-rows: repeat(2, 1fr);
      gap: 15px;
      overflow: hidden;
    }

    .stats-panel {
      grid-column: 1 / 3;
      grid-row: 1 / 2;
      background: white;
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }

    .stat-box {
      background: linear-gradient(135deg, #f8f9fa, #ffffff);
      padding: 18px;
      border-radius: 12px;
      border-left: 4px solid;
      transition: all 0.3s;
    }

    .stat-box:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    }

    .stat-box:nth-child(1) { border-left-color: #3498db; }
    .stat-box:nth-child(2) { border-left-color: #a759d1; }
    .stat-box:nth-child(3) { border-left-color: #2ecc71; }
    .stat-box:nth-child(4) { border-left-color: #f39c12; }
    .stat-box:nth-child(5) { border-left-color: #e74c3c; }
    .stat-box:nth-child(6) { border-left-color: #1abc9c; }

    .stat-value {
      font-size: 28px;
      font-weight: 800;
      color: #2c3e50;
      line-height: 1;
      margin-bottom: 4px;
    }

    .stat-label {
      font-size: 11px;
      color: #7f8c8d;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .chart-card {
      background: white;
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      display: flex;
      flex-direction: column;
    }

    .chart-card.daily {
      grid-column: 3 / 5;
      grid-row: 1 / 2;
    }

    .chart-card.status {
      grid-column: 1 / 2;
      grid-row: 2 / 3;
    }

    .actions-panel {
      grid-column: 2 / 4;
      grid-row: 2 / 3;
    }

    .pdf-panel {
      grid-column: 4 / 5;
      grid-row: 2 / 3;
      background: linear-gradient(135deg, #79a1deff 0%, #2d77f6ff 100%);
      border-radius: 16px;
      padding: 25px;
      box-shadow: 0 8px 25px rgba(167,89,209,0.35);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      color: white;
    }

    .card-title {
      font-size: 14px;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .chart-wrapper {
      flex: 1;
      position: relative;
      min-height: 0;
    }

    .actions-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      height: 100%;
    }

    .action-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 15px;
      background: linear-gradient(135deg, #2fcbd9ff, #acf8ffff);
      border-radius: 12px;
      text-decoration: none;
      color: #2c3e50;
      font-weight: 600;
      font-size: 12px;
      transition: all 0.3s;
      border: 2px solid transparent;
    }

    .action-btn:hover {
      background: linear-gradient(135deg, #2c3e50, #34495e);
      color: white;
      border-color: #2c3e50;
      transform: translateY(-3px);
      box-shadow: 0 6px 15px rgba(0,0,0,0.15);
    }

    .action-icon {
      font-size: 28px;
      width: 50px;
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(44,62,80,0.08);
      border-radius: 12px;
      transition: all 0.3s;
    }

    .action-btn:hover .action-icon {
      background:rgba(255,255,255,0.2);
    }

    .pdf-panel h3 {
      font-size: 18px;
      margin-bottom: 8px;
      font-weight: 800;
    }

    .pdf-panel p {
      font-size: 11px;
      opacity: 0.9;
      margin-bottom: 18px;
    }

    .pdf-btn {
      display: inline-block;
      background: white;
      color: #a759d1;
      padding: 12px 28px;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 700;
      font-size: 13px;
      transition: all 0.3s;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    .pdf-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(0,0,0,0.3);
    }

    .footer {
      background: rgba(255,255,255,0.95);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      padding: 15px 25px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .footer-left {
      font-size: 13px;
      color: #7f8c8d;
      font-weight: 500;
    }

    .footer-right {
      display: flex;
      gap: 20px;
      font-size: 12px;
      color: #95a5a6;
    }

    .footer-link {
      color: #3498db;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s;
    }

    .footer-link:hover {
      color: #2980b9;
    }

    @media (max-width: 1400px) {
      .content-grid {
        grid-template-columns: repeat(3, 1fr);
      }
      
      .stats-panel {
        grid-column: 1 / 4;
      }
      
      .chart-card.daily {
        grid-column: 1 / 3;
        grid-row: 2 / 3;
      }
      
      .chart-card.status {
        grid-column: 3 / 4;
        grid-row: 2 / 3;
      }
      
      .actions-panel {
        grid-column: 1 / 3;
        grid-row: 3 / 4;
      }
      
      .pdf-panel {
        grid-column: 3 / 4;
        grid-row: 3 / 4;
      }
    }
  </style>
</head>
<body>

<div class="dashboard">
  <div class="header">
    <div class="header-left">
      <div class="logo">🎯</div>
      <div class="header-title">
        <h1>LabEasy</h1>
        <p>Administrator Dashboard</p>
      </div>
    </div>
    <div class="header-actions">
      <a href="analytics.php" class="header-btn analytics-btn">
        <span>📊</span> Analytics
      </a>
      <a href="logout.php" class="header-btn logout-btn">
        <span>🚪</span> Logout
      </a>
    </div>
  </div>

  <div class="content-grid">
    <div class="stats-panel">
      <div class="stat-box">
        <div class="stat-value"><?= $total_users ?></div>
        <div class="stat-label">👥 Users</div>
      </div>
      <div class="stat-box">
        <div class="stat-value"><?= $total_labs ?></div>
        <div class="stat-label">🔬 Labs</div>
      </div>
      <div class="stat-box">
        <div class="stat-value"><?= $total_bookings ?></div>
        <div class="stat-label">📅 Bookings</div>
      </div>
      <div class="stat-box">
        <div class="stat-value"><?= $pending ?></div>
        <div class="stat-label">⏳ Pending</div>
      </div>
      <div class="stat-box">
        <div class="stat-value"><?= $approved ?></div>
        <div class="stat-label">✅ Approved</div>
      </div>
      <div class="stat-box">
        <div class="stat-value"><?= $rejected ?></div>
        <div class="stat-label">❌ Rejected</div>
      </div>
    </div>

    <div class="chart-card daily">
      <h3 class="card-title">📈 Last 7 Days Activity</h3>
      <div class="chart-wrapper">
        <canvas id="dailyChart"></canvas>
      </div>
    </div>

    <div class="chart-card status">
      <h3 class="card-title">🎯 Status</h3>
      <div class="chart-wrapper">
        <canvas id="statusChart"></canvas>
      </div>
    </div>

    <div class="chart-card actions-panel">
      <h3 class="card-title">⚡ Quick Actions</h3>
      <div class="actions-grid">
        <a href="manage_bookings.php" class="action-btn">
          <div class="action-icon">📋</div>
          <span>Bookings</span>
        </a>
        <a href="admin_labs.php" class="action-btn">
          <div class="action-icon">🔬</div>
          <span>Labs</span>
        </a>
        <a href="admin_chat.php" class="action-btn">
          <div class="action-icon">💬</div>
          <span>Chat</span>
        </a>
        <a href="create_notification.php" class="action-btn">
          <div class="action-icon">🔔</div>
          <span>Notify</span>
        </a>
        <a href="admin_feedback.php" class="action-btn">
          <div class="action-icon">⭐</div>
          <span>Feedback</span>
        </a>
        <a href="analytics.php" class="action-btn">
          <div class="action-icon">📊</div>
          <span>Analytics</span>
        </a>
      </div>
    </div>

    <div class="pdf-panel">
      <h3>📊 Reports</h3>
      <p>Generate comprehensive PDF reports</p>
      <a href="pdf_selector.php" class="pdf-btn">Generate Report</a>
    </div>
  </div>

  <div class="footer">
    <div class="footer-left">
      © 2024 Lab Management System
    </div>
    <div class="footer-right">
      <a href="#" class="footer-link">Help</a>
      <a href="#" class="footer-link">Documentation</a>
      <a href="#" class="footer-link">Support</a>
    </div>
  </div>
</div>

<script>
  const statusData = <?= json_encode($status_data) ?>;
  const dailyData = <?= json_encode($daily_data) ?>;

  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.font.size = 11;

  // Daily Activity Chart
  const dailyCtx = document.getElementById('dailyChart').getContext('2d');
  const gradient = dailyCtx.createLinearGradient(0, 0, 0, 180);
  gradient.addColorStop(0, 'rgba(52,152,219,0.35)');
  gradient.addColorStop(1, 'rgba(52,152,219,0.0)');

  new Chart(dailyCtx, {
    type: 'line',
    data: {
      labels: dailyData.map(d => new Date(d.booking_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
      datasets: [{
        label: 'Bookings',
        data: dailyData.map(d => d.count),
        borderColor: '#3498db',
        backgroundColor: gradient,
        borderWidth: 2.5,
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#3498db',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(44,62,80,0.95)',
          padding: 10,
          cornerRadius: 8,
          titleFont: { size: 12 },
          bodyFont: { size: 11 }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(0,0,0,0.05)' },
          ticks: { padding: 8, font: { size: 10 } }
        },
        x: {
          grid: { display: false },
          ticks: { padding: 8, font: { size: 10 } }
        }
      }
    }
  });

  // Status Distribution Chart
  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
      labels: statusData.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1)),
      datasets: [{
        data: statusData.map(s => s.count),
        backgroundColor: ['#f39c12', '#27ae60', '#e74c3c'],
        borderWidth: 3,
        borderColor: '#fff',
        hoverOffset: 8
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            padding: 12,
            font: { size: 11, weight: '600' },
            usePointStyle: true
          }
        },
        tooltip: {
          backgroundColor: 'rgba(44,62,80,0.95)',
          padding: 10,
          cornerRadius: 8
        }
      },
      cutout: '60%'
    }
  });
</script>

</body>
</html>