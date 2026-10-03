<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require '../db.php';

$success_message = "";
$error_message = "";

// --- Handle Create / Update Lab ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_name = trim($_POST['name']);
    $lab_location = trim($_POST['location']);
    $lab_capacity = intval($_POST['capacity']);
    $lab_id = $_POST['lab_id'] ?? null;

    // --- Handle file upload ---
    $photo_filename = null;
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "../uploads/labs/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $photo_filename = time() . "_" . basename($_FILES["photo"]["name"]);
        $target_file = $target_dir . $photo_filename;

        $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];

        if (!in_array($fileType, $allowed)) {
            $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
        } elseif ($_FILES["photo"]["size"] > 5000000) { // 5MB limit
            $error_message = "File size must be less than 5MB.";
        } elseif (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            // Success - file uploaded
        } else {
            $error_message = "Error uploading photo.";
        }
    }

    if (!$error_message) {
        if ($lab_id) {
            // Update existing lab - delete old photo if new one uploaded
            if ($photo_filename) {
                // Get old photo to delete
                $old_photo = $mysqli->query("SELECT photo FROM labs WHERE id=$lab_id")->fetch_assoc()['photo'];
                if ($old_photo && file_exists($target_dir . $old_photo)) {
                    unlink($target_dir . $old_photo);
                }
                
                $stmt = $mysqli->prepare("UPDATE labs SET name=?, location=?, capacity=?, photo=? WHERE id=?");
                $stmt->bind_param("ssisi", $lab_name, $lab_location, $lab_capacity, $photo_filename, $lab_id);
            } else {
                $stmt = $mysqli->prepare("UPDATE labs SET name=?, location=?, capacity=? WHERE id=?");
                $stmt->bind_param("siii", $lab_name, $lab_location, $lab_capacity, $lab_id);
            }
            if ($stmt->execute()) {
                $success_message = "Lab updated successfully!";
            } else {
                $error_message = "Failed to update lab.";
            }
        } else {
            // Create new lab
            $stmt = $mysqli->prepare("INSERT INTO labs (name, location, capacity, photo) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssis", $lab_name, $lab_location, $lab_capacity, $photo_filename);
            if ($stmt->execute()) {
                $success_message = "New lab created successfully!";
            } else {
                $error_message = "Failed to create lab.";
            }
        }
    }
}

// --- Handle Delete Lab ---
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    // Delete photo file
    $lab = $mysqli->query("SELECT photo FROM labs WHERE id=$delete_id")->fetch_assoc();
    if ($lab['photo'] && file_exists("../uploads/labs/".$lab['photo'])) {
        unlink("../uploads/labs/".$lab['photo']);
    }
    if ($mysqli->query("DELETE FROM labs WHERE id=$delete_id")) {
        $success_message = "Lab deleted successfully!";
    } else {
        $error_message = "Failed to delete lab. Make sure no computers or bookings are associated with this lab.";
    }
}

// --- Fetch Labs ---
$labs_result = $mysqli->query("SELECT * FROM labs ORDER BY id ASC");

// --- Fetch lab for editing ---
$edit_lab = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_result = $mysqli->query("SELECT * FROM labs WHERE id=$edit_id");
    if ($edit_result->num_rows > 0) {
        $edit_lab = $edit_result->fetch_assoc();
    }
}

// Get statistics
$labs = $mysqli->query("SELECT * FROM labs ORDER BY id ASC");
$total_labs = $labs->num_rows;
$labs_with_computers = $mysqli->query("SELECT COUNT(DISTINCT lab_id) as cnt FROM computers")->fetch_assoc()['cnt'];
$labs_with_photos = $mysqli->query("SELECT COUNT(*) as cnt FROM labs WHERE photo IS NOT NULL AND photo != ''")->fetch_assoc()['cnt'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Labs</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #b8eaf8ff;
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

        .form-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-container h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-group input, 
        .form-group .file-input-wrapper {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .file-input-wrapper {
            position: relative;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-input-wrapper:hover {
            border-color: #3498db;
            background: #e3f2fd;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-input-label {
            color: #495057;
        }

        .current-photo {
            margin-top: 15px;
            text-align: center;
        }

        .current-photo img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 5px;
            background: white;
        }

        .photo-preview {
            margin-top: 10px;
            text-align: center;
        }

        .photo-preview img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px dashed #3498db;
            padding: 5px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
            margin-left: 10px;
        }

        .btn-edit {
            background: #f39c12;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-view {
            background: #27ae60;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .labs-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
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

        .lab-photo {
            text-align: center;
        }

        .lab-photo img {
            max-width: 80px;
            max-height: 60px;
            border-radius: 6px;
            object-fit: cover;
            border: 2px solid #e9ecef;
            transition: transform 0.3s ease;
        }

        .lab-photo img:hover {
            transform: scale(1.5);
        }

        .no-photo {
            color: #95a5a6;
            font-style: italic;
            font-size: 12px;
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

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .capacity-badge {
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .photo-badge {
            background: #9b59b6;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔬 Manage Labs</h1>
        <p>Create, edit, and manage computer labs with photos</p>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <!-- Status Messages -->
    <?php if ($error_message): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= htmlspecialchars($total_labs) ?></h3>
            <p>Total Labs</p>
        </div>

        <div class="stat-card">
            <h3><?= htmlspecialchars($labs_with_photos) ?></h3>
            <p>Labs with Photos</p>
        </div>
    </div>

    <!-- Lab Form -->
    <div class="form-container">
        <h3><?= $edit_lab ? "✏️ Edit Lab" : "➕ Add New Lab" ?></h3>
        <form method="post" enctype="multipart/form-data" id="labForm">
            <input type="hidden" name="lab_id" value="<?= $edit_lab['id'] ?? '' ?>">
            
            <div class="form-group">
                <label for="name">Lab Name</label>
                <input type="text" id="name" name="name" 
                       value="<?= htmlspecialchars($edit_lab['name'] ?? '') ?>" 
                       placeholder="e.g., Computer Lab 1" required>
            </div>

            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" 
                       value="<?= htmlspecialchars($edit_lab['location'] ?? '') ?>" 
                       placeholder="e.g., Building A, Room 101">
            </div>

            <div class="form-group">
                <label for="capacity">Capacity (Number of Computers)</label>
                <input type="number" id="capacity" name="capacity" 
                       value="<?= $edit_lab['capacity'] ?? '' ?>" 
                       placeholder="e.g., 25" min="1" required>
            </div>

            <div class="form-group">
                <label>Lab Photo</label>
                <div class="file-input-wrapper">
                    <div class="file-input-label">
                        📷 Click to upload photo (Max 5MB)
                    </div>
                    <input type="file" name="photo" id="photo" accept="image/*" 
                           onchange="previewImage(this)">
                </div>
                <small style="color: #6c757d; display: block; margin-top: 5px;">
                    Supported formats: JPG, JPEG, PNG, GIF
                </small>
                
                <!-- Photo Preview -->
                <div id="photoPreview" class="photo-preview" style="display: none;">
                    <p>New photo preview:</p>
                    <img id="previewImage" src="" alt="Preview">
                </div>
                
                <!-- Current Photo -->
                <?php if (isset($edit_lab['photo']) && $edit_lab['photo']): ?>
                <div class="current-photo">
                    <p>Current photo:</p>
                    <img src="../uploads/labs/<?= htmlspecialchars($edit_lab['photo']) ?>" 
                         alt="Current Lab Photo" 
                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgZmlsbD0iI2Y4ZjlmYSIvPjx0ZXh0IHg9IjEwMCUieT0iNTAlIiBkeT0iMC4zZW0iIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IiM2Yzc1N2QiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNHB4Ij5MYWIgUGhvdG88L3RleHQ+PC9zdmc+'">
                </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">
                <?= $edit_lab ? "Update Lab" : "Create Lab" ?>
            </button>
            
            <?php if ($edit_lab): ?>
                <a href="admin_labs.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Labs Table -->
    <div class="labs-table">
        <?php if ($labs_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Lab Name</th>
                        <th>Location</th>
                        <th>Capacity</th>
                        <th>Photo</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($lab = $labs_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $lab['id'] ?></td>
                        <td><strong><?= htmlspecialchars($lab['name']) ?></strong></td>
                        <td><?= htmlspecialchars($lab['location']) ?></td>
                        <td><span class="capacity-badge"><?= $lab['capacity'] ?> computers</span></td>
                        <td class="lab-photo">
                            <?php if($lab['photo']): ?>
                                <img src="../uploads/labs/<?= htmlspecialchars($lab['photo']) ?>" 
                                     alt="Lab Photo"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZjhmOWZhIi8+PHRleHQgeD0iNTAlInk9IjUwJSIgZHk9IjAuM2VtIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjNmM3NTdkIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTJweCI+UGhvdG88L3RleHQ+PC9zdmc+'" 
                                     title="Click to enlarge">
                            <?php else: ?>
                                <span class="no-photo">No photo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="admin_labs.php?edit=<?= $lab['id'] ?>" class="btn btn-edit">Edit</a>
                                <?php if($lab['photo']): ?>
                                    <a href="../uploads/labs/<?= htmlspecialchars($lab['photo']) ?>" 
                                       target="_blank" 
                                       class="btn btn-view">View</a>
                                <?php endif; ?>
                                <a href="admin_labs.php?delete=<?= $lab['id'] ?>" 
                                   class="btn btn-delete" 
                                   onclick="return confirm('Are you sure you want to delete this lab? This will also delete all computers in this lab.');">
                                   Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <div>🏢</div>
                <h3>No labs yet</h3>
                <p>Create your first lab using the form above.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Navigation -->
    <div class="navigation">
        <a href="dashboard.php" class="nav-btn">← Back to Dashboard</a>
  
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('photoPreview');
            const previewImage = document.getElementById('previewImage');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }

        // Form validation
        document.getElementById('labForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('photo');
            if (fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size / 1024 / 1024; // in MB
                if (fileSize > 5) {
                    alert('File size must be less than 5MB');
                    e.preventDefault();
                    return false;
                }
                
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(fileInput.files[0].type)) {
                    alert('Only JPG, JPEG, PNG & GIF files are allowed.');
                    e.preventDefault();
                    return false;
                }
            }
            return true;
        });
    </script>
</body>
</html>