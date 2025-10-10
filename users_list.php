<?php
include "db_connect.php"; // DB connection + session

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
// ================= Delete =================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    // Before deleting, check if the user is attempting to delete themselves
    if ($delete_id == $_SESSION['user_id']) {
        $error = "❌ You cannot delete your own admin account!";
    } else {
        $conn->query("DELETE FROM Users WHERE user_id=$delete_id");
        $_SESSION['success'] = "✅ User ID {$delete_id} deleted successfully!";
        header("Location: users_list.php");
        exit;
    }
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

// ================= Fetch edit user data =================
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_result = $conn->query("SELECT * FROM Users WHERE user_id=$edit_id");
    if ($edit_result->num_rows > 0) {
        $edit_user = $edit_result->fetch_assoc();
    }
}

// ================= Fetch all users =================
$result = $conn->query("SELECT * FROM Users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - Earthelic Admin</title>
    <link rel="stylesheet" href="css/admin.css">
    <!-- ADDED: Link to managepro.css for consistent table styling -->
    <link rel="stylesheet" href="css/managepro.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Message Styling */
        .success, .error { 
            padding: 10px; 
            margin-bottom: 20px; 
            border-radius: 5px; 
            font-weight: 500;
        }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Edit Form Styling */
        .edit-form-container { 
            margin: 20px 0; 
            padding: 20px; 
            border: 1px solid #ccc; 
            background-color: #f9f9f9; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .edit-form-container h2 {
            margin-top: 0;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            color: #007bff;
        }
        .edit-form-container label { 
            display: block; 
            margin-top: 10px; 
            font-weight: bold; 
            color: #555;
        }
        .edit-form-container input[type="text"], 
        .edit-form-container input[type="email"], 
        .edit-form-container input[type="tel"], 
        .edit-form-container select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .edit-form-container button {
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 20px;
            transition: background-color 0.3s, transform 0.1s;
            font-weight: bold;
        }
        .edit-form-container button:hover { background-color: #0056b3; }

        /* Action Buttons - Consistent with other manage lists */
        .action-btn { 
            padding: 6px 10px; 
            margin: 2px;
            display: inline-block; 
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            transition: background-color 0.3s;
            white-space: nowrap; /* Prevents button text wrap */
        }
        .edit-btn { background-color: #007bff; color: white; }
        .delete-btn { background-color: #dc3545; color: white; }
        .edit-btn:hover { background-color: #0056b3; }
        .delete-btn:hover { background-color: #c82333; }

        /* REMOVED: Generic table styling, as managepro.css will handle it */
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="admin.php"><i class="fa fa-home"></i> Dashboard</a></li>
            <li><a href="upload.php"><i class="fa fa-upload"></i> Upload Product</a></li>
            <li><a href="products_list.php"><i class="fa fa-box"></i> Manage Products</a></li>
            <li><a href="orders_list.php"><i class="fa fa-shopping-cart"></i> Manage Orders</a></li>
            <li><a href="custom_requests_list.php"><i class="fa fa-paint-brush"></i> Custom Requests</a></li>
            <li><a href="users_list.php" class="active"><i class="fa fa-users"></i> Manage Users</a></li>
            <li><a href="complaintadmin.php"><i class="fa fa-headset"></i> Manage Complaints</a></li>
            <li><a href="refunds_list.php"><i class="fa fa-receipt"></i> Manage Refunds</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>Manage Users</h1>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Edit Form -->
        <?php if ($edit_user): ?>
          <div class="edit-form-container">
            <h2>Edit User: <?php echo htmlspecialchars($edit_user['full_name']); ?></h2>
            <form action="users_list.php" method="POST">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>">
                
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($edit_user['full_name']); ?>" required>

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>

                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($edit_user['phone']); ?>">

                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="user" <?php echo ($edit_user['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?php echo ($edit_user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                </select>

                <button type="submit" name="update_user">Update User</button>
            </form>
          </div>
        <?php endif; ?>

        <!-- User Table -->
        <div class="table-container">
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
                        <!-- Changed alert() to a custom message in the delete confirmation for canvas compatibility -->
                        <a href="users_list.php?delete=<?php echo $row['user_id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete user ID <?php echo $row['user_id']; ?>?');">Delete</a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="7">No users found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
        </div>
    </div>
</body>
</html>
