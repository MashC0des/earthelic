<?php
session_start();
$conn = new mysqli("localhost", "root", "", "earthelic");

if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

$message = "";

if (isset($_POST['reset'])) {
    $email = trim($_POST['email']);
    $oldPassword = trim($_POST['old_password']);
    $newPassword = trim($_POST['new_password']);

    // Check if email exists
    $stmt = $conn->prepare("SELECT password_hash FROM Users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Verify old password
        if (password_verify($oldPassword, $row['password_hash'])) {
            $hashedNew = password_hash($newPassword, PASSWORD_BCRYPT);

            // Update password
            $stmt = $conn->prepare("UPDATE Users SET password_hash=? WHERE email=?");
            $stmt->bind_param("ss", $hashedNew, $email);
            $stmt->execute();

            $message = "✅ Password updated successfully! <a href='login.php'>Login</a>";
        } else {
            $message = "❌ Current password is incorrect.";
        }
    } else {
        $message = "❌ No account found with this email.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <style>
        body { font-family: Arial; background: #f3f3f3; }
        .container { max-width: 400px; margin: 80px auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px #ccc; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; }
        button { width: 100%; padding: 10px; background: orange; border: none; color: white; cursor: pointer; border-radius: 5px; }
        button:hover { background: darkorange; }
        p { margin-top: 10px; color: green; }
    </style>
</head>
<body>
<div class="container">
    <h2>Reset Password</h2>
    <form method="post">
        <input type="email" name="email" placeholder="Enter your email" required>
        <input type="password" name="old_password" placeholder="Enter current password" required>
        <input type="password" name="new_password" placeholder="Enter new password" required>
        <button type="submit" name="reset">Update Password</button>
    </form>
    <p><?php echo $message; ?></p>
</div>
</body>
</html>
