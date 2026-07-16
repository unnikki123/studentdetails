<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['attendanceFile'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$file = $_FILES['attendanceFile'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit;
}

$origName = (string)$file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$isXlsx = in_array($ext, ['xlsx', 'xls'], true);
$isCsv = ($ext === 'csv');

if (!$isXlsx && !$isCsv) {
    echo json_encode(['success' => false, 'message' => 'Please upload a valid CSV or Excel (.xlsx/.xls) file']);
    exit;
}

$fileHash = md5_file($file['tmp_name']);

try {
    $stmt = $pdo->prepare('SELECT id FROM attendance_uploads WHERE file_hash = ? LIMIT 1');
    $stmt->execute([$fileHash]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'This file was already uploaded']);
        exit;
    }

    $allRows = [];

    if ($isXlsx) {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            echo json_encode(['success' => false, 'message' => 'Server is missing PhpSpreadsheet']);
            exit;
        }
        require_once $autoload;

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Unable to read Excel file: ' . $e->getMessage()]);
            exit;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($r = 1; $r <= $highestRow; $r++) {
            $rowData = [];
            for ($c = 1; $c <= $highestColIndex; $c++) {
                $val = $sheet->getCell([$c, $r])->getFormattedValue();
                $rowData[] = $val === null ? '' : (string)$val;
            }
            $allRows[] = $rowData;
        }
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            echo json_encode(['success' => false, 'message' => 'Unable to read file']);
            exit;
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (!is_array($row)) continue;
            $allRows[] = array_map(function ($x) { return $x === null ? '' : (string)$x; }, $row);
        }
        fclose($handle);
    }

    if (!$allRows) {
        echo json_encode(['success' => false, 'message' => 'File is empty']);
        exit;
    }

    $normalizeText = function ($value): string {
        $text = str_replace("\xC2\xA0", ' ', (string)$value);
        return trim(preg_replace('/\s+/', ' ', $text));
    };

    $compactText = function ($value) use ($normalizeText): string {
        return strtolower(preg_replace('/[^a-zA-Z0-9%]+/', '', $normalizeText($value)));
    };


    $rowText = function (array $row) use ($normalizeText): string {
        return $normalizeText(implode(' ', array_filter($row, function ($v) {
            return trim((string)$v) !== '';
        })));
    };

    $romanToInt = function (string $roman): ?int {
        $map = ['I' => 1, 'V' => 5, 'X' => 10];
        $roman = strtoupper(trim($roman));
        if ($roman === '' || !preg_match('/^[IVX]+$/', $roman)) return null;
        $total = 0;
        $prev = 0;
        for ($i = strlen($roman) - 1; $i >= 0; $i--) {
            $val = $map[$roman[$i]] ?? 0;
            if ($val < $prev) {
                $total -= $val;
            } else {
                $total += $val;
                $prev = $val;
            }
        }
        return $total > 0 ? $total : null;
    };

    $filename = pathinfo($origName, PATHINFO_FILENAME);
    $parts = explode('_', $filename);

    $manualAcademicYear = isset($_POST['academic_year']) ? $normalizeText($_POST['academic_year']) : '';
    $academicYear = $manualAcademicYear !== '' ? $manualAcademicYear : null;
    $semester = null;
    $section = null;
    $branch = null;
    $monthName = null;
    $fileYearNumber = null;
    $yearNumber = null;

    if ($academicYear === null && count($parts) >= 5) {
        $academicYear = $parts[1];
    }
    if (count($parts) >= 5) {
        $semester = $parts[2];
        $section = strtoupper($parts[3]);
        $monthName = strtolower($parts[4]);
    }

    if (preg_match('/\b([1-4])\s*S\s*([A-Z0-9]+)\b.*\b(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\b.*\b(20\d{2})(?:\s*[-_]\s*(\d{2,4}))?\b/i', $filename, $m)) {
    $fileYearNumber = (int)$m[1];
        $section = $section ?: ('S' . strtoupper($m[2]));
        $monthName = $monthName ?: strtolower($m[3]);
        if (!$academicYear) {
            $academicYear = $m[4] . (!empty($m[5]) ? '-' . $m[5] : '');
        }
    }


    // Support manual fields from UI (if present)
    if (isset($_POST['academic_year']) && trim((string)$_POST['academic_year']) !== '') {
        $academicYear = trim((string)$_POST['academic_year']);
    }


    $metadataText = '';
    $scanLimit = min(8, count($allRows));
    for ($i = 0; $i < $scanLimit; $i++) {
        $metadataText .= ' ' . $rowText($allRows[$i]);
    }
    $metadataText = $normalizeText($metadataText);

    if (!$section && preg_match('/\bSection\s*:\s*([A-Z0-9]+)/i', $metadataText, $m)) {
        $section = strtoupper($m[1]);
    }

    // Try to extract Branch from table-like (2-row) header layout:
    // Row 1 contains column headers like: Programme | Branch | Year | Semester | Section | Date | Roll No | ...
    // Row 2 contains corresponding values: B.TECH | INFORMATION TECHNOLOGY | II | II | I | <date range> | <roll...> | ...
    // If regex-based extraction fails, this fixes the "unknown" branch issue.
    if ($branch === null || $branch === 'unknown') {
        $headerRowForMeta = null;
        $valueRowForMeta = null;

        // Find the header row that contains the literal column name "Branch".
        for ($i = 0; $i < min(count($allRows), 8); $i++) {
            $row = $allRows[$i];
            foreach ($row as $cell) {
                if ($cell === null) continue;
                $compactCell = $compactText($cell);
                if ($compactCell === 'branch') {
                    $headerRowForMeta = $i;
                    $valueRowForMeta = $i + 1;
                    break 2;
                }
            }
        }

        if ($headerRowForMeta !== null && isset($allRows[$valueRowForMeta])) {
            $headerRow = $allRows[$headerRowForMeta];
            $valueRow = $allRows[$valueRowForMeta];

            foreach ($headerRow as $colIdx => $cell) {
                if ($compactText($cell) === 'branch') {
                    $rawBranch = $valueRow[$colIdx] ?? '';
                    $rawBranch = strtoupper($normalizeText($rawBranch));
                    if ($rawBranch !== '' && $rawBranch !== 'UNKNOWN' && $rawBranch !== 'NA') {
                        $branch = $rawBranch;
                    }
                    break;
                }
            }
        }
    }

    // Apply regex-based Branch extraction only if branch is still unknown.
    if (($branch === null || $branch === 'unknown') && preg_match('/\b(?:Branch|Department|Dept)\s*:\s*([A-Z][A-Z0-9 .&\/-]{1,40})/i', $metadataText, $m)) {
        $candidate = strtoupper(trim(preg_replace('/\s+/', ' ', $m[1])));
        $candidate = preg_replace('/\s+(?:Year|Semester|Section|Date)\s*:.*$/i', '', $candidate);
        if ($candidate !== '' && $candidate !== 'UNKNOWN') {
            $branch = $candidate;
        }
    }

    // Hard fallback: if the file is in the exact 2-row table header format
    // (Programme/Branch/Year/Semester/Section/Date/Roll No ... + values row),
    // use the earliest row pair that looks like that.
    // This avoids depending on exact "Branch" text rendering.
    if ($branch === null || $branch === 'unknown' || $section === null || $section === 'unknown' || $yearNumber === null) {
        // scan first 10 rows for a row that contains both "programme" and "roll"
        for ($i = 0; $i < min(count($allRows), 10); $i++) {
            $r1 = $allRows[$i];
            $r2 = $allRows[$i + 1] ?? null;
            if ($r2 === null) continue;

            $hasProgramme = false;
            $hasRollNo = false;
            $headerColMap = [];

            foreach ($r1 as $colIdx => $cell) {
                $c = $compactText($cell);
                if ($c === 'programme' || $c === 'program') $hasProgramme = true;
                if ($c === 'rollno' || $c === 'rollnumber' || $c === 'roll_no') $hasRollNo = true;

                if ($c === 'branch') $headerColMap['branch'] = (int)$colIdx;
                if ($c === 'section') $headerColMap['section'] = (int)$colIdx;
                if ($c === 'year') $headerColMap['year'] = (int)$colIdx;
                if ($c === 'semester') $headerColMap['semester'] = (int)$colIdx;
            }

            if ($hasProgramme && $hasRollNo && isset($headerColMap['branch'])) {
                $branchCandidate = $r2[$headerColMap['branch']] ?? '';
                $branchCandidate = strtoupper($normalizeText($branchCandidate));
                if ($branchCandidate !== '' && $branchCandidate !== 'UNKNOWN' && $branchCandidate !== 'NA') {
                    $branch = $branchCandidate;
                }

                if (isset($headerColMap['section'])) {
                    $sectionCandidate = strtoupper($normalizeText($r2[$headerColMap['section']] ?? ''));
                    if ($sectionCandidate !== '' && $sectionCandidate !== 'UNKNOWN' && $sectionCandidate !== 'NA') {
                        $section = $sectionCandidate;
                    }
                }

                // year/semester in your file are Roman numerals (II) already.
                $yearCandidate = null;
                $semCandidate = null;
                if (isset($headerColMap['year'])) {
                    $tmp = $normalizeText($r2[$headerColMap['year']] ?? '');
                    if ($tmp !== '') $yearCandidate = $tmp;
                }
                if (isset($headerColMap['semester'])) {
                    $tmp = $normalizeText($r2[$headerColMap['semester']] ?? '');
                    if ($tmp !== '') $semCandidate = $tmp;
                }

                if ($yearCandidate !== null && $semCandidate !== null) {
                    $y = null;
                    $s = null;
                    // re-use romanToInt logic via direct romanToInt closure not accessible here,
                    // so parse simple roman numerals II/III etc.
                    $map = ['I' => 1, 'V' => 5, 'X' => 10];
                    $romanToInt2 = function (string $roman) use ($map): ?int {
                        $roman = strtoupper(trim($roman));
                        if ($roman === '' || !preg_match('/^[IVX]+$/', $roman)) return null;
                        $total = 0; $prev = 0;
                        for ($k = strlen($roman) - 1; $k >= 0; $k--) {
                            $val = $map[$roman[$k]] ?? 0;
                            if ($val < $prev) $total -= $val; else { $total += $val; $prev = $val; }
                        }
                        return $total > 0 ? $total : null;
                    };
                    $yy = $romanToInt2((string)$yearCandidate);
                    $ss = $romanToInt2((string)$semCandidate);
                    if ($yy !== null && $ss !== null) {
                        $yearNumber = $yy;
                        $semester = $yy . '-' . $ss;
                    }
                }

                // We found what we need
                break;
            }
        }
    }

    if (preg_match('/\bYear\s*:\s*([IVX]+|\d+)/i', $metadataText, $m)) {
        $yearNumber = is_numeric($m[1]) ? (int)$m[1] : $romanToInt($m[1]);
    }
    if ($yearNumber === null && $fileYearNumber !== null) {
        $yearNumber = $fileYearNumber;
    }

    $semNumber = null;
    if (preg_match('/\bSemester\s*:\s*([IVX]+|\d+)/i', $metadataText, $m)) {
        $semNumber = is_numeric($m[1]) ? (int)$m[1] : $romanToInt($m[1]);
    }

    if (!$semester && $yearNumber !== null && $semNumber !== null) {
        $semester = $yearNumber . '-' . $semNumber;
    }

    if (preg_match('/\bDate\s*:\s*(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})\s*-\s*(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})/i', $metadataText, $m)) {
        if (!$academicYear) {
            $academicYear = $m[3] . '-' . $m[6];
        }
        if (!$monthName) {
            $monthNames = [1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dec'];
            $startMonth = $monthNames[(int)$m[2]] ?? strtolower($m[2]);
            $endMonth = $monthNames[(int)$m[5]] ?? strtolower($m[5]);
            $monthName = $startMonth === $endMonth ? $startMonth : $startMonth . '-' . $endMonth;
        }
    }

    $academicYear = $academicYear ?: 'unknown';
    $semester = $semester ?: 'unknown';
    $section = $section ?: 'unknown';
    $branch = $branch ?: 'unknown';
    $monthName = $monthName ?: 'unknown';
    $session = sanitizeName($filename);

    $headerRowIdx = null;
    $header = null;

    $headerSubjectIndexes = [];
    foreach ($allRows as $i => $candidate) {
        $hasRoll = false;
        $hasTotal = false;
        $hasPercent = false;
        $candidateSubjectIndexes = [];

        foreach ($candidate as $cellIndex => $cell) {
            $compact = $compactText($cell);
            if (strpos($compact, 'rollno') !== false || strpos($compact, 'rollnumber') !== false || $compact === 'regdno') {
                $hasRoll = true;
            }
            if (strpos($compact, 'total') !== false || strpos($compact, 'tota') !== false || $compact === 'tot') {
                $hasTotal = true;
            }
            if ($compact === '%' || strpos($compact, 'percent') !== false || strpos($compact, 'percentage') !== false) {
                $hasPercent = true;
            }
            if (preg_match('/\d{2}[a-z]{2,}[0-9a-z]*/i', (string)$cell)) {
                $candidateSubjectIndexes[] = $cellIndex;
            }
        }

        if (($hasRoll && ($hasTotal || count($candidateSubjectIndexes) >= 2) && ($hasPercent || $hasTotal)) || count($candidateSubjectIndexes) >= 3) {
            $headerRowIdx = $i;
            $header = $candidate;
            $headerSubjectIndexes = $candidateSubjectIndexes;
            break;
        }
    }

    if ($headerRowIdx === null || !$header) {
        echo json_encode(['success' => false, 'message' => 'Could not find attendance header row containing Roll No, Total, and %']);
        exit;
    }

    $rollIndex = false;
    $totalIndex = false;
    $percentIndex = false;

    foreach ($header as $i => $col) {
        $compact = $compactText($col);
        if (strpos($compact, 'rollno') !== false || strpos($compact, 'rollnumber') !== false || $compact === 'regdno') {
            $rollIndex = $i;
        } elseif ((strpos($compact, 'total') !== false || strpos($compact, 'tota') !== false || $compact === 'tot') && $totalIndex === false) {
            $totalIndex = $i;
        } elseif ($compact === '%' || strpos($compact, 'percent') !== false || strpos($compact, 'percentage') !== false) {
            $percentIndex = $i;
        }
    }

    if ($rollIndex === false && $headerSubjectIndexes) {
        $firstSubjectIndex = min($headerSubjectIndexes);
        $rollIndex = max(0, $firstSubjectIndex - 1);
    }

    if ($totalIndex === false && $headerSubjectIndexes) {
        $totalIndex = max($headerSubjectIndexes) + 1;
    }

    if ($percentIndex === false && $totalIndex !== false) {
        $percentIndex = $totalIndex + 1;
    }

    if ($rollIndex === false || $totalIndex === false || $percentIndex === false) {
        echo json_encode(['success' => false, 'message' => 'Required columns not found. Header must contain Roll No, subject codes, Total, and %']);
        exit;
    }

    $subjectIndexes = [];
    foreach ($header as $i => $col) {
        if ($i === $rollIndex || $i === $totalIndex || $i === $percentIndex) continue;

        $label = $normalizeText($col);
        $compact = $compactText($label);
        if ($label === '' || in_array($compact, ['sno', 'sno.', 'no', 'name'], true)) continue;

        if (preg_match('/\d{2}[A-Z]{2,}[0-9A-Z]*/i', $label)) {
            $subjectIndexes[$i] = $label;
        }
    }

    if (!$subjectIndexes) {
        echo json_encode(['success' => false, 'message' => 'No subject columns found in attendance header']);
        exit;
    }

    $pdo->beginTransaction();

    $hasAttendanceUploadColumn = function (string $col) use ($pdo): bool {
        try {
            $stmt = $pdo->prepare('SHOW COLUMNS FROM attendance_uploads LIKE ?');
            $stmt->execute([$col]);
            return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return false;
        }
    };

    if (!$hasAttendanceUploadColumn('branch')) {
        $pdo->exec('ALTER TABLE attendance_uploads ADD COLUMN branch varchar(50) NOT NULL DEFAULT \'unknown\' AFTER section');
    }

    $stmt = $pdo->prepare('
        INSERT INTO attendance_uploads
        (file_name, file_hash, academic_year, semester, section, branch, month_name, session_name)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $origName,
        $fileHash,
        $academicYear,
        $semester,
        $section,
        $branch,
        $monthName,
        $session,
    ]);

    $uploadId = $pdo->lastInsertId();

    $stmtSummary = $pdo->prepare('
        INSERT INTO attendance_summary
        (roll_no, total_present, total_classes, percentage, session_name, upload_id)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_present = VALUES(total_present),
            total_classes = VALUES(total_classes),
            percentage = VALUES(percentage),
            upload_id = VALUES(upload_id)
    ');

    $stmtSub = $pdo->prepare('
        INSERT INTO attendance_subjects
        (roll_no, subject_code, subject_name, present, extra, total, session_name, upload_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            present = VALUES(present),
            extra = VALUES(extra),
            total = VALUES(total),
            upload_id = VALUES(upload_id)
    ');

    $processed = 0;
    $errors = 0;
    $errorDetails = [];

    for ($r = $headerRowIdx + 1; $r < count($allRows); $r++) {
        $row = $allRows[$r];

        if (count(array_filter($row, function ($x) { return trim((string)$x) !== ''; })) === 0) {
            continue;
        }

        $roll = strtoupper($normalizeText($row[$rollIndex] ?? ''));
        if ($roll === '' || stripos($roll, 'roll') !== false) continue;

        $totalRaw = $normalizeText($row[$totalIndex] ?? '');
        if (!preg_match('/(\d+)\s*(?:\((\d+)\))?\s*\/\s*(\d+)/', $totalRaw, $m)) {
            continue;
        }

        $totalPresent = (int)$m[1] + (int)($m[2] ?? 0);
        $totalClasses = (int)$m[3];
        $percentageRaw = str_replace('%', '', $normalizeText($row[$percentIndex] ?? '0'));
        $percentage = is_numeric($percentageRaw) ? (float)$percentageRaw : 0.0;

        try {
            $stmtSummary->execute([
                $roll,
                $totalPresent,
                $totalClasses,
                $percentage,
                $session,
                $uploadId,
            ]);
        } catch (Throwable $e) {
            $errors++;
            if (count($errorDetails) < 3) {
                $errorDetails[] = 'Summary insert: ' . $e->getMessage();
            }
            continue;
        }

        foreach ($subjectIndexes as $index => $subjectFull) {
            $value = $normalizeText($row[$index] ?? '');
            if ($value === '' || $value === '-') continue;

            if (!preg_match('/^([0-9A-Z]+)\s*(.*)$/i', $subjectFull, $subjectMatch)) {
                continue;
            }

            $subjectCode = strtoupper(trim($subjectMatch[1]));
            $subjectName = trim($subjectMatch[2] ?? '');

            $present = null;
            $extra = 0;
            $total = null;

            if (preg_match('/(\d+)\s*\((\d+)\)\s*\/\s*(\d+)/', $value, $m)) {
                $present = (int)$m[1];
                $extra = (int)$m[2];
                $total = (int)$m[3];
            } elseif (preg_match('/(\d+)\s*\/\s*(\d+)/', $value, $m)) {
                $present = (int)$m[1];
                $total = (int)$m[2];
            }

            if ($present === null || $total === null) continue;

            try {
                $stmtSub->execute([
                    $roll,
                    $subjectCode,
                    $subjectName !== '' ? $subjectName : null,
                    $present,
                    $extra,
                    $total,
                    $session,
                    $uploadId,
                ]);
            } catch (Throwable $e) {
                $errors++;
                if (count($errorDetails) < 3) {
                    $errorDetails[] = 'Subject insert: ' . $e->getMessage();
                }
            }
        }

        $processed++;
    }

    $pdo->commit();

    $message = "Upload successful. $processed records inserted.";
    if ($errors > 0) {
        $message .= " $errors errors occurred.";
        if ($errorDetails) {
            $message .= ' Sample: ' . implode(' | ', $errorDetails);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'processed' => $processed,
        'errors' => $errors,
        'extracted' => [
            'academic_year' => $academicYear,
            'semester' => $semester,
            'section' => $section,
            'branch' => $branch,
            'month_name' => $monthName,
        ],
        'debug_meta' => [
            'metadata_text_preview' => substr($metadataText, 0, 250),
            'branch_final' => $branch,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Attendance upload failed', 'error' => $e->getMessage()]);
}

function sanitizeName($name) {
    $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    return strtolower(substr($name, 0, 64));
}
?>
