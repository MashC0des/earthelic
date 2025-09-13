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
   Fetch user's last shipping address and payment details
------------------------------------------- */
$existing_address = null;
$stmt = $conn->prepare("SELECT address_id, address_line1, address_line2, city, state, postal_code, country FROM ShippingAddresses WHERE user_id = ? ORDER BY address_id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $existing_address = $result->fetch_assoc();
}
$stmt->close();

$existing_card_details = null;
$stmt = $conn->prepare("SELECT card_details_id, cardholder_name, SUBSTRING(card_number, -4) AS last_four, expiry_date FROM Card_Details WHERE user_id = ? ORDER BY card_details_id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $existing_card_details = $result->fetch_assoc();
}
$stmt->close();

$existing_upi_details = null;
$stmt = $conn->prepare("SELECT upi_details_id, upi_id FROM Upi_Details WHERE user_id = ? ORDER BY upi_details_id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $existing_upi_details = $result->fetch_assoc();
}
$stmt->close();

/* -------------------------------------------
   Handle payment submission
------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    $payment_amount = $grand_total;
    $shipping_address_id = null;

    if (isset($_POST['change_address_flag']) && $_POST['change_address_flag'] === 'yes') {
        $address_line1 = $_POST['address_line1'] ?? '';
        $address_line2 = $_POST['address_line2'] ?? '';
        $city = $_POST['city'] ?? '';
        $state = $_POST['state'] ?? '';
        $postal_code = $_POST['postal_code'] ?? '';
        $country = $_POST['country'] ?? '';
        
        // Server-side validation for new address
        if (empty($address_line1) || empty($city) || empty($state) || empty($postal_code) || empty($country)) {
            // Handle error: redirect or display a message
            exit("Please fill in all required address fields.");
        }
        
        $stmt = $conn->prepare("INSERT INTO ShippingAddresses (user_id, address_line1, address_line2, city, state, postal_code, country) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $user_id, $address_line1, $address_line2, $city, $state, $postal_code, $country);
        $stmt->execute();
        $shipping_address_id = $stmt->insert_id;
        $stmt->close();
    } else if (isset($_POST['existing_address_id'])) {
        $shipping_address_id = (int)$_POST['existing_address_id'];
    }

    if ($shipping_address_id) {
        $stmt = $conn->prepare("INSERT INTO Orders (user_id, total_amount, status, shipping_address_id) VALUES (?, ?, 'processing', ?)");
        $stmt->bind_param("idi", $user_id, $payment_amount, $shipping_address_id);
        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();

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
        
        $payment_method_input = $_POST['payment_method'] ?? 'card';
        $payment_method = '';
        $payment_status = 'completed';

        switch ($payment_method_input) {
            case 'card':
                $payment_method = 'credit_card';
                if (isset($_POST['change_card_flag']) && $_POST['change_card_flag'] === 'yes') {
                    $card_number = $_POST['card_number'] ?? '';
                    $cardholder_name = $_POST['card_name'] ?? '';
                    $expiry_date = $_POST['expiry'] ?? '';
                    
                    // Server-side validation for new card details
                    if (empty($card_number) || empty($cardholder_name) || empty($expiry_date)) {
                        exit("Please fill in all required card details.");
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO Card_Details (user_id, card_number, cardholder_name, expiry_date) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $user_id, $card_number, $cardholder_name, $expiry_date);
                    $stmt->execute();
                }
                break;
            case 'upi':
                $payment_method = 'upi';
                if (isset($_POST['change_upi_flag']) && $_POST['change_upi_flag'] === 'yes') {
                    $upi_id = $_POST['upi_id'] ?? '';
                    
                    // Server-side validation for new UPI ID
                    if (empty($upi_id)) {
                        exit("Please enter a valid UPI ID.");
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO Upi_Details (user_id, upi_id) VALUES (?, ?)");
                    $stmt->bind_param("is", $user_id, $upi_id);
                    $stmt->execute();
                }
                break;
            case 'cod':
                $payment_method = 'cod';
                $payment_status = 'pending';
                break;
            default:
                $payment_method = 'credit_card';
                break;
        }

        $stmt = $conn->prepare("INSERT INTO Payments (order_id, payment_method, payment_status) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $order_id, $payment_method, $payment_status);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("DELETE FROM Cart_Items WHERE cart_id = ?");
        $stmt->bind_param("i", $cart_id);
        $stmt->execute();
        $stmt->close();

        header("Location: confirmation.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - Earthelic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="stylesheet" href="https://db.onlinewebfonts.com/c/ef6bdf5ef216552c7e9869841e891ca0?family=Arial+Rounded+MT+Bold">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/metal.css">
    <link rel="stylesheet" href="css/checkout.css">
   
</head>
<body>
<header class="head1">
    <a href="landing.html"><img src="imgs/earthelic logo file png.png" alt="logo" id="logo1"></a>
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
            <form method="post" action="">
                <h3>Shipping Details</h3>
                <?php if ($existing_address): ?>
                    <div id="existing-address-container">
                        <p style="color: #fff; font-size: 1.1em; line-height: 1.5;">
                            Shipping to:<br>
                            <?php echo htmlspecialchars($existing_address['address_line1']); ?><br>
                            <?php echo htmlspecialchars($existing_address['address_line2']); ?><br>
                            <?php echo htmlspecialchars($existing_address['city']); ?>, <?php echo htmlspecialchars($existing_address['state']); ?> <?php echo htmlspecialchars($existing_address['postal_code']); ?><br>
                            <?php echo htmlspecialchars($existing_address['country']); ?>
                        </p>
                        <div class="address-buttons">
                            <button type="button" class="btn1" id="change-address-btn" style="width: auto;">Change Address</button>
                        </div>
                        <input type="hidden" name="existing_address_id" value="<?php echo htmlspecialchars((string)$existing_address['address_id']); ?>">
                    </div>
                    <div id="new-address-form" style="display:none;">
                <?php else: ?>
                    <div id="new-address-form">
                <?php endif; ?>
                        <div class="form-group">
                            <label for="address_line1">Address Line 1</label>
                            <input type="text" id="address_line1" name="address_line1" <?php echo !$existing_address ? 'required' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="address_line2">Address Line 2 (Optional)</label>
                            <input type="text" id="address_line2" name="address_line2">
                        </div>
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" <?php echo !$existing_address ? 'required' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="state">State</label>
                            <input type="text" id="state" name="state" <?php echo !$existing_address ? 'required' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="postal_code">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" <?php echo !$existing_address ? 'required' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" <?php echo !$existing_address ? 'required' : ''; ?>>
                        </div>
                        <?php if ($existing_address): ?>
                            <div class="address-buttons">
                                <button type="button" class="btn1" id="cancel-change-btn" style="width: auto; background-color: #a0a0a0; border-color: #a0a0a0;">Cancel</button>
                            </div>
                        <?php endif; ?>
                    </div>
                
                <input type="hidden" name="change_address_flag" id="change-address-flag" value="<?php echo $existing_address ? 'no' : 'yes'; ?>">

                <h3>Payment Details</h3>
                <div class="payment-options">
                    <input type="radio" id="card" name="payment_method" value="card" checked>
                    <label for="card">Credit/Debit Card</label>
                    <input type="radio" id="upi" name="payment_method" value="upi">
                    <label for="upi">UPI</label>
                    <input type="radio" id="cod" name="payment_method" value="cod">
                    <label for="cod">Cash on Delivery</label>
                </div>

                <div id="card-details" class="payment-method-details" style="display:block;">
                    <?php if ($existing_card_details): ?>
                        <div id="existing-card-container">
                            <p style="color: #fff; font-size: 1.1em; line-height: 1.5;">
                                Saved Card:<br>
                                Cardholder: <?php echo htmlspecialchars($existing_card_details['cardholder_name']); ?><br>
                                Ends in: <?php echo htmlspecialchars($existing_card_details['last_four']); ?><br>
                                Expires: <?php echo htmlspecialchars($existing_card_details['expiry_date']); ?>
                            </p>
                            <button type="button" class="btn1" id="change-card-btn" style="width: auto; margin-top: 15px;">Change Card</button>
                        </div>
                        <div id="new-card-form" style="display:none;">
                    <?php else: ?>
                        <div id="new-card-form">
                    <?php endif; ?>
                            <div class="form-group">
                                <label for="card_name">Cardholder Name</label>
                                <input type="text" id="card_name" name="card_name" >
                            </div>
                            <div class="form-group">
                                <label for="card_number">Card Number</label>
                                <input type="text" id="card_number" name="card_number" pattern="\d{16}" title="16-digit card number" >
                            </div>
                            <div class="form-group">
                                <label for="expiry">Expiry Date (MM/YY)</label>
                                <input type="text" id="expiry" name="expiry" pattern="\d{2}/\d{2}" title="Format: MM/YY" >
                            </div>
                            <div class="form-group">
                                <label for="cvv">CVV</label>
                                <input type="text" id="cvv" name="cvv" pattern="\d{3,4}" title="3 or 4 digits" >
                            </div>
                            <?php if ($existing_card_details): ?>
                                <button type="button" class="btn1" id="cancel-card-btn" style="width: auto; background-color: #a0a0a0; border-color: #a0a0a0;">Cancel</button>
                            <?php endif; ?>
                        </div>
                    <input type="hidden" name="change_card_flag" id="change-card-flag" value="<?php echo $existing_card_details ? 'no' : 'yes'; ?>">
                </div>

                <div id="upi-details" class="payment-method-details">
                    <?php if ($existing_upi_details): ?>
                        <div id="existing-upi-container">
                            <p style="color: #fff; font-size: 1.1em; line-height: 1.5;">
                                Saved UPI ID:<br>
                                <?php echo htmlspecialchars($existing_upi_details['upi_id']); ?>
                            </p>
                            <button type="button" class="btn1" id="change-upi-btn" style="width: auto; margin-top: 15px;">Change UPI ID</button>
                        </div>
                        <div id="new-upi-form" style="display:none;">
                    <?php else: ?>
                        <div id="new-upi-form">
                    <?php endif; ?>
                            <div class="form-group">
                                <label for="upi_id">Your UPI ID</label>
                                <input type="text" id="upi_id" name="upi_id" >
                            </div>
                            <?php if ($existing_upi_details): ?>
                                <button type="button" class="btn1" id="cancel-upi-btn" style="width: auto; background-color: #a0a0a0; border-color: #a0a0a0;">Cancel</button>
                            <?php endif; ?>
                        </div>
                    <p style="color: #fff; font-size: 0.9em;">You will receive a payment request on your UPI app.</p>
                    <input type="hidden" name="change_upi_flag" id="change-upi-flag" value="<?php echo $existing_upi_details ? 'no' : 'yes'; ?>">
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

        const addressInputs = document.querySelectorAll('#new-address-form input');
        const cardInputs = document.querySelectorAll('#new-card-form input');
        const upiInputs = document.querySelectorAll('#new-upi-form input');

        function setRequired(inputs, isRequired) {
            inputs.forEach(input => {
                if (isRequired) {
                    input.setAttribute('required', '');
                } else {
                    input.removeAttribute('required');
                }
            });
        }
        
        // Address Toggling
        const changeAddressBtn = document.getElementById('change-address-btn');
        const cancelChangeBtn = document.getElementById('cancel-change-btn');
        const existingAddressContainer = document.getElementById('existing-address-container');
        const newAddressForm = document.getElementById('new-address-form');
        const changeAddressFlag = document.getElementById('change-address-flag');

        if (changeAddressBtn) {
            changeAddressBtn.addEventListener('click', () => {
                existingAddressContainer.style.display = 'none';
                newAddressForm.style.display = 'block';
                setRequired(addressInputs, true);
                changeAddressFlag.value = 'yes';
            });
        }
        if (cancelChangeBtn) {
            cancelChangeBtn.addEventListener('click', () => {
                existingAddressContainer.style.display = 'block';
                newAddressForm.style.display = 'none';
                setRequired(addressInputs, false);
                changeAddressFlag.value = 'no';
            });
        }
        
        // Card Details Toggling
        const changeCardBtn = document.getElementById('change-card-btn');
        const cancelCardBtn = document.getElementById('cancel-card-btn');
        const existingCardContainer = document.getElementById('existing-card-container');
        const newCardForm = document.getElementById('new-card-form');
        const changeCardFlag = document.getElementById('change-card-flag');
        
        if (changeCardBtn) {
            changeCardBtn.addEventListener('click', () => {
                existingCardContainer.style.display = 'none';
                newCardForm.style.display = 'block';
                setRequired(cardInputs, true);
                changeCardFlag.value = 'yes';
            });
        }
        if (cancelCardBtn) {
            cancelCardBtn.addEventListener('click', () => {
                existingCardContainer.style.display = 'block';
                newCardForm.style.display = 'none';
                setRequired(cardInputs, false);
                changeCardFlag.value = 'no';
            });
        }

        // UPI Details Toggling
        const changeUpiBtn = document.getElementById('change-upi-btn');
        const cancelUpiBtn = document.getElementById('cancel-upi-btn');
        const existingUpiContainer = document.getElementById('existing-upi-container');
        const newUpiForm = document.getElementById('new-upi-form');
        const changeUpiFlag = document.getElementById('change-upi-flag');

        if (changeUpiBtn) {
            changeUpiBtn.addEventListener('click', () => {
                existingUpiContainer.style.display = 'none';
                newUpiForm.style.display = 'block';
                setRequired(upiInputs, true);
                changeUpiFlag.value = 'yes';
            });
        }
        if (cancelUpiBtn) {
            cancelUpiBtn.addEventListener('click', () => {
                existingUpiContainer.style.display = 'block';
                newUpiForm.style.display = 'none';
                setRequired(upiInputs, false);
                changeUpiFlag.value = 'no';
            });
        }
        
        // Initial setup to handle 'required' attributes correctly
        updateFormVisibility();
        // This part needs to be improved to ensure inputs for the selected method are required
        // based on the initial 'change_flag' value
        if (document.querySelector('input[name="payment_method"]:checked').value === 'card' && changeCardFlag.value === 'yes') {
            setRequired(cardInputs, true);
        } else if (document.querySelector('input[name="payment_method"]:checked').value === 'upi' && changeUpiFlag.value === 'yes') {
            setRequired(upiInputs, true);
        }
    });
</script>

</body>
</html>