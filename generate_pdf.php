<?php
require_once 'config.php';

// mPDF
require_once __DIR__ . '/vendor/autoload.php';

$roll = '';
if (isset($_POST['roll_no'])) {
    $roll = trim((string)$_POST['roll_no']);
} elseif (isset($_GET['roll_no'])) {
    $roll = trim((string)$_GET['roll_no']);
}

$postedHtml = null;
if (isset($_POST['html'])) {
    $postedHtml = (string)$_POST['html'];
    // mPDF handles Unicode symbols better than TCPDF, but we'll normalize for consistency
    $postedHtml = str_replace(["≥", "≤"], [">=", "<="], $postedHtml);

    // Remove attendance sources label if it exists in captured HTML
    $postedHtml = str_replace(
        [
            'ATTENDANCE SUMMARY + ATTENDANCE SUBJECTS + ATTENDANCE ANALYSIS',
            'Attendance Summary + Attendance Subjects + Attendance Analysis',
        ],
        ['', ''],
        $postedHtml
    );
}
if ($roll === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing roll_no']);
    exit;
}

$rollUpper = strtoupper($roll);
$rollLower = strtolower($roll);

try {
    $getField = function (array $row, array $keys): string {
        foreach ($keys as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null && trim((string)$row[$k]) !== '' && (string)$row[$k] !== 'N/A') {
                return (string)$row[$k];
            }
        }
        return '';
    };

    $monthLabelFrom = function (array $row): string {
        $src = strtolower(trim((string)($row['source_table'] ?? '')));
        $createdAt = trim((string)($row['created_at'] ?? ''));

        $monthMap = [
            'jan' => 'January',
            'feb' => 'February',
            'mar' => 'March',
            'apr' => 'April',
            'may' => 'May',
            'jun' => 'June',
            'jul' => 'July',
            'aug' => 'August',
            'sep' => 'September',
            'oct' => 'October',
            'nov' => 'November',
            'dec' => 'December',
        ];

        if ($src !== '' && preg_match('/(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[^0-9]*?(20\d{2})/i', $src, $m)) {
            $mon = strtolower($m[1]);
            $yr = $m[2];
            return ($monthMap[$mon] ?? ucfirst($mon)) . ' ' . $yr;
        }

        if ($createdAt !== '') {
            $ts = strtotime($createdAt);
            if ($ts !== false) {
                return date('F Y', $ts);
            }
        }

        return '';
    };

    // Student
    $stmt = $pdo->prepare("SELECT * FROM students WHERE roll_no IN (?, ?, ?) LIMIT 1");
    $stmt->execute([$roll, $rollUpper, $rollLower]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // Results
    $studentResults = [];
    $subjectResults = [];

    try {
        $stmt = $pdo->prepare("SELECT * FROM student_results WHERE roll_no IN (?, ?, ?) ORDER BY created_at DESC");
        $stmt->execute([$roll, $rollUpper, $rollLower]);
        $studentResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    try {
        $stmt = $pdo->prepare("SELECT * FROM subject_results WHERE roll_no IN (?, ?, ?) ORDER BY subject_code");
        $stmt->execute([$roll, $rollUpper, $rollLower]);
        $subjectResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    // Attendance
    $attendanceSummary = [];
    $attendanceSubjects = [];

    try {
        $stmt = $pdo->prepare("SELECT * FROM attendance_summary WHERE roll_no IN (?, ?, ?) ORDER BY session_name");
        $stmt->execute([$roll, $rollUpper, $rollLower]);
        $attendanceSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    try {
        $stmt = $pdo->prepare("SELECT * FROM attendance_subjects WHERE roll_no IN (?, ?, ?) ORDER BY session_name, subject_code");
        $stmt->execute([$roll, $rollUpper, $rollLower]);
        $attendanceSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    if (!$student && empty($studentResults) && empty($attendanceSummary) && empty($attendanceSubjects) && empty($subjectResults)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No data found for this roll number']);
        exit;
    }

    // Fallback name from results
    if (!$student && !empty($studentResults) && !empty($studentResults[0]['student_name'])) {
        $student = [
            'roll_no' => $roll,
            'student_name' => $studentResults[0]['student_name'],
        ];
    }

    // Build HTML: if UI sent the rendered view, use it so PDF matches the page.
    $name = $student['student_name'] ?? 'N/A';
    $rollNo = $student['roll_no'] ?? $roll;

    $primaryRow = (!empty($studentResults) ? ($studentResults[0] ?? []) : (!empty($subjectResults) ? ($subjectResults[0] ?? []) : []));
    $monthLabel = is_array($primaryRow) ? $monthLabelFrom($primaryRow) : '';
    $examInfo = is_array($primaryRow) ? $getField($primaryRow, ['exam_info', 'exam', 'examtype', 'exam_type']) : '';
    if ($examInfo === '' && !empty($subjectResults)) {
        $examInfo = $getField($subjectResults[0], ['exam_info', 'exam', 'examtype', 'exam_type']);
    }
    $semInfo = is_array($primaryRow) ? $getField($primaryRow, ['semester_info', 'sem_info', 'semester', 'sem', 'semester_info']) : '';
    if ($semInfo === '' && !empty($subjectResults)) {
        $semInfo = $getField($subjectResults[0], ['semester_info', 'sem_info', 'semester', 'sem', 'semester_info']);
    }

    $fatherName = is_array($primaryRow) ? $getField($primaryRow, ['father_name', 'fathername', 'father', 'f_name']) : '';
    if ($fatherName === '' && !empty($subjectResults)) {
        $fatherName = $getField($subjectResults[0], ['father_name', 'fathername', 'father', 'f_name']);
    }

    if ($examInfo !== '') {
        $examInfo = preg_replace('/pvp/i', '***', $examInfo);
    }

    $headerLine = '';
    $parts = [];
    if ($monthLabel !== '') $parts[] = htmlspecialchars($monthLabel);
    if ($examInfo !== '') $parts[] = htmlspecialchars($examInfo);
    if ($semInfo !== '') $parts[] = 'SEMESTER INFO: <b>' . htmlspecialchars($semInfo) . '</b>';
    if (!empty($parts)) {
        $headerLine = '<div style="font-size:10px; color:#111; margin:6px 0 8px;">' . implode(' | ', $parts) . '</div>';
    }

    $srLatest = (!empty($studentResults) && is_array($studentResults[0])) ? $studentResults[0] : [];
    $hasSgpaBlock = !empty($srLatest);
    $sgpaBlock = '';
    if ($hasSgpaBlock) {
        $sgpa = $getField($srLatest, ['sgpa']);
        $cgpa = $getField($srLatest, ['cgpa']);
        $scr = $getField($srLatest, ['scr']);
        $tcr = $getField($srLatest, ['tcr']);
        if ($sgpa !== '' || $cgpa !== '' || $scr !== '' || $tcr !== '') {
            $sgpaBlock .= '<table cellpadding="4">';
            $sgpaBlock .= '<tr>';
            $sgpaBlock .= '<th width="25%">SGPA</th>';
            $sgpaBlock .= '<th width="25%">CGPA</th>';
            $sgpaBlock .= '<th width="25%">SCR</th>';
            $sgpaBlock .= '<th width="25%">TCR</th>';
            $sgpaBlock .= '</tr>';
            $sgpaBlock .= '<tr>';
            $sgpaBlock .= '<td align="center">' . htmlspecialchars($sgpa) . '</td>';
            $sgpaBlock .= '<td align="center">' . htmlspecialchars($cgpa) . '</td>';
            $sgpaBlock .= '<td align="center">' . htmlspecialchars($scr) . '</td>';
            $sgpaBlock .= '<td align="center">' . htmlspecialchars($tcr) . '</td>';
            $sgpaBlock .= '</tr>';
            $sgpaBlock .= '</table>';
        }
    }

    $gradesGrid = '';
    if (!empty($subjectResults)) {
        usort($subjectResults, function ($a, $b) {
            return strcmp((string)($a['subject_code'] ?? ''), (string)($b['subject_code'] ?? ''));
        });

        $gradesGrid .= '<h3>Subject-wise Grades</h3>';
        $gradesGrid .= '<table cellpadding="4">';
        $gradesGrid .= '<tr>';
        $gradesGrid .= '<th width="55%">Subject</th>';
        $gradesGrid .= '<th width="20%">Credits</th>';
        $gradesGrid .= '<th width="25%">Grade</th>';
        $gradesGrid .= '</tr>';
        foreach ($subjectResults as $s) {
            $gradeRaw = trim((string)($s['grade'] ?? ''));
            $isFail = ($gradeRaw !== '' && preg_match('/^f/i', $gradeRaw) === 1);
            $gradeStyle = $isFail
                ? 'background-color:#f8d7da; color:#842029; font-weight:bold; text-align:center;'
                : 'background-color:#d1e7dd; color:#0f5132; font-weight:bold; text-align:center;';

            $code = trim((string)($s['subject_code'] ?? ''));
            $sname = trim((string)($s['subject_name'] ?? ''));
            $subjectLabel = $code;
            if ($sname !== '') {
                $subjectLabel = $code . ' - ' . $sname;
            }

            $gradesGrid .= '<tr>';
            $gradesGrid .= '<td>' . htmlspecialchars($subjectLabel) . '</td>';
            $gradesGrid .= '<td align="center">' . htmlspecialchars((string)($s['credits'] ?? '')) . '</td>';
            $gradesGrid .= '<td style="' . $gradeStyle . '">' . htmlspecialchars($gradeRaw) . '</td>';
            $gradesGrid .= '</tr>';
        }
        $gradesGrid .= '</table>';
    }

    if ($postedHtml !== null && trim($postedHtml) !== '') {
        // Filter posted HTML to include only attendance-related tables
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $postedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $filteredHtml = '';

        // Find all data-card elements
        $cards = $xpath->query('//div[contains(@class, "data-card")]');
        foreach ($cards as $card) {
            $cardHtml = $dom->saveHTML($card);
            // Include only attendance-related cards, exclude student results and subject results
            if (stripos($cardHtml, 'ATTENDANCE') !== false && stripos($cardHtml, 'STUDENT RESULTS') === false && stripos($cardHtml, 'SUBJECT RESULTS') === false) {
                $filteredHtml .= $cardHtml;
            }
        }

        $html = '<h2 style="text-align:center;">OFFICIAL STUDENT REPORT</h2>';
        $html .= '<div style="font-size:9px; color:#333; text-align:center; border-bottom: 1px solid #333; padding-bottom: 3px; margin-bottom: 6px;">Roll No: ' . htmlspecialchars((string)$rollNo) . ' | Name: ' . htmlspecialchars((string)$name) . ($fatherName !== '' ? (' | Father: ' . htmlspecialchars((string)$fatherName)) : '') . '</div>';
        if ($sgpaBlock !== '') {
            $html .= $sgpaBlock;
            $html .= '<div style="height: 4px;"></div>';
        }
        $html .= '<div style="border-top: 1px solid #333; margin: 6px 0;"></div>';

        // Minimal styles to keep tables readable in mPDF (compact spacing for PDF).
        $html .= '<style>
            body { font-family: serif; font-size: 10px; }
            table { border-collapse: collapse; width: 100%; margin: 1px 0; font-size: 9px; }
            th, td { border: 1px solid #333; padding: 2px; font-size: 9px; vertical-align: top; line-height: 1.3; }
            h2 { font-size: 16px; margin: 6px 0 4px; text-align: center; font-weight: bold; }
            h3 { font-size: 13px; margin: 6px 0 3px; font-weight: bold; }
            h6 { font-size: 10px; margin: 3px 0 2px; }
            .card { margin: 3px 0; border: 1px solid #999; }
            .card-header { background-color: #f0f0f0; font-weight: bold; padding: 2px; border-bottom: 1px solid #999; font-size: 10px; }
            .card-body { padding: 2px; }
            .badge { padding: 2px 3px; border: 1px solid #333; font-size: 8px; }
            .text-success { color: #198754; }
            .text-warning { color: #fd7e14; }
            .text-danger { color: #dc3545; }
            .attendance-pivot th, .attendance-pivot td { padding: 2px; font-size: 8px; }
            .pct-line { margin-top: 2px; }
            .small { font-size: 8px; }
            .fw-bold { font-weight: bold; }
            .text-end { text-align: right; }
            .table-sm { font-size: 8px; }
            .table-striped tr:nth-child(even) { background-color: #f9f9f9; }
            .table-hover tr:hover { background-color: #f0f0f0; }
            .table-dark { background-color: #333; color: white; }
            .table-dark th { background-color: #333; color: white; }
        </style>';

        $html .= $filteredHtml;
    } else {
        $html = '<h2 style="text-align:center;">OFFICIAL STUDENT REPORT</h2>';
        if ($headerLine !== '') {
            $html .= $headerLine;
        }
        if ($fatherName !== '') {
            $html .= '<div style="font-size:9px; color:#333; text-align:center; border-bottom: 1px solid #333; padding-bottom: 3px; margin-bottom: 6px;">Father: ' . htmlspecialchars((string)$fatherName) . '</div>';
        }
        $html .= '<div style="border-top: 1px solid #333; margin: 6px 0;"></div>';

        // Details of Academic Performance
        $html .= '<h3 style="font-size:10px; font-weight:bold; margin:6px 0 3px;">Details of Academic Performance</h3>';
        $html .= '<table border="1" cellpadding="2" style="width:100%; border-collapse:collapse;">';
        
        // Header row
        $html .= '<tr>';
        $html .= '<th style="width:35%; text-align:left; padding:2px; font-size:9px;">Subject Name</th>';
        $html .= '<th style="width:8%; text-align:center; padding:2px; font-size:9px;">Credits</th>';
        $html .= '<th style="width:12%; text-align:center; padding:2px; font-size:9px;">% attendance</th>';
        $html .= '<th style="width:7%; text-align:center; padding:2px; font-size:9px;">Des I</th>';
        $html .= '<th style="width:7%; text-align:center; padding:2px; font-size:9px;">Obj I</th>';
        $html .= '<th style="width:7%; text-align:center; padding:2px; font-size:9px;">Ass I</th>';
        $html .= '<th style="width:7%; text-align:center; padding:2px; font-size:9px;">Des II</th>';
        $html .= '<th style="width:7%; text-align:center; padding:2px; font-size:9px;">Obj II</th>';
        $html .= '<th style="width:7%; text-align:center; padding:2px; font-size:9px;">Ass II</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Final Marks</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Grade</th>';
        $html .= '</tr>';
        
        // Subject rows
        if (!empty($subjectResults)) {
            foreach ($subjectResults as $s) {
                $subjectName = ($s['subject_code'] ?? '') . ' - ' . ($s['subject_name'] ?? '');
                $credits = htmlspecialchars((string)($s['credits'] ?? ''));
                $grade = htmlspecialchars((string)($s['grade'] ?? ''));
                
                // Calculate attendance percentage for this subject (if available)
                $attendancePct = '';
                if (!empty($attendanceSubjects)) {
                    foreach ($attendanceSubjects as $a) {
                        if (($a['subject_code'] ?? '') === ($s['subject_code'] ?? '')) {
                            $total = (int)($a['total'] ?? 0);
                            $present = (int)($a['present'] ?? 0);
                            $attendancePct = $total > 0 ? round(($present / $total) * 100, 2) . '%' : '';
                            break;
                        }
                    }
                }
                
                $html .= '<tr>';
                $html .= '<td style="padding:2px; font-size:9px;">' . htmlspecialchars(trim($subjectName)) . '</td>';
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;">' . $credits . '</td>';
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;">' . $attendancePct . '</td>';
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Descriptive I - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Objective I - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Assignment I - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Descriptive II - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Objective II - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Assignment II - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Final Internal Marks - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px; font-weight:bold;">' . $grade . '</td>';
                $html .= '</tr>';
            }
        } else {
            // Empty row if no subjects
            $html .= '<tr><td colspan="11" style="text-align:center; padding:10px;">No subject data available</td></tr>';
        }
        
        $html .= '</table>';
        $html .= '<div style="height:4px;"></div>';

        // Backlog Courses Information
        $html .= '<h3 style="font-size:10px; font-weight:bold; margin:6px 0 3px;">Backlog Courses Information</h3>';
        $html .= '<table border="1" cellpadding="2" style="width:100%; border-collapse:collapse;">';
        
        // Backlog header
        $html .= '<tr>';
        $html .= '<th style="width:30%; text-align:left; padding:2px; font-size:9px;">Subject Name</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Current CGPA</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Internal Marks</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">End semester Marks</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Supply 1 (mm-yy)</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Grade/external Marks</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Supply 2 (mm-yy)</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Grade/external Marks</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Supply 3 (mm-yy)</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Grade/external Marks</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Supply 4 (mm-yy)</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Grade/external Marks</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Supply 5 (mm-yy)</th>';
        $html .= '<th style="width:10%; text-align:center; padding:2px; font-size:9px;">Grade/external Marks</th>';
        $html .= '</tr>';
        
        // Check for backlogs (F grades)
        $backlogSubjects = [];
        if (!empty($subjectResults)) {
            foreach ($subjectResults as $s) {
                $grade = trim((string)($s['grade'] ?? ''));
                if ($grade !== '' && preg_match('/^f/i', $grade) === 1) {
                    $backlogSubjects[] = $s;
                }
            }
        }
        
        if (!empty($backlogSubjects)) {
            foreach ($backlogSubjects as $s) {
                $subjectName = ($s['subject_code'] ?? '') . ' - ' . ($s['subject_name'] ?? '');
                $html .= '<tr>';
                $html .= '<td style="padding:2px; font-size:9px;">' . htmlspecialchars(trim($subjectName)) . '</td>';
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Current CGPA - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Internal Marks - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // End semester Marks - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Supply 1 - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Grade/external Marks - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Supply 2 - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Grade/external Marks - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Supply 3 - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Grade/external Marks - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Supply 4 - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Grade/external Marks - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Supply 5 - placeholder
                $html .= '<td style="text-align:center; padding:2px; font-size:9px;"></td>'; // Grade/external Marks - placeholder
                $html .= '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="14" style="text-align:center; padding:10px;">No backlogs</td></tr>';
        }
        
        $html .= '</table>';
        $html .= '<div style="height:4px;"></div>';

        // Overall Performance upto this semester
        $html .= '<h3 style="font-size:10px; font-weight:bold; margin:6px 0 3px;">Overall Performance upto this semester</h3>';
        
        // Left column
        $currentSgpa = $getField($srLatest, ['sgpa']);
        $currentCgpa = $getField($srLatest, ['cgpa']);
        $backlogCount = count($backlogSubjects);
        
        $html .= '<table border="1" cellpadding="2" style="width:48%; border-collapse:collapse; float:left;">';
        $html .= '<tr><td style="padding:2px; font-size:9px; font-weight:bold;">Total Credits:</td><td style="padding:2px; font-size:9px;"></td></tr>';
        $html .= '<tr><td style="padding:2px; font-size:9px; font-weight:bold;">SGPA:</td><td style="padding:2px; font-size:9px;">' . htmlspecialchars($currentSgpa) . '</td></tr>';
        $html .= '<tr><td style="padding:2px; font-size:9px; font-weight:bold;">No of BACKLOGS:</td><td style="padding:2px; font-size:9px;">' . $backlogCount . '</td></tr>';
        $html .= '<tr><td style="padding:2px; font-size:9px; font-weight:bold;">Mentor Remarks about attendance & marks:</td><td style="padding:2px; font-size:9px;"></td></tr>';
        $html .= '</table>';
        
        // Right column
        $html .= '<table border="1" cellpadding="2" style="width:48%; border-collapse:collapse; float:right;">';
        $html .= '<tr><td style="padding:2px; font-size:9px; font-weight:bold;">CGPA:</td><td style="padding:2px; font-size:9px;">' . htmlspecialchars($currentCgpa) . '</td></tr>';
        $html .= '<tr><td style="padding:2px; font-size:9px; font-weight:bold;">Cumulative Rank in the College:</td><td style="padding:2px; font-size:9px;"></td></tr>';
        $html .= '<tr><td style="padding:2px; font-size:9px; font-weight:bold;">Cumulative Rank in the Branch:</td><td style="padding:2px; font-size:9px;"></td></tr>';
        $html .= '<tr><td style="padding:2px; font-size:9px; font-weight:bold;">HOD Remarks:</td><td style="padding:2px; font-size:9px;"></td></tr>';
        $html .= '</table>';
        
        $html .= '<div style="clear:both; height:10px;"></div>';
        
        // Signature lines
        $html .= '<table border="0" cellpadding="2" style="width:100%; margin-top:15px;">';
        $html .= '<tr>';
        $html .= '<td style="width:33%; text-align:center; border-top:1px solid #333; padding:2px; font-size:9px;">Signature of the Student</td>';
        $html .= '<td style="width:33%; text-align:center; border-top:1px solid #333; padding:2px; font-size:9px;">Signature of the Mentor</td>';
        $html .= '<td style="width:33%; text-align:center; border-top:1px solid #333; padding:2px; font-size:9px;">Signature of the HOD</td>';
        $html .= '</tr>';
        $html .= '</table>';
    }

    // PDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_header' => 5,
        'margin_footer' => 5,
        'shrink_tables_to_fit' => 1,
        'keep_table_proportions' => false
    ]);
    
    $mpdf->SetCreator('Student Details System');
    $mpdf->SetAuthor('Student Details System');
    $mpdf->SetTitle('Official Student Report');
    $mpdf->SetSubject('Academic Report');
    $mpdf->SetKeywords('Student, Report, Academic');
    $mpdf->SetFont('helvetica', '', 10);
    $mpdf->WriteHTML($html);
    
    // Add footer with disclaimer
    $mpdf->SetFooter('<div style="font-size:8px; text-align:center; color:#666; border-top:1px solid #ccc; padding-top:5px;">* This is an unofficial view for convenience only. Final authority is the original/official records. We do not guarantee accuracy or completeness.</div>');

    $filename = 'student_report_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$rollNo) . '_' . date('Y-m-d') . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $mpdf->Output($filename, 'I');
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'PDF generation error', 'error' => $e->getMessage()]);
    exit;
}
