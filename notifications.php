<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$sql = "
    SELECT un.id AS uid, an.title, an.message, an.type, an.priority, un.is_read, un.sent_at
    FROM user_notifications un
    JOIN admin_notifications an ON an.id = un.notification_id
    WHERE un.user_id = ?
      AND an.is_published = 1
      AND (an.publish_at IS NULL OR an.publish_at <= NOW())
      AND (an.expires_at IS NULL OR an.expires_at >= NOW())
    ORDER BY un.sent_at DESC
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Count unread notifications
$unread_count = 0;
mysqli_data_seek($result, 0);
while($row = $result->fetch_assoc()) {
    if(!$row['is_read']) $unread_count++;
}
mysqli_data_seek($result, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications | Lab Management System</title>
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

.page-header {
  background: white;
  padding: 25px 30px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  margin-bottom: 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.page-header-content h1 {
  font-size: 28px;
  color: #1e293b;
  font-weight: 700;
  margin-bottom: 5px;
}

.page-header-content p {
  color: #64748b;
  font-size: 15px;
}

.unread-badge {
  background: #ef4444;
  color: white;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
}

/* Notifications Section */
.notifications-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  overflow: hidden;
}

.section-header {
  padding: 25px 30px;
  border-bottom: 1px solid #e2e8f0;
}

.section-header h2 {
  font-size: 20px;
  color: #1e293b;
  font-weight: 700;
}

/* Notification Card */
.notification-card {
  padding: 20px 30px;
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.2s ease;
}

.notification-card:hover {
  background: #f8fafc;
}

.notification-card:last-child {
  border-bottom: none;
}

.notification-card.unread {
  background: #eff6ff;
  border-left: 4px solid #3b82f6;
}

.notification-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 10px;
  gap: 15px;
}

.notification-title {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
}

.notification-icon {
  font-size: 24px;
  flex-shrink: 0;
}

.notification-title h3 {
  font-size: 16px;
  color: #1e293b;
  font-weight: 600;
  line-height: 1.4;
}

.notification-badges {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-shrink: 0;
}

.priority-badge {
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.priority-badge.high {
  background: #fee2e2;
  color: #991b1b;
}

.priority-badge.medium {
  background: #fef3c7;
  color: #92400e;
}

.priority-badge.low {
  background: #dbeafe;
  color: #1e40af;
}

.type-badge {
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: capitalize;
}

.type-badge.announcement {
  background: #e0e7ff;
  color: #3730a3;
}

.type-badge.alert {
  background: #fee2e2;
  color: #991b1b;
}

.type-badge.reminder {
  background: #fef3c7;
  color: #92400e;
}

.type-badge.update {
  background: #d1fae5;
  color: #065f46;
}

.notification-message {
  color: #475569;
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 12px;
  padding-left: 34px;
}

.notification-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-left: 34px;
}

.notification-time {
  color: #94a3b8;
  font-size: 13px;
}

.mark-read-btn {
  padding: 6px 14px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.2s ease;
}

.mark-read-btn:hover {
  background: #2563eb;
}

.read-status {
  color: #10b981;
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 5px;
}

/* Empty State */
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
  
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }
  
  .notification-card {
    padding: 15px 20px;
  }
  
  .notification-header {
    flex-direction: column;
    gap: 10px;
  }
  
  .notification-badges {
    align-self: flex-start;
  }
  
  .notification-message,
  .notification-footer {
    padding-left: 0;
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
    <li><a href="analytics.php"><span>📈</span> Analytics</a></li>
    <li><a href="chat.php"><span>💬</span> Chat with Admin</a></li>
    <li><a href="notifications.php" class="active"><span>🔔</span> Notifications</a></li>
    <li><a href="feedback.php"><span>💬</span> Give Feedback</a></li>
  </ul>
  
  <div class="logout-btn">
    <a href="logout.php">Logout</a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content">
  
  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-content">
      <h1>🔔 Notifications</h1>
      <p>Stay updated with the latest announcements and alerts</p>
    </div>
    <?php if ($unread_count > 0): ?>
      <div class="unread-badge">
        <?= $unread_count ?> Unread
      </div>
    <?php endif; ?>
  </div>

  <!-- Notifications Container -->
  <div class="notifications-container">
    <div class="section-header">
      <h2>All Notifications</h2>
    </div>
    
    <?php if ($result->num_rows > 0): ?>
      <?php while ($n = $result->fetch_assoc()): ?>
        <div class="notification-card <?= !$n['is_read'] ? 'unread' : '' ?>">
          <div class="notification-header">
            <div class="notification-title">
              <span class="notification-icon">
                <?php
                  switch($n['type']) {
                    case 'announcement': echo '📢'; break;
                    case 'alert': echo '⚠️'; break;
                    case 'reminder': echo '⏰'; break;
                    case 'update': echo '📝'; break;
                    default: echo '🔔';
                  }
                ?>
              </span>
              <h3><?= htmlspecialchars($n['title']) ?></h3>
            </div>
            <div class="notification-badges">
              <span class="type-badge <?= strtolower($n['type']) ?>">
                <?= ucfirst($n['type']) ?>
              </span>
              <span class="priority-badge <?= strtolower($n['priority']) ?>">
                <?= ucfirst($n['priority']) ?>
              </span>
            </div>
          </div>
          
          <div class="notification-message">
            <?= nl2br(htmlspecialchars($n['message'])) ?>
          </div>
          
          <div class="notification-footer">
            <span class="notification-time">
              📅 <?= date('M d, Y g:i A', strtotime($n['sent_at'])) ?>
            </span>
            
            <?php if (!$n['is_read']): ?>
              <a href="read_notification.php?id=<?= $n['uid'] ?>" class="mark-read-btn">
                ✓ Mark as Read
              </a>
            <?php else: ?>
              <span class="read-status">
                ✓ Read
              </span>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-state-icon">🔔</div>
        <p>No notifications yet</p>
        <a href="dashboard.php">Go to Dashboard</a>
      </div>
    <?php endif; ?>
  </div>

</div>

</body>
</html>