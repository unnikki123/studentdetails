<?php
require_once 'config.php';

echo "Students Table Structure Check\n";
echo "=============================\n\n";

try {
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database:\n";
    print_r($tables);
    
    // Find students table
    $studentsTable = null;
    foreach ($tables as $table) {
        if ($table === 'students') {
            $studentsTable = $table;
            echo "\n\nFound students table: $studentsTable\n";
            break;
        }
    }
    
    if ($studentsTable) {
        echo "\nStudents Table Structure:\n";
        $columns = $pdo->query("DESCRIBE $studentsTable")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})\n";
        }
        
        echo "\n\nSample Students Data:\n";
        $sample = $pdo->query("SELECT * FROM $studentsTable LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        print_r($sample);
    } else {
        echo "\n\nNo students table found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
