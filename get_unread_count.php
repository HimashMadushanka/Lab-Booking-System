<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['count' => 0]);
    exit;
}

require 'db.php';

$user_id = $_SESSION['user_id'];

$sql = "SELECT COUNT(*) as unread_count 
        FROM user_notifications un
        JOIN admin_notifications an ON un.notification_id = an.id
        WHERE un.user_id = ? 
        AND un.is_read = FALSE
        AND an.deleted_at IS NULL
        AND (an.expires_at IS NULL OR an.expires_at > NOW())";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode(['count' => $data['unread_count'] ?? 0]);