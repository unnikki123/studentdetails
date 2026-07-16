<?php
/**
 * Verify OTP and authenticate student
 * POST: { roll_number, otp, csrf_token }
 */
session_start();
header('Content-Type: application/json');
require_once '../config.php';

// Read JSON input
$jsonInput = file_get_contents('php://input');
$postData = json_decode($jsonInput, true);

// Validate CSRF token
$sessionToken = $_SESSION['csrf_token'] ?? '';
$postedToken = $postData['csrf_token'] ?? '';

if (empty($sessionToken) || $postedToken !== $sessionToken) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$rollNumber = trim($postData['roll_number'] ?? '');
$otp = trim($postData['otp'] ?? '');

if (empty($rollNumber) || empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Roll number and OTP are required']);
    exit;
}

// Check if OTP exists and is not expired
if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_expiry']) || time() > $_SESSION['otp_expiry']) {
    echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new OTP.']);
    exit;
}

// Check if roll number matches
if ($_SESSION['otp_roll_number'] !== $rollNumber) {
    echo json_encode(['success' => false, 'message' => 'Invalid roll number']);
    exit;
}

// Check OTP attempts (max 3 attempts)
if (isset($_SESSION['otp_attempts']) && $_SESSION['otp_attempts'] >= 3) {
    echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please request a new OTP.']);
    exit;
}

// Verify OTP
if ($_SESSION['otp'] !== $otp) {
    $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
    $remainingAttempts = 3 - $_SESSION['otp_attempts'];
    echo json_encode([
        'success' => false, 
        'message' => "Invalid OTP. $remainingAttempts attempts remaining."
    ]);
    exit;
}

// OTP is valid - authenticate student
$_SESSION['student_authenticated'] = true;
$_SESSION['student_roll_no'] = $rollNumber;
$_SESSION['auth_time'] = time();

// Clear OTP from session
unset($_SESSION['otp']);
unset($_SESSION['otp_expiry']);
unset($_SESSION['otp_roll_number']);
unset($_SESSION['otp_attempts']);

echo json_encode(['success' => true, 'message' => 'OTP verified successfully']);
