<?php
// Correct way to include your database configuration
require_once  '..\users\includes\config.php';

// Initialize variables
$name = $email = $message = "";
$responseMessage = "";
$responseColor = "red";

// Check if database connections exist
if (!isset($conn)) {
    die("Database connection not established. Please check your config.php file.");
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $name = htmlspecialchars(trim($_POST["name"]));
    $email = htmlspecialchars(trim($_POST["email"]));
    $message = htmlspecialchars(trim($_POST["message"]));
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($message)) {
        $responseMessage = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $responseMessage = "Please enter a valid email address.";
    } else {
        // Prepare and bind using sevak_db connection
        $stmt = $conn->prepare("INSERT INTO contactus (name, email, message, created_at) VALUES (?, ?, ?, NOW())");
        if (!$stmt) {
            $responseMessage = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("sss", $name, $email, $message);
            
            // Execute the statement
            if ($stmt->execute()) {
                $responseMessage = "Thank you for reaching out! We will get back to you soon.";
                $responseColor = "green";
                
                // Clear form fields
                $name = $email = $message = "";
            } else {
                $responseMessage = "Error: " . $stmt->error;
            }
            
            // Close statement
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Sevak</title>
    <style>
    :root {
  --sevak-primary: #7E69AB;
  --sevak-secondary: #6E59A5;
  --sevak-dark: #1A1F2C;
  --sevak-light: #F1F0FB;
  --sevak-white: #FFFFFF;
  --sevak-gray: #8E9196;
  --sevak-gray-light: #F6F6F7;
}
body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background: linear-gradient(135deg, #7E69AB 20%, #F1F0FB 80%);
    }
    
    header {
        background: #6E59A5;
        color: white;
        text-align: center;
        margin-top: 50px;
        padding: 1rem;
    }
 /* Navbar */
.navbar {
  background-color:var(--sevak-primary);
  padding: 1rem 0;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.navbar .container {
  display: flex;
  justify-content: space-between;
  align-items: center;
 
}

.logo {
  font-size: 1.5rem;
  font-weight: 700;
  text-decoration:none;
  color: white;
}

.navbar-menu {
  display: flex;
 
  gap: 2rem;
}

.navbar-item {
  color: #333;
  text-decoration:none;
  transition: color 0.3s;
}

.navbar-item:hover {
  color: var(--sevak-primary);
}

.navbar-toggle {
  display: none;
  flex-direction: column;
  cursor: pointer;
}

.bar {
  width: 25px;
  height: 3px;
  background-color: #333;
  margin: 3px 0;
  transition: 0.4s;
}

    .contact-container {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding: 20px;
        gap: 20px;
    }
    
    .contact-form, .contact-info {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        width: 50%;
    }
    
    label {
        display: block;
        margin: 10px 10px 5px;
    }
    
    input, textarea {
        width: 98%;
        padding: 10px 10px;
        margin-bottom: 10px;
        
        border: 1px solid #ccc;
        border-radius: 5px;
    }
    
    button {
        background: #7E69AB;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        width: 100%;
    }
    
    button:hover {
        background: #8E9196;
    }
    
    .map iframe {
        width: 100%;
        height: 200px;
        border-radius: 8px;
    }

    /* Back Button Styles */
    .back-button {
        position: absolute;
        left: 20px;
        top: 50%;
        transform: translateY(-50%);
        background-color: white;
        color: var(--sevak-primary);
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        text-decoration: none;
        transition: background-color 0.3s ease;
    }

    .back-button:hover {
        background-color: #f0f0f0;
    }
    
    @media (max-width: 768px) {
        .contact-container {
            flex-direction: column;
        }
        
        .contact-form, .contact-info {
            width: 100%;
        }

        .back-button {
            position: static;
            transform: none;
            margin-bottom: 10px;
            text-align: center;
            display: inline-block;
        }
    }
    </style>
</head>
<body>
<nav class="navbar">
        <div class="container">
          <div class="navbar-brand">
            <a href="/" class="logo" style="padding-left: 5rem;">Sevak</a>
            <a href="javascript:history.back()" style="position: absolute; top: 20px; right: 20px; color: white; text-decoration: none; font-size: 1.2rem;">← Back</a>
          </div>
  
    
          <div class="navbar-toggle" id="navbarToggle">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
          </div>
        </div>
      </nav>
    <header>
        <h1>Contact Us</h1>
    </header>
    <section class="contact-container">
        <div class="contact-form">
            <h2>Get in Touch</h2>
            <form id="contactForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?php echo $name; ?>" required>
                
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo $email; ?>" required>
                
                <label for="message">Message</label>
                <textarea id="message" name="message" required><?php echo $message; ?></textarea>
                
                <button type="submit">Send Message</button>
            </form>
            <?php if (!empty($responseMessage)): ?>
                <p id="responseMessage" style="color: <?php echo $responseColor; ?>"><?php echo $responseMessage; ?></p>
            <?php endif; ?>
        </div>
        <div class="contact-info">
            <h2>Contact Details</h2>
            <p><strong>Phone:</strong> +91 98765 43210</p>
            <p><strong>Email:</strong> support@sevak.com</p>
            <p><strong>Address:</strong>Banasthali vidyapith, tonk, Rajasthan, 304022</p>
            <div class="map">
                <iframe src="https://maps.google.com/maps?q=Rajasthan&t=&z=13&ie=UTF8&iwloc=&output=embed" frameborder="0" allowfullscreen></iframe>
            </div>
        </div>
    </section>
</body>
</html>