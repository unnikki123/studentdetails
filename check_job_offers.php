<?php
require_once 'config.php';

// Check if job_offers table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'job_offers'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "job_offers table exists.\n";
        
        // Check table structure
        echo "\nTable structure:\n";
        $stmt = $pdo->query("DESCRIBE job_offers");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})\n";
        }
        
        // Check if there's any data
        echo "\nTotal records: ";
        $stmt = $pdo->query("SELECT COUNT(*) FROM job_offers");
        echo $stmt->fetchColumn() . "\n";
        
        // Show sample data
        echo "\nSample records:\n";
        $stmt = $pdo->query("SELECT * FROM job_offers LIMIT 3");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($records) {
            foreach ($records as $record) {
                echo "Roll: {$record['roll_no']}, Company: {$record['company_name']}, Package: {$record['package']}\n";
            }
        } else {
            echo "No records found.\n";
        }
    } else {
        echo "job_offers table does NOT exist.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
