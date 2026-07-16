<?php
require_once 'config.php';

$rollNo = '22501A1209';

try {
    $stmt = $pdo->prepare('SELECT * FROM job_offers WHERE roll_no = ?');
    $stmt->execute([$rollNo]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($records) {
        echo "Found " . count($records) . " job offer(s) for roll number $rollNo:\n\n";
        foreach ($records as $record) {
            echo "Company: {$record['company_name']}\n";
            echo "Role: {$record['job_role']}\n";
            echo "Package: {$record['package']}\n";
            echo "Offer Date: {$record['offer_date']}\n";
            echo "Academic Year: {$record['academic_year']}\n";
            echo "Semester: {$record['semester']}\n";
            echo "---\n";
        }
    } else {
        echo "No job offers found for roll number $rollNo.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
