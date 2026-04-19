<?php
session_start();
require_once "includes/config.php"; // Include database connection

// Check if database connection is established
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Unknown error"));
}

// Fetch services from `services` table
$servicesQuery = "SELECT * FROM services LIMIT 8";
$servicesResult = $conn->query($servicesQuery);

if (!$servicesResult) {
    die("Query Error on services: " . $conn->error);
}

if ($servicesResult->num_rows === 0) {
    echo "<p>No services found.</p>";
}

// Fetch top professionals from `professionals` table
$professionalsQuery = "SELECT * FROM professionals ORDER BY rating DESC LIMIT 4";
$professionalsResult = $conn->query($professionalsQuery);

// Initialize reviews variables
$reviews = [];
$showReviews = false;

// Check if reviews table exists
$checkTables = $conn->query("SHOW TABLES LIKE 'reviews'");
if ($checkTables && $checkTables->num_rows > 0) {
    // Fetch reviews
    $reviewsQuery = "SELECT review, rating, client_name 
                     FROM reviews 
                     ORDER BY created_at DESC 
                     LIMIT 3";
    
    $reviewsResult = $conn->query($reviewsQuery);
    
    if ($reviewsResult === false) {
        error_log("Reviews query failed: " . $conn->error);
    } elseif ($reviewsResult->num_rows > 0) {
        $showReviews = true;
        while($row = $reviewsResult->fetch_assoc()) {
            $reviews[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sevak - Connect with Local Service Professionals</title>
    <meta name="description" content="Find trusted local service professionals for home, business, and personal needs. Sevak connects you with verified experts near you." />
    <meta name="author" content="Sevak" />
    <meta property="og:image" content="/og-image.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
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

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      line-height: 1.6;
      color: #333;
    }

    .container {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    ul {
      list-style: none;
    }

    img {
      max-width: 100%;
    }

    h1, h2, h3, h4, h5, h6 {
      line-height: 1.2;
    }

    .section-header {
      text-align: center;
      margin-bottom: 3rem;
    }

    .section-header h2 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 1rem;
    }

    .section-header p {
      max-width: 600px;
      margin: 0 auto;
      color: #666;
    }

    /* Button Styles */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
      font-weight: 500;
      border-radius: 0.375rem;
      cursor: pointer;
      transition: all 0.3s ease;
      border: none;
    }

    .btn-primary {
      background-color: var(--sevak-primary);
      color: white;
    }

    .btn-primary:hover {
      background-color: var(--sevak-secondary);
    }

    .btn-outline {
      background-color: transparent;
      color: var(--sevak-primary);
      border: 1px solid var(--sevak-primary);
    }

    .btn-outline:hover {
      background-color: var(--sevak-primary);
      color: white;
    }

    .btn-light {
      background-color: white;
      color: var(--sevak-primary);
    }

    .btn-light:hover {
      background-color: rgba(255, 255, 255, 0.9);
    }

    .btn-outline-light {
      background-color: transparent;
      color: white;
      border: 1px solid white;
    }

    .btn-outline-light:hover {
      background-color: rgba(255, 255, 255, 0.2);
    }

    .btn-tag {
      background-color: rgba(255, 255, 255, 0.1);
      color: white;
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 20px;
      padding: 0.25rem 0.75rem;
      font-size: 0.75rem;
    }

    .btn-tag:hover {
      background-color: rgba(255, 255, 255, 0.2);
    }

    .btn-outline-small, .btn-primary-small {
      padding: 0.25rem 0.5rem;
      font-size: 0.75rem;
    }

    .btn-text {
      background: none;
      border: none;
      color: var(--sevak-primary);
      font-weight: 500;
      cursor: pointer;
      display: flex;
      align-items: center;
    }

    .btn-text i {
      margin-left: 0.25rem;
      font-size: 0.75rem;
    }

    /* Navbar */
    .navbar {
      background-color: white;
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
      color: var(--sevak-primary);
    }

    .navbar-menu {
      display: flex;
      gap: 2rem;
    }

    .navbar-item {
      color: #333;
      transition: color 0.3s;
    }

    .navbar-item:hover {
      color: var(--sevak-primary);
    }

    .navbar-buttons {
      display: flex;
      gap: 0.5rem;
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

    /* Hero Section */
    .hero {
      background: linear-gradient(135deg, var(--sevak-primary), var(--sevak-secondary));
      color: white;
      text-align: center;
      padding: 8rem 0 5rem;
    }

    .hero h1 {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 1rem;
      max-width: 800px;
      margin-left: auto;
      margin-right: auto;
    }

    .hero-subtitle {
      font-size: 1.125rem;
      max-width: 600px;
      margin: 0 auto 2rem;
      opacity: 0.9;
    }

    .search-box {
      background-color: white;
      border-radius: 0.5rem;
      padding: 0.5rem;
      display: flex;
      flex-direction: column;
      max-width: 700px;
      margin: 0 auto;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .search-input {
      position: relative;
      margin-bottom: 0.5rem;
    }

    .search-input i {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
    }

    .search-input input {
      width: 100%;
      padding: 0.75rem 0.75rem 0.75rem 2.5rem;
      border: none;
      border-radius: 0.375rem;
      outline: none;
    }

    .search-input input:focus {
      box-shadow: 0 0 0 2px rgba(126, 105, 171, 0.2);
    }

    .popular-services {
      margin-top: 1.5rem;
      color: rgba(255, 255, 255, 0.8);
    }

    .service-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      justify-content: center;
      margin-top: 0.5rem;
    }

    /* Services Section */
    .services {
      padding: 5rem 0;
      background-color: var(--sevak-light);
    }

    .services-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 1.5rem;
      animation: pingpong 3s linear infinite alternate;
    }
    @keyframes pingpong {
      0% {
        transform: translateX(10%);
      }
      100% {
        transform: translateX(-10%);
      }
    }

    .service-card {
      background-color: white;
      border-radius: 0.75rem;
      padding: 1.5rem;
      flex-shrink: 0;
      white-space: nowrap;
      text-align: center;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      transition: transform 0.3s, box-shadow 0.3s;
    }

    .service-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .service-icon {
      font-size: 2.5rem;
      color: var(--sevak-primary);
      margin-bottom: 1rem;
    }

    .service-card h3 {
      font-weight: 600;
      margin-bottom: 0.5rem;
    }

    .service-card p {
      font-size: 0.875rem;
      color: #666;
    }

    .services-view-all {
      text-align: center;
      margin-top: 2rem;
    }

    /* How It Works Section */
    .how-it-works {
      padding: 5rem 0;
    }

    .steps-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 2rem;
    }

    .step {
      text-align: center;
      position: relative;
    }

    .step-icon {
      width: 4rem;
      height: 4rem;
      border-radius: 50%;
      background-color: rgba(126, 105, 171, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 1.5rem;
      color: var(--sevak-primary);
    }

    .step h3 {
      font-weight: 600;
      margin-bottom: 0.75rem;
    }

    .step p {
      color: #666;
    }

    /* Professionals Section */
    .professionals {
      padding: 5rem 0;
      background-color: white;
    }

    .professionals-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 2rem;
    }

    .professional-card {
      background-color: white;
      border-radius: 0.75rem;
      overflow: hidden;
      box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
      transition: box-shadow 0.3s;
    }

    .professional-card:hover {
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .professional-image {
      position: relative;
    }

    .professional-image img {
      width: 100%;
      height: 250px;
      object-fit: cover;
    }

    .professional-rating {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 0.5rem 1rem;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.7), transparent);
      color: white;
      display: flex;
      align-items: center;
    }

    .professional-rating i {
      color: #FFD700;
      margin-right: 0.25rem;
    }

    .professional-rating .reviews {
      font-size: 0.75rem;
      opacity: 0.8;
      margin-left: 0.25rem;
    }

    .professional-info {
      padding: 1.25rem;
    }

    .professional-info h3 {
      font-weight: 600;
      margin-bottom: 0.25rem;
    }

    .profession {
      color: var(--sevak-primary);
      font-weight: 500;
      margin-bottom: 0.5rem;
    }

    .location {
      display: flex;
      align-items: center;
      color: #666;
      font-size: 0.875rem;
      margin-bottom: 1rem;
    }

    .location i {
      margin-right: 0.5rem;
      font-size: 0.875rem;
    }

    .professional-actions {
      display: flex;
      gap: 0.5rem;
    }

    .professionals-view-all {
      text-align: center;
      margin-top: 3rem;
    }

    /* Testimonials Section */
    .testimonials {
      padding: 5rem 0;
      background-color: var(--sevak-light);
    }

    .testimonials-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
    }

    .testimonial-card {
      background-color: white;
      border-radius: 0.75rem;
      padding: 1.5rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .testimonial-rating {
      color: #FFD700;
      margin-bottom: 1rem;
    }

    .testimonial-content {
      font-style: italic;
      margin-bottom: 1.5rem;
      color: #555;
    }

    .testimonial-author {
      display:flex;
      align-items: center;
    }

    .testimonial-author img {
      width: 3rem;
      height: 3rem;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 1rem;
    }

    .testimonial-author h4 {
      font-weight: 600;
      
    }

    .testimonial-author p {
      color: #666;
      font-size: 0.875rem;
    }

    /* Call To Action Section */
    .cta {
      padding: 5rem 0;
      background: linear-gradient(135deg, var(--sevak-primary), var(--sevak-secondary));
      color: white;
      text-align: center;
    }

    .cta h2 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 1rem;
    }

    .cta p {
      max-width: 600px;
      margin: 0 auto 2.5rem;
      opacity: 0.9;
    }

    .cta-buttons {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin-bottom: 3rem;
      flex-wrap: wrap;
    }

    .cta-stats {
      display: flex;
      justify-content: center;
      gap: 3rem;
      border-top: 1px solid rgba(255, 255, 255, 0.2);
      padding-top: 2rem;
      flex-wrap: wrap;
    }

    .stat h3 {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .stat p {
      opacity: 0.8;
      margin: 0;
    }

    /* Footer Section */
    .footer {
      background-color: var(--sevak-dark);
      color: white;
      padding: 4rem 0 2rem;
    }

    .footer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 2rem;
      margin-bottom: 3rem;
    }

    .footer-about h3 {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 1rem;
    }

    .footer-about p {
      color: #ccc;
      margin-bottom: 1.5rem;
    }

    .social-links {
      display: flex;
      gap: 1rem;
    }

    .social-links a {
      width: 2rem;
      height: 2rem;
      border-radius: 50%;
      background-color: rgba(255, 255, 255, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background-color 0.3s;
    }

    .social-links a:hover {
      background-color: var(--sevak-primary);
    }

    .footer h4 {
      font-size: 1.125rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
    }

    .footer-links ul li {
      margin-bottom: 0.75rem;
    }

    .footer-links a {
      color: #ccc;
      transition: color 0.3s;
    }

    .footer-links a:hover {
      color: white;
    }

    .footer-contact ul li {
      display: flex;
      color: #ccc;
      margin-bottom: 1rem;
    }

    .footer-contact i {
      margin-right: 0.75rem;
      margin-top: 0.25rem;
    }

    .footer-bottom {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 2rem;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      color: #aaa;
      font-size: 0.875rem;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .footer-legal {
      display: flex;
      gap: 1.5rem;
    }

    .footer-legal a {
      color: #aaa;
      transition: color 0.3s;
    }

    .footer-legal a:hover {
      color: white;
    }

    /* Responsive Styles */
    @media (max-width: 992px) {
      .navbar-menu, .navbar-buttons {
        display: none;
      }
      
      .navbar-toggle {
        display: flex;
      }
      
      .hero h1 {
        font-size: 2rem;
      }
      
      .search-box {
        flex-direction: column;
      }
      
      .search-input {
        margin-bottom: 0.5rem;
      }
      
      .search-box .btn {
        width: 100%;
      }
      
      .cta-stats {
        gap: 1.5rem;
      }
      
      .stat h3 {
        font-size: 2rem;
      }
    }

    @media (min-width: 768px) {
      .search-box {
        flex-direction: row;
        align-items: center;
      }
      
      .search-input {
        flex: 1;
        margin-bottom: 0;
        margin-right: 0.5rem;
      }
      
      .search-box .btn {
        flex-shrink: 0;
      }
    }

    @media (max-width: 640px) {
      .section-header h2 {
        font-size: 1.75rem;
      }
      
      .hero h1 {
        font-size: 1.75rem;
      }
      
      .hero-subtitle {
        font-size: 1rem;
      }
      
      .footer-bottom {
        flex-direction: column;
        text-align: center;
      }
      
      .footer-legal {
        flex-direction: column;
        gap: 0.75rem;
      }
    }

  </style>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <div class="navbar-brand">
                <a href="#" class="logo">Sevak</a>
            </div>
            <div class="navbar-menu">
                <a href="#services" class="navbar-item">Services</a>
                <a href="#how-it-works" class="navbar-item">How It Works</a>
                <a href="#professionals" class="navbar-item">Professionals</a>
                <a href="#testimonials" class="navbar-item">Review</a>
            </div>
            <div class="navbar-buttons">
                <button class="btn btn-outline" onclick="location.href='login.php'">Log In</button>
                <button class="btn btn-primary" onclick="location.href='register.php'">Sign Up</button>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
      <div class="container">
        <h1>Find Trusted Local Service Professionals</h1>
        <p class="hero-subtitle">Connect with skilled professionals near you for all your home, business, and personal service needs.</p>
        
        <div class="search-box">
          <div class="search-input">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="What service do you need?">
          </div>
          <div class="search-input">
            <i class="fa-solid fa-location-dot"></i>
            <input type="text" placeholder="Your location">
          </div>
          <button class="btn btn-primary">Search</button>
        </div>

        <div class="popular-services">
          <p>Popular services:</p>
          <div class="service-tags">
            <button class="btn btn-tag">Home Cleaning</button>
            <button class="btn btn-tag">Plumbing</button>
            <button class="btn btn-tag">Electrician</button>
          </div>
        </div>
      </div>
    </section>

        <!-- Services Section -->
        <section id="services" class="services">
      <div class="container">
        <div class="section-header">
          <h2>Our Services</h2>
          <p>Discover a wide range of services provided by verified professionals in your area.</p>
        </div>

        <div class="services-grid">
          <div class="service-card">
            <div class="service-icon"><i class="fa-solid fa-house"></i></div>
            <h3>Home Repair</h3>
            <p>Find trusted professionals</p>
          </div>
          <div class="service-card">
            <div class="service-icon"><i class="fa-solid fa-wrench"></i></div>
            <h3>Plumbing</h3>
            <p>Find trusted professionals</p>
          </div>
          <div class="service-card">
            <div class="service-icon"><i class="fa-solid fa-paint-roller"></i></div>
            <h3>Painting</h3>
            <p>Find trusted professionals</p>
          </div>
          <div class="service-card">
            <div class="service-icon"><i class="fa-solid fa-briefcase"></i></div>
            <h3>Business</h3>
            <p>Find trusted professionals</p>
          </div>
          <div class="service-card">
            <div class="service-icon"><i class="fa-solid fa-car"></i></div>
            <h3>Auto Service</h3>
            <p>Find trusted professionals</p>
          </div>
          <div class="service-card">
            <div class="service-icon"><i class="fa-solid fa-utensils"></i></div>
            <h3>Catering</h3>
            <p>Find trusted professionals</p>
          </div>
          <div class="service-card">
            <div class="service-icon"><i class="fa-solid fa-pencil"></i></div>
            <h3>Design</h3>
            <p>Find trusted professionals</p>
          </div>
          <div class="service-card">
            <div class="service-icon"><i class="fa-solid fa-scissors"></i></div>
            <h3>Beauty & Salon</h3>
            <p>Find trusted professionals</p>
          </div>
        </div>

        <div class="services-view-all">
          <button class="btn-text">View All Services <i class="fa-solid fa-chevron-right"></i></button>
        </div>
      </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="how-it-works">
      <div class="container">
        <div class="section-header">
          <h2>How Sevak Works</h2>
          <p>Finding and booking professional services has never been easier. Follow these simple steps to get started.</p>
        </div>

        <div class="steps-grid">
          <div class="step">
            <div class="step-icon">
              <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <h3>Search Services</h3>
            <p>Browse through our extensive catalog of professional services available in your area.</p>
          </div>
          
          <div class="step">
            <div class="step-icon">
              <i class="fa-solid fa-user-check"></i>
            </div>
            <h3>Choose a Professional</h3>
            <p>Select from a list of qualified and verified service providers based on reviews and ratings.</p>
          </div>
          
          <div class="step">
            <div class="step-icon">
              <i class="fa-solid fa-calendar"></i>
            </div>
            <h3>Book an Appointment</h3>
            <p>Schedule a convenient time for the service directly through our easy-to-use platform.</p>
          </div>
          
          <div class="step">
            <div class="step-icon">
              <i class="fa-solid fa-star"></i>
            </div>
            <h3>Rate & Review</h3>
            <p>Share your experience and help others find reliable professionals in the community.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Professionals Section (updated to use single connection) -->
    <section id="professionals" class="professionals">
        <div class="container">
            <div class="section-header">
                <h2>Meet Our Top Professionals</h2>
                <p>Our platform hosts thousands of skilled professionals who are ready to help you with your service needs.</p>
            </div>

            <div class="professionals-grid">
                <?php 
                // Re-query professionals using the single connection
                $professionalsQuery = "SELECT * FROM professionals ORDER BY rating DESC LIMIT 4";
                $professionalsResult = $conn->query($professionalsQuery);
                
                if ($professionalsResult === false) {
                    die("Query failed: " . $conn->error);
                }
                
                if ($professionalsResult->num_rows > 0) {
                    while ($professional = $professionalsResult->fetch_assoc()): 
                        $location = '';
                        if (!empty($professional['city'])) {
                            $location .= htmlspecialchars($professional['city']);
                        }
                        if (!empty($professional['state'])) {
                            if (!empty($location)) $location .= ', ';
                            $location .= htmlspecialchars($professional['state']);
                        }
                        if (empty($location)) {
                            $location = 'Location not specified';
                        }
                        
                        $image = !empty($professional['profile_image']) ? htmlspecialchars($professional['profile_image']) : '../uploads/default_profille.png';
                ?>
                        <div class="professional-card">
                            <div class="professional-image">
                                <img src="uploads/<?php echo $image; ?>" alt="<?php echo htmlspecialchars($professional['name'] ?? 'Professional'); ?>">
                                <div class="professional-rating">
                                    <i class="fa-solid fa-star"></i>
                                    <span><?php echo htmlspecialchars($professional['rating'] ?? '0'); ?></span>
                                    <span class="reviews">(<?php echo htmlspecialchars($professional['reviews_count'] ?? '0'); ?> reviews)</span>
                                </div>
                            </div>
                            <div class="professional-info">
                                <h3><?php echo htmlspecialchars($professional['name'] ?? 'Professional'); ?></h3>
                                <p class="profession"><?php echo htmlspecialchars($professional['profession'] ?? 'Service Provider'); ?></p>
                                <p class="location"><i class="fa-solid fa-location-dot"></i> <?php echo $location; ?></p>
                            </div>
                        </div>
                    <?php endwhile; 
                } else {
                    echo "<p>No professionals found in the database.</p>";
                }
                ?>
            </div>
        </div>
    </section>

    <!-- Reviews Section -->
    <section id="testimonials" class="testimonials">
        <div class="container">
            <div class="section-header">
                <h2>What Our Customers Say</h2>
                <p>Don't just take our word for it. Here's what customers have to say about their experiences with Sevak.</p>
            </div>

            <div class="testimonials-grid">
                <?php if ($showReviews && !empty($reviews)): ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="testimonial-card">
                            <div class="testimonial-rating">
                                <?php
                                $rating = isset($review['rating']) ? (float)$review['rating'] : 0;
                                for ($i = 0; $i < 5; $i++): ?>
                                    <i class="fa-solid fa-star<?php echo ($i < $rating) ? '' : '-half-stroke' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="testimonial-content">"<?php echo htmlspecialchars($review['review'] ?? ''); ?>"</p>
                            <div class="testimonial-author">
                                <h4><?php echo htmlspecialchars($review['client_name'] ?? 'Anonymous'); ?></h4>
                                
                                <p>Verified Client</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback testimonials when no reviews exist -->
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                        </div>
                        <p class="testimonial-content">"Sevak helped me find a great plumber in minutes. Excellent service!"</p>
                        <div class="testimonial-author">
                            <h4>Rajesh Kumar</h4>
                            <p>Verified Client</p>
                        </div>
                    </div>
                    
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star-half-stroke"></i>
                        </div>
                        <p class="testimonial-content">"The electrician was professional and fixed my issue quickly. Will use Sevak again!"</p>
                        <div class="testimonial-author">
                            <h4>Priya Sharma</h4>
                            <p>Verified Client</p>
                        </div>
                    </div>
                    
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                            <i class="fa-solid fa-star"></i>
                        </div>
                        <p class="testimonial-content">"Found a reliable cleaning service through Sevak. They did an amazing job!"</p>
                        <div class="testimonial-author">
                            <h4>Anita Desai</h4>
                            <p>Verified Client</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Call To Action Section -->
    <section class="cta">
      <div class="container">
        <h2>Ready to Get Started?</h2>
        <p>Join thousands of satisfied customers and professionals on Sevak. Whether you need a service or want to offer your skills, we've got you covered.</p>
        
        <div class="cta-buttons">
          <?php if (isset($_SESSION['user_id'])): ?>
            <!-- User is logged in - redirect to services page -->
            <button class="btn btn-light" onclick="location.href='services.php'">Find a Service</button>
            <button class="btn btn-outline-light" onclick="location.href='professional_signup.php'">Become a Professional</button>
          <?php else: ?>
            <!-- User is not logged in - redirect to login with redirect parameter -->
            <button class="btn btn-light" onclick="location.href='login.php?redirect=services'">Find a Service</button>
            <button class="btn btn-outline-light" onclick="location.href='login.php?redirect=professional'">Become a Professional</button>
          <?php endif; ?>
        </div>
        
        <div class="cta-stats">
          <div class="stat">
            <h3>10,000+</h3>
            <p>Active Professionals</p>
          </div>
          <div class="stat">
            <h3>50+</h3>
            <p>Service Categories</p>
          </div>
          <div class="stat">
            <h3>100,000+</h3>
            <p>Satisfied Customers</p>
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
            <p>Connecting customers with trusted service professionals since 2023. Your one-stop platform for all service needs.</p>
            <div class="social-links">
              <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
              <a href="#"><i class="fa-brands fa-twitter"></i></a>
              <a href="#"><i class="fa-brands fa-instagram"></i></a>
              <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
            </div>
          </div>

          <div class="footer-links">
            <h4>Services</h4>
            <ul>
              <li><a >Home Repair</a></li>
              <li><a >Plumbing</a></li>
              <li><a>Electrical</a></li>
              <li><a >Cleaning</a></li>
              <li><a href="../html files/service.html">View All Services</a></li>
            </ul>
          </div>

          <div class="footer-links">
            <h4>Quick Links</h4>
            <ul>
              <li><a href="#how-it-works">How It Works</a></li>
              <li><a href="../html files/aboutus.html">About Us</a></li>
              <li><a href="../Users/login.php">Join as Professional</a></li>
              <li><a >Blog</a></li>
              <li><a href="../html files/help.html">FAQs</a></li>
            </ul>
          </div>

          <div class="footer-contact">
            <h4>Contact Us</h4>
            <ul>
              <li><i class="fa-solid fa-location-dot"></i> Banasthali Vidyapith<br>Tonk, Rajasthan ,304022, India</li>
              <li><i class="fa-solid fa-phone"></i> +91 123-456-7890</li>
              <li><i class="fa-solid fa-envelope"></i> support@sevak.com</li>
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

    <script src="script.js"></script>
  </body>
</html>