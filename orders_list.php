<?php
$conn = new mysqli("localhost", "root", "", "earthelic");
if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

// Handle actions
if (isset($_GET['action']) && isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    $action   = $_GET['action'];

    $success = false;
    if ($action == "accept") {
        $success = $conn->query("UPDATE Orders SET status='processing' WHERE order_id=$order_id");
    } elseif ($action == "reject") {
        $success = $conn->query("UPDATE Orders SET status='cancelled' WHERE order_id=$order_id");
    } elseif ($action == "shipped") {
        $success = $conn->query("UPDATE Orders SET status='shipped', update_date=NOW() WHERE order_id=$order_id");
    } elseif ($action == "delivered") {
        $success = $conn->query("UPDATE Orders SET status='delivered', update_date=NOW() WHERE order_id=$order_id");
    }

    if (!$success) {
        // Display a detailed error message if the query fails
        die("Error updating status: " . $conn->error);
    }

    header("Location: orders_list.php");
    exit;
}

// Fetch all orders with user info
$sql = "SELECT o.order_id, o.user_id, o.total_amount, o.status, o.order_date, o.update_date,
               u.full_name, u.email
        FROM Orders o
        JOIN Users u ON o.user_id = u.user_id
        ORDER BY o.order_date DESC";
$orders = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Orders - Earthelic Admin</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/orderlist.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="logo-fixed">
        <a href="landing.php">
            <img src="imgs/earthelic logo file png.png" alt="Earthelic Logo">
        </a>
    </div>

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

    <div class="main-content">
        <h1>Manage Orders</h1>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Items</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders->num_rows > 0): ?>
                        <?php while($order = $orders->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $order['order_id']; ?></td>
                                <td><?php echo $order['full_name']; ?></td>
                                <td><?php echo $order['email']; ?></td>
                                <td>
                                    <ul>
                                        <?php
                                        $items_sql = "SELECT oi.quantity, oi.price, p.product_name
                                                      FROM Order_Items oi
                                                      JOIN Products p ON oi.product_id = p.product_id
                                                      WHERE oi.order_id = ".$order['order_id'];
                                        $items = $conn->query($items_sql);
                                        while($item = $items->fetch_assoc()) {
                                            echo "<li>{$item['product_name']} ({$item['quantity']} × ₹{$item['price']})</li>";
                                        }
                                        ?>
                                    </ul>
                                </td>
                                <td>₹<?php echo $order['total_amount']; ?></td>
                                <td><span class="status <?php echo strtolower($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                <td><?php echo $order['update_date']; ?></td>
                                <td><?php echo $order['order_date']; ?></td>
                                <td>
                                    <a href="?action=accept&order_id=<?php echo $order['order_id']; ?>" class="btn accept">Accept</a>
                                    <a href="?action=reject&order_id=<?php echo $order['order_id']; ?>" class="btn reject">Reject</a>
                                    <a href="?action=shipped&order_id=<?php echo $order['order_id']; ?>" class="btn shipped">Shipped</a>
                                    <a href="?action=delivered&order_id=<?php echo $order['order_id']; ?>" class="btn delivered">Delivered</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9">No orders found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>