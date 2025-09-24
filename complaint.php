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
    <title>Complaints</title>
    <link rel="stylesheet" href="css/complaint.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

  <section class="main-content">
      <h2>Submit a Complaint</h2>

    <div class="glass-container">
      <div class="contact-section">
        
        <div class="form-container">
          <form action=\"<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>\" method=\"post\">
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
 
    <p>&copy; 2024 Earthelic.com | All Rights Reserved</p>


</body>
</html>
