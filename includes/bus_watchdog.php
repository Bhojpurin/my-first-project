<?php
// includes/bus_watchdog.php — "silent bus" watchdog.
// If a bus is expected to be running (inside a shift window of an active route, on a running day)
// but has sent no location for BUS_WATCHDOG_STALE_SEC, an alert is opened ONCE and the admin is
// notified. It closes by itself as soon as the bus reports again or the shift window ends.
//
// Runs from:  tools/bus_watchdog_cron.php (every minute, works with nobody logged in)
//             and, throttled, from the admin live map (bus_actions.php) as a free backup.
// Notification: always error_log + the alert shows as a red banner on the admin map.
//               Define BUS_WATCHDOG_WEBHOOK in config/constants.php (any URL that accepts a JSON POST
//               {"text": "..."} — Telegram/WhatsApp/Slack relay, n8n, Zapier ...) for instant messages.

const BUS_WATCHDOG_TZ        = 'Asia/Kolkata';
const BUS_WATCHDOG_STALE_SEC = 300;   // no fix for 5 min while expected to run
const BUS_WATCHDOG_BEFORE_MIN = 10;   // window opens this long before a pickup/drop time...
const BUS_WATCHDOG_AFTER_MIN  = 75;   // ...and closes this long after it

/** Is "now" inside the run window of this active route assignment row (days + up to 5 shifts)? */
function busWatchdogExpected(array $a, ?DateTime $now = null): bool
{
    $now = $now ?: new DateTime('now', new DateTimeZone(BUS_WATCHDOG_TZ));
    $known = ['mon','tue','wed','thu','fri','sat','sun'];
    $days  = array_values(array_intersect(
        array_map(function ($d) { return strtolower(substr(trim($d), 0, 3)); }, explode(',', (string)($a['days'] ?? ''))),
        $known
    ));
    if ($days && !in_array(strtolower($now->format('D')), $days, true)) return false;

    $nowMin = (int)$now->format('H') * 60 + (int)$now->format('i');
    $shifts = max(1, min(5, (int)($a['shift_count'] ?? 1)));
    for ($n = 1; $n <= $shifts; $n++) {
        $sfx = $n === 1 ? '' : (string)$n;
        foreach (['pickup_time', 'drop_time'] as $base) {
            $t = $a[$base . $sfx] ?? null;
            if (!$t) continue;
            $p  = explode(':', (string)$t);
            $d  = $nowMin - ((int)$p[0] * 60 + (int)($p[1] ?? 0));
            if ($d >= -BUS_WATCHDOG_BEFORE_MIN && $d <= BUS_WATCHDOG_AFTER_MIN) return true;
        }
    }
    return false;   // no times configured → the watchdog stays quiet (cannot know when it should run)
}

function busWatchdogNotify(string $text): void
{
    error_log('bus_watchdog: ' . $text);
    if (!defined('BUS_WATCHDOG_WEBHOOK') || !BUS_WATCHDOG_WEBHOOK) return;
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 5, 'ignore_errors' => true,
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode(['text' => $text]),
    ]]);
    @file_get_contents(BUS_WATCHDOG_WEBHOOK, false, $ctx);
}

/**
 * One watchdog pass. $schoolId = 0 checks every school (cron), otherwise just that school.
 * Never throws. Returns ['opened'=>n, 'resolved'=>n].
 */
function runBusWatchdog(PDO $pdo, int $schoolId = 0): array
{
    $res = ['opened' => 0, 'resolved' => 0];
    try {
        $sql = "SELECT b.id AS bus_id, b.school_id, b.bus_name, b.bus_number,
                       a.days, a.shift_count,
                       a.pickup_time,  a.drop_time,  a.pickup_time2, a.drop_time2, a.pickup_time3,
                       a.drop_time3, a.pickup_time4, a.drop_time4, a.pickup_time5, a.drop_time5,
                       l.recorded_at AS last_seen,
                       TIMESTAMPDIFF(SECOND, l.recorded_at, NOW()) AS age
                FROM school_buses b
                JOIN bus_route_assignments a ON a.bus_id=b.id AND a.school_id=b.school_id AND a.status='active'
                LEFT JOIN bus_live l ON l.bus_id=b.id
                WHERE b.status='active'" . ($schoolId ? ' AND b.school_id=?' : '');
        $st = $pdo->prepare($sql);
        $st->execute($schoolId ? [$schoolId] : []);

        // A bus can have several routes: it is "expected" if ANY of them is in its window.
        $buses = [];
        foreach ($st->fetchAll() as $r) {
            $id = (int)$r['bus_id'];
            if (!isset($buses[$id])) { $buses[$id] = $r; $buses[$id]['expected'] = false; }
            if (busWatchdogExpected($r)) $buses[$id]['expected'] = true;
        }

        $open = $pdo->prepare("SELECT id FROM bus_watchdog_alerts WHERE bus_id=? AND resolved_at IS NULL LIMIT 1");
        foreach ($buses as $id => $b) {
            $stale = $b['age'] === null || (int)$b['age'] > BUS_WATCHDOG_STALE_SEC;
            $open->execute([$id]);
            $openId = $open->fetchColumn();

            if ($stale && $b['expected'] && !$openId) {
                $pdo->prepare("INSERT INTO bus_watchdog_alerts (school_id, bus_id, opened_at, last_seen_at) VALUES (?,?,NOW(),?)")
                    ->execute([(int)$b['school_id'], $id, $b['last_seen']]);
                $mins = $b['age'] === null ? null : (int)round($b['age'] / 60);
                busWatchdogNotify('🚌 ' . $b['bus_name'] . ' (' . $b['bus_number'] . ') is running but has sent no location '
                    . ($mins === null ? 'yet' : 'for ' . $mins . ' min') . '. Check the driver phone / GPS device.');
                $res['opened']++;
            } elseif ($openId && (!$stale || !$b['expected'])) {
                $why = !$stale ? 'bus_back' : 'window_ended';
                $pdo->prepare("UPDATE bus_watchdog_alerts SET resolved_at=NOW(), resolved_reason=? WHERE id=?")->execute([$why, $openId]);
                $res['resolved']++;
            }
        }
    } catch (\Throwable $e) {
        error_log('bus_watchdog: ' . $e->getMessage());   // e.g. tables not migrated yet
    }
    return $res;
}

/** Open (unresolved) alerts for the admin banner. */
function busWatchdogOpenAlerts(PDO $pdo, int $schoolId): array
{
    try {
        $st = $pdo->prepare("
            SELECT w.id, w.bus_id, b.bus_name, b.bus_number, w.opened_at, w.last_seen_at,
                   TIMESTAMPDIFF(MINUTE, COALESCE(w.last_seen_at, w.opened_at), NOW()) AS silent_min
            FROM bus_watchdog_alerts w JOIN school_buses b ON b.id=w.bus_id
            WHERE w.school_id=? AND w.resolved_at IS NULL ORDER BY w.opened_at");
        $st->execute([$schoolId]);
        return $st->fetchAll();
    } catch (\Throwable $e) { return []; }
}
