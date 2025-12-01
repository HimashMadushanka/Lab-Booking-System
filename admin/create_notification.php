<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require '../db.php';

$success = "";
$error = "";

// --- Create or Update Notification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title']);
    $message    = trim($_POST['message']);
    $type       = $_POST['type'];
    $priority   = $_POST['priority'];
    $publish_at = !empty($_POST['publish_at']) ? $_POST['publish_at'] : NULL;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : NULL;

    if (!empty($title) && !empty($message)) {
        if (!empty($_POST['id'])) {
            // --- Update ---
            $stmt = $mysqli->prepare("UPDATE admin_notifications SET title=?, message=?, type=?, priority=?, publish_at=?, expires_at=? WHERE id=?");
            $stmt->bind_param("ssssssi", $title, $message, $type, $priority, $publish_at, $expires_at, $_POST['id']);
            $success = $stmt->execute() ? "Notification updated successfully!" : "Failed to update notification.";
        } else {
            // --- Create ---
            $stmt = $mysqli->prepare("INSERT INTO admin_notifications (title, message, type, priority, created_by, publish_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssisss", $title, $message, $type, $priority, $_SESSION['admin_id'], $publish_at, $expires_at);
            if ($stmt->execute()) {
                $notification_id = $stmt->insert_id;

                // --- Send to all users (simple example) ---
                $users = $mysqli->query("SELECT id FROM users");
                $sendStmt = $mysqli->prepare("INSERT INTO user_notifications (user_id, notification_id) VALUES (?, ?)");
                while ($u = $users->fetch_assoc()) {
                    $sendStmt->bind_param("ii", $u['id'], $notification_id);
                    $sendStmt->execute();
                }
                $success = "Notification sent successfully!";
            } else {
                $error = "Failed to create notification.";
            }
        }
    } else {
        $error = "Title and message are required.";
    }
}

// --- Delete Notification ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $mysqli->query("DELETE FROM admin_notifications WHERE id=$id");
    $mysqli->query("DELETE FROM user_notifications WHERE notification_id=$id");
    $success = "Notification deleted successfully!";
}

// --- Fetch all notifications ---
$result = $mysqli->query("SELECT * FROM admin_notifications ORDER BY created_at DESC");

// Count stats
$total_notifications = $result->num_rows;
$high_priority = $mysqli->query("SELECT COUNT(*) as cnt FROM admin_notifications WHERE priority='high'")->fetch_assoc()['cnt'];
$active_notifications = $mysqli->query("SELECT COUNT(*) as cnt FROM admin_notifications WHERE is_published=1 AND (expires_at IS NULL OR expires_at >= NOW())")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Notifications | Lab Management System</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #b8eaf8ff;
    padding: 20px;
}

/* Header */
.header {
    background: #2c3e50;
    color: white;
    padding: 25px 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header h1 {
    font-size: 28px;
    margin-bottom: 5px;
}

.header p {
    color: #bdc3c7;
    font-size: 14px;
}

.header-buttons {
    display: flex;
    gap: 10px;
}

.back-btn {
    background: #e74c3c;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: background 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.back-btn {
    background: #3498db;
}


.back-btn:hover {
    background: #2980b9;
}

/* Alerts */
.alert {
    padding: 15px 20px;
    margin-bottom: 20px;
    border-radius: 8px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

/* Stats Grid */
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
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card h3 {
    font-size: 32px;
    color: #2c3e50;
    margin-bottom: 5px;
}

.stat-card p {
    color: #7f8c8d;
    font-size: 14px;
}

.stat-card.high {
    border-left-color: #e74c3c;
}

.stat-card.active {
    border-left-color: #27ae60;
}

/* Form Section */
.form-section {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.form-section h2 {
    color: #2c3e50;
    margin-bottom: 20px;
    font-size: 22px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #2c3e50;
    font-weight: 600;
    font-size: 14px;
}

.form-group input[type="text"],
.form-group textarea,
.form-group select,
.form-group input[type="datetime-local"] {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #ecf0f1;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #3498db;
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.submit-btn {
    background: #27ae60;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s ease;
}

.submit-btn:hover {
    background: #229954;
}

/* Table Section */
.table-section {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.section-header {
    padding: 25px 30px;
    border-bottom: 2px solid #ecf0f1;
}

.section-header h2 {
    color: #2c3e50;
    font-size: 22px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table thead {
    background: #34495e;
}

table th {
    padding: 15px 20px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: white;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

table td {
    padding: 18px 20px;
    color: #2c3e50;
    font-size: 14px;
    border-bottom: 1px solid #ecf0f1;
}

table tbody tr {
    transition: background 0.2s ease;
}

table tbody tr:hover {
    background: #f8f9fa;
}

table tbody tr:last-child td {
    border-bottom: none;
}

/* Type Icons and Badges */
.type-icon {
    font-size: 20px;
    margin-right: 5px;
}

.type-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-transform: capitalize;
    display: inline-block;
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

.priority-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
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

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
}

.btn {
    padding: 8px 14px;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s ease;
}

.btn-edit {
    background: #dbeafe;
    color: #1d4ed8;
}

.btn-edit:hover {
    background: #bfdbfe;
    color: #1e40af;
}

.btn-delete {
    background: #fee2e2;
    color: #dc2626;
}

.btn-delete:hover {
    background: #fecaca;
    color: #b91c1c;
}

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
    color: #7f8c8d;
    font-size: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    body {
        padding: 10px;
    }
    
    .header-content {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    table th, table td {
        padding: 12px 15px;
        font-size: 13px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-content">
        <div>
            <h1>📢 Admin Notifications</h1>
            <p>Create and manage system-wide notifications</p>
        </div>
        <div class="header-buttons">
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
 
        </div>
    </div>
</div>

<!-- Alerts -->
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <h3><?= $total_notifications ?></h3>
        <p>Total Notifications</p>
    </div>
    <div class="stat-card high">
        <h3><?= $high_priority ?></h3>
        <p>High Priority</p>
    </div>
    <div class="stat-card active">
        <h3><?= $active_notifications ?></h3>
        <p>Active Notifications</p>
    </div>
</div>

<!-- Create/Edit Form -->
<div class="form-section">
    <h2>✏️ Create / Edit Notification</h2>
    <form method="POST">
        <input type="hidden" name="id" id="notif_id">
        
        <div class="form-group">
            <label>Title *</label>
            <input type="text" name="title" id="title" placeholder="Enter notification title" required>
        </div>

        <div class="form-group">
            <label>Message *</label>
            <textarea name="message" id="message" placeholder="Enter notification message" required></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Type</label>
                <select name="type" id="type">
                    <option value="announcement">📢 Announcement</option>
                    <option value="alert">⚠️ Alert</option>
                    <option value="reminder">⏰ Reminder</option>
                    <option value="update">📝 Update</option>
                </select>
            </div>

            <div class="form-group">
                <label>Priority</label>
                <select name="priority" id="priority">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Publish At (optional)</label>
                <input type="datetime-local" name="publish_at" id="publish_at">
            </div>

            <div class="form-group">
                <label>Expires At (optional)</label>
                <input type="datetime-local" name="expires_at" id="expires_at">
            </div>
        </div>

        <button type="submit" class="submit-btn">💾 Save Notification</button>
    </form>
</div>

<!-- All Notifications Table -->
<div class="table-section">
    <div class="section-header">
        <h2>📋 All Notifications</h2>
    </div>
    
    <div class="table-wrapper">
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Type</th>
                        <th>Priority</th>
                        <th>Publish At</th>
                        <th>Expires At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()): 
                        $type_icons = [
                            'announcement' => '📢',
                            'alert' => '⚠️',
                            'reminder' => '⏰',
                            'update' => '📝'
                        ];
                        $icon = $type_icons[$row['type']] ?? '🔔';
                    ?>
                    <tr>
                        <td><strong>#<?= $row['id'] ?></strong></td>
                        <td>
                            <span class="type-icon"><?= $icon ?></span>
                            <?= htmlspecialchars($row['title']) ?>
                        </td>
                        <td><?= htmlspecialchars(substr($row['message'], 0, 50)) . (strlen($row['message']) > 50 ? '...' : '') ?></td>
                        <td>
                            <span class="type-badge <?= $row['type'] ?>">
                                <?= ucfirst($row['type']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="priority-badge <?= $row['priority'] ?>">
                                <?= ucfirst($row['priority']) ?>
                            </span>
                        </td>
                        <td><?= $row['publish_at'] ? date('M d, Y g:i A', strtotime($row['publish_at'])) : '-' ?></td>
                        <td><?= $row['expires_at'] ? date('M d, Y g:i A', strtotime($row['expires_at'])) : '-' ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="#" class="btn btn-edit" 
                                   onclick="editNotification(<?= $row['id'] ?>,'<?= addslashes($row['title']) ?>','<?= addslashes($row['message']) ?>','<?= $row['type'] ?>','<?= $row['priority'] ?>','<?= $row['publish_at'] ?>','<?= $row['expires_at'] ?>'); return false;">
                                    ✏️ Edit
                                </a>
                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-delete" 
                                   onclick="return confirm('Are you sure you want to delete this notification?')">
                                    🗑️ Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>No notifications created yet</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function editNotification(id, title, message, type, priority, publish_at, expires_at) {
    document.getElementById('notif_id').value = id;
    document.getElementById('title').value = title;
    document.getElementById('message').value = message;
    document.getElementById('type').value = type;
    document.getElementById('priority').value = priority;
    document.getElementById('publish_at').value = publish_at && publish_at !== 'null' ? publish_at.replace(' ', 'T') : '';
    document.getElementById('expires_at').value = expires_at && expires_at !== 'null' ? expires_at.replace(' ', 'T') : '';
    
    // Scroll to form
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

</body>
</html>