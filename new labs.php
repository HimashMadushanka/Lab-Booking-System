<?php
// labs.php - View All Labs
require 'db.php';

// Check if logged in and is user
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user'){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fetch all labs with detailed information
$labs_query = "
    SELECT l.*, 
           COUNT(c.id) as total_computers,
           SUM(CASE WHEN c.status = 'available' THEN 1 ELSE 0 END) as available_computers
    FROM labs l 
    LEFT JOIN computers c ON l.id = c.lab_id 
    GROUP BY l.id 
    ORDER BY l.name
";
$labs_result = $conn->query($labs_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Labs | LabEase</title>
<style>
/* Reuse the same styles from index.php */
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

/* Sidebar and main content styles from index.php */
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
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 25px;
  margin-bottom: 30px;
}

.lab-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 25px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  transition: all 0.3s ease;
}

.lab-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

@media (max-width: 1024px) {
  .sidebar {
    transform: translateX(-100%);
  }
  .main-content {
    margin-left: 0;
  }
}
</style>
</head>
<body>

<!-- Sidebar (same as index.php) -->
<div class="sidebar">
  <div class="sidebar-logo">
    <h2>🖥️ LabEase</h2>
    <p>Computer Lab Booking System</p>
  </div>
  
  <ul class="sidebar-menu">
    <li><a href="index.php"><span>📊</span> Dashboard</a></li>
    <li><a href="create.php"><span>➕</span> Book a Lab</a></li>
    <li><a href="my_bookings.php"><span>📋</span> My Bookings</a></li>
    <li><a href="labs.php" class="active"><span>🏢</span> All Labs</a></li>
    <li><a href="feedback.php"><span>💬</span>Give Feedback</a></li>
  </ul>
  
  <div class="logout-btn" style="position: absolute; bottom: 30px; left: 25px; right: 25px;">
    <a href="logout.php" style="display: block; padding: 12px 20px; background: #dc2626; color: white; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600;">Logout</a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content">
  
  <!-- Top Bar -->
  <div class="top-bar">
    <h1>🏢 All Computer Labs</h1>
    <a href="create.php" class="btn-primary">+ New Booking</a>
  </div>

  <!-- Labs Grid -->
  <div class="labs-grid">
    <?php if ($labs_result->num_rows > 0): 
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
        <!-- Lab Header -->
        <div style="display: flex; justify-content: between; align-items: flex-start; margin-bottom: 15px;">
            <div>
                <h3 style="font-size: 20px; color: #1e293b; font-weight: 700; margin-bottom: 5px;">
                    <?= htmlspecialchars($lab['name']) ?>
                </h3>
                <p style="font-size: 14px; color: #64748b; margin: 0;">
                    📍 <?= htmlspecialchars($lab['location']) ?>
                </p>
            </div>
            <div style="
                background: <?= $status_color ?>20;
                color: <?= $status_color ?>;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                border: 1px solid <?= $status_color ?>40;
            ">
                <?= $status_text ?>
            </div>
        </div>
        
        <!-- Capacity Info -->
        <div style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 14px; color: #64748b; font-weight: 500;">Total Capacity:</span>
                <span style="font-size: 14px; color: #1e293b; font-weight: 600;"><?= $lab['capacity'] ?> computers</span>
            </div>
            
            <!-- Availability Progress Bar -->
            <div style="margin-bottom: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <span style="font-size: 14px; color: #64748b; font-weight: 500;">Available Now:</span>
                    <span style="font-size: 14px; color: #1e293b; font-weight: 600;">
                        <?= $lab['available_computers'] ?>/<?= $lab['total_computers'] ?> (<?= $available_percentage ?>%)
                    </span>
                </div>
                <div style="width: 100%; height: 8px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
                    <div style="width: <?= $available_percentage ?>%; height: 100%; background: <?= $status_color ?>; border-radius: 10px;"></div>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
            <div style="text-align: center; padding: 12px; background: #f8fafc; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: 700; color: #3b82f6; margin-bottom: 2px;">
                    <?= $lab['available_computers'] ?>
                </div>
                <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">
                    Available
                </div>
            </div>
            <div style="text-align: center; padding: 12px; background: #f8fafc; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: 700; color: #8b5cf6; margin-bottom: 2px;">
                    <?= $lab['total_computers'] - $lab['available_computers'] ?>
                </div>
                <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">
                    In Use
                </div>
            </div>
        </div>
        
        <!-- Quick Action Button -->
        <a href="create.php?lab=<?= $lab['id'] ?>" style="
            display: block;
            text-align: center;
            padding: 12px 15px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s ease;
        " onmouseover="this.style.background='#2563eb'" 
           onmouseout="this.style.background='#3b82f6'">
            🖥️ Book This Lab
        </a>
    </div>
    <?php endwhile; ?>
    <?php else: ?>
    <div style="grid-column: 1 / -1; text-align: center; padding: 60px 40px; color: #94a3b8;">
        <div style="font-size: 64px; margin-bottom: 15px;">🏢</div>
        <h3 style="color: #64748b; margin-bottom: 10px;">No Labs Available</h3>
        <p>There are currently no labs configured in the system.</p>
        <a href="index.php" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px;">Return to Dashboard</a>
    </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>