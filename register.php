<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if email already exists
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->bind_param('sss', $name, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Lab Management</title>
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
        
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 420px;
        }
        
        .register-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }
        
        form {
            display: flex;
            flex-direction: column;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
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
            margin-top: 10px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .login-link p {
            margin: 8px 0;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }

        .password-strength.weak {
            color: #dc3545;
        }

        .password-strength.medium {
            color: #ffc107;
        }

        .password-strength.strong {
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Create Account</h2>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" action="" id="registerForm">
            <div class="form-group">
                <label>Full Name:</label>
                <input type="text" name="name" required value="<?= isset($name) ? htmlspecialchars($name) : '' ?>" placeholder="Enter your full name">
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" placeholder="Enter your email">
            </div>
            
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" id="password" required placeholder="Minimum 6 characters">
                <div class="password-strength" id="passwordStrength"></div>
            </div>
            
            <div class="form-group">
                <label>Confirm Password:</label>
                <input type="password" name="confirm_password" id="confirmPassword" required placeholder="Re-enter your password">
            </div>
            
            <button type="submit">Register</button>
        </form>

        <div class="login-link">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>

    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthDiv = document.getElementById('passwordStrength');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = '';
            let className = '';

            if (password.length === 0) {
                strengthDiv.textContent = '';
                strengthDiv.className = 'password-strength';
                return;
            }

            if (password.length < 6) {
                strength = 'Too short (minimum 6 characters)';
                className = 'weak';
            } else if (password.length < 8) {
                strength = 'Weak password';
                className = 'weak';
            } else if (password.length < 10 && /[A-Z]/.test(password) && /[0-9]/.test(password)) {
                strength = 'Medium strength';
                className = 'medium';
            } else if (password.length >= 10 && /[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) {
                strength = 'Strong password';
                className = 'strong';
            } else {
                strength = 'Medium strength';
                className = 'medium';
            }

            strengthDiv.textContent = strength;
            strengthDiv.className = 'password-strength ' + className;
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }

            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>