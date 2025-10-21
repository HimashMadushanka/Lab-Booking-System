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
    $rating = intval($_POST['rating']);

    if ($subject && $message && $rating) {
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, subject, message, rating) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $user_id, $subject, $message, $rating);
        
        if ($stmt->execute()) {
            $success = "✅ Thank you for your feedback! We'll review it soon.";
        } else {
            $error = "❌ Failed to submit feedback. Please try again.";
        }
    } else {
        $error = "⚠️ Please fill all fields and provide a rating.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback | Lab Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }

        /* Sidebar Navigation */
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
            padding: 40px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .feedback-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 600px;
        }

        .page-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .page-header h2 {
            font-size: 28px;
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #64748b;
            font-size: 15px;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            line-height: 1.5;
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

        form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        label {
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            color: #1e293b;
            transition: all 0.3s ease;
            outline: none;
            background: white;
        }

        input:focus, textarea:focus, select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .rating-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .stars {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        .star {
            font-size: 32px;
            cursor: pointer;
            color: #e2e8f0;
            transition: color 0.2s ease;
        }

        .star:hover,
        .star.active {
            color: #fbbf24;
        }

        .rating-labels {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }

        button {
            width: 100%;
            padding: 14px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        button:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #3b82f6;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .feedback-container {
                padding: 30px 25px;
            }

            .page-header h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-logo">
        <h2>🖥️ Lab Manager</h2>
        <p>Computer Lab System</p>
    </div>
    
    <ul class="sidebar-menu">
        <li><a href="index.php"><span>📊</span> Dashboard</a></li>
        <li><a href="create.php"><span>➕</span> Book a Lab</a></li>
        <li><a href="mybookings.php"><span>📋</span> My Bookings</a></li>
        <li><a href="feedback.php" class="active"><span>💬</span> Give Feedback</a></li>
    </ul>
    
    <div class="logout-btn">
        <a href="logout.php">Logout</a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="feedback-container">
        
        <div class="page-header">
            <h2>💬 Send Feedback</h2>
            <p>We value your opinion! Share your experience with us.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="subject">📋 Subject</label>
                <input type="text" name="subject" id="subject" placeholder="Brief description of your feedback" required>
            </div>

            <div class="form-group rating-group">
                <label>⭐ Rating</label>
                <div class="stars" id="stars">
                    <span class="star" data-value="1">★</span>
                    <span class="star" data-value="2">★</span>
                    <span class="star" data-value="3">★</span>
                    <span class="star" data-value="4">★</span>
                    <span class="star" data-value="5">★</span>
                </div>
                <div class="rating-labels">
                    <span>Poor</span>
                    <span>Excellent</span>
                </div>
                <input type="hidden" name="rating" id="rating" value="5" required>
            </div>

            <div class="form-group">
                <label for="message">📝 Your Message</label>
                <textarea name="message" id="message" placeholder="Please share your detailed feedback, suggestions, or issues..." required></textarea>
            </div>

            <button type="submit">📤 Submit Feedback</button>
        </form>

        <div class="back-link">
            <a href="index.php">← Back to Dashboard</a>
        </div>

    </div>
</div>

<script>
    // Star rating functionality
    const stars = document.querySelectorAll('.star');
    const ratingInput = document.getElementById('rating');
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const value = this.getAttribute('data-value');
            ratingInput.value = value;
            
            // Update star appearance
            stars.forEach(s => {
                if (s.getAttribute('data-value') <= value) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
        });
        
        star.addEventListener('mouseover', function() {
            const value = this.getAttribute('data-value');
            stars.forEach(s => {
                if (s.getAttribute('data-value') <= value) {
                    s.style.color = '#fbbf24';
                } else {
                    s.style.color = '#e2e8f0';
                }
            });
        });
        
        star.addEventListener('mouseout', function() {
            stars.forEach(s => {
                const value = s.getAttribute('data-value');
                if (value <= ratingInput.value) {
                    s.style.color = '#fbbf24';
                } else {
                    s.style.color = '#e2e8f0';
                }
            });
        });
    });

    // Initialize with 5 stars
    stars.forEach(star => {
        if (star.getAttribute('data-value') <= 5) {
            star.classList.add('active');
        }
    });
</script>

</body>
</html>