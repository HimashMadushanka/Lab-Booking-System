<?php
// verify_otp.php
require 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_SESSION['reset_email'] ?? '';
    $otp = trim($_POST['otp'] ?? '');
    
    if (empty($email) || empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }
    
    // Verify OTP
    $stmt = $conn->prepare("SELECT id, otp_code, otp_expires FROM users WHERE email = ? AND otp_code = ?");
    $stmt->bind_param('ss', $email, $otp);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if OTP is expired
        if (strtotime($user['otp_expires']) < time()) {
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
            exit;
        }
        
        // OTP is valid, create reset token
        $reset_token = bin2hex(random_bytes(32));
        $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ?, otp_code = NULL, otp_expires = NULL WHERE email = ?");
        $update_stmt->bind_param('sss', $reset_token, $reset_expires, $email);
        
        if ($update_stmt->execute()) {
            $_SESSION['reset_token'] = $reset_token;
            echo json_encode(['success' => true, 'message' => 'OTP verified successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP code.']);
    }
}
?>