<?php
require_once 'config.php';

// Update enrollment data to match the semester format used in attendance data
$roll_number = '24501A1267';

echo "Updating Enrollment Data to Match Attendance Semester Format\n";
echo "===========================================================\n\n";

try {
    // First, let's see what semester formats are actually used in attendance data
    echo "1. Semester formats in attendance_uploads:\n";
    $stmt = $pdo->query('SELECT DISTINCT semester, academic_year FROM attendance_uploads ORDER BY academic_year, semester');
    $semesterFormats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($semesterFormats as $fmt) {
        echo "   {$fmt['academic_year']} | {$fmt['semester']}\n";
    }
    echo "\n";
    
    // Clear existing enrollment data for this student
    $stmt = $pdo->prepare('DELETE FROM student_courses WHERE roll_no = ?');
    $stmt->execute([$roll_number]);
    echo "Cleared existing enrollment data for student {$roll_number}\n\n";
    
    // Insert corrected enrollment data with proper semester format
    // Based on the attendance data showing "2-1" format (year-semester)
    $correctedData = [
        // 2nd Year, 1st Semester (2-1 format)
        ['24501A1267', '2025-26', '2', '2-1', '23BS1305', 'DMGT'],
        ['24501A1267', '2025-26', '2', '2-1', '23ES1304', 'DLCO'],
        ['24501A1267', '2025-26', '2', '2-1', '23HS1301', 'UHV'],
        ['24501A1267', '2025-26', '2', '2-1', '23IT3301', 'ADSA'],
        ['24501A1267', '2025-26', '2', '2-1', '23IT3302', 'OOPJ'],
        ['24501A1267', '2025-26', '2', '2-1', '23IT3351', 'ADS LAB'],
        ['24501A1267', '2025-26', '2', '2-1', '23IT3352', 'OOPJ LAB'],
        ['24501A1267', '2025-26', '2', '2-1', '23SO8355', 'PP'],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO student_courses 
        (roll_no, academic_year, year, semester, subject_code, subject_name) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($correctedData as $row) {
        $stmt->execute($row);
        echo "Inserted: {$row[3]} | {$row[4]} - {$row[5]}\n";
    }
    
    echo "\nUpdated enrollment data with correct semester format.\n";
    echo "Total enrolled courses: " . count($correctedData) . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
