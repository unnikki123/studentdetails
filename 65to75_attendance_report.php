<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $scope = isset($_GET['scope']) ? strtolower(trim((string)$_GET['scope'])) : '';
    $stmt = $pdo->query("SELECT roll_no, total_present, total_classes, percentage, session_name FROM attendance_summary WHERE CAST(percentage AS DECIMAL(10,3)) >= 65 AND CAST(percentage AS DECIMAL(10,3)) <= 75 ORDER BY session_name DESC, roll_no ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sessionToKey = function ($s) {
        $str = strtolower(trim((string)$s));
        if ($str === '') return null;

        $monthMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];

        $mon = null;
        if (preg_match_all('/(?:^|_)(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)(?=$|_)/', $str, $mm)) {
            if (!empty($mm[1])) $mon = end($mm[1]);
        }

        if (!$mon || !isset($monthMap[$mon])) return null;
        $m = (int)$monthMap[$mon];

        $yearStart = null;
        $yearEnd = null;
        if (preg_match('/(20\d{2})\s*[_-]\s*(\d{2}|20\d{2})/', $str, $yr)) {
            $yearStart = (int)$yr[1];
            $y2raw = $yr[2];
            $yearEnd = (strlen((string)$y2raw) === 2) ? (2000 + (int)$y2raw) : (int)$y2raw;
        } elseif (preg_match('/(20\d{2})/', $str, $ys)) {
            $yearStart = (int)$ys[1];
        }

        if (!is_int($yearStart) || $yearStart < 2000) return null;
        if (!is_int($yearEnd) || $yearEnd < 2000) $yearEnd = $yearStart + 1;

        // Academic year spans mid-year; months 1-6 belong to yearEnd, 7-12 belong to yearStart
        $year = ($m <= 6) ? $yearEnd : $yearStart;
        return ($year * 100) + $m;
    };

    // If scope=last, filter to the latest month among 65-75 rows
    $latestKey = null;
    if ($scope === 'last') {
        foreach ($rows as $r) {
            $k = $sessionToKey($r['session_name'] ?? '');
            if ($k === null) continue;
            if ($latestKey === null || $k > $latestKey) $latestKey = $k;
        }

        if ($latestKey !== null) {
            $rows = array_values(array_filter($rows, function ($r) use ($sessionToKey, $latestKey) {
                $k = $sessionToKey($r['session_name'] ?? '');
                return $k !== null && $k === $latestKey;
            }));
        } else {
            $rows = [];
        }
    }

    // Build roll_no -> student_name map (students table first, then fallback to latest student_results)
    $nameMap = [];
    $rolls = [];
    foreach ($rows as $r) {
        $rn = isset($r['roll_no']) ? trim((string)$r['roll_no']) : '';
        if ($rn !== '') $rolls[] = $rn;
    }
    $rolls = array_values(array_unique($rolls));

    $buildInClause = function (array $vals) {
        return implode(',', array_fill(0, count($vals), '?'));
    };

    if (!empty($rolls)) {
        // Detect students columns (schema differs across environments)
        $studentRollCol = null;
        $studentNameCol = null;
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_map(function ($c) { return strtolower((string)($c['Field'] ?? '')); }, $cols);
            if (in_array('roll_no', $colNames, true)) $studentRollCol = 'roll_no';
            else if (in_array('roll_number', $colNames, true)) $studentRollCol = 'roll_number';

            if (in_array('student_name', $colNames, true)) $studentNameCol = 'student_name';
            else if (in_array('name', $colNames, true)) $studentNameCol = 'name';
        } catch (Throwable $e) {
            $studentRollCol = null;
            $studentNameCol = null;
        }

        if ($studentRollCol && $studentNameCol) {
            try {
                $in = $buildInClause($rolls);
                $stmtN = $pdo->prepare("SELECT `$studentRollCol` AS roll_no, `$studentNameCol` AS student_name FROM students WHERE `$studentRollCol` IN ($in)");
                $stmtN->execute($rolls);
                $nr = $stmtN->fetchAll(PDO::FETCH_ASSOC);
                foreach ($nr as $nrow) {
                    $k = isset($nrow['roll_no']) ? trim((string)$nrow['roll_no']) : '';
                    $v = isset($nrow['student_name']) ? trim((string)$nrow['student_name']) : '';
                    if ($k !== '' && $v !== '') $nameMap[strtolower($k)] = $v;
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        // Fallback: latest student_results.student_name for any rolls still missing
        try {
            $missing = [];
            foreach ($rolls as $rn) {
                if (!isset($nameMap[strtolower($rn)])) $missing[] = $rn;
            }
            if (!empty($missing)) {
                $in = $buildInClause($missing);
                $sql = "
                    SELECT sr.roll_no, sr.student_name
                    FROM student_results sr
                    INNER JOIN (
                        SELECT roll_no, MAX(created_at) AS max_created
                        FROM student_results
                        WHERE roll_no IN ($in)
                        GROUP BY roll_no
                    ) latest
                    ON latest.roll_no = sr.roll_no AND latest.max_created = sr.created_at
                ";
                $stmtSR = $pdo->prepare($sql);
                $stmtSR->execute($missing);
                $nr = $stmtSR->fetchAll(PDO::FETCH_ASSOC);
                foreach ($nr as $nrow) {
                    $k = isset($nrow['roll_no']) ? trim((string)$nrow['roll_no']) : '';
                    $v = isset($nrow['student_name']) ? trim((string)$nrow['student_name']) : '';
                    if ($k !== '' && $v !== '') $nameMap[strtolower($k)] = $v;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    $yearGroups = [];

    $toYearLabel = function ($sessionName) {
        $s = strtolower(trim((string)$sessionName));

        if (preg_match('/(20\d{2})\s*[_-]\s*(\d{2}|20\d{2})/', $s, $m)) {
            $y1 = (int)$m[1];
            $y2raw = $m[2];
            $y2 = (strlen((string)$y2raw) === 2) ? (2000 + (int)$y2raw) : (int)$y2raw;
            return sprintf('%d-%02d', $y1, (int)substr((string)$y2, -2));
        }

        if (preg_match('/(20\d{2})/', $s, $m)) {
            $y1 = (int)$m[1];
            return sprintf('%d-%02d', $y1, (int)substr((string)($y1 + 1), -2));
        }

        return 'Unknown';
    };

    foreach ($rows as $r) {
        $year = $toYearLabel($r['session_name'] ?? '');
        $rn = isset($r['roll_no']) ? trim((string)$r['roll_no']) : '';
        $r['student_name'] = ($rn !== '' && isset($nameMap[strtolower($rn)])) ? $nameMap[strtolower($rn)] : '';

        if (isset($r['session_name']) && $r['session_name'] !== null) {
            $r['session_name'] = preg_replace('/pvp/i', '***', (string)$r['session_name']);
        }
        if (isset($r['roll_no']) && $r['roll_no'] !== null) {
            $r['roll_no'] = preg_replace('/pvp/i', '***', (string)$r['roll_no']);
        }
        if (isset($r['student_name']) && $r['student_name'] !== null) {
            $r['student_name'] = preg_replace('/pvp/i', '***', (string)$r['student_name']);
        }

        // Calculate fine (₹1 per absent hour)
        $totalClasses = (int)($r['total_classes'] ?? 0);
        $totalPresent = (int)($r['total_present'] ?? 0);
        $absent = max(0, $totalClasses - $totalPresent);
        $r['fine'] = $absent;
        
        if (!isset($yearGroups[$year])) $yearGroups[$year] = [];
        $yearGroups[$year][] = $r;
    }

    // Sort years descending (latest first), keep Unknown last
    $years = array_keys($yearGroups);
    usort($years, function ($a, $b) {
        if ($a === 'Unknown' && $b !== 'Unknown') return 1;
        if ($b === 'Unknown' && $a !== 'Unknown') return -1;
        return strcmp($b, $a);
    });

    $sortedGroups = [];
    foreach ($years as $y) {
        $sortedGroups[$y] = $yearGroups[$y];
    }

    echo json_encode([
        'success' => true,
        'total' => count($rows),
        'scope' => ($scope === 'last') ? 'last' : 'all',
        'latest_key' => $latestKey,
        'years' => $sortedGroups,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load 65-75 report',
        'error' => $e->getMessage(),
    ]);
}
