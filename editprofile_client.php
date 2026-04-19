<?php
// Start session and include database connection
session_start();
require_once('../includes/config.php');

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get client data
$client_id = $_SESSION['client_id'];
$client_query = "SELECT * FROM clients WHERE id = ?";
$stmt = $conn->prepare($client_query);
if (!$stmt) {
    die("Error preparing client query: " . $conn->error);
}
$stmt->bind_param("i", $client_id);
if (!$stmt->execute()) {
    die("Error executing client query: " . $stmt->error);
}
$client_result = $stmt->get_result();
$client = $client_result->fetch_assoc();

// Get client's completed bookings that haven't been reviewed
$bookings_query = "SELECT 
    r.id,
    r.professional_name,
    r.service_name,
    r.address,
    r.city,
    r.state,
    r.preferred_time,
    r.date,
    r.created_at
FROM requests r
LEFT JOIN reviews rev ON r.id = rev.request_id
WHERE r.client_id = ? 
AND r.status = 'accepted' 
AND r.complete = 'complete'
AND (rev.id IS NULL OR rev.reviewed = 0)
ORDER BY r.date DESC";

$stmt = $conn->prepare($bookings_query);
if (!$stmt) {
    die("Error preparing bookings query: " . $conn->error);
}
$stmt->bind_param("i", $client_id);
if (!$stmt->execute()) {
    die("Error executing bookings query: " . $stmt->error);
}
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profileImage'])) {
    $target_dir = "../clients/uploads/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Check if image file is an actual image
    $check = getimagesize($_FILES["profileImage"]["tmp_name"]);
    if ($check !== false) {
        // Generate unique filename
        $imageFileType = strtolower(pathinfo($_FILES["profileImage"]["name"], PATHINFO_EXTENSION));
        $new_filename = "profile_" . $client_id . "." . $imageFileType;
        $target_file = $target_dir . $new_filename;
        
        // Try to upload file
        if (move_uploaded_file($_FILES["profileImage"]["tmp_name"], $target_file)) {
            // Update database with just the filename (not full path)
            $update_query = "UPDATE clients SET profile_image = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $new_filename, $client_id);
            $stmt->execute();
            
            // Update client data
            $client['profile_image'] = $new_filename;
        } else {
            // Add error handling
            $error = error_get_last();
            die("Failed to move uploaded file. Error: " . $error['message']);
        }
    }
}

// Handle form submission for basic info
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_basic_info'])) {
    $name = $_POST['fullName'];
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $email = $_POST['email'];
    $contact = $_POST['phone'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $pincode = $_POST['pincode'];
    
    // Validation flags
    $is_valid = true;
    $errors = [];
    
    // Phone number validation (Indian format)
    if (!preg_match('/^[6-9]\d{9}$/', $contact)) {
        $is_valid = false;
        $errors['phone'] = "Please enter a valid 10-digit Indian phone number starting with 6,7,8 or 9";
    }
    
    // Pincode validation (Indian format)
    if (!preg_match('/^[1-9]\d{5}$/', $pincode)) {
        $is_valid = false;
        $errors['pincode'] = "Please enter a valid 6-digit Indian pincode";
    }
    
    if ($is_valid) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update clients table
            $update_client_query = "UPDATE clients SET 
                name = ?,
                dob = ?,
                gender = ?,
                email = ?,
                contact = ?,
                address = ?,
                city = ?,
                state = ?,
                pincode = ?,
                updated_at = NOW()
            WHERE id = ?";
            
            $stmt = $conn->prepare($update_client_query);
            $stmt->bind_param("sssssssssi", $name, $dob, $gender, $email, $contact, $address, $city, $state, $pincode, $client_id);
            $stmt->execute();
            
            // Update user_db table
            $update_user_query = "UPDATE user_db SET 
                name = ?,
                email = ?,
                contact = ?,
                updated_at = NOW()
            WHERE id = ?";
            
            $stmt = $conn->prepare($update_user_query);
            $stmt->bind_param("sssi", $name, $email, $contact, $client_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Refresh client data
            $client_result = $conn->query("SELECT * FROM clients WHERE id = $client_id");
            $client = $client_result->fetch_assoc();
            
            $success_message = "Profile updated successfully!";
        } catch (Exception $e) {
            // Rollback transaction if any error occurs
            $conn->rollback();
            $errors['database'] = "Failed to update profile. Please try again.";
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['currentPassword'];
    $new_password = $_POST['newPassword'];
    $confirm_password = $_POST['confirmPassword'];
    
    // Validation flags
    $is_valid = true;
    $errors = [];
    
    // Verify current password
    if (!password_verify($current_password, $client['password'])) {
        $is_valid = false;
        $errors['current_password'] = "Current password is incorrect";
    }
    
    // Validate new password strength only if current password is correct
    if ($is_valid) {
        if (strlen($new_password) < 8) {
            $is_valid = false;
            $errors['new_password'] = "Password must be at least 8 characters long";
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $is_valid = false;
            $errors['new_password'] = "Password must contain at least one uppercase letter";
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $is_valid = false;
            $errors['new_password'] = "Password must contain at least one lowercase letter";
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $is_valid = false;
            $errors['new_password'] = "Password must contain at least one number";
        } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
            $is_valid = false;
            $errors['new_password'] = "Password must contain at least one special character";
        }
        
        // Check if passwords match
        if ($new_password !== $confirm_password) {
            $is_valid = false;
            $errors['confirm_password'] = "New passwords don't match";
        }
    }
    
    if ($is_valid) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update password in clients table
            $update_client_query = "UPDATE clients SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($update_client_query);
            $stmt->bind_param("si", $hashed_password, $client_id);
            $stmt->execute();
            
            // Update password in user_db table
            $update_user_query = "UPDATE user_db SET password = ? WHERE email = ?";
            $stmt = $conn->prepare($update_user_query);
            $stmt->bind_param("ss", $hashed_password, $client['email']);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $password_updated = true;
            
            // Refresh client data
            $client_result = $conn->query("SELECT * FROM clients WHERE id = $client_id");
            $client = $client_result->fetch_assoc();
        } catch (Exception $e) {
            // Rollback transaction if any error occurs
            $conn->rollback();
            $errors['database'] = "Failed to update password. Please try again.";
        }
    }
}

// Handle account deactivation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deactivate_account'])) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Deactivate in clients table
        $update_client_query = "UPDATE clients SET account = 'deactive' WHERE id = ?";
        $stmt = $conn->prepare($update_client_query);
        $stmt->bind_param("i", $client_id);
        $stmt->execute();
        
        // Deactivate in user_db table
        $update_user_query = "UPDATE user_db SET account = 'deactive' WHERE email = ?";
        $stmt = $conn->prepare($update_user_query);
        $stmt->bind_param("s", $client['email']);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Logout and redirect
        session_destroy();
        header('Location: ../index.php');
        exit();
    } catch (Exception $e) {
        // Rollback transaction if any error occurs
        $conn->rollback();
        $errors['database'] = "Failed to deactivate account. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sevak - Profile</title>
    <link rel="stylesheet" href="../css/editprofile_client.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="../Users/index.php" class="logo">Sevak</a>
            <ul class="navbar-menu" id="navbarMenu">
                <li><a href="../../html files/service.html" class="navbar-item">Services</a></li>
                <li><a href="../../html files/aboutus.html" class="navbar-item">How It Works</a></li>
                <li><a href="../contactus.php" class="navbar-item">Contact Us</a></li>
                <li><a href="../../html files/help.html" class="navbar-item">Help</a></li>
                <li><a href="clients-dashboard.php" class="navbar-item">Dashboard</a></li>
            </ul>
            <div class="navbar-toggle" id="navbarToggle">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
        </div>
    </nav>

    <!-- Edit Profile Section -->
    <section class="edit-profile-section">
        <div class="container">
            <div class="edit-profile-header">
                <h2>Account</h2>
            </div>

            <div class="edit-profile-container">
                <div class="profile-sidebar">
                    <div class="profile-image-container">
                    <img src="<?php 
                        echo !empty($client['profile_image']) ? 
                        '../clients/uploads/' . htmlspecialchars($client['profile_image']) : 
                        '../clients/uploads/default_profile.png'; 
                    ?>" alt="Profile" class="profile-image" id="profileImage">
                        <div class="profile-image-overlay">
                            <form method="post" enctype="multipart/form-data">
                                <label for="profileImageUpload" class="profile-image-edit">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" id="profileImageUpload" name="profileImage" accept="image/*" hidden>
                            </form>
                        </div>
                    </div>
                    <div class="profile-navigation">
                        <a href="#Basic-Info" class="profile-nav-item active">Basic-Info</a>
                        <a href="#bookings" class="profile-nav-item">My Bookings</a>
                        <a href="#settings" class="profile-nav-item">Account Settings</a>
                    </div>
                </div>
                
                <div class="profile-content">
                    <!-- Basic Information Section -->
                    <div class="profile-section active" id="Basic-Info">
                        <h3 class="section-title"><u>Basic Information</u></h3>
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        <form class="profile-form" method="post" onsubmit="return validateForm()">
                            <div class="form-group">
                                <label for="fullName">Full Name</label>
                                <input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($client['name']); ?>" class="form-control" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($client['contact']); ?>" 
                                           class="form-control" pattern="[6-9]\d{9}" title="10-digit Indian number starting with 6-9" required>
                                    <?php if (isset($errors['phone'])): ?>
                                        <span class="error-message"><?php echo $errors['phone']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="dob">Date of Birth</label>
                                    <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($client['dob']); ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender" class="form-control">
                                        <option value="select" <?php echo empty($client['gender']) ? 'selected' : ''; ?>>--select--</option>
                                        <option value="male" <?php echo $client['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo $client['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo $client['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                        <option value="prefer-not-to-say" <?php echo $client['gender'] == 'prefer-not-to-say' ? 'selected' : ''; ?>>Prefer not to say</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="address">Full Address</label>
                                <textarea id="address" name="address" class="form-control" rows="3" required><?php echo htmlspecialchars($client['address']); ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($client['city']); ?>" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="state">State</label>
                                    <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($client['state']); ?>" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="pincode">Pincode</label>
                                    <input type="text" id="pincode" name="pincode" value="<?php echo htmlspecialchars($client['pincode']); ?>" 
                                           class="form-control" pattern="[1-9]\d{5}" title="6-digit Indian pincode" required>
                                    <?php if (isset($errors['pincode'])): ?>
                                        <span class="error-message"><?php echo $errors['pincode']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="save_basic_info" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- My Bookings Section -->
                    <div class="profile-section" id="bookings">
                        <h3 class="section-title">My Bookings </h3>
                        <?php if (!empty($bookings)): ?>
                            <?php foreach ($bookings as $booking): ?>
                                <div class="booking-card">
                                    <div class="booking-info">
                                        <h4><?php echo htmlspecialchars($booking['service_name']); ?></h4>
                                        <p class="booking-client"><i class="fas fa-user"></i> <?php echo htmlspecialchars($booking['professional_name']); ?></p>
                                        <p class="booking-address">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo htmlspecialchars($booking['address']); ?>, 
                                            <?php echo htmlspecialchars($booking['city']); ?>, 
                                            <?php echo htmlspecialchars($booking['state']); ?>
                                        </p>
                                        <p class="booking-time">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo date('F j, Y', strtotime($booking['date'])); ?> 
                                            <?php echo date('g:i A', strtotime($booking['preferred_time'])); ?>
                                        </p>
                                    </div>
                                    <button class="btn btn-primary add-review-btn" data-request-id="<?php echo $booking['id']; ?>">Add Review</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No bookings pending review.</p>
                        <?php endif; ?>
                    </div>  
                    
                    <!-- Account Settings Section -->
                    <div class="profile-section" id="settings">
                        <h3 class="section-title">Account Settings</h3>
                        <form class="profile-form" method="post">
                            <?php if (isset($password_updated)): ?>
                                <div class="alert alert-success">Password updated successfully!</div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="currentPassword">Current Password</label>
                                <input type="password" id="currentPassword" name="currentPassword" class="form-control" placeholder="Enter current password" required>
                                <?php if (isset($errors['current_password'])): ?>
                                    <span class="error-message"><?php echo $errors['current_password']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="newPassword">New Password</label>
                                    <input type="password" id="newPassword" name="newPassword" class="form-control" 
                                        placeholder="Enter new password" required 
                                        oninput="checkPasswordStrength(this.value)">
                                    <div class="password-strength" id="passwordStrength">
                                        <span></span><span></span><span></span><span></span><span></span>
                                    </div>
                                    <div class="password-requirements">
                                        Password must contain:
                                        <ul>
                                            <li id="req-length">At least 8 characters</li>
                                            <li id="req-upper">One uppercase letter</li>
                                            <li id="req-lower">One lowercase letter</li>
                                            <li id="req-number">One number</li>
                                            <li id="req-special">One special character</li>
                                        </ul>
                                    </div>
                                    <?php if (isset($errors['new_password'])): ?>
                                        <span class="error-message"><?php echo $errors['new_password']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="confirmPassword">Confirm New Password</label>
                                    <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" 
                                        placeholder="Confirm new password" required
                                        oninput="checkPasswordMatch()">
                                    <span id="passwordMatch" class="error-message"></span>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <span class="error-message"><?php echo $errors['confirm_password']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                            </div>
                            <div class="danger-zone">
                                <h4 class="settings-subheading">Danger Zone</h4>
                                <p>Actions performed here cannot be undone. Please proceed with caution.</p>
                                <div class="danger-actions">
                                    <form method="post" onsubmit="return confirm('Are you sure you want to deactivate your account? This action cannot be undone.');">
                                        <button type="submit" name="deactivate_account" class="btn btn-outline">Deactivate Account</button>
                                    </form>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <h3>Sevak</h3>
                    <p>Connecting skilled professionals with customers in need of services. Find reliable help for all your home and office needs.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="../../html files/aboutus.html">About Us</a></li>
                        <li><a href="../../html files/service.html">Services</a></li>
                        <li><a href="#">How It Works</a></li>
                        <li><a href="#">Professionals</a></li>
                        <li><a href="#">Blog</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Services</h4>
                    <ul>
                        <li><a href="#">Electrician</a></li>
                        <li><a href="#">Plumber</a></li>
                        <li><a href="#">Carpenter</a></li>
                        <li><a href="#">Cleaning</a></li>
                        <li><a href="#">More Services</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h4>Contact Us</h4>
                    <ul>
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <div>123 Main Street, Delhi, India - 110001</div>
                        </li>
                        <li>
                            <i class="fas fa-phone-alt"></i>
                            <div>+91 98765 43210</div>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <div>info@sevak.com</div>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2023 Sevak. All rights reserved.</p>
                <div class="footer-legal">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Review Dialog -->
    <div class="review-dialog" id="reviewDialog">
        <div class="review-dialog-content">
            <span class="close-dialog" id="closeDialog">&times;</span>
            <h3>Add Your Review</h3>
            <form id="reviewForm" method="post" action="../clients/submit_review.php">
                <input type="hidden" id="requestId" name="request_id">
                <div class="form-group">
                    <label>Rating</label>
                    <div class="rating-stars">
                        <i class="far fa-star" data-rating="1"></i>
                        <i class="far fa-star" data-rating="2"></i>
                        <i class="far fa-star" data-rating="3"></i>
                        <i class="far fa-star" data-rating="4"></i>
                        <i class="far fa-star" data-rating="5"></i>
                    </div>
                    <input type="hidden" id="rating" name="rating" required>
                </div>
                <div class="form-group">
                    <label for="reviewText">Your Review</label>
                    <textarea id="reviewText" name="review" class="form-control" rows="5" placeholder="Share your experience..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Submit Review</button>
            </form>
        </div>
    </div>

    <script src="../js/script.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Profile image upload
        const profileImageUpload = document.getElementById('profileImageUpload');
        if (profileImageUpload) {
            profileImageUpload.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    this.form.submit();
                }
            });
        }

        // Tab navigation
        const navItems = document.querySelectorAll('.profile-nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                
                // Update active nav item
                navItems.forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding section
                document.querySelectorAll('.profile-section').forEach(section => {
                    section.classList.remove('active');
                });
                document.getElementById(targetId).classList.add('active');
            });
        });

        // Mobile menu toggle
        const navbarToggle = document.getElementById('navbarToggle');
        const navbarMenu = document.getElementById('navbarMenu');
        if (navbarToggle && navbarMenu) {
            navbarToggle.addEventListener('click', function() {
                navbarMenu.classList.toggle('active');
            });
        }

        // Phone number input validation
        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });

        // Pincode input validation
        document.getElementById('pincode').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 6);
        });
    });

    function validateForm() {
        // Phone number validation
        const phone = document.getElementById('phone').value;
        const phoneRegex = /^[6-9]\d{9}$/;
        if (!phoneRegex.test(phone)) {
            alert('Please enter a valid 10-digit Indian phone number starting with 6,7,8 or 9');
            return false;
        }

        // Pincode validation
        const pincode = document.getElementById('pincode').value;
        const pincodeRegex = /^[1-9]\d{5}$/;
        if (!pincodeRegex.test(pincode)) {
            alert('Please enter a valid 6-digit Indian pincode');
            return false;
        }

        return true;
    }
    function validatePasswordForm() {
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        // Check current password (this would normally require an AJAX call to verify server-side)
        if (currentPassword.length === 0) {
            alert('Please enter your current password');
            return false;
        }
        
        // Check new password strength
        if (newPassword.length < 8) {
            alert('Password must be at least 8 characters long');
            return false;
        }
        
        if (!/[A-Z]/.test(newPassword)) {
            alert('Password must contain at least one uppercase letter');
            return false;
        }
        
        if (!/[a-z]/.test(newPassword)) {
            alert('Password must contain at least one lowercase letter');
            return false;
        }
        
        if (!/[0-9]/.test(newPassword)) {
            alert('Password must contain at least one number');
            return false;
        }
        
        if (!/[^A-Za-z0-9]/.test(newPassword)) {
            alert('Password must contain at least one special character');
            return false;
        }
        
        // Check password match
        if (newPassword !== confirmPassword) {
            alert('Passwords do not match');
            return false;
        }
        
        return true;
    }

    // Update the form submission to use this function
    document.querySelector('#settings form').addEventListener('submit', function(e) {
        if (!validatePasswordForm()) {
            e.preventDefault();
        }
    });

    // Review Dialog functionality
    const reviewDialog = document.getElementById('reviewDialog');
    const closeDialog = document.getElementById('closeDialog');
    const reviewForm = document.getElementById('reviewForm');
    const stars = document.querySelectorAll('.rating-stars i');
    const ratingInput = document.getElementById('rating');

    // Open dialog when Add Review button is clicked
    document.querySelectorAll('.add-review-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const requestId = this.getAttribute('data-request-id');
            document.getElementById('requestId').value = requestId;
            
            // Reset form
            reviewForm.reset();
            stars.forEach(star => {
                star.classList.add('far');
                star.classList.remove('fas', 'active');
            });
            ratingInput.value = '';
            
            reviewDialog.style.display = 'flex';
        });
    });

    // Close dialog
    closeDialog.addEventListener('click', function() {
        reviewDialog.style.display = 'none';
    });

    // Star rating functionality
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            ratingInput.value = rating;
            
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.add('fas', 'active');
                    s.classList.remove('far');
                } else {
                    s.classList.add('far');
                    s.classList.remove('fas', 'active');
                }
            });
        });
    });

    // Close dialog when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === reviewDialog) {
            reviewDialog.style.display = 'none';
        }
    });

    // Handle form submission
    reviewForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate form
        if (!ratingInput.value) {
            alert('Please select a rating');
            return;
        }
        
        const reviewText = document.getElementById('reviewText').value.trim();
        if (!reviewText) {
            alert('Please enter your review');
            return;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
        
        // Create FormData
        const formData = new FormData();
        formData.append('request_id', document.getElementById('requestId').value);
        formData.append('rating', ratingInput.value);
        formData.append('review', reviewText);
        
        // Submit form via AJAX
        fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(async response => {
            // First get the text to see if it's valid JSON
            const text = await response.text();
            
            try {
                // Try to parse as JSON
                const data = JSON.parse(text);
                
                if (!response.ok) {
                    throw new Error(data.message || 'Server error');
                }
                
                return data;
            } catch (e) {
                // If not JSON, throw with the raw text
                throw new Error(text || 'Invalid server response');
            }
        })
        .then(data => {
            if (data.success) {
                alert('Review submitted successfully!');
                reviewDialog.style.display = 'none';
                location.reload();
            } else {
                throw new Error(data.message || 'Submission failed');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Review';
        });
    });
    </script>
</body>
</html>