<?php
require_once 'config.php';

// Add enrollment data for semester 2-2 based on the student's actual attendance subjects
$roll_number = '24501A1267';

echo "Adding Enrollment Data for Semester 2-2\n";
echo "========================================\n\n";

try {
    // First, let's see what subjects this student actually has attendance data for in 2-2
    echo "1. Student's attendance subjects in semester 2-2:\n";
    $stmt = $pdo->prepare(
        'SELECT DISTINCT subject_code, subject_name
           FROM attendance_subjects s
           LEFT JOIN attendance_uploads u ON s.session_name = u.session_name
          WHERE s.roll_no = ? AND u.semester = ?
          ORDER BY subject_code'
    );
    $stmt->execute([$roll_number, '2-2']);
    $attendanceSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($attendanceSubjects as $subject) {
        echo "   {$subject['subject_code']} - {$subject['subject_name']}\n";
    }
    echo "   Total subjects in attendance data: " . count($attendanceSubjects) . "\n\n";
    
    // Insert enrollment data for semester 2-2
    $semester2_2Data = [];
    foreach ($attendanceSubjects as $subject) {
        $semester2_2Data[] = [
            '24501A1267',
            '2025-26',
            '2',
            '2-2',
            $subject['subject_code'],
            $subject['subject_name']
        ];
    }
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO student_courses 
        (roll_no, academic_year, year, semester, subject_code, subject_name) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($semester2_2Data as $row) {
        $stmt->execute($row);
        echo "Inserted: 2-2 | {$row[4]} - {$row[5]}\n";
    }
    
    echo "\nAdded enrollment data for semester 2-2.\n";
    echo "Total courses for 2-2: " . count($semester2_2Data) . "\n";
    
    // Show complete enrollment summary
    echo "\nComplete Enrollment Summary:\n";
    $stmt = $pdo->prepare(
        'SELECT semester, COUNT(*) as course_count
           FROM student_courses
          WHERE roll_no = ?
          GROUP BY semester
          ORDER BY semester'
    );
    $stmt->execute([$roll_number]);
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($summary as $row) {
        echo "  Semester {$row['semester']}: {$row['course_count']} courses\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
