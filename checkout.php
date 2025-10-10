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
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    $payment_amount = $grand_total;
    $shipping_address_id = null;

    // Validate shipping address
    if (isset($_POST['change_address_flag']) && $_POST['change_address_flag'] === 'yes') {
        $address_line1 = $_POST['address_line1'] ?? '';
        $address_line2 = $_POST['address_line2'] ?? '';
        $city = $_POST['city'] ?? '';
        $state = $_POST['state'] ?? '';
        $postal_code = $_POST['postal_code'] ?? '';
        $country = $_POST['country'] ?? '';
        
        // --- SERVER-SIDE VALIDATION FOR ADDRESS LINE 1 & 2 LENGTH (Max 60) ---
        if (strlen($address_line1) > 60) {
             $errors[] = "Address Line 1 cannot exceed 60 characters.";
        }
        if (strlen($address_line2) > 60) {
             $errors[] = "Address Line 2 cannot exceed 60 characters.";
        }
        
        // --- SERVER-SIDE VALIDATION FOR CITY, STATE, COUNTRY LENGTH (Max 30, Country Max 20) ---
        if (strlen($city) > 30) {
            $errors[] = "City name cannot exceed 30 characters.";
        }
        if (strlen($state) > 30) {
            $errors[] = "State name cannot exceed 30 characters.";
        }
        // Updated to max 20 characters for Country
        if (strlen($country) > 20) {
            $errors[] = "Country name cannot exceed 20 characters.";
        }
        // --------------------------------------------------------------

        // --- NEW SERVER-SIDE VALIDATION FOR POSTAL CODE (6 digits only) ---
        if (empty($postal_code)) {
            $errors[] = "Postal Code is required.";
        } elseif (strlen($postal_code) > 6 || !ctype_digit($postal_code)) { // Check for max 6 length and only digits
            $errors[] = "Postal Code must be up to 6 digits, containing only numbers.";
        }
        // ------------------------------------------------------------------

        if (empty($address_line1) || empty($city) || empty($state) || empty($postal_code) || empty($country)) {
            if (empty($address_line1) || empty($city) || empty($state) || empty($country)) {
                 $errors[] = "Please fill in all required address fields.";
            }
        }
    } else if (isset($_POST['existing_address_id'])) {
        $shipping_address_id = (int)$_POST['existing_address_id'];
    } else {
        $errors[] = "A shipping address must be selected or entered.";
    }

    // Validate payment method details
    $payment_method_input = $_POST['payment_method'] ?? 'card';
    switch ($payment_method_input) {
        case 'card':
            if (isset($_POST['change_card_flag']) && $_POST['change_card_flag'] === 'yes') {
                $card_number = $_POST['card_number'] ?? '';
                $cardholder_name = $_POST['card_name'] ?? '';
                
                // --- START: Reading month and year separately and combining them ---
                $month_input = $_POST['expiry_month'] ?? '';
                $year_input = $_POST['expiry_year'] ?? '';
                $expiry_date = "{$month_input}/{$year_input}"; // COMBINE FOR DB STORAGE & VALIDATION
                // --- END: Reading month and year separately and combining them ---
                
                $cvv = $_POST['cvv'] ?? '';
                
                // Server-side validation for Card Number (must be exactly 16 digits and only numbers)
                if (!preg_match('/^\d{16}$/', $card_number)) {
                    $errors[] = "Card Number must be exactly 16 digits and contain only numbers.";
                }
                
                // Server-side validation for Cardholder Name (max 20 characters)
                if (strlen($cardholder_name) > 20) {
                    $errors[] = "Cardholder Name cannot exceed 20 characters.";
                }

                // ********************************************************************************
                // * Updated server-side validation for Expiry Date (MM/YY format expected)
                // ********************************************************************************
                if (!preg_match('/^(\d{2})\/(\d{2})$/', $expiry_date, $matches)) {
                    $errors[] = "Expiry Date must be provided as two 2-digit numbers (MM/YY).";
                } else {
                    // Current year (last two digits, e.g., 25) and current month (1-12)
                    $current_year_yy = (int)date('y'); 
                    $current_month = (int)date('m');   

                    $month = (int)$matches[1];
                    $year_yy = (int)$matches[2];
                    
                    // --- DIGIT-BASED CHECKS (Must match client-side constraints) ---
                    $month_first_digit = (int)substr($matches[1], 0, 1);
                    $year_first_digit = (int)substr($matches[2], 0, 1);

                    // M1 Check: 0 or 1
                    if ($month_first_digit < 0 || $month_first_digit > 1) {
                        $errors[] = "The first month digit must be 0 or 1.";
                    }
                    // M2 Check: 0-9, but only 0-2 if M1 is 1. (This is covered by the 01-12 check below)

                    // Y1 Check: 2 to 4
                    if ($year_first_digit < 2 || $year_first_digit > 4) {
                        $errors[] = "The first year digit must be between 2 and 4 (i.e., between 20 and 49).";
                    }

                    // Check Month (01-12)
                    if ($month < 1 || $month > 12) {
                        $errors[] = "Expiry Month must be between 01 and 12.";
                    } 
                    
                    // Check Year (Current year (e.g., 25) up to 49)
                    // Y1=4, Y2=9 means max year is 49.
                    if ($year_yy < $current_year_yy || $year_yy > 49) {
                        $errors[] = "Expiry Year must be between " . $current_year_yy . " and 49 (inclusive).";
                    } 
                    
                    // Check if the card has already expired (only relevant if year is current year)
                    if ($year_yy === $current_year_yy && $month < $current_month) {
                        $errors[] = "The card has already expired.";
                    }
                }
                // ----------------------------------------------------------------------
                
                // Server-side validation for CVV (must be exactly 3 digits and only numbers)
                if (!preg_match('/^\d{3}$/', $cvv)) {
                    $errors[] = "CVV must be exactly 3 digits and contain only numbers.";
                }

                if (empty($card_number) || empty($cardholder_name) || empty($month_input) || empty($year_input) || empty($cvv)) {
                    // Re-check generic emptiness
                    if (empty($card_number) || empty($cardholder_name) || empty($cvv)) {
                         $errors[] = "Please fill in all required credit/debit card details.";
                    }
                }
            }
            break;
        case 'upi':
            if (isset($_POST['change_upi_flag']) && $_POST['change_upi_flag'] === 'yes') {
                $upi_id = $_POST['upi_id'] ?? '';
                
                // Added validation for UPI ID (max 25 chars and proper format)
                if (empty($upi_id)) {
                    $errors[] = "UPI ID is required.";
                } elseif (strlen($upi_id) > 25) {
                    $errors[] = "UPI ID cannot exceed 25 characters.";
                } elseif (!preg_match('/^[a-zA-Z0-9.\-_]{2,20}@[a-zA-Z0-9.\-_]{2,5}$/', $upi_id)) {
                    // Basic validation for common VPA format
                    $errors[] = "Please enter a valid UPI ID format (e.g., username@bank).";
                }
            }
            break;
        case 'cod':
            // No extra validation needed for COD
            break;
    }

    // Process payment if there are no errors
    if (empty($errors)) {
        // Insert new address if applicable
        if (isset($_POST['change_address_flag']) && $_POST['change_address_flag'] === 'yes') {
            $stmt = $conn->prepare("INSERT INTO ShippingAddresses (user_id, address_line1, address_line2, city, state, postal_code, country) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $user_id, $address_line1, $address_line2, $city, $state, $postal_code, $country);
            $stmt->execute();
            $shipping_address_id = $stmt->insert_id;
            $stmt->close();
        }

        // Insert new payment details if applicable
        switch ($payment_method_input) {
            case 'card':
                $payment_method = 'credit_card';
                $payment_status = 'completed';
                if (isset($_POST['change_card_flag']) && $_POST['change_card_flag'] === 'yes') {
                    // The combined $expiry_date (MM/YY) is saved here
                    $stmt = $conn->prepare("INSERT INTO Card_Details (user_id, card_number, cardholder_name, expiry_date) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $user_id, $card_number, $cardholder_name, $expiry_date);
                    $stmt->execute();
                }
                break;
            case 'upi':
                $payment_method = 'upi';
                $payment_status = 'completed';
                if (isset($_POST['change_upi_flag']) && $_POST['change_upi_flag'] === 'yes') {
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
                $payment_status = 'completed';
                break;
        }

        // Create the order
        $stmt = $conn->prepare("INSERT INTO Orders (user_id, total_amount, status, shipping_address_id) VALUES (?, ?, 'pending', ?)");
        $stmt->bind_param("idi", $user_id, $payment_amount, $shipping_address_id);
        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();

        // Insert order items
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
        
        // Insert payment record
        $stmt = $conn->prepare("INSERT INTO Payments (order_id, payment_method, payment_status) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $order_id, $payment_method, $payment_status);
        $stmt->execute();
        $stmt->close();
        
        // Clear the cart
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
    <a href="index.html"><img src="imgs/earthelic logo file png.png" alt="logo" id="logo1"></a>
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
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <h3>Shipping Details</h3>
                <?php if ($existing_address): ?>
                    <div id="existing-address-container">
                        <p style="color: #fff;">
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
                            <input type="text" id="address_line1" name="address_line1" maxlength="60" <?php echo !$existing_address ? 'required' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="address_line2">Address Line 2 (Optional)</label>
                            <input type="text" id="address_line2" name="address_line2" maxlength="60">
                        </div>
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" maxlength="30" <?php echo !$existing_address ? 'required' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="state">State</label>
                            <input type="text" id="state" name="state" maxlength="30" <?php echo !$existing_address ? 'required' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="postal_code">Postal Code</label>
                            <!-- Client-side: Only allows digits and limits to 6 chars -->
                            <input type="text" id="postal_code" name="postal_code" maxlength="6" 
                                pattern="\d{1,6}" 
                                inputmode="numeric" 
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 6)"
                                title="Up to 6-digit postal code" 
                                <?php echo !$existing_address ? 'required' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <!-- Client-side max length changed to 20 -->
                            <input type="text" id="country" name="country" maxlength="20" <?php echo !$existing_address ? 'required' : ''; ?>>
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
                            <p style="color: #fff;">
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
                                <!-- Client-side max length changed to 20 -->
                                <input type="text" id="card_name" name="card_name" maxlength="20" <?php echo !$existing_card_details ? 'required' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label for="card_number">Card Number</label>
                                <!-- Client-side constraints for 16 digit number only -->
                                <input type="text" id="card_number" name="card_number" 
                                    maxlength="16" 
                                    pattern="\d{16}" 
                                    inputmode="numeric" 
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 16)" 
                                    title="16-digit number" 
                                    <?php echo !$existing_card_details ? 'required' : ''; ?>>
                            </div>

                            <!-- START: SPLIT EXPIRY DATE FIELDS -->
                            <label style="color: white; display: block; margin-bottom: 5px;">Expiry Date</label>
                            <div class="form-group-flex">
                                <div class="form-group">
                                    <label for="expiry_month">Month (MM)</label>
                                    <input type="text" id="expiry_month" name="expiry_month" 
                                        maxlength="2" 
                                        pattern="\d{2}" 
                                        inputmode="numeric" 
                                        oninput="validateMonthInput(this)" 
                                        onblur="validateExpiryDate(this, document.getElementById('expiry_year'))"
                                        title="Two-digit month (01-12)" 
                                        <?php echo !$existing_card_details ? 'required' : ''; ?>>
                                    <span id="month-error" style="color: #ff9999; font-size: 0.8em; display: block; margin-top: 5px;"></span>
                                </div>
                                <div class="form-group">
                                    <label for="expiry_year">Year (YY)</label>
                                    <input type="text" id="expiry_year" name="expiry_year" 
                                        maxlength="2" 
                                        pattern="\d{2}" 
                                        inputmode="numeric" 
                                        oninput="validateYearInput(this)" 
                                        onblur="validateExpiryDate(document.getElementById('expiry_month'), this)"
                                        title="Two-digit year (YY)" 
                                        <?php echo !$existing_card_details ? 'required' : ''; ?>>
                                    <span id="year-error" style="color: #ff9999; font-size: 0.8em; display: block; margin-top: 5px;"></span>
                                </div>
                            </div>
                            <!-- END: SPLIT EXPIRY DATE FIELDS -->

                            <div class="form-group">
                                <label for="cvv">CVV</label>
                                <!-- Client-side constraints for 3-digit number only -->
                                <input type="text" id="cvv" name="cvv" 
                                    maxlength="3" 
                                    pattern="\d{3}" 
                                    inputmode="numeric" 
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 3)" 
                                    title="3-digit number" 
                                    <?php echo !$existing_card_details ? 'required' : ''; ?>>
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
                            <p style="color: #fff;">
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
                                <!-- Updated: Client-side max length is now 25 -->
                                <input type="text" id="upi_id" name="upi_id" maxlength="25" <?php echo !$existing_upi_details ? 'required' : ''; ?>>
                            </div>
                            <?php if ($existing_upi_details): ?>
                                <button type="button" class="btn1" id="cancel-upi-btn" style="width: auto; background-color: #a0a0a0; border-color: #a0a0a0;">Cancel</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    <p style="color: #fff;">You will receive a payment request on your UPI app.</p>
                    <input type="hidden" name="change_upi_flag" id="change-upi-flag" value="<?php echo $existing_upi_details ? 'no' : 'yes'; ?>">
                </div>
                
                <div id="cod-details" class="payment-method-details">
                    <p style="color: #fff;">You have selected Cash on Delivery. Please keep the exact amount ready.</p>
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
    const expiryMonthInput = document.getElementById('expiry_month');
    const expiryYearInput = document.getElementById('expiry_year');
    
    /**
     * Client-side function for strict validation of Month input based on digit constraints.
     * M1: 0-1, M2: 0-9 (but max 2 if M1 is 1)
     * @param {HTMLInputElement} input - The month input element.
     */
    function validateMonthInput(input) {
        let value = input.value.replace(/\D/g, '').substring(0, 2);
        
        // M1 Check: Restrict M1 to 0 or 1
        if (value.length >= 1) {
            const M1 = parseInt(value[0], 10);
            if (M1 > 1) {
                value = '0' + value.substring(1); // Force M1 to 0 if > 1 (e.g., '2' becomes '02')
            }
        }
        
        // M2 Check: If M1 is 1, M2 must be 0, 1, or 2 (for 10, 11, 12)
        if (value.length === 2) {
            const M1 = parseInt(value[0], 10);
            const M2 = parseInt(value[1], 10);
            if (M1 === 1 && M2 > 2) {
                value = '12'; // Force to 12 if invalid (e.g., '13' becomes '12')
            } else if (M1 === 0 && M2 === 0) {
                value = '01'; // Force 00 to 01
            }
        }
        
        input.value = value;
        // Call main validation after any change
        validateExpiryDate(input, expiryYearInput); 
    }

    /**
     * Client-side function for strict validation of Year input based on digit constraints.
     * Y1: 2-4, Y2: 0-9
     * @param {HTMLInputElement} input - The year input element.
     */
    function validateYearInput(input) {
        let value = input.value.replace(/\D/g, '').substring(0, 2);

        // Y1 Check: Restrict Y1 to 2, 3, or 4
        if (value.length >= 1) {
            const Y1 = parseInt(value[0], 10);
            if (Y1 < 2 || Y1 > 4) {
                value = '2' + value.substring(1); // Force Y1 to 2
            }
        }
        // Y2 is 0-9, which is covered by the \D/g filter.

        input.value = value;
        // Call main validation after any change
        validateExpiryDate(expiryMonthInput, input); 
    }
    
    /**
     * Client-side function for strict validation based on combined MM/YY values
     * and ensuring the date is not expired.
     * @param {HTMLInputElement} monthEl - The month input element.
     * @param {HTMLInputElement} yearEl - The year input element.
     * @returns {boolean} True if valid, false if invalid.
     */
    function validateExpiryDate(monthEl, yearEl) {
        const monthErrorSpan = document.getElementById('month-error');
        const yearErrorSpan = document.getElementById('year-error');
        
        monthErrorSpan.textContent = ''; // Clear previous error
        yearErrorSpan.textContent = ''; // Clear previous error

        const monthStr = monthEl.value;
        const yearStr = yearEl.value;
        
        let isValid = true;

        // 1. Check if both fields are completely filled
        if (monthStr.length !== 2 || yearStr.length !== 2) {
            return true; // Pass if incomplete, let 'required' attribute handle emptiness
        }

        const month = parseInt(monthStr, 10);
        const year_yy = parseInt(yearStr, 10);

        const currentDate = new Date();
        const currentYear = currentDate.getFullYear() % 100; 
        const currentMonth = currentDate.getMonth() + 1; // 1-12

        
        // 2. Basic Month Validity Check (01-12)
        if (month < 1 || month > 12) {
            monthErrorSpan.textContent = "Month must be 01-12.";
            isValid = false;
        }

        // 3. Digit-based restriction re-check for year (Y1: 2-4)
        const Y1 = parseInt(yearStr[0], 10);
        if (Y1 < 2 || Y1 > 4) {
            yearErrorSpan.textContent = "Year must be between 20 and 49 (Y1=2-4).";
            isValid = false;
        }

        // 4. Expiration Check (only if basic checks passed)
        if (isValid) {
            if (year_yy < currentYear) {
                 yearErrorSpan.textContent = "Card year is in the past.";
                 isValid = false;
            } else if (year_yy > 49) {
                yearErrorSpan.textContent = "Year exceeds maximum (49).";
                isValid = false;
            } else if (year_yy === currentYear && month < currentMonth) {
                monthErrorSpan.textContent = "Card has already expired.";
                 isValid = false;
            }
        }
        
        return isValid;
    }


    document.addEventListener('DOMContentLoaded', () => {
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        const detailsMap = {
            'card': document.getElementById('card-details'),
            'upi': document.getElementById('upi-details'),
            'cod': document.getElementById('cod-details')
        };
        
        const addressInputs = document.querySelectorAll('#new-address-form input');
        const cardInputs = document.querySelectorAll('#new-card-form input');
        const upiInputs = document.querySelectorAll('#new-upi-form input');

        const checkoutForm = document.querySelector('.payment-form form');
        const changeCardFlag = document.getElementById('change-card-flag');


        // --- Add form submission listener for hard client-side block ---
        checkoutForm.addEventListener('submit', (e) => {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;

            // Only validate card details if 'card' is selected AND a new card is being entered
            if (selectedMethod === 'card' && changeCardFlag.value === 'yes') {
                // Manually run the strict validation
                const isExpiryValid = validateExpiryDate(expiryMonthInput, expiryYearInput);

                if (!isExpiryValid) {
                    e.preventDefault();
                    // Scroll to the error input for better visibility
                    (document.getElementById('month-error').textContent || document.getElementById('year-error').textContent)
                        ? expiryMonthInput.scrollIntoView({ behavior: 'smooth', block: 'center' })
                        : null;
                }
            }
        });
        // -------------------------------------------------------------------


        function setRequired(inputs, isRequired) {
            inputs.forEach(input => {
                // Ensure only 'required' status is toggled, not physical attributes
                if (input.id === 'address_line1' || input.id === 'address_line2' || input.id === 'city' || input.id === 'state' || input.id === 'country' || input.id === 'postal_code' || input.id === 'card_name' || input.id === 'card_number' || input.id === 'expiry_month' || input.id === 'expiry_year' || input.id === 'cvv' || input.id === 'upi_id') {
                    // Only apply 'required' status
                }
                
                if (isRequired) {
                    input.setAttribute('required', '');
                } else {
                    input.removeAttribute('required');
                }
            });
        }

        // Handles visibility and 'required' attributes for all payment methods
        function updateFormState() {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
            
            // Hide all details sections
            for (const method in detailsMap) {
                detailsMap[method].style.display = 'none';
            }
            
            // Show the selected details section and manage 'required' attributes
            detailsMap[selectedMethod].style.display = 'block';

            // Reset required for all inputs
            setRequired(cardInputs, false);
            setRequired(upiInputs, false);

            if (selectedMethod === 'card') {
                const changeCardFlag = document.getElementById('change-card-flag');
                if (changeCardFlag.value === 'yes') {
                    setRequired(cardInputs, true);
                }
            } else if (selectedMethod === 'upi') {
                const changeUpiFlag = document.getElementById('change-upi-flag');
                if (changeUpiFlag.value === 'yes') {
                    setRequired(upiInputs, true);
                }
            }
            // For COD, no inputs are required
        }
        
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', updateFormState);
        });

        // Address Toggling
        const changeAddressBtn = document.getElementById('change-address-btn');
        const cancelChangeBtn = document.getElementById('cancel-change-btn');
        const existingAddressContainer = document.getElementById('existing-address-container');
        const newAddressForm = document.getElementById('new-address-form');
        const changeAddressFlag = document.getElementById('change-address-flag');

        // Initial setup for address required state based on flag
        if (changeAddressFlag.value === 'yes') {
            // Note: address_line2 is optional and not set to required here
            setRequired(document.querySelectorAll('#address_line1, #city, #state, #postal_code, #country'), true);
        } else {
             setRequired(addressInputs, false);
        }

        if (changeAddressBtn) {
            changeAddressBtn.addEventListener('click', () => {
                existingAddressContainer.style.display = 'none';
                newAddressForm.style.display = 'block';
                // Only make the non-optional fields required
                setRequired(document.querySelectorAll('#address_line1, #city, #state, #postal_code, #country'), true);
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
        
        if (changeCardBtn) {
            changeCardBtn.addEventListener('click', () => {
                existingCardContainer.style.display = 'none';
                newCardForm.style.display = 'block';
                setRequired(cardInputs, true);
                changeCardFlag.value = 'yes';
                // Also clear expiry error message when changing card
                document.getElementById('month-error').textContent = '';
                document.getElementById('year-error').textContent = '';
            });
        }
        if (cancelCardBtn) {
            cancelCardBtn.addEventListener('click', () => {
                existingCardContainer.style.display = 'block';
                newCardForm.style.display = 'none';
                setRequired(cardInputs, false);
                changeCardFlag.value = 'no';
                // Also clear expiry error message when cancelling
                document.getElementById('month-error').textContent = '';
                document.getElementById('year-error').textContent = '';
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
        
        // Initial setup for required attributes and visibility
        updateFormState();
    });
</script>

<style>
/* Add a new style for the error message */
.error-message {
    background-color: #f44336;
    color: white;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 20px;
    font-size: 1.1em;
}
.error-message p {
    margin: 0;
    padding: 0;
}
.form-group-flex {
    display: flex;
    gap: 15px; /* Spacing between month and year fields */
    margin-bottom: 15px;
}
.form-group-flex .form-group {
    flex: 1; /* Make both fields take equal width */
}
</style>

</body>
</html>
