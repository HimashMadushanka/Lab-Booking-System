<?php
session_start();
require '../db.php';

if (isset($_POST['login'])) {
    $username = $mysqli->real_escape_string($_POST['username']);
    $password = md5($_POST['password']);

    $sql = "SELECT * FROM admin WHERE username='$username' AND password='$password'";
    $res = $mysqli->query($sql);

    if ($res->num_rows > 0) {
        $_SESSION['admin'] = $username;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}

// Handle forgot password
if (isset($_POST['reset_password'])) {
    $username = $mysqli->real_escape_string($_POST['reset_username']);
    
    // Check if admin exists
    $check_sql = "SELECT * FROM admin WHERE username='$username'";
    $check_res = $mysqli->query($check_sql);
    
    if ($check_res->num_rows > 0) {
        // Generate a simple reset code (6 digits)
        $reset_code = sprintf("%06d", mt_rand(1, 999999));
        $_SESSION['admin_reset_code'] = $reset_code;
        $_SESSION['admin_reset_username'] = $username;
        $_SESSION['admin_reset_expires'] = time() + 600; // 10 minutes
        
        $success = "Reset code generated: <strong>$reset_code</strong><br><small>Use this code to reset your password.</small>";
    } else {
        $error = "Admin username not found!";
    }
}

// Handle password reset with code
if (isset($_POST['confirm_reset'])) {
    $code = $mysqli->real_escape_string($_POST['reset_code']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!isset($_SESSION['admin_reset_code'])) {
        $error = "Reset session expired! Please start again.";
    } elseif ($code != $_SESSION['admin_reset_code']) {
        $error = "Invalid reset code!";
    } elseif (time() > $_SESSION['admin_reset_expires']) {
        $error = "Reset code has expired!";
    } elseif ($new_password != $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($new_password) < 4) {
        $error = "Password must be at least 4 characters!";
    } else {
        // Update password
        $hashed_password = md5($new_password);
        $username = $_SESSION['admin_reset_username'];
        $update_sql = "UPDATE admin SET password='$hashed_password' WHERE username='$username'";
        
        if ($mysqli->query($update_sql)) {
            // Clear reset session
            unset($_SESSION['admin_reset_code']);
            unset($_SESSION['admin_reset_username']);
            unset($_SESSION['admin_reset_expires']);
            
            $success = "Password reset successfully! You can now login with your new password.";
        } else {
            $error = "Error resetting password: " . $mysqli->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Lab Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #172c45ff 0%,  #401c62ff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 420px;
        }
        
        .login-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
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
        
        p {
            margin-top: 15px;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            font-size: 14px;
        }
        
        p[style*="color:red"] {
            background: #fee;
            color: #c33 !important;
            border: 1px solid #fcc;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal h3 {
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }

        .modal p {
            margin-bottom: 20px;
            color: #666;
            text-align: center;
            padding: 0;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .modal-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .modal-buttons .cancel-btn {
            background: #6c757d;
            color: white;
        }

        .modal-buttons .cancel-btn:hover {
            background: #5a6268;
        }

        .modal-buttons .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .modal-buttons .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .reset-code-display {
            background: #f8f9fa;
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            text-align: center;
        }

        .reset-code {
            font-size: 32px;
            font-weight: bold;
            color: #2d3748;
            letter-spacing: 8px;
            margin: 10px 0;
        }

        .code-note {
            color: #718096;
            font-size: 12px;
            display: block;
            margin-top: 10px;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 10px;
        }

        .step.active {
            background: #667eea;
            color: white;
        }

        .step-line {
            width: 40px;
            height: 2px;
            background: #e2e8f0;
            margin: 0 5px;
            align-self: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>
        
        <?php if(isset($success)): ?>
            <div class="success-message"><?= $success ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button name="login">Login</button>
            <?php if(isset($error) && !isset($_POST['reset_password']) && !isset($_POST['confirm_reset'])) echo "<p style='color:red;'>$error</p>"; ?>
        </form>
        
        <div class="register-link">
            <a onclick="openForgotPassword()">Forgot Password?</a> | 
            <a href="../login.php">User Login</a>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <h3>Reset Admin Password</h3>
            
            <div class="step-indicator">
                <div class="step <?= !isset($_SESSION['admin_reset_code']) ? 'active' : '' ?>">1</div>
                <div class="step-line"></div>
                <div class="step <?= isset($_SESSION['admin_reset_code']) ? 'active' : '' ?>">2</div>
            </div>

            <div id="forgotPasswordMessage"></div>
            
            <!-- Step 1: Request Reset -->
            <form id="resetRequestForm" method="POST" style="<?= isset($_SESSION['admin_reset_code']) ? 'display:none;' : '' ?>">
                <p>Enter your admin username to generate a reset code.</p>
                <input type="text" name="reset_username" placeholder="Admin Username" required>
                <div class="modal-buttons">
                    <button type="button" class="cancel-btn" onclick="closeForgotPassword()">Cancel</button>
                    <button type="submit" name="reset_password" class="submit-btn">Generate Reset Code</button>
                </div>
            </form>

            <!-- Step 2: Enter Code and New Password -->
            <form id="resetConfirmForm" method="POST" style="<?= isset($_SESSION['admin_reset_code']) ? '' : 'display:none;' ?>">
                <p>Enter the reset code and your new password.</p>
                
                <?php if(isset($_SESSION['admin_reset_code'])): ?>
                    <div class="reset-code-display">
                        <div>🔐 Your Reset Code</div>
                        <div class="reset-code"><?= $_SESSION['admin_reset_code'] ?></div>
                        <span class="code-note">⏰ Valid for 10 minutes</span>
                    </div>
                <?php endif; ?>
                
                <input type="text" name="reset_code" placeholder="Enter Reset Code" required maxlength="6">
                <input type="password" name="new_password" placeholder="New Password" required minlength="4">
                <input type="password" name="confirm_password" placeholder="Confirm New Password" required minlength="4">
                
                <?php if(isset($error) && (isset($_POST['reset_password']) || isset($_POST['confirm_reset']))): ?>
                    <p style='color:red; margin: 10px 0;'><?= $error ?></p>
                <?php endif; ?>
                
                <div class="modal-buttons">
                    <button type="button" class="cancel-btn" onclick="closeForgotPassword()">Cancel</button>
                    <button type="submit" name="confirm_reset" class="submit-btn">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openForgotPassword() {
            document.getElementById('forgotPasswordModal').style.display = 'flex';
        }

        function closeForgotPassword() {
            document.getElementById('forgotPasswordModal').style.display = 'none';
            // Clear any form data
            document.getElementById('resetRequestForm').reset();
            document.getElementById('resetConfirmForm').reset();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('forgotPasswordModal');
            if (event.target === modal) {
                closeForgotPassword();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeForgotPassword();
            }
        });

        // Auto-format reset code input
        const resetCodeInput = document.querySelector('input[name="reset_code"]');
        if (resetCodeInput) {
            resetCodeInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }

        // Show appropriate form based on PHP state
        <?php if(isset($_SESSION['admin_reset_code'])): ?>
            document.getElementById('resetRequestForm').style.display = 'none';
            document.getElementById('resetConfirmForm').style.display = 'block';
            // Auto-open modal if we're in reset process
            setTimeout(() => {
                openForgotPassword();
            }, 100);
        <?php endif; ?>

        // Prevent form submission from closing modal
        document.getElementById('resetRequestForm').addEventListener('submit', function(e) {
            // Let the form submit normally, don't close modal
        });

        document.getElementById('resetConfirmForm').addEventListener('submit', function(e) {
            // Let the form submit normally, don't close modal
        });
    </script>
</body>
</html>