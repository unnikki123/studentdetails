<?php
require_once 'config.php';

// Create student_courses table to map students to their enrolled courses per semester
$sql = "CREATE TABLE IF NOT EXISTS student_courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    roll_no VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    year VARCHAR(10) NOT NULL,
    semester VARCHAR(10) NOT NULL,
    subject_code VARCHAR(50) NOT NULL,
    subject_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_enrollment (roll_no, academic_year, semester, subject_code),
    INDEX idx_roll_no (roll_no),
    INDEX idx_semester (academic_year, semester)
)";

try {
    $pdo->exec($sql);
    echo "student_courses table created successfully.\n";
    
    // Insert sample data for the student from the provided data
    // Based on the student portal data provided: 24501A1267 with course codes
    $sampleData = [
        // II Yr I Sem, 2025-26
        ['24501A1267', '2025-26', '2', '1', '23BS1305', 'DMGT'],
        ['24501A1267', '2025-26', '2', '1', '23ES1304', 'DLCO'],
        ['24501A1267', '2025-26', '2', '1', '23HS1301', 'UHV'],
        ['24501A1267', '2025-26', '2', '1', '23IT3301', 'ADSA'],
        ['24501A1267', '2025-26', '2', '1', '23IT3302', 'OOPJ'],
        ['24501A1267', '2025-26', '2', '1', '23IT3351', 'ADS LAB'],
        ['24501A1267', '2025-26', '2', '1', '23IT3352', 'OOPJ LAB'],
        ['24501A1267', '2025-26', '2', '1', '23SO8355', 'PP'],
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO student_courses 
        (roll_no, academic_year, year, semester, subject_code, subject_name) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($sampleData as $row) {
        $stmt->execute($row);
    }
    
    echo "Sample data inserted for student 24501A1267.\n";
    echo "Total enrolled courses: " . count($sampleData) . "\n";
    
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>
