<?php
// includes/bus_message_hook.php — bus notifications → the panel's own messaging (student portal → Messages tab).
// Writes an auto message from the school to ONE student, exactly like the panel's other auto messages
// (school_messages with is_auto = 1 / auto_event), so it shows up as "Auto: <event>" with the unread badge.
// bus_notify.php has already verified that the student belongs to $schoolId.

// Which bus events also go into the Messages tab. Frequent "nearby" alerts stay push-only so the
// Messages tab does not fill up (the ETA message already says when the bus will come).
const BUS_MESSAGE_EVENTS = ['Bus trip shuru', 'Bus ETA', 'Bus school pahunchi', 'Bus school se nikli', 'Bus suchna'];

if (is_file(__DIR__ . '/school_message_helper.php')) require_once __DIR__ . '/school_message_helper.php';

function bus_send_school_message(PDO $pdo, int $schoolId, int $studentId, string $body, string $event): bool
{
    if (!in_array($event, BUS_MESSAGE_EVENTS, true) || $schoolId <= 0 || $studentId <= 0) return false;
    static $ready = null;
    if ($ready === null) {
        $ready = true;
        try { if (function_exists('msgEnsureTables')) msgEnsureTables($pdo); } catch (\Throwable $e) {}
    }
    try {
        $pdo->prepare("INSERT INTO school_messages
                         (school_id, sender_type, sender_id, recipient_type, recipient_id, body, image, is_auto, auto_event, created_at)
                       VALUES (?, 'school', 0, 'student', ?, ?, NULL, 1, ?, NOW())")
            ->execute([$schoolId, $studentId, mb_substr($body, 0, 2000), mb_substr($event, 0, 60)]);
        return true;
    } catch (\Throwable $e) {
        error_log('bus_message_hook: ' . $e->getMessage());
        return false;
    }
}
