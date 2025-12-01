<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'];

$sql = "
    SELECT COUNT(*) as unread_count 
    FROM notifications 
    WHERE (user_id = ? OR user_id IS NULL) 
    AND is_read = FALSE
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode(['count' => $row['unread_count']]);
?>