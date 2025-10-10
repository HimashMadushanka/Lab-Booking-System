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
<form method="post">
  <input name="email" type="email" required placeholder="Email"><br>
  <input name="password" type="password" required placeholder="Password"><br>
  <button type="submit">Login</button>
  <?php if (!empty($error)) echo "<p style='color:red'>".esc($error)."</p>"; ?>
</form>
