<?php
session_start();
require_once '../includes/config.php';

// Check if professional ID is provided in URL
if (!isset($_GET['id'])) {
    header("Location: ../index.php");
    exit();
}

$professional_id = $_GET['id'];

// Fetch professional data
$professional_stmt = $conn->prepare("SELECT * FROM professionals WHERE id = ?");
$professional_stmt->bind_param("i", $professional_id);
$professional_stmt->execute();
$professional_result = $professional_stmt->get_result();

if ($professional_result->num_rows === 0) {
    header("Location: ../index.php");
    exit();
}

$professional = $professional_result->fetch_assoc();
$professional_stmt->close();

// Fetch professional reviews
$reviews_stmt = $conn->prepare("SELECT * FROM reviews WHERE professional_id = ? ORDER BY created_at DESC");
$reviews_stmt->bind_param("i", $professional_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
$reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);
$reviews_stmt->close();

// Calculate average rating and total reviews
$average_rating = round($professional['rating'], 1);
$total_reviews = count($reviews);

// Fetch professional availability
$availability_stmt = $conn->prepare("SELECT * FROM professional_availability WHERE professional_id = ?");
$availability_stmt->bind_param("i", $professional_id);
$availability_stmt->execute();
$availability_result = $availability_stmt->get_result();

if ($availability_result->num_rows > 0) {
    $availability = $availability_result->fetch_assoc();
} else {
    // Default availability if not set
    $availability = [
        'monday' => 1,
        'tuesday' => 1,
        'wednesday' => 1,
        'thursday' => 1,
        'friday' => 1,
        'saturday' => 1,
        'sunday' => 0
    ];
}
$availability_stmt->close();

// Fetch professional pricing
$pricing_stmt = $conn->prepare("SELECT * FROM professional_pricing WHERE professional_id = ?");
$pricing_stmt->bind_param("i", $professional_id);
$pricing_stmt->execute();
$pricing_result = $pricing_stmt->get_result();
$pricing = $pricing_result->fetch_all(MYSQLI_ASSOC);
$pricing_stmt->close();

// Find minimum price
$min_price = 0;
if (!empty($pricing)) {
    $prices = array_column($pricing, 'price');
    $min_price = min($prices);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($professional['name']) ?> | Sevak</title>
    <link rel="stylesheet" href="../css/professionalprofile_client.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Add your CSS styles here */
        .stars {
            color: #FFD700;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .availability-status{
            font-weight:400;
        }
       .availability-day{
        background-color: var(--sevak-light);
            padding: 40px;
            font-weight:700;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
       }
        .tab-btn.active {
            font-weight: bold;
            border-bottom: 2px solid #007BFF;
        }.pricing-item{
            background-color: var(--sevak-light);
            padding: 40px;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
    </style>
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
                <?php if (isset($_SESSION['client_id'])): ?>
                    <li><a href="../clients/clients-dashboard.php" class="navbar-item">Dashboard</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Professional Profile -->
    <div class="hello">
        <div id="tab" class="card">
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-image">
                        <img id="pro-image" src="<?= !empty($professional['profile_image']) ? '../uploads/profiles/' . htmlspecialchars($professional['profile_image']) : '../uploads/default_profille.png' ?>" alt="<?= htmlspecialchars($professional['name']) ?>">
                    </div>
                    <div class="profile-info">
                        <h2 id="pro-name"><?= htmlspecialchars($professional['name']) ?></h2>
                        <p id="pro-profession"><?= htmlspecialchars($professional['profession']) ?></p>
                        <div class="rating-container">
                            <span class="stars">
                                <?php
                                $full_stars = floor($average_rating);
                                $has_half_star = ($average_rating - $full_stars) >= 0.5;
                                
                                for ($i = 0; $i < 5; $i++) {
                                    if ($i < $full_stars) {
                                        echo '<i class="fas fa-star"></i>';
                                    } elseif ($i == $full_stars && $has_half_star) {
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </span>
                            <span id="pro-rating"><?= $average_rating ?></span>
                            <span id="pro-reviews">(<?= $total_reviews ?> reviews)</span>
                        </div>
                        <p><i class="fas fa-map-marker-alt"></i> <span id="pro-location"><?= htmlspecialchars($professional['city'] . ', ' . $professional['state']) ?></span></p>
                        <p><i class="fas fa-envelope"></i> <span id="pro-email"><?= htmlspecialchars($professional['email']) ?></span></p>
                        <p><i class="fas fa-phone"></i> <span id="pro-phone"><?= htmlspecialchars($professional['contact']) ?></span></p>
                    </div>
                    <div class="profile-actions">
                        <?php if (isset($_SESSION['client_id'])): ?>
                            <button class="btn primary-btn" onclick="location.href='../clients/booking_form.php?professional_id=<?= $professional_id ?>'">Book Now</button>
                        <?php else: ?>
                            <button class="btn primary-btn" onclick="location.href='../login.php'">Login to Book</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-tabs">
            <div class="tabs">   
                <button class="tab-btn active" data-tab="pro-reviews-list">Reviews</button>
                <button class="tab-btn" data-tab="pro-availability">Availability</button>
                <button class="tab-btn" data-tab="pro-fees">Fees</button>
            </div>

            <!-- Reviews Tab -->
            <div class="tab-content active" id="pro-reviews-list">
                <div class="reviews-list">
                    <h1>Reviews</h1>
                    <?php if (empty($reviews)): ?>
                        <p>No reviews yet.</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="reviewer-info">
                                        <img src="../uploads/default_profille.png" alt="<?= htmlspecialchars($review['client_name']) ?>" class="reviewer-img">
                                        <div class="reviewer-details">
                                            <h4><?= htmlspecialchars($review['client_name']) ?></h4>
                                            <p class="review-date"><?= date('M j, Y', strtotime($review['created_at'])) ?></p>
                                        </div>
                                    </div>
                                    <div class="review-rating">
                                        <span class="stars">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $review['rating']) {
                                                    echo '<i class="fas fa-star"></i>';
                                                } elseif ($i - 0.5 <= $review['rating']) {
                                                    echo '<i class="fas fa-star-half-alt"></i>';
                                                } else {
                                                    echo '<i class="far fa-star"></i>';
                                                }
                                            }
                                            ?>
                                        </span>
                                        <span class="rating-value"><?= $review['rating'] ?>.0</span>
                                    </div>
                                </div>
                                <div class="review-content">
                                    <p><?= htmlspecialchars($review['review']) ?></p>
                                </div>
                                <?php if (isset($_SESSION['client_id']) && $_SESSION['client_id'] == $review['client_id']): ?>
                                    <div class="review-actions">
                                        <button class="btn icon-btn reply-review-btn" title="Reply">
                                            <i class="fas fa-reply"></i>
                                        </button>
                                        <button class="btn icon-btn flag-review-btn" title="Flag as inappropriate">
                                            <i class="fas fa-flag"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Availability Tab -->
            <div class="tab-content" id="pro-availability">
                <div class="cont">  
                    <h2>Availability</h2>
                    <div class="availability-grid">
                        <?php
                        $days = [
                            'monday' => 'Monday',
                            'tuesday' => 'Tuesday',
                            'wednesday' => 'Wednesday',
                            'thursday' => 'Thursday',
                            'friday' => 'Friday',
                            'saturday' => 'Saturday',
                            'sunday' => 'Sunday'
                        ];
                        
                        foreach ($days as $day_key => $day_name): ?>
                            <div class="availability-day">
                                <span><?= $day_name ?></span> <p> </p><span class="availability-status">
                                    <?= $availability   [$day_key] ? 'Available' : 'Not Available' ?>
                                </span>   
                                
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            </div>
            <!-- Fees Tab -->
            <div class="tab-content" id="pro-fees">
            <h2>Service Fees</h2>
            
                <div class="cont">  
                    <?php if (empty($pricing)): ?>
                        <p>No pricing information available.</p>
                        <?php else: ?>
                            <div class="pricing-list">
                                <?php foreach ($pricing as $service): ?>
                                    <div class="pricing-item">
                                        <h4><?= htmlspecialchars($service['service_name']) ?></h4>
                                        <p>₹<?= number_format($service['price'], 2) ?> per <?= htmlspecialchars($service['price_unit']) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
  </div>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const tabButtons = document.querySelectorAll(".tab-btn");
            const tabContents = document.querySelectorAll(".tab-content");
        
            tabButtons.forEach((button) => {
                button.addEventListener("click", function () {
                    const targetTab = this.getAttribute("data-tab");
            
                    // Remove 'active' class from all buttons
                    tabButtons.forEach((btn) => btn.classList.remove("active"));
                    // Remove 'active' class from all tab contents
                    tabContents.forEach((tab) => tab.classList.remove("active"));
            
                    // Add 'active' class to the clicked button
                    this.classList.add("active");
                    // Show the targeted tab content
                    document.getElementById(targetTab).classList.add("active");
                });
            });
        });
    </script>
</body>
</html>