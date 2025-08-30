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
    <title>User Profile - Earthelic</title>
    <link rel="stylesheet" href="css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <!-- Header -->
    <header class="head1">
        <a href="landing.html"><img src="imgs/earthelic logo file png.png" alt="Earthelic Logo" id="logo1"></a>
    </header>

    <!-- Profile Container -->
    <div class="profile-container">
        <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?> 👋</h2>
        
        <!-- Profile Image -->
       <div class="profile-pic-box">
    <img src="<?php echo (!empty($user['profile_picture'])) 
                    ? $user['profile_picture'] 
                    : 'imgs/default-profile.png'; ?>" 
         alt="Profile Picture" class="profile-pic" id="profilePic">
    <p>Click image to change</p>
</div>

        <!-- User Info -->
        <div class="user-info">
            <h3><i class="fa fa-user"></i> My Details</h3>
            <p><strong>Name:</strong> <?php echo $user['full_name']; ?></p>
            <p><strong>Email:</strong> <?php echo $user['email']; ?></p>
            <p><strong>Phone:</strong> <?php echo $user['phone'] ?: 'N/A'; ?></p>
            <p><strong>Role:</strong> <?php echo ucfirst($user['role']); ?></p>
            <p><strong>Member Since:</strong> <?php echo date("d M Y", strtotime($user['created_at'])); ?></p>
            <a href="forgot-password.php" class="btn"><i class="fa fa-key"></i> Change Password</a>
            <a href="logout.php" class="btn logout"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>

        <!-- Orders -->
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $orders->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $row['order_id']; ?></td>
                            <td>₹<?php echo number_format($row['total_amount'], 2); ?></td>
                            <td><span class="status <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                            <td><?php echo date("d M Y H:i", strtotime($row['order_date'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No orders yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for Upload -->
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
        <p>&copy; 2024 Earthelic.com</p>
    </footer>

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
