<?php
session_start();
require_once __DIR__ . "/includes/config.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["login-email"]);
    $password = trim($_POST["login-password"]);

    // Check if table exists first (for debugging)
    $table_check = $conn->query("SHOW TABLES LIKE 'user_db'");
    if ($table_check->num_rows == 0) {
        die("Error: The 'user_db' table doesn't exist in the database.");
    }

    // Prepare SQL to prevent SQL injection
    $sql = "SELECT id, email, password, user_type FROM user_db WHERE email = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password using password_verify() to match register.php's password_hash()
        if (password_verify($password, $user['password'])) {
            $_SESSION["loggedin"] = true;
            $_SESSION["id"] = $user['id'];
            $_SESSION["email"] = $user['email'];
            $_SESSION["user_type"] = $user['user_type'];
            
            // Set professional_id in session if user is professional
            if ($user['user_type'] == 'professional') {
                $_SESSION["professional_id"] = $user['id'];
            }
            
            // Set client_id in session if user is client
            if ($user['user_type'] == 'client') {
                $_SESSION["client_id"] = $user['id'];
            } 

            // Set admin in session if user is admin
            if ($user['user_type'] == 'admin') {
                $_SESSION["id"] = $user['id'];
            } 

            // Redirect based on user type
            if ($user['user_type'] == 'professional') {
                header("Location: ../Users/professional/professional-dashboard.php");
            } 
            elseif($user['user_type'] == 'admin'){
                header("Location: ../admin/admin.php");
            }
            else{
                header("Location: ../Users/clients/clients-dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid email or password!";
        }
    } else {
        $error = "Invalid email or password!";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Provider App - Login</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<style>
  b body {
    min-height: 100vh;
    width: 100%;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
}
.container {
  min-height: 100vh;
  width: 100%;
  background-image: linear-gradient(135deg, #ffffff 0%, #764ba2 100%);
  background-size: cover;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}
/**Right-side*/
.login-container {
  display: flex;
  width: 100%;
  max-width: 1200px;
  min-height: 600px;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
}
#login-form{
  display: flex;
}
.login-form-container {
  flex: 1;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.login-form {
  width: 100%;
  max-width: 400px;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(20px);
  padding: 2.5rem;
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(31, 38, 135, 0.2); 
  border: 1px solid rgba(255, 255, 255, 0.18); 
}

.form-header {
  text-align: center;
  margin-bottom: 2rem;
}

.form-header h2 {
  color: white;
  font-size: 1.8rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.form-header p {
  color: rgba(255, 255, 255, 0.8);
  font-size: 0.9rem;
}
.form-container{
        flex: 1;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
}
.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: white;
  font-size: 0.9rem;
  font-weight: 500;
}
/* Left Side - Information */
.login-info {
  flex: 1;
  background: rgba(141, 89, 181, 0.9);
  color: white;
  padding: 4rem 3rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.login-info h1 {
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: 3rem;
}

.brand-name {
  color:  #F6F6F7;
}

.info-cards {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  animation: fadeIn 1s ease-out forwards;
}

.info-card {
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(5px);
  padding: 1.5rem;
  border-radius: 12px;
  transition: transform 0.3s ease;
}

.info-card:hover {
  transform: translateY(-5px);
}

.info-card h3 {
  font-size: 1.2rem;
  margin-bottom: 0.5rem;
  font-weight: 600;
}

.info-card p {
  opacity: 0.9;
  line-height: 1.5;}
/**--*/
h2 {
    text-align: center;
}
form {
    display: flex;
    flex-direction: column;
}
label {
    margin-top: 10px;
}
input, select {
    padding: 8px;
    margin-top: 5px;
    border: 1px solid #ccc;
    border-radius: 3px;
}
button {
    margin-top: 20px;
    padding: 10px;
    background-color: #333;
    color: #fff;
    border: none;
    border-radius: 3px;
    cursor: pointer;
}
button:hover {
    background-color: #5c5757;
}
p {
    text-align: center;
    margin-top: 15px;
}
a {
    color: #333;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
  </style>
<body>
    <div class="container">
      <div class="login-conatiner" id="login-form">
        <!-- <div class="form-container" id="login-form"> -->
            <div class="login-info">
                <h1>Welcome to <span class="brand-name">Sevak</span></h1>
                <div class="info-cards">
                  <div class="info-card">
                      <h3>Your Trusted Service Partner</h3>
                      <p>Connect with verified service providers for all your needs with our secure platform.</p>
                  </div>
                  
                  <div class="info-card">
                      <h3>Seamless Experience</h3>
                      <p>Book, manage, and review services with our easy-to-use dashboard and real-time updates.</p>
                  </div>
                  
                  <div class="info-card">
                      <h3>Verified Professionals</h3>
                      <p>All service providers are thoroughly vetted to ensure quality and reliability.</p>
                  </div>
                </div>
            </div>
            <!-- Right side - Login Form -->
            <div class="login-form-container">
                <div class="login-form">
                <!--changement-->
                    <div class="form-header">
                        <h2>Login to Your Account</h2>
                        <p>Enter your credentials to access your account</p>
                    </div>
                    <div class="form-group">
                    <?php if (!empty($error)): ?>
                        <div class="error"><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="login.php">
                        <label for="login-email">Email:</label>
                        <input type="email" id="login-email" name="login-email" required>
                        <label for="login-password">Password:</label>
                        <input type="password" id="login-password" name="login-password" required>
                        <button type="submit">Login</button>
                        <p>Don't have an account? <a href="register.php">Register here</a></p>
                        <p><a href="../Users/forgotpassword.php">Forgot Password?</a></p>
                    </form>
                    </div>
                </div>
            </div>
        <!-- </div> -->
        </div>
    </div>
</body>
</html>