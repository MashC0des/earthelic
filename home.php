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

    // --- MODIFIED: START (Collect Address and UPI ID fields) ---
    $shipping_address = $_POST['shipping_address'] ?? '';
    $city = $_POST['city'] ?? '';
    $zip_code = $_POST['zip_code'] ?? '';
    $customer_upi_id = $_POST['customer_upi_id'] ?? null; // Collect specific UPI ID (optional)
    // --- MODIFIED: END ---
    
    // Determine user ID (NULL if not logged in, matching the Custom_Requests table definition)
    $user_id = $_SESSION['user_id'] ?? null; 
    
    // --- MODIFIED: START (Server-side validation for length and format) ---
    // Define limits and patterns (must match user requirements)
    $limits = [
        'product_name' => 15, 'description' => 250, 'contact_email' => 30,
        'shipping_address' => 35, 'city' => 15, 'zip_code' => 6, 'customer_upi_id' => 25
    ];
    // Zip Code must be exactly 6 digits
    $zip_pattern = '/^\d{6}$/'; 
    // MODIFIED: Improved UPI VPA format check: handle@psp, ensuring at least one character on either side of @
    $upi_pattern = '/^[a-zA-Z0-9.\-_]+@[a-zA-Z0-9.\-]+$/'; 

    // Server-side Required Field & Length/Format Validation
    if (empty($product_type) ||
        empty($product_name) || strlen($product_name) > $limits['product_name'] ||
        empty($description) || strlen($description) > $limits['description'] ||
        empty($contact_email) || strlen($contact_email) > $limits['contact_email'] || !filter_var($contact_email, FILTER_VALIDATE_EMAIL) ||
        empty($shipping_address) || strlen($shipping_address) > $limits['shipping_address'] ||
        empty($city) || strlen($city) > $limits['city'] ||
        // CRITICAL: Check if zip_code is exactly 6 digits (numeric only)
        empty($zip_code) || !preg_match($zip_pattern, $zip_code) 
    ) {
        // Consolidated error message for required and format issues
        $response['message'] = "Please check all required fields. Ensure lengths (max 15 for name, 250 for description) and formats (6-digit numeric Zip Code, valid Email) are correct.";
        echo json_encode($response);
        $conn->close();
        exit;
    }

    // UPI Validation (Optional but must be formatted correctly if present)
    if (!empty($customer_upi_id)) {
        if (strlen($customer_upi_id) > $limits['customer_upi_id'] || !preg_match($upi_pattern, $customer_upi_id)) {
            $response['message'] = "The UPI ID provided is either too long (max 25 chars) or not in a valid format (e.g., name@bank).";
            echo json_encode($response);
            $conn->close();
            exit;
        }
    }
    // --- MODIFIED: END (Server-side validation) ---

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
    $sql = "INSERT INTO Custom_Requests (user_id, product_type, product_name, description, contact_email, reference_image_path, shipping_address, city, zip_code, customer_upi_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);

    // Bind parameters (i = integer, s = string). Total 10 parameters now: i + 9s
    $types = "isssssssss"; 
    $bind_user_id = $user_id;
    $bind_image_path = $reference_image_path;
    $bind_upi_id = $customer_upi_id;

    $stmt->bind_param($types, 
        $bind_user_id, 
        $product_type, 
        $product_name, 
        $description, 
        $contact_email, 
        $bind_image_path,
        $shipping_address,
        $city,
        $zip_code,
        $bind_upi_id // Bound UPI ID
    );
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Your custom request for '{$product_name}' has been successfully submitted! We will contact you soon with a quote, based on the address and payment preference provided.";
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
        /* --- MODIFIED: START (Ensured styling applies to all form inputs) --- */
        #custom-product-form label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }
        #custom-product-form input[type="text"], 
        #custom-product-form input[type="email"], 
        #custom-product-form select, 
        #custom-product-form textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            box-sizing: border-box; /* Ensures padding doesn't affect overall width */
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        /* Specific style for the numeric input to remove browser default number spinners */
        #custom-product-form input[type=number]::-webkit-inner-spin-button,
        #custom-product-form input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        #custom-product-form .success {
            color: green;
            font-weight: bold;
            padding: 10px;
            background-color: #e6ffe6;
            border: 1px solid green;
            border-radius: 4px;
            margin-top: 10px;
        }
        #custom-product-form .error {
            color: red;
            font-weight: bold;
            padding: 10px;
            background-color: #ffe6e6;
            border: 1px solid red;
            border-radius: 4px;
            margin-top: 10px;
        }
        /* --- MODIFIED: END --- */
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
                                    <a href="productpage.php?id=<?php echo $row['product_id']; ?>" class="btnv">View</a>
                                </div>
                            <?php endwhile; ?>
                            
                            <a href="<?php echo ucfirst($category); ?>.php" id="morebtn">more  <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
                                 <h3 style="padding-left:30%; color: azure;">Create Your Own Decor</h3>
        <!-- CUSTOM PRODUCT SECTION -->
        <section id="custom-product-section">
            <h2>Unleash Your Creativity!</h2>
            <p>Design your own custom ceramic piece or a unique canvas painting. Tell us exactly what you envision!</p>
            <button id="toggleCustomFormBtn">Design Your Own <i class="fa-solid fa-paintbrush"></i></button>

            <!-- ACTION IS NOW home.php -->
            <form id="custom-product-form" action="home.php" method="POST" enctype="multipart/form-data">
                <h3>Custom Product Request</h3>
                
                
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
                <label for="product_name">Desired Product Name (Max 15 Chars):</label>
                <input type="text" id="product_name" name="product_name" placeholder="E.g., Custom Blue Ceramic Mug" required maxlength="15">

                <!-- Corresponds to description (TEXT) -->
                <label for="description">Design Description/Details (Size, Color, Pattern, Grade - Max 250 Chars):</label>
                <textarea id="description" name="description" rows="5" placeholder="Describe the size, color, pattern, material grade, or any specific details for your custom design." required maxlength="250"></textarea>

                <!-- Corresponds to reference_image_path (VARCHAR) -->
                <label for="reference_image">Upload Reference Image (Optional):</label>
                <input type="file" id="reference_image" name="reference_image" accept="image/*">
                
                <!-- Corresponds to contact_email (VARCHAR) -->
                <label for="contact_email">Your Email (for contact - Max 30 Chars):</label>
                <input type="email" id="contact_email" name="contact_email" value="<?php echo $isLoggedIn ? htmlspecialchars($_SESSION['email'] ?? '') : ''; ?>" placeholder="name@example.com" required maxlength="30">
                
                <!-- --- MODIFIED: START (Address Section with Numeric-only Zip Code) --- -->
                <hr>
                <h4>Shipping Address for Quote *</h4>
                <p><small>We need this to calculate shipping costs and provide an accurate quote.</small></p>
                <label for="shipping_address">Street Address / P.O. Box (Max 35 Chars):</label>
                <input type="text" id="shipping_address" name="shipping_address" placeholder="123 Main St" required maxlength="35">

                <label for="city">City (Max 15 Chars):</label>
                <input type="text" id="city" name="city" placeholder="E.g., Mumbai" required maxlength="15">

                <label for="zip_code">Zip/Postal Code (6 Digits):</label>
                <!-- 
                    MODIFIED: Added oninput filter to immediately remove any non-digit characters. 
                    The pattern="\d{6}" and client/server JS/PHP validation still enforce 
                    exactly 6 digits upon submission.
                -->
                <input type="text" id="zip_code" name="zip_code" placeholder="E.g., 400001" required maxlength="6" pattern="\d{6}" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6)">
                <!-- --- MODIFIED: END (Address Section with Numeric-only Zip Code) --- -->
                
                <!-- --- MODIFIED: START (UPI ID Input Section with Max Length) --- -->
                <hr>
                <h4>Preferred Payment Method: UPI</h4>
                <p><small>Enter your **UPI ID (VPA)** if you prefer this method for the final payment. We will use this to send a payment request after you accept the quote. (Max 25 Chars)</small></p>
                
                <label for="customer_upi_id">Your UPI ID (Optional):</label>
                <input type="text" id="customer_upi_id" name="customer_upi_id" placeholder="e.g., yourname@bankname or 9876543210@paytm" maxlength="25">
                <!-- --- MODIFIED: END (UPI ID Input Section with Max Length) --- -->
                
                <button type="submit" id="submitRequestBtn">Submit Request</button>
            </form>
            <div id="ajax-message"></div>
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
        // Ensure the form starts hidden (in CSS or inline style)
        form.style.display = 'none'; 
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

            const submitBtn = document.getElementById('submitRequestBtn');

            // --- MODIFIED: START (Client-side validation for UPI and Zip Code) ---
            messageDiv.style.display = 'none';
            messageDiv.className = '';

            const zipCodeInput = document.getElementById('zip_code');
            const zipCodeValue = zipCodeInput.value.trim();
            // Pattern remains the same: exactly 6 digits, numeric only
            const zipCodePattern = /^\d{6}$/; 

            // Check if zip code is valid (must be 6 numeric digits)
            if (!zipCodePattern.test(zipCodeValue)) {
                // The oninput handler prevents non-numeric input, so this error 
                // primarily guards against non-6-digit input.
                messageDiv.textContent = 'Zip/Postal Code must be exactly 6 numeric digits.';
                messageDiv.className = 'error';
                messageDiv.style.display = 'block';
                return; // Stop submission
            }

            const upiInput = document.getElementById('customer_upi_id');
            const upiValue = upiInput.value.trim();
            // MODIFIED: Improved UPI VPA format check: handle@psp
            const upiPattern = /^[a-zA-Z0-9.\-_]+@[a-zA-Z0-9.\-]+$/;

            // Check if UPI ID is entered and invalid
            if (upiValue !== '' && !upiPattern.test(upiValue)) {
                messageDiv.textContent = 'Please enter a valid UPI ID in handle@psp format (e.g., name@bank). Max 25 characters.';
                messageDiv.className = 'error';
                messageDiv.style.display = 'block';
                return; // Stop submission
            }
            // --- MODIFIED: END (Client-side validation) ---
            
            const formData = new FormData(form);

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
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
