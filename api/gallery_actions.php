<?php
// api/gallery_actions.php — School-shared Gallery CRUD API

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../includes/subscription_gate.php';

// requireLogin() below redirects (HTTP 302) on a dead session — correct for
// a normal page load, but this is a JSON/AJAX endpoint the offline queue
// (assets/js/offline-queue.js) can replay long after the session died.
// fetch() silently follows that redirect to the login PAGE's HTML, which
// then fails to parse as JSON and would be misclassified as an ordinary
// rejection instead of "please log in again" — short-circuit with a clean,
// distinguishable 401 JSON response first (mirrors api/data_collect.php's
// identical fix).
if (!isLoggedIn()) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Your session has expired. Please log in again.']);
    exit;
}
requireLogin(ROLE_SCHOOL_ADMIN, ROLE_TEACHER, ROLE_OPERATOR);

$pdo      = Database::connect();
$user     = currentUser();
$schoolId = (int)($user['school_id'] ?? 0);

// Operators have personal galleries scoped by user_id; school roles use school_id.
$isOperator = ($user['role'] === ROLE_OPERATOR);


// Add school_id column if table existed before this change

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
ob_start();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function jout(array $d): void {
    if (ob_get_level() > 0) ob_end_clean();
    // INVALID_UTF8_SUBSTITUTE: one bad byte in a design name used to make
    // json_encode() return false → empty body → "Network error" in the editor.
    $j = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    echo $j === false ? '{"success":false,"message":"Encoding error"}' : $j;
    exit;
}
/** Only a real base64 raster data-URI may be stored as a thumbnail — the value
 *  is later printed into <img src="…"> on gallery pages, so anything else
 *  (javascript:, an external URL, broken text) is dropped. */
function cleanThumb(string $t): string {
    if ($t === '' || strlen($t) > 700000) return '';
    return preg_match('#^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#', $t) ? $t : '';
}
function csrfOk(): bool { return isset($_POST['csrf_token']) && verifyCsrf($_POST['csrf_token']); }

switch ($action) {
    case 'save':   doSave($pdo, $user, $isOperator);   break;
    case 'delete': doDelete($pdo, $user, $isOperator); break;
    case 'list':   doList($pdo, $user, $isOperator);   break;
    case 'get':    doGet($pdo, $user, $isOperator);    break;
    case 'rename': doRename($pdo, $user, $isOperator); break;
    default:       jout(['success'=>false,'message'=>'Unknown action']);
}

/* ── Save / Update ───────────────────────────────────── */
function doSave(PDO $pdo, array $user, bool $isOperator): void {
    if (!csrfOk()) jout(['success'=>false,'message'=>'CSRF error']);
    requireActiveSubOrReject($pdo, $isOperator ? 'icard' : 'school', $isOperator ? (int)$user['id'] : (int)($user['school_id'] ?? 0));

    $schoolId   = $isOperator ? 0 : (int)($user['school_id'] ?? 0);
    $userId     = (int)$user['id'];
    $galleryId  = (int)($_POST['gallery_id']  ?? 0);
    $name       = mb_substr(trim($_POST['name'] ?? ''), 0, 120) ?: 'My Design';
    $canvasJson = $_POST['canvas_json']        ?? '';
    $thumb      = cleanThumb((string)($_POST['thumbnail_data'] ?? ''));
    $srcTplId   = (int)($_POST['source_template_id'] ?? 0) ?: null;
    // Logos can be smaller than 100 px; the editor allows up to 16000 px.
    $width      = min(16000, max(10, (int)($_POST['canvas_width']  ?? 856)));
    $height     = min(16000, max(10, (int)($_POST['canvas_height'] ?? 540)));
    $roleMap    = [ROLE_SUPER_ADMIN=>'admin', ROLE_SCHOOL_ADMIN=>'school', ROLE_TEACHER=>'teacher', ROLE_OPERATOR=>'operator'];
    $userType   = $roleMap[$user['role']] ?? 'teacher';

    if (!$canvasJson) jout(['success'=>false,'message'=>'Canvas data missing']);
    if (strlen($canvasJson) > 40 * 1024 * 1024) jout(['success'=>false,'message'=>'Design is too large to save (over 40 MB). Use smaller photos.']);
    $decoded = json_decode($canvasJson, true);
    if (!is_array($decoded) || !isset($decoded['objects']) || !is_array($decoded['objects'])) {
        jout(['success'=>false,'message'=>'Design data is damaged — not saved. Please try again.']);
    }

    try {
        if ($galleryId > 0) {
            // Operators: own by user_id (personal); school users: own by school_id (shared)
            $ownerWhere = $isOperator ? 'user_id=?' : 'school_id=?';
            $ownerVal   = $isOperator ? $userId : $schoolId;
            $s = $pdo->prepare("UPDATE user_gallery SET name=?,canvas_json=?,thumbnail=?,canvas_width=?,canvas_height=?,updated_at=NOW() WHERE id=? AND $ownerWhere");
            $s->execute([$name, $canvasJson, $thumb, $width, $height, $galleryId, $ownerVal]);
            // rowCount() is 0 both for "not yours / missing" and for "saved
            // identical data" — tell them apart instead of reporting a false error.
            if ($s->rowCount() === 0) {
                $chk = $pdo->prepare("SELECT 1 FROM user_gallery WHERE id=? AND $ownerWhere");
                $chk->execute([$galleryId, $ownerVal]);
                if (!$chk->fetchColumn()) jout(['success'=>false,'message'=>'Gallery item not found']);
            }
            logActivity($userId, 'gallery_updated', 'Design updated: ' . $name, $pdo);
            jout(['success'=>true,'gallery_id'=>$galleryId,'message'=>'Design updated in gallery!']);
        } else {
            $s = $pdo->prepare("INSERT INTO user_gallery (school_id,user_id,user_type,source_template_id,name,canvas_json,thumbnail,canvas_width,canvas_height) VALUES (?,?,?,?,?,?,?,?,?)");
            $s->execute([$schoolId,$userId,$userType,$srcTplId,$name,$canvasJson,$thumb,$width,$height]);
            $newId = (int)$pdo->lastInsertId();
            logActivity($userId, 'gallery_saved', 'New design saved: ' . $name, $pdo);
            jout(['success'=>true,'gallery_id'=>$newId,'message'=>'Saved to gallery!']);
        }
    } catch (\Throwable $e) {
        error_log('gallery_actions: '.$e->getMessage());
        jout(['success'=>false,'message'=>'Database error.']);
    }
}

/* ── Delete ──────────────────────────────────────────── */
function doDelete(PDO $pdo, array $user, bool $isOperator): void {
    if (!csrfOk()) jout(['success'=>false,'message'=>'CSRF error']);
    requireActiveSubOrReject($pdo, $isOperator ? 'icard' : 'school', $isOperator ? (int)$user['id'] : (int)($user['school_id'] ?? 0));
    $userId   = (int)$user['id'];
    $schoolId = $isOperator ? 0 : (int)($user['school_id'] ?? 0);
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) jout(['success'=>false,'message'=>'Invalid ID']);
    try {
        // Operators: own by user_id; school users: own by school_id
        $ownerWhere = $isOperator ? 'user_id=?' : 'school_id=?';
        $ownerVal   = $isOperator ? $userId : $schoolId;
        $nm = $pdo->prepare("SELECT name FROM user_gallery WHERE id=? AND $ownerWhere");
        $nm->execute([$id, $ownerVal]);
        $designName = $nm->fetchColumn() ?: "ID $id";
        $s = $pdo->prepare("DELETE FROM user_gallery WHERE id=? AND $ownerWhere");
        $s->execute([$id, $ownerVal]);
        logActivity($userId, 'gallery_deleted', 'Design deleted: ' . $designName, $pdo);
        jout(['success'=>true,'message'=>'Deleted from gallery']);
    } catch (\Throwable $e) {
        jout(['success'=>false,'message'=>'DB error']);
    }
}

/* ── List ────────────────────────────────────────────── */
function doList(PDO $pdo, array $user, bool $isOperator): void {
    $userId   = (int)$user['id'];
    $schoolId = $isOperator ? 0 : (int)($user['school_id'] ?? 0);
    $page     = max(0, (int)($_GET['page'] ?? 0));
    $limit    = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $offset   = $page * $limit;
    try {
        $whereClause = $isOperator ? 'g.user_id = ?' : 'g.school_id = ?';
        $whereVal    = $isOperator ? $userId : $schoolId;
        // ?kind=logo — only Logo Studio designs (they carry an "_lsp" block), so
        // "My Logos" no longer lists every ID-card design too (and vice versa).
        $kind = $_GET['kind'] ?? '';
        // (older logos predate the "_lsp" block but always contain "lspName")
        $isLogo = "(g.canvas_json LIKE '%\"_lsp\"%' OR g.canvas_json LIKE '%\"lspName\"%')";
        if ($kind === 'logo')   $whereClause .= " AND $isLogo";
        if ($kind === 'design') $whereClause .= " AND NOT $isLogo";
        $s = $pdo->prepare("
            SELECT g.id, g.name, g.thumbnail, g.canvas_width, g.canvas_height,
                   g.source_template_id, g.created_at, g.updated_at,
                   u.name AS created_by
            FROM user_gallery g
            LEFT JOIN users u ON u.id = g.user_id
            WHERE $whereClause
            ORDER BY g.updated_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $s->execute([$whereVal]);
        $items = $s->fetchAll(PDO::FETCH_ASSOC);
        jout(['success'=>true,'items'=>$items,'page'=>$page,'has_more'=>count($items)===$limit]);
    } catch (\Throwable $e) {
        jout(['success'=>false,'items'=>[]]);
    }
}

/* ── Get single ──────────────────────────────────────── */
function doGet(PDO $pdo, array $user, bool $isOperator): void {
    $userId   = (int)$user['id'];
    $schoolId = $isOperator ? 0 : (int)($user['school_id'] ?? 0);
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jout(['success'=>false,'message'=>'Invalid ID']);
    try {
        $ownerWhere = $isOperator ? 'user_id=?' : 'school_id=?';
        $ownerVal   = $isOperator ? $userId : $schoolId;
        $s = $pdo->prepare("SELECT * FROM user_gallery WHERE id=? AND $ownerWhere");
        $s->execute([$id, $ownerVal]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) jout(['success'=>false,'message'=>'Not found or access denied']);
        jout(['success'=>true,'item'=>$row]);
    } catch (\Throwable $e) {
        jout(['success'=>false,'message'=>'DB error']);
    }
}

/* ── Rename ──────────────────────────────────────────── */
function doRename(PDO $pdo, array $user, bool $isOperator): void {
    if (!csrfOk()) jout(['success'=>false,'message'=>'CSRF error']);
    requireActiveSubOrReject($pdo, $isOperator ? 'icard' : 'school', $isOperator ? (int)$user['id'] : (int)($user['school_id'] ?? 0));
    $userId   = (int)$user['id'];
    $schoolId = $isOperator ? 0 : (int)($user['school_id'] ?? 0);
    $id   = (int)($_POST['id']   ?? 0);
    $name = mb_substr(trim($_POST['name'] ?? ''), 0, 120) ?: 'My Design';
    if (!$id) jout(['success'=>false,'message'=>'Invalid ID']);
    try {
        $ownerWhere = $isOperator ? 'user_id=?' : 'school_id=?';
        $ownerVal   = $isOperator ? $userId : $schoolId;
        $chk = $pdo->prepare("SELECT 1 FROM user_gallery WHERE id=? AND $ownerWhere");
        $chk->execute([$id, $ownerVal]);
        if (!$chk->fetchColumn()) jout(['success'=>false,'message'=>'Not found or access denied']);
        $s = $pdo->prepare("UPDATE user_gallery SET name=? WHERE id=? AND $ownerWhere");
        $s->execute([$name, $id, $ownerVal]);
        logActivity($userId, 'gallery_renamed', 'Renamed to: ' . $name, $pdo);
        jout(['success'=>true,'message'=>'Renamed!']);
    } catch (\Throwable $e) {
        jout(['success'=>false,'message'=>'DB error']);
    }
}
