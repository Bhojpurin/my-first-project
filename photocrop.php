<?php
$pageTitle = 'Bulk Photo Cropper';
require_once __DIR__ . '/icard_header.php';
$csrf     = csrfToken();
$statsApi = BASE_URL . '/api/usage_stats.php';
$segLocal = BASE_URL . '/assets/mediapipe-seg';
?>
<style>
:root{
  --cp-blue:#2563eb;--cp-blue-lt:#eff6ff;--cp-blue-bd:#bfdbfe;
  --cp-green:#16a34a;--cp-green-lt:#f0fdf4;--cp-green-bd:#bbf7d0;
  --cp-amber:#d97706;--cp-amber-lt:#fffbeb;--cp-amber-bd:#fde68a;
  --cp-red:#dc2626;--cp-red-lt:#fef2f2;--cp-red-bd:#fecaca;
  --cp-purple:#7c3aed;--cp-purple-lt:#f5f3ff;--cp-purple-bd:#ddd6fe;
  --cp-grad:linear-gradient(120deg,#4f46e5 0%,#8b5cf6 50%,#d946ef 100%);
  --cp-border:#ece9f7;--cp-bg:#f6f4fd;--cp-text:#1e1b2e;--cp-muted:#7c7a94;
  --cp-radius:20px;--cp-radius-sm:13px;
}
/* Same soft "aurora" wash used across the other rebuilt icard pages, so the
   whole panel reads as one product instead of each page looking bolted on. */
.ic-content{background:
  radial-gradient(1100px 480px at 8% -8%, rgba(139,92,246,.10), transparent 60%),
  radial-gradient(900px 460px at 96% 10%, rgba(217,70,239,.09), transparent 55%),
  radial-gradient(800px 500px at 50% 100%, rgba(79,70,229,.06), transparent 60%),
  var(--cp-bg) !important;
}
/* ── layout ── */
.cp-wrap{max-width:1200px;}
.cp-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.6rem;flex-wrap:wrap;gap:.75rem;}
.cp-title{font-size:1.2rem;font-weight:900;margin:0;display:flex;align-items:center;gap:.6rem;color:var(--cp-text);letter-spacing:-.01em;}
.cp-title-icon{width:40px;height:40px;border-radius:12px;background:var(--cp-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;flex-shrink:0;box-shadow:0 8px 20px -8px rgba(147,51,234,.5);}

/* ── step pills ── */
.cp-steps{display:flex;align-items:center;background:#fff;border-radius:999px;padding:.35rem .5rem;gap:.15rem;box-shadow:0 10px 26px -14px rgba(109,40,217,.22);}
.cp-pill{display:flex;align-items:center;gap:.35rem;padding:.32rem .78rem;border-radius:999px;font-size:.78rem;font-weight:700;color:var(--cp-muted);transition:all .2s;cursor:default;white-space:nowrap;}
.cp-pill.active{background:var(--cp-grad);color:#fff;box-shadow:0 6px 16px -6px rgba(147,51,234,.5);}
.cp-pill.done{background:var(--cp-green-lt);color:var(--cp-green);}
.cp-pnum{width:19px;height:19px;border-radius:50%;border:2px solid currentColor;display:flex;align-items:center;justify-content:center;font-size:.66rem;font-weight:900;flex-shrink:0;}
.cp-pill.active .cp-pnum{background:rgba(255,255,255,.25);border-color:rgba(255,255,255,.6);}
.cp-pill.done .cp-pnum{background:var(--cp-green);border-color:var(--cp-green);color:#fff;}
.cp-pill-sep{color:#d1d5db;font-size:.72rem;padding:0 .05rem;}

/* ── browser warn ── */
.cp-warn-bar{background:linear-gradient(90deg,#eef0ff,#f3ecfe);border:1.5px solid #ddd6fe;border-radius:var(--cp-radius-sm);padding:.65rem 1rem;font-size:.78rem;font-weight:600;color:#5b21b6;display:none;margin-bottom:1rem;}

/* ── cards / panels ── */
.cp-card{background:#fff;border:1px solid var(--cp-border);border-radius:var(--cp-radius);overflow:hidden;box-shadow:0 12px 30px -20px rgba(76,29,149,.18);}
.cp-panel-hd{padding:.8rem 1.05rem;display:flex;align-items:center;gap:.55rem;font-size:.8rem;font-weight:800;color:var(--cp-text);border-bottom:1px solid var(--cp-border);}
.cp-panel-hd i{width:26px;height:26px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;color:#fff;flex-shrink:0;}
.cp-panel-hd.blue i{background:linear-gradient(135deg,#2563eb,#4f46e5);}
.cp-panel-hd.green i{background:linear-gradient(135deg,#16a34a,#0d9488);}
.cp-panel-hd.amber i{background:linear-gradient(135deg,#d97706,#f59e0b);}
.cp-panel-hd.red i{background:linear-gradient(135deg,#dc2626,#db2777);}
.cp-panel-hd.purple i{background:var(--cp-grad);}
.cp-panel-body{padding:1rem 1.05rem;}

/* ── dir cards ── */
.cp-dir-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:1.1rem;}
@media(max-width:520px){.cp-dir-grid{grid-template-columns:1fr;}}
.cp-dir-card{border:2px dashed var(--cp-border);border-radius:var(--cp-radius);padding:1.2rem 1.1rem;cursor:pointer;transition:all .2s;background:#faf9ff;display:flex;align-items:center;gap:.85rem;}
.cp-dir-card:hover{border-color:#c4b5fd;background:var(--cp-purple-lt);transform:translateY(-2px);box-shadow:0 12px 26px -14px rgba(109,40,217,.3);}
.cp-dir-card.sel{border-style:solid;border-color:transparent;background:linear-gradient(135deg,#ecfdf5,#f0fdfa);box-shadow:0 12px 26px -16px rgba(22,163,74,.3);}
.cp-dir-card.sel .cp-di-icon{background:linear-gradient(135deg,#16a34a,#0d9488);color:#fff;}
.cp-di-icon{width:48px;height:48px;border-radius:14px;background:var(--cp-purple-lt);color:var(--cp-purple);display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;transition:all .2s;}
.cp-dir-card:hover .cp-di-icon{background:var(--cp-grad);color:#fff;}
.cp-di-body{flex:1;min-width:0;}
.cp-di-label{font-size:.85rem;font-weight:800;color:var(--cp-text);}
.cp-di-sub{font-size:.71rem;color:var(--cp-muted);margin-top:.1rem;}
.cp-di-path{font-size:.67rem;color:#059669;font-weight:700;margin-top:.22rem;word-break:break-all;display:none;}
.cp-dir-card.sel .cp-di-path{display:block;}

/* ── settings row ── */
.cp-set-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:.85rem;}
@media(max-width:600px){.cp-set-grid{grid-template-columns:1fr 1fr;}}
.cp-sg label{font-size:.67rem;font-weight:800;color:var(--cp-muted);display:block;margin-bottom:.22rem;text-transform:uppercase;letter-spacing:.04em;}
.cp-sg select,.cp-sg input[type=number]{width:100%;padding:.5rem .6rem;border:1.5px solid var(--cp-border);border-radius:var(--cp-radius-sm);font-size:.8rem;background:#faf9ff;transition:border-color .15s,box-shadow .15s;color:var(--cp-text);}
.cp-sg select:focus,.cp-sg input[type=number]:focus{border-color:#a78bfa;outline:none;background:#fff;box-shadow:0 0 0 3px rgba(139,92,246,.15);}
.cp-sz-info{background:linear-gradient(90deg,var(--cp-blue-lt),var(--cp-purple-lt));border:1px solid var(--cp-blue-bd);border-radius:var(--cp-radius-sm);padding:.55rem .9rem;font-size:.77rem;color:#4338ca;font-weight:700;margin-bottom:.9rem;display:flex;align-items:center;gap:.5rem;}

/* ── color chips ── */
.cp-color-row{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;}
.cp-chip{width:30px;height:30px;border-radius:50%;border:2.5px solid #e5e7eb;cursor:pointer;transition:all .15s;flex-shrink:0;position:relative;}
.cp-chip:hover{transform:scale(1.14);}
.cp-chip.active{border-color:#8b5cf6;box-shadow:0 0 0 3px rgba(139,92,246,.3);}
.cp-chip.c-custom{display:flex;align-items:center;justify-content:center;background:#f1f5f9;font-size:.7rem;color:var(--cp-muted);overflow:hidden;}
.cp-chip.c-custom input[type=color]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}

/* ── sliders ── */
.cp-sr{margin-bottom:.5rem;}.cp-sr:last-child{margin-bottom:0;}
.cp-sr-meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:.18rem;}
.cp-sr-name{font-size:.77rem;font-weight:600;color:var(--cp-text);}
.cp-sr-val{font-size:.73rem;font-weight:800;min-width:38px;text-align:right;}
.cp-sr-val.blue{color:#4f46e5;}
.cp-sr-val.green{color:var(--cp-green);}
.cp-sr-val.purple{color:var(--cp-purple);}
input[type=range].cp-range{-webkit-appearance:none;width:100%;height:5px;border-radius:3px;background:#ece9f7;outline:none;cursor:pointer;transition:opacity .2s;}
input[type=range].cp-range::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#8b5cf6);cursor:pointer;box-shadow:0 2px 6px rgba(79,70,229,.35),0 0 0 3px #fff;transition:box-shadow .15s,transform .1s;}
input[type=range].cp-range::-webkit-slider-thumb:hover{box-shadow:0 2px 8px rgba(79,70,229,.45),0 0 0 4px #fff;transform:scale(1.1);}
input[type=range].cp-range.green::-webkit-slider-thumb{background:linear-gradient(135deg,#16a34a,#0d9488);box-shadow:0 2px 6px rgba(22,163,74,.35),0 0 0 3px #fff;}
input[type=range].cp-range.green::-webkit-slider-thumb:hover{box-shadow:0 2px 8px rgba(22,163,74,.45),0 0 0 4px #fff;}
input[type=range].cp-range.purple::-webkit-slider-thumb{background:var(--cp-grad);box-shadow:0 2px 6px rgba(147,51,234,.35),0 0 0 3px #fff;}
input[type=range].cp-range.purple::-webkit-slider-thumb:hover{box-shadow:0 2px 8px rgba(147,51,234,.45),0 0 0 4px #fff;}

/* bipolar slider */
.cp-bipolar-wrap{position:relative;padding-bottom:.1rem;}
.cp-bipolar-center-tick{position:absolute;top:50%;left:50%;transform:translate(-50%,-8px);width:2px;height:8px;background:rgba(124,58,237,.5);border-radius:1px;pointer-events:none;}
.cp-bipolar-labels{display:flex;justify-content:space-between;font-size:.63rem;color:var(--cp-muted);margin-top:3px;}
.cp-bipolar-labels span.mid{color:var(--cp-purple);font-weight:800;}

/* checkboxes */
.cp-chk-row{display:flex;align-items:flex-start;gap:.5rem;margin-bottom:.4rem;}
.cp-chk-row:last-child{margin-bottom:0;}
.cp-chk-row input[type=checkbox]{accent-color:#7c3aed;width:16px;height:16px;flex-shrink:0;margin-top:2px;}
.cp-chk-row label{font-size:.8rem;color:var(--cp-text);cursor:pointer;font-weight:500;line-height:1.35;}
.cp-chk-row label small{color:var(--cp-muted);font-size:.7rem;display:block;font-weight:400;margin-top:.06rem;}

/* model status */
.cp-model-badge{font-size:.72rem;padding:.3rem .7rem;border-radius:999px;display:none;margin-top:.45rem;font-weight:700;}
.cp-model-badge.loading{background:var(--cp-blue-lt);color:var(--cp-blue);display:inline-block;}
.cp-model-badge.ready{background:var(--cp-green-lt);color:var(--cp-green);display:inline-block;}
.cp-model-badge.err{background:var(--cp-red-lt);color:var(--cp-red);display:inline-block;}
.cp-bg-sub{display:none;margin-top:.7rem;padding-top:.7rem;border-top:1px dashed var(--cp-border);}
.cp-bg-sub.show{display:block;}

/* ── preview panel ── */
.cp-prev-panel{background:#fff;border:1px solid var(--cp-border);border-radius:var(--cp-radius);overflow:hidden;position:sticky;top:1rem;box-shadow:0 12px 30px -20px rgba(76,29,149,.18);}
.cp-prev-hd{display:flex;align-items:center;justify-content:space-between;padding:.7rem .9rem;background:linear-gradient(90deg,#faf9ff,#f6f4fd);border-bottom:1px solid var(--cp-border);}
.cp-prev-title{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--cp-muted);display:flex;align-items:center;gap:.3rem;}
.cp-ba{display:flex;border:1.5px solid var(--cp-border);border-radius:999px;overflow:hidden;background:#fff;}
.cp-ba button{padding:.22rem .65rem;font-size:.7rem;font-weight:700;border:none;background:transparent;cursor:pointer;color:var(--cp-muted);transition:all .15s;}
.cp-ba button.active{background:var(--cp-grad);color:#fff;}
.cp-prev-body{padding:.7rem;}
.cp-canvas-wrap{position:relative;border-radius:14px;overflow:hidden;background:linear-gradient(135deg,#faf9ff,#f3ecfe);margin-bottom:.5rem;}
.cp-canvas-wrap canvas{display:block;width:100%;height:auto;}
.cp-prev-loader{position:absolute;inset:0;background:rgba(255,255,255,.88);z-index:5;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.3rem;font-size:.72rem;color:var(--cp-muted);backdrop-filter:blur(2px);}
.cp-tags{display:flex;gap:.3rem;flex-wrap:wrap;}
.cpb{font-size:.65rem;font-weight:700;padding:3px 8px;border-radius:999px;}
.cpb.ok{background:#dcfce7;color:#15803d;}.cpb.warn{background:#fef9c3;color:#a16207;}
.cpb.err{background:#fee2e2;color:var(--cp-red);}.cpb.info{background:var(--cp-purple-lt);color:#6d28d9;}
.cp-prev-fname{font-size:.65rem;color:var(--cp-muted);text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:.3rem;}
.cp-no-prev{text-align:center;padding:2.5rem 1rem;color:var(--cp-muted);}
.cp-no-prev i{font-size:2rem;display:block;margin-bottom:.4rem;opacity:.4;}

/* ── step 1 ── */
.cp-s1-grid{display:grid;grid-template-columns:1fr 230px;gap:1.1rem;align-items:start;}
@media(max-width:840px){.cp-s1-grid{grid-template-columns:1fr;}}

/* ── step 2 ── */
.cp-s2-grid{display:grid;grid-template-columns:220px 1fr;gap:1.1rem;align-items:start;}
@media(max-width:840px){.cp-s2-grid{grid-template-columns:1fr;}}
.cp-ctrl-stack{display:flex;flex-direction:column;gap:.7rem;}

/* ── buttons ── */
.cp-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.6rem 1.25rem;border-radius:12px;font-size:.83rem;font-weight:800;cursor:pointer;border:none;transition:all .16s;text-decoration:none;}
.cp-btn-primary{background:var(--cp-grad);color:#fff;box-shadow:0 8px 20px -8px rgba(147,51,234,.5);}
.cp-btn-primary:hover:not(:disabled){box-shadow:0 10px 24px -8px rgba(147,51,234,.6);transform:translateY(-1px);}
.cp-btn-outline{background:#fff;color:var(--cp-text);border:1.5px solid var(--cp-border);}
.cp-btn-outline:hover{background:var(--cp-purple-lt);border-color:#ddd6fe;}
.cp-btn-success{background:linear-gradient(135deg,#16a34a,#0d9488);color:#fff;box-shadow:0 8px 20px -8px rgba(22,163,74,.5);}
.cp-btn-success:hover:not(:disabled){box-shadow:0 10px 24px -8px rgba(22,163,74,.6);transform:translateY(-1px);}
.cp-btn:disabled{opacity:.45;cursor:not-allowed;transform:none!important;box-shadow:none!important;}
.cp-btn-row{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-top:1.1rem;}

/* ── step 3 ── */
.cp-s3-done{background:linear-gradient(120deg,#0f766e,#16a34a,#22c55e);position:relative;overflow:hidden;color:#fff;border-radius:var(--cp-radius);padding:1.7rem 1.5rem;text-align:center;display:none;margin-bottom:1rem;box-shadow:0 20px 40px -18px rgba(21,128,61,.5);}
.cp-s3-done::before{content:'';position:absolute;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.14);top:-90px;right:-60px;}
.cp-s3-done::after{content:'';position:absolute;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.1);bottom:-80px;left:-40px;}
.cp-s3-done.show{display:block;}
.cp-s3-done>*{position:relative;}
.cp-s3-done h4{font-size:1.25rem;font-weight:900;margin:.5rem 0 .25rem;}
.cp-s3-done p{font-size:.85rem;opacity:.92;margin:0;}
.cp-prog-card{background:#fff;border:1px solid var(--cp-border);border-radius:var(--cp-radius);padding:1.2rem;margin-bottom:.9rem;box-shadow:0 12px 30px -20px rgba(76,29,149,.18);}
.cp-prog-cur{font-size:.85rem;font-weight:700;color:var(--cp-text);margin-bottom:.35rem;word-break:break-all;}
.cp-prog-bar-bg{background:#ece9f7;border-radius:99px;height:9px;overflow:hidden;margin:.3rem 0 .2rem;}
.cp-prog-bar{height:100%;background:var(--cp-grad);border-radius:99px;transition:width .3s;}
.cp-prog-lbl{display:flex;justify-content:space-between;font-size:.7rem;color:var(--cp-muted);}
.cp-thumb-strip{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.8rem;}
.cp-ti{position:relative;width:68px;height:88px;border-radius:11px;overflow:hidden;border:2px solid var(--cp-border);flex-shrink:0;}
.cp-ti img{width:100%;height:100%;object-fit:cover;}
.cp-tis{position:absolute;bottom:0;left:0;right:0;padding:2px;font-size:.57rem;font-weight:700;text-align:center;}
.cp-tis.ok{background:rgba(22,163,74,.85);color:#fff;}.cp-tis.err{background:rgba(220,38,38,.85);color:#fff;}
.cp-stats-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:.9rem;}
.cp-stat{border-radius:var(--cp-radius);padding:1.1rem;text-align:center;color:#fff;}
.cp-stat.blue{background:var(--cp-grad);box-shadow:0 12px 26px -12px rgba(147,51,234,.45);}
.cp-stat.green{background:linear-gradient(135deg,#0f766e,#0d9488);box-shadow:0 12px 26px -12px rgba(13,148,136,.45);}
.cp-stat-n{font-size:1.95rem;font-weight:900;line-height:1;}
.cp-stat-l{font-size:.7rem;opacity:.88;margin-top:.25rem;font-weight:600;}
.cp-elog{max-height:130px;overflow-y:auto;font-size:.72rem;color:var(--cp-muted);margin-top:.6rem;background:#faf9ff;border:1px solid var(--cp-border);border-radius:10px;padding:.5rem .7rem;display:none;}
.cp-elog.show{display:block;}
.cp-mdl-load{background:var(--cp-purple-lt);border:1px solid var(--cp-purple-bd);border-radius:var(--cp-radius-sm);padding:.9rem 1.05rem;margin-bottom:.85rem;display:none;}
.cp-mdl-load.show{display:block;}

/* ── mobile native touch-up ── */
@media(max-width:768px){
  .cp-wrap{padding-left:.15rem;padding-right:.15rem;}
  .cp-header{margin-bottom:1.1rem;}
  .cp-title{font-size:1.05rem;}
  .cp-title-icon{width:36px;height:36px;font-size:.9rem;}
  .cp-steps{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;}
  .cp-steps::-webkit-scrollbar{display:none;}
  .cp-pill{padding:.32rem .6rem;font-size:.73rem;}
  .cp-dir-card{padding:1rem .9rem;}
  .cp-di-icon{width:44px;height:44px;font-size:1.1rem;}
  .cp-set-grid{grid-template-columns:1fr 1fr;}
  .cp-btn{width:100%;justify-content:center;padding:.75rem 1.1rem;font-size:.85rem;}
  .cp-btn-row{flex-direction:column;align-items:stretch;gap:.5rem;}
  .cp-btn-row span{text-align:center;}
  input[type=range].cp-range::-webkit-slider-thumb{width:20px;height:20px;}
  .cp-prev-panel{position:static;}
  .cp-thumb-strip .cp-ti{width:60px;height:78px;}
  .cp-stats-row{gap:.6rem;}
  .cp-stat{padding:.9rem;}
  .cp-stat-n{font-size:1.6rem;}
  .cp-chk-row input[type=checkbox]{width:19px;height:19px;}
}
</style>

<div class="cp-wrap">
<div class="cp-warn-bar" id="cpBrowserWarn">
  <i class="bi bi-info-circle-fill me-1"></i>
  <strong>Photo picker mode</strong> — this device/browser can't open folders directly, so pick photos individually below and the cropped results will download as a ZIP file instead of saving straight to a folder.
</div>
<!-- Fallback for any browser/WebView without the File System Access API
     (every mobile browser, and the app's own WebView, fall in this bucket —
     showDirectoryPicker() is Chrome/Edge-desktop only). Without this, the
     folder cards were a dead end on mobile: tap → alert() → nothing else
     possible, on the one page in this app that's actually impossible to use
     from the app itself. webkitdirectory degrades to a plain multi-file
     picker wherever real folder selection isn't supported, which is exactly
     the right fallback for a phone. -->
<input type="file" id="cpInFallback" accept="image/*" multiple style="display:none" onchange="onFallbackFilesPicked(this)">
<!-- No webkitdirectory here on purpose — some Android WebViews handle it
     inconsistently (silently returning zero files instead of degrading to a
     normal multi-select), and the actual goal ("pick several photos") works
     the same either way. A plain multi-file picker is the one behavior every
     mobile browser and this app's own WebView are guaranteed to support. -->

<div class="cp-header">
  <h4 class="cp-title">
    <span class="cp-title-icon"><i class="bi bi-scissors"></i></span>
    Bulk Photo Cropper
  </h4>
  <div class="cp-steps">
    <div class="cp-pill active" id="sp1"><span class="cp-pnum">1</span> Setup</div>
    <span class="cp-pill-sep">›</span>
    <div class="cp-pill" id="sp2"><span class="cp-pnum">2</span> Adjustments</div>
    <span class="cp-pill-sep">›</span>
    <div class="cp-pill" id="sp3"><span class="cp-pnum">3</span> Processing</div>
  </div>
</div>

<!-- ══════════════ STEP 1 ══════════════ -->
<div id="cpStep1">
  <div class="cp-s1-grid">
    <div>
      <!-- Folder pickers -->
      <div class="cp-dir-grid">
        <div class="cp-dir-card" id="cpInCard" onclick="pickInputDir()">
          <div class="cp-di-icon"><i class="bi bi-folder2-open"></i></div>
          <div class="cp-di-body">
            <div class="cp-di-label">Input Folder</div>
            <div class="cp-di-sub" id="cpInSub">Click to select photos</div>
            <div class="cp-di-path" id="cpInPath"></div>
          </div>
        </div>
        <div class="cp-dir-card" id="cpOutCard" onclick="pickOutputDir()">
          <div class="cp-di-icon"><i class="bi bi-folder-plus"></i></div>
          <div class="cp-di-body">
            <div class="cp-di-label">Output Folder</div>
            <div class="cp-di-sub" id="cpOutSub">Click to select save location</div>
            <div class="cp-di-path" id="cpOutPath"></div>
          </div>
        </div>
      </div>

      <!-- Size / quality settings -->
      <div class="cp-card" style="margin-bottom:.85rem;">
        <div class="cp-panel-hd blue"><i class="bi bi-gear-fill"></i> Output Settings</div>
        <div class="cp-panel-body">
          <div class="cp-set-grid">
            <div class="cp-sg">
              <label>Passport Size</label>
              <select id="cpSize" onchange="onSizeChange()">
                <option value="35x45">India / EU (35×45 mm)</option>
                <option value="51x51">USA (51×51 mm)</option>
                <option value="33x48">China (33×48 mm)</option>
                <option value="50x70">Canada (50×70 mm)</option>
                <option value="45x45">UK (45×45 mm)</option>
                <option value="35x35">Australia (35×35 mm)</option>
                <option value="original">Original (same as input)</option>
                <option value="custom">Custom…</option>
              </select>
            </div>
            <div class="cp-sg">
              <label>Output DPI</label>
              <select id="cpDpi" onchange="onSizeChange()">
                <option value="150">150 DPI (web)</option>
                <option value="300" selected>300 DPI (print)</option>
                <option value="600">600 DPI (high-res)</option>
              </select>
            </div>
            <div class="cp-sg">
              <label>JPEG Quality</label>
              <select id="cpJpeg">
                <option value="0.75">75% — small</option>
                <option value="0.88">88% — balanced</option>
                <option value="0.94">94% — high</option>
                <option value="1.0" selected>100% — max (print)</option>
              </select>
            </div>
          </div>
          <div id="cpCustomRow" style="display:none;gap:.6rem;margin-bottom:.6rem;">
            <div class="cp-sg" style="max-width:110px;"><label>Width (mm)</label><input type="number" id="cpCW" value="35" min="20" max="200" oninput="onSizeChange()"></div>
            <div class="cp-sg" style="max-width:110px;"><label>Height (mm)</label><input type="number" id="cpCH" value="45" min="20" max="300" oninput="onSizeChange()"></div>
          </div>
          <div id="cpSuffixRow" style="margin-bottom:.6rem;">
            <div style="font-size:.67rem;font-weight:800;color:var(--cp-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.22rem;">Filename Suffix <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional — appended before extension)</span></div>
            <input type="text" id="cpSuffix" placeholder='e.g.  _pp  →  photo_pp.jpg' maxlength="40"
              style="width:100%;padding:.42rem .55rem;border:1.5px solid var(--cp-border);border-radius:var(--cp-radius-sm);font-size:.8rem;color:var(--cp-text);background:#fff;box-sizing:border-box;">
          </div>
          <div class="cp-sz-info">
            <i class="bi bi-aspect-ratio"></i>
            Output: <strong id="cpSzInfo">413 × 531 px</strong> &nbsp;@&nbsp; <strong id="cpDpiInfo">300 DPI</strong>
          </div>
        </div>
      </div>

      <div style="background:#fff;border:1.5px solid var(--cp-border);border-radius:var(--cp-radius);padding:.7rem 1rem;margin-bottom:.85rem;display:flex;align-items:center;gap:.65rem;">
        <input type="checkbox" id="chk_formmode" style="accent-color:var(--cp-blue);width:17px;height:17px;flex-shrink:0;cursor:pointer;">
        <label for="chk_formmode" style="cursor:pointer;margin:0;line-height:1.35;">
          <span style="font-size:.84rem;font-weight:800;color:var(--cp-text);">Form / Document Mode</span>
          <span style="font-size:.72rem;color:var(--cp-muted);display:block;margin-top:.08rem;">Enable when photos are inside a filled form or scanned document — detects face in any corner of the page</span>
        </label>
      </div>

      <div class="cp-btn-row">
        <button class="cp-btn cp-btn-primary" onclick="goToStep2()" id="cpBtnNext" disabled>
          <i class="bi bi-arrow-right-circle-fill"></i> Next: Adjustments
        </button>
        <span style="font-size:.75rem;color:var(--cp-muted);" id="cpReadyMsg">Select both folders to continue</span>
      </div>
    </div>

    <!-- Step 1 preview (right) -->
    <div class="cp-prev-panel">
      <div class="cp-prev-hd">
        <div class="cp-prev-title"><i class="bi bi-eye-fill"></i> Preview</div>
        <div class="cp-ba">
          <button class="active" id="s1BtnSrc" onclick="setS1Mode('source',this)">Source</button>
          <button id="s1BtnCrop" onclick="setS1Mode('crop',this)">Cropped</button>
        </div>
      </div>
      <div class="cp-prev-body">
        <div class="cp-canvas-wrap">
          <canvas id="cpS1Canvas" width="210" height="270" style="display:none;"></canvas>
          <div id="cpS1Empty" class="cp-no-prev">
            <i class="bi bi-image"></i>
            Select input folder<br>to see preview
          </div>
          <div id="cpS1Loader" class="cp-prev-loader" style="display:none;">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <span id="cpS1Msg" style="font-size:.72rem;color:var(--cp-muted);">Loading…</span>
          </div>
        </div>
        <div id="cpS1Tags" class="cp-tags"></div>
        <div id="cpS1Fname" class="cp-prev-fname"></div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════ STEP 2 ══════════════ -->
<div id="cpStep2" style="display:none;">
  <div class="cp-s2-grid">

    <!-- Left: live preview -->
    <div class="cp-prev-panel">
      <div class="cp-prev-hd">
        <div class="cp-prev-title"><i class="bi bi-eye-fill"></i> Preview</div>
        <div class="cp-ba">
          <button class="active" id="cpBtnAfter" onclick="setPrevMode('after',this)">After</button>
          <button id="cpBtnBefore" onclick="setPrevMode('before',this)">Before</button>
        </div>
      </div>
      <div class="cp-prev-body">
        <div class="cp-canvas-wrap">
          <canvas id="cpPrevCanvas" width="210" height="270"></canvas>
          <div id="cpPrevLoader" class="cp-prev-loader" style="display:none;">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <span id="cpPrevMsg" style="font-size:.72rem;color:var(--cp-muted);">Loading…</span>
          </div>
        </div>
        <div id="cpPrevTags" class="cp-tags"></div>
        <div id="cpPrevFname" class="cp-prev-fname"></div>
      </div>
    </div>

    <!-- Right: controls -->
    <div class="cp-ctrl-stack">

      <!-- Basic adjustments -->
      <div class="cp-card">
        <div class="cp-panel-hd blue"><i class="bi bi-sliders"></i> Basic Adjustments</div>
        <div class="cp-panel-body">
          <?php foreach([
            ['brightness','Brightness',50,200,100,'%','blue'],
            ['contrast','Contrast',50,200,100,'%','blue'],
            ['saturation','Saturation',0,200,100,'%','blue'],
            ['warmth','Warmth',-50,50,0,'','blue'],
            ['sharpness','Sharpness',0,100,0,'%','blue'],
            ['vibrance','Vibrance',0,100,0,'%','blue'],
          ] as [$id,$lbl,$mn,$mx,$df,$u,$col]): ?>
          <div class="cp-sr">
            <div class="cp-sr-meta">
              <span class="cp-sr-name"><?=$lbl?></span>
              <span class="cp-sr-val <?=$col?>" id="v_<?=$id?>"><?=$df?><?=$u?></span>
            </div>
            <input type="range" class="cp-range" id="s_<?=$id?>" min="<?=$mn?>" max="<?=$mx?>" value="<?=$df?>"
                   oninput="v_<?=$id?>.textContent=this.value+'<?=$u?>';schedPrev()">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Portrait -->
      <div class="cp-card">
        <div class="cp-panel-hd green"><i class="bi bi-person-bounding-box"></i> Portrait Enhancement</div>
        <div class="cp-panel-body">
          <?php foreach([
            ['face_smooth','Face Smooth',0,100,0,'green'],
            ['skin_glow','Skin Glow',0,100,0,'green'],
            ['face_brighten','Face Brighten',0,100,0,'green'],
            ['hdr','HDR Effect',0,100,0,'green'],
            ['clarity','Clarity',0,100,0,'green'],
            ['noise_reduce','Noise Reduce',0,100,0,'green'],
          ] as [$id,$lbl,$mn,$mx,$df,$col]): ?>
          <div class="cp-sr">
            <div class="cp-sr-meta">
              <span class="cp-sr-name"><?=$lbl?></span>
              <span class="cp-sr-val <?=$col?>" id="v_<?=$id?>"><?=$df?>%</span>
            </div>
            <input type="range" class="cp-range green" id="s_<?=$id?>" min="<?=$mn?>" max="<?=$mx?>" value="<?=$df?>"
                   oninput="v_<?=$id?>.textContent=this.value+'%';schedPrev()">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Auto corrections -->
      <div class="cp-card">
        <div class="cp-panel-hd amber"><i class="bi bi-magic"></i> Auto Corrections</div>
        <div class="cp-panel-body">
          <div class="cp-chk-row">
            <input type="checkbox" id="chk_awb" checked onchange="onAutoChg()">
            <label for="chk_awb">Auto White Balance<small>Percentile gray-world correction</small></label>
          </div>
          <div class="cp-chk-row">
            <input type="checkbox" id="chk_ae" checked onchange="onAutoChg()">
            <label for="chk_ae">Auto Exposure<small>Histogram stretch at 0.6% / 99.4%</small></label>
          </div>
          <div class="cp-chk-row">
            <input type="checkbox" id="chk_tilt" checked onchange="onAutoChg()">
            <label for="chk_tilt">Auto Tilt Correction<small>Eye-level alignment via face landmarks</small></label>
          </div>
          <div class="cp-chk-row">
            <input type="checkbox" id="chk_fallback" checked>
            <label for="chk_fallback">Centre-crop fallback<small>Use if no face detected</small></label>
          </div>
        </div>
      </div>

      <!-- Background removal + color -->
      <div class="cp-card">
        <div class="cp-panel-hd red"><i class="bi bi-person-fill-slash"></i> Background Removal (AI)</div>
        <div class="cp-panel-body">

          <!-- BG Color always visible -->
          <div style="margin-bottom:.75rem;">
            <div style="font-size:.72rem;font-weight:700;color:var(--cp-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem;">Replacement Color</div>
            <div class="cp-color-row" id="cpBgChips">
              <div class="cp-chip active" style="background:#ffffff;" data-c="#ffffff" onclick="pickChip(this)" title="White"></div>
              <div class="cp-chip" style="background:#f5f5f0;" data-c="#f5f5f0" onclick="pickChip(this)" title="Off-White"></div>
              <div class="cp-chip" style="background:#c9d8e8;" data-c="#c9d8e8" onclick="pickChip(this)" title="Light Blue"></div>
              <div class="cp-chip" style="background:#0047ab;" data-c="#0047ab" onclick="pickChip(this)" title="Dark Blue"></div>
              <div class="cp-chip" style="background:#e8e8e8;" data-c="#e8e8e8" onclick="pickChip(this)" title="Light Gray"></div>
              <div class="cp-chip" style="background:#cc0000;" data-c="#cc0000" onclick="pickChip(this)" title="Red"></div>
              <label class="cp-chip c-custom" title="Custom color">
                <i class="bi bi-plus" style="font-size:.65rem;pointer-events:none;"></i>
                <input type="color" id="cpBgCustom" value="#ffffff" oninput="pickCustomColor(this)">
              </label>
            </div>
            <div style="font-size:.68rem;color:var(--cp-muted);margin-top:.28rem;">Used as plain fill, or replaces background when AI removal is enabled below.</div>
          </div>

          <div style="border-top:1px solid var(--cp-border);padding-top:.7rem;">
            <div class="cp-chk-row" style="margin-bottom:.3rem;">
              <input type="checkbox" id="chk_bgremove" onchange="onBgToggle()">
              <label for="chk_bgremove"><strong>Enable AI Background Removal</strong><small>MediaPipe Selfie Segmentation — runs locally</small></label>
            </div>
            <div id="cpBgModelStatus" class="cp-model-badge"></div>

            <div class="cp-bg-sub" id="cpBgSub">
              <!-- Bipolar edge slider -->
              <div class="cp-sr" style="margin-top:.5rem;">
                <div class="cp-sr-meta">
                  <span class="cp-sr-name">Edge Control</span>
                  <span class="cp-sr-val purple" id="v_bg_smooth">0</span>
                </div>
                <div class="cp-bipolar-wrap">
                  <input type="range" class="cp-range purple" id="s_bg_smooth"
                         min="-50" max="50" value="0"
                         oninput="onBgSmoothChange(this)"
                         style="background:linear-gradient(to right,#e5e7eb 50%,#e5e7eb 50%);">
                  <div class="cp-bipolar-center-tick"></div>
                </div>
                <div class="cp-bipolar-labels">
                  <span>← BG into body</span>
                  <span class="mid">0</span>
                  <span>Add buffer →</span>
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem;margin-top:.55rem;font-size:.68rem;color:var(--cp-muted);background:var(--cp-bg);border-radius:7px;padding:.45rem .6rem;">
                <div><strong style="color:var(--cp-red);">−</strong> Background shrinks person inward, removes colour fringe</div>
                <div><strong style="color:var(--cp-purple);">+</strong> Person mask expands outward, adds clean buffer padding</div>
              </div>
            </div>
          </div>

        </div>
      </div>

    </div><!-- /.cp-ctrl-stack -->
  </div>

  <div class="cp-btn-row">
    <button class="cp-btn cp-btn-outline" onclick="showStep(1)"><i class="bi bi-arrow-left"></i> Back</button>
    <button class="cp-btn cp-btn-success" onclick="startProcessing()">
      <i class="bi bi-play-circle-fill"></i> Start Cropping
      <span style="background:rgba(255,255,255,.25);border-radius:5px;padding:0 .45rem;margin-left:.15rem;" id="cpFCnt">0</span> photos
    </button>
  </div>
</div>

<!-- ══════════════ STEP 3 ══════════════ -->
<div id="cpStep3" style="display:none;">
  <div class="cp-s3-done" id="cpDoneBanner">
    <i class="bi bi-check-circle-fill" style="font-size:2.2rem;"></i>
    <h4>All Done!</h4>
    <p id="cpDoneMsg"></p>
    <button class="cp-btn cp-btn-outline" onclick="resetAll()" style="margin-top:.75rem;background:rgba(255,255,255,.16);color:#fff;border-color:rgba(255,255,255,.5);">
      <i class="bi bi-arrow-counterclockwise"></i> Process Another Batch
    </button>
  </div>
  <div class="cp-mdl-load" id="cpMdlLoad">
    <div style="font-size:.82rem;font-weight:700;color:var(--cp-blue);margin-bottom:.3rem;"><i class="bi bi-cpu me-1"></i>Loading AI Models…</div>
    <div class="cp-prog-bar-bg"><div class="cp-prog-bar" id="cpMdlBar" style="width:0%"></div></div>
    <div style="font-size:.7rem;color:var(--cp-blue);margin-top:.2rem;" id="cpMdlStatus">Connecting…</div>
  </div>
  <div class="cp-prog-card">
    <div class="cp-prog-cur" id="cpCurFile">Initializing…</div>
    <div class="cp-prog-bar-bg"><div class="cp-prog-bar" id="cpMainBar" style="width:0%"></div></div>
    <div class="cp-prog-lbl"><span id="cpProgTxt">0 / 0</span><span id="cpProgPct">0%</span></div>
    <div class="cp-tags" id="cpBadges" style="margin-top:.4rem;"></div>
    <div class="cp-thumb-strip" id="cpThumbStrip">
      <div style="font-size:.76rem;color:var(--cp-muted);padding:.35rem 0;" data-ph>No outputs yet</div>
    </div>
  </div>
  <div class="cp-stats-row">
    <div class="cp-stat blue"><div class="cp-stat-n" id="cpStSess">0</div><div class="cp-stat-l">This session</div></div>
    <div class="cp-stat green"><div class="cp-stat-n" id="cpStAll">—</div><div class="cp-stat-l">My all-time total</div></div>
  </div>
  <div class="cp-elog" id="cpElog"></div>
</div>
</div><!-- /.cp-wrap -->

<!-- Self-hosted (assets/js/vendor/) so bulk cropping works with zero internet —
     the actual crop/face-detect/background-removal work was already 100%
     local (model weights already self-hosted), the library scripts
     themselves were the only remaining CDN dependency. -->
<script src="<?= BASE_URL ?>/assets/js/vendor/face-api-0.22.2.min.js"></script>
<script src="<?= $segLocal ?>/selfie_segmentation.js"></script>
<script src="<?= BASE_URL ?>/assets/js/vendor/jszip-3.10.1.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/vendor/filesaver-2.0.5.min.js"></script>
<script>
/* ═══════════════════ BULK PHOTO CROPPER ═══════════════════ */
const STATS_API  = '<?= $statsApi ?>';
const CSRF_TOKEN = '<?= $csrf ?>';
const MODEL_CDN  = '<?= BASE_URL ?>/assets/face-api-weights';
const SEG_LOCAL  = '<?= $segLocal ?>';
const SZ_MAP = {
  '35x45':{w:35,h:45},'51x51':{w:51,h:51},'33x48':{w:33,h:48},
  '50x70':{w:50,h:70},'45x45':{w:45,h:45},'35x35':{w:35,h:35}
};

/* ── state ── */
// inDir/outDir hold either a real FileSystemDirectoryHandle (Chrome/Edge
// desktop) or the sentinel strings 'FALLBACK'/'ZIP' (every other browser and
// the app's own WebView) — kept as plain truthy values on purpose so every
// existing !!(inDir&&outDir&&...) check elsewhere keeps working unchanged;
// only the few places that actually call directory-handle methods need to
// branch on which kind they have.
const HAS_FSA='showDirectoryPicker' in window;
let inDir=null,outDir=null,imgFiles=[],modelsLoaded=false;
let zipOut=null;
let sessionCnt=0,errLog=[],running=false;
let bgModel=null,bgModelLoaded=false,bgModelLoading=false;
let bgColor='#ffffff';
let s1Bitmap=null,s1SrcW=0,s1SrcH=0,s1FaceBox=null,s1Mode='crop',s1Timer=null;
let prevBitmap=null,prevBase=null,prevOrig=null,prevFaceOut=null,prevMode='after',prevTimer=null;
let prevBgMaskRaw=null,prevBgMask=null;

/* ── init ── */
if(!HAS_FSA)
  document.getElementById('cpBrowserWarn').style.display='block';

/* ── size helpers ── */
function getSizeMM(){
  const v=document.getElementById('cpSize').value;
  if(v==='custom') return{w:+(document.getElementById('cpCW').value)||35,h:+(document.getElementById('cpCH').value)||45};
  return SZ_MAP[v]||{w:35,h:45};
}
function getPxSize(){
  const{w,h}=getSizeMM(), dpi=+document.getElementById('cpDpi').value;
  return{w:Math.round(w/25.4*dpi),h:Math.round(h/25.4*dpi)};
}
function onSizeChange(){
  const v=document.getElementById('cpSize').value;
  document.getElementById('cpCustomRow').style.display=v==='custom'?'flex':'none';
  if(v==='original'){
    document.getElementById('cpSzInfo').textContent='Same as input image';
    document.getElementById('cpDpiInfo').textContent='No resize';
  }else{
    const{w,h}=getPxSize(), dpi=document.getElementById('cpDpi').value;
    document.getElementById('cpSzInfo').textContent=`${w} × ${h} px`;
    document.getElementById('cpDpiInfo').textContent=`${dpi} DPI`;
  }
  schedS1Prev();
}
onSizeChange();

/* ── BG color picker ── */
function pickChip(el){
  bgColor=el.dataset.c;
  document.querySelectorAll('#cpBgChips .cp-chip').forEach(c=>c.classList.remove('active'));
  el.classList.add('active');
  schedS1Prev(); schedPrev();
}
function pickCustomColor(inp){
  bgColor=inp.value;
  document.querySelectorAll('#cpBgChips .cp-chip').forEach(c=>c.classList.remove('active'));
  inp.parentElement.classList.add('active');
  schedS1Prev(); schedPrev();
}

/* ── BG model ── */
function onBgToggle(){
  const on=chkVal('chk_bgremove');
  document.getElementById('cpBgSub').classList.toggle('show',on);
  if(on && !bgModelLoaded && !bgModelLoading) loadBgModel();
  else if(on && bgModelLoaded) rebuildPrevMask();
  else{ prevBgMaskRaw=null; prevBgMask=null; renderPrev(); }
}
async function loadBgModel(){
  if(bgModelLoading||bgModelLoaded) return;
  bgModelLoading=true;
  setMdlStatus('loading','⏳ Loading segmentation model (~6 MB)…');
  try{
    bgModel=new SelfieSegmentation({locateFile:f=>`${SEG_LOCAL}/${f}`});
    bgModel.setOptions({modelSelection:1,selfieMode:false});
    await bgModel.initialize();
    bgModelLoaded=true; bgModelLoading=false;
    setMdlStatus('ready','✓ Model ready');
    await rebuildPrevMask();
  }catch(e){
    bgModelLoading=false;
    setMdlStatus('err','✗ Failed: '+e.message);
  }
}
function setMdlStatus(cls,txt){
  const el=document.getElementById('cpBgModelStatus');
  el.className='cp-model-badge '+cls; el.textContent=txt;
}

/* ── dir pickers ── */
async function pickInputDir(){
  if(!HAS_FSA){ document.getElementById('cpInFallback').click(); return; }
  try{
    inDir=await showDirectoryPicker({mode:'read'});
    imgFiles=[];
    for await(const e of inDir.values()){
      if(e.kind==='file'){
        const f=await e.getFile();
        if(/^image\//i.test(f.type)||/\.(jpe?g|png|webp|bmp|tiff?)$/i.test(f.name))
          imgFiles.push({entry:e,file:f,name:f.name});
      }
    }
    imgFiles.sort((a,b)=>a.name.localeCompare(b.name));
    const card=document.getElementById('cpInCard');
    card.classList.add('sel');
    document.getElementById('cpInPath').textContent=inDir.name;
    document.getElementById('cpInSub').textContent=`${imgFiles.length} image${imgFiles.length!==1?'s':''} found`;
    refreshS1();
    if(imgFiles.length) loadS1Preview();
  }catch(e){if(e.name!=='AbortError')console.error(e);}
}
function onFallbackFilesPicked(inp){
  const files=[...inp.files].filter(f=>/^image\//i.test(f.type)||/\.(jpe?g|png|webp|bmp|tiff?)$/i.test(f.name));
  inp.value=''; // allow re-picking the same files later
  if(!files.length) return;
  inDir='FALLBACK';
  imgFiles=files.map(f=>({entry:null,file:f,name:f.name})).sort((a,b)=>a.name.localeCompare(b.name));
  document.getElementById('cpInCard').classList.add('sel');
  document.getElementById('cpInPath').textContent=`${imgFiles.length} photo${imgFiles.length!==1?'s':''} selected`;
  document.getElementById('cpInSub').textContent=`${imgFiles.length} image${imgFiles.length!==1?'s':''} found`;
  // No folder concept here, so the output side has nothing to pick either —
  // auto-select the ZIP download path the moment there's something to crop.
  if(!outDir) autoSelectZipOutput();
  refreshS1();
  loadS1Preview();
}
function autoSelectZipOutput(){
  outDir='ZIP';
  document.getElementById('cpOutCard').classList.add('sel');
  document.getElementById('cpOutPath').textContent='Downloads as a ZIP file';
  document.getElementById('cpOutSub').textContent='Ready — no folder needed on this device';
}
async function pickOutputDir(){
  if(!HAS_FSA){ if(!outDir) autoSelectZipOutput(); refreshS1(); return; }
  try{
    outDir=await showDirectoryPicker({mode:'readwrite'});
    document.getElementById('cpOutCard').classList.add('sel');
    document.getElementById('cpOutPath').textContent=outDir.name;
    document.getElementById('cpOutSub').textContent='Ready to save';
    refreshS1();
  }catch(e){if(e.name!=='AbortError')console.error(e);}
}
function refreshS1(){
  const ok=!!(inDir&&outDir&&imgFiles.length);
  document.getElementById('cpBtnNext').disabled=!ok;
  document.getElementById('cpReadyMsg').textContent=
    !inDir?'Select both folders to continue':
    !outDir?'Select output folder to continue':
    imgFiles.length===0?'No images found in input folder':
    `${imgFiles.length} image${imgFiles.length!==1?'s':''} ready — click Next`;
}

/* ── step 1 preview ── */
async function loadS1Preview(){
  if(!imgFiles.length) return;
  s1Bitmap=null; s1FaceBox=null;
  document.getElementById('cpS1Empty').style.display='none';
  document.getElementById('cpS1Canvas').style.display='block';
  showS1Loader(true,'Loading…');
  try{
    const f=imgFiles[0].file;
    document.getElementById('cpS1Fname').textContent=imgFiles[0].name;
    s1Bitmap=await createImageBitmap(f);
    s1SrcW=s1Bitmap.width; s1SrcH=s1Bitmap.height;
    showS1Loader(false); renderS1();
    if(!modelsLoaded) modelsLoaded=await loadModelsQuiet();
    if(modelsLoaded){
      const sc=Math.min(1,1200/Math.max(s1SrcW,s1SrcH));
      const tmp=mk(Math.round(s1SrcW*sc),Math.round(s1SrcH*sc));
      tmp.getContext('2d').drawImage(s1Bitmap,0,0,tmp.width,tmp.height);
      const _fn=chkVal('chk_formmode')?detectFaceInDocument:detectFace;
      const det=await _fn(tmp);
      if(det){
        const inv=1/sc, b=det.detection.box;
        s1FaceBox={x:b.x*inv,y:b.y*inv,width:b.width*inv,height:b.height*inv};
      }
      renderS1();
    }
  }catch(e){ showS1Loader(false); }
}
function schedS1Prev(){clearTimeout(s1Timer);s1Timer=setTimeout(renderS1,160);}
function renderS1(){
  if(!s1Bitmap) return;
  const cv=document.getElementById('cpS1Canvas'), ctx=cv.getContext('2d');
  ctx.imageSmoothingEnabled=true; ctx.imageSmoothingQuality='high';
  if(document.getElementById('cpSize').value==='original'){
    const PW=210, PH=Math.min(Math.round(PW*s1SrcH/s1SrcW),350);
    cv.width=PW; cv.height=PH;
    ctx.fillStyle=bgColor; ctx.fillRect(0,0,PW,PH);
    ctx.drawImage(s1Bitmap,0,0,PW,PH);
    setS1Tags([{t:'info',l:`${s1SrcW}×${s1SrcH}`},{t:'ok',l:'Original size'},...(s1FaceBox?[{t:'ok',l:'Face'}]:[])]);
    return;
  }
  const{w:outW,h:outH}=getPxSize(), ratio=outW/outH;
  if(s1Mode==='source'){
    const PW=210, PH=Math.min(Math.round(PW*s1SrcH/s1SrcW),350);
    cv.width=PW; cv.height=PH;
    ctx.drawImage(s1Bitmap,0,0,PW,PH);
    const r=getCropRect(s1SrcW,s1SrcH,s1FaceBox,outW,outH);
    const scX=PW/s1SrcW, scY=PH/s1SrcH;
    const dx=Math.max(0,r.x*scX), dy=Math.max(0,r.y*scY), dw=r.w*scX, dh=r.h*scY;
    ctx.fillStyle='rgba(0,0,0,.46)';
    ctx.fillRect(0,0,PW,dy); ctx.fillRect(0,dy+dh,PW,PH-dy-dh);
    ctx.fillRect(0,dy,dx,dh); ctx.fillRect(dx+dw,dy,PW-dx-dw,dh);
    ctx.strokeStyle='#2563eb'; ctx.lineWidth=2; ctx.strokeRect(dx,dy,dw,dh);
    setS1Tags([{t:'info',l:`${s1SrcW}×${s1SrcH}`},...(s1FaceBox?[{t:'ok',l:'Face'}]:[])]);
  }else{
    const PW=210, PH=Math.round(PW/ratio);
    cv.width=PW; cv.height=PH;
    const r=getCropRect(s1SrcW,s1SrcH,s1FaceBox,PW,PH);
    ctx.fillStyle=bgColor; ctx.fillRect(0,0,PW,PH);
    const sx=Math.max(0,r.x), sy=Math.max(0,r.y);
    const sw=Math.min(r.w-(sx-r.x),s1SrcW-sx);
    const sh=Math.min(r.h-(sy-r.y),s1SrcH-sy);
    const dyo=r.y<0?Math.round((-r.y/r.h)*PH):0;
    ctx.drawImage(s1Bitmap,sx,sy,sw,sh,0,dyo,PW,Math.round((sh/r.h)*PH));
    setS1Tags(s1FaceBox?[{t:'ok',l:'Face crop'}]:[{t:'info',l:'Centre crop'}]);
  }
}
function setS1Mode(mode,btn){
  s1Mode=mode;
  document.querySelectorAll('#s1BtnSrc,#s1BtnCrop').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active'); renderS1();
}
function showS1Loader(on,msg){
  const el=document.getElementById('cpS1Loader'); el.style.display=on?'flex':'none';
  if(msg) document.getElementById('cpS1Msg').textContent=msg;
}
function setS1Tags(tags){ document.getElementById('cpS1Tags').innerHTML=tags.map(t=>`<span class="cpb ${t.t}">${t.l}</span>`).join(''); }

/* ── getCropRect ── */
function getCropRect(sW,sH,fb,outW,outH){
  if(fb){
    const fH=fb.height, fCX=fb.x+fb.width/2;
    const cH=fH/0.54, cW=cH*(outW/outH);
    return{x:fCX-cW/2,y:fb.y-fH*0.50,w:cW,h:cH};
  }
  const ratio=outW/outH, cH=Math.min(sH,sW/ratio), cW=cH*ratio;
  return{x:(sW-cW)/2,y:(sH-cH)*0.15,w:cW,h:cH};
}

/* ── navigation ── */
function showStep(n){
  ['cpStep1','cpStep2','cpStep3'].forEach((id,i)=>
    document.getElementById(id).style.display=i+1===n?'block':'none');
  ['sp1','sp2','sp3'].forEach((id,i)=>{
    const el=document.getElementById(id);
    el.classList.remove('active','done');
    if(i+1===n) el.classList.add('active');
    else if(i+1<n) el.classList.add('done');
    el.querySelector('.cp-pnum').innerHTML=el.classList.contains('done')
      ?'<i class="bi bi-check-lg" style="font-size:.6rem;"></i>':String(i+1);
  });
}
function goToStep2(){
  if(!inDir||!outDir||!imgFiles.length) return;
  document.getElementById('cpFCnt').textContent=imgFiles.length;
  showStep(2);
  loadStep2Preview();
}

/* ── step 2 preview ── */
async function loadStep2Preview(){
  if(!imgFiles.length) return;
  prevBitmap=null; prevBase=null; prevOrig=null; prevFaceOut=null;
  prevBgMaskRaw=null; prevBgMask=null;
  showPrevLoader(true,'Loading image…');
  try{
    document.getElementById('cpPrevFname').textContent=imgFiles[0].name;
    prevBitmap=await createImageBitmap(imgFiles[0].file);
    await buildPrevBase(false);
    showPrevLoader(false); renderPrev();
    if(!modelsLoaded) modelsLoaded=await loadModelsQuiet();
    if(modelsLoaded){
      setPrevTags([{t:'info',l:'Detecting face…'}]);
      await buildPrevBase(true);
      renderPrev();
    }
  }catch(e){ showPrevLoader(false); setPrevTags([{t:'warn',l:'Preview error: '+e.message}]); }
}

async function buildPrevBase(doDetect){
  if(!prevBitmap) return;
  const isOrig=document.getElementById('cpSize').value==='original';
  const ratio=isOrig?(prevBitmap.width/prevBitmap.height):(getPxSize().w/getPxSize().h);
  const PH=270, PW=Math.round(PH*ratio);
  const maxD=1400, sc=Math.min(1,maxD/Math.max(prevBitmap.width,prevBitmap.height));
  const sW=Math.round(prevBitmap.width*sc), sH=Math.round(prevBitmap.height*sc);
  const srcC=mk(sW,sH), srcCtx=srcC.getContext('2d');
  srcCtx.imageSmoothingQuality='high';
  srcCtx.drawImage(prevBitmap,0,0,sW,sH);
  prevOrig=isOrig?hiQResize(srcC,PW,PH):cropPass(srcC,sW,sH,null,PW,PH,bgColor,ratio);
  if(chkVal('chk_awb')) applyAutoWB(srcCtx,sW,sH);
  if(chkVal('chk_ae'))  applyAutoExposure(srcCtx,sW,sH);
  let faceBox=null, tiltDeg=0; prevFaceOut=null;
  if(doDetect&&modelsLoaded){
    try{
      const _fn=chkVal('chk_formmode')?detectFaceInDocument:detectFace;
      const det=await _fn(srcC);
      if(det){
        faceBox=det.detection.box;
        if(chkVal('chk_tilt')&&det.landmarks){
          const le=det.landmarks.getLeftEye(), re=det.landmarks.getRightEye();
          const angle=Math.atan2(favg(re,'y')-favg(le,'y'),favg(re,'x')-favg(le,'x'));
          tiltDeg=+(angle*180/Math.PI).toFixed(1);
          if(Math.abs(angle)>0.008){
            const rot=mk(sW,sH), rc=rot.getContext('2d');
            rc.translate(sW/2,sH/2); rc.rotate(-angle); rc.translate(-sW/2,-sH/2);
            rc.drawImage(srcC,0,0); srcCtx.clearRect(0,0,sW,sH); srcCtx.drawImage(rot,0,0);
            const d2=await faceapi.detectSingleFace(srcC,new faceapi.TinyFaceDetectorOptions({inputSize:416,scoreThreshold:0.42}));
            if(d2) faceBox=d2.detection.box;
          }
        }
        const tags=[{t:'ok',l:'Face'}];
        if(tiltDeg&&Math.abs(tiltDeg)>0.5) tags.push({t:'ok',l:`Tilt ${tiltDeg}°`});
        if(chkVal('chk_awb')) tags.push({t:'ok',l:'AWB'});
        if(chkVal('chk_ae'))  tags.push({t:'ok',l:'AE'});
        setPrevTags(tags);
        prevFaceOut=isOrig
          ?{x:Math.round(faceBox.x*PW/sW),y:Math.round(faceBox.y*PH/sH),width:Math.round(faceBox.width*PW/sW),height:Math.round(faceBox.height*PH/sH)}
          :mapFaceOut(faceBox,sW,sH,PW,PH,ratio);
      }else{ setPrevTags([{t:'warn',l:isOrig?'No face – original mode':'No face – centre crop'}]); }
    }catch(e){ setPrevTags([{t:'warn',l:'Detection error'}]); }
  }else if(!doDetect){ setPrevTags([{t:'info',l:'Loading preview…'}]); }
  prevBase=isOrig?hiQResize(srcC,PW,PH):cropPass(srcC,sW,sH,faceBox,PW,PH,bgColor,ratio);
  if(!isOrig) autoSharpen(prevBase,PW,PH,22);
  prevBgMaskRaw=null; prevBgMask=null;
  if(chkVal('chk_bgremove')&&bgModelLoaded){
    prevBgMaskRaw=await getSegMaskRaw(prevBase);
    if(prevBgMaskRaw){
      prevBgMask=processMask(prevBgMaskRaw,sliderVal('s_bg_smooth'));
      setPrevTags([...getPrevTags(),{t:'ok',l:'BG removed'}]);
    }
  }
  const cv=document.getElementById('cpPrevCanvas'); cv.width=PW; cv.height=PH;
}

async function rebuildPrevMask(){
  if(!prevBase) return;
  prevBgMaskRaw=await getSegMaskRaw(prevBase);
  prevBgMask=prevBgMaskRaw?processMask(prevBgMaskRaw,sliderVal('s_bg_smooth')):null;
  renderPrev();
}

function onBgSmoothChange(inp){
  const v=+inp.value;
  document.getElementById('v_bg_smooth').textContent=(v>0?'+':'')+v;
  // Update track gradient
  const pct=(v+50)/100*100;
  if(v<0)  inp.style.background=`linear-gradient(to right,#e5e7eb ${pct}%,#ef4444 ${pct}%,#ef4444 50%,#e5e7eb 50%)`;
  else if(v>0) inp.style.background=`linear-gradient(to right,#e5e7eb 50%,#7c3aed 50%,#7c3aed ${pct}%,#e5e7eb ${pct}%)`;
  else inp.style.background='#e5e7eb';
  if(!prevBgMaskRaw) return;
  prevBgMask=processMask(prevBgMaskRaw,v);
  renderPrev();
}

function renderPrev(){
  const cv=document.getElementById('cpPrevCanvas'), ctx=cv.getContext('2d');
  const W=cv.width, H=cv.height;
  ctx.imageSmoothingQuality='high';
  if(prevMode==='before'){
    if(prevOrig) ctx.drawImage(prevOrig,0,0);
    else{ ctx.fillStyle='#f1f5f9'; ctx.fillRect(0,0,W,H); }
    return;
  }
  if(!prevBase) return;
  const tmp=mk(W,H); tmp.getContext('2d').drawImage(prevBase,0,0);
  applyAllFilters(tmp,prevFaceOut,getSettings());
  if(prevBgMask) compositeWithBg(tmp,prevBgMask,bgColor);
  ctx.drawImage(tmp,0,0);
}
function schedPrev(){clearTimeout(prevTimer);prevTimer=setTimeout(renderPrev,130);}
function setPrevMode(mode,btn){
  prevMode=mode;
  document.querySelectorAll('#cpBtnAfter,#cpBtnBefore').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active'); renderPrev();
}
async function onAutoChg(){
  if(!prevBitmap) return;
  showPrevLoader(true,'Recalculating…');
  await buildPrevBase(modelsLoaded);
  showPrevLoader(false); renderPrev();
}
function showPrevLoader(on,msg){
  const el=document.getElementById('cpPrevLoader'); el.style.display=on?'flex':'none';
  if(msg) document.getElementById('cpPrevMsg').textContent=msg;
}
function setPrevTags(tags){ document.getElementById('cpPrevTags').innerHTML=tags.map(t=>`<span class="cpb ${t.t}">${t.l}</span>`).join(''); }
function getPrevTags(){ return[...document.getElementById('cpPrevTags').querySelectorAll('.cpb')].map(el=>({t:el.className.replace('cpb ',''),l:el.textContent})); }

/* ── settings ── */
function getSettings(){
  const sizeMode=document.getElementById('cpSize').value;
  const{w:outW,h:outH}=sizeMode==='original'?{w:0,h:0}:getPxSize();
  return{
    outW,outH,sizeMode,bgColor,
    suffix:(document.getElementById('cpSuffix')?.value||'').trim(),
    jpegQ:sizeMode==='original'?1.0:+document.getElementById('cpJpeg').value,
    brightness:sliderVal('s_brightness'),contrast:sliderVal('s_contrast'),
    saturation:sliderVal('s_saturation'),warmth:sliderVal('s_warmth'),
    sharpness:sliderVal('s_sharpness'),vibrance:sliderVal('s_vibrance'),
    face_smooth:sliderVal('s_face_smooth'),skin_glow:sliderVal('s_skin_glow'),
    face_brighten:sliderVal('s_face_brighten'),hdr:sliderVal('s_hdr'),
    clarity:sliderVal('s_clarity'),noise_reduce:sliderVal('s_noise_reduce'),
    bg_smooth:sliderVal('s_bg_smooth'),
    awb:chkVal('chk_awb'),ae:chkVal('chk_ae'),
    autoTilt:chkVal('chk_tilt'),fallback:chkVal('chk_fallback'),
    formMode:chkVal('chk_formmode'),
    bgRemove:chkVal('chk_bgremove'),
  };
}
function sliderVal(id){ return parseFloat(document.getElementById(id)?.value??0); }
function chkVal(id)   { return !!(document.getElementById(id)?.checked); }

/* ── segmentation ── */
async function getSegMaskRaw(canvas){
  if(!bgModelLoaded||!bgModel) return null;
  const W=canvas.width, H=canvas.height;
  // Upscale small canvases before model inference — model sees more detail
  // and produces cleaner, more detailed edges (especially hair/shoulders).
  const MIN_MODEL_W=768;
  let src=canvas;
  if(W<MIN_MODEL_W){
    const s=MIN_MODEL_W/W;
    src=mk(Math.round(W*s),Math.round(H*s));
    const sc=src.getContext('2d'); sc.imageSmoothingQuality='high'; sc.drawImage(canvas,0,0,src.width,src.height);
  }
  return new Promise(resolve=>{
    bgModel.onResults(r=>{
      try{
        const raw=mk(W,H), rc=raw.getContext('2d');
        rc.imageSmoothingEnabled=true; rc.imageSmoothingQuality='high';
        rc.drawImage(r.segmentationMask,0,0,W,H);
        resolve(raw);
      }catch(e){ resolve(null); }
    });
    bgModel.send({image:src}).catch(()=>resolve(null));
  });
}

function processMask(rawMask, edgeVal=0){
  if(!rawMask) return null;
  const W=rawMask.width, H=rawMask.height;
  const rawD=rawMask.getContext('2d').getImageData(0,0,W,H).data;

  // ── Step 1: Binarize raw mask at 128 ────────────────────────────────────
  // All hole-fill operations work on the BINARY mask so the outer boundary
  // is never shifted (morph-close on a gradient mask causes boundary drift).
  const binC=mk(W,H), bcc=binC.getContext('2d');
  const bi=bcc.createImageData(W,H);
  for(let i=0;i<bi.data.length;i+=4){const v=rawD[i]>128?255:0;bi.data[i]=bi.data[i+1]=bi.data[i+2]=v;bi.data[i+3]=255;}
  bcc.putImageData(bi,0,0);

  // ── Step 2: Morph close on BINARY mask (fills hair holes, no boundary drift)
  // σ=8, symmetric thresholds 80/175 → fills holes ≤ ~16px diameter, net drift ≈ 0px.
  const HFR=8;
  const hd1=mk(W,H), hc1=hd1.getContext('2d');
  hc1.filter=`blur(${HFR}px)`; hc1.drawImage(binC,0,0);
  let img=hc1.getImageData(0,0,W,H), d=img.data;
  for(let i=0;i<d.length;i+=4){const v=d[i]>80?255:0;d[i]=d[i+1]=d[i+2]=v;d[i+3]=255;}
  hc1.putImageData(img,0,0);
  const hd2=mk(W,H), hc2=hd2.getContext('2d');
  hc2.filter=`blur(${HFR}px)`; hc2.drawImage(hd1,0,0);
  img=hc2.getImageData(0,0,W,H); d=img.data;
  for(let i=0;i<d.length;i+=4){const v=d[i]>175?255:0;d[i]=d[i+1]=d[i+2]=v;d[i+3]=255;}
  hc2.putImageData(img,0,0);
  const clD=hc2.getImageData(0,0,W,H).data; // hole-filled binary mask

  // ── Step 3: Build refined soft mask ─────────────────────────────────────
  // Keep raw gradient values everywhere (preserves natural hair transparency),
  // except where a hole was filled (closed=255 but raw was low) → force 255.
  const soft=mk(W,H), sc2=soft.getContext('2d');
  const si=sc2.createImageData(W,H);
  for(let i=0;i<si.data.length;i+=4){
    si.data[i]=si.data[i+1]=si.data[i+2]=(clD[i]>127&&rawD[i]<100)?255:rawD[i];
    si.data[i+3]=255;
  }
  sc2.putImageData(si,0,0);

  // ── Step 4: Sigmoid sharpening (K=9, center 0.46) ───────────────────────
  // Steepens the edge transition to reduce the contaminated fringe zone.
  // K=9 produces a crisper edge; center 0.46 trims slight colour fringe.
  img=sc2.getImageData(0,0,W,H); d=img.data;
  for(let i=0;i<d.length;i+=4){
    const t=d[i]/255;
    d[i]=d[i+1]=d[i+2]=Math.round(255/(1+Math.exp(-9*(t-0.46))));
    d[i+3]=255;
  }
  sc2.putImageData(img,0,0);
  // 'soft' = sharpened mask, holes filled, boundary unchanged

  if(edgeVal===0) return soft;

  // ── For + / − : binarize the sharpened mask then dilate / erode ──────────
  const bin=mk(W,H), bc=bin.getContext('2d');
  bc.drawImage(soft,0,0);
  img=bc.getImageData(0,0,W,H); d=img.data;
  for(let i=0;i<d.length;i+=4){const v=d[i]>128?255:0;d[i]=d[i+1]=d[i+2]=v;d[i+3]=255;}
  bc.putImageData(img,0,0);

  if(edgeVal>0){
    const r=edgeVal/50*8+0.5;
    const ex=mk(W,H), xc=ex.getContext('2d');
    xc.filter=`blur(${r}px)`; xc.drawImage(bin,0,0);
    img=xc.getImageData(0,0,W,H); d=img.data;
    const thr=Math.max(8,160-edgeVal/50*152);
    for(let i=0;i<d.length;i+=4){const v=d[i]>=thr?255:0;d[i]=d[i+1]=d[i+2]=v;d[i+3]=255;}
    xc.putImageData(img,0,0);
    const sf=mk(W,H),sfc=sf.getContext('2d');
    sfc.filter='blur(0.8px)'; sfc.drawImage(ex,0,0);
    return sf;
  }else{
    const strength=(-edgeVal)/50;
    const r=strength*12+0.5, thr=128+strength*120;
    const et=mk(W,H), tc=et.getContext('2d');
    tc.filter=`blur(${r}px)`; tc.drawImage(bin,0,0);
    img=tc.getImageData(0,0,W,H); d=img.data;
    for(let i=0;i<d.length;i+=4){const v=d[i]>=thr?255:0;d[i]=d[i+1]=d[i+2]=v;d[i+3]=255;}
    tc.putImageData(img,0,0);
    const sf=mk(W,H),sfc=sf.getContext('2d');
    sfc.filter='blur(0.8px)'; sfc.drawImage(et,0,0);
    return sf;
  }
}

async function getSegMask(canvas,edgeVal=0){
  const raw=await getSegMaskRaw(canvas); return processMask(raw,edgeVal);
}

function compositeWithBg(canvas,maskC,replColor){
  if(!maskC) return;
  const W=canvas.width, H=canvas.height, ctx=canvas.getContext('2d');
  const msk=maskC.getContext('2d').getImageData(0,0,W,H).data;
  const id=ctx.getImageData(0,0,W,H); const d=id.data;
  const[nR,nG,nB]=hexRGB(replColor); // new background color

  // Estimate old background color from clearly-background pixels (mask < 20).
  // This lets us do exact color decontamination on edge pixels.
  let eR=0,eG=0,eB=0,eCnt=0;
  for(let i=0;i<d.length;i+=4){
    if(msk[i]<20){ eR+=d[i]; eG+=d[i+1]; eB+=d[i+2]; eCnt++; }
  }
  const hasBg=eCnt>=60;
  const oR=hasBg?eR/eCnt:nR, oG=hasBg?eG/eCnt:nG, oB=hasBg?eB/eCnt:nB;

  for(let i=0;i<d.length;i+=4){
    const p=msk[i]/255;
    if(hasBg&&p>0.06&&p<0.94){
      // Exact decontamination:
      // pixel ≈ p*person + (1-p)*old_bg
      // result = p*person + (1-p)*new_bg = pixel + (new_bg − old_bg)*(1−p)
      d[i]  =cl(d[i]  +(nR-oR)*(1-p));
      d[i+1]=cl(d[i+1]+(nG-oG)*(1-p));
      d[i+2]=cl(d[i+2]+(nB-oB)*(1-p));
    }else{
      d[i]  =cl(d[i]*p  +nR*(1-p));
      d[i+1]=cl(d[i+1]*p+nG*(1-p));
      d[i+2]=cl(d[i+2]*p+nB*(1-p));
    }
    d[i+3]=255;
  }
  ctx.putImageData(id,0,0);
}

/* ── model loaders ── */
async function loadModelsQuiet(){
  try{
    await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_CDN);
    await faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_CDN);
    return true;
  }catch(e){ return false; }
}
async function loadModelsUI(){
  if(modelsLoaded) return true;
  const w=document.getElementById('cpMdlLoad'),bar=document.getElementById('cpMdlBar'),st=document.getElementById('cpMdlStatus');
  w.classList.add('show');
  try{
    st.textContent='Loading face detector…'; bar.style.width='25%';
    await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_CDN);
    bar.style.width='65%'; st.textContent='Loading landmark model…';
    await faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_CDN);
    bar.style.width='100%'; st.textContent='Models ready!';
    setTimeout(()=>w.classList.remove('show'),700);
    modelsLoaded=true; return true;
  }catch(e){ st.textContent='Failed: '+e.message; bar.style.background='#ef4444'; return false; }
}

/* ── standard face detection — original parameters that work well for normal photos ── */
async function detectFace(canvas){
  try{
    const det=await faceapi
      .detectSingleFace(canvas,new faceapi.TinyFaceDetectorOptions({inputSize:416,scoreThreshold:0.42}))
      .withFaceLandmarks(true);
    return det||null;
  }catch(e){ return null; }
}

/* ── document/form detection: lower thresholds + quadrant search for small corner photos ──
   Step 1 tries full image at reduced thresholds (printed photos have lower contrast).
   Step 2 crops 4 overlapping 65% quadrants — face becomes 2× larger relative to frame,
   pushing it well past the detection threshold. Coordinates mapped back to full-image space. ── */
async function detectFaceInDocument(canvas){
  // Full-image passes with reduced thresholds for printed/scanned photos
  for(const opts of [
    {inputSize:608,scoreThreshold:0.30},
    {inputSize:416,scoreThreshold:0.22},
    {inputSize:320,scoreThreshold:0.18},
  ]){
    try{
      const det=await faceapi
        .detectSingleFace(canvas,new faceapi.TinyFaceDetectorOptions(opts))
        .withFaceLandmarks(true);
      if(det) return det;
    }catch(e){}
  }

  // Quadrant search: four 65% crops with 30% overlap each direction
  const W=canvas.width, H=canvas.height;
  const regions=[
    [0,              0,              Math.ceil(W*.65), Math.ceil(H*.65)],
    [Math.floor(W*.35), 0,           W,                Math.ceil(H*.65)],
    [0,              Math.floor(H*.35), Math.ceil(W*.65), H           ],
    [Math.floor(W*.35), Math.floor(H*.35), W,          H             ],
  ];
  for(const [x1,y1,x2,y2] of regions){
    const rW=x2-x1, rH=y2-y1;
    const crop=mk(rW,rH);
    crop.getContext('2d').drawImage(canvas,x1,y1,rW,rH,0,0,rW,rH);
    const det=await detectFace(crop); // face is 2× bigger in crop — standard threshold works
    if(det){
      const b=det.detection.box;
      const le=det.landmarks?.getLeftEye();
      const re=det.landmarks?.getRightEye();
      return{
        detection:{box:{x:b.x+x1,y:b.y+y1,width:b.width,height:b.height}},
        landmarks:det.landmarks?{
          getLeftEye:()=>le.map(p=>({x:p.x+x1,y:p.y+y1})),
          getRightEye:()=>re.map(p=>({x:p.x+x1,y:p.y+y1})),
        }:null
      };
    }
  }
  return null;
}

/* ── processing ── */
async function startProcessing(){
  if(running) return;
  running=true; sessionCnt=0; errLog=[];
  zipOut=(outDir==='ZIP')?new JSZip():null;
  document.getElementById('cpThumbStrip').innerHTML='<div style="font-size:.76rem;color:var(--cp-muted);padding:.35rem 0;" data-ph>Processing…</div>';
  document.getElementById('cpStSess').textContent='0';
  document.getElementById('cpDoneBanner').classList.remove('show');
  document.getElementById('cpElog').innerHTML=''; document.getElementById('cpElog').classList.remove('show');
  showStep(3);
  document.getElementById('cpCurFile').textContent='Loading AI face models…';
  await loadModelsUI();
  const s=getSettings();
  if(s.bgRemove&&!bgModelLoaded){
    document.getElementById('cpCurFile').textContent='Loading background removal model…';
    await loadBgModel();
  }
  fetch(STATS_API+'?action=my_stats').then(r=>r.json()).then(d=>{
    if(d.success) document.getElementById('cpStAll').textContent=(d.totals?.photo_crop||0).toLocaleString();
  }).catch(()=>{});
  const total=imgFiles.length;
  let done=0;
  for(const info of imgFiles){
    updProg(done,total,info.name);
    try{ await processOne(info,s); sessionCnt++; document.getElementById('cpStSess').textContent=sessionCnt; }
    catch(e){ errLog.push(`${info.name}: ${e.message}`); addThumb(null,info.name,'err'); }
    done++; updProg(done,total,done<total?(imgFiles[done]?.name??''):'Finalizing…');
    await sleep(8);
  }
  if(sessionCnt>0){
    try{
      const fd=new FormData();
      fd.append('action','record'); fd.append('csrf_token',CSRF_TOKEN);
      fd.append('action_type','photo_crop'); fd.append('panel','icard'); fd.append('count',sessionCnt);
      const d = window.smartFetch
        ? await window.smartFetch(STATS_API,fd,{label:'Usage stats: '+sessionCnt+' photos cropped',panel:'photocrop'})
        : await (await fetch(STATS_API,{method:'POST',body:fd})).json();
      if(d.success && !d.queued){ const r2=await fetch(STATS_API+'?action=my_stats'), d2=await r2.json();
        if(d2.success) document.getElementById('cpStAll').textContent=(d2.totals?.photo_crop||0).toLocaleString(); }
    }catch(e){}
  }
  if(errLog.length){
    const el=document.getElementById('cpElog');
    el.innerHTML=errLog.map(l=>`<div style="padding:2px 0;border-bottom:1px solid #f3f4f6;"><i class="bi bi-x-circle text-danger me-1"></i>${esc(l)}</div>`).join('');
    el.classList.add('show');
  }
  let zipMsg='';
  if(zipOut&&sessionCnt>0){
    document.getElementById('cpCurFile').textContent='Preparing ZIP…';
    try{
      const zipBlob=await zipOut.generateAsync({type:'blob'});
      saveAs(zipBlob,'cropped_photos.zip');
      zipMsg=' · downloaded as ZIP';
    }catch(e){ errLog.push('ZIP creation failed: '+e.message); }
  }
  document.getElementById('cpDoneBanner').classList.add('show');
  document.getElementById('cpDoneMsg').textContent=
    `${sessionCnt} photo${sessionCnt!==1?'s':''} cropped`+zipMsg+(errLog.length?` · ${errLog.length} skipped`:'');
  running=false;
}

async function processOne(info,s){
  setBadges([['info','Loading']]);
  const bmp=await createImageBitmap(info.file);
  const isOrig=s.sizeMode==='original';
  // Original mode: preserve full resolution (cap at 8000 for memory); crop mode: 4× oversampling
  const maxD=isOrig?Math.min(Math.max(bmp.width,bmp.height),8000):Math.max(s.outW*4,3600);
  const sc=Math.min(1,maxD/Math.max(bmp.width,bmp.height));
  const sW=Math.round(bmp.width*sc), sH=Math.round(bmp.height*sc);
  const srcC=hiQResize(bmp,sW,sH); bmp.close?.();
  const ctx=srcC.getContext('2d');
  if(s.awb) applyAutoWB(ctx,sW,sH);
  if(s.ae)  applyAutoExposure(ctx,sW,sH);
  setBadges([['ok','Loaded'],['info','Detecting…']]);
  let faceBox=null, tiltDeg=0;
  if(modelsLoaded){
    try{
      const det=await (s.formMode?detectFaceInDocument:detectFace)(srcC);
      if(det){
        faceBox=det.detection.box;
        if(s.autoTilt&&det.landmarks){
          const le=det.landmarks.getLeftEye(), re=det.landmarks.getRightEye();
          const angle=Math.atan2(favg(re,'y')-favg(le,'y'),favg(re,'x')-favg(le,'x'));
          tiltDeg=+(angle*180/Math.PI).toFixed(1);
          if(Math.abs(angle)>0.008){
            const rot=mk(sW,sH), rc=rot.getContext('2d');
            rc.imageSmoothingQuality='high'; rc.translate(sW/2,sH/2); rc.rotate(-angle); rc.translate(-sW/2,-sH/2); rc.drawImage(srcC,0,0);
            ctx.clearRect(0,0,sW,sH); ctx.drawImage(rot,0,0);
            const d2=await faceapi.detectSingleFace(srcC,new faceapi.TinyFaceDetectorOptions({inputSize:416,scoreThreshold:0.42}));
            if(d2) faceBox=d2.detection.box;
          }
        }
        const bs=[['ok','Face']];
        if(tiltDeg&&Math.abs(tiltDeg)>0.5) bs.push(['ok',`Tilt ${tiltDeg}°`]);
        setBadges(bs);
      }else{ setBadges([['warn','No Face'],['info',isOrig?'Cleaning':'Fallback']]); }
    }catch(e){ setBadges([['warn','Detection failed']]); }
  }
  if(!isOrig&&!faceBox&&!s.fallback) throw new Error('No face, fallback disabled');
  setBadges([['ok','Detected'],['info',isOrig?'Processing…':'Cropping…']]);
  let outC, outW, outH;
  if(isOrig){
    // Original mode: no crop/resize — pass through at full working resolution
    outC=mk(sW,sH); outC.getContext('2d').drawImage(srcC,0,0);
    outW=sW; outH=sH;
  }else{
    const ratio=s.outW/s.outH;
    outC=cropPass(srcC,sW,sH,faceBox,s.outW,s.outH,s.bgColor,ratio);
    autoSharpen(outC,s.outW,s.outH,30);
    outW=s.outW; outH=s.outH;
  }
  let bgMask=null;
  if(s.bgRemove&&bgModelLoaded){
    setBadges([['ok',isOrig?'Processed':'Cropped'],['info','Segmenting…']]);
    bgMask=await getSegMask(outC,s.bg_smooth);
  }
  setBadges([['ok',isOrig?'Processed':'Cropped'],['info','Filters…']]);
  const outRatio=outW/outH;
  // In original mode faceBox is already in output coords (no crop mapping needed)
  const fbo=faceBox?(isOrig
    ?{x:Math.round(faceBox.x*outW/sW),y:Math.round(faceBox.y*outH/sH),width:Math.round(faceBox.width*outW/sW),height:Math.round(faceBox.height*outH/sH)}
    :mapFaceOut(faceBox,sW,sH,outW,outH,outRatio)):null;
  applyAllFilters(outC,fbo,s);
  if(bgMask){ setBadges([['ok','Filters'],['info','Removing BG…']]); compositeWithBg(outC,bgMask,s.bgColor); }
  setBadges([['ok','Done'],['info','Saving…']]);
  // Preserve exact input filename; non-JPEG/PNG formats (webp/bmp/tiff) convert to .jpg
  const dotIdx=info.name.lastIndexOf('.');
  const baseName=dotIdx>=0?info.name.slice(0,dotIdx):info.name;
  const origExt=dotIdx>=0?info.name.slice(dotIdx):'';
  const isPng=/\.png$/i.test(info.name);
  const mime=isPng?'image/png':'image/jpeg';
  const outExt=isPng?origExt:(/\.(webp|bmp|tiff?)$/i.test(origExt)?'.jpg':origExt);
  const outName=baseName+s.suffix+outExt;
  // Original mode always saves at full quality (1.0); otherwise use user setting
  const blob=await new Promise(r=>outC.toBlob(r,mime,isPng?undefined:s.jpegQ));
  if(outDir==='ZIP'){
    zipOut.file(outName,blob);
  }else{
    const fh=await outDir.getFileHandle(outName,{create:true});
    const wr=await fh.createWritable(); await wr.write(blob); await wr.close();
  }
  addThumb(outC.toDataURL('image/jpeg',0.3),outName,'ok');
  setBadges([['ok','Saved']]);
}

/* ── crop / resize / sharpen ── */
function cropPass(srcC,sW,sH,fb,outW,outH,bgCol,ratio){
  const out=mk(outW,outH), ctx=out.getContext('2d');
  ctx.imageSmoothingEnabled=true; ctx.imageSmoothingQuality='high';
  ctx.fillStyle=bgCol; ctx.fillRect(0,0,outW,outH);
  const r=getCropRect(sW,sH,fb,outW,outH);
  const sx=Math.max(0,r.x), sy=Math.max(0,r.y);
  const sw=Math.min(r.w-(sx-r.x),sW-sx), sh=Math.min(r.h-(sy-r.y),sH-sy);
  const dy=r.y<0?Math.round((-r.y/r.h)*outH):0, dh=Math.round((sh/r.h)*outH);
  ctx.drawImage(srcC,sx,sy,sw,sh,0,dy,outW,dh);
  return out;
}
function mapFaceOut(fb,sW,sH,outW,outH,ratio){
  const cH=fb.height/0.54, cW=cH*ratio;
  const scX=outW/cW, scY=outH/cH, cropX=fb.x+fb.width/2-cW/2;
  return{x:Math.max(0,Math.round((fb.x-cropX)*scX)),y:Math.round(outH*0.28),
         width:Math.min(outW,Math.round(fb.width*scX*1.08)),height:Math.min(outH*0.65,Math.round(fb.height*scY))};
}
function hiQResize(src,targetW,targetH){
  let W=src.width||targetW, H=src.height||targetH;
  let cur=mk(W,H); cur.getContext('2d').drawImage(src,0,0);
  while(W>targetW*2||H>targetH*2){
    const nW=Math.max(targetW,Math.floor(W/2)), nH=Math.max(targetH,Math.floor(H/2));
    const tmp=mk(nW,nH), tc=tmp.getContext('2d');
    tc.imageSmoothingEnabled=true; tc.imageSmoothingQuality='high'; tc.drawImage(cur,0,0,nW,nH);
    cur=tmp; W=nW; H=nH;
  }
  if(W!==targetW||H!==targetH){
    const fin=mk(targetW,targetH), fc=fin.getContext('2d');
    fc.imageSmoothingEnabled=true; fc.imageSmoothingQuality='high'; fc.drawImage(cur,0,0,targetW,targetH);
    return fin;
  }
  return cur;
}
function autoSharpen(canvas,W,H,amount){
  const ctx=canvas.getContext('2d'), orig=ctx.getImageData(0,0,W,H);
  const off=mk(W,H), oc=off.getContext('2d');
  oc.filter='blur(0.6px)'; oc.drawImage(canvas,0,0);
  const blur=oc.getImageData(0,0,W,H), sf=amount/100;
  const od=orig.data, bd=blur.data;
  for(let i=0;i<od.length;i+=4){ od[i]=cl(od[i]+sf*(od[i]-bd[i])); od[i+1]=cl(od[i+1]+sf*(od[i+1]-bd[i+1])); od[i+2]=cl(od[i+2]+sf*(od[i+2]-bd[i+2])); }
  ctx.putImageData(orig,0,0);
}

/* ── filter pipeline ── */
function applyAllFilters(canvas,fb,s){
  const ctx=canvas.getContext('2d'), W=canvas.width, H=canvas.height;
  if(s.noise_reduce>0) blurCanvas(canvas,ctx,W,H,s.noise_reduce/40);
  const needPix=s.brightness!==100||s.contrast!==100||s.saturation!==100||s.warmth!==0||s.vibrance>0||s.hdr>0||s.clarity>0;
  if(needPix) pixelAdjust(ctx,W,H,s);
  if(s.sharpness>0) unsharpMask(canvas,ctx,W,H,s.sharpness);
  if(fb){ if(s.face_smooth>0) faceBlur(canvas,ctx,W,H,fb,s.face_smooth); if(s.skin_glow>0) faceGlow(ctx,W,H,fb,s.skin_glow); if(s.face_brighten>0) faceBrighten(ctx,W,H,fb,s.face_brighten); }
}
const cl=v=>v<0?0:v>255?255:Math.round(v);
function pixelAdjust(ctx,W,H,s){
  const img=ctx.getImageData(0,0,W,H), d=img.data;
  const bF=s.brightness/100, cAdj=s.contrast-100;
  const cF=cAdj!==0?(259*(cAdj+255))/(255*(259-cAdj)):1;
  const lut=new Uint8Array(256);
  for(let i=0;i<256;i++){let v=i*bF;if(cF!==1)v=cF*(v-128)+128;lut[i]=cl(v);}
  const sF=s.saturation/100,vF=s.vibrance/100,hF=s.hdr/100,cLF=s.clarity/200;
  const wR=s.warmth*1.4,wG=s.warmth*0.25,wB=-s.warmth*0.9;
  for(let i=0;i<d.length;i+=4){
    let r=lut[d[i]],g=lut[d[i+1]],b=lut[d[i+2]];
    if(sF!==1){const gr=0.299*r+0.587*g+0.114*b;r=cl(gr+sF*(r-gr));g=cl(gr+sF*(g-gr));b=cl(gr+sF*(b-gr));}
    if(s.warmth!==0){r=cl(r+wR);g=cl(g+wG);b=cl(b+wB);}
    if(vF>0){const mx=Math.max(r,g,b),mn=Math.min(r,g,b),sat=(mx-mn)/Math.max(mx,1);const bo=vF*(1-sat),gr2=0.299*r+0.587*g+0.114*b;r=cl(gr2+(1+bo)*(r-gr2));g=cl(gr2+(1+bo)*(g-gr2));b=cl(gr2+(1+bo)*(b-gr2));}
    if(hF>0){const lm=0.299*r+0.587*g+0.114*b;const cu=lm<128?lm*(1-hF*0.22):lm+hF*0.22*(255-lm);const ra=lm>0?cu/lm:1;r=cl(r*ra);g=cl(g*ra);b=cl(b*ra);}
    if(cLF>0){const lm2=0.299*r+0.587*g+0.114*b;if(lm2>45&&lm2<210){r=cl(r+(r-128)*cLF);g=cl(g+(g-128)*cLF);b=cl(b+(b-128)*cLF);}}
    d[i]=r;d[i+1]=g;d[i+2]=b;
  }
  ctx.putImageData(img,0,0);
}
function applyAutoWB(ctx,W,H){
  const img=ctx.getImageData(0,0,W,H),d=img.data,rs=[],gs=[],bs=[];
  for(let i=0;i<d.length;i+=16){rs.push(d[i]);gs.push(d[i+1]);bs.push(d[i+2]);}
  rs.sort((a,b)=>a-b);gs.sort((a,b)=>a-b);bs.sort((a,b)=>a-b);
  const clip=Math.max(1,Math.round(rs.length*0.008));
  const mR=smean(rs,clip),mG=smean(gs,clip),mB=smean(bs,clip),avg=(mR+mG+mB)/3;
  const sR=Math.min(1.8,Math.max(0.6,avg/Math.max(mR,1)));
  const sG=Math.min(1.8,Math.max(0.6,avg/Math.max(mG,1)));
  const sB=Math.min(1.8,Math.max(0.6,avg/Math.max(mB,1)));
  for(let i=0;i<d.length;i+=4){d[i]=cl(d[i]*sR);d[i+1]=cl(d[i+1]*sG);d[i+2]=cl(d[i+2]*sB);}
  ctx.putImageData(img,0,0);
}
function applyAutoExposure(ctx,W,H){
  const img=ctx.getImageData(0,0,W,H),d=img.data,n=d.length/4,hist=new Int32Array(256);
  for(let i=0;i<d.length;i+=4) hist[Math.round(0.299*d[i]+0.587*d[i+1]+0.114*d[i+2])]++;
  const lo=n*0.006,hi=n*0.994;let cum=0,minL=0,maxL=255;
  for(let i=0;i<256;i++){cum+=hist[i];if(cum<=lo)minL=i;if(cum<=hi)maxL=i;}
  if(maxL-minL<20) return;
  const lut=new Uint8Array(256);
  for(let i=0;i<256;i++) lut[i]=cl((i-minL)*255/(maxL-minL));
  for(let i=0;i<d.length;i+=4){d[i]=lut[d[i]];d[i+1]=lut[d[i+1]];d[i+2]=lut[d[i+2]];}
  ctx.putImageData(img,0,0);
}
function blurCanvas(canvas,ctx,W,H,r){const o=mk(W,H),oc=o.getContext('2d');oc.filter=`blur(${Math.max(0.5,r)}px)`;oc.drawImage(canvas,0,0);ctx.drawImage(o,0,0);}
function unsharpMask(canvas,ctx,W,H,amount){
  const orig=ctx.getImageData(0,0,W,H),o=mk(W,H),oc=o.getContext('2d');
  oc.filter='blur(1px)';oc.drawImage(canvas,0,0);
  const blur=oc.getImageData(0,0,W,H),sf=amount/100*1.75,od=orig.data,bd=blur.data;
  for(let i=0;i<od.length;i+=4){od[i]=cl(od[i]+sf*(od[i]-bd[i]));od[i+1]=cl(od[i+1]+sf*(od[i+1]-bd[i+1]));od[i+2]=cl(od[i+2]+sf*(od[i+2]-bd[i+2]));}
  ctx.putImageData(orig,0,0);
}
function faceBlur(canvas,ctx,W,H,fb,amount){
  const{x,y,w:fw,h:fh}=sfb(fb,W,H);if(fw<4||fh<4)return;
  const orig=ctx.getImageData(x,y,fw,fh),o=mk(W,H),oc=o.getContext('2d');
  oc.filter=`blur(${Math.round(amount/14)+1}px)`;oc.drawImage(canvas,0,0);
  const blr=oc.getImageData(x,y,fw,fh),al=amount/100*0.80,od=orig.data,bd=blr.data,res=ctx.createImageData(fw,fh),rd=res.data;
  for(let i=0;i<od.length;i+=4){rd[i]=Math.round(od[i]*(1-al)+bd[i]*al);rd[i+1]=Math.round(od[i+1]*(1-al)+bd[i+1]*al);rd[i+2]=Math.round(od[i+2]*(1-al)+bd[i+2]*al);rd[i+3]=255;}
  ctx.putImageData(res,x,y);
}
function faceGlow(ctx,W,H,fb,amount){const{x,y,w:fw,h:fh}=sfb(fb,W,H);if(fw<4||fh<4)return;const img=ctx.getImageData(x,y,fw,fh),d=img.data,gf=amount/100;for(let i=0;i<d.length;i+=4){d[i]=cl(d[i]+gf*20);d[i+1]=cl(d[i+1]+gf*14);d[i+2]=cl(d[i+2]+gf*5);}ctx.putImageData(img,x,y);}
function faceBrighten(ctx,W,H,fb,amount){const{x,y,w:fw,h:fh}=sfb(fb,W,H);if(fw<4||fh<4)return;const img=ctx.getImageData(x,y,fw,fh),d=img.data,bf=1+amount/100*0.42;for(let i=0;i<d.length;i+=4){d[i]=cl(d[i]*bf);d[i+1]=cl(d[i+1]*bf);d[i+2]=cl(d[i+2]*bf);}ctx.putImageData(img,x,y);}
function sfb(fb,W,H){const x=Math.max(0,Math.round(fb.x)),y=Math.max(0,Math.round(fb.y));return{x,y,w:Math.min(W-x,Math.round(fb.width)),h:Math.min(H-y,Math.round(fb.height))};}

/* ── utils ── */
function mk(w,h){const c=document.createElement('canvas');c.width=w;c.height=h;return c;}
function favg(pts,k){return pts.reduce((s,p)=>s+p[k],0)/pts.length;}
function smean(arr,clip){return arr.slice(clip,-clip).reduce((s,v)=>s+v,0)/Math.max(arr.length-2*clip,1);}
function sleep(ms){return new Promise(r=>setTimeout(r,ms));}
function esc(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function hexRGB(hex){return[parseInt(hex.slice(1,3),16),parseInt(hex.slice(3,5),16),parseInt(hex.slice(5,7),16)];}
function updProg(done,total,name){
  const pct=total>0?Math.round(done/total*100):0;
  document.getElementById('cpMainBar').style.width=pct+'%';
  document.getElementById('cpProgTxt').textContent=`${done} / ${total}`;
  document.getElementById('cpProgPct').textContent=pct+'%';
  if(name) document.getElementById('cpCurFile').textContent=name;
}
function setBadges(items){ document.getElementById('cpBadges').innerHTML=items.map(([t,l])=>`<span class="cpb ${t}">${l}</span>`).join(''); }
function addThumb(url,name,st){
  const s=document.getElementById('cpThumbStrip');
  const ph=s.querySelector('[data-ph]'); if(ph) ph.remove();
  const d=document.createElement('div'); d.className='cp-ti'; d.title=name;
  d.innerHTML=(url?`<img src="${url}" alt="">`:'<div style="background:#fee2e2;width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><i class="bi bi-x text-danger"></i></div>')+`<div class="cp-tis ${st}">${st==='ok'?'✓':'✗'}</div>`;
  s.insertBefore(d,s.firstChild);
  while(s.children.length>12) s.removeChild(s.lastChild);
}
function resetAll(){
  inDir=null;outDir=null;zipOut=null;imgFiles=[];sessionCnt=0;errLog=[];
  s1Bitmap=null;prevBitmap=null;prevBase=null;prevOrig=null;
  prevBgMaskRaw=null;prevBgMask=null;prevFaceOut=null;
  ['cpInCard','cpOutCard'].forEach(id=>document.getElementById(id).classList.remove('sel'));
  document.getElementById('cpInSub').textContent='Click to select photos';
  document.getElementById('cpOutSub').textContent='Click to select save location';
  document.getElementById('cpInPath').textContent='';
  document.getElementById('cpOutPath').textContent='';
  document.getElementById('cpElog').classList.remove('show');
  document.getElementById('cpS1Canvas').style.display='none';
  document.getElementById('cpS1Empty').style.display='block';
  document.getElementById('cpS1Tags').innerHTML='';
  document.getElementById('cpS1Fname').textContent='';
  refreshS1(); showStep(1);
}
</script>
<?php require_once __DIR__ . '/icard_footer.php'; ?>
