<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$current = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
$new = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
$confirm = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

if ($current === '' || $new === '' || $confirm === '') {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if ($new !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'New password and confirm password do not match']);
    exit;
}

if (strlen($new) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
    exit;
}

if ($new === $current) {
    echo json_encode(['success' => false, 'message' => 'New password must be different from current password']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, password FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $stored = (string)$user['password'];
    $ok = password_verify($current, $stored) || hash_equals($stored, $current);

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $u = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $u->execute([$newHash, $user['id']]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
