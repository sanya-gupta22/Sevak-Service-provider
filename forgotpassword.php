<?php
session_start();
require_once __DIR__ . "/../vendor/autoload.php"; // Load PHPMailer
require_once "includes/config.php";


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";
$showOtpSection = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["forgot-email"])) {
    $email = trim($_POST["forgot-email"]);

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM user_db WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($user_id);
            $stmt->fetch();

            // Generate a 6-digit OTP
            $otp = rand(100000, 999999);
            $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes")); // OTP valid for 10 minutes

            // Store OTP in the database
            $updateStmt = $conn->prepare("UPDATE user_db SET otp_code = ?, otp_expiry = ? WHERE id = ?");
            $updateStmt->bind_param("ssi", $otp, $expiry, $user_id);
            $updateStmt->execute();
            $updateStmt->close();

            // Send OTP via Email
            if (sendEmailOTP($email, $otp)) {
                $message = "OTP sent to your email.";
                $showOtpSection = true;
                $_SESSION['otp_email'] = $email; // Store email in session for verification
            } else {
                $message = "Failed to send OTP. Please try again.";
            }
        } else {
            $message = "Email not found in our system.";
        }
        $stmt->close();
    } else {
        $message = "Database error: " . $conn->error;
    }
}

// Function to send OTP via Email
function sendEmailOTP($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Change for other email providers
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com'; // Replace with your email
        $mail->Password = 'your-email-password'; // Replace with your password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Email Content
        $mail->setFrom('your-email@gmail.com', 'Sevak');
        $mail->addAddress($email);
        $mail->Subject = "Your OTP for Password Reset";
        $mail->Body = "Your OTP for password reset is: $otp. This OTP is valid for 10 minutes.";

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Provider App - Forgot Password</title>
    <link rel="stylesheet" href="..\CSS\style.css">
    <style>
        .otp-section { display: <?php echo $showOtpSection ? 'block' : 'none'; ?>; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container" id="forgot-form">
            <h2>Forgot Password</h2>
            <?php if (!empty($message)): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="POST" action="forgotpassword.php">
                <div id="email-section" style="display: <?php echo $showOtpSection ? 'none' : 'block'; ?>;">
                    <label for="forgot-email">Enter your registered email:</label>
                    <input type="email" id="forgot-email" name="forgot-email" required>
                    <button type="submit" id="submit-btn">Send OTP</button>
                </div>
            </form>

            <!-- OTP Verification Section -->
            <div id="otp-section" class="otp-section">
                <form method="POST" action="verify-otp.php">
                    <label for="otp-code">Enter OTP sent to your email:</label>
                    <input type="text" id="otp-code" name="otp-code" maxlength="6" required placeholder="6-digit code">
                    <button type="submit">Verify OTP</button>
                </form>
                <p id="otp-message" class="message"></p>
                <form method="POST" action="resend-otp.php">
                    <input type="hidden" name="email" value="<?php echo $_SESSION['otp_email'] ?? ''; ?>">
                    <button type="submit" id="resend-otp">Resend OTP</button>
                </form>
            </div>

            <p>Remembered your password? <a href="../Users/login.php">Login here</a></p>
        </div>
    </div>
</body>
</html>
