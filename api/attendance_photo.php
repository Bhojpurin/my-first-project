<?php
// api/attendance_photo.php — serves a student / staff photo only for a valid signed, unexpired link
// made by AttCore::photoUrl(). No login session needed (gate devices), no session lock.
//   ?sc=<school>&t=s|t&id=<id>&h=<photo hash>&e=<expiry>&s=<signature>
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/attendance_core.php';

function photoFail(int $code): void {
    http_response_code($code);
    header('Cache-Control: no-store');
    exit;
}

$sc  = (int)($_GET['sc'] ?? 0);
$t   = (string)($_GET['t'] ?? '');
$id  = (int)($_GET['id'] ?? 0);
$h   = (string)($_GET['h'] ?? '');
$exp = (int)($_GET['e'] ?? 0);
$sig = (string)($_GET['s'] ?? '');

if ($sc <= 0 || $id <= 0 || !in_array($t, ['s', 't'], true) || !preg_match('/^[0-9a-f]{8}$/', $h)
    || !preg_match('/^[0-9a-f]{24}$/', $sig)) photoFail(400);
if ($exp < time()) photoFail(410);

$pdo    = Database::connect();
$secret = AttCore::qrSecret($pdo, $sc, false);
if ($secret === '' || !hash_equals(AttCore::photoSig($secret, $sc, $t, $id, $h, $exp), $sig)) photoFail(403);

$q = $pdo->prepare($t === 's' ? "SELECT photo FROM students WHERE id=? AND school_id=?" : "SELECT photo FROM teachers WHERE id=? AND school_id=?");
$q->execute([$id, $sc]);
$photo = (string)$q->fetchColumn();
if ($photo === '' || substr(md5($photo), 0, 8) !== $h) photoFail(404);   // photo changed since the link was made

$dir  = realpath(__DIR__ . '/../assets/uploads/' . ($t === 's' ? 'photos' : 'teacher_photos'));
$file = $dir ? realpath($dir . '/' . basename($photo)) : false;
if (!$file || !str_starts_with($file, $dir . DIRECTORY_SEPARATOR) || !is_file($file)) photoFail(404);

$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (!isset($types[$ext])) photoFail(404);

header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=' . max(60, min(86400, $exp - time())));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'");
readfile($file);
