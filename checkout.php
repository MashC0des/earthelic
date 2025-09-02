<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . "/db_connect.php";

/* -------------------------------------------
   Auth check
------------------------------------------- */
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

/* -------------------------------------------
   Helpers
------------------------------------------- */
function to_float($val): float {
    return (float)$val;
}

/* -------------------------------------------
   Fetch cart data from DB and calculate total
------------------------------------------- */
$cart_id = null;
$stmt = $conn->prepare("SELECT cart_id FROM Cart WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: cart.php"); // Redirect to cart if it's empty
    exit();
}

$row = $result->fetch_assoc();
$cart_id = $row['cart_id'];
$stmt->close();

$cart_items = [];
$grand_total = 0.0;
$stmt = $conn->prepare("SELECT ci.product_id, ci.quantity, p.product_name, p.price, p.image_url 
                        FROM Cart_Items ci 
                        JOIN Products p ON ci.product_id = p.product_id 
                        WHERE ci.cart_id = ?");
$stmt->bind_param("i", $cart_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $grand_total += to_float($row['price']) * (int)$row['quantity'];
}
$stmt->close();

/* -------------------------------------------
   Handle payment submission (simulated)
   This section is now updated to create entries
   in the new Orders and Order_Items tables.
------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    // 1. Insert a new record into the Orders table
    $stmt = $conn->prepare("INSERT INTO Orders (user_id, total_amount, status) VALUES (?, ?, 'processing')");
    $stmt->bind_param("id", $user_id, $grand_total);
    $stmt->execute();
    $order_id = $stmt->insert_id;
    $stmt->close();

    // 2. Insert each cart item into the Order_Items table
    $stmt = $conn->prepare("INSERT INTO Order_Items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
    foreach ($cart_items as $item) {
        $price = to_float($item['price']);
        $quantity = (int)$item['quantity'];
        $product_id = (int)$item['product_id'];
        $product_name = $item['product_name'];
        $stmt->bind_param("iisid", $order_id, $product_id, $product_name, $quantity, $price);
        $stmt->execute();
    }
    $stmt->close();
    
    // 3. Insert a new record into the Payments table
    $payment_method_input = $_POST['payment_method'] ?? 'card';
    $payment_method = '';
    $payment_status = 'completed';

    switch ($payment_method_input) {
        case 'card':
            $payment_method = 'credit_card';
            break;
        case 'upi':
            $payment_method = 'upi';
            break;
        case 'cod':
            $payment_method = 'cod';
            $payment_status = 'pending'; // Set status to pending for Cash on Delivery
            break;
        default:
            $payment_method = 'credit_card'; // Default to card
            break;
    }

    $stmt = $conn->prepare("INSERT INTO Payments (order_id, payment_method, payment_status) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $order_id, $payment_method, $payment_status);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();
    
    // 4. Store specific payment details based on method
    if ($payment_method === 'credit_card') {
        $card_number = $_POST['card_number'] ?? '';
        $cardholder_name = $_POST['card_name'] ?? '';
        $expiry_date = $_POST['expiry'] ?? '';
        
        $stmt = $conn->prepare("INSERT INTO Card_Details (payment_id, card_number, cardholder_name, expiry_date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $payment_id, $card_number, $cardholder_name, $expiry_date);
        $stmt->execute();
        $stmt->close();
    } else if ($payment_method === 'upi') {
        $upi_id = $_POST['upi_id'] ?? '';
        
        $stmt = $conn->prepare("INSERT INTO Upi_Details (payment_id, upi_id) VALUES (?, ?)");
        $stmt->bind_param("is", $payment_id, $upi_id);
        $stmt->execute();
        $stmt->close();
    }

    // 5. Clear the user's cart after the order is successfully placed
    $stmt = $conn->prepare("DELETE FROM Cart_Items WHERE cart_id = ?");
    $stmt->bind_param("i", $cart_id);
    $stmt->execute();
    $stmt->close();

    // Redirect to a confirmation page
    header("Location: confirmation.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - Earthelic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/metal.css">
    <style>
        /* General layout for centering content */
        body, html {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        main {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 20px 0;
        }

        .checkout-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            max-width: 900px;
            width: 95%;
            margin: auto;
        }
        .order-summary {
            width: 100%;
            margin-bottom: 25px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
            padding-bottom: 25px;
        }
        .order-summary table {
            width: 100%;
            border-collapse: collapse;
            color: #fff;
        }
        .order-summary th, .order-summary td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            text-align: left;
        }
        .order-summary th {
            font-weight: bold;
            color: #904A2D;
            font-size: 1.1rem;
        }
        .order-summary .total-row td {
            font-weight: bold;
            color: #904A2D;
            font-size: 1.4rem;
        }
        .payment-form {
            width: 100%;
        }
        .payment-form .form-group {
            margin-bottom: 20px;
        }
        .payment-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #eee;
        }
        .payment-form input {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            background-color: rgba(255, 255, 255, 0.8);
            color: #333;
        }
        .payment-form .btn1 {
            width: 100%;
            font-size: 1.4rem;
            margin-top: 25px;
        }
        .payment-form .btn1.pay-now-btn {
            background-color: #904A2D !important;
            border-color: #904A2D !important;
        }
        .payment-form .btn1.pay-now-btn:hover {
            background-color: #7a3c24 !important;
            box-shadow: 0 0 25px #904A2D !important;
        }
        .payment-options {
            margin-bottom: 20px;
        }
        .payment-options label {
            display: inline-block;
            margin-right: 20px;
            font-weight: normal;
        }
        .payment-method-details {
            display: none;
            margin-top: 20px;
        }
        h3 {
            font-size: 1.8rem;
            color: #904A2D;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<header class="head1">
    <a href="landing.html"><img src="imgs/earthelic logo file png.png" id="logo1" alt="Earthelic Logo"></a>
    <h2>Checkout</h2>
</header>

<main>
    <div class="checkout-container">
        <h3>Order Summary</h3>
        <div class="order-summary">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td>₹<?php echo number_format(to_float($item['price']), 2); ?></td>
                        <td><?php echo (int)$item['quantity']; ?></td>
                        <td>₹<?php echo number_format(to_float($item['price']) * (int)$item['quantity'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="3">Grand Total</td>
                        <td>₹<?php echo number_format($grand_total, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="payment-form">
            <h3>Payment Details</h3>
            <form method="post" action="">
                <div class="payment-options">
                    <input type="radio" id="card" name="payment_method" value="card" checked>
                    <label for="card">Credit/Debit Card</label>
                    <input type="radio" id="upi" name="payment_method" value="upi">
                    <label for="upi">UPI</label>
                    <input type="radio" id="cod" name="payment_method" value="cod">
                    <label for="cod">Cash on Delivery</label>
                </div>

                <div id="card-details" class="payment-method-details" style="display:block;">
                    <div class="form-group">
                        <label for="card_name">Cardholder Name</label>
                        <input type="text" id="card_name" name="card_name">
                    </div>
                    <div class="form-group">
                        <label for="card_number">Card Number</label>
                        <input type="text" id="card_number" name="card_number" pattern="\d{16}" title="16-digit card number">
                    </div>
                    <div class="form-group">
                        <label for="expiry">Expiry Date (MM/YY)</label>
                        <input type="text" id="expiry" name="expiry" pattern="\d{2}/\d{2}" title="Format: MM/YY">
                    </div>
                    <div class="form-group">
                        <label for="cvv">CVV</label>
                        <input type="text" id="cvv" name="cvv" pattern="\d{3,4}" title="3 or 4 digits">
                    </div>
                </div>

                <div id="upi-details" class="payment-method-details">
                    <div class="form-group">
                        <label for="upi_id">Your UPI ID</label>
                        <input type="text" id="upi_id" name="upi_id">
                    </div>
                    <p style="color: #fff; font-size: 0.9em;">You will receive a payment request on your UPI app.</p>
                </div>
                
                <div id="cod-details" class="payment-method-details">
                    <p style="color: #fff; font-size: 1.1em;">You have selected Cash on Delivery. Please keep the exact amount ready.</p>
                </div>

                <input type="hidden" name="total" value="<?php echo htmlspecialchars((string)$grand_total); ?>">
                <button type="submit" name="pay_now" class="btn1 pay-now-btn">Pay Now: ₹<?php echo number_format($grand_total, 2); ?></button>
            </form>
        </div>
    </div>
</main>

<footer class="foot1">
    <p>&copy; 2024 Earthelic</p>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        const cardDetails = document.getElementById('card-details');
        const upiDetails = document.getElementById('upi-details');
        const codDetails = document.getElementById('cod-details');
        
        const detailsMap = {
            'card': cardDetails,
            'upi': upiDetails,
            'cod': codDetails
        };
        
        function updateFormVisibility() {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
            for (const method in detailsMap) {
                if (method === selectedMethod) {
                    detailsMap[method].style.display = 'block';
                } else {
                    detailsMap[method].style.display = 'none';
                }
            }
        }
        
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', updateFormVisibility);
        });
    });
</script>

</body>
</html>
