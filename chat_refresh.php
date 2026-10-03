<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['newMessages' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];
$conversation_id = isset($_GET['conversation']) ? intval($_GET['conversation']) : 0;

if ($conversation_id > 0) {
    // Check if there are new messages from admin
    $check_sql = "
        SELECT COUNT(*) as new_count 
        FROM chat_messages 
        WHERE conversation_id = ? 
        AND sender_id != ? 
        AND is_read = FALSE
    ";
    $check_stmt = $mysqli->prepare($check_sql);
    $check_stmt->bind_param("ii", $conversation_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    echo json_encode(['newMessages' => $row['new_count'] > 0]);
} else {
    echo json_encode(['newMessages' => false]);
}
?>