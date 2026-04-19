<?php
session_start();
require_once '../includes/config.php';

// Check if professional is logged in
if (!isset($_SESSION['professional_id'])) {
    header("Location: ../../professional.php");
    exit();
}

$professional_id = $_SESSION['professional_id'];
// Process expired bookings and requests automatically


// Fetch professional data
$professional_stmt = $conn->prepare("SELECT * FROM professionals WHERE id = ?");
$professional_stmt->bind_param("i", $professional_id);
$professional_stmt->execute();
$professional_result = $professional_stmt->get_result();
$professional = $professional_result->fetch_assoc();
$professional_stmt->close();

// Calculate total earnings from payments table
$earnings_stmt = $conn->prepare("SELECT SUM(payment) as total_earnings FROM payments WHERE professional_id = ? AND status = 'paid'");
$earnings_stmt->bind_param("i", $professional_id);
$earnings_stmt->execute();
$earnings_result = $earnings_stmt->get_result();
$earnings = $earnings_result->fetch_assoc();
$total_earnings = $earnings['total_earnings'] ?? 0;
$earnings_stmt->close();

// Calculate average rating from reviews table
$rating_stmt = $conn->prepare("SELECT AVG(rating) as average_rating FROM reviews WHERE professional_id = ?");
$rating_stmt->bind_param("i", $professional_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating = $rating_result->fetch_assoc();
$average_rating = round($rating['average_rating'] ?? 0, 1);
$rating_stmt->close();

// Count clients served (completed jobs)
$clients_stmt = $conn->prepare("SELECT COUNT(DISTINCT client_id) as clients_served FROM requests WHERE professional_id = ? AND status = 'accepted' AND complete = 'complete'");
$clients_stmt->bind_param("i", $professional_id);
$clients_stmt->execute();
$clients_result = $clients_stmt->get_result();
$clients = $clients_result->fetch_assoc();
$clients_served = $clients['clients_served'] ?? 0;
$clients_stmt->close();

// Count total upcoming bookings
$bookings_stmt = $conn->prepare("SELECT COUNT(*) as total_bookings FROM requests WHERE professional_id = ? AND status = 'accepted' AND complete = 'upcoming'");
$bookings_stmt->bind_param("i", $professional_id);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();
$bookings = $bookings_result->fetch_assoc();
$total_bookings = $bookings['total_bookings'] ?? 0;
$bookings_stmt->close();

// Update professional stats in professionals table
$update_stmt = $conn->prepare("UPDATE professionals SET total_bookings = ?, total_earnings = ?, rating = ?, clients_served = ? WHERE id = ?");
$update_stmt->bind_param("idiii", $total_bookings, $total_earnings, $average_rating, $clients_served, $professional_id);
$update_stmt->execute();
$update_stmt->close();

// Fetch work requests (status = pending, complete = requested)
$requests_stmt = $conn->prepare("SELECT * FROM requests WHERE professional_id = ? AND status = 'pending' AND complete = 'requested' ORDER BY date, preferred_time");
$requests_stmt->bind_param("i", $professional_id);
$requests_stmt->execute();
$requests_result = $requests_stmt->get_result();
$requests = $requests_result->fetch_all(MYSQLI_ASSOC);
$requests_stmt->close();

// Fetch upcoming bookings (status = accepted, complete = upcoming)
$upcoming_stmt = $conn->prepare("SELECT * FROM requests WHERE professional_id = ? AND status = 'accepted' AND complete = 'upcoming' ORDER BY date, preferred_time");
$upcoming_stmt->bind_param("i", $professional_id);
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();
$upcoming_bookings = $upcoming_result->fetch_all(MYSQLI_ASSOC);
$upcoming_stmt->close();

// Fetch payment history (from payments table where status = paid)
$payments_stmt = $conn->prepare("SELECT * FROM payments WHERE professional_id = ? AND status = 'paid' ORDER BY date DESC");
$payments_stmt->bind_param("i", $professional_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();
$payments = $payments_result->fetch_all(MYSQLI_ASSOC);
$payments_stmt->close();

// Fetch history (status = accepted and complete = complete or cancelled)
$history_stmt = $conn->prepare("SELECT r.*, rev.rating, rev.review FROM requests r LEFT JOIN reviews rev ON r.id = rev.request_id WHERE r.professional_id = ? AND r.status = 'accepted' AND (r.complete = 'complete' OR r.complete = 'cancelled') ORDER BY r.date DESC");
$history_stmt->bind_param("i", $professional_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$history = $history_result->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();

// Fetch professional availability
$availability_stmt = $conn->prepare("SELECT * FROM professional_availability WHERE professional_id = ?");
$availability_stmt->bind_param("i", $professional_id);
$availability_stmt->execute();
$availability_result = $availability_stmt->get_result();

if ($availability_result->num_rows > 0) {
    $availability = $availability_result->fetch_assoc();
} else {
    // Create default availability if none exists
    $availability = [
        'monday' => 1,
        'tuesday' => 1,
        'wednesday' => 1,
        'thursday' => 1,
        'friday' => 1,
        'saturday' => 1,
        'sunday' => 0
    ];
    // Insert default availability
    $insert_stmt = $conn->prepare("INSERT INTO professional_availability (professional_id, monday, tuesday, wednesday, thursday, friday, saturday, sunday) VALUES (?, 1, 1, 1, 1, 1, 1, 0)");
    $insert_stmt->bind_param("i", $professional_id);
    $insert_stmt->execute();
    $insert_stmt->close();
}
$availability_stmt->close();

// Handle availability update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_availability'])) {
    $monday = isset($_POST['monday']) ? 1 : 0;
    $tuesday = isset($_POST['tuesday']) ? 1 : 0;
    $wednesday = isset($_POST['wednesday']) ? 1 : 0;
    $thursday = isset($_POST['thursday']) ? 1 : 0;
    $friday = isset($_POST['friday']) ? 1 : 0;
    $saturday = isset($_POST['saturday']) ? 1 : 0;
    $sunday = isset($_POST['sunday']) ? 1 : 0;
    
    // Check if availability record exists
    $check_stmt = $conn->prepare("SELECT * FROM professional_availability WHERE professional_id = ?");
    $check_stmt->bind_param("i", $professional_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_stmt->close();
    
    if ($check_result->num_rows > 0) {
        // Update existing record
        $update_stmt = $conn->prepare("UPDATE professional_availability SET monday = ?, tuesday = ?, wednesday = ?, thursday = ?, friday = ?, saturday = ?, sunday = ? WHERE professional_id = ?");
        $update_stmt->bind_param("iiiiiiii", $monday, $tuesday, $wednesday, $thursday, $friday, $saturday, $sunday, $professional_id);
    } else {
        // Insert new record
        $update_stmt = $conn->prepare("INSERT INTO professional_availability (monday, tuesday, wednesday, thursday, friday, saturday, sunday, professional_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $update_stmt->bind_param("iiiiiiii", $monday, $tuesday, $wednesday, $thursday, $friday, $saturday, $sunday, $professional_id);
    }
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Availability updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating availability.";
    }
    $update_stmt->close();
    
    header("Location: professional-dashboard.php");
    exit();
}

// Handle request actions (accept/decline)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $request_id = $_POST['request_id'];
        $action = $_POST['action'];
        
        if ($action == 'accept') {
            $update_request = $conn->prepare("UPDATE requests SET status = 'accepted', complete = 'upcoming', updated_at = NOW() WHERE id = ?");
            $update_request->bind_param("i", $request_id);
            $update_request->execute();
            $update_request->close();
            
            // Add to payments table as pending
            $request_info = $conn->query("SELECT * FROM requests WHERE id = $request_id")->fetch_assoc();
            $insert_payment = $conn->prepare("INSERT INTO payments (professional_id, client_id, request_id, date, client_name, professional_name, service_name, payment, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $insert_payment->bind_param("iiissssd", $professional_id, $request_info['client_id'], $request_id, $request_info['date'], $request_info['client_name'], $professional['name'], $request_info['service_name'], $request_info['payment']);
            $insert_payment->execute();
            $insert_payment->close();
            
            $_SESSION['success'] = "Request accepted successfully!";
        } elseif ($action == 'decline') {
            $reason = $_POST['reason'] ?? 'No reason provided';
            $update_request = $conn->prepare("UPDATE requests SET status = 'declined', complete = 'cancelled', reason = ?, updated_at = NOW() WHERE id = ?");
            $update_request->bind_param("si", $reason, $request_id);
            $update_request->execute();
            $update_request->close();
            
            $_SESSION['success'] = "Request declined successfully!";
        }
        
        header("Location: professional-dashboard.php");
        exit();
    }
}

// Check if professional has pricing entries, if not create default one
$pricing_check = $conn->prepare("SELECT * FROM professional_pricing WHERE professional_id = ?");
$pricing_check->bind_param("i", $professional_id);
$pricing_check->execute();
$pricing_result = $pricing_check->get_result();

if ($pricing_result->num_rows == 0) {
    // Insert default pricing based on their profession
    $default_price = 350;
    $default_unit = 'fixed';
    
    $insert_pricing = $conn->prepare("INSERT INTO professional_pricing 
        (professional_id, service_name, price, price_unit, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NOW(), NOW())");
    $insert_pricing->bind_param("isis", 
        $professional_id, 
        $professional['profession'], 
        $default_price, 
        $default_unit);
    
    if ($insert_pricing->execute()) {
        $_SESSION['success'] = "Default service pricing created successfully!";
    } else {
        $_SESSION['error'] = "Error creating default service pricing.";
    }
    
    $insert_pricing->close();
}

$pricing_check->close();

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_image'])) {
    $target_dir = "../professional/uploads/profiles/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Check if image file is an actual image
    $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
    if ($check !== false) {
        // Generate unique filename
        $imageFileType = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
        $new_filename = "prof_" . $professional_id . "_" . time() . "." . $imageFileType;
        $target_file = $target_dir . $new_filename;
        
        // Try to upload file
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            // Update database with just the filename (not full path)
            $update_query = "UPDATE professionals SET profile_image = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $new_filename, $professional_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Profile image updated successfully!";
                // Update the professional array to show the new image immediately
                $professional['profile_image'] = $new_filename;
            } else {
                $_SESSION['error'] = "Error updating profile image in database";
                // Delete the uploaded file if DB update fails
                if (file_exists($target_file)) {
                    unlink($target_file);
                }
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Error uploading profile image";
        }
    } else {
        $_SESSION['error'] = "File is not an image";
    }
    
    header("Location: professional-dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Dashboard | Sevak</title>
    <link rel="stylesheet" href="../CSS/proffessional.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="#" class="logo">Sevak</a>
            <div class="navbar-toggle" id="navbarToggle">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
            <ul class="navbar-menu" id="navbarMenu">
                <li><a href="../../html files/service.html" class="navbar-item">Services</a></li>
                <li><a href="../../html files/aboutus.html" class="navbar-item">How It Works</a></li>
                
                <li><a href="../contactus.php" class="navbar-item">Contact Us</a></li>
                <li><a href="../../html files/help.html" class="navbar-item">Help</a></li>
            </ul>
            <div class="navbar-buttons">
            <a href="../logout.php" class="btn btn-primary" id="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Professional Dashboard -->
    <section class="professional-dashboard">
        <div class="container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <div class="dashboard-header">
                <div class="professional-info">
                    <form method="post" enctype="multipart/form-data" id="profileImageForm">
                        <img src="<?php echo !empty($professional['profile_image']) ? '../professional/uploads/profiles/' . htmlspecialchars($professional['profile_image']) : '../professional/uploads/profiles/default_profile.png'; ?>" 
                            alt="Professional Profile Picture" 
                            class="profile-image"
                            id="profileImage"
                            onerror="this.onerror=null; this.src='../professional/uploads/profiles/default_profile.png'">
                        <div class="profile-image-overlay">
                        </div>
                    </form>
                    <div>
                        <h2><?= htmlspecialchars($professional['name']); ?></h2>
                        <p class="profession"><?= htmlspecialchars($professional['profession']); ?></p>
                        <p class="location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($professional['city'] . ', ' . $professional['state']); ?></p>
                    </div>
                </div>
                <div class="dashboard-actions">
                    <button class="btn btn-primary" onclick="location.href='../professional/editprofile.php'"><i class="fas fa-user-edit"></i> Edit Profile</button>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-content">
                        <h3><?= $total_bookings; ?></h3>
                        <p>Total Bookings</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
                    <div class="stat-content">
                        <h3>₹<?= number_format($total_earnings, 2); ?></h3>
                        <p>Total Earnings</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-content">
                        <h3><?= $average_rating; ?></h3>
                        <p>Rating</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-content">
                        <h3><?= $clients_served; ?></h3>
                        <p>Clients Served</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-tabs">
                <div class="tabs">
                    <button class="tab-btn active" data-tab="bookings">Booking Status</button>
                    <button class="tab-btn" data-tab="payments">Payments</button>
                    <button class="tab-btn" data-tab="schedule">Schedule</button>
                    <button class="tab-btn" data-tab="requests">Work Requests</button>
                    <button class="tab-btn" data-tab="history">History</button>
                </div>

                <div class="tab-content active" id="bookings">
                    <div class="content-header">
                        <h3>Upcoming Bookings</h3>
                    </div>
                    
                    <div class="booking-list">
                        <?php if (empty($upcoming_bookings)): ?>
                            <p>No upcoming bookings found.</p>
                        <?php else: ?>
                            <?php foreach ($upcoming_bookings as $booking): ?>
                                <div class="booking-card">
                                    <div class="booking-info">
                                        <h4><?= htmlspecialchars($booking['service_name']); ?></h4>
                                        <p class="booking-client"><i class="fas fa-user"></i> <?= htmlspecialchars($booking['client_name']); ?></p>
                                        <p class="booking-address"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($booking['address'] . ', ' . $booking['city'] . ', ' . $booking['state']); ?></p>
                                        <p class="booking-time"><i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($booking['date'])) . ', ' . $booking['preferred_time']; ?></p>
                                    </div>
                                    <div class="booking-status">
                                        <span class="status upcoming">Upcoming</span>
                                        <div class="booking-actions">
                                            <button class="btn-text" onclick="completeBooking(<?= $booking['id']; ?>, '<?= $booking['date']; ?>')">
                                                <i class="fas fa-check-circle"></i> Complete
                                            </button>
                                            <button class="btn-text" onclick="cancelBooking(<?= $booking['id']; ?>)">
                                                <i class="fas fa-times-circle"></i> Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tab-content" id="payments">
                    <div class="content-header">
                        <h3>Payment History</h3>
                        <div class="filter">
                            <select id="paymentFilter">
                                <option>Last 30 days</option>
                                <option>This Month</option>
                                <option>Last Month</option>
                                <option>All Time</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="payment-stats">
                        <div class="payment-stat">
                            <h4>₹<?= number_format(array_reduce($payments, function($carry, $payment) {
                                return (date('m Y', strtotime($payment['date'])) == date('m Y')) ? $carry + $payment['payment'] : $carry;
                            }, 0), 2); ?></h4>
                            <p>This Month</p>
                        </div>
                        <div class="payment-stat">
                            <h4>₹<?= number_format($total_earnings, 2); ?></h4>
                            <p>Total Earned</p>
                        </div>
                    </div>
                    
                    <div class="payment-list">
                        <?php if (empty($payments)): ?>
                            <p>No payment history found.</p>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <div class="payment-card">
                                    <div class="payment-info">
                                        <h4><?= htmlspecialchars($payment['service_name']); ?></h4>
                                        <p><i class="fas fa-user"></i> <?= htmlspecialchars($payment['client_name']); ?></p>
                                        <p><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($payment['date'])); ?></p>
                                    </div>
                                    <div class="payment-amount">
                                        <h4>₹<?= number_format($payment['payment'], 2); ?></h4>
                                        <span class="status completed">Paid</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tab-content" id="schedule">
                    <div class="content-header">
                        <h3>Weekly Schedule</h3>
                        <div class="week-navigator">
                            <button><i class="fas fa-chevron-left"></i></button>
                            <h4><?= date('M j', strtotime('monday this week')) . ' - ' . date('M j, Y', strtotime('sunday this week')); ?></h4>
                            <button><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                    
                    <div class="schedule-calendar">
                        <div class="calendar-header">
                            <div>Monday</div>
                            <div>Tuesday</div>
                            <div>Wednesday</div>
                            <div>Thursday</div>
                            <div>Friday</div>
                            <div>Saturday</div>
                            <div>Sunday</div>
                        </div>
                        
                        <div class="calendar-body">
                            <?php
                            // Get all bookings for the current week
                            $monday = date('Y-m-d', strtotime('monday this week'));
                            $sunday = date('Y-m-d', strtotime('sunday this week'));
                            
                            $weekly_stmt = $conn->prepare("SELECT * FROM requests WHERE professional_id = ? AND status = 'accepted' AND (complete = 'upcoming' ) AND date BETWEEN ? AND ? ORDER BY date, preferred_time");
                            $weekly_stmt->bind_param("iss", $professional_id, $monday, $sunday);
                            $weekly_stmt->execute();
                            $weekly_result = $weekly_stmt->get_result();
                            $weekly_bookings = $weekly_result->fetch_all(MYSQLI_ASSOC);
                            $weekly_stmt->close();
                            
                            // Group bookings by day
                            $bookings_by_day = [];
                            foreach ($weekly_bookings as $booking) {
                                $day = date('j', strtotime($booking['date']));
                                $bookings_by_day[$day][] = $booking;
                            }
                            
                            // Generate calendar days
                            for ($i = 0; $i < 7; $i++):
                                $current_date = date('Y-m-d', strtotime("+$i days", strtotime($monday)));
                                $current_day = date('j', strtotime($current_date));
                                $is_today = (date('Y-m-d') == $current_date);
                                $day_name = strtolower(date('l', strtotime($current_date)));
                            ?>
                            <div class="calendar-day <?= $is_today ? 'today' : ''; ?>">
                                <div class="day-number"><?= $current_day; ?></div>
                                <div class="day-events">
                                    <?php if (isset($bookings_by_day[$current_day])): ?>
                                        <?php foreach ($bookings_by_day[$current_day] as $event): ?>
                                            <div class="event">
                                                <div class="event-time"><?= $event['preferred_time']; ?></div>
                                                <div class="event-title"><?= htmlspecialchars($event['service_name']); ?></div>
                                                <div class="event-location"><?= htmlspecialchars($event['city']); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php elseif (!$availability[$day_name]): ?>
                                        <div class="event off-day">Day Off</div>
                                    <?php else: ?>
                                        <div class="event no-events">No bookings</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="availability-settings">
                        <h4>Your Availability</h4>
                        <form method="post" id="availabilityForm">
                            <div class="availability-toggles">
                                <div class="availability-day">
                                    <span>Monday</span>
                                    <label class="switch">
                                        <input type="checkbox" name="monday" <?= $availability['monday'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                <div class="availability-day">
                                    <span>Tuesday</span>
                                    <label class="switch">
                                        <input type="checkbox" name="tuesday" <?= $availability['tuesday'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                <div class="availability-day">
                                    <span>Wednesday</span>
                                    <label class="switch">
                                        <input type="checkbox" name="wednesday" <?= $availability['wednesday'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                <div class="availability-day">
                                    <span>Thursday</span>
                                    <label class="switch">
                                        <input type="checkbox" name="thursday" <?= $availability['thursday'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                <div class="availability-day">
                                    <span>Friday</span>
                                    <label class="switch">
                                        <input type="checkbox" name="friday" <?= $availability['friday'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                <div class="availability-day">
                                    <span>Saturday</span>
                                    <label class="switch">
                                        <input type="checkbox" name="saturday" <?= $availability['saturday'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                <div class="availability-day">
                                    <span>Sunday</span>
                                    <label class="switch">
                                        <input type="checkbox" name="sunday" <?= $availability['sunday'] ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                            </div>
                            <input type="hidden" name="update_availability" value="1">
                            <button type="submit" class="btn btn-primary">Update Availability</button>
                        </form>
                    </div>
                </div>

                <div class="tab-content" id="requests">
                    <div class="content-header">
                        <h3>New Work Requests</h3>
                    </div>
                    
                    <div class="work-requests">
                        <?php if (empty($requests)): ?>
                            <p>No new work requests found.</p>
                        <?php else: ?>
                            <?php foreach ($requests as $request): ?>
                                <div class="request-card">
                                    <div class="request-info">
                                        <h4><?= htmlspecialchars($request['service_name']); ?></h4>
                                        <p class="request-client"><i class="fas fa-user"></i> <?= htmlspecialchars($request['client_name']); ?></p>
                                        <p class="request-address"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($request['address'] . ', ' . $request['city'] . ', ' . $request['state']); ?></p>
                                        <p class="request-time"><i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($request['date'])) . ', ' . $request['preferred_time']; ?></p>
                                        <p class="request-details"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($request['details']); ?></p>
                                        <p class="request-budget"><i class="fas fa-rupee-sign"></i> Budget: ₹<?= number_format($request['payment'], 2); ?></p>
                                    </div>
                                    <div class="request-actions">
                                        <form method="post" class="request-form">
                                            <input type="hidden" name="request_id" value="<?= $request['id']; ?>">
                                            <input type="hidden" name="action" value="accept">
                                            <button type="submit" class="btn btn-primary">Accept</button>
                                            <button type="button" class="btn btn-outline decline-btn" data-request-id="<?= $request['id']; ?>">Decline</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tab-content" id="history">
                    <div class="content-header">
                        <h3>History</h3>
                    </div>
                    <div class="booking-list">
                        <?php if (empty($history)): ?>
                            <p>No history found.</p>
                        <?php else: ?>
                            <?php foreach ($history as $job): ?>
                                <div class="booking-card">
                                    <div class="booking-info">
                                        <h4><?= htmlspecialchars($job['service_name']); ?></h4>
                                        <p class="booking-client"><i class="fas fa-user"></i> <?= htmlspecialchars($job['client_name']); ?></p>
                                        <p class="booking-address"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($job['address'] . ', ' . $job['city'] . ', ' . $job['state']); ?></p>
                                        <p class="booking-time"><i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($job['date'])) . ', ' . $job['preferred_time']; ?></p>
                                        <?php if ($job['complete'] == 'complete' && isset($job['rating'])): ?>
                                            <p class="Rating"><i class="fas fa-star"></i> <?= $job['rating']; ?>/5</p>
                                            <?php if (!empty($job['review'])): ?>
                                                <p class="Review"><i class="fas fa-comment-alt"></i> "<?= htmlspecialchars($job['review']); ?>"</p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="booking-status">
                                        <?php if ($job['complete'] == 'complete'): ?>
                                            <span class="status completed">Completed</span>
                                            <p class="payment-info">Payment: ₹<?= number_format($job['payment'], 2); ?> (Received)</p>
                                        <?php else: ?>
                                            <span class="status cancelled">Cancelled</span>
                                            <p class="cancellation-reason">Reason: <?= htmlspecialchars($job['reason'] ?? 'Not specified'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <h3>Sevak</h3>
                    <p>Connecting skilled professionals with customers for home services, repairs, and more.</p>
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
                        <li><a href="../../html files/reviews_rate.html">Services</a></li>
                        <!-- <li><a href="">How it Works</a></li> -->
                        <li><a href="#">Pricing</a></li>
                        <li><a href="#">FAQs</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>For Professionals</h4>
                    <ul>
                        <li><a href="../register.php">Join as Professional</a></li>
                        <li><a href="#">Professional Dashboard</a></li>
                        <li><a href="#">Success Stories</a></li>
                        <li><a href="#">Professional Terms</a></li>
                        <li><a href="#">Earning Calculator</a></li>
                    </ul>
                </div>
                
                <div class="footer-contact">
                    <h4>Contact Us</h4>
                    <ul>
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <p>Banasthali Vidyapith<br>Tonk, Rajasthan ,304022, India</p>
                            </div>
                        </li>
                        <li>
                            <i class="fas fa-phone-alt"></i>
                            <div>
                                <p>+91 98765 43210</p>
                                <p>+91 22 2345 6789</p>
                            </div>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <div>
                                <p>info@sevak.com</p>
                                <p>support@sevak.com</p>
                            </div>
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
    
    <!-- Decline Reason Modal -->
    <div id="declineModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Reason for Declining</h3>
            <form id="declineForm" method="post">
                <input type="hidden" name="request_id" id="modalRequestId">
                <input type="hidden" name="action" value="decline">
                <textarea name="reason" placeholder="Please specify the reason for declining this request..." required></textarea>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab navigation functionality
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons and contents
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Show corresponding content
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
        // Profile image upload handling
        document.getElementById('profileImageUpload').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                // Check file size (max 2MB)
                if (this.files[0].size > 2 * 1024 * 1024) {
                    alert('Image size should be less than 2MB');
                    return;
                }
                
                // Check file type
                const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!validTypes.includes(this.files[0].type)) {
                    alert('Only JPG, PNG and GIF images are allowed');
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profileImage').src = e.target.result;
                }
                reader.readAsDataURL(this.files[0]);
                
                // Submit the form
                document.getElementById('profileImageForm').submit();
            }
        });
        // Replace the availability update JavaScript with this:
        document.getElementById('availabilityForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('professional-dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating availability');
            });
        });
        
        // Payment filter functionality - UPDATED VERSION
        const paymentFilter = document.getElementById('paymentFilter');
        const paymentCards = document.querySelectorAll('.payment-card');

        paymentFilter.addEventListener('change', function() {
            const filterValue = this.value;
            const today = new Date();
            
            paymentCards.forEach(card => {
                const paymentDateStr = card.querySelector('p:nth-child(3)').textContent.replace(' ', ''); // Get date text
                const paymentDate = new Date(paymentDateStr);
                let shouldShow = true;
                
                switch(filterValue) {
                    case 'Last 30 days':
                        const thirtyDaysAgo = new Date();
                        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                        shouldShow = paymentDate >= thirtyDaysAgo;
                        break;
                        
                    case 'This Month':
                        const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                        const lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                        shouldShow = paymentDate >= firstDayOfMonth && paymentDate <= lastDayOfMonth;
                        break;
                        
                    case 'Last Month':
                        const firstDayLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        const lastDayLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
                        shouldShow = paymentDate >= firstDayLastMonth && paymentDate <= lastDayLastMonth;
                        break;
                        
                    case 'All Time':
                        // Show all
                        break;
                }
                
                card.style.display = shouldShow ? 'flex' : 'none';
            });
        });
                
    // Function to complete a booking
    window.completeBooking = function(bookingId, bookingDate) {
        const today = new Date().toISOString().split('T')[0];
        const bookingDateObj = new Date(bookingDate);
        const todayObj = new Date(today);
        
        if (bookingDateObj > todayObj) {
            alert("You cannot complete a booking that is scheduled for a future date.");
            return;
        }

        if (confirm("Are you sure you want to mark this booking as completed?")) {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            button.disabled = true;

            fetch('../professional/complete_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=${encodeURIComponent(bookingId)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('Booking completed successfully!', 'success');
                    // Force page reload after 1.5 seconds
                    setTimeout(() => {
                        window.location.reload(true);
                    }, 15);
                } else {
                    throw new Error(data.message || 'Failed to complete booking');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification(error.message, 'error');
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
    };

        // Function to cancel a booking
        window.cancelBooking = function(bookingId) {
            const reason = prompt("Please enter the reason for cancellation:");
            if (reason !== null && reason.trim() !== '') {
                fetch('../professional/cancel_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        booking_id: bookingId,
                        reason: reason
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Booking cancelled successfully!');
                        location.reload();
                    } else {
                        alert('Error cancelling booking: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while cancelling the booking');
                });
            }
        };
    });

    // Decline Request Modal Handling
    const declineModal = document.getElementById('declineModal');
    const declineBtns = document.querySelectorAll('.decline-btn');
    const closeModal = document.querySelector('.close-modal');
    const declineForm = document.getElementById('declineForm');
    const modalRequestId = document.getElementById('modalRequestId');

    // Open modal when decline button is clicked
    declineBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const requestId = this.getAttribute('data-request-id');
            modalRequestId.value = requestId;
            declineModal.style.display = 'block';
        });
    });

    // Close modal when X is clicked
    closeModal.addEventListener('click', function() {
        declineModal.style.display = 'none';
    });

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target == declineModal) {
            declineModal.style.display = 'none';
        }
    });

    // Handle form submission
    declineForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('professional-dashboard.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.redirected) {
                window.location.href = response.url;
            } else {
                return response.text();
            }
        })
        .then(data => {
            if (data) {
                // Handle response if needed
                declineModal.style.display = 'none';
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while declining the request');
        });
    });

    // Logout confirmation
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                // Clear browser cache and prevent back navigation
                fetch('../logout.php', {
                    method: 'POST',
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (response.redirected) {
                        // Force a hard redirect to prevent back button access
                        window.location.replace(response.url);
                    } else {
                        window.location.href = '../index.php';
                    }
                })
                .catch(error => {
                    console.error('Logout error:', error);
                    window.location.href = '../index.php';
                });
            }
        });
    }

    // Check if session is valid
    function checkSession() {
        fetch('../includes/check_session.php', {
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (!data.valid) {
                window.location.replace('../index.php');
            }
        })
        .catch(error => {
            console.error('Session check error:', error);
        });
    }

    // Check session periodically (every 5 minutes)
    setInterval(checkSession, 300000);

    // Also check on page load
    document.addEventListener('DOMContentLoaded', checkSession);

    </script>
</body>
</html>