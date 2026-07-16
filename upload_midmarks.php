<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['midmarksFile'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$file = $_FILES['midmarksFile'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit;
}

$origName = (string)$file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$isXlsx = in_array($ext, ['xlsx', 'xls'], true);
$isCsv  = ($ext === 'csv');

if (!$isXlsx && !$isCsv) {
    echo json_encode(['success' => false, 'message' => 'Please upload a valid CSV or Excel (.xlsx/.xls) file']);
    exit;
}

$collegeName  = isset($_POST['college_name'])  ? trim((string)$_POST['college_name'])  : null;
$details      = isset($_POST['details'])       ? trim((string)$_POST['details'])       : null;
$semesterInfo = isset($_POST['semester_info']) ? trim((string)$_POST['semester_info']) : null;
$academicYear = isset($_POST['academic_year']) ? trim((string)$_POST['academic_year']) : null;
$section      = isset($_POST['section'])       ? trim((string)$_POST['section'])       : null;
$department   = isset($_POST['department'])    ? trim((string)$_POST['department'])    : null;

$fileHash = hash_file('sha256', $file['tmp_name']);

try {
    $stmt = $pdo->prepare('SELECT id FROM midmarks_uploads WHERE file_hash = ? LIMIT 1');
    $stmt->execute([$fileHash]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'This exact file was already uploaded']);
        exit;
    }

    // ---------------------------------------------------------------
    // STEP 1: Load the whole sheet into a plain array of string rows,
    // regardless of source format (CSV or XLSX). Everything downstream
    // (header detection, wide/long parsing) works off this array.
    // ---------------------------------------------------------------
    $allRows = [];

    if ($isXlsx) {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            echo json_encode(['success' => false, 'message' => 'Server is missing PhpSpreadsheet (run: composer require phpoffice/phpspreadsheet)']);
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
                $cell = $sheet->getCell([$c, $r]);
                $val = $cell->getFormattedValue();
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

    if (count($allRows) === 0) {
        echo json_encode(['success' => false, 'message' => 'File is empty']);
        exit;
    }

    // ---------------------------------------------------------------
    // STEP 2: Auto-detect header format
    // Format A: 2-row table header (Row 1: Programm/Branch/Year/Semester/Section/Date/Hallticket/subjects,
    //                                Row 2: B.TECH/IT/II/I/I/2025-2026/1/24501A12029/...)
    // Format B: Details text row + header row
    // ---------------------------------------------------------------
    $normalizeRowText = function (array $row): string {
        $text = implode(' ', array_filter($row, function ($v) { return trim((string)$v) !== ''; }));
        $text = str_replace("\xC2\xA0", ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    };

    $normalizeCell = function ($cell): string {
        return strtolower(trim(preg_replace('/[^a-z0-9]/', '', (string)$cell)));
    };

    // Check for Format A (2-row table header)
    $headerRowIdx = null;
    $metadataRowIdx = null;
    $inferredHallIdx = null;

    for ($i = 0; $i < min(5, count($allRows)); $i++) {
        $row = $allRows[$i];
        $hasProgramm = false;
        $hasBranch = false;
        $hasHallticket = false;
        $hasYear = false;
        $hasSemester = false;
        $hasSection = false;
        $hasDate = false;

        foreach ($row as $cell) {
            $norm = $normalizeCell($cell);
            if ($norm === 'programm' || $norm === 'program') $hasProgramm = true;
            if ($norm === 'branch' || $norm === 'department' || $norm === 'dept') $hasBranch = true;
            if ($norm === 'hallticket' || $norm === 'hallticketno' || $norm === 'htno' || $norm === 'rollno') $hasHallticket = true;
            if ($norm === 'year') $hasYear = true;
            if ($norm === 'semester' || $norm === 'sem') $hasSemester = true;
            if ($norm === 'section' || $norm === 'sec') $hasSection = true;
            if ($norm === 'date') $hasDate = true;
        }

        // If we find a row with these key columns, it's likely the header row
        // Made detection less strict - only need Branch, Hallticket, and (Year or Semester)
        if ($hasBranch && $hasHallticket && ($hasYear || $hasSemester)) {
            $headerRowIdx = $i;
            // Metadata is in the next row
            if (isset($allRows[$i + 1])) {
                $metadataRowIdx = $i + 1;
            }
            break;
        }
    }

    // If Format A not found, try Format B (Details text + header)
    if ($headerRowIdx === null) {
        $detailsRowIdx = null;
        $detailsText = null;

        $scanLimit = min(12, count($allRows));
        for ($i = 0; $i < $scanLimit; $i++) {
            $rowText = $normalizeRowText($allRows[$i]);
            $joined = strtolower($rowText);
            if (strpos($joined, 'details') !== false || strpos($joined, 'ac.yr') !== false ||
                strpos($joined, 'ac yr') !== false || strpos($joined, 'section') !== false) {
                $detailsRowIdx = $i;
                $detailsText = $rowText;
                break;
            }
        }

        $searchFrom = ($detailsRowIdx !== null) ? $detailsRowIdx + 1 : 0;
        for ($i = $searchFrom; $i < count($allRows); $i++) {
            $joined = strtolower($normalizeRowText($allRows[$i]));
            $compactJoined = preg_replace('/[^a-z0-9]+/', '', $joined);
            if (strpos($compactJoined, 'hallticket') !== false || strpos($compactJoined, 'rollno') !== false ||
                strpos($compactJoined, 'rollnumber') !== false || strpos($compactJoined, 'htno') !== false ||
                strpos($compactJoined, 'regdno') !== false || strpos($compactJoined, 'registerno') !== false ||
                strpos($compactJoined, 'registrationno') !== false || strpos($compactJoined, 'pinno') !== false) {
                $headerRowIdx = $i;
                break;
            }
        }

        // Fallback: infer from roll number pattern
        if ($headerRowIdx === null) {
            for ($i = $searchFrom; $i < min(count($allRows), 40); $i++) {
                foreach ($allRows[$i] as $colIdx => $cell) {
                    $candidate = strtoupper(preg_replace('/\s+/', '', trim((string)$cell)));
                    if (!preg_match('/^\d{2,}[A-Z][A-Z0-9]{4,}$/', $candidate)) continue;

                    for ($h = $i - 1; $h >= max(0, $i - 4); $h--) {
                        $nonEmpty = count(array_filter($allRows[$h], function ($v) { return trim((string)$v) !== ''; }));
                        if ($nonEmpty >= 2) {
                            $headerRowIdx = $h;
                            $inferredHallIdx = (int)$colIdx;
                            break 3;
                        }
                    }
                }
            }
        }
    }

    if ($headerRowIdx === null) {
        echo json_encode(['success' => false, 'message' => 'Could not find a header row containing Hallticket/Roll No in the file']);
        exit;
    }

    $header = $allRows[$headerRowIdx];

    // ---------------------------------------------------------------
    // STEP 3: Parse department / semester_info / academic_year /
    // section out of the details text. POST values (if explicitly
    // supplied) always win over what we auto-extract.
    // ---------------------------------------------------------------
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

    $parseHeaderDetails = function (?string $text) use ($romanToInt): array {
        $result = ['department' => null, 'semester_info' => null, 'academic_year' => null, 'section' => null];
        if (!$text) return $result;

        // Department, e.g. "B.TECH - IT" / "B.TECH-CSE"
        if (preg_match('/B\.?\s*TECH\.?\s*-\s*([A-Z]{2,10})/i', $text, $m)) {
            $result['department'] = strtoupper(trim($m[1]));
        }

        // Year/Sem, e.g. "III Yr II Sem"
        if (preg_match('/\b([IVX]{1,4})\s*Yr\.?\s*([IVX]{1,4})\s*Sem/i', $text, $m)) {
            $year = $romanToInt($m[1]);
            $sem = $romanToInt($m[2]);
            if ($year !== null && $sem !== null) {
                $result['semester_info'] = $year . '-' . $sem;
            }
        }

        // Academic year, e.g. "2025-2026 Ac.Yr"
        if (preg_match('/(\d{4}\s*-\s*\d{4})\s*Ac\.?\s*Yr/i', $text, $m)) {
            $result['academic_year'] = preg_replace('/\s+/', '', $m[1]);
        }

        // Section, e.g. "Section : II"
        if (preg_match('/Section\s*:?\s*([A-Z0-9]+)/i', $text, $m)) {
            $result['section'] = strtoupper(trim($m[1]));
        }

        return $result;
    };

    $extracted = $parseHeaderDetails($detailsText);

    // Table-style metadata layout:
    // Format A (2-row header):
    // Row 1: Programme | Branch | Year | Semester | Section | Date | ...
    // Row 2: B.TECH    | IT     | II   | I        | I       | 2025-2026 | ...
    // Row 3+: Student data
    $tableMeta = ['department' => null, 'semester_info' => null, 'academic_year' => null, 'section' => null];
    
    // Use metadata row if Format A was detected, otherwise use row after header
    $metadataRow = ($metadataRowIdx !== null) ? $allRows[$metadataRowIdx] : ($allRows[$headerRowIdx + 1] ?? []);
    
    $metaColumnMap = [];
    foreach ($header as $idx => $cell) {
        $key = strtolower(preg_replace('/[^a-z0-9]+/', '', trim((string)$cell)));
        if (in_array($key, ['branch', 'department', 'dept'], true)) $metaColumnMap['department'] = $idx;
        if ($key === 'year') $metaColumnMap['year'] = $idx;
        if (in_array($key, ['semester', 'sem'], true)) $metaColumnMap['semester'] = $idx;
        if (in_array($key, ['section', 'sec'], true)) $metaColumnMap['section'] = $idx;
        if (in_array($key, ['date', 'academicyear', 'acyr'], true)) $metaColumnMap['date'] = $idx;
        if (in_array($key, ['programm', 'program'], true)) $metaColumnMap['programm'] = $idx;
    }
    
    // Extract department from Branch column
    if (isset($metaColumnMap['department'])) {
        $value = strtoupper(trim((string)($metadataRow[$metaColumnMap['department']] ?? '')));
        if ($value !== '' && $value !== 'IT') {
            $tableMeta['department'] = $value;
        } else if ($value === 'IT') {
            $tableMeta['department'] = 'INFORMATION TECHNOLOGY';
        }
    }
    
    // Extract section
    if (isset($metaColumnMap['section'])) {
        $value = strtoupper(trim((string)($metadataRow[$metaColumnMap['section']] ?? '')));
        // Don't use if value is literally "SECTION" (column name)
        if ($value !== '' && $value !== 'SECTION') $tableMeta['section'] = $value;
    }
    
    // Extract academic year from Date column
    if (isset($metaColumnMap['date'])) {
        $value = (string)($metadataRow[$metaColumnMap['date']] ?? '');
        // Don't use if value is literally "DATE" (column name)
        if (strtoupper(trim($value)) !== 'DATE') {
            // Check if value looks like "2025-2026" format
            if (preg_match('/(20\d{2})\s*-\s*(20\d{2}|\d{2})/', $value, $m)) {
                $endYear = strlen($m[2]) === 2 ? substr($m[1], 0, 2) . $m[2] : $m[2];
                $tableMeta['academic_year'] = $m[1] . '-' . $endYear;
            }
            // Also check if it's just a year range like "2025-26"
            else if (preg_match('/^(\d{4})-(\d{2})$/', $value, $m)) {
                $tableMeta['academic_year'] = $m[1] . '-' . substr($m[1], 0, 2) . $m[2];
            }
        }
    }
    
    // Extract year and semester for semester_info
    if (isset($metaColumnMap['year'], $metaColumnMap['semester'])) {
        $yearValue = trim((string)($metadataRow[$metaColumnMap['year']] ?? ''));
        $semValue = trim((string)($metadataRow[$metaColumnMap['semester']] ?? ''));
        $yearNumber = is_numeric($yearValue) ? (int)$yearValue : $romanToInt($yearValue);
        $semNumber = is_numeric($semValue) ? (int)$semValue : $romanToInt($semValue);
        if ($yearNumber && $semNumber) $tableMeta['semester_info'] = $yearNumber . '-' . $semNumber;
    }
    
    // Adjust data start row for Format A (skip metadata row)
    $dataStartRow = ($metadataRowIdx !== null) ? $headerRowIdx + 2 : $headerRowIdx + 1;

    // Fallback: Parse metadata from filename if table extraction failed
    // Filename format examples: 2S1_INTERNAL_MARKS_25-26_-_I_SEM.xlsx, 3S2_MID_MARKS_25-26-_II_SEM.xlsx
    if (($tableMeta['semester_info'] === null || $tableMeta['academic_year'] === null) && $origName) {
        $filename = pathinfo($origName, PATHINFO_FILENAME);
        // Pattern: YEAR(SEM)_TYPE_ACADEMIC_YEAR-SEMESTER
        if (preg_match('/(\d)S(\d+).*?(\d{2})-(\d{2}).*?([IVX]+)_SEM/i', $filename, $m)) {
            $year = (int)$m[1];
            $section = $m[2];
            $startYear = '20' . $m[3];
            $endYear = '20' . $m[4];
            $semRoman = strtoupper($m[5]);
            
            $tableMeta['academic_year'] = $startYear . '-' . $endYear;
            $tableMeta['section'] = $section;
            
            // Convert Roman to number
            $romanMap = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4];
            $semNum = $romanMap[$semRoman] ?? null;
            if ($semNum) {
                $tableMeta['semester_info'] = $year . '-' . $semNum;
            }
        }
    }

    if ($collegeName === null || $collegeName === '') {
        $collegeName = null; // not present in details text by design; leave as POST-only field
    }
    if ($details === null || $details === '') {
        $details = $detailsText;
    }
    if ($semesterInfo === null || $semesterInfo === '') {
        $semesterInfo = $tableMeta['semester_info'] ?: $extracted['semester_info'];
    }
    if ($academicYear === null || $academicYear === '') {
        $academicYear = $tableMeta['academic_year'] ?: $extracted['academic_year'];
    }
    if ($section === null || $section === '') {
        $section = $tableMeta['section'] ?: $extracted['section'];
    }
    if ($department === null || $department === '') {
        $department = $tableMeta['department'] ?: $extracted['department'];
    }

    // ---------------------------------------------------------------
    // STEP 4: Identify long-mode vs wide-mode using the detected
    // header row, exactly as before.
    // ---------------------------------------------------------------
    $norm = function ($s) {
        $s = strtolower(trim((string)$s));
        $s = preg_replace('/\s+/', ' ', $s);
        $s = preg_replace('/[^a-z0-9_ ]/', '', $s);
        $s = str_replace(' ', '_', $s);
        return $s;
    };

    $headerNorm = array_map($norm, $header);

    $findIndex = function (array $candidates) use ($headerNorm) {
        foreach ($candidates as $c) {
            $idx = array_search($c, $headerNorm, true);
            if ($idx !== false) return (int)$idx;
        }
        return null;
    };

    $rollIdx = $findIndex(['roll_no', 'rollnumber', 'roll_number', 'roll']);
    $subCodeIdx = $findIndex(['subject_code', 'sub_code', 'subjectcode', 'code']);
    $hallIdx = $findIndex(['hallticket_no', 'hallticket', 'hall_ticket_no', 'hallticketno', 'htno', 'ht_no', 'regd_no', 'regdno', 'register_no', 'registration_no', 'pin_no', 'pin']);
    if ($hallIdx === null && $inferredHallIdx !== null) {
        $hallIdx = $inferredHallIdx;
    }

    $wideMode = false;
    if ($rollIdx === null || $subCodeIdx === null) {
        if ($hallIdx !== null) {
            $wideMode = true;
        } else {
            echo json_encode(['success' => false, 'message' => 'Required columns missing. File must contain roll_no and subject_code (long format) OR Hallticket No (wide format)']);
            exit;
        }
    }

    $subNameIdx = $findIndex(['subject_name', 'sub_name', 'subjectname', 'name']);
    $marksIdx = $findIndex(['marks', 'mid_marks', 'midmarks']);
    $d1Idx = $findIndex(['descriptive_marks1', 'descriptive1', 'des_1', 'des1', 'desc1']);
    $o1Idx = $findIndex(['objective_marks1', 'objective1', 'obj_1', 'obj1']);
    $a1Idx = $findIndex(['assignment_marks1', 'assignment1', 'ass_1', 'ass1']);
    $d2Idx = $findIndex(['descriptive_marks2', 'descriptive2', 'des_2', 'des2', 'desc2']);
    $o2Idx = $findIndex(['objective_marks2', 'objective2', 'obj_2', 'obj2']);
    $a2Idx = $findIndex(['assignment_marks2', 'assignment2', 'ass_2', 'ass2']);
    $totalIdx = $findIndex(['total_marks', 'total', 'totalmark', 'totalmarks']);
    $maxIdx = $findIndex(['max_marks', 'max', 'maxmark', 'maxmarks']);

    $toDec = function ($v) {
        if ($v === null) return null;
        $s = trim((string)$v);
        if ($s === '' || $s === '-' || strtolower($s) === 'na' || strtolower($s) === 'n/a') return null;
        $s = str_replace(',', '', $s);
        if (!is_numeric($s)) return null;
        return (float)$s;
    };

    $parseCellMarks = function ($v) use ($toDec) {
        if ($v === null) return null;
        $s = trim((string)$v);
        if ($s === '' || $s === '-' || strtolower($s) === 'na' || strtolower($s) === 'n/a') return null;
        if (preg_match('/=\s*([0-9]+(?:\.[0-9]+)?)/', $s, $m)) {
            return $toDec($m[1]);
        }
        return $toDec($s);
    };

    $parseTupleN = function (string $s) use ($toDec): array {
        $s = trim($s);
        $s = preg_replace('/^\(+|\)+$/', '', $s);
        $parts = array_map('trim', explode(',', $s));
        return array_map($toDec, $parts);
    };

    // Wide-cell parser. Handles, after flattening whitespace/newlines:
    //   (14, 10, 5) + (12, 9, 5)= 29     -> two 3-part mid components + total
    //   (25, 25)= 25                     -> single 2-part lab component + total
    //   29                                -> plain numeric (lab totals etc.)
    $parseWideCell = function (string $cell) use ($parseTupleN, $parseCellMarks, $toDec): array {
        // Flatten newlines/multiple spaces introduced by wrapped Excel cells
        $cell = preg_replace('/\s+/', ' ', trim($cell));
        $d1 = $o1 = $a1 = $d2 = $o2 = $a2 = $total = null;

        // Case 1: two tuples added together, e.g. (a,b,c) + (d,e,f) = total
        if (preg_match('/\(([^)]*)\)\s*\+\s*\(([^)]*)\)\s*(?:=\s*([0-9]+(?:\.[0-9]+)?))?/i', $cell, $m)) {
            $t1 = array_pad($parseTupleN($m[1]), 3, null);
            $t2 = array_pad($parseTupleN($m[2]), 3, null);
            [$d1, $o1, $a1] = $t1;
            [$d2, $o2, $a2] = $t2;

            if (isset($m[3]) && $m[3] !== '') {
                $total = $toDec($m[3]);
            } else {
                $sum = 0.0;
                $has = false;
                foreach ([$d1, $o1, $a1, $d2, $o2, $a2] as $v) {
                    if ($v !== null) { $sum += (float)$v; $has = true; }
                }
                $total = $has ? $sum : null;
            }
            return [$d1, $o1, $a1, $d2, $o2, $a2, $total];
        }

        // Case 2: single tuple, e.g. (25, 25) = 25  (lab format, 2 or 3 components)
        if (preg_match('/^\(([^)]*)\)\s*(?:=\s*([0-9]+(?:\.[0-9]+)?))?$/i', $cell, $m)) {
            $vals = $parseTupleN($m[1]);
            $d1 = $vals[0] ?? null;
            $o1 = $vals[1] ?? null;
            $a1 = $vals[2] ?? null; // null for 2-component lab tuples

            if (isset($m[2]) && $m[2] !== '') {
                $total = $toDec($m[2]);
            } else {
                $sum = 0.0;
                $has = false;
                foreach ($vals as $v) {
                    if ($v !== null) { $sum += (float)$v; $has = true; }
                }
                $total = $has ? $sum : null;
            }
            return [$d1, $o1, $a1, $d2, $o2, $a2, $total];
        }

        // Case 3: plain numeric, e.g. "29"
        $total = $parseCellMarks($cell);
        return [$d1, $o1, $a1, $d2, $o2, $a2, $total];
    };

    // ---------------------------------------------------------------
    // STEP 5: Insert upload record + per-subject marks.
    // ---------------------------------------------------------------
    $pdo->beginTransaction();

    $stmtUp = $pdo->prepare('INSERT INTO midmarks_uploads (college_name, details, semester_info, academic_year, section, department, file_name, file_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

    try {
        $stmtUp->execute([
            ($collegeName !== '' ? $collegeName : null),
            ($details !== '' ? $details : null),
            ($semesterInfo !== '' ? $semesterInfo : null),
            ($academicYear !== '' ? $academicYear : null),
            ($section !== '' ? $section : null),
            ($department !== '' ? $department : null),
            $origName,
            $fileHash,
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000') {
            echo json_encode([
                'success' => false,
                'message' => "Mid marks for $academicYear, Semester $semesterInfo, Section $section ($department) have already been uploaded. Delete the existing upload first if you want to replace it.",
            ]);
            exit;
        }
        throw $e;
    }

    $uploadId = (int)$pdo->lastInsertId();

    $stmtIns = $pdo->prepare('INSERT INTO midmarks_subject_marks (upload_id, roll_no, subject_code, subject_name, marks, descriptive_marks1, objective_marks1, assignment_marks1, descriptive_marks2, objective_marks2, assignment_marks2, total_marks, max_marks, raw_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $processed = 0;
    $errors = 0;
    $errorDetails = [];

    for ($r = $dataStartRow; $r < count($allRows); $r++) {
        $row = $allRows[$r];

        if (count(array_filter($row, function ($x) { return trim((string)$x) !== ''; })) === 0) {
            continue;
        }

        if ($wideMode) {
            $roll = isset($row[$hallIdx]) ? strtoupper(trim((string)$row[$hallIdx])) : '';
            if ($roll === '') {
                continue;
            }

            foreach ($header as $i => $colLabel) {
                if ($i === $hallIdx) continue;

                $colNorm = $headerNorm[$i] ?? '';
                if ($colNorm === '' || in_array($colNorm, ['sno', 's_no', 'sno_', 's_no_', 'serial_no', 'serialno', 'slno', 'sl_no'], true)) {
                    continue;
                }

                $cell = isset($row[$i]) ? (string)$row[$i] : '';
                $cellTrim = trim($cell);
                if ($cellTrim === '') {
                    continue;
                }

                $label = trim((string)$colLabel);
                if ($label === '') continue;

                $parts = preg_split('/\s+/', $label, 2);
                $subCode = trim((string)($parts[0] ?? ''));
                $subName = isset($parts[1]) ? trim((string)$parts[1]) : null;
                // Only course-code columns are marks columns. This excludes
                // Student Name, status, totals and other descriptive fields.
                if ($subCode === '' || !preg_match('/^\d{2}[A-Z]{2,}[0-9A-Z]*/i', $subCode)) continue;

                // A trailing number in headings such as "23HS1301 UHV 30"
                // is the maximum mark, not part of the subject name.
                $subjectMaxMarks = null;
                if ($subName !== null && preg_match('/\s+(\d+(?:\.\d+)?)\s*\*?\s*$/', $subName, $maxMatch)) {
                    $subjectMaxMarks = $toDec($maxMatch[1]);
                    $subName = trim(preg_replace('/\s+\d+(?:\.\d+)?\s*\*?\s*$/', '', $subName));
                }

                [$d1, $o1, $a1, $d2, $o2, $a2, $total] = $parseWideCell($cellTrim);
                $marks = $total;

                try {
                    $stmtIns->execute([
                        $uploadId,
                        $roll,
                        $subCode,
                        ($subName !== '' ? $subName : null),
                        $marks,
                        $d1,
                        $o1,
                        $a1,
                        $d2,
                        $o2,
                        $a2,
                        $total,
                        $subjectMaxMarks,
                        $cellTrim,
                    ]);
                    $processed++;
                } catch (Throwable $e) {
                    $errors++;
                    if (count($errorDetails) < 3) {
                        $errorDetails[] = $e->getMessage();
                    }
                }
            }

            continue;
        }

        $roll = isset($row[$rollIdx]) ? strtoupper(trim((string)$row[$rollIdx])) : '';
        $subCode = isset($row[$subCodeIdx]) ? trim((string)$row[$subCodeIdx]) : '';

        if ($roll === '' || $subCode === '') {
            continue;
        }

        $subName = ($subNameIdx !== null && isset($row[$subNameIdx])) ? trim((string)$row[$subNameIdx]) : null;

        $marks = ($marksIdx !== null && isset($row[$marksIdx])) ? $toDec($row[$marksIdx]) : null;
        $d1 = ($d1Idx !== null && isset($row[$d1Idx])) ? $toDec($row[$d1Idx]) : null;
        $o1 = ($o1Idx !== null && isset($row[$o1Idx])) ? $toDec($row[$o1Idx]) : null;
        $a1 = ($a1Idx !== null && isset($row[$a1Idx])) ? $toDec($row[$a1Idx]) : null;

        $d2 = ($d2Idx !== null && isset($row[$d2Idx])) ? $toDec($row[$d2Idx]) : null;
        $o2 = ($o2Idx !== null && isset($row[$o2Idx])) ? $toDec($row[$o2Idx]) : null;
        $a2 = ($a2Idx !== null && isset($row[$a2Idx])) ? $toDec($row[$a2Idx]) : null;

        $total = ($totalIdx !== null && isset($row[$totalIdx])) ? $toDec($row[$totalIdx]) : null;
        $max = ($maxIdx !== null && isset($row[$maxIdx])) ? $toDec($row[$maxIdx]) : null;

        $rawText = implode(',', array_map(function ($x) {
            $x = (string)$x;
            if (strpos($x, '"') !== false || strpos($x, ',') !== false) {
                $x = '"' . str_replace('"', '""', $x) . '"';
            }
            return $x;
        }, $row));

        try {
            $stmtIns->execute([
                $uploadId,
                $roll,
                $subCode,
                ($subName !== '' ? $subName : null),
                $marks,
                $d1,
                $o1,
                $a1,
                $d2,
                $o2,
                $a2,
                $total,
                $max,
                $rawText,
            ]);
            $processed++;
        } catch (Throwable $e) {
            $errors++;
            if (count($errorDetails) < 3) {
                $errorDetails[] = $e->getMessage();
            }
        }
    }

    $pdo->commit();

    $message = "Midmarks upload successful. Inserted $processed rows.";
    if ($errors > 0) {
        $message .= " $errors errors.";
        if ($errorDetails) {
            $message .= ' Sample: ' . implode(' | ', $errorDetails);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'upload_id' => $uploadId,
        'processed' => $processed,
        'errors' => $errors,
        'extracted' => [
            'department' => $department,
            'semester_info' => $semesterInfo,
            'academic_year' => $academicYear,
            'section' => $section,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Midmarks upload failed', 'error' => $e->getMessage()]);
}
