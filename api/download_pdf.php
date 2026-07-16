<?php
/**
 * download_pdf.php
 * 
 * Generates PDF for student profile download
 * Uses mPDF library for PDF generation
 * Uses same data fetching logic as public_search.php
 */

session_start();
require_once '../config.php';

// Get roll number from GET parameter
$rollNumber = $_GET['roll'] ?? '';

if (empty($rollNumber)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Roll number is required']);
    exit;
}

try {
    // Use the same data fetching logic as public_search.php
    $rp = [$rollNumber, strtoupper($rollNumber), strtolower($rollNumber)];
    
    // Fetch student data
    $studentColumns = ['roll_no', 'student_name', 'father_name', 'date_of_birth', 'student_photo'];
    try {
        $availableStudentColumns = array_map(
            fn($r) => (string)($r['Field'] ?? ''),
            $pdo->query('DESCRIBE students')->fetchAll(PDO::FETCH_ASSOC)
        );
        foreach (['student_photo', 'photo_path', 'profile_photo', 'photo'] as $photoColumn) {
            if (in_array($photoColumn, $availableStudentColumns, true)) {
                $studentColumns[] = $photoColumn;
                break;
            }
        }
    } catch (Throwable $e) { /* keep basic student lookup working */ }
    $studentSelect = implode(', ', array_map(fn($col) => "`{$col}`", $studentColumns));
    $stmt = $pdo->prepare("SELECT {$studentSelect} FROM students WHERE roll_no IN (?,?,?) LIMIT 1");
    $stmt->execute($rp);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Add full photo path if student_photo exists
    if ($student && !empty($student['student_photo'])) {
        $student['student_photo'] = 'uploads/student_photos/' . $student['student_photo'];
    }

    if (!$student) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    // Fetch student results
    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM student_results WHERE roll_no IN (?,?,?) ORDER BY created_at DESC'
        );
        $stmt->execute($rp);
        $studentResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('student_results: '.$e->getMessage()); $studentResults = []; }

    // Fetch subject results with subject names
    try {
        $stmt = $pdo->prepare("
            SELECT sr.*, 
                   COALESCE(sc.subject_name, s.subject_name, sr.subject_name) as subject_name
            FROM subject_results sr
            LEFT JOIN student_courses sc ON sr.subject_code = sc.subject_code AND sr.roll_no = sc.roll_no
            LEFT JOIN subjects s ON sr.subject_code = s.subject_code
            WHERE sr.roll_no IN (?,?,?)
            ORDER BY sr.subject_code
        ");
        $stmt->execute($rp);
        $subjectResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('subject_results: '.$e->getMessage()); $subjectResults = []; }

    // Fetch attendance summary
    try {
        $stmt = $pdo->prepare(
            'SELECT s.*, u.file_name as upload_file_name, u.semester, u.academic_year, u.section, u.month_name
               FROM attendance_summary s
               LEFT JOIN attendance_uploads u ON s.session_name = u.session_name
              WHERE s.roll_no IN (?,?,?)
              ORDER BY u.uploaded_at ASC, s.session_name ASC'
        );
        $stmt->execute($rp);
        $attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('attendance_summary: '.$e->getMessage()); $attendanceData = []; }

    // Fetch attendance subjects
    try {
        $stmt = $pdo->prepare(
            'SELECT s.*, u.file_name as upload_file_name, u.semester, u.academic_year, u.section, u.month_name
               FROM attendance_subjects s
               LEFT JOIN attendance_uploads u ON s.session_name = u.session_name
              WHERE s.roll_no IN (?,?,?)
              ORDER BY u.uploaded_at ASC, s.session_name ASC, s.subject_code ASC'
        );
        $stmt->execute($rp);
        $subjectRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('attendance_subjects: '.$e->getMessage()); $subjectRows = []; }

    // Calculate overall attendance
    $totalPresent = 0;
    $totalClasses = 0;
    foreach ($attendanceData as $att) {
        $totalPresent += ($att['present'] ?? 0);
        $totalClasses += ($att['total'] ?? 0);
    }
    $attendancePercentage = $totalClasses > 0 ? round(($totalPresent / $totalClasses) * 100, 2) : 0;

    // Generate HTML for PDF
    $html = generatePDFHTML($student, $attendanceData, $subjectResults, $studentResults, $attendancePercentage, $totalPresent, $totalClasses, $subjectRows);

    // Initialize mPDF
    require_once '../vendor/autoload.php';
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_top' => 20,
        'margin_right' => 15,
        'margin_bottom' => 20,
        'margin_left' => 15,
    ]);

    // Add CSS
    $css = '
    body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.6; }
    .header { text-align: center; border-bottom: 2px solid #667eea; padding-bottom: 20px; margin-bottom: 20px; }
    .header h1 { color: #667eea; margin: 0; font-size: 24px; }
    .header p { color: #666; margin: 5px 0; }
    .student-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .info-row { display: flex; margin-bottom: 8px; }
    .info-label { font-weight: bold; width: 120px; color: #333; }
    .info-value { color: #555; }
    .section-title { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 15px; border-radius: 6px; margin: 20px 0 15px 0; font-size: 16px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    th { background: #667eea; color: white; padding: 10px; text-align: left; font-size: 11px; }
    td { padding: 8px 10px; border-bottom: 1px solid #ddd; font-size: 11px; }
    tr:nth-child(even) { background: #f9f9f9; }
    .attendance-summary { text-align: center; padding: 15px; background: #e8f5e9; border-radius: 8px; margin-top: 15px; }
    .attendance-summary strong { color: #2e7d32; font-size: 18px; }
    .footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; color: #999; font-size: 10px; }
    ';
    $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html);

    // Output PDF
    $filename = 'student_profile_' . $rollNumber . '_' . date('Y-m-d') . '.pdf';
    $mpdf->Output($filename, 'D');

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error generating PDF: ' . $e->getMessage()]);
    exit;
}

function generatePDFHTML($student, $attendanceData, $subjectResults, $studentResults, $attendancePercentage, $totalPresent, $totalClasses, $subjectRows) {
    $html = '
    <div class="header">
        <h1>PVPSIT Student Profile</h1>
        <p>Prakasam Vidya Jyothi Siddhartha Institute of Technology</p>
        <p>Generated on: ' . date('d M Y H:i') . '</p>
    </div>

    <div class="student-info">
        <div class="info-row">
            <span class="info-label">Roll Number:</span>
            <span class="info-value">' . htmlspecialchars($student['roll_no'] ?? 'N/A') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Name:</span>
            <span class="info-value">' . htmlspecialchars($student['student_name'] ?? 'N/A') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Father Name:</span>
            <span class="info-value">' . htmlspecialchars($student['father_name'] ?? 'N/A') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Date of Birth:</span>
            <span class="info-value">' . ($student['date_of_birth'] ? date('d M Y', strtotime($student['date_of_birth'])) : 'N/A') . '</span>
        </div>
    </div>

    <div class="section-title">Attendance Summary</div>
    <div class="attendance-summary">
        <strong>Overall Attendance: ' . $attendancePercentage . '%</strong>
        <p>Present: ' . $totalPresent . ' / Total: ' . $totalClasses . '</p>
    </div>';

    if (!empty($attendanceData)) {
        $html .= '
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Present</th>
                    <th>Total</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($attendanceData as $att) {
            $percentage = ($att['total'] ?? 0) > 0 ? round(($att['present'] / $att['total']) * 100, 2) : 0;
            // Extract month from session_name if month_name is not available
            $monthName = $att['month_name'] ?? 'Unknown';
            if ($monthName === 'Unknown' || empty($monthName)) {
                // Try to extract month from session_name
                if (preg_match('/(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/i', $att['session_name'] ?? '', $matches)) {
                    $monthMap = ['jan' => 'January', 'feb' => 'February', 'mar' => 'March', 'apr' => 'April', 
                                'may' => 'May', 'jun' => 'June', 'jul' => 'July', 'aug' => 'August', 
                                'sep' => 'September', 'oct' => 'October', 'nov' => 'November', 'dec' => 'December'];
                    $monthName = ucfirst($monthMap[strtolower($matches[1])]);
                }
            }
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($monthName) . '</td>
                    <td>' . ($att['present'] ?? 0) . '</td>
                    <td>' . ($att['total'] ?? 0) . '</td>
                    <td>' . $percentage . '%</td>
                </tr>';
        }
        $html .= '</tbody></table>';
    }

    if (!empty($subjectRows)) {
        $html .= '<div class="section-title">Subject-wise Attendance</div>
        <table>
            <thead>
                <tr>
                    <th>Subject Code</th>
                    <th>Present</th>
                    <th>Total</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($subjectRows as $row) {
            $percentage = ($row['total'] ?? 0) > 0 ? round(($row['present'] / $row['total']) * 100, 2) : 0;
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($row['subject_code'] ?? 'N/A') . '</td>
                    <td>' . ($row['present'] ?? 0) . '</td>
                    <td>' . ($row['total'] ?? 0) . '</td>
                    <td>' . $percentage . '%</td>
                </tr>';
        }
        $html .= '</tbody></table>';
    }

    if (!empty($subjectResults)) {
        $html .= '<div class="section-title">Subject Results</div>
        <table>
            <thead>
                <tr>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Credits</th>
                    <th>Grade</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($subjectResults as $result) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($result['subject_code'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($result['subject_name'] ?? 'N/A') . '</td>
                    <td>' . ($result['credits'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($result['grade'] ?? 'N/A') . '</td>
                </tr>';
        }
        $html .= '</tbody></table>';
    }

    if (!empty($studentResults)) {
        $html .= '<div class="section-title">Academic Results</div>
        <table>
            <thead>
                <tr>
                    <th>Semester</th>
                    <th>SGPA</th>
                    <th>CGPA</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($studentResults as $result) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($result['semester'] ?? 'N/A') . '</td>
                    <td>' . ($result['sgpa'] ?? 'N/A') . '</td>
                    <td>' . ($result['cgpa'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($result['status'] ?? 'N/A') . '</td>
                </tr>';
        }
        $html .= '</tbody></table>';
    }

    $html .= '
    <div class="footer">
        <p>This is an unofficial document for reference only.</p>
        <p>Final authority is the original/official records.</p>
        <p>Generated by PVPSIT Student Portal</p>
    </div>';

    return $html;
}

