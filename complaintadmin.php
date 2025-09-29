<?php
session_start();
// Include the database connection file
include "db_connect.php";


// Handle complaint status updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $complaint_id = $conn->real_escape_string($_POST['complaint_id']);
    $new_status = $conn->real_escape_string($_POST['status']);

    $sql = "UPDATE Complaints SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $complaint_id);
    $stmt->execute();
    $stmt->close();
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .complaints-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .complaints-table th, .complaints-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .complaints-table th {
            background-color: #333;
            color: white;
            font-size: 1.1rem;
        }

        .complaints-table tr:hover {
            background-color: #444;
        }

        .status-form {
            display: flex;
            align-items: center;
        }

        .status-form select {
            padding: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
            margin-right: 10px;
        }

        .status-form button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
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
    <div class="main-content">
        <h1>Manage Complaints</h1>
        <div class="complaint-list">
            <?php if ($result->num_rows > 0): ?>
                <table class="complaints-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Complaint</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                <td><?php echo htmlspecialchars($row['message']); ?></td>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
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
