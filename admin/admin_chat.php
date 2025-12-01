<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require '../db.php';

// Get or create admin user ID
$admin_id = null;
$admin_name = "Admin";

// Method 1: Check if admin exists in users table
$admin_check_sql = "SELECT id, name FROM users WHERE role = 'admin' OR email = 'admin@labease.com' LIMIT 1";
$admin_check_result = $mysqli->query($admin_check_sql);

if ($admin_check_result->num_rows > 0) {
    $admin_data = $admin_check_result->fetch_assoc();
    $admin_id = $admin_data['id'];
    $admin_name = $admin_data['name'];
} else {
    // Method 2: Create admin user if doesn't exist
    $create_admin_sql = "INSERT INTO users (name, email, password, role, created_at) 
                         VALUES ('System Admin', 'admin@labease.com', 'e10adc3949ba59abbe56e057f20f883e', 'admin', NOW())";
    
    if ($mysqli->query($create_admin_sql)) {
        $admin_id = $mysqli->insert_id;
        $admin_name = 'System Admin';
    } else {
        // Method 3: Use first available user as fallback
        $fallback_sql = "SELECT id, name FROM users LIMIT 1";
        $fallback_result = $mysqli->query($fallback_sql);
        if ($fallback_result->num_rows > 0) {
            $fallback_data = $fallback_result->fetch_assoc();
            $admin_id = $fallback_data['id'];
            $admin_name = $fallback_data['name'] . ' (Admin)';
        } else {
            die("Error: No users found in database. Please contact system administrator.");
        }
    }
}

// Handle admin reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $conversation_id = intval($_POST['conversation_id']);
    $message = trim($_POST['message']);
    
    if (!empty($message) && $admin_id !== null) {
        // Verify conversation exists
        $verify_sql = "SELECT id FROM chat_conversations WHERE id = ?";
        $verify_stmt = $mysqli->prepare($verify_sql);
        $verify_stmt->bind_param("i", $conversation_id);
        $verify_stmt->execute();
        
        if ($verify_stmt->get_result()->num_rows > 0) {
            // Send message as admin
            $msg_sql = "INSERT INTO chat_messages (conversation_id, sender_id, message) VALUES (?, ?, ?)";
            $msg_stmt = $mysqli->prepare($msg_sql);
            $msg_stmt->bind_param("iis", $conversation_id, $admin_id, $message);
            
            if ($msg_stmt->execute()) {
                // Update conversation status and timestamp
                $update_sql = "UPDATE chat_conversations SET status = 'open', updated_at = NOW() WHERE id = ?";
                $update_stmt = $mysqli->prepare($update_sql);
                $update_stmt->bind_param("i", $conversation_id);
                $update_stmt->execute();
                
                header("Location: admin_chat.php?conversation=" . $conversation_id . "&success=1");
                exit;
            } else {
                $error = "Failed to send message: " . $mysqli->error;
            }
        } else {
            $error = "Conversation not found.";
        }
    } else {
        $error = "Message cannot be empty.";
    }
}

// Handle status changes
if (isset($_GET['resolve'])) {
    $conversation_id = intval($_GET['resolve']);
    $resolve_sql = "UPDATE chat_conversations SET status = 'resolved' WHERE id = ?";
    $resolve_stmt = $mysqli->prepare($resolve_sql);
    $resolve_stmt->bind_param("i", $conversation_id);
    $resolve_stmt->execute();
    header("Location: admin_chat.php?conversation=" . $conversation_id);
    exit;
}

if (isset($_GET['close'])) {
    $conversation_id = intval($_GET['close']);
    $close_sql = "UPDATE chat_conversations SET status = 'closed' WHERE id = ?";
    $close_stmt = $mysqli->prepare($close_sql);
    $close_stmt->bind_param("i", $conversation_id);
    $close_stmt->execute();
    header("Location: admin_chat.php?conversation=" . $conversation_id);
    exit;
}

if (isset($_GET['reopen'])) {
    $conversation_id = intval($_GET['reopen']);
    $reopen_sql = "UPDATE chat_conversations SET status = 'open' WHERE id = ?";
    $reopen_stmt = $mysqli->prepare($reopen_sql);
    $reopen_stmt->bind_param("i", $conversation_id);
    $reopen_stmt->execute();
    header("Location: admin_chat.php?conversation=" . $conversation_id);
    exit;
}

// Get active conversation
$active_conversation_id = isset($_GET['conversation']) ? intval($_GET['conversation']) : 0;
$active_conversation = null;
$messages = [];

if ($active_conversation_id > 0) {
    // Get conversation details
    $conv_sql = "
        SELECT c.*, u.name as user_name, u.email as user_email 
        FROM chat_conversations c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ";
    $conv_stmt = $mysqli->prepare($conv_sql);
    $conv_stmt->bind_param("i", $active_conversation_id);
    $conv_stmt->execute();
    $conv_result = $conv_stmt->get_result();
    $active_conversation = $conv_result->fetch_assoc();
    
    if ($active_conversation) {
        // Get all messages for this conversation
        $msg_sql = "
            SELECT m.*, u.name as sender_name, u.role as sender_role 
            FROM chat_messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE m.conversation_id = ? 
            ORDER BY m.created_at ASC
        ";
        $msg_stmt = $mysqli->prepare($msg_sql);
        $msg_stmt->bind_param("i", $active_conversation_id);
        $msg_stmt->execute();
        $msg_result = $msg_stmt->get_result();
        
        while($message = $msg_result->fetch_assoc()) {
            $messages[] = $message;
        }
        
        // Mark messages as read
        $read_sql = "UPDATE chat_messages SET is_read = TRUE WHERE conversation_id = ? AND sender_id != ?";
        $read_stmt = $mysqli->prepare($read_sql);
        $read_stmt->bind_param("ii", $active_conversation_id, $admin_id);
        $read_stmt->execute();
    }
}

// Get all conversations with filters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_search = isset($_GET['search']) ? $_GET['search'] : '';

$conversations_sql = "
    SELECT c.*, 
           u.name as user_name,
           u.email as user_email,
           (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = c.id AND is_read = FALSE AND sender_id != ?) as unread_count,
           (SELECT message FROM chat_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT created_at FROM chat_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
    FROM chat_conversations c 
    JOIN users u ON c.user_id = u.id 
    WHERE 1=1
";

$params = [$admin_id];
$types = "i";

if ($filter_status != 'all') {
    $conversations_sql .= " AND c.status = ?";
    $types .= "s";
    $params[] = $filter_status;
}

if (!empty($filter_search)) {
    $conversations_sql .= " AND (c.subject LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $types .= "sss";
    $search_term = "%{$filter_search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$conversations_sql .= " ORDER BY 
    CASE WHEN c.status = 'open' THEN 1 
         WHEN c.status = 'resolved' THEN 2 
         ELSE 3 END,
    c.updated_at DESC";

$conversations_stmt = $mysqli->prepare($conversations_sql);

// Bind parameters dynamically
if (!empty($params)) {
    $conversations_stmt->bind_param($types, ...$params);
}

$conversations_stmt->execute();
$conversations_result = $conversations_stmt->get_result();
$conversations = [];

while($conv = $conversations_result->fetch_assoc()) {
    $conversations[] = $conv;
}

// Get stats
$stats_sql = "SELECT status, COUNT(*) as count FROM chat_conversations GROUP BY status";
$stats_result = $mysqli->query($stats_sql);
$stats = [];
while($stat = $stats_result->fetch_assoc()) {
    $stats[$stat['status']] = $stat['count'];
}

// Get total unread messages
$unread_sql = "
    SELECT COUNT(*) as total_unread 
    FROM chat_messages m 
    JOIN chat_conversations c ON m.conversation_id = c.id 
    WHERE m.is_read = FALSE 
    AND m.sender_id != ?
";
$unread_stmt = $mysqli->prepare($unread_sql);
$unread_stmt->bind_param("i", $admin_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$total_unread = $unread_result->fetch_assoc()['total_unread'];

// Get total users count for stats
$total_users = $mysqli->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Chat Support | LabEase</title>
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html, body {
  height: 100%;
  overflow: hidden;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: #f5f7fa;
  color: #333;
  display: flex;
  flex-direction: column;
}

/* Header */
.header { 
  background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
  color: white; 
  padding: 20px; 
  text-align: center; 
  position: relative; 
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  flex-shrink: 0;
}

.header h1 {
  font-size: 28px;
  font-weight: 600;
  margin-bottom: 5px;
}

.header .subtitle {
  font-size: 14px;
  opacity: 0.8;
}

.header .logout-btn {
  color: white;
  text-decoration: none;
  background: linear-gradient(135deg, #d32f2f 0%, #f44336 100%);
  padding: 10px 25px;
  border-radius: 8px;
  font-weight: 600;
  transition: all 0.3s ease;
  position: absolute;
  right: 20px;
  top: 50%;
  transform: translateY(-50%);
  box-shadow: 0 4px 8px rgba(244, 67, 54, 0.3);
}

.header .logout-btn:hover {
  transform: translateY(-50%) translateY(-2px);
  box-shadow: 0 6px 12px rgba(244, 67, 54, 0.4);
}

.back-btn {
  position: absolute;
  left: 20px;
  top: 50%;
  transform: translateY(-50%);
  background: linear-gradient(135deg, #1976d2 0%, #2196f3 100%);
  color: white;
  padding: 10px 25px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s ease;
  box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
}

.back-btn:hover {
  transform: translateY(-50%) translateY(-2px);
  box-shadow: 0 6px 12px rgba(33, 150, 243, 0.4);
}

/* Main content area */
.main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: 0 30px 30px;
}

/* Stats Cards */
.stats-container {
  display: flex;
  justify-content: center;
  margin: 30px 0;
  gap: 20px;
  flex-wrap: wrap;
  flex-shrink: 0;
}

.stat-card {
  background: white; 
  padding: 25px; 
  width: 22%; 
  min-width: 220px;
  text-align: center;
  border-radius: 12px; 
  box-shadow: 0 6px 15px rgba(0,0,0,0.08);
  display: flex;
  align-items: center;
  gap: 20px;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 20px rgba(0,0,0,0.12);
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
}

.stat-icon.open { background: #e3f2fd; color: #1565c0; }
.stat-icon.resolved { background: #e8f5e9; color: #2e7d32; }
.stat-icon.closed { background: #f5f5f5; color: #616161; }
.stat-icon.unread { background: #ffebee; color: #c62828; }

.stat-content h3 {
  font-size: 32px;
  font-weight: 700;
  margin-bottom: 5px;
  color: #1a237e;
}

.stat-content p {
  color: #666;
  font-size: 14px;
  font-weight: 500;
}

/* Alerts */
.alert {
  padding: 15px 20px;
  margin: 15px 0;
  border-radius: 8px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

.alert-success {
  background: #e8f5e9;
  color: #2e7d32;
  border-left: 4px solid #4caf50;
}

.alert-error {
  background: #ffebee;
  color: #c62828;
  border-left: 4px solid #f44336;
}

/* Filters Bar */
.filters-bar {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  margin-bottom: 25px;
  display: flex;
  gap: 20px;
  align-items: center;
  flex-wrap: wrap;
  flex-shrink: 0;
}

.filter-group {
  display: flex;
  gap: 12px;
  align-items: center;
}

.filter-label {
  font-weight: 600;
  color: #424242;
  font-size: 14px;
}

.status-filter {
  display: flex;
  gap: 8px;
}

.status-btn {
  padding: 8px 18px;
  border: 2px solid #e0e0e0;
  background: white;
  border-radius: 25px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  color: #424242;
}

.status-btn:hover {
  background: #f5f5f5;
  border-color: #bdbdbd;
}

.status-btn.active {
  background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
  border-color: #1a237e;
  color: white;
}

.search-box {
  flex: 1;
  max-width: 350px;
  position: relative;
}

.search-box input {
  width: 100%;
  padding: 12px 15px 12px 45px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.3s ease;
}

.search-box input:focus {
  outline: none;
  border-color: #2196f3;
  box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
}

.search-icon {
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: #9e9e9e;
  pointer-events: none;
}

/* Chat Container */
.chat-container {
  display: flex;
  background: white;
  border-radius: 12px;
  box-shadow: 0 8px 25px rgba(0,0,0,0.1);
  overflow: hidden;
  border: 1px solid #e0e0e0;
  flex: 1;
  min-height: 0;
}

/* Conversations Sidebar */
.conversations-sidebar {
  width: 400px;
  border-right: 1px solid #eee;
  display: flex;
  flex-direction: column;
  background: #fafafa;
  flex-shrink: 0;
}

.conversations-header {
  padding: 25px;
  border-bottom: 1px solid #eee;
  background: white;
  flex-shrink: 0;
}

.conversations-header h3 {
  font-size: 20px;
  font-weight: 700;
  color: #1a237e;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.conversations-list {
  flex: 1;
  overflow-y: auto;
  padding: 0;
  min-height: 0;
}

.conversation-item {
  padding: 20px;
  border-bottom: 1px solid #eee;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
  background: white;
}

.conversation-item:hover {
  background: #f8f9fa;
  padding-left: 25px;
}

.conversation-item.active {
  background: #e3f2fd;
  border-left: 5px solid #2196f3;
  padding-left: 20px;
}

.conversation-user {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.user-avatar {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #2196f3 0%, #21cbf3 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 16px;
  flex-shrink: 0;
  box-shadow: 0 4px 8px rgba(33, 150, 243, 0.2);
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-name {
  font-weight: 600;
  color: #212121;
  font-size: 15px;
  margin-bottom: 3px;
}

.user-email {
  color: #757575;
  font-size: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.conversation-subject {
  font-weight: 700;
  color: #1a237e;
  font-size: 15px;
  margin-bottom: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.conversation-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 10px;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 15px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-open {
  background: #e8f5e9;
  color: #2e7d32;
  border: 1px solid #c8e6c9;
}

.status-resolved {
  background: #fff3e0;
  color: #ef6c00;
  border: 1px solid #ffe0b2;
}

.status-closed {
  background: #f5f5f5;
  color: #757575;
  border: 1px solid #e0e0e0;
}

.unread-badge {
  background: linear-gradient(135deg, #d32f2f 0%, #f44336 100%);
  color: white;
  min-width: 22px;
  height: 22px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  padding: 0 6px;
  box-shadow: 0 2px 4px rgba(244, 67, 54, 0.3);
}

.conversation-preview {
  color: #616161;
  font-size: 13px;
  line-height: 1.5;
  margin: 8px 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.conversation-time {
  font-size: 11px;
  color: #9e9e9e;
  text-align: right;
}

/* Chat Area */
.chat-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
}

.chat-header {
  padding: 25px;
  border-bottom: 1px solid #eee;
  background: white;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-shrink: 0;
}

.chat-user-info {
  display: flex;
  align-items: center;
  gap: 18px;
}

.chat-user-avatar {
  width: 55px;
  height: 55px;
  background: linear-gradient(135deg, #2196f3 0%, #21cbf3 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 22px;
  flex-shrink: 0;
  box-shadow: 0 6px 12px rgba(33, 150, 243, 0.3);
}

.chat-user-details h3 {
  font-size: 20px;
  font-weight: 700;
  color: #1a237e;
  margin-bottom: 6px;
}

.chat-user-details p {
  color: #757575;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.chat-actions {
  display: flex;
  gap: 12px;
}

.chat-btn {
  padding: 10px 20px;
  border: 2px solid #e0e0e0;
  background: white;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  color: inherit;
}

.chat-btn:hover {
  background: #f5f5f5;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.chat-btn.resolve {
  border-color: #4caf50;
  color: #2e7d32;
}

.chat-btn.resolve:hover {
  background: #e8f5e9;
  border-color: #388e3c;
}

.chat-btn.reopen {
  border-color: #2196f3;
  color: #1565c0;
}

.chat-btn.reopen:hover {
  background: #e3f2fd;
  border-color: #1976d2;
}

.chat-btn.close {
  border-color: #f44336;
  color: #c62828;
}

.chat-btn.close:hover {
  background: #ffebee;
  border-color: #d32f2f;
}

/* Messages Container - FIXED FOR SCROLL */
.messages-container-wrapper {
  flex: 1;
  min-height: 0;
  position: relative;
}

.messages-container {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 25px;
  overflow-y: auto;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  display: flex;
  flex-direction: column;
  gap: 18px;
}

/* Scrollbar styling */
.messages-container::-webkit-scrollbar {
  width: 8px;
}

.messages-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.messages-container::-webkit-scrollbar-thumb {
  background: #bdbdbd;
  border-radius: 4px;
}

.messages-container::-webkit-scrollbar-thumb:hover {
  background: #9e9e9e;
}

.message {
  max-width: 75%;
  padding: 16px 20px;
  border-radius: 18px;
  position: relative;
  animation: fadeIn 0.3s ease;
  box-shadow: 0 4px 8px rgba(0,0,0,0.05);
  flex-shrink: 0;
}

.message.sent {
  align-self: flex-end;
  background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
  color: white;
  border-bottom-right-radius: 6px;
}

.message.received {
  align-self: flex-start;
  background: white;
  color: #212121;
  border: 1px solid #e0e0e0;
  border-bottom-left-radius: 6px;
}

.message-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.sender-name {
  font-weight: 600;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.sent .sender-name {
  color: rgba(255, 255, 255, 0.95);
}

.received .sender-name {
  color: #424242;
}

.admin-badge {
  background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 700;
  box-shadow: 0 2px 4px rgba(255, 152, 0, 0.3);
}

.message-time {
  font-size: 12px;
  opacity: 0.8;
}

.message-content {
  font-size: 15px;
  line-height: 1.6;
  word-wrap: break-word;
}

/* Scroll to bottom button */
.scroll-bottom-btn {
  position: absolute;
  bottom: 100px;
  right: 40px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #2196f3;
  color: white;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
  transition: all 0.3s ease;
  z-index: 100;
  opacity: 0;
  transform: translateY(10px);
}

.scroll-bottom-btn.show {
  opacity: 1;
  transform: translateY(0);
}

.scroll-bottom-btn:hover {
  background: #1976d2;
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(33, 150, 243, 0.4);
}

/* Message Input */
.message-input-container {
  padding: 25px;
  border-top: 1px solid #eee;
  background: white;
  flex-shrink: 0;
}

.message-form {
  display: flex;
  gap: 15px;
  align-items: flex-end;
}

.message-input {
  flex: 1;
  padding: 15px 20px;
  border: 2px solid #e0e0e0;
  border-radius: 12px;
  font-size: 15px;
  resize: none;
  min-height: 55px;
  max-height: 150px;
  line-height: 1.5;
  transition: all 0.3s ease;
  font-family: inherit;
}

.message-input:focus {
  outline: none;
  border-color: #2196f3;
  box-shadow: 0 0 0 4px rgba(33, 150, 243, 0.1);
}

.send-btn {
  padding: 15px 30px;
  background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
  color: white;
  border: none;
  border-radius: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 10px;
  height: 55px;
  box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
}

.send-btn:hover {
  background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(33, 150, 243, 0.4);
}

.send-btn:disabled {
  background: #bdbdbd;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
  text-align: center;
  padding: 50px;
  color: #757575;
}

.empty-icon {
  font-size: 72px;
  margin-bottom: 25px;
  opacity: 0.3;
}

.empty-state h3 {
  font-size: 22px;
  font-weight: 600;
  margin-bottom: 12px;
  color: #424242;
}

.empty-state p {
  margin-bottom: 25px;
  max-width: 450px;
  line-height: 1.6;
}

/* Responsive */
@media (max-width: 1200px) {
  .chat-container {
    flex-direction: column;
    margin: 0;
  }
  
  .conversations-sidebar {
    width: 100%;
    height: 300px;
    border-right: none;
    border-bottom: 1px solid #eee;
  }
  
  .stat-card {
    width: 45%;
  }
  
  .main-content {
    padding: 0 20px 20px;
  }
}

@media (max-width: 768px) {
  .stats-container {
    flex-direction: column;
    align-items: center;
    margin: 20px 0;
  }
  
  .stat-card {
    width: 100%;
    max-width: 300px;
  }
  
  .filters-bar {
    flex-direction: column;
    align-items: stretch;
    margin-bottom: 20px;
  }
  
  .search-box {
    max-width: 100%;
  }
  
  .chat-header {
    flex-direction: column;
    gap: 20px;
    align-items: flex-start;
  }
  
  .chat-actions {
    width: 100%;
    justify-content: space-between;
  }
  
  .message {
    max-width: 90%;
  }
  
  .header {
    padding: 15px;
  }
  
  .header h1 {
    font-size: 22px;
    padding: 0 100px;
  }
  
  .back-btn, .logout-btn {
    padding: 8px 15px;
    font-size: 14px;
  }
}

/* Animations */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>

<div class="header">
  <a href="dashboard.php" class="back-btn">← Dashboard</a>
  <div>
    <h1>💬 Admin Chat Support</h1>
    <p class="subtitle">Manage user conversations and provide support</p>
  </div>
  <a href="logout.php" class="logout-btn">Logout</a>
</div>

<div class="main-content">
  <!-- Success/Error Messages -->
  <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
      <div class="alert alert-success">
          <span>✅</span> Message sent successfully!
      </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
      <div class="alert alert-error">
          <span>❌</span> <?= htmlspecialchars($error) ?>
      </div>
  <?php endif; ?>

  <!-- Stats Cards -->
  <div class="stats-container">
    <div class="stat-card">
      <div class="stat-icon open">
        <span>💬</span>
      </div>
      <div class="stat-content">
        <h3><?= isset($stats['open']) ? $stats['open'] : 0 ?></h3>
        <p>Open Conversations</p>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon resolved">
        <span>✅</span>
      </div>
      <div class="stat-content">
        <h3><?= isset($stats['resolved']) ? $stats['resolved'] : 0 ?></h3>
        <p>Resolved</p>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon closed">
        <span>🔒</span>
      </div>
      <div class="stat-content">
        <h3><?= isset($stats['closed']) ? $stats['closed'] : 0 ?></h3>
        <p>Closed</p>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon unread">
        <span>📩</span>
      </div>
      <div class="stat-content">
        <h3><?= $total_unread ?></h3>
        <p>Unread Messages</p>
      </div>
    </div>
  </div>

  <!-- Filters Bar -->
  <div class="filters-bar">
    <div class="filter-group">
      <span class="filter-label">Filter by status:</span>
      <div class="status-filter">
        <a href="admin_chat.php?status=all<?= !empty($filter_search) ? '&search=' . urlencode($filter_search) : '' ?>" 
           class="status-btn <?= $filter_status == 'all' ? 'active' : '' ?>">All</a>
        <a href="admin_chat.php?status=open<?= !empty($filter_search) ? '&search=' . urlencode($filter_search) : '' ?>" 
           class="status-btn <?= $filter_status == 'open' ? 'active' : '' ?>">Open</a>
        <a href="admin_chat.php?status=resolved<?= !empty($filter_search) ? '&search=' . urlencode($filter_search) : '' ?>" 
           class="status-btn <?= $filter_status == 'resolved' ? 'active' : '' ?>">Resolved</a>
        <a href="admin_chat.php?status=closed<?= !empty($filter_search) ? '&search=' . urlencode($filter_search) : '' ?>" 
           class="status-btn <?= $filter_status == 'closed' ? 'active' : '' ?>">Closed</a>
      </div>
    </div>
    
    <div class="search-box">
      <form method="GET" action="">
        <input type="hidden" name="status" value="<?= $filter_status ?>">
        <div class="search-icon">🔍</div>
        <input type="text" name="search" value="<?= htmlspecialchars($filter_search) ?>" 
               placeholder="Search conversations..." oninput="this.form.submit()">
      </form>
    </div>
    
    <div style="font-size: 14px; color: #616161; font-weight: 500;">
      <span style="color: #1a237e; font-weight: 700;"><?= count($conversations) ?></span> conversation<?= count($conversations) != 1 ? 's' : '' ?> found
    </div>
  </div>

  <!-- Chat Container -->
  <div class="chat-container">
    
    <!-- Conversations Sidebar -->
    <div class="conversations-sidebar">
      <div class="conversations-header">
        <h3><span>📋</span> User Conversations</h3>
      </div>
      
      <div class="conversations-list">
        <?php if (!empty($conversations)): ?>
          <?php foreach($conversations as $conv): ?>
            <div class="conversation-item <?= $conv['id'] == $active_conversation_id ? 'active' : '' ?>" 
                 onclick="window.location.href='admin_chat.php?conversation=<?= $conv['id'] ?>&status=<?= $filter_status ?><?= !empty($filter_search) ? '&search=' . urlencode($filter_search) : '' ?>'">
              <div class="conversation-user">
                <div class="user-avatar">
                  <?= strtoupper(substr($conv['user_name'], 0, 1)) ?>
                </div>
                <div class="user-info">
                  <div class="user-name"><?= htmlspecialchars($conv['user_name']) ?></div>
                  <div class="user-email"><?= htmlspecialchars($conv['user_email']) ?></div>
                </div>
              </div>
              
              <div class="conversation-subject">
                <?= htmlspecialchars($conv['subject']) ?>
                <?php if ($conv['unread_count'] > 0): ?>
                  <span class="unread-badge"><?= $conv['unread_count'] ?> new</span>
                <?php endif; ?>
              </div>
              
              <div class="conversation-preview" title="<?= htmlspecialchars($conv['last_message']) ?>">
                <?= htmlspecialchars(substr($conv['last_message'], 0, 80)) ?>
                <?= strlen($conv['last_message']) > 80 ? '...' : '' ?>
              </div>
              
              <div class="conversation-meta">
                <span class="status-badge status-<?= $conv['status'] ?>">
                  <?= ucfirst($conv['status']) ?>
                </span>
                <div class="conversation-time">
                  <?= date('M d, h:i A', strtotime($conv['last_message_time'])) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state" style="padding: 40px 20px;">
            <div class="empty-icon">💬</div>
            <h3>No conversations found</h3>
            <p><?= !empty($filter_search) ? 'Try a different search term' : 'All conversations are handled' ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <!-- Chat Area -->
    <div class="chat-area">
      <?php if ($active_conversation): ?>
        <!-- Chat Header -->
        <div class="chat-header">
          <div class="chat-user-info">
            <div class="chat-user-avatar">
              <?= strtoupper(substr($active_conversation['user_name'], 0, 1)) ?>
            </div>
            <div class="chat-user-details">
              <h3><?= htmlspecialchars($active_conversation['user_name']) ?></h3>
              <p>
                <span>📧 <?= htmlspecialchars($active_conversation['user_email']) ?></span>
                <span>•</span>
                <span>📅 Started <?= date('M d, Y', strtotime($active_conversation['created_at'])) ?></span>
                <span>•</span>
                <span class="status-badge status-<?= $active_conversation['status'] ?>" style="display: inline-block;">
                  <?= ucfirst($active_conversation['status']) ?>
                </span>
              </p>
            </div>
          </div>
          
          <div class="chat-actions">
            <?php if ($active_conversation['status'] == 'open'): ?>
              <a href="admin_chat.php?resolve=<?= $active_conversation['id'] ?>&status=<?= $filter_status ?><?= !empty($filter_search) ? '&search=' . urlencode($filter_search) : '' ?>" 
                 class="chat-btn resolve" onclick="return confirm('Mark this conversation as resolved?')">
                <span>✅</span> Mark Resolved
              </a>
            <?php elseif ($active_conversation['status'] == 'resolved'): ?>
              <a href="admin_chat.php?reopen=<?= $active_conversation['id'] ?>&status=<?= $filter_status ?><?= !empty($filter_search) ? '&search=' . urlencode($filter_search) : '' ?>" 
                 class="chat-btn reopen">
                <span>↩️</span> Re-open
              </a>
            <?php elseif ($active_conversation['status'] == 'closed'): ?>
              <a href="admin_chat.php?reopen=<?= $active_conversation['id'] ?>&status=<?= $filter_status ?><?= !empty($filter_search) ? '&search=' . urlencode($filter_search) : '' ?>" 
                 class="chat-btn reopen">
                <span>🔄</span> Re-open
              </a>
            <?php endif; ?>
            
            <?php if ($active_conversation['status'] != 'closed'): ?>
              <a href="admin_chat.php?close=<?= $active_conversation['id'] ?>&status=<?= $filter_status ?><?= !empty($filter_search) ? '&search=' . urlencode($filter_search) : '' ?>" 
                 class="chat-btn close" onclick="return confirm('Close this conversation? You can re-open it later.')">
                <span>❌</span> Close Chat
              </a>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Messages Container Wrapper -->
        <div class="messages-container-wrapper">
          <div class="messages-container" id="messagesContainer">
            <?php if (!empty($messages)): ?>
              <?php foreach($messages as $message): ?>
                <div class="message <?= $message['sender_id'] == $admin_id ? 'sent' : 'received' ?>">
                  <div class="message-header">
                    <span class="sender-name">
                      <?= htmlspecialchars($message['sender_name']) ?>
                      <?php if ($message['sender_role'] == 'admin'): ?>
                        <span class="admin-badge">ADMIN</span>
                      <?php endif; ?>
                    </span>
                    <span class="message-time">
                      <?= date('h:i A', strtotime($message['created_at'])) ?>
                      <?php if ($message['sender_id'] == $admin_id): ?>
                        <span style="margin-left: 5px; opacity: 0.7;">• You</span>
                      <?php endif; ?>
                    </span>
                  </div>
                  <div class="message-content">
                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-state" style="padding: 40px;">
                <div class="empty-icon">💬</div>
                <h3>No messages yet</h3>
                <p>Start the conversation by sending a message</p>
              </div>
            <?php endif; ?>
          </div>
          
          <!-- Scroll to bottom button -->
          <button class="scroll-bottom-btn" id="scrollBottomBtn" title="Scroll to bottom">
            👇
          </button>
        </div>
        
        <!-- Message Input -->
        <div class="message-input-container">
          <form method="POST" action="" class="message-form" id="messageForm">
            <input type="hidden" name="conversation_id" value="<?= $active_conversation['id'] ?>">
            <textarea 
              name="message" 
              class="message-input" 
              placeholder="Type your reply here..." 
              rows="1"
              oninput="autoResize(this)"
              <?= $active_conversation['status'] == 'closed' ? 'disabled' : '' ?>
              required></textarea>
            <button type="submit" 
                    name="send_message" 
                    class="send-btn"
                    <?= $active_conversation['status'] == 'closed' ? 'disabled' : '' ?>>
              <span>📤</span> Send
            </button>
          </form>
          <?php if ($active_conversation['status'] == 'closed'): ?>
            <p style="color: #f44336; font-size: 13px; margin-top: 12px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
              <span>⚠️</span> This conversation is closed. <a href="admin_chat.php?reopen=<?= $active_conversation['id'] ?>" style="color: #2196f3; text-decoration: none;">Click here to re-open it</a> and send messages.
            </p>
          <?php endif; ?>
        </div>
        
      <?php else: ?>
        <!-- Empty Chat State -->
        <div class="empty-state">
          <div class="empty-icon">💬</div>
          <h3>Select a conversation</h3>
          <p>Choose a conversation from the sidebar to view and respond to messages</p>
          <div style="margin-top: 30px; font-size: 14px; color: #616161; background: #f5f5f5; padding: 20px; border-radius: 10px; max-width: 500px;">
            <p style="font-weight: 600; color: #1a237e; margin-bottom: 15px;">💡 Tips for effective support:</p>
            <ul style="text-align: left; margin-top: 10px; list-style: none; padding: 0;">
              <li style="margin-bottom: 10px; padding-left: 20px; position: relative;">• <strong>Respond promptly</strong> to open conversations</li>
              <li style="margin-bottom: 10px; padding-left: 20px; position: relative;">• <strong>Mark resolved</strong> when issues are solved</li>
              <li style="margin-bottom: 10px; padding-left: 20px; position: relative;">• Use <strong>clear and professional</strong> language</li>
              <li style="margin-bottom: 10px; padding-left: 20px; position: relative;">• <strong>Close conversations</strong> when fully resolved</li>
            </ul>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Auto-resize textarea
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px';
}

// Scroll to bottom of messages
function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    if (container) {
        container.scrollTop = container.scrollHeight;
        hideScrollButton();
    }
}

// Check if user is at bottom of chat
function isAtBottom() {
    const container = document.getElementById('messagesContainer');
    if (!container) return true;
    
    const threshold = 100; // pixels from bottom
    return container.scrollHeight - container.scrollTop - container.clientHeight <= threshold;
}

// Show/hide scroll to bottom button
function toggleScrollButton() {
    const scrollBtn = document.getElementById('scrollBottomBtn');
    if (scrollBtn) {
        if (isAtBottom()) {
            scrollBtn.classList.remove('show');
        } else {
            scrollBtn.classList.add('show');
        }
    }
}

function showScrollButton() {
    const scrollBtn = document.getElementById('scrollBottomBtn');
    if (scrollBtn) {
        scrollBtn.classList.add('show');
    }
}

function hideScrollButton() {
    const scrollBtn = document.getElementById('scrollBottomBtn');
    if (scrollBtn) {
        scrollBtn.classList.remove('show');
    }
}

// Setup scroll event listener
function setupScrollListener() {
    const container = document.getElementById('messagesContainer');
    if (container) {
        container.addEventListener('scroll', toggleScrollButton);
        
        // Initial check
        setTimeout(toggleScrollButton, 100);
    }
}

// Auto-scroll when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Setup scroll listener
    setupScrollListener();
    
    // Initial scroll to bottom with delay
    setTimeout(scrollToBottom, 300);
    
    // Focus message input if active conversation
    const messageInput = document.querySelector('.message-input');
    if (messageInput && !messageInput.disabled) {
        messageInput.focus();
    }
    
    // Add click handler to scroll button
    const scrollBtn = document.getElementById('scrollBottomBtn');
    if (scrollBtn) {
        scrollBtn.addEventListener('click', scrollToBottom);
    }
    
    // Auto-scroll when new messages might be added (form submission)
    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        messageForm.addEventListener('submit', function() {
            setTimeout(scrollToBottom, 500);
        });
    }
});

// Form submission handling
document.getElementById('messageForm')?.addEventListener('submit', function(e) {
    const messageInput = this.querySelector('textarea[name="message"]');
    if (!messageInput.value.trim()) {
        e.preventDefault();
        alert('Please enter a message before sending.');
        messageInput.focus();
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+Enter or Cmd+Enter to send message
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        const sendBtn = document.querySelector('.send-btn:not(:disabled)');
        if (sendBtn) {
            sendBtn.click();
        }
    }
    
    // Escape to clear focus
    if (e.key === 'Escape') {
        document.activeElement.blur();
    }
    
    // Page Down/Up for scrolling
    const container = document.getElementById('messagesContainer');
    if (container) {
        if (e.key === 'PageDown') {
            container.scrollTop += container.clientHeight - 50;
            e.preventDefault();
        } else if (e.key === 'PageUp') {
            container.scrollTop -= container.clientHeight - 50;
            e.preventDefault();
        } else if (e.key === 'Home') {
            container.scrollTop = 0;
            e.preventDefault();
        } else if (e.key === 'End') {
            scrollToBottom();
            e.preventDefault();
        }
    }
});

// Auto-focus search on Ctrl+F
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-box input');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
});

// Save message draft to prevent loss
const messageTextarea = document.querySelector('.message-input');
if (messageTextarea) {
    const conversationId = <?= $active_conversation_id ?>;
    
    // Load saved draft
    const savedDraft = localStorage.getItem(`chat_draft_${conversationId}`);
    if (savedDraft) {
        messageTextarea.value = savedDraft;
        autoResize(messageTextarea);
    }
    
    // Save draft on input
    messageTextarea.addEventListener('input', function() {
        localStorage.setItem(`chat_draft_${conversationId}`, this.value);
        autoResize(this);
    });
    
    // Clear draft after successful send
    messageTextarea.form.addEventListener('submit', function() {
        setTimeout(() => {
            localStorage.removeItem(`chat_draft_${conversationId}`);
        }, 100);
    });
}

// Handle window resize
window.addEventListener('resize', function() {
    setTimeout(toggleScrollButton, 100);
});
</script>

</body>
</html>