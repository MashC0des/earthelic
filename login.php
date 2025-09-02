<?php
session_start();

include "db_connect.php"; // DB connection + session

$errors = [];
$success = '';

// Handle Registration
if (isset($_POST['register'])) {
    $fullName = $_POST['fullName'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // Validate fields and store errors
    if (empty($fullName)) {
        $errors['fullName'] = "Full name is required!";
    }
    if (empty($email)) {
        $errors['email'] = "Email is required!";
    }
    if (empty($password)) {
        $errors['password'] = "Password is required!";
    }
    if (empty($confirmPassword)) {
        $errors['confirmPassword'] = "Confirm password is required!";
    }
    if ($password !== $confirmPassword) {
        $errors['confirmPassword'] = "Passwords do not match!";
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO Users (full_name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullName, $email, $phone, $hashedPassword);

        if ($stmt->execute()) {
            $success = "Registration successful. You can now log in!";
        } else {
            // Check for duplicate email error
            if ($conn->errno == 1062) {
                $errors['email'] = "Error: Email already exists.";
            } else {
                $errors['general'] = "An unexpected database error occurred.";
            }
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

            header("Location: landing.html"); // redirect to homepage
            exit();
        } else {
            $errors['loginPassword'] = "Invalid password!";
        }
    } else {
        $errors['loginEmail'] = "No user found with this email!";
    }
    $stmt->close();
}

$showRegisterForm = isset($_POST['register']);
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
        <a href="landing.html"><img src="imgs/earthelic logo file png.png" alt="Earthelic Logo" id="logo1"></a>
        <nav class="nav1">
            <div class="icons1">
                <ul class="nav-links">
                    <li><a href="landing.html">Home</a></li>
                    <li><a href="metal.php">Metal</a></li>
                    <li><a href="ceramic.php">Ceramic</a></li>
                    <li><a href="cart.php">Cart</a></li>
                    <li><a href="about.php">About us</a></li>
                    
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
            <?php if (isset($errors['general'])) echo "<p class='error-message' style='text-align:center;'>{$errors['general']}</p>"; ?>
            <?php if ($success) echo "<p class='success-message' style='text-align:center;'>$success</p>"; ?>

            <!-- Login Form -->
            <div class="form-wrapper <?= $showRegisterForm ? '' : 'active' ?>" id="login-form">
                <h2>Login</h2>
                <!-- Login Form -->
                <form method="POST" action="">
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-envelope"></i></div>
                        <input type="email" name="loginEmail" placeholder="Email" required class="<?= isset($errors['loginEmail']) ? 'invalid' : '' ?>">
                        <span class="error-message"><?= $errors['loginEmail'] ?? '' ?></span>
                    </div>
                    <div class="input-group" id="loginPasswordGroup">
                        <div class="icon-wrapper"><i class="fas fa-lock"></i></div>
                        <div class="password-field">
                            <input type="password" name="loginPassword" id="loginPassword" placeholder="Password" required class="<?= isset($errors['loginPassword']) ? 'invalid' : '' ?>">
                            <button type="button" class="toggle-password" id="toggleLoginPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <span class="error-message"><?= $errors['loginPassword'] ?? '' ?></span>
                    </div>
                    <div class="forgot-password"><a href="forgot-password.php">Forgot Password?</a></div>
                    <button type="submit" name="login" class="btn">Login</button>
                </form>

                <p class="toggle-text">Don't have an account? <a href="#" id="show-register">Register</a></p>
            </div>
            
            <!-- Registration Form -->
            <div class="form-wrapper <?= $showRegisterForm ? 'active' : '' ?>" id="register-form">
                <h2>Register</h2>
                <!-- Registration Form -->
                <form method="POST" action="">
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-user"></i></div>
                        <input type="text" name="fullName" id="fullName" placeholder="Full Name" required class="<?= isset($errors['fullName']) ? 'invalid' : '' ?>">
                        <span class="error-message"><?= $errors['fullName'] ?? '' ?></span>
                    </div>
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-envelope"></i></div>
                        <input type="email" name="email" id="email" placeholder="Email" required class="<?= isset($errors['email']) ? 'invalid' : '' ?>">
                        <span class="error-message"><?= $errors['email'] ?? '' ?></span>
                    </div>
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-phone"></i></div>
                        <input type="text" name="phone" id="phone" placeholder="Phone (optional)">
                    </div>
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-lock"></i></div>
                        <div class="password-field">
                            <input type="password" name="password" id="password" placeholder="Password" required class="<?= isset($errors['password']) ? 'invalid' : '' ?>">
                            <button type="button" class="toggle-password" id="togglePassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <span class="error-message"><?= $errors['password'] ?? '' ?></span>
                    </div>
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-lock"></i></div>
                        <div class="password-field">
                            <input type="password" name="confirmPassword" id="confirmPassword" placeholder="Confirm Password" required class="<?= isset($errors['confirmPassword']) ? 'invalid' : '' ?>">
                            <button type="button" class="toggle-password" id="toggleConfirmPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <span class="error-message"><?= $errors['confirmPassword'] ?? '' ?></span>
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
