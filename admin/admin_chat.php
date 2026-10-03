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

// Check if admin exists in users table
$admin_check_sql = "SELECT id, name FROM users WHERE role = 'admin' LIMIT 1";
$admin_check_result = $mysqli->query($admin_check_sql);

if ($admin_check_result->num_rows > 0) {
    $admin_data = $admin_check_result->fetch_assoc();
    $admin_id = $admin_data['id'];
    $admin_name = $admin_data['name'];
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
                
                $_SESSION['success'] = "Message sent successfully!";
                header("Location: admin_chat.php?conversation=" . $conversation_id);
                exit;
            } else {
                $_SESSION['error'] = "Failed to send message: " . $mysqli->error;
            }
        } else {
            $_SESSION['error'] = "Conversation not found.";
        }
    } else {
        $_SESSION['error'] = "Message cannot be empty.";
    }
}

// Handle status changes
if (isset($_GET['resolve'])) {
    $conversation_id = intval($_GET['resolve']);
    $resolve_sql = "UPDATE chat_conversations SET status = 'resolved' WHERE id = ?";
    $resolve_stmt = $mysqli->prepare($resolve_sql);
    $resolve_stmt->bind_param("i", $conversation_id);
    $resolve_stmt->execute();
    $_SESSION['success'] = "Conversation marked as resolved!";
    header("Location: admin_chat.php?conversation=" . $conversation_id);
    exit;
}

if (isset($_GET['close'])) {
    $conversation_id = intval($_GET['close']);
    $close_sql = "UPDATE chat_conversations SET status = 'closed' WHERE id = ?";
    $close_stmt = $mysqli->prepare($close_sql);
    $close_stmt->bind_param("i", $conversation_id);
    $close_stmt->execute();
    $_SESSION['success'] = "Conversation closed!";
    header("Location: admin_chat.php?conversation=" . $conversation_id);
    exit;
}

if (isset($_GET['reopen'])) {
    $conversation_id = intval($_GET['reopen']);
    $reopen_sql = "UPDATE chat_conversations SET status = 'open' WHERE id = ?";
    $reopen_stmt = $mysqli->prepare($reopen_sql);
    $reopen_stmt->bind_param("i", $conversation_id);
    $reopen_stmt->execute();
    $_SESSION['success'] = "Conversation re-opened!";
    header("Location: admin_chat.php?conversation=" . $conversation_id);
    exit;
}

// Handle status messages
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
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
        if ($admin_id) {
            $read_sql = "UPDATE chat_messages SET is_read = TRUE WHERE conversation_id = ? AND sender_id != ?";
            $read_stmt = $mysqli->prepare($read_sql);
            $read_stmt->bind_param("ii", $active_conversation_id, $admin_id);
            $read_stmt->execute();
        }
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
$total_unread = 0;
if ($admin_id) {
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
    $unread_data = $unread_result->fetch_assoc();
    $total_unread = $unread_data ? $unread_data['total_unread'] : 0;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Chat Support</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background:#b8eaf8ff;
    padding: 20px;
}

.header {
    background: #2c3e50;
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    text-align: center;
    position: relative;
}

.header h1 {
    margin-bottom: 10px;
}

.logout-btn {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    background: #e74c3c;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 600;
}

.back-btn {
    position: absolute;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    background: #3498db;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 600;
}

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

.filters-bar {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 25px;
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.filter-label {
    font-weight: 600;
    color: #2c3e50;
}

.status-btn {
    padding: 8px 16px;
    border: 2px solid #ecf0f1;
    background: white;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    color: #2c3e50;
}

.status-btn:hover {
    background: #ecf0f1;
    border-color: #bdc3c7;
}

.status-btn.active {
    background: #3498db;
    border-color: #3498db;
    color: white;
}

.search-box {
    flex: 1;
    max-width: 300px;
}

.search-box input {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid #ecf0f1;
    border-radius: 5px;
    font-size: 14px;
}

.search-box input:focus {
    outline: none;
    border-color: #3498db;
}

.chat-container {
    display: flex;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
    border: 1px solid #ecf0f1;
    height: 600px;
}

.conversations-sidebar {
    width: 350px;
    border-right: 1px solid #ecf0f1;
    display: flex;
    flex-direction: column;
    background: #f8f9fa;
}

.conversations-header {
    padding: 20px;
    border-bottom: 1px solid #ecf0f1;
    background: white;
}

.conversations-header h3 {
    font-size: 18px;
    color: #2c3e50;
}

.conversations-list {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.conversation-item {
    padding: 15px;
    border-bottom: 1px solid #ecf0f1;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    background: white;
}

.conversation-item:hover {
    background: #f8f9fa;
}

.conversation-item.active {
    background: #e3f2fd;
    border-left: 4px solid #3498db;
}

.conversation-user {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: #3498db;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
}

.user-email {
    color: #7f8c8d;
    font-size: 12px;
}

.conversation-subject {
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
    margin-bottom: 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.conversation-preview {
    color: #7f8c8d;
    font-size: 13px;
    line-height: 1.4;
    margin: 5px 0;
}

.conversation-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 10px;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-open {
    background: #d4edda;
    color: #155724;
}

.status-resolved {
    background: #fff3cd;
    color: #856404;
}

.status-closed {
    background: #f8d7da;
    color: #721c24;
}

.unread-badge {
    background: #e74c3c;
    color: white;
    min-width: 20px;
    height: 20px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    padding: 0 6px;
}

.conversation-time {
    font-size: 12px;
    color: #95a5a6;
}

.chat-area {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.chat-header {
    padding: 20px;
    border-bottom: 1px solid #ecf0f1;
    background: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-user-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.chat-user-avatar {
    width: 50px;
    height: 50px;
    background: #3498db;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 20px;
}

.chat-user-details h3 {
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
}

.chat-user-details p {
    color: #7f8c8d;
    font-size: 14px;
}

.chat-actions {
    display: flex;
    gap: 10px;
}

.chat-btn {
    padding: 8px 16px;
    border: 2px solid #ecf0f1;
    background: white;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    color: inherit;
}

.chat-btn:hover {
    background: #ecf0f1;
}

.chat-btn.resolve {
    border-color: #28a745;
    color: #28a745;
}

.chat-btn.reopen {
    border-color: #17a2b8;
    color: #17a2b8;
}

.chat-btn.close {
    border-color: #dc3545;
    color: #dc3545;
}

.messages-container {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.message {
    max-width: 70%;
    padding: 12px 16px;
    border-radius: 10px;
    position: relative;
}

.message.sent {
    align-self: flex-end;
    background: #3498db;
    color: white;
}

.message.received {
    align-self: flex-start;
    background: white;
    color: #2c3e50;
    border: 1px solid #ecf0f1;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.sender-name {
    font-weight: 600;
    font-size: 13px;
}

.admin-badge {
    background: #e74c3c;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 5px;
}

.message-time {
    font-size: 12px;
    opacity: 0.8;
}

.message-content {
    font-size: 14px;
    line-height: 1.5;
}

.message-input-container {
    padding: 20px;
    border-top: 1px solid #ecf0f1;
    background: white;
}

.message-form {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.message-input {
    flex: 1;
    padding: 12px 15px;
    border: 2px solid #ecf0f1;
    border-radius: 5px;
    font-size: 14px;
    resize: none;
    min-height: 50px;
    max-height: 100px;
    line-height: 1.5;
}

.message-input:focus {
    outline: none;
    border-color: #3498db;
}

.send-btn {
    padding: 12px 25px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    height: 50px;
}

.send-btn:hover {
    background: #2980b9;
}

.send-btn:disabled {
    background: #95a5a6;
    cursor: not-allowed;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
    text-align: center;
    padding: 40px;
    color: #7f8c8d;
}

.empty-icon {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #2c3e50;
}

.empty-state p {
    margin-bottom: 20px;
    max-width: 400px;
    line-height: 1.5;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    font-weight: 600;
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

.scroll-bottom-btn {
    position: absolute;
    bottom: 20px;
    right: 20px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #3498db;
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
    background: #2980b9;
    transform: translateY(-2px);
}
</style>
</head>
<body>
    <div class="header">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <div>
            <h1>💬 Admin Chat Support</h1>
            <p>Manage user conversations and provide support</p>
        </div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <!-- Status Messages -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= isset($stats['open']) ? $stats['open'] : 0 ?></h3>
            <p>Open Conversations</p>
        </div>
        <div class="stat-card">
            <h3><?= isset($stats['resolved']) ? $stats['resolved'] : 0 ?></h3>
            <p>Resolved</p>
        </div>
        <div class="stat-card">
            <h3><?= isset($stats['closed']) ? $stats['closed'] : 0 ?></h3>
            <p>Closed</p>
        </div>
        <div class="stat-card">
            <h3><?= $total_unread ?></h3>
            <p>Unread Messages</p>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="filters-bar">
        <div class="filter-group">
            <span class="filter-label">Filter by status:</span>
            <div style="display: flex; gap: 5px;">
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
                <input type="text" name="search" value="<?= htmlspecialchars($filter_search) ?>" 
                       placeholder="Search conversations..." style="width: 100%;">
            </form>
        </div>
        
        <div style="font-size: 14px; color: #7f8c8d;">
            <span style="color: #2c3e50; font-weight: 600;"><?= count($conversations) ?></span> conversation<?= count($conversations) != 1 ? 's' : '' ?> found
        </div>
    </div>

    <!-- Chat Container -->
    <div class="chat-container">
        
        <!-- Conversations Sidebar -->
        <div class="conversations-sidebar">
            <div class="conversations-header">
                <h3>📋 User Conversations</h3>
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
                                    <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="conversation-preview" title="<?= htmlspecialchars($conv['last_message']) ?>">
                                <?= htmlspecialchars(substr($conv['last_message'], 0, 60)) ?>
                                <?= strlen($conv['last_message']) > 60 ? '...' : '' ?>
                            </div>
                            
                            <div class="conversation-meta">
                                <span class="status-badge status-<?= $conv['status'] ?>">
                                    <?= ucfirst($conv['status']) ?>
                                </span>
                                <div class="conversation-time">
                                    <?= date('M d', strtotime($conv['last_message_time'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
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
                                <?= htmlspecialchars($active_conversation['user_email']) ?> • 
                                Started <?= date('M d, Y', strtotime($active_conversation['created_at'])) ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="chat-actions">
                        <?php if ($active_conversation['status'] == 'open'): ?>
                            <a href="admin_chat.php?resolve=<?= $active_conversation['id'] ?>&status=<?= $filter_status ?><?= !empty($filter_search) ? '&search=' . urlencode($filter_search) : '' ?>" 
                               class="chat-btn resolve" onclick="return confirm('Mark this conversation as resolved?')">
                                ✅ Resolve
                            </a>
                        <?php elseif ($active_conversation['status'] == 'resolved'): ?>
                            <a href="admin_chat.php?reopen=<?= $active_conversation['id'] ?>&status=<?= $filter_status ?><?= !empty($filter_search) ? '&search=' . urlencode($filter_search) : '' ?>" 
                               class="chat-btn reopen">
                                ↩️ Re-open
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($active_conversation['status'] != 'closed'): ?>
                            <a href="admin_chat.php?close=<?= $active_conversation['id'] ?>&status=<?= $filter_status ?><?= !empty($filter_search) ? '&search=' . urlencode($filter_search) : '' ?>" 
                               class="chat-btn close" onclick="return confirm('Close this conversation? You can re-open it later.')">
                                ❌ Close
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Messages Container -->
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
                                    </span>
                                </div>
                                <div class="message-content">
                                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">💬</div>
                            <h3>No messages yet</h3>
                            <p>Start the conversation by sending a message</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Scroll to bottom button -->
                <button class="scroll-bottom-btn" id="scrollBottomBtn" title="Scroll to bottom">
                    ↓
                </button>
                
                <!-- Message Input -->
                <div class="message-input-container">
                    <form method="POST" action="" class="message-form">
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
                            📤 Send
                        </button>
                    </form>
                </div>
                
            <?php else: ?>
                <!-- Empty Chat State -->
                <div class="empty-state">
                    <div class="empty-icon">💬</div>
                    <h3>Select a conversation</h3>
                    <p>Choose a conversation from the sidebar to view and respond to messages</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Auto-resize textarea
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
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
        
        const threshold = 50;
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
            setTimeout(toggleScrollButton, 100);
        }
    }

    // Auto-scroll when page loads
    document.addEventListener('DOMContentLoaded', function() {
        setupScrollListener();
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
        
        // Auto-scroll when new messages might be added
        const messageForm = document.querySelector('.message-form');
        if (messageForm) {
            messageForm.addEventListener('submit', function() {
                setTimeout(scrollToBottom, 500);
            });
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        setTimeout(toggleScrollButton, 100);
    });
    </script>
</body>
</html>