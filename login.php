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
    // Added server-side check for name length
    if (strlen($fullName) > 20) {
        $errors['fullName'] = "Name must be 20 characters or less.";
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

    // Server-side password complexity check to match login.js
    if (!empty($password)) {
        if (strlen($password) < 8 || strlen($password) > 12) {
            $errors['password'] = "Password must be 8-12 characters long.";
        } else if (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = "Must contain at least one uppercase letter.";
        } else if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors['password'] = "Must contain at least one special character.";
        }
    }
    
    // Server-side phone number validation
    if (!empty($phone) && !preg_match('/^\d{10}$/', $phone)) {
        $errors['phone'] = "Please enter a valid 10-digit phone number.";
    }

    // Check for existing email *before* attempting to insert
    if (empty($errors)) {
        $stmt_check = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $errors['email'] = "This email is already registered.";
              }
        $stmt_check->close();
    }
    
    // Now perform the insert if there are no errors
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO Users (full_name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullName, $email, $phone, $hashedPassword);

        if ($stmt->execute()) {
            $success = "Registration successful. You can now log in!";
            $_SESSION['registration_success'] = true;
        } else {
            // General database error handling
           
        }
        $stmt->close();
    }
}

// Handle Login
if (isset($_POST['login'])) {
    $email = $_POST['loginEmail'];
    $password = $_POST['loginPassword'];
    $errors = [];
    $success = "";

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

            if (strtolower($role) === 'admin') {
                header("Location: admin.php");
                exit();
            } else {
                header("Location: landing.html");
                exit();
            }
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
    <link rel="stylesheet" href="https://db.onlinewebfonts.com/c/ef6bdf5ef216552c7e9869841e891ca0?family=Arial+Rounded+MT+Bold">
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
                        <li><a href="canvas.php">Paintings & Wall Art</a></li>
                    <li><a href="cart.php">Cart</a></li>
                    <li><a href="about.php">About us</a></li>
                </ul>
                <ul class="nav2-links">
                    <li><a href="landing.html">Home</a></li>
                    <li><a href="metal.php">Metal</a></li>
                    <li><a href="ceramic.php">Ceramic</a></li>
                        <li><a href="canvas.php">Paintings & Wall Art</a></li>
                    <li><a href="cart.php">Cart</a></li>
                    <li><a href="about.php">About us</a></li>
                </ul>
            </div>
            <div class="hamburger" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
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
        

            <!-- Login Form -->
            <div class="form-wrapper <?= $showRegisterForm ? '' : 'active' ?>" id="login-form">
                <h2>Login</h2>
                <!-- Login Form -->
                <form method="POST" action="">
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-envelope"></i></div>
                        <input type="email" name="loginEmail" placeholder="Email" required class="<?= isset($errors['loginEmail']) ? 'invalid' : '' ?>">
                        <span id="loginEmailError" class="error-message"><?= $errors['loginEmail'] ?? '' ?></span>
                    </div>
                    <div class="input-group" id="loginPasswordGroup">
                        <div class="icon-wrapper"><i class="fas fa-lock"></i></div>
                        <div class="password-field">
                            <input type="password" name="loginPassword" id="loginPassword" placeholder="Password" required class="<?= isset($errors['loginPassword']) ? 'invalid' : '' ?>">
                            <button type="button" class="toggle-password" id="toggleLoginPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <span id="loginPasswordError" class="error-message"><?= $errors['loginPassword'] ?? '' ?></span>
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
                <form method="POST" action="login.php">
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-user"></i></div>
                        <input type="text" name="fullName" id="fullName" placeholder="Full Name" required class="<?= isset($errors['fullName']) ? 'invalid' : '' ?>">
                        <span id="fullNameError" class="error-message"><?= $errors['fullName'] ?? '' ?></span>
                    </div>
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-envelope"></i></div>
                        <input type="email" name="email" id="email" placeholder="Email" required class="<?= isset($errors['email']) ? 'invalid' : '' ?>">
                        <span id="emailError" class="error-message"><?= $errors['email'] ?? '' ?></span>
                    </div>
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-phone"></i></div>
                        <input type="text" name="phone" id="phone" placeholder="Phone (optional)" class="<?= isset($errors['phone']) ? 'invalid' : '' ?>">
                        <span id="phoneError" class="error-message"><?= $errors['phone'] ?? '' ?></span>
                    </div>
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-lock"></i></div>
                        <div class="password-field">
                            <input type="password" name="password" id="password" placeholder="Password" required class="<?= isset($errors['password']) ? 'invalid' : '' ?>">
                            <button type="button" class="toggle-password" id="togglePassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <span id="passwordError" class="error-message"><?= $errors['password'] ?? '' ?></span>
                    </div>
                    <div class="input-group">
                        <div class="icon-wrapper"><i class="fas fa-lock"></i></div>
                        <div class="password-field">
                            <input type="password" name="confirmPassword" id="confirmPassword" placeholder="Confirm Password" required class="<?= isset($errors['confirmPassword']) ? 'invalid' : '' ?>">
                            <button type="button" class="toggle-password" id="toggleConfirmPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <span id="confirmPasswordError" class="error-message"><?= $errors['confirmPassword'] ?? '' ?></span>
                    </div>
                    <button type="submit" name="register" class="btn">Register</button>
                </form>

                <p class="toggle-text">Already have an account? <a href="#" id="show-login">Login</a></p>
            </div>
        </div>
    </div>
    <!-- Load jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
    // Check if the server-side has signaled a successful registration
    const registrationSuccess = "<?= isset($_SESSION['registration_success']) && $_SESSION['registration_success'] === true ? 'true' : 'false' ?>";

    if (registrationSuccess === 'true') {
        // Unset the PHP session variable to prevent the pop-up on page refresh
        <?php unset($_SESSION['registration_success']); ?>
        
        // Hide the default server-side message
        const $successMessage = $(".success-message:contains('Registration successful.')");
        if ($successMessage.length) {
            $successMessage.hide();
        }

        // Create the custom pop-up HTML
        const successPopup = `
            <div id="success-popup" class="success-popup">
                Registration Successful! 🎉
                <br><small>Redirecting to Login...</small>
            </div>
        `;
        
        $('body').append(successPopup);
        const $popup = $('#success-popup');

        // Animate the pop-up's appearance
        setTimeout(() => {
            $popup.addClass('show');
        }, 50);

        // After a delay, fade out the pop-up and switch forms
        setTimeout(() => {
            $popup.removeClass('show').addClass('hide');
            
            // Wait for the fade-out transition to complete before removing the element
            setTimeout(() => {
                $popup.remove();
                
                // Trigger the click event on the "Login" link to switch forms
                $('#show-login').trigger('click');
                
                // Show the original PHP success message on the login form
                if ($successMessage.length) {
                    $successMessage.show();
                }
                
            }, 3500); // Matches the CSS transition time
        }, 2000);
    }
});

    </script>
</body>

<script src="script.js"></script>
<script src="login.js"></script>
</html>
