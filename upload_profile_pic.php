<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized!");
}
$user_id = $_SESSION['user_id'];

if (!empty($_FILES['profile_picture']['name'])) {
    $targetDir = "uploads/profile/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $fileName = time()."_".basename($_FILES["profile_picture"]["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

    $allowedTypes = ["jpg","jpeg","png","gif"];
    if (in_array($fileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFilePath)) {
            $stmt = $conn->prepare("UPDATE Users SET profile_picture=? WHERE user_id=?");
            $stmt->bind_param("si", $targetFilePath, $user_id);
            $stmt->execute();
            echo "<span style='color:green;'>✅ Profile picture updated!</span>";
        } else {
            echo "<span style='color:red;'>❌ Upload failed.</span>";
        }
    } else {
        echo "<span style='color:red;'>❌ Invalid file type.</span>";
    }
}
?>
