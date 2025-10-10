<?php
declare(strict_types=1);
session_start();
require_once "db_connect.php"; // Assumes db_connect.php exists with a valid mysqli connection ($conn)

// ---- Helper function to sanitize output ----
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ---- Validate product ID from URL ----
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die("Invalid product ID.");
}
$product_id = (int)$_GET['id'];

// ---- Fetch product details from the database ----
$stmt = $conn->prepare("SELECT product_id, product_name, description, price, stock_quantity, material, image_url
                        FROM Products WHERE product_id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if product was found
if (!$product) {
    http_response_code(404);
    die("Product not found.");
}

// ---- Handle review submission (if user is logged in) ----
if (isset($_POST['submit_review']) && isset($_SESSION['user_id'])) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = trim($_POST['comment'] ?? '');

    if ($rating >= 1 && $rating <= 5 && $comment !== '') {
        $stmt = $conn->prepare("INSERT INTO Reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $product_id, $_SESSION['user_id'], $rating, $comment);
        if ($stmt->execute()) {
            header("Location: productpage.php?id=" . $product_id); // Redirect to prevent form resubmission
            exit;
        }
        $stmt->close();
    } else {
        $review_error = "Please provide a rating (1-5) and a comment.";
    }
}

// ---- Fetch rating summary for this product ----
$stmt = $conn->prepare("SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total_reviews
                        FROM Reviews WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$avg_rating = $summary['avg_rating'] ? (float)$summary['avg_rating'] : 0.0;
$total_reviews = (int)($summary['total_reviews'] ?? 0);

// ---- Fetch all reviews for this product, including user details ----
$stmt = $conn->prepare("SELECT r.rating, r.comment, r.review_date, u.full_name, u.profile_picture
                        FROM Reviews r
                        JOIN Users u ON r.user_id = u.user_id
                        WHERE r.product_id = ?
                        ORDER BY r.review_date DESC");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$reviews = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title><?php echo h($product['product_name']); ?> - Earthelic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/productpage.css" />
    <style>
        /* CSS for the temporary warning message */
        .stock-warning {
            color: #dc3545; /* Red color */
            font-size: 1.5rem;
            margin-top: 5px;
            margin-bottom: 5px;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        .stock-warning.show {
            opacity: 1;
        }
    </style>
</head>
<body>

<header class="head1">
    <a href="index.html"><img src="imgs/earthelic logo file png.png" alt="logo" id="logo1"></a>
    
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
<main class="main_sec">
    <div class="glass-container">
        <div class="back-btn">
            <button class="btn-back" onclick="window.history.back()">⬅ Back</button>
        </div>
                         <h1><?php echo h($product['product_name']); ?></h1>
        <!-- PRODUCT SHOWCASE SECTION -->
        <section class="product-showcase">
            <div class="product-image">
                <img src="<?php echo h($product['image_url']); ?>" alt="<?php echo h($product['product_name']); ?>">
            </div>

            <div class="product-details">
               

                <div class="rating-row">
                    <?php if ($total_reviews > 0): ?>
                        <span class="stars" aria-label="Average rating">
                            <?php
                                $full = (int)floor($avg_rating);
                                $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                                for ($i=0; $i<$full; $i++) echo '★';
                                if ($half) echo '☆';
                                for ($i=$full + $half; $i<5; $i++) echo '☆';
                            ?>
                        </span>
                        <span class="rating-text"><?php echo number_format($avg_rating,1); ?>/5 (<?php echo $total_reviews; ?> reviews)</span>
                    <?php else: ?>
                        <span class="rating-text">No reviews yet</span>
                    <?php endif; ?>
                </div>

                <p class="product-price">₹<?php echo number_format((float)$product['price'], 2); ?></p>
                <p class="product-description"><?php echo nl2br(h($product['description'])); ?></p>

                <ul class="product-features">
                    <li>✔ Material: <?php echo h(ucfirst($product['material'])); ?></li>
                    <li>✔ Stock: <?php echo ($product['stock_quantity'] > 0) ? h((string)$product['stock_quantity']) . " available" : "Out of Stock"; ?></li>
                </ul>

                <div class="product-actions">
                    <?php if ($product['stock_quantity'] > 0): ?>
                       <form method="POST" action="cart.php" id="add-to-cart-form">
                            <!-- All product data is passed as hidden inputs to cart.php -->
                            <input type="hidden" name="add_to_cart" value="1">
                            <input type="hidden" name="product_id" value="<?php echo h((string)$product['product_id']); ?>">
                            <input type="hidden" name="product_name" value="<?php echo h($product['product_name']); ?>">
                            <input type="hidden" name="product_price" value="<?php echo h((string)$product['price']); ?>">
                            <input type="hidden" name="image_url" value="<?php echo h($product['image_url']); ?>">
                            
                         

                            <input type="number" 
                                   name="quantity" 
                                   id="quantityInput"
                                   class="quantity-input" 
                                   value="1" 
                                   min="1" 
                                   max="<?php echo h((string)$product['stock_quantity']); ?>"
                                   data-max-stock="<?php echo h((string)$product['stock_quantity']); ?>"
                                   oninput="checkStockLimit(this)">
                            <button type="submit" name="add_to_cart" class="btn-add-cart">Add to Cart</button>
                            <!-- IMPORTANT: Remember to add server-side validation in cart.php 
                                 to ensure the submitted quantity does not exceed the current stock. -->
                           <!-- Add a container for the warning message -->
                            <div id="stockWarningMessage" class="stock-warning"></div>
                                </form>
                    <?php else: ?>
                        <button class="btn-disabled" disabled>Out of Stock</button>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- REVIEWS SECTION -->
        <section class="review-section">
            <h2>Customer Reviews</h2>
            <?php if ($reviews->num_rows > 0): ?>
                <?php while ($rev = $reviews->fetch_assoc()): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <img src="<?php echo (!empty($rev['profile_picture'])) 
                                ? h($rev['profile_picture']) 
                                : 'imgs/default-profile.png'; ?>" 
                                alt="Profile Picture" class="profile-pic">
                            <div>
                                <strong><?php echo h($rev['full_name']); ?></strong>
                                <div class="stars">
                                    <?php echo str_repeat('★', (int)$rev['rating']); ?>
                                    <?php echo str_repeat('☆', 5 - (int)$rev['rating']); ?>
                                </div>
                                <time><?php echo date("d M Y", strtotime($rev['review_date'])); ?></time>
                            </div>
                        </div>
                        <p><?php echo nl2br(h($rev['comment'])); ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-reviews">No reviews yet. Be the first to review!</p>
            <?php endif; ?>

            <!-- Leave a review form -->
            <div class="review-form">
                <h3>Leave a Review</h3>
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <?php if (!empty($review_error)): ?>
                        <p class="form-error"><?php echo h($review_error); ?></p>
                    <?php endif; ?>
                    <form method="post">
                        <select name="rating" required>
                            <option value="">Select Rating</option>
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Good</option>
                            <option value="3">3 - Average</option>
                            <option value="2">2 - Poor</option>
                            <option value="1">1 - Very Bad</option>
                        </select>
                        <textarea placeholder="Your Review" name="comment" rows="3" required></textarea>
                        <button type="submit" name="submit_review">Submit Review</button>
                    </form>
                <?php else: ?>
                    <p class="login-note">Please <a href="login.php">log in</a> to write a review.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

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
    <script src="script.js"></script>
<script>
    // Debounce function to limit how often the warning fades out
    let warningTimeout;

    function checkStockLimit(input) {
        const value = parseInt(input.value);
        const maxStock = parseInt(input.getAttribute('data-max-stock'));
        const warningElement = document.getElementById('stockWarningMessage');

        // Clear any existing timeout
        clearTimeout(warningTimeout);

        if (value > maxStock) {
            // 1. Enforce the limit: set the input value back to maxStock
            input.value = maxStock;

            // 2. Show the warning
            warningElement.textContent = `You can only add a maximum of ${maxStock} items to the cart.`;
            warningElement.classList.add('show');

            // 3. Set a timeout to fade the warning out
            warningTimeout = setTimeout(() => {
                warningElement.classList.remove('show');
            }, 3000); // Warning visible for 3 seconds
        } else if (value < 1) {
            // Also ensure the value doesn't drop below 1
            input.value = 1;
            warningElement.classList.remove('show');
        } else {
            // Hide warning if input is valid
            warningElement.classList.remove('show');
        }
    }
</script>

</body>
</html>
