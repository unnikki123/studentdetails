<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roll_no = trim($_POST['roll_no']);
    $date_of_birth = trim($_POST['date_of_birth']);

    if (empty($roll_no)) {
        echo json_encode(['success' => false, 'message' => 'Roll number is required']);
        exit;
    }

    if (empty($date_of_birth)) {
        echo json_encode(['success' => false, 'message' => 'Date of birth is required']);
        exit;
    }

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD']);
        exit;
    }

    try {
        // Check if student exists
        $stmt = $pdo->prepare("SELECT id FROM students WHERE roll_no = ?");
        $stmt->execute([$roll_no]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }

        // Update date of birth
        $stmt = $pdo->prepare("UPDATE students SET date_of_birth = ? WHERE roll_no = ?");
        $stmt->execute([$date_of_birth, $roll_no]);

        echo json_encode(['success' => true, 'message' => 'Date of birth updated successfully']);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
