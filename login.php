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
        
        .links-container {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .links-container a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .links-container a:hover {
            text-decoration: underline;
            color: #5a6fd8;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 20px;
        }
        
        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .forgot-password a:hover {
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

        .forgot-password-modal {
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

        .forgot-password-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .forgot-password-content h3 {
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }

        .forgot-password-content p {
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

        .forgot-password-link {
            cursor: pointer;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .forgot-password-link:hover {
            text-decoration: underline;
        }

        .otp-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1001;
        }

        .otp-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .otp-input {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .resend-otp {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }

        .resend-otp a {
            color: #667eea;
            cursor: pointer;
        }

        .loading {
            display: none;
            text-align: center;
            margin: 10px 0;
        }

        .success-message-modal {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .error-message-modal {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .otp-display {
            background: #f8f9fa;
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            text-align: center;
        }

        .otp-display h3 {
            color: #667eea;
            margin: 0 0 10px 0;
            font-size: 18px;
        }

        .otp-code {
            font-size: 32px;
            font-weight: bold;
            color: #2d3748;
            letter-spacing: 8px;
            margin: 10px 0;
        }

        .otp-note {
            color: #718096;
            display: block;
            margin-top: 10px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php if (isset($_GET['registered'])): ?>
            <div class="success-message">Registration successful! Please login.</div>
        <?php endif; ?>
<<<<<<< HEAD
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
=======
        <?php if (isset($_GET['reset'])): ?>
            <div class="success-message">Password reset successful! Please login with your new password.</div>
>>>>>>> dd1ddc649ab1ee685d9b277be09b9fce921ebdb7
        <?php endif; ?>
        <form method="post">
            <input name="email" type="email" required placeholder="Email">
            <input name="password" type="password" required placeholder="Password">
            <div class="forgot-password">
                <a href="forgot_password.php">Forgot Password?</a>
            </div>
            <button type="submit">Login</button>
            <?php if (!empty($error)) echo "<p style='color:red'>".htmlspecialchars($error)."</p>"; ?>
        </form>
        <div class="links-container">
            <div>
                Don't have an account? <a href="register.php">Register here</a>
            </div>
            <div>
                <a href="#" class="forgot-password-link" onclick="openForgotPassword()">Forgot Password?</a>
            </div>
            <div>
                Login for Admin - <a href="admin/login.php">Login here</a>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="forgot-password-modal">
        <div class="forgot-password-content">
            <h3>Reset Your Password</h3>
            <p>Enter your email address and we'll generate an OTP for you.</p>
            <div id="forgotPasswordMessage"></div>
            <form id="forgotPasswordForm" onsubmit="submitForgotPassword(event)">
                <input type="email" id="resetEmail" placeholder="Enter your email" required>
                <div class="loading" id="forgotPasswordLoading">Generating OTP...</div>
                <div class="modal-buttons">
                    <button type="button" class="cancel-btn" onclick="closeForgotPassword()">Cancel</button>
                    <button type="submit" class="submit-btn">Generate OTP</button>
                </div>
            </form>
        </div>
    </div>

    <!-- OTP Verification Modal -->
    <div id="otpModal" class="otp-modal">
        <div class="otp-content">
            <h3>Enter OTP</h3>
            <p>Enter the 6-digit OTP that was displayed:</p>
            <div id="otpMessage"></div>
            <form id="otpForm" onsubmit="verifyOtp(event)">
                <input type="text" id="otpInput" class="otp-input" placeholder="000000" maxlength="6" required pattern="[0-9]{6}">
                <div class="loading" id="otpLoading">Verifying OTP...</div>
                <div class="modal-buttons">
                    <button type="button" class="cancel-btn" onclick="closeOtpModal()">Cancel</button>
                    <button type="submit" class="submit-btn">Verify OTP</button>
                </div>
            </form>
            <div class="resend-otp">
                Need a new OTP? <a onclick="resendOtp()">Generate Again</a>
            </div>
        </div>
    </div>

    <script>
        let currentEmail = '';

        function openForgotPassword() {
            document.getElementById('forgotPasswordModal').style.display = 'flex';
            document.getElementById('forgotPasswordMessage').innerHTML = '';
            document.getElementById('resetEmail').value = '';
        }

        function closeForgotPassword() {
            document.getElementById('forgotPasswordModal').style.display = 'none';
        }

        function openOtpModal() {
            document.getElementById('otpModal').style.display = 'flex';
            document.getElementById('otpMessage').innerHTML = '';
            document.getElementById('otpInput').value = '';
        }

        function closeOtpModal() {
            document.getElementById('otpModal').style.display = 'none';
            closeForgotPassword();
        }

        function submitForgotPassword(event) {
            event.preventDefault();
            const email = document.getElementById('resetEmail').value;
            const loading = document.getElementById('forgotPasswordLoading');
            const messageDiv = document.getElementById('forgotPasswordMessage');
            
            if (!email) {
                showMessage(messageDiv, 'Please enter your email address.', 'error');
                return;
            }

            currentEmail = email;
            loading.style.display = 'block';
            messageDiv.innerHTML = '';

            fetch('forgot_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                if (data.success) {
                    // Display OTP to user
                    const otpDisplay = `
                        <div class="otp-display">
                            <h3>🔐 Your OTP Code</h3>
                            <div class="otp-code">${data.otp}</div>
                            <span class="otp-note">⏰ Valid for 10 minutes | 📧 Email simulation</span>
                        </div>
                    `;
                    messageDiv.innerHTML = otpDisplay;
                    
                    setTimeout(() => {
                        closeForgotPassword();
                        openOtpModal();
                    }, 4000); // Give user time to see the OTP
                } else {
                    showMessage(messageDiv, data.message, 'error');
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                showMessage(messageDiv, 'An error occurred. Please try again.', 'error');
                console.error('Error:', error);
            });
        }

        function verifyOtp(event) {
            event.preventDefault();
            const otp = document.getElementById('otpInput').value;
            const loading = document.getElementById('otpLoading');
            const messageDiv = document.getElementById('otpMessage');
            
            if (!otp || otp.length !== 6) {
                showMessage(messageDiv, 'Please enter a valid 6-digit OTP.', 'error');
                return;
            }

            loading.style.display = 'block';
            messageDiv.innerHTML = '';

            fetch('verify_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'otp=' + encodeURIComponent(otp)
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                if (data.success) {
                    showMessage(messageDiv, data.message, 'success');
                    setTimeout(() => {
                        window.location.href = 'reset_password.php';
                    }, 1500);
                } else {
                    showMessage(messageDiv, data.message, 'error');
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                showMessage(messageDiv, 'An error occurred. Please try again.', 'error');
                console.error('Error:', error);
            });
        }

        function resendOtp() {
            if (!currentEmail) return;
            
            const messageDiv = document.getElementById('otpMessage');
            showMessage(messageDiv, 'Generating new OTP...', 'success');
            
            fetch('forgot_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(currentEmail)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(messageDiv, 'New OTP generated! Check the previous screen.', 'success');
                    setTimeout(() => {
                        closeOtpModal();
                        openForgotPassword();
                    }, 2000);
                } else {
                    showMessage(messageDiv, data.message, 'error');
                }
            })
            .catch(error => {
                showMessage(messageDiv, 'Failed to generate OTP. Please try again.', 'error');
            });
        }

        function showMessage(container, message, type) {
            const className = type === 'success' ? 'success-message-modal' : 'error-message-modal';
            container.innerHTML = `<div class="${className}">${message}</div>`;
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const forgotModal = document.getElementById('forgotPasswordModal');
            const otpModal = document.getElementById('otpModal');
            
            if (event.target === forgotModal) {
                closeForgotPassword();
            }
            if (event.target === otpModal) {
                closeOtpModal();
            }
        }

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeForgotPassword();
                closeOtpModal();
            }
        });

        // Auto-format OTP input
        document.getElementById('otpInput').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>