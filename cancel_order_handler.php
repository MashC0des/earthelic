<?php
session_start();
// IMPORTANT: Ensure 'db_connect.php' exists and provides a valid $conn object.
include "db_connect.php"; 

// Set header for JSON response
header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    $response['message'] = "Authentication required. Please log in.";
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = "Invalid request method.";
    http_response_code(405); // Method Not Allowed
    echo json_encode($response);
    exit();
}

// 2. Collect and sanitize input data
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$account_number = filter_input(INPUT_POST, 'account_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
$upi_id = filter_input(INPUT_POST, 'upi_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;

// Simple input validation
if (!$order_id || empty($reason)) {
    $response['message'] = "Error: Missing Order ID or Cancellation Reason.";
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit();
}

// Check that at least one refund method is provided
if (empty($account_number) && empty($upi_id)) {
    $response['message'] = "Error: Please provide either a Bank Account Number/Wallet ID or a UPI ID for the refund.";
    http_response_code(400);
    echo json_encode($response);
    exit();
}


try {
    // Start transaction to ensure atomicity (both update and insert succeed or fail together)
    $conn->begin_transaction();

    // 3. Check Order Status, Ownership, and Fetch Amount
    // Only 'pending' or 'processing' orders can be cancelled. Use FOR UPDATE to lock the row.
    $stmt_check = $conn->prepare("SELECT total_amount, status FROM Orders WHERE order_id = ? AND user_id = ? FOR UPDATE");
    if (!$stmt_check) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    $stmt_check->bind_param("ii", $order_id, $user_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $order = $result->fetch_assoc();
    $stmt_check->close();

    if (!$order) {
        throw new Exception("Order not found or does not belong to the current user.");
    }

    $cancellable_statuses = ['pending', 'processing'];
    if (!in_array($order['status'], $cancellable_statuses)) {
        throw new Exception("Order #{$order_id} cannot be cancelled. Current status is '{$order['status']}'.");
    }

    $refund_amount = $order['total_amount'];

    // 4. Update the Order Status to 'cancelled'
    $sql_update_order = "UPDATE Orders SET status = 'cancelled' WHERE order_id = ?";
    $stmt_update_order = $conn->prepare($sql_update_order);
    if (!$stmt_update_order) {
        throw new Exception("Database prepare failed for order update: " . $conn->error);
    }
    $stmt_update_order->bind_param("i", $order_id);
    if (!$stmt_update_order->execute()) {
        throw new Exception("Order status update failed: " . $stmt_update_order->error);
    }
    $stmt_update_order->close();


    // 5. Insert a new record into the Refunds table (status defaults to 'pending')
    // Fields: order_id, refund_amount, reason, refund_date, status, account_number, upi_id
    $sql_insert_refund = "INSERT INTO Refunds (order_id, refund_amount, reason, refund_date, account_number, upi_id) 
                          VALUES (?, ?, ?, NOW(), ?, ?)";
    $stmt_insert_refund = $conn->prepare($sql_insert_refund);
    if (!$stmt_insert_refund) {
        throw new Exception("Database prepare failed for refund insert: " . $conn->error);
    }
    
    // Bind parameters: (i=order_id, d=refund_amount, s=reason, s=account_number, s=upi_id)
    $stmt_insert_refund->bind_param("idsss", $order_id, $refund_amount, $reason, $account_number, $upi_id);
    
    if (!$stmt_insert_refund->execute()) {
        throw new Exception("Refund record insertion failed: " . $stmt_insert_refund->error);
    }
    $stmt_insert_refund->close();

    // Commit transaction if both steps succeeded
    $conn->commit();

    // 6. Return Success Response
    $response['success'] = true;
    $response['message'] = "Order #{$order_id} has been successfully cancelled. A refund request for ₹" . number_format($refund_amount, 2) . " has been initiated and is now 'pending'.";

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("Order Cancellation Error: " . $e->getMessage());

    $response['message'] = "Cancellation failed. Please try again. (" . substr($e->getMessage(), 0, 50) . "...)";
    http_response_code(500); // Internal Server Error
}

// Echo JSON response
echo json_encode($response);

// Close connection
if (isset($conn)) {
    $conn->close();
}
?>
