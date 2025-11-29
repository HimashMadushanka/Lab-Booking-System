<?php
session_start();
require '../db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
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

$result = $conn->query("
    SELECT b.*, u.name AS user_name, c.code AS computer_code, l.name as lab_name
    FROM bookings b
    JOIN users u ON b.user_id=u.id
    JOIN computers c ON b.computer_id=c.id
    JOIN labs l ON c.lab_id = l.id
    ORDER BY 
        CASE 
            WHEN b.status = 'pending' THEN 1
            WHEN b.status = 'approved' THEN 2
            ELSE 3
        END,
        b.date DESC
");

// Stats for dashboard
$total_bookings = $conn->query("SELECT COUNT(*) as cnt FROM bookings")->fetch_assoc()['cnt'];
$pending_bookings = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status='pending'")->fetch_assoc()['cnt'];
$approved_bookings = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status='approved'")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Manage Bookings</title>
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

.booking-table {
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

.status-pending {
    background: #ffeaa7;
    color: #e17055;
}

.status-approved {
    background: #55efc4;
    color: #00b894;
}

.status-rejected {
    background: #fab1a0;
    color: #d63031;
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

.btn-approve {
    background: #27ae60;
    color: white;
}

.btn-reject {
    background: #e74c3c;
    color: white;
}

.btn-delete {
    background: #e67e22;
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

.conflict-warning {
    background: #fef3c7;
    color: #92400e;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 11px;
    margin-top: 5px;
    display: inline-block;
}
</style>
</head>
<body>
    <div class="header">
        <h1>📅 Manage Bookings</h1>
        <p>Review and manage lab booking requests</p>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <!-- Status Messages -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">❌ <?= $error_message ?></div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">✅ <?= $success_message ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $total_bookings ?></h3>
            <p>Total Bookings</p>
        </div>
        <div class="stat-card">
            <h3><?= $pending_bookings ?></h3>
            <p>Pending Bookings</p>
        </div>
        <div class="stat-card">
            <h3><?= $approved_bookings ?></h3>
            <p>Approved Bookings</p>
        </div>
    </div>

    <!-- Booking Table -->
    <div class="booking-table">
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Computer</th>
                        <th>Lab</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['user_name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['computer_code']) ?></td>
                        <td><?= htmlspecialchars($row['lab_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                        <td><?= $row['start_time'].' - '.$row['end_time'] ?></td>
                        <td>
                            <span class="status-badge status-<?= $row['status'] ?>">
                                <?= ucfirst($row['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if($row['status'] == 'pending'): ?>
                                    <a href="approve.php?id=<?= $row['id'] ?>" class="btn btn-approve" 
                                       onclick="return confirm('Approve this booking?')">Approve</a>
                                    <a href="reject.php?id=<?= $row['id'] ?>" class="btn btn-reject"
                                       onclick="return confirm('Reject this booking?')">Reject</a>
                                <?php else: ?>
                                    <a href="delete_booking.php?id=<?= $row['id'] ?>" class="btn btn-delete" 
                                       onclick="return confirm('Are you sure you want to delete this booking?')">Delete</a>
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
                <h3>No bookings yet</h3>
                <p>There are no booking requests at the moment.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="navigation">
        <a href="dashboard.php" class="nav-btn">← Back to Dashboard</a>
    </div>
</body>
</html>