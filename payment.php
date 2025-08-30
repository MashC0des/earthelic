<?php
session_start();

// Calculate grand total from cart
$grand_total = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cart_item) {
        $grand_total += $cart_item['price'] * $cart_item['quantity'];
    }
} else {
    header("Location: cart.php");
    exit();
}

// Razorpay expects amount in paisa (₹1 = 100 paise)
$amount_in_paisa = $grand_total * 100;

// Your Razorpay Test API Keys
$key_id = "rzp_test_123456789";  // replace with your Razorpay Key ID
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment - Earthelic</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; }
        .container { max-width: 500px; margin: 60px auto; background: #fff; padding: 25px; border-radius: 10px; text-align: center; }
        h2 { margin-bottom: 20px; }
        .summary { margin-bottom: 30px; }
        button { background: #28a745; color: #fff; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Confirm Payment</h2>
        <div class="summary">
            <p><strong>Order Amount:</strong> ₹<?= number_format($grand_total); ?></p>
        </div>
        <button id="payBtn">Pay with Razorpay</button>
    </div>

    <script>
    var options = {
        "key": "<?= $key_id ?>", // Razorpay Key ID
        "amount": "<?= $amount_in_paisa ?>", // Amount in paise
        "currency": "INR",
        "name": "Earthelic",
        "description": "Order Payment",
        "image": "imgs/earthelic logo file png.png", // your logo
        "handler": function (response){
            // After successful payment
            alert("Payment Successful! Payment ID: " + response.razorpay_payment_id);
            window.location.href = "success.php?payment_id=" + response.razorpay_payment_id;
        },
        "prefill": {
            "name": "<?= $_SESSION['user_name'] ?? 'Customer'; ?>",
            "email": "test@example.com",
            "contact": "9999999999"
        },
        "theme": {
            "color": "#3399cc"
        }
    };

    document.getElementById('payBtn').onclick = function(e){
        var rzp1 = new Razorpay(options);
        rzp1.open();
        e.preventDefault();
    }
    </script>
</body>
</html>
