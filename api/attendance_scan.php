<?php
// api/attendance_scan.php — Gate Console v2 backend
// QR/Barcode/USB-scanner based auto attendance (students + staff).
//
// Writes into the SAME `attendance` / `staff_attendance` tables the manual
// screens use, so manual marking, reports and exports keep working unchanged.
// Every raw scan is also logged to attendance_scan_log for audit.
//
// Actions:
//   scan             mark check-in / check-out (idempotent via client_uid, offline-sync aware)
//   undo             revert the latest scan of a person (today only)
//   recent           today's scans from ALL gates (live feed)
//   summary          school-wide numbers for today + class-wise progress
//   not_arrived      students / staff with no attendance yet today
//   finalize_absent  mark everyone without attendance as absent (admin)
//   search           find a student / staff by name or code
//   get_settings     read attendance rules
//   save_settings    save attendance rules (admin)
//   card_payload     signed QR text for ID cards (admin)
//   revoke_card      lost card: old printed card stops working, returns the new QR text (admin)
//
// Schema: this file does NOT create or check tables at runtime (that made every request slower).
// Run attendance_scan_v2.sql and then sql/attendance_fixes_v3.sql ONCE in the database BEFORE uploading this file.
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../includes/subscription_gate.php';
require_once __DIR__ . '/../includes/attendance_features.php';
require_once __DIR__ . '/../includes/attendance_core.php';

const TIME_RE      = AttCore::TIME_RE;
const SCAN_SOURCES = ['camera', 'scanner', 'manual', 'image', 'search', 'sync'];

// Gate devices may only do these. Everything else needs a logged-in admin / teacher.
const DEVICE_ACTIONS = ['scan', 'recent', 'summary', 'not_arrived', 'search', 'get_settings'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

$pdo    = Database::connect();
$action = $_POST['action'] ?? '';
$csrf   = $_POST['csrf_token'] ?? '';
$device = null;   // set when the request is authenticated by a registered gate device instead of a login

$sessionOk = !empty($_SESSION['logged_in']) && in_array($_SESSION['role'] ?? '', [ROLE_SCHOOL_ADMIN, ROLE_TEACHER], true);
if ($sessionOk && verifyCsrf($csrf)) {
    $schoolId  = (int)$_SESSION['school_id'];
    $userId    = (int)$_SESSION['user_id'];
    $isAdmin   = (($_SESSION['role'] ?? '') === ROLE_SCHOOL_ADMIN);
    $actorName = (string)($_SESSION['name'] ?? '');
    $actorRole = (string)($_SESSION['role'] ?? '');
} elseif (($tok = (string)($_SERVER['HTTP_X_GATE_TOKEN'] ?? '')) !== '') {
    // Registered gate phone: keeps working when the teacher's session has expired.
    // A header token is not sent by the browser on its own, so CSRF does not apply here.
    $device = AttCore::deviceAuth($pdo, $tok);
    if (!$device) jsonResponse(false, 'This gate device was removed or is not registered. Ask the school admin to register it again.', ['device_revoked' => true], 401);
    if (!in_array($action, DEVICE_ACTIONS, true)) jsonResponse(false, 'Please log in to do this.', [], 403);
    $schoolId  = (int)$device['school_id'];
    $userId    = (int)$device['created_by'];
    $isAdmin   = false;
    $actorName = 'Gate device: ' . $device['name'];
    $actorRole = 'gate_device';
} elseif (empty($_SESSION['logged_in'])) {
    jsonResponse(false, 'Your session has expired. Please log in again.', [], 401);
} elseif (!$sessionOk) {
    jsonResponse(false, 'Permission denied.', [], 403);
} else {
    jsonResponse(false, 'Session expired. Please reload the page.', [], 401);
}

/** Permission check that also covers gate devices (they may only add attendance). */
function can(string $module, string $act): bool {
    global $device;
    if ($device) return $act === 'add' && in_array($module, ['attendance', 'staff_attendance'], true);
    return hasPermission($module, $act);
}

// Per-school feature switches (school choice AND plan allowance) + which column the ID card holds.
$FT     = attFeatures($pdo, $schoolId);
$F      = $FT['effective'];
$stuCol = attCodeColumn($pdo, 'students', $FT['student_code_field'], ATT_STUDENT_CODE_FIELDS, 'admission_no');
$stfCol = attCodeColumn($pdo, 'teachers', $FT['staff_code_field'], ATT_STAFF_CODE_FIELDS, 'employee_code');

/* ═══════════════════════════ Helpers ═══════════════════════════ */

function getSettings(PDO $pdo, int $schoolId): array {
    return AttCore::rules($pdo, $schoolId);
}

function dayWarning(PDO $pdo, int $schoolId, string $date): string {
    $r = AttCore::offDayReason($pdo, $schoolId, $date);
    return $r === '' ? '' : ($date === date('Y-m-d') ? 'Today: ' : date('d/m/Y', strtotime($date)) . ': ') . $r;
}

function cleanGate(string $g): string {
    return mb_substr(trim(preg_replace('/[^\p{L}\p{N} _\-\.]/u', '', $g)), 0, 60);
}

function dayBounds(string $day): array { return [$day . ' 00:00:00', $day . ' 23:59:59']; }

/** Signed, expiring photo link (api/attendance_photo.php), never the public uploads URL. */
function scanPhotoUrl(array $entity, string $entityType): string {
    global $pdo, $schoolId;
    if (empty($entity['photo']) || empty($entity['id'])) return '';
    return AttCore::photoUrl($pdo, (int)$schoolId, $entityType, (int)$entity['id'], (string)$entity['photo']);
}

function entityPayload(array $e, string $type, string $code): array {
    return [
        'name'      => $e['name'],
        'photo'     => scanPhotoUrl($e, $type),
        'type'      => $type,
        'person_id' => (int)$e['id'],
        'code'      => $code,
        'sub'       => $type === 'staff' ? ($e['post'] ?? '') : ($e['class_label'] ?? ''),
    ];
}

function logReject(PDO $pdo, int $schoolId, int $userId, string $raw, string $reason, string $gate): void {
    try {
        $pdo->prepare("INSERT INTO attendance_scan_reject (school_id, raw_payload, reason, gate_name, scanned_by, created_at)
                       VALUES (?,?,?,?,?,NOW())")
            ->execute([$schoolId, substr($raw, 0, 255), $reason, $gate !== '' ? $gate : null, $userId]);
    } catch (\Throwable $e) {}
}

function replayStored(PDO $pdo, int $schoolId, string $uid): void {
    $q = $pdo->prepare("SELECT result_json FROM attendance_scan_log WHERE school_id=? AND client_uid=? LIMIT 1");
    $q->execute([$schoolId, $uid]);
    $j = $q->fetchColumn();
    if ($j && is_array($d = json_decode($j, true))) {
        $msg = (string)($d['message'] ?? '');
        unset($d['message']);
        $d['replayed'] = true;
        jsonResponse(true, $msg, $d);
    }
}

function notifyGuardian(PDO $pdo, int $schoolId, int $studentId, string $scanType, string $status, string $timeLabel,
                        string $day, int $scanId): void {
    try {
        $st = $pdo->prepare("
            SELECT s.name, s.guardian_phone, s.email,
                   CONCAT(c.class_name, COALESCE(CONCAT(' ', sec.section_name), '')) AS class_label,
                   (SELECT school_name FROM schools WHERE id = s.school_id) AS school_name
            FROM students s
            LEFT JOIN classes c    ON c.id = s.class_id
            LEFT JOIN sections sec ON sec.id = s.section_id
            WHERE s.id=? AND s.school_id=?");
        $st->execute([$studentId, $schoolId]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        if (!$s || (empty($s['guardian_phone']) && empty($s['email']))) return;
        // Queued: the gate never waits for the SMS / WhatsApp gateway.
        AttCore::queueNotify($pdo, $schoolId, $scanType === 'in' ? 'student_checkin' : 'student_checkout', [
            'student_id'   => $studentId,
            'student_name' => $s['name'],
            'class'        => $s['class_label'] ?? '',
            'time'         => $timeLabel,
            'status'       => $status,
            'date'         => date('d/m/Y', strtotime($day)),
            'phone'        => $s['guardian_phone'] ?? '',
            'email'        => $s['email'] ?? '',
            'school_name'  => $s['school_name'] ?? 'School',
        ], "scan:$scanId", 1);
    } catch (\Throwable $e) {
        error_log('scan notify: ' . $e->getMessage());
    }
}

/* ═══════════════════════════ Smart voice (mini AI) ═══════════════════════════ */

/** School days (no weekly offs / holidays) before $day, newest first. */
function schoolDaysBack(PDO $pdo, int $schoolId, string $day, int $n = 30): array {
    return AttCore::schoolDaysBack($pdo, $schoolId, $day, $n, false);
}

/** Real data about this person that makes the voice message personal. */
function scanFacts(PDO $pdo, int $schoolId, string $type, int $id, string $day, string $scanType, string $statusToday): array {
    $f = ['absent_streak' => 0, 'present_streak' => 0, 'month_pct' => null, 'birthday' => false, 'gender' => ''];
    try {
        $isS   = ($type === 'student');
        $pt    = $isS ? 'students' : 'teachers';
        $table = $isS ? 'attendance' : 'staff_attendance';
        $col   = $isS ? 'student_id' : 'teacher_id';

        // Column names come from constants in includes/attendance_features.php ('' = feature off). No schema lookups at runtime.
        $dobCol = $isS ? ATT_STUDENT_DOB_COLUMN : ATT_STAFF_DOB_COLUMN;
        $genCol = $isS ? '' : ATT_STAFF_GENDER_COLUMN;
        $sel = [];
        if ($dobCol) $sel[] = "`$dobCol` AS dob";
        if ($genCol) $sel[] = "`$genCol` AS gender";
        if ($sel) {
            try {
                $q = $pdo->prepare("SELECT " . implode(',', $sel) . " FROM $pt WHERE id=? AND school_id=?");
                $q->execute([$id, $schoolId]);
                if ($r = $q->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($r['dob']) && strlen($r['dob']) >= 10 && substr($r['dob'], 5, 5) === substr($day, 5, 5)) $f['birthday'] = true;
                    if (!empty($r['gender'])) { $g = strtolower(substr(trim($r['gender']), 0, 1)); $f['gender'] = $g === 'm' ? 'm' : ($g === 'f' ? 'f' : ''); }
                }
            } catch (\Throwable $e) {
                // wrong dob/gender column name in the constants: skip birthday/title, keep everything else working
            }
        }

        if ($scanType === 'in') {
            $days = schoolDaysBack($pdo, $schoolId, $day, 30);
            if ($days) {
                $q = $pdo->prepare("SELECT date, status FROM $table WHERE school_id=? AND $col=? AND date BETWEEN ? AND ?");
                $q->execute([$schoolId, $id, end($days), $days[0]]);
                $map = $q->fetchAll(PDO::FETCH_KEY_PAIR);
                $a = 0;
                foreach ($days as $d) { if (($map[$d] ?? '') === 'absent') $a++; else break; }
                $f['absent_streak'] = $a;
                if ($a === 0) {
                    $p = 0;
                    foreach ($days as $d) { if (in_array($map[$d] ?? '', ['present', 'late', 'half_day'], true)) $p++; else break; }
                    $f['present_streak'] = $p + 1;   // + today
                }
            }
            $ms = substr($day, 0, 8) . '01';
            if ($ms < $day) {
                $q = $pdo->prepare("SELECT status, COUNT(*) FROM $table WHERE school_id=? AND $col=? AND date >= ? AND date < ? GROUP BY status");
                $q->execute([$schoolId, $id, $ms, $day]);
                $by = $q->fetchAll(PDO::FETCH_KEY_PAIR);
                $marked = array_sum(array_map('intval', $by)) + 1;
                $here   = (int)($by['present'] ?? 0) + (int)($by['late'] ?? 0) + (int)($by['half_day'] ?? 0)
                        + (in_array($statusToday, ['present', 'late'], true) ? 1 : 0);
                if ($marked >= 8) $f['month_pct'] = (int)round($here * 100 / $marked);
            }
        }
    } catch (\Throwable $e) {
        error_log('scan facts: ' . $e->getMessage());
    }
    return $f;
}

function hiNum(int $n): string {
    static $w = [1 => 'एक', 2 => 'दो', 3 => 'तीन', 4 => 'चार', 5 => 'पाँच', 6 => 'छह', 7 => 'सात', 8 => 'आठ', 9 => 'नौ', 10 => 'दस'];
    return $w[$n] ?? (string)$n;
}
function pickOne(array $a): string { return $a[array_rand($a)]; }

function speechName(string $name, string $type, string $gender, string $lang): string {
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($type !== 'staff') return $name;
    if (preg_match('/^(mr|mrs|ms|miss|dr|shri|smt|sri|sir|madam)\b\.?/i', $name)) return $name;
    if ($lang === 'hi') return $gender === 'm' ? "$name सर" : ($gender === 'f' ? "$name मैडम" : "$name जी");
    return $gender === 'm' ? "Mr. $name" : ($gender === 'f' ? "Ms. $name" : $name);
}

/** Builds the sentence the page speaks, in Hindi and English, from real attendance data. */
function buildSpeech(array $c): array {
    $type = $c['type']; $ev = $c['event']; $st = $c['status'] ?? ''; $f = $c['facts'] ?? [];
    $hm = $c['hm']; $staff = ($type === 'staff');
    $gender = $f['gender'] ?? '';
    $nh = speechName($c['name'], $type, $gender, 'hi');
    $ne = speechName($c['name'], $type, $gender, 'en');
    $gh = $hm < '12:00' ? 'सुप्रभात' : ($hm < '17:00' ? 'नमस्कार' : 'शुभ संध्या');
    $ge = $hm < '12:00' ? 'Good morning' : ($hm < '17:00' ? 'Good afternoon' : 'Good evening');
    $abs = (int)($f['absent_streak'] ?? 0);
    $ps  = (int)($f['present_streak'] ?? 0);
    $pct = $f['month_pct'] ?? null;
    $bd  = !empty($f['birthday']);
    $hi = ''; $en = '';

    if ($ev === 'duplicate') {
        $hi = "$nh, आपकी उपस्थिति अभी-अभी दर्ज हो चुकी है।";
        $en = "$ne, your attendance was just recorded.";
    } elseif ($ev === 'complete') {
        if (!empty($c['in_only'])) {
            $hi = "$nh, आपकी उपस्थिति आज पहले ही दर्ज हो चुकी है।";
            $en = "$ne, your attendance is already marked today.";
        } else {
            $hi = "$nh, आज आपका चेक-इन और चेक-आउट दोनों हो चुके हैं।";
            $en = "$ne, both your check-in and check-out are already done today.";
        }
    } elseif ($ev === 'in') {
        if ($staff) {
            if ($bd) {
                $hi = "जन्मदिन की हार्दिक शुभकामनाएँ $nh! आपका अटेंडेंस रिकॉर्ड हो गया है। आपका दिन शानदार रहे।";
                $en = "Happy birthday, $ne! Your attendance is recorded. Have a wonderful day.";
            } elseif ($abs >= 2) {
                $k = hiNum($abs);
                $hi = "वापसी पर स्वागत है $nh। आप $k दिन अनुपस्थित रहे, आशा है सब कुशल है। आपका अटेंडेंस रिकॉर्ड हो गया है।";
                $en = "Welcome back, $ne. You were away for $abs days, hope everything is fine. Your attendance is recorded.";
            } elseif ($abs === 1) {
                $hi = "स्वागत है $nh। आपका अटेंडेंस रिकॉर्ड हो गया है। आपका दिन उत्पादक रहे।";
                $en = "Welcome, $ne. Your attendance is recorded. Have a productive day.";
            } elseif ($st === 'late') {
                $hi = "$nh, आपका अटेंडेंस रिकॉर्ड हो गया है। कृपया कल समय पर पहुँचने का प्रयास कीजिए।";
                $en = "$ne, your attendance is recorded. Please try to arrive on time tomorrow.";
            } elseif ($ps >= 5 && $ps % 5 === 0) {
                $hi = "$nh, आपका अटेंडेंस रिकॉर्ड हो गया है। लगातार $ps दिन की उपस्थिति के लिए धन्यवाद।";
                $en = "$ne, your attendance is recorded. Thank you for $ps days of perfect presence in a row.";
            } else {
                $hi = pickOne([
                    "$gh $nh, आपका अटेंडेंस रिकॉर्ड हो गया है। आपका दिन उत्पादक रहे।",
                    "$nh, आपका अटेंडेंस रिकॉर्ड हो गया है। आपका दिन शानदार रहे।",
                    "$gh $nh, स्वागत है। आपका अटेंडेंस दर्ज हो गया है। आपका दिन शुभ और उत्पादक रहे।",
                ]);
                $en = pickOne([
                    "$ge, $ne. Your attendance is recorded. Have a productive day.",
                    "$ne, your attendance is recorded. Wishing you a great day.",
                    "$ge, $ne. Welcome. Have a wonderful and productive day.",
                ]);
            }
        } else {
            if ($bd) {
                $hi = "जन्मदिन की हार्दिक शुभकामनाएँ $nh! आपकी उपस्थिति दर्ज हो गई है। आपका दिन खुशियों से भरा रहे।";
                $en = "Happy birthday, $ne! Your attendance is marked. Have a wonderful day.";
            } elseif ($abs === 1) {
                $hi = "$nh, आप कल स्कूल से अनुपस्थित रहे। आज आपकी उपस्थिति दर्ज हो गई है। मन लगाकर पढ़ाई कीजिए।";
                $en = "$ne, you were absent yesterday. Today's attendance is marked. Study well.";
            } elseif ($abs >= 2) {
                $k = hiNum($abs);
                $tailH = $abs >= 6 ? 'कृपया अपने अभिभावक के साथ क्लास टीचर से मिलें।'
                       : ($abs >= 3 ? 'आगे से नियमित स्कूल आइए, पढ़ाई का नुकसान न होने दें।' : 'अच्छे से पढ़ाई कीजिए।');
                $tailE = $abs >= 6 ? 'Please meet your class teacher with a guardian.'
                       : ($abs >= 3 ? "Please attend regularly so you don't fall behind." : 'Study well.');
                $hi = "$nh, आप पिछले $k दिन स्कूल से अनुपस्थित रहे। आज आपकी उपस्थिति दर्ज हो गई है। $tailH";
                $en = "$ne, you were absent for $abs days. Today's attendance is marked. $tailE";
            } elseif ($st === 'late') {
                $hi = "$nh, आपकी उपस्थिति दर्ज हो गई है, पर आज आपको देर हो गई। कल समय पर आने की कोशिश कीजिए।";
                $en = "$ne, attendance marked, but you are late today. Please try to arrive on time tomorrow.";
            } elseif ($ps >= 5 && $ps % 5 === 0) {
                $hi = "$nh, आपकी उपस्थिति दर्ज हो गई है। आप लगातार $ps दिन से स्कूल आ रहे हैं, शाबाश!";
                $en = "$ne, attendance marked. You have been present $ps days in a row. Well done!";
            } elseif ($pct !== null && $pct >= 95) {
                $hi = "$nh, उपस्थिति दर्ज हो गई है। इस महीने आपकी उपस्थिति $pct प्रतिशत है, बहुत बढ़िया!";
                $en = "$ne, attendance marked. Your attendance this month is $pct percent. Excellent!";
            } elseif ($pct !== null && $pct < 75) {
                $hi = "$nh, उपस्थिति दर्ज हो गई है। इस महीने आपकी उपस्थिति $pct प्रतिशत है, कृपया नियमित स्कूल आइए।";
                $en = "$ne, attendance marked. Your attendance this month is $pct percent. Please attend regularly.";
            } else {
                $hi = pickOne([
                    "$nh, आपकी उपस्थिति दर्ज हो गई है। मन लगाकर पढ़ाई कीजिए।",
                    "$gh $nh! आपकी अटेंडेंस लग गई है। आज का दिन शानदार रहे।",
                    "$nh, स्वागत है। उपस्थिति दर्ज हो गई, अच्छे से पढ़ाई कीजिए।",
                ]);
                $en = pickOne([
                    "$ne, your attendance is marked. Study well today.",
                    "$ge, $ne! Attendance marked. Have a great day.",
                    "$ne, welcome. Attendance marked, learn something new today.",
                ]);
            }
        }
    } else { // out
        $half = ($st === 'half_day');
        if ($staff) {
            if ($half) {
                $hi = "$nh, आपका हाफ डे रिकॉर्ड हुआ है। ध्यान से जाइए।";
                $en = "$ne, your half day is recorded. Take care.";
            } else {
                $hi = "$nh, आपका चेक-आउट रिकॉर्ड हो गया है। आज के योगदान के लिए धन्यवाद, शुभ संध्या।";
                $en = "$ne, your check-out is recorded. Thank you for your contribution today. Have a good evening.";
            }
        } else {
            if ($half) {
                $hi = "$nh, आपका हाफ डे दर्ज हुआ है। ध्यान से घर जाइए।";
                $en = "$ne, your half day is recorded. Go home safely.";
            } else {
                $hi = pickOne([
                    "$nh, आपका चेक-आउट दर्ज हो गया है। घर सुरक्षित पहुँचिए, कल फिर मिलेंगे।",
                    "$nh, आज की पढ़ाई के लिए शाबाश। चेक-आउट हो गया है, ध्यान से जाइए।",
                ]);
                $en = pickOne([
                    "$ne, your check-out is recorded. Reach home safely, see you tomorrow.",
                    "$ne, well done today. Check-out recorded, take care on the way.",
                ]);
            }
        }
    }
    return ['hi' => $hi, 'en' => $en];
}

/* ═══════════════════════════ get_settings ═══════════════════════════ */
if ($action === 'get_settings') {
    $S = getSettings($pdo, $schoolId);
    jsonResponse(true, '', ['settings' => $S, 'features' => $F, 'locked' => $FT['locked'],
        'student_code_field' => $stuCol, 'staff_code_field' => $stfCol] + $S);
}

/* ═══════════════════════════ save_settings (admin) ═══════════════════════════ */
if ($action === 'save_settings') {
    if (!$isAdmin) jsonResponse(false, 'Only the school admin can change this.');
    $save = [];

    if (isset($_POST['full_day_time'])) {
        $t = trim($_POST['full_day_time']);
        if (!preg_match(TIME_RE, $t)) jsonResponse(false, 'Enter a valid full day cutoff time (HH:MM).');
        $save['attendance_full_day_time'] = $t;
    }
    foreach (['late_students' => 'attendance_late_students', 'late_staff' => 'attendance_late_staff',
              'scan_start' => 'attendance_scan_start', 'scan_end' => 'attendance_scan_end'] as $field => $key) {
        if (isset($_POST[$field])) {
            $t = trim($_POST[$field]);
            if ($t !== '' && !preg_match(TIME_RE, $t)) jsonResponse(false, "Enter a valid time for '$field' (HH:MM) or leave it empty.");
            $save[$key] = $t;
        }
    }
    if (isset($_POST['min_gap'])) {
        $g = (int)$_POST['min_gap'];
        if ($g < 1 || $g > 60) jsonResponse(false, 'Re-scan gap must be between 1 and 60 minutes.');
        $save['attendance_min_gap'] = (string)$g;
    }
    if (isset($_POST['notify_parents'])) {
        $save['attendance_notify_parents'] = $_POST['notify_parents'] === '1' ? '1' : '0';
    }
    if (isset($_POST['weekly_off'])) {
        $raw = trim((string)$_POST['weekly_off']);
        $clean = AttCore::cleanWeeklyOff($raw);
        if ($raw !== '' && count(explode(',', $clean)) !== count(array_filter(array_map('trim', explode(',', $raw)), 'strlen'))) {
            jsonResponse(false, 'Invalid weekly off value.');
        }
        $save['attendance_weekly_off'] = $clean;
    }
    if (isset($_POST['edit_lock_days'])) {
        $v = trim((string)$_POST['edit_lock_days']);
        if (!ctype_digit($v) || (int)$v > 365) jsonResponse(false, 'Edit lock must be between 0 and 365 days.');
        $save['attendance_edit_lock_days'] = (string)(int)$v;
    }
    if (isset($_POST['absent_alert'])) {
        $save['attendance_absent_alert'] = $_POST['absent_alert'] === '1' ? '1' : '0';
    }
    if (isset($_POST['absent_alert_time'])) {
        $t = trim((string)$_POST['absent_alert_time']);
        if (!preg_match(TIME_RE, $t)) jsonResponse(false, 'Enter a valid time for the "not reached school" message (HH:MM).');
        $save['attendance_absent_alert_time'] = $t;
    }
    if (isset($_POST['require_signed'])) {
        $save['attendance_require_signed'] = $_POST['require_signed'] === '1' ? '1' : '0';
        if ($save['attendance_require_signed'] === '1') AttCore::qrSecret($pdo, $schoolId, true);
    }
    if (isset($save['attendance_scan_start'], $save['attendance_scan_end'])
        && $save['attendance_scan_start'] !== '' && $save['attendance_scan_end'] !== ''
        && $save['attendance_scan_start'] >= $save['attendance_scan_end']) {
        jsonResponse(false, 'Scanning must open before it closes.');
    }
    if (isset($_POST['features'])) {
        $in = json_decode($_POST['features'], true);
        if (!is_array($in)) jsonResponse(false, 'Invalid feature list.');
        $clean = [];
        foreach (ATT_FEATURES as $k => $_) {
            if (!array_key_exists($k, $in)) continue;
            $clean[$k] = (bool)$in[$k] && !in_array($k, $FT['locked'], true);   // plan-locked features can never be switched on
        }
        if (empty($clean['students']) && empty($clean['staff'])
            && (array_key_exists('students', $clean) || array_key_exists('staff', $clean))
            && !($F['students'] && !array_key_exists('students', $clean)) && !($F['staff'] && !array_key_exists('staff', $clean))) {
            jsonResponse(false, 'Keep at least Students or Staff switched on.');
        }
        $save['attendance_features'] = json_encode($clean);
    }
    foreach (['student_code_field' => ['students', ATT_STUDENT_CODE_FIELDS, 'admission_no', 'attendance_student_code_field'],
              'staff_code_field'   => ['teachers', ATT_STAFF_CODE_FIELDS, 'employee_code', 'attendance_staff_code_field']] as $field => [$tbl, $allow, $def, $key]) {
        if (!isset($_POST[$field])) continue;
        $v = trim($_POST[$field]);
        if (!in_array($v, $allow, true)) jsonResponse(false, 'Invalid ID code field.');
        $save[$key] = $v;
    }
    if (!$save) jsonResponse(false, 'Nothing to save.');

    $up = $pdo->prepare("
        INSERT INTO school_settings (school_id, setting_key, setting_val) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)");
    foreach ($save as $k => $v) $up->execute([$schoolId, $k, $v]);

    logSchoolActivity($schoolId, $userId, $actorName, $actorRole,
        'Attendance rules updated: ' . implode(', ', array_keys($save)), 'update', $pdo);
    jsonResponse(true, 'Saved.', ['settings' => AttCore::rules($pdo, $schoolId, true)]);
}

/* ═══════════════════════════ scan ═══════════════════════════ */
if ($action === 'scan') {
    if (!can('attendance', 'add') && !can('staff_attendance', 'add')) {
        jsonResponse(false, 'Permission denied.');
    }
    requireActiveSubOrReject($pdo, 'school', $schoolId);

    $raw = trim($_POST['code'] ?? '');
    if ($raw === '') jsonResponse(false, 'Empty scan.');

    $gate      = cleanGate($_POST['gate'] ?? '');
    if ($device && $gate === '') $gate = cleanGate((string)$device['name']);
    $source    = in_array($_POST['source'] ?? '', SCAN_SOURCES, true) ? $_POST['source'] : 'camera';
    if (($source === 'camera' && !$F['camera']) || ($source === 'scanner' && !$F['usb_scanner'])
        || ($source === 'image' && !$F['photo_scan']) || ($source === 'search' && !$F['name_search'])) {
        jsonResponse(false, 'This way of scanning is turned off for your school.', ['event' => 'closed']);
    }
    if (!$F['students'] && !$F['staff']) jsonResponse(false, 'Attendance scanning is turned off for your school.', ['event' => 'closed']);
    $clientUid = preg_replace('/[^A-Za-z0-9\-_]/', '', substr($_POST['client_uid'] ?? '', 0, 40));

    // Same scan sent twice (offline sync after a lost response) → return the stored result, never re-process.
    if ($clientUid !== '') replayStored($pdo, $schoolId, $clientUid);

    // Real scan time = server time − how long the scan waited on the device (age_ms).
    // The device clock is never trusted directly, so a phone with a wrong clock cannot shift scans.
    [$nowStr, $isOffline, $ageSec, $timeErr] = AttCore::scanTime((string)($_POST['scanned_at'] ?? ''), (string)($_POST['age_ms'] ?? ''), time());
    if ($timeErr === 'too_old') {
        logReject($pdo, $schoolId, $userId, $raw, 'too_old', $gate);
        jsonResponse(false, 'This offline scan is more than 36 hours old, so it was not recorded. Mark it from the Attendance page.', ['event' => 'rejected']);
    }
    $day = substr($nowStr, 0, 10);
    $hm  = substr($nowStr, 11, 5);
    [$dayStart, $dayEnd] = dayBounds($day);
    $S = getSettings($pdo, $schoolId);

    // Optional scanning window (e.g. 06:30–10:00 and 12:00–18:00 can be handled by leaving it empty)
    if (($S['scan_start'] !== '' && $hm < $S['scan_start']) || ($S['scan_end'] !== '' && $hm > $S['scan_end'])) {
        logReject($pdo, $schoolId, $userId, $raw, 'outside_window', $gate);
        jsonResponse(false, 'Scanning is closed right now. It is open from ' . ($S['scan_start'] ?: '00:00') . ' to ' . ($S['scan_end'] ?: '23:59') . '.', ['event' => 'closed']);
    }

    /* ── Who is this? ──
       SZ1|S|<ver>|<code>|<sig>  signed card: type + code are fixed and verified
       S:<code> / T:<code>       typed (name search, prefixed cards)
       plain code                old cards: matched against students AND staff; if both match, refuse */
    $P = AttCore::parseCode($raw);
    if ($P['kind'] === 'bad') {
        logReject($pdo, $schoolId, $userId, $raw, 'bad_card', $gate);
        jsonResponse(false, 'This QR code looks damaged or edited, so it was not accepted.', ['event' => 'unknown']);
    }
    if ($S['require_signed'] && $P['kind'] !== 'signed' && !in_array($source, ['manual', 'search'], true)) {
        logReject($pdo, $schoolId, $userId, $raw, 'unsigned', $gate);
        jsonResponse(false, 'This is an old ID card. Your school only accepts the new secure cards. Ask the office for a new card, or use name search.', ['event' => 'unknown']);
    }

    $allowStu = $F['students'] && can('attendance', 'add') && $P['type'] !== 'staff';
    $allowStf = $F['staff'] && can('staff_attendance', 'add') && $P['type'] !== 'student';

    $lookup = function (array $codes) use ($pdo, $schoolId, $stuCol, $stfCol, $allowStu, $allowStf): array {
        $found = [];
        if (!$codes) return $found;
        $ph = implode(',', array_fill(0, count($codes), '?'));
        if ($allowStu) {
            $s = $pdo->prepare("
                SELECT s.id, s.name, s.photo, s.class_id, s.section_id, s.`{$stuCol}` AS matched_code,
                       CONCAT(c.class_name, COALESCE(CONCAT(' ', sec.section_name), '')) AS class_label
                FROM students s
                LEFT JOIN classes c    ON c.id = s.class_id
                LEFT JOIN sections sec ON sec.id = s.section_id
                WHERE s.school_id=? AND s.`{$stuCol}` IN ($ph) AND s.status='active' LIMIT 10");
            $s->execute([$schoolId, ...$codes]);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $found['student:' . $r['id']] = ['student', $r, (string)$r['matched_code']];
        }
        if ($allowStf) {
            $t = $pdo->prepare("
                SELECT t.id, u.name, t.photo, t.post, t.`{$stfCol}` AS matched_code
                FROM teachers t JOIN users u ON u.id = t.user_id
                WHERE t.school_id=? AND t.`{$stfCol}` IN ($ph) AND t.status='active' LIMIT 10");
            $t->execute([$schoolId, ...$codes]);
            foreach ($t->fetchAll(PDO::FETCH_ASSOC) as $r) $found['staff:' . $r['id']] = ['staff', $r, (string)$r['matched_code']];
        }
        return array_values($found);
    };

    $matches = $lookup([$P['code']]);
    if (!$matches && $P['kind'] === 'plain') {
        // Old QR that encodes more than one column: try its alphanumeric pieces, but only accept
        // the result if exactly ONE person matches (never guess between people).
        $tokens = [];
        if (preg_match_all('/[A-Za-z0-9\-\/]{3,}/', $raw, $m)) {
            foreach ($m[0] as $tok) if ($tok !== $P['code'] && !in_array($tok, $tokens, true)) $tokens[] = $tok;
        }
        $matches = $lookup(array_slice($tokens, 0, 6));
    }
    if (!$matches) {
        logReject($pdo, $schoolId, $userId, $raw, 'unknown_id', $gate);
        jsonResponse(false, 'This ID is not linked to any active student or staff member.', ['event' => 'unknown']);
    }
    if (count($matches) > 1) {
        logReject($pdo, $schoolId, $userId, $raw, 'ambiguous', $gate);
        $who = implode(', ', array_map(fn($x) => $x[1]['name'] . ' (' . ($x[0] === 'staff' ? 'staff' : 'student') . ')', array_slice($matches, 0, 3)));
        jsonResponse(false, "This code belongs to more than one person: $who. Nothing was marked. Use name search, or print new secure ID cards.", ['event' => 'ambiguous']);
    }
    [$entityType, $entity, $matchedCode] = $matches[0];
    $entityId = (int)$entity['id'];

    if ($P['kind'] === 'signed') {
        $secret = AttCore::qrSecret($pdo, $schoolId, false);
        if ($secret === '' || !hash_equals(AttCore::qrSign($secret, $schoolId, $P['letter'], $P['ver'], $P['code']), $P['sig'])) {
            logReject($pdo, $schoolId, $userId, $raw, 'bad_signature', $gate);
            jsonResponse(false, 'This ID card could not be verified. It may be a copy. Nothing was marked.', ['event' => 'unknown']);
        }
        if ($P['ver'] !== AttCore::cardVersion($pdo, $schoolId, $entityType, $entityId)) {
            logReject($pdo, $schoolId, $userId, $raw, 'revoked_card', $gate);
            jsonResponse(false, "This card of {$entity['name']} has been blocked (lost or replaced). Use the new card.", ['event' => 'unknown']);
        }
    }

    AttCore::deferFlush($pdo, $schoolId, true);
    $isStudent = ($entityType === 'student');
    $lateAfter = $isStudent ? $S['late_students'] : $S['late_staff'];
    if (!$F['late']) $lateAfter = '';
    $canLate   = ($lateAfter !== '');
    $warning   = dayWarning($pdo, $schoolId, $day);
    $base      = entityPayload($entity, $entityType, (string)$matchedCode);
    $timeLabel = date('h:i A', strtotime($nowStr));

    for ($try = 1; ; $try++) {
        $scanType = null; $statusToSet = null; $message = ''; $scanId = 0;
        try {
            $pdo->beginTransaction();

            // Lock this person's scans for the day so two gates scanning the same card
            // at the same moment cannot both decide "this is the check-in".
            $chk = $pdo->prepare("
                SELECT scan_type, scanned_at FROM attendance_scan_log
                WHERE school_id=? AND entity_type=? AND entity_id=? AND scanned_at BETWEEN ? AND ? AND voided_at IS NULL
                ORDER BY scanned_at ASC, id ASC FOR UPDATE");
            $chk->execute([$schoolId, $entityType, $entityId, $dayStart, $dayEnd]);
            $todayScans = $chk->fetchAll(PDO::FETCH_ASSOC);

            $inScan = null; $outScan = null;
            foreach ($todayScans as $sc) {
                if ($sc['scan_type'] === 'in'  && !$inScan)  $inScan  = $sc;
                if ($sc['scan_type'] === 'out' && !$outScan) $outScan = $sc;
            }

            if (!$inScan) {
                $scanType = 'in';
                $isLate   = ($canLate && $hm > $lateAfter);
                $statusToSet = $isLate ? 'late' : 'present';
                $message  = $isLate ? "Welcome, {$entity['name']}! Marked late (after {$lateAfter})."
                                    : "Welcome, {$entity['name']}!";
            } elseif (!$F['checkout']) {
                // One-scan-per-day mode: arrival is already recorded.
                $pdo->rollBack();
                jsonResponse(true, "{$entity['name']} is already marked present today.", $base + ['event' => 'complete',
                    'speech' => buildSpeech(['name' => $entity['name'], 'type' => $entityType, 'event' => 'complete', 'hm' => $hm, 'in_only' => true])]);
            } elseif (!$outScan) {
                $gapMin = (strtotime($nowStr) - strtotime($inScan['scanned_at'])) / 60;
                if ($gapMin < $S['min_gap']) {
                    $pdo->rollBack();
                    jsonResponse(true, "{$entity['name']} was already welcomed a moment ago.", $base + ['event' => 'duplicate',
                        'speech' => buildSpeech(['name' => $entity['name'], 'type' => $entityType, 'event' => 'duplicate', 'hm' => $hm])]);
                }
                $scanType  = 'out';
                $inWasLate = ($canLate && substr($inScan['scanned_at'], 11, 5) > $lateAfter);
                $isFullDay = (!$F['half_day'] || $hm >= $S['full_day_time']);
                $statusToSet = $isFullDay ? ($inWasLate ? 'late' : 'present') : 'half_day';
                $message = $isFullDay
                    ? "Good bye, {$entity['name']}! Full day attendance marked."
                    : "Good bye, {$entity['name']}! Half day marked (left before {$S['full_day_time']}).";
            } else {
                $pdo->rollBack();
                jsonResponse(true, "{$entity['name']} has already been scanned in and out today.", $base + ['event' => 'complete',
                    'speech' => buildSpeech(['name' => $entity['name'], 'type' => $entityType, 'event' => 'complete', 'hm' => $hm])]);
            }

            // Write attendance first so we know what it was before (needed for an exact undo).
            $w = AttCore::write($pdo, $schoolId, $entityType, $entityId, $day, $statusToSet, 'scan', $userId,
                                $isStudent ? ['class_id' => $entity['class_id'] ?? null, 'section_id' => $entity['section_id'] ?? null] : []);

            $pdo->prepare("
                INSERT INTO attendance_scan_log
                    (school_id, entity_type, entity_id, scan_type, raw_payload, matched_code, scanned_at, device_info, ip_address,
                     scanned_by, client_uid, gate_name, source, status_set, prev_status, is_offline_sync, client_age_sec, received_at, gate_device_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)
            ")->execute([
                $schoolId, $entityType, $entityId, $scanType, substr($raw, 0, 255), $matchedCode, $nowStr,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45), $userId,
                $clientUid !== '' ? $clientUid : null, $gate !== '' ? $gate : null, $source, $statusToSet, $w['prev'], $isOffline, $ageSec,
                $device ? (int)$device['id'] : null,
            ]);
            $scanId = (int)$pdo->lastInsertId();

            $facts  = $F['smart_voice'] ? scanFacts($pdo, $schoolId, $entityType, $entityId, $day, $scanType, $statusToSet) : [];
            $speech = $F['smart_voice'] ? buildSpeech(['name' => $entity['name'], 'type' => $entityType, 'event' => $scanType,
                                   'status' => $statusToSet, 'hm' => $hm, 'facts' => $facts]) : null;

            $data = $base + [
                'speech'       => $speech,
                'facts'        => $facts,
                'event'        => $scanType,
                'status'       => $statusToSet,
                'scan_id'      => $scanId,
                'time'         => $timeLabel,
                'late'         => ($statusToSet === 'late'),
                'warning'      => $warning,
                'offline_sync' => (bool)$isOffline,
            ];
            $pdo->prepare("UPDATE attendance_scan_log SET result_json=? WHERE id=?")
                ->execute([json_encode(['message' => $message] + $data, JSON_UNESCAPED_UNICODE), $scanId]);

            $pdo->commit();
            break;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($try < 3 && AttCore::isRetryable($e)) { usleep(random_int(50000, 150000) * $try); continue; }
            if ($clientUid !== '' && $e instanceof PDOException && (string)$e->getCode() === '23000') {
                replayStored($pdo, $schoolId, $clientUid);   // raced with an identical request — return its result
            }
            error_log('attendance_scan: ' . $e->getMessage());
            jsonResponse(false, 'Could not record attendance. Please try again.');
        }
    }

    logSchoolActivity($schoolId, $userId, $actorName, $actorRole,
        ucfirst($entityType) . " attendance scanned: {$entity['name']} → " . ($scanType === 'in' ? 'check-in' : 'check-out') . " ($statusToSet)"
        . ($gate !== '' ? " @ $gate" : '') . ($isOffline ? " [offline, scanned $nowStr]" : ''), 'attendance', $pdo);

    // Optional parent message (skip stale offline replays older than 30 min). Queued, sent after the response.
    if ($isStudent && $S['notify_parents'] && (!$isOffline || (time() - strtotime($nowStr)) < 1800)) {
        notifyGuardian($pdo, $schoolId, $entityId, $scanType, $statusToSet, $timeLabel, $day, $scanId);
    }

    jsonResponse(true, $message, $data);
}

/* ═══════════════════════════ undo ═══════════════════════════ */
if ($action === 'undo') {
    if (!$F['undo']) jsonResponse(false, 'This feature is turned off for your school.');
    requireActiveSubOrReject($pdo, 'school', $schoolId);
    $scanId = (int)($_POST['scan_id'] ?? 0);
    if ($scanId <= 0) jsonResponse(false, 'Invalid scan.');

    try {
        $pdo->beginTransaction();
        $q = $pdo->prepare("SELECT * FROM attendance_scan_log WHERE id=? AND school_id=? AND voided_at IS NULL FOR UPDATE");
        $q->execute([$scanId, $schoolId]);
        $scan = $q->fetch(PDO::FETCH_ASSOC);
        if (!$scan) { $pdo->rollBack(); jsonResponse(false, 'Scan not found or already undone.'); }

        $isStudent = ($scan['entity_type'] === 'student');
        $perm = $isStudent ? can('attendance', 'edit') : can('staff_attendance', 'edit');
        if (!$perm) { $pdo->rollBack(); jsonResponse(false, 'Permission denied.'); }
        if (!$isAdmin && (int)$scan['scanned_by'] !== $userId) { $pdo->rollBack(); jsonResponse(false, 'You can only undo scans you made yourself.'); }

        $day = substr($scan['scanned_at'], 0, 10);
        if ($day !== date('Y-m-d')) { $pdo->rollBack(); jsonResponse(false, "Only today's scans can be undone."); }
        [$dayStart, $dayEnd] = dayBounds($day);

        $last = $pdo->prepare("SELECT MAX(id) FROM attendance_scan_log
                               WHERE school_id=? AND entity_type=? AND entity_id=? AND scanned_at BETWEEN ? AND ? AND voided_at IS NULL");
        $last->execute([$schoolId, $scan['entity_type'], $scan['entity_id'], $dayStart, $dayEnd]);
        if ((int)$last->fetchColumn() !== $scanId) { $pdo->rollBack(); jsonResponse(false, 'A later scan exists for this person. Undo that one first.'); }

        $eid  = (int)$scan['entity_id'];
        $type = $scan['entity_type'];

        // If someone changed this attendance after the scan (manual edit, "mark remaining absent"),
        // undoing the scan must not silently overwrite their change.
        $cur = AttCore::current($pdo, $schoolId, $type, $eid, $day);
        if (!$cur || $cur['status'] !== $scan['status_set']) {
            $pdo->rollBack();
            jsonResponse(false, 'This attendance was changed after the scan (now: ' . ($cur['status'] ?? 'not marked')
                . '). Change it from the Attendance page instead.');
        }

        // Put back exactly what was there before this scan.
        if ($scan['prev_status'] !== null) {
            $back = $scan['prev_status'];
        } elseif ($scan['scan_type'] === 'in') {
            $back = null;                                   // the check-in created the row
        } else {
            // Scans saved before prev_status existed: fall back to the check-in result.
            $inQ = $pdo->prepare("SELECT status_set FROM attendance_scan_log
                                  WHERE school_id=? AND entity_type=? AND entity_id=? AND scan_type='in' AND scanned_at BETWEEN ? AND ? AND voided_at IS NULL
                                  ORDER BY id ASC LIMIT 1");
            $inQ->execute([$schoolId, $type, $eid, $dayStart, $dayEnd]);
            $back = $inQ->fetchColumn() ?: 'present';
        }

        $pdo->prepare("UPDATE attendance_scan_log SET voided_at=NOW(), voided_by=? WHERE id=?")->execute([$userId, $scanId]);
        AttCore::write($pdo, $schoolId, $type, $eid, $day, $back, 'undo', $userId, ['reason' => "Undo of scan #$scanId"]);

        if ($isStudent) { $n = $pdo->prepare("SELECT name FROM students WHERE id=? AND school_id=?"); $n->execute([$eid, $schoolId]); }
        else { $n = $pdo->prepare("SELECT u.name FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=? AND t.school_id=?"); $n->execute([$eid, $schoolId]); }
        $name = (string)$n->fetchColumn();

        $pdo->commit();
        logSchoolActivity($schoolId, $userId, $actorName, $actorRole,
            "Scan undone: $name ({$scan['scan_type']}, scan #$scanId) → " . ($back ?? 'not marked'), 'update', $pdo);
        jsonResponse(true, "Scan undone for $name.", ['scan_id' => $scanId, 'name' => $name]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('attendance_scan undo: ' . $e->getMessage());
        jsonResponse(false, 'Could not undo this scan. Please try again.');
    }
}

/* ═══════════════════════════ recent (all gates, live) ═══════════════════════════ */
if ($action === 'recent') {
    if (!$F['live_gates']) jsonResponse(false, 'This feature is turned off for your school.');
    [$dayStart, $dayEnd] = dayBounds(date('Y-m-d'));
    $q = $pdo->prepare("
        SELECT l.id, l.entity_type, l.entity_id, l.scan_type, l.status_set, l.scanned_at, l.gate_name, l.source,
               COALESCE(s.name, u.name) AS name, s.photo AS s_photo, t.photo AS t_photo, t.post,
               CONCAT(c.class_name, COALESCE(CONCAT(' ', sec.section_name), '')) AS class_label
        FROM attendance_scan_log l
        LEFT JOIN students s   ON l.entity_type='student' AND s.id = l.entity_id
        LEFT JOIN classes c    ON c.id = s.class_id
        LEFT JOIN sections sec ON sec.id = s.section_id
        LEFT JOIN teachers t   ON l.entity_type='staff' AND t.id = l.entity_id
        LEFT JOIN users u      ON u.id = t.user_id
        WHERE l.school_id=? AND l.scanned_at BETWEEN ? AND ? AND l.voided_at IS NULL
        ORDER BY l.id DESC LIMIT 60");
    $q->execute([$schoolId, $dayStart, $dayEnd]);
    $items = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $isS = ($r['entity_type'] === 'student');
        $items[] = [
            'scan_id'    => (int)$r['id'],
            'type'       => $r['entity_type'],
            'person_id'  => (int)$r['entity_id'],
            'scan_type'  => $r['scan_type'],
            'status'     => $r['status_set'],
            'scanned_at' => $r['scanned_at'],
            'gate'       => $r['gate_name'] ?? '',
            'source'     => $r['source'] ?? '',
            'name'       => $r['name'] ?? '',
            'photo'      => scanPhotoUrl(['id' => $r['entity_id'], 'photo' => $isS ? $r['s_photo'] : $r['t_photo']], $r['entity_type']),
            'sub'        => $isS ? ($r['class_label'] ?? '') : ($r['post'] ?? ''),
        ];
    }
    jsonResponse(true, '', ['items' => $items]);
}

/* ═══════════════════════════ summary ═══════════════════════════ */
if ($action === 'summary') {
    AttCore::deferFlush($pdo, $schoolId, true);   // daily jobs (e.g. "not reached school") for schools without cron
    $day = date('Y-m-d');
    [$dayStart, $dayEnd] = dayBounds($day);

    $cnt = function (string $sql) use ($pdo, $schoolId): int { $s = $pdo->prepare($sql); $s->execute([$schoolId]); return (int)$s->fetchColumn(); };
    $stuTotal = $cnt("SELECT COUNT(*) FROM students WHERE school_id=? AND status='active'");
    $stfTotal = $cnt("SELECT COUNT(*) FROM teachers WHERE school_id=? AND status='active'");

    $by = function (string $sql) use ($pdo, $schoolId, $day): array {
        $s = $pdo->prepare($sql); $s->execute([$schoolId, $day]);
        return $s->fetchAll(PDO::FETCH_KEY_PAIR);
    };
    $stuBy = $by("SELECT status, COUNT(*) FROM attendance WHERE school_id=? AND date=? GROUP BY status");
    $stfBy = $by("SELECT status, COUNT(*) FROM staff_attendance WHERE school_id=? AND date=? GROUP BY status");

    $sc = $pdo->prepare("SELECT entity_type, SUM(scan_type='in') AS ins, SUM(scan_type='out') AS outs
                         FROM attendance_scan_log WHERE school_id=? AND scanned_at BETWEEN ? AND ? AND voided_at IS NULL
                         GROUP BY entity_type");
    $sc->execute([$schoolId, $dayStart, $dayEnd]);
    $scanBy = [];
    foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) $scanBy[$r['entity_type']] = $r;

    $mk = function (array $by, int $total, ?array $scan): array {
        $marked = array_sum(array_map('intval', $by));
        $in  = (int)($scan['ins'] ?? 0);
        $out = (int)($scan['outs'] ?? 0);
        return [
            'total'       => $total,
            'present'     => (int)($by['present'] ?? 0),
            'late'        => (int)($by['late'] ?? 0),
            'half_day'    => (int)($by['half_day'] ?? 0),
            'absent'      => (int)($by['absent'] ?? 0),
            'leave'       => (int)($by['leave'] ?? 0),
            'marked'      => $marked,
            'not_marked'  => max(0, $total - $marked),
            'scanned_in'  => $in,
            'scanned_out' => $out,
            'inside'      => max(0, $in - $out),
        ];
    };

    $cl = $pdo->prepare("
        SELECT CONCAT(c.class_name, COALESCE(CONCAT(' ', sec.section_name), '')) AS label,
               COUNT(s.id) AS total,
               COALESCE(SUM(a.status IN ('present','late','half_day')), 0) AS here
        FROM students s
        LEFT JOIN classes c    ON c.id = s.class_id
        LEFT JOIN sections sec ON sec.id = s.section_id
        LEFT JOIN attendance a ON a.student_id = s.id AND a.school_id = s.school_id AND a.date = ?
        WHERE s.school_id=? AND s.status='active'
        GROUP BY s.class_id, s.section_id, c.class_name, sec.section_name
        ORDER BY c.class_name, sec.section_name LIMIT 80");
    $cl->execute([$day, $schoolId]);
    $classes = !$F['class_progress'] ? [] : array_map(fn($r) => ['label' => $r['label'] ?: 'No class', 'total' => (int)$r['total'], 'here' => (int)$r['here']], $cl->fetchAll(PDO::FETCH_ASSOC));

    jsonResponse(true, '', [
        'date'     => $day,
        'warning'  => dayWarning($pdo, $schoolId, $day),
        'students' => $mk($stuBy, $stuTotal, $scanBy['student'] ?? null),
        'staff'    => $mk($stfBy, $stfTotal, $scanBy['staff'] ?? null),
        'classes'  => $classes,
        'settings' => getSettings($pdo, $schoolId),
    ]);
}

/* ═══════════════════════════ not_arrived ═══════════════════════════ */
if ($action === 'not_arrived') {
    $type = ($_POST['type'] ?? 'student') === 'staff' ? 'staff' : 'student';
    $day  = date('Y-m-d');
    $limit = 300;
    if ($type === 'student') {
        if (!can('attendance', 'add')) jsonResponse(false, 'Permission denied.');
        $q = $pdo->prepare("
            SELECT s.name, s.`{$stuCol}` AS code, s.guardian_phone AS phone,
                   CONCAT(c.class_name, COALESCE(CONCAT(' ', sec.section_name), '')) AS sub
            FROM students s
            LEFT JOIN classes c    ON c.id = s.class_id
            LEFT JOIN sections sec ON sec.id = s.section_id
            LEFT JOIN attendance a ON a.student_id = s.id AND a.school_id = s.school_id AND a.date = ?
            WHERE s.school_id=? AND s.status='active' AND a.student_id IS NULL
            ORDER BY c.class_name, sec.section_name, s.name LIMIT " . ($limit + 1));
    } else {
        if (!can('staff_attendance', 'add')) jsonResponse(false, 'Permission denied.');
        $q = $pdo->prepare("
            SELECT u.name, t.`{$stfCol}` AS code, '' AS phone, COALESCE(t.post, '') AS sub
            FROM teachers t
            JOIN users u ON u.id = t.user_id
            LEFT JOIN staff_attendance a ON a.teacher_id = t.id AND a.school_id = t.school_id AND a.date = ?
            WHERE t.school_id=? AND t.status='active' AND a.teacher_id IS NULL
            ORDER BY u.name LIMIT " . ($limit + 1));
    }
    $q->execute([$day, $schoolId]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    $truncated = count($rows) > $limit;
    jsonResponse(true, '', ['rows' => array_slice($rows, 0, $limit), 'truncated' => $truncated]);
}

/* ═══════════════════════════ finalize_absent (admin) ═══════════════════════════ */
if ($action === 'finalize_absent') {
    if (!$F['finalize_absent']) jsonResponse(false, 'This feature is turned off for your school.');
    requireActiveSubOrReject($pdo, 'school', $schoolId);
    if (!$isAdmin) jsonResponse(false, 'Only the school admin can do this.');
    $day   = date('Y-m-d');
    $type  = $_POST['type'] ?? 'both';
    $force = (($_POST['force'] ?? '') === '1');
    $warn  = dayWarning($pdo, $schoolId, $day);
    if ($warn !== '' && !$force) {
        jsonResponse(false, $warn . ' Marking everyone absent on a non-working day is usually a mistake.', ['needs_force' => true]);
    }
    $stu = 0; $stf = 0; $absentStudents = [];
    for ($try = 1; ; $try++) {
        $stu = 0; $stf = 0; $absentStudents = [];
        try {
            $pdo->beginTransaction();
            if (in_array($type, ['both', 'student'], true) && can('attendance', 'add')) {
                $q = $pdo->prepare("
                    SELECT s.id, s.class_id, s.section_id FROM students s
                    WHERE s.school_id=? AND s.status='active'
                      AND NOT EXISTS (SELECT 1 FROM attendance a WHERE a.school_id = s.school_id AND a.student_id = s.id AND a.date = ?)");
                $q->execute([$schoolId, $day]);
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    // INSERT IGNORE: a student who scans in at this exact moment keeps their scan.
                    if (AttCore::insertIfMissing($pdo, $schoolId, 'student', (int)$r['id'], $day, 'absent', 'finalize', $userId,
                                                 ['class_id' => $r['class_id'], 'section_id' => $r['section_id']])) {
                        $stu++; $absentStudents[] = (int)$r['id'];
                    }
                }
            }
            if (in_array($type, ['both', 'staff'], true) && can('staff_attendance', 'add')) {
                $q = $pdo->prepare("
                    SELECT t.id FROM teachers t
                    WHERE t.school_id=? AND t.status='active'
                      AND NOT EXISTS (SELECT 1 FROM staff_attendance a WHERE a.school_id = t.school_id AND a.teacher_id = t.id AND a.date = ?)");
                $q->execute([$schoolId, $day]);
                foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $tid) {
                    if (AttCore::insertIfMissing($pdo, $schoolId, 'staff', (int)$tid, $day, 'absent', 'finalize', $userId)) $stf++;
                }
            }
            $pdo->commit();
            break;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($try < 3 && AttCore::isRetryable($e)) { usleep(150000 * $try); continue; }
            error_log('attendance_scan finalize: ' . $e->getMessage());
            jsonResponse(false, 'Could not close attendance. Please try again.');
        }
    }
    logSchoolActivity($schoolId, $userId, $actorName, $actorRole,
        "Attendance closed for $day: $stu students and $stf staff marked absent", 'create', $pdo);

    // The 6-day absence alert used to run only from manual marking, so schools using the gate never got it.
    if ($absentStudents) AttCore::checkConsecutiveAbsence($pdo, $schoolId, $absentStudents, $day);

    jsonResponse(true, "Marked absent: $stu students, $stf staff. Anyone who scans later will become Present.", ['students' => $stu, 'staff' => $stf]);
}

/* ═══════════════════════════ search ═══════════════════════════ */
if ($action === 'search') {
    if (!$F['name_search']) jsonResponse(false, 'This feature is turned off for your school.');
    $q = trim($_POST['q'] ?? '');
    if (mb_strlen($q) < 2) jsonResponse(true, '', ['results' => []]);
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $out = [];
    if ($F['students'] && can('attendance', 'add')) {
        $s = $pdo->prepare("
            SELECT s.id, s.name, s.`{$stuCol}` AS code, s.photo,
                   CONCAT(c.class_name, COALESCE(CONCAT(' ', sec.section_name), '')) AS class_label
            FROM students s
            LEFT JOIN classes c    ON c.id = s.class_id
            LEFT JOIN sections sec ON sec.id = s.section_id
            WHERE s.school_id=? AND s.status='active' AND (s.name LIKE ? OR s.`{$stuCol}` LIKE ?)
            ORDER BY s.name LIMIT 8");
        $s->execute([$schoolId, $like, $like]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = ['type' => 'student', 'name' => $r['name'], 'code' => $r['code'], 'scan_code' => 'S:' . $r['code'],
                      'sub' => $r['class_label'] ?? '', 'photo' => scanPhotoUrl($r, 'student')];
        }
    }
    if ($F['staff'] && can('staff_attendance', 'add')) {
        $t = $pdo->prepare("
            SELECT t.id, u.name, t.`{$stfCol}` AS code, t.photo, t.post
            FROM teachers t JOIN users u ON u.id = t.user_id
            WHERE t.school_id=? AND t.status='active' AND (u.name LIKE ? OR t.`{$stfCol}` LIKE ?)
            ORDER BY u.name LIMIT 5");
        $t->execute([$schoolId, $like, $like]);
        foreach ($t->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = ['type' => 'staff', 'name' => $r['name'], 'code' => $r['code'], 'scan_code' => 'T:' . $r['code'],
                      'sub' => $r['post'] ?? '', 'photo' => scanPhotoUrl($r, 'staff')];
        }
    }
    jsonResponse(true, '', ['results' => $out]);
}

/* ═══════════════════════════ card_payload (admin) ═══════════════════════════ */
// Signed QR text for ID cards. The ID card print page must put `payload` into the QR.
if ($action === 'card_payload') {
    if (!$isAdmin) jsonResponse(false, 'Only the school admin can do this.');
    $type = ($_POST['type'] ?? '') === 'staff' ? 'staff' : 'student';
    $ids  = array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? '')))))), 0, 1000);
    if (!$ids) jsonResponse(false, 'No people selected.');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $q = $type === 'student'
        ? $pdo->prepare("SELECT s.id, s.name, s.`{$stuCol}` AS code FROM students s WHERE s.school_id=? AND s.id IN ($ph)")
        : $pdo->prepare("SELECT t.id, u.name, t.`{$stfCol}` AS code FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.school_id=? AND t.id IN ($ph)");
    $q->execute([$schoolId, ...$ids]);
    $out = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ((string)$r['code'] === '') continue;
        $out[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'code' => $r['code'],
                  'payload' => AttCore::cardPayload($pdo, $schoolId, $type, (int)$r['id'], (string)$r['code'])];
    }
    jsonResponse(true, '', ['cards' => $out]);
}

/* ═══════════════════════════ revoke_card (admin) ═══════════════════════════ */
// Lost / stolen card: the old printed card stops working at once. Print a new card with the returned payload.
if ($action === 'revoke_card') {
    if (!$isAdmin) jsonResponse(false, 'Only the school admin can do this.');
    $P = AttCore::parseCode((string)($_POST['code'] ?? ''));
    if ($P['kind'] === 'bad' || $P['code'] === '') jsonResponse(false, 'Enter the ID code printed on the card.');
    $found = [];
    if ($P['type'] !== 'staff') {
        $q = $pdo->prepare("SELECT id, name FROM students WHERE school_id=? AND `{$stuCol}`=?");
        $q->execute([$schoolId, $P['code']]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $found[] = ['student', $r];
    }
    if ($P['type'] !== 'student') {
        $q = $pdo->prepare("SELECT t.id, u.name FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.school_id=? AND t.`{$stfCol}`=?");
        $q->execute([$schoolId, $P['code']]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $found[] = ['staff', $r];
    }
    if (!$found) jsonResponse(false, 'No student or staff member has this ID code.');
    if (count($found) > 1) jsonResponse(false, 'This code belongs to more than one person. Type S:' . $P['code'] . ' for the student or T:' . $P['code'] . ' for staff.');
    [$type, $r] = $found[0];
    $ver = AttCore::revokeCard($pdo, $schoolId, $type, (int)$r['id']);
    $payload = AttCore::cardPayload($pdo, $schoolId, $type, (int)$r['id'], $P['code']);
    logSchoolActivity($schoolId, $userId, $actorName, $actorRole,
        "ID card blocked and reissued: {$r['name']} ($type, card v$ver)", 'update', $pdo);
    jsonResponse(true, "Old card of {$r['name']} is blocked. Print a new card with this QR text.", ['name' => $r['name'], 'type' => $type, 'payload' => $payload]);
}

/* ═══════════════════════════ insights ═══════════════════════════ */
// Students who need attention: below 75%, absent 3+ days in a row, or often late (last 30 working days).
if ($action === 'insights') {
    if (!$F['students'] || !(can('attendance', 'add') || can('attendance', 'edit'))) jsonResponse(false, 'Permission denied.');
    jsonResponse(true, '', ['rows' => AttCore::needsAttention($pdo, $schoolId, date('Y-m-d'))]);
}

/* ═══════════════════════════ gate devices ═══════════════════════════ */
if ($action === 'device_register') {
    if (!$isAdmin) jsonResponse(false, 'Only the school admin can register a gate device.');
    $name = cleanGate((string)($_POST['name'] ?? ''));
    if ($name === '') jsonResponse(false, 'Give this gate device a name, e.g. Main Gate.');
    $active = count(AttCore::deviceList($pdo, $schoolId));
    if ($active >= 50) jsonResponse(false, 'Too many gate devices. Remove old ones first.');
    $token = AttCore::deviceCreate($pdo, $schoolId, $name, $userId);
    logSchoolActivity($schoolId, $userId, $actorName, $actorRole, "Gate device registered: $name", 'create', $pdo);
    jsonResponse(true, "This browser is now the gate device \"$name\". It keeps scanning even after your login expires.", ['token' => $token, 'name' => $name]);
}
if ($action === 'device_list') {
    if (!$isAdmin) jsonResponse(false, 'Only the school admin can see gate devices.');
    jsonResponse(true, '', ['devices' => AttCore::deviceList($pdo, $schoolId)]);
}
if ($action === 'device_revoke') {
    if (!$isAdmin) jsonResponse(false, 'Only the school admin can remove a gate device.');
    $id = (int)($_POST['id'] ?? 0);
    if (!AttCore::deviceRevoke($pdo, $schoolId, $id, $userId)) jsonResponse(false, 'Device not found or already removed.');
    logSchoolActivity($schoolId, $userId, $actorName, $actorRole, "Gate device removed: #$id", 'update', $pdo);
    jsonResponse(true, 'Gate device removed. It can no longer scan.');
}

jsonResponse(false, 'Unknown action.');