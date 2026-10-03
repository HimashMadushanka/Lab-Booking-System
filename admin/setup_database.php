<?php
require 'db.php';

echo "<h2>Setting up LabEase Database</h2>";

// Check if admin user exists in users table
$check_sql = "SELECT * FROM users WHERE role = 'admin'";
$result = $mysqli->query($check_sql);

if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Admin user already exists in users table.</p>";
} else {
    // Create admin user
    $sql = "INSERT INTO users (name, email, password, role, created_at) 
            VALUES ('System Admin', 'admin@labease.com', 'e10adc3949ba59abbe56e057f20f883e', 'admin', NOW())";
    
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ Admin user created successfully!</p>";
        echo "<p>Login with: admin@labease.com / 123456</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating admin user: " . $mysqli->error . "</p>";
    }
}

// Update conversation status
$update_sql = "UPDATE chat_conversations SET status = 'open' WHERE status = 'closed'";
if ($mysqli->query($update_sql)) {
    echo "<p style='color: green;'>✓ All conversations re-opened.</p>";
}

// Show current status
echo "<hr><h3>Current Database Status:</h3>";

$tables = ['users', 'admin', 'chat_conversations', 'chat_messages'];
foreach ($tables as $table) {
    echo "<h4>$table:</h4>";
    $result = $mysqli->query("SELECT COUNT(*) as count FROM $table");
    $count = $result->fetch_assoc()['count'];
    echo "<p>Records: $count</p>";
}

echo "<p><a href='admin/admin_chat.php'>Go to Admin Chat</a></p>";
?>