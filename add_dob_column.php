<?php
require_once 'config.php';

echo "Adding date_of_birth column to students table\n";
echo "=============================================\n\n";

try {
    // Check if column already exists
    $columns = $pdo->query("DESCRIBE students")->fetchAll(PDO::FETCH_ASSOC);
    $columnExists = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'date_of_birth') {
            $columnExists = true;
            break;
        }
    }
    
    if ($columnExists) {
        echo "Column 'date_of_birth' already exists in students table.\n";
    } else {
        // Add the column
        $sql = "ALTER TABLE students ADD COLUMN date_of_birth DATE DEFAULT NULL AFTER phone";
        $pdo->exec($sql);
        echo "Successfully added 'date_of_birth' column to students table.\n";
    }
    
    // Show updated structure
    echo "\nUpdated Students Table Structure:\n";
    $columns = $pdo->query("DESCRIBE students")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
