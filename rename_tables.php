<?php
require_once 'config.php';

try {
    // Get current table names
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Current tables:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    echo "\n";
    
    // Rename tables based on patterns
    $renameOperations = [
        // S2 ATTENDANCE TABLES
        's1_att_dec2025' => 'DEC3S12025',
        's1_att_feb_2026' => 'FEB3S12026',
        
        // S1 ATTENDANCE TABLES  
        's1_att_dec2025' => 'DEC3S12025',
        's1_att_feb_2026' => 'FEB3S12026',
        's1_att_jan_2026' => 'JAN3S12026',
        
        // S2 ATTENDANCE TABLES
        's2_att_dec_2025' => 'DEC2S22025',
        's2_att_feb_2026' => 'FEB2S22026',
        's2_att_jan_2026' => 'JAN2S22026',
        
        // S13 ATTENDANCE TABLES
        's13_att_dec_2025' => 'DEC3S12025',
        's13_att_feb_2026' => 'FEB3S12026',
        's13_att_fed_2026' => 'FEB3S12026'
    ];
    
    echo "Executing rename operations:\n";
    
    foreach ($renameOperations as $oldName => $newName) {
        if (in_array($oldName, $tables)) {
            try {
                $sql = "ALTER TABLE `$oldName` RENAME TO `$newName`";
                echo "Renaming $oldName to $newName...\n";
                $pdo->exec($sql);
                echo "✓ Success: $oldName -> $newName\n";
            } catch (PDOException $e) {
                echo "✗ Error renaming $oldName: " . $e->getMessage() . "\n";
            }
        } else {
            echo "- Table $oldName not found\n";
        }
    }
    
    echo "\nRename operations completed!\n";
    
    // Show new table names
    $stmt = $pdo->query("SHOW TABLES");
    $newTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\nUpdated table names:\n";
    foreach ($newTables as $table) {
        echo "- $table\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
