<?php
// icard/editor.php — Full Fabric.js Canvas Editor
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
// ROLE_TEACHER was missing here even though school/gallery.php's own "Re-edit
// this design" button (and gallery_actions.php's API) both already treat
// teachers as full gallery users — a teacher clicking that button hit a raw
// 403 Access Denied instead of opening their own design.
requireLogin(ROLE_SUPER_ADMIN, ROLE_OPERATOR, ROLE_SCHOOL_ADMIN, ROLE_TEACHER);

$user   = currentUser();
$tplId  = (int)($_GET['id']         ?? 0);
$galId  = (int)($_GET['gallery_id'] ?? 0);
$autoDownload = !empty($_GET['autodownload']);

$pdo = Database::connect();

// Ensure gallery table exists

$tpl = null;
$galleryItem = null;

if ($galId > 0) {
    // ── Load from user gallery ────────────────────────────
    try {
        $stmt = $pdo->prepare(
            "SELECT g.*, t.name AS tpl_name, c.name AS cat_name, c.icon AS cat_icon
             FROM user_gallery g
             LEFT JOIN icard_templates t ON t.id = g.source_template_id
             LEFT JOIN icard_template_categories c ON c.id = t.category_id
             WHERE g.id = ? AND g.user_id = ?"
        );
        $stmt->execute([$galId, $user['id']]);
        $galleryItem = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {}
    if (!$galleryItem) { header('Location: ' . BASE_URL . '/icard/gallery'); exit; }

    $editorData = [
        'id'          => 0,
        'gallery_id'  => $galId,
        'src_tpl_id'  => (int)($galleryItem['source_template_id'] ?? 0),
        'name'        => $galleryItem['name'],
        'width'       => (int)$galleryItem['canvas_width'],
        'height'      => (int)$galleryItem['canvas_height'],
        'canvas_json' => $galleryItem['canvas_json'] ?: null,
        'bg_image'    => null,
        'api_url'     => BASE_URL . '/api/template_actions.php',
        'gallery_api' => BASE_URL . '/api/gallery_actions.php',
        'csrf'        => csrfToken(),
        'back_url'    => in_array($user['role'], [ROLE_SCHOOL_ADMIN, ROLE_TEACHER], true)
                          ? BASE_URL . '/school/gallery'
                          : ($user['role'] === ROLE_SUPER_ADMIN
                              ? BASE_URL . '/admin/template-manager'
                              : BASE_URL . '/icard/gallery'),
        'is_admin'    => ($user['role'] === ROLE_SUPER_ADMIN),
        'from_gallery'=> true,
        'auto_download'=> $autoDownload,
        'category'    => $galleryItem['cat_name'] ?? 'My Gallery',
        'cat_icon'    => $galleryItem['cat_icon'] ?? 'bi-images',
    ];
    // Fake $tpl for template name input
    $tpl = ['name'=>$galleryItem['name'], 'cat_name'=>$galleryItem['cat_name']??'My Gallery',
            'cat_icon'=>$galleryItem['cat_icon']??'bi-images',
            'canvas_width'=>(int)$galleryItem['canvas_width'],
            'canvas_height'=>(int)$galleryItem['canvas_height']];

} elseif ($tplId > 0) {
    // ── Load from main template ───────────────────────────
    try {
        $stmt = $pdo->prepare("SELECT t.*, c.name AS cat_name, c.icon AS cat_icon
                               FROM icard_templates t
                               JOIN icard_template_categories c ON c.id=t.category_id
                               WHERE t.id=?");
        $stmt->execute([$tplId]);
        $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {}
    if (!$tpl) { header('Location: ' . BASE_URL . '/icard/templates'); exit; }

    $editorData = [
        'id'          => (int)$tpl['id'],
        'gallery_id'  => 0,
        'src_tpl_id'  => (int)$tpl['id'],
        'name'        => $tpl['name'],
        'width'       => (int)$tpl['canvas_width'],
        'height'      => (int)$tpl['canvas_height'],
        'canvas_json' => $tpl['canvas_json'] ?: null,
        'bg_image'    => $tpl['bg_image'] ? BASE_URL . '/assets/uploads/templates/' . $tpl['bg_image'] : null,
        'api_url'     => BASE_URL . '/api/template_actions.php',
        'gallery_api' => BASE_URL . '/api/gallery_actions.php',
        'csrf'        => csrfToken(),
        'back_url'    => in_array($user['role'], [ROLE_SCHOOL_ADMIN, ROLE_TEACHER], true)
                          ? BASE_URL . '/school/templates'
                          : ($user['role'] === ROLE_SUPER_ADMIN
                              ? BASE_URL . '/admin/template-manager'
                              : BASE_URL . '/icard/templates'),
        'is_admin'    => ($user['role'] === ROLE_SUPER_ADMIN),
        'from_gallery'=> false,
        'auto_download'=> false,
        'category'    => $tpl['cat_name'],
        'cat_icon'    => $tpl['cat_icon'],
    ];
} else {
    header('Location: ' . BASE_URL . '/icard/templates'); exit;
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($tpl['name']) ?> — Editor · SSZone</title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">
<link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;700&family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Lato:wght@400;700&family=Montserrat:wght@400;700&family=Poppins:wght@400;700&family=Playfair+Display:wght@400;700&family=Dancing+Script:wght@700&family=Baloo+2:wght@400;700&family=Noto+Sans+Devanagari:wght@400;700&family=Hind:wght@400;700&family=Mukta:wght@400;700&family=Kalam:wght@400;700&family=Rozha+One&family=Tiro+Devanagari+Hindi&family=Yatra+One&family=Oswald:wght@400;700&family=Bebas+Neue&family=Cinzel:wght@400;700&family=Merriweather:wght@400;700&family=Raleway:wght@400;700&family=Great+Vibes&family=Pacifico&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; overflow: hidden; font-family: 'Segoe UI', system-ui, sans-serif; background: #0f172a; color: #e2e8f0; }

/* ── Layout ──────────────────────────────────────── */
.ed-wrap   { display:flex; flex-direction:column; height:100vh; }
.ed-topbar { height:52px; background:#1e293b; border-bottom:1px solid #334155; display:flex; align-items:center; padding:0 .75rem; gap:.5rem; flex-shrink:0; z-index:100; }
.ed-body   { display:flex; flex:1; min-height:0; }

/* ── Topbar ──────────────────────────────────────── */
.ed-top-left  { display:flex; align-items:center; gap:.5rem; flex:1; min-width:0; }
.ed-top-right { display:flex; align-items:center; gap:.4rem; flex-shrink:0; }
.ed-divider   { width:1px; height:24px; background:#334155; margin:0 .25rem; flex-shrink:0; }

.ed-back {
  display:inline-flex; align-items:center; gap:.35rem;
  color:#94a3b8; text-decoration:none; font-size:.82rem; padding:.35rem .6rem;
  border-radius:7px; border:1px solid #334155; white-space:nowrap;
  transition:color .15s, border-color .15s;
}
.ed-back:hover { color:#e2e8f0; border-color:#475569; }

.tpl-name-input {
  background:transparent; border:1px solid transparent; border-radius:7px;
  color:#e2e8f0; font-size:.9rem; font-weight:600; padding:.3rem .6rem;
  max-width:220px; outline:none; min-width:0;
  transition:border-color .15s, background .15s;
}
.tpl-name-input:hover  { border-color:#334155; background:#334155; }
.tpl-name-input:focus  { border-color:#6366f1; background:#1e293b; }

.ed-cat-tag { font-size:.7rem; background:#312e81; color:#a5b4fc; padding:2px 9px; border-radius:999px; white-space:nowrap; flex-shrink:0; }
.ed-size-tag { font-size:.72rem; color:#64748b; white-space:nowrap; flex-shrink:0; }

/* Topbar buttons */
.ed-btn {
  display:inline-flex; align-items:center; gap:.35rem;
  padding:.35rem .7rem; border-radius:7px; border:1px solid #334155;
  background:transparent; color:#94a3b8; font-size:.8rem; cursor:pointer;
  transition:all .15s; white-space:nowrap;
}
.ed-btn:hover:not(:disabled)  { background:#334155; color:#e2e8f0; border-color:#475569; }
.ed-btn:disabled { opacity:.4; cursor:not-allowed; }
.ed-btn.primary  { background:#4f46e5; border-color:#4f46e5; color:#fff; font-weight:600; }
.ed-btn.primary:hover:not(:disabled) { background:#4338ca; border-color:#4338ca; }
.ed-btn.success  { background:#16a34a; border-color:#16a34a; color:#fff; font-weight:600; }
.ed-btn.success:hover:not(:disabled) { background:#15803d; }
.zoom-select {
  background:#1e293b; border:1px solid #334155; color:#94a3b8; border-radius:7px;
  padding:.3rem .5rem; font-size:.78rem; cursor:pointer; outline:none;
}

/* ── Tools Panel ─────────────────────────────────── */
.ed-tools {
  width:56px; background:#0f172a; border-right:1px solid #1e293b;
  display:flex; flex-direction:column; align-items:center;
  padding:.5rem 0; gap:.15rem; flex-shrink:0; overflow-y:auto;
}
.tool-btn {
  width:40px; height:40px; display:flex; align-items:center; justify-content:center;
  border-radius:9px; border:1px solid transparent; background:transparent;
  color:#64748b; cursor:pointer; font-size:1rem; transition:all .15s;
  position:relative;
}
.tool-btn:hover { background:#1e293b; color:#e2e8f0; border-color:#334155; }
.tool-btn.active { background:#4f46e5; color:#fff; border-color:#4f46e5; }
.tool-btn.danger:hover { background:#7f1d1d; color:#fca5a5; border-color:#991b1b; }
.tool-sep { width:32px; height:1px; background:#1e293b; margin:.3rem 0; flex-shrink:0; }

/* ── Colorful Tool Button Variants ───────── */
.tool-btn.c-text  { color:#60a5fa; }
.tool-btn.c-shape { color:#a78bfa; }
.tool-btn.c-image { color:#34d399; }
.tool-btn.c-draw  { color:#fb923c; }
.tool-btn.c-pick  { color:#fbbf24; }
.tool-btn.c-util  { color:#e879f9; }
/* on hover/active restore readable white — must come after .c-* rules */
.tool-btn[class*="c-"]:hover { color:#e2e8f0 !important; }
.tool-btn[class*="c-"].active { color:#fff !important; }

/* ── Gradient Pro ─────────────────────────── */
.grad-preview { height:36px; border-radius:8px; border:1px solid #334155; margin-bottom:.5rem; transition:background .2s; }
.grad-dir-btn { flex:1; padding:.28rem .15rem; border-radius:5px; border:1px solid #334155; background:#0f172a; color:#94a3b8; font-size:.65rem; cursor:pointer; text-align:center; transition:all .12s; }
.grad-dir-btn:hover { background:#1e293b; color:#e2e8f0; }
.grad-dir-btn.active { background:#312e81; border-color:#6366f1; color:#a5b4fc; }
.tool-tooltip { display:none; } /* JS-driven global tooltip replaces CSS approach */

/* ── Global tooltip (JS) ─────────────────── */
#g-tip {
  position:fixed; background:#0f172a; color:#e2e8f0; font-size:.72rem;
  padding:.3rem .65rem; border-radius:7px; white-space:nowrap;
  border:1px solid #334155; pointer-events:none; z-index:9990; display:none;
  box-shadow:0 4px 16px rgba(0,0,0,.5);
}

/* ── Eyedropper active ───────────────────── */
body.eyedrop-mode { cursor:crosshair !important; }
body.eyedrop-mode * { cursor:crosshair !important; }

/* ── Eraser mode ─────────────────────────── */
body.eraser-mode #fabricCanvas { outline:2px dashed #ef4444 !important; }
body.eraser-mode { cursor:crosshair !important; }
body.eraser-mode * { cursor:crosshair !important; }
.eraser-cursor {
  position:fixed; pointer-events:none; border-radius:50%;
  border:2px solid #ef4444; background:rgba(239,68,68,.15);
  transform:translate(-50%,-50%); z-index:9999; display:none;
}
body.eraser-mode .eraser-cursor { display:block; }
#bTypeEraser { color:#f87171 !important; }
#bTypeEraser.active { background:#7f1d1d !important; border-color:#ef4444 !important; color:#fca5a5 !important; }
#eraserModeRow { display:none; margin-top:.4rem; }
.eraser-mode-btn {
  flex:1; padding:.28rem .2rem; border-radius:5px; border:1px solid #334155;
  background:#0f172a; color:#94a3b8; font-size:.68rem; cursor:pointer; text-align:center;
}
.eraser-mode-btn.active { background:#7f1d1d; border-color:#ef4444; color:#fca5a5; }
.tEye-color {
  width:20px; height:20px; border-radius:50%; border:2px solid #334155;
  display:inline-block; vertical-align:middle; margin-left:.4rem;
  cursor:pointer; flex-shrink:0;
}

/* ── Sticker Modal ───────────────────────── */
.sticker-modal-inner {
  background:#1e293b; border:1px solid #334155; border-radius:16px;
  width:720px; max-width:98vw; max-height:88vh;
  display:flex; flex-direction:column; overflow:hidden;
}
.sticker-cat-btn {
  display:block; width:100%; text-align:left; padding:.45rem .65rem;
  background:transparent; border:none; border-radius:7px; color:#94a3b8;
  font-size:.78rem; cursor:pointer; transition:all .13s;
}
.sticker-cat-btn:hover  { background:#334155; color:#e2e8f0; }
.sticker-cat-btn.active { background:#4f46e5; color:#fff; }
.sticker-thumb {
  aspect-ratio:1; object-fit:contain; background:#0f172a; padding:4px;
  border-radius:8px; border:1.5px solid #334155; cursor:pointer;
  transition:all .15s; width:100%;
}
.sticker-thumb:hover { border-color:#6366f1; transform:scale(1.06); box-shadow:0 4px 14px rgba(99,102,241,.35); }

/* ── Canvas Area ─────────────────────────────────── */
.ed-canvas-area {
  /* justify-content:flex-start + margin:auto on the wrap: centring with
     justify-content:center made the left part of a canvas wider than the
     screen unreachable (it overflowed to the left, where you can't scroll). */
  flex:1; overflow:auto; background:#374151;
  display:flex; align-items:flex-start; justify-content:flex-start;
  padding:2rem;
  background-image: radial-gradient(circle, #475569 1px, transparent 1px);
  background-size: 24px 24px;
}
.canvas-shadow { box-shadow:0 8px 48px rgba(0,0,0,.6), 0 2px 8px rgba(0,0,0,.4); display:inline-block; margin:0 auto; flex-shrink:0; }
.canvas-shadow.cmyk-sim { filter:saturate(.82) contrast(.96) brightness(.98); }
.ed-canvas-area.panning, .ed-canvas-area.panning * { cursor:grabbing !important; }
.layer-item.drag-over { box-shadow:inset 0 2px 0 #6366f1; }
.layer-item[draggable="true"] { cursor:grab; }
#edDirtyDot { width:8px; height:8px; border-radius:50%; background:#f59e0b; display:none; box-shadow:0 0 8px #f59e0b; flex-shrink:0; }
#edDirtyDot.show { display:inline-block; }
.align-row { display:flex; flex-wrap:wrap; gap:.3rem; margin-top:.4rem; }
.align-row .align-btn { width:30px; height:28px; }

/* ── Properties Panel ────────────────────────────── */
.ed-props {
  width:270px; background:#1e293b; border-left:1px solid #334155;
  overflow-y:auto; flex-shrink:0; scrollbar-width:thin; scrollbar-color:#334155 transparent;
}
.ed-props::-webkit-scrollbar { width:4px; }
.ed-props::-webkit-scrollbar-thumb { background:#334155; border-radius:4px; }

.props-section { border-bottom:1px solid #334155; padding:.85rem 1rem; }
.props-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#64748b; margin-bottom:.75rem; }
.props-row { display:flex; align-items:center; gap:.5rem; margin-bottom:.55rem; font-size:.8rem; }
.props-row label { color:#94a3b8; flex-shrink:0; min-width:70px; font-size:.78rem; }
.props-row span  { color:#e2e8f0; font-weight:600; }
.props-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin-bottom:.6rem; }
.props-label { font-size:.68rem; color:#64748b; margin-bottom:.2rem; display:block; }
.props-input {
  width:100%; background:#0f172a; border:1px solid #334155; border-radius:6px;
  color:#e2e8f0; font-size:.8rem; padding:.3rem .5rem; outline:none;
  transition:border-color .15s;
}
.props-input:focus { border-color:#6366f1; }
.props-select { width:100%; }
input[type="color"] { width:32px; height:28px; border:1px solid #334155; border-radius:6px; cursor:pointer; background:none; padding:1px 2px; }
input[type="range"] { flex:1; accent-color:#6366f1; }

/* Style toggle buttons */
.style-btns { display:flex; gap:.35rem; }
.style-btn {
  width:30px; height:28px; background:#0f172a; border:1px solid #334155;
  border-radius:6px; color:#94a3b8; font-size:.82rem; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  transition:all .15s;
}
.style-btn:hover  { background:#334155; color:#e2e8f0; }
.style-btn.active { background:#4f46e5; border-color:#4f46e5; color:#fff; }

/* Alignment buttons */
.align-btns { display:flex; gap:.35rem; }
.align-btn {
  width:30px; height:28px; background:#0f172a; border:1px solid #334155;
  border-radius:6px; color:#94a3b8; font-size:.9rem; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  transition:all .15s;
}
.align-btn:hover { background:#334155; color:#e2e8f0; }

/* ── Layers Panel ────────────────────────────────── */
.layer-item {
  display:flex; align-items:center; gap:.5rem;
  padding:.4rem .7rem; border-radius:6px; cursor:pointer;
  font-size:.78rem; color:#94a3b8; transition:background .12s;
  margin-bottom:.1rem;
}
.layer-item:hover  { background:#0f172a; color:#e2e8f0; }
.layer-item.sel    { background:#312e81; color:#a5b4fc; }
.layer-item i      { font-size:.85rem; flex-shrink:0; }
.layer-item .lname { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.layer-vis { color:#475569; font-size:.85rem; flex-shrink:0; cursor:pointer; }
.layer-vis:hover { color:#e2e8f0; }
.layer-item.locked { opacity:.7; }
.layer-item.locked:hover { background:#0f172a; cursor:not-allowed; }

/* ── Toast ───────────────────────────────────────── */
.ed-toast {
  position:fixed; bottom:1.5rem; left:50%; transform:translateX(-50%) translateY(80px);
  background:#22c55e; color:#fff; font-size:.85rem; font-weight:600;
  padding:.6rem 1.4rem; border-radius:999px;
  box-shadow:0 4px 20px rgba(0,0,0,.3);
  transition:transform .25s ease; z-index:9999; pointer-events:none;
}
.ed-toast.show { transform:translateX(-50%) translateY(0); }
.ed-toast.err  { background:#ef4444; }
.ed-toast.info { background:#3b82f6; }

/* ── Brush Floating Bar ─────────────────── */
.brush-bar {
  display:none; position:absolute; left:64px; top:50%; transform:translateY(-50%);
  background:#1e293b; border:1px solid #334155; border-radius:12px;
  padding:.65rem .75rem; z-index:400; box-shadow:0 8px 32px rgba(0,0,0,.5);
  flex-direction:column; gap:.5rem; min-width:170px;
}
.brush-bar.open { display:flex; }
.brush-row { display:flex; align-items:center; gap:.5rem; font-size:.76rem; color:#94a3b8; }
.brush-row label { min-width:52px; flex-shrink:0; }
.brush-type-btn {
  width:34px; height:34px; border-radius:8px; border:1px solid #334155;
  background:#0f172a; color:#64748b; cursor:pointer; display:flex;
  align-items:center; justify-content:center; font-size:.85rem; transition:all .12s;
}
.brush-type-btn:hover  { background:#334155; color:#e2e8f0; }
.brush-type-btn.active { background:#4f46e5; border-color:#4f46e5; color:#fff; }

/* ── Warp Cards — Enhanced ───────────────── */
.warp-types { display:grid; grid-template-columns:repeat(2,1fr); gap:.4rem; margin:.5rem 0; }
.warp-card {
  background:#0f172a; border:2px solid #334155; border-radius:10px;
  padding:.5rem .3rem; text-align:center; cursor:pointer;
  font-size:.68rem; color:#94a3b8; transition:all .15s;
  display:flex; flex-direction:column; align-items:center;
}
.warp-card:hover { border-color:#6366f1; color:#e2e8f0; background:#1e293b; }
.warp-card.sel   { border-color:#4f46e5; background:#312e81; color:#a5b4fc; box-shadow:0 0 12px rgba(79,70,229,.3); }
.warp-card svg   { margin-bottom:.15rem; }

/* ── Gradient ───────────────────────────── */
.grad-preview { height:24px; border-radius:6px; border:1px solid #334155; margin-bottom:.5rem; }
.align-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.3rem; }
.align-grid .align-btn { font-size:.75rem; height:28px; }

/* ── Tool Flyout ────────────────────────── */
.tool-flyout {
  display:none; position:fixed; left:60px;
  background:#1e293b; border:1px solid #334155; border-radius:14px;
  padding:.9rem; z-index:600; box-shadow:0 12px 40px rgba(0,0,0,.6);
  min-width:200px;
}
.tool-flyout.open { display:block; }
.flyout-title { font-size:.68rem; font-weight:700; text-transform:uppercase;
  letter-spacing:.08em; color:#64748b; margin-bottom:.65rem; }
.flyout-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.4rem; }
.flyout-item {
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  gap:.3rem; padding:.55rem .3rem; border-radius:9px; cursor:pointer;
  border:1px solid #334155; background:#0f172a; color:#94a3b8;
  font-size:.65rem; font-weight:600; text-align:center;
  transition:all .13s;
}
.flyout-item:hover { background:#334155; color:#e2e8f0; border-color:#475569; }
.flyout-item i { font-size:1.15rem; }
.flyout-item.active { background:#4f46e5; border-color:#4f46e5; color:#fff; }
.flyout-sep { height:1px; background:#334155; margin:.5rem 0; }
.tool-btn.has-drop::after {
  content:''; position:absolute; bottom:3px; right:3px;
  width:0; height:0;
  border-left:4px solid transparent; border-right:0;
  border-top:4px solid #64748b;
}

/* ── Crop mode indicator ────────────────── */
body.crop-mode .canvas-shadow { outline:2px dashed #f59e0b; outline-offset:2px; }
#btnApplyCrop { animation: pulse-btn .9s ease-in-out infinite alternate; }
@keyframes pulse-btn { from { box-shadow:0 0 0 rgba(34,197,94,0); } to { box-shadow:0 0 8px rgba(34,197,94,.6); } }

/* ── Text Effects Buttons ────────────────── */
.text-fx-btns { display:flex; flex-wrap:wrap; gap:.3rem; margin-top:.35rem; }
.text-fx-btn {
  padding:.22rem .45rem; border-radius:6px; border:1px solid #334155;
  background:#0f172a; color:#94a3b8; font-size:.7rem; cursor:pointer;
  transition:all .12s; white-space:nowrap;
}
.text-fx-btn:hover  { background:#334155; color:#e2e8f0; }
.text-fx-btn.danger { color:#f87171; }
.text-fx-btn.danger:hover { background:#7f1d1d; color:#fca5a5; border-color:#991b1b; }

/* ── Clip Mask Buttons ───────────────────── */
.clip-btns { display:grid; grid-template-columns:repeat(2,1fr); gap:.35rem; margin-top:.3rem; }
.clip-btn {
  padding:.3rem .4rem; border-radius:6px; border:1px solid #334155;
  background:#0f172a; color:#94a3b8; font-size:.72rem; cursor:pointer;
  text-align:center; transition:all .13s;
}
.clip-btn:hover { background:#334155; color:#e2e8f0; }
.clip-btn.danger { color:#f87171; }
.clip-btn.danger:hover { background:#7f1d1d; color:#fca5a5; border-color:#991b1b; }

/* ── Spot Color Chips ────────────────────── */
.spot-color-chip {
  display:inline-flex; align-items:center; gap:.3rem;
  padding:.2rem .45rem; border-radius:5px; border:1px solid #334155;
  font-size:.65rem; cursor:pointer; color:#e2e8f0; font-weight:600;
  transition:all .12s;
}
.spot-color-chip:hover { opacity:.85; border-color:#64748b; }

/* ── Print Guides active state ───────────── */
#btnPrintGuides.active { background:#f59e0b; border-color:#f59e0b; color:#000; }

/* ── Mockup type buttons ─────────────────── */
.mockup-type-btn.active { background:#4f46e5; border-color:#4f46e5; color:#fff; }

/* ── New Canvas Modal buttons ───────────── */
.nc-preset-btn {
  padding:.3rem .2rem; border-radius:7px; border:1px solid #334155;
  background:#0f172a; color:#94a3b8; font-size:.68rem; cursor:pointer;
  text-align:center; transition:all .12s; white-space:nowrap;
}
.nc-preset-btn:hover { background:#1e293b; color:#e2e8f0; border-color:#6366f1; }
.nc-dpi-btn {
  padding:.35rem .2rem; border-radius:7px; border:1px solid #334155;
  background:#0f172a; color:#94a3b8; font-size:.7rem; cursor:pointer;
  text-align:center; transition:all .12s; line-height:1.3;
}
.nc-dpi-btn:hover { background:#1e293b; color:#e2e8f0; }
.nc-dpi-btn.active { background:#064e3b; border-color:#059669; color:#6ee7b7; }

/* ════════════════════════════════════════════
   3D TEXT STUDIO
════════════════════════════════════════════ */
.fx3d-cat {
  padding:.2rem .55rem; border-radius:999px; border:1px solid #334155;
  background:#0f172a; color:#64748b; font-size:.7rem; cursor:pointer;
  transition:all .13s; white-space:nowrap;
}
.fx3d-cat:hover  { background:#1e293b; color:#e2e8f0; }
.fx3d-cat.active { background:#4f46e5; border-color:#4f46e5; color:#fff; }

.fx3d-effect-card {
  background:#0a0f1a; border:1.5px solid #1e293b; border-radius:9px;
  padding:.25rem .2rem .22rem; text-align:center; cursor:pointer;
  font-size:.58rem; color:#64748b; transition:all .14s;
  display:flex; flex-direction:column; align-items:center; gap:.12rem;
  position:relative; overflow:hidden;
}
.fx3d-effect-card:hover  { border-color:#6366f1; color:#e2e8f0; transform:translateY(-1px); box-shadow:0 3px 12px rgba(99,102,241,.25); }
.fx3d-effect-card.sel    { border-color:#a855f7; background:#120a20; color:#c4b5fd; box-shadow:0 0 12px rgba(168,85,247,.35); }
.fx3d-effect-card canvas { border-radius:6px; width:100%; display:block; max-height:52px; object-fit:cover; }
.fx3d-effect-card > .fx3d-lbl { font-size:.58rem; color:inherit; line-height:1.1; padding:0 .1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
.fx3d-badge {
  position:absolute; top:3px; right:3px;
  font-size:.5rem; font-weight:800; padding:1px 4px; border-radius:4px;
  text-transform:uppercase; letter-spacing:.06em;
}
.fx3d-badge.pro    { background:#4f46e5; color:#fff; }
.fx3d-badge.new    { background:#22c55e; color:#fff; }
.fx3d-badge.hot    { background:#f43f5e; color:#fff; }

.s3d-ctrl-block {
  background:#0f172a; border:1px solid #1e293b; border-radius:9px;
  padding:.55rem .65rem; margin-bottom:.4rem;
}
.s3d-ctrl-label {
  font-size:.67rem; font-weight:700; text-transform:uppercase;
  letter-spacing:.07em; color:#475569; margin-bottom:.4rem;
}
.s3d-swatch {
  width:20px; height:20px; border-radius:5px; cursor:pointer;
  border:2px solid transparent; transition:all .12s; flex-shrink:0;
}
.s3d-swatch:hover { border-color:#fff; transform:scale(1.2); }
.s3d-swatch-grad {
  width:20px; height:20px; border-radius:5px; cursor:pointer;
  background:linear-gradient(135deg,#f43f5e,#a855f7); font-size:.6rem;
  display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;
  border:2px solid transparent; transition:all .12s;
}
.s3d-swatch-grad:hover { transform:scale(1.2); }

/* ── Pattern Fill ─────────────────────────────────── */
.pat-type-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:.35rem; margin-bottom:.5rem; }
.pat-type-btn {
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  gap:.22rem; padding:.4rem .2rem; border-radius:8px; border:1.5px solid #334155;
  background:#0f172a; color:#64748b; font-size:.62rem; cursor:pointer;
  transition:all .13s; text-align:center;
}
.pat-type-btn svg { display:block; }
.pat-type-btn:hover { border-color:#6366f1; color:#e2e8f0; background:#1e293b; }
.pat-type-btn.sel   { border-color:#6366f1; background:#312e81; color:#a5b4fc; }
.pat-color-row { display:flex; gap:.35rem; align-items:center; margin-bottom:.4rem; font-size:.76rem; color:#94a3b8; }
.pat-color-row label { min-width:54px; flex-shrink:0; }
.pat-size-row { display:flex; align-items:center; gap:.4rem; font-size:.76rem; color:#94a3b8; margin-bottom:.4rem; }
.pat-size-row label { min-width:54px; flex-shrink:0; }

/* ── Gradient Presets ─────────────────────────────── */
.grad-presets { display:flex; flex-wrap:wrap; gap:.3rem; margin-bottom:.45rem; }
.grad-preset-swatch {
  width:32px; height:32px; border-radius:7px; cursor:pointer;
  border:2px solid transparent; transition:all .14s; flex-shrink:0;
  box-shadow:0 2px 6px rgba(0,0,0,.3);
}
.grad-preset-swatch:hover { border-color:#fff; transform:scale(1.12); box-shadow:0 4px 14px rgba(0,0,0,.5); }

/* ── Fade/Transparency tool ───────────────────────── */
.fade-btn-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.3rem; margin-top:.35rem; }
.fade-btn {
  padding:.32rem .2rem; border-radius:7px; border:1.5px solid #334155;
  background:#0f172a; color:#64748b; font-size:.66rem; cursor:pointer;
  text-align:center; transition:all .13s;
}
.fade-btn:hover { border-color:#06b6d4; color:#e2e8f0; background:#1e293b; }
.fade-btn.danger { color:#f87171; }
.fade-btn.danger:hover { background:#7f1d1d; border-color:#ef4444; color:#fca5a5; }

/* ── Interactive fill tool button active ──────────── */
#tInterFill.active { background:linear-gradient(135deg,#f59e0b,#ef4444); border-color:#f59e0b; color:#fff; }
/* ── Gradient direction active ────────────────────── */
.gdir-btn.active { background:#312e81; border-color:#6366f1; color:#a5b4fc; }
#gBtnDrag.drag-on { background:#78350f; border-color:#f59e0b; color:#fbbf24; }
/* ── Interactive fill drag line ───────────────────── */
#gradDragLine { position:absolute; pointer-events:none; z-index:500; display:none; }
/* ── Gradient section title with drag hint ─────────── */
.grad-drag-hint { font-size:.65rem; color:#f59e0b; margin-left:.35rem; }

/* ══════════════════════════════════════════════════════════
   MOBILE-NATIVE LAYOUT — everything below is gated to <=900px;
   desktop layout/behavior above that width is completely untouched.
══════════════════════════════════════════════════════════ */
.ed-mob-only { display:none; }
.ed-panel-mob-hd { display:none; }
#edSheetBackdrop { display:none; }

@media(max-width:900px) {
  /* ── Topbar: wrap instead of clipping/scrolling (never hide a function silently) ── */
  .ed-topbar { height:auto; min-height:52px; flex-wrap:wrap; row-gap:.35rem; padding:.45rem .5rem; }
  .ed-top-left { flex-wrap:nowrap; min-width:0; }
  .ed-top-right { flex-wrap:wrap; justify-content:flex-end; row-gap:.35rem; width:100%; flex-basis:100%; }
  .ed-back span { display:none; }               /* icon-only Back on mobile */
  .ed-cat-tag, .ed-size-tag { display:none; }    /* informational only, not a function — freed for space */
  .tpl-name-input { max-width:none; flex:1 1 60px; min-width:0; }
  .ed-divider { display:none; }
  .ed-btn { min-height:36px; }
  .ed-mob-only { display:inline-flex; }

  /* ── Body stacks: canvas on top, tool rail becomes a bottom bar ── */
  .ed-body { flex-direction:column; }
  .ed-canvas-area { order:1; padding:.75rem; }
  .ed-tools {
    order:2; width:100%; height:auto; flex-direction:row; flex-shrink:0;
    overflow-x:auto; overflow-y:visible; -webkit-overflow-scrolling:touch;
    padding:.4rem .5rem calc(.4rem + env(safe-area-inset-bottom,0px));
    border-right:none; border-top:1px solid #1e293b;
    /* fade the trailing edge so "more tools" is visually obvious, not a silent scroll */
    -webkit-mask-image:linear-gradient(to right,#000 90%,transparent);
    mask-image:linear-gradient(to right,#000 90%,transparent);
  }
  .ed-tools .tool-sep { width:1px; height:32px; margin:0 .35rem; flex-shrink:0; }
  .ed-tools .tool-btn { flex-shrink:0; }

  /* ── Flyouts (Shapes/Brush/Slots/etc): bottom sheet instead of side popover ── */
  .tool-flyout {
    position:fixed !important; left:8px !important; right:8px !important; top:auto !important;
    bottom:calc(64px + env(safe-area-inset-bottom,0px)); width:auto; min-width:0; max-width:none;
    max-height:min(45vh, calc(100vh - 220px)); overflow-y:auto; -webkit-overflow-scrolling:touch;
  }

  /* ── Properties panel becomes a slide-up bottom sheet ──
     Capped so a real strip of canvas always stays visible above it (this used to be
     70vh, which — once stacked under the topbar + bottom tool bar — left almost no
     canvas visible at all; you'd open Properties and lose sight of what you were
     editing entirely). */
  .ed-props {
    display:none; position:fixed; left:0; right:0; bottom:0; z-index:9300;
    width:auto; max-height:min(46vh, calc(100vh - 260px)); flex-direction:column; overflow:hidden;
    border-radius:16px 16px 0 0; box-shadow:0 -10px 34px rgba(0,0,0,.55);
    border-left:none;
  }
  .ed-props.mob-open { display:flex; }
  .ed-panel-mob-hd {
    display:flex; align-items:center; gap:.6rem; padding:.85rem 1rem .6rem;
    border-bottom:1px solid #334155; flex-shrink:0; position:relative;
  }
  .ed-panel-mob-hd::before {
    content:''; position:absolute; top:.4rem; left:50%; transform:translateX(-50%);
    width:36px; height:4px; border-radius:3px; background:#334155;
  }
  .ed-panel-mob-hd span { font-size:.88rem; font-weight:800; color:#e2e8f0; flex:1; margin-top:.3rem; }
  .ed-panel-mob-hd button {
    width:32px; height:32px; border:none; background:#334155; border-radius:9px;
    display:flex; align-items:center; justify-content:center; font-size:.95rem;
    color:#e2e8f0; cursor:pointer; flex-shrink:0; margin-top:.2rem;
  }
  .ed-props-scroll { overflow-y:auto; -webkit-overflow-scrolling:touch; padding-bottom:env(safe-area-inset-bottom,0px); }
  #edSheetBackdrop {
    display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9250;
  }
  #edSheetBackdrop.show { display:block; }

  /* ── Bigger touch targets throughout the panel/flyouts ── */
  .style-btn, .align-btn { width:36px; height:34px; }
  .props-input, .props-select { min-height:34px; }
  .flyout-item { padding:.65rem .3rem; }
  .grad-dir-btn, .fade-btn, .pat-type-btn, .nc-dpi-btn, .nc-preset-btn { padding-top:.42rem; padding-bottom:.42rem; }

  /* ── Text Warp Studio: side-by-side (canvas + 320px controls) → stacked ── */
  #warpModal > div { width:100% !important; max-width:100vw !important; height:100%; max-height:100vh !important; border-radius:0 !important; }
  #warpModal > div > div:nth-child(2) { flex-direction:column !important; overflow-y:auto !important; }
  #warpModal > div > div:nth-child(2) > div:first-child { flex:none !important; width:100% !important; padding:1rem !important; }
  #warpModal > div > div:nth-child(2) > div:last-child { width:100% !important; border-left:none !important; border-top:1px solid #334155; }
  #warpPreviewCanvas { width:100%; height:auto; }

  /* ── 3D Text Studio: 3-column (310px + canvas + 300px) → stacked ── */
  #studio3DModal > div { width:100% !important; max-width:100vw !important; height:100%; max-height:100vh !important; border-radius:0 !important; }
  #studio3DModal > div > div:nth-child(2) { flex-direction:column !important; overflow-y:auto !important; }
  #studio3DModal > div > div:nth-child(2) > div { width:100% !important; flex:none !important; border:none !important; border-bottom:1px solid #1e293b; }
  #fx3DList { max-height:220px; grid-template-columns:repeat(2,1fr) !important; grid-auto-rows:min-content; }
  .fx3d-effect-card { min-height:82px; height:auto; }
  .fx3d-effect-card canvas { max-height:64px; height:64px; }
  .fx3d-effect-card > .fx3d-lbl { font-size:.64rem; }
  #canvas3DPreview { max-width:100%; }

  /* ── Sticker modal: fixed 130px category rail + 6-col grid → stacked, fewer columns ── */
  .sticker-modal-inner { width:100% !important; max-width:100vw !important; height:100%; max-height:100vh !important; border-radius:0 !important; }
  .sticker-modal-inner > div:nth-child(2) { flex-direction:column !important; overflow-y:auto !important; }
  .sticker-modal-inner > div:nth-child(2) > div:first-child { width:100% !important; border-right:none !important; border-bottom:1px solid #334155; }
  #stickerCats { display:flex; flex-wrap:wrap; gap:.3rem; }
  .sticker-cat-btn { display:inline-block !important; width:auto !important; padding:.4rem .7rem; }
  #stickerGrid { grid-template-columns:repeat(4,1fr) !important; }

  /* ── New Canvas modal: 4-col preset/DPI grids are cramped at phone width ── */
  #newCanvasModal .nc-preset-btn { font-size:.64rem; }

  /* ── Logo Studio host close button: bigger touch target on mobile ── */
  #lspIframeModal button { width:42px !important; height:42px !important; top:8px !important; right:8px !important; font-size:1.3rem !important; }
}
</style>
<style>
/* ═══════════ EDITOR PRO — rulers, guides, status bar, menus, panels ═══════════ */
:root { --pro-bg:#0f172a; --pro-panel:#1e293b; --pro-line:#334155; --pro-text:#e2e8f0; --pro-mute:#94a3b8; --pro-acc:#6366f1; --pro-guide:#06b6d4; }
.pro-ruler { position:fixed; z-index:40; background:#111827; display:none; cursor:default; touch-action:none; }
.pro-ruler.on { display:block; }
#proRulerTop { height:20px; cursor:row-resize; border-bottom:1px solid var(--pro-line); }
#proRulerLeft { width:20px; cursor:col-resize; border-right:1px solid var(--pro-line); }
#proRulerCorner { position:fixed; z-index:41; width:20px; height:20px; background:#111827; border-right:1px solid var(--pro-line); border-bottom:1px solid var(--pro-line);
  color:var(--pro-mute); font-size:.55rem; font-weight:700; display:none; align-items:center; justify-content:center; cursor:pointer; user-select:none; }
#proRulerCorner.on { display:flex; }
#proRulerCorner:hover { color:#fff; background:var(--pro-acc); }
.ed-canvas-area.pro-rulers { padding-top:calc(2rem + 20px) !important; padding-left:calc(2rem + 20px) !important; }
#proGuideTip { position:fixed; z-index:9800; pointer-events:none; background:var(--pro-guide); color:#002b33; font:700 11px/1 system-ui; padding:3px 6px; border-radius:4px; display:none; }

#proStatus { position:fixed; z-index:39; height:28px; display:flex; align-items:center; gap:.6rem; padding:0 .6rem;
  background:rgba(15,23,42,.92); backdrop-filter:blur(6px); border-top:1px solid var(--pro-line); color:var(--pro-mute); font-size:.72rem; user-select:none; }
#proSelInfo { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; flex-shrink:1; }
#proStatus b { color:var(--pro-text); font-weight:600; font-variant-numeric:tabular-nums; }
#proStatus .sep { width:1px; height:14px; background:var(--pro-line); }
#proStatus button { background:transparent; border:1px solid transparent; color:var(--pro-mute); border-radius:5px; padding:1px 6px; font-size:.72rem; cursor:pointer; }
#proStatus button:hover { color:#fff; border-color:var(--pro-line); background:var(--pro-panel); }
#proStatus input[type=range] { width:110px; flex:none; }
#proStatus .grow { flex:1; }
@media (max-width:900px) { #proStatus .hide-m { display:none; } #proStatus input[type=range] { width:80px; } }

.pro-menu { position:fixed; z-index:9600; min-width:220px; background:var(--pro-panel); border:1px solid var(--pro-line); border-radius:10px; padding:.3rem;
  box-shadow:0 18px 50px rgba(0,0,0,.55); display:none; font-size:.8rem; color:var(--pro-text); }
.pro-menu.open { display:block; }
.pro-menu .mi { display:flex; align-items:center; gap:.55rem; padding:.42rem .6rem; border-radius:6px; cursor:pointer; white-space:nowrap; }
.pro-menu .mi:hover, .pro-menu .mi.kb { background:var(--pro-acc); color:#fff; }
.pro-menu .mi i { width:16px; text-align:center; color:var(--pro-mute); }
.pro-menu .mi:hover i { color:#fff; }
.pro-menu .mi .k { margin-left:auto; color:var(--pro-mute); font-size:.7rem; padding-left:1rem; }
.pro-menu .mi:hover .k { color:#e0e7ff; }
.pro-menu .mi.danger { color:#fca5a5; }
.pro-menu .mi.off { opacity:.4; pointer-events:none; }
.pro-menu .ms { height:1px; background:var(--pro-line); margin:.25rem .2rem; }

.pro-ov { position:fixed; inset:0; z-index:9650; background:rgba(2,6,23,.6); display:none; align-items:flex-start; justify-content:center; padding:10vh 1rem 1rem; }
.pro-ov.open { display:flex; }
.pro-box { width:100%; max-width:560px; background:var(--pro-panel); border:1px solid var(--pro-line); border-radius:14px; box-shadow:0 30px 80px rgba(0,0,0,.6); overflow:hidden; color:var(--pro-text); }
.pro-box-hd { display:flex; align-items:center; gap:.5rem; padding:.8rem 1rem; border-bottom:1px solid var(--pro-line); font-weight:700; }
.pro-box-hd .x { margin-left:auto; background:none; border:none; color:var(--pro-mute); font-size:1.1rem; cursor:pointer; }
.pro-box-bd { padding:1rem; max-height:70vh; overflow:auto; }
#proCmdInput { width:100%; background:var(--pro-bg); border:none; border-bottom:1px solid var(--pro-line); color:var(--pro-text); font-size:1rem; padding:.9rem 1rem; outline:none; }
#proCmdList { max-height:52vh; overflow:auto; padding:.35rem; }
#proCmdList .mi { display:flex; align-items:center; gap:.6rem; padding:.5rem .65rem; border-radius:8px; cursor:pointer; font-size:.85rem; }
#proCmdList .mi.kb { background:var(--pro-acc); color:#fff; }
#proCmdList .mi i { width:18px; text-align:center; color:var(--pro-mute); }
#proCmdList .mi.kb i { color:#fff; }
#proCmdList .mi .grp { font-size:.66rem; color:var(--pro-mute); text-transform:uppercase; letter-spacing:.06em; }
#proCmdList .mi .k { margin-left:auto; font-size:.72rem; color:var(--pro-mute); }
#proCmdList .mi.kb .k, #proCmdList .mi.kb .grp { color:#e0e7ff; }
.pro-kbd { display:inline-block; min-width:1.4em; padding:1px 5px; border:1px solid var(--pro-line); border-bottom-width:2px; border-radius:5px; font:600 .7rem system-ui; color:var(--pro-text); background:var(--pro-bg); text-align:center; }
.pro-keys { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:.2rem 1.2rem; font-size:.8rem; }
.pro-keys h4 { grid-column:1/-1; font-size:.7rem; text-transform:uppercase; letter-spacing:.08em; color:var(--pro-acc); margin:.7rem 0 .2rem; }
.pro-keys div { display:flex; justify-content:space-between; gap:.5rem; padding:.2rem 0; border-bottom:1px dashed #263244; }

#proHistory { position:fixed; z-index:45; width:250px; max-height:60vh; display:none; flex-direction:column; background:var(--pro-panel); border:1px solid var(--pro-line); border-radius:12px; box-shadow:0 18px 50px rgba(0,0,0,.5); overflow:hidden; }
#proHistory.open { display:flex; }
#proHistory .hd { display:flex; align-items:center; padding:.55rem .75rem; border-bottom:1px solid var(--pro-line); font-size:.78rem; font-weight:700; color:var(--pro-text); gap:.4rem; }
#proHistory .hd button { margin-left:auto; background:none; border:none; color:var(--pro-mute); cursor:pointer; }
#proHistList { overflow:auto; padding:.3rem; }
#proHistList .hi { display:flex; align-items:center; gap:.5rem; padding:.35rem .5rem; border-radius:6px; font-size:.76rem; color:var(--pro-text); cursor:pointer; }
#proHistList .hi:hover { background:#273449; }
#proHistList .hi.cur { background:var(--pro-acc); color:#fff; }
#proHistList .hi.future { opacity:.45; }
#proHistList .hi .t { margin-left:auto; font-size:.66rem; color:var(--pro-mute); }
#proHistList .hi.cur .t { color:#e0e7ff; }

.pro-field { margin-bottom:.75rem; }
.pro-field label { display:block; font-size:.72rem; color:var(--pro-mute); margin-bottom:.3rem; font-weight:600; }
.pro-field input[type=text], .pro-field input[type=number], .pro-field select { width:100%; background:var(--pro-bg); border:1px solid var(--pro-line); border-radius:8px; color:var(--pro-text); padding:.45rem .6rem; font-size:.85rem; outline:none; }
.pro-chips { display:flex; flex-wrap:wrap; gap:.35rem; }
.pro-chip { padding:.35rem .7rem; border-radius:999px; border:1px solid var(--pro-line); background:var(--pro-bg); color:var(--pro-mute); font-size:.76rem; cursor:pointer; font-weight:600; }
.pro-chip.on { background:var(--pro-acc); border-color:var(--pro-acc); color:#fff; }
.pro-actions { display:flex; gap:.5rem; justify-content:flex-end; padding:.8rem 1rem; border-top:1px solid var(--pro-line); }
.pro-btn { padding:.5rem 1rem; border-radius:8px; border:1px solid var(--pro-line); background:transparent; color:var(--pro-text); cursor:pointer; font-size:.82rem; font-weight:600; }
.pro-btn.primary { background:var(--pro-acc); border-color:var(--pro-acc); color:#fff; }
.pro-btn:disabled { opacity:.5; cursor:not-allowed; }
.pro-note { font-size:.72rem; color:var(--pro-mute); margin-top:.3rem; }
#proQrPreview { display:flex; align-items:center; justify-content:center; min-height:150px; background:#fff; border-radius:10px; margin-bottom:.8rem; padding:.6rem; }
#proQrPreview svg { max-width:100%; max-height:200px; }

#proDrop { position:fixed; z-index:9500; inset:0; display:none; align-items:center; justify-content:center; background:rgba(79,70,229,.25); border:3px dashed #818cf8; color:#fff; font-size:1.3rem; font-weight:700; pointer-events:none; }
#proDrop.on { display:flex; }
.pro-top-btn { position:relative; }
.pro-badge-new { position:absolute; top:-5px; right:-5px; background:#22c55e; color:#04260f; font-size:.5rem; font-weight:800; padding:1px 4px; border-radius:6px; }
</style>
<style>
/* ═══════════ EDITOR PRO 2 — pen, boolean, adjustments, auto-fit, palette, layer fx ═══════════ */
.p2-sec { border-bottom:1px solid #334155; padding:.85rem 1rem; }
.p2-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#64748b; margin-bottom:.6rem; display:flex; align-items:center; gap:.4rem; }
.p2-title .badge { background:#4f46e5; color:#fff; font-size:.55rem; padding:1px 5px; border-radius:4px; letter-spacing:.04em; }
.p2-row { display:flex; align-items:center; gap:.45rem; margin-bottom:.45rem; font-size:.78rem; color:#94a3b8; }
.p2-row > label:first-child { min-width:64px; }
.p2-row input[type=range] { flex:1; accent-color:#6366f1; }
.p2-row input[type=number] { width:62px; background:#0f172a; border:1px solid #334155; border-radius:6px; color:#e2e8f0; padding:.2rem .35rem; font-size:.76rem; }
.p2-row .val { min-width:30px; text-align:right; color:#e2e8f0; font-size:.74rem; font-variant-numeric:tabular-nums; }
.p2-btn { background:#0f172a; border:1px solid #334155; color:#cbd5e1; border-radius:7px; padding:.32rem .6rem; font-size:.74rem; cursor:pointer; display:inline-flex; align-items:center; gap:.3rem; }
.p2-btn:hover { border-color:#6366f1; color:#fff; }
.p2-btn.primary { background:#4f46e5; border-color:#4f46e5; color:#fff; }
.p2-btn.on { background:#312e81; border-color:#6366f1; color:#fff; }
.p2-btns { display:flex; flex-wrap:wrap; gap:.3rem; }
.p2-fx { border:1px solid #334155; border-radius:8px; margin-bottom:.45rem; overflow:hidden; }
.p2-fx-hd { display:flex; align-items:center; gap:.45rem; padding:.4rem .55rem; background:#0f172a; font-size:.76rem; color:#e2e8f0; cursor:pointer; user-select:none; }
.p2-fx-hd input { margin:0; }
.p2-fx-hd .chev { margin-left:auto; color:#64748b; transition:transform .15s; }
.p2-fx.open .p2-fx-hd .chev { transform:rotate(90deg); }
.p2-fx-bd { display:none; padding:.5rem .55rem .2rem; }
.p2-fx.open .p2-fx-bd { display:block; }
/* palette */
.p2-pal { display:flex; flex-wrap:wrap; gap:4px; align-items:center; }
.p2-sw { width:22px; height:22px; border-radius:5px; cursor:pointer; border:1px solid rgba(255,255,255,.18); position:relative; flex:none; }
.p2-sw:hover { transform:scale(1.15); box-shadow:0 0 0 2px #6366f1; }
.p2-sw.add { display:flex; align-items:center; justify-content:center; color:#94a3b8; background:#0f172a; border:1px dashed #475569; font-size:.85rem; }
.p2-pal-lbl { font-size:.64rem; color:#64748b; margin:.35rem 0 .2rem; }
/* pen tool */
body.pen-mode .canvas-shadow { outline:2px solid #22d3ee; outline-offset:2px; }
#p2PenBar { position:fixed; z-index:46; display:none; align-items:center; gap:.5rem; padding:.4rem .6rem; background:#0e7490; color:#ecfeff; border-radius:10px; font-size:.76rem; box-shadow:0 10px 30px rgba(0,0,0,.45); }
#p2PenBar.on { display:flex; }
#p2PenBar button { background:rgba(255,255,255,.15); border:none; color:#fff; border-radius:6px; padding:.25rem .55rem; font-size:.74rem; cursor:pointer; }
#p2PenBar button:hover { background:rgba(255,255,255,.3); }
/* adjustments modal */
#p2AdjOv .pro-box { max-width:720px; }
.p2-adj { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
@media (max-width:700px) { .p2-adj { grid-template-columns:1fr; } }
#p2Hist, #p2Curve { width:100%; background:#0f172a; border:1px solid #334155; border-radius:8px; display:block; }
#p2Curve { cursor:crosshair; touch-action:none; }
.p2-ch { display:flex; gap:.25rem; margin-bottom:.4rem; }
.p2-ch span { padding:.15rem .55rem; border-radius:999px; border:1px solid #334155; font-size:.7rem; cursor:pointer; color:#94a3b8; }
.p2-ch span.on { background:#4f46e5; border-color:#4f46e5; color:#fff; }
.p2-lv { position:relative; height:16px; margin-top:4px; }
.p2-lv input { position:absolute; inset:0; width:100%; pointer-events:none; -webkit-appearance:none; appearance:none; background:transparent; }
.p2-lv input::-webkit-slider-thumb { pointer-events:auto; -webkit-appearance:none; width:12px; height:14px; border-radius:2px; background:#e2e8f0; border:1px solid #0f172a; cursor:ew-resize; }
.p2-lv input::-moz-range-thumb { pointer-events:auto; width:12px; height:14px; border-radius:2px; background:#e2e8f0; border:1px solid #0f172a; cursor:ew-resize; }
.p2-lv input.g::-webkit-slider-thumb { background:#94a3b8; }
.p2-lv input.w::-webkit-slider-thumb { background:#fff; }
.p2-lv input.b::-webkit-slider-thumb { background:#000; border-color:#94a3b8; }
.p2-lv .track { position:absolute; left:0; right:0; top:6px; height:4px; border-radius:2px; background:linear-gradient(90deg,#000,#fff); }
</style>
</head>
<body>
<div class="eraser-cursor" id="eraserCursor"></div>
<div class="ed-wrap">

  <!-- ── Top toolbar ──────────────────────────────────────────────── -->
  <div class="ed-topbar">
    <div class="ed-top-left">
      <a href="<?= e($editorData['back_url']) ?>" class="ed-back">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <button class="ed-btn" onclick="openNewCanvasModal()" title="New Canvas — create a fresh design" style="color:#34d399;border-color:#065f46;">
        <i class="bi bi-plus-lg"></i> New
      </button>
      <div class="ed-divider"></div>
      <input type="text" id="tplNameInput" class="tpl-name-input" maxlength="100"
             value="<?= e($tpl['name']) ?>" title="Template name (click to rename)">
      <span id="edDirtyDot" title="Unsaved changes"></span>
      <span class="ed-cat-tag"><i class="bi <?= e($tpl['cat_icon']) ?> me-1"></i><?= e($tpl['cat_name']) ?></span>
      <span class="ed-size-tag" id="sizeTag"><?= (int)$tpl['canvas_width'] ?> × <?= (int)$tpl['canvas_height'] ?> px</span>
    </div>

    <div class="ed-top-right">
      <!-- Undo / Redo -->
      <button class="ed-btn" id="btnUndo" onclick="histUndo()" title="Undo (Ctrl+Z)" disabled>
        <i class="bi bi-arrow-counterclockwise"></i>
      </button>
      <button class="ed-btn" id="btnRedo" onclick="histRedo()" title="Redo (Ctrl+Y)" disabled>
        <i class="bi bi-arrow-clockwise"></i>
      </button>
      <!-- Mobile-only: open the Properties sheet (desktop shows the panel directly) -->
      <button class="ed-btn ed-mob-only" onclick="openEdPanel()" title="Properties">
        <i class="bi bi-sliders"></i>
      </button>
      <div class="ed-divider"></div>

      <!-- Zoom -->
      <select class="zoom-select" id="zoomSel" onchange="setZoom(this.value)" title="Zoom (Ctrl + mouse wheel)">
        <option value="fit">Fit</option>
        <option value="0.25">25%</option>
        <option value="0.4">40%</option>
        <option value="0.6">60%</option>
        <option value="0.75">75%</option>
        <option value="1" selected>100%</option>
        <option value="1.25">125%</option>
        <option value="1.5">150%</option>
        <option value="2">200%</option>
        <option value="3">300%</option>
      </select>
      <div class="ed-divider"></div>

      <!-- Grid toggle -->
      <button class="ed-btn" id="btnGrid" onclick="toggleGrid()" title="Toggle Grid">
        <i class="bi bi-grid-3x3"></i>
      </button>
      <div class="ed-divider"></div>

      <!-- Crop -->
      <button class="ed-btn" id="btnCropMode" onclick="toggleCropMode()" title="Crop Tool — select crop area">
        <i class="bi bi-crop"></i>
      </button>
      <button class="ed-btn success" id="btnApplyCrop" onclick="applyCrop()" title="Apply Crop" style="display:none;">
        <i class="bi bi-check-lg"></i> Apply Crop
      </button>
      <div class="ed-divider"></div>

      <!-- Group/Ungroup -->
      <button class="ed-btn" onclick="groupSelected()" title="Group selected (Ctrl+G)">
        <i class="bi bi-collection"></i>
      </button>
      <button class="ed-btn" onclick="ungroupSelected()" title="Ungroup (Ctrl+Shift+G)">
        <i class="bi bi-collection-fill"></i>
      </button>
      <div class="ed-divider"></div>

      <!-- Export -->
      <button class="ed-btn" onclick="exportPng()" title="Export PNG">
        <i class="bi bi-file-image"></i>
      </button>
      <button class="ed-btn" onclick="exportSVG()" title="Export SVG">
        <i class="bi bi-filetype-svg"></i>
      </button>
      <button class="ed-btn" onclick="exportJSON()" title="Export JSON">
        <i class="bi bi-filetype-json"></i>
      </button>
      <button class="ed-btn" onclick="printCanvas()" title="Print (Ctrl+P)">
        <i class="bi bi-printer"></i>
      </button>
      <button class="ed-btn" onclick="exportPDF()" title="Export PDF (high-res)" style="color:#f97316;border-color:#7c2d12;">
        <i class="bi bi-filetype-pdf"></i>
      </button>
      <div class="ed-divider"></div>
      <button class="ed-btn" id="btnPrintGuides" onclick="togglePrintGuides()" title="Toggle Print Guides — Bleed (red) &amp; Safe Area (green)">
        <i class="bi bi-rulers"></i>
      </button>
      <button class="ed-btn" onclick="openMockupModal()" title="Mockup Preview — view design in 3D perspective">
        <i class="bi bi-phone-flip"></i>
      </button>
      <div class="ed-divider"></div>
      <button class="ed-btn" id="btnSaveGallery" onclick="saveToGallery()" title="Save to My Gallery" style="background:#0f4c81;color:#93c5fd;border-color:#1d4ed8;">
        <i class="bi bi-collection"></i> <span id="galleryBtnLabel"><?= $editorData['from_gallery'] ? 'Update Gallery' : 'Save to Gallery' ?></span>
      </button>
      <?php if ($editorData['is_admin']): ?>
      <button class="ed-btn primary" id="btnSave" onclick="saveCanvas()">
        <i class="bi bi-floppy"></i> Save
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Editor body ───────────────────────────────────────────────── -->
  <div class="ed-body">

    <!-- Left tools -->
    <div class="ed-tools">

      <!-- Select -->
      <button class="tool-btn active" id="tTool" onclick="setActiveTool('select');closeFlyouts()">
        <i class="bi bi-cursor-fill"></i><span class="tool-tooltip">Select (V)</span>
      </button>

      <div class="tool-sep"></div>

      <!-- Text group -->
      <button class="tool-btn c-text" onclick="addText();closeFlyouts()">
        <i class="bi bi-type-bold"></i><span class="tool-tooltip">Text (T)</span>
      </button>
      <button class="tool-btn c-text has-drop" id="tWarp" onclick="openWarpModal()">
        <i class="bi bi-fonts"></i><span class="tool-tooltip">Text Warp Studio</span>
      </button>
      <button class="tool-btn c-text has-drop" id="t3D" onclick="open3DStudio()">
        <i class="bi bi-badge-3d"></i><span class="tool-tooltip">3D Text Studio</span>
      </button>
      <button class="tool-btn c-text" onclick="openCurvedTextModal();closeFlyouts()">
        <i class="bi bi-circle-half"></i><span class="tool-tooltip">Curved Text</span>
      </button>
      <button class="tool-btn c-text" onclick="addVerticalText();closeFlyouts()">
        <i class="bi bi-distribute-vertical"></i><span class="tool-tooltip">Vertical Text (90°)</span>
      </button>
      <button class="tool-btn c-text" onclick="promptBulletList();closeFlyouts()">
        <i class="bi bi-list-ul"></i><span class="tool-tooltip">Bullet List</span>
      </button>

      <div class="tool-sep"></div>

      <!-- Shapes flyout -->
      <button class="tool-btn c-shape has-drop" id="tShapes" onclick="toggleFlyout('shapesFlyout', this)">
        <i class="bi bi-pentagon-fill"></i><span class="tool-tooltip">Shapes ▸</span>
      </button>

      <div class="tool-sep"></div>

      <!-- Image -->
      <button class="tool-btn c-image" onclick="document.getElementById('imgUpload').click();closeFlyouts()">
        <i class="bi bi-image"></i><span class="tool-tooltip">Upload Image (I)</span>
      </button>
      <button class="tool-btn c-image" onclick="openUrlModal();closeFlyouts()">
        <i class="bi bi-link-45deg"></i><span class="tool-tooltip">Image from URL (U)</span>
      </button>
      <button class="tool-btn c-image" id="tCrop" onclick="toggleCropMode()">
        <i class="bi bi-crop"></i><span class="tool-tooltip">Crop Tool</span>
      </button>

      <div class="tool-sep"></div>

      <!-- Draw flyout -->
      <button class="tool-btn c-draw has-drop" id="tDraw" onclick="toggleFlyout('brushFlyout', this)">
        <i class="bi bi-brush"></i><span class="tool-tooltip">Draw / Brush ▸</span>
      </button>
      <button class="tool-btn c-draw" id="tEraser" onclick="toggleEraserMode();closeFlyouts()" title="Eraser Tool (E)">
        <i class="bi bi-eraser"></i><span class="tool-tooltip">Eraser (E)</span>
      </button>
      <button class="tool-btn c-pick" id="tEye" onclick="toggleEyedropper()">
        <i class="bi bi-eyedropper"></i><span class="tool-tooltip">Color Picker — pick from canvas</span>
      </button>
      <button class="tool-btn" id="tInterFill" onclick="openInterFillPanel()" title="Interactive Fill Tool — gradient/pattern fill like CorelDRAW" style="color:#f59e0b;">
        <i class="bi bi-paint-bucket"></i><span class="tool-tooltip">Interactive Fill ▸</span>
      </button>
      <button class="tool-btn" onclick="openFadePanel()" title="Transparency / Fade Tool" style="color:#06b6d4;">
        <i class="bi bi-circle-half"></i><span class="tool-tooltip">Transparency / Fade</span>
      </button>

      <div class="tool-sep"></div>

      <!-- Online Photos / Stickers -->
      <button class="tool-btn c-image" onclick="openStickerModal();closeFlyouts()">
        <i class="bi bi-images"></i><span class="tool-tooltip">Photos &amp; Stickers ▸</span>
      </button>

      <!-- Logo Designer Studio -->
      <button class="tool-btn c-util" onclick="openLogoStudioPro();closeFlyouts()" style="color:#fbbf24;" title="Logo Designer Studio Pro — opens in new window">
        <i class="bi bi-pentagon-fill"></i><span class="tool-tooltip">Logo Designer Studio ★ Pro</span>
      </button>

      <div class="tool-sep"></div>

      <!-- Placeholders flyout -->
      <button class="tool-btn c-util has-drop" onclick="toggleFlyout('slotFlyout', this)">
        <i class="bi bi-person-bounding-box"></i><span class="tool-tooltip">Placeholder Slots ▸</span>
      </button>

      <div class="tool-sep"></div>

      <!-- Arrange -->
      <button class="tool-btn" onclick="bringFwd();closeFlyouts()">
        <i class="bi bi-layers-fill"></i><span class="tool-tooltip">Bring Forward</span>
      </button>
      <button class="tool-btn" onclick="sendBwd();closeFlyouts()">
        <i class="bi bi-layers"></i><span class="tool-tooltip">Send Backward</span>
      </button>
      <button class="tool-btn" onclick="dupSelected();closeFlyouts()">
        <i class="bi bi-copy"></i><span class="tool-tooltip">Duplicate (Ctrl+D)</span>
      </button>

      <div class="tool-sep"></div>

      <button class="tool-btn danger" onclick="deleteSelected();closeFlyouts()">
        <i class="bi bi-trash"></i><span class="tool-tooltip">Delete (Del)</span>
      </button>
    </div>

    <!-- ── Flyout Panels ─────────────────────────────────────── -->

    <!-- Shapes Flyout -->
    <div class="tool-flyout" id="shapesFlyout" style="min-width:240px;">
      <div class="flyout-title">Shapes</div>
      <div class="flyout-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="flyout-item" onclick="addShape('rect');closeFlyouts()"><i class="bi bi-square"></i>Rect</div>
        <div class="flyout-item" onclick="addShape('circle');closeFlyouts()"><i class="bi bi-circle"></i>Circle</div>
        <div class="flyout-item" onclick="addShape('ellipse');closeFlyouts()"><i class="bi bi-circle"></i>Ellipse</div>
        <div class="flyout-item" onclick="addShape('tri');closeFlyouts()"><i class="bi bi-triangle"></i>Triangle</div>
        <div class="flyout-item" onclick="addShape('line');closeFlyouts()"><i class="bi bi-slash-lg"></i>Line</div>
        <div class="flyout-item" onclick="addShape('star');closeFlyouts()"><i class="bi bi-star"></i>Star</div>
        <div class="flyout-item" onclick="addShape('heart');closeFlyouts()"><i class="bi bi-heart"></i>Heart</div>
        <div class="flyout-item" onclick="addShape('arrow');closeFlyouts()"><i class="bi bi-arrow-right-square"></i>Arrow</div>
        <div class="flyout-item" onclick="addShape('diamond');closeFlyouts()"><i class="bi bi-diamond"></i>Diamond</div>
        <div class="flyout-item" onclick="addShape('pentagon');closeFlyouts()"><i class="bi bi-pentagon"></i>Pentagon</div>
        <div class="flyout-item" onclick="addShape('hexagon');closeFlyouts()"><i class="bi bi-hexagon"></i>Hexagon</div>
        <div class="flyout-item" onclick="addShape('octagon');closeFlyouts()"><i class="bi bi-octagon"></i>Octagon</div>
        <div class="flyout-item" onclick="addShape('cross');closeFlyouts()"><i class="bi bi-plus-square"></i>Cross</div>
        <div class="flyout-item" onclick="addShape('cloud');closeFlyouts()"><i class="bi bi-cloud"></i>Cloud</div>
        <div class="flyout-item" onclick="addShape('sunburst');closeFlyouts()" style="color:#f59e0b;"><i class="bi bi-sun"></i>Sunburst</div>
        <div class="flyout-item" onclick="addShape('chevron');closeFlyouts()" style="color:#f87171;"><i class="bi bi-chevron-right"></i>Chevron</div>
        <div class="flyout-item" onclick="addShape('arch');closeFlyouts()" style="color:#34d399;"><i class="bi bi-rainbow"></i>Arch</div>
        <div class="flyout-item" onclick="addShape('wave');closeFlyouts()" style="color:#60a5fa;"><i class="bi bi-water"></i>Wave</div>
        <div class="flyout-item" onclick="addShape('badge');closeFlyouts()" style="color:#a78bfa;"><i class="bi bi-shield-fill"></i>Badge</div>
        <div class="flyout-item" onclick="addShape('ribbon');closeFlyouts()" style="color:#fb923c;"><i class="bi bi-award"></i>Ribbon</div>
      </div>
    </div>

    <!-- Brush Flyout -->
    <div class="tool-flyout" id="brushFlyout" style="min-width:210px;">
      <div class="flyout-title">Draw &amp; Brush</div>
      <div class="flyout-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:.65rem;">
        <div class="flyout-item" id="bTypePencil" onclick="activateBrush('pencil');setBrushType('pencil')"><i class="bi bi-pencil"></i>Pencil</div>
        <div class="flyout-item" id="bTypeSpray"  onclick="activateBrush('spray');setBrushType('spray')"><i class="bi bi-moisture"></i>Spray</div>
        <div class="flyout-item" id="bTypeCircle" onclick="activateBrush('circle');setBrushType('circle')"><i class="bi bi-brush"></i>Marker</div>
        <div class="flyout-item" id="bTypeCallig" onclick="activateBrush('pencil');setBrushType('pencil')"><i class="bi bi-vector-pen"></i>Pen</div>
        <div class="flyout-item" id="bTypeEraser" onclick="activateBrush('eraser')" style="grid-column:span 2;color:#f87171;"><i class="bi bi-eraser-fill"></i>Eraser (Pro)</div>
      </div>
      <!-- Eraser mode selector (hidden unless eraser active) -->
      <div id="eraserModeRow">
        <div style="font-size:.7rem;color:#94a3b8;margin-bottom:.25rem;">Eraser Mode</div>
        <div style="display:flex;gap:.3rem;">
          <button class="eraser-mode-btn active" id="emBtnBg"  onclick="setEraserMode('background')">BG Color</button>
          <button class="eraser-mode-btn" id="emBtnPx"  onclick="setEraserMode('pixel')">Pixel</button>
          <button class="eraser-mode-btn" id="emBtnObj" onclick="setEraserMode('object')">Object</button>
        </div>
        <div id="eraserModeDesc" style="font-size:.65rem;color:#64748b;margin-top:.22rem;">Paints canvas background color — fast &amp; safe</div>
      </div>
      <div class="flyout-sep" style="margin-top:.5rem;"></div>
      <div class="brush-row" style="margin-top:.5rem;">
        <label>Size</label>
        <input type="range" id="brushSize" min="1" max="80" value="10" oninput="updateBrush();document.getElementById('brushSizeVal').textContent=this.value" style="flex:1;accent-color:#6366f1;">
        <span id="brushSizeVal" style="min-width:22px;font-size:.74rem;color:#94a3b8;">10</span>
      </div>
      <div class="brush-row" id="brushColorRow">
        <label>Color</label>
        <input type="color" id="brushColor" value="#e74c3c" oninput="updateBrush()" style="width:32px;height:26px;border:1px solid #334155;border-radius:5px;cursor:pointer;background:none;padding:1px 2px;">
        <label style="margin-left:.4rem;">Opacity</label>
        <input type="range" id="brushOpacity" min="5" max="100" value="100" oninput="updateBrush();document.getElementById('brushOpVal').textContent=this.value+'%'" style="flex:1;accent-color:#6366f1;">
        <span id="brushOpVal" style="min-width:30px;font-size:.72rem;color:#94a3b8;">100%</span>
      </div>
      <button onclick="setActiveTool('select');closeFlyouts()" style="background:#334155;border:none;color:#e2e8f0;border-radius:7px;padding:.4rem .6rem;font-size:.74rem;cursor:pointer;width:100%;margin-top:.4rem;">✕ Done</button>
    </div>

    <!-- Slots Flyout -->
    <div class="tool-flyout" id="slotFlyout">
      <div class="flyout-title">Placeholder Slots</div>
      <div class="flyout-grid" style="grid-template-columns:repeat(2,1fr);">
        <div class="flyout-item" onclick="addPlaceholder('photo');closeFlyouts()"><i class="bi bi-person-square"></i>Photo</div>
        <div class="flyout-item" onclick="addPlaceholder('qr');closeFlyouts()"><i class="bi bi-qr-code"></i>QR Code</div>
        <div class="flyout-item" onclick="addPlaceholder('barcode');closeFlyouts()"><i class="bi bi-upc"></i>Barcode</div>
        <div class="flyout-item" onclick="addPlaceholder('sign');closeFlyouts()"><i class="bi bi-pen"></i>Signature</div>
      </div>
    </div>

    <!-- Canvas -->
    <div class="ed-canvas-area" id="canvasArea">
      <div class="canvas-shadow" id="canvasWrap" style="position:relative;">
        <canvas id="fabricCanvas"></canvas>
        <!-- SVG overlay for interactive gradient drag indicator -->
        <svg id="gradDragLine" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;display:none;">
          <defs>
            <marker id="arrowEnd" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
              <path d="M0,0 L0,6 L8,3 z" fill="#f59e0b"/>
            </marker>
            <marker id="arrowStart" markerWidth="8" markerHeight="8" refX="2" refY="3" orient="auto-start-reverse">
              <circle cx="3" cy="3" r="3" fill="#4f46e5"/>
            </marker>
          </defs>
          <line id="gradDragLineEl" x1="0" y1="0" x2="0" y2="0"
            stroke="#f59e0b" stroke-width="2.5" stroke-dasharray="6,3"
            marker-end="url(#arrowEnd)" marker-start="url(#arrowStart)"/>
        </svg>
      </div>
    </div>

    <!-- Properties Panel -->
    <div class="ed-props" id="propsPanel">
      <div class="ed-panel-mob-hd">
        <span>Properties</span>
        <button onclick="closeEdPanel()" title="Close"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="ed-props-scroll">

      <!-- Canvas settings (nothing selected) -->
      <div id="pCanvas">
        <div class="props-section">
          <div class="props-title">Canvas</div>
          <div class="props-row">
            <label>Width</label>
            <span id="pCanvasW">—</span> px
          </div>
          <div class="props-row">
            <label>Height</label>
            <span id="pCanvasH">—</span> px
          </div>
          <div class="props-row">
            <label>Background</label>
            <input type="color" id="pCanvasBg" value="#ffffff" oninput="setCanvasBg(this.value)" title="Canvas background color">
          </div>
          <div class="props-row" style="margin-top:.3rem;">
            <button onclick="openResizeModal()" style="width:100%;background:#334155;border:none;color:#e2e8f0;border-radius:7px;padding:.4rem;font-size:.77rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.35rem;">
              <i class="bi bi-aspect-ratio"></i> Resize Canvas
            </button>
          </div>
          <div class="props-row" style="margin-top:.2rem;">
            <button onclick="checkImageResolution()" style="width:100%;background:#334155;border:none;color:#e2e8f0;border-radius:7px;padding:.4rem;font-size:.77rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.35rem;">
              <i class="bi bi-aspect-ratio-fill"></i> Check Resolution
            </button>
          </div>
          <div class="props-row" style="margin-top:.2rem;">
            <button id="btnCMYK" onclick="toggleCMYKPreview()" style="width:100%;background:#334155;border:none;color:#e2e8f0;border-radius:7px;padding:.4rem;font-size:.77rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.35rem;">
              <i class="bi bi-palette"></i> CMYK Preview
            </button>
          </div>
          <div class="props-row" style="margin-top:.2rem;">
            <button onclick="exportPrintReady()" style="width:100%;background:#0f4c81;border:none;color:#93c5fd;border-radius:7px;padding:.4rem;font-size:.77rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.35rem;font-weight:600;">
              <i class="bi bi-printer-fill"></i> Export Print-Ready
            </button>
          </div>
          <div class="props-row" style="margin-top:.2rem;">
            <button id="btnTransBg" onclick="toggleTransparentBg()" style="width:100%;background:#334155;border:none;color:#e2e8f0;border-radius:7px;padding:.4rem;font-size:.77rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.35rem;">
              <i class="bi bi-transparency"></i> Transparent BG
            </button>
          </div>
          <div class="props-row" style="margin-top:.2rem;">
            <button onclick="checkColorAccessibility()" style="width:100%;background:#0f4c2e;border:none;color:#6ee7b7;border-radius:7px;padding:.4rem;font-size:.77rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.35rem;font-weight:600;">
              <i class="bi bi-universal-access-circle"></i> WCAG Accessibility Check
            </button>
          </div>
          <div class="props-row" id="eyeColorRow" style="display:none;">
            <label>Picked</label>
            <div id="eyeColorSwatch" class="tEye-color" style="background:#ffffff;" title="Last picked color"></div>
            <span id="eyeColorHex" style="font-size:.75rem;color:#94a3b8;flex:1;">#ffffff</span>
            <button onclick="applyPickedColor()" style="background:#4f46e5;border:none;color:#fff;border-radius:5px;padding:.2rem .5rem;font-size:.72rem;cursor:pointer;">Apply</button>
          </div>
        </div>
        <div class="props-section">
          <div class="props-title">Tips</div>
          <div style="font-size:.75rem;color:#64748b;line-height:1.6;">
            <p>• Click = Select object</p>
            <p>• Double-click text = Edit</p>
            <p>• Drag corner = Resize</p>
            <p>• Arrow keys = Nudge (Shift+Arrow = 10px)</p>
            <p>• Ctrl+Z/Y = Undo/Redo</p>
            <p>• Del = Delete selected</p>
            <p>• Ctrl+D = Duplicate · Ctrl+C / Ctrl+V = Copy / Paste</p>
            <p>• Ctrl+G = Group · Ctrl+Shift+G = Ungroup</p>
            <p>• Ctrl + mouse wheel = Zoom · Space + drag = Pan</p>
            <p>• Paste an image (Ctrl+V) straight from clipboard</p>
            <p>• Drag layers in the list to reorder · double-click to rename</p>
          </div>
        </div>
      </div>

      <!-- Object properties (object selected) -->
      <div id="pObj" style="display:none;">

        <!-- Position & Size -->
        <div class="props-section">
          <div class="props-title">Transform</div>
          <div class="props-grid2">
            <div>
              <label class="props-label">X (left)</label>
              <input type="number" class="props-input" id="pX" oninput="applyProp('left', +this.value)">
            </div>
            <div>
              <label class="props-label">Y (top)</label>
              <input type="number" class="props-input" id="pY" oninput="applyProp('top', +this.value)">
            </div>
            <div>
              <label class="props-label">Width</label>
              <input type="number" class="props-input" id="pW" oninput="applyScale('w', +this.value)">
            </div>
            <div>
              <label class="props-label">Height</label>
              <input type="number" class="props-input" id="pH" oninput="applyScale('h', +this.value)">
            </div>
          </div>
          <div class="props-row">
            <label>Rotate</label>
            <input type="range" id="pAngle" min="-180" max="180" value="0" oninput="applyProp('angle', +this.value); document.getElementById('pAngleVal').textContent = this.value + '°'">
            <span id="pAngleVal" style="min-width:36px;text-align:right;font-size:.78rem;color:#94a3b8;">0°</span>
          </div>
          <div class="props-row">
            <label>Opacity</label>
            <input type="range" id="pOpacity" min="0" max="100" value="100" oninput="applyProp('opacity', this.value/100); document.getElementById('pOpacityVal').textContent = this.value + '%'">
            <span id="pOpacityVal" style="min-width:36px;text-align:right;font-size:.78rem;color:#94a3b8;">100%</span>
          </div>
          <div style="font-size:.68rem;color:#64748b;margin:.2rem 0 .1rem;" id="pAlignLbl">Align to canvas</div>
          <div class="align-row">
            <button class="align-btn" onclick="alignObj('left')"    title="Align left"><i class="bi bi-align-start"></i></button>
            <button class="align-btn" onclick="alignObj('hcenter')" title="Align horizontal centre"><i class="bi bi-align-center"></i></button>
            <button class="align-btn" onclick="alignObj('right')"   title="Align right"><i class="bi bi-align-end"></i></button>
            <button class="align-btn" onclick="alignObj('top')"     title="Align top"><i class="bi bi-align-top"></i></button>
            <button class="align-btn" onclick="alignObj('vcenter')" title="Align vertical centre"><i class="bi bi-align-middle"></i></button>
            <button class="align-btn" onclick="alignObj('bottom')"  title="Align bottom"><i class="bi bi-align-bottom"></i></button>
            <button class="align-btn" onclick="distributeObjs('h')" title="Distribute horizontally (3+ selected)"><i class="bi bi-distribute-horizontal"></i></button>
            <button class="align-btn" onclick="distributeObjs('v')" title="Distribute vertically (3+ selected)"><i class="bi bi-distribute-vertical"></i></button>
            <button class="align-btn" onclick="flipImg('x')" title="Flip horizontal">⇋</button>
            <button class="align-btn" onclick="flipImg('y')" title="Flip vertical">⥮</button>
            <button class="align-btn" id="btnLockObj" onclick="toggleLockActive()" title="Lock / unlock (locked objects can't be moved or selected on canvas — unlock from Layers)"><i class="bi bi-lock"></i></button>
          </div>
          <div class="props-row">
            <label>Blend</label>
            <select class="props-input props-select" id="pBlend" onchange="applyBlendMode(this.value)" style="font-size:.75rem;">
              <option value="source-over">Normal</option>
              <option value="multiply">Multiply</option>
              <option value="screen">Screen</option>
              <option value="overlay">Overlay</option>
              <option value="darken">Darken</option>
              <option value="lighten">Lighten</option>
              <option value="color-dodge">Dodge</option>
              <option value="color-burn">Burn</option>
              <option value="difference">Difference</option>
              <option value="exclusion">Exclusion</option>
              <option value="hue">Hue</option>
              <option value="saturation">Saturation</option>
              <option value="color">Color</option>
              <option value="luminosity">Luminosity</option>
            </select>
          </div>
        </div>

        <!-- Text properties -->
        <div class="props-section" id="pTextSec" style="display:none;">
          <div class="props-title">Text</div>
          <div class="props-row">
            <label>Font</label>
            <select class="props-input props-select" id="pFont" onchange="applyTextProp('fontFamily', this.value)"></select>
          </div>
          <div class="props-row">
            <label>Size</label>
            <input type="number" class="props-input" id="pFontSize" min="6" max="400" style="width:70px;"
                   oninput="applyTextProp('fontSize', +this.value)">
          </div>
          <div class="props-row">
            <label>Style</label>
            <div class="style-btns">
              <button class="style-btn" id="btnB" onclick="toggleStyle('bold')" title="Bold"><b>B</b></button>
              <button class="style-btn" id="btnI" onclick="toggleStyle('italic')" title="Italic"><i>I</i></button>
              <button class="style-btn" id="btnU" onclick="toggleStyle('underline')" title="Underline"><u>U</u></button>
              <button class="style-btn" id="btnLT" onclick="toggleStyle('linethrough')" title="Strikethrough"><s>S</s></button>
            </div>
          </div>
          <div class="props-row">
            <label>Align</label>
            <div class="align-btns">
              <button class="align-btn" onclick="applyTextProp('textAlign','left')"   title="Left"><i class="bi bi-text-left"></i></button>
              <button class="align-btn" onclick="applyTextProp('textAlign','center')" title="Center"><i class="bi bi-text-center"></i></button>
              <button class="align-btn" onclick="applyTextProp('textAlign','right')"  title="Right"><i class="bi bi-text-right"></i></button>
              <button class="align-btn" onclick="applyTextProp('textAlign','justify')" title="Justify"><i class="bi bi-justify"></i></button>
            </div>
          </div>
          <div class="props-row">
            <label>Color</label>
            <input type="color" id="pTextColor" value="#1f2937" oninput="applyTextProp('fill', this.value)">
          </div>
          <div class="props-row">
            <label>Line H</label>
            <input type="range" id="pLH" min="0.8" max="4" step="0.05" value="1.16"
                   oninput="applyTextProp('lineHeight', +this.value); document.getElementById('pLHVal').textContent = (+this.value).toFixed(2)">
            <span id="pLHVal" style="min-width:30px;font-size:.76rem;color:#94a3b8;">1.16</span>
          </div>
          <div class="props-row">
            <label>Char Sp</label>
            <input type="number" class="props-input" id="pCharSp" min="-20" max="200" value="0" style="width:60px;"
                   oninput="applyTextProp('charSpacing', +this.value)">
          </div>
          <div class="props-row">
            <label>Bg Color</label>
            <input type="color" id="pTextBg" value="#ffffff" oninput="applyTextBg(this.value)">
            <button onclick="applyTextBg('transparent')" style="background:#334155;border:none;color:#e2e8f0;border-radius:5px;padding:.2rem .5rem;font-size:.72rem;cursor:pointer;margin-left:.25rem;">Clear</button>
          </div>
          <div class="props-row" style="flex-direction:column;align-items:flex-start;gap:.3rem;">
            <label style="color:#94a3b8;font-size:.78rem;">Text Effects</label>
            <div class="text-fx-btns">
              <button class="text-fx-btn" onclick="applyTextEffect('neon')"    title="Neon glow using fill color">Neon</button>
              <button class="text-fx-btn" onclick="applyTextEffect('3d')"      title="3D drop shadow">3D</button>
              <button class="text-fx-btn" onclick="applyTextEffect('outline')" title="Stroke outline">Outline</button>
              <button class="text-fx-btn" onclick="applyTextEffect('glow')"    title="Golden glow">Glow</button>
              <button class="text-fx-btn" onclick="applyTextEffect('emboss')"  title="Emboss highlight">Emboss</button>
              <button class="text-fx-btn danger" onclick="applyTextEffect('clear')" title="Remove all effects">✕ Clear</button>
            </div>
          </div>
        </div>

        <!-- Shape / Image properties -->
        <div class="props-section" id="pShapeSec" style="display:none;">
          <div class="props-title">Appearance</div>
          <div class="props-row">
            <label>Fill</label>
            <input type="color" id="pFill" value="#4f46e5" oninput="applyProp('fill', this.value)">
            <label style="margin-left:.4rem;font-size:.75rem;cursor:pointer;display:flex;align-items:center;gap:.25rem;">
              <input type="checkbox" id="pNoFill" onchange="applyProp('fill', this.checked ? 'transparent' : document.getElementById('pFill').value)"> No fill
            </label>
          </div>
          <div class="props-row">
            <label>Stroke</label>
            <input type="color" id="pStroke" value="#000000" oninput="applyProp('stroke', this.value)">
          </div>
          <div class="props-row">
            <label>Stroke W</label>
            <input type="number" class="props-input" id="pStrokeW" min="0" max="50" value="0" style="width:60px;"
                   oninput="applyProp('strokeWidth', +this.value)">
          </div>
          <div class="props-row">
            <label>Radius</label>
            <input type="number" class="props-input" id="pRx" min="0" max="200" value="0" style="width:60px;"
                   oninput="applyRoundCorner(+this.value)" title="Corner radius (Rect only)">
          </div>
          <div class="props-row" style="flex-direction:column;align-items:flex-start;gap:.3rem;">
            <label style="color:#94a3b8;font-size:.78rem;">Pantone / Spot Colors</label>
            <div style="display:flex;flex-wrap:wrap;gap:.3rem;">
              <span class="spot-color-chip" style="background:#E4002B;" onclick="applySpotColor('Pantone 185 C')" title="Pantone 185 C — Red">185 C</span>
              <span class="spot-color-chip" style="background:#0033A0;" onclick="applySpotColor('Pantone 286 C')" title="Pantone 286 C — Blue">286 C</span>
              <span class="spot-color-chip" style="background:#00A550;" onclick="applySpotColor('Pantone 354 C')" title="Pantone 354 C — Green">354 C</span>
              <span class="spot-color-chip" style="background:#FFC72C;color:#000;" onclick="applySpotColor('Pantone 123 C')" title="Pantone 123 C — Yellow">123 C</span>
              <span class="spot-color-chip" style="background:#2D2926;" onclick="applySpotColor('Pantone Black')" title="Pantone Black">Black</span>
            </div>
          </div>
        </div>

        <!-- Gradient Fill (shapes + text) -->
        <div class="props-section" id="pGradSec" style="display:none;">
          <div class="props-title">Gradient Fill</div>
          <div class="grad-preview" id="gradPreview"></div>
          <div style="display:flex;gap:.3rem;margin-bottom:.4rem;align-items:flex-end;">
            <div style="flex:1;text-align:center;">
              <span class="props-label" style="display:block;text-align:center;">Start</span>
              <input type="color" id="gC1" value="#4f46e5" oninput="previewGrad()" style="width:100%;height:28px;border:1px solid #334155;border-radius:5px;cursor:pointer;background:none;padding:1px;">
            </div>
            <div style="flex:1;text-align:center;">
              <span class="props-label" style="display:block;text-align:center;">Mid</span>
              <input type="color" id="gCMid" value="#7c3aed" oninput="previewGrad()" style="width:100%;height:28px;border:1px solid #334155;border-radius:5px;cursor:pointer;background:none;padding:1px;">
            </div>
            <div style="flex:1;text-align:center;">
              <span class="props-label" style="display:block;text-align:center;">End</span>
              <input type="color" id="gC2" value="#ec4899" oninput="previewGrad()" style="width:100%;height:28px;border:1px solid #334155;border-radius:5px;cursor:pointer;background:none;padding:1px;">
            </div>
          </div>
          <!-- Direction Quick Buttons -->
          <div style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#475569;margin-bottom:.3rem;">Direction</div>
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.25rem;margin-bottom:.45rem;">
            <button class="grad-dir-btn gdir-btn" id="gDir0"   onclick="setGradDir(0)"   title="Left → Right">← →</button>
            <button class="grad-dir-btn gdir-btn" id="gDir180" onclick="setGradDir(180)" title="Right → Left">→ ←</button>
            <button class="grad-dir-btn gdir-btn" id="gDir90"  onclick="setGradDir(90)"  title="Top → Bottom">↓</button>
            <button class="grad-dir-btn gdir-btn" id="gDir270" onclick="setGradDir(270)" title="Bottom → Top">↑</button>
            <button class="grad-dir-btn gdir-btn" id="gDir45"  onclick="setGradDir(45)"  title="TL → BR">↘</button>
            <button class="grad-dir-btn gdir-btn" id="gDir135" onclick="setGradDir(135)" title="TR → BL">↙</button>
            <button class="grad-dir-btn gdir-btn" id="gDir225" onclick="setGradDir(225)" title="BR → TL">↖</button>
            <button class="grad-dir-btn gdir-btn" id="gDir315" onclick="setGradDir(315)" title="BL → TR">↗</button>
          </div>
          <div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.45rem;">
            <span class="props-label" style="min-width:32px;flex-shrink:0;font-size:.7rem;">Custom°</span>
            <input type="range" id="gAngle" min="0" max="360" value="0" oninput="previewGrad();document.getElementById('gAngleVal').textContent=this.value+'°'" style="flex:1;accent-color:#6366f1;">
            <span id="gAngleVal" style="min-width:30px;font-size:.72rem;color:#94a3b8;">0°</span>
          </div>
          <!-- Type + interactive drag -->
          <div style="display:flex;gap:.25rem;flex-wrap:wrap;margin-bottom:.5rem;">
            <button class="grad-dir-btn" id="gBtnLinear"  onclick="applyGradient('linear')"  style="font-size:.68rem;">↗ Linear</button>
            <button class="grad-dir-btn" id="gBtnRadial"  onclick="applyGradient('radial')"  style="font-size:.68rem;">◎ Radial</button>
            <button class="grad-dir-btn" id="gBtnDiamond" onclick="applyGradient('diamond')" style="font-size:.68rem;">◇ Diamond</button>
            <button class="grad-dir-btn" id="gBtnConical" onclick="applyGradient('conical')" style="font-size:.68rem;">⊙ Conical</button>
            <button class="grad-dir-btn" id="gBtnDrag"    onclick="toggleInterFillDrag()"   style="font-size:.68rem;color:#f59e0b;border-color:#f59e0b;" title="Drag Mode — click &amp; drag on canvas to set gradient direction">⤢ Drag</button>
            <button class="grad-dir-btn" onclick="removeGradient()" style="color:#f87171;border-color:#7f1d1d;font-size:.68rem;">✕ Clear</button>
          </div>
          <div id="dragFillHint" style="display:none;font-size:.7rem;color:#f59e0b;background:rgba(245,158,11,.1);border:1px solid #f59e0b;border-radius:6px;padding:.3rem .5rem;margin-bottom:.4rem;">
            <i class="bi bi-mouse2"></i> Drag on object to set gradient direction. Click Drag again to exit.
          </div>
          <div style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#475569;margin-bottom:.35rem;">Quick Presets</div>
          <div class="grad-presets">
            <div class="grad-preset-swatch" style="background:linear-gradient(135deg,#b91c1c,#f59e0b);" onclick="applyGradPreset('#b91c1c','#ef4444','#f59e0b')" title="Red → Yellow (School Card)"></div>
            <div class="grad-preset-swatch" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);" onclick="applyGradPreset('#1e3a8a','#1d4ed8','#3b82f6')" title="Navy Blue"></div>
            <div class="grad-preset-swatch" style="background:linear-gradient(135deg,#1e3a8a,#7c3aed);" onclick="applyGradPreset('#1e3a8a','#4f46e5','#7c3aed')" title="Royal Blue-Purple"></div>
            <div class="grad-preset-swatch" style="background:linear-gradient(135deg,#7f1d1d,#dc2626);" onclick="applyGradPreset('#7f1d1d','#b91c1c','#dc2626')" title="Deep Red"></div>
            <div class="grad-preset-swatch" style="background:linear-gradient(135deg,#064e3b,#10b981);" onclick="applyGradPreset('#064e3b','#059669','#10b981')" title="Forest Green"></div>
            <div class="grad-preset-swatch" style="background:linear-gradient(135deg,#4a044e,#a855f7);" onclick="applyGradPreset('#4a044e','#7e22ce','#a855f7')" title="Deep Purple"></div>
            <div class="grad-preset-swatch" style="background:linear-gradient(135deg,#1c1917,#78716c);" onclick="applyGradPreset('#1c1917','#44403c','#78716c')" title="Dark Slate"></div>
            <div class="grad-preset-swatch" style="background:linear-gradient(135deg,#7f1d1d,#1e3a8a);" onclick="applyGradPreset('#7f1d1d','#44403c','#1e3a8a')" title="Red-Navy (ID Card)"></div>
            <div class="grad-preset-swatch" style="background:linear-gradient(135deg,#0c4a6e,#06b6d4);" onclick="applyGradPreset('#0c4a6e','#0284c7','#06b6d4')" title="Deep Ocean"></div>
            <div class="grad-preset-swatch" style="background:linear-gradient(135deg,#fff7ed,#f97316);" onclick="applyGradPreset('#fff7ed','#fb923c','#f97316')" title="Sunrise Orange"></div>
            <div class="grad-preset-swatch" style="background:linear-gradient(135deg,#f0fdf4,#16a34a);" onclick="applyGradPreset('#f0fdf4','#4ade80','#16a34a')" title="Fresh Mint"></div>
            <div class="grad-preset-swatch" style="background:linear-gradient(135deg,#581c87,#ec4899);" onclick="applyGradPreset('#581c87','#a855f7','#ec4899')" title="Purple-Pink"></div>
          </div>
        </div>

        <!-- Pattern Fill -->
        <div class="props-section" id="pPatternSec" style="display:none;">
          <div class="props-title">Pattern Fill <span style="font-size:.62rem;color:#6366f1;font-weight:600;">CorelDRAW Style</span></div>
          <div class="pat-type-grid">
            <div class="pat-type-btn sel" id="patBtnHStripe" onclick="selectPatType('hstripe',this)">
              <svg width="32" height="22"><rect width="32" height="22" fill="#0f172a"/><line x1="0" y1="5" x2="32" y2="5" stroke="#6366f1" stroke-width="3"/><line x1="0" y1="13" x2="32" y2="13" stroke="#6366f1" stroke-width="3"/></svg>
              H-Stripe
            </div>
            <div class="pat-type-btn" id="patBtnVStripe" onclick="selectPatType('vstripe',this)">
              <svg width="32" height="22"><rect width="32" height="22" fill="#0f172a"/><line x1="8" y1="0" x2="8" y2="22" stroke="#6366f1" stroke-width="3"/><line x1="22" y1="0" x2="22" y2="22" stroke="#6366f1" stroke-width="3"/></svg>
              V-Stripe
            </div>
            <div class="pat-type-btn" id="patBtnDiag" onclick="selectPatType('diagonal',this)">
              <svg width="32" height="22"><rect width="32" height="22" fill="#0f172a"/><line x1="0" y1="22" x2="22" y2="0" stroke="#6366f1" stroke-width="2.5"/><line x1="10" y1="22" x2="32" y2="0" stroke="#6366f1" stroke-width="2.5"/></svg>
              Diagonal
            </div>
            <div class="pat-type-btn" id="patBtnDots" onclick="selectPatType('dots',this)">
              <svg width="32" height="22"><rect width="32" height="22" fill="#0f172a"/><circle cx="8" cy="7" r="3" fill="#6366f1"/><circle cx="24" cy="7" r="3" fill="#6366f1"/><circle cx="8" cy="17" r="3" fill="#6366f1"/><circle cx="24" cy="17" r="3" fill="#6366f1"/></svg>
              Dots
            </div>
            <div class="pat-type-btn" id="patBtnCheck" onclick="selectPatType('checker',this)">
              <svg width="32" height="22"><rect width="32" height="22" fill="#0f172a"/><rect x="0" y="0" width="11" height="11" fill="#6366f1"/><rect x="11" y="11" width="11" height="11" fill="#6366f1"/><rect x="22" y="0" width="10" height="11" fill="#6366f1"/><rect x="0" y="11" width="0" height="11" fill="#6366f1"/></svg>
              Checker
            </div>
            <div class="pat-type-btn" id="patBtnCross" onclick="selectPatType('crosshatch',this)">
              <svg width="32" height="22"><rect width="32" height="22" fill="#0f172a"/><line x1="0" y1="11" x2="32" y2="11" stroke="#6366f1" stroke-width="1.5"/><line x1="16" y1="0" x2="16" y2="22" stroke="#6366f1" stroke-width="1.5"/><line x1="0" y1="22" x2="22" y2="0" stroke="#6366f1" stroke-width="1"/><line x1="10" y1="22" x2="32" y2="0" stroke="#6366f1" stroke-width="1"/></svg>
              Crosshatch
            </div>
            <div class="pat-type-btn" id="patBtnWave" onclick="selectPatType('waves',this)">
              <svg width="32" height="22"><rect width="32" height="22" fill="#0f172a"/><path d="M0,7 Q8,0 16,7 Q24,14 32,7" fill="none" stroke="#6366f1" stroke-width="2"/><path d="M0,16 Q8,9 16,16 Q24,23 32,16" fill="none" stroke="#6366f1" stroke-width="2"/></svg>
              Waves
            </div>
            <div class="pat-type-btn" id="patBtnBrick" onclick="selectPatType('brick',this)">
              <svg width="32" height="22"><rect width="32" height="22" fill="#0f172a"/><rect x="1" y="1" width="14" height="8" rx="1" fill="none" stroke="#6366f1" stroke-width="1.5"/><rect x="17" y="1" width="14" height="8" rx="1" fill="none" stroke="#6366f1" stroke-width="1.5"/><rect x="7" y="12" width="14" height="8" rx="1" fill="none" stroke="#6366f1" stroke-width="1.5"/></svg>
              Brick
            </div>
          </div>
          <div class="pat-color-row">
            <label>Foreground</label>
            <input type="color" id="patFg" value="#6366f1" oninput="previewPattern()" style="width:32px;height:26px;border:1px solid #334155;border-radius:5px;cursor:pointer;background:none;padding:1px 2px;">
          </div>
          <div class="pat-color-row">
            <label>Background</label>
            <input type="color" id="patBg" value="#ffffff" oninput="previewPattern()" style="width:32px;height:26px;border:1px solid #334155;border-radius:5px;cursor:pointer;background:none;padding:1px 2px;">
            <label style="margin-left:.3rem;font-size:.72rem;display:flex;align-items:center;gap:.2rem;cursor:pointer;">
              <input type="checkbox" id="patBgTrans" onchange="previewPattern()"> Transparent
            </label>
          </div>
          <div class="pat-size-row">
            <label>Size</label>
            <input type="range" id="patSize" min="6" max="60" value="16" oninput="previewPattern();document.getElementById('patSizeVal').textContent=this.value+'px'" style="flex:1;accent-color:#6366f1;">
            <span id="patSizeVal" style="min-width:36px;font-size:.72rem;color:#94a3b8;">16px</span>
          </div>
          <div id="patPreviewBox" style="height:42px;border-radius:8px;border:1px solid #334155;margin-bottom:.4rem;"></div>
          <div style="display:flex;gap:.35rem;">
            <button onclick="applyPatternFill()" style="flex:1;background:#6366f1;border:none;color:#fff;border-radius:8px;padding:.4rem;font-size:.78rem;font-weight:700;cursor:pointer;"><i class="bi bi-paint-bucket"></i> Apply Pattern</button>
            <button onclick="removeGradient()" style="background:#334155;border:none;color:#e2e8f0;border-radius:8px;padding:.4rem .7rem;font-size:.78rem;cursor:pointer;">✕</button>
          </div>
        </div>

        <!-- Fade / Transparency -->
        <div class="props-section" id="pFadeSec" style="display:none;">
          <div class="props-title">Transparency / Fade <span style="font-size:.62rem;color:#06b6d4;font-weight:600;">Interactive</span></div>
          <div style="font-size:.73rem;color:#64748b;margin-bottom:.4rem;">Apply gradient transparency to object edges</div>
          <div class="fade-btn-grid">
            <button class="fade-btn" onclick="applyFade('top')">▲ Top<br>Fade</button>
            <button class="fade-btn" onclick="applyFade('bottom')">▼ Bottom<br>Fade</button>
            <button class="fade-btn" onclick="applyFade('left')">◀ Left<br>Fade</button>
            <button class="fade-btn" onclick="applyFade('right')">▶ Right<br>Fade</button>
            <button class="fade-btn" onclick="applyFade('center')">◎ Center<br>Vignette</button>
            <button class="fade-btn" onclick="applyFade('edges')">⬚ Edge<br>Fade</button>
          </div>
          <div class="props-row" style="margin-top:.4rem;">
            <label>Intensity</label>
            <input type="range" id="fadeIntensity" min="10" max="100" value="70" style="flex:1;accent-color:#06b6d4;" oninput="document.getElementById('fadeIntVal').textContent=this.value+'%'">
            <span id="fadeIntVal" style="min-width:36px;font-size:.72rem;color:#94a3b8;">70%</span>
          </div>
          <button onclick="removeFadeEffect()" class="fade-btn danger" style="width:100%;margin-top:.3rem;">✕ Remove Fade</button>
        </div>

        <!-- Text stroke / outline -->
        <div class="props-section" id="pTextOutlineSec" style="display:none;">
          <div class="props-title">Text Outline</div>
          <div class="props-row">
            <label>Color</label>
            <input type="color" id="pTStroke" value="#000000" oninput="applyTextProp('stroke', this.value)">
          </div>
          <div class="props-row">
            <label>Width</label>
            <input type="number" class="props-input" id="pTStrokeW" min="0" max="20" value="0" style="width:60px;"
                   oninput="applyTextProp('strokeWidth', +this.value)">
          </div>
        </div>

        <!-- Image filters (shown only for images) -->
        <div class="props-section" id="pImgSec" style="display:none;">
          <div class="props-title">Image Filters</div>
          <div class="props-row">
            <label style="min-width:76px;">Brightness</label>
            <input type="range" id="fBright" min="-1" max="1" step="0.01" value="0" oninput="applyImgFilter()">
            <span id="fBrightVal" style="min-width:30px;font-size:.75rem;color:#94a3b8;">0</span>
          </div>
          <div class="props-row">
            <label style="min-width:76px;">Contrast</label>
            <input type="range" id="fContrast" min="-1" max="1" step="0.01" value="0" oninput="applyImgFilter()">
            <span id="fContrastVal" style="min-width:30px;font-size:.75rem;color:#94a3b8;">0</span>
          </div>
          <div class="props-row">
            <label style="min-width:76px;">Saturation</label>
            <input type="range" id="fSat" min="-1" max="1" step="0.01" value="0" oninput="applyImgFilter()">
            <span id="fSatVal" style="min-width:30px;font-size:.75rem;color:#94a3b8;">0</span>
          </div>
          <div class="props-row">
            <label style="min-width:76px;">Blur</label>
            <input type="range" id="fBlur" min="0" max="1" step="0.01" value="0" oninput="applyImgFilter()">
            <span id="fBlurVal" style="min-width:30px;font-size:.75rem;color:#94a3b8;">0</span>
          </div>
          <div class="props-row">
            <label style="min-width:76px;">Noise</label>
            <input type="range" id="fNoise" min="0" max="600" step="5" value="0" oninput="applyImgFilter()">
            <span id="fNoiseVal" style="min-width:30px;font-size:.75rem;color:#94a3b8;">0</span>
          </div>
          <div class="props-row">
            <label style="min-width:76px;">Hue</label>
            <input type="range" id="fHue" min="-180" max="180" step="5" value="0" oninput="applyImgFilter(); document.getElementById('fHueVal').textContent = this.value">
            <span id="fHueVal" style="min-width:30px;font-size:.75rem;color:#94a3b8;">0</span>
          </div>
          <div class="props-row">
            <label style="min-width:76px;">Vibrance</label>
            <input type="range" id="fVibrance" min="-1" max="1" step="0.05" value="0" oninput="applyImgFilter(); document.getElementById('fVibranceVal').textContent = (+this.value).toFixed(2)">
            <span id="fVibranceVal" style="min-width:30px;font-size:.75rem;color:#94a3b8;">0</span>
          </div>
          <div class="props-row">
            <label style="min-width:76px;">Gamma</label>
            <input type="range" id="fGamma" min="0.2" max="2.2" step="0.1" value="1" oninput="applyImgFilter(); document.getElementById('fGammaVal').textContent = (+this.value).toFixed(1)">
            <span id="fGammaVal" style="min-width:30px;font-size:.75rem;color:#94a3b8;">1</span>
          </div>
          <div class="props-row" style="flex-wrap:wrap;gap:.4rem;">
            <button class="style-btn" id="btnGray"    onclick="toggleImgPreset('gray')"    title="Grayscale">Gray</button>
            <button class="style-btn" id="btnSepia"   onclick="toggleImgPreset('sepia')"   title="Sepia">Sepia</button>
            <button class="style-btn" id="btnInvert"  onclick="toggleImgPreset('invert')"  title="Invert">Invert</button>
            <button class="style-btn" id="btnVintage" onclick="toggleImgPreset('vintage')" title="Vintage">Vintage</button>
            <button class="style-btn" id="btnPolaroid" onclick="toggleImgPreset('polaroid')" title="Polaroid">Polaroid</button>
            <button class="style-btn" onclick="resetImgFilters()" title="Reset all filters" style="color:#f87171;">Reset</button>
          </div>
          <div style="margin-top:.6rem;border-top:1px solid #334155;padding-top:.6rem;">
            <div class="props-title" style="margin-bottom:.5rem;">Flip</div>
            <div style="display:flex;gap:.5rem;margin-bottom:.5rem;">
              <button class="style-btn" onclick="flipImg('x')" title="Flip Horizontal" style="flex:1;width:auto;padding:.3rem;">↔ Flip H</button>
              <button class="style-btn" onclick="flipImg('y')" title="Flip Vertical" style="flex:1;width:auto;padding:.3rem;">↕ Flip V</button>
            </div>
          </div>
          <div style="border-top:1px solid #334155;padding-top:.6rem;">
            <div class="props-title" style="margin-bottom:.4rem;">Background Remove</div>
            <div class="props-row">
              <label style="min-width:70px;">Tolerance</label>
              <input type="range" id="bgTol" min="5" max="80" value="30" style="flex:1;accent-color:#6366f1;" oninput="document.getElementById('bgTolVal').textContent=this.value">
              <span id="bgTolVal" style="min-width:24px;font-size:.75rem;color:#94a3b8;">30</span>
            </div>
            <button onclick="removeBgFromImage(+document.getElementById('bgTol').value)"
              style="width:100%;background:#7c3aed;border:none;color:#fff;border-radius:7px;padding:.4rem;font-size:.78rem;font-weight:600;cursor:pointer;margin-top:.2rem;">
              <i class="bi bi-magic"></i> Smart Remove BG
            </button>
          </div>
          <div style="border-top:1px solid #334155;padding-top:.6rem;margin-top:.4rem;">
            <div class="props-title" style="margin-bottom:.4rem;">Clip Mask</div>
            <div class="clip-btns">
              <button class="clip-btn" onclick="applyClipMask('circle')"><i class="bi bi-circle"></i> Circle</button>
              <button class="clip-btn" onclick="applyClipMask('rounded')"><i class="bi bi-square-rounded"></i> Rounded</button>
              <button class="clip-btn" onclick="applyClipMask('star')"><i class="bi bi-star"></i> Star</button>
              <button class="clip-btn" onclick="applyClipMask('arch')"><i class="bi bi-rainbow"></i> Arch</button>
              <button class="clip-btn" onclick="applyClipMask('triangle')"><i class="bi bi-triangle"></i> Triangle</button>
              <button class="clip-btn" onclick="applyClipMask('diamond')"><i class="bi bi-diamond"></i> Diamond</button>
              <button class="clip-btn" onclick="applyClipMask('hexagon')"><i class="bi bi-hexagon"></i> Hexagon</button>
              <button class="clip-btn" onclick="applyClipMask('heart')"><i class="bi bi-heart"></i> Heart</button>
              <button class="clip-btn danger" onclick="removeClipMask()">✕ Remove Mask</button>
            </div>
          </div>
        </div>

        <!-- Shadow -->
        <div class="props-section">
          <div class="props-title">Shadow</div>
          <div class="props-row">
            <label>Enable</label>
            <input type="checkbox" id="pShadow" onchange="toggleShadow(this.checked)">
            <button onclick="addRealisticShadow()" style="margin-left:auto;background:#334155;border:none;color:#e2e8f0;border-radius:5px;padding:.2rem .6rem;font-size:.7rem;cursor:pointer;" title="Add drop shadow + mirror reflection">+ Reflection</button>
          </div>
          <div id="pShadowOpts" style="display:none;">
            <div class="props-row">
              <label>Color</label>
              <input type="color" id="pShadowColor" value="#000000" oninput="updateShadow()">
            </div>
            <div class="props-row">
              <label>Blur</label>
              <input type="range" id="pShadowBlur" min="0" max="50" value="10" oninput="updateShadow()">
            </div>
            <div class="props-row">
              <label>Offset X</label>
              <input type="number" class="props-input" id="pShadowX" value="3" style="width:60px;" oninput="updateShadow()">
            </div>
            <div class="props-row">
              <label>Offset Y</label>
              <input type="number" class="props-input" id="pShadowY" value="3" style="width:60px;" oninput="updateShadow()">
            </div>
          </div>
        </div>
      </div>

      <!-- Layers -->
      <div class="props-section">
        <div class="props-title" style="display:flex;justify-content:space-between;">
          Layers
          <span style="font-size:.65rem;color:#475569;">top → bottom</span>
        </div>
        <div id="layersList" style="min-height:40px;"></div>
      </div>
    </div>
    </div><!-- /ed-props-scroll -->
  </div><!-- /ed-props -->
  <div id="edSheetBackdrop" onclick="closeEdPanel()"></div>
</div>

<!-- Hidden image upload -->
<input type="file" id="imgUpload" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,image/bmp" style="display:none" onchange="handleImgUpload(event)">

<!-- Professional Text Warp Modal -->
<div id="warpModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:2000;align-items:center;justify-content:center;">
  <div style="background:#1e293b;border:1px solid #334155;border-radius:16px;padding:0;width:900px;max-width:98vw;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;">

    <!-- Header -->
    <div style="padding:.9rem 1.2rem;border-bottom:1px solid #334155;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
      <div>
        <div style="font-size:1.05rem;font-weight:700;color:#e2e8f0;"><i class="bi bi-bezier2 me-2" style="color:#6366f1;"></i>Text Warp Studio</div>
        <div style="font-size:.72rem;color:#64748b;margin-top:.15rem;">Select a text object — choose warp type, adjust controls, then Apply</div>
      </div>
      <button onclick="closeWarpModal()" style="background:transparent;border:none;color:#94a3b8;font-size:1.4rem;cursor:pointer;line-height:1;padding:.2rem .5rem;">✕</button>
    </div>

    <!-- Body: Preview + Controls -->
    <div style="display:flex;flex:1;min-height:0;overflow:hidden;">

      <!-- Left: Live Preview Canvas -->
      <div style="flex:1;background:#0f172a;display:flex;align-items:center;justify-content:center;padding:1.5rem;position:relative;overflow:hidden;">
        <canvas id="warpPreviewCanvas" width="500" height="350" style="background:#1e293b;border-radius:12px;border:1px solid #334155;max-width:100%;max-height:100%;"></canvas>
        <div style="position:absolute;bottom:1rem;left:50%;transform:translateX(-50%);font-size:.7rem;color:#475569;background:rgba(15,23,42,.9);padding:.3rem .8rem;border-radius:999px;border:1px solid #334155;">
          <i class="bi bi-eye me-1"></i>Live Preview
        </div>
      </div>

      <!-- Right: Controls Panel -->
      <div style="width:320px;background:#1e293b;border-left:1px solid #334155;overflow-y:auto;padding:1rem;scrollbar-width:thin;">

        <!-- Warp Type Grid -->
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.5rem;">Warp Type</div>
        <div class="warp-types" id="warpTypeGrid">
          <div class="warp-card sel" data-warp="arc-up" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M5,35 Q40,5 75,35" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="28" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Arc Up</text></svg>
            <span>Arc Up</span>
          </div>
          <div class="warp-card" data-warp="arc-down" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M5,5 Q40,35 75,5" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="18" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Arc Down</text></svg>
            <span>Arc Down</span>
          </div>
          <div class="warp-card" data-warp="wave" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M2,20 C20,5 30,35 40,20 S60,5 78,20" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="22" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Wave</text></svg>
            <span>Wave</span>
          </div>
          <div class="warp-card" data-warp="flag" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M2,15 C25,5 55,35 78,25" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="20" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Flag</text></svg>
            <span>Flag</span>
          </div>
          <div class="warp-card" data-warp="inflate" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M2,20 Q20,2 40,2 Q60,2 78,20" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="18" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Inflate</text></svg>
            <span>Inflate</span>
          </div>
          <div class="warp-card" data-warp="deflate" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M2,5 Q40,38 78,5" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="22" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Deflate</text></svg>
            <span>Deflate</span>
          </div>
          <div class="warp-card" data-warp="bulge" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><ellipse cx="40" cy="20" rx="35" ry="15" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="24" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Bulge</text></svg>
            <span>Bulge</span>
          </div>
          <div class="warp-card" data-warp="pinch" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M5,5 Q40,20 75,5 M5,35 Q40,20 75,35" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="24" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Pinch</text></svg>
            <span>Pinch</span>
          </div>
          <div class="warp-card" data-warp="circle" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><circle cx="40" cy="20" r="16" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="24" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Circle</text></svg>
            <span>Circle</span>
          </div>
          <div class="warp-card" data-warp="arch" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M5,38 Q40,-10 75,38" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="28" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Arch</text></svg>
            <span>Arch</span>
          </div>
          <div class="warp-card" data-warp="bridge" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M5,38 Q20,5 40,5 Q60,5 75,38" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="28" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Bridge</text></svg>
            <span>Bridge</span>
          </div>
          <div class="warp-card" data-warp="sine" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M2,20 Q20,5 40,20 T78,20" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="12" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Sine</text></svg>
            <span>Sine</span>
          </div>
          <div class="warp-card" data-warp="zigzag" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M5,20 L20,5 L40,35 L60,5 L75,20" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="24" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">ZigZag</text></svg>
            <span>ZigZag</span>
          </div>
          <div class="warp-card" data-warp="stairs" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M5,35 L20,35 L20,20 L40,20 L40,5 L60,5 L60,20 L75,20" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="32" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Stairs</text></svg>
            <span>Stairs</span>
          </div>
          <div class="warp-card" data-warp="spiral" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M40,20 m-18,0 a18,18 0 1,1 36,0 a18,18 0 1,1 -36,0" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="24" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Spiral</text></svg>
            <span>Spiral</span>
          </div>
          <div class="warp-card" data-warp="fisheye" onclick="selectWarpType(this)">
            <svg width="80" height="40" viewBox="0 0 80 40"><path d="M5,20 Q40,0 75,20 Q40,40 5,20" fill="none" stroke="#a5b4fc" stroke-width="2.5"/><text x="40" y="24" text-anchor="middle" font-size="11" fill="#a5b4fc" font-family="Arial">Fisheye</text></svg>
            <span>Fisheye</span>
          </div>
        </div>

        <!-- Sliders -->
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.5rem;margin-top:.5rem;">Controls</div>

        <div style="margin-bottom:.6rem;">
          <div style="display:flex;justify-content:space-between;margin-bottom:.2rem;">
            <label style="font-size:.76rem;color:#94a3b8;">Intensity / Curve</label>
            <span id="warpAmtVal" style="font-size:.74rem;color:#e2e8f0;font-weight:600;">50%</span>
          </div>
          <input type="range" id="warpAmount" min="0" max="150" value="50" oninput="onWarpSliderChange()" style="width:100%;accent-color:#6366f1;height:6px;">
        </div>
        <div style="margin-bottom:.6rem;">
          <div style="display:flex;justify-content:space-between;margin-bottom:.2rem;">
            <label style="font-size:.76rem;color:#94a3b8;">Spread / Width</label>
            <span id="warpSpreadVal" style="font-size:.74rem;color:#e2e8f0;font-weight:600;">100%</span>
          </div>
          <input type="range" id="warpSpread" min="50" max="200" value="100" oninput="onWarpSliderChange()" style="width:100%;accent-color:#6366f1;height:6px;">
        </div>
        <div style="margin-bottom:.6rem;">
          <div style="display:flex;justify-content:space-between;margin-bottom:.2rem;">
            <label style="font-size:.76rem;color:#94a3b8;">Letter Spacing</label>
            <span id="warpSpacingVal" style="font-size:.74rem;color:#e2e8f0;font-weight:600;">0</span>
          </div>
          <input type="range" id="warpSpacing" min="-10" max="30" value="0" oninput="onWarpSliderChange()" style="width:100%;accent-color:#6366f1;height:6px;">
        </div>
        <div style="margin-bottom:.8rem;">
          <div style="display:flex;justify-content:space-between;margin-bottom:.2rem;">
            <label style="font-size:.76rem;color:#94a3b8;">3D Depth</label>
            <span id="warpDepthVal" style="font-size:.74rem;color:#e2e8f0;font-weight:600;">0</span>
          </div>
          <input type="range" id="warpDepth" min="0" max="50" value="0" oninput="onWarpSliderChange()" style="width:100%;accent-color:#6366f1;height:6px;">
        </div>

        <!-- Options -->
        <div style="display:flex;gap:.6rem;margin-bottom:1rem;flex-wrap:wrap;">
          <label style="display:flex;align-items:center;gap:.3rem;font-size:.74rem;color:#94a3b8;cursor:pointer;">
            <input type="checkbox" id="warpSmooth" checked onchange="onWarpSliderChange()"> Smooth
          </label>
          <label style="display:flex;align-items:center;gap:.3rem;font-size:.74rem;color:#94a3b8;cursor:pointer;">
            <input type="checkbox" id="warpReverse" onchange="onWarpSliderChange()"> Reverse
          </label>
          <label style="display:flex;align-items:center;gap:.3rem;font-size:.74rem;color:#94a3b8;cursor:pointer;">
            <input type="checkbox" id="warpPreserveSize" checked onchange="onWarpSliderChange()"> Keep Size
          </label>
        </div>

        <!-- Action Buttons -->
        <div style="display:flex;gap:.5rem;">
          <button onclick="closeWarpModal()" style="flex:1;background:transparent;border:1px solid #334155;color:#94a3b8;border-radius:8px;padding:.5rem;cursor:pointer;font-size:.82rem;">Cancel</button>
          <button onclick="applyTextWarp()" style="flex:1;background:#4f46e5;border:none;color:#fff;border-radius:8px;padding:.5rem;cursor:pointer;font-size:.82rem;font-weight:600;">
            <i class="bi bi-magic me-1"></i>Apply Warp
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     3D TEXT STUDIO MODAL
═══════════════════════════════════════════════════════════════════ -->
<div id="studio3DModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:2100;align-items:center;justify-content:center;">
  <div style="background:#1e293b;border:1px solid #334155;border-radius:18px;padding:0;width:1060px;max-width:99vw;max-height:96vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 32px 80px rgba(0,0,0,.8);">

    <!-- Header -->
    <div style="padding:.9rem 1.3rem;border-bottom:1px solid #334155;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background:linear-gradient(90deg,#0f172a,#1e293b);">
      <div style="display:flex;align-items:center;gap:.8rem;">
        <div style="width:36px;height:36px;background:linear-gradient(135deg,#6366f1,#a855f7,#ec4899);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;">🎲</div>
        <div>
          <div style="font-size:1.1rem;font-weight:800;color:#e2e8f0;letter-spacing:-.02em;">3D Text Studio</div>
          <div style="font-size:.71rem;color:#64748b;margin-top:.1rem;">65+ Pro Effects · Fully Customizable · Live Preview · <span style="color:#a855f7;">🎲 Randomize</span></div>
        </div>
      </div>
      <button onclick="close3DStudio()" style="background:rgba(255,255,255,.06);border:1px solid #334155;color:#94a3b8;font-size:1.25rem;cursor:pointer;padding:.3rem .65rem;border-radius:9px;line-height:1;transition:all .15s;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='rgba(255,255,255,.06)'">✕</button>
    </div>

    <!-- Body -->
    <div style="display:flex;flex:1;min-height:0;overflow:hidden;">

      <!-- LEFT: Effect Categories + Grid -->
      <div style="width:310px;background:#0a0f1a;border-right:1px solid #1e293b;display:flex;flex-direction:column;flex-shrink:0;overflow:hidden;">

        <!-- Search + Randomize -->
        <div style="padding:.55rem .65rem;border-bottom:1px solid #1e293b;display:flex;gap:.4rem;align-items:center;">
          <input id="fx3DSearch" type="text" placeholder="🔍 Search effects…"
            style="flex:1;background:#1e293b;border:1px solid #334155;border-radius:7px;color:#e2e8f0;font-size:.76rem;padding:.35rem .6rem;outline:none;"
            oninput="filter3DEffects(this.value)">
          <button onclick="randomize3DEffect()" title="Surprise me! (Random Effect)"
            style="background:#312e81;border:1px solid #4338ca;color:#a5b4fc;border-radius:7px;padding:.35rem .55rem;cursor:pointer;font-size:.9rem;line-height:1;flex-shrink:0;transition:all .13s;"
            onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#312e81'">🎲</button>
        </div>

        <!-- Category tabs -->
        <div id="fx3DCatTabs" style="display:flex;flex-wrap:wrap;gap:.3rem;padding:.6rem .7rem;border-bottom:1px solid #1e293b;">
          <button class="fx3d-cat active" data-cat="all"    onclick="set3DCat(this,'all')">All</button>
          <button class="fx3d-cat" data-cat="3d"            onclick="set3DCat(this,'3d')">3D</button>
          <button class="fx3d-cat" data-cat="4d"            onclick="set3DCat(this,'4d')">4D</button>
          <button class="fx3d-cat" data-cat="5d"            onclick="set3DCat(this,'5d')">5D</button>
          <button class="fx3d-cat" data-cat="blend"         onclick="set3DCat(this,'blend')">Blend</button>
          <button class="fx3d-cat" data-cat="neon"          onclick="set3DCat(this,'neon')">Neon</button>
          <button class="fx3d-cat" data-cat="metal"         onclick="set3DCat(this,'metal')">Metal</button>
          <button class="fx3d-cat" data-cat="fire"          onclick="set3DCat(this,'fire')">Fire</button>
          <button class="fx3d-cat" data-cat="ice"           onclick="set3DCat(this,'ice')">Ice</button>
          <button class="fx3d-cat" data-cat="retro"         onclick="set3DCat(this,'retro')">Retro</button>
          <button class="fx3d-cat" data-cat="glass"         onclick="set3DCat(this,'glass')">Glass</button>
          <button class="fx3d-cat" data-cat="hologram"      onclick="set3DCat(this,'hologram')">Holo</button>
        </div>

        <!-- Effect cards list -->
        <div id="fx3DList" style="flex:1;overflow-y:auto;padding:.45rem;scrollbar-width:thin;scrollbar-color:#334155 transparent;display:grid;grid-template-columns:repeat(3,1fr);gap:.3rem;align-content:start;">
          <!-- Injected by JS -->
        </div>
      </div>

      <!-- CENTER: Live Canvas Preview -->
      <div style="flex:1;background:#0f172a;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.2rem;gap:.8rem;position:relative;overflow:hidden;">
        <!-- Checkerboard bg hint -->
        <div style="position:absolute;inset:0;background-image:radial-gradient(circle,#1e293b 1px,transparent 1px);background-size:28px 28px;opacity:.4;pointer-events:none;"></div>
        <canvas id="canvas3DPreview" width="560" height="300" style="border-radius:14px;border:1px solid #334155;max-width:100%;max-height:260px;position:relative;z-index:1;background:#1a1f2e;"></canvas>
        <!-- Text input for preview -->
        <div style="display:flex;gap:.5rem;align-items:center;z-index:2;background:#1e293b;border:1px solid #334155;border-radius:10px;padding:.4rem .7rem;width:100%;max-width:560px;">
          <i class="bi bi-type" style="color:#6366f1;flex-shrink:0;"></i>
          <input id="fx3DTextInput" type="text" value="SSZone" maxlength="30"
            style="background:transparent;border:none;color:#e2e8f0;font-size:.9rem;font-weight:700;outline:none;flex:1;"
            oninput="update3DPreview()" placeholder="Preview text…">
          <span style="font-size:.7rem;color:#475569;" id="fx3DCharCount">6/30</span>
        </div>
        <div style="position:absolute;bottom:.7rem;right:.9rem;font-size:.68rem;color:#334155;z-index:2;">Live Preview · Canvas 2D</div>
      </div>

      <!-- RIGHT: Controls Panel -->
      <div style="width:300px;background:#1e293b;border-left:1px solid #334155;overflow-y:auto;padding:1rem;scrollbar-width:thin;scrollbar-color:#334155 transparent;display:flex;flex-direction:column;gap:.1rem;">

        <div style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#4f46e5;margin-bottom:.5rem;display:flex;align-items:center;gap:.4rem;">
          <i class="bi bi-sliders2"></i> Customize Effect
        </div>

        <!-- Main Text Color -->
        <div class="s3d-ctrl-block">
          <div class="s3d-ctrl-label">Main Text Color</div>
          <div style="display:flex;gap:.4rem;align-items:center;">
            <input type="color" id="fx3DMainColor" value="#ffffff" oninput="setFx3DMainSolid();update3DPreview()" style="width:36px;height:30px;border:1px solid #334155;border-radius:6px;cursor:pointer;background:none;padding:1px 2px;">
            <div id="fx3DColorSwatches" style="display:flex;gap:.25rem;flex-wrap:wrap;">
              <div class="s3d-swatch" style="background:#ffffff;" onclick="setFx3DColor('main','#ffffff')"></div>
              <div class="s3d-swatch" style="background:#fbbf24;" onclick="setFx3DColor('main','#fbbf24')"></div>
              <div class="s3d-swatch" style="background:#f43f5e;" onclick="setFx3DColor('main','#f43f5e')"></div>
              <div class="s3d-swatch" style="background:#22c55e;" onclick="setFx3DColor('main','#22c55e')"></div>
              <div class="s3d-swatch" style="background:#3b82f6;" onclick="setFx3DColor('main','#3b82f6')"></div>
              <div class="s3d-swatch" style="background:#a855f7;" onclick="setFx3DColor('main','#a855f7')"></div>
              <div class="s3d-swatch" style="background:#0f172a;" onclick="setFx3DColor('main','#0f172a')"></div>
              <div class="s3d-swatch" style="background:#f97316;" onclick="setFx3DColor('main','#f97316')"></div>
              <div class="s3d-swatch s3d-swatch-grad" onclick="pick3DGradMain()" title="Gradient">✦</div>
            </div>
          </div>
          <!-- Gradient Main -->
          <div id="fx3DMainGradRow" style="display:none;margin-top:.4rem;gap:.3rem;align-items:center;">
            <input type="color" id="fx3DMainG1" value="#f43f5e" oninput="update3DPreview()" style="width:28px;height:24px;border:1px solid #334155;border-radius:5px;cursor:pointer;background:none;padding:1px 2px;">
            <span style="font-size:.7rem;color:#475569;">→</span>
            <input type="color" id="fx3DMainG2" value="#a855f7" oninput="update3DPreview()" style="width:28px;height:24px;border:1px solid #334155;border-radius:5px;cursor:pointer;background:none;padding:1px 2px;">
            <label style="display:flex;align-items:center;gap:.2rem;font-size:.7rem;color:#94a3b8;cursor:pointer;margin-left:.2rem;">
              <input type="checkbox" id="fx3DMainGradOn" onchange="update3DPreview()"> On
            </label>
          </div>
        </div>

        <!-- 3D Extrude Color -->
        <div class="s3d-ctrl-block">
          <div class="s3d-ctrl-label">3D Depth / Extrude Color</div>
          <div style="display:flex;gap:.4rem;align-items:center;">
            <input type="color" id="fx3DExtColor" value="#7c3aed" oninput="update3DPreview()" style="width:36px;height:30px;border:1px solid #334155;border-radius:6px;cursor:pointer;background:none;padding:1px 2px;">
            <div style="display:flex;gap:.25rem;flex-wrap:wrap;">
              <div class="s3d-swatch" style="background:#7c3aed;" onclick="setFx3DColor('ext','#7c3aed')"></div>
              <div class="s3d-swatch" style="background:#1e40af;" onclick="setFx3DColor('ext','#1e40af')"></div>
              <div class="s3d-swatch" style="background:#9a3412;" onclick="setFx3DColor('ext','#9a3412')"></div>
              <div class="s3d-swatch" style="background:#065f46;" onclick="setFx3DColor('ext','#065f46')"></div>
              <div class="s3d-swatch" style="background:#1e293b;" onclick="setFx3DColor('ext','#1e293b')"></div>
              <div class="s3d-swatch" style="background:#78350f;" onclick="setFx3DColor('ext','#78350f')"></div>
            </div>
          </div>
        </div>

        <!-- Shadow Color -->
        <div class="s3d-ctrl-block">
          <div class="s3d-ctrl-label">Shadow / Glow Color</div>
          <div style="display:flex;gap:.4rem;align-items:center;">
            <input type="color" id="fx3DShadowColor" value="#000000" oninput="update3DPreview()" style="width:36px;height:30px;border:1px solid #334155;border-radius:6px;cursor:pointer;background:none;padding:1px 2px;">
            <div style="display:flex;gap:.25rem;flex-wrap:wrap;">
              <div class="s3d-swatch" style="background:#000000;" onclick="setFx3DColor('shadow','#000000')"></div>
              <div class="s3d-swatch" style="background:#0ea5e9;" onclick="setFx3DColor('shadow','#0ea5e9')"></div>
              <div class="s3d-swatch" style="background:#22c55e;" onclick="setFx3DColor('shadow','#22c55e')"></div>
              <div class="s3d-swatch" style="background:#f43f5e;" onclick="setFx3DColor('shadow','#f43f5e')"></div>
              <div class="s3d-swatch" style="background:#f59e0b;" onclick="setFx3DColor('shadow','#f59e0b')"></div>
              <div class="s3d-swatch" style="background:#8b5cf6;" onclick="setFx3DColor('shadow','#8b5cf6')"></div>
            </div>
          </div>
        </div>

        <!-- Font -->
        <div class="s3d-ctrl-block">
          <div class="s3d-ctrl-label">Font Family</div>
          <select id="fx3DFont" onchange="update3DPreview()" style="width:100%;background:#0f172a;border:1px solid #334155;border-radius:7px;color:#e2e8f0;font-size:.8rem;padding:.35rem .5rem;outline:none;">
            <option value="Poppins">Poppins</option>
            <option value="Montserrat">Montserrat</option>
            <option value="Roboto">Roboto</option>
            <option value="Open Sans">Open Sans</option>
            <option value="Lato">Lato</option>
            <option value="Playfair Display">Playfair Display</option>
            <option value="Dancing Script">Dancing Script</option>
            <option value="Baloo 2">Baloo 2 (हिंदी)</option>
            <option value="Hind">Hind (हिंदी)</option>
            <option value="Mukta">Mukta (हिंदी)</option>
            <option value="Rozha One">Rozha One (हिंदी)</option>
            <option value="Yatra One">Yatra One (हिंदी)</option>
            <option value="Bebas Neue">Bebas Neue</option>
            <option value="Oswald">Oswald</option>
            <option value="Impact">Impact</option>
            <option value="Georgia">Georgia</option>
            <option value="Arial Black">Arial Black</option>
            <option value="Courier New">Courier New</option>
          </select>
        </div>

        <!-- Font Size -->
        <div class="s3d-ctrl-block">
          <div style="display:flex;justify-content:space-between;margin-bottom:.25rem;">
            <div class="s3d-ctrl-label" style="margin:0;">Font Size</div>
            <span id="fx3DFontSizeVal" style="font-size:.74rem;color:#e2e8f0;font-weight:700;">72</span>
          </div>
          <input type="range" id="fx3DFontSize" min="24" max="160" value="72" oninput="update3DPreview();document.getElementById('fx3DFontSizeVal').textContent=this.value" style="width:100%;accent-color:#6366f1;">
        </div>

        <!-- Depth (extrude layers) -->
        <div class="s3d-ctrl-block">
          <div style="display:flex;justify-content:space-between;margin-bottom:.25rem;">
            <div class="s3d-ctrl-label" style="margin:0;">Extrude Depth</div>
            <span id="fx3DDepthVal" style="font-size:.74rem;color:#e2e8f0;font-weight:700;">12</span>
          </div>
          <input type="range" id="fx3DDepth" min="0" max="40" value="12" oninput="update3DPreview();document.getElementById('fx3DDepthVal').textContent=this.value" style="width:100%;accent-color:#a855f7;">
        </div>

        <!-- Angle -->
        <div class="s3d-ctrl-block">
          <div style="display:flex;justify-content:space-between;margin-bottom:.25rem;">
            <div class="s3d-ctrl-label" style="margin:0;">Depth Angle</div>
            <span id="fx3DAngleVal" style="font-size:.74rem;color:#e2e8f0;font-weight:700;">45°</span>
          </div>
          <input type="range" id="fx3DAngle" min="0" max="360" value="45" oninput="update3DPreview();document.getElementById('fx3DAngleVal').textContent=this.value+'°'" style="width:100%;accent-color:#ec4899;">
        </div>

        <!-- Outline -->
        <div class="s3d-ctrl-block">
          <div style="display:flex;justify-content:space-between;margin-bottom:.25rem;">
            <div class="s3d-ctrl-label" style="margin:0;">Outline / Stroke</div>
            <span id="fx3DOutlineVal" style="font-size:.74rem;color:#e2e8f0;font-weight:700;">0</span>
          </div>
          <div style="display:flex;gap:.4rem;align-items:center;">
            <input type="color" id="fx3DOutlineColor" value="#000000" oninput="update3DPreview()" style="width:28px;height:24px;border:1px solid #334155;border-radius:5px;cursor:pointer;background:none;padding:1px 2px;">
            <input type="range" id="fx3DOutline" min="0" max="12" value="0" oninput="update3DPreview();document.getElementById('fx3DOutlineVal').textContent=this.value" style="flex:1;accent-color:#f43f5e;">
          </div>
        </div>

        <!-- Glow Intensity -->
        <div class="s3d-ctrl-block">
          <div style="display:flex;justify-content:space-between;margin-bottom:.25rem;">
            <div class="s3d-ctrl-label" style="margin:0;">Glow Intensity</div>
            <span id="fx3DGlowVal" style="font-size:.74rem;color:#e2e8f0;font-weight:700;">0</span>
          </div>
          <input type="range" id="fx3DGlow" min="0" max="60" value="0" oninput="update3DPreview();document.getElementById('fx3DGlowVal').textContent=this.value" style="width:100%;accent-color:#22c55e;">
        </div>

        <!-- Letter Spacing -->
        <div class="s3d-ctrl-block">
          <div style="display:flex;justify-content:space-between;margin-bottom:.25rem;">
            <div class="s3d-ctrl-label" style="margin:0;">Letter Spacing</div>
            <span id="fx3DSpacingVal" style="font-size:.74rem;color:#e2e8f0;font-weight:700;">0</span>
          </div>
          <input type="range" id="fx3DSpacing" min="-5" max="30" value="0" oninput="update3DPreview();document.getElementById('fx3DSpacingVal').textContent=this.value" style="width:100%;accent-color:#f59e0b;">
        </div>

        <!-- Italic / Bold -->
        <div class="s3d-ctrl-block" style="display:flex;gap:.5rem;">
          <label style="display:flex;align-items:center;gap:.3rem;font-size:.77rem;color:#94a3b8;cursor:pointer;">
            <input type="checkbox" id="fx3DBold" checked onchange="update3DPreview()"> Bold
          </label>
          <label style="display:flex;align-items:center;gap:.3rem;font-size:.77rem;color:#94a3b8;cursor:pointer;">
            <input type="checkbox" id="fx3DItalic" onchange="update3DPreview()"> Italic
          </label>
          <label style="display:flex;align-items:center;gap:.3rem;font-size:.77rem;color:#94a3b8;cursor:pointer;">
            <input type="checkbox" id="fx3DTransBg" onchange="update3DPreview()"> Transp. BG
          </label>
        </div>

        <!-- BG Color (preview canvas) -->
        <div class="s3d-ctrl-block" id="fx3DBgColorRow">
          <div class="s3d-ctrl-label">Preview BG</div>
          <div style="display:flex;gap:.3rem;">
            <input type="color" id="fx3DBgColor" value="#1a1f2e" oninput="update3DPreview()" style="width:36px;height:28px;border:1px solid #334155;border-radius:6px;cursor:pointer;background:none;padding:1px 2px;">
            <div style="display:flex;gap:.2rem;flex-wrap:wrap;">
              <div class="s3d-swatch" style="background:#1a1f2e;" onclick="setFx3DColor('bg','#1a1f2e')"></div>
              <div class="s3d-swatch" style="background:#ffffff;" onclick="setFx3DColor('bg','#ffffff')"></div>
              <div class="s3d-swatch" style="background:#000000;" onclick="setFx3DColor('bg','#000000')"></div>
              <div class="s3d-swatch" style="background:#0f172a;" onclick="setFx3DColor('bg','#0f172a')"></div>
              <div class="s3d-swatch" style="background:#f1f5f9;" onclick="setFx3DColor('bg','#f1f5f9')"></div>
            </div>
          </div>
        </div>

        <!-- Divider -->
        <div style="height:1px;background:#334155;margin:.3rem 0;"></div>

        <!-- Action buttons -->
        <button onclick="apply3DEffect()" style="width:100%;padding:.6rem;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;color:#fff;border-radius:10px;font-size:.88rem;font-weight:700;cursor:pointer;letter-spacing:.01em;margin-top:.2rem;">
          <i class="bi bi-magic me-1"></i> Apply to Canvas
        </button>
        <button onclick="close3DStudio()" style="width:100%;padding:.45rem;background:transparent;border:1px solid #334155;color:#94a3b8;border-radius:10px;font-size:.82rem;cursor:pointer;margin-top:.35rem;">
          Cancel
        </button>
        <div style="font-size:.67rem;color:#475569;margin-top:.4rem;text-align:center;">Applies as high-res PNG image to canvas</div>
      </div>
    </div>
  </div>
</div>

<!-- URL Image Modal -->
<div id="urlModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:2000;align-items:center;justify-content:center;">
  <div style="background:#1e293b;border:1px solid #334155;border-radius:14px;padding:1.5rem;width:440px;max-width:96vw;">
    <div style="font-size:.95rem;font-weight:700;color:#e2e8f0;margin-bottom:1rem;">Add Image from URL</div>
    <input id="urlInput" type="url" placeholder="https://example.com/image.png"
      style="width:100%;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:.85rem;padding:.5rem .75rem;outline:none;margin-bottom:.75rem;"
      onkeydown="if(event.key==='Enter')addImageFromURL()">
    <div style="font-size:.72rem;color:#64748b;margin-bottom:1rem;">
      JPG, PNG (transparent), SVG, WebP — Paste the direct image link
    </div>
    <div style="display:flex;gap:.5rem;justify-content:flex-end;">
      <button onclick="document.getElementById('urlModal').style.display='none'"
        style="background:transparent;border:1px solid #334155;color:#94a3b8;border-radius:8px;padding:.4rem .9rem;cursor:pointer;font-size:.82rem;">Cancel</button>
      <button onclick="addImageFromURL()"
        style="background:#4f46e5;border:none;color:#fff;border-radius:8px;padding:.4rem 1.1rem;cursor:pointer;font-size:.82rem;font-weight:600;">Add Image</button>
    </div>
  </div>
</div>

<!-- Sticker / Online Photos Modal -->
<div id="stickerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.78);z-index:2000;align-items:flex-start;justify-content:center;padding:2rem 1rem;">
  <div class="sticker-modal-inner">
    <!-- Header -->
    <div style="padding:.9rem 1.1rem;border-bottom:1px solid #334155;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
      <div>
        <div style="font-size:.95rem;font-weight:700;color:#e2e8f0;"><i class="bi bi-images me-2" style="color:#6366f1;"></i>Online Photos / Stickers</div>
        <div style="font-size:.72rem;color:#64748b;margin-top:.1rem;">Choose a category, then click a sticker to add it to the canvas</div>
      </div>
      <button onclick="document.getElementById('stickerModal').style.display='none'" style="background:transparent;border:none;color:#94a3b8;font-size:1.3rem;cursor:pointer;padding:.2rem .5rem;">✕</button>
    </div>
    <!-- Body: cats + grid -->
    <div style="display:flex;flex:1;min-height:0;overflow:hidden;">
      <!-- Left: categories -->
      <div style="width:130px;border-right:1px solid #334155;overflow-y:auto;flex-shrink:0;padding:.5rem .4rem;scrollbar-width:thin;">
        <div style="font-size:.62rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:.2rem .4rem .5rem;">Categories</div>
        <div id="stickerCats"></div>
      </div>
      <!-- Right: sticker grid -->
      <div style="flex:1;overflow-y:auto;padding:.75rem;scrollbar-width:thin;" id="stickerGridWrap">
        <div id="stickerGrid" style="display:grid;grid-template-columns:repeat(6,1fr);gap:.5rem;"></div>
        <div id="stickerEmpty" style="display:none;text-align:center;padding:3rem 1rem;color:#64748b;">
          <i class="bi bi-image" style="font-size:2.5rem;opacity:.25;display:block;margin-bottom:.75rem;"></i>
          <div style="font-size:.82rem;font-weight:600;color:#475569;margin-bottom:.3rem;">No stickers found</div>
          <div style="font-size:.75rem;">Ask admin to upload stickers</div>
        </div>
        <div id="stickerLoading" style="text-align:center;padding:3rem;color:#64748b;display:none;">
          <div style="font-size:.85rem;">Loading...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── New Canvas Modal ──────────────────────────────────────────── -->
<div id="newCanvasModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:2100;align-items:center;justify-content:center;">
  <div style="background:#1e293b;border:1px solid #334155;border-radius:16px;padding:0;width:580px;max-width:97vw;max-height:94vh;overflow-y:auto;">
    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid #334155;background:#0f172a;border-radius:16px 16px 0 0;">
      <div style="font-size:1rem;font-weight:700;color:#e2e8f0;"><i class="bi bi-plus-circle me-2" style="color:#34d399;"></i>New Canvas</div>
      <button onclick="closeNewCanvasModal()" style="background:none;border:none;color:#64748b;font-size:1.2rem;cursor:pointer;line-height:1;">×</button>
    </div>
    <div style="padding:1.25rem;">

      <!-- Name -->
      <div style="margin-bottom:1rem;">
        <label style="font-size:.75rem;color:#64748b;display:block;margin-bottom:.3rem;font-weight:600;">Design Name</label>
        <input type="text" id="ncName" class="props-input" value="My Design" style="width:100%;font-size:.9rem;" placeholder="Enter design name...">
      </div>

      <!-- Size Presets -->
      <div style="margin-bottom:.75rem;">
        <label style="font-size:.75rem;color:#64748b;display:block;margin-bottom:.45rem;font-weight:600;">Quick Presets</label>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.35rem;">
          <button class="nc-preset-btn" onclick="ncSetPreset(3.5,2,'in',300,'ID Card 3.5×2&quot;')">ID Card</button>
          <button class="nc-preset-btn" onclick="ncSetPreset(4,6,'in',300,'Postcard 4×6&quot;')">Postcard</button>
          <button class="nc-preset-btn" onclick="ncSetPreset(8.27,11.69,'in',300,'A4 Portrait')">A4</button>
          <button class="nc-preset-btn" onclick="ncSetPreset(11.69,8.27,'in',300,'A4 Landscape')">A4 Land.</button>
          <button class="nc-preset-btn" onclick="ncSetPreset(8.5,11,'in',300,'Letter')">Letter</button>
          <button class="nc-preset-btn" onclick="ncSetPreset(24,36,'in',150,'Poster 24×36&quot;')">Poster</button>
          <button class="nc-preset-btn" onclick="ncSetPreset(10,4,'ft',50,'Flex Banner 10×4 ft')">Banner</button>
          <button class="nc-preset-btn" onclick="ncSetPreset(40,14,'ft',25,'Billboard 40×14 ft')">Billboard</button>
          <button class="nc-preset-btn" onclick="ncSetPreset(1080,1080,'px',96,'Social Square')">Social Sq.</button>
          <button class="nc-preset-btn" onclick="ncSetPreset(1920,1080,'px',96,'HD 1920×1080')">HD Video</button>
          <button class="nc-preset-btn" onclick="ncSetPreset(1080,1920,'px',96,'Story 9:16')">Story</button>
          <button class="nc-preset-btn" onclick="ncSetPreset(856,540,'px',96,'Default')">Default</button>
        </div>
      </div>

      <!-- Size inputs -->
      <div style="margin-bottom:.75rem;">
        <label style="font-size:.75rem;color:#64748b;display:block;margin-bottom:.45rem;font-weight:600;">Custom Size</label>
        <div style="display:flex;gap:.5rem;align-items:center;">
          <div style="flex:1;">
            <label style="font-size:.68rem;color:#64748b;display:block;margin-bottom:.2rem;">Width</label>
            <input type="number" id="ncW" class="props-input" value="856" min="10" max="20000" style="width:100%;" oninput="ncUpdatePxPreview()">
          </div>
          <div style="padding-top:1.2rem;color:#64748b;font-size:.8rem;">×</div>
          <div style="flex:1;">
            <label style="font-size:.68rem;color:#64748b;display:block;margin-bottom:.2rem;">Height</label>
            <input type="number" id="ncH" class="props-input" value="540" min="10" max="20000" style="width:100%;" oninput="ncUpdatePxPreview()">
          </div>
          <div style="flex:.8;">
            <label style="font-size:.68rem;color:#64748b;display:block;margin-bottom:.2rem;">Unit</label>
            <select id="ncUnit" class="props-input" style="width:100%;" onchange="ncUnitChange()">
              <option value="px">px</option>
              <option value="in">inches</option>
              <option value="cm">cm</option>
              <option value="ft">feet</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Orientation -->
      <div style="display:flex;gap:.4rem;margin-bottom:.75rem;">
        <button onclick="ncSwapOrientation()" style="padding:.3rem .75rem;border-radius:7px;border:1px solid #334155;background:#0f172a;color:#94a3b8;font-size:.75rem;cursor:pointer;"><i class="bi bi-arrow-left-right me-1"></i>Swap W↔H</button>
      </div>

      <!-- DPI -->
      <div style="margin-bottom:.75rem;">
        <label style="font-size:.75rem;color:#64748b;display:block;margin-bottom:.3rem;font-weight:600;">Resolution / DPI</label>
        <!-- Large Format row -->
        <div style="font-size:.64rem;color:#f59e0b;margin-bottom:.25rem;font-weight:600;">&#127758; Large Format (banner / billboard / hoarding)</div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.3rem;margin-bottom:.3rem;">
          <button class="nc-dpi-btn" onclick="ncSetDpi(10)"  data-dpi="10">10 DPI<br><span style="font-size:.58rem;color:#64748b;">Hoarding</span></button>
          <button class="nc-dpi-btn" onclick="ncSetDpi(25)"  data-dpi="25">25 DPI<br><span style="font-size:.58rem;color:#64748b;">Billboard</span></button>
          <button class="nc-dpi-btn" onclick="ncSetDpi(50)"  data-dpi="50">50 DPI<br><span style="font-size:.58rem;color:#64748b;">Flex/Banner</span></button>
          <button class="nc-dpi-btn" onclick="ncSetDpi(72)"  data-dpi="72">72 DPI<br><span style="font-size:.58rem;color:#64748b;">Screen</span></button>
        </div>
        <!-- Print Quality row -->
        <div style="font-size:.64rem;color:#6ee7b7;margin-bottom:.25rem;font-weight:600;">&#128438; Print Quality (ID card / poster / photo / brochure)</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.3rem;">
          <button class="nc-dpi-btn" onclick="ncSetDpi(150)" data-dpi="150">150 DPI<br><span style="font-size:.58rem;color:#64748b;">Good Print</span></button>
          <button class="nc-dpi-btn active" onclick="ncSetDpi(300)" data-dpi="300">300 DPI<br><span style="font-size:.58rem;color:#6ee7b7;">Professional ★</span></button>
          <button class="nc-dpi-btn" onclick="ncSetDpi(600)" data-dpi="600">600 DPI<br><span style="font-size:.58rem;color:#f59e0b;">Ultra HQ</span></button>
        </div>
        <div id="ncDpiHint" style="margin-top:.4rem;font-size:.7rem;color:#64748b;"></div>
        <!-- Max size reference table -->
        <div style="margin-top:.5rem;background:#0a0f1e;border:1px solid #1e293b;border-radius:7px;padding:.5rem .7rem;font-size:.67rem;color:#475569;">
          <div style="color:#64748b;font-weight:600;margin-bottom:.3rem;">Max canvas size per DPI (browser limit ≈ 16,000 px)</div>
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.15rem .4rem;line-height:1.6;">
            <span style="color:#f59e0b;">10 DPI</span><span>→ 133 ft</span>
            <span style="color:#f59e0b;">25 DPI</span><span>→ 53 ft</span>
            <span style="color:#f59e0b;">50 DPI</span><span>→ 26 ft</span>
            <span style="color:#94a3b8;">72 DPI</span><span>→ 18 ft</span>
            <span style="color:#6ee7b7;">150 DPI</span><span>→ 107 in</span>
            <span style="color:#6ee7b7;">300 DPI</span><span>→ 53 in</span>
            <span style="color:#6ee7b7;">600 DPI</span><span>→ 26 in</span>
            <span style="color:#64748b;font-style:italic;font-size:.62rem;" colspan="1">per side</span>
          </div>
        </div>
      </div>

      <!-- Background Color -->
      <div style="margin-bottom:1rem;">
        <label style="font-size:.75rem;color:#64748b;display:block;margin-bottom:.45rem;font-weight:600;">Background Color</label>
        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
          <input type="color" id="ncBg" value="#ffffff" oninput="ncSetBg(this.value)" style="width:40px;height:32px;border:none;border-radius:6px;cursor:pointer;padding:2px;">
          <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
            <button onclick="ncSetBg('#ffffff')" style="width:24px;height:24px;border-radius:5px;background:#ffffff;border:1px solid #475569;cursor:pointer;" title="White"></button>
            <button onclick="ncSetBg('#000000')" style="width:24px;height:24px;border-radius:5px;background:#000000;border:1px solid #475569;cursor:pointer;" title="Black"></button>
            <button onclick="ncSetBg('#f8fafc')" style="width:24px;height:24px;border-radius:5px;background:#f8fafc;border:1px solid #475569;cursor:pointer;" title="Light Gray"></button>
            <button onclick="ncSetBg('#1e293b')" style="width:24px;height:24px;border-radius:5px;background:#1e293b;border:1px solid #475569;cursor:pointer;" title="Dark Blue"></button>
            <button onclick="ncSetBg('#fef9c3')" style="width:24px;height:24px;border-radius:5px;background:#fef9c3;border:1px solid #475569;cursor:pointer;" title="Light Yellow"></button>
            <button onclick="ncSetTransparent()" style="width:24px;height:24px;border-radius:5px;background:repeating-conic-gradient(#ccc 0% 25%,#fff 0% 50%) 0/8px 8px;border:1px solid #475569;cursor:pointer;" title="Transparent"></button>
          </div>
        </div>
      </div>

      <!-- Size preview -->
      <div id="ncPxPreview" style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:.6rem .85rem;font-size:.75rem;color:#64748b;margin-bottom:1.1rem;">
        Canvas: <strong id="ncPxW" style="color:#a5b4fc;">856</strong> × <strong id="ncPxH" style="color:#a5b4fc;">540</strong> px &nbsp;|&nbsp; DPI: <strong id="ncPxDpi" style="color:#6ee7b7;">150</strong> &nbsp;|&nbsp; Print size: <strong id="ncPhysical" style="color:#fbbf24;">—</strong>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:.6rem;justify-content:flex-end;">
        <button onclick="closeNewCanvasModal()" style="background:transparent;border:1px solid #334155;color:#94a3b8;border-radius:8px;padding:.45rem 1rem;cursor:pointer;font-size:.85rem;">Cancel</button>
        <button onclick="createNewCanvas()" style="background:linear-gradient(135deg,#059669,#0d9488);border:none;color:#fff;border-radius:8px;padding:.45rem 1.4rem;cursor:pointer;font-size:.85rem;font-weight:700;"><i class="bi bi-plus-lg me-1"></i>Create Canvas</button>
      </div>
    </div>
  </div>
</div>

<!-- Canvas Resize Modal -->
<div id="resizeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:2000;align-items:center;justify-content:center;">
  <div style="background:#1e293b;border:1px solid #334155;border-radius:14px;padding:1.5rem;width:360px;max-width:96vw;">
    <div style="font-size:.95rem;font-weight:700;color:#e2e8f0;margin-bottom:1rem;"><i class="bi bi-aspect-ratio me-2" style="color:#6366f1;"></i>Resize Canvas</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
      <div>
        <label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.3rem;">Width (px)</label>
        <input type="number" id="resizeW" class="props-input" min="100" max="16000">
      </div>
      <div>
        <label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.3rem;">Height (px)</label>
        <input type="number" id="resizeH" class="props-input" min="100" max="16000">
      </div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.9rem;">
      <button class="style-btn" onclick="setResizePreset(856,540)"  style="width:auto;padding:.25rem .55rem;font-size:.7rem;">856×540</button>
      <button class="style-btn" onclick="setResizePreset(1080,1080)" style="width:auto;padding:.25rem .55rem;font-size:.7rem;">Square</button>
      <button class="style-btn" onclick="setResizePreset(1920,1080)" style="width:auto;padding:.25rem .55rem;font-size:.7rem;">HD</button>
      <button class="style-btn" onclick="setResizePreset(794,1123)"  style="width:auto;padding:.25rem .55rem;font-size:.7rem;">A4</button>
      <button class="style-btn" onclick="setResizePreset(600,900)"   style="width:auto;padding:.25rem .55rem;font-size:.7rem;">Story</button>
    </div>
    <div style="display:flex;gap:.5rem;justify-content:flex-end;">
      <button onclick="document.getElementById('resizeModal').style.display='none'"
        style="background:transparent;border:1px solid #334155;color:#94a3b8;border-radius:8px;padding:.4rem .9rem;cursor:pointer;font-size:.82rem;">Cancel</button>
      <button onclick="applyResize()"
        style="background:#4f46e5;border:none;color:#fff;border-radius:8px;padding:.4rem 1.1rem;cursor:pointer;font-size:.82rem;font-weight:600;">Apply</button>
    </div>
  </div>
</div>

<!-- Curved Text Modal -->
<div id="curvedTextModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:2000;align-items:center;justify-content:center;">
  <div style="background:#1e293b;border:1px solid #334155;border-radius:14px;padding:1.5rem;width:380px;max-width:96vw;">
    <div style="font-size:.95rem;font-weight:700;color:#e2e8f0;margin-bottom:1rem;"><i class="bi bi-circle-half me-2" style="color:#6366f1;"></i>Curved Text Path</div>
    <div style="margin-bottom:.6rem;">
      <label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.3rem;">Text</label>
      <input type="text" id="ctTextInput" class="props-input" value="Your Curved Text" placeholder="Enter text...">
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:.6rem;">
      <div>
        <label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.3rem;">Radius (px)</label>
        <input type="number" id="ctRadius" class="props-input" value="150" min="50" max="600">
      </div>
      <div>
        <label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.3rem;">Font Size</label>
        <input type="number" id="ctFontSize" class="props-input" value="24" min="8" max="120">
      </div>
    </div>
    <div style="margin-bottom:.9rem;">
      <label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.3rem;">Position</label>
      <select id="ctSide" class="props-input">
        <option value="top">Top arc (text curves above)</option>
        <option value="bottom">Bottom arc (text curves below)</option>
      </select>
    </div>
    <div style="display:flex;gap:.5rem;justify-content:flex-end;">
      <button onclick="document.getElementById('curvedTextModal').style.display='none'"
        style="background:transparent;border:1px solid #334155;color:#94a3b8;border-radius:8px;padding:.4rem .9rem;cursor:pointer;font-size:.82rem;">Cancel</button>
      <button onclick="applyCurvedText()"
        style="background:#4f46e5;border:none;color:#fff;border-radius:8px;padding:.4rem 1.1rem;cursor:pointer;font-size:.82rem;font-weight:600;">Add Curved Text</button>
    </div>
  </div>
</div>

<!-- Mockup Preview Modal -->
<div id="mockupModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:2000;align-items:center;justify-content:center;">
  <div style="background:#1e293b;border:1px solid #334155;border-radius:16px;overflow:hidden;width:700px;max-width:98vw;max-height:92vh;display:flex;flex-direction:column;">
    <div style="padding:.8rem 1.1rem;border-bottom:1px solid #334155;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
      <div style="font-size:.9rem;font-weight:700;color:#e2e8f0;"><i class="bi bi-phone-flip me-2" style="color:#6366f1;"></i>Mockup Preview</div>
      <button onclick="closeMockupModal()" style="background:transparent;border:none;color:#94a3b8;font-size:1.4rem;cursor:pointer;line-height:1;">✕</button>
    </div>
    <div style="padding:.65rem .9rem;display:flex;gap:.4rem;flex-wrap:wrap;border-bottom:1px solid #334155;flex-shrink:0;">
      <button class="style-btn mockup-type-btn active" style="width:auto;padding:.25rem .6rem;font-size:.74rem;" onclick="setMockupType('flat',this)">Flat</button>
      <button class="style-btn mockup-type-btn" style="width:auto;padding:.25rem .6rem;font-size:.74rem;" onclick="setMockupType('tshirt',this)">T-Shirt</button>
      <button class="style-btn mockup-type-btn" style="width:auto;padding:.25rem .6rem;font-size:.74rem;" onclick="setMockupType('idcard',this)">ID Card</button>
      <button class="style-btn mockup-type-btn" style="width:auto;padding:.25rem .6rem;font-size:.74rem;" onclick="setMockupType('mug',this)">Mug</button>
      <button class="style-btn mockup-type-btn" style="width:auto;padding:.25rem .6rem;font-size:.74rem;" onclick="setMockupType('banner',this)">Banner</button>
      <button class="style-btn mockup-type-btn" style="width:auto;padding:.25rem .6rem;font-size:.74rem;" onclick="setMockupType('tilt',this)">Tilt</button>
    </div>
    <div style="flex:1;overflow:auto;background:#374151;display:flex;align-items:center;justify-content:center;padding:2.5rem;min-height:320px;">
      <div id="mockupFrame" style="background:#fff;border-radius:8px;box-shadow:0 16px 48px rgba(0,0,0,.6);overflow:hidden;transition:all .4s ease;display:inline-block;">
        <img id="mockupDesign" style="display:block;max-width:500px;max-height:50vh;" alt="Design Preview">
      </div>
    </div>
    <div style="padding:.5rem .9rem;text-align:center;font-size:.7rem;color:#475569;flex-shrink:0;">
      CSS 3D perspective simulation — for final print use "Export Print-Ready"
    </div>
  </div>
</div>

<!-- Toast -->
<div class="ed-toast" id="edToast">Saved!</div>

<!-- In-app Confirm dialog (replaces native confirm()) -->
<div id="edConfirmOv" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9700;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#1e293b;border:1px solid #334155;border-radius:14px;max-width:380px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.5);">
    <div style="padding:1.1rem 1.2rem .3rem;font-size:.88rem;color:#e2e8f0;line-height:1.6;" id="edConfirmMsg"></div>
    <div style="display:flex;gap:.5rem;padding:1rem 1.2rem 1.2rem;">
      <button onclick="_edConfirmAnswer(false)" style="flex:1;padding:.5rem;border-radius:8px;border:1px solid #334155;background:transparent;color:#94a3b8;font-size:.82rem;font-weight:600;cursor:pointer;">Cancel</button>
      <button onclick="_edConfirmAnswer(true)" style="flex:1;padding:.5rem;border-radius:8px;border:none;background:#4f46e5;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;">OK</button>
    </div>
  </div>
</div>

<!-- In-app Prompt dialog (replaces native prompt()) -->
<div id="edPromptOv" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9700;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#1e293b;border:1px solid #334155;border-radius:14px;max-width:420px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.5);">
    <div style="padding:1.1rem 1.2rem .2rem;font-size:.9rem;font-weight:700;color:#e2e8f0;" id="edPromptTitle"></div>
    <div style="padding:.4rem 1.2rem 0;" id="edPromptBody"></div>
    <div style="display:flex;gap:.5rem;padding:1rem 1.2rem 1.2rem;">
      <button onclick="_edPromptAnswer(false)" style="flex:1;padding:.5rem;border-radius:8px;border:1px solid #334155;background:transparent;color:#94a3b8;font-size:.82rem;font-weight:600;cursor:pointer;">Cancel</button>
      <button onclick="_edPromptAnswer(true)" style="flex:1;padding:.5rem;border-radius:8px;border:none;background:#4f46e5;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;">OK</button>
    </div>
  </div>
</div>

<!-- In-app issue-list dialog (replaces native alert() for WCAG / resolution checks) -->
<div id="edListOv" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9700;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#1e293b;border:1px solid #334155;border-radius:14px;max-width:460px;width:100%;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,.5);">
    <div style="padding:1rem 1.2rem;border-bottom:1px solid #334155;font-size:.9rem;font-weight:700;color:#e2e8f0;" id="edListTitle"></div>
    <div style="padding:1rem 1.2rem;overflow-y:auto;font-size:.8rem;color:#cbd5e1;line-height:1.7;" id="edListBody"></div>
    <div style="padding:.8rem 1.2rem 1.1rem;">
      <button onclick="document.getElementById('edListOv').style.display='none'" style="width:100%;padding:.5rem;border-radius:8px;border:none;background:#4f46e5;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;">Got it</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/vendor/fabric-5.3.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/idm-poll.js"></script>
<script src="<?= BASE_URL ?>/assets/js/offline-queue.js?v=4"></script>
<script>
<?php
// HEX_* flags: a design containing "</script>" or quotes in a text layer can
// never break out of this <script>; INVALID_UTF8_SUBSTITUTE: one bad byte in
// a saved design used to make json_encode() return false → "const ED = ;" →
// a syntax error that left the whole editor blank.
$edJson = json_encode($editorData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
?>
const ED = <?= $edJson ?: '{}' ?>;

// Guard: if Fabric.js failed to load, show error
if (typeof fabric === 'undefined') {
  document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;flex-direction:column;gap:1rem;color:#e2e8f0;background:#0f172a;">'
    + '<i class="bi bi-exclamation-triangle" style="font-size:3rem;color:#f59e0b;"></i>'
    + '<h3>Fabric.js failed to load</h3>'
    + '<p style="color:#64748b;">Check your internet connection or refresh the page.</p>'
    + '<a href="javascript:location.reload()" style="background:#4f46e5;color:#fff;padding:.6rem 1.4rem;border-radius:8px;text-decoration:none;">Refresh</a>'
    + '</div>';
  throw new Error('fabric not loaded');
}

// ── Canvas init ────────────────────────────────────────────────────
let canvasW = ED.width  || 856;
let canvasH = ED.height || 540;

// Image filters on photos larger than 2048px were silently cropped by the
// default WebGL tile size.
fabric.textureSize = 4096;

const canvas = new fabric.Canvas('fabricCanvas', {
  width:  canvasW,
  height: canvasH,
  backgroundColor: '#ffffff',
  preserveObjectStacking: true,
  selection: true,
  stopContextMenu: true,
  fireRightClick: false,
});
canvas.renderAll();
// Must be called after canvas element exists in DOM
setTimeout(_initGradDragListeners, 0);

document.getElementById('pCanvasW').textContent = canvasW;
document.getElementById('pCanvasH').textContent = canvasH;
document.getElementById('sizeTag').textContent  = canvasW + ' × ' + canvasH + ' px';

// ── Small shared helpers ───────────────────────────────────────────
const TEXT_TYPES = ['i-text','text','textbox'];
function isTextObj(o) { return !!o && TEXT_TYPES.includes(o.type); }
function escHtml(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
// Custom properties that must survive undo/redo, save, copy & duplicate
// (placeholder slots used to lose their data.placeholder on duplicate/paste).
const JSON_PROPS = ['data','selectable','evented','hoverCursor','lockMovementX','lockMovementY','lockRotation','lockScalingX','lockScalingY','hasControls'];
// Design metadata (DPI, physical size) chosen in "New Canvas". It used to be
// dropped on the first save, so Print/PDF fell back to 96 DPI afterwards and
// printed at the wrong physical size.
let _designMeta = null;
try { const _j = ED.canvas_json ? JSON.parse(ED.canvas_json) : null; if (_j && _j._meta) _designMeta = _j._meta; } catch (_) {}

/** Full design JSON (objects + size + metadata) — used by save, history, autosave, export. */
function canvasJSON() {
  const j = canvas.toJSON(JSON_PROPS);
  j._size = { w: canvasW, h: canvasH };
  if (_designMeta) j._meta = Object.assign({}, _designMeta, { dpi: _ncCurrentDpi });
  else if (_ncCurrentDpi && _ncCurrentDpi !== 96) j._meta = { dpi: _ncCurrentDpi };
  return j;
}
/** Zoom-independent PNG/JPEG export. Fabric scales the on-screen (zoomed)
 *  size, so exports/thumbnails/PDFs came out smaller whenever you were zoomed
 *  out (always on phones). Dividing the multiplier by the zoom fixes it. */
function exportDataURL(opts) {
  opts = opts || {};
  const z = canvas.getZoom() || 1;
  const fmt = opts.format || 'png';
  const origBg = canvas.backgroundColor;
  if (fmt === 'jpeg' && (!origBg || origBg === 'transparent')) canvas.backgroundColor = '#ffffff';
  const hadActive = canvas.getActiveObject();
  try {
    return canvas.toDataURL(Object.assign({}, opts, { format: fmt, multiplier: (opts.multiplier || 1) / z }));
  } finally {
    canvas.backgroundColor = origBg;
    if (hadActive) canvas.requestRenderAll();
  }
}
/** Resize the artboard (keeps the current zoom). */
function resizeCanvasTo(w, h) {
  canvasW = w; canvasH = h;
  const z = canvas.getZoom() || 1;
  canvas.setDimensions({ width: Math.round(w * z), height: Math.round(h * z) });
  document.getElementById('pCanvasW').textContent = w;
  document.getElementById('pCanvasH').textContent = h;
  document.getElementById('sizeTag').textContent  = w + ' × ' + h + ' px';
}
/** Load web fonts used by the design, then re-measure text. Fabric caches
 *  glyph widths, so text first drawn in a fallback font kept a wrong/clipped
 *  box (and wrong wrapping) even after the real font arrived. */
function ensureFontsFor(objs) {
  if (!document.fonts || !document.fonts.load) return Promise.resolve();
  const fams = new Set();
  const walk = o => { if (o.type === 'group') o.getObjects().forEach(walk); else if (o.fontFamily) fams.add(o.fontFamily + '|' + (o.fontWeight || 'normal')); };
  (objs || canvas.getObjects()).forEach(walk);
  if (!fams.size) return Promise.resolve();
  return Promise.all([...fams].map(f => { const [fam, w] = f.split('|'); return document.fonts.load(`${w} 32px "${fam}"`).catch(() => {}); }))
    .then(() => {
      try { fabric.util.clearFabricFontCache(); } catch (_) {}
      const fix = o => { if (o.type === 'group') { o.getObjects().forEach(fix); o.dirty = true; } else if (isTextObj(o) && o.initDimensions) { o.initDimensions(); o.setCoords(); o.dirty = true; } };
      (objs || canvas.getObjects()).forEach(fix);
      canvas.requestRenderAll();
    });
}

// ── Dirty state (unsaved changes) ──────────────────────────────────
let _dirty = false;
function markDirty() { _dirty = true; document.getElementById('edDirtyDot')?.classList.add('show'); }
function markClean() { _dirty = false; document.getElementById('edDirtyDot')?.classList.remove('show'); clearAutoSave(); }
window.addEventListener('beforeunload', e => { if (_dirty) { e.preventDefault(); e.returnValue = ''; } });

// ── History ────────────────────────────────────────────────────────
const hist = { states: [], labels: [], times: [], idx: -1, locked: false };
let _histLabel = null;   // set by an action just before it saves (shown in the History panel)
function histLabel(l) { _histLabel = l; }

function histSave(label) {
  if (hist.locked) return;
  clearTimeout(_histSoonT);
  const json = JSON.stringify(canvasJSON());
  const lbl = (typeof label === 'string' && label) || _histLabel || 'Edit';
  _histLabel = null;
  if (hist.idx >= 0 && hist.states[hist.idx] === json) return;   // nothing actually changed
  hist.states.splice(hist.idx + 1); hist.labels.splice(hist.idx + 1); hist.times.splice(hist.idx + 1);
  hist.states.push(json); hist.labels.push(hist.states.length === 1 ? 'Open design' : lbl); hist.times.push(Date.now());
  if (hist.states.length > 80) { hist.states.shift(); hist.labels.shift(); hist.times.shift(); } else hist.idx++;
  if (hist.states.length > 1) markDirty();
  syncHistBtns();
}
/** Jump straight to any step (History panel). */
function histGoTo(i) {
  if (i < 0 || i >= hist.states.length || i === hist.idx || hist.locked) return;
  clearTimeout(_histSoonT);
  hist.idx = i;
  _histRestore(hist.states[i]);
}
// Coalesce rapid slider/typing changes into one undo step (property edits
// were never recorded before, so Undo skipped straight past them).
let _histSoonT = null, _histSoonLabel = null;
function histSaveSoon(label) { if (hist.locked) return; _histSoonLabel = label || _histSoonLabel || 'Change properties'; clearTimeout(_histSoonT); _histSoonT = setTimeout(() => { const l = _histSoonLabel; _histSoonLabel = null; histSave(l); }, 350); }

function _histRestore(json) {
  hist.locked = true;
  let size = null;
  try { size = JSON.parse(json)._size || null; } catch (_) {}
  // Canvas size is part of each step now — undoing a Crop/Resize used to leave
  // the old objects on the new, wrong-sized canvas.
  if (size && (size.w !== canvasW || size.h !== canvasH)) resizeCanvasTo(size.w, size.h);
  canvas.loadFromJSON(json, () => {
    canvas.renderAll(); hist.locked = false;
    refreshProps(); refreshLayers(); syncHistBtns(); markDirty();
  });
}
function histUndo() {
  if (hist.idx <= 0 || hist.locked) return;
  clearTimeout(_histSoonT);
  hist.idx--;
  _histRestore(hist.states[hist.idx]);
}
function histRedo() {
  if (hist.idx >= hist.states.length - 1 || hist.locked) return;
  hist.idx++;
  _histRestore(hist.states[hist.idx]);
}

function syncHistBtns() {
  document.getElementById('btnUndo').disabled = hist.idx <= 0;
  document.getElementById('btnRedo').disabled = hist.idx >= hist.states.length - 1;
  document.getElementById('btnUndo').title = hist.idx > 0 ? 'Undo: ' + hist.labels[hist.idx] + ' (Ctrl+Z)' : 'Undo (Ctrl+Z)';
  document.getElementById('btnRedo').title = hist.idx < hist.states.length - 1 ? 'Redo: ' + hist.labels[hist.idx + 1] + ' (Ctrl+Y)' : 'Redo (Ctrl+Y)';
  if (typeof renderHistoryPanel === 'function') renderHistoryPanel();
}

// ── Auto-save & Session Recovery ──────────────────────────────────
// Keyed by template AND gallery item — every gallery design has ED.id = 0, so
// they all shared one slot and one design's recovery was offered on another.
const AUTOSAVE_KEY  = 'editor_autosave_' + ED.id + '_' + (ED.gallery_id || 0);
const AUTOSAVE_TKEY = AUTOSAVE_KEY + '_time';
function clearAutoSave() { try { localStorage.removeItem(AUTOSAVE_KEY); localStorage.removeItem(AUTOSAVE_TKEY); } catch (_) {} }
function checkAutoSave(onDone) {
  let saved = null, time = null;
  try { saved = localStorage.getItem(AUTOSAVE_KEY); time = localStorage.getItem(AUTOSAVE_TKEY); } catch (_) {}
  if (!saved || !time) { onDone(false); return; }
  if (Date.now() - +time > 24 * 60 * 60 * 1000) { clearAutoSave(); onDone(false); return; }
  const mins = Math.max(1, Math.round((Date.now() - +time) / 60000));
  edConfirm('Unsaved work from ' + mins + ' min ago was found for this design. Recover it?', () => {
    hist.locked = true;
    let size = null; try { size = JSON.parse(saved)._size; } catch (_) {}
    if (size && size.w && size.h) resizeCanvasTo(size.w, size.h);
    canvas.loadFromJSON(saved, () => {
      canvas.renderAll(); hist.locked = false;
      histSave(); refreshLayers(); markDirty(); fitZoomIfNeeded();
      ensureFontsFor();
      showToast('Session recovered!');
    });
    onDone(true);
  }, () => {
    clearAutoSave();
    onDone(false);
  });
}

// ── Load template ──────────────────────────────────────────────────
function initEditor() {
  checkAutoSave(recovered => { if (!recovered) _loadNormalContent(); });
}
function _loadNormalContent() {
  if (ED.canvas_json) {
    hist.locked = true;
    let size = null; try { size = JSON.parse(ED.canvas_json)._size; } catch (_) {}
    if (size && size.w && size.h && (size.w !== canvasW || size.h !== canvasH)) resizeCanvasTo(size.w, size.h);
    canvas.loadFromJSON(ED.canvas_json, () => {
      canvas.renderAll(); hist.locked = false;
      histSave(); refreshLayers(); markClean();
      ensureFontsFor().then(() => { if (ED.auto_download) setTimeout(exportPng, 300); });
    }, (o, obj) => { /* reviver: nothing */ });
  } else if (ED.bg_image) {
    const imgEl = new Image();
    imgEl.crossOrigin = 'anonymous';
    imgEl.onload = () => {
      const fImg = new fabric.Image(imgEl);
      fImg.set({ selectable: true, evented: true, data: { isBg: true } });
      const scale = Math.min(canvasW / fImg.width, canvasH / fImg.height);
      fImg.scale(scale);
      fImg.set({ left: canvasW / 2, top: canvasH / 2, originX: 'center', originY: 'center' });
      hist.locked = true; canvas.add(fImg); canvas.sendToBack(fImg); hist.locked = false;
      canvas.renderAll();
      histSave(); refreshLayers(); markClean();
    };
    imgEl.onerror = () => { canvas.renderAll(); histSave(); markClean(); showToast('Template background image could not be loaded', 'err'); };
    imgEl.src = ED.bg_image;
  } else {
    canvas.renderAll();
    histSave(); markClean();
  }
}

// ── Canvas events ──────────────────────────────────────────────────
let snapGuides  = [];   // [{t:'v'|'h', p:number}] — drawn as an overlay, never added as objects
let cropRect    = null;
let isCropping  = false;

// ── Print Guides ────────────────────────────────────────────────────
const BLEED_MM = 3, SAFE_MM = 5, SCREEN_DPI = 96;
let printGuidesOn      = false;
let printGuideObjects  = [];

// ── Snap toggle ─────────────────────────────────────────────────────
let snapEnabled = true;

const _typeName = o => o ? (isTextObj(o) ? 'text' : o.data?.placeholder ? o.data.placeholder + ' slot' : o.type === 'path' ? 'drawing' : o.type) : 'object';
canvas.on('object:added',    e => { histSave('Add ' + _typeName(e.target)); refreshLayers(); });
canvas.on('object:modified', e => { const a = e.action || (e.transform && e.transform.action) || ''; histSave(/rotate/.test(a) ? 'Rotate' : /scale/.test(a) ? 'Resize' : 'Move'); refreshLayers(); refreshProps(); });
canvas.on('object:removed',  () => { histSave('Delete'); refreshLayers(); });
canvas.on('selection:created', e  => { refreshProps(e.selected?.[0]); refreshLayers(); });
canvas.on('selection:updated', e  => { refreshProps(e.selected?.[0]); refreshLayers(); });
canvas.on('selection:cleared', () => { refreshProps(null); refreshLayers(); });
// Typing in a text box was never an undo step.
canvas.on('text:editing:exited', () => { histSave(); refreshLayers(); });
canvas.on('text:changed', () => markDirty());

// Grid, smart-snap guides, print guides and the accessibility/resolution
// highlights are painted on top of the canvas instead of being added as real
// objects. As objects they flooded the undo history, showed up in Select-All,
// and an Undo could resurrect them as permanent lines saved into the design.
let _flagBoxes = [];   // [{obj, color}] temporary highlight boxes
canvas.on('after:render', opt => {
  if (opt.ctx !== canvas.contextContainer) return;   // skip exports (toDataURL also fires after:render)
  if (!gridOn && !snapGuides.length && !printGuidesOn && !_flagBoxes.length) return;
  const ctx = opt.ctx, v = canvas.viewportTransform, z = v[0] || 1;
  ctx.save();
  ctx.transform(v[0], v[1], v[2], v[3], v[4], v[5]);
  if (gridOn) {
    let step = 20; while (canvasW / step > 300 || canvasH / step > 300) step *= 2;
    ctx.strokeStyle = 'rgba(99,102,241,.28)'; ctx.lineWidth = 1 / z;
    ctx.beginPath();
    for (let x = step; x < canvasW; x += step) { ctx.moveTo(x, 0); ctx.lineTo(x, canvasH); }
    for (let y = step; y < canvasH; y += step) { ctx.moveTo(0, y); ctx.lineTo(canvasW, y); }
    ctx.stroke();
  }
  if (printGuidesOn) {
    const bp = mmToPx(BLEED_MM), sp = mmToPx(SAFE_MM);
    ctx.lineWidth = 1.5 / z; ctx.setLineDash([6 / z, 4 / z]);
    ctx.strokeStyle = '#ef4444'; ctx.strokeRect(bp, bp, canvasW - bp * 2, canvasH - bp * 2);   // trim line (bleed zone outside)
    ctx.strokeStyle = '#22c55e'; ctx.strokeRect(sp, sp, canvasW - sp * 2, canvasH - sp * 2);                 // safe area for text
    ctx.setLineDash([]);
  }
  if (snapGuides.length) {
    ctx.strokeStyle = '#6366f1'; ctx.lineWidth = 1 / z; ctx.setLineDash([5 / z, 4 / z]);
    snapGuides.forEach(g => { ctx.beginPath(); if (g.t === 'v') { ctx.moveTo(g.p, 0); ctx.lineTo(g.p, canvasH); } else { ctx.moveTo(0, g.p); ctx.lineTo(canvasW, g.p); } ctx.stroke(); });
    ctx.setLineDash([]);
  }
  _flagBoxes.forEach(f => {
    if (!canvas.contains(f.obj)) return;
    const b = f.obj.getBoundingRect(true, true);
    ctx.strokeStyle = f.color; ctx.lineWidth = 3 / z; ctx.setLineDash([]);
    ctx.strokeRect(b.left - 3 / z, b.top - 3 / z, b.width + 6 / z, b.height + 6 / z);
  });
  ctx.restore();
});
/** Temporarily outline objects (accessibility / resolution checks) without modifying them. */
function flagObjects(objs, color, ms) {
  _flagBoxes = objs.map(obj => ({ obj, color: color || '#ef4444' }));
  canvas.requestRenderAll();
  clearTimeout(flagObjects._t);
  flagObjects._t = setTimeout(() => { _flagBoxes = []; canvas.requestRenderAll(); }, ms || 8000);
}

// ── Tools ──────────────────────────────────────────────────────────
function addText() {
  if (canvas.isDrawingMode || activeTool !== 'select') setActiveTool('select');
  // canvasW/H (design units) — canvas.width is the ZOOMED size, so new text
  // used to land off-centre whenever the view was zoomed.
  const t = new fabric.IText('Enter text here', {
    left: canvasW / 2, top: canvasH / 2,
    originX: 'center', originY: 'center',
    fontSize: Math.max(12, Math.round(Math.min(canvasW, canvasH) * 0.05)), fontFamily: 'Noto Sans', fill: '#1f2937',
    editable: true,
  });
  canvas.add(t).setActiveObject(t).renderAll();
  t.enterEditing(); t.selectAll();
}

function addShape(type) {
  if (canvas.isDrawingMode || activeTool !== 'select') setActiveTool('select');
  const cx = canvasW / 2, cy = canvasH / 2;
  // Shapes scale with the canvas — on a 300-DPI A4 a 180px rectangle was a speck.
  const k = Math.max(1, Math.min(canvasW, canvasH) / 540);
  const base = { left:cx, top:cy, originX:'center', originY:'center', fill:'#4f46e5', scaleX:k, scaleY:k };
  const polyN = (n, r) => { const pp=[]; for(let i=0;i<n;i++){const a=(i*2*Math.PI/n)-Math.PI/2;pp.push({x:r*Math.cos(a),y:r*Math.sin(a)});} return pp; };
  const addPoly = (pts, extra={}) => { const o=new fabric.Polygon(pts,{...base,...extra}); canvas.add(o).setActiveObject(o).renderAll(); };
  const addSVGShape = (svgStr, extra={}) => fabric.loadSVGFromString(svgStr, svg => {
    if (!svg || !svg[0]) { showToast('Shape failed to load', 'err'); return; }
    const o = svg[0]; o.set({...base,...extra, scaleX:(extra.scaleX||1)*k, scaleY:(extra.scaleY||1)*k}); canvas.add(o).setActiveObject(o).renderAll();
  });

  let obj;
  switch(type) {
    case 'rect':    obj = new fabric.Rect({...base, width:180, height:100, rx:6, ry:6}); break;
    case 'circle':  obj = new fabric.Circle({...base, radius:70}); break;
    case 'ellipse': obj = new fabric.Ellipse({...base, rx:90, ry:50}); break;
    case 'tri':     obj = new fabric.Triangle({...base, width:130, height:110}); break;
    case 'line':    obj = new fabric.Line([-80,0,80,0], {left:cx, top:cy, originX:'center', originY:'center', stroke:'#1f2937', strokeWidth:3*k, scaleX:k, scaleY:1}); break;
    case 'star':    addPoly(starPoints(0,0,70,30,5)); return;
    case 'heart':   addSVGShape('<svg><path d="M 0,-30 C -5,-60 -50,-60 -50,-20 C -50,20 0,50 0,70 C 0,50 50,20 50,-20 C 50,-60 5,-60 0,-30 Z"/></svg>', {fill:'#ef4444',scaleX:.8,scaleY:.8}); return;
    case 'arrow':   addPoly([{x:-80,y:-15},{x:20,y:-15},{x:20,y:-35},{x:80,y:0},{x:20,y:35},{x:20,y:15},{x:-80,y:15}]); return;
    case 'diamond': addPoly([{x:0,y:-70},{x:55,y:0},{x:0,y:70},{x:-55,y:0}]); return;
    case 'pentagon': addPoly(polyN(5,65)); return;
    case 'hexagon':  addPoly(polyN(6,65)); return;
    case 'octagon':  addPoly(polyN(8,65)); return;
    case 'cross':    addPoly([{x:-15,y:-60},{x:15,y:-60},{x:15,y:-15},{x:60,y:-15},{x:60,y:15},{x:15,y:15},{x:15,y:60},{x:-15,y:60},{x:-15,y:15},{x:-60,y:15},{x:-60,y:-15},{x:-15,y:-15}]); return;
    case 'cloud':    addSVGShape('<svg viewBox="0 0 200 120"><path d="M 30,90 a 30,30 0 0,1 0,-60 a 20,20 0 0,1 35,-15 a 25,25 0 0,1 45,0 a 20,20 0 0,1 35,15 a 30,30 0 0,1 0,60 z"/></svg>', {fill:'#60a5fa',scaleX:1,scaleY:1}); return;
    case 'sunburst': {
      const pts = [], oR = 80, iR = 38, nRays = 16;
      for (let i = 0; i < nRays * 2; i++) {
        const a = (i * Math.PI) / nRays - Math.PI / 2;
        const r = i % 2 === 0 ? oR : iR;
        pts.push({ x: r * Math.cos(a), y: r * Math.sin(a) });
      }
      addPoly(pts, { fill: '#f59e0b' }); return;
    }
    case 'chevron': {
      const pts = [{x:-70,y:-50},{x:0,y:-50},{x:70,y:0},{x:0,y:50},{x:-70,y:50},{x:-20,y:0}];
      addPoly(pts, { fill: '#ef4444' }); return;
    }
    case 'arch': {
      addSVGShape('<svg viewBox="-100 -60 200 120"><path d="M -80,60 L -80,-10 A 80,70 0 0,1 80,-10 L 80,60 Z"/></svg>', {fill:'#6366f1',scaleX:1.2,scaleY:1}); return;
    }
    case 'wave': {
      addSVGShape('<svg viewBox="0 0 240 80"><path d="M0,40 C30,10 60,70 120,40 C180,10 210,70 240,40 L240,80 L0,80 Z"/></svg>', {fill:'#3b82f6',scaleX:1,scaleY:1.2}); return;
    }
    case 'badge': {
      const bPts = [], bN = 12, bOr = 70, bIr = 58;
      for (let i = 0; i < bN * 2; i++) {
        const a = (i * Math.PI) / bN - Math.PI / 2;
        const r = i % 2 === 0 ? bOr : bIr;
        bPts.push({ x: r * Math.cos(a), y: r * Math.sin(a) });
      }
      addPoly(bPts, { fill: '#6366f1' }); return;
    }
    case 'ribbon': {
      addSVGShape('<svg viewBox="0 0 200 80"><path d="M10,0 L190,0 L170,40 L190,80 L10,80 L30,40 Z"/></svg>', {fill:'#f97316',scaleX:1,scaleY:1}); return;
    }
  }
  if (obj) canvas.add(obj).setActiveObject(obj).renderAll();
}

function addPlaceholder(kind) {
  const cx = canvasW / 2, cy = canvasH / 2;
  const configs = {
    photo:   { w:110, h:140, label:'PHOTO',     dash:[6,4] },
    qr:      { w:100, h:100, label:'QR CODE',   dash:[4,3] },
    barcode: { w:190, h:55,  label:'BARCODE',   dash:[4,3] },
    sign:    { w:160, h:50,  label:'SIGNATURE', dash:[4,3] },
  };
  const cfg = configs[kind];
  if (!cfg) return;
  const grp = new fabric.Group([
    new fabric.Rect({ width:cfg.w, height:cfg.h, fill:'#1e293b', stroke:'#64748b', strokeWidth:1.5, strokeDashArray:cfg.dash, rx:4, ry:4, originX:'center', originY:'center' }),
    new fabric.Text(cfg.label, { fontSize:11, fontFamily:'Arial', fill:'#64748b', originX:'center', originY:'center', selectable:false, evented:false }),
  ], { left:cx, top:cy, originX:'center', originY:'center', data:{ placeholder: kind } });
  canvas.add(grp).setActiveObject(grp).renderAll();
}

/* Read an image File, validate it, and downscale anything above 6000px
   (a 48 MP phone photo used to freeze the tab and bloat the saved design). */
function _readImageFile(file, cb) {
  if (!file) return;
  if (!/^image\//.test(file.type)) { showToast('Please choose an image file (PNG, JPG, WebP, SVG)', 'err'); return; }
  if (file.size > 30 * 1024 * 1024) { showToast('Image is too large (max 30 MB)', 'err'); return; }
  const reader = new FileReader();
  reader.onerror = () => showToast('Could not read that file', 'err');
  reader.onload = ev => {
    const imgEl = new Image();
    imgEl.onload = () => {
      const MAX = 6000;
      if (file.type !== 'image/svg+xml' && (imgEl.naturalWidth > MAX || imgEl.naturalHeight > MAX)) {
        const k = MAX / Math.max(imgEl.naturalWidth, imgEl.naturalHeight);
        const c = document.createElement('canvas');
        c.width = Math.round(imgEl.naturalWidth * k); c.height = Math.round(imgEl.naturalHeight * k);
        const cx = c.getContext('2d'); cx.imageSmoothingQuality = 'high'; cx.drawImage(imgEl, 0, 0, c.width, c.height);
        const small = new Image(); small.onload = () => cb(small); small.src = c.toDataURL(file.type === 'image/jpeg' ? 'image/jpeg' : 'image/png', 0.92);
        showToast('Large photo scaled to ' + c.width + '×' + c.height + ' px', 'info');
      } else cb(imgEl);
    };
    imgEl.onerror = () => showToast('Image failed to load', 'err');
    imgEl.src = ev.target.result;
  };
  reader.readAsDataURL(file);
}
function handleImgUpload(e) {
  const file = e.target.files[0];
  e.target.value = '';
  _readImageFile(file, imgEl => _placeImage(new fabric.Image(imgEl), file.name));
}

// Paste an image straight from the clipboard (screenshot, copied photo…).
document.addEventListener('paste', e => {
  const tag = (document.activeElement || {}).tagName;
  if (tag === 'INPUT' || tag === 'TEXTAREA' || canvas.getActiveObject()?.isEditing) return;
  const items = [...(e.clipboardData?.items || [])];
  const img = items.find(i => i.kind === 'file' && /^image\//.test(i.type));
  if (!img) return;
  e.preventDefault();
  _readImageFile(img.getAsFile(), el => _placeImage(new fabric.Image(el), 'Pasted image'));
});

function openUrlModal() {
  closeEdPanel();
  const m = document.getElementById('urlModal');
  m.style.display = 'flex';
  setTimeout(() => document.getElementById('urlInput').focus(), 50);
}

function addImageFromURL() {
  const url = document.getElementById('urlInput').value.trim();
  if (!url) return;
  if (!/^(https?:)?\/\//i.test(url) && !/^data:image\//i.test(url)) { showToast('Enter a full image link starting with https://', 'err'); return; }
  document.getElementById('urlModal').style.display = 'none';
  document.getElementById('urlInput').value = '';
  showToast('Loading image…');

  // Use a PHP proxy to avoid CORS for external URLs (and so exports don't get
  // blocked by a "tainted" canvas).
  const proxyUrl = '<?= BASE_URL ?>/api/img_proxy.php?url=' + encodeURIComponent(url);
  const imgEl = new Image();
  imgEl.crossOrigin = 'anonymous';
  imgEl.onload = () => {
    const fImg = new fabric.Image(imgEl);
    _placeImage(fImg, 'Web image');
  };
  imgEl.onerror = () => {
    // Try direct load as fallback (for same-origin or CORS-enabled images)
    const imgEl2 = new Image();
    imgEl2.crossOrigin = 'anonymous';
    imgEl2.onload = () => { const fImg = new fabric.Image(imgEl2); _placeImage(fImg, 'Web image'); };
    imgEl2.onerror = () => showToast('Image failed to load. Check the link (it must point directly to an image).', 'err');
    imgEl2.src = url;
  };
  imgEl.src = proxyUrl;
}

function _placeImage(fImg, name) {
  if (activeTool !== 'select') setActiveTool('select');
  const maxW = canvasW * 0.6, maxH = canvasH * 0.6;
  if (fImg.width > maxW)              fImg.scaleToWidth(maxW);
  if (fImg.getScaledHeight() > maxH)  fImg.scaleToHeight(maxH);
  fImg.set({ left: canvasW / 2, top: canvasH / 2, originX: 'center', originY: 'center' });
  if (name) fImg.data = Object.assign({}, fImg.data, { name: String(name).replace(/\.[^.]+$/, '').slice(0, 30) });
  canvas.add(fImg).setActiveObject(fImg).renderAll();
  showToast('Image added!');
}

// ── Image Filters ───────────────────────────────────────────────────
let imgPresets = { gray: false, sepia: false, invert: false, vintage: false, polaroid: false };

function applyImgFilter() {
  const obj = canvas.getActiveObject();
  if (!obj || obj.type !== 'image') return;

  const bv  = +document.getElementById('fBright').value;
  const cv  = +document.getElementById('fContrast').value;
  const sv  = +document.getElementById('fSat').value;
  const blv = +document.getElementById('fBlur').value;
  const nv  = +document.getElementById('fNoise').value;
  const hv  = +(document.getElementById('fHue')?.value      || 0);
  const vv  = +(document.getElementById('fVibrance')?.value  || 0);
  const gv  = +(document.getElementById('fGamma')?.value     || 1);

  _syncImgFilterLabels();

  const filters = [];
  if (bv  !== 0) filters.push(new fabric.Image.filters.Brightness({ brightness: bv }));
  if (cv  !== 0) filters.push(new fabric.Image.filters.Contrast({ contrast: cv }));
  if (sv  !== 0) filters.push(new fabric.Image.filters.Saturation({ saturation: sv }));
  if (blv > 0)   filters.push(new fabric.Image.filters.Blur({ blur: blv }));
  if (nv  > 0)   filters.push(new fabric.Image.filters.Noise({ noise: nv }));
  if (hv  !== 0) filters.push(new fabric.Image.filters.HueRotation({ rotation: hv / 180 }));
  if (vv  !== 0) filters.push(new fabric.Image.filters.Vibrance({ vibrance: vv }));
  if (gv  !== 1) filters.push(new fabric.Image.filters.Gamma({ gamma: [gv, gv, gv] }));
  if (imgPresets.gray)    filters.push(new fabric.Image.filters.Grayscale());
  if (imgPresets.sepia)   filters.push(new fabric.Image.filters.Sepia());
  if (imgPresets.invert)  filters.push(new fabric.Image.filters.Invert());
  if (imgPresets.vintage) filters.push(new fabric.Image.filters.Vintage());
  if (imgPresets.polaroid) { try { filters.push(new fabric.Image.filters.Polaroid()); } catch(e) {} }

  obj.filters = filters;
  try { obj.applyFilters(); } catch (e) { showToast('This image cannot be filtered (it is from another website)', 'err'); return; }
  canvas.renderAll();
  histSaveSoon();
}
function _syncImgFilterLabels() {
  const g = id => document.getElementById(id);
  g('fBrightVal').textContent   = (+g('fBright').value).toFixed(2);
  g('fContrastVal').textContent = (+g('fContrast').value).toFixed(2);
  g('fSatVal').textContent      = (+g('fSat').value).toFixed(2);
  g('fBlurVal').textContent     = (+g('fBlur').value).toFixed(2);
  g('fNoiseVal').textContent    = Math.round(+g('fNoise').value);
  if (g('fHueVal'))      g('fHueVal').textContent      = g('fHue').value;
  if (g('fVibranceVal')) g('fVibranceVal').textContent = (+g('fVibrance').value).toFixed(2);
  if (g('fGammaVal'))    g('fGammaVal').textContent    = (+g('fGamma').value).toFixed(1);
}
/* Show the SELECTED image's own filter values. The sliders used to keep the
   previous image's values, and touching one slider then stamped all of them
   (brightness, blur, sepia…) onto the newly selected image. */
function syncImgFilterUI(obj) {
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
  const vals = { fBright:0, fContrast:0, fSat:0, fBlur:0, fNoise:0, fHue:0, fVibrance:0, fGamma:1 };
  imgPresets = { gray:false, sepia:false, invert:false, vintage:false, polaroid:false };
  (obj.filters || []).forEach(f => {
    if (!f) return;
    switch (f.type) {
      case 'Brightness':  vals.fBright = f.brightness; break;
      case 'Contrast':    vals.fContrast = f.contrast; break;
      case 'Saturation':  vals.fSat = f.saturation; break;
      case 'Blur':        vals.fBlur = f.blur; break;
      case 'Noise':       vals.fNoise = f.noise; break;
      case 'HueRotation': vals.fHue = Math.round(f.rotation * 180); break;
      case 'Vibrance':    vals.fVibrance = f.vibrance; break;
      case 'Gamma':       vals.fGamma = (f.gamma && f.gamma[0]) || 1; break;
      case 'Grayscale':   imgPresets.gray = true; break;
      case 'Sepia':       imgPresets.sepia = true; break;
      case 'Invert':      imgPresets.invert = true; break;
      case 'Vintage':     imgPresets.vintage = true; break;
      case 'Polaroid':    imgPresets.polaroid = true; break;
    }
  });
  Object.entries(vals).forEach(([k, v]) => set(k, v));
  ['gray','sepia','invert','vintage','polaroid'].forEach(n =>
    document.getElementById('btn' + n.charAt(0).toUpperCase() + n.slice(1))?.classList.toggle('active', imgPresets[n]));
  _syncImgFilterLabels();
}

function toggleImgPreset(name) {
  imgPresets[name] = !imgPresets[name];
  document.getElementById('btn' + name.charAt(0).toUpperCase() + name.slice(1))
    .classList.toggle('active', imgPresets[name]);
  applyImgFilter();
}

function resetImgFilters() {
  imgPresets = { gray: false, sepia: false, invert: false, vintage: false, polaroid: false };
  ['btnGray','btnSepia','btnInvert','btnVintage','btnPolaroid']
    .forEach(id => document.getElementById(id)?.classList.remove('active'));
  ['fBright','fContrast','fSat','fBlur','fNoise','fHue','fVibrance'].forEach(id => { const el = document.getElementById(id); if (el) el.value = 0; });
  const fG = document.getElementById('fGamma'); if (fG) fG.value = 1;
  applyImgFilter();
}

function flipImg(axis) {
  const objs = canvas.getActiveObjects();
  if (!objs.length) { showToast('Select an object first', 'info'); return; }
  objs.forEach(obj => { if (axis === 'x') obj.set('flipX', !obj.flipX); else obj.set('flipY', !obj.flipY); });
  canvas.renderAll(); histSave();
}

function deleteSelected() {
  const objs = canvas.getActiveObjects();
  if (!objs.length) return;
  if (canvas.getActiveObject()?.isEditing) return;
  hist.locked = true;
  canvas.discardActiveObject();
  objs.forEach(o => canvas.remove(o));
  hist.locked = false;
  canvas.renderAll(); histSave(); refreshLayers();
  showToast(objs.length > 1 ? objs.length + ' items deleted (Ctrl+Z to undo)' : 'Deleted (Ctrl+Z to undo)');
}

/* Clone + place. Works for multi-selections too: before, duplicating or
   pasting several objects added a broken, empty "selection" object. */
function _placeClone(clone, dx, dy) {
  canvas.discardActiveObject();
  clone.set({ left: clone.left + dx, top: clone.top + dy, evented: true });
  hist.locked = true;
  if (clone.type === 'activeSelection') {
    clone.canvas = canvas;
    clone.forEachObject(o => canvas.add(o));
    clone.setCoords();
  } else {
    canvas.add(clone);
  }
  hist.locked = false;
  canvas.setActiveObject(clone).requestRenderAll();
  histSave(); refreshLayers();
}
function dupSelected() {
  const obj = canvas.getActiveObject();
  if (!obj) { showToast('Select something to duplicate', 'info'); return; }
  obj.clone(clone => _placeClone(clone, 22, 22), JSON_PROPS);
}

// ── Clipboard ──────────────────────────────────────────────────────
let clipboard = null, _pasteN = 0;

function copySelected() {
  const obj = canvas.getActiveObject();
  if (!obj) return;
  obj.clone(cl => { clipboard = cl; _pasteN = 0; showToast('Copied!'); }, JSON_PROPS);
}

function cutSelected() {
  const obj = canvas.getActiveObject();
  if (!obj) return;
  obj.clone(cl => {
    clipboard = cl; _pasteN = 0;
    deleteSelected();
    showToast('Cut!');
  }, JSON_PROPS);
}

function pasteSelected() {
  if (!clipboard) { showToast('Clipboard empty', 'info'); return; }
  _pasteN++;
  // Each paste lands a little further away, instead of stacking on the same spot.
  clipboard.clone(cl => { _placeClone(cl, 25 * _pasteN, 25 * _pasteN); showToast('Pasted!'); }, JSON_PROPS);
}

// ── Group / Ungroup ────────────────────────────────────────────────
// Fabric's own toGroup()/toActiveSelection() keep every object exactly where
// it was; the manual matrix math before shifted rotated/flipped children.
function groupSelected() {
  const a = canvas.getActiveObject();
  if (!a || a.type !== 'activeSelection') { showToast('Select 2+ objects to group (Shift+click)', 'info'); return; }
  hist.locked = true;
  a.toGroup();
  hist.locked = false;
  canvas.requestRenderAll();
  histSave(); refreshLayers(); refreshProps();
  showToast('Grouped!');
}

function ungroupSelected() {
  const obj = canvas.getActiveObject();
  if (!obj || obj.type !== 'group') { showToast('Select a group to ungroup', 'info'); return; }
  hist.locked = true;
  obj.toActiveSelection();
  hist.locked = false;
  canvas.requestRenderAll();
  histSave(); refreshLayers(); refreshProps();
  showToast('Ungrouped!');
}

// ── Text Background ────────────────────────────────────────────────
function applyTextBg(color) {
  const objs = canvas.getActiveObjects().filter(isTextObj);
  if (!objs.length) return;
  objs.forEach(o => o.set('textBackgroundColor', color === 'transparent' ? '' : color));
  canvas.renderAll(); histSaveSoon();
}

// ── Canvas Resize ──────────────────────────────────────────────────
function openResizeModal() {
  document.getElementById('resizeW').value = canvasW;
  document.getElementById('resizeH').value = canvasH;
  document.getElementById('resizeModal').style.display = 'flex';
}
function setResizePreset(w, h) {
  document.getElementById('resizeW').value = w;
  document.getElementById('resizeH').value = h;
}
function applyResize() {
  const w = parseInt(document.getElementById('resizeW').value, 10);
  const h = parseInt(document.getElementById('resizeH').value, 10);
  if (!w || !h || w < 100 || h < 100) { showToast('Min 100px required', 'err'); return; }
  if (w > NC_MAX_PX || h > NC_MAX_PX) { showToast('Max ' + NC_MAX_PX + 'px per side', 'err'); return; }
  resizeCanvasTo(w, h);
  canvas.renderAll(); histSave();
  document.getElementById('resizeModal').style.display = 'none';
  showToast('Canvas resized to ' + w + '×' + h + 'px');
  fitZoomIfNeeded(true);
}

// ── Export SVG / JSON ──────────────────────────────────────────────
function _fileBase() {
  return ((document.getElementById('tplNameInput').value || '').trim() || 'template').replace(/[\\/:*?"<>|]+/g, '').slice(0, 80) || 'template';
}
function _downloadBlob(blob, name) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = name;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 4000);   // revoking immediately could cancel the download
}
function exportSVG() {
  let svg;
  try {
    svg = canvas.toSVG({ width: canvasW, height: canvasH, viewBox: { x: 0, y: 0, width: canvasW, height: canvasH } });
  } catch (e) { showToast('SVG export failed: ' + e.message, 'err'); return; }
  _downloadBlob(new Blob([svg], { type:'image/svg+xml' }), _fileBase() + '.svg');
  showToast('SVG exported');
}

function exportJSON() {
  const json = JSON.stringify(canvasJSON(), null, 2);
  _downloadBlob(new Blob([json], { type:'application/json' }), _fileBase() + '.json');
  showToast('JSON exported');
}

function bringFwd() {
  const obj = canvas.getActiveObject();
  if (obj) { canvas.bringForward(obj); canvas.renderAll(); histSave(); refreshLayers(); }
}

function sendBwd() {
  const obj = canvas.getActiveObject();
  if (obj) { canvas.sendBackwards(obj); canvas.renderAll(); histSave(); refreshLayers(); }
}

// ── Properties panel ───────────────────────────────────────────────
function refreshProps(obj) {
  obj = obj !== undefined ? obj : canvas.getActiveObject();
  // With a multi-selection Fabric hands us its first member — show the
  // selection's own box/angle/opacity instead so typed values affect all.
  const active = canvas.getActiveObject();
  if (obj && active && active.type === 'activeSelection') obj = active;
  const pCanvas = document.getElementById('pCanvas');
  const pObj    = document.getElementById('pObj');

  if (!obj) {
    pCanvas.style.display = ''; pObj.style.display = 'none';
    const bg = canvas.backgroundColor;
    const bgEl = document.getElementById('pCanvasBg');
    if (bgEl && typeof bg === 'string' && bg && bg !== 'transparent') bgEl.value = colorToHex(bg);
    return;
  }
  pCanvas.style.display = 'none'; pObj.style.display = '';
  // Deliberately no mobAutoOpenProps() here — this used to jump straight to
  // the Properties sheet on every select/move/resize, covering the canvas
  // every time an element was merely tapped. Same fix as the Generate ID
  // Cards page: selecting an object only updates the panel's data; opening
  // the sheet is a manual "Properties" tap (openEdPanel()) from now on.

  const multi = obj.type === 'activeSelection';
  const ae = document.activeElement;
  const setVal = (id, v) => { const el = document.getElementById(id); if (el && el !== ae) el.value = v; };
  // X/Y/W/H are the visible bounding box, so they mean the same thing for
  // every object regardless of its internal origin or rotation.
  const br = obj.getBoundingRect(true, true);
  setVal('pX', Math.round(br.left));
  setVal('pY', Math.round(br.top));
  setVal('pW', Math.round(br.width));
  setVal('pH', Math.round(br.height));
  const ang = Math.round((((obj.angle ?? 0) + 540) % 360) - 180);
  setVal('pAngle', ang);
  document.getElementById('pAngleVal').textContent = ang + '°';
  const opa = Math.round((obj.opacity ?? 1) * 100);
  setVal('pOpacity', opa);
  document.getElementById('pOpacityVal').textContent = opa + '%';
  const lbl = document.getElementById('pAlignLbl');
  if (lbl) lbl.textContent = multi ? 'Align selected objects to each other' : 'Align to canvas';
  const lockBtn = document.getElementById('btnLockObj');
  if (lockBtn) lockBtn.innerHTML = '<i class="bi ' + (obj.lockMovementX ? 'bi-lock-fill' : 'bi-lock') + '"></i>';

  const blendSel = document.getElementById('pBlend');
  if (blendSel) blendSel.value = obj.globalCompositeOperation || 'source-over';

  const kids = multi ? obj.getObjects() : [obj];
  const isText  = kids.every(isTextObj);
  const isShape = !isText && kids.every(o => ['rect','circle','triangle','line','polygon','path','ellipse','polyline'].includes(o.type));
  const isImage = !multi && obj.type === 'image';
  const ref = kids[0];

  document.getElementById('pTextSec').style.display        = isText  ? '' : 'none';
  document.getElementById('pTextOutlineSec').style.display  = isText  ? '' : 'none';
  document.getElementById('pShapeSec').style.display        = (isShape || isImage) ? '' : 'none';
  document.getElementById('pImgSec').style.display          = isImage ? '' : 'none';
  document.getElementById('pGradSec').style.display         = (isShape || isText) ? '' : 'none';
  document.getElementById('pPatternSec').style.display      = isShape ? '' : 'none';
  document.getElementById('pFadeSec').style.display         = (!multi && (isShape || isImage)) ? '' : 'none';

  // Sync gradient pickers if object already has a gradient fill.
  // obj.data._grad holds the c1/cMid/c2/type params this tool applied.
  if ((isShape || isText) && ref.data && ref.data._grad) {
    const g = ref.data._grad;
    document.getElementById('gC1').value   = g.c1   || '#4f46e5';
    document.getElementById('gCMid').value = g.cMid || '#7c3aed';
    document.getElementById('gC2').value   = g.c2   || '#ec4899';
    if (g.type !== 'custom2pt') document.getElementById('gAngle').value = g.ang || 0;
    document.getElementById('gAngleVal').textContent = (document.getElementById('gAngle').value) + '°';
    ['gBtnLinear','gBtnRadial','gBtnDiamond','gBtnConical'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.classList.toggle('active', id === ({linear:'gBtnLinear',radial:'gBtnRadial',diamond:'gBtnDiamond',conical:'gBtnConical'})[g.type]);
    });
    _previewGradSwatch();
  } else if ((isShape || isText) && ref.fill && typeof ref.fill === 'object' && ref.fill.colorStops) {
    const stops = ref.fill.colorStops;
    if (stops.length >= 2) {
      document.getElementById('gC1').value   = colorToHex(stops[0].color);
      document.getElementById('gC2').value   = colorToHex(stops[stops.length - 1].color);
      document.getElementById('gCMid').value = colorToHex(stops[Math.floor(stops.length/2)].color);
      _previewGradSwatch();
    }
  } else if (isShape || isText) {
    // No gradient on this object — clear stale active-state from whichever
    // type button was highlighted for the previously-selected object.
    ['gBtnLinear','gBtnRadial','gBtnDiamond','gBtnConical'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.classList.remove('active');
    });
  }

  if (isText) {
    setVal('pTStroke', colorToHex(ref.stroke) || '#000000');
    setVal('pTStrokeW', ref.strokeWidth || 0);
    const sel = document.getElementById('pFont');
    const fam = ref.fontFamily || 'Noto Sans';
    if (sel && ![...sel.options].some(o => o.value === fam)) { const o = document.createElement('option'); o.value = o.textContent = fam; sel.appendChild(o); }
    setVal('pFont', fam);
    setVal('pFontSize', Math.round(ref.fontSize || 24));
    setVal('pTextColor', colorToHex(ref.fill) || '#1f2937');
    document.getElementById('btnB').classList.toggle('active',  ref.fontWeight === 'bold' || +ref.fontWeight >= 600);
    document.getElementById('btnI').classList.toggle('active',  ref.fontStyle  === 'italic');
    document.getElementById('btnU').classList.toggle('active',  !!ref.underline);
    document.getElementById('btnLT').classList.toggle('active', !!ref.linethrough);
    setVal('pLH', ref.lineHeight || 1.16);
    document.getElementById('pLHVal').textContent = (ref.lineHeight || 1.16).toFixed(2);
    setVal('pCharSp', ref.charSpacing || 0);
    const tbg = ref.textBackgroundColor;
    setVal('pTextBg', (tbg && tbg !== 'transparent') ? colorToHex(tbg) : '#ffffff');
  }

  if (isShape || isImage) {
    const noFill = !ref.fill || ref.fill === 'transparent';
    setVal('pFill', noFill ? '#4f46e5' : colorToHex(ref.fill));
    document.getElementById('pNoFill').checked = noFill;
    setVal('pStroke', colorToHex(ref.stroke) || '#000000');
    setVal('pStrokeW', ref.strokeWidth || 0);
    setVal('pRx', ref.rx || 0);
  }
  if (isImage) syncImgFilterUI(obj);

  // Shadow
  const hasShadow = !!ref.shadow;
  document.getElementById('pShadow').checked = hasShadow;
  document.getElementById('pShadowOpts').style.display = hasShadow ? '' : 'none';
  if (hasShadow) {
    setVal('pShadowColor', colorToHex(ref.shadow.color) || '#000000');
    setVal('pShadowBlur', ref.shadow.blur ?? 10);
    setVal('pShadowX', ref.shadow.offsetX ?? 3);
    setVal('pShadowY', ref.shadow.offsetY ?? 3);
  }
}

// ── Apply property helpers ─────────────────────────────────────────
// Every setter applies to all selected objects and records an undo step.
function _targets(filter) { return canvas.getActiveObjects().filter(filter || (() => true)); }
function applyProp(prop, val) {
  const a = canvas.getActiveObject();
  if (!a) return;
  if ((prop === 'left' || prop === 'top') && !isFinite(val)) return;
  if (prop === 'left' || prop === 'top') {
    // X/Y fields show the visible box — move by the difference.
    const br = a.getBoundingRect(true, true);
    if (prop === 'left') a.left += val - br.left; else a.top += val - br.top;
  } else if (prop === 'angle') {
    if (!isFinite(val)) return;
    a.rotate(val);                       // rotate around the centre (set('angle') swung left/top-origin objects around their corner)
  } else if (prop === 'opacity') {
    a.set('opacity', Math.max(0, Math.min(1, val)));
  } else {
    const objs = _targets();
    objs.forEach(o => {
      o.set(prop, val);
      // Picking a solid fill must also forget a stored gradient, otherwise the
      // gradient silently came back the next time the object was moved.
      if (prop === 'fill' && o.data && o.data._grad) o.data._grad = null;
    });
  }
  a.setCoords(); canvas.requestRenderAll();
  histSaveSoon();
}

function applyScale(dim, val) {
  const obj = canvas.getActiveObject();
  if (!obj || !(val > 0)) return;          // empty field → 0 used to make the object vanish
  const br = obj.getBoundingRect(true, true);
  const f = dim === 'w' ? val / (br.width || 1) : val / (br.height || 1);
  if (dim === 'w') obj.scaleX *= f; else obj.scaleY *= f;
  obj.setCoords(); canvas.requestRenderAll();
  histSaveSoon();
}

function applyTextProp(prop, val) {
  const objs = _targets(isTextObj);
  if (!objs.length) return;
  if (prop === 'fontSize' && !(val > 0)) return;
  objs.forEach(o => {
    o.set(prop, val);
    if (prop === 'fill' && o.data && o.data._grad) o.data._grad = null;
    if (prop === 'stroke' && !o.strokeWidth) o.set({ strokeWidth: 1, paintFirst: 'stroke' });
    if (o.initDimensions) o.initDimensions();
    o.setCoords();
  });
  canvas.requestRenderAll();
  if (prop === 'fontFamily' || prop === 'fontWeight') ensureFontsFor(objs).then(histSave);
  else histSaveSoon();
}

function toggleStyle(style) {
  const objs = _targets(isTextObj);
  if (!objs.length) return;
  const o0 = objs[0];
  if (style === 'bold') {
    const next = (o0.fontWeight === 'bold' || +o0.fontWeight >= 600) ? 'normal' : 'bold';
    objs.forEach(o => o.set('fontWeight', next));
    document.getElementById('btnB').classList.toggle('active', next === 'bold');
    ensureFontsFor(objs);
  } else if (style === 'italic') {
    const next = o0.fontStyle === 'italic' ? 'normal' : 'italic';
    objs.forEach(o => o.set('fontStyle', next));
    document.getElementById('btnI').classList.toggle('active', next === 'italic');
  } else if (style === 'underline') {
    const next = !o0.underline;
    objs.forEach(o => o.set('underline', next));
    document.getElementById('btnU').classList.toggle('active', next);
  } else if (style === 'linethrough') {
    const next = !o0.linethrough;
    objs.forEach(o => o.set('linethrough', next));
    document.getElementById('btnLT').classList.toggle('active', next);
  }
  objs.forEach(o => { if (o.initDimensions) o.initDimensions(); o.setCoords(); });
  canvas.renderAll(); histSave();
}

function applyRoundCorner(val) {
  const objs = _targets(o => o.type === 'rect');
  if (!objs.length) { showToast('Corner radius works on rectangles', 'info'); return; }
  objs.forEach(o => o.set({ rx: val, ry: val }));
  canvas.renderAll(); histSaveSoon();
}

function applyBlendMode(mode) {
  const objs = _targets();
  if (!objs.length) return;
  objs.forEach(o => o.set('globalCompositeOperation', mode));
  canvas.renderAll(); histSave();
}

function setCanvasBg(color) {
  canvas.setBackgroundColor(color, canvas.renderAll.bind(canvas));
  // "BG colour" eraser strokes follow the new background (they showed up as
  // coloured scribbles after changing it).
  canvas.getObjects().forEach(o => { if (o.data?.eraserPath && o.globalCompositeOperation !== 'destination-out') o.set('stroke', color); });
  const btn = document.getElementById('btnTransBg');
  if (btn) { btn.style.background = '#334155'; btn.style.color = '#e2e8f0'; }
  canvas.requestRenderAll();
  histSaveSoon();      // the colour picker fires on every drag step — one undo step, not 60
}

function toggleShadow(on) {
  const objs = _targets();
  if (!objs.length) return;
  objs.forEach(o => o.set('shadow', on ? new fabric.Shadow({
    color: document.getElementById('pShadowColor').value,
    blur:  +document.getElementById('pShadowBlur').value,
    offsetX: +document.getElementById('pShadowX').value,
    offsetY: +document.getElementById('pShadowY').value,
  }) : null));
  document.getElementById('pShadowOpts').style.display = on ? '' : 'none';
  canvas.renderAll(); histSave();
}

function updateShadow() {
  const objs = _targets(o => !!o.shadow);
  if (!objs.length) return;
  objs.forEach(o => {
    o.shadow.color   = document.getElementById('pShadowColor').value;
    o.shadow.blur    = +document.getElementById('pShadowBlur').value || 0;
    o.shadow.offsetX = +document.getElementById('pShadowX').value || 0;
    o.shadow.offsetY = +document.getElementById('pShadowY').value || 0;
    o.dirty = true;
  });
  canvas.renderAll(); histSaveSoon();
}

// ── Layers ─────────────────────────────────────────────────────────
function _layerLabel(obj) {
  if (obj.data?.name) return obj.data.name;
  if (isTextObj(obj)) return '"' + ((obj.text || 'Text').slice(0,18)) + ((obj.text || '').length > 18 ? '…' : '') + '"';
  if (obj.data?.isCropOverlay) return 'Crop Area';
  if (obj.data?.placeholder)   return obj.data.placeholder.toUpperCase() + ' SLOT';
  if (obj.data?.isBg)          return 'Background';
  if (obj.data?.fx3d)          return '3D: ' + (obj.data.origText || '').slice(0, 14);
  if (obj.data?.warpedFrom)    return 'Warp: ' + obj.data.warpedFrom.slice(0, 14);
  if (obj.data?.eraserPath)    return 'Eraser stroke';
  return obj.type.charAt(0).toUpperCase() + obj.type.slice(1);
}
/* Rebuilt once per frame at most (it was rebuilt for every single object a
   template/undo added), with escaped names, drag-to-reorder and rename. */
let _layersRaf = 0;
function refreshLayers() {
  if (_layersRaf) return;
  _layersRaf = requestAnimationFrame(() => { _layersRaf = 0; _renderLayers(); });
}
function _renderLayers() {
  const list = document.getElementById('layersList');
  const allObjs = canvas.getObjects();
  const objs = allObjs.filter(o => !o.data?.grid && !o.data?.isSnapGuide && !o.data?.printGuide);
  const active = canvas.getActiveObjects();
  list.innerHTML = '';
  if (!objs.length) { list.innerHTML = '<div style="font-size:.72rem;color:#64748b;padding:.4rem;">No layers yet — add text, a shape or an image.</div>'; return; }
  [...objs].reverse().forEach(obj => {
    const realIdx = allObjs.indexOf(obj);
    const el = document.createElement('div');
    const isLocked = obj.selectable === false && obj.evented === false;
    el.className = 'layer-item' + (active.includes(obj) ? ' sel' : '') + (isLocked ? ' locked' : '');
    el.draggable = true;
    el.dataset.idx = realIdx;
    const icon = {
      'i-text':'bi-type','text':'bi-type','textbox':'bi-type',
      rect:'bi-square',circle:'bi-circle',triangle:'bi-triangle',
      line:'bi-slash-lg',image:'bi-image',group:'bi-collection',
      path:'bi-bezier2', polygon:'bi-pentagon', ellipse:'bi-circle'
    }[obj.type] || 'bi-circle';
    el.innerHTML = `<i class="bi ${icon}"></i>
      <span class="lname" style="${isLocked ? 'opacity:.5;' : ''}${obj.visible === false ? 'text-decoration:line-through;opacity:.5;' : ''}" title="Double-click to rename">${escHtml(_layerLabel(obj))}</span>
      <i class="bi ${obj.visible === false ? 'bi-eye-slash' : 'bi-eye'} layer-vis"
         onclick="event.stopPropagation(); toggleVis(${realIdx})" title="Toggle visibility"></i>
      <i class="bi ${isLocked ? 'bi-lock-fill' : 'bi-unlock'} layer-vis"
         style="${isLocked ? 'color:#f59e0b;' : ''}"
         onclick="event.stopPropagation(); toggleLock(${realIdx})" title="${isLocked ? 'Unlock' : 'Lock'}"></i>`;
    el.onclick = (ev) => {
      if (isLocked) { showToast('Layer is locked — click the lock icon to unlock', 'info'); return; }
      if ((ev.shiftKey || ev.ctrlKey || ev.metaKey) && canvas.getActiveObject()) {
        const cur = canvas.getActiveObjects();
        const next = cur.includes(obj) ? cur.filter(o => o !== obj) : cur.concat(obj);
        canvas.discardActiveObject();
        if (next.length === 1) canvas.setActiveObject(next[0]);
        else if (next.length) canvas.setActiveObject(new fabric.ActiveSelection(next, { canvas }));
      } else canvas.setActiveObject(obj);
      canvas.requestRenderAll(); refreshProps(); refreshLayers();
    };
    el.ondblclick = () => {
      edPromptModal('Rename layer', [{ id:'n', label:'Layer name', value:_layerLabel(obj).replace(/^"|"$/g,'') }], v => {
        obj.data = Object.assign({}, obj.data, { name: (v.n || '').trim().slice(0, 40) || undefined });
        histSave(); refreshLayers();
      });
    };
    // Drag & drop to change stacking order.
    el.addEventListener('dragstart', e => { e.dataTransfer.setData('text/plain', String(realIdx)); e.dataTransfer.effectAllowed = 'move'; });
    el.addEventListener('dragover', e => { e.preventDefault(); el.classList.add('drag-over'); });
    el.addEventListener('dragleave', () => el.classList.remove('drag-over'));
    el.addEventListener('drop', e => {
      e.preventDefault(); el.classList.remove('drag-over');
      const from = +e.dataTransfer.getData('text/plain');
      const moving = allObjs[from];
      if (!moving || moving === obj) return;
      canvas.moveTo(moving, canvas.getObjects().indexOf(obj));
      canvas.requestRenderAll(); histSave(); refreshLayers();
    });
    list.appendChild(el);
  });
}

function toggleVis(idx) {
  const obj = canvas.item(idx);
  if (!obj) return;
  obj.visible = !obj.visible;
  if (!obj.visible && canvas.getActiveObjects().includes(obj)) canvas.discardActiveObject();
  canvas.renderAll(); refreshLayers(); histSave();
}

function toggleLock(idx) {
  const obj = canvas.item(idx);
  if (!obj) return;
  const wasLocked = obj.selectable === false && obj.evented === false;
  _setLocked(obj, !wasLocked);
  if (!wasLocked && canvas.getActiveObjects().includes(obj)) canvas.discardActiveObject();
  canvas.renderAll(); refreshLayers(); refreshProps(); histSave();
}
function _setLocked(obj, lock) {
  obj.set({ selectable: !lock, evented: !lock, lockMovementX: lock, lockMovementY: lock, lockRotation: lock, lockScalingX: lock, lockScalingY: lock, hasControls: !lock });
}
function toggleLockActive() {
  const objs = canvas.getActiveObjects();
  if (!objs.length) return;
  canvas.discardActiveObject();
  objs.forEach(o => _setLocked(o, true));
  canvas.renderAll(); refreshLayers(); histSave();
  showToast((objs.length > 1 ? objs.length + ' layers' : 'Layer') + ' locked — unlock it from the Layers list', 'info');
}

// ── Zoom ────────────────────────────────────────────────────────────
let curZoom = 1;
function _fitZoomValue() {
  const area = document.getElementById('canvasArea');
  if (!area || !canvasW || !canvasH) return 1;
  const pad = window.innerWidth <= 900 ? 24 : 64;
  const availW = area.clientWidth - pad, availH = area.clientHeight - pad;
  if (availW <= 0 || availH <= 0) return 1;
  return Math.max(0.05, Math.round(Math.min(availW / canvasW, availH / canvasH, 1) * 100) / 100);
}
function _syncZoomSel(z, isFit) {
  const sel = document.getElementById('zoomSel');
  if (!sel) return;
  const fitOpt = [...sel.options].find(o => o.value === 'fit');
  if (fitOpt) fitOpt.textContent = isFit ? 'Fit (' + Math.round(z * 100) + '%)' : 'Fit';
  if (isFit) { sel.value = 'fit'; return; }
  let o = [...sel.options].find(x => x.value !== 'fit' && Math.abs(+x.value - z) < 0.001);
  if (!o) {   // show the real zoom (wheel/pinch values) instead of a misleading nearby preset
    o = [...sel.options].find(x => x.dataset.custom) || document.createElement('option');
    o.dataset.custom = '1'; o.value = String(z); o.textContent = Math.round(z * 100) + '%';
    if (!o.parentNode) sel.appendChild(o);
  }
  sel.value = o.value;
}
function setZoom(val) {
  const isFit = val === 'fit';
  let z = isFit ? _fitZoomValue() : parseFloat(val);
  if (!isFinite(z) || z <= 0) return;
  z = Math.max(0.05, Math.min(4, z));
  curZoom = z;
  canvas.setZoom(curZoom);
  canvas.setDimensions({ width: Math.round(canvasW * curZoom), height: Math.round(canvasH * curZoom) });
  _syncZoomSel(z, isFit);
}

// Ctrl/⌘ + wheel = zoom (like every design tool). A plain wheel now scrolls a
// canvas larger than the screen — before, ANY wheel movement zoomed, so a big
// design could not be scrolled at all, and the % shown drifted from reality.
canvas.on('mouse:wheel', opt => {
  const e = opt.e;
  const area = document.getElementById('canvasArea');
  const scrollable = area && (area.scrollHeight > area.clientHeight + 2 || area.scrollWidth > area.clientWidth + 2);
  if (!e.ctrlKey && !e.metaKey && scrollable) return;
  const z = Math.min(4, Math.max(0.05, canvas.getZoom() * (e.deltaY > 0 ? 0.92 : 1.08)));
  setZoom(z);
  e.preventDefault(); e.stopPropagation();
});

/* Fit large designs to the screen on open (a 300-DPI A4 opened at 100% was
   several screens wide). On phones always fit. */
function fitZoomIfNeeded(force) {
  const fz = _fitZoomValue();
  if (force || window.innerWidth <= 900 || fz < 1) setZoom('fit');
}
function mobileFitZoom() { if (window.innerWidth <= 900) setZoom('fit'); }
let _edResizeTid = null;
window.addEventListener('resize', () => {
  clearTimeout(_edResizeTid);
  _edResizeTid = setTimeout(() => { if (document.getElementById('zoomSel')?.value === 'fit') setZoom('fit'); }, 150);
});

// Pinch-to-zoom (2-finger) on the canvas area — mirrors the desktop mouse-wheel zoom above
(function() {
  const area = document.getElementById('canvasArea');
  if (!area) return;
  let pinchStartDist = null, pinchStartZoom = 1;
  const dist = (t0, t1) => Math.hypot(t1.clientX - t0.clientX, t1.clientY - t0.clientY);
  area.addEventListener('touchstart', e => {
    if (e.touches.length === 2) { pinchStartDist = dist(e.touches[0], e.touches[1]); pinchStartZoom = canvas.getZoom(); }
  }, { passive: true });
  area.addEventListener('touchmove', e => {
    if (e.touches.length === 2 && pinchStartDist) {
      setZoom(Math.min(4, Math.max(0.1, pinchStartZoom * (dist(e.touches[0], e.touches[1]) / pinchStartDist))));
      e.preventDefault();
    }
  }, { passive: false });
  area.addEventListener('touchend', e => { if (e.touches.length < 2) pinchStartDist = null; }, { passive: true });
})();

// Space + drag = pan (scroll) a zoomed-in canvas.
(function() {
  const area = document.getElementById('canvasArea');
  let space = false, st = null;
  document.addEventListener('keydown', e => {
    if (e.code !== 'Space' || e.repeat) return;
    const t = (document.activeElement || {}).tagName;
    if (t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT' || t === 'BUTTON' || canvas.getActiveObject()?.isEditing || canvas.isDrawingMode) return;
    space = true; canvas.skipTargetFind = true; canvas.selection = false; canvas.defaultCursor = 'grab'; e.preventDefault();
  });
  document.addEventListener('keyup', e => {
    if (e.code !== 'Space' || !space) return;
    space = false; canvas.skipTargetFind = false; canvas.selection = true; canvas.defaultCursor = 'default';
  });
  canvas.on('mouse:down', o => { if (!space) return; const p = o.e.touches ? o.e.touches[0] : o.e; st = { x: p.clientX, y: p.clientY, l: area.scrollLeft, t: area.scrollTop }; area.classList.add('panning'); });
  canvas.on('mouse:move', o => { if (!st) return; const p = o.e.touches ? o.e.touches[0] : o.e; area.scrollLeft = st.l - (p.clientX - st.x); area.scrollTop = st.t - (p.clientY - st.y); });
  canvas.on('mouse:up', () => { st = null; area.classList.remove('panning'); });
})();

function openEdPanel() {
  document.getElementById('propsPanel')?.classList.add('mob-open');
  document.getElementById('edSheetBackdrop')?.classList.add('show');
}
function closeEdPanel() {
  document.getElementById('propsPanel')?.classList.remove('mob-open');
  document.getElementById('edSheetBackdrop')?.classList.remove('show');
}
/* Selecting an object jumps straight to its Properties sheet on mobile — the canvas
   stays visible above the sheet since it's a partial bottom sheet, not full-screen */
function mobAutoOpenProps() {
  if (window.innerWidth <= 900) openEdPanel();
}

// ── Grid ────────────────────────────────────────────────────────────
// Painted as an overlay (see the after:render handler) — never saved, never in undo.
let gridOn = false;
function toggleGrid() {
  gridOn = !gridOn;
  document.getElementById('btnGrid').classList.toggle('active', gridOn);
  canvas.requestRenderAll();
}

// ── Save ────────────────────────────────────────────────────────────
function _designName() {
  return ((document.getElementById('tplNameInput')?.value || '').trim() || ED.name || 'Design').slice(0, 100);
}
function _thumbnail() {
  // ≤ 600px on the long side regardless of zoom or canvas size.
  try { return exportDataURL({ format:'jpeg', quality:0.82, multiplier: Math.min(1, 600 / Math.max(canvasW, canvasH)) }); }
  catch (_) { return ''; }   // tainted canvas (image from another site without CORS)
}
async function saveCanvas() {
  const btn = document.getElementById('btnSave');
  if (!btn) { return saveToGallery(); }   // non-admins have no template Save button — Ctrl+S used to crash here
  if (btn.disabled) return;
  btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

  const fd = new FormData();
  fd.append('action',         'save_canvas');
  fd.append('csrf_token',     ED.csrf);
  fd.append('id',             ED.id);
  fd.append('name',           _designName());
  fd.append('canvas_json',    JSON.stringify(canvasJSON()));
  fd.append('thumbnail_data', _thumbnail());
  fd.append('canvas_width',   canvasW);
  fd.append('canvas_height',  canvasH);

  try {
    const r = await idmFetch(ED.api_url, { method:'POST', body:fd }, 30000);
    const d = await r.json();
    if (d.success) { markClean(); showToast('Template saved!'); }
    else showToast('Error: ' + (d.message || 'could not save'), 'err');
  } catch(e) {
    showToast('Network error. Try again.', 'err');
  }

  btn.disabled = false; btn.innerHTML = '<i class="bi bi-floppy"></i> Save';
}

// ── Save to Gallery ─────────────────────────────────────────────────
// A brand-new gallery item saved while offline has no real gallery_id yet
// (the queued write hasn't reached the server) — block re-saving until that
// first save actually syncs, so a second click can't create a duplicate
// design. An UPDATE to an already-saved item (ED.gallery_id already set)
// has no such risk and is never blocked.
let _galSavePending = false, _galSaveQueueId = null;
window.addEventListener('sszone-queue-synced', (e) => {
  if (!e.detail || e.detail.panel !== 'editor' || e.detail.id !== _galSaveQueueId) return;
  _galSavePending = false; _galSaveQueueId = null;
  const result = e.detail.result;
  if (result && result.success && result.gallery_id) { ED.gallery_id = result.gallery_id; markClean(); }
  const btn = document.getElementById('btnSaveGallery');
  if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-collection"></i> <span id="galleryBtnLabel">Update Gallery</span>'; }
});

async function saveToGallery() {
  const btn = document.getElementById('btnSaveGallery');
  if (btn.disabled) return;
  if (_galSavePending && !ED.gallery_id) { showToast('Still syncing your last save — please wait', 'info'); return; }
  btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

  const name = _designName();
  const fd = new FormData();
  fd.append('action',             'save');
  fd.append('csrf_token',         ED.csrf);
  fd.append('gallery_id',         ED.gallery_id  || 0);
  fd.append('source_template_id', ED.src_tpl_id  || 0);
  fd.append('name',               name);
  fd.append('canvas_json',        JSON.stringify(canvasJSON()));
  fd.append('thumbnail_data',     _thumbnail());
  // Current size — ED.width/height were the size at page load, so a resized
  // or cropped design was saved with its old dimensions.
  fd.append('canvas_width',       canvasW);
  fd.append('canvas_height',      canvasH);

  const wasNew = !ED.gallery_id;
  try {
    const d = typeof window.smartFetch === 'function'
      ? await window.smartFetch(ED.gallery_api, fd, { label: 'Save design: ' + name, panel: 'editor', timeoutMs: 30000 })
      : await (await idmFetch(ED.gallery_api, { method:'POST', body:fd }, 30000)).json();
    if (d.queued) {
      if (wasNew) {
        // No real id yet — hold the button disabled until the sszone-queue-synced
        // listener above fills in ED.gallery_id, so a second click can't
        // create a second brand-new gallery row for the same design.
        _galSavePending = true; _galSaveQueueId = d.queueId;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Syncing…';
        showToast(d.message || 'Saved offline — will sync automatically', 'ok');
        return; // stays disabled until the sync event fires
      }
      showToast(d.message || 'Saved offline — will sync automatically', 'ok');
    } else if (d.success) {
      if (d.gallery_id) ED.gallery_id = d.gallery_id;
      markClean();
      showToast(d.message || 'Saved to gallery!', 'ok');
    } else {
      showToast('Error: ' + (d.message || 'could not save'), 'err');
    }
  } catch(e) {
    console.error('saveToGallery fetch error:', e);
    showToast('Network error: ' + e.message, 'err');
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-collection"></i> <span id="galleryBtnLabel">' + (ED.gallery_id ? 'Update Gallery' : 'Save to Gallery') + '</span>';
}

// ── Export / Print ──────────────────────────────────────────────────
/* Export multiplier for ~300 DPI output, capped so the bitmap stays inside the
   browser's canvas limits (a large banner ×3 used to fail with a blank file). */
function _printMultiplier() {
  const dpi  = _ncCurrentDpi || 96;
  let mult = Math.max(1, 300 / dpi);
  if (dpi >= 300) mult = 1;
  const maxSide = 14000, maxArea = 200e6;
  mult = Math.min(mult, maxSide / Math.max(canvasW, canvasH), Math.sqrt(maxArea / (canvasW * canvasH)));
  return Math.max(0.1, Math.round(mult * 100) / 100);
}
function exportPng() {
  canvas.discardActiveObject().renderAll();
  const mult = _printMultiplier();
  let url;
  try { url = exportDataURL({ format:'png', multiplier: mult }); }
  catch (e) { showToast('Export blocked: an image from another website has no download permission. Re-upload it from your device.', 'err'); return; }
  const a = document.createElement('a');
  a.href = url; a.download = _fileBase() + '.png';
  document.body.appendChild(a); a.click(); a.remove();
  showToast('PNG exported — ' + Math.round(canvasW * mult) + '×' + Math.round(canvasH * mult) + ' px');
}

// ── Print — high-res, proper DPI, correct physical size ──────────
function printCanvas() {
  canvas.discardActiveObject().renderAll();
  const dpi    = _ncCurrentDpi || 96;                // design DPI
  let url;
  try { url = exportDataURL({ format:'png', multiplier: _printMultiplier() }); }
  catch (e) { showToast('Print blocked: an image from another website has no download permission.', 'err'); return; }

  // Physical size in inches at original DPI
  const pageW = (canvasW / dpi).toFixed(4) + 'in';
  const pageH = (canvasH / dpi).toFixed(4) + 'in';

  const w = window.open('', '_blank');
  if (!w) { showToast('Pop-up blocked — allow pop-ups for printing', 'err'); return; }
  w.document.write(`<!DOCTYPE html><html><head><title>Print — ${escHtml(_designName())}</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
@page{margin:0;size:${pageW} ${pageH};}
html,body{width:${pageW};height:${pageH};overflow:hidden;}
img{display:block;width:100%;height:100%;object-fit:contain;}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
</style></head>
<body><img src="${url}" id="pi">
<script>
var img=document.getElementById('pi');
img.onload=function(){window.focus();setTimeout(function(){window.print();setTimeout(function(){window.close();},800);},400);};
img.onerror=function(){document.body.innerHTML='<p style="color:red;padding:20px">Image load failed</p>';};
<\/script></body></html>`);
  w.document.close();
}

// ── Export PDF — world-class resolution ──────────────────────────
function exportPDF() {
  if (typeof window.jspdf === 'undefined' && typeof window.jsPDF === 'undefined') {
    showToast('jsPDF loading — try again in a moment', 'err'); return;
  }
  canvas.discardActiveObject().renderAll();
  const dpi  = _ncCurrentDpi || 96;
  showToast('Generating PDF…');
  setTimeout(() => {
    try {
      const url = exportDataURL({ format:'png', multiplier: _printMultiplier() });
      const { jsPDF } = window.jspdf || window;
      const wMm = (canvasW / dpi) * 25.4; // convert px → mm
      const hMm = (canvasH / dpi) * 25.4;
      const ori = wMm >= hMm ? 'l' : 'p';
      const doc = new jsPDF({ orientation: ori, unit: 'mm', format: [wMm, hMm], compress: true });
      const img = new Image();
      img.onload = () => {
        // Draw image filling the entire PDF page
        doc.addImage(img, 'PNG', 0, 0, wMm, hMm, '', 'FAST');
        doc.save(_fileBase() + '.pdf');
        showToast('PDF downloaded! (' + wMm.toFixed(1) + '×' + hMm.toFixed(1) + 'mm)');
      };
      img.onerror = () => showToast('PDF error: could not render the design', 'err');
      img.src = url;
    } catch(ex) {
      showToast('PDF error: ' + ex.message, 'err');
    }
  }, 50);
}

// ── Toast ────────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, cls = '') {
  const t = document.getElementById('edToast');
  t.textContent = msg;
  t.className = 'ed-toast show' + (cls ? ' ' + cls : '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), cls === 'err' ? 4500 : 3000);
}

// ── In-app confirm/alert dialogs (replace native confirm()/alert() — better on mobile) ──
let _edConfirmYes = null, _edConfirmNo = null;
function edConfirm(message, onYes, onNo) {
  document.getElementById('edConfirmMsg').textContent = message;
  document.getElementById('edConfirmOv').style.display = 'flex';
  _edConfirmYes = onYes || null;
  _edConfirmNo  = onNo  || null;
}
function _edConfirmAnswer(yes) {
  document.getElementById('edConfirmOv').style.display = 'none';
  const y = _edConfirmYes, n = _edConfirmNo;
  _edConfirmYes = null; _edConfirmNo = null;
  if (yes && y) y();
  if (!yes && n) n();
}
/** lines: plain strings (escaped) — pass {html:'…'} for a trusted formatted line. */
function edAlertList(title, lines) {
  document.getElementById('edListTitle').textContent = title;
  document.getElementById('edListBody').innerHTML = lines.map(l => `<div style="padding:.3rem 0;border-bottom:1px solid #334155;">${typeof l === 'object' && l && l.html ? l.html : escHtml(l)}</div>`).join('');
  document.getElementById('edListOv').style.display = 'flex';
}

let _edPromptCb=null, _edPromptFields=null;
function edPromptModal(title, fields, onOk) {
  document.getElementById('edPromptTitle').textContent = title;
  _edPromptFields = fields;
  // Values/labels are escaped: a name containing a quote used to break the field.
  document.getElementById('edPromptBody').innerHTML = fields.map(f => {
    const v = f.value !== undefined && f.value !== null ? f.value : '';
    const inputHtml = f.type === 'textarea'
      ? `<textarea id="_edPf_${f.id}" rows="${f.rows||4}" style="width:100%;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:.85rem;padding:.5rem .65rem;outline:none;resize:vertical;font-family:inherit;">${escHtml(v)}</textarea>`
      : `<input type="${f.type==='number'?'number':'text'}" id="_edPf_${f.id}" value="${escHtml(v)}" style="width:100%;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:.85rem;padding:.5rem .65rem;outline:none;" onkeydown="if(event.key==='Enter'){event.preventDefault();_edPromptAnswer(true);}">`;
    return `<label for="_edPf_${f.id}" style="font-size:.78rem;color:#94a3b8;display:block;margin:.5rem 0 .3rem;font-weight:600;">${escHtml(f.label)}</label>${inputHtml}`;
  }).join('');
  document.getElementById('edPromptOv').style.display = 'flex';
  setTimeout(() => { const first = document.getElementById('_edPf_'+fields[0].id); if (first) { first.focus(); first.select && first.select(); } }, 50);
  _edPromptCb = onOk;
}
function _edPromptAnswer(ok) {
  document.getElementById('edPromptOv').style.display = 'none';
  const cb = _edPromptCb, fields = _edPromptFields;
  _edPromptCb = null; _edPromptFields = null;
  if (ok && cb && fields) {
    const vals = {};
    fields.forEach(f => { const el = document.getElementById('_edPf_'+f.id); vals[f.id] = el ? el.value : ''; });
    cb(vals);
  }
}

// ── Keyboard shortcuts ────────────────────────────────────────────────
const _EDITOR_MODALS = ['edPromptOv','edConfirmOv','edListOv','warpModal','studio3DModal','urlModal','stickerModal','newCanvasModal','resizeModal','curvedTextModal','mockupModal','lspIframeModal'];
function _openModalId() { return _EDITOR_MODALS.find(id => { const el = document.getElementById(id); return el && el.style.display && el.style.display !== 'none'; }); }
function _closeModal(id) {
  if (id === 'edPromptOv') return _edPromptAnswer(false);
  if (id === 'edConfirmOv') return _edConfirmAnswer(false);
  if (id === 'warpModal') return closeWarpModal();
  if (id === 'studio3DModal') return close3DStudio();
  if (id === 'lspIframeModal') return closeLspModal();
  if (id === 'mockupModal') return closeMockupModal();
  if (id === 'newCanvasModal') return closeNewCanvasModal();
  document.getElementById(id).style.display = 'none';
}
let _nudgeT = null;
document.addEventListener('keydown', e => {
  // While a dialog is open: Escape closes it, Enter confirms a confirm-box,
  // and no shortcut leaks through to the canvas (Delete used to delete the
  // selected object behind an open dialog).
  const modal = _openModalId();
  if (modal) {
    if (e.key === 'Escape') { e.preventDefault(); _closeModal(modal); }
    else if (e.key === 'Enter' && modal === 'edConfirmOv') { e.preventDefault(); _edConfirmAnswer(true); }
    return;
  }
  const tag      = document.activeElement.tagName;
  const editing  = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || document.activeElement.isContentEditable;
  const textEditing = !!canvas.getActiveObject()?.isEditing;
  if (editing || textEditing) return;

  // While drawing: [ ] change brush size, Escape leaves draw mode, Ctrl+Z still undoes.
  if (canvas.isDrawingMode || isEraserMode) {
    if (e.key === '[' || e.key === ']') {
      e.preventDefault();
      const bs = document.getElementById('brushSize');
      bs.value = e.key === '[' ? Math.max(1, +bs.value - 2) : Math.min(80, +bs.value + 2);
      document.getElementById('brushSizeVal').textContent = bs.value; updateBrush(); return;
    }
    if (e.key === 'Escape') { e.preventDefault(); setActiveTool('select'); closeFlyouts(); showToast('Back to Select tool', 'info'); return; }
    if (!(e.ctrlKey || e.metaKey)) return;
  }

  // Single-key shortcuts (no modifiers)
  if (!e.ctrlKey && !e.metaKey && !e.altKey) {
    const k = e.key.toLowerCase();
    if (k === 'v') { e.preventDefault(); setActiveTool('select'); return; }
    if (k === 't') { e.preventDefault(); addText(); return; }
    if (k === 'r') { e.preventDefault(); addShape('rect'); return; }
    if (k === 'c') { e.preventDefault(); addShape('circle'); return; }
    if (k === 'l') { e.preventDefault(); addShape('line'); return; }
    if (k === 'p') { e.preventDefault(); activateBrush('pencil'); return; }
    if (k === 'e') { e.preventDefault(); toggleEraserMode(); return; }
    if (k === 'i') { e.preventDefault(); document.getElementById('imgUpload').click(); return; }
    if (k === 'u') { e.preventDefault(); openUrlModal(); return; }
    if (k === 'b') { e.preventDefault(); toggleFlyout('brushFlyout', document.getElementById('tDraw')); return; }
    if (k === 's') { e.preventDefault(); toggleFlyout('shapesFlyout', document.getElementById('tShapes')); return; }
    if (k === 'm') { e.preventDefault(); openStickerModal(); return; }
    if (k === 'g') { e.preventDefault(); toggleGrid(); return; }
    if (k === 'h') { e.preventDefault(); toggleSnapGuides(); return; }
  }

  // Ctrl / Cmd shortcuts
  if (e.ctrlKey || e.metaKey) {
    const k = e.key.toLowerCase();
    if (k === 'z' && !e.shiftKey) { e.preventDefault(); histUndo(); return; }
    if ((k === 'z' && e.shiftKey) || k === 'y') { e.preventDefault(); histRedo(); return; }
    if (k === 's') { e.preventDefault(); ED.is_admin && !ED.from_gallery && document.getElementById('btnSave') ? saveCanvas() : saveToGallery(); return; }
    if (k === 'd') { e.preventDefault(); dupSelected(); return; }
    if (k === 'c') { if (canvas.getActiveObject()) { e.preventDefault(); copySelected(); } return; }
    if (k === 'x') { if (canvas.getActiveObject()) { e.preventDefault(); cutSelected(); } return; }
    // Ctrl+V: our own clipboard first; otherwise let the browser fire 'paste'
    // so an image copied from elsewhere can be pasted in.
    if (k === 'v') { if (clipboard) { e.preventDefault(); pasteSelected(); } return; }
    if (k === 'g' && !e.shiftKey) { e.preventDefault(); groupSelected(); return; }
    if (k === 'g' &&  e.shiftKey) { e.preventDefault(); ungroupSelected(); return; }
    if (k === 'a') {
      e.preventDefault();
      canvas.discardActiveObject();
      const objs = canvas.getObjects().filter(o => o.selectable !== false && o.visible !== false && !o.data?.isCropOverlay);
      if (!objs.length) return;
      canvas.setActiveObject(new fabric.ActiveSelection(objs, { canvas })); canvas.renderAll(); return;
    }
    if (k === ']') { e.preventDefault(); const o = canvas.getActiveObject(); if (o) { e.shiftKey ? canvas.bringToFront(o) : canvas.bringForward(o); canvas.renderAll(); histSave(); refreshLayers(); } return; }
    if (k === '[') { e.preventDefault(); const o = canvas.getActiveObject(); if (o) { e.shiftKey ? canvas.sendToBack(o) : canvas.sendBackwards(o); canvas.renderAll(); histSave(); refreshLayers(); } return; }
    if (k === 'p') { e.preventDefault(); printCanvas(); return; }
    if (k === 'e') { e.preventDefault(); exportPng(); return; }
    if (e.key === '0') { e.preventDefault(); setZoom('fit'); return; }
    if (e.key === '1') { e.preventDefault(); setZoom(1); return; }
    if (e.key === '+' || e.key === '=') { e.preventDefault(); setZoom(Math.min(4, canvas.getZoom() * 1.2)); return; }
    if (e.key === '-') { e.preventDefault(); setZoom(Math.max(0.05, canvas.getZoom() / 1.2)); return; }
    return;
  }

  if (e.key === 'Delete' || e.key === 'Backspace') { if (canvas.getActiveObject()) { e.preventDefault(); deleteSelected(); } return; }
  if (e.key === 'Escape') {
    e.preventDefault();
    if (isCropping) { toggleCropMode(); return; }
    if (_gradDragMode) { _exitGradDrag(); return; }
    if (eyedropperActive) { toggleEyedropper(); return; }
    closeFlyouts(); canvas.discardActiveObject().renderAll(); return;
  }

  // Arrow nudge (Shift = 10px, default = 1px) — one undo step per burst
  const obj = canvas.getActiveObject();
  if (obj && ['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key)) {
    e.preventDefault();
    if (obj.lockMovementX) return;
    const step = e.shiftKey ? 10 : 1;
    if (e.key === 'ArrowLeft')  obj.left -= step;
    if (e.key === 'ArrowRight') obj.left += step;
    if (e.key === 'ArrowUp')    obj.top  -= step;
    if (e.key === 'ArrowDown')  obj.top  += step;
    obj.setCoords(); canvas.renderAll(); refreshProps(obj);
    clearTimeout(_nudgeT); _nudgeT = setTimeout(histSave, 400);
  }
});

// ── Utilities ────────────────────────────────────────────────────────
function colorToHex(c) {
  // Guard against non-string fills (a fabric.Pattern/Gradient object, e.g. the
  // gradient tool's own Pattern fill) — calling .startsWith on one of those
  // used to throw and silently abort the REST of refreshProps() whenever a
  // gradient-filled object was (re)selected, breaking the whole Properties
  // panel sync. This was a real, confirmed root cause of "gradient/colour
  // not applying properly" reports.
  if (!c || typeof c !== 'string' || c === 'transparent') return '#000000';
  if (c.startsWith('#')) return c.length === 4
    ? '#' + c.slice(1).split('').map(x => x+x).join('') : c;
  const m = c.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/);
  if (!m) return '#000000';
  return '#' + [m[1],m[2],m[3]].map(v => (+v).toString(16).padStart(2,'0')).join('');
}

// ── Extended Shapes ─────────────────────────────────────────────────
function starPoints(cx, cy, outerR, innerR, pts) {
  const arr = [];
  for (let i = 0; i < pts * 2; i++) {
    const ang = (i * Math.PI / pts) - Math.PI / 2;
    const r = i % 2 === 0 ? outerR : innerR;
    arr.push({ x: cx + r * Math.cos(ang), y: cy + r * Math.sin(ang) });
  }
  return arr;
}


// ═══════════════════════════════════════════════════════════════════════
// BRUSH / DRAW / ERASER TOOLS — Complete Professional Implementation
// ═══════════════════════════════════════════════════════════════════════

let activeTool   = 'select';
let curBrushType = 'pencil';
let isEraserMode = false;
let eraserMode   = 'background'; // 'background' | 'pixel' | 'object'

// ── Activate Brush (Normal Drawing) ───────────────────────────────────
function activateBrush(type) {
  // Clear any previous eraser state
  _clearEraserState();

  activeTool   = type;
  curBrushType = type;
  canvas.isDrawingMode = true;

  // UI: mark brush type active in flyout
  ['bTypePencil','bTypeSpray','bTypeCircle','bTypeEraser'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
  });

  if (type === 'eraser') {
    // ===== ERASER MODE =====
    isEraserMode = true;
    document.body.classList.add('eraser-mode');
    const tEr = document.getElementById('tEraser');
    if (tEr) tEr.classList.add('active');
    const bEr = document.getElementById('bTypeEraser');
    if (bEr) bEr.classList.add('active');

    // Show eraser options, hide color picker
    const eraserRow = document.getElementById('eraserModeRow');
    const colorRow  = document.getElementById('brushColorRow');
    if (eraserRow) eraserRow.style.display = '';
    if (colorRow)  colorRow.style.display  = 'none';

    // Apply current eraser mode
    setEraserMode(eraserMode);

    showToast('Eraser ON — Draw on canvas to erase', 'info');
    return;
  }

  // ===== NORMAL BRUSH MODE =====
  const m = {pencil:'bTypePencil', spray:'bTypeSpray', circle:'bTypeCircle'};
  const el = document.getElementById(m[type]);
  if (el) el.classList.add('active');

  // Create brush
  if (type === 'spray')       canvas.freeDrawingBrush = new fabric.SprayBrush(canvas);
  else if (type === 'circle') canvas.freeDrawingBrush = new fabric.CircleBrush(canvas);
  else                        canvas.freeDrawingBrush = new fabric.PencilBrush(canvas);

  updateBrush();
}

// ── Set Brush Type (for normal drawing only) ──────────────────────────
function setBrushType(type) {
  // Never call this for eraser — eraser has its own setup
  if (type === 'eraser') return;

  _clearEraserState();
  curBrushType = type;
  canvas.isDrawingMode = true;

  ['bTypePencil','bTypeSpray','bTypeCircle','bTypeEraser'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
  });

  const m = {pencil:'bTypePencil', spray:'bTypeSpray', circle:'bTypeCircle'};
  const el = document.getElementById(m[type]);
  if (el) el.classList.add('active');

  if (type === 'spray')       canvas.freeDrawingBrush = new fabric.SprayBrush(canvas);
  else if (type === 'circle') canvas.freeDrawingBrush = new fabric.CircleBrush(canvas);
  else                        canvas.freeDrawingBrush = new fabric.PencilBrush(canvas);

  updateBrush();
}

// ── Update Normal Brush ───────────────────────────────────────────────
function updateBrush() {
  if (isEraserMode) { updateEraserBrush(); return; }
  if (!canvas.freeDrawingBrush) return;

  const color   = document.getElementById('brushColor').value;
  const size    = +document.getElementById('brushSize').value;
  const opacity = +document.getElementById('brushOpacity').value / 100;

  const r = parseInt(color.slice(1,3),16);
  const g = parseInt(color.slice(3,5),16);
  const b = parseInt(color.slice(5,7),16);

  canvas.freeDrawingBrush.color = `rgba(${r},${g},${b},${opacity})`;
  canvas.freeDrawingBrush.width = size;
}

// ── Update Eraser Brush ─────────────────────────────────────────────
function updateEraserBrush() {
  const size = +document.getElementById('brushSize').value || 20;

  if (eraserMode === 'object') {
    // Object mode: NO drawing, just click to delete
    canvas.isDrawingMode = false;
    return;
  }

  // Background & Pixel mode: use PencilBrush for erasing
  canvas.isDrawingMode = true;
  if (!canvas.freeDrawingBrush || !(canvas.freeDrawingBrush instanceof fabric.PencilBrush)) {
    canvas.freeDrawingBrush = new fabric.PencilBrush(canvas);
  }
  canvas.freeDrawingBrush.width = size;

  if (eraserMode === 'pixel') {
    // TRUE ERASE: the stroke becomes destination-out when finished (path:created).
    // Shown semi-transparent while drawing so you can see where you erase.
    canvas.freeDrawingBrush.color = 'rgba(148,163,184,0.6)';
  } else {
    // BACKGROUND mode: paint with canvas background color. On a transparent
    // background there's no colour to paint, so it used to draw nothing.
    const bg = canvas.backgroundColor;
    if (!bg || bg === 'transparent' || typeof bg !== 'string') {
      setEraserMode('pixel');
      showToast('Background is transparent — switched to Pixel eraser', 'info');
      return;
    }
    canvas.freeDrawingBrush.color = bg;
  }
}

// ── Set Eraser Mode (Background / Pixel / Object) ───────────────────
function setEraserMode(mode) {
  eraserMode = mode;

  // Update UI buttons
  ['emBtnBg','emBtnPx','emBtnObj'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
  });
  const map = {background:'emBtnBg', pixel:'emBtnPx', object:'emBtnObj'};
  const btn = document.getElementById(map[mode]);
  if (btn) btn.classList.add('active');

  // Update description text
  const desc = document.getElementById('eraserModeDesc');
  if (desc) {
    desc.textContent = mode === 'pixel'
      ? 'True transparency — background must be transparent/OFF'
      : mode === 'object'
      ? 'Click any object to delete it instantly'
      : 'Paints canvas background color — fast & safe';
  }

  // Remove old object eraser handler
  canvas.off('mouse:down', _objectEraserMouseDown);

  if (mode === 'object') {
    // Object mode: disable drawing, enable click-to-delete
    canvas.isDrawingMode = false;
    canvas.on('mouse:down', _objectEraserMouseDown);
  } else {
    // Background/Pixel mode: enable drawing with eraser brush
    updateEraserBrush();
  }
}

// ── Object Eraser Click Handler ─────────────────────────────────────
function _objectEraserMouseDown(opt) {
  if (!isEraserMode || eraserMode !== 'object') return;
  const target = opt.target;
  // Don't delete guides, grids, or crop overlays
  if (!target || target.data?.grid || target.data?.isSnapGuide || target.data?.printGuide || target.data?.isCropOverlay) return;
  hist.locked = true;
  canvas.discardActiveObject();
  canvas.remove(target);
  hist.locked = false;
  canvas.renderAll();
  histSave(); refreshLayers();
  showToast('Object erased', 'info');
}

// ── Toggle Eraser Mode from Toolbar Button ──────────────────────────
function toggleEraserMode() {
  if (isEraserMode) {
    // Turn OFF eraser
    setActiveTool('select');
    showToast('Eraser mode OFF', 'info');
  } else {
    // Turn ON eraser
    activateBrush('eraser');
  }
}

// ── Clear Eraser State (helper) ─────────────────────────────────────
function _clearEraserState() {
  isEraserMode = false;
  document.body.classList.remove('eraser-mode');
  canvas.off('mouse:down', _objectEraserMouseDown);

  const tEr = document.getElementById('tEraser');
  if (tEr) tEr.classList.remove('active');

  const eraserRow = document.getElementById('eraserModeRow');
  const colorRow  = document.getElementById('brushColorRow');
  if (eraserRow) eraserRow.style.display = 'none';
  if (colorRow)  colorRow.style.display  = '';
}

// ── Erase Selected (old behavior — delete selected objects) ─────────
function eraseSelected() {
  const objs = canvas.getActiveObjects();
  if (objs.length > 0) {
    canvas.discardActiveObject();
    objs.forEach(o => canvas.remove(o));
    canvas.renderAll(); histSave();
    showToast(objs.length > 1 ? objs.length + ' items deleted' : 'Item deleted');
    return;
  }
  // If nothing selected, toggle eraser mode
  toggleEraserMode();
}

// ── Set Active Tool (Select mode) ───────────────────────────────────
function setActiveTool(name) {
  activeTool = name;
  canvas.isDrawingMode = false;

  // Close flyouts
  const bf = document.getElementById('brushFlyout');
  if (bf) bf.classList.remove('open');

  // Clear eraser
  _clearEraserState();

  // UI reset
  ['tTool','tEraser','tEye'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
  });

  if (name === 'select') {
    const tTool = document.getElementById('tTool');
    if (tTool) tTool.classList.add('active');
  }

  // Exit eyedropper
  eyedropperActive = false;
  canvas.skipTargetFind = false;
  document.body.classList.remove('eyedrop-mode');
  const tEye = document.getElementById('tEye');
  if (tEye) tEye.classList.remove('active');
}

// ── Handle path:created for Pixel Eraser ────────────────────────────
// When user draws with eraser in pixel mode, apply destination-out
canvas.on('path:created', function(e) {
  const path = e.path;
  if (!path) return;
  if (!isEraserMode) { path.data = Object.assign({}, path.data, { name: 'Drawing' }); refreshLayers(); return; }

  // The stroke was already recorded by object:added — replace that history
  // step with the finished eraser stroke instead of adding a second one.
  if (hist.idx > 0) { hist.states.splice(hist.idx, 1); hist.labels.splice(hist.idx, 1); hist.times.splice(hist.idx, 1); hist.idx--; }
  if (eraserMode === 'pixel') {
    // TRUE TRANSPARENCY: destination-out makes pixels transparent
    path.set({
      globalCompositeOperation: 'destination-out',
      stroke: 'rgba(0,0,0,1)',
      selectable: false,
      evented: false,
      data: { eraserPath: true }
    });
  } else if (eraserMode === 'background') {
    // BACKGROUND COLOR: just a normal stroke with bg color
    path.set({
      selectable: false,
      evented: false,
      data: { eraserPath: true }
    });
  }
  canvas.renderAll();
  histSave(); refreshLayers();
});

// ── Transparent Background Toggle ───────────────────────────────────
function toggleTransparentBg() {
  const btn = document.getElementById('btnTransBg');
  const hasBg = canvas.backgroundColor && canvas.backgroundColor !== '' && canvas.backgroundColor !== 'transparent';

  if (hasBg) {
    // Remove background → transparent
    canvas.backgroundColor = '';
    if (btn) { btn.style.background = '#4f46e5'; btn.style.color = '#fff'; }
    showToast('Background transparent — Pixel Eraser will now erase to transparency!', 'info');
  } else {
    // Add white background
    canvas.backgroundColor = '#ffffff';
    document.getElementById('pCanvasBg').value = '#ffffff';
    canvas.getObjects().forEach(o => { if (o.data?.eraserPath && o.globalCompositeOperation !== 'destination-out') o.set('stroke', '#ffffff'); });
    if (btn) { btn.style.background = '#334155'; btn.style.color = '#e2e8f0'; }
    showToast('Background set to white');
  }
  canvas.renderAll();
  histSave();
}

// ── Eraser Cursor Follow ──────────────────────────────────────────────
(function() {
  const cur = document.getElementById('eraserCursor');
  if (!cur) return;
  function followTo(clientX, clientY) {
    cur.style.left = clientX + 'px';
    cur.style.top  = clientY + 'px';
    const sz = +document.getElementById('brushSize').value || 20;
    cur.style.width  = sz + 'px';
    cur.style.height = sz + 'px';
  }
  document.addEventListener('mousemove', e => { if (document.body.classList.contains('eraser-mode')) followTo(e.clientX, e.clientY); });
  document.addEventListener('touchmove', e => {
    if (!document.body.classList.contains('eraser-mode') || !e.touches.length) return;
    followTo(e.touches[0].clientX, e.touches[0].clientY);
  }, { passive: true });
})();

// ── Flyout management ─────────────────────────────────────────────
function toggleFlyout(id, btn) {
  const flyout = document.getElementById(id);
  const isOpen = flyout.classList.contains('open');
  closeFlyouts();
  if (!isOpen) {
    const rect = btn.getBoundingClientRect();
    flyout.style.top  = rect.top + 'px';
    flyout.style.left = (rect.right + 6) + 'px';
    flyout.classList.add('open');
    btn.classList.add('active');
    flyout._openBtn = btn;
  }
}
function closeFlyouts() {
  document.querySelectorAll('.tool-flyout.open').forEach(f => {
    f.classList.remove('open');
    if (f._openBtn) { f._openBtn.classList.remove('active'); f._openBtn = null; }
  });
}
document.addEventListener('click', e => {
  if (!e.target.closest('.tool-flyout') && !e.target.closest('.tool-btn.has-drop'))
    closeFlyouts();
});

// ── Text Warp Studio ───────────────────────────────────────────────
let selectedWarpType  = 'arc-up';
let warpTargetObj     = null;
let warpPreviewCanvas = null;
let warpPreviewCtx    = null;
let warpDebounceTimer = null;

function openWarpModal() {
  const obj = canvas.getActiveObject();
  // Selecting an already-warped text and pressing Warp again re-opens it for editing.
  if (obj && obj.data?.warpedFrom && obj.type === 'image') { reEditWarp(); return; }
  if (!obj || !isTextObj(obj)) {
    showToast('Select a text object first', 'err'); return;
  }
  warpTargetObj = obj;
  closeFlyouts();
  closeEdPanel();
  const modal = document.getElementById('warpModal');
  modal.style.display = 'flex';
  warpPreviewCanvas = document.getElementById('warpPreviewCanvas');
  warpPreviewCtx    = warpPreviewCanvas.getContext('2d');
  // Reset sliders
  document.getElementById('warpAmount').value  = obj.data?.warpAmount  ?? 50;
  document.getElementById('warpSpread').value  = obj.data?.warpSpread  ?? 100;
  document.getElementById('warpSpacing').value = obj.data?.warpSpacing ?? 0;
  document.getElementById('warpDepth').value   = obj.data?.warpDepth   ?? 0;
  document.getElementById('warpSmooth').checked        = true;
  document.getElementById('warpReverse').checked       = false;
  document.getElementById('warpPreserveSize').checked  = true;
  if (obj.data?.warpType) {
    document.querySelectorAll('.warp-card').forEach(c => {
      c.classList.toggle('sel', c.dataset.warp === obj.data.warpType);
    });
    selectedWarpType = obj.data.warpType;
  }
  onWarpSliderChange();
}

function closeWarpModal() {
  document.getElementById('warpModal').style.display = 'none';
  warpTargetObj = null;
  clearTimeout(warpDebounceTimer);
}

function selectWarpType(el) {
  document.querySelectorAll('.warp-card').forEach(c => c.classList.remove('sel'));
  el.classList.add('sel');
  selectedWarpType = el.dataset.warp;
  onWarpSliderChange();
}

function onWarpSliderChange() {
  document.getElementById('warpAmtVal').textContent     = document.getElementById('warpAmount').value + '%';
  document.getElementById('warpSpreadVal').textContent  = document.getElementById('warpSpread').value + '%';
  document.getElementById('warpSpacingVal').textContent = document.getElementById('warpSpacing').value;
  document.getElementById('warpDepthVal').textContent   = document.getElementById('warpDepth').value;
  clearTimeout(warpDebounceTimer);
  warpDebounceTimer = setTimeout(updateWarpPreview, 80);
}

// Returns {x,y} only — no angle. Used by getWarpPosition for numerical tangent.
function _warpXY(t, type, W, H, amount) {
  const amt = amount / 100;
  const mid = H / 2;
  const A   = H * amt * 0.65; // amplitude scaled to 65% of canvas height × intensity

  switch (type) {
    case 'arc-up':
      return { x: t*W, y: mid - Math.sin(t * Math.PI) * A };

    case 'arc-down':
      return { x: t*W, y: mid + Math.sin(t * Math.PI) * A };

    case 'wave':
      return { x: t*W, y: mid + Math.sin(t * Math.PI * 2) * A * 0.65 };

    case 'flag':
      // Diagonal drift + gentle wave
      return { x: t*W, y: mid + (t - 0.5) * A * 0.8 + Math.sin(t * Math.PI) * A * 0.35 };

    case 'inflate':
      // Smooth peak in the centre, flat at ends (wider bell)
      return { x: t*W, y: mid - Math.pow(Math.sin(t * Math.PI), 0.7) * A * 0.9 };

    case 'deflate':
      return { x: t*W, y: mid + Math.pow(Math.sin(t * Math.PI), 0.7) * A * 0.9 };

    case 'bulge':
      // Upward bow, slightly flatter than arc-up (barrel lens top)
      return { x: t*W, y: mid - Math.sin(t * Math.PI) * A * 0.75 * (1 + Math.sin(t*Math.PI)*0.3) };

    case 'pinch': {
      // V-shape: edges up, centre down
      const d = 2*t - 1; // −1..1
      return { x: t*W, y: mid + (d*d) * A - A * 0.2 };
    }

    case 'circle': {
      // True circular arc spanning full width
      const sweep = Math.PI * (0.5 + amt * 1.4);
      const r     = W / (2 * Math.sin(sweep / 2) + 0.001);
      const startA = -Math.PI / 2 - sweep / 2;
      const angle  = startA + t * sweep;
      const cx = W / 2, cy = r * 0.95;
      return { x: cx + r * Math.cos(angle), y: cy + r * Math.sin(angle) };
    }

    case 'arch':
      // Strong arch — high peak, anchored base
      return { x: t*W, y: H * 0.8 - Math.sin(t * Math.PI) * A * 1.15 };

    case 'bridge': {
      // Parabolic arch anchored at both ends
      const d = 2*t - 1; // −1..1
      return { x: t*W, y: mid - (1 - d*d) * A };
    }

    case 'sine':
      // 1.5 full sine cycles
      return { x: t*W, y: mid + Math.sin(t * Math.PI * 3) * A * 0.5 };

    case 'zigzag': {
      // Smooth triangle wave (4 peaks)
      const n     = 4;
      const phase = (t * n) % 1;
      const tri   = 2 * Math.abs(2 * phase - 1) - 1; // −1..1 triangle
      return { x: t*W, y: mid + tri * A * 0.55 };
    }

    case 'stairs': {
      const steps = 5;
      const step  = Math.min(Math.floor(t * steps), steps - 1);
      return { x: t*W, y: H * 0.15 + step * (H * 0.7 / (steps - 1)) };
    }

    case 'spiral': {
      // Progressive spiral — text orbits outward
      const turns = 1.2 + amt * 1.5;
      const angle = t * Math.PI * 2 * turns;
      const r     = A * 0.35 * t;
      return { x: t*W + r * Math.cos(angle) * 0.25, y: mid + r * Math.sin(angle) };
    }

    case 'fisheye': {
      // Hourglass / lens: centre dips, edges rise
      const d = 2*t - 1; // −1..1
      return { x: t*W, y: mid + (1 - 2*d*d) * A * 0.65 };
    }

    default:
      return { x: t*W, y: mid };
  }
}

// Returns {x, y, a} — angle computed via numerical differentiation for exact tangent on every curve.
function getWarpPosition(t, type, W, H, amount, reverse, smooth) {
  if (reverse) t = 1 - t;
  // EaseInOut applied to t affects character clustering along the path (smoother at endpoints)
  const te = smooth ? (t < 0.5 ? 2*t*t : 1 - Math.pow(-2*t+2, 2)/2) : t;

  const pos = _warpXY(te, type, W, H, amount);

  // Numerical tangent — works perfectly for all 16 types, no manual derivative needed
  const dt = 0.002;
  const p1 = _warpXY(Math.max(0, te - dt), type, W, H, amount);
  const p2 = _warpXY(Math.min(1, te + dt), type, W, H, amount);
  const angle = Math.atan2(p2.y - p1.y, p2.x - p1.x);

  return { x: pos.x, y: pos.y, a: angle };
}

function updateWarpPreview() {
  if (!warpPreviewCtx || !warpTargetObj) return;
  const obj    = warpTargetObj;
  const PC     = warpPreviewCanvas;
  const ctx    = warpPreviewCtx;
  const text   = obj.text || 'Text';
  const fs     = Math.min(obj.fontSize || 28, 48);
  const fill   = (typeof obj.fill === 'string') ? obj.fill : '#e2e8f0';
  const family = obj.fontFamily || 'Noto Sans';
  const fw     = obj.fontWeight || 'normal';
  const fi     = obj.fontStyle  || 'normal';
  const stroke = obj.stroke && obj.stroke !== 'transparent' ? obj.stroke : null;
  const sw     = obj.strokeWidth || 0;
  const amount = +document.getElementById('warpAmount').value;
  const spread = +document.getElementById('warpSpread').value / 100;
  const spacing= +document.getElementById('warpSpacing').value;
  const depth  = +document.getElementById('warpDepth').value;
  const smooth = document.getElementById('warpSmooth').checked;
  const reverse= document.getElementById('warpReverse').checked;

  ctx.clearRect(0, 0, PC.width, PC.height);
  ctx.fillStyle = '#1e293b';
  ctx.fillRect(0, 0, PC.width, PC.height);

  const W = PC.width, H = PC.height;
  const pad = fs * 1.2;
  const tw = W - pad*2;
  const th = H - pad*2;

  ctx.font = `${fi} ${fw} ${fs}px "${family}", sans-serif`;
  ctx.textBaseline = 'middle';

  const chars  = [...text];
  const sp     = fs * 0.04 + spacing;
  const widths = chars.map(c => ctx.measureText(c).width * spread + sp);
  const total  = widths.reduce((a,b) => a+b, 0);
  let   xOff   = (tw - total) / 2;

  drawWarpPathGuide(ctx, W, H, pad, amount, smooth, reverse);

  chars.forEach((ch, i) => {
    const cx = xOff + widths[i]/2 - sp/2;
    const t  = Math.min(Math.max(cx / tw, 0.001), 0.999);
    const pt = getWarpPosition(t, selectedWarpType, tw, th, amount, reverse, smooth);
    ctx.save();
    ctx.translate(pad + pt.x, pad + pt.y);
    ctx.rotate(pt.a);
    if (depth > 0) {
      ctx.shadowColor = 'rgba(0,0,0,.6)';
      ctx.shadowOffsetX = depth * .4;
      ctx.shadowOffsetY = depth * .6;
      ctx.shadowBlur = depth * .5;
    }
    ctx.fillStyle = fill;
    if (stroke && sw > 0) {
      ctx.strokeStyle = stroke; ctx.lineWidth = sw * spread;
      ctx.lineJoin = 'round'; ctx.strokeText(ch, 0, 0);
    }
    ctx.fillText(ch, 0, 0);
    ctx.restore();
    xOff += widths[i];
  });
}

function drawWarpPathGuide(ctx, W, H, pad, amount, smooth, reverse) {
  const tw = W - pad*2, th = H - pad*2;
  ctx.save();
  ctx.strokeStyle = 'rgba(99,102,241,.25)';
  ctx.lineWidth = 1;
  ctx.setLineDash([4,4]);
  ctx.beginPath();
  for (let i = 0; i <= 60; i++) {
    const t  = i / 60;
    const pt = getWarpPosition(t, selectedWarpType, tw, th, amount, reverse, smooth);
    i === 0 ? ctx.moveTo(pad + pt.x, pad + pt.y) : ctx.lineTo(pad + pt.x, pad + pt.y);
  }
  ctx.stroke();
  ctx.restore();
}

function applyTextWarp() {
  const obj = warpTargetObj || canvas.getActiveObject();
  if (!obj || !isTextObj(obj)) {
    showToast('Select a warped text object first', 'err'); return;
  }
  const text   = obj.text || 'Text';
  const fs     = obj.fontSize || 28;
  const fill   = (typeof obj.fill === 'string') ? obj.fill : '#1f2937';
  const family = obj.fontFamily || 'Noto Sans';
  const fw     = obj.fontWeight || 'normal';
  const fi     = obj.fontStyle  || 'normal';
  const stroke = obj.stroke && obj.stroke !== 'transparent' ? obj.stroke : null;
  const sw     = obj.strokeWidth || 0;
  const amount = +document.getElementById('warpAmount').value;
  const spread = +document.getElementById('warpSpread').value / 100;
  const spacing= +document.getElementById('warpSpacing').value;
  const depth  = +document.getElementById('warpDepth').value;
  const smooth = document.getElementById('warpSmooth').checked;
  const reverse= document.getElementById('warpReverse').checked;
  const keepSz = document.getElementById('warpPreserveSize').checked;

  const scale = 3;
  const font  = `${fi} ${fw} ${fs}px "${family}", sans-serif`;
  // Measure FIRST and size the work canvas from the real text width — the old
  // estimate (chars × 0.8 × size) clipped wide letters / long names.
  const mctx = document.createElement('canvas').getContext('2d');
  mctx.font = font;
  const chars  = [...text];
  const sp     = fs * 0.04 + spacing;
  const widths = chars.map(c => mctx.measureText(c).width * spread + sp);
  const total  = widths.reduce((a,b) => a+b, 0);
  const pad   = fs * 1.5 + depth;
  const w     = Math.max(200, total * 1.15 + pad * 2);
  const h     = fs * 5 + depth * 2;
  const tmpC  = document.createElement('canvas');
  tmpC.width  = Math.round(w * scale);
  tmpC.height = Math.round(h * scale);
  const ctx   = tmpC.getContext('2d', { willReadFrequently: true });
  ctx.scale(scale, scale);
  ctx.font         = font;
  ctx.textBaseline = 'middle';

  let   xOff   = (w - pad*2 - total) / 2;
  const tw     = w - pad*2, th = h - pad*2;

  chars.forEach((ch, i) => {
    const cx = xOff + widths[i]/2 - sp/2;
    const t  = Math.min(Math.max(cx / tw, 0.001), 0.999);
    const pt = getWarpPosition(t, selectedWarpType, tw, th, amount, reverse, smooth);
    ctx.save();
    ctx.translate(pad + pt.x, pad + pt.y);
    ctx.rotate(pt.a);
    if (depth > 0) {
      ctx.shadowColor = 'rgba(0,0,0,.5)';
      ctx.shadowOffsetX = depth * .4;
      ctx.shadowOffsetY = depth * .6;
      ctx.shadowBlur = depth * .5;
    }
    ctx.fillStyle = fill;
    if (stroke && sw > 0) {
      ctx.strokeStyle = stroke; ctx.lineWidth = sw; ctx.lineJoin = 'round'; ctx.strokeText(ch, 0, 0);
    }
    ctx.fillText(ch, 0, 0);
    ctx.restore();
    xOff += widths[i];
  });

  // Trim the empty margin so the image's box hugs the warped text.
  const W = tmpC.width, H = tmpC.height;
  const px = ctx.getImageData(0, 0, W, H).data;
  let x0 = W, y0 = H, x1 = -1, y1 = -1;
  for (let y = 0; y < H; y++) { const row = y * W * 4; for (let x = 0; x < W; x++) { if (px[row + x * 4 + 3] > 8) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; } } }
  let src = tmpC;
  if (x1 >= 0) {
    const m = 4 * scale;
    x0 = Math.max(0, x0 - m); y0 = Math.max(0, y0 - m); x1 = Math.min(W - 1, x1 + m); y1 = Math.min(H - 1, y1 + m);
    src = document.createElement('canvas');
    src.width = x1 - x0 + 1; src.height = y1 - y0 + 1;
    src.getContext('2d').drawImage(tmpC, x0, y0, src.width, src.height, 0, 0, src.width, src.height);
  }

  const dataURL = src.toDataURL('image/png');
  const imgEl   = new Image();
  imgEl.onload  = () => {
    // "Keep Size" now scales UNIFORMLY so the warped text is as wide as the
    // original — it used to stretch width and height independently, which
    // squashed the result into a thin, distorted strip.
    let sc = (obj.scaleX || 1) / scale;
    if (keepSz) sc = (obj.getScaledWidth() || src.width / scale) / src.width;
    const center = obj.getCenterPoint();
    const fImg = new fabric.Image(imgEl, {
      left: center.x, top: center.y, originX: 'center', originY: 'center',
      scaleX: sc, scaleY: sc, angle: obj.angle || 0, opacity: obj.opacity ?? 1,
      data: {
        warpedFrom: text, warpType: selectedWarpType, warpAmount: amount,
        warpSpread: +document.getElementById('warpSpread').value,
        warpSpacing: spacing, warpDepth: depth,
        origFontSize: fs, origFill: fill, origFamily: family, origFw: fw, origFi: fi,
        origStroke: stroke, origSw: sw, origScale: obj.scaleX || 1
      }
    });
    hist.locked = true;
    const idx = canvas.getObjects().indexOf(obj);
    canvas.remove(obj);
    canvas.add(fImg);
    if (idx >= 0) canvas.moveTo(fImg, idx);    // keep its place in the layer stack
    hist.locked = false;
    canvas.setActiveObject(fImg).renderAll();
    histSave(); refreshLayers();
    closeWarpModal();
    showToast('Text warp applied! (select it and press Warp again to re-edit)');
  };
  imgEl.src = dataURL;
}

function reEditWarp() {
  const obj = canvas.getActiveObject();
  if (!obj || !obj.data?.warpedFrom) {
    showToast('Select a warped text object first', 'err'); return;
  }
  // Restore original text object for re-editing
  const d = obj.data;
  const center = obj.getCenterPoint();
  const txt = new fabric.IText(d.warpedFrom, {
    left: center.x, top: center.y, originX: 'center', originY: 'center',
    fontSize: d.origFontSize, fill: d.origFill, fontFamily: d.origFamily,
    fontWeight: d.origFw, fontStyle: d.origFi, angle: obj.angle || 0,
    scaleX: d.origScale || 1, scaleY: d.origScale || 1,
    stroke: d.origStroke || '', strokeWidth: d.origSw || 0,
    data: { warpType: d.warpType, warpAmount: d.warpAmount, warpSpread: d.warpSpread, warpSpacing: d.warpSpacing, warpDepth: d.warpDepth }
  });
  hist.locked = true;
  const idx = canvas.getObjects().indexOf(obj);
  canvas.remove(obj);
  canvas.add(txt);
  if (idx >= 0) canvas.moveTo(txt, idx);
  hist.locked = false;
  canvas.setActiveObject(txt).renderAll();
  histSave();
  openWarpModal();
}

// ── Gradient Fill (Pro) ─────────────────────────────────────────────
/* Swatch only. CSS measures gradient angles from "to top" while the canvas
   gradient measures from "to the right", so the preview used to point 90°
   away from what was actually applied (e.g. "← →" previewed as "↑"). */
function _previewGradSwatch() {
  const c1   = document.getElementById('gC1').value;
  const cMid = document.getElementById('gCMid').value;
  const c2   = document.getElementById('gC2').value;
  const ang  = +(document.getElementById('gAngle').value || 0);
  document.getElementById('gradPreview').style.background =
    `linear-gradient(${ang + 90}deg, ${c1}, ${cMid}, ${c2})`;
}
function previewGrad() {
  _previewGradSwatch();
  const c1   = document.getElementById('gC1').value;
  const cMid = document.getElementById('gCMid').value;
  const c2   = document.getElementById('gC2').value;
  const ang  = document.getElementById('gAngle').value || 0;

  // Live-update the object if a gradient is already applied to it, so dragging
  // the Start/Mid/End colour pickers previews on the real design immediately —
  // not just on the little swatch above (this was the "colour doesn't apply" bug).
  const objs = canvas.getActiveObjects().filter(o => o.data && o.data._grad);
  objs.forEach(obj => {
    const p = obj.data._grad;
    p.c1 = c1; p.cMid = cMid; p.c2 = c2;
    if (p.type !== 'custom2pt') p.ang = +ang || 0;
    _applyGradPattern(obj, p);
  });
  if (objs.length) histSaveSoon();
}
_previewGradSwatch();
// init pattern preview after page load
setTimeout(() => { try { previewPattern(); } catch(e){} }, 200);

// ── applyGradient — canvas-space approach ───────────────────────
// Uses a full-canvas HTML canvas as Pattern source so the gradient
// is always guaranteed to cover the COMPLETE object at any scale/angle.
// Gradient params are stored in obj.data._grad so the canvas:object:modified
// event can reapply automatically when the object is moved or resized.
function applyGradient(type, customParams) {
  const objs = canvas.getActiveObjects().filter(o => o.type !== 'image' && o.type !== 'group');
  if (!objs.length) { showToast('Select a shape or text first', 'info'); return; }

  objs.forEach(obj => {
    const p = Object.assign({}, customParams || {
      c1:   document.getElementById('gC1').value,
      cMid: document.getElementById('gCMid').value,
      c2:   document.getElementById('gC2').value,
      ang:  +document.getElementById('gAngle').value || 0,
    });
    p.type = type;
    // Store params on object so we can reapply on move/resize
    obj.data = Object.assign({}, obj.data, { _grad: p });
    _applyGradPattern(obj, p);
  });
  histSave();

  ['gBtnLinear','gBtnRadial','gBtnDiamond','gBtnConical'].forEach(id=>{
    const el=document.getElementById(id); if(el) el.classList.remove('active');
  });
  const idMap = {linear:'gBtnLinear',radial:'gBtnRadial',diamond:'gBtnDiamond',conical:'gBtnConical'};
  const ab = document.getElementById(idMap[type]); if(ab) ab.classList.add('active');
  showToast(type[0].toUpperCase()+type.slice(1)+' gradient applied!');
}

// Convert a CANVAS-absolute point (e.g. from the drag tool) into the
// object's own LOCAL, unscaled, top-left-origin coordinate space — needed
// because Fabric fills a shape's path using its local coordinate system
// (already pre-scaled/rotated/translated by the context transform), not
// canvas-absolute coordinates.
function _canvasPtToLocal(obj, px, py) {
  const m = obj.calcTransformMatrix();
  const inv = fabric.util.invertTransform(m);
  const pt = fabric.util.transformPoint({ x: px, y: py }, inv);
  return { x: pt.x + obj.width / 2, y: pt.y + obj.height / 2 };
}

// Core render — builds a real fabric.Gradient (linear/radial) using
// gradientUnits:'pixels' in the object's OWN local coordinate space
// (0,0 = top-left of its unscaled bounding box). This is the fix for the
// "gradient colour not applying / not covering the object" bug: the
// previous version painted a full-CANVAS-sized offscreen bitmap using
// canvas-absolute coordinates and applied it as a fabric.Pattern — but
// Fabric actually samples a Pattern fill starting at the object's own
// LOCAL top-left, not the canvas origin, so for any object not sitting
// exactly at canvas (0,0) the visible result was the wrong slice of the
// bitmap: almost always a near-solid patch of colour 1, with Mid/End
// barely (or never) reaching the object at all. Native fabric.Gradient
// with 'pixels' units is anchored correctly to the object automatically,
// and Fabric rescales/rotates it for free whenever the object transforms
// — no manual reapply-on-move/resize workaround needed for these types.
// Conical has no native fabric.Gradient equivalent, so it alone still
// uses a Pattern — but now sized to the object's own local box instead
// of the whole canvas, which fixes the exact same alignment problem.
function _applyGradPattern(obj, p) {
  const w = Math.max(1, Math.round(obj.width  || 1));
  const h = Math.max(1, Math.round(obj.height || 1));
  const ang = (p.ang || 0) * Math.PI / 180;

  if (p.type === 'conical') {
    const el = document.createElement('canvas');
    el.width = w; el.height = h;
    const ctx = el.getContext('2d');
    const cx = w / 2, cy = h / 2;
    const sweepR = Math.sqrt(w * w + h * h) / 2 + 2;
    // Loop through all 3 colours (c1 → cMid → c2 → cMid → c1) so the sweep
    // uses the full Start/Mid/End selection and ends back where it started —
    // both the native and manual-fallback paths use identical stops.
    if (typeof ctx.createConicGradient === 'function') {
      const cg = ctx.createConicGradient(-Math.PI / 2, cx, cy);
      cg.addColorStop(0,    p.c1);
      cg.addColorStop(0.25, p.cMid);
      cg.addColorStop(0.5,  p.c2);
      cg.addColorStop(0.75, p.cMid);
      cg.addColorStop(1,    p.c1);
      ctx.fillStyle = cg;
      ctx.fillRect(0, 0, w, h);
    } else {
      // 72 pie sectors — manual fallback for browsers without createConicGradient
      const n = 72;
      const hexToRgb = hx => [parseInt(hx.slice(1,3),16), parseInt(hx.slice(3,5),16), parseInt(hx.slice(5,7),16)];
      const stops = [ {t:0,c:p.c1}, {t:0.25,c:p.cMid}, {t:0.5,c:p.c2}, {t:0.75,c:p.cMid}, {t:1,c:p.c1} ];
      function colorAt(t) {
        for (let i = 0; i < stops.length - 1; i++) {
          if (t >= stops[i].t && t <= stops[i+1].t) {
            const lt = (t - stops[i].t) / (stops[i+1].t - stops[i].t || 1);
            const c0 = hexToRgb(stops[i].c), c1_ = hexToRgb(stops[i+1].c);
            return [0,1,2].map(k => Math.round(c0[k] + (c1_[k]-c0[k]) * lt));
          }
        }
        return hexToRgb(p.c1);
      }
      for (let i=0;i<n;i++) {
        const t = i/n;
        const [ri,gi,bi] = colorAt(t);
        ctx.fillStyle=`rgb(${ri},${gi},${bi})`;
        const a1=(i/n)*Math.PI*2-Math.PI/2, a2=((i+1)/n)*Math.PI*2-Math.PI/2;
        ctx.beginPath(); ctx.moveTo(cx,cy); ctx.arc(cx,cy,sweepR,a1,a2); ctx.closePath(); ctx.fill();
      }
    }
    obj.set('fill', new fabric.Pattern({ source: el, repeat: 'no-repeat' }));
    obj.dirty = true; obj.setCoords(); canvas.renderAll();
    return;
  }

  let gtype = 'linear', coords;
  if (p.type === 'radial') {
    coords = { x1: w/2, y1: h/2, r1: 0, x2: w/2, y2: h/2, r2: Math.sqrt(w*w + h*h) / 2 };
    gtype = 'radial';
  } else if (p.type === 'diamond') {
    coords = { x1: w/2, y1: h/2, r1: 0, x2: w/2, y2: h/2, r2: Math.max(w, h) / 2 };
    gtype = 'radial';
  } else if (p.type === 'custom2pt') {
    // Two-point drag gradient — the drag points were captured in canvas-
    // absolute coordinates, so convert them into the object's local space.
    const s = _canvasPtToLocal(obj, p.sx, p.sy);
    const e = _canvasPtToLocal(obj, p.ex, p.ey);
    coords = { x1: s.x, y1: s.y, x2: e.x, y2: e.y };
  } else {
    // Linear with angle — the gradient LINE's half-length is the object's
    // own half-width/height projected onto the angle (same formula CSS uses
    // for linear-gradient(<angle>)), so Start lands exactly at one edge and
    // End exactly at the opposite edge, with Mid at the centre.
    const halfLen = Math.abs(w/2 * Math.cos(ang)) + Math.abs(h/2 * Math.sin(ang));
    coords = {
      x1: w/2 - halfLen * Math.cos(ang), y1: h/2 - halfLen * Math.sin(ang),
      x2: w/2 + halfLen * Math.cos(ang), y2: h/2 + halfLen * Math.sin(ang),
    };
  }

  const colorStops = (p.type === 'diamond')
    ? [ {offset:0,color:p.c1}, {offset:0.45,color:p.cMid}, {offset:0.55,color:p.cMid}, {offset:1,color:p.c2} ]
    : [ {offset:0,color:p.c1}, {offset:0.5,color:p.cMid}, {offset:1,color:p.c2} ];

  obj.set('fill', new fabric.Gradient({ type: gtype, gradientUnits: 'pixels', coords, colorStops }));
  obj.dirty = true;
  obj.setCoords();
  canvas.renderAll();
}

// Direction quick buttons
function setGradDir(deg) {
  document.getElementById('gAngle').value = deg;
  document.getElementById('gAngleVal').textContent = deg + '°';
  previewGrad();
  document.querySelectorAll('.gdir-btn').forEach(b => b.classList.remove('active'));
  const btn = document.getElementById('gDir' + deg);
  if (btn) btn.classList.add('active');
  // Re-apply using whichever gradient type is currently on the object —
  // previously this always forced 'linear', silently reverting a user's
  // Radial/Diamond/Conical choice the moment they touched a direction arrow.
  const obj = canvas.getActiveObjects()[0];
  if (obj) {
    let curType = (obj.data && obj.data._grad && obj.data._grad.type) || 'linear';
    // custom2pt has no "angle" concept (it's anchored to two drag points) —
    // picking a preset direction arrow means the user wants to leave drag mode.
    if (curType === 'custom2pt') curType = 'linear';
    applyGradient(curType);
  }
}

// ── Interactive Gradient Drag Tool ───────────────────────────────
let _gradDragMode = false;
let _gradDragStart = null;

function toggleInterFillDrag() {
  _gradDragMode = !_gradDragMode;
  const btn  = document.getElementById('gBtnDrag');
  const hint = document.getElementById('dragFillHint');
  const wrap = document.getElementById('canvasWrap');
  if (_gradDragMode) {
    if (btn)  { btn.classList.add('drag-on');  btn.textContent = '⤢ Drag ON'; }
    if (hint) hint.style.display = '';
    if (wrap) wrap.style.cursor = 'crosshair';
    showToast('Drag on canvas to set gradient direction', 'info');
  } else {
    _exitGradDrag();
  }
}

function _exitGradDrag() {
  _gradDragMode = false;
  _gradDragStart = null;
  const btn  = document.getElementById('gBtnDrag');
  const hint = document.getElementById('dragFillHint');
  const line = document.getElementById('gradDragLine');
  const wrap = document.getElementById('canvasWrap');
  if (btn)  { btn.classList.remove('drag-on'); btn.textContent = '⤢ Drag'; }
  if (hint) hint.style.display = 'none';
  if (line) line.style.display = 'none';
  if (wrap) wrap.style.cursor = '';
}

// Attach drag listeners on canvas (patched after canvas init)
// Shared by both mouse and touch events — takes already-extracted client coords
// so the one-finger-drag gesture on mobile works exactly like a mouse drag here.
function _initGradDragListeners() {
  // Fabric uses an upper-canvas overlay for all mouse events; attach there
  const target = canvas.upperCanvasEl || document.getElementById('fabricCanvas');
  if (!target) return;
  const line   = document.getElementById('gradDragLine');
  const lineEl = document.getElementById('gradDragLineEl');

  function onDown(clientX, clientY) {
    if (!_gradDragMode) return;
    const rect = target.getBoundingClientRect();
    const sx = clientX - rect.left, sy = clientY - rect.top;
    // Screen → Fabric canvas coords (handles zoom+pan)
    const vpt = canvas.viewportTransform || [1,0,0,1,0,0];
    _gradDragStart = {
      fx: (sx - vpt[4]) / vpt[0], fy: (sy - vpt[5]) / vpt[3],
      sx, sy,
    };
    if (line)   line.style.display = '';
    if (lineEl) { lineEl.setAttribute('x1',sx); lineEl.setAttribute('y1',sy);
                  lineEl.setAttribute('x2',sx); lineEl.setAttribute('y2',sy); }
  }

  function onMove(clientX, clientY) {
    if (!_gradDragMode || !_gradDragStart) return;
    const rect = target.getBoundingClientRect();
    const ex = clientX - rect.left, ey = clientY - rect.top;
    if (lineEl) { lineEl.setAttribute('x2', ex); lineEl.setAttribute('y2', ey); }
  }

  function onUp(clientX, clientY) {
    if (!_gradDragMode || !_gradDragStart) return;
    const rect = target.getBoundingClientRect();
    const ex_s = clientX - rect.left, ey_s = clientY - rect.top;
    const vpt  = canvas.viewportTransform || [1,0,0,1,0,0];
    const ex_f = (ex_s - vpt[4]) / vpt[0];
    const ey_f = (ey_s - vpt[5]) / vpt[3];

    const dx = ex_f - _gradDragStart.fx, dy = ey_f - _gradDragStart.fy;
    if (Math.sqrt(dx*dx+dy*dy) < 5) { _gradDragStart = null; return; }

    const obj = canvas.getActiveObject();
    if (!obj) { showToast('Select an object first', 'info'); _gradDragStart = null; return; }

    const c1   = document.getElementById('gC1').value;
    const cMid = document.getElementById('gCMid').value;
    const c2   = document.getElementById('gC2').value;

    const params = { c1, cMid, c2, type: 'custom2pt', ang: 0,
                     sx: _gradDragStart.fx, sy: _gradDragStart.fy,
                     ex: ex_f, ey: ey_f };
    if (!obj.data) obj.data = {};
    obj.data._grad = params;
    _applyGradPattern(obj, params);
    histSave();
    showToast('Gradient direction set by drag!');

    _gradDragStart = null;
    if (line) line.style.display = 'none';
    _exitGradDrag();
  }

  target.addEventListener('mousedown', e => { onDown(e.clientX, e.clientY); if (_gradDragMode) { e.preventDefault(); e.stopPropagation(); } }, true); // capture phase so we beat Fabric's handlers
  target.addEventListener('mousemove', e => { onMove(e.clientX, e.clientY); }, true);
  target.addEventListener('mouseup',   e => { const was=_gradDragMode&&_gradDragStart; onUp(e.clientX, e.clientY); if (was) { e.preventDefault(); e.stopPropagation(); } }, true);

  target.addEventListener('touchstart', e => {
    if (!_gradDragMode || !e.touches.length) return;
    onDown(e.touches[0].clientX, e.touches[0].clientY);
    e.preventDefault(); e.stopPropagation();
  }, { capture: true, passive: false });
  target.addEventListener('touchmove', e => {
    if (!_gradDragMode || !_gradDragStart || !e.touches.length) return;
    onMove(e.touches[0].clientX, e.touches[0].clientY);
    e.preventDefault(); e.stopPropagation();
  }, { capture: true, passive: false });
  target.addEventListener('touchend', e => {
    if (!_gradDragMode || !_gradDragStart || !e.changedTouches.length) return;
    onUp(e.changedTouches[0].clientX, e.changedTouches[0].clientY);
    e.preventDefault(); e.stopPropagation();
  }, { capture: true, passive: false });
}

// Reapply gradient when object is moved/resized
canvas.on('object:modified', function(opt) {
  const obj = opt.target;
  if (obj && obj.data && obj.data._grad) {
    const p = obj.data._grad;
    if (p.type !== 'custom2pt') { // custom2pt stays anchored to canvas coords
      _applyGradPattern(obj, p);
    }
  }
});

function removeGradient() {
  const objs = canvas.getActiveObjects();
  if (!objs.length) return;
  // Clear the stored gradient params — otherwise the canvas:object:modified
  // handler below would silently REAPPLY the old gradient the next time this
  // object is moved or resized, undoing the removal.
  objs.forEach(obj => {
    if (obj.data) obj.data._grad = null;
    // Fall back to the gradient's start colour so the object keeps its look.
    const start = (obj.fill && obj.fill.colorStops && obj.fill.colorStops[0]?.color) || document.getElementById('gC1').value;
    obj.set('fill', start || (isTextObj(obj) ? '#1f2937' : '#4f46e5'));
    obj.dirty = true;
    obj.setCoords();
  });
  canvas._currentTransform = null;
  canvas.renderAll();
  histSave();
  ['gBtnLinear','gBtnRadial','gBtnDiamond','gBtnConical'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
  });
  showToast('Gradient / Pattern removed');
}

// Conical gradient is now handled directly in _applyGradPattern (type='conical')

// ════════════════════════════════════════════════════════════════
// NEW CANVAS — create fresh design with custom size, DPI, bg
// ════════════════════════════════════════════════════════════════
let _ncCurrentDpi  = 96;  // DPI of the CURRENT design being edited (for print/PDF export)
let _ncModalDpi    = 150; // DPI selected in the New Canvas modal (separate state)
let _ncBgTransparent = false;

function openNewCanvasModal() {
  closeEdPanel();
  ncUpdatePxPreview();
  ncUpdateDpiHint();
  document.getElementById('newCanvasModal').style.display = 'flex';
}
function closeNewCanvasModal() {
  document.getElementById('newCanvasModal').style.display = 'none';
}

function ncSetPreset(w, h, unit, dpi, label) {
  document.getElementById('ncUnit').value = unit;
  document.getElementById('ncW').value    = w;
  document.getElementById('ncH').value    = h;
  _ncBgTransparent = false;
  ncSetDpi(dpi);
  ncUpdatePxPreview();
}

function ncSetDpi(dpi) {
  _ncModalDpi = dpi; // modal-only, does NOT affect current design's print DPI
  document.querySelectorAll('.nc-dpi-btn').forEach(b => {
    b.classList.toggle('active', +b.dataset.dpi === dpi);
  });
  ncUpdateDpiHint();
  ncUpdatePxPreview();
}

function ncSetTransparent() {
  _ncBgTransparent = true;
  showToast('New canvas will have a transparent background', 'info');
}
function ncSetBg(color) {
  _ncBgTransparent = false;   // picking any colour cancels "transparent"
  document.getElementById('ncBg').value = color;
}

function ncUnitChange() { ncUpdatePxPreview(); }

function ncSwapOrientation() {
  const w = document.getElementById('ncW').value;
  const h = document.getElementById('ncH').value;
  document.getElementById('ncW').value = h;
  document.getElementById('ncH').value = w;
  ncUpdatePxPreview();
}

function _ncToPx(val, unit, dpi) {
  val = +val;
  switch (unit) {
    case 'in': return Math.round(val * dpi);
    case 'cm': return Math.round(val * dpi / 2.54);
    case 'ft': return Math.round(val * 12 * dpi);
    default:   return Math.round(val); // px
  }
}
function _ncToPhysical(pxW, pxH, dpi) {
  if (dpi <= 0) return '—';
  const wIn = pxW / dpi, hIn = pxH / dpi;
  if (wIn >= 12 || hIn >= 12) {
    return (wIn / 12).toFixed(2) + ' × ' + (hIn / 12).toFixed(2) + ' ft';
  }
  const wCm = (wIn * 2.54).toFixed(1), hCm = (hIn * 2.54).toFixed(1);
  return wCm + ' × ' + hCm + ' cm  (' + wIn.toFixed(2) + '×' + hIn.toFixed(2) + '")'
}

const NC_MAX_PX = 16000; // safe browser canvas limit

function ncUpdatePxPreview() {
  const unit = document.getElementById('ncUnit').value;
  const dpi  = _ncModalDpi;
  const pw   = _ncToPx(document.getElementById('ncW').value, unit, dpi);
  const ph   = _ncToPx(document.getElementById('ncH').value, unit, dpi);
  const maxDim = Math.max(pw, ph);

  // Color code the preview based on size safety
  const wEl = document.getElementById('ncPxW'), hEl = document.getElementById('ncPxH');
  wEl.textContent = pw; hEl.textContent = ph;
  document.getElementById('ncPxDpi').textContent = dpi;
  document.getElementById('ncPhysical').textContent = unit === 'px'
    ? _ncToPhysical(pw, ph, dpi)
    : document.getElementById('ncW').value + ' × ' + document.getElementById('ncH').value + ' ' + unit;

  const hint = document.getElementById('ncDpiHint');
  const previewBox = document.getElementById('ncPxPreview');
  if (maxDim > NC_MAX_PX) {
    wEl.style.color = '#f87171'; hEl.style.color = '#f87171';
    previewBox.style.borderColor = '#7f1d1d';
    // Suggest best DPI to bring within limit
    const dipis = [10, 25, 50, 72, 96, 150, 300, 600];
    const wVal = +document.getElementById('ncW').value, hVal = +document.getElementById('ncH').value;
    const toIn = { px: 1/dpi, in: 1, cm: 1/2.54, ft: 12 };
    const maxIn = Math.max(wVal, hVal) * (toIn[unit] || 1);
    const suggestDpi = dipis.find(d => Math.round(maxIn * d) <= NC_MAX_PX) || 10;
    hint.innerHTML = '<span style="color:#f87171;">⚠ Canvas too large (' + pw + '×' + ph + ' px). Browser limit ~' + NC_MAX_PX + 'px.</span>'
      + ' <button onclick="ncSetDpi(' + suggestDpi + ')" style="background:#7c2d12;border:1px solid #f87171;color:#fca5a5;border-radius:5px;padding:1px 7px;font-size:.66rem;cursor:pointer;">Auto-fix: use ' + suggestDpi + ' DPI</button>';
  } else if (maxDim > NC_MAX_PX * 0.8) {
    wEl.style.color = '#fbbf24'; hEl.style.color = '#fbbf24';
    previewBox.style.borderColor = '#78350f';
    hint.textContent = '⚠ Canvas is large. May be slow on some computers. (' + pw + '×' + ph + ' px)';
  } else {
    wEl.style.color = '#a5b4fc'; hEl.style.color = '#a5b4fc';
    previewBox.style.borderColor = '#334155';
    ncUpdateDpiHint(); // restore normal hint
  }
}

function ncUpdateDpiHint() {
  const hints = {
    10:  '10 DPI — Hoarding / highway billboard. Viewed from 100+ feet. Max ~133 ft per side.',
    25:  '25 DPI — Large outdoor billboard / sign. Viewed from 20-100 feet. Max ~53 ft per side.',
    50:  '50 DPI — Flex banner / large wall print. Viewed from 6-20 feet. Max ~26 ft per side.',
    72:  '72 DPI — Screen display only. Not suitable for professional printing. Max ~18 ft.',
    96:  '96 DPI — Web / digital. OK for digital use only. Max ~14 ft.',
    150: '150 DPI — Good quality print. Suitable for posters, medium banners. Max ~107 inches.',
    300: '300 DPI — Professional print standard. Best for ID cards, brochures, photos. Max ~53 inches.',
    600: '600 DPI — Ultra high quality. Fine art, photo prints. Max ~26 inches.',
  };
  document.getElementById('ncDpiHint').textContent = hints[_ncModalDpi] || '';
}

async function createNewCanvas() {
  const unit = document.getElementById('ncUnit').value;
  const dpi  = _ncModalDpi; // use modal DPI, not current design's DPI
  const pw   = _ncToPx(document.getElementById('ncW').value, unit, dpi);
  const ph   = _ncToPx(document.getElementById('ncH').value, unit, dpi);
  const name = document.getElementById('ncName').value.trim() || 'My Design';
  const bg   = _ncBgTransparent ? '' : (document.getElementById('ncBg').value || '#ffffff');
  if (!(+document.getElementById('ncW').value > 0) || !(+document.getElementById('ncH').value > 0)) { showToast('Enter a width and height', 'err'); return; }

  if (pw < 10 || ph < 10) {
    showToast('Canvas size too small (minimum 10 px)', 'err'); return;
  }
  if (pw > NC_MAX_PX || ph > NC_MAX_PX) {
    // Find the max physical dimension in inches (unit-agnostic)
    const wVal = +document.getElementById('ncW').value, hVal = +document.getElementById('ncH').value;
    const toIn = { px: 1/dpi, in: 1, cm: 1/2.54, ft: 12 };
    const maxIn = Math.max(wVal, hVal) * (toIn[unit] || 1);
    const dipis = [10, 25, 50, 72, 96, 150, 300, 600];
    const suggest = dipis.find(d => Math.round(maxIn * d) <= NC_MAX_PX) || 10;
    showToast('Canvas too large (' + pw + '×' + ph + ' px). Try ' + suggest + ' DPI for this size.', 'err');
    ncSetDpi(suggest);
    ncUpdatePxPreview();
    return;
  }

  // Build minimal Fabric.js JSON for a blank canvas
  const blankJson = JSON.stringify({
    version: '5.3.0',
    objects: [],
    background: bg,          // '' = transparent (the choice used to be silently turned into white)
    _size: { w: pw, h: ph },
    _meta: { dpi, unit, physW: document.getElementById('ncW').value, physH: document.getElementById('ncH').value }
  });

  // Generate a tiny solid thumbnail
  const thumbEl = document.createElement('canvas');
  thumbEl.width = 240; thumbEl.height = Math.round(240 * ph / pw);
  const tctx = thumbEl.getContext('2d');
  if (bg) { tctx.fillStyle = bg; tctx.fillRect(0, 0, thumbEl.width, thumbEl.height); }
  else { for (let y = 0; y < thumbEl.height; y += 12) for (let x = 0; x < thumbEl.width; x += 12) { tctx.fillStyle = ((x + y) / 12) % 2 ? '#e5e7eb' : '#ffffff'; tctx.fillRect(x, y, 12, 12); } }
  const thumb = thumbEl.toDataURL('image/jpeg', 0.7);

  const btn = document.querySelector('#newCanvasModal button[onclick="createNewCanvas()"]');
  if (btn && btn.disabled) return;
  if (btn) btn.disabled = true;
  showToast('Creating canvas…');

  const fd = new FormData();
  fd.append('action',       'save');
  fd.append('csrf_token',   ED.csrf);
  fd.append('gallery_id',   0);
  fd.append('name',         name);
  fd.append('canvas_json',  blankJson);
  fd.append('thumbnail_data', thumb);
  fd.append('canvas_width',  pw);
  fd.append('canvas_height', ph);

  try {
    const res = await idmFetch(ED.gallery_api, { method: 'POST', body: fd }, 30000);
    const d   = await res.json();
    if (d.success && d.gallery_id) {
      showToast('Canvas created! Opening…');
      closeNewCanvasModal();
      _dirty = false;   // the user chose to start a new design — don't block the navigation
      window.location.href = window.location.pathname + '?gallery_id=' + encodeURIComponent(d.gallery_id);
    } else {
      showToast(d.message || 'Failed to create canvas', 'err');
      if (btn) btn.disabled = false;
    }
  } catch (ex) {
    showToast('Network error: ' + ex.message, 'err');
    if (btn) btn.disabled = false;
  }
}

// Read DPI from loaded canvas metadata on startup
(function initDesignDpi() {
  try {
    const meta = (ED.canvas_json ? JSON.parse(ED.canvas_json) : {})._meta;
    if (meta && meta.dpi) _ncCurrentDpi = +meta.dpi;
  } catch(_) {}
})();

// ── Gradient Presets ─────────────────────────────────────────────
function applyGradPreset(c1, cMid, c2, type) {
  document.getElementById('gC1').value   = c1;
  document.getElementById('gCMid').value = cMid;
  document.getElementById('gC2').value   = c2;
  previewGrad();
  applyGradient(type || 'linear');
}

// ── Pattern Fill ─────────────────────────────────────────────────
let _currentPatType = 'hstripe';

function selectPatType(type, btn) {
  _currentPatType = type;
  document.querySelectorAll('.pat-type-btn').forEach(b => b.classList.remove('sel'));
  if (btn) btn.classList.add('sel');
  previewPattern();
}

function _buildPatternCanvas(type, fg, bg, size) {
  const pc = document.createElement('canvas');
  pc.width = size; pc.height = size;
  const ctx = pc.getContext('2d');
  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, size, size);
  ctx.strokeStyle = fg;
  ctx.fillStyle = fg;
  ctx.lineWidth = Math.max(1, size * 0.18);

  switch (type) {
    case 'hstripe':
      ctx.beginPath(); ctx.moveTo(0, size*0.25); ctx.lineTo(size, size*0.25); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(0, size*0.75); ctx.lineTo(size, size*0.75); ctx.stroke();
      break;
    case 'vstripe':
      ctx.beginPath(); ctx.moveTo(size*0.25, 0); ctx.lineTo(size*0.25, size); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(size*0.75, 0); ctx.lineTo(size*0.75, size); ctx.stroke();
      break;
    case 'diagonal':
      ctx.lineWidth = Math.max(1.5, size * 0.2);
      ctx.beginPath(); ctx.moveTo(-size*0.2, size); ctx.lineTo(size, -size*0.2); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(0, size*1.2); ctx.lineTo(size*1.2, 0); ctx.stroke();
      break;
    case 'dots':
      const dr = size * 0.2;
      [[0.25,0.25],[0.75,0.25],[0.25,0.75],[0.75,0.75]].forEach(([cx,cy])=>{
        ctx.beginPath(); ctx.arc(cx*size, cy*size, dr, 0, Math.PI*2); ctx.fill();
      });
      break;
    case 'checker':
      ctx.fillRect(0, 0, size/2, size/2);
      ctx.fillRect(size/2, size/2, size/2, size/2);
      break;
    case 'crosshatch':
      ctx.lineWidth = Math.max(1, size*0.12);
      ctx.beginPath(); ctx.moveTo(0, size/2); ctx.lineTo(size, size/2); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(size/2, 0); ctx.lineTo(size/2, size); ctx.stroke();
      ctx.lineWidth = Math.max(1, size*0.09);
      ctx.beginPath(); ctx.moveTo(0, size); ctx.lineTo(size, 0); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(size, size); ctx.stroke();
      break;
    case 'waves':
      ctx.lineWidth = Math.max(1.5, size*0.13);
      ctx.beginPath();
      ctx.moveTo(0, size*0.35);
      ctx.quadraticCurveTo(size*0.25, size*0.05, size*0.5, size*0.35);
      ctx.quadraticCurveTo(size*0.75, size*0.65, size, size*0.35);
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(0, size*0.75);
      ctx.quadraticCurveTo(size*0.25, size*0.45, size*0.5, size*0.75);
      ctx.quadraticCurveTo(size*0.75, size*1.05, size, size*0.75);
      ctx.stroke();
      break;
    case 'brick':
      ctx.lineWidth = Math.max(1, size*0.1);
      ctx.strokeRect(1, 1, size*0.5-2, size*0.48-2);
      ctx.strokeRect(size*0.5+1, 1, size*0.5-2, size*0.48-2);
      ctx.strokeRect(size*0.25+1, size*0.5+1, size*0.5-2, size*0.48-2);
      break;
  }
  return pc;
}

function previewPattern() {
  const fg   = document.getElementById('patFg').value;
  const bgTr = document.getElementById('patBgTrans').checked;
  const bg   = bgTr ? 'transparent' : document.getElementById('patBg').value;
  const size = +document.getElementById('patSize').value;
  const type = _currentPatType;
  const pc   = _buildPatternCanvas(type, fg, bgTr ? 'rgba(0,0,0,0)' : bg, size);
  const box  = document.getElementById('patPreviewBox');
  if (box) box.style.background = `url(${pc.toDataURL()}) repeat`;
}

function applyPatternFill() {
  const objs = canvas.getActiveObjects().filter(o => o.type !== 'image' && o.type !== 'group');
  if (!objs.length) { showToast('Select a shape first', 'info'); return; }
  const fg   = document.getElementById('patFg').value;
  const bgTr = document.getElementById('patBgTrans').checked;
  const bg   = bgTr ? 'rgba(0,0,0,0)' : document.getElementById('patBg').value;
  const size = +document.getElementById('patSize').value;
  const pc   = _buildPatternCanvas(_currentPatType, fg, bg, size);
  objs.forEach(obj => {
    obj.set('fill', new fabric.Pattern({ source: pc, repeat: 'repeat' }));
    if (obj.data) obj.data._grad = null;
    obj.dirty = true;
  });
  canvas.renderAll();
  histSave();
  showToast('Pattern fill applied!');
}

// Scrolls whichever element is actually the scroll container — on desktop
// that's #propsPanel itself, on mobile it's the .ed-props-scroll wrapper
// inside the bottom sheet. Setting scrollTop on the non-scrolling one is a no-op.
function _scrollPropsTo(pos) {
  [document.getElementById('propsPanel'), document.querySelector('.ed-props-scroll')].forEach(el => {
    if (el) el.scrollTop = pos === 'bottom' ? el.scrollHeight : pos;
  });
}

// ── Interactive Fill Panel (opens pattern + gradient sections) ────
function openInterFillPanel() {
  const obj = canvas.getActiveObject();
  if (!obj) { showToast('Select a shape first', 'info'); return; }
  document.getElementById('pGradSec').style.display    = '';
  document.getElementById('pPatternSec').style.display = '';
  document.getElementById('tInterFill').classList.toggle('active');
  mobAutoOpenProps();
  _scrollPropsTo(0);
  showToast('Interactive Fill: choose Gradient or Pattern below', 'info');
}

// ── Fade / Transparency ───────────────────────────────────────────
function openFadePanel() {
  const obj = canvas.getActiveObject();
  if (!obj) { showToast('Select an object first', 'info'); return; }
  document.getElementById('pFadeSec').style.display = '';
  mobAutoOpenProps();
  _scrollPropsTo('bottom');
  showToast('Transparency: choose a fade direction', 'info');
}

// ── Fade / Transparency — rasterize + destination-out mask ───────
// Applies fade DIRECTLY on the object (not a separate overlay).
// Result is a PNG image — use Ctrl+Z to undo.
function applyFade(dir) {
  const obj = canvas.getActiveObject();
  if (!obj || obj.type === 'activeSelection') { showToast('Select one object first', 'info'); return; }
  const intensity = +document.getElementById('fadeIntensity').value / 100;

  showToast('Applying fade…', 'info');

  // 1. Capture the object UNROTATED at 2× (sharper), then restore. Capturing it
  //    rotated and then rotating the result again double-rotated it and
  //    squashed the rotated bounding box into the unrotated size.
  const savedAngle = obj.angle || 0;
  obj.set('angle', 0); obj.setCoords();
  let dataURL;
  try { dataURL = obj.toDataURL({ format: 'png', multiplier: 2 }); }   // object export is zoom-independent
  catch (e) { obj.set('angle', savedAngle); obj.setCoords(); showToast('This image cannot be edited (it is from another website)', 'err'); return; }
  const center = obj.getCenterPoint();
  obj.set('angle', savedAngle); obj.setCoords();

  const srcImg = new Image();
  srcImg.onload = () => {
    const sw = srcImg.width, sh = srcImg.height;
    // 2. Create a mask canvas, draw the object, then erase with gradient
    const maskEl = document.createElement('canvas');
    maskEl.width  = sw;
    maskEl.height = sh;
    const mctx = maskEl.getContext('2d');

    mctx.drawImage(srcImg, 0, 0, sw, sh);          // draw object
    mctx.globalCompositeOperation = 'destination-out'; // erase mode

    let grad;
    const fade = intensity;
    switch (dir) {
      case 'top':
        grad = mctx.createLinearGradient(0, 0, 0, sh);
        grad.addColorStop(0,              `rgba(0,0,0,${fade})`);
        grad.addColorStop(Math.max(0.05, 1 - fade * 0.9), 'rgba(0,0,0,0)');
        break;
      case 'bottom':
        grad = mctx.createLinearGradient(0, 0, 0, sh);
        grad.addColorStop(Math.min(0.95, fade * 0.9), 'rgba(0,0,0,0)');
        grad.addColorStop(1,              `rgba(0,0,0,${fade})`);
        break;
      case 'left':
        grad = mctx.createLinearGradient(0, 0, sw, 0);
        grad.addColorStop(0,              `rgba(0,0,0,${fade})`);
        grad.addColorStop(Math.max(0.05, 1 - fade * 0.9), 'rgba(0,0,0,0)');
        break;
      case 'right':
        grad = mctx.createLinearGradient(0, 0, sw, 0);
        grad.addColorStop(Math.min(0.95, fade * 0.9), 'rgba(0,0,0,0)');
        grad.addColorStop(1,              `rgba(0,0,0,${fade})`);
        break;
      case 'center':
        grad = mctx.createRadialGradient(sw/2, sh/2, 0, sw/2, sh/2, Math.max(sw, sh) * 0.52);
        grad.addColorStop(0,           'rgba(0,0,0,0)');
        grad.addColorStop(1 - fade * 0.45, 'rgba(0,0,0,0)');
        grad.addColorStop(1,           `rgba(0,0,0,${fade})`);
        break;
      case 'edges':
        grad = mctx.createRadialGradient(sw/2, sh/2, Math.min(sw,sh) * 0.15, sw/2, sh/2, Math.max(sw, sh) * 0.65);
        grad.addColorStop(0,   'rgba(0,0,0,0)');
        grad.addColorStop(0.5, 'rgba(0,0,0,0)');
        grad.addColorStop(1,   `rgba(0,0,0,${fade})`);
        break;
    }

    if (grad) {
      mctx.fillStyle = grad;
      mctx.fillRect(0, 0, sw, sh);
    }

    // 3. Load masked PNG as fabric.Image and replace original object (same place, angle and layer)
    fabric.Image.fromURL(maskEl.toDataURL('image/png'), newImg => {
      newImg.set({
        left: center.x, top: center.y, originX: 'center', originY: 'center',
        angle: savedAngle, scaleX: 0.5, scaleY: 0.5, opacity: obj.opacity ?? 1,
        data: Object.assign({}, obj.data, { faded: dir, _grad: null }),
      });
      hist.locked = true;
      const idx = canvas.getObjects().indexOf(obj);
      canvas.remove(obj);
      canvas.add(newImg);
      if (idx >= 0) canvas.moveTo(newImg, idx);
      hist.locked = false;
      canvas.setActiveObject(newImg);
      canvas.renderAll();
      histSave(); refreshLayers();
      showToast('Fade applied! (Ctrl+Z to undo)');
    });
  };
  srcImg.src = dataURL;
}

// No separate overlay anymore — undo reverts the rasterize operation
function removeFadeEffect() {
  showToast('Use Ctrl+Z to undo the fade effect', 'info');
}
// Keep stub so old references don't break
function _applyFadeOverlay(obj, grad) { applyFade('top'); }

// ── Color Accessibility (WCAG) ───────────────────────────────────
function hexToRgb(color) {
  if (!color) return { r:0, g:0, b:0 };
  // Handle rgba(...) strings
  const rgba = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
  if (rgba) return { r: +rgba[1], g: +rgba[2], b: +rgba[3] };
  // Handle hex
  const hex = color.replace('#', '');
  const full = hex.length === 3
    ? hex.split('').map(c => c + c).join('')
    : hex.padEnd(6, '0');
  return {
    r: parseInt(full.slice(0,2), 16),
    g: parseInt(full.slice(2,4), 16),
    b: parseInt(full.slice(4,6), 16)
  };
}

function getLuminance(color) {
  const { r, g, b } = hexToRgb(color);
  const a = [r, g, b].map(v => {
    v /= 255;
    return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
  });
  return a[0] * 0.2126 + a[1] * 0.7152 + a[2] * 0.0722;
}

function getContrastRatio(color1, color2) {
  const l1 = getLuminance(color1);
  const l2 = getLuminance(color2);
  const bright = Math.max(l1, l2);
  const dark   = Math.min(l1, l2);
  return (bright + 0.05) / (dark + 0.05);
}

function checkColorAccessibility() {
  const issues  = [];
  const bgCol = typeof canvas.backgroundColor === 'string' && canvas.backgroundColor ? canvas.backgroundColor : '#ffffff';

  canvas.getObjects().forEach(obj => {
    if (!isTextObj(obj) || obj.visible === false) return;
    if (typeof obj.fill !== 'string') return;          // gradient/pattern text can't be measured reliably
    const fg = obj.fill;
    const bg = (obj.textBackgroundColor && obj.textBackgroundColor !== 'transparent') ? obj.textBackgroundColor : _colorBehind(obj, bgCol);
    const ratio = getContrastRatio(bg, fg);
    const level = ratio >= 7 ? 'AAA' : ratio >= 4.5 ? 'AA' : ratio >= 3 ? 'AA Large' : 'Fail';
    if (ratio < 4.5) issues.push({ obj, ratio: ratio.toFixed(2), level });
  });

  // Highlight with a temporary overlay box. It used to paint red strokes onto
  // the text itself — and REMOVED the existing outline from every text that
  // passed, permanently changing the design.
  flagObjects(issues.map(i => i.obj), '#ef4444', 10000);

  if (issues.length) {
    const lines = issues.map(i => `"${(i.obj.text||'').slice(0,20)}" — ${i.ratio}:1 (${i.level})`);
    edAlertList('WCAG Accessibility Issues Found', lines.concat([{ html: '<i>Failing text is outlined in red for 10 seconds (your design is not changed). WCAG AA needs at least 4.5 : 1 contrast.</i>' }]));
    showToast(issues.length + ' contrast issue(s) found — check red outlines', 'err');
  } else {
    showToast('All text passes WCAG AA! ✓');
  }
}
/* Colour actually under a text object: the top-most solid-filled shape below
   it whose box contains the text's centre (falls back to the canvas colour). */
function _colorBehind(obj, fallback) {
  const objs = canvas.getObjects();
  const c = obj.getCenterPoint();
  for (let i = objs.indexOf(obj) - 1; i >= 0; i--) {
    const o = objs[i];
    if (o.visible === false || typeof o.fill !== 'string' || !o.fill || o.fill === 'transparent' || isTextObj(o)) continue;
    if (o.containsPoint && o.containsPoint(c, null, true)) return o.fill;
  }
  return fallback;
}

// ── Smart Templates ──────────────────────────────────────────────
function autoFitText(textObj, maxWidth, maxHeight) {
  if (!textObj) return;
  let size = textObj.fontSize || 20;
  const tmp = document.createElement('canvas');
  const ctx = tmp.getContext('2d');
  while (size > 8) {
    ctx.font = `${textObj.fontWeight || 'normal'} ${size}px "${textObj.fontFamily || 'sans-serif'}"`;
    const metrics = ctx.measureText(textObj.text || '');
    if (metrics.width <= maxWidth && size <= maxHeight) break;
    size -= 1;
  }
  textObj.set('fontSize', size);
  canvas.renderAll();
}

function autoLayoutVisitingCard() {
  const elements = canvas.getObjects();
  const nameObj  = elements.find(e => e.data?.field === 'name');
  const phoneObj = elements.find(e => e.data?.field === 'phone');
  const emailObj = elements.find(e => e.data?.field === 'email');

  const phi  = 1.618;
  const baseY = canvasH * 0.3;

  if (nameObj)  { nameObj.set({ top: baseY }); autoFitText(nameObj, canvasW * 0.8, 40); }
  if (phoneObj) phoneObj.set({ top: baseY + 30 * phi });
  if (emailObj) emailObj.set({ top: baseY + 30 * phi * 2 });

  canvas.renderAll();
  histSave();
  showToast('Auto-layout applied to visiting card');
}

// ── Align to Canvas ────────────────────────────────────────────────
function alignObj(dir) {
  const a = canvas.getActiveObject();
  if (!a) { showToast('Select an object first', 'info'); return; }
  // Uses the visible bounding box, so rotated objects and any origin work
  // (it used to change originX/Y, making rotated objects jump). With several
  // objects selected they are aligned to each other.
  const move = (o, ref) => {
    const b = o.getBoundingRect(true, true);
    let dx = 0, dy = 0;
    if (dir === 'left')    dx = ref.l - b.left;
    if (dir === 'right')   dx = ref.r - (b.left + b.width);
    if (dir === 'hcenter') dx = (ref.l + ref.r) / 2 - (b.left + b.width / 2);
    if (dir === 'top')     dy = ref.t - b.top;
    if (dir === 'bottom')  dy = ref.b - (b.top + b.height);
    if (dir === 'vcenter') dy = (ref.t + ref.b) / 2 - (b.top + b.height / 2);
    o.left += dx; o.top += dy; o.setCoords();
  };
  if (a.type === 'activeSelection') {
    const objs = a.getObjects();
    canvas.discardActiveObject();
    const bs = objs.map(o => o.getBoundingRect(true, true));
    const ref = { l: Math.min(...bs.map(b => b.left)), t: Math.min(...bs.map(b => b.top)),
                  r: Math.max(...bs.map(b => b.left + b.width)), b: Math.max(...bs.map(b => b.top + b.height)) };
    objs.forEach(o => move(o, ref));
    canvas.setActiveObject(new fabric.ActiveSelection(objs, { canvas }));
  } else {
    move(a, { l: 0, t: 0, r: canvasW, b: canvasH });
  }
  canvas.renderAll(); histSave(); refreshProps();
}
function distributeObjs(axis) {
  const a = canvas.getActiveObject();
  if (!a || a.type !== 'activeSelection' || a.size() < 3) { showToast('Select 3 or more objects to distribute', 'info'); return; }
  const objs = a.getObjects();
  canvas.discardActiveObject();
  const items = objs.map(o => ({ o, b: o.getBoundingRect(true, true) }));
  const k = axis === 'h' ? 'left' : 'top', s = axis === 'h' ? 'width' : 'height';
  items.sort((p, q) => p.b[k] - q.b[k]);
  const start = items[0].b[k], last = items[items.length - 1];
  const gap = (last.b[k] + last.b[s] - start - items.reduce((t, i) => t + i.b[s], 0)) / (items.length - 1);
  let pos = start;
  items.forEach(i => { const d = pos - i.b[k]; if (axis === 'h') i.o.left += d; else i.o.top += d; i.o.setCoords(); pos += i.b[s] + gap; });
  canvas.setActiveObject(new fabric.ActiveSelection(objs, { canvas }));
  canvas.renderAll(); histSave();
  showToast('Distributed evenly');
}

// ── BG Remove (magic eraser for images) ─────────────────────────────
function removeBgFromImage(tolerance) {
  const obj = canvas.getActiveObject();
  if (!obj || obj.type !== 'image') { showToast('Select an image first', 'err'); return; }
  showToast('Removing background...', 'info');

  setTimeout(() => {
    // Work on the image at its ORIGINAL resolution (the old version rendered it
    // at on-screen size, permanently lowering the photo's quality).
    const el = obj._originalElement || obj.getElement();   // unfiltered source, so filters aren't applied twice
    const iw = el.naturalWidth || el.width, ih = el.naturalHeight || el.height;
    const pc = document.createElement('canvas');
    pc.width = iw; pc.height = ih;
    const ctx = pc.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(el, 0, 0, iw, ih);
    let id;
    try { id = ctx.getImageData(0, 0, iw, ih); }
    catch (e) { showToast('This image is from another website and cannot be edited — download it and upload it instead.', 'err'); return; }
    const d = id.data;

    // Background colour = average of the 4 corners.
    const corners = [[0,0],[iw-1,0],[0,ih-1],[iw-1,ih-1]];
    let bgR=0, bgG=0, bgB=0;
    corners.forEach(([x,y]) => { const i=(y*iw+x)*4; bgR+=d[i]; bgG+=d[i+1]; bgB+=d[i+2]; });
    bgR/=4; bgG/=4; bgB/=4;
    const tol = tolerance * 3;
    const isBg = i => d[i+3] > 0 && Math.abs(d[i]-bgR)+Math.abs(d[i+1]-bgG)+Math.abs(d[i+2]-bgB) < tol;

    // Flood-fill from the image border: only background CONNECTED to the edges
    // is removed. The old global colour match also punched holes inside the
    // subject (white shirts, eyes, teeth in an ID photo).
    const seen = new Uint8Array(iw * ih);
    const stack = new Int32Array(iw * ih);   // each pixel is pushed at most once
    let sp = 0;
    const push = (x, y) => { const p = y * iw + x; if (!seen[p] && isBg(p * 4)) { seen[p] = 1; stack[sp++] = p; } };
    for (let x = 0; x < iw; x++) { push(x, 0); push(x, ih - 1); }
    for (let y = 0; y < ih; y++) { push(0, y); push(iw - 1, y); }
    let removed = 0;
    while (sp > 0) {
      const p = stack[--sp], x = p % iw, y = (p / iw) | 0;
      d[p * 4 + 3] = 0; removed++;
      if (x > 0) push(x - 1, y); if (x < iw - 1) push(x + 1, y);
      if (y > 0) push(x, y - 1); if (y < ih - 1) push(x, y + 1);
    }
    if (!removed) { showToast('No plain background found at the edges — try a higher tolerance', 'info'); return; }
    // Soften the cut edge: half-transparent rim pixels next to removed ones.
    for (let y = 1; y < ih - 1; y++) for (let x = 1; x < iw - 1; x++) {
      const p = y * iw + x;
      if (seen[p]) continue;
      if (seen[p-1] || seen[p+1] || seen[p-iw] || seen[p+iw]) d[p*4+3] = Math.min(d[p*4+3], 170);
    }
    ctx.putImageData(id, 0, 0);

    const newImg = new Image();
    newImg.onload = () => {
      // Same element size → every transform (scale, angle, flip, crop, clip mask, filters) stays valid.
      obj.setElement(newImg, { width: obj.width, height: obj.height });   // keep any crop window
      obj.dirty = true;
      canvas.renderAll();
      histSave(); showToast('Background removed! (Ctrl+Z to undo)');
    };
    newImg.src = pc.toDataURL('image/png');
  }, 30);
}

// ── Eyedropper / Color Picker ────────────────────────────────────────
let eyedropperActive = false;
let lastPickedColor  = '#ffffff';

function toggleEyedropper() {
  // Native EyeDropper (Chrome/Edge) can pick ANY colour on screen, exactly.
  if (!eyedropperActive && window.EyeDropper) {
    const btn = document.getElementById('tEye');
    if (btn) btn.classList.add('active');
    new window.EyeDropper().open().then(r => _setPickedColor(r.sRGBHex)).catch(() => {}).finally(() => { if (btn) btn.classList.remove('active'); });
    return;
  }
  eyedropperActive = !eyedropperActive;
  document.body.classList.toggle('eyedrop-mode', eyedropperActive);
  const btn = document.getElementById('tEye');
  if (btn) btn.classList.toggle('active', eyedropperActive);
  if (eyedropperActive) {
    if (canvas.isDrawingMode) setActiveTool('select');
    eyedropperActive = true; document.body.classList.add('eyedrop-mode');
    canvas.skipTargetFind = true;
    showToast('Click on the canvas to pick a color (Esc to cancel)', 'info');
  } else canvas.skipTargetFind = false;
}
function _setPickedColor(hex) {
  lastPickedColor = colorToHex(hex);
  const swatch = document.getElementById('eyeColorSwatch');
  const hexEl  = document.getElementById('eyeColorHex');
  const row    = document.getElementById('eyeColorRow');
  if (swatch) swatch.style.background = lastPickedColor;
  if (hexEl)  hexEl.textContent = lastPickedColor;
  if (row)    row.style.display = '';
  // Apply straight away to whatever is selected; otherwise keep it for "Apply".
  if (canvas.getActiveObjects().length) { applyPickedColor(); return; }
  document.getElementById('pCanvas').style.display = '';
  document.getElementById('pObj').style.display = 'none';
  try { navigator.clipboard && navigator.clipboard.writeText(lastPickedColor); } catch (_) {}
  showToast('Color picked: ' + lastPickedColor + ' (copied) — select an object and press Apply');
}

function applyPickedColor() {
  const objs = canvas.getActiveObjects();
  if (!objs.length) { showToast('Select an object first', 'info'); return; }
  objs.forEach(obj => {
    obj.set('fill', lastPickedColor);
    if (obj.data && obj.data._grad) obj.data._grad = null;
  });
  if (isTextObj(objs[0])) document.getElementById('pTextColor').value = lastPickedColor;
  else document.getElementById('pFill').value = lastPickedColor;
  canvas.renderAll(); histSave();
  showToast('Color applied: ' + lastPickedColor);
}

canvas.on('mouse:down', opt => {
  if (!eyedropperActive) return;
  const canvasEl = canvas.lowerCanvasEl;
  const rect = canvasEl.getBoundingClientRect();
  // opt.e is the raw event — on touch devices Fabric still fires 'mouse:down' but
  // opt.e is a TouchEvent, which has no top-level clientX/clientY (only touches[0] does)
  const _pt = opt.e.touches && opt.e.touches.length ? opt.e.touches[0] : (opt.e.changedTouches && opt.e.changedTouches[0]) || opt.e;
  const pixX = Math.round((_pt.clientX - rect.left) * (canvasEl.width  / rect.width));
  const pixY = Math.round((_pt.clientY - rect.top)  * (canvasEl.height / rect.height));
  // Deactivate first so a failure below never leaves the tool stuck on.
  eyedropperActive = false;
  canvas.skipTargetFind = false;
  document.body.classList.remove('eyedrop-mode');
  document.getElementById('tEye')?.classList.remove('active');
  let px;
  try { px = canvasEl.getContext('2d').getImageData(Math.max(0,pixX), Math.max(0,pixY), 1, 1).data; }
  catch (e) { showToast('Cannot read colours from an image of another website', 'err'); return; }
  _setPickedColor('#' + [px[0],px[1],px[2]].map(v => v.toString(16).padStart(2,'0')).join(''));
  opt.e.stopPropagation();
});

// ── Online Photos / Stickers ─────────────────────────────────────────
const STICKER_API = '<?= BASE_URL ?>/api/sticker_actions.php';
const STICKER_URL = '<?= BASE_URL ?>/assets/uploads/stickers/';
let stickerCatActive = 0;

async function openStickerModal() {
  closeEdPanel();
  document.getElementById('stickerModal').style.display = 'flex';
  await loadStickerCats();
}

async function loadStickerCats() {
  const fd = new FormData();
  fd.append('action', 'list_cats');
  fd.append('csrf_token', ED.csrf);
  try {
    const r = await idmFetch(STICKER_API, { method:'POST', body:fd }, 20000);
    const d = await r.json();
    if (!d.success) return;
    const cats = d.cats || [];
    const el = document.getElementById('stickerCats');
    el.innerHTML = '';
    cats.forEach((cat, idx) => {
      const btn = document.createElement('button');
      btn.className = 'sticker-cat-btn' + (idx === 0 ? ' active' : '');
      btn.textContent = cat.name;
      btn.onclick = () => {
        document.querySelectorAll('.sticker-cat-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        loadStickers(cat.id);
      };
      el.appendChild(btn);
    });
    if (cats.length > 0) loadStickers(cats[0].id);
    else { document.getElementById('stickerGrid').innerHTML = ''; document.getElementById('stickerEmpty').style.display = ''; }
  } catch(e) { showToast('Sticker failed to load', 'err'); }
}

async function loadStickers(catId) {
  stickerCatActive = catId;
  document.getElementById('stickerGrid').innerHTML = '';
  document.getElementById('stickerEmpty').style.display = 'none';
  document.getElementById('stickerLoading').style.display = '';

  const fd = new FormData();
  fd.append('action',     'list_stickers');
  fd.append('cat_id',     catId);
  fd.append('csrf_token', ED.csrf);
  try {
    const r = await idmFetch(STICKER_API, { method:'POST', body:fd }, 20000);
    const d = await r.json();
    document.getElementById('stickerLoading').style.display = 'none';
    const stickers = d.stickers || [];
    if (!d.success || !stickers.length) {
      document.getElementById('stickerEmpty').style.display = ''; return;
    }
    const grid = document.getElementById('stickerGrid');
    stickers.forEach(s => {
      const img = document.createElement('img');
      img.src = STICKER_URL + encodeURIComponent(s.filename);
      img.title = s.name || s.filename;
      img.className = 'sticker-thumb';
      img.loading = 'lazy';
      img.onclick = () => addStickerToCanvas(img.src, s.name);
      grid.appendChild(img);
    });
  } catch(e) {
    document.getElementById('stickerLoading').style.display = 'none';
    showToast('Sticker failed to load', 'err');
  }
}

function addStickerToCanvas(src, name) {
  document.getElementById('stickerModal').style.display = 'none';
  showToast('Adding sticker…', 'info');
  const imgEl = new Image();
  imgEl.crossOrigin = 'anonymous';
  imgEl.onload = () => {
    const fImg = new fabric.Image(imgEl);
    const maxW = canvasW * 0.4, maxH = canvasH * 0.4;
    if (fImg.width > maxW)            fImg.scaleToWidth(maxW);
    if (fImg.getScaledHeight() > maxH) fImg.scaleToHeight(maxH);
    fImg.set({ left:canvasW/2, top:canvasH/2, originX:'center', originY:'center' });
    canvas.add(fImg).setActiveObject(fImg).renderAll();
    showToast((name || 'Sticker') + ' added!');
  };
  imgEl.onerror = () => showToast('Sticker failed to load', 'err');
  imgEl.src = src;
}

// ── Font select ──────────────────────────────────────────────────────
// Grouped font list — including Hindi / Devanagari fonts (school names, mottos
// like "विद्या ददाति विनयम्" need a font that actually has Devanagari glyphs).
const FONT_GROUPS = {
  'हिंदी / Devanagari': ['Noto Sans Devanagari','Hind','Mukta','Baloo 2','Kalam','Rozha One','Tiro Devanagari Hindi','Yatra One'],
  'Sans-serif': ['Noto Sans','Roboto','Open Sans','Lato','Montserrat','Poppins','Raleway','Oswald'],
  'Serif': ['Playfair Display','Merriweather','Cinzel','Times New Roman','Georgia'],
  'Display': ['Bebas Neue','Impact'],
  'Script / handwriting': ['Dancing Script','Great Vibes','Pacifico'],
  'System': ['Arial','Verdana','Tahoma','Trebuchet MS','Courier New'],
};
function populateFonts() {
  const sel = document.getElementById('pFont');
  Object.entries(FONT_GROUPS).forEach(([g, fonts]) => {
    const og = document.createElement('optgroup'); og.label = g;
    fonts.forEach(f => { const o = document.createElement('option'); o.value = o.textContent = f; o.style.fontFamily = `'${f}'`; og.appendChild(o); });
    sel.appendChild(og);
  });
}

// ── Crop Tool ──────────────────────────────────────────────────────
/* Crop tool. With a PHOTO selected it now crops just that photo (what most
   people expect); with nothing / another object selected it crops the whole
   design to the box (flattening it, as before — with a warning). */
let _cropTarget = null;
function _endCropUI() {
  isCropping = false; cropRect = null; _cropTarget = null;
  document.getElementById('btnApplyCrop').style.display = 'none';
  document.getElementById('btnCropMode').classList.remove('active');
  document.getElementById('tCrop')?.classList.remove('active');
  document.body.classList.remove('crop-mode');
}
function toggleCropMode() {
  if (isCropping) {
    if (cropRect) { hist.locked = true; canvas.remove(cropRect); hist.locked = false; }
    if (_cropTarget) { _cropTarget.set({ selectable: true, evented: true }); canvas.setActiveObject(_cropTarget); }
    _endCropUI();
    canvas.renderAll(); refreshLayers();
    return;
  }
  const sel = canvas.getActiveObject();
  _cropTarget = sel && sel.type === 'image' ? sel : null;
  isCropping = true;
  let box = { left: canvasW * 0.1, top: canvasH * 0.1, width: canvasW * 0.8, height: canvasH * 0.8 };
  if (_cropTarget) {
    const b = _cropTarget.getBoundingRect(true, true);
    box = { left: b.left + b.width * 0.05, top: b.top + b.height * 0.05, width: b.width * 0.9, height: b.height * 0.9 };
    _cropTarget.set({ selectable: false, evented: false });
  }
  cropRect = new fabric.Rect({
    ...box,
    fill: 'rgba(0,0,0,0.2)', stroke: '#f59e0b', strokeWidth: 2, strokeUniform: true,
    strokeDashArray: [8,4], selectable: true, hasRotatingPoint: false, lockRotation: true,
    cornerColor: '#f59e0b', cornerStrokeColor: '#f59e0b', cornerSize: 10,
    data: { isCropOverlay: true }
  });
  cropRect.setControlsVisibility({ mtr: false });
  hist.locked = true;          // the crop box is a temporary helper — never an undo step
  canvas.add(cropRect);
  hist.locked = false;
  canvas.setActiveObject(cropRect);
  document.getElementById('btnApplyCrop').style.display = '';
  document.getElementById('btnCropMode').classList.add('active');
  document.getElementById('tCrop')?.classList.add('active');
  document.body.classList.add('crop-mode');
  canvas.renderAll();
  showToast(_cropTarget ? 'Resize the box over the photo, then click "Apply Crop" (Esc cancels)' : 'Resize the crop area, then click "Apply Crop" — tip: select a photo first to crop only that photo', 'info');
}

function applyCrop() {
  if (!cropRect) return;
  if (_cropTarget) { _cropImage(); return; }
  // This flattens the WHOLE design (every text/shape/image layer) into one
  // picture and permanently resizes the canvas to the crop box. Confirm first.
  edConfirm(
    'This resizes the WHOLE canvas to the crop area and flattens all text/shapes/images into a single picture — you won\'t be able to edit them separately afterward (Ctrl+Z undoes it). To crop only one photo, select that photo first. Continue?',
    () => _doApplyCrop(),
    () => {}
  );
}

/* Non-destructive photo crop via Fabric's cropX/cropY — the full image is kept,
   so it can be re-cropped later. Handles rotated/flipped/scaled photos. */
function _cropImage() {
  const img = _cropTarget, r = cropRect;
  const b = r.getBoundingRect(true, true);
  const m = fabric.util.invertTransform(img.calcTransformMatrix());
  const corners = [[b.left, b.top], [b.left + b.width, b.top], [b.left, b.top + b.height], [b.left + b.width, b.top + b.height]]
    .map(([x, y]) => fabric.util.transformPoint({ x, y }, m));
  const w = img.width, h = img.height;
  // local coords are centred on the image; convert to 0..w / 0..h within the current crop window
  let x0 = Math.max(0, Math.min(...corners.map(p => p.x)) + w / 2), x1 = Math.min(w, Math.max(...corners.map(p => p.x)) + w / 2);
  let y0 = Math.max(0, Math.min(...corners.map(p => p.y)) + h / 2), y1 = Math.min(h, Math.max(...corners.map(p => p.y)) + h / 2);
  if (x1 - x0 < 2 || y1 - y0 < 2) { showToast('Crop box must overlap the photo', 'err'); return; }
  // centre of the kept region, in canvas coordinates (so the photo doesn't move)
  const keepCenter = fabric.util.transformPoint({ x: (x0 + x1) / 2 - w / 2, y: (y0 + y1) / 2 - h / 2 }, img.calcTransformMatrix());
  hist.locked = true;
  canvas.remove(r);
  img.set({ cropX: (img.cropX || 0) + x0, cropY: (img.cropY || 0) + y0, width: x1 - x0, height: y1 - y0, selectable: true, evented: true });
  img.setPositionByOrigin(keepCenter, 'center', 'center');
  if (img.clipPath) img.set('clipPath', null);   // old mask was sized for the uncropped photo
  img.setCoords(); img.dirty = true;
  hist.locked = false;
  const t = img;
  _endCropUI();
  canvas.setActiveObject(t).renderAll();
  histSave(); refreshLayers();
  showToast('Photo cropped! (Ctrl+Z to undo)');
}

function _doApplyCrop() {
  const b = cropRect.getBoundingRect(true, true);
  const cLeft   = Math.max(0, Math.round(b.left));
  const cTop    = Math.max(0, Math.round(b.top));
  const cWidth  = Math.min(canvasW - cLeft, Math.round(b.width));
  const cHeight = Math.min(canvasH - cTop,  Math.round(b.height));
  if (cWidth < 10 || cHeight < 10) { showToast('Crop area is too small', 'err'); return; }

  hist.locked = true;
  canvas.remove(cropRect);
  hist.locked = false;
  canvas.discardActiveObject();

  // Reset viewport temporarily for accurate pixel export
  const savedVpt = canvas.viewportTransform.slice();
  canvas.setViewportTransform([1,0,0,1,0,0]);
  let dataURL;
  try {
    dataURL = canvas.toDataURL({ format: 'png', multiplier: 1, left: cLeft, top: cTop, width: cWidth, height: cHeight });
  } catch (e) {
    canvas.setViewportTransform(savedVpt); _endCropUI(); canvas.renderAll();
    showToast('Crop blocked: an image from another website has no edit permission', 'err'); return;
  }
  canvas.setViewportTransform(savedVpt);

  fabric.Image.fromURL(dataURL, (img) => {
    hist.locked = true;
    canvas.clear();
    canvas.setBackgroundColor('', () => {});
    resizeCanvasTo(cWidth, cHeight);
    img.set({ left:0, top:0, originX:'left', originY:'top', selectable:false, evented:false, data:{ isBg:true } });
    canvas.add(img);
    hist.locked = false;
    setZoom('fit');
    canvas.renderAll();
    histSave(); refreshLayers();
    _endCropUI();
    showToast('Crop applied! ' + cWidth + ' × ' + cHeight + ' px');
  });
}

// ── Smart Snapping ─────────────────────────────────────────────────
// Snaps to the canvas centre/edges AND to other objects' edges/centres.
// Guides are drawn by the after:render overlay — no temporary objects.
canvas.on('object:moving', function(e) {
  const obj = e.target;
  if (obj.data?.isCropOverlay) return;
  snapGuides = [];
  if (!snapEnabled) return;
  const z = canvas.getZoom() || 1;
  const snapT = 7 / z;
  const b = obj.getBoundingRect(true, true);
  const xs = [b.left, b.left + b.width / 2, b.left + b.width];
  const ys = [b.top, b.top + b.height / 2, b.top + b.height];
  const tx = [0, canvasW / 2, canvasW], ty = [0, canvasH / 2, canvasH];
  const members = obj.type === 'activeSelection' ? obj.getObjects() : [obj];
  const others = canvas.getObjects().filter(o => !members.includes(o) && o.visible !== false && !o.data?.isCropOverlay && !o.data?.isBg && !o.data?.eraserPath);
  if (others.length <= 150) others.forEach(o => {
    const ob = o.getBoundingRect(true, true);
    tx.push(ob.left, ob.left + ob.width / 2, ob.left + ob.width);
    ty.push(ob.top, ob.top + ob.height / 2, ob.top + ob.height);
  });
  let best = null;
  xs.forEach(v => tx.forEach(t => { const d = t - v; if (Math.abs(d) < snapT && (!best || Math.abs(d) < Math.abs(best.d))) best = { d, t }; }));
  if (best) { obj.left += best.d; snapGuides.push({ t: 'v', p: best.t }); }
  best = null;
  ys.forEach(v => ty.forEach(t => { const d = t - v; if (Math.abs(d) < snapT && (!best || Math.abs(d) < Math.abs(best.d))) best = { d, t }; }));
  if (best) { obj.top += best.d; snapGuides.push({ t: 'h', p: best.t }); }
  if (snapGuides.length) obj.setCoords();
});

function showSnapGuide(type, pos) { snapGuides.push({ t: type, p: pos }); canvas.requestRenderAll(); }
function _clearSnapGuides() { if (snapGuides.length) { snapGuides = []; canvas.requestRenderAll(); } }

// Clear snap guides when object drag ends
canvas.on('object:modified', _clearSnapGuides);
canvas.on('mouse:up', _clearSnapGuides);

// ── Fix: mouseup outside canvas leaves Fabric in stuck-drag state ─────
// When user releases mouse over properties panel / toolbar, Fabric.js
// never receives a mouseup on the canvas, so _currentTransform stays set
// and the next mousemove drags the object. Reset it here.
document.addEventListener('mouseup', (e) => {
  if (canvas._currentTransform && !canvas.upperCanvasEl.contains(e.target)) {
    const t = canvas._currentTransform.target;
    canvas._currentTransform = null;
    if (t) { t.setCoords(); canvas.fire('object:modified', { target: t }); }   // the drag that happened is still an undo step
    canvas.renderAll();
  }
  _clearSnapGuides();
}, true /* capture phase — fires before Fabric's own handlers */);

// ── Text Effects ─────────────────────────────────────────────────────
function applyTextEffect(effect) {
  const obj = canvas.getActiveObject();
  if (!obj || !['i-text','text','textbox'].includes(obj.type)) {
    showToast('Select a text object first', 'err'); return;
  }
  switch(effect) {
    case 'neon':
      obj.set('shadow', new fabric.Shadow({
        color: typeof obj.fill === 'string' ? obj.fill : '#4f46e5',
        blur:20, offsetX:0, offsetY:0
      }));
      break;
    case '3d':
      obj.set('shadow', new fabric.Shadow({ color:'rgba(0,0,0,0.35)', blur:0, offsetX:5, offsetY:5 }));
      break;
    case 'outline':
      obj.set({ stroke:'#000000', strokeWidth:2, paintFirst:'stroke' });
      break;
    case 'glow':
      obj.set('shadow', new fabric.Shadow({ color:'#fbbf24', blur:18, offsetX:0, offsetY:0 }));
      break;
    case 'emboss':
      obj.set('shadow', new fabric.Shadow({ color:'rgba(255,255,255,0.6)', blur:1, offsetX:-2, offsetY:-2 }));
      break;
    case 'clear':
      obj.set({ shadow:null, stroke:'transparent', strokeWidth:0, paintFirst:'fill' });
      break;
  }
  canvas.renderAll(); histSave(); refreshProps(obj);
  showToast(effect === 'clear' ? 'Effects cleared!' : effect.charAt(0).toUpperCase()+effect.slice(1)+' effect applied!');
}

// ── Curved Text Path ──────────────────────────────────────────────────
function openCurvedTextModal() {
  closeEdPanel();
  document.getElementById('curvedTextModal').style.display = 'flex';
  setTimeout(() => document.getElementById('ctTextInput').focus(), 50);
}

function applyCurvedText() {
  const text   = document.getElementById('ctTextInput').value.trim() || 'Curved Text';
  const radius = Math.max(50, parseInt(document.getElementById('ctRadius').value, 10) || 150);
  const fs     = Math.max(8,  parseInt(document.getElementById('ctFontSize').value, 10) || 24);
  const isTop  = document.getElementById('ctSide').value === 'top';
  document.getElementById('curvedTextModal').style.display = 'none';
  addCurvedText(text, radius, isTop, fs);
}

function addCurvedText(text, radius, isTop, fontSize) {
  fontSize = fontSize || 24;
  const path = new fabric.Path(
    isTop
      ? `M ${-radius},0 A ${radius},${radius} 0 0,1 ${radius},0`
      : `M ${-radius},0 A ${radius},${radius} 0 0,0 ${radius},0`,
    { fill:'transparent', stroke:'transparent' }
  );
  const textObj = new fabric.Text(text, {
    left: canvasW / 2, top: canvasH / 2,
    originX:'center', originY:'center',
    path: path, fontSize: fontSize,
    fontFamily:'Noto Sans', fill:'#1f2937',
    pathSide: isTop ? 'left' : 'right',
    pathStartOffset: 50,
  });
  canvas.add(textObj).setActiveObject(textObj).renderAll();
  histSave(); showToast('Curved text added!');
}

// ── Vertical Text ─────────────────────────────────────────────────────
function addVerticalText() {
  const t = new fabric.IText('Vertical', {
    left: canvasW / 2, top: canvasH / 2,
    originX:'center', originY:'center',
    fontSize:30, fontFamily:'Noto Sans', fill:'#1f2937',
    angle: 90,
  });
  canvas.add(t).setActiveObject(t).renderAll();
  histSave(); showToast('Vertical text added! Double-click to edit.');
}

// ── Bullet List ───────────────────────────────────────────────────────
function promptBulletList() {
  edPromptModal('Bullet List', [
    {id:'raw', label:'Bullet points (one per line)', type:'textarea', value:'First item\nSecond item\nThird item'},
  ], vals => {
    const raw = vals.raw || '';
    const items = raw.split('\n').map(s => s.trim()).filter(s => s.length > 0);
    if (!items.length) return;
    addBulletList(items);
  });
}

function addBulletList(items) {
  const text = items.map(item => '• ' + item).join('\n');
  const t = new fabric.Textbox(text, {
    left: canvasW / 2, top: canvasH / 2,
    originX:'center', originY:'center',
    fontSize:18, fontFamily:'Noto Sans', fill:'#1f2937',
    lineHeight:1.75, width:280,
  });
  canvas.add(t).setActiveObject(t).renderAll();
  histSave(); showToast('Bullet list added!');
}

// ── Clip Mask ─────────────────────────────────────────────────────────
function applyClipMask(shape) {
  const obj = canvas.getActiveObject();
  if (!obj || obj.type !== 'image') { showToast('Select an image first', 'err'); return; }
  const w = obj.getScaledWidth(), h = obj.getScaledHeight();
  let clipPath;
  if (shape === 'circle') {
    clipPath = new fabric.Circle({ radius: Math.min(w,h)/2, originX:'center', originY:'center' });
  } else if (shape === 'rounded') {
    const r = Math.min(w,h) * 0.12;
    clipPath = new fabric.Rect({ width:w, height:h, rx:r, ry:r, originX:'center', originY:'center' });
  } else if (shape === 'star') {
    clipPath = new fabric.Polygon(
      starPoints(0, 0, Math.min(w,h)/2, Math.min(w,h)/4, 5),
      { originX:'center', originY:'center' }
    );
  } else if (shape === 'arch') {
    const r = w / 2;
    clipPath = new fabric.Path(
      `M ${-w/2} ${h/2} L ${-w/2} 0 A ${r} ${r} 0 0 1 ${w/2} 0 L ${w/2} ${h/2} Z`,
      { originX:'center', originY:'center' }
    );
  } else if (shape === 'triangle') {
    clipPath = new fabric.Triangle({ width:w, height:h, originX:'center', originY:'center' });
  } else if (shape === 'diamond') {
    clipPath = new fabric.Polygon(
      [{x:0,y:-h/2},{x:w/2,y:0},{x:0,y:h/2},{x:-w/2,y:0}],
      { originX:'center', originY:'center' }
    );
  } else if (shape === 'hexagon') {
    const polyN = (n, rx, ry) => {
      const pp = [];
      for (let i = 0; i < n; i++) {
        const a = (i * 2 * Math.PI / n) - Math.PI / 2;
        pp.push({ x: rx * Math.cos(a), y: ry * Math.sin(a) });
      }
      return pp;
    };
    clipPath = new fabric.Polygon(polyN(6, w/2, h/2), { originX:'center', originY:'center' });
  } else if (shape === 'heart') {
    const hs = Math.min(w, h) / 2;
    clipPath = new fabric.Path(
      `M 0,${-hs*0.4} C ${-hs*0.05},${-hs*0.7} ${-hs},${-hs*0.7} ${-hs},${-hs*0.2} C ${-hs},${hs*0.3} 0,${hs*0.7} 0,${hs} C 0,${hs*0.7} ${hs},${hs*0.3} ${hs},${-hs*0.2} C ${hs},${-hs*0.7} ${hs*0.05},${-hs*0.7} 0,${-hs*0.4} Z`,
      { originX:'center', originY:'center' }
    );
  }
  if (!clipPath) return;
  obj.set('clipPath', clipPath);
  canvas.renderAll(); histSave();
  showToast('Clip mask applied!');
}

function removeClipMask() {
  const obj = canvas.getActiveObject();
  if (!obj || obj.type !== 'image') { showToast('Select an image first', 'err'); return; }
  obj.set('clipPath', null);
  canvas.renderAll(); histSave();
  showToast('Clip mask removed!');
}

// ── Realistic Shadow + Reflection ─────────────────────────────────────
function addRealisticShadow() {
  const obj = canvas.getActiveObject();
  if (!obj) { showToast('Select an object first', 'err'); return; }
  obj.set('shadow', new fabric.Shadow({ color:'rgba(0,0,0,0.3)', blur:20, offsetX:10, offsetY:15 }));
  canvas.renderAll();
  obj.clone(clone => {
    const b = obj.getBoundingRect(true, true);
    clone.set({ flipY: !obj.flipY, opacity: 0.22, selectable: true, evented: true, shadow: null, data: Object.assign({}, obj.data, { name: 'Reflection' }) });
    clone.setCoords();
    const cb = clone.getBoundingRect(true, true);
    clone.top += (b.top + b.height + 4) - cb.top;   // directly under the original, whatever its origin/angle
    clone.setCoords();
    hist.locked = true; canvas.add(clone); hist.locked = false;
    canvas.moveTo(clone, Math.max(0, canvas.getObjects().indexOf(obj)));
    canvas.renderAll(); histSave(); refreshLayers();
    showToast('Realistic shadow & reflection added!');
  });
}

// ── Print Guides (Bleed / Safe Area) ─────────────────────────────────
// mm → design pixels at the DESIGN's DPI (it always used 96, so on a 300-DPI
// card the bleed/safe guides were ~3× too thin).
function mmToPx(mm) { return Math.round(mm * (_ncCurrentDpi || SCREEN_DPI) / 25.4); }

function togglePrintGuides() {
  printGuidesOn = !printGuidesOn;
  document.getElementById('btnPrintGuides').classList.toggle('active', printGuidesOn);
  canvas.requestRenderAll();
  showToast(printGuidesOn ? 'Print guides ON — Red: 3 mm cut/bleed line, Green: 5 mm safe area for text' : 'Print guides OFF', 'info');
}
// Kept for older callers — guides are an overlay now (see after:render).
function showPrintGuides() { printGuidesOn = true; canvas.requestRenderAll(); }
function hidePrintGuides() { printGuidesOn = false; canvas.requestRenderAll(); }

// ── Resolution Check ──────────────────────────────────────────────────
function checkImageResolution() {
  const dpi = _ncCurrentDpi || SCREEN_DPI;
  const low = [], lines = [];
  const walk = obj => {
    if (obj.type === 'group') { obj.getObjects().forEach(walk); return; }
    if (obj.type !== 'image' || obj.data?.isBg && obj.data?.flattened) return;
    const el = obj._originalElement || obj.getElement();
    const nativeW = obj.width || el?.naturalWidth || 1;              // source pixels (after crop)
    const printedIn = obj.getScaledWidth() / dpi;                     // inches on paper at the design DPI
    const eff = Math.round(nativeW / Math.max(printedIn, 0.0001));
    if (eff < 150) { low.push(obj); lines.push('• ' + (_layerLabel(obj)) + ' — ~' + eff + ' DPI (300+ recommended for print)'); }
  };
  canvas.getObjects().forEach(walk);
  flagObjects(low.filter(o => canvas.contains(o)), '#ef4444', 10000);
  if (lines.length) {
    showToast(lines.length + ' low-res image(s) detected!', 'err');
    edAlertList('Low Resolution Images', lines.concat([{ html: '<i>Outlined in red for 10 seconds (your design is not changed). Replace them with larger photos for sharp printing.</i>' }]));
  } else {
    showToast('All images are print-ready!');
  }
}

// ── Export Print-Ready (bleed + crop marks) ───────────────────────────
// The old version drew the crop marks exactly ON the outer edge pointing
// outward — i.e. off the image, so they were never visible. Now the file has
// a white margin with real crop marks at the trim corners, plus 3 mm bleed.
function exportPrintReady() {
  canvas.discardActiveObject().renderAll();
  const mult = _printMultiplier();
  let baseURL;
  try { baseURL = exportDataURL({ format:'png', multiplier: mult }); }
  catch (e) { showToast('Export blocked: an image from another website has no download permission.', 'err'); return; }
  const bleed = mmToPx(BLEED_MM) * mult, margin = mmToPx(10) * mult;
  const W = Math.round(canvasW * mult), H = Math.round(canvasH * mult);
  const ec = document.createElement('canvas');
  ec.width = Math.round(W + margin * 2); ec.height = Math.round(H + margin * 2);
  const ctx = ec.getContext('2d');
  ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, ec.width, ec.height);

  const img = new Image();
  img.onload = () => {
    // Bleed: stretch the outermost pixels of the design outward by 3 mm.
    const x = margin, y = margin, b = bleed;
    ctx.drawImage(img, 0, 0, 1, H, x - b, y, b, H);           // left
    ctx.drawImage(img, W - 1, 0, 1, H, x + W, y, b, H);       // right
    ctx.drawImage(img, 0, 0, W, 1, x, y - b, W, b);           // top
    ctx.drawImage(img, 0, H - 1, W, 1, x, y + H, W, b);       // bottom
    ctx.drawImage(img, 0, 0, 1, 1, x - b, y - b, b, b);
    ctx.drawImage(img, W - 1, 0, 1, 1, x + W, y - b, b, b);
    ctx.drawImage(img, 0, H - 1, 1, 1, x - b, y + H, b, b);
    ctx.drawImage(img, W - 1, H - 1, 1, 1, x + W, y + H, b, b);
    ctx.drawImage(img, x, y);
    // Crop marks at the trim corners, in the white margin.
    ctx.strokeStyle = '#000'; ctx.lineWidth = Math.max(1, mult * 0.75);
    const L = margin - bleed - 2 * mult, gap = bleed + 2 * mult;
    [[x, y, -1, -1], [x + W, y, 1, -1], [x, y + H, -1, 1], [x + W, y + H, 1, 1]].forEach(([cx, cy, sx, sy]) => {
      ctx.beginPath(); ctx.moveTo(cx + sx * gap, cy); ctx.lineTo(cx + sx * (gap + L), cy); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(cx, cy + sy * gap); ctx.lineTo(cx, cy + sy * (gap + L)); ctx.stroke();
    });
    ec.toBlob(blob => {
      if (!blob) { showToast('Export failed — design too large', 'err'); return; }
      _downloadBlob(blob, _fileBase() + '-print-ready.png');
      showToast('Print-ready export complete! (3 mm bleed + crop marks)');
    }, 'image/png');
  };
  img.onerror = () => showToast('Export failed', 'err');
  img.src = baseURL;
}

// ── CMYK Preview (simulation) ──────────────────────────────────────────
// Now a view-only screen filter over the whole canvas. It used to push a tint
// filter into every photo — which got SAVED and EXPORTED if left on, and did
// nothing for text/shapes.
function toggleCMYKPreview() {
  const btn  = document.getElementById('btnCMYK');
  const wrap = document.getElementById('canvasWrap');
  const on = !wrap.classList.contains('cmyk-sim');
  wrap.classList.toggle('cmyk-sim', on);
  if (btn) { btn.classList.toggle('active', on); btn.style.background = on ? '#4f46e5' : '#334155'; }
  showToast('CMYK Preview ' + (on ? 'ON (approximate print colours — view only)' : 'OFF'), 'info');
}

// ── Spot Color / Pantone ───────────────────────────────────────────────
const spotColors = {
  'Pantone 185 C':'#E4002B', 'Pantone 286 C':'#0033A0',
  'Pantone 354 C':'#00A550', 'Pantone 123 C':'#FFC72C', 'Pantone Black':'#2D2926'
};

function applySpotColor(name) {
  const objs = canvas.getActiveObjects();
  if (!objs.length) { showToast('Select an object first', 'err'); return; }
  const hex = spotColors[name]; if (!hex) return;
  objs.forEach(obj => obj.set({ fill: hex, data: { ...obj.data, spotColor: name, _grad: null } }));
  canvas.renderAll(); histSave(); refreshProps();
  showToast(name + ' color applied!');
}

// ── Mockup Preview ─────────────────────────────────────────────────────
function openMockupModal() {
  closeEdPanel();
  let dataURL;
  try { dataURL = exportDataURL({ format:'png', multiplier: Math.min(1, 1200 / Math.max(canvasW, canvasH)) }); }
  catch (e) { showToast('Preview blocked: an image from another website has no permission', 'err'); return; }
  document.getElementById('mockupDesign').src = dataURL;
  document.getElementById('mockupFrame').style.transform = 'none';
  document.querySelectorAll('.mockup-type-btn').forEach((b,i) => b.classList.toggle('active', i===0));
  document.getElementById('mockupModal').style.display = 'flex';
}

function closeMockupModal() {
  document.getElementById('mockupModal').style.display = 'none';
}

function setMockupType(type, btn) {
  document.querySelectorAll('.mockup-type-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  const frame = document.getElementById('mockupFrame');
  const transforms = {
    flat:   'none',
    tshirt: 'perspective(600px) rotateX(10deg) rotateY(-4deg) scale(0.95)',
    idcard: 'perspective(500px) rotateY(18deg) rotateX(3deg)',
    mug:    'perspective(400px) rotateY(22deg) rotateX(6deg) skewY(-2deg)',
    banner: 'perspective(800px) rotateY(-12deg) scaleX(0.92)',
    tilt:   'perspective(600px) rotateX(8deg) rotateZ(-3deg)',
  };
  frame.style.transform = transforms[type] || 'none';
}

// ── Snap Guides Toggle ─────────────────────────────────────────────────
function toggleSnapGuides() {
  snapEnabled = !snapEnabled;
  showToast('Smart Snap ' + (snapEnabled ? 'ON — snaps to canvas & other objects (H to toggle)' : 'OFF'), 'info');
}

// ── Global tooltip (replaces CSS :hover approach) ─────────────────────
(function() {
  const tip = document.createElement('div');
  tip.id = 'g-tip';
  document.body.appendChild(tip);
  document.querySelectorAll('.tool-btn').forEach(btn => {
    const span = btn.querySelector('.tool-tooltip');
    if (!span) return;
    const text = span.textContent.trim();
    btn.addEventListener('mouseenter', () => {
      tip.textContent = text;
      tip.style.display = 'block';
      const r = btn.getBoundingClientRect();
      tip.style.top  = (r.top + r.height/2 - 12) + 'px';
      tip.style.left = (r.right + 10) + 'px';
    });
    btn.addEventListener('mouseleave',  () => { tip.style.display = 'none'; });
    btn.addEventListener('mousedown',   () => { tip.style.display = 'none'; });
  });
})();

// ── Init ─────────────────────────────────────────────────────────────
populateFonts();
initEditor();
fitZoomIfNeeded();
// Web fonts finish loading after the first paint — re-measure text so boxes fit.
if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => ensureFontsFor());

// Auto-save to localStorage every 20 seconds — only when there are unsaved
// changes (it used to overwrite the slot even right after a real save, so the
// "recover session?" prompt appeared on every visit). Quota errors (designs
// with big photos) no longer throw every 30 s.
setInterval(() => {
  if (!_dirty || hist.locked) return;
  try {
    localStorage.setItem(AUTOSAVE_KEY, hist.states[hist.idx] || JSON.stringify(canvasJSON()));
    localStorage.setItem(AUTOSAVE_TKEY, String(Date.now()));
  } catch (e) { /* storage full — skip */ }
}, 20000);

// ════════════════════════════════════════════════════════════════════
// ██████████  3D TEXT STUDIO  ████████████████████████████████████████
// 50+ Pro Effects · Fully Customizable · Live Canvas Preview
// ════════════════════════════════════════════════════════════════════

/* ── 50 Pro Effect Definitions ─────────────────────────────────── */
const FX3D_EFFECTS = [
  // ── 3D Classic ──
  { id:'extrude-hard',   label:'Extrude Hard',     cat:'3d',      badge:'pro',
    desc:'Classic hard block extrude — bold depth',
    render: (ctx,W,H,txt,cfg) => fx3D_extrudeHard(ctx,W,H,txt,cfg) },
  { id:'extrude-soft',   label:'Extrude Soft',     cat:'3d',      badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_extrudeSoft(ctx,W,H,txt,cfg) },
  { id:'shadow-stack',   label:'Shadow Stack',     cat:'3d',      badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_shadowStack(ctx,W,H,txt,cfg) },
  { id:'isometric',      label:'Isometric',        cat:'3d',      badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_isometric(ctx,W,H,txt,cfg) },
  { id:'deep-shadow',    label:'Deep Shadow',      cat:'3d',      badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_deepShadow(ctx,W,H,txt,cfg) },
  { id:'pop-out',        label:'Pop Out',          cat:'3d',      badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_popOut(ctx,W,H,txt,cfg) },
  { id:'emboss',         label:'Emboss',           cat:'3d',      badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_emboss(ctx,W,H,txt,cfg) },
  { id:'deboss',         label:'Deboss',           cat:'3d',      badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_deboss(ctx,W,H,txt,cfg) },
  { id:'layered-3d',     label:'Layered 3D',       cat:'3d',      badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_layered(ctx,W,H,txt,cfg) },
  { id:'perspective-3d', label:'Perspective',      cat:'3d',      badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_perspective(ctx,W,H,txt,cfg) },

  // ── 4D ──
  { id:'4d-shift',       label:'4D Color Shift',   cat:'4d',      badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_4dShift(ctx,W,H,txt,cfg) },
  { id:'4d-time',        label:'4D Time Warp',     cat:'4d',      badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_4dTime(ctx,W,H,txt,cfg) },
  { id:'4d-ghost',       label:'4D Ghost Trail',   cat:'4d',      badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_4dGhost(ctx,W,H,txt,cfg) },
  { id:'4d-multiplane',  label:'4D Multi-Plane',   cat:'4d',      badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_4dMultiplane(ctx,W,H,txt,cfg) },
  { id:'4d-echo',        label:'4D Echo',          cat:'4d',      badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_4dEcho(ctx,W,H,txt,cfg) },

  // ── 5D ──
  { id:'5d-crystal',     label:'5D Crystal',       cat:'5d',      badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_5dCrystal(ctx,W,H,txt,cfg) },
  { id:'5d-prism',       label:'5D Prism',         cat:'5d',      badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_5dPrism(ctx,W,H,txt,cfg) },
  { id:'5d-quantum',     label:'5D Quantum',       cat:'5d',      badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_5dQuantum(ctx,W,H,txt,cfg) },
  { id:'5d-hyperspace',  label:'5D Hyperspace',    cat:'5d',      badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_5dHyperspace(ctx,W,H,txt,cfg) },
  { id:'5d-wormhole',    label:'5D Wormhole',      cat:'5d',      badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_5dWormhole(ctx,W,H,txt,cfg) },

  // ── Blend ──
  { id:'blend-duotone',  label:'Blend Duotone',    cat:'blend',   badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_blendDuotone(ctx,W,H,txt,cfg) },
  { id:'blend-chromatic',label:'Chromatic',        cat:'blend',   badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_blendChromatic(ctx,W,H,txt,cfg) },
  { id:'blend-glass',    label:'Blend Glass',      cat:'blend',   badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_blendGlass(ctx,W,H,txt,cfg) },
  { id:'blend-halo',     label:'Blend Halo',       cat:'blend',   badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_blendHalo(ctx,W,H,txt,cfg) },
  { id:'blend-aurora',   label:'Aurora Blend',     cat:'blend',   badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_blendAurora(ctx,W,H,txt,cfg) },

  // ── Neon ──
  { id:'neon-classic',   label:'Neon Classic',     cat:'neon',    badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_neonClassic(ctx,W,H,txt,cfg) },
  { id:'neon-double',    label:'Neon Double',      cat:'neon',    badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_neonDouble(ctx,W,H,txt,cfg) },
  { id:'neon-cyberpunk', label:'Cyberpunk',        cat:'neon',    badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_neonCyberpunk(ctx,W,H,txt,cfg) },
  { id:'neon-pulse',     label:'Neon Pulse',       cat:'neon',    badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_neonPulse(ctx,W,H,txt,cfg) },
  { id:'neon-rainbow',   label:'Neon Rainbow',     cat:'neon',    badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_neonRainbow(ctx,W,H,txt,cfg) },

  // ── Metal ──
  { id:'metal-gold',     label:'Gold Metal',       cat:'metal',   badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_metalGold(ctx,W,H,txt,cfg) },
  { id:'metal-chrome',   label:'Chrome',           cat:'metal',   badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_metalChrome(ctx,W,H,txt,cfg) },
  { id:'metal-silver',   label:'Silver',           cat:'metal',   badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_metalSilver(ctx,W,H,txt,cfg) },
  { id:'metal-rose',     label:'Rose Gold',        cat:'metal',   badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_metalRose(ctx,W,H,txt,cfg) },
  { id:'metal-copper',   label:'Copper',           cat:'metal',   badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_metalCopper(ctx,W,H,txt,cfg) },

  // ── Fire ──
  { id:'fire-classic',   label:'Fire',             cat:'fire',    badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_fire(ctx,W,H,txt,cfg) },
  { id:'fire-inferno',   label:'Inferno',          cat:'fire',    badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_inferno(ctx,W,H,txt,cfg) },
  { id:'fire-plasma',    label:'Plasma',           cat:'fire',    badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_plasma(ctx,W,H,txt,cfg) },
  { id:'fire-lava',      label:'Lava',             cat:'fire',    badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_lava(ctx,W,H,txt,cfg) },

  // ── Ice ──
  { id:'ice-frost',      label:'Ice Frost',        cat:'ice',     badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_iceFrost(ctx,W,H,txt,cfg) },
  { id:'ice-crystal',    label:'Ice Crystal',      cat:'ice',     badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_iceCrystal(ctx,W,H,txt,cfg) },
  { id:'ice-snow',       label:'Snowfall',         cat:'ice',     badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_snow(ctx,W,H,txt,cfg) },

  // ── Retro ──
  { id:'retro-80s',      label:'80s Retro',        cat:'retro',   badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_retro80s(ctx,W,H,txt,cfg) },
  { id:'retro-vintage',  label:'Vintage',          cat:'retro',   badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_vintage(ctx,W,H,txt,cfg) },
  { id:'retro-comic',    label:'Comic Pop',        cat:'retro',   badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_comicPop(ctx,W,H,txt,cfg) },
  { id:'retro-vaporwave',label:'Vaporwave',        cat:'retro',   badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_vaporwave(ctx,W,H,txt,cfg) },

  // ── Glass ──
  { id:'glass-clear',    label:'Clear Glass',      cat:'glass',   badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_glassEffect(ctx,W,H,txt,cfg,false) },
  { id:'glass-frosted',  label:'Frosted Glass',    cat:'glass',   badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_glassEffect(ctx,W,H,txt,cfg,true) },
  { id:'glass-3d',       label:'3D Glass Block',   cat:'glass',   badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_glassBlock(ctx,W,H,txt,cfg) },

  // ── Hologram ──
  { id:'holo-scan',      label:'Holo Scan',        cat:'hologram',badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_holoScan(ctx,W,H,txt,cfg) },
  { id:'holo-glitch',    label:'Glitch',           cat:'hologram',badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_glitch(ctx,W,H,txt,cfg) },
  { id:'holo-matrix',    label:'Matrix',           cat:'hologram',badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_matrix(ctx,W,H,txt,cfg) },

  // ── New: Marble / Galaxy / Sunset ──
  { id:'blend-marble',   label:'Marble Stone',     cat:'blend',   badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_marble(ctx,W,H,txt,cfg) },
  { id:'blend-sunset',   label:'Sunset Glow',      cat:'blend',   badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_sunset(ctx,W,H,txt,cfg) },
  { id:'blend-watercolor',label:'Watercolor',      cat:'blend',   badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_watercolor(ctx,W,H,txt,cfg) },
  { id:'blend-liquid',   label:'Liquid Metal',     cat:'blend',   badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_liquidMetal(ctx,W,H,txt,cfg) },

  // ── New: 5D ──
  { id:'5d-galaxy',      label:'Galaxy',           cat:'5d',      badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_galaxy(ctx,W,H,txt,cfg) },
  { id:'5d-diamond',     label:'Diamond',          cat:'5d',      badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_diamond(ctx,W,H,txt,cfg) },
  { id:'5d-glitter',     label:'Glitter Gold',     cat:'5d',      badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_glitter(ctx,W,H,txt,cfg) },

  // ── New: Neon ──
  { id:'neon-spectrum',  label:'Spectrum',          cat:'neon',    badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_rainbow(ctx,W,H,txt,cfg) },
  { id:'neon-laser',     label:'Laser',            cat:'neon',    badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_laser(ctx,W,H,txt,cfg) },

  // ── New: Retro ──
  { id:'retro-graffiti', label:'Graffiti',         cat:'retro',   badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_graffiti(ctx,W,H,txt,cfg) },
  { id:'retro-stamp',    label:'Stamp',            cat:'retro',   badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_stamp(ctx,W,H,txt,cfg) },
  { id:'retro-chalk',    label:'Chalk',            cat:'retro',   badge:'pro',
    render: (ctx,W,H,txt,cfg) => fx3D_chalk(ctx,W,H,txt,cfg) },

  // ── New: 4D ──
  { id:'4d-psychedelic', label:'Psychedelic',      cat:'4d',      badge:'hot',
    render: (ctx,W,H,txt,cfg) => fx3D_psychedelic(ctx,W,H,txt,cfg) },
  { id:'4d-smoke',       label:'Smoke',            cat:'4d',      badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_smoke(ctx,W,H,txt,cfg) },

  // ── New: Glass ──
  { id:'glass-blueprint',label:'Blueprint',        cat:'glass',   badge:'new',
    render: (ctx,W,H,txt,cfg) => fx3D_blueprint(ctx,W,H,txt,cfg) },
];

/* ── State ──────────────────────────────────────────────────── */
let fx3D_selected    = 'extrude-hard';
let fx3D_activeCat   = 'all';
let fx3D_previewCtx  = null;
let fx3D_rafId       = null;

/* ── Open / Close ───────────────────────────────────────────── */
function open3DStudio() {
  closeEdPanel();
  const modal = document.getElementById('studio3DModal');
  modal.style.display = 'flex';
  if (!fx3D_previewCtx) {
    const cv = document.getElementById('canvas3DPreview');
    fx3D_previewCtx = cv.getContext('2d');
  }
  build3DEffectGrid('all');
  update3DPreview();
}
function close3DStudio() {
  document.getElementById('studio3DModal').style.display = 'none';
}

/* ── Category Filter ────────────────────────────────────────── */
function set3DCat(btn, cat) {
  document.querySelectorAll('.fx3d-cat').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  fx3D_activeCat = cat;
  build3DEffectGrid(cat, document.getElementById('fx3DSearch').value);
}

function filter3DEffects(q) {
  build3DEffectGrid(fx3D_activeCat, q);
}

function randomize3DEffect() {
  const pool = FX3D_EFFECTS.filter(f => fx3D_activeCat === 'all' || f.cat === fx3D_activeCat);
  if (!pool.length) return;
  const pick = pool[Math.floor(Math.random() * pool.length)];
  fx3D_selected = pick.id;
  build3DEffectGrid(fx3D_activeCat, document.getElementById('fx3DSearch').value);
  update3DPreview();
  showToast('🎲 ' + pick.label + ' — try it!', 'info');
}

function build3DEffectGrid(cat, q='') {
  const list = document.getElementById('fx3DList');
  list.innerHTML = '';
  const lq = q.toLowerCase();
  FX3D_EFFECTS.forEach(fx => {
    if (cat !== 'all' && fx.cat !== cat) return;
    if (lq && !fx.label.toLowerCase().includes(lq) && !fx.cat.includes(lq)) return;
    const div = document.createElement('div');
    div.className = 'fx3d-effect-card' + (fx.id === fx3D_selected ? ' sel' : '');
    div.dataset.id = fx.id;
    // Thumbnail: render at 2× then CSS scales to fit card
    const thumbC = document.createElement('canvas');
    thumbC.width = 200; thumbC.height = 80;
    const cfg = get3DConfig();
    const thumbCfg = Object.assign({}, cfg, {
      fontSize: 32,
      depth:    Math.max(3, Math.min(cfg.depth * 0.4, 7)),
      glow:     Math.min(cfg.glow  * 0.3, 7),
      outline:  Math.min(cfg.outline * 0.4, 1),
      spacing:  0, bold: true,
    });
    const tCtx = thumbC.getContext('2d');
    tCtx.fillStyle = '#0a0f1a';
    tCtx.fillRect(0, 0, 200, 80);
    try { fx.render(tCtx, 200, 80, 'Aa', thumbCfg); } catch(e){}
    const badge = fx.badge ? `<div class="fx3d-badge ${fx.badge}">${fx.badge}</div>` : '';
    div.innerHTML = badge;
    div.appendChild(thumbC);
    const lbl = document.createElement('div');
    lbl.className = 'fx3d-lbl';
    lbl.textContent = fx.label;
    div.appendChild(lbl);
    div.onclick = () => {
      document.querySelectorAll('.fx3d-effect-card').forEach(c => c.classList.remove('sel'));
      div.classList.add('sel');
      fx3D_selected = fx.id;
      update3DPreview();
    };
    list.appendChild(div);
  });
}

/* ── Get Config from UI controls ────────────────────────────── */
function get3DConfig() {
  const mainGradOn = document.getElementById('fx3DMainGradOn') &&
                     document.getElementById('fx3DMainGradOn').checked &&
                     document.getElementById('fx3DMainGradRow')?.style.display !== 'none';
  // `+value || default` turned a deliberate 0 into the default — Depth 0 came
  // out as 12 and Angle 0 as 45. Only fall back when the field is empty/invalid.
  const num = (id, def) => { const el = document.getElementById(id); const v = el ? parseFloat(el.value) : NaN; return isFinite(v) ? v : def; };
  return {
    mainColor:   document.getElementById('fx3DMainColor')?.value   || '#ffffff',
    extColor:    document.getElementById('fx3DExtColor')?.value    || '#7c3aed',
    shadowColor: document.getElementById('fx3DShadowColor')?.value || '#000000',
    font:        document.getElementById('fx3DFont')?.value        || 'Poppins',
    fontSize:    num('fx3DFontSize', 72) || 72,
    depth:       num('fx3DDepth', 12),
    angle:       num('fx3DAngle', 45),
    outline:     num('fx3DOutline', 0),
    outlineColor:document.getElementById('fx3DOutlineColor')?.value|| '#000000',
    glow:        num('fx3DGlow', 0),
    spacing:     num('fx3DSpacing', 0),
    bold:        document.getElementById('fx3DBold')?.checked      ?? true,
    italic:      document.getElementById('fx3DItalic')?.checked    ?? false,
    transBg:     document.getElementById('fx3DTransBg')?.checked   ?? false,
    bgColor:     document.getElementById('fx3DBgColor')?.value     || '#1a1f2e',
    mainGradOn,
    gradC1:      document.getElementById('fx3DMainG1')?.value      || '#f43f5e',
    gradC2:      document.getElementById('fx3DMainG2')?.value      || '#a855f7',
  };
}

/* ── Update Live Preview ─────────────────────────────────────── */
function update3DPreview() {
  const txtEl = document.getElementById('fx3DTextInput');
  if (!txtEl) return;
  const txt = txtEl.value || 'SSZone';
  const ccEl = document.getElementById('fx3DCharCount');
  if (ccEl) ccEl.textContent = txt.length + '/30';
  const cv  = document.getElementById('canvas3DPreview');
  const ctx = fx3D_previewCtx || cv.getContext('2d');
  const cfg = get3DConfig();

  // BG
  if (cfg.transBg) {
    ctx.clearRect(0, 0, cv.width, cv.height);
  } else {
    ctx.fillStyle = cfg.bgColor;
    ctx.fillRect(0, 0, cv.width, cv.height);
  }

  const fx = FX3D_EFFECTS.find(f => f.id === fx3D_selected);
  if (fx) { try { ctx.save(); fx.render(ctx, cv.width, cv.height, txt, _fit3DConfig(ctx, cv.width, cv.height, txt, cfg)); ctx.restore(); } catch(e){ ctx.restore(); console.warn('3D FX error:', e); } }
}
/* Shrink the font so the whole word fits — long names (up to 30 chars) were
   cut off at the left/right edge in both the preview and the applied image. */
function _fit3DConfig(ctx, W, H, txt, cfg) {
  const c = Object.assign({}, cfg);
  let fs = Math.min(c.fontSize, H * 0.65);
  ctx.save();
  for (let i = 0; i < 40 && fs > 8; i++) {
    ctx.font = `${c.italic ? 'italic' : 'normal'} ${c.bold ? 'bold' : 'normal'} ${fs}px "${c.font}", sans-serif`;
    const w = ctx.measureText(txt).width + Math.max(0, c.spacing) * txt.length + c.depth * 1.2 + c.glow + c.outline * 2;
    if (w <= W * 0.9) break;
    fs *= 0.92;
  }
  ctx.restore();
  c.fontSize = Math.round(fs);
  return c;
}

/* ── Color setters ───────────────────────────────────────────── */
function setFx3DColor(type, hex) {
  const map = { main:'fx3DMainColor', ext:'fx3DExtColor', shadow:'fx3DShadowColor', bg:'fx3DBgColor' };
  const el = document.getElementById(map[type]);
  if (type === 'main') setFx3DMainSolid();   // picking a solid main colour switches the gradient off
  if (el) { el.value = hex; update3DPreview(); }
}
function pick3DGradMain() {
  const row = document.getElementById('fx3DMainGradRow');
  const chk = document.getElementById('fx3DMainGradOn');
  if (!row) return;
  const show = row.style.display === 'none';
  row.style.display = show ? 'flex' : 'none';
  if (chk) chk.checked = show;          // opening the gradient row turns it on, closing turns it off
  update3DPreview();
}
function setFx3DMainSolid() { const chk = document.getElementById('fx3DMainGradOn'); if (chk) chk.checked = false; }

/* ── Apply to Canvas ─────────────────────────────────────────── */
function apply3DEffect() {
  const txt = (document.getElementById('fx3DTextInput')?.value || 'SSZone').trim();
  if (!txt) { showToast('Enter text first', 'err'); return; }
  const cfg   = get3DConfig();
  const scale = 3;
  const W0 = 800, H0 = 280;
  const tmpC  = document.createElement('canvas');
  tmpC.width  = W0 * scale; tmpC.height = H0 * scale;
  const ctx   = tmpC.getContext('2d', { willReadFrequently: true });
  ctx.scale(scale, scale);

  ctx.clearRect(0, 0, W0, H0);
  // (The preview background is only for viewing — the applied text is always transparent.)
  const fx = FX3D_EFFECTS.find(f => f.id === fx3D_selected);
  if (fx) { try { ctx.save(); fx.render(ctx, W0, H0, txt, _fit3DConfig(ctx, W0, H0, txt, cfg)); ctx.restore(); } catch(e){ ctx.restore(); console.error(e); } }

  // ── Auto-trim: find tight bounding box of non-transparent pixels ──
  const fullW = tmpC.width, fullH = tmpC.height;
  const idata = ctx.getImageData(0, 0, fullW, fullH).data;
  let minX = fullW, maxX = 0, minY = fullH, maxY = 0;
  for (let y = 0; y < fullH; y++) {
    for (let x = 0; x < fullW; x++) {
      if (idata[(y * fullW + x) * 4 + 3] > 6) { // alpha threshold
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
      }
    }
  }

  let useDataURL;
  if (maxX < minX || maxY < minY) {
    // Nothing visible — fall back to full canvas
    useDataURL = tmpC.toDataURL('image/png');
  } else {
    // Add a small padding so strokes/glows at edges aren't clipped
    const pad = Math.max(8, Math.round(fullW * 0.015));
    minX = Math.max(0, minX - pad);
    minY = Math.max(0, minY - pad);
    maxX = Math.min(fullW - 1, maxX + pad);
    maxY = Math.min(fullH - 1, maxY + pad);
    const cropW = maxX - minX + 1;
    const cropH = maxY - minY + 1;
    const cropC = document.createElement('canvas');
    cropC.width = cropW; cropC.height = cropH;
    cropC.getContext('2d').drawImage(tmpC, minX, minY, cropW, cropH, 0, 0, cropW, cropH);
    useDataURL = cropC.toDataURL('image/png');
  }

  const imgEl = new Image();
  imgEl.onload = () => {
    const scl = Math.min(canvasW * 0.75 / imgEl.width, canvasH * 0.75 / imgEl.height);
    if (activeTool !== 'select') setActiveTool('select');
    const fImg = new fabric.Image(imgEl, {
      left: canvasW / 2, top: canvasH / 2,
      originX: 'center', originY: 'center',
      scaleX: scl, scaleY: scl,
      data: { fx3d: true, fxId: fx3D_selected, origText: txt }
    });
    canvas.add(fImg).setActiveObject(fImg).renderAll();
    histSave();
    close3DStudio();
    showToast('3D effect applied! ✨');
  };
  imgEl.src = useDataURL;
}

/* ════════════════════════════════════════════════════════════════
   3D EFFECT RENDERERS — 50 Pro Effects
════════════════════════════════════════════════════════════════ */

/* ── Helper: setup font + fill ──────────────────────────────── */
function fx3D_setupFont(ctx, cfg, size) {
  const fw = cfg.bold ? 'bold' : 'normal';
  const fi = cfg.italic ? 'italic' : 'normal';
  const fs = size || cfg.fontSize;
  ctx.font = `${fi} ${fw} ${fs}px "${cfg.font}", sans-serif`;
  ctx.textBaseline = 'middle';
  ctx.textAlign = 'center';
}

function fx3D_getMainFill(ctx, W, cfg) {
  if (cfg.mainGradOn) {
    const g = ctx.createLinearGradient(0, 0, W, 0);
    g.addColorStop(0, cfg.gradC1); g.addColorStop(1, cfg.gradC2); return g;
  }
  return cfg.mainColor;
}

function fx3D_drawOutline(ctx, W, H, txt, cfg, size) {
  if (cfg.outline > 0) {
    ctx.strokeStyle = cfg.outlineColor;
    ctx.lineWidth   = cfg.outline * 2;
    ctx.lineJoin    = 'round';
    ctx.strokeText(txt, W/2, H/2);
  }
}

function fx3D_applyGlow(ctx, cfg) {
  if (cfg.glow > 0) {
    ctx.shadowColor = cfg.shadowColor;
    ctx.shadowBlur  = cfg.glow;
  } else {
    ctx.shadowBlur = 0; ctx.shadowColor = 'transparent';
  }
}

/* ── 1. Extrude Hard ─────────────────────────────────────────── */
function fx3D_extrudeHard(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.65);
  fx3D_setupFont(ctx, cfg, fs);
  const ang = cfg.angle * Math.PI / 180;
  const dx  = Math.cos(ang) * cfg.depth * 0.7;
  const dy  = Math.sin(ang) * cfg.depth * 0.7;
  // Draw layers
  for (let i = cfg.depth; i >= 1; i--) {
    const f = i / cfg.depth;
    ctx.fillStyle = shadeColor(cfg.extColor, -30 + f * 20);
    ctx.fillText(txt, W/2 + dx * (i/cfg.depth), H/2 + dy * (i/cfg.depth));
  }
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  ctx.fillStyle  = fx3D_getMainFill(ctx, W, cfg);
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2);
  ctx.shadowBlur = 0;
}

/* ── 2. Extrude Soft ─────────────────────────────────────────── */
function fx3D_extrudeSoft(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.65);
  fx3D_setupFont(ctx, cfg, fs);
  const ang = cfg.angle * Math.PI / 180;
  for (let i = cfg.depth; i >= 0; i--) {
    const t   = 1 - i / Math.max(cfg.depth, 1);
    const col = lerpColor(cfg.extColor, cfg.mainColor, t);
    ctx.globalAlpha = 0.5 + t * 0.5;
    ctx.fillStyle   = col;
    ctx.fillText(txt, W/2 + Math.cos(ang)*i*0.7, H/2 + Math.sin(ang)*i*0.7);
  }
  ctx.globalAlpha = 1;
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  ctx.fillStyle  = fx3D_getMainFill(ctx, W, cfg);
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2);
  ctx.shadowBlur = 0;
}

/* ── 3. Shadow Stack ─────────────────────────────────────────── */
function fx3D_shadowStack(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = cfg.depth; i > 0; i--) {
    ctx.globalAlpha = 0.12 + (1 - i/cfg.depth) * 0.2;
    ctx.fillStyle   = cfg.shadowColor;
    ctx.fillText(txt, W/2 + i*1.2, H/2 + i*1.2);
  }
  ctx.globalAlpha = 1;
  ctx.fillStyle   = fx3D_getMainFill(ctx, W, cfg);
  ctx.shadowColor = cfg.shadowColor; ctx.shadowBlur = cfg.glow;
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2);
  ctx.shadowBlur = 0;
}

/* ── 4. Isometric ────────────────────────────────────────────── */
function fx3D_isometric(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.55);
  fx3D_setupFont(ctx, cfg, fs);
  const d  = cfg.depth;
  // top face offset
  for (let i = d; i >= 1; i--) {
    ctx.fillStyle = i % 2 === 0 ? shadeColor(cfg.extColor,-20) : cfg.extColor;
    ctx.fillText(txt, W/2 - i*0.5, H/2 + i*0.5);
  }
  // right-side offset
  for (let i = d; i >= 1; i--) {
    ctx.fillStyle = shadeColor(cfg.extColor, -40);
    ctx.fillText(txt, W/2 + i*0.4, H/2 + i*0.3);
  }
  ctx.fillStyle  = fx3D_getMainFill(ctx, W, cfg);
  ctx.shadowColor= cfg.shadowColor; ctx.shadowBlur=cfg.glow;
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2);
  ctx.shadowBlur = 0;
}

/* ── 5. Deep Shadow ──────────────────────────────────────────── */
function fx3D_deepShadow(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  ctx.shadowColor = cfg.shadowColor; ctx.shadowBlur = cfg.depth * 3;
  ctx.shadowOffsetX = cfg.depth; ctx.shadowOffsetY = cfg.depth * 1.2;
  ctx.fillStyle = fx3D_getMainFill(ctx, W, cfg);
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2);
  ctx.shadowBlur = cfg.glow; ctx.shadowOffsetX = 0; ctx.shadowOffsetY = 0;
  ctx.fillText(txt, W/2, H/2);
  ctx.shadowBlur = 0;
}

/* ── 6. Pop Out ──────────────────────────────────────────────── */
function fx3D_popOut(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.55);
  fx3D_setupFont(ctx, cfg, fs);
  // Big bg shadow
  ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = cfg.depth * 2;
  ctx.shadowOffsetX = cfg.depth * 1.5; ctx.shadowOffsetY = cfg.depth * 1.5;
  // Extrude
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = shadeColor(cfg.extColor, -50 + i * 2);
    ctx.fillText(txt, W/2 + i*0.6, H/2 + i*0.6);
  }
  ctx.shadowBlur = cfg.glow; ctx.shadowOffsetX = 0; ctx.shadowOffsetY = 0;
  ctx.shadowColor = cfg.shadowColor;
  ctx.fillStyle = fx3D_getMainFill(ctx, W, cfg);
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2);
  ctx.shadowBlur = 0;
}

/* ── 7. Emboss ───────────────────────────────────────────────── */
function fx3D_emboss(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  // Highlight top-left
  ctx.shadowColor = 'rgba(255,255,255,.5)'; ctx.shadowBlur = 2;
  ctx.shadowOffsetX = -2; ctx.shadowOffsetY = -2;
  ctx.fillStyle = cfg.mainColor;
  ctx.fillText(txt, W/2, H/2);
  // Dark bottom-right
  ctx.shadowColor = 'rgba(0,0,0,.6)'; ctx.shadowOffsetX = 2; ctx.shadowOffsetY = 2;
  ctx.globalAlpha = 0.55;
  ctx.fillStyle = cfg.extColor;
  ctx.fillText(txt, W/2, H/2);
  ctx.globalAlpha = 1; ctx.shadowBlur = 0; ctx.shadowOffsetX = 0; ctx.shadowOffsetY = 0;
  ctx.fillStyle = fx3D_getMainFill(ctx, W, cfg);
  ctx.fillText(txt, W/2, H/2);
}

/* ── 8. Deboss ───────────────────────────────────────────────── */
function fx3D_deboss(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  ctx.shadowColor = 'rgba(0,0,0,.7)'; ctx.shadowBlur = 3;
  ctx.shadowOffsetX = 2; ctx.shadowOffsetY = 2;
  ctx.fillStyle = cfg.extColor; ctx.fillText(txt, W/2, H/2);
  ctx.shadowColor = 'rgba(255,255,255,.4)'; ctx.shadowOffsetX = -1; ctx.shadowOffsetY = -1;
  ctx.globalAlpha = 0.5;
  ctx.fillStyle = cfg.mainColor; ctx.fillText(txt, W/2, H/2);
  ctx.globalAlpha = 1; ctx.shadowBlur = 0; ctx.shadowOffsetX = 0; ctx.shadowOffsetY = 0;
  ctx.fillStyle = shadeColor(cfg.mainColor, -20);
  ctx.fillText(txt, W/2, H/2);
}

/* ── 9. Layered 3D ───────────────────────────────────────────── */
function fx3D_layered(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.55);
  fx3D_setupFont(ctx, cfg, fs);
  const colors = [cfg.extColor, shadeColor(cfg.extColor,30), shadeColor(cfg.mainColor,-30), cfg.mainColor];
  for (let i = cfg.depth; i >= 0; i--) {
    const ci = Math.floor((i / cfg.depth) * (colors.length - 1));
    ctx.fillStyle = colors[Math.min(ci, colors.length-1)];
    ctx.fillText(txt, W/2 - i*0.6, H/2 - i*0.4);
  }
  ctx.fillStyle  = fx3D_getMainFill(ctx, W, cfg);
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2);
  ctx.shadowBlur = 0;
}

/* ── 10. Perspective ─────────────────────────────────────────── */
function fx3D_perspective(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.55);
  fx3D_setupFont(ctx, cfg, fs);
  ctx.save();
  ctx.translate(W/2, H/2);
  // Perspective skew
  for (let i = cfg.depth; i >= 0; i--) {
    const t = 1 - i / Math.max(cfg.depth,1);
    ctx.fillStyle = lerpColor(cfg.extColor, cfg.mainColor, t);
    const skewX = i * 0.018; const shiftY = i * 0.5;
    ctx.save();
    ctx.transform(1, -skewX, 0, 1 + skewX * 0.2, 0, shiftY);
    ctx.fillText(txt, 0, 0);
    ctx.restore();
  }
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  ctx.fillStyle = fx3D_getMainFill(ctx, W, cfg);
  ctx.fillText(txt, 0, 0);
  ctx.shadowBlur = 0;
  ctx.restore();
}

/* ── 11. 4D Color Shift ──────────────────────────────────────── */
function fx3D_4dShift(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  const offsets = [[-3,-1,'rgba(255,0,80,.7)'],[-1,2,'rgba(0,200,255,.7)'],[2,-2,'rgba(255,200,0,.6)']];
  offsets.forEach(([ox,oy,col]) => {
    ctx.fillStyle = col; ctx.fillText(txt, W/2+ox, H/2+oy);
  });
  ctx.fillStyle = fx3D_getMainFill(ctx, W, cfg);
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2);
  ctx.shadowBlur = 0;
}

/* ── 12. 4D Time Warp ────────────────────────────────────────── */
function fx3D_4dTime(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.55);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = 6; i >= 0; i--) {
    ctx.globalAlpha = 0.08 + i * 0.1;
    ctx.fillStyle   = lerpColor(cfg.extColor, cfg.mainColor, i/6);
    ctx.save();
    ctx.translate(W/2, H/2);
    ctx.rotate((i - 3) * 0.012);
    ctx.scale(1 - i * 0.015, 1);
    ctx.fillText(txt, 0, i * 2);
    ctx.restore();
  }
  ctx.globalAlpha = 1;
  ctx.fillStyle   = fx3D_getMainFill(ctx, W, cfg);
  ctx.shadowBlur  = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  ctx.fillText(txt, W/2, H/2);
  ctx.shadowBlur  = 0;
}

/* ── 13. 4D Ghost Trail ──────────────────────────────────────── */
function fx3D_4dGhost(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = cfg.depth; i > 0; i--) {
    ctx.globalAlpha = 0.04 + (1 - i/cfg.depth) * 0.25;
    ctx.fillStyle   = lerpColor(cfg.shadowColor, cfg.extColor, 1 - i/cfg.depth);
    ctx.fillText(txt, W/2 - i * 2.5, H/2 - i * 0.5);
  }
  ctx.globalAlpha = 1;
  ctx.shadowBlur  = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  ctx.fillStyle   = fx3D_getMainFill(ctx, W, cfg);
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2);
  ctx.shadowBlur  = 0;
}

/* ── 14. 4D Multi-Plane ──────────────────────────────────────── */
function fx3D_4dMultiplane(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize * 0.75, H * 0.42);
  fx3D_setupFont(ctx, cfg, fs);
  const planes = [[0,-H*0.25,cfg.extColor,0.6],[W*0.12,-H*0.08,cfg.mainColor,0.45],[0,0,cfg.mainColor,1.0]];
  planes.forEach(([ox,oy,col,a]) => {
    ctx.globalAlpha = a; ctx.fillStyle = col;
    ctx.fillText(txt, W/2+ox, H/2+oy);
  });
  ctx.globalAlpha = 1;
}

/* ── 15. 4D Echo ─────────────────────────────────────────────── */
function fx3D_4dEcho(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.55);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = 5; i >= 0; i--) {
    ctx.globalAlpha = 0.08 + i * 0.12;
    const sc = 1 + i * 0.04;
    ctx.save(); ctx.translate(W/2, H/2); ctx.scale(sc, sc);
    ctx.fillStyle = i % 2 === 0 ? cfg.extColor : cfg.shadowColor;
    ctx.fillText(txt, 0, 0); ctx.restore();
  }
  ctx.globalAlpha = 1;
  ctx.fillStyle   = fx3D_getMainFill(ctx, W, cfg);
  ctx.shadowBlur  = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 16. 5D Crystal ──────────────────────────────────────────── */
function fx3D_5dCrystal(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.6);
  fx3D_setupFont(ctx, cfg, fs);
  // Multi-angle refraction
  [[2,0,'rgba(255,100,100,.35)'],[0,2,'rgba(100,100,255,.35)'],[-2,0,'rgba(100,255,100,.35)'],[0,-2,'rgba(255,255,0,.25)']].forEach(([ox,oy,c]) => {
    ctx.fillStyle = c; ctx.fillText(txt, W/2+ox, H/2+oy);
  });
  // Crystal shimmer (white highlight diagonal)
  const g = ctx.createLinearGradient(W*0.1,0,W*0.9,H);
  g.addColorStop(0,'rgba(255,255,255,0)'); g.addColorStop(0.45,'rgba(255,255,255,.7)');
  g.addColorStop(0.55,'rgba(255,255,255,.7)'); g.addColorStop(1,'rgba(255,255,255,0)');
  ctx.fillStyle = g;
  ctx.globalCompositeOperation = 'source-atop';
  ctx.save(); ctx.fillText(txt, W/2, H/2);
  // Actually apply properly:
  ctx.globalCompositeOperation = 'source-over';
  ctx.restore();
  ctx.fillStyle = fx3D_getMainFill(ctx, W, cfg);
  ctx.shadowBlur = cfg.glow * 1.5; ctx.shadowColor = '#88aaff';
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 17. 5D Prism ────────────────────────────────────────────── */
function fx3D_5dPrism(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  const rainbow = ['#ff0000','#ff7700','#ffff00','#00ff00','#0000ff','#8b00ff'];
  rainbow.forEach((c, i) => {
    ctx.globalAlpha = 0.3; ctx.fillStyle = c;
    ctx.fillText(txt, W/2 + (i-3)*1.5, H/2 + (i-3)*0.5);
  });
  ctx.globalAlpha = 1;
  const g = ctx.createLinearGradient(W*0.1,0,W*0.9,0);
  rainbow.forEach((c,i) => g.addColorStop(i/5, c));
  ctx.fillStyle = g;
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 18. 5D Quantum ──────────────────────────────────────────── */
function fx3D_5dQuantum(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.55);
  fx3D_setupFont(ctx, cfg, fs);
  // Quantum superposition: multiple overlapping states
  [[0,0,1],[3,3,0.4],[-3,-3,0.4],[0,4,0.3],[4,0,0.3]].forEach(([ox,oy,a],i) => {
    ctx.globalAlpha = a;
    ctx.fillStyle = i===0 ? fx3D_getMainFill(ctx,W,cfg) : (i%2===0?cfg.extColor:cfg.shadowColor);
    ctx.fillText(txt, W/2+ox, H/2+oy);
  });
  ctx.globalAlpha = 1;
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 19. 5D Hyperspace ───────────────────────────────────────── */
function fx3D_5dHyperspace(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.5);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = 8; i >= 0; i--) {
    ctx.globalAlpha = 0.05 + i * 0.09;
    const sc = 1 + i * 0.06;
    ctx.save(); ctx.translate(W/2,H/2); ctx.scale(sc,sc);
    ctx.fillStyle = lerpColor(cfg.extColor, cfg.shadowColor, i/8);
    ctx.fillText(txt, 0, 0); ctx.restore();
  }
  ctx.globalAlpha = 1;
  const g = ctx.createRadialGradient(W/2,H/2,0,W/2,H/2,fs*0.5);
  g.addColorStop(0,'#ffffff'); g.addColorStop(0.3,cfg.mainColor); g.addColorStop(1,'rgba(0,0,0,0)');
  ctx.fillStyle = g;
  ctx.shadowBlur = cfg.glow*2; ctx.shadowColor = cfg.mainColor;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 20. 5D Wormhole ─────────────────────────────────────────── */
function fx3D_5dWormhole(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.55);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = 10; i >= 0; i--) {
    const sc = 0.5 + i * 0.06;
    const a  = 0.04 + i * 0.08;
    ctx.globalAlpha = Math.min(a, 1);
    ctx.save(); ctx.translate(W/2,H/2); ctx.scale(sc,sc);
    ctx.rotate(i * 0.05);
    ctx.fillStyle = lerpColor(cfg.extColor, cfg.mainColor, i/10);
    ctx.fillText(txt, 0, 0); ctx.restore();
  }
  ctx.globalAlpha = 1;
  ctx.fillStyle  = fx3D_getMainFill(ctx, W, cfg);
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 21. Blend Duotone ───────────────────────────────────────── */
function fx3D_blendDuotone(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  const g = ctx.createLinearGradient(0, H*0.2, 0, H*0.8);
  g.addColorStop(0, cfg.mainColor); g.addColorStop(1, cfg.extColor);
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillStyle = g;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
  // Extrude
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.globalAlpha = 0.05;
    ctx.fillStyle = cfg.extColor;
    ctx.fillText(txt, W/2+i*0.5, H/2+i*0.5);
  }
  ctx.globalAlpha = 1;
}

/* ── 22. Chromatic Aberration ────────────────────────────────── */
function fx3D_blendChromatic(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  const shift = Math.max(2, cfg.depth * 0.3);
  ctx.fillStyle = 'rgba(255,0,0,.6)'; ctx.fillText(txt, W/2 - shift, H/2);
  ctx.fillStyle = 'rgba(0,255,255,.6)'; ctx.fillText(txt, W/2 + shift, H/2);
  ctx.fillStyle = fx3D_getMainFill(ctx, W, cfg);
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 23. Blend Glass ─────────────────────────────────────────── */
function fx3D_blendGlass(ctx, W, H, txt, cfg) {
  fx3D_glassEffect(ctx, W, H, txt, cfg, false);
}

/* ── 24. Blend Halo ──────────────────────────────────────────── */
function fx3D_blendHalo(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = 4; i >= 0; i--) {
    ctx.shadowBlur = 8 + i * 12; ctx.shadowColor = cfg.shadowColor;
    ctx.globalAlpha = 0.15 + i * 0.05;
    ctx.fillStyle = cfg.extColor;
    ctx.fillText(txt, W/2, H/2);
  }
  ctx.globalAlpha = 1; ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  ctx.fillStyle = fx3D_getMainFill(ctx, W, cfg);
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 25. Aurora Blend ────────────────────────────────────────── */
function fx3D_blendAurora(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  const g = ctx.createLinearGradient(0, 0, W, H);
  g.addColorStop(0,'#00f5d4'); g.addColorStop(0.33,'#00bbf9');
  g.addColorStop(0.66,'#f15bb5'); g.addColorStop(1,'#fee440');
  ctx.shadowBlur = 18 + cfg.glow; ctx.shadowColor = '#00f5d4';
  fx3D_extrudeSoft(ctx, W, H, txt, { ...cfg, mainColor: '#00bbf9', extColor: '#f15bb5' });
  ctx.fillStyle = g; ctx.shadowBlur = cfg.glow; ctx.shadowColor = '#00f5d4';
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 26. Neon Classic ────────────────────────────────────────── */
function fx3D_neonClassic(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  [30, 18, 10, 5].forEach((b, i) => {
    ctx.shadowBlur = b + cfg.glow; ctx.shadowColor = cfg.shadowColor;
    ctx.fillStyle  = i < 2 ? cfg.shadowColor : cfg.mainColor;
    ctx.globalAlpha = i < 2 ? 0.35 : 1;
    ctx.fillText(txt, W/2, H/2);
  });
  ctx.globalAlpha = 1; ctx.shadowBlur = 0;
  ctx.fillStyle = '#ffffff';
  ctx.globalAlpha = 0.85; ctx.fillText(txt, W/2, H/2);
  ctx.globalAlpha = 1;
}

/* ── 27. Neon Double ─────────────────────────────────────────── */
function fx3D_neonDouble(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  // Outer glow (extColor)
  ctx.shadowBlur = 30 + cfg.glow; ctx.shadowColor = cfg.extColor;
  ctx.fillStyle  = cfg.extColor; ctx.globalAlpha = 0.4;
  ctx.fillText(txt, W/2, H/2);
  // Inner glow (shadowColor/mainColor)
  ctx.shadowColor = cfg.mainColor; ctx.shadowBlur = 14; ctx.globalAlpha = 0.6;
  ctx.fillStyle   = cfg.mainColor; ctx.fillText(txt, W/2, H/2);
  // Core
  ctx.globalAlpha = 1; ctx.shadowBlur = 6; ctx.shadowColor = '#fff';
  ctx.fillStyle   = '#fff'; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 28. Cyberpunk ───────────────────────────────────────────── */
function fx3D_neonCyberpunk(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  // Hard extrude
  for (let i = cfg.depth * 0.5; i >= 1; i--) {
    ctx.fillStyle = '#ff00ff'; ctx.globalAlpha = 0.3;
    ctx.fillText(txt, W/2 + i, H/2 + i * 0.5);
  }
  ctx.globalAlpha = 1;
  // Glitch lines
  ctx.strokeStyle = '#00ffff'; ctx.lineWidth = 1.5;
  ctx.strokeText(txt, W/2 + 2, H/2);
  ctx.strokeStyle = '#ff00ff';
  ctx.strokeText(txt, W/2 - 2, H/2);
  // Main
  const g = ctx.createLinearGradient(0,H*0.3,0,H*0.7);
  g.addColorStop(0,'#ff00ff'); g.addColorStop(0.5,'#00ffff'); g.addColorStop(1,'#ff00ff');
  ctx.shadowBlur = 18 + cfg.glow; ctx.shadowColor = '#ff00ff';
  ctx.fillStyle  = g; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 29. Neon Pulse ──────────────────────────────────────────── */
function fx3D_neonPulse(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  [40,25,14,6].forEach(b => {
    ctx.shadowBlur = b + cfg.glow; ctx.shadowColor = cfg.mainColor;
    ctx.fillStyle  = cfg.mainColor; ctx.fillText(txt, W/2, H/2);
  });
  ctx.fillStyle = '#ffffff'; ctx.shadowBlur = 4;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 30. Neon Rainbow ────────────────────────────────────────── */
function fx3D_neonRainbow(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  const r = ['#ff0000','#ff7700','#ffff00','#00ff00','#0000ff','#8b00ff'];
  const g = ctx.createLinearGradient(W*0.05,0,W*0.95,0);
  r.forEach((c,i) => { g.addColorStop(i/5, c); ctx.shadowBlur = 20; ctx.shadowColor = c; });
  ctx.fillStyle = g; ctx.shadowBlur = 16; ctx.shadowColor = cfg.mainColor;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 31. Gold Metal ──────────────────────────────────────────── */
function fx3D_metalGold(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  // Extrude in dark gold
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = '#7a5000';
    ctx.fillText(txt, W/2 + i*0.5, H/2 + i*0.5);
  }
  const g = ctx.createLinearGradient(0, H*0.25, 0, H*0.75);
  g.addColorStop(0,'#fff8a0'); g.addColorStop(0.2,'#ffd700');
  g.addColorStop(0.5,'#b8860b'); g.addColorStop(0.8,'#ffd700');
  g.addColorStop(1,'#8b6914');
  ctx.shadowBlur = cfg.glow + 4; ctx.shadowColor = '#ffd700';
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 32. Chrome ──────────────────────────────────────────────── */
function fx3D_metalChrome(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = '#333'; ctx.fillText(txt, W/2+i*0.5, H/2+i*0.5);
  }
  const g = ctx.createLinearGradient(0, H*0.2, 0, H*0.8);
  g.addColorStop(0,'#ffffff'); g.addColorStop(0.15,'#e0e0e0');
  g.addColorStop(0.4,'#888'); g.addColorStop(0.6,'#ccc');
  g.addColorStop(0.85,'#666'); g.addColorStop(1,'#aaa');
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = '#ccc';
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 33. Silver ──────────────────────────────────────────────── */
function fx3D_metalSilver(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = '#4a4a4a'; ctx.fillText(txt, W/2+i*0.5, H/2+i*0.5);
  }
  const g = ctx.createLinearGradient(0, H*0.15, 0, H*0.85);
  g.addColorStop(0,'#f5f5f5'); g.addColorStop(0.3,'#b0b0b0');
  g.addColorStop(0.55,'#d8d8d8'); g.addColorStop(0.8,'#909090');
  g.addColorStop(1,'#c8c8c8');
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2);
}

/* ── 34. Rose Gold ───────────────────────────────────────────── */
function fx3D_metalRose(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = '#7a3030'; ctx.fillText(txt, W/2+i*0.5, H/2+i*0.5);
  }
  const g = ctx.createLinearGradient(0, H*0.2, 0, H*0.8);
  g.addColorStop(0,'#ffe4e0'); g.addColorStop(0.3,'#e8a090');
  g.addColorStop(0.6,'#d4706a'); g.addColorStop(1,'#b86060');
  ctx.shadowBlur = cfg.glow + 2; ctx.shadowColor = '#e8a090';
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 35. Copper ──────────────────────────────────────────────── */
function fx3D_metalCopper(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = '#5c2e00'; ctx.fillText(txt, W/2+i*0.5, H/2+i*0.5);
  }
  const g = ctx.createLinearGradient(0, H*0.2, 0, H*0.8);
  g.addColorStop(0,'#ffcba4'); g.addColorStop(0.3,'#cd7f32');
  g.addColorStop(0.6,'#a05820'); g.addColorStop(1,'#cd7f32');
  ctx.shadowBlur = cfg.glow + 2; ctx.shadowColor = '#cd7f32';
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 36. Fire ────────────────────────────────────────────────── */
function fx3D_fire(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  // Ember shadow
  ctx.shadowBlur = 20 + cfg.glow; ctx.shadowColor = '#ff4400';
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = shadeColor('#ff6600', -i*3);
    ctx.fillText(txt, W/2, H/2 + i*0.5);
  }
  const g = ctx.createLinearGradient(0, H*0.3, 0, H*0.8);
  g.addColorStop(0,'#fff700'); g.addColorStop(0.4,'#ff6600');
  g.addColorStop(0.8,'#ff2200'); g.addColorStop(1,'#880000');
  ctx.fillStyle = g; ctx.shadowBlur = cfg.glow; ctx.shadowColor = '#ff6600';
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 37. Inferno ─────────────────────────────────────────────── */
function fx3D_inferno(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.globalAlpha = 0.3;
    ctx.fillStyle = '#330000';
    ctx.fillText(txt, W/2+i*0.7, H/2+i*0.7);
  }
  ctx.globalAlpha = 1;
  const g = ctx.createLinearGradient(0, 0, 0, H);
  g.addColorStop(0,'#ffffff'); g.addColorStop(0.2,'#ffff00');
  g.addColorStop(0.5,'#ff6600'); g.addColorStop(0.8,'#cc0000');
  g.addColorStop(1,'#330000');
  ctx.shadowBlur = 25 + cfg.glow; ctx.shadowColor = '#ff4400';
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 38. Plasma ──────────────────────────────────────────────── */
function fx3D_plasma(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  const g = ctx.createLinearGradient(W*0.05, 0, W*0.95, H);
  g.addColorStop(0,'#ff00ff'); g.addColorStop(0.25,'#8800ff');
  g.addColorStop(0.5,'#0088ff'); g.addColorStop(0.75,'#00ffff');
  g.addColorStop(1,'#ff00aa');
  ctx.shadowBlur = 20 + cfg.glow; ctx.shadowColor = '#8800ff';
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = '#220044'; ctx.globalAlpha = 0.2;
    ctx.fillText(txt, W/2+i*0.5, H/2+i*0.5);
  }
  ctx.globalAlpha = 1;
  ctx.fillStyle = g; ctx.shadowBlur = cfg.glow; ctx.shadowColor = '#ff00ff';
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 39. Lava ────────────────────────────────────────────────── */
function fx3D_lava(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = '#1a0000'; ctx.fillText(txt, W/2+i*0.6, H/2+i*0.6);
  }
  const g = ctx.createLinearGradient(0, H*0.2, 0, H*0.9);
  g.addColorStop(0,'#ff9900'); g.addColorStop(0.4,'#ff4400');
  g.addColorStop(0.7,'#cc1100'); g.addColorStop(1,'#550000');
  ctx.shadowBlur = 15 + cfg.glow; ctx.shadowColor = '#ff4400';
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 40. Ice Frost ───────────────────────────────────────────── */
function fx3D_iceFrost(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  ctx.shadowBlur = 18 + cfg.glow; ctx.shadowColor = '#aaddff';
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = '#003366'; ctx.globalAlpha = 0.2;
    ctx.fillText(txt, W/2+i*0.4, H/2+i*0.4);
  }
  ctx.globalAlpha = 1;
  const g = ctx.createLinearGradient(0, H*0.2, 0, H*0.8);
  g.addColorStop(0,'#ffffff'); g.addColorStop(0.3,'#b8e8ff');
  g.addColorStop(0.6,'#80ccff'); g.addColorStop(1,'#c8f0ff');
  ctx.fillStyle = g; ctx.shadowBlur = cfg.glow; ctx.shadowColor = '#aaddff';
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 41. Ice Crystal ─────────────────────────────────────────── */
function fx3D_iceCrystal(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  [[-3,-2,'rgba(180,230,255,.5)'],[3,2,'rgba(100,190,255,.4)'],[0,3,'rgba(200,240,255,.3)']].forEach(([ox,oy,c]) => {
    ctx.fillStyle = c; ctx.fillText(txt, W/2+ox, H/2+oy);
  });
  const g = ctx.createLinearGradient(0, 0, W, H);
  g.addColorStop(0,'#e0f8ff'); g.addColorStop(0.3,'#aae8ff');
  g.addColorStop(0.6,'#ffffff'); g.addColorStop(1,'#88ccee');
  ctx.shadowBlur = 14 + cfg.glow; ctx.shadowColor = '#88ccee';
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 42. Snowfall ────────────────────────────────────────────── */
function fx3D_snow(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  ctx.shadowBlur = 10 + cfg.glow; ctx.shadowColor = '#cceeFF';
  ctx.fillStyle = '#cceeff'; ctx.fillText(txt, W/2+2, H/2+2);
  ctx.fillStyle = '#eef8ff'; ctx.fillText(txt, W/2-1, H/2-1);
  ctx.shadowBlur = 0;
  ctx.fillStyle  = '#ffffff'; ctx.fillText(txt, W/2, H/2);
  // Snow dots
  for (let i = 0; i < 18; i++) {
    const sx = W*0.1 + Math.random()*W*0.8;
    const sy = H*0.05 + Math.random()*H*0.5;
    const sr = 1 + Math.random()*2.5;
    ctx.beginPath(); ctx.arc(sx,sy,sr,0,Math.PI*2);
    ctx.fillStyle = 'rgba(255,255,255,.7)'; ctx.fill();
  }
}

/* ── 43. 80s Retro ───────────────────────────────────────────── */
function fx3D_retro80s(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.55);
  fx3D_setupFont(ctx, cfg, fs);
  // Big bold extrude in hot pink / cyan
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = i % 2 === 0 ? '#ff00aa' : '#00ffee';
    ctx.fillText(txt, W/2 + i*0.7, H/2 + i*0.7);
  }
  const g = ctx.createLinearGradient(0, H*0.2, 0, H*0.8);
  g.addColorStop(0,'#ffffff'); g.addColorStop(0.4,'#ff00ff');
  g.addColorStop(1,'#ff6600');
  ctx.shadowBlur = 14 + cfg.glow; ctx.shadowColor = '#ff00ff';
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 44. Vintage ─────────────────────────────────────────────── */
function fx3D_vintage(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  // Sepia extrude
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = '#4a3000'; ctx.globalAlpha = 0.25;
    ctx.fillText(txt, W/2+i*0.6, H/2+i*0.6);
  }
  ctx.globalAlpha = 1;
  const g = ctx.createLinearGradient(0, H*0.2, 0, H*0.8);
  g.addColorStop(0,'#ffe8b0'); g.addColorStop(0.4,'#d4a060');
  g.addColorStop(1,'#8b5e20');
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = '#8b5e20';
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 45. Comic Pop ───────────────────────────────────────────── */
function fx3D_comicPop(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.6);
  fx3D_setupFont(ctx, cfg, fs);
  // Bold black outline
  ctx.strokeStyle = '#000'; ctx.lineWidth = cfg.depth * 0.8 + 4;
  ctx.lineJoin = 'round'; ctx.strokeText(txt, W/2, H/2);
  // Offset shadow
  ctx.fillStyle = '#000';
  ctx.fillText(txt, W/2 + cfg.depth * 0.5, H/2 + cfg.depth * 0.5);
  // Main bright fill
  ctx.fillStyle = fx3D_getMainFill(ctx, W, cfg);
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 46. Vaporwave ───────────────────────────────────────────── */
function fx3D_vaporwave(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = '#220044'; ctx.globalAlpha = 0.25;
    ctx.fillText(txt, W/2-i*0.5, H/2+i*0.5);
  }
  ctx.globalAlpha = 1;
  const g = ctx.createLinearGradient(0, H*0.2, 0, H*0.8);
  g.addColorStop(0,'#ff71ce'); g.addColorStop(0.4,'#b967ff');
  g.addColorStop(0.7,'#05ffa1'); g.addColorStop(1,'#01cdfe');
  ctx.shadowBlur = 20 + cfg.glow; ctx.shadowColor = '#b967ff';
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 47. Clear Glass ─────────────────────────────────────────── */
function fx3D_glassEffect(ctx, W, H, txt, cfg, frosted) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  // Shadow extrude
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.fillStyle = 'rgba(0,0,0,0.08)'; ctx.fillText(txt, W/2+i*0.4, H/2+i*0.4);
  }
  // Body
  ctx.globalAlpha = frosted ? 0.5 : 0.25;
  ctx.fillStyle = '#c8e8ff'; ctx.fillText(txt, W/2, H/2);
  ctx.globalAlpha = 1;
  // Edge highlight
  ctx.strokeStyle = 'rgba(255,255,255,.7)'; ctx.lineWidth = 1.5;
  ctx.strokeText(txt, W/2, H/2);
  ctx.strokeStyle = 'rgba(100,150,200,.4)'; ctx.lineWidth = 0.5;
  ctx.strokeText(txt, W/2+1.5, H/2+1.5);
  // Shimmer
  const g = ctx.createLinearGradient(W*0.1,H*0.15,W*0.5,H*0.45);
  g.addColorStop(0,'rgba(255,255,255,.9)'); g.addColorStop(1,'rgba(255,255,255,0)');
  ctx.fillStyle = g; ctx.globalAlpha = 0.6;
  ctx.fillText(txt, W/2, H/2); ctx.globalAlpha = 1;
}

/* ── 48. 3D Glass Block ──────────────────────────────────────── */
function fx3D_glassBlock(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.55);
  fx3D_setupFont(ctx, cfg, fs);
  // Extrude — dark tinted glass
  for (let i = cfg.depth; i >= 1; i--) {
    ctx.globalAlpha = 0.15; ctx.fillStyle = '#003355';
    ctx.fillText(txt, W/2+i*0.5, H/2+i*0.5);
  }
  ctx.globalAlpha = 1;
  fx3D_glassEffect(ctx, W, H, txt, cfg, false);
}

/* ── 49. Holo Scan ───────────────────────────────────────────── */
function fx3D_holoScan(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  // Scan lines (horizontal)
  ctx.save();
  ctx.fillStyle = cfg.mainColor; ctx.shadowBlur = 18; ctx.shadowColor = cfg.mainColor;
  ctx.globalAlpha = 0.9; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
  // Clip scan lines
  for (let y = H*0.15; y < H*0.85; y += 4) {
    ctx.globalAlpha = 0.08; ctx.fillStyle = '#000';
    ctx.fillRect(0, y, W, 1.5);
  }
  ctx.restore(); ctx.globalAlpha = 1;
  const g = ctx.createLinearGradient(0, H*0.2, W, H*0.8);
  g.addColorStop(0,'#00ffcc'); g.addColorStop(0.5,'#00bbff'); g.addColorStop(1,'#aa00ff');
  ctx.fillStyle = g; ctx.globalAlpha = 0.7;
  ctx.fillText(txt, W/2, H/2); ctx.globalAlpha = 1;
}

/* ── 50. Glitch ──────────────────────────────────────────────── */
function fx3D_glitch(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  // RGB glitch slices
  const slices = 5;
  for (let s = 0; s < slices; s++) {
    const sy = H * 0.1 + s * H * 0.16;
    const sh = H * 0.14;
    ctx.save(); ctx.rect(0, sy, W, sh); ctx.clip();
    const ox = (Math.random()-0.5) * cfg.depth * 2;
    ctx.fillStyle = 'rgba(255,0,80,.7)'; ctx.fillText(txt, W/2 + ox - 3, H/2);
    ctx.fillStyle = 'rgba(0,255,200,.7)'; ctx.fillText(txt, W/2 + ox + 3, H/2);
    ctx.restore();
  }
  ctx.fillStyle = fx3D_getMainFill(ctx, W, cfg);
  ctx.shadowBlur = cfg.glow; ctx.shadowColor = cfg.shadowColor;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── 51. Matrix ──────────────────────────────────────────────── */
function fx3D_matrix(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  // Matrix rain columns (subtle)
  ctx.font = '10px monospace'; ctx.fillStyle = 'rgba(0,255,70,.18)';
  for (let x = 10; x < W-10; x += 14) {
    for (let y = 10; y < H; y += 14) {
      ctx.fillText(String.fromCharCode(0x30A0 + Math.floor(Math.random()*96)), x, y);
    }
  }
  // Restore font
  fx3D_setupFont(ctx, cfg, fs);
  const g = ctx.createLinearGradient(0, H*0.2, 0, H*0.8);
  g.addColorStop(0,'#aaffaa'); g.addColorStop(0.4,'#00ff44');
  g.addColorStop(0.7,'#008800'); g.addColorStop(1,'#004400');
  ctx.shadowBlur = 14 + cfg.glow; ctx.shadowColor = '#00ff44';
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ══════════════════════════════════════════════════════════════
   NEW EFFECTS — 15 Additional Pro Designs
══════════════════════════════════════════════════════════════ */

/* Marble Stone */
function fx3D_marble(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.58);
  fx3D_setupFont(ctx, cfg, fs);
  const g = ctx.createLinearGradient(0, 0, W*1.2, H*1.2);
  g.addColorStop(0,   '#e8e8ec'); g.addColorStop(0.2, '#f8f8f8');
  g.addColorStop(0.38,'#c0c0cc'); g.addColorStop(0.5, '#fff');
  g.addColorStop(0.65,'#b4b4c0'); g.addColorStop(0.8, '#e0e0e8');
  g.addColorStop(1,   '#d0d0da');
  ctx.shadowColor = '#a0a0b0'; ctx.shadowBlur = 8 + cfg.glow;
  ctx.fillStyle = g;
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
  ctx.fillText(txt, W/2, H/2);
  ctx.save(); ctx.globalAlpha = 0.16; ctx.strokeStyle = '#fff'; ctx.lineWidth = 0.7;
  for (let i = -H; i < W+H; i += 16) { ctx.beginPath(); ctx.moveTo(i,0); ctx.lineTo(i+H,H); ctx.stroke(); }
  ctx.restore(); ctx.shadowBlur = 0;
}

/* Sunset Glow */
function fx3D_sunset(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.6);
  fx3D_setupFont(ctx, cfg, fs);
  const g = ctx.createLinearGradient(0, 0, W, 0);
  g.addColorStop(0,'#f97316'); g.addColorStop(0.35,'#f43f5e');
  g.addColorStop(0.65,'#ec4899'); g.addColorStop(1,'#a855f7');
  ctx.shadowColor = '#fb923c'; ctx.shadowBlur = 22 + cfg.glow;
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2);
  ctx.shadowColor = '#f43f5e'; ctx.shadowBlur = 8; ctx.globalAlpha = 0.35;
  ctx.fillText(txt, W/2, H/2); ctx.globalAlpha = 1; ctx.shadowBlur = 0;
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
}

/* Watercolor */
function fx3D_watercolor(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.6);
  fx3D_setupFont(ctx, cfg, fs);
  [14,10,7,4,2].forEach((blur, i) => {
    ctx.globalAlpha = 0.22 + i * 0.12;
    ctx.shadowColor = cfg.shadowColor; ctx.shadowBlur = blur + cfg.glow * 0.3;
    ctx.fillStyle = i % 2 === 0 ? cfg.mainColor : shadeColor(cfg.mainColor, 25);
    const jx = (i - 2) * 1.2, jy = (i % 3 - 1) * 1.2;
    ctx.fillText(txt, W/2 + jx, H/2 + jy);
  });
  ctx.globalAlpha = 1; ctx.shadowBlur = 0;
}

/* Liquid Metal */
function fx3D_liquidMetal(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.6);
  fx3D_setupFont(ctx, cfg, fs);
  const g = ctx.createLinearGradient(0, H*0.1, 0, H*0.9);
  g.addColorStop(0,'#e2e8f0'); g.addColorStop(0.2,'#94a3b8');
  g.addColorStop(0.42,'#f1f5f9'); g.addColorStop(0.6,'#64748b');
  g.addColorStop(0.8,'#e2e8f0'); g.addColorStop(1,'#94a3b8');
  ctx.shadowColor = cfg.extColor || '#6366f1'; ctx.shadowBlur = 10 + cfg.glow;
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2);
  ctx.fillStyle = 'rgba(255,255,255,.45)';
  ctx.shadowBlur = 3; ctx.shadowColor = '#fff';
  ctx.fillText(txt, W/2 - 1, H/2 - 1);
  ctx.shadowBlur = 0; fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
}

/* Galaxy */
function fx3D_galaxy(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.56);
  fx3D_setupFont(ctx, cfg, fs);
  const stars = [.12,.45,.78,.23,.89,.56,.34,.67,.91,.11,.52,.84];
  stars.forEach((v, i) => {
    ctx.fillStyle = `rgba(255,255,255,${.3+v*.55})`;
    ctx.beginPath(); ctx.arc(v*W, stars[(i+4)%12]*H, .5+v*.8, 0, Math.PI*2); ctx.fill();
  });
  const g = ctx.createLinearGradient(0, 0, W, H);
  g.addColorStop(0,'#818cf8'); g.addColorStop(0.3,'#a78bfa');
  g.addColorStop(0.65,'#c084fc'); g.addColorStop(1,'#f472b6');
  ctx.shadowColor = '#a78bfa'; ctx.shadowBlur = 20 + cfg.glow;
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2);
  ctx.shadowColor = '#fff'; ctx.shadowBlur = 5; ctx.globalAlpha = 0.3;
  ctx.fillStyle = '#fff'; ctx.fillText(txt, W/2, H/2);
  ctx.globalAlpha = 1; ctx.shadowBlur = 0;
}

/* Diamond */
function fx3D_diamond(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.6);
  fx3D_setupFont(ctx, cfg, fs);
  const g = ctx.createLinearGradient(0, 0, W, H);
  g.addColorStop(0,'#a5f3fc'); g.addColorStop(0.15,'#fff');
  g.addColorStop(0.32,'#7dd3fc'); g.addColorStop(0.5,'#e0f2fe');
  g.addColorStop(0.65,'#fff'); g.addColorStop(0.82,'#38bdf8'); g.addColorStop(1,'#a5f3fc');
  ctx.shadowColor = '#38bdf8'; ctx.shadowBlur = 18 + cfg.glow;
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2);
  ctx.fillStyle = 'rgba(255,255,255,.38)'; ctx.shadowBlur = 4; ctx.shadowColor = '#fff';
  ctx.fillText(txt, W/2 - 1.2, H/2 - 1.2); ctx.shadowBlur = 0;
  fx3D_drawOutline(ctx, W, H, txt, cfg, fs);
}

/* Glitter Gold */
function fx3D_glitter(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.6);
  fx3D_setupFont(ctx, cfg, fs);
  const g = ctx.createLinearGradient(0, 0, W, H);
  g.addColorStop(0,'#ffd700'); g.addColorStop(0.25,'#fff7a1');
  g.addColorStop(0.5,'#ffd700'); g.addColorStop(0.75,'#f59e0b'); g.addColorStop(1,'#ffd700');
  ctx.fillStyle = g; ctx.shadowColor = '#ffd700'; ctx.shadowBlur = 12 + cfg.glow;
  ctx.fillText(txt, W/2, H/2);
  const sx=[.1,.3,.5,.7,.9,.2,.6,.8,.15,.45,.85,.65];
  const sy=[.3,.6,.2,.7,.4,.8,.3,.5,.65,.25,.45,.75];
  sx.forEach((x, i) => {
    ctx.fillStyle = `rgba(255,255,210,${.4+sy[i]*.5})`;
    ctx.shadowColor = '#fff'; ctx.shadowBlur = 5;
    ctx.beginPath(); ctx.arc(x*W, sy[i]*H, 1.3, 0, Math.PI*2); ctx.fill();
  });
  ctx.shadowBlur = 0;
}

/* Spectrum / Rainbow */
function fx3D_rainbow(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.6);
  fx3D_setupFont(ctx, cfg, fs);
  const g = ctx.createLinearGradient(0, 0, W, 0);
  ['#ff0000','#ff7700','#ffff00','#00ff44','#0077ff','#7700ff','#ff00ff']
    .forEach((c, i) => g.addColorStop(i/6, c));
  ctx.strokeStyle = 'rgba(0,0,0,.22)'; ctx.lineWidth = fs * .07; ctx.lineJoin = 'round';
  ctx.strokeText(txt, W/2, H/2);
  ctx.fillStyle = g; ctx.shadowColor = '#fff'; ctx.shadowBlur = 5 + cfg.glow;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* Laser */
function fx3D_laser(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.6);
  fx3D_setupFont(ctx, cfg, fs);
  ctx.shadowColor = '#00ffff'; ctx.shadowBlur = 22 + cfg.glow;
  ctx.fillStyle = '#00ffff'; ctx.globalAlpha = 0.45; ctx.fillText(txt, W/2, H/2);
  ctx.shadowColor = '#ff00ff'; ctx.shadowBlur = 16; ctx.globalAlpha = 0.38;
  ctx.fillStyle = '#ff00ff'; ctx.fillText(txt, W/2+1, H/2); ctx.fillText(txt, W/2-1, H/2);
  ctx.globalAlpha = 1; ctx.shadowColor = '#fff'; ctx.shadowBlur = 5;
  ctx.fillStyle = '#ffffff'; ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* Graffiti */
function fx3D_graffiti(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.62);
  fx3D_setupFont(ctx, cfg, fs);
  ctx.strokeStyle = '#000'; ctx.lineWidth = fs * .13; ctx.lineJoin = 'round';
  ctx.strokeText(txt, W/2, H/2);
  ctx.strokeStyle = '#fff'; ctx.lineWidth = fs * .035; ctx.strokeText(txt, W/2, H/2);
  const g = ctx.createLinearGradient(0, H*.2, 0, H*.85);
  g.addColorStop(0, shadeColor(cfg.mainColor, 30));
  g.addColorStop(0.5, cfg.mainColor);
  g.addColorStop(1, shadeColor(cfg.mainColor, -25));
  ctx.fillStyle = g; ctx.shadowColor = cfg.mainColor; ctx.shadowBlur = 10 + cfg.glow;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* Stamp */
function fx3D_stamp(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.54);
  fx3D_setupFont(ctx, cfg, fs);
  ctx.strokeStyle = '#c00020'; ctx.lineWidth = fs * .11; ctx.lineJoin = 'round';
  ctx.strokeText(txt, W/2, H/2);
  ctx.fillStyle = '#c00020'; ctx.shadowColor = '#800010'; ctx.shadowBlur = 5 + cfg.glow;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
  ctx.strokeStyle = '#c00020'; ctx.lineWidth = 2.5; ctx.globalAlpha = 0.45;
  ctx.strokeRect(7, 4, W-14, H-8); ctx.globalAlpha = 1;
}

/* Chalk */
function fx3D_chalk(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.6);
  fx3D_setupFont(ctx, cfg, fs);
  ctx.strokeStyle = '#d4d4d0'; ctx.lineWidth = fs * .035;
  for (let i = 0; i < 4; i++) {
    ctx.globalAlpha = .2 + i * .14;
    ctx.strokeText(txt, W/2 + (i-1.5)*1.4, H/2 + (i%2-.5)*1.4);
  }
  ctx.globalAlpha = .88; ctx.fillStyle = '#f0f0ee';
  ctx.shadowColor = 'rgba(255,255,255,.28)'; ctx.shadowBlur = 4 + cfg.glow;
  ctx.fillText(txt, W/2, H/2); ctx.globalAlpha = 1; ctx.shadowBlur = 0;
}

/* Psychedelic */
function fx3D_psychedelic(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.58);
  fx3D_setupFont(ctx, cfg, fs);
  const cols = ['#ff006e','#fb5607','#ffbe0b','#3a86ff','#8338ec','#06d6a0'];
  cols.forEach((c, i) => {
    ctx.globalAlpha = .55; ctx.fillStyle = c;
    ctx.shadowColor = c; ctx.shadowBlur = 8 + cfg.glow;
    const off = (i - cols.length/2) * (fs * .055);
    ctx.fillText(txt, W/2 + off, H/2 + off * .45);
  });
  ctx.globalAlpha = 1; ctx.shadowBlur = 0;
  const g = ctx.createLinearGradient(0, 0, W, H);
  ['#ff006e','#8338ec','#3a86ff'].forEach((c,i) => g.addColorStop(i/2, c));
  ctx.fillStyle = g; ctx.fillText(txt, W/2, H/2);
}

/* Smoke */
function fx3D_smoke(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.6);
  fx3D_setupFont(ctx, cfg, fs);
  [18,13,9,5,2].forEach((blur, i) => {
    ctx.globalAlpha = .1 + i * .12;
    ctx.shadowColor = '#94a3b8'; ctx.shadowBlur = blur + cfg.glow * .3;
    ctx.fillStyle = `hsl(220,${18-i*3}%,${55+i*9}%)`;
    ctx.fillText(txt, W/2, H/2 - i * 2.5);
  });
  ctx.globalAlpha = 1; ctx.shadowBlur = 0;
}

/* Blueprint */
function fx3D_blueprint(ctx, W, H, txt, cfg) {
  const fs = Math.min(cfg.fontSize, H * 0.54);
  ctx.fillStyle = '#163460'; ctx.fillRect(0, 0, W, H);
  ctx.strokeStyle = 'rgba(90,155,240,.22)'; ctx.lineWidth = .5;
  for (let x=0; x<W; x+=10) { ctx.beginPath(); ctx.moveTo(x,0); ctx.lineTo(x,H); ctx.stroke(); }
  for (let y=0; y<H; y+=10) { ctx.beginPath(); ctx.moveTo(0,y); ctx.lineTo(W,y); ctx.stroke(); }
  fx3D_setupFont(ctx, cfg, fs);
  ctx.strokeStyle = '#5bb8ff'; ctx.lineWidth = 1.3; ctx.lineJoin = 'round';
  ctx.strokeText(txt, W/2, H/2);
  ctx.fillStyle = '#c8e8ff'; ctx.shadowColor = '#5bb8ff'; ctx.shadowBlur = 12 + cfg.glow;
  ctx.fillText(txt, W/2, H/2); ctx.shadowBlur = 0;
}

/* ── Color Utility Helpers ──────────────────────────────────── */
function shadeColor(hex, pct) {
  let c = hex.replace('#','');
  if (c.length === 3) c = c.split('').map(x=>x+x).join('');
  const num = parseInt(c, 16);
  const clamp = v => Math.max(0, Math.min(255, v));
  const r = clamp(((num>>16)&255) + pct);
  const g = clamp(((num>>8)&255)  + pct);
  const b = clamp((num&255)       + pct);
  return '#' + ((1<<24)|(r<<16)|(g<<8)|b).toString(16).slice(1);
}
function lerpColor(hex1, hex2, t) {
  const parse = h => { const c=h.replace('#',''); const l=c.length===3?c.split('').map(x=>x+x).join(''):c; return [parseInt(l.slice(0,2),16),parseInt(l.slice(2,4),16),parseInt(l.slice(4,6),16)]; };
  const [r1,g1,b1]=parse(hex1), [r2,g2,b2]=parse(hex2);
  const lerp=(a,b,t)=>Math.round(a+(b-a)*t);
  return '#'+[lerp(r1,r2,t),lerp(g1,g2,t),lerp(b1,b2,t)].map(v=>v.toString(16).padStart(2,'0')).join('');
}

/* ── Auto-init 3D UI when DOM ready ────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  // nothing needed — grid is built on open
});

/* ── Logo Studio Pro — iframe modal integration ────────────── */
function openLogoStudioPro() {
  closeEdPanel();
  const modal = document.getElementById('lspIframeModal');
  const frame = document.getElementById('lspIframe');
  // Build the URL from the page's own live location rather than a bare
  // relative path — avoids a scheme mismatch (http vs https) that CSP's
  // frame-src 'self' then rejects as cross-origin. Uses pathname (never
  // includes the query string) so a stray "/" in ?id=... can't confuse it.
  const dir = location.pathname.slice(0, location.pathname.lastIndexOf('/') + 1);
  frame.src = location.origin + dir + 'logo_designer_addition.php?from=editor&mode=iframe';
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeLspModal() {
  const modal = document.getElementById('lspIframeModal');
  const frame = document.getElementById('lspIframe');
  modal.style.display = 'none';
  frame.src = '';
  document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape' && document.getElementById('lspIframeModal').style.display !== 'none') {
    closeLspModal();
  }
});

window.addEventListener('message', function(e) {
  // Only accept images from our own Logo Studio page (any site could post here before).
  if (e.origin !== location.origin || !e.data) return;
  if (e.data.type === 'lspClose') { closeLspModal(); return; }
  if (e.data.type !== 'lspApply') return;
  const dataURL = e.data.dataURL;
  if (!dataURL) return;
  const imgEl = new Image();
  imgEl.onload = () => {
    const fImg = new fabric.Image(imgEl);
    const maxW = canvasW * 0.45, maxH = canvasH * 0.45;
    if (fImg.width > maxW) fImg.scaleToWidth(maxW);
    if (fImg.getScaledHeight() > maxH) fImg.scaleToHeight(maxH);
    fImg.set({ left: canvasW / 2, top: canvasH / 2, originX: 'center', originY: 'center' });
    canvas.add(fImg).setActiveObject(fImg).renderAll();
    histSave();
    showToast('Logo from Studio Pro applied! 🎉');
    closeLspModal();
  };
  imgEl.src = dataURL;
});

// ── Static-asset cache (Phase 1 of the offline/WebView caching plan) ──────
// See sw.js — static files only, every page/API call always hits the server
// fresh, so nothing here can ever show stale data.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js').catch(() => {});
  });
}
</script>

<!-- ═══════════ EDITOR PRO UI ═══════════ -->
<canvas id="proRulerTop" class="pro-ruler"></canvas>
<canvas id="proRulerLeft" class="pro-ruler"></canvas>
<div id="proRulerCorner" title="Ruler units — click to switch px / mm / in">PX</div>
<div id="proGuideTip"></div>
<div id="proDrop"><i class="bi bi-cloud-arrow-down" style="margin-right:.6rem;"></i> Drop images here to add them</div>

<div id="proStatus">
  <span class="hide-m"><i class="bi bi-crosshair"></i> <b id="proPos">—</b></span>
  <span class="sep hide-m"></span>
  <span id="proSelInfo">Nothing selected</span>
  <span class="grow"></span>
  <button onclick="proToggleRulers()" class="hide-m" title="Rulers & guides (Ctrl+R)"><i class="bi bi-rulers"></i></button>
  <button onclick="proToggleHistory()" title="History panel (Ctrl+Shift+H)"><i class="bi bi-clock-history"></i></button>
  <button onclick="proOpenCmd()" class="hide-m" title="Search all tools (Ctrl+K)"><i class="bi bi-search"></i></button>
  <span class="sep"></span>
  <button onclick="setZoom(Math.max(0.05, canvas.getZoom()/1.25))" title="Zoom out (Ctrl -)"><i class="bi bi-dash-lg"></i></button>
  <input type="range" id="proZoomRange" min="5" max="400" value="100" oninput="setZoom(this.value/100)" title="Zoom">
  <button onclick="setZoom(Math.min(4, canvas.getZoom()*1.25))" title="Zoom in (Ctrl +)"><i class="bi bi-plus-lg"></i></button>
  <b id="proZoomTxt" style="min-width:42px;text-align:right;">100%</b>
  <button onclick="setZoom('fit')" title="Fit to screen (Ctrl+0)">Fit</button>
  <button onclick="setZoom(1)" class="hide-m" title="Actual size (Ctrl+1)">1:1</button>
  <button onclick="proZoomToSelection()" class="hide-m" title="Zoom to selection (Ctrl+2)"><i class="bi bi-zoom-in"></i></button>
</div>

<div id="proCtxMenu" class="pro-menu" role="menu"></div>

<div id="proHistory">
  <div class="hd"><i class="bi bi-clock-history"></i> History <span style="font-weight:500;color:var(--pro-mute);font-size:.68rem;">click a step to go back</span><button onclick="proToggleHistory(false)" title="Close">✕</button></div>
  <div id="proHistList"></div>
</div>

<div id="proCmdOv" class="pro-ov" onclick="if(event.target===this)proCloseCmd()">
  <div class="pro-box" style="max-width:600px;">
    <input id="proCmdInput" type="text" placeholder="Search tools & actions…  (e.g. qr, align, export, undo)" autocomplete="off">
    <div id="proCmdList"></div>
    <div style="padding:.45rem .9rem;border-top:1px solid var(--pro-line);font-size:.7rem;color:var(--pro-mute);display:flex;gap:1rem;">
      <span><span class="pro-kbd">↑</span><span class="pro-kbd">↓</span> move</span><span><span class="pro-kbd">Enter</span> run</span><span><span class="pro-kbd">Esc</span> close</span>
      <span style="margin-left:auto;">Press <span class="pro-kbd">?</span> for all shortcuts</span>
    </div>
  </div>
</div>

<div id="proKeysOv" class="pro-ov" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="pro-box" style="max-width:820px;">
    <div class="pro-box-hd"><i class="bi bi-keyboard"></i> Keyboard shortcuts <button class="x" onclick="document.getElementById('proKeysOv').classList.remove('open')">✕</button></div>
    <div class="pro-box-bd"><div class="pro-keys" id="proKeysList"></div></div>
  </div>
</div>

<div id="proExportOv" class="pro-ov" onclick="if(event.target===this)proCloseExport()">
  <div class="pro-box">
    <div class="pro-box-hd"><i class="bi bi-box-arrow-up"></i> Export <button class="x" onclick="proCloseExport()">✕</button></div>
    <div class="pro-box-bd">
      <div class="pro-field"><label>Format</label>
        <div class="pro-chips" id="proExpFmt">
          <span class="pro-chip on" data-v="png">PNG</span><span class="pro-chip" data-v="jpeg">JPG</span><span class="pro-chip" data-v="webp">WebP</span>
          <span class="pro-chip" data-v="pdf">PDF</span><span class="pro-chip" data-v="svg">SVG</span>
        </div>
      </div>
      <div class="pro-field" id="proExpScaleRow"><label>Size / quality</label>
        <div class="pro-chips" id="proExpScale">
          <span class="pro-chip" data-v="1">1× screen</span><span class="pro-chip" data-v="2">2×</span><span class="pro-chip" data-v="3">3×</span><span class="pro-chip on" data-v="print">Print (300 DPI)</span>
        </div>
        <div class="pro-note" id="proExpDims"></div>
      </div>
      <div class="pro-field" id="proExpQualRow" style="display:none;"><label>JPG / WebP quality: <b id="proExpQualVal">92</b>%</label>
        <input type="range" id="proExpQual" min="40" max="100" value="92" style="width:100%;" oninput="document.getElementById('proExpQualVal').textContent=this.value">
      </div>
      <div class="pro-field">
        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;color:var(--pro-text);"><input type="checkbox" id="proExpTrans"> Transparent background <span class="pro-note" style="margin:0;">(PNG / WebP / SVG)</span></label>
        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;color:var(--pro-text);margin-top:.35rem;"><input type="checkbox" id="proExpSel"> Selected object(s) only</label>
      </div>
      <div class="pro-field"><label>File name</label><input type="text" id="proExpName"></div>
    </div>
    <div class="pro-actions"><button class="pro-btn" onclick="proCloseExport()">Cancel</button><button class="pro-btn primary" id="proExpGo" onclick="proDoExport()"><i class="bi bi-download"></i> Download</button></div>
  </div>
</div>

<div id="proQrOv" class="pro-ov" onclick="if(event.target===this)proCloseQr()">
  <div class="pro-box">
    <div class="pro-box-hd"><i class="bi bi-qr-code"></i> <span id="proQrTitle">QR Code / Barcode</span> <button class="x" onclick="proCloseQr()">✕</button></div>
    <div class="pro-box-bd">
      <div class="pro-field"><label>Type</label>
        <div class="pro-chips" id="proQrType">
          <span class="pro-chip on" data-v="qr">QR Code</span><span class="pro-chip" data-v="CODE128">Barcode (Code 128)</span><span class="pro-chip" data-v="CODE39">Code 39</span><span class="pro-chip" data-v="EAN13">EAN-13</span>
        </div>
      </div>
      <div class="pro-field"><label id="proQrValLbl">Text / link / ID number</label><input type="text" id="proQrVal" value="https://" maxlength="1000"></div>
      <div style="display:flex;gap:.8rem;">
        <div class="pro-field" style="flex:1;"><label>Colour</label><input type="color" id="proQrFg" value="#000000" style="width:100%;height:34px;"></div>
        <div class="pro-field" style="flex:1;"><label>Background</label><input type="color" id="proQrBg" value="#ffffff" style="width:100%;height:34px;"></div>
        <div class="pro-field" style="flex:1;" id="proQrEccRow"><label>Error correction</label>
          <select id="proQrEcc"><option value="L">Low</option><option value="M" selected>Medium</option><option value="Q">High</option><option value="H">Max (logo-safe)</option></select></div>
      </div>
      <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.78rem;margin-bottom:.6rem;" id="proQrTextRow"><input type="checkbox" id="proQrShowText" checked> Show the number under the barcode</label>
      <div id="proQrPreview"><span style="color:#64748b;font-size:.8rem;">Preview</span></div>
      <div class="pro-note" id="proQrMsg"></div>
    </div>
    <div class="pro-actions"><button class="pro-btn" onclick="proCloseQr()">Cancel</button><button class="pro-btn primary" id="proQrGo" onclick="proInsertQr()"><i class="bi bi-plus-lg"></i> <span id="proQrGoLbl">Add to design</span></button></div>
  </div>
</div>

<!-- ═══════════ EDITOR PRO 2 UI ═══════════ -->
<div id="p2PenBar">
  <i class="bi bi-vector-pen"></i> <span id="p2PenMsg">Pen: click = corner point · click &amp; drag = curve · click the first point to close</span>
  <button onclick="p2PenFinish(false)" title="Finish as an open line (Enter)">Done</button>
  <button onclick="p2PenUndoPoint()" title="Remove last point (Backspace)">⌫ Point</button>
  <button onclick="p2PenCancel()" title="Cancel (Esc)">Cancel</button>
</div>

<div id="p2AdjOv" class="pro-ov" onclick="if(event.target===this)p2AdjCancel()">
  <div class="pro-box">
    <div class="pro-box-hd"><i class="bi bi-sliders"></i> Image Adjustments <span style="font-weight:500;color:var(--pro-mute);font-size:.72rem;">live preview on the canvas</span><button class="x" onclick="p2AdjCancel()">✕</button></div>
    <div class="pro-box-bd">
      <div class="p2-btns" style="margin-bottom:.8rem;">
        <button class="p2-btn primary" onclick="p2AutoEnhance()"><i class="bi bi-magic"></i> Auto-enhance</button>
        <button class="p2-btn" onclick="p2AdjPreset('bright')">Brighten</button>
        <button class="p2-btn" onclick="p2AdjPreset('contrast')">More contrast</button>
        <button class="p2-btn" onclick="p2AdjPreset('faded')">Fix faded photo</button>
        <button class="p2-btn" onclick="p2AdjPreset('dark')">Lift shadows</button>
        <button class="p2-btn" onclick="p2AdjReset()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
      </div>
      <div class="p2-adj">
        <div>
          <div class="p2-title">Levels</div>
          <canvas id="p2Hist" width="512" height="160"></canvas>
          <div class="p2-lv"><div class="track"></div>
            <input type="range" class="b" id="p2LvB" min="0" max="254" value="0">
            <input type="range" class="g" id="p2LvG" min="10" max="990" value="500">
            <input type="range" class="w" id="p2LvW" min="1" max="255" value="255">
          </div>
          <div class="p2-row" style="margin-top:.4rem;"><label>Input</label><span class="val" id="p2LvBv">0</span><span style="flex:1;text-align:center;" class="val" id="p2LvGv">1.00</span><span class="val" id="p2LvWv">255</span></div>
          <div class="p2-row"><label>Output</label><input type="range" id="p2OutB" min="0" max="128" value="0"><input type="range" id="p2OutW" min="128" max="255" value="255"></div>
          <div class="p2-title" style="margin-top:.6rem;">Detail</div>
          <div class="p2-row"><label>Sharpen</label><input type="range" id="p2Sharp" min="0" max="100" value="0"><span class="val" id="p2SharpV">0</span></div>
          <div class="p2-row"><label>Saturation</label><input type="range" id="p2Sat" min="-100" max="100" value="0"><span class="val" id="p2SatV">0</span></div>
          <div class="p2-row"><label>Warmth</label><input type="range" id="p2Warm" min="-100" max="100" value="0"><span class="val" id="p2WarmV">0</span></div>
        </div>
        <div>
          <div class="p2-title">Curves <span style="text-transform:none;letter-spacing:0;font-weight:500;">click to add point · drag · double-click to remove</span></div>
          <div class="p2-ch" id="p2CurveCh"><span class="on" data-c="rgb">RGB</span><span data-c="r">Red</span><span data-c="g">Green</span><span data-c="b">Blue</span></div>
          <canvas id="p2Curve" width="512" height="512"></canvas>
        </div>
      </div>
    </div>
    <div class="pro-actions"><button class="pro-btn" onclick="p2AdjCancel()">Cancel</button><button class="pro-btn primary" onclick="p2AdjApply()"><i class="bi bi-check-lg"></i> Apply</button></div>
  </div>
</div>

<!-- ═══ LOGO STUDIO PRO — IFRAME MODAL ═══ -->
<div id="lspIframeModal" onclick="if(event.target===this)closeLspModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:3000;align-items:center;justify-content:center;padding:12px;">
  <div style="position:relative;width:100%;height:100%;max-width:1600px;border-radius:14px;overflow:hidden;box-shadow:0 30px 100px rgba(0,0,0,.95),0 0 0 1px rgba(168,85,247,.25);">
    <iframe id="lspIframe" src="" style="width:100%;height:100%;border:none;display:block;" allow="clipboard-write"></iframe>
    <button onclick="closeLspModal()" title="Close (Esc)"
      style="position:absolute;top:10px;right:10px;z-index:10;background:rgba(20,10,40,.85);border:1px solid #4c1d95;color:#c4b5fd;font-size:1.1rem;cursor:pointer;border-radius:50%;width:34px;height:34px;display:flex;align-items:center;justify-content:center;transition:all .15s;line-height:1;"
      onmouseover="this.style.background='#4c1d95'" onmouseout="this.style.background='rgba(20,10,40,.85)'">✕</button>
  </div>
</div>

<script>
/* ═════════════════════════════════════════════════════════════════════
   EDITOR PRO — Photoshop / CorelDRAW-style workflow on top of the editor:
   rulers + draggable guides, right-click menu, Ctrl+K command search,
   shortcut sheet, History panel, export dialog, QR / barcode generator,
   status bar with live zoom/position, drag-and-drop images.
   ═════════════════════════════════════════════════════════════════════ */
const $pro = id => document.getElementById(id);
const PRO_PREF_KEY = 'ed_pro_prefs';
let proPrefs = { rulers: true, unit: 'px', history: false };
try { Object.assign(proPrefs, JSON.parse(localStorage.getItem(PRO_PREF_KEY) || '{}')); } catch (_) {}
function proSavePrefs() { try { localStorage.setItem(PRO_PREF_KEY, JSON.stringify(proPrefs)); } catch (_) {} }

/* ── Guides (design units). Saved inside the design so they come back. ── */
let edGuides = [];
try { const g = ED.canvas_json ? JSON.parse(ED.canvas_json)._guides : null; if (Array.isArray(g)) edGuides = g.filter(x => x && (x.t === 'v' || x.t === 'h') && isFinite(x.p)); } catch (_) {}
const _origCanvasJSON = canvasJSON;
canvasJSON = function () { const j = _origCanvasJSON(); if (edGuides.length) j._guides = edGuides.map(g => ({ t: g.t, p: Math.round(g.p * 100) / 100 })); return j; };

canvas.on('after:render', opt => {
  if (opt.ctx !== canvas.contextContainer || !edGuides.length || !proPrefs.rulers) return;
  const ctx = opt.ctx, v = canvas.viewportTransform, z = v[0] || 1;
  ctx.save(); ctx.transform(v[0], v[1], v[2], v[3], v[4], v[5]);
  ctx.strokeStyle = '#06b6d4'; ctx.lineWidth = 1 / z; ctx.setLineDash([]);
  edGuides.forEach(g => { ctx.beginPath(); if (g.t === 'v') { ctx.moveTo(g.p, -1e4); ctx.lineTo(g.p, 1e4); } else { ctx.moveTo(-1e4, g.p); ctx.lineTo(1e4, g.p); } ctx.stroke(); });
  ctx.restore();
});
// Moving objects also snap to guides (in addition to canvas + other objects).
canvas.on('object:moving', e => {
  if (!snapEnabled || !edGuides.length || !proPrefs.rulers) return;
  const o = e.target, z = canvas.getZoom() || 1, T = 7 / z, b = o.getBoundingRect(true, true);
  let dx = null, dy = null;
  edGuides.forEach(g => {
    if (g.t === 'v') [b.left, b.left + b.width / 2, b.left + b.width].forEach(x => { const d = g.p - x; if (Math.abs(d) < T && (dx === null || Math.abs(d) < Math.abs(dx))) dx = d; });
    else [b.top, b.top + b.height / 2, b.top + b.height].forEach(y => { const d = g.p - y; if (Math.abs(d) < T && (dy === null || Math.abs(d) < Math.abs(dy))) dy = d; });
  });
  if (dx !== null) o.left += dx; if (dy !== null) o.top += dy;
  if (dx !== null || dy !== null) o.setCoords();
});

/* ── Layout of the fixed chrome (rulers + status bar) around the canvas area ── */
function proLayout() {
  const area = $pro('canvasArea'); if (!area) return;
  const r = area.getBoundingClientRect();
  // Rulers are a desktop tool — on phones they would only eat canvas space.
  const on = proPrefs.rulers && r.width > 0 && window.innerWidth > 900;
  const was = area.classList.contains('pro-rulers');
  const top = $pro('proRulerTop'), left = $pro('proRulerLeft'), corner = $pro('proRulerCorner'), st = $pro('proStatus');
  [top, left].forEach(el => el.classList.toggle('on', on));
  corner.classList.toggle('on', on);
  area.classList.toggle('pro-rulers', on);
  const dpr = window.devicePixelRatio || 1;
  if (on) {
    Object.assign(corner.style, { left: r.left + 'px', top: r.top + 'px' });
    Object.assign(top.style, { left: (r.left + 20) + 'px', top: r.top + 'px', width: Math.max(0, r.width - 20) + 'px' });
    Object.assign(left.style, { left: r.left + 'px', top: (r.top + 20) + 'px', height: Math.max(0, r.height - 20 - 28) + 'px' });
    top.width = Math.max(1, Math.round((r.width - 20) * dpr)); top.height = Math.round(20 * dpr);
    left.width = Math.round(20 * dpr); left.height = Math.max(1, Math.round((r.height - 48) * dpr));
    corner.textContent = proPrefs.unit.toUpperCase();
  }
  Object.assign(st.style, { left: r.left + 'px', width: r.width + 'px', top: (r.bottom - 28) + 'px', display: r.width > 0 ? 'flex' : 'none' });
  // Ruler padding changes the space available — keep "Fit" actually fitting.
  if (was !== on && document.getElementById('zoomSel')?.value === 'fit') setZoom('fit');
  proDrawRulers();
}
const _pxPerUnit = () => proPrefs.unit === 'mm' ? (_ncCurrentDpi || 96) / 25.4 : proPrefs.unit === 'in' ? (_ncCurrentDpi || 96) : 1;
let _proCursor = null;
function proDrawRulers() {
  if (!proPrefs.rulers) return;
  const topC = $pro('proRulerTop'), leftC = $pro('proRulerLeft');
  if (!topC.classList.contains('on')) return;
  const dpr = window.devicePixelRatio || 1, z = canvas.getZoom() || 1;
  const cr = canvas.lowerCanvasEl.getBoundingClientRect();
  const tr = topC.getBoundingClientRect(), lr = leftC.getBoundingClientRect();
  const ppu = _pxPerUnit();
  // choose a tick step that is ≥ 55 screen px apart
  const steps = [0.1, 0.25, 0.5, 1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000];
  let step = steps.find(s => s * ppu * z >= 55) || 10000;
  const sub = step >= 10 && String(step)[0] !== '2' && String(step)[0] !== '5' ? 10 : 5;
  const sel = canvas.getActiveObject();
  const sb = sel ? sel.getBoundingRect(true, true) : null;
  const draw = (c, horiz, originScreen, lengthPx) => {
    const ctx = c.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const W = horiz ? c.width / dpr : 20, H = horiz ? 20 : c.height / dpr;
    ctx.fillStyle = '#111827'; ctx.fillRect(0, 0, W, H);
    // design area band
    const a0 = originScreen, a1 = originScreen + canvasW * z, b0 = 0, len = horiz ? W : H;
    ctx.fillStyle = '#1f2937';
    if (horiz) ctx.fillRect(Math.max(0, a0), 0, Math.max(0, Math.min(len, a1) - Math.max(0, a0)), H);
    else ctx.fillRect(0, Math.max(0, originScreen), W, Math.max(0, Math.min(len, originScreen + canvasH * z) - Math.max(0, originScreen)));
    // selection band
    if (sb) {
      ctx.fillStyle = 'rgba(99,102,241,.35)';
      if (horiz) ctx.fillRect(originScreen + sb.left * z, 0, sb.width * z, H);
      else ctx.fillRect(0, originScreen + sb.top * z, W, sb.height * z);
    }
    ctx.strokeStyle = '#64748b'; ctx.fillStyle = '#94a3b8'; ctx.lineWidth = 1;
    ctx.font = '9px system-ui, sans-serif'; ctx.textBaseline = 'top';
    const unitPx = step * ppu * z, minor = unitPx / sub;
    const first = Math.floor(-originScreen / minor) - 1, last = Math.ceil((len - originScreen) / minor) + 1;
    ctx.beginPath();
    for (let i = first; i <= last; i++) {
      const s = Math.round(originScreen + i * minor) + 0.5;
      const major = i % sub === 0, half = !major && sub === 10 && i % 5 === 0;
      const tl = major ? 20 : half ? 9 : 5;
      if (horiz) { ctx.moveTo(s, 20); ctx.lineTo(s, 20 - tl); } else { ctx.moveTo(20, s); ctx.lineTo(20 - tl, s); }
      if (major) {
        const val = +(i / sub * step).toFixed(2);
        if (horiz) ctx.fillText(String(val), s + 3, 2);
        else { ctx.save(); ctx.translate(2, s + 3); ctx.rotate(Math.PI / 2); ctx.fillText(String(val), 0, -9); ctx.restore(); }
      }
    }
    ctx.stroke();
    // guide markers
    ctx.fillStyle = '#06b6d4';
    edGuides.forEach(g => {
      if ((g.t === 'v') !== horiz) return;
      const s = originScreen + g.p * z;
      ctx.beginPath();
      if (horiz) { ctx.moveTo(s - 4, 12); ctx.lineTo(s + 4, 12); ctx.lineTo(s, 20); } else { ctx.moveTo(12, s - 4); ctx.lineTo(12, s + 4); ctx.lineTo(20, s); }
      ctx.fill();
    });
    // cursor line
    if (_proCursor) {
      const s = originScreen + (horiz ? _proCursor.x : _proCursor.y) * z;
      ctx.strokeStyle = '#f59e0b'; ctx.beginPath();
      if (horiz) { ctx.moveTo(s, 0); ctx.lineTo(s, 20); } else { ctx.moveTo(0, s); ctx.lineTo(20, s); }
      ctx.stroke();
    }
  };
  draw(topC, true, cr.left - tr.left, 0);
  draw(leftC, false, cr.top - lr.top, 0);
}
let _proRaf = 0;
function proRefreshChrome() { if (_proRaf) return; _proRaf = requestAnimationFrame(() => { _proRaf = 0; proDrawRulers(); proStatus(); }); }
canvas.on('after:render', opt => { if (opt.ctx === canvas.contextContainer) proRefreshChrome(); });
document.getElementById('canvasArea').addEventListener('scroll', proRefreshChrome, { passive: true });
window.addEventListener('resize', () => setTimeout(proLayout, 60));
new ResizeObserver(() => proLayout()).observe(document.getElementById('canvasArea'));

function proToggleRulers(force) {
  proPrefs.rulers = typeof force === 'boolean' ? force : !proPrefs.rulers; proSavePrefs();
  proLayout(); canvas.requestRenderAll();
  showToast('Rulers & guides ' + (proPrefs.rulers ? 'ON — drag from a ruler to create a guide' : 'OFF'), 'info');
}
$pro('proRulerCorner').addEventListener('click', () => {
  proPrefs.unit = { px: 'mm', mm: 'in', in: 'px' }[proPrefs.unit] || 'px'; proSavePrefs(); proLayout();
  showToast('Ruler units: ' + proPrefs.unit, 'info');
});
function proFmt(vpx) { const u = proPrefs.unit, v = vpx / _pxPerUnit(); return (u === 'px' ? Math.round(v) : v.toFixed(u === 'mm' ? 1 : 2)) + ' ' + u; }

/* Drag from a ruler → new guide. Drag a guide marker on the ruler → move it.
   Drop it back onto the ruler (or outside the canvas) → delete it. */
function proRulerDrag(e, horiz) {
  e.preventDefault();
  const z = canvas.getZoom() || 1;
  const cr = canvas.lowerCanvasEl.getBoundingClientRect();
  const toDesign = ev => horiz ? (ev.clientY - cr.top) / z : (ev.clientX - cr.left) / z;
  const along = ev => horiz ? (ev.clientX - cr.left) / z : (ev.clientY - cr.top) / z;
  // pick an existing guide whose marker was grabbed (horizontal ruler holds vertical guides)
  const t = horiz ? 'v' : 'h';
  const pos = along(e);
  let g = edGuides.find(x => x.t === t && Math.abs(x.p - pos) * z < 6);
  const isNew = !g;
  if (isNew) { g = { t: horiz ? 'h' : 'v', p: toDesign(e) }; edGuides.push(g); }
  const tip = $pro('proGuideTip');
  const move = ev => {
    g.p = isNew ? toDesign(ev) : along(ev);
    if (ev.shiftKey) g.p = Math.round(g.p / 10) * 10;
    if (Math.abs(g.p - (g.t === 'v' ? canvasW / 2 : canvasH / 2)) * z < 5) g.p = g.t === 'v' ? canvasW / 2 : canvasH / 2;   // snap to centre
    tip.style.display = 'block'; tip.style.left = (ev.clientX + 12) + 'px'; tip.style.top = (ev.clientY + 12) + 'px';
    tip.textContent = (g.t === 'v' ? 'X ' : 'Y ') + proFmt(g.p);
    canvas.requestRenderAll(); proDrawRulers();
  };
  const up = ev => {
    document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', up);
    tip.style.display = 'none';
    const r = $pro('canvasArea').getBoundingClientRect();
    const overRuler = ev.clientY < r.top + 20 || ev.clientX < r.left + 20;
    const outside = g.p < -20 || g.p > (g.t === 'v' ? canvasW : canvasH) + 20;
    // A new guide dropped back on the ruler is cancelled; an existing one is
    // deleted by dragging it past the canvas edge (or double-clicking its marker).
    if ((isNew && overRuler) || outside) { edGuides = edGuides.filter(x => x !== g); showToast('Guide removed', 'info'); }
    canvas.requestRenderAll(); proDrawRulers(); markDirty();
  };
  document.addEventListener('pointermove', move); document.addEventListener('pointerup', up);
  move(e);
}
$pro('proRulerTop').addEventListener('pointerdown', e => proRulerDrag(e, true));
$pro('proRulerLeft').addEventListener('pointerdown', e => proRulerDrag(e, false));
['proRulerTop', 'proRulerLeft'].forEach((id, i) => $pro(id).addEventListener('dblclick', e => {
  const z = canvas.getZoom() || 1, cr = canvas.lowerCanvasEl.getBoundingClientRect();
  const horiz = i === 0, pos = horiz ? (e.clientX - cr.left) / z : (e.clientY - cr.top) / z;
  const g = edGuides.find(x => x.t === (horiz ? 'v' : 'h') && Math.abs(x.p - pos) * z < 6);
  if (g) { edGuides = edGuides.filter(x => x !== g); canvas.requestRenderAll(); proDrawRulers(); markDirty(); showToast('Guide removed', 'info'); }
}));
function proClearGuides() { if (!edGuides.length) return showToast('No guides', 'info'); edGuides = []; canvas.requestRenderAll(); proDrawRulers(); markDirty(); showToast('All guides cleared'); }
function proAddCenterGuides() { edGuides.push({ t: 'v', p: canvasW / 2 }, { t: 'h', p: canvasH / 2 }); if (!proPrefs.rulers) proToggleRulers(true); canvas.requestRenderAll(); proDrawRulers(); markDirty(); }

/* ── Status bar ── */
canvas.on('mouse:move', o => {
  const p = canvas.getPointer(o.e);
  _proCursor = { x: p.x, y: p.y };
  const el = $pro('proPos'); if (el) el.textContent = proFmt(p.x).replace(/ \w+$/, '') + ', ' + proFmt(p.y);
  proRefreshChrome();
});
canvas.on('mouse:out', () => { _proCursor = null; proRefreshChrome(); });
function proStatus() {
  const a = canvas.getActiveObject();
  const el = $pro('proSelInfo');
  if (el) {
    if (!a) el.innerHTML = 'Canvas <b>' + proFmt(canvasW).replace(/ \w+$/, '') + ' × ' + proFmt(canvasH) + '</b>';
    else {
      const b = a.getBoundingRect(true, true);
      const what = a.type === 'activeSelection' ? a.size() + ' objects' : escHtml(_layerLabel(a)).slice(0, 26);
      el.innerHTML = what + ' · <b>' + proFmt(b.width).replace(/ \w+$/, '') + ' × ' + proFmt(b.height) + '</b>' + (Math.round(a.angle || 0) ? ' · ' + Math.round(a.angle) + '°' : '');
    }
  }
  const z = canvas.getZoom();
  const zt = $pro('proZoomTxt'); if (zt) zt.textContent = Math.round(z * 100) + '%';
  const zr = $pro('proZoomRange'); if (zr && document.activeElement !== zr) zr.value = Math.round(z * 100);
}
canvas.on('selection:created', proRefreshChrome); canvas.on('selection:updated', proRefreshChrome); canvas.on('selection:cleared', proRefreshChrome);

function proZoomToSelection() {
  const a = canvas.getActiveObject();
  if (!a) { setZoom('fit'); return; }
  const area = $pro('canvasArea'), b = a.getBoundingRect(true, true);
  const z = Math.max(0.05, Math.min(4, (area.clientWidth - 140) / Math.max(b.width, 1), (area.clientHeight - 140) / Math.max(b.height, 1)));
  setZoom(z);
  requestAnimationFrame(() => {
    const cr = canvas.lowerCanvasEl.getBoundingClientRect(), ar = area.getBoundingClientRect();
    area.scrollLeft += (cr.left + (b.left + b.width / 2) * z) - (ar.left + ar.width / 2);
    area.scrollTop  += (cr.top + (b.top + b.height / 2) * z) - (ar.top + ar.height / 2);
  });
}

/* ── Keep-proportions for the W/H fields ── */
let proKeepRatio = true;
const _origApplyScale = applyScale;
applyScale = function (dim, val) {
  const obj = canvas.getActiveObject();
  if (!obj || !(val > 0)) return;
  if (!proKeepRatio) return _origApplyScale(dim, val);
  const br = obj.getBoundingRect(true, true);
  const f = dim === 'w' ? val / (br.width || 1) : val / (br.height || 1);
  obj.scaleX *= f; obj.scaleY *= f; obj.setCoords(); canvas.requestRenderAll();
  refreshProps(); histSaveSoon('Resize');
};
function proToggleRatio(btn) {
  proKeepRatio = !proKeepRatio;
  btn.innerHTML = proKeepRatio ? '<i class="bi bi-link-45deg"></i> Proportions locked' : '<i class="bi bi-link"></i> Free resize';
  btn.style.color = proKeepRatio ? '#a5b4fc' : '#94a3b8';
}

/* ── Right-click (and long-press) context menu ── */
const _proMenu = $pro('proCtxMenu');
function proMenuItem(icon, label, keys, fn, opts) {
  opts = opts || {};
  return { icon, label, keys, fn, danger: opts.danger, off: opts.off };
}
function proOpenMenu(x, y, items) {
  _proMenu.innerHTML = items.map((it, i) => it === '-' ? '<div class="ms"></div>'
    : `<div class="mi${it.danger ? ' danger' : ''}${it.off ? ' off' : ''}" data-i="${i}" role="menuitem"><i class="bi ${it.icon}"></i>${escHtml(it.label)}${it.keys ? '<span class="k">' + escHtml(it.keys) + '</span>' : ''}</div>`).join('');
  _proMenu.classList.add('open');
  const w = _proMenu.offsetWidth, h = _proMenu.offsetHeight;
  _proMenu.style.left = Math.max(6, Math.min(x, innerWidth - w - 6)) + 'px';
  _proMenu.style.top  = Math.max(6, Math.min(y, innerHeight - h - 6)) + 'px';
  _proMenu.onclick = ev => { const el = ev.target.closest('.mi'); if (!el) return; const it = items[+el.dataset.i]; proCloseMenu(); it && it.fn && it.fn(); };
}
function proCloseMenu() { _proMenu.classList.remove('open'); }
document.addEventListener('pointerdown', e => { if (!e.target.closest('#proCtxMenu')) proCloseMenu(); }, true);
window.addEventListener('blur', proCloseMenu);

function proCanvasMenu(clientX, clientY, nativeEvent) {
  const target = canvas.findTarget(nativeEvent, false);
  const sel = canvas.getActiveObjects();
  if (target && !sel.includes(target) && target.selectable !== false) { canvas.setActiveObject(target); canvas.requestRenderAll(); refreshProps(); }
  const a = canvas.getActiveObject();
  const n = canvas.getActiveObjects().length;
  const items = [];
  if (a) {
    items.push(proMenuItem('bi-scissors', 'Cut', 'Ctrl+X', cutSelected), proMenuItem('bi-files', 'Copy', 'Ctrl+C', copySelected));
  }
  items.push(proMenuItem('bi-clipboard', 'Paste', 'Ctrl+V', pasteSelected, { off: !clipboard }));
  if (a) {
    items.push(proMenuItem('bi-copy', 'Duplicate', 'Ctrl+D', dupSelected), '-');
    if (isTextObj(a)) items.push(proMenuItem('bi-cursor-text', 'Edit text', 'Dbl-click', () => { a.enterEditing(); a.selectAll(); canvas.requestRenderAll(); }), proMenuItem('bi-bezier2', 'Warp text…', '', openWarpModal));
    if (a.type === 'image') {
      if (a.data?.warpedFrom) items.push(proMenuItem('bi-pencil', 'Re-edit warped text', '', reEditWarp));
      if (a.data?.qr) items.push(proMenuItem('bi-qr-code', 'Edit QR / barcode…', '', () => proOpenQr(a)));
      items.push(proMenuItem('bi-crop', 'Crop photo', '', toggleCropMode), proMenuItem('bi-magic', 'Remove background', '', () => removeBgFromImage(+$pro('bgTol').value || 30)), proMenuItem('bi-circle', 'Mask: circle', '', () => applyClipMask('circle')));
    }
    if (a.data?.qr && a.type !== 'image') items.push(proMenuItem('bi-qr-code', 'Edit QR / barcode…', '', () => proOpenQr(a)));
    items.push('-',
      proMenuItem('bi-front', 'Bring to front', 'Ctrl+Shift+]', () => { canvas.bringToFront(a); canvas.renderAll(); histSave('Bring to front'); refreshLayers(); }),
      proMenuItem('bi-chevron-up', 'Bring forward', 'Ctrl+]', bringFwd),
      proMenuItem('bi-chevron-down', 'Send backward', 'Ctrl+[', sendBwd),
      proMenuItem('bi-back', 'Send to back', 'Ctrl+Shift+[', () => { canvas.sendToBack(a); canvas.renderAll(); histSave('Send to back'); refreshLayers(); }), '-');
    if (n > 1) items.push(proMenuItem('bi-collection', 'Group', 'Ctrl+G', groupSelected));
    if (a.type === 'group') items.push(proMenuItem('bi-collection-fill', 'Ungroup', 'Ctrl+Shift+G', ungroupSelected));
    items.push(
      proMenuItem('bi-align-center', n > 1 ? 'Align centres' : 'Centre on canvas', '', () => { alignObj('hcenter'); alignObj('vcenter'); }),
      proMenuItem('bi-symmetry-vertical', 'Flip horizontal', '', () => flipImg('x')),
      proMenuItem('bi-symmetry-horizontal', 'Flip vertical', '', () => flipImg('y')),
      proMenuItem('bi-zoom-in', 'Zoom to selection', 'Ctrl+2', proZoomToSelection),
      proMenuItem('bi-image', 'Export selection…', '', () => proOpenExport(true)),
      proMenuItem('bi-lock', 'Lock', '', toggleLockActive), '-',
      proMenuItem('bi-trash', n > 1 ? 'Delete ' + n + ' objects' : 'Delete', 'Del', deleteSelected, { danger: true }));
  } else {
    items.push(proMenuItem('bi-ui-checks-grid', 'Select all', 'Ctrl+A', () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'a', ctrlKey: true }))), '-',
      proMenuItem('bi-type', 'Add text', 'T', addText),
      proMenuItem('bi-image', 'Add image…', 'I', () => $pro('imgUpload').click()),
      proMenuItem('bi-qr-code', 'Add QR code / barcode…', '', () => proOpenQr()), '-',
      proMenuItem('bi-palette', 'Background colour…', '', () => { canvas.discardActiveObject(); refreshProps(null); openEdPanel(); setTimeout(() => $pro('pCanvasBg').click(), 50); }),
      proMenuItem('bi-grid-3x3', (gridOn ? 'Hide' : 'Show') + ' grid', 'G', toggleGrid),
      proMenuItem('bi-rulers', (proPrefs.rulers ? 'Hide' : 'Show') + ' rulers & guides', 'Ctrl+R', () => proToggleRulers()),
      proMenuItem('bi-plus-square-dotted', 'Add centre guides', '', proAddCenterGuides),
      proMenuItem('bi-eraser', 'Clear guides', '', proClearGuides, { off: !edGuides.length }), '-',
      proMenuItem('bi-arrows-fullscreen', 'Fit to screen', 'Ctrl+0', () => setZoom('fit')),
      proMenuItem('bi-box-arrow-up', 'Export…', 'Ctrl+E', () => proOpenExport()));
  }
  proOpenMenu(clientX, clientY, items);
}
canvas.upperCanvasEl.addEventListener('contextmenu', e => { e.preventDefault(); if (canvas.isDrawingMode) return; proCanvasMenu(e.clientX, e.clientY, e); });
// Long-press on touch screens opens the same menu.
(function () {
  let t = null, sx = 0, sy = 0;
  canvas.upperCanvasEl.addEventListener('touchstart', e => {
    if (e.touches.length !== 1 || canvas.isDrawingMode) return;
    const p = e.touches[0]; sx = p.clientX; sy = p.clientY;
    t = setTimeout(() => { t = null; proCanvasMenu(sx, sy, { clientX: sx, clientY: sy, target: canvas.upperCanvasEl }); }, 600);
  }, { passive: true });
  const cancel = e => { if (!t) return; const p = e.touches && e.touches[0]; if (!p || Math.hypot(p.clientX - sx, p.clientY - sy) > 10 || e.type !== 'touchmove') { clearTimeout(t); t = null; } };
  canvas.upperCanvasEl.addEventListener('touchmove', cancel, { passive: true });
  canvas.upperCanvasEl.addEventListener('touchend', cancel, { passive: true });
})();

/* ── Command palette (Ctrl+K) — every tool, searchable ── */
const PRO_CMDS = [
  ['File', 'bi-floppy', 'Save to gallery', 'Ctrl+S', () => saveToGallery()],
  ['File', 'bi-box-arrow-up', 'Export… (PNG / JPG / WebP / PDF / SVG)', 'Ctrl+E', () => proOpenExport()],
  ['File', 'bi-printer', 'Print', 'Ctrl+P', printCanvas],
  ['File', 'bi-filetype-pdf', 'Export PDF (print size)', '', exportPDF],
  ['File', 'bi-printer-fill', 'Export print-ready (bleed + crop marks)', '', exportPrintReady],
  ['File', 'bi-filetype-json', 'Export JSON backup', '', exportJSON],
  ['File', 'bi-plus-lg', 'New canvas…', '', openNewCanvasModal],
  ['File', 'bi-aspect-ratio', 'Resize canvas…', '', openResizeModal],
  ['Edit', 'bi-arrow-counterclockwise', 'Undo', 'Ctrl+Z', histUndo],
  ['Edit', 'bi-arrow-clockwise', 'Redo', 'Ctrl+Y', histRedo],
  ['Edit', 'bi-clock-history', 'History panel', 'Ctrl+Shift+H', () => proToggleHistory()],
  ['Edit', 'bi-files', 'Copy', 'Ctrl+C', copySelected],
  ['Edit', 'bi-clipboard', 'Paste', 'Ctrl+V', pasteSelected],
  ['Edit', 'bi-copy', 'Duplicate', 'Ctrl+D', dupSelected],
  ['Edit', 'bi-trash', 'Delete selection', 'Del', deleteSelected],
  ['Edit', 'bi-collection', 'Group', 'Ctrl+G', groupSelected],
  ['Edit', 'bi-collection-fill', 'Ungroup', 'Ctrl+Shift+G', ungroupSelected],
  ['Edit', 'bi-lock', 'Lock selection', '', toggleLockActive],
  ['Insert', 'bi-type', 'Add text', 'T', addText],
  ['Insert', 'bi-circle-half', 'Add curved text…', '', openCurvedTextModal],
  ['Insert', 'bi-distribute-vertical', 'Add vertical text', '', addVerticalText],
  ['Insert', 'bi-list-ul', 'Add bullet list…', '', promptBulletList],
  ['Insert', 'bi-badge-3d', 'Add 3D text (studio)…', '', open3DStudio],
  ['Insert', 'bi-image', 'Add image from computer…', 'I', () => $pro('imgUpload').click()],
  ['Insert', 'bi-link-45deg', 'Add image from link…', 'U', openUrlModal],
  ['Insert', 'bi-images', 'Add photos & stickers…', 'M', openStickerModal],
  ['Insert', 'bi-qr-code', 'Add QR code…', '', () => proOpenQr(null, 'qr')],
  ['Insert', 'bi-upc', 'Add barcode…', '', () => proOpenQr(null, 'CODE128')],
  ['Insert', 'bi-pentagon-fill', 'Open Logo Studio…', '', openLogoStudioPro],
  ['Insert', 'bi-square', 'Add rectangle', 'R', () => addShape('rect')],
  ['Insert', 'bi-circle', 'Add circle', 'C', () => addShape('circle')],
  ['Insert', 'bi-star', 'Add star', '', () => addShape('star')],
  ['Insert', 'bi-slash-lg', 'Add line', 'L', () => addShape('line')],
  ['Insert', 'bi-person-square', 'Add photo placeholder', '', () => addPlaceholder('photo')],
  ['Insert', 'bi-pen', 'Add signature placeholder', '', () => addPlaceholder('sign')],
  ['Tools', 'bi-cursor-fill', 'Select tool', 'V', () => setActiveTool('select')],
  ['Tools', 'bi-brush', 'Brush / draw', 'P', () => activateBrush('pencil')],
  ['Tools', 'bi-eraser', 'Eraser', 'E', toggleEraserMode],
  ['Tools', 'bi-eyedropper', 'Colour picker (eyedropper)', '', toggleEyedropper],
  ['Tools', 'bi-crop', 'Crop (select a photo to crop only it)', '', toggleCropMode],
  ['Tools', 'bi-magic', 'Remove photo background', '', () => removeBgFromImage(+$pro('bgTol').value || 30)],
  ['Tools', 'bi-bezier2', 'Warp text…', '', openWarpModal],
  ['Tools', 'bi-universal-access-circle', 'Accessibility (contrast) check', '', checkColorAccessibility],
  ['Tools', 'bi-aspect-ratio-fill', 'Check image resolution', '', checkImageResolution],
  ['Tools', 'bi-palette', 'CMYK print preview', '', toggleCMYKPreview],
  ['Tools', 'bi-phone-flip', 'Mockup preview', '', openMockupModal],
  ['Arrange', 'bi-align-start', 'Align left', '', () => alignObj('left')],
  ['Arrange', 'bi-align-center', 'Align horizontal centre', '', () => alignObj('hcenter')],
  ['Arrange', 'bi-align-end', 'Align right', '', () => alignObj('right')],
  ['Arrange', 'bi-align-top', 'Align top', '', () => alignObj('top')],
  ['Arrange', 'bi-align-middle', 'Align vertical centre', '', () => alignObj('vcenter')],
  ['Arrange', 'bi-align-bottom', 'Align bottom', '', () => alignObj('bottom')],
  ['Arrange', 'bi-distribute-horizontal', 'Distribute horizontally', '', () => distributeObjs('h')],
  ['Arrange', 'bi-distribute-vertical', 'Distribute vertically', '', () => distributeObjs('v')],
  ['Arrange', 'bi-symmetry-vertical', 'Flip horizontal', '', () => flipImg('x')],
  ['Arrange', 'bi-symmetry-horizontal', 'Flip vertical', '', () => flipImg('y')],
  ['Arrange', 'bi-front', 'Bring forward', 'Ctrl+]', bringFwd],
  ['Arrange', 'bi-back', 'Send backward', 'Ctrl+[', sendBwd],
  ['View', 'bi-arrows-fullscreen', 'Fit to screen', 'Ctrl+0', () => setZoom('fit')],
  ['View', 'bi-1-square', 'Actual size 100%', 'Ctrl+1', () => setZoom(1)],
  ['View', 'bi-zoom-in', 'Zoom to selection', 'Ctrl+2', proZoomToSelection],
  ['View', 'bi-grid-3x3', 'Toggle grid', 'G', toggleGrid],
  ['View', 'bi-rulers', 'Toggle rulers & guides', 'Ctrl+R', () => proToggleRulers()],
  ['View', 'bi-plus-square-dotted', 'Add centre guides', '', proAddCenterGuides],
  ['View', 'bi-eraser', 'Clear all guides', '', proClearGuides],
  ['View', 'bi-magnet', 'Toggle smart snap', 'H', toggleSnapGuides],
  ['View', 'bi-bounding-box', 'Toggle print guides (bleed / safe)', '', togglePrintGuides],
  ['View', 'bi-keyboard', 'Keyboard shortcuts', '?', () => proOpenKeys()],
].map(([group, icon, label, keys, run]) => ({ group, icon, label, keys, run }));

let _proCmdSel = 0, _proCmdHits = [];
function proOpenCmd() {
  proCloseMenu();
  $pro('proCmdOv').classList.add('open');
  const inp = $pro('proCmdInput'); inp.value = ''; proFilterCmd(''); inp.focus();   // focus now, so fast typing isn't lost
}
function proCloseCmd() { $pro('proCmdOv').classList.remove('open'); }
function _proScore(q, s) {
  s = s.toLowerCase(); if (!q) return 1;
  if (s.includes(q)) return 100 - s.indexOf(q);
  let i = 0, score = 0; for (const ch of s) { if (ch === q[i]) { i++; score += 1; if (i === q.length) return score; } }
  return 0;
}
function proFilterCmd(q) {
  q = q.trim().toLowerCase();
  _proCmdHits = PRO_CMDS.map(c => ({ c, s: Math.max(_proScore(q, c.label), _proScore(q, c.group + ' ' + c.label) * .8) })).filter(x => x.s > 0)
    .sort((a, b) => b.s - a.s).slice(0, 40).map(x => x.c);
  _proCmdSel = 0; proRenderCmd();
}
function proRenderCmd() {
  const list = $pro('proCmdList');
  list.innerHTML = _proCmdHits.length ? _proCmdHits.map((c, i) => `<div class="mi${i === _proCmdSel ? ' kb' : ''}" data-i="${i}"><i class="bi ${c.icon}"></i><span>${escHtml(c.label)}</span> <span class="grp">${c.group}</span>${c.keys ? `<span class="k">${escHtml(c.keys)}</span>` : ''}</div>`).join('')
    : '<div style="padding:1rem;color:var(--pro-mute);font-size:.85rem;">No matching tool</div>';
  list.querySelector('.kb')?.scrollIntoView({ block: 'nearest' });
}
$pro('proCmdInput').addEventListener('input', e => proFilterCmd(e.target.value));
$pro('proCmdInput').addEventListener('keydown', e => {
  if (e.key === 'ArrowDown') { e.preventDefault(); _proCmdSel = Math.min(_proCmdHits.length - 1, _proCmdSel + 1); proRenderCmd(); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); _proCmdSel = Math.max(0, _proCmdSel - 1); proRenderCmd(); }
  else if (e.key === 'Enter') { e.preventDefault(); const c = _proCmdHits[_proCmdSel]; proCloseCmd(); if (c) setTimeout(c.run, 10); }
  else if (e.key === 'Escape') { e.preventDefault(); proCloseCmd(); }
});
$pro('proCmdList').addEventListener('click', e => { const el = e.target.closest('.mi'); if (!el) return; const c = _proCmdHits[+el.dataset.i]; proCloseCmd(); if (c) setTimeout(c.run, 10); });

function proOpenKeys() {
  const extra = [['Edit', 'Nudge 1 px / 10 px', '← → ↑ ↓ / Shift+arrows'], ['Edit', 'Rename layer', 'Double-click in Layers'], ['View', 'Zoom', 'Ctrl + mouse wheel / pinch'],
    ['View', 'Pan the view', 'Space + drag'], ['View', 'Create guide', 'Drag from a ruler'], ['View', 'Delete guide', 'Drag it back onto the ruler'], ['Tools', 'Brush size', '[ and ]'],
    ['Tools', 'Context menu', 'Right-click / long-press'], ['Tools', 'Search all tools', 'Ctrl+K']];
  const rows = PRO_CMDS.filter(c => c.keys).map(c => [c.group, c.label, c.keys]).concat(extra);
  const groups = [...new Set(rows.map(r => r[0]))];
  $pro('proKeysList').innerHTML = groups.map(g => `<h4>${g}</h4>` + rows.filter(r => r[0] === g).map(r => `<div><span>${escHtml(r[1])}</span><span class="pro-kbd">${escHtml(r[2])}</span></div>`).join('')).join('');
  $pro('proKeysOv').classList.add('open');
}

/* ── History panel ── */
function proToggleHistory(force) {
  proPrefs.history = typeof force === 'boolean' ? force : !proPrefs.history; proSavePrefs();
  const p = $pro('proHistory');
  p.classList.toggle('open', proPrefs.history);
  if (proPrefs.history) {
    const r = $pro('canvasArea').getBoundingClientRect();
    p.style.left = Math.max(8, r.right - 262) + 'px'; p.style.top = (r.top + 30) + 'px';
    renderHistoryPanel();
  }
}
function renderHistoryPanel() {
  const list = $pro('proHistList'); if (!list || !proPrefs.history) return;
  const now = Date.now();
  const ago = t => { const s = Math.round((now - t) / 1000); return s < 60 ? s + 's' : s < 3600 ? Math.round(s / 60) + 'm' : Math.round(s / 3600) + 'h'; };
  list.innerHTML = hist.states.map((_, i) => `<div class="hi${i === hist.idx ? ' cur' : ''}${i > hist.idx ? ' future' : ''}" data-i="${i}"><i class="bi ${i === 0 ? 'bi-file-earmark' : 'bi-dot'}"></i>${escHtml(hist.labels[i] || 'Edit')}<span class="t">${hist.times[i] ? ago(hist.times[i]) : ''}</span></div>`).reverse().join('');
}
$pro('proHistList').addEventListener('click', e => { const el = e.target.closest('.hi'); if (el) histGoTo(+el.dataset.i); });

/* ── Export dialog ── */
let _proExp = { fmt: 'png', scale: 'print' };
function _proChips(id, key, after) {
  $pro(id).addEventListener('click', e => {
    const c = e.target.closest('.pro-chip'); if (!c) return;
    $pro(id).querySelectorAll('.pro-chip').forEach(x => x.classList.toggle('on', x === c));
    _proExp[key] = c.dataset.v; after && after();
  });
}
_proChips('proExpFmt', 'fmt', proExpUpdate); _proChips('proExpScale', 'scale', proExpUpdate);
$pro('proExpSel').addEventListener('change', proExpUpdate);
function _proExpMult() { return _proExp.scale === 'print' ? _printMultiplier() : +_proExp.scale; }
function proExpUpdate() {
  const f = _proExp.fmt;
  $pro('proExpScaleRow').style.display = (f === 'svg' || f === 'pdf') ? 'none' : '';
  $pro('proExpQualRow').style.display = (f === 'jpeg' || f === 'webp') ? '' : 'none';
  const tr = $pro('proExpTrans'); tr.disabled = (f === 'jpeg' || f === 'pdf'); if (tr.disabled) tr.checked = false;
  const a = canvas.getActiveObject(), sel = $pro('proExpSel');
  sel.disabled = !a || f === 'pdf' || f === 'svg'; if (sel.disabled) sel.checked = false;
  const b = sel.checked && a ? a.getBoundingRect(true, true) : { width: canvasW, height: canvasH };
  const m = _proExpMult();
  $pro('proExpDims').textContent = 'Output: ' + Math.round(b.width * m) + ' × ' + Math.round(b.height * m) + ' px';
}
function proOpenExport(selection) {
  proCloseMenu();
  $pro('proExpName').value = _fileBase();
  $pro('proExpTrans').checked = !canvas.backgroundColor || canvas.backgroundColor === 'transparent';
  $pro('proExportOv').classList.add('open');
  proExpUpdate();
  if (selection && canvas.getActiveObject()) { $pro('proExpSel').checked = true; proExpUpdate(); }
}
function proCloseExport() { $pro('proExportOv').classList.remove('open'); }
function proDoExport() {
  const f = _proExp.fmt, name = ($pro('proExpName').value.trim() || _fileBase()).replace(/[\\/:*?"<>|]+/g, '').slice(0, 80) || 'design';
  const trans = $pro('proExpTrans').checked, onlySel = $pro('proExpSel').checked;
  const q = +$pro('proExpQual').value / 100;
  if (f === 'pdf') { proCloseExport(); exportPDF(); return; }
  if (f === 'svg') {
    const orig = canvas.backgroundColor; if (trans) canvas.backgroundColor = '';
    try { const svg = canvas.toSVG({ width: canvasW, height: canvasH, viewBox: { x: 0, y: 0, width: canvasW, height: canvasH } }); _downloadBlob(new Blob([svg], { type: 'image/svg+xml' }), name + '.svg'); showToast('SVG exported'); }
    catch (e) { showToast('SVG export failed: ' + e.message, 'err'); }
    finally { canvas.backgroundColor = orig; canvas.requestRenderAll(); }
    proCloseExport(); return;
  }
  const btn = $pro('proExpGo'); btn.disabled = true;
  setTimeout(() => {
    try {
      const m = _proExpMult();
      let url;
      const a = canvas.getActiveObject();
      if (onlySel && a) {
        url = a.toDataURL({ format: f, quality: q, multiplier: m });
        if (f === 'jpeg') {   // object export is transparent → put it on white for JPG
          url = null;
          const img = a.toCanvasElement({ multiplier: m });
          const c = document.createElement('canvas'); c.width = img.width; c.height = img.height;
          const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(img, 0, 0);
          url = c.toDataURL('image/jpeg', q);
        }
      } else {
        const orig = canvas.backgroundColor;
        if (trans) canvas.backgroundColor = '';
        canvas.discardActiveObject();
        try { url = exportDataURL({ format: f, quality: q, multiplier: m }); }
        finally { canvas.backgroundColor = orig; canvas.requestRenderAll(); }
      }
      const a2 = document.createElement('a'); a2.href = url; a2.download = name + '.' + (f === 'jpeg' ? 'jpg' : f);
      document.body.appendChild(a2); a2.click(); a2.remove();
      showToast('Exported ' + (f === 'jpeg' ? 'JPG' : f.toUpperCase()));
      proCloseExport();
    } catch (e) {
      showToast('Export failed: ' + (/tainted|insecure/i.test(e.message) ? 'an image from another website blocks downloading — re-upload it' : e.message), 'err');
    } finally { btn.disabled = false; }
  }, 20);
}

/* ── QR code & barcode generator (vector — prints razor sharp) ── */
const PRO_LIBS = {
  qr: 'https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js',
  bar: 'https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js',
};
const _proLibP = {};
function proLoadLib(key) {
  if (key === 'qr' && window.qrcode) return Promise.resolve();
  if (key === 'bar' && window.JsBarcode) return Promise.resolve();
  return _proLibP[key] || (_proLibP[key] = new Promise((res, rej) => {
    const s = document.createElement('script'); s.src = PRO_LIBS[key]; s.async = true;
    s.onload = res; s.onerror = () => { delete _proLibP[key]; rej(new Error('Could not load the generator — check your internet connection')); };
    document.head.appendChild(s);
  }));
}
let _proQr = { type: 'qr', editing: null };
$pro('proQrType').addEventListener('click', e => { const c = e.target.closest('.pro-chip'); if (!c) return; _proQr.type = c.dataset.v; proQrTypeUI(); proQrPreview(); });
['proQrVal', 'proQrFg', 'proQrBg', 'proQrEcc', 'proQrShowText'].forEach(id => $pro(id).addEventListener('input', () => { clearTimeout(_proQr.t); _proQr.t = setTimeout(proQrPreview, 150); }));
function proQrTypeUI() {
  const qr = _proQr.type === 'qr';
  $pro('proQrType').querySelectorAll('.pro-chip').forEach(x => x.classList.toggle('on', x.dataset.v === _proQr.type));
  $pro('proQrEccRow').style.display = qr ? '' : 'none';
  $pro('proQrTextRow').style.display = qr ? 'none' : '';
  $pro('proQrValLbl').textContent = qr ? 'Text / link / ID number' : _proQr.type === 'EAN13' ? '12 or 13 digits' : 'Number or text (e.g. admission no.)';
}
function proOpenQr(obj, type) {
  proCloseMenu();
  _proQr.editing = obj && obj.data?.qr ? obj : null;
  const d = _proQr.editing ? obj.data.qr : null;
  _proQr.type = d ? d.type : (type || 'qr');
  $pro('proQrVal').value = d ? d.value : (_proQr.type === 'qr' ? 'https://' : '');
  $pro('proQrFg').value = d ? d.fg : '#000000';
  $pro('proQrBg').value = d ? d.bg : '#ffffff';
  $pro('proQrEcc').value = d ? (d.ecc || 'M') : 'M';
  $pro('proQrShowText').checked = d ? d.showText !== false : true;
  $pro('proQrTitle').textContent = _proQr.editing ? 'Edit QR code / barcode' : 'QR Code / Barcode';
  $pro('proQrGoLbl').textContent = _proQr.editing ? 'Update' : 'Add to design';
  proQrTypeUI();
  $pro('proQrOv').classList.add('open');
  setTimeout(() => { $pro('proQrVal').focus(); $pro('proQrVal').select(); }, 40);
  proQrPreview();
}
function proCloseQr() { $pro('proQrOv').classList.remove('open'); _proQr.editing = null; }
/** Build the SVG markup for the current settings. */
async function proQrSvg() {
  const val = $pro('proQrVal').value.trim(), fg = $pro('proQrFg').value, bg = $pro('proQrBg').value;
  if (!val) throw new Error('Enter the text or number to encode');
  if (_proQr.type === 'qr') {
    await proLoadLib('qr');
    const q = window.qrcode(0, $pro('proQrEcc').value);
    q.addData(unescape(encodeURIComponent(val)), 'Byte');   // UTF-8 (Hindi text works)
    q.make();
    const n = q.getModuleCount(), m = 4, size = n + m * 2;
    let d = '';
    for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (q.isDark(r, c)) d += `M${c + m} ${r + m}h1v1h-1z`;
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}" width="${size * 8}" height="${size * 8}" shape-rendering="crispEdges"><rect width="${size}" height="${size}" fill="${bg}"/><path d="${d}" fill="${fg}"/></svg>`;
  }
  await proLoadLib('bar');
  const el = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  let ok = true;
  window.JsBarcode(el, val, { format: _proQr.type, lineColor: fg, background: bg, displayValue: $pro('proQrShowText').checked, font: 'monospace', fontSize: 18, height: 80, margin: 10, valid: v => { ok = v; } });
  if (!ok) throw new Error(_proQr.type === 'EAN13' ? 'EAN-13 needs exactly 12 or 13 digits' : 'These characters cannot be used in this barcode type');
  el.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
  return new XMLSerializer().serializeToString(el);
}
async function proQrPreview() {
  const box = $pro('proQrPreview'), msg = $pro('proQrMsg'), go = $pro('proQrGo');
  try { const svg = await proQrSvg(); box.innerHTML = svg; msg.textContent = ''; go.disabled = false; }
  catch (e) { box.innerHTML = '<span style="color:#64748b;font-size:.8rem;">' + escHtml(e.message) + '</span>'; msg.textContent = ''; go.disabled = true; }
}
async function proInsertQr() {
  let svg;
  try { svg = await proQrSvg(); } catch (e) { showToast(e.message, 'err'); return; }
  const data = { qr: { type: _proQr.type, value: $pro('proQrVal').value.trim(), fg: $pro('proQrFg').value, bg: $pro('proQrBg').value, ecc: $pro('proQrEcc').value, showText: $pro('proQrShowText').checked } };
  fabric.loadSVGFromString(svg, (objs, opts) => {
    const g = fabric.util.groupSVGElements(objs, opts);
    const old = _proQr.editing;
    if (old && canvas.contains(old)) {
      // Keep the old code's position, size and stacking.
      const b = old.getBoundingRect(true, true), idx = canvas.getObjects().indexOf(old);
      g.scaleToWidth(b.width);
      g.set({ originX: 'center', originY: 'center', left: b.left + b.width / 2, top: b.top + b.height / 2, angle: 0,
              data: Object.assign({}, old.data, data, { name: data.qr.type === 'qr' ? 'QR code' : 'Barcode' }) });
      hist.locked = true; canvas.remove(old); canvas.add(g); canvas.moveTo(g, idx); hist.locked = false;
      histSave('Edit ' + (data.qr.type === 'qr' ? 'QR code' : 'barcode'));
    } else {
      const target = Math.min(canvasW, canvasH) * (data.qr.type === 'qr' ? 0.28 : 0.45);
      g.scaleToWidth(target);
      g.set({ originX: 'center', originY: 'center', left: canvasW / 2, top: canvasH / 2, data: Object.assign({ name: data.qr.type === 'qr' ? 'QR code' : 'Barcode' }, data) });
      histLabel('Add ' + (data.qr.type === 'qr' ? 'QR code' : 'barcode'));
      canvas.add(g);
    }
    canvas.setActiveObject(g).requestRenderAll(); refreshLayers();
    proCloseQr();
    showToast((data.qr.type === 'qr' ? 'QR code' : 'Barcode') + ' added — double-click it to edit');
  });
}
canvas.on('mouse:dblclick', e => { const o = e.target; if (o && o.data?.qr) proOpenQr(o); });

/* ── Drag & drop images from the computer ── */
(function () {
  const area = $pro('canvasArea'), ov = $pro('proDrop');
  let depth = 0;
  const hasFiles = e => [...(e.dataTransfer?.types || [])].includes('Files');
  area.addEventListener('dragenter', e => { if (!hasFiles(e)) return; e.preventDefault(); depth++; ov.classList.add('on'); });
  area.addEventListener('dragleave', e => { if (!hasFiles(e)) return; depth = Math.max(0, depth - 1); if (!depth) ov.classList.remove('on'); });
  area.addEventListener('dragover', e => { if (hasFiles(e)) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; } });
  area.addEventListener('drop', e => {
    if (!hasFiles(e)) return;
    e.preventDefault(); depth = 0; ov.classList.remove('on');
    const files = [...e.dataTransfer.files].filter(f => /^image\//.test(f.type));
    if (!files.length) { showToast('Only image files can be dropped here', 'err'); return; }
    const p = canvas.getPointer(e);
    files.slice(0, 10).forEach((file, i) => _readImageFile(file, el => {
      const img = new fabric.Image(el);
      _placeImage(img, file.name);
      img.set({ left: Math.max(0, Math.min(canvasW, p.x + i * 20)), top: Math.max(0, Math.min(canvasH, p.y + i * 20)) }).setCoords();
      canvas.requestRenderAll();
    }));
  });
})();

/* ── Better history labels for the main actions (shown in History panel / Undo tooltip) ── */
[['deleteSelected', 'Delete'], ['groupSelected', 'Group'], ['ungroupSelected', 'Ungroup'], ['flipImg', 'Flip'], ['alignObj', 'Align'],
 ['distributeObjs', 'Distribute'], ['applyGradient', 'Gradient fill'], ['removeGradient', 'Remove gradient'], ['applyPatternFill', 'Pattern fill'],
 ['toggleStyle', 'Text style'], ['applyBlendMode', 'Blend mode'], ['toggleShadow', 'Shadow'], ['applyTextEffect', 'Text effect'],
 ['applyClipMask', 'Clip mask'], ['removeClipMask', 'Remove mask'], ['applySpotColor', 'Spot colour'], ['applyPickedColor', 'Picked colour'],
 ['toggleTransparentBg', 'Background'], ['applyResize', 'Resize canvas'], ['bringFwd', 'Bring forward'], ['sendBwd', 'Send backward'],
 ['toggleVis', 'Show / hide layer'], ['toggleLock', 'Lock / unlock'], ['toggleLockActive', 'Lock'], ['pasteSelected', 'Paste'], ['dupSelected', 'Duplicate']]
.forEach(([fn, label]) => {
  const f = window[fn]; if (typeof f !== 'function') return;
  window[fn] = function () { histLabel(label); return f.apply(this, arguments); };
});
['applyProp', 'applyTextProp', 'applyImgFilter', 'updateShadow', 'setCanvasBg', 'applyRoundCorner', 'applyTextBg'].forEach(fn => {
  const f = window[fn]; if (typeof f !== 'function') return;
  const lbl = { applyProp: 'Change property', applyTextProp: 'Text', applyImgFilter: 'Image filter', updateShadow: 'Shadow', setCanvasBg: 'Background colour', applyRoundCorner: 'Corner radius', applyTextBg: 'Text background' }[fn];
  window[fn] = function () { _histSoonLabel = lbl; return f.apply(this, arguments); };
});

/* ── Pro keyboard shortcuts (run before the editor's own handler) ── */
document.addEventListener('keydown', e => {
  if ($pro('proCmdOv').classList.contains('open') || $pro('proQrOv').classList.contains('open') || $pro('proExportOv').classList.contains('open') || $pro('proKeysOv').classList.contains('open')) {
    if (e.key === 'Escape') { ['proCmdOv', 'proQrOv', 'proExportOv', 'proKeysOv'].forEach(id => $pro(id).classList.remove('open')); e.preventDefault(); e.stopImmediatePropagation(); }
    else if (e.key === 'Enter' && $pro('proExportOv').classList.contains('open') && e.target.tagName !== 'BUTTON') { e.preventDefault(); proDoExport(); e.stopImmediatePropagation(); }
    else if (!/INPUT|TEXTAREA|SELECT/.test(e.target.tagName)) e.stopImmediatePropagation();
    return;
  }
  if (_proMenu.classList.contains('open') && e.key === 'Escape') { proCloseMenu(); e.stopImmediatePropagation(); return; }
  const tag = (document.activeElement || {}).tagName;
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || canvas.getActiveObject()?.isEditing || _openModalId()) return;
  const k = (e.key || '').toLowerCase(), mod = e.ctrlKey || e.metaKey;
  let done = true;
  if (mod && k === 'k') proOpenCmd();
  else if (mod && k === 'e') proOpenExport();
  else if (mod && k === 'r') proToggleRulers();
  else if (mod && e.shiftKey && k === 'h') proToggleHistory();
  else if (mod && k === '2') proZoomToSelection();
  else if (!mod && !e.altKey && (e.key === '?' || (e.shiftKey && k === '/'))) proOpenKeys();
  else if (!mod && !e.altKey && e.key === '/') proOpenCmd();
  else done = false;
  if (done) { e.preventDefault(); e.stopImmediatePropagation(); }
}, true);

/* ── Init ── */
(function proInit() {
  // Toolbar: Export opens the new dialog; add Search, History and Shortcuts buttons.
  const pngBtn = document.querySelector('.ed-top-right .ed-btn[onclick="exportPng()"]');
  if (pngBtn) { pngBtn.setAttribute('onclick', 'proOpenExport()'); pngBtn.title = 'Export… PNG / JPG / WebP / PDF / SVG (Ctrl+E)'; pngBtn.innerHTML = '<i class="bi bi-box-arrow-up"></i>'; }
  const undoBtn = $pro('btnRedo');
  if (undoBtn) {
    const mk = (icon, title, fn, extra) => { const b = document.createElement('button'); b.className = 'ed-btn pro-top-btn'; b.title = title; b.innerHTML = '<i class="bi ' + icon + '"></i>' + (extra || ''); b.onclick = fn; return b; };
    undoBtn.after(mk('bi-search', 'Search all tools (Ctrl+K)', proOpenCmd), mk('bi-clock-history', 'History (Ctrl+Shift+H)', () => proToggleHistory()), mk('bi-keyboard', 'Keyboard shortcuts (?)', proOpenKeys));
  }
  // Keep-proportions toggle under W/H
  const grid = $pro('pW')?.closest('.props-grid2');
  if (grid) {
    const b = document.createElement('button');
    b.type = 'button'; b.style.cssText = 'background:#0f172a;border:1px solid #334155;color:#a5b4fc;border-radius:6px;padding:.2rem .5rem;font-size:.7rem;cursor:pointer;margin:-.3rem 0 .5rem;';
    b.innerHTML = '<i class="bi bi-link-45deg"></i> Proportions locked'; b.onclick = () => proToggleRatio(b);
    grid.after(b);
  }
  // QR / barcode button in the left toolbar (after Placeholder Slots)
  const slots = document.querySelector('.ed-tools .tool-btn[onclick*="slotFlyout"]');
  if (slots) {
    const b = document.createElement('button'); b.className = 'tool-btn c-util'; b.style.color = '#22d3ee';
    b.innerHTML = '<i class="bi bi-qr-code"></i><span class="tool-tooltip">QR Code / Barcode</span>';
    b.onclick = () => { closeFlyouts(); proOpenQr(); };
    slots.after(b);
    const tip = document.getElementById('g-tip');
    b.addEventListener('mouseenter', () => { if (!tip) return; tip.textContent = 'QR Code / Barcode'; tip.style.display = 'block'; const r = b.getBoundingClientRect(); tip.style.top = (r.top + r.height / 2 - 12) + 'px'; tip.style.left = (r.right + 10) + 'px'; });
    b.addEventListener('mouseleave', () => { if (tip) tip.style.display = 'none'; });
  }
  if (proPrefs.history) setTimeout(() => proToggleHistory(true), 300);
  setTimeout(proLayout, 50);
  setTimeout(proLayout, 600);
  // first-time hint
  try { if (!localStorage.getItem('ed_pro_hint')) { localStorage.setItem('ed_pro_hint', '1'); setTimeout(() => showToast('New: right-click for quick actions · Ctrl+K searches every tool · drag from the rulers for guides', 'info'), 1500); } } catch (_) {}
})();
</script>
<script>
/* ═════════════════════════════════════════════════════════════════════
   EDITOR PRO 2 — Pen / Bezier tool + point editing, shape boolean
   (Union / Subtract / Intersect / Exclude), Levels / Curves / Sharpen /
   Auto-enhance, text auto-fit + overflow warnings, brand colour palette,
   and editable layer effects (stroke, outer glow, inner glow,
   gradient overlay).
   ═════════════════════════════════════════════════════════════════════ */
const $p2 = id => document.getElementById(id);
const p2Clamp = (v, a, b) => Math.max(a, Math.min(b, v));

/* ─────────────────────────── 1. LAYER EFFECTS ENGINE ───────────────────────────
   Effects live in obj.data.fx, so they are saved, undoable and editable later.
   They are rendered at draw time on an off-screen bitmap at the CURRENT
   resolution (screen zoom or export multiplier), so they stay sharp in print. */
const P2_FX_DEFAULTS = {
  stroke:   { on: false, color: '#ffffff', size: 4, opacity: 100 },
  outer:    { on: false, color: '#fbbf24', size: 14, opacity: 85 },
  inner:    { on: false, color: '#ffffff', size: 10, opacity: 70 },
  overlay:  { on: false, c1: '#f59e0b', c2: '#ef4444', angle: 90, opacity: 100 },
};
function p2HasFx(fx) { return !!fx && ['stroke', 'outer', 'inner', 'overlay'].some(k => fx[k] && fx[k].on); }
function p2FxPad(fx) {   // design px of extra room needed around the object
  let p = 0;
  if (fx.stroke?.on) p = Math.max(p, +fx.stroke.size || 0);
  if (fx.outer?.on)  p = Math.max(p, (+fx.outer.size || 0) * 1.6);
  return p + 2;
}
function p2Rgba(hex, a) {
  const h = colorToHex(hex);
  return `rgba(${parseInt(h.slice(1, 3), 16)},${parseInt(h.slice(3, 5), 16)},${parseInt(h.slice(5, 7), 16)},${a})`;
}
function _p2Canvas(o, key, w, h) {
  o.__p2 = o.__p2 || {};
  let c = o.__p2[key];
  if (!c) c = o.__p2[key] = document.createElement('canvas');
  if (c.width !== w || c.height !== h) { c.width = w; c.height = h; } else c.getContext('2d').clearRect(0, 0, w, h);
  return c;
}
function p2DrawWithFx(obj, ctx, fx, origDraw) {
  const m = ctx.getTransform();
  let s = Math.hypot(m.a, m.b) || 1;                     // device px per local unit
  const sc = obj.getObjectScaling(), objScale = (Math.abs(sc.scaleX) + Math.abs(sc.scaleY)) / 2 || 1;
  const sw = obj.stroke && obj.strokeWidth ? obj.strokeWidth : 0;
  const lw = (obj.width || 1) + sw, lh = (obj.height || 1) + sw;
  // cap the bitmap so a huge export can't run out of memory
  const maxSide = 6000;
  if (lw * s > maxSide || lh * s > maxSide) s = maxSide / Math.max(lw, lh);
  const k = s / objScale;                                  // device px per design px
  const pad = Math.ceil(p2FxPad(fx) * k);
  const W = Math.max(1, Math.ceil(lw * s + pad * 2)), H = Math.max(1, Math.ceil(lh * s + pad * 2));
  const ox = pad + lw * s / 2, oy = pad + lh * s / 2;

  // a) the object itself
  const c1 = _p2Canvas(obj, 'c1', W, H), x1 = c1.getContext('2d');
  x1.save(); x1.setTransform(s, 0, 0, s, ox, oy); origDraw.call(obj, x1); x1.restore();

  // b) gradient overlay (inside the shape only)
  if (fx.overlay?.on) {
    const o = fx.overlay, a = (+o.angle || 0) * Math.PI / 180;
    const cx = ox, cy = oy, half = Math.abs(lw * s / 2 * Math.cos(a)) + Math.abs(lh * s / 2 * Math.sin(a));
    const g = x1.createLinearGradient(cx - Math.cos(a) * half, cy - Math.sin(a) * half, cx + Math.cos(a) * half, cy + Math.sin(a) * half);
    g.addColorStop(0, o.c1); g.addColorStop(1, o.c2);
    x1.save(); x1.globalCompositeOperation = 'source-atop'; x1.globalAlpha = p2Clamp((+o.opacity || 0) / 100, 0, 1);
    x1.fillStyle = g; x1.fillRect(0, 0, W, H); x1.restore();
  }
  // c) inner glow — blurred colour from the shape's edge inward
  if (fx.inner?.on) {
    const o = fx.inner, t = _p2Canvas(obj, 'tmp', W, H), xt = t.getContext('2d');
    xt.fillStyle = '#000'; xt.fillRect(0, 0, W, H);
    xt.globalCompositeOperation = 'destination-out'; xt.drawImage(c1, 0, 0); xt.globalCompositeOperation = 'source-over';
    x1.save(); x1.globalCompositeOperation = 'source-atop';
    x1.shadowColor = p2Rgba(o.color, p2Clamp((+o.opacity || 0) / 100, 0, 1));
    x1.shadowBlur = Math.max(1, (+o.size || 0) * k); x1.shadowOffsetX = W * 2;
    x1.drawImage(t, -W * 2, 0); x1.drawImage(t, -W * 2, 0);
    x1.restore();
  }
  // d) silhouette for stroke / outer glow (works for text, shapes AND photos)
  let fin = c1;
  if (fx.stroke?.on || fx.outer?.on) {
    fin = _p2Canvas(obj, 'fin', W, H);
    const xf = fin.getContext('2d');
    const sil = _p2Canvas(obj, 'sil', W, H), xs = sil.getContext('2d');
    if (fx.outer?.on) {
      const o = fx.outer;
      xs.drawImage(c1, 0, 0); xs.globalCompositeOperation = 'source-in'; xs.fillStyle = o.color; xs.fillRect(0, 0, W, H); xs.globalCompositeOperation = 'source-over';
      xf.save(); xf.globalAlpha = p2Clamp((+o.opacity || 0) / 100, 0, 1);
      xf.shadowColor = o.color; xf.shadowBlur = Math.max(1, (+o.size || 0) * k); xf.shadowOffsetX = W * 2;
      xf.drawImage(sil, -W * 2, 0); xf.drawImage(sil, -W * 2, 0);
      xf.restore();
      xs.clearRect(0, 0, W, H);
    }
    if (fx.stroke?.on) {
      const o = fx.stroke, r = Math.max(0.5, (+o.size || 0) * k);
      xs.drawImage(c1, 0, 0); xs.globalCompositeOperation = 'source-in'; xs.fillStyle = o.color; xs.fillRect(0, 0, W, H); xs.globalCompositeOperation = 'source-over';
      const n = Math.min(64, Math.max(16, Math.ceil(r * 2)));
      xf.save(); xf.globalAlpha = p2Clamp((+o.opacity || 0) / 100, 0, 1);
      // draw the silhouette around a circle (plus inner rings for thick strokes) → solid outline
      for (let ring = r; ring > 0; ring -= Math.max(1, r / 3)) for (let i = 0; i < n; i++) { const a = i / n * Math.PI * 2; xf.drawImage(sil, Math.cos(a) * ring, Math.sin(a) * ring); }
      xf.restore();
    }
    xf.drawImage(c1, 0, 0);
  }
  ctx.drawImage(fin, -ox / s, -oy / s, W / s, H / s);
}
function _p2WrapDraw(proto) {
  const orig = proto.drawObject;
  proto.drawObject = function (ctx, forClipping) {
    const fx = !forClipping && this.data && this.data.fx;
    if (!fx || !p2HasFx(fx) || this.type === 'activeSelection') return orig.call(this, ctx, forClipping);
    try { p2DrawWithFx(this, ctx, fx, orig); } catch (e) { orig.call(this, ctx, forClipping); }
  };
}
_p2WrapDraw(fabric.Object.prototype);
_p2WrapDraw(fabric.Group.prototype);
(function () {
  const so = fabric.Object.prototype.shouldCache;
  fabric.Object.prototype.shouldCache = function () { if (this.data && p2HasFx(this.data.fx)) { this.ownCaching = false; return false; } return so.call(this); };
  const sg = fabric.Group.prototype.shouldCache;
  const anyFx = g => g._objects.some(o => (o.data && p2HasFx(o.data.fx)) || (o._objects && anyFx(o)));
  fabric.Group.prototype.shouldCache = function () { if (anyFx(this)) { this.ownCaching = false; return false; } return sg.call(this); };
})();

/* ─────────────────────────── 2. TONE FILTER (Levels / Curves / Sharpen) ───────────────────────────
   One custom filter keeps every adjustment editable and saved with the design. */
fabric.filterBackend = new fabric.Canvas2dFilterBackend();   // custom filters run in 2D (works on every device)
function p2CurveLUT(pts) {
  // monotone cubic interpolation through the control points → 256-entry table
  const p = (pts && pts.length >= 2 ? pts : [[0, 0], [255, 255]]).slice().sort((a, b) => a[0] - b[0]);
  const n = p.length, xs = p.map(q => q[0]), ys = p.map(q => q[1]);
  const d = [], mm = [];
  for (let i = 0; i < n - 1; i++) d.push((ys[i + 1] - ys[i]) / ((xs[i + 1] - xs[i]) || 1));
  mm[0] = d[0]; mm[n - 1] = d[n - 2];
  for (let i = 1; i < n - 1; i++) mm[i] = d[i - 1] * d[i] <= 0 ? 0 : (d[i - 1] + d[i]) / 2;
  for (let i = 0; i < n - 1; i++) { if (d[i] === 0) { mm[i] = mm[i + 1] = 0; continue; } const a = mm[i] / d[i], b = mm[i + 1] / d[i], h = a * a + b * b; if (h > 9) { const t = 3 / Math.sqrt(h); mm[i] = t * a * d[i]; mm[i + 1] = t * b * d[i]; } }
  const lut = new Uint8ClampedArray(256);
  for (let x = 0; x < 256; x++) {
    if (x <= xs[0]) { lut[x] = ys[0]; continue; } if (x >= xs[n - 1]) { lut[x] = ys[n - 1]; continue; }
    let i = 0; while (x > xs[i + 1]) i++;
    const hh = xs[i + 1] - xs[i], t = (x - xs[i]) / hh, t2 = t * t, t3 = t2 * t;
    lut[x] = (2 * t3 - 3 * t2 + 1) * ys[i] + (t3 - 2 * t2 + t) * hh * mm[i] + (-2 * t3 + 3 * t2) * ys[i + 1] + (t3 - t2) * hh * mm[i + 1];
  }
  return lut;
}
const P2_TONE_DEFAULT = () => ({ levels: { b: 0, g: 1, w: 255, ob: 0, ow: 255 }, curves: { rgb: [[0, 0], [255, 255]], r: [[0, 0], [255, 255]], g: [[0, 0], [255, 255]], b: [[0, 0], [255, 255]] }, sharpen: 0, sat: 0, warm: 0 });
fabric.Image.filters.ToneLUT = fabric.util.createClass(fabric.Image.filters.BaseFilter, {
  type: 'ToneLUT',
  levels: null, curves: null, sharpen: 0, sat: 0, warm: 0,
  initialize: function (opts) { Object.assign(this, P2_TONE_DEFAULT(), opts || {}); },
  _tables: function () {
    const L = this.levels, C = this.curves;
    const lv = new Uint8ClampedArray(256), range = Math.max(1, L.w - L.b);
    for (let v = 0; v < 256; v++) { let x = p2Clamp((v - L.b) / range, 0, 1); x = Math.pow(x, 1 / (L.g || 1)); lv[v] = L.ob + x * (L.ow - L.ob); }
    const all = p2CurveLUT(C.rgb), ch = [p2CurveLUT(C.r), p2CurveLUT(C.g), p2CurveLUT(C.b)];
    return ch.map(t => { const o = new Uint8ClampedArray(256); for (let v = 0; v < 256; v++) o[v] = t[all[lv[v]]]; return o; });
  },
  applyTo2d: function (options) {
    const img = options.imageData, d = img.data, [tr, tg, tb] = this._tables();
    const sat = 1 + (this.sat || 0) / 100, warm = (this.warm || 0) * 0.3;
    for (let i = 0; i < d.length; i += 4) {
      let r = tr[d[i]], g = tg[d[i + 1]], b = tb[d[i + 2]];
      if (sat !== 1) { const l = 0.299 * r + 0.587 * g + 0.114 * b; r = l + (r - l) * sat; g = l + (g - l) * sat; b = l + (b - l) * sat; }
      if (warm) { r += warm; b -= warm; }
      d[i] = r; d[i + 1] = g; d[i + 2] = b;
    }
    const amt = (this.sharpen || 0) / 100 * 1.4;
    if (amt > 0) {   // unsharp mask, 3×3
      const w = img.width, h = img.height, src = new Uint8ClampedArray(d);
      for (let y = 1; y < h - 1; y++) for (let x = 1; x < w - 1; x++) {
        const i = (y * w + x) * 4;
        for (let c = 0; c < 3; c++) {
          const v = src[i + c], blur = (src[i + c - 4] + src[i + c + 4] + src[i + c - w * 4] + src[i + c + w * 4]) / 4;
          d[i + c] = v + (v - blur) * amt;
        }
      }
    }
  },
  isNeutralState: function () {
    const L = this.levels, C = this.curves, id = p => p.length === 2 && p[0][0] === 0 && p[0][1] === 0 && p[1][0] === 255 && p[1][1] === 255;
    return L.b === 0 && L.w === 255 && L.g === 1 && L.ob === 0 && L.ow === 255 && id(C.rgb) && id(C.r) && id(C.g) && id(C.b) && !this.sharpen && !this.sat && !this.warm;
  },
  toObject: function () { return { type: this.type, levels: this.levels, curves: this.curves, sharpen: this.sharpen, sat: this.sat, warm: this.warm }; },
});
fabric.Image.filters.ToneLUT.fromObject = fabric.Image.filters.BaseFilter.fromObject;

/* ─────────────────────────── 3. PEN / BEZIER TOOL + POINT EDITING ─────────────────────────── */
const pen = { active: false, mode: null, pts: [], closed: false, drag: null, hover: null, sel: -1, target: null, cursor: null, style: null };
const _pt = (x, y) => ({ x, y, ix: x, iy: y, ox: x, oy: y });
function p2PenPathData(pts, closed) {
  if (!pts.length) return '';
  let d = `M ${pts[0].x} ${pts[0].y}`;
  const seg = (a, b) => (a.ox === a.x && a.oy === a.y && b.ix === b.x && b.iy === b.y) ? ` L ${b.x} ${b.y}` : ` C ${a.ox} ${a.oy} ${b.ix} ${b.iy} ${b.x} ${b.y}`;
  for (let i = 1; i < pts.length; i++) d += seg(pts[i - 1], pts[i]);
  if (closed && pts.length > 2) d += seg(pts[pts.length - 1], pts[0]) + ' Z';
  return d;
}
function p2PenBar(msg) {
  const bar = $p2('p2PenBar');
  bar.classList.toggle('on', pen.active);
  if (msg) $p2('p2PenMsg').innerHTML = msg;
  const r = $p2('canvasArea').getBoundingClientRect();
  bar.style.left = (r.left + r.width / 2 - bar.offsetWidth / 2) + 'px'; bar.style.top = (r.top + 28) + 'px';
}
function p2PenStart() {
  if (pen.active) { p2PenFinish(false); return; }
  setActiveTool('select'); closeFlyouts();
  canvas.discardActiveObject();
  Object.assign(pen, { active: true, mode: 'draw', pts: [], closed: false, drag: null, hover: null, sel: -1, target: null });
  canvas.selection = false; canvas.skipTargetFind = true; canvas.defaultCursor = 'crosshair'; canvas.hoverCursor = 'crosshair';
  document.body.classList.add('pen-mode');
  $p2('tPen')?.classList.add('active');
  p2PenBar('Pen: <b>click</b> = corner point · <b>click &amp; drag</b> = smooth curve · click the <b>first point</b> to close · <b>Enter</b> = finish');
  canvas.requestRenderAll();
}
function _p2PenStop() {
  pen.active = false; pen.mode = null; pen.pts = []; pen.drag = null; pen.target = null; pen.sel = -1;
  canvas.selection = true; canvas.skipTargetFind = false; canvas.defaultCursor = 'default'; canvas.hoverCursor = 'move';
  document.body.classList.remove('pen-mode');
  $p2('tPen')?.classList.remove('active');
  p2PenBar(); canvas.requestRenderAll();
}
function p2PenCancel() {
  if (pen.mode === 'edit' && pen.target) { pen.target.set({ visible: true }); const t = pen.target; _p2PenStop(); canvas.setActiveObject(t); canvas.requestRenderAll(); return; }
  _p2PenStop();
}
function p2PenUndoPoint() { if (pen.mode === 'draw' && pen.pts.length) { pen.pts.pop(); canvas.requestRenderAll(); } }
/** Build a fabric.Path from the pen points (canvas coordinates). */
function _p2MakePath(pts, closed, style) {
  const p = new fabric.Path(p2PenPathData(pts, closed), Object.assign({
    fill: closed ? (document.getElementById('pFill')?.value || '#4f46e5') : '',
    stroke: '#1f2937', strokeWidth: Math.max(1, Math.round(Math.min(canvasW, canvasH) / 270)), strokeLineCap: 'round', strokeLineJoin: 'round',
  }, style || {}));
  p.setCoords();
  p.data = Object.assign({}, p.data, { pen: { pts: pts.map(q => Object.assign({}, q)), closed: !!closed, m0: p.calcTransformMatrix() } });
  return p;
}
function p2PenFinish(close) {
  if (!pen.active) return;
  if (pen.mode === 'edit') { _p2EditCommit(); return; }
  // drop duplicate points (a double-click adds two clicks on the same spot)
  const pts = pen.pts.filter((q, i, a) => i === 0 || Math.hypot(q.x - a[i - 1].x, q.y - a[i - 1].y) > 0.5);
  if (pts.length < 2) { _p2PenStop(); showToast('Pen cancelled — place at least 2 points', 'info'); return; }
  const path = _p2MakePath(pts, close && pts.length > 2);
  _p2PenStop();
  histLabel(close ? 'Pen shape' : 'Pen line');
  canvas.add(path); canvas.setActiveObject(path).requestRenderAll();
  showToast((close ? 'Shape' : 'Line') + ' created — double-click it to edit its points', 'ok');
}
/** Points of an existing path / polygon in CANVAS coordinates (for editing). */
function _p2PointsOf(o) {
  const M = o.calcTransformMatrix(), tp = (x, y) => fabric.util.transformPoint({ x, y }, M);
  if (o.data?.pen?.pts && o.data.pen.m0) {
    const T = fabric.util.multiplyTransformMatrices(M, fabric.util.invertTransform(o.data.pen.m0));
    const f = (x, y) => fabric.util.transformPoint({ x, y }, T);
    return { closed: o.data.pen.closed, pts: o.data.pen.pts.map(q => { const a = f(q.x, q.y), i = f(q.ix, q.iy), b = f(q.ox, q.oy); return { x: a.x, y: a.y, ix: i.x, iy: i.y, ox: b.x, oy: b.y }; }) };
  }
  if (o.type === 'polygon' || o.type === 'polyline') {
    const off = o.pathOffset;
    return { closed: o.type === 'polygon', pts: o.points.map(q => { const a = tp(q.x - off.x, q.y - off.y); return _pt(a.x, a.y); }) };
  }
  if (o.type === 'path' && Array.isArray(o.path)) {
    const off = o.pathOffset, pts = []; let closed = false, start = null;
    const P = (x, y) => tp(x - off.x, y - off.y);
    for (const seg of o.path) {
      const c = seg[0];
      if (c === 'M') { if (pts.length) break; const a = P(seg[1], seg[2]); pts.push(_pt(a.x, a.y)); start = pts[0]; }
      else if (c === 'L') { const a = P(seg[1], seg[2]); pts.push(_pt(a.x, a.y)); }
      else if (c === 'C' || c === 'Q') {
        const last = pts[pts.length - 1];
        let c1, c2, e;
        if (c === 'C') { c1 = P(seg[1], seg[2]); c2 = P(seg[3], seg[4]); e = P(seg[5], seg[6]); }
        else { const q = P(seg[1], seg[2]); e = P(seg[3], seg[4]); c1 = { x: last.x + 2 / 3 * (q.x - last.x), y: last.y + 2 / 3 * (q.y - last.y) }; c2 = { x: e.x + 2 / 3 * (q.x - e.x), y: e.y + 2 / 3 * (q.y - e.y) }; }
        last.ox = c1.x; last.oy = c1.y;
        const n = _pt(e.x, e.y); n.ix = c2.x; n.iy = c2.y; pts.push(n);
      } else if (c === 'Z' || c === 'z') { closed = true; break; }
      else return null;   // arcs etc. — not editable
    }
    if (closed && pts.length > 2) {   // merge a closing point that duplicates the start
      const l = pts[pts.length - 1];
      if (Math.hypot(l.x - start.x, l.y - start.y) < 0.5) { start.ix = l.ix; start.iy = l.iy; pts.pop(); }
    }
    return pts.length >= 2 ? { closed, pts } : null;
  }
  return null;
}
function p2EditPoints(o) {
  o = o || canvas.getActiveObject();
  if (!o || !['path', 'polygon', 'polyline'].includes(o.type)) { showToast('Select a pen shape, path, star or polygon to edit its points', 'info'); return; }
  const r = _p2PointsOf(o);
  if (!r) { showToast('This shape contains arcs and cannot be point-edited', 'err'); return; }
  setActiveTool('select'); canvas.discardActiveObject();
  Object.assign(pen, { active: true, mode: 'edit', pts: r.pts, closed: r.closed, target: o, drag: null, sel: -1 });
  o.set({ visible: false });
  canvas.selection = false; canvas.skipTargetFind = true; canvas.defaultCursor = 'default';
  document.body.classList.add('pen-mode');
  p2PenBar('Edit points: <b>drag</b> points or handles · click the line to <b>add</b> a point · <b>double-click</b> a point = corner/smooth · <b>Del</b> = remove · <b>Enter</b> = done');
  canvas.requestRenderAll();
}
function _p2EditCommit() {
  const o = pen.target, pts = pen.pts, closed = pen.closed;
  if (!o) { _p2PenStop(); return; }
  const keep = { fill: o.fill, stroke: o.stroke, strokeWidth: o.strokeWidth, strokeDashArray: o.strokeDashArray, strokeLineCap: o.strokeLineCap, strokeLineJoin: o.strokeLineJoin,
    opacity: o.opacity, shadow: o.shadow, globalCompositeOperation: o.globalCompositeOperation, fillRule: o.fillRule };
  const np = _p2MakePath(pts, closed, keep);
  np.data = Object.assign({}, o.data, { pen: np.data.pen });
  const idx = canvas.getObjects().indexOf(o);
  hist.locked = true; canvas.remove(o); canvas.add(np); if (idx >= 0) canvas.moveTo(np, idx); hist.locked = false;
  _p2PenStop();
  canvas.setActiveObject(np).requestRenderAll();
  histSave('Edit points'); refreshLayers();
}
function _p2Bez(a, b, t) {   // point on segment a→b at t
  const u = 1 - t;
  return { x: u * u * u * a.x + 3 * u * u * t * a.ox + 3 * u * t * t * b.ix + t * t * t * b.x, y: u * u * u * a.y + 3 * u * u * t * a.oy + 3 * u * t * t * b.iy + t * t * t * b.y };
}
function _p2Split(a, b, t) {   // de Casteljau → new middle point, updates a.out / b.in
  const L = (p, q) => ({ x: p.x + (q.x - p.x) * t, y: p.y + (q.y - p.y) * t });
  const p0 = { x: a.x, y: a.y }, p1 = { x: a.ox, y: a.oy }, p2 = { x: b.ix, y: b.iy }, p3 = { x: b.x, y: b.y };
  const q0 = L(p0, p1), q1 = L(p1, p2), q2 = L(p2, p3), r0 = L(q0, q1), r1 = L(q1, q2), s = L(r0, r1);
  a.ox = q0.x; a.oy = q0.y; b.ix = q2.x; b.iy = q2.y;
  return { x: s.x, y: s.y, ix: r0.x, iy: r0.y, ox: r1.x, oy: r1.y };
}
function _p2Hit(p) {   // what is under the pointer in edit/draw mode
  const z = canvas.getZoom() || 1, R = 7 / z, pts = pen.pts;
  for (let i = 0; i < pts.length; i++) {
    const q = pts[i];
    if ((i === pen.sel || pen.mode === 'draw') && (q.ox !== q.x || q.oy !== q.y) && Math.hypot(p.x - q.ox, p.y - q.oy) < R) return { kind: 'out', i };
    if ((i === pen.sel || pen.mode === 'draw') && (q.ix !== q.x || q.iy !== q.y) && Math.hypot(p.x - q.ix, p.y - q.iy) < R) return { kind: 'in', i };
  }
  for (let i = 0; i < pts.length; i++) if (Math.hypot(p.x - pts[i].x, p.y - pts[i].y) < R) return { kind: 'anchor', i };
  if (pen.mode === 'edit') {
    const n = pts.length, segs = pen.closed ? n : n - 1;
    let best = null;
    for (let s = 0; s < segs; s++) {
      const a = pts[s], b = pts[(s + 1) % n];
      for (let k = 1; k < 40; k++) { const t = k / 40, q = _p2Bez(a, b, t), d = Math.hypot(p.x - q.x, p.y - q.y); if (d < R && (!best || d < best.d)) best = { kind: 'segment', i: s, t, d }; }
    }
    if (best) return best;
  }
  return null;
}
canvas.on('mouse:down', o => {
  if (!pen.active) return;
  const p = canvas.getPointer(o.e), z = canvas.getZoom() || 1;
  if (pen.mode === 'draw') {
    if (pen.pts.length >= 2 && Math.hypot(p.x - pen.pts[0].x, p.y - pen.pts[0].y) < 9 / z) { p2PenFinish(true); return; }
    const h = _p2Hit(p);
    if (h && (h.kind === 'in' || h.kind === 'out')) { pen.drag = h; return; }
    pen.pts.push(_pt(p.x, p.y)); pen.drag = { kind: 'new', i: pen.pts.length - 1 };
  } else {
    const h = _p2Hit(p);
    if (!h) { _p2EditCommit(); return; }
    if (h.kind === 'segment') {
      const n = pen.pts.length, a = pen.pts[h.i], b = pen.pts[(h.i + 1) % n];
      pen.pts.splice(h.i + 1, 0, _p2Split(a, b, h.t)); pen.sel = h.i + 1; pen.drag = { kind: 'anchor', i: h.i + 1, sx: p.x, sy: p.y };
    } else { if (h.kind === 'anchor') pen.sel = h.i; pen.drag = Object.assign(h, { sx: p.x, sy: p.y }); }
  }
  canvas.requestRenderAll();
});
canvas.on('mouse:move', o => {
  if (!pen.active) return;
  const p = canvas.getPointer(o.e); pen.cursor = p;
  const d = pen.drag;
  if (d) {
    const q = pen.pts[d.i];
    if (d.kind === 'new') { q.ox = p.x; q.oy = p.y; q.ix = 2 * q.x - p.x; q.iy = 2 * q.y - p.y; }
    else if (d.kind === 'anchor') { const dx = p.x - d.sx, dy = p.y - d.sy; d.sx = p.x; d.sy = p.y; q.x += dx; q.y += dy; q.ix += dx; q.iy += dy; q.ox += dx; q.oy += dy; }
    else {
      const k = d.kind === 'out' ? ['ox', 'oy', 'ix', 'iy'] : ['ix', 'iy', 'ox', 'oy'];
      q[k[0]] = p.x; q[k[1]] = p.y;
      if (!o.e.altKey) {   // keep the curve smooth: mirror the other handle (Alt = break into a corner)
        const len = Math.hypot(q[k[2]] - q.x, q[k[3]] - q.y) || Math.hypot(p.x - q.x, p.y - q.y);
        const ang = Math.atan2(p.y - q.y, p.x - q.x) + Math.PI;
        q[k[2]] = q.x + Math.cos(ang) * len; q[k[3]] = q.y + Math.sin(ang) * len;
      }
    }
  } else {
    const h = _p2Hit(p);
    canvas.setCursor(h ? (h.kind === 'segment' ? 'copy' : 'move') : pen.mode === 'draw' ? 'crosshair' : 'default');
    pen.hover = h;
  }
  canvas.requestRenderAll();
});
canvas.on('mouse:up', () => { if (pen.active) pen.drag = null; });
canvas.on('mouse:dblclick', o => {
  if (pen.active && pen.mode === 'draw') { p2PenFinish(false); return; }
  if (pen.active && pen.mode === 'edit') {
    const h = _p2Hit(canvas.getPointer(o.e));
    if (h && h.kind === 'anchor') {   // toggle corner ↔ smooth
      const q = pen.pts[h.i], n = pen.pts.length;
      if (q.ix !== q.x || q.iy !== q.y || q.ox !== q.x || q.oy !== q.y) { q.ix = q.ox = q.x; q.iy = q.oy = q.y; }
      else {
        const pr = pen.pts[(h.i - 1 + n) % n], nx = pen.pts[(h.i + 1) % n];
        const ax = nx.x - pr.x, ay = nx.y - pr.y, al = Math.hypot(ax, ay) || 1;
        const l1 = Math.hypot(q.x - pr.x, q.y - pr.y) / 3, l2 = Math.hypot(nx.x - q.x, nx.y - q.y) / 3;
        q.ix = q.x - ax / al * l1; q.iy = q.y - ay / al * l1; q.ox = q.x + ax / al * l2; q.oy = q.y + ay / al * l2;
      }
      canvas.requestRenderAll();
    }
    return;
  }
  const t = o.target;
  if (t && !t.data?.qr && ['path', 'polygon', 'polyline'].includes(t.type) && !t.data?.eraserPath) p2EditPoints(t);
});
canvas.on('after:render', opt => {
  if (!pen.active || opt.ctx !== canvas.contextContainer) return;
  const ctx = opt.ctx, v = canvas.viewportTransform, z = v[0] || 1, pts = pen.pts;
  ctx.save(); ctx.transform(v[0], v[1], v[2], v[3], v[4], v[5]);
  ctx.lineWidth = 1.5 / z; ctx.strokeStyle = '#22d3ee';
  if (pts.length) {
    ctx.beginPath(); const p = new Path2D(p2PenPathData(pts, pen.closed)); ctx.stroke(p);
    if (pen.mode === 'draw' && pen.cursor && !pen.drag) {   // rubber band to the cursor
      const l = pts[pts.length - 1]; ctx.setLineDash([4 / z, 4 / z]); ctx.beginPath(); ctx.moveTo(l.x, l.y);
      ctx.bezierCurveTo(l.ox, l.oy, pen.cursor.x, pen.cursor.y, pen.cursor.x, pen.cursor.y); ctx.stroke(); ctx.setLineDash([]);
    }
  }
  const sz = 4.5 / z;
  pts.forEach((q, i) => {
    const showH = pen.mode === 'draw' ? i >= pts.length - 2 : (i === pen.sel || i === pen.sel - 1 || i === pen.sel + 1);
    if (showH) {
      ctx.strokeStyle = '#a5f3fc'; ctx.lineWidth = 1 / z;
      [['ix', 'iy'], ['ox', 'oy']].forEach(([hx, hy]) => { if (q[hx] === q.x && q[hy] === q.y) return; ctx.beginPath(); ctx.moveTo(q.x, q.y); ctx.lineTo(q[hx], q[hy]); ctx.stroke(); ctx.beginPath(); ctx.arc(q[hx], q[hy], 3.5 / z, 0, Math.PI * 2); ctx.fillStyle = '#0e7490'; ctx.fill(); ctx.stroke(); });
    }
    const close = pen.mode === 'draw' && i === 0 && pts.length >= 2 && pen.cursor && Math.hypot(pen.cursor.x - q.x, pen.cursor.y - q.y) < 9 / z;
    ctx.fillStyle = i === pen.sel ? '#22d3ee' : '#ffffff'; ctx.strokeStyle = '#0e7490'; ctx.lineWidth = 1.5 / z;
    const s2 = close ? sz * 1.8 : sz;
    ctx.fillRect(q.x - s2, q.y - s2, s2 * 2, s2 * 2); ctx.strokeRect(q.x - s2, q.y - s2, s2 * 2, s2 * 2);
  });
  ctx.restore();
});

/* ─────────────────────────── 4. SHAPE BOOLEAN (Union / Subtract / Intersect / Exclude) ─────────────────────────── */
const P2_PAPER_URL = 'https://cdn.jsdelivr.net/npm/paper@0.12.18/dist/paper-core.min.js';
let _p2PaperP = null, _p2Scope = null;
function p2LoadPaper() {
  if (window.paper) return Promise.resolve();
  return _p2PaperP || (_p2PaperP = new Promise((res, rej) => {
    const s = document.createElement('script'); s.src = P2_PAPER_URL; s.async = true;
    s.onload = res; s.onerror = () => { _p2PaperP = null; rej(new Error('Could not load the shape engine — check your internet connection')); };
    document.head.appendChild(s);
  }));
}
const P2_BOOL_TYPES = ['rect', 'circle', 'ellipse', 'triangle', 'polygon', 'polyline', 'path', 'group'];
function _p2ToPaper(scope, o) {
  const svg = '<svg xmlns="http://www.w3.org/2000/svg">' + o.toSVG() + '</svg>';
  const item = scope.project.importSVG(svg, { expandShapes: true, insert: true });
  const parts = item.getItems({ class: scope.PathItem }).filter(p => !(p.parent instanceof scope.CompoundPath));
  if (!parts.length) { item.remove(); return null; }
  let r = parts[0].clone({ insert: false });
  for (let i = 1; i < parts.length; i++) r = r.unite(parts[i], { insert: false });
  item.remove();
  return r;
}
async function p2Boolean(op) {
  const sel = canvas.getActiveObjects();
  if (sel.length < 2) { showToast('Select 2 or more shapes (Shift+click), then choose Union / Subtract / Intersect / Exclude', 'info'); return; }
  const bad = sel.find(o => !P2_BOOL_TYPES.includes(o.type) || (o.type === 'group' && o.getObjects().some(c => isTextObj(c) || c.type === 'image')));
  if (bad) { showToast('Only shapes can be combined (not text or photos) — "' + _layerLabel(bad) + '" is not a shape', 'err'); return; }
  showToast('Combining shapes…', 'info');
  try { await p2LoadPaper(); } catch (e) { showToast(e.message, 'err'); return; }
  if (!_p2Scope) { _p2Scope = new paper.PaperScope(); _p2Scope.setup(document.createElement('canvas')); }
  const scope = _p2Scope;
  const all = canvas.getObjects();
  const objs = sel.slice().sort((a, b) => all.indexOf(a) - all.indexOf(b));   // back → front
  let res;
  try {
    const paths = objs.map(o => _p2ToPaper(scope, o));
    if (paths.some(p => !p)) throw new Error('One of the shapes has no outline');
    res = paths[0];
    for (let i = 1; i < paths.length; i++) {
      const p = paths[i];
      res = op === 'union' ? res.unite(p, { insert: false }) : op === 'subtract' ? res.subtract(p, { insert: false })
          : op === 'intersect' ? res.intersect(p, { insert: false }) : res.exclude(p, { insert: false });
    }
  } catch (e) { showToast('Could not combine: ' + e.message, 'err'); return; }
  const d = res.pathData;
  if (!d || res.isEmpty && res.isEmpty()) { showToast(op === 'intersect' ? 'These shapes do not overlap' : 'Nothing is left after this operation', 'err'); return; }
  const base = objs[0];
  const src = base.type === 'group' ? base.getObjects()[0] : base;
  const out = new fabric.Path(d, {
    fill: src.fill || '#4f46e5', stroke: src.stroke || null, strokeWidth: src.strokeWidth || 0, opacity: base.opacity,
    fillRule: op === 'exclude' ? 'evenodd' : 'nonzero', shadow: base.shadow,
    data: Object.assign({}, base.data && base.data.fx ? { fx: base.data.fx } : {}, { name: { union: 'Union', subtract: 'Subtracted shape', intersect: 'Intersection', exclude: 'Excluded shape' }[op] }),
  });
  const idx = all.indexOf(base);
  hist.locked = true;
  canvas.discardActiveObject();
  objs.forEach(o => canvas.remove(o));
  canvas.add(out); canvas.moveTo(out, idx);
  hist.locked = false;
  canvas.setActiveObject(out).requestRenderAll();
  histSave({ union: 'Union shapes', subtract: 'Subtract shapes', intersect: 'Intersect shapes', exclude: 'Exclude shapes' }[op]);
  refreshLayers();
  showToast('Done — double-click the new shape to edit its points');
}

/* ─────────────────────────── 5. IMAGE ADJUSTMENTS DIALOG ─────────────────────────── */
const adj = { obj: null, before: null, tone: null, ch: 'rgb', drag: -1, hist: null, raf: 0 };
function p2ToneOf(o) { return (o.filters || []).find(f => f && f.type === 'ToneLUT'); }
function p2OpenAdjust() {
  const o = canvas.getActiveObject();
  if (!o || o.type !== 'image') { showToast('Select a photo first', 'info'); return; }
  adj.obj = o;
  adj.before = (o.filters || []).map(f => f && f.toObject ? f.toObject() : null);
  let t = p2ToneOf(o);
  if (!t) { t = new fabric.Image.filters.ToneLUT(); o.filters = (o.filters || []).concat(t); }
  adj.tone = t;
  adj.hist = p2Histogram(o);
  p2AdjSyncUI();
  $p2('p2AdjOv').classList.add('open');
}
function p2Histogram(o) {
  const el = o._originalElement || o.getElement(), k = Math.min(1, 400 / Math.max(el.naturalWidth || el.width, el.naturalHeight || el.height));
  const c = document.createElement('canvas'); c.width = Math.max(1, Math.round((el.naturalWidth || el.width) * k)); c.height = Math.max(1, Math.round((el.naturalHeight || el.height) * k));
  const x = c.getContext('2d', { willReadFrequently: true }); x.drawImage(el, 0, 0, c.width, c.height);
  const h = { l: new Uint32Array(256), r: new Uint32Array(256), g: new Uint32Array(256), b: new Uint32Array(256), n: 0 };
  try {
    const d = x.getImageData(0, 0, c.width, c.height).data;
    for (let i = 0; i < d.length; i += 4) { if (d[i + 3] < 20) continue; h.r[d[i]]++; h.g[d[i + 1]]++; h.b[d[i + 2]]++; h.l[Math.round(0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2])]++; h.n++; }
  } catch (e) { /* cross-origin photo — histogram unavailable */ }
  return h;
}
function p2AdjSyncUI() {
  const L = adj.tone.levels;
  $p2('p2LvB').value = L.b; $p2('p2LvW').value = L.w; $p2('p2LvG').value = Math.round((Math.log(L.g) / Math.log(10) + 1) * 500);
  $p2('p2OutB').value = L.ob; $p2('p2OutW').value = L.ow;
  $p2('p2Sharp').value = adj.tone.sharpen; $p2('p2Sat').value = adj.tone.sat; $p2('p2Warm').value = adj.tone.warm;
  ['Sharp', 'Sat', 'Warm'].forEach(k => $p2('p2' + k + 'V').textContent = $p2('p2' + k).value);
  $p2('p2LvBv').textContent = L.b; $p2('p2LvWv').textContent = L.w; $p2('p2LvGv').textContent = L.g.toFixed(2);
  p2DrawHist(); p2DrawCurve();
}
function p2DrawHist() {
  const c = $p2('p2Hist'), x = c.getContext('2d'), h = adj.hist, W = c.width, H = c.height;
  x.clearRect(0, 0, W, H);
  if (!h || !h.n) { x.fillStyle = '#64748b'; x.font = '22px system-ui'; x.fillText('Histogram not available', 20, H / 2); return; }
  const max = Math.max(...Array.from(h.l).slice(2, 254)) || 1;
  x.fillStyle = 'rgba(148,163,184,.55)';
  for (let v = 0; v < 256; v++) { const bh = Math.min(H, h.l[v] / max * H); x.fillRect(v * 2, H - bh, 2, bh); }
  const L = adj.tone.levels; x.fillStyle = 'rgba(99,102,241,.25)'; x.fillRect(0, 0, L.b * 2, H); x.fillRect(L.w * 2, 0, W - L.w * 2, H);
}
function p2DrawCurve() {
  const c = $p2('p2Curve'), x = c.getContext('2d'), W = c.width, k = W / 255;
  x.clearRect(0, 0, W, W);
  x.strokeStyle = '#1e293b'; x.lineWidth = 1;
  for (let i = 1; i < 4; i++) { x.beginPath(); x.moveTo(i * W / 4, 0); x.lineTo(i * W / 4, W); x.moveTo(0, i * W / 4); x.lineTo(W, i * W / 4); x.stroke(); }
  x.strokeStyle = '#334155'; x.beginPath(); x.moveTo(0, W); x.lineTo(W, 0); x.stroke();
  const col = { rgb: '#e2e8f0', r: '#f87171', g: '#4ade80', b: '#60a5fa' }[adj.ch];
  const pts = adj.tone.curves[adj.ch], lut = p2CurveLUT(pts);
  x.strokeStyle = col; x.lineWidth = 3; x.beginPath();
  for (let v = 0; v < 256; v++) { const px = v * k, py = W - lut[v] * k; v ? x.lineTo(px, py) : x.moveTo(px, py); }
  x.stroke();
  pts.forEach((p, i) => { x.beginPath(); x.arc(p[0] * k, W - p[1] * k, 9, 0, Math.PI * 2); x.fillStyle = i === adj.drag ? col : '#0f172a'; x.fill(); x.strokeStyle = col; x.lineWidth = 3; x.stroke(); });
}
function p2AdjLive() {
  if (adj.raf) return;
  adj.raf = requestAnimationFrame(() => { adj.raf = 0; if (!adj.obj) return; try { adj.obj.applyFilters(); } catch (e) {} canvas.requestRenderAll(); });
}
function p2AdjRead() {
  const L = adj.tone.levels;
  let b = +$p2('p2LvB').value, w = +$p2('p2LvW').value; if (w - b < 2) { if (document.activeElement === $p2('p2LvB')) b = w - 2; else w = b + 2; }
  L.b = b; L.w = w; L.g = Math.round(Math.pow(10, +$p2('p2LvG').value / 500 - 1) * 100) / 100;
  L.ob = +$p2('p2OutB').value; L.ow = +$p2('p2OutW').value;
  adj.tone.sharpen = +$p2('p2Sharp').value; adj.tone.sat = +$p2('p2Sat').value; adj.tone.warm = +$p2('p2Warm').value;
  p2AdjSyncUI(); p2AdjLive();
}
['p2LvB', 'p2LvG', 'p2LvW', 'p2OutB', 'p2OutW', 'p2Sharp', 'p2Sat', 'p2Warm'].forEach(id => $p2(id).addEventListener('input', p2AdjRead));
$p2('p2CurveCh').addEventListener('click', e => { const s = e.target.closest('span'); if (!s) return; adj.ch = s.dataset.c; $p2('p2CurveCh').querySelectorAll('span').forEach(x => x.classList.toggle('on', x === s)); p2DrawCurve(); });
(function () {
  const c = $p2('p2Curve');
  const pos = e => { const r = c.getBoundingClientRect(); return [p2Clamp((e.clientX - r.left) / r.width * 255, 0, 255), p2Clamp(255 - (e.clientY - r.top) / r.height * 255, 0, 255)]; };
  const near = p => adj.tone.curves[adj.ch].findIndex(q => Math.hypot(q[0] - p[0], q[1] - p[1]) < 10);
  c.addEventListener('pointerdown', e => {
    const p = pos(e), pts = adj.tone.curves[adj.ch];
    let i = near(p);
    if (i < 0) { pts.push([Math.round(p[0]), Math.round(p[1])]); pts.sort((a, b) => a[0] - b[0]); i = pts.findIndex(q => q[0] === Math.round(p[0]) && q[1] === Math.round(p[1])); }
    adj.drag = i; c.setPointerCapture(e.pointerId); p2DrawCurve(); p2AdjLive();
  });
  c.addEventListener('pointermove', e => {
    if (adj.drag < 0) return;
    const p = pos(e), pts = adj.tone.curves[adj.ch], i = adj.drag;
    const lo = i > 0 ? pts[i - 1][0] + 2 : 0, hi = i < pts.length - 1 ? pts[i + 1][0] - 2 : 255;
    pts[i] = [Math.round(p2Clamp(p[0], lo, hi)), Math.round(p[1])];
    p2DrawCurve(); p2AdjLive();
  });
  c.addEventListener('pointerup', () => { adj.drag = -1; p2DrawCurve(); });
  c.addEventListener('dblclick', e => { const pts = adj.tone.curves[adj.ch], i = near(pos(e)); if (i > 0 && i < pts.length - 1) { pts.splice(i, 1); p2DrawCurve(); p2AdjLive(); } });
})();
function p2AdjReset() { const d = P2_TONE_DEFAULT(); Object.assign(adj.tone, d); p2AdjSyncUI(); p2AdjLive(); }
function p2AdjPreset(name) {
  const t = adj.tone; p2AdjReset();
  if (name === 'bright') t.curves.rgb = [[0, 0], [110, 150], [255, 255]];
  if (name === 'contrast') t.curves.rgb = [[0, 0], [64, 48], [192, 210], [255, 255]];
  if (name === 'dark') { t.curves.rgb = [[0, 0], [50, 90], [255, 255]]; }
  if (name === 'faded') { p2AutoEnhance(true); t.sat = 25; t.curves.rgb = [[0, 0], [64, 52], [192, 205], [255, 255]]; }
  p2AdjSyncUI(); p2AdjLive();
}
/** One-click photo clean-up: stretch levels to the real black/white points,
 *  balance mid-tones, correct colour cast, add a little saturation + sharpness. */
function p2AutoEnhance(keepOpen) {
  const h = adj.hist, t = adj.tone;
  if (!h || !h.n) { showToast('Auto-enhance needs a photo uploaded from your device', 'err'); return; }
  const pct = (arr, q) => { let s = 0; const target = h.n * q; for (let v = 0; v < 256; v++) { s += arr[v]; if (s >= target) return v; } return 255; };
  const b = pct(h.l, 0.005), w = Math.max(b + 10, pct(h.l, 0.995)), med = pct(h.l, 0.5);
  const tmid = p2Clamp((med - b) / (w - b), 0.05, 0.95);
  t.levels = { b, w, g: Math.round(p2Clamp(Math.log(tmid) / Math.log(0.5), 0.6, 1.8) * 100) / 100, ob: 0, ow: 255 };
  // colour cast: per-channel curves so each channel's 99.5% point maps to the same white
  ['r', 'g', 'b'].forEach(c => { const hi = pct(h[c], 0.995), lo = pct(h[c], 0.005); t.curves[c] = [[0, 0], [lo, Math.min(lo, b)], [Math.max(hi, lo + 10), Math.max(w, Math.min(255, hi))], [255, 255]].filter((p, i, a) => i === 0 || p[0] > a[i - 1][0]); });
  t.sat = 12; t.sharpen = 25;
  p2AdjSyncUI(); p2AdjLive();
  if (!keepOpen) showToast('Auto-enhanced — fine-tune or press Apply', 'ok');
}
function p2AdjCancel() {
  const o = adj.obj; $p2('p2AdjOv').classList.remove('open');
  if (!o) return;
  o.filters = (adj.before || []).filter(Boolean).map(f => { const C = fabric.Image.filters[f.type]; return C ? (C.fromObject ? C.fromObject(f) : new C(f)) : null; }).filter(Boolean);
  try { o.applyFilters(); } catch (e) {}
  canvas.requestRenderAll(); adj.obj = null;
}
function p2AdjApply() {
  const o = adj.obj; $p2('p2AdjOv').classList.remove('open');
  if (!o) return;
  if (adj.tone.isNeutralState()) o.filters = o.filters.filter(f => f !== adj.tone);
  try { o.applyFilters(); } catch (e) {}
  adj.obj = null; canvas.requestRenderAll(); histSave('Image adjustments');
  showToast('Adjustments applied — you can re-open and change them any time');
}

/* ─────────────────────────── 6. TEXT AUTO-FIT + OVERFLOW WARNINGS ─────────────────────────── */
function p2FitText(o) {
  const af = o && o.data && o.data.autoFit; if (!af || !isTextObj(o)) return false;
  const c = o.getCenterPoint(), min = af.min || 6, max = af.size || o.fontSize;
  const fits = () => o.type === 'textbox' ? (o._textLines || o.textLines || []).length <= (af.lines || 1) : o.width * o.scaleX <= af.w + 0.5;
  o.set('fontSize', max); o.initDimensions();
  if (!fits()) {
    let lo = min, hi = max;
    for (let i = 0; i < 14; i++) { const mid = (lo + hi) / 2; o.set('fontSize', mid); o.initDimensions(); if (fits()) lo = mid; else hi = mid; }
    o.set('fontSize', Math.floor(lo * 10) / 10); o.initDimensions();
  }
  o.setPositionByOrigin(c, 'center', 'center'); o.setCoords(); o.dirty = true;
  af.overflow = !fits();
  return true;
}
function p2SetAutoFit(on) {
  const objs = canvas.getActiveObjects().filter(isTextObj); if (!objs.length) return;
  objs.forEach(o => {
    if (on) {
      const lines = o.type === 'textbox' ? (o._textLines || o.textLines || []).length || 1 : 1;
      o.data = Object.assign({}, o.data, { autoFit: { w: Math.round(o.width * o.scaleX), size: o.fontSize, min: 6, lines } });
      p2FitText(o);
    } else if (o.data && o.data.autoFit) { o.set('fontSize', o.data.autoFit.size || o.fontSize); const d = Object.assign({}, o.data); delete d.autoFit; o.data = d; o.initDimensions(); o.setCoords(); }
  });
  canvas.requestRenderAll(); histSave(on ? 'Auto-fit text on' : 'Auto-fit text off'); refreshProps();
  if (on) showToast('Auto-fit on — long names now shrink to stay inside ' + (objs[0].type === 'textbox' ? 'their lines' : 'this width'), 'ok');
}
canvas.on('text:changed', e => { if (p2FitText(e.target)) canvas.requestRenderAll(); });
/** Text sticking out of the canvas, or still too long at the smallest auto-fit size, gets a warning outline. */
canvas.on('after:render', opt => {
  if (opt.ctx !== canvas.contextContainer || pen.active) return;
  const ctx = opt.ctx, v = canvas.viewportTransform, z = v[0] || 1;
  const warn = [];
  canvas.getObjects().forEach(o => {
    if (!isTextObj(o) || o.visible === false || o.isEditing) return;
    const b = o.getBoundingRect(true, true);
    const out = b.left < -1 || b.top < -1 || b.left + b.width > canvasW + 1 || b.top + b.height > canvasH + 1;
    const long = o.data?.autoFit?.overflow;
    if (out || long) warn.push({ b, msg: long ? 'Text too long' : 'Outside the card' });
  });
  if (!warn.length) return;
  ctx.save(); ctx.transform(v[0], v[1], v[2], v[3], v[4], v[5]);
  warn.forEach(w => {
    ctx.strokeStyle = '#f97316'; ctx.lineWidth = 1.5 / z; ctx.setLineDash([4 / z, 3 / z]);
    ctx.strokeRect(w.b.left, w.b.top, w.b.width, w.b.height); ctx.setLineDash([]);
    ctx.font = `600 ${11 / z}px system-ui`; const tw = ctx.measureText('⚠ ' + w.msg).width;
    // keep the label inside the visible canvas area, even when the text itself sticks out
    const vl = -v[4] / z, vt = -v[5] / z, vr = (canvas.getWidth() - v[4]) / z, bw = tw + 8 / z;
    const lx = Math.max(vl + 2 / z, Math.min(w.b.left, vr - bw - 2 / z));
    const ly = w.b.top - 16 / z < vt ? w.b.top + w.b.height + 1 / z : w.b.top - 16 / z;
    ctx.fillStyle = '#f97316'; ctx.fillRect(lx, ly, bw, 15 / z);
    ctx.fillStyle = '#fff'; ctx.fillText('⚠ ' + w.msg, lx + 4 / z, ly + 11.5 / z);
  });
  ctx.restore();
});

/* ─────────────────────────── 7. BRAND COLOUR PALETTE ─────────────────────────── */
const P2_PAL_KEY = 'ed_brand_palette';
let p2Palette = [];
try { p2Palette = JSON.parse(localStorage.getItem(P2_PAL_KEY) || '[]').filter(c => /^#[0-9a-f]{6}$/i.test(c)); } catch (_) {}
try {   // a palette saved inside the opened design comes along with it
  const dp = ED.canvas_json ? JSON.parse(ED.canvas_json)._palette : null;
  if (Array.isArray(dp)) dp.forEach(c => { if (/^#[0-9a-f]{6}$/i.test(c) && !p2Palette.includes(c.toLowerCase())) p2Palette.push(c.toLowerCase()); });
} catch (_) {}
function p2SavePalette() { try { localStorage.setItem(P2_PAL_KEY, JSON.stringify(p2Palette)); } catch (_) {} p2RenderPalette(); markDirty(); }
const _p2PrevJSON = canvasJSON;
canvasJSON = function () { const j = _p2PrevJSON(); if (p2Palette.length) j._palette = p2Palette.slice(0, 24); return j; };
function p2DocColors() {
  const set = new Map();
  const add = c => { if (typeof c !== 'string' || !c || c === 'transparent') return; const h = colorToHex(c).toLowerCase(); if (h !== '#000000' || /^#000|black|rgba?\(0, ?0, ?0/.test(c)) set.set(h, (set.get(h) || 0) + 1); };
  const walk = o => { if (o._objects) o._objects.forEach(walk); add(o.fill); add(o.stroke); if (o.data?.fx) Object.values(o.data.fx).forEach(f => f && f.on && (add(f.color), add(f.c1), add(f.c2))); };
  canvas.getObjects().forEach(walk);
  add(canvas.backgroundColor);
  return [...set.entries()].sort((a, b) => b[1] - a[1]).map(e => e[0]).slice(0, 14);
}
function p2ApplyColor(hex, asStroke) {
  const objs = canvas.getActiveObjects();
  if (!objs.length) { setCanvasBg(hex); const el = $p2('pCanvasBg'); if (el) el.value = hex; showToast('Background set to ' + hex); return; }
  objs.forEach(o => {
    if (asStroke) { o.set('stroke', hex); if (!o.strokeWidth) o.set('strokeWidth', isTextObj(o) ? 1 : 2); }
    else if (o.type === 'group') o.getObjects().forEach(c => { if (c.fill && c.fill !== 'transparent' && typeof c.fill === 'string') c.set('fill', hex); });
    else if (o.type !== 'image') { o.set('fill', hex); if (o.data && o.data._grad) o.data._grad = null; }
    o.dirty = true;
  });
  canvas.requestRenderAll(); histSave(asStroke ? 'Stroke colour' : 'Fill colour'); refreshProps();
}
function p2PaletteAdd() {
  const o = canvas.getActiveObject();
  const cur = o && typeof o.fill === 'string' && o.fill && o.fill !== 'transparent' ? colorToHex(o.fill) : null;
  const inp = document.createElement('input'); inp.type = 'color'; inp.value = cur || '#4f46e5';
  if (cur && !p2Palette.includes(cur.toLowerCase())) { p2Palette.push(cur.toLowerCase()); p2SavePalette(); showToast('Added ' + cur + ' to brand colours'); return; }
  inp.addEventListener('change', () => { const c = inp.value.toLowerCase(); if (!p2Palette.includes(c)) { p2Palette.push(c); p2SavePalette(); } });
  inp.click();
}
/** Pull the main colours out of a selected logo / photo into the brand palette. */
function p2PaletteFromImage() {
  const o = canvas.getActiveObject();
  if (!o || o.type !== 'image') { showToast('Select your school logo (an image) first', 'info'); return; }
  const el = o._originalElement || o.getElement(), c = document.createElement('canvas'), k = Math.min(1, 160 / Math.max(el.width, el.height));
  c.width = Math.max(1, el.width * k | 0); c.height = Math.max(1, el.height * k | 0);
  const x = c.getContext('2d', { willReadFrequently: true }); x.drawImage(el, 0, 0, c.width, c.height);
  let d; try { d = x.getImageData(0, 0, c.width, c.height).data; } catch (e) { showToast('This image is from another website — upload it from your device', 'err'); return; }
  const buckets = new Map();
  for (let i = 0; i < d.length; i += 4) {
    if (d[i + 3] < 128) continue;
    const r = d[i], g = d[i + 1], b = d[i + 2], mx = Math.max(r, g, b), mn = Math.min(r, g, b);
    if (mx > 238 && mn > 238) continue;   // skip white paper
    const key = (r >> 4) << 8 | (g >> 4) << 4 | (b >> 4);
    const e = buckets.get(key) || { n: 0, r: 0, g: 0, b: 0 }; e.n++; e.r += r; e.g += g; e.b += b; buckets.set(key, e);
  }
  const picked = [];
  [...buckets.values()].sort((a, b) => b.n - a.n).forEach(e => {
    if (picked.length >= 6) return;
    const col = [e.r / e.n, e.g / e.n, e.b / e.n];
    if (picked.every(p => Math.hypot(p[0] - col[0], p[1] - col[1], p[2] - col[2]) > 60)) picked.push(col);
  });
  const hexes = picked.map(p => '#' + p.map(v => Math.round(v).toString(16).padStart(2, '0')).join(''));
  hexes.forEach(h => { if (!p2Palette.includes(h)) p2Palette.push(h); });
  p2SavePalette(); showToast(hexes.length + ' colours taken from the logo', 'ok');
}
function p2RenderPalette() {
  const box = $p2('p2PalSec'); if (!box) return;
  const sw = (c, brand) => `<div class="p2-sw" style="background:${c}" data-c="${c}" data-brand="${brand ? 1 : 0}" title="${c} — click: fill · Shift+click: outline${brand ? ' · right-click: remove' : ''}"></div>`;
  const doc = p2DocColors();
  box.innerHTML = `<div class="p2-title"><i class="bi bi-palette-fill" style="color:#a78bfa;"></i> Brand colours <span style="margin-left:auto;text-transform:none;letter-spacing:0;font-weight:500;">${canvas.getActiveObject() ? 'applies to selection' : 'click = background'}</span></div>
    <div class="p2-pal">${p2Palette.map(c => sw(c, true)).join('')}<div class="p2-sw add" title="Add colour (adds the selected object's colour, or pick one)" onclick="p2PaletteAdd()">+</div>
    <button class="p2-btn" style="padding:.15rem .45rem;font-size:.66rem;" onclick="p2PaletteFromImage()" title="Select your logo image, then click to take its colours"><i class="bi bi-eyedropper"></i> From logo</button></div>
    ${doc.length ? `<div class="p2-pal-lbl">Colours in this design</div><div class="p2-pal">${doc.map(c => sw(c, false)).join('')}</div>` : ''}`;
}
document.addEventListener('click', e => { const s = e.target.closest('#p2PalSec .p2-sw:not(.add)'); if (s) p2ApplyColor(s.dataset.c, e.shiftKey); });
document.addEventListener('contextmenu', e => {
  const s = e.target.closest('#p2PalSec .p2-sw[data-brand="1"]'); if (!s) return;
  e.preventDefault(); p2Palette = p2Palette.filter(c => c !== s.dataset.c); p2SavePalette(); showToast('Colour removed from brand palette');
});

/* ─────────────────────────── 8. PANELS (fx, auto-fit, combine, adjust, edit points) ─────────────────────────── */
let p2FxClipboard = null;
function p2FxSet(key, prop, val) {
  const objs = canvas.getActiveObjects(); if (!objs.length) return;
  objs.forEach(o => {
    const fx = JSON.parse(JSON.stringify((o.data && o.data.fx) || {}));
    fx[key] = Object.assign({}, P2_FX_DEFAULTS[key], fx[key] || {}, { [prop]: val });
    o.data = Object.assign({}, o.data, { fx }); o.dirty = true;
  });
  canvas.requestRenderAll(); histSaveSoon('Layer effects');
  if (prop === 'on') p2RenderFx();
}
function p2FxClear() { canvas.getActiveObjects().forEach(o => { if (o.data && o.data.fx) { const d = Object.assign({}, o.data); delete d.fx; o.data = d; o.dirty = true; } }); canvas.requestRenderAll(); histSave('Clear effects'); p2RenderFx(); }
function p2FxCopy() { const o = canvas.getActiveObject(); if (!o || !o.data || !p2HasFx(o.data.fx)) { showToast('This layer has no effects to copy', 'info'); return; } p2FxClipboard = JSON.parse(JSON.stringify(o.data.fx)); showToast('Effects copied — select other layers and Paste effects'); p2RenderFx(); }
function p2FxPaste() { if (!p2FxClipboard) return; canvas.getActiveObjects().forEach(o => { o.data = Object.assign({}, o.data, { fx: JSON.parse(JSON.stringify(p2FxClipboard)) }); o.dirty = true; }); canvas.requestRenderAll(); histSave('Paste effects'); p2RenderFx(); }
/** Bake effects into a picture (for pages/printers that don't understand effects). */
function p2FxFlatten() {
  const o = canvas.getActiveObject(); if (!o || !p2HasFx(o.data?.fx)) return;
  const pad = p2FxPad(o.data.fx), oldShadow = o.shadow;
  o.shadow = new fabric.Shadow({ color: 'rgba(0,0,0,0)', blur: pad, offsetX: 0, offsetY: 0 });   // makes room for glow in the export box
  const el = o.toCanvasElement({ multiplier: 3 });
  o.shadow = oldShadow;
  const c = o.getCenterPoint();
  const img = new fabric.Image(el, { originX: 'center', originY: 'center', left: c.x, top: c.y, scaleX: 1 / 3, scaleY: 1 / 3, opacity: 1, shadow: oldShadow, data: { name: _layerLabel(o) + ' (flattened)' } });
  const idx = canvas.getObjects().indexOf(o);
  hist.locked = true; canvas.remove(o); canvas.add(img); canvas.moveTo(img, idx); hist.locked = false;
  canvas.setActiveObject(img).requestRenderAll(); histSave('Flatten effects'); refreshLayers();
}
function p2RenderFx() {
  const box = $p2('p2FxSec'); if (!box) return;
  const a = canvas.getActiveObject();
  if (!a) { box.style.display = 'none'; return; }
  box.style.display = '';
  const ref = a.type === 'activeSelection' ? a.getObjects()[0] : a;
  const fx = (ref.data && ref.data.fx) || {};
  const v = (k, p) => (fx[k] && fx[k][p] !== undefined ? fx[k][p] : P2_FX_DEFAULTS[k][p]);
  const open = k => fx[k] && fx[k].on ? ' open' : '';
  const range = (k, p, lbl, min, max) => `<div class="p2-row"><label>${lbl}</label><input type="range" min="${min}" max="${max}" value="${v(k, p)}" oninput="p2FxSet('${k}','${p}',+this.value);this.nextElementSibling.textContent=this.value"><span class="val">${v(k, p)}</span></div>`;
  const color = (k, p, lbl) => `<div class="p2-row"><label>${lbl}</label><input type="color" value="${colorToHex(v(k, p))}" oninput="p2FxSet('${k}','${p}',this.value)"></div>`;
  const item = (k, title, body) => `<div class="p2-fx${open(k)}"><div class="p2-fx-hd" onclick="if(event.target.tagName!=='INPUT')this.parentNode.classList.toggle('open')"><input type="checkbox" ${v(k, 'on') ? 'checked' : ''} onchange="p2FxSet('${k}','on',this.checked)"> ${title}<i class="bi bi-chevron-right chev"></i></div><div class="p2-fx-bd">${body}</div></div>`;
  box.innerHTML = `<div class="p2-title"><i class="bi bi-stars" style="color:#f472b6;"></i> Layer effects <span class="badge">EDITABLE</span></div>
    ${item('stroke', 'Stroke (outline)', color('stroke', 'color', 'Colour') + range('stroke', 'size', 'Size', 1, 40) + range('stroke', 'opacity', 'Opacity', 5, 100))}
    ${item('outer', 'Outer glow', color('outer', 'color', 'Colour') + range('outer', 'size', 'Size', 1, 80) + range('outer', 'opacity', 'Opacity', 5, 100))}
    ${item('inner', 'Inner glow', color('inner', 'color', 'Colour') + range('inner', 'size', 'Size', 1, 60) + range('inner', 'opacity', 'Opacity', 5, 100))}
    ${item('overlay', 'Gradient overlay', color('overlay', 'c1', 'From') + color('overlay', 'c2', 'To') + range('overlay', 'angle', 'Angle', 0, 360) + range('overlay', 'opacity', 'Opacity', 5, 100))}
    <div class="p2-btns" style="margin-top:.3rem;">
      <button class="p2-btn" onclick="p2FxCopy()"><i class="bi bi-files"></i> Copy</button>
      <button class="p2-btn" onclick="p2FxPaste()" ${p2FxClipboard ? '' : 'disabled style="opacity:.45"'}><i class="bi bi-clipboard"></i> Paste</button>
      <button class="p2-btn" onclick="p2FxClear()"><i class="bi bi-x-lg"></i> Clear</button>
      <button class="p2-btn" onclick="p2FxFlatten()" title="Turn this layer + its effects into a picture (for printers/pages that don't support effects)"><i class="bi bi-image"></i> Flatten</button>
    </div>`;
}
function p2RenderExtra() {
  const a = canvas.getActiveObject();
  // Combine shapes (multi-selection)
  const cb = $p2('p2BoolSec');
  if (cb) {
    const multi = a && a.type === 'activeSelection';
    cb.style.display = multi ? '' : 'none';
  }
  // Auto-fit (text)
  const af = $p2('p2FitSec');
  if (af) {
    const t = canvas.getActiveObjects().filter(isTextObj)[0];
    af.style.display = t ? '' : 'none';
    if (t) {
      const on = !!t.data?.autoFit;
      af.innerHTML = `<div class="p2-title"><i class="bi bi-arrows-collapse-vertical"></i> Auto-fit</div>
        <label class="p2-row" style="cursor:pointer;color:#e2e8f0;"><input type="checkbox" ${on ? 'checked' : ''} onchange="p2SetAutoFit(this.checked)"> Shrink text to fit ${t.type === 'textbox' ? 'its lines' : 'its width'}</label>
        ${on && t.type !== 'textbox' ? `<div class="p2-row"><label>Max width</label><input type="number" min="10" value="${t.data.autoFit.w}" onchange="p2AutoFitWidth(+this.value)"> px <button class="p2-btn" onclick="p2AutoFitWidth(Math.round(canvasW*0.9))" title="90% of the canvas width">Card width</button></div>` : ''}
        ${on ? `<div class="pro-note" style="margin:0;">${t.data.autoFit.overflow ? '<span style="color:#fb923c;">⚠ Still too long at the smallest size — shorten the text.</span>' : 'Size now ' + Math.round(t.fontSize) + ' px (max ' + Math.round(t.data.autoFit.size) + ').'}</div>` : '<div class="pro-note" style="margin:0;">Turn on for names/addresses so long ones never overflow.</div>'}`;
    }
  }
  // Image tools
  const im = $p2('p2ImgSec');
  if (im) im.style.display = a && a.type === 'image' ? '' : 'none';
  // Edit points
  const ep = $p2('p2PtsSec');
  if (ep) ep.style.display = a && ['path', 'polygon', 'polyline'].includes(a.type) && !a.data?.eraserPath ? '' : 'none';
}
function p2AutoFitWidth(w) {
  if (!(w > 5)) return;
  canvas.getActiveObjects().filter(o => isTextObj(o) && o.data?.autoFit).forEach(o => { o.data.autoFit.w = w; p2FitText(o); });
  canvas.requestRenderAll(); histSave('Auto-fit width'); p2RenderExtra();
}

/* ─────────────────────────── 9. WIRING ─────────────────────────── */
(function p2Init() {
  const props = document.querySelector('.ed-props-scroll'), pObj = $p2('pObj');
  // brand palette — always visible at the top of the panel
  const pal = document.createElement('div'); pal.className = 'p2-sec'; pal.id = 'p2PalSec'; props.insertBefore(pal, props.firstChild);
  // combine shapes
  const bs = document.createElement('div'); bs.className = 'p2-sec'; bs.id = 'p2BoolSec'; bs.style.display = 'none';
  bs.innerHTML = `<div class="p2-title"><i class="bi bi-intersect" style="color:#22d3ee;"></i> Combine shapes</div>
    <div class="p2-btns">
      <button class="p2-btn" onclick="p2Boolean('union')" title="Merge into one shape"><i class="bi bi-union"></i> Union</button>
      <button class="p2-btn" onclick="p2Boolean('subtract')" title="Cut the front shapes out of the back one"><i class="bi bi-subtract"></i> Subtract</button>
      <button class="p2-btn" onclick="p2Boolean('intersect')" title="Keep only the overlapping part"><i class="bi bi-intersect"></i> Intersect</button>
      <button class="p2-btn" onclick="p2Boolean('exclude')" title="Keep everything except the overlap"><i class="bi bi-exclude"></i> Exclude</button>
    </div><div class="pro-note">Back-most shape keeps its colour. Works on shapes, stars, pen paths.</div>`;
  pObj.insertBefore(bs, pObj.firstChild);
  // edit points
  const ps = document.createElement('div'); ps.className = 'p2-sec'; ps.id = 'p2PtsSec'; ps.style.display = 'none';
  ps.innerHTML = `<div class="p2-title"><i class="bi bi-bezier" style="color:#22d3ee;"></i> Shape points</div><button class="p2-btn primary" onclick="p2EditPoints()"><i class="bi bi-bezier2"></i> Edit points</button> <span class="pro-note">or double-click the shape</span>`;
  pObj.insertBefore(ps, bs.nextSibling);
  // auto-fit inside the text section
  const fit = document.createElement('div'); fit.id = 'p2FitSec'; fit.style.cssText = 'margin-top:.6rem;border-top:1px solid #334155;padding-top:.6rem;';
  $p2('pTextSec').appendChild(fit);
  // image adjust buttons at the top of the image section
  const im = document.createElement('div'); im.id = 'p2ImgSec'; im.style.marginBottom = '.6rem';
  im.innerHTML = `<div class="p2-btns"><button class="p2-btn primary" onclick="p2OpenAdjust()"><i class="bi bi-sliders"></i> Levels / Curves</button><button class="p2-btn" onclick="p2QuickEnhance()"><i class="bi bi-magic"></i> Auto-enhance</button></div>`;
  const imgSec = $p2('pImgSec'); imgSec.insertBefore(im, imgSec.children[1] || null);
  // layer effects at the end of the object panel
  const fx = document.createElement('div'); fx.className = 'p2-sec'; fx.id = 'p2FxSec'; pObj.appendChild(fx);
  // pen tool button after Shapes
  const shapes = $p2('tShapes');
  if (shapes) {
    const b = document.createElement('button'); b.className = 'tool-btn c-shape'; b.id = 'tPen'; b.style.color = '#22d3ee';
    b.innerHTML = '<i class="bi bi-vector-pen"></i><span class="tool-tooltip">Pen / Bezier (P)</span>';
    b.onclick = () => { closeFlyouts(); p2PenStart(); };
    shapes.after(b);
    const tip = document.getElementById('g-tip');
    b.addEventListener('mouseenter', () => { if (!tip) return; tip.textContent = 'Pen / Bezier tool (P)'; tip.style.display = 'block'; const r = b.getBoundingClientRect(); tip.style.top = (r.top + r.height / 2 - 12) + 'px'; tip.style.left = (r.right + 10) + 'px'; });
    b.addEventListener('mouseleave', () => { if (tip) tip.style.display = 'none'; });
  }
  // refresh our panels whenever the editor refreshes its own
  const rp = refreshProps;
  refreshProps = function () { const r = rp.apply(this, arguments); try { p2RenderExtra(); p2RenderFx(); p2RenderPalette(); } catch (e) { console.warn(e); } return r; };
  // keep the tone filter when the classic filter sliders rebuild the filter list
  const af = applyImgFilter;
  applyImgFilter = function () {
    const o = canvas.getActiveObject(), tone = o && o.type === 'image' ? p2ToneOf(o) : null;
    const r = af.apply(this, arguments);
    if (tone && o.filters && !o.filters.includes(tone) && !p2ResetAll) { o.filters.push(tone); try { o.applyFilters(); } catch (e) {} canvas.requestRenderAll(); }
    return r;
  };
  const rf = resetImgFilters;
  resetImgFilters = function () { p2ResetAll = true; try { return rf.apply(this, arguments); } finally { p2ResetAll = false; } };
  // font size typed in the panel becomes the auto-fit maximum; other text changes re-fit
  const atp = applyTextProp;
  applyTextProp = function (prop, val) {
    const r = atp.apply(this, arguments);
    canvas.getActiveObjects().filter(o => isTextObj(o) && o.data?.autoFit).forEach(o => { if (prop === 'fontSize' && val > 0) o.data.autoFit.size = val; p2FitText(o); });
    canvas.requestRenderAll(); return r;
  };
  // leaving the pen when another tool is chosen
  const sat = setActiveTool;
  setActiveTool = function () { if (pen.active && pen.mode === 'draw' && pen.pts.length >= 2) p2PenFinish(false); else if (pen.active) p2PenCancel(); return sat.apply(this, arguments); };
  // context menu + command palette entries
  const om = proOpenMenu;
  proOpenMenu = function (x, y, items) {
    const a = canvas.getActiveObject(), extra = [];
    if (a && a.type === 'activeSelection') extra.push(proMenuItem('bi-union', 'Union shapes', '', () => p2Boolean('union')), proMenuItem('bi-subtract', 'Subtract (back minus front)', '', () => p2Boolean('subtract')), proMenuItem('bi-intersect', 'Intersect', '', () => p2Boolean('intersect')), proMenuItem('bi-exclude', 'Exclude overlap', '', () => p2Boolean('exclude')));
    if (a && ['path', 'polygon', 'polyline'].includes(a.type)) extra.push(proMenuItem('bi-bezier2', 'Edit points', 'Dbl-click', () => p2EditPoints(a)));
    if (a && a.type === 'image') extra.push(proMenuItem('bi-sliders', 'Levels / Curves…', '', p2OpenAdjust), proMenuItem('bi-magic', 'Auto-enhance photo', '', p2QuickEnhance));
    if (a) extra.push(proMenuItem('bi-stars', 'Copy effects', '', p2FxCopy, { off: !p2HasFx(a.data?.fx) }), proMenuItem('bi-stars', 'Paste effects', '', p2FxPaste, { off: !p2FxClipboard }));
    if (extra.length) { const at = Math.max(0, items.indexOf('-')); items = items.slice(0, at + 1).concat(extra, ['-'], items.slice(at + 1)); }
    if (!a) items = items.concat(['-', proMenuItem('bi-vector-pen', 'Pen tool', 'P', p2PenStart)]);
    return om.call(this, x, y, items);
  };
  PRO_CMDS.push(
    { group: 'Tools', icon: 'bi-vector-pen', label: 'Pen / Bezier tool', keys: 'P', run: p2PenStart },
    { group: 'Tools', icon: 'bi-bezier2', label: 'Edit shape points', keys: '', run: () => p2EditPoints() },
    { group: 'Arrange', icon: 'bi-union', label: 'Union shapes', keys: '', run: () => p2Boolean('union') },
    { group: 'Arrange', icon: 'bi-subtract', label: 'Subtract shapes', keys: '', run: () => p2Boolean('subtract') },
    { group: 'Arrange', icon: 'bi-intersect', label: 'Intersect shapes', keys: '', run: () => p2Boolean('intersect') },
    { group: 'Arrange', icon: 'bi-exclude', label: 'Exclude shapes', keys: '', run: () => p2Boolean('exclude') },
    { group: 'Tools', icon: 'bi-sliders', label: 'Levels / Curves (photo)', keys: '', run: p2OpenAdjust },
    { group: 'Tools', icon: 'bi-magic', label: 'Auto-enhance photo', keys: '', run: p2QuickEnhance },
    { group: 'Edit', icon: 'bi-stars', label: 'Copy layer effects', keys: '', run: p2FxCopy },
    { group: 'Edit', icon: 'bi-stars', label: 'Paste layer effects', keys: '', run: p2FxPaste },
    { group: 'Edit', icon: 'bi-arrows-collapse-vertical', label: 'Auto-fit text on', keys: '', run: () => p2SetAutoFit(true) },
    { group: 'Edit', icon: 'bi-palette-fill', label: 'Take brand colours from logo', keys: '', run: p2PaletteFromImage },
  );
  p2RenderPalette(); p2RenderFx(); p2RenderExtra();
})();
var p2ResetAll = false;
function p2QuickEnhance() {
  const o = canvas.getActiveObject(); if (!o || o.type !== 'image') { showToast('Select a photo first', 'info'); return; }
  p2OpenAdjust(); p2AutoEnhance(true); p2AdjApply();
  showToast('Photo auto-enhanced (Ctrl+Z to undo, Levels/Curves to fine-tune)', 'ok');
}
// Pen keyboard: P starts the pen; while drawing Enter/Esc/Backspace control it.
document.addEventListener('keydown', e => {
  if ($p2('p2AdjOv').classList.contains('open')) { if (e.key === 'Escape') { e.preventDefault(); e.stopImmediatePropagation(); p2AdjCancel(); } return; }
  if (pen.active) {
    const k = e.key;
    if (k === 'Enter') p2PenFinish(false);
    else if (k === 'Escape') p2PenCancel();
    else if ((k === 'Backspace' || k === 'Delete') && pen.mode === 'draw') p2PenUndoPoint();
    else if ((k === 'Backspace' || k === 'Delete') && pen.mode === 'edit' && pen.sel >= 0 && pen.pts.length > 2) { pen.pts.splice(pen.sel, 1); pen.sel = -1; canvas.requestRenderAll(); }
    else if ((e.ctrlKey || e.metaKey) && k.toLowerCase() === 'z' && pen.mode === 'draw') p2PenUndoPoint();
    else if (!(e.ctrlKey || e.metaKey)) return;
    e.preventDefault(); e.stopImmediatePropagation(); return;
  }
  const tag = (document.activeElement || {}).tagName;
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || canvas.getActiveObject()?.isEditing || _openModalId()) return;
  if (!e.ctrlKey && !e.metaKey && !e.altKey && e.key.toLowerCase() === 'p' && !document.querySelector('.pro-ov.open')) { e.preventDefault(); e.stopImmediatePropagation(); p2PenStart(); }
}, true);
window.addEventListener('resize', () => { if (pen.active) p2PenBar(); });
</script>

</body>
</html>
