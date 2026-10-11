<?php
// api/attendance_actions.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../includes/subscription_gate.php';

if (empty($_SESSION['logged_in']) || !in_array($_SESSION['role'], [ROLE_SCHOOL_ADMIN, ROLE_TEACHER])) {
    // 401 lets the offline write-queue (assets/js/offline-queue.js) tell
    // "please re-authenticate" apart from an ordinary rejected write.
    jsonResponse(false, 'Your session has expired. Please log in again.', [], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

$csrf = $_POST['csrf_token'] ?? '';
$schoolId = $_SESSION['school_id'];
$markedBy = $_SESSION['user_id'];

function _slog(string $activity, string $type = 'other'): void {
    global $schoolId;
    logSchoolActivity((int)$schoolId, (int)($_SESSION['user_id']??0),
        $_SESSION['name']??'', $_SESSION['role']??'', $activity, $type);
}

if (!verifyCsrf($csrf)) {
    jsonResponse(false, 'Session expired. Please reload the page.', [], 401);
}

$action = $_POST['action'] ?? '';

// ── ACTION: edit_single ───────────────────────────────────────────────────────
if ($action === 'edit_single') {
    if (!hasPermission('attendance', 'edit')) jsonResponse(false, 'Permission denied.');
    $pdo       = Database::connect();
    requireActiveSubOrReject($pdo, 'school', (int)$schoolId);
    $attId     = (int)($_POST['attendance_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $validStatuses = ['present', 'absent', 'late', 'half_day', 'leave'];

    if ($attId <= 0) jsonResponse(false, 'Invalid record.');
    if (!in_array($newStatus, $validStatuses, true)) jsonResponse(false, 'Invalid status.');

    $verify = $pdo->prepare("SELECT id FROM attendance WHERE id = ? AND school_id = ?");
    $verify->execute([$attId, $schoolId]);
    if (!$verify->fetch()) jsonResponse(false, 'Record not found.');

    $pdo->prepare("UPDATE attendance SET status = ?, marked_by = ? WHERE id = ?")
        ->execute([$newStatus, $markedBy, $attId]);

    _slog("Student attendance updated: record #$attId → $newStatus", 'update');
    jsonResponse(true, 'Attendance updated.');
}

// ── Bulk mark attendance ──────────────────────────────────────────────────────
if (!hasPermission('attendance', 'add')) jsonResponse(false, 'Permission denied.');
requireActiveSubOrReject(Database::connect(), 'school', (int)$schoolId);
$date = $_POST['date'] ?? '';
$classId = $_POST['class_id'] ?? null;
$sectionId = $_POST['section_id'] ?? null;

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonResponse(false, 'Invalid date.');
}

// Collect att_<student_id> fields
$entries = [];
foreach ($_POST as $key => $value) {
    if (str_starts_with($key, 'att_')) {
        $studentId = (int) substr($key, 4);
        $validStatuses = ['present', 'absent', 'late', 'half_day', 'leave'];
        if ($studentId > 0 && in_array($value, $validStatuses)) {
            $entries[$studentId] = $value;
        }
    }
}

if (empty($entries)) {
    jsonResponse(false, 'No attendance data received.');
}

$pdo = Database::connect();

try {
    $pdo->beginTransaction();

    // Verify all students belong to this school (prevent cross-school writes)
    $studentIds = array_keys($entries);
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $verifyStmt = $pdo->prepare("SELECT id FROM students WHERE id IN ($placeholders) AND school_id = ?");
    $verifyStmt->execute([...$studentIds, $schoolId]);
    $validIds = array_column($verifyStmt->fetchAll(), 'id');

    $stmt = $pdo->prepare("
        INSERT INTO attendance (school_id, student_id, class_id, section_id, date, status, marked_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)
    ");

    $savedCount = 0;
    foreach ($entries as $studentId => $status) {
        if (!in_array($studentId, $validIds)) continue; // skip students not in this school
        $stmt->execute([$schoolId, $studentId, $classId ?: null, $sectionId ?: null, $date, $status, $markedBy]);
        $savedCount++;
    }

    $pdo->commit();
    _slog("Student attendance marked for $savedCount students on $date", 'create');

    // Check 7-day consecutive absence after saving (async-style: runs after response)
    $absentStudentIds = array_keys(array_filter($entries, fn($s) => $s === 'absent'));
    if (!empty($absentStudentIds)) {
        _checkConsecutiveAbsence($pdo, (int)$schoolId, $absentStudentIds, $date);
    }

    jsonResponse(true, "Attendance saved for {$savedCount} students.");
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('attendance save failed: ' . $e->getMessage());
    jsonResponse(false, 'A database error occurred. Please try again.');
}

// ── 6-day consecutive absence check (students) ───────────────────────────────
function _checkConsecutiveAbsence(PDO $pdo, int $schoolId, array $studentIds, string $todayDate): void
{
    try {
        require_once __DIR__ . '/../includes/notifier.php';

        // Load school holidays for a 30-day window (enough to find 6 school days back)
        $windowStart = date('Y-m-d', strtotime($todayDate . ' -30 days'));
        $holStmt = $pdo->prepare("SELECT date FROM school_holidays
                                  WHERE school_id=? AND date BETWEEN ? AND ?");
        $holStmt->execute([$schoolId, $windowStart, $todayDate]);
        $holidaySet = array_flip(array_column($holStmt->fetchAll(PDO::FETCH_ASSOC), 'date'));

        // Build list of last 6 school days (exclude Sundays + holidays)
        $schoolDays = [];
        $cursor = new DateTime($todayDate);
        while (count($schoolDays) < 6) {
            $ds  = $cursor->format('Y-m-d');
            $dow = (int)$cursor->format('N'); // 7=Sunday
            if ($dow !== 7 && !isset($holidaySet[$ds])) {
                array_unshift($schoolDays, $ds);
            }
            $cursor->modify('-1 day');
            if ((new DateTime($todayDate))->diff($cursor)->days > 60) break;
        }

        if (count($schoolDays) < 6) return;

        $fromDate = $schoolDays[0];
        $toDate   = $schoolDays[5];

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $attStmt = $pdo->prepare("
            SELECT student_id, status FROM attendance
            WHERE school_id=? AND student_id IN ($placeholders)
              AND date IN (" . implode(',', array_fill(0, 6, '?')) . ")
        ");
        $attStmt->execute(array_merge([$schoolId], $studentIds, $schoolDays));
        $attMap = [];
        foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $attMap[$row['student_id']][$row['status']] = ($attMap[$row['student_id']][$row['status']] ?? 0) + 1;
        }

        $sn = $pdo->prepare("SELECT school_name FROM schools WHERE id=?");
        $sn->execute([$schoolId]);
        $schoolName = $sn->fetchColumn() ?: 'School';

        foreach ($studentIds as $sid) {
            $counts      = $attMap[$sid] ?? [];
            $absentCount = $counts['absent'] ?? 0;
            $nonAbsent   = array_sum($counts) - $absentCount;
            if ($absentCount < 6 || $nonAbsent > 0) continue;

            // Spam guard: don't notify if already sent in last 7 days
            $already = $pdo->prepare("
                SELECT id FROM school_notification_log
                WHERE school_id=? AND event_type='student_absent_6days'
                  AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND (
                    recipient IN (SELECT guardian_phone FROM students WHERE id=?)
                    OR recipient IN (SELECT email FROM students WHERE id=?)
                  )
                LIMIT 1
            ");
            $already->execute([$schoolId, $sid, $sid]);
            if ($already->fetch()) continue;

            $stuStmt = $pdo->prepare("
                SELECT s.name, s.guardian_phone, s.email,
                       CONCAT(c.class_name, COALESCE(CONCAT(' ', sec.section_name),'')) AS class_label
                FROM students s
                LEFT JOIN classes c   ON c.id = s.class_id
                LEFT JOIN sections sec ON sec.id = s.section_id
                WHERE s.id=? AND s.school_id=? AND s.status='active'
            ");
            $stuStmt->execute([$sid, $schoolId]);
            $stu = $stuStmt->fetch(PDO::FETCH_ASSOC);
            if (!$stu) continue;

            sendSchoolNotification($schoolId, 'student_absent_6days', [
                'student_id'   => $sid,
                'student_name' => $stu['name'],
                'class'        => $stu['class_label'] ?? '',
                'days'         => 6,
                'from_date'    => date('d/m/Y', strtotime($fromDate)),
                'to_date'      => date('d/m/Y', strtotime($toDate)),
                'phone'        => $stu['guardian_phone'] ?? '',
                'email'        => $stu['email'] ?? '',
                'school_name'  => $schoolName,
            ]);
        }
    } catch (\Throwable $e) {
        error_log('6-day student absence check error: ' . $e->getMessage());
    }
}

