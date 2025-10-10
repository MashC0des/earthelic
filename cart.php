<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . "/db_connect.php"; // Assumes db_connect.php exists with a valid mysqli connection ($conn)

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
function sanitize_string(string $val): string {
    return trim($val);
}
function to_int($val): int {
    return (int)$val;
}
function to_float($val): float {
    return (float)$val;
}
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}


/* -------------------------------------------
   Ensure cart exists in DB and session
------------------------------------------- */
$cart_id = null;
$stmt = $conn->prepare("SELECT cart_id FROM Cart WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User already has a cart, get the cart_id
    $row = $result->fetch_assoc();
    $cart_id = $row['cart_id'];
} else {
    // User does not have a cart, create a new one
    $stmt->close();
    $stmt = $conn->prepare("INSERT INTO Cart (user_id) VALUES (?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_id = $conn->insert_id;
}
$stmt->close();

// Fetch cart items from the database and populate the session
// *** FIX: Now fetching stock_quantity directly in the main query for reliability ***
$_SESSION['cart'] = [];
$stmt = $conn->prepare("SELECT ci.product_id, ci.quantity, p.product_name, p.price, p.image_url, p.stock_quantity 
                        FROM Cart_Items ci 
                        JOIN Products p ON ci.product_id = p.product_id 
                        WHERE ci.cart_id = ?");
$stmt->bind_param("i", $cart_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Use stock directly from the fetched row
    $stock = to_int($row['stock_quantity'] ?? 0); 
    
    // Cap the cart quantity to the available stock
    // ALLOW 0: Use max(0, ...) to ensure quantity is not negative, but allow 0
    $current_quantity = max(0, to_int($row['quantity'])); 
    
    if ($current_quantity > $stock) {
        // When stock is 0, cap to 0 so the item is correctly removed on next page load/update
        $current_quantity = $stock; // Cap to available stock (which can be 0)
        
        // We will update the DB immediately after this session population to correct any over-limit quantities
        if ($current_quantity === 0) {
             // If quantity is now 0, delete the item from DB
            $stmt_update = $conn->prepare("DELETE FROM Cart_Items WHERE cart_id = ? AND product_id = ?");
            $stmt_update->bind_param("ii", $cart_id, $row['product_id']);
            $stmt_update->execute();
        } else {
             // If quantity > 0, update the capped quantity
            $stmt_update = $conn->prepare("UPDATE Cart_Items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
            $stmt_update->bind_param("iii", $current_quantity, $cart_id, $row['product_id']);
            $stmt_update->execute();
        }
        $stmt_update->close();
    }
    
    // Skip adding item to session if its quantity was capped to 0
    if ($current_quantity === 0) {
        continue;
    }

    $_SESSION['cart'][] = [
        'id'       => $row['product_id'],
        'name'     => $row['product_name'],
        'price'    => $row['price'],
        'image'    => $row['image_url'],
        'quantity' => $current_quantity,
        'stock'    => $stock, // Add stock information to the session for HTML use
    ];
}
$stmt->close();

/* -------------------------------------------
   Handle Add to Cart from product page (Ensure stock check here too)
   (Minimum quantity to add remains 1)
------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id    = isset($_POST['product_id']) ? to_int($_POST['product_id']) : 0;
    // Keep min 1 for adding to cart initially
    $quantity      = isset($_POST['quantity']) ? max(1, to_int($_POST['quantity'])) : 1; 

    if ($product_id > 0) {
        // Fetch current stock
        $stmt_stock = $conn->prepare("SELECT product_name, stock_quantity FROM Products WHERE product_id = ?");
        $stmt_stock->bind_param("i", $product_id);
        $stmt_stock->execute();
        $product_data = $stmt_stock->get_result()->fetch_assoc();
        $stmt_stock->close();
        
        $max_stock = (int)($product_data['stock_quantity'] ?? 0);
        $product_name = h($product_data['product_name'] ?? 'Item');
        
        // FIX 1: Cap the requested quantity directly using max_stock. If max_stock is 0, quantity becomes 0.
        $quantity = min($quantity, $max_stock); 

        if ($quantity === 0) {
             // If trying to add to cart when stock is 0, stop and optionally set error
             $_SESSION['cart_error'] = "The product '{$product_name}' is currently out of stock and could not be added to your cart.";
             header("Location: cart.php");
             exit();
        }

        // Check if item is already in the cart_items table
        $stmt = $conn->prepare("SELECT quantity FROM Cart_Items WHERE cart_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $cart_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Item exists, calculate new quantity
            $row = $result->fetch_assoc();
            $new_quantity = $row['quantity'] + $quantity;
            // FIX 2: Final check on cumulative quantity caps to max_stock.
            $new_quantity = min($new_quantity, $max_stock); 
            $stmt->close();
            
            // If the item was already in the cart but the cumulative quantity is 0 (due to max_stock being 0 or very low)
            if ($new_quantity === 0) {
                $stmt = $conn->prepare("DELETE FROM Cart_Items WHERE cart_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $cart_id, $product_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("UPDATE Cart_Items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
                $stmt->bind_param("iii", $new_quantity, $cart_id, $product_id);
                $stmt->execute();
            }

        } else {
            // Item does not exist, insert new item
            // $quantity must be > 0 at this point due to the check above
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO Cart_Items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $cart_id, $product_id, $quantity);
            $stmt->execute();
        }
        $stmt->close();

        // Redirect to cart page to refresh and prevent form resubmission
        header("Location: cart.php");
        exit();
    }
}

/* -------------------------------------------
   Process Buy Now (redirects to checkout)
   This block will now be reached because the checkout button is in its own form, 
   and it is placed BEFORE the quantity update block to ensure priority.
------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_now'])) {
    // Before redirecting, check if the cart is empty
    if (empty($_SESSION['cart'])) {
         $_SESSION['cart_error'] = "Cannot proceed to checkout, your cart is empty.";
         header("Location: cart.php");
         exit();
    }
    // No need to update quantities here, as the user is proceeding to the next page.
    header("Location: checkout.php");
    exit();
}

/* -------------------------------------------
   Handle Update Quantities (Allows setting quantity to 0 to remove item)
   This block now handles both explicit form submission via Enter key.
   It must ONLY run if 'update_qty' is present and 'buy_now' is NOT present.
------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty']) && !isset($_POST['buy_now'])) { // <-- ADDED !isset($_POST['buy_now'])
    $qtyInput = $_POST['qty'] ?? null;
    $stock_error_message = ''; // Use this to capture the first error encountered.

    if (is_array($qtyInput)) {
        foreach ($qtyInput as $product_id => $requested_quantity) {
            $product_id = to_int($product_id);
            // ALLOW 0: Ensure quantity is non-negative, but allow 0 for removal
            $requested_quantity = max(0, to_int($requested_quantity)); 

            // 1. Fetch current stock quantity and name
            $stmt_stock = $conn->prepare("SELECT product_name, stock_quantity FROM Products WHERE product_id = ?");
            $stmt_stock->bind_param("i", $product_id);
            $stmt_stock->execute();
            $result_stock = $stmt_stock->get_result();
            $product_data = $result_stock->fetch_assoc();
            $stmt_stock->close();
            
            $max_stock = (int)($product_data['stock_quantity'] ?? 0);
            $product_name = h($product_data['product_name'] ?? 'Item');
            
            // 2. Determine the quantity to set, respecting stock limit
            $final_quantity = $requested_quantity;
            $needs_db_update = false;

            // CHECK 1: If quantity is 0, delete the item
            if ($final_quantity === 0) {
                $stmt = $conn->prepare("DELETE FROM Cart_Items WHERE cart_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $cart_id, $product_id);
                $stmt->execute();
                $stmt->close();
                continue; // Move to the next item
            }
            
            // CHECK 2: If quantity is over stock (and > 0)
            if ($final_quantity > $max_stock) {
                // FIX 3: Cap to stock quantity, even if stock is 0. 
                $final_quantity = $max_stock; 
                $needs_db_update = true;
                
                // Only set the error message once
                if (empty($stock_error_message)) {
                    if ($max_stock > 0) {
                         $stock_error_message = "The quantity for '{$product_name}' was limited to {$max_stock}, as that is the available stock.";
                    } else {
                         // max_stock is 0, so final_quantity is 0
                         $stock_error_message = "The quantity for '{$product_name}' was set to 0 and will be removed, as it is out of stock.";
                    }
                }
            } else {
                // If it was not capped, but the request was made, we still need to update the DB
                $needs_db_update = true;
            }

            // 3. Perform DB action based on final quantity
            if ($needs_db_update) {
                if ($final_quantity > 0) {
                    $stmt = $conn->prepare("UPDATE Cart_Items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
                    $stmt->bind_param("iii", $final_quantity, $cart_id, $product_id);
                    $stmt->execute();
                    $stmt->close();
                } else if ($final_quantity === 0) {
                    // Item was capped to 0 (because max_stock was 0)
                    $stmt = $conn->prepare("DELETE FROM Cart_Items WHERE cart_id = ? AND product_id = ?");
                    $stmt->bind_param("ii", $cart_id, $product_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
    
    // Set the accumulated error message only if an error occurred
    if (!empty($stock_error_message)) {
        $_SESSION['cart_error'] = $stock_error_message;
    }
    
    // Redirect to cart page to refresh and prevent form resubmission
    header("Location: cart.php");
    exit();
}

/* -------------------------------------------
   Handle Remove Item
------------------------------------------- */
if (isset($_GET['remove'])) {
    $product_id = to_int($_GET['remove']);

    if ($product_id > 0) {
        $stmt = $conn->prepare("DELETE FROM Cart_Items WHERE cart_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $cart_id, $product_id);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: cart.php");
    exit();
}

/* -------------------------------------------
   Compute grand total from session data
------------------------------------------- */
$grand_total = 0.0;
foreach ($_SESSION['cart'] as $ci) {
    $price = isset($ci['price']) ? to_float($ci['price']) : 0.0;
    // ALLOW 0: Use max(0, ...) for total calculation
    $qty   = isset($ci['quantity']) ? max(0, to_int($ci['quantity'])) : 0; 
    $grand_total += $price * $qty;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Cart - Earthelic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/cart.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/metal.css">
    <style>
        /* CSS for the temporary warning message */
        .stock-warning {
            color: #dc3545; /* Red color */
            font-size: 0.9rem;
            margin-top: 5px;
            margin-bottom: 5px;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        .stock-warning.show {
            opacity: 1;
        }
        .error-message {
            margin-top: 10px;
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 8px;
            background-color: #fce7e7; /* Light red background */
            border: 1px solid #dc3545;
            color: #dc3545;
            font-weight: 500;
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
                                <span class="nav-profile-name"><?php echo (h($_SESSION['full_name'] ?? 'Profile')); ?></span>
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
<div class="glass-container">
       <div class="back-btn">
        <button class="btn-back" onclick="window.history.back()">⬅ Back</button>
    </div>
     <h1>My Cart</h1>
    <?php 
        // Display and clear any stock error message from server-side checks
        if (isset($_SESSION['cart_error'])): ?>
        <div class="error-message">
            <p><?php echo h($_SESSION['cart_error']); ?></p>
        </div>
        <?php unset($_SESSION['cart_error']); 
    endif; ?>

    <?php if (!empty($_SESSION['cart'])): ?>
        <!-- 
            FORM 1: Handles Quantity Updates and Item Removals (via input and enter key).
            This form is now separate from the Checkout button.
        -->
        <form id="cart-update-form" method="post" action="">
            <input type="hidden" name="update_qty" value="manual">
            
            <?php foreach ($_SESSION['cart'] as $cart_item):
                $id        = to_int($cart_item['id']);
                $price     = to_float($cart_item['price'] ?? 0.0);
                $quantity  = to_int($cart_item['quantity'] ?? 0); 
                $stock     = to_int($cart_item['stock'] ?? 1); 
                $total     = $price * $quantity;
                $image_url = h($cart_item['image'] ?? 'imgs/default-product.png');
            ?>
                <div class="products" data-product-id="<?php echo $id; ?>">
                    <div class="metprod">
                        <img id="mp" src="<?php echo $image_url; ?>" alt="<?php echo h($cart_item['name']); ?>">
                        <div class="wrapper3">
                            <p id="metname"><?php echo h($cart_item['name']); ?></p>
                            <p id="metdesc">Price: ₹<span class="price-value" data-price="<?php echo $price; ?>"><?php echo number_format($price, 2); ?></span> each</p>
                            <div class="quantity-controls">
                                <label for="qty_<?php echo $id; ?>">Quantity:</label>
                                <input
                                    type="number"
                                    class="quantity-input"
                                    id="qty_<?php echo $id; ?>"
                                    name="qty[<?php echo $id; ?>]"
                                    value="<?php echo $quantity; ?>"
                                    min="0"
                                    max="<?php echo $stock; ?>"
                                    data-max-stock="<?php echo $stock; ?>"
                                    onchange="checkStockLimit(this)" 
                                    inputmode="numeric"
                                >
                            </div>
                            <!-- Client-side warning message container -->
                            <div id="stockWarningMessage_<?php echo $id; ?>" class="stock-warning"></div>

                            <p id="metdesc">Total: ₹<span class="total-price"><?php echo number_format($total, 2); ?></span></p>
                            <span id="sp1">
                                <a
                                    href="cart.php?remove=<?php echo $id; ?>"
                                    class="btn1"
                                    onclick="return confirm('Remove item from cart?')"
                                >Remove</a>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </form>

        <div class="cart-summary">
            <div class="grand-total">
                <h3>Grand Total: ₹<span id="grand-total"><?php echo number_format($grand_total, 2); ?></span></h3>
            </div>

            <div class="cart-actions">
                <!-- 
                    FORM 2: Only handles the Buy Now/Checkout action.
                -->
                <form id="checkout-form" method="post" action="">
                     <button type="submit" name="buy_now" class="btn1 buy-all-btn">Proceed to Checkout</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-cart">
            <p>Your cart is empty.</p>
            <a href="metal.php" class="btn1">Continue Shopping</a>
        </div>
<?php endif; ?>
</div>
<footer class="foot1">
    <div class="social-icons">
        <a href="mailto:earthelicarthouse@gmail.com"><i class="fa-solid fa-envelope"></i></a>
        <a href="tel:999999999"><i class="fa-solid fa-phone"></i></a>
        <a href="https://www.instagram.com/earthelic_homedecor/" target="_blank"><i class="fa-brands fa-square-instagram"></i></a>
        <a href="#"><i class="fa-brands fa-facebook"></i></a>
        <a href="#"><i class="fa-solid fa-location-dot"></i></a>
    </div>
    <p>&copy; 2024 Earthelic.com</p>
</footer>
<script src="script.js"></script>
<script>
    // Debounce function to limit how often the warning fades out
    let warningTimeout = {}; // Use an object for multiple timeouts (one per product)
    
    /**
     * Client-side validation to check and enforce the stock limit for a single input.
     * Updates local totals, displays a temporary warning message.
     * The form submission (DB update) now relies on the user pressing 'Enter' in the input.
     * @param {HTMLInputElement} input The quantity input element.
     */
    function checkStockLimit(input) {
        const productId = input.closest('.products').getAttribute('data-product-id');
        let value = parseInt(input.value, 10);
        const maxStock = parseInt(input.getAttribute('data-max-stock'), 10);
        const warningElement = document.getElementById(`stockWarningMessage_${productId}`);
        const updateCartTotals = document.cartTotalsUpdate; 

        // Clear any existing client-side warning timeout for this specific product
        clearTimeout(warningTimeout[productId]);

        // --- Input Validation and Clamping ---
        if (isNaN(value) || value < 0) {
            value = 0; // Ensure non-negative and is a number
        }

        if (value > maxStock) {
            // 1. Enforce the limit: set the input value back to maxStock
            input.value = maxStock;
            value = maxStock; // Use clamped value for submission
            
            // 2. Show the warning
            warningElement.textContent = `Limit: only ${maxStock} items available.`;
            warningElement.classList.add('show');

            // 3. Set a timeout to fade the warning out
            warningTimeout[productId] = setTimeout(() => {
                warningElement.classList.remove('show');
            }, 3000); // Warning visible for 3 seconds
        } else {
            // Update input value with sanitized (non-negative) value
            input.value = value;
            // Hide warning if input is valid (including 0)
            warningElement.classList.remove('show');
        }

        // Always update the totals immediately for a smooth client-side experience
        if (updateCartTotals) {
            updateCartTotals();
        }
    }


    document.addEventListener('DOMContentLoaded', () => {
        const quantityInputs = document.querySelectorAll('.quantity-input');
        const grandTotalElement = document.getElementById('grand-total');

        /**
         * Calculates and updates the total price for each item and the grand total.
         */
        function updateCartTotals() {
            let newGrandTotal = 0;
            quantityInputs.forEach(input => {
                // Read quantity, treat NaN as 0
                const quantity = parseInt(input.value, 10) || 0; 
                const itemContainer = input.closest('.products');
                const priceElement = itemContainer.querySelector('.price-value');
                const totalPriceElement = itemContainer.querySelector('.total-price');
                
                const price = parseFloat(priceElement.dataset.price);
                const itemTotal = price * quantity;
                
                totalPriceElement.textContent = itemTotal.toFixed(2);
                newGrandTotal += itemTotal;
            });
            
            grandTotalElement.textContent = newGrandTotal.toFixed(2);
        }

        // Attach the function globally so the onchange attribute can access it
        document.cartTotalsUpdate = updateCartTotals;

        // Attach the event listener for the initial check
        quantityInputs.forEach(input => {
            // Check initial state (useful if quantities were corrected by server on load)
            checkStockLimit(input); 
        });
        
        // Ensure totals are calculated on load
        updateCartTotals();
        
        // --- Add Enter Key Submission for Quantity Updates ---
        const updateForm = document.getElementById('cart-update-form');
        quantityInputs.forEach(input => {
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    // Prevent default behavior (which might submit the checkout form if it's the only one)
                    event.preventDefault(); 
                    // Submit the specific quantity update form
                    updateForm.submit(); 
                }
            });
        });
    });
</script>

</body>
</html>
