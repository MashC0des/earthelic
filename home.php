<?php
session_start();
include "db_connect.php"; // DB connection

// Check login session
$isLoggedIn = isset($_SESSION['user_id']);
$fullName = $isLoggedIn ? $_SESSION['full_name'] : null;
$profilePic = $isLoggedIn && !empty($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : "imgs/default_profile.png";

// Function to fetch best sellers per category
function getBestSellers($conn, $category) {
    $sql = "
        SELECT p.product_id, p.product_name, p.price, p.image_url, SUM(oi.quantity) as total_sold
        FROM Products p
        JOIN Order_Items oi ON p.product_id = oi.product_id
        JOIN Orders o ON o.order_id = oi.order_id
        WHERE p.material = ? AND o.status != 'cancelled'
        GROUP BY p.product_id, p.product_name, p.price, p.image_url
        ORDER BY total_sold DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $category);
    $stmt->execute();
    return $stmt->get_result();
}

$categories = ["metal", "ceramic", "canvas", "mixed"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Earthelic.com</title>
     <link rel="stylesheet"
        href="https://db.onlinewebfonts.com/c/ef6bdf5ef216552c7e9869841e891ca0?family=Arial+Rounded+MT+Bold">
     <link rel="stylesheet" href="https://db.onlinewebfonts.com/c/ef6bdf5ef216552c7e9869841e891ca0?family=Arial+Rounded+MT+Bold">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/home.css">
</head>
<body>
<header class="head1">
    <a href="landing.html"><img src="imgs/earthelic logo file png.png" alt="logo" id="logo1"></a>
    <nav class="nav1">   
        <div class="icons1">
           <ul class="nav-links">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="metal.php">Metal</a></li>
                    <li><a href="ceramic.php">Ceramic</a></li>
                     <li><a href="artwork.php">Paintings & Wall Art</a></li>
                    <li><a href="cart.php">Cart</a></li>
                    <li><a href="about.php">About us</a></li>
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <li class="nav-profile-wrap">
                            <a href="profile.php" class="nav-profile-link">
                                <span class="nav-profile-name"><?php echo ($_SESSION['full_name'] ?? 'Profile'); ?></span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li><a href="login.php">Log In</a></li>
                    <?php endif; ?>
                </ul>
        </div>
    </nav>
</header>

<!-- Main Section -->
<section class="main_sec">
    <div class="home-content">
        <span id="ea">Earthelic</span><br>
        <h3> Excellence in Ceramics & Metal Products</h3>
        <p>
            Explore our diverse range of high-quality ceramic and metal products, designed to meet your needs with durability and style.
        </p>
        <!-- Best Sellers Section -->
        <section class="best-sellers">
            <?php foreach ($categories as $category): ?>
                <?php $bestSellers = getBestSellers($conn, $category); ?>
                <?php if ($bestSellers->num_rows > 0): ?>
                    <div class="category-section">
                        <h3><?php echo ucfirst($category); ?> Best Sellers</h3>
                        <div class="product-grid">
                            <?php while ($row = $bestSellers->fetch_assoc()): ?>
                                <div class="product-card">
                                    <img src="<?php echo $row['image_url']; ?>" alt="<?php echo $row['product_name']; ?>">
                                    <h4><?php echo $row['product_name']; ?></h4>
                                    <p>₹<?php echo number_format($row['price'], 2); ?></p>
                                    <p><small>Sold: <?php echo $row['total_sold']; ?></small></p>
                                    <a href="productpage.php?id=<?php echo $row['product_id']; ?>" class="btn">View</a>
                                </div>
                            <?php endwhile; ?>
                            
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
    </div>
</section>

<footer class="foot1">
    <div class="social-icons">
        <a href="#"><i class="fa-solid fa-envelope"></i></a>
        <a href="#"><i class="fa-solid fa-phone"></i></a>
        <a href="#"><i class="fa-brands fa-square-instagram"></i></a>
        <a href="#"><i class="fa-brands fa-facebook"></i></a>
        <a href="#"><i class="fa-solid fa-location-dot"></i></a>
    </div>
    <p>&copy; 2024 Earthelic.com</p>
</footer>
</body>
</html>
