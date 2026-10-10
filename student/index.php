<?php
require_once __DIR__ . '/stu_guard.php';
stuGuard();

$info = stuInfo();
$pdo  = Database::connect();
$stuId    = (int)$info['id'];
$schoolId = (int)$info['school_id'];

// ── Full student data + school info ──────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT s.*, c.class_name, sec.section_name,
           sc.school_name, sc.logo AS school_logo, sc.phone AS school_phone,
           sc.email AS school_email, sc.address AS school_address,
           sc.city AS school_city, sc.state AS school_state
    FROM students s
    LEFT JOIN classes  c   ON c.id   = s.class_id
    LEFT JOIN sections sec ON sec.id  = s.section_id
    LEFT JOIN schools  sc  ON sc.id   = s.school_id
    WHERE s.id = ? AND s.school_id = ?
    LIMIT 1
");
$stmt->execute([$stuId, $schoolId]);
$stu = $stmt->fetch();
if (!$stu) { session_destroy(); header('Location: ' . BASE_URL . '/student/login'); exit; }

$schoolLogoUrl = !empty($stu['school_logo'])
    ? BASE_URL . '/assets/uploads/logos/' . $stu['school_logo']
    : '';
$schoolAddr = trim(implode(', ', array_filter([
    $stu['school_address'] ?? '',
    $stu['school_city']    ?? '',
    $stu['school_state']   ?? '',
])));

// ── Attendance stats — defaults to the current academic year, but a parent/
//    student can pick any past session they attended at this school via
//    ?att_year=YYYY (still their own record only — scoped by student_id below) ──
$currentAcadYear = (date('n') >= 4) ? (int)date('Y') : (int)date('Y') - 1;
$attYearOptions  = [$currentAcadYear]; // always offer the current year even with zero records yet
try {
    $ayQ = $pdo->prepare("
        SELECT DISTINCT (CASE WHEN MONTH(date) >= 4 THEN YEAR(date) ELSE YEAR(date) - 1 END) AS acad_yr
        FROM attendance WHERE student_id=? AND school_id=? ORDER BY acad_yr DESC
    ");
    $ayQ->execute([$stuId, $schoolId]);
    foreach ($ayQ->fetchAll(PDO::FETCH_COLUMN) as $ay) if (!in_array((int)$ay, $attYearOptions, true)) $attYearOptions[] = (int)$ay;
    rsort($attYearOptions);
} catch (\Throwable $e) {}

$acadYear = $currentAcadYear;
if (isset($_GET['att_year']) && in_array((int)$_GET['att_year'], $attYearOptions, true)) {
    $acadYear = (int)$_GET['att_year'];
}
$yearStart = $acadYear . '-04-01';
$yearEnd   = ($acadYear + 1) . '-03-31';

// ── Holidays for this range (so a holiday day shows as "Holiday", not a blank/
//    ambiguous cell — and so the % below can defensively exclude any holiday-
//    dated attendance row, even though none should normally exist) ──
$attHolidays = [];
try {
    $hStmt = $pdo->prepare("SELECT date, reason, type FROM school_holidays WHERE school_id=? AND date BETWEEN ? AND ?");
    $hStmt->execute([$schoolId, $yearStart, $yearEnd]);
    foreach ($hStmt->fetchAll(PDO::FETCH_ASSOC) as $h) $attHolidays[$h['date']] = $h;
} catch (\Throwable $e) {}

$attStats  = ['present' => 0, 'absent' => 0, 'late' => 0, 'half_day' => 0, 'leave' => 0, 'total' => 0];
$attRecent = [];
try {
    $aStmt = $pdo->prepare("
        SELECT date, status FROM attendance
        WHERE student_id=? AND school_id=? AND date BETWEEN ? AND ?
        ORDER BY date DESC
    ");
    $aStmt->execute([$stuId, $schoolId, $yearStart, $yearEnd]);
    $attRowsAll = $aStmt->fetchAll();
    // Never let a holiday or Sunday count toward the percentage, even if a
    // stray attendance row exists for one (e.g. a make-up class was held).
    $attRows = array_values(array_filter($attRowsAll, function ($r) use ($attHolidays) {
        if (isset($attHolidays[$r['date']])) return false;
        if ((int)date('N', strtotime($r['date'])) === 7) return false; // Sunday
        return true;
    }));
    $attStats['total'] = count($attRows);
    foreach ($attRows as $r) { if (isset($attStats[$r['status']])) $attStats[$r['status']]++; }
    $attRecent = array_slice($attRows, 0, 30);
} catch (\Throwable $e) {}

$attPct = $attStats['total'] > 0 ? round(($attStats['present'] + $attStats['late'] * 0.5) / $attStats['total'] * 100, 1) : 0;

// ── Fee summary ───────────────────────────────────────────────────────────────
$feeSummary = ['total' => 0, 'paid' => 0, 'due' => 0];
$feeRows    = [];
try {
    $fStmt = $pdo->prepare("
        SELECT fl.period_label, fl.period_date, fl.amount, fl.paid_amount, fl.status, fl.due_date,
               COALESCE(ft.name, 'Fee') AS fee_type_name
        FROM fee_ledger fl
        LEFT JOIN fee_types ft ON ft.id = fl.fee_type_id
        WHERE fl.student_id=? AND fl.school_id=?
        ORDER BY fl.period_date DESC
        LIMIT 24
    ");
    $fStmt->execute([$stuId, $schoolId]);
    $feeRows = $fStmt->fetchAll();
} catch (\Throwable $e) {}

// One-off manual charges (exam fee, trip fee, lost-book fine, etc.) live in a
// separate table and were never queried here — the school panel shows them,
// but students never saw them, making paid/partial manual charges invisible.
try {
    $mStmt = $pdo->prepare("
        SELECT description, charge_date, total_amount, paid_amount, status
        FROM fee_manual_charges
        WHERE student_id=? AND school_id=?
        ORDER BY charge_date DESC
        LIMIT 24
    ");
    $mStmt->execute([$stuId, $schoolId]);
    foreach ($mStmt->fetchAll() as $mc) {
        $feeRows[] = [
            'fee_type_name' => $mc['description'],
            'period_label'  => '',
            'period_date'   => $mc['charge_date'],
            'amount'        => $mc['total_amount'],
            'paid_amount'   => $mc['paid_amount'],
            'status'        => $mc['status'],
            'due_date'      => null,
        ];
    }
} catch (\Throwable $e) {}

usort($feeRows, fn($a, $b) => strcmp((string)$b['period_date'], (string)$a['period_date']));
foreach ($feeRows as $r) {
    $feeSummary['total'] += $r['amount'];
    $feeSummary['paid']  += $r['paid_amount'];
}
$feeSummary['due'] = round($feeSummary['total'] - $feeSummary['paid'], 2);

// ── Online payment config ─────────────────────────────────────────────────────
$payEnabled = false;
$payKeyId   = '';
try {
    require_once __DIR__ . '/../includes/payment_helper.php';
    $ps = payGetSettings($pdo, $schoolId);
    if ($ps['is_enabled'] && !empty($ps['key_id'])) {
        $payEnabled = true;
        $payKeyId   = $ps['key_id'];
    }
} catch (\Throwable $e) {}

// ── Library ───────────────────────────────────────────────────────────────────
$libIssues = []; $libStats = ['issued'=>0,'returned'=>0,'overdue'=>0];
try {
    $lStmt = $pdo->prepare("
        SELECT li.id, li.issue_date, li.expected_return_date AS due_date,
               li.actual_return_date AS return_date, li.status, li.notes,
               lb.title, lb.author, lb.isbn, lb.rack_no,
               DATEDIFF(CURDATE(), li.expected_return_date) AS days_overdue
        FROM library_issues li
        JOIN library_books lb ON lb.id = li.book_id
        WHERE li.student_id=? AND li.school_id=? AND li.borrower_type='student'
        ORDER BY li.issue_date DESC
    ");
    $lStmt->execute([$stuId, $schoolId]);
    $libIssues = $lStmt->fetchAll();
    foreach ($libIssues as $r) {
        if ($r['status'] === 'returned') $libStats['returned']++;
        elseif ($r['status'] === 'overdue' || ($r['days_overdue'] > 0 && $r['status'] !== 'returned')) $libStats['overdue']++;
        else $libStats['issued']++;
    }
} catch (\Throwable $e) {}

// ── School Store — only queried/rendered at all when the school has this
//    turned ON; when off, $storeEnabled stays false and the "Store" nav
//    entry + panel are omitted entirely further down (not just hidden by
//    CSS) so a disabled store leaves no trace in the page, not even its name ──
$storeEnabled  = false;
$storeProducts = [];
try {
    $stEn = $pdo->prepare("SELECT setting_val FROM school_settings WHERE school_id=? AND setting_key='store_enabled' LIMIT 1");
    $stEn->execute([$schoolId]);
    $storeEnabled = $stEn->fetchColumn() === '1';
    if ($storeEnabled) {
        $spStmt = $pdo->prepare("
            SELECT sp.id, sp.name, sp.price, sp.mrp, sp.images, sp.stock
            FROM school_store_selections sss
            JOIN store_products sp ON sp.id = sss.product_id AND sp.is_active = 1
            WHERE sss.school_id = ? AND sss.is_active = 1
            ORDER BY sss.selected_at DESC LIMIT 60
        ");
        $spStmt->execute([$schoolId]);
        $storeProducts = $spStmt->fetchAll();
    }
} catch (\Throwable $e) {}

// ── Siblings ──────────────────────────────────────────────────────────────────
$siblings = [];
try {
    $fgStmt = $pdo->prepare("SELECT family_group_id FROM students WHERE id=? AND school_id=?");
    $fgStmt->execute([$stuId, $schoolId]);
    $fgRow = $fgStmt->fetch();
    $familyGroupId = $fgRow ? (int)$fgRow['family_group_id'] : 0;
    if ($familyGroupId) {
        $sibStmt = $pdo->prepare("
            SELECT s.id, s.name, s.admission_no, s.photo, s.status, s.dob, s.gender,
                   c.class_name, sec.section_name
            FROM students s
            LEFT JOIN classes  c   ON c.id   = s.class_id
            LEFT JOIN sections sec ON sec.id  = s.section_id
            WHERE s.family_group_id=? AND s.school_id=? AND s.id!=?
            ORDER BY s.class_id, s.name
        ");
        $sibStmt->execute([$familyGroupId, $schoolId, $stuId]);
        $siblings = $sibStmt->fetchAll();
    }
} catch (\Throwable $e) {}

// ── Results ───────────────────────────────────────────────────────────────────
$examResults = [];
try {
    $examsQ = $pdo->prepare("
        SELECT DISTINCT e.id AS exam_id, e.exam_name, e.exam_type,
               COALESCE(ses.session_name, e.academic_year, '') AS session_label,
               e.start_date, c.class_name, stu.class_id
        FROM exam_marks m
        JOIN exams e       ON e.id  = m.exam_id
        JOIN students stu  ON stu.id = m.student_id
        LEFT JOIN classes c    ON c.id   = stu.class_id
        LEFT JOIN sessions ses ON ses.id = e.session_id AND ses.school_id = e.school_id
        WHERE m.student_id=? AND m.school_id=?
          AND (e.deleted_at IS NULL OR e.deleted_at > NOW())
        ORDER BY e.start_date DESC, e.id DESC
    ");
    $examsQ->execute([$stuId, $schoolId]);
    $exams = $examsQ->fetchAll();

    foreach ($exams as $exam) {
        $examId = (int)$exam['exam_id'];
        $subQ = $pdo->prepare("
            SELECT es.subject_id, es.max_marks, es.pass_marks, subj.subject_name,
                   em.marks_obtained, em.is_absent
            FROM exam_subjects es
            JOIN subjects subj ON subj.id = es.subject_id
            LEFT JOIN exam_marks em ON em.exam_id=es.exam_id AND em.subject_id=es.subject_id
                                   AND em.student_id=? AND em.school_id=?
            WHERE es.exam_id=? AND es.school_id=? AND es.class_id=?
            ORDER BY es.sort_order, subj.subject_name
        ");
        $subQ->execute([$stuId, $schoolId, $examId, $schoolId, (int)$exam['class_id']]);
        $subjects = $subQ->fetchAll();

        // exam_subjects is scoped per class — a student promoted since this
        // exam no longer matches on their CURRENT class_id, which would
        // silently show a past session's result as empty. Fall back to the
        // subjects this student actually has marks for, regardless of class.
        if (!$subjects) {
            $fbQ = $pdo->prepare("
                SELECT es.subject_id, es.max_marks, es.pass_marks, subj.subject_name,
                       em.marks_obtained, em.is_absent
                FROM exam_marks em
                JOIN exam_subjects es ON es.exam_id = em.exam_id AND es.subject_id = em.subject_id AND es.school_id = em.school_id
                JOIN subjects subj ON subj.id = es.subject_id
                WHERE em.exam_id=? AND em.student_id=? AND em.school_id=?
                ORDER BY es.sort_order, subj.subject_name
            ");
            $fbQ->execute([$examId, $stuId, $schoolId]);
            $seenSubj = [];
            foreach ($fbQ->fetchAll() as $row) {
                if (isset($seenSubj[$row['subject_id']])) continue; // same subject can appear once per class_id group
                $seenSubj[$row['subject_id']] = true;
                $subjects[] = $row;
            }
        }

        $totalMarks = 0; $totalMax = 0; $passed = true;
        foreach ($subjects as $sub) {
            $totalMax += (float)$sub['max_marks'];
            if ($sub['is_absent']) { $passed = false; continue; }
            $obtained = (float)($sub['marks_obtained'] ?? 0);
            $totalMarks += $obtained;
            if ($obtained < (float)$sub['pass_marks']) $passed = false;
        }
        $percent = $totalMax > 0 ? round($totalMarks / $totalMax * 100, 1) : 0;
        $examResults[] = ['exam_id'=>$examId,'exam'=>$exam,'subjects'=>$subjects,'totalMarks'=>$totalMarks,'totalMax'=>$totalMax,'percent'=>$percent,'passed'=>$passed];
    }
} catch (\Throwable $e) {}

// ── Syllabus Progress (per subject, this student's class+section) ────────────
$lessonsBySubject = [];
try {
    $lsStmt = $pdo->prepare("
        SELECT l.subject_id, l.id, l.title, l.sort_order, COALESCE(ls.status,'not_started') AS status
        FROM lessons l
        LEFT JOIN lesson_status ls ON ls.lesson_id = l.id AND ls.school_id=? AND ls.section_id=?
        WHERE l.school_id=? AND l.class_id=? AND l.is_active=1
        ORDER BY l.subject_id, l.sort_order
    ");
    $lsStmt->execute([$schoolId, (int)$stu['section_id'], $schoolId, (int)$stu['class_id']]);
    foreach ($lsStmt->fetchAll() as $row) { $lessonsBySubject[(int)$row['subject_id']][] = $row; }
} catch (\Throwable $e) {}

$progressRows = [];
try {
    $pStmt = $pdo->prepare("
        SELECT sub.id AS subject_id, sub.subject_name,
               spc.total_lessons, spc.completed_lessons, spc.in_progress_lessons, spc.percent_complete
        FROM subjects sub
        LEFT JOIN subject_progress_cache spc
            ON spc.school_id = sub.school_id AND spc.subject_id = sub.id
           AND spc.class_id = ? AND spc.section_id = ?
        WHERE sub.school_id=? AND sub.class_id=?
        ORDER BY sub.subject_name
    ");
    $pStmt->execute([(int)$stu['class_id'], (int)$stu['section_id'], $schoolId, (int)$stu['class_id']]);
    $progressRows = $pStmt->fetchAll();

    // Cache row may not exist yet (recompute only ever ran after a teaching_log
    // write) even though lessons ARE defined — fall back to deriving the same
    // numbers straight from lesson_status so the student never sees a false
    // "not set up" for a subject that simply hasn't had its first class logged.
    foreach ($progressRows as &$pr) {
        if ($pr['total_lessons'] === null) {
            $forSubj = $lessonsBySubject[(int)$pr['subject_id']] ?? [];
            $pr['total_lessons']       = count($forSubj);
            $pr['completed_lessons']   = count(array_filter($forSubj, fn($l) => $l['status'] === 'completed'));
            $pr['in_progress_lessons'] = count(array_filter($forSubj, fn($l) => $l['status'] === 'in_progress'));
            $pr['percent_complete']    = $pr['total_lessons'] > 0
                ? round((($pr['completed_lessons'] + 0.5 * $pr['in_progress_lessons']) / $pr['total_lessons']) * 100, 1)
                : null;
        }
    }
    unset($pr);
} catch (\Throwable $e) {}

// Which teacher teaches which subject — from the real weekly timetable for this
// exact class+section, so a parent sees the actual assigned teacher, not a guess.
$teacherBySubject = [];
try {
    $tbsStmt = $pdo->prepare("
        SELECT DISTINCT ts.subject_id, u.name AS teacher_name
        FROM timetable_slots ts
        JOIN teachers t ON t.id = ts.teacher_id
        JOIN users u ON u.id = t.user_id
        WHERE ts.school_id=? AND ts.class_id=? AND ts.section_id=?
        ORDER BY u.name
    ");
    $tbsStmt->execute([$schoolId, (int)$stu['class_id'], (int)$stu['section_id']]);
    foreach ($tbsStmt->fetchAll() as $row) {
        $sid = (int)$row['subject_id'];
        $teacherBySubject[$sid] ??= [];
        if (!in_array($row['teacher_name'], $teacherBySubject[$sid], true)) {
            $teacherBySubject[$sid][] = $row['teacher_name'];
        }
    }
} catch (\Throwable $e) {}

// Currently-being-taught lesson per subject (first not-yet-completed lesson in
// sort order) plus its topic-level breakdown, so a parent can see exactly how
// far the teacher has gotten inside that lesson — not just a lesson count.
$currentLessonBySubject = [];
foreach ($lessonsBySubject as $sid => $lessonsList) {
    $current = null;
    foreach ($lessonsList as $l) {
        if ($l['status'] !== 'completed') { $current = $l; break; }
    }
    if (!$current) continue; // all lessons completed — nothing "current" to show
    $topics = [];
    try {
        $topStmt = $pdo->prepare("
            SELECT lt.id, lt.title, lt.sort_order, COALESCE(tps.percent,0) AS percent
            FROM lesson_topics lt
            LEFT JOIN topic_status tps ON tps.topic_id = lt.id AND tps.section_id=?
            WHERE lt.school_id=? AND lt.lesson_id=?
            ORDER BY lt.sort_order
        ");
        $topStmt->execute([(int)$stu['section_id'], $schoolId, (int)$current['id']]);
        $topics = $topStmt->fetchAll();
    } catch (\Throwable $e) {}
    $currentLessonBySubject[$sid] = ['lesson' => $current, 'topics' => $topics];
}

// ── Missed lessons on absent days — full transparency: on any day this student
// was marked absent, show exactly what was taught in each subject that day (from
// the real teaching log), so a parent knows what to help the child catch up on. ──
$missedByDate = [];
try {
    $absentDates = [];
    foreach ($attRows as $r) {
        if ($r['status'] === 'absent') $absentDates[] = $r['date'];
        if (count($absentDates) >= 20) break; // $attRows is already DESC by date — most recent 20 absences
    }
    if ($absentDates) {
        $ph = implode(',', array_fill(0, count($absentDates), '?'));
        $mlStmt = $pdo->prepare("
            SELECT tl.date, tl.status AS log_status, tl.topic_reached,
                   subj.subject_name, lsn.title AS lesson_title
            FROM teaching_log tl
            JOIN subjects subj ON subj.id = tl.subject_id
            LEFT JOIN lessons lsn ON lsn.id = tl.lesson_id
            WHERE tl.school_id=? AND tl.class_id=? AND tl.section_id=?
              AND tl.date IN ($ph) AND tl.status != 'not_held'
            ORDER BY tl.date DESC, subj.subject_name
        ");
        $mlStmt->execute(array_merge([$schoolId, (int)$stu['class_id'], (int)$stu['section_id']], $absentDates));
        foreach ($mlStmt->fetchAll() as $row) {
            $missedByDate[$row['date']][] = $row;
        }
    }
} catch (\Throwable $e) {}

// ── Bus data — create tables first (idempotent), then query ──────────────────
$busData = []; // empty array = no assignment; null would hide tab

try {
    $bsq = $pdo->prepare("
        SELECT sva.van_route_id,
               vr.route_name, vr.from_location, vr.to_location,
               b.id AS bus_id, b.bus_name, b.bus_number, b.capacity, b.status AS bus_status,
               bra.pickup_time, bra.drop_time, bra.pickup_time2, bra.drop_time2,
               bra.pickup_time3, bra.drop_time3, bra.pickup_time4, bra.drop_time4, bra.pickup_time5, bra.drop_time5,
               bra.shift_count, bra.days,
               u.name AS driver_name, u.phone AS driver_phone,
               bss.shift_no,
               COALESCE((SELECT COUNT(*) FROM student_home_locations hl WHERE hl.student_id=?),0) AS has_home,
               (SELECT hl.alert_radius FROM student_home_locations hl WHERE hl.student_id=? LIMIT 1) AS home_radius
        FROM student_van_assignments sva
        JOIN van_routes vr ON vr.id = sva.van_route_id
        LEFT JOIN bus_route_assignments bra ON bra.route_id = sva.van_route_id AND bra.school_id = sva.school_id AND bra.status='active'
        LEFT JOIN school_buses b ON b.id = bra.bus_id
        LEFT JOIN teachers t ON t.id = bra.driver_id
        LEFT JOIN users u ON u.id = t.user_id
        LEFT JOIN bus_student_shifts bss ON bss.student_id = sva.student_id AND bss.route_id = sva.van_route_id
        WHERE sva.student_id=? AND sva.school_id=?
        LIMIT 1
    ");
    $bsq->execute([$stuId, $stuId, $stuId, $schoolId]);
    $busRow = $bsq->fetch();
    if ($busRow) {
        $busData = $busRow; // has_home and other keys always present
    }
} catch (\Throwable $e) {}

// ── Setup new feature tables ──────────────────────────────────────────────────
require_once __DIR__ . '/setup_tables.php';
stuSetupTables($pdo);

// ── School field visibility (for edit profile) ────────────────────────────────
$schoolFieldConfig = [];
try {
    $sfStmt = $pdo->prepare("SELECT field_key, is_visible, field_label FROM school_student_fields WHERE school_id=? ORDER BY sort_order");
    $sfStmt->execute([$schoolId]);
    $schoolFieldConfig = array_column($sfStmt->fetchAll(), null, 'field_key');
} catch (\Throwable $e) {}

// Guardian-editable fields: key => [label, type, dv (default visible), always]
// dv=1 means shown even if school has no config entry yet (standard fields)
// always=true means shown regardless of school config (only 'name')
$GUARDIAN_EDITABLE_FIELDS = [
    'name'                    => ['label' => 'Full Name',               'type' => 'text',     'dv' => 1, 'always' => true],
    'gender'                  => ['label' => 'Gender',                  'type' => 'gender',   'dv' => 1],
    'father_name'             => ['label' => "Father's Name",          'type' => 'text',     'dv' => 1],
    'mother_name'             => ['label' => "Mother's Name",          'type' => 'text',     'dv' => 1],
    'guardian_phone'          => ['label' => 'Guardian Phone',         'type' => 'tel',      'dv' => 1],
    'email'                   => ['label' => 'Email',                  'type' => 'email',    'dv' => 1],
    'address'                 => ['label' => 'Address',                'type' => 'textarea', 'dv' => 1],
    'aadhar_no'               => ['label' => 'Aadhar Number',          'type' => 'text',     'dv' => 1],
    'blood_group'             => ['label' => 'Blood Group',            'type' => 'text',     'dv' => 0],
    'religion'                => ['label' => 'Religion',               'type' => 'text',     'dv' => 0],
    'caste_category'          => ['label' => 'Caste / Category',       'type' => 'text',     'dv' => 0],
    'nationality'             => ['label' => 'Nationality',            'type' => 'text',     'dv' => 0],
    'mother_tongue'           => ['label' => 'Mother Tongue',          'type' => 'text',     'dv' => 0],
    'emergency_contact_name'  => ['label' => 'Emergency Contact Name', 'type' => 'text',     'dv' => 0],
    'emergency_contact_phone' => ['label' => 'Emergency Contact Phone','type' => 'tel',      'dv' => 0],
    'medical_notes'           => ['label' => 'Medical Notes',          'type' => 'textarea', 'dv' => 0],
    'bus_route'               => ['label' => 'Bus Route',              'type' => 'text',     'dv' => 0],
];

// Resolve visibility per school config
$visibleEditableFields = [];
foreach ($GUARDIAN_EDITABLE_FIELDS as $key => $meta) {
    if (!empty($meta['always'])) {
        $visibleEditableFields[$key] = $meta;
        continue;
    }
    if (isset($schoolFieldConfig[$key])) {
        // School has explicit config — respect it
        if ((int)$schoolFieldConfig[$key]['is_visible'] === 1) {
            // Use school-customised label if set
            if (!empty($schoolFieldConfig[$key]['field_label'])) {
                $meta['label'] = $schoolFieldConfig[$key]['field_label'];
            }
            $visibleEditableFields[$key] = $meta;
        }
    } else {
        // No config yet (school hasn't customised) — use default visibility
        if ($meta['dv']) {
            $visibleEditableFields[$key] = $meta;
        }
    }
}

// ── Pending profile request ───────────────────────────────────────────────────
$pendingProfileReq = null;
try {
    $pr = $pdo->prepare("SELECT id, created_at FROM student_profile_requests WHERE student_id=? AND school_id=? AND status='pending' LIMIT 1");
    $pr->execute([$stuId, $schoolId]);
    $pendingProfileReq = $pr->fetch();
} catch (\Throwable $e) {}

// ── Complaints (my list) ──────────────────────────────────────────────────────
$myComplaints = [];
$openComplaints = 0;
try {
    $cStmt = $pdo->prepare("SELECT id,subject,description,attachments,status,created_at FROM student_complaints WHERE student_id=? AND school_id=? ORDER BY created_at DESC");
    $cStmt->execute([$stuId, $schoolId]);
    $myComplaints = $cStmt->fetchAll();
    foreach ($myComplaints as $cRow) {
        if (in_array($cRow['status'], ['open','in_progress'])) $openComplaints++;
    }
} catch (\Throwable $e) {}

// ── Homework (my class) ───────────────────────────────────────────────────────
$myHomework = [];
$pendingHwCount = 0;
try {
    $hwStmt = $pdo->prepare("
        SELECT h.*, u.name AS teacher_name,
               hs.id AS sub_id, hs.text_answer, hs.image AS sub_image,
               hs.status AS sub_status, hs.teacher_remarks, hs.marks_obtained, hs.submitted_at
        FROM homework h
        LEFT JOIN users u ON u.id = h.created_by
        LEFT JOIN homework_submissions hs ON hs.homework_id = h.id AND hs.student_id = ?
        WHERE h.school_id=? AND h.class_id=?
          AND (h.section_id IS NULL OR h.section_id=?)
        ORDER BY hs.id IS NOT NULL ASC, h.due_date ASC, h.created_at DESC
    ");
    $hwStmt->execute([$stuId, $schoolId, (int)$stu['class_id'], (int)($stu['section_id'] ?? 0)]);
    $myHomework = $hwStmt->fetchAll();
    foreach ($myHomework as $hwRow) {
        if (!$hwRow['sub_id']) $pendingHwCount++;
    }
} catch (\Throwable $e) {}

// ── Helpers ───────────────────────────────────────────────────────────────────
$photoUrl = '';
if (!empty($stu['photo'])) {
    $photoUrl = BASE_URL . '/assets/uploads/photos/' . $stu['photo'];
}
$initial = strtoupper(mb_substr($stu['name'], 0, 1));
$statusColors = ['active'=>['#dcfce7','#15803d'],'inactive'=>['#f1f5f9','#64748b'],'passed'=>['#dbeafe','#1d4ed8'],'transferred'=>['#fef9c3','#92400e'],'dropped'=>['#fee2e2','#b91c1c']];
[$sBg, $sFg] = $statusColors[$stu['status'] ?? 'active'] ?? ['#f1f5f9','#64748b'];

function fmtDate(?string $d, string $fmt = 'd M Y'): string {
    if (!$d) return '—';
    try { return (new DateTime($d))->format($fmt); } catch(\Throwable $e) { return $d; }
}
function fld(string $lbl, ?string $val, string $icon = ''): string {
    $v = htmlspecialchars($val ?? '', ENT_QUOTES);
    return "<div class=\"fi\"><div class=\"fi-l\">{$lbl}</div><div class=\"fi-v\">" . ($v ?: '<span class="fi-empty">—</span>') . "</div></div>";
}

// Build date→status map for the JS attendance calendar
$attCalData = [];
foreach ($attRows as $r) { $attCalData[$r['date']] = $r['status']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<?php require_once __DIR__ . '/../includes/app_context.php'; renderAppContextScript(); ?>
<meta name="theme-color" content="#4338ca">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?= htmlspecialchars($stu['name']) ?> — Student Portal</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<style>
:root{
  --primary:#4f46e5;--primary-dk:#312e81;--primary-lt:#e0e7ff;
  --secondary:#7c3aed;--teal:#0d9488;
  --green:#16a34a;--green-lt:#dcfce7;
  --red:#dc2626;--red-lt:#fee2e2;
  --orange:#d97706;--orange-lt:#fef9c3;
  --blue:#1d4ed8;--blue-lt:#dbeafe;
  --bg:#f0f4ff;--card:#fff;--border:#e2e8f0;
  --text:#1e293b;--muted:#64748b;--subtle:#94a3b8;
  --radius:16px;--radius-sm:10px;
  --shadow:0 2px 10px rgba(0,0,0,.07);
  --shadow-lg:0 8px 32px rgba(79,70,229,.15);
  --bnav-h:62px;
  --safe-b:env(safe-area-inset-bottom,0px);
  --safe-t:env(safe-area-inset-top,0px);
  /* Mobile-width default: a fixed bottom nav is reserved, and only the
     56px app-bar sits above the content. On wider screens (desktop browser,
     not the mobile WebView) --chrome-bottom becomes 0 and --chrome-top grows
     to include the restored top tab bar too — see min-width:768px below. */
  --chrome-bottom: calc(var(--bnav-h) + var(--safe-b));
  --chrome-top: calc(56px + var(--safe-t));
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}


/* ── Top App Bar ── */
.app-bar{position:sticky;top:0;z-index:100;background:#fff;border-bottom:1px solid var(--border);
  padding:0 16px;height:56px;display:flex;align-items:center;justify-content:space-between;
  box-shadow:0 1px 6px rgba(0,0,0,.06);padding-top:var(--safe-t);}
.app-bar-school{display:flex;align-items:center;gap:10px;min-width:0;}
.app-bar-logo{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--primary),var(--secondary));
  display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.9rem;
  flex-shrink:0;overflow:hidden;}
.app-bar-logo img{width:100%;height:100%;object-fit:contain;padding:3px;}
.app-bar-name{font-size:.82rem;font-weight:700;color:var(--text);line-height:1.2;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;}
.app-bar-sub{font-size:.68rem;color:var(--muted);}
.app-bar-right{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.app-bar-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));
  display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.8rem;
  overflow:hidden;flex-shrink:0;border:2px solid var(--primary-lt);}
.app-bar-av img{width:100%;height:100%;object-fit:cover;}
.btn-logout{display:flex;align-items:center;gap:5px;padding:6px 13px;border-radius:20px;
  background:#f8fafc;border:1.5px solid var(--border);color:var(--muted);
  font-size:.75rem;font-weight:600;text-decoration:none;transition:all .15s;}
.btn-logout:hover,.btn-logout:active{background:var(--red-lt);color:var(--red);border-color:#fca5a5;}
@media(max-width:400px){.btn-logout span{display:none;}}

/* ── Hero ── */
.hero{background:linear-gradient(135deg,#312e81 0%,#4338ca 45%,#7c3aed 100%);
  padding:20px 16px 0;}
.hero-card{max-width:960px;margin:0 auto;}
.hero-top{display:flex;align-items:flex-start;gap:16px;padding-bottom:16px;}
.hero-photo-wrap{position:relative;flex-shrink:0;}
.hero-photo{width:80px;height:80px;border-radius:50%;border:3px solid rgba(255,255,255,.4);
  background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;
  font-size:2rem;font-weight:800;color:#fff;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.25);}
.hero-photo img{width:100%;height:100%;object-fit:cover;}
.hero-photo-edit{position:absolute;bottom:-2px;right:-2px;width:26px;height:26px;border-radius:50%;
  background:#fff;border:2px solid rgba(255,255,255,.6);display:flex;align-items:center;justify-content:center;
  color:var(--primary);font-size:.7rem;cursor:pointer;box-shadow:0 2px 6px rgba(0,0,0,.2);
  transition:transform .15s;}
.hero-photo-edit:hover{transform:scale(1.1);}
.hero-info{flex:1;min-width:0;padding-top:4px;}
.hero-name{font-size:1.18rem;font-weight:800;color:#fff;line-height:1.2;margin-bottom:5px;}
.hero-meta{display:flex;flex-wrap:wrap;gap:5px 14px;font-size:.74rem;color:rgba(255,255,255,.8);margin-bottom:6px;}
.hero-meta span{display:flex;align-items:center;gap:4px;}
.hero-pending{display:inline-flex;align-items:center;gap:5px;font-size:.7rem;
  background:rgba(255,200,0,.2);border:1px solid rgba(255,200,0,.4);color:#fde68a;
  padding:3px 10px;border-radius:20px;margin-top:2px;}
.hero-edit-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 14px;
  background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);
  color:#fff;border-radius:20px;font-size:.74rem;font-weight:600;cursor:pointer;
  transition:background .15s;margin-top:4px;}
.hero-edit-btn:hover{background:rgba(255,255,255,.25);}

/* Hero quick stats */
.hero-stats{display:flex;background:rgba(0,0,0,.18);margin:0 -16px;}
.hstat{flex:1;padding:11px 8px;text-align:center;position:relative;cursor:default;}
.hstat+.hstat::before{content:'';position:absolute;left:0;top:18%;height:64%;
  border-left:1px solid rgba(255,255,255,.12);}
.hstat-val{font-size:.95rem;font-weight:800;color:#fff;line-height:1;}
.hstat-lbl{font-size:.6rem;font-weight:600;color:rgba(255,255,255,.6);margin-top:3px;
  text-transform:uppercase;letter-spacing:.04em;}
.hstat.red .hstat-val{color:#fca5a5;}
.hstat.green .hstat-val{color:#86efac;}
.hstat.yellow .hstat-val{color:#fde68a;}

/* ── Bottom Navigation (native app pattern — replaces the old scrolling top
   tab strip; this is the single biggest "feels like a website vs a real app"
   fix) ── */
.bottom-nav{position:fixed;left:0;right:0;bottom:0;z-index:200;background:#fff;
  border-top:1px solid var(--border);display:flex;padding-bottom:var(--safe-b);
  box-shadow:0 -2px 14px rgba(0,0,0,.07);}
.bnav-btn{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:3px;height:var(--bnav-h);background:none;border:none;color:var(--subtle);
  font-size:.62rem;font-weight:700;cursor:pointer;position:relative;transition:color .15s;
  -webkit-tap-highlight-color:transparent;}
.bnav-btn.active{color:var(--primary);}
.bnav-btn i{font-size:1.2rem;}
.bnav-btn .tab-dot{top:6px;right:calc(50% - 22px);}
.bnav-btn .tab-cnt{position:absolute;top:2px;right:calc(50% - 24px);}

.tab-dot{position:absolute;top:10px;right:10px;width:7px;height:7px;border-radius:50%;
  background:var(--red);border:1.5px solid #fff;}
.tab-cnt{background:var(--red);color:#fff;border-radius:20px;
  font-size:.6rem;font-weight:800;padding:1px 6px;min-width:18px;text-align:center;}
.tab-cnt.orange{background:var(--orange);}
.tab-cnt.green{background:var(--green);}

/* ── "More" bottom sheet — native slide-up drawer for the less-frequent tabs ── */
.more-sheet-overlay{position:fixed;inset:0;background:rgba(15,23,42,.4);z-index:300;
  display:none;align-items:flex-end;-webkit-tap-highlight-color:transparent;}
.more-sheet-overlay.show{display:flex;}
.more-sheet{background:#fff;width:100%;border-radius:20px 20px 0 0;
  padding:0 8px calc(12px + var(--safe-b));max-height:75vh;overflow-y:auto;
  animation:sheetUp .22s cubic-bezier(.2,.8,.2,1);}
@keyframes sheetUp{from{transform:translateY(100%);}to{transform:translateY(0);}}
.more-sheet-handle{width:38px;height:4px;background:#e2e8f0;border-radius:4px;margin:10px auto 8px;}
.more-sheet-title{font-size:.68rem;font-weight:800;color:var(--subtle);text-transform:uppercase;
  letter-spacing:.06em;padding:4px 14px 8px;}
.more-item{display:flex;align-items:center;gap:14px;width:100%;padding:13px 14px;
  font-size:.86rem;font-weight:600;color:var(--text);text-align:left;border-radius:12px;
  position:relative;background:none;border:none;cursor:pointer;-webkit-tap-highlight-color:transparent;}
.more-item:active{background:#f8fafc;}
.more-item i{font-size:1.1rem;color:var(--primary);width:22px;text-align:center;flex-shrink:0;}
.more-item .tab-cnt,.more-item .tab-dot{margin-left:auto;position:static;flex-shrink:0;}

/* ── Native-style alert/confirm — replaces browser alert()/confirm() so
   popups look like part of the app instead of a "yoursite.com says" dialog ── */
.stu-native-alert-overlay{position:fixed;inset:0;background:rgba(15,23,42,.45);
  z-index:500;display:flex;align-items:center;justify-content:center;padding:24px;
  animation:stuAlertFade .15s;-webkit-tap-highlight-color:transparent;}
@keyframes stuAlertFade{from{opacity:0;}to{opacity:1;}}
.stu-native-alert-box{background:#fff;border-radius:18px;width:100%;max-width:320px;
  padding:22px 20px 16px;box-shadow:0 12px 32px rgba(15,23,42,.22);text-align:center;
  animation:stuAlertPop .18s cubic-bezier(.2,.8,.2,1);}
@keyframes stuAlertPop{from{transform:scale(.92);opacity:0;}to{transform:scale(1);opacity:1;}}
.stu-native-alert-msg{font-size:.9rem;font-weight:600;color:var(--text);line-height:1.5;
  margin-bottom:18px;white-space:pre-line;}
.stu-native-alert-actions{display:flex;gap:8px;}
.stu-native-alert-actions.two .stu-native-alert-btn{flex:1;}
.stu-native-alert-btn{flex:1;border:none;border-radius:11px;padding:11px 0;font-size:.85rem;
  font-weight:700;cursor:pointer;-webkit-tap-highlight-color:transparent;}
.stu-native-alert-btn.primary{background:var(--primary);color:#fff;}
.stu-native-alert-btn.primary:active{background:var(--primary-dk);}
.stu-native-alert-btn.ghost{background:#f1f5f9;color:var(--muted);}
.stu-native-alert-btn.ghost:active{background:#e2e8f0;}
.stu-native-alert-btn.danger{background:var(--red);color:#fff;}
.stu-native-alert-btn.danger:active{background:#b91c1c;}

/* ── Desktop-width tab bar — hidden by default (mobile uses the bottom nav
   above); restored at min-width:768px so a real desktop browser keeps the
   original top-scrolling-tabs layout untouched. This is the exact original
   .tab-bar/.tab-btn styling from before the native bottom-nav redesign. ── */
.tab-bar-desktop{display:none;}
@media(min-width:768px){
  .bottom-nav,.more-sheet-overlay{display:none !important;}
  .tab-bar-desktop{display:flex;background:#fff;border-bottom:1px solid var(--border);
    position:sticky;top:56px;z-index:90;overflow-x:auto;scrollbar-width:none;
    box-shadow:0 2px 8px rgba(0,0,0,.04);}
  .tab-bar-desktop::-webkit-scrollbar{display:none;}
  :root{--chrome-bottom:0px;--chrome-top:calc(56px + var(--safe-t) + 48px);}
  .main-content{padding-bottom:16px;}
}
.dtab-btn{flex-shrink:0;display:flex;align-items:center;gap:6px;padding:0 18px;
  height:48px;font-size:.8rem;font-weight:600;color:var(--muted);
  background:none;border:none;border-bottom:3px solid transparent;cursor:pointer;
  transition:all .15s;white-space:nowrap;position:relative;}
.dtab-btn.active{color:var(--primary);border-bottom-color:var(--primary);}
.dtab-btn:hover:not(.active){color:var(--text);background:#fafbff;}
.dtab-btn i{font-size:.9rem;}
.dtab-btn .tab-dot{position:absolute;top:10px;right:10px;}

/* ── Main Content ── */
.main-content{max-width:960px;margin:0 auto;padding:16px 16px calc(var(--chrome-bottom) + 20px);}

/* ── Panels ── */
.panel{display:none;}
.panel.active{display:block;}
.empty-state{text-align:center;padding:36px 20px;color:var(--muted);}
.empty-state p{margin-top:10px;font-size:.84rem;line-height:1.6;}

/* ── Generic Card ── */
.card{background:var(--card);border-radius:var(--radius);border:1.5px solid var(--border);
  overflow:hidden;margin-bottom:14px;box-shadow:var(--shadow);}
.card-head{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;
  border-bottom:1px solid #f1f5f9;}
.card-head-left{display:flex;align-items:center;gap:8px;font-size:.85rem;font-weight:700;color:var(--text);}
.card-head-left i{font-size:.95rem;color:var(--primary);}
.card-body{padding:16px 18px;}
.card-body.p0{padding:0;}

/* ── Field Grid ── */
.fi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;}
.fi{background:#f8fafc;border-radius:var(--radius-sm);padding:10px 13px;border:1px solid #f1f5f9;}
.fi-l{font-size:.6rem;font-weight:700;color:var(--subtle);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;}
.fi-v{font-size:.84rem;font-weight:600;color:var(--text);word-break:break-word;}
.fi-empty{color:var(--subtle);}
.fi-full{background:#f8fafc;border-radius:var(--radius-sm);padding:10px 13px;border:1px solid #f1f5f9;margin-top:10px;}

/* ── Panel Header ── */
.panel-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;}
.panel-hdr-left{display:flex;align-items:center;gap:8px;}
.panel-hdr-title{font-size:.95rem;font-weight:700;color:var(--text);}
.panel-hdr-meta{font-size:.75rem;color:var(--muted);}

/* ── Empty State ── */
.empty-card{background:var(--card);border-radius:var(--radius);border:1.5px solid var(--border);
  padding:48px 24px;text-align:center;margin-bottom:14px;}
.empty-emoji{font-size:2.4rem;margin-bottom:10px;}
.empty-title{font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:4px;}
.empty-sub{font-size:.78rem;color:var(--muted);}

/* ── Alerts ── */
.alert{display:flex;align-items:flex-start;gap:9px;padding:11px 14px;border-radius:var(--radius-sm);
  font-size:.8rem;font-weight:500;margin-bottom:14px;}
.alert i{flex-shrink:0;font-size:.9rem;margin-top:1px;}
.alert.danger{background:var(--red-lt);color:#b91c1c;border:1px solid #fca5a5;}
.alert.success{background:var(--green-lt);color:#15803d;border:1px solid #86efac;}
.alert.warning{background:var(--orange-lt);color:#92400e;border:1px solid #fbbf24;}
.alert.info{background:var(--blue-lt);color:var(--blue);border:1px solid #93c5fd;}

/* ── Badges ── */
.badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;
  font-size:.68rem;font-weight:700;}
.badge-green{background:var(--green-lt);color:#15803d;}
.badge-red{background:var(--red-lt);color:#b91c1c;}
.badge-orange{background:var(--orange-lt);color:#92400e;}
.badge-blue{background:var(--blue-lt);color:var(--blue);}
.badge-purple{background:#f3e8ff;color:#6d28d9;}
.badge-gray{background:#f1f5f9;color:var(--muted);}
.badge-teal{background:#ccfbf1;color:#0f766e;}

/* ── Attendance ── */
.att-year-select-row{display:flex;align-items:center;gap:10px;background:var(--card);
  border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:14px;}
.att-year-select-row label{display:flex;align-items:center;gap:6px;font-size:.78rem;font-weight:700;color:var(--text);flex-shrink:0;}
.att-year-select-row select{flex:1;border:1.5px solid var(--border);border-radius:8px;padding:8px 10px;
  font-size:.82rem;font-weight:700;color:var(--primary);background:#fff;}
.att-hero{display:flex;align-items:center;gap:20px;background:var(--card);
  border-radius:var(--radius);border:1.5px solid var(--border);padding:20px;
  margin-bottom:14px;flex-wrap:wrap;box-shadow:var(--shadow);}
.att-ring-wrap{flex-shrink:0;}
.att-ring{width:110px;height:110px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  background:conic-gradient(var(--ring-c,#16a34a) calc(var(--ring-p,0)*1%),#f1f5f9 0);
  position:relative;}
.att-ring::after{content:'';position:absolute;width:80px;height:80px;background:#fff;border-radius:50%;}
.att-ring-inner{position:relative;z-index:1;text-align:center;}
.att-ring-val{font-size:1.1rem;font-weight:800;color:var(--text);}
.att-ring-lbl{font-size:.6rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;}
.att-mini-chips{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;flex:1;min-width:180px;}
/* On narrow phones, the ring (110px) + gap + chips' 180px floor genuinely
   don't fit side-by-side — flex-wrap alone left it borderline, spilling a
   sliver of the rightmost chip past the card edge. Stack cleanly instead of
   relying on that fragile math. */
@media(max-width:420px){
  .att-hero{flex-direction:column;}
  .att-mini-chips{width:100%;min-width:0;}
}
.att-mini{background:#f8fafc;border-radius:var(--radius-sm);padding:9px 10px;text-align:center;border:1.5px solid var(--border);}
.att-mini .av{font-size:1.15rem;font-weight:800;}
.att-mini .al{font-size:.6rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;margin-top:2px;}
.att-mini.present .av{color:var(--green);}
.att-mini.absent .av{color:var(--red);}
.att-mini.late .av{color:var(--orange);}
.att-mini.leave .av{color:#7c3aed;}
.att-mini.total .av{color:var(--primary);}
.att-mini.hday .av{color:var(--blue);}
.att-list-row{display:flex;align-items:center;padding:10px 18px;border-bottom:1px solid #f8fafc;gap:14px;}
.att-list-row:last-child{border-bottom:none;}
.att-list-row:hover{background:#fafbff;}
.att-day-num{font-size:1.1rem;font-weight:800;color:var(--text);line-height:1;min-width:26px;text-align:center;}
.att-day-info{font-size:.7rem;color:var(--muted);margin-top:1px;}
.att-date-block{text-align:center;}
.att-sb{padding:3px 11px;border-radius:20px;font-size:.7rem;font-weight:700;margin-left:auto;}
.att-sb.present{background:var(--green-lt);color:#15803d;}
.att-sb.absent{background:var(--red-lt);color:#b91c1c;}
.att-sb.late{background:var(--orange-lt);color:#92400e;}
.att-sb.half_day{background:var(--blue-lt);color:var(--blue);}
.att-sb.leave{background:#f3e8ff;color:#6d28d9;}

/* ── Attendance Calendar ── */
.att-cal-card{background:var(--card);border-radius:var(--radius);border:1.5px solid var(--border);
  margin-bottom:14px;overflow:hidden;box-shadow:var(--shadow);}
.att-cal-nav{display:flex;align-items:center;justify-content:space-between;padding:14px 18px 12px;
  border-bottom:1px solid #f1f5f9;}
.att-cal-nav-btn{width:32px;height:32px;border-radius:8px;border:1.5px solid var(--border);
  background:var(--card);color:var(--text);font-size:.95rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:all .15s;}
.att-cal-nav-btn:hover{background:#f1f5f9;border-color:#818cf8;color:var(--primary);}
.att-cal-title{font-size:.9rem;font-weight:800;color:var(--text);}
.att-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);padding:10px 10px 12px;gap:3px;}
.att-cal-dow{font-size:.56rem;font-weight:700;color:var(--subtle);text-align:center;
  text-transform:uppercase;letter-spacing:.04em;padding:3px 0 6px;}
.att-cal-day{aspect-ratio:1;border-radius:7px;display:flex;align-items:center;justify-content:center;
  font-size:.73rem;font-weight:600;cursor:default;transition:transform .1s;}
.att-cal-day:not(.blank):not(.em):hover{transform:scale(1.12);}
.att-cal-day.today{outline:2px solid var(--primary);outline-offset:1px;}
.att-cal-day.p{background:#dcfce7;color:#15803d;}
.att-cal-day.a{background:#fce7f3;color:#be185d;font-weight:800;}
.att-cal-day.l{background:#fef9c3;color:#92400e;}
.att-cal-day.h{background:#dbeafe;color:#1d4ed8;}
.att-cal-day.lv{background:#f3e8ff;color:#6d28d9;}
.att-cal-day.em{background:#f8fafc;color:var(--subtle);}
.att-cal-day.sun{background:#fee2e2;color:#b91c1c;font-weight:700;}
.att-cal-day.holp{background:#e0e7ff;color:#4338ca;font-weight:700;}
.att-cal-day.hols{background:#fae8ff;color:#a21caf;font-weight:700;}
.att-cal-day.blank{background:transparent;}
.att-cal-legend{display:flex;flex-wrap:wrap;gap:6px 14px;padding:0 12px 12px;font-size:.67rem;}
.att-cal-hol-list{display:flex;flex-direction:column;gap:6px;padding:0 12px 14px;}
.att-cal-hol-row{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #f1f5f9;
  border-radius:8px;padding:7px 10px;font-size:.72rem;}
.att-cal-hol-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
.att-cal-hol-dot.holp{background:#4338ca;}
.att-cal-hol-dot.hols{background:#a21caf;}
.att-cal-hol-date{font-weight:800;color:var(--text);flex-shrink:0;}
.att-cal-hol-reason{flex:1;color:var(--muted);}
.att-cal-hol-badge{font-size:.6rem;font-weight:700;color:var(--muted);background:#fff;
  border:1px solid var(--border);border-radius:20px;padding:2px 8px;flex-shrink:0;}

/* ── Month / Year switch — large, thumb-friendly, obvious for less tech-savvy users ── */
.att-view-switch{display:flex;gap:8px;margin-bottom:14px;}
.att-view-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:7px;
  padding:12px 10px;border-radius:var(--radius-sm);border:1.5px solid var(--border);
  background:var(--card);color:var(--muted);font-size:.82rem;font-weight:700;cursor:pointer;
  transition:all .15s;box-shadow:var(--shadow);}
.att-view-btn i{font-size:1rem;}
.att-view-btn.active{background:linear-gradient(135deg,var(--primary),var(--secondary));
  color:#fff;border-color:transparent;box-shadow:0 4px 14px rgba(79,70,229,.3);}

/* ── Year at a Glance — one bar per month, no reading required to compare months ── */
.att-year-grid{display:flex;flex-direction:column;gap:10px;padding:14px 16px 18px;}
.att-year-row{display:flex;align-items:center;gap:10px;cursor:pointer;padding:6px;border-radius:10px;
  transition:background .15s;}
.att-year-row:hover{background:#f8fafc;}
.att-year-mlabel{width:56px;flex-shrink:0;font-size:.76rem;font-weight:700;color:var(--text);}
.att-year-bar-track{flex:1;height:22px;background:#f1f5f9;border-radius:20px;overflow:hidden;position:relative;}
.att-year-bar-fill{height:100%;border-radius:20px;transition:width .4s ease;
  display:flex;align-items:center;justify-content:flex-end;padding-right:8px;}
.att-year-bar-fill.good{background:linear-gradient(90deg,#4ade80,#16a34a);}
.att-year-bar-fill.warn{background:linear-gradient(90deg,#fca5a5,#dc2626);}
.att-year-bar-fill.none{background:transparent;}
.att-year-pct{font-size:.68rem;font-weight:800;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.25);}
.att-year-pct.outside{color:var(--subtle);text-shadow:none;}
.att-year-sub{width:64px;flex-shrink:0;text-align:right;font-size:.63rem;color:var(--muted);font-weight:600;}
.att-cal-leg{display:flex;align-items:center;gap:5px;color:var(--muted);font-weight:500;}
.att-cal-leg-dot{width:11px;height:11px;border-radius:3px;flex-shrink:0;}

/* ── Fee ── */
.fee-sum-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;}
.fee-sum{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);
  padding:16px 12px;text-align:center;box-shadow:var(--shadow);}
.fee-sum .fv{font-size:1.1rem;font-weight:800;color:var(--text);}
.fee-sum .fl{font-size:.65rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-top:4px;}
.fee-sum.paid .fv{color:var(--green);}
.fee-sum.due .fv{color:var(--red);}
.fee-row-card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);
  padding:14px 16px;margin-bottom:10px;box-shadow:var(--shadow);}
.fee-row-top{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;}
.fee-row-name{font-size:.88rem;font-weight:700;color:var(--text);}
.fee-row-meta{display:flex;flex-wrap:wrap;gap:6px 16px;font-size:.74rem;color:var(--muted);margin-bottom:8px;}
.fee-row-meta span{display:flex;align-items:center;gap:4px;}
.fee-row-bar{background:#f1f5f9;border-radius:100px;height:6px;overflow:hidden;margin-bottom:4px;}
.fee-row-bar-fill{height:100%;border-radius:100px;background:linear-gradient(90deg,var(--green),#4ade80);}
.fee-row-bar-lbl{font-size:.68rem;color:var(--muted);}
.fee-sb{padding:2px 9px;border-radius:20px;font-size:.68rem;font-weight:700;}
.fee-sb.paid{background:var(--green-lt);color:#15803d;}
.fee-sb.partial{background:var(--orange-lt);color:#92400e;}
.fee-sb.pending,.fee-sb.unpaid{background:var(--red-lt);color:#b91c1c;}

/* ── Results ── */
.result-card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);
  overflow:hidden;margin-bottom:12px;box-shadow:var(--shadow);}
.result-card-head{display:flex;align-items:center;gap:12px;padding:14px 16px;cursor:pointer;
  background:#f8fafc;justify-content:space-between;transition:background .12s;}
.result-card-head:hover{background:#f1f5f9;}
.result-card-head:active{background:#eef2ff;}
.result-hl{display:flex;align-items:center;gap:12px;}
.result-icon{width:40px;height:40px;border-radius:10px;
  background:linear-gradient(135deg,var(--primary),var(--secondary));
  display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;flex-shrink:0;}
.result-en{font-size:.88rem;font-weight:700;color:var(--text);}
.result-em{font-size:.7rem;color:var(--muted);margin-top:2px;}
.result-hr{display:flex;align-items:center;gap:8px;}
.result-pct{font-size:1.05rem;font-weight:800;}
.result-body{display:none;border-top:1px solid #f1f5f9;}
.result-card.open .result-body{display:block;}
.result-card.open .chevron{transform:rotate(180deg);}
.chevron{transition:transform .2s;color:var(--subtle);font-size:.85rem;}
.res-table{width:100%;border-collapse:collapse;font-size:.79rem;}
.res-table th{background:#f8fafc;padding:8px 14px;text-align:left;font-size:.65rem;
  font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;}
.res-table td{padding:9px 14px;border-top:1px solid #f8fafc;color:var(--text);}
.res-table tr:hover td{background:#fafbff;}
.res-bar{flex:1;background:#f1f5f9;border-radius:100px;height:5px;overflow:hidden;}
.res-bar-f{height:100%;border-radius:100px;}
.res-total td{font-weight:700;border-top:2px solid var(--border);background:#f8fafc;}

/* ── Library ── */
.lib-card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);
  padding:14px 16px;display:flex;gap:14px;margin-bottom:10px;
  transition:border-color .15s,box-shadow .15s;box-shadow:var(--shadow);}
.lib-card:hover{border-color:#c7d2fe;}
.lib-card.overdue{border-left:4px solid var(--red);}
.lib-card.returned{opacity:.75;}
.lib-icon{width:46px;height:46px;border-radius:11px;background:#ede9fe;
  display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
.lib-card.overdue .lib-icon{background:var(--red-lt);}
.lib-card.returned .lib-icon{background:var(--green-lt);}
.lib-binfo{flex:1;min-width:0;}
.lib-btitle{font-size:.88rem;font-weight:700;color:var(--text);margin-bottom:2px;}
.lib-bauth{font-size:.74rem;color:var(--muted);margin-bottom:7px;}
.lib-bmeta{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:.71rem;color:var(--muted);}
.lib-bmeta span{display:flex;align-items:center;gap:4px;}

/* ── School Store ── */
.store-banner{display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,var(--primary),var(--primary-dk));
  border-radius:var(--radius);padding:16px 18px;margin-bottom:16px;color:#fff;box-shadow:var(--shadow);}
.store-banner-text{flex:1;min-width:0;}
.store-banner-title{font-size:.95rem;font-weight:800;display:flex;align-items:center;gap:7px;}
.store-banner-sub{font-size:.76rem;opacity:.85;margin-top:2px;}
.store-shop-btn{flex-shrink:0;background:#fff;color:var(--primary-dk);font-size:.8rem;font-weight:800;
  padding:9px 16px;border-radius:10px;display:flex;align-items:center;gap:6px;white-space:nowrap;
  text-decoration:none;transition:transform .15s;}
.store-shop-btn:active{transform:scale(.96);}
.store-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;}
@media(min-width:768px){.store-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));}}
.store-card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius-sm);
  overflow:hidden;display:block;text-decoration:none;color:inherit;transition:border-color .15s,box-shadow .15s;}
.store-card:active{border-color:var(--primary);}
.store-card-img{aspect-ratio:1/1;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;}
.store-card-img img{width:100%;height:100%;object-fit:contain;}
.store-card-img i{font-size:1.6rem;color:var(--subtle);}
.store-card-body{padding:9px 10px 11px;}
.store-card-name{font-size:.78rem;font-weight:700;color:var(--text);line-height:1.35;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:4px;min-height:2.1em;}
.store-card-price{font-size:.84rem;font-weight:800;color:var(--text);}
.store-card-mrp{font-size:.68rem;font-weight:500;color:var(--subtle);text-decoration:line-through;margin-left:4px;}

/* ── Siblings ── */
.sib-row{display:flex;gap:12px;overflow-x:auto;padding-bottom:4px;scrollbar-width:none;}
.sib-row::-webkit-scrollbar{display:none;}
.sib-mini{flex-shrink:0;text-align:center;width:80px;}
.sib-mini-av{width:52px;height:52px;border-radius:50%;margin:0 auto 6px;
  background:linear-gradient(135deg,var(--primary),var(--secondary));
  display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:800;color:#fff;
  overflow:hidden;border:2px solid var(--primary-lt);}
.sib-mini-av img{width:100%;height:100%;object-fit:cover;}
.sib-mini-name{font-size:.74rem;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.sib-mini-class{font-size:.65rem;color:var(--muted);margin-top:2px;}

/* ── Homework ── */
.hw-filter{display:flex;gap:6px;overflow-x:auto;padding-bottom:4px;margin-bottom:14px;scrollbar-width:none;}
.hw-filter::-webkit-scrollbar{display:none;}
.hw-filter-btn{flex-shrink:0;padding:6px 14px;border-radius:20px;font-size:.75rem;font-weight:600;
  border:1.5px solid var(--border);background:var(--card);color:var(--muted);cursor:pointer;transition:all .15s;}
.hw-filter-btn.active{background:var(--primary);border-color:var(--primary);color:#fff;}
.hw-card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);
  padding:14px 16px;margin-bottom:12px;box-shadow:var(--shadow);border-left-width:4px;}
.hw-card.pending{border-left-color:var(--orange);}
.hw-card.submitted{border-left-color:var(--teal);}
.hw-card.checked{border-left-color:var(--green);}
.hw-card.returned{border-left-color:#7c3aed;}
.hw-card.hidden{display:none;}
.hw-card-top{display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;}
.hw-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;
  font-size:1.2rem;flex-shrink:0;}
.hw-icon.pending{background:var(--orange-lt);}
.hw-icon.submitted{background:#ccfbf1;}
.hw-icon.checked{background:var(--green-lt);}
.hw-icon.returned{background:#f3e8ff;}
.hw-info{flex:1;min-width:0;}
.hw-title{font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:3px;}
.hw-meta{display:flex;flex-wrap:wrap;gap:4px 12px;font-size:.73rem;color:var(--muted);}
.hw-meta span{display:flex;align-items:center;gap:4px;}
.hw-due{font-size:.74rem;color:var(--orange);font-weight:600;margin-bottom:6px;display:flex;align-items:center;gap:5px;}
.hw-due.overdue{color:var(--red);}
.hw-desc{font-size:.8rem;color:#475569;line-height:1.55;margin-bottom:10px;
  display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
.hw-desc.expanded{-webkit-line-clamp:unset;}
.hw-img{margin-bottom:10px;}
.hw-img img{max-width:100%;max-height:180px;border-radius:var(--radius-sm);object-fit:contain;
  border:1.5px solid var(--border);cursor:zoom-in;}
.hw-submit-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;
  background:linear-gradient(135deg,#0f766e,var(--teal));border:none;color:#fff;
  border-radius:var(--radius-sm);font-size:.82rem;font-weight:700;cursor:pointer;
  width:100%;justify-content:center;margin-top:4px;transition:opacity .15s;}
.hw-submit-btn:hover{opacity:.9;}
.hw-your-ans{background:#f8fafc;border-radius:var(--radius-sm);padding:9px 12px;
  margin-bottom:8px;font-size:.8rem;color:var(--text);}
.hw-your-ans-lbl{font-size:.65rem;font-weight:700;color:var(--subtle);text-transform:uppercase;margin-bottom:4px;}
.hw-sub-img img{max-height:130px;border-radius:var(--radius-sm);border:1.5px solid var(--border);cursor:zoom-in;}
.hw-feedback{background:#f0fdfa;border:1px solid #99f6e4;border-radius:var(--radius-sm);
  padding:11px 13px;margin-top:8px;}
.hw-feedback-lbl{font-size:.68rem;font-weight:700;color:#0f766e;text-transform:uppercase;margin-bottom:5px;}
.hw-feedback-txt{font-size:.8rem;color:#134e4a;line-height:1.5;}
.hw-marks{font-size:.82rem;font-weight:700;color:#0f766e;margin-top:4px;}
.hw-awaiting{font-size:.74rem;color:var(--muted);margin-top:6px;display:flex;align-items:center;gap:5px;}

/* ── Nalish/Complaints ── */
.fab-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;
  padding:13px;background:linear-gradient(135deg,var(--red),#b91c1c);border:none;
  color:#fff;border-radius:var(--radius);font-size:.85rem;font-weight:700;cursor:pointer;
  margin-bottom:14px;box-shadow:0 4px 14px rgba(220,38,38,.3);transition:opacity .15s;}
.fab-btn:hover{opacity:.9;}
.cmp-card{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);
  padding:14px 16px;margin-bottom:10px;cursor:pointer;transition:all .15s;box-shadow:var(--shadow);}
.cmp-card:hover{border-color:#818cf8;box-shadow:0 4px 14px rgba(99,102,241,.1);}
.cmp-card:active{transform:scale(.99);}
.cmp-card-top{display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;}
.cmp-emoji{width:36px;height:36px;border-radius:9px;background:#fff0f0;display:flex;
  align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
.cmp-info{flex:1;min-width:0;}
.cmp-subject{font-size:.88rem;font-weight:700;color:var(--text);margin-bottom:2px;}
.cmp-preview{font-size:.76rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cmp-arrow{color:var(--subtle);font-size:.9rem;flex-shrink:0;margin-top:2px;}
.cmp-foot{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.cmp-att{font-size:.7rem;color:var(--muted);display:flex;align-items:center;gap:3px;}
.cmp-date{font-size:.7rem;color:var(--subtle);margin-left:auto;}
.cmp-s{padding:2px 9px;border-radius:20px;font-size:.68rem;font-weight:700;}
.cmp-s.open{background:var(--red-lt);color:#b91c1c;}
.cmp-s.in_progress{background:var(--orange-lt);color:#92400e;}
.cmp-s.resolved{background:var(--green-lt);color:#15803d;}
.cmp-s.closed{background:#f1f5f9;color:var(--muted);}

/* ── More tab grid ── */
.more-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-bottom:14px;}
.more-item{background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);
  padding:18px 14px;text-align:center;cursor:pointer;transition:all .15s;box-shadow:var(--shadow);}
.more-item:hover{border-color:var(--primary);box-shadow:0 4px 14px rgba(79,70,229,.12);transform:translateY(-1px);}
.more-item:active{transform:scale(.97);}
.more-icon{width:48px;height:48px;border-radius:12px;margin:0 auto 10px;
  display:flex;align-items:center;justify-content:center;font-size:1.4rem;}
.more-label{font-size:.82rem;font-weight:700;color:var(--text);}
.more-badge{display:inline-flex;margin-top:5px;padding:2px 8px;border-radius:20px;
  font-size:.65rem;font-weight:700;background:var(--red-lt);color:#b91c1c;}

/* ── School info card ── */
.school-info-row{display:flex;align-items:center;gap:8px;padding:9px 0;
  border-bottom:1px solid #f8fafc;font-size:.8rem;color:var(--text);}
.school-info-row:last-child{border-bottom:none;padding-bottom:0;}
.school-info-row i{color:var(--primary);font-size:.85rem;flex-shrink:0;width:18px;}
.school-info-row a{color:var(--text);text-decoration:none;}
.school-info-row a:hover{color:var(--primary);}

/* ── Modals ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);
  z-index:300;backdrop-filter:blur(2px);}
.modal-overlay.show{display:flex;align-items:flex-end;justify-content:center;}
.modal-box{background:#fff;border-radius:20px 20px 0 0;width:100%;max-height:92dvh;
  overflow:hidden;display:flex;flex-direction:column;
  animation:slideUp .22s cubic-bezier(.2,.8,.2,1);box-shadow:0 -8px 40px rgba(0,0,0,.18);}
.modal-box.wide{max-width:680px;}
@keyframes slideUp{from{transform:translateY(60px);opacity:0}to{transform:translateY(0);opacity:1}}
.modal-head{padding:16px 20px;display:flex;align-items:center;justify-content:space-between;
  background:linear-gradient(135deg,var(--primary-dk),var(--primary));flex-shrink:0;}
.modal-head.teal{background:linear-gradient(135deg,#0f766e,var(--teal));}
.modal-head.red{background:linear-gradient(135deg,#b91c1c,var(--red));}
.modal-head-left{display:flex;flex-direction:column;gap:2px;}
.modal-title{font-size:.95rem;font-weight:700;color:#fff;}
.modal-subtitle{font-size:.72rem;color:rgba(255,255,255,.72);}
.modal-close{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.18);
  border:none;color:#fff;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;
  flex-shrink:0;transition:background .15s;}
.modal-close:hover{background:rgba(255,255,255,.28);}
.modal-body{padding:18px 20px;overflow-y:auto;flex:1;}
.modal-body.p0{padding:0;}
.modal-foot{padding:12px 20px;background:#f8fafc;border-top:1px solid var(--border);
  display:flex;gap:10px;justify-content:flex-end;flex-shrink:0;
  padding-bottom:calc(12px + var(--safe-b));}
.modal-msg{padding:10px 13px;border-radius:var(--radius-sm);font-size:.82rem;margin-bottom:14px;display:none;}
.modal-msg.ok{background:var(--green-lt);color:#15803d;}
.modal-msg.err{background:var(--red-lt);color:#b91c1c;}

/* ── Form elements ── */
.fld-label{display:block;font-size:.7rem;font-weight:700;color:var(--muted);
  text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;}
.fld-input{width:100%;padding:11px 13px;border:1.5px solid var(--border);border-radius:var(--radius-sm);
  font-size:.85rem;font-family:inherit;color:var(--text);transition:border-color .15s,box-shadow .15s;}
.fld-input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,70,229,.1);}
select.fld-input{appearance:auto;}
.fld-group{margin-bottom:14px;}
.fld-group:last-child{margin-bottom:0;}
.file-drop{border:2px dashed var(--border);border-radius:var(--radius-sm);padding:16px;
  text-align:center;cursor:pointer;transition:border-color .15s;}
.file-drop:hover{border-color:#818cf8;}
.file-drop i{font-size:1.5rem;display:block;margin-bottom:6px;color:#818cf8;}
.file-drop-lbl{font-size:.8rem;font-weight:600;color:var(--primary);cursor:pointer;}
.file-drop-sub{font-size:.7rem;color:var(--muted);margin-top:4px;}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:var(--radius-sm);
  font-size:.84rem;font-weight:700;cursor:pointer;border:none;transition:opacity .15s;}
.btn:hover{opacity:.88;}
.btn:active{opacity:.75;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-outline{background:#fff;border:1.5px solid var(--border);color:var(--text);}
.btn-sm{padding:8px 16px;font-size:.78rem;}
.btn:disabled{opacity:.55;cursor:not-allowed;}

/* ── Thread ── */
.thread-msgs{padding:14px;display:flex;flex-direction:column;gap:0;min-height:120px;max-height:380px;overflow-y:auto;}
.thread-msg-wrap{display:flex;margin-bottom:10px;}
.thread-msg-wrap.admin{justify-content:flex-end;}
.thread-msg-wrap.student{justify-content:flex-start;}
.thread-bubble{max-width:82%;padding:10px 13px;border-radius:12px;font-size:.83rem;line-height:1.5;}
.thread-bubble.admin{background:var(--primary);color:#fff;border-bottom-right-radius:3px;}
.thread-bubble.student{background:#f1f5f9;color:var(--text);border-bottom-left-radius:3px;}
.thread-who{font-size:.65rem;font-weight:700;margin-bottom:3px;}
.thread-bubble.admin .thread-who{color:rgba(255,255,255,.75);}
.thread-bubble.student .thread-who{color:var(--primary);}
.thread-time{font-size:.6rem;opacity:.6;margin-top:3px;}
.thread-att{max-width:200px;max-height:150px;border-radius:8px;margin-top:6px;display:block;}
.thread-input-row{padding:12px 16px;border-top:1px solid var(--border);background:#fafbff;flex-shrink:0;}
.thread-att-row{display:flex;align-items:center;gap:8px;margin-top:8px;}

/* ── Cropper ── */
.crop-area{display:none;margin-top:14px;}
.crop-canvas{background:#1e293b;border-radius:10px;overflow:hidden;max-height:280px;}
.crop-canvas img{display:block;max-width:100%;}
.crop-tools{display:flex;gap:8px;margin-top:10px;align-items:center;}

/* ── Image viewer ── */
#imgViewer{display:none;position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:500;
  align-items:center;justify-content:center;cursor:zoom-out;}
#imgViewer.show{display:flex;}
#imgViewer img{max-width:95%;max-height:95dvh;border-radius:8px;object-fit:contain;}
#imgViewer .iv-close{position:absolute;top:16px;right:16px;width:36px;height:36px;
  background:rgba(255,255,255,.2);border:none;border-radius:50%;color:#fff;font-size:1.2rem;
  cursor:pointer;display:flex;align-items:center;justify-content:center;}

/* ── Responsive ── */
@media(max-width:640px){
  .hero-top{gap:12px;}
  .hero-photo{width:68px;height:68px;font-size:1.7rem;}
  .hero-name{font-size:1.05rem;}
  .hstat-val{font-size:.85rem;}
  .fee-sum-row{grid-template-columns:repeat(3,1fr);}
  .att-mini-chips{grid-template-columns:repeat(3,1fr);}
  .fi-grid{grid-template-columns:1fr 1fr;}
  .res-table th:nth-child(4),.res-table td:nth-child(4),
  .res-table th:nth-child(5),.res-table td:nth-child(5){display:none;}
  .more-grid{grid-template-columns:repeat(2,1fr);}
  .app-bar-name{max-width:130px;}
}
@media(max-width:380px){
  .fi-grid{grid-template-columns:1fr;}
  .fee-sum-row{grid-template-columns:1fr 1fr;}
  .fee-sum.total{display:none;}
}
/* ── Messages tab – WhatsApp style ── */
/* The bottom nav is fully hidden while chatting (see showTab()), so this
   panel gets the whole remaining height below the app-bar — no need to
   reserve room for a nav that isn't there. Uses 100dvh, not 100vh: on real
   mobile browsers/WebViews 100vh can overshoot the true visible area (it
   doesn't track the browser's own dynamic UI chrome). The bottom margin
   cancels .main-content's own reserved bottom padding (which every OTHER
   tab still needs, for the nav that's visible there) so this panel doesn't
   leave empty scroll room below itself. */
#panel_messages.active{display:flex !important;flex-direction:column;
  height:calc(100dvh - var(--chrome-top));
  margin:-16px -16px calc(-20px - var(--chrome-bottom));
  background:#efeae2;overflow:hidden;}
.stu-thread-hdr{padding:10px 16px;background:#f0f2f5;display:flex;align-items:center;gap:12px;border-bottom:1px solid #d1d7db;flex-shrink:0;}
.stu-thread-back{background:none;border:none;color:#54656f;font-size:1.2rem;cursor:pointer;
  padding:4px;display:flex;align-items:center;flex-shrink:0;-webkit-tap-highlight-color:transparent;}
.stu-thread-hdr-av{width:40px;height:40px;border-radius:50%;background:#4f46e5;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
.stu-thread-hdr-name{font-size:.93rem;font-weight:700;color:#111b21;}
.stu-thread-hdr-sub{font-size:.73rem;color:#667781;}
.stu-thread-wrap{flex:1;overflow-y:auto;padding:12px 16px;display:flex;flex-direction:column;gap:2px;}
.stu-bubble{display:flex;flex-direction:column;max-width:75%;margin:1px 0;}
.stu-bubble.out{align-self:flex-end;}
.stu-bubble.in{align-self:flex-start;}
.stu-bubble-inner{padding:8px 12px 24px;border-radius:10px;font-size:.85rem;line-height:1.55;position:relative;word-break:break-word;min-width:90px;box-shadow:0 1px 2px rgba(0,0,0,.08);}
.stu-bubble.out .stu-bubble-inner{background:#d9eeff;border-top-right-radius:2px;}
.stu-bubble.in  .stu-bubble-inner{background:#fff;border-top-left-radius:2px;}
.stu-bubble-footer{display:flex;align-items:center;justify-content:flex-end;gap:5px;position:absolute;bottom:4px;right:8px;}
.stu-bubble-meta{font-size:.63rem;color:#667781;white-space:nowrap;}
.stu-bubble-img{max-width:100%;border-radius:8px;margin-bottom:6px;cursor:zoom-in;display:block;max-height:220px;object-fit:cover;}
.stu-date-sep{text-align:center;margin:8px 0;}
.stu-date-sep span{background:rgba(225,220,215,.9);border-radius:8px;padding:4px 14px;font-size:.71rem;color:#54656f;font-weight:600;}
.stu-auto-badge{font-size:.68rem;color:#64748b;margin-bottom:3px;display:flex;align-items:center;gap:3px;}
.stu-del-btn{background:none;border:none;cursor:pointer;color:#b0b8c1;padding:0 2px;font-size:.68rem;line-height:1;opacity:0;transition:.1s;border-radius:3px;}
.stu-bubble:hover .stu-del-btn{opacity:1;}
.stu-del-btn:hover{color:#ef4444;}
.stu-img-prev-bar{display:none;align-items:center;gap:8px;padding:6px 12px;background:#f0f2f5;border-top:1px solid #d1d7db;flex-shrink:0;}
.stu-img-prev-bar img{height:44px;border-radius:6px;object-fit:cover;}
.stu-compose-wrap{padding:8px 12px calc(10px + var(--safe-b,0px));background:#f0f2f5;display:flex;gap:8px;align-items:flex-end;border-top:1px solid #d1d7db;flex-shrink:0;}
.stu-attach-btn{background:none;border:none;cursor:pointer;color:#54656f;padding:8px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;}
.stu-attach-btn:hover{background:#e9edef;color:#111b21;}
.stu-compose-input{flex:1;border:none;background:#fff;border-radius:22px;padding:9px 14px;font-size:.85rem;resize:none;max-height:120px;min-height:42px;outline:none;font-family:inherit;line-height:1.5;color:#111b21;}
.stu-compose-input::placeholder{color:#8696a0;}
.stu-send-btn{background:#2563eb;color:#fff;border:none;border-radius:50%;font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;height:42px;width:42px;flex-shrink:0;transition:.15s;}
.stu-send-btn:hover{background:#1d4ed8;}
.stu-send-btn:disabled{background:#93c5fd;cursor:not-allowed;}
</style>
</head>
<body>

<!-- ── Top App Bar ── -->
<header class="app-bar">
  <div class="app-bar-school">
    <div class="app-bar-logo">
      <?php if ($schoolLogoUrl): ?><img src="<?= htmlspecialchars($schoolLogoUrl) ?>" alt=""><?php else: ?><?= htmlspecialchars(strtoupper(mb_substr($stu['school_name']??'S',0,1))) ?><?php endif; ?>
    </div>
    <div>
      <div class="app-bar-name"><?= htmlspecialchars($stu['school_name']??'School') ?></div>
      <div class="app-bar-sub"><?= htmlspecialchars(($stu['class_name']??'').($stu['section_name']?' · '.$stu['section_name']:'')) ?> &middot; <?= $acadYear ?>–<?= $acadYear+1 ?></div>
    </div>
  </div>
  <div class="app-bar-right">
    <div class="app-bar-av">
      <?php if ($photoUrl): ?><img src="<?= htmlspecialchars($photoUrl) ?>" alt=""><?php else: ?><?= htmlspecialchars($initial) ?><?php endif; ?>
    </div>
    <a href="<?= BASE_URL ?>/student/logout.php" class="btn-logout">
      <i class="bi bi-box-arrow-right"></i><span> Logout</span>
    </a>
  </div>
</header>

<!-- Desktop-width tab bar — hidden by default (mobile uses the bottom nav
     below instead); shown only at min-width:768px, exactly the original
     pre-redesign layout for anyone opening this in a real desktop browser.
     Same class="tab-btn"/data-tab/onclick as the mobile buttons so showTab()
     and every badge-update lookup works identically either way. -->
<nav class="tab-bar-desktop" id="tabBarDesktop">
  <button class="tab-btn dtab-btn active" data-tab="home" onclick="showTab('home',this)"><i class="bi bi-house-fill"></i> Home</button>
  <button class="tab-btn dtab-btn" data-tab="homework" onclick="showTab('homework',this)"><i class="bi bi-book-half"></i> Homework<?php if($pendingHwCount>0): ?><span class="tab-cnt orange"><?= $pendingHwCount ?></span><?php endif; ?></button>
  <button class="tab-btn dtab-btn" data-tab="attendance" onclick="showTab('attendance',this)"><i class="bi bi-calendar-check"></i> Attendance</button>
  <button class="tab-btn dtab-btn" data-tab="fees" onclick="showTab('fees',this)"><i class="bi bi-cash-stack"></i> Fees<?php if($feeSummary['due']>0): ?><span class="tab-dot"></span><?php endif; ?></button>
  <button class="tab-btn dtab-btn" data-tab="nalish" onclick="showTab('nalish',this)"><i class="bi bi-megaphone-fill"></i> Complaints<?php if($openComplaints>0): ?><span class="tab-cnt"><?= $openComplaints ?></span><?php endif; ?></button>
  <button class="tab-btn dtab-btn" data-tab="results" onclick="showTab('results',this)"><i class="bi bi-bar-chart-fill"></i> Results<?php if(count($examResults)>0): ?><span class="tab-cnt green"><?= count($examResults) ?></span><?php endif; ?></button>
  <button class="tab-btn dtab-btn" data-tab="progress" onclick="showTab('progress',this)"><i class="bi bi-graph-up-arrow"></i> Progress</button>
  <button class="tab-btn dtab-btn" data-tab="library" onclick="showTab('library',this)"><i class="bi bi-book-fill"></i> Library<?php if($libStats['overdue']>0): ?><span class="tab-cnt"><?= $libStats['overdue'] ?></span><?php endif; ?></button>
  <?php if ($storeEnabled): ?>
  <button class="tab-btn dtab-btn" data-tab="store" onclick="showTab('store',this)"><i class="bi bi-shop"></i> Store</button>
  <?php endif; ?>
  <button class="tab-btn dtab-btn" data-tab="profile" onclick="showTab('profile',this)"><i class="bi bi-person-lines-fill"></i> Profile</button>
  <button class="tab-btn dtab-btn" data-tab="bus" onclick="showTab('bus',this);initBusTab()"><i class="bi bi-bus-front-fill"></i> Bus</button>
  <button class="tab-btn dtab-btn" data-tab="messages" onclick="showTab('messages',this);loadStuMessages()"><i class="bi bi-chat-dots-fill"></i> Messages<span class="tab-cnt stu-msg-badge-el" style="display:none;"></span></button>
</nav>

<!-- ── Hero ── -->
<div class="hero">
  <div class="hero-card">
    <div class="hero-top">
      <div class="hero-photo-wrap">
        <div class="hero-photo" id="heroPhoto">
          <?php if ($photoUrl): ?><img src="<?= htmlspecialchars($photoUrl) ?>" alt=""><?php else: ?><span><?= htmlspecialchars($initial) ?></span><?php endif; ?>
        </div>
        <?php if (!$pendingProfileReq): ?>
        <button class="hero-photo-edit" onclick="openEditProfile()" title="Edit Profile"><i class="bi bi-pencil-fill"></i></button>
        <?php endif; ?>
      </div>
      <div class="hero-info">
        <div class="hero-name"><?= htmlspecialchars($stu['name']) ?></div>
        <div class="hero-meta">
          <span><i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars(trim(($stu['class_name']??'').' '.($stu['section_name']??''))) ?></span>
          <span><i class="bi bi-card-text"></i> <?= htmlspecialchars($stu['admission_no']) ?></span>
          <?php if ($stu['roll_no']): ?><span><i class="bi bi-person-badge-fill"></i> Roll <?= htmlspecialchars($stu['roll_no']) ?></span><?php endif; ?>
        </div>
        <?php if ($pendingProfileReq): ?>
          <div class="hero-pending"><i class="bi bi-hourglass-split"></i> Profile update pending approval</div>
        <?php else: ?>
          <button class="hero-edit-btn" onclick="openEditProfile()"><i class="bi bi-pencil-fill"></i> Edit Profile</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="hero-stats">
      <div class="hstat <?= $attPct >= 75 ? 'green' : 'red' ?>">
        <div class="hstat-val"><?= $attPct ?>%</div>
        <div class="hstat-lbl">Attendance</div>
      </div>
      <div class="hstat <?= $feeSummary['due'] > 0 ? 'red' : 'green' ?>">
        <div class="hstat-val">₹<?= number_format($feeSummary['due'],0) ?></div>
        <div class="hstat-lbl">Fee Due</div>
      </div>
      <div class="hstat <?= $pendingHwCount > 0 ? 'yellow' : 'green' ?>">
        <div class="hstat-val"><?= $pendingHwCount ?></div>
        <div class="hstat-lbl">HW Pending</div>
      </div>
      <div class="hstat <?= $openComplaints > 0 ? 'red' : 'green' ?>">
        <div class="hstat-val"><?= $openComplaints ?></div>
        <div class="hstat-lbl">Complaints</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Tab Bar ── -->
<!-- Bottom Navigation — native app pattern: the 4 things a parent checks most,
     everything else lives one tap away in "More". Every button keeps the exact
     same class="tab-btn" / data-tab / onclick as before so all existing JS
     (badge updates, tab-restore-on-load, initBusTab(), loadStuMessages()) keeps
     working unchanged, whether the button lives here or inside the sheet. -->
<nav class="bottom-nav" id="tabBar">
  <button class="tab-btn bnav-btn active" data-tab="home" onclick="showTab('home',this)"><i class="bi bi-house-fill"></i><span>Home</span></button>
  <button class="tab-btn bnav-btn" data-tab="homework" onclick="showTab('homework',this)"><i class="bi bi-book-half"></i><span>Homework</span><?php if($pendingHwCount>0): ?><span class="tab-cnt orange"><?= $pendingHwCount ?></span><?php endif; ?></button>
  <button class="tab-btn bnav-btn" data-tab="attendance" onclick="showTab('attendance',this)"><i class="bi bi-calendar-check"></i><span>Attendance</span></button>
  <button class="tab-btn bnav-btn" data-tab="fees" onclick="showTab('fees',this)"><i class="bi bi-cash-stack"></i><span>Fees</span><?php if($feeSummary['due']>0): ?><span class="tab-dot"></span><?php endif; ?></button>
  <button class="bnav-btn" id="moreNavBtn" onclick="openMoreSheet()"><i class="bi bi-grid-3x3-gap-fill"></i><span>More</span><?php if($openComplaints>0 || $libStats['overdue']>0): ?><span class="tab-dot" id="moreDot"></span><?php else: ?><span class="tab-dot" id="moreDot" style="display:none;"></span><?php endif; ?></button>
</nav>

<div class="more-sheet-overlay" id="moreSheetOverlay" onclick="if(event.target===this)closeMoreSheet()">
  <div class="more-sheet">
    <div class="more-sheet-handle"></div>
    <div class="more-sheet-title">More</div>
    <button class="tab-btn more-item" data-tab="nalish" onclick="showTab('nalish',this);closeMoreSheet()"><i class="bi bi-megaphone-fill"></i>Complaints<?php if($openComplaints>0): ?><span class="tab-cnt"><?= $openComplaints ?></span><?php endif; ?></button>
    <button class="tab-btn more-item" data-tab="results" onclick="showTab('results',this);closeMoreSheet()"><i class="bi bi-bar-chart-fill"></i>Results<?php if(count($examResults)>0): ?><span class="tab-cnt green"><?= count($examResults) ?></span><?php endif; ?></button>
    <button class="tab-btn more-item" data-tab="progress" onclick="showTab('progress',this);closeMoreSheet()"><i class="bi bi-graph-up-arrow"></i>Progress</button>
    <button class="tab-btn more-item" data-tab="library" onclick="showTab('library',this);closeMoreSheet()"><i class="bi bi-book-fill"></i>Library<?php if($libStats['overdue']>0): ?><span class="tab-cnt"><?= $libStats['overdue'] ?></span><?php endif; ?></button>
    <?php if ($storeEnabled): ?>
    <button class="tab-btn more-item" data-tab="store" onclick="showTab('store',this);closeMoreSheet()"><i class="bi bi-shop"></i>Store</button>
    <?php endif; ?>
    <button class="tab-btn more-item" data-tab="bus" onclick="showTab('bus',this);initBusTab();closeMoreSheet()"><i class="bi bi-bus-front-fill"></i>Bus</button>
    <button class="tab-btn more-item" data-tab="messages" onclick="showTab('messages',this);loadStuMessages();closeMoreSheet()"><i class="bi bi-chat-dots-fill"></i>Messages<span class="tab-cnt stu-msg-badge-el" style="display:none;"></span></button>
    <button class="tab-btn more-item" data-tab="profile" onclick="showTab('profile',this);closeMoreSheet()"><i class="bi bi-person-lines-fill"></i>Profile</button>
  </div>
</div>

<div class="main-content">

  <!-- ── HOME TAB (default active) ── -->
  <div id="panel_home" class="panel active">

    <!-- School info -->
    <div class="card">
      <div class="card-head">
        <div class="card-head-left"><i class="bi bi-building-fill"></i> School</div>
      </div>
      <div class="card-body">
        <?php if ($schoolAddr): ?><div class="school-info-row"><i class="bi bi-geo-alt-fill"></i><?= htmlspecialchars($schoolAddr) ?></div><?php endif; ?>
        <?php if (!empty($stu['school_phone'])): ?><div class="school-info-row"><i class="bi bi-telephone-fill"></i><a href="tel:<?= htmlspecialchars($stu['school_phone']) ?>"><?= htmlspecialchars($stu['school_phone']) ?></a></div><?php endif; ?>
        <?php if (!empty($stu['school_email'])): ?><div class="school-info-row"><i class="bi bi-envelope-fill"></i><a href="mailto:<?= htmlspecialchars($stu['school_email']) ?>"><?= htmlspecialchars($stu['school_email']) ?></a></div><?php endif; ?>
      </div>
    </div>

    <!-- Siblings -->
    <?php if (!empty($siblings)): ?>
    <div class="card">
      <div class="card-head">
        <div class="card-head-left"><i class="bi bi-people-fill"></i> Siblings (<?= count($siblings) ?>)</div>
      </div>
      <div class="card-body">
        <div class="sib-row">
          <?php foreach ($siblings as $sib):
            $sp = !empty($sib['photo']) ? BASE_URL.'/assets/uploads/photos/'.$sib['photo'] : '';
            $si = strtoupper(mb_substr($sib['name'],0,1));
          ?>
          <div class="sib-mini">
            <div class="sib-mini-av"><?php if($sp): ?><img src="<?= htmlspecialchars($sp) ?>" alt=""><?php else: ?><?= $si ?><?php endif; ?></div>
            <div class="sib-mini-name"><?= htmlspecialchars(mb_substr($sib['name'],0,10)) ?></div>
            <div class="sib-mini-class"><?= htmlspecialchars(trim(($sib['class_name']??'').' '.($sib['section_name']??''))) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Quick shortcuts -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
      <button onclick="showTab('homework',document.querySelector('[data-tab=homework]'))" style="background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);padding:16px 14px;cursor:pointer;text-align:left;box-shadow:var(--shadow);transition:all .15s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:1.5rem;margin-bottom:6px;">📚</div>
        <div style="font-size:.82rem;font-weight:700;color:var(--text);">Homework</div>
        <div style="font-size:.72rem;color:<?= $pendingHwCount>0?'var(--orange)':'var(--muted)' ?>;font-weight:600;"><?= $pendingHwCount > 0 ? $pendingHwCount.' pending' : 'All done!' ?></div>
      </button>
      <button onclick="showTab('attendance',document.querySelector('[data-tab=attendance]'))" style="background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);padding:16px 14px;cursor:pointer;text-align:left;box-shadow:var(--shadow);transition:all .15s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:1.5rem;margin-bottom:6px;">📅</div>
        <div style="font-size:.82rem;font-weight:700;color:var(--text);">Attendance</div>
        <div style="font-size:.72rem;color:<?= $attPct>=75?'var(--green)':'var(--red)' ?>;font-weight:600;"><?= $attPct ?>% this year</div>
      </button>
      <button onclick="showTab('fees',document.querySelector('[data-tab=fees]'))" style="background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);padding:16px 14px;cursor:pointer;text-align:left;box-shadow:var(--shadow);transition:all .15s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:1.5rem;margin-bottom:6px;">💰</div>
        <div style="font-size:.82rem;font-weight:700;color:var(--text);">Fees</div>
        <div style="font-size:.72rem;color:<?= $feeSummary['due']>0?'var(--red)':'var(--green)' ?>;font-weight:600;"><?= $feeSummary['due']>0?'₹'.number_format($feeSummary['due'],0).' due':'All paid' ?></div>
      </button>
      <button onclick="openNewComplaint()" style="background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius);padding:16px 14px;cursor:pointer;text-align:left;box-shadow:var(--shadow);transition:all .15s;" onmouseover="this.style.borderColor='var(--red)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:1.5rem;margin-bottom:6px;">📢</div>
        <div style="font-size:.82rem;font-weight:700;color:var(--text);">Complaints</div>
        <div style="font-size:.72rem;color:var(--muted);">Report an issue</div>
      </button>
    </div>

  </div><!-- /home -->

  <!-- ── PROFILE TAB ── -->
  <div id="panel_profile" class="panel">

    <div class="card">
      <div class="card-head"><div class="card-head-left"><i class="bi bi-person-circle"></i> Personal Information</div></div>
      <div class="card-body">
        <div class="fi-grid">
          <?= fld('Full Name', $stu['name']) ?>
          <?= fld('Date of Birth', fmtDate($stu['dob'])) ?>
          <?= fld('Gender', $stu['gender'] ? ucfirst($stu['gender']) : null) ?>
          <?= fld('Blood Group', $stu['blood_group']) ?>
          <?= fld('Nationality', $stu['nationality']) ?>
          <?= fld('Religion', $stu['religion']) ?>
          <?= fld('Caste / Category', $stu['caste_category']) ?>
          <?= fld('Mother Tongue', $stu['mother_tongue']) ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="card-head-left"><i class="bi bi-mortarboard-fill"></i> Academic Information</div></div>
      <div class="card-body">
        <div class="fi-grid">
          <?= fld('Admission No.', $stu['admission_no']) ?>
          <?= fld('Class', $stu['class_name']) ?>
          <?= fld('Section', $stu['section_name']) ?>
          <?= fld('Roll Number', $stu['roll_no']) ?>
          <?= fld('Admission Date', fmtDate($stu['admission_date'])) ?>
          <?= fld('Previous School', $stu['previous_school']) ?>
          <?= fld('Bus Route', $stu['bus_route']) ?>
          <?= fld('House', $stu['house']) ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div class="card-head-left"><i class="bi bi-people-fill"></i> Guardian Information</div></div>
      <div class="card-body">
        <div class="fi-grid">
          <?= fld("Father's Name", $stu['father_name']) ?>
          <?= fld("Mother's Name", $stu['mother_name']) ?>
          <?= fld('Guardian Phone', $stu['guardian_phone']) ?>
          <?= fld('Email', $stu['email']) ?>
        </div>
        <?php if (!empty($stu['address'])): ?>
        <div class="fi-full" style="margin-top:10px;"><div class="fi-l">Address</div><div class="fi-v"><?= htmlspecialchars($stu['address']) ?></div></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($stu['emergency_contact_name'] || $stu['medical_notes']): ?>
    <div class="card">
      <div class="card-head"><div class="card-head-left"><i class="bi bi-heart-pulse-fill"></i> Emergency &amp; Medical</div></div>
      <div class="card-body">
        <div class="fi-grid">
          <?= fld('Emergency Contact', $stu['emergency_contact_name']) ?>
          <?= fld('Emergency Phone', $stu['emergency_contact_phone']) ?>
        </div>
        <?php if ($stu['medical_notes']): ?>
        <div class="fi-full" style="margin-top:10px;"><div class="fi-l">Medical Notes</div><div class="fi-v"><?= htmlspecialchars($stu['medical_notes']) ?></div></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($stu['aadhar_no'] || $stu['apaar_id'] || $stu['pen_number']): ?>
    <div class="card">
      <div class="card-head"><div class="card-head-left"><i class="bi bi-file-earmark-text-fill"></i> Documents &amp; IDs</div></div>
      <div class="card-body">
        <div class="fi-grid">
          <?= fld('APAAR / ABC ID', $stu['apaar_id']) ?>
          <?= fld('Aadhar Number', $stu['aadhar_no'] ? '****-****-'.substr($stu['aadhar_no'],-4) : null) ?>
          <?= fld('PEN Number', $stu['pen_number']) ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div style="padding:12px;text-align:center;">
      <?php if (!$pendingProfileReq): ?>
      <button onclick="openEditProfile()" class="btn btn-primary" style="width:100%;justify-content:center;">
        <i class="bi bi-pencil-fill"></i> Edit My Profile
      </button>
      <?php else: ?>
      <div class="alert info"><i class="bi bi-hourglass-split"></i> Your profile change request is pending school approval.</div>
      <?php endif; ?>
    </div>

  </div><!-- /profile -->

  <!-- ── ATTENDANCE TAB ── -->
  <div id="panel_attendance" class="panel">

    <?php if (count($attYearOptions) > 1): ?>
    <div class="att-year-select-row">
      <label for="attYearSelect"><i class="bi bi-calendar-range"></i> Session</label>
      <select id="attYearSelect" onchange="location.href='?att_year='+this.value+'#attendance'">
        <?php foreach ($attYearOptions as $ay): ?>
        <option value="<?= $ay ?>" <?= $ay === $acadYear ? 'selected' : '' ?>><?= $ay ?>–<?= $ay+1 ?><?= $ay === $currentAcadYear ? ' (Current)' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <?php if ($attStats['total'] > 0): ?>

    <div class="att-hero">
      <div class="att-ring-wrap">
        <div class="att-ring" style="--ring-p:<?= min($attPct,100) ?>;--ring-c:<?= $attPct>=75?'#16a34a':'#dc2626' ?>;">
          <div class="att-ring-inner">
            <div class="att-ring-val" style="color:<?= $attPct>=75?'#16a34a':'#dc2626' ?>;"><?= $attPct ?>%</div>
            <div class="att-ring-lbl">Rate</div>
          </div>
        </div>
      </div>
      <div class="att-mini-chips">
        <div class="att-mini present"><span class="av"><?= $attStats['present'] ?></span><span class="al">Present</span></div>
        <div class="att-mini absent"><span class="av"><?= $attStats['absent'] ?></span><span class="al">Absent</span></div>
        <div class="att-mini late"><span class="av"><?= $attStats['late'] ?></span><span class="al">Late</span></div>
        <div class="att-mini leave"><span class="av"><?= $attStats['leave'] ?></span><span class="al">Leave</span></div>
        <div class="att-mini hday"><span class="av"><?= $attStats['half_day'] ?></span><span class="al">Half Day</span></div>
        <div class="att-mini total"><span class="av"><?= $attStats['total'] ?></span><span class="al">Total</span></div>
      </div>
    </div>

    <?php if ($attPct < 75): ?>
    <div class="alert danger"><i class="bi bi-exclamation-triangle-fill"></i> Attendance <?= $attPct ?>% is below the 75% minimum requirement. Please ensure regular attendance.</div>
    <?php else: ?>
    <div class="alert success"><i class="bi bi-check-circle-fill"></i> Good attendance! Keep it up throughout the year.</div>
    <?php endif; ?>

    <!-- Month / Year view switch — big, simple, thumb-friendly buttons -->
    <div class="att-view-switch">
      <button class="att-view-btn active" id="attViewBtnMonth" onclick="attSetView('month')">
        <i class="bi bi-calendar3"></i> Month View
      </button>
      <button class="att-view-btn" id="attViewBtnYear" onclick="attSetView('year')">
        <i class="bi bi-bar-chart-fill"></i> Year View
      </button>
    </div>

    <!-- Attendance Calendar (Month View) -->
    <div class="att-cal-card" id="attMonthView">
      <div class="att-cal-nav">
        <button class="att-cal-nav-btn" onclick="attCalPrev()"><i class="bi bi-chevron-left"></i></button>
        <div class="att-cal-title" id="attCalTitle"></div>
        <button class="att-cal-nav-btn" onclick="attCalNext()"><i class="bi bi-chevron-right"></i></button>
      </div>
      <div class="att-cal-grid" id="attCalGrid"></div>
      <div class="att-cal-hol-list" id="attCalHolList" style="display:none;"></div>
      <div class="att-cal-legend">
        <div class="att-cal-leg"><div class="att-cal-leg-dot" style="background:#dcfce7;"></div> Present</div>
        <div class="att-cal-leg"><div class="att-cal-leg-dot" style="background:#fce7f3;"></div> Absent</div>
        <div class="att-cal-leg"><div class="att-cal-leg-dot" style="background:#fef9c3;"></div> Late</div>
        <div class="att-cal-leg"><div class="att-cal-leg-dot" style="background:#dbeafe;"></div> Half Day</div>
        <div class="att-cal-leg"><div class="att-cal-leg-dot" style="background:#f3e8ff;"></div> Leave</div>
        <div class="att-cal-leg"><div class="att-cal-leg-dot" style="background:#fee2e2;"></div> Sunday</div>
        <div class="att-cal-leg"><div class="att-cal-leg-dot" style="background:#e0e7ff;"></div> Official Holiday</div>
        <div class="att-cal-leg"><div class="att-cal-leg-dot" style="background:#fae8ff;"></div> Special Holiday</div>
        <div class="att-cal-leg"><div class="att-cal-leg-dot" style="background:#f8fafc;border:1px solid #e2e8f0;"></div> No Record</div>
      </div>
    </div>

    <!-- Year at a Glance (Year View) -->
    <div class="att-cal-card" id="attYearView" style="display:none;">
      <div class="att-cal-nav" style="justify-content:center;">
        <div class="att-cal-title"><i class="bi bi-calendar-range"></i>&nbsp; <?= $acadYear ?> – <?= $acadYear+1 ?> · Whole Year</div>
      </div>
      <div id="attYearGrid" class="att-year-grid"></div>
    </div>

    <?php if (!empty($missedByDate)): ?>
    <div class="card">
      <div class="card-head"><div class="card-head-left"><i class="bi bi-journal-x" style="color:var(--red);"></i> Lessons Missed on Absent Days</div></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($missedByDate as $mDate => $mRows): ?>
        <div>
          <div style="font-size:.76rem;font-weight:700;color:var(--text);margin-bottom:6px;display:flex;align-items:center;gap:6px;">
            <span class="badge badge-red"><?= fmtDate($mDate,'d M Y') ?></span>
            <span style="color:var(--muted);font-weight:500;">— absent this day</span>
          </div>
          <div style="display:flex;flex-direction:column;gap:6px;">
            <?php foreach ($mRows as $mr): ?>
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:var(--radius-sm);padding:8px 12px;font-size:.78rem;">
              <strong style="color:var(--text);"><?= htmlspecialchars($mr['subject_name']) ?></strong>
              <?php if ($mr['lesson_title']): ?><span style="color:var(--muted);"> — <?= htmlspecialchars($mr['lesson_title']) ?></span><?php endif; ?>
              <?php if ($mr['topic_reached']): ?><div style="color:var(--muted);font-size:.72rem;margin-top:2px;">Topic reached: <?= htmlspecialchars($mr['topic_reached']) ?></div><?php endif; ?>
              <span class="badge <?= $mr['log_status']==='completed'?'badge-green':'badge-orange' ?>" style="margin-left:6px;"><?= $mr['log_status']==='completed'?'Fully Taught':'Partially Taught' ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($attRecent)): ?>
    <div class="card">
      <div class="card-head"><div class="card-head-left"><i class="bi bi-clock-history"></i> Recent 30 Days</div></div>
      <div class="card-body p0">
        <?php foreach ($attRecent as $ar): ?>
        <div class="att-list-row">
          <div class="att-date-block">
            <div class="att-day-num"><?= fmtDate($ar['date'],'d') ?></div>
            <div class="att-day-info"><?= fmtDate($ar['date'],'M') ?> · <?= fmtDate($ar['date'],'D') ?></div>
          </div>
          <div style="flex:1;font-size:.8rem;color:var(--muted);"><?= fmtDate($ar['date'],'Y') ?></div>
          <span class="att-sb <?= htmlspecialchars($ar['status']) ?>"><?= ucwords(str_replace('_',' ',$ar['status'])) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-card"><div class="empty-emoji">📅</div><div class="empty-title">No Attendance Records</div><div class="empty-sub">Records for <?= $acadYear ?>–<?= $acadYear+1 ?> will appear here</div></div>
    <?php endif; ?>
  </div><!-- /attendance -->

  <!-- ── FEES TAB ── -->
  <div id="panel_fees" class="panel">
    <?php if (!empty($feeRows)): ?>

    <div class="fee-sum-row">
      <div class="fee-sum"><div class="fv">₹<?= number_format($feeSummary['total'],0) ?></div><div class="fl">Total</div></div>
      <div class="fee-sum paid"><div class="fv">₹<?= number_format($feeSummary['paid'],0) ?></div><div class="fl">Paid</div></div>
      <div class="fee-sum <?= $feeSummary['due']>0?'due':'' ?>"><div class="fv">₹<?= number_format($feeSummary['due'],0) ?></div><div class="fl">Due</div></div>
    </div>

    <?php if ($feeSummary['due'] > 0): ?>
    <div class="alert danger" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.6rem;">
      <span><i class="bi bi-exclamation-circle-fill"></i> ₹<?= number_format($feeSummary['due'],0) ?> fee is due.</span>
      <?php if ($payEnabled): ?>
      <button onclick="startOnlinePayment()" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:.4rem 1rem;font-size:.8rem;font-weight:700;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:.35rem;"><i class="bi bi-credit-card-fill"></i> Pay Online</button>
      <?php else: ?>
      <span style="font-size:.75rem;">Pay at the school office.</span>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="alert success"><i class="bi bi-check-circle-fill"></i> All fees are paid. Thank you!</div>
    <?php endif; ?>

    <?php foreach ($feeRows as $fr):
      $paidPct = $fr['amount'] > 0 ? round($fr['paid_amount']/$fr['amount']*100) : 0;
      $remaining = round($fr['amount'] - $fr['paid_amount'], 2);
    ?>
    <div class="fee-row-card">
      <div class="fee-row-top">
        <div class="fee-row-name"><?= htmlspecialchars($fr['fee_type_name']) ?></div>
        <span class="fee-sb <?= htmlspecialchars($fr['status']) ?>"><?= ucfirst($fr['status']) ?></span>
      </div>
      <div class="fee-row-meta">
        <?php if ($fr['period_label']): ?><span><i class="bi bi-calendar3"></i><?= htmlspecialchars($fr['period_label']) ?></span><?php endif; ?>
        <span><i class="bi bi-currency-rupee"></i>₹<?= number_format($fr['amount'],0) ?> total</span>
        <span style="color:var(--green);"><i class="bi bi-check-circle"></i>₹<?= number_format($fr['paid_amount'],0) ?> paid</span>
        <?php if ($fr['due_date']): ?><span><i class="bi bi-calendar-x"></i>Due <?= fmtDate($fr['due_date']) ?></span><?php endif; ?>
      </div>
      <?php if ($remaining > 0): ?>
      <div class="fee-row-bar"><div class="fee-row-bar-fill" style="width:<?= $paidPct ?>%"></div></div>
      <div class="fee-row-bar-lbl"><?= $paidPct ?>% paid · ₹<?= number_format($remaining,0) ?> remaining</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php else: ?>
    <div class="empty-card"><div class="empty-emoji">💰</div><div class="empty-title">No Fee Records</div><div class="empty-sub">Your fee details will appear here</div></div>
    <?php endif; ?>
  </div><!-- /fees -->

  <!-- ── LIBRARY TAB ── -->
  <div id="panel_library" class="panel">
      <?php if (!empty($libIssues)): ?>
      <div style="display:flex;gap:10px;margin-bottom:14px;">
        <div style="flex:1;background:var(--blue-lt);border-radius:var(--radius-sm);padding:12px;text-align:center;">
          <div style="font-size:1.2rem;font-weight:800;color:var(--blue);"><?= $libStats['issued'] ?></div>
          <div style="font-size:.62rem;font-weight:700;color:var(--blue);text-transform:uppercase;margin-top:2px;">Issued</div>
        </div>
        <div style="flex:1;background:var(--green-lt);border-radius:var(--radius-sm);padding:12px;text-align:center;">
          <div style="font-size:1.2rem;font-weight:800;color:var(--green);"><?= $libStats['returned'] ?></div>
          <div style="font-size:.62rem;font-weight:700;color:var(--green);text-transform:uppercase;margin-top:2px;">Returned</div>
        </div>
        <div style="flex:1;background:var(--red-lt);border-radius:var(--radius-sm);padding:12px;text-align:center;">
          <div style="font-size:1.2rem;font-weight:800;color:var(--red);"><?= $libStats['overdue'] ?></div>
          <div style="font-size:.62rem;font-weight:700;color:var(--red);text-transform:uppercase;margin-top:2px;">Overdue</div>
        </div>
      </div>

      <?php if ($libStats['overdue'] > 0): ?>
      <div class="alert danger"><i class="bi bi-exclamation-triangle-fill"></i> You have <?= $libStats['overdue'] ?> overdue book(s). Please return them to the library immediately.</div>
      <?php endif; ?>

      <?php foreach ($libIssues as $bk):
        $isOverdue = ($bk['status'] !== 'returned' && $bk['days_overdue'] > 0);
        $bkCls  = $bk['status']==='returned' ? 'returned' : ($isOverdue ? 'overdue' : '');
        $bkIcon = $bk['status']==='returned' ? '📗' : ($isOverdue ? '⚠️' : '📘');
      ?>
      <div class="lib-card <?= $bkCls ?>">
        <div class="lib-icon"><?= $bkIcon ?></div>
        <div class="lib-binfo">
          <div class="lib-btitle"><?= htmlspecialchars($bk['title']) ?></div>
          <div class="lib-bauth">by <?= htmlspecialchars($bk['author']??'Unknown') ?><?= $bk['rack_no'] ? ' · Rack '.$bk['rack_no'] : '' ?></div>
          <div class="lib-bmeta">
            <span><i class="bi bi-calendar-plus"></i>Issued: <?= fmtDate($bk['issue_date']) ?></span>
            <?php if ($bk['return_date']): ?>
            <span><i class="bi bi-calendar-check"></i>Returned: <?= fmtDate($bk['return_date']) ?></span>
            <?php else: ?>
            <span style="<?= $isOverdue?'color:var(--red);font-weight:700;':'' ?>"><i class="bi bi-calendar-x"></i>Due: <?= fmtDate($bk['due_date']) ?><?= $isOverdue?' ('.$bk['days_overdue'].'d overdue)':'' ?></span>
            <?php endif; ?>
          </div>
          <span class="badge <?= $bk['status']==='returned'?'badge-green':($isOverdue?'badge-red':'badge-blue') ?>" style="margin-top:6px;">
            <?= $bk['status']==='returned'?'Returned':($isOverdue?'Overdue':'Issued') ?>
          </span>
        </div>
      </div>
      <?php endforeach; ?>

      <?php else: ?>
      <div class="empty-card"><div class="empty-emoji">📚</div><div class="empty-title">No Library Books</div><div class="empty-sub">No books currently issued</div></div>
      <?php endif; ?>
  </div><!-- /library -->

  <!-- ── STORE TAB — omitted entirely (no markup at all) when the school
       hasn't turned this on; the nav buttons above are gated the same way ── -->
  <?php if ($storeEnabled): ?>
  <div id="panel_store" class="panel">
    <?php if (!empty($storeProducts)):
      $storeShopUrl = BASE_URL . '/shop?school_id=' . $schoolId . '&school_name=' . urlencode($stu['school_name'] ?? '');
    ?>
    <div class="store-banner">
      <div class="store-banner-text">
        <div class="store-banner-title"><i class="bi bi-shop-window"></i> School Store</div>
        <div class="store-banner-sub"><?= count($storeProducts) ?> item<?= count($storeProducts) !== 1 ? 's' : '' ?> picked by your school</div>
      </div>
      <a href="<?= htmlspecialchars($storeShopUrl) ?>" class="store-shop-btn" target="_blank" rel="noopener">
        Shop Now <i class="bi bi-arrow-right"></i>
      </a>
    </div>
    <div class="store-grid">
      <?php foreach ($storeProducts as $p):
        $imgs  = json_decode($p['images'] ?? '[]', true);
        $img   = is_array($imgs) ? ($imgs[0] ?? '') : '';
        $price = (float)$p['price'];
        $mrp   = (float)$p['mrp'];
        $disc  = ($mrp > $price) ? (int)round(($mrp - $price) / $mrp * 100) : 0;
      ?>
      <a class="store-card" href="<?= htmlspecialchars($storeShopUrl) ?>" target="_blank" rel="noopener">
        <div class="store-card-img">
          <?php if ($img): ?>
          <img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy" onerror="this.parentElement.innerHTML='<i class=&quot;bi bi-image&quot;></i>'">
          <?php else: ?>
          <i class="bi bi-image"></i>
          <?php endif; ?>
        </div>
        <div class="store-card-body">
          <div class="store-card-name"><?= htmlspecialchars($p['name']) ?></div>
          <div class="store-card-price">
            ₹<?= number_format($price, 0) ?><?php if ($disc >= 5): ?><span class="store-card-mrp">₹<?= number_format($mrp, 0) ?></span><?php endif; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-card"><div class="empty-emoji">🛍️</div><div class="empty-title">Store Coming Soon</div><div class="empty-sub">Your school hasn't added any products yet</div></div>
    <?php endif; ?>
  </div><!-- /store -->
  <?php endif; ?>

  <!-- ── RESULTS TAB ── -->
  <div id="panel_results" class="panel">
    <?php if (!empty($examResults)): ?>
    <?php foreach ($examResults as $idx => $res):
      $pct      = $res['percent'];
      $bColor   = $pct >= 75 ? '#16a34a' : ($pct >= 50 ? '#d97706' : '#dc2626');
      $examData = $res['exam'];
    ?>
    <div class="result-card" id="rc<?= $idx ?>">
      <div class="result-card-head" onclick="this.parentElement.classList.toggle('open')">
        <div class="result-hl">
          <div class="result-icon"><i class="bi bi-clipboard2-data-fill"></i></div>
          <div>
            <div class="result-en"><?= htmlspecialchars($examData['exam_name']) ?></div>
            <div class="result-em"><?= htmlspecialchars($examData['session_label']??'') ?><?php if($examData['class_name']): ?> · <?= htmlspecialchars($examData['class_name']) ?><?php endif; ?><?php if($examData['start_date']): ?> · <?= fmtDate($examData['start_date'],'M Y') ?><?php endif; ?></div>
          </div>
        </div>
        <div class="result-hr">
          <div style="text-align:right;">
            <div class="result-pct" style="color:<?= $bColor ?>;"><?= $pct ?>%</div>
            <div style="font-size:.68rem;color:var(--muted);"><?= number_format($res['totalMarks'],1) ?>/<?= number_format($res['totalMax'],1) ?></div>
          </div>
          <span class="badge <?= $res['passed']?'badge-green':'badge-red' ?>"><?= $res['passed']?'PASS':'FAIL' ?></span>
          <i class="bi bi-chevron-down chevron"></i>
        </div>
      </div>
      <div class="result-body">
        <div style="padding:12px 16px 4px;display:flex;align-items:center;gap:8px;">
          <div style="flex:1;background:#f1f5f9;border-radius:100px;height:7px;overflow:hidden;">
            <div style="height:100%;border-radius:100px;width:<?= min(100,$pct) ?>%;background:<?= $bColor ?>;"></div>
          </div>
          <span style="font-size:.74rem;font-weight:700;color:<?= $bColor ?>;"><?= $pct ?>%</span>
        </div>
        <div style="overflow-x:auto;">
          <table class="res-table">
            <thead><tr><th>Subject</th><th style="text-align:right;">Max</th><th style="text-align:right;">Got</th><th style="text-align:right;">Pass</th><th>Bar</th><th>Result</th></tr></thead>
            <tbody>
            <?php foreach ($res['subjects'] as $sub):
              $ob = (float)($sub['marks_obtained']??0);
              $mx = (float)$sub['max_marks'];
              $ps = (float)$sub['pass_marks'];
              $sp = $mx>0 ? $ob/$mx*100 : 0;
              $ok = !$sub['is_absent'] && $ob>=$ps;
              $sc = $sub['is_absent']?'#94a3b8':($ok?'#16a34a':'#dc2626');
            ?>
            <tr>
              <td style="font-weight:600;"><?= htmlspecialchars($sub['subject_name']) ?></td>
              <td style="text-align:right;color:var(--muted);"><?= number_format($mx,1) ?></td>
              <td style="text-align:right;font-weight:700;color:<?= $sc ?>;"><?= $sub['is_absent']?'<span style="color:var(--subtle)">Ab.</span>':number_format($ob,1) ?></td>
              <td style="text-align:right;color:var(--muted);"><?= number_format($ps,1) ?></td>
              <td style="min-width:60px;"><?php if(!$sub['is_absent']&&$mx>0): ?><div style="display:flex;align-items:center;gap:5px;"><div class="res-bar"><div class="res-bar-f" style="width:<?= min(100,$sp) ?>%;background:<?= $sc ?>;"></div></div><span style="font-size:.65rem;color:<?= $sc ?>;font-weight:700;"><?= round($sp) ?>%</span></div><?php endif; ?></td>
              <td><span class="badge <?= $sub['is_absent']?'badge-gray':($ok?'badge-green':'badge-red') ?>"><?= $sub['is_absent']?'Absent':($ok?'Pass':'Fail') ?></span></td>
            </tr>
            <?php endforeach; ?>
            <tr class="res-total">
              <td><strong>Total</strong></td>
              <td style="text-align:right;"><strong><?= number_format($res['totalMax'],1) ?></strong></td>
              <td style="text-align:right;font-weight:700;color:<?= $bColor ?>;"><strong><?= number_format($res['totalMarks'],1) ?></strong></td>
              <td colspan="3"><span class="badge <?= $res['passed']?'badge-green':'badge-red' ?>"><?= $res['passed']?'PASS':'FAIL' ?></span> <strong style="color:<?= $bColor ?>;"><?= $pct ?>%</strong></td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="empty-card"><div class="empty-emoji">📊</div><div class="empty-title">No Results Yet</div><div class="empty-sub">Exam results will appear here after marks are entered</div></div>
    <?php endif; ?>
  </div><!-- /results -->

  <!-- ── SYLLABUS PROGRESS TAB ── -->
  <div id="panel_progress" class="panel">
    <?php if (empty($progressRows)): ?>
    <div class="empty-card"><div class="empty-emoji">📈</div><div class="empty-title">No Subjects Yet</div><div class="empty-sub">Syllabus progress will appear here once your school sets it up</div></div>
    <?php else: ?>
    <?php foreach ($progressRows as $pr):
      $sid        = (int)$pr['subject_id'];
      $hasLessons = (int)$pr['total_lessons'] > 0;
      $percent    = $hasLessons ? (float)$pr['percent_complete'] : null;
      $bColor     = $percent === null ? '#94a3b8' : ($percent >= 75 ? '#16a34a' : ($percent >= 40 ? '#d97706' : '#dc2626'));
      $dots       = $lessonsBySubject[$sid] ?? [];
      $teachers   = $teacherBySubject[$sid] ?? [];
      $curLesson  = $currentLessonBySubject[$sid] ?? null;
    ?>
    <div class="card">
      <div class="card-head">
        <div class="card-head-left"><i class="bi bi-journal-bookmark-fill"></i> <?= htmlspecialchars($pr['subject_name']) ?></div>
        <?php if ($hasLessons): ?><div style="font-weight:800;color:<?= $bColor ?>;"><?= $percent ?>%</div><?php endif; ?>
      </div>
      <div class="card-body">
        <?php if ($teachers): ?>
        <div style="font-size:.76rem;color:var(--muted);margin-bottom:10px;display:flex;align-items:center;gap:5px;">
          <i class="bi bi-person-fill" style="color:var(--teal);"></i> Taught by <strong style="color:var(--text);"><?= htmlspecialchars(implode(', ', $teachers)) ?></strong>
        </div>
        <?php endif; ?>
        <?php if (!$hasLessons): ?>
          <div style="font-size:.78rem;color:var(--muted);">Syllabus not set up for this subject yet.</div>
        <?php else: ?>
          <div style="background:#f1f5f9;border-radius:100px;height:8px;overflow:hidden;margin-bottom:10px;">
            <div style="height:100%;border-radius:100px;width:<?= min(100,$percent) ?>%;background:<?= $bColor ?>;"></div>
          </div>
          <div style="font-size:.76rem;color:var(--muted);margin-bottom:10px;">
            Lesson <?= (int)$pr['completed_lessons'] ?> of <?= (int)$pr['total_lessons'] ?> completed
            <?php if ((int)$pr['in_progress_lessons'] > 0): ?> · <?= (int)$pr['in_progress_lessons'] ?> in progress<?php endif; ?>
          </div>

          <?php if ($curLesson): ?>
          <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:var(--radius-sm);padding:11px 13px;margin-bottom:12px;">
            <div style="font-size:.65rem;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">
              <i class="bi bi-broadcast"></i> Currently Teaching
            </div>
            <div style="font-size:.86rem;font-weight:700;color:var(--text);margin-bottom:<?= $curLesson['topics'] ? '8px':'0' ?>;">
              <?= htmlspecialchars($curLesson['lesson']['title']) ?>
            </div>
            <?php if ($curLesson['topics']): ?>
            <div style="display:flex;flex-direction:column;gap:5px;">
              <?php foreach ($curLesson['topics'] as $tp): $tpc = (int)$tp['percent']; ?>
              <div>
                <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--muted);margin-bottom:2px;">
                  <span><?= htmlspecialchars($tp['title']) ?></span><span style="font-weight:700;color:<?= $tpc>=100?'#16a34a':'#4f46e5' ?>;"><?= $tpc ?>%</span>
                </div>
                <div style="background:#e0e7ff;border-radius:100px;height:5px;overflow:hidden;">
                  <div style="height:100%;border-radius:100px;width:<?= $tpc ?>%;background:<?= $tpc>=100?'#16a34a':'#4f46e5' ?>;"></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <?php if ($dots): ?>
          <div style="font-size:.65rem;font-weight:700;color:var(--subtle);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">All Lessons</div>
          <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <?php foreach ($dots as $ls):
              $dc = $ls['status']==='completed' ? '#16a34a' : ($ls['status']==='in_progress' ? '#d97706' : '#cbd5e1');
              $di = $ls['status']==='completed' ? '✓' : ($ls['status']==='in_progress' ? '···' : '');
            ?>
            <span title="<?= htmlspecialchars($ls['title']) ?>" style="display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 4px;border-radius:11px;background:<?= $dc ?>;color:#fff;font-size:.62rem;font-weight:800;"><?= $di ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div><!-- /progress -->

  <!-- ── HOMEWORK TAB ── -->
  <div id="panel_homework" class="panel">

    <div class="panel-hdr">
      <div class="panel-hdr-left">
        <div class="panel-hdr-title"><i class="bi bi-book-half" style="color:var(--teal);"></i> Homework</div>
        <div class="panel-hdr-meta" id="hwHdrMeta"><?= count($myHomework) ?> total<?= $pendingHwCount>0?' · <span style="color:var(--orange);font-weight:700;">'.$pendingHwCount.' pending</span>':' · All done!' ?></div>
      </div>
    </div>

    <div id="hwDynArea">
    <?php if (empty($myHomework)): ?>
    <div class="empty-card"><div class="empty-emoji">📚</div><div class="empty-title">No Homework Yet</div><div class="empty-sub">Your teacher hasn't assigned any homework yet</div></div>
    <?php else: ?>

    <div class="hw-filter">
      <button class="hw-filter-btn active" onclick="filterHw('all',this)">All (<?= count($myHomework) ?>)</button>
      <button class="hw-filter-btn" onclick="filterHw('pending',this)">Pending (<?= $pendingHwCount ?>)</button>
      <button class="hw-filter-btn" onclick="filterHw('submitted',this)">Submitted</button>
      <button class="hw-filter-btn" onclick="filterHw('checked',this)">Checked</button>
    </div>

    <?php foreach ($myHomework as $hw):
      $hwStatus  = $hw['sub_id'] ? ($hw['sub_status']??'submitted') : 'pending';
      $isOverdue = $hw['due_date'] && strtotime($hw['due_date']) < time() && !$hw['sub_id'];
      $hwIcons   = ['pending'=>'📝','submitted'=>'⏳','checked'=>'✅','returned'=>'📋'];
    ?>
    <div class="hw-card <?= $hwStatus ?>" data-status="<?= $hwStatus ?>">
      <div class="hw-card-top">
        <div class="hw-icon <?= $hwStatus ?>"><?= $hwIcons[$hwStatus]??'📝' ?></div>
        <div class="hw-info">
          <div class="hw-title"><?= htmlspecialchars($hw['title']) ?></div>
          <div class="hw-meta">
            <?php if($hw['subject_name']): ?><span><i class="bi bi-book-fill" style="color:var(--primary);"></i><?= htmlspecialchars($hw['subject_name']) ?></span><?php endif; ?>
            <?php if($hw['teacher_name']): ?><span><i class="bi bi-person-fill" style="color:var(--teal);"></i><?= htmlspecialchars($hw['teacher_name']) ?></span><?php endif; ?>
          </div>
        </div>
        <span class="badge <?= ['pending'=>'badge-orange','submitted'=>'badge-blue','checked'=>'badge-green','returned'=>'badge-purple'][$hwStatus]??'badge-gray' ?>"><?= ['pending'=>'Pending','submitted'=>'Submitted','checked'=>'Checked','returned'=>'Returned'][$hwStatus]??$hwStatus ?></span>
      </div>

      <?php if($hw['due_date']): ?>
      <div class="hw-due <?= $isOverdue?'overdue':'' ?>">
        <i class="bi bi-<?= $isOverdue?'exclamation-triangle-fill':'calendar-event' ?>"></i>
        Due: <?= fmtDate($hw['due_date']) ?><?= $isOverdue?' — OVERDUE!':'' ?>
      </div>
      <?php endif; ?>

      <?php if($hw['description']): ?>
      <div class="hw-desc" id="hwDesc<?= $hw['id'] ?>"><?= htmlspecialchars(mb_substr($hw['description'],0,180).(mb_strlen($hw['description'])>180?'…':'')) ?></div>
      <?php endif; ?>

      <?php if($hw['image']): ?>
      <div class="hw-img"><img src="<?= BASE_URL ?>/assets/uploads/hw_images/<?= htmlspecialchars($hw['image']) ?>" alt="Homework" onclick="openImageViewer(this.src)"></div>
      <?php endif; ?>

      <?php if($hwStatus === 'pending'): ?>
      <button class="hw-submit-btn" onclick="openSubmitHw(<?= $hw['id'] ?>, <?= htmlspecialchars(json_encode($hw['title'])) ?>)">
        <i class="bi bi-send-fill"></i> Submit Your Answer
      </button>
      <?php else: ?>
        <?php if($hw['text_answer']): ?>
        <div class="hw-your-ans"><div class="hw-your-ans-lbl">Your Answer:</div><?= htmlspecialchars(mb_substr($hw['text_answer'],0,150).(mb_strlen($hw['text_answer'])>150?'…':'')) ?></div>
        <?php endif; ?>
        <?php if($hw['sub_image']): ?>
        <div class="hw-sub-img"><img src="<?= BASE_URL ?>/assets/uploads/stu_homework/<?= htmlspecialchars($hw['sub_image']) ?>" alt="Your submission" onclick="openImageViewer(this.src)"></div>
        <?php endif; ?>
        <?php if(in_array($hwStatus,['checked','returned']) && ($hw['teacher_remarks']||$hw['marks_obtained']!==null)): ?>
        <div class="hw-feedback">
          <div class="hw-feedback-lbl"><i class="bi bi-stars"></i> Teacher Feedback</div>
          <?php if($hw['teacher_remarks']): ?><div class="hw-feedback-txt"><?= htmlspecialchars($hw['teacher_remarks']) ?></div><?php endif; ?>
          <?php if($hw['marks_obtained']!==null): ?><div class="hw-marks">Marks: <?= htmlspecialchars($hw['marks_obtained']) ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if($hwStatus === 'submitted'): ?>
        <div class="hw-awaiting"><i class="bi bi-clock-history"></i> Submitted <?= fmtDate($hw['submitted_at']) ?> · Awaiting teacher review</div>
        <?php endif; ?>
        <?php if($hwStatus === 'returned'): ?>
        <button class="hw-submit-btn" style="background:#7c3aed;" onclick="openSubmitHw(<?= $hw['id'] ?>, <?= htmlspecialchars(json_encode($hw['title'])) ?>)">
          <i class="bi bi-arrow-repeat"></i> Resubmit Your Answer
        </button>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    </div><!-- /hwDynArea -->

  </div><!-- /homework -->

  <!-- ── NALISH TAB ── -->
  <div id="panel_nalish" class="panel">

    <button class="fab-btn" onclick="openNewComplaint()">
      <i class="bi bi-plus-lg"></i> File a New Complaint
    </button>

    <div class="panel-hdr">
      <div class="panel-hdr-left">
        <div class="panel-hdr-title">My Complaints</div>
        <div class="panel-hdr-meta" id="cmpHdrMeta"><?= count($myComplaints) ?> total<?php if($openComplaints>0): ?> · <span style="color:var(--red);font-weight:700;"><?= $openComplaints ?> open</span><?php endif; ?></div>
      </div>
    </div>

    <div id="cmpListWrap">
    <?php if (empty($myComplaints)): ?>
    <div class="card">
      <div class="empty-state">
        <i class="bi bi-megaphone" style="font-size:2.5rem;color:var(--muted);"></i>
        <p>No complaints filed yet.<br><span style="font-size:.78rem;">Tap the button above to file a complaint.</span></p>
      </div>
    </div>
    <?php else: ?>
    <?php foreach ($myComplaints as $comp):
      $atts = json_decode($comp['attachments'] ?? '[]', true) ?: [];
    ?>
    <div class="cmp-card" onclick="openComplaintThread(<?= $comp['id'] ?>)">
      <div class="cmp-card-top">
        <div class="cmp-emoji">📢</div>
        <div class="cmp-info">
          <div class="cmp-subject"><?= htmlspecialchars($comp['subject']) ?></div>
          <div class="cmp-preview"><?= htmlspecialchars(mb_substr($comp['description'],0,100)) ?></div>
        </div>
        <i class="bi bi-chevron-right cmp-arrow"></i>
      </div>
      <div class="cmp-foot">
        <span class="cmp-s <?= htmlspecialchars($comp['status']) ?>"><?= ucwords(str_replace('_',' ',$comp['status'])) ?></span>
        <?php if (!empty($atts)): ?><span class="cmp-att"><i class="bi bi-paperclip"></i> <?= count($atts) ?></span><?php endif; ?>
        <span class="cmp-date"><?= fmtDate($comp['created_at']) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    </div><!-- /cmpListWrap -->

  </div><!-- /nalish -->

  <!-- ── BUS TAB ── -->
  <div id="panel_bus" class="panel">
    <?php
    $bd = $busData ?: [];
    $hasBusAssign  = !empty($bd['van_route_id']);
    $hasBus        = !empty($bd['bus_id']);
    $hasHome       = !empty($bd['has_home']) && (int)$bd['has_home'] > 0;
    $shiftCount    = max(1, (int)($bd['shift_count'] ?? 1));
    $shiftNo       = (int)($bd['shift_no'] ?? 1);
    if ($shiftNo < 1 || $shiftNo > $shiftCount) $shiftNo = 1;   // never show a shift the route doesn't have
    $shiftSfx      = $shiftNo === 1 ? '' : (string)$shiftNo;   // shift 1 => pickup_time, shift 2 => pickup_time2 ...
    $pickupTime    = $bd['pickup_time' . $shiftSfx] ?? null;
    $dropTime      = $bd['drop_time'   . $shiftSfx] ?? null;
    if (!function_exists('fmt12bus')) {
        function fmt12bus(?string $t): string {
            if (!$t) return '—';
            [$h,$m] = explode(':', $t);
            $hh = (int)$h;
            return ($hh===0?12:($hh>12?$hh-12:$hh)).':'.$m.($hh<12?'am':'pm');
        }
    }
    ?>

    <div class="panel-hdr">
      <div class="panel-hdr-left">
        <div class="panel-hdr-title"><i class="bi bi-bus-front-fill"></i> My Bus</div>
        <div class="panel-hdr-meta"><?= $hasBusAssign ? htmlspecialchars($bd['route_name']) : 'School Transport' ?></div>
      </div>
    </div>

    <?php if (!$hasBusAssign): ?>
    <!-- State 1: No bus route assigned at all -->
    <div class="card">
      <div class="empty-state" style="padding:48px 24px;">
        <i class="bi bi-bus-front" style="font-size:2.8rem;color:var(--muted);display:block;margin-bottom:12px;"></i>
        <p style="font-weight:600;color:var(--text);margin:0 0 6px;">No Bus Route Assigned</p>
        <p style="font-size:.8rem;color:var(--muted);margin:0;">You are not currently assigned to a school bus route.<br>Contact your school administrator for assistance.</p>
      </div>
    </div>

    <?php elseif (!$hasBus): ?>
    <!-- State 2: Route assigned but no bus yet -->
    <div class="card" style="margin-bottom:12px;">
      <div class="card-body" style="padding:14px 16px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div>
            <div style="font-size:.72rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Route</div>
            <div style="font-weight:700;color:var(--text);margin-top:2px;"><?= htmlspecialchars($bd['route_name']) ?></div>
            <div style="font-size:.78rem;color:var(--muted);"><?= htmlspecialchars($bd['from_location']??'') ?> → <?= htmlspecialchars($bd['to_location']??'') ?></div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;padding:10px;background:var(--orange-lt);border-radius:var(--radius-sm);">
            <i class="bi bi-clock-history" style="color:var(--orange);font-size:1.2rem;"></i>
            <div style="font-size:.78rem;color:var(--orange);font-weight:600;">Bus not assigned yet.<br><span style="font-weight:400;">Admin is setting it up.</span></div>
          </div>
        </div>
      </div>
    </div>

    <?php else: ?>
    <!-- State 3: Full assignment with bus and GPS tracking -->

    <!-- Bus Info Card -->
    <div class="card" style="margin-bottom:12px;">
      <div class="card-body" style="padding:14px 16px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <div style="font-size:.7rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Bus</div>
            <div style="font-weight:700;color:var(--text);margin-top:3px;font-size:.95rem;"><?= htmlspecialchars($bd['bus_name']) ?></div>
            <div style="font-size:.76rem;color:var(--muted);font-family:monospace;"><?= htmlspecialchars($bd['bus_number']) ?></div>
          </div>
          <div>
            <div style="font-size:.7rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Route</div>
            <div style="font-weight:600;color:var(--text);margin-top:3px;"><?= htmlspecialchars($bd['route_name']) ?></div>
            <div style="font-size:.76rem;color:var(--muted);"><?= htmlspecialchars($bd['from_location']??'') ?> → <?= htmlspecialchars($bd['to_location']??'') ?></div>
          </div>
          <?php if (!empty($bd['driver_name'])): ?>
          <div>
            <div style="font-size:.7rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Driver</div>
            <div style="font-weight:600;color:var(--text);margin-top:3px;"><?= htmlspecialchars($bd['driver_name']) ?></div>
            <?php if (!empty($bd['driver_phone'])): ?>
            <a href="tel:<?= htmlspecialchars($bd['driver_phone']) ?>" style="font-size:.76rem;color:var(--primary);display:inline-flex;align-items:center;gap:3px;margin-top:2px;">
              <i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($bd['driver_phone']) ?>
            </a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <div>
            <div style="font-size:.7rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Schedule<?= $shiftCount > 1 ? ' · Shift '.$shiftNo : '' ?></div>
            <div style="margin-top:6px;display:flex;gap:16px;">
              <div>
                <div style="font-size:.65rem;color:var(--green);font-weight:700;letter-spacing:.05em;">PICKUP</div>
                <div style="font-weight:800;color:var(--text);font-size:1rem;line-height:1.1;"><?= fmt12bus($pickupTime) ?></div>
              </div>
              <div>
                <div style="font-size:.65rem;color:var(--red);font-weight:700;letter-spacing:.05em;">DROP</div>
                <div style="font-weight:800;color:var(--text);font-size:1rem;line-height:1.1;"><?= fmt12bus($dropTime) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- GPS Status Bar -->
    <div class="card" id="gpsStatusBar" style="margin-bottom:12px;display:none;">
      <div class="card-body" style="padding:10px 16px;display:flex;align-items:center;gap:10px;">
        <span id="gpsDot" style="width:10px;height:10px;border-radius:50%;background:#d1d5db;flex-shrink:0;transition:background .3s;"></span>
        <div style="flex:1;">
          <span id="gpsStatusText" style="font-size:.82rem;font-weight:600;color:var(--text);">Checking GPS…</span>
          <span id="gpsAgeText" style="font-size:.76rem;color:var(--muted);margin-left:6px;"></span>
        </div>
        <div id="busSpeedBadge" style="display:none;background:#eff6ff;color:#2563eb;border-radius:20px;padding:3px 10px;font-size:.75rem;font-weight:600;"></div>
      </div>
    </div>

    <!-- Live Map -->
    <div class="card" style="margin-bottom:12px;padding:0;overflow:hidden;border-radius:var(--radius);">
      <div id="busMap" style="height:300px;"></div>
    </div>

    <!-- Proximity Alert & Home Location -->
    <div class="card" style="margin-bottom:12px;">
      <div class="card-head">
        <div class="card-head-left"><i class="bi bi-bell-fill"></i> Proximity Alert</div>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;user-select:none;">
          <input type="checkbox" id="alertToggle" onchange="toggleAlert(this.checked)"
                 style="width:16px;height:16px;accent-color:var(--primary);">
          <span style="font-size:.8rem;font-weight:600;">Enable</span>
        </label>
      </div>
      <div class="card-body">
        <p style="font-size:.82rem;color:var(--muted);margin:0 0 12px;">
          Alert will trigger when the bus is within <strong id="alertRadiusLabel"><?= (int)($bd['home_radius'] ?? 500) ?>m</strong> of home.
        </p>
        <?php
          $curR = (int)($bd['home_radius'] ?? 500) ?: 500;
          $radiusOpts = array_values(array_unique(array_merge([200, 300, 500, 800, 1000], [$curR])));
          sort($radiusOpts);
        ?>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0 0 12px;font-size:.8rem;">
          <label for="alertRadiusSel" style="font-weight:600;color:var(--text);">Alert distance</label>
          <select id="alertRadiusSel" onchange="changeAlertRadius(this.value)" style="padding:6px 8px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:.8rem;">
            <?php foreach ($radiusOpts as $rr): ?>
            <option value="<?= (int)$rr ?>"<?= $rr === $curR ? ' selected' : '' ?>><?= (int)$rr ?> m</option>
            <?php endforeach; ?>
          </select>
          <span style="color:var(--muted);">You are alerted when the bus comes this close to home.</span>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button onclick="setHomeLocation()" style="flex:1;min-width:160px;padding:11px;background:var(--primary);color:#fff;border:none;border-radius:var(--radius-sm);font-size:.84rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;" id="homeLocBtn">
            <i class="bi bi-geo-alt-fill"></i> <?= $hasHome ? 'Update Home Location' : 'Set Home Location' ?>
          </button>
          <?php if ($hasHome): ?>
          <button onclick="showHomeOnMap()" style="padding:11px 14px;background:var(--green-lt);color:var(--green);border:1.5px solid #bbf7d0;border-radius:var(--radius-sm);font-size:.83rem;font-weight:600;cursor:pointer;">
            <i class="bi bi-house-fill"></i>
          </button>
          <?php endif; ?>
        </div>
        <div id="homeLocStatus" style="margin-top:10px;font-size:.8rem;color:var(--green);display:none;align-items:center;gap:6px;">
          <i class="bi bi-check-circle-fill"></i> <span id="homeLocStatusText"></span>
        </div>
      </div>
    </div>

    <?php endif; // hasBus ?>
  </div><!-- /bus -->

  <!-- ── MESSAGES TAB ── -->
  <div id="panel_messages" class="panel">

    <!-- Header — the bottom nav is hidden while chatting (see showTab()), so
         this Back button is the way out, exactly like a real chat app. -->
    <div class="stu-thread-hdr">
      <button class="stu-thread-back" onclick="showTab('home')"><i class="bi bi-arrow-left"></i></button>
      <div class="stu-thread-hdr-av"><i class="bi bi-building"></i></div>
      <div style="flex:1;">
        <div class="stu-thread-hdr-name">School Administration</div>
        <div class="stu-thread-hdr-sub">Your school conversation</div>
      </div>
    </div>

    <!-- Thread (scrollable) -->
    <div class="stu-thread-wrap" id="stuMsgThread">
      <div style="text-align:center;padding:30px;color:#8696a0;font-size:.83rem;">Loading…</div>
    </div>

    <!-- Image attach preview bar -->
    <div class="stu-img-prev-bar" id="stuCmpImgPreview">
      <img id="stuCmpImgThumb" src="" alt="">
      <span id="stuCmpImgName" style="flex:1;font-size:.78rem;color:#54656f;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
      <button onclick="clearStuCmpImg()" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:1.3rem;line-height:1;padding:0 4px;">×</button>
    </div>

    <!-- Compose bar (WhatsApp style) -->
    <div class="stu-compose-wrap">
      <button class="stu-attach-btn" onclick="document.getElementById('stuCmpImg').click()" title="Attach image"><i class="bi bi-image"></i></button>
      <textarea class="stu-compose-input" id="stuCmpBody" placeholder="Message to school…" rows="1"
        oninput="autoGrowStu(this)"
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();submitStuMsg();}"></textarea>
      <button class="stu-send-btn" id="stuCmpSendBtn" onclick="submitStuMsg()"><i class="bi bi-send-fill"></i></button>
      <input type="file" id="stuCmpImg" accept="image/*" style="display:none">
    </div>

  </div><!-- /messages -->

  <!-- Image viewer for messages -->
  <div id="stuMsgImgLb" onclick="this.style.display='none'" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center;cursor:zoom-out;">
    <img id="stuMsgImgLbSrc" src="" style="max-width:92vw;max-height:90vh;border-radius:10px;" alt="">
  </div>

</div><!-- /main-content -->

<!-- ── IMAGE VIEWER ── -->
<div id="imgViewer" onclick="closeImageViewer()">
  <button class="iv-close" onclick="event.stopPropagation();closeImageViewer()">&times;</button>
  <img id="imgViewerImg" src="" alt="">
</div>

<!-- ── EDIT PROFILE MODAL ── -->
<div class="modal-overlay" id="editProfileModal">
  <div class="modal-box wide" onclick="event.stopPropagation()">
    <div class="modal-head">
      <div class="modal-head-left">
        <div class="modal-title"><i class="bi bi-pencil-fill"></i> Edit Profile</div>
      </div>
      <button class="modal-close" onclick="closeEditProfile()">&times;</button>
    </div>
    <div class="modal-body">
      <div id="epMsg" class="modal-msg"></div>

      <!-- Photo section -->
      <div style="margin-bottom:18px;padding:14px;background:#f8fafc;border-radius:var(--radius);border:1px solid var(--border);">
        <div style="display:flex;align-items:center;gap:16px;">
          <div id="epPhotoPreview" style="width:64px;height:64px;border-radius:50%;background:var(--primary-lt);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;color:var(--primary);overflow:hidden;border:3px solid var(--border);flex-shrink:0;">
            <?php echo $photoUrl ? '<img src="'.htmlspecialchars($photoUrl).'" style="width:100%;height:100%;object-fit:cover;">' : '<span>'.htmlspecialchars($initial).'</span>'; ?>
          </div>
          <div>
            <div style="font-size:.78rem;font-weight:600;color:var(--text);margin-bottom:6px;">Profile Photo</div>
            <label style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:var(--primary-lt);border-radius:var(--radius-sm);font-size:.78rem;font-weight:600;color:var(--primary);cursor:pointer;">
              <i class="bi bi-camera-fill"></i> Choose Photo
              <input type="file" id="epPhoto" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;" onchange="initCropper()">
            </label>
            <div id="epCropDone" style="display:none;margin-top:6px;font-size:.72rem;color:#16a34a;font-weight:600;">
              <i class="bi bi-check-circle-fill"></i> Photo cropped &amp; ready
            </div>
            <div style="font-size:.67rem;color:var(--muted);margin-top:4px;">JPG · PNG · WEBP</div>
          </div>
        </div>
        <div id="epCropperArea" class="crop-area">
          <div style="font-size:.75rem;font-weight:600;color:var(--text);margin-bottom:8px;">
            <i class="bi bi-crop" style="color:var(--primary);"></i> Drag to reposition · Resize corners to adjust
          </div>
          <div class="crop-canvas"><img id="epCropperImg" style="display:block;max-width:100%;"></div>
          <div class="crop-tools">
            <button type="button" onclick="rotateCrop(-90)" style="padding:6px 10px;background:#f1f5f9;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.8rem;cursor:pointer;"><i class="bi bi-arrow-counterclockwise"></i></button>
            <button type="button" onclick="rotateCrop(90)"  style="padding:6px 10px;background:#f1f5f9;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.8rem;cursor:pointer;"><i class="bi bi-arrow-clockwise"></i></button>
            <div style="flex:1;"></div>
            <button type="button" onclick="cancelCrop()" style="padding:7px 14px;background:#fff;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.8rem;font-weight:600;cursor:pointer;color:var(--muted);">Cancel</button>
            <button type="button" onclick="confirmCrop()" style="padding:7px 18px;background:var(--primary);border:none;color:#fff;border-radius:var(--radius-sm);font-size:.82rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
              <i class="bi bi-scissors"></i> Crop &amp; Use
            </button>
          </div>
        </div>
      </div>

      <?php if (count($visibleEditableFields) <= 1): ?>
      <div style="padding:14px;background:var(--orange-lt);border-radius:var(--radius-sm);font-size:.82rem;color:#92400e;margin-bottom:14px;">
        <i class="bi bi-info-circle-fill"></i> The school has not configured any editable fields. Only the photo can be changed.
      </div>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <?php foreach ($visibleEditableFields as $fKey => $fMeta):
          $fVal   = $stu[$fKey] ?? '';
          $fValH  = htmlspecialchars($fVal);
          $fLabel = htmlspecialchars($fMeta['label']);
          $fType  = $fMeta['type'];
          $isWide = ($fType === 'textarea');
          $inputType = match($fType) { 'tel' => 'tel', 'email' => 'email', default => 'text' };
        ?>
        <div class="fld-group"<?= $isWide ? ' style="grid-column:span 2;"' : '' ?>>
          <label class="fld-label"><?= $fLabel ?></label>
          <?php if ($fType === 'textarea'): ?>
            <textarea class="fld-input" id="ep_<?= $fKey ?>" rows="2"><?= $fValH ?></textarea>
          <?php elseif ($fType === 'gender'): ?>
            <select class="fld-input" id="ep_<?= $fKey ?>">
              <option value="">-- Select --</option>
              <option value="male"   <?= $fVal === 'male'   ? 'selected' : '' ?>>Male</option>
              <option value="female" <?= $fVal === 'female' ? 'selected' : '' ?>>Female</option>
              <option value="other"  <?= $fVal === 'other'  ? 'selected' : '' ?>>Other</option>
            </select>
          <?php else: ?>
            <input class="fld-input" id="ep_<?= $fKey ?>" type="<?= $inputType ?>" value="<?= $fValH ?>">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div style="margin-top:14px;padding:10px 14px;background:var(--orange-lt);border-radius:var(--radius-sm);font-size:.78rem;color:#92400e;">
        <i class="bi bi-info-circle-fill"></i> Changes will be sent for school admin approval. Current profile stays unchanged until approved.
      </div>
    </div>
    <div class="modal-foot">
      <button onclick="closeEditProfile()" style="padding:9px 18px;border:1.5px solid var(--border);border-radius:var(--radius-sm);background:#fff;color:var(--text);font-size:.84rem;font-weight:600;cursor:pointer;">Cancel</button>
      <button onclick="submitProfileRequest()" id="epSubmitBtn" style="padding:9px 20px;background:var(--primary);border:none;color:#fff;border-radius:var(--radius-sm);font-size:.84rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-send-fill"></i> Submit for Approval
      </button>
    </div>
  </div>
</div>

<!-- ── NEW COMPLAINT MODAL ── -->
<div class="modal-overlay" id="newComplaintModal">
  <div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-head red">
      <div class="modal-head-left">
        <div class="modal-title"><i class="bi bi-megaphone-fill"></i> File a Complaint</div>
      </div>
      <button class="modal-close" onclick="closeNewComplaint()">&times;</button>
    </div>
    <div class="modal-body">
      <div id="cmpMsg" class="modal-msg"></div>
      <div class="fld-group">
        <label class="fld-label">Subject *</label>
        <input class="fld-input" id="cmpSubject" placeholder="Brief title of your complaint">
      </div>
      <div class="fld-group">
        <label class="fld-label">Description *</label>
        <textarea class="fld-input" id="cmpDesc" rows="4" placeholder="Describe your complaint in detail…"></textarea>
      </div>
      <div class="fld-group">
        <label class="fld-label">Attachments (images or video, up to 5 files)</label>
        <label style="display:flex;flex-direction:column;align-items:center;gap:6px;padding:18px;background:#fafbff;border:2px dashed var(--border);border-radius:var(--radius);cursor:pointer;">
          <i class="bi bi-paperclip" style="font-size:1.4rem;color:#818cf8;"></i>
          <span style="font-size:.8rem;font-weight:600;color:var(--primary);">Browse Files</span>
          <span style="font-size:.7rem;color:var(--muted);">Images + MP4/MOV videos</span>
          <input type="file" id="cmpFiles" multiple accept="image/*,video/mp4,video/webm,video/quicktime,video/3gpp" style="display:none;" onchange="updateCmpFiles()">
        </label>
        <div id="cmpFileList" style="margin-top:8px;font-size:.75rem;color:var(--muted);"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button onclick="closeNewComplaint()" style="padding:9px 18px;border:1.5px solid var(--border);border-radius:var(--radius-sm);background:#fff;color:var(--text);font-size:.84rem;font-weight:600;cursor:pointer;">Cancel</button>
      <button onclick="submitComplaint()" id="cmpSubmitBtn" style="padding:9px 20px;background:var(--red);border:none;color:#fff;border-radius:var(--radius-sm);font-size:.84rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-send-fill"></i> Submit Complaint
      </button>
    </div>
  </div>
</div>

<!-- ── COMPLAINT THREAD MODAL ── -->
<div class="modal-overlay" id="threadModal">
  <div class="modal-box" style="display:flex;flex-direction:column;" onclick="event.stopPropagation()">
    <div class="modal-head red">
      <div class="modal-head-left">
        <div class="modal-title" id="threadModalTitle">Complaint</div>
        <div class="modal-subtitle" id="threadModalStatus"></div>
      </div>
      <button class="modal-close" onclick="closeThreadModal()">&times;</button>
    </div>
    <div class="thread-msgs modal-body p0" id="threadMessages"></div>
    <div class="thread-input-row" id="threadReplySection">
      <textarea id="threadReply" class="fld-input" rows="2" placeholder="Write your reply…" style="margin-bottom:8px;"></textarea>
      <div class="thread-att-row">
        <label style="display:inline-flex;align-items:center;gap:5px;font-size:.75rem;color:var(--muted);cursor:pointer;padding:6px 12px;background:#f1f5f9;border-radius:var(--radius-sm);border:1px solid var(--border);">
          <i class="bi bi-paperclip"></i>
          <input type="file" id="threadFile" accept="image/*,video/mp4,video/webm" style="display:none;" onchange="document.getElementById('threadFileName').textContent=this.files[0]?.name||''">
        </label>
        <span id="threadFileName" style="font-size:.72rem;color:var(--muted);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></span>
        <button onclick="sendThreadReply()" style="padding:7px 16px;background:var(--red);border:none;color:#fff;border-radius:var(--radius-sm);font-size:.8rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;white-space:nowrap;">
          <i class="bi bi-send-fill"></i> Reply
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── SUBMIT HOMEWORK MODAL ── -->
<div class="modal-overlay" id="submitHwModal">
  <div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-head teal">
      <div class="modal-head-left">
        <div class="modal-title" id="hwModalTitle"><i class="bi bi-send-fill"></i> Submit Homework</div>
      </div>
      <button class="modal-close" onclick="closeSubmitHw()">&times;</button>
    </div>
    <div class="modal-body">
      <div id="hwMsg" class="modal-msg"></div>
      <input type="hidden" id="hwSubmitId">
      <div class="fld-group">
        <label class="fld-label">Written Answer (optional if uploading photo)</label>
        <textarea class="fld-input" id="hwTextAnswer" rows="4" placeholder="Type your answer here…"></textarea>
      </div>
      <div class="fld-group">
        <label class="fld-label">Photo of Copy / Written Work (optional)</label>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <label style="flex:1;min-width:130px;display:flex;flex-direction:column;align-items:center;gap:6px;padding:18px 10px;background:#fafbff;border:2px dashed var(--border);border-radius:var(--radius);cursor:pointer;">
            <i class="bi bi-image" style="font-size:1.4rem;color:var(--teal);"></i>
            <span style="font-size:.8rem;font-weight:600;color:#0f766e;">Choose Photo</span>
            <span style="font-size:.7rem;color:var(--muted);">JPG, PNG, WEBP</span>
            <input type="file" id="hwImage" accept="image/*" style="display:none;" onchange="previewHwSub()">
          </label>
          <div id="hwSubBtnTakePhoto" style="flex:1;min-width:130px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:18px 10px;background:#fafbff;border:2px dashed var(--border);border-radius:var(--radius);cursor:pointer;">
            <i class="bi bi-camera-fill" style="font-size:1.4rem;color:var(--teal);"></i>
            <span style="font-size:.8rem;font-weight:600;color:#0f766e;">Take Photo</span>
            <span style="font-size:.7rem;color:var(--muted);">Use camera</span>
          </div>
        </div>
        <div id="hwImagePreview" style="margin-top:10px;display:none;">
          <img id="hwImagePreviewImg" style="max-width:100%;max-height:200px;border-radius:var(--radius-sm);border:1px solid var(--border);" alt="">
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button onclick="closeSubmitHw()" style="padding:9px 18px;border:1.5px solid var(--border);border-radius:var(--radius-sm);background:#fff;color:var(--text);font-size:.84rem;font-weight:600;cursor:pointer;">Cancel</button>
      <button onclick="submitHwAnswer()" id="hwSubmitBtn" style="padding:9px 20px;background:#0f766e;border:none;color:#fff;border-radius:var(--radius-sm);font-size:.84rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-send-fill"></i> Submit Homework
      </button>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════
     LIVE CAMERA CAPTURE + CROP OVERLAY (homework submission photo)
     Outside all modals — avoids overflow:hidden clipping
════════════════════════════════════ -->
<div id="hwSubCameraOverlay" style="display:none;position:fixed;inset:0;z-index:2300;background:rgba(0,0,0,0.88);align-items:center;justify-content:center;padding:16px;">
  <div style="background:#fff;border-radius:14px;padding:20px;width:100%;max-width:560px;max-height:92vh;display:flex;flex-direction:column;gap:12px;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <strong style="font-size:1rem;">Take Homework Photo</strong>
      <button type="button" id="hwSubBtnCameraCancel" style="border:none;background:#f3f4f6;border-radius:8px;padding:6px 12px;cursor:pointer;font-size:.85rem;">✕ Cancel</button>
    </div>
    <div style="flex:1;min-height:0;overflow:hidden;border-radius:8px;background:#111;display:flex;align-items:center;justify-content:center;position:relative;">
      <video id="hwSubCameraVideo" autoplay playsinline muted style="display:block;max-width:100%;max-height:60vh;"></video>
      <div id="hwSubCameraErrorMsg" style="display:none;color:#fca5a5;font-size:.85rem;text-align:center;padding:24px;"></div>
      <button type="button" id="hwSubBtnCameraSwitch" title="Switch camera" style="display:none;position:absolute;top:10px;right:10px;width:38px;height:38px;border-radius:50%;border:none;background:rgba(0,0,0,.55);color:#fff;font-size:1.05rem;cursor:pointer;align-items:center;justify-content:center;">
        <i class="bi bi-arrow-repeat"></i>
      </button>
    </div>
    <div style="display:flex;justify-content:center;gap:8px;">
      <button type="button" id="hwSubBtnCameraCapture" style="padding:9px 22px;background:#0f766e;border:none;color:#fff;border-radius:9px;font-size:.84rem;font-weight:700;cursor:pointer;">
        <i class="bi bi-camera-fill"></i> Capture Photo
      </button>
    </div>
  </div>
</div>

<div id="hwSubCropperOverlay" style="display:none;position:fixed;inset:0;z-index:2300;background:rgba(0,0,0,0.88);align-items:center;justify-content:center;padding:16px;">
  <div style="background:#fff;border-radius:14px;padding:20px;width:100%;max-width:560px;max-height:92vh;display:flex;flex-direction:column;gap:12px;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <strong style="font-size:1rem;">Crop Photo</strong>
      <button type="button" id="hwSubBtnCropCancel" style="border:none;background:#f3f4f6;border-radius:8px;padding:6px 12px;cursor:pointer;font-size:.85rem;">✕ Cancel</button>
    </div>
    <div style="flex:1;min-height:0;overflow:hidden;border-radius:8px;background:#111;">
      <img id="hwSubCropperImg" alt="" style="display:block;max-width:100%;">
    </div>
    <div style="display:flex;justify-content:center;gap:8px;">
      <button type="button" id="hwSubBtnCropUse" style="padding:9px 22px;background:#0f766e;border:none;color:#fff;border-radius:9px;font-size:.84rem;font-weight:700;cursor:pointer;">
        <i class="bi bi-check-circle"></i> Crop &amp; Use Photo
      </button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<?php if ($hasBus): ?>
<script>
// ── Bus Live Tracking ─────────────────────────────────────────────────────────
const BUS_ID     = <?= (int)$busData['bus_id'] ?>;
const BUS_SCHOOL = <?= $schoolId ?>;
const BUS_NAME   = <?= json_encode($busData['bus_name']) ?>;
let   ALERT_R    = <?= (int)($busData['home_radius'] ?? 500) ?: 500 ?>;
const BUS_LOC_URL = <?= json_encode(BASE_URL . '/api/bus_location.php') ?>;
const STU_BUS_URL = <?= json_encode(BASE_URL . '/api/student_bus.php') ?>;
const VAPID_PUBLIC_KEY = <?= json_encode(VAPID_PUBLIC_KEY) ?>;
const STU_SW_URL  = <?= json_encode(BASE_URL . '/student/sw.js') ?>;

let _busMap = null, _busMapInit = false;
let _busMarker = null, _homeMarker = null;
let _busPoller = null;
let _alertOn   = false;
let _homeLatLng = null;
let _lastBusLatLng = null;
let _alarmPlaying  = false;
let _inZone = false, _homeCircle = null, _homeWatch = null;

function initBusTab() {
  if (_busMapInit) return;
  _busMapInit = true;
  _busMap = L.map('busMap').setView([20.5937, 78.9629], 5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19,
  }).addTo(_busMap);
  // Fix: Leaflet needs invalidateSize after being shown in a previously hidden container
  setTimeout(() => _busMap.invalidateSize(), 150);
  document.getElementById('gpsStatusBar').style.display = 'block';
  pollBusLocation();
  if (_busPoller) clearInterval(_busPoller);
  _busPoller = setInterval(pollBusLocation, 10000);
  loadHomeLocation();
}

async function pollBusLocation() {
  // Skip update if user navigated away from the bus panel
  if (!document.getElementById('panel_bus')?.classList.contains('active')) return;
  try {
    const r = await fetch(BUS_LOC_URL + '?bus_id=' + BUS_ID + '&school_id=' + BUS_SCHOOL + '&_=' + Date.now());
    const d = await r.json();
    if (!d.ok) { updateGpsStatus('offline', null, null, null); return; }

    const status = d.age < 120 ? 'live' : d.age < 300 ? 'recent' : 'offline';
    updateGpsStatus(status, d.age, d.lat, d.speed);

    const latlng = [parseFloat(d.lat), parseFloat(d.lng)];
    _lastBusLatLng = latlng;

    const busIcon = L.divIcon({
      className:'',
      html:`<div style="background:${status==='live'?'#22c55e':status==='recent'?'#f59e0b':'#94a3b8'};width:38px;height:38px;border-radius:50% 50% 50% 0;display:flex;align-items:center;justify-content:center;font-size:1.2rem;box-shadow:0 2px 8px rgba(0,0,0,.3);transform:rotate(-45deg)"><span style="transform:rotate(45deg)">🚌</span></div>`,
      iconSize:[38,38], iconAnchor:[19,38], popupAnchor:[0,-38],
    });

    if (_busMarker) {
      _busMarker.setLatLng(latlng).setIcon(busIcon);
    } else {
      _busMarker = L.marker(latlng, {icon:busIcon})
        .bindPopup(`<strong>${BUS_NAME}</strong><br>Speed: ${Math.round(d.speed||0)} km/h`)
        .addTo(_busMap);
      _busMap.setView(latlng, 14);
    }
    _busMarker.getPopup().setContent(`<strong>${BUS_NAME}</strong><br>Speed: ${Math.round(d.speed||0)} km/h`);

    // In-page alarm: once per approach, and only while the GPS fix is fresh (never from a stale position).
    if (_alertOn && _homeLatLng && status !== 'offline') {
      const dist = haversineM(_homeLatLng[0], _homeLatLng[1], latlng[0], latlng[1]);
      if (dist <= ALERT_R && !_inZone) { _inZone = true; triggerAlarm(dist); }
      else if (dist > ALERT_R * 1.25) { _inZone = false; stopAlarm(); }   // re-arm only after it has clearly left
    }
  } catch(e) { updateGpsStatus('offline', null, null, null); }
}

function updateGpsStatus(status, age, lat, speed) {
  const colors = {live:'#22c55e', recent:'#f59e0b', offline:'#94a3b8'};
  const labels = {live:'GPS Live', recent:'GPS Recent', offline:'GPS Offline'};
  document.getElementById('gpsDot').style.background = colors[status] || '#94a3b8';
  document.getElementById('gpsStatusText').textContent = labels[status] || 'No GPS';
  if (age != null) {
    const ag = age < 60 ? age + 's ago' : Math.round(age/60) + 'm ago';
    document.getElementById('gpsAgeText').textContent = '· Updated ' + ag;
  } else {
    document.getElementById('gpsAgeText').textContent = '';
  }
  const sb = document.getElementById('busSpeedBadge');
  if (speed != null && speed > 0) {
    sb.textContent = Math.round(speed) + ' km/h';
    sb.style.display = 'block';
  } else {
    sb.style.display = 'none';
  }
}

// ── Home Location ─────────────────────────────────────────────────────────────
async function loadHomeLocation() {
  try {
    const r = await fetch(STU_BUS_URL + '?action=get_home&_=' + Date.now());
    const d = await r.json();
    if (d.success) {
      _homeLatLng = [parseFloat(d.lat), parseFloat(d.lng)];
      if (parseInt(d.radius) > 0) ALERT_R = parseInt(d.radius);
      placeHomeMarker(_homeLatLng[0], _homeLatLng[1]);
      document.getElementById('alertRadiusLabel').textContent = ALERT_R + 'm';
      const sel = document.getElementById('alertRadiusSel');
      if (sel) {
        if (![...sel.options].some(o => parseInt(o.value) === ALERT_R)) sel.add(new Option(ALERT_R + ' m', ALERT_R));
        sel.value = String(ALERT_R);
      }
      // Restore the switch: the in-page alarm must also work after a page reload (it used to stay off)
      _alertOn = !!d.push_enabled;
      document.getElementById('alertToggle').checked = _alertOn;
      // Quietly re-register this device: heals a subscription the push service expired or rotated
      if (_alertOn && 'Notification' in window && Notification.permission === 'granted') subscribePush();
    }
  } catch(e) {}
}

// Why background alerts can't be enabled here, in plain words
function pushFailReason() {
  const ua = navigator.userAgent || '';
  const ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const standalone = window.navigator.standalone === true || (window.matchMedia && matchMedia('(display-mode: standalone)').matches);
  if (!window.isSecureContext) return 'Background alerts need a secure (https) connection.';
  if (ios && !standalone) return 'On iPhone/iPad, first add this site to your Home Screen (Share > Add to Home Screen) and open it from there to get background alerts.';
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return 'This browser does not support background alerts.';
  if ('Notification' in window && Notification.permission === 'denied') return 'Notifications are blocked for this site. Allow them in your browser settings, then switch the alert on again.';
  return 'Background alerts could not be enabled on this device/browser.';
}

// ── Push helpers ─────────────────────────────────────────────────────────────
function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const arr = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
  return arr;
}

function placeHomeMarker(lat, lng) {
  if (!_busMap) return;
  const homeIcon = L.divIcon({
    className:'',
    html:'<div style="background:#2563eb;color:#fff;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:1rem;box-shadow:0 2px 6px rgba(0,0,0,.3);">\u{1F3E0}</div>',
    iconSize:[32,32], iconAnchor:[16,16],
  });
  if (_homeMarker) {
    _homeMarker.setLatLng([lat, lng]);
  } else {
    _homeMarker = L.marker([lat, lng], {icon: homeIcon, draggable: true})
      .bindPopup('Your home. Drag this pin to the exact spot to fine-tune; it saves automatically.').addTo(_busMap);
    _homeMarker.on('dragend', async () => {
      const p = _homeMarker.getLatLng();
      const ok = await saveHomeLocation(p.lat, p.lng, 'Home pin moved and saved!');
      if (!ok && _homeLatLng) _homeMarker.setLatLng(_homeLatLng);
    });
  }
  // Show the alert zone on the map
  if (_homeCircle) _homeCircle.setLatLng([lat, lng]).setRadius(ALERT_R);
  else _homeCircle = L.circle([lat, lng], {radius: ALERT_R, color:'#2563eb', weight:1.5, fillColor:'#2563eb', fillOpacity:.07, interactive:false}).addTo(_busMap);
}

function showHomeOnMap() {
  if (!_homeLatLng || !_busMap) return;
  _busMap.setView(_homeLatLng, 15);
  _homeMarker && _homeMarker.openPopup();
}

function showHomeStatus(text) {
  const st = document.getElementById('homeLocStatus');
  if (!st) return;
  document.getElementById('homeLocStatusText').textContent = text;
  st.style.display = 'flex';
  setTimeout(() => { st.style.display = 'none'; }, 5000);
}

async function saveHomeLocation(lat, lng, okMsg) {
  try {
    const fd = new FormData();
    fd.append('action', 'set_home');
    fd.append('lat', lat);
    fd.append('lng', lng);
    fd.append('radius', ALERT_R);
    const d = await (await fetch(STU_BUS_URL, {method:'POST', body:fd})).json();
    if (d.success) {
      _homeLatLng = [lat, lng];
      _inZone = false;
      if (_busMapInit) placeHomeMarker(lat, lng);
      showHomeStatus(okMsg || 'Home location saved!');
      return true;
    }
    stuAlert(d.message || 'Location could not be saved.');
  } catch(e) {
    stuAlert('An error occurred while saving location.');
  }
  return false;
}

async function changeAlertRadius(v) {
  ALERT_R = parseInt(v) || 500;
  document.getElementById('alertRadiusLabel').textContent = ALERT_R + 'm';
  _inZone = false;
  if (_homeLatLng) await saveHomeLocation(_homeLatLng[0], _homeLatLng[1], 'Alert distance set to ' + ALERT_R + ' m');
}

// Set home from the phone's GPS. Waits up to 20 s for a GOOD fix (instead of saving the first rough one).
function setHomeLocation() {
  if (!navigator.geolocation) { stuAlert('Geolocation is not supported by your browser.'); return; }
  const btn = document.getElementById('homeLocBtn');
  const reset = () => {
    if (btn) { btn.innerHTML = '<i class="bi bi-geo-alt-fill"></i> ' + (_homeLatLng ? 'Update' : 'Set') + ' Home Location'; btn.disabled = false; }
  };
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Finding accurate location\u2026'; }

  let best = null, done = false, timer = null;
  const stopWatch = () => {
    if (_homeWatch !== null) { navigator.geolocation.clearWatch(_homeWatch); _homeWatch = null; }
    clearTimeout(timer);
  };
  const finish = async () => {
    if (done) return;
    done = true; stopWatch();
    if (!best) { stuAlert('Could not get your location. Turn on GPS / Location and try again.'); reset(); return; }
    if (best.acc > 150) {
      reset();
      if (_busMap) {
        _busMap.setView([best.lat, best.lng], 15);
        _busMap.once('click', e => saveHomeLocation(e.latlng.lat, e.latlng.lng, 'Home saved at the spot you tapped.'));
      }
      stuAlert('Your device found only a rough location (about \u00b1' + Math.round(best.acc) + ' m). For a correct alert, go outside or near a window with GPS on and try again, or tap your exact home on the map.');
      return;
    }
    const ok = await saveHomeLocation(best.lat, best.lng, 'Home saved (accuracy \u00b1' + Math.round(best.acc) + ' m). Drag the pin to fine-tune.');
    if (ok && _busMap) _busMap.setView([best.lat, best.lng], 17);
    reset();
  };

  timer = setTimeout(finish, 20000);
  _homeWatch = navigator.geolocation.watchPosition(pos => {
    const a = pos.coords.accuracy || 9999;
    if (!best || a < best.acc) best = {lat: pos.coords.latitude, lng: pos.coords.longitude, acc: a};
    if (btn) btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Accuracy \u00b1' + Math.round(best.acc) + ' m\u2026';
    if (best.acc <= 20) finish();
  }, err => {
    if (err.code === 1) {
      done = true; stopWatch();
      stuAlert('Location permission was denied. Allow Location for this site and try again.');
      reset();
    }
  }, {enableHighAccuracy: true, maximumAge: 0, timeout: 20000});
}

// ── Proximity Alert ───────────────────────────────────────────────────────────
let _audioCtx = null;

async function toggleAlert(on) {
  const box = document.getElementById('alertToggle');
  if (on && !_homeLatLng) {
    box.checked = false;
    stuAlert('Please set your home location first.');
    return;
  }
  if (on) {
    // Foreground alarm (works while this tab is open) — unrelated to push, always armed.
    _alertOn = true;
    if (typeof DeviceMotionEvent !== 'undefined' && typeof DeviceMotionEvent.requestPermission === 'function') {
      DeviceMotionEvent.requestPermission().catch(() => {});
    }
    _audioCtx = _audioCtx || new (window.AudioContext || window.webkitAudioContext)();

    // Background alarm — needs a push subscription so the server can wake the
    // phone even when this tab/app is closed or the screen is locked.
    const ok = await subscribePush();
    if (!ok) {
      // Foreground alarm still works even if background push couldn't be set up
      // (e.g. permission denied, unsupported browser) — don't force the toggle off.
      stuAlert(pushFailReason() + ' The alarm will still work while this Bus tab is open.');
    }
  } else {
    _alertOn = false;
    stopAlarm();
    await unsubscribePush();
  }
}

async function subscribePush() {
  try {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;
    const perm = await Notification.requestPermission();
    if (perm !== 'granted') return false;

    const reg = await navigator.serviceWorker.register(STU_SW_URL);
    await navigator.serviceWorker.ready;

    let sub = await reg.pushManager.getSubscription();
    // If the server's VAPID key was ever rotated, a subscription made under the old key
    // can never receive pushes — drop it and subscribe fresh so no manual step is needed.
    if (sub && sub.options && sub.options.applicationServerKey) {
      const have = new Uint8Array(sub.options.applicationServerKey);
      const want = urlBase64ToUint8Array(VAPID_PUBLIC_KEY);
      if (have.length !== want.length || have.some((b, i) => b !== want[i])) {
        try { await sub.unsubscribe(); } catch (e) {}
        sub = null;
      }
    }
    if (!sub) {
      sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
      });
    }
    const j = sub.toJSON();
    const fd = new FormData();
    fd.append('action', 'subscribe_push');
    fd.append('endpoint', j.endpoint);
    fd.append('p256dh', j.keys.p256dh);
    fd.append('auth', j.keys.auth);
    const r = await fetch(STU_BUS_URL, {method:'POST', body:fd}).then(x=>x.json());
    return !!r.success;
  } catch (e) {
    return false;
  }
}

async function unsubscribePush() {
  try {
    if (!('serviceWorker' in navigator)) return;
    const reg = await navigator.serviceWorker.getRegistration(STU_SW_URL);
    const sub = reg ? await reg.pushManager.getSubscription() : null;
    const fd = new FormData();
    fd.append('action', 'unsubscribe_push');
    if (sub) { fd.append('endpoint', sub.endpoint); await sub.unsubscribe(); }
    await fetch(STU_BUS_URL, {method:'POST', body:fd});
  } catch (e) {}
}

function showBusBanner(msg) {
  let el = document.getElementById('busBanner');
  if (!el) {
    el = document.createElement('div');
    el.id = 'busBanner';
    el.style.cssText = 'position:fixed;top:12px;left:50%;transform:translateX(-50%);z-index:99999;max-width:92vw;background:#16a34a;color:#fff;font:600 .9rem/1.4 system-ui,sans-serif;padding:12px 18px;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.25);cursor:pointer;';
    el.onclick = () => el.remove();
    document.body.appendChild(el);
  }
  el.textContent = msg;
  clearTimeout(el._t);
  el._t = setTimeout(() => el.remove(), 15000);
}

function triggerAlarm(dist) {
  if (_alarmPlaying) return;
  _alarmPlaying = true;
  setTimeout(() => { _alarmPlaying = false; }, 3000);   // always released, even if sound is blocked
  try { if (navigator.vibrate) navigator.vibrate([300, 150, 300, 150, 500]); } catch (e) {}
  showBusBanner('\u{1F68C} ' + BUS_NAME + ' is about ' + (Math.round(dist / 10) * 10) + ' m from your home');
  try {
    _audioCtx = _audioCtx || new (window.AudioContext || window.webkitAudioContext)();
    const play = () => {
      function beep(freq, when, dur) {
        const osc = _audioCtx.createOscillator();
        const gain = _audioCtx.createGain();
        osc.type = 'sine'; osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.4, when);
        gain.gain.exponentialRampToValueAtTime(0.001, when + dur);
        osc.connect(gain); gain.connect(_audioCtx.destination);
        osc.start(when); osc.stop(when + dur);
      }
      const now = _audioCtx.currentTime;
      beep(880, now,       0.15);
      beep(660, now + 0.2, 0.15);
      beep(880, now + 0.4, 0.15);
      beep(1100, now + 0.6, 0.25);
    };
    if (_audioCtx.state === 'suspended') _audioCtx.resume().then(play).catch(() => {});
    else play();
  } catch (e) { /* sound blocked by the browser — the banner + vibration above still alert the student */ }
}

function stopAlarm() { _alarmPlaying = false; }

// ── Haversine distance (meters) ───────────────────────────────────────────────
function haversineM(lat1, lng1, lat2, lng2) {
  const R = 6371000, rad = Math.PI/180;
  const φ1 = lat1*rad, φ2 = lat2*rad;
  const dφ = (lat2-lat1)*rad, dλ = (lng2-lng1)*rad;
  const a = Math.sin(dφ/2)**2 + Math.cos(φ1)*Math.cos(φ2)*Math.sin(dλ/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/resilient-upload.js?v=1"></script>
<script src="<?= BASE_URL ?>/assets/js/idm-poll.js"></script>
<script>
const STU_BASE = <?= json_encode(BASE_URL) ?>;
const STU_CHUNK_API = STU_BASE + '/api/chunk_upload.php';

// ── Tabs ──────────────────────────────────────────────────────────────────────
const STU_PRIMARY_TABS = ['home', 'homework', 'attendance', 'fees'];

// Pure DOM update — no history/back-stack changes here, so both a real click
// and the phone's own back button (via popstate below) can drive it safely.
function _stuActivateTab(name) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('moreNavBtn')?.classList.remove('active');
  document.getElementById('panel_' + name)?.classList.add('active');
  // Every tab has TWO button copies in the DOM — the mobile bottom-nav /
  // "More" sheet item, and the desktop top-bar equivalent (CSS shows only
  // one of the two depending on screen width). Activate BOTH by data-tab so
  // whichever one is actually visible is correctly highlighted, regardless
  // of which copy was clicked (or restored via the saved-tab/hash logic).
  document.querySelectorAll('.tab-btn[data-tab="' + name + '"]').forEach(b => b.classList.add('active'));
  // A tab tucked inside "More" has no bottom-nav icon of its own — light up
  // the More icon itself so the nav still shows where you are.
  if (!STU_PRIMARY_TABS.includes(name)) document.getElementById('moreNavBtn')?.classList.add('active');
  // The Messages screen is a full chat view — hide the bottom nav entirely
  // while it's open (exactly like a real chat app) instead of trying to
  // squeeze the reply box into the sliver of space left above it.
  const tabBarEl = document.getElementById('tabBar');
  if (tabBarEl) tabBarEl.style.display = (name === 'messages') ? 'none' : '';
  document.body.classList.toggle('stu-chat-open', name === 'messages');
  try { localStorage.setItem('stu_tab', name); } catch(e) {}
}

// Whether a "you can go back to Home" entry is currently sitting in the
// browser/WebView history — pushed once on leaving Home, not once per tab.
let _stuHasBackEntry = false;

// Called by every tab button's onclick. Most parents will actually use the
// PHONE'S OWN back button rather than the in-app one, and by default that
// exits the whole WebView (there's nothing on the history stack to go back
// to, since every tab switch only ever replaced the current URL). Pushing
// exactly one real history entry when leaving Home fixes that: the phone's
// back button then correctly returns to Home first (see popstate below),
// and only exits the app on a second press from Home — the same pattern
// virtually every native app uses for a bottom-tab layout.
function showTab(name, btn) {
  _stuActivateTab(name);
  if (name === 'home') {
    _stuHasBackEntry = false;
    if (history.replaceState) history.replaceState({tab: 'home'}, '', '#home');
  } else if (!_stuHasBackEntry && history.pushState) {
    history.pushState({tab: name}, '', '#' + name);
    _stuHasBackEntry = true;
  } else if (history.replaceState) {
    history.replaceState({tab: name}, '', '#' + name);
  }
}

window.addEventListener('popstate', () => {
  _stuHasBackEntry = false;
  _stuActivateTab('home');
});

// ── "More" sheet ──────────────────────────────────────────────────────────────
function openMoreSheet() {
  document.getElementById('moreSheetOverlay').classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeMoreSheet() {
  document.getElementById('moreSheetOverlay').classList.remove('show');
  document.body.style.overflow = '';
}
// Unread messages live inside "More" — light up its dot too so an unread
// message isn't missed just because it's not one of the 4 bottom-nav icons.
// Only ever turns the dot ON here; never hides it (a real complaint/overdue
// book reason may already be showing it from the page's initial render).
function updateMoreDot() {
  const dot = document.getElementById('moreDot');
  const msgBadge = document.querySelector('.stu-msg-badge-el');
  if (dot && msgBadge && msgBadge.style.display !== 'none' && msgBadge.textContent.trim() !== '') {
    dot.style.display = '';
  }
}
function toggleResult(idx) {
  document.getElementById('resultBlock' + idx).classList.toggle('open');
}

// ── HW filter ─────────────────────────────────────────────────────────────────
function filterHw(status, btn) {
  document.querySelectorAll('.hw-filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.hw-card').forEach(card => {
    const match = status === 'all' || card.dataset.status === status;
    card.classList.toggle('hidden', !match);
  });
}

// ── Attendance Calendar ────────────────────────────────────────────────────────
const ATT_DATA  = <?= json_encode($attCalData) ?>;
const HOL_DATA  = <?= json_encode($attHolidays) ?>; // date -> {reason, type}
const _CAL_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const _CAL_DOWS   = ['Su','Mo','Tu','We','Th','Fr','Sa'];
const _CAL_SC     = {present:'p', absent:'a', late:'l', half_day:'h', leave:'lv'};

// Open on today's real month when the CURRENT session is selected; for a past
// session (picked via the Session dropdown), open on the most recent month
// that actually has data instead — "today" would fall outside that year.
const ATT_IS_CURRENT_SESSION = <?= $acadYear === $currentAcadYear ? 'true' : 'false' ?>;
let _attCalYear, _attCalMonth;
if (ATT_IS_CURRENT_SESSION) {
  _attCalYear  = new Date().getFullYear();
  _attCalMonth = new Date().getMonth();
} else {
  const attDates = Object.keys(ATT_DATA).sort();
  const lastDate = attDates.length ? attDates[attDates.length - 1] : (<?= (int)$acadYear ?> + '-04-01');
  const [ly, lm] = lastDate.split('-').map(Number);
  _attCalYear = ly; _attCalMonth = lm - 1;
}

function attCalPrev() {
  if (_attCalMonth === 0) { _attCalMonth = 11; _attCalYear--; } else _attCalMonth--;
  renderAttCal();
}
function attCalNext() {
  if (_attCalMonth === 11) { _attCalMonth = 0; _attCalYear++; } else _attCalMonth++;
  renderAttCal();
}
function renderAttCal() {
  const yr = _attCalYear, mo = _attCalMonth;
  document.getElementById('attCalTitle').textContent = _CAL_MONTHS[mo] + ' ' + yr;
  const firstDow    = new Date(yr, mo, 1).getDay();
  const daysInMonth = new Date(yr, mo + 1, 0).getDate();
  const now         = new Date();
  const todayStr    = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');
  let html = _CAL_DOWS.map(d => `<div class="att-cal-dow">${d}</div>`).join('');
  for (let i = 0; i < firstDow; i++) html += '<div class="att-cal-day blank"></div>';
  const monthHolidays = []; // for the "why is this day off" list below the grid
  for (let d = 1; d <= daysInMonth; d++) {
    const ds     = yr + '-' + String(mo+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
    const status = ATT_DATA[ds];
    const hol    = HOL_DATA[ds];
    const isSun  = new Date(yr, mo, d).getDay() === 0;
    const isTdy  = ds === todayStr ? ' today' : '';
    let sc, tip;
    if (status)     { sc = _CAL_SC[status] || 'em'; tip = status.replace('_',' '); }
    else if (hol)   { sc = hol.type === 'public' ? 'holp' : 'hols'; tip = hol.reason; monthHolidays.push({d, reason: hol.reason, type: hol.type}); }
    else if (isSun) { sc = 'sun'; tip = 'Sunday'; }
    else            { sc = 'em'; tip = ''; }
    html += `<div class="att-cal-day ${sc}${isTdy}" title="${tip}">${d}</div>`;
  }
  document.getElementById('attCalGrid').innerHTML = html;

  // Visible list of this month's holidays and why — works on touch, not just hover
  const listEl = document.getElementById('attCalHolList');
  if (monthHolidays.length) {
    listEl.style.display = '';
    listEl.innerHTML = monthHolidays.map(h => `
      <div class="att-cal-hol-row">
        <span class="att-cal-hol-dot ${h.type === 'public' ? 'holp' : 'hols'}"></span>
        <span class="att-cal-hol-date">${h.d} ${_CAL_MONTHS[mo].slice(0,3)}</span>
        <span class="att-cal-hol-reason">${h.reason}</span>
        <span class="att-cal-hol-badge">${h.type === 'public' ? 'Official' : 'Special'}</span>
      </div>`).join('');
  } else {
    listEl.style.display = 'none';
    listEl.innerHTML = '';
  }
}
renderAttCal();

// ── Month / Year view switch ────────────────────────────────────────────────
function attSetView(v) {
  document.getElementById('attMonthView').style.display = v === 'month' ? '' : 'none';
  document.getElementById('attYearView').style.display  = v === 'year'  ? '' : 'none';
  document.getElementById('attViewBtnMonth').classList.toggle('active', v === 'month');
  document.getElementById('attViewBtnYear').classList.toggle('active', v === 'year');
  if (v === 'year') renderAttYear();
}

// ── Year at a Glance — one bar per month of the academic year (Apr → Mar) ──────
// Computed straight from ATT_DATA (already loaded for the whole academic year),
// so no extra server request is needed to switch views.
function renderAttYear() {
  const acadYear = <?= (int)$acadYear ?>;
  // April (month index 3) of acadYear through March (index 2) of acadYear+1
  const months = [];
  for (let i = 0; i < 12; i++) {
    const mi = (3 + i) % 12;
    const yr = mi >= 3 ? acadYear : acadYear + 1;
    months.push({ mi, yr });
  }
  const now = new Date();
  const todayStr = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');

  let html = '';
  months.forEach(({mi, yr}) => {
    const prefix = yr + '-' + String(mi+1).padStart(2,'0') + '-';
    let p=0, a=0, l=0, h=0, lv=0, marked=0;
    Object.keys(ATT_DATA).forEach(ds => {
      if (!ds.startsWith(prefix)) return;
      marked++;
      const st = ATT_DATA[ds];
      if (st==='present') p++; else if (st==='absent') a++; else if (st==='late') l++;
      else if (st==='half_day') h++; else if (st==='leave') lv++;
    });
    const pct = marked > 0 ? Math.round((p + l*0.5 + h*0.5) / marked * 100) : null;
    const isFuture = (yr + '-' + String(mi+1).padStart(2,'0') + '-01') > todayStr && marked === 0;
    const label = _CAL_MONTHS[mi].slice(0,3) + " '" + String(yr).slice(2);

    let barHtml;
    if (pct === null) {
      barHtml = `<div class="att-year-bar-fill none"></div><span class="att-year-pct outside" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);">${isFuture ? 'Upcoming' : 'No record'}</span>`;
    } else {
      const cls = pct >= 75 ? 'good' : 'warn';
      barHtml = `<div class="att-year-bar-fill ${cls}" style="width:${Math.max(pct,14)}%;"><span class="att-year-pct">${pct}%</span></div>`;
    }

    html += `<div class="att-year-row" onclick="attJumpToMonth(${mi},${yr})">
      <div class="att-year-mlabel">${label}</div>
      <div class="att-year-bar-track">${barHtml}</div>
      <div class="att-year-sub">${marked ? p+'P '+a+'A' : ''}</div>
    </div>`;
  });
  document.getElementById('attYearGrid').innerHTML = html;
}

function attJumpToMonth(mi, yr) {
  _attCalMonth = mi; _attCalYear = yr;
  renderAttCal();
  attSetView('month');
}

// ── Image viewer ──────────────────────────────────────────────────────────────
function openImageViewer(src) {
  document.getElementById('imgViewerImg').src = src;
  document.getElementById('imgViewer').classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeImageViewer() {
  document.getElementById('imgViewer').classList.remove('show');
  document.body.style.overflow = '';
}

// ── Modal helpers ──────────────────────────────────────────────────────────────
function openModal(id) { document.getElementById(id).classList.add('show'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('show'); document.body.style.overflow=''; }
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', function(e) { if (e.target===this) closeModal(this.id); });
});

// ── Utilities ─────────────────────────────────────────────────────────────────
function escHtml(s) {
  const d = document.createElement('div'); d.textContent = String(s??''); return d.innerHTML;
}

// ── Native-style alert/confirm — drop-in replacements for window.alert()/confirm()
// so popups look like part of the app, not a browser "site says" dialog.
function stuAlert(message, opts) {
  opts = opts || {};
  return new Promise(resolve => {
    const ov = document.createElement('div');
    ov.className = 'stu-native-alert-overlay';
    ov.innerHTML = '<div class="stu-native-alert-box">'
      + '<div class="stu-native-alert-msg">' + escHtml(message) + '</div>'
      + '<div class="stu-native-alert-actions"><button class="stu-native-alert-btn primary" id="stuAlertOkBtn">'
      + escHtml(opts.okLabel || 'OK') + '</button></div></div>';
    document.body.appendChild(ov);
    const done = () => { ov.remove(); resolve(true); };
    document.getElementById('stuAlertOkBtn').onclick = done;
    ov.addEventListener('click', e => { if (e.target === ov) done(); });
  });
}
function stuConfirm(message, opts) {
  opts = opts || {};
  return new Promise(resolve => {
    const ov = document.createElement('div');
    ov.className = 'stu-native-alert-overlay';
    ov.innerHTML = '<div class="stu-native-alert-box">'
      + '<div class="stu-native-alert-msg">' + escHtml(message) + '</div>'
      + '<div class="stu-native-alert-actions two">'
      + '<button class="stu-native-alert-btn ghost" id="stuConfirmCancelBtn">' + escHtml(opts.cancelLabel || 'Cancel') + '</button>'
      + '<button class="stu-native-alert-btn ' + (opts.danger === false ? 'primary' : 'danger') + '" id="stuConfirmOkBtn">' + escHtml(opts.okLabel || 'Delete') + '</button>'
      + '</div></div>';
    document.body.appendChild(ov);
    const done = (val) => { ov.remove(); resolve(val); };
    document.getElementById('stuConfirmCancelBtn').onclick = () => done(false);
    document.getElementById('stuConfirmOkBtn').onclick = () => done(true);
    ov.addEventListener('click', e => { if (e.target === ov) done(false); });
  });
}
function showMsg(el, ok, text) {
  el.className = 'modal-msg ' + (ok ? 'ok' : 'err');
  el.style.display = 'block';
  el.innerHTML = (ok ? '<i class="bi bi-check-circle-fill"></i> ' : '<i class="bi bi-exclamation-circle-fill"></i> ') + escHtml(text);
}

// ── EDIT PROFILE ──────────────────────────────────────────────────────────────
let epCropper = null, croppedBlob = null;

function openEditProfile() {
  croppedBlob = null;
  openModal('editProfileModal');
}
function closeEditProfile() {
  cancelCrop();
  croppedBlob = null;
  document.getElementById('epPhoto').value = '';
  document.getElementById('epCropDone').style.display = 'none';
  document.getElementById('epMsg').style.display = 'none';
  closeModal('editProfileModal');
}

function initCropper() {
  const f = document.getElementById('epPhoto').files[0];
  if (!f) return;
  if (epCropper) { epCropper.destroy(); epCropper = null; }
  croppedBlob = null;
  document.getElementById('epCropDone').style.display = 'none';
  const img = document.getElementById('epCropperImg');
  img.src = URL.createObjectURL(f);
  document.getElementById('epCropperArea').style.display = 'block';
  img.onload = function() {
    epCropper = new Cropper(img, {
      aspectRatio: 1, viewMode: 1, dragMode: 'move',
      autoCropArea: 0.85, restore: false, guides: true,
      center: true, highlight: false, cropBoxMovable: true,
      cropBoxResizable: true, toggleDragModeOnDblclick: false, background: false,
    });
  };
}

function rotateCrop(deg) { if (epCropper) epCropper.rotate(deg); }

function confirmCrop() {
  if (!epCropper) return;
  const canvas = epCropper.getCroppedCanvas({ width: 480, height: 480, imageSmoothingQuality: 'high' });
  canvas.toBlob(blob => {
    croppedBlob = blob;
    document.getElementById('epPhotoPreview').innerHTML = `<img src="${canvas.toDataURL('image/jpeg', 0.92)}" style="width:100%;height:100%;object-fit:cover;">`;
    document.getElementById('epCropperArea').style.display = 'none';
    document.getElementById('epCropDone').style.display = 'block';
    epCropper.destroy(); epCropper = null;
  }, 'image/jpeg', 0.92);
}

function cancelCrop() {
  if (epCropper) { epCropper.destroy(); epCropper = null; }
  document.getElementById('epCropperArea').style.display = 'none';
  document.getElementById('epPhoto').value = '';
  croppedBlob = null;
}

async function submitProfileRequest() {
  const btn = document.getElementById('epSubmitBtn');
  const msg = document.getElementById('epMsg');
  btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting…';
  const fields = <?= json_encode(array_keys($visibleEditableFields)) ?>;
  const fd = new FormData();
  fd.append('action','submit_profile_request');
  fields.forEach(f => { const el = document.getElementById('ep_'+f); if (el) fd.append(f, el.value); });
  // Photo rides along in this same request as a normal file field — one round
  // trip, protected by idmFetch's own hard timeout, instead of the old
  // ~15-18 separate chunk-upload requests via resilientUpload()/chunk_upload.php
  // (init + one per 128KB piece + finalize), where any single dropped step on
  // a slow school connection failed the whole submission.
  const photoBlob = croppedBlob || document.getElementById('epPhoto').files[0];
  if (photoBlob) {
    fd.append('photo_new', photoBlob, 'photo.jpg');
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Uploading & Submitting…';
  }
  try {
    const r = await idmFetch(STU_BASE + '/student/api.php', {method:'POST',body:fd}, photoBlob ? 90000 : 45000);
    const d = await r.json();
    showMsg(msg, d.success, d.message);
    if (d.success) setTimeout(() => { closeEditProfile(); location.reload(); }, 1800);
  } catch(e) {
    showMsg(msg, false, e && e.name === 'AbortError'
      ? 'Network timeout — the connection is too slow or was interrupted. Please try again.'
      : 'Network error. Please try again.');
  }
  btn.disabled=false; btn.innerHTML='<i class="bi bi-send-fill"></i> Submit for Approval';
}

// ── COMPLAINTS ─────────────────────────────────────────────────────────────────
function openNewComplaint() {
  document.getElementById('cmpSubject').value='';
  document.getElementById('cmpDesc').value='';
  document.getElementById('cmpFiles').value='';
  document.getElementById('cmpFileList').innerHTML='';
  document.getElementById('cmpMsg').style.display='none';
  openModal('newComplaintModal');
}
function closeNewComplaint() { closeModal('newComplaintModal'); }

function updateCmpFiles() {
  const files = document.getElementById('cmpFiles').files;
  document.getElementById('cmpFileList').innerHTML =
    Array.from(files).map(f=>`<div><i class="bi bi-paperclip"></i> ${escHtml(f.name)} (${(f.size/1024).toFixed(1)}KB)</div>`).join('');
}

async function submitComplaint() {
  const btn = document.getElementById('cmpSubmitBtn');
  const msg = document.getElementById('cmpMsg');
  const subject = document.getElementById('cmpSubject').value.trim();
  const desc    = document.getElementById('cmpDesc').value.trim();
  if (!subject || !desc) { showMsg(msg, false, 'Please fill in subject and description.'); return; }
  btn.disabled=true; btn.innerHTML='<i class="bi bi-hourglass-split"></i> Submitting…';
  const fd = new FormData();
  fd.append('action','submit_complaint'); fd.append('subject',subject); fd.append('description',desc);
  Array.from(document.getElementById('cmpFiles').files).forEach(f => fd.append('attachments[]', f));
  try {
    const r = await fetch(STU_BASE + '/student/api.php', {method:'POST',body:fd});
    const d = await r.json();
    showMsg(msg, d.success, d.message);
    if (d.success) setTimeout(() => { closeNewComplaint(); refreshComplaints(); }, 1600);
  } catch(e) { showMsg(msg, false, 'Network error.'); }
  btn.disabled=false; btn.innerHTML='<i class="bi bi-send-fill"></i> Submit Complaint';
}

let currentCmpId = null;

async function openComplaintThread(cid) {
  currentCmpId = cid;
  openModal('threadModal');
  const msgs = document.getElementById('threadMessages');
  msgs.innerHTML = '<div style="text-align:center;padding:32px;color:var(--muted);"><i class="bi bi-hourglass-split"></i> Loading…</div>';
  try {
    const r = await fetch(STU_BASE + '/student/api.php?action=get_complaint_thread&id=' + cid);
    const d = await r.json();
    if (!d.success) { msgs.innerHTML='<div style="padding:16px;color:var(--red);">'+escHtml(d.message)+'</div>'; return; }
    const comp = d.complaint;
    document.getElementById('threadModalTitle').textContent = comp.subject;
    document.getElementById('threadModalStatus').textContent = 'Status: ' + comp.status.replace(/_/g,' ');
    document.getElementById('threadReplySection').style.display = comp.status === 'closed' ? 'none' : '';
    let html = buildMsg('student', 'You (Student)', comp.description, comp.created_at, comp.attachments);
    d.replies.forEach(rep => {
      html += buildMsg(rep.reply_by, rep.reply_by==='admin' ? (rep.admin_name||'School Admin') : 'You (Student)', rep.message, rep.created_at, rep.attachments);
    });
    msgs.innerHTML = html;
    msgs.scrollTop = msgs.scrollHeight;
  } catch(e) { msgs.innerHTML='<div style="padding:16px;color:var(--red);">Network error.</div>'; }
}

function buildMsg(type, who, text, time, atts) {
  let attsHtml = '';
  (atts||[]).forEach(a => {
    const src = STU_BASE + '/assets/uploads/stu_complaints/' + a.name;
    attsHtml += a.type==='video'
      ? `<video src="${src}" controls class="thread-att"></video>`
      : `<img src="${src}" class="thread-att" onclick="openImageViewer(this.src)" style="cursor:zoom-in;">`;
  });
  const dt = new Date(time).toLocaleDateString('en-IN',{day:'2-digit',month:'short'})
           + ' ' + new Date(time).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});
  return `<div class="thread-msg-wrap ${type}">
    <div class="thread-bubble ${type}">
      <div class="thread-who">${escHtml(who)}</div>
      <div>${escHtml(text)}</div>${attsHtml}
      <div class="thread-time">${dt}</div>
    </div>
  </div>`;
}

function closeThreadModal() { closeModal('threadModal'); currentCmpId = null; }

async function sendThreadReply() {
  if (!currentCmpId) return;
  const msg = document.getElementById('threadReply').value.trim();
  if (!msg) { await stuAlert('Please enter a message.'); return; }
  const fd = new FormData();
  fd.append('action','add_complaint_reply'); fd.append('complaint_id',currentCmpId); fd.append('message',msg);
  const file = document.getElementById('threadFile').files[0];
  if (file) fd.append('file', file);
  try {
    const r = await fetch(STU_BASE + '/student/api.php', {method:'POST',body:fd});
    const d = await r.json();
    if (d.success) {
      document.getElementById('threadReply').value='';
      document.getElementById('threadFile').value='';
      document.getElementById('threadFileName').textContent='';
      openComplaintThread(currentCmpId);
    } else await stuAlert(d.message);
  } catch(e) { await stuAlert('Network error.'); }
}

// ── HOMEWORK ──────────────────────────────────────────────────────────────────
let _hwSubCroppedBlob = null;

function openSubmitHw(hwId, title) {
  document.getElementById('hwSubmitId').value = hwId;
  document.getElementById('hwModalTitle').innerHTML = '<i class="bi bi-send-fill"></i> Submit: ' + escHtml(title);
  document.getElementById('hwTextAnswer').value = '';
  document.getElementById('hwImage').value = '';
  document.getElementById('hwImagePreview').style.display='none';
  document.getElementById('hwMsg').style.display='none';
  _hwSubCroppedBlob = null;
  openModal('submitHwModal');
}
function closeSubmitHw() { closeModal('submitHwModal'); hwSubCloseCameraCapture(); }

function previewHwSub() {
  _hwSubCroppedBlob = null; // a manually chosen file takes over from any prior camera capture
  const f = document.getElementById('hwImage').files[0];
  const wrap = document.getElementById('hwImagePreview');
  if (f) { document.getElementById('hwImagePreviewImg').src = URL.createObjectURL(f); wrap.style.display='block'; }
  else wrap.style.display='none';
}

// ── Live camera capture + crop for homework submission photo ──────────────────
let _hwSubCameraStream = null;
let _hwSubCameraFacing = 'environment'; // default to the back/rear camera
let _hwSubCropper = null;

async function hwSubOpenCameraCapture() {
  const overlay    = document.getElementById('hwSubCameraOverlay');
  const video      = document.getElementById('hwSubCameraVideo');
  const errBox     = document.getElementById('hwSubCameraErrorMsg');
  const captureBtn = document.getElementById('hwSubBtnCameraCapture');
  const switchBtn  = document.getElementById('hwSubBtnCameraSwitch');

  errBox.style.display = 'none';
  video.style.display  = 'block';
  captureBtn.style.display = 'inline-flex';
  switchBtn.style.display  = 'none';
  overlay.style.display = 'flex';

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    video.style.display = 'none';
    captureBtn.style.display = 'none';
    errBox.textContent = 'Camera not supported on this browser/device. Please use "Choose Photo" instead.';
    errBox.style.display = 'block';
    return;
  }

  try {
    _hwSubCameraStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: _hwSubCameraFacing } }, audio: false
    });
    video.srcObject = _hwSubCameraStream;
    switchBtn.style.display = 'flex';
  } catch (e) {
    video.style.display = 'none';
    captureBtn.style.display = 'none';
    errBox.textContent = 'Could not access camera — please allow camera permission, or use "Choose Photo" instead.';
    errBox.style.display = 'block';
  }
}

async function hwSubSwitchCamera() {
  _hwSubCameraFacing = (_hwSubCameraFacing === 'environment') ? 'user' : 'environment';
  if (_hwSubCameraStream) { _hwSubCameraStream.getTracks().forEach(t => t.stop()); _hwSubCameraStream = null; }
  await hwSubOpenCameraCapture();
}

function hwSubCloseCameraCapture() {
  document.getElementById('hwSubCameraOverlay').style.display = 'none';
  if (_hwSubCameraStream) { _hwSubCameraStream.getTracks().forEach(t => t.stop()); _hwSubCameraStream = null; }
  document.getElementById('hwSubCameraVideo').srcObject = null;
}

function hwSubOpenCropperWithDataUrl(dataUrl) {
  const overlay = document.getElementById('hwSubCropperOverlay');
  const cropImg = document.getElementById('hwSubCropperImg');
  if (_hwSubCropper) { _hwSubCropper.destroy(); _hwSubCropper = null; }
  cropImg.src = '';
  overlay.style.display = 'flex';
  requestAnimationFrame(() => {
    setTimeout(() => {
      cropImg.src = dataUrl;
      cropImg.onload = () => {
        // Free aspect ratio — homework photos are usually full copy/worksheet
        // pages, not square, so the crop shouldn't force a fixed shape.
        _hwSubCropper = new Cropper(cropImg, { viewMode: 1, autoCropArea: 0.95, dragMode: 'move' });
      };
    }, 50);
  });
}

document.getElementById('hwSubBtnTakePhoto').addEventListener('click', hwSubOpenCameraCapture);
document.getElementById('hwSubBtnCameraCancel').addEventListener('click', hwSubCloseCameraCapture);
document.getElementById('hwSubBtnCameraSwitch').addEventListener('click', hwSubSwitchCamera);

document.getElementById('hwSubBtnCameraCapture').addEventListener('click', () => {
  const video = document.getElementById('hwSubCameraVideo');
  if (!video.videoWidth) return;
  const canvas = document.createElement('canvas');
  canvas.width  = video.videoWidth;
  canvas.height = video.videoHeight;
  canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
  const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
  hwSubCloseCameraCapture();
  hwSubOpenCropperWithDataUrl(dataUrl);
});

document.getElementById('hwSubBtnCropUse').addEventListener('click', () => {
  if (!_hwSubCropper) return;
  _hwSubCropper.getCroppedCanvas().toBlob(blob => {
    _hwSubCroppedBlob = blob;
    const url = URL.createObjectURL(blob);
    document.getElementById('hwImagePreviewImg').src = url;
    document.getElementById('hwImagePreview').style.display = 'block';
    document.getElementById('hwSubCropperOverlay').style.display = 'none';
    if (_hwSubCropper) { _hwSubCropper.destroy(); _hwSubCropper = null; }
    document.getElementById('hwImage').value = ''; // camera photo replaces any chosen file
  }, 'image/jpeg', 0.92);
});

document.getElementById('hwSubBtnCropCancel').addEventListener('click', () => {
  document.getElementById('hwSubCropperOverlay').style.display = 'none';
  if (_hwSubCropper) { _hwSubCropper.destroy(); _hwSubCropper = null; }
});

async function submitHwAnswer() {
  const btn = document.getElementById('hwSubmitBtn');
  const msg = document.getElementById('hwMsg');
  const hwId = document.getElementById('hwSubmitId').value;
  const text = document.getElementById('hwTextAnswer').value.trim();
  const img  = document.getElementById('hwImage').files[0];
  if (!text && !img && !_hwSubCroppedBlob) { showMsg(msg,false,'Please write an answer or upload a photo of your work.'); return; }
  btn.disabled=true; btn.innerHTML='<i class="bi bi-hourglass-split"></i> Submitting…';
  const fd = new FormData();
  fd.append('action','submit_homework'); fd.append('homework_id',hwId);
  if (text) fd.append('text_answer',text);
  // Photo rides along in this same request as a normal file field — one round
  // trip, protected by idmFetch's own hard timeout, instead of the old
  // ~15-18 separate chunk-upload requests via resilientUpload()/chunk_upload.php
  // (init + one per 128KB piece + finalize), where any single dropped step on
  // a slow school connection failed the whole submission.
  const photoBlob = _hwSubCroppedBlob || img;
  if (photoBlob) {
    fd.append('sub_image', photoBlob, 'photo.jpg');
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Uploading & Submitting…';
  }
  try {
    // Shared IDM one-shot fetch (idm-poll.js) — same hang-proofing used across
    // the site now, instead of a one-off AbortController just for this call.
    const r = await idmFetch(STU_BASE + '/student/api.php', {method:'POST',body:fd}, photoBlob ? 120000 : 45000);
    const d = await r.json();
    showMsg(msg, d.success, d.message);
    if (d.success) { _hwSubCroppedBlob = null; setTimeout(() => { closeSubmitHw(); refreshHomework(); }, 1500); }
  } catch(e) {
    showMsg(msg,false, e && e.name === 'AbortError'
      ? 'Network timeout — the connection is too slow or was interrupted. Please try again.'
      : 'Network error — please check your connection and try again.');
  }
  btn.disabled=false; btn.innerHTML='<i class="bi bi-send-fill"></i> Submit Homework';
}

// ── Student Messages ──────────────────────────────────────────────────────────
const STU_MSG_API = STU_BASE + '/api/student_message_actions.php';
let _stuMsgLoaded = false;
let _stuCmpUploadedImg = '';
let _stuMsgs = [];

function loadStuMessages() {
  if (!_stuMsgLoaded) {
    _stuMsgLoaded = true;
    fetchStuMessages();
  }
}

function stuFmtTime(ts) {
  if (!ts) return '';
  const d = new Date(ts.replace(' ','T'));
  const now = new Date();
  const diff = (now - d) / 1000;
  if (diff < 60)    return 'Just now';
  if (diff < 3600)  return Math.floor(diff/60) + 'm ago';
  if (diff < 86400) return d.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});
  return d.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'});
}

function stuFmtDate(ts) {
  const d = new Date(ts.replace(' ','T'));
  const now = new Date();
  if (d.toDateString() === now.toDateString()) return 'Today';
  const y = new Date(now); y.setDate(y.getDate()-1);
  if (d.toDateString() === y.toDateString()) return 'Yesterday';
  return d.toLocaleDateString('en-IN',{day:'numeric',month:'long',year:'numeric'});
}

async function fetchStuMessages() {
  const el = document.getElementById('stuMsgThread');
  try {
    const res = await fetch(STU_MSG_API + '?action=my_messages').then(r=>r.json());
    if (!res.ok) {
      el.innerHTML = `<div style="text-align:center;padding:20px;color:#ef4444;font-size:.83rem;">${escHtml(res.msg||'Error loading messages.')}</div>`;
      return;
    }
    // API returns newest first — reverse for chronological display
    _stuMsgs = (res.messages || []).slice().reverse();
    const unread = _stuMsgs.filter(m => !m.is_mine && !m.is_read).length;
    document.querySelectorAll('.stu-msg-badge-el').forEach(badge => {
      badge.textContent = unread>0?unread:''; badge.style.display = unread>0?'':'none';
    });
    updateMoreDot();
    renderStuThread();
  } catch(e) {
    document.getElementById('stuMsgThread').innerHTML = '<div style="text-align:center;padding:20px;color:#ef4444;font-size:.83rem;">Network error. Please try again.</div>';
  }
}

function renderStuThread() {
  const el = document.getElementById('stuMsgThread');
  if (!_stuMsgs.length) {
    el.innerHTML = `<div style="text-align:center;padding:40px 20px;color:#8696a0;">
      <div style="font-size:3rem;margin-bottom:10px;">💬</div>
      <div style="font-weight:600;margin-bottom:4px;">No messages yet</div>
      <div style="font-size:.78rem;">Messages from school will appear here.<br>You can also send a message to school below.</div>
    </div>`;
    return;
  }
  let html = '', lastDate = '';
  for (const m of _stuMsgs) {
    const dateStr = stuFmtDate(m.created_at);
    if (dateStr !== lastDate) {
      html += `<div class="stu-date-sep"><span>${escHtml(dateStr)}</span></div>`;
      lastDate = dateStr;
    }
    const mine = !!m.is_mine;
    const dir = mine ? 'out' : 'in';
    const imgHtml   = m.image   ? `<img class="stu-bubble-img" src="${escHtml(STU_BASE+'/'+m.image)}" onclick="stuOpenImg(this.src)">` : '';
    const autoLabel = m.is_auto ? `<div class="stu-auto-badge"><i class="bi bi-robot"></i> Auto: ${escHtml(m.auto_event||'')}</div>` : '';
    const delBtn = mine ? `<button class="stu-del-btn" onclick="deleteStuMsg(${m.id})" title="Delete"><i class="bi bi-trash3"></i></button>` : '';
    html += `<div class="stu-bubble ${dir}" id="stumsg-${m.id}">
      ${autoLabel}
      <div class="stu-bubble-inner">${imgHtml}${escHtml(m.body||'')}
        <div class="stu-bubble-footer">
          <span class="stu-bubble-meta">${stuFmtTime(m.created_at)}</span>${delBtn}
        </div>
      </div>
    </div>`;
  }
  el.innerHTML = html;
  el.scrollTop = el.scrollHeight;
}

function stuOpenImg(src) {
  document.getElementById('stuMsgImgLbSrc').src = src;
  document.getElementById('stuMsgImgLb').style.display = 'flex';
}

async function deleteStuMsg(id) {
  if (!(await stuConfirm('Delete this message?', {okLabel:'Delete'}))) return;
  try {
    const fd = new FormData();
    fd.append('action', 'delete_msg');
    fd.append('message_id', id);
    const res = await fetch(STU_MSG_API, {method:'POST', body:fd}).then(r=>r.json());
    if (!res.ok) { await stuAlert(res.msg); return; }
    _stuMsgs = _stuMsgs.filter(m => m.id != id);
    renderStuThread();
  } catch(e) { await stuAlert('Network error. Please try again.'); }
}

function autoGrowStu(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function clearStuCmpImg() {
  _stuCmpUploadedImg = '';
  document.getElementById('stuCmpImgPreview').style.display = 'none';
  document.getElementById('stuCmpImg').value = '';
}

document.getElementById('stuCmpImg').addEventListener('change', async function() {
  if (!this.files[0]) return;
  const file = this.files[0];
  const nameEl = document.getElementById('stuCmpImgName');
  nameEl.textContent = 'Uploading…';
  document.getElementById('stuCmpImgPreview').style.display = 'flex';
  // One direct request instead of the old chunk-upload dance (resilientUpload
  // → chunk_upload.php in ~15-18 steps) — idmFetch's own timeout below is
  // what protects a slow upload now.
  try {
    const fd = new FormData();
    fd.append('action','upload_img');
    fd.append('image', file, file.name || 'photo.jpg');
    const r = await idmFetch(STU_MSG_API, {method:'POST',body:fd}, 60000);
    const res = await r.json();
    if (!res.ok) { await stuAlert(res.msg); document.getElementById('stuCmpImgPreview').style.display = 'none'; return; }
    _stuCmpUploadedImg = res.path;
    document.getElementById('stuCmpImgThumb').src = STU_BASE + '/' + res.path;
    nameEl.textContent = res.path.split('/').pop();
  } catch(e) {
    document.getElementById('stuCmpImgPreview').style.display = 'none';
    await stuAlert(e && e.name === 'AbortError'
      ? 'Network timeout — the connection is too slow or was interrupted. Please try again.'
      : 'Image upload failed. Please try again.');
  }
});

async function submitStuMsg() {
  const body = document.getElementById('stuCmpBody').value.trim();
  if (!body && !_stuCmpUploadedImg) {
    await stuAlert('Please write a message or attach an image.');
    return;
  }
  const btn = document.getElementById('stuCmpSendBtn');
  btn.disabled = true;
  const fd = new FormData();
  fd.append('action','send');
  fd.append('body', body);
  fd.append('image', _stuCmpUploadedImg);
  try {
    const res = await fetch(STU_MSG_API, {method:'POST',body:fd}).then(r=>r.json());
    if (!res.ok) { await stuAlert(res.msg); return; }
    document.getElementById('stuCmpBody').value = '';
    autoGrowStu(document.getElementById('stuCmpBody'));
    clearStuCmpImg();
    _stuMsgLoaded = false;
    fetchStuMessages();
  } catch(e) { await stuAlert('Network error. Please try again.'); }
  finally { btn.disabled = false; }
}

// ── Notification sound (Web Audio API — no file needed) ───────────────────────
function playMsgTone() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const t = ctx.currentTime;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain); gain.connect(ctx.destination);
    osc.type = 'sine';
    osc.frequency.setValueAtTime(880, t);
    osc.frequency.setValueAtTime(660, t + 0.12);
    gain.gain.setValueAtTime(0.4, t);
    gain.gain.exponentialRampToValueAtTime(0.001, t + 0.35);
    osc.start(t); osc.stop(t + 0.35);
    setTimeout(() => ctx.close(), 500);
  } catch(e) {}
}

// Check unread count on page load (for badge without clicking tab)
let _stuPrevUnread = 0;
(async function checkStuMsgUnread() {
  try {
    const res = await fetch(STU_MSG_API + '?action=unread_count').then(r=>r.json());
    if (res.ok && res.count > 0) {
      document.querySelectorAll('.stu-msg-badge-el').forEach(badge => {
        badge.textContent = res.count; badge.style.display = '';
      });
      updateMoreDot();
      if (res.count > _stuPrevUnread && _stuPrevUnread >= 0) playMsgTone();
      _stuPrevUnread = res.count;
    }
  } catch(e) {}
})();

// Shared IDM sync poller (idm-poll.js) — a tiny unread-count ping instead of
// a full message re-fetch on a timer. Built-in timeout + exponential backoff
// keep this reliable on a weak/2G connection (common in rural areas) instead
// of piling up requests when the network is struggling.
createIdmPoller({
  url: STU_MSG_API + '?action=unread_count',
  onData: (res) => {
    if (!res.ok) return;
    document.querySelectorAll('.stu-msg-badge-el').forEach(badge => {
      badge.textContent = res.count>0?res.count:''; badge.style.display=res.count>0?'':'none';
    });
    updateMoreDot();
    if (res.count > _stuPrevUnread) { playMsgTone(); if (_stuMsgLoaded) { _stuMsgLoaded=false; fetchStuMessages(); } }
    _stuPrevUnread = res.count;
  },
}).start();

// ── Messages: auto-refresh thread every 15s when tab is active ─────────────────
setInterval(() => {
  if (document.getElementById('panel_messages')?.classList.contains('active')) {
    fetchStuMessages();
  }
}, 15000);

// ── Homework: JS renderer + live poll ─────────────────────────────────────────
let _hwRefreshing = false;

function fmtDateJs(ds) {
  if (!ds) return '—';
  try { return new Date(ds.replace(' ','T')).toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'}); }
  catch(e) { return ds; }
}

function buildHwCard(hw) {
  const status = hw.sub_id ? (hw.sub_status || 'submitted') : 'pending';
  const dueTs  = hw.due_date ? new Date(hw.due_date + 'T23:59:59').getTime() : 0;
  const isOD   = hw.due_date && dueTs < Date.now() && !hw.sub_id;
  const icons  = {pending:'📝',submitted:'⏳',checked:'✅',returned:'📋'};
  const bcls   = {pending:'badge-orange',submitted:'badge-blue',checked:'badge-green',returned:'badge-purple'};
  const blbl   = {pending:'Pending',submitted:'Submitted',checked:'Checked',returned:'Returned'};

  let h = `<div class="hw-card ${status}" data-status="${status}">
    <div class="hw-card-top">
      <div class="hw-icon ${status}">${icons[status]||'📝'}</div>
      <div class="hw-info">
        <div class="hw-title">${escHtml(hw.title||'')}</div>
        <div class="hw-meta">
          ${hw.subject_name?`<span><i class="bi bi-book-fill" style="color:var(--primary);"></i>${escHtml(hw.subject_name)}</span>`:''}
          ${hw.teacher_name?`<span><i class="bi bi-person-fill" style="color:var(--teal);"></i>${escHtml(hw.teacher_name)}</span>`:''}
        </div>
      </div>
      <span class="badge ${bcls[status]||'badge-gray'}">${blbl[status]||status}</span>
    </div>`;
  if (hw.due_date) {
    h += `<div class="hw-due${isOD?' overdue':''}">
      <i class="bi bi-${isOD?'exclamation-triangle-fill':'calendar-event'}"></i>
      Due: ${fmtDateJs(hw.due_date)}${isOD?' — OVERDUE!':''}
    </div>`;
  }
  if (hw.description) h += `<div class="hw-desc">${escHtml(hw.description)}</div>`;
  if (hw.image && !hw.sub_id) {
    h += `<div class="hw-img"><img src="${STU_BASE}/assets/uploads/hw_images/${escHtml(hw.image)}" onclick="openImageViewer(this.src)" alt=""></div>`;
  }
  if (hw.sub_id) {
    h += `<div class="hw-your-ans"><div class="hw-your-ans-lbl">Your Answer</div>
      ${hw.text_answer?`<div>${escHtml(hw.text_answer)}</div>`:''}
      ${hw.sub_image?`<div class="hw-sub-img"><img src="${STU_BASE}/assets/uploads/stu_homework/${escHtml(hw.sub_image)}" onclick="openImageViewer(this.src)"></div>`:''}
    </div>`;
    if ((status==='checked'||status==='returned') && hw.teacher_remarks) {
      h += `<div class="hw-feedback"><div class="hw-feedback-lbl"><i class="bi bi-stars"></i> Teacher Feedback</div>
        <div class="hw-feedback-txt">${escHtml(hw.teacher_remarks)}</div>
        ${hw.marks_obtained!=null?`<div class="hw-marks">Marks: ${escHtml(String(hw.marks_obtained))}</div>`:''}
      </div>`;
    } else if (status==='submitted') {
      h += `<div class="hw-awaiting"><i class="bi bi-clock-history"></i> Submitted · Awaiting review</div>`;
    } else if (status==='returned') {
      h += `<button class="hw-submit-btn" style="background:#7c3aed;" onclick="openSubmitHw(${+hw.id},${JSON.stringify(hw.title||'')})">
        <i class="bi bi-arrow-repeat"></i> Resubmit Your Answer
      </button>`;
    }
  } else {
    h += `<button class="hw-submit-btn" onclick="openSubmitHw(${+hw.id},${JSON.stringify(hw.title||'')})">
      <i class="bi bi-send-fill"></i> Submit Your Answer
    </button>`;
  }
  return h + '</div>';
}

async function refreshHomework() {
  if (_hwRefreshing) return;
  _hwRefreshing = true;
  try {
    const r = await fetch(STU_BASE + '/student/api.php?action=get_homework');
    const d = await r.json();
    if (!d.success) { _hwRefreshing=false; return; }
    const hw = d.data || [];
    const pending = hw.filter(h => !h.sub_id).length;
    // Badge — every tab has two button copies (mobile + desktop), update both
    document.querySelectorAll('.tab-btn[data-tab="homework"]').forEach(tabBtn => {
      let cnt = tabBtn.querySelector('.tab-cnt');
      if (pending > 0) {
        if (!cnt) { cnt=document.createElement('span'); cnt.className='tab-cnt orange'; tabBtn.appendChild(cnt); }
        cnt.textContent=pending; cnt.style.display='';
      } else if (cnt) cnt.style.display='none';
    });
    // Header meta
    const meta = document.getElementById('hwHdrMeta');
    if (meta) meta.innerHTML = `${hw.length} total${pending>0?` · <span style="color:var(--orange);font-weight:700;">${pending} pending</span>`:' · All done!'}`;
    // Content
    const wrap = document.getElementById('hwDynArea');
    if (!wrap) { _hwRefreshing=false; return; }
    if (hw.length === 0) {
      wrap.innerHTML = '<div class="empty-card"><div class="empty-emoji">📚</div><div class="empty-title">No Homework Yet</div><div class="empty-sub">Your teacher hasn\'t assigned any homework yet</div></div>';
    } else {
      const flt = `<div class="hw-filter">
        <button class="hw-filter-btn active" onclick="filterHw(\'all\',this)">All (${hw.length})</button>
        <button class="hw-filter-btn" onclick="filterHw(\'pending\',this)">Pending (${pending})</button>
        <button class="hw-filter-btn" onclick="filterHw(\'submitted\',this)">Submitted</button>
        <button class="hw-filter-btn" onclick="filterHw(\'checked\',this)">Checked</button>
      </div>`;
      wrap.innerHTML = flt + hw.map(h => buildHwCard(h)).join('');
    }
  } catch(e) {}
  _hwRefreshing = false;
}

setInterval(() => {
  if (document.getElementById('panel_homework')?.classList.contains('active')) refreshHomework();
}, 30000);

// ── Complaints: JS renderer + live poll ───────────────────────────────────────
function buildCmpCard(c) {
  const slbl = {open:'Open',in_progress:'In Progress',resolved:'Resolved',closed:'Closed'};
  const rc   = c.reply_count || 0;
  const atts = Array.isArray(c.attachments) ? c.attachments : [];
  let dt = '';
  try { dt = new Date(c.created_at).toLocaleDateString('en-IN',{day:'numeric',month:'short'}); } catch(e){}
  return `<div class="cmp-card" onclick="openComplaintThread(${+c.id})">
    <div class="cmp-card-top">
      <div class="cmp-emoji">📢</div>
      <div class="cmp-info">
        <div class="cmp-subject">${escHtml(c.subject||'')}</div>
        <div class="cmp-preview">${escHtml((c.description||'').substring(0,100))}</div>
      </div>
      <i class="bi bi-chevron-right cmp-arrow"></i>
    </div>
    <div class="cmp-foot">
      <span class="cmp-s ${c.status}">${slbl[c.status]||c.status}</span>
      ${rc>0?`<span class="cmp-att"><i class="bi bi-chat-dots"></i> ${rc}</span>`:''}
      ${atts.length>0?`<span class="cmp-att"><i class="bi bi-paperclip"></i> ${atts.length}</span>`:''}
      <span class="cmp-date">${dt}</span>
    </div>
  </div>`;
}

async function refreshComplaints() {
  const wrap = document.getElementById('cmpListWrap');
  if (!wrap) return;
  try {
    const r = await fetch(STU_BASE + '/student/api.php?action=get_complaints');
    const d = await r.json();
    if (!d.success) return;
    const cmps = d.data || [];
    const open = cmps.filter(c => ['open','in_progress'].includes(c.status)).length;
    // Badge — every tab has two button copies (mobile + desktop), update both
    document.querySelectorAll('.tab-btn[data-tab="nalish"]').forEach(tabBtn => {
      let cnt = tabBtn.querySelector('.tab-cnt');
      if (open>0) {
        if (!cnt) { cnt=document.createElement('span'); cnt.className='tab-cnt'; tabBtn.appendChild(cnt); }
        cnt.textContent=open; cnt.style.display='';
      } else if (cnt) cnt.style.display='none';
    });
    if (open > 0) { const md = document.getElementById('moreDot'); if (md) md.style.display = ''; }
    // Header meta
    const meta = document.getElementById('cmpHdrMeta');
    if (meta) meta.innerHTML = `${cmps.length} total${open>0?` · <span style="color:var(--red);font-weight:700;">${open} open</span>`:''}`;
    // Content
    if (cmps.length===0) {
      wrap.innerHTML = '<div class="card"><div class="empty-state"><i class="bi bi-megaphone" style="font-size:2.5rem;color:var(--muted);"></i><p>No complaints filed yet.<br><span style="font-size:.78rem;">Tap the button above to file a complaint.</span></p></div></div>';
    } else {
      wrap.innerHTML = cmps.map(c => buildCmpCard(c)).join('');
    }
  } catch(e) {}
}

// Auto-refresh thread modal every 10s when open
setInterval(() => {
  if (currentCmpId && document.getElementById('threadModal')?.classList.contains('show')) {
    openComplaintThread(currentCmpId);
  }
}, 10000);

setInterval(() => {
  if (document.getElementById('panel_nalish')?.classList.contains('active')) refreshComplaints();
}, 30000);

// ── Tab Restore (must run last, after all functions are defined) ───────────────
(function() {
  const hash = (location.hash || '').slice(1);
  let saved = '';
  try { saved = hash || localStorage.getItem('stu_tab') || ''; } catch(e) { saved = hash; }
  if (saved && saved !== 'home') {
    const btn = document.querySelector('.tab-btn[data-tab="' + saved + '"]');
    if (btn) btn.click();
  }
})();
</script>

<?php if ($payEnabled): ?>
<!-- ── Online Payment Modal ────────────────────────────────────────── -->
<div id="payOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:18px;width:min(92vw,420px);max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.22);">

    <!-- Header -->
    <div style="padding:1.1rem 1.3rem;border-bottom:1.5px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
      <div>
        <div style="font-size:.95rem;font-weight:800;color:#111827;">Pay School Fees Online</div>
        <div style="font-size:.74rem;color:#64748b;margin-top:.1rem;">Secure payment via Razorpay</div>
      </div>
      <button onclick="closePayOverlay(false)" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#94a3b8;line-height:1;">&times;</button>
    </div>

    <div style="padding:1.2rem 1.3rem;">

      <!-- STATE: Amount entry -->
      <div id="payAmtState">
        <!-- Total due summary -->
        <div style="background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:10px;padding:.85rem 1rem;margin-bottom:1rem;">
          <div style="font-size:.7rem;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:.04em;">Total Pending Dues</div>
          <div style="font-size:1.5rem;font-weight:900;color:#0c4a6e;margin:.15rem 0;">₹<?= number_format($feeSummary['due'], 2) ?></div>
          <div style="font-size:.73rem;color:#0369a1;">You may pay the full amount or a partial amount below.</div>
        </div>

        <!-- Amount input -->
        <div style="margin-bottom:.3rem;">
          <label style="font-size:.72rem;font-weight:700;color:#374151;display:block;margin-bottom:.4rem;">Amount to Pay (₹)</label>
          <div style="display:flex;align-items:center;border:2px solid #2563eb;border-radius:10px;overflow:hidden;background:#fff;">
            <span style="padding:.6rem .8rem;font-size:1rem;font-weight:700;color:#2563eb;background:#eff6ff;">₹</span>
            <input type="number" id="payCustomAmt"
              min="1" max="<?= round($feeSummary['due'], 2) ?>" step="1"
              value="<?= round($feeSummary['due'], 2) ?>"
              oninput="validatePayAmt()"
              style="flex:1;border:none;outline:none;padding:.6rem .8rem;font-size:1.05rem;font-weight:800;color:#111827;width:100%;">
            <button onclick="setPayFull()" style="padding:.55rem .8rem;background:#eff6ff;border:none;border-left:1.5px solid #bfdbfe;cursor:pointer;font-size:.72rem;font-weight:700;color:#2563eb;white-space:nowrap;">Full ₹<?= number_format($feeSummary['due'], 2) ?></button>
          </div>
          <div id="payAmtErr" style="display:none;font-size:.75rem;color:#dc2626;margin-top:.35rem;padding:.25rem .5rem;background:#fef2f2;border-radius:6px;"></div>
        </div>

        <!-- Remaining preview -->
        <div id="payRemPreview" style="font-size:.75rem;color:#64748b;margin-bottom:1rem;min-height:1.2rem;padding:.3rem 0;"></div>

        <!-- Security note -->
        <div style="background:#f8fafc;border:1.5px solid #e5e7eb;border-radius:8px;padding:.7rem .9rem;margin-bottom:1rem;font-size:.75rem;color:#64748b;line-height:1.6;">
          <i class="bi bi-shield-check" style="color:#15803d;"></i>
          Processed securely by <b>Razorpay</b>. Supports UPI, Cards, Net Banking &amp; Wallets.
        </div>

        <button onclick="startRazorpayFlow()" id="payProceedBtn"
          style="width:100%;padding:.75rem;background:#2563eb;color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.5rem;">
          <i class="bi bi-credit-card-fill"></i> Proceed to Pay
        </button>
      </div>

      <!-- STATE: Loading -->
      <div id="payLoadingState" style="display:none;text-align:center;padding:2rem 0;color:#64748b;">
        <i class="bi bi-hourglass-split" style="font-size:1.8rem;"></i>
        <div style="margin-top:.5rem;font-size:.85rem;" id="payLoadingMsg">Creating payment order…</div>
      </div>

      <!-- STATE: Success -->
      <div id="paySuccessState" style="display:none;text-align:center;padding:1rem 0;">
        <div style="font-size:2.8rem;margin-bottom:.4rem;">✅</div>
        <div style="font-size:1rem;font-weight:800;color:#15803d;margin-bottom:.3rem;">Payment Successful!</div>
        <div id="paySuccessMsg"  style="font-size:.85rem;color:#374151;margin-bottom:.2rem;font-weight:700;"></div>
        <div id="paySuccessRem"  style="font-size:.78rem;color:#64748b;margin-bottom:.8rem;"></div>
        <div id="payReceiptNo"   style="font-size:.78rem;color:#64748b;margin-bottom:.8rem;"></div>
        <a id="payReceiptLink" href="#" target="_blank"
          style="display:inline-block;margin-bottom:.6rem;background:#f0fdf4;color:#15803d;border:1.5px solid #bbf7d0;border-radius:8px;padding:.4rem 1.1rem;font-size:.8rem;font-weight:700;text-decoration:none;">
          <i class="bi bi-receipt"></i> View Receipt
        </a><br>
        <button onclick="closePayOverlay(true)"
          style="margin-top:.5rem;padding:.45rem 1.3rem;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:.82rem;font-weight:700;">
          Close &amp; Refresh
        </button>
      </div>

      <!-- STATE: Error -->
      <div id="payErrorState" style="display:none;">
        <div style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:10px;padding:.9rem 1rem;font-size:.82rem;color:#b91c1c;margin-bottom:.8rem;">
          <i class="bi bi-x-circle-fill"></i> <span id="payErrorMsg"></span>
        </div>
        <button onclick="showPayState('amount')"
          style="width:100%;padding:.6rem;background:#f1f5f9;color:#374151;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;font-size:.82rem;font-weight:700;">
          <i class="bi bi-arrow-left"></i> Try Again
        </button>
      </div>

    </div>
  </div>
</div>

<script>
const STU_PAY_API  = '<?= BASE_URL ?>/api/student_payment_actions.php';
const _maxDue      = <?= round($feeSummary['due'], 2) ?>;
let   _rzpOrderId  = '';
let   _rzpKeyId    = '<?= htmlspecialchars($payKeyId, ENT_QUOTES) ?>';
let   _paymentJustSucceeded = false; // tracks whether THIS overlay session actually completed a payment

function showPayState(state) {
  ['payAmtState','payLoadingState','paySuccessState','payErrorState']
    .forEach(id => { const el = document.getElementById(id); if (el) el.style.display = 'none'; });
  const ids = { amount:'payAmtState', loading:'payLoadingState', success:'paySuccessState', error:'payErrorState' };
  const el = document.getElementById(ids[state]);
  if (el) el.style.display = '';
}

function startOnlinePayment() {
  // Reset amount field to max due each time modal opens
  const inp = document.getElementById('payCustomAmt');
  if (inp) { inp.value = _maxDue; }
  document.getElementById('payAmtErr').style.display = 'none';
  _paymentJustSucceeded = false;
  updateRemPreview();
  document.getElementById('payOverlay').style.display = 'flex';
  showPayState('amount');
}

function closePayOverlay(reload) {
  document.getElementById('payOverlay').style.display = 'none';
  // A completed payment must refresh the page no matter which close path
  // the student uses (× button, tap outside, or the dedicated button) —
  // otherwise their pending-dues figure elsewhere on the page stays stale.
  if (reload || _paymentJustSucceeded) location.reload();
}

function setPayFull() {
  document.getElementById('payCustomAmt').value = _maxDue;
  document.getElementById('payAmtErr').style.display = 'none';
  updateRemPreview();
}

function validatePayAmt() {
  document.getElementById('payAmtErr').style.display = 'none';
  updateRemPreview();
}

function updateRemPreview() {
  const inp = document.getElementById('payCustomAmt');
  const pr  = document.getElementById('payRemPreview');
  const amt = parseFloat(inp.value);
  if (!isNaN(amt) && amt > 0 && amt <= _maxDue) {
    const rem = Math.round((_maxDue - amt) * 100) / 100;
    if (rem > 0) {
      pr.innerHTML = '<i class="bi bi-info-circle" style="color:#f59e0b;"></i> After this payment, ₹' +
        rem.toLocaleString('en-IN', {minimumFractionDigits:2}) + ' will remain as due.';
      pr.style.color = '#92400e';
    } else {
      pr.innerHTML = '<i class="bi bi-check-circle" style="color:#15803d;"></i> This will clear all pending dues.';
      pr.style.color = '#15803d';
    }
  } else {
    pr.innerHTML = '';
  }
}

async function startRazorpayFlow() {
  // ── Validate amount ───────────────────────────────────────────────
  const inp = document.getElementById('payCustomAmt');
  const errEl = document.getElementById('payAmtErr');
  const amt   = parseFloat(inp.value);

  if (isNaN(amt) || amt <= 0) {
    errEl.textContent = 'Please enter a valid amount.';
    errEl.style.display = 'block'; return;
  }
  if (amt < 1) {
    errEl.textContent = 'Minimum payment amount is ₹1.';
    errEl.style.display = 'block'; return;
  }
  if (amt > _maxDue + 0.01) {
    errEl.textContent = 'Amount cannot exceed total dues of ₹' + _maxDue.toLocaleString('en-IN', {minimumFractionDigits:2}) + '.';
    errEl.style.display = 'block'; return;
  }
  errEl.style.display = 'none';

  // ── Create Razorpay order ─────────────────────────────────────────
  document.getElementById('payLoadingMsg').textContent = 'Creating payment order…';
  showPayState('loading');

  try {
    const fd = new FormData();
    fd.append('action',        'create_order');
    fd.append('custom_amount', amt.toFixed(2));
    const r = await fetch(STU_PAY_API, { method:'POST', body:fd, credentials:'same-origin' });
    const d = await r.json();
    if (!d.ok) {
      showPayState('error');
      document.getElementById('payErrorMsg').textContent = d.msg;
      return;
    }
    _rzpOrderId = d.order_id;
    _rzpKeyId   = d.key_id;

    // Load Razorpay JS then open checkout
    if (!window.Razorpay) {
      const s = document.createElement('script');
      s.src     = 'https://checkout.razorpay.com/v1/checkout.js';
      s.onload  = () => _openRzpCheckout(d.amount);
      s.onerror = () => {
        showPayState('error');
        document.getElementById('payErrorMsg').textContent =
          'Could not load payment gateway. Check internet connection and try again.';
      };
      document.head.appendChild(s);
    } else {
      _openRzpCheckout(d.amount);
    }
  } catch (e) {
    showPayState('error');
    document.getElementById('payErrorMsg').textContent = 'Network error. Please try again.';
  }
}

function _openRzpCheckout(amtPaise) {
  const options = {
    key:         _rzpKeyId,
    order_id:    _rzpOrderId,
    amount:      amtPaise,
    currency:    'INR',
    name:        '<?= htmlspecialchars($stu['school_name'] ?? 'School', ENT_QUOTES) ?>',
    description: 'Fee Payment — <?= htmlspecialchars($stu['name'] ?? '', ENT_QUOTES) ?>',
    prefill: {
      name:    '<?= htmlspecialchars($stu['name'] ?? '', ENT_QUOTES) ?>',
      email:   '<?= htmlspecialchars($stu['email'] ?? '', ENT_QUOTES) ?>',
      contact: '<?= htmlspecialchars($stu['guardian_phone'] ?? '', ENT_QUOTES) ?>',
    },
    theme: { color: '#2563eb' },
    handler: async function(response) {
      document.getElementById('payLoadingMsg').textContent = 'Verifying payment…';
      showPayState('loading');
      try {
        const fd = new FormData();
        fd.append('action',                'verify_payment');
        fd.append('razorpay_order_id',     response.razorpay_order_id);
        fd.append('razorpay_payment_id',   response.razorpay_payment_id);
        fd.append('razorpay_signature',    response.razorpay_signature);
        const r = await fetch(STU_PAY_API, { method:'POST', body:fd, credentials:'same-origin' });
        const d = await r.json();
        if (d.ok) {
          const paidAmt = parseFloat(d.amount);
          const remAmt  = Math.round((_maxDue - paidAmt) * 100) / 100;
          document.getElementById('paySuccessMsg').textContent =
            '₹' + paidAmt.toLocaleString('en-IN', {minimumFractionDigits:2}) + ' paid successfully.';
          document.getElementById('paySuccessRem').textContent =
            remAmt > 0
              ? '₹' + remAmt.toLocaleString('en-IN', {minimumFractionDigits:2}) + ' remaining due.'
              : 'All dues cleared!';
          document.getElementById('payReceiptNo').textContent = 'Receipt No: ' + d.receipt_no;
          const rl = document.getElementById('payReceiptLink');
          if (d.receipt_url) { rl.href = d.receipt_url; rl.style.display = 'inline-block'; }
          else { rl.style.display = 'none'; }
          _paymentJustSucceeded = true; // any close path now must refresh the page
          showPayState('success');
        } else {
          showPayState('error');
          document.getElementById('payErrorMsg').textContent =
            d.msg || 'Verification failed. Contact school office.';
        }
      } catch (e) {
        showPayState('error');
        document.getElementById('payErrorMsg').textContent =
          'Network error during verification. Please contact the school with this reference: ' +
          response.razorpay_payment_id;
      }
    },
    modal: {
      ondismiss: function() {
        // Guardian closed checkout — back to amount entry
        showPayState('amount');
      }
    }
  };
  const rzp = new window.Razorpay(options);
  rzp.open();
}

// Close overlay when clicking outside the card
document.getElementById('payOverlay').addEventListener('click', function(e) {
  if (e.target === this) closePayOverlay(false);
});

// Initialize remaining preview on load
document.addEventListener('DOMContentLoaded', updateRemPreview);
</script>
<?php endif; ?>

</body>
</html>