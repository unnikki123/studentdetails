<?php
require_once 'config.php';

echo "Database Structure Check\n";
echo "========================\n\n";

try {
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database:\n";
    print_r($tables);
    
    // Check if nptel_certificates exists
    if (in_array('nptel_certificates', $tables)) {
        echo "\n\nNPTEL Certificates Table Structure:\n";
        $columns = $pdo->query("DESCRIBE nptel_certificates")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})\n";
        }
        
        echo "\n\nSample NPTEL Data:\n";
        $sample = $pdo->query("SELECT * FROM nptel_certificates LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        print_r($sample);
    } else {
        echo "\n\nnptel_certificates table does not exist\n";
    }
    
    // Check attendance_subjects structure
    echo "\n\nAttendance Subjects Structure:\n";
    $columns = $pdo->query("DESCRIBE attendance_subjects")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    // Check if student_courses exists
    if (in_array('student_courses', $tables)) {
        echo "\n\nStudent Courses Structure:\n";
        $columns = $pdo->query("DESCRIBE student_courses")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})\n";
        }
        
        echo "\n\nSample Student Courses Data:\n";
        $sample = $pdo->query("SELECT * FROM student_courses LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        print_r($sample);
    } else {
        echo "\n\nstudent_courses table does not exist\n";
    }
    
    // Check attendance_uploads structure
    echo "\n\nAttendance Uploads Structure:\n";
    $columns = $pdo->query("DESCRIBE attendance_uploads")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    // Sample attendance data
    echo "\n\nSample Attendance Subjects Data:\n";
    $sample = $pdo->query("SELECT * FROM attendance_subjects LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    print_r($sample);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
