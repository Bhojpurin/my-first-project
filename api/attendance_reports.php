<?php
// api/attendance_reports.php — monthly attendance register (students by class, or staff).
// All counting happens here: working days, weekly offs, holidays, totals, percentage, 75% flag.
//   classes   class / section combinations that have active students
//   register  one month for one class (or all staff)
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/attendance_features.php';
require_once __DIR__ . '/../includes/attendance_core.php';

if (empty($_SESSION['logged_in']) || !in_array($_SESSION['role'] ?? '', [ROLE_SCHOOL_ADMIN, ROLE_TEACHER], true)) {
    jsonResponse(false, 'Your session has expired. Please log in again.', [], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Invalid request method.');
if (!verifyCsrf($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Session expired. Please reload the page.', [], 401);

$schoolId = (int)$_SESSION['school_id'];
$pdo      = Database::connect();
$action   = $_POST['action'] ?? '';
$canStu   = hasPermission('attendance', 'view') || hasPermission('attendance', 'add') || hasPermission('attendance', 'edit');
$canStf   = hasPermission('staff_attendance', 'view') || hasPermission('staff_attendance', 'add') || hasPermission('staff_attendance', 'edit');

if ($action === 'classes') {
    if (!$canStu && !$canStf) jsonResponse(false, 'Permission denied.');
    $rows = [];
    if ($canStu) {
        $q = $pdo->prepare("
            SELECT s.class_id, COALESCE(s.section_id, 0) AS section_id,
                   CONCAT(COALESCE(c.class_name, 'No class'), COALESCE(CONCAT(' ', sec.section_name), '')) AS label,
                   COUNT(*) AS students
            FROM students s
            LEFT JOIN classes c    ON c.id = s.class_id
            LEFT JOIN sections sec ON sec.id = s.section_id
            WHERE s.school_id=? AND s.status='active'
            GROUP BY s.class_id, s.section_id, c.class_name, sec.section_name
            ORDER BY c.class_name, sec.section_name");
        $q->execute([$schoolId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = ['value' => (int)$r['class_id'] . ':' . (int)$r['section_id'], 'label' => $r['label'], 'count' => (int)$r['students']];
        }
    }
    jsonResponse(true, '', ['classes' => $rows, 'staff' => $canStf]);
}

if ($action === 'register') {
    $month = (string)($_POST['month'] ?? '');
    if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month, $m)) jsonResponse(false, 'Choose a month.');
    $first = "$month-01";
    $nDays = (int)date('t', strtotime($first));
    $last  = "$month-" . str_pad((string)$nDays, 2, '0', STR_PAD_LEFT);
    $today = date('Y-m-d');
    if ($first > $today) jsonResponse(false, 'This month has not started yet.');

    $FT  = attFeatures($pdo, $schoolId);
    $who = (string)($_POST['class'] ?? '');

    if ($who === 'staff') {
        if (!$canStf) jsonResponse(false, 'Permission denied.');
        $stfCol = attCodeColumn($pdo, 'teachers', $FT['staff_code_field'], ATT_STAFF_CODE_FIELDS, 'employee_code');
        $q = $pdo->prepare("SELECT t.id, u.name, t.`{$stfCol}` AS code FROM teachers t JOIN users u ON u.id = t.user_id
                            WHERE t.school_id=? AND t.status='active' ORDER BY u.name");
        $q->execute([$schoolId]);
        $people = $q->fetchAll(PDO::FETCH_ASSOC);
        $table = 'staff_attendance'; $col = 'teacher_id'; $title = 'Staff';
    } else {
        if (!$canStu) jsonResponse(false, 'Permission denied.');
        if (!preg_match('/^(\d+):(\d+)$/', $who, $cm)) jsonResponse(false, 'Choose a class.');
        $stuCol = attCodeColumn($pdo, 'students', $FT['student_code_field'], ATT_STUDENT_CODE_FIELDS, 'admission_no');
        $secSql = (int)$cm[2] > 0 ? 's.section_id = ?' : '(s.section_id IS NULL OR s.section_id = 0)';
        $args = [$schoolId, (int)$cm[1]]; if ((int)$cm[2] > 0) $args[] = (int)$cm[2];
        $q = $pdo->prepare("SELECT s.id, s.name, s.`{$stuCol}` AS code FROM students s
                            WHERE s.school_id=? AND s.status='active' AND s.class_id = ? AND $secSql ORDER BY s.name");
        $q->execute($args);
        $people = $q->fetchAll(PDO::FETCH_ASSOC);
        $l = $pdo->prepare("SELECT CONCAT(COALESCE(c.class_name,'No class'), COALESCE(CONCAT(' ', sec.section_name), ''))
                            FROM (SELECT 1) x LEFT JOIN classes c ON c.id = ? AND c.school_id = ? LEFT JOIN sections sec ON sec.id = ?");
        $l->execute([(int)$cm[1], $schoolId, (int)$cm[2]]);
        $title = (string)$l->fetchColumn();
        $table = 'attendance'; $col = 'student_id';
    }

    // Days of the month with their type.
    $rules = AttCore::rules($pdo, $schoolId);
    $h = $pdo->prepare("SELECT date FROM school_holidays WHERE school_id=? AND date BETWEEN ? AND ?");
    $h->execute([$schoolId, $first, $last]);
    $hol = array_flip($h->fetchAll(PDO::FETCH_COLUMN));
    $days = []; $working = 0;
    for ($d = 1; $d <= $nDays; $d++) {
        $date = sprintf('%s-%02d', $month, $d);
        $off = AttCore::isWeeklyOff($date, $rules['weekly_off']) ? 'W' : (isset($hol[$date]) ? 'H' : '');
        $future = $date > $today;
        if (!$off && !$future) $working++;
        $days[] = ['d' => $d, 'date' => $date, 'dow' => date('D', strtotime($date)), 'off' => $off, 'future' => $future];
    }

    $marks = [];
    if ($people) {
        $ids = array_column($people, 'id');
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $a = $pdo->prepare("SELECT $col AS pid, date, status FROM $table WHERE school_id=? AND $col IN ($ph) AND date BETWEEN ? AND ?");
            $a->execute([$schoolId, ...$chunk, $first, $last]);
            foreach ($a->fetchAll(PDO::FETCH_ASSOC) as $r) $marks[(int)$r['pid']][$r['date']] = $r['status'];
        }
    }

    $short = ['present' => 'P', 'late' => 'P', 'half_day' => '½', 'absent' => 'A', 'leave' => 'Lv'];
    $rows = []; $dayPresent = array_fill(1, $nDays, 0);
    foreach ($people as $p) {
        $pid = (int)$p['id']; $cells = []; $t = ['present' => 0.0, 'absent' => 0, 'leave' => 0, 'late' => 0, 'marked' => 0];
        foreach ($days as $day) {
            $st = $marks[$pid][$day['date']] ?? '';
            $cells[] = $st !== '' ? $short[$st] ?? '?' : ($day['off'] ?: '');
            if ($st === '') continue;
            $t['marked']++;
            if ($st === 'present' || $st === 'late') { $t['present'] += 1; $dayPresent[$day['d']]++; }
            if ($st === 'half_day') { $t['present'] += 0.5; $dayPresent[$day['d']]++; }
            if ($st === 'late') $t['late']++;
            if ($st === 'absent') $t['absent']++;
            if ($st === 'leave') $t['leave']++;
        }
        $pct = $t['marked'] ? (int)floor($t['present'] * 100 / $t['marked']) : null;
        $rows[] = ['id' => $pid, 'name' => $p['name'], 'code' => (string)($p['code'] ?? ''), 'cells' => $cells,
                   'present' => $t['present'], 'absent' => $t['absent'], 'leave' => $t['leave'], 'late' => $t['late'],
                   'marked' => $t['marked'], 'pct' => $pct, 'low' => $pct !== null && $t['marked'] >= 10 && $pct < 75];
    }

    $sn = $pdo->prepare("SELECT school_name FROM schools WHERE id=?");
    $sn->execute([$schoolId]);
    jsonResponse(true, '', [
        'school'      => (string)($sn->fetchColumn() ?: ''),
        'title'       => $title,
        'month_label' => date('F Y', strtotime($first)),
        'days'        => $days,
        'working'     => $working,
        'rows'        => $rows,
        'day_present' => array_values($dayPresent),
    ]);
}

jsonResponse(false, 'Unknown action.');
