<?php
include "db_connect.php"; // DB connection + session

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
// Initialize variables
$product_data = null;
$error = "";
$success = "";
$form_title = "Upload New Product";
$button_text = "Add Product";
$is_edit_mode = false;

// Check if a product ID is passed in the URL to pre-fill the form (EDIT mode)
if (isset($_GET['id'])) {
    $is_edit_mode = true;
    $form_title = "Edit Product";
    $button_text = "Update Product";
    $product_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM Products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $product_data = $result->fetch_assoc();
    } else {
        $error = "Product not found.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id    = $_POST['category_id'];
    $product_name   = $_POST['product_name'];
    $description    = $_POST['description'];
    $price          = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'];
    $material       = $_POST['material'];
    $image_url      = isset($_POST['current_image_url']) ? $_POST['current_image_url'] : ""; // For edit mode

    // Check for image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . time() . "_" . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $allowedTypes = ["jpg", "jpeg", "png", "gif"];

        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $image_url = $targetFilePath;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    } elseif (!$is_edit_mode) {
        $error = "Please upload an image.";
    }

    if (empty($error)) {
        if ($is_edit_mode) {
            // It's an UPDATE operation
            $product_id = $_POST['product_id'];
            $stmt = $conn->prepare("UPDATE Products
                SET category_id = ?, product_name = ?, description = ?, price = ?, stock_quantity = ?, material = ?, image_url = ?
                WHERE product_id = ?");
            $stmt->bind_param("issdissi",
                $category_id, $product_name, $description, $price, $stock_quantity, $material, $image_url, $product_id);

            if ($stmt->execute()) {
                $success = "Product updated successfully!";
                // Re-fetch data to show the updated information on the form
                $product_data = [
                    'product_id' => $product_id,
                    'category_id' => $category_id,
                    'product_name' => $product_name,
                    'description' => $description,
                    'price' => $price,
                    'stock_quantity' => $stock_quantity,
                    'material' => $material,
                    'image_url' => $image_url
                ];
            } else {
                $error = "Database error: " . $stmt->error;
            }
        } else {
            // It's an INSERT operation
            $stmt = $conn->prepare("INSERT INTO Products (category_id, product_name, description, price, stock_quantity, material, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdiss",
                $category_id, $product_name, $description, $price, $stock_quantity, $material, $image_url);

            if ($stmt->execute()) {
                $success = "New product added successfully!";
            } else {
                $error = "Database error: " . $stmt->error;
            }
        }
    }
}

// Fetch categories for the dropdown
$categories = [];
$cat_sql = "SELECT * FROM Categories";
$cat_result = $conn->query($cat_sql);
while ($cat_row = $cat_result->fetch_assoc()) {
    $categories[] = $cat_row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($form_title); ?> - Earthelic</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/upload.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
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
            <li><a href="refunds_list.php" class="active"><i class="fa fa-receipt"></i> Manage Refunds</a></li> <!-- Added new item -->
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

<div class="main-content">
    <div class="upload-container" style="background: #ffffff38;">
        <h2><?php echo htmlspecialchars($form_title); ?></h2>

        <!-- success/error messages -->
        <?php if (!empty($success)): ?>
            <p class='success'>✅ <?php echo $success; ?></p>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <p class='error'>❌ <?php echo $error; ?></p>
        <?php endif; ?>

        <form action="upload.php<?php echo $is_edit_mode ? '?id=' . htmlspecialchars($product_data['product_id']) : ''; ?>" method="POST" enctype="multipart/form-data">
            <?php if ($is_edit_mode && $product_data): ?>
                <input type="hidden" name="product_id" value="<?php echo $product_data['product_id']; ?>">
                <input type="hidden" name="current_image_url" value="<?php echo $product_data['image_url']; ?>">
            <?php endif; ?>

            <label>Product Name:</label>
            <input type="text" name="product_name" value="<?php echo $is_edit_mode ? htmlspecialchars($product_data['product_name']) : ''; ?>" required>

            <label>Description:</label>
            <textarea name="description" required><?php echo $is_edit_mode ? htmlspecialchars($product_data['description']) : ''; ?></textarea>

            <label>Price:</label>
            <input type="number" step="0.01" name="price" value="<?php echo $is_edit_mode ? $product_data['price'] : ''; ?>" required>

            <label>Stock Quantity:</label>
            <input type="number" name="stock_quantity" value="<?php echo $is_edit_mode ? $product_data['stock_quantity'] : ''; ?>" required>

            <label>Material:</label>
            <select name="material" required>
                <option value="ceramic" <?php echo ($is_edit_mode && $product_data['material'] == 'ceramic') ? 'selected' : ''; ?>>Ceramic</option>
                <option value="metal" <?php echo ($is_edit_mode && $product_data['material'] == 'metal') ? 'selected' : ''; ?>>Metal</option>
                <option value="canvas" <?php echo ($is_edit_mode && $product_data['material'] == 'canvas') ? 'selected' : ''; ?>>Canvas</option>
                <option value="wallart" <?php echo ($is_edit_mode && $product_data['material'] == 'wallart') ? 'selected' : ''; ?>>Wall Art</option>
                <option value="mixed" <?php echo ($is_edit_mode && $product_data['material'] == 'mixed') ? 'selected' : ''; ?>>Mixed</option>
            </select>

            <label>Category:</label>
            <select name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['category_id']; ?>" <?php echo ($is_edit_mode && $product_data['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                        <?php echo $cat['category_name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($is_edit_mode && $product_data['image_url']): ?>
                <label>Current Image:</label>
                <img src="<?php echo htmlspecialchars($product_data['image_url']); ?>" alt="Current Product Image" style="max-width: 200px; display: block; margin-bottom: 15px;">
                <label>Upload New Image (optional):</label>
                <input type="file" name="image" accept="image/*">
            <?php else: ?>
                <label>Product Image:</label>
                <input type="file" name="image" accept="image/*" required>
            <?php endif; ?>

            <button type="submit"><?php echo htmlspecialchars($button_text); ?></button>
        </form>
    </div>
</div>

<footer>
    <p>&copy; 2024 Earthelic.com</p>
</footer>
</body>
</html>
