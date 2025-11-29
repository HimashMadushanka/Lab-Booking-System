<?php
// forgot_password.php
require 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your email address.']);
        exit;
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Generate 6-digit OTP
        $otp = sprintf("%06d", mt_rand(1, 999999));
        $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Store OTP in database
        $update_stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires = ? WHERE email = ?");
        $update_stmt->bind_param('sss', $otp, $otp_expires, $email);
        
        if ($update_stmt->execute()) {
            $_SESSION['reset_email'] = $email;
            
            // Return OTP in response for display
            echo json_encode([
                'success' => true, 
                'message' => 'OTP generated successfully!',
                'otp' => $otp
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found.']);
    }
}
?>