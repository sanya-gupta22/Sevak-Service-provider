<?php
// Start session and include database connection
session_start();
require_once('../includes/config.php');

// Check if user is logged in 
if (!isset($_SESSION['client_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get the service ID from the URL
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;

// First, get the service name from the services table
$service_query = "SELECT service_name FROM services WHERE service_id = ?";
$stmt = $conn->prepare($service_query);
$stmt->bind_param("i", $service_id);
$stmt->execute();
$service_result = $stmt->get_result();
$service = $service_result->fetch_assoc();

if (!$service) {
    die("Service not found");
}

// Get location from search input if provided
$location = isset($_GET['location']) ? trim($_GET['location']) : '';

// Base query to fetch professionals
$query = "SELECT 
            id, name, profession, contact, city, state, profile_image, rating
          FROM professionals 
          WHERE account = 'active' 
          AND profession = ?";

// Add parameters - start with service name
$params = [$service['service_name']];
$types = "s";

// Add location filter if provided
if (!empty($location)) {
    $query .= " AND (city LIKE ? OR state LIKE ?)";
    $location_param = "%$location%";
    $params[] = $location_param;
    $params[] = $location_param;
    $types .= "ss";
}

$query .= " ORDER BY rating DESC, name ASC";

$stmt = $conn->prepare($query);

// Bind parameters dynamically
if (!empty($location)) {
    $stmt->bind_param($types, $service['service_name'], $location_param, $location_param);
} else {
    $stmt->bind_param($types, $service['service_name']);
}

$stmt->execute();
$result = $stmt->get_result();
$professionals = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sevak - Professionals for <?php echo htmlspecialchars($service['service_name']); ?></title>
    
    <link rel="stylesheet" href="../css/professional_lists.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
   
    <!-- <div class="am"> 
        <div class="for"> -->
            <div class="location">  
                <form method="GET" action="">
                    <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">
                    
                    <input type="text" id="search-input" name="location" class="search-input" 
                           placeholder="Search by city or state" value="<?php echo htmlspecialchars($location); ?>">
                    <button type="submit" class="search-button">Search</button>
                </form>
            </div>
            
            <!-- <div class="service-header">
                <h1>Available <?php echo htmlspecialchars($service['service_name']); ?> Professionals</h1>
                <?php if (!empty($location)): ?>
                    <p>Showing results in <?php echo htmlspecialchars($location); ?></p>
                <?php endif; ?>
            </div> -->
            <div class="booking-list">
            <div class="prof-list">
                <?php if (count($professionals) > 0): ?>
                    <?php foreach ($professionals as $pro): ?>
                        <div class="booking-card" data-location="<?php echo htmlspecialchars($pro['city']); ?>">
                            <div class="booking-info">
                                <div class="pro-details">
                                    <h2><?php echo htmlspecialchars($pro['name']); ?></h2>
                                    <p class="profession"><?php echo htmlspecialchars($pro['profession']); ?></p>
                                    
                                    <p class="contact">
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($pro['contact']); ?>
                                    </p>
                                    <p class="address">Location: <?php echo htmlspecialchars($pro['city'] . ', ' . $pro['state']); ?></p>
                                    <!-- Star rating display -->
                                    <div class="rating-display">
                                        <?php 
                                        $rating = round($pro['rating']);
                                        for ($i = 1; $i <= 5; $i++): 
                                            if ($i <= $rating): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif;
                                        endfor; 
                                        ?>
                                        <span>(<?php echo number_format($pro['rating'], 1); ?>)</span>
                                    </div>
                                    
                                    <div class="pro-actions">
                                        <button class="btn btn-primary" onclick="location.href='../clients/professionalprofile_client.php?id=<?php echo $pro['id']; ?>'">Profile</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-results">
                        <p>No <?php echo htmlspecialchars($service['service_name']); ?> professionals found<?php echo !empty($location) ? ' in ' . htmlspecialchars($location) : ''; ?></p>
                        <?php if (!empty($location)): ?>
                            <a href="?service_id=<?php echo $service_id; ?>" class="btn btn-primary">Show All Professionals</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>  
        <!-- </div>
    </div> -->

    <script>
    // Mobile menu toggle functionality
    document.getElementById('navbarToggle').addEventListener('click', function() {
        document.getElementById('navbarMenu').classList.toggle('active');
    });

    // Optional: Client-side filtering (complementary to server-side filtering)
    document.getElementById('search-input').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const cards = document.querySelectorAll('.booking-card');
        
        cards.forEach(card => {
            const location = card.getAttribute('data-location').toLowerCase();
            if (location.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
    //to hide 
    window.addEventListener("scroll", function() {
    let locationDiv = document.querySelector(".location");
    let bookingCards = document.querySelectorAll(".booking-card");

    bookingCards.forEach(card => {
        let cardRect = card.getBoundingClientRect();
        let locationRect = locationDiv.getBoundingClientRect();

        // If the card moves into the background of the location div, hide it
        if (cardRect.top <= locationRect.bottom) {
            card.style.opacity = "0"; // Makes the card invisible
        } else {
            card.style.opacity = "1"; // Restores visibility when not behind
        }
    });
    })
        </script>
</body>
</html>