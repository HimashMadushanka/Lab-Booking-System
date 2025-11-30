<?php
session_start();
require_once 'db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    
    if ($email === '' || $username === '') {
        $error = "Please fill in all fields.";
    } else {
        // Verify user exists
        $stmt = $mysqli->prepare("SELECT id, name, email FROM users WHERE email = ? AND name = ? LIMIT 1");
        $stmt->bind_param('ss', $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            // Generate simple temporary password
            $temp_password = "lab123"; // Simple fixed temporary password
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // Update user password
            $update_stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param('si', $hashed_password, $user['id']);
            
            if ($update_stmt->execute()) {
                $message = "
                <div style='text-align: center;'>
                    <h3 style='color: green;'>Password Reset Successful!</h3>
                    <div style='background: #fff3cd; border: 2px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                        <strong style='font-size: 18px;'>Your new password is: <span style='color: #d63031;'>lab123</span></strong>
                    </div>
                    <p>Please login with this password and change it immediately.</p>
                    <a href='login.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px;'>Go to Login</a>
                </div>
                ";
            } else {
                $error = "Error resetting password. Please try again.";
            }
            $update_stmt->close();
        } else {
            $error = "No account found with that email and username combination.";
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Forgot Password — Lab Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 50px; }
        .container { max-width: 450px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        input[type="email"], input[type="text"] { 
            width: 100%; 
            padding: 12px; 
            border: 2px solid #ddd; 
            border-radius: 5px; 
            font-size: 16px; 
            transition: border-color 0.3s;
        }
        input[type="email"]:focus, input[type="text"]:focus {
            border-color: #007bff;
            outline: none;
        }
        button { 
            width: 100%; 
            padding: 12px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            font-size: 16px; 
            cursor: pointer; 
            font-weight: bold;
        }
        button:hover { background: #0056b3; }
        .message { text-align: center; margin-bottom: 15px; }
        .error { 
            color: red; 
            text-align: center; 
            margin-bottom: 15px; 
            padding: 12px; 
            background: #f8d7da; 
            border: 1px solid #f5c6cb; 
            border-radius: 5px; 
            font-weight: bold;
        }
        .links { text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
        .links a { color: #007bff; text-decoration: none; margin: 0 15px; font-weight: bold; }
        .links a:hover { text-decoration: underline; }
        .info-box { 
            background: #e7f3ff; 
            border: 2px solid #b3d9ff; 
            border-radius: 8px; 
            padding: 20px; 
            margin-bottom: 25px; 
            text-align: center;
        }
        .temp-password { 
            background: #fff3cd; 
            border: 2px solid #ffeaa7; 
            padding: 20px; 
            border-radius: 8px; 
            margin: 20px 0; 
            text-align: center; 
            font-size: 20px; 
            font-weight: bold;
            color: #d63031;
        }
        .instructions {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            text-align: left;
        }
        .instructions h4 {
            margin-bottom: 10px;
            color: #155724;
        }
        .instructions ul {
            margin-left: 20px;
        }
        .instructions li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔐 Forgot Password</h2>
        
        <div class="info-box">
            <strong>Reset Your Password Instantly</strong><br>
            <span style="font-size: 14px; color: #666;">Enter your email and username to get a new password</span>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?= $message ?></div>
            
            <div class="instructions">
                <h4>📋 What to do next:</h4>
                <ul>
                    <li>Use the temporary password: <strong>lab123</strong></li>
                    <li>Login to your account immediately</li>
                    <li>Go to your profile settings</li>
                    <li>Change your password to something secure</li>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$message): ?>
            <form method="post" action="">
                <div class="form-group">
                    <label>📧 Email Address:</label>
                    <input type="email" name="email" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" placeholder="Enter your registered email">
                </div>
                
                <div class="form-group">
                    <label>👤 Username:</label>
                    <input type="text" name="username" required value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" placeholder="Enter your username">
                </div>
                
                <button type="submit">🔄 Reset My Password</button>
            </form>
        <?php endif; ?>

        <div class="links">
            <a href="login.php">← Back to Login</a>
            <a href="register.php">Create New Account →</a>
        </div>
        
        <?php if (!$message): ?>
        <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #888;">
            <strong>Note:</strong> Your password will be reset to <strong>lab123</strong>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>