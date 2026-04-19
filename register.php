<?php
require_once "includes/config.php"; 

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["register-email"]);
    $name = trim($_POST["register-name"]);
    $contact = trim($_POST["register-contact"]);
    $user_type = trim($_POST["user-type"]);
    $specialization = ($user_type == "professional") ? trim($_POST["professional-specialization"]) : NULL;
    $password = trim($_POST["register-password"]);
    $confirm_password = trim($_POST["confirm-password"]);

    // Validate contact number (exactly 10 digits)
    if (!preg_match('/^[0-9]{10}$/', $contact)) {
        $error = "Contact number must be exactly 10 digits!";
    }
    // Validate password (8+ chars, letters, numbers, special chars)
    elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/', $password)) {
        $error = "Password must be at least 8 characters with letters, numbers, and special symbols!";
    }
    // Validate passwords match
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Check if email already exists
        $check_email_sql = "SELECT id FROM user_db WHERE email = ?";
        if ($stmt = $conn->prepare($check_email_sql)) {
            $stmt->bind_param("s", $email);
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $error = "Email already exists!";
                } else {
                    // Hash password securely
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Begin transaction
                    $conn->begin_transaction();

                    try {
                        // Insert user into user_db (sevak_db)
                        $insert_user_sql = "INSERT INTO user_db (email, name, contact, user_type, specialization, password) VALUES (?, ?, ?, ?, ?, ?)";
                        if ($insert_stmt = $conn->prepare($insert_user_sql)) {
                            $insert_stmt->bind_param("ssssss", $email, $name, $contact, $user_type, $specialization, $hashed_password);
                            $insert_stmt->execute();
                            
                            // Get the newly inserted user ID
                            $user_id = $insert_stmt->insert_id;
                            $insert_stmt->close();

                            if ($user_type == "professional") {
                                // Insert into professionals table in sevak_db
                                $insert_prof_sql = "INSERT INTO professionals (id, name, email, contact, profession, profile_image, total_bookings, total_earnings, rating, clients_served, password) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                if ($insert_prof_stmt = $conn->prepare($insert_prof_sql)) {
                                    $profile_image = ''; // Default value
                                    $total_bookings = 0;
                                    $total_earnings = 0.00;
                                    $rating = 0;
                                    $clients_served = 0;

                                    $insert_prof_stmt->bind_param("issssssddis", $user_id, $name, $email, $contact, $specialization, $profile_image, $total_bookings, $total_earnings, $rating, $clients_served, $hashed_password);
                                    $insert_prof_stmt->execute();
                                    $insert_prof_stmt->close();
                                } else {
                                    throw new Exception("Error inserting into professionals table: " . $conn->error);
                                }
                            } else {
                                // Insert into clients table in sevak_db
                                $insert_client_sql = "INSERT INTO clients (id, name, email, contact, password) VALUES (?, ?, ?, ?, ?)";
                                if ($insert_client_stmt = $conn->prepare($insert_client_sql)) {
                                    $insert_client_stmt->bind_param("issss", $user_id, $name, $email, $contact, $hashed_password);
                                    $insert_client_stmt->execute();
                                    $insert_client_stmt->close();
                                } else {
                                    throw new Exception("Error inserting into clients table: " . $conn->error);
                                }
                            }

                            // Commit transaction
                            $conn->commit();

                            $success = "Registration successful! You can now login.";
                            header("refresh:2; url=login.php");
                            exit();
                        } else {
                            throw new Exception("Error inserting into user table: " . $conn->error);
                        }
                    } catch (Exception $e) {
                        // Rollback on error
                        $conn->rollback();
                        $error = $e->getMessage();
                    }
                }
            } else {
                $error = "Database error occurred. Please try again.";
                error_log("Email check failed: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
            error_log("Prepare failed: " . $conn->error);
        }
    }
}

// Close connection
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Provider App - Register</title>
    <link rel="stylesheet" href="..\CSS\style.css">
    <style>
        .error { color: red; margin-bottom: 10px; }
        .success { color: green; margin-bottom: 10px; }
        .password-hint {
            font-size: 0.8em;
            color: #666;
            margin-top: -10px;
            margin-bottom: -10px; 
        }
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
</head>
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
<div class="login-form-conatiner">
    <div class="login-form" id="register-form">
        <div class="form-header">
        <h2>Sign Up</h2>
        <p>Enter your credentials and sign up to become a member of<strong>Sevak</strong></p></div>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST" action="register.php" onsubmit="return validateForm()">
            <label for="register-email">Email:</label>
            <input type="email" id="register-email" name="register-email" required>
            
            <label for="register-name">Name:</label>
            <input type="text" id="register-name" name="register-name" required>
            
            <label for="register-contact">Contact No (10 digits):</label>
            <input type="tel" id="register-contact" name="register-contact" 
                   pattern="[0-9]{10}" title="Please enter exactly 10 digits" required>
            
            <label for="user-type">Choose:</label>
            <select id="user-type" name="user-type" required>
                <option value="client" selected>Client</option>
                <option value="professional">Professional</option>
                <!-- <option value="professional">admin</option> -->
            </select>
            
            <label for="professional-specialization">Professional Specialization:</label>
            <select id="professional-specialization" name="professional-specialization" disabled>
                <option value="">-- Select --</option>
                <option value="Plumber">Plumber</option>
                <option value="Househelp">Househelp</option>
                <option value="Garbage_Collector">Garbage Collector</option>
                <option value="Cleaner">Cleaner</option>
                <option value="Electrician">Electrician</option>
                <option value="Painter">Painter</option>
                <option value="Gardener">Gardener</option>
                <option value="Laundry">Laundry</option>
            </select>
            
            <label for="register-password">Password:</label>
            <input type="password" id="register-password" name="register-password" 
                   pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}"
                   title="Must contain at least 8 characters, including letters, numbers and special symbols" required>
            <div class="password-hint">
                <p>Password must contain at least 8 characters with letters, numbers, and special symbols (@$!%*#?&)</p>
            </div>
            <label for="confirm-password">Confirm Password:</label>
            <input type="password" id="confirm-password" name="confirm-password" required>
            
            <button type="submit">Register</button>
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </form>
    </div>
        </div>
        </div>
</div>
<script>
    // Enable/disable specialization dropdown based on user type
    document.getElementById('user-type').addEventListener('change', function() {
        const specializationDropdown = document.getElementById('professional-specialization');
        if (this.value === 'professional') {
            specializationDropdown.disabled = false;
            specializationDropdown.required = true;
        } else {
            specializationDropdown.disabled = true;
            specializationDropdown.required = false;
            specializationDropdown.selectedIndex = 0;
        }
    });

    // Client-side validation for contact number
    document.getElementById('register-contact').addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, ''); // Remove non-numeric characters
        if (this.value.length > 10) {
            this.value = this.value.slice(0, 10); // Limit to 10 digits
        }
    });

    // Client-side form validation
    function validateForm() {
        const contact = document.getElementById('register-contact').value;
        const password = document.getElementById('register-password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        const userType = document.getElementById('user-type').value;
        const specialization = document.getElementById('professional-specialization').value;
        
        // Validate contact number
        if (!/^[0-9]{10}$/.test(contact)) {
            alert('Contact number must be exactly 10 digits');
            return false;
        }
        
        // Validate password
        if (!/(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}/.test(password)) {
            alert('Password must be at least 8 characters with letters, numbers, and special symbols');
            return false;
        }
        
        // Validate password match
        if (password !== confirmPassword) {
            alert('Passwords do not match');
            return false;
        }
        
        // Validate professional specialization if professional is selected
        if (userType === 'professional' && !specialization) {
            alert('Please select a professional specialization');
            return false;
        }
        
        return true;
    }
</script>
</body>
</html>