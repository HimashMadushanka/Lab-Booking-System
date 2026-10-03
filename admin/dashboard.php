<?php
session_start();
require '../db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$total_users    = $mysqli->query("SELECT COUNT(*) AS cnt FROM users")->fetch_assoc()['cnt'];
$total_bookings = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings")->fetch_assoc()['cnt'];
$pending        = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE status='pending'")->fetch_assoc()['cnt'];
$total_labs     = $mysqli->query("SELECT COUNT(*) AS cnt FROM labs")->fetch_assoc()['cnt'];
$approved       = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE status='approved'")->fetch_assoc()['cnt'];
$rejected       = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE status='rejected'")->fetch_assoc()['cnt'];

$status_distribution = $mysqli->query("SELECT status, COUNT(*) as count FROM bookings GROUP BY status");
$status_data = [];
while ($row = $status_distribution->fetch_assoc()) $status_data[] = $row;

$daily_bookings = $mysqli->query("
    SELECT DATE(date) as booking_date, COUNT(*) as count
    FROM bookings
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(date)
    ORDER BY booking_date
");
$daily_data = [];
while ($row = $daily_bookings->fetch_assoc()) $daily_data[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — LabEasy</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
      --bg:       #f0f4f8;
      --surface:  #ffffff;
      --surface2: #f5f7fa;
      --border:   rgba(0,0,0,0.07);
      --text:     #1a202c;
      --muted:    #718096;
      --radius:   12px;
      --blue:     #2d77f6;
      --blue-lt:  #e8f0fe;
      --blue-dk:  #185FA5;
      --green:    #1D9E75;
      --amber:    #EF9F27;
      --red:      #E24B4A;
      --purple:   #7F77DD;
      --teal:     #5DCAA5;
    }

    html, body { height: 100%; }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #8df3eeff 0%,  #85bde7ff 100%);
      color: var(--text);
      height: 100vh;
      overflow: hidden;
    }

    /* ─── Layout ─── */
    .dash {
      height: 100vh;
      display: grid;
      grid-template-rows: 64px 1fr 48px;
      gap: 12px;
      padding: 12px;
    }

    /* ─── Top bar ─── */
    .topbar {
      background: #1e2d3d;
      border-radius: var(--radius);
      padding: 0 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .brand { display: flex; align-items: center; gap: 12px; }

    .brand-icon {
      width: 38px; height: 38px;
      background: rgba(255,255,255,0.12);
      border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px;
    }

    .brand h1   { color: #fff; font-size: 16px; font-weight: 600; }
    .brand span { color: rgba(255,255,255,0.45); font-size: 11px; display: block; margin-top: 1px; }

    .topbar-btns { display: flex; gap: 8px; }

    .tbtn {
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      text-decoration: none;
      display: flex; align-items: center; gap: 5px;
      transition: opacity .2s, transform .15s;
      border: none; cursor: pointer;
    }
    .tbtn:hover { opacity: .88; transform: translateY(-1px); }
    .tbtn-blue { background: var(--blue); color: #fff; }
    .tbtn-red  { background: #e74c3c;    color: #fff; }

    /* ─── Main grid ─── */
    .main {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      grid-template-rows: repeat(2, minmax(0, 1fr));
      gap: 12px;
      min-height: 0;
    }

    .card {
      background: var(--surface);
      border: 0.5px solid var(--border);
      border-radius: var(--radius);
      padding: 16px;
      display: flex;
      flex-direction: column;
      min-height: 0;
      overflow: hidden;
    }

    .card-title {
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-bottom: 12px;
      flex-shrink: 0;
      display: flex; align-items: center; gap: 6px;
    }

    /* ─── Stats ─── */
    .stats {
      grid-column: 1 / 3;
      grid-row:    1 / 2;
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      grid-template-rows: repeat(2, minmax(0, 1fr));
      gap: 10px;
      background: var(--surface);
      border: 0.5px solid var(--border);
      border-radius: var(--radius);
      padding: 14px;
    }

    .stat {
      background: var(--surface2);
      border-radius: 8px;
      padding: 12px 14px;
      border-left: 3px solid transparent;
      display: flex; flex-direction: column; justify-content: center;
      transition: transform .2s, box-shadow .2s;
    }
    .stat:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,.07); }

    .stat:nth-child(1) { border-color: var(--blue);   }
    .stat:nth-child(2) { border-color: var(--purple); }
    .stat:nth-child(3) { border-color: var(--green);  }
    .stat:nth-child(4) { border-color: var(--amber);  }
    .stat:nth-child(5) { border-color: var(--red);    }
    .stat:nth-child(6) { border-color: var(--teal);   }

    .stat-val { font-size: 26px; font-weight: 700; line-height: 1; color: var(--text); }
    .stat-lbl { font-size: 10px; font-weight: 600; color: var(--muted); margin-top: 4px; text-transform: uppercase; letter-spacing: .4px; }

    /* ─── Daily chart ─── */
    .daily {
      grid-column: 3 / 5;
      grid-row:    1 / 2;
    }

    .chart-wrap { flex: 1; position: relative; min-height: 0; }

    /* ─── Status chart ─── */
    .status-card {
      grid-column: 1 / 2;
      grid-row:    2 / 3;
    }

    /* ─── Quick actions ─── */
    .actions {
      grid-column: 2 / 4;
      grid-row:    2 / 3;
    }

    .actions-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      grid-template-rows: repeat(2, minmax(0, 1fr));
      gap: 8px;
      flex: 1;
      min-height: 0;
    }

    .act-btn {
      background: var(--blue-lt);
      border-radius: 8px;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      gap: 6px;
      text-decoration: none;
      color: var(--blue-dk);
      font-size: 11px;
      font-weight: 600;
      transition: background .2s, transform .15s, color .2s;
      min-height: 0; overflow: hidden; padding: 8px;
      border: 0.5px solid transparent;
    }
    .act-btn:hover {
      background: #1e2d3d;
      color: #fff;
      transform: translateY(-2px);
      border-color: transparent;
    }

    .act-icon {
      font-size: 22px;
      width: 40px; height: 40px;
      background: rgba(45,119,246,0.12);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      transition: background .2s;
      flex-shrink: 0;
    }
    .act-btn:hover .act-icon { background: rgba(255,255,255,0.15); }

    /* ─── Reports ─── */
    .reports {
      grid-column: 4 / 5;
      grid-row:    2 / 3;
      background: #1e2d3d;
      border-radius: var(--radius);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      text-align: center;
      gap: 6px; padding: 20px;
      border: none;
    }
    .reports-icon { font-size: 32px; margin-bottom: 4px; }
    .reports h3   { color: #fff; font-size: 15px; font-weight: 600; }
    .reports p    { color: rgba(255,255,255,0.55); font-size: 11px; }
    .rpt-btn {
      display: inline-block;
      background: var(--blue);
      color: #fff;
      padding: 9px 22px;
      border-radius: 8px;
      text-decoration: none;
      font-size: 12px;
      font-weight: 600;
      margin-top: 8px;
      transition: opacity .2s, transform .15s;
    }
    .rpt-btn:hover { opacity: .88; transform: translateY(-1px); }

    /* ─── Footer ─── */
    .footer {
      background: var(--surface);
      border: 0.5px solid var(--border);
      border-radius: var(--radius);
      padding: 0 20px;
      display: flex; justify-content: space-between; align-items: center;
    }
    .footer-l { font-size: 12px; color: var(--muted); }
    .footer-r { display: flex; gap: 18px; }
    .footer-r a { font-size: 12px; color: var(--blue); text-decoration: none; font-weight: 500; }
    .footer-r a:hover { text-decoration: underline; }

    /* ─── Responsive ─── */
    @media (max-width: 1100px) {
      body { overflow: auto; height: auto; }
      .dash { height: auto; grid-template-rows: 64px auto 48px; }
      .main { grid-template-columns: repeat(2, minmax(0, 1fr)); grid-template-rows: auto; }
      .stats       { grid-column: 1 / 3; grid-row: auto; }
      .daily       { grid-column: 1 / 3; grid-row: auto; min-height: 200px; }
      .status-card { grid-column: 1 / 2; grid-row: auto; min-height: 200px; }
      .actions     { grid-column: 1 / 2; grid-row: auto; min-height: 200px; }
      .reports     { grid-column: 2 / 3; grid-row: auto; }
    }

    @media (max-width: 640px) {
      .main { grid-template-columns: 1fr; }
      .stats       { grid-column: 1; grid-template-columns: repeat(2, 1fr); }
      .daily, .status-card, .actions, .reports { grid-column: 1; }
      .topbar .tbtn span.label { display: none; }
    }
  </style>
</head>
<body>
<div class="dash">

  <!-- TOP BAR -->
  <header class="topbar">
    <div class="brand">
      <div class="brand-icon">🎯</div>
      <div>
        <h1>LabEasy</h1>
        <span>Administrator Dashboard</span>
      </div>
    </div>
    <nav class="topbar-btns">
      <a href="analytics.php" class="tbtn tbtn-blue">📊 <span class="label">Analytics</span></a>
      <a href="logout.php"    class="tbtn tbtn-red">🚪 <span class="label">Logout</span></a>
    </nav>
  </header>

  <!-- MAIN GRID -->
  <div class="main">

    <!-- Stats 2×3 -->
    <div class="stats">
      <div class="stat"><div class="stat-val"><?= $total_users ?></div><div class="stat-lbl">👥 Users</div></div>
      <div class="stat"><div class="stat-val"><?= $total_labs ?></div><div class="stat-lbl">🔬 Labs</div></div>
      <div class="stat"><div class="stat-val"><?= $total_bookings ?></div><div class="stat-lbl">📅 Bookings</div></div>
      <div class="stat"><div class="stat-val"><?= $pending ?></div><div class="stat-lbl">⏳ Pending</div></div>
      <div class="stat"><div class="stat-val"><?= $approved ?></div><div class="stat-lbl">✅ Approved</div></div>
      <div class="stat"><div class="stat-val"><?= $rejected ?></div><div class="stat-lbl">❌ Rejected</div></div>
    </div>

    <!-- Daily line chart -->
    <div class="card daily">
      <p class="card-title">📈 Last 7 days activity</p>
      <div class="chart-wrap"><canvas id="dailyChart"></canvas></div>
    </div>

    <!-- Status donut -->
    <div class="card status-card">
      <p class="card-title">🎯 Status</p>
      <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
    </div>

    <!-- Quick actions -->
    <div class="card actions">
      <p class="card-title">⚡ Quick actions</p>
      <div class="actions-grid">
        <a href="manage_bookings.php"    class="act-btn"><div class="act-icon">📋</div><span>Bookings</span></a>
        <a href="admin_labs.php"         class="act-btn"><div class="act-icon">🔬</div><span>Labs</span></a>
        <a href="admin_chat.php"         class="act-btn"><div class="act-icon">💬</div><span>Chat</span></a>
        <a href="create_notification.php" class="act-btn"><div class="act-icon">🔔</div><span>Notify</span></a>
        <a href="admin_feedback.php"     class="act-btn"><div class="act-icon">⭐</div><span>Feedback</span></a>
        <a href="analytics.php"          class="act-btn"><div class="act-icon">📊</div><span>Analytics</span></a>
      </div>
    </div>

    <!-- Reports -->
    <div class="reports">
      <div class="reports-icon">📊</div>
      <h3>Reports</h3>
      <p>Generate comprehensive PDF reports for any period</p>
      <a href="pdf_selector.php" class="rpt-btn">Generate Report</a>
    </div>

  </div>

  <!-- FOOTER -->
  <footer class="footer">
    <span class="footer-l">© 2024 Lab Management System</span>
    <nav class="footer-r">
      <a href="#">Help</a>
      <a href="#">Documentation</a>
      <a href="#">Support</a>
    </nav>
  </footer>

</div>

<script>
const statusData = <?= json_encode($status_data) ?>;
const dailyData  = <?= json_encode($daily_data) ?>;

Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size   = 11;

/* Daily line chart */
const dCtx = document.getElementById('dailyChart').getContext('2d');
const grad = dCtx.createLinearGradient(0, 0, 0, 200);
grad.addColorStop(0, 'rgba(45,119,246,0.25)');
grad.addColorStop(1, 'rgba(45,119,246,0.0)');

new Chart(dCtx, {
  type: 'line',
  data: {
    labels: dailyData.map(d =>
      new Date(d.booking_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    ),
    datasets: [{
      label: 'Bookings',
      data: dailyData.map(d => d.count),
      borderColor: '#2d77f6',
      backgroundColor: grad,
      borderWidth: 2,
      tension: 0.4,
      fill: true,
      pointBackgroundColor: '#2d77f6',
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
        backgroundColor: '#1e2d3d',
        padding: 10,
        cornerRadius: 8,
        titleFont: { size: 12 },
        bodyFont:  { size: 11 }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(0,0,0,0.04)' },
        ticks: { padding: 6, font: { size: 10 } }
      },
      x: {
        grid: { display: false },
        ticks: { padding: 6, font: { size: 10 } }
      }
    }
  }
});

/* Status donut */
const colorMap = { pending: '#EF9F27', approved: '#1D9E75', rejected: '#E24B4A' };
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: statusData.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1)),
    datasets: [{
      data: statusData.map(s => s.count),
      backgroundColor: statusData.map(s => colorMap[s.status] || '#718096'),
      borderWidth: 3,
      borderColor: '#fff',
      hoverOffset: 6
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: { padding: 12, font: { size: 11 }, usePointStyle: true }
      },
      tooltip: {
        backgroundColor: '#1e2d3d',
        padding: 10,
        cornerRadius: 8
      }
    },
    cutout: '62%'
  }
});
</script>
</body>
</html>