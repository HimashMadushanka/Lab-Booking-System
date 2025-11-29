<?php
session_start();
require '../db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lab_id = intval($_POST['lab_id']);
    
    // Get lab name for message
    $lab = $conn->query("SELECT name FROM labs WHERE id = $lab_id")->fetch_assoc();
    $lab_name = $lab['name'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete related bookings first
        $conn->query("DELETE b FROM bookings b 
                     JOIN computers c ON b.computer_id = c.id 
                     WHERE c.lab_id = $lab_id");
        
        // Delete computers
        $conn->query("DELETE FROM computers WHERE lab_id = $lab_id");
        
        // Delete the lab
        $conn->query("DELETE FROM labs WHERE id = $lab_id");
        
        $conn->commit();
        $_SESSION['message'] = "✅ Lab '$lab_name' deleted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "❌ Error deleting lab: " . $e->getMessage();
    }
    
    header("Location: manage_labs.php");
    exit;
}
?>