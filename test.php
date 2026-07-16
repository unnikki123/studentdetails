<?php
// Test password verification
$password = 'admin123';
$hash = '$2y$10$8K7L1OJ45/4Y2nFYcGFxLu3lGhQKPqyTp0HH9VCaCcGvZz6fzF/W';

if (password_verify($password, $hash)) {
    echo "Password matches!";
} else {
    echo "Password does not match!";
}
?>
