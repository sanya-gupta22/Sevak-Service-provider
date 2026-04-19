<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Provider App - Reset Password</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .password-rules {
            font-size: 0.8em;
            color: #666;
            margin-bottom: 15px;
        }
        .password-match {
            color: green;
            font-size: 0.8em;
            display: none;
        }
        .password-mismatch {
            color: red;
            font-size: 0.8em;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Reset Your Password</h2>
            <form onsubmit="return validatePasswordReset()">
                <input type="hidden" id="reset-email" name="email">
                
                <div class="form-group">
                    <label for="new-password">New Password:</label>
                    <input type="password" id="new-password" required oninput="checkPasswordStrength()">
                    <div class="password-rules">
                        Password must contain at least:
                        <ul>
                            <li id="rule-length">8 characters</li>
                            <li id="rule-uppercase">1 uppercase letter</li>
                            <li id="rule-number">1 number</li>
                            <li id="rule-special">1 special character</li>
                        </ul>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm-password">Confirm New Password:</label>
                    <input type="password" id="confirm-password" required oninput="checkPasswordMatch()">
                    <span id="password-match" class="password-match">Passwords match!</span>
                    <span id="password-mismatch" class="password-mismatch">Passwords do not match!</span>
                </div>
                
                <button type="submit" id="reset-btn">Reset Password</button>
                
                <p>Remembered your password? <a href="login.php">Login here</a></p>
            </form>
        </div>
    </div>

    <script>
        // Get the email from the URL parameters
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const email = urlParams.get('email');
            if (email) {
                document.getElementById('reset-email').value = email;
            } else {
                // If no email provided, redirect back to forgot password
                window.location.href = 'forgot-password.html';
            }
        });

        function checkPasswordStrength() {
            const password = document.getElementById('new-password').value;
            
            // Check rules
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            
            // Update rule indicators
            document.getElementById('rule-length').style.color = hasLength ? 'green' : 'red';
            document.getElementById('rule-uppercase').style.color = hasUppercase ? 'green' : 'red';
            document.getElementById('rule-number').style.color = hasNumber ? 'green' : 'red';
            document.getElementById('rule-special').style.color = hasSpecial ? 'green' : 'red';
            
            return hasLength && hasUppercase && hasNumber && hasSpecial;
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (confirmPassword.length === 0) {
                document.getElementById('password-match').style.display = 'none';
                document.getElementById('password-mismatch').style.display = 'none';
                return false;
            }
            
            if (password === confirmPassword) {
                document.getElementById('password-match').style.display = 'inline';
                document.getElementById('password-mismatch').style.display = 'none';
                return true;
            } else {
                document.getElementById('password-match').style.display = 'none';
                document.getElementById('password-mismatch').style.display = 'inline';
                return false;
            }
        }
        
        function validatePasswordReset() {
            const isStrong = checkPasswordStrength();
            const isMatching = checkPasswordMatch();
            
            if (!isStrong) {
                alert('Please ensure your password meets all the requirements.');
                return false;
            }
            
            if (!isMatching) {
                alert('Passwords do not match!');
                return false;
            }
            
            // In a real application, you would send the new password to your server here
            const email = document.getElementById('reset-email').value;
            const newPassword = document.getElementById('new-password').value;
            
            console.log(`Password would be reset for ${email} to ${newPassword}`);
            
            // Simulate successful password reset
            alert('Your password has been reset successfully! Redirecting to login...');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
            
            return false; // Prevent actual form submission for this demo
        }
    </script>
</body>
</html>