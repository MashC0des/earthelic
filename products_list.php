<?php
// --- FIX 1: Add error reporting for immediate debugging ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --------------------------------------------------------

include "db_connect.php"; // DB connection + session

// --- FIX 2: Check for successful database connection after include ---
if (!isset($conn) || $conn->connect_error) {
    die("<div class='error-message' style='padding: 20px; background-color: #fdd; border: 1px solid #f99; color: #a00;'>
        <h2>Database Connection Error</h2>
        <p>Could not connect to the database. Please check your 'db_connect.php' file and ensure the database server is running.</p>
        <p>Error: " . (isset($conn) ? $conn->connect_error : "Connection object not defined.") . "</p>
    </div></body></html>");
}
// ---------------------------------------------------------------------

// Fetch categories
$categories = [];
$cat_sql = "SELECT * FROM Categories";
$cat_result = $conn->query($cat_sql);

if ($cat_result === false) {
    echo "<p class='error-message'>Error fetching categories: " . $conn->error . "</p>";
} else {
    while ($cat_row = $cat_result->fetch_assoc()) {
        $categories[$cat_row['category_id']] = $cat_row['category_name'];
    }
}


// Fetch all products
// --- EDITED: Changed primary ORDER BY from 'material' to 'category_id' ---
$sql = "SELECT * FROM Products ORDER BY category_id, material, created_at DESC";
$result = $conn->query($sql);

if ($result === false) {
    echo "<p class='error-message'>Error fetching products: " . $conn->error . "</p>";
    // Initialize $result to an object that has a num_rows property to prevent errors later
    $result = new class { public $num_rows = 0; public function fetch_assoc() {} };
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Products - Earthelic Admin</title>
  <!-- Basic inline styles for the new modal and error message to ensure they display -->
  <style>
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.6);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }
    .modal-content {
        background-color: #fff;
        padding: 30px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        max-width: 400px;
        width: 90%;
    }
    .modal-content p {
        margin-bottom: 20px;
        font-size: 1.1em;
    }
    .modal-actions button {
        margin: 0 10px;
        padding: 10px 20px;
        cursor: pointer;
        border: none;
        border-radius: 8px;
        font-weight: bold;
        transition: transform 0.1s;
    }
    /* Assuming admin.css or managepro.css defines action-btn, delete-btn, edit-btn */
    #modal-confirm { background-color: #e74c3c; color: white; }
    #modal-cancel { background-color: #ccc; color: #333; }
  </style>

  <link rel="stylesheet" href="css/managepro.css">
  <link rel="stylesheet" href="css/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

    <!-- Custom Confirmation Modal (FIX 3: Replacement for confirm()) -->
    <div id="custom-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <p>Are you sure you want to delete this product?</p>
            <div class="modal-actions">
                <button id="modal-confirm" data-product-id="" class="action-btn delete-btn">
                    <i class="fa fa-trash"></i> Delete
                </button>
                <button id="modal-cancel" class="action-btn edit-btn">
                    <i class="fa fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>


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
            <li><a href="custom_requests_list.php" class="active"><i class="fa fa-paint-brush"></i> Custom Requests</a></li>
            <li><a href="users_list.php"><i class="fa fa-users"></i> Manage Users</a></li>
            <li><a href="complaintadmin.php"><i class="fa fa-headset"></i> Manage Complaints</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h1>Manage Products</h1>

        <?php
        // --- EDITED: Grouping by Category ID instead of Material ---
        $current_category_id = "";
        if ($result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                if ($row['category_id'] !== $current_category_id): // Check against category ID
                    if ($current_category_id !== "") {
                        echo "</tbody></table>";
                    }
                    $current_category_id = $row['category_id'];
                    $category_name = isset($categories[$current_category_id]) ? $categories[$current_category_id] : "Uncategorized"; // Get the category name
                    echo "<h2 class='section-title'>".$category_name." Products</h2>"; // Use category name for the heading
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
                        <img src="<?php echo $row['image_url']; ?>" class="prod-img" alt="Product" onerror="this.onerror=null;this.src='https://placehold.co/50x50/cccccc/333333?text=No+Image';">
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
            var $modal = $('#custom-modal');
            var $confirmButton = $('#modal-confirm');
            var currentProductId = null;

            // Edit button functionality
            $('.edit-btn').on('click', function() {
                var productId = $(this).data('id');
                window.location.href = 'upload.php?id=' + productId;
            });

            // 1. Show the custom modal when delete is clicked
            $('.delete-btn').on('click', function() {
                currentProductId = $(this).data('id');
                $confirmButton.data('product-id', currentProductId);
                $modal.fadeIn(200);
            });

            // 2. Hide the modal when cancel is clicked
            $('#modal-cancel').on('click', function() {
                $modal.fadeOut(200);
            });

            // 3. Handle deletion when confirm is clicked
            $confirmButton.on('click', function() {
                var productId = $(this).data('product-id');

                if (productId) {
                    // Hide the modal immediately
                    $modal.fadeOut(100);

                    // Perform AJAX deletion
                    $.ajax({
                        url: 'delete_product.php',
                        type: 'POST',
                        data: { product_id: productId },
                        success: function(response) {
                            if (response.trim() === 'success') {
                                // Reload the page to show the updated list
                                location.reload();
                            } else {
                                alert('Error deleting product: ' + response);
                                console.error('Error deleting product:', response);
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            alert('An error occurred. Please try again.');
                            console.error('An error occurred. Please try again.', textStatus, errorThrown);
                        }
                    });
                } else {
                    console.error("Product ID not found for deletion.");
                }
            });
        });
    </script>
</body>
</html>
