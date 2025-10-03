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
$_SESSION['cart'] = [];
$stmt = $conn->prepare("SELECT ci.product_id, ci.quantity, p.product_name, p.price, p.image_url 
                        FROM Cart_Items ci 
                        JOIN Products p ON ci.product_id = p.product_id 
                        WHERE ci.cart_id = ?");
$stmt->bind_param("i", $cart_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $_SESSION['cart'][] = [
        'id'       => $row['product_id'],
        'name'     => $row['product_name'],
        'price'    => $row['price'],
        'image'    => $row['image_url'],
        'quantity' => $row['quantity'],
    ];
}
$stmt->close();


/* -------------------------------------------
   Handle Add to Cart from product page
------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id    = isset($_POST['product_id']) ? to_int($_POST['product_id']) : 0;
    $quantity      = isset($_POST['quantity']) ? max(1, to_int($_POST['quantity'])) : 1;

    if ($product_id > 0) {
        // Check if item is already in the cart_items table
        $stmt = $conn->prepare("SELECT quantity FROM Cart_Items WHERE cart_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $cart_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Item exists, update quantity
            $row = $result->fetch_assoc();
            $new_quantity = $row['quantity'] + $quantity;
            $stmt->close();
            
            $stmt = $conn->prepare("UPDATE Cart_Items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
            $stmt->bind_param("iii", $new_quantity, $cart_id, $product_id);
            $stmt->execute();
        } else {
            // Item does not exist, insert new item
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
   Handle Update Quantities
------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
    $qtyInput = $_POST['qty'] ?? null;

    if (is_array($qtyInput)) {
        foreach ($qtyInput as $product_id => $quantity) {
            $product_id = to_int($product_id);
            $quantity = max(1, to_int($quantity));

            $stmt = $conn->prepare("UPDATE Cart_Items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
            $stmt->bind_param("iii", $quantity, $cart_id, $product_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
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
   Process Buy Now (redirects to checkout)
------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_now'])) {
    header("Location: checkout.php");
    exit();
}

/* -------------------------------------------
   Compute grand total from session data
------------------------------------------- */
$grand_total = 0.0;
foreach ($_SESSION['cart'] as $ci) {
    $price = isset($ci['price']) ? to_float($ci['price']) : 0.0;
    $qty   = isset($ci['quantity']) ? max(1, to_int($ci['quantity'])) : 1;
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
<div class="glass-container">
       <div class="back-btn">
        <button class="btn-back" onclick="window.history.back()">⬅ Back</button>
    </div>
     <h1>My Cart</h1>
    <?php if (!empty($_SESSION['cart'])): ?>
        <form method="post" action="">
            <?php foreach ($_SESSION['cart'] as $cart_item):
                $price    = isset($cart_item['price']) ? to_float($cart_item['price']) : 0.0;
                $quantity = isset($cart_item['quantity']) ? max(1, to_int($cart_item['quantity'])) : 1;
                $total    = $price * $quantity;
                $image_url = isset($cart_item['image']) && $cart_item['image'] !== '' ? $cart_item['image'] : 'imgs/default-product.png';
            ?>
                <div class="products">
                    <div class="metprod">
                        <img id="mp" src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars((string)$cart_item['name']); ?>">
                        <div class="wrapper3">
                            <p id="metname"><?php echo htmlspecialchars((string)$cart_item['name']); ?></p>
                            <p id="metdesc">Price: ₹<span class="price-value" data-price="<?php echo $price; ?>"><?php echo number_format($price, 2); ?></span> each</p>
                            <div class="quantity-controls">
                                <label for="qty_<?php echo htmlspecialchars((string)$cart_item['id']); ?>">Quantity:</label>
                                <input
                                    type="number"
                                    class="quantity-input"
                                    id="qty_<?php echo htmlspecialchars((string)$cart_item['id']); ?>"
                                    name="qty[<?php echo htmlspecialchars((string)$cart_item['id']); ?>]"
                                    value="<?php echo $quantity; ?>"
                                    min="1"
                                    inputmode="numeric"
                                >
                            </div>
                            <p id="metdesc">Total: ₹<span class="total-price"><?php echo number_format($total, 2); ?></span></p>
                            <span id="sp1">
                                <a
                                    href="cart.php?remove=<?php echo urlencode((string)$cart_item['id']); ?>"
                                    class="btn1"
                                    onclick="return confirm('Remove item from cart?')"
                                >Remove</a>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="cart-summary">
                <div class="grand-total">
                    <h3>Grand Total: ₹<span id="grand-total"><?php echo number_format($grand_total, 2); ?></span></h3>
                </div>

                <div class="cart-actions">
                    <button type="submit" name="buy_now" class="btn1 buy-all-btn">Proceed to Checkout</button>
                </div>
            </div>
        </form>
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
    document.addEventListener('DOMContentLoaded', () => {
        const quantityInputs = document.querySelectorAll('.quantity-input');
        const grandTotalElement = document.getElementById('grand-total');

        function updateCartTotals() {
            let newGrandTotal = 0;
            quantityInputs.forEach(input => {
                const quantity = parseInt(input.value, 10);
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

        quantityInputs.forEach(input => {
            input.addEventListener('input', updateCartTotals);
        });

        // The old, redundant form submission logic has been removed.
    });
</script>

</body>
</html>
