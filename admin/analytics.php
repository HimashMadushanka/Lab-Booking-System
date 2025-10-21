<?php
session_start();
require '../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Get booking statistics for charts
$daily_bookings = $conn->query("
    SELECT DATE(date) as booking_date, COUNT(*) as count 
    FROM bookings 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(date) 
    ORDER BY booking_date
");

$status_distribution = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM bookings 
    GROUP BY status
");

$monthly_trends = $conn->query("
    SELECT DATE_FORMAT(date, '%Y-%m') as month, COUNT(*) as count 
    FROM bookings 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m') 
    ORDER BY month
");

$lab_usage = $conn->query("
    SELECT l.name as lab_name, COUNT(b.id) as booking_count
    FROM labs l
    LEFT JOIN computers c ON l.id = c.lab_id
    LEFT JOIN bookings b ON c.id = b.computer_id
    GROUP BY l.id, l.name
    ORDER BY booking_count DESC
");

$peak_hours = $conn->query("
    SELECT HOUR(start_time) as hour, COUNT(*) as count
    FROM bookings 
    WHERE status = 'approved'
    GROUP BY HOUR(start_time)
    ORDER BY hour
");

// Convert to arrays for JavaScript
$daily_data = [];
while($row = $daily_bookings->fetch_assoc()) {
    $daily_data[] = $row;
}

$status_data = [];
while($row = $status_distribution->fetch_assoc()) {
    $status_data[] = $row;
}

$monthly_data = [];
while($row = $monthly_trends->fetch_assoc()) {
    $monthly_data[] = $row;
}

$lab_data = [];
while($row = $lab_usage->fetch_assoc()) {
    $lab_data[] = $row;
}

$hour_data = [];
while($row = $peak_hours->fetch_assoc()) {
    $hour_data[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Booking Analytics | Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
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
        
        .logout-btn {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
        }
        
        .logout-btn:hover {
            background: #c0392b;
        }
        
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .chart-card h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            text-align: center;
            font-size: 18px;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #3498db;
        }
        
        .stat-card h4 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .navigation {
            text-align: center;
            margin-top: 30px;
        }
        
        .nav-btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 6px;
            margin: 0 10px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .nav-btn:hover {
            background: #2980b9;
        }
        
        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .chart-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Booking Analytics Dashboard</h1>
        <p>Visual insights into lab booking patterns and trends</p>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <?php
        $total_bookings = $conn->query("SELECT COUNT(*) as cnt FROM bookings")->fetch_assoc()['cnt'];
        $pending = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status='pending'")->fetch_assoc()['cnt'];
        $approved = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status='approved'")->fetch_assoc()['cnt'];
        $today_bookings = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE date=CURDATE()")->fetch_assoc()['cnt'];
        ?>
        <div class="stat-card">
            <h4><?= $total_bookings ?></h4>
            <p>Total Bookings</p>
        </div>
        <div class="stat-card">
            <h4><?= $approved ?></h4>
            <p>Approved</p>
        </div>
        <div class="stat-card">
            <h4><?= $pending ?></h4>
            <p>Pending</p>
        </div>
        <div class="stat-card">
            <h4><?= $today_bookings ?></h4>
            <p>Today's Bookings</p>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="charts-container">
        <!-- Daily Bookings Chart -->
        <div class="chart-card">
            <h3>📈 Daily Bookings (Last 30 Days)</h3>
            <div class="chart-wrapper">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>

        <!-- Status Distribution Chart -->
        <div class="chart-card">
            <h3>📊 Booking Status Distribution</h3>
            <div class="chart-wrapper">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- Monthly Trends Chart -->
        <div class="chart-card">
            <h3>📅 Monthly Booking Trends</h3>
            <div class="chart-wrapper">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <!-- Lab Usage Chart -->
        <div class="chart-card">
            <h3>🏢 Lab Usage Distribution</h3>
            <div class="chart-wrapper">
                <canvas id="labChart"></canvas>
            </div>
        </div>

        <!-- Peak Hours Chart -->
        <div class="chart-card">
            <h3>⏰ Peak Booking Hours</h3>
            <div class="chart-wrapper">
                <canvas id="hoursChart"></canvas>
            </div>
        </div>

    </div>

    <div class="navigation">
        <a href="dashboard.php" class="nav-btn">← Back to Dashboard</a>
        <a href="manage_bookings.php" class="nav-btn">Manage Bookings</a>
    </div>

    <script>
        // Convert PHP data to JavaScript
        const dailyData = <?= json_encode($daily_data) ?>;
        const statusData = <?= json_encode($status_data) ?>;
        const monthlyData = <?= json_encode($monthly_data) ?>;
        const labData = <?= json_encode($lab_data) ?>;
        const hourData = <?= json_encode($hour_data) ?>;

        // Daily Bookings Line Chart
        new Chart(document.getElementById('dailyChart'), {
            type: 'line',
            data: {
                labels: dailyData.map(d => new Date(d.booking_date).toLocaleDateString()),
                datasets: [{
                    label: 'Daily Bookings',
                    data: dailyData.map(d => d.count),
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    }
                }
            }
        });

        // Status Distribution Doughnut Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusData.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1)),
                datasets: [{
                    data: statusData.map(s => s.count),
                    backgroundColor: [
                        '#f39c12', // pending - orange
                        '#27ae60', // approved - green
                        '#e74c3c'  // rejected - red
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Monthly Trends Bar Chart
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: monthlyData.map(m => m.month),
                datasets: [{
                    label: 'Monthly Bookings',
                    data: monthlyData.map(m => m.count),
                    backgroundColor: '#9b59b6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Lab Usage Bar Chart
        new Chart(document.getElementById('labChart'), {
            type: 'bar',
            data: {
                labels: labData.map(l => l.lab_name),
                datasets: [{
                    label: 'Bookings',
                    data: labData.map(l => l.booking_count),
                    backgroundColor: '#1abc9c'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y'
            }
        });

        // Peak Hours Chart
        new Chart(document.getElementById('hoursChart'), {
            type: 'line',
            data: {
                labels: hourData.map(h => {
                    const hour = parseInt(h.hour);
                    return hour === 0 ? '12 AM' : 
                           hour < 12 ? hour + ' AM' : 
                           hour === 12 ? '12 PM' : 
                           (hour - 12) + ' PM';
                }),
                datasets: [{
                    label: 'Bookings per Hour',
                    data: hourData.map(h => h.count),
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Weekly Pattern Chart (You'll need to add this query)
        new Chart(document.getElementById('weeklyChart'), {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Average Bookings',
                    data: [12, 19, 15, 17, 14, 8, 5], // Sample data - replace with actual query
                    backgroundColor: '#34495e'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
</body>
</html>