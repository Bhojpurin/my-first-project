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
/* ── new: composition / nav / results / editor ── */
.cp-prev-nav{display:flex;align-items:center;justify-content:space-between;gap:.3rem;margin-top:.45rem;}
.cp-nav-btn{width:30px;height:30px;border-radius:9px;border:1.5px solid var(--cp-border);background:#fff;cursor:pointer;color:var(--cp-text);display:inline-flex;align-items:center;justify-content:center;font-size:.9rem;transition:all .14s;}
.cp-nav-btn:hover:not(:disabled){border-color:#c4b5fd;background:var(--cp-purple-lt);color:var(--cp-purple);}
.cp-nav-btn:disabled{opacity:.35;cursor:default;}
.cp-nav-lbl{font-size:.7rem;font-weight:800;color:var(--cp-muted);}
.cp-guide-btn{font-size:.66rem;font-weight:800;padding:.2rem .55rem;border-radius:999px;border:1.5px solid var(--cp-border);background:#fff;color:var(--cp-muted);cursor:pointer;}
.cp-guide-btn.on{background:var(--cp-purple-lt);border-color:#c4b5fd;color:var(--cp-purple);}
.cp-ov{position:absolute;inset:0;width:100%;height:100%;pointer-events:none;}
.cp-mini-btn{font-size:.68rem;font-weight:700;padding:.25rem .6rem;border-radius:8px;border:1.5px solid var(--cp-border);background:#fff;color:var(--cp-muted);cursor:pointer;}
.cp-mini-btn:hover{border-color:#c4b5fd;color:var(--cp-purple);}
.cp-res-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(84px,1fr));gap:.5rem;margin-top:.8rem;max-height:430px;overflow-y:auto;padding:.15rem;}
.cp-res{position:relative;border-radius:11px;overflow:hidden;border:2px solid var(--cp-border);cursor:pointer;background:#faf9ff;transition:all .14s;}
.cp-res:hover{border-color:#a78bfa;transform:translateY(-2px);box-shadow:0 8px 18px -10px rgba(109,40,217,.4);}
.cp-res img{width:100%;display:block;aspect-ratio:var(--ar,35/45);object-fit:cover;}
.cp-res .nm{font-size:.56rem;color:var(--cp-muted);padding:2px 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cp-res .st{position:absolute;top:4px;right:4px;width:18px;height:18px;border-radius:50%;font-size:.6rem;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;}
.cp-res .st.ok{background:#16a34a;}.cp-res .st.warn{background:#d97706;}.cp-res .st.err{background:#dc2626;}.cp-res .st.edit{background:#7c3aed;}
.cp-res-bar{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;margin-top:.9rem;}
.cp-res-bar .cp-res-ttl{font-size:.8rem;font-weight:800;color:var(--cp-text);margin-right:auto;}
.cp-filter{font-size:.68rem;font-weight:700;padding:.25rem .65rem;border-radius:999px;border:1.5px solid var(--cp-border);background:#fff;color:var(--cp-muted);cursor:pointer;}
.cp-filter.on{background:var(--cp-grad);color:#fff;border-color:transparent;}
#cpEdOv{position:fixed;inset:0;z-index:9800;background:rgba(15,10,40,.62);display:none;align-items:center;justify-content:center;padding:1rem;}
#cpEdOv.show{display:flex;}
.cp-ed{background:#fff;border-radius:18px;max-width:760px;width:100%;max-height:94vh;overflow:auto;box-shadow:0 30px 80px rgba(0,0,0,.35);}
.cp-ed-hd{display:flex;align-items:center;gap:.5rem;padding:.75rem 1rem;border-bottom:1px solid var(--cp-border);}
.cp-ed-hd b{font-size:.88rem;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.cp-ed-body{display:grid;grid-template-columns:300px 1fr;gap:1rem;padding:1rem;}
@media(max-width:640px){.cp-ed-body{grid-template-columns:1fr;}}
.cp-ed-cv{position:relative;border-radius:12px;overflow:hidden;background:repeating-conic-gradient(#f1f5f9 0 25%,#fff 0 50%) 50%/16px 16px;cursor:grab;touch-action:none;}
.cp-ed-cv.drag{cursor:grabbing;}
.cp-ed-cv canvas{display:block;width:100%;height:auto;}
.cp-ed-pad{display:grid;grid-template-columns:repeat(3,34px);gap:4px;justify-content:center;margin:.4rem 0;}
.cp-ed-hint{font-size:.68rem;color:var(--cp-muted);line-height:1.5;}
.cp-ed-ft{display:flex;gap:.5rem;flex-wrap:wrap;padding:.75rem 1rem;border-top:1px solid var(--cp-border);}
.cp-stop{background:#fff;color:var(--cp-red);border:1.5px solid var(--cp-red-bd);}
.cp-stop:hover{background:var(--cp-red-lt);}
.cp-drop-hl{border-color:#8b5cf6!important;background:var(--cp-purple-lt)!important;}
</style>

<div class="cp-wrap">
<div class="cp-warn-bar" id="cpBrowserWarn">
  <i class="bi bi-info-circle-fill me-1"></i>
  <strong>Photo picker mode</strong> — this device/browser can't open folders directly, so pick photos below (or drag &amp; drop them) and the cropped results will download as a ZIP file.
</div>
<!-- Fallback for browsers/WebViews without the File System Access API
     (all mobile browsers, Firefox, Safari, the app's own WebView). A plain
     multi-file picker is the one behaviour every one of them supports. -->
<input type="file" id="cpInFallback" accept="image/*" multiple style="display:none" onchange="onFallbackFilesPicked(this)">

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
    <div class="cp-pill" id="sp3"><span class="cp-pnum">3</span> Processing &amp; Review</div>
  </div>
</div>

<!-- ══════════════ STEP 1 ══════════════ -->
<div id="cpStep1">
  <div class="cp-s1-grid">
    <div>
      <!-- Folder pickers -->
      <div class="cp-dir-grid">
        <div class="cp-dir-card" id="cpInCard" onclick="pickInputDir()" title="Click to choose — or drag &amp; drop photos here">
          <div class="cp-di-icon"><i class="bi bi-folder2-open"></i></div>
          <div class="cp-di-body">
            <div class="cp-di-label">Input Folder</div>
            <div class="cp-di-sub" id="cpInSub">Click to select · or drop photos here</div>
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
              <label>Photo Size</label>
              <select id="cpSize" onchange="onSizeChange()">
                <optgroup label="Passport / Visa">
                  <option value="35x45">India / EU passport (35×45 mm)</option>
                  <option value="51x51">USA (2×2 in · 51×51 mm)</option>
                  <option value="33x48">China (33×48 mm)</option>
                  <option value="50x70">Canada (50×70 mm)</option>
                  <option value="45x45">UK visa (45×45 mm)</option>
                  <option value="35x35">Australia / square (35×35 mm)</option>
                </optgroup>
                <optgroup label="ID card / school">
                  <option value="30x40">ID card photo (30×40 mm)</option>
                  <option value="25x35">Small ID / stamp (25×35 mm)</option>
                  <option value="20x25">Stamp size (20×25 mm)</option>
                  <option value="40x50">Large ID (40×50 mm)</option>
                </optgroup>
                <optgroup label="Other">
                  <option value="original">Original (no crop — enhance only)</option>
                  <option value="custom">Custom…</option>
                </optgroup>
              </select>
            </div>
            <div class="cp-sg">
              <label>Output DPI</label>
              <select id="cpDpi" onchange="onSizeChange()">
                <option value="150">150 DPI (web / screen)</option>
                <option value="300">300 DPI (standard print)</option>
                <option value="600" selected>600 DPI (best print ★)</option>
              </select>
            </div>
            <div class="cp-sg">
              <label>File Format</label>
              <select id="cpFmt" onchange="onSizeChange()">
                <option value="jpg" selected>JPEG (recommended)</option>
                <option value="png">PNG (lossless, larger)</option>
                <option value="auto">Same as input</option>
              </select>
            </div>
            <div class="cp-sg">
              <label>JPEG Quality</label>
              <select id="cpJpeg">
                <option value="0.88">88% — small file</option>
                <option value="0.94">94% — high</option>
                <option value="0.97" selected>97% — print (best balance)</option>
                <option value="1.0">100% — max</option>
              </select>
            </div>
          </div>
          <div id="cpCustomRow" style="display:none;gap:.6rem;margin-bottom:.6rem;">
            <div class="cp-sg" style="max-width:110px;"><label>Width (mm)</label><input type="number" id="cpCW" value="35" min="10" max="300" step="0.5" oninput="onSizeChange()"></div>
            <div class="cp-sg" style="max-width:110px;"><label>Height (mm)</label><input type="number" id="cpCH" value="45" min="10" max="300" step="0.5" oninput="onSizeChange()"></div>
          </div>
          <div id="cpSuffixRow" style="margin-bottom:.6rem;">
            <div style="font-size:.67rem;font-weight:800;color:var(--cp-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.22rem;">Filename Suffix <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional — appended before extension; same file name is kept so photos still match your Excel)</span></div>
            <input type="text" id="cpSuffix" placeholder='e.g.  _pp  →  photo_pp.jpg' maxlength="40" oninput="saveSettingsSoon()"
              style="width:100%;padding:.42rem .55rem;border:1.5px solid var(--cp-border);border-radius:var(--cp-radius-sm);font-size:.8rem;color:var(--cp-text);background:#fff;box-sizing:border-box;">
          </div>
          <div class="cp-sz-info">
            <i class="bi bi-aspect-ratio"></i>
            <span>Output: <strong id="cpSzInfo">827 × 1063 px</strong> &nbsp;@&nbsp; <strong id="cpDpiInfo">600 DPI</strong> <span id="cpSzMm" style="font-weight:600;opacity:.8;"></span></span>
          </div>
        </div>
      </div>

      <div style="background:#fff;border:1.5px solid var(--cp-border);border-radius:var(--cp-radius);padding:.7rem 1rem;margin-bottom:.85rem;display:flex;align-items:center;gap:.65rem;">
        <input type="checkbox" id="chk_formmode" onchange="onDetectModeChg()" style="accent-color:var(--cp-blue);width:17px;height:17px;flex-shrink:0;cursor:pointer;">
        <label for="chk_formmode" style="cursor:pointer;margin:0;line-height:1.35;">
          <span style="font-size:.84rem;font-weight:800;color:var(--cp-text);">Form / Document Mode</span>
          <span style="font-size:.72rem;color:var(--cp-muted);display:block;margin-top:.08rem;">Enable when photos are inside a filled form or scanned document — searches the whole page (any corner) for a small photo</span>
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
          <button id="s1BtnSrc" onclick="setS1Mode('source',this)">Source</button>
          <button class="active" id="s1BtnCrop" onclick="setS1Mode('crop',this)">Cropped</button>
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

    <!-- Left: live preview (exactly what will be saved) -->
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
          <canvas id="cpPrevOv" class="cp-ov" width="210" height="270"></canvas>
          <div id="cpPrevLoader" class="cp-prev-loader" style="display:none;">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <span id="cpPrevMsg" style="font-size:.72rem;color:var(--cp-muted);">Loading…</span>
          </div>
        </div>
        <div id="cpPrevTags" class="cp-tags"></div>
        <div class="cp-prev-nav">
          <button class="cp-nav-btn" id="cpPrevBack" onclick="prevNav(-1)" title="Previous photo"><i class="bi bi-chevron-left"></i></button>
          <span class="cp-nav-lbl" id="cpPrevIdx">1 / 1</span>
          <button class="cp-guide-btn" id="cpGuideBtn" onclick="toggleGuides()" title="Show head / eye-line guides">Guides</button>
          <button class="cp-nav-btn" id="cpPrevFwd" onclick="prevNav(1)" title="Next photo"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div id="cpPrevFname" class="cp-prev-fname"></div>
        <div style="font-size:.62rem;color:var(--cp-muted);text-align:center;margin-top:.25rem;">Preview = exactly the saved photo</div>
      </div>
    </div>

    <!-- Right: controls -->
    <div class="cp-ctrl-stack">

      <!-- Composition -->
      <div class="cp-card">
        <div class="cp-panel-hd purple"><i class="bi bi-person-square"></i> Face Position (precise)
          <button class="cp-mini-btn" style="margin-left:auto;" onclick="resetComposition()">Reset</button>
        </div>
        <div class="cp-panel-body">
          <div class="cp-sr">
            <div class="cp-sr-meta"><span class="cp-sr-name">Head Size <small style="color:var(--cp-muted);font-weight:400;">(crown → chin, % of photo height)</small></span><span class="cp-sr-val purple" id="v_head">74%</span></div>
            <input type="range" class="cp-range purple" id="s_head" min="50" max="90" value="74" oninput="v_head.textContent=this.value+'%';schedPrev()">
          </div>
          <div class="cp-sr">
            <div class="cp-sr-meta"><span class="cp-sr-name">Space Above Head</span><span class="cp-sr-val purple" id="v_top">8%</span></div>
            <input type="range" class="cp-range purple" id="s_top" min="2" max="25" value="8" oninput="v_top.textContent=this.value+'%';schedPrev()">
          </div>
          <div style="font-size:.66rem;color:var(--cp-muted);line-height:1.5;">Face is centred on the <b>eyes</b> and sized from <b>eye-line → chin</b> landmarks, so every photo in the batch gets the same head size &amp; position. Passport standard ≈ 70–80% head size.</div>
        </div>
      </div>

      <!-- Basic adjustments -->
      <div class="cp-card">
        <div class="cp-panel-hd blue"><i class="bi bi-sliders"></i> Basic Adjustments
          <button class="cp-mini-btn" style="margin-left:auto;" onclick="resetAdjustments()">Reset all</button>
        </div>
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
            <input type="range" class="cp-range" id="s_<?=$id?>" min="<?=$mn?>" max="<?=$mx?>" value="<?=$df?>" data-def="<?=$df?>" data-unit="<?=$u?>"
                   oninput="v_<?=$id?>.textContent=this.value+'<?=$u?>';schedPrev()" ondblclick="this.value=this.dataset.def;this.oninput()" title="Double-click to reset">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Portrait -->
      <div class="cp-card">
        <div class="cp-panel-hd green"><i class="bi bi-person-bounding-box"></i> Portrait Enhancement <small style="margin-left:auto;font-weight:600;color:var(--cp-muted);font-size:.64rem;">face-only, soft-edged</small></div>
        <div class="cp-panel-body">
          <?php foreach([
            ['face_smooth','Face Smooth (skin only)',0,100,0,'green'],
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
            <input type="range" class="cp-range green" id="s_<?=$id?>" min="<?=$mn?>" max="<?=$mx?>" value="<?=$df?>" data-def="<?=$df?>" data-unit="%"
                   oninput="v_<?=$id?>.textContent=this.value+'%';schedPrev()" ondblclick="this.value=this.dataset.def;this.oninput()" title="Double-click to reset">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Auto corrections -->
      <div class="cp-card">
        <div class="cp-panel-hd amber"><i class="bi bi-magic"></i> Auto Corrections</div>
        <div class="cp-panel-body">
          <div class="cp-chk-row">
            <input type="checkbox" id="chk_awb" checked onchange="schedPrev()">
            <label for="chk_awb">Auto White Balance<small>Neutral-tone based — skin tones stay natural on coloured backgrounds</small></label>
          </div>
          <div class="cp-chk-row">
            <input type="checkbox" id="chk_ae" checked onchange="schedPrev()">
            <label for="chk_ae">Auto Exposure<small>Face-aware — dark / over-bright faces corrected gently</small></label>
          </div>
          <div class="cp-chk-row">
            <input type="checkbox" id="chk_tilt" checked onchange="schedPrev()">
            <label for="chk_tilt">Auto Tilt Correction<small>Levels the eyes (up to ±20°) using face landmarks</small></label>
          </div>
          <div class="cp-chk-row">
            <input type="checkbox" id="chk_sharp" checked onchange="schedPrev()">
            <label for="chk_sharp">Print Sharpening<small>Light, halo-free sharpening tuned for the output size</small></label>
          </div>
          <div class="cp-chk-row">
            <input type="checkbox" id="chk_fallback" checked onchange="saveSettingsSoon()">
            <label for="chk_fallback">Centre-crop fallback<small>If no face is found, still crop (marked for review)</small></label>
          </div>
        </div>
      </div>

      <!-- Background removal + color -->
      <div class="cp-card">
        <div class="cp-panel-hd red"><i class="bi bi-person-fill-slash"></i> Background Removal (AI)</div>
        <div class="cp-panel-body">

          <div style="margin-bottom:.75rem;">
            <div style="font-size:.72rem;font-weight:700;color:var(--cp-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem;">Background / Fill Color</div>
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
            <div style="font-size:.68rem;color:var(--cp-muted);margin-top:.28rem;">Fills any area outside the original photo, and replaces the background when AI removal is on.</div>
          </div>

          <div style="border-top:1px solid var(--cp-border);padding-top:.7rem;">
            <div class="cp-chk-row" style="margin-bottom:.3rem;">
              <input type="checkbox" id="chk_bgremove" onchange="onBgToggle()">
              <label for="chk_bgremove"><strong>Enable AI Background Removal</strong><small>MediaPipe Selfie Segmentation — runs locally, nothing uploaded</small></label>
            </div>
            <div id="cpBgModelStatus" class="cp-model-badge"></div>

            <div class="cp-bg-sub" id="cpBgSub">
              <div class="cp-sr" style="margin-top:.5rem;">
                <div class="cp-sr-meta">
                  <span class="cp-sr-name">Edge Control</span>
                  <span class="cp-sr-val purple" id="v_bg_smooth">0</span>
                </div>
                <div class="cp-bipolar-wrap">
                  <input type="range" class="cp-range purple" id="s_bg_smooth"
                         min="-50" max="50" value="0"
                         oninput="onBgSmoothChange(this)"
                         style="background:#e5e7eb;">
                  <div class="cp-bipolar-center-tick"></div>
                </div>
                <div class="cp-bipolar-labels">
                  <span>← Tighter edge</span>
                  <span class="mid">0</span>
                  <span>Wider edge →</span>
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem;margin-top:.55rem;font-size:.68rem;color:var(--cp-muted);background:var(--cp-bg);border-radius:7px;padding:.45rem .6rem;">
                <div><strong style="color:var(--cp-red);">−</strong> Mask shrinks inward — removes leftover background fringe</div>
                <div><strong style="color:var(--cp-purple);">+</strong> Mask grows outward — keeps more hair / shoulders</div>
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
    <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;margin-top:.75rem;">
      <button class="cp-btn cp-btn-outline" id="cpZipBtn" onclick="downloadZip()" style="display:none;background:#fff;color:#0f766e;border-color:#fff;">
        <i class="bi bi-file-earmark-zip-fill"></i> Download ZIP
      </button>
      <button class="cp-btn cp-btn-outline" onclick="resetAll()" style="background:rgba(255,255,255,.16);color:#fff;border-color:rgba(255,255,255,.5);">
        <i class="bi bi-arrow-counterclockwise"></i> Process Another Batch
      </button>
    </div>
  </div>
  <div class="cp-mdl-load" id="cpMdlLoad">
    <div style="font-size:.82rem;font-weight:700;color:var(--cp-blue);margin-bottom:.3rem;"><i class="bi bi-cpu me-1"></i>Loading AI Models…</div>
    <div class="cp-prog-bar-bg"><div class="cp-prog-bar" id="cpMdlBar" style="width:0%"></div></div>
    <div style="font-size:.7rem;color:var(--cp-blue);margin-top:.2rem;" id="cpMdlStatus">Connecting…</div>
  </div>
  <div class="cp-prog-card">
    <div style="display:flex;align-items:flex-start;gap:.6rem;">
      <div class="cp-prog-cur" id="cpCurFile" style="flex:1;">Initializing…</div>
      <button class="cp-btn cp-stop" id="cpStopBtn" onclick="stopProcessing()" style="padding:.35rem .8rem;font-size:.74rem;"><i class="bi bi-stop-circle"></i> Stop</button>
    </div>
    <div class="cp-prog-bar-bg"><div class="cp-prog-bar" id="cpMainBar" style="width:0%"></div></div>
    <div class="cp-prog-lbl"><span id="cpProgTxt">0 / 0</span><span id="cpEta"></span><span id="cpProgPct">0%</span></div>
    <div class="cp-tags" id="cpBadges" style="margin-top:.4rem;"></div>

    <div class="cp-res-bar">
      <span class="cp-res-ttl"><i class="bi bi-grid-3x3-gap-fill" style="color:var(--cp-purple);"></i> Results — click any photo to fine-tune</span>
      <button class="cp-filter on" data-f="all" onclick="setResFilter('all',this)">All <span id="cpCntAll">0</span></button>
      <button class="cp-filter" data-f="review" onclick="setResFilter('review',this)">Needs review <span id="cpCntRev">0</span></button>
      <button class="cp-filter" data-f="err" onclick="setResFilter('err',this)">Failed <span id="cpCntErr">0</span></button>
    </div>
    <div class="cp-res-grid" id="cpResGrid">
      <div style="font-size:.76rem;color:var(--cp-muted);padding:.35rem 0;grid-column:1/-1;" data-ph>No outputs yet</div>
    </div>
  </div>
  <div class="cp-stats-row">
    <div class="cp-stat blue"><div class="cp-stat-n" id="cpStSess">0</div><div class="cp-stat-l">This session</div></div>
    <div class="cp-stat green"><div class="cp-stat-n" id="cpStAll">—</div><div class="cp-stat-l">My all-time total</div></div>
  </div>
  <div class="cp-elog" id="cpElog"></div>
</div>

<!-- ══════════════ MANUAL FINE-TUNE EDITOR ══════════════ -->
<div id="cpEdOv" onclick="if(event.target===this)closeEditor()">
  <div class="cp-ed">
    <div class="cp-ed-hd">
      <i class="bi bi-crop" style="color:var(--cp-purple);"></i>
      <b id="cpEdName">photo.jpg</b>
      <span id="cpEdPos" style="font-size:.7rem;color:var(--cp-muted);font-weight:700;"></span>
      <button class="cp-nav-btn" onclick="edNav(-1)" title="Previous (←)"><i class="bi bi-chevron-left"></i></button>
      <button class="cp-nav-btn" onclick="edNav(1)" title="Next (→)"><i class="bi bi-chevron-right"></i></button>
      <button class="cp-nav-btn" onclick="closeEditor()" title="Close (Esc)"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="cp-ed-body">
      <div>
        <div class="cp-ed-cv" id="cpEdCv"><canvas id="cpEdCanvas" width="300" height="386"></canvas><canvas id="cpEdOvc" class="cp-ov" width="300" height="386"></canvas></div>
        <div id="cpEdTags" class="cp-tags" style="margin-top:.4rem;"></div>
      </div>
      <div>
        <div class="cp-ed-hint" style="margin-bottom:.6rem;"><b>Drag</b> the photo to move · <b>mouse wheel / pinch</b> to zoom · arrow keys nudge (Shift = bigger). Changes are saved over the output file.</div>
        <div class="cp-sr">
          <div class="cp-sr-meta"><span class="cp-sr-name">Zoom</span><span class="cp-sr-val purple" id="v_edZoom">100%</span></div>
          <input type="range" class="cp-range purple" id="s_edZoom" min="60" max="200" value="100" oninput="edSet('zoom',this.value/100)">
        </div>
        <div class="cp-sr">
          <div class="cp-sr-meta"><span class="cp-sr-name">Rotate</span><span class="cp-sr-val purple" id="v_edRot">0°</span></div>
          <input type="range" class="cp-range purple" id="s_edRot" min="-20" max="20" value="0" step="0.5" oninput="edSet('rot',+this.value)">
        </div>
        <div class="cp-ed-pad">
          <span></span><button class="cp-nav-btn" onclick="edNudge(0,-1)"><i class="bi bi-arrow-up"></i></button><span></span>
          <button class="cp-nav-btn" onclick="edNudge(-1,0)"><i class="bi bi-arrow-left"></i></button>
          <button class="cp-nav-btn" onclick="edReset()" title="Back to automatic crop"><i class="bi bi-bullseye"></i></button>
          <button class="cp-nav-btn" onclick="edNudge(1,0)"><i class="bi bi-arrow-right"></i></button>
          <span></span><button class="cp-nav-btn" onclick="edNudge(0,1)"><i class="bi bi-arrow-down"></i></button><span></span>
        </div>
        <div class="cp-chk-row" style="margin-top:.5rem;">
          <input type="checkbox" id="chk_edGuides" checked onchange="edDraw()">
          <label for="chk_edGuides">Show guides<small>Crown, eye-line &amp; chin lines</small></label>
        </div>
      </div>
    </div>
    <div class="cp-ed-ft">
      <button class="cp-btn cp-btn-outline" onclick="edReset()"><i class="bi bi-arrow-counterclockwise"></i> Auto</button>
      <span style="flex:1;"></span>
      <button class="cp-btn cp-btn-outline" onclick="closeEditor()">Cancel</button>
      <button class="cp-btn cp-btn-success" id="cpEdSave" onclick="edSave()"><i class="bi bi-check2-circle"></i> Save photo</button>
    </div>
  </div>
</div>
</div><!-- /.cp-wrap -->

<!-- Self-hosted (assets/js/vendor/) so bulk cropping works with zero internet. -->
<script src="<?= BASE_URL ?>/assets/js/vendor/face-api-0.22.2.min.js"></script>
<script src="<?= $segLocal ?>/selfie_segmentation.js"></script>
<script src="<?= BASE_URL ?>/assets/js/vendor/jszip-3.10.1.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/vendor/filesaver-2.0.5.min.js"></script>
<script>
'use strict';
/* ═══════════════════ BULK PHOTO CROPPER ═══════════════════
   Pipeline (identical for the live preview, the batch and the manual editor,
   so what you see is exactly what gets saved):
     decode (EXIF-rotated) → detect face (SSD, falls back to Tiny) → refine
     landmarks on a close-up → geometry from eyes + chin (head size / top
     space / tilt) → one high-quality crop+rotate+resample from the ORIGINAL
     pixels → auto WB / exposure → (AI mask) → filters → print sharpen →
     background composite → JPEG/PNG with real DPI written into the file. */
const STATS_API  = <?= json_encode($statsApi) ?>;
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
const MODEL_CDN  = <?= json_encode(BASE_URL . '/assets/face-api-weights') ?>;
const SEG_LOCAL  = <?= json_encode($segLocal) ?>;
const SZ_MAP = {
  '35x45':{w:35,h:45},'51x51':{w:51,h:51},'33x48':{w:33,h:48},
  '50x70':{w:50,h:70},'45x45':{w:45,h:45},'35x35':{w:35,h:35},
  '30x40':{w:30,h:40},'25x35':{w:25,h:35},'20x25':{w:20,h:25},'40x50':{w:40,h:50}
};
const DEG=Math.PI/180;
const REF_AREA=413*531;   // filter strengths were tuned on a 35×45 mm @300 DPI photo
const CROWN_K=1.30;       // top of head INCLUDING hair ≈ eye-line − 1.30 × (eye-line → chin)
const MAX_TILT=20*DEG;
const IMG_RX=/\.(jpe?g|jfif|png|webp|bmp|gif|avif|tiff?)$/i;

/* ── state ── */
// inDir/outDir: real FileSystemDirectoryHandle (Chrome/Edge desktop) or the
// sentinels 'FALLBACK' / 'ZIP' (every other browser & the app's WebView).
const HAS_FSA='showDirectoryPicker' in window;
let inDir=null,outDir=null,imgFiles=[],modelsLoaded=false,_modelsP=null;
const DET={ssd:false,tiny:false,lm:null};
let zipOut=null,running=false,stopReq=false,sessionCnt=0,errLog=[];
let results=[],resFilter='all',usedNames=new Set();
let bgModel=null,bgModelLoaded=false,bgModelLoading=false;
let bgColor='#ffffff';
const anCache=new Map();            // file index → {face,W,H,form} (detection reused by the batch)
let s1An=null,s1Mode='crop',s1Timer=null,s1Tok=0;
let prevIdx=0,prevAn=null,prevMode='after',prevTimer=null,prevTok=0,showGuides=false,prevMaskCache={};

if(!HAS_FSA) document.getElementById('cpBrowserWarn').style.display='block';

/* ── tiny toast ── */
function toast(msg,type){
  let box=document.getElementById('cpToast');
  if(!box){ box=document.createElement('div'); box.id='cpToast'; box.style.cssText='position:fixed;right:1rem;bottom:1rem;z-index:9900;display:flex;flex-direction:column;gap:.4rem;max-width:340px;'; document.body.appendChild(box); }
  const c={ok:'#16a34a',warn:'#d97706',err:'#dc2626',info:'#4f46e5'}[type||'info'];
  const t=document.createElement('div');
  t.style.cssText=`background:${c};color:#fff;padding:.55rem .9rem;border-radius:11px;font-size:.78rem;font-weight:700;box-shadow:0 8px 24px rgba(0,0,0,.2);transition:opacity .3s;`;
  t.textContent=msg; box.appendChild(t);
  while(box.children.length>3) box.firstChild.remove();
  setTimeout(()=>{ t.style.opacity='0'; setTimeout(()=>t.remove(),320); },type==='err'||type==='warn'?5000:3000);
}

/* ── size helpers ── */
function getSizeMM(){
  const v=document.getElementById('cpSize').value;
  if(v==='custom'){
    const w=+document.getElementById('cpCW').value, h=+document.getElementById('cpCH').value;
    return{w:w>=10&&w<=300?w:35,h:h>=10&&h<=300?h:45};
  }
  return SZ_MAP[v]||{w:35,h:45};
}
function getDpi(){ return +document.getElementById('cpDpi').value||300; }
function getPxSize(){
  const{w,h}=getSizeMM(), dpi=getDpi();
  return{w:Math.round(w/25.4*dpi),h:Math.round(h/25.4*dpi)};
}
function onSizeChange(){
  const v=document.getElementById('cpSize').value;
  document.getElementById('cpCustomRow').style.display=v==='custom'?'flex':'none';
  const fmt=document.getElementById('cpFmt').value;
  document.getElementById('cpJpeg').disabled=fmt==='png';
  if(v==='original'){
    document.getElementById('cpSzInfo').textContent='Same as input image';
    document.getElementById('cpDpiInfo').textContent=getDpi()+' DPI tag';
    document.getElementById('cpSzMm').textContent='';
  }else{
    const{w,h}=getPxSize(), mm=getSizeMM();
    document.getElementById('cpSzInfo').textContent=`${w} × ${h} px`;
    document.getElementById('cpDpiInfo').textContent=`${getDpi()} DPI`;
    document.getElementById('cpSzMm').textContent=`· prints at exactly ${mm.w}×${mm.h} mm`;
  }
  document.querySelectorAll('.cp-res img').forEach(im=>im.parentElement.style.setProperty('--ar',_arCss()));
  saveSettingsSoon();
  schedS1Prev(); schedPrev();
}
function _arCss(){ const s=getPxSize(); return document.getElementById('cpSize').value==='original'?'3/4':`${s.w}/${s.h}`; }

/* ── BG color picker ── */
function pickChip(el){
  bgColor=el.dataset.c;
  document.querySelectorAll('#cpBgChips .cp-chip').forEach(c=>c.classList.remove('active'));
  el.classList.add('active');
  saveSettingsSoon(); schedS1Prev(); schedPrev();
}
function pickCustomColor(inp){
  bgColor=inp.value;
  document.querySelectorAll('#cpBgChips .cp-chip').forEach(c=>c.classList.remove('active'));
  inp.parentElement.classList.add('active');
  saveSettingsSoon(); schedS1Prev(); schedPrev();
}

/* ── BG model ── */
function onBgToggle(){
  const on=chkVal('chk_bgremove');
  document.getElementById('cpBgSub').classList.toggle('show',on);
  saveSettingsSoon();
  if(on&&!bgModelLoaded&&!bgModelLoading) loadBgModel();
  else schedPrev();
}
async function loadBgModel(){
  if(bgModelLoaded) return true;
  if(bgModelLoading) return bgModelLoading;
  setMdlStatus('loading','⏳ Loading segmentation model…');
  bgModelLoading=(async()=>{
    try{
      bgModel=new SelfieSegmentation({locateFile:f=>`${SEG_LOCAL}/${f}`});
      // modelSelection 0 = "general" model (256×256) — more accurate on
      // head-and-shoulder portraits than the faster "landscape" one.
      bgModel.setOptions({modelSelection:0,selfieMode:false});
      await bgModel.initialize();
      bgModelLoaded=true;
      setMdlStatus('ready','✓ Model ready');
      schedPrev();
      return true;
    }catch(e){
      setMdlStatus('err','✗ Failed: '+(e.message||e));
      return false;
    }finally{ bgModelLoading=false; }
  })();
  return bgModelLoading;
}
function setMdlStatus(cls,txt){
  const el=document.getElementById('cpBgModelStatus');
  el.className='cp-model-badge '+cls; el.textContent=txt;
}

/* ── folder pickers ── */
const _natSort=(a,b)=>a.name.localeCompare(b.name,undefined,{numeric:true,sensitivity:'base'});
async function pickInputDir(){
  if(!HAS_FSA){ document.getElementById('cpInFallback').click(); return; }
  try{
    const dir=await showDirectoryPicker({mode:'read'});
    const list=[];
    for await(const e of dir.values()){
      if(e.kind==='file'&&IMG_RX.test(e.name)){
        const f=await e.getFile();
        list.push({entry:e,file:f,name:f.name});
      }
    }
    setInput(dir,list.sort(_natSort),dir.name);
  }catch(e){ if(e.name!=='AbortError'){ console.error(e); toast('Could not open folder: '+e.message,'err'); } }
}
function onFallbackFilesPicked(inp){
  const files=[...inp.files].filter(f=>/^image\//i.test(f.type)||IMG_RX.test(f.name));
  inp.value='';
  if(!files.length) return;
  setInput('FALLBACK',files.map(f=>({entry:null,file:f,name:f.name})).sort(_natSort),null);
}
function setInput(dir,list,label){
  inDir=dir; imgFiles=list; anCache.clear(); prevIdx=0;
  document.getElementById('cpInCard').classList.add('sel');
  document.getElementById('cpInPath').textContent=label||`${list.length} photo${list.length!==1?'s':''} selected`;
  document.getElementById('cpInSub').textContent=`${list.length} image${list.length!==1?'s':''} found`;
  if(dir==='FALLBACK'&&!outDir) autoSelectZipOutput();
  checkSameFolder();
  refreshS1();
  if(list.length) loadS1Preview(); else toast('No images found in that folder','warn');
}
function autoSelectZipOutput(){
  outDir='ZIP';
  document.getElementById('cpOutCard').classList.add('sel');
  document.getElementById('cpOutPath').textContent='Downloads as a ZIP file';
  document.getElementById('cpOutSub').textContent='Ready — no folder needed';
}
async function pickOutputDir(){
  if(!HAS_FSA){ if(!outDir) autoSelectZipOutput(); refreshS1(); return; }
  try{
    outDir=await showDirectoryPicker({mode:'readwrite'});
    document.getElementById('cpOutCard').classList.add('sel');
    document.getElementById('cpOutPath').textContent=outDir.name;
    document.getElementById('cpOutSub').textContent='Ready to save';
    await checkSameFolder();
    refreshS1();
  }catch(e){ if(e.name!=='AbortError'){ console.error(e); toast('Could not open folder: '+e.message,'err'); } }
}
/* Saving into the SAME folder with the same file names would overwrite the
   original photos — force a suffix in that case. */
async function checkSameFolder(){
  if(!inDir||!outDir||typeof inDir==='string'||typeof outDir==='string') return false;
  try{
    if(await inDir.isSameEntry(outDir)){
      const sf=document.getElementById('cpSuffix');
      if(!sf.value.trim()){ sf.value='_crop'; saveSettingsSoon(); }
      toast('Input & output are the same folder — suffix "'+sf.value.trim()+'" added so originals are NOT overwritten','warn');
      return true;
    }
  }catch(e){}
  return false;
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
/* drag & drop photos onto the input card */
(function(){
  const card=document.getElementById('cpInCard');
  ['dragenter','dragover'].forEach(ev=>card.addEventListener(ev,e=>{ e.preventDefault(); card.classList.add('cp-drop-hl'); }));
  ['dragleave','drop'].forEach(ev=>card.addEventListener(ev,()=>card.classList.remove('cp-drop-hl')));
  card.addEventListener('drop',e=>{
    e.preventDefault();
    const files=[...(e.dataTransfer?.files||[])].filter(f=>/^image\//i.test(f.type)||IMG_RX.test(f.name));
    if(!files.length){ toast('Drop image files (JPG / PNG / WEBP)','warn'); return; }
    setInput('FALLBACK',files.map(f=>({entry:null,file:f,name:f.name})).sort(_natSort),null);
  });
})();

/* ═══════════════ IMAGE + AI HELPERS ═══════════════ */
/* EXIF orientation is applied explicitly — phone photos were otherwise read
   sideways on some browsers (→ "no face" or a sideways crop). */
async function decodeFile(file){
  try{ return await createImageBitmap(file,{imageOrientation:'from-image'}); }
  catch(e){
    const url=URL.createObjectURL(file);
    try{
      const im=await new Promise((res,rej)=>{ const i=new Image(); i.onload=()=>res(i); i.onerror=()=>rej(new Error('Unsupported or damaged image (HEIC? save as JPG)')); i.src=url; });
      return await createImageBitmap(im);
    }finally{ URL.revokeObjectURL(url); }
  }
}
function ensureModels(onStep){
  if(modelsLoaded) return Promise.resolve(true);
  if(!_modelsP) _modelsP=(async()=>{
    const st=(p,t)=>{ try{ onStep&&onStep(p,t); }catch(e){} };
    st(10,'Loading face detector…');
    // SSD MobileNet is far more accurate than Tiny; used when its weights are on
    // the server (assets/face-api-weights/ssd_mobilenetv1_*), else Tiny only.
    try{ await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_CDN); DET.ssd=true; }catch(e){ DET.ssd=false; }
    st(40,'Loading fast face detector…');
    try{ await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_CDN); DET.tiny=true; }catch(e){ DET.tiny=false; }
    st(65,'Loading landmark model…');
    try{ await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_CDN); DET.lm='full'; }
    catch(e){ try{ await faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_CDN); DET.lm='tiny'; }catch(e2){ DET.lm=null; } }
    st(100,'Models ready!');
    modelsLoaded=DET.ssd||DET.tiny;
    if(!modelsLoaded) _modelsP=null;   // allow a retry later
    return modelsLoaded;
  })();
  return _modelsP;
}
function _detTasks(canvas,form,light){
  const t=[];
  if(DET.ssd) t.push(()=>faceapi.detectAllFaces(canvas,new faceapi.SsdMobilenetv1Options({minConfidence:form?0.3:0.5,maxResults:12})));
  if(DET.tiny){
    const opts=form?[[608,.3],[416,.22],[320,.18]]:[[416,.45],[608,.32],[320,.28]];
    (light?opts.slice(0,1):opts).forEach(([i,s])=>t.push(()=>faceapi.detectAllFaces(canvas,new faceapi.TinyFaceDetectorOptions({inputSize:i,scoreThreshold:s}))));
  }
  if(DET.ssd&&!light) t.push(()=>faceapi.detectAllFaces(canvas,new faceapi.SsdMobilenetv1Options({minConfidence:0.25,maxResults:12})));
  return t;
}
async function _runDet(task){
  let q=task();
  if(DET.lm) q=q.withFaceLandmarks(DET.lm==='tiny');
  const r=await q;
  return (r||[]).map(d=>DET.lm
    ?{box:d.detection.box,score:d.detection.score,pts:d.landmarks.positions.map(p=>({x:p.x,y:p.y}))}
    :{box:d.box,score:d.score,pts:null});
}
/* The main subject = big, confident and near the centre (group photos / forms). */
function _pickMain(dets,W,H){
  let best=null,bs=-1;
  for(const d of dets){
    const b=d.box, cx=(b.x+b.width/2)/W-.5, cy=(b.y+b.height/2)/H-.45;
    const sc=b.width*b.height*(0.5+0.5*d.score)*(1-0.6*Math.min(1,Math.hypot(cx,cy)));
    if(sc>bs){ bs=sc; best=d; }
  }
  return best;
}
function _offDet(d,ox,oy,k){
  return{box:{x:d.box.x*k+ox,y:d.box.y*k+oy,width:d.box.width*k,height:d.box.height*k},score:d.score,pts:d.pts?d.pts.map(p=>({x:p.x*k+ox,y:p.y*k+oy})):null};
}
async function detectFaces(canvas,form,light){
  for(const t of _detTasks(canvas,form,light)){
    try{ const r=await _runDet(t); if(r.length) return _pickMain(r,canvas.width,canvas.height); }catch(e){}
  }
  if(!form||light) return null;
  // Quadrant search: a small photo in a form corner becomes 2× larger
  const W=canvas.width,H=canvas.height;
  for(const [x1,y1,x2,y2] of [[0,0,W*.65,H*.65],[W*.35,0,W,H*.65],[0,H*.35,W*.65,H],[W*.35,H*.35,W,H]]){
    const rx=Math.floor(x1),ry=Math.floor(y1),rW=Math.ceil(x2-x1),rH=Math.ceil(y2-y1);
    const crop=mk(rW,rH); crop.getContext('2d').drawImage(canvas,rx,ry,rW,rH,0,0,rW,rH);
    for(const t of _detTasks(crop,false,true)){
      try{ const r=await _runDet(t); if(r.length) return _offDet(_pickMain(r,rW,rH),rx,ry,1); }catch(e){}
    }
  }
  return null;
}
/* Second pass on a close-up of the face at higher resolution — landmarks
   (eyes / chin) become much more exact than on the down-scaled full photo. */
async function refineDet(bmp,det){
  const b=det.box, side=Math.max(b.width,b.height)*2.4;
  const cx=b.x+b.width/2, cy=b.y+b.height/2;
  const x0=Math.max(0,Math.floor(cx-side/2)), y0=Math.max(0,Math.floor(cy-side/2));
  const x1=Math.min(bmp.width,Math.ceil(cx+side/2)), y1=Math.min(bmp.height,Math.ceil(cy+side/2));
  const rw=x1-x0, rh=y1-y0; if(rw<24||rh<24) return null;
  const k=Math.min(2.5,560/Math.max(rw,rh));   // up to 560px close-up
  const c=mk(Math.round(rw*k),Math.round(rh*k)), x=c.getContext('2d');
  x.imageSmoothingEnabled=true; x.imageSmoothingQuality='high';
  x.drawImage(bmp,x0,y0,rw,rh,0,0,c.width,c.height);
  let r=null;
  for(const t of _detTasks(c,false,true)){ try{ const d=await _runDet(t); if(d.length){ r=d; break; } }catch(e){} }
  if(!r) return null;
  // the detection closest to the original face centre
  let best=null,bd=1e9;
  for(const d of r){ const dx=(d.box.x+d.box.width/2)/k+x0-cx, dy=(d.box.y+d.box.height/2)/k+y0-cy, dd=Math.hypot(dx,dy); if(dd<bd){ bd=dd; best=d; } }
  if(!best||bd>b.width*0.6) return null;
  return _offDet(best,x0,y0,1/k);
}
/* Face geometry from the 68 landmarks (eyes, chin, jaw) — with a sanity check
   so a wrong landmark fit falls back to the detection box instead of
   producing a strange crop. */
function buildFace(det){
  const b=det.box, P=det.pts;
  const f={box:{x:b.x,y:b.y,w:b.width,h:b.height},score:det.score,lm:false};
  if(P&&P.length===68){
    const avg=(a,z)=>{ let x=0,y=0; for(let i=a;i<=z;i++){ x+=P[i].x; y+=P[i].y; } const n=z-a+1; return{x:x/n,y:y/n}; };
    const eL=avg(36,41), eR=avg(42,47), chin=P[8];
    const ang=Math.atan2(eR.y-eL.y,eR.x-eL.x);
    const ied=Math.hypot(eR.x-eL.x,eR.y-eL.y);
    const mid={x:(eL.x+eR.x)/2,y:(eL.y+eR.y)/2};
    const ec=(chin.x-mid.x)*-Math.sin(ang)+(chin.y-mid.y)*Math.cos(ang);   // eye-line → chin, perpendicular
    const r=ec/Math.max(ied,1), rb=ied/Math.max(b.width,1);
    if(ied>4&&r>1.15&&r<3.3&&rb>0.18&&rb<0.75){
      Object.assign(f,{lm:true,eL,eR,mid,angle:ang,ec,ied,jawW:Math.hypot(P[16].x-P[0].x,P[16].y-P[0].y)});
    }
  }
  if(!f.lm){
    Object.assign(f,{mid:{x:b.x+b.width/2,y:b.y+b.height*0.40},angle:0,ec:b.height*0.60,ied:b.width*0.42,jawW:b.width*0.95});
  }
  return f;
}
async function analyze(idx,form,keepCache){
  const info=imgFiles[idx];
  const bmp=await decodeFile(info.file);
  const an={bmp,W:bmp.width,H:bmp.height,face:null};
  const c=anCache.get(idx);
  if(c&&c.form===form&&c.detected&&c.W===an.W&&c.H===an.H){ an.face=c.face; an.detected=c.detected; return an; }
  if(await ensureModels()){
    const maxD=form?1600:1024, sc=Math.min(1,maxD/Math.max(an.W,an.H));
    const dc=hiQResize(bmp,Math.max(1,Math.round(an.W*sc)),Math.max(1,Math.round(an.H*sc)));
    let det=await detectFaces(dc,form,false);
    if(det){
      const small=det.box.width<220;     // big faces already have exact landmarks → skip the 2nd pass (faster)
      det=_offDet(det,0,0,1/sc);
      const ref=small?await refineDet(bmp,det).catch(()=>null):null;
      if(ref) det=ref;
      an.face=buildFace(det);
    }
    an.detected=true;
  }
  if(keepCache!==false) anCache.set(idx,{face:an.face,W:an.W,H:an.H,form,detected:an.detected});
  return an;
}
function releaseAn(an){ try{ an?.bmp?.close?.(); }catch(e){} if(an){ an.bmp=null; an._pre=null; } }

/* ═══════════════ GEOMETRY ═══════════════
   All crop maths happens in the "levelled" frame: the source rotated by
   -angle around the eye mid-point P (so the eyes are horizontal). */
const _rot=(p,P,a)=>{ const c=Math.cos(a),s=Math.sin(a),x=p.x-P.x,y=p.y-P.y; return{x:P.x+x*c-y*s,y:P.y+x*s+y*c}; };
function _boundsLevelled(W,H,P,a){
  if(!a) return{x0:0,y0:0,x1:W,y1:H};
  // inner box of the rotated photo (conservative → no empty corner wedges)
  const pts=[{x:0,y:0},{x:W,y:0},{x:W,y:H},{x:0,y:H}].map(p=>_rot(p,P,-a));
  const xs=pts.map(p=>p.x).sort((u,v)=>u-v), ys=pts.map(p=>p.y).sort((u,v)=>u-v);
  return{x0:xs[1],y0:ys[1],x1:xs[2],y1:ys[2]};
}
/* Keep the crop inside the photo: small shifts first (≤7% sideways, ≤12% up,
   ≤4% down), then a gentle zoom-in, and only then pad with the fill colour.
   Before, a crop running past the edge was STRETCHED to the output width —
   the main reason faces came out distorted. */
function _fitCrop(base,B,o){
  const lim={x:0.07,up:0.12,down:0.04};
  let z=1,c=null;
  for(let it=0;it<60;it++){
    c={x:o.ax-(o.ax-base.x)/z,y:o.ay-(o.ay-base.y)/z,w:base.w/z,h:base.h/z};
    const fW=c.w<=B.x1-B.x0+0.5, fH=c.h<=B.y1-B.y0+0.5;
    let sx=0,sy=0;
    if(fW){ if(c.x<B.x0) sx=B.x0-c.x; else if(c.x+c.w>B.x1) sx=B.x1-(c.x+c.w); }
    if(fH){ if(c.y<B.y0) sy=B.y0-c.y; else if(c.y+c.h>B.y1) sy=B.y1-(c.y+c.h); }
    const okX=fW&&Math.abs(sx)<=lim.x*c.w, okY=fH&&(sy>=0?sy<=lim.down*c.h:-sy<=lim.up*c.h);
    if(okX&&okY){ c.x+=sx; c.y+=sy; return{c,zoom:z,shift:!!(sx||sy)}; }
    if(z>=o.maxZoom-1e-6){
      c.x+=fW?Math.max(-lim.x*c.w,Math.min(lim.x*c.w,sx)):0;
      c.y+=fH?Math.max(-lim.up*c.h,Math.min(lim.down*c.h,sy)):0;
      return{c,zoom:z,shift:true};
    }
    z=Math.min(o.maxZoom,z*1.015);
  }
  return{c,zoom:z,shift:true};
}
function geometry(an,s,man){
  man=man||{};
  const W=an.W,H=an.H,f=an.face;
  const g={angle:0,P:{x:W/2,y:H/2},crop:null,faceEll:null,lines:null,method:f?'face':'centre',padded:false,fitted:false,lowRes:false,tilt:0};
  if(s.sizeMode==='original'){
    g.crop={x:0,y:0,w:W,h:H};
    if(f) g.faceEll={cx:f.mid.x,cy:f.mid.y+f.ec*0.22,rx:Math.max(f.jawW*0.52,f.ied*1.05),ry:f.ec*0.84};
    return g;
  }
  const ratio=s.outW/s.outH, head=Math.max(0.3,Math.min(0.95,s.head/100)), top=Math.max(0,Math.min(0.4,s.top/100));
  let crop;
  if(f){
    let a=(s.autoTilt&&f.lm&&Math.abs(f.angle)<=MAX_TILT)?f.angle:0;
    a+=(man.rot||0)*DEG;
    g.angle=a; g.tilt=f.lm?f.angle/DEG:0; g.P=f.mid;
    const ec=f.ec*Math.cos(f.angle-a);          // eye→chin in the levelled frame
    const eyeY=f.mid.y, chinY=eyeY+ec, crownY=eyeY-ec*CROWN_K;
    const cH=(chinY-crownY)/head, cW=cH*ratio;
    crop={x:f.mid.x-cW/2,y:crownY-cH*top,w:cW,h:cH};
    // manual edits are applied ON TOP of this auto-fitted crop (editor starts from it)
    const r=_fitCrop(crop,_boundsLevelled(W,H,g.P,a),{ax:f.mid.x,ay:eyeY,maxZoom:Math.max(1,0.88/head)});
    crop=r.c; g.fitted=r.zoom>1.001||r.shift;
    const browY=eyeY-ec*0.55;
    g.faceEll={cx:f.mid.x,cy:(browY+chinY)/2+ec*0.04,rx:Math.max(f.jawW*0.5,f.ied*1.0),ry:(chinY-browY)/2*1.1};
    g.lines={crownY,eyeY,chinY};
  }else{
    const cH=Math.min(H,W/ratio), cW=cH*ratio;
    crop={x:(W-cW)/2,y:(H-cH)*0.15,w:cW,h:cH};
  }
  // manual fine-tune (editor) — taken literally
  const z=man.zoom||1;
  if(z!==1){ const cx=crop.x+crop.w/2, cy=crop.y+crop.h/2; crop.w/=z; crop.h/=z; crop.x=cx-crop.w/2; crop.y=cy-crop.h/2; }
  if(man.dx) crop.x+=man.dx*crop.w;
  if(man.dy) crop.y+=man.dy*crop.h;
  const B=_boundsLevelled(W,H,g.P,g.angle), e=0.5;
  g.padded=crop.x<B.x0-e||crop.y<B.y0-e||crop.x+crop.w>B.x1+e||crop.y+crop.h>B.y1+e;
  g.lowRes=crop.w<s.outW*0.85;      // source has fewer real pixels than the output needs
  g.crop=crop;
  return g;
}

/* ═══════════════ RENDER ═══════════════ */
function _getPre(an,k){
  if(k>=0.999) return an.bmp;
  const q=Math.max(0.02,Math.round(k*40)/40);
  if(an._pre&&an._preK===q) return an._pre;
  an._pre=hiQResize(an.bmp,Math.max(1,Math.round(an.W*q)),Math.max(1,Math.round(an.H*q)));
  an._preK=q;
  return an._pre;
}
/* One crop + rotate + resample straight from the original pixels: the source
   is first step-down resized so the crop lands at 2× the output size, drawn
   once with the rotation, then halved with high-quality filtering. */
function drawCrop(an,g,outW,outH,bg,fast){
  const {crop,angle,P}=g;
  const ss=(fast||outW*outH>6e6)?1:2;   // 2× super-sampling (skipped for huge 'original' outputs)
  const iw=Math.max(1,Math.round(outW*ss)), ih=Math.max(1,Math.round(outH*ss));
  const k=iw/crop.w;
  const pre=_getPre(an,Math.min(1,k*1.05));
  const mid=mk(iw,ih), c=mid.getContext('2d');
  c.imageSmoothingEnabled=true; c.imageSmoothingQuality='high';
  c.fillStyle=bg; c.fillRect(0,0,iw,ih);
  c.setTransform(k,0,0,k,-crop.x*k,-crop.y*k);
  if(angle){ c.translate(P.x,P.y); c.rotate(-angle); c.translate(-P.x,-P.y); }
  c.drawImage(pre,0,0,an.W,an.H);
  c.setTransform(1,0,0,1,0,0);
  return ss===1?mid:hiQResize(mid,outW,outH);
}
function _toOut(g,outW){ const k=outW/g.crop.w, c=g.crop; return{k,x:v=>(v-c.x)*k,y:v=>(v-c.y)*k}; }
function faceInOut(g,outW){
  if(!g.faceEll) return null;
  const t=_toOut(g,outW), e=g.faceEll;
  return{cx:t.x(e.cx),cy:t.y(e.cy),rx:e.rx*t.k,ry:e.ry*t.k};
}
function outDims(an,s,opt){
  if(opt&&opt.w) return{w:opt.w,h:opt.h};
  if(s.sizeMode==='original'){ const k=Math.min(1,8000/Math.max(an.W,an.H)); return{w:Math.round(an.W*k),h:Math.round(an.H*k)}; }
  return{w:s.outW,h:s.outH};
}
async function renderOutput(an,s,man,opt){
  opt=opt||{};
  const {w:outW,h:outH}=outDims(an,s,opt);
  const g=geometry(an,s,man);
  const out=drawCrop(an,g,outW,outH,s.bgColor,!!opt.fast);
  const fo=faceInOut(g,outW);
  if(opt.raw) return{canvas:out,g,fo};
  const rs=Math.sqrt(outW*outH/REF_AREA);
  if(s.awb) autoWB(out);
  if(s.ae) autoExposure(out,fo);
  let mask=null;
  if(s.bgRemove&&bgModelLoaded&&!opt.fast){
    const mc=opt.maskCache, key=[g.crop.x,g.crop.y,g.crop.w,g.angle,outW,outH,s.awb,s.ae].map(v=>typeof v==='number'?v.toFixed(2):v).join('|');
    let raw=mc&&mc.key===key?mc.raw:null;
    if(!raw){ raw=await getSegMaskRaw(out); if(mc){ mc.key=key; mc.raw=raw; mc.proc={}; } }
    if(raw){
      const pk=String(s.bg_smooth);
      mask=mc&&mc.proc&&mc.proc[pk]||processMask(raw,s.bg_smooth,rs);
      if(mc&&mc.proc) mc.proc[pk]=mask;
    }
  }
  if(!opt.fast){
    applyAllFilters(out,fo,s,rs);
    if(s.sharp&&s.sizeMode!=='original') unsharp(out,0.7*rs,0.38,2);
  }
  if(mask) compositeWithBg(out,mask,s.bgColor);
  return{canvas:out,g,fo,masked:!!mask};
}
function resultTags(an,g){
  const t=[];
  if(!an.face) t.push({t:'warn',l:an.detected===false?'AI not loaded – centre crop':'No face – centre crop'});
  else{
    t.push({t:'ok',l:an.face.lm?'Face + landmarks':'Face (box only)'});
    if(Math.abs(g.tilt)>0.5&&g.angle) t.push({t:'ok',l:`Tilt ${g.tilt.toFixed(1)}° fixed`});
    else if(Math.abs(g.tilt)>MAX_TILT/DEG) t.push({t:'warn',l:`Tilt ${g.tilt.toFixed(0)}° (too big — check)`});
  }
  if(g.fitted&&an.face) t.push({t:'info',l:'Fitted to photo edge'});
  if(g.padded) t.push({t:'warn',l:'Edge filled with colour'});
  if(g.lowRes) t.push({t:'info',l:'Low-res source (upscaled)'});
  return t;
}

/* ═══════════════ FILTERS ═══════════════ */
const cl=v=>v<0?0:v>255?255:Math.round(v);
/* Gaussian-ish blur that works everywhere: canvas filter where supported
   (Chrome/Edge/Firefox), otherwise a 3-pass box blur in JS (Safari < 18,
   some WebViews silently ignore ctx.filter → effects did nothing there). */
let _filterOK=null;
function _hasCanvasFilter(){
  if(_filterOK!==null) return _filterOK;
  try{
    const a=mk(9,1),c=a.getContext('2d'); c.fillStyle='#000'; c.fillRect(0,0,9,1); c.fillStyle='#fff'; c.fillRect(4,0,1,1);
    const b=mk(9,1),d=b.getContext('2d'); d.filter='blur(2px)'; d.drawImage(a,0,0);
    const px=d.getImageData(2,0,1,1).data[0]; _filterOK=px>3&&px<250;
  }catch(e){ _filterOK=false; }
  return _filterOK;
}
function blurCopy(src,r){
  const W=src.width,H=src.height,o=mk(W,H),c=o.getContext('2d');
  if(r<=0.05){ c.drawImage(src,0,0); return o; }
  if(_hasCanvasFilter()){ c.filter=`blur(${r}px)`; c.drawImage(src,0,0); c.filter='none'; return o; }
  c.drawImage(src,0,0);
  const id=c.getImageData(0,0,W,H); _boxBlur3(id.data,W,H,r); c.putImageData(id,0,0); return o;
}
function _boxBlur3(d,W,H,sigma){
  const rad=Math.max(1,Math.round(Math.sqrt(12*sigma*sigma/3+1)/2));
  const tmp=new Uint8ClampedArray(d.length);
  const pass=(src,dst,horiz)=>{
    const len=horiz?W:H, lines=horiz?H:W, win=rad*2+1;
    for(let l=0;l<lines;l++){
      for(let ch=0;ch<3;ch++){
        let acc=0;
        const idx=i=>{ i=i<0?0:i>=len?len-1:i; return horiz?((l*W+i)*4+ch):((i*W+l)*4+ch); };
        for(let i=-rad;i<=rad;i++) acc+=src[idx(i)];
        for(let i=0;i<len;i++){ dst[idx(i)]=acc/win; acc+=src[idx(i+rad+1)]-src[idx(i-rad)]; }
      }
      for(let i=0;i<len;i++){ const a=horiz?((l*W+i)*4+3):((i*W+l)*4+3); dst[a]=src[a]; }
    }
  };
  for(let k=0;k<3;k++){ pass(d,tmp,true); pass(tmp,d,false); }
}
function applyAllFilters(cv,fo,s,rs){
  const ctx=cv.getContext('2d'),W=cv.width,H=cv.height;
  if(s.noise_reduce>0) edgeAwareSmooth(cv,null,Math.min(1,s.noise_reduce/100*0.9),1.1*rs,12+s.noise_reduce*0.12);
  const needPix=s.brightness!==100||s.contrast!==100||s.saturation!==100||s.warmth!==0||s.vibrance>0||s.hdr>0;
  if(needPix) pixelAdjust(ctx,W,H,s);
  if(s.clarity>0) unsharp(cv,10*rs,s.clarity/100*0.55,3,true);
  if(s.sharpness>0) unsharp(cv,1.0*rs,s.sharpness/100*1.4,2);
  if(fo){
    if(s.face_smooth>0) edgeAwareSmooth(cv,fo,Math.min(1,s.face_smooth/100*0.95),2.4*rs,16+s.face_smooth*0.14);
    if(s.skin_glow>0) faceTone(cv,fo,'glow',s.skin_glow/100);
    if(s.face_brighten>0) faceTone(cv,fo,'bright',s.face_brighten/100);
  }
}
/* soft elliptical weight: 1 inside the face, smooth fade at the edge (no box seams) */
function _ellW(x,y,fo){ const dx=(x-fo.cx)/fo.rx, dy=(y-fo.cy)/fo.ry, d=Math.sqrt(dx*dx+dy*dy); if(d<=0.78) return 1; if(d>=1.12) return 0; const t=1-(d-0.78)/0.34; return t*t*(3-2*t); }
function _region(fo,W,H){ if(!fo) return{x0:0,y0:0,w:W,h:H}; const x0=Math.max(0,Math.floor(fo.cx-fo.rx*1.15)),y0=Math.max(0,Math.floor(fo.cy-fo.ry*1.15)),x1=Math.min(W,Math.ceil(fo.cx+fo.rx*1.15)),y1=Math.min(H,Math.ceil(fo.cy+fo.ry*1.15)); return{x0,y0,w:x1-x0,h:y1-y0}; }
/* Edge-preserving smoothing (skin smoothing / noise reduction): blends with a
   blurred copy ONLY where the two are similar — eyes, lashes, lips, hair and
   outlines stay sharp. The old version blurred a hard rectangle. */
function edgeAwareSmooth(cv,fo,al,r,thr){
  const W=cv.width,H=cv.height,ctx=cv.getContext('2d'),R=_region(fo,W,H);
  if(R.w<4||R.h<4||al<=0) return;
  const bl=blurCopy(cv,r);
  const o=ctx.getImageData(R.x0,R.y0,R.w,R.h), b=bl.getContext('2d').getImageData(R.x0,R.y0,R.w,R.h), od=o.data, bd=b.data;
  for(let y=0;y<R.h;y++) for(let x=0;x<R.w;x++){
    const i=(y*R.w+x)*4;
    const m=fo?_ellW(x+R.x0,y+R.y0,fo):1; if(m<=0) continue;
    const diff=(Math.abs(od[i]-bd[i])+Math.abs(od[i+1]-bd[i+1])+Math.abs(od[i+2]-bd[i+2]))/3;
    let ep=1-diff/thr; if(ep<=0) continue; ep*=ep;
    const a=al*m*ep;
    od[i]=od[i]+(bd[i]-od[i])*a; od[i+1]=od[i+1]+(bd[i+1]-od[i+1])*a; od[i+2]=od[i+2]+(bd[i+2]-od[i+2])*a;
  }
  ctx.putImageData(o,R.x0,R.y0);
}
function faceTone(cv,fo,mode,amt){
  const W=cv.width,H=cv.height,ctx=cv.getContext('2d'),R=_region(fo,W,H);
  if(R.w<4||R.h<4) return;
  const img=ctx.getImageData(R.x0,R.y0,R.w,R.h), d=img.data;
  for(let y=0;y<R.h;y++) for(let x=0;x<R.w;x++){
    const m=_ellW(x+R.x0,y+R.y0,fo); if(m<=0) continue;
    const i=(y*R.w+x)*4, k=amt*m;
    if(mode==='bright'){ // lift shadows/mids, protect highlights
      for(let c=0;c<3;c++){ const v=d[i+c]; d[i+c]=cl(v+(255-v)*k*0.32*(1-v/320)); }
    }else{               // warm healthy glow
      d[i]=cl(d[i]+k*16); d[i+1]=cl(d[i+1]+k*10); d[i+2]=cl(d[i+2]+k*3);
    }
  }
  ctx.putImageData(img,R.x0,R.y0);
}
function pixelAdjust(ctx,W,H,s){
  const img=ctx.getImageData(0,0,W,H), d=img.data;
  const bF=s.brightness/100, cAdj=s.contrast-100;
  const cF=cAdj!==0?(259*(cAdj+255))/(255*(259-cAdj)):1;
  const lut=new Uint8Array(256);
  for(let i=0;i<256;i++){ let v=i*bF; if(cF!==1) v=cF*(v-128)+128; lut[i]=cl(v); }
  const sF=s.saturation/100,vF=s.vibrance/100,hF=s.hdr/100;
  const wR=s.warmth*1.4,wG=s.warmth*0.25,wB=-s.warmth*0.9;
  for(let i=0;i<d.length;i+=4){
    let r=lut[d[i]],g=lut[d[i+1]],b=lut[d[i+2]];
    if(sF!==1){ const gr=0.299*r+0.587*g+0.114*b; r=cl(gr+sF*(r-gr)); g=cl(gr+sF*(g-gr)); b=cl(gr+sF*(b-gr)); }
    if(s.warmth!==0){ r=cl(r+wR); g=cl(g+wG); b=cl(b+wB); }
    if(vF>0){ const mx=Math.max(r,g,b),mn=Math.min(r,g,b),sat=(mx-mn)/Math.max(mx,1),bo=vF*(1-sat),gr2=0.299*r+0.587*g+0.114*b; r=cl(gr2+(1+bo)*(r-gr2)); g=cl(gr2+(1+bo)*(g-gr2)); b=cl(gr2+(1+bo)*(b-gr2)); }
    if(hF>0){ const lm=0.299*r+0.587*g+0.114*b, cu=lm<128?lm*(1-hF*0.22):lm+hF*0.22*(255-lm), ra=lm>0?cu/lm:1; r=cl(r*ra); g=cl(g*ra); b=cl(b*ra); }
    d[i]=r; d[i+1]=g; d[i+2]=b;
  }
  ctx.putImageData(img,0,0);
}
/* Unsharp mask with a threshold (no noise amplification). midtone=true →
   "clarity": large radius, weighted to mid-tones only. */
function unsharp(cv,r,amount,thr,midtone){
  const W=cv.width,H=cv.height,ctx=cv.getContext('2d');
  const bl=blurCopy(cv,r), o=ctx.getImageData(0,0,W,H), b=bl.getContext('2d').getImageData(0,0,W,H), od=o.data, bd=b.data;
  for(let i=0;i<od.length;i+=4){
    let w=amount;
    if(midtone){ const lm=0.299*od[i]+0.587*od[i+1]+0.114*od[i+2]; w*=Math.max(0,1-Math.abs(lm-128)/118); }
    for(let c=0;c<3;c++){ const df=od[i+c]-bd[i+c]; if(Math.abs(df)>thr) od[i+c]=cl(od[i+c]+w*df); }
  }
  ctx.putImageData(o,0,0);
}
/* White balance from NEUTRAL pixels only (white/grey background, clothes) with
   gains limited to ±12%. The old gray-world version averaged everything — a
   blue or red backdrop turned faces orange / green. */
function autoWB(cv){
  const W=cv.width,H=cv.height,ctx=cv.getContext('2d'),img=ctx.getImageData(0,0,W,H),d=img.data;
  let R=0,G=0,B=0,n=0,tot=0;
  const step=Math.max(4,Math.round(d.length/4/90000))*4;
  for(let i=0;i<d.length;i+=step){
    tot++;
    const r=d[i],g=d[i+1],b=d[i+2],mx=Math.max(r,g,b),mn=Math.min(r,g,b),lm=0.299*r+0.587*g+0.114*b;
    if(lm>70&&lm<245&&(mx-mn)<mx*0.14+4){ R+=r; G+=g; B+=b; n++; }
  }
  if(n<tot*0.04) return;            // no reliable neutral reference → leave colours alone
  R/=n; G/=n; B/=n; const avg=(R+G+B)/3;
  const gR=Math.min(1.12,Math.max(0.89,avg/R)), gG=Math.min(1.12,Math.max(0.89,avg/G)), gB=Math.min(1.12,Math.max(0.89,avg/B));
  if(Math.abs(gR-1)<0.01&&Math.abs(gG-1)<0.01&&Math.abs(gB-1)<0.01) return;
  const lr=new Uint8Array(256),lg=new Uint8Array(256),lb=new Uint8Array(256);
  for(let v=0;v<256;v++){ lr[v]=cl(v*gR); lg[v]=cl(v*gG); lb[v]=cl(v*gB); }
  for(let i=0;i<d.length;i+=4){ d[i]=lr[d[i]]; d[i+1]=lg[d[i+1]]; d[i+2]=lb[d[i+2]]; }
  ctx.putImageData(img,0,0);
}
/* Exposure: limited black/white-point stretch + face-aware gamma (a dark face
   is lifted towards a natural skin brightness, an over-bright one toned down).
   The old 0.6%/99.4% hard stretch could blow out faces against white walls. */
function autoExposure(cv,fo){
  const W=cv.width,H=cv.height,ctx=cv.getContext('2d'),img=ctx.getImageData(0,0,W,H),d=img.data,n=d.length/4;
  const hist=new Uint32Array(256);
  for(let i=0;i<d.length;i+=4) hist[(0.299*d[i]+0.587*d[i+1]+0.114*d[i+2])|0]++;
  let cum=0,lo=0,hi=255; const l1=n*0.005,h1=n*0.995;
  for(let i=0;i<256;i++){ cum+=hist[i]; if(cum<=l1) lo=i; if(cum<=h1) hi=i; }
  const bp=Math.min(lo,26), wp=Math.max(hi,222);
  let fm=0,fn=0;
  if(fo){
    const R=_region(fo,W,H), st=Math.max(1,Math.round(Math.sqrt(R.w*R.h/6000)));
    for(let y=R.y0;y<R.y0+R.h;y+=st) for(let x=R.x0;x<R.x0+R.w;x+=st){
      if(_ellW(x,y,{...fo,rx:fo.rx*0.7,ry:fo.ry*0.7})<0.5) continue;
      const i=(y*W+x)*4; fm+=0.299*d[i]+0.587*d[i+1]+0.114*d[i+2]; fn++;
    }
  }
  let gam=1;
  if(fn>20){
    let m=fm/fn; m=Math.max(1,Math.min(254,(m-bp)*255/Math.max(1,wp-bp)));
    if(m<118) gam=Math.log(132/255)/Math.log(m/255);
    else if(m>195) gam=Math.log(184/255)/Math.log(m/255);
    gam=Math.max(0.72,Math.min(1.3,gam));
  }
  if(bp===0&&wp===255&&Math.abs(gam-1)<0.02) return;
  const lut=new Uint8Array(256);
  for(let v=0;v<256;v++){ const t=Math.max(0,Math.min(1,(v-bp)/(wp-bp))); lut[v]=cl(255*Math.pow(t,gam)); }
  for(let i=0;i<d.length;i+=4){ d[i]=lut[d[i]]; d[i+1]=lut[d[i+1]]; d[i+2]=lut[d[i+2]]; }
  ctx.putImageData(img,0,0);
}

/* ═══════════════ SEGMENTATION ═══════════════ */
// Calls are queued: MediaPipe has ONE onResults callback, so two overlapping
// requests (preview + batch) used to receive each other's masks.
let _segQ=Promise.resolve();
function getSegMaskRaw(canvas){
  const job=_segQ.then(()=>_segOnce(canvas));
  _segQ=job.catch(()=>{});
  return job;
}
function _segOnce(canvas){
  if(!bgModelLoaded||!bgModel) return Promise.resolve(null);
  const W=canvas.width,H=canvas.height;
  return new Promise(resolve=>{
    let done=false; const fin=v=>{ if(!done){ done=true; clearTimeout(to); resolve(v); } };
    const to=setTimeout(()=>fin(null),20000);
    bgModel.onResults(r=>{
      try{ const raw=mk(W,H),rc=raw.getContext('2d'); rc.imageSmoothingEnabled=true; rc.imageSmoothingQuality='high'; rc.drawImage(r.segmentationMask,0,0,W,H); fin(raw); }
      catch(e){ fin(null); }
    });
    bgModel.send({image:canvas}).catch(()=>fin(null));
  });
}
/* Mask clean-up; every radius scales with the photo size (was fixed in px,
   so 600-DPI outputs got a much weaker clean-up than 300-DPI ones). */
function processMask(rawMask,edgeVal,rs){
  if(!rawMask) return null;
  rs=rs||1;
  const W=rawMask.width,H=rawMask.height;
  const rawD=rawMask.getContext('2d').getImageData(0,0,W,H).data;
  const toCanvas=(fn)=>{ const c=mk(W,H),x=c.getContext('2d'),id=x.createImageData(W,H); for(let i=0;i<id.data.length;i+=4){ const v=fn(i); id.data[i]=id.data[i+1]=id.data[i+2]=v; id.data[i+3]=255; } x.putImageData(id,0,0); return c; };
  const thrC=(src,t)=>{ const sd=src.getContext('2d').getImageData(0,0,W,H).data; return toCanvas(i=>sd[i]>=t?255:0); };
  // 1. binary → 2. morphological close (fills hair holes, boundary unchanged)
  const bin=toCanvas(i=>rawD[i]>128?255:0);
  const HFR=8*rs;
  const c1=thrC(blurCopy(bin,HFR),80), c2=thrC(blurCopy(c1,HFR),175);
  const clD=c2.getContext('2d').getImageData(0,0,W,H).data;
  // 3. soft mask = raw gradient, holes forced opaque; 4. sigmoid edge sharpening
  const soft=toCanvas(i=>{ const v=(clD[i]>127&&rawD[i]<100)?255:rawD[i]; const t=v/255; return Math.round(255/(1+Math.exp(-9*(t-0.46)))); });
  if(!edgeVal) return soft;
  const bin2=thrC(soft,128), st=Math.abs(edgeVal)/50;
  let res;
  if(edgeVal>0) res=thrC(blurCopy(bin2,(st*8+0.5)*rs),Math.max(8,160-st*152));
  else res=thrC(blurCopy(bin2,(st*12+0.5)*rs),128+st*120);
  return blurCopy(res,0.8*rs);
}
function compositeWithBg(canvas,maskC,replColor){
  if(!maskC) return;
  const W=canvas.width,H=canvas.height,ctx=canvas.getContext('2d');
  const msk=maskC.getContext('2d').getImageData(0,0,W,H).data;
  const id=ctx.getImageData(0,0,W,H),d=id.data;
  const[nR,nG,nB]=hexRGB(replColor);
  // old background colour (for exact colour decontamination of edge pixels)
  let eR=0,eG=0,eB=0,eCnt=0;
  for(let i=0;i<d.length;i+=4){ if(msk[i]<20){ eR+=d[i]; eG+=d[i+1]; eB+=d[i+2]; eCnt++; } }
  const hasBg=eCnt>=60, oR=hasBg?eR/eCnt:nR, oG=hasBg?eG/eCnt:nG, oB=hasBg?eB/eCnt:nB;
  for(let i=0;i<d.length;i+=4){
    const p=msk[i]/255;
    if(hasBg&&p>0.06&&p<0.94){
      d[i]=cl(d[i]+(nR-oR)*(1-p)); d[i+1]=cl(d[i+1]+(nG-oG)*(1-p)); d[i+2]=cl(d[i+2]+(nB-oB)*(1-p));
    }else{
      d[i]=cl(d[i]*p+nR*(1-p)); d[i+1]=cl(d[i+1]*p+nG*(1-p)); d[i+2]=cl(d[i+2]*p+nB*(1-p));
    }
    d[i+3]=255;
  }
  ctx.putImageData(id,0,0);
}

/* ═══════════════ ENCODE (real print DPI inside the file) ═══════════════
   Without this, JPEGs say 1:1 / 72-96 DPI and print apps size them wrongly. */
async function encodeOut(cv,fmt,q,dpi){
  const mime=fmt==='png'?'image/png':'image/jpeg';
  const blob=await new Promise((res,rej)=>cv.toBlob(b=>b?res(b):rej(new Error('Could not encode image (out of memory?)')),mime,fmt==='png'?undefined:q));
  try{
    const buf=new Uint8Array(await blob.arrayBuffer());
    return new Blob([fmt==='png'?_pngDpi(buf,dpi):_jpgDpi(buf,dpi)],{type:mime});
  }catch(e){ return blob; }
}
function _jpgDpi(b,dpi){
  if(b[0]!==0xFF||b[1]!==0xD8) return b;
  if(b[2]===0xFF&&b[3]===0xE0&&b[6]===0x4A&&b[7]===0x46&&b[8]===0x49&&b[9]===0x46){
    b[13]=1; b[14]=dpi>>8; b[15]=dpi&255; b[16]=dpi>>8; b[17]=dpi&255; return b;
  }
  const app=new Uint8Array([0xFF,0xE0,0,16,0x4A,0x46,0x49,0x46,0,1,1,1,dpi>>8,dpi&255,dpi>>8,dpi&255,0,0]);
  const o=new Uint8Array(b.length+18); o.set(b.subarray(0,2),0); o.set(app,2); o.set(b.subarray(2),20); return o;
}
let _crcT=null;
function _crc32(u8){
  if(!_crcT){ _crcT=new Uint32Array(256); for(let n=0;n<256;n++){ let c=n; for(let k=0;k<8;k++) c=c&1?0xEDB88320^(c>>>1):c>>>1; _crcT[n]=c>>>0; } }
  let c=0xFFFFFFFF; for(let i=0;i<u8.length;i++) c=_crcT[(c^u8[i])&255]^(c>>>8); return (c^0xFFFFFFFF)>>>0;
}
function _pngDpi(b,dpi){
  if(b[12]!==0x49||b[13]!==0x48) return b;            // IHDR must be first
  const ppm=Math.round(dpi/0.0254), ch=new Uint8Array(21), dv=new DataView(ch.buffer);
  dv.setUint32(0,9); ch.set([0x70,0x48,0x59,0x73],4); dv.setUint32(8,ppm); dv.setUint32(12,ppm); ch[16]=1;
  dv.setUint32(17,_crc32(ch.subarray(4,17)));
  const o=new Uint8Array(b.length+21); o.set(b.subarray(0,33),0); o.set(ch,33); o.set(b.subarray(33),54); return o;
}

/* ── utils ── */
function mk(w,h){ const c=document.createElement('canvas'); c.width=Math.max(1,w|0); c.height=Math.max(1,h|0); return c; }
function hiQResize(src,targetW,targetH){
  targetW=Math.max(1,Math.round(targetW)); targetH=Math.max(1,Math.round(targetH));
  let W=src.width||targetW, H=src.height||targetH, cur=src;
  // step down by halves (box-filter quality on every browser), then the final step
  while(W>targetW*2||H>targetH*2){
    const nW=Math.max(targetW,Math.floor(W/2)), nH=Math.max(targetH,Math.floor(H/2));
    const t=mk(nW,nH),tc=t.getContext('2d'); tc.imageSmoothingEnabled=true; tc.imageSmoothingQuality='high'; tc.drawImage(cur,0,0,nW,nH);
    cur=t; W=nW; H=nH;
  }
  const fin=mk(targetW,targetH),fc=fin.getContext('2d'); fc.imageSmoothingEnabled=true; fc.imageSmoothingQuality='high'; fc.drawImage(cur,0,0,targetW,targetH);
  return fin;
}
function sliderVal(id){ return parseFloat(document.getElementById(id)?.value??0); }
function chkVal(id){ return !!(document.getElementById(id)?.checked); }
function sleep(ms){ return new Promise(r=>setTimeout(r,ms)); }
function esc(s){ return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function hexRGB(hex){ const h=/^#[0-9a-f]{6}$/i.test(hex)?hex:'#ffffff'; return[parseInt(h.slice(1,3),16),parseInt(h.slice(3,5),16),parseInt(h.slice(5,7),16)]; }
function tagsHTML(tags){ return tags.map(t=>`<span class="cpb ${t.t}">${esc(t.l)}</span>`).join(''); }

/* ═══════════════ SETTINGS ═══════════════ */
function getSettings(){
  const sizeMode=document.getElementById('cpSize').value;
  const{w:outW,h:outH}=sizeMode==='original'?{w:0,h:0}:getPxSize();
  return{
    outW,outH,sizeMode,dpi:getDpi(),bgColor,
    fmt:document.getElementById('cpFmt').value,
    suffix:(document.getElementById('cpSuffix')?.value||'').trim().replace(/[\\/:*?"<>|]/g,''),
    jpegQ:+document.getElementById('cpJpeg').value||0.97,
    head:sliderVal('s_head'),top:sliderVal('s_top'),
    brightness:sliderVal('s_brightness'),contrast:sliderVal('s_contrast'),
    saturation:sliderVal('s_saturation'),warmth:sliderVal('s_warmth'),
    sharpness:sliderVal('s_sharpness'),vibrance:sliderVal('s_vibrance'),
    face_smooth:sliderVal('s_face_smooth'),skin_glow:sliderVal('s_skin_glow'),
    face_brighten:sliderVal('s_face_brighten'),hdr:sliderVal('s_hdr'),
    clarity:sliderVal('s_clarity'),noise_reduce:sliderVal('s_noise_reduce'),
    bg_smooth:sliderVal('s_bg_smooth'),
    awb:chkVal('chk_awb'),ae:chkVal('chk_ae'),autoTilt:chkVal('chk_tilt'),sharp:chkVal('chk_sharp'),
    fallback:chkVal('chk_fallback'),formMode:chkVal('chk_formmode'),bgRemove:chkVal('chk_bgremove'),
  };
}
/* Remember every setting in this browser — next batch starts where you left off. */
const SAVE_IDS=['cpSize','cpDpi','cpFmt','cpJpeg','cpCW','cpCH','cpSuffix','s_head','s_top','s_brightness','s_contrast','s_saturation','s_warmth','s_sharpness','s_vibrance','s_face_smooth','s_skin_glow','s_face_brighten','s_hdr','s_clarity','s_noise_reduce','s_bg_smooth','chk_awb','chk_ae','chk_tilt','chk_sharp','chk_fallback','chk_formmode','chk_bgremove'];
let _saveT=null;
function saveSettingsSoon(){ clearTimeout(_saveT); _saveT=setTimeout(()=>{
  const o={bgColor};
  SAVE_IDS.forEach(id=>{ const el=document.getElementById(id); if(el) o[id]=el.type==='checkbox'?el.checked:el.value; });
  try{ localStorage.setItem('cp_settings_v2',JSON.stringify(o)); }catch(e){}
},300); }
function loadSettings(){
  let o=null; try{ o=JSON.parse(localStorage.getItem('cp_settings_v2')||'null'); }catch(e){}
  if(!o) return;
  SAVE_IDS.forEach(id=>{ const el=document.getElementById(id); if(!el||!(id in o)) return; if(el.type==='checkbox') el.checked=!!o[id]; else el.value=o[id]; });
  document.querySelectorAll('input[type=range][id^="s_"]').forEach(r=>{ const v=document.getElementById('v_'+r.id.slice(2)); if(v&&r.id!=='s_bg_smooth') v.textContent=r.value+(r.dataset.unit??'%'); });
  if(/^#[0-9a-f]{6}$/i.test(o.bgColor||'')){
    bgColor=o.bgColor;
    const chip=[...document.querySelectorAll('#cpBgChips .cp-chip[data-c]')].find(c=>c.dataset.c.toLowerCase()===bgColor.toLowerCase());
    document.querySelectorAll('#cpBgChips .cp-chip').forEach(c=>c.classList.remove('active'));
    if(chip) chip.classList.add('active'); else { const cu=document.getElementById('cpBgCustom'); cu.value=bgColor; cu.parentElement.classList.add('active'); }
  }
  onBgSmoothChange(document.getElementById('s_bg_smooth'),true);
  document.getElementById('cpBgSub').classList.toggle('show',chkVal('chk_bgremove'));
}
document.addEventListener('input',e=>{ if(e.target.closest&&e.target.closest('.cp-ctrl-stack')) saveSettingsSoon(); });
document.addEventListener('change',e=>{ if(e.target.closest&&e.target.closest('.cp-ctrl-stack')) saveSettingsSoon(); });
function resetAdjustments(){
  document.querySelectorAll('.cp-ctrl-stack input[type=range][data-def]').forEach(r=>{ r.value=r.dataset.def; const v=document.getElementById('v_'+r.id.slice(2)); if(v) v.textContent=r.value+(r.dataset.unit??''); });
  saveSettingsSoon(); schedPrev(); toast('Adjustments reset','info');
}
function resetComposition(){
  document.getElementById('s_head').value=74; v_head.textContent='74%';
  document.getElementById('s_top').value=8; v_top.textContent='8%';
  saveSettingsSoon(); schedPrev();
}
function onDetectModeChg(){ anCache.clear(); saveSettingsSoon(); if(imgFiles.length) loadS1Preview(); if(document.getElementById('cpStep2').style.display!=='none') loadPreview(prevIdx); }

/* ═══════════════ STEP 1 PREVIEW ═══════════════ */
async function loadS1Preview(){
  if(!imgFiles.length) return;
  const tok=++s1Tok;
  releaseAn(s1An); s1An=null;
  document.getElementById('cpS1Empty').style.display='none';
  document.getElementById('cpS1Canvas').style.display='block';
  document.getElementById('cpS1Fname').textContent=imgFiles[0].name;
  showS1Loader(true,'Detecting face…');
  try{
    const an=await analyze(0,chkVal('chk_formmode'));
    if(tok!==s1Tok){ releaseAn(an); return; }
    s1An=an;
  }catch(e){ document.getElementById('cpS1Tags').innerHTML=tagsHTML([{t:'err',l:e.message}]); }
  showS1Loader(false); renderS1();
}
function schedS1Prev(){ clearTimeout(s1Timer); s1Timer=setTimeout(renderS1,160); }
async function renderS1(){
  if(!s1An||!s1An.bmp) return;
  const cv=document.getElementById('cpS1Canvas'), ctx=cv.getContext('2d'), s=getSettings();
  const an=s1An;
  if(s1Mode==='source'||s.sizeMode==='original'){
    const PW=420, PH=Math.min(Math.round(PW*an.H/an.W),700);
    cv.width=PW; cv.height=PH;
    ctx.imageSmoothingEnabled=true; ctx.imageSmoothingQuality='high';
    ctx.drawImage(_getPre(an,Math.min(1,PW*2/an.W)),0,0,PW,PH);
    if(s.sizeMode!=='original'){
      const g=geometry(an,s), kx=PW/an.W, ky=PH/an.H, c=g.crop;
      const pts=[{x:c.x,y:c.y},{x:c.x+c.w,y:c.y},{x:c.x+c.w,y:c.y+c.h},{x:c.x,y:c.y+c.h}].map(p=>_rot(p,g.P,g.angle));
      ctx.save(); ctx.fillStyle='rgba(0,0,0,.46)'; ctx.beginPath(); ctx.rect(0,0,PW,PH);
      ctx.moveTo(pts[0].x*kx,pts[0].y*ky); for(let i=3;i>=1;i--) ctx.lineTo(pts[i].x*kx,pts[i].y*ky); ctx.closePath(); ctx.fill('evenodd');
      ctx.strokeStyle='#8b5cf6'; ctx.lineWidth=2; ctx.beginPath(); pts.forEach((p,i)=>i?ctx.lineTo(p.x*kx,p.y*ky):ctx.moveTo(p.x*kx,p.y*ky)); ctx.closePath(); ctx.stroke(); ctx.restore();
      document.getElementById('cpS1Tags').innerHTML=tagsHTML([{t:'info',l:`${an.W}×${an.H}`},...resultTags(an,g)]);
    }else document.getElementById('cpS1Tags').innerHTML=tagsHTML([{t:'info',l:`${an.W}×${an.H}`},{t:'ok',l:'Original size'}]);
    return;
  }
  const PW=420, PH=Math.round(PW*s.outH/s.outW);
  const r=await renderOutput(an,s,null,{w:PW,h:PH,raw:true});
  if(!s1An) return;
  cv.width=PW; cv.height=PH; ctx.drawImage(r.canvas,0,0);
  document.getElementById('cpS1Tags').innerHTML=tagsHTML(resultTags(an,r.g));
}
function setS1Mode(mode,btn){
  s1Mode=mode;
  document.querySelectorAll('#s1BtnSrc,#s1BtnCrop').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active'); renderS1();
}
function showS1Loader(on,msg){
  document.getElementById('cpS1Loader').style.display=on?'flex':'none';
  if(msg) document.getElementById('cpS1Msg').textContent=msg;
}

/* ═══════════════ NAVIGATION ═══════════════ */
function showStep(n){
  ['cpStep1','cpStep2','cpStep3'].forEach((id,i)=>document.getElementById(id).style.display=i+1===n?'block':'none');
  ['sp1','sp2','sp3'].forEach((id,i)=>{
    const el=document.getElementById(id);
    el.classList.remove('active','done');
    if(i+1===n) el.classList.add('active'); else if(i+1<n) el.classList.add('done');
    el.querySelector('.cp-pnum').innerHTML=el.classList.contains('done')?'<i class="bi bi-check-lg" style="font-size:.6rem;"></i>':String(i+1);
  });
  window.scrollTo({top:0,behavior:'smooth'});
}
function goToStep2(){
  if(!inDir||!outDir||!imgFiles.length) return;
  document.getElementById('cpFCnt').textContent=imgFiles.length;
  showStep(2);
  loadPreview(0);
}

/* ═══════════════ STEP 2 PREVIEW (= exact output) ═══════════════ */
async function loadPreview(idx){
  if(!imgFiles.length) return;
  prevIdx=Math.max(0,Math.min(imgFiles.length-1,idx));
  const tok=++prevTok;
  document.getElementById('cpPrevFname').textContent=imgFiles[prevIdx].name;
  document.getElementById('cpPrevIdx').textContent=`${prevIdx+1} / ${imgFiles.length}`;
  document.getElementById('cpPrevBack').disabled=prevIdx===0;
  document.getElementById('cpPrevFwd').disabled=prevIdx>=imgFiles.length-1;
  showPrevLoader(true,modelsLoaded?'Detecting face…':'Loading AI models…');
  try{
    const an=await analyze(prevIdx,chkVal('chk_formmode'));
    if(tok!==prevTok){ releaseAn(an); return; }
    releaseAn(prevAn); prevAn=an; prevMaskCache={};
    await renderPrev();
  }catch(e){
    if(tok===prevTok){ document.getElementById('cpPrevTags').innerHTML=tagsHTML([{t:'err',l:'Preview error: '+e.message}]); }
  }finally{ if(tok===prevTok) showPrevLoader(false); }
}
function prevNav(d){ loadPreview(prevIdx+d); }
let _renderTok=0;
async function renderPrev(){
  if(!prevAn||!prevAn.bmp) return;
  const tok=++_renderTok, s=getSettings();
  if(s.bgRemove&&!bgModelLoaded) loadBgModel();
  let opt={maskCache:prevMaskCache};
  if(s.sizeMode==='original'){ const k=Math.min(1,900/Math.max(prevAn.W,prevAn.H)); opt.w=Math.round(prevAn.W*k); opt.h=Math.round(prevAn.H*k); }
  if(prevMode==='before') opt.raw=true;
  const r=await renderOutput(prevAn,s,null,opt);
  if(tok!==_renderTok||!prevAn) return;
  const cv=document.getElementById('cpPrevCanvas');
  cv.width=r.canvas.width; cv.height=r.canvas.height;
  cv.getContext('2d').drawImage(r.canvas,0,0);
  const tags=resultTags(prevAn,r.g);
  if(r.masked) tags.push({t:'ok',l:'BG removed'});
  else if(s.bgRemove) tags.push({t:'info',l:bgModelLoaded?'BG…':'Loading BG model…'});
  document.getElementById('cpPrevTags').innerHTML=tagsHTML(tags);
  drawGuides(document.getElementById('cpPrevOv'),r.g,cv.width,cv.height,showGuides&&s.sizeMode!=='original');
}
function drawGuides(ov,g,W,H,on){
  ov.width=W; ov.height=H;
  const c=ov.getContext('2d'); c.clearRect(0,0,W,H);
  if(!on||!g) return;
  const lw=Math.max(1,W/220);
  c.lineWidth=lw; c.setLineDash([lw*4,lw*3]);
  c.strokeStyle='rgba(139,92,246,.9)'; c.beginPath(); c.moveTo(W/2,0); c.lineTo(W/2,H); c.stroke();
  if(g.lines){
    const t=_toOut(g,W);
    [['crownY','#16a34a','Crown'],['eyeY','#2563eb','Eyes'],['chinY','#dc2626','Chin']].forEach(([k,col,l])=>{
      const y=t.y(g.lines[k]); c.strokeStyle=col; c.beginPath(); c.moveTo(0,y); c.lineTo(W,y); c.stroke();
      c.setLineDash([]); c.fillStyle=col; c.font=`bold ${Math.round(10*lw)}px sans-serif`; c.fillText(l,4*lw,y-3*lw); c.setLineDash([lw*4,lw*3]);
    });
  }
}
function toggleGuides(){ showGuides=!showGuides; document.getElementById('cpGuideBtn').classList.toggle('on',showGuides); renderPrev(); }
function schedPrev(){ clearTimeout(prevTimer); prevTimer=setTimeout(()=>{ if(document.getElementById('cpStep2').style.display!=='none') renderPrev(); },140); }
function setPrevMode(mode,btn){
  prevMode=mode;
  document.querySelectorAll('#cpBtnAfter,#cpBtnBefore').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active'); renderPrev();
}
function showPrevLoader(on,msg){
  document.getElementById('cpPrevLoader').style.display=on?'flex':'none';
  if(msg) document.getElementById('cpPrevMsg').textContent=msg;
}
function onBgSmoothChange(inp,silent){
  const v=+inp.value;
  document.getElementById('v_bg_smooth').textContent=(v>0?'+':'')+v;
  const pct=(v+50);
  if(v<0) inp.style.background=`linear-gradient(to right,#e5e7eb ${pct}%,#ef4444 ${pct}%,#ef4444 50%,#e5e7eb 50%)`;
  else if(v>0) inp.style.background=`linear-gradient(to right,#e5e7eb 50%,#7c3aed 50%,#7c3aed ${pct}%,#e5e7eb ${pct}%)`;
  else inp.style.background='#e5e7eb';
  if(!silent) schedPrev();
}

/* ═══════════════ PROCESSING ═══════════════ */
function outNameFor(info,s){
  const dot=info.name.lastIndexOf('.');
  const base=dot>0?info.name.slice(0,dot):info.name, ext=dot>0?info.name.slice(dot).toLowerCase():'';
  let fmt=s.fmt;
  if(fmt==='auto') fmt=ext==='.png'?'png':'jpg';
  const outExt=fmt==='png'?'.png':(ext==='.jpeg'?'.jpeg':'.jpg');
  let name=base+s.suffix+outExt, n=2;
  while(usedNames.has(name.toLowerCase())) name=`${base}${s.suffix}_${n++}${outExt}`;   // a.jpg + a.png → no silent overwrite
  usedNames.add(name.toLowerCase());
  return{name,fmt};
}
async function writeOut(name,blob){
  if(outDir==='ZIP'){ zipOut.file(name,blob); return; }
  const fh=await outDir.getFileHandle(name,{create:true});
  const wr=await fh.createWritable(); await wr.write(blob); await wr.close();
}
async function startProcessing(){
  if(running) return;
  running=true; stopReq=false; sessionCnt=0; errLog=[]; results=[]; usedNames=new Set();
  zipOut=(outDir==='ZIP')?new JSZip():null;
  const grid=document.getElementById('cpResGrid');
  grid.innerHTML='<div style="font-size:.76rem;color:var(--cp-muted);padding:.35rem 0;grid-column:1/-1;" data-ph>Processing…</div>';
  updCounts();
  document.getElementById('cpStSess').textContent='0';
  document.getElementById('cpDoneBanner').classList.remove('show');
  document.getElementById('cpZipBtn').style.display='none';
  document.getElementById('cpStopBtn').style.display='';
  document.getElementById('cpElog').innerHTML=''; document.getElementById('cpElog').classList.remove('show');
  showStep(3);
  releaseAn(prevAn); prevAn=null; releaseAn(s1An); s1An=null;
  const s=getSettings();
  await checkSameFolder();
  s.suffix=getSettings().suffix;
  // models
  if(!modelsLoaded){
    const w=document.getElementById('cpMdlLoad'),bar=document.getElementById('cpMdlBar'),st=document.getElementById('cpMdlStatus');
    w.classList.add('show');
    document.getElementById('cpCurFile').textContent='Loading AI face models…';
    const ok=await ensureModels((p,t)=>{ bar.style.width=p+'%'; st.textContent=t; });
    if(!ok){ st.textContent='Face models could not be loaded — photos will be centre-cropped'; bar.style.background='#ef4444'; }
    else setTimeout(()=>w.classList.remove('show'),700);
  }
  if(s.bgRemove&&!bgModelLoaded){
    document.getElementById('cpCurFile').textContent='Loading background removal model…';
    if(!await loadBgModel()) toast('Background model failed — continuing without background removal','warn');
  }
  if(outDir!=='ZIP'&&outDir?.requestPermission){
    try{ if(await outDir.queryPermission({mode:'readwrite'})!=='granted') await outDir.requestPermission({mode:'readwrite'}); }catch(e){}
  }
  fetch(STATS_API+'?action=my_stats').then(r=>r.json()).then(d=>{ if(d.success) document.getElementById('cpStAll').textContent=(d.totals?.photo_crop||0).toLocaleString(); }).catch(()=>{});
  const total=imgFiles.length, t0=Date.now();
  for(let i=0;i<total;i++){
    if(stopReq) break;
    const info=imgFiles[i];
    updProg(i,total,info.name,t0);
    const rec={i,name:info.name,status:'err',tags:[],man:null,outName:'',fmt:'jpg',thumb:null,face:null};
    let an=null;
    try{
      setBadges([['info','Loading']]);
      an=await analyze(i,s.formMode);
      if(!an.face&&!s.fallback&&s.sizeMode!=='original') throw new Error('No face found (centre-crop fallback is off)');
      setBadges([['ok',an.face?'Face found':'No face'],['info','Rendering…']]);
      const r=await renderOutput(an,s,null);
      const o=outNameFor(info,s);
      rec.outName=o.name; rec.fmt=o.fmt;
      await writeOut(o.name,await encodeOut(r.canvas,o.fmt,s.jpegQ,s.dpi));
      rec.tags=resultTags(an,r.g);
      // 'review' = the CROP may need a human look; low resolution is only an info tag
      rec.status=(!an.face||r.g.padded||(an.face&&!an.face.lm))?'warn':'ok';
      rec.thumb=_thumb(r.canvas);
      sessionCnt++; document.getElementById('cpStSess').textContent=sessionCnt;
      setBadges([['ok','Saved']]);
    }catch(e){
      rec.tags=[{t:'err',l:e.message||String(e)}];
      errLog.push(`${info.name}: ${e.message||e}`);
    }finally{ releaseAn(an); }
    results.push(rec); addResult(rec);
    updProg(i+1,total,i+1<total?imgFiles[i+1].name:'Finalizing…',t0);
    if(i%4===3) await sleep(0);       // keep the page responsive
  }
  document.getElementById('cpStopBtn').style.display='none';
  if(sessionCnt>0){
    try{
      const fd=new FormData();
      fd.append('action','record'); fd.append('csrf_token',CSRF_TOKEN);
      fd.append('action_type','photo_crop'); fd.append('panel','icard'); fd.append('count',sessionCnt);
      const d=window.smartFetch
        ? await window.smartFetch(STATS_API,fd,{label:'Usage stats: '+sessionCnt+' photos cropped',panel:'photocrop'})
        : await (await fetch(STATS_API,{method:'POST',body:fd})).json();
      if(d&&d.success&&!d.queued){ const d2=await (await fetch(STATS_API+'?action=my_stats')).json(); if(d2.success) document.getElementById('cpStAll').textContent=(d2.totals?.photo_crop||0).toLocaleString(); }
    }catch(e){}
  }
  if(errLog.length){
    const el=document.getElementById('cpElog');
    el.innerHTML=errLog.map(l=>`<div style="padding:2px 0;border-bottom:1px solid #f3f4f6;"><i class="bi bi-x-circle text-danger me-1"></i>${esc(l)}</div>`).join('');
    el.classList.add('show');
  }
  let zipMsg='';
  if(zipOut&&sessionCnt>0){ if(await downloadZip()) zipMsg=' · downloaded as ZIP'; document.getElementById('cpZipBtn').style.display=''; }
  const rev=results.filter(r=>r.status==='warn').length;
  document.getElementById('cpDoneBanner').classList.add('show');
  document.getElementById('cpDoneMsg').textContent=
    `${sessionCnt} photo${sessionCnt!==1?'s':''} cropped`+zipMsg+(rev?` · ${rev} to review (orange)`:'')+(errLog.length?` · ${errLog.length} failed`:'')+(stopReq?' · stopped early':'');
  document.getElementById('cpCurFile').textContent=stopReq?'Stopped':'Finished';
  running=false;
  if(rev) setResFilter('review',document.querySelector('.cp-filter[data-f="review"]'));
}
function stopProcessing(){ stopReq=true; document.getElementById('cpCurFile').textContent='Stopping after this photo…'; }
async function downloadZip(){
  if(!zipOut) return false;
  const btn=document.getElementById('cpZipBtn'); btn.disabled=true;
  document.getElementById('cpCurFile').textContent='Preparing ZIP…';
  try{
    const blob=await zipOut.generateAsync({type:'blob',compression:'STORE'});   // JPEG/PNG are already compressed
    saveAs(blob,'cropped_photos.zip');
    return true;
  }catch(e){ errLog.push('ZIP creation failed: '+e.message); toast('ZIP failed: '+e.message,'err'); return false; }
  finally{ btn.disabled=false; document.getElementById('cpCurFile').textContent='Finished'; }
}
function _thumb(cv){ const w=150,h=Math.round(150*cv.height/cv.width); return hiQResize(cv,w,h).toDataURL('image/jpeg',0.78); }
function updProg(done,total,name,t0){
  const pct=total>0?Math.round(done/total*100):0;
  document.getElementById('cpMainBar').style.width=pct+'%';
  document.getElementById('cpProgTxt').textContent=`${done} / ${total}`;
  document.getElementById('cpProgPct').textContent=pct+'%';
  if(t0&&done>0&&done<total){ const per=(Date.now()-t0)/done, left=Math.round(per*(total-done)/1000); document.getElementById('cpEta').textContent=left>=60?`~${Math.round(left/60)} min left`:`~${left}s left`; }
  else document.getElementById('cpEta').textContent='';
  if(name) document.getElementById('cpCurFile').textContent=name;
}
function setBadges(items){ document.getElementById('cpBadges').innerHTML=items.map(([t,l])=>`<span class="cpb ${t}">${esc(l)}</span>`).join(''); }

/* ── results grid ── */
function _resHTML(r){
  const st={ok:'✓',warn:'!',err:'✗',edit:'✎'}[r.status];
  const tip=esc(r.name+(r.tags.length?' — '+r.tags.map(t=>t.l).join(', '):''));
  return `${r.thumb?`<img src="${r.thumb}" alt="">`:'<div style="aspect-ratio:var(--ar,35/45);display:flex;align-items:center;justify-content:center;background:#fee2e2;"><i class="bi bi-x-lg text-danger"></i></div>'}
    <span class="st ${r.status}">${st}</span><div class="nm" title="${tip}">${esc(r.name)}</div>`;
}
function addResult(r){
  const g=document.getElementById('cpResGrid');
  g.querySelector('[data-ph]')?.remove();
  const d=document.createElement('div'); d.className='cp-res'; d.id='cpRes'+r.i; d.style.setProperty('--ar',_arCss());
  d.title=r.name; d.innerHTML=_resHTML(r); d.onclick=()=>openEditor(r.i);
  g.appendChild(d); _applyFilterOne(d,r); updCounts();
}
function updResult(r){ const d=document.getElementById('cpRes'+r.i); if(d){ d.innerHTML=_resHTML(r); _applyFilterOne(d,r); } updCounts(); }
function _applyFilterOne(d,r){ d.style.display=resFilter==='all'||(resFilter==='review'&&r.status==='warn')||(resFilter==='err'&&r.status==='err')?'':'none'; }
function setResFilter(f,btn){
  resFilter=f;
  document.querySelectorAll('.cp-filter').forEach(b=>b.classList.toggle('on',b===btn));
  results.forEach(r=>{ const d=document.getElementById('cpRes'+r.i); if(d) _applyFilterOne(d,r); });
}
function updCounts(){
  document.getElementById('cpCntAll').textContent=results.length;
  document.getElementById('cpCntRev').textContent=results.filter(r=>r.status==='warn').length;
  document.getElementById('cpCntErr').textContent=results.filter(r=>r.status==='err').length;
}

/* ═══════════════ MANUAL FINE-TUNE EDITOR ═══════════════ */
const ED={i:-1,an:null,man:null,s:null,drag:null,tok:0,full:null,ptrs:new Map(),pinch:null};
function _visibleResults(){ return results.filter(r=>{ const d=document.getElementById('cpRes'+r.i); return d&&d.style.display!=='none'; }); }
async function openEditor(i){
  if(running){ toast('Wait until the batch has finished, then fine-tune','info'); return; }
  const r=results.find(x=>x.i===i); if(!r) return;
  releaseAn(ED.an); ED.an=null;
  ED.i=i; ED.s=getSettings(); ED.s.suffix=getSettings().suffix;
  ED.man={zoom:1,rot:0,dx:0,dy:0,...(r.man||{})};
  document.getElementById('cpEdOv').classList.add('show');
  document.getElementById('cpEdName').textContent=r.name;
  const vis=_visibleResults(), pos=vis.indexOf(r);
  document.getElementById('cpEdPos').textContent=pos>=0?`${pos+1} / ${vis.length}`:'';
  document.getElementById('cpEdTags').innerHTML=tagsHTML([{t:'info',l:'Loading…'}]);
  _edSyncUI();
  try{
    const an=await analyze(i,ED.s.formMode);
    if(ED.i!==i){ releaseAn(an); return; }
    ED.an=an; edDraw();
  }catch(e){ document.getElementById('cpEdTags').innerHTML=tagsHTML([{t:'err',l:e.message}]); }
}
function closeEditor(){ document.getElementById('cpEdOv').classList.remove('show'); releaseAn(ED.an); ED.an=null; ED.i=-1; }
function edNav(d){
  const vis=_visibleResults(), pos=vis.findIndex(r=>r.i===ED.i);
  const nx=vis[pos+d]; if(nx) openEditor(nx.i);
}
function _edSyncUI(){
  const m=ED.man;
  document.getElementById('s_edZoom').value=Math.round(m.zoom*100); document.getElementById('v_edZoom').textContent=Math.round(m.zoom*100)+'%';
  document.getElementById('s_edRot').value=m.rot; document.getElementById('v_edRot').textContent=(+m.rot).toFixed(1)+'°';
}
function edSet(k,v){ ED.man[k]=v; _edSyncUI(); edDraw(); }
function edNudge(x,y,big){ const st=big?0.03:0.006; ED.man.dx+=x*st; ED.man.dy+=y*st; edDraw(); }
function edReset(){ ED.man={zoom:1,rot:0,dx:0,dy:0}; _edSyncUI(); edDraw(); }
function edDraw(){
  if(!ED.an||!ED.an.bmp) return;
  const s=ED.s, cv=document.getElementById('cpEdCanvas');
  let W=300,H;
  if(s.sizeMode==='original'){ H=Math.round(W*ED.an.H/ED.an.W); } else H=Math.round(W*s.outH/s.outW);
  // fast view while dragging, full-quality view (filters, AI background) after a pause
  renderOutput(ED.an,s,ED.man,{w:W*2,h:H*2,fast:true}).then(r=>{ _edPaint(r,W*2,H*2); });
  clearTimeout(ED.full); const tok=++ED.tok;
  ED.full=setTimeout(async()=>{ const r=await renderOutput(ED.an,s,ED.man,{w:W*2,h:H*2}); if(tok===ED.tok&&ED.an) _edPaint(r,W*2,H*2,true); },260);
}
function _edPaint(r,W,H,full){
  const cv=document.getElementById('cpEdCanvas'); cv.width=W; cv.height=H; cv.getContext('2d').drawImage(r.canvas,0,0);
  drawGuides(document.getElementById('cpEdOvc'),r.g,W,H,chkVal('chk_edGuides')&&ED.s.sizeMode!=='original');
  if(full) document.getElementById('cpEdTags').innerHTML=tagsHTML(resultTags(ED.an,r.g));
}
async function edSave(){
  const r=results.find(x=>x.i===ED.i); if(!r||!ED.an) return;
  const btn=document.getElementById('cpEdSave'); btn.disabled=true;
  try{
    const out=await renderOutput(ED.an,ED.s,ED.man);
    if(!r.outName){ const o=outNameFor(imgFiles[r.i],ED.s); r.outName=o.name; r.fmt=o.fmt; }
    if(outDir!=='ZIP'&&outDir?.requestPermission){ try{ if(await outDir.queryPermission({mode:'readwrite'})!=='granted') await outDir.requestPermission({mode:'readwrite'}); }catch(e){} }
    await writeOut(r.outName,await encodeOut(out.canvas,r.fmt,ED.s.jpegQ,ED.s.dpi));
    if(r.status==='err'){ sessionCnt++; document.getElementById('cpStSess').textContent=sessionCnt; }
    r.man={...ED.man}; r.status='edit'; r.thumb=_thumb(out.canvas); r.tags=resultTags(ED.an,out.g); r.tags.unshift({t:'ok',l:'Manually adjusted'});
    updResult(r);
    toast(`Saved ${r.outName}`+(outDir==='ZIP'?' — click "Download ZIP" for the updated set':''),'ok');
    if(outDir==='ZIP') document.getElementById('cpZipBtn').style.display='';
    edNav(1);
  }catch(e){ toast('Save failed: '+e.message,'err'); }
  finally{ btn.disabled=false; }
}
(function(){
  const box=document.getElementById('cpEdCv');
  const dist=()=>{ const p=[...ED.ptrs.values()]; return Math.hypot(p[0].x-p[1].x,p[0].y-p[1].y); };
  box.addEventListener('pointerdown',e=>{ box.setPointerCapture(e.pointerId); ED.ptrs.set(e.pointerId,{x:e.clientX,y:e.clientY}); box.classList.add('drag'); if(ED.ptrs.size===2) ED.pinch={d:dist(),z:ED.man.zoom}; });
  box.addEventListener('pointermove',e=>{
    if(!ED.ptrs.has(e.pointerId)||!ED.an) return;
    const prev=ED.ptrs.get(e.pointerId); ED.ptrs.set(e.pointerId,{x:e.clientX,y:e.clientY});
    if(ED.ptrs.size===2&&ED.pinch){ ED.man.zoom=Math.max(0.6,Math.min(2,ED.pinch.z*dist()/ED.pinch.d)); _edSyncUI(); edDraw(); return; }
    const rc=box.getBoundingClientRect();
    ED.man.dx-=(e.clientX-prev.x)/rc.width; ED.man.dy-=(e.clientY-prev.y)/rc.height;
    edDraw();
  });
  const up=e=>{ ED.ptrs.delete(e.pointerId); if(ED.ptrs.size<2) ED.pinch=null; if(!ED.ptrs.size) box.classList.remove('drag'); };
  box.addEventListener('pointerup',up); box.addEventListener('pointercancel',up);
  box.addEventListener('wheel',e=>{ e.preventDefault(); ED.man.zoom=Math.max(0.6,Math.min(2,ED.man.zoom*(e.deltaY<0?1.04:1/1.04))); _edSyncUI(); edDraw(); },{passive:false});
  document.addEventListener('keydown',e=>{
    if(!document.getElementById('cpEdOv').classList.contains('show')) return;
    if(e.target.tagName==='INPUT'&&e.target.type!=='range') return;
    const k=e.key;
    if(k==='Escape'){ closeEditor(); return; }
    if(k==='Enter'){ e.preventDefault(); edSave(); return; }
    if(k==='PageDown'){ e.preventDefault(); edNav(1); return; }
    if(k==='PageUp'){ e.preventDefault(); edNav(-1); return; }
    const mv={ArrowLeft:[-1,0],ArrowRight:[1,0],ArrowUp:[0,-1],ArrowDown:[0,1]}[k];
    if(mv&&e.target.type!=='range'){ e.preventDefault(); edNudge(mv[0],mv[1],e.shiftKey); }
  });
})();

function resetAll(){
  if(running) return;
  inDir=null; outDir=null; zipOut=null; imgFiles=[]; sessionCnt=0; errLog=[]; results=[]; anCache.clear();
  releaseAn(s1An); s1An=null; releaseAn(prevAn); prevAn=null;
  ['cpInCard','cpOutCard'].forEach(id=>document.getElementById(id).classList.remove('sel'));
  document.getElementById('cpInSub').textContent='Click to select · or drop photos here';
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
window.addEventListener('beforeunload',e=>{ if(running){ e.preventDefault(); e.returnValue=''; } });

/* ── init ── */
loadSettings();
onSizeChange();
// warm the face models up in the background so the first preview is quick
setTimeout(()=>ensureModels(),600);
</script>
<?php require_once __DIR__ . '/icard_footer.php'; ?>
