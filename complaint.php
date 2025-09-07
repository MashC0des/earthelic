<?php
// Include the database connection file
include "db_connect.php";

// Check if the form was submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input data
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $message = $conn->real_escape_string($_POST['message']);

    // Prepare an SQL statement to prevent SQL injection
    $sql = "INSERT INTO Complaints (name, email, subject, message) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    // Bind parameters to the statement
    $stmt->bind_param("ssss", $name, $email, $subject, $message);

    // Execute the statement
    if ($stmt->execute()) {
        // Redirect to the success page
        header("Location: successcomplaint.php");
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();
} else {
    // If someone tries to access this page directly, redirect them
    // header("Location: contact_complaint.html");
}
?>

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit a Complaint - Earthelic</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/complaint.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .form-message {
            text-align: center;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 1.1rem;
        }
        .form-message.success {
            background-color: #4CAF50;
            color: white;
        }
        .form-message.error {
            background-color: #f44336;
            color: white;
        }
    </style>
</head>
<body>

  <!-- Navigation Bar (for consistency with other pages) -->
  <header class="head1">
    <a href="landing.php">
      <img src="imgs/earthelic logo file png.png" alt="logo" id="logo1">
    </a>
    <nav class="nav1">
      <ul class="nav-links">
        <li><a href="landing.php">Home</a></li>
        <li><a href="metal.php">Metal</a></li>
        <li><a href="ceramic.php">Ceramic</a></li>
        <li><a href="artworks.php">Paintings & Wall Art</a></li>
        <li><a href="cart.php">Cart</a></li>
        <li><a href="login.php">Log In</a></li>
      </ul>
    </nav>
  </header>

  <section class="main_sec">
    <div class="glass-container">

      <!-- Contact Form Section -->
      <div class="contact-section">
        <div class="contact-form">
          <h2 class="form-title">Submit a Complaint</h2>
          <p class="form-description">We take your feedback seriously. Please fill out the form below and we will get back to you as soon as possible.</p>
          
          <?php
            if (isset($_GET['status'])) {
                if ($_GET['status'] == 'success') {
                    echo '<div class="form-message success">Complaint submitted successfully! We will get back to you shortly.</div>';
                } else if ($_GET['status'] == 'error') {
                    echo '<div class="form-message error">There was an error submitting your complaint. Please try again.</div>';
                }
            }
          ?>

          <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
            <div class="form-group">
              <label for="name">Your Name</label>
              <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
              <label for="email">Your Email</label>
              <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
              <label for="subject">Subject</label>
              <input type="text" id="subject" name="subject" required>
            </div>
            <div class="form-group">
              <label for="message">Your Complaint</label>
              <textarea id="message" name="message" rows="6" required></textarea>
            </div>
            <button type="submit" class="submit-btn">Submit</button>
          </form>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="foot1">
    <div class="social-icons">
      <a href="#"><i class="fab fa-facebook-f"></i></a>
      <a href="#"><i class="fab fa-instagram"></i></a>
      <a href="#"><i class="fab fa-twitter"></i></a>
    </div>
    <p>&copy; 2024 Earthelic.com</p>
  </footer>

</body>
</html>
