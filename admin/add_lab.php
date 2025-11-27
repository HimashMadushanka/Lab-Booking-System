<?php
session_start();
require '../db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lab_name = $conn->real_escape_string($_POST['lab_name']);
    $location = $conn->real_escape_string($_POST['location']);
    $capacity = intval($_POST['capacity']);
    
    // Check if lab name already exists
    $check = $conn->query("SELECT id FROM labs WHERE name = '$lab_name'");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "Lab name already exists!";
        header("Location: manage_labs.php");
        exit;
    }
    
    // Insert new lab
    $stmt = $conn->prepare("INSERT INTO labs (name, location, capacity) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $lab_name, $location, $capacity);
    
    if ($stmt->execute()) {
        $lab_id = $stmt->insert_id;
        
        // Create computers for this lab
        for ($i = 1; $i <= $capacity; $i++) {
            $computer_code = $lab_name . " - PC " . $i;
            $conn->query("INSERT INTO computers (lab_id, code, status) VALUES ($lab_id, '$computer_code', 'available')");
        }
        
        $_SESSION['message'] = "✅ Lab '$lab_name' added successfully with $capacity computers!";
    } else {
        $_SESSION['error'] = "❌ Error adding lab: " . $conn->error;
    }
    
    $stmt->close();
    header("Location: manage_labs.php");
    exit;
}
?>