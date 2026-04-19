<?php
session_start();
require_once "includes/config.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_SESSION['otp_email'];
    $otp = trim($_POST["otp-code"]);

    // Verify OTP
    $stmt = $conn_users->prepare("SELECT id FROM user_db WHERE email = ? AND otp_code = ? AND otp_expiry >= NOW()");
    if ($stmt) {
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $_SESSION['reset_email'] = $email;
            header("Location: reset-password.php");
            exit();
        } else {
            $message = "Invalid or expired OTP!";
        }
        $stmt->close();
    }
}

$conn_users->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <link rel="stylesheet" href="..\CSS\style.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Verify OTP</h2>
            <?php if (!empty($message)): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="POST" action="verify-otp.php">
                <label for="otp">Enter OTP:</label>
                <input type="text" id="otp" name="otp-code" required>
                <button type="submit">Verify OTP</button>
            </form>
        </div>
    </div>
</body>
</html>
