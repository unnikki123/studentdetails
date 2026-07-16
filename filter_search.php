<?php
require_once 'config.php';

header('Content-Type: application/json');

$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';

// Inputs
$semesterInfo = isset($_POST['semester_info']) ? trim((string)$_POST['semester_info']) : '';
$department = isset($_POST['department']) ? trim((string)$_POST['department']) : '';
$cgpaMin = isset($_POST['cgpa_min']) && $_POST['cgpa_min'] !== '' ? (float)$_POST['cgpa_min'] : null;
$cgpaMax = isset($_POST['cgpa_max']) && $_POST['cgpa_max'] !== '' ? (float)$_POST['cgpa_max'] : null;
$sgpaMin = isset($_POST['sgpa_min']) && $_POST['sgpa_min'] !== '' ? (float)$_POST['sgpa_min'] : null;
$sgpaMax = isset($_POST['sgpa_max']) && $_POST['sgpa_max'] !== '' ? (float)$_POST['sgpa_max'] : null;
$attMin = isset($_POST['attendance_min']) && $_POST['attendance_min'] !== '' ? (float)$_POST['attendance_min'] : null;
$attMax = isset($_POST['attendance_max']) && $_POST['attendance_max'] !== '' ? (float)$_POST['attendance_max'] : null;
$fCountMode = isset($_POST['f_count']) ? trim((string)$_POST['f_count']) : '';
$limit = isset($_POST['limit']) && $_POST['limit'] !== '' ? (int)$_POST['limit'] : 200;
if ($limit < 1) $limit = 1;
if ($limit > 500) $limit = 500;

// Interpret F count filter
$filterF = false;
$fMin = null;
$fMax = null;
if ($fCountMode !== '') {
    $filterF = true;
    if ($fCountMode === '0') {
        $fMin = 0;
        $fMax = 0;
    } elseif ($fCountMode === '1') {
        $fMin = 1;
        $fMax = 1;
    } elseif ($fCountMode === '2') {
        $fMin = 2;
        $fMax = 2;
    } elseif ($fCountMode === '3plus') {
        $fMin = 3;
        $fMax = null;
    }
}

try {
    $extractDepartment = function ($examInfo) {
        $s = trim((string)$examInfo);
        if ($s !== '') {
            $s = preg_replace('/pvp/i', '***', $s);
        }
        if ($s === '') return '';

        // Common pattern: "... Regular IT-PVP23 ..." => IT
        if (preg_match('/\bregular\s+([a-z]{2,10})\s*-/i', $s, $m)) {
            return strtoupper($m[1]);
        }

        // Pattern: "IT-PVP23" or "CSE-PVP23" (or masked variant) => IT/CSE
        if (preg_match('/\b([A-Z]{2,10})\s*-\s*(?:PVP|\*\*\*)\d{2,}/i', $s, $m)) {
            return strtoupper($m[1]);
        }

        // Fallback: take first all-caps token of length 2-10
        if (preg_match('/\b([A-Z]{2,10})\b/', $s, $m)) {
            return strtoupper($m[1]);
        }

        return '';
    };

    // Determine usable semester_info column
    $srSemColCandidates = ['semester_info', 'sem_info', 'semester', 'sem'];
    $subSemColCandidates = ['semester_info', 'sem_info', 'semester', 'sem'];

    $srSemCol = null;
    foreach ($srSemColCandidates as $c) {
        try {
            $pdo->query("SELECT `$c` FROM student_results LIMIT 1");
            $srSemCol = $c;
            break;
        } catch (Throwable $e) {
        }
    }

    $subSemCol = null;
    foreach ($subSemColCandidates as $c) {
        try {
            $pdo->query("SELECT `$c` FROM subject_results LIMIT 1");
            $subSemCol = $c;
            break;
        } catch (Throwable $e) {
        }
    }

    if ($srSemCol === null) {
        echo json_encode(['success' => false, 'message' => 'student_results semester info column not found']);
        exit;
    }

    // Dropdown options: return distinct semester_info values
    if ($action === 'semesters') {
        $stmt = $pdo->query("SELECT DISTINCT `$srSemCol` AS semester_info FROM student_results WHERE `$srSemCol` IS NOT NULL AND TRIM(`$srSemCol`) <> '' ORDER BY `$srSemCol` ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $values = array_values(array_filter(array_map(function ($r) {
            return isset($r['semester_info']) ? trim((string)$r['semester_info']) : '';
        }, $rows), function ($v) { return $v !== ''; }));
        echo json_encode(['success' => true, 'data' => $values]);
        exit;
    }

    // Dropdown options: return distinct departments parsed from exam_info
    if ($action === 'departments') {
        $sem = isset($_GET['semester_info']) ? trim((string)$_GET['semester_info']) : '';
        if ($sem === '') {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT DISTINCT exam_info FROM student_results WHERE `$srSemCol` = ? AND exam_info IS NOT NULL AND TRIM(exam_info) <> ''");
        $stmt->execute([$sem]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $deptSet = [];
        foreach ($rows as $r) {
            $d = $extractDepartment((string)($r['exam_info'] ?? ''));
            if ($d !== '') $deptSet[$d] = true;
        }
        $depts = array_keys($deptSet);
        sort($depts);
        echo json_encode(['success' => true, 'data' => $depts]);
        exit;
    }

    // Build main query (latest student_results per roll within semester_info)
    $params = [];
    $where = [];

    if ($semesterInfo !== '') {
        $where[] = "sr.`$srSemCol` = ?";
        $params[] = $semesterInfo;
    }

    if ($cgpaMin !== null) { $where[] = "CAST(sr.cgpa AS DECIMAL(10,3)) >= ?"; $params[] = $cgpaMin; }
    if ($cgpaMax !== null) { $where[] = "CAST(sr.cgpa AS DECIMAL(10,3)) <= ?"; $params[] = $cgpaMax; }
    if ($sgpaMin !== null) { $where[] = "CAST(sr.sgpa AS DECIMAL(10,3)) >= ?"; $params[] = $sgpaMin; }
    if ($sgpaMax !== null) { $where[] = "CAST(sr.sgpa AS DECIMAL(10,3)) <= ?"; $params[] = $sgpaMax; }

    if ($attMin !== null) { $where[] = "CAST(att.percentage AS DECIMAL(10,3)) >= ?"; $params[] = $attMin; }
    if ($attMax !== null) { $where[] = "CAST(att.percentage AS DECIMAL(10,3)) <= ?"; $params[] = $attMax; }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Subquery for latest record per roll+semester
    $latestWhere = [];
    $latestParams = [];
    if ($semesterInfo !== '') {
        $latestWhere[] = "`$srSemCol` = ?";
        $latestParams[] = $semesterInfo;
    }
    $latestWhereSql = $latestWhere ? ('WHERE ' . implode(' AND ', $latestWhere)) : '';

    // F-count subquery (join on semester if subject_results has a semester info column)
    $fcSelect = "SELECT roll_no";
    $fcGroupBy = " GROUP BY roll_no";
    $fcJoinExtra = '';
    if ($subSemCol !== null) {
        $fcSelect .= ", `$subSemCol` AS semv";
        $fcGroupBy = " GROUP BY roll_no, `$subSemCol`";
        $fcJoinExtra = " AND fc.semv = sr.`$srSemCol`";
    }
    $fcSelect .= ", SUM(CASE WHEN UPPER(TRIM(grade)) LIKE 'F%' THEN 1 ELSE 0 END) AS f_count FROM subject_results";
    $fcSql = $fcSelect . $fcGroupBy;

    // Attendance: join a single latest attendance_summary row per roll_no (prevents duplicate rows)
    $attLatestSql = "SELECT roll_no, MAX(id) AS max_id FROM attendance_summary GROUP BY roll_no";
    $attLatestJoinOn = "latest_att.roll_no = att.roll_no AND latest_att.max_id = att.id";

    $sql = "
        SELECT
            sr.roll_no,
            sr.student_name,
            sr.exam_info,
            sr.sgpa,
            sr.cgpa,
            sr.scr,
            sr.tcr,
            sr.`$srSemCol` AS semester_info,
            COALESCE(fc.f_count, 0) AS f_count,
            att.percentage AS attendance_percentage,
            att.session_name AS attendance_session
        FROM student_results sr
        INNER JOIN (
            SELECT roll_no, `$srSemCol` AS semv, MAX(created_at) AS max_created
            FROM student_results
            $latestWhereSql
            GROUP BY roll_no, `$srSemCol`
        ) latest
            ON latest.roll_no = sr.roll_no
            AND latest.semv = sr.`$srSemCol`
            AND latest.max_created = sr.created_at
        LEFT JOIN (
            $fcSql
        ) fc
            ON fc.roll_no = sr.roll_no$fcJoinExtra
        LEFT JOIN (
            $attLatestSql
        ) latest_att
            ON latest_att.roll_no = sr.roll_no
        LEFT JOIN attendance_summary att
            ON $attLatestJoinOn
        $whereSql
        ORDER BY CAST(sr.cgpa AS DECIMAL(10,3)) DESC, sr.roll_no ASC
        LIMIT $limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($latestParams, $params));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add department field derived from exam_info in student_results
    foreach ($rows as &$r) {
        $r['department'] = $extractDepartment((string)($r['exam_info'] ?? ''));
    }
    unset($r);

    if ($department !== '') {
        $depUpper = strtoupper($department);
        $rows = array_values(array_filter($rows, function ($r) use ($depUpper) {
            $d = isset($r['department']) ? strtoupper((string)$r['department']) : '';
            return $d === $depUpper;
        }));
    }

    // Apply F filter in PHP (safer due to dynamic join variations)
    if ($filterF && ($fMin !== null || $fMax !== null)) {
        $rows = array_values(array_filter($rows, function ($r) use ($fMin, $fMax) {
            $n = isset($r['f_count']) ? (int)$r['f_count'] : 0;
            if ($fMin !== null && $n < $fMin) return false;
            if ($fMax !== null && $n > $fMax) return false;
            return true;
        }));
    }

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Filter search failed', 'error' => $e->getMessage()]);
}
