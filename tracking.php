<?php
session_start();
include "db_connect.php"; // DB connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$statusMsg = "";
$orderData = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Stricter input validation. Use filter_input for security and type safety.
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    if ($order_id === false || $order_id === null) {
        $statusMsg = "⚠️ Invalid order ID provided.";
    } else {
        $user_id = $_SESSION['user_id'];

        // 2. Add error handling for the prepared statement
        // Updated query to fetch the total_amount
        $query = "SELECT order_id, status, order_date, update_date, total_amount
                  FROM Orders
                  WHERE order_id = ? AND user_id = ?";
        if ($stmt = $conn->prepare($query)) {
            $stmt->bind_param("ii", $order_id, $user_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $orderData = $result->fetch_assoc();

                    // Build a single, detailed message
                    $initialStatus = "";
                    if ($orderData['status'] == "shipped") {
                        $initialStatus = "✅ Your order #{$order_id} has been dispatched and is on the way!";
                    } elseif ($orderData['status'] == "delivered") {
                        $initialStatus = "🎉 Your order #{$order_id} has been delivered successfully!";
                    } elseif ($orderData['status'] == "processing") {
                        $initialStatus = "⏳ Your order #{$order_id} is being processed.";
                    } elseif ($orderData['status'] == "pending") {
                        $initialStatus = "🕒 Your order #{$order_id} is pending and will be processed soon.";
                    } elseif ($orderData['status'] == "cancelled") {
                        $initialStatus = "❌ Your order #{$order_id} was cancelled.";
                    }
                    
                    // Combine all info into the status message
                    $statusMsg = "
                        <h3>" . htmlspecialchars($initialStatus) . "</h3>
                        <p><strong>Order ID:</strong> " . htmlspecialchars($orderData['order_id']) . "</p>
                        <p><strong>Status:</strong> " . htmlspecialchars(ucfirst($orderData['status'])) . "</p>
                        <p><strong>Last Updated:</strong> " . htmlspecialchars($orderData['update_date']) . "</p>
                        <p><strong>Order Date:</strong> " . htmlspecialchars($orderData['order_date']) . "</p>
                        <p><strong>Total Amount:</strong> " . htmlspecialchars(number_format($orderData['total_amount'], 2)) . "</p>
                    ";

                } else {
                    $statusMsg = "⚠️ No order found with this ID for your account.";
                }
                $stmt->close();
            } else {
                $statusMsg = "❌ Database execution failed.";
                // In a real app, log the detailed error, don't show to user
            }
        } else {
            $statusMsg = "❌ Database query preparation failed.";
            // In a real app, log the detailed error, don't show to user
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Check Order Status</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            padding: 40px;
        }
        .container {
            max-width: 500px;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin: auto;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            margin: 15px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #0275d8;
            border: none;
            color: #fff;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background: #025aa5;
        }
        .status-box {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            background: #f1f1f1;
            font-size: 16px;
            text-align: center;
        }
        .status-box p {
            text-align: left;
            margin: 5px 0;
        }

        /* New styles for the timeline */
        .timeline-container {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 20px 0;
            margin-bottom: 20px;
        }

        .timeline-line {
            position: absolute;
            top: 50%;
            left: 0;
            height: 4px;
            background-color: #ddd;
            width: 100%;
            transform: translateY(-50%);
            z-index: 1;
        }

        .timeline-progress {
            height: 4px;
            background-color: #0275d8;
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            z-index: 2;
            transition: width 0.7s ease;
            width: 0%; /* Start with 0 width */
        }

        .timeline-step {
            position: relative;
            text-align: center;
            flex: 1;
            z-index: 2;
        }

        .timeline-circle {
            width: 25px;
            height: 25px;
            background-color: #ddd;
            border-radius: 50%;
            margin: 0 auto 10px;
            transition: background-color 0.5s ease, box-shadow 0.5s ease;
        }

        .timeline-step.active .timeline-circle {
            background-color: #0275d8;
            box-shadow: 0 0 0 5px rgba(2, 117, 216, 0.3);
        }

        .timeline-step.active p {
            font-weight: bold;
            color: #0275d8;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Check Order Status</h2>
    <form method="POST">
        <label for="order_id">Enter Order ID:</label>
        <input type="text" name="order_id" id="order_id" placeholder="e.g., 101" required>
        <button type="submit">Check Status</button>
    </form>

    <?php if ($statusMsg != "") { ?>
        <div class="status-box">
            <div class="timeline-container">
                <div class="timeline-line"></div>
                <div class="timeline-progress"></div>
                <div class="timeline-step pending">
                    <div class="timeline-circle"></div>
                    <p>Pending</p>
                </div>
                <div class="timeline-step processing">
                    <div class="timeline-circle"></div>
                    <p>Processing</p>
                </div>
                <div class="timeline-step shipped">
                    <div class="timeline-circle"></div>
                    <p>Shipped</p>
                </div>
                <div class="timeline-step delivered">
                    <div class="timeline-circle"></div>
                    <p>Delivered</p>
                </div>
            </div>
            <?= $statusMsg; ?>
        </div>
    <?php } ?>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const statusBox = document.querySelector('.status-box');
        if (statusBox) {
            // Find the status from the hidden PHP message
            const statusMessage = statusBox.querySelector('p:nth-of-type(2)').textContent;
            let currentStatus = '';

            if (statusMessage.toLowerCase().includes("pending")) {
                currentStatus = 'pending';
            } else if (statusMessage.toLowerCase().includes("processing")) {
                currentStatus = 'processing';
            } else if (statusMessage.toLowerCase().includes("shipped")) {
                currentStatus = 'shipped';
            } else if (statusMessage.toLowerCase().includes("delivered")) {
                currentStatus = 'delivered';
            } else {
                return; // Exit if no valid status is found
            }

            const steps = document.querySelectorAll('.timeline-step');
            const progressBar = document.querySelector('.timeline-progress');
            let progressWidth = 0;

            for (let i = 0; i < steps.length; i++) {
                steps[i].classList.add('active');
                if (steps[i].classList.contains(currentStatus)) {
                    progressWidth = (i / (steps.length - 1)) * 100;
                    break;
                }
            }

            progressBar.style.width = progressWidth + '%';
        }
    });
</script>
</body>
</html>
