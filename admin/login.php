<?php
session_start();
require '../db.php';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = md5($_POST['password']);

    $sql = "SELECT * FROM admin WHERE username='$username' AND password='$password'";
    $res = $conn->query($sql);

    if ($res->num_rows > 0) {
        $_SESSION['admin'] = $username;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Login | Lab Management</title>
  <style>
    body { font-family: Arial; background: #f5f6fa; }
    .login-box {
      width: 300px; margin: 100px auto; background: white;
      padding: 20px; border-radius: 10px; box-shadow: 0 0 10px #ccc;
    }
    input, button { width: 100%; padding: 10px; margin: 8px 0; }
    button { background: #2980b9; color: white; border: none; border-radius: 5px; }
  </style>
</head>
<body>
<div class="login-box">
  <h2>Admin Login</h2>
  <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
  <form method="POST">
    <input type="text" name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password" required>
    <button name="login">Login</button>
  </form>
</div>
</body>
</html>
