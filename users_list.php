<?php
include "db_connect.php"; // DB connection + session

// ================= Delete =================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM Users WHERE user_id=$delete_id");
    header("Location: users_list.php");
    exit;
}

// ================= Update =================
if (isset($_POST['update_user'])) {
    $user_id   = intval($_POST['user_id']);
    $full_name = $_POST['full_name'];
    $email     = $_POST['email'];
    $phone     = $_POST['phone'];
    $role      = $_POST['role'];

    $stmt = $conn->prepare("UPDATE Users SET full_name=?, email=?, phone=?, role=? WHERE user_id=?");
    $stmt->bind_param("ssssi", $full_name, $email, $phone, $role, $user_id);

    if ($stmt->execute()) {
        $success = "✅ User updated successfully!";
    } else {
        $error = "❌ Error updating user: " . $stmt->error;
    }
}

// ================= Fetch all users =================
$result = $conn->query("SELECT * FROM Users ORDER BY created_at DESC");

// If edit mode
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_user = $conn->query("SELECT * FROM Users WHERE user_id=$edit_id")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users - Earthelic Admin</title>
  <link rel="stylesheet" href="css/admin.css">
<link rel="stylesheet" href="css/userlist.css">
    <link rel="stylesheet" href="css/upload.css"> <!-- for table styling -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
  <!-- Logo -->
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
          <li><a href="users_list.php" class="active"><i class="fa fa-users"></i> Manage Users</a></li>
          <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
      </ul>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <h1 class="section-title">Manage Users</h1>

    <!-- Success / Error -->
    <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

    <!-- Edit Form -->
    <?php if ($edit_user): ?>
      <div class="upload-container">
        <h2>Edit User: <?php echo htmlspecialchars($edit_user['full_name']); ?></h2>
        <form method="POST" action="users_list.php">
          <input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>">

          <label>Full Name:</label>
          <input type="text" name="full_name" value="<?php echo htmlspecialchars($edit_user['full_name']); ?>" required>

          <label>Email:</label>
          <input type="email" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>

          <label>Phone:</label>
          <input type="text" name="phone" value="<?php echo htmlspecialchars($edit_user['phone']); ?>">

          <label>Role:</label>
          <select name="role" required>
              <option value="customer" <?php if($edit_user['role']=="customer") echo "selected"; ?>>Customer</option>
              <option value="admin" <?php if($edit_user['role']=="admin") echo "selected"; ?>>Admin</option>
          </select>

          <button type="submit" name="update_user">Update User</button>
        </form>
      </div>
    <?php endif; ?>

    <!-- User Table -->
    <table>
      <thead>
        <tr>
          <th>User ID</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Role</th>
          <th>Created At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result->num_rows > 0): ?>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?php echo $row['user_id']; ?></td>
              <td><?php echo htmlspecialchars($row['full_name']); ?></td>
              <td><?php echo htmlspecialchars($row['email']); ?></td>
              <td><?php echo htmlspecialchars($row['phone']); ?></td>
              <td><?php echo ucfirst($row['role']); ?></td>
              <td><?php echo $row['created_at']; ?></td>
              <td>
                <a href="users_list.php?edit=<?php echo $row['user_id']; ?>" class="action-btn edit-btn">Edit</a>
                <a href="users_list.php?delete=<?php echo $row['user_id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7">No users found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
