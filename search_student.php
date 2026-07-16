<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roll_number = trim($_POST['roll_number']);

    if (empty($roll_number)) {
        echo json_encode(['success' => false, 'message' => 'Roll number is required']);
        exit;
    }

    try {
        // Get student information
        $studentColumns = ['id', 'roll_no', 'student_name', 'email', 'phone', 'date_of_birth', 'father_name', 'department', 'created_at'];
        try {
            $availableStudentColumns = array_map(
                fn($r) => (string)($r['Field'] ?? ''),
                $pdo->query('DESCRIBE students')->fetchAll(PDO::FETCH_ASSOC)
            );
            if (in_array('student_photo', $availableStudentColumns, true)) {
                $studentColumns[] = 'student_photo';
            }
        } catch (Throwable $e) { /* keep basic student lookup working */ }

        $studentSelect = implode(', ', array_map(fn($col) => "`{$col}`", $studentColumns));
        $stmt = $pdo->prepare("SELECT {$studentSelect} FROM students WHERE roll_no = ?");
        $stmt->execute([$roll_number]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get student results from student_results table (regardless of student existence in students table)
        $stmt = $pdo->prepare("SELECT * FROM student_results WHERE roll_no = ?");
        $stmt->execute([$roll_number]);
        $student_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If student not found in students table but exists in student_results, create a basic student record
        if (!$student && !empty($student_results)) {
            $first_result = $student_results[0];
            $student = [
                'id' => null,
                'roll_number' => $roll_number,
                'name' => $first_result['student_name'] ?: 'N/A',
                'email' => 'N/A',
                'phone' => 'N/A'
            ];
        }

        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }

        // Get attendance summary (only if student exists in students table)
        $attendance = ['total' => 0, 'present' => 0, 'absent' => 0];
        // Commented out - attendance table doesn't exist, using attendance_summary instead
        /*
        if ($student['id']) {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
                FROM attendance 
                WHERE student_id = ?
            ");
            $stmt->execute([$student['id']]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        */

        // Get marks information (only if student exists in students table)
        $marks = [];
        // Commented out - marks table might not exist
        /*
        if ($student['id']) {
            $stmt = $pdo->prepare("SELECT * FROM marks WHERE student_id = ? ORDER BY semester, subject");
            $stmt->execute([$student['id']]);
            $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        */

        $response = [
            'success' => true,
            'data' => [
                'student' => $student,
                'attendance' => [
                    'total' => 0,
                    'present' => 0,
                    'absent' => 0
                ],
                'marks' => [],
                'student_results' => $student_results
            ]
        ];

        echo json_encode($response);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
