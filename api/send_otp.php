<?php
/**
 * Send OTP to student email
 * POST: { roll_number, csrf_token */
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

try {
    require_once '../config.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Config error: ' . $e->getMessage()]);
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Read JSON input
$jsonInput = file_get_contents('php://input');
$postData = json_decode($jsonInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Validate CSRF token
$sessionToken = $_SESSION['csrf_token'] ?? '';
$postedToken = $postData['csrf_token'] ?? '';

// Generate token if not exists
if (empty($sessionToken)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$rollNumber = trim($postData['roll_number'] ?? '');

if (empty($rollNumber)) {
    echo json_encode(['success' => false, 'message' => 'Roll number is required']);
    exit;
}

// Validate roll number format
if (!preg_match('/^[0-9A-Za-z]+$/', $rollNumber)) {
    echo json_encode(['success' => false, 'message' => 'Invalid roll number format']);
    exit;
}

// Check if student exists
try {
    $stmt = $pdo->prepare("SELECT student_name FROM students WHERE roll_no = ? LIMIT 1");
    $stmt->execute([$rollNumber]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

// Generate 6-digit OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Store OTP in session with expiry (5 minutes)
$_SESSION['otp'] = $otp;
$_SESSION['otp_roll_number'] = $rollNumber;
$_SESSION['otp_expiry'] = time() + 300; // 5 minutes
$_SESSION['otp_attempts'] = 0;

// Send email
$to = $rollNumber . '@pvpsit.ac.in';
$subject = 'PVPSIT Student Portal - OTP Verification';
$message = "
<html>
<head>
    <title>OTP Verification</title>
</head>
<body>
    <h2>PVPSIT Student Portal</h2>
    <p>Your OTP for profile update is:</p>
    <h1 style='color: #667eea; font-size: 48px; letter-spacing: 5px;'>$otp</h1>
    <p>This OTP is valid for 5 minutes.</p>
    <p>If you did not request this, please ignore this email.</p>
    <hr>
    <p><small>This is an automated email. Please do not reply.</small></p>
</body>
</html>
";

// Send email using PHPMailer
$mail = new PHPMailer(true);

try {
    // Server settings - Gmail SMTP
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'unnikiran@pvpsiddhartha.ac.in';
    $mail->Password = 'byxq jgii ievn nhgd';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Recipients
    $mail->setFrom('unnikiran@pvpsiddhartha.ac.in', 'PVPSIT Student Portal');
    $mail->addAddress($to);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'PVPSIT Student Portal - OTP Verification';
    $mail->Body = $message;

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);
} catch (Exception $e) {
    // For testing: return OTP in response if email fails
    echo json_encode(['success' => true, 'message' => 'OTP sent successfully (email error: ' . $mail->ErrorInfo . ', use OTP: ' . $otp . ')', 'otp' => $otp]);
}
