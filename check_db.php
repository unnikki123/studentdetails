<?php
require_once 'config.php';

// Check database structure
echo "<h2>Database Tables</h2>";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<pre>";
print_r($tables);
echo "</pre>";

// Check attendance_subjects structure
echo "<h2>Attendance Subjects Table Structure</h2>";
try {
    $columns = $pdo->query("DESCRIBE attendance_subjects")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Sample data
    echo "<h2>Sample Attendance Subjects Data</h2>";
    $sample = $pdo->query("SELECT * FROM attendance_subjects LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($sample);
    echo "</pre>";
} catch(Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Check attendance_uploads structure
echo "<h2>Attendance Uploads Table Structure</h2>";
try {
    $columns = $pdo->query("DESCRIBE attendance_uploads")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch(Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Check if student_courses table exists
echo "<h2>Student Courses Table Structure</h2>";
try {
    $columns = $pdo->query("DESCRIBE student_courses")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch(Exception $e) {
    echo "<p>student_courses table does not exist yet. Need to create it.</p>";
}

// Check students table
echo "<h2>Students Table Structure</h2>";
try {
    $columns = $pdo->query("DESCRIBE students")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Sample data
    echo "<h2>Sample Students Data</h2>";
    $sample = $pdo->query("SELECT * FROM students LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($sample);
    echo "</pre>";
} catch(Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
