<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Please fill in both fields.";
    } else {
        // Prepare query
        $stmt = $mysqli->prepare("SELECT id, name, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();

        // Fetch result using fetch_assoc to avoid null warnings
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            // $user['password'] is guaranteed to be string from DB
            if (password_verify($password, $user['password'])) {
                // Login success
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }

        $stmt->close();
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login — Lab Management</title>
</head>
<body>
<h2>Login</h2>

<?php if ($error): ?>
    <div style="color: red;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="">
    Email:<br>
    <input type="email" name="email" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>"><br><br>
    Password:<br>
    <input type="password" name="password" required><br><br>
    <button type="submit">Login</button>
</form>

<p>No account? <a href="register.php">Register here</a></p>
</body>
</html>
