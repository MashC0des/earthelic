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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        p{
             font-family: 'Arial Rounded MT Bold', 'Helvetica', 'Arial', sans-serif;
        }
        h3{
             font-family: 'Arial Rounded MT Bold';
        }
        .best-sellers {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            box-sizing: border-box;
           
        }

        .category-section h3 {
            color: #fff;
            font-size: 2em;
            text-align: center;
            margin-top: 40px;
            margin-bottom: 20px;
            text-transform: capitalize;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            justify-items: center;
            align-items: stretch;
        }

        .product-card {
            background-color: #b6aeae73;
    
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            color: #000;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .product-card h4 {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
        
        .product-card p {
            color: #0c0c0cff;
            margin: 5px 0;
        }
        
        .product-card small {
            color: #0f0f0fff;
        }

        .product-card .btn {
            background-color: #904A2D;
            color: #fff;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-top: 10px;
            display: inline-block;
        }

        .product-card .btn:hover {
            background-color: #7a3c24;
        }
    </style>
</head>
<body>
<header class="head1">
    <a href="landing.html"><img src="imgs/earthelic logo file png.png" alt="logo" id="logo1"></a>
    <div class="search">
        <span class="search-icon material-symbols-outlined">search</span>
        <input class="searchbar" type="search" placeholder="Search products...">
    </div>
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
            <h2>🔥 Best Sellers</h2>
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
