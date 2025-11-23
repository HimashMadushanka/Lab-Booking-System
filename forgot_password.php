<?php
// forgot_password.php - Complete OTP Flow
require 'db.php';

$error = '';
$success = '';
$step = $_POST['step'] ?? 'email'; // email, otp, password

// Step 1: Send OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'email') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($user = $res->fetch_assoc()) {
            // Generate 6-digit OTP
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Store OTP in database
            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE token=?, expires_at=?");
            $stmt->bind_param('issss', $user['id'], $otp, $expires, $otp, $expires);
            $stmt->execute();
            
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_otp'] = $otp; // For display
            $_SESSION['reset_user_id'] = $user['id'];
            
            $step = 'otp';
        } else {
            $error = "Email not found in our system.";
        }
    }
}

// Step 2: Verify OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'verify_otp') {
    $entered_otp = trim($_POST['otp'] ?? '');
    $entered_otp = preg_replace('/\s+/', '', $entered_otp); // Remove all spaces
    
    if (empty($entered_otp)) {
        $error = "Please enter the OTP code.";
        $step = 'otp';
    } elseif (!isset($_SESSION['reset_user_id'])) {
        $error = "Session expired. Please start over.";
        $step = 'email';
    } else {
        // Get the stored OTP from database
        $stmt = $conn->prepare("SELECT token, expires_at FROM password_resets 
                                WHERE user_id=? LIMIT 1");
        $stmt->bind_param('i', $_SESSION['reset_user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($reset_data = $res->fetch_assoc()) {
            // Check if OTP matches and not expired
            if ($reset_data['token'] === $entered_otp && strtotime($reset_data['expires_at']) > time()) {
                $_SESSION['otp_verified'] = true;
                $step = 'password';
            } else if (strtotime($reset_data['expires_at']) <= time()) {
                $error = "OTP has expired. Please request a new one.";
                $step = 'otp';
            } else {
                $error = "Invalid OTP. Please try again. (Entered: $entered_otp)";
                $step = 'otp';
            }
        } else {
            $error = "No OTP found. Please start over.";
            $step = 'email';
        }
    }
}

// Step 3: Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset_password') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!isset($_SESSION['otp_verified']) || !isset($_SESSION['reset_user_id'])) {
        $error = "Session expired. Please start over.";
        $step = 'email';
    } elseif (empty($password)) {
        $error = "Please enter a new password.";
        $step = 'password';
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
        $step = 'password';
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
        $step = 'password';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param('si', $hashed, $_SESSION['reset_user_id']);
        
        if ($stmt->execute()) {
            // Delete OTP
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id=?");
            $stmt->bind_param('i', $_SESSION['reset_user_id']);
            $stmt->execute();
            
            // Clear session
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_otp']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['otp_verified']);
            
            header("Location: login.php?reset=1");
            exit;
        } else {
            $error = "Failed to reset password. Please try again.";
            $step = 'password';
        }
    }
}

// Check for existing session
if (isset($_SESSION['reset_email']) && !isset($_POST['step'])) {
    if (isset($_SESSION['otp_verified'])) {
        $step = 'password';
    } else {
        $step = 'otp';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #172c45ff 0%, #401c62ff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 420px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 10px;
        }
        
        .step-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #999;
            position: relative;
        }
        
        .step-dot.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .step-dot.completed {
            background: #28a745;
            color: white;
        }
        
        .step-dot:not(:last-child)::after {
            content: '';
            position: absolute;
            width: 30px;
            height: 2px;
            background: #e0e0e0;
            right: -35px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .step-title {
            text-align: center;
            color: #667eea;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .description {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .otp-display {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .otp-display p {
            color: #856404;
            font-size: 13px;
            margin-bottom: 10px;
        }
        
        .otp-code {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
        
        .email-display {
            text-align: center;
            color: #667eea;
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 15px;
        }
        
        form {
            display: flex;
            flex-direction: column;
        }
        
        input {
            padding: 14px;
            margin-bottom: 18px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
        }
        
        input[name="otp"] {
            text-align: center;
            letter-spacing: 8px;
            font-size: 24px;
            font-family: 'Courier New', monospace;
        }
        
        input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        input::placeholder {
            color: #999;
        }
        
        button {
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .password-requirements {
            background: #f0f4ff;
            border: 1px solid #d0d9f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #555;
        }
        
        .password-requirements h4 {
            margin-bottom: 10px;
            color: #333;
            font-size: 14px;
        }
        
        .password-requirements ul {
            margin: 0 0 0 20px;
        }
        
        .password-requirements li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-dot <?php echo $step === 'email' ? 'active' : 'completed'; ?>">1</div>
            <div class="step-dot <?php echo $step === 'otp' ? 'active' : ($step === 'password' ? 'completed' : ''); ?>">2</div>
            <div class="step-dot <?php echo $step === 'password' ? 'active' : ''; ?>">3</div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo esc($error); ?></div>
        <?php endif; ?>

        <!-- STEP 1: Enter Email -->
        <?php if ($step === 'email'): ?>
            <h2>Forgot Password</h2>
            <p class="step-title">Step 1: Enter Your Email</p>
            <p class="description">Enter your registered email address to receive an OTP code.</p>
            
            <form method="post">
                <input type="hidden" name="step" value="email">
                <input name="email" type="email" required placeholder="Enter your email address" autofocus>
                <button type="submit">Send OTP Code</button>
            </form>

        <!-- STEP 2: Enter OTP -->
        <?php elseif ($step === 'otp'): ?>
            <h2>Verify OTP</h2>
            <p class="step-title">Step 2: Enter OTP Code</p>
            <p class="description">We've generated an OTP code for:</p>
            <p class="email-display"><?php echo esc($_SESSION['reset_email']); ?></p>
            
            <div class="otp-display">
                <p><strong>📧 Your OTP Code:</strong></p>
                <div class="otp-code"><?php echo $_SESSION['reset_otp']; ?></div>
                <p style="margin-top:10px; font-size:12px;">⏱️ Expires in 15 minutes</p>
            </div>
            
            <form method="post">
                <input type="hidden" name="step" value="verify_otp">
                <input name="otp" type="text" required placeholder="000000" 
                       maxlength="6" pattern="[0-9]{6}" autofocus autocomplete="off">
                <button type="submit">Verify OTP</button>
            </form>

        <!-- STEP 3: New Password -->
        <?php elseif ($step === 'password'): ?>
            <h2>Create New Password</h2>
            <p class="step-title">Step 3: Set New Password</p>
            
            <div class="password-requirements">
                <h4>🔒 Password Requirements:</h4>
                <ul>
                    <li>Minimum 6 characters</li>
                    <li>Both passwords must match</li>
                </ul>
            </div>
            
            <form method="post">
                <input type="hidden" name="step" value="reset_password">
                <input name="password" type="password" required placeholder="New Password" minlength="6" autofocus>
                <input name="confirm_password" type="password" required placeholder="Confirm Password" minlength="6">
                <button type="submit">Reset Password</button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>