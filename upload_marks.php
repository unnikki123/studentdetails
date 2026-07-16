<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['marksFile'])) {
    $file = $_FILES['marksFile'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File upload error']);
        exit;
    }

    // Check file type
    $allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
    if (!in_array($file['type'], $allowedTypes) && !preg_match('/\.csv$/i', $file['name'])) {
        echo json_encode(['success' => false, 'message' => 'Please upload a valid CSV file']);
        exit;
    }

    // Get table name from filename
    $filename = pathinfo($file['name'], PATHINFO_FILENAME);
    $tableName = sanitizeTableName($filename);

    if (empty($tableName)) {
        echo json_encode(['success' => false, 'message' => 'Invalid filename for table name']);
        exit;
    }
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'Unable to read file']);
        exit;
    }

    // Read header row
    $header = fgetcsv($handle);
    if (!$header || !is_array($header) || count($header) == 0) {
        fclose($handle);
        echo json_encode(['success' => false, 'message' => 'Invalid CSV format or empty file']);
        exit;
    }

    // Sanitize column names
    $columns = array_map('sanitizeColumnName', array_map('trim', $header));

    // Remove empty columns
    $columns = array_filter($columns, function($col) { return !empty($col); });

    if (count($columns) == 0) {
        fclose($handle);
        echo json_encode(['success' => false, 'message' => 'No valid columns found in CSV']);
        exit;
    }

    try {
        // Check if table already exists
        $checkTableSQL = "SHOW TABLES LIKE '$tableName'";
        $result = $pdo->query($checkTableSQL);
        
        if ($result->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => "Table '$tableName' already exists in the database. Please choose a different filename or rename the existing table."]);
            fclose($handle);
            exit;
        }

        // Create table dynamically
        $createTableSQL = buildCreateTableSQL($tableName, $columns);
        $pdo->exec($createTableSQL);

        // Prepare insert statement
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        $insertSQL = "INSERT INTO `$tableName` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)";
        $stmt = $pdo->prepare($insertSQL);

        $processed = 0;
        $errors = 0;
        $errorDetails = [];

        // Process each row
        while (($row = fgetcsv($handle)) !== false) {
            if (!is_array($row)) {
                continue;
            }

            // Ensure row has enough columns
            $rowData = [];
            for ($i = 0; $i < count($columns); $i++) {
                $rowData[] = isset($row[$i]) ? trim($row[$i]) : '';
            }

            // Skip empty rows
            if (count(array_filter($rowData)) == 0) {
                continue;
            }

            try {
                $stmt->execute($rowData);
                $processed++;
            } catch (Exception $e) {
                $errors++;
                $errorDetails[] = "Row " . ($processed + $errors + 1) . ": " . $e->getMessage();
                // Continue processing other rows
            }
        }

        fclose($handle);

        $message = "Successfully created table '$tableName' with " . count($columns) . " columns and processed $processed records.";
        if ($errors > 0) {
            $message .= " $errors records had errors.";
            // Include first few error details for debugging
            $detailedErrors = array_slice($errorDetails, 0, 3);
            if (!empty($detailedErrors)) {
                $message .= " Sample errors: " . implode("; ", $detailedErrors);
            }
        }

        echo json_encode(['success' => true, 'message' => $message]);

    } catch (Exception $e) {
        fclose($handle);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

// Helper functions
function findColumnIndex($header, $possibleNames) {
    foreach ($possibleNames as $name) {
        $index = array_search($name, $header);
        if ($index !== false) {
            return $index;
        }
    }
    return null;
}

function parseMarks($marksStr) {
    // Handle empty or null values
    if (empty($marksStr)) {
        return null;
    }

    // Remove any extra whitespace
    $marksStr = trim($marksStr);

    // Handle percentages (e.g., "85%", "85 %")
    if (preg_match('/^(\d+(?:\.\d+)?)\s*%?$/', $marksStr, $matches)) {
        $value = floatval($matches[1]);
        // If it has %, assume it's out of 100, otherwise assume it's already a percentage
        return strpos($marksStr, '%') !== false ? $value : $value;
    }

    // Handle fractions (e.g., "85/100", "17/20")
    if (preg_match('/^(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)$/', $marksStr, $matches)) {
        $numerator = floatval($matches[1]);
        $denominator = floatval($matches[2]);
        if ($denominator == 0) return null;
        return ($numerator / $denominator) * 100;
    }

    // Handle letter grades (basic conversion)
    $gradeMap = [
        'a+' => 95, 'a' => 90, 'a-' => 85,
        'b+' => 80, 'b' => 75, 'b-' => 70,
        'c+' => 65, 'c' => 60, 'c-' => 55,
        'd+' => 50, 'd' => 45, 'd-' => 40,
        'f' => 0
    ];

    $lowerMarks = strtolower($marksStr);
    if (isset($gradeMap[$lowerMarks])) {
        return $gradeMap[$lowerMarks];
    }

    // Try to parse as plain number
    if (is_numeric($marksStr)) {
        $value = floatval($marksStr);
        // Assume values over 10 are percentages, under 10 are out of 10
        return $value <= 10 ? $value * 10 : $value;
    }

    return null;
}

function getOrCreateStudent($pdo, $rollNumber) {
    // Check if student exists
    $stmt = $pdo->prepare("SELECT id FROM students WHERE roll_number = ?");
    $stmt->execute([$rollNumber]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        return $student['id'];
    }

    // Create new student
    $stmt = $pdo->prepare("INSERT INTO students (roll_number) VALUES (?)");
    $stmt->execute([$rollNumber]);
    return $pdo->lastInsertId();
}

function sanitizeTableName($name) {
    // Remove file extension if present
    $name = preg_replace('/\.[^.]+$/', '', $name);
    // Replace invalid characters with underscores
    $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    // Ensure it starts with a letter
    $name = preg_replace('/^[^a-zA-Z]+/', '', $name);
    // Limit length
    $name = substr($name, 0, 64);
    return strtolower($name);
}

function sanitizeColumnName($name) {
    // Replace invalid characters with underscores
    $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    // Ensure it starts with a letter or underscore
    $name = preg_replace('/^[^a-zA-Z_]+/', '', $name);
    // Limit length
    $name = substr($name, 0, 64);
    return strtolower($name);
}

function buildCreateTableSQL($tableName, $columns) {
    $columnDefinitions = [];
    foreach ($columns as $column) {
        $columnDefinitions[] = "`$column` VARCHAR(255) DEFAULT NULL";
    }

    $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        " . implode(', ', $columnDefinitions) . ",
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    return $sql;
}
?>
