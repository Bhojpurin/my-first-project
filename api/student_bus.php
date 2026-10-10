<?php
// api/student_bus.php — Student-authenticated bus actions
// Allows a logged-in student to save/retrieve their home location.

header('Content-Type: application/json; charset=utf-8'); // set first, so even the "not logged in" reply is JSON
header('Cache-Control: no-store');

require_once __DIR__ . '/../student/stu_guard.php';

if (!stuLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Not logged in']); exit;
}

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// A request made from another website (cross-site) must never change a student's home/alert settings
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Cross-site request blocked']); exit;
}

$action   = $_REQUEST['action'] ?? '';
$stu      = stuInfo();
$stuId    = (int)($stu['id']        ?? 0);
$schoolId = (int)($stu['school_id'] ?? 0);

if (!$stuId || !$schoolId) {
    echo json_encode(['success'=>false,'message'=>'Session error']); exit;
}

$pdo = Database::connect();


// ── set_home ──────────────────────────────────────────────────────────────────
if ($action === 'set_home') {
    $lat    = (float)($_POST['lat']    ?? 0);
    $lng    = (float)($_POST['lng']    ?? 0);
    $radius = max(100, min(2000, (int)($_POST['radius'] ?? 500)));

    if (abs($lat) > 90 || abs($lng) > 180 || ($lat === 0.0 && $lng === 0.0)) {
        echo json_encode(['success'=>false,'message'=>'Invalid location']); exit;
    }

    $pdo->prepare("
        INSERT INTO student_home_locations (student_id, school_id, lat, lng, alert_radius)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng),
            alert_radius=VALUES(alert_radius), in_radius_since=NULL, updated_at=NOW()
    ")->execute([$stuId, $schoolId, $lat, $lng, $radius]);

    echo json_encode(['success'=>true,'message'=>'Home location saved.']); exit;
}

// ── get_home ──────────────────────────────────────────────────────────────────
if ($action === 'get_home') {
    $s = $pdo->prepare("SELECT lat, lng, alert_radius, push_enabled, updated_at FROM student_home_locations WHERE student_id=? AND school_id=?");
    $s->execute([$stuId, $schoolId]);
    $row = $s->fetch();
    if ($row) {
        echo json_encode([
            'success'      => true,
            'lat'          => (float)$row['lat'],
            'lng'          => (float)$row['lng'],
            'radius'       => (int)$row['alert_radius'],
            'push_enabled' => (bool)$row['push_enabled'],
            'updated'      => $row['updated_at'],
        ]);
    } else {
        echo json_encode(['success'=>false,'msg'=>'Not set']);
    }
    exit;
}

// ── subscribe_push ───────────────────────────────────────────────────────────
// Saves the browser's PushSubscription so background proximity alerts can be
// sent even while the app is closed. Requires a home location to already exist.
if ($action === 'subscribe_push') {
    $endpoint = trim($_POST['endpoint'] ?? '');
    $p256dh   = trim($_POST['p256dh']   ?? '');
    $auth     = trim($_POST['auth']     ?? '');

    if (!$endpoint || !$p256dh || !$auth) {
        echo json_encode(['success'=>false,'message'=>'Invalid subscription data.']); exit;
    }

    $home = $pdo->prepare("SELECT student_id FROM student_home_locations WHERE student_id=? AND school_id=?");
    $home->execute([$stuId, $schoolId]);
    if (!$home->fetch()) {
        echo json_encode(['success'=>false,'message'=>'Please set your home location first.']); exit;
    }

    $endpointHash = hash('sha256', $endpoint);
    $pdo->prepare("
        INSERT INTO push_subscriptions (student_id, school_id, endpoint, endpoint_hash, p256dh, auth)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE student_id=VALUES(student_id), school_id=VALUES(school_id),
            p256dh=VALUES(p256dh), auth=VALUES(auth)
    ")->execute([$stuId, $schoolId, $endpoint, $endpointHash, $p256dh, $auth]);

    $pdo->prepare("
        UPDATE student_home_locations SET push_enabled=1, in_radius_since=NULL
        WHERE student_id=? AND school_id=?
    ")->execute([$stuId, $schoolId]);

    echo json_encode(['success'=>true,'message'=>'Background alerts enabled.']); exit;
}

// ── unsubscribe_push ─────────────────────────────────────────────────────────
if ($action === 'unsubscribe_push') {
    $endpoint = trim($_POST['endpoint'] ?? '');
    if ($endpoint) {
        $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash=? AND student_id=?")
            ->execute([hash('sha256', $endpoint), $stuId]);
    } else {
        // No endpoint available (e.g. permission was revoked) — drop everything for this student.
        $pdo->prepare("DELETE FROM push_subscriptions WHERE student_id=? AND school_id=?")
            ->execute([$stuId, $schoolId]);
    }
    $pdo->prepare("UPDATE student_home_locations SET push_enabled=0, in_radius_since=NULL WHERE student_id=? AND school_id=?")
        ->execute([$stuId, $schoolId]);

    echo json_encode(['success'=>true,'message'=>'Background alerts disabled.']); exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);