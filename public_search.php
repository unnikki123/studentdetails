<?php
/**
 * public_search.php
 *
 * Attendance filename format:
 *   att_YYYY-YY_YEAR-SEM_SECTION_mmm
 *   att_2025-26_2-1_S2_oct
 *        └─AY─┘  │ │  │   └── month (3-letter)
 *                │ │  └─────── section  (S1/S2/A/B … display only, NOT a grouping key)
 *                │ └────────── semester (1 or 2)
 *                └──────────── college year (1/2/3/4)
 *
 * Returns attendance_groups[] grouped by:
 *   academic_year  →  year  →  semester  →  month
 *
 * Each group:
 *   academic_year, year, semester, section, month,
 *   session_name, file_name, summary[], subjects[]
 */

header('Content-Type: application/json');
require_once 'config.php';

/* ══════════════════════════════════════════════
   parseAttendanceFileName
   att_2025-26_2-1_S2_oct  →
     academic_year = "2025-26"
     year          = "2"
     semester      = "1"
     section       = "S2"
     month         = "Oct"
     raw_key       = sortable string "20252110"
══════════════════════════════════════════════ */
function parseAttendanceFileName(string $fileName): array
{
    $default = [
        'academic_year' => 'Unknown',
        'year'          => 'Unknown',
        'semester'      => 'Unknown',
        'section'       => '',
        'month'         => 'Unknown',
        'raw_key'       => 'zzz',
    ];

    if (empty($fileName)) return $default;

    $f = strtolower(trim($fileName));

    /*
     * Pattern A  —  WITH section token
     *   att_YYYY-YY_YEAR-SEM_SECTION_mmm
     *   att_2025-26_2-1_s2_oct
     *   Groups: 1=YYYY  2=YY  3=YEAR  4=SEM  5=SECTION  6=month
     */
    if (preg_match(
        '/att[_-](\d{4})[_-](\d{2,4})[_-](\d+)[_-](\d+)[_-]([a-z0-9]+)[_-]([a-z]{3})(?:[_-]|$)/',
        $f, $m
    )) {
        return [
            'academic_year' => "{$m[1]}-{$m[2]}",
            'year'          => $m[3],
            'semester'      => $m[4],
            'section'       => strtoupper($m[5]),
            'month'         => ucfirst($m[6]),
            'raw_key'       => "{$m[1]}{$m[3]}{$m[4]}" . sprintf('%02d', _monthNum($m[6])),
        ];
    }

    /*
     * Pattern B  —  NO section token
     *   att_YYYY-YY_YEAR-SEM_mmm
     *   att_2025-26_2-1_oct
     *   Groups: 1=YYYY  2=YY  3=YEAR  4=SEM  5=month
     */
    if (preg_match(
        '/att[_-](\d{4})[_-](\d{2,4})[_-](\d+)[_-](\d+)[_-]([a-z]{3})(?:[_-]|$)/',
        $f, $m
    )) {
        return [
            'academic_year' => "{$m[1]}-{$m[2]}",
            'year'          => $m[3],
            'semester'      => $m[4],
            'section'       => '',
            'month'         => ucfirst($m[5]),
            'raw_key'       => "{$m[1]}{$m[3]}{$m[4]}" . sprintf('%02d', _monthNum($m[5])),
        ];
    }

    /*
     * Pattern C  —  year only, no semester
     *   att_YYYY-YY_YEAR_mmm
     *   att_2025-26_2_oct
     */
    if (preg_match(
        '/att[_-](\d{4})[_-](\d{2,4})[_-](\d+)[_-]([a-z]{3})(?:[_-]|$)/',
        $f, $m
    )) {
        return [
            'academic_year' => "{$m[1]}-{$m[2]}",
            'year'          => $m[3],
            'semester'      => '1',
            'section'       => '',
            'month'         => ucfirst($m[4]),
            'raw_key'       => "{$m[1]}{$m[3]}1" . sprintf('%02d', _monthNum($m[4])),
        ];
    }

    /*
     * Pattern D  —  fallback: any YYYY-YY + 3-letter month
     */
    if (preg_match('/(\d{4})[_-](\d{2,4})/', $f, $ym)
     && preg_match('/(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/', $f, $mm)
    ) {
        return [
            'academic_year' => "{$ym[1]}-{$ym[2]}",
            'year'          => '1',
            'semester'      => '1',
            'section'       => '',
            'month'         => ucfirst($mm[1]),
            'raw_key'       => "{$ym[1]}11" . sprintf('%02d', _monthNum($mm[1])),
        ];
    }

    return $default;
}

function _monthNum(string $m): int
{
    return [
        'jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,
        'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12,
    ][strtolower(substr($m, 0, 3))] ?? 99;
}

function _attendanceYearFromSemester(?string $semester, string $fallback): string
{
    $semester = trim((string)$semester);
    if (preg_match('/^(\d+)\s*[-\/]/', $semester, $m)) {
        return $m[1];
    }
    return $fallback;
}

function _attendanceRawKey(string $academicYear, string $year, string $semester, string $month, string $fallback): string
{
    if ($academicYear === 'Unknown' || $year === 'Unknown' || $semester === 'Unknown' || $month === 'Unknown') {
        return $fallback;
    }

    $startYear = null;
    if (preg_match('/(20\d{2})/', $academicYear, $m)) {
        $startYear = $m[1];
    }

    $semPart = '1';
    if (preg_match('/[-\/]\s*(\d+)/', $semester, $m)) {
        $semPart = $m[1];
    } elseif (preg_match('/^\d+$/', $semester)) {
        $semPart = $semester;
    }

    return $startYear ? $startYear . $year . $semPart . sprintf('%02d', _monthNum($month)) : $fallback;
}

function _attendancePartsFromRow(array $row): array
{
    $fileName = (string)($row['upload_file_name'] ?? $row['file_name'] ?? '');
    $p = parseAttendanceFileName($fileName);

    $academicYear = trim((string)($row['academic_year'] ?? '')) ?: $p['academic_year'];
    $semester = trim((string)($row['semester'] ?? '')) ?: $p['semester'];
    $year = _attendanceYearFromSemester($semester, $p['year']);
    $month = trim((string)($row['month_name'] ?? '')) ?: $p['month'];
    $section = trim((string)($row['section'] ?? '')) ?: $p['section'];
    $rawKey = _attendanceRawKey($academicYear, $year, $semester, $month, $p['raw_key']);

    return [
        'academic_year' => $academicYear ?: 'Unknown',
        'year' => $year ?: 'Unknown',
        'semester' => $semester ?: 'Unknown',
        'section' => $section,
        'month' => $month ?: 'Unknown',
        'raw_key' => $rawKey,
        'file_name' => $fileName,
    ];
}

/* ══════════════════════════════════════════════
   groupAttendanceData
   Group key = academic_year | year | semester | month
   Section is stored per-group for display only —
   it does NOT split groups (same month, same sem,
   different sections → one group, section badge shown).
══════════════════════════════════════════════ */
function groupAttendanceData(array $summaryRows, array $subjectRows): array
{
    $groups       = [];
    $sessionToKey = [];

    /* Index summary rows */
    foreach ($summaryRows as $row) {
        $p = _attendancePartsFromRow($row);
        $academicYear = $p['academic_year'];
        $semester = $p['semester'];
        $year = $p['year'];
        $month = $p['month'];
        
        $key = "{$academicYear}|{$year}|{$semester}|{$month}";

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'academic_year' => $academicYear,
                'year'          => $year,
                'semester'      => $semester,
                'section'       => $p['section'],
                'month'         => $month,
                'raw_key'       => $p['raw_key'],
                'session_name'  => $row['session_name'] ?? '',
                'file_name'     => $p['file_name'],
                'summary'       => [],
                'subjects'      => [],
            ];
            $sessionToKey[$row['session_name'] ?? ''] = $key;
        }
        $groups[$key]['summary'][] = $row;
    }

    /* Attach subject rows */
    foreach ($subjectRows as $row) {
        $sess = (string)($row['session_name'] ?? '');
        $key  = $sessionToKey[$sess] ?? null;

        if ($key === null) {
            $p = _attendancePartsFromRow($row);
            $academicYear = $p['academic_year'];
            $semester = $p['semester'];
            $year = $p['year'];
            $month = $p['month'];
            
            $key = "{$academicYear}|{$year}|{$semester}|{$month}";

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'academic_year' => $academicYear,
                    'year'          => $year,
                    'semester'      => $semester,
                    'section'       => $p['section'],
                    'month'         => $month,
                    'raw_key'       => $p['raw_key'],
                    'session_name'  => $sess,
                    'file_name'     => $p['file_name'],
                    'summary'       => [],
                    'subjects'      => [],
                ];
                $sessionToKey[$sess] = $key;
            }
        }
        $groups[$key]['subjects'][] = $row;
    }

    /* Sort: academic_year → year → semester → month */
    uasort($groups, fn($a, $b) => strcmp($a['raw_key'], $b['raw_key']));

    return array_values($groups);
}

/* ══════════════════════════════════════════════
   MAIN REQUEST HANDLER
══════════════════════════════════════════════ */
// Allow GET requests for ranking modal (read-only operation)
$isRankingRequest = isset($_GET['ranking_type']) && ($_GET['ranking_type'] === 'overall' || $_GET['ranking_type'] === 'semester');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$isRankingRequest) {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$search_query = trim($_POST['roll_number'] ?? $_POST['search'] ?? '');
if ($search_query === '' && !$isRankingRequest) {
    echo json_encode(['success' => false, 'message' => 'Roll number or name is required']);
    exit;
}

// For ranking requests, get roll numbers from GET parameter
if ($isRankingRequest) {
    $search_query = trim($_GET['roll_numbers'] ?? '');
    if ($search_query === '') {
        echo json_encode(['success' => false, 'message' => 'Roll number is required for ranking request']);
        exit;
    }
}

try {
    $resolveLike = function (string $value): string {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    };

    $resolveStudentSearch = function (PDO $pdo, string $query) use ($resolveLike): array {
        $q = trim($query);
        if ($q === '') return [];

        $upper = strtoupper($q);
        $lower = strtolower($q);
        $rollParams = [$q, $upper, $lower];

        $results = [];

        // First try exact roll number match
        try {
            $stmt = $pdo->prepare('SELECT roll_no, student_name, student_photo FROM students WHERE roll_no IN (?,?,?) LIMIT 1');
            $stmt->execute($rollParams);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['roll_no'])) {
                // Add photo path if exists
                if (!empty($row['student_photo'])) {
                    $row['student_photo'] = 'uploads/student_photos/' . $row['student_photo'];
                }
                return [$row]; // Return single match for exact roll number
            }
        } catch (Throwable $e) { /* keep searching */ }

        // Try roll number in student_results
        try {
            $stmt = $pdo->prepare('SELECT roll_no, student_name FROM student_results WHERE roll_no IN (?,?,?) ORDER BY created_at DESC LIMIT 1');
            $stmt->execute($rollParams);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['roll_no'])) return [$row]; // Return single match for exact roll number
        } catch (Throwable $e) { /* keep searching */ }

        // For name searches, return all matches
        $likeExact = $q;
        $likePrefix = $resolveLike($q) . '%';
        $likeContains = '%' . $resolveLike($q) . '%';

        try {
            $stmt = $pdo->prepare(
                "SELECT roll_no, student_name, student_photo
                   FROM students
                  WHERE student_name = ?
                     OR student_name LIKE ? ESCAPE '\\\\'
                     OR student_name LIKE ? ESCAPE '\\\\'
                  ORDER BY
                    CASE
                      WHEN student_name = ? THEN 0
                      WHEN student_name LIKE ? ESCAPE '\\\\' THEN 1
                      ELSE 2
                    END,
                    student_name ASC
                  LIMIT 20"
            );
            $stmt->execute([$likeExact, $likePrefix, $likeContains, $likeExact, $likePrefix]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if (!empty($row['roll_no'])) {
                    // Add photo path if exists
                    if (!empty($row['student_photo'])) {
                        $row['student_photo'] = 'uploads/student_photos/' . $row['student_photo'];
                    }
                    $results[] = $row;
                }
            }
        } catch (Throwable $e) { /* keep searching */ }

        try {
            $stmt = $pdo->prepare(
                "SELECT sr.roll_no, sr.student_name
                   FROM student_results sr
                   INNER JOIN (
                        SELECT roll_no, MAX(created_at) AS max_created
                          FROM student_results
                         WHERE student_name = ?
                            OR student_name LIKE ? ESCAPE '\\\\'
                            OR student_name LIKE ? ESCAPE '\\\\'
                         GROUP BY roll_no
                   ) latest ON latest.roll_no = sr.roll_no AND latest.max_created = sr.created_at
                  ORDER BY
                    CASE
                      WHEN sr.student_name = ? THEN 0
                      WHEN sr.student_name LIKE ? ESCAPE '\\\\' THEN 1
                      ELSE 2
                    END,
                    sr.student_name ASC
                  LIMIT 20"
            );
            $stmt->execute([$likeExact, $likePrefix, $likeContains, $likeExact, $likePrefix]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if (!empty($row['roll_no'])) {
                    // Avoid duplicates
                    $exists = false;
                    foreach ($results as $r) {
                        if ($r['roll_no'] === $row['roll_no']) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) $results[] = $row;
                }
            }
        } catch (Throwable $e) { /* no match */ }

        return $results;
    };

    $resolvedStudent = $resolveStudentSearch($pdo, $search_query);
    if (!$resolvedStudent || empty($resolvedStudent)) {
        echo json_encode(['success' => false, 'message' => 'No student found for that roll number or name']);
        exit;
    }

    // If multiple students found (name search), return options for user to choose
    if (count($resolvedStudent) > 1) {
        echo json_encode([
            'success' => true,
            'multiple_matches' => true,
            'students' => $resolvedStudent,
            'search' => [
                'query' => $search_query,
                'count' => count($resolvedStudent)
            ]
        ]);
        exit;
    }

    // Single student found, proceed with normal flow
    $resolvedStudent = $resolvedStudent[0];
    $roll_number = trim((string)$resolvedStudent['roll_no']);
    $roll_upper = strtoupper($roll_number);
    $roll_lower = strtolower($roll_number);
    $rp         = [$roll_number, $roll_upper, $roll_lower];

    $response = [
        'success' => true,
        'data'    => [
            'student'           => null,
            'tables'            => [],
            'attendance_groups' => [],
            'search'            => [
                'query'       => $search_query,
                'resolved_to' => $roll_number,
            ],
        ],
    ];

    /* ── 1. Student info ─────────────────────────── */
    $studentColumns = ['id', 'roll_no', 'student_name', 'email', 'phone', 'date_of_birth', 'father_name', 'department', 'created_at'];
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
    
    if ($student) $response['data']['student'] = $student;

    /* ── 2. Student results ──────────────────────── */
    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM student_results WHERE roll_no IN (?,?,?) ORDER BY created_at DESC'
        );
        $stmt->execute($rp);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $response['data']['tables']['student_results'] = $rows;
            if (!$student && isset($rows[0]['student_name'])) {
                $response['data']['student'] = [
                    'roll_no'      => $roll_number,
                    'student_name' => $rows[0]['student_name'],
                ];
            }
        }
    } catch (PDOException $e) { error_log('student_results: '.$e->getMessage()); }

    /* ── 2b. Subject results / grades ───────────── */
    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM subject_results WHERE roll_no IN (?,?,?) ORDER BY subject_code'
        );
        $stmt->execute($rp);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) $response['data']['tables']['subject_results'] = $rows;
    } catch (PDOException $e) { error_log('subject_results: '.$e->getMessage()); }

    /* ── 3. Attendance summary ───────────────────── */
    $summaryRows = [];
    try {
        $stmt = $pdo->prepare(
            'SELECT s.*, u.file_name as upload_file_name, u.semester, u.academic_year, u.section, u.month_name
               FROM attendance_summary s
               LEFT JOIN attendance_uploads u ON s.session_name = u.session_name
              WHERE s.roll_no IN (?,?,?)
              ORDER BY u.uploaded_at ASC, s.session_name ASC'
        );
        $stmt->execute($rp);
        $summaryRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($summaryRows)
            $response['data']['tables']['attendance_summary'] = $summaryRows;
    } catch (PDOException $e) { error_log('attendance_summary: '.$e->getMessage()); }

    /* ── 4. Get student's enrolled courses per semester ─────────────────── */
    $enrolledCourses = [];
    try {
        $stmt = $pdo->prepare(
            'SELECT academic_year, year, semester, subject_code, subject_name
               FROM student_courses
              WHERE roll_no IN (?,?,?)'
        );
        $stmt->execute($rp);
        $enrollmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create lookup key: academic_year|semester -> array of subject_codes
        foreach ($enrollmentRows as $row) {
            $key = "{$row['academic_year']}|{$row['semester']}";
            if (!isset($enrolledCourses[$key])) {
                $enrolledCourses[$key] = [];
            }
            $enrolledCourses[$key][] = strtoupper($row['subject_code']);
        }
    } catch (PDOException $e) { error_log('student_courses: '.$e->getMessage()); }

    /* ── 4b. Attendance subjects ─────────────────── */
    $subjectRows = [];
    $filteredSubjectRows = [];
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
        
        // Filter subjects based on enrollment
        foreach ($subjectRows as $row) {
            $academicYear = $row['academic_year'] ?? '';
            $semester = $row['semester'] ?? '';
            $subjectCode = strtoupper($row['subject_code'] ?? '');
            
            $enrollmentKey = "{$academicYear}|{$semester}";
            
            // If no enrollment data exists for this semester, include all subjects (backward compatibility)
            // Otherwise, only include enrolled courses
            if (empty($enrolledCourses) || !isset($enrolledCourses[$enrollmentKey])) {
                $filteredSubjectRows[] = $row;
            } elseif (in_array($subjectCode, $enrolledCourses[$enrollmentKey])) {
                $filteredSubjectRows[] = $row;
            }
        }
        
        $subjectRows = $filteredSubjectRows;
        
        if ($subjectRows)
            $response['data']['tables']['attendance_subjects'] = $subjectRows;
    } catch (PDOException $e) { error_log('attendance_subjects: '.$e->getMessage()); }

    /* ── 4b. Build session to semester mapping from attendance_uploads ───────────── */
    $sessionToSemesterMap = [];
    try {
        $stmt = $pdo->query('SELECT session_name, semester, academic_year, file_name FROM attendance_uploads');
        $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($uploads as $u) {
            // Store both exact match and pattern match (without month suffix)
            $sessionToSemesterMap[$u['session_name']] = [
                'semester' => $u['semester'],
                'academic_year' => $u['academic_year'],
                'file_name' => $u['file_name']
            ];
            // Also store pattern without month (e.g., att_2025_26_2_2_s2)
            $basePattern = preg_replace('/_[a-z]{3}$/', '', $u['session_name']);
            $sessionToSemesterMap[$basePattern] = [
                'semester' => $u['semester'],
                'academic_year' => $u['academic_year'],
                'file_name' => $u['file_name']
            ];
        }
    } catch (PDOException $e) { error_log('attendance_uploads mapping: '.$e->getMessage()); }

    /* ── 4c. Apply semester mapping to summary and subject rows ─────────────────── */
    foreach ($summaryRows as &$row) {
        if (empty($row['semester']) || empty($row['academic_year'])) {
            $sess = $row['session_name'] ?? '';
            if (isset($sessionToSemesterMap[$sess])) {
                if (empty($row['semester'])) $row['semester'] = $sessionToSemesterMap[$sess]['semester'];
                if (empty($row['academic_year'])) $row['academic_year'] = $sessionToSemesterMap[$sess]['academic_year'];
            }
        }
        // Final fallback: parse from file_name
        if (empty($row['semester']) || empty($row['academic_year'])) {
            $fileNameToParse = $row['upload_file_name'] ?? $row['file_name'] ?? '';
            $parsed = parseAttendanceFileName((string)$fileNameToParse);
            if (empty($row['semester'])) $row['semester'] = $parsed['semester'];
            if (empty($row['academic_year'])) $row['academic_year'] = $parsed['academic_year'];
        }
    }
    unset($row);

    foreach ($subjectRows as &$row) {
        if (empty($row['semester']) || empty($row['academic_year'])) {
            $sess = $row['session_name'] ?? '';
            if (isset($sessionToSemesterMap[$sess])) {
                if (empty($row['semester'])) $row['semester'] = $sessionToSemesterMap[$sess]['semester'];
                if (empty($row['academic_year'])) $row['academic_year'] = $sessionToSemesterMap[$sess]['academic_year'];
            }
        }
        // Final fallback: parse from file_name
        if (empty($row['semester']) || empty($row['academic_year'])) {
            $fileNameToParse = $row['upload_file_name'] ?? $row['file_name'] ?? '';
            $parsed = parseAttendanceFileName((string)$fileNameToParse);
            if (empty($row['semester'])) $row['semester'] = $parsed['semester'];
            if (empty($row['academic_year'])) $row['academic_year'] = $parsed['academic_year'];
        }
    }
    unset($row);

    /* ── 4c. Build attendance_groups ─────────────── */
    if ($summaryRows || $subjectRows) {
        $response['data']['attendance_groups'] =
            groupAttendanceData($summaryRows, $subjectRows);
    }

    /* ── 4b. Uploads reference ───────────────────── */
    try {
        $stmt = $pdo->query(
            'SELECT DISTINCT file_name, session_name, uploaded_at
               FROM attendance_uploads
              ORDER BY uploaded_at DESC LIMIT 50'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) $response['data']['attendance_uploads'] = $rows;
    } catch (PDOException $e) { error_log('attendance_uploads: '.$e->getMessage()); }

    /* ── 5. Mid marks ────────────────────────────── */
    try {
        $desc     = $pdo->query('DESCRIBE `midmarks_subject_marks`')->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_map(fn($r) => strtolower((string)($r['Field'] ?? '')), $desc);
        $rollCol  = null;
        foreach (['roll_no','rollnumber','roll_number','roll'] as $c)
            if (in_array($c, $colNames, true)) { $rollCol = $c; break; }
        if ($rollCol) {
            $stmt = $pdo->prepare(
                "SELECT m.*, u.semester_info, u.academic_year, u.file_name
                   FROM `midmarks_subject_marks` m
                   LEFT JOIN `midmarks_uploads` u ON m.upload_id = u.id
                  WHERE m.`{$rollCol}` IN (?,?,?)
                  ORDER BY m.upload_id DESC, m.subject_code ASC LIMIT 500"
            );
            $stmt->execute($rp);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) $response['data']['tables']['midmarks_subject_marks'] = $rows;
        }
    } catch (Throwable $e) { /* table may not exist */ }

    /* ── 6. NPTEL Certificates ───────────────────── */
    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM nptel_certificates WHERE roll_no IN (?,?,?) ORDER BY uploaded_at DESC'
        );
        $stmt->execute($rp);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) $response['data']['tables']['nptel_certificates'] = $rows;
    } catch (PDOException $e) { error_log('nptel_certificates: '.$e->getMessage()); }

    /* ── 6. Legacy marks ─────────────────────────── */
    try {
        $stmt = $pdo->prepare(
            'SELECT m.*, s.student_name FROM marks m
               JOIN students s ON m.student_id = s.id
              WHERE s.roll_no IN (?,?,?)'
        );
        $stmt->execute($rp);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) $response['data']['tables']['marks'] = $rows;
    } catch (PDOException $e) { /* ignore */ }

    /* ── 7. Job Offers ───────────────────────────── */
    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM job_offers WHERE roll_no IN (?,?,?) ORDER BY offer_date DESC, upload_date DESC'
        );
        $stmt->execute($rp);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $response['data']['tables']['job_offers'] = $rows;

            // Calculate highest package for this student
            $studentHighestPackage = 0;
            foreach ($rows as $row) {
                $pkg = floatval($row['package']);
                if ($pkg > $studentHighestPackage) {
                    $studentHighestPackage = $pkg;
                }
            }

            // Get ranking - count students with higher packages
            $rankStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT roll_no) as higher_count
                FROM job_offers
                WHERE package > ?
            ");
            $rankStmt->execute([$studentHighestPackage]);
            $higherCount = $rankStmt->fetchColumn();
            $rank = $higherCount + 1;

            // Get total students with job offers
            $totalStmt = $pdo->query("SELECT COUNT(DISTINCT roll_no) as total FROM job_offers");
            $totalStudents = $totalStmt->fetchColumn();

            $response['data']['job_offers_rank'] = [
                'rank' => $rank,
                'total' => $totalStudents,
                'highest_package' => $studentHighestPackage
            ];
        }
    } catch (PDOException $e) { error_log('job_offers: '.$e->getMessage()); }

    /* ── 8. Test Assessment Results ───────────────── */
    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM test_assessment_results WHERE college_roll_number IN (?,?,?) ORDER BY test_date DESC, created_at DESC'
        );
        $stmt->execute($rp);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) $response['data']['tables']['test_assessment_results'] = $rows;
    } catch (PDOException $e) { error_log('test_assessment_results: '.$e->getMessage()); }

    /* ── 9. CGPA Rankings ─────────────────────────── */
    try {
        // Get student's CGPA from latest semester (same logic as leaderboard)
        $latestSemesterQuery = "WITH latest_semester AS (
                                 SELECT
                                   roll_no, student_name, cgpa, sgpa, semester_info,
                                   ROW_NUMBER() OVER (PARTITION BY roll_no ORDER BY
                                     CASE
                                       WHEN semester_info LIKE '%III Yr%' THEN 3
                                       WHEN semester_info LIKE '%II Yr%'  THEN 2
                                       WHEN semester_info LIKE '%I Yr%'   THEN 1
                                       ELSE 0
                                     END DESC,
                                     semester_info DESC
                                   ) as rn
                                 FROM student_results
                                 WHERE cgpa IS NOT NULL AND cgpa != '' AND cgpa != 'N/A'
                               )
                               SELECT roll_no, student_name, cgpa, sgpa, semester_info
                               FROM latest_semester
                               WHERE rn = 1 AND roll_no IN (?,?,?)";
        $stmt = $pdo->prepare($latestSemesterQuery);
        $stmt->execute($rp);
        $studentResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($studentResult && !empty($studentResult['cgpa']) && $studentResult['cgpa'] !== 'N/A') {
            $studentCGPA = floatval($studentResult['cgpa']);

            // Overall CGPA ranking - rank based on latest semester CGPA (same as leaderboard)
            $overallRankStmt = $pdo->prepare("
                WITH latest_semester AS (
                  SELECT
                    roll_no, cgpa,
                    ROW_NUMBER() OVER (PARTITION BY roll_no ORDER BY
                      CASE
                        WHEN semester_info LIKE '%III Yr%' THEN 3
                        WHEN semester_info LIKE '%II Yr%'  THEN 2
                        WHEN semester_info LIKE '%I Yr%'   THEN 1
                        ELSE 0
                      END DESC,
                      semester_info DESC
                    ) as rn
                  FROM student_results
                  WHERE cgpa IS NOT NULL AND cgpa != '' AND cgpa != 'N/A'
                )
                SELECT COUNT(DISTINCT roll_no) as higher_count
                FROM latest_semester
                WHERE rn = 1 AND CAST(cgpa AS DECIMAL(5,2)) > ?
            ");
            $overallRankStmt->execute([$studentCGPA]);
            $higherCount = $overallRankStmt->fetchColumn();
            $overallRank = $higherCount + 1;

            // Get total students with CGPA (latest semester only)
            $totalCGPAStmt = $pdo->query("
                WITH latest_semester AS (
                  SELECT
                    roll_no, cgpa,
                    ROW_NUMBER() OVER (PARTITION BY roll_no ORDER BY
                      CASE
                        WHEN semester_info LIKE '%III Yr%' THEN 3
                        WHEN semester_info LIKE '%II Yr%'  THEN 2
                        WHEN semester_info LIKE '%I Yr%'   THEN 1
                        ELSE 0
                      END DESC,
                      semester_info DESC
                    ) as rn
                  FROM student_results
                  WHERE cgpa IS NOT NULL AND cgpa != '' AND cgpa != 'N/A'
                )
                SELECT COUNT(DISTINCT roll_no) as total FROM latest_semester WHERE rn = 1
            ");
            $totalCGPAStudents = $totalCGPAStmt->fetchColumn();

            // Semester-wise ranking based on CGPA
            $semesterInfo = $studentResult['semester_info'] ?? '';
            $studentSGPA = !empty($studentResult['sgpa']) && $studentResult['sgpa'] !== 'N/A' ? floatval($studentResult['sgpa']) : null;

            $semesterRank = null;
            $totalSemesterStudents = null;

            if ($studentCGPA !== null && $semesterInfo) {
                $semesterRankStmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT roll_no) as higher_count
                    FROM student_results
                    WHERE semester_info = ? AND cgpa IS NOT NULL AND cgpa != '' AND cgpa != 'N/A' AND CAST(cgpa AS DECIMAL(5,2)) > ?
                ");
                $semesterRankStmt->execute([$semesterInfo, $studentCGPA]);
                $semesterHigherCount = $semesterRankStmt->fetchColumn();
                $semesterRank = $semesterHigherCount + 1;

                $totalSemesterStmt = $pdo->prepare("SELECT COUNT(DISTINCT roll_no) as total FROM student_results WHERE semester_info = ? AND cgpa IS NOT NULL AND cgpa != '' AND cgpa != 'N/A'");
                $totalSemesterStmt->execute([$semesterInfo]);
                $totalSemesterStudents = $totalSemesterStmt->fetchColumn();
            }

            $response['data']['cgpa_rank'] = [
                'overall_rank' => $overallRank,
                'overall_total' => $totalCGPAStudents,
                'cgpa' => $studentCGPA,
                'semester_rank' => $semesterRank,
                'semester_total' => $totalSemesterStudents,
                'sgpa' => $studentSGPA,
                'semester_info' => $semesterInfo
            ];
        }
    } catch (PDOException $e) { error_log('cgpa_rank: '.$e->getMessage()); }

    /* ── 10. Ranking List (for modal) ───────────────── */
    $rankingType = $_GET['ranking_type'] ?? '';
    if ($rankingType === 'overall' || $rankingType === 'semester') {
        try {
            if ($rankingType === 'overall') {
                // Get overall ranking list
                $rankingQuery = "WITH latest_semester AS (
                  SELECT
                    roll_no, student_name, cgpa, sgpa, semester_info,
                    ROW_NUMBER() OVER (PARTITION BY roll_no ORDER BY
                      CASE
                        WHEN semester_info LIKE '%III Yr%' THEN 3
                        WHEN semester_info LIKE '%II Yr%'  THEN 2
                        WHEN semester_info LIKE '%I Yr%'   THEN 1
                        ELSE 0
                      END DESC,
                      semester_info DESC
                    ) as rn
                  FROM student_results
                  WHERE cgpa IS NOT NULL AND cgpa != '' AND cgpa != 'N/A'
                )
                SELECT roll_no as college_roll_number, student_name as full_name, cgpa, sgpa
                FROM latest_semester
                WHERE rn = 1
                ORDER BY CAST(cgpa AS DECIMAL(5,2)) DESC";
                $stmt = $pdo->query($rankingQuery);
                $rankingList = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Get semester-wise ranking list (use the same semester as the current student)
                $semesterInfo = $studentResult['semester_info'] ?? '';
                if ($semesterInfo) {
                    $rankingQuery = "SELECT roll_no as college_roll_number, student_name as full_name, cgpa, sgpa
                                    FROM student_results
                                    WHERE semester_info = ? AND cgpa IS NOT NULL AND cgpa != '' AND cgpa != 'N/A'
                                    ORDER BY CAST(cgpa AS DECIMAL(5,2)) DESC";
                    $stmt = $pdo->prepare($rankingQuery);
                    $stmt->execute([$semesterInfo]);
                    $rankingList = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $rankingList = [];
                }
            }
            $response['data']['ranking_list'] = $rankingList;
        } catch (PDOException $e) { error_log('ranking_list: '.$e->getMessage()); }
    }

    /* ── 8. Auto-detect mid/internal tables ─────── */
    try {
        $allTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $skip = [
            'students','student_results','subject_results',
            'attendance_summary','attendance_subjects','attendance_uploads',
            'midmarks_subject_marks','midmarks_uploads','users','marks',
            'job_offers',
        ];
        $midTables = [];
        foreach ($allTables as $t) {
            $tn = strtolower((string)$t);
            if (in_array($tn, $skip, true)) continue;
            if (str_contains($tn,'mid') || str_contains($tn,'internal') || str_contains($tn,'cia'))
                $midTables[] = (string)$t;
        }
        foreach (array_slice($midTables, 0, 10) as $t) {
            try {
                $cols    = array_map(
                    fn($r) => strtolower((string)($r['Field'] ?? '')),
                    $pdo->query("DESCRIBE `{$t}`")->fetchAll(PDO::FETCH_ASSOC)
                );
                $rollCol = null;
                foreach (['roll_no','rollnumber','roll_number','roll','student_roll','student_id'] as $c)
                    if (in_array($c, $cols, true)) { $rollCol = $c; break; }
                if (!$rollCol) continue;
                $stmt = $pdo->prepare("SELECT * FROM `{$t}` WHERE `{$rollCol}` IN (?,?,?) LIMIT 200");
                $stmt->execute($rp);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if ($rows) $response['data']['tables'][$t] = $rows;
            } catch (Throwable $e) { /* skip bad tables */ }
        }
    } catch (Throwable $e) { /* ignore */ }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log('public_search error: '.$e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
