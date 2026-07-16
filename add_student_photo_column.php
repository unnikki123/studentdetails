<?php
require_once 'config.php';

echo "Adding student_photo column to students table\n";
echo "============================================\n\n";

try {
    $columns = $pdo->query("DESCRIBE students")->fetchAll(PDO::FETCH_ASSOC);
    $columnExists = false;

    foreach ($columns as $col) {
        if (($col['Field'] ?? '') === 'student_photo') {
            $columnExists = true;
            break;
        }
    }

    if ($columnExists) {
        echo "Column 'student_photo' already exists in students table.\n";
    } else {
        $columnNames = array_map(fn($col) => (string)($col['Field'] ?? ''), $columns);
        $afterColumn = in_array('date_of_birth', $columnNames, true) ? 'date_of_birth' : (in_array('phone', $columnNames, true) ? 'phone' : 'student_name');
        $pdo->exec("ALTER TABLE students ADD COLUMN student_photo VARCHAR(255) DEFAULT NULL AFTER `{$afterColumn}`");
        echo "Successfully added 'student_photo' column to students table.\n";
    }

    echo "\nUpdated Students Table Structure:\n";
    $columns = $pdo->query("DESCRIBE students")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
