<?php
// Start session and include database connection
session_start();
require_once('../includes/config.php');

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once('../includes/config.php');

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: ../index.php');
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: ../clients/clients-dashboard.php');
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

// Get client bookings from requests table (last 2 months)
$bookings_query = "SELECT 
    r.id,
    r.professional_name,
    r.client_name,
    r.address,
    r.city,
    r.state,
    r.preferred_time,
    r.payment,
    r.status,
    r.complete,
    r.details,
    r.profession,
    r.service_name,
    r.date,
    r.reason,
    r.created_at,
    r.updated_at
FROM requests r
WHERE r.client_id = ? 
AND r.status = 'accepted' 
AND r.date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
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

// Get payment history from payments table (last 6 months)
$payments_query = "SELECT 
    p.*,
    pr.name as professional_name,
    pr.contact as professional_contact
FROM payments p
JOIN professionals pr ON p.professional_id = pr.id
WHERE p.client_id = ?
AND p.status = 'paid' 
AND p.date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
ORDER BY p.date DESC";

$stmt = $conn->prepare($payments_query);
if (!$stmt) {
    die("Error preparing payments query: " . $conn->error);
}
$stmt->bind_param("i", $client_id);
if (!$stmt->execute()) {
    die("Error executing payments query: " . $stmt->error);
}
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get service requests from requests table (last 2 months)
$requests_query = "SELECT 
    r.id,
    r.professional_name,
    r.client_name,
    r.address,
    r.city,
    r.state,
    r.preferred_time,
    r.payment,
    r.status,
    r.complete,
    r.details,
    r.profession,
    r.service_name,
    r.date,
    r.reason,  
    r.created_at,
    r.updated_at
FROM requests r
WHERE r.client_id = ?
AND (
    (r.status = 'pending' AND r.complete = 'requested') 
    OR 
    (r.status = 'declined' AND r.complete = 'cancelled')
)
AND r.date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
ORDER BY r.date DESC";

$stmt = $conn->prepare($requests_query);
if (!$stmt) {
    die("Error preparing requests query: " . $conn->error);
}
$stmt->bind_param("i", $client_id);
if (!$stmt->execute()) {
    die("Error executing requests query: " . $stmt->error);
}
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all services for the services section
$services_query = "SELECT * FROM services ";
$services_result = $conn->query($services_query);
$services = $services_result->fetch_all(MYSQLI_ASSOC);

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profileImage'])) {
    $target_dir = "../Users/uploads/profile_images/";
    $target_file = $target_dir . basename($_FILES["profileImage"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is a actual image
    $check = getimagesize($_FILES["profileImage"]["tmp_name"]);
    if ($check !== false) {
        // Generate unique filename
        $new_filename = "profile_" . $client_id . "." . $imageFileType;
        $target_file = $target_dir . $new_filename;
        
        // Try to upload file
        if (move_uploaded_file($_FILES["profileImage"]["tmp_name"], $target_file)) {
            // Update database with new image path
            $update_query = "UPDATE clients SET profile_image = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $image_path = "uploads/profile_images/" . $new_filename;
            $stmt->bind_param("si", $image_path, $client_id);
            $stmt->execute();
            
            // Update client data
            $client['profile_image'] = $image_path;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Service_Requestor Page | Sevak</title>
    <link rel="stylesheet" href="../css/clients.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .service-card img{
                width:auto;
                height:200px;
                        }

        .status.requested {
        background-color: #ffc107;
        color: #000;
        padding: 0.25rem 0.5rem;
        margin-bottom:0.2rem;

    }

    .status.cancelled {
        background-color: #dc3545;
        color: #fff;
    }
    .work-requests{
        padding:2rem;
    }
    .request-info h4 {
        font-weight: 600;
        margin-bottom: 0.5rem;
        }

    .cancellation-reason {
        margin-top: 10px;
        padding: 8px;
        background-color: #f8f9fa;
        border-left: 3px solid #dc3545;
        font-size: 0.9em;
        color: #6c757d;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #6c757d;
    }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="" class="logo">Sevak</a>
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
    
    <!--user name & pic-->
    <section class="user-dashboard">
        <div class="container">
            <div class="dashboard-header">
                <div class="user-info">
                    <form method="post" enctype="multipart/form-data" id="profileImageForm">
                    <img src="<?php echo !empty($client['profile_image']) ? '../clients/uploads/' . $client['profile_image'] : '../uploads/default_profille.png'; ?>" 
                        alt="Profile" class="profile-image" id="profileImage"
                        style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">

                        <input type="file" id="profileImageUpload" name="profileImage" accept="image/*" hidden>
                        <h3><?php echo htmlspecialchars($client['name'] ) ?></h3>
                      <!-- <p class="location">
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php echo htmlspecialchars($client['city']); ?> 
                            <?php echo htmlspecialchars($client['state']); ?>
                        </p>     -->                   
                            <div class="profile-image-overlay">
                            <label for="profileImageUpload" class="profile-image-edit">
                                <i class="fas fa-camera"></i>
                            </label>
                        </div>
                    </form>
                </div>

                <div class="dashboard-actions">
                    <button class="btn btn-primary" onclick="location.href='../clients/editprofile_client.php'">
                        <i class="fas fa-user-edit"></i> Edit Profile
                    </button>
                </div>
            </div>
        </div>
    </section>
    
    <!--services-->
    <div class="idi">
    <div class="sliding-text"> 
        <h1>Our Trending Services</h1>
    </div>
    </div>
    <!-- In your services section -->
    <div class="inline">
        <main id="service-list">
            <?php foreach ($services as $service): ?>
                <div class="service-card">
                    <h2><?php echo htmlspecialchars($service['service_name']); ?>
                    </h2>
                    <img src="../uploads/<?php echo htmlspecialchars($service['service_image']); ?>">
                    <p><?php echo htmlspecialchars($service['description']); ?></p>
                    <button class="btn btn-primary" onclick="location.href='../clients/professional_lists.php?service_id=<?php echo $service['service_id']; ?>'">
                        Book here
                    </button>
                </div>
            <?php endforeach; ?>
        </main>
    </div>
    
    <div class="dashboard-tabs">
        <div class="tabs">
            <button class="tab-btn active" data-tab="bookings">My Bookings</button>
            <button class="tab-btn" data-tab="payments">Payments</button>
            <button class="tab-btn" data-tab="requests">Requests for Services</button>
        </div>
    </div>    
        <!--my bookings-->
        <div class="tab-content active" id="bookings">
            <div class="content-header">
                <h3>Recent Bookings </h3>
                <div class="filter">
                <select id="bookingFilter">
                    <option value="all">All Statuses</option>
                    <option value="completed">Completed</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                </div>
            </div>
            
            <div class="booking-list">
                <?php foreach ($bookings as $booking): 
                    // Determine the display status based on conditions
                    $display_status = '';
                    $status_class = '';
                    $show_cancel = false;
                    $show_reason = false;
                    $reason_text = '';
                    
                    if ($booking['status'] == 'accepted' && $booking['complete'] == 'upcoming') {
                        $display_status = 'upcoming';
                        $status_class = 'upcoming';
                        $show_cancel = true;
                    } elseif ($booking['status'] == 'accepted' && $booking['complete'] == 'complete') {
                        $display_status = 'completed';
                        $status_class = 'completed';
                    } elseif ($booking['status'] == 'accepted' || $booking['complete'] == 'cancelled') {
                        $display_status = 'cancelled';
                        $status_class = 'cancelled';
                        $show_reason = true;
                        $reason_text = isset($booking['reason']) ? $booking['reason'] : 'No reason provided';
                    }
                    
                    // Only display if we have a valid status
                    if (!empty($display_status)):
                ?>
                    <div class="booking-card" data-status="<?php echo $display_status; ?>">
                        <div class="booking-info">
                            <h4><?php echo htmlspecialchars($booking['profession']); ?></h4>
                            <h4><?php echo htmlspecialchars($booking['service_name']); ?></h4>
                            <p class="booking-client">
                                <i class="fas fa-user"></i> 
                                <?php echo htmlspecialchars($booking['professional_name']); ?>
                            </p>
                            <p class="booking-address">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($booking['address']); ?>,
                                <?php echo htmlspecialchars($booking['city']); ?>,
                                <?php echo htmlspecialchars($booking['state']); ?>
                            </p>
                            <p class="booking-time">
                                <i class="fas fa-clock"></i> 
                                <?php echo date('F j, Y', strtotime($booking['date'])); ?>
                                <?= date('g:i A', strtotime($booking['preferred_time'])); ?>
                            </p>
                        </div>
                        <div class="booking-status">
                            <span class="status <?php echo $status_class; ?>">
                                <?php echo $display_status; ?>
                            </span>
                            <div class="booking-actions">
                                <?php if ($show_cancel): ?>
                                    <button class="btn-text cancel-booking" data-booking-id="<?php echo $booking['id']; ?>">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                <?php elseif ($show_reason): ?>
                                    <div class="cancellation-reason">
                                        <i class="fas fa-info-circle"></i>
                                        <span class="reason-text" title="<?php echo htmlspecialchars($reason_text); ?>">
                                            <?php echo htmlspecialchars($reason_text); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; endforeach; ?>
                
                <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <p>No bookings found for the last 2 months</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!--payments-->
        <div class="tab-content" id="payments">
            <div class="content-header">
                <h3>Payment History </h3>
                <div class="filter">
                    <select id="paymentFilter">
                        <option value="30">Last 30 days</option>
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="6">All Time (6 Months)</option>
                    </select>
                </div>
            </div>

            <div class="payment-list">
                <?php foreach ($payments as $payment): ?>
                    <div class="payment-card" 
                         data-date="<?php echo date('Y-m-d', strtotime($payment['date'])); ?>"
                         data-month="<?php echo date('Y-m', strtotime($payment['date'])); ?>">
                        <div class="payment-info">
                            <h4><?php echo htmlspecialchars($payment['profession']); ?></h4>
                            <h4><?php echo htmlspecialchars($payment['service_name']); ?></h4>
                            <p class="payment-professional">
                                <i class="fas fa-user"></i> 
                                <?php echo htmlspecialchars($payment['professional_name']); ?>
                            </p>
                            <p class="payment-date">
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('F j, Y', strtotime($payment['date'])); ?>
                            </p>
                        </div>
                        <div class="payment-amount">
                            <h4>₹<?php echo number_format($payment['payment'], 2); ?></h4>
                            <span class="status <?php echo strtolower($payment['status']); ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($payments)): ?>
                    <div class="empty-state">
                        <p>No payment history found for the last 6 months</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!--requests-->
        <div class="tab-content" id="requests">
            <div class="content-header">
                <h3>Requests Status </h3>
            </div>
            
            <div class="work-requests">
                <?php foreach ($requests as $request): ?>
                    <div class="booking-list">
                        <div class="booking-card">
                            <div class="request-info">
                                <h4><?php echo htmlspecialchars($request['profession']); ?></h4>
                                <h4><?php echo htmlspecialchars($request['service_name']); ?></h4>
                                <p class="request-professional">
                                    <i class="fas fa-user"></i> 
                                    <?php echo htmlspecialchars($request['professional_name']); ?>
                                </p>
                                <p class="request-address">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?php echo htmlspecialchars($request['address']); ?>,
                                    <?php echo htmlspecialchars($request['city']); ?>,
                                    <?php echo htmlspecialchars($request['state']); ?>
                                </p>
                                <p class="request-time">
                                    <i class="fas fa-clock"></i> 
                                    <?php echo date('F j, Y', strtotime($request['date'])); ?> 
                                    <?= date('g:i A', strtotime($request['preferred_time'])); ?>
                                </p>
                                <p class="request-budget">
                                    <i class="fas fa-rupee-sign"></i> 
                                    Budget: ₹<?php echo number_format($request['payment'], 2); ?>
                                </p>
                                
                                <!-- Status display with different styling based on status -->
                                <?php if ($request['status'] == 'pending'): ?>
                                    <span class="status requested">Requested</span>
                                <?php elseif ($request['status'] == 'declined'): ?>
                                    <span class="status cancelled">Declined</span>
                                    <?php if (!empty($request['reason'])): ?>
                                        <p class="cancellation-reason">
                                            <i class="fas fa-info-circle"></i>
                                            Reason: <?php echo htmlspecialchars($request['reason']); ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <p>No service requests found for the last 2 months</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    
    
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
                        <li><a href="../../html files/service.html">Services</a></li>
                        <li><a href="#">How it Works</a></li>
                        <li><a href="#">Pricing</a></li>
                        <li><a href="#">FAQs</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>For Service_Requestors</h4>
                    <ul>
                        <p>join us on sevak<br>for a reliable and<br>hygenic services for<br>your homes and offices.</p>
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

    <script src="../js/script.js"></script>
    <script>
     //sliding-text
     let text = document.querySelector(".sliding-text");
        let position = 0;
        let direction = 1;

        function slideText() {
            position += 2 * direction;
            text.style.left = position + "px";

            if (position >= 250 || position <= 0) {
                direction *= -1; // Reverse direction
            }

            requestAnimationFrame(slideText);
        }

        slideText();

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
        
        // Profile image upload
        const profileImageUpload = document.getElementById('profileImageUpload');
        const profileImageForm = document.getElementById('profileImageForm');
        
        profileImageUpload.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                profileImageForm.submit();
            }
        });
        
        // Filter functionality for bookings
        const bookingFilter = document.getElementById('bookingFilter');
        bookingFilter.addEventListener('change', function() {
            const status = this.value;
            const bookingCards = document.querySelectorAll('#bookings .booking-card');
            
            bookingCards.forEach(card => {
                if (status === 'all' || card.getAttribute('data-status') === status) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show empty state if no matching bookings
            const visibleBookings = document.querySelectorAll('#bookings .booking-card[style="display: flex;"]');
            const emptyState = document.querySelector('#bookings .empty-state');
            
            if (visibleBookings.length === 0) {
                if (!emptyState) {
                    const emptyDiv = document.createElement('div');
                    emptyDiv.className = 'empty-state';
                    emptyDiv.innerHTML = '<p>No bookings match your filter</p>';
                    document.querySelector('.booking-list').appendChild(emptyDiv);
                }
            } else if (emptyState) {
                emptyState.remove();
            }
        });
        
        // Filter functionality for payments
        const paymentFilter = document.getElementById('paymentFilter');
        paymentFilter.addEventListener('change', function() {
            const period = this.value;
            const paymentCards = document.querySelectorAll('#payments .payment-card');
            const now = new Date();
            
            paymentCards.forEach(card => {
                const paymentDate = new Date(card.getAttribute('data-date'));
                const paymentMonth = card.getAttribute('data-month');
                const currentMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
                const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                const lastMonthStr = lastMonth.getFullYear() + '-' + String(lastMonth.getMonth() + 1).padStart(2, '0');
                
                let showCard = false;
                
                switch(period) {
                    case '30':
                        // Last 30 days
                        const thirtyDaysAgo = new Date();
                        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                        showCard = paymentDate >= thirtyDaysAgo;
                        break;
                    case 'this_month':
                        // This month
                        showCard = paymentMonth === currentMonth;
                        break;
                    case 'last_month':
                        // Last month
                        showCard = paymentMonth === lastMonthStr;
                        break;
                    case '6':
                        // All (6 months) - already filtered by server
                        showCard = true;
                        break;
                    default:
                        showCard = true;
                }
                
                card.style.display = showCard ? 'flex' : 'none';
            });
            
            // Show empty state if no matching payments
            const visiblePayments = document.querySelectorAll('#payments .payment-card[style="display: flex;"]');
            const emptyState = document.querySelector('#payments .empty-state');
            
            if (visiblePayments.length === 0) {
                if (!emptyState) {
                    const emptyDiv = document.createElement('div');
                    emptyDiv.className = 'empty-state';
                    emptyDiv.innerHTML = '<p>No payments match your filter</p>';
                    document.querySelector('.payment-list').appendChild(emptyDiv);
                }
            } else if (emptyState) {
                emptyState.remove();
            }
        });
    });

    // Cancel booking with reason dialog
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('cancel-booking') || 
            e.target.closest('.cancel-booking')) {
            e.preventDefault();
            const button = e.target.classList.contains('cancel-booking') ? 
                        e.target : e.target.closest('.cancel-booking');
            const bookingId = button.getAttribute('data-booking-id');
            
            // Create cancellation reason dialog
            const dialog = document.createElement('div');
            dialog.className = 'cancellation-dialog';
            dialog.innerHTML = `
                <div class="dialog-content">
                    <h3>Cancel Booking</h3>
                    <p>Please provide a reason for cancellation:</p>
                    <textarea id="cancellationReason" rows="4" placeholder="Enter your reason here..." required></textarea>
                    <div class="dialog-buttons">
                        <button class="btn btn-secondary cancel-dialog">Cancel</button>
                        <button class="btn btn-primary confirm-cancellation">Submit</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(dialog);
            document.body.style.overflow = 'hidden';
            
            const cancelBtn = dialog.querySelector('.cancel-dialog');
            const confirmBtn = dialog.querySelector('.confirm-cancellation');
            
            cancelBtn.addEventListener('click', () => {
                document.body.removeChild(dialog);
                document.body.style.overflow = '';
            });
            
            confirmBtn.addEventListener('click', () => {
                const reason = document.getElementById('cancellationReason').value.trim();
                if (reason === '') {
                    alert('Please provide a cancellation reason');
                    return;
                }
                
                document.body.removeChild(dialog);
                document.body.style.overflow = '';
                
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
                button.disabled = true;
                
                fetch('../clients/cancel_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `booking_id=${bookingId}&reason=${encodeURIComponent(reason)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload the bookings section
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        button.innerHTML = '<i class="fas fa-times"></i> Cancel';
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while cancelling the booking');
                    button.innerHTML = '<i class="fas fa-times"></i> Cancel';
                    button.disabled = false;
                });
            });
        }
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