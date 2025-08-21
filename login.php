<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";   // change if needed
$password = "";       // change if needed
$dbname = "earthelic";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Registration
if (isset($_POST['register'])) {
    $fullName = $_POST['fullName'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    if (empty($fullName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = "All required fields must be filled!";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match!";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO Users (full_name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullName, $email, $phone, $hashedPassword);

        if ($stmt->execute()) {
            $success = "Registration successful. You can now log in!";
        } else {
            $error = "Error: Email already exists or DB issue.";
        }
        $stmt->close();
    }
}

// Handle Login
if (isset($_POST['login'])) {
    $email = $_POST['loginEmail'];
    $password = $_POST['loginPassword'];

    $stmt = $conn->prepare("SELECT user_id, full_name, password_hash, role FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id, $full_name, $hashedPassword, $role);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;

            header("Location: landing.php"); // redirect to homepage
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "No user found with this email!";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login/Register - Earthelic</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header class="head1">
        <a href="landing.php"><img src="imgs/earthelic logo file png.png" alt="Earthelic Logo" id="logo1"></a>
        <nav class="nav1">
            <div class="icons1">
                <ul class="nav-links">
                    <li><a href="landing.php">Home</a></li>
                    <li><a href="metal.php">Metal</a></li>
                    <li><a href="ceramic.php">Ceramic</a></li>
                    <li><a href="cart.php">Cart</a></li>
                    <li><a href="about.php">About us</a></li>
                    <li><a href="login.php" class="active">Log In</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="logo-container">
            <img src="imgs/earthelic logo file png.png" alt="Earthelic Logo" class="logo" id="logo">
        </div>
        
        <div class="form-container">
            <!-- Messages -->
            <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
            <?php if (isset($success)) echo "<p style='color:green;'>$success</p>"; ?>

            <!-- Login Form -->
            <div class="form-wrapper active" id="login-form">
                <h2>Login</h2>
                <form method="POST" action="">
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-envelope"></i></div>
                        <input type="email" name="loginEmail" placeholder="Email" required>
                    </div>
                     <div class="input-group">
                        <div class="icon-wrapper">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="password-field">
                            <input type="password"  placeholder="Password" required>
                            <button type="button" class="toggle-password" id="toggleLoginPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <span class="error-message" id="loginPasswordError"></span>
                    </div>
                    <div class="forgot-password"><a href="forgot-password.php">Forgot Password?</a></div>
                    <button type="submit" name="login" class="btn">Login</button>
                </form>
                <p class="toggle-text">Don't have an account? <a href="#" id="show-register">Register</a></p>
            </div>
            
            <!-- Registration Form -->
            <div class="form-wrapper" id="register-form">
                <h2>Register</h2>
                <form method="POST" action="">
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-user"></i></div>
                        <input type="text" name="fullName" placeholder="Full Name" required>
                    </div>
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-envelope"></i></div>
                        <input type="email" name="email" placeholder="Email" required>
                    </div>
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-phone"></i></div>
                        <input type="text" name="phone" placeholder="Phone (optional)">
                    </div>
<div class="input-group">
                        <div class="icon-wrapper">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="password-field">
                            <input type="password"  placeholder="Password" required>
                            <button type="button" class="toggle-password" id="togglePassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <span class="error-message" id="passwordError"></span>
                    </div>
                    <div class="input-group">
                        <div class="icon-wrapper">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="password-field">
                            <input type="password"  placeholder="Confirm Password" required>
                            <button type="button" class="toggle-password" id="toggleConfirmPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <span class="error-message" id="confirmPasswordError"></span>
                    </div>
                    <button type="submit" name="register" class="btn">Register</button>
                </form>
                <p class="toggle-text">Already have an account? <a href="#" id="show-login">Login</a></p>
            </div>
        </div>
    </div>
</body>
<script src="login.js"></script>
</html>
