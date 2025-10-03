<?php

include "db_connect.php"; 

// --- Function to safely format status for CSS class (removes spaces, converts to lowercase) ---
function formatStatusForCss($status) {
    if (empty($status)) return 'unknown';
    // Replaces spaces with hyphens and converts to lowercase: "Pricing Sent" -> "pricing-sent"
    return strtolower(str_replace([' ', '_'], '-', trim($status)));
}

// --- Handle actions to update request status ---
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);
    $action   = $_GET['action'];

    $new_status = '';
    
    // NOTE: We MUST use the exact ENUM values here for the update query
    if ($action == "quote") {
        $new_status = 'Pricing Sent'; 
    } 
    elseif ($action == "complete") {
        $new_status = 'Completed';
    } 
    elseif ($action == "review") {
        $new_status = 'In Review';
    }
    elseif ($action == "cancel") {
        $new_status = 'Cancelled';
    }
    
    $success = false;
    $error_detail = ""; // Variable to capture specific MySQL error
    
    if (!empty($new_status)) {
        // Check if the statement preparation was successful
        if ($stmt = $conn->prepare("UPDATE Custom_Requests SET status=?, update_date=NOW() WHERE request_id=?")) {
            // The ENUM status string is bound as a string (s)
            $stmt->bind_param("si", $new_status, $request_id);
            
            // Check if execution was successful
            if ($stmt->execute()) {
                $success = true;
            } else {
                $error_detail = $stmt->error; // Capture the error from the statement execution
            }
            $stmt->close();
        } else {
            $error_detail = $conn->error; // Capture the error from the prepare step
        }
    }

    // --- Added Session Feedback Logic ---
    if ($success) {
        // Set success message for display after redirect
        $_SESSION['success_message'] = "Request ID {$request_id} status successfully updated to " . htmlspecialchars($new_status) . ".";
    } else {
        // Use the captured error_detail for a specific error message
        $errorMessage = "Error updating status: ";
        if ($error_detail) {
             $errorMessage .= "SQL Error: " . $error_detail;
        } elseif (empty($new_status)) {
             $errorMessage .= "Invalid action provided.";
        } else {
             $errorMessage .= "A connection or unknown error occurred.";
        }
        $_SESSION['error_message'] = $errorMessage;
    }

    // Redirect back to the same page to prevent form resubmission and display the message
    header("Location: custom_requests_list.php");
    exit;
}

// --- Fetch all custom requests ---
// LEFT JOIN with Users table to get name/email if the user was logged in.
// If not logged in, we use the contact_email provided in the form.
$sql = "SELECT cr.*, u.full_name
        FROM Custom_Requests cr
        LEFT JOIN Users u ON cr.user_id = u.user_id
        ORDER BY cr.request_date DESC";
$requests = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Custom Requests - Earthelic Admin</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Inline CSS for status colors and table layout, including button fix -->
    <style>
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
            font-size: 0.85rem;
            text-transform: capitalize;
        }
        /* Define status colors based on your ENUM values, using hyphenated, lowercase names */
        .status.new { background-color: #ffc107; color: #333; }
        .status.in-review { background-color: #ff7f50; color: white; } /* Coral color for In Review */
        .status.pricing-sent { background-color: #007bff; color: white; } /* Blue for Pricing Sent (was quoted/shipped) */
        .status.completed { background-color: #28a745; color: white; } /* Green for Completed */
        .status.cancelled { background-color: #6c757d; color: white; } /* Gray for Cancelled */
        .status.unknown { background-color: #dc3545; color: white; } /* Fallback for missing/invalid status */


        /* --- BUTTON STYLES --- */
        .btn {
            display: inline-block;
            padding: 6px 10px;
            margin: 2px 0;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.8em;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            opacity: 0.9;
        }

        /* Adjusted button style for Pricing Sent */
        .btn.shipped { background-color: #007bff; }
        /* Complete button style */
        .btn.completed { background-color: #28a745; }
        /* New In Review button style */
        .btn.in-review { background-color: #ff7f50; } 
        /* Cancel button style */
        .btn.cancel { background-color: #6c757d; }


        .table-container {
            overflow-x: auto;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-container th, .table-container td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }

        .table-container th {
            background-color: #f8f9fa;
        }

        /* Styling for the long description column */
        .table-container td:nth-child(7) { 
            max-width: 300px;
            white-space: normal;
            word-wrap: break-word;
            font-size: 0.9em;
        }
        /* Styling for the image preview */
        .table-container img {
            max-width: 80px;
            max-height: 80px;
            border-radius: 4px;
            cursor: pointer;
            object-fit: cover;
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
            <li><a href="custom_requests_list.php" class="active"><i class="fa fa-paint-brush"></i> Custom Requests</a></li>
            <li><a href="users_list.php"><i class="fa fa-users"></i> Manage Users</a></li>
            <li><a href="complaintadmin.php"><i class="fa fa-headset"></i> Manage Complaints</a></li>
            <li><a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>Manage Custom Product Requests</h1>
        
        <!-- Status/Error Message Display Block -->
        <?php 
        // Check and display success message
        if (isset($_SESSION['success_message'])): ?>
            <div style="padding: 12px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px; font-weight: 500;">
                <i class="fa fa-check-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            </div>
            <?php unset($_SESSION['success_message']); 
        endif; 

        // Check and display error message
        if (isset($_SESSION['error_message'])): ?>
            <div style="padding: 12px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px; font-weight: 500;">
                <i class="fa fa-times-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); 
        endif; 
        ?>
        <!-- End Message Display Block -->

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Product Name</th>
                        <th>Description</th>
                        <th>Image</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests->num_rows > 0): ?>
                        <?php while($request = $requests->fetch_assoc()): 
                            $customer_name = $request['full_name'] ?? 'Guest User';
                            $customer_email = $request['contact_email'];
                            
                            // 1. Robustly get the status, defaulting to 'Unknown' if NULL/empty
                            $status_from_db = $request['status'] ?? 'Unknown';

                            // 2. Standardize the status for comparison logic (e.g., "In Review" -> "in review")
                            $current_status = strtolower(trim($status_from_db));
                            
                            // 3. Generate the CSS class (e.g., "In Review" -> "in-review")
                            $css_class = formatStatusForCss($status_from_db);
                        ?>
                            <tr>
                                <td><?php echo $request['request_id']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($request['request_date'])); ?></td>
                                <td><?php echo htmlspecialchars($customer_name); ?></td>
                                <td><?php echo htmlspecialchars($customer_email); ?></td>
                                <td><?php echo ucfirst($request['product_type']); ?></td>
                                <td><?php echo htmlspecialchars($request['product_name']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars(substr($request['description'], 0, 150))) . (strlen($request['description']) > 150 ? '...' : ''); ?></td>
                                <td>
                                    <?php if (!empty($request['reference_image_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($request['reference_image_path']); ?>" target="_blank">
                                            <img src="<?php echo htmlspecialchars($request['reference_image_path']); ?>" alt="Ref Image">
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <!-- UPDATED: Use the raw DB status for display, and the generated class for styling -->
                                <td><span class="status <?php echo htmlspecialchars($css_class); ?>"><?php echo htmlspecialchars($status_from_db); ?></span></td>
                                <td>
                                    <?php if ($current_status == 'new'): ?>
                                        <a href="?action=review&request_id=<?php echo $request['request_id']; ?>" class="btn in-review" title="Mark as In Review">In Review</a>
                                        <a href="?action=quote&request_id=<?php echo $request['request_id']; ?>" class="btn shipped" title="Mark as Pricing Sent">Pricing Sent</a>
                                        <a href="?action=cancel&request_id=<?php echo $request['request_id']; ?>" class="btn cancel" title="Cancel Request">Cancel</a>
                                    <?php elseif ($current_status == 'in review'): // Note: Lowercase and space matched here ?>
                                        <a href="?action=quote&request_id=<?php echo $request['request_id']; ?>" class="btn shipped" title="Mark as Pricing Sent">Pricing Sent</a>
                                        <a href="?action=cancel&request_id=<?php echo $request['request_id']; ?>" class="btn cancel" title="Cancel Request">Cancel</a>
                                    <?php elseif ($current_status == 'pricing sent'): // Note: Lowercase and space matched here ?>
                                        <a href="?action=complete&request_id=<?php echo $request['request_id']; ?>" class="btn completed" title="Mark as Completed">Complete</a>
                                        <a href="?action=cancel&request_id=<?php echo $request['request_id']; ?>" class="btn cancel" title="Cancel Request">Cancel</a>
                                    <?php elseif ($current_status == 'cancelled'): ?>
                                        <span style="font-size: 0.9em; color: #6c757d; font-weight: 500;">Cancelled</span>
                                    <?php elseif ($current_status == 'completed'): ?>
                                        <span style="font-size: 0.9em; color: #28a745; font-weight: 500;">Closed</span>
                                    <?php else: ?>
                                        <!-- Fallback for unknown status -->
                                        <span style="font-size: 0.9em; color: #dc3545; font-weight: 500;">Unknown Status</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="10">No custom requests found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
