<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $conversation_id = intval($_POST['conversation_id']);
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        // Send message as admin
        $msg_sql = "INSERT INTO chat_messages (conversation_id, sender_id, message) VALUES (?, ?, ?)";
        $msg_stmt = $mysqli->prepare($msg_sql);
        $msg_stmt->bind_param("iis", $conversation_id, $_SESSION['user_id'], $message);
        $msg_stmt->execute();
        
        // Update conversation timestamp
        $update_sql = "UPDATE chat_conversations SET updated_at = NOW() WHERE id = ?";
        $update_stmt = $mysqli->prepare($update_sql);
        $update_stmt->bind_param("i", $conversation_id);
        $update_stmt->execute();
        
        // Mark all messages as read
        $read_sql = "UPDATE chat_messages SET is_read = TRUE WHERE conversation_id = ?";
        $read_stmt = $mysqli->prepare($read_sql);
        $read_stmt->bind_param("i", $conversation_id);
        $read_stmt->execute();
        
        header("Location: admin_chat.php?conversation=" . $conversation_id);
        exit;
    }
}

// Handle conversation status changes
if (isset($_GET['status'])) {
    $conversation_id = intval($_GET['conversation']);
    $status = $_GET['status'];
    
    $status_sql = "UPDATE chat_conversations SET status = ? WHERE id = ?";
    $status_stmt = $mysqli->prepare($status_sql);
    $status_stmt->bind_param("si", $status, $conversation_id);
    $status_stmt->execute();
    
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
        
        // Mark all messages as read
        $read_sql = "UPDATE chat_messages SET is_read = TRUE WHERE conversation_id = ?";
        $read_stmt = $mysqli->prepare($read_sql);
        $read_stmt->bind_param("i", $active_conversation_id);
        $read_stmt->execute();
    }
}

// Get all conversations
$conversations_sql = "
    SELECT c.*, u.name as user_name,
           (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = c.id AND is_read = FALSE AND sender_id != ?) as unread_count,
           (SELECT message FROM chat_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
    FROM chat_conversations c 
    JOIN users u ON c.user_id = u.id 
    ORDER BY c.updated_at DESC
";
$conversations_stmt = $mysqli->prepare($conversations_sql);
$conversations_stmt->bind_param("i", $_SESSION['user_id']);
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
<title>Admin Chat | LabEase</title>
<style>
/* Similar styling to user chat but with admin colors */
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #f8f9fc; }

.container { max-width: 1400px; margin: 0 auto; padding: 20px; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.header h1 { color: #1e293b; }
.chat-container { display: flex; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); height: calc(100vh - 150px); }

/* Admin-specific styles */
.admin-badge { background: #f59e0b; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
.message.sent.admin { background: #f59e0b; }
.status-badge.status-open { background: #d1fae5; color: #065f46; }
.status-badge.status-resolved { background: #fef3c7; color: #92400e; }
.status-badge.status-closed { background: #f3f4f6; color: #6b7280; }

/* Rest of the styles similar to user chat... */
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>💬 Admin Chat Support</h1>
        <div>
            <a href="admin_dashboard.php">← Back to Dashboard</a>
            <?php if ($unread_conv_count > 0): ?>
                <span style="background: #ef4444; color: white; padding: 4px 12px; border-radius: 20px; margin-left: 10px;">
                    <?= $unread_conv_count ?> unread
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="chat-container">
        <!-- Similar layout to user chat but with admin features -->
        <div style="width: 300px; border-right: 1px solid #e5e7eb;">
            <div style="padding: 20px; border-bottom: 1px solid #e5e7eb;">
                <h3 style="margin-bottom: 15px;">Conversations</h3>
                <input type="text" placeholder="Search conversations..." style="width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px;">
            </div>
            <div style="overflow-y: auto; height: calc(100% - 100px);">
                <?php foreach($conversations as $conv): ?>
                <div style="padding: 15px; border-bottom: 1px solid #f3f4f6; cursor: pointer; background: <?= $conv['id'] == $active_conversation_id ? '#f0f9ff' : 'white' ?>;"
                     onclick="window.location.href='admin_chat.php?conversation=<?= $conv['id'] ?>'">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <strong><?= htmlspecialchars($conv['subject']) ?></strong>
                        <?php if ($conv['unread_count'] > 0): ?>
                        <span style="background: #ef4444; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px;">
                            <?= $conv['unread_count'] ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 13px; color: #6b7280; margin-bottom: 3px;">
                        <?= htmlspecialchars($conv['user_name']) ?>
                    </div>
                    <div style="font-size: 12px; color: #9ca3af;">
                        <?= date('M d, h:i A', strtotime($conv['updated_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div style="flex: 1; display: flex; flex-direction: column;">
            <?php if ($active_conversation): ?>
            <div style="padding: 20px; border-bottom: 1px solid #e5e7eb; background: white;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin-bottom: 5px;"><?= htmlspecialchars($active_conversation['subject']) ?></h3>
                        <div style="color: #6b7280; font-size: 14px;">
                            Chatting with: <strong><?= htmlspecialchars($active_conversation['user_name']) ?></strong>
                            (<?= htmlspecialchars($active_conversation['user_email']) ?>)
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="window.location.href='admin_chat.php?conversation=<?= $active_conversation['id'] ?>&status=resolved'"
                                style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer;">
                            Mark Resolved
                        </button>
                        <button onclick="window.location.href='admin_chat.php?conversation=<?= $active_conversation['id'] ?>&status=closed'"
                                style="padding: 8px 16px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer;">
                            Close Chat
                        </button>
                    </div>
                </div>
            </div>
            
            <div style="flex: 1; padding: 20px; overflow-y: auto; background: #f9fafb;">
                <?php foreach($messages as $message): ?>
                <div style="margin-bottom: 15px; max-width: 70%; <?= $message['sender_role'] == 'admin' ? 'margin-left: auto;' : '' ?>">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 12px;">
                        <strong>
                            <?= htmlspecialchars($message['sender_name']) ?>
                            <?php if ($message['sender_role'] == 'admin'): ?>
                                <span class="admin-badge">ADMIN</span>
                            <?php endif; ?>
                        </strong>
                        <span style="color: #9ca3af;"><?= date('h:i A', strtotime($message['created_at'])) ?></span>
                    </div>
                    <div style="background: <?= $message['sender_role'] == 'admin' ? '#f59e0b' : 'white' ?>; 
                                color: <?= $message['sender_role'] == 'admin' ? 'white' : '#1f2937' ?>;
                                padding: 12px 16px; border-radius: 12px; 
                                <?= $message['sender_role'] == 'admin' ? 'border-bottom-right-radius: 4px;' : 'border-bottom-left-radius: 4px;' ?>
                                <?= $message['sender_role'] != 'admin' ? 'border: 1px solid #e5e7eb;' : '' ?>">
                        <?= nl2br(htmlspecialchars($message['message'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div style="padding: 20px; border-top: 1px solid #e5e7eb; background: white;">
                <form method="POST" action="" style="display: flex; gap: 10px;">
                    <input type="hidden" name="conversation_id" value="<?= $active_conversation['id'] ?>">
                    <textarea name="message" placeholder="Type your response..." 
                              style="flex: 1; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; resize: vertical; min-height: 50px;"></textarea>
                    <button type="submit" name="send_message" 
                            style="padding: 0 30px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        Send
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div style="display: flex; align-items: center; justify-content: center; flex: 1; text-align: center; color: #6b7280;">
                <div>
                    <div style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;">💬</div>
                    <h3 style="margin-bottom: 10px;">Select a conversation</h3>
                    <p>Choose a conversation from the sidebar to start chatting</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>