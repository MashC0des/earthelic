<?php
session_start();
include "db_connect.php";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$edit_message = ''; // Variable to hold immediate form submission errors/feedback

// --- START: PROFILE EDITING LOGIC (Unified from update_profile.php) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    
    // 3. Collect and sanitize input data
    $new_full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $new_email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $new_phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null; // Allow phone to be null/empty

    // 4. Input validation
    if (empty($new_full_name)) {
        $edit_message = "Error: Full Name is required.";
    } elseif (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $edit_message = "Error: A valid Email is required.";
    } else {
        // 5. Check if the new email already exists for another user (Email must be unique)
        $stmt_check = $conn->prepare("SELECT user_id FROM Users WHERE email = ? AND user_id != ?");
        $stmt_check->bind_param("si", $new_email, $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $edit_message = "Error: This email is already registered to another account.";
        } else {
            // 6. Prepare and execute the update statement
            $sql = "UPDATE Users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?";
            $stmt_update = $conn->prepare($sql);
            
            if ($stmt_update === false) {
                error_log("Prepare failed: " . $conn->error);
                $edit_message = "Error: Database preparation failed.";
            } else {
                $stmt_update->bind_param("sssi", $new_full_name, $new_email, $new_phone, $user_id);

                if ($stmt_update->execute()) {
                    // Update successful
                    
                    // Crucial: Update the session variable for the name displayed in the header
                    $_SESSION['full_name'] = $new_full_name;
                    
                    // Set a success message and redirect to refresh the page/data
                    $_SESSION['update_success'] = "Profile details were successfully updated!";

                    // POST/REDIRECT/GET pattern to prevent form resubmission
                    header("Location: profile.php");
                    exit();

                } else {
                    // Update failed
                    error_log("Execute failed: " . $stmt_update->error);
                    $edit_message = "Error: Failed to save changes. Please try again later.";
                }
                $stmt_update->close();
            }
        }
        $stmt_check->close();
    }
    
    // If we reach here, there was an immediate error (like validation failure), 
    // so we set the message in session to persist across the non-redirected render.
    if (!empty($edit_message)) {
        $_SESSION['edit_error'] = $edit_message;
    }
}
// --- END: PROFILE EDITING LOGIC ---


// Handle potential successful update message from session
$update_message = '';
if (isset($_SESSION['update_success'])) {
    $update_message = $_SESSION['update_success'];
    unset($_SESSION['update_success']);
}

// Handle potential error messages from the immediate POST (if no redirect happened)
if (isset($_SESSION['edit_error'])) {
    $edit_message = $_SESSION['edit_error'];
    unset($_SESSION['edit_error']);
}


// Fetch user details (This runs AFTER the POST logic, ensuring we get the latest data on a new GET request)
$stmt = $conn->prepare("SELECT full_name, email, phone, role, created_at, profile_picture FROM Users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch user orders
$order_sql = "SELECT order_id, total_amount, status, order_date 
              FROM Orders WHERE user_id = ? ORDER BY order_date DESC";
$stmt = $conn->prepare($order_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Earthelic</title>
    <link rel="stylesheet" href="css/profile.css">
        <link rel="stylesheet" href="css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .track-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 12px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin: 4px 2px;
            cursor: pointer;
            border-radius: 4px;
            transition-duration: 0.4s;
        }

        .track-btn:hover {
            background-color: #45a049;
        }
        
        /* Basic modal styles */
        .modal-input {
            width: 100%;
            padding: 10px;
            margin: 8px 0 16px 0;
            display: inline-block;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
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

    <div class="profile-container">
        <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?> 👋</h2>
        
        <?php if (!empty($update_message)): ?>
            <p style="color: green; background-color: #e6ffe6; padding: 10px; border-radius: 5px; text-align: center;"><?php echo $update_message; ?></p>
        <?php endif; ?>
        
        <?php if (!empty($edit_message)): ?>
            <p style="color: red; background-color: #ffe6e6; padding: 10px; border-radius: 5px; text-align: center;"><?php echo $edit_message; ?></p>
        <?php endif; ?>
        
        <div class="profile-pic-box">
            <img src="<?php echo (!empty($user['profile_picture'])) 
                            ? htmlspecialchars($user['profile_picture'])
                            : 'imgs/default-profile.png'; ?>" 
                 alt="Profile Picture" class="profile-pic" id="profilePic">
            <p>Click image to change</p>
        </div>

        <div class="user-info">
            <h3><i class="fa fa-user"></i> My Details</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></p>
            <p><strong>Member Since:</strong> <?php echo htmlspecialchars(date("d M Y", strtotime($user['created_at']))); ?></p>
            
            <button id="editProfileBtn" class="btn"><i class="fa fa-edit"></i> Edit Profile</button>
            <a href="forgot_password.php" class="btn"><i class="fa fa-key"></i> Change Password</a>
            <a href="logout.php" class="btn logout"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="orders-section">
            <h3><i class="fa fa-shopping-cart"></i> My Orders</h3>
            <?php if ($orders->num_rows > 0): ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $orders->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($row['order_id']); ?></td>
                            <td>₹<?php echo htmlspecialchars(number_format($row['total_amount'], 2)); ?></td>
                            <td><span class="status <?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst(htmlspecialchars($row['status'])); ?></span></td>
                            <td><?php echo htmlspecialchars(date("d M Y H:i", strtotime($row['order_date']))); ?></td>
                            <td>
                                <form action="tracking.php" method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($row['order_id']); ?>">
                                    <button type="submit" class="track-btn">Track</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No orders yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close edit-close">&times;</span>
            <h3>Edit My Profile</h3>
            <!-- ACTION changed to self-submit (profile.php) and METHOD is POST -->
            <form method="POST" action="profile.php">
                <!-- Hidden field to identify the form submission -->
                <input type="hidden" name="edit_profile" value="1">
            
                <label for="full_name">Full Name</label>
                <!-- Note: The form values are now guaranteed to be the latest data from the database fetch -->
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required class="modal-input">

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="modal-input">

                <label for="phone">Phone (Optional)</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="modal-input">

                <button type="submit" class="btn track-btn" style="margin-top: 10px;">Save Changes</button>
            </form>
            <!-- The result message is now handled by the PHP block above (edit_message/update_message) -->
        </div>
    </div>
    
    <!-- Existing Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close upload-close">&times;</span>
            <h3>Upload New Profile Picture</h3>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="file" name="profile_picture" accept="image/*" required>
                <button type="submit">Upload</button>
            </form>
            <p id="uploadMsg"></p>
        </div>
    </div>

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
<script>
$(document).ready(function(){
    // --- Modal Control Logic ---
    
    // Open profile picture modal
    $("#profilePic").click(function(){
        $("#uploadModal").fadeIn();
    });
    
    // Open edit profile modal
    $("#editProfileBtn").click(function(){
        $("#editModal").fadeIn();
    });

    // Close all modals by clicking the .close span
    $(".modal .close").click(function(){
        $(this).closest('.modal').fadeOut();
    });
    
    // Close modal if user clicks outside of it
    $(window).click(function(event) {
      if ($(event.target).is('.modal')) {
        // Only fade out if the modal wasn't just opened due to an error.
        // For simplicity with the PHP POST, we rely on the PHP messages 
        // being displayed after the page reload or immediate POST.
        $('.modal').fadeOut();
      }
    });

    // --- AJAX Logic (Only for Profile Picture Upload - Edit is now a standard POST) ---

    // AJAX Profile Picture Upload (Existing)
    $("#uploadForm").on("submit", function(e){
        e.preventDefault();
        var formData = new FormData(this);
        $.ajax({
            url: "upload_profile_pic.php",
            type: "POST",
            data: formData,
            contentType:false,
            processData:false,
            success:function(response){
                $("#uploadMsg").html(response);
                setTimeout(()=>{ location.reload(); },1500);
            }
        });
    });
    
    // If there was an immediate error from the POST submission, 
    // the page reloads, and we check if the error message is present. 
    // If it is, we automatically show the modal again so the user can fix the input.
    if ($('.profile-container p:contains("Error:")').length > 0) {
        // We look for any red error message displayed in the container
        $("#editModal").fadeIn();
    }
});
</script>
</body>
</html>
