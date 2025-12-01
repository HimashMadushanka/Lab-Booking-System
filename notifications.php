<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$message = '';

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    $update_sql = "UPDATE notifications SET is_read = TRUE WHERE (user_id = ? OR user_id IS NULL)";
    $update_stmt = $mysqli->prepare($update_sql);
    $update_stmt->bind_param("i", $user_id);
    if ($update_stmt->execute()) {
        $message = "<div class='alert alert-success'>All notifications marked as read!</div>";
    }
}

// Mark single notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);
    $update_sql = "UPDATE notifications SET is_read = TRUE WHERE id = ? AND (user_id = ? OR user_id IS NULL)";
    $update_stmt = $mysqli->prepare($update_sql);
    $update_stmt->bind_param("ii", $notification_id, $user_id);
    $update_stmt->execute();
}

// Fetch all notifications
$notifications_sql = "
    SELECT * FROM notifications 
    WHERE (user_id = ? OR user_id IS NULL)
    ORDER BY created_at DESC
";

$notifications_stmt = $mysqli->prepare($notifications_sql);
$notifications_stmt->bind_param("i", $user_id);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();

// Count unread
$unread_count = 0;
$all_notifications = [];
while($notification = $notifications_result->fetch_assoc()) {
    if (!$notification['is_read']) {
        $unread_count++;
    }
    $all_notifications[] = $notification;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications | LabEase</title>
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

/* Sidebar (same as before) */
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

.header-actions {
  display: flex;
  gap: 15px;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background: #2563eb;
}

.btn-secondary {
  background: #6b7280;
  color: white;
}

.btn-secondary:hover {
  background: #4b5563;
}

/* Alert Messages */
.alert {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 20px;
  font-weight: 500;
}

.alert-success {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.alert-error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

/* Notifications Container */
.notifications-container {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
  overflow: hidden;
}

/* Tabs */
.notification-tabs {
  display: flex;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
  padding: 0 20px;
}

.tab-btn {
  padding: 15px 25px;
  background: none;
  border: none;
  border-bottom: 3px solid transparent;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
}

.tab-btn.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
}

.tab-btn:hover:not(.active) {
  color: #4b5563;
}

.tab-badge {
  position: absolute;
  top: 8px;
  right: 5px;
  background: #ef4444;
  color: white;
  font-size: 10px;
  min-width: 18px;
  height: 18px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
}

/* Notifications List */
.notifications-list {
  padding: 0;
}

.notification-item {
  padding: 20px 25px;
  border-bottom: 1px solid #f3f4f6;
  display: flex;
  gap: 15px;
  align-items: flex-start;
  transition: background 0.2s ease;
  position: relative;
}

.notification-item:hover {
  background: #f9fafb;
}

.notification-item.unread {
  background: #f0f9ff;
}

.notification-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}

.icon-booking { background: #dbeafe; color: #2563eb; }
.icon-announcement { background: #f3e8ff; color: #9333ea; }
.icon-maintenance { background: #fef3c7; color: #d97706; }
.icon-alert { background: #fee2e2; color: #dc2626; }

.notification-content {
  flex: 1;
}

.notification-title {
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 5px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.notification-message {
  color: #4b5563;
  font-size: 14px;
  line-height: 1.5;
  margin-bottom: 8px;
}

.notification-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: #9ca3af;
}

.notification-time {
  font-family: monospace;
}

.unread-badge {
  background: #3b82f6;
  color: white;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 600;
}

.notification-actions {
  display: flex;
  gap: 10px;
}

.action-btn {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  padding: 5px;
  border-radius: 4px;
  transition: all 0.2s ease;
}

.action-btn:hover {
  background: #f3f4f6;
  color: #4b5563;
}

.mark-read-btn:hover {
  color: #10b981;
}

.delete-btn:hover {
  color: #ef4444;
}

/* Empty State */
.empty-state {
  padding: 60px 30px;
  text-align: center;
  color: #6b7280;
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 15px;
  opacity: 0.3;
}

.empty-state p {
  margin-bottom: 20px;
  font-size: 15px;
}

/* Notification Types Filter */
.filter-buttons {
  display: flex;
  gap: 10px;
  padding: 20px;
  border-bottom: 1px solid #f3f4f6;
  background: #f9fafb;
  flex-wrap: wrap;
}

.filter-btn {
  padding: 6px 12px;
  border: 1px solid #e5e7eb;
  background: white;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  color: #6b7280;
}

.filter-btn.active {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

.filter-btn:hover:not(.active) {
  background: #f9fafb;
  border-color: #d1d5db;
}

/* Responsive */
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
  
  .header-actions {
    flex-direction: column;
    width: 100%;
  }
  
  .btn {
    justify-content: center;
  }
  
  .notification-item {
    flex-direction: column;
    padding: 15px;
  }
  
  .notification-actions {
    position: absolute;
    top: 15px;
    right: 15px;
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
    <li><a href="analytics.php"><span>📈</span> Analytics</a></li>
    <li><a href="calendar.php"><span>📅</span> Calendar View</a></li>
    <li><a href="create.php"><span>➕</span> Book a Lab</a></li>
    <li><a href="my_bookings.php"><span>📋</span> My Bookings</a></li>
    <li><a href="notifications.php" class="active"><span>🔔</span> Notifications</a></li>
    <li><a href="feedback.php"><span>💬</span> Give Feedback</a></li>
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
      <h1>🔔 Notifications</h1>
      <p>Stay updated with important announcements and alerts</p>
    </div>
    
    <div class="header-actions">
      <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
      <a href="notifications.php?mark_all_read=true" class="btn btn-primary">Mark All as Read</a>
    </div>
  </div>

  <?= $message ?>

  <!-- Filter Buttons -->
  <div class="filter-buttons">
    <button class="filter-btn active" onclick="filterNotifications('all')">All</button>
    <button class="filter-btn" onclick="filterNotifications('unread')">Unread (<?= $unread_count ?>)</button>
    <button class="filter-btn" onclick="filterNotifications('booking')">Booking</button>
    <button class="filter-btn" onclick="filterNotifications('announcement')">Announcements</button>
    <button class="filter-btn" onclick="filterNotifications('maintenance')">Maintenance</button>
    <button class="filter-btn" onclick="filterNotifications('alert')">Alerts</button>
  </div>

  <!-- Notifications Container -->
  <div class="notifications-container">
    <?php if (!empty($all_notifications)): ?>
      <div class="notifications-list" id="notificationsList">
        <?php foreach($all_notifications as $notification): ?>
          <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>" data-type="<?= $notification['type'] ?>">
            <div class="notification-icon icon-<?= $notification['type'] ?>">
              <?php if($notification['type'] == 'booking'): ?>📅
              <?php elseif($notification['type'] == 'maintenance'): ?>🔧
              <?php elseif($notification['type'] == 'alert'): ?>⚠️
              <?php else: ?>📢
              <?php endif; ?>
            </div>
            
            <div class="notification-content">
              <div class="notification-title">
                <span><?= htmlspecialchars($notification['title']) ?></span>
                <?php if(!$notification['is_read']): ?>
                  <span class="unread-badge">NEW</span>
                <?php endif; ?>
              </div>
              
              <div class="notification-message">
                <?= htmlspecialchars($notification['message']) ?>
              </div>
              
              <div class="notification-meta">
                <span class="notification-time">
                  <?= date('M d, Y h:i A', strtotime($notification['created_at'])) ?>
                </span>
                <span>
                  <?php if(is_null($notification['user_id'])): ?>
                    📢 Broadcast Message
                  <?php else: ?>
                    👤 Personal Message
                  <?php endif; ?>
                </span>
              </div>
            </div>
            
            <div class="notification-actions">
              <?php if(!$notification['is_read']): ?>
                <button class="action-btn mark-read-btn" onclick="markAsRead(<?= $notification['id'] ?>, this)" title="Mark as Read">
                  ✅
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">🔔</div>
        <h3>No Notifications Yet</h3>
        <p>You don't have any notifications at the moment.</p>
        <p>Check back later for updates from administrators.</p>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
// Filter notifications
function filterNotifications(type) {
    const buttons = document.querySelectorAll('.filter-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    const notifications = document.querySelectorAll('.notification-item');
    notifications.forEach(notification => {
        if (type === 'all') {
            notification.style.display = 'flex';
        } else if (type === 'unread') {
            if (notification.classList.contains('unread')) {
                notification.style.display = 'flex';
            } else {
                notification.style.display = 'none';
            }
        } else {
            if (notification.getAttribute('data-type') === type) {
                notification.style.display = 'flex';
            } else {
                notification.style.display = 'none';
            }
        }
    });
}

// Mark notification as read
function markAsRead(notificationId, button) {
    fetch(`mark_read.php?id=${notificationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationItem = button.closest('.notification-item');
                notificationItem.classList.remove('unread');
                
                // Remove the "NEW" badge
                const badge = notificationItem.querySelector('.unread-badge');
                if (badge) {
                    badge.remove();
                }
                
                // Remove the mark as read button
                button.remove();
                
                // Update unread count
                updateUnreadCount();
            }
        })
        .catch(error => {
            console.error('Error marking as read:', error);
        });
}

// Mark all as read
function markAllAsRead() {
    if (confirm('Mark all notifications as read?')) {
        fetch('mark_read.php?all=true')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page
                    window.location.reload();
                }
            });
    }
}

// Update unread badge count (for dropdown)
function updateUnreadCount() {
    // This would typically update the notification bell count
    // For now, we'll just reload the count from server
    fetch('get_unread_count.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notif-badge');
            if (data.count > 0) {
                if (!badge) {
                    // Create badge if it doesn't exist
                    const bell = document.querySelector('.notif-btn');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notif-badge';
                    newBadge.textContent = data.count;
                    bell.appendChild(newBadge);
                } else {
                    badge.textContent = data.count;
                }
            } else if (badge) {
                badge.remove();
            }
        });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Auto mark as read on click in dropdown
    const dropdownItems = document.querySelectorAll('.notif-item');
    dropdownItems.forEach(item => {
        item.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-id');
            if (notificationId) {
                fetch(`mark_read.php?id=${notificationId}`);
                this.classList.remove('unread');
                
                // Update count
                const badge = this.querySelector('.unread-badge');
                if (badge) {
                    badge.remove();
                }
            }
        });
    });
});
</script>

</body>
</html>