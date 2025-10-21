<?php
// login.php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            // redirect based on role
            if ($user['role'] === 'admin') header("Location: admin/dashboard.php");
            else header("Location: index.php");
            exit;
        } else $error = "Invalid credentials.";
    } else $error = "Invalid credentials.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
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
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php if (isset($_GET['registered'])): ?>
            <div class="success-message">Registration successful! Please login.</div>
        <?php endif; ?>
        <form method="post">
            <input name="email" type="email" required placeholder="Email">
            <input name="password" type="password" required placeholder="Password">
            <button type="submit">Login</button>
            <?php if (!empty($error)) echo "<p style='color:red'>".esc($error)."</p>"; ?>
        </form>
        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a><br />
            Login for Admin-<a href="admin/login.php">Login here</a>
        </div>
    </div>
</body>
</html>