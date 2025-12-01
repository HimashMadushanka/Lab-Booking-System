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
?>

<!DOCTYPE html>
<html>
<head>
<title>Your Notifications</title>
</head>
<body>

<h2>Your Notifications</h2>

<?php while ($n = $result->fetch_assoc()): ?>
<div style="border:1px solid #ccc; padding:10px; margin:10px;">
    <strong><?php echo $n['title']; ?></strong>
    <p><?php echo $n['message']; ?></p>
    <small>Sent: <?php echo $n['sent_at']; ?></small><br>

    <?php if (!$n['is_read']): ?>
        <a href="read_notification.php?id=<?php echo $n['uid']; ?>">Mark as Read</a>
    <?php else: ?>
        <span style="color:green;">Read</span>
    <?php endif; ?>
</div>
<?php endwhile; ?>

</body>
</html>
