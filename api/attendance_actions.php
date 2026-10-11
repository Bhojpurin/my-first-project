<?php
// api/attendance_actions.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../includes/subscription_gate.php';
require_once __DIR__ . '/../includes/attendance_core.php';

if (empty($_SESSION['logged_in']) || !in_array($_SESSION['role'], [ROLE_SCHOOL_ADMIN, ROLE_TEACHER])) {
    // 401 lets the offline write-queue (assets/js/offline-queue.js) tell
    // "please re-authenticate" apart from an ordinary rejected write.
    jsonResponse(false, 'Your session has expired. Please log in again.', [], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

$csrf     = $_POST['csrf_token'] ?? '';
$schoolId = (int)$_SESSION['school_id'];
$markedBy = (int)$_SESSION['user_id'];
$isAdmin  = (($_SESSION['role'] ?? '') === ROLE_SCHOOL_ADMIN);

function _slog(string $activity, string $type = 'other'): void {
    global $schoolId;
    logSchoolActivity((int)$schoolId, (int)($_SESSION['user_id']??0),
        $_SESSION['name']??'', $_SESSION['role']??'', $activity, $type);
}

function _validDate(string $d): bool {
    return (bool)preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

if (!verifyCsrf($csrf)) {
    jsonResponse(false, 'Session expired. Please reload the page.', [], 401);
}

$action = $_POST['action'] ?? '';

// ── ACTION: edit_single ───────────────────────────────────────────────────────
if ($action === 'edit_single') {
    if (!hasPermission('attendance', 'edit')) jsonResponse(false, 'Permission denied.');
    $pdo       = Database::connect();
    requireActiveSubOrReject($pdo, 'school', $schoolId);
    $attId     = (int)($_POST['attendance_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $reason    = trim((string)($_POST['reason'] ?? ''));

    if ($attId <= 0) jsonResponse(false, 'Invalid record.');
    if (!in_array($newStatus, AttCore::STATUSES, true)) jsonResponse(false, 'Invalid status.');

    $verify = $pdo->prepare("SELECT student_id, date, status FROM attendance WHERE id = ? AND school_id = ?");
    $verify->execute([$attId, $schoolId]);
    $row = $verify->fetch(PDO::FETCH_ASSOC);
    if (!$row) jsonResponse(false, 'Record not found.');

    if ($why = AttCore::editBlockedReason($pdo, $schoolId, $row['date'], $isAdmin)) jsonResponse(false, $why);

    try {
        $pdo->beginTransaction();
        $res = AttCore::write($pdo, $schoolId, 'student', (int)$row['student_id'], $row['date'], $newStatus,
                              'manual', $markedBy, ['reason' => $reason]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('attendance edit failed: ' . $e->getMessage());
        jsonResponse(false, 'A database error occurred. Please try again.');
    }

    if ($res['changed']) {
        _slog("Student attendance updated: record #$attId ({$row['date']}) " . ($res['prev'] ?? 'none') . " → $newStatus"
              . ($reason !== '' ? " — reason: $reason" : ''), 'update');
        if ($newStatus === 'absent') AttCore::checkConsecutiveAbsence($pdo, $schoolId, [(int)$row['student_id']], $row['date']);
    }
    jsonResponse(true, 'Attendance updated.');
}

// ── Bulk mark attendance ──────────────────────────────────────────────────────
if (!hasPermission('attendance', 'add')) jsonResponse(false, 'Permission denied.');
$pdo = Database::connect();
requireActiveSubOrReject($pdo, 'school', $schoolId);
$date         = (string)($_POST['date'] ?? '');
$overrideScan = (($_POST['override_scan'] ?? '') === '1');

if (!_validDate($date)) {
    jsonResponse(false, 'Invalid date.');
}
if ($why = AttCore::editBlockedReason($pdo, $schoolId, $date, $isAdmin)) jsonResponse(false, $why);

// Collect att_<student_id> fields
$entries = [];
foreach ($_POST as $key => $value) {
    if (str_starts_with($key, 'att_')) {
        $studentId = (int) substr($key, 4);
        if ($studentId > 0 && in_array($value, AttCore::STATUSES, true)) {
            $entries[$studentId] = $value;
        }
    }
}

if (empty($entries)) {
    jsonResponse(false, 'No attendance data received.');
}

$savedCount = 0; $unchanged = 0; $keptScan = []; $nowAbsent = [];
for ($try = 1; ; $try++) {
    try {
        $pdo->beginTransaction();

        // Only students of this school; class/section come from the student record, not from the form.
        $studentIds   = array_keys($entries);
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $verifyStmt   = $pdo->prepare("SELECT id, name, class_id, section_id FROM students WHERE id IN ($placeholders) AND school_id = ?");
        $verifyStmt->execute([...$studentIds, $schoolId]);
        $valid = [];
        foreach ($verifyStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $valid[(int)$r['id']] = $r;

        $savedCount = 0; $unchanged = 0; $keptScan = []; $nowAbsent = [];
        foreach ($entries as $studentId => $status) {
            if (!isset($valid[$studentId])) continue; // skip students not in this school
            $stu = $valid[$studentId];

            // A register form opened before the student scanned in still says "absent".
            // Do not let that stale value wipe out a gate scan; the teacher can change it on purpose
            // with a single edit, or by resending with override_scan=1.
            if ($status === 'absent' && !$overrideScan) {
                $cur = AttCore::current($pdo, $schoolId, 'student', $studentId, $date);
                if ($cur && $cur['source'] === 'scan' && $cur['status'] !== 'absent') { $keptScan[] = $stu['name']; continue; }
            }

            $res = AttCore::write($pdo, $schoolId, 'student', $studentId, $date, $status, 'manual', $markedBy,
                                  ['class_id' => $stu['class_id'], 'section_id' => $stu['section_id']]);
            if ($res['changed']) { $savedCount++; if ($status === 'absent') $nowAbsent[] = $studentId; }
            else $unchanged++;
        }

        $pdo->commit();
        break;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($try < 3 && AttCore::isRetryable($e)) { usleep(100000 * $try); continue; }
        error_log('attendance save failed: ' . $e->getMessage());
        jsonResponse(false, 'A database error occurred. Please try again.');
    }
}

_slog("Student attendance marked on $date: $savedCount changed, $unchanged unchanged"
      . ($keptScan ? ', ' . count($keptScan) . ' kept as scanned' : ''), 'create');

// 6-day consecutive absence check. Only queues messages; sending happens after the response.
if ($nowAbsent) AttCore::checkConsecutiveAbsence($pdo, $schoolId, $nowAbsent, $date);

$msg = "Attendance saved for " . ($savedCount + $unchanged) . " students.";
if ($keptScan) {
    $names = implode(', ', array_slice($keptScan, 0, 5)) . (count($keptScan) > 5 ? ' and ' . (count($keptScan) - 5) . ' more' : '');
    $msg .= ' ' . count($keptScan) . " already scanned in at the gate, so they were NOT marked absent ($names). Edit them one by one if they really are absent.";
}
jsonResponse(true, $msg, ['saved' => $savedCount, 'unchanged' => $unchanged, 'kept_scan' => count($keptScan)]);
