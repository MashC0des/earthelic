<?php
include "db_connect.php"; // includes DB + starts session

// Pagination setup
$limit = 10; // products per page
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// Count total ceramic products
$total_query = $conn->query("SELECT COUNT(*) AS total FROM Products WHERE material='ceramic'");
$total_row = $total_query->fetch_assoc();
$total_products = $total_row['total'];
$total_pages = ceil($total_products / $limit);

// Fetch ceramic products for this page
$sql = "SELECT * FROM Products WHERE material='ceramic' LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Ceramic Products - Earthelic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css\style.css">
    <link rel="stylesheet" href="css\ceramic.css"> <!-- ✅ ceramic css -->
</head>

<body>
    <header class="head1">
        <a href="landing.html"><img src="imgs/earthelic logo file png.png" alt="Earthelic Logo" id="logo1"></a>
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



    <section class="main_sec">
        <div class="home-content">
            <div class="back-btn">
                <button onclick="window.history.back()">⬅ Back</button>
            </div>
            <h1 id="eas">Ceramic</h1>
            <div class="product-list">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="product-card">
                            <img src="uploads/<?php echo $row['image_url']; ?>" alt="<?php echo $row['product_name']; ?>">
                            <h3><?php echo $row['product_name']; ?></h3>
                            <p><?php echo $row['description']; ?></p>
                            <p><strong>₹<?php echo $row['price']; ?></strong></p>
                            <button>Add to Cart</button>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No ceramic products available.</p>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">&laquo; Prev</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer class="foot1">
        <div class="social-icons">
            <a href="mailto:earthelicarthouse@gmail.com"><i class="fa-solid fa-envelope"></i></a>
            <a href="tel:999999999"><i class="fa-solid fa-phone"></i></a>
            <a href="https://www.instagram.com/earthelic_homedecor/" target="_blank"><i
                    class="fa-brands fa-square-instagram"></i></a>
            <a href="#"><i class="fa-brands fa-facebook"></i></a>
            <a href="#"><i class="fa-solid fa-location-dot"></i></a>
        </div>
        <p>&copy; 2024 Earthelic.com</p>
    </footer>
</body>

</html>