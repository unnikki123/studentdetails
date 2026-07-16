<?php
/**
 * Get CSRF token
 * Returns a fresh CSRF token for the session
 */
session_start();
header('Content-Type: application/json');

// Generate new token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode(['csrf_token' => $_SESSION['csrf_token']]);
