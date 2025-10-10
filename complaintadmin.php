
<?php
session_start();
// Include the database connection file
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle complaint status updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $complaint_id = $conn->real_escape_string($_POST['complaint_id']);
    $new_status = $conn->real_escape_string($_POST['status']);

    $sql = "UPDATE Complaints SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $complaint_id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to prevent form resubmission
    header("Location: complaintadmin.php");
    exit;
}

// Fetch all complaints from the database
$sql = "SELECT id, name, email, subject, message, status, created_at FROM Complaints ORDER BY created_at DESC";
$result = $conn->query($sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Complaints - Admin Panel</title>
    <link rel="stylesheet" href="css/admin.css">
    <!-- ADDED: Link to managepro.css for consistent table styling -->
    <link rel="stylesheet" href="css/managepro.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Status Tag Styling (consistent with other lists) */
        .status-tag {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
            font-size: 0.85rem;
            text-transform: capitalize;
        }
        
        /* Status Colors */
        .status-Pending { background-color: #ffc107; color: #333; } /* Yellow */
        .status-In-Progress { background-color: #007bff; color: white; } /* Blue */
        .status-Resolved { background-color: #28a745; color: white; } /* Green */

        /* Form for Status Update */
        .status-form {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .status-form select {
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .status-form button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9em;
            transition: background-color 0.3s;
        }

        .status-form button:hover {
            background-color: #0056b3;
        }
        
        /* Message column width management */
        .table-container td:nth-child(5) { 
            max-width: 300px;
            white-space: normal;
            word-wrap: break-word;
            font-size: 0.9em;
        }
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
            <li><a href="users_list.php"><i class="fa fa-users"></i> Manage Users</a></li>
            <li><a href="complaintadmin.php" class="active"><i class="fa fa-headset"></i> Manage Complaints</a></li>
            <li><a href="refunds_list.php"><i class="fa fa-receipt"></i> Manage Refunds</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>Manage Customer Complaints</h1>
        
        <div class="table-container">
            <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                <td><?php echo htmlspecialchars($row['message']); ?></td>
                                <td>
                                    <!-- Using status-tag class and status for coloring -->
                                    <span class="status-tag status-<?php echo str_replace(' ', '-', $row['status']); ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form class="status-form" method="post" action="">
                                        <input type="hidden" name="complaint_id" value="<?php echo $row['id']; ?>">
                                        <select name="status">
                                            <option value="Pending" <?php echo ($row['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="In Progress" <?php echo ($row['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="Resolved" <?php echo ($row['status'] == 'Resolved') ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                        <button type="submit" name="update_status">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No complaints found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
