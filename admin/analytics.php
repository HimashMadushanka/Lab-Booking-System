<?php
session_start();
require '../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Get booking statistics for charts - Fixed $conn to $mysqli
$daily_bookings = $mysqli->query("
    SELECT DATE(date) as booking_date, COUNT(*) as count 
    FROM bookings 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(date) 
    ORDER BY booking_date
");

$status_distribution = $mysqli->query("
    SELECT status, COUNT(*) as count 
    FROM bookings 
    GROUP BY status
");

$monthly_trends = $mysqli->query("
    SELECT DATE_FORMAT(date, '%Y-%m') as month, COUNT(*) as count 
    FROM bookings 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m') 
    ORDER BY month
");

$lab_usage = $mysqli->query("
    SELECT l.name as lab_name, COUNT(b.id) as booking_count
    FROM labs l
    LEFT JOIN computers c ON l.id = c.lab_id
    LEFT JOIN bookings b ON c.id = b.computer_id
    GROUP BY l.id, l.name
    ORDER BY booking_count DESC
");

$peak_hours = $mysqli->query("
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
            background: linear-gradient(135deg, #8df3eeff 0%,  #85bde7ff 100%);
            min-height: 100vh;
            padding: 20px;
        }
                
        .header {
            background:  #2c3e50;;
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .header h1 {
            margin-bottom: 10px;
            font-size: 32px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .logout-btn {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: #dc2626;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            transform: translateY(-50%) scale(1.05);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        .charts-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .chart-card.full-width {
            grid-column: 1 / -1;
        }
        
        .chart-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .chart-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        }
        
        .chart-card h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            text-align: center;
            font-size: 18px;
            font-weight: 700;
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 5px solid #667eea;
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        }
        
        .stat-card h4 {
            font-size: 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
            font-weight: 800;
        }
        
        .stat-card p {
            color: #5a6c7d;
            font-size: 14px;
            font-weight: 600;
        }
        
        .navigation {
            text-align: center;
            margin-top: 30px;
        }
        
        .nav-btn {
            display: inline-block;
            background: #2980b9;
            color: white;
            padding: 14px 30px;
            text-decoration: none;
            border-radius: 25px;
            margin: 0 10px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .nav-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .chart-card {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .logout-btn {
                position: static;
                transform: none;
                display: block;
                margin-top: 15px;
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
        // Fixed these queries too - changed $conn to $mysqli
        $total_bookings = $mysqli->query("SELECT COUNT(*) as cnt FROM bookings")->fetch_assoc()['cnt'];
        $pending = $mysqli->query("SELECT COUNT(*) as cnt FROM bookings WHERE status='pending'")->fetch_assoc()['cnt'];
        $approved = $mysqli->query("SELECT COUNT(*) as cnt FROM bookings WHERE status='approved'")->fetch_assoc()['cnt'];
        $today_bookings = $mysqli->query("SELECT COUNT(*) as cnt FROM bookings WHERE date=CURDATE()")->fetch_assoc()['cnt'];
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
        <!-- Row 1: Daily Bookings & Status Distribution -->
        <div class="chart-card">
            <h3>📈 Daily Bookings (Last 30 Days)</h3>
            <div class="chart-wrapper">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <h3>📊 Booking Status Distribution</h3>
            <div class="chart-wrapper">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- Row 2: Monthly Trends Full Width -->
        <div class="chart-card full-width">
            <h3>📅 Monthly Booking Trends</h3>
            <div class="chart-wrapper">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <!-- Row 3: Lab Usage & Peak Hours -->
        <div class="chart-card">
            <h3>🏢 Lab Usage Distribution</h3>
            <div class="chart-wrapper">
                <canvas id="labChart"></canvas>
            </div>
        </div>

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

        // Chart.js default options for modern look
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#2c3e50';

        // Daily Bookings Line Chart - Modern gradient style
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyGradient = dailyCtx.createLinearGradient(0, 0, 0, 300);
        dailyGradient.addColorStop(0, 'rgba(52, 152, 219, 0.4)');
        dailyGradient.addColorStop(1, 'rgba(52, 152, 219, 0.01)');

        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyData.map(d => new Date(d.booking_date).toLocaleDateString()),
                datasets: [{
                    label: 'Daily Bookings',
                    data: dailyData.map(d => d.count),
                    borderColor: '#3498db',
                    backgroundColor: dailyGradient,
                    borderWidth: 3,
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
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            padding: 15,
                            font: { weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(44, 62, 80, 0.9)',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            padding: 10
                        }
                    }
                }
            }
        });

        // Status Distribution Doughnut Chart - Modern with shadows
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusData.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1)),
                datasets: [{
                    data: statusData.map(s => s.count),
                    backgroundColor: [
                        '#f39c12',
                        '#27ae60',
                        '#e74c3c'
                    ],
                    borderWidth: 4,
                    borderColor: '#fff',
                    hoverOffset: 15,
                    hoverBorderWidth: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: { size: 13, weight: '600' },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(44, 62, 80, 0.9)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });

        // Monthly Trends Bar Chart - Modern gradient bars
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyGradient = monthlyCtx.createLinearGradient(0, 0, 0, 300);
        monthlyGradient.addColorStop(0, '#9b59b6');
        monthlyGradient.addColorStop(1, '#8e44ad');

        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyData.map(m => m.month),
                datasets: [{
                    label: 'Monthly Bookings',
                    data: monthlyData.map(m => m.count),
                    backgroundColor: monthlyGradient,
                    borderRadius: 8,
                    borderSkipped: false,
                    hoverBackgroundColor: '#8e44ad'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            padding: 15,
                            font: { weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(44, 62, 80, 0.9)',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            padding: 10
                        }
                    }
                }
            }
        });

        // Lab Usage Bar Chart - Horizontal with gradient
        const labCtx = document.getElementById('labChart').getContext('2d');
        const labGradient = labCtx.createLinearGradient(0, 0, 400, 0);
        labGradient.addColorStop(0, '#1abc9c');
        labGradient.addColorStop(1, '#16a085');

        new Chart(labCtx, {
            type: 'bar',
            data: {
                labels: labData.map(l => l.lab_name),
                datasets: [{
                    label: 'Bookings',
                    data: labData.map(l => l.booking_count),
                    backgroundColor: labGradient,
                    borderRadius: 8,
                    borderSkipped: false,
                    hoverBackgroundColor: '#16a085'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            padding: 15,
                            font: { weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(44, 62, 80, 0.9)',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            padding: 10
                        }
                    }
                }
            }
        });

        // Peak Hours Chart - Area style with gradient
        const hoursCtx = document.getElementById('hoursChart').getContext('2d');
        const hoursGradient = hoursCtx.createLinearGradient(0, 0, 0, 300);
        hoursGradient.addColorStop(0, 'rgba(231, 76, 60, 0.4)');
        hoursGradient.addColorStop(1, 'rgba(231, 76, 60, 0.01)');

        new Chart(hoursCtx, {
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
                    backgroundColor: hoursGradient,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#e74c3c',
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
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            padding: 15,
                            font: { weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(44, 62, 80, 0.9)',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            padding: 10
                        }
                    }
                }
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