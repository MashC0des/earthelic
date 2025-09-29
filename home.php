<?php
session_start();
include "db_connect.php"; // DB connection

// --- START AJAX POST REQUEST HANDLER ---
// This code block handles the form submission when the user clicks 'Submit Request'
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['is_ajax_request'])) {
    
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    // 1. Collect and Validate Data
    $product_type = $_POST['product_type'] ?? '';
    $product_name = $_POST['product_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $contact_email = $_POST['contact_email'] ?? '';
    
    // Determine user ID (NULL if not logged in, matching the Custom_Requests table definition)
    $user_id = $_SESSION['user_id'] ?? null; 

    // Basic required field validation
    if (empty($product_type) || empty($product_name) || empty($description) || empty($contact_email)) {
        $response['message'] = "Please fill out all required fields.";
        echo json_encode($response);
        $conn->close();
        exit;
    }

    // 2. Handle File Upload (reference_image)
    $reference_image_path = null;
    if (isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'custom_uploads/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); 
        }

        $file_info = pathinfo($_FILES['reference_image']['name']);
        $file_ext = strtolower($file_info['extension']);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed_ext)) {
            $new_file_name = uniqid('custom_') . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES['reference_image']['tmp_name'], $destination)) {
                $reference_image_path = $destination; // Path to store in DB
            } 
        }
    }

    // 3. The SQL INSERT Query
    // Note: The 'status' and 'request_date' columns use defaults defined in the SQL schema.
    $sql = "INSERT INTO Custom_Requests (user_id, product_type, product_name, description, contact_email, reference_image_path) 
            VALUES (?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);

    // Bind parameters (i = integer, s = string). user_id is the only integer.
    $types = "isssss"; 
    $bind_user_id = $user_id;
    $bind_image_path = $reference_image_path;

    $stmt->bind_param($types, 
        $bind_user_id, 
        $product_type, 
        $product_name, 
        $description, 
        $contact_email, 
        $bind_image_path
    );
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Your custom request for '{$product_name}' has been successfully submitted! We will contact you soon.";
    } else {
        // Provide the specific error for debugging
        $response['message'] = "Error submitting request. Please try again. Database Error: " . $stmt->error;
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode($response);
    exit; // CRITICAL: Stop execution to prevent the HTML page from loading into the AJAX response
}
// --- END AJAX POST REQUEST HANDLER ---


// Check login session (runs only if the above block didn't exit)
$isLoggedIn = isset($_SESSION['user_id']);
$fullName = $isLoggedIn ? $_SESSION['full_name'] : null;
$profilePic = $isLoggedIn && !empty($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : "imgs/default_profile.png";

// Function to fetch best sellers per category (existing code)
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earthelic.com</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/home.css">
    <!-- Include Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> 
    <style>
        /* Basic styles for the new section - you might move these to home.css */
   
    </style>
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
        <span id="ea">Earthelic</span><br>
        <h3> Excellence in Ceramics & Metal Products</h3>
        <p>
            Explore our diverse range of high-quality ceramic and metal products, designed to meet your needs with durability and style.
        </p>
        
        <!-- BEST SELLERS SECTION -->
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
                            
                            <a href="<?php echo ucfirst($category); ?>.php" id="morebtn">more  <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
        
        <!-- CUSTOM PRODUCT SECTION -->
        <section id="custom-product-section">
            <h2>Unleash Your Creativity!</h2>
            <p>Design your own custom ceramic piece or a unique canvas painting. Tell us exactly what you envision!</p>
            <button id="toggleCustomFormBtn">Design Your Own <i class="fa-solid fa-paintbrush"></i></button>

            <!-- ACTION IS NOW home.php -->
            <form id="custom-product-form" action="home.php" method="POST" enctype="multipart/form-data">
                <h3>Custom Product Request</h3>
                <div id="ajax-message"></div>
                
                <!-- Hidden field to identify this as an AJAX POST request -->
                <input type="hidden" name="is_ajax_request" value="1"> 

                <!-- Corresponds to product_type (VARCHAR) -->
                <label for="product_type">Product Type:</label>
                <select id="product_type" name="product_type" required>
                    <option value="">Select a type</option>
                    <option value="ceramic">Ceramic</option>
                    <option value="canvas">Painting / Wall Art</option>
                </select>

                <!-- Corresponds to product_name (VARCHAR) -->
                <label for="product_name">Desired Product Name:</label>
                <input type="text" id="product_name" name="product_name" placeholder="E.g., Custom Blue Ceramic Mug" required>

                <!-- Corresponds to description (TEXT) -->
                <label for="description">Design Description/Details (Size, Color, Pattern, Grade):</label>
                <textarea id="description" name="description" rows="5" placeholder="Describe the size, color, pattern, material grade, or any specific details for your custom design." required></textarea>

                <!-- Corresponds to reference_image_path (VARCHAR) -->
                <label for="reference_image">Upload Reference Image (Optional):</label>
                <input type="file" id="reference_image" name="reference_image" accept="image/*">
                
                <!-- Corresponds to contact_email (VARCHAR) -->
                <label for="contact_email">Your Email (for contact):</label>
                <input type="email" id="contact_email" name="contact_email" value="<?php echo $isLoggedIn ? htmlspecialchars($_SESSION['email'] ?? '') : ''; ?>" placeholder="name@example.com" required>
                
                <button type="submit" id="submitRequestBtn">Submit Request</button>
            </form>
        </section>
        <!-- END CUSTOM PRODUCT SECTION -->

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
<script src="script.js"></script>
<script>
    // JavaScript/jQuery for toggling the form and handling AJAX submission
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('custom-product-form');
        const toggleBtn = document.getElementById('toggleCustomFormBtn');
        const messageDiv = document.getElementById('ajax-message');
        // Define the target URL as the current page
        const targetUrl = 'home.php'; 

        // Function to toggle the form visibility
        toggleBtn.addEventListener('click', function() {
            // Check for display value 'none' or empty string (default for hidden elements)
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
                toggleBtn.innerHTML = 'Hide Form <i class="fa-solid fa-chevron-up"></i>';
            } else {
                form.style.display = 'none';
                toggleBtn.innerHTML = 'Design Your Own <i class="fa-solid fa-paintbrush"></i>';
            }
            messageDiv.style.display = 'none'; // Clear message on toggle
        });

        // Function to handle AJAX form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const formData = new FormData(form);
            const submitBtn = document.getElementById('submitRequestBtn');

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            messageDiv.style.display = 'none';
            messageDiv.className = '';

            // Using the Fetch API to send data asynchronously to the current page
            fetch(targetUrl, { 
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check for HTTP errors
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Handle the JSON response from the PHP handler at the top of home.php
                if (data.success) {
                    messageDiv.textContent = data.message;
                    messageDiv.className = 'success';
                    form.reset(); // Clear the form on success
                } else {
                    messageDiv.textContent = data.message || 'An unknown error occurred during submission.';
                    messageDiv.className = 'error';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messageDiv.textContent = 'There was a network or server error. Please try again.';
                messageDiv.className = 'error';
            })
            .finally(() => {
                // Restore button state and show message
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Request';
                messageDiv.style.display = 'block';
            });
        });
    });
</script>
   
</body>
</html>
