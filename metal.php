<?php
include "db_connect.php"; // includes DB + starts session
include "commonthings.php"; // includes truncate function


// Count total products
$total_query = $conn->query("SELECT COUNT(*) AS total FROM Products WHERE material='metal'");
$total_row = $total_query->fetch_assoc();
$total_products = $total_row['total'];
$total_pages = ceil($total_products / $limit);

// Fetch products for this page
$sql = "SELECT * FROM Products WHERE material='metal' LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earthelic - Metal</title>
    <link rel="stylesheet" href="css\style.css">
    <link rel="stylesheet" href="css\metal.css">
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
                     <li><a href="canvas.php">Paintings & Wall Art</a></li>
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
     <div class="hamburger" onclick="toggleNav()">
            <i class="fa-solid fa-bars"></i>
        </div>
</header>
<section class="main_sec">
    
    <div class="home-content">
          <div class="back-btn">
        <button class="btn-back" onclick="window.history.back()">⬅ Back</button>
    </div>
        <p  id="eas">Metal</p>
        <h3>Our Metal Inventory</h3>

       <?php if ($result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): ?>
        <div class="products">
            <div class="metprod">
                <img src="<?php echo htmlspecialchars($row['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($row['product_name']); ?>" 
                     id="mp">
                <div class="wrapper3">
                    <p id="metname"><?php echo htmlspecialchars($row['product_name']); ?></p>
                      <!-- Truncate the description here with 300 characters limit -->
                    <p id="metdesc"><?php echo htmlspecialchars(string: truncate_description($row['description'], 300)); ?></p>
                        <a href="productpage.php?id=<?php echo $row['product_id']; ?>" class="btn1">
                            ₹<?php echo number_format($row['price'], 2); ?> Buy Now
                        </a>
                    </span>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p style="color:#fff;">No metal products available.</p>
<?php endif; ?>

        <!-- Pagination -->
        <div class="container">
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a class="btnpg1" href="metal.php?page=<?php echo $page - 1; ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a class="link <?php echo ($i == $page) ? 'active' : ''; ?>" href="metal.php?page=<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a class="btnpg1" href="metal.php?page=<?php echo $page + 1; ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<footer class="foot1">
    <div class="social-icons">
        <a href="mailto:earthelicarthouse@gmail.com"><i class="fa-solid fa-envelope"></i></a>
        <a href="tel:999999999"><i class="fa-solid fa-phone"></i></a>
        <a href="https://www.instagram.com/earthelic_homedecor/" target="_blank"><i class="fa-brands fa-square-instagram"></i></a>
        <a href="#"><i class="fa-brands fa-facebook"></i></a>
        <a href="#"><i class="fa-solid fa-location-dot"></i></a>
    </div>
    <p>&copy; 2024 Earthelic.com</p>
</footer>
<script src="script.js"></script>
</body>
</html>
