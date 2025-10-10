<?php
// Note: Assumes db_connect.php handles session start and DB connection.
include "db_connect.php"; 

// IMPORTANT: Add authorization check here to ensure only 'admin' role can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// ================= Update Refund Status =================
if (isset($_POST['update_refund'])) {
    $refund_id = intval($_POST['refund_id']);
    $new_status = $_POST['status'];
    $notes = $_POST['notes'];
    
    // Basic validation
    if (!in_array($new_status, ['pending', 'completed', 'rejected'])) {
        $error = "❌ Invalid status provided.";
    } else {
        $stmt = $conn->prepare("UPDATE Refunds SET status=?, admin_notes=?, updated_at=NOW() WHERE refund_id=?");
        // s=status, s=notes, i=refund_id
        $stmt->bind_param("ssi", $new_status, $notes, $refund_id);

        if ($stmt->execute()) {
            $success = "✅ Refund #$refund_id status updated successfully to " . ucfirst($new_status) . "!";
        } else {
            $error = "❌ Error updating refund: " . $stmt->error;
        }
    }
}

// ================= Fetch all refunds with user and order data =================
$sql = "SELECT 
            R.*, 
            O.total_amount, 
            U.full_name, 
            U.email
        FROM 
            Refunds R
        JOIN 
            Orders O ON R.order_id = O.order_id
        JOIN 
            Users U ON O.user_id = U.user_id
        ORDER BY 
            R.refund_date DESC";
            
$result = $conn->query($sql);

// If edit mode
$edit_refund = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    
    // Fetch the specific refund details for the edit form
    $stmt_edit = $conn->prepare("SELECT * FROM Refunds WHERE refund_id=?");
    $stmt_edit->bind_param("i", $edit_id);
    $stmt_edit->execute();
    $edit_refund = $stmt_edit->get_result()->fetch_assoc();
    $stmt_edit->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Refunds - Earthelic Admin</title>
  <link rel="stylesheet" href="css/admin.css">
  <link rel="stylesheet" href="css/upload.css"> 
  <link rel="stylesheet" href="css/userlist.css"> 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    /* Status styling for clarity */
    .status-pending { background-color: #ffc107; color: #333; }
    .status-completed { background-color: #28a745; color: white; }
    .status-rejected { background-color: #dc3545; color: white; }
    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
        display: inline-block;
        font-size: 12px;
    }
    .refund-details {
        font-size: 0.9em;
        margin-top: 5px;
        padding: 5px;
        background-color: #f8f9fa;
        border-radius: 4px;
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <h2>Admin Panel</h2>
    <ul>
        <li><a href="admin.php"><i class="fa fa-home"></i> Dashboard</a></li>
        <li><a href="upload.php"><i class="fa fa-upload"></i> Upload Product</a></li>
        <li><a href="products_list.php"><i class="fa fa-box"></i> Manage Products</a></li>
        <li><a href="orders_list.php"><i class="fa fa-shopping-cart"></i> Manage Orders</a></li>
        <li><a href="custom_requests_list.php"><i class="fa fa-paint-brush"></i> Custom Requests</a></li>
        <li><a href="users_list.php"><i class="fa fa-users"></i> Manage Users</a></li>
        <li><a href="complaintadmin.php"><i class="fa fa-headset"></i> Manage Complaints</a></li>
         <li><a href="refunds_list.php" class="active"><i class="fa fa-receipt"></i> Manage Refunds</a></li> <!-- Added new item -->
        
        <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <h1 class="section-title">Manage Refund Requests</h1>

    <!-- Success / Error Messages -->
    <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

    <!-- Edit/Update Status Form -->
    <?php if ($edit_refund): ?>
      <div class="upload-container" style="border: 2px solid #ffc107; padding: 20px;">
        <h2>Update Refund #<?php echo htmlspecialchars($edit_refund['refund_id']); ?></h2>
        <p><strong>Order ID:</strong> #<?php echo htmlspecialchars($edit_refund['order_id']); ?></p>
        <p><strong>Customer Reason:</strong> <?php echo htmlspecialchars($edit_refund['reason']); ?></p>
        <p><strong>Refund Amount:</strong> ₹<?php echo htmlspecialchars(number_format($edit_refund['refund_amount'], 2)); ?></p>

        <h3 style="margin-top: 15px;">Refund Details Provided by Customer:</h3>
        <p><strong>A/C Number / Wallet ID:</strong> <?php echo htmlspecialchars($edit_refund['account_number'] ?: 'N/A'); ?></p>
        <p><strong>UPI ID:</strong> <?php echo htmlspecialchars($edit_refund['upi_id'] ?: 'N/A'); ?></p>
        <hr style="margin: 15px 0;">
        
        <form method="POST" action="refunds_list.php">
          <input type="hidden" name="refund_id" value="<?php echo $edit_refund['refund_id']; ?>">

          <label for="status">Update Status:</label>
          <select name="status" required>
              <option value="pending" <?php if($edit_refund['status']=="pending") echo "selected"; ?>>Pending</option>
              <option value="completed" <?php if($edit_refund['status']=="completed") echo "selected"; ?>>Completed (Refund Sent)</option>
              <option value="rejected" <?php if($edit_refund['status']=="rejected") echo "selected"; ?>>Rejected</option>
          </select>

          <label for="notes">Admin Notes (Internal)</label>
          <textarea name="notes" rows="3"><?php echo htmlspecialchars($edit_refund['admin_notes']); ?></textarea>
          
          <button type="submit" name="update_refund" class="action-btn edit-btn" style="background-color: #007bff;">Save Refund Update</button>
          <a href="refunds_list.php" class="action-btn delete-btn" style="background-color: #6c757d; float: right;">Cancel Edit</a>
        </form>
      </div>
    <?php endif; ?>

    <!-- Refunds Table -->
    <table>
      <thead>
        <tr>
          <th>Refund ID</th>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Amount</th>
          <th>Date Requested</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result->num_rows > 0): ?>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?php echo $row['refund_id']; ?></td>
              <td>#<?php echo $row['order_id']; ?></td>
              <td>
                <?php echo htmlspecialchars($row['full_name']); ?><br>
                <small><?php echo htmlspecialchars($row['email']); ?></small>
              </td>
              <td>₹<?php echo htmlspecialchars(number_format($row['refund_amount'], 2)); ?></td>
              <td><?php echo date("d M Y H:i", strtotime($row['refund_date'])); ?></td>
              <td>
                <span class="status-badge status-<?php echo htmlspecialchars($row['status']); ?>">
                    <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                </span>
              </td>
              <td>
                <a href="refunds_list.php?edit=<?php echo $row['refund_id']; ?>" class="action-btn edit-btn">Review/Update</a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7">No refund requests found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
