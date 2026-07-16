<?php
require_once 'config.php';

echo "Checking NPTEL Certificates Table\n";
echo "====================================\n\n";

try {
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database:\n";
    print_r($tables);
    
    // Check if nptel_certificates exists
    if (in_array('nptel_certificates', $tables)) {
        echo "\n\nnptel_certificates table structure:\n";
        $columns = $pdo->query("DESCRIBE nptel_certificates")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "- {$col['Field']} ({$col['Type']})\n";
        }
        
        echo "\n\nSample NPTEL data:\n";
        $sample = $pdo->query("SELECT * FROM nptel_certificates LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        print_r($sample);
    } else {
        echo "\n\nnptel_certificates table does not exist\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>