<?php
// Function to truncate text to a maximum length (default 50 characters)
function truncate_description($text, $max_length = 50) {
    // Check if the text length exceeds the maximum allowed length
    if (mb_strlen($text) > $max_length) {
        // Truncate the string to the max_length
        $truncated = mb_substr($text, 0, $max_length);
        
        // Find the position of the last space in the truncated string
        $last_space = mb_strrpos($truncated, ' ');
        
        // If a space is found, cut it there to avoid splitting a word
        if ($last_space !== false) {
             return mb_substr($truncated, 0, $last_space) . '...';
        }
        
        // Fallback: just cut at max_length if no space is found
        return $truncated . '...';
    }
    // Return the original text if it's short enough
    return $text;
}

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;
?>
