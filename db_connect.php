<?php
// Start session on every page that includes this
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$servername = "localhost";   // XAMPP default
$username   = "root";        // change if needed
$password   = "";            // change if needed
$dbname     = "earthelic";   // your DB name

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
