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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Notifications</title>
</head>
<body>
<h2>Admin Notifications</h2>

<?php if ($success) echo "<p style='color:green;'>$success</p>"; ?>
<?php if ($error) echo "<p style='color:red;'>$error</p>"; ?>

<h3>Create / Edit Notification</h3>
<form method="POST">
    <input type="hidden" name="id" id="notif_id">
    <label>Title:</label><br>
    <input type="text" name="title" id="title" required><br><br>

    <label>Message:</label><br>
    <textarea name="message" id="message" required></textarea><br><br>

    <label>Type:</label>
    <select name="type" id="type">
        <option value="info">Info</option>
        <option value="warning">Warning</option>
        <option value="danger">Danger</option>
        <option value="success">Success</option>
    </select><br><br>

    <label>Priority:</label>
    <select name="priority" id="priority">
        <option value="low">Low</option>
        <option value="medium">Medium</option>
        <option value="high">High</option>
    </select><br><br>

    <label>Publish At (optional):</label>
    <input type="datetime-local" name="publish_at" id="publish_at"><br><br>

    <label>Expires At (optional):</label>
    <input type="datetime-local" name="expires_at" id="expires_at"><br><br>

    <button type="submit">Save Notification</button>
</form>

<h3>All Notifications</h3>
<table border="1" cellpadding="5">
    <tr>
        <th>ID</th><th>Title</th><th>Message</th><th>Type</th><th>Priority</th>
        <th>Publish At</th><th>Expires At</th><th>Actions</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= $row['id'] ?></td>
        <td><?= $row['title'] ?></td>
        <td><?= $row['message'] ?></td>
        <td><?= $row['type'] ?></td>
        <td><?= $row['priority'] ?></td>
        <td><?= $row['publish_at'] ?></td>
        <td><?= $row['expires_at'] ?></td>
        <td>
            <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this notification?')">Delete</a> |
            <a href="#" onclick="editNotification(<?= $row['id'] ?>,'<?= addslashes($row['title']) ?>','<?= addslashes($row['message']) ?>','<?= $row['type'] ?>','<?= $row['priority'] ?>','<?= $row['publish_at'] ?>','<?= $row['expires_at'] ?>')">Edit</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<script>
function editNotification(id, title, message, type, priority, publish_at, expires_at) {
    document.getElementById('notif_id').value = id;
    document.getElementById('title').value = title;
    document.getElementById('message').value = message;
    document.getElementById('type').value = type;
    document.getElementById('priority').value = priority;
    document.getElementById('publish_at').value = publish_at ? publish_at.replace(' ', 'T') : '';
    document.getElementById('expires_at').value = expires_at ? expires_at.replace(' ', 'T') : '';
}
</script>

</body>
</html>
