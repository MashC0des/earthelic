<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>About Us - Earthelic</title>
  <link rel="stylesheet" href="css/metal.css">
  <link rel="stylesheet" href="css/aboutus.css">
  <link rel="stylesheet" href="css/style.css">
   <link rel="stylesheet" href="https://db.onlinewebfonts.com/c/ef6bdf5ef216552c7e9869841e891ca0?family=Arial+Rounded+MT+Bold">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<header class="head1">
  <a href="landing.html">
    <img src="imgs/earthelic logo file png.png" alt="logo" id="logo1">
  </a>
  <nav class="nav1">
    <ul class="nav-links">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="metal.php">Metal</a></li>
                    <li><a href="ceramic.php">Ceramic</a></li>
                     <li><a href="artwork.php">Paintings & Wall Art</a></li>
                    <li><a href="cart.php">Cart</a></li>
                    <li><a href="about.php">About us</a></li>
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <li class="nav-profile-wrap">
                            <a href="profile.php" class="nav-profile-link">
                                <span class="nav-profile-name"><?php echo ($_SESSION['full_name'] ?? 'Profile'); ?></span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li><a href="login.php">Log In</a></li>
                    <?php endif; ?>
                </ul>
</header>

<section class="main_sec">
  <div class="glass-container">

    <!-- Section 1: Artist Photo & Intro -->
    <div class="about-section intro">
      <div class="about-photo">
        <img src="imgs\IMG-20250903-WA0002.jpg" alt="Our Artist">
      </div>
      <div class="about-text">
        <h1>About Our Journey</h1>
        <p>At <strong>Earthelic</strong>, art and culture converge. Our team of passionate artists and creators
          strive to bring unique pieces of ceramic, metal, wall art, and paintings that embody tradition
          with a modern twist.</p>
      </div>
    </div>

    <!-- Section 2–6: Alternating video + description -->
    <?php
    $sections = [
      ["video" => "VID-20250903-WA0001.mp4", "title" => "Our Inspiration", "desc" => "This video shows the detailed process of a sculptor creating a relief of a powerful, bearded figure. The description, Inspired by classical and mythological figures, our art embodies tradition and modern design. This piece showcases the intricate process of bringing a powerful character to life through clay, highlights the classical influence and the craftsmanship involved."],
      ["video" => "VID-20250903-WA0004.mp4", "title" => "The Potter's Wheel", "desc" => "This clip features a potter shaping a piece of clay on a wheel. The description, Witness the ancient art of pottery as skilled hands transform a simple lump of clay into a beautiful, functional piece of art on the wheel, focuses on the creation of a ceramic piece."],
      ["video" => "VID-20250903-WA0005.mp4", "title" => "Shaping Perfection", "desc" => " This video shows an artist meticulously refining the edges and surface of a piece of pottery with their hands. The description, Every curve and line is meticulously shaped to achieve perfection. This video captures the final, delicate touches that give each piece its unique form, emphasizes the attention to detail."],
      ["video" => "VID-20250903-WA0002.mp4", "title" => "Sculpting Narratives", "desc" => "Our sculptures tell stories. This piece, with its intricate details and expressive form, showcases the careful craftsmanship that brings each character to life."],
      ["video" => "VID-20250903-WA0003.mp4", "title" => "Finishing Touches", "desc" => "This video shows an artist painting a metal sculpture with a brush. The description, After the initial shaping, our artworks undergo a final transformation. This clip shows the meticulous process of painting a metal piece, blending durability with vibrant creativity,describes the final steps in the creation of a metal artwork."]
    ];
    $i = 0;
    foreach ($sections as $sec): 
      $isEven = $i % 2 === 0;
    ?>
      <div class="about-section <?php echo $isEven ? 'left' : 'right'; ?>">
        <?php if ($isEven): ?>
          <div class="about-video">
            <video controls muted autoplay loop>
              <source src="imgs/<?php echo $sec['video']; ?>" type="video/mp4">
              Your browser does not support the video tag.
            </video>
          </div>
          <div class="about-text">
            <h2><?php echo $sec['title']; ?></h2>
            <p><?php echo $sec['desc']; ?></p>
          </div>
        <?php else: ?>
          <div class="about-text">
            <h2><?php echo $sec['title']; ?></h2>
            <p><?php echo $sec['desc']; ?></p>
          </div>
          <div class="about-video">
            <video controls muted autoplay loop>
              <source src="imgs/<?php echo $sec['video']; ?>" type="video/mp4">
              Your browser does not support the video tag.
            </video>
          </div>
        <?php endif; ?>
      </div>
    <?php $i++; endforeach; ?>
    
    <!-- Complaint Button -->
    <div class="complaint-button-container">
        <a href="complaint.php" class="complaint-btn">Submit a Complaint</a>
    </div>

  </div>
</section>

<footer class="foot1">
    <div class="social-icons">
        <a href="mailto:earthelicarthouse@gmail.com"><i class="fa-solid fa-envelope"></i></a>
        <a href="tel:999999999"><i class="fa-solid fa-phone"></i></a>
        <a href="https://www.instagram.com/earthelic_homedecor/" target="_blank"><i class="fa-brands fa-square-instagram"></i></a>
        <a href="#"><i class="fa-brands fa-facebook"></i></a>
        <a href="#"><i class="fa-solid fa-location-dot"></i></a>
    </div>
    <p>&copy; 2024 Earthelic.com</p>
</footer>
</body>
</html>
