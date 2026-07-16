<?php
header('Content-Type: application/json');

require_once 'config.php';

try {
    // Get today's date
    $today = date('m-d');
    
    // Get students whose birthday is today
    $stmt = $pdo->prepare("
        SELECT roll_no, student_name, date_of_birth, department 
        FROM students 
        WHERE DATE_FORMAT(date_of_birth, '%m-%d') = ? 
        AND date_of_birth IS NOT NULL 
        ORDER BY student_name ASC
    ");
    $stmt->execute([$today]);
    $birthdays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $birthdays,
        'count' => count($birthdays),
        'date' => date('F j, Y')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
