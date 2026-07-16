<?php
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

$year = '';
if (isset($_GET['year'])) {
    $year = trim((string)$_GET['year']);
}

$scope = '';
if (isset($_GET['scope'])) {
    $scope = strtolower(trim((string)$_GET['scope']));
}

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

try {
    $stmt = $pdo->query("SELECT roll_no, total_present, total_classes, percentage, session_name FROM attendance_summary WHERE CAST(percentage AS DECIMAL(10,3)) < 65 ORDER BY session_name DESC, roll_no ASC");
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

        $year = ($m <= 6) ? $yearEnd : $yearStart;
        return ($year * 100) + $m;
    };

    // If scope=last, filter to the latest month among below-65 rows
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
    foreach ($rows as $r) {
        $y = $toYearLabel($r['session_name'] ?? '');
        if ($year !== '' && $y !== $year) continue;
        if (!isset($yearGroups[$y])) $yearGroups[$y] = [];
        $rn = isset($r['roll_no']) ? trim((string)$r['roll_no']) : '';
        $r['student_name'] = ($rn !== '' && isset($nameMap[strtolower($rn)])) ? $nameMap[strtolower($rn)] : '';
        $yearGroups[$y][] = $r;
    }

    $years = array_keys($yearGroups);
    usort($years, function ($a, $b) {
        if ($a === 'Unknown' && $b !== 'Unknown') return 1;
        if ($b === 'Unknown' && $a !== 'Unknown') return -1;
        return strcmp($b, $a);
    });

    $html = '<h2 style="text-align:center; margin:0;">Below 65% Attendance Report</h2>';
    $html .= '<div style="text-align:center; font-size:10px; margin:6px 0 10px;">Generated: ' . date('d-M-Y h:i A') . '</div>';
    if ($scope === 'last') {
        $html .= '<div style="text-align:center; font-size:10px; margin:0 0 10px;"><b>Scope:</b> Last Month Only</div>';
    }
    if ($year !== '') {
        $html .= '<div style="text-align:center; font-size:10px; margin:0 0 10px;">Year: <b>' . htmlspecialchars($year) . '</b></div>';
    }

    $html .= '<style>
        body { font-family: sans-serif; font-size: 10px; }
        h2 { font-size: 16px; }
        h3 { font-size: 12px; margin: 12px 0 6px; }
        table { border-collapse: collapse; width: 100%; margin: 4px 0 10px; }
        th, td { border: 1px solid #333; padding: 4px; }
        th { background: #f1f3f6; }
        .text-end { text-align: right; }
        .pct { font-weight: bold; color: #dc3545; }
    </style>';

    if (empty($years)) {
        $html .= '<div style="text-align:center; margin-top:18px;">No records found.</div>';
    } else {
        foreach ($years as $y) {
            $items = $yearGroups[$y] ?? [];
            $html .= '<h3>' . htmlspecialchars($y) . ' (Total: ' . count($items) . ')</h3>';
            $html .= '<table>
                <thead>
                    <tr>
                        <th width="15%">ROLL NO</th>
                        <th width="25%">NAME</th>
                        <th width="12%" class="text-end">PRESENT</th>
                        <th width="12%" class="text-end">TOTAL</th>
                        <th width="12%" class="text-end">%</th>
                        <th width="12%" class="text-end">ABSENT</th>
                        <th width="14%">SESSION</th>
                        <th width="18%" class="text-end">FINE (₹)</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($items as $r) {
                $pctVal = isset($r['percentage']) ? (float)$r['percentage'] : 0;
                $pct = number_format($pctVal, 2) . '%';
                $totalClasses = (int)($r['total_classes'] ?? 0);
                $totalPresent = (int)($r['total_present'] ?? 0);
                $absent = $totalClasses - $totalPresent;
                $fine = max(0, $absent);
                $fineDisplay = $fine > 0 ? '₹' . $fine : '-';
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars(preg_replace('/pvp/i','***',(string)($r['roll_no'] ?? ''))) . '</td>';
                $html .= '<td>' . htmlspecialchars(preg_replace('/pvp/i','***',(string)($r['student_name'] ?? ''))) . '</td>';
                $html .= '<td class="text-end">' . htmlspecialchars((string)($r['total_present'] ?? '')) . '</td>';
                $html .= '<td class="text-end">' . htmlspecialchars((string)($r['total_classes'] ?? '')) . '</td>';
                $html .= '<td class="text-end pct">' . htmlspecialchars($pct) . '</td>';
                $html .= '<td class="text-end">' . htmlspecialchars($absent) . '</td>';
                $html .= '<td>' . htmlspecialchars(preg_replace('/pvp/i','***',(string)($r['session_name'] ?? ''))) . '</td>';
                $html .= '<td class="text-end">' . htmlspecialchars($fineDisplay) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }
    }

    $html .= '<div style="font-size:9px; color:#666; margin-top:6px;">* This is an unofficial view for convenience only. Final authority is the original/official records. We do not guarantee accuracy or completeness.</div>';

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 12,
    ]);

    $mpdf->WriteHTML($html);

    $fileName = 'below65_attendance_report' . ($year !== '' ? ('_' . preg_replace('/[^0-9A-Za-z_-]+/', '_', $year)) : '') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    $mpdf->Output($fileName, \Mpdf\Output\Destination::INLINE);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Failed to generate PDF: ' . $e->getMessage();
}
