<?php
// includes/bus_halt.php — "why is the bus standing on the road?"
//
//  1. Driver phone: stopped ≥ halt_ask_min away from every stop / the school → popup asks the reason
//     (puncture, breakdown, jam …). The report becomes an admin alert with a "tell the parents" button.
//  2. Server backup: during a trip, standing ≥ halt_admin_min away from stops/school and the driver said
//     nothing → admin alert "stopped, no reason given" (works for GPS hardware too).
//  3. The bus moves again → admin alert "running again (stood N min)", also one click to the parents.
//  4. Admin broadcast: one click sends an editable message to every student of that trip's shift (push +
//     Messages tab), never to students marked absent, never outside the bus's school.

require_once __DIR__ . '/bus_notify.php';
require_once __DIR__ . '/bus_trips.php';

const BUS_HALT_REASONS = [
    'puncture'  => 'tyre puncture',
    'breakdown' => 'bus kharab hone',
    'traffic'   => 'traffic jam',
    'fuel'      => 'fuel / CNG',
    'road'      => 'raasta band hone',
    'police'    => 'checking',
    'other'     => 'ek samasya',
];
const BUS_HALT_LABELS = [   // short labels for the admin feed
    'puncture' => 'Tyre puncture', 'breakdown' => 'Bus kharab', 'traffic' => 'Traffic jam', 'fuel' => 'Fuel / CNG',
    'road' => 'Raasta band', 'police' => 'Checking', 'other' => 'Samasya',
];
const BUS_HALT_NEAR_STOP_M = 80;   // standing this close to a student's home is a normal stop, not a halt

function busHaltClean(string $t, int $max): string
{
    $t = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($t));
    return mb_substr(trim(preg_replace('/\s+/u', ' ', $t)), 0, $max);
}

/** Is this point a normal place to stand (a student's home of the running shift, or the school gate)? */
function busHaltIsNormalPlace(PDO $pdo, array $trip, float $lat, float $lng, ?array $settings = null): bool
{
    $list = busStopsForShift($pdo, (int)$trip['bus_id'], (int)$trip['school_id'], (int)$trip['shift_no']);
    foreach ($list['stops'] as $s) if (busTripDist($lat, $lng, $s['lat'], $s['lng']) <= BUS_HALT_NEAR_STOP_M) return true;
    if ($settings && $settings['school_lat'] !== null
        && busTripDist($lat, $lng, $settings['school_lat'], $settings['school_lng']) <= max(50, $settings['school_radius_m']) * 1.5) return true;
    return false;
}

/** Driver's report → stored + admin alert. Returns the alert id. */
function busHaltReport(PDO $pdo, array $bus, array $in): ?int
{
    $schoolId = (int)$bus['school_id']; $busId = (int)$bus['id'];
    $code  = array_key_exists($in['reason'] ?? '', BUS_HALT_REASONS) ? $in['reason'] : 'other';
    $text  = busHaltClean((string)($in['text'] ?? ''), 200);
    $delay = isset($in['delay']) && is_numeric($in['delay']) ? max(0, min(240, (int)$in['delay'])) : null;
    $lat   = is_numeric($in['lat'] ?? null) && abs((float)$in['lat']) <= 90 ? (float)$in['lat'] : null;
    $lng   = is_numeric($in['lng'] ?? null) && abs((float)$in['lng']) <= 180 ? (float)$in['lng'] : null;
    $halt  = max(0, min(86400, (int)($in['halt_sec'] ?? 0)));
    $trip  = busTripGetOpen($pdo, $busId);
    $tripId = $trip ? (int)$trip['id'] : null;

    $pdo->prepare("INSERT INTO bus_halt_reports (school_id, bus_id, trip_id, device_id, reason_code, reason_text, delay_min, lat, lng, halt_sec, created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,NOW())")
        ->execute([$schoolId, $busId, $tripId, $bus['device_id'] ?? null, $code, $text ?: null, $delay, $lat, $lng, $halt]);
    $rid = (int)$pdo->lastInsertId();
    busCacheSet('haltopen:' . $busId, 1, 3600);

    $why = $code === 'other' && $text !== '' ? $text : BUS_HALT_LABELS[$code] . ($text !== '' ? ' — ' . $text : '');
    $msg = '🛑 ' . $bus['bus_name'] . ' raaste mein ruki: ' . $why . ($delay ? ' · ~' . $delay . ' min der' : '') . ($halt >= 60 ? ' (' . round($halt / 60) . ' min se)' : '');
    return busAdminAlert($pdo, $schoolId, $busId, $tripId, 'halt_report', $msg, $lat, $lng, $delay, 0, $rid);
}

/** Server-side halt / resume detection. Called from busAlertCheck() on every live fix. */
function busHaltServerCheck(PDO $pdo, array $set, array $state, int $schoolId, int $busId, ?array $trip, string $label,
                            float $lat, float $lng, float $speedKmh): void
{
    // Resume: an open driver report and the bus is clearly driving again (flag in the cache → no query normally)
    if ($speedKmh >= 8) {
        if (busCacheGet('haltopen:' . $busId) === 0) return;
        $o = $pdo->prepare("SELECT id, TIMESTAMPDIFF(MINUTE, created_at, NOW()) + FLOOR(COALESCE(halt_sec,0)/60) AS stood FROM bus_halt_reports
                            WHERE bus_id=? AND school_id=? AND resolved_at IS NULL ORDER BY id DESC LIMIT 1");
        $o->execute([$busId, $schoolId]);
        $r = $o->fetch();
        busCacheSet('haltopen:' . $busId, $r ? 1 : 0, 3600);
        if ($r) {
            busCacheSet('haltopen:' . $busId, 0, 3600);
            $pdo->prepare("UPDATE bus_halt_reports SET resolved_at=NOW() WHERE bus_id=? AND school_id=? AND resolved_at IS NULL")->execute([$busId, $schoolId]);
            busAdminAlert($pdo, $schoolId, $busId, $trip ? (int)$trip['id'] : null, 'halt_resolved',
                '▶️ ' . $label . ' phir chal padi (lagbhag ' . max(1, (int)$r['stood']) . ' min ruki)', $lat, $lng, null, 0, (int)$r['id']);
        }
        return;
    }
    if (!$trip) return;
    // Long halt without an answer from the driver
    $ls = $pdo->prepare("SELECT still_since, TIMESTAMPDIFF(SECOND, still_since, NOW()) AS s FROM bus_live WHERE bus_id=? AND school_id=?");
    $ls->execute([$busId, $schoolId]);
    $live = $ls->fetch();
    if (!$live || $live['still_since'] === null || (int)$live['s'] < max(2, (int)$set['halt_admin_min']) * 60) return;
    if (($state['halt_alerted_for'] ?? null) === $live['still_since']) return;                  // this halt already reported
    $rep = $pdo->prepare("SELECT 1 FROM bus_halt_reports WHERE bus_id=? AND school_id=? AND resolved_at IS NULL AND created_at >= ? LIMIT 1");
    $rep->execute([$busId, $schoolId, $live['still_since']]);
    if ($rep->fetchColumn()) return;                                                             // the driver explained it
    if (busHaltIsNormalPlace($pdo, $trip, $lat, $lng, $set)) return;
    $pdo->prepare("UPDATE bus_alert_state SET halt_alerted_for=? WHERE bus_id=?")->execute([$live['still_since'], $busId]);
    busAdminAlert($pdo, $schoolId, $busId, (int)$trip['id'], 'halt',
        '⏸ ' . $label . ' ' . round($live['s'] / 60) . ' min se beech raaste mein ruki hai — driver ne kaaran nahi bataya', $lat, $lng, null);
}

/** Students to tell about a trip: that shift, without the ones marked absent. */
function busTripRecipients(PDO $pdo, array $trip): array
{
    $l = busStopsForShift($pdo, (int)$trip['bus_id'], (int)$trip['school_id'], (int)$trip['shift_no'], (int)$trip['id']);
    $ids = array_merge(array_column($l['stops'], 'id'), array_column($l['missing'], 'id'));
    $ab = $pdo->prepare("SELECT student_id FROM bus_trip_stops WHERE trip_id=? AND status='absent'");
    $ab->execute([(int)$trip['id']]);
    return array_values(array_diff(array_unique($ids), array_map('intval', $ab->fetchAll(PDO::FETCH_COLUMN))));
}

/** Suggested parent message for an alert (the admin can edit it before sending). */
function busBroadcastDefault(PDO $pdo, array $alert): string
{
    $b = $pdo->prepare("SELECT bus_name, bus_number FROM school_buses WHERE id=? AND school_id=?");
    $b->execute([(int)$alert['bus_id'], (int)$alert['school_id']]);
    $bus = $b->fetch() ?: ['bus_name' => 'Bus', 'bus_number' => ''];
    $name = $bus['bus_name'] . ($bus['bus_number'] ? ' (' . $bus['bus_number'] . ')' : '');
    if ($alert['type'] === 'halt_resolved') return "Update: $name phir se chal padi hai. Der ke liye khed hai.";
    if ($alert['type'] === 'halt_report' && $alert['ref_id']) {
        $r = $pdo->prepare("SELECT reason_code, reason_text, delay_min FROM bus_halt_reports WHERE id=? AND school_id=?");
        $r->execute([(int)$alert['ref_id'], (int)$alert['school_id']]);
        if ($h = $r->fetch()) {
            $why = $h['reason_code'] === 'other' && $h['reason_text'] ? $h['reason_text'] : (BUS_HALT_REASONS[$h['reason_code']] ?? 'ek samasya');
            return "Suchna: $name raaste mein $why ki wajah se ruki hai."
                . ($h['delay_min'] ? ' Lagbhag ' . (int)$h['delay_min'] . ' min ki der ho sakti hai.' : ' Thodi der ho sakti hai.')
                . ' School driver ke sampark mein hai, hum update dete rahenge.';
        }
    }
    return "Suchna: $name raaste mein kuch der ke liye ruki hui hai. School driver ke sampark mein hai, hum jald update denge.";
}

/**
 * Admin broadcast to the parents of one alert's trip (or the bus's running trip).
 * Returns ['ok'=>bool, 'msg'=>string, 'sent'=>n]. Everything is limited to $schoolId.
 */
function busBroadcast(PDO $pdo, int $schoolId, ?int $alertId, ?int $busId, string $text, bool $sendNow = true): array
{
    $text = busHaltClean($text, 500);
    if (mb_strlen($text) < 5) return ['ok' => false, 'msg' => 'Message bahut chhota hai.', 'sent' => 0];
    $trip = null; $alert = null;
    if ($alertId) {
        $a = $pdo->prepare("SELECT * FROM bus_alerts WHERE id=? AND school_id=?");
        $a->execute([$alertId, $schoolId]);
        $alert = $a->fetch();
        if (!$alert) return ['ok' => false, 'msg' => 'Alert nahi mila.', 'sent' => 0];
        if ($alert['broadcast_at'] && strtotime($alert['broadcast_at']) > time() - 60) return ['ok' => false, 'msg' => 'Abhi-abhi bheja gaya hai — 1 min baad dobara bhej sakte hain.', 'sent' => 0];
        $busId = (int)$alert['bus_id'];
        if ($alert['trip_id']) {
            $t = $pdo->prepare("SELECT * FROM bus_trips WHERE id=? AND school_id=?");
            $t->execute([(int)$alert['trip_id'], $schoolId]);
            $trip = $t->fetch() ?: null;
        }
    }
    if (!$trip && $busId) {
        $trip = busTripGetOpen($pdo, $busId);
        if ($trip && (int)$trip['school_id'] !== $schoolId) $trip = null;
    }
    if (!$trip) return ['ok' => false, 'msg' => 'Is bus ki koi trip nahi mili — kis shift ko bhejein, pata nahi.', 'sent' => 0];
    $ids = busTripRecipients($pdo, $trip);
    if (!$ids) return ['ok' => false, 'msg' => 'Is shift mein koi student nahi.', 'sent' => 0];
    if ($alert) $pdo->prepare("UPDATE bus_alerts SET broadcast_at=NOW(), broadcast_n=broadcast_n+1 WHERE id=? AND school_id=?")->execute([(int)$alert['id'], $schoolId]);
    if ($sendNow) busNotifyStudents($pdo, $schoolId, $ids, 'Bus suchna 🚌', $text, 'Bus suchna');
    return ['ok' => true, 'msg' => count($ids) . ' parents ko message bhej diya gaya.', 'sent' => count($ids), 'ids' => $ids, 'text' => $text];
}
