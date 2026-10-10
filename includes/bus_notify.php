<?php
// includes/bus_notify.php — ONE place for every bus notification, using the school's OWN channels only
// (no WhatsApp / SMS / third-party webhooks, so no school's data ever leaves the panel):
//
//   Parents (students): 1) Web Push to the phone (works with the app closed)
//                       2) an "Auto" message in the student portal's Messages tab (the panel's own messaging)
//   Admin:              the bus_alerts feed on the Live Map (with sound + browser notification while open)
//
// Every call is scoped to ONE school_id; recipients are filtered by that school_id in SQL, so a bug elsewhere
// can never deliver one school's alert to another school's parents.
//
// Messaging hook: the panel's messaging code lives outside this module. Create includes/bus_message_hook.php
// that defines   bus_send_school_message(PDO $pdo, int $schoolId, int $studentId, string $body, string $event): bool
// (insert an auto message from the school to that student). Until it exists, parents still get the push.

if (is_file(__DIR__ . '/bus_message_hook.php')) require_once __DIR__ . '/bus_message_hook.php';

/**
 * Notify the parents of these students (same school only). Returns how many students were reached.
 * $event is a short code shown as the auto-message label (e.g. "Bus ETA", "Bus school pahunchi").
 */
function busNotifyStudents(PDO $pdo, int $schoolId, array $studentIds, string $title, string $body, string $event, string $url = ''): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
    if (!$ids || $schoolId <= 0) return 0;
    $reached = [];

    // Only students that really belong to this school (defence in depth)
    $in = implode(',', array_fill(0, count($ids), '?'));
    $ok = $pdo->prepare("SELECT id FROM students WHERE school_id=? AND id IN ($in)");
    $ok->execute(array_merge([$schoolId], $ids));
    $ids = array_map('intval', $ok->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) return 0;
    $in = implode(',', array_fill(0, count($ids), '?'));

    // 1) Web Push
    try {
        require_once __DIR__ . '/push_sender.php';
        $subs = $pdo->prepare("SELECT id, student_id, endpoint, p256dh, auth FROM push_subscriptions WHERE school_id=? AND student_id IN ($in)");
        $subs->execute(array_merge([$schoolId], $ids));
        $link = $url !== '' ? $url : (defined('BASE_URL') ? BASE_URL : '') . '/student/index.php#bus';
        foreach ($subs->fetchAll() as $s) {
            try {
                $res = sendProximityPush(['endpoint' => $s['endpoint'], 'p256dh' => $s['p256dh'], 'auth' => $s['auth']],
                                         ['title' => $title, 'body' => $body, 'url' => $link]);
                if (!empty($res['expired'])) $pdo->prepare("DELETE FROM push_subscriptions WHERE id=? AND school_id=?")->execute([$s['id'], $schoolId]);
                else $reached[(int)$s['student_id']] = true;
            } catch (\Throwable $e) { error_log('bus_notify push: ' . $e->getMessage()); }
        }
    } catch (\Throwable $e) { error_log('bus_notify push setup: ' . $e->getMessage()); }

    // 2) The panel's own messaging (Messages tab → "Auto" message)
    if (function_exists('bus_send_school_message')) {
        foreach ($ids as $sid) {
            try { if (bus_send_school_message($pdo, $schoolId, $sid, $title . ' — ' . $body, $event)) $reached[$sid] = true; }
            catch (\Throwable $e) { error_log('bus_notify message: ' . $e->getMessage()); }
        }
    }
    return count($reached);
}

/**
 * Admin alert (Live Map feed). One row per event; $dedupeMin suppresses the same type for the same bus.
 * Types: overspeed | school_arrive | school_leave | deviation | silent | halt_report | halt | halt_resolved
 */
function busAdminAlert(PDO $pdo, int $schoolId, int $busId, ?int $tripId, string $type, string $message,
                       ?float $lat = null, ?float $lng = null, ?float $value = null, int $dedupeMin = 0, ?int $refId = null): ?int
{
    try {
        if ($dedupeMin > 0) {
            $d = $pdo->prepare("SELECT id FROM bus_alerts WHERE school_id=? AND bus_id=? AND type=? AND created_at > (NOW() - INTERVAL ? MINUTE) LIMIT 1");
            $d->execute([$schoolId, $busId, $type, $dedupeMin]);
            if ($d->fetchColumn()) return null;
        }
        if ($refId !== null) {
            $pdo->prepare("INSERT INTO bus_alerts (school_id, bus_id, trip_id, type, lat, lng, value, message, ref_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([$schoolId, $busId, $tripId, $type, $lat, $lng, $value, mb_substr($message, 0, 255), $refId]);
        } else {
            $pdo->prepare("INSERT INTO bus_alerts (school_id, bus_id, trip_id, type, lat, lng, value, message, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")
                ->execute([$schoolId, $busId, $tripId, $type, $lat, $lng, $value, mb_substr($message, 0, 255)]);
        }
        return (int)$pdo->lastInsertId();
    } catch (\Throwable $e) {
        error_log('bus_alert: ' . $e->getMessage());   // table missing → never break GPS ingestion
        return null;
    }
}
