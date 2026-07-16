<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once 'config.php';

$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';

try {
    // List all student enrollments
    if ($action === 'list') {
        $rollNo = isset($_GET['roll_no']) ? trim($_GET['roll_no']) : '';
        
        if ($rollNo) {
            $stmt = $pdo->prepare(
                'SELECT * FROM student_courses 
                  WHERE roll_no = ? 
                  ORDER BY academic_year, semester, subject_code'
            );
            $stmt->execute([$rollNo]);
        } else {
            $stmt = $pdo->query(
                'SELECT * FROM student_courses 
                  ORDER BY roll_no, academic_year, semester, subject_code 
                  LIMIT 100'
            );
        }
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }
    
    // Add enrollment
    if ($action === 'add') {
        $rollNo = isset($_POST['roll_no']) ? trim($_POST['roll_no']) : '';
        $academicYear = isset($_POST['academic_year']) ? trim($_POST['academic_year']) : '';
        $year = isset($_POST['year']) ? trim($_POST['year']) : '';
        $semester = isset($_POST['semester']) ? trim($_POST['semester']) : '';
        $subjectCode = isset($_POST['subject_code']) ? trim($_POST['subject_code']) : '';
        $subjectName = isset($_POST['subject_name']) ? trim($_POST['subject_name']) : '';
        
        if (!$rollNo || !$academicYear || !$year || !$semester || !$subjectCode) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        $stmt = $pdo->prepare(
            "INSERT INTO student_courses 
            (roll_no, academic_year, year, semester, subject_code, subject_name) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            subject_name = VALUES(subject_name), 
            updated_at = CURRENT_TIMESTAMP"
        );
        
        $stmt->execute([$rollNo, $academicYear, $year, $semester, $subjectCode, $subjectName]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Course enrollment added successfully',
            'id' => $pdo->lastInsertId()
        ]);
        exit;
    }
    
    // Delete enrollment
    if ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        
        $stmt = $pdo->prepare('DELETE FROM student_courses WHERE id = ?');
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Course enrollment deleted successfully'
        ]);
        exit;
    }
    
    // Get available subjects from attendance data for a specific semester
    if ($action === 'available_subjects') {
        $academicYear = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
        $semester = isset($_GET['semester']) ? trim($_GET['semester']) : '';
        
        if (!$academicYear || !$semester) {
            echo json_encode(['success' => false, 'message' => 'Academic year and semester required']);
            exit;
        }
        
        $stmt = $pdo->prepare(
            'SELECT DISTINCT subject_code, subject_name
               FROM attendance_subjects s
               LEFT JOIN attendance_uploads u ON s.session_name = u.session_name
              WHERE u.academic_year = ? AND u.semester = ?
              ORDER BY subject_code'
        );
        $stmt->execute([$academicYear, $semester]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $subjects]);
        exit;
    }
    
    // Bulk import enrollments from attendance data
    if ($action === 'bulk_import') {
        $rollNo = isset($_POST['roll_no']) ? trim($_POST['roll_no']) : '';
        $academicYear = isset($_POST['academic_year']) ? trim($_POST['academic_year']) : '';
        $semester = isset($_POST['semester']) ? trim($_POST['semester']) : '';
        $year = isset($_POST['year']) ? trim($_POST['year']) : '';
        
        if (!$rollNo || !$academicYear || !$semester || !$year) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        // Get all subjects the student has attendance data for in this semester
        $stmt = $pdo->prepare(
            'SELECT DISTINCT s.subject_code, s.subject_name, u.academic_year, u.semester
               FROM attendance_subjects s
               LEFT JOIN attendance_uploads u ON s.session_name = u.session_name
              WHERE s.roll_no = ? AND u.academic_year = ? AND u.semester = ?
              ORDER BY s.subject_code'
        );
        $stmt->execute([$rollNo, $academicYear, $semester]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $imported = 0;
        foreach ($subjects as $subject) {
            $insertStmt = $pdo->prepare(
                "INSERT IGNORE INTO student_courses 
                (roll_no, academic_year, year, semester, subject_code, subject_name) 
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $insertStmt->execute([
                $rollNo,
                $academicYear,
                $year,
                $semester,
                $subject['subject_code'],
                $subject['subject_name']
            ]);
            $imported += $insertStmt->rowCount();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "Imported {$imported} course enrollments from attendance data"
        ]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Enrollment management failed', 'error' => $e->getMessage()]);
}
?>
