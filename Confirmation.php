<?php
declare(strict_types=1);
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Confirmed - Earthelic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/metal.css">
    <style>
        .confirmation-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            max-width: 600px;
            margin: 80px auto;
            color: #fff;
        }
        .confirmation-container h1 {
            color: #904A2D;
            margin-bottom: 20px;
            font-size: 3rem;
        }
        .confirmation-container p {
            font-size: 2rem;
            margin-bottom: 30px;
        }
        .confirmation-icon {
            font-size: 5rem;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .home-btn {
            background-color: #904A2D !important;
            border-color: #904A2D !important;
            font-size: 2rem;
        }
        .home-btn:hover {
            background-color: #7a3c24 !important;
            box-shadow: 0 0 25px #904A2D !important;
        }
        .confirmation-container h2 {
            color: #904A2D;
            margin-bottom: 10px;
            font-size: 4rem;
        }
    </style>
</head>
<body>
<header class="head1">
    <a href="index.html"><img src="imgs/earthelic logo file png.png" id="logo1" alt="Earthelic Logo"></a>
   
</header>

<main>
    
    <div class="confirmation-container">
         <h2>Order Confirmed</h2>
        <div class="confirmation-icon">&#10003;</div>
        <h1>Thank You!</h1>
        <p>Your order has been placed successfully.</p>
        <p>A confirmation email has been sent to your registered email address.</p>
        <a href="landing.html" class="btn1 home-btn">Continue Shopping</a>
    </div>
</main>

<footer class="foot1">
    <p>&copy; 2024 Earthelic</p>
</footer>
</body>
</html>
