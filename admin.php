<?php
include "db_connect.php"; // ✅ uses your central connection file

// Count total users
$users_result = $conn->query("SELECT COUNT(*) AS total_users FROM Users");
$total_users = $users_result->fetch_assoc()['total_users'];

// Count total products
$products_result = $conn->query("SELECT COUNT(*) AS total_products FROM Products");
$total_products = $products_result->fetch_assoc()['total_products'];

// Count total orders
$orders_result = $conn->query("SELECT COUNT(*) AS total_orders FROM Orders");
$total_orders = $orders_result->fetch_assoc()['total_orders'];

// Sum total revenue
$revenue_result = $conn->query("SELECT IFNULL(SUM(total_amount),0) AS total_revenue FROM Orders WHERE status!='cancelled'");
$total_revenue = $revenue_result->fetch_assoc()['total_revenue'];

// Count today's orders
$today_orders_result = $conn->query("SELECT COUNT(*) AS today_orders FROM Orders WHERE DATE(order_date) = CURDATE()");
$today_orders = $today_orders_result->fetch_assoc()['today_orders'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Earthelic</title>
    <link rel="stylesheet" href="css/admin.css">
     <link rel="stylesheet"
        href="https://db.onlinewebfonts.com/c/ef6bdf5ef216552c7e9869841e891ca0?family=Arial+Rounded+MT+Bold">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="admin.php" class="active"><i class="fa fa-home"></i> Dashboard</a></li>
            <li><a href="upload.php"><i class="fa fa-upload"></i> Upload Product</a></li>
            <li><a href="products_list.php"><i class="fa fa-box"></i> Manage Products</a></li>
            <li><a href="orders_list.php"><i class="fa fa-shopping-cart"></i> Manage Orders</a></li>
            <li><a href="users_list.php"><i class="fa fa-users"></i> Manage Users</a></li>
            <li><a href="complaintadmin.php"><i class="fa fa-headset"></i> Manage Complaints</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h1>Welcome, Admin 👋</h1>
        <div class="cards">
            <div class="card">
                <h3>Total Products</h3>
                <p><?php echo $total_products; ?></p>
            </div>
            <div class="card">
                <h3>Total Orders</h3>
                <p><?php echo $total_orders; ?></p>
            </div>
            <div class="card">
                <h3>Total Users</h3>
                <p><?php echo $total_users; ?></p>
            </div>
            <div class="card">
                <h3>Revenue</h3>
                <p>₹<?php echo number_format($total_revenue, 2); ?></p>
            </div>
            <div class="card">
                <h3>Today's Orders</h3>
                <p><?php echo $today_orders; ?></p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; 2024 Earthelic.com</p>
    </footer>
</body>
</html>
