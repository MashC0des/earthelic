<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "earthelic");
if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id    = $_POST['category_id'];
    $product_name   = $_POST['product_name'];
    $description    = $_POST['description'];
    $price          = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'];
    $material       = $_POST['material'];

    // Image upload settings
    $targetDir = "uploads/"; 
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = basename($_FILES["image"]["name"]);
    $targetFilePath = $targetDir . time() . "_" . $fileName;
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

    $allowedTypes = ["jpg","jpeg","png","gif"];
    if (in_array($fileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            // Save product with image URL
            $stmt = $conn->prepare("INSERT INTO Products 
                (category_id, product_name, description, price, stock_quantity, material, image_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdiss", 
                $category_id, $product_name, $description, $price, $stock_quantity, $material, $targetFilePath);

            if ($stmt->execute()) {
                echo "<p style='color:green;'>✅ Product uploaded successfully!</p>";
            } else {
                echo "<p style='color:red;'>❌ Database error: " . $stmt->error . "</p>";
            }
        } else {
            echo "<p style='color:red;'>❌ Failed to upload image.</p>";
        }
    } else {
        echo "<p style='color:red;'>❌ Only JPG, JPEG, PNG & GIF files allowed.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Product - Earthelic</title>
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
            <li><a href="admin.php"><i class="fa fa-home"></i> Dashboard</a></li>
            <li><a href="upload.php"><i class="fa fa-upload"></i> Upload Product</a></li>
            <li><a href="products_list.php"><i class="fa fa-box"></i> Manage Products</a></li>
            <li><a href="orders_list.php"><i class="fa fa-shopping-cart"></i> Manage Orders</a></li>
            <li><a href="users_list.php"><i class="fa fa-users"></i> Manage Users</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

<div class="main-content">
    <div class="upload-container" style="background: #ffffff38;">
        <h2>Upload New Product</h2>

        <!-- success/error messages -->
        <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

        <form action="upload.php" method="POST" enctype="multipart/form-data">
            <label>Product Name:</label>
            <input type="text" name="product_name" required>

            <label>Description:</label>
            <textarea name="description" required></textarea>

            <label>Price:</label>
            <input type="number" step="0.01" name="price" required>

            <label>Stock Quantity:</label>
            <input type="number" name="stock_quantity" required>

            <label>Material:</label>
            <select name="material" required>
                <option value="ceramic">Ceramic</option>
                <option value="metal">Metal</option>
                <option value="canvas">Canvas</option>
                <option value="wallart">Wall Art</option>
                <option value="mixed">Mixed</option>
            </select>

            <label>Category:</label>
            <select name="category_id" required>
                <?php
                $result = $conn->query("SELECT * FROM Categories");
                while($row = $result->fetch_assoc()) {
                    echo "<option value='{$row['category_id']}'>{$row['category_name']}</option>";
                }
                ?>
            </select>

            <label>Upload Image:</label>
            <input type="file" name="image" accept="image/*" required>

            <button type="submit">Upload Product</button>
        </form>
    </div>
</div>

<footer>
    <p>&copy; 2024 Earthelic.com</p>
</footer>
</body>
</html>

