<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if ($subject && $message) {
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, subject, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $subject, $message);
        if ($stmt->execute()) {
            $success = "Feedback submitted successfully!";
        } else {
            $error = "Failed to submit feedback.";
        }
    } else {
        $error = "Please fill all fields.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Feedback</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow-lg p-4 mx-auto" style="max-width: 600px;">
        <h3 class="text-center mb-4">💬 Send Feedback</h3>
        <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <form method="POST">
            <div class="mb-3">
                <label>Subject</label>
                <input type="text" name="subject" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Message</label>
                <textarea name="message" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100">Submit</button>
        </form>
        <div class="mt-3 text-center">
            <a href="index.php" class="btn btn-link">⬅ Back to Dashboard</a>
        </div>
    </div>
</div>
</body>
</html>
