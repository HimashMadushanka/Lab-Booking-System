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

// --- Time Based Greeting Logic ---
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

// Initialize variables
$total_bookings = 0;
$approved_bookings = 0;
$upcoming = null;
$next_session = null; 
$labs_result = null;
$calendar_data = [];

try {
    // 1. Fetch statistics
    $result = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE user_id=$user_id");
    if ($result) {
        $total_bookings = $result->fetch_assoc()['cnt'];
    }

    $result = $mysqli->query("SELECT COUNT(*) AS cnt FROM bookings WHERE user_id=$user_id AND status='approved'");
    if ($result) {
        $approved_bookings = $result->fetch_assoc()['cnt'];
    }

    // 2. Fetch upcoming bookings
    $stmt = $mysqli->prepare("
        SELECT b.*, l.name as lab_name, l.location
        FROM bookings b 
        JOIN computers c ON c.id=b.computer_id 
        JOIN labs l ON c.lab_id=l.id
        WHERE b.user_id=? AND b.date >= CURDATE() 
        ORDER BY b.date, b.start_time LIMIT 10
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $upcoming = $stmt->get_result();
        
        // Get the very next session for the countdown widget
        if ($upcoming && $upcoming->num_rows > 0) {
            $next_session = $upcoming->fetch_assoc();
            $upcoming->data_seek(0); // Reset pointer for the table display later
        }
    }

    // 3. Fetch all bookings for calendar
    $calendar_bookings_query = "
        SELECT b.date, b.start_time, b.end_time, b.status
        FROM bookings b
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

    // 4. Fetch ALL labs (LIMIT removed)
    $labs_query = "
        SELECT l.*, 
               COUNT(c.id) as total_computers,
               SUM(CASE WHEN c.status = 'available' THEN 1 ELSE 0 END) as available_computers
        FROM labs l 
        LEFT JOIN computers c ON l.id = c.lab_id 
        GROUP BY l.id 
        ORDER BY available_computers DESC, l.name
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
/* Reset & Base */
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f9fc; min-height: 100vh; color: #1f2937; }

/* Sidebar */
.sidebar { position: fixed; left: 0; top: 0; width: 260px; height: 100vh; background: #1e293b; padding: 30px 0; z-index: 100; transition: width 0.3s; }
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

.sidebar-menu { list-style: none; }
.sidebar-menu li { margin-bottom: 5px; }
.sidebar-menu a { display: flex; align-items: center; padding: 14px 25px; color: #cbd5e1; text-decoration: none; transition: all 0.2s ease; font-weight: 500; }
.sidebar-menu a:hover { background: rgba(255,255,255,0.05); color: white; padding-left: 30px; }
.sidebar-menu a.active { background: #3b82f6; color: white; border-right: 4px solid #93c5fd; }
.logout-btn { position: absolute; bottom: 30px; left: 25px; right: 25px; }
.logout-btn a { display: block; padding: 12px 20px; background: #ef4444; color: white; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600; transition: background 0.2s; }
.logout-btn a:hover { background: #dc2626; }

/* Main Content */
.main-content { margin-left: 260px; padding: 30px 40px; }
.top-bar { background: white; padding: 20px 30px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; position: relative; }
.welcome-text h1 { font-size: 24px; font-weight: 700; color: #111827; }
.welcome-text p { color: #6b7280; font-size: 14px; margin-top: 4px; }

.header-right { display: flex; align-items: center; gap: 20px; }

/* Notification Bell */
.notif-wrapper { position: relative; }
.notif-btn { background: #f3f4f6; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #4b5563; transition: background 0.2s; }
.notif-btn:hover { background: #e5e7eb; color: #1f2937; }
.notif-badge { position: absolute; top: -2px; right: -2px; background: #ef4444; color: white; font-size: 10px; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; border: 2px solid white; }
.notif-dropdown { position: absolute; top: 50px; right: 0; width: 300px; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid #f3f4f6; display: none; z-index: 50; overflow: hidden; }
.notif-dropdown.active { display: block; animation: slideDown 0.2s ease-out; }
.notif-header { padding: 15px; border-bottom: 1px solid #f3f4f6; font-weight: 600; font-size: 14px; background: #f9fafb; }
.notif-item { padding: 15px; border-bottom: 1px solid #f3f4f6; font-size: 13px; color: #4b5563; transition: background 0.2s; cursor: pointer; }
.notif-item:hover { background: #f9fafb; }
.notif-item strong { display: block; color: #1f2937; margin-bottom: 2px; }
.notif-time { font-size: 11px; color: #9ca3af; margin-top: 4px; display: block; }

.user-profile { display: flex; align-items: center; gap: 12px; }
.user-avatar { width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #6366f1); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px; box-shadow: 0 2px 10px rgba(59, 130, 246, 0.3); }

/* Next Session Banner */
.next-session-banner { background: linear-gradient(135deg, #4f46e5, #3b82f6); border-radius: 16px; padding: 20px 30px; color: white; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4); }
.ns-content h3 { font-size: 18px; font-weight: 700; margin-bottom: 5px; }
.ns-content p { font-size: 14px; opacity: 0.9; }
.ns-timer { background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 8px; font-family: monospace; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px; backdrop-filter: blur(4px); }

/* Stats Grid */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; margin-bottom: 35px; }
.stat-card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid #f3f4f6; }
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
.stat-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 26px; }
.stat-info h3 { font-size: 28px; font-weight: 800; color: #111827; line-height: 1; margin-bottom: 5px; }
.stat-info p { color: #6b7280; font-size: 14px; font-weight: 500; }
.bg-blue { background: #dbeafe; color: #2563eb; }
.bg-green { background: #dcfce7; color: #16a34a; }
.bg-purple { background: #f3e8ff; color: #9333ea; cursor: pointer; }

/* Sections */
.section-container { background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 30px; border: 1px solid #f3f4f6; overflow: hidden; }
.section-header { padding: 20px 30px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; background: #fff; }
.section-header h2 { font-size: 18px; font-weight: 700; color: #1f2937; }

/* Search & Filters */
.search-wrapper { position: relative; max-width: 300px; width: 100%; }
.search-input { width: 100%; padding: 10px 15px 10px 40px; border: 1px solid #e5e7eb; border-radius: 8px; outline: none; transition: border-color 0.2s; }
.search-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
.search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; }

/* Labs Grid */
.labs-grid { padding: 25px; display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; }
.lab-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; transition: all 0.3s ease; position: relative; overflow: hidden; background: white; display: flex; flex-direction: column; }
.lab-card:hover { border-color: #3b82f6; transform: translateY(-3px); box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }
.lab-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
.lab-title h3 { font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 4px; }
.lab-title p { font-size: 13px; color: #6b7280; display: flex; align-items: center; gap: 4px; }
.status-pill { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; }

/* Lab Amenities */
.lab-amenities { display: flex; gap: 10px; margin-bottom: 15px; }
.amenity { background: #f3f4f6; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #6b7280; }

/* Progress Bar for Capacity */
.capacity-wrapper { margin-top: auto; margin-bottom: 20px; }
.capacity-labels { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; font-weight: 500; }
.progress-bg { width: 100%; height: 8px; background: #f3f4f6; border-radius: 4px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }

.btn-book { display: block; width: 100%; padding: 10px; background: #3b82f6; color: white; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600; transition: background 0.2s; }
.btn-book:hover { background: #2563eb; }

/* Table Filters */
.table-filters { display: flex; gap: 10px; }
.filter-btn { padding: 6px 12px; border: 1px solid #e5e7eb; background: white; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.2s; color: #6b7280; }
.filter-btn.active { background: #3b82f6; color: white; border-color: #3b82f6; }
.filter-btn:hover:not(.active) { background: #f9fafb; border-color: #d1d5db; }

/* Table */
.table-responsive { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th { text-align: left; padding: 16px 25px; background: #f9fafb; color: #6b7280; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
td { padding: 16px 25px; border-bottom: 1px solid #f3f4f6; color: #374151; font-size: 14px; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #f9fafb; }
.status-badge { display: inline-flex; align-items: center; px: 2.5; py: 0.5; border-radius: 9999px; font-size: 12px; font-weight: 500; padding: 4px 12px; }
.status-approved { background: #ecfdf5; color: #047857; }
.status-pending { background: #fffbeb; color: #b45309; }
.status-rejected { background: #fef2f2; color: #b91c1c; }

/* Empty State */
.empty-state { padding: 50px; text-align: center; color: #6b7280; }
.empty-icon { font-size: 40px; margin-bottom: 10px; opacity: 0.5; }

/* Calendar Modal */
.calendar-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.calendar-modal.active { display: flex; animation: fadeIn 0.2s; }
.calendar-container { background: white; border-radius: 16px; width: 90%; max-width: 800px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; }
.calendar-header { padding: 20px 30px; background: #3b82f6; color: white; display: flex; justify-content: space-between; align-items: center; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; padding: 20px; }
.calendar-day-header { text-align: center; font-weight: 600; color: #64748b; font-size: 13px; padding-bottom: 10px; }
.calendar-day { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; border-radius: 8px; cursor: pointer; background: #f8fafc; font-size: 14px; position: relative; }
.calendar-day.today { border: 2px solid #3b82f6; color: #3b82f6; font-weight: 700; }
.calendar-day.has-bookings::after { content: ''; position: absolute; bottom: 6px; width: 6px; height: 6px; background: #3b82f6; border-radius: 50%; }

@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* Mobile Responsive */
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .main-content { margin-left: 0; padding: 20px; }
    .top-bar { flex-direction: column; gap: 15px; align-items: flex-start; }
    .header-right { width: 100%; justify-content: space-between; }
    .next-session-banner { flex-direction: column; text-align: center; gap: 15px; }
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-logo">
        <h2>🖥️ LabEase</h2>
        <p >Computer Lab Booking System</p>
    </div>
    
    <ul class="sidebar-menu">
        <li><a href="dashboard.php" class="active"><span>📊</span>&nbsp; Dashboard</a></li>
        <li><a href="calendar.php"><span>📅</span>&nbsp; Calendar</a></li>
        <li><a href="create.php"><span>➕</span>&nbsp; Book Lab</a></li>
        <li><a href="my_bookings.php"><span>📋</span>&nbsp; My Bookings</a></li>
       
        <li><a href="feedback.php"><span>💬</span>&nbsp; Feedback</a></li>
          <li><a href="logout.php">🚪 Logout</a></li>
    </ul>

    <div class="logout-btn">
        <a href="logout.php">Sign Out</a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
  
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="welcome-text">
            <h1><?= $greeting ?>, <?= htmlspecialchars($user_name) ?>! 👋</h1>
            <p>Here's what's happening with your lab bookings today.</p>
        </div>
        
        <div class="header-right">
            <!-- Notification Bell -->
            <div class="notif-wrapper">
           
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">Notifications (2)</div>
                    <!-- Mock Notifications -->
                    <div class="notif-item">
                        <strong>Booking Approved ✅</strong>
                        Your booking for Lab A on Monday was approved.
                        <span class="notif-time">2 hours ago</span>
                    </div>
                    <div class="notif-item">
                        <strong>New Lab Added 🏢</strong>
                        Physics Lab 2 is now available for booking.
                        <span class="notif-time">Yesterday</span>
                    </div>
                    <div class="notif-item" style="text-align:center; color:#3b82f6; font-weight:600;">View All Notifications</div>
                </div>
            </div>

            <div class="user-profile">
                <div style="text-align: right; margin-right: 10px;">
                    <span style="display: block; font-weight: 600; font-size: 14px;"><?= htmlspecialchars($user_name) ?></span>
                    <span style="display: block; font-size: 12px; color: #6b7280;">Student ID: #<?= $user_id ?></span>
                </div>
                <div class="user-avatar">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Next Session Banner (Conditional) -->
    <?php if ($next_session): 
        $ns_date = strtotime($next_session['date'] . ' ' . $next_session['start_time']);
        $time_diff = $ns_date - time();
        $hours_until = floor($time_diff / 3600);
        
        // Only show if session is in the future
        if ($time_diff > 0):
    ?>
   
    <?php endif; endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon bg-blue">📚</div>
            <div class="stat-info">
                <h3><?= $total_bookings ?></h3>
                <p>Total Bookings</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-green">✅</div>
            <div class="stat-info">
                <h3><?= $approved_bookings ?></h3>
                <p>Approved</p>
            </div>
        </div>
        
        <div class="stat-card" onclick="openCalendar()">
            <div class="stat-icon bg-purple">📅</div>
            <div class="stat-info">
                <h3 style="font-size: 20px;">Calendar</h3>
                <p>View Schedule</p>
            </div>
        </div>
    </div>

    <!-- Labs Section -->
    <div class="section-container">
        <div class="section-header">
            <h2>🏢 Browse Labs</h2>
            <div class="search-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text" id="labSearch" class="search-input" placeholder="Search labs by name..." onkeyup="filterLabs()">
            </div>
        </div>
        
        <div class="labs-grid" id="labsContainer">
            <?php if ($labs_result && $labs_result->num_rows > 0): 
                while($lab = $labs_result->fetch_assoc()): 
                    $total = $lab['total_computers'];
                    $avail = $lab['available_computers'];
                    $percentage = $total > 0 ? round(($avail / $total) * 100) : 0;
                    
                    if ($percentage >= 70) {
                        $color_class = '#10b981'; $bg_class = '#dcfce7'; $status_text = 'High Availability';
                    } elseif ($percentage >= 30) {
                        $color_class = '#f59e0b'; $bg_class = '#fef3c7'; $status_text = 'Filling Fast';
                    } else {
                        $color_class = '#ef4444'; $bg_class = '#fee2e2'; $status_text = 'Almost Full';
                    }
            ?>
            <div class="lab-card" data-name="<?= strtolower(htmlspecialchars($lab['name'])) ?>">
                <div class="lab-header">
                    <div class="lab-title">
                        <h3><?= htmlspecialchars($lab['name']) ?></h3>
                        <p>📍 <?= htmlspecialchars($lab['location']) ?></p>
                    </div>
                    <div class="status-pill" style="background: <?= $bg_class ?>; color: <?= $color_class ?>;">
                        <?= $status_text ?>
                    </div>
                </div>

                <!-- Added Amenities Icons (Static for demo) -->
                <div class="lab-amenities">
                    <div class="amenity" title="Wi-Fi">📶</div>
                    <div class="amenity" title="Projector">📽️</div>
                    <div class="amenity" title="Air Conditioned">❄️</div>
                </div>
                
                <div class="capacity-wrapper">
                    <div class="capacity-labels">
                        <span>Availability</span>
                        <span><?= $avail ?> / <?= $total ?> Seats</span>
                    </div>
                    <div class="progress-bg">
                        <div class="progress-fill" style="width: <?= $percentage ?>%; background-color: <?= $color_class ?>;"></div>
                    </div>
                </div>
                
                <a href="create.php?lab=<?= $lab['id'] ?>" class="btn-book">Book Seat</a>
            </div>
            <?php endwhile; ?>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🏢</div>
                <h3>No Labs Found</h3>
                <p>Please check back later.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Bookings with Filters -->
    <div class="section-container">
        <div class="section-header">
            <div style="display:flex; align-items:center; gap: 15px;">
                <h2>📅 Upcoming Sessions</h2>
                <div class="table-filters">
                    <button class="filter-btn active" onclick="filterTable('all', this)">All</button>
                    <button class="filter-btn" onclick="filterTable('approved', this)">Approved</button>
                    <button class="filter-btn" onclick="filterTable('pending', this)">Pending</button>
                </div>
            </div>
            <a href="my_bookings.php" style="color: #3b82f6; text-decoration: none; font-size: 14px; font-weight: 600;">View Full History →</a>
        </div>
        
        <div class="table-responsive">
            <?php if($upcoming && $upcoming->num_rows > 0): ?>
                <table id="bookingsTable">
                    <thead>
                        <tr>
                            <th width="35%">Lab Name</th>
                            <th width="20%">Location</th>
                            <th width="20%">Date</th>
                            <th width="15%">Time</th>
                            <th width="10%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($b = $upcoming->fetch_assoc()): ?>
                        <tr class="booking-row" data-status="<?= strtolower($b['status']) ?>">
                            <td>
                                <strong><?= htmlspecialchars($b['lab_name']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($b['location']) ?></td>
                            <td><?= date('M d, Y', strtotime($b['date'])) ?></td>
                            <td>
                                <?= date('g:i A', strtotime($b['start_time'])) ?> - 
                                <?= date('g:i A', strtotime($b['end_time'])) ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= strtolower($b['status']) ?>">
                                    <?= ucfirst(htmlspecialchars($b['status'])) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>No upcoming bookings scheduled.</p>
                    <a href="create.php" style="color: #3b82f6; font-weight: 600; text-decoration: none; margin-top: 10px; display: inline-block;">Make a Reservation</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Calendar Modal -->
<div class="calendar-modal" id="calendarModal">
    <div class="calendar-container">
        <div class="calendar-header">
            <h2 id="currentMonthYear">Loading...</h2>
            <div style="display: flex; gap: 10px;">
                <button onclick="previousMonth()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 5px 10px; border-radius: 4px; cursor: pointer;">←</button>
                <button onclick="nextMonth()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 5px 10px; border-radius: 4px; cursor: pointer;">→</button>
                <button onclick="closeCalendar()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer; margin-left: 10px;">✕</button>
            </div>
        </div>
        <div style="padding: 10px;">
             <div class="calendar-grid" style="border-bottom: 1px solid #eee; margin-bottom: 10px;">
                <div class="calendar-day-header">Sun</div><div class="calendar-day-header">Mon</div><div class="calendar-day-header">Tue</div><div class="calendar-day-header">Wed</div><div class="calendar-day-header">Thu</div><div class="calendar-day-header">Fri</div><div class="calendar-day-header">Sat</div>
             </div>
             <div class="calendar-grid" id="calendarDays"></div>
        </div>
    </div>
</div>

<script>
// Toggle Notifications
function toggleNotifications() {
    const dropdown = document.getElementById('notifDropdown');
    dropdown.classList.toggle('active');
}

// Close dropdowns when clicking outside
window.onclick = function(event) {
    if (!event.target.matches('.notif-btn') && !event.target.matches('.notif-btn *')) {
        var dropdowns = document.getElementsByClassName("notif-dropdown");
        for (var i = 0; i < dropdowns.length; i++) {
            var openDropdown = dropdowns[i];
            if (openDropdown.classList.contains('active')) {
                openDropdown.classList.remove('active');
            }
        }
    }
}

// Filter Labs Script
function filterLabs() {
    const input = document.getElementById('labSearch');
    const filter = input.value.toLowerCase();
    const cards = document.querySelectorAll('.lab-card');
    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        if (name.includes(filter)) card.style.display = "";
        else card.style.display = "none";
    });
}

// Filter Table Script
function filterTable(status, btn) {
    // Update active button state
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const rows = document.querySelectorAll('.booking-row');
    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        if (status === 'all' || rowStatus === status) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}

// Calendar Logic
const calendarData = <?= json_encode($calendar_data) ?>;
let currentDate = new Date();

function openCalendar() {
    document.getElementById('calendarModal').classList.add('active');
    renderCalendar();
}
function closeCalendar() { document.getElementById('calendarModal').classList.remove('active'); }

function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    document.getElementById('currentMonthYear').textContent = `${monthNames[month]} ${year}`;
    
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const calendarDaysContainer = document.getElementById('calendarDays');
    calendarDaysContainer.innerHTML = '';
    
    for (let i = 0; i < firstDay; i++) calendarDaysContainer.appendChild(document.createElement('div'));
    
    const today = new Date();
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayDiv = document.createElement('div');
        dayDiv.className = 'calendar-day';
        if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) dayDiv.classList.add('today');
        if (calendarData[dateStr]) {
            dayDiv.classList.add('has-bookings');
            dayDiv.title = `${calendarData[dateStr].length} Booking(s)`;
        }
        dayDiv.textContent = day;
        calendarDaysContainer.appendChild(dayDiv);
    }
}
function previousMonth() { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); }
function nextMonth() { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); }
document.getElementById('calendarModal').addEventListener('click', (e) => { if (e.target.id === 'calendarModal') closeCalendar(); });
</script>

</body>
</html>