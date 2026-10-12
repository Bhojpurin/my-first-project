<?php
require_once __DIR__ . '/../includes/session.php';
// Bare requireLogin() let ANY logged-in role through (student, parent, driver,
// shop customer, etc.) — the role list was simply missing, not a deliberate
// "open to everyone" choice. Matches editor.php, its sibling nav entry, which
// already restricts to the iCard panel's actual user base.
// ROLE_TEACHER added: editor.php (which embeds this page as an iframe from its
// "Logo Designer Studio" button) already admits teachers, so a teacher clicking
// that button got a raw 403 inside the modal instead of the studio.
requireLogin(ROLE_SUPER_ADMIN, ROLE_OPERATOR, ROLE_SCHOOL_ADMIN, ROLE_TEACHER);
$fromEditor = !empty($_GET['from']) && $_GET['from'] === 'editor';
$isIframe   = !empty($_GET['mode']) && $_GET['mode'] === 'iframe';
$galleryId  = (int)($_GET['gallery_id'] ?? 0);
// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: ' . ($isIframe ? 'SAMEORIGIN' : 'DENY'));
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf" content="<?= csrfToken() ?>">
<title>Logo Studio Pro — School Edition</title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Montserrat:wght@300;400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Raleway:wght@300;400;600;700;800&family=Oswald:wght@300;400;500;600;700&family=Dancing+Script:wght@400;600;700&family=Pacifico&family=Lobster&family=Bebas+Neue&family=Righteous&family=Exo+2:wght@400;600;700;900&family=Nunito:wght@400;600;700;800;900&family=Open+Sans:wght@400;600;700&family=Source+Sans+Pro:wght@400;600;700&family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="<?= BASE_URL ?>/assets/js/vendor/fabric-5.3.1.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/idm-poll.js"></script>
<script src="<?= BASE_URL ?>/assets/js/offline-queue.js?v=4"></script>
<style>
/* ═══════════════════════════════════════════════════════════
   LOGO STUDIO PRO — Design System
   World Pro Max · Indian School Edition
═══════════════════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg-void:#080612;
  --bg-deep:#0d0a1a;
  --bg-panel:#110e20;
  --bg-card:#161228;
  --bg-hover:#1e1835;
  --bg-active:#251f40;
  --border:#1e1a35;
  --border-hi:#2d2855;
  --text-bright:#f0ecff;
  --text-mid:#8b82b8;
  --text-dim:#3d3660;
  --gold:#f59e0b;
  --gold-lo:#c47d09;
  --purple:#a855f7;
  --purple-lo:#7c3aed;
  --blue:#3b82f6;
  --cyan:#06b6d4;
  --green:#22c55e;
  --red:#ef4444;
  --pink:#ec4899;
  --accent-glow:rgba(168,85,247,.35);
  --gold-glow:rgba(245,158,11,.35);
  --r-sm:6px;
  --r-md:10px;
  --r-lg:16px;
  --r-xl:20px;
  --shadow-panel:0 20px 60px rgba(0,0,0,.7);
}
html,body{height:100%;overflow:hidden;background:var(--bg-void);color:var(--text-bright);font-family:'Montserrat',sans-serif;font-size:13px;}
/* BUG FIX: body was plain block flow with #lsp-body given a hardcoded
   height:calc(100vh - 48px) — that alone consumes the ENTIRE space below the
   topbar, leaving nothing for the #lsp-bottom layer tray (a sibling, not a
   child, of #lsp-body). The tray was rendering 140px below the visible
   viewport on every screen size, clipped by this body's overflow:hidden —
   i.e. the whole Layers tray (add/duplicate/delete/reorder/group) was
   permanently inaccessible. Fixed by making body a real flex column so
   #lsp-body can just flex:1 instead of computing its own fixed height. */
body{display:flex;flex-direction:column;}

/* ── TOPBAR ── */
#lsp-topbar{
  height:48px;display:flex;align-items:center;padding:0 12px;gap:8px;
  background:var(--bg-panel);border-bottom:1px solid var(--border);
  flex-shrink:0;position:relative;z-index:100;
}
.lsp-logo{
  display:flex;align-items:center;gap:8px;margin-right:8px;
  font-weight:900;font-size:.95rem;letter-spacing:-.02em;color:var(--text-bright);
  white-space:nowrap;
}
.lsp-logo-icon{
  width:30px;height:30px;border-radius:8px;
  background:linear-gradient(135deg,#f59e0b,#ef4444,#a855f7);
  display:flex;align-items:center;justify-content:center;font-size:.9rem;
  box-shadow:0 2px 10px var(--gold-glow);flex-shrink:0;
}
.lsp-logo-badge{
  font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;
  padding:2px 7px;border-radius:4px;
  background:linear-gradient(90deg,#f59e0b,#a855f7);color:#fff;
}
.lsp-top-btn{
  display:flex;align-items:center;gap:6px;padding:6px 12px;
  border:1px solid var(--border-hi);background:var(--bg-card);color:#a8b4e8;
  border-radius:var(--r-sm);font-size:.78rem;cursor:pointer;transition:all .15s;
  white-space:nowrap;font-family:'Montserrat',sans-serif;font-weight:600;
}
.lsp-top-btn:hover{background:var(--bg-hover);color:#fff;border-color:var(--purple);}
.lsp-top-btn.primary{background:linear-gradient(135deg,var(--purple-lo),var(--purple));border-color:transparent;color:#fff;font-weight:700;box-shadow:0 2px 12px var(--accent-glow);}
.lsp-top-btn.primary:hover{box-shadow:0 4px 20px var(--accent-glow);transform:translateY(-1px);}
.lsp-top-btn.gold{background:linear-gradient(135deg,var(--gold-lo),var(--gold));border-color:transparent;color:#000;font-weight:700;box-shadow:0 2px 12px var(--gold-glow);}
.lsp-top-btn.gold:hover{box-shadow:0 4px 20px var(--gold-glow);transform:translateY(-1px);}
.lsp-top-btn i{font-size:.95rem;}
.lsp-top-sep{width:1px;height:24px;background:var(--border-hi);margin:0 2px;flex-shrink:0;}
.lsp-top-spacer{flex:1;}
#lsp-zoom-display{font-size:.78rem;color:#a8b4e8;min-width:42px;text-align:center;font-weight:700;}
.lsp-undo-btn{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid var(--border-hi);background:var(--bg-card);color:#a8b4e8;border-radius:var(--r-sm);cursor:pointer;font-size:.95rem;transition:all .15s;}
.lsp-undo-btn:hover{background:var(--bg-hover);color:#fff;border-color:var(--purple);}
.lsp-undo-btn:disabled{opacity:.3;cursor:not-allowed;}

/* ── MAIN LAYOUT ── */
#lsp-body{display:flex;flex:1;overflow:hidden;min-height:0;}

/* ── LEFT SIDEBAR ── */
#lsp-left{
  width:70px;background:var(--bg-panel);border-right:1px solid var(--border);
  display:flex;flex-direction:column;align-items:center;padding:8px 0;gap:4px;
  flex-shrink:0;z-index:50;
}
.lsp-tool-btn{
  width:58px;height:54px;display:flex;flex-direction:column;align-items:center;
  justify-content:center;border-radius:var(--r-md);border:1px solid transparent;
  background:rgba(255,255,255,.03);color:#7b8fcc;cursor:pointer;transition:all .18s;
  gap:4px;font-size:.56rem;font-family:'Montserrat',sans-serif;font-weight:600;
}
.lsp-tool-btn i{font-size:1.35rem;transition:transform .15s;}
.lsp-tool-btn:hover{background:var(--bg-hover);color:#fff;border-color:var(--border-hi);transform:translateX(2px);}
.lsp-tool-btn:hover i{transform:scale(1.1);}
.lsp-tool-btn.active{border-color:currentColor;box-shadow:0 0 14px rgba(168,85,247,.25);}
.lsp-tool-sep{width:40px;height:1px;background:var(--border-hi);margin:3px 0;opacity:.5;}
.lsp-tool-btn span{font-size:.55rem;line-height:1;color:inherit;letter-spacing:.02em;}

/* Per-tool colors */
#tool-select{color:#38bdf8;}
#tool-select.active{background:rgba(56,189,248,.12);color:#38bdf8;}
#tool-move{color:#818cf8;}
#tool-move.active{background:rgba(129,140,248,.12);color:#818cf8;}
#tool-text{color:#fbbf24;}
#tool-text.active{background:rgba(251,191,36,.12);color:#fbbf24;}
#tool-shape{color:#4ade80;}
#tool-shape.active{background:rgba(74,222,128,.12);color:#4ade80;}
#tool-icon{color:#c084fc;}
#tool-icon.active{background:rgba(192,132,252,.12);color:#c084fc;}
#tool-image{color:#fb923c;}
#tool-image.active{background:rgba(251,146,60,.12);color:#fb923c;}
#tool-frame{color:#f472b6;}
#tool-frame.active{background:rgba(244,114,182,.12);color:#f472b6;}
#tool-draw{color:#f87171;}
#tool-draw.active{background:rgba(248,113,113,.12);color:#f87171;}
#tool-curved{color:#34d399;}
#tool-curved.active{background:rgba(52,211,153,.12);color:#34d399;}
#tool-badge{color:#fbbf24;}
#tool-badge.active{background:rgba(251,191,36,.12);color:#fbbf24;}
#tool-ring{color:#e879f9;}
#tool-ring.active{background:rgba(232,121,249,.12);color:#e879f9;}

/* ── CANVAS AREA ── */
/* align/justify flex-start + margin:auto on the wrap (below): a canvas larger
   than the viewport used to be centred with justify-content:center, which made
   its left/top part unreachable — you could not scroll to it. */
#lsp-canvas-area{
  flex:1;display:flex;flex-direction:column;align-items:flex-start;
  justify-content:flex-start;background:var(--bg-void);position:relative;overflow:auto;
  padding:24px 24px 64px;
  background-image:radial-gradient(circle,#1a1530 1px,transparent 1px);
  background-size:24px 24px;
  scrollbar-width:thin;scrollbar-color:var(--border) transparent;
}
#lsp-canvas-wrap{
  position:relative;box-shadow:0 0 0 1px rgba(168,85,247,.2), 0 20px 80px rgba(0,0,0,.8);
  border-radius:2px;flex-shrink:0;margin:auto;
}
#lsp-canvas-area.lsp-panning, #lsp-canvas-area.lsp-panning *{cursor:grabbing !important;}
#lsp-dirty-dot{width:8px;height:8px;border-radius:50%;background:#f59e0b;display:none;flex-shrink:0;box-shadow:0 0 8px #f59e0b;}
#lsp-dirty-dot.show{display:inline-block;}
.lsp-num{width:62px;flex:0 0 auto;}
#lsp-canvas-wrap .canvas-container{border-radius:2px;}
#lsp-main-canvas{display:block;cursor:default;}
.lsp-canvas-overlay{position:absolute;pointer-events:none;}
#lsp-grid-overlay{position:absolute;top:0;left:0;pointer-events:none;opacity:.25;}
#lsp-snap-indicator{position:absolute;pointer-events:none;display:none;}

/* Canvas size preset bar */
#lsp-size-bar{
  position:absolute;bottom:14px;left:50%;transform:translateX(-50%);
  display:flex;gap:6px;align-items:center;
  background:rgba(13,10,26,.85);backdrop-filter:blur(8px);
  border:1px solid var(--border);border-radius:20px;padding:5px 12px;
  z-index:10;
}
.lsp-size-pill{
  padding:4px 11px;border-radius:12px;border:1px solid var(--border-hi);
  background:transparent;color:#8b9ed4;font-size:.68rem;font-weight:600;cursor:pointer;
  transition:all .13s;font-family:'Montserrat',sans-serif;
}
.lsp-size-pill:hover{background:var(--bg-hover);color:var(--text-bright);}
.lsp-size-pill.active{background:var(--purple-lo);border-color:var(--purple);color:#fff;}

/* ── RIGHT PANEL ── */
#lsp-right{
  width:295px;background:var(--bg-panel);border-left:1px solid var(--border);
  display:flex;flex-direction:column;overflow:hidden;flex-shrink:0;
}
.lsp-panel-tabs{display:flex;border-bottom:1px solid var(--border);flex-shrink:0;}
.lsp-ptab{
  flex:1;padding:11px 4px;text-align:center;font-size:.72rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.07em;color:#6b7ab8;
  cursor:pointer;transition:all .15s;border-bottom:2px solid transparent;
  font-family:'Montserrat',sans-serif;
}
.lsp-ptab:hover{color:#a8b4e8;background:var(--bg-hover);}
.lsp-ptab.active{color:#c4b5fd;border-bottom-color:var(--purple);background:var(--bg-card);}
.lsp-panel-body{flex:1;overflow-y:auto;scrollbar-width:thin;scrollbar-color:var(--border) transparent;}
.lsp-panel-body::-webkit-scrollbar{width:4px;}
.lsp-panel-body::-webkit-scrollbar-thumb{background:var(--border-hi);}

/* Panel sections */
.lsp-section{padding:.7rem .75rem;border-bottom:1px solid var(--border);}
.lsp-section-title{
  font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;
  color:#8b9ed4;margin-bottom:.5rem;display:flex;align-items:center;gap:.4rem;
}
.lsp-section-title i{font-size:.85rem;color:var(--purple);}
.lsp-row{display:flex;align-items:center;gap:.4rem;margin-bottom:.4rem;}
.lsp-label{font-size:.72rem;color:#9ba8d4;white-space:nowrap;min-width:54px;font-weight:600;}
.lsp-inp{
  flex:1;background:var(--bg-deep);border:1px solid var(--border-hi);border-radius:var(--r-sm);
  color:var(--text-bright);font-size:.78rem;padding:.32rem .5rem;outline:none;
  font-family:'Montserrat',sans-serif;transition:border-color .15s;width:100%;
}
.lsp-inp:focus{border-color:var(--purple);}
.lsp-range{width:100%;accent-color:var(--purple);height:4px;cursor:pointer;}
.lsp-select{
  flex:1;background:var(--bg-deep);border:1px solid var(--border-hi);border-radius:var(--r-sm);
  color:var(--text-bright);font-size:.76rem;padding:.3rem .4rem;outline:none;
  font-family:'Montserrat',sans-serif;cursor:pointer;
}
.lsp-color-btn{
  width:28px;height:28px;border-radius:6px;border:2px solid var(--border-hi);
  cursor:pointer;flex-shrink:0;transition:border-color .15s;position:relative;
}
.lsp-color-btn:hover{border-color:var(--text-bright);}
.lsp-color-btn input[type=color]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.lsp-btn-group{display:flex;gap:.3rem;flex-wrap:wrap;}
.lsp-micro-btn{
  padding:.28rem .55rem;border-radius:6px;border:1px solid var(--border-hi);
  background:var(--bg-card);color:#9ba8d4;font-size:.7rem;cursor:pointer;
  transition:all .12s;font-family:'Montserrat',sans-serif;white-space:nowrap;font-weight:600;
}
.lsp-micro-btn:hover{background:var(--bg-hover);color:#fff;border-color:var(--purple);}
.lsp-micro-btn.active{background:var(--purple-lo);border-color:var(--purple);color:#fff;}
.lsp-swatch{
  width:20px;height:20px;border-radius:5px;cursor:pointer;border:2px solid transparent;
  transition:all .12s;flex-shrink:0;
}
.lsp-swatch:hover{border-color:#fff;transform:scale(1.28);}
.lsp-swatches{display:flex;gap:.25rem;flex-wrap:wrap;}

/* ── BOTTOM PANEL — LAYERS ── */
#lsp-bottom{
  height:140px;background:var(--bg-panel);border-top:1px solid var(--border);
  display:flex;flex-direction:column;flex-shrink:0;
  width:100%;
}
#lsp-bottom-header{
  display:flex;align-items:center;padding:0 10px;height:28px;
  border-bottom:1px solid var(--border);flex-shrink:0;gap:6px;
}
#lsp-bottom-header span{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;}
.lsp-layer-action{
  width:26px;height:26px;display:flex;align-items:center;justify-content:center;
  border:1px solid var(--border-hi);background:var(--bg-card);color:#9ba8d4;
  border-radius:5px;cursor:pointer;font-size:.82rem;transition:all .12s;
}
.lsp-layer-action:hover{background:var(--bg-hover);color:#fff;border-color:var(--purple);}
#lsp-layers-list{
  flex:1;overflow-x:auto;overflow-y:hidden;display:flex;align-items:center;
  gap:6px;padding:6px 10px;scrollbar-width:thin;scrollbar-color:var(--border-hi) transparent;
}
.lsp-layer-item{
  flex-shrink:0;width:96px;height:82px;background:var(--bg-card);
  border:1.5px solid var(--border-hi);border-radius:var(--r-md);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  cursor:pointer;transition:all .15s;position:relative;overflow:hidden;gap:4px;
  padding:5px;
}
.lsp-layer-item:hover{border-color:var(--purple);background:var(--bg-hover);}
.lsp-layer-item.active{border-color:var(--purple);box-shadow:0 0 14px var(--accent-glow);background:var(--bg-active);}
.lsp-layer-thumb{width:56px;height:44px;border-radius:4px;background:var(--bg-deep);display:flex;align-items:center;justify-content:center;overflow:hidden;}
.lsp-layer-thumb canvas{width:100%;height:100%;object-fit:contain;}
.lsp-layer-name{font-size:.58rem;color:#9ba8d4;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:100%;font-weight:600;}
.lsp-layer-item.active .lsp-layer-name{color:#fff;}
.lsp-layer-vis{position:absolute;top:3px;right:3px;font-size:.72rem;color:#6b7ab8;cursor:pointer;}
.lsp-layer-type-icon{position:absolute;top:3px;left:3px;font-size:.68rem;color:var(--purple);}

/* ── POPUP PANELS ── */
.lsp-flyout{
  position:fixed;background:var(--bg-panel);border:1px solid var(--border-hi);
  border-radius:var(--r-lg);box-shadow:0 20px 60px rgba(0,0,0,.8),0 0 0 1px rgba(168,85,247,.1);
  z-index:500;display:none;overflow:hidden;
}
.lsp-flyout.open{display:flex;flex-direction:column;}
.lsp-flyout-header{
  padding:.6rem .8rem;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
}
.lsp-flyout-title{font-size:.78rem;font-weight:700;color:var(--text-bright);}
.lsp-flyout-close{
  width:22px;height:22px;border:none;background:transparent;color:var(--text-mid);
  cursor:pointer;font-size:.85rem;display:flex;align-items:center;justify-content:center;
  border-radius:4px;
}
.lsp-flyout-close:hover{background:var(--bg-hover);color:var(--text-bright);}
.lsp-flyout-body{flex:1;overflow-y:auto;padding:.6rem;scrollbar-width:thin;scrollbar-color:var(--border) transparent;}

/* Templates grid */
.lsp-tpl-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.4rem;}
.lsp-tpl-card{
  background:var(--bg-card);border:1.5px solid var(--border);border-radius:var(--r-md);
  cursor:pointer;transition:all .15s;overflow:hidden;
}
.lsp-tpl-card:hover{border-color:var(--purple);transform:translateY(-2px);box-shadow:0 6px 20px var(--accent-glow);}
.lsp-tpl-card.active{border-color:var(--gold);box-shadow:0 0 16px var(--gold-glow);}
.lsp-tpl-card canvas{width:100%;display:block;}
.lsp-tpl-label{font-size:.6rem;color:#9ba8d4;font-weight:600;text-align:center;padding:.22rem .1rem .28rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

/* Icons grid */
.lsp-icon-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:.3rem;}
.lsp-icon-item{
  width:44px;height:44px;display:flex;align-items:center;justify-content:center;
  border:1.5px solid var(--border);border-radius:var(--r-sm);background:var(--bg-card);
  cursor:pointer;font-size:1.3rem;transition:all .13s;
}
.lsp-icon-item:hover{border-color:var(--purple);background:var(--bg-hover);transform:scale(1.1);}
.lsp-icon-item.active{border-color:var(--gold);box-shadow:0 0 10px var(--gold-glow);}

/* Text effects panel */
.lsp-effect-card{
  background:var(--bg-card);border:1.5px solid var(--border-hi);border-radius:var(--r-sm);
  padding:.45rem .55rem;cursor:pointer;transition:all .13s;text-align:center;
  font-size:.68rem;color:#9ba8d4;font-weight:600;
}
.lsp-effect-card:hover{border-color:var(--purple);color:var(--text-bright);}
.lsp-effect-card.active{border-color:var(--gold);color:var(--gold);}
.lsp-effect-preview{width:100%;height:38px;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:900;margin-bottom:.2rem;}

/* Toast */
#lsp-toast{
  position:fixed;bottom:20px;left:50%;transform:translateX(-50%) translateY(60px);
  background:var(--bg-panel);border:1px solid var(--border-hi);
  border-radius:20px;padding:8px 20px;font-size:.75rem;color:var(--text-bright);
  z-index:9999;transition:transform .3s;pointer-events:none;
  box-shadow:0 8px 30px rgba(0,0,0,.6);
}
#lsp-toast.show{transform:translateX(-50%) translateY(0);}

/* Color harmony panel */
.lsp-harmony-swatch{
  height:32px;border-radius:6px;cursor:pointer;transition:all .13s;
  border:2px solid transparent;
}
.lsp-harmony-swatch:hover{border-color:#fff;transform:scaleY(1.1);}
.lsp-harmony-swatch.active{border-color:var(--gold);}

/* Export panel */
.lsp-export-opt{
  background:var(--bg-card);border:1.5px solid var(--border-hi);border-radius:var(--r-md);
  padding:.6rem .7rem;cursor:pointer;transition:all .15s;display:flex;gap:.65rem;
  align-items:center;margin-bottom:.35rem;
}
.lsp-export-opt:hover{border-color:var(--purple);background:var(--bg-hover);}
.lsp-export-opt.active{border-color:var(--gold);}
.lsp-export-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}

/* Cat tabs */
.lsp-cat-row{display:flex;gap:.25rem;flex-wrap:wrap;margin-bottom:.45rem;}
.lsp-cat-btn{padding:.25rem .5rem;border-radius:5px;border:1px solid var(--border-hi);background:var(--bg-deep);color:#8b9ed4;font-size:.68rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:all .12s;font-family:'Montserrat',sans-serif;}
.lsp-cat-btn:hover{background:var(--bg-hover);color:var(--text-bright);}
.lsp-cat-btn.active{background:var(--purple-lo);border-color:var(--purple);color:#fff;}

/* Animation preview panel */
#lsp-anim-preview{width:100%;height:180px;background:var(--bg-deep);border:1px solid var(--border);border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;margin-bottom:.5rem;}
.lsp-anim-btn{padding:.3rem .65rem;border-radius:20px;border:1px solid var(--border-hi);background:var(--bg-deep);color:#9ba8d4;font-size:.7rem;font-weight:600;cursor:pointer;transition:all .13s;font-family:'Montserrat',sans-serif;}
.lsp-anim-btn:hover{background:var(--bg-hover);color:var(--text-bright);}
.lsp-anim-btn.active{background:var(--purple-lo);border-color:var(--purple);color:#fff;}

/* Gradient editor */
#lsp-gradient-bar{width:100%;height:28px;border-radius:6px;border:1px solid var(--border);cursor:pointer;position:relative;margin-bottom:.35rem;}
.lsp-grad-stop{position:absolute;top:0;width:10px;height:100%;cursor:ew-resize;border:2px solid #fff;border-radius:3px;transform:translateX(-50%);}

/* Scrollbar global */
::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px;}

/* ── Canvas size presets responsive ── */
@media(max-height:700px){#lsp-bottom{height:110px;}}

/* ══════════════════════════════════════════════════════════
   MOBILE-NATIVE LAYOUT — everything below is gated to <=900px;
   desktop layout/behavior above that width is completely untouched.
══════════════════════════════════════════════════════════ */
.lsp-mob-only { display:none; }
.lsp-panel-mob-hd { display:none; }
#lspSheetBackdrop { display:none; }

@media(max-width:900px) {
  /* ── Topbar: wrap instead of clipping ── */
  #lsp-topbar { height:auto; min-height:48px; flex-wrap:wrap; row-gap:.3rem; padding:.4rem .5rem; }
  .lsp-top-sep { display:none; }
  .lsp-top-spacer { flex-basis:100%; height:0; }
  .lsp-mob-only { display:flex; }
  #lsp-zoom-display { min-width:34px; }

  /* ── Body stacks: canvas on top, tool rail becomes a bottom bar ── */
  #lsp-body { flex-direction:column; }
  #lsp-canvas-area { order:1; flex:1; min-height:0; padding:12px 12px 56px; }
  #lsp-left {
    order:2; width:100%; height:auto; flex-direction:row; flex-shrink:0;
    overflow-x:auto; overflow-y:visible; -webkit-overflow-scrolling:touch;
    padding:.3rem .4rem; border-right:none; border-top:1px solid var(--border);
    -webkit-mask-image:linear-gradient(to right,#000 90%,transparent);
    mask-image:linear-gradient(to right,#000 90%,transparent);
  }
  .lsp-tool-btn { flex-shrink:0; width:52px; height:50px; }
  .lsp-tool-sep { width:1px; height:32px; margin:0 .3rem; flex-shrink:0; }

  /* ── Bottom layer tray: shrink a little on phones, keep horizontal scroll ── */
  #lsp-bottom { height:96px; }
  .lsp-layer-item { width:72px; height:64px; }
  .lsp-layer-thumb { width:42px; height:32px; }

  /* ── Right panel becomes a slide-up bottom sheet ──
     Capped so a real strip of canvas always stays visible above it (this used to be
     68vh, which — once stacked under the topbar + bottom tool bar + layer tray —
     left almost no canvas visible; you'd open Properties and lose sight of the design). */
  #lsp-right {
    display:none; position:fixed; left:0; right:0; bottom:0; z-index:9300;
    width:auto; max-height:min(42vh, calc(100vh - 320px)); flex-direction:column; overflow:hidden;
    border-radius:16px 16px 0 0; box-shadow:0 -10px 34px rgba(0,0,0,.6);
    border-left:none;
  }
  #lsp-right.mob-open { display:flex; }
  .lsp-panel-mob-hd {
    display:flex; align-items:center; gap:.6rem; padding:.85rem 1rem .5rem;
    border-bottom:1px solid var(--border); flex-shrink:0; position:relative;
  }
  .lsp-panel-mob-hd::before {
    content:''; position:absolute; top:.4rem; left:50%; transform:translateX(-50%);
    width:36px; height:4px; border-radius:3px; background:var(--border-hi);
  }
  .lsp-panel-mob-hd span { font-size:.88rem; font-weight:800; color:var(--text-bright); flex:1; margin-top:.3rem; }
  .lsp-panel-mob-hd button {
    width:32px; height:32px; border:none; background:var(--bg-hover); border-radius:9px;
    display:flex; align-items:center; justify-content:center; font-size:.95rem;
    color:var(--text-bright); cursor:pointer; flex-shrink:0;
  }
  .lsp-panel-body { padding-bottom:env(safe-area-inset-bottom,0px); }
  #lspSheetBackdrop {
    display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9250;
  }
  #lspSheetBackdrop.show { display:block; }

  /* ── Flyouts: bottom-anchored sheet instead of a fixed-position popover next to the old rail ── */
  .lsp-flyout {
    left:8px !important; right:8px !important; top:auto !important;
    bottom:calc(58px + env(safe-area-inset-bottom,0px)) !important;
    width:auto !important; max-width:none !important; height:auto !important;
    max-height:min(45vh, calc(100vh - 280px)) !important;
  }

  /* ── Bigger touch targets ── */
  .lsp-micro-btn, .lsp-cat-btn, .lsp-anim-btn, .lsp-size-pill { padding-top:.42rem; padding-bottom:.42rem; }
  .lsp-icon-item { width:auto; aspect-ratio:1; font-size:1.5rem; }
  .lsp-color-btn { width:32px; height:32px; }
  .lsp-swatch { width:24px; height:24px; }

  /* ── Dense grids: fewer columns so each item stays legible on a phone ── */
  .lsp-icon-grid { grid-template-columns:repeat(4,1fr) !important; }
  #frames-grid { grid-template-columns:repeat(3,1fr) !important; }
  #shapes-grid { grid-template-columns:repeat(4,1fr) !important; }
}
</style>
</head>
<body>

<!-- ═══════ TOP BAR ═══════ -->
<div id="lsp-topbar">
  <?php if (!$isIframe): ?>
  <!-- Standalone entry only (from the icard sidebar/dashboard) — when embedded
       as an iframe inside editor.php's own modal, the parent page's own X
       button closes it; a back button here would instead navigate the
       iframe itself away, stranding it inside that modal's chrome. -->
  <a href="<?= BASE_URL ?>/icard/dashboard" class="lsp-undo-btn" title="Back to Dashboard" style="text-decoration:none;">
    <i class="bi bi-arrow-left"></i>
  </a>
  <?php endif; ?>
  <div class="lsp-logo">
    <div class="lsp-logo-icon">⬡</div>
    <div>
      <div style="line-height:1.1;">Logo <span style="color:var(--purple);">Studio</span></div>
      <div style="display:flex;gap:4px;margin-top:1px;">
        <!-- <span class="lsp-logo-badge">World Pro Max</span> -->
        <span style="font-size:.48rem;background:#1e3a5f;color:#7dd3fc;padding:2px 6px;border-radius:4px;font-weight:700;">🏫 School Edition</span>
      </div>
    </div>
  </div>
  <span id="lsp-dirty-dot" title="Unsaved changes"></span>
  <div class="lsp-top-sep"></div>

  <!-- File -->
  <button class="lsp-top-btn" onclick="lspNew()"><i class="bi bi-file-earmark-plus"></i> New</button>
  <button class="lsp-top-btn" onclick="lspOpenTemplates()"><i class="bi bi-grid-3x3-gap-fill"></i> Templates</button>
  <button class="lsp-top-btn" onclick="lspOpenGallery()"><i class="bi bi-folder2-open"></i> My Logos</button>
  <div class="lsp-top-sep"></div>

  <!-- Edit -->
  <button class="lsp-undo-btn" id="btnUndo" onclick="lspUndo()" title="Undo (Ctrl+Z)"><i class="bi bi-arrow-counterclockwise"></i></button>
  <button class="lsp-undo-btn" id="btnRedo" onclick="lspRedo()" title="Redo (Ctrl+Y)"><i class="bi bi-arrow-clockwise"></i></button>
  <!-- Mobile-only: open the Properties sheet (desktop shows the panel directly) -->
  <button class="lsp-undo-btn lsp-mob-only" onclick="lspOpenPanel()" title="Properties"><i class="bi bi-sliders"></i></button>
  <div class="lsp-top-sep"></div>

  <!-- Zoom -->
  <button class="lsp-undo-btn" onclick="lspZoomOut()" title="Zoom Out"><i class="bi bi-zoom-out"></i></button>
  <span id="lsp-zoom-display">100%</span>
  <button class="lsp-undo-btn" onclick="lspZoomIn()" title="Zoom In"><i class="bi bi-zoom-in"></i></button>
  <button class="lsp-top-btn" onclick="lspZoomFit()"><i class="bi bi-fullscreen"></i></button>
  <div class="lsp-top-sep"></div>

  <!-- View -->
  <button class="lsp-top-btn" id="btnGrid" onclick="lspToggleGrid()"><i class="bi bi-grid"></i> Grid</button>
  <button class="lsp-top-btn" id="btnSnap" onclick="lspToggleSnap()"><i class="bi bi-magnet"></i> Snap</button>
  <button class="lsp-top-btn" id="btnGuides" onclick="lspToggleGuides()"><i class="bi bi-layout-split"></i> Guides</button>
  <div class="lsp-top-sep"></div>

  <!-- BG -->
  <span style="font-size:.72rem;color:#8b9ed4;font-weight:700;">BG:</span>
  <div style="display:flex;gap:3px;">
    <div class="lsp-swatch" style="background:#fff;border-color:#ccc;" onclick="lspSetBg('#fff')" title="White"></div>
    <div class="lsp-swatch" style="background:#000;" onclick="lspSetBg('#000')" title="Black"></div>
    <div class="lsp-swatch" style="background:#1e3a8a;" onclick="lspSetBg('#1e3a8a')" title="Navy"></div>
    <div class="lsp-swatch" id="lsp-bg-custom" style="background:linear-gradient(135deg,#f59e0b,#a855f7);position:relative;overflow:hidden;" title="Custom">
      <input type="color" id="lspBgColor" style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;" onchange="lspSetBg(this.value)">
    </div>
    <div class="lsp-swatch" style="background:repeating-conic-gradient(#aaa 0% 25%,#fff 0% 50%) 0 0/10px 10px;border-color:#aaa;" onclick="lspSetBg('transparent')" title="Transparent"></div>
  </div>

  <div class="lsp-top-spacer"></div>

  <!-- Animate -->
  <button class="lsp-top-btn" onclick="lspOpenAnimation()"><i class="bi bi-play-circle"></i> Animate</button>
  <!-- Save to Gallery -->
  <button class="lsp-top-btn" onclick="lspSaveToGallery()" id="btnSaveGallery" title="Save to My Gallery"><i class="bi bi-cloud-arrow-up-fill"></i> Save</button>
  <!-- Export -->
  <button class="lsp-top-btn gold" onclick="lspOpenExport()"><i class="bi bi-download"></i> Export</button>
  <?php if($isIframe): ?>
  <button class="lsp-top-btn" onclick="lspApplyToEditor()" id="btnApplyEditor"
    style="background:linear-gradient(135deg,#16a34a,#22c55e);border-color:transparent;color:#fff;font-weight:800;font-size:.82rem;padding:6px 16px;box-shadow:0 2px 14px rgba(34,197,94,.45);">
    <i class="bi bi-check-circle-fill"></i> Apply to Canvas
  </button>
  <?php elseif($fromEditor): ?>
  <button class="lsp-top-btn primary" onclick="lspApplyToEditor()" id="btnApplyEditor"><i class="bi bi-check-circle-fill"></i> Apply to Editor</button>
  <?php else: ?>
  <button class="lsp-top-btn primary" onclick="lspApplyToEditor()" id="btnApplyEditor"><i class="bi bi-check-circle-fill"></i> Apply to Canvas</button>
  <?php endif; ?>
</div>

<!-- ═══════ BODY ═══════ -->
<div id="lsp-body">

  <!-- LEFT TOOL SIDEBAR -->
  <div id="lsp-left">
    <button class="lsp-tool-btn active" id="tool-select" onclick="lspSetTool('select',this)" title="Select (V)"><i class="bi bi-cursor-fill"></i><span>Select</span></button>
    <button class="lsp-tool-btn" id="tool-move" onclick="lspSetTool('move',this)" title="Hand / Pan view (H) — drag to scroll a zoomed canvas"><i class="bi bi-hand-index"></i><span>Pan</span></button>
    <div class="lsp-tool-sep"></div>
    <button class="lsp-tool-btn" id="tool-text" onclick="lspAddText()" title="Text (T)"><i class="bi bi-type"></i><span>Text</span></button>
    <button class="lsp-tool-btn" id="tool-shape" onclick="lspOpenShapes()" title="Shape (S)"><i class="bi bi-pentagon"></i><span>Shape</span></button>
    <button class="lsp-tool-btn" id="tool-icon" onclick="lspOpenIcons()" title="Icons (I)"><i class="bi bi-grid-3x3-gap"></i><span>Icons</span></button>
    <div class="lsp-tool-sep"></div>
    <button class="lsp-tool-btn" id="tool-image" onclick="lspUploadImage()" title="Image (P)"><i class="bi bi-image"></i><span>Image</span></button>
    <button class="lsp-tool-btn" id="tool-frame" onclick="lspOpenFrames()" title="Frames (F)"><i class="bi bi-circle"></i><span>Frame</span></button>
    <div class="lsp-tool-sep"></div>
    <button class="lsp-tool-btn" id="tool-draw" onclick="lspStartDraw()" title="Draw / Brush (D)"><i class="bi bi-pen"></i><span>Draw</span></button>
    <button class="lsp-tool-btn" id="tool-curved" onclick="lspAddCircularText('top')" title="Circular Arc Text — Smooth text along circle path"><i class="bi bi-arrow-repeat"></i><span>Arc</span></button>
    <button class="lsp-tool-btn" id="tool-badge" onclick="lspOpenBadgeBuilder()" title="School Badge Builder — Complete circular badge designer"><i class="bi bi-patch-check-fill"></i><span>Badge</span></button>
    <div class="lsp-tool-sep"></div>
    <button class="lsp-tool-btn" onclick="lspOpenColorHarmony()" title="Color Harmony"><i class="bi bi-palette2"></i><span>Palette</span></button>
    <button class="lsp-tool-btn" onclick="lspOpenTemplates()" title="Templates"><i class="bi bi-layout-wtf"></i><span>Templates</span></button>
  </div>

  <!-- CANVAS AREA -->
  <div id="lsp-canvas-area">
    <div id="lsp-canvas-wrap">
      <canvas id="lsp-main-canvas"></canvas>
      <canvas id="lsp-grid-overlay"></canvas>
    </div>
    <!-- Canvas size presets -->
    <div id="lsp-size-bar">
      <span style="font-size:.72rem;color:#8b9ed4;font-weight:700;">Size:</span>
      <button class="lsp-size-pill active" onclick="lspSetCanvasSize(500,500,'Square',this)">Square 500</button>
      <button class="lsp-size-pill" onclick="lspSetCanvasSize(800,600,'Landscape',this)">800×600</button>
      <button class="lsp-size-pill" onclick="lspSetCanvasSize(600,800,'Portrait',this)">600×800</button>
      <button class="lsp-size-pill" onclick="lspSetCanvasSize(1000,1000,'HD Square',this)">HD 1000</button>
      <button class="lsp-size-pill" onclick="lspSetCanvasSize(1200,630,'Banner',this)">Banner</button>
      <button class="lsp-size-pill" onclick="lspCustomSize()">Custom…</button>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div id="lsp-right">
    <div class="lsp-panel-mob-hd">
      <span>Properties</span>
      <button onclick="lspClosePanel()" title="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="lsp-panel-tabs">
      <div class="lsp-ptab active" onclick="lspSwitchPanel('design',this)">Design</div>
      <div class="lsp-ptab" onclick="lspSwitchPanel('text',this)">Text</div>
      <div class="lsp-ptab" onclick="lspSwitchPanel('layer',this)">Layer</div>
    </div>
    <div class="lsp-panel-body" id="lsp-right-body"></div>
  </div>
</div>

<!-- ═══════ BOTTOM — LAYER TRAY ═══════ -->
<div id="lsp-bottom">
  <div id="lsp-bottom-header">
    <span>Layers</span>
    <button class="lsp-layer-action" onclick="lspAddLayer('text')" title="Add Text Layer"><i class="bi bi-plus-lg"></i></button>
    <button class="lsp-layer-action" onclick="lspDuplicateLayer()" title="Duplicate"><i class="bi bi-copy"></i></button>
    <button class="lsp-layer-action" onclick="lspDeleteLayer()" title="Delete"><i class="bi bi-trash"></i></button>
    <button class="lsp-layer-action" onclick="lspLayerUp()" title="Move Up"><i class="bi bi-arrow-up"></i></button>
    <button class="lsp-layer-action" onclick="lspLayerDown()" title="Move Down"><i class="bi bi-arrow-down"></i></button>
    <button class="lsp-layer-action" onclick="lspGroupLayers()" title="Group Selected (Ctrl+G)"><i class="bi bi-collection"></i></button>
    <button class="lsp-layer-action" onclick="lspUngroupLayers()" title="Ungroup (Ctrl+Shift+G)"><i class="bi bi-collection-fill"></i></button>
    <button class="lsp-layer-action" onclick="lspToggleLockActive()" title="Lock / Unlock selected"><i class="bi bi-lock"></i></button>
    <div style="flex:1;"></div>
    <span style="font-size:.68rem;color:#8b9ed4;font-weight:600;" id="lsp-layer-count">0 layers</span>
  </div>
  <div id="lsp-layers-list"></div>
</div>

<!-- ═══════ TOAST ═══════ -->
<div id="lsp-toast"></div>
<div id="lspSheetBackdrop" onclick="lspClosePanel()"></div>

<!-- In-app Confirm dialog (replaces native confirm()) -->
<div id="lspConfirmOv" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9700;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:var(--bg-panel);border:1px solid var(--border-hi);border-radius:14px;max-width:380px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.6);">
    <div style="padding:1.1rem 1.2rem .3rem;font-size:.88rem;color:var(--text-bright);line-height:1.6;" id="lspConfirmMsg"></div>
    <div style="display:flex;gap:.5rem;padding:1rem 1.2rem 1.2rem;">
      <button onclick="_lspConfirmAnswer(false)" style="flex:1;padding:.5rem;border-radius:8px;border:1px solid var(--border-hi);background:transparent;color:#9ba8d4;font-size:.82rem;font-weight:600;cursor:pointer;">Cancel</button>
      <button onclick="_lspConfirmAnswer(true)" style="flex:1;padding:.5rem;border-radius:8px;border:none;background:var(--purple-lo);color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;">OK</button>
    </div>
  </div>
</div>

<!-- In-app Prompt dialog (replaces native prompt()) -->
<div id="lspPromptOv" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9700;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:var(--bg-panel);border:1px solid var(--border-hi);border-radius:14px;max-width:380px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.6);">
    <div style="padding:1.1rem 1.2rem .2rem;font-size:.9rem;font-weight:700;color:var(--text-bright);" id="lspPromptTitle"></div>
    <div style="padding:.4rem 1.2rem 0;" id="lspPromptBody"></div>
    <div style="display:flex;gap:.5rem;padding:1rem 1.2rem 1.2rem;">
      <button onclick="_lspPromptAnswer(false)" style="flex:1;padding:.5rem;border-radius:8px;border:1px solid var(--border-hi);background:transparent;color:#9ba8d4;font-size:.82rem;font-weight:600;cursor:pointer;">Cancel</button>
      <button onclick="_lspPromptAnswer(true)" style="flex:1;padding:.5rem;border-radius:8px;border:none;background:var(--purple-lo);color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;">OK</button>
    </div>
  </div>
</div>

<!-- ═══════ FLYOUT PANELS ═══════ -->

<!-- TEMPLATES FLYOUT -->
<div class="lsp-flyout" id="fly-templates" style="top:55px;left:68px;width:640px;height:500px;">
  <div class="lsp-flyout-header">
    <div class="lsp-flyout-title"><i class="bi bi-layout-wtf" style="color:var(--purple);margin-right:5px;"></i>School Logo Templates</div>
    <button class="lsp-flyout-close" onclick="lspCloseFlyout('fly-templates')">✕</button>
  </div>
  <div style="padding:.5rem .6rem;border-bottom:1px solid var(--border);flex-shrink:0;">
    <input type="text" id="tplSearch" class="lsp-inp" placeholder="🔍 Search templates…" oninput="lspFilterTemplates(this.value)" style="width:100%;">
    <div class="lsp-cat-row" id="tpl-cats" style="margin-top:.4rem;margin-bottom:0;"></div>
  </div>
  <div class="lsp-flyout-body"><div class="lsp-tpl-grid" id="tpl-grid"></div></div>
</div>

<!-- ICONS FLYOUT -->
<div class="lsp-flyout" id="fly-icons" style="top:55px;left:68px;width:480px;height:460px;">
  <div class="lsp-flyout-header">
    <div class="lsp-flyout-title"><i class="bi bi-grid-3x3-gap" style="color:var(--purple);margin-right:5px;"></i>Symbol & Icon Library</div>
    <button class="lsp-flyout-close" onclick="lspCloseFlyout('fly-icons')">✕</button>
  </div>
  <div style="padding:.5rem .6rem;border-bottom:1px solid var(--border);flex-shrink:0;">
    <input type="text" id="iconSearch" class="lsp-inp" placeholder="🔍 Search icons…" oninput="lspFilterIcons(this.value)" style="width:100%;">
    <div class="lsp-cat-row" id="icon-cats" style="margin-top:.4rem;margin-bottom:0;"></div>
  </div>
  <div class="lsp-flyout-body"><div class="lsp-icon-grid" id="icon-grid"></div></div>
</div>

<!-- FRAMES FLYOUT -->
<div class="lsp-flyout" id="fly-frames" style="top:55px;left:68px;width:420px;height:420px;">
  <div class="lsp-flyout-header">
    <div class="lsp-flyout-title"><i class="bi bi-circle" style="color:var(--purple);margin-right:5px;"></i>Logo Frames & Shapes</div>
    <button class="lsp-flyout-close" onclick="lspCloseFlyout('fly-frames')">✕</button>
  </div>
  <div class="lsp-flyout-body"><div id="frames-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:.4rem;"></div></div>
</div>

<!-- SHAPES FLYOUT -->
<div class="lsp-flyout" id="fly-shapes" style="top:55px;left:68px;width:360px;height:340px;">
  <div class="lsp-flyout-header">
    <div class="lsp-flyout-title"><i class="bi bi-pentagon" style="color:var(--purple);margin-right:5px;"></i>Shapes</div>
    <button class="lsp-flyout-close" onclick="lspCloseFlyout('fly-shapes')">✕</button>
  </div>
  <div class="lsp-flyout-body"><div id="shapes-grid" style="display:grid;grid-template-columns:repeat(5,1fr);gap:.4rem;"></div></div>
</div>

<!-- COLOR HARMONY FLYOUT -->
<div class="lsp-flyout" id="fly-harmony" style="top:55px;left:68px;width:340px;height:400px;">
  <div class="lsp-flyout-header">
    <div class="lsp-flyout-title"><i class="bi bi-palette2" style="color:var(--purple);margin-right:5px;"></i>Color Harmony</div>
    <button class="lsp-flyout-close" onclick="lspCloseFlyout('fly-harmony')">✕</button>
  </div>
  <div class="lsp-flyout-body" id="harmony-body"></div>
</div>

<!-- EXPORT FLYOUT -->
<div class="lsp-flyout" id="fly-export" style="top:55px;right:10px;width:300px;height:auto;max-height:480px;">
  <div class="lsp-flyout-header">
    <div class="lsp-flyout-title"><i class="bi bi-download" style="color:var(--gold);margin-right:5px;"></i>Export Logo</div>
    <button class="lsp-flyout-close" onclick="lspCloseFlyout('fly-export')">✕</button>
  </div>
  <div class="lsp-flyout-body" id="export-body"></div>
</div>

<!-- ANIMATION FLYOUT -->
<div class="lsp-flyout" id="fly-animation" style="top:55px;right:10px;width:320px;height:420px;">
  <div class="lsp-flyout-header">
    <div class="lsp-flyout-title"><i class="bi bi-play-circle" style="color:var(--purple);margin-right:5px;"></i>Animation Preview</div>
    <button class="lsp-flyout-close" onclick="lspCloseFlyout('fly-animation')">✕</button>
  </div>
  <div class="lsp-flyout-body" id="anim-body"></div>
</div>

<!-- GALLERY FLYOUT -->
<div class="lsp-flyout" id="fly-gallery" style="top:55px;left:68px;width:560px;height:480px;">
  <div class="lsp-flyout-header">
    <div class="lsp-flyout-title"><i class="bi bi-folder2-open" style="color:var(--gold);margin-right:5px;"></i>My Saved Logos</div>
    <button class="lsp-flyout-close" onclick="lspCloseFlyout('fly-gallery')">✕</button>
  </div>
  <div class="lsp-flyout-body" id="gallery-body">
    <div style="text-align:center;color:var(--text-dim);padding:2rem;font-size:.75rem;">Loading...</div>
  </div>
</div>

<!-- SCHOOL BADGE BUILDER FLYOUT -->
<div class="lsp-flyout" id="fly-badge" style="top:55px;left:68px;width:460px;max-height:580px;height:auto;">
  <div class="lsp-flyout-header">
    <div class="lsp-flyout-title"><i class="bi bi-patch-check-fill" style="color:#fbbf24;margin-right:5px;"></i>School Badge Builder</div>
    <button class="lsp-flyout-close" onclick="lspCloseFlyout('fly-badge')">✕</button>
  </div>
  <div class="lsp-flyout-body" id="badge-body" style="overflow-y:auto;padding:.65rem;"></div>
</div>

<!-- DRAW TOOL FLYOUT -->
<div class="lsp-flyout" id="fly-draw" style="top:390px;left:68px;width:235px;max-height:460px;height:auto;">
  <div class="lsp-flyout-header">
    <div class="lsp-flyout-title"><i class="bi bi-pen-fill" style="color:#f472b6;margin-right:5px;"></i>Draw Tool</div>
    <button class="lsp-flyout-close" onclick="lspExitDraw()">✕</button>
  </div>
  <div class="lsp-flyout-body" style="padding:.6rem;">
    <div style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin-bottom:.3rem;">Brush Type</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.22rem;margin-bottom:.55rem;">
      <button id="dbtn-pencil" class="lsp-micro-btn" onclick="lspSetBrush('pencil')" style="height:34px;font-size:.64rem;flex-direction:column;gap:1px;display:flex;align-items:center;justify-content:center;"><span style="font-size:.9rem;">✏️</span><span>Pencil</span></button>
      <button id="dbtn-ink" class="lsp-micro-btn" onclick="lspSetBrush('ink')" style="height:34px;font-size:.64rem;flex-direction:column;gap:1px;display:flex;align-items:center;justify-content:center;"><span style="font-size:.9rem;">🖊️</span><span>Ink Pen</span></button>
      <button id="dbtn-marker" class="lsp-micro-btn" onclick="lspSetBrush('marker')" style="height:34px;font-size:.64rem;flex-direction:column;gap:1px;display:flex;align-items:center;justify-content:center;"><span style="font-size:.9rem;">🖍️</span><span>Marker</span></button>
      <button id="dbtn-spray" class="lsp-micro-btn" onclick="lspSetBrush('spray')" style="height:34px;font-size:.64rem;flex-direction:column;gap:1px;display:flex;align-items:center;justify-content:center;"><span style="font-size:.9rem;">💨</span><span>Spray</span></button>
      <button id="dbtn-circle" class="lsp-micro-btn" onclick="lspSetBrush('circle')" style="height:34px;font-size:.64rem;flex-direction:column;gap:1px;display:flex;align-items:center;justify-content:center;"><span style="font-size:.9rem;">⚫</span><span>Round</span></button>
      <button id="dbtn-eraser" class="lsp-micro-btn" onclick="lspSetBrush('eraser')" style="height:34px;font-size:.64rem;flex-direction:column;gap:1px;display:flex;align-items:center;justify-content:center;"><span style="font-size:.9rem;">⬜</span><span>Eraser</span></button>
    </div>
    <div style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin-bottom:.3rem;">Color</div>
    <div style="display:flex;gap:.3rem;align-items:center;margin-bottom:.5rem;">
      <input type="color" id="draw-color" value="#000000" onchange="lspSetDrawColor(this.value)" style="width:34px;height:26px;border:none;background:none;cursor:pointer;padding:0;border-radius:4px;">
      <div style="display:flex;flex-wrap:wrap;gap:.18rem;flex:1;">
        <div onclick="lspSetDrawColor('#000000')" style="width:17px;height:17px;background:#000000;border-radius:3px;cursor:pointer;border:1px solid rgba(255,255,255,.25);"></div>
        <div onclick="lspSetDrawColor('#ffffff')" style="width:17px;height:17px;background:#ffffff;border-radius:3px;cursor:pointer;border:1px solid rgba(255,255,255,.25);"></div>
        <div onclick="lspSetDrawColor('#c0392b')" style="width:17px;height:17px;background:#c0392b;border-radius:3px;cursor:pointer;"></div>
        <div onclick="lspSetDrawColor('#1e3a8a')" style="width:17px;height:17px;background:#1e3a8a;border-radius:3px;cursor:pointer;"></div>
        <div onclick="lspSetDrawColor('#15803d')" style="width:17px;height:17px;background:#15803d;border-radius:3px;cursor:pointer;"></div>
        <div onclick="lspSetDrawColor('#fbbf24')" style="width:17px;height:17px;background:#fbbf24;border-radius:3px;cursor:pointer;"></div>
        <div onclick="lspSetDrawColor('#f472b6')" style="width:17px;height:17px;background:#f472b6;border-radius:3px;cursor:pointer;"></div>
        <div onclick="lspSetDrawColor('#8b5cf6')" style="width:17px;height:17px;background:#8b5cf6;border-radius:3px;cursor:pointer;"></div>
        <div onclick="lspSetDrawColor('#06b6d4')" style="width:17px;height:17px;background:#06b6d4;border-radius:3px;cursor:pointer;"></div>
        <div onclick="lspSetDrawColor('#f97316')" style="width:17px;height:17px;background:#f97316;border-radius:3px;cursor:pointer;"></div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;margin-bottom:.12rem;">
      <div style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;">Brush Size</div>
      <div id="draw-size-lbl" style="font-size:.68rem;color:#38bdf8;font-weight:700;">4px</div>
    </div>
    <input type="range" min="1" max="80" value="4" id="draw-size" oninput="lspSetDrawSize(+this.value)" style="width:100%;accent-color:#38bdf8;margin-bottom:.45rem;">
    <div style="display:flex;justify-content:space-between;margin-bottom:.12rem;">
      <div style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;">Opacity</div>
      <div id="draw-opacity-lbl" style="font-size:.68rem;color:#a78bfa;font-weight:700;">100%</div>
    </div>
    <input type="range" min="5" max="100" value="100" id="draw-opacity" oninput="lspSetDrawOpacity(+this.value)" style="width:100%;accent-color:#a78bfa;margin-bottom:.5rem;">
    <div style="height:1px;background:var(--border-hi);margin:0 0 .4rem;"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.22rem;margin-bottom:.3rem;">
      <button class="lsp-micro-btn" onclick="lspUndo()" style="height:30px;font-size:.64rem;color:#f87171;">↶ Undo Stroke</button>
      <button class="lsp-micro-btn" onclick="lspClearAllStrokes()" style="height:30px;font-size:.64rem;color:#f87171;">🗑 Clear Strokes</button>
    </div>
    <button onclick="lspExitDraw()" style="width:100%;padding:.48rem;background:linear-gradient(90deg,#6366f1,#8b5cf6);border:none;border-radius:8px;color:#fff;font-weight:700;font-size:.74rem;cursor:pointer;letter-spacing:.03em;">✅ Done — Exit Draw Mode</button>
  </div>
</div>

<!-- FILE INPUT for image upload -->
<input type="file" id="lsp-file-input" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" style="display:none" onchange="lspHandleImageUpload(event)">
<script>
/* LOGO STUDIO PRO — JavaScript Engine */
/* Bigger touch handles for corner/rotate controls on touchscreens only — desktop mouse behavior (cornerSize) is untouched */
fabric.Object.prototype.touchCornerSize = 24;
const LSP = {
  canvas:null, canvasW:500, canvasH:500, bgColor:'#ffffff', isBgTransparent:false,
  activeTool:'select', activePanel:'design', showGrid:false, showSnap:false, showGuides:false,
  zoom:1, history:[], histIdx:-1, maxHistory:50, layers:[], selectedObj:null,
  currentFont:'Montserrat', currentFontSize:48, currentTextColor:'#1e3a8a',
  currentFillColor:'#1e3a8a', currentStrokeColor:'#fbbf24', currentStrokeWidth:3,
  gridSize:20, snapThreshold:8, isDrawing:false, animTimer:null,
  exportFormat:'png', exportScale:3, exportTight:true,
  currentGalleryId:0, _galleryName:'My Logo Design',
  dirty:false, _restoring:false, clipboard:null, _pasteCount:0,
};

/* ══════════════ CORE HELPERS (shared by every tool below) ══════════════ */
/* Image filters on photos larger than 2048px were silently cropped by the
   default WebGL tile size — raise it (uploads are also capped to 4096px). */
fabric.textureSize = 4096;
/* Every custom property that must survive undo/redo, save and duplicate.
   Without these, lock state, layer names and "not selectable" decorations
   were lost the moment you pressed Undo or re-opened a saved logo. */
const LSP_JSON_PROPS = ['lspName','lspDrawn','lspEraser','selectable','evented','hasControls',
  'lockMovementX','lockMovementY','lockRotation','lockScalingX','lockScalingY'];
function lspEsc(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function lspIsText(o){return !!o&&(o.type==='i-text'||o.type==='text'||o.type==='textbox');}
/* Objects a property change should apply to: every member of a multi-selection
   (setting fill on an ActiveSelection itself does nothing), else the single object. */
function lspTargets(){const c=LSP.canvas;if(!c)return[];const a=c.getActiveObjects();return a&&a.length?a:[];}
function lspMarkDirty(){LSP.dirty=true;document.getElementById('lsp-dirty-dot')?.classList.add('show');}
function lspMarkClean(){LSP.dirty=false;document.getElementById('lsp-dirty-dot')?.classList.remove('show');}
/* Render + record one undo step. Every property setter funnels through here so
   colour/size/font/etc. changes are undoable (previously only mouse drags were). */
function lspTouch(){LSP.canvas.requestRenderAll();lspSaveHistory();}
/* Hex colour + alpha → rgba(). Templates did `'#fff'+'55'`, which builds the
   invalid colour '#fff55' and rendered black/transparent instead of a tint. */
function lspAlpha(color,a){
  const h=lspToHexColor(color);
  const r=parseInt(h.slice(1,3),16),g=parseInt(h.slice(3,5),16),b=parseInt(h.slice(5,7),16);
  return `rgba(${r},${g},${b},${a})`;
}
/* Load a web font before measuring text with it — Fabric caches glyph widths,
   so text set in a not-yet-loaded font kept a wrong width/clipped box forever. */
function lspEnsureFont(family,weight){
  if(!family||!document.fonts||!document.fonts.load)return Promise.resolve();
  return document.fonts.load(`${weight||'normal'} 40px "${family}"`).then(()=>{
    try{fabric.util.clearFabricFontCache(family);}catch(e){}
  }).catch(()=>{});
}
function lspRefreshTextMetrics(objs){
  (objs||LSP.canvas.getObjects()).forEach(o=>{
    if(o.type==='group'){lspRefreshTextMetrics(o.getObjects());o.dirty=true;return;}
    if(lspIsText(o)&&o.initDimensions){o.initDimensions();o.setCoords();o.dirty=true;}
    if(o.type==='circularText'){o._recalcSize();o.dirty=true;}
  });
  LSP.canvas.requestRenderAll();
}
/* Zoom-independent export. Fabric's toDataURL multiplies the *on-screen*
   (zoomed) size, so the same "3× Print" export came out smaller whenever the
   view was zoomed out (always on phones). Dividing by the zoom fixes it. */
function lspDataURL(opts){
  opts=opts||{};
  const c=LSP.canvas,z=c.getZoom()||1,fmt=opts.format||'png';
  const origBg=c.backgroundColor;
  let bg=origBg;
  if(opts.transparent)bg='';
  if((fmt==='jpeg'||fmt==='jpg')&&(!bg||bg==='transparent'))bg='#ffffff'; // JPG has no alpha → black otherwise
  c.backgroundColor=bg;
  try{
    return c.toDataURL({format:fmt==='jpg'?'jpeg':fmt,quality:opts.quality,multiplier:(opts.multiplier||1)/z});
  }finally{c.backgroundColor=origBg;}
}
/* Trim empty margins around the design (shared by Smart-crop export and Apply). */
function lspSmartCrop(url,mime,quality,cb){
  const img=new Image();
  img.onload=()=>{
    const c=document.createElement('canvas');c.width=img.width;c.height=img.height;
    const ctx=c.getContext('2d',{willReadFrequently:true});ctx.drawImage(img,0,0);
    let d;try{d=ctx.getImageData(0,0,c.width,c.height).data;}catch(e){cb(url,img.width,img.height);return;}
    let bgR=0,bgG=0,bgB=0,bgA=0,sp=0;
    for(let by=0;by<Math.min(8,c.height);by++)for(let bx=0;bx<Math.min(8,c.width);bx++){
      const bi=(by*c.width+bx)*4;bgR+=d[bi];bgG+=d[bi+1];bgB+=d[bi+2];bgA+=d[bi+3];sp++;
    }
    bgR/=sp;bgG/=sp;bgB/=sp;bgA/=sp;
    const isBgTransp=bgA<15,TOL=16;
    let x0=c.width,y0=c.height,x1=-1,y1=-1;
    for(let y=0;y<c.height;y++){const row=y*c.width*4;for(let x=0;x<c.width;x++){
      const i=row+x*4;
      const empty=isBgTransp?d[i+3]<10:(Math.abs(d[i]-bgR)<TOL&&Math.abs(d[i+1]-bgG)<TOL&&Math.abs(d[i+2]-bgB)<TOL&&d[i+3]>180);
      if(!empty){if(x<x0)x0=x;if(x>x1)x1=x;if(y<y0)y0=y;if(y>y1)y1=y;}
    }}
    if(x1<0){cb(url,c.width,c.height);return;}
    const pad=Math.max(12,Math.round(Math.max(c.width,c.height)*.02));
    x0=Math.max(0,x0-pad);y0=Math.max(0,y0-pad);x1=Math.min(c.width,x1+pad+1);y1=Math.min(c.height,y1+pad+1);
    const cw=x1-x0,ch=y1-y0;
    if(cw<30||ch<30){cb(url,c.width,c.height);return;}
    const out=document.createElement('canvas');out.width=cw;out.height=ch;
    out.getContext('2d').drawImage(c,x0,y0,cw,ch,0,0,cw,ch);
    cb(out.toDataURL(mime||'image/png',quality),cw,ch);
  };
  img.onerror=()=>cb(url,0,0);
  img.src=url;
}
window.addEventListener('beforeunload',e=>{if(LSP.dirty&&LSP.canvas&&LSP.canvas.getObjects().length){e.preventDefault();e.returnValue='';}});

/* 300+ ICONS */
const LSP_ICONS = {
  'School':[
    {e:'🎓',l:'Graduation'},{e:'📚',l:'Books'},{e:'✏️',l:'Pencil'},{e:'🏫',l:'School'},
    {e:'📐',l:'Set Square'},{e:'📏',l:'Ruler'},{e:'🔭',l:'Telescope'},{e:'📝',l:'Notepad'},
    {e:'🎨',l:'Art Palette'},{e:'🔬',l:'Microscope'},{e:'🧮',l:'Abacus'},{e:'📖',l:'Open Book'},
    {e:'🏛️',l:'Library'},{e:'🧪',l:'Test Tube'},{e:'🖊️',l:'Pen'},{e:'📒',l:'Notebook'},
    {e:'📌',l:'Pin'},{e:'🖋️',l:'Fountain Pen'},{e:'📜',l:'Scroll'},{e:'🔑',l:'Key'},
    {e:'💡',l:'Light Bulb'},{e:'🧠',l:'Brain'},{e:'🎯',l:'Target'},{e:'🏅',l:'Medal'},
    {e:'🏆',l:'Trophy'},{e:'🥇',l:'Gold Medal'},{e:'🎖️',l:'Honor Badge'},{e:'🌟',l:'Star'},
    {e:'⭐',l:'Star 2'},{e:'🔢',l:'Numbers'},
  ],
  'Indian':[
    {e:'🪔',l:'Diya'},{e:'ॐ',l:'Om'},{e:'🌸',l:'Lotus'},{e:'🦚',l:'Peacock'},
    {e:'🌺',l:'Hibiscus'},{e:'🛕',l:'Temple'},{e:'🪷',l:'Pink Lotus'},{e:'🏰',l:'Fort'},
    {e:'🌙',l:'Moon'},{e:'🌞',l:'Surya'},{e:'🦁',l:'Lion'},{e:'🐘',l:'Elephant'},
    {e:'🦅',l:'Eagle'},{e:'🏔️',l:'Himalaya'},{e:'🌊',l:'River'},{e:'🌾',l:'Wheat'},
    {e:'🌴',l:'Palm'},{e:'🎋',l:'Bamboo'},{e:'🕉️',l:'Om Hindu'},{e:'☸️',l:'Dharma'},
    {e:'🇮🇳',l:'India Flag'},{e:'🎑',l:'Moon Fest'},{e:'🏺',l:'Pot'},{e:'💐',l:'Bouquet'},
    {e:'🌹',l:'Rose'},{e:'🌻',l:'Sunflower'},{e:'🦋',l:'Butterfly'},{e:'🕊️',l:'Dove'},
    {e:'🌏',l:'World'},{e:'🎪',l:'Mela'},
  ],
  'Values':[
    {e:'❤️',l:'Heart'},{e:'💎',l:'Diamond'},{e:'👑',l:'Crown'},{e:'🌈',l:'Rainbow'},
    {e:'🤝',l:'Handshake'},{e:'💪',l:'Strength'},{e:'🌱',l:'Growth'},{e:'⚡',l:'Power'},
    {e:'🔥',l:'Flame'},{e:'✨',l:'Sparkle'},{e:'🌟',l:'Glow'},{e:'💫',l:'Aura'},
    {e:'🎗️',l:'Ribbon'},{e:'🛡️',l:'Shield'},{e:'🔱',l:'Trident'},{e:'🌿',l:'Leaf'},
    {e:'🍃',l:'Leaves'},{e:'🌲',l:'Tree'},{e:'🌳',l:'Oak'},{e:'🦌',l:'Deer'},
    {e:'🦜',l:'Parrot'},{e:'🐯',l:'Tiger'},{e:'🦉',l:'Owl'},{e:'🐉',l:'Dragon'},
    {e:'🦄',l:'Unicorn'},{e:'🌠',l:'Shooting Star'},{e:'☮️',l:'Peace'},{e:'🙏',l:'Namaste'},
    {e:'🎯',l:'Focus'},{e:'💡',l:'Knowledge'},
  ],
  'Geometric':[
    {e:'⬡',l:'Hexagon'},{e:'◆',l:'Diamond'},{e:'▲',l:'Triangle'},{e:'●',l:'Circle'},
    {e:'■',l:'Square'},{e:'✦',l:'Star4'},{e:'★',l:'Star5'},{e:'✸',l:'Star8'},
    {e:'∞',l:'Infinity'},{e:'◉',l:'Bullseye'},{e:'⊕',l:'Circle+'},{e:'⬟',l:'Pentagon'},
    {e:'✤',l:'Cross'},{e:'⟳',l:'Cycle'},{e:'◈',l:'Diamond In'},{e:'❋',l:'Asterisk'},
    {e:'❂',l:'Sun Out'},{e:'⊙',l:'Dot'},{e:'△',l:'Tri Alt'},{e:'▽',l:'Tri Down'},
    {e:'⬠',l:'Hex2'},{e:'⊗',l:'Circle X'},{e:'⊘',l:'No'},{e:'◯',l:'Large Circ'},
    {e:'❖',l:'Diamond4'},{e:'⧗',l:'Hourglass'},{e:'⊞',l:'Grid'},{e:'⌬',l:'Tri Out'},
    {e:'⎔',l:'Hex Out'},{e:'⏣',l:'Hex Bold'},
  ],
  'Sports':[
    {e:'⚽',l:'Football'},{e:'🏏',l:'Cricket'},{e:'🏸',l:'Badminton'},{e:'🎾',l:'Tennis'},
    {e:'🏊',l:'Swimming'},{e:'🏃',l:'Running'},{e:'🚴',l:'Cycling'},{e:'🤸',l:'Gymnastics'},
    {e:'🏋️',l:'Weightlift'},{e:'🎯',l:'Archery'},{e:'🏐',l:'Volleyball'},{e:'🏀',l:'Basketball'},
    {e:'🥋',l:'Martial Arts'},{e:'♟️',l:'Chess'},{e:'🏓',l:'Table Tennis'},{e:'🤼',l:'Wrestling'},
    {e:'🏑',l:'Hockey'},{e:'🎱',l:'Billiards'},{e:'⛳',l:'Golf'},{e:'🥊',l:'Boxing'},
    {e:'🤺',l:'Fencing'},{e:'🏹',l:'Bow & Arrow'},{e:'🥇',l:'Gold'},{e:'🏆',l:'Trophy'},
    {e:'🎖️',l:'Medal'},{e:'🏅',l:'Sport Medal'},{e:'🎿',l:'Ski'},{e:'🧗',l:'Climb'},
    {e:'🤽',l:'Water Polo'},{e:'🎽',l:'Jersey'},
  ],
  'Arts':[
    {e:'🎨',l:'Art Palette'},{e:'🎵',l:'Note'},{e:'🎶',l:'Notes'},{e:'💃',l:'Dancer'},
    {e:'🎭',l:'Theater'},{e:'🎻',l:'Violin'},{e:'🎺',l:'Trumpet'},{e:'🥁',l:'Drums'},
    {e:'🪗',l:'Accordion'},{e:'🎸',l:'Guitar'},{e:'🎹',l:'Piano'},{e:'🎤',l:'Mic'},
    {e:'📸',l:'Camera'},{e:'🎬',l:'Film'},{e:'✂️',l:'Scissors'},{e:'🖌️',l:'Brush'},
    {e:'🖍️',l:'Crayon'},{e:'🩰',l:'Ballet'},{e:'🪕',l:'Banjo'},{e:'🎷',l:'Sax'},
    {e:'🎙️',l:'Studio Mic'},{e:'🎼',l:'Score'},{e:'🎪',l:'Circus'},{e:'🌌',l:'Cosmos'},
    {e:'🌠',l:'Stars'},{e:'🌅',l:'Sunrise'},{e:'🖼️',l:'Frame'},{e:'🎠',l:'Carousel'},
    {e:'🎡',l:'Wheel'},{e:'📽️',l:'Projector'},
  ],
  'Science':[
    {e:'🔬',l:'Microscope'},{e:'🔭',l:'Telescope'},{e:'⚗️',l:'Alembic'},{e:'🧬',l:'DNA'},
    {e:'💊',l:'Pill'},{e:'🩺',l:'Stethoscope'},{e:'⚛️',l:'Atom'},{e:'🤖',l:'Robot'},
    {e:'💻',l:'Laptop'},{e:'📱',l:'Mobile'},{e:'⚙️',l:'Gear'},{e:'🔧',l:'Wrench'},
    {e:'🔩',l:'Bolt'},{e:'💡',l:'Bulb'},{e:'⚡',l:'Power'},{e:'🌐',l:'Internet'},
    {e:'📡',l:'Satellite'},{e:'🚀',l:'Rocket'},{e:'✈️',l:'Plane'},{e:'🧲',l:'Magnet'},
    {e:'🔋',l:'Battery'},{e:'💾',l:'Disk'},{e:'🖥️',l:'Desktop'},{e:'🧪',l:'Lab'},
    {e:'🧫',l:'Culture'},{e:'🧲',l:'Magnet 2'},{e:'🔭',l:'Scope'},{e:'🛸',l:'UFO'},
    {e:'🌡️',l:'Thermometer'},{e:'🔌',l:'Plug'},
  ],
  'Mahapurush':[
    /* Indian Great Personalities — symbolic icons */
    {e:'🪷',l:'Saraswati Mata'},{e:'📚',l:'Saraswati Books'},{e:'🎵',l:'Saraswati Veena'},{e:'🎻',l:'Veena Symbol'},
    {e:'☸️',l:'Ambedkar Wheel'},{e:'📜',l:'Constitution'},{e:'⚖️',l:'Ambedkar Justice'},{e:'🔵',l:'B.R. Ambedkar'},
    {e:'☮️',l:'Gandhi Peace'},{e:'🌹',l:'Nehru Rose'},{e:'🌀',l:'Gandhi Charkha'},{e:'🕊️',l:'Gandhi Dove'},
    {e:'🔥',l:'Bhagat Singh Flame'},{e:'✊',l:'Azad Fist'},{e:'⚔️',l:'Subhash Bose Sword'},{e:'🗡️',l:'Sword Symbol'},
    {e:'🚀',l:'Kalam Rocket'},{e:'🌟',l:'Kalam Star'},{e:'🧠',l:'Kalam Genius'},{e:'🔭',l:'Kalam Science'},
    {e:'🐯',l:'Shivaji Tiger'},{e:'🦁',l:'Shivaji Lion'},{e:'🏰',l:'Shivaji Fort'},{e:'🗡️',l:'Rana Pratap Sword'},
    {e:'🦅',l:'Subhash Eagle'},{e:'🌾',l:'Shastri Jai Kisan'},{e:'🌺',l:'Sarojini Naidu Flower'},{e:'🌻',l:'Tilak Sun'},
    {e:'🎨',l:'Tagore Art'},{e:'🖊️',l:'Tagore Pen'},{e:'🌸',l:'Meera Bhakti'},{e:'🪔',l:'Diya Knowledge'},
  ],
  'Deities':[
    /* Hindu Deities — symbolic representations */
    {e:'🪷',l:'Saraswati Lotus'},{e:'🎵',l:'Saraswati Veena'},{e:'📚',l:'Saraswati Granth'},
    {e:'ॐ',l:'Om Shiv'},{e:'🔱',l:'Trishul Shiv'},{e:'🐄',l:'Nandi'},{e:'🌙',l:'Chandra'},
    {e:'🙏',l:'Namaste Devi'},{e:'🌺',l:'Lakshmi Lotus'},{e:'🌸',l:'Parvati'},{e:'🪔',l:'Diya Devi'},
    {e:'🦚',l:'Saraswati Peacock'},{e:'🦢',l:'Saraswati Hans'},{e:'🌊',l:'Ganga Mata'},
    {e:'☸️',l:'Vishnu Chakra'},{e:'🐚',l:'Vishnu Shankh'},{e:'🌅',l:'Surya Dev'},
    {e:'🦅',l:'Garuda'},{e:'🌿',l:'Tulsi Sacred'},{e:'🪷',l:'Lotus Brahma'},
    {e:'🐘',l:'Ganesha'},{e:'🍬',l:'Modak Ganesha'},{e:'🌙',l:'Ganesh Crescent'},
    {e:'🎺',l:'Krishna Bansuri'},{e:'🌻',l:'Surya Mandal'},{e:'⭐',l:'Ashtabhuja'},
    {e:'🦁',l:'Durga Simha'},{e:'💫',l:'Shakti'},{e:'🕉️',l:'Om Universal'},{e:'✨',l:'Divine Light'},
  ],
  'Religious':[
    {e:'ॐ',l:'Om'},{e:'🕉️',l:'Om Hindu'},{e:'☸️',l:'Dharma'},{e:'✝️',l:'Cross'},
    {e:'☦️',l:'Orthodox'},{e:'✡️',l:'Star David'},{e:'☪️',l:'Crescent'},{e:'🛐',l:'Worship'},
    {e:'🕌',l:'Mosque'},{e:'🕍',l:'Synagogue'},{e:'⛪',l:'Church'},{e:'🛕',l:'Temple'},
    {e:'🪔',l:'Diya'},{e:'🕯️',l:'Candle'},{e:'📿',l:'Prayer Beads'},{e:'🙏',l:'Namaste'},
    {e:'☮️',l:'Peace'},{e:'🌙',l:'Moon'},{e:'🌟',l:'Holy Star'},{e:'💫',l:'Aura'},
    {e:'✨',l:'Divine'},{e:'🔯',l:'Star 6'},{e:'🕎',l:'Menorah'},{e:'⛎',l:'Ophiuchus'},
    {e:'🌸',l:'Lotus Sacred'},{e:'🌺',l:'Sacred Flower'},{e:'🌿',l:'Sacred Leaf'},{e:'🦚',l:'Sacred Bird'},
    {e:'🐘',l:'Ganesha Sym'},{e:'🦁',l:'Narasimha'},
  ],
  'Medals & Awards':[
    {e:'🥇',l:'Gold Medal'},{e:'🥈',l:'Silver Medal'},{e:'🥉',l:'Bronze Medal'},
    {e:'🏅',l:'Sports Medal'},{e:'🎖️',l:'Military Medal'},{e:'🏆',l:'Trophy'},
    {e:'🎗️',l:'Awareness Ribbon'},{e:'🎀',l:'Bow Ribbon'},{e:'🏵️',l:'Rosette'},
    {e:'⭐',l:'Star Award'},{e:'🌟',l:'Gold Star'},{e:'💫',l:'Shining Star'},
    {e:'👑',l:'Crown'},{e:'💎',l:'Diamond'},{e:'🔰',l:'Beginner Badge'},
    {e:'📛',l:'Name Badge'},{e:'🎫',l:'Certificate'},{e:'🎟️',l:'Award Ticket'},
    {e:'🏆',l:'Champion Cup'},{e:'🥏',l:'Frisbee Award'},{e:'🎯',l:'Bullseye'},
    {e:'✨',l:'Excellence Star'},{e:'💠',l:'Diamond Badge'},{e:'🔷',l:'Blue Gem'},
    {e:'🔶',l:'Gold Gem'},{e:'🏅',l:'Honor Medal'},{e:'🎖️',l:'Service Medal'},
    {e:'🌠',l:'Shooting Star'},{e:'🎊',l:'Celebration'},{e:'🎉',l:'Party Award'},
    {e:'🎈',l:'Achievement'},{e:'🌈',l:'Rainbow Award'},
  ],
  'Ribbons & Banners':[
    {e:'🎗️',l:'Ribbon'},{e:'🎀',l:'Bow'},{e:'🎁',l:'Gift Ribbon'},{e:'🏮',l:'Lantern'},
    {e:'🎏',l:'Streamer'},{e:'🎊',l:'Confetti'},{e:'🎉',l:'Banner Burst'},
    {e:'📜',l:'Scroll Banner'},{e:'🗺️',l:'Map Scroll'},{e:'📋',l:'Certificate'},
    {e:'🖼️',l:'Frame'},{e:'🪄',l:'Magic Wand'},{e:'✂️',l:'Cut Ribbon'},
    {e:'🎈',l:'Balloon'},{e:'🎆',l:'Fireworks'},{e:'🎇',l:'Sparkler'},
    {e:'🌸',l:'Floral Ribbon'},{e:'🌺',l:'Flower Fita'},{e:'💐',l:'Bouquet Fita'},
    {e:'🌼',l:'Daisy Ribbon'},{e:'🌻',l:'Sunflower Fita'},{e:'🌹',l:'Rose Ribbon'},
    {e:'🍃',l:'Leaf Fita'},{e:'🌿',l:'Vine Ribbon'},{e:'🎋',l:'Bamboo Fita'},
    {e:'🦋',l:'Butterfly Fita'},{e:'🕊️',l:'Dove Banner'},{e:'🦅',l:'Eagle Banner'},
    {e:'📣',l:'Megaphone'},{e:'📢',l:'Announcement'},{e:'🎺',l:'Fanfare'},
  ],
  'Flags':[], /* rendered via LSP_FLAG_DATA in lspRenderIconGrid */
};

/* ── National Flag color data — rendered as actual colored Fabric.js objects ── */
const LSP_FLAG_DATA = {
  IN:{n:'India',      h:['#FF9933','#FFFFFF','#138808'], sym:'☸', sc:'#000080', symSz:.42},
  US:{n:'USA',        h:['#B22234','#FFFFFF','#B22234','#FFFFFF','#B22234','#FFFFFF','#B22234'], ct:'#3C3B6E'},
  GB:{n:'UK',         bg:'#012169', cross:'#C8102E', crossBg:'#FFFFFF', crossX:.28},
  DE:{n:'Germany',    h:['#000000','#DD0000','#FFCE00']},
  FR:{n:'France',     v:['#002395','#FFFFFF','#ED2939']},
  IT:{n:'Italy',      v:['#009246','#FFFFFF','#CE2B37']},
  BE:{n:'Belgium',    v:['#000000','#FFD90C','#EF3340']},
  NL:{n:'Netherlands',h:['#AE1C28','#FFFFFF','#21468B']},
  RU:{n:'Russia',     h:['#FFFFFF','#0039A6','#D52B1E']},
  JP:{n:'Japan',      bg:'#FFFFFF', dot:'#BC002D'},
  CN:{n:'China',      bg:'#DE2910', sym:'★', sc:'#FFDE00', symSz:.55},
  CA:{n:'Canada',     v:['#FF0000','#FFFFFF','#FF0000'], sym:'🍁', sc:'#FF0000', symSz:.4},
  AU:{n:'Australia',  bg:'#00008B', sym:'★', sc:'#FFFFFF', symSz:.5},
  BR:{n:'Brazil',     bg:'#009C3B', dia:'#FEDF00', inn:'#002776'},
  ZA:{n:'S.Africa',   h:['#007A4D','#FFB612','#DE3831'], left:'#000000'},
  PK:{n:'Pakistan',   bg:'#01411C', left:'#FFFFFF', sym:'☪', sc:'#FFFFFF', symSz:.45},
  NP:{n:'Nepal',      bg:'#003893', h:['#DC143C','#003893']},
  BD:{n:'Bangladesh', bg:'#006A4E', dot:'#F42A41', dotX:-.08},
  LK:{n:'Sri Lanka',  bg:'#8D153A', sym:'⚔', sc:'#FFBE00', symSz:.4},
  AE:{n:'UAE',        h:['#00732F','#FFFFFF','#000000'], left:'#FF0000'},
  SG:{n:'Singapore',  h:['#EF3340','#FFFFFF'], sym:'★', sc:'#EF3340', symSz:.3},
  MY:{n:'Malaysia',   h:['#CC0001','#FFFFFF','#CC0001','#FFFFFF','#CC0001'], ct:'#010066'},
  ID:{n:'Indonesia',  h:['#CE1126','#FFFFFF']},
  TH:{n:'Thailand',   h:['#A51931','#FFFFFF','#2D2A4A','#FFFFFF','#A51931']},
  KR:{n:'S.Korea',    bg:'#FFFFFF', sym:'☯', sc:'#CD2E3A', symSz:.4},
  SA:{n:'Saudi Arabia',bg:'#006C35', sym:'✦', sc:'#FFFFFF', symSz:.4},
  IR:{n:'Iran',       h:['#239F40','#FFFFFF','#DA0000']},
  TR:{n:'Turkey',     bg:'#E30A17', sym:'☪', sc:'#FFFFFF', symSz:.45},
  EG:{n:'Egypt',      h:['#CE1126','#FFFFFF','#000000']},
  NG:{n:'Nigeria',    v:['#008751','#FFFFFF','#008751']},
  KE:{n:'Kenya',      h:['#006600','#FFFFFF','#BB0000']},
  PH:{n:'Philippines',h:['#0038A8','#CE1126'], tri:'#FCD116'},
  VN:{n:'Vietnam',    bg:'#DA251D', sym:'★', sc:'#FFFF00', symSz:.45},
  AR:{n:'Argentina',  h:['#74ACDF','#FFFFFF','#74ACDF'], sym:'☀', sc:'#F6B40E', symSz:.35},
  MX:{n:'Mexico',     v:['#006847','#FFFFFF','#CE1126']},
  PL:{n:'Poland',     h:['#FFFFFF','#DC143C']},
  UA:{n:'Ukraine',    h:['#005BBB','#FFD500']},
  GR:{n:'Greece',     bg:'#0D5EAF', h:['#0D5EAF','#FFFFFF','#0D5EAF','#FFFFFF','#0D5EAF','#FFFFFF','#0D5EAF','#FFFFFF','#0D5EAF']},
  SE:{n:'Sweden',     bg:'#006AA7', cross:'#FECC02', crossX:.32},
  NO:{n:'Norway',     bg:'#EF2B2D', cross:'#003087', crossBg:'#FFFFFF', crossX:.32},
  DK:{n:'Denmark',    bg:'#C60C30', cross:'#FFFFFF', crossX:.32},
  FI:{n:'Finland',    bg:'#FFFFFF', cross:'#003580', crossX:.32},
  AT:{n:'Austria',    h:['#ED2939','#FFFFFF','#ED2939']},
  CH:{n:'Switzerland',bg:'#FF0000', cross:'#FFFFFF', crossX:.5},
  PT:{n:'Portugal',   v:['#006600','#FF0000'], sym:'○', sc:'#FFFF00', symSz:.3},
  ES:{n:'Spain',      h:['#AA151B','#F1BF00','#AA151B']},
  HU:{n:'Hungary',    h:['#CE2939','#FFFFFF','#477050']},
  CZ:{n:'Czech Rep',  h:['#FFFFFF','#D7141A'], tri:'#11457E'},
  RO:{n:'Romania',    v:['#002B7F','#FCD116','#CE1126']},
  NZ:{n:'New Zealand',bg:'#00247D', sym:'★', sc:'#CC142B', symSz:.42},
};

/* TEMPLATE GENERATOR */
function mkTpl(id,label,cat,cfg){return{id,label,cat,cfg};}
function mkC(pri,sec,acc,sym,frame,font,type){
  return{primaryColor:pri,secondary:sec,accent:acc,symbol:sym,frame:frame||'circle',
    font:font||'Montserrat',type:type||'emblem',borderW:4,shadow:12,fill:'solid'};
}

const _TBASE=[
  mkTpl('cb01','CBSE Classic','CBSE',mkC('#1e3a8a','#fff','#fbbf24','🎓','circle','Cinzel')),
  mkTpl('cb02','CBSE Navy Gold','CBSE',mkC('#0f2057','#ffd700','#c0392b','📚','circle','Cinzel')),
  mkTpl('cb03','CBSE Red Crest','CBSE',mkC('#9b1c1c','#fff','#fbbf24','🏛️','shield','Cinzel')),
  mkTpl('cb04','CBSE Hex Modern','CBSE',mkC('#1d4ed8','#fff','#f59e0b','🌟','hex','Montserrat')),
  mkTpl('cb05','CBSE Royal Purple','CBSE',mkC('#4c1d95','#fff','#fbbf24','👑','circle','Cinzel')),
  mkTpl('cb06','CBSE Nature Green','CBSE',mkC('#065f46','#fff','#fbbf24','🌿','circle','Montserrat')),
  mkTpl('cb07','CBSE Tricolor','CBSE',mkC('#1e3a8a','#fff','#f97316','🇮🇳','badge','Montserrat')),
  mkTpl('cb08','CBSE Lotus Rose','CBSE',mkC('#be185d','#fff','#fbbf24','🌸','circle','Cinzel')),
  mkTpl('cb09','CBSE Eagle Shield','CBSE',mkC('#1e3a8a','#fbbf24','#fff','🦅','shield','Cinzel')),
  mkTpl('cb10','CBSE Ashoka Star','CBSE',mkC('#0f172a','#fbbf24','#ef4444','⭐','circle','Cinzel')),
  mkTpl('cb11','CBSE Peacock','CBSE',mkC('#065f46','#fbbf24','#fff','🦚','circle','Montserrat')),
  mkTpl('cb12','CBSE Diya','CBSE',mkC('#92400e','#fbbf24','#fff','🪔','circle','Cinzel')),
  mkTpl('cb13','CBSE Star Crest','CBSE',mkC('#1e3a8a','#fff','#fbbf24','🌟','star','Cinzel')),
  mkTpl('cb14','CBSE Om Badge','CBSE',mkC('#7c2d12','#fbbf24','#fff','ॐ','badge','Cinzel')),
  mkTpl('cb15','CBSE Infinity','CBSE',mkC('#312e81','#fff','#a78bfa','∞','circle','Montserrat')),
  mkTpl('cb16','CBSE Premier','CBSE',mkC('#0c1445','#c9a84c','#fff','🎓','circle','Cinzel')),
  mkTpl('cb17','CBSE Science','CBSE',mkC('#0e7490','#fff','#fbbf24','🔬','circle','Montserrat')),
  mkTpl('cb18','CBSE Mountain','CBSE',mkC('#1e3a8a','#fff','#22c55e','🏔️','hex','Montserrat')),
  mkTpl('cb19','CBSE Lion Gate','CBSE',mkC('#7c2d12','#fbbf24','#fff','🦁','shield','Cinzel')),
  mkTpl('cb20','CBSE Diamond','CBSE',mkC('#0c1445','#c9a84c','#e9d5ff','💎','diamond','Cinzel')),
  mkTpl('ic01','ICSE Heritage','ICSE',mkC('#14532d','#fff','#fbbf24','🏛️','circle','Cinzel')),
  mkTpl('ic02','ICSE Maroon','ICSE',mkC('#7f1d1d','#fbbf24','#fff','🎓','circle','Cinzel')),
  mkTpl('ic03','ICSE Oxford','ICSE',mkC('#0f2057','#fff','#c9a84c','📚','shield','Cinzel')),
  mkTpl('ic04','ICSE Cambridge','ICSE',mkC('#0369a1','#fff','#fbbf24','🌟','circle','Cinzel')),
  mkTpl('ic05','ICSE Crown','ICSE',mkC('#3b0764','#fbbf24','#fff','👑','circle','Cinzel')),
  mkTpl('ic06','ICSE Lotus Seal','ICSE',mkC('#831843','#fff','#fbbf24','🌸','badge','Cinzel')),
  mkTpl('ic07','ICSE Lion','ICSE',mkC('#7c2d12','#fbbf24','#fff','🦁','shield','Cinzel')),
  mkTpl('ic08','ICSE Eagle','ICSE',mkC('#1a1a2e','#c9a84c','#fff','🦅','shield','Cinzel')),
  mkTpl('ic09','ICSE Globe','ICSE',mkC('#0f2057','#fbbf24','#93c5fd','🌐','circle','Cinzel')),
  mkTpl('ic10','ICSE Elite','ICSE',mkC('#0c0c1d','#c9a84c','#8b6914','💎','circle','Cinzel')),
  mkTpl('kv01','KV Classic','KV/Govt',mkC('#1e3a8a','#fff','#f97316','🎓','circle','Cinzel')),
  mkTpl('kv02','KV Tricolor','KV/Govt',mkC('#15803d','#fff','#f97316','🇮🇳','circle','Montserrat')),
  mkTpl('kv03','KV Ashoka','KV/Govt',mkC('#1e3a8a','#fbbf24','#fff','⭐','circle','Cinzel')),
  mkTpl('kv04','KV Diya','KV/Govt',mkC('#7c2d12','#fbbf24','#fff','🪔','circle','Cinzel')),
  mkTpl('kv05','KV Peacock','KV/Govt',mkC('#1e3a8a','#fbbf24','#22c55e','🦚','badge','Montserrat')),
  mkTpl('kv06','KV Lotus','KV/Govt',mkC('#15803d','#fbbf24','#fff','🪷','circle','Cinzel')),
  mkTpl('kv07','KV Saraswati','KV/Govt',mkC('#7e22ce','#fbbf24','#fff','📜','circle','Cinzel')),
  mkTpl('kv08','KV Himalaya','KV/Govt',mkC('#1e3a8a','#fff','#22c55e','🏔️','hex','Montserrat')),
  mkTpl('kv09','KV Lion Gate','KV/Govt',mkC('#7c2d12','#fbbf24','#fff','🦁','shield','Cinzel')),
  mkTpl('kv10','KV Navodaya','KV/Govt',mkC('#0f172a','#fbbf24','#16a34a','🌱','circle','Montserrat')),
  mkTpl('ms01','St Xavier Blue','Mission',mkC('#1e3a8a','#fff','#fbbf24','✝️','circle','Cinzel')),
  mkTpl('ms02','St Mary Red','Mission',mkC('#7f1d1d','#fff','#fbbf24','🌸','circle','Cinzel')),
  mkTpl('ms03','Don Bosco','Mission',mkC('#1e3a8a','#fbbf24','#fff','⭐','shield','Cinzel')),
  mkTpl('ms04','Carmel Green','Mission',mkC('#14532d','#fbbf24','#fff','🌿','circle','Cinzel')),
  mkTpl('ms05','Sacred Heart','Mission',mkC('#9b1c1c','#fff','#fbbf24','❤️','circle','Cinzel')),
  mkTpl('ms06','Holy Cross','Mission',mkC('#0f2057','#fff','#c9a84c','✝️','circle','Cinzel')),
  mkTpl('ms07','St Joseph','Mission',mkC('#4c1d95','#fff','#fbbf24','🕊️','shield','Cinzel')),
  mkTpl('ms08','Loreto Blue','Mission',mkC('#1e40af','#fff','#fbbf24','🌺','circle','Cinzel')),
  mkTpl('ms09','Christ School','Mission',mkC('#7c2d12','#fbbf24','#fff','✝️','badge','Cinzel')),
  mkTpl('ms10','Bishop Cotton','Mission',mkC('#0f2057','#c9a84c','#fff','🦅','circle','Cinzel')),
  mkTpl('dv01','DAV Classic','DAV/Arya',mkC('#b91c1c','#fff','#fbbf24','ॐ','circle','Cinzel')),
  mkTpl('dv02','DAV Vedic','DAV/Arya',mkC('#ea580c','#fff','#fbbf24','🪔','circle','Montserrat')),
  mkTpl('dv03','DAV Saffron','DAV/Arya',mkC('#b45309','#fff','#fbbf24','ॐ','circle','Cinzel')),
  mkTpl('dv04','DAV Fire','DAV/Arya',mkC('#7c2d12','#fbbf24','#fff','🔥','circle','Montserrat')),
  mkTpl('dv05','DAV Lotus','DAV/Arya',mkC('#9b1c1c','#fbbf24','#fff','🌸','badge','Cinzel')),
  mkTpl('dv06','DAV Knowledge','DAV/Arya',mkC('#1e3a8a','#fbbf24','#fff','💡','circle','Montserrat')),
  mkTpl('dv07','DAV Sunrise','DAV/Arya',mkC('#f97316','#fff','#fbbf24','🌞','circle','Montserrat')),
  mkTpl('dv08','DAV Peacock','DAV/Arya',mkC('#065f46','#fbbf24','#fff','🦚','circle','Montserrat')),
  mkTpl('dv09','Arya Vidyalaya','DAV/Arya',mkC('#7c2d12','#fff','#fbbf24','ॐ','circle','Cinzel')),
  mkTpl('dv10','DAV Shield','DAV/Arya',mkC('#b45309','#1e3a8a','#fff','ॐ','shield','Cinzel')),
  mkTpl('el01','DPS Royal','Elite',mkC('#1e3a8a','#fbbf24','#fff','👑','circle','Cinzel')),
  mkTpl('el02','Ryan Intl','Elite',mkC('#0f2057','#fbbf24','#c0392b','🌐','circle','Cinzel')),
  mkTpl('el03','Amity Modern','Elite',mkC('#1e3a8a','#fff','#f59e0b','🎓','hex','Montserrat')),
  mkTpl('el04','Podar Diamond','Elite',mkC('#0c1445','#c9a84c','#fff','💎','circle','Cinzel')),
  mkTpl('el05','Modern School','Elite',mkC('#0f2057','#fbbf24','#fff','🌟','circle','Cinzel')),
  mkTpl('el06','Doon School','Elite',mkC('#1c1c1c','#c9a84c','#fff','🏔️','circle','Cinzel')),
  mkTpl('el07','Mayo College','Elite',mkC('#7c2d12','#c9a84c','#fff','🦁','circle','Cinzel')),
  mkTpl('el08','Scindia','Elite',mkC('#0f2057','#c9a84c','#fff','⭐','shield','Cinzel')),
  mkTpl('el09','Lawrence','Elite',mkC('#14532d','#fbbf24','#fff','🦅','circle','Cinzel')),
  mkTpl('el10','Air Force Bal','Elite',mkC('#0f172a','#60a5fa','#fff','✈️','circle','Montserrat')),
  mkTpl('st01','UP Board','State',mkC('#1e3a8a','#fff','#f97316','📚','circle','Montserrat')),
  mkTpl('st02','MP Board','State',mkC('#14532d','#fff','#fbbf24','🌿','circle','Montserrat')),
  mkTpl('st03','Rajasthan Gold','State',mkC('#78350f','#fbbf24','#fff','🏰','circle','Cinzel')),
  mkTpl('st04','Bihar Proud','State',mkC('#9b1c1c','#fff','#fbbf24','🪔','circle','Montserrat')),
  mkTpl('st05','Punjab Heritage','State',mkC('#b45309','#fff','#fbbf24','🌾','circle','Cinzel')),
  mkTpl('st06','Bengal Classic','State',mkC('#0f2057','#fff','#f97316','📖','circle','Cinzel')),
  mkTpl('st07','Gujarat Proud','State',mkC('#1e3a8a','#fff','#fbbf24','🦁','circle','Cinzel')),
  mkTpl('st08','Maharashtra','State',mkC('#7c2d12','#fff','#fbbf24','⭐','shield','Montserrat')),
  mkTpl('st09','Karnataka','State',mkC('#14532d','#fbbf24','#ef4444','🌺','circle','Montserrat')),
  mkTpl('st10','Kerala Blue','State',mkC('#1e3a8a','#fff','#16a34a','🌴','circle','Montserrat')),
  mkTpl('co01','IIT Prep','Coaching',mkC('#1e3a8a','#fff','#f59e0b','🔬','hex','Montserrat')),
  mkTpl('co02','NEET Medical','Coaching',mkC('#14532d','#fff','#fbbf24','🩺','circle','Montserrat')),
  mkTpl('co03','JEE Classic','Coaching',mkC('#0f172a','#60a5fa','#fbbf24','⚛️','hex','Montserrat')),
  mkTpl('co04','Allen Style','Coaching',mkC('#1e3a8a','#fff','#ef4444','🎯','circle','Montserrat')),
  mkTpl('co05','Narayana','Coaching',mkC('#14532d','#fff','#fbbf24','💡','circle','Montserrat')),
  mkTpl('ps01','Rainbow Kids','Playschool',mkC('#f97316','#fff','#22c55e','🌈','circle','Poppins')),
  mkTpl('ps02','Tiny Tots','Playschool',mkC('#a855f7','#fff','#fbbf24','⭐','circle','Poppins')),
  mkTpl('ps03','Little Flower','Playschool',mkC('#be185d','#fff','#fbbf24','🌸','circle','Poppins')),
  mkTpl('ps04','Sunshine Kids','Playschool',mkC('#f59e0b','#fff','#22c55e','🌞','circle','Poppins')),
  mkTpl('ps05','Happy Minds','Playschool',mkC('#0ea5e9','#fff','#fbbf24','🦋','circle','Poppins')),
  mkTpl('gk01','Gurukul Om','Gurukul',mkC('#78350f','#fbbf24','#fff','ॐ','circle','Cinzel')),
  mkTpl('gk02','Vedic School','Gurukul',mkC('#7c2d12','#fbbf24','#fff','🪔','circle','Cinzel')),
  mkTpl('gk03','Sanskrit Patha','Gurukul',mkC('#92400e','#fbbf24','#fff','📜','circle','Cinzel')),
  mkTpl('gk04','Ashram School','Gurukul',mkC('#14532d','#fbbf24','#fff','🌿','circle','Cinzel')),
  mkTpl('gk05','Saraswati','Gurukul',mkC('#4c1d95','#fbbf24','#fff','📚','circle','Cinzel')),
  mkTpl('sp01','Cricket Academy','Sports',mkC('#14532d','#fbbf24','#fff','🏏','circle','Montserrat')),
  mkTpl('sp02','Football Club','Sports',mkC('#1e3a8a','#fff','#f59e0b','⚽','circle','Montserrat')),
  mkTpl('sp03','Athletics','Sports',mkC('#ef4444','#fff','#fbbf24','🏃','circle','Montserrat')),
  mkTpl('sp04','Chess Academy','Sports',mkC('#0f172a','#fbbf24','#fff','♟️','circle','Montserrat')),
  mkTpl('sp05','Yoga Center','Sports',mkC('#065f46','#fbbf24','#fff','🧘','circle','Cinzel')),
  mkTpl('ar01','Fine Arts','Arts',mkC('#7c3aed','#fff','#fbbf24','🎨','circle','Cinzel')),
  mkTpl('ar02','Music Academy','Arts',mkC('#0f172a','#a855f7','#fbbf24','🎵','circle','Montserrat')),
  mkTpl('ar03','Dance School','Arts',mkC('#be185d','#fff','#fbbf24','💃','circle','Cinzel')),
  mkTpl('ar04','Bharatanatyam','Arts',mkC('#9b1c1c','#fbbf24','#fff','🩰','circle','Cinzel')),
  mkTpl('ar05','Film School','Arts',mkC('#0f172a','#f59e0b','#fff','🎬','circle','Montserrat')),
  mkTpl('tc01','IT School','Tech',mkC('#0f172a','#60a5fa','#fbbf24','💻','hex','Montserrat')),
  mkTpl('tc02','Robotics','Tech',mkC('#1e3a8a','#22c55e','#fbbf24','🤖','circle','Montserrat')),
  mkTpl('tc03','AI Academy','Tech',mkC('#0f2057','#a855f7','#fbbf24','🧠','hex','Montserrat')),
  mkTpl('tc04','STEM School','Tech',mkC('#065f46','#fff','#fbbf24','🔬','circle','Montserrat')),
  mkTpl('tc05','Future School','Tech',mkC('#4c1d95','#fff','#22c55e','🔮','hex','Montserrat')),

  /* ═══ 20 READY-TO-USE CIRCULAR SCHOOL BADGE TEMPLATES ═══
     type:'badge' → rendered with concentric rings + arc text.
     User only changes: school name, address, center symbol.   */
  {id:'bx01',label:'Classic Red-Blue',cat:'School Badges',cfg:{type:'badge',outer:'#c0392b',inner:'#1e3a8a',topColor:'#ffffff',botColor:'#fbbf24',symbol:'🎓',font:'Cinzel',topText:'YOUR SCHOOL NAME',botText:'CITY · STATE · INDIA'}},
  {id:'bx02',label:'Maroon-Gold Heritage',cat:'School Badges',cfg:{type:'badge',outer:'#7c1d1d',inner:'#92400e',topColor:'#ffffff',botColor:'#fbbf24',symbol:'📚',font:'Cinzel',topText:'YOUR SCHOOL NAME',botText:'CITY · STATE · INDIA'}},
  {id:'bx03',label:'Saraswati Vidya',cat:'School Badges',cfg:{type:'badge',outer:'#d97706',inner:'#7c2d12',topColor:'#ffffff',botColor:'#fef08a',symbol:'🪷',font:'Cinzel',topText:'SARASWATI VIDYA MANDIR',botText:'CITY · STATE · INDIA'}},
  {id:'bx04',label:'Ambedkar Shiksha',cat:'School Badges',cfg:{type:'badge',outer:'#1e3a8a',inner:'#312e81',topColor:'#ffffff',botColor:'#fbbf24',symbol:'☸️',font:'Montserrat',topText:'DR. AMBEDKAR SCHOOL',botText:'CITY · STATE · INDIA'}},
  {id:'bx05',label:'Navoda Green-Gold',cat:'School Badges',cfg:{type:'badge',outer:'#14532d',inner:'#713f12',topColor:'#ffffff',botColor:'#fbbf24',symbol:'🌿',font:'Cinzel',topText:'NAVODAYA VIDYALAYA',botText:'CITY · STATE · INDIA'}},
  {id:'bx06',label:'Vedic Gurukul Om',cat:'School Badges',cfg:{type:'badge',outer:'#92400e',inner:'#78350f',topColor:'#ffffff',botColor:'#fde68a',symbol:'ॐ',font:'Cinzel',topText:'VEDA GURUKUL SCHOOL',botText:'CITY · STATE · INDIA'}},
  {id:'bx07',label:'Royal Navy Crown',cat:'School Badges',cfg:{type:'badge',outer:'#0f2057',inner:'#7c5a00',topColor:'#ffffff',botColor:'#fbbf24',symbol:'👑',font:'Cinzel',topText:'ROYAL PUBLIC SCHOOL',botText:'CITY · STATE · INDIA'}},
  {id:'bx08',label:'Tricolor National',cat:'School Badges',cfg:{type:'badge',outer:'#1e3a8a',inner:'#15803d',topColor:'#ffffff',botColor:'#fb923c',symbol:'🇮🇳',font:'Montserrat',topText:'NATIONAL PUBLIC SCHOOL',botText:'CITY · STATE · INDIA'}},
  {id:'bx09',label:'Purple Diamond Elite',cat:'School Badges',cfg:{type:'badge',outer:'#4c1d95',inner:'#78350f',topColor:'#ffffff',botColor:'#fbbf24',symbol:'💎',font:'Cinzel',topText:'DIAMOND PUBLIC SCHOOL',botText:'CITY · STATE · INDIA'}},
  {id:'bx10',label:'Science Academy',cat:'School Badges',cfg:{type:'badge',outer:'#0e7490',inner:'#0c4a6e',topColor:'#ffffff',botColor:'#fbbf24',symbol:'⚛️',font:'Montserrat',topText:'SCIENCE ACADEMY',botText:'CITY · STATE · INDIA'}},
  {id:'bx11',label:'Ashoka Wheel Blue',cat:'School Badges',cfg:{type:'badge',outer:'#1e3a8a',inner:'#1e40af',topColor:'#ffffff',botColor:'#f97316',symbol:'☸️',font:'Cinzel',topText:'ASHOKA MODEL SCHOOL',botText:'CITY · STATE · INDIA'}},
  {id:'bx12',label:'Mission School',cat:'School Badges',cfg:{type:'badge',outer:'#1e3a8a',inner:'#374151',topColor:'#ffffff',botColor:'#fbbf24',symbol:'✝️',font:'Cinzel',topText:'ST. MARY\'S SCHOOL',botText:'CITY · STATE · INDIA'}},
  {id:'bx13',label:'Eagle Public School',cat:'School Badges',cfg:{type:'badge',outer:'#0f172a',inner:'#1c1917',topColor:'#c9a84c',botColor:'#fbbf24',symbol:'🦅',font:'Cinzel',topText:'EAGLE PUBLIC SCHOOL',botText:'CITY · STATE · INDIA'}},
  {id:'bx14',label:'Lion Gate School',cat:'School Badges',cfg:{type:'badge',outer:'#7c2d12',inner:'#451a03',topColor:'#fbbf24',botColor:'#fef08a',symbol:'🦁',font:'Cinzel',topText:'LION GATE ACADEMY',botText:'CITY · STATE · INDIA'}},
  {id:'bx15',label:'Sunrise Saffron',cat:'School Badges',cfg:{type:'badge',outer:'#b45309',inner:'#78350f',topColor:'#ffffff',botColor:'#fef08a',symbol:'🌞',font:'Montserrat',topText:'SUNRISE SCHOOL',botText:'CITY · STATE · INDIA'}},
  {id:'bx16',label:'Diya Jyot School',cat:'School Badges',cfg:{type:'badge',outer:'#7c2d12',inner:'#451a03',topColor:'#fbbf24',botColor:'#fde68a',symbol:'🪔',font:'Cinzel',topText:'JYOTI VIDYA MANDIR',botText:'CITY · STATE · INDIA'}},
  {id:'bx17',label:'Peacock Glory Green',cat:'School Badges',cfg:{type:'badge',outer:'#065f46',inner:'#14532d',topColor:'#ffffff',botColor:'#fbbf24',symbol:'🦚',font:'Cinzel',topText:'PEACOCK PUBLIC SCHOOL',botText:'CITY · STATE · INDIA'}},
  {id:'bx18',label:'Lotus Pink Heritage',cat:'School Badges',cfg:{type:'badge',outer:'#831843',inner:'#4a044e',topColor:'#ffffff',botColor:'#fbbf24',symbol:'🌸',font:'Cinzel',topText:'LOTUS VALLEY SCHOOL',botText:'CITY · STATE · INDIA'}},
  {id:'bx19',label:'Oxford English Medium',cat:'School Badges',cfg:{type:'badge',outer:'#0f2057',inner:'#1e1b4b',topColor:'#c9a84c',botColor:'#fbbf24',symbol:'📖',font:'Cinzel',topText:'OXFORD ENGLISH SCHOOL',botText:'CITY · STATE · INDIA'}},
  {id:'bx20',label:'Black-Gold Elite',cat:'School Badges',cfg:{type:'badge',outer:'#0c0c0c',inner:'#1c1917',topColor:'#c9a84c',botColor:'#fbbf24',symbol:'🏛️',font:'Cinzel',topText:'ELITE PUBLIC SCHOOL',botText:'CITY · STATE · INDIA'}},
];

const _CV=[
  {p:'#1e3a8a',s:'#fff',a:'#fbbf24'},{p:'#14532d',s:'#fff',a:'#fbbf24'},
  {p:'#7c2d12',s:'#fbbf24',a:'#fff'},{p:'#4c1d95',s:'#fff',a:'#fbbf24'},
  {p:'#0f172a',s:'#c9a84c',a:'#fff'},{p:'#991b1b',s:'#fff',a:'#fbbf24'},
];
const _FR=['circle','hex','shield','badge','diamond','star'];

const LSP_TEMPLATES=(()=>{
  const out=[];
  _TBASE.forEach((t,bi)=>{
    out.push(t);
    // Badge templates are drawn from outer/inner colours, not primary/secondary,
    // so the generated "v1…v6" copies of them all rendered identically (120
    // duplicate-looking cards). Only emblem templates get colour variants.
    if(t.cfg.type==='badge')return;
    _CV.forEach((cv,vi)=>{
      if(vi===0&&t.cfg.primaryColor===cv.p)return;
      out.push({id:t.id+'v'+(vi+1),label:t.label+' v'+(vi+1),cat:t.cat,
        cfg:{...t.cfg,primaryColor:cv.p,secondary:cv.s,accent:cv.a,frame:_FR[(bi+vi)%_FR.length]}});
    });
  });
  return out;
})();
const LSP_TPL_CATS=[...new Set(LSP_TEMPLATES.map(t=>t.cat))];

const LSP_PALETTES=[
  {name:'CBSE Blue',colors:['#1e3a8a','#1d4ed8','#93c5fd','#fbbf24','#fff']},
  {name:'Heritage Gold',colors:['#0c1445','#1e3a8a','#b45309','#d97706','#fbbf24']},
  {name:'Saffron Flame',colors:['#7c2d12','#b45309','#d97706','#fbbf24','#fff']},
  {name:'Forest Wisdom',colors:['#14532d','#166534','#15803d','#22c55e','#fbbf24']},
  {name:'Royal Purple',colors:['#2e1065','#4c1d95','#7c3aed','#c4b5fd','#fbbf24']},
  {name:'Sacred Red',colors:['#450a0a','#7f1d1d','#991b1b','#fbbf24','#fff']},
  {name:'Vedic Orange',colors:['#431407','#7c2d12','#c2410c','#fb923c','#fbbf24']},
  {name:'Sky Tech',colors:['#0c4a6e','#0369a1','#0ea5e9','#7dd3fc','#fff']},
  {name:'Tricolor Pride',colors:['#1e3a8a','#2563eb','#ffffff','#ea580c','#16a34a']},
  {name:'Maroon Ivory',colors:['#4a0404','#7f1d1d','#9f1239','#c9a84c','#fdf8ee']},
  {name:'Dark Prestige',colors:['#080612','#1a1523','#2d2440','#a855f7','#fbbf24']},
  {name:'Mission Blue',colors:['#1e1b4b','#1e3a8a','#1d4ed8','#fbbf24','#fff']},
];

/* INIT */
document.addEventListener('DOMContentLoaded',()=>{
  lspInitCanvas();lspRenderPanel('design');lspInitKeyboard();lspUpdateUndoRedo();
  lspZoomFit();
  lspShowToast('👋 Logo Studio Pro ready! Choose a template to start.',3000);
  // Web fonts finish loading after first paint — re-measure text so boxes fit.
  if(document.fonts&&document.fonts.ready)document.fonts.ready.then(()=>{try{fabric.util.clearFabricFontCache();}catch(e){}lspRefreshTextMetrics();});
  lspOfferDraftRestore();
  // Track designer open (once per session)
  try{const _cs=document.querySelector('meta[name=csrf]')?.content||'';if(_cs){const _f=new FormData();_f.append('action','record');_f.append('csrf_token',_cs);_f.append('action_type','logo_designer');_f.append('panel','icard');_f.append('count',1);idmFetch('../api/usage_stats.php',{method:'POST',body:_f},10000).catch(()=>{});}}catch(e){}
  // Deliberately no auto-open of the Templates flyout here anymore — it used
  // to pop the "School Badges" template picker over the canvas 700ms after
  // every load, making it look like a template had been forced on the user.
  // The canvas now starts genuinely blank; Templates stays one manual tap
  // away via its own toolbar button, same as Icons/Frames/Shapes.
});

/* ══════════════════════════════════════════════════════════════════════
   CIRCULAR TEXT — Custom Fabric.js class
   Renders text smoothly along a circular arc path.
   Algorithm: for each character, compute its arc-angle from the center,
   then rotate+translate to place it tangentially on the circle.
   ══════════════════════════════════════════════════════════════════════ */
fabric.CircularText = fabric.util.createClass(fabric.Object, {
  type: 'circularText',
  text: 'SCHOOL NAME',
  radius: 120,
  fontSize: 22,
  fontFamily: 'Cinzel',
  fontWeight: 'bold',
  fontStyle: 'normal',
  fill: '#1e3a8a',
  side: 'top',
  rotateOffset: 0,
  letterSpacing: 8,
  /* Fabric re-renders a cached bitmap unless a property listed here changes —
     so editing the text, side, spacing, font… from the panel used to show no
     change on the canvas until something else happened to refresh it. */
  cacheProperties: fabric.Object.prototype.cacheProperties.concat(
    ['text','radius','fontSize','fontFamily','fontWeight','fontStyle','side','rotateOffset','letterSpacing']),

  initialize: function(options) {
    options = options || {};
    this.callSuper('initialize', options);
    this._recalcSize();
  },

  _recalcSize: function() {
    var d = (this.radius + this.fontSize * 1.9) * 2 + 20;
    this.set({ width: d, height: d });
    this.setCoords();
  },

  /* Shared layout used by both canvas rendering and SVG export. */
  _layout: function(ctx) {
    var chars = Array.from(this.text || '');
    var r = Math.max(20, this.radius);
    var ls = this.letterSpacing || 0;
    var offsetRad = (this.rotateOffset || 0) * Math.PI / 180;
    var widths = chars.map(function(ch) { return Math.max(ctx.measureText(ch).width, 4); });
    var totalW = widths.reduce(function(a, b) { return a + b; }, 0) + ls * Math.max(chars.length - 1, 0);
    var totalAngle = totalW / r, pos = 0, out = [];
    var top = this.side === 'top';
    var start = top ? -(totalAngle / 2) + offsetRad : Math.PI + (totalAngle / 2) + offsetRad;
    for (var i = 0; i < chars.length; i++) {
      var a = top ? start + (pos + widths[i] / 2) / r : start - (pos + widths[i] / 2) / r;
      out.push({ ch: chars[i], angle: a, flip: !top });
      pos += widths[i] + ls;
    }
    return { r: r, items: out };
  },

  _fontString: function() {
    var fStyle = (this.fontStyle && this.fontStyle !== 'normal') ? this.fontStyle + ' ' : '';
    return fStyle + (this.fontWeight || 'bold') + ' ' + this.fontSize + 'px "' + this.fontFamily + '", sans-serif';
  },

  /* SVG export used to throw ("this._toSVG is not a function") for any design
     containing arc text, so SVG export of every badge failed. */
  _toSVG: function() {
    var c = document.createElement('canvas').getContext('2d');
    c.font = this._fontString();
    var L = this._layout(c), self = this;
    var esc = function(s){ return String(s).replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); };
    var fill = typeof this.fill === 'string' ? this.fill : '#000000';
    var out = ['<g>\n'];
    L.items.forEach(function(it) {
      var deg = it.angle * 180 / Math.PI;
      out.push('<text text-anchor="middle" font-family="' + esc(self.fontFamily) + ', sans-serif" font-size="' + self.fontSize +
        '" font-weight="' + esc(self.fontWeight || 'bold') + '" font-style="' + esc(self.fontStyle || 'normal') + '" fill="' + esc(fill) +
        '" transform="rotate(' + deg.toFixed(3) + ') translate(0 ' + (-L.r) + ')' + (it.flip ? ' rotate(180)' : '') + '">' + esc(it.ch) + '</text>\n');
    });
    out.push('</g>\n');
    return out;
  },

  _render: function(ctx) {
    if (!this.text || !this.text.length) return;
    ctx.save();
    ctx.font = this._fontString();
    ctx.fillStyle = this.fill || '#1e3a8a';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    /* Same algorithm as before (top arc reads clockwise, bottom arc reads
       left→right from outside), now via _layout() so SVG export matches and
       emoji / Devanagari clusters are not split into broken halves. */
    var L = this._layout(ctx);
    for (var i = 0; i < L.items.length; i++) {
      var it = L.items[i];
      ctx.save();
      ctx.rotate(it.angle);
      ctx.translate(0, -L.r);
      if (it.flip) ctx.rotate(Math.PI);
      ctx.fillText(it.ch, 0, 0);
      ctx.restore();
    }
    ctx.restore();
  },

  toObject: function() {
    return fabric.util.object.extend(this.callSuper('toObject'), {
      text: this.text,
      radius: this.radius,
      fontSize: this.fontSize,
      fontFamily: this.fontFamily,
      fontWeight: this.fontWeight,
      fontStyle: this.fontStyle,
      fill: this.fill,
      side: this.side,
      rotateOffset: this.rotateOffset,
      letterSpacing: this.letterSpacing
    });
  }
});

fabric.CircularText.fromObject = function(object, callback) {
  var inst = new fabric.CircularText(object);
  inst._recalcSize();
  if (callback) callback(inst);
  return inst;
};

function lspInitCanvas(){
  LSP.canvas=new fabric.Canvas('lsp-main-canvas',{
    width:LSP.canvasW,height:LSP.canvasH,backgroundColor:'#ffffff',
    preserveObjectStacking:true,selection:true,
    enableRetinaScaling:true,imageSmoothingEnabled:true,
    stopContextMenu:true,
  });
  const cv=LSP.canvas;
  // Force high-quality image smoothing on all canvas contexts
  ['lowerCanvasEl','upperCanvasEl'].forEach(k=>{const ctx=cv[k]?.getContext('2d');if(ctx)ctx.imageSmoothingQuality='high';});
  cv.on('object:modified',()=>{lspClearGuides();lspSaveHistory();lspRenderPanelSoon();});
  cv.on('object:added',()=>{lspRefreshLayers();lspSaveHistory();});
  cv.on('object:removed',()=>{lspRefreshLayers();lspSaveHistory();});
  // Typing into a text layer never reached the undo history before.
  cv.on('text:editing:exited',()=>{lspSaveHistory();lspRefreshLayers();});
  cv.on('text:changed',()=>{lspMarkDirty();});
  cv.on('selection:created',(e)=>{LSP.selectedObj=e.selected?.[0]||null;lspOnSelect();});
  cv.on('selection:updated',(e)=>{LSP.selectedObj=e.selected?.[0]||null;lspOnSelect();});
  cv.on('selection:cleared',()=>{LSP.selectedObj=null;lspRenderPanel(LSP.activePanel);lspRefreshLayers();});
  cv.on('mouse:wheel',(opt)=>{
    const e=opt.e;
    if(!e.ctrlKey&&!e.metaKey)return;
    e.preventDefault();e.stopPropagation();
    const factor=e.deltaY>0?0.9:1.1;
    lspApplyZoom(Math.max(0.1,Math.min(5,LSP.zoom*factor)));
  });
  cv.on('object:moving',function(e){
    const o=e.target;
    if(LSP.showSnap){
      const g=LSP.gridSize||20;
      o.set({left:Math.round(o.left/g)*g,top:Math.round(o.top/g)*g});
    }
    if(LSP.showGuides) lspApplyAlignGuides(o);
  });
  cv.on('object:scaling',function(e){
    if(!LSP.showSnap)return;
    const o=e.target,g=LSP.gridSize||20;
    const newW=Math.round(o.getScaledWidth()/g)*g;
    const newH=Math.round(o.getScaledHeight()/g)*g;
    if(newW>0)o.scaleX=newW/o.width;
    if(newH>0)o.scaleY=newH/o.height;
  });
  cv.on('mouse:up',()=>{lspClearGuides();});
  // Drawn strokes: tag them so "Clear Strokes" also removes spray/round-brush
  // groups (it only matched plain paths before) and set up the eraser.
  cv.on('path:created',(e)=>{
    const p=e.path;if(!p)return;
    p.lspDrawn=true;
    if(LSP._drawBrush==='eraser'){
      p.lspEraser=true;p.lspName='Eraser stroke';
      p.set({selectable:false,evented:false});
      // A transparent background has no colour to paint over — cut real holes instead.
      if(LSP.isBgTransparent)p.set({globalCompositeOperation:'destination-out',stroke:'rgba(0,0,0,1)'});
    } else p.lspName='Drawing';
    lspRefreshLayers();lspSaveHistory();
  });
  // Double-click arc text / emoji text → quick edit (fabric.Text is not editable in place).
  cv.on('mouse:dblclick',(e)=>{
    const o=e.target;if(!o)return;
    if(o.type==='circularText'){
      lspPromptModal('Edit arc text',[{id:'t',label:'Text',value:o.text}],v=>{
        if(v.t==null)return;o.set('text',v.t);o._recalcSize();lspTouch();lspRenderPanel(LSP.activePanel);lspRefreshLayers();
      });
    } else if(o.type==='text'){
      lspPromptModal('Edit text / symbol',[{id:'t',label:'Text or emoji',value:o.text}],v=>{
        if(!v.t)return;o.set('text',v.t);o.initDimensions&&o.initDimensions();o.setCoords();lspTouch();lspRefreshLayers();
      });
    }
  });
  // Smart guides are painted as an overlay instead of being added as real
  // objects — the old Line objects polluted the undo history, the Layers tray,
  // Select-All and colour palettes on every drag.
  cv.on('after:render',(opt)=>{
    if(!_lspGuideLines.length||opt.ctx!==cv.contextContainer)return;
    const ctx=opt.ctx,v=cv.viewportTransform;
    ctx.save();ctx.transform(v[0],v[1],v[2],v[3],v[4],v[5]);
    ctx.strokeStyle='#a855f7';ctx.lineWidth=1/(LSP.zoom||1);ctx.setLineDash([5/(LSP.zoom||1),4/(LSP.zoom||1)]);
    _lspGuideLines.forEach(g=>{
      ctx.beginPath();
      if(g.t==='v'){ctx.moveTo(g.p,0);ctx.lineTo(g.p,LSP.canvasH);}else{ctx.moveTo(0,g.p);ctx.lineTo(LSP.canvasW,g.p);}
      ctx.stroke();
    });
    ctx.restore();
  });
  lspInitPan();
  lspSetCanvasSize(500,500,'Square',document.querySelector('.lsp-size-pill.active'),true);
  lspSaveHistory();lspUpdateGridOverlay();lspUpdateZoomDisplay();
  setTimeout(lspMarkClean,200);
}

/* CANVAS CONTROLS */
function lspSetCanvasSize(w,h,label,btn,quiet){
  w=Math.round(+w);h=Math.round(+h);
  if(!(w>=50&&h>=50&&w<=5000&&h<=5000)){lspShowToast('Size must be between 50 and 5000 px');return;}
  LSP.canvasW=w;LSP.canvasH=h;
  LSP.canvas.setBackgroundColor(LSP.isBgTransparent?'':LSP.bgColor,()=>{});
  document.querySelectorAll('.lsp-size-pill').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
  lspApplyZoom(LSP.zoom||1);
  lspZoomFit(); // always refit — a 1000px canvas used to overflow the desktop view
  if(!quiet){lspShowToast('Canvas: '+w+'×'+h);lspSaveHistory();}
}
function lspCustomSize(){
  lspPromptModal('Custom Canvas Size (50 – 5000 px)',[
    {id:'w',label:'Width (px)',value:LSP.canvasW,type:'number'},
    {id:'h',label:'Height (px)',value:LSP.canvasH,type:'number'},
  ],vals=>{
    const w=parseInt(vals.w,10)||0, h=parseInt(vals.h,10)||0;
    lspSetCanvasSize(w,h,'Custom',null);
  });
}
function lspSetBg(color){
  LSP.bgColor=color;LSP.isBgTransparent=(color==='transparent'||color==='');
  const wrap=document.getElementById('lsp-canvas-wrap');
  if(LSP.isBgTransparent){
    LSP.canvas.setBackgroundColor('',LSP.canvas.renderAll.bind(LSP.canvas));
    wrap.style.background='repeating-conic-gradient(#aaa 0% 25%,#fff 0% 50%) 0 0/12px 12px';
  } else {
    LSP.canvas.setBackgroundColor(color,LSP.canvas.renderAll.bind(LSP.canvas));
    wrap.style.background='';
  }
  // Eraser strokes painted in the old background colour would otherwise show
  // up as coloured scribbles after the background is changed.
  LSP.canvas.getObjects().forEach(o=>{
    if(!o.lspEraser)return;
    if(LSP.isBgTransparent)o.set({globalCompositeOperation:'destination-out',stroke:'rgba(0,0,0,1)'});
    else o.set({globalCompositeOperation:'source-over',stroke:color});
  });
  if(LSP.canvas.freeDrawingBrush&&LSP._drawBrush==='eraser')LSP.canvas.freeDrawingBrush.color=LSP.isBgTransparent?'rgba(0,0,0,1)':color;
  lspTouch();
}
function lspZoomIn(){lspApplyZoom(Math.min(LSP.zoom*1.2,5));}
function lspZoomOut(){lspApplyZoom(Math.max(LSP.zoom/1.2,0.1));}
function lspZoomFit(){
  const area=document.getElementById('lsp-canvas-area');
  if(!area||!LSP.canvas)return;
  const mob=window.innerWidth<=900;
  const aw=area.clientWidth-(mob?24:64),ah=area.clientHeight-(mob?70:96);
  if(aw<=0||ah<=0)return;
  const z=Math.max(0.1,Math.min(aw/LSP.canvasW,ah/LSP.canvasH,2));
  lspApplyZoom(Math.round(z*100)/100);
}
let _lspResizeTid=null;
window.addEventListener('resize',()=>{
  clearTimeout(_lspResizeTid);
  _lspResizeTid=setTimeout(()=>{ if(window.innerWidth<=900 && LSP.canvas) lspZoomFit(); },150);
});

/* ── Mobile Properties sheet open/close ── */
function lspOpenPanel(){
  document.getElementById('lsp-right')?.classList.add('mob-open');
  document.getElementById('lspSheetBackdrop')?.classList.add('show');
}
function lspClosePanel(){
  document.getElementById('lsp-right')?.classList.remove('mob-open');
  document.getElementById('lspSheetBackdrop')?.classList.remove('show');
}
/* Selecting an object jumps straight to its Properties sheet on mobile */
function lspMobAutoOpenPanel(){
  if(window.innerWidth<=900) lspOpenPanel();
}
function lspApplyZoom(z){
  LSP.zoom=z;
  const newW=Math.round(LSP.canvasW*z);
  const newH=Math.round(LSP.canvasH*z);
  LSP.canvas.setZoom(z);
  LSP.canvas.setDimensions({width:newW,height:newH});
  const wrap=document.getElementById('lsp-canvas-wrap');
  wrap.style.width=newW+'px';wrap.style.height=newH+'px';
  wrap.style.transform='';
  lspUpdateZoomDisplay();
  lspUpdateGridOverlay();
}
function lspUpdateZoomDisplay(){document.getElementById('lsp-zoom-display').textContent=Math.round(LSP.zoom*100)+'%';}
function lspToggleGrid(){LSP.showGrid=!LSP.showGrid;document.getElementById('btnGrid').classList.toggle('active',LSP.showGrid);lspUpdateGridOverlay();}
function lspToggleSnap(){LSP.showSnap=!LSP.showSnap;document.getElementById('btnSnap').classList.toggle('active',LSP.showSnap);lspShowToast(LSP.showSnap?'Snap to grid ON':'Snap to grid OFF');}
function lspToggleGuides(){
  LSP.showGuides=!LSP.showGuides;
  document.getElementById('btnGuides').classList.toggle('active',LSP.showGuides);
  if(!LSP.showGuides) lspClearGuides();
  lspShowToast(LSP.showGuides?'Smart guides ON — snaps to canvas & other objects':'Smart guides OFF');
}
/* ── Smart guides: snap to canvas centre/edges AND to other objects' centres/edges ── */
let _lspGuideLines=[];
function lspClearGuides(){
  if(!_lspGuideLines.length)return;
  _lspGuideLines=[];
  LSP.canvas&&LSP.canvas.requestRenderAll();
}
function lspShowGuideLine(type,pos){_lspGuideLines.push({t:type,p:pos});}
function lspApplyAlignGuides(obj){
  _lspGuideLines=[];
  const snapT=6/(LSP.zoom||1);
  const br=obj.getBoundingRect(true,true);
  const xs=[{v:br.left,k:0},{v:br.left+br.width/2,k:.5},{v:br.left+br.width,k:1}];
  const ys=[{v:br.top,k:0},{v:br.top+br.height/2,k:.5},{v:br.top+br.height,k:1}];
  const tx=[0,LSP.canvasW/2,LSP.canvasW],ty=[0,LSP.canvasH/2,LSP.canvasH];
  const members=obj.type==='activeSelection'?obj.getObjects():[obj];
  LSP.canvas.getObjects().forEach(o=>{
    if(members.includes(o)||!o.visible)return;
    const b=o.getBoundingRect(true,true);
    tx.push(b.left,b.left+b.width/2,b.left+b.width);ty.push(b.top,b.top+b.height/2,b.top+b.height);
  });
  let best=null;
  xs.forEach(s=>tx.forEach(t=>{const d=t-s.v;if(Math.abs(d)<snapT&&(!best||Math.abs(d)<Math.abs(best.d)))best={d,t};}));
  if(best){obj.left+=best.d;lspShowGuideLine('v',best.t);}
  best=null;
  ys.forEach(s=>ty.forEach(t=>{const d=t-s.v;if(Math.abs(d)<snapT&&(!best||Math.abs(d)<Math.abs(best.d)))best={d,t};}));
  if(best){obj.top+=best.d;lspShowGuideLine('h',best.t);}
  obj.setCoords();
}
function lspUpdateGridOverlay(){
  const gv=document.getElementById('lsp-grid-overlay');
  const z=LSP.zoom||1;
  const dW=Math.round(LSP.canvasW*z),dH=Math.round(LSP.canvasH*z);
  gv.width=dW;gv.height=dH;
  gv.style.display=LSP.showGrid?'block':'none';
  if(!LSP.showGrid)return;
  const gs=Math.max(4,LSP.gridSize*z);
  const ctx=gv.getContext('2d');ctx.clearRect(0,0,dW,dH);
  ctx.strokeStyle='rgba(168,85,247,0.55)';ctx.lineWidth=0.7;
  for(let x=0;x<dW;x+=gs){ctx.beginPath();ctx.moveTo(Math.round(x)+.5,0);ctx.lineTo(Math.round(x)+.5,dH);ctx.stroke();}
  for(let y=0;y<dH;y+=gs){ctx.beginPath();ctx.moveTo(0,Math.round(y)+.5);ctx.lineTo(dW,Math.round(y)+.5);ctx.stroke();}
}

/* HISTORY */
let _histTimer=null;
function lspSnapshot(){
  const j=LSP.canvas.toJSON(LSP_JSON_PROPS);
  j._lsp={w:LSP.canvasW,h:LSP.canvasH,bg:LSP.isBgTransparent?'transparent':LSP.bgColor};
  return JSON.stringify(j);
}
function lspSaveHistory(){if(LSP._restoring)return;clearTimeout(_histTimer);_histTimer=setTimeout(_commitHistory,150);}
function _commitHistory(){
  if(LSP._restoring||!LSP.canvas)return;
  const j=lspSnapshot();
  if(LSP.histIdx>=0&&LSP.history[LSP.histIdx]===j)return; // no change
  if(LSP.histIdx<LSP.history.length-1)LSP.history=LSP.history.slice(0,LSP.histIdx+1);
  LSP.history.push(j);
  if(LSP.history.length>LSP.maxHistory)LSP.history.shift();
  LSP.histIdx=LSP.history.length-1;
  lspUpdateUndoRedo();
  if(LSP.history.length>1)lspMarkDirty();
  lspSaveDraftSoon();
}
/* Restore a snapshot: canvas size + background come back too (undoing a size
   change used to leave the objects on the wrong-sized canvas). */
function lspRestore(json,cb){
  LSP._restoring=true;clearTimeout(_histTimer);
  let meta=null;try{meta=JSON.parse(json)._lsp||null;}catch(e){}
  if(meta&&(meta.w!==LSP.canvasW||meta.h!==LSP.canvasH)){LSP.canvasW=meta.w;LSP.canvasH=meta.h;lspZoomFit();}
  LSP.canvas.loadFromJSON(json,()=>{
    LSP.canvas.getObjects().forEach(o=>{if(o.type==='circularText'&&o._recalcSize)o._recalcSize();});
    if(meta){
      LSP.isBgTransparent=meta.bg==='transparent';LSP.bgColor=LSP.isBgTransparent?'transparent':meta.bg;
      document.getElementById('lsp-canvas-wrap').style.background=LSP.isBgTransparent?'repeating-conic-gradient(#aaa 0% 25%,#fff 0% 50%) 0 0/12px 12px':'';
    }
    LSP.canvas.renderAll();
    LSP._restoring=false;
    lspRefreshLayers();lspRenderPanel(LSP.activePanel);
    if(cb)cb();
  });
}
function _lspReviveCanvas(cb){
  return function(){
    LSP.canvas.getObjects().forEach(o=>{if(o.type==='circularText'&&o._recalcSize)o._recalcSize();});
    const bg=LSP.canvas.backgroundColor;
    LSP.isBgTransparent=!bg||bg==='transparent';LSP.bgColor=LSP.isBgTransparent?'transparent':(typeof bg==='string'?bg:'#ffffff');
    document.getElementById('lsp-canvas-wrap').style.background=LSP.isBgTransparent?'repeating-conic-gradient(#aaa 0% 25%,#fff 0% 50%) 0 0/12px 12px':'';
    LSP.canvas.renderAll();lspRefreshLayers();
    if(cb)cb();
  };
}
function lspUndo(){if(LSP.histIdx<=0||LSP._restoring)return;LSP.histIdx--;lspRestore(LSP.history[LSP.histIdx]);lspUpdateUndoRedo();lspMarkDirty();}
function lspRedo(){if(LSP.histIdx>=LSP.history.length-1||LSP._restoring)return;LSP.histIdx++;lspRestore(LSP.history[LSP.histIdx]);lspUpdateUndoRedo();lspMarkDirty();}
function lspUpdateUndoRedo(){document.getElementById('btnUndo').disabled=LSP.histIdx<=0;document.getElementById('btnRedo').disabled=LSP.histIdx>=LSP.history.length-1;}
function lspNew(){
  lspConfirm('Clear canvas and start fresh?',()=>{
    LSP.bgColor='#ffffff';LSP.isBgTransparent=false;
    LSP._restoring=true;
    LSP.canvas.clear();
    LSP.canvas.setBackgroundColor('#ffffff',LSP.canvas.renderAll.bind(LSP.canvas));
    LSP._restoring=false;
    document.getElementById('lsp-canvas-wrap').style.background='';
    LSP.history=[];LSP.histIdx=-1;LSP.currentGalleryId=0;LSP._galleryName='My Logo Design';
    _commitHistory();lspRefreshLayers();lspRenderPanel(LSP.activePanel);
    lspClearDraft();lspMarkClean();
    lspShowToast('New canvas ready');
  });
}

/* ── Crash-safe draft: every change is mirrored to this browser so a closed
   tab / dead battery doesn't lose the design. Offered back on next open. ── */
const LSP_DRAFT_KEY='lsp_draft_v1';
let _lspDraftTid=null;
function lspSaveDraftSoon(){
  clearTimeout(_lspDraftTid);
  _lspDraftTid=setTimeout(()=>{
    if(!LSP.dirty||!LSP.canvas.getObjects().length)return;
    try{localStorage.setItem(LSP_DRAFT_KEY,JSON.stringify({t:Date.now(),id:LSP.currentGalleryId,name:LSP._galleryName,json:LSP.history[LSP.histIdx]}));}catch(e){/* quota (large photos) — skip */}
  },1500);
}
function lspClearDraft(){try{localStorage.removeItem(LSP_DRAFT_KEY);}catch(e){}}
function lspOfferDraftRestore(){
  let d=null;try{d=JSON.parse(localStorage.getItem(LSP_DRAFT_KEY)||'null');}catch(e){}
  if(!d||!d.json)return;
  if(Date.now()-d.t>7*864e5){lspClearDraft();return;}
  const mins=Math.max(1,Math.round((Date.now()-d.t)/60000));
  const ago=mins<60?mins+' min':Math.round(mins/60)+' hr';
  setTimeout(()=>lspConfirm('An unsaved logo from '+ago+' ago was found. Restore it?',()=>{
    LSP.currentGalleryId=d.id||0;LSP._galleryName=d.name||'My Logo Design';
    lspRestore(d.json,()=>{lspSaveHistory();lspMarkDirty();lspShowToast('Draft restored ✅');});
  },()=>lspClearDraft()),600);
}

/* TOOLS */
function lspSetTool(tool,btn){
  if(LSP.activeTool==='draw'&&tool!=='draw'){
    LSP.canvas.isDrawingMode=false;
    document.getElementById('fly-draw')?.classList.remove('open');
  }
  LSP.activeTool=tool;
  document.querySelectorAll('#lsp-left .lsp-tool-btn').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
  const cv=LSP.canvas;
  cv.isDrawingMode=(tool==='draw');
  const pan=(tool==='move');
  cv.selection=!pan&&tool!=='draw';
  cv.skipTargetFind=pan;
  if(pan)cv.discardActiveObject();
  cv.defaultCursor=tool==='draw'?'crosshair':pan?'grab':'default';
  cv.hoverCursor=pan?'grab':'move';
  cv.requestRenderAll();
}
/* Pan tool: the old "Move" tool only changed the cursor. It now drags the view
   (scrolls a zoomed-in canvas) — also available any time with Space+drag. */
function lspInitPan(){
  const area=document.getElementById('lsp-canvas-area');
  let st=null,space=false;
  document.addEventListener('keydown',e=>{if(e.code==='Space'&&!e.repeat&&!/INPUT|TEXTAREA|SELECT|BUTTON/.test(e.target.tagName)&&!LSP.canvas.getActiveObject()?.isEditing&&!lspModalOpen()){space=true;LSP.canvas.defaultCursor='grab';LSP.canvas.skipTargetFind=true;e.preventDefault();}});
  document.addEventListener('keyup',e=>{if(e.code==='Space'&&space){space=false;if(LSP.activeTool!=='move'){LSP.canvas.defaultCursor=LSP.activeTool==='draw'?'crosshair':'default';LSP.canvas.skipTargetFind=false;}}});
  LSP.canvas.on('mouse:down',o=>{
    if(LSP.activeTool!=='move'&&!space)return;
    const ev=o.e.touches?o.e.touches[0]:o.e;
    st={x:ev.clientX,y:ev.clientY,sl:area.scrollLeft,stp:area.scrollTop};
    area.classList.add('lsp-panning');
    if(space){LSP.canvas.selection=false;LSP.canvas.discardActiveObject();}
  });
  LSP.canvas.on('mouse:move',o=>{
    if(!st)return;
    const ev=o.e.touches?o.e.touches[0]:o.e;
    area.scrollLeft=st.sl-(ev.clientX-st.x);area.scrollTop=st.stp-(ev.clientY-st.y);
  });
  LSP.canvas.on('mouse:up',()=>{
    if(!st)return;st=null;area.classList.remove('lsp-panning');
    if(LSP.activeTool==='select')LSP.canvas.selection=true;
  });
}

/* ─── DRAW TOOL STATE ─── */
LSP._drawBrush   = 'pencil';
LSP._drawColor   = '#000000';
LSP._drawSize    = 4;
LSP._drawOpacity = 100;

function lspStartDraw(){
  // Draw button now toggles: pressing it again leaves draw mode.
  if(LSP.activeTool==='draw'){lspExitDraw();return;}
  lspClosePanel();
  lspCloseAllFlyouts();
  lspSetTool('draw', document.getElementById('tool-draw'));
  document.getElementById('fly-draw').classList.add('open');
  lspSetBrush(LSP._drawBrush);
}

function lspSetBrush(type){
  LSP._drawBrush = type;
  // highlight active button
  document.querySelectorAll('#fly-draw .lsp-micro-btn[id^="dbtn-"]').forEach(b=>{b.style.borderColor='var(--border-hi)';b.classList.remove('active');});
  const btn = document.getElementById('dbtn-'+type);
  if(btn){btn.style.borderColor='#fbbf24';btn.classList.add('active');}

  const cv = LSP.canvas;
  cv.isDrawingMode = true;
  const sz = LSP._drawSize || 4;

  if(type==='eraser'){
    cv.freeDrawingBrush = new fabric.PencilBrush(cv);
    // On a transparent background the stroke is turned into a real "cut-out"
    // (destination-out) when it's finished — see the path:created handler.
    cv.freeDrawingBrush.color   = LSP.isBgTransparent ? 'rgba(160,160,160,0.6)' : (LSP.bgColor || '#ffffff');
    cv.freeDrawingBrush.width   = sz * 4;
    cv.defaultCursor = 'cell';
  } else if(type==='spray'){
    cv.freeDrawingBrush = new fabric.SprayBrush(cv);
    cv.freeDrawingBrush.density  = 25;
    cv.freeDrawingBrush.dotWidth = Math.max(1, sz * 0.6);
    cv.freeDrawingBrush.dotWidthVariance = 1;
    cv.freeDrawingBrush.width = sz * 4;
    cv.freeDrawingBrush.color = lspDrawRGBA();
    cv.defaultCursor = 'crosshair';
  } else if(type==='circle'){
    cv.freeDrawingBrush = new fabric.CircleBrush(cv);
    cv.freeDrawingBrush.width = sz;
    cv.freeDrawingBrush.color = lspDrawRGBA();
    cv.defaultCursor = 'crosshair';
  } else if(type==='marker'){
    cv.freeDrawingBrush = new fabric.PencilBrush(cv);
    cv.freeDrawingBrush.width = sz * 3.5;
    cv.freeDrawingBrush.color = lspDrawRGBA(0.7);
    cv.defaultCursor = 'crosshair';
  } else if(type==='ink'){
    cv.freeDrawingBrush = new fabric.PencilBrush(cv);
    cv.freeDrawingBrush.width   = Math.max(1, sz * 0.7);
    cv.freeDrawingBrush.decimate = 0;
    cv.freeDrawingBrush.color = lspDrawRGBA();
    cv.defaultCursor = 'crosshair';
  } else {
    // pencil (default)
    cv.freeDrawingBrush = new fabric.PencilBrush(cv);
    cv.freeDrawingBrush.width = sz;
    cv.freeDrawingBrush.color = lspDrawRGBA();
    cv.defaultCursor = 'crosshair';
  }
}

function lspDrawRGBA(forceOpacity){
  const col = lspToHexColor(LSP._drawColor || '#000000');
  // Marker's fixed 0.7 is now scaled by the opacity slider instead of ignoring it.
  const base = (LSP._drawOpacity||100)/100;
  const op  = forceOpacity !== undefined ? forceOpacity*base : base;
  const r=parseInt(col.slice(1,3),16), g=parseInt(col.slice(3,5),16), b=parseInt(col.slice(5,7),16);
  return `rgba(${r},${g},${b},${op.toFixed(2)})`;
}

function lspSetDrawColor(c){
  LSP._drawColor = c;
  const el = document.getElementById('draw-color');
  if(el) el.value = lspToHexColor(c);
  if(LSP.canvas.freeDrawingBrush && LSP._drawBrush !== 'eraser')
    LSP.canvas.freeDrawingBrush.color = lspDrawRGBA(LSP._drawBrush==='marker'?0.7:undefined);
}

function lspSetDrawSize(v){
  LSP._drawSize = v;
  const lbl = document.getElementById('draw-size-lbl');
  if(lbl) lbl.textContent = v+'px';
  const b=LSP.canvas.freeDrawingBrush;
  if(b){
    if(LSP._drawBrush==='eraser')       b.width = v * 4;
    else if(LSP._drawBrush==='marker')  b.width = v * 3.5;
    else if(LSP._drawBrush==='ink')     b.width = Math.max(1, v * 0.7);
    else if(LSP._drawBrush==='spray'){  b.width = v * 4; b.dotWidth = Math.max(1, v * 0.6); }
    else                                b.width = v;
  }
}

function lspSetDrawOpacity(v){
  LSP._drawOpacity = v;
  const lbl = document.getElementById('draw-opacity-lbl');
  if(lbl) lbl.textContent = v+'%';
  if(LSP.canvas.freeDrawingBrush && LSP._drawBrush !== 'eraser')
    LSP.canvas.freeDrawingBrush.color = lspDrawRGBA(LSP._drawBrush==='marker'?0.7:undefined);
}

function lspExitDraw(){
  lspSetTool('select', document.getElementById('tool-select'));
  document.getElementById('fly-draw').classList.remove('open');
}

function lspClearAllStrokes(){
  const strokes = LSP.canvas.getObjects().filter(o=>o.lspDrawn||(o.type==='path'&&!o.lspName));
  if(!strokes.length){ lspShowToast('No strokes to clear.'); return; }
  lspConfirm('Clear all '+strokes.length+' drawn stroke(s)?',()=>{
    LSP._restoring=true; strokes.forEach(p => LSP.canvas.remove(p)); LSP._restoring=false;
    LSP.canvas.renderAll(); lspSaveHistory(); lspRefreshLayers();
    lspShowToast('All strokes cleared.');
  });
}
function lspAddText(){
  if(LSP.activeTool!=='select')lspSetTool('select',document.getElementById('tool-select'));
  const t=new fabric.IText('School Name',{left:LSP.canvasW/2,top:LSP.canvasH/2,originX:'center',originY:'center',fontFamily:LSP.currentFont,fontSize:LSP.currentFontSize,fill:LSP.currentTextColor,fontWeight:'bold',textAlign:'center'});
  LSP.canvas.add(t).setActiveObject(t);
  // Select the placeholder so the first keystroke replaces it.
  t.enterEditing();t.selectAll();
  lspEnsureFont(t.fontFamily,'bold').then(()=>lspRefreshTextMetrics([t]));
}
function lspUploadImage(){document.getElementById('lsp-file-input').click();}
/* Uploads are validated and very large photos are scaled to ≤4096px — a 12MP
   phone photo used to bloat the saved logo by many MB and break image filters. */
function lspHandleImageUpload(e){
  const file=e.target.files[0];e.target.value='';
  if(!file)return;
  if(!/^image\//.test(file.type)){lspShowToast('Please choose an image file (PNG, JPG, WebP, SVG)');return;}
  if(file.size>25*1024*1024){lspShowToast('Image is too large (max 25 MB)');return;}
  const reader=new FileReader();
  reader.onerror=()=>lspShowToast('Could not read that file');
  reader.onload=(ev)=>{
    const src=ev.target.result;
    const el=new Image();
    el.onerror=()=>lspShowToast('This image could not be opened');
    el.onload=()=>{
      let url=src;const MAX=4096;
      if(file.type!=='image/svg+xml'&&(el.naturalWidth>MAX||el.naturalHeight>MAX)){
        const k=MAX/Math.max(el.naturalWidth,el.naturalHeight);
        const c=document.createElement('canvas');c.width=Math.round(el.naturalWidth*k);c.height=Math.round(el.naturalHeight*k);
        const cx=c.getContext('2d');cx.imageSmoothingQuality='high';cx.drawImage(el,0,0,c.width,c.height);
        url=c.toDataURL(file.type==='image/jpeg'?'image/jpeg':'image/png',.92);
      }
      fabric.Image.fromURL(url,(img)=>{
        if(!img||!img.width){lspShowToast('This image could not be opened');return;}
        const sc=Math.min((LSP.canvasW*.5)/img.width,(LSP.canvasH*.5)/img.height,1);
        img.scale(sc).set({left:LSP.canvasW/2,top:LSP.canvasH/2,originX:'center',originY:'center'});
        img.lspName=(file.name||'Image').replace(/\.[^.]+$/,'').slice(0,24);
        LSP.canvas.add(img).setActiveObject(img);LSP.canvas.requestRenderAll();
        lspShowToast('Image added');
      });
    };
    el.src=src;
  };
  reader.readAsDataURL(file);
}

/* LAYERS */
function lspAddLayer(type){if(type==='text')lspAddText();else lspOpenShapes();}
/* Run fn() as ONE undo step (adding/removing several objects fires many events). */
function lspBatch(fn){
  LSP._restoring=true;
  try{fn();}finally{LSP._restoring=false;}
  lspRefreshLayers();lspTouch();lspRenderPanel(LSP.activePanel);
}
/* Clone that keeps names/locks — and works for multi-selections too
   (duplicating several objects used to add a broken, empty "selection" object). */
function lspCloneActive(cb){
  const obj=LSP.canvas.getActiveObject();if(!obj)return;
  obj.clone(cl=>cb(cl),LSP_JSON_PROPS);
}
function lspPlaceClone(clone,dx,dy){
  const cv=LSP.canvas;
  cv.discardActiveObject();
  clone.set({left:clone.left+dx,top:clone.top+dy,evented:true});
  if(clone.type==='activeSelection'){
    clone.canvas=cv;
    LSP._restoring=true;
    clone.forEachObject(o=>cv.add(o));
    LSP._restoring=false;
    clone.setCoords();
  } else {
    LSP._restoring=true;cv.add(clone);LSP._restoring=false;
  }
  cv.setActiveObject(clone);
  lspRefreshLayers();lspTouch();
}
function lspDuplicateLayer(){
  if(!LSP.canvas.getActiveObject()){lspShowToast('Select something to duplicate');return;}
  lspCloneActive(cl=>lspPlaceClone(cl,20,20));
}
function lspCopy(){
  if(!LSP.canvas.getActiveObject())return;
  lspCloneActive(cl=>{LSP.clipboard=cl;LSP._pasteCount=0;lspShowToast('Copied');});
}
function lspCut(){
  if(!LSP.canvas.getActiveObject())return;
  lspCloneActive(cl=>{LSP.clipboard=cl;LSP._pasteCount=0;lspDeleteLayer(true);lspShowToast('Cut');});
}
function lspPaste(){
  if(!LSP.clipboard){lspShowToast('Nothing copied yet');return;}
  LSP._pasteCount++;
  LSP.clipboard.clone(cl=>lspPlaceClone(cl,20*LSP._pasteCount,20*LSP._pasteCount),LSP_JSON_PROPS);
}
/* Delete now removes every selected object (multi-select delete did nothing). */
function lspDeleteLayer(silent){
  const cv=LSP.canvas,objs=cv.getActiveObjects();
  if(!objs.length)return;
  if(cv.getActiveObject()?.isEditing)return;
  lspBatch(()=>{cv.discardActiveObject();objs.forEach(o=>cv.remove(o));});
  if(!silent)lspShowToast(objs.length>1?objs.length+' layers deleted':'Layer deleted');
}
function lspLayerUp(){const o=LSP.canvas.getActiveObject();if(!o)return;LSP.canvas.bringForward(o);lspRefreshLayers();lspTouch();}
function lspLayerDown(){const o=LSP.canvas.getActiveObject();if(!o)return;LSP.canvas.sendBackwards(o);lspRefreshLayers();lspTouch();}
function lspLayerFront(){const o=LSP.canvas.getActiveObject();if(!o)return;LSP.canvas.bringToFront(o);lspRefreshLayers();lspTouch();}
function lspLayerBack(){const o=LSP.canvas.getActiveObject();if(!o)return;LSP.canvas.sendToBack(o);lspRefreshLayers();lspTouch();}
/* Group/ungroup via Fabric's own toGroup()/toActiveSelection() — keeps every
   object exactly where it was (manual re-grouping shifted rotated items). */
function lspGroupLayers(){
  const a=LSP.canvas.getActiveObject();
  if(!a||a.type!=='activeSelection'){lspShowToast('Select 2+ objects to group (Shift+click or drag a box)');return;}
  const n=a.size();
  lspBatch(()=>{const g=a.toGroup();g.lspName='Group ('+n+')';});
  lspShowToast('Grouped '+n+' layers');
}
function lspUngroupLayers(){
  const a=LSP.canvas.getActiveObject();
  if(!a||a.type!=='group'){lspShowToast('Select a group to ungroup');return;}
  lspBatch(()=>{a.toActiveSelection();});
  lspShowToast('Ungrouped — every part is editable now');
}
function lspSelectAll(){
  const objs=LSP.canvas.getObjects().filter(o=>o.visible!==false&&o.selectable!==false);
  if(!objs.length)return;
  LSP.canvas.discardActiveObject();
  const sel=new fabric.ActiveSelection(objs,{canvas:LSP.canvas});
  LSP.canvas.setActiveObject(sel);LSP.canvas.requestRenderAll();
}
function lspToggleVis(idx,e){e&&e.stopPropagation();const obj=LSP.canvas.getObjects()[idx];if(!obj)return;obj.visible=!obj.visible;if(!obj.visible&&LSP.canvas.getActiveObject()===obj)LSP.canvas.discardActiveObject();lspRefreshLayers();lspTouch();}
function lspSetLocked(obj,lock){
  obj.set({lockMovementX:lock,lockMovementY:lock,lockRotation:lock,lockScalingX:lock,lockScalingY:lock,hasControls:!lock});
}
function lspToggleLock(idx,e){e&&e.stopPropagation();const obj=LSP.canvas.getObjects()[idx];if(!obj)return;lspSetLocked(obj,!obj.lockMovementX);lspRefreshLayers();lspRenderPanel(LSP.activePanel);lspTouch();}
function lspToggleLockActive(){
  const objs=lspTargets();if(!objs.length){lspShowToast('Select a layer to lock');return;}
  const lock=!objs[0].lockMovementX;objs.forEach(o=>lspSetLocked(o,lock));
  lspRefreshLayers();lspTouch();lspShowToast(lock?'Locked 🔒':'Unlocked 🔓');
}
function lspOnSelect(){
  const obj=LSP.selectedObj;
  /* Auto-switch to Design panel when circular text is selected */
  if(obj && obj.type==='circularText' && LSP.activePanel!=='design'){
    LSP.activePanel='design';
    document.querySelectorAll('.lsp-ptab').forEach((b,i)=>b.classList.toggle('active',i===0));
  }
  /* …and to the Text panel for a single text layer, where its controls live */
  if(obj && lspIsText(obj) && LSP.canvas.getActiveObjects().length===1 && LSP.activePanel==='layer'){
    LSP.activePanel='text';
    document.querySelectorAll('.lsp-ptab').forEach((b,i)=>b.classList.toggle('active',i===1));
  }
  lspRenderPanel(LSP.activePanel);
  lspRefreshLayers();
  // Deliberately no lspMobAutoOpenPanel() here — this used to jump straight
  // to the Properties sheet on every select, covering the canvas every time
  // a layer was merely tapped. Same fix as iCard Editor/Generate ID Cards:
  // selecting a layer only refreshes the panel's data; opening the sheet is
  // a manual "Properties" tap (lspOpenPanel()) from now on.
}
function lspLayerLabel(obj){
  if(obj.lspName)return obj.lspName;
  if(obj.type==='circularText')return 'Arc: '+(obj.text||'').slice(0,12);
  if(lspIsText(obj))return (obj.text||'Text').slice(0,14);
  return obj.type.charAt(0).toUpperCase()+obj.type.slice(1,10);
}
function lspLayerIcon(obj){
  return lspIsText(obj)?'bi-type':obj.type==='circularText'?'bi-circle':obj.type==='image'?'bi-image':obj.type==='group'?'bi-collection':obj.lspDrawn?'bi-pen':'bi-pentagon';
}
/* Batched to one refresh per frame — building a template fired this for every
   object, re-rendering the whole tray (with thumbnails) dozens of times. */
let _lspLayersRaf=0;
function lspRefreshLayers(){
  if(_lspLayersRaf)return;
  _lspLayersRaf=requestAnimationFrame(()=>{_lspLayersRaf=0;_lspRenderLayers();});
}
function _lspRenderLayers(){
  const objs=LSP.canvas.getObjects();
  const list=document.getElementById('lsp-layers-list');
  document.getElementById('lsp-layer-count').textContent=objs.length+' layer'+(objs.length!==1?'s':'');
  list.innerHTML='';
  const active=LSP.canvas.getActiveObjects();
  [...objs].reverse().forEach((obj,i)=>{
    const real=objs.length-1-i;
    const isA=active.includes(obj);
    const item=document.createElement('div');
    item.className='lsp-layer-item'+(isA?' active':'');
    item.title=lspLayerLabel(obj)+(obj.lockMovementX?' (locked)':'');
    item.innerHTML=`<i class="bi ${lspLayerIcon(obj)} lsp-layer-type-icon"></i><div class="lsp-layer-thumb"><canvas width="104" height="84"></canvas></div><div class="lsp-layer-name">${obj.lockMovementX?'🔒 ':''}${lspEsc(lspLayerLabel(obj))}</div><i class="bi bi-eye${obj.visible===false?'-slash':''} lsp-layer-vis" onclick="lspToggleVis(${real},event)"></i>`;
    item.addEventListener('click',(ev)=>{
      const cv=LSP.canvas;
      if((ev.shiftKey||ev.ctrlKey||ev.metaKey)&&cv.getActiveObject()){
        const cur=cv.getActiveObjects();
        const next=cur.includes(obj)?cur.filter(o=>o!==obj):cur.concat(obj);
        cv.discardActiveObject();
        if(next.length===1)cv.setActiveObject(next[0]);
        else if(next.length)cv.setActiveObject(new fabric.ActiveSelection(next,{canvas:cv}));
      } else cv.setActiveObject(obj);
      cv.requestRenderAll();lspOnSelect();
    });
    list.appendChild(item);
    // Thumbnail: render the object alone, unzoomed, fitted into the box.
    try{
      const tc=item.querySelector('canvas'),tctx=tc.getContext('2d');
      const ob=obj.getBoundingRect(true,true);
      const sc=Math.min(96/Math.max(ob.width,1),76/Math.max(ob.height,1));
      tctx.save();
      tctx.translate(52-(ob.left+ob.width/2)*sc,42-(ob.top+ob.height/2)*sc);
      tctx.scale(sc,sc);
      const vis=obj.visible;obj.visible=true;obj.render(tctx);obj.visible=vis;
      tctx.restore();
    }catch(e){}
  });
}

/* PANEL */
function lspSwitchPanel(name,btn){
  LSP.activePanel=name;
  document.querySelectorAll('.lsp-ptab').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
  lspRenderPanel(name);
}
function lspRenderPanel(name){
  const body=document.getElementById('lsp-right-body');
  if(!body)return;
  // Don't rebuild under the user's fingers while they are typing in a panel field.
  const st=body.scrollTop;
  if(name==='design')body.innerHTML=lspBuildDesignPanel();
  else if(name==='text')body.innerHTML=lspBuildTextPanel();
  else if(name==='layer')body.innerHTML=lspBuildLayerPanel();
  body.scrollTop=st;
}
let _lspPanelTid=null;
function lspRenderPanelSoon(){
  clearTimeout(_lspPanelTid);
  _lspPanelTid=setTimeout(()=>{
    const ae=document.activeElement;
    if(ae&&ae.closest&&ae.closest('#lsp-right-body')&&/INPUT|SELECT|TEXTAREA/.test(ae.tagName))return;
    lspRenderPanel(LSP.activePanel);
  },60);
}

/* An <input type="color"> only accepts a strict #rrggbb hex string — Fabric
   objects (esp. ones loaded from badge templates) can carry fill/stroke as
   "rgb(r,g,b)" strings, which the browser silently rejects, leaving the input
   stuck black and logging a console warning. Normalize via canvas, which
   parses any valid CSS color and hands back its components reliably. */
let _lspColorCtx=null;
function lspToHexColor(v){
  if(!v||typeof v!=='string')return'#000000';
  if(/^#[0-9a-fA-F]{6}$/.test(v))return v.toLowerCase();
  if(/^#[0-9a-fA-F]{3}$/.test(v))return('#'+v.slice(1).split('').map(x=>x+x).join('')).toLowerCase();
  try{
    const c=_lspColorCtx||(_lspColorCtx=document.createElement('canvas').getContext('2d'));
    c.fillStyle='#000000';
    c.fillStyle=v;
    const norm=c.fillStyle;
    if(/^#[0-9a-fA-F]{6}$/.test(norm))return norm;
    const m=norm.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    if(m)return'#'+[1,2,3].map(i=>(+m[i]).toString(16).padStart(2,'0')).join('');
  }catch(e){}
  return'#000000';
}

function lspNumRow(label,id,val,onchange,extra){
  return `<div style="flex:1;min-width:0;"><div style="font-size:.58rem;color:#8b9ed4;font-weight:700;margin-bottom:2px;">${label}</div><input type="number" class="lsp-inp" id="${id}" value="${val}" ${extra||''} onchange="${onchange}" onkeydown="if(event.key==='Enter')this.blur()"></div>`;
}

function lspBuildDesignPanel(){
  const cv=LSP.canvas;
  const obj=cv?.getActiveObject();
  /* Circular text has its own specialized panel */
  if(obj && obj.type==='circularText') return lspBuildCircularTextPanel(obj);
  const multi=obj&&obj.type==='activeSelection';
  const first=multi?obj.getObjects()[0]:obj;
  const fill=typeof first?.fill==='string'?first.fill:(LSP.currentFillColor||'#1e3a8a');
  const hasGradFill=first?.fill&&typeof first.fill!=='string';
  const stroke=typeof first?.stroke==='string'&&first.stroke?first.stroke:(LSP.currentStrokeColor||'#fbbf24');
  const sw=first?.strokeWidth??LSP.currentStrokeWidth;
  const op=obj?Math.round((obj.opacity??1)*100):100;
  const rot=obj?Math.round(((obj.angle||0)+540)%360-180):0;
  const hasStroke=first&&!lspIsText(first)&&first.type!=='image';
  const isImg=first?.type==='image'&&!multi;
  const isGroup=obj?.type==='group';
  const br=obj?obj.getBoundingRect(true,true):null;
  const locked=!!obj?.lockMovementX;
  // Read the ACTUAL applied gradient's colours (real fabric.Gradient colorStops)
  // so the Start/Mid/End pickers below always reflect what's really on the
  // object, instead of always showing the same two hardcoded preview colours.
  const gradStops=hasGradFill&&first.fill.colorStops?first.fill.colorStops:null;
  const gC1v=gradStops?lspToHexColor(gradStops[0].color):'#1e3a8a';
  const gCMidv=gradStops&&gradStops.length>2?lspToHexColor(gradStops[1].color):'#7c3aed';
  const gC2v=gradStops?lspToHexColor(gradStops[gradStops.length-1].color):'#fbbf24';
  const fillPreview=hasGradFill?`linear-gradient(90deg,${gC1v},${gCMidv},${gC2v})`:fill;
  const fillColorVal=hasGradFill?gC1v:(fill==='transparent'||!fill?'#ffffff':lspToHexColor(fill));
  const head=!obj
    ? `<div class="lsp-section" style="font-size:.68rem;color:#8b9ed4;line-height:1.5;"><i class="bi bi-info-circle"></i> Nothing selected — colours you pick below become the defaults for new shapes &amp; text. Click a layer to edit it.</div>`
    : multi?`<div class="lsp-section" style="font-size:.7rem;color:#c4b5fd;font-weight:700;"><i class="bi bi-ui-checks-grid"></i> ${obj.size()} objects selected — changes apply to all of them
      <div class="lsp-btn-group" style="margin-top:.4rem;"><button class="lsp-micro-btn" onclick="lspGroupLayers()"><i class="bi bi-collection"></i> Group</button><button class="lsp-micro-btn" onclick="lspDuplicateLayer()"><i class="bi bi-copy"></i> Duplicate</button><button class="lsp-micro-btn" style="color:#f87171;" onclick="lspDeleteLayer()"><i class="bi bi-trash"></i> Delete</button></div></div>`
    : isGroup?`<div class="lsp-section" style="font-size:.7rem;color:#c4b5fd;font-weight:700;"><i class="bi bi-collection"></i> Group of ${obj.size()} <button class="lsp-micro-btn" style="margin-left:.4rem;" onclick="lspUngroupLayers()">Ungroup to edit parts</button></div>`:'';
  return head+`<div class="lsp-section"><div class="lsp-section-title"><i class="bi bi-palette"></i>Fill & Color</div>
<div class="lsp-row"><span class="lsp-label">Fill</span><div class="lsp-color-btn" style="background:${lspEsc(fillPreview)};"><input type="color" value="${fillColorVal}" oninput="lspApplyFill(this.value)"></div>
<div class="lsp-swatches">${['#1e3a8a','#7c2d12','#14532d','#fbbf24','#ffffff','#000000','#a855f7','#ef4444','#22c55e','#0ea5e9','#f97316','transparent'].map(c=>`<div class="lsp-swatch" title="${c==='transparent'?'No fill':c}" style="background:${c==='transparent'?'repeating-conic-gradient(#aaa 0% 25%,#fff 0% 50%) 0 0/8px 8px':c};" onclick="lspApplyFill('${c}')"></div>`).join('')}</div></div>
<div class="lsp-row"><span class="lsp-label">Gradient</span><div class="lsp-btn-group">
<button class="lsp-micro-btn" onclick="lspApplyShapeGradient('linear','h')" title="Horizontal">→ H</button>
<button class="lsp-micro-btn" onclick="lspApplyShapeGradient('linear','v')" title="Vertical">↓ V</button>
<button class="lsp-micro-btn" onclick="lspApplyShapeGradient('linear','d')" title="Diagonal">↘ D</button>
<button class="lsp-micro-btn" onclick="lspApplyShapeGradient('radial','')" title="Radial">◉ Rad</button>
<button class="lsp-micro-btn" onclick="lspApplyShapeGradient('linear','a')" title="Anti-diagonal">↙ A</button>
${hasGradFill?`<button class="lsp-micro-btn" onclick="lspApplyFill(document.getElementById('lspGC1').value)" title="Remove gradient" style="color:#f87171;">✕ Clear</button>`:''}
</div></div>
<div class="lsp-row"><span class="lsp-label">Grad. Colors</span><div style="display:flex;gap:.3rem;flex:1;">
<div style="flex:1;text-align:center;"><input type="color" id="lspGC1" value="${gC1v}" oninput="lspLiveGradColor()" onchange="lspSaveHistory()" style="width:100%;height:26px;border:1px solid var(--border-hi);border-radius:5px;cursor:pointer;background:none;padding:1px;"><div style="font-size:.55rem;color:#8b9ed4;margin-top:1px;">Start</div></div>
<div style="flex:1;text-align:center;"><input type="color" id="lspGCMid" value="${gCMidv}" oninput="lspLiveGradColor()" onchange="lspSaveHistory()" style="width:100%;height:26px;border:1px solid var(--border-hi);border-radius:5px;cursor:pointer;background:none;padding:1px;"><div style="font-size:.55rem;color:#8b9ed4;margin-top:1px;">Mid</div></div>
<div style="flex:1;text-align:center;"><input type="color" id="lspGC2" value="${gC2v}" oninput="lspLiveGradColor()" onchange="lspSaveHistory()" style="width:100%;height:26px;border:1px solid var(--border-hi);border-radius:5px;cursor:pointer;background:none;padding:1px;"><div style="font-size:.55rem;color:#8b9ed4;margin-top:1px;">End</div></div>
</div></div>
${(hasStroke||!obj)?`<div class="lsp-row"><span class="lsp-label">Stroke</span><div class="lsp-color-btn" style="background:${lspEsc(stroke)};"><input type="color" value="${lspToHexColor(stroke)}" oninput="lspApplyStroke(this.value)"></div><div class="lsp-swatches">${['#fbbf24','#ffffff','#1e3a8a','#000000','#ef4444','#22c55e'].map(c=>`<div class="lsp-swatch" style="background:${c};" onclick="lspApplyStroke('${c}')"></div>`).join('')}</div></div>
<div class="lsp-row"><span class="lsp-label">Stroke W</span><input type="range" class="lsp-range" min="0" max="30" step="0.5" value="${sw}" oninput="lspApplyStrokeW(+this.value);this.nextElementSibling.textContent=this.value+'px'"><span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:30px;">${sw}px</span></div>
${first&&first.type==='rect'?`<div class="lsp-row"><span class="lsp-label">Corners</span><input type="range" class="lsp-range" min="0" max="${Math.round(Math.min(first.width,first.height)/2)}" value="${first.rx||0}" oninput="lspApplyCorner(+this.value);this.nextElementSibling.textContent=this.value+'px'"><span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:30px;">${first.rx||0}px</span></div>`:''}`:''}
</div>
${obj?`<div class="lsp-section"><div class="lsp-section-title"><i class="bi bi-arrows-move"></i>Position & Size <button class="lsp-micro-btn" style="margin-left:auto;padding:.1rem .4rem;${locked?'color:#fbbf24;border-color:#fbbf24;':''}" onclick="lspToggleLockActive()" title="Lock / unlock">${locked?'🔒 Locked':'🔓 Lock'}</button></div>
<div style="display:flex;gap:.3rem;margin-bottom:.35rem;">
${lspNumRow('X','lspPX',Math.round(br.left),'lspSetBox(\'x\',+this.value)')}
${lspNumRow('Y','lspPY',Math.round(br.top),'lspSetBox(\'y\',+this.value)')}
${lspNumRow('W','lspPW',Math.round(br.width),'lspSetBox(\'w\',+this.value)','min="1"')}
${lspNumRow('H','lspPH',Math.round(br.height),'lspSetBox(\'h\',+this.value)','min="1"')}
</div>
<label style="display:flex;align-items:center;gap:.3rem;font-size:.66rem;color:#9ba8d4;font-weight:600;margin-bottom:.35rem;cursor:pointer;"><input type="checkbox" id="lspKeepRatio" ${LSP.keepRatio!==false?'checked':''} onchange="LSP.keepRatio=this.checked"> Keep proportions</label>
<div class="lsp-row"><span class="lsp-label">Opacity</span><input type="range" class="lsp-range" min="0" max="100" value="${op}" oninput="lspApplyOpacity(+this.value/100);this.nextElementSibling.textContent=this.value+'%'" onchange="lspSaveHistory()"><span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:30px;">${op}%</span></div>
<div class="lsp-row"><span class="lsp-label">Rotation</span><input type="range" class="lsp-range" min="-180" max="180" value="${rot}" oninput="lspApplyRotation(+this.value);this.nextElementSibling.textContent=this.value+'°'" onchange="lspSaveHistory()" style="accent-color:var(--gold);"><span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:30px;">${rot}°</span></div>
<div class="lsp-btn-group">
<button class="lsp-micro-btn" onclick="lspFlip('x')" title="Flip horizontal">⇋ Flip H</button>
<button class="lsp-micro-btn" onclick="lspFlip('y')" title="Flip vertical">⥮ Flip V</button>
<button class="lsp-micro-btn" onclick="lspApplyRotation(0);lspRenderPanel('design')" title="Reset rotation">⟲ 0°</button>
<button class="lsp-micro-btn" onclick="lspCenterOnCanvas()" title="Centre on canvas">⊕ Centre</button>
</div>
</div>`:''}
<div class="lsp-section"><div class="lsp-section-title"><i class="bi bi-shadow"></i>Shadow</div>
<div class="lsp-btn-group">${[['None',0],['Soft',5],['Medium',12],['Hard',25],['Glow',40]].map(([l,v])=>`<button class="lsp-micro-btn" onclick="lspApplyShadow(${v})">${l}</button>`).join('')}</div>
${isImg?`<div class="lsp-row" style="margin-top:.35rem;"><span class="lsp-label">Filter</span><div class="lsp-btn-group">${['None','Grayscale','Sepia','Invert','Blur','Brighten','Contrast'].map(f=>`<button class="lsp-micro-btn" onclick="lspApplyImgFilter('${f.toLowerCase()}')">${f}</button>`).join('')}</div></div>
<div class="lsp-row"><span class="lsp-label">Shape</span><div class="lsp-btn-group">${[['circle','● Circle'],['rounded','▢ Rounded'],['none','✕ None']].map(([s,l])=>`<button class="lsp-micro-btn" onclick="lspImageMask('${s}')">${l}</button>`).join('')}</div></div>`:''}
</div>
<div class="lsp-section"><div class="lsp-section-title"><i class="bi bi-align-center"></i>Align ${multi?'(to each other)':'(to canvas)'}</div>
<div class="lsp-btn-group">${[['left','⬅ Left'],['hcenter','↔ H-Ctr'],['right','➡ Right'],['top','⬆ Top'],['vcenter','↕ V-Ctr'],['bottom','⬇ Bottom']].map(([d,l])=>`<button class="lsp-micro-btn" onclick="lspAlign('${d}')">${l}</button>`).join('')}
${multi&&obj.size()>2?`<button class="lsp-micro-btn" onclick="lspDistribute('h')" title="Equal horizontal spacing">⇿ Distribute H</button><button class="lsp-micro-btn" onclick="lspDistribute('v')" title="Equal vertical spacing">⇳ Distribute V</button>`:''}</div>
</div>
<div class="lsp-section"><div class="lsp-section-title"><i class="bi bi-stack"></i>Layer Order</div>
<div class="lsp-btn-group">
<button class="lsp-micro-btn" onclick="lspLayerFront()">⬆ Front</button>
<button class="lsp-micro-btn" onclick="lspLayerBack()">⬇ Back</button>
<button class="lsp-micro-btn" onclick="lspLayerUp()">↑ Fwd</button>
<button class="lsp-micro-btn" onclick="lspLayerDown()">↓ Bwd</button>
</div></div>
<div class="lsp-section"><div class="lsp-section-title"><i class="bi bi-palette2"></i>Color Palettes</div>
<div style="font-size:.6rem;color:#8b9ed4;margin-bottom:.3rem;">${obj?'Recolours the selected layer(s)':'Recolours the whole design'}</div>
${LSP_PALETTES.map((p,pi)=>`<div style="display:flex;align-items:center;gap:.3rem;margin-bottom:.24rem;cursor:pointer;border-radius:6px;overflow:hidden;border:1px solid var(--border-hi);" onclick="lspApplyPalette(LSP_PALETTES[${pi}].colors)" title="${lspEsc(p.name)}">${p.colors.map(c=>`<div style="flex:1;height:20px;background:${c};"></div>`).join('')}<div style="padding:0 6px;font-size:.62rem;color:#8b9ed4;font-weight:600;white-space:nowrap;">${lspEsc(p.name)}</div></div>`).join('')}
</div>`;
}

function lspBuildTextPanel(){
  const obj=LSP.canvas?.getActiveObject();
  const texts=lspTargets().filter(lspIsText);
  const t0=texts[0];
  const isT=!!t0;
  const ff=isT?t0.fontFamily:LSP.currentFont;
  const fs=isT?Math.round(t0.fontSize):LSP.currentFontSize;
  const fc=isT?(typeof t0.fill==='string'?t0.fill:'#1e3a8a'):LSP.currentTextColor;
  const fw=isT&&(t0.fontWeight==='bold'||+t0.fontWeight>=600);
  const fi=isT&&t0.fontStyle==='italic';
  const fu=isT&&!!t0.underline;
  const ta=isT?t0.textAlign:'center';
  const tsk=isT&&t0.stroke?t0.stroke:'';
  const tskW=isT&&t0.strokeWidth?t0.strokeWidth:0;
  const tskPreview=tsk||'#fbbf24';
  const lh=isT?(t0.lineHeight||1.16):1.16;
  const fonts=['Cinzel','Montserrat','Poppins','Playfair Display','Raleway','Oswald','Open Sans','Nunito','Exo 2','Ubuntu','Source Sans Pro','Bebas Neue','Righteous','Pacifico','Lobster','Dancing Script','Georgia','Impact','Times New Roman','Arial','Verdana'];
  if(ff&&!fonts.includes(ff))fonts.unshift(ff);
  const note=!isT&&obj?`<div class="lsp-section" style="font-size:.66rem;color:#fbbf24;">Selected layer is not text — settings below apply to new text.</div>`:'';
  return note+`<div class="lsp-section"><div class="lsp-section-title"><i class="bi bi-type"></i>Add Text</div>
<button class="lsp-micro-btn" style="width:100%;padding:.35rem;margin-bottom:.3rem;" onclick="lspAddText()"><i class="bi bi-plus-lg"></i> Add Text Layer</button>
<button class="lsp-micro-btn" style="width:100%;padding:.3rem;margin-bottom:.3rem;" onclick="lspAddTextPreset()"><i class="bi bi-type-h1"></i> School Heading Style</button>
<button class="lsp-micro-btn" style="width:100%;padding:.3rem;" onclick="lspAddCircularText('top')"><i class="bi bi-arrow-repeat"></i> Circular Arc Text</button>
</div>
${isT&&texts.length===1?`<div class="lsp-section"><div class="lsp-section-title"><i class="bi bi-pencil"></i>Text Content</div>
<textarea class="lsp-inp" rows="2" style="resize:vertical;font-family:inherit;" oninput="lspSetTextContent(this.value)" onchange="lspSaveHistory()">${lspEsc(t0.text)}</textarea>
<div class="lsp-btn-group" style="margin-top:.3rem;"><button class="lsp-micro-btn" onclick="lspTextCase('upper')">AA</button><button class="lsp-micro-btn" onclick="lspTextCase('lower')">aa</button><button class="lsp-micro-btn" onclick="lspTextCase('title')">Aa</button></div></div>`:''}
<div class="lsp-section"><div class="lsp-section-title"><i class="bi bi-fonts"></i>Font Settings${texts.length>1?` <span style="color:#c4b5fd;">(${texts.length} texts)</span>`:''}</div>
<div class="lsp-row"><span class="lsp-label">Font</span><select class="lsp-select" onchange="lspApplyFont(this.value)">${fonts.map(f=>`<option ${f===ff?'selected':''} style="font-family:'${f}'">${lspEsc(f)}</option>`).join('')}</select></div>
<div class="lsp-row"><span class="lsp-label">Size</span><input type="range" class="lsp-range" min="8" max="200" value="${fs}" oninput="lspApplyFontSize(+this.value);this.nextElementSibling.value=this.value" onchange="lspSaveHistory()"><input type="number" class="lsp-inp lsp-num" min="4" max="600" value="${fs}" onchange="lspApplyFontSize(+this.value);this.previousElementSibling.value=this.value;lspSaveHistory()"></div>
<div class="lsp-row"><span class="lsp-label">Color</span><div class="lsp-color-btn" style="background:${lspEsc(fc||'#1e3a8a')};"><input type="color" value="${lspToHexColor(fc||'#1e3a8a')}" oninput="lspApplyTextColor(this.value)" onchange="lspSaveHistory()"></div><div class="lsp-swatches">${['#ffffff','#fbbf24','#1e3a8a','#000000','#c9a84c','#ef4444','#7c3aed','#22c55e'].map(c=>`<div class="lsp-swatch" style="background:${c};" onclick="lspApplyTextColor('${c}');lspSaveHistory()"></div>`).join('')}</div></div>
<div class="lsp-row" title="Text outline / border around each letter"><span class="lsp-label">Stroke</span><div class="lsp-color-btn" style="background:${lspEsc(tskPreview)};box-shadow:0 0 0 2px ${tskW>0?'#fbbf24':'rgba(255,255,255,.15)'};"><input type="color" value="${lspToHexColor(tskPreview)}" oninput="lspApplyTextStroke(this.value)"></div><div class="lsp-swatches">${['#fbbf24','#ffffff','#000000','#c0392b','#1e3a8a','#15803d','#a855f7','#f97316'].map(c=>`<div class="lsp-swatch" style="background:${c};" onclick="lspApplyTextStroke('${c}')"></div>`).join('')}</div><button class="lsp-micro-btn" onclick="lspApplyTextStrokeW(0);lspRenderPanel('text')" style="margin-left:.18rem;padding:.1rem .28rem;font-size:.6rem;height:auto;min-width:auto;color:#f87171;" title="Remove stroke">✕</button></div>
<div class="lsp-row"><span class="lsp-label">Stroke W</span><input type="range" class="lsp-range" min="0" max="18" step="0.5" value="${tskW}" oninput="lspApplyTextStrokeW(+this.value);this.nextElementSibling.textContent=this.value+'px'" style="accent-color:#fbbf24;"><span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:28px;">${tskW}px</span></div>
<div class="lsp-row"><span class="lsp-label">Style</span><div class="lsp-btn-group"><button class="lsp-micro-btn ${fw?'active':''}" onclick="lspToggleBold()"><b>B</b></button><button class="lsp-micro-btn ${fi?'active':''}" onclick="lspToggleItalic()"><i>I</i></button><button class="lsp-micro-btn ${fu?'active':''}" onclick="lspToggleUnderline()"><u>U</u></button></div></div>
<div class="lsp-row"><span class="lsp-label">Align</span><div class="lsp-btn-group">${['left','center','right'].map(a=>`<button class="lsp-micro-btn ${ta===a?'active':''}" onclick="lspApplyTextAlign('${a}')">${a}</button>`).join('')}</div></div>
<div class="lsp-row"><span class="lsp-label">Spacing</span><input type="range" class="lsp-range" min="-100" max="800" value="${isT?(t0.charSpacing||0):0}" oninput="lspApplyCharSpacing(+this.value);this.nextElementSibling.textContent=this.value" onchange="lspSaveHistory()" style="accent-color:var(--cyan);"><span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:24px;">${isT?(t0.charSpacing||0):0}</span></div>
<div class="lsp-row"><span class="lsp-label">Line H</span><input type="range" class="lsp-range" min="0.7" max="3" step="0.05" value="${lh}" oninput="lspApplyLineHeight(+this.value);this.nextElementSibling.textContent=(+this.value).toFixed(2)" onchange="lspSaveHistory()"><span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:28px;">${(+lh).toFixed(2)}</span></div>
</div>
<div class="lsp-section"><div class="lsp-section-title"><i class="bi bi-stars"></i>Text Effects</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:.28rem;">
${[{id:'none',l:'Normal',s:'color:var(--text-bright)'},{id:'shadow',l:'Shadow',s:'color:#fff;text-shadow:3px 3px 0 rgba(0,0,0,.5)'},{id:'outline',l:'Outline',s:'color:#1e3a8a;-webkit-text-stroke:1.5px #fbbf24'},{id:'glow',l:'Glow',s:'color:#a855f7;text-shadow:0 0 8px #a855f7,0 0 20px #a855f7'},{id:'gradient',l:'Gradient',s:'background:linear-gradient(90deg,#f59e0b,#a855f7);-webkit-background-clip:text;-webkit-text-fill-color:transparent'},{id:'3d',l:'3D Bold',s:'color:#1e3a8a;text-shadow:3px 3px 0 #fbbf24,5px 5px 0 rgba(0,0,0,.2)'},{id:'neon',l:'Neon',s:'color:#22c55e;text-shadow:0 0 5px #22c55e,0 0 15px #22c55e,0 0 30px #22c55e'},{id:'gold',l:'Gold',s:'color:#fbbf24;text-shadow:1px 1px 0 #92400e,2px 2px 5px rgba(0,0,0,.4)'},{id:'fire',l:'Fire',s:'background:linear-gradient(0deg,#ef4444,#f97316,#fbbf24);-webkit-background-clip:text;-webkit-text-fill-color:transparent'},{id:'retro',l:'Retro',s:'color:#f97316;text-shadow:-2px 0 #fff,2px 0 #ef4444'},{id:'emboss',l:'Emboss',s:'color:#d4c5a0;text-shadow:1px 1px 0 #fff,-1px -1px 0 #888'},{id:'ink',l:'Ink Pen',s:'color:#1e3a8a;font-style:italic;text-shadow:1px 1px 2px rgba(0,0,0,.2)'}].map(ef=>`<div class="lsp-effect-card" onclick="lspApplyTextEffect('${ef.id}')"><div class="lsp-effect-preview" style="${ef.s};font-family:'Montserrat',sans-serif;font-weight:900;">Aa</div><div>${ef.l}</div></div>`).join('')}
</div></div>
<div class="lsp-section"><div class="lsp-section-title"><i class="bi bi-translate"></i>Quick School Texts</div>
<div style="display:grid;grid-template-columns:1fr;gap:.22rem;">
${LSP_QUICK_TEXTS.map((t,i)=>`<button class="lsp-micro-btn" style="text-align:left;" onclick="lspInsertQuickText(LSP_QUICK_TEXTS[${i}])">${lspEsc(t)}</button>`).join('')}
</div></div>`;
}
const LSP_QUICK_TEXTS=['School Name Here','Motto / Tagline','Est. 1985','Knowledge is Power','Satyam Shivam Sundaram','Tamso Ma Jyotirgamaya','Excellence Always','Jai Hind','ॐ श्री गणेशाय नमः','विद्या ददाति विनयम्','Vidya Vinayam Dadati','सा विद्या या विमुक्तये'];

function lspBuildLayerPanel(){
  const objs=LSP.canvas?.getObjects()||[];
  if(!objs.length)return`<div class="lsp-section" style="text-align:center;color:var(--text-dim);padding:2rem 1rem;font-size:.72rem;">No layers yet.<br>Use toolbar to add elements.</div>`;
  const active=LSP.canvas.getActiveObjects();
  return`<div class="lsp-section"><div class="lsp-section-title"><i class="bi bi-stack"></i>Layer Stack (${objs.length}) <span style="margin-left:auto;font-size:.56rem;color:#6b7ab8;text-transform:none;letter-spacing:0;">double-click a name to rename</span></div>
${[...objs].reverse().map((obj,i)=>{
  const real=objs.length-1-i;const isA=active.includes(obj);
  return`<div style="display:flex;align-items:center;gap:.4rem;padding:.3rem .4rem;border-radius:6px;cursor:pointer;background:${isA?'var(--bg-active)':'transparent'};border:1px solid ${isA?'var(--purple)':'transparent'};margin-bottom:.2rem;" onclick="LSP.canvas.setActiveObject(LSP.canvas.getObjects()[${real}]);LSP.canvas.requestRenderAll();lspOnSelect()" ondblclick="lspRenameLayer(${real})"><i class="bi ${lspLayerIcon(obj)}" style="color:var(--purple);font-size:.75rem;flex-shrink:0;"></i><span style="flex:1;font-size:.68rem;color:${isA?'var(--text-bright)':'var(--text-mid)'};overflow:hidden;text-overflow:ellipsis;white-space:nowrap;${obj.visible===false?'opacity:.45;':''}">${lspEsc(lspLayerLabel(obj))}</span><i class="bi bi-eye${obj.visible===false?'-slash':''}" style="font-size:.7rem;color:var(--text-mid);cursor:pointer;padding:2px;" title="Show / hide" onclick="lspToggleVis(${real},event);lspRenderPanel('layer')"></i><i class="bi bi-lock${obj.lockMovementX?'-fill':'-open'}" style="font-size:.7rem;color:${obj.lockMovementX?'#fbbf24':'var(--text-mid)'};cursor:pointer;padding:2px;" title="Lock / unlock" onclick="lspToggleLock(${real},event)"></i></div>`;
}).join('')}</div>
<div class="lsp-section"><div class="lsp-btn-group">
<button class="lsp-micro-btn" onclick="lspSelectAll()">Select all</button>
<button class="lsp-micro-btn" onclick="lspGroupLayers()">Group</button>
<button class="lsp-micro-btn" onclick="lspUngroupLayers()">Ungroup</button>
<button class="lsp-micro-btn" onclick="lspDuplicateLayer()">Duplicate</button>
<button class="lsp-micro-btn" style="color:#f87171;" onclick="lspDeleteLayer()">Delete</button>
</div></div>`;
}
function lspRenameLayer(idx){
  const o=LSP.canvas.getObjects()[idx];if(!o)return;
  lspPromptModal('Rename layer',[{id:'n',label:'Layer name',value:lspLayerLabel(o)}],v=>{
    o.lspName=(v.n||'').trim().slice(0,40)||undefined;lspRefreshLayers();lspRenderPanel(LSP.activePanel);lspSaveHistory();
  });
}

/* PROPERTY APPLIERS — each applies to every selected object and is undoable */
function lspEach(fn,filter){
  const objs=lspTargets().filter(filter||(()=>true));
  objs.forEach(fn);
  return objs.length;
}
/* For colour changes, step inside groups so e.g. a flag/emblem can be recoloured as a whole. */
function lspEachDeep(fn,filter){
  let n=0;
  const walk=o=>{if(o.type==='group'){o.getObjects().forEach(walk);o.dirty=true;}else if(!filter||filter(o)){fn(o);n++;}};
  lspTargets().forEach(walk);
  return n;
}
function lspApplyFill(v){
  LSP.currentFillColor=v;
  if(lspEachDeep(o=>{o.set('fill',v);},o=>o.type!=='image'&&!(o.type==='line'))){lspTouch();lspRenderPanelSoon();}
}
function lspApplyStroke(v){
  LSP.currentStrokeColor=v;
  if(lspEachDeep(o=>{o.set('stroke',v);if(!o.strokeWidth)o.set('strokeWidth',LSP.currentStrokeWidth||2);},o=>o.type!=='image'&&!lspIsText(o)))lspTouch();
}
function lspApplyStrokeW(v){LSP.currentStrokeWidth=v;if(lspEachDeep(o=>{o.set('strokeWidth',v);if(v>0&&!o.stroke)o.set('stroke',LSP.currentStrokeColor);},o=>o.type!=='image'&&!lspIsText(o))){LSP.canvas.getActiveObject()?.setCoords();lspTouch();}}
function lspApplyCorner(v){if(lspEach(o=>o.set({rx:v,ry:v}),o=>o.type==='rect'))lspTouch();}
function lspApplyOpacity(v){if(lspEach(o=>o.set('opacity',v)))LSP.canvas.requestRenderAll();}
/* rotate() turns around the object's centre; set('angle') spun left/top-origin
   objects around their corner, throwing them off-canvas. */
function lspApplyRotation(v){
  const a=LSP.canvas.getActiveObject();if(!a)return;
  a.rotate(v);a.setCoords();LSP.canvas.requestRenderAll();
}
function lspFlip(axis){
  if(lspEach(o=>o.set(axis==='x'?'flipX':'flipY',!(axis==='x'?o.flipX:o.flipY))))lspTouch();
}
function lspCenterOnCanvas(){const a=LSP.canvas.getActiveObject();if(!a)return;a.setPositionByOrigin(new fabric.Point(LSP.canvasW/2,LSP.canvasH/2),'center','center');a.setCoords();lspTouch();lspRenderPanelSoon();}
/* Exact X/Y/W/H typed into the panel (measured on the visible bounding box). */
function lspSetBox(k,v){
  const a=LSP.canvas.getActiveObject();if(!a||!isFinite(v))return;
  const br=a.getBoundingRect(true,true);
  if(k==='x')a.left+=v-br.left;
  else if(k==='y')a.top+=v-br.top;
  else if(v>0){
    const keep=LSP.keepRatio!==false;
    const f=k==='w'?v/br.width:v/br.height;
    if(keep){a.scaleX*=f;a.scaleY*=f;}else if(k==='w')a.scaleX*=f;else a.scaleY*=f;
  }
  a.setCoords();lspTouch();lspRenderPanelSoon();
}
function lspApplyFont(v){
  LSP.currentFont=v;
  const texts=lspTargets().filter(o=>lspIsText(o)||o.type==='circularText');
  texts.forEach(o=>o.set('fontFamily',v));
  LSP.canvas.requestRenderAll();
  lspEnsureFont(v,'bold').then(()=>{lspRefreshTextMetrics(texts);if(texts.length)lspSaveHistory();});
}
function lspApplyFontSize(v){
  if(!(v>0))return;
  LSP.currentFontSize=v;
  lspEach(o=>{o.set('fontSize',v);if(o._recalcSize)o._recalcSize();o.setCoords();},o=>lspIsText(o)||o.type==='circularText');
  LSP.canvas.requestRenderAll();
}
function lspApplyTextColor(v){LSP.currentTextColor=v;if(lspEach(o=>o.set('fill',v),o=>lspIsText(o)||o.type==='circularText'))LSP.canvas.requestRenderAll();}
function lspSetTextContent(v){const o=LSP.canvas.getActiveObject();if(!lspIsText(o))return;o.set('text',v);o.initDimensions&&o.initDimensions();o.setCoords();LSP.canvas.requestRenderAll();lspRefreshLayers();}
function lspTextCase(mode){
  if(lspEach(o=>{let t=o.text||'';t=mode==='upper'?t.toUpperCase():mode==='lower'?t.toLowerCase():t.toLowerCase().replace(/(^|\s)(\S)/g,(m,a,b)=>a+b.toUpperCase());o.set('text',t);o.initDimensions&&o.initDimensions();o.setCoords();},o=>lspIsText(o)||o.type==='circularText')){lspTouch();lspRefreshLayers();lspRenderPanel('text');}
}
function lspToggleBold(){
  const t=lspTargets().filter(lspIsText);if(!t.length)return;
  const next=(t[0].fontWeight==='bold'||+t[0].fontWeight>=600)?'normal':'bold';
  t.forEach(o=>o.set('fontWeight',next));lspEnsureFont(t[0].fontFamily,next).then(()=>lspRefreshTextMetrics(t));
  lspTouch();lspRenderPanel('text');
}
function lspToggleItalic(){const t=lspTargets().filter(lspIsText);if(!t.length)return;const next=t[0].fontStyle==='italic'?'normal':'italic';t.forEach(o=>o.set('fontStyle',next));lspTouch();lspRenderPanel('text');}
function lspToggleUnderline(){const t=lspTargets().filter(lspIsText);if(!t.length)return;const next=!t[0].underline;t.forEach(o=>o.set('underline',next));lspTouch();lspRenderPanel('text');}
function lspApplyTextAlign(v){if(lspEach(o=>o.set('textAlign',v),lspIsText)){lspTouch();lspRenderPanel('text');}}
function lspApplyCharSpacing(v){if(lspEach(o=>{o.set('charSpacing',v);o.setCoords();},lspIsText))LSP.canvas.requestRenderAll();}
function lspApplyLineHeight(v){if(lspEach(o=>{o.set('lineHeight',v);o.setCoords();},lspIsText))LSP.canvas.requestRenderAll();}
function lspApplyTextStroke(color){
  if(lspEach(o=>{o.set({stroke:color,paintFirst:'stroke'});if(!o.strokeWidth)o.set('strokeWidth',2);},lspIsText))lspTouch();
}
function lspApplyTextStrokeW(w){
  if(lspEach(o=>{if(w>0)o.set({strokeWidth:w,stroke:o.stroke||'#fbbf24',paintFirst:'stroke'});else o.set({stroke:'',strokeWidth:0});},lspIsText))lspTouch();
}
function lspApplyShadow(blur){
  if(lspEach(o=>o.set('shadow',blur===0?null:new fabric.Shadow({color:blur>=40?'rgba(251,191,36,.85)':'rgba(0,0,0,0.45)',blur,offsetX:blur>=40?0:2,offsetY:blur>=40?0:4}))))lspTouch();
  else lspShowToast('Select an object first');
}
function lspGradCoords(type,dir){
  if(type==='radial')return{x1:0.5,y1:0.5,r1:0,x2:0.5,y2:0.5,r2:0.5};
  if(dir==='h')return{x1:0,y1:0,x2:1,y2:0};
  if(dir==='v')return{x1:0,y1:0,x2:0,y2:1};
  if(dir==='d')return{x1:0,y1:0,x2:1,y2:1};
  if(dir==='a')return{x1:1,y1:0,x2:0,y2:1};
  return{x1:0,y1:0,x2:1,y2:0};
}
function lspMakeGrad(type,coords,c1,cMid,c2){
  return new fabric.Gradient({type,gradientUnits:'percentage',coords,
    colorStops:[{offset:0,color:c1},{offset:0.5,color:cMid},{offset:1,color:c2}]});
}
function lspApplyShapeGradient(type,dir){
  if(!lspTargets().length){lspShowToast('Select an object first');return;}
  const c1=document.getElementById('lspGC1')?.value||LSP.currentFillColor||'#1e3a8a';
  const cMid=document.getElementById('lspGCMid')?.value||'#7c3aed';
  const c2=document.getElementById('lspGC2')?.value||LSP.currentStrokeColor||'#fbbf24';
  const n=lspEachDeep(o=>{o.set('fill',lspMakeGrad(type,lspGradCoords(type,dir),c1,cMid,c2));},o=>o.type!=='image'&&o.type!=='line'&&o.type!=='circularText');
  if(!n){lspShowToast('Gradients work on shapes and text');return;}
  lspTouch();lspShowToast('Gradient applied!');
  lspRenderPanel('design');
}
// Live-reapply as the user drags the Start/Mid/End colour pickers. Reads the
// type/coords from the object's OWN current gradient, so it also works on a
// gradient reloaded from a saved design.
function lspLiveGradColor(){
  const c1=document.getElementById('lspGC1').value;
  const cMid=document.getElementById('lspGCMid').value;
  const c2=document.getElementById('lspGC2').value;
  let any=false;
  lspEachDeep(o=>{
    if(!o.fill||typeof o.fill==='string')return;
    any=true;
    o.set('fill',lspMakeGrad(o.fill.type||'linear',o.fill.coords||lspGradCoords('linear','h'),c1,cMid,c2));
  });
  if(any)LSP.canvas.requestRenderAll();
}
function lspApplyImgFilter(type){
  const imgs=lspTargets().filter(o=>o.type==='image');if(!imgs.length)return;
  imgs.forEach(o=>{
    o.filters=[];
    if(type==='grayscale')o.filters.push(new fabric.Image.filters.Grayscale());
    else if(type==='sepia')o.filters.push(new fabric.Image.filters.Sepia());
    else if(type==='invert')o.filters.push(new fabric.Image.filters.Invert());
    else if(type==='blur')o.filters.push(new fabric.Image.filters.Blur({blur:0.15}));
    else if(type==='brighten')o.filters.push(new fabric.Image.filters.Brightness({brightness:0.12}));
    else if(type==='contrast')o.filters.push(new fabric.Image.filters.Contrast({contrast:0.2}));
    try{o.applyFilters();}catch(e){lspShowToast('Filter not supported for this image');}
  });
  lspTouch();
}
/* Round / circular crop for a photo (e.g. founder's photo inside a badge). */
function lspImageMask(shape){
  const o=LSP.canvas.getActiveObject();if(!o||o.type!=='image')return;
  if(shape==='none')o.set('clipPath',null);
  else if(shape==='circle')o.set('clipPath',new fabric.Circle({radius:Math.min(o.width,o.height)/2,originX:'center',originY:'center'}));
  else{const r=Math.min(o.width,o.height)*.14;o.set('clipPath',new fabric.Rect({width:o.width,height:o.height,rx:r,ry:r,originX:'center',originY:'center'}));}
  o.dirty=true;lspTouch();
}
/* Align by the visible bounding box (works for rotated objects; the old version
   changed originX/Y, which made rotated or left-origin objects jump). With a
   multi-selection, objects are aligned to each other. */
function lspAlign(dir){
  const cv=LSP.canvas,a=cv.getActiveObject();if(!a)return;
  const move=(o,ref)=>{
    const b=o.getBoundingRect(true,true);
    let dx=0,dy=0;
    if(dir==='left')dx=ref.l-b.left;
    else if(dir==='hcenter')dx=(ref.l+ref.r)/2-(b.left+b.width/2);
    else if(dir==='right')dx=ref.r-(b.left+b.width);
    else if(dir==='top')dy=ref.t-b.top;
    else if(dir==='vcenter')dy=(ref.t+ref.b)/2-(b.top+b.height/2);
    else if(dir==='bottom')dy=ref.b-(b.top+b.height);
    o.left+=dx;o.top+=dy;o.setCoords();
  };
  if(a.type==='activeSelection'){
    const objs=a.getObjects();
    cv.discardActiveObject();
    const bs=objs.map(o=>o.getBoundingRect(true,true));
    const ref={l:Math.min(...bs.map(b=>b.left)),t:Math.min(...bs.map(b=>b.top)),r:Math.max(...bs.map(b=>b.left+b.width)),b:Math.max(...bs.map(b=>b.top+b.height))};
    objs.forEach(o=>move(o,ref));
    cv.setActiveObject(new fabric.ActiveSelection(objs,{canvas:cv}));
  } else move(a,{l:0,t:0,r:LSP.canvasW,b:LSP.canvasH});
  lspTouch();lspRenderPanelSoon();
}
function lspDistribute(axis){
  const cv=LSP.canvas,a=cv.getActiveObject();
  if(!a||a.type!=='activeSelection'||a.size()<3){lspShowToast('Select 3 or more objects to distribute');return;}
  const objs=a.getObjects();cv.discardActiveObject();
  const items=objs.map(o=>({o,b:o.getBoundingRect(true,true)}));
  const k=axis==='h'?'left':'top',s=axis==='h'?'width':'height';
  items.sort((p,q)=>p.b[k]-q.b[k]);
  const start=items[0].b[k],end=items[items.length-1].b[k]+items[items.length-1].b[s];
  const total=items.reduce((t,i)=>t+i.b[s],0);
  const gap=(end-start-total)/(items.length-1);
  let pos=start;
  items.forEach(i=>{const d=pos-i.b[k];if(axis==='h')i.o.left+=d;else i.o.top+=d;i.o.setCoords();pos+=i.b[s]+gap;});
  cv.setActiveObject(new fabric.ActiveSelection(objs,{canvas:cv}));
  lspTouch();lspShowToast('Distributed evenly');
}
/* Palette: recolours the selection (or whole design when nothing is selected),
   stepping into groups. Text gets the darkest/contrasting colours. Undoable. */
function lspApplyPalette(colors){
  const cv=LSP.canvas;
  let objs=lspTargets();
  const all=!objs.length;
  if(all)objs=cv.getObjects();
  if(!objs.length){LSP.currentFillColor=colors[0];LSP.currentTextColor=colors[1];lspShowToast('Palette set as default colours');return;}
  let i=0;
  const walk=o=>{
    if(o.type==='group'){o.getObjects().forEach(walk);o.dirty=true;return;}
    if(o.type==='image'||o.lspEraser)return;
    if(lspIsText(o)||o.type==='circularText')o.set('fill',colors[(i+1)%colors.length]);
    else if(o.fill&&o.fill!=='transparent'&&o.fill!=='')o.set('fill',colors[i%colors.length]);
    if(o.stroke&&o.stroke!=='transparent')o.set('stroke',colors[(i+2)%colors.length]);
    i++;
  };
  objs.forEach(walk);
  lspTouch();lspRenderPanelSoon();lspShowToast('Palette applied'+(all?' to whole design':' to selection')+' — Ctrl+Z to undo');
}
function lspApplyTextEffect(eff){
  const texts=lspTargets().filter(lspIsText);
  if(!texts.length){lspShowToast('Select a text layer first');return;}
  texts.forEach(o=>{
    o.set({stroke:'',strokeWidth:0,shadow:null,fontStyle:'normal'});
    if(typeof o.fill!=='string')o.set('fill','#1e3a8a');
    if(eff==='shadow'){o.set({shadow:new fabric.Shadow({color:'rgba(0,0,0,.5)',blur:4,offsetX:3,offsetY:3})});}
    else if(eff==='outline'){o.set({stroke:'#fbbf24',strokeWidth:2,fill:'#1e3a8a',paintFirst:'stroke'});}
    else if(eff==='glow'){o.set({fill:'#c4b5fd',shadow:new fabric.Shadow({color:'rgba(168,85,247,.8)',blur:15,offsetX:0,offsetY:0})});}
    else if(eff==='gradient'){o.set('fill',lspMakeGrad('linear',{x1:0,y1:0,x2:1,y2:0},'#f59e0b','#ef4444','#a855f7'));}
    else if(eff==='3d'){o.set({fill:'#1e3a8a',shadow:new fabric.Shadow({color:'#fbbf24',blur:0,offsetX:4,offsetY:4})});}
    else if(eff==='neon'){o.set({fill:'#22c55e',shadow:new fabric.Shadow({color:'rgba(34,197,94,.9)',blur:20,offsetX:0,offsetY:0})});}
    else if(eff==='gold'){o.set({fill:'#fbbf24',shadow:new fabric.Shadow({color:'rgba(0,0,0,.4)',blur:4,offsetX:2,offsetY:2})});}
    else if(eff==='fire'){o.set('fill',lspMakeGrad('linear',{x1:0,y1:1,x2:0,y2:0},'#ef4444','#f97316','#fbbf24'));}
    else if(eff==='retro'){o.set({fill:'#f97316',shadow:new fabric.Shadow({color:'#ef4444',blur:0,offsetX:-2,offsetY:0})});}
    else if(eff==='emboss'){o.set({fill:'#d4c5a0',shadow:new fabric.Shadow({color:'rgba(255,255,255,.8)',blur:0,offsetX:1,offsetY:1})});}
    else if(eff==='ink'){o.set({fill:'#1e3a8a',fontStyle:'italic'});}
    o.initDimensions&&o.initDimensions();o.setCoords();
  });
  lspTouch();lspRenderPanel('text');
}
function lspInsertQuickText(txt){
  const t=new fabric.IText(txt,{left:LSP.canvasW/2,top:LSP.canvasH/2,originX:'center',originY:'center',fontFamily:LSP.currentFont,fontSize:Math.min(LSP.currentFontSize,Math.round(LSP.canvasW*0.07)),fill:LSP.currentTextColor,fontWeight:'bold',textAlign:'center'});
  // Keep long phrases inside the canvas.
  if(t.width*t.scaleX>LSP.canvasW*0.9)t.scaleToWidth(LSP.canvasW*0.9);
  LSP.canvas.add(t).setActiveObject(t);
  lspEnsureFont(t.fontFamily,'bold').then(()=>lspRefreshTextMetrics([t]));
}
function lspAddTextPreset(){
  const t=new fabric.IText('SCHOOL NAME',{left:LSP.canvasW/2,top:LSP.canvasH*0.33,originX:'center',originY:'center',fontFamily:'Cinzel',fontSize:52,fill:'#1e3a8a',fontWeight:'700',textAlign:'center',charSpacing:50,shadow:new fabric.Shadow({color:'rgba(0,0,0,.25)',blur:4,offsetX:2,offsetY:2})});
  if(t.width>LSP.canvasW*0.9)t.scaleToWidth(LSP.canvasW*0.9);
  t.lspName='School Heading';LSP.canvas.add(t).setActiveObject(t);
  lspEnsureFont('Cinzel','700').then(()=>lspRefreshTextMetrics([t]));
}

/* TEMPLATES */
let _tplCat='School Badges',_tplSearch='';
function lspOpenTemplates(){
  lspToggleFlyout('fly-templates',()=>{lspBuildTplCats();lspRenderTplGrid(_tplCat);});
}
function lspBuildTplCats(){
  document.getElementById('tpl-cats').innerHTML=LSP_TPL_CATS.map(c=>`<button class="lsp-cat-btn${c===_tplCat?' active':''}" onclick="lspSelTplCat('${c.replace(/\//g,'_')}',this)">${c}</button>`).join('');
}
function lspSelTplCat(cat,btn){
  const real=LSP_TPL_CATS.find(c=>c.replace(/\//g,'_')===cat)||cat;
  _tplCat=real;
  document.querySelectorAll('#tpl-cats .lsp-cat-btn').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
  lspRenderTplGrid(real);
}
function lspFilterTemplates(q){_tplSearch=q;lspRenderTplGrid(_tplCat);}
function lspRenderTplGrid(cat){
  let tpls=_tplSearch?LSP_TEMPLATES.filter(t=>t.label.toLowerCase().includes(_tplSearch.toLowerCase())):LSP_TEMPLATES.filter(t=>t.cat===cat);
  const q=(_tplSearch||'').trim().toLowerCase();
  if(q)tpls=LSP_TEMPLATES.filter(t=>t.label.toLowerCase().includes(q)||t.cat.toLowerCase().includes(q));
  const grid=document.getElementById('tpl-grid');
  if(!tpls.length){grid.innerHTML='<div style="grid-column:1/-1;text-align:center;color:var(--text-mid);padding:2rem;font-size:.75rem;">No templates match “'+lspEsc(_tplSearch)+'”</div>';return;}
  grid.innerHTML=tpls.map(t=>`<div class="lsp-tpl-card" onclick="lspApplyTemplate('${t.id}')" title="${lspEsc(t.label)}"><canvas width="130" height="130" id="tcv_${t.id}"></canvas><div class="lsp-tpl-label">${lspEsc(t.label)}</div></div>`).join('');
  setTimeout(()=>tpls.forEach(t=>lspRenderTplThumb(t)),20);
}
function lspThFrame(ctx,cx,cy,r,f,pri,acc,bw){
  ctx.save();ctx.beginPath();
  if(f==='circle')ctx.arc(cx,cy,r,0,Math.PI*2);
  else if(f==='hex'){for(let i=0;i<6;i++){const a=i*Math.PI/3-Math.PI/6;i===0?ctx.moveTo(cx+r*Math.cos(a),cy+r*Math.sin(a)):ctx.lineTo(cx+r*Math.cos(a),cy+r*Math.sin(a));}ctx.closePath();}
  else if(f==='shield'){ctx.moveTo(cx,cy-r);ctx.lineTo(cx+r*.85,cy-r*.5);ctx.lineTo(cx+r*.85,cy+r*.2);ctx.quadraticCurveTo(cx+r*.85,cy+r*.85,cx,cy+r);ctx.quadraticCurveTo(cx-r*.85,cy+r*.85,cx-r*.85,cy+r*.2);ctx.lineTo(cx-r*.85,cy-r*.5);ctx.closePath();}
  else if(f==='badge'){const pts=8,ir=r*.84;for(let i=0;i<pts*2;i++){const rd=i%2===0?r:ir,a=i*Math.PI/pts-Math.PI/2;i===0?ctx.moveTo(cx+rd*Math.cos(a),cy+rd*Math.sin(a)):ctx.lineTo(cx+rd*Math.cos(a),cy+rd*Math.sin(a));}ctx.closePath();}
  else if(f==='diamond'){ctx.moveTo(cx,cy-r);ctx.lineTo(cx+r*.75,cy);ctx.lineTo(cx,cy+r);ctx.lineTo(cx-r*.75,cy);ctx.closePath();}
  else if(f==='star'){const pts=5,ir=r*.45;for(let i=0;i<pts*2;i++){const rd=i%2===0?r:ir,a=i*Math.PI/pts-Math.PI/2;i===0?ctx.moveTo(cx+rd*Math.cos(a),cy+rd*Math.sin(a)):ctx.lineTo(cx+rd*Math.cos(a),cy+rd*Math.sin(a));}ctx.closePath();}
  /* These frames all fell back to a plain circle thumbnail before. */
  else if(f==='square')ctx.rect(cx-r,cy-r,r*2,r*2);
  else if(f==='rounded'){const k=r*.3;ctx.moveTo(cx-r+k,cy-r);ctx.arcTo(cx+r,cy-r,cx+r,cy+r,k);ctx.arcTo(cx+r,cy+r,cx-r,cy+r,k);ctx.arcTo(cx-r,cy+r,cx-r,cy-r,k);ctx.arcTo(cx-r,cy-r,cx+r,cy-r,k);ctx.closePath();}
  else if(f==='oval')ctx.ellipse(cx,cy,r,r*.75,0,0,Math.PI*2);
  else if(f==='pentagon'){for(let i=0;i<5;i++){const a=i*2*Math.PI/5-Math.PI/2;i===0?ctx.moveTo(cx+r*Math.cos(a),cy+r*Math.sin(a)):ctx.lineTo(cx+r*Math.cos(a),cy+r*Math.sin(a));}ctx.closePath();}
  else if(f==='ring'||f==='double'){ctx.arc(cx,cy,r,0,Math.PI*2);ctx.restore();ctx.save();ctx.strokeStyle=acc;ctx.lineWidth=bw||3;ctx.stroke();ctx.beginPath();ctx.arc(cx,cy,r*(f==='ring'?.82:.88),0,Math.PI*2);ctx.lineWidth=f==='ring'?2:(bw||3);ctx.stroke();ctx.restore();return;}
  else ctx.arc(cx,cy,r,0,Math.PI*2);
  ctx.shadowColor='rgba(0,0,0,.4)';ctx.shadowBlur=8;ctx.fillStyle=pri;ctx.fill();ctx.shadowBlur=0;ctx.strokeStyle=acc;ctx.lineWidth=bw||3;ctx.stroke();ctx.restore();
}
/* Draw arc text on thumbnail canvas (same algorithm as fabric.CircularText) */
function _thumbArcText(ctx, text, cx, cy, r, side, fontSize, color, ls){
  ls = ls || 2;
  ctx.save();
  ctx.font = 'bold ' + fontSize + 'px "Cinzel", serif';
  ctx.fillStyle = color;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'alphabetic';
  const chars = text.split('');
  const cw = chars.map(ch => Math.max(ctx.measureText(ch).width, fontSize * 0.5));
  const tw = cw.reduce((a,b)=>a+b,0) + ls*(chars.length-1);
  const ta = tw / r;
  let pos = 0;
  if (side === 'top') {
    const sa = -(ta/2);
    for (let i=0; i<chars.length; i++){
      const ca = sa + (pos + cw[i]/2) / r;
      ctx.save(); ctx.translate(cx,cy); ctx.rotate(ca); ctx.translate(0,-r); ctx.fillText(chars[i],0,0); ctx.restore();
      pos += cw[i] + ls;
    }
  } else {
    const sa = Math.PI + ta/2;
    for (let i=0; i<chars.length; i++){
      const ca = sa - (pos + cw[i]/2) / r;
      ctx.save(); ctx.translate(cx,cy); ctx.rotate(ca); ctx.translate(0,-r); ctx.rotate(Math.PI); ctx.fillText(chars[i],0,0); ctx.restore();
      pos += cw[i] + ls;
    }
  }
  ctx.restore();
}

function lspRenderTplThumb(t){
  const cv=document.getElementById('tcv_'+t.id);if(!cv)return;
  const ctx=cv.getContext('2d'),W=130,H=130,cx=65,cy=65,r=52;
  ctx.clearRect(0,0,W,H);ctx.fillStyle='#0d0717';ctx.fillRect(0,0,W,H);

  if(t.cfg.type==='badge'){
    /* Badge thumbnail: concentric rings + arc text + center symbol */
    const c=t.cfg;
    // Outer ring
    ctx.beginPath();ctx.arc(cx,cy,r,0,Math.PI*2);ctx.fillStyle=c.outer;ctx.fill();
    ctx.strokeStyle='#fff';ctx.lineWidth=2;ctx.stroke();
    // White band
    ctx.beginPath();ctx.arc(cx,cy,r*0.84,0,Math.PI*2);ctx.fillStyle='#ffffff';ctx.fill();
    // Inner ring
    ctx.beginPath();ctx.arc(cx,cy,r*0.75,0,Math.PI*2);ctx.fillStyle=c.inner;ctx.fill();
    ctx.strokeStyle='#fff';ctx.lineWidth=1.5;ctx.stroke();
    // Center white area
    ctx.beginPath();ctx.arc(cx,cy,r*0.54,0,Math.PI*2);ctx.fillStyle='#ffffff';ctx.fill();
    // Center symbol
    ctx.font=`${r*0.52}px serif`;ctx.textAlign='center';ctx.textBaseline='middle';
    ctx.fillText(c.symbol,cx,cy+1);
    // Top arc text
    _thumbArcText(ctx,c.topText||'SCHOOL NAME',cx,cy,r*0.70,'top',4.5,c.topColor,1.2);
    // Bottom arc text
    _thumbArcText(ctx,c.botText||'CITY STATE',cx,cy,r*0.70,'bottom',3.8,c.botColor,0.8);
    // Stars
    ctx.fillStyle=c.botColor;ctx.font='7px sans-serif';ctx.textAlign='center';
    ctx.fillText('★ ★ ★',cx,cy+r*0.66);
    // Label below
    ctx.font=`bold 7px 'Montserrat',sans-serif`;ctx.fillStyle='#a8b4e8';
    ctx.fillText(t.label.slice(0,20),cx,H-4);
  } else {
    lspThFrame(ctx,cx,cy,r,t.cfg.frame,t.cfg.primaryColor,t.cfg.accent,3);
    ctx.font=`${r*.65}px serif`;ctx.textAlign='center';ctx.textBaseline='middle';
    ctx.fillStyle=t.cfg.secondary;ctx.shadowColor='rgba(0,0,0,.3)';ctx.shadowBlur=3;
    ctx.fillText(t.cfg.symbol,cx,cy);ctx.shadowBlur=0;
    ctx.font=`bold 7px 'Montserrat',sans-serif`;
    ctx.fillStyle=t.cfg.accent;ctx.fillText(t.label.slice(0,18),cx,H-5);
  }
}
function lspApplyTemplate(id){
  const t=LSP_TEMPLATES.find(x=>x.id===id);if(!t)return;
  const go=()=>{
    // One undo step for the whole template (it adds ~10 objects).
    LSP._restoring=true;
    try{ if(t.cfg.type==='badge') lspApplyBadgeTemplate(t); else lspBuildLogoFromTemplate(t); }
    finally{ LSP._restoring=false; }
    lspRefreshLayers();lspTouch();lspRenderPanel(LSP.activePanel);
    lspCloseFlyout('fly-templates');
    lspShowToast('Template applied: '+t.label+' — Ctrl+Z to undo');
    lspEnsureFont(t.cfg.font||'Cinzel','bold').then(()=>lspRefreshTextMetrics());
  };
  // Applying a template replaces the canvas — ask first so work isn't lost by one tap.
  if(LSP.canvas.getObjects().length) lspConfirm('Replace your current design with “'+t.label+'”? (You can Undo.)',go);
  else go();
}

/* Apply a badge-type template — builds complete circular school badge */
function lspApplyBadgeTemplate(t){
  const c = t.cfg;
  LSP.canvas.clear();
  const cx = LSP.canvasW/2, cy = LSP.canvasH/2;
  const maxR = Math.min(LSP.canvasW, LSP.canvasH) * 0.44;
  const textR = maxR * 0.905; // text sits ON the outer colored ring

  // Background is left as the user set it (white/transparent). It used to be
  // painted with the ring colour, so the "round" badge exported as a coloured
  // square and could not be placed cleanly on an ID card.
  LSP.canvas.setBackgroundColor(LSP.isBgTransparent?'':LSP.bgColor, ()=>LSP.canvas.renderAll());

  // ─── Concentric rings (outer-to-inner) ───
  const r1 = new fabric.Circle({ left:cx,top:cy,radius:maxR,      fill:c.outer,  stroke:'#ffffff',strokeWidth:4, originX:'center',originY:'center',lspName:'Outer Ring' });
  const r2 = new fabric.Circle({ left:cx,top:cy,radius:maxR*0.818,fill:'#ffffff', stroke:'transparent',strokeWidth:0, originX:'center',originY:'center',lspName:'White Band' });
  const r3 = new fabric.Circle({ left:cx,top:cy,radius:maxR*0.785,fill:c.inner,  stroke:'#ffffff',strokeWidth:3, originX:'center',originY:'center',lspName:'Inner Ring' });
  const r4 = new fabric.Circle({ left:cx,top:cy,radius:maxR*0.59, fill:'#ffffff', stroke:c.inner,  strokeWidth:2, originX:'center',originY:'center',lspName:'Center Area' });
  [r1,r2,r3,r4].forEach(o=>LSP.canvas.add(o));

  // ─── Top arc text (school name) — bold, large, on outer ring ───
  const tFS = Math.max(16, Math.round(maxR * 0.118));
  const topText = new fabric.CircularText({
    left:cx, top:cy, text:c.topText||'YOUR SCHOOL NAME',
    radius:textR, fontSize:tFS, fontFamily:c.font||'Cinzel', fontWeight:'bold',
    fill:c.topColor||'#ffffff', side:'top', rotateOffset:0, letterSpacing:4,
    originX:'center', originY:'center', lspName:'School Name — click to edit',
  });
  topText._recalcSize(); LSP.canvas.add(topText);

  // ─── Bottom arc text (address) ───
  const bFS = Math.max(12, Math.round(maxR * 0.088));
  const botText = new fabric.CircularText({
    left:cx, top:cy, text:c.botText||'CITY · STATE · INDIA',
    radius:textR, fontSize:bFS, fontFamily:'Montserrat', fontWeight:'bold',
    fill:c.botColor||'#fbbf24', side:'bottom', rotateOffset:0, letterSpacing:3,
    originX:'center', originY:'center', lspName:'Address — click to edit',
  });
  botText._recalcSize(); LSP.canvas.add(botText);

  // ─── Stars row (inside inner ring, near bottom) ───
  const sY = cy + maxR * 0.63;
  const starSz = Math.round(maxR * 0.095);
  const starGrp = new fabric.Group(
    ['★','★','★'].map((s,i)=>new fabric.Text(s,{left:(i-1)*(starSz+6),top:0,fontSize:starSz,fill:c.botColor||'#fbbf24',originX:'center',originY:'center',selectable:false})),
    { left:cx, top:sY, originX:'center', originY:'center', lspName:'Stars' }
  );
  LSP.canvas.add(starGrp);

  // ─── Center symbol ───
  const emblSz = Math.round(maxR * 0.38);
  const emblem = new fabric.Text(c.symbol||'🎓',{
    left:cx, top:cy - maxR*0.05, fontSize:emblSz,
    originX:'center', originY:'center', lspName:'Center Symbol — click to replace',
  });
  LSP.canvas.add(emblem);

  LSP.canvas.setActiveObject(emblem);
  LSP.canvas.renderAll();
}
function lspBuildLogoFromTemplate(t){
  LSP.canvas.clear();
  const cfg=t.cfg,W=LSP.canvasW,H=LSP.canvasH,cx=W/2,cy=H/2,fr=Math.min(W,H)*.34;
  LSP.canvas.setBackgroundColor(LSP.isBgTransparent?'':(LSP.bgColor||'#ffffff'),LSP.canvas.renderAll.bind(LSP.canvas));

  // Frame
  let frameObj;
  const baseF={fill:cfg.primaryColor,stroke:cfg.accent,strokeWidth:cfg.borderW||4,originX:'center',originY:'center',left:cx,top:cy,shadow:new fabric.Shadow({color:'rgba(0,0,0,.4)',blur:cfg.shadow||12,offsetX:2,offsetY:4})};
  const f=cfg.frame;
  if(f==='circle')frameObj=new fabric.Circle({...baseF,radius:fr});
  else if(f==='hex'){const pts=[];for(let i=0;i<6;i++){const a=i*Math.PI/3-Math.PI/6;pts.push({x:fr*Math.cos(a),y:fr*Math.sin(a)});}frameObj=new fabric.Polygon(pts,{...baseF});}
  else if(f==='shield'){const s=fr;frameObj=new fabric.Path(`M 0 ${-s} L ${s*.85} ${-s*.5} L ${s*.85} ${s*.2} Q ${s*.85} ${s*.85} 0 ${s} Q ${-s*.85} ${s*.85} ${-s*.85} ${s*.2} L ${-s*.85} ${-s*.5} Z`,{...baseF});} // real shield (was a plain circle)
  else if(f==='badge'){const pts=[];const pts2=8,ir=fr*.84;for(let i=0;i<pts2*2;i++){const rd=i%2===0?fr:ir,a=i*Math.PI/pts2-Math.PI/2;pts.push({x:rd*Math.cos(a),y:rd*Math.sin(a)});}frameObj=new fabric.Polygon(pts,{...baseF});}
  else if(f==='diamond')frameObj=new fabric.Polygon([{x:0,y:-fr},{x:fr*.75,y:0},{x:0,y:fr},{x:-fr*.75,y:0}],{...baseF});
  else if(f==='star'){const pts=[];for(let i=0;i<10;i++){const r=i%2===0?fr:fr*.45,a=i*Math.PI/5-Math.PI/2;pts.push({x:r*Math.cos(a),y:r*Math.sin(a)});}frameObj=new fabric.Polygon(pts,{...baseF});}
  else frameObj=new fabric.Circle({...baseF,radius:fr});
  frameObj.lspName='Frame';

  // Inner ring decoration
  const ring=new fabric.Circle({radius:fr*.89,fill:'transparent',stroke:lspAlpha(cfg.accent,.35),strokeWidth:1.5,originX:'center',originY:'center',left:cx,top:cy,selectable:false,evented:false});
  ring.lspName='Inner Ring';

  // Symbol
  const sym=new fabric.Text(cfg.symbol,{left:cx,top:cy-fr*.08,originX:'center',originY:'center',fontSize:fr*.65,fill:cfg.secondary,shadow:new fabric.Shadow({color:'rgba(0,0,0,.2)',blur:3,offsetX:1,offsetY:1}),selectable:true});
  sym.lspName='Symbol';

  // School name below frame
  const name=new fabric.IText('School Name',{left:cx,top:cy+fr*1.22,originX:'center',originY:'center',fontFamily:cfg.font||'Montserrat',fontSize:Math.max(16,Math.min(30,fr*.27)),fill:cfg.secondary,fontWeight:'bold',textAlign:'center',charSpacing:35,shadow:new fabric.Shadow({color:'rgba(0,0,0,.2)',blur:3,offsetX:1,offsetY:1})});
  name.lspName='School Name';

  // Motto
  const motto=new fabric.IText('Your School Motto',{left:cx,top:cy+fr*1.55,originX:'center',originY:'center',fontFamily:cfg.font||'Montserrat',fontSize:Math.max(9,fr*.12),fill:cfg.accent,textAlign:'center',charSpacing:18});
  motto.lspName='Motto';

  // Inner curved text label
  const inner=new fabric.IText('SCHOOL OF EXCELLENCE',{left:cx,top:cy+fr*.52,originX:'center',originY:'center',fontFamily:cfg.font||'Montserrat',fontSize:Math.max(7,fr*.115),fill:cfg.secondary,fontWeight:'bold',textAlign:'center',charSpacing:55,opacity:.88});
  inner.lspName='Inner Text';

  const est=new fabric.Text('Est. 1985',{left:cx,top:cy+fr*.32,originX:'center',originY:'center',fontFamily:cfg.font||'Montserrat',fontSize:Math.max(7,fr*.095),fill:lspAlpha(cfg.secondary,.55),textAlign:'center'});
  est.lspName='Est. Year';

  LSP.canvas.add(frameObj,ring,inner,est,sym,name,motto);
  LSP.canvas.renderAll();
}

/* ICONS */
let _iconCat='School';
/* lspOpenIcons('Deities') always opens on that category (the Badge Builder's
   shortcut buttons used to TOGGLE the flyout shut if it was already open). */
function lspOpenIcons(cat){
  if(cat&&LSP_ICONS[cat])_iconCat=cat;
  const s=document.getElementById('iconSearch');if(s&&cat)s.value='';
  lspToggleFlyout('fly-icons',()=>{lspBuildIconCats();lspRenderIconGrid(_iconCat);},!!cat);
}
function lspBuildIconCats(){document.getElementById('icon-cats').innerHTML=Object.keys(LSP_ICONS).map(c=>`<button class="lsp-cat-btn${c===_iconCat?' active':''}" onclick="lspSelIconCat('${c.replace(/[^a-zA-Z]/g,'_')}',this)">${c}</button>`).join('');}
function lspSelIconCat(cat,btn){
  const real=Object.keys(LSP_ICONS).find(k=>k.replace(/[^a-zA-Z]/g,'_')===cat)||cat;
  _iconCat=real;
  document.querySelectorAll('#icon-cats .lsp-cat-btn').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
  lspRenderIconGrid(real);
}
function lspFilterIcons(q){
  q=(q||'').trim();
  if(!q){lspRenderIconGrid(_iconCat);return;}
  const lq=q.toLowerCase(),seen=new Set();
  const grid=document.getElementById('icon-grid');grid.style.gridTemplateColumns='';
  const icons=Object.values(LSP_ICONS).flat().filter(i=>(i.l.toLowerCase().includes(lq)||i.e.includes(q))&&!seen.has(i.e+i.l)&&seen.add(i.e+i.l));
  const flags=Object.entries(LSP_FLAG_DATA).filter(([c,f])=>f.n.toLowerCase().includes(lq)||c.toLowerCase()===lq);
  grid.innerHTML=icons.map(i=>`<div class="lsp-icon-item" onclick="lspAddIcon('${i.e}','${lspEsc(i.l)}')" title="${lspEsc(i.l)}">${i.e}</div>`).join('')
    +flags.map(([c,f])=>`<div class="lsp-icon-item" style="font-size:.55rem;font-weight:700;" onclick="lspAddFlagCanvas('${c}')" title="${lspEsc(f.n)} flag">🏳️<br>${lspEsc(f.n)}</div>`).join('')
    ||'<div style="grid-column:1/-1;text-align:center;color:var(--text-mid);padding:1.5rem;font-size:.72rem;">Nothing found</div>';
}
function lspRenderIconGrid(cat){
  const grid = document.getElementById('icon-grid');
  if(cat === 'Flags'){
    /* Render colored flag thumbnails from LSP_FLAG_DATA */
    grid.style.gridTemplateColumns = 'repeat(4,1fr)';
    grid.innerHTML = Object.entries(LSP_FLAG_DATA).map(([code,f])=>{
      let bg = '';
      if(f.v){ const p=100/f.v.length; bg=`background:linear-gradient(90deg,${f.v.map((c,i)=>`${c} ${i*p}%,${c} ${(i+1)*p}%`).join(',')})` }
      else if(f.h){ const p=100/f.h.length; bg=`background:linear-gradient(180deg,${f.h.map((c,i)=>`${c} ${i*p}%,${c} ${(i+1)*p}%`).join(',')})` }
      else{ bg=`background:${f.bg||'#666'}` }
      const inner = f.dot ? `<span style="display:block;width:36%;aspect-ratio:1;background:${f.dot};border-radius:50%;"></span>`
                  : (f.sym&&!f.cross) ? `<span style="font-size:.78rem;color:${f.sc||'#fff'};line-height:1;">${f.sym}</span>` : '';
      return `<div onclick="lspAddFlagCanvas('${code}')" title="${f.n}" style="height:38px;border-radius:4px;cursor:pointer;border:1.5px solid rgba(255,255,255,.12);${bg};display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;transition:transform .12s;" onmouseover="this.style.transform='scale(1.07)';this.style.borderColor='var(--gold)'" onmouseout="this.style.transform='scale(1)';this.style.borderColor='rgba(255,255,255,.12)'">${inner}<div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.58);font-size:.44rem;color:#fff;font-weight:700;padding:.1rem;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${f.n}</div></div>`;
    }).join('');
  } else {
    grid.style.gridTemplateColumns = '';
    grid.innerHTML=(LSP_ICONS[cat]||[]).map(i=>`<div class="lsp-icon-item" onclick="lspAddIcon('${i.e}','${lspEsc(i.l)}')" title="${lspEsc(i.l)}">${i.e}</div>`).join('');
  }
}

function lspAddIcon(emoji,label){
  // Icons are added already selected, so clicking another icon while one is
  // selected REPLACES it (handy for swapping a badge's centre symbol).
  const cur=LSP.canvas.getActiveObject();
  if(cur&&cur.type==='text'&&/Icon$|Symbol|Emblem/.test(cur.lspName||'')&&!cur.isEditing&&Array.from(cur.text||'').length<=3){
    cur.set('text',emoji);cur.lspName=(label||emoji)+' Icon';cur.initDimensions&&cur.initDimensions();cur.setCoords();
    lspTouch();lspRefreshLayers();lspShowToast('Replaced with '+emoji+' (Ctrl+Z to undo)');return;
  }
  const t=new fabric.Text(emoji,{left:LSP.canvasW/2,top:LSP.canvasH/2,originX:'center',originY:'center',fontSize:Math.round(Math.min(LSP.canvasW,LSP.canvasH)*0.16)});
  t.lspName=(label||emoji)+' Icon';LSP.canvas.add(t).setActiveObject(t);lspShowToast('Added '+emoji+' — pick another icon now to swap it');
}

/* Add a national flag as Fabric.js vector shapes */
function lspAddFlagCanvas(code){
  const f=LSP_FLAG_DATA[code]; if(!f){lspShowToast('Flag not found');return;}
  const cx=LSP.canvasW/2, cy=LSP.canvasH/2;
  const fw=Math.min(LSP.canvasW,LSP.canvasH)*0.52, fh=fw*(2/3);
  const x=cx-fw/2, y=cy-fh/2;
  const objs=[];
  const R=(rx,ry,rw,rh,fill,extra={})=>new fabric.Rect({left:rx,top:ry,width:rw,height:rh,fill,...extra,selectable:false});

  /* Background / horizontal stripes */
  if(f.h){ const n=f.h.length,sh=fh/n; f.h.forEach((c,i)=>objs.push(R(x,y+i*sh,fw,sh,c))); }
  else if(f.v){ const n=f.v.length,sw=fw/n; f.v.forEach((c,i)=>objs.push(R(x+i*sw,y,sw,fh,c))); }
  else{ objs.push(R(x,y,fw,fh,f.bg||'#888')); }

  /* Left vertical bar (Pakistan, UAE, South Africa) */
  if(f.left) objs.push(R(x,y,fw*0.22,fh,f.left));

  /* Canton (upper-left block: USA, Australia, Malaysia) */
  if(f.ct) objs.push(R(x,y,fw*0.4,fh*0.5,f.ct));

  /* Nordic / Swiss cross */
  if(f.cross){
    const cw=fw*0.115, ch=fh*0.115;
    const crossCX = f.crossX ? x+fw*f.crossX : cx;
    if(f.crossBg){
      objs.push(R(x,cy-ch*0.9,fw,ch*1.8,f.crossBg));
      objs.push(R(crossCX-cw*0.9,y,cw*1.8,fh,f.crossBg));
    }
    objs.push(R(x,cy-ch/2,fw,ch,f.cross));
    objs.push(R(crossCX-cw/2,y,cw,fh,f.cross));
  }

  /* Center dot (Japan, Bangladesh) */
  if(f.dot){
    const dx = f.dotX ? cx+fw*f.dotX : cx;
    objs.push(new fabric.Circle({left:dx,top:cy,radius:fh*0.285,fill:f.dot,originX:'center',originY:'center',selectable:false}));
  }

  /* Brazil: green bg + yellow diamond + blue circle */
  if(f.dia){
    const dp=[{x:cx,y:y+fh*0.1},{x:x+fw*0.94,y:cy},{x:cx,y:y+fh*0.9},{x:x+fw*0.06,y:cy}];
    objs.push(new fabric.Polygon(dp,{fill:f.dia,selectable:false}));
    if(f.inn) objs.push(new fabric.Circle({left:cx,top:cy,radius:fh*0.3,fill:f.inn,originX:'center',originY:'center',selectable:false}));
  }

  /* Triangle (Czech, Philippines) */
  if(f.tri){
    objs.push(new fabric.Triangle({left:x,top:cy,width:fw*0.32,height:fh,fill:f.tri,originX:'left',originY:'center',selectable:false}));
  }

  /* Symbol / text overlay */
  if(f.sym){
    const sx = cx, sy = f.ct ? cy+fh*0.12 : cy;
    objs.push(new fabric.Text(f.sym,{left:sx,top:sy,fontSize:fh*(f.symSz||0.38),fill:f.sc||'#fff',fontFamily:'sans-serif',originX:'center',originY:'center',selectable:false}));
  }

  /* Thin border */
  objs.push(R(x,y,fw,fh,'transparent',{stroke:'rgba(0,0,0,.14)',strokeWidth:1}));

  const grp=new fabric.Group(objs,{left:cx,top:cy,originX:'center',originY:'center',lspName:f.n+' Flag'});
  LSP.canvas.add(grp); LSP.canvas.setActiveObject(grp);
  LSP.canvas.renderAll(); lspSaveHistory(); lspRefreshLayers();
  lspShowToast(f.n+' flag added — resize as needed!');
}

/* FRAMES */
function lspOpenFrames(){lspToggleFlyout('fly-frames',lspBuildFrames);}
function lspBuildFrames(){
  const frames=[{id:'circle',n:'Circle'},{id:'hex',n:'Hexagon'},{id:'badge',n:'Badge Star'},{id:'shield',n:'Shield'},{id:'diamond',n:'Diamond'},{id:'star',n:'5-Star'},{id:'square',n:'Square'},{id:'rounded',n:'Rounded'},{id:'oval',n:'Oval'},{id:'ring',n:'Ring'},{id:'double',n:'Double Ring'},{id:'pentagon',n:'Pentagon'}];
  document.getElementById('frames-grid').innerHTML=frames.map(f=>`<div style="background:var(--bg-card);border:1.5px solid var(--border);border-radius:var(--r-md);cursor:pointer;padding:.5rem;text-align:center;" onclick="lspAddFrame('${f.id}')" onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border)'"><canvas width="80" height="80" id="frcv_${f.id}"></canvas><div style="font-size:.55rem;color:var(--text-mid);margin-top:.2rem;">${f.n}</div></div>`).join('');
  setTimeout(()=>frames.forEach(f=>{const cv=document.getElementById('frcv_'+f.id);if(!cv)return;const ctx=cv.getContext('2d'),W=80,H=80,cx=40,cy=40,r=30;ctx.fillStyle='#0d0717';ctx.fillRect(0,0,W,H);lspThFrame(ctx,cx,cy,r,f.id,'#1e3a8a','#fbbf24',3);}),30);
}
function lspAddFrame(type){
  const W=LSP.canvasW,H=LSP.canvasH,r=Math.min(W,H)*.3,cx=W/2,cy=H/2;
  let obj;
  const bp={fill:'transparent',stroke:'#1e3a8a',strokeWidth:6,originX:'center',originY:'center',left:cx,top:cy};
  if(type==='circle')obj=new fabric.Circle({...bp,radius:r});
  else if(type==='hex'){const pts=[];for(let i=0;i<6;i++){const a=i*Math.PI/3-Math.PI/6;pts.push({x:r*Math.cos(a),y:r*Math.sin(a)});}obj=new fabric.Polygon(pts,{...bp});}
  else if(type==='square')obj=new fabric.Rect({...bp,width:r*2,height:r*2});
  else if(type==='rounded')obj=new fabric.Rect({...bp,width:r*2,height:r*2,rx:20,ry:20});
  else if(type==='oval')obj=new fabric.Ellipse({...bp,rx:r*1.3,ry:r});
  else if(type==='diamond')obj=new fabric.Polygon([{x:0,y:-r},{x:r*.75,y:0},{x:0,y:r},{x:-r*.75,y:0}],{...bp});
  else if(type==='star'){const pts=[];for(let i=0;i<10;i++){const rd=i%2===0?r:r*.45,a=i*Math.PI/5-Math.PI/2;pts.push({x:rd*Math.cos(a),y:rd*Math.sin(a)});}obj=new fabric.Polygon(pts,{...bp});}
  else if(type==='pentagon'){const pts=[];for(let i=0;i<5;i++){const a=i*2*Math.PI/5-Math.PI/2;pts.push({x:r*Math.cos(a),y:r*Math.sin(a)});}obj=new fabric.Polygon(pts,{...bp});}
  else if(type==='ring'){const o=new fabric.Circle({...bp,radius:r,strokeWidth:6});const inn=new fabric.Circle({...bp,radius:r*.82,strokeWidth:2,stroke:'#fbbf24'});obj=new fabric.Group([o,inn],{originX:'center',originY:'center',left:cx,top:cy});}
  else if(type==='double'){const o=new fabric.Circle({...bp,radius:r});const inn=new fabric.Circle({...bp,radius:r*.88,strokeWidth:3});obj=new fabric.Group([o,inn],{originX:'center',originY:'center',left:cx,top:cy});}
  else obj=new fabric.Circle({...bp,radius:r});
  if(obj){obj.lspName=type.charAt(0).toUpperCase()+type.slice(1)+' Frame';LSP.canvas.add(obj).setActiveObject(obj);}
  lspCloseFlyout('fly-frames');
}

/* SHAPES */
function lspOpenShapes(){lspToggleFlyout('fly-shapes',lspBuildShapes);}
function lspBuildShapes(){
  const shapes=[{id:'rect',icon:'▬',n:'Rectangle'},{id:'circle',icon:'●',n:'Circle'},{id:'triangle',icon:'▲',n:'Triangle'},{id:'star5',icon:'★',n:'Star 5pt'},{id:'star6',icon:'✦',n:'Star 6pt'},{id:'hexagon',icon:'⬡',n:'Hexagon'},{id:'pentagon',icon:'⬟',n:'Pentagon'},{id:'diamond',icon:'◆',n:'Diamond'},{id:'cross',icon:'✚',n:'Cross'},{id:'line',icon:'─',n:'Line'},{id:'parallelogram',icon:'▱',n:'Parallelogram'},{id:'arrow',icon:'➤',n:'Arrow'},{id:'ellipse',icon:'⬭',n:'Ellipse'},{id:'rhombus',icon:'⬦',n:'Rhombus'},{id:'octagon',icon:'⯃',n:'Octagon'}];
  document.getElementById('shapes-grid').innerHTML=shapes.map(s=>`<div style="background:var(--bg-card);border:1.5px solid var(--border);border-radius:var(--r-sm);cursor:pointer;padding:.45rem .3rem;text-align:center;font-size:1.35rem;" onclick="lspAddShape('${s.id}')" title="${s.n}" onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border)'">${s.icon}<div style="font-size:.48rem;color:var(--text-dim);margin-top:.15rem;">${s.n}</div></div>`).join('');
}
function lspAddShape(type){
  const W=LSP.canvasW,H=LSP.canvasH,cx=W/2,cy=H/2;
  const bp={fill:LSP.currentFillColor,stroke:LSP.currentStrokeColor,strokeWidth:LSP.currentStrokeWidth,originX:'center',originY:'center',left:cx,top:cy};
  let obj;
  if(type==='rect')obj=new fabric.Rect({...bp,width:180,height:120});
  else if(type==='circle')obj=new fabric.Circle({...bp,radius:90});
  else if(type==='ellipse')obj=new fabric.Ellipse({...bp,rx:130,ry:80});
  else if(type==='triangle')obj=new fabric.Triangle({...bp,width:160,height:140});
  else if(type==='star5'){const pts=[];for(let i=0;i<10;i++){const r=i%2===0?90:40,a=i*Math.PI/5-Math.PI/2;pts.push({x:r*Math.cos(a),y:r*Math.sin(a)});}obj=new fabric.Polygon(pts,{...bp});}
  else if(type==='star6'){const pts=[];for(let i=0;i<12;i++){const r=i%2===0?90:52,a=i*Math.PI/6-Math.PI/2;pts.push({x:r*Math.cos(a),y:r*Math.sin(a)});}obj=new fabric.Polygon(pts,{...bp});}
  else if(type==='hexagon'){const pts=[];for(let i=0;i<6;i++){const a=i*Math.PI/3-Math.PI/6;pts.push({x:90*Math.cos(a),y:90*Math.sin(a)});}obj=new fabric.Polygon(pts,{...bp});}
  else if(type==='pentagon'){const pts=[];for(let i=0;i<5;i++){const a=i*2*Math.PI/5-Math.PI/2;pts.push({x:90*Math.cos(a),y:90*Math.sin(a)});}obj=new fabric.Polygon(pts,{...bp});}
  else if(type==='diamond')obj=new fabric.Polygon([{x:0,y:-90},{x:70,y:0},{x:0,y:90},{x:-70,y:0}],{...bp});
  else if(type==='rhombus')obj=new fabric.Polygon([{x:-70,y:-40},{x:70,y:-40},{x:100,y:40},{x:-100,y:40}],{...bp});
  else if(type==='parallelogram')obj=new fabric.Polygon([{x:-80,y:-40},{x:80,y:-40},{x:60,y:40},{x:-100,y:40}],{...bp});
  else if(type==='cross')obj=new fabric.Polygon([{x:-20,y:-90},{x:20,y:-90},{x:20,y:-20},{x:90,y:-20},{x:90,y:20},{x:20,y:20},{x:20,y:90},{x:-20,y:90},{x:-20,y:20},{x:-90,y:20},{x:-90,y:-20},{x:-20,y:-20}],{...bp});
  else if(type==='line')obj=new fabric.Line([0,0,180,0],{...bp,stroke:LSP.currentFillColor,strokeWidth:4,fill:''});
  else if(type==='arrow')obj=new fabric.Polygon([{x:-80,y:-20},{x:20,y:-20},{x:20,y:-50},{x:80,y:0},{x:20,y:50},{x:20,y:20},{x:-80,y:20}],{...bp});
  else if(type==='octagon'){const pts=[];for(let i=0;i<8;i++){const a=i*Math.PI/4;pts.push({x:90*Math.cos(a),y:90*Math.sin(a)});}obj=new fabric.Polygon(pts,{...bp});}
  else obj=new fabric.Rect({...bp,width:120,height:120});
  if(obj){obj.lspName=type.charAt(0).toUpperCase()+type.slice(1);LSP.canvas.add(obj).setActiveObject(obj);}
  lspCloseFlyout('fly-shapes');
}

/* COLOR HARMONY */
function lspOpenColorHarmony(){lspToggleFlyout('fly-harmony',lspBuildHarmony);}
function lspBuildHarmony(){
  document.getElementById('harmony-body').innerHTML=`
<div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin-bottom:.5rem;">Curated School Palettes</div>
${LSP_PALETTES.map(p=>`<div style="margin-bottom:.4rem;"><div style="font-size:.65rem;color:#8b9ed4;font-weight:600;margin-bottom:.15rem;">${p.name}</div><div style="display:flex;align-items:center;gap:.25rem;"><div style="display:flex;flex:1;border-radius:6px;overflow:hidden;height:26px;cursor:pointer;" onclick="lspApplyPalette(${JSON.stringify(p.colors)})">${p.colors.map(c=>`<div style="flex:1;background:${c};"></div>`).join('')}</div><button class="lsp-micro-btn" onclick="lspApplyPalette(${JSON.stringify(p.colors)})">Apply</button></div></div>`).join('')}
<div style="height:1px;background:var(--border-hi);margin:1rem 0;"></div>
<div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin-bottom:.4rem;">Generate from Base Color</div>
<div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.4rem;">
<div class="lsp-color-btn" style="background:#1e3a8a;"><input type="color" value="#1e3a8a" oninput="lspGenHarmony(this.value)"></div>
<span style="font-size:.7rem;color:#9ba8d4;font-weight:600;">Pick base → auto generate</span></div>
<div id="gen-harmony"></div>`;
}
function lspGenHarmony(hex){
  const r=parseInt(hex.slice(1,3),16)/255,g=parseInt(hex.slice(3,5),16)/255,b=parseInt(hex.slice(5,7),16)/255;
  const max=Math.max(r,g,b),min=Math.min(r,g,b);let h,s,l=(max+min)/2;
  if(max===min){h=s=0;}else{const d=max-min;s=l>.5?d/(2-max-min):d/(max+min);switch(max){case r:h=(g-b)/d+(g<b?6:0);break;case g:h=(b-r)/d+2;break;default:h=(r-g)/d+4;}h/=6;}
  const h2h=(hh,ss,ll)=>{let rr,gg,bb;if(ss===0){rr=gg=bb=ll;}else{const hue2rgb=(p,q,t)=>{if(t<0)t+=1;if(t>1)t-=1;if(t<1/6)return p+(q-p)*6*t;if(t<1/2)return q;if(t<2/3)return p+(q-p)*(2/3-t)*6;return p;};const q=ll<.5?ll*(1+ss):ll+ss-ll*ss,p=2*ll-q;rr=hue2rgb(p,q,hh+1/3);gg=hue2rgb(p,q,hh);bb=hue2rgb(p,q,hh-1/3);}return'#'+[rr,gg,bb].map(x=>Math.round(x*255).toString(16).padStart(2,'0')).join('');};
  const harmonies=[{n:'Complementary',c:[hex,h2h((h+.5)%1,s,l),h2h(h,s,l+.2),h2h(h,s,l-.2)]},{n:'Triadic',c:[hex,h2h((h+.33)%1,s,l),h2h((h+.66)%1,s,l)]},{n:'Analogous',c:[hex,h2h((h+.08)%1,s,l),h2h((h-.08+1)%1,s,l),h2h((h+.16)%1,s,l)]},{n:'Monochromatic',c:[h2h(h,s,l*.4),h2h(h,s,l*.6),hex,h2h(h,s,Math.min(l*1.4,.95))]}];
  document.getElementById('gen-harmony').innerHTML=harmonies.map(p=>`<div style="margin-bottom:.35rem;"><div style="font-size:.65rem;color:#8b9ed4;font-weight:600;margin-bottom:.12rem;">${p.n}</div><div style="display:flex;align-items:center;gap:.25rem;"><div style="display:flex;flex:1;border-radius:5px;overflow:hidden;height:22px;cursor:pointer;" onclick="lspApplyPalette(${JSON.stringify(p.c)})">${p.c.map(c=>`<div style="flex:1;background:${c};"></div>`).join('')}</div><button class="lsp-micro-btn" onclick="lspApplyPalette(${JSON.stringify(p.c)})">↑</button></div></div>`).join('');
}

/* EXPORT */
function lspOpenExport(){lspToggleFlyout('fly-export',lspBuildExportPanel);}
function lspBuildExportPanel(){
  const body=document.getElementById('export-body');if(!body)return;
  const fmt=LSP.exportFormat||'png',sc=LSP.exportScale||3;
  const big=LSP.canvasW*sc*LSP.canvasH*sc>60e6;
  body.innerHTML=`
<div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin-bottom:.5rem;">Export Format</div>
${[{fmt:'png',icon:'🖼',l:'PNG',d:'Best for logos · transparency'},{fmt:'jpg',icon:'📷',l:'JPG',d:'Small file · white background'},{fmt:'svg',icon:'📐',l:'SVG',d:'Vector, scalable'},{fmt:'webp',icon:'🌐',l:'WebP',d:'Modern web'}].map(f=>`<div style="display:flex;align-items:center;gap:.5rem;padding:.45rem .55rem;border:1.5px solid ${fmt===f.fmt?'var(--gold)':'var(--border-hi)'};border-radius:var(--r-md);cursor:pointer;background:${fmt===f.fmt?'var(--bg-hover)':'var(--bg-card)'};margin-bottom:.35rem;" onclick="LSP.exportFormat='${f.fmt}';lspBuildExportPanel()"><span style="font-size:1.2rem;">${f.icon}</span><div style="flex:1;"><div style="font-size:.74rem;font-weight:700;color:var(--text-bright);">${f.l} — ${f.d}</div></div>${fmt===f.fmt?'<i class="bi bi-check-circle-fill" style="color:var(--gold);"></i>':''}</div>`).join('')}
${fmt!=='svg'?`<div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin:.6rem 0 .35rem;">Resolution</div>
<div class="lsp-btn-group" style="margin-bottom:.4rem;">${[[1,'1× Web'],[2,'2× HD'],[3,'3× Print'],[4,'4× Ultra'],[5,'5× Max']].map(([s,l])=>`<button class="lsp-micro-btn${sc===s?' active':''}" onclick="LSP.exportScale=${s};lspBuildExportPanel()">${l}</button>`).join('')}</div>
<div style="font-size:.7rem;color:${big?'#fbbf24':'#9ba8d4'};font-weight:600;margin-bottom:.5rem;">Output: ${LSP.canvasW*sc}×${LSP.canvasH*sc}px (${fmt==='png'?'Lossless':'Quality 95%'})${big?' — very large, may be slow on phones':''}</div>`:'<div style="font-size:.66rem;color:#9ba8d4;margin:.4rem 0 .5rem;">Vector file — opens in Illustrator / Inkscape / CorelDRAW. Photos stay embedded as images.</div>'}
<div style="display:flex;gap:.6rem;margin-bottom:.6rem;flex-wrap:wrap;">
${fmt!=='svg'?`<label style="display:flex;align-items:center;gap:.3rem;font-size:.73rem;color:#9ba8d4;font-weight:600;cursor:pointer;"><input type="checkbox" id="expTight" ${LSP.exportTight?'checked':''} onchange="LSP.exportTight=this.checked"> Smart crop (trim empty space)</label>`:''}
${fmt!=='jpg'?`<label style="display:flex;align-items:center;gap:.3rem;font-size:.73rem;color:#9ba8d4;font-weight:600;cursor:pointer;"><input type="checkbox" id="expTrans" ${LSP.exportTrans||LSP.isBgTransparent?'checked':''} onchange="LSP.exportTrans=this.checked"> Transparent BG</label>`:''}
</div>
<label style="display:block;font-size:.66rem;color:#8b9ed4;font-weight:700;margin-bottom:.2rem;">File name</label>
<input type="text" id="expName" class="lsp-inp" value="${lspEsc(LSP._galleryName||'school_logo')}" style="margin-bottom:.5rem;">
<button style="width:100%;padding:.6rem;background:linear-gradient(135deg,var(--purple-lo),var(--purple));border:none;border-radius:10px;color:#fff;font-weight:700;font-size:.85rem;cursor:pointer;margin-bottom:.3rem;" onclick="lspDoExport()"><i class="bi bi-download me-1"></i>Download ${fmt.toUpperCase()}</button>
<button style="width:100%;padding:.45rem;background:var(--bg-card);border:1px solid var(--border-hi);border-radius:10px;color:var(--text-mid);font-weight:600;font-size:.75rem;cursor:pointer;margin-bottom:.3rem;" onclick="lspCopyClipboard()"><i class="bi bi-clipboard-fill me-1"></i>Copy to Clipboard</button>
<button style="width:100%;padding:.5rem;background:linear-gradient(135deg,var(--gold-lo),var(--gold));border:none;border-radius:10px;color:#000;font-weight:700;font-size:.8rem;cursor:pointer;margin-bottom:.3rem;" onclick="lspApplyToEditor()"><i class="bi bi-check-circle-fill me-1"></i>${(window.parent!==window||window.opener)?'Apply to Editor Canvas':'Apply to Canvas'}</button>
<button style="width:100%;padding:.45rem;background:var(--bg-card);border:1px solid var(--border);border-radius:10px;color:var(--text-mid);font-weight:600;font-size:.75rem;cursor:pointer;" onclick="lspSaveToGallery()"><i class="bi bi-cloud-arrow-up-fill"></i> ${LSP.currentGalleryId?'Update in My Logos':'Save to My Logos'}</button>`;
}
function lspExportFileName(ext){
  const raw=(document.getElementById('expName')?.value||LSP._galleryName||'school_logo').trim();
  return (raw.replace(/[\\/:*?"<>|]+/g,'').replace(/\s+/g,'_').slice(0,60)||'school_logo')+'.'+ext;
}
function lspDoExport(){
  const fmt=LSP.exportFormat||'png',scale=LSP.exportScale||3;
  const transBg=fmt!=='jpg'&&!!document.getElementById('expTrans')?.checked;
  const cv=LSP.canvas;
  cv.discardActiveObject();cv.requestRenderAll();
  if(fmt==='svg'){
    const origBg=cv.backgroundColor;if(transBg)cv.backgroundColor='';
    let svg;
    try{svg=cv.toSVG({width:LSP.canvasW,height:LSP.canvasH,viewBox:{x:0,y:0,width:LSP.canvasW,height:LSP.canvasH}});}
    catch(e){cv.backgroundColor=origBg;lspShowToast('SVG export failed: '+e.message);return;}
    cv.backgroundColor=origBg;
    const u=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml'}));
    lspDL(u,lspExportFileName('svg'));setTimeout(()=>URL.revokeObjectURL(u),4000);
    lspShowToast('SVG exported ✅');return;
  }
  lspShowToast('Preparing '+fmt.toUpperCase()+'…');
  setTimeout(()=>{
    let url;
    try{url=lspDataURL({format:fmt==='jpg'?'jpeg':fmt,quality:fmt==='png'?undefined:0.95,multiplier:scale,transparent:transBg});}
    catch(e){lspShowToast('Export failed — try a lower resolution');return;}
    const mime='image/'+(fmt==='jpg'?'jpeg':fmt);
    if(document.getElementById('expTight')?.checked){
      lspSmartCrop(url,mime,fmt==='png'?undefined:0.95,(u,w,h)=>{lspDL(u,lspExportFileName(fmt));lspShowToast('Exported '+w+'×'+h+'px ✅');});
    } else {
      lspDL(url,lspExportFileName(fmt));lspShowToast('Exported '+LSP.canvasW*scale+'×'+LSP.canvasH*scale+'px ✅');
    }
  },30);
}
/* Clipboard: the PNG promise is handed to ClipboardItem synchronously, which is
   what Safari/iOS require; failures now show a message instead of nothing. */
function lspCopyClipboard(){
  if(!navigator.clipboard||typeof ClipboardItem==='undefined'){lspShowToast('Copy to clipboard is not supported in this browser — use Download');return;}
  try{
    const url=lspDataURL({format:'png',multiplier:LSP.exportScale||3,transparent:!!document.getElementById('expTrans')?.checked});
    const blob=fetch(url).then(r=>r.blob());
    navigator.clipboard.write([new ClipboardItem({'image/png':blob})])
      .then(()=>lspShowToast('Copied to clipboard! ✅'))
      .catch(()=>lspShowToast('Browser blocked clipboard access — use Download'));
  }catch(e){lspShowToast('Copy failed — use Download');}
}
function lspTightCrop(url,fmt){lspSmartCrop(url,'image/'+(fmt==='jpg'?'jpeg':fmt),fmt==='png'?undefined:0.95,(u,w,h)=>{lspDL(u,lspExportFileName(fmt));lspShowToast('Smart crop: '+w+'×'+h+'px ✅');});}
function lspDL(url,name){const a=document.createElement('a');a.href=url;a.download=name;document.body.appendChild(a);a.click();a.remove();}

/* ANIMATION — previews YOUR logo (it used to always animate a fixed 🎓 demo
   badge), and can be exported as a short WebM video for WhatsApp/social. */
function lspOpenAnimation(){
  lspToggleFlyout('fly-animation',lspBuildAnimPanel);
}
let _lspAnimImg=null,_lspAnimType='fade_in',_lspAnimRaf=0;
function lspBuildAnimPanel(){
  document.getElementById('anim-body').innerHTML=`
<div id="lsp-anim-preview"><canvas id="animCv" width="560" height="360" style="width:100%;height:100%;"></canvas></div>
<div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin:.4rem 0 .35rem;">Animation Preset</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:.28rem;margin-bottom:.4rem;">
${['Fade In','Scale Up','Rotate','Bounce','Slide In','Flash','Pulse','Swing','Shimmer','Zoom Beat'].map(a=>{const id=a.toLowerCase().replace(/ /g,'_');return `<button class="lsp-anim-btn${id===_lspAnimType?' active':''}" onclick="lspPlayAnim('${id}',this)">${a}</button>`;}).join('')}
</div>
<div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.5rem;">
<span style="font-size:.7rem;color:#8b9ed4;font-weight:700;">Speed:</span>
<input type="range" class="lsp-range" min="300" max="5000" value="1500" id="animDur" oninput="document.getElementById('animDurVal').textContent=(this.value/1000).toFixed(1)+'s'">
<span id="animDurVal" style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:30px;">1.5s</span>
</div>
<button class="lsp-micro-btn" style="width:100%;padding:.45rem;" onclick="lspRecordAnim()" id="animRecBtn"><i class="bi bi-camera-reels"></i> Download as video (WebM)</button>`;
  _lspAnimImg=new Image();
  _lspAnimImg.onload=()=>lspPlayAnim(_lspAnimType,null);
  lspSmartCrop(lspDataURL({format:'png',multiplier:1,transparent:true}),'image/png',undefined,u=>{_lspAnimImg.src=u;});
}
function lspStopAnim(){if(_lspAnimRaf)cancelAnimationFrame(_lspAnimRaf);_lspAnimRaf=0;if(LSP.animTimer){clearInterval(LSP.animTimer);LSP.animTimer=null;}}
function lspDrawAnimFrame(ctx,W,H,type,t){
  ctx.clearRect(0,0,W,H);ctx.fillStyle='#080612';ctx.fillRect(0,0,W,H);
  let alpha=1,sc=1,rot=0,tx=0,ty=0;
  if(type==='fade_in')alpha=t;
  else if(type==='scale_up'){sc=Math.min(1,t*1.15);alpha=Math.min(1,t*2);}
  else if(type==='rotate')rot=t*Math.PI*2;
  else if(type==='bounce'){ty=-Math.abs(Math.sin(t*Math.PI*3))*(H*.12)*(1-t);}
  else if(type==='slide_in'){tx=(1-Math.min(1,t*1.3))*(-W*.6);alpha=Math.min(1,t*2);}
  else if(type==='flash')alpha=Math.sin(t*Math.PI*6)>0?1:.15;
  else if(type==='pulse')sc=.9+Math.sin(t*Math.PI*4)*.1;
  else if(type==='swing')rot=Math.sin(t*Math.PI*4)*.25;
  else if(type==='shimmer')alpha=.55+.45*Math.sin(t*Math.PI*8);
  else if(type==='zoom_beat')sc=.85+Math.abs(Math.sin(t*Math.PI*3))*.25;
  const img=_lspAnimImg;
  if(!img||!img.width)return;
  const k=Math.min(W*.8/img.width,H*.8/img.height);
  ctx.save();ctx.globalAlpha=Math.max(0,Math.min(1,alpha));
  ctx.translate(W/2+tx,H/2+ty);ctx.rotate(rot);ctx.scale(sc*k,sc*k);
  ctx.drawImage(img,-img.width/2,-img.height/2);
  ctx.restore();
}
function lspPlayAnim(type,btn){
  _lspAnimType=type;
  document.querySelectorAll('.lsp-anim-btn').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
  const cv=document.getElementById('animCv');if(!cv)return;
  const ctx=cv.getContext('2d');
  lspStopAnim();
  const t0=performance.now();
  const loop=now=>{
    if(!document.getElementById('fly-animation').classList.contains('open')){lspStopAnim();return;} // stop when hidden
    const dur=+(document.getElementById('animDur')?.value||1500); // speed changes apply live
    lspDrawAnimFrame(ctx,cv.width,cv.height,type,((now-t0)%dur)/dur);
    _lspAnimRaf=requestAnimationFrame(loop);
  };
  _lspAnimRaf=requestAnimationFrame(loop);
}
function lspRecordAnim(){
  const cv=document.getElementById('animCv');
  if(!cv||!cv.captureStream||typeof MediaRecorder==='undefined'){lspShowToast('Video export is not supported in this browser (try Chrome)');return;}
  const mime=['video/webm;codecs=vp9','video/webm;codecs=vp8','video/webm'].find(m=>MediaRecorder.isTypeSupported(m));
  if(!mime){lspShowToast('Video export is not supported in this browser');return;}
  const dur=+(document.getElementById('animDur')?.value||1500);
  const total=Math.max(dur*2,2000);
  const rec=new MediaRecorder(cv.captureStream(30),{mimeType:mime,videoBitsPerSecond:4e6});
  const chunks=[];rec.ondataavailable=e=>e.data.size&&chunks.push(e.data);
  rec.onstop=()=>{
    const u=URL.createObjectURL(new Blob(chunks,{type:'video/webm'}));
    lspDL(u,lspExportFileName('webm'));setTimeout(()=>URL.revokeObjectURL(u),5000);
    const b=document.getElementById('animRecBtn');if(b){b.disabled=false;b.innerHTML='<i class="bi bi-camera-reels"></i> Download as video (WebM)';}
    lspShowToast('Video downloaded ✅');
  };
  const b=document.getElementById('animRecBtn');if(b){b.disabled=true;b.innerHTML='<i class="bi bi-record-circle"></i> Recording…';}
  lspPlayAnim(_lspAnimType,document.querySelector('.lsp-anim-btn.active'));
  rec.start();setTimeout(()=>rec.state!=='inactive'&&rec.stop(),total);
}

/* ══════════════════════════════════════════════════════════════
   CIRCULAR TEXT TOOL — uses fabric.CircularText custom class
   ══════════════════════════════════════════════════════════════ */
function lspAddCurvedText(){ lspAddCircularText('top'); } // backward-compat alias

function lspAddCircularText(side){
  side = side || 'top';
  const placeholder = side === 'top' ? 'SCHOOL OF EXCELLENCE' : 'CITY · STATE · INDIA';
  lspPromptModal(side === 'top' ? 'Top Arc Text' : 'Bottom Arc Text', [
    {id:'txt', label: side === 'top' ? 'School name' : 'Address / tagline', value: placeholder},
  ], vals => {
    const txt = (vals.txt || '').trim();
    if (!txt) return;

    const cx = LSP.canvasW / 2, cy = LSP.canvasH / 2;
    const maxR = Math.min(LSP.canvasW, LSP.canvasH) * 0.37;

    const ct = new fabric.CircularText({
      left: cx, top: cy,
      text: txt,
      radius: Math.round(maxR),
      fontSize: side === 'top' ? 22 : 18,
      fontFamily: side === 'top' ? 'Cinzel' : 'Montserrat',
      fontWeight: 'bold',
      fill: side === 'top' ? '#1e3a8a' : '#7c1d1d',
      side: side,
      rotateOffset: 0,
      letterSpacing: side === 'top' ? 8 : 5,
      originX: 'center', originY: 'center',
      lspName: side === 'top' ? 'Top Arc Text' : 'Bottom Arc Text',
    });
    ct._recalcSize();
    LSP.canvas.add(ct);
    LSP.canvas.setActiveObject(ct);
    LSP.canvas.renderAll();
    lspOnSelect();
    lspEnsureFont(ct.fontFamily,'bold').then(()=>{ct._recalcSize();ct.dirty=true;LSP.canvas.requestRenderAll();});
    lspShowToast('Arc text added — edit it in the Design panel (or double-click it).');
  });
}

/* Update a circularText property live from the properties panel */
function lspUpdateCircText(prop, val){
  const obj = LSP.canvas.getActiveObject();
  if (!obj || obj.type !== 'circularText') return;
  obj.set(prop, val);
  obj.dirty = true;
  obj._recalcSize();
  LSP.canvas.requestRenderAll();
  if (prop === 'side' || prop === 'fontWeight') lspRenderPanel(LSP.activePanel);
  if (prop === 'fontFamily') lspEnsureFont(val, obj.fontWeight).then(()=>{obj._recalcSize();obj.dirty=true;LSP.canvas.requestRenderAll();});
  if (prop === 'text') lspRefreshLayers();
  lspSaveHistory();
}

/* Right-panel HTML for circularText objects */
function lspBuildCircularTextPanel(obj){
  const fonts = ['Cinzel','Montserrat','Poppins','Raleway','Oswald','Open Sans','Nunito','Exo 2','Ubuntu','Bebas Neue','Righteous','Pacifico','Lobster','Dancing Script','Georgia','Impact','Times New Roman','Arial'];
  const op = Math.round((obj.opacity??1)*100);
  const rot = Math.round(obj.angle||0);
  return `
<div class="lsp-section">
  <div class="lsp-section-title" style="color:#fbbf24;"><i class="bi bi-circle"></i> Circular Text</div>
  <div class="lsp-row"><span class="lsp-label">Text</span>
    <input type="text" class="lsp-inp" value="${lspEsc(obj.text||'')}" oninput="lspUpdateCircText('text',this.value)" style="flex:1;font-size:.72rem;">
  </div>
  <div class="lsp-row"><span class="lsp-label">Arc</span>
    <div class="lsp-btn-group">
      <button class="lsp-micro-btn ${obj.side==='top'?'active':''}" onclick="lspUpdateCircText('side','top')" style="${obj.side==='top'?'color:#38bdf8;border-color:#38bdf8;':''}">⬆ Top</button>
      <button class="lsp-micro-btn ${obj.side==='bottom'?'active':''}" onclick="lspUpdateCircText('side','bottom')" style="${obj.side==='bottom'?'color:#4ade80;border-color:#4ade80;':''}">⬇ Bottom</button>
    </div>
  </div>
</div>
<div class="lsp-section">
  <div class="lsp-section-title"><i class="bi bi-sliders"></i> Arc Controls</div>
  <div class="lsp-row"><span class="lsp-label">Radius</span>
    <input type="range" class="lsp-range" min="30" max="${Math.max(320,Math.round(Math.max(LSP.canvasW,LSP.canvasH)*0.6))}" value="${obj.radius}" oninput="lspUpdateCircText('radius',+this.value);this.nextElementSibling.textContent=this.value+'px'">
    <span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:36px;">${obj.radius}px</span>
  </div>
  <div class="lsp-row"><span class="lsp-label">Font Size</span>
    <input type="range" class="lsp-range" min="8" max="80" value="${obj.fontSize}" oninput="lspUpdateCircText('fontSize',+this.value);this.nextElementSibling.textContent=this.value+'px'">
    <span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:36px;">${obj.fontSize}px</span>
  </div>
  <div class="lsp-row"><span class="lsp-label">Spacing</span>
    <input type="range" class="lsp-range" min="-10" max="40" value="${obj.letterSpacing||0}" oninput="lspUpdateCircText('letterSpacing',+this.value);this.nextElementSibling.textContent=this.value+'px'" style="accent-color:var(--cyan);">
    <span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:36px;">${obj.letterSpacing||0}px</span>
  </div>
  <div class="lsp-row"><span class="lsp-label">Rotate</span>
    <input type="range" class="lsp-range" min="-180" max="180" value="${obj.rotateOffset||0}" oninput="lspUpdateCircText('rotateOffset',+this.value);this.nextElementSibling.textContent=this.value+'°'" style="accent-color:var(--gold);">
    <span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:36px;">${obj.rotateOffset||0}°</span>
  </div>
</div>
<div class="lsp-section">
  <div class="lsp-section-title"><i class="bi bi-fonts"></i> Font & Color</div>
  <div class="lsp-row"><span class="lsp-label">Font</span>
    <select class="lsp-select" onchange="lspUpdateCircText('fontFamily',this.value)">
      ${(fonts.includes(obj.fontFamily)?fonts:[obj.fontFamily].concat(fonts)).map(f=>`<option ${f===obj.fontFamily?'selected':''}>${lspEsc(f)}</option>`).join('')}
    </select>
  </div>
  <div class="lsp-row"><span class="lsp-label">Weight</span>
    <div class="lsp-btn-group">
      <button class="lsp-micro-btn ${obj.fontWeight==='bold'?'active':''}" onclick="lspUpdateCircText('fontWeight','bold')"><b>Bold</b></button>
      <button class="lsp-micro-btn ${obj.fontWeight!=='bold'?'active':''}" onclick="lspUpdateCircText('fontWeight','normal')">Normal</button>
    </div>
  </div>
  <div class="lsp-row"><span class="lsp-label">Color</span>
    <div class="lsp-color-btn" style="background:${lspEsc(typeof obj.fill==='string'?obj.fill:'#1e3a8a')};"><input type="color" value="${lspToHexColor(obj.fill||'#1e3a8a')}" oninput="lspUpdateCircText('fill',this.value)"></div>
    <div class="lsp-swatches">${['#fff','#fbbf24','#1e3a8a','#000','#c9a84c','#ef4444','#7c3aed','#22c55e','#c0392b'].map(c=>`<div class="lsp-swatch" style="background:${c};" onclick="lspUpdateCircText('fill','${c}')"></div>`).join('')}</div>
  </div>
</div>
<div class="lsp-section">
  <div class="lsp-section-title"><i class="bi bi-sliders"></i>Object Transform</div>
  <div class="lsp-row"><span class="lsp-label">Opacity</span>
    <input type="range" class="lsp-range" min="0" max="100" value="${op}" oninput="lspApplyOpacity(+this.value/100);this.nextElementSibling.textContent=this.value+'%'" onchange="lspSaveHistory()">
    <span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:30px;">${op}%</span>
  </div>
  <div class="lsp-row"><span class="lsp-label">Rotation</span>
    <input type="range" class="lsp-range" min="-180" max="180" value="${rot}" oninput="lspApplyRotation(+this.value);this.nextElementSibling.textContent=this.value+'°'" onchange="lspSaveHistory()" style="accent-color:var(--gold);">
    <span style="font-size:.72rem;color:#9ba8d4;font-weight:600;min-width:30px;">${rot}°</span>
  </div>
</div>
<div class="lsp-section">
  <div class="lsp-section-title"><i class="bi bi-align-center"></i>Alignment</div>
  <div class="lsp-btn-group">${[['left','⬅'],['hcenter','↔'],['right','➡'],['top','⬆'],['vcenter','↕'],['bottom','⬇']].map(([d,l])=>`<button class="lsp-micro-btn" onclick="lspAlign('${d}')">${l}</button>`).join('')}</div>
</div>`;
}

/* ══════════════════════════════════════════════════════════════
   SCHOOL BADGE BUILDER — Complete circular school logo creator
   ══════════════════════════════════════════════════════════════ */
const LSP_BADGE_PRESETS = [
  { name:'Red-Blue',      o:'#c0392b', i:'#1e3a8a', tc:'#ffffff', bc:'#fbbf24' },
  { name:'Maroon-Gold',   o:'#7c1d1d', i:'#92400e', tc:'#ffffff', bc:'#fbbf24' },
  { name:'Green-Gold',    o:'#14532d', i:'#713f12', tc:'#ffffff', bc:'#fbbf24' },
  { name:'Navy-Gold',     o:'#0f2057', i:'#7c5a00', tc:'#ffffff', bc:'#fbbf24' },
  { name:'Purple-Gold',   o:'#4c1d95', i:'#78350f', tc:'#ffffff', bc:'#fbbf24' },
  { name:'Blue-Red',      o:'#1e3a8a', i:'#9b1c1c', tc:'#ffffff', bc:'#fbbf24' },
  { name:'Saffron-Green', o:'#c2410c', i:'#15803d', tc:'#ffffff', bc:'#fbbf24' },
  { name:'Saffron-Navy',  o:'#ea580c', i:'#1e3a8a', tc:'#ffffff', bc:'#fef08a' },
  { name:'Teal-Maroon',   o:'#0f766e', i:'#7c1d1d', tc:'#ffffff', bc:'#fbbf24' },
];
LSP._badgeColors = LSP_BADGE_PRESETS[0];

function lspOpenBadgeBuilder(){
  lspToggleFlyout('fly-badge',lspBuildBadgePanel);
}

function lspBuildBadgePanel(){
  const body = document.getElementById('badge-body');
  if (!body) return;
  body.innerHTML = `
<div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin-bottom:.4rem;">Color Scheme</div>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.3rem;margin-bottom:.65rem;">
${LSP_BADGE_PRESETS.map((c,idx)=>`
  <div class="lsp-export-opt" style="flex-direction:column;padding:.35rem .4rem;gap:.22rem;cursor:pointer;${LSP._badgeColors===c?'border-color:var(--gold);':''}" onclick="LSP._badgeColors=LSP_BADGE_PRESETS[${idx}];document.querySelectorAll('#badge-body .lsp-export-opt').forEach(x=>x.style.borderColor='var(--border-hi)');this.style.borderColor='var(--gold)';lspShowToast('${c.name} colours selected');">
    <div style="display:flex;gap:3px;width:100%;">
      <div style="flex:1;height:14px;background:${c.o};border-radius:2px;"></div>
      <div style="flex:1;height:14px;background:#fff;border-radius:2px;border:1px solid #ddd;"></div>
      <div style="flex:1;height:14px;background:${c.i};border-radius:2px;"></div>
    </div>
    <div style="font-size:.6rem;color:#9ba8d4;font-weight:600;text-align:center;">${c.name}</div>
  </div>`).join('')}
</div>

<div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin-bottom:.4rem;">Badge Frame</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:.3rem;margin-bottom:.65rem;">
  <button class="lsp-micro-btn" onclick="lspAddBadgeFrame('double')" style="height:34px;font-size:.68rem;"><i class="bi bi-circle"></i> Double Ring</button>
  <button class="lsp-micro-btn" onclick="lspAddBadgeFrame('triple')" style="height:34px;font-size:.68rem;"><i class="bi bi-circle"></i> Triple Ring</button>
  <button class="lsp-micro-btn" onclick="lspAddBadgeRing('solid')" style="height:34px;font-size:.68rem;"><i class="bi bi-circle"></i> Single Ring</button>
  <button class="lsp-micro-btn" onclick="lspAddBadgeRing('dashed')" style="height:34px;font-size:.68rem;"><i class="bi bi-circle-dotted"></i> Dashed Ring</button>
</div>

<div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin-bottom:.4rem;">Circular Arc Text</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:.3rem;margin-bottom:.65rem;">
  <button class="lsp-micro-btn" onclick="lspAddCircularText('top')" style="height:36px;font-size:.68rem;color:#38bdf8;border-color:rgba(56,189,248,.4);"><i class="bi bi-type"></i> ⬆ Top Arc Text</button>
  <button class="lsp-micro-btn" onclick="lspAddCircularText('bottom')" style="height:36px;font-size:.68rem;color:#4ade80;border-color:rgba(74,222,128,.4);"><i class="bi bi-type"></i> ⬇ Bottom Arc Text</button>
</div>

<div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin-bottom:.4rem;">Center Shape</div>
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.28rem;margin-bottom:.65rem;">
  ${[['shield','🛡️'],['star5','⭐'],['circle','⚫'],['hexagon','⬡'],['diamond','◆']].map(([s,e])=>`<button class="lsp-micro-btn" onclick="lspBadgeCenterShape('${s}')" title="${s}" style="height:36px;font-size:1.1rem;">${e}</button>`).join('')}
</div>

<div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin-bottom:.3rem;">Mahapurush &amp; Deity Emblems</div>
<div style="font-size:.58rem;color:#6b7ab8;margin-bottom:.35rem;">Vector art — scales without blur. Click to add, then resize.</div>
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.28rem;margin-bottom:.45rem;">
  ${[['lotus','🪷','Lotus'],['chakra','☸','Chakra'],['charkha','🌀','Charkha'],['book','📖','Book'],['torch','🔥','Torch'],['sun','☀️','Sun'],['trishul','🔱','Trishul'],['om','ॐ','OM'],['star','⭐','Star'],['constitution','📜','Law']].map(([t,e,l])=>`<button class="lsp-micro-btn" onclick="lspAddMahapurushEmblem('${t}')" title="${l}" style="height:46px;flex-direction:column;gap:2px;display:flex;align-items:center;justify-content:center;padding:0;"><span style="font-size:1.18rem;line-height:1.1;">${e}</span><span style="font-size:.5rem;color:#9ba8d4;font-weight:700;">${l}</span></button>`).join('')}
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:.25rem;margin-bottom:.6rem;">
  <button class="lsp-micro-btn" onclick="lspOpenIcons('Mahapurush')" style="height:30px;font-size:.62rem;color:#fbbf24;">🙏 Mahapurush Icons</button>
  <button class="lsp-micro-btn" onclick="lspOpenIcons('Deities')" style="height:30px;font-size:.62rem;color:#f472b6;">🪷 Deity Icons</button>
  <button class="lsp-micro-btn" onclick="lspUploadImage()" style="height:30px;font-size:.62rem;color:#38bdf8;">📷 Upload Photo</button>
  <button class="lsp-micro-btn" onclick="lspOpenIcons('Medals & Awards')" style="height:30px;font-size:.62rem;color:#a78bfa;">🏅 Medals</button>
</div>

<div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#8b9ed4;margin-bottom:.4rem;">Decorations</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:.28rem;margin-bottom:.75rem;">
  <button class="lsp-micro-btn" onclick="lspAddLaurel('left')" style="height:34px;font-size:.68rem;">🌿 Laurel Left</button>
  <button class="lsp-micro-btn" onclick="lspAddLaurel('right')" style="height:34px;font-size:.68rem;">🌿 Laurel Right</button>
  <button class="lsp-micro-btn" onclick="lspAddLaurel('both')" style="height:34px;font-size:.68rem;">🌿 Wreath Pair</button>
  <button class="lsp-micro-btn" onclick="lspAddStarRow(3)" style="height:34px;font-size:.68rem;">★ Star Row</button>
  <button class="lsp-micro-btn" onclick="lspAddDividerLine()" style="height:34px;font-size:.68rem;">━ Divider Line</button>
  <button class="lsp-micro-btn" onclick="lspAddDotRow()" style="height:34px;font-size:.68rem;">• • • Dot Row</button>
</div>

<div style="height:1px;background:var(--border-hi);margin:0 0 .65rem;"></div>
<button onclick="lspBuildCompleteBadge()" style="width:100%;padding:.68rem;background:linear-gradient(135deg,#c0392b 0%,#7c1d1d 50%,#1e3a8a 100%);border:none;border-radius:10px;color:#fff;font-weight:800;font-size:.84rem;cursor:pointer;box-shadow:0 4px 18px rgba(192,57,43,.55);letter-spacing:.04em;transition:opacity .15s;" onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
  <i class="bi bi-magic" style="margin-right:5px;"></i> Build Complete School Badge
</button>`;
}

/* Add complete badge frame (rings only) */
function lspAddBadgeFrame(style){
  const c = LSP._badgeColors || LSP_BADGE_PRESETS[0];
  const cx = LSP.canvasW/2, cy = LSP.canvasH/2;
  const maxR = Math.min(LSP.canvasW, LSP.canvasH) * 0.42;
  const rings = [];

  if (style === 'triple'){
    rings.push(new fabric.Circle({ left:cx, top:cy, radius:maxR, fill:c.o, stroke:'#fff', strokeWidth:5, originX:'center', originY:'center', lspName:'Outer Ring' }));
    rings.push(new fabric.Circle({ left:cx, top:cy, radius:maxR*0.87, fill:'#ffffff', stroke:'transparent', strokeWidth:0, originX:'center', originY:'center', lspName:'White Band' }));
    rings.push(new fabric.Circle({ left:cx, top:cy, radius:maxR*0.79, fill:c.i, stroke:'#fff', strokeWidth:3, originX:'center', originY:'center', lspName:'Inner Ring' }));
    rings.push(new fabric.Circle({ left:cx, top:cy, radius:maxR*0.67, fill:'#fff', stroke:c.i, strokeWidth:2, originX:'center', originY:'center', lspName:'Inner Band' }));
    rings.push(new fabric.Circle({ left:cx, top:cy, radius:maxR*0.59, fill:'#fff', stroke:c.o, strokeWidth:1.5, originX:'center', originY:'center', lspName:'Center Area' }));
  } else {
    rings.push(new fabric.Circle({ left:cx, top:cy, radius:maxR, fill:c.o, stroke:'#fff', strokeWidth:5, originX:'center', originY:'center', lspName:'Outer Ring' }));
    rings.push(new fabric.Circle({ left:cx, top:cy, radius:maxR*0.85, fill:'#fff', stroke:c.o, strokeWidth:2, originX:'center', originY:'center', lspName:'Gap Band' }));
    rings.push(new fabric.Circle({ left:cx, top:cy, radius:maxR*0.77, fill:c.i, stroke:'#fff', strokeWidth:3, originX:'center', originY:'center', lspName:'Inner Ring' }));
    rings.push(new fabric.Circle({ left:cx, top:cy, radius:maxR*0.58, fill:'#fff', stroke:c.i, strokeWidth:2, originX:'center', originY:'center', lspName:'Center Area' }));
  }
  rings.forEach(o => LSP.canvas.add(o));
  LSP.canvas.renderAll(); lspSaveHistory(); lspRefreshLayers();
  lspShowToast('Badge frame added! Add arc text + center emblem next.');
}

/* Add a single ring */
function lspAddBadgeRing(style){
  const c = LSP._badgeColors || LSP_BADGE_PRESETS[0];
  const cx = LSP.canvasW/2, cy = LSP.canvasH/2;
  const r = Math.min(LSP.canvasW, LSP.canvasH) * 0.40;
  const ring = new fabric.Circle({
    left:cx, top:cy, radius:r,
    fill:'transparent', stroke:c.o, strokeWidth:7,
    strokeDashArray: style==='dashed' ? [14,7] : null,
    originX:'center', originY:'center',
    lspName: style==='dashed' ? 'Dashed Ring' : 'Circle Ring',
  });
  LSP.canvas.add(ring); LSP.canvas.setActiveObject(ring);
  LSP.canvas.renderAll(); lspSaveHistory(); lspRefreshLayers();
}

/* Center shape */
function lspBadgeCenterShape(type){
  const c = LSP._badgeColors || LSP_BADGE_PRESETS[0];
  const cx = LSP.canvasW/2, cy = LSP.canvasH/2;
  const sz = Math.min(LSP.canvasW, LSP.canvasH) * 0.22;
  let obj;

  if (type === 'circle'){
    obj = new fabric.Circle({ left:cx, top:cy, radius:sz/2, fill:c.i, stroke:c.o, strokeWidth:5, originX:'center', originY:'center', lspName:'Center Circle' });
  } else if (type === 'star5'){
    const pts = []; const or2=sz/2, ir=sz/4;
    for(let i=0;i<10;i++){ const a=(Math.PI/5)*i-Math.PI/2, rr=i%2===0?or2:ir; pts.push({x:rr*Math.cos(a),y:rr*Math.sin(a)}); }
    const path='M'+pts.map(p=>`${p.x.toFixed(2)},${p.y.toFixed(2)}`).join('L')+'Z';
    obj = new fabric.Path(path,{ left:cx, top:cy, fill:c.o, stroke:'#fff', strokeWidth:2, originX:'center', originY:'center', lspName:'Star' });
  } else if (type === 'diamond'){
    obj = new fabric.Polygon([{x:0,y:-sz/2},{x:sz/3,y:0},{x:0,y:sz/2},{x:-sz/3,y:0}],{ left:cx, top:cy, fill:c.i, stroke:c.o, strokeWidth:3, originX:'center', originY:'center', lspName:'Diamond' });
  } else if (type === 'hexagon'){
    const hp = Array.from({length:6},(_,k)=>({ x:(sz/2)*Math.cos(Math.PI/3*k-Math.PI/6), y:(sz/2)*Math.sin(Math.PI/3*k-Math.PI/6) }));
    obj = new fabric.Polygon(hp,{ left:cx, top:cy, fill:c.i, stroke:c.o, strokeWidth:3, originX:'center', originY:'center', lspName:'Hexagon' });
  } else {
    // Shield
    const sh=sz/2;
    const sp=`M 0,${-sh} L ${(sh*.85).toFixed(1)},${(-sh*.4).toFixed(1)} L ${(sh*.85).toFixed(1)},${(sh*.2).toFixed(1)} Q ${(sh*.85).toFixed(1)},${(sh*.85).toFixed(1)} 0,${sh} Q ${(-sh*.85).toFixed(1)},${(sh*.85).toFixed(1)} ${(-sh*.85).toFixed(1)},${(sh*.2).toFixed(1)} L ${(-sh*.85).toFixed(1)},${(-sh*.4).toFixed(1)} Z`;
    obj = new fabric.Path(sp,{ left:cx, top:cy, fill:c.i, stroke:c.o, strokeWidth:4, originX:'center', originY:'center', lspName:'Shield' });
  }
  LSP.canvas.add(obj); LSP.canvas.setActiveObject(obj);
  LSP.canvas.renderAll(); lspSaveHistory(); lspRefreshLayers();
  lspShowToast(`${type} added!`);
}

/* Mahapurush / Deity vector emblems — added as grouped Fabric.js objects */
function lspAddMahapurushEmblem(type){
  const cx = LSP.canvasW/2, cy = LSP.canvasH/2;
  const c  = LSP._badgeColors || LSP_BADGE_PRESETS[0];
  const col = c.bc || '#fbbf24';
  const sz  = Math.min(LSP.canvasW, LSP.canvasH) * 0.20;
  let objs = [], nm = type;

  if(type==='lotus'){
    nm='Lotus Emblem';
    for(let i=0;i<8;i++){
      const a=(i*45)*Math.PI/180;
      objs.push(new fabric.Ellipse({
        left:cx+Math.cos(a)*sz*0.28, top:cy+Math.sin(a)*sz*0.28,
        rx:sz*0.10, ry:sz*0.30, fill:col, opacity:.88,
        angle:i*45, originX:'center', originY:'center'
      }));
    }
    objs.push(new fabric.Circle({left:cx,top:cy,radius:sz*0.13,fill:'#fff',opacity:.8,originX:'center',originY:'center'}));
    objs.push(new fabric.Circle({left:cx,top:cy,radius:sz*0.085,fill:col,originX:'center',originY:'center'}));
  }
  else if(type==='chakra'){
    nm='Ashoka Chakra';
    const r=sz*0.46;
    objs.push(new fabric.Circle({left:cx,top:cy,radius:r,fill:'transparent',stroke:col,strokeWidth:sz*0.055,originX:'center',originY:'center'}));
    objs.push(new fabric.Circle({left:cx,top:cy,radius:r*0.18,fill:col,originX:'center',originY:'center'}));
    for(let i=0;i<24;i++){
      const a=(i*15)*Math.PI/180;
      objs.push(new fabric.Line([
        cx+Math.cos(a)*r*0.20, cy+Math.sin(a)*r*0.20,
        cx+Math.cos(a)*r*0.86, cy+Math.sin(a)*r*0.86
      ],{stroke:col,strokeWidth:sz*0.022}));
    }
  }
  else if(type==='charkha'){
    nm='Charkha (Gandhi Wheel)';
    const r=sz*0.44;
    objs.push(new fabric.Circle({left:cx,top:cy,radius:r,fill:'transparent',stroke:col,strokeWidth:sz*0.04,originX:'center',originY:'center'}));
    objs.push(new fabric.Circle({left:cx,top:cy,radius:sz*0.07,fill:col,originX:'center',originY:'center'}));
    for(let i=0;i<8;i++){
      const a=(i*22.5)*Math.PI/180;
      objs.push(new fabric.Line([cx,cy,cx+Math.cos(a)*r*0.88,cy+Math.sin(a)*r*0.88],{stroke:col,strokeWidth:sz*0.03}));
    }
    objs.push(new fabric.Rect({left:cx+r,top:cy,width:sz*0.40,height:sz*0.055,fill:col,rx:2,ry:2,originX:'left',originY:'center'}));
    objs.push(new fabric.Circle({left:cx+r+sz*0.40,top:cy,radius:sz*0.065,fill:col,originX:'center',originY:'center'}));
  }
  else if(type==='book'){
    nm='Open Book Emblem';
    const bw=sz*0.88, bh=sz*0.68;
    objs.push(new fabric.Path(`M ${cx-bw*0.02} ${cy-bh/2} L ${cx-bw*0.5} ${cy-bh/2} C ${cx-bw*0.58} ${cy} ${cx-bw*0.5} ${cy+bh/2} ${cx-bw*0.02} ${cy+bh/2} Z`,{fill:col,opacity:.92}));
    objs.push(new fabric.Path(`M ${cx+bw*0.02} ${cy-bh/2} L ${cx+bw*0.5} ${cy-bh/2} C ${cx+bw*0.58} ${cy} ${cx+bw*0.5} ${cy+bh/2} ${cx+bw*0.02} ${cy+bh/2} Z`,{fill:col,opacity:.92}));
    objs.push(new fabric.Rect({left:cx,top:cy,width:sz*0.045,height:bh,fill:'#fff',opacity:.5,originX:'center',originY:'center'}));
    for(let i=0;i<3;i++){
      const ly=cy-bh*0.22+i*bh*0.22;
      objs.push(new fabric.Rect({left:cx-bw*0.24,top:ly,width:bw*0.20,height:sz*0.024,fill:'#fff',opacity:.45,originX:'center',originY:'center'}));
      objs.push(new fabric.Rect({left:cx+bw*0.24,top:ly,width:bw*0.20,height:sz*0.024,fill:'#fff',opacity:.45,originX:'center',originY:'center'}));
    }
  }
  else if(type==='torch'){
    nm='Torch of Freedom';
    const hs=sz*0.92;
    objs.push(new fabric.Path(`M ${cx} ${cy-hs*0.45} C ${cx-sz*0.38} ${cy+hs*0.02} ${cx-sz*0.22} ${cy+hs*0.18} ${cx} ${cy+hs*0.13} C ${cx+sz*0.22} ${cy+hs*0.18} ${cx+sz*0.38} ${cy+hs*0.02} ${cx} ${cy-hs*0.45} Z`,{fill:'#f59e0b'}));
    objs.push(new fabric.Path(`M ${cx} ${cy-hs*0.35} C ${cx-sz*0.16} ${cy+hs*0.05} ${cx-sz*0.09} ${cy+hs*0.12} ${cx} ${cy+hs*0.10} C ${cx+sz*0.09} ${cy+hs*0.12} ${cx+sz*0.16} ${cy+hs*0.05} ${cx} ${cy-hs*0.35} Z`,{fill:'#fef3c7'}));
    objs.push(new fabric.Rect({left:cx,top:cy+hs*0.12,width:sz*0.14,height:hs*0.50,fill:col,rx:3,ry:3,originX:'center',originY:'top'}));
    for(let i=0;i<3;i++) objs.push(new fabric.Rect({left:cx,top:cy+hs*0.20+i*hs*0.13,width:sz*0.19,height:sz*0.024,fill:'#fff',opacity:.38,originX:'center',originY:'center'}));
  }
  else if(type==='sun'){
    nm='Rising Sun Emblem';
    const sr=sz*0.29;
    objs.push(new fabric.Circle({left:cx,top:cy,radius:sr,fill:col,originX:'center',originY:'center'}));
    for(let i=0;i<12;i++){
      const a=(i*30)*Math.PI/180;
      objs.push(new fabric.Line([cx+Math.cos(a)*sr*1.28,cy+Math.sin(a)*sr*1.28,cx+Math.cos(a)*sr*1.72,cy+Math.sin(a)*sr*1.72],{stroke:col,strokeWidth:sz*0.048}));
    }
  }
  else if(type==='trishul'){
    nm='Trishul Emblem';
    const hs=sz*0.90;
    objs.push(new fabric.Rect({left:cx,top:cy-hs*0.20,width:sz*0.075,height:hs*0.82,fill:col,rx:2,ry:2,originX:'center',originY:'center'}));
    objs.push(new fabric.Path(`M ${cx} ${cy-hs*0.46} L ${cx-sz*0.065} ${cy-hs*0.18} L ${cx+sz*0.065} ${cy-hs*0.18} Z`,{fill:col}));
    objs.push(new fabric.Path(`M ${cx-sz*0.155} ${cy-hs*0.38} L ${cx-sz*0.215} ${cy-hs*0.17} L ${cx-sz*0.095} ${cy-hs*0.17} Z`,{fill:col}));
    objs.push(new fabric.Path(`M ${cx+sz*0.155} ${cy-hs*0.38} L ${cx+sz*0.095} ${cy-hs*0.17} L ${cx+sz*0.215} ${cy-hs*0.17} Z`,{fill:col}));
    objs.push(new fabric.Rect({left:cx,top:cy-hs*0.175,width:sz*0.44,height:sz*0.044,fill:col,rx:2,ry:2,originX:'center',originY:'center'}));
  }
  else if(type==='om'){
    const t=new fabric.Text('ॐ',{left:cx,top:cy,fontSize:sz*1.15,fill:col,fontFamily:'serif',originX:'center',originY:'center',lspName:'OM Symbol'});
    LSP.canvas.add(t);LSP.canvas.setActiveObject(t);
    LSP.canvas.renderAll();lspSaveHistory();lspRefreshLayers();
    lspShowToast('OM symbol added! Double-click to edit.');return;
  }
  else if(type==='star'){
    nm='Star Emblem';
    const pts=[];
    for(let i=0;i<10;i++){const a=(i*36-90)*Math.PI/180,r=i%2===0?sz*0.47:sz*0.20;pts.push((cx+Math.cos(a)*r).toFixed(1)+','+(cy+Math.sin(a)*r).toFixed(1));}
    objs.push(new fabric.Path('M '+pts.join(' L ')+' Z',{fill:col}));
  }
  else if(type==='constitution'){
    nm='Constitution of India';
    const bw=sz*0.55,bh=sz*0.72;
    objs.push(new fabric.Rect({left:cx,top:cy,width:bw,height:bh,fill:col,rx:4,ry:4,originX:'center',originY:'center'}));
    objs.push(new fabric.Rect({left:cx-bw*0.43,top:cy,width:sz*0.055,height:bh,fill:'#fff',opacity:.22,originX:'center',originY:'center'}));
    for(let i=0;i<5;i++) objs.push(new fabric.Rect({left:cx,top:cy-bh*0.30+i*bh*0.15,width:bw*0.65,height:sz*0.024,fill:'#fff',opacity:.40,originX:'center',originY:'center'}));
    objs.push(new fabric.Text('INDIA',{left:cx,top:cy+bh*0.34,fontSize:sz*0.13,fill:'#fff',fontWeight:'bold',fontFamily:'Montserrat',originX:'center',originY:'center'}));
  }

  if(objs.length){
    const grp=new fabric.Group(objs,{lspName:nm,originX:'center',originY:'center'});
    grp.set({left:cx,top:cy});
    LSP.canvas.add(grp);LSP.canvas.setActiveObject(grp);
    LSP.canvas.renderAll();lspSaveHistory();lspRefreshLayers();
    lspShowToast(nm+' added — resize/rotate as needed!');
  }
}

/* Laurel wreath — programmatic ellipse leaves arranged in arc */
function lspAddLaurel(side){
  const cx = LSP.canvasW/2, cy = LSP.canvasH/2;
  const arcR = Math.min(LSP.canvasW, LSP.canvasH) * 0.29;
  const leafCount = 10;

  function makeSideLeaves(isLeft){
    const leaves = [];
    const startDeg = isLeft ? 115 : 65;
    const sweepDeg = isLeft ? 115 : -115; // how many degrees the wreath spans

    for(let i=0; i<leafCount; i++){
      const t = i / (leafCount-1);
      const deg = startDeg + t * sweepDeg;
      const rad = deg * Math.PI / 180;
      const lx = (arcR - 12) * Math.cos(rad);
      const ly = (arcR - 12) * Math.sin(rad);
      // leaf angle: tangent to the arc
      const tangDeg = isLeft ? (deg + 90) : (deg - 90);

      // alternate leaf style for visual variety
      const isAlt = i % 2 === 1;
      leaves.push(new fabric.Ellipse({
        left: lx, top: ly,
        rx: isAlt ? 16 : 13, ry: isAlt ? 7 : 5.5,
        fill: isAlt ? '#166534' : '#15803d',
        stroke: '#14532d', strokeWidth: 0.5,
        angle: tangDeg,
        originX:'center', originY:'center',
        selectable: false,
      }));
    }

    // Stem (thin circle segment)
    leaves.push(new fabric.Circle({
      left: 0, top: 0,
      radius: arcR - 14,
      fill: 'transparent', stroke: '#92400e', strokeWidth: 2,
      originX: 'center', originY: 'center',
      selectable: false,
    }));

    return leaves;
  }

  const allLeaves = [];
  if (side === 'left' || side === 'both') allLeaves.push(...makeSideLeaves(true));
  if (side === 'right' || side === 'both') allLeaves.push(...makeSideLeaves(false));

  const group = new fabric.Group(allLeaves, {
    left: cx, top: cy,
    originX:'center', originY:'center',
    lspName: side === 'both' ? 'Laurel Wreath Pair' : `Laurel ${side.charAt(0).toUpperCase()+side.slice(1)}`,
  });
  LSP.canvas.add(group); LSP.canvas.setActiveObject(group);
  LSP.canvas.renderAll(); lspSaveHistory(); lspRefreshLayers();
  lspShowToast('Laurel wreath added!');
}

/* Stars row (circular, placed inside badge near bottom of inner ring) */
function lspAddStarRow(count){
  const c = LSP._badgeColors || LSP_BADGE_PRESETS[0];
  const cx = LSP.canvasW/2;
  const r = Math.min(LSP.canvasW, LSP.canvasH) * 0.30;
  const yPos = LSP.canvasH/2 + r * 0.82;
  const spacing = 22;
  const startX = -(count-1)*spacing/2;

  const stars = Array.from({length:count},(_,i)=>new fabric.Text('★',{
    left: startX + i*spacing, top: 0,
    fontSize: 18, fill: c.bc||'#fbbf24',
    fontFamily:'Arial', originX:'center', originY:'center', selectable:false,
  }));
  const grp = new fabric.Group(stars,{ left:cx, top:yPos, originX:'center', originY:'center', lspName:'Stars Row' });
  LSP.canvas.add(grp); LSP.canvas.setActiveObject(grp);
  LSP.canvas.renderAll(); lspSaveHistory(); lspRefreshLayers();
}

/* Horizontal divider line */
function lspAddDividerLine(){
  const c = LSP._badgeColors || LSP_BADGE_PRESETS[0];
  const cx = LSP.canvasW/2, cy = LSP.canvasH/2;
  const w = Math.min(LSP.canvasW, LSP.canvasH) * 0.52;
  const yPos = cy + Math.min(LSP.canvasW, LSP.canvasH)*0.29;
  const line = new fabric.Line([cx-w/2, yPos, cx+w/2, yPos],{
    stroke: c.tc||'#ffffff', strokeWidth:2.5,
    originX:'center', originY:'center', lspName:'Divider',
  });
  LSP.canvas.add(line); LSP.canvas.setActiveObject(line);
  LSP.canvas.renderAll(); lspSaveHistory(); lspRefreshLayers();
}

/* Dot divider */
function lspAddDotRow(){
  const c = LSP._badgeColors || LSP_BADGE_PRESETS[0];
  const yPos = LSP.canvasH/2 + Math.min(LSP.canvasW,LSP.canvasH)*0.27;
  const t = new fabric.Text('• • • • •',{
    left:LSP.canvasW/2, top:yPos,
    fontSize:15, fill:c.bc||'#fbbf24', fontWeight:'bold',
    fontFamily:'Montserrat', originX:'center', originY:'center', lspName:'Dot Divider',
  });
  LSP.canvas.add(t); LSP.canvas.setActiveObject(t);
  LSP.canvas.renderAll(); lspSaveHistory(); lspRefreshLayers();
}

/* ══════════════════════════════════════════════════
   ONE-CLICK: Build complete school badge
   ══════════════════════════════════════════════════ */
function lspBuildCompleteBadge(){
  const go=()=>{
    LSP._restoring=true;
    try{ LSP.canvas.clear(); _lspBuildCompleteBadge(); } finally { LSP._restoring=false; }
    lspRefreshLayers();lspTouch();lspRenderPanel(LSP.activePanel);
    lspCloseFlyout('fly-badge');
    lspShowToast('🏫 School badge built! Double-click the arc text to edit name & address.');
    lspEnsureFont('Cinzel','bold').then(()=>lspRefreshTextMetrics());
  };
  if(LSP.canvas.getObjects().length) lspConfirm('Replace the current design with a new complete badge? (You can Undo.)',go);
  else go();
}
function _lspBuildCompleteBadge(){
  const c = LSP._badgeColors || LSP_BADGE_PRESETS[0];
  const cx = LSP.canvasW/2, cy = LSP.canvasH/2;
  const maxR = Math.min(LSP.canvasW, LSP.canvasH) * 0.44;
  const textR = maxR * 0.905; // text ON the outer colored ring

  // Keep the user's background (painting the whole square with the ring colour
  // made the round badge export as a coloured square).
  LSP.canvas.setBackgroundColor(LSP.isBgTransparent?'':LSP.bgColor, () => LSP.canvas.renderAll());

  // ─── Rings (outer-to-inner) ───
  const r1 = new fabric.Circle({ left:cx, top:cy, radius:maxR,       fill:c.o,      stroke:'#ffffff', strokeWidth:4, originX:'center', originY:'center', lspName:'Outer Ring' });
  const r2 = new fabric.Circle({ left:cx, top:cy, radius:maxR*0.818, fill:'#ffffff', stroke:'transparent', strokeWidth:0, originX:'center', originY:'center', lspName:'White Band' });
  const r3 = new fabric.Circle({ left:cx, top:cy, radius:maxR*0.785, fill:c.i,      stroke:'#ffffff', strokeWidth:3, originX:'center', originY:'center', lspName:'Inner Ring' });
  const r4 = new fabric.Circle({ left:cx, top:cy, radius:maxR*0.59,  fill:'#ffffff', stroke:c.i,      strokeWidth:2, originX:'center', originY:'center', lspName:'Center Area' });
  [r1,r2,r3,r4].forEach(o => LSP.canvas.add(o));

  // ─── Top arc text: SCHOOL NAME — bold, large, white on outer ring ───
  const tFS = Math.max(16, Math.round(maxR * 0.118));
  const topText = new fabric.CircularText({
    left:cx, top:cy, text:'YOUR SCHOOL NAME',
    radius:textR, fontSize:tFS, fontFamily:'Cinzel', fontWeight:'bold',
    fill:'#ffffff', side:'top', rotateOffset:0, letterSpacing:4,
    originX:'center', originY:'center', lspName:'School Name (Top Arc)',
  });
  topText._recalcSize();
  LSP.canvas.add(topText);

  // ─── Bottom arc text: ADDRESS ───
  const bFS = Math.max(12, Math.round(maxR * 0.088));
  const botText = new fabric.CircularText({
    left:cx, top:cy, text:'CITY · STATE · INDIA',
    radius:textR, fontSize:bFS, fontFamily:'Montserrat', fontWeight:'bold',
    fill:c.bc||'#fbbf24', side:'bottom', rotateOffset:0, letterSpacing:3,
    originX:'center', originY:'center', lspName:'Address (Bottom Arc)',
  });
  botText._recalcSize();
  LSP.canvas.add(botText);

  // ─── Stars (inside inner ring, near bottom) ───
  const starY  = cy + maxR * 0.63;
  const starSz = Math.round(maxR * 0.095);
  const stars = new fabric.Group(
    ['★','★','★'].map((s,i)=>new fabric.Text(s,{left:(i-1)*(starSz+6),top:0,fontSize:starSz,fill:c.bc||'#fbbf24',originX:'center',originY:'center',selectable:false})),
    { left:cx, top:starY, originX:'center', originY:'center', lspName:'Stars' }
  );
  LSP.canvas.add(stars);

  // ─── Center emblem ───
  const emblSz = Math.round(maxR * 0.38);
  const emblem = new fabric.Text('🎓',{
    left:cx, top:cy - maxR*0.05, fontSize:emblSz,
    originX:'center', originY:'center', lspName:'Center Emblem (click to edit)',
  });
  LSP.canvas.add(emblem);

  LSP.canvas.setActiveObject(emblem);
  LSP.canvas.renderAll();
}

/* SAVE TO GALLERY */
// A brand-new gallery item saved while offline has no real id yet until the
// queued write actually reaches the server — block re-saving until then, so
// a second save can't create a duplicate design (an update to an already-
// saved item, LSP.currentGalleryId already set, has no such risk).
let _lspGalSavePending=false, _lspGalSaveQueueId=null, _lspSaving=false;
window.addEventListener('sszone-queue-synced',(e)=>{
  if(!e.detail||e.detail.panel!=='logo_designer'||e.detail.id!==_lspGalSaveQueueId)return;
  _lspGalSavePending=false; _lspGalSaveQueueId=null;
  const result=e.detail.result;
  if(result&&result.success&&result.gallery_id){LSP.currentGalleryId=+result.gallery_id; lspBuildExportPanel(); lspMarkClean(); lspClearDraft();}
});
/* Network helper: offline-queue's smartFetch when present, plain fetch otherwise
   (if that script failed to load, Save used to throw and do nothing). */
function lspPost(url,fd,opts){
  if(typeof window.smartFetch==='function')return window.smartFetch(url,fd,opts);
  return fetch(url,{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json());
}
function lspGet(url){
  const p=typeof window.idmFetch==='function'?idmFetch(url,{},20000):fetch(url,{credentials:'same-origin'});
  return p.then(r=>r.json());
}
function lspSaveToGallery(){
  if(_lspSaving){lspShowToast('Already saving…');return;}
  if(_lspGalSavePending&&!LSP.currentGalleryId){lspShowToast('Still syncing your last save — please wait',3000);return;}
  if(!LSP.canvas.getObjects().length){lspShowToast('Canvas is empty — add something first');return;}
  lspPromptModal(LSP.currentGalleryId?'Update saved logo':'Save to My Logos',[
    {id:'name',label:'Logo name',value:LSP._galleryName||'My Logo Design'},
  ],vals=>{
    const displayName=((vals.name||'').trim()||'My Logo Design').slice(0,100);
    LSP._galleryName=displayName;
    const wasNew=!LSP.currentGalleryId;
    const csrf=document.querySelector('meta[name=csrf]')?.content||'';
    if(!csrf){lspShowToast('Session expired — please refresh page');return;}

    lspShowToast('Saving…');
    const canvasJson=lspSnapshot();
    // Thumbnail ≤ 400px on the long side (zoom-independent) — keeps the DB row small.
    const th=Math.min(1,400/Math.max(LSP.canvasW,LSP.canvasH));
    let thumbnail='';try{thumbnail=lspDataURL({format:'jpeg',quality:.82,multiplier:th});}catch(e){}

    const fd=new FormData();
    fd.append('action','save');
    fd.append('name',displayName);
    fd.append('canvas_json',canvasJson);
    fd.append('thumbnail_data',thumbnail);
    fd.append('canvas_width',LSP.canvasW);
    fd.append('canvas_height',LSP.canvasH);
    fd.append('gallery_id',LSP.currentGalleryId||0);
    fd.append('csrf_token',csrf);
    _lspSaving=true;
    const btn=document.getElementById('btnSaveGallery');if(btn)btn.disabled=true;

    lspPost('../api/gallery_actions.php',fd,{label:'Save logo: '+displayName,panel:'logo_designer',timeoutMs:30000})
      .then(d=>{
        if(d&&d.queued){
          if(wasNew){ _lspGalSavePending=true; _lspGalSaveQueueId=d.queueId; }
          lspShowToast(d.message||'Saved offline — will sync automatically',3000);
        } else if(d&&d.success){
          if(d.gallery_id)LSP.currentGalleryId=+d.gallery_id;
          lspMarkClean();lspClearDraft();
          lspShowToast(d.message||'Saved! ✅',3000);
          lspBuildExportPanel(); // refresh button label to "Update"
        }else{lspShowToast('Save failed: '+((d&&d.message)||'error'),3500);}
      })
      .catch(()=>{lspShowToast('Save error — check connection',3000);})
      .finally(()=>{_lspSaving=false;if(btn)btn.disabled=false;});
  });
}

/* MY GALLERY */
function lspOpenGallery(){
  lspToggleFlyout('fly-gallery',()=>lspLoadGallery());
}
function lspLoadGallery(page){
  page=page||0;
  const body=document.getElementById('gallery-body');
  if(!page)body.innerHTML='<div style="text-align:center;color:var(--text-dim);padding:2rem;font-size:.75rem;">Loading…</div>';
  document.getElementById('gal-more')?.remove();
  lspGet('../api/gallery_actions.php?action=list&kind=logo&limit=30&page='+page)
    .then(d=>{
      if(!d||!d.success){body.innerHTML='<div style="text-align:center;color:var(--red);padding:2rem;font-size:.75rem;">Could not load gallery.<br><button class="lsp-micro-btn" style="margin-top:.5rem;" onclick="lspLoadGallery()">Retry</button></div>';return;}
      const items=d.items||[];
      if(!page&&!items.length){body.innerHTML='<div style="text-align:center;color:var(--text-dim);padding:2rem;font-size:.75rem;">No saved logos yet.<br>Create a logo and click Save!</div>';return;}
      const cards=items.map(it=>{
        const cur=+it.id===+LSP.currentGalleryId;
        const thumb=/^(data:image\/|https?:\/\/|\/)/.test(it.thumbnail||'')?it.thumbnail:'';
        return `<div style="background:var(--bg-card);border:1.5px solid ${cur?'var(--purple)':'var(--border)'};border-radius:var(--r-md);cursor:pointer;overflow:hidden;transition:all .15s;" onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='${cur?'var(--purple)':'var(--border)'}'" onclick="lspLoadGalleryItem(${+it.id})" title="${lspEsc(it.name)}">`
        +(thumb?`<img src="${lspEsc(thumb)}" alt="" style="width:100%;height:100px;object-fit:contain;background:#0d0a1a;" loading="lazy">`:'<div style="width:100%;height:100px;background:var(--bg-deep);display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:1.5rem;">⬡</div>')
        +`<div style="padding:.35rem .4rem;font-size:.6rem;color:var(--text-mid);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${cur?'✏️ ':''}${lspEsc(it.name)}</div></div>`;
      }).join('');
      if(!page)body.innerHTML='<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;" id="gal-grid">'+cards+'</div>';
      else{const g=document.getElementById('gal-grid');if(g)g.insertAdjacentHTML('beforeend',cards);}
      if(d.has_more)body.insertAdjacentHTML('beforeend',`<button id="gal-more" class="lsp-micro-btn" style="width:100%;margin-top:.5rem;" onclick="lspLoadGallery(${page+1})">Load more…</button>`);
    })
    .catch(()=>{if(!page)body.innerHTML='<div style="text-align:center;color:var(--red);padding:2rem;font-size:.75rem;">Could not load gallery.<br><button class="lsp-micro-btn" style="margin-top:.5rem;" onclick="lspLoadGallery()">Retry</button></div>';else lspShowToast('Could not load more');});
}
function lspLoadGalleryItem(id){
  const load=()=>{
    lspShowToast('Opening…');
    lspGet('../api/gallery_actions.php?action=get&id='+encodeURIComponent(id))
      .then(d=>{
        if(!d||!d.success||!d.item?.canvas_json){lspShowToast('Could not load design');return;}
        const item=d.item;
        let json=item.canvas_json;
        try{const o=typeof json==='string'?JSON.parse(json):json;
          if(!o._lsp)o._lsp={w:+item.canvas_width||500,h:+item.canvas_height||500,bg:o.background||'#ffffff'};
          json=JSON.stringify(o);}catch(e){lspShowToast('This design file is damaged');return;}
        LSP.currentGalleryId=+item.id||0; // enable update-in-place on next save
        LSP._galleryName=item.name||'My Design';
        lspRestore(json,()=>{
          LSP.history=[];LSP.histIdx=-1;_commitHistory();lspUpdateUndoRedo();
          lspMarkClean();lspClearDraft();
          if(document.fonts&&document.fonts.ready)document.fonts.ready.then(()=>lspRefreshTextMetrics());
        });
        lspCloseFlyout('fly-gallery');
        lspShowToast('Loaded: '+item.name);
      })
      .catch(()=>lspShowToast('Load error — check connection'));
  };
  if(LSP.dirty&&LSP.canvas.getObjects().length)lspConfirm('You have unsaved changes. Open the saved logo anyway?',load);
  else load();
}

/* APPLY TO EDITOR */
function lspApplyToEditor(){
  if(!LSP.canvas.getObjects().length){lspShowToast('Canvas is empty — design a logo first');return;}
  LSP.canvas.discardActiveObject();LSP.canvas.requestRenderAll();
  const transBg=document.getElementById('expTrans')?document.getElementById('expTrans').checked:(LSP.exportTrans||LSP.isBgTransparent);
  let dataURL;
  try{dataURL=lspDataURL({format:'png',multiplier:3,transparent:!!transBg});}
  catch(e){lspShowToast('Could not render the logo');return;}
  const btn=document.getElementById('btnApplyEditor');if(btn)btn.disabled=true;
  lspShowToast('Preparing logo…');
  lspSmartCrop(dataURL,'image/png',undefined,(cropURL)=>{
    if(btn)btn.disabled=false;
    const inIframe=(window.parent&&window.parent!==window);
    let opener=null;try{opener=window.opener&&!window.opener.closed?window.opener:null;}catch(e){opener=null;}
    // Only ever hand the image to our own site (postMessage '*' would give it to any page).
    const origin=location.origin;
    if(inIframe){
      window.parent.postMessage({type:'lspApply',dataURL:cropURL},origin);
      lspMarkClean();
      lspShowToast('Applying to editor canvas… ✅');
    } else if(opener){
      let direct=false;
      try{ direct=!!(opener.canvas&&opener.fabric); }catch(e){ direct=false; } // cross-origin opener throws
      if(direct){
        const fEl=new Image();fEl.onload=()=>{
          try{
            const op=opener,fc=op.canvas,cW=op.canvasW||856,cH=op.canvasH||540;
            const fImg=new op.fabric.Image(fEl);
            const sc=Math.min((cW*.42)/fEl.width,(cH*.42)/fEl.height,1);
            fImg.scale(sc).set({left:cW/2,top:cH/2,originX:'center',originY:'center'});
            fc.add(fImg).setActiveObject(fImg).renderAll();
            if(typeof op.histSave==='function')op.histSave();
            if(typeof op.showToast==='function')op.showToast('Logo applied! 🎉');
            lspMarkClean();lspShowToast('Applied! Closing…');
            setTimeout(()=>window.close(),900);
          }catch(err){
            opener.postMessage({type:'lspApply',dataURL:cropURL},origin);
            lspShowToast('Sent to editor ✅');
          }
        };fEl.src=cropURL;
      } else {
        try{opener.postMessage({type:'lspApply',dataURL:cropURL},origin);}catch(e){}
        lspMarkClean();lspShowToast('Sent to editor ✅');
        setTimeout(()=>window.close(),1200);
      }
    } else {
      lspDL(cropURL,lspExportFileName('png'));
      lspShowToast('Downloaded! Import it in the editor with Upload Image.');
    }
  });
}

/* FLYOUT MANAGEMENT — one flyout at a time (they all open at the same spot and
   used to stack on top of each other), toggled by its own button. */
function lspToggleFlyout(id,build,forceOpen){
  const el=document.getElementById(id);if(!el)return;
  const wasOpen=el.classList.contains('open');
  lspClosePanel();
  if(wasOpen&&!forceOpen){lspCloseFlyout(id);return;}
  if(LSP.activeTool==='draw'&&id!=='fly-draw')lspExitDraw();
  document.querySelectorAll('.lsp-flyout.open').forEach(f=>{if(f.id!==id)f.classList.remove('open');});
  el.classList.add('open');
  if(build)build();
}
function lspCloseFlyout(id){
  document.getElementById(id)?.classList.remove('open');
  if(id==='fly-animation')lspStopAnim();
}
function lspCloseAllFlyouts(){document.querySelectorAll('.lsp-flyout.open').forEach(f=>lspCloseFlyout(f.id));}

/* KEYBOARD */
function lspModalOpen(){
  return ['lspConfirmOv','lspPromptOv'].some(id=>document.getElementById(id)?.style.display==='flex');
}
function lspNudge(dx,dy){
  const o=LSP.canvas.getActiveObject();if(!o||o.lockMovementX)return;
  o.left+=dx;o.top+=dy;o.setCoords();LSP.canvas.requestRenderAll();
  clearTimeout(lspNudge._t);lspNudge._t=setTimeout(()=>{lspSaveHistory();lspRenderPanelSoon();},350);
}
function lspInitKeyboard(){
  document.addEventListener('keydown',(e)=>{
    // Dialogs: Enter = OK, Escape = Cancel; nothing else reaches the canvas.
    if(document.getElementById('lspPromptOv')?.style.display==='flex'){if(e.key==='Escape'){e.preventDefault();_lspPromptAnswer(false);}return;}
    if(document.getElementById('lspConfirmOv')?.style.display==='flex'){
      if(e.key==='Escape'){e.preventDefault();_lspConfirmAnswer(false);}
      else if(e.key==='Enter'){e.preventDefault();_lspConfirmAnswer(true);}
      return;
    }
    const tag=e.target.tagName;
    if(tag==='INPUT'||tag==='TEXTAREA'||tag==='SELECT'||e.target.isContentEditable)return;
    if(LSP.canvas.getActiveObject()?.isEditing)return;
    const k=(e.key||'').toLowerCase();
    if(e.ctrlKey||e.metaKey){
      if(k==='z'&&!e.shiftKey){e.preventDefault();lspUndo();}
      else if(k==='y'||(k==='z'&&e.shiftKey)){e.preventDefault();lspRedo();}
      else if(k==='d'){e.preventDefault();lspDuplicateLayer();}
      else if(k==='c'){if(LSP.canvas.getActiveObject()){e.preventDefault();lspCopy();}}
      else if(k==='x'){if(LSP.canvas.getActiveObject()){e.preventDefault();lspCut();}}
      else if(k==='v'){if(LSP.clipboard){e.preventDefault();lspPaste();}}
      else if(k==='a'){e.preventDefault();lspSelectAll();}
      else if(k==='g'){e.preventDefault();e.shiftKey?lspUngroupLayers():lspGroupLayers();}
      else if(k==='s'){e.preventDefault();lspSaveToGallery();}
      else if(k==='e'){e.preventDefault();lspOpenExport();}
      else if(k==='='||k==='+'){e.preventDefault();lspZoomIn();}
      else if(k==='-'){e.preventDefault();lspZoomOut();}
      else if(k==='0'){e.preventDefault();lspZoomFit();}
      else if(k===']'){e.preventDefault();e.shiftKey?lspLayerFront():lspLayerUp();}
      else if(k==='['){e.preventDefault();e.shiftKey?lspLayerBack():lspLayerDown();}
      return;
    }
    if(e.altKey)return;
    if(e.key==='Delete'||e.key==='Backspace'){if(LSP.canvas.getActiveObject()){e.preventDefault();lspDeleteLayer();}return;}
    if(e.key==='Escape'){
      if(LSP.activeTool==='draw')lspExitDraw();
      else if(document.querySelector('.lsp-flyout.open'))lspCloseAllFlyouts();
      else{LSP.canvas.discardActiveObject();LSP.canvas.requestRenderAll();}
      return;
    }
    const step=e.shiftKey?10:1;
    if(e.key==='ArrowUp'){e.preventDefault();lspNudge(0,-step);return;}
    if(e.key==='ArrowDown'){e.preventDefault();lspNudge(0,step);return;}
    if(e.key==='ArrowLeft'){e.preventDefault();lspNudge(-step,0);return;}
    if(e.key==='ArrowRight'){e.preventDefault();lspNudge(step,0);return;}
    if(e.shiftKey)return;
    if(k==='v')lspSetTool('select',document.getElementById('tool-select'));
    else if(k==='h')lspSetTool('move',document.getElementById('tool-move'));
    else if(k==='t')lspAddText();
    else if(k==='c')lspAddCircularText('top');
    else if(k==='s')lspOpenShapes();
    else if(k==='i')lspOpenIcons();
    else if(k==='f')lspOpenFrames();
    else if(k==='b')lspOpenBadgeBuilder();
    else if(k==='p')lspUploadImage();
    else if(k==='d')lspStartDraw();
  });
}

/* TOAST */
let _tt=null;
function lspShowToast(msg,dur){
  const t=document.getElementById('lsp-toast');t.textContent=msg;t.classList.add('show');
  if(_tt)clearTimeout(_tt);_tt=setTimeout(()=>t.classList.remove('show'),dur||2500);
}

/* ── In-app confirm/prompt dialogs (replace native confirm()/prompt() — better on mobile) ── */
let _lspConfirmYes=null,_lspConfirmNo=null;
function lspConfirm(message,onYes,onNo){
  document.getElementById('lspConfirmMsg').textContent=message;
  document.getElementById('lspConfirmOv').style.display='flex';
  _lspConfirmYes=onYes||null;_lspConfirmNo=onNo||null;
}
function _lspConfirmAnswer(yes){
  document.getElementById('lspConfirmOv').style.display='none';
  const y=_lspConfirmYes,n=_lspConfirmNo;
  _lspConfirmYes=null;_lspConfirmNo=null;
  if(yes&&y)y();
  if(!yes&&n)n();
}

let _lspPromptCb=null, _lspPromptFields=null;
function lspPromptModal(title,fields,onOk){
  document.getElementById('lspPromptTitle').textContent=title;
  _lspPromptFields=fields;
  // Values are escaped — a saved name containing a quote used to break the field.
  document.getElementById('lspPromptBody').innerHTML=fields.map(f=>
    `<label style="font-size:.72rem;color:#9ba8d4;display:block;margin:.5rem 0 .25rem;font-weight:600;" for="_lspPf_${f.id}">${lspEsc(f.label)}</label>
     <input type="${f.type==='number'?'number':'text'}" id="_lspPf_${f.id}" class="lsp-inp" value="${lspEsc(f.value!==undefined&&f.value!==null?f.value:'')}" style="width:100%;" onkeydown="if(event.key==='Enter'){event.preventDefault();_lspPromptAnswer(true);}">`
  ).join('');
  document.getElementById('lspPromptOv').style.display='flex';
  setTimeout(()=>{ const first=document.getElementById('_lspPf_'+fields[0].id); if(first){first.focus();first.select&&first.select();} },50);
  _lspPromptCb=onOk;
}
function _lspPromptAnswer(ok){
  document.getElementById('lspPromptOv').style.display='none';
  const cb=_lspPromptCb,fields=_lspPromptFields;
  _lspPromptCb=null; _lspPromptFields=null;
  if(ok&&cb&&fields){
    const vals={};
    fields.forEach(f=>{ const el=document.getElementById('_lspPf_'+f.id); vals[f.id]=el?el.value:''; });
    cb(vals);
  }
}

// ── Static-asset cache (Phase 1 of the offline/WebView caching plan) ──────
// See sw.js — static files only, every page/API call always hits the server
// fresh, so nothing here can ever show stale data.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js').catch(() => {});
  });
}
</script>
</body>
</html>