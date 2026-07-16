<?php
/**
 * Update student date of birth
 * POST: { date_of_birth, csrf_token }
 */
session_start();
header('Content-Type: application/json');
require_once '../config.php';

// Check authentication
if (!isset($_SESSION['student_authenticated']) || $_SESSION['student_authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$rollNumber = $_SESSION['student_roll_no'] ?? '';

if (empty($rollNumber)) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

// Validate CSRF token
$sessionToken = $_SESSION['csrf_token'] ?? '';
$postedToken = $_POST['csrf_token'] ?? '';

if (empty($sessionToken) || $postedToken !== $sessionToken) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$dateOfBirth = trim($_POST['date_of_birth'] ?? '');

if (empty($dateOfBirth)) {
    echo json_encode(['success' => false, 'message' => 'Date of birth is required']);
    exit;
}

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

// Validate date is not in the future
$dobTimestamp = strtotime($dateOfBirth);
if ($dobTimestamp === false || $dobTimestamp > time()) {
    echo json_encode(['success' => false, 'message' => 'Invalid date']);
    exit;
}

// Validate reasonable age (between 10 and 100 years old)
$minAge = strtotime('-100 years');
$maxAge = strtotime('-10 years');

if ($dobTimestamp < $minAge || $dobTimestamp > $maxAge) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid date of birth']);
    exit;
}

// Update database
try {
    $stmt = $pdo->prepare("UPDATE students SET date_of_birth = ? WHERE roll_no = ?");
    $stmt->execute([$dateOfBirth, $rollNumber]);

    echo json_encode([
        'success' => true,
        'message' => 'Date of birth updated successfully'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
