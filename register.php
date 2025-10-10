<?php
// register.php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = 'user'; // only admins set manually in DB or admin panel

    if (!$name || !$email || !$password) {
        $error = "Fill all fields.";
    } else {
        // check existing
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (name,email,password,role) VALUES (?, ?, ?, ?)");
            $ins->bind_param('ssss', $name, $email, $hash, $role);
            if ($ins->execute()) {
                header("Location: login.php?registered=1");
                exit;
            } else {
                $error = "Registration failed.";
            }
        }
        $stmt->close();
    }
}
?>
<!-- Simple HTML form -->
<form method="post">
  <input name="name" placeholder="Full name" required><br>
  <input name="email" placeholder="Email" type="email" required><br>
  <input name="password" placeholder="Password" type="password" required><br>
  <button type="submit">Register</button>
  <?php if (!empty($error)) echo "<p style='color:red'>".esc($error)."</p>"; ?>
</form>
