<?php
// Start session and include database connection
session_start();
require_once('../includes/config.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: ../login.php');
    exit();
}

$professional_id = isset($_GET['professional_id']) ? intval($_GET['professional_id']) : 0;

if ($professional_id <= 0) {
    // Redirect if no valid professional ID is provided
    header("Location: ../clients/booking_form.php");
    exit();
}

// Get client data
$client_id = $_SESSION['client_id'];
$client_query = "SELECT name, email, contact FROM clients WHERE id = ?";
$stmt = $conn->prepare($client_query);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$client_result = $stmt->get_result();
$client = $client_result->fetch_assoc();

// Get professional data if professional_id is provided
$professional = null;
$service_name = isset($_GET['service_name']) ? $_GET['service_name'] : '';

if ($professional_id > 0) {
    // Get professional details with profession
    $professional_query = "SELECT name, profession FROM professionals WHERE id = ?";
    $stmt = $conn->prepare($professional_query);
    $stmt->bind_param("i", $professional_id);
    $stmt->execute();
    $professional_result = $stmt->get_result();
    $professional = $professional_result->fetch_assoc();
    
    // Check if professional_pricing table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'professional_pricing'");
    if ($table_check === false || $table_check->num_rows == 0) {
        die("Error: professional_pricing table doesn't exist in the database");
    }
    
    // Get services and prices for this professional
    $services_query = "SELECT id, profession, service_name, price 
                      FROM professional_pricing 
                      WHERE professional_id = ?";
    $stmt = $conn->prepare($services_query);

    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param("i", $professional_id);
    if (!$stmt->execute()) {
        die("Error executing statement: " . $stmt->error);
    }

    $services_result = $stmt->get_result();
    $professional_services = $services_result->fetch_all(MYSQLI_ASSOC);
}

// Process form submission
$booking_status = null; // 'success', 'error', or null

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if this is a duplicate submission
    if (isset($_SESSION['last_booking_time']) && (time() - $_SESSION['last_booking_time']) < 30) {
        $error = "Please wait before making another booking request.";
        $booking_status = 'error';
    } else {
        // Validate and sanitize input
        $name = filter_var(trim($_POST['name']), FILTER_SANITIZE_STRING);
        $address = filter_var(trim($_POST['address']), FILTER_SANITIZE_STRING);
        $city = filter_var(trim($_POST['city']), FILTER_SANITIZE_STRING);
        $state = filter_var(trim($_POST['state']), FILTER_SANITIZE_STRING);
        $pin = filter_var(trim($_POST['pin']), FILTER_SANITIZE_STRING);
        $date = $_POST['date'];
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $phone = filter_var(trim($_POST['phone']), FILTER_SANITIZE_STRING);
        $time = $_POST['time'];
        $till_time = $_POST['till_time'];
        $service_id = intval($_POST['service']);
        $requirement = filter_var(trim($_POST['requirement']), FILTER_SANITIZE_STRING);
        $price = filter_var(trim($_POST['price']), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        // Basic validation
        $errors = [];
        if (empty($name)) $errors[] = "Name is required";
        if (empty($address)) $errors[] = "Address is required";
        if (empty($city)) $errors[] = "City is required";
        if (empty($state)) $errors[] = "State is required";
        if (empty($date)) $errors[] = "Date is required";
        if (empty($phone)) $errors[] = "Phone number is required";
        if (empty($time)) $errors[] = "Start time is required";
        if (empty($till_time)) $errors[] = "End time is required";
        if (empty($service_id)) $errors[] = "Service selection is required";

        // PIN validation (if provided)
        if (!empty($pin) && !preg_match('/^[0-9]{6}$/', $pin)) {
            $errors[] = "PIN code must be exactly 6 digits";
        }

        // Phone validation
        if (!preg_match('/^[0-9]{10}$/', $phone)) {
            $errors[] = "Please enter a valid 10-digit phone number";
        }

        // Email validation (if provided)
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address";
        }

        // Date validation
        $today = new DateTime();
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj || $date_obj < $today) {
            $errors[] = "Date must be today or in the future";
        }

        // Time validation
        if (!empty($time) && !empty($till_time)) {
            $start_time = DateTime::createFromFormat('h:i A', $time);
            $end_time = DateTime::createFromFormat('h:i A', $till_time);
            
            if (!$start_time || !$end_time) {
                $errors[] = "Please enter valid times in 12-hour format (e.g., 02:30 PM)";
            } elseif ($start_time >= $end_time) {
                $errors[] = "End time must be after start time";
            }
        }
        
        if (!empty($errors)) {
            $error = implode("<br>", $errors);
            $booking_status = 'error';
        } else {
            // Start transaction for atomic operations
            $conn->begin_transaction();
            
            try {
                // Verify the submitted service and price for this professional
                $price_check_query = "SELECT profession, service_name, price 
                                    FROM professional_pricing 
                                    WHERE professional_id = ? AND id = ?";
                $stmt = $conn->prepare($price_check_query);
                $stmt->bind_param("ii", $professional_id, $service_id);
                $stmt->execute();
                $price_result = $stmt->get_result();
                $service_data = $price_result->fetch_assoc();

                if (!$service_data) {
                    throw new Exception("Invalid service selected for this professional.");
                }
                
                if ($price != $service_data['price']) {
                    throw new Exception("Service price cannot be modified.");
                }
                
                // Get the verified service details
                $service_name = $service_data['service_name'];
                $profession = $service_data['profession'];
                
                // Combine time values
                $preferred_time = $time . ' - ' . $till_time;

                // Insert into requests table
                $insert_query = "INSERT INTO requests (
                    professional_id,
                    client_id,
                    professional_name,
                    client_name,
                    address,
                    city,
                    state,
                    preferred_time,
                    payment,
                    status,
                    details,
                    profession,
                    service_name,
                    date,
                    complete
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($insert_query);
                
                if ($stmt === false) {
                    throw new Exception("Error preparing statement: " . $conn->error);
                }
                
                // Set default values
                $status = 'pending';
                $complete = 'requested';
                $professional_name = $professional ? $professional['name'] : '';
                
                $bind_result = $stmt->bind_param(
                    "iisssssssssssss",
                    $professional_id,
                    $client_id,
                    $professional_name,
                    $name,
                    $address,
                    $city,
                    $state,
                    $preferred_time,
                    $price,
                    $status,
                    $requirement,
                    $profession,
                    $service_name,
                    $date,
                    $complete
                );
                
                if ($bind_result === false) {
                    throw new Exception("Error binding parameters: " . $stmt->error);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Error executing statement: " . $stmt->error);
                }
                
                // Commit transaction if all operations succeeded
                $conn->commit();
                
                $booking_status = 'success';
                $_SESSION['last_booking_time'] = time();
                
                $_SESSION['booking_popup'] = [
                    'status' => 'success',
                    'message' => 'Booking confirmed successfully!'
                ];
                
                header("Location: ../clients/clients-dashboard.php");
                exit();
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = $e->getMessage();
                $booking_status = 'error';
                $_SESSION['booking_popup'] = [
                    'status' => 'error',
                    'message' => $error
                ];
            }
        }
    }
}

// Check for booking popup in session
$show_popup = false;
$popup_message = '';
$popup_status = '';

if (isset($_SESSION['booking_popup'])) {
    $show_popup = true;
    $popup_message = $_SESSION['booking_popup']['message'];
    $popup_status = $_SESSION['booking_popup']['status'];
    unset($_SESSION['booking_popup']); // Clear after displaying
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sevak - Booking Form</title>
    <link rel="stylesheet" href="../css/booking_form.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="" class="logo">Sevak</a>
            <ul class="navbar-menu" id="navbarMenu">
                <li><a href="../clients/clients-dashboard.php" class="navbar-item">Dashboard</a></li>
                <li><a href="../../html files/service.html" class="navbar-item">Services</a></li>
                <li><a href="../../html files/aboutus.html" class="navbar-item">How It Works</a></li>
            </ul>
            <div class="navbar-toggle" id="navbarToggle">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
        </div>
    </nav>
    
    <!-- Booking Popup -->
    <?php if ($show_popup): ?>
    <div class="booking-popup <?php echo $popup_status; ?>">
        <button class="popup-close" onclick="closePopup()">&times;</button>
        <h3><?php echo $popup_status === 'success' ? 'Success!' : 'Error!'; ?></h3>
        <p><?php echo htmlspecialchars($popup_message); ?></p>
        <button onclick="redirectToDashboard()" class="btn btn-primary">
            OK
        </button>
    </div>
    <?php endif; ?>
    
    <div class="hello">
        <div class="hello h1"><h1>Enter Service Booking Details</h1></div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form id="bookingForm" method="POST" action="">
            <label for="name">Full Name:*</label>
            <input type="text" id="name" name="name" required 
                   placeholder="Enter your name" 
                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($client['name'] ?? ''); ?>">
            
            <label for="address">Address:*</label>
            <input type="text" id="address" name="address" required 
                   placeholder="Enter your address" 
                   value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
            
            <label for="city">City:*</label>
            <input type="text" id="city" name="city" 
                   placeholder="Enter your city" 
                   value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
            
            <label for="state">State:*</label>
            <input type="text" id="state" name="state" required 
                   placeholder="Enter your state" 
                   value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>">
            
            <label for="pin">Pin:</label>
            <input type="number" id="pin" name="pin" 
                placeholder="Enter your PIN" 
                pattern="[0-9]{6}"
                maxlength="6"
                oninput="this.value = this.value.slice(0, 6)"
                value="<?php echo htmlspecialchars($_POST['pin'] ?? ''); ?>">
                        
            <label for="date">Preferred Date*</label>
            <input type="date" id="date" name="date" required 
                value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>">
            
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" 
                   placeholder="Enter your Email" 
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($client['email'] ?? ''); ?>">
            
            <label for="phone">Phone Number:*</label>
            <input type="tel" id="phone" name="phone" required 
                placeholder="Enter your number" 
                pattern="[0-9]{10}"
                maxlength="10"
                oninput="this.value = this.value.slice(0, 10)"
                value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : htmlspecialchars($client['contact'] ?? ''); ?>">
            
            <label for="time">Preferred Time Duration:*</label>
            <div class="time-range">
                <input type="text" id="time" name="time" class="timepicker" 
                    placeholder="Start time" 
                    value="<?php echo htmlspecialchars($_POST['time'] ?? ''); ?>">
                <span>to</span>
                <input type="text" id="till_time" name="till_time" class="timepicker"
                    placeholder="End time"
                    value="<?php echo htmlspecialchars($_POST['till_time'] ?? ''); ?>">
            </div>
            
            <select id="service" name="service" required onchange="updatePrice()">
                <option value="">Select a service</option>
                <?php foreach ($professional_services as $service): ?>
                    <option value="<?php echo $service['id']; ?>" 
                            data-price="<?php echo $service['price']; ?>"
                            <?php echo (isset($_POST['service']) && $_POST['service'] == $service['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($service['service_name']); ?> (₹<?php echo $service['price']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="price">Price</label>
            <input type="number" id="price" name="price" readonly 
                   placeholder="Price will be auto-filled" 
                   value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
            
            <label for="requirement">Requirement:</label>
            <textarea id="requirement" name="requirement" 
                      placeholder="Describe your requirement"><?php echo htmlspecialchars($_POST['requirement'] ?? ''); ?></textarea>
            
            <input type="hidden" name="professional_id" value="<?php echo $professional_id; ?>">
            
            <button type="submit" id="submitBtn">Book Now</button>
        </form>
    </div>  

    <!-- jQuery and Timepicker JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js"></script>
    
    <script>
    // Function to update price based on selected service
    function updatePrice() {
        const serviceSelect = document.getElementById('service');
        const priceInput = document.getElementById('price');
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        
        if (selectedOption && selectedOption.dataset.price) {
            priceInput.value = selectedOption.dataset.price;
        } else {
            priceInput.value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize price on page load
        updatePrice();
        
        // Initialize timepicker with 12-hour format
        $('.timepicker').timepicker({
            timeFormat: 'h:mm p',
            interval: 30,
            minTime: '12:00am',
            maxTime: '11:30pm',
            defaultTime: 'now',
            startTime: '6:00am',
            dynamic: false,
            dropdown: true,
            scrollbar: true
        });

        // Mobile menu toggle functionality
        document.getElementById('navbarToggle').addEventListener('click', function() {
            document.getElementById('navbarMenu').classList.toggle('active');
        });

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('date').setAttribute('min', today);

        // Add event listeners for real-time validation
        const phoneInput = document.getElementById('phone');
        const pinInput = document.getElementById('pin');
        
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
        });
        
        pinInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
        });

        // Form validation
        document.getElementById("bookingForm").addEventListener("submit", function(event) {
            let isValid = true;
            let errorMessage = '';
            
            // Time validation
            const startTime = document.getElementById('time').value;
            const endTime = document.getElementById('till_time').value;
            
            if (!startTime || !endTime) {
                isValid = false;
                errorMessage += "Both start and end times are required.\n";
            } else {
                // Convert to Date objects for comparison
                const start24 = $('.timepicker').first().timepicker('getTime');
                const end24 = $('.timepicker').last().timepicker('getTime');
                
                if (start24 >= end24) {
                    isValid = false;
                    errorMessage += "End time must be after start time.\n";
                }
            }
            
            // Phone validation
            const phone = document.getElementById('phone').value.trim();
            const phoneRegex = /^[0-9]{10}$/;
            if (!phoneRegex.test(phone)) {
                isValid = false;
                errorMessage += "Please enter a valid 10-digit phone number.\n";
            }
            
            // PIN validation (if provided)
            const pin = document.getElementById('pin').value.trim();
            if (pin && !/^[0-9]{6}$/.test(pin)) {
                isValid = false;
                errorMessage += "PIN code must be exactly 6 digits.\n";
            }
            
            // Email validation (if provided)
            const email = document.getElementById('email').value.trim();
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                isValid = false;
                errorMessage += "Please enter a valid email address.\n";
            }
            
            // Date validation
            const selectedDate = document.getElementById('date').value;
            if (new Date(selectedDate) < new Date(today)) {
                isValid = false;
                errorMessage += "Date must be today or in the future.\n";
            }
            
            if (!isValid) {
                event.preventDefault();
                alert(errorMessage);
                return false;
            }
            
            // Disable submit button to prevent multiple submissions
            document.getElementById('submitBtn').disabled = true;
            return true;
        });

        // Make sure the onchange handler is properly set up
        document.getElementById('service').addEventListener('change', updatePrice);
    });

    function closePopup() {
        document.querySelector('.booking-popup').style.display = 'none';
        window.location.href = '../clients/clients-dashboard.php';
    }

    function redirectToDashboard() {
        window.location.href = '../clients/clients-dashboard.php';
    }

    // Auto-close popup after 5 seconds
    <?php if ($show_popup): ?>
    setTimeout(function() {
        redirectToDashboard();
    }, 5000);
    <?php endif; ?>
    </script>
</body>
</html>