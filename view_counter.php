<?php
// View counter functionality
function incrementViewCounter() {
    $counterFile = 'view_counter.txt';
    $count = 0;
    
    // Read current count
    if (file_exists($counterFile)) {
        $count = (int)file_get_contents($counterFile);
    }
    
    // Increment count
    $count++;
    
    // Save new count
    file_put_contents($counterFile, $count);
    
    return $count;
}

function getCurrentViewCount() {
    $counterFile = 'view_counter.txt';
    if (file_exists($counterFile)) {
        return (int)file_get_contents($counterFile);
    }
    return 0;
}

// Auto-increment on page load
$viewCount = incrementViewCounter();
?>
