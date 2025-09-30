<?php
session_start();
include "db_connect.php";
include "commonthings.php"; // includes truncate function


// Count total painting + wall art products
$count_sql = "
  SELECT COUNT(*) AS total
  FROM Products p
  LEFT JOIN Categories c ON p.category_id = c.category_id
  WHERE LOWER(c.category_name) IN ('painting','wall art','wallart')
     OR LOWER(p.material) = 'canvas'
";
$total_row = $conn->query($count_sql)->fetch_assoc();
$total_products = (int)$total_row['total'];
$total_pages = max(1, ceil($total_products / $limit));

// Fetch both categories at once
$sql = "
  SELECT p.*
  FROM Products p
  LEFT JOIN Categories c ON p.category_id = c.category_id
  WHERE LOWER(c.category_name) IN ('painting','wall art','wallart')
     OR LOWER(p.material) = 'canvas'
  ORDER BY p.created_at DESC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paintings & Wall Art - Earthelic</title>
  
  <link rel="stylesheet" href="css/metal.css">
  <link rel="stylesheet" href="css/style.css">
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
        <p  id="eas">Paintings & Wall Art </p>
        <h3>Our artwork Inventory</h3>

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
                    <span id="sp1">
                        <a href="productpage.php?id=<?php echo $row['product_id']; ?>" class="btn1">
                            ₹<?php echo number_format($row['price'], 2); ?> Buy Now
                        </a>
                    </span>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p style="color:#fff;">No artwork products available.</p>
<?php endif; ?>

        <!-- Pagination -->
        <div class="container">
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <!-- Previous button ID added for uniqueness -->
                    <a id="prev-page" class="btnpg1" href="canvas.php?page=<?php echo $page - 1; ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <!-- Page number links now have unique, sequential IDs -->
                    <a id="page-link-<?php echo $i; ?>" class="link <?php echo ($i == $page) ? 'active' : ''; ?>" href="canvas.php?page=<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <!-- Next button ID added for uniqueness -->
                    <a id="next-page" class="btnpg1" href="canvas.php?page=<?php echo $page + 1; ?>"><i class="fa-solid fa-chevron-right"></i></a>
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
