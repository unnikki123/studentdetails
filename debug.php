<?php
require_once 'config.php';

try {
    // Check user exists
    $stmt = $pdo->prepare("SELECT id, email, password FROM users WHERE email = ?");
    $stmt->execute(['admin@example.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "User found: " . ($user ? 'YES' : 'NO') . "\n";
    
    if ($user) {
        echo "Email: " . $user['email'] . "\n";
        echo "Password hash: " . $user['password'] . "\n";
        
        // Test password
        $password = 'admin123';
        $verify = password_verify($password, $user['password']);
        echo "Password verification: " . ($verify ? 'SUCCESS' : 'FAILED') . "\n";
        
        // Test with plain text fallback
        if (!$verify && $user['password'] === $password) {
            echo "Plain text match: YES\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
