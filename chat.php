<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Handle new conversation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_conversation'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if (!empty($subject) && !empty($message)) {
        // Start transaction
        $mysqli->begin_transaction();
        
        try {
            // Create conversation
            $conv_sql = "INSERT INTO chat_conversations (user_id, subject) VALUES (?, ?)";
            $conv_stmt = $mysqli->prepare($conv_sql);
            $conv_stmt->bind_param("is", $user_id, $subject);
            $conv_stmt->execute();
            $conversation_id = $mysqli->insert_id;
            
            // Add first message
            $msg_sql = "INSERT INTO chat_messages (conversation_id, sender_id, message) VALUES (?, ?, ?)";
            $msg_stmt = $mysqli->prepare($msg_sql);
            $msg_stmt->bind_param("iis", $conversation_id, $user_id, $message);
            $msg_stmt->execute();
            
            $mysqli->commit();
            header("Location: chat.php?conversation=" . $conversation_id);
            exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            $error = "Failed to start conversation. Please try again.";
        }
    } else {
        $error = "Please fill in both subject and message.";
    }
}

// Handle sending new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $conversation_id = intval($_POST['conversation_id']);
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        // Verify conversation belongs to user
        $verify_sql = "SELECT id FROM chat_conversations WHERE id = ? AND user_id = ?";
        $verify_stmt = $mysqli->prepare($verify_sql);
        $verify_stmt->bind_param("ii", $conversation_id, $user_id);
        $verify_stmt->execute();
        
        if ($verify_stmt->get_result()->num_rows > 0) {
            // Send message
            $msg_sql = "INSERT INTO chat_messages (conversation_id, sender_id, message) VALUES (?, ?, ?)";
            $msg_stmt = $mysqli->prepare($msg_sql);
            $msg_stmt->bind_param("iis", $conversation_id, $user_id, $message);
            $msg_stmt->execute();
            
            // Update conversation timestamp
            $update_sql = "UPDATE chat_conversations SET updated_at = NOW() WHERE id = ?";
            $update_stmt = $mysqli->prepare($update_sql);
            $update_stmt->bind_param("i", $conversation_id);
            $update_stmt->execute();
            
            // Mark all previous messages as read
            $read_sql = "UPDATE chat_messages SET is_read = TRUE WHERE conversation_id = ?";
            $read_stmt = $mysqli->prepare($read_sql);
            $read_stmt->bind_param("i", $conversation_id);
            $read_stmt->execute();
        }
    }
}

// Handle close conversation
if (isset($_GET['close'])) {
    $conversation_id = intval($_GET['close']);
    
    $close_sql = "UPDATE chat_conversations SET status = 'closed' WHERE id = ? AND user_id = ?";
    $close_stmt = $mysqli->prepare($close_sql);
    $close_stmt->bind_param("ii", $conversation_id, $user_id);
    $close_stmt->execute();
}

// Get active conversation
$active_conversation_id = isset($_GET['conversation']) ? intval($_GET['conversation']) : 0;
$active_conversation = null;
$messages = [];

if ($active_conversation_id > 0) {
    // Get conversation details
    $conv_sql = "
        SELECT c.*, u.name as user_name 
        FROM chat_conversations c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ? AND c.user_id = ?
    ";
    $conv_stmt = $mysqli->prepare($conv_sql);
    $conv_stmt->bind_param("ii", $active_conversation_id, $user_id);
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
        $read_stmt->bind_param("ii", $active_conversation_id, $user_id);
        $read_stmt->execute();
    }
}

// Get user's conversations
$conversations_sql = "
    SELECT c.*, 
           (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = c.id AND is_read = FALSE AND sender_id != ?) as unread_count,
           (SELECT message FROM chat_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
    FROM chat_conversations c 
    WHERE c.user_id = ? 
    ORDER BY c.updated_at DESC
";
$conversations_stmt = $mysqli->prepare($conversations_sql);
$conversations_stmt->bind_param("ii", $user_id, $user_id);
$conversations_stmt->execute();
$conversations_result = $conversations_stmt->get_result();
$conversations = [];

while($conv = $conversations_result->fetch_assoc()) {
    $conversations[] = $conv;
}

// Get unread conversations count
$unread_conv_count = 0;
foreach($conversations as $conv) {
    if ($conv['unread_count'] > 0) {
        $unread_conv_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat with Admin | LabEase</title>
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

/* Sidebar */
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
  height: calc(100vh - 60px);
  display: flex;
  flex-direction: column;
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

.chat-status {
  display: flex;
  align-items: center;
  gap: 15px;
}

.status-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 500;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.status-dot.online {
  background: #10b981;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
}

.status-dot.offline {
  background: #6b7280;
}

/* Chat Container */
.chat-container {
  display: flex;
  flex: 1;
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
  overflow: hidden;
  min-height: 500px;
}

/* Conversations Sidebar */
.conversations-sidebar {
  width: 350px;
  border-right: 1px solid #f3f4f6;
  display: flex;
  flex-direction: column;
  background: #f9fafb;
}

.conversations-header {
  padding: 20px;
  border-bottom: 1px solid #f3f4f6;
  background: white;
}

.conversations-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 15px;
}

.new-conversation-btn {
  width: 100%;
  padding: 12px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 14px;
}

.new-conversation-btn:hover {
  background: #2563eb;
}

.conversations-list {
  flex: 1;
  overflow-y: auto;
  padding: 0;
}

.conversation-item {
  padding: 18px 20px;
  border-bottom: 1px solid #f3f4f6;
  cursor: pointer;
  transition: background 0.2s ease;
  position: relative;
  background: white;
}

.conversation-item:hover {
  background: #f9fafb;
}

.conversation-item.active {
  background: #dbeafe;
  border-left: 4px solid #3b82f6;
}

.conversation-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 8px;
}

.conversation-subject {
  font-weight: 600;
  color: #1f2937;
  font-size: 14px;
  flex: 1;
}

.conversation-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  color: #6b7280;
}

.status-badge {
  padding: 2px 6px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-open {
  background: #d1fae5;
  color: #065f46;
}

.status-resolved {
  background: #fef3c7;
  color: #92400e;
}

.status-closed {
  background: #f3f4f6;
  color: #6b7280;
}

.unread-badge {
  background: #ef4444;
  color: white;
  min-width: 18px;
  height: 18px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
  padding: 0 4px;
}

.conversation-preview {
  color: #6b7280;
  font-size: 13px;
  line-height: 1.4;
  margin-bottom: 5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.conversation-time {
  font-size: 11px;
  color: #9ca3af;
}

/* Chat Area */
.chat-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.chat-header {
  padding: 20px;
  border-bottom: 1px solid #f3f4f6;
  background: white;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.chat-info h3 {
  font-size: 18px;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 5px;
}

.chat-info p {
  color: #6b7280;
  font-size: 13px;
}

.chat-actions {
  display: flex;
  gap: 10px;
}

.chat-btn {
  padding: 8px 16px;
  border: 1px solid #e5e7eb;
  background: white;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 6px;
}

.chat-btn:hover {
  background: #f9fafb;
}

.chat-btn.resolve {
  border-color: #10b981;
  color: #10b981;
}

.chat-btn.resolve:hover {
  background: #d1fae5;
}

.chat-btn.close {
  border-color: #ef4444;
  color: #ef4444;
}

.chat-btn.close:hover {
  background: #fee2e2;
}

/* Messages Container */
.messages-container {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
  background: #f9fafb;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.message {
  max-width: 70%;
  padding: 14px 18px;
  border-radius: 16px;
  position: relative;
  animation: fadeIn 0.3s ease;
}

.message.sent {
  align-self: flex-end;
  background: #3b82f6;
  color: white;
  border-bottom-right-radius: 4px;
}

.message.received {
  align-self: flex-start;
  background: white;
  color: #1f2937;
  border: 1px solid #e5e7eb;
  border-bottom-left-radius: 4px;
}

.message-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.sender-name {
  font-weight: 600;
  font-size: 13px;
}

.sent .sender-name {
  color: rgba(255, 255, 255, 0.9);
}

.received .sender-name {
  color: #4b5563;
}

.message-time {
  font-size: 11px;
  opacity: 0.8;
}

.message-content {
  font-size: 14px;
  line-height: 1.5;
  word-wrap: break-word;
}

/* Message Input */
.message-input-container {
  padding: 20px;
  border-top: 1px solid #f3f4f6;
  background: white;
}

.message-form {
  display: flex;
  gap: 12px;
  align-items: flex-end;
}

.message-input {
  flex: 1;
  padding: 12px 16px;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  resize: none;
  min-height: 48px;
  max-height: 120px;
  line-height: 1.5;
  transition: border-color 0.2s ease;
}

.message-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.send-btn {
  padding: 12px 24px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  height: 48px;
}

.send-btn:hover {
  background: #2563eb;
}

.send-btn:disabled {
  background: #9ca3af;
  cursor: not-allowed;
}

/* New Conversation Modal */
.modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 1000;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(2px);
}

.modal-overlay.active {
  display: flex;
  animation: fadeIn 0.2s ease;
}

.modal-container {
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 500px;
  box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
  overflow: hidden;
}

.modal-header {
  padding: 20px 30px;
  background: #3b82f6;
  color: white;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  font-size: 20px;
  font-weight: 700;
}

.close-modal {
  background: none;
  border: none;
  color: white;
  font-size: 24px;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: background 0.2s ease;
}

.close-modal:hover {
  background: rgba(255,255,255,0.1);
}

.modal-body {
  padding: 30px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #374151;
  font-size: 14px;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  transition: border-color 0.2s ease;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #3b82f6;
}

.form-group textarea {
  min-height: 120px;
  resize: vertical;
}

.modal-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 30px;
}

.modal-btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s ease;
}

.modal-btn.primary {
  background: #3b82f6;
  color: white;
}

.modal-btn.primary:hover {
  background: #2563eb;
}

.modal-btn.secondary {
  background: #6b7280;
  color: white;
}

.modal-btn.secondary:hover {
  background: #4b5563;
}

/* Typing indicator */
.typing-indicator {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 10px 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  align-self: flex-start;
  margin-top: 8px;
}

.typing-dot {
  width: 6px;
  height: 6px;
  background: #9ca3af;
  border-radius: 50%;
  animation: typing 1.4s infinite ease-in-out;
}

.typing-dot:nth-child(1) { animation-delay: -0.32s; }
.typing-dot:nth-child(2) { animation-delay: -0.16s; }

@keyframes typing {
  0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
  40% { transform: scale(1); opacity: 1; }
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
  text-align: center;
  padding: 40px;
  color: #6b7280;
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 20px;
  opacity: 0.3;
}

.empty-state h3 {
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 10px;
  color: #4b5563;
}

.empty-state p {
  margin-bottom: 20px;
  max-width: 400px;
}

/* Responsive */
@media (max-width: 1024px) {
  .sidebar {
    transform: translateX(-100%);
  }
  
  .main-content {
    margin-left: 0;
    padding: 20px;
    height: calc(100vh - 40px);
  }
  
  .chat-container {
    flex-direction: column;
  }
  
  .conversations-sidebar {
    width: 100%;
    height: 300px;
    border-right: none;
    border-bottom: 1px solid #f3f4f6;
  }
  
  .chat-area {
    flex: 1;
    min-height: 400px;
  }
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    gap: 15px;
    text-align: center;
  }
  
  .chat-header {
    flex-direction: column;
    gap: 15px;
    align-items: flex-start;
  }
  
  .chat-actions {
    width: 100%;
    justify-content: space-between;
  }
  
  .message {
    max-width: 85%;
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
    <li><a href="dashboard.php" ><span>📊</span> Dashboard</a></li>
    <li><a href="calendar.php"><span>📅</span> Calendar View</a></li>
    <li><a href="create.php"><span>➕</span> Book a Lab</a></li>
    <li><a href="my_bookings.php"><span>📋</span> My Bookings</a></li>
    <li><a href="analytics.php"><span>📈</span> Analytics</a></li>
    <li><a href="chat.php" class="active"><span>💬</span> Chat with Admin</a></li>
    <li><a href="notifications.php"><span>🔔</span> Notifications</a></li>
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
      <h1>💬 Chat with Admin</h1>
      <p>Get help with booking issues, lab questions, or technical problems</p>
    </div>
    
    <div class="chat-status">
      <div class="status-indicator">
        <div class="status-dot online"></div>
        <span>Admin Support: Online</span>
      </div>
      <?php if ($unread_conv_count > 0): ?>
        <span style="background: #ef4444; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
          <?= $unread_conv_count ?> unread conversation<?= $unread_conv_count > 1 ? 's' : '' ?>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Chat Container -->
  <div class="chat-container">
    
    <!-- Conversations Sidebar -->
    <div class="conversations-sidebar">
      <div class="conversations-header">
        <h3>Your Conversations</h3>
        <button class="new-conversation-btn" onclick="openNewConversationModal()">
          <span>+</span> Start New Chat
        </button>
      </div>
      
      <div class="conversations-list">
        <?php if (!empty($conversations)): ?>
          <?php foreach($conversations as $conv): ?>
            <div class="conversation-item <?= $conv['id'] == $active_conversation_id ? 'active' : '' ?>" 
                 onclick="window.location.href='chat.php?conversation=<?= $conv['id'] ?>'">
              <div class="conversation-header">
                <div class="conversation-subject">
                  <?= htmlspecialchars($conv['subject']) ?>
                </div>
                <div class="conversation-meta">
                  <span class="status-badge status-<?= $conv['status'] ?>">
                    <?= ucfirst($conv['status']) ?>
                  </span>
                  <?php if ($conv['unread_count'] > 0): ?>
                    <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                  <?php endif; ?>
                </div>
              </div>
              
              <div class="conversation-preview" title="<?= htmlspecialchars($conv['last_message']) ?>">
                <?= htmlspecialchars(substr($conv['last_message'], 0, 60)) ?>
                <?= strlen($conv['last_message']) > 60 ? '...' : '' ?>
              </div>
              
              <div class="conversation-time">
                <?= date('M d, h:i A', strtotime($conv['updated_at'])) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state" style="padding: 40px 20px;">
            <div class="empty-icon">💬</div>
            <h3>No conversations yet</h3>
            <p>Start a new chat to get help from admin</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <!-- Chat Area -->
    <div class="chat-area">
      <?php if ($active_conversation): ?>
        <!-- Chat Header -->
        <div class="chat-header">
          <div class="chat-info">
            <h3><?= htmlspecialchars($active_conversation['subject']) ?></h3>
            <p>
              Started <?= date('M d, Y', strtotime($active_conversation['created_at'])) ?> • 
              Status: <span class="status-badge status-<?= $active_conversation['status'] ?>" style="display: inline-block;">
                <?= ucfirst($active_conversation['status']) ?>
              </span>
            </p>
          </div>
          
          <div class="chat-actions">
            <?php if ($active_conversation['status'] == 'open'): ?>
              <button class="chat-btn resolve" onclick="window.location.href='chat.php?resolve=<?= $active_conversation['id'] ?>'">
                <span>✅</span> Mark as Resolved
              </button>
            <?php endif; ?>
            <button class="chat-btn close" onclick="window.location.href='chat.php?close=<?= $active_conversation['id'] ?>'">
              <span>❌</span> Close Chat
            </button>
          </div>
        </div>
        
        <!-- Messages Container -->
        <div class="messages-container" id="messagesContainer">
          <?php if (!empty($messages)): ?>
            <?php foreach($messages as $message): ?>
              <div class="message <?= $message['sender_id'] == $user_id ? 'sent' : 'received' ?>">
                <div class="message-header">
                  <span class="sender-name">
                    <?= htmlspecialchars($message['sender_name']) ?>
                    <?php if ($message['sender_role'] == 'admin'): ?>
                      <span style="color: #f59e0b; margin-left: 4px;">👑</span>
                    <?php endif; ?>
                  </span>
                  <span class="message-time">
                    <?= date('h:i A', strtotime($message['created_at'])) ?>
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
              <p>Send a message to start the conversation</p>
            </div>
          <?php endif; ?>
        </div>
        
        <!-- Message Input -->
        <div class="message-input-container">
          <form method="POST" action="" class="message-form" id="messageForm">
            <input type="hidden" name="conversation_id" value="<?= $active_conversation['id'] ?>">
            <textarea 
              name="message" 
              class="message-input" 
              placeholder="Type your message here..." 
              rows="1"
              oninput="autoResize(this)"
              required></textarea>
            <button type="submit" name="send_message" class="send-btn">
              <span>📤</span> Send
            </button>
          </form>
        </div>
        
      <?php else: ?>
        <!-- Empty Chat State -->
        <div class="empty-state">
          <div class="empty-icon">💬</div>
          <h3>Select a conversation</h3>
          <p>Choose a conversation from the sidebar or start a new chat</p>
          <button class="new-conversation-btn" onclick="openNewConversationModal()" style="width: auto; margin-top: 20px;">
            <span>+</span> Start New Chat
          </button>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- New Conversation Modal -->
<div class="modal-overlay" id="newConversationModal">
  <div class="modal-container">
    <div class="modal-header">
      <h2>💬 Start New Conversation</h2>
      <button class="close-modal" onclick="closeNewConversationModal()">&times;</button>
    </div>
    
    <div class="modal-body">
      <form method="POST" action="" id="newConversationForm">
        <div class="form-group">
          <label for="subject">Subject *</label>
          <input type="text" id="subject" name="subject" placeholder="e.g., Booking issue, Lab question, Technical problem..." required>
        </div>
        
        <div class="form-group">
          <label for="message">Your Message *</label>
          <textarea id="message" name="message" placeholder="Describe your issue or question in detail..." required></textarea>
        </div>
        
        <div class="form-group">
          <label>Common Topics:</label>
          <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">
            <button type="button" class="topic-btn" onclick="setTopic('Booking Issues')">Booking Issues</button>
            <button type="button" class="topic-btn" onclick="setTopic('Lab Equipment')">Lab Equipment</button>
            <button type="button" class="topic-btn" onclick="setTopic('Technical Problems')">Technical Problems</button>
            <button type="button" class="topic-btn" onclick="setTopic('Account Help')">Account Help</button>
          </div>
        </div>
        
        <div class="modal-actions">
          <button type="button" class="modal-btn secondary" onclick="closeNewConversationModal()">Cancel</button>
          <button type="submit" name="new_conversation" class="modal-btn primary">Start Conversation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Auto-resize textarea
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
}

// Scroll to bottom of messages
function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// New conversation modal
function openNewConversationModal() {
    document.getElementById('newConversationModal').classList.add('active');
}

function closeNewConversationModal() {
    document.getElementById('newConversationModal').classList.remove('active');
}

// Set common topic
function setTopic(topic) {
    document.getElementById('subject').value = topic;
}

// Auto-scroll when page loads
document.addEventListener('DOMContentLoaded', function() {
    scrollToBottom();
    
    // Auto-refresh messages every 5 seconds if in active conversation
    const activeConv = <?= $active_conversation_id ?>;
    if (activeConv) {
        setInterval(() => {
            fetch(`chat_refresh.php?conversation=${activeConv}`)
                .then(response => response.json())
                .then(data => {
                    if (data.newMessages) {
                        location.reload();
                    }
                })
                .catch(error => console.error('Refresh error:', error));
        }, 5000);
    }
    
    // Close modal when clicking outside
    document.getElementById('newConversationModal').addEventListener('click', function(e) {
        if (e.target.id === 'newConversationModal') {
            closeNewConversationModal();
        }
    });
    
    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeNewConversationModal();
        }
    });
});

// Form submission handling
document.getElementById('newConversationForm')?.addEventListener('submit', function(e) {
    const subject = document.getElementById('subject').value.trim();
    const message = document.getElementById('message').value.trim();
    
    if (!subject || !message) {
        e.preventDefault();
        alert('Please fill in both subject and message fields.');
    }
});

document.getElementById('messageForm')?.addEventListener('submit', function(e) {
    const messageInput = this.querySelector('textarea[name="message"]');
    if (!messageInput.value.trim()) {
        e.preventDefault();
        alert('Please enter a message.');
    }
});
</script>

<style>
.topic-btn {
    padding: 6px 12px;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 12px;
    color: #4b5563;
    cursor: pointer;
    transition: all 0.2s ease;
}

.topic-btn:hover {
    background: #e5e7eb;
    border-color: #d1d5db;
}
</style>

</body>
</html>