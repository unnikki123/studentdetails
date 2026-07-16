<?php
/**
 * Get current student profile data
 * Requires authenticated session
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

try {
    // Get student data
    $stmt = $pdo->prepare("SELECT student_name, student_photo, date_of_birth FROM students WHERE roll_no = ? LIMIT 1");
    $stmt->execute([$rollNumber]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    // Build photo path
    $photoPath = '';
    if (!empty($student['student_photo'])) {
        // Check if it's a relative path
        if (strpos($student['student_photo'], 'http') === 0) {
            $photoPath = $student['student_photo'];
        } else {
            $photoPath = 'uploads/student_photos/' . $student['student_photo'];
        }
    }

    echo json_encode([
        'success' => true,
        'student_name' => $student['student_name'] ?? '',
        'photo_path' => $photoPath,
        'date_of_birth' => $student['date_of_birth'] ?? ''
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
