<?php
session_start();
require '../db.php';

// Fix: Use the same session check as dashboard.php
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Handle status updates
if (isset($_GET['mark_read'])) {
    $feedback_id = intval($_GET['mark_read']);
    $conn->query("UPDATE feedback SET status='read' WHERE id=$feedback_id");
    header("Location: admin_feedback.php");
    exit;
}

if (isset($_GET['mark_replied'])) {
    $feedback_id = intval($_GET['mark_replied']);
    $conn->query("UPDATE feedback SET status='replied' WHERE id=$feedback_id");
    header("Location: admin_feedback.php");
    exit;
}

// Check if rating column exists
$check_rating = $conn->query("SHOW COLUMNS FROM feedback LIKE 'rating'");
$rating_column_exists = $check_rating->num_rows > 0;

// Get feedback with user info
$result = $conn->query("
    SELECT f.*, u.name AS user_name, u.email
    FROM feedback f
    JOIN users u ON f.user_id = u.id
    ORDER BY 
        CASE 
            WHEN f.status = 'new' THEN 1
            WHEN f.status = 'read' THEN 2
            ELSE 3
        END,
        f.created_at DESC
");

// Stats for dashboard
$total_feedback = $conn->query("SELECT COUNT(*) as cnt FROM feedback")->fetch_assoc()['cnt'];
$new_feedback = $conn->query("SELECT COUNT(*) as cnt FROM feedback WHERE status='new'")->fetch_assoc()['cnt'];

// Handle average rating calculation safely
if ($rating_column_exists) {
    $avg_rating_result = $conn->query("SELECT AVG(rating) as avg FROM feedback WHERE rating IS NOT NULL");
    $avg_rating_data = $avg_rating_result->fetch_assoc();
    $avg_rating = $avg_rating_data['avg'] ? number_format($avg_rating_data['avg'], 1) : '0.0';
} else {
    $avg_rating = 'N/A';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - User Feedback</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
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
        
        .feedback-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        th {
            background: #34495e;
            color: white;
            font-weight: 600;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-new {
            background: #ffeaa7;
            color: #e17055;
        }
        
        .status-read {
            background: #dfe6e9;
            color: #636e72;
        }
        
        .status-replied {
            background: #55efc4;
            color: #00b894;
        }
        
        .rating-stars {
            color: #fdcb6e;
            font-size: 16px;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-read {
            background: #3498db;
            color: white;
        }
        
        .btn-replied {
            background: #27ae60;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .navigation {
            text-align: center;
            margin-top: 30px;
        }
        
        .nav-btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 6px;
            margin: 0 10px;
            font-weight: 600;
        }

        .warning-banner {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📋 User Feedback Management</h1>
        <p>Review and manage user feedback and ratings</p>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <?php if (!$rating_column_exists): ?>
        <div class="warning-banner">
            ⚠️ <strong>Rating feature not available:</strong> The rating column is missing from the database. 
            <a href="javascript:void(0)" onclick="alert('Run the SQL query to add rating column to feedback table')">Click here for fix</a>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $total_feedback ?></h3>
            <p>Total Feedback</p>
        </div>
        <div class="stat-card">
            <h3><?= $new_feedback ?></h3>
            <p>New Feedback</p>
        </div>
        <div class="stat-card">
            <h3><?= $avg_rating ?> <?= $rating_column_exists ? '⭐' : '' ?></h3>
            <p>Average Rating</p>
        </div>
    </div>

    <!-- Feedback Table -->
    <div class="feedback-table">
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <?php if ($rating_column_exists): ?>
                            <th>Rating</th>
                        <?php endif; ?>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['user_name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['subject']) ?></td>
                        <?php if ($rating_column_exists): ?>
                            <td>
                                <div class="rating-stars">
                                    <?= str_repeat('★', $row['rating']) ?><?= str_repeat('☆', 5 - $row['rating']) ?>
                                </div>
                            </td>
                        <?php endif; ?>
                        <td style="max-width: 300px;"><?= nl2br(htmlspecialchars($row['message'])) ?></td>
                        <td><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                        <td>
                            <span class="status-badge status-<?= $row['status'] ?>">
                                <?= ucfirst($row['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($row['status'] == 'new'): ?>
                                    <a href="?mark_read=<?= $row['id'] ?>" class="btn btn-read">Mark Read</a>
                                <?php endif; ?>
                                <?php if ($row['status'] != 'replied'): ?>
                                    <a href="?mark_replied=<?= $row['id'] ?>" class="btn btn-replied">Mark Replied</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <div>📭</div>
                <h3>No feedback yet</h3>
                <p>Users haven't submitted any feedback.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="navigation">
        <a href="dashboard.php" class="nav-btn">← Back to Dashboard</a>
        <a href="analytics.php" class="nav-btn">📊 View Analytics</a>
    </div>
</body>
</html>