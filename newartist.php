<?php
include "db_connect.php"; // DB connection + session

// Initialize variables for form persistence and submission status
$artist_name = '';
$email = '';
$primary_medium = '';
$portfolio_link = '';
$artist_message = '';
$phone_number = '';
$submission_status = '';
$submission_message = '';

// Check if the form was submitted
if ($conn && $_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Sanitize and Validate Inputs
    $artist_name = $conn->real_escape_string($_POST['artist_name'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $primary_medium = $conn->real_escape_string($_POST['medium'] ?? '');
    $portfolio_link = $conn->real_escape_string($_POST['portfolio_link'] ?? '');
    $artist_message = $conn->real_escape_string($_POST['message'] ?? '');
    $phone_number = $conn->real_escape_string($_POST['phone'] ?? '');

    // Basic form validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $submission_status = 'error';
        $submission_message = 'Invalid email format provided.';
    } elseif (empty($artist_name) || empty($primary_medium) || empty($portfolio_link) || empty($artist_message)) {
        $submission_status = 'error';
        $submission_message = 'Please fill in all required fields (*).';
    } else {
        
        // --- FILE UPLOAD HANDLING SECTION (Prevents "MySQL server has gone away" error) ---
        $upload_success = true;
        
        if (isset($_FILES['sample_work']) && is_array($_FILES['sample_work']['name'])) {
            $uploaded_files = $_FILES['sample_work'];
            $file_count = count($uploaded_files['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                // If a file was uploaded for this slot
                if ($uploaded_files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    if ($uploaded_files['error'][$i] !== UPLOAD_ERR_OK) {
                        $submission_status = 'error';
                        $submission_message = "File upload failed for file #".($i + 1).". Error code: ".$uploaded_files['error'][$i];
                        $upload_success = false;
                        break; 
                    }
                    
                    // Note: Actual file moving logic (move_uploaded_file) goes here 
                    // if you plan to save the images to the server filesystem.
                }
            }
        }
        
        // --- END FILE UPLOAD HANDLING SECTION ---

        if ($upload_success) {
            // 2. Prepare the INSERT Statement
            $sql = "INSERT INTO artists_applications (artist_name, email, phone_number, primary_medium, portfolio_link, artist_message) VALUES (?, ?, ?, ?, ?, ?)";

            if ($stmt = $conn->prepare($sql)) {
                
                // 3. Bind parameters (s = string)
                $stmt->bind_param("ssssss", $param_name, $param_email, $param_phone, $param_medium, $param_portfolio, $param_message);

                // Set parameters
                $param_name = $artist_name;
                $param_email = $email;
                $param_phone = empty($phone_number) ? NULL : $phone_number;
                $param_medium = $primary_medium;
                $param_portfolio = $portfolio_link;
                $param_message = $artist_message;

                // 4. Execute the statement
                if ($stmt->execute()) {
                    $submission_status = 'success';
                    $submission_message = "Thank you, $artist_name! Your application has been successfully submitted and we will review your portfolio shortly.";
                } else {
                    error_log("SQL Error: " . $stmt->error);
                    $submission_status = 'error';
                    $submission_message = 'Oops! An error occurred during submission. Please try again or check your data.';
                }

                $stmt->close();
            } else {
                error_log("Prepare Error: " . $conn->error);
                $submission_status = 'error';
                $submission_message = 'System error: Could not prepare database query.';
            }
        }
    }
}

// Close database connection if it was successfully opened
if ($conn && !$conn->connect_error) {
    $conn->close();
}

// --- END PHP PROCESSING ---
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Earthelic - Artist Submission</title>
    <!-- Link to the main style for shared elements (like the one class and footer) -->
    <link rel="stylesheet" href="css/style.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>

/*
 * NOTE: The original CSS was embedded here. For simplicity and single-file operation, 
 * the styles are kept in the <style> block, simulating the presence of 'css/join.css'.
 */

.join-container {
    max-width: 800px; 
    margin: 50px auto; 
    padding: 30px;
    background-color: #ffffff; 
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1); 
    font-family: 'Inter', Arial, sans-serif; 
}

.join-container header h2 {
    text-align: center;
    color: #4A4A4A; 
    margin-bottom: 10px;
    font-size: 2.2em;
    font-weight: 700;
}

.join-container header p {
    text-align: center;
    color: #777;
    margin-bottom: 40px;
    line-height: 1.6;
    font-size: 1.1em;
}

/* Form layout */
.artist-form {
    display: flex;
    flex-direction: column;
    gap: 25px; 
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600; 
    margin-bottom: 8px;
    color: #333;
    font-size: 0.95em;
}

/* Styling for all input and select fields */
.form-group input:not([type="file"]),
.form-group select,
.form-group textarea {
    padding: 12px 15px;
    border: 1px solid #D1D5DB; 
    border-radius: 8px; 
    font-size: 1em;
    width: 100%;
    box-sizing: border-box; 
    transition: border-color 0.3s, box-shadow 0.3s;
    background-color: #F9FAFB; 
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #A0522D; 
    outline: none;
    box-shadow: 0 0 0 3px rgba(160, 82, 45, 0.2); 
    background-color: #fff;
}

.form-group textarea {
    resize: vertical; 
}

/* Custom styling for file input to make it look cleaner */
.form-group.file-upload label {
    background-color: #f0f4f8; 
    padding: 15px;
    border-radius: 8px;
    cursor: pointer;
    text-align: center;
    display: block;
    border: 2px dashed #A0522D; 
    color: #4A4A4A;
    transition: background-color 0.3s, border-color 0.3s;
}

.form-group.file-upload label:hover {
    background-color: #e6eaf0;
    border-color: #8B4513;
}

.form-group input[type="file"] {
    /* Hide the default file input field */
    opacity: 0;
    position: absolute;
    z-index: -1;
    width: 0.1px;
    height: 0.1px;
}

/* Submit/Register button styling */
.submit-btn {
    padding: 15px 25px;
    background-color: #A0522D; 
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1.1em;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s, transform 0.1s, box-shadow 0.3s;
    margin-top: 20px;
    box-shadow: 0 4px 10px rgba(160, 82, 45, 0.3);
}

.submit-btn:hover {
    background-color: #8B4513; 
    box-shadow: 0 6px 15px rgba(160, 82, 45, 0.4);
}

.submit-btn:active {
    transform: scale(0.98);
}

/* Submission Message Styling */
.alert-box {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
}
.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Profile specific styles */
.artist-profile {
    text-align: center; 
    padding: 30px; 
    border: 1px solid #c3e6cb; 
    border-radius: 8px; 
    margin-top: 20px;
    background-color: #f7fff7;
}
.artist-profile h3 {
    color: #4A4A4A;
    margin-bottom: 15px;
}


/* Media Queries for smaller screens */
@media (max-width: 600px) {
    .join-container {
        margin: 20px 15px; 
        padding: 20px;
    }
    .join-container header h2 {
        font-size: 1.7em;
    }
    .submit-btn {
        padding: 14px 20px;
        font-size: 1em;
    }
}

</style>
</head>

<body>
    <div id="main">

        <!-- Consistent Header Section (one) -->
        <div class="one">
            <a href="index.html"><img src="imgs\earthelic logo file png.png" alt="Earthelic Logo" id="logo1"></a>
            <h1>Earthelic</h1>
            <a href="home.php" class="shop-btn">Shop Now</a>
        </div>
        
        <!-- Main Content for Artist Submission -->
        <div class="join-container">
            <header>
                <h2>Join the Earthelic Artist Collective</h2>
                <p>We are always looking for passionate creators in **Ceramics, Metals, and Canvas Art** who share our dedication to quality craftsmanship. Share your details below and let's create something beautiful together.</p>
            </header>

            <!-- 
                1. ARTIST PROFILE VIEW - SHOWN BY DEFAULT (unless a form error occurs)
            -->
            <div id="artistProfile" 
                <?php 
                    // Hide the profile view if the form was just submitted with an error
                    if (isset($submission_status) && $submission_status === 'error') {
                        echo 'style="display: none;"';
                    }
                ?>>
                
                <?php if (isset($submission_status) && $submission_status === 'success'): ?>
                    
                    <!-- Submission Success Message -->
                    <div class="alert-box alert-success">
                        <?php echo htmlspecialchars($submission_message); ?>
                    </div>

                    <!-- Placeholder for the "Artist Profile" information -->
                    <div class="artist-profile">
                        <h3>Application Details Summary</h3>
                        <p><strong>Artist Name:</strong> <?php echo htmlspecialchars($artist_name); ?></p>
                        <p><strong>Primary Medium:</strong> <?php echo htmlspecialchars($primary_medium); ?></p>
                        <p><strong>Portfolio Link:</strong> <a href="<?php echo htmlspecialchars($portfolio_link); ?>" target="_blank" style="color: #A0522D; text-decoration: underline;"><?php echo htmlspecialchars($portfolio_link); ?></a></p>
                        <p style="margin-top: 15px; font-style: italic; color: #555;">We will be in touch shortly!</p>
                    </div>

                <?php else: ?>
                    
                    <!-- Generic Initial Profile Message when no application is submitted -->
                    <div class="artist-profile">
                        <h3>Welcome to the Collective!</h3>
                        <p style="color: #555;">If you are an artist ready to showcase your work, click the **Register** button below to begin your application process.</p>
                        <p style="color: #777; font-size: 0.9em;">We specialize in Ceramics, Metal Art, and Painting/Canvas Art.</p>
                    </div>

                <?php endif; ?>

                <div style="text-align: center; margin-top: 30px;">
                    <!-- Button to show the form -->
                    <button type="button" id="showFormBtn" class="submit-btn" style="width: 100%; max-width: 300px;">
                        <i class="fas fa-edit"></i> Register Your Art
                    </button>
                </div>

            </div>


            <!-- 
                2. ARTIST FORM - HIDDEN BY DEFAULT (unless a form error occurs)
            -->
            <div id="artistForm" class="artist-form" 
                <?php 
                    // Hide the form if the profile should be shown, OR if submission was successful
                    if (!isset($submission_status) || $submission_status === 'success') {
                        echo 'style="display: none;"';
                    }
                ?>>
                
                <!-- Display any error message above the form -->
                <?php if (isset($submission_status) && $submission_status === 'error'): ?>
                    <div class="alert-box alert-error">
                        <?php echo htmlspecialchars($submission_message); ?>
                    </div>
                <?php endif; ?>

                <!-- The form action is updated to submit to the same file (newartist.php) -->
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" class="artist-form">
                    
                    <div class="form-group">
                        <label for="artist_name">Full Name*</label>
                        <!-- PHP added to retain data on error -->
                        <input type="text" id="artist_name" name="artist_name" required placeholder="John Doe" value="<?php echo htmlspecialchars($artist_name); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address*</label>
                        <input type="email" id="email" name="email" required placeholder="you@example.com" value="<?php echo htmlspecialchars($email); ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number (Optional)</label>
                        <input type="tel" id="phone" name="phone" placeholder="(123) 456-7890" value="<?php echo htmlspecialchars($phone_number); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="medium">Primary Art Medium*</label>
                        <select id="medium" name="medium" required>
                            <option value="" disabled <?php echo (empty($primary_medium)) ? 'selected' : ''; ?>>Select your primary medium</option>
                            <option value="ceramics" <?php echo ($primary_medium) === 'ceramics' ? 'selected' : ''; ?>>Ceramics</option>
                            <option value="metal" <?php echo ($primary_medium) === 'metal' ? 'selected' : ''; ?>>Metal Art</option>
                            <option value="painting" <?php echo ($primary_medium) === 'painting' ? 'selected' : ''; ?>>Painting / Canvas Art</option>
                            <option value="other" <?php echo ($primary_medium) === 'other' ? 'selected' : ''; ?>>Other (Please specify in the message)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="portfolio_link">Portfolio/Website Link*</label>
                        <input type="url" id="portfolio_link" name="portfolio_link" required placeholder="https://www.yourartsite.com (e.g., Instagram, website, Etsy)" value="<?php echo htmlspecialchars($portfolio_link); ?>">
                    </div>

                    <div class="form-group">
                        <label for="message">Tell Us About Your Work*</label>
                        <textarea id="message" name="message" rows="5" required placeholder="Describe your style, experience, and why you want to join Earthelic..."><?php echo htmlspecialchars($artist_message); ?></textarea>
                    </div>
                    
                    <div class="form-group file-upload">
                        <label for="sample_work">Upload a few sample images (Max 3 files, 5MB each)</label>
                        <input type="file" id="sample_work" name="sample_work[]" multiple accept="image/*">
                    </div>

                    <button type="submit" class="submit-btn">Submit Application</button>
                </form>

            </div>

        </div>
        
        <!-- Consistent Footer Section -->
        <footer class="foot1">
            <div class="social-icons">
                <a href="#"><i class="fa-solid fa-envelope"></i></a>
                <a href="#"><i class="fa-solid fa-phone"></i></a>
                <a href="#"><i class="fa-brands fa-square-instagram"></i></a>
                <a href="#"><i class="fa-brands fa-facebook"></i></a>
                <a href="#"><i class="fa-solid fa-location-dot"></i></a>
            </div>
            <p>&copy; 2024 Earthelic.com</p>
        </footer>

        <!-- JavaScript for toggling the form visibility -->
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const profileDiv = document.getElementById('artistProfile');
                const formDiv = document.getElementById('artistForm');
                const showFormBtn = document.getElementById('showFormBtn');

                if (showFormBtn) {
                    showFormBtn.addEventListener('click', () => {
                        // Hide the profile/summary view
                        profileDiv.style.display = 'none';
                        // Show the form view
                        formDiv.style.display = 'flex';
                        // Scroll to the top of the form 
                        formDiv.scrollIntoView({ behavior: 'smooth' });
                    });
                }

                // Check if an error occurred on load. If it did, the form is already visible
                // but the header text should be updated to prompt the user to complete the form.
                <?php if (isset($submission_status) && $submission_status === 'error'): ?>
                    // When an error happens, the form is already shown via PHP, 
                    // so we just scroll to ensure the error message is visible.
                    if (formDiv) {
                         formDiv.scrollIntoView({ behavior: 'smooth' });
                    }
                <?php endif; ?>
            });
        </script>
    </div>
</body>

</html>
