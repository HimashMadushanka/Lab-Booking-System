<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$result = $conn->query("
    SELECT f.*, u.name AS user_name, u.email
    FROM feedback f
    JOIN users u ON f.user_id = u.id
    ORDER BY f.created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Feedback</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h3 class="text-center mb-4">📋 User Feedback</h3>

    <?php if ($result->num_rows > 0): ?>
        <table class="table table-bordered table-striped bg-white shadow-sm">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['user_name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['subject']) ?></td>
                    <td><?= nl2br(htmlspecialchars($row['message'])) ?></td>
                    <td><?= $row['created_at'] ?></td>
                    <td><?= $row['status'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-info text-center">No feedback yet.</div>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="admin_dashboard.php" class="btn btn-secondary">⬅ Back to Dashboard</a>
    </div>
</div>
</body>
</html>
