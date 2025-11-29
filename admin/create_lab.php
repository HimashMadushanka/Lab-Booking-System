<?php
session_start();
require '../db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_lab'])) {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $capacity = intval($_POST['capacity']);
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name) || empty($location) || empty($capacity)) {
        $error = "Please fill in all required fields.";
    } elseif ($capacity <= 0) {
        $error = "Capacity must be a positive number.";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM labs WHERE name = ?");
        $check_stmt->bind_param('s', $name);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "A lab with this name already exists.";
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO labs (name, location, capacity, description)
                                           VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param('ssis', $name, $location, $capacity, $description);
            
            if ($insert_stmt->execute()) {
                $success = "Lab '$name' created successfully!";
                $_POST = array();
            } else {
                $error = "Error creating lab: " . $conn->error;
            }
        }
    }
}

// Handle lab deletion
if (isset($_GET['delete_id'])) {
    $lab_id = intval($_GET['delete_id']);
    
    // Check computers
    $check_computers = $conn->query("SELECT COUNT(*) AS cnt FROM computers WHERE lab_id = $lab_id");
    $computer_count = $check_computers->fetch_assoc()['cnt'];
    
    if ($computer_count > 0) {
        $error = "Cannot delete lab. It has $computer_count computer(s).";
    } else {
        // Check bookings
        $check_bookings = $conn->query("
            SELECT COUNT(*) AS cnt FROM bookings 
            WHERE computer_id IN (SELECT id FROM computers WHERE lab_id = $lab_id)
        ");
        $booking_count = $check_bookings->fetch_assoc()['cnt'];
        
        if ($booking_count > 0) {
            $error = "Cannot delete lab. It has $booking_count booking(s).";
        } else {
            $delete_stmt = $conn->prepare("DELETE FROM labs WHERE id = ?");
            $delete_stmt->bind_param('i', $lab_id);
            $delete_stmt->execute();
            $success = "Lab deleted successfully.";
        }
    }
}

// Get labs
$existing_labs = $conn->query("
    SELECT l.*, 
           COUNT(c.id) AS computer_count,
           (SELECT COUNT(*) FROM bookings b 
                JOIN computers c ON b.computer_id = c.id 
                WHERE c.lab_id = l.id) AS booking_count
    FROM labs l 
    LEFT JOIN computers c ON l.id = c.lab_id 
    GROUP BY l.id 
    ORDER BY l.name
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create New Lab - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #b8eaf8;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #172c45ff 0%,  #401c62ff 100%);
            color: white;
            padding: 30px;
            height: 130px;
            border-radius: 20px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .logout-btn, .back-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            color: white;
        }
        .logout-btn { right: 15px; background: #e74c3c; }


        .container {
            max-width: 1000px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
        }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        input, textarea {
            width: 100%; padding: 12px; border: 2px solid #e0e0e0;
            border-radius: 8px; margin-top: 5px;
        }
        button {
            background: #27ae60;
            color: white;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            margin-top: 15px;
        }

        .labs-grid {
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(auto-fill, minmax(300px,1fr));
        }
        .lab-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }
        .delete-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 15px;
            background: #e74c3c;
            color: white;
            border-radius: 6px;
            text-decoration: none;
        }
        .delete-btn:disabled {
            background: #bdc3c7;
        }

                .navigation {
            text-align: center;
            margin-top: 30px;
        }
        
        .nav-btn {
            display: inline-block;
            background: #2980b9;
            color: white;
            padding: 14px 30px;
            text-decoration: none;
            border-radius: 25px;
            margin: 0 10px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .nav-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
    </style>
</head>

<body>

<div class="header">
    <h1>Create New Lab</h1>

    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<div class="container">

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <h2>🏢 Create New Lab</h2>

    <form method="POST">
        <label>Lab Name *</label>
        <input type="text" name="name" required
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

        <label>Location *</label>
        <input type="text" name="location" required
               value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">

        <label>Capacity *</label>
        <input type="number" name="capacity" min="1" required
               value="<?= htmlspecialchars($_POST['capacity'] ?? '') ?>">

        <label>Description</label>
        <textarea name="description"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

        <button type="submit" name="create_lab">➕ Create Lab</button>
    </form>

    <h2 style="margin-top: 40px;">📋 Existing Labs (<?= $existing_labs->num_rows ?>)</h2>

    <div class="labs-grid">
        <?php while($lab = $existing_labs->fetch_assoc()): ?>
            <?php $can_delete = ($lab['computer_count'] == 0 && $lab['booking_count'] == 0); ?>

            <div class="lab-card">
                <h3><?= htmlspecialchars($lab['name']) ?></h3>
                <p><b>📍 Location:</b> <?= htmlspecialchars($lab['location']) ?></p>
                <p><b>📊 Capacity:</b> <?= $lab['capacity'] ?></p>
                <p><b>Computers:</b> <?= $lab['computer_count'] ?></p>
                <p><b>Bookings:</b> <?= $lab['booking_count'] ?></p>

                <?php if ($can_delete): ?>
                    <a href="create_lab.php?delete_id=<?= $lab['id'] ?>"
                       class="delete-btn"
                       onclick="return confirm('Are you sure you want to delete lab <?= addslashes($lab['name']) ?>? This action cannot be undone.');">
                       🗑️ Delete Lab
                    </a>
                <?php else: ?>
                    <button class="delete-btn" disabled>Cannot Delete</button>
                <?php endif; ?>
            </div>

        <?php endwhile; ?>
    </div>

</div>
    <div class="navigation">
        <a href="dashboard.php" class="nav-btn">← Back to Dashboard</a></div>
<script>
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(el => {
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 5000);
</script>

</body>
</html>
