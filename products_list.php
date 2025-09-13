<?php
include "db_connect.php"; // DB connection + session

// Fetch categories
$categories = [];
$cat_sql = "SELECT * FROM Categories";
$cat_result = $conn->query($cat_sql);
while ($cat_row = $cat_result->fetch_assoc()) {
    $categories[$cat_row['category_id']] = $cat_row['category_name'];
}

// Fetch all products
$sql = "SELECT * FROM Products ORDER BY material, category_id, created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Products - Earthelic Admin</title>
  <link rel="stylesheet" href="css/managepro.css">
  <link rel="stylesheet" href="css/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <h1>Manage Products</h1>

        <?php
        $current_material = "";
        if ($result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                if ($row['material'] !== $current_material):
                    if ($current_material !== "") {
                        echo "</tbody></table>";
                    }
                    $current_material = $row['material'];
                    echo "<h2 class='section-title'>".ucfirst($current_material)." Products</h2>";
                    echo "<table>
                            <thead>
                              <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Actions</th>
                              </tr>
                            </thead>
                            <tbody>";
                endif;
        ?>
                <tr>
                    <td><?php echo $row['product_id']; ?></td>
                    <td>
                        <img src="<?php echo $row['image_url']; ?>" class="prod-img" alt="Product">
                    </td>
                    <td><strong><?php echo $row['product_name']; ?></strong></td>
                    <td><?php echo isset($categories[$row['category_id']]) ? $categories[$row['category_id']] : "-"; ?></td>
                    <td><?php echo substr($row['description'], 0, 50) . "..."; ?></td>
                    <td><b>₹<?php echo $row['price']; ?></b></td>
                    <td><?php echo $row['stock_quantity']; ?></td>
                    <td>
                        <button class="action-btn edit-btn" data-id="<?php echo $row['product_id']; ?>"><i class="fa fa-edit"></i> Edit</button>
                        <button class="action-btn delete-btn" data-id="<?php echo $row['product_id']; ?>"><i class="fa fa-trash"></i> Delete</button>
                    </td>
                </tr>
        <?php
            endwhile;
            echo "</tbody></table>";
        else:
            echo "<p>No products found.</p>";
        endif;
        ?>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; 2024 Earthelic.com</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Edit button functionality
            $('.edit-btn').on('click', function() {
                var productId = $(this).data('id');
                window.location.href = 'upload.php?id=' + productId;
            });

            // Delete button functionality (now uses a simple modal instead of confirm)
            $('.delete-btn').on('click', function() {
                var productId = $(this).data('id');
                // Use a simple custom modal or a hidden form for confirmation
                if (confirm('Are you sure you want to delete this product?')) {
                    $.ajax({
                        url: 'delete_product.php',
                        type: 'POST',
                        data: { product_id: productId },
                        success: function(response) {
                            if (response.trim() === 'success') {
                                location.reload();
                            } else {
                                console.error('Error deleting product:', response);
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('An error occurred. Please try again.', textStatus, errorThrown);
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
