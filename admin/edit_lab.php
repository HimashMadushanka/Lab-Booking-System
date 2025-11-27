<?php
session_start();
require '../db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lab_id = intval($_POST['lab_id']);
    $new_capacity = intval($_POST['new_capacity']);
    
    // Get current lab info
    $current = $conn->query("SELECT name, capacity FROM labs WHERE id = $lab_id")->fetch_assoc();
    $current_capacity = $current['capacity'];
    $lab_name = $current['name'];
    
    if ($new_capacity != $current_capacity) {
        // Update lab capacity
        $conn->query("UPDATE labs SET capacity = $new_capacity WHERE id = $lab_id");
        
        if ($new_capacity > $current_capacity) {
            // Add new computers
            for ($i = $current_capacity + 1; $i <= $new_capacity; $i++) {
                $computer_code = $lab_name . " - PC " . $i;
                $conn->query("INSERT INTO computers (lab_id, code, status) VALUES ($lab_id, '$computer_code', 'available')");
            }
            
            $_SESSION['message'] = "✅ Lab capacity increased! Added " . ($new_capacity - $current_capacity) . " new computers to $lab_name.";
        } else {
            // Reduce capacity (remove excess computers)
            $conn->query("DELETE FROM computers WHERE lab_id = $lab_id AND id > $new_capacity");
            $_SESSION['message'] = "✅ Lab capacity updated! Removed " . ($current_capacity - $new_capacity) . " computers from $lab_name.";
        }
    } else {
        $_SESSION['message'] = "ℹ️ Capacity unchanged.";
    }
    
    header("Location: manage_labs.php");
    exit;
}
?>