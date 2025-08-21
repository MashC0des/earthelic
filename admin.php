<?php
session_start();

// Check if user is logged in & is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Earthelic</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <!-- Fixed Logo -->
    <div class="logo-fixed">
        <a href="landing.php">
            <img src="imgs/earthelic logo file png.png" alt="Earthelic Logo">
        </a>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="admin.php"><i class="fa fa-home"></i> Dashboard</a></li>
            <li><a href="upload.php"><i class="fa fa-upload"></i> Upload Product</a></li>
            <li><a href="products_list.php"><i class="fa fa-box"></i> Manage Products</a></li>
            <li><a href="categories_list.php"><i class="fa fa-list"></i> Manage Categories</a></li>
            <li><a href="orders_list.php"><i class="fa fa-shopping-cart"></i> Manage Orders</a></li>
            <li><a href="users_list.php"><i class="fa fa-users"></i> Manage Users</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h1>Welcome, Admin 👋</h1>
        <div class="cards">
            <div class="card">
                <h3>Total Products</h3>
                <p>120</p>
            </div>
            <div class="card">
                <h3>Total Orders</h3>
                <p>45</p>
            </div>
            <div class="card">
                <h3>Total Users</h3>
                <p>80</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; 2024 Earthelic.com</p>
    </footer>
</body>
</html>
