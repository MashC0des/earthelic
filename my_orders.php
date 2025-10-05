<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . "/db_connect.php"; // Assume this file connects to $conn

/* -------------------------------------------
   Auth check
------------------------------------------- */
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

/* -------------------------------------------
   Process Cancellation Request (POST from profile.php or my_orders.php)
------------------------------------------- */
$cancellation_message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $order_to_cancel_id = (int)$_POST['cancel_order_id'];
    // CHANGE 1: Sanitize and validate cancellation reason
    $cancellation_reason = trim($_POST['cancellation_reason'] ?? 'User initiated cancellation from my orders page.');
    $cancellation_reason = substr($cancellation_reason, 0, 255); // Truncate to a reasonable length

    // 1. Check if the order belongs to the user and is cancellable (e.g., status is 'pending' or 'processing')
    $stmt = $conn->prepare("SELECT o.total_amount, o.status, p.payment_method 
                            FROM Orders o 
                            JOIN Payments p ON o.order_id = p.order_id 
                            WHERE o.order_id = ? AND o.user_id = ?");
    $stmt->bind_param("ii", $order_to_cancel_id, $user_id);
    // CHANGE 1: Error handling for execution
    if (!$stmt->execute()) {
        $errors[] = "Database error during order check: " . $stmt->error;
        $stmt->close();
    } else {
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $order_data = $result->fetch_assoc();
            $current_status = $order_data['status'];
            $total_amount = $order_data['total_amount'];
            $payment_method = $order_data['payment_method'];

            // CRITICAL CHECK: Allow cancellation if status is 'pending' OR 'processing'
            if ($current_status === 'pending' || $current_status === 'processing') {
                // Start a transaction for safe status update and refund record creation
                $conn->begin_transaction();
                try {
                    // 2. Update Order Status
                    // CHANGE 1: Ensure update query is correct and successful
                    $stmt_update = $conn->prepare("UPDATE Orders SET status = 'cancelled' WHERE order_id = ? AND user_id = ?");
                    $stmt_update->bind_param("ii", $order_to_cancel_id, $user_id);
                    if (!$stmt_update->execute() || $stmt_update->affected_rows === 0) {
                        throw new Exception("Order status update failed or order not found/not owned.");
                    }
                    $stmt_update->close();

                    // 3. Insert Refund Record (Simulated Refund Initiation)
                    $refund_status = 'initiated'; // Can be 'initiated', 'processed', 'failed'
                    $stmt_refund = $conn->prepare("INSERT INTO Refunds (order_id, amount, reason, initiated_at, status) VALUES (?, ?, ?, NOW(), ?)");
                    // CHANGE 1: Use 'd' for double/float for total_amount if it's a decimal type
                    $stmt_refund->bind_param("idss", $order_to_cancel_id, $total_amount, $cancellation_reason, $refund_status);
                    if (!$stmt_refund->execute()) {
                         throw new Exception("Refund record insertion failed.");
                    }
                    $stmt_refund->close();

                    // Commit the transaction
                    $conn->commit();
                    // FIX: Cast $total_amount to float to satisfy strict type requirements of number_format()
                    $cancellation_message = "Success: Order #{$order_to_cancel_id} has been cancelled. A refund of ₹" . number_format((float)$total_amount, 2) . " via {$payment_method} has been initiated. Please provide your bank or UPI details below to complete the refund process.";
                    
                    // Clear order cache to reflect status change on reload (if implemented)
                    if (isset($_SESSION['user_orders'])) {
                        unset($_SESSION['user_orders']);
                    }

                    // CHANGE 1: Redirect to self with the order ID and a success param to show only the cancelled order
                    header("Location: my_orders.php?order_id={$order_to_cancel_id}&status=success");
                    exit();

                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Cancellation failed for Order ID {$order_to_cancel_id}: " . $e->getMessage());
                    $errors[] = "A critical database error occurred during cancellation. Please try again or contact support. Error: " . $e->getMessage();
                }
            } else {
                $errors[] = "Order #{$order_to_cancel_id} cannot be cancelled because its current status is '{$current_status}'.";
            }
        } else {
            $errors[] = "Invalid order ID or the order does not belong to your account.";
        }
        $stmt->close();
    }
}
// ----------------------------------------------------------------


/* -------------------------------------------
   Fetch Orders for Display (Modified for single order view)
------------------------------------------- */
$orders = [];
$single_order_id = null;

// CHANGE 2: Check for a specific order ID in the URL
if (isset($_GET['order_id']) && filter_var($_GET['order_id'], FILTER_VALIDATE_INT) !== false) {
    $single_order_id = (int)$_GET['order_id'];
    $order_sql = "SELECT order_id, total_amount, status, order_date FROM Orders WHERE user_id = ? AND order_id = ? ORDER BY order_date DESC";
    $stmt = $conn->prepare($order_sql);
    $stmt->bind_param("ii", $user_id, $single_order_id);
} else {
    // Original logic: fetch all orders
    $order_sql = "SELECT order_id, total_amount, status, order_date FROM Orders WHERE user_id = ? ORDER BY order_date DESC";
    $stmt = $conn->prepare($order_sql);
    $stmt->bind_param("i", $user_id);
}

if (!$stmt->execute()) {
    $errors[] = "Error fetching orders: " . $stmt->error;
} else {
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Fetch payment method
        $stmt_payment = $conn->prepare("SELECT payment_method FROM Payments WHERE order_id = ?");
        $stmt_payment->bind_param("i", $row['order_id']);
        $stmt_payment->execute();
        $payment_row = $stmt_payment->get_result()->fetch_assoc();
        $row['payment_method'] = $payment_row['payment_method'] ?? 'N/A';
        $stmt_payment->close();
        
        // CHANGE 2: Fetch refund status/details for the cancelled order if it exists
        if ($row['status'] === 'cancelled') {
             $stmt_refund = $conn->prepare("SELECT status, refund_details FROM Refunds WHERE order_id = ? ORDER BY initiated_at DESC LIMIT 1");
             $stmt_refund->bind_param("i", $row['order_id']);
             $stmt_refund->execute();
             $refund_row = $stmt_refund->get_result()->fetch_assoc();
             $row['refund_status'] = $refund_row['status'] ?? 'N/A';
             $row['refund_details_provided'] = !empty($refund_row['refund_details']);
             $stmt_refund->close();
        }

        $orders[] = $row;
    }
    $stmt->close();
}

// Handle cancellation success message on GET request after redirect
if (isset($_GET['status']) && $_GET['status'] === 'success' && $single_order_id) {
     $cancellation_message = "Success: Order #{$single_order_id} has been cancelled. Please check the details below and provide your refund information.";
}


/* -------------------------------------------
   AJAX Endpoint for Refund Details (Must be handled *before* output)
------------------------------------------- */
// CHANGE 3: Handle AJAX request for refund details submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_refund_details') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    $refund_order_id = (int)($_POST['order_id'] ?? 0);
    $refund_details = trim($_POST['refund_details'] ?? '');

    if (empty($refund_order_id) || empty($refund_details)) {
        $response['message'] = 'Order ID and refund details are required.';
        echo json_encode($response);
        exit();
    }

    // Check if the order exists, belongs to the user, and is cancelled/awaiting details
    $stmt = $conn->prepare("SELECT order_id FROM Orders WHERE order_id = ? AND user_id = ? AND status = 'cancelled'");
    $stmt->bind_param("ii", $refund_order_id, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows !== 1) {
        $response['message'] = 'Invalid or non-cancelled order.';
        $stmt->close();
        echo json_encode($response);
        exit();
    }
    $stmt->close();
    
    // Update the latest Refund record with the details
    // NOTE: This assumes 'Refunds' table has a 'refund_details' column.
    // If not, you'll need to create one, or use a separate table for details.
    // For simplicity, we'll assume a `refund_details` column in the `Refunds` table.
    $stmt_update = $conn->prepare("UPDATE Refunds SET refund_details = ?, status = 'details_provided' WHERE order_id = ? ORDER BY initiated_at DESC LIMIT 1");
    $stmt_update->bind_param("si", $refund_details, $refund_order_id);
    
    if ($stmt_update->execute()) {
        $response['success'] = true;
        $response['message'] = 'Refund details submitted successfully. Your refund will be processed shortly.';
    } else {
        $response['message'] = 'Database error: Could not update refund details.';
        error_log("Refund details update failed for Order ID {$refund_order_id}: " . $stmt_update->error);
    }
    $stmt_update->close();

    echo json_encode($response);
    exit();
}

// ----------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $single_order_id ? "Order #{$single_order_id} Details" : "My Orders"; ?> - Earthelic</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .container {
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        h2 {
            text-align: center;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .order-card {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            transition: box-shadow 0.3s;
        }
        .order-card:hover {
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .order-status {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .order-status.pending { background-color: #fff3cd; color: #856404; }
        .order-status.processing { background-color: #cce5ff; color: #004085; }
        .order-status.shipped { background-color: #d4edda; color: #155724; }
        .order-status.delivered { background-color: #d1ecf1; color: #0c5460; }
        .order-status.cancelled { background-color: #f8d7da; color: #721c24; }

        .order-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .order-detail-grid p {
            margin: 0;
            font-size: 0.9em;
        }
        .order-detail-grid strong {
            font-weight: 600;
        }

        .cancel-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }
        .cancel-btn:hover {
            background-color: #c82333;
        }
        
        .alert-success {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-error {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        /* CHANGE 3: Styles for Refund Form */
        #refund-form-section {
            padding: 20px;
            border: 1px dashed #007bff;
            border-radius: 6px;
            margin-top: 20px;
            background-color: #f7f9fc;
        }
        #refund-form-section h4 {
            color: #007bff;
            margin-top: 0;
        }
        #refund_details {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            resize: vertical;
        }
        #submit-refund-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            float: right;
        }
        #submit-refund-btn:hover {
            background-color: #0056b3;
        }
        #refund-message-area {
             clear: both;
             padding: 10px 0 0 0;
             font-weight: bold;
        }

    </style>
</head>
<body>
<header class="head1">
    <a href="index.html"><img src="imgs/earthelic logo file png.png" alt="logo" id="logo1"></a>
    
    <nav class="nav1">
        <div class="icons1">
           <ul class="nav-links">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="metal.php">Metal</a></li>
                    <li><a href="ceramic.php">Ceramic</a></li>
                     <li><a href="canvas.php">Paintings & Wall Art</a></li>
                    <li><a href="cart.php">Cart</a></li>
                    <li><a href="about.php">About us</a></li>
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <li class="nav-profile-wrap">
                            <a href="profile.php" class="nav-profile-link">
                                <span class="nav-profile-name"><?php echo ($_SESSION['full_name'] ?? 'Profile'); ?></span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li><a href="login.php">Log In</a></li>
                    <?php endif; ?>
                </ul>
        </div>
    </nav>
     <div class="hamburger" onclick="toggleNav()">
            <i class="fa-solid fa-bars"></i>
        </div>
</header>

<main>
    <div class="container">
        <h2><?php echo $single_order_id ? "Details for Order #{$single_order_id}" : "My Orders"; ?></h2>

        <?php if (!empty($cancellation_message)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($cancellation_message); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <p style="text-align: center;">
                <?php echo $single_order_id ? "The requested order (#{$single_order_id}) was not found or does not belong to your account." : "You have not placed any orders yet."; ?>
            </p>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <h3>
                            <?php if (!$single_order_id): ?>
                                <a href="my_orders.php?order_id=<?php echo $order['order_id']; ?>" style="text-decoration: none; color: inherit;">Order #<?php echo htmlspecialchars((string)$order['order_id']); ?></a>
                            <?php else: ?>
                                Order #<?php echo htmlspecialchars((string)$order['order_id']); ?>
                            <?php endif; ?>
                        </h3>
                        <span class="order-status <?php echo htmlspecialchars($order['status']); ?>">
                            <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                        </span>
                    </div>
                    <div class="order-detail-grid">
                        <p><strong>Date:</strong> <?php echo htmlspecialchars(date("d M Y H:i", strtotime($order['order_date']))); ?></p>
                        <p><strong>Amount:</strong> ₹<?php echo number_format((float)$order['total_amount'], 2); ?> (via <?php echo htmlspecialchars(strtoupper($order['payment_method'])); ?>)</p>
                    </div>
                    
                    <?php 
                        // Show the Cancel button only if the status is 'pending' OR 'processing'
                        if ($order['status'] === 'pending' || $order['status'] === 'processing'): 
                    ?>
                        <div style="text-align: right;">
                            <form method="post" action="my_orders.php" onsubmit="return confirm('WARNING: Are you sure you want to cancel Order #<?php echo $order['order_id']; ?>? This action cannot be undone and a refund will be initiated.');">
                                <input type="hidden" name="cancel_order_id" value="<?php echo htmlspecialchars((string)$order['order_id']); ?>">
                                <input type="hidden" name="cancellation_reason" value="User cancelled via My Orders page.">
                                <button type="submit" class="cancel-btn">Cancel Order #<?php echo htmlspecialchars((string)$order['order_id']); ?></button>
                            </form>
                        </div>
                    <?php 
                        // CHANGE 3: Display Refund Details form for cancelled orders
                        elseif ($order['status'] === 'cancelled' && $single_order_id):
                        
                            $refund_message = '';
                            if ($order['refund_status'] === 'details_provided') {
                                $refund_message = '<p style="color: green;">Refund details have been received. Processing is underway.</p>';
                            } elseif ($order['refund_status'] === 'processed') {
                                $refund_message = '<p style="color: green;">Refund has been processed.</p>';
                            } elseif ($order['refund_status'] === 'initiated') {
                                $refund_message = '<p style="color: orange;">Refund initiated. **Please provide your account details below.**</p>';
                            } else {
                                $refund_message = '<p style="color: red;">Refund status: ' . htmlspecialchars(ucfirst($order['refund_status'])) . '. Contact support if needed.</p>';
                            }
                    ?>
                        <div id="refund-form-section">
                            <h4>Refund Information</h4>
                            <?php echo $refund_message; ?>
                            
                            <?php if ($order['refund_status'] === 'initiated'): ?>
                            <form id="refund-details-form">
                                <input type="hidden" id="refund_order_id" value="<?php echo htmlspecialchars((string)$order['order_id']); ?>">
                                <label for="refund_details">Enter Bank Account No. / IFSC or UPI ID for Refund (Max 255 chars):</label>
                                <textarea id="refund_details" name="refund_details" rows="3" placeholder="e.g., A/C: 1234567890, IFSC: ABCD0000123 OR UPI: yourname@bankname"></textarea>
                                <button type="submit" id="submit-refund-btn">Submit Details</button>
                                <div id="refund-message-area"></div>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div style="text-align: center; margin-top: 20px;">
            <?php if ($single_order_id): ?>
                <a href="my_orders.php" class="btn1">View All Orders</a>
            <?php endif; ?>
            <a href="profile.php" class="btn1">Go back to Profile</a>
        </div>
    </div>
</main>

<footer class="foot1">
    <p>&copy; 2024 Earthelic</p>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('refund-details-form');
        const messageArea = document.getElementById('refund-message-area');
        const submitBtn = document.getElementById('submit-refund-btn');

        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const orderId = document.getElementById('refund_order_id').value;
                const refundDetails = document.getElementById('refund_details').value;

                if (refundDetails.trim() === '') {
                    messageArea.innerHTML = '<span style="color: red;">Please enter your bank or UPI details.</span>';
                    return;
                }

                messageArea.innerHTML = '<span style="color: gray;"><i class="fa-solid fa-spinner fa-spin"></i> Submitting details...</span>';
                submitBtn.disabled = true;

                fetch('my_orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'submit_refund_details',
                        order_id: orderId,
                        refund_details: refundDetails
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageArea.innerHTML = '<span style="color: green;"><i class="fa-solid fa-check-circle"></i> ' + data.message + '</span>';
                        // Optionally hide the form after successful submission
                        form.style.display = 'none'; 
                        // Reload the page to refresh the refund status section
                        setTimeout(() => {
                             window.location.href = window.location.href.split('?')[0] + '?order_id=' + orderId;
                        }, 1500); 
                    } else {
                        messageArea.innerHTML = '<span style="color: red;"><i class="fa-solid fa-times-circle"></i> ' + data.message + '</span>';
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error submitting refund details:', error);
                    messageArea.innerHTML = '<span style="color: red;">An error occurred. Please try again.</span>';
                    submitBtn.disabled = false;
                });
            });
        }
    });
</script>
</body>
</html>