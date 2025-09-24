<?php
session_start();
include "db_connect.php";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details
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
     <div class="hamburger" onclick="toggleNav()">
            <i class="fa-solid fa-bars"></i>
        </div>
</header>

    <div class="profile-container">
        <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?> 👋</h2>
        
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
            <p><strong>Role:</strong> <?php echo ucfirst(htmlspecialchars($user['role'])); ?></p>
            <p><strong>Member Since:</strong> <?php echo htmlspecialchars(date("d M Y", strtotime($user['created_at']))); ?></p>
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

    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
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
    // Open modal
    $("#profilePic").click(function(){
        $("#uploadModal").fadeIn();
    });

    // Close modal
    $(".close").click(function(){
        $("#uploadModal").fadeOut();
    });

    // AJAX Upload
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
});
</script>
</body>
</html>