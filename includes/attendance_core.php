<?php
// includes/attendance_core.php — shared attendance logic used by
//   api/attendance_actions.php   (manual marking / editing)
//   api/attendance_scan.php      (gate scanning, undo, finalize)
//   cron/attendance_notify_worker.php
//
// Everything that WRITES attendance goes through AttCore::write() so every change is recorded in
// attendance_history (old status → new status, source, who, reason). Parent messages go through
// AttCore::queueNotify() so no request ever waits for an SMS / WhatsApp gateway.
//
// Requires sql/attendance_fixes_v3.sql to have been run once.

final class AttCore
{
    public const STATUSES      = ['present', 'absent', 'late', 'half_day', 'leave'];
    public const TIME_RE       = '/^([01]\d|2[0-3]):[0-5]\d$/';
    public const QR_PREFIX     = 'SZ1';
    public const MAX_OFFLINE_S = 36 * 3600;   // offline scans older than this are refused, not silently re-dated
    public const OFFLINE_AFTER = 90;          // a scan that waited longer than this on the device counts as an offline sync
    public const ABSENT_ALERT_DAYS = 6;

    /* ───────────────────────── Settings ───────────────────────── */

    private static array $rulesCache = [];

    /** All attendance rules for a school, validated, with defaults. */
    public static function rules(PDO $pdo, int $schoolId, bool $fresh = false): array
    {
        if (!$fresh && isset(self::$rulesCache[$schoolId])) return self::$rulesCache[$schoolId];
        $keys = ['attendance_full_day_time', 'attendance_late_students', 'attendance_late_staff', 'attendance_min_gap',
                 'attendance_notify_parents', 'attendance_scan_start', 'attendance_scan_end', 'attendance_weekly_off',
                 'attendance_edit_lock_days', 'attendance_require_signed'];
        $in = implode(',', array_fill(0, count($keys), '?'));
        $st = $pdo->prepare("SELECT setting_key, setting_val FROM school_settings WHERE school_id=? AND setting_key IN ($in)");
        $st->execute([$schoolId, ...$keys]);
        $raw = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        $t = fn(string $k): string => (isset($raw[$k]) && preg_match(self::TIME_RE, (string)$raw[$k])) ? (string)$raw[$k] : '';

        $weekly = array_key_exists('attendance_weekly_off', $raw) ? self::cleanWeeklyOff((string)$raw['attendance_weekly_off']) : '7';
        $lock   = (isset($raw['attendance_edit_lock_days']) && $raw['attendance_edit_lock_days'] !== '' && ctype_digit((string)$raw['attendance_edit_lock_days']))
                ? min(365, (int)$raw['attendance_edit_lock_days']) : 7;

        return self::$rulesCache[$schoolId] = [
            'full_day_time'   => $t('attendance_full_day_time') ?: '14:00',
            'late_students'   => $t('attendance_late_students'),
            'late_staff'      => $t('attendance_late_staff'),
            'scan_start'      => $t('attendance_scan_start'),
            'scan_end'        => $t('attendance_scan_end'),
            'min_gap'         => isset($raw['attendance_min_gap']) ? max(1, min(60, (int)$raw['attendance_min_gap'])) : 5,
            'notify_parents'  => (($raw['attendance_notify_parents'] ?? '0') === '1'),
            'weekly_off'      => $weekly,          // e.g. "7" or "7,6:2,6:4" (Sunday + 2nd & 4th Saturday)
            'edit_lock_days'  => $lock,            // teachers cannot change attendance older than this many days
            'require_signed'  => (($raw['attendance_require_signed'] ?? '0') === '1'),
        ];
    }

    /** Keeps only valid weekly-off tokens: "N" (ISO weekday 1-7) or "N:k" (k-th such weekday of the month, 1-5). */
    public static function cleanWeeklyOff(string $v): string
    {
        $out = [];
        foreach (explode(',', $v) as $tok) {
            $tok = trim($tok);
            if (preg_match('/^[1-7]$/', $tok) || preg_match('/^[1-7]:[1-5]$/', $tok)) $out[$tok] = true;
        }
        return implode(',', array_keys($out));
    }

    /* ───────────────────────── Working days ───────────────────────── */

    public static function isWeeklyOff(string $date, string $weeklyOff): bool
    {
        if ($weeklyOff === '') return false;
        $ts  = strtotime($date);
        $dow = (int)date('N', $ts);
        $nth = intdiv((int)date('j', $ts) - 1, 7) + 1;
        $set = array_flip(explode(',', $weeklyOff));
        return isset($set[(string)$dow]) || isset($set["$dow:$nth"]);
    }

    private static function holidaySet(PDO $pdo, int $schoolId, string $from, string $to): array
    {
        try {
            $s = $pdo->prepare("SELECT date FROM school_holidays WHERE school_id=? AND date BETWEEN ? AND ?");
            $s->execute([$schoolId, $from, $to]);
            return array_flip($s->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** '' when $date is a working day, otherwise a short human reason. */
    public static function offDayReason(PDO $pdo, int $schoolId, string $date): string
    {
        $r = self::rules($pdo, $schoolId);
        if (self::isWeeklyOff($date, $r['weekly_off'])) return date('l', strtotime($date)) . ' is a weekly off.';
        if (self::holidaySet($pdo, $schoolId, $date, $date)) return 'This day is marked as a school holiday.';
        return '';
    }

    /** Working days ending at $endDay (inclusive or not), newest first. Looks back at most 90 days. */
    public static function schoolDaysBack(PDO $pdo, int $schoolId, string $endDay, int $n, bool $inclusive = false): array
    {
        $end   = $inclusive ? $endDay : date('Y-m-d', strtotime("$endDay -1 day"));
        $start = date('Y-m-d', strtotime("$endDay -90 days"));
        $hol   = self::holidaySet($pdo, $schoolId, $start, $end);
        $wo    = self::rules($pdo, $schoolId)['weekly_off'];
        $out = []; $c = strtotime($end); $min = strtotime($start);
        while (count($out) < $n && $c >= $min) {
            $d = date('Y-m-d', $c);
            if (!self::isWeeklyOff($d, $wo) && !isset($hol[$d])) $out[] = $d;
            $c = strtotime('-1 day', $c);
        }
        return $out;
    }

    /* ───────────────────────── Edit lock ───────────────────────── */

    /** '' if this user may change attendance for $date, else the reason. Admins are only blocked from future dates. */
    public static function editBlockedReason(PDO $pdo, int $schoolId, string $date, bool $isAdmin): string
    {
        $today = date('Y-m-d');
        if ($date > $today) return 'Attendance cannot be marked for a future date.';
        if ($isAdmin) return '';
        $days = self::rules($pdo, $schoolId)['edit_lock_days'];
        $oldest = date('Y-m-d', strtotime("$today -$days days"));
        if ($date < $oldest) {
            return $days === 0 ? 'Only today\'s attendance can be changed. Ask the school admin to change older records.'
                               : "Attendance older than $days days is locked. Ask the school admin to change it.";
        }
        return '';
    }

    /* ───────────────────────── Scan time ───────────────────────── */

    /**
     * When was this scan really made?
     *
     * New clients send age_ms = how long the scan waited on the device before this request. The real
     * time is then server_now − age, so a phone with a wrong clock no longer shifts every scan.
     * Old cached pages only send scanned_at (device clock); that path is kept so they keep working.
     *
     * @return array{0:string,1:int,2:?int,3:string} [Y-m-d H:i:s, is_offline, age_seconds, error]
     */
    public static function scanTime(string $clientTs, string $ageMs, int $now): array
    {
        if ($ageMs !== '' && preg_match('/^\d{1,12}$/', $ageMs)) {
            $age = intdiv((int)$ageMs, 1000);
            if ($age > self::MAX_OFFLINE_S) return [date('Y-m-d H:i:s', $now), 0, $age, 'too_old'];
            return [date('Y-m-d H:i:s', $now - $age), $age > self::OFFLINE_AFTER ? 1 : 0, $age, ''];
        }
        // Legacy client (no age_ms).
        $ts = $now; $offline = 0;
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $clientTs)) {
            $c = strtotime($clientTs);
            if ($c !== false && $c <= $now + 120 && $c >= $now - self::MAX_OFFLINE_S && ($now - $c) > self::OFFLINE_AFTER) {
                $ts = $c; $offline = 1;
            }
        }
        return [date('Y-m-d H:i:s', $ts), $offline, null, ''];
    }

    /* ───────────────────────── Signed QR cards ───────────────────────── */

    /** Per-school HMAC key. Created on first use when $create is true. */
    public static function qrSecret(PDO $pdo, int $schoolId, bool $create = false): string
    {
        $s = $pdo->prepare("SELECT setting_val FROM school_settings WHERE school_id=? AND setting_key='attendance_qr_secret'");
        $s->execute([$schoolId]);
        $v = (string)$s->fetchColumn();
        if (preg_match('/^[0-9a-f]{64}$/', $v) || !$create) return $v;
        $v = bin2hex(random_bytes(32));
        $pdo->prepare("INSERT INTO school_settings (school_id, setting_key, setting_val) VALUES (?, 'attendance_qr_secret', ?)
                       ON DUPLICATE KEY UPDATE setting_val = IF(setting_val REGEXP '^[0-9a-f]{64}$', setting_val, VALUES(setting_val))")
            ->execute([$schoolId, $v]);
        return self::qrSecret($pdo, $schoolId, false);
    }

    public static function typeLetter(string $type): string { return $type === 'staff' ? 'T' : 'S'; }

    public static function qrSign(string $secret, int $schoolId, string $letter, int $ver, string $code): string
    {
        return substr(hash_hmac('sha256', self::QR_PREFIX . "|$letter|$ver|$code|$schoolId", $secret), 0, 20);
    }

    /**
     * Splits a raw scan into its parts.
     *   SZ1|S|<ver>|<code>|<sig>   signed card (S = student, T = staff)
     *   S:<code> / T:<code>        typed but unsigned (e.g. picked from name search)
     *   anything else              plain code (old cards)
     */
    public static function parseCode(string $raw): array
    {
        $raw = trim($raw);
        if (str_starts_with($raw, self::QR_PREFIX . '|')) {
            $p = explode('|', $raw);
            if (count($p) >= 5 && in_array($p[1], ['S', 'T'], true) && ctype_digit($p[2])) {
                $sig  = array_pop($p);
                $code = implode('|', array_slice($p, 3));
                if ($code !== '' && preg_match('/^[0-9a-f]{20}$/', $sig)) {
                    return ['kind' => 'signed', 'type' => $p[1] === 'T' ? 'staff' : 'student', 'letter' => $p[1],
                            'ver' => (int)$p[2], 'code' => $code, 'sig' => $sig];
                }
            }
            return ['kind' => 'bad', 'type' => null, 'code' => $raw];
        }
        if (preg_match('/^([STE]):(.+)$/i', $raw, $m)) {
            return ['kind' => 'typed', 'type' => strtoupper($m[1]) === 'S' ? 'student' : 'staff', 'code' => trim($m[2])];
        }
        return ['kind' => 'plain', 'type' => null, 'code' => $raw];
    }

    public static function cardVersion(PDO $pdo, int $schoolId, string $type, int $id): int
    {
        $s = $pdo->prepare("SELECT version FROM attendance_card WHERE school_id=? AND entity_type=? AND entity_id=?");
        $s->execute([$schoolId, $type, $id]);
        $v = $s->fetchColumn();
        return $v === false ? 1 : max(1, (int)$v);
    }

    /** Lost card: bump the version so the old printed card stops working. Returns the new version. */
    public static function revokeCard(PDO $pdo, int $schoolId, string $type, int $id): int
    {
        $pdo->prepare("INSERT INTO attendance_card (school_id, entity_type, entity_id, version, revoked_at)
                       VALUES (?,?,?,2,NOW())
                       ON DUPLICATE KEY UPDATE version = version + 1, revoked_at = NOW()")
            ->execute([$schoolId, $type, $id]);
        return self::cardVersion($pdo, $schoolId, $type, $id);
    }

    /** The text an ID card's QR must contain. Card printing pages should call this. */
    public static function cardPayload(PDO $pdo, int $schoolId, string $type, int $id, string $code): string
    {
        $secret = self::qrSecret($pdo, $schoolId, true);
        $letter = self::typeLetter($type);
        $ver    = self::cardVersion($pdo, $schoolId, $type, $id);
        return self::QR_PREFIX . "|$letter|$ver|$code|" . self::qrSign($secret, $schoolId, $letter, $ver, $code);
    }

    /* ───────────────────────── Writing attendance ───────────────────────── */

    /**
     * The ONLY way attendance rows are created, changed or removed.
     * Locks the row, writes it, and records the change in attendance_history.
     * Must be called inside a transaction.
     *
     * $status null = remove the row (used by undo of a check-in that created it).
     * @return array{prev:?string, changed:bool}
     */
    public static function write(PDO $pdo, int $schoolId, string $type, int $pid, string $date, ?string $status,
                                 string $source, int $by, array $opt = []): array
    {
        $isS   = ($type === 'student');
        $table = $isS ? 'attendance' : 'staff_attendance';
        $col   = $isS ? 'student_id' : 'teacher_id';

        $q = $pdo->prepare("SELECT status FROM $table WHERE school_id=? AND $col=? AND date=? FOR UPDATE");
        $q->execute([$schoolId, $pid, $date]);
        $prev = $q->fetchColumn();
        $prev = ($prev === false) ? null : (string)$prev;

        if ($prev === $status) return ['prev' => $prev, 'changed' => false];

        if ($status === null) {
            $pdo->prepare("DELETE FROM $table WHERE school_id=? AND $col=? AND date=?")->execute([$schoolId, $pid, $date]);
        } elseif ($isS) {
            $pdo->prepare("
                INSERT INTO attendance (school_id, student_id, class_id, section_id, date, status, marked_by, source, updated_at)
                VALUES (?,?,?,?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by),
                                        source = VALUES(source), updated_at = NOW()")
                ->execute([$schoolId, $pid, ($opt['class_id'] ?? null) ?: null, ($opt['section_id'] ?? null) ?: null,
                           $date, $status, $by, $source]);
        } else {
            $pdo->prepare("
                INSERT INTO staff_attendance (school_id, teacher_id, date, status, marked_by, source, updated_at)
                VALUES (?,?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by),
                                        source = VALUES(source), updated_at = NOW()")
                ->execute([$schoolId, $pid, $date, $status, $by, $source]);
        }

        $pdo->prepare("
            INSERT INTO attendance_history (school_id, entity_type, entity_id, date, old_status, new_status, source, reason, changed_by, changed_at)
            VALUES (?,?,?,?,?,?,?,?,?,NOW())")
            ->execute([$schoolId, $type, $pid, $date, $prev, $status, $source,
                       ($opt['reason'] ?? '') !== '' ? mb_substr((string)$opt['reason'], 0, 255) : null, $by]);

        return ['prev' => $prev, 'changed' => true];
    }

    /**
     * Inserts a row only if none exists yet (INSERT IGNORE), with a history entry when it did insert.
     * Used by "mark remaining absent": a scan that lands at the same moment always wins.
     */
    public static function insertIfMissing(PDO $pdo, int $schoolId, string $type, int $pid, string $date, string $status,
                                           string $source, int $by, array $opt = []): bool
    {
        static $stmts = [];
        $k = spl_object_id($pdo) . $type;
        if (!isset($stmts[$k])) {
            $stmts[$k] = [
                $type === 'student'
                    ? $pdo->prepare("INSERT IGNORE INTO attendance (school_id, student_id, class_id, section_id, date, status, marked_by, source, updated_at)
                                     VALUES (?,?,?,?,?,?,?,?,NOW())")
                    : $pdo->prepare("INSERT IGNORE INTO staff_attendance (school_id, teacher_id, date, status, marked_by, source, updated_at)
                                     VALUES (?,?,?,?,?,?,NOW())"),
                $pdo->prepare("INSERT INTO attendance_history (school_id, entity_type, entity_id, date, old_status, new_status, source, reason, changed_by, changed_at)
                               VALUES (?,?,?,?,NULL,?,?,NULL,?,NOW())"),
            ];
        }
        [$ins, $hist] = $stmts[$k];
        $ins->execute($type === 'student'
            ? [$schoolId, $pid, ($opt['class_id'] ?? null) ?: null, ($opt['section_id'] ?? null) ?: null, $date, $status, $by, $source]
            : [$schoolId, $pid, $date, $status, $by, $source]);
        if ($ins->rowCount() !== 1) return false;
        $hist->execute([$schoolId, $type, $pid, $date, $status, $source, $by]);
        return true;
    }

    /** Current status + source of one attendance row (no lock). */
    public static function current(PDO $pdo, int $schoolId, string $type, int $pid, string $date): ?array
    {
        $isS = ($type === 'student');
        $q = $pdo->prepare("SELECT status, source FROM " . ($isS ? 'attendance' : 'staff_attendance')
                         . " WHERE school_id=? AND " . ($isS ? 'student_id' : 'teacher_id') . "=? AND date=?");
        $q->execute([$schoolId, $pid, $date]);
        $r = $q->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** True for MySQL deadlock / lock-wait timeout — safe to retry the whole transaction. */
    public static function isRetryable(\Throwable $e): bool
    {
        if (!$e instanceof PDOException) return false;
        $driver = (int)($e->errorInfo[1] ?? 0);
        return in_array($driver, [1213, 1205], true) || (string)$e->getCode() === '40001';
    }

    /* ───────────────────────── Notifications (queued) ───────────────────────── */

    private static array $flushFor = [];

    /**
     * Queue a parent / staff message. Never calls the SMS / WhatsApp gateway inside the request:
     * the queue is flushed after the response has been sent (see deferFlush) and by the cron worker.
     * $dedupeKey + $dedupeDays: skip if the same key was queued within that many days.
     */
    public static function queueNotify(PDO $pdo, int $schoolId, string $event, array $data,
                                       ?string $dedupeKey = null, int $dedupeDays = 0): bool
    {
        try {
            if ($dedupeKey !== null && $dedupeDays > 0) {
                $d = $pdo->prepare("SELECT 1 FROM attendance_notify_queue
                                    WHERE school_id=? AND dedupe_key=? AND status <> 'failed'
                                      AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT 1");
                $d->execute([$schoolId, $dedupeKey, $dedupeDays]);
                if ($d->fetchColumn()) return false;
            }
            $pdo->prepare("INSERT INTO attendance_notify_queue (school_id, event_type, dedupe_key, payload_json, status, attempts, available_at, created_at)
                           VALUES (?,?,?,?, 'pending', 0, NOW(), NOW())")
                ->execute([$schoolId, $event, $dedupeKey, json_encode($data, JSON_UNESCAPED_UNICODE)]);
            self::deferFlush($pdo, $schoolId);
            return true;
        } catch (\Throwable $e) {
            // Queue table missing (migration not run) — fall back to the old direct send so messages are not lost.
            error_log('attendance notify queue unavailable, sending directly: ' . $e->getMessage());
            try {
                require_once __DIR__ . '/notifier.php';
                sendSchoolNotification($schoolId, $event, $data);
                return true;
            } catch (\Throwable $e2) {
                error_log('attendance notify direct send failed: ' . $e2->getMessage());
                return false;
            }
        }
    }

    /**
     * After the JSON response is out, send this school's pending messages.
     * PHP-FPM / LiteSpeed: the browser already has its answer, nothing waits.
     * mod_php: runs before the connection closes (same as before), still capped to a few messages.
     */
    public static function deferFlush(PDO $pdo, int $schoolId): void
    {
        if (isset(self::$flushFor[$schoolId])) return;
        self::$flushFor[$schoolId] = true;
        register_shutdown_function(static function () use ($pdo, $schoolId) {
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();   // do not block this user's next request
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
            ignore_user_abort(true);
            try { self::processQueue($pdo, 10, $schoolId); }
            catch (\Throwable $e) { error_log('attendance notify flush: ' . $e->getMessage()); }
        });
    }

    /** Sends up to $limit due messages. Safe to run from several places at once. Returns how many were sent. */
    public static function processQueue(PDO $pdo, int $limit = 50, ?int $schoolId = null): int
    {
        require_once __DIR__ . '/notifier.php';
        $lockName = 'att_notify_' . ($schoolId ?? 'all');
        $got = (int)$pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockName) . ", 0)")->fetchColumn();
        if ($got !== 1) return 0;
        $sent = 0;
        try {
            // Rows stuck in 'sending' (worker crashed mid-send) go back to the queue.
            $pdo->exec("UPDATE attendance_notify_queue SET status='pending'
                        WHERE status='sending' AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");

            $sql = "SELECT id, school_id, event_type, payload_json, attempts FROM attendance_notify_queue
                    WHERE status='pending' AND available_at <= NOW()" . ($schoolId !== null ? " AND school_id=?" : "") . "
                    ORDER BY id LIMIT " . max(1, min(500, $limit));
            $q = $pdo->prepare($sql);
            $q->execute($schoolId !== null ? [$schoolId] : []);
            $claim = $pdo->prepare("UPDATE attendance_notify_queue SET status='sending', attempts=attempts+1, updated_at=NOW()
                                    WHERE id=? AND status='pending'");
            $ok    = $pdo->prepare("UPDATE attendance_notify_queue SET status='sent', sent_at=NOW(), updated_at=NOW(), last_error=NULL WHERE id=?");
            $fail  = $pdo->prepare("UPDATE attendance_notify_queue
                                    SET status = IF(attempts >= 5, 'failed', 'pending'),
                                        available_at = DATE_ADD(NOW(), INTERVAL (attempts * attempts) MINUTE),
                                        last_error = ?, updated_at = NOW()
                                    WHERE id=?");
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $job) {
                $claim->execute([$job['id']]);
                if ($claim->rowCount() !== 1) continue;
                try {
                    $data = json_decode((string)$job['payload_json'], true) ?: [];
                    $r = sendSchoolNotification((int)$job['school_id'], (string)$job['event_type'], $data);
                    if ($r === false) throw new \RuntimeException('notifier returned false (event not set up or gateway refused)');
                    $ok->execute([$job['id']]);
                    $sent++;
                } catch (\Throwable $e) {
                    $fail->execute([mb_substr($e->getMessage(), 0, 255), $job['id']]);
                }
            }
        } finally {
            $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")");
        }
        return $sent;
    }

    /* ───────────────────────── Consecutive absence alert ───────────────────────── */

    /**
     * For each student absent on $date: if they were absent on each of the last N working days
     * (ending on $date), queue one 'student_absent_6days' message (at most once per 7 days).
     * Called from manual marking, single edit and "mark remaining absent".
     */
    public static function checkConsecutiveAbsence(PDO $pdo, int $schoolId, array $studentIds, string $date): void
    {
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if (!$studentIds) return;
        $n = self::ABSENT_ALERT_DAYS;
        try {
            $days = self::schoolDaysBack($pdo, $schoolId, $date, $n, true);
            if (count($days) < $n) return;
            $from = end($days); $to = $days[0];

            $schoolName = null;
            foreach (array_chunk($studentIds, 500) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                $dp = implode(',', array_fill(0, $n, '?'));
                $a = $pdo->prepare("SELECT student_id, SUM(status='absent') AS ab, COUNT(*) AS cnt FROM attendance
                                    WHERE school_id=? AND student_id IN ($ph) AND date IN ($dp) GROUP BY student_id");
                $a->execute(array_merge([$schoolId], $chunk, $days));
                $hit = [];
                foreach ($a->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    if ((int)$r['ab'] >= $n && (int)$r['cnt'] === (int)$r['ab']) $hit[] = (int)$r['student_id'];
                }
                if (!$hit) continue;

                if ($schoolName === null) {
                    $sn = $pdo->prepare("SELECT school_name FROM schools WHERE id=?");
                    $sn->execute([$schoolId]);
                    $schoolName = (string)($sn->fetchColumn() ?: 'School');
                }
                $stu = $pdo->prepare("
                    SELECT s.name, s.guardian_phone, s.email,
                           CONCAT(c.class_name, COALESCE(CONCAT(' ', sec.section_name),'')) AS class_label
                    FROM students s
                    LEFT JOIN classes c    ON c.id = s.class_id
                    LEFT JOIN sections sec ON sec.id = s.section_id
                    WHERE s.id=? AND s.school_id=? AND s.status='active'");
                foreach ($hit as $sid) {
                    if (self::sentRecentlyViaOldLog($pdo, $schoolId, $sid)) continue;
                    $stu->execute([$sid, $schoolId]);
                    $s = $stu->fetch(PDO::FETCH_ASSOC);
                    if (!$s || (empty($s['guardian_phone']) && empty($s['email']))) continue;
                    self::queueNotify($pdo, $schoolId, 'student_absent_6days', [
                        'student_id'   => $sid,
                        'student_name' => $s['name'],
                        'class'        => $s['class_label'] ?? '',
                        'days'         => $n,
                        'from_date'    => date('d/m/Y', strtotime($from)),
                        'to_date'      => date('d/m/Y', strtotime($to)),
                        'phone'        => $s['guardian_phone'] ?? '',
                        'email'        => $s['email'] ?? '',
                        'school_name'  => $schoolName,
                    ], "absent{$n}:$sid", 7);
                }
            }
        } catch (\Throwable $e) {
            error_log('consecutive absence check: ' . $e->getMessage());
        }
    }

    /** Messages sent before the queue existed are only in school_notification_log. */
    private static function sentRecentlyViaOldLog(PDO $pdo, int $schoolId, int $sid): bool
    {
        try {
            $q = $pdo->prepare("
                SELECT 1 FROM school_notification_log l
                JOIN students s ON s.id = ? AND s.school_id = l.school_id
                WHERE l.school_id=? AND l.event_type='student_absent_6days'
                  AND l.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND ((s.guardian_phone <> '' AND l.recipient = s.guardian_phone) OR (s.email <> '' AND l.recipient = s.email))
                LIMIT 1");
            $q->execute([$sid, $schoolId]);
            return (bool)$q->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
