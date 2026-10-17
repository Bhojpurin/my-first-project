<?php
$pageTitle = 'Smart ID Maker';
require_once __DIR__ . '/icard_header.php';
$_sc = csrfToken();
$_sa = BASE_URL . '/api/usage_stats.php';
?>
<style>
:root{
  --p:#4f46e5;--pL:#ede9fe;--pD:#4338ca;--v:#7c3aed;
  --g:#e5e7eb;--gt:#1f2937;--gm:#6b7280;--gs:#9ca3af;
  --ok:#059669;--okL:#ecfdf5;--warn:#d97706;--err:#dc2626;--errL:#fef2f2;
  --r:12px;--r2:8px;--surf:#fff;--bg:#f8fafc;
}

/* ── Wizard bar ── */
.sw-bar{display:flex;align-items:center;background:#fff;border-radius:var(--r);border:1px solid var(--g);padding:.7rem 1.2rem;margin-bottom:1.2rem;box-shadow:0 1px 4px rgba(0,0,0,.04);overflow-x:auto;gap:0;}
.sw-item{display:flex;align-items:center;flex:1;min-width:0;}
.sw-item:last-child{flex:none;}
.sw-num{width:30px;height:30px;border-radius:50%;background:var(--g);color:var(--gm);font-size:.74rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s;position:relative;z-index:1;}
.sw-lbl{font-size:.7rem;font-weight:600;color:var(--gm);white-space:nowrap;margin-left:.35rem;transition:color .2s;}
.sw-line{flex:1;height:2px;background:var(--g);margin:0 .4rem;min-width:12px;}
.sw-item.done .sw-num{background:#10b981;color:#fff;}
.sw-item.done .sw-lbl{color:#10b981;}
.sw-item.done .sw-line{background:#10b981;}
.sw-item.active .sw-num{background:var(--p);color:#fff;box-shadow:0 0 0 4px rgba(79,70,229,.18);}
.sw-item.active .sw-lbl{color:var(--p);font-weight:700;}
@media(max-width:560px){.sw-lbl{display:none}}

/* ── Step panels ── */
.sw-step{display:none;animation:fadeIn .2s ease;}
.sw-step.active{display:block;}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.48rem .95rem;border-radius:var(--r2);font-size:.82rem;font-weight:700;cursor:pointer;border:none;transition:all .14s;}
.btn-p{background:var(--p);color:#fff;} .btn-p:hover{background:var(--pD);}
.btn-s{background:#fff;color:var(--gt);border:1.5px solid var(--g);} .btn-s:hover{border-color:var(--p);color:var(--p);}
.btn-ok{background:#059669;color:#fff;} .btn-ok:hover{background:#047857;}
.btn-v{background:linear-gradient(135deg,var(--p),var(--v));color:#fff;box-shadow:0 4px 14px rgba(79,70,229,.3);}
.btn-v:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,70,229,.4);}
.btn-sm{padding:.32rem .7rem;font-size:.74rem;}
.btn-ico{width:34px;height:34px;padding:0;justify-content:center;border-radius:var(--r2);background:#fff;border:1.5px solid var(--g);color:var(--gm);} .btn-ico:hover{border-color:var(--p);color:var(--p);}

/* ── Cards ── */
.sc{background:#fff;border-radius:var(--r);border:1px solid var(--g);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:.85rem;}
.sc:last-child{margin-bottom:0;}
.sc-h{padding:.6rem 1rem;border-bottom:1px solid var(--g);font-size:.82rem;font-weight:700;color:var(--gt);display:flex;align-items:center;gap:.4rem;}
.sc-h i{color:var(--p);}
.sc-b{padding:.9rem 1rem;}

/* ── Upload zones ── */
.drop-zone{border:2.5px dashed #d1d5db;border-radius:var(--r);padding:1.6rem 1rem;text-align:center;transition:all .18s;cursor:pointer;position:relative;background:#fafafa;}
.drop-zone:hover,.drop-zone.drag{border-color:var(--p);background:var(--pL);}
.drop-zone.filled{border-color:#10b981;background:#ecfdf5;border-style:solid;}
.drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.dz-ico{font-size:2rem;color:#9ca3af;display:block;margin-bottom:.4rem;transition:color .18s;}
.drop-zone:hover .dz-ico,.drop-zone.drag .dz-ico{color:var(--p);}
.drop-zone.filled .dz-ico{color:#10b981;}
.dz-title{font-size:.84rem;font-weight:700;color:var(--gt);margin-bottom:.2rem;}
.dz-sub{font-size:.72rem;color:var(--gm);}
.dz-ok{font-size:.74rem;font-weight:700;color:#059669;margin-top:.35rem;display:none;}
.drop-zone.filled .dz-ok{display:block;}

/* ── Excel preview ── */
.xprev-wrap{max-height:200px;overflow:auto;border:1px solid var(--g);border-radius:var(--r2);margin-top:.7rem;}
.xprev-tbl{width:100%;border-collapse:collapse;font-size:.72rem;}
.xprev-tbl th{background:var(--pL);color:var(--p);padding:.35rem .5rem;font-weight:700;position:sticky;top:0;white-space:nowrap;border-bottom:1px solid var(--g);}
.xprev-tbl td{padding:.3rem .5rem;border-bottom:1px solid #f3f4f6;color:var(--gt);white-space:nowrap;max-width:120px;overflow:hidden;text-overflow:ellipsis;}
.xprev-tbl tr:last-child td{border-bottom:none;}
.xprev-tbl tr:hover td{background:#f8fafc;}

/* ── Photo strip ── */
.photo-strip{display:flex;gap:.5rem;margin-top:.6rem;flex-wrap:wrap;}
.photo-thumb{width:44px;height:44px;object-fit:cover;border-radius:6px;border:2px solid var(--g);}
.photo-more{width:44px;height:44px;border-radius:6px;background:var(--pL);color:var(--p);font-size:.7rem;font-weight:800;display:flex;align-items:center;justify-content:center;}

/* ── Step 2: Column cards ── */
.col-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.6rem;}
.col-card{background:#fff;border:2px solid var(--g);border-radius:var(--r2);padding:.65rem .7rem;transition:all .15s;}
.col-card.assigned{border-color:var(--p);background:var(--pL);}
.col-name{font-size:.78rem;font-weight:700;color:var(--gt);margin-bottom:.2rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.col-sample{font-size:.66rem;color:var(--gm);margin-bottom:.45rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.col-roles{display:flex;flex-wrap:wrap;gap:.25rem;}
.role-btn{font-size:.62rem;font-weight:700;padding:.18rem .45rem;border-radius:8px;border:1.5px solid var(--g);background:#fff;color:var(--gm);cursor:pointer;transition:all .12s;}
.role-btn:hover{border-color:var(--p);color:var(--p);}
.role-btn.on{border-color:var(--p);background:var(--p);color:#fff;}
.role-btn.on-qr{border-color:#059669;background:#059669;color:#fff;}
.role-btn.on-bar{border-color:#d97706;background:#d97706;color:#fff;}
.role-btn.on-photo{border-color:#2563eb;background:#2563eb;color:#fff;}
.role-btn.on-sign{border-color:#7c3aed;background:#7c3aed;color:#fff;}

/* ── QR/Barcode preview boxes ── */
.code-prev-box{background:#f8fafc;border:1px solid var(--g);border-radius:var(--r2);padding:.7rem;display:flex;flex-direction:column;align-items:center;gap:.4rem;min-height:80px;}
#barcodePreviewCanvas{max-width:100%;height:60px;}

/* ── Step 3: Size presets ── */
.sz-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(92px,1fr));gap:.4rem;}
.sz-cat{grid-column:1/-1;font-size:.64rem;font-weight:800;color:var(--gm);text-transform:uppercase;letter-spacing:.06em;padding:.55rem 0 .25rem;border-bottom:2px solid #f1f5f9;margin-top:.5rem;}
.sz-cat:first-child{margin-top:0;padding-top:.1rem;}
.sz-card{border:2px solid var(--g);border-radius:10px;padding:.5rem .4rem;text-align:center;cursor:pointer;transition:all .15s;background:#fff;position:relative;}
.sz-card:hover{border-color:#a5b4fc;transform:translateY(-1px);box-shadow:0 4px 12px rgba(99,102,241,.12);}
.sz-card.on{border-color:#2563eb;background:#eff6ff;box-shadow:0 0 0 3px rgba(37,99,235,.14);}
.sz-star::after{content:'★';position:absolute;top:-6px;right:-6px;background:#f59e0b;color:#fff;font-size:.5rem;width:15px;height:15px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:800;line-height:1;}
.sz-thumb{display:flex;justify-content:center;align-items:flex-end;height:40px;margin-bottom:.3rem;}
.sz-thumb-inner{border:2px solid #93c5fd;border-radius:3px;background:#dbeafe;overflow:hidden;transition:all .15s;background-size:cover;background-position:center;}
.sz-card.on .sz-thumb-inner{border-color:#2563eb;background-color:#bfdbfe;}
.sz-name{font-size:.72rem;font-weight:700;color:#1e293b;line-height:1.2;margin-bottom:.1rem;}
.sz-dim{font-size:.58rem;color:var(--gm);}
.sz-orient{font-size:.58rem;color:#6366f1;font-weight:700;margin-top:.1rem;}
.sz-custom-row{display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap;margin-top:.6rem;padding:.65rem .7rem;background:#f8fafc;border:1px solid var(--g);border-radius:var(--r2);}
.sz-custom-row label{font-size:.72rem;font-weight:600;color:#374151;display:block;margin-bottom:.2rem;}
.sz-custom-row input{width:72px;border:1.5px solid #d1d5db;border-radius:6px;padding:.3rem .4rem;font-size:.78rem;outline:none;}
.sz-custom-row input:focus{border-color:#2563eb;}
#szPreviewWrap{display:flex;align-items:center;justify-content:center;background:#f8fafc;border-radius:8px;padding:1.4rem;overflow:visible;}
#szPreviewBox{position:relative;overflow:visible;flex-shrink:0;}
#pvClip{position:absolute;inset:0;overflow:hidden;border:2px solid #2563eb;border-radius:5px;box-shadow:0 4px 18px rgba(37,99,235,.15);background:linear-gradient(135deg,#dbeafe,#c7d2fe);}
#pvImg{position:absolute;cursor:move;user-select:none;}
.pv-corner{position:absolute;width:13px;height:13px;border-radius:50%;background:#2563eb;border:2.5px solid #fff;box-shadow:0 1px 5px rgba(37,99,235,.5);z-index:10;display:none;transition:transform .12s;}
.pv-corner:hover{transform:scale(1.3);}
.pv-corner[data-dir=nw]{top:-7px;left:-7px;cursor:nw-resize;}
.pv-corner[data-dir=ne]{top:-7px;right:-7px;cursor:ne-resize;}
.pv-corner[data-dir=sw]{bottom:-7px;left:-7px;cursor:sw-resize;}
.pv-corner[data-dir=se]{bottom:-7px;right:-7px;cursor:se-resize;}

/* ── Designer layout ── */
.des-wrap{display:flex;flex-direction:column;height:calc(100vh - 128px);min-height:560px;}
.des-toolbar{display:flex;align-items:center;gap:.28rem;flex-wrap:nowrap;padding:.42rem .65rem;background:#fff;border-bottom:2px solid #f1f5f9;flex-shrink:0;overflow-x:auto;}
.des-body{display:grid;grid-template-columns:196px 1fr 228px;flex:1;overflow:hidden;}
.des-left{border-right:1px solid #f1f5f9;overflow-y:auto;padding:.42rem .38rem;}
.des-center{background:#e2e8f0;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;overflow:auto;padding:.75rem;}
.des-right{border-left:1px solid #f1f5f9;overflow-y:auto;background:#fafafe;}
.des-mob-only{display:none;}
.des-panel-mob-hd{display:none;}
#desSheetBackdrop{display:none;}
@media(max-width:900px){
  /* Hide the page's own promo banner ONLY while step 4 (Designer) is open —
     it's just orientation content, not needed while actively working, and
     it was eating screen height the card design needs to stay visible
     behind the Add Elements / Properties sheets. Same fix already applied
     on school/school_icard.php's Designer. */
  body.des-step4-mob .qprint-banner{display:none;}
  .des-wrap{height:auto;min-height:0;}
  /* Toolbar used to just horizontal-scroll on phones, which could hide the
     Generate button off to the right entirely unless you scrolled to find
     it — wraps onto extra rows instead, same fix already applied on the
     Generate ID Cards / school ID card designers. */
  .des-toolbar{flex-wrap:wrap;overflow-x:visible;row-gap:.35rem;}
  .des-toolbar .tb-sep{display:none;}
  .des-toolbar .btn-ico{width:38px;height:38px;}
  /* Add/Properties need a visible label, not just an icon+tooltip — tooltips
     never appear on touch, so there'd be no clue what tapping one does. */
  .des-mob-only{display:inline-flex;align-items:center;gap:.32rem;height:34px;padding:0 .65rem;
    border:1.5px solid #e2e8f0;border-radius:8px;background:#fff;font-size:.72rem;font-weight:700;
    color:#374151;cursor:pointer;white-space:nowrap;}
  .des-mob-add{background:var(--pL);border-color:var(--p);color:var(--p);}
  .des-body{display:flex;flex-direction:column;height:auto;}
  .des-center{order:1;padding:.6rem .4rem;max-height:60vh;}
  .des-left,.des-right{
    display:none;position:fixed;left:0;right:0;bottom:0;z-index:9300;
    /* Capped so a real strip of the card stays visible above the sheet —
       matches the same fix already applied to Generate ID Cards' Designer,
       where letting this run closer to full height left almost nothing
       showing above it. */
    max-height:min(46vh, calc(100vh - 360px));background:#fff;flex-direction:column;overflow:hidden;
    border-radius:16px 16px 0 0;box-shadow:0 -10px 34px rgba(0,0,0,.22);
    padding:0;border:none;
  }
  .des-left.mob-open,.des-right.mob-open{display:flex;}
  .des-panel-mob-hd{
    display:flex;align-items:center;gap:.6rem;padding:.85rem 1rem .7rem;
    border-bottom:1.5px solid #f1f5f9;flex-shrink:0;position:relative;
  }
  .des-panel-mob-hd::before{
    content:'';position:absolute;top:.4rem;left:50%;transform:translateX(-50%);
    width:36px;height:4px;border-radius:3px;background:#e2e8f0;
  }
  .des-panel-mob-hd span{font-size:.88rem;font-weight:800;color:#1f2937;flex:1;margin-top:.3rem;}
  .des-panel-mob-hd button{width:32px;height:32px;border:none;background:#f1f5f9;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.95rem;color:#64748b;cursor:pointer;flex-shrink:0;margin-top:.2rem;}
  /* flex:1+min-height:0 is what actually lets this scroll internally instead
     of the sheet's own overflow:hidden just clipping everything below the
     fixed header — without min-height:0 a flex child never shrinks past its
     content's natural height, so the sheet would grow past max-height (or
     clip) instead of scrolling. */
  .des-panel-mob-body{flex:1;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;padding:.7rem .9rem calc(20px + var(--sbnav-safe-b,0px));}
  #desSheetBackdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.4);z-index:9250;}
  #desSheetBackdrop.show{display:block;}
  /* Bigger touch targets inside the sheets */
  .add-el-btn{padding:.6rem .2rem;}
  .add-el-btn .ael-ico{width:36px;height:36px;font-size:1.05rem;}
  .add-el-btn .ael-lbl{font-size:.7rem;}
  .field-item{padding:.5rem .6rem;}
  .rp-inp,.rp-btn,.aln-btn{min-height:36px;}
  .rp-clr{min-height:36px;}
  .layer-item{padding:.4rem .5rem;min-height:38px;}
  .psh-btn{padding:.4rem;}
}
.tb-sep{width:1px;height:20px;background:#e2e8f0;flex-shrink:0;margin:0 .05rem;}

/* ── Size chip in toolbar ── */
.des-szbar{display:flex;align-items:center;gap:.2rem;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:.18rem .38rem;}
.des-szbar input{width:46px;font-size:.68rem;border:1px solid #d1d5db;border-radius:4px;padding:.12rem .2rem;outline:none;text-align:center;}
.des-szbar input:focus{border-color:var(--p);}
.des-szbar span{font-size:.63rem;color:#94a3b8;}

/* ── Row preview pill ── */
.row-pill{display:flex;align-items:center;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:20px;padding:.12rem .28rem;gap:.05rem;}
.row-pill-arrow{width:20px;height:20px;border:none;background:none;cursor:pointer;color:#6366f1;font-size:.8rem;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:background .12s;}
.row-pill-arrow:hover{background:#ede9fe;}
.row-pill-arrow:disabled{color:#d1d5db;cursor:default;}
.row-pill-label{min-width:56px;text-align:center;font-size:.68rem;font-weight:700;color:#374151;}
.live-btn{display:flex;align-items:center;gap:.22rem;padding:.18rem .48rem;border-radius:20px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.67rem;font-weight:700;cursor:pointer;color:#64748b;white-space:nowrap;transition:all .14s;}
.live-btn.on{background:#f0fdf4;border-color:#22c55e;color:#16a34a;}
.live-btn i{font-size:.74rem;}

/* ── Canvas wrap ── */
.canvas-wrap{position:relative;box-shadow:0 4px 24px rgba(0,0,0,.14);border-radius:4px;overflow:hidden;}
#desCanvas{display:block;}

/* ── Left panel ── */
.des-section-lbl{font-size:.57rem;font-weight:800;color:#b0bec5;text-transform:uppercase;letter-spacing:.09em;padding:.48rem .06rem .18rem;display:flex;align-items:center;gap:.2rem;}
.add-el-grid{display:grid;grid-template-columns:1fr 1fr;gap:.22rem;margin-bottom:.15rem;}
.add-el-btn{display:flex;flex-direction:column;align-items:center;gap:.1rem;padding:.34rem .1rem;border-radius:9px;border:1.5px solid #f1f5f9;background:#fff;cursor:pointer;transition:all .14s;box-shadow:0 1px 2px rgba(0,0,0,.04);}
.add-el-btn:hover{border-color:var(--p);box-shadow:0 2px 8px rgba(99,102,241,.13);transform:translateY(-1px);}
.add-el-btn .ael-ico{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0;}
.add-el-btn .ael-lbl{font-size:.61rem;font-weight:700;color:#374151;line-height:1;}
.add-el-btn:hover .ael-lbl{color:var(--p);}
.add-el-btn.full{grid-column:1/-1;flex-direction:row;padding:.28rem .48rem;gap:.35rem;}
.add-el-btn.full .ael-ico{width:22px;height:22px;border-radius:6px;}
.add-el-btn.full .ael-lbl{font-size:.64rem;}
.field-item{display:flex;align-items:center;gap:.25rem;padding:.22rem .34rem;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;margin-bottom:.14rem;transition:all .12s;}
.field-item:hover{border-color:var(--p);background:#f0f4ff;}
.field-item i{font-size:.63rem;color:#a5b4fc;}
.field-item span{font-size:.67rem;font-weight:600;color:#374151;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.field-item:hover span,.field-item:hover i{color:var(--p);}

/* ── Right panel ── */
.rp-sec{padding:.44rem .58rem;border-bottom:1px solid #f1f5f9;}
.rp-sec:last-child{border-bottom:none;}
.rp-hd{font-size:.59rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.34rem;display:flex;align-items:center;gap:.25rem;}
.rp-hd i{color:var(--p);font-size:.7rem;}
.rp-row{display:flex;align-items:center;gap:.24rem;margin-bottom:.24rem;}
.rp-row:last-child{margin-bottom:0;}
.rp-lbl{font-size:.61rem;color:#94a3b8;min-width:30px;font-weight:600;flex-shrink:0;}
.prop-inp,.rp-inp{flex:1;font-size:.71rem;border:1.5px solid #e5e7eb;border-radius:6px;padding:.22rem .32rem;outline:none;background:#fff;min-width:0;color:#1f2937;transition:border-color .12s;}
.prop-inp:focus,.rp-inp:focus{border-color:var(--p);}
.prop-btn,.rp-btn{height:26px;min-width:26px;border:1.5px solid #e5e7eb;border-radius:6px;background:#fff;cursor:pointer;font-size:.72rem;display:flex;align-items:center;justify-content:center;transition:all .12s;padding:0 .26rem;color:#374151;}
.prop-btn:hover,.prop-btn.on,.rp-btn:hover,.rp-btn.on{border-color:var(--p);background:var(--pL);color:var(--p);}
.prop-clr,.rp-clr{height:26px;width:30px;border-radius:6px;border:1.5px solid #e5e7eb;cursor:pointer;flex-shrink:0;}
.prop-rng,.rp-rng{flex:1;accent-color:var(--p);}
.rp-val{font-size:.61rem;color:#94a3b8;min-width:24px;text-align:right;flex-shrink:0;}

/* ── Alignment grid ── */
.aln-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:.18rem;}
.aln-btn{height:27px;border:1.5px solid #e5e7eb;border-radius:6px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.8rem;color:#64748b;transition:all .12s;}
.aln-btn:hover{border-color:var(--p);background:var(--pL);color:var(--p);}

/* ── Layer list ── */
.layer-item{display:flex;align-items:center;gap:.28rem;padding:.24rem .34rem;border-radius:6px;cursor:pointer;font-size:.67rem;color:#374151;border:1.5px solid transparent;margin-bottom:.1rem;transition:all .1s;}
.layer-item:hover{background:#f1f5f9;}
.layer-item.sel{background:var(--pL);border-color:#c7d2fe;color:var(--p);}
.layer-item i{font-size:.68rem;color:#9ca3af;flex-shrink:0;}
.layer-item.sel i{color:var(--p);}
.layer-item span{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.layer-btn{width:18px;height:18px;border:none;background:none;cursor:pointer;color:#d1d5db;font-size:.64rem;display:flex;align-items:center;justify-content:center;border-radius:3px;}
.layer-btn:hover{color:#dc2626;background:#fee2e2;}

/* ── Photo shape buttons ── */
.psh-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.2rem;margin-bottom:.28rem;}
.psh-btn{border:1.5px solid #e5e7eb;border-radius:7px;background:#fff;cursor:pointer;padding:.26rem;transition:all .12s;display:flex;align-items:center;justify-content:center;}
.psh-btn svg{width:20px;height:20px;display:block;color:#9ca3af;}
.psh-btn:hover,.psh-btn.on{border-color:var(--p);background:var(--pL);}
.psh-btn.on svg{color:var(--p);}

/* ── Legacy compat aliases ── */
.prop-lbl{font-size:.61rem;font-weight:600;color:#94a3b8;flex-shrink:0;min-width:30px;}
.prop-row{display:flex;align-items:center;gap:.24rem;margin-bottom:.24rem;}
.prop-row:last-child{margin-bottom:0;}
.prop-section-title{font-size:.59rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.34rem;display:flex;align-items:center;gap:.25rem;}
.prop-section-title i{color:var(--p);}

/* ── Row preview bar (legacy kept for js compat) ── */
.prev-bar{display:none;}
.prev-mode-btn{display:none;}

/* ── Step 5 generate ── */
.gen-layout{display:grid;grid-template-columns:320px 1fr;gap:1rem;align-items:start;}
@media(max-width:800px){.gen-layout{grid-template-columns:1fr}}
.fmt-btn{display:flex;align-items:center;gap:.45rem;padding:.5rem .55rem;border:2px solid var(--g);border-radius:var(--r2);cursor:pointer;transition:all .15s;background:#fff;text-align:center;}
.fmt-btn:hover{border-color:var(--p);background:#f8f7ff;}
.fmt-btn.on{border-color:var(--p);background:var(--pL);}
.fmt-ico{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.fmt-label{font-size:.8rem;font-weight:700;color:var(--gt);}
.fmt-sub{font-size:.66rem;color:var(--gm);}
.preset-btn{display:flex;align-items:center;justify-content:center;gap:.35rem;padding:.38rem .4rem;border:1.5px solid #e2e8f0;border-radius:8px;cursor:pointer;background:#f8fafc;font-size:.75rem;font-weight:600;color:#374151;transition:all .15s;}
.preset-btn:hover{border-color:var(--p);background:var(--pL);color:var(--p);}
.gen-progress-wrap{background:#e5e7eb;border-radius:6px;height:8px;overflow:hidden;margin:.5rem 0;}
.gen-progress-bar{height:100%;background:linear-gradient(90deg,var(--p),var(--v));border-radius:6px;transition:width .25s;}
.sheet-thumb{max-width:100%;border-radius:8px;border:1px solid var(--g);box-shadow:0 2px 8px rgba(0,0,0,.08);}

/* ── Quick Print banner ── */
.qprint-banner{display:flex;align-items:center;gap:.75rem;background:linear-gradient(120deg,#1e3a8a 0%,#4f46e5 100%);border-radius:var(--r);padding:.7rem 1rem;margin-bottom:.85rem;cursor:pointer;transition:opacity .14s;box-shadow:0 4px 14px rgba(79,70,229,.22);}
.qprint-banner:hover{opacity:.93;}
.qprint-banner-ico{width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff;flex-shrink:0;}
.qprint-banner h6{font-size:.88rem;font-weight:800;color:#fff;margin:0 0 .1rem;}
.qprint-banner p{font-size:.71rem;color:rgba(255,255,255,.75);margin:0;}
.qprint-banner-arrow{margin-left:auto;width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;flex-shrink:0;}

/* ── Print Tool Overlay ── */
#printToolOv{position:fixed;inset:0;z-index:9800;background:rgba(15,23,42,.7);display:none;align-items:flex-start;justify-content:center;padding:1rem;overflow-y:auto;}
.pt-modal{background:#fff;border-radius:16px;max-width:900px;width:100%;margin:auto;box-shadow:0 24px 80px rgba(0,0,0,.28);}
.pt-hd{display:flex;align-items:center;gap:.65rem;padding:.85rem 1rem;border-bottom:2px solid #f1f5f9;}
.pt-hd h5{font-size:1rem;font-weight:800;color:#1f2937;margin:0;flex:1;}
.pt-hd-close{width:32px;height:32px;border:none;background:#f1f5f9;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#64748b;transition:all .12s;}
.pt-hd-close:hover{background:#fee2e2;color:#dc2626;}
.pt-body{display:grid;grid-template-columns:1fr 1fr;gap:1rem;padding:.9rem;}
@media(max-width:640px){.pt-body{grid-template-columns:1fr}}
.pt-sec{background:#f8fafc;border-radius:10px;padding:.7rem .75rem;}
.pt-sh{font-size:.6rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.45rem;display:flex;align-items:center;gap:.22rem;}
.pt-sh i{color:#6366f1;font-size:.7rem;}
.pt-dz{border:2px dashed #d1d5db;border-radius:10px;padding:1.2rem 1rem;text-align:center;cursor:pointer;transition:all .16s;position:relative;background:#fff;}
.pt-dz:hover,.pt-dz.drag{border-color:#6366f1;background:#f5f3ff;}
.pt-dz.filled{border-color:#10b981;border-style:solid;background:#f0fdf4;}
.pt-dz input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.pt-szpill{border:1.5px solid #e2e8f0;border-radius:7px;padding:.3rem .35rem;text-align:center;cursor:pointer;font-size:.67rem;font-weight:700;color:#374151;background:#fff;transition:all .13s;}
.pt-szpill:hover{border-color:#6366f1;color:#6366f1;}
.pt-szpill.on{border-color:#6366f1;color:#6366f1;background:#ede9fe;}
.pt-szpill small{display:block;font-size:.56rem;color:#94a3b8;font-weight:400;}
.pt-inp{width:100%;border:1.5px solid #e2e8f0;border-radius:7px;padding:.28rem .42rem;font-size:.77rem;outline:none;background:#fff;color:#1f2937;transition:border-color .12s;}
.pt-inp:focus{border-color:#6366f1;}
.pt-lbl{font-size:.67rem;font-weight:600;color:#374151;display:block;margin-bottom:.18rem;}
.pt-prev{background:#e2e8f0;border-radius:8px;overflow:hidden;min-height:160px;display:flex;align-items:center;justify-content:center;}
.pt-footer{display:flex;gap:.55rem;align-items:center;padding:.8rem 1rem;border-top:2px solid #f1f5f9;flex-wrap:wrap;}
.pt-print-btn{display:flex;align-items:center;gap:.4rem;padding:.48rem 1.1rem;border-radius:9px;border:none;background:linear-gradient(120deg,#1e3a8a,#4f46e5);color:#fff;font-size:.84rem;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(79,70,229,.3);transition:all .14s;}
.pt-print-btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,70,229,.4);}
@media print{body>*{display:none!important;}#ptPrintFrame{display:block!important;position:fixed;inset:0;}}

/* ── Toast ── */
#smToast{position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.4rem;}
.toast-item{display:flex;align-items:center;gap:.5rem;padding:.55rem 1rem;border-radius:10px;font-size:.8rem;font-weight:700;color:#fff;box-shadow:0 4px 16px rgba(0,0,0,.18);animation:toastIn .2s ease;max-width:320px;}
@keyframes toastIn{from{opacity:0;transform:translateX(24px)}to{opacity:1;transform:none}}
.toast-ok{background:#059669;} .toast-err{background:#dc2626;} .toast-info{background:var(--p);}

/* ── Misc ── */
.glbl{font-size:.75rem;font-weight:600;color:#374151;display:block;margin-bottom:.25rem;}
.ginp,.gsel{width:100%;border:1.5px solid #d1d5db;border-radius:var(--r2);padding:.42rem .65rem;font-size:.82rem;color:var(--gt);outline:none;background:#fff;}
.ginp:focus,.gsel:focus{border-color:var(--p);}
.szpill{border:2px solid var(--g);border-radius:9px;padding:.45rem .5rem;text-align:center;cursor:pointer;font-size:.72rem;font-weight:700;color:var(--gm);background:#fff;transition:all .14s;}
.szpill:hover{border-color:#a5b4fc;color:var(--p);}
.szpill.on{border-color:var(--p);color:var(--p);background:var(--pL);}
.szpill small{display:block;font-size:.58rem;font-weight:400;color:var(--gm);}
.hidden{display:none!important;}
.sec-title{font-size:.72rem;font-weight:800;color:var(--gt);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem;display:flex;align-items:center;gap:.35rem;}
.sec-title i{color:var(--p);}
.info-chip{display:inline-flex;align-items:center;gap:.3rem;padding:.22rem .55rem;background:var(--pL);color:var(--p);border-radius:10px;font-size:.68rem;font-weight:700;}
</style>

<!-- ── Quick Print Banner ── -->
<div class="qprint-banner" onclick="openPrintTool()">
  <div class="qprint-banner-ico"><i class="bi bi-printer-fill"></i></div>
  <div>
    <h6>Print Card Template</h6>
    <p>Upload your card image &amp; print at exact size — no software needed</p>
  </div>
  <div class="qprint-banner-arrow"><i class="bi bi-arrow-right"></i></div>
</div>

<!-- ── Print Tool Overlay ── -->
<div id="printToolOv" onclick="if(event.target===this)closePrintTool()">
  <div class="pt-modal">
    <!-- Header -->
    <div class="pt-hd">
      <div style="width:34px;height:34px;border-radius:9px;background:#ede9fe;display:flex;align-items:center;justify-content:center;font-size:1rem;color:#6366f1;flex-shrink:0;"><i class="bi bi-printer-fill"></i></div>
      <h5>Print Card Template</h5>
      <button class="pt-hd-close" onclick="closePrintTool()" title="Close"><i class="bi bi-x-lg"></i></button>
    </div>

    <!-- Body -->
    <div class="pt-body">
      <!-- Left col -->
      <div style="display:flex;flex-direction:column;gap:.7rem;">

        <!-- Upload -->
        <div class="pt-sec">
          <div class="pt-sh"><i class="bi bi-image"></i> Card Template Image</div>
          <div class="pt-dz" id="ptDZ" ondragover="ptDzOver(event)" ondragleave="ptDzLeave()" ondrop="ptDzDrop(event)">
            <input type="file" accept="image/*" id="ptImgInp" onchange="ptLoadImg(this)">
            <i class="bi bi-card-image" style="font-size:1.8rem;color:#a5b4fc;display:block;margin-bottom:.3rem;" id="ptDzIco"></i>
            <div style="font-size:.76rem;font-weight:700;color:#374151;" id="ptDzTitle">Drop card image here or click to browse</div>
            <div style="font-size:.66rem;color:#94a3b8;margin-top:.15rem;" id="ptDzSub">JPG · PNG · WEBP · any image</div>
          </div>
          <!-- Image thumb -->
          <div id="ptThumbWrap" style="display:none;margin-top:.5rem;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;text-align:center;background:#f8fafc;">
            <img id="ptThumb" style="max-width:100%;max-height:110px;object-fit:contain;display:block;margin:0 auto;">
          </div>
          <div style="margin-top:.45rem;display:flex;align-items:center;gap:.5rem;">
            <span style="font-size:.7rem;color:#94a3b8;font-weight:600;">or</span>
            <button onclick="openSgGalPicker()" style="display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .8rem;border-radius:7px;border:1.5px solid #e0e7ff;background:#f5f3ff;color:#4f46e5;font-size:.74rem;font-weight:700;cursor:pointer;transition:background .14s;" onmouseenter="this.style.background='#ede9fe'" onmouseleave="this.style.background='#f5f3ff'">
              <i class="bi bi-collection-fill"></i>From My Gallery
            </button>
          </div>
        </div>

        <!-- Card Size -->
        <div class="pt-sec">
          <div class="pt-sh"><i class="bi bi-rulers"></i> Card Size</div>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.28rem;margin-bottom:.5rem;" id="ptSzGrid">
            <div class="pt-szpill on" onclick="ptSetSize(85.6,54,this)">CR80<small>85.6×54mm</small></div>
            <div class="pt-szpill" onclick="ptSetSize(74,105,this)">A7<small>74×105mm</small></div>
            <div class="pt-szpill" onclick="ptSetSize(52,74,this)">A8<small>52×74mm</small></div>
            <div class="pt-szpill" onclick="ptSetSize(90,60,this)">90×60<small>90×60mm</small></div>
            <div class="pt-szpill" onclick="ptSetSize(88,63,this)">88×63<small>88×63mm</small></div>
            <div class="pt-szpill" id="ptCustomPill" onclick="ptPickCustom(this)">Custom<small>set below</small></div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
            <div>
              <label class="pt-lbl">Width (mm)</label>
              <input type="number" class="pt-inp" id="ptCW" value="85.6" min="10" max="500" step="0.1" oninput="ptOnCustomSize()">
            </div>
            <div>
              <label class="pt-lbl">Height (mm)</label>
              <input type="number" class="pt-inp" id="ptCH" value="54" min="10" max="500" step="0.1" oninput="ptOnCustomSize()">
            </div>
          </div>
        </div>

        <!-- Paper & Layout -->
        <div class="pt-sec">
          <div class="pt-sh"><i class="bi bi-file-earmark"></i> Paper &amp; Layout</div>
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.25rem;margin-bottom:.5rem;">
            <div class="pt-szpill on" data-pt-paper onclick="ptSetPaper('A4',this)">A4<small>210×297</small></div>
            <div class="pt-szpill" data-pt-paper onclick="ptSetPaper('A3',this)">A3<small>297×420</small></div>
            <div class="pt-szpill" data-pt-paper onclick="ptSetPaper('A5',this)">A5<small>148×210</small></div>
            <div class="pt-szpill" data-pt-paper onclick="ptSetPaper('Letter',this)">Letter<small>216×279</small></div>
          </div>
          <div style="display:flex;gap:.28rem;margin-bottom:.5rem;">
            <div class="pt-szpill on" id="ptOrLand" style="flex:1;" onclick="ptSetOrient('landscape')"><i class="bi bi-phone-landscape"></i> Landscape</div>
            <div class="pt-szpill" id="ptOrPort" style="flex:1;" onclick="ptSetOrient('portrait')"><i class="bi bi-phone"></i> Portrait</div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
            <div>
              <label class="pt-lbl">Page Margin (mm)</label>
              <input type="number" class="pt-inp" id="ptMargin" value="8" min="0" max="50" step="1" oninput="ptUpdatePreview()">
            </div>
            <div>
              <label class="pt-lbl">Gap Between (mm)</label>
              <input type="number" class="pt-inp" id="ptGap" value="3" min="0" max="30" step="1" oninput="ptUpdatePreview()">
            </div>
          </div>
        </div>
      </div>

      <!-- Right col: Preview -->
      <div style="display:flex;flex-direction:column;gap:.7rem;">
        <div class="pt-sec" style="flex:1;">
          <div class="pt-sh"><i class="bi bi-grid-3x3-gap"></i> Print Layout Preview
            <span id="ptCardsInfo" style="margin-left:auto;font-size:.64rem;font-weight:700;color:#6366f1;"></span>
          </div>
          <div class="pt-prev" id="ptPrevBox">
            <canvas id="ptPreviewCanvas" style="display:block;max-width:100%;max-height:400px;border-radius:6px;"></canvas>
          </div>
          <div style="margin-top:.45rem;font-size:.67rem;color:#94a3b8;text-align:center;" id="ptPrevHint">Upload image and configure settings to see layout</div>
        </div>
        <!-- Copies control -->
        <div class="pt-sec">
          <div class="pt-sh"><i class="bi bi-copy"></i> Copies Per Page</div>
          <div style="display:flex;align-items:center;gap:.5rem;">
            <input type="range" id="ptCopies" min="1" max="50" value="0" step="1" style="flex:1;accent-color:#6366f1;" oninput="ptOnCopiesChange(this.value)">
            <span id="ptCopiesVal" style="font-size:.78rem;font-weight:800;color:#6366f1;min-width:60px;text-align:right;">Auto-fill</span>
          </div>
          <div style="font-size:.63rem;color:#94a3b8;margin-top:.18rem;">0 = auto-fill page · or set exact number of cards per sheet</div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="pt-footer">
      <div style="font-size:.72rem;color:#64748b;flex:1;">
        <i class="bi bi-info-circle" style="color:#6366f1;"></i>
        Cards will print at exact physical size. Make sure printer scale is set to <strong>100%</strong>.
      </div>
      <button class="pt-print-btn" onclick="ptDoPrint()">
        <i class="bi bi-printer-fill"></i> Print Now
      </button>
      <button onclick="closePrintTool()" style="padding:.46rem .9rem;border:1.5px solid #e2e8f0;border-radius:9px;background:#fff;font-size:.8rem;font-weight:700;cursor:pointer;color:#64748b;">Cancel</button>
    </div>
  </div>
</div>

<!-- ── Wizard progress bar ── -->
<div class="sw-bar">
  <div class="sw-item active" id="swi1">
    <div class="sw-num" id="swn1">1</div>
    <span class="sw-lbl">Data Files</span>
    <div class="sw-line"></div>
  </div>
  <div class="sw-item" id="swi2">
    <div class="sw-num" id="swn2">2</div>
    <span class="sw-lbl">Field Setup</span>
    <div class="sw-line"></div>
  </div>
  <div class="sw-item" id="swi3">
    <div class="sw-num" id="swn3">3</div>
    <span class="sw-lbl">Card Size</span>
    <div class="sw-line"></div>
  </div>
  <div class="sw-item" id="swi4">
    <div class="sw-num" id="swn4">4</div>
    <span class="sw-lbl">Designer</span>
    <div class="sw-line"></div>
  </div>
  <div class="sw-item" id="swi5">
    <div class="sw-num" id="swn5">5</div>
    <span class="sw-lbl">Generate</span>
  </div>
</div>

<!-- ════════════ STEP 1: DATA FILES ════════════ -->
<div class="sw-step active" id="step1">
  <div class="row g-3">
    <div class="col-lg-8">
      <!-- Excel -->
      <div class="sc">
        <div class="sc-h"><i class="bi bi-table"></i> Excel / CSV Data File</div>
        <div class="sc-b">
          <div class="drop-zone" id="dzExcel" ondragover="dzOver(event,'dzExcel')" ondragleave="dzLeave('dzExcel')" ondrop="dzDrop(event,'excel')">
            <input type="file" accept=".xlsx,.xls,.csv" id="inpExcel" onchange="loadExcel(this.files[0])">
            <i class="bi bi-file-earmark-spreadsheet dz-ico"></i>
            <div class="dz-title">Drop Excel / CSV here or click to browse</div>
            <div class="dz-sub">Supports .xlsx · .xls · .csv — reads first sheet</div>
            <div class="dz-ok" id="excelOk"></div>
          </div>
          <div id="excelPreviewArea" class="hidden">
            <div style="display:flex;align-items:center;gap:.5rem;margin-top:.55rem;">
              <span class="info-chip" id="excelInfoChip"></span>
            </div>
            <div class="xprev-wrap"><table class="xprev-tbl" id="excelPreviewTbl"></table></div>
          </div>
        </div>
      </div>
      <!-- Photos -->
      <div class="sc">
        <div class="sc-h"><i class="bi bi-images"></i> Student Photos <span style="font-size:.68rem;font-weight:400;color:var(--gm);margin-left:auto;">Filename must match Excel column value</span></div>
        <div class="sc-b">
          <div class="drop-zone" id="dzPhotos" ondragover="dzOver(event,'dzPhotos')" ondragleave="dzLeave('dzPhotos')" ondrop="dzDrop(event,'photos')">
            <input type="file" accept="image/*" multiple id="inpPhotos" onchange="loadPhotos(this.files)">
            <i class="bi bi-person-bounding-box dz-ico"></i>
            <div class="dz-title">Drop student photos here or click to browse</div>
            <div class="dz-sub">Select multiple images at once · JPG · PNG · WEBP</div>
            <div class="dz-ok" id="photosOk"></div>
          </div>
          <div id="photoStrip" class="photo-strip hidden"></div>
        </div>
      </div>
      <!-- Signatures -->
      <div class="sc">
        <div class="sc-h"><i class="bi bi-pen"></i> Signatures <span style="font-size:.68rem;font-weight:400;color:var(--gm);margin-left:auto;">Optional</span></div>
        <div class="sc-b">
          <div class="drop-zone" id="dzSigns" ondragover="dzOver(event,'dzSigns')" ondragleave="dzLeave('dzSigns')" ondrop="dzDrop(event,'signs')">
            <input type="file" accept="image/*" multiple id="inpSigns" onchange="loadSigns(this.files)">
            <i class="bi bi-pen dz-ico"></i>
            <div class="dz-title">Drop signature images here (optional)</div>
            <div class="dz-sub">One per student or a single common signature</div>
            <div class="dz-ok" id="signsOk"></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <!-- Checklist -->
      <div class="sc">
        <div class="sc-h"><i class="bi bi-check2-all"></i> Upload Status</div>
        <div class="sc-b" id="step1Status">
          <div style="color:var(--gm);font-size:.8rem;text-align:center;padding:.5rem 0;">
            <i class="bi bi-cloud-upload" style="font-size:2rem;display:block;opacity:.25;margin-bottom:.4rem;"></i>
            Upload your files to begin
          </div>
        </div>
      </div>
      <div class="sc">
        <div class="sc-h"><i class="bi bi-info-circle"></i> Tips</div>
        <div class="sc-b" style="font-size:.75rem;color:var(--gm);line-height:1.7;">
          <div style="margin-bottom:.4rem;"><i class="bi bi-check-circle text-success"></i> Excel column headers become field names in designer</div>
          <div style="margin-bottom:.4rem;"><i class="bi bi-check-circle text-success"></i> Name photos as student ID or name to auto-match</div>
          <div><i class="bi bi-check-circle text-success"></i> Signature is optional — use one common sign if needed</div>
        </div>
      </div>
    </div>
  </div>
  <div class="d-flex gap-2 mt-3">
    <button class="btn btn-v" onclick="step1Next()">Next: Field Setup <i class="bi bi-arrow-right"></i></button>
  </div>
</div>

<!-- ════════════ STEP 2: FIELD SETUP ════════════ -->
<div class="sw-step" id="step2">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="sc">
        <div class="sc-h"><i class="bi bi-columns-gap"></i> Assign Column Roles
          <span style="font-size:.68rem;font-weight:400;color:var(--gm);margin-left:auto;">Click roles to assign · QR = multiple OK · Barcode = one only</span>
        </div>
        <div class="sc-b">
          <div id="autoDetectNotice" style="display:none;background:#f0fdf4;border:1px solid #86efac;border-radius:7px;padding:.4rem .65rem;font-size:.74rem;color:#166534;font-weight:600;margin-bottom:.55rem;display:flex;align-items:center;gap:.4rem;">
            <i class="bi bi-magic"></i>
            <span id="autoDetectText"></span>
          </div>
          <div class="col-grid" id="colGrid"></div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <!-- QR config -->
      <div class="sc">
        <div class="sc-h"><i class="bi bi-qr-code"></i> QR Code Preview</div>
        <div class="sc-b">
          <div class="code-prev-box" id="qrPrevBox">
            <i class="bi bi-qr-code" style="font-size:2rem;color:#d1d5db;"></i>
            <span style="font-size:.7rem;color:var(--gm);">Assign QR fields to see preview</span>
          </div>
          <div style="margin-top:.55rem;">
            <label class="glbl">QR Error Correction</label>
            <select class="gsel" id="qrLevel" style="font-size:.78rem;">
              <option value="M">M — Medium (15% recovery)</option>
              <option value="L">L — Low (7% recovery)</option>
              <option value="Q">Q — High (25% recovery)</option>
              <option value="H">H — Max (30% recovery)</option>
            </select>
          </div>
        </div>
      </div>
      <!-- Barcode config -->
      <div class="sc">
        <div class="sc-h"><i class="bi bi-upc"></i> Barcode Preview</div>
        <div class="sc-b">
          <div class="code-prev-box" id="barPrevBox">
            <canvas id="barcodePreviewCanvas"></canvas>
            <span id="barPrevNone" style="font-size:.7rem;color:var(--gm);">Assign Barcode field to see preview</span>
          </div>
          <div style="margin-top:.55rem;">
            <label class="glbl">Barcode Format</label>
            <select class="gsel" id="barcodeType" style="font-size:.78rem;" onchange="refreshBarPreview()">
              <option value="CODE128">CODE128 (alphanumeric)</option>
              <option value="EAN13">EAN-13 (13 digits)</option>
              <option value="EAN8">EAN-8 (8 digits)</option>
              <option value="CODE39">CODE39</option>
              <option value="ITF14">ITF-14 (14 digits)</option>
            </select>
          </div>
        </div>
      </div>
      <!-- Advanced -->
      <div class="sc">
        <div class="sc-h"><i class="bi bi-gear"></i> Advanced Options</div>
        <div class="sc-b">
          <div style="margin-bottom:.55rem;">
            <label class="glbl">Watermark Text (optional)</label>
            <input class="ginp" id="watermarkText" placeholder="e.g. CONFIDENTIAL" style="font-size:.78rem;">
          </div>
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.78rem;font-weight:600;color:var(--gt);">
            <input type="checkbox" id="serialEnabled"> Auto Serial Number column
          </label>
        </div>
      </div>
    </div>
  </div>
  <div class="d-flex gap-2 mt-3">
    <button class="btn btn-s" onclick="goStep(1)"><i class="bi bi-arrow-left"></i> Back</button>
    <button class="btn btn-v" onclick="step2Next()">Next: Card Size <i class="bi bi-arrow-right"></i></button>
  </div>
</div>

<!-- ════════════ STEP 3: CARD SIZE ════════════ -->
<div class="sw-step" id="step3">
  <div class="row g-3">
    <div class="col-lg-7">
      <!-- Template upload -->
      <div class="sc">
        <div class="sc-h"><i class="bi bi-image"></i> Card Template Image
          <span id="s3SideBadge" style="display:none;margin-left:.5rem;background:#eff6ff;color:#2563eb;border:1.5px solid #bfdbfe;border-radius:20px;padding:.08rem .45rem;font-size:.62rem;font-weight:800;letter-spacing:.05em;">FRONT</span>
          <span style="font-size:.68rem;font-weight:400;color:var(--gm);margin-left:auto;">Upload your card design — size auto-detected</span>
        </div>
        <div class="sc-b">
          <div class="drop-zone" id="dzTemplate" ondragover="dzOver(event,'dzTemplate')" ondragleave="dzLeave('dzTemplate')" ondrop="dzDrop(event,'template')">
            <input type="file" accept="image/*" id="inpTemplate" onchange="loadTemplate(this.files[0])">
            <i class="bi bi-image dz-ico" id="tplDzIco"></i>
            <div class="dz-title" id="tplDzTitle">Drop card template image here</div>
            <div class="dz-sub">JPG · PNG · WEBP · Size is auto-detected from pixels</div>
            <div class="dz-ok" id="templateOk"></div>
          </div>
          <div id="tplPreviewArea" class="hidden" style="margin-top:.7rem;display:flex;gap:.75rem;align-items:flex-start;flex-wrap:wrap;">
            <img id="tplThumb" style="max-height:100px;border-radius:8px;border:1px solid var(--g);" alt="">
            <div style="flex:1;">
              <div id="tplDetectInfo" style="font-size:.78rem;color:var(--gm);line-height:1.8;"></div>
              <button onclick="clearTemplate()" style="margin-top:.35rem;font-size:.68rem;padding:.2rem .5rem;border:1px solid #fca5a5;background:#fef2f2;color:#dc2626;border-radius:5px;cursor:pointer;">
                <i class="bi bi-x"></i> Remove template
              </button>
            </div>
          </div>
          <div style="margin-top:.4rem;text-align:center;">
            <button onclick="openSgGalPickerForTpl()" style="display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .8rem;border-radius:7px;border:1.5px solid #e0e7ff;background:#f5f3ff;color:#4f46e5;font-size:.74rem;font-weight:700;cursor:pointer;transition:background .14s;" onmouseenter="this.style.background='#ede9fe'" onmouseleave="this.style.background='#f5f3ff'">
              <i class="bi bi-collection-fill"></i>From My Gallery
            </button>
          </div>
          <div style="text-align:center;margin-top:.5rem;" id="skipTplRow">
            <span style="font-size:.72rem;color:#94a3b8;">No template? </span>
            <a href="#" onclick="skipTemplate(event)" style="font-size:.72rem;color:#6366f1;text-decoration:none;font-weight:600;">
              Use plain background color instead <i class="bi bi-arrow-right-short"></i>
            </a>
          </div>
        </div>
      </div>
      <!-- Size presets -->
      <div class="sc">
        <div class="sc-h"><i class="bi bi-rulers"></i> Card Size
          <span id="selectedSizeChip" class="info-chip" style="margin-left:auto;display:none;"></span>
        </div>
        <div class="sc-b">
          <div class="sz-grid" id="szGrid">

            <!-- ── Small Cards ── -->
            <div class="sz-cat">School &amp; ID Cards</div>
            <div class="sz-card sz-star on" data-w="2.125" data-h="3.375" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:17px;height:27px;"></div></div>
              <div class="sz-name">School ID</div><div class="sz-dim">5.4×8.6 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>
            <div class="sz-card" data-w="3.375" data-h="2.125" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:27px;height:17px;"></div></div>
              <div class="sz-name">School ID</div><div class="sz-dim">8.6×5.4 cm</div><div class="sz-orient">↔ Landscape</div>
            </div>
            <div class="sz-card" data-w="3.5" data-h="2.0" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:28px;height:16px;"></div></div>
              <div class="sz-name">Biz Card</div><div class="sz-dim">8.9×5.1 cm</div><div class="sz-orient">↔ Landscape</div>
            </div>
            <div class="sz-card" data-w="2.0" data-h="3.5" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:16px;height:28px;"></div></div>
              <div class="sz-name">Biz Card</div><div class="sz-dim">5.1×8.9 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>
            <div class="sz-card" data-w="1.34" data-h="1.77" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:18px;height:24px;"></div></div>
              <div class="sz-name">Passport</div><div class="sz-dim">3.4×4.5 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>
            <div class="sz-card" data-w="1.97" data-h="2.76" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:17px;height:24px;"></div></div>
              <div class="sz-name">Passport</div><div class="sz-dim">5.0×7.0 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>

            <!-- ── Half Page ── -->
            <div class="sz-cat">Half Page — Fee / Admit Cards (2 per A4)</div>
            <div class="sz-card sz-star" data-w="4.135" data-h="5.845" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:22px;height:31px;"></div></div>
              <div class="sz-name">Fee Card</div><div class="sz-dim">10.5×14.9 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>
            <div class="sz-card" data-w="5.845" data-h="4.135" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:31px;height:22px;"></div></div>
              <div class="sz-name">Fee Card</div><div class="sz-dim">14.9×10.5 cm</div><div class="sz-orient">↔ Landscape</div>
            </div>
            <div class="sz-card sz-star" data-w="8.27" data-h="5.845" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:34px;height:24px;"></div></div>
              <div class="sz-name">Admit Card</div><div class="sz-dim">21×14.9 cm</div><div class="sz-orient">↔ Landscape</div>
            </div>
            <div class="sz-card" data-w="5.845" data-h="8.27" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:24px;height:34px;"></div></div>
              <div class="sz-name">Half A4</div><div class="sz-dim">14.9×21 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>
            <div class="sz-card" data-w="5.5" data-h="8.5" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:22px;height:34px;"></div></div>
              <div class="sz-name">Half Letter</div><div class="sz-dim">14.0×21.6 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>
            <div class="sz-card" data-w="8.5" data-h="5.5" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:34px;height:22px;"></div></div>
              <div class="sz-name">Half Letter</div><div class="sz-dim">21.6×14.0 cm</div><div class="sz-orient">↔ Landscape</div>
            </div>
            <div class="sz-card" data-w="4.25" data-h="5.5" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:24px;height:31px;"></div></div>
              <div class="sz-name">Qtr Letter</div><div class="sz-dim">10.8×14.0 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>
            <div class="sz-card" data-w="5.5" data-h="4.25" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:31px;height:24px;"></div></div>
              <div class="sz-name">Qtr Letter</div><div class="sz-dim">14.0×10.8 cm</div><div class="sz-orient">↔ Landscape</div>
            </div>

            <!-- ── Full Page ── -->
            <div class="sz-cat">Full Page — Mark Sheet, Result, Certificate</div>
            <div class="sz-card sz-star" data-w="8.27" data-h="11.69" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:22px;height:31px;"></div></div>
              <div class="sz-name">A4</div><div class="sz-dim">21×29.7 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>
            <div class="sz-card" data-w="11.69" data-h="8.27" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:31px;height:22px;"></div></div>
              <div class="sz-name">A4</div><div class="sz-dim">29.7×21 cm</div><div class="sz-orient">↔ Landscape</div>
            </div>
            <div class="sz-card" data-w="5.83" data-h="8.27" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:22px;height:31px;"></div></div>
              <div class="sz-name">A5</div><div class="sz-dim">14.8×21 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>
            <div class="sz-card" data-w="8.27" data-h="5.83" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:31px;height:22px;"></div></div>
              <div class="sz-name">A5</div><div class="sz-dim">21×14.8 cm</div><div class="sz-orient">↔ Landscape</div>
            </div>
            <div class="sz-card" data-w="8.5" data-h="11" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:23px;height:30px;"></div></div>
              <div class="sz-name">Letter</div><div class="sz-dim">21.6×27.9 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>
            <div class="sz-card" data-w="11" data-h="8.5" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:30px;height:23px;"></div></div>
              <div class="sz-name">Letter</div><div class="sz-dim">27.9×21.6 cm</div><div class="sz-orient">↔ Landscape</div>
            </div>
            <div class="sz-card" data-w="11.69" data-h="16.54" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:22px;height:31px;"></div></div>
              <div class="sz-name">A3</div><div class="sz-dim">29.7×42 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>
            <div class="sz-card" data-w="16.54" data-h="11.69" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:31px;height:22px;"></div></div>
              <div class="sz-name">A3</div><div class="sz-dim">42×29.7 cm</div><div class="sz-orient">↔ Landscape</div>
            </div>
            <div class="sz-card" data-w="2.913" data-h="4.134" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:22px;height:31px;"></div></div>
              <div class="sz-name">A7</div><div class="sz-dim">7.4×10.5 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>
            <div class="sz-card" data-w="4.134" data-h="5.826" onclick="selectSize(this)">
              <div class="sz-thumb"><div class="sz-thumb-inner" style="width:22px;height:31px;"></div></div>
              <div class="sz-name">A6</div><div class="sz-dim">10.5×14.8 cm</div><div class="sz-orient">↕ Portrait</div>
            </div>

            <!-- ── Custom ── -->
            <div class="sz-cat">Custom Size</div>
            <div class="sz-card" data-w="0" data-h="0" onclick="selectSize(this)">
              <div class="sz-thumb" style="font-size:1.5rem;line-height:1;">✏️</div>
              <div class="sz-name">Custom</div><div class="sz-dim">Set your own</div><div class="sz-orient">&nbsp;</div>
            </div>

          </div>
          <div id="customSzRow" class="sz-custom-row hidden">
            <div><label>Width (in)</label><input type="number" id="customW" value="3.375" step="0.01" min="0.5" max="48" oninput="updateCustomSzPreview()"></div>
            <div><label>Height (in)</label><input type="number" id="customH" value="2.125" step="0.01" min="0.5" max="48" oninput="updateCustomSzPreview()"></div>
            <div style="align-self:flex-end;"><button class="btn btn-s btn-sm" onclick="applyCustomSize()">Apply</button></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <!-- Quality -->
      <div class="sc">
        <div class="sc-h"><i class="bi bi-sliders"></i> Quality &amp; Output</div>
        <div class="sc-b">
          <label class="glbl">Print DPI</label>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;margin-bottom:.75rem;">
            <div class="szpill" data-dpi="150" onclick="setDPI(this)">150<small>Draft</small></div>
            <div class="szpill on" data-dpi="300" onclick="setDPI(this)">300<small>Print</small></div>
            <div class="szpill" data-dpi="600" onclick="setDPI(this)">600<small>Pro</small></div>
          </div>
          <label class="glbl">Background Color</label>
          <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.75rem;">
            <input type="color" id="bgColor" value="#ffffff" style="width:34px;height:34px;border-radius:6px;border:2px solid var(--g);cursor:pointer;" oninput="document.getElementById('bgHex').value=this.value">
            <input class="ginp" id="bgHex" value="#ffffff" maxlength="7" style="font-size:.8rem;" oninput="syncBgHex()">
          </div>
          <label class="glbl">Card Sides</label>
          <div style="display:flex;gap:.5rem;">
            <div class="szpill on" id="sdSingle" style="flex:1;" onclick="setSides('single',this)">
              <i class="bi bi-file-earmark" style="display:block;font-size:1rem;margin-bottom:.2rem;"></i>Single
            </div>
            <div class="szpill" id="sdDouble" style="flex:1;" onclick="setSides('double',this)">
              <i class="bi bi-file-earmark-plus" style="display:block;font-size:1rem;margin-bottom:.2rem;"></i>Double-Sided
            </div>
          </div>
          <div id="s3SideTabs" style="display:none;margin-top:.55rem;background:#f1f5f9;border-radius:9px;padding:.3rem;grid-template-columns:1fr 1fr;gap:.3rem;">
            <button id="s3TabFront" onclick="switchCardSide3('front')" style="border:none;border-radius:7px;padding:.45rem .4rem;font-size:.76rem;font-weight:700;cursor:pointer;background:#2563eb;color:#fff;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:.3rem;">
              <i class="bi bi-card-image"></i> Front
            </button>
            <button id="s3TabBack" onclick="switchCardSide3('back')" style="border:none;border-radius:7px;padding:.45rem .4rem;font-size:.76rem;font-weight:700;cursor:pointer;background:transparent;color:#64748b;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:.3rem;">
              <i class="bi bi-card-image"></i> Back
            </button>
          </div>
        </div>
      </div>
      <!-- Size preview -->
      <div class="sc">
        <div class="sc-h"><i class="bi bi-aspect-ratio"></i> Size Preview</div>
        <div class="sc-b" style="text-align:center;">
          <div id="szPreviewWrap">
            <div id="szPreviewBox">
              <div id="pvClip">
                <img id="pvImg" draggable="false" alt="" style="display:none;">
              </div>
              <div class="pv-corner" data-dir="nw"></div>
              <div class="pv-corner" data-dir="ne"></div>
              <div class="pv-corner" data-dir="sw"></div>
              <div class="pv-corner" data-dir="se"></div>
            </div>
          </div>
          <!-- Preview controls -->
          <div style="display:flex;gap:.35rem;justify-content:center;margin-top:.55rem;">
            <button id="pvBtnFit"   onclick="setPvMode('fit',this)"   class="btn btn-s btn-sm" style="font-size:.65rem;padding:.2rem .55rem;">Fit</button>
            <button id="pvBtnCover" onclick="setPvMode('cover',this)" class="btn btn-s btn-sm" style="font-size:.65rem;padding:.2rem .55rem;opacity:.5;">Fill</button>
            <button id="pvBtnFull"  onclick="setPvMode('reset',this)" class="btn btn-s btn-sm" style="font-size:.65rem;padding:.2rem .55rem;opacity:.5;">Reset</button>
          </div>
          <div style="display:flex;align-items:center;gap:.5rem;margin-top:.4rem;">
            <span style="font-size:.62rem;color:var(--gm);white-space:nowrap;">Zoom</span>
            <input type="range" id="pvZoom" min="40" max="260" value="100" style="flex:1;cursor:pointer;" oninput="setPvZoom(this.value)">
            <span id="pvZoomPct" style="font-size:.62rem;color:var(--gm);min-width:2.2rem;text-align:right;">100%</span>
          </div>
          <div id="szPreviewInfo" style="font-size:.72rem;color:var(--gm);margin-top:.5rem;font-weight:600;line-height:1.7;"></div>
          <div id="szPreviewHint" style="font-size:.63rem;color:#94a3b8;margin-top:.25rem;"></div>
          <div id="dpiQualityBadge" style="margin-top:.4rem;display:none;"></div>
        </div>
      </div>
    </div>
  </div>
  <div class="d-flex gap-2 mt-3">
    <button class="btn btn-s" onclick="goStep(2)"><i class="bi bi-arrow-left"></i> Back</button>
    <button class="btn btn-v" onclick="step3Next()">Next: Designer <i class="bi bi-arrow-right"></i></button>
  </div>
</div>

<!-- ════════════ STEP 4: DESIGNER ════════════ -->
<div class="sw-step" id="step4" style="padding:0;margin:-1rem -1rem 0;">
  <div class="des-wrap">
    <!-- ── Toolbar ── -->
    <div class="des-toolbar">
      <!-- Back -->
      <button class="btn btn-s btn-sm" onclick="goStep(3)" title="Back to settings"><i class="bi bi-arrow-left"></i></button>
      <!-- Mobile/tablet only (see .des-mob-only CSS) — opens the Add
           Elements / Properties panels as bottom sheets instead of the
           desktop's always-visible side columns. -->
      <button class="des-mob-only des-mob-add" onclick="openDesPanel('left')"><i class="bi bi-plus-lg"></i> Add</button>
      <button class="des-mob-only" onclick="openDesPanel('right')"><i class="bi bi-sliders"></i> Properties</button>
      <!-- Side tabs (double-sided only) -->
      <div id="sideTabs" style="display:none;gap:.25rem;">
        <button id="tabFront" onclick="switchSide('front')" style="padding:.2rem .55rem;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;font-size:.68rem;font-weight:700;cursor:pointer;color:#6366f1;transition:all .14s;">
          <i class="bi bi-card-image"></i> Front
        </button>
        <button id="tabBack" onclick="switchSide('back')" style="padding:.2rem .55rem;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;font-size:.68rem;font-weight:700;cursor:pointer;color:#94a3b8;transition:all .14s;">
          <i class="bi bi-card-image"></i> Back
        </button>
      </div>
      <div class="tb-sep"></div>
      <!-- Card size -->
      <div class="des-szbar" title="Card dimensions">
        <span style="font-size:.64rem;font-weight:700;color:#6366f1;">W</span>
        <input type="number" id="desW" step="0.01" min="0.5" max="48" onchange="resizeFromUI()" title="Width in inches">
        <span>in</span>
        <span id="desWpx" style="color:#94a3b8;white-space:nowrap;font-size:.62rem;"></span>
        <span style="color:#d1d5db;margin:0 .12rem;">×</span>
        <span style="font-size:.64rem;font-weight:700;color:#6366f1;">H</span>
        <input type="number" id="desH" step="0.01" min="0.5" max="48" onchange="resizeFromUI()" title="Height in inches">
        <span>in</span>
        <span id="desHpx" style="color:#94a3b8;white-space:nowrap;font-size:.62rem;"></span>
      </div>
      <div class="tb-sep"></div>
      <!-- Row preview pill -->
      <div class="row-pill" title="Preview row data">
        <button class="row-pill-arrow" onclick="prevRow(-1)" title="Previous row"><i class="bi bi-chevron-left"></i></button>
        <span class="row-pill-label" id="prevRowLabel">Row 1</span>
        <button class="row-pill-arrow" onclick="prevRow(1)" title="Next row"><i class="bi bi-chevron-right"></i></button>
      </div>
      <!-- Live preview toggle -->
      <button class="live-btn" id="prevModeBtn" onclick="togglePreviewMode()" title="Toggle live data preview">
        <i class="bi bi-eye"></i> Preview
      </button>
      <div class="tb-sep"></div>
      <!-- Tool buttons -->
      <button class="btn-ico" onclick="toggleGrid()" title="Toggle snap grid" id="gridBtn"><i class="bi bi-grid-3x3"></i></button>
      <button class="btn-ico" onclick="undoC()" title="Undo (Ctrl+Z)"><i class="bi bi-arrow-counterclockwise"></i></button>
      <button class="btn-ico" onclick="redoC()" title="Redo (Ctrl+Y)"><i class="bi bi-arrow-clockwise"></i></button>
      <span style="flex:1;min-width:.25rem;"></span>
      <button class="btn btn-ok btn-sm" onclick="step4Next()" style="white-space:nowrap;">Generate <i class="bi bi-arrow-right"></i></button>
    </div>

    <!-- ── Body ── -->
    <div class="des-body">

      <!-- ── LEFT PANEL ── -->
      <div class="des-left" id="desLeftPanel">
        <div class="des-panel-mob-hd">
          <span>Add Elements</span>
          <button type="button" onclick="closeDesPanel('left')" title="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="des-panel-mob-body">
        <div class="des-section-lbl"><i class="bi bi-plus-circle" style="color:var(--p);"></i> Add Elements</div>
        <div class="add-el-grid">
          <!-- mobCloseLeftAfterAdd() added on top of each existing onclick —
               mobile/tablet only in effect — so tapping an element immediately
               reveals it on the canvas instead of leaving the sheet covering it. -->
          <button class="add-el-btn" onclick="addLabel();mobCloseLeftAfterAdd()">
            <span class="ael-ico" style="background:#ede9fe;color:#6d28d9;"><i class="bi bi-fonts"></i></span>
            <span class="ael-lbl">Label</span>
          </button>
          <button class="add-el-btn" onclick="addPhotoEl();mobCloseLeftAfterAdd()">
            <span class="ael-ico" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-person-bounding-box"></i></span>
            <span class="ael-lbl">Photo</span>
          </button>
          <button class="add-el-btn" onclick="addSignEl();mobCloseLeftAfterAdd()">
            <span class="ael-ico" style="background:#fce7f3;color:#be185d;"><i class="bi bi-pen"></i></span>
            <span class="ael-lbl">Sign</span>
          </button>
          <button class="add-el-btn" onclick="addBarcodeEl();mobCloseLeftAfterAdd()">
            <span class="ael-ico" style="background:#fef9c3;color:#b45309;"><i class="bi bi-upc"></i></span>
            <span class="ael-lbl">Barcode</span>
          </button>
          <button class="add-el-btn" onclick="addRect();mobCloseLeftAfterAdd()">
            <span class="ael-ico" style="background:#f1f5f9;color:#475569;"><i class="bi bi-square"></i></span>
            <span class="ael-lbl">Box</span>
          </button>
          <button class="add-el-btn" onclick="addLine();mobCloseLeftAfterAdd()">
            <span class="ael-ico" style="background:#f1f5f9;color:#475569;"><i class="bi bi-slash-lg"></i></span>
            <span class="ael-lbl">Line</span>
          </button>
          <button class="add-el-btn full" onclick="addQREl();mobCloseLeftAfterAdd()">
            <span class="ael-ico" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-qr-code"></i></span>
            <span class="ael-lbl">QR Code</span>
          </button>
        </div>

        <div class="des-section-lbl" style="margin-top:.3rem;"><i class="bi bi-table" style="color:#6366f1;"></i> Data Fields</div>
        <button onclick="addAllFields();mobCloseLeftAfterAdd()" style="width:100%;margin-bottom:.28rem;padding:.28rem .38rem;border:1.5px dashed #a5b4fc;background:#f5f3ff;border-radius:7px;cursor:pointer;font-size:.67rem;font-weight:700;color:#6366f1;display:flex;align-items:center;justify-content:center;gap:.3rem;transition:all .14s;" onmouseover="this.style.background='#ede9fe'" onmouseout="this.style.background='#f5f3ff'">
          <i class="bi bi-lightning-charge-fill"></i> Auto-place all fields
        </button>
        <div id="fieldsList"></div>
        </div><!-- /des-panel-mob-body -->
      </div>

      <!-- ── CENTER: Canvas ── -->
      <div class="des-center">
        <div id="canvasOuter">
          <div class="canvas-wrap" id="canvasWrap"
               ondragover="event.preventDefault();event.dataTransfer.dropEffect='copy';"
               ondrop="canvasDrop(event)">
            <canvas id="desCanvas"></canvas>
          </div>
        </div>
        <div style="font-size:.67rem;color:#94a3b8;margin-top:.5rem;text-align:center;" id="canvasSzLabel"></div>
      </div>

      <!-- ── RIGHT PANEL ── -->
      <div class="des-right" id="desRightPanel">
        <div class="des-panel-mob-hd">
          <span>Properties</span>
          <button type="button" onclick="closeDesPanel('right')" title="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="des-panel-mob-body">

        <!-- Empty state -->
        <div id="propEmpty" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem 1rem;color:#94a3b8;text-align:center;">
          <i class="bi bi-cursor" style="font-size:2rem;margin-bottom:.6rem;opacity:.3;"></i>
          <span style="font-size:.72rem;font-weight:600;color:#cbd5e1;">Select an element</span>
          <span style="font-size:.65rem;margin-top:.22rem;color:#d1d5db;">to edit its properties</span>
        </div>

        <!-- ── Text Properties ── -->
        <div id="propText" class="hidden">
          <div class="rp-sec">
            <div class="rp-hd"><i class="bi bi-fonts"></i> Typography</div>
            <div class="rp-row">
              <span class="rp-lbl">Font</span>
              <select class="rp-inp" id="pFont" onchange="ap('fontFamily',this.value)">
                <option>Arial</option><option>Times New Roman</option><option>Calibri</option>
                <option>Georgia</option><option>Verdana</option><option>Impact</option>
                <option>Courier New</option><option>Tahoma</option>
              </select>
            </div>
            <div class="rp-row">
              <span class="rp-lbl">Size</span>
              <input type="number" class="rp-inp" id="pSize" min="6" max="300" style="width:52px;flex:none;" onchange="ap('fontSize',+this.value)">
              <button class="rp-btn" id="pBold" onclick="toggleBold()" title="Bold" style="font-weight:800;">B</button>
              <button class="rp-btn" id="pItal" onclick="toggleItal()" title="Italic" style="font-style:italic;">I</button>
              <button class="rp-btn" id="pUndr" onclick="toggleUndr()" title="Underline" style="text-decoration:underline;">U</button>
            </div>
            <div class="rp-row">
              <span class="rp-lbl">Align</span>
              <button class="rp-btn" id="paL" onclick="ap('textAlign','left')" title="Left"><i class="bi bi-text-left"></i></button>
              <button class="rp-btn" id="paC" onclick="ap('textAlign','center')" title="Center"><i class="bi bi-text-center"></i></button>
              <button class="rp-btn" id="paR" onclick="ap('textAlign','right')" title="Right"><i class="bi bi-text-right"></i></button>
            </div>
            <div class="rp-row">
              <span class="rp-lbl">Color</span>
              <input type="color" class="rp-clr" id="pFill" oninput="ap('fill',this.value)">
              <input class="rp-inp" id="pFillH" maxlength="7" placeholder="#000000" oninput="syncFillHex()">
            </div>
            <div class="rp-row" id="staticTextRow">
              <span class="rp-lbl">Text</span>
              <input class="rp-inp" id="pText" placeholder="Static text…" onchange="ap('text',this.value)">
            </div>
          </div>
        </div>

        <!-- ── Image Size ── -->
        <div id="propImg" class="hidden">
          <div class="rp-sec">
            <div class="rp-hd"><i class="bi bi-aspect-ratio"></i> Size</div>
            <div class="rp-row">
              <span class="rp-lbl">W</span>
              <input type="number" class="rp-inp" id="pW" min="5" onchange="apSize()">
              <span style="font-size:.64rem;color:#94a3b8;flex-shrink:0;">px</span>
            </div>
            <div class="rp-row">
              <span class="rp-lbl">H</span>
              <input type="number" class="rp-inp" id="pH" min="5" onchange="apSize()">
              <span style="font-size:.64rem;color:#94a3b8;flex-shrink:0;">px</span>
            </div>
          </div>
        </div>

        <!-- ── Photo Shape ── -->
        <div id="propPhoto" class="hidden">
          <div class="rp-sec">
            <div class="rp-hd"><i class="bi bi-circle-half"></i> Photo Shape</div>
            <div class="psh-grid">
              <button class="psh-btn on" id="pshRect" onclick="setPhotoShape('rect')" title="Rectangle">
                <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="0" fill="none" stroke="currentColor" stroke-width="2"/></svg>
              </button>
              <button class="psh-btn" id="pshRound" onclick="setPhotoShape('round')" title="Rounded">
                <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="4" fill="none" stroke="currentColor" stroke-width="2"/></svg>
              </button>
              <button class="psh-btn" id="pshCircle" onclick="setPhotoShape('circle')" title="Circle">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/></svg>
              </button>
              <button class="psh-btn" id="pshEllipse" onclick="setPhotoShape('ellipse')" title="Ellipse">
                <svg viewBox="0 0 24 24"><ellipse cx="12" cy="12" rx="10" ry="7" fill="none" stroke="currentColor" stroke-width="2"/></svg>
              </button>
            </div>
            <div class="rp-row">
              <span class="rp-lbl">Border</span>
              <input type="number" class="rp-inp" id="pBorderW" min="0" max="30" value="0" style="width:44px;flex:none;" onchange="setPhotoBorder()">
              <span style="font-size:.64rem;color:#94a3b8;flex-shrink:0;">px</span>
              <input type="color" class="rp-clr" id="pBorderC" value="#000000" oninput="setPhotoBorder()">
            </div>
          </div>
        </div>

        <!-- ── Alignment ── -->
        <div id="propAlign" class="hidden">
          <div class="rp-sec">
            <div class="rp-hd"><i class="bi bi-distribute-horizontal"></i> Align to Card</div>
            <div class="aln-grid">
              <button class="aln-btn" onclick="alignObj('left')" title="Align Left"><i class="bi bi-align-start"></i></button>
              <button class="aln-btn" onclick="alignObj('centerH')" title="Center Horizontal"><i class="bi bi-align-center"></i></button>
              <button class="aln-btn" onclick="alignObj('right')" title="Align Right"><i class="bi bi-align-end"></i></button>
              <button class="aln-btn" onclick="alignObj('top')" title="Align Top"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="2" x2="12" y2="22"/><polyline points="6 8 12 2 18 8"/></svg></button>
              <button class="aln-btn" onclick="alignObj('centerV')" title="Center Vertical"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="2" y1="12" x2="22" y2="12"/><line x1="2" y1="6" x2="22" y2="6"/><line x1="2" y1="18" x2="22" y2="18"/></svg></button>
              <button class="aln-btn" onclick="alignObj('bottom')" title="Align Bottom"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="2" x2="12" y2="22"/><polyline points="6 16 12 22 18 16"/></svg></button>
            </div>
          </div>
        </div>

        <!-- ── Position & Transform ── -->
        <div id="propPos" class="hidden">
          <div class="rp-sec">
            <div class="rp-hd"><i class="bi bi-arrows-move"></i> Position</div>
            <div class="rp-row">
              <span class="rp-lbl">X</span>
              <input type="number" class="rp-inp" id="pX" onchange="apXY()">
              <span class="rp-lbl" style="min-width:14px;text-align:center;">Y</span>
              <input type="number" class="rp-inp" id="pY" onchange="apXY()">
            </div>
            <div class="rp-row">
              <span class="rp-lbl">Angle</span>
              <input type="range" class="rp-rng" id="pRot" min="-180" max="180" value="0" oninput="ap('angle',+this.value);document.getElementById('pRotV').textContent=this.value+'°'">
              <span class="rp-val" id="pRotV">0°</span>
            </div>
            <div class="rp-row">
              <span class="rp-lbl">Opacity</span>
              <input type="range" class="rp-rng" id="pOpa" min="0" max="100" value="100" oninput="ap('opacity',this.value/100);document.getElementById('pOpaV').textContent=this.value+'%'">
              <span class="rp-val" id="pOpaV">100%</span>
            </div>
          </div>
          <div class="rp-sec">
            <div class="rp-hd"><i class="bi bi-stack"></i> Layer Order</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.22rem;margin-bottom:.22rem;">
              <button class="rp-btn" style="padding:.26rem;" onclick="SA.canvas.bringForward(SA.canvas.getActiveObject());SA.canvas.renderAll();buildLayerList()"><i class="bi bi-arrow-up"></i> Fwd</button>
              <button class="rp-btn" style="padding:.26rem;" onclick="SA.canvas.sendBackwards(SA.canvas.getActiveObject());SA.canvas.renderAll();buildLayerList()"><i class="bi bi-arrow-down"></i> Back</button>
            </div>
            <button onclick="deleteSelected()" style="width:100%;padding:.3rem;border:1.5px solid #fca5a5;border-radius:6px;background:#fef2f2;color:#dc2626;cursor:pointer;font-size:.71rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:.3rem;"><i class="bi bi-trash"></i> Delete</button>
          </div>
        </div>

        <!-- ── Layers ── -->
        <div class="rp-sec">
          <div class="rp-hd"><i class="bi bi-layers"></i> Layers</div>
          <div id="layerList" style="max-height:190px;overflow-y:auto;"></div>
        </div>

        </div><!-- /des-panel-mob-body -->
      </div><!-- /des-right -->
    </div><!-- /des-body -->
  </div><!-- /des-wrap -->
  <!-- Mobile/tablet only (see #desSheetBackdrop CSS) — shared backdrop for
       whichever of Add Elements/Properties is open as a bottom sheet. -->
  <div id="desSheetBackdrop" onclick="closeDesPanel()"></div>
</div>

<!-- ════════════ STEP 5: GENERATE ════════════ -->
<div class="sw-step" id="step5">
  <div class="gen-layout">
    <!-- Left: settings -->
    <div>

      <!-- Quick Presets -->
      <div class="sc" style="margin-bottom:.6rem;">
        <div class="sc-h"><i class="bi bi-lightning-charge-fill" style="color:#f59e0b;"></i> Quick Presets</div>
        <div class="sc-b" style="padding:.5rem .6rem;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem;">
            <button class="preset-btn" onclick="applyGenPreset('auto')">
              <i class="bi bi-magic"></i><span>Auto Best</span>
            </button>
            <button class="preset-btn" onclick="applyGenPreset('10up')">
              <i class="bi bi-grid-3x3-gap"></i><span>10-up A4</span>
            </button>
            <button class="preset-btn" onclick="applyGenPreset('8up')">
              <i class="bi bi-grid"></i><span>8-up A4</span>
            </button>
            <button class="preset-btn" onclick="applyGenPreset('individual')">
              <i class="bi bi-person-badge"></i><span>Individual</span>
            </button>
          </div>
          <div id="autoLayoutNote" style="display:none;font-size:.72rem;color:#059669;font-weight:700;padding:.28rem .5rem;background:#ecfdf5;border-radius:6px;border:1px solid #bbf7d0;margin-top:.4rem;"></div>
        </div>
      </div>

      <!-- Paper & Layout -->
      <div class="sc" style="margin-bottom:.6rem;">
        <div class="sc-h"><i class="bi bi-printer"></i> Paper &amp; Layout</div>
        <div class="sc-b">
          <!-- Paper + Orientation in one row -->
          <div style="display:grid;grid-template-columns:1fr auto;gap:.4rem;align-items:end;margin-bottom:.6rem;">
            <div>
              <label class="glbl">Paper Size</label>
              <select class="gsel" id="genPaper" onchange="updateGenLayout()">
                <option value="A4">A4 (210×297 mm)</option>
                <option value="A3">A3 (297×420 mm)</option>
                <option value="A5">A5 (148×210 mm)</option>
                <option value="Letter">Letter (8.5×11")</option>
              </select>
            </div>
            <div>
              <label class="glbl">Orientation</label>
              <div style="display:flex;gap:.3rem;">
                <div class="szpill on" id="goPort" style="font-size:.7rem;padding:.25rem .5rem;" onclick="setGenOr('portrait',this)" title="Portrait"><i class="bi bi-phone"></i></div>
                <div class="szpill" id="goLand" style="font-size:.7rem;padding:.25rem .5rem;" onclick="setGenOr('landscape',this)" title="Landscape"><i class="bi bi-tablet-landscape"></i></div>
              </div>
            </div>
          </div>
          <!-- Margin + Card scale row -->
          <div style="display:grid;grid-template-columns:auto 1fr;gap:.6rem;align-items:center;margin-bottom:.55rem;">
            <div>
              <label class="glbl">Margin <small style="color:#94a3b8;font-weight:400;">(in)</small></label>
              <input class="ginp" id="mAll" value="0.2" style="text-align:center;width:62px;" oninput="syncMargins(this.value);updateGenLayout();" placeholder="0.2">
            </div>
            <div>
              <label class="glbl">Card Size <span id="cardScaleV" style="font-weight:700;color:var(--p);">100%</span></label>
              <input type="range" id="cardScale" min="50" max="130" value="100" step="5" style="width:100%;accent-color:var(--p);" oninput="document.getElementById('cardScaleV').textContent=this.value+'%';SA.gen.cardScale=this.value/100;updateGenLayout();">
            </div>
          </div>
          <!-- Cut Zone slider -->
          <div style="background:#fff5f5;border:1px solid #fecaca;border-radius:8px;padding:.45rem .55rem;margin-bottom:.45rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.2rem;">
              <label class="glbl" style="margin:0;color:#b91c1c;"><i class="bi bi-scissors"></i> Cut Zone</label>
              <span id="cutZoneV" style="font-size:.78rem;font-weight:700;color:#dc2626;">3 mm</span>
            </div>
            <input type="range" id="cutZoneMM" min="0" max="8" value="3" step="0.5" style="width:100%;accent-color:#dc2626;"
              oninput="document.getElementById('cutZoneV').textContent=this.value+' mm';applyCutZone(this.value);updateGenLayout();">
            <div style="display:flex;justify-content:space-between;font-size:.62rem;color:#fca5a5;margin-top:.1rem;"><span>0 (no gap)</span><span>3mm ✓</span><span>8mm</span></div>
          </div>
          <!-- Center + crop marks checkboxes -->
          <div style="display:flex;gap:.8rem;margin-bottom:.45rem;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.76rem;font-weight:600;color:var(--gt);">
              <input type="checkbox" id="optCenter" checked> Center on page
            </label>
            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.76rem;font-weight:600;color:var(--gt);">
              <input type="checkbox" id="optCutLines" checked> Cut guide lines
            </label>
          </div>
          <!-- Hidden individual margin/gap inputs (kept for JS compatibility) -->
          <input type="hidden" id="mTop" value="0.2"><input type="hidden" id="mBot" value="0.2">
          <input type="hidden" id="mLeft" value="0.2"><input type="hidden" id="mRight" value="0.2">
          <input type="hidden" id="gapX" value="0.118"><input type="hidden" id="gapY" value="0.118">
          <input type="hidden" id="gapAll" value="0.118">
        </div>
      </div>

      <!-- Output Format -->
      <div class="sc" style="margin-bottom:.6rem;">
        <div class="sc-h"><i class="bi bi-file-earmark-arrow-down"></i> Download Format</div>
        <div class="sc-b" style="padding:.5rem .6rem;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem;margin-bottom:.5rem;">
            <button class="fmt-btn on" id="fmt-pdf" onclick="setFmt('pdf',this)" style="flex-direction:column;align-items:center;padding:.5rem .3rem;text-align:center;">
              <i class="bi bi-file-earmark-pdf-fill" style="font-size:1.4rem;color:#dc2626;display:block;margin-bottom:.2rem;"></i>
              <div class="fmt-label" style="font-size:.75rem;">PDF Sheets</div>
              <div class="fmt-sub" style="font-size:.65rem;">Print-ready</div>
            </button>
            <button class="fmt-btn" id="fmt-png" onclick="setFmt('png',this)" style="flex-direction:column;align-items:center;padding:.5rem .3rem;text-align:center;">
              <i class="bi bi-file-earmark-image" style="font-size:1.4rem;color:#7c3aed;display:block;margin-bottom:.2rem;"></i>
              <div class="fmt-label" style="font-size:.75rem;">PNG Sheets</div>
              <div class="fmt-sub" style="font-size:.65rem;">Image pages</div>
            </button>
            <button class="fmt-btn" id="fmt-zip" onclick="setFmt('zip',this)" style="flex-direction:column;align-items:center;padding:.5rem .3rem;text-align:center;">
              <i class="bi bi-file-earmark-zip" style="font-size:1.4rem;color:#059669;display:block;margin-bottom:.2rem;"></i>
              <div class="fmt-label" style="font-size:.75rem;">Card ZIP</div>
              <div class="fmt-sub" style="font-size:.65rem;">1 card = 1 file</div>
            </button>
            <button class="fmt-btn" id="fmt-pdfzip" onclick="setFmt('pdfzip',this)" style="flex-direction:column;align-items:center;padding:.5rem .3rem;text-align:center;">
              <i class="bi bi-journals" style="font-size:1.4rem;color:#0369a1;display:block;margin-bottom:.2rem;"></i>
              <div class="fmt-label" style="font-size:.75rem;">PDF per Card</div>
              <div class="fmt-sub" style="font-size:.65rem;">ZIP of PDFs</div>
            </button>
          </div>
          <!-- Image quality (hidden for PDF formats) -->
          <div id="qualityRow" style="display:none;margin-bottom:.4rem;">
            <label class="glbl">Image Quality <span id="qualityV" style="color:var(--p);font-weight:700;">92%</span></label>
            <input type="range" id="imgQuality" min="60" max="100" value="92" step="2" style="width:100%;accent-color:var(--p);" oninput="document.getElementById('qualityV').textContent=this.value+'%';SA.gen.quality=this.value/100;">
          </div>
          <!-- Options -->
          <div style="display:flex;gap:.8rem;flex-wrap:wrap;">
            <input type="hidden" id="optCropMarks" value="1"><!-- crop marks always on when cut zone > 0 -->
            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.76rem;font-weight:600;color:#f59e0b;">
              <input type="checkbox" id="firstOnly"> Test mode (1st card only)
            </label>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <button class="btn btn-s" style="width:100%;margin-bottom:.4rem;" onclick="goStep(4)"><i class="bi bi-arrow-left"></i> Back to Designer</button>
      <button class="btn btn-v" id="genBtn" style="width:100%;font-size:.95rem;padding:.65rem;" onclick="startGeneration()">
        <i class="bi bi-lightning-charge-fill"></i> Generate &amp; Download
      </button>
      <div id="genProgressArea" class="hidden" style="margin-top:.6rem;">
        <div style="font-size:.78rem;color:var(--gm);margin-bottom:.3rem;" id="genProgressTxt">Preparing…</div>
        <div class="gen-progress-wrap"><div class="gen-progress-bar" id="genProgressBar" style="width:0%"></div></div>
      </div>
    </div>

    <!-- Right: layout preview + output -->
    <div>
      <div class="sc" style="margin-bottom:.6rem;">
        <div class="sc-h"><i class="bi bi-grid"></i> Layout Preview
          <span id="layoutInfo" style="margin-left:auto;font-size:.7rem;color:var(--gm);font-weight:400;"></span>
        </div>
        <div class="sc-b" style="background:#f8fafc;padding:.65rem;text-align:center;">
          <canvas id="layoutPreviewCanvas" style="max-width:100%;border-radius:4px;border:1px solid #e2e8f0;"></canvas>
        </div>
      </div>
      <div class="sc">
        <div class="sc-h"><i class="bi bi-eye"></i> Output Preview
          <span id="sheetCount" style="margin-left:auto;font-size:.7rem;color:var(--gm);font-weight:400;"></span>
        </div>
        <div class="sc-b" id="outputArea">
          <div style="color:var(--gm);text-align:center;padding:2rem 1rem;font-size:.83rem;">
            <i class="bi bi-lightning-charge" style="font-size:2.5rem;display:block;margin-bottom:.5rem;opacity:.2;"></i>
            Click Generate &amp; Download to begin
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toast container -->
<div id="smToast"></div>

<!-- ══ Card Lightbox ══ -->
<div id="cardLB" style="display:none;position:fixed;inset:0;z-index:9500;background:rgba(0,0,0,.88);align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)lbClose()">
  <div style="position:relative;display:flex;flex-direction:column;align-items:center;max-width:min(520px,94vw);">
    <!-- Close -->
    <button onclick="lbClose()" style="position:absolute;top:-42px;right:-6px;background:rgba(255,255,255,.12);border:none;color:#fff;font-size:1rem;width:34px;height:34px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;" onmouseenter="this.style.background='rgba(255,255,255,.28)'" onmouseleave="this.style.background='rgba(255,255,255,.12)'">✕</button>
    <!-- Image -->
    <div style="position:relative;width:100%;">
      <img id="lbImg" alt="" style="width:100%;max-height:68vh;object-fit:contain;border-radius:10px;box-shadow:0 12px 48px rgba(0,0,0,.6);display:block;">
      <!-- Side nav arrows -->
      <button id="lbPrev" onclick="lbNav(-1)" style="position:absolute;left:-22px;top:50%;transform:translateY(-50%);width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.18);border:none;color:#fff;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;" onmouseenter="this.style.background='rgba(255,255,255,.38)'" onmouseleave="this.style.background='rgba(255,255,255,.18)'">‹</button>
      <button id="lbNext" onclick="lbNav(1)"  style="position:absolute;right:-22px;top:50%;transform:translateY(-50%);width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.18);border:none;color:#fff;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;" onmouseenter="this.style.background='rgba(255,255,255,.38)'" onmouseleave="this.style.background='rgba(255,255,255,.18)'">›</button>
    </div>
    <!-- Info row -->
    <div id="lbInfo" style="margin-top:.55rem;text-align:center;color:#f1f5f9;font-size:.8rem;font-weight:600;line-height:1.5;"></div>
    <!-- Actions -->
    <div style="display:flex;gap:.55rem;margin-top:.7rem;flex-wrap:wrap;justify-content:center;">
      <button onclick="lbDownloadSingle()" style="padding:.4rem .9rem;border-radius:8px;background:#2563eb;border:none;color:#fff;font-size:.8rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:.35rem;">
        <i class="bi bi-download"></i> Download this card
      </button>
      <button onclick="lbNav(-1)" style="padding:.4rem .75rem;border-radius:8px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);color:#fff;font-size:.8rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.3rem;">
        ‹ Previous
      </button>
      <button onclick="lbNav(1)" style="padding:.4rem .75rem;border-radius:8px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);color:#fff;font-size:.8rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.3rem;">
        Next ›
      </button>
    </div>
    <!-- Dot strip (up to 20 dots) -->
    <div id="lbDots" style="display:flex;gap:.3rem;margin-top:.6rem;flex-wrap:wrap;justify-content:center;max-width:300px;"></div>
  </div>
</div>

<!-- Hidden inputs -->
<input type="file" id="bgInp" accept="image/*" class="hidden" onchange="applyBgImg(this)">

<!-- Libraries — self-hosted (assets/js/vendor/), same reasoning as generate.php -->
<script src="<?= BASE_URL ?>/assets/js/vendor/xlsx-0.18.5.full.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/vendor/fabric-5.3.0.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/vendor/jsbarcode-3.11.6.all.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/vendor/qrcode-1.0.0.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/vendor/jspdf-2.5.1.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/vendor/jszip-3.10.1.min.js"></script>
<script>
'use strict';

/* ══════════════════════════════════════
   PHOTO BORDER PATCH (fabric.Image)
══════════════════════════════════════ */
(function(){
  var _orig = fabric.Image.prototype.render;
  fabric.Image.prototype.render = function(ctx){
    _orig.call(this,ctx);
    var bw=+(this.photoBorderW)||0, shape=this.photoShape||'rect';
    if(!this.eleType||this.eleType!=='photo'||bw<=0||shape==='rect') return;
    var bc=this.photoBorderColor||'#333', t=this.calcTransformMatrix();
    var cx=t[4],cy=t[5],cw=this.getScaledWidth(),ch=this.getScaledHeight(),mn=Math.min(cw,ch);
    var ang=(this.angle||0)*Math.PI/180;
    ctx.save(); ctx.translate(cx,cy); if(ang) ctx.rotate(ang);
    ctx.strokeStyle=bc; ctx.lineWidth=bw; ctx.beginPath();
    if(shape==='circle') ctx.arc(0,0,mn/2,0,Math.PI*2);
    else if(shape==='ellipse'){if(ctx.ellipse)ctx.ellipse(0,0,cw/2,ch/2,0,0,Math.PI*2);else{ctx.scale(cw/ch,1);ctx.arc(0,0,ch/2,0,Math.PI*2);}}
    else{var r=shape==='round'?mn*0.12:mn*0.40,x=-cw/2,y=-ch/2;ctx.moveTo(x+r,y);ctx.lineTo(x+cw-r,y);ctx.quadraticCurveTo(x+cw,y,x+cw,y+r);ctx.lineTo(x+cw,y+ch-r);ctx.quadraticCurveTo(x+cw,y+ch,x+cw-r,y+ch);ctx.lineTo(x+r,y+ch);ctx.quadraticCurveTo(x,y+ch,x,y+ch-r);ctx.lineTo(x,y+r);ctx.quadraticCurveTo(x,y,x+r,y);ctx.closePath();}
    ctx.stroke(); ctx.restore();
  };
})();

/* ══════════════════════════════════════
   STATE
══════════════════════════════════════ */
const PAPER = { A4:[8.27,11.69], A3:[11.69,16.54], A5:[5.83,8.27], Letter:[8.5,11] };

const SA = {
  step: 1,
  excel: { headers:[], rows:[], colTypes:{} },
  photos: new Map(),
  signs: new Map(), singleSign: null,
  project: {
    cardW:2.125, cardH:3.375, dpi:300, bgColor:'#ffffff', sides:'single',
    photoCol:'', signCol:'',
    qrFields:[], barcodeField:'', barcodeType:'CODE128',
    watermark:'', serialEnabled:false,
    frontJSON:null, backJSON:null,
    frontBgURL:null, backBgURL:null,
    templateURL:null,
    activeSide3:'front',
    frontDetectHTML:'', backDetectHTML:'',
    frontThumbSrc:'', backThumbSrc:'',
    frontFileName:'', backFileName:'',
    frontHasFilled:false, backHasFilled:false,
    frontPreviewHidden:true, backPreviewHidden:true,
  },
  canvas: null,
  dispW:600, dispH:840,
  curSide: 'front',
  previewRow: 0,
  previewMode: false,
  snapGrid: false, gridSize: 20,
  history:[], histIdx:-1,
  generating: false,
  outputBlob: null, outputType: '',
  gen: { paper:'A4', orientation:'portrait', mTop:0,mBot:0,mLeft:0,mRight:0, gapX:0,gapY:0, format:'pdf', cardScale:1.0, cropMarks:false },
};

/* ══════════════════════════════════════
   WIZARD NAVIGATION
══════════════════════════════════════ */
function goStep(n){
  if(n===SA.step) return;
  if(n>SA.step && !validateStep(SA.step)) return;
  document.querySelectorAll('.sw-step').forEach(s=>s.classList.remove('active'));
  document.getElementById('step'+n).classList.add('active');
  for(let i=1;i<=5;i++){
    const it=document.getElementById('swi'+i), nm=document.getElementById('swn'+i);
    it.classList.remove('active','done');
    if(i<n){it.classList.add('done'); nm.innerHTML='<i class="bi bi-check-lg"></i>';}
    else if(i===n){it.classList.add('active'); nm.textContent=i;}
    else nm.textContent=i;
  }
  SA.step=n;
  // Hide the "Print Card Template" promo banner only while step 4 (Designer)
  // is open on mobile — it's just orientation content, not needed while
  // actively working, and it was eating screen height the card design needs
  // to stay visible behind the Add/Properties sheets. Same fix already
  // applied on school/school_icard.php's Designer.
  document.body.classList.toggle('des-step4-mob', n===4);
  if(n===4) setTimeout(initDesignerIfNeeded,80);
  if(n===5) setTimeout(()=>{
    syncMargins(document.getElementById('mAll')?.value||'0.2');
    applyCutZone(document.getElementById('cutZoneMM')?.value||'3');
    autoLayout();
  },80);
  window.scrollTo(0,0);
}

function validateStep(n){
  if(n===1){
    if(!SA.excel.rows.length){ toast('Please upload an Excel file first','warn'); return false; }
    return true;
  }
  if(n===2) return true;
  if(n===3){
    if(!SA.project.cardW||!SA.project.cardH){ toast('Please select a card size','warn'); return false; }
    return true;
  }
  if(n===4) return true;
  return true;
}

function step1Next(){ goStep(2); if(!SA.excel.rows.length)return; buildColGrid(); }
function step2Next(){ goStep(3); }
function step3Next(){
  SA.project.bgColor = document.getElementById('bgColor').value;
  goStep(4);
}
function step4Next(){
  if(SA.canvas) SA.project[SA.curSide+'JSON']=SA.canvas.toJSON(['eleType','fieldName','photoShape','photoBorderW','photoBorderColor']);
  goStep(5);
}

/* ══════════════════════════════════════
   DRAG-AND-DROP ZONE HELPERS
══════════════════════════════════════ */
function dzOver(e,id){ e.preventDefault(); document.getElementById(id).classList.add('drag'); }
function dzLeave(id){ document.getElementById(id).classList.remove('drag'); }
function dzDrop(e,type){
  e.preventDefault();
  const id='dz'+type[0].toUpperCase()+type.slice(1);
  document.getElementById(id)?.classList.remove('drag');
  const files=e.dataTransfer.files;
  if(!files.length) return;
  if(type==='excel') loadExcel(files[0]);
  else if(type==='photos') loadPhotos(files);
  else if(type==='signs') loadSigns(files);
  else if(type==='template') loadTemplate(files[0]);
}

/* ══════════════════════════════════════
   STEP 1 — EXCEL
══════════════════════════════════════ */
function loadExcel(file){
  if(!file) return;
  const reader=new FileReader();
  reader.onload=e=>{
    try{
      const wb=XLSX.read(e.target.result,{type:'array'});
      const ws=wb.Sheets[wb.SheetNames[0]];
      const json=XLSX.utils.sheet_to_json(ws,{defval:''});
      if(!json.length){toast('Excel is empty','warn');return;}
      SA.excel.headers=Object.keys(json[0]);
      SA.excel.rows=json;
      autoDetectColTypes();
      const dz=document.getElementById('dzExcel');
      dz.classList.add('filled'); dz.classList.remove('drag');
      document.getElementById('excelOk').textContent=`✓ ${file.name} — ${json.length} rows · ${SA.excel.headers.length} columns`;
      buildExcelPreview();
      updateStep1Status();
      toast(`Excel loaded: ${json.length} rows`,'ok');
    } catch(err){ toast('Failed to read Excel: '+err.message,'error'); }
  };
  reader.readAsArrayBuffer(file);
}

function autoDetectColTypes(){
  const ct={};
  const NAME_KW=/name|naam|student|pupil/i;
  const CLASS_KW=/class|grade|std|standard/i;
  const ROLL_KW=/roll|id|no|number|sr|serial/i;
  const PHOTO_KW=/photo|image|pic|img|file/i;
  const SIGN_KW=/sign|signature/i;
  SA.excel.headers.forEach(h=>{
    if(NAME_KW.test(h)) ct[h]='name';
    else if(CLASS_KW.test(h)) ct[h]='class';
    else if(ROLL_KW.test(h)) ct[h]='roll';
    else if(PHOTO_KW.test(h)){ ct[h]='photo'; if(!SA.project.photoCol) SA.project.photoCol=h; }
    else if(SIGN_KW.test(h)){ ct[h]='sign'; if(!SA.project.signCol) SA.project.signCol=h; }
    else ct[h]='';
  });
  SA.excel.colTypes=ct;
}

function buildExcelPreview(){
  const area=document.getElementById('excelPreviewArea');
  const tbl=document.getElementById('excelPreviewTbl');
  const chip=document.getElementById('excelInfoChip');
  chip.textContent=`${SA.excel.rows.length} rows · ${SA.excel.headers.length} columns`;
  let html='<thead><tr>'+SA.excel.headers.map(h=>`<th>${h}</th>`).join('')+'</tr></thead><tbody>';
  const preview=SA.excel.rows.slice(0,5);
  preview.forEach(row=>{
    html+='<tr>'+SA.excel.headers.map(h=>`<td>${row[h]||''}</td>`).join('')+'</tr>';
  });
  html+='</tbody>';
  tbl.innerHTML=html;
  area.classList.remove('hidden');
}

/* ══════════════════════════════════════
   STEP 1 — PHOTOS
══════════════════════════════════════ */
function loadPhotos(files){
  const arr=Array.from(files).filter(f=>f.type.startsWith('image/'));
  if(!arr.length) return;
  SA.photos.clear();
  SA.dcPhotos=null; // a manually chosen photo folder replaces the Data Collection photos
  let done=0;
  arr.forEach(f=>{
    const reader=new FileReader();
    reader.onload=e=>{
      const nm=f.name.toLowerCase();
      SA.photos.set(nm, e.target.result);
      SA.photos.set(nm.replace(/\.[^.]+$/,''), e.target.result);
      done++;
      if(done===arr.length){
        const dz=document.getElementById('dzPhotos');
        dz.classList.add('filled');
        document.getElementById('photosOk').textContent=`✓ ${arr.length} photos loaded`;
        buildPhotoStrip(arr);
        updateStep1Status();
        // Show match result after status update
        const m=_countPhotoMatches();
        if(m) toast(m.matched===m.total?`All ${m.total} photos matched!`:`${m.matched}/${m.total} photos matched — check filenames`,(m.matched===m.total)?'ok':'warn');
        else toast(`${arr.length} photos loaded`,'ok');
      }
    };
    reader.readAsDataURL(f);
  });
}

function buildPhotoStrip(arr){
  const strip=document.getElementById('photoStrip');
  strip.classList.remove('hidden');
  const show=arr.slice(0,6);
  strip.innerHTML=show.map(f=>`<img class="photo-thumb" src="${URL.createObjectURL(f)}" alt="">`).join('');
  if(arr.length>6) strip.innerHTML+=`<div class="photo-more">+${arr.length-6}</div>`;
}

function loadSigns(files){
  const arr=Array.from(files).filter(f=>f.type.startsWith('image/'));
  if(!arr.length) return;
  SA.signs.clear(); SA.singleSign=null;
  let done=0;
  arr.forEach(f=>{
    const reader=new FileReader();
    reader.onload=e=>{
      if(arr.length===1) SA.singleSign=e.target.result;
      SA.signs.set(f.name.toLowerCase().replace(/\.[^.]+$/,''), e.target.result);
      done++;
      if(done===arr.length){
        document.getElementById('dzSigns').classList.add('filled');
        document.getElementById('signsOk').textContent=`✓ ${arr.length} signature${arr.length>1?'s':''} loaded`;
        updateStep1Status();
        toast(`${arr.length} signature${arr.length>1?'s':''} loaded`,'ok');
      }
    };
    reader.readAsDataURL(f);
  });
}

function _countPhotoMatches(){
  if(SA.dcPhotos&&SA.excel.rows.length){
    let m=0; SA.excel.rows.forEach(r=>{ if(SA.dcPhotos.has(String(r.__rid))) m++; });
    return {matched:m, total:SA.excel.rows.length};
  }
  if(!SA.excel.rows.length||!SA.photos.size) return null;
  // Same rule as resolveRowPhoto(): marked Photo column, else the sheet's first column.
  let matched=0;
  SA.excel.rows.forEach(row=>{ if(resolveRowPhoto(row)) matched++; });
  return {matched, total:SA.excel.rows.length};
}

function updateStep1Status(){
  const el=document.getElementById('step1Status');
  const rows=SA.excel.rows.length, photos=SA.photos.size/2|0, signs=SA.signs.size;
  const items=[
    {ok:rows>0, label:`Excel: ${rows>0?rows+' rows':'not loaded'}`},
    {ok:photos>0, label:`Photos: ${photos>0?photos+' loaded':'not loaded'}`},
    {ok:true, label:`Signatures: ${signs>0?signs+' loaded':'optional — skip if not needed'}`},
  ];
  // Photo match count — show only when both Excel and photos are loaded
  const match=_countPhotoMatches();
  if(match!==null){
    const pct=Math.round(match.matched/match.total*100);
    const allMatch=match.matched===match.total;
    const noneMatch=match.matched===0;
    items.push({
      ok:match.matched>0,
      label:allMatch?`Photo match: All ${match.total} students matched!`:
            noneMatch?`Photo match: 0 matched — photo file names must equal the values of column "${SA.project.photoCol||SA.excel.headers[0]}" (e.g. No 5 → 5.jpg)`:
            `Photo match: ${match.matched}/${match.total} matched (${match.total-match.matched} missing)`,
      warn:!allMatch
    });
  }
  el.innerHTML=items.map(i=>`
    <div style="display:flex;align-items:flex-start;gap:.5rem;padding:.3rem 0;font-size:.78rem;color:${i.ok?(i.warn?'#d97706':'#059669'):'var(--gm)'};">
      <i class="bi ${i.ok?(i.warn?'bi-exclamation-circle-fill':'bi-check-circle-fill'):'bi-circle'}" style="flex-shrink:0;margin-top:.1rem;"></i>
      <span>${i.label}</span>
    </div>`).join('');
}

/* ══════════════════════════════════════
   STEP 2 — COLUMN ROLE ASSIGNMENT
══════════════════════════════════════ */
const ROLES=[
  {key:'qr',   label:'QR',   cls:'on-qr',  multi:true},
  {key:'bar',  label:'Bar',  cls:'on-bar', multi:false},
  {key:'photo',label:'Photo',cls:'on-photo',multi:false},
  {key:'sign', label:'Sign', cls:'on-sign', multi:false},
];

function buildColGrid(){
  const grid=document.getElementById('colGrid');
  const sampleRow=SA.excel.rows[0]||{};
  grid.innerHTML=SA.excel.headers.map(h=>{
    const sample=String(sampleRow[h]||'').slice(0,24);
    const ct=SA.excel.colTypes[h]||'';
    const autoLabel=ct?`<span style="font-size:.6rem;font-weight:700;color:var(--p);margin-left:.3rem;">${ct}</span>`:'';
    return `<div class="col-card" id="cc_${CSS.escape(h)}" data-col="${h}">
      <div class="col-name">${h}${autoLabel}</div>
      <div class="col-sample">${sample||'—'}</div>
      <div class="col-roles">
        ${ROLES.map(r=>`<button class="role-btn ${isRoleOn(h,r)?r.cls:''}" id="rb_${r.key}_${CSS.escape(h)}" onclick="toggleRole('${h.replace(/'/g,"\\'")}','${r.key}')">${r.label}</button>`).join('')}
      </div>
    </div>`;
  }).join('');
  // Auto-assign photo/sign based on detection
  if(SA.project.photoCol) forceRole(SA.project.photoCol,'photo');
  if(SA.project.signCol) forceRole(SA.project.signCol,'sign');
  // Show auto-detect notice
  const noticed=[];
  if(SA.project.photoCol) noticed.push(`Photo → "${SA.project.photoCol}"`);
  if(SA.project.signCol) noticed.push(`Sign → "${SA.project.signCol}"`);
  const noticeEl=document.getElementById('autoDetectNotice');
  const noticeText=document.getElementById('autoDetectText');
  if(noticeEl&&noticeText){
    if(noticed.length){
      noticeText.textContent='Auto-detected: '+noticed.join(' · ')+' — verify and change if needed';
      noticeEl.style.display='flex';
    } else {
      noticeEl.style.display='none';
    }
  }
}

function isRoleOn(col,role){
  if(role.key==='qr') return SA.project.qrFields.includes(col);
  if(role.key==='bar') return SA.project.barcodeField===col;
  if(role.key==='photo') return SA.project.photoCol===col;
  if(role.key==='sign') return SA.project.signCol===col;
  return false;
}

function toggleRole(col,roleKey){
  if(roleKey==='qr'){
    const idx=SA.project.qrFields.indexOf(col);
    if(idx>-1) SA.project.qrFields.splice(idx,1);
    else SA.project.qrFields.push(col);
  } else if(roleKey==='bar'){
    SA.project.barcodeField = SA.project.barcodeField===col?'':col;
    SA.project.barcodeType = document.getElementById('barcodeType').value;
  } else if(roleKey==='photo'){
    SA.project.photoCol = SA.project.photoCol===col?'':col;
  } else if(roleKey==='sign'){
    SA.project.signCol = SA.project.signCol===col?'':col;
  }
  refreshColGrid();
  refreshQRPreview();
  refreshBarPreview();
}

function forceRole(col, roleKey){
  if(roleKey==='photo'&&!SA.project.photoCol) SA.project.photoCol=col;
  if(roleKey==='sign'&&!SA.project.signCol) SA.project.signCol=col;
  refreshColGrid();
}

function refreshColGrid(){
  SA.excel.headers.forEach(h=>{
    const card=document.getElementById('cc_'+CSS.escape(h));
    if(!card) return;
    const assigned=SA.project.qrFields.includes(h)||SA.project.barcodeField===h||SA.project.photoCol===h||SA.project.signCol===h;
    card.classList.toggle('assigned',assigned);
    ROLES.forEach(r=>{
      const btn=document.getElementById('rb_'+r.key+'_'+CSS.escape(h));
      if(!btn) return;
      const on=isRoleOn(h,r);
      btn.className='role-btn '+(on?r.cls:'');
    });
  });
}

function refreshQRPreview(){
  const box=document.getElementById('qrPrevBox');
  if(!SA.project.qrFields.length){ box.innerHTML='<i class="bi bi-qr-code" style="font-size:2rem;color:#d1d5db;"></i><span style="font-size:.7rem;color:var(--gm);">Assign QR fields to see preview</span>'; return; }
  const row=SA.excel.rows[0]||{};
  const text=SA.project.qrFields.map(f=>row[f]||'').join(' ').trim()||'Sample QR';
  box.innerHTML='<div id="qrPrevDiv"></div><span style="font-size:.68rem;color:var(--gm);">'+SA.project.qrFields.join(' + ')+'</span>';
  try{new QRCode(document.getElementById('qrPrevDiv'),{text,width:90,height:90,correctLevel:QRCode.CorrectLevel[document.getElementById('qrLevel').value||'M']});}catch(e){}
}

function refreshBarPreview(){
  const box=document.getElementById('barPrevBox');
  const none=document.getElementById('barPrevNone');
  const cvs=document.getElementById('barcodePreviewCanvas');
  SA.project.barcodeType=document.getElementById('barcodeType').value;
  if(!SA.project.barcodeField){ none.style.display=''; cvs.style.display='none'; return; }
  const row=SA.excel.rows[0]||{};
  const val=String(row[SA.project.barcodeField]||'123456789');
  none.style.display='none'; cvs.style.display='';
  try{ JsBarcode(cvs,val,{format:SA.project.barcodeType,width:1.5,height:40,fontSize:10,lineColor:'#000',background:'#fff',margin:4,displayValue:true}); }catch(e){none.style.display='';cvs.style.display='none';}
}

/* ══════════════════════════════════════
   STEP 3 — TEMPLATE & SIZE
══════════════════════════════════════ */
function loadTemplate(file){
  if(!file||!file.type.startsWith('image/')) return;
  const reader=new FileReader();
  reader.onload=e=>{
    const url=e.target.result;
    const isBack=SA.project.sides==='double'&&SA.project.activeSide3==='back';
    SA.project.templateURL=url;
    if(isBack) SA.project.backBgURL=url;
    else SA.project.frontBgURL=url;
    const img=new Image();
    img.onload=()=>{
      SA._templateImg=img; // store for DPI recalculation
      if(isBack) SA._backTemplateImg=img; else SA._frontTemplateImg=img;
      _pvImgAR=img.naturalWidth/img.naturalHeight||1; // for interactive preview
      const {finalW,finalH,info}=autoDetectSz(img.naturalWidth,img.naturalHeight);
      SA.project.cardW=finalW; SA.project.cardH=finalH;
      // Find matching preset
      highlightPreset(finalW,finalH);
      updateSzPreview();
      updateCardThumbs();
      showDpiQuality();
      // Show template thumb
      document.getElementById('tplThumb').src=url;
      document.getElementById('tplDetectInfo').innerHTML=
        `<b>Detected:</b> ${finalW.toFixed(3)}" × ${finalH.toFixed(3)}" (${img.naturalWidth}×${img.naturalHeight}px)<br>${info}`;
      document.getElementById('tplPreviewArea').classList.remove('hidden');
      document.getElementById('dzTemplate').classList.add('filled');
      document.getElementById('templateOk').textContent=`✓ ${file.name}`;
      // If designer already init'd, resize it
      if(SA.canvas) resizeCanvas(finalW,finalH);
      toast(`Template loaded — Size: ${finalW.toFixed(2)}" × ${finalH.toFixed(2)}"`,'ok');
    };
    img.src=url;
  };
  reader.readAsDataURL(file);
}

function skipTemplate(e){
  if(e) e.preventDefault();
  const isBack=SA.project.sides==='double'&&SA.project.activeSide3==='back';
  SA.project.templateURL=null;
  if(isBack){ SA.project.backBgURL=null; SA._backTemplateImg=null; }
  else { SA.project.frontBgURL=null; SA._frontTemplateImg=null; }
  SA._templateImg=null;
  document.getElementById('dzTemplate').classList.remove('filled');
  document.getElementById('templateOk').textContent='';
  document.getElementById('tplPreviewArea').classList.add('hidden');
  document.getElementById('inpTemplate').value='';
  document.getElementById('tplDzTitle').textContent='Drop card template image here';
  _pvTplURL=''; updateSzPreview();
  const b=document.getElementById('dpiQualityBadge'); if(b) b.style.display='none';
  toast('Using plain background — you can set the color below','info');
}

function clearTemplate(){ skipTemplate(); }

function autoDetectSz(px,py){
  const STD=[
    // Full page
    {w:8.27,h:11.69,n:'A4 Portrait'},{w:11.69,h:8.27,n:'A4 Landscape'},
    {w:5.83,h:8.27,n:'A5 Portrait'},{w:8.27,h:5.83,n:'A5 Landscape'},
    {w:11.69,h:16.54,n:'A3 Portrait'},{w:16.54,h:11.69,n:'A3 Landscape'},
    {w:4.134,h:5.826,n:'A6 Portrait'},{w:5.826,h:4.134,n:'A6 Landscape'},
    {w:2.913,h:4.134,n:'A7 Portrait'},
    {w:8.5,h:11,n:'Letter Portrait'},{w:11,h:8.5,n:'Letter Landscape'},
    // Half page
    {w:4.135,h:5.845,n:'Fee Card Portrait'},{w:5.845,h:4.135,n:'Fee Card Landscape'},
    {w:5.845,h:8.27,n:'Half A4 Portrait'},{w:8.27,h:5.845,n:'Admit Card Landscape'},
    {w:5.5,h:8.5,n:'Half Letter Portrait'},{w:8.5,h:5.5,n:'Half Letter Landscape'},
    {w:4.25,h:5.5,n:'Qtr Letter Portrait'},{w:5.5,h:4.25,n:'Qtr Letter Landscape'},
    // ID/small cards
    {w:2.125,h:3.375,n:'School ID Portrait'},{w:3.375,h:2.125,n:'School ID Landscape'},
    {w:3.5,h:2.0,n:'Business Card'},{w:2.0,h:3.5,n:'Business Card Portrait'},
    {w:1.97,h:2.76,n:'Passport 5×7'},{w:1.34,h:1.77,n:'Passport Photo'},
  ];
  let best=null,bestScore=999;
  for(const sz of STD){
    const dpX=px/sz.w, dpY=py/sz.h;
    if(dpX<36||dpX>720||dpY<36||dpY>720) continue;
    const con=Math.abs(dpX-dpY)/Math.max(dpX,dpY);
    if(con>0.04) continue;
    const avg=(dpX+dpY)/2;
    const near=Math.min(...[72,96,120,150,200,300,600].map(d=>Math.abs(d-avg)/d));
    const score=con*5+near;
    if(score<bestScore){bestScore=score;best={w:sz.w,h:sz.h,dpi:Math.round(avg),n:sz.n};}
  }
  const printDPI=SA.project.dpi||300;
  if(best&&bestScore<0.40) return{finalW:best.w,finalH:best.h,info:`Matched ${best.n} @ ~${best.dpi} DPI`};
  const fall=(Math.min(px,py)/printDPI)<1.5?96:printDPI;
  return{finalW:px/fall,finalH:py/fall,info:`Custom size @ ${fall} DPI fallback`};
}

function highlightPreset(w,h){
  let found=false;
  document.querySelectorAll('.sz-card').forEach(c=>{
    const cw=+c.dataset.w,ch=+c.dataset.h;
    const match=Math.abs(cw-w)<0.05&&Math.abs(ch-h)<0.05;
    c.classList.toggle('on',match);
    if(match) found=true;
  });
  // If no exact match, fall back to cross-orientation match
  if(!found){
    document.querySelectorAll('.sz-card').forEach(c=>{
      const cw=+c.dataset.w,ch=+c.dataset.h;
      const cross=Math.abs(ch-w)<0.05&&Math.abs(cw-h)<0.05;
      if(cross) c.classList.add('on');
    });
  }
}

function selectSize(el){
  document.querySelectorAll('.sz-card').forEach(c=>c.classList.remove('on'));
  el.classList.add('on');
  const w=+el.dataset.w,h=+el.dataset.h;
  const customRow=document.getElementById('customSzRow');
  if(!w&&!h){ customRow.classList.remove('hidden'); return; }
  customRow.classList.add('hidden');
  SA.project.cardW=w; SA.project.cardH=h;
  const chip=document.getElementById('selectedSizeChip');
  chip.textContent=`${w.toFixed(2)}" × ${h.toFixed(2)}"`;
  chip.style.display='inline-flex';
  updateSzPreview(true); // true = reset image to fit on new size
  showDpiQuality();
  if(SA.canvas) resizeCanvas(w,h);
}

function applyCustomSize(){
  const w=parseFloat(document.getElementById('customW').value);
  const h=parseFloat(document.getElementById('customH').value);
  if(!w||!h) return;
  SA.project.cardW=w; SA.project.cardH=h;
  updateSzPreview(true);
  showDpiQuality();
  if(SA.canvas) resizeCanvas(w,h);
  toast(`Custom size: ${w.toFixed(2)}" × ${h.toFixed(2)}"`,'ok');
}
function updateCustomSzPreview(){ updateSzPreview(); }

/* ── Preview image interactive state ── */
let _pvZoom=100;
let _pvImgX=0,_pvImgY=0,_pvImgW=0,_pvImgH=0,_pvImgAR=1;
let _pvBW=0,_pvBH=0; // last box dimensions (for proportional scaling on zoom)
let _pvOp=null; // active drag op: {type:'move'|'nw'|'ne'|'sw'|'se', sx,sy,ix,iy,iw,ih}
let _pvTplURL='';

function applyPvTransform(){
  const img=document.getElementById('pvImg');
  if(!img) return;
  img.style.left=_pvImgX+'px';
  img.style.top=_pvImgY+'px';
  img.style.width=_pvImgW+'px';
  img.style.height=_pvImgH+'px';
}

function resetPvImage(bw,bh,mode){
  bw=bw||_pvBW||200; bh=bh||_pvBH||150;
  const ar=_pvImgAR||1;
  mode=mode||'fit';
  if(mode==='cover'){
    // Fill: image covers the entire box (may overflow, clipped by pvClip)
    if(ar>bw/bh){ _pvImgH=bh; _pvImgW=Math.round(bh*ar); }
    else { _pvImgW=bw; _pvImgH=Math.round(bw/ar); }
  } else if(mode==='reset'){
    // Fit: entire image visible inside box
    if(ar>bw/bh){ _pvImgW=bw; _pvImgH=Math.round(bw/ar); }
    else { _pvImgH=bh; _pvImgW=Math.round(bh*ar); }
  } else {
    // Default fit
    if(ar>bw/bh){ _pvImgW=bw; _pvImgH=Math.round(bw/ar); }
    else { _pvImgH=bh; _pvImgW=Math.round(bh*ar); }
  }
  // Center within box
  _pvImgX=Math.round((bw-_pvImgW)/2);
  _pvImgY=Math.round((bh-_pvImgH)/2);
  applyPvTransform();
}

function setPvMode(mode,btn){
  document.querySelectorAll('#pvBtnCover,#pvBtnFit,#pvBtnFull').forEach(b=>b.style.opacity='.5');
  if(btn) btn.style.opacity='1';
  resetPvImage(_pvBW,_pvBH,mode);
}
function setPvZoom(val){
  _pvZoom=+val;
  const el=document.getElementById('pvZoomPct');
  if(el) el.textContent=val+'%';
  updateSzPreview();
}

function initPreviewInteract(){
  const box=document.getElementById('szPreviewBox');
  const img=document.getElementById('pvImg');
  if(!box||!img) return;

  const getXY=e=>e.touches?[e.touches[0].clientX,e.touches[0].clientY]:[e.clientX,e.clientY];

  const startMove=e=>{
    if(!_pvTplURL) return;
    const [cx,cy]=getXY(e);
    _pvOp={type:'move',sx:cx,sy:cy,ix:_pvImgX,iy:_pvImgY,iw:_pvImgW,ih:_pvImgH};
    e.preventDefault(); e.stopPropagation();
  };
  img.addEventListener('mousedown',startMove);
  img.addEventListener('touchstart',startMove,{passive:false});

  box.querySelectorAll('.pv-corner').forEach(h=>{
    const startResize=e=>{
      if(!_pvTplURL) return;
      const [cx,cy]=getXY(e);
      _pvOp={type:h.dataset.dir,sx:cx,sy:cy,ix:_pvImgX,iy:_pvImgY,iw:_pvImgW,ih:_pvImgH};
      e.preventDefault(); e.stopPropagation();
    };
    h.addEventListener('mousedown',startResize);
    h.addEventListener('touchstart',startResize,{passive:false});
  });

  const onMove=e=>{
    if(!_pvOp) return;
    const [cx,cy]=getXY(e);
    const dx=cx-_pvOp.sx, dy=cy-_pvOp.sy;
    const ar=_pvImgAR||1;
    if(_pvOp.type==='move'){
      _pvImgX=_pvOp.ix+dx;
      _pvImgY=_pvOp.iy+dy;
    } else {
      // Resize — maintain aspect ratio, anchor is opposite corner
      let nw=_pvOp.iw,nh=_pvOp.ih,nx=_pvOp.ix,ny=_pvOp.iy;
      const dir=_pvOp.type;
      const raw=dir==='se'?_pvOp.iw+dx : dir==='sw'?_pvOp.iw-dx : dir==='ne'?_pvOp.iw+dx : _pvOp.iw-dx;
      nw=Math.max(50,raw);
      nh=Math.round(nw/ar);
      if(dir==='sw'||dir==='nw') nx=_pvOp.ix+_pvOp.iw-nw;
      if(dir==='ne'||dir==='nw') ny=_pvOp.iy+_pvOp.ih-nh;
      _pvImgW=nw; _pvImgH=nh; _pvImgX=nx; _pvImgY=ny;
    }
    applyPvTransform();
    e.preventDefault();
  };
  const onStop=()=>{ _pvOp=null; };
  document.addEventListener('mousemove',onMove);
  document.addEventListener('touchmove',e=>{ if(_pvOp) onMove(e); },{passive:false});
  document.addEventListener('mouseup',onStop);
  document.addEventListener('touchend',onStop);

  // Double-click resets to fit
  box.addEventListener('dblclick',()=>resetPvImage(_pvBW,_pvBH,'fit'));
}

function _pvBoxDims(){
  const w=SA.project.cardW||3.375, h=SA.project.cardH||2.125;
  const wrap=document.getElementById('szPreviewWrap');
  const maxW=wrap?Math.min(320,wrap.clientWidth-40)||220:220;
  const maxH=Math.round(maxW*1.6);
  const base=Math.round(200*_pvZoom/100);
  let sc=base/Math.max(w,h);
  if(w*sc>maxW) sc=maxW/w;
  if(h*sc>maxH) sc=maxH/h;
  return [Math.round(w*sc),Math.round(h*sc)];
}

function updateSzPreview(resetImg){
  const w=SA.project.cardW||3.375, h=SA.project.cardH||2.125;
  const dpi=SA.project.dpi||300;
  const box=document.getElementById('szPreviewBox');
  const clip=document.getElementById('pvClip');
  const pvImg=document.getElementById('pvImg');
  const info=document.getElementById('szPreviewInfo');
  const hint=document.getElementById('szPreviewHint');
  if(!box) return;

  const [bw,bh]=_pvBoxDims();

  // When box size changes due to zoom: scale image proportionally
  if(!resetImg && _pvBW && _pvBH && (_pvBW!==bw||_pvBH!==bh) && _pvImgW){
    const sx=bw/_pvBW, sy=bh/_pvBH;
    _pvImgX=Math.round(_pvImgX*sx);
    _pvImgY=Math.round(_pvImgY*sy);
    _pvImgW=Math.round(_pvImgW*(sx+sy)/2);
    _pvImgH=Math.round(_pvImgW/_pvImgAR);
    applyPvTransform();
  }
  _pvBW=bw; _pvBH=bh;
  box.style.width=bw+'px';
  box.style.height=bh+'px';

  const tplURL=SA.project.templateURL||SA.project.frontBgURL||'';
  if(tplURL&&pvImg){
    if(tplURL!==_pvTplURL){
      _pvTplURL=tplURL;
      // Register onload BEFORE setting src to avoid missing cached-image events
      pvImg.onload=()=>{
        _pvImgAR=pvImg.naturalWidth/pvImg.naturalHeight||1;
        resetPvImage(bw,bh,'fit');
      };
      pvImg.src=tplURL;
      // Fallback: data-URLs are often already complete after .src= assignment
      if(pvImg.complete&&pvImg.naturalWidth){
        _pvImgAR=pvImg.naturalWidth/pvImg.naturalHeight||1;
        resetPvImage(bw,bh,'fit');
      }
    } else {
      if(resetImg) resetPvImage(bw,bh,'fit');
      // Re-apply transform in case box was resized
      else applyPvTransform();
    }
    pvImg.style.display='block'; // must be 'block', not '' (CSS rule says display:none)
    document.querySelectorAll('.pv-corner').forEach(h=>h.style.display='block');
    if(clip) clip.style.background='#e2e8f0';
    if(hint) hint.innerHTML='<span style="color:#6366f1;font-weight:600;">Drag image to move</span> &nbsp;·&nbsp; <span style="color:#2563eb;font-weight:600;">Drag blue dots to resize</span> &nbsp;·&nbsp; Double-click to reset';
  } else {
    _pvTplURL='';
    if(pvImg){ pvImg.style.display='none'; pvImg.src=''; }
    document.querySelectorAll('.pv-corner').forEach(h=>h.style.display='none');
    if(clip) clip.style.background='linear-gradient(135deg,#dbeafe,#c7d2fe)';
    if(hint) hint.textContent='Upload a template image above to see it here';
  }

  const cmW=(w*2.54).toFixed(1), cmH=(h*2.54).toFixed(1);
  const pxW=Math.round(w*dpi), pxH=Math.round(h*dpi);
  const a4label=calcA4Label(w,h);
  if(info) info.innerHTML=`<b>${w.toFixed(2)}" × ${h.toFixed(2)}"</b> &nbsp;·&nbsp; ${cmW}×${cmH}cm &nbsp;·&nbsp; <span style="color:#6366f1;font-weight:700;">${a4label}</span><br><span style="color:#94a3b8;">${pxW}×${pxH} px @ ${dpi} DPI</span>`;
}

function showDpiQuality(){
  const badge=document.getElementById('dpiQualityBadge');
  if(!badge||!SA._templateImg||!SA.project.cardW) return;
  const dpi=SA.project.dpi||300;
  const needW=Math.round(SA.project.cardW*dpi), needH=Math.round(SA.project.cardH*dpi);
  const haveW=SA._templateImg.naturalWidth, haveH=SA._templateImg.naturalHeight;
  const ratio=Math.min(haveW/needW,haveH/needH);
  badge.style.display='block';
  if(ratio>=0.95){
    badge.innerHTML=`<span style="background:#dcfce7;color:#166534;border-radius:20px;padding:.2rem .7rem;font-size:.68rem;font-weight:700;">✓ Good quality for ${dpi} DPI</span>`;
  } else if(ratio>=0.55){
    badge.innerHTML=`<span style="background:#fef9c3;color:#854d0e;border-radius:20px;padding:.2rem .7rem;font-size:.68rem;font-weight:700;">⚠ Slightly small for ${dpi} DPI (${Math.round(ratio*100)}%)</span>`;
  } else {
    badge.innerHTML=`<span style="background:#fee2e2;color:#991b1b;border-radius:20px;padding:.2rem .7rem;font-size:.68rem;font-weight:700;">✗ Image too small for ${dpi} DPI — may look blurry</span>`;
  }
}

function updateCardThumbs(){
  const url=SA.project.templateURL||SA.project.frontBgURL||'';
  document.querySelectorAll('.sz-thumb-inner').forEach(el=>{
    if(url){
      el.style.background='';
      el.style.backgroundImage=`url('${url}')`;
      el.style.backgroundSize='cover';
      el.style.backgroundPosition='center';
    } else {
      el.style.backgroundImage='';
      el.style.background='';
    }
  });
}

/* ── A4 comparison labels ── */
function calcA4Label(w,h){
  if(!w||!h) return '';
  const A4W=8.27,A4H=11.69;
  // Exact A4 match — check BEFORE larger check so A4 landscape is not mis-labelled
  if((Math.abs(w-A4W)<0.15&&Math.abs(h-A4H)<0.15)||(Math.abs(w-A4H)<0.15&&Math.abs(h-A4W)<0.15)) return '1 per A4';
  // Exact A3 match portrait or landscape
  const A3H=16.54;
  if((Math.abs(w-A4H)<0.2&&Math.abs(h-A3H)<0.2)||(Math.abs(w-A3H)<0.2&&Math.abs(h-A4H)<0.2)) return 'A3 — 2× A4';
  // Larger than A4
  if(w>A4W*1.05||h>A4H*1.05) return 'Larger than A4';
  // Calculate how many fit on A4 portrait, try both orientations
  const n1=Math.floor(A4W/w)*Math.floor(A4H/h);
  const n2=Math.floor(A4W/h)*Math.floor(A4H/w);
  const n=Math.max(n1,n2,1);
  return n===1?'1 per A4':`${n} per A4`;
}
function refreshSzLabels(){
  document.querySelectorAll('.sz-card[data-w]').forEach(card=>{
    const w=+card.dataset.w,h=+card.dataset.h;
    const dim=card.querySelector('.sz-dim');
    if(!dim) return;
    if(!w&&!h){ dim.textContent='Set your own'; return; }
    dim.textContent=calcA4Label(w,h);
  });
}

function setDPI(el){
  document.querySelectorAll('[data-dpi]').forEach(x=>x.classList.remove('on'));
  el.classList.add('on');
  const newDPI=+el.dataset.dpi;
  SA.project.dpi=newDPI;
  // If template image loaded, check if image matches current card at new DPI
  // If not enough pixels, recalculate card size so image fills it exactly
  if(SA._templateImg&&SA.project.cardW){
    const px=SA._templateImg.naturalWidth, py=SA._templateImg.naturalHeight;
    const needW=Math.round(SA.project.cardW*newDPI), needH=Math.round(SA.project.cardH*newDPI);
    const ratio=Math.min(px/needW,py/needH);
    if(ratio<0.80){
      // Image doesn't have enough pixels — recalculate card size from image at new DPI
      const newW=px/newDPI, newH=py/newDPI;
      SA.project.cardW=newW; SA.project.cardH=newH;
      highlightPreset(newW,newH);
      const chip=document.getElementById('selectedSizeChip');
      chip.textContent=`${newW.toFixed(2)}" × ${newH.toFixed(2)}"`;
      chip.style.display='inline-flex';
      toast(`Size adjusted to ${newW.toFixed(2)}" × ${newH.toFixed(2)}" to fit image at ${newDPI} DPI`,'warn');
    }
    showDpiQuality();
  }
  updateSzPreview();
  updateSzBar();
}

function setSides(v,el){
  document.querySelectorAll('#sdSingle,#sdDouble').forEach(x=>x.classList.remove('on'));
  el.classList.add('on');
  SA.project.sides=v;
  // Designer side tabs (step 4)
  const desTabs=document.getElementById('sideTabs');
  if(desTabs) desTabs.style.display=v==='double'?'flex':'none';
  // Step 3 side tabs
  const s3Tabs=document.getElementById('s3SideTabs');
  const badge=document.getElementById('s3SideBadge');
  if(v==='double'){
    if(s3Tabs) s3Tabs.style.display='grid';
    if(badge){ badge.style.display=''; badge.textContent='FRONT'; }
    SA.project.activeSide3='front';
    _updateS3TabButtons('front');
    // Make sure front template is active
    SA.project.templateURL=SA.project.frontBgURL||null;
    _pvTplURL=''; updateSzPreview();
  } else {
    if(s3Tabs) s3Tabs.style.display='none';
    if(badge) badge.style.display='none';
    SA.project.activeSide3='front';
  }
}

function _updateS3TabButtons(side){
  const btnF=document.getElementById('s3TabFront');
  const btnB=document.getElementById('s3TabBack');
  if(!btnF||!btnB) return;
  if(side==='front'){
    btnF.style.background='#2563eb'; btnF.style.color='#fff';
    btnB.style.background='transparent'; btnB.style.color='#64748b';
  } else {
    btnB.style.background='#2563eb'; btnB.style.color='#fff';
    btnF.style.background='transparent'; btnF.style.color='#64748b';
  }
}

function switchCardSide3(side){
  const cur=SA.project.activeSide3||'front';
  if(cur===side) return;

  // Save current side's upload UI state
  const thumb=document.getElementById('tplThumb');
  const detectInfo=document.getElementById('tplDetectInfo');
  const ok=document.getElementById('templateOk');
  const dz=document.getElementById('dzTemplate');
  const title=document.getElementById('tplDzTitle');
  const previewArea=document.getElementById('tplPreviewArea');
  if(cur==='front'){
    SA.project.frontThumbSrc=thumb.src;
    SA.project.frontDetectHTML=detectInfo.innerHTML;
    SA.project.frontFileName=ok.textContent;
    SA.project.frontHasFilled=dz.classList.contains('filled');
    SA.project.frontPreviewHidden=previewArea.classList.contains('hidden');
  } else {
    SA.project.backThumbSrc=thumb.src;
    SA.project.backDetectHTML=detectInfo.innerHTML;
    SA.project.backFileName=ok.textContent;
    SA.project.backHasFilled=dz.classList.contains('filled');
    SA.project.backPreviewHidden=previewArea.classList.contains('hidden');
  }

  SA.project.activeSide3=side;
  _updateS3TabButtons(side);
  const badge=document.getElementById('s3SideBadge');
  if(badge) badge.textContent=side==='front'?'FRONT':'BACK';

  // Restore new side's state
  document.getElementById('inpTemplate').value='';
  if(side==='front'){
    thumb.src=SA.project.frontThumbSrc||'';
    detectInfo.innerHTML=SA.project.frontDetectHTML||'';
    ok.textContent=SA.project.frontFileName||'';
    if(SA.project.frontHasFilled) dz.classList.add('filled'); else dz.classList.remove('filled');
    if(!SA.project.frontPreviewHidden) previewArea.classList.remove('hidden'); else previewArea.classList.add('hidden');
    if(title) title.textContent='Drop FRONT template image here';
    SA.project.templateURL=SA.project.frontBgURL||null;
    SA._templateImg=SA._frontTemplateImg||null;
  } else {
    if(SA.project.backBgURL){
      thumb.src=SA.project.backThumbSrc||'';
      detectInfo.innerHTML=SA.project.backDetectHTML||'';
      ok.textContent=SA.project.backFileName||'';
      if(SA.project.backHasFilled) dz.classList.add('filled'); else dz.classList.remove('filled');
      if(!SA.project.backPreviewHidden) previewArea.classList.remove('hidden'); else previewArea.classList.add('hidden');
    } else {
      // Fresh blank back side
      thumb.src=''; detectInfo.innerHTML=''; ok.textContent='';
      dz.classList.remove('filled');
      previewArea.classList.add('hidden');
    }
    if(title) title.textContent='Drop BACK template image here';
    SA.project.templateURL=SA.project.backBgURL||null;
    SA._templateImg=SA._backTemplateImg||null;
  }
  _pvTplURL=''; updateSzPreview();
  if(SA._templateImg) showDpiQuality();
  else { const b=document.getElementById('dpiQualityBadge'); if(b) b.style.display='none'; }
}

function syncBgHex(){
  const v=document.getElementById('bgHex').value;
  if(/^#[0-9a-fA-F]{6}$/.test(v)){
    document.getElementById('bgColor').value=v;
    SA.project.bgColor=v;
    if(SA.canvas) SA.canvas.setBackgroundColor(v,SA.canvas.renderAll.bind(SA.canvas));
  }
}

/* ══════════════════════════════════════
   STEP 4 — DESIGNER (Fabric.js)
══════════════════════════════════════ */
let _desInited=false;
function initDesignerIfNeeded(){ if(!SA.canvas||!SA.canvas.getElement()) initDesigner(); }

function initDesigner(){
  const {cardW,cardH,bgColor}=SA.project;
  const ratio=cardH/cardW;
  SA.dispW=600; SA.dispH=Math.round(600*ratio);
  const el=document.getElementById('desCanvas');
  el.width=SA.dispW; el.height=SA.dispH;
  if(SA.canvas) SA.canvas.dispose();
  SA.canvas=new fabric.Canvas('desCanvas',{
    width:SA.dispW, height:SA.dispH,
    backgroundColor:bgColor,
    preserveObjectStacking:true, selection:true,
  });

  // Load template as locked background
  if(SA.project.frontBgURL){
    fabric.Image.fromURL(SA.project.frontBgURL,img=>{
      SA.canvas.setBackgroundImage(img,SA.canvas.renderAll.bind(SA.canvas),{
        scaleX:SA.dispW/img.width, scaleY:SA.dispH/img.height
      });
    });
  }

  // Restore saved JSON
  if(SA.project.frontJSON){
    SA.canvas.loadFromJSON(SA.project.frontJSON,()=>SA.canvas.renderAll(),(o,obj)=>{
      obj.eleType=o.eleType; obj.fieldName=o.fieldName;
      if(o.photoShape){obj.photoShape=o.photoShape;obj.photoBorderW=o.photoBorderW;obj.photoBorderColor=o.photoBorderColor;}
    });
  }

  updateSzBar();
  buildFieldsList();

  // Events
  SA.canvas.on('selection:created',e=>updateProp(e.selected[0]));
  SA.canvas.on('selection:updated',e=>updateProp(e.selected[0]));
  SA.canvas.on('selection:cleared',()=>showProp('empty'));
  SA.canvas.on('object:modified',e=>{saveHist();updateProp(e.target);buildLayerList();});
  SA.canvas.on('object:added',()=>{buildLayerList();});
  SA.canvas.on('object:removed',()=>{buildLayerList();});

  // Textbox: convert scale to dimensions so font never stretches
  SA.canvas.on('object:scaling',e=>{
    const obj=e.target;
    if(obj.type==='textbox'){
      const newW=Math.max(30, obj.width*obj.scaleX);
      const newH=Math.max(obj.fontSize||16, obj.height*obj.scaleY);
      obj.set({width:newW, height:newH, scaleX:1, scaleY:1});
      obj.setCoords();
    }
  });

  // Snap grid
  SA.canvas.on('object:moving',e=>{
    if(SA.snapGrid){
      const gs=SA.gridSize;
      e.target.set({left:Math.round(e.target.left/gs)*gs,top:Math.round(e.target.top/gs)*gs});
    }
  });

  saveHist();
  _desInited=true;
  // Set initial side tab active state
  const tf=document.getElementById('tabFront'), tb=document.getElementById('tabBack');
  if(tf){ tf.style.background='var(--p)'; tf.style.color='#fff'; tf.style.borderColor='var(--p)'; }
  if(tb){ tb.style.background='#fff'; tb.style.color='#94a3b8'; tb.style.borderColor='#e2e8f0'; }
  desAutoFitZoom();
}

/* ══════════════════════════════════════
   MOBILE DESIGNER UI — auto-fit zoom + slide-up panels
   (all gated to window.innerWidth<=900; desktop rendering/behavior is never
   touched). Same technique as school/school_icard.php's Designer: a plain
   CSS transform:scale() on the canvas WRAPPER, not Fabric's own zoom API —
   the canvas itself always stays at its native SA.dispW/dispH resolution,
   so none of the "physical vs logical size" scale-math bugs found in the
   Generate ID Cards page's zoom (which DID resize the Fabric canvas
   itself) can happen here at all.
══════════════════════════════════════ */
let _desZoom=1;
function setDesZoom(z){
  _desZoom=z;
  const wrap=document.getElementById('canvasWrap');
  const outer=document.getElementById('canvasOuter');
  if(!wrap||!outer) return;
  if(z===1){ wrap.style.transform=''; wrap.style.width=''; wrap.style.height=''; outer.style.width=''; outer.style.height=''; return; }
  // #canvasWrap has no CSS width of its own, so as a plain block element it
  // was shrinking to match #canvasOuter's explicit (zoomed-down) width below
  // instead of its actual content's full-size width — with overflow:hidden
  // on the wrap, that silently clipped off the right side of the card BEFORE
  // the scale-down transform even ran (e.g. only showing the left 54% of a
  // 600px-wide canvas when outer was set to 324px). Pinning the wrap to the
  // canvas's real logical size first means there's nothing left to clip —
  // the transform then shrinks that correctly-sized box down to fit visually.
  wrap.style.width=(SA.dispW||600)+'px';
  wrap.style.height=(SA.dispH||840)+'px';
  wrap.style.transform='scale('+z+')';
  wrap.style.transformOrigin='top left';
  outer.style.width=Math.round((SA.dispW||600)*z)+'px';
  outer.style.height=Math.round((SA.dispH||840)*z)+'px';
}
function desAutoFitZoom(){
  if(window.innerWidth>900){ setDesZoom(1); return; }
  const center=document.querySelector('.des-center');
  if(!center) return;
  const available=center.clientWidth-16;
  const natural=SA.dispW||600;
  if(available<=0||natural<=0) return;
  let z=Math.min(1,available/natural);
  z=Math.max(0.3,Math.floor(z*100)/100);
  setDesZoom(z);
}
let _desResizeTid=null;
window.addEventListener('resize',()=>{
  clearTimeout(_desResizeTid);
  _desResizeTid=setTimeout(()=>{ if(SA.canvas) desAutoFitZoom(); },150);
});

function openDesPanel(side){
  const panel=document.getElementById(side==='left'?'desLeftPanel':'desRightPanel');
  const other=document.getElementById(side==='left'?'desRightPanel':'desLeftPanel');
  if(!panel) return;
  other?.classList.remove('mob-open');
  panel.classList.add('mob-open');
  document.getElementById('desSheetBackdrop')?.classList.add('show');
}
function closeDesPanel(side){
  if(side){
    document.getElementById(side==='left'?'desLeftPanel':'desRightPanel')?.classList.remove('mob-open');
  } else {
    document.getElementById('desLeftPanel')?.classList.remove('mob-open');
    document.getElementById('desRightPanel')?.classList.remove('mob-open');
  }
  document.getElementById('desSheetBackdrop')?.classList.remove('show');
}
/* Close the Add-Elements sheet right after tapping an add button — mirrors
   the natural "pick it, see it land on the card" flow on a phone. Properties
   deliberately does NOT auto-open on selection here (that was tried on the
   Generate ID Cards page and reverted per feedback — it covered too much of
   the screen every time an element was merely tapped/moved); this stays a
   manual "Properties" tap only, same as "Add". */
function mobCloseLeftAfterAdd(){
  if(window.innerWidth<=900) closeDesPanel('left');
}

function resizeCanvas(newW,newH){
  SA.project.cardW=newW; SA.project.cardH=newH;
  const newDW=600, newDH=Math.round(600*newH/newW);
  if(SA.canvas){
    // Old LOGICAL size (SA.dispW/dispH from the previous init/resize), not
    // SA.canvas.width/height — on mobile the canvas element's physical
    // pixel size is whatever zoom is currently applied (e.g. 46% of the
    // logical 600px), so using it here computed a wildly wrong rescale
    // factor and applied it to every existing element's position/size —
    // e.g. changing the card size after already placing fields would fling
    // them far outside the card or blow them up huge.
    const oldW=SA.dispW||newDW, oldH=SA.dispH||newDH;
    const scX=newDW/oldW, scY=newDH/oldH;
    SA.canvas.getObjects().forEach(obj=>{
      if(obj.type==='textbox'){
        // Same protection as the object:scaling handler above ("Textbox:
        // convert scale to dimensions so font never stretches") — applied
        // here too, since resizing the CARD (via the W/H fields in the
        // toolbar) went through this separate code path and left a lingering
        // non-1 scaleX/scaleY on every text field instead of baking the
        // change into width/fontSize like a direct drag-resize already does.
        // A field's chosen font size (e.g. 20→30 in the Typography panel)
        // still reads back correctly afterward either way, so this went
        // unnoticed on screen — Fabric applies the object's own fontSize
        // AND its scaleY together for on-canvas display, so the drawn size
        // still looked right. But it's exactly this kind of transform-vs-
        // property split that risks coming apart between the interactive
        // canvas and a freshly reconstructed offscreen render, so fields are
        // normalized back to true scale 1 immediately, same as everywhere
        // else in the designer, rather than leaving a hidden multiplier.
        obj.set({
          left:obj.left*scX, top:obj.top*scY,
          width:Math.max(30,obj.width*scX),
          fontSize:Math.max(6,Math.round((obj.fontSize||20)*scY)),
          scaleX:1, scaleY:1,
        });
      } else {
        obj.set({left:obj.left*scX,top:obj.top*scY,scaleX:(obj.scaleX||1)*scX,scaleY:(obj.scaleY||1)*scY});
      }
      obj.setCoords();
    });
    const el=document.getElementById('desCanvas');
    el.width=newDW; el.height=newDH;
    SA.canvas.setWidth(newDW); SA.canvas.setHeight(newDH);
    // Re-fit background — double-sided bug: this always re-applied
    // frontBgURL regardless of which side was actually open, so resizing
    // the card while designing the BACK silently replaced its background
    // with the front's (or blanked it if front had none).
    const curBgURL=SA.project[SA.curSide+'BgURL'];
    if(curBgURL){
      fabric.Image.fromURL(curBgURL,img=>{
        SA.canvas.setBackgroundImage(img,SA.canvas.renderAll.bind(SA.canvas),{scaleX:newDW/img.width,scaleY:newDH/img.height});
      });
    } else { SA.canvas.renderAll(); }
  }
  SA.dispW=newDW; SA.dispH=newDH;
  updateSzBar();
  desAutoFitZoom();
}

function updateSzBar(){
  const w=SA.project.cardW||3.375, h=SA.project.cardH||2.125;
  const dpi=SA.project.dpi||300;
  const wEl=document.getElementById('desW'), hEl=document.getElementById('desH');
  if(wEl) wEl.value=w.toFixed(3);
  if(hEl) hEl.value=h.toFixed(3);
  const wpx=document.getElementById('desWpx'), hpx=document.getElementById('desHpx');
  if(wpx) wpx.textContent='('+Math.round(w*dpi)+'px)';
  if(hpx) hpx.textContent='('+Math.round(h*dpi)+'px)';
  const lbl=document.getElementById('canvasSzLabel');
  if(lbl) lbl.textContent=`${SA.dispW}×${SA.dispH}px display · ${Math.round(w*dpi)}×${Math.round(h*dpi)}px export @ ${dpi}DPI`;
}

function resizeFromUI(){
  const nW=parseFloat(document.getElementById('desW').value);
  const nH=parseFloat(document.getElementById('desH').value);
  if(nW>0.1&&nH>0.1) resizeCanvas(nW,nH);
}

/* ── Background ── */
function setBg(){ const i=document.getElementById('bgInp'); i.value=''; i.click(); }
function clearBg(){
  SA.canvas.setBackgroundImage(null,()=>{});
  SA.canvas.setBackgroundColor(SA.project.bgColor,SA.canvas.renderAll.bind(SA.canvas));
  // Double-sided bug: this always cleared frontBgURL regardless of which
  // side was actually open — clearing the BACK side's background while
  // designing it silently wiped the FRONT side's background instead (and
  // left the back with a stale reference), since front/back are tracked
  // as two separate URLs. Same fix as applyBgImg() below.
  SA.project[SA.curSide+'BgURL']=null;
  if(SA.curSide==='front') SA.project.templateURL=null;
}
function applyBgImg(inp){
  const f=inp.files[0]; if(!f) return;
  const reader=new FileReader();
  reader.onload=e=>{
    const url=e.target.result;
    // Double-sided bug: this always wrote to frontBgURL — uploading a
    // background while the Back side tab was active silently overwrote
    // the FRONT side's background (with what was meant for the back) and
    // left the back side with nothing.
    SA.project[SA.curSide+'BgURL']=url;
    const probe=new Image();
    probe.onload=()=>{
      const {finalW,finalH}=autoDetectSz(probe.naturalWidth,probe.naturalHeight);
      resizeCanvas(finalW,finalH);
      fabric.Image.fromURL(url,bgImg=>{
        SA.canvas.setBackgroundImage(bgImg,SA.canvas.renderAll.bind(SA.canvas),{scaleX:SA.dispW/bgImg.width,scaleY:SA.dispH/bgImg.height});
        saveHist();
      });
    };
    probe.src=url;
  };
  reader.readAsDataURL(f);
}

/* Photo placeholder SVG */
const PHOTO_PH='data:image/svg+xml;charset=utf-8,'+encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="250" viewBox="0 0 200 250">'+
  '<rect width="200" height="250" fill="#e0e7ff" rx="6"/>'+
  '<circle cx="100" cy="90" r="50" fill="#818cf8"/>'+
  '<ellipse cx="100" cy="245" rx="78" ry="62" fill="#818cf8"/>'+
  '<text x="100" y="238" text-anchor="middle" font-size="20" fill="#4338ca" font-family="Arial,sans-serif">Photo</text>'+
  '</svg>'
);

/* ── Add Elements ── */
function addLabel(){
  const obj=new fabric.Textbox('Text Label',{
    left:50,top:50,width:200,fontFamily:'Arial',fontSize:18,fill:'#1a1a1a',
    eleType:'label',editable:true,
  });
  SA.canvas.add(obj); SA.canvas.setActiveObject(obj); SA.canvas.renderAll(); saveHist();
}
function addPhotoEl(){
  const n=SA.canvas.getObjects().length;
  fabric.Image.fromURL(PHOTO_PH,img=>{
    img.set({
      left:30, top:30+n*5,
      scaleX:80/img.width, scaleY:100/img.height,
      eleType:'photo', photoShape:'rect', photoBorderW:0, photoBorderColor:'#000000',
    });
    SA.canvas.add(img); SA.canvas.setActiveObject(img); SA.canvas.renderAll(); saveHist();
  });
}
function addSignEl(){
  const obj=new fabric.Rect({left:30,top:160,width:100,height:40,fill:'#f5f3ff',stroke:'#7c3aed',strokeWidth:2,rx:3,eleType:'sign'});
  SA.canvas.add(obj); SA.canvas.setActiveObject(obj); SA.canvas.renderAll(); saveHist();
}
function addBarcodeEl(){
  const obj=new fabric.Rect({left:40,top:220,width:160,height:50,fill:'#fffbeb',stroke:'#d97706',strokeWidth:2,eleType:'barcode'});
  SA.canvas.add(obj); SA.canvas.setActiveObject(obj); SA.canvas.renderAll(); saveHist();
}
function addQREl(){
  const x=Math.max(0,(SA.dispW||600)-110), y=30, sz=80;
  // Render a real QR code immediately using first row data
  const row0=SA.excel.rows[0]||{};
  const qv=SA.project.qrFields.length?SA.project.qrFields.map(f=>String(row0[f]||'')).join('\n').trim()||'SAMPLE QR':String(Object.values(row0)[0]||'SAMPLE QR');
  _makeQRImg(qv,sz,img=>{
    img.set({left:x,top:y,eleType:'qr',_qrVal:qv});
    SA.canvas.add(img); SA.canvas.setActiveObject(img); SA.canvas.renderAll(); saveHist();
  });
}
/* Render a QR code into a fabric.Image of given size, call cb(img) */
function _makeQRImg(val,sz,cb){
  try{
    const tmp=document.createElement('div');
    tmp.style.cssText='position:fixed;left:-9999px;top:-9999px;width:'+sz+'px;height:'+sz+'px;';
    document.body.appendChild(tmp);
    new QRCode(tmp,{text:String(val||'QR'),width:sz*2,height:sz*2,correctLevel:QRCode.CorrectLevel.M});
    setTimeout(()=>{
      const el=tmp.querySelector('canvas')||tmp.querySelector('img');
      const src=el?(el.tagName==='CANVAS'?el.toDataURL():el.src):null;
      document.body.removeChild(tmp);
      if(!src){
        cb(new fabric.Rect({width:sz,height:sz,fill:'#ecfdf5',stroke:'#059669',strokeWidth:2,eleType:'qr'}));
        return;
      }
      fabric.Image.fromURL(src,img=>{
        img.set({scaleX:sz/img.width,scaleY:sz/img.height});
        cb(img);
      });
    },180);
  }catch(e){
    cb(new fabric.Rect({width:sz,height:sz,fill:'#ecfdf5',stroke:'#059669',strokeWidth:2,eleType:'qr'}));
  }
}
function addRect(){
  const obj=new fabric.Rect({left:80,top:80,width:150,height:60,fill:'transparent',stroke:'#374151',strokeWidth:2,rx:4,eleType:'shape'});
  SA.canvas.add(obj); SA.canvas.setActiveObject(obj); SA.canvas.renderAll(); saveHist();
}
function addLine(){
  const obj=new fabric.Line([40,200,260,200],{stroke:'#374151',strokeWidth:2,eleType:'line',selectable:true});
  SA.canvas.add(obj); SA.canvas.setActiveObject(obj); SA.canvas.renderAll(); saveHist();
}
function addFieldEl(name,dropX,dropY){
  const n=SA.canvas.getObjects().length;
  const obj=new fabric.Textbox('['+name+']',{
    left:dropX!==undefined?Math.max(0,dropX-80):50,
    top:dropY!==undefined?Math.max(0,dropY-12):60+n*24,
    width:180,fontFamily:'Arial',fontSize:20,fill:'#1a56db',
    eleType:'field',fieldName:name,editable:false,padding:0,strokeWidth:0,
  });
  SA.canvas.add(obj); SA.canvas.setActiveObject(obj); SA.canvas.renderAll(); saveHist();
  buildLayerList();
}

/* ── Fields list (left panel) ── */
function buildFieldsList(){
  const cont=document.getElementById('fieldsList');
  if(!SA.excel.headers.length){ cont.innerHTML='<div style="color:var(--gm);font-size:.72rem;">No Excel loaded</div>'; return; }
  cont.innerHTML=SA.excel.headers.map(h=>
    `<div class="field-item" draggable="true" ondragstart="fieldDragStart(event,'${h.replace(/'/g,"\\'")}',)" onclick="addFieldEl('${h.replace(/'/g,"\\'")}');mobCloseLeftAfterAdd()">
      <i class="bi bi-grip-vertical" style="color:#9ca3af;font-size:.65rem;"></i>
      <i class="bi bi-braces" style="font-size:.72rem;"></i>
      <span style="font-size:.73rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">[${h}]</span>
    </div>`
  ).join('');
}
/* One-click: auto-place all Excel columns as field text elements */
function addAllFields(){
  if(!SA.canvas||!SA.excel.headers.length) return;
  // Remove any existing field elements first to avoid duplicates
  const existing=SA.canvas.getObjects().filter(o=>o.eleType==='field').map(o=>o.fieldName);
  const toAdd=SA.excel.headers.filter(h=>!existing.includes(h));
  if(!toAdd.length){ toast('All fields already on canvas','info'); return; }
  const cW=SA.dispW||600, cH=SA.dispH||840;
  const startY=Math.round(cH*0.12), lineH=Math.round(cH*0.07);
  let y=startY;
  toAdd.forEach(h=>{
    const obj=new fabric.Textbox('['+h+']',{
      left:Math.round(cW*0.06), top:y,
      width:Math.round(cW*0.88),
      fontFamily:'Arial', fontSize:Math.round(cH*0.028),
      fill:'#1a56db', eleType:'field', fieldName:h, editable:false,
    });
    SA.canvas.add(obj);
    y+=lineH;
  });
  SA.canvas.renderAll(); saveHist(); buildLayerList();
  toast(`${toAdd.length} fields added — drag to position them`,'ok');
}

function fieldDragStart(ev,name){ ev.dataTransfer.setData('fieldName',name); ev.dataTransfer.effectAllowed='copy'; }
function canvasDrop(ev){
  ev.preventDefault();
  const name=ev.dataTransfer.getData('fieldName');
  if(!name||!SA.canvas) return;
  const wrap=document.getElementById('canvasWrap');
  const rect=wrap.getBoundingClientRect();
  const scaleX=SA.canvas.width/rect.width, scaleY=SA.canvas.height/rect.height;
  addFieldEl(name,(ev.clientX-rect.left)*scaleX,(ev.clientY-rect.top)*scaleY);
}

/* ── Layer list (right panel) ── */
const ELTYPE_ICON={'field':'bi-braces','label':'bi-fonts','photo':'bi-person-bounding-box','sign':'bi-pen','barcode':'bi-upc','qr':'bi-qr-code','shape':'bi-square','line':'bi-slash-lg'};
function buildLayerList(){
  const cont=document.getElementById('layerList');
  if(!SA.canvas){ cont.innerHTML=''; return; }
  const objs=[...SA.canvas.getObjects()].reverse();
  if(!objs.length){ cont.innerHTML='<div style="color:var(--gm);font-size:.7rem;text-align:center;padding:.5rem;">No elements yet</div>'; return; }
  const active=SA.canvas.getActiveObject();
  cont.innerHTML=objs.map((obj,i)=>{
    const et=obj.eleType||'label';
    const ico=ELTYPE_ICON[et]||'bi-square';
    const lbl=obj.fieldName?'['+obj.fieldName+']':(obj.text||et);
    const isSel=(obj===active);
    return `<div class="layer-item ${isSel?'sel':''}" onclick="selectLayer(${objs.length-1-i})">
      <i class="bi ${ico}"></i>
      <span>${lbl}</span>
      <button class="layer-btn" onclick="deleteLayer(event,${objs.length-1-i})" title="Delete"><i class="bi bi-trash"></i></button>
    </div>`;
  }).join('');
}
function selectLayer(idx){ SA.canvas.setActiveObject(SA.canvas.item(idx)); SA.canvas.renderAll(); buildLayerList(); updateProp(SA.canvas.item(idx)); }
function deleteLayer(e,idx){ e.stopPropagation(); SA.canvas.remove(SA.canvas.item(idx)); SA.canvas.renderAll(); saveHist(); buildLayerList(); }

/* ── Properties panel ── */
function showProp(which){
  ['propEmpty','propText','propImg','propPhoto','propAlign','propPos'].forEach(id=>document.getElementById(id).classList.add('hidden'));
  document.getElementById('propEmpty').classList.remove('hidden');
  if(which==='empty') return;
  document.getElementById('propEmpty').classList.add('hidden');
  if(which==='text'||which==='field'){ document.getElementById('propText').classList.remove('hidden'); document.getElementById('staticTextRow').style.display=which==='text'?'':'none'; }
  if(which==='img'||which==='photo') document.getElementById('propImg').classList.remove('hidden');
  if(which==='photo') document.getElementById('propPhoto').classList.remove('hidden');
  document.getElementById('propAlign').classList.remove('hidden');
  document.getElementById('propPos').classList.remove('hidden');
}

/* ── Alignment to card edges ── */
function alignObj(dir){
  const obj=SA.canvas.getActiveObject(); if(!obj) return;
  const cW=SA.canvas.getWidth(), cH=SA.canvas.getHeight();
  const br=obj.getBoundingRect(true);
  let dx=0,dy=0;
  if(dir==='left')    dx=0-br.left;
  else if(dir==='centerH') dx=(cW/2)-(br.left+br.width/2);
  else if(dir==='right')   dx=cW-(br.left+br.width);
  else if(dir==='top')     dy=0-br.top;
  else if(dir==='centerV') dy=(cH/2)-(br.top+br.height/2);
  else if(dir==='bottom')  dy=cH-(br.top+br.height);
  obj.set({left:obj.left+dx, top:obj.top+dy});
  obj.setCoords(); SA.canvas.renderAll(); saveHist(); updateProp(obj);
}
function updateProp(obj){
  if(!obj){ showProp('empty'); buildLayerList(); return; }
  const et=obj.eleType;
  if(et==='field'){ showProp('field'); document.getElementById('pFont').value=obj.fontFamily||'Arial'; document.getElementById('pSize').value=obj.fontSize||20; document.getElementById('pFill').value=obj.fill||'#000000'; document.getElementById('pFillH').value=obj.fill||'#000000'; }
  else if(et==='label'||obj.type==='textbox'||obj.type==='text'){ showProp('text'); document.getElementById('pFont').value=obj.fontFamily||'Arial'; document.getElementById('pSize').value=obj.fontSize||16; document.getElementById('pFill').value=obj.fill||'#000000'; document.getElementById('pFillH').value=obj.fill||'#000000'; document.getElementById('pText').value=obj.text||''; document.getElementById('pBold').classList.toggle('on',obj.fontWeight==='bold'); document.getElementById('pItal').classList.toggle('on',obj.fontStyle==='italic'); document.getElementById('pUndr').classList.toggle('on',!!obj.underline); }
  else if(et==='photo'){ showProp('photo'); document.getElementById('pW').value=Math.round(obj.getScaledWidth()); document.getElementById('pH').value=Math.round(obj.getScaledHeight()); document.getElementById('pBorderW').value=obj.photoBorderW||0; document.getElementById('pBorderC').value=obj.photoBorderColor||'#000000'; ['rect','round','circle','ellipse'].forEach(s=>document.getElementById('psh'+s[0].toUpperCase()+s.slice(1))?.classList.toggle('on',obj.photoShape===s)); }
  else{ showProp('img'); if(document.getElementById('pW')) document.getElementById('pW').value=Math.round(obj.getScaledWidth()||obj.width||0); if(document.getElementById('pH')) document.getElementById('pH').value=Math.round(obj.getScaledHeight()||obj.height||0); }
  document.getElementById('pX').value=Math.round(obj.left||0);
  document.getElementById('pY').value=Math.round(obj.top||0);
  document.getElementById('pRot').value=obj.angle||0;
  document.getElementById('pRotV').textContent=(obj.angle||0)+'°';
  document.getElementById('pOpa').value=Math.round((obj.opacity||1)*100);
  document.getElementById('pOpaV').textContent=Math.round((obj.opacity||1)*100)+'%';
  buildLayerList();
}
function ap(key,val){ const obj=SA.canvas.getActiveObject(); if(!obj) return; obj.set(key,val); SA.canvas.renderAll(); }
function syncFillHex(){ const v=document.getElementById('pFillH').value; if(/^#[0-9a-fA-F]{6}$/.test(v)){document.getElementById('pFill').value=v;ap('fill',v);} }
function toggleBold(){ const o=SA.canvas.getActiveObject(); if(!o) return; o.set('fontWeight',o.fontWeight==='bold'?'normal':'bold'); SA.canvas.renderAll(); document.getElementById('pBold').classList.toggle('on',o.fontWeight==='bold'); }
function toggleItal(){ const o=SA.canvas.getActiveObject(); if(!o) return; o.set('fontStyle',o.fontStyle==='italic'?'normal':'italic'); SA.canvas.renderAll(); document.getElementById('pItal').classList.toggle('on',o.fontStyle==='italic'); }
function toggleUndr(){ const o=SA.canvas.getActiveObject(); if(!o) return; o.set('underline',!o.underline); SA.canvas.renderAll(); document.getElementById('pUndr').classList.toggle('on',o.underline); }
function apXY(){ const o=SA.canvas.getActiveObject(); if(!o) return; o.set({left:+document.getElementById('pX').value,top:+document.getElementById('pY').value}); o.setCoords(); SA.canvas.renderAll(); }
function apSize(){ const o=SA.canvas.getActiveObject(); if(!o) return; const tw=+document.getElementById('pW').value,th=+document.getElementById('pH').value; if(tw>0) o.set('scaleX',tw/o.width); if(th>0) o.set('scaleY',th/o.height); o.setCoords(); SA.canvas.renderAll(); }
function deleteSelected(){ const o=SA.canvas.getActiveObject(); if(!o) return; SA.canvas.remove(o); SA.canvas.renderAll(); saveHist(); buildLayerList(); showProp('empty'); }

/* ── Photo shape ── */
function applyPhotoShapeVisual(obj,shape){
  // Clip relative to object's own unscaled dimensions
  const w=obj.width||80, h=obj.height||100;
  obj.clipPath=null;
  if(shape==='round'){
    const r=Math.min(w,h)*0.12;
    obj.clipPath=new fabric.Rect({width:w,height:h,rx:r,ry:r,originX:'center',originY:'center'});
  } else if(shape==='circle'){
    obj.clipPath=new fabric.Circle({radius:Math.min(w,h)/2,originX:'center',originY:'center'});
  } else if(shape==='ellipse'){
    obj.clipPath=new fabric.Ellipse({rx:w/2,ry:h/2,originX:'center',originY:'center'});
  }
  // Border via stroke
  const bw=obj.photoBorderW||0, bc=obj.photoBorderColor||'#000000';
  obj.set({stroke:bw>0?bc:null,strokeWidth:bw,paintFirst:'stroke'});
}
function setPhotoShape(shape){
  const obj=SA.canvas.getActiveObject(); if(!obj) return;
  obj.photoShape=shape;
  applyPhotoShapeVisual(obj,shape);
  ['rect','round','circle','ellipse'].forEach(s=>document.getElementById('psh'+s[0].toUpperCase()+s.slice(1))?.classList.toggle('on',s===shape));
  SA.canvas.renderAll(); saveHist();
}
function setPhotoBorder(){
  const obj=SA.canvas.getActiveObject(); if(!obj) return;
  obj.photoBorderW=+document.getElementById('pBorderW').value;
  obj.photoBorderColor=document.getElementById('pBorderC').value;
  applyPhotoShapeVisual(obj,obj.photoShape||'rect');
  SA.canvas.renderAll(); saveHist();
}

/* ── Live data preview (non-destructive) ── */
function prevRow(dir){
  const max=SA.excel.rows.length;
  SA.previewRow=Math.max(0,Math.min(max-1,SA.previewRow+dir));
  document.getElementById('prevRowLabel').textContent=`Row ${SA.previewRow+1}${max>0?' / '+max:''}`;
  if(SA.previewMode){ clearPreviewData(); applyPreviewData(); }
}
function togglePreviewMode(){
  SA.previewMode=!SA.previewMode;
  document.getElementById('prevModeBtn').classList.toggle('on',SA.previewMode);
  SA.previewMode?applyPreviewData():clearPreviewData();
}
function applyPreviewData(){
  const row=SA.excel.rows[SA.previewRow]||{};
  // Pass 1: text fields
  SA.canvas.getObjects().forEach(obj=>{
    if(obj.eleType==='field'&&obj.fieldName){
      if(obj._origText===undefined) obj._origText=obj.text;
      obj.set('text',String(row[obj.fieldName]??''));
    }
  });
  // Pass 2: photo — swap the HTML element in-place (no overlay, no visibility tricks)
  SA.canvas.getObjects().filter(o=>o.eleType==='photo').forEach(obj=>{
    const url=resolveRowPhoto(row);
    if(!url) return;
    const bw=obj.getScaledWidth(), bh=obj.getScaledHeight();
    // Save original element once
    if(!obj._origEl){ obj._origEl=obj._element; obj._origScX=obj.scaleX; obj._origScY=obj.scaleY; }
    const htmlImg=new Image();
    htmlImg.onload=()=>{
      obj.setElement(htmlImg);
      obj.set({scaleX:bw/htmlImg.naturalWidth, scaleY:bh/htmlImg.naturalHeight});
      applyPhotoShapeVisual(obj, obj.photoShape||'rect');
      obj.dirty=true; obj.setCoords(); SA.canvas.requestRenderAll();
    };
    htmlImg.src=url;
  });
  // Pass 3: QR — swap element in-place
  SA.canvas.getObjects().filter(o=>o.eleType==='qr'&&o.type==='image').forEach(obj=>{
    const qv=SA.project.qrFields.length?SA.project.qrFields.map(f=>String(row[f]||'')).join('\n').trim()||'QR':String(Object.values(row)[0]||'QR');
    const bw=obj.getScaledWidth(), bh=obj.getScaledHeight();
    if(!obj._origEl){ obj._origEl=obj._element; obj._origScX=obj.scaleX; obj._origScY=obj.scaleY; }
    const sz=Math.round(Math.max(bw,bh,40));
    _makeQRImg(qv,sz,newQR=>{
      const el=newQR&&newQR._element?newQR._element:null;
      if(!el) return;
      obj.setElement(el);
      obj.set({scaleX:bw/el.width, scaleY:bh/el.height});
      obj.setCoords(); SA.canvas.renderAll();
    });
  });
  SA.canvas.renderAll();
}
function clearPreviewData(){
  // Restore photo elements to placeholder
  SA.canvas.getObjects().filter(o=>o.eleType==='photo'&&o._origEl).forEach(obj=>{
    obj.setElement(obj._origEl);
    obj.set({scaleX:obj._origScX, scaleY:obj._origScY});
    applyPhotoShapeVisual(obj, obj.photoShape||'rect');
    obj.setCoords();
    delete obj._origEl; delete obj._origScX; delete obj._origScY;
  });
  // Restore QR elements
  SA.canvas.getObjects().filter(o=>o.eleType==='qr'&&o._origEl).forEach(obj=>{
    obj.setElement(obj._origEl);
    obj.set({scaleX:obj._origScX, scaleY:obj._origScY});
    obj.setCoords();
    delete obj._origEl; delete obj._origScX; delete obj._origScY;
  });
  // Restore text fields
  SA.canvas.getObjects().forEach(obj=>{
    if(obj.eleType==='field'&&obj._origText!==undefined){
      obj.set('text',obj._origText); delete obj._origText;
    }
  });
  SA.canvas.renderAll();
}

/* ── Side switch ── */
function switchSide(side){
  if(SA.previewMode){togglePreviewMode();}
  if(SA.canvas) SA.project[SA.curSide==='front'?'frontJSON':'backJSON']=SA.canvas.toJSON(['eleType','fieldName','photoShape','photoBorderW','photoBorderColor']);
  SA.curSide=side;
  const tf=document.getElementById('tabFront'), tb=document.getElementById('tabBack');
  if(tf&&tb){
    tf.style.background=side==='front'?'var(--p)':'#fff'; tf.style.color=side==='front'?'#fff':'#94a3b8'; tf.style.borderColor=side==='front'?'var(--p)':'#e2e8f0';
    tb.style.background=side==='back'?'var(--p)':'#fff';  tb.style.color=side==='back'?'#fff':'#94a3b8';  tb.style.borderColor=side==='back'?'var(--p)':'#e2e8f0';
  }
  const json=SA.project[side+'JSON'];
  const bgURL=SA.project[side+'BgURL']||null;
  SA.canvas.clear();
  SA.canvas.setBackgroundColor(SA.project.bgColor,()=>{});
  if(bgURL) fabric.Image.fromURL(bgURL,img=>{SA.canvas.setBackgroundImage(img,SA.canvas.renderAll.bind(SA.canvas),{scaleX:SA.dispW/img.width,scaleY:SA.dispH/img.height});});
  if(json) SA.canvas.loadFromJSON(json,()=>SA.canvas.renderAll(),(o,obj)=>{obj.eleType=o.eleType;obj.fieldName=o.fieldName;if(o.photoShape){obj.photoShape=o.photoShape;obj.photoBorderW=o.photoBorderW;obj.photoBorderColor=o.photoBorderColor;}});
  buildLayerList();
}

/* ── Snap grid ── */
function toggleGrid(){
  SA.snapGrid=!SA.snapGrid;
  document.getElementById('gridBtn').classList.toggle('on',SA.snapGrid);
  toast(SA.snapGrid?'Snap grid ON':'Snap grid OFF','info');
}

/* ── Undo / Redo ── */
function saveHist(){
  if(!SA.canvas) return;
  if(SA.previewMode) return; // don't save history during preview
  const json=SA.canvas.toJSON(['eleType','fieldName','photoShape','photoBorderW','photoBorderColor']);
  SA.history.splice(SA.histIdx+1);
  SA.history.push(json);
  if(SA.history.length>50) SA.history.shift();
  SA.histIdx=SA.history.length-1;
}
function undoC(){ if(SA.histIdx<=0) return; SA.histIdx--; loadHist(); }
function redoC(){ if(SA.histIdx>=SA.history.length-1) return; SA.histIdx++; loadHist(); }
function loadHist(){
  const json=SA.history[SA.histIdx];
  SA.canvas.loadFromJSON(json,()=>{ SA.canvas.renderAll(); buildLayerList(); showProp('empty'); },(o,obj)=>{obj.eleType=o.eleType;obj.fieldName=o.fieldName;if(o.photoShape){obj.photoShape=o.photoShape;obj.photoBorderW=o.photoBorderW;obj.photoBorderColor=o.photoBorderColor;}});
}

/* ══════════════════════════════════════
   STEP 5 — GENERATE
══════════════════════════════════════ */
const PAPER_SZ={
  A4:    {w:8.27,  h:11.69},
  A3:    {w:11.69, h:16.54},
  A5:    {w:5.83,  h:8.27},
  Letter:{w:8.5,   h:11},
  '4x6': {w:4,     h:6},
  '5x7': {w:5,     h:7},
};

function setGenOr(or,el){
  // HTML uses id="goPort" / id="goLand" with onclick passing this
  document.getElementById('goPort')?.classList.remove('on');
  document.getElementById('goLand')?.classList.remove('on');
  el.classList.add('on');
  SA.gen.orientation=or;
  updateGenLayout();
}
function setFmt(fmt,el){
  SA.gen.format=fmt;
  document.querySelectorAll('.fmt-btn').forEach(x=>x.classList.remove('on'));
  if(el) el.classList.add('on');
  // Show quality slider for image-based formats
  const qRow=document.getElementById('qualityRow');
  if(qRow) qRow.style.display=(fmt==='png'||fmt==='zip')?'':'none';
}
function syncMargins(v){
  const val=parseFloat(v)||0;
  ['mTop','mBot','mLeft','mRight'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value=val; });
}
function syncGaps(v){
  const val=parseFloat(v)||0;
  ['gapX','gapY','gapAll'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value=val; });
}
function applyCutZone(mmVal){
  const v=parseFloat(mmVal)||0;
  const inVal=+(v/25.4).toFixed(4);
  syncGaps(inVal);
}
function _setCutZone(mm){
  const el=document.getElementById('cutZoneMM');
  const lbl=document.getElementById('cutZoneV');
  if(el){el.value=mm;if(lbl)lbl.textContent=mm+' mm';}
  applyCutZone(mm);
}
function applyGenPreset(type){
  const paperSel=document.getElementById('genPaper');
  const mAllEl=document.getElementById('mAll');
  const scaleEl=document.getElementById('cardScale');
  const scaleV=document.getElementById('cardScaleV');
  const ecW=(SA.project.cardW||3.375)*(SA.gen.cardScale||1);
  const ecH=(SA.project.cardH||2.125)*(SA.gen.cardScale||1);

  if(type==='auto'){ autoLayout(); return; }

  if(type==='10up'){
    // Warn if card is not ID-card sized
    if(ecW > 4 || ecH > 3){
      toast(`10-up preset is for small ID cards (yours: ${(ecW*2.54).toFixed(1)}×${(ecH*2.54).toFixed(1)} cm). Using Auto instead.`,'warn');
      autoLayout(); return;
    }
    if(paperSel) paperSel.value='A4';
    SA.gen.orientation='portrait';
    document.getElementById('goPort')?.classList.add('on');
    document.getElementById('goLand')?.classList.remove('on');
    if(mAllEl){mAllEl.value='0.2';syncMargins('0.2');}
    _setCutZone(3);
    if(scaleEl){scaleEl.value=100;if(scaleV)scaleV.textContent='100%';SA.gen.cardScale=1;}

  } else if(type==='8up'){
    if(ecW > 4 || ecH > 3){
      toast(`8-up preset is for small ID cards. Using Auto instead.`,'warn');
      autoLayout(); return;
    }
    if(paperSel) paperSel.value='A4';
    SA.gen.orientation='landscape';
    document.getElementById('goPort')?.classList.remove('on');
    document.getElementById('goLand')?.classList.add('on');
    if(mAllEl){mAllEl.value='0.25';syncMargins('0.25');}
    _setCutZone(3);
    if(scaleEl){scaleEl.value=100;if(scaleV)scaleV.textContent='100%';SA.gen.cardScale=1;}

  } else if(type==='individual'){
    // Find the smallest paper that fits this card with no margin
    let bestPaper='A4';
    for(const [pk,pb] of Object.entries(PAPER_SZ)){
      const pFit=(pb.w>=ecW-0.01&&pb.h>=ecH-0.01)||(pb.h>=ecW-0.01&&pb.w>=ecH-0.01);
      if(!pFit) continue;
      const pbArea=pb.w*pb.h;
      const bpArea=(PAPER_SZ[bestPaper]||PAPER_SZ.A4);
      if(pbArea<bpArea.w*bpArea.h) bestPaper=pk;
    }
    if(paperSel) paperSel.value=bestPaper;
    SA.gen.orientation=ecW>ecH?'landscape':'portrait';
    document.getElementById('goPort')?.classList.toggle('on',SA.gen.orientation==='portrait');
    document.getElementById('goLand')?.classList.toggle('on',SA.gen.orientation==='landscape');
    if(mAllEl){mAllEl.value='0';syncMargins('0');}
    _setCutZone(0);
    if(scaleEl){scaleEl.value=100;if(scaleV)scaleV.textContent='100%';SA.gen.cardScale=1;}
  }
  updateGenLayout();
  toast(`Preset applied`,'ok');
}
/* ── Smart Layout Engine ──────────────────────────────────────────────
   Algorithm:
   1. Read ACTUAL card size from SA.project (set in Step 3)
   2. Use SELECTED paper only — never switch papers
   3. Try portrait vs landscape — pick whichever fits more
   4. If card fills > 70% of paper in any dimension, that axis = 1
   5. Show comparison hint (what other papers could give)
──────────────────────────────────────────────────────────────────── */
function _smartFit(ecW, ecH, pw, ph, margin, cutIn){
  const uw = pw - margin * 2;  // usable width
  const uh = ph - margin * 2;  // usable height
  // If card is bigger than usable area in either axis, return null (doesn't fit)
  if(ecW > uw * 1.005 || ecH > uh * 1.005) return null;
  const cols = Math.max(1, Math.floor((uw + cutIn) / (ecW + cutIn)));
  const rows = Math.max(1, Math.floor((uh + cutIn) / (ecH + cutIn)));
  // Paper coverage %
  const usedW = cols * ecW + (cols - 1) * cutIn;
  const usedH = rows * ecH + (rows - 1) * cutIn;
  const coverage = Math.round(usedW * usedH / (uw * uh) * 100);
  return { cols, rows, count: cols * rows, coverage };
}

function _cardSizeLabel(ecW, ecH){
  const wCm = ecW * 2.54, hCm = ecH * 2.54;
  // Match against known paper sizes (within 5%)
  for(const [pk, pb] of Object.entries(PAPER_SZ)){
    const matchP = Math.abs(ecW - pb.w) < pb.w * 0.05 && Math.abs(ecH - pb.h) < pb.h * 0.05;
    const matchL = Math.abs(ecW - pb.h) < pb.h * 0.05 && Math.abs(ecH - pb.w) < pb.w * 0.05;
    if(matchP) return `${pk} Portrait (${wCm.toFixed(1)}×${hCm.toFixed(1)} cm)`;
    if(matchL) return `${pk} Landscape (${wCm.toFixed(1)}×${hCm.toFixed(1)} cm)`;
  }
  // Generic labels
  const area = ecW * ecH;
  if(area > 60) return `Large sheet (${wCm.toFixed(1)}×${hCm.toFixed(1)} cm)`;
  if(area > 20) return `Half-page (${wCm.toFixed(1)}×${hCm.toFixed(1)} cm)`;
  if(area > 6)  return `Large card (${wCm.toFixed(1)}×${hCm.toFixed(1)} cm)`;
  return `ID card (${wCm.toFixed(1)}×${hCm.toFixed(1)} cm)`;
}

function autoLayout(){
  // Step 1: Actual card dimensions (from Step 3 designer setup)
  const ecW = (SA.project.cardW || 3.375) * (SA.gen.cardScale || 1.0);
  const ecH = (SA.project.cardH || 2.125) * (SA.gen.cardScale || 1.0);

  // Step 2: Layout params
  const cutMM = parseFloat(document.getElementById('cutZoneMM')?.value) || 3;
  const cutIn = cutMM / 25.4;
  const margin = parseFloat(document.getElementById('mAll')?.value) || 0.2;

  // Step 3: Use SELECTED paper — do NOT switch papers
  const selectedPaper = document.getElementById('genPaper')?.value || 'A4';
  const pBase = PAPER_SZ[selectedPaper] || PAPER_SZ.A4;

  // Step 4: Try both orientations for this paper
  const fitPort = _smartFit(ecW, ecH, pBase.w, pBase.h, margin, cutIn);
  const fitLand = _smartFit(ecW, ecH, pBase.h, pBase.w, margin, cutIn);

  // Step 5: Pick orientation with more cards per sheet
  let bestOr, bestFit;
  const pCount = fitPort ? fitPort.count : 0;
  const lCount = fitLand ? fitLand.count : 0;

  if(!fitPort && !fitLand){
    // Card doesn't fit with current margins — force 1-up and remove margin
    toast(`Card larger than printable area — switching to 1-up with 0 margin`, 'warn');
    const mEl = document.getElementById('mAll');
    if(mEl){ mEl.value='0'; syncMargins(0); }
    bestOr = ecW >= ecH ? 'landscape' : 'portrait';
    bestFit = {cols:1, rows:1, count:1, coverage:100};
  } else if(lCount > pCount){
    bestOr = 'landscape'; bestFit = fitLand;
  } else if(pCount > lCount){
    bestOr = 'portrait'; bestFit = fitPort;
  } else {
    // Tie: match card aspect ratio to paper aspect ratio
    const cardAR = ecW / ecH;
    const portAR = pBase.w / pBase.h;
    bestOr = Math.abs(cardAR - portAR) < Math.abs(cardAR - (pBase.h/pBase.w)) ? 'portrait' : 'landscape';
    bestFit = fitPort || fitLand;
  }

  // Step 6: Apply orientation
  SA.gen.orientation = bestOr;
  document.getElementById('goPort')?.classList.toggle('on', bestOr === 'portrait');
  document.getElementById('goLand')?.classList.toggle('on', bestOr === 'landscape');

  // Step 7: Build comparison hints for other papers
  const alts = [];
  for(const [pk, pb] of Object.entries(PAPER_SZ)){
    if(pk === selectedPaper) continue;
    const fP = _smartFit(ecW, ecH, pb.w, pb.h, margin, cutIn);
    const fL = _smartFit(ecW, ecH, pb.h, pb.w, margin, cutIn);
    const best2 = (fL && (!fP || fL.count > fP.count)) ? fL : fP;
    if(best2 && best2.count > bestFit.count) alts.push(`${pk}:${best2.count}`);
  }
  const altTip = alts.length ? ` | Better on: ${alts.sort((a,b)=>parseInt(b.split(':')[1])-parseInt(a.split(':')[1])).slice(0,2).join(', ')}` : '';

  // Step 8: Show result
  const sizeLabel = _cardSizeLabel(ecW, ecH);
  const perSheet = bestFit.cols * bestFit.rows;
  const totalCards = SA.excel.rows.length || 0;
  const sheets = totalCards > 0 ? Math.ceil(totalCards / perSheet) : '-';
  const note = document.getElementById('autoLayoutNote');
  if(note){
    note.style.display = '';
    note.innerHTML = `✓ <b>${sizeLabel}</b> → ${bestFit.cols}×${bestFit.rows} on ${selectedPaper} ${bestOr} = <b>${perSheet}/sheet</b> · ${sheets} sheet(s) · ${bestFit.coverage}% coverage${altTip}`;
  }
  updateGenLayout();
  toast(`${sizeLabel} → ${perSheet}/sheet on ${selectedPaper} (${bestOr})`, 'ok');
}
function _getLayoutParams(){
  const pKey=document.getElementById('genPaper')?.value||'A4';
  const or=SA.gen.orientation||'portrait';
  const pBase=PAPER_SZ[pKey]||PAPER_SZ.A4;
  let pw=pBase.w, ph=pBase.h;
  if(or==='landscape'){[pw,ph]=[ph,pw];}
  const sc=parseFloat(document.getElementById('cardScale')?.value)||100;
  SA.gen.cardScale=sc/100;
  const mAll=parseFloat(document.getElementById('mAll')?.value)||0.2;
  const mT=parseFloat(document.getElementById('mTop')?.value)||mAll;
  const mB=parseFloat(document.getElementById('mBot')?.value)||mAll;
  const mL=parseFloat(document.getElementById('mLeft')?.value)||mAll;
  const mR=parseFloat(document.getElementById('mRight')?.value)||mAll;
  const gX=parseFloat(document.getElementById('gapX')?.value)||0;
  const gY=parseFloat(document.getElementById('gapY')?.value)||0;
  const cutMM=parseFloat(document.getElementById('cutZoneMM')?.value)||0;
  const cutIn=+(cutMM/25.4).toFixed(4);
  // effective gap = max(user gap, cut zone)
  const egX=Math.max(gX,cutIn), egY=Math.max(gY,cutIn);
  const center=document.getElementById('optCenter')?.checked!==false;
  const cutLines=document.getElementById('optCutLines')?.checked!==false;
  return{pKey,or,pw,ph,mT,mB,mL,mR,gX:egX,gY:egY,sc:SA.gen.cardScale,cutMM,cutIn,center,cutLines};
}
function updateGenLayout(){
  const p=_getLayoutParams();
  const {pKey,or,pw,ph,mT,mB,mL,mR,gX,gY,sc,cutMM,cutIn,center,cutLines}=p;
  SA.gen.paper=pKey; SA.gen.orientation=or;
  const cW=(SA.project.cardW||3.375)*sc, cH=(SA.project.cardH||2.125)*sc;
  // Calculate how many fit — clamped to 1 minimum (never 0)
  const cols=Math.max(1,Math.floor((pw-mL-mR+gX)/(cW+gX)));
  const rows=Math.max(1,Math.floor((ph-mT-mB+gY)/(cH+gY)));
  SA.gen.cols=cols; SA.gen.rows=rows;
  SA.gen.mTop=mT; SA.gen.mBot=mB; SA.gen.mLeft=mL; SA.gen.mRight=mR;
  SA.gen.gapX=gX; SA.gen.gapY=gY; SA.gen.cutMM=cutMM; SA.gen.center=center;
  const perSheet=cols*rows;
  const totalCards=SA.excel.rows.length||0;
  const sheets=totalCards>0?Math.ceil(totalCards/perSheet):1;
  // Centered start
  const totalW=cols*cW+(cols-1)*gX, totalH=rows*cH+(rows-1)*gY;
  const startX=center?Math.max(mL,(pw-totalW)/2):mL;
  const startY=center?Math.max(mT,(ph-totalH)/2):mT;
  // Smart card size label in layout info
  const sLabel=_cardSizeLabel(cW,cH);
  const li=document.getElementById('layoutInfo');
  if(li) li.textContent=`${cols}×${rows} = ${perSheet}/sheet · ${sheets} sheet(s) · cut zone ${cutMM}mm`;
  const sc2=document.getElementById('sheetCount');
  if(sc2) sc2.textContent=totalCards>0?`${totalCards} cards · ${sLabel}`:'No data';
  drawLayoutPreview(pw,ph,startX,startY,mL,mT,gX,gY,cW,cH,cols,rows,cutMM,cutIn,cutLines);
}
function drawLayoutPreview(pw,ph,startX,startY,mL,mT,gX,gY,cW,cH,cols,rows,cutMM,cutIn,cutLines){
  const cvs=document.getElementById('layoutPreviewCanvas');
  if(!cvs) return;
  const maxW=260, ratio=ph/pw;
  cvs.width=maxW; cvs.height=Math.round(maxW*ratio);
  const ctx=cvs.getContext('2d');
  const S=cvs.width/pw;

  // Paper background with subtle shadow
  ctx.shadowColor='rgba(0,0,0,.15)'; ctx.shadowBlur=6; ctx.shadowOffsetX=2; ctx.shadowOffsetY=2;
  ctx.fillStyle='#fff'; ctx.fillRect(0,0,cvs.width,cvs.height);
  ctx.shadowColor='transparent';
  ctx.strokeStyle='#cbd5e1'; ctx.lineWidth=.5; ctx.strokeRect(0,0,cvs.width,cvs.height);

  // Cut zone shading (light red) between cards
  if(cutMM>0){
    ctx.fillStyle='rgba(254,202,202,.35)';
    // Vertical strips between columns
    for(let c=0;c<cols-1;c++){
      const cx=(startX+(c+1)*cW+c*gX)*S;
      ctx.fillRect(cx, startY*S, gX*S, (rows*cH+(rows-1)*gY)*S);
    }
    // Horizontal strips between rows
    for(let r=0;r<rows-1;r++){
      const ry=(startY+(r+1)*cH+r*gY)*S;
      ctx.fillRect(startX*S, ry, (cols*cW+(cols-1)*gX)*S, gY*S);
    }
  }

  // Card cells
  for(let r=0;r<rows;r++){
    for(let c=0;c<cols;c++){
      const x=(startX+c*(cW+gX))*S, y=(startY+r*(cH+gY))*S, w=cW*S, h=cH*S;
      // Card fill with gradient-like look
      const grad=ctx.createLinearGradient(x,y,x,y+h);
      grad.addColorStop(0,'#dbeafe'); grad.addColorStop(1,'#bfdbfe');
      ctx.fillStyle=grad; ctx.fillRect(x,y,w,h);
      ctx.strokeStyle='#2563eb'; ctx.lineWidth=.4; ctx.strokeRect(x,y,w,h);
      // Mini card illustration
      if(w>16&&h>12){
        const ph2=Math.min(w*.38,h*.55), pw2=ph2*.75;
        ctx.fillStyle='#93c5fd'; ctx.fillRect(x+1.5,y+1.5,pw2,ph2);
        ctx.fillStyle='#1e40af';
        for(let li2=0;li2<3;li2++) ctx.fillRect(x+pw2+2.5,y+2+li2*3,Math.min(w-pw2-5,w*.45),1.2);
        // Tiny QR box
        ctx.fillStyle='#e0e7ff'; ctx.fillRect(x+1.5,y+ph2+2,pw2*.6,pw2*.6);
      }
      // Crop mark corners (L-shaped, red)
      if(cutMM>0){
        const mk=Math.min(gX*S*.45, 5, 3.5), gap=Math.min(gX*S*.12, 1);
        ctx.strokeStyle='rgba(220,38,38,.75)'; ctx.lineWidth=.6; ctx.setLineDash([]);
        // TL
        ctx.beginPath(); ctx.moveTo(x-gap-mk,y); ctx.lineTo(x-gap,y); ctx.moveTo(x,y-gap-mk); ctx.lineTo(x,y-gap); ctx.stroke();
        // TR
        ctx.beginPath(); ctx.moveTo(x+w+gap,y); ctx.lineTo(x+w+gap+mk,y); ctx.moveTo(x+w,y-gap-mk); ctx.lineTo(x+w,y-gap); ctx.stroke();
        // BL
        ctx.beginPath(); ctx.moveTo(x-gap-mk,y+h); ctx.lineTo(x-gap,y+h); ctx.moveTo(x,y+h+gap); ctx.lineTo(x,y+h+gap+mk); ctx.stroke();
        // BR
        ctx.beginPath(); ctx.moveTo(x+w+gap,y+h); ctx.lineTo(x+w+gap+mk,y+h); ctx.moveTo(x+w,y+h+gap); ctx.lineTo(x+w,y+h+gap+mk); ctx.stroke();
      }
    }
  }

  // Cut guide dashed lines between cards
  if(cutLines&&(gX>0.005||gY>0.005)){
    ctx.strokeStyle='rgba(220,38,38,.4)'; ctx.lineWidth=.5; ctx.setLineDash([2.5,2]);
    for(let c=1;c<cols;c++){
      const cx=(startX+c*cW+(c-.5)*gX)*S;
      ctx.beginPath(); ctx.moveTo(cx,0); ctx.lineTo(cx,cvs.height); ctx.stroke();
    }
    for(let r=1;r<rows;r++){
      const ry=(startY+r*cH+(r-.5)*gY)*S;
      ctx.beginPath(); ctx.moveTo(0,ry); ctx.lineTo(cvs.width,ry); ctx.stroke();
    }
    ctx.setLineDash([]);
    // Scissor icon at first cut line
    if(cols>1){
      const cx=(startX+cW+gX*.5)*S;
      ctx.fillStyle='rgba(220,38,38,.5)'; ctx.font=`${Math.min(7,gX*S*.8)}px sans-serif`;
      ctx.textAlign='center'; ctx.fillText('✂',cx,8);
    }
  }

  // Printable area guide (dashed border)
  ctx.strokeStyle='rgba(148,163,184,.5)'; ctx.setLineDash([3,3]); ctx.lineWidth=.5;
  ctx.strokeRect(mL*S,mT*S,(pw-mL*2)*S,(ph-mT*2)*S);
  ctx.setLineDash([]);

  // Paper size label
  const pKey=document.getElementById('genPaper')?.value||'A4';
  ctx.fillStyle='rgba(100,116,139,.55)'; ctx.font='bold 7px Arial'; ctx.textAlign='right';
  ctx.fillText(pKey, cvs.width-2, cvs.height-2);
}

/* ── Per-card renderer ──
   side: 'front' (default) or 'back' — previously hardcoded to frontJSON/
   frontBgURL, so a double-sided card's Back design (fully saved, per-side
   background and all) was silently unreachable at generation time: no
   caller ever passed anything else in, and there was no parameter to pass.
   The actual output always contained the front side only, regardless of
   how the card was designed, on every device. */
async function renderCardSM(rowIdx,side){
  side=side||'front';
  return new Promise(resolve=>{
    const row=SA.excel.rows[rowIdx]||{};
    const sc=SA.gen.cardScale||1.0;
    const dpi=SA.project.dpi||300;
    const cW=SA.project.cardW||3.375, cH=SA.project.cardH||2.125;
    const expW=Math.round(cW*dpi*sc);
    const offCvs=new fabric.StaticCanvas(null,{width:SA.dispW,height:SA.dispH,enableRetinaScaling:false});
    const mult=expW/SA.dispW;
    const json=SA.project[side+'JSON'];
    const bgURL=SA.project[side+'BgURL'];
    const doRender=()=>{
      if(json){
        offCvs.loadFromJSON(json,()=>{
          offCvs.getObjects().forEach(obj=>{
            if(obj.eleType==='field'&&obj.fieldName) obj.set('text',String(row[obj.fieldName]??''));
          });
          renderSpecialEls(offCvs,row,()=>{
            offCvs.renderAll();
            resolve(offCvs.toDataURL({format:'jpeg',quality:SA.gen.quality||0.92,multiplier:mult}));
            offCvs.dispose();
          },rowIdx);
        },(o,obj)=>{obj.eleType=o.eleType;obj.fieldName=o.fieldName;obj.photoShape=o.photoShape;obj.photoBorderW=o.photoBorderW;obj.photoBorderColor=o.photoBorderColor;});
      } else { resolve(offCvs.toDataURL({format:'jpeg',quality:.92,multiplier:mult})); offCvs.dispose(); }
    };
    if(bgURL){
      fabric.Image.fromURL(bgURL,bgImg=>{
        offCvs.setBackgroundImage(bgImg,doRender,{scaleX:SA.dispW/bgImg.width,scaleY:SA.dispH/bgImg.height});
      },{crossOrigin:'anonymous'});
    } else {
      offCvs.setBackgroundColor(SA.project.bgColor||'#ffffff',doRender);
    }
  });
}
function renderSpecialEls(canvas,row,done,rowIdx){
  const objs=[...canvas.getObjects()];
  let pending=0;
  const check=()=>{if(--pending<=0)done();};
  objs.forEach(obj=>{
    if(obj.eleType==='photo'){
      const u=resolveRowPhoto(row,rowIdx);
      if(u){pending++;fabric.Image.fromURL(u,img=>{
        const bw=obj.getScaledWidth(),bh=obj.getScaledHeight();
        // "Cover" fit (like CSS object-fit:cover) instead of stretching the
        // photo to exactly bw×bh: a real photo's aspect ratio almost never
        // matches the box's, so forcing scaleX≠scaleY squashed/stretched
        // every photo, and on top of that the shape clip (esp. circle,
        // whose diameter = the box's SMALLER side) then cut into that
        // already-distorted image. Scaling uniformly to fully cover the box
        // and centering the overflow removes the stretch — the circle/round
        // clip still has to crop *something* off a non-square box (that's
        // unavoidable; make the Photo box closer to square for less of it),
        // but what's left on screen is a true, undistorted crop of the
        // original photo instead of a squashed one.
        const cover=Math.max(bw/img.width,bh/img.height);
        const cw=img.width*cover, ch=img.height*cover;
        img.set({left:obj.left+(bw-cw)/2,top:obj.top+(bh-ch)/2,scaleX:cover,scaleY:cover});
        applyPhotoClip(img,obj.photoShape||'rect',obj.left,obj.top,bw,bh,obj.photoBorderW||0,obj.photoBorderColor||'#000');
        canvas.remove(obj);canvas.add(img);check();
      });}
    } else if(obj.eleType==='sign'){
      const v=SA.project.signCol?row[SA.project.signCol]:null;
      const u=getSignURL(v);
      if(u){pending++;fabric.Image.fromURL(u,img=>{img.scaleToWidth(obj.getScaledWidth());img.set({left:obj.left,top:obj.top});canvas.remove(obj);canvas.add(img);check();},{crossOrigin:'anonymous'});}
    } else if(obj.eleType==='qr'){
      pending++;
      const qv=SA.project.qrFields.length?SA.project.qrFields.map(f=>String(row[f]||'')).join('\n').trim()||'QR':String(Object.values(row)[0]||'QR');
      const qsz=Math.round(Math.min(obj.getScaledWidth(),obj.getScaledHeight()));
      _makeQRImg(qv,qsz,qi=>{qi.set({left:obj.left,top:obj.top,scaleX:obj.getScaledWidth()/qi.width,scaleY:obj.getScaledHeight()/qi.height});canvas.remove(obj);canvas.add(qi);check();});
    } else if(obj.eleType==='barcode'){
      pending++;
      const bf=SA.project.barcodeField;
      const bv=bf?String(row[bf]||'123456'):String(Object.values(row)[0]||'123456');
      renderBarFabric(canvas,bv,obj,SA.project.barcodeType||'CODE128',check);
    }
  });
  if(!pending)done();
}
function applyPhotoClip(img,shape,boxLeft,boxTop,bw,bh,bW,bC){
  // Clip is centered on the BOX's own position, not the (now cover-fit,
  // possibly larger/offset) image's position — those only coincided before
  // when the photo was stretched to exactly bw×bh.
  const cx=boxLeft+bw/2, cy=boxTop+bh/2;
  if(shape==='circle'){
    img.clipPath=new fabric.Circle({radius:Math.min(bw,bh)/2,originX:'center',originY:'center',left:cx,top:cy,absolutePositioned:true});
  } else if(shape==='round'){
    const r=Math.min(bw,bh)*0.12;
    img.clipPath=new fabric.Rect({width:bw,height:bh,rx:r,ry:r,originX:'center',originY:'center',left:cx,top:cy,absolutePositioned:true});
  } else if(shape==='ellipse'){
    img.clipPath=new fabric.Ellipse({rx:bw/2,ry:bh/2,originX:'center',originY:'center',left:cx,top:cy,absolutePositioned:true});
  }
  if(bW>0) img.set({stroke:bC,strokeWidth:bW});
}
function renderQRFabric(canvas,val,refObj,cb){
  try{
    const tmp=document.createElement('div'); tmp.style.display='none'; document.body.appendChild(tmp);
    const sz=Math.round(Math.min(refObj.getScaledWidth(),refObj.getScaledHeight()));
    new QRCode(tmp,{text:val,width:sz,height:sz,correctLevel:QRCode.CorrectLevel.M});
    setTimeout(()=>{
      const el=tmp.querySelector('canvas')||tmp.querySelector('img');
      const src=el?(el.src||el.toDataURL()):null;
      document.body.removeChild(tmp);
      if(!src){cb();return;}
      fabric.Image.fromURL(src,qi=>{qi.set({left:refObj.left,top:refObj.top});qi.scaleToWidth(refObj.getScaledWidth());canvas.remove(refObj);canvas.add(qi);cb();});
    },120);
  }catch(e){cb();}
}
function renderBarFabric(canvas,val,refObj,fmt,cb){
  try{
    const tmp=document.createElement('canvas');
    JsBarcode(tmp,val,{format:fmt,width:2,height:Math.round(refObj.getScaledHeight()*.7),fontSize:9,lineColor:'#000',background:'rgba(0,0,0,0)',margin:2,displayValue:true});
    fabric.Image.fromURL(tmp.toDataURL(),bi=>{bi.set({left:refObj.left,top:refObj.top});bi.scaleToWidth(refObj.getScaledWidth());canvas.remove(refObj);canvas.add(bi);cb();});
  }catch(e){cb();}
}
function _stripExt(s){ return String(s).trim().replace(/\.[^.]+$/,'').toLowerCase(); }
function getPhotoURL(val){
  if(!val&&val!==0) return null;
  const v=String(val).trim();
  if(SA.photos.has(v)) return SA.photos.get(v);
  const vn=_stripExt(v);
  for(const [k,u] of SA.photos){ if(_stripExt(k)===vn) return u; }
  // Numbers: sheet says 1 but the file is 01.jpg (or the reverse) — compare them as numbers.
  if(/^\d+$/.test(vn)){
    const n=parseInt(vn,10);
    for(const [k,u] of SA.photos){ const kb=_stripExt(k); if(/^\d+$/.test(kb)&&parseInt(kb,10)===n) return u; }
  }
  return null;
}
function getSignURL(val){
  if(!val&&val!==0) return null;
  const v=String(val).trim();
  if(SA.signs.has(v)) return SA.signs.get(v);
  const vn=_stripExt(v);
  for(const [k,u] of SA.signs){ if(_stripExt(k)===vn) return u; }
  return null;
}
/* Resolve photo for a row — tries photoCol, first column, any column, then index fallback */
function resolveRowPhoto(row,rowIdx){
  // Rows from Data Collection carry their own record id (__rid): the photo belongs to THAT record,
  // never to a name (many students share one) and never to a "cycled" fallback photo. A record with
  // no photo gets none.
  if(row && row.__rid!==undefined && SA.dcPhotos) return SA.dcPhotos.get(String(row.__rid))||null;
  // The rule: the sheet's FIRST column (e.g. "No" / "Roll No") is matched with the photo FILE NAME
  // (No 5 → 5.jpg). If the user marked a specific Photo/Image column, that one is tried first.
  // Nothing else is guessed: no scanning of other columns and no "cycling" through the photos —
  // a row with no matching photo gets NO photo instead of somebody else's.
  if(SA.project.photoCol){
    const u=getPhotoURL(row[SA.project.photoCol]);
    if(u) return u;
  }
  const firstKey=SA.excel.headers[0];
  if(firstKey){
    const u=getPhotoURL(row[firstKey]);
    if(u) return u;
  }
  return null;
}

/* ── Main generation trigger ── */
async function startGeneration(){
  if(!SA.project.frontJSON&&!SA.project.backJSON&&!SA.canvas){ toast('Please complete the designer step first','warn'); return; }
  // Clear Preview BEFORE snapshotting the canvas, not after — Preview
  // substitutes one specific row's real text into each field (so its
  // Textbox re-wraps/re-sizes against THAT row's content), and the snapshot
  // taken right here is exactly what every card in the export gets rebuilt
  // from. Capturing it while Preview was still showing a substituted value
  // baked that one row's field state into the template for every card;
  // clearing first guarantees the snapshot always reflects the actual
  // placeholder design, not whatever happened to be on screen last.
  if(SA.previewMode) clearPreviewData();
  if(SA.canvas) SA.project[SA.curSide+'JSON']=SA.canvas.toJSON(['eleType','fieldName','photoShape','photoBorderW','photoBorderColor']);
  // Print-ready formats (PDF Sheets / PDF per Card) must never go out below
  // 300 DPI — "150 Draft" in the Card Size step is explicitly meant for a
  // fast on-screen check, not for something that ends up printed, so a PDF
  // export silently upgrades to at least 300 regardless of what was picked
  // earlier. PNG/ZIP outputs are left at the user's chosen DPI since those
  // are as likely to be used on-screen as printed.
  const _fmtForDpi=SA.gen.format||'pdf';
  if((_fmtForDpi==='pdf'||_fmtForDpi==='pdfzip')&&(SA.project.dpi||300)<300){
    SA.project.dpi=300;
    toast('Print quality: using 300 DPI for the PDF output','info');
  }
  // Respect "first card only" test mode
  const firstOnly=document.getElementById('firstOnly')?.checked;
  const totalCards=firstOnly?Math.min(1,SA.excel.rows.length||1):Math.max(SA.excel.rows.length,1);
  const btn=document.getElementById('genBtn');
  btn.disabled=true; btn.innerHTML='<i class="bi bi-hourglass-split"></i> Generating…';
  // HTML uses id="genProgressArea" and id="genProgressTxt"
  const prog=document.getElementById('genProgressArea');
  prog?.classList.remove('hidden'); setProgress(0);
  const ptxt=document.getElementById('genProgressTxt');
  if(ptxt) ptxt.textContent='Starting…';
  // Double-sided: render the Back side's cards too. backJSON is only ever
  // populated once the user has actually opened/edited the Back tab in the
  // Designer — an empty/never-visited Back is treated as "no back content"
  // rather than generating blank-card backs nobody asked for.
  const isDouble=SA.project.sides==='double'&&!!SA.project.backJSON;
  try{
    const cardURLs=[];
    const totalUnits=isDouble?totalCards*2:totalCards;
    for(let i=0;i<totalCards;i++){
      setProgress(Math.round((i/totalUnits)*72));
      if(ptxt) ptxt.textContent=`Rendering front ${i+1} of ${totalCards}…`;
      cardURLs.push(await renderCardSM(i,'front'));
      await new Promise(r=>setTimeout(r,0));
    }
    const backURLs=[];
    if(isDouble){
      for(let i=0;i<totalCards;i++){
        setProgress(Math.round(((totalCards+i)/totalUnits)*72));
        if(ptxt) ptxt.textContent=`Rendering back ${i+1} of ${totalCards}…`;
        backURLs.push(await renderCardSM(i,'back'));
        await new Promise(r=>setTimeout(r,0));
      }
    }
    SA.gen.outURLs=cardURLs;
    SA.gen.outBackURLs=backURLs;
    setProgress(80);
    if(ptxt) ptxt.textContent='Building output file…';
    const fmt=SA.gen.format||'pdf';
    let outURL, outName;
    if(fmt==='pdf'){     outURL=await buildPDFSheets(cardURLs,backURLs);    outName='SmartIDs_sheets.pdf'; }
    else if(fmt==='png'){outURL=await buildImageSheets(cardURLs,backURLs);  outName='SmartIDs_sheets.zip'; }
    else if(fmt==='pdfzip'){outURL=await buildPDFZip(cardURLs,backURLs);   outName='SmartIDs_individual.zip'; }
    else {               outURL=await buildZIPOutput(cardURLs,backURLs);    outName='SmartIDs_cards.zip'; }
    setProgress(100);
    if(ptxt) ptxt.textContent='Done!';
    showDownload(outURL,outName,fmt,cardURLs);
    // Track smart generation
    try{const _fd=new FormData();_fd.append('action','record');_fd.append('csrf_token','<?= $_sc ?>');_fd.append('action_type','smart_generate');_fd.append('panel','icard');_fd.append('count',cardURLs.length);
      (window.smartFetch?window.smartFetch('<?= $_sa ?>',_fd,{label:'Usage stats: '+cardURLs.length+' cards generated',panel:'smart_generate'}):fetch('<?= $_sa ?>',{method:'POST',body:_fd})).catch(()=>{});}catch(e){}
  }catch(err){
    toast('Generation error: '+err.message,'error');
    console.error(err);
  }finally{
    btn.disabled=false; btn.innerHTML='<i class="bi bi-lightning-charge-fill"></i> Generate All Cards';
    setTimeout(()=>prog?.classList.add('hidden'),3500);
  }
}
async function buildPDFSheets(cardURLs,backURLs){
  const {jsPDF}=window.jspdf;
  const p=_getLayoutParams();
  const {pKey,or,pw,ph,mT,mB,mL,mR,gX,gY,sc,cutMM,center,cutLines}=p;
  const IN2MM=25.4;
  const pdf=new jsPDF({orientation:or,unit:'mm',format:[pw*IN2MM,ph*IN2MM]});
  const cW=(SA.project.cardW||3.375)*sc, cH=(SA.project.cardH||2.125)*sc;
  const cols=SA.gen.cols||Math.max(1,Math.floor((pw-mL-mR+gX)/(cW+gX)));
  const rows=SA.gen.rows||Math.max(1,Math.floor((ph-mT-mB+gY)/(cH+gY)));
  // Centered start position
  const totalW=cols*cW+(cols-1)*gX, totalH=rows*cH+(rows-1)*gY;
  const startX=center?Math.max(mL,(pw-totalW)/2):mL;
  const startY=center?Math.max(mT,(ph-totalH)/2):mT;
  const doCropMarks=cutMM>0;
  const doGuideLines=cutLines&&(gX>0.04||gY>0.04); // at least ~1mm gap
  // Crop mark geometry (in mm)
  const CM=3.5;        // mark length
  const CMg=0.8;       // gap between mark and card edge
  const PW=pw*IN2MM, PH=ph*IN2MM; // page size in mm

  const safeLine=(x1,y1,x2,y2)=>{
    if(x1<-2||y1<-2||x2>PW+2||y2>PH+2) return; // skip marks outside page
    pdf.line(Math.max(-1,x1),Math.max(-1,y1),Math.min(PW+1,x2),Math.min(PH+1,y2));
  };

  // Draws ONE sheet's worth of cards (starting at urls[startIdx]) onto
  // whatever page is currently active in pdf, returns the next startIdx.
  const cardsPerSheet=cols*rows;
  const drawSheet=(urls,startIdx)=>{
    let i=startIdx;
    const pageCards=[];
    for(let r=0;r<rows&&i<urls.length;r++){
      for(let c=0;c<cols&&i<urls.length;c++){
        const x=(startX+c*(cW+gX))*IN2MM;
        const y=(startY+r*(cH+gY))*IN2MM;
        const W=cW*IN2MM, H=cH*IN2MM;
        pdf.addImage(urls[i],'JPEG',x,y,W,H,undefined,'FAST');
        pageCards.push({x,y,W,H});
        i++;
      }
    }
    if(doCropMarks){
      pdf.setDrawColor(120,120,120); pdf.setLineWidth(0.15);
      pdf.setLineDashPattern([],0);
      for(const {x,y,W,H} of pageCards){
        const ex=x+W, ey=y+H;
        safeLine(x-CMg-CM,y,  x-CMg,y);
        safeLine(x,y-CMg-CM,  x,y-CMg);
        safeLine(ex+CMg,y,    ex+CMg+CM,y);
        safeLine(ex,y-CMg-CM, ex,y-CMg);
        safeLine(x-CMg-CM,ey, x-CMg,ey);
        safeLine(x,ey+CMg,    x,ey+CMg+CM);
        safeLine(ex+CMg,ey,   ex+CMg+CM,ey);
        safeLine(ex,ey+CMg,   ex,ey+CMg+CM);
      }
    }
    if(doGuideLines){
      pdf.setDrawColor(170,170,170); pdf.setLineWidth(0.1);
      pdf.setLineDashPattern([1.5,1.5],0);
      for(let c2=1;c2<cols;c2++){
        const cx=(startX+c2*cW+(c2-1)*gX+gX*0.5)*IN2MM;
        safeLine(cx,0, cx,PH);
      }
      for(let r2=1;r2<rows;r2++){
        const cy=(startY+r2*cH+(r2-1)*gY+gY*0.5)*IN2MM;
        safeLine(0,cy, PW,cy);
      }
      pdf.setLineDashPattern([],0);
    }
    return i;
  };

  // Double-sided: interleave Front sheet 1, Back sheet 1, Front sheet 2,
  // Back sheet 2, ... (same convention as Generate ID Cards' PDF output) —
  // previously backURLs never even reached this function, so a double-sided
  // card's Back design was designed correctly but never appeared in any
  // generated output at all, on any device.
  const hasBack=backURLs&&backURLs.length>0;
  const totalSheets=Math.max(1,Math.ceil(cardURLs.length/cardsPerSheet));
  let firstPage=true;
  for(let s=0;s<totalSheets;s++){
    if(!firstPage) pdf.addPage([PW,PH],or); firstPage=false;
    drawSheet(cardURLs,s*cardsPerSheet);
    if(hasBack){
      pdf.addPage([PW,PH],or);
      drawSheet(backURLs,s*cardsPerSheet);
    }
  }
  return pdf.output('bloburi');
}
async function buildZIPOutput(cardURLs,backURLs){
  const zip=new JSZip();
  const q=SA.gen.quality||0.92;
  const hasBack=backURLs&&backURLs.length>0;
  cardURLs.forEach((url,i)=>{
    const row=SA.excel.rows[i]||{};
    const nk=SA.project.nameCol||SA.excel.headers[0];
    const rawName=nk?String(row[nk]||''):('card_'+(i+1));
    const name=rawName.replace(/[^a-z0-9_\-]/gi,'_').substring(0,40);
    const b64=url.includes(',')? url.split(',')[1] : url;
    // Suffix "_front" only when a back side actually exists — a single-sided
    // card's files keep their plain original name.
    zip.file(`${String(i+1).padStart(4,'0')}_${name}${hasBack?'_front':''}.jpg`,b64,{base64:true});
    if(hasBack&&backURLs[i]){
      const bb64=backURLs[i].includes(',')? backURLs[i].split(',')[1] : backURLs[i];
      zip.file(`${String(i+1).padStart(4,'0')}_${name}_back.jpg`,bb64,{base64:true});
    }
  });
  const blob=await zip.generateAsync({type:'blob',compression:'DEFLATE',compressionOptions:{level:6}});
  return URL.createObjectURL(blob);
}
async function buildImageSheets(cardURLs,backURLs){
  // Render multi-card sheets as PNG images, zipped
  const {pKey,or,pw,ph,mT,mB,mL,mR,gX,gY,sc}=_getLayoutParams();
  const IN2PX=96; // 96 dpi for screen-quality PNG sheets
  const cW=(SA.project.cardW||3.375)*sc, cH=(SA.project.cardH||2.125)*sc;
  const cols=SA.gen.cols||Math.max(1,Math.floor((pw-mL-mR+gX)/(cW+gX)));
  const rows=SA.gen.rows||Math.max(1,Math.floor((ph-mT-mB+gY)/(cH+gY)));
  const cardsPerSheet=cols*rows;
  const zip=new JSZip();

  // Renders ONE sheet PNG starting at urls[startIdx], returns its base64.
  const renderSheet=async(urls,startIdx)=>{
    const cvs=document.createElement('canvas');
    cvs.width=Math.round(pw*IN2PX); cvs.height=Math.round(ph*IN2PX);
    const ctx=cvs.getContext('2d');
    ctx.fillStyle='#ffffff'; ctx.fillRect(0,0,cvs.width,cvs.height);
    const loads=[]; let i=startIdx;
    for(let r=0;r<rows&&i<urls.length;r++){
      for(let c=0;c<cols&&i<urls.length;c++){
        const idx=i, row=r, col=c;
        loads.push(new Promise(res=>{
          const im=new Image();
          im.onload=()=>{
            const x=(mL+col*(cW+gX))*IN2PX, y=(mT+row*(cH+gY))*IN2PX;
            ctx.drawImage(im,Math.round(x),Math.round(y),Math.round(cW*IN2PX),Math.round(cH*IN2PX));
            res();
          };
          im.onerror=res;
          im.src=urls[idx];
        }));
        i++;
      }
    }
    await Promise.all(loads);
    return cvs.toDataURL('image/png').split(',')[1];
  };

  // Double-sided: a "_front"/"_back" pair of PNGs per sheet number — same
  // reasoning as buildZIPOutput above (previously backURLs never reached
  // this function at all).
  const hasBack=backURLs&&backURLs.length>0;
  const totalSheets=Math.max(1,Math.ceil(cardURLs.length/cardsPerSheet));
  for(let s=0;s<totalSheets;s++){
    const num=String(s+1).padStart(3,'0');
    zip.file(`sheet_${num}${hasBack?'_front':''}.png`,await renderSheet(cardURLs,s*cardsPerSheet),{base64:true});
    if(hasBack) zip.file(`sheet_${num}_back.png`,await renderSheet(backURLs,s*cardsPerSheet),{base64:true});
  }
  const blob=await zip.generateAsync({type:'blob',compression:'DEFLATE',compressionOptions:{level:6}});
  return URL.createObjectURL(blob);
}
async function buildPDFZip(cardURLs,backURLs){
  // Each card gets its own PDF, all zipped — 2 pages (front+back) when
  // double-sided instead of 1 (previously backURLs never reached this
  // function, so this only ever produced a front-only PDF regardless).
  const {jsPDF}=window.jspdf;
  const zip=new JSZip();
  const cW=SA.project.cardW||3.375, cH=SA.project.cardH||2.125;
  const sc=SA.gen.cardScale||1.0;
  const eW=cW*sc, eH=cH*sc;
  const IN2MM=25.4;
  const or=eW>eH?'landscape':'portrait';
  const hasBack=backURLs&&backURLs.length>0;
  for(let i=0;i<cardURLs.length;i++){
    const row=SA.excel.rows[i]||{};
    const nk=SA.project.nameCol||SA.excel.headers[0];
    const rawName=nk?String(row[nk]||''):('card_'+(i+1));
    const name=rawName.replace(/[^a-z0-9_\-]/gi,'_').substring(0,40);
    const pdf=new jsPDF({orientation:or,unit:'mm',format:[eW*IN2MM,eH*IN2MM]});
    pdf.addImage(cardURLs[i],'JPEG',0,0,eW*IN2MM,eH*IN2MM,undefined,'FAST');
    if(hasBack&&backURLs[i]){
      pdf.addPage([eW*IN2MM,eH*IN2MM],or);
      pdf.addImage(backURLs[i],'JPEG',0,0,eW*IN2MM,eH*IN2MM,undefined,'FAST');
    }
    const b64=pdf.output('datauristring').split(',')[1];
    zip.file(`${String(i+1).padStart(4,'0')}_${name}.pdf`,b64,{base64:true});
  }
  const blob=await zip.generateAsync({type:'blob',compression:'DEFLATE',compressionOptions:{level:6}});
  return URL.createObjectURL(blob);
}
function showDownload(url,name,fmt,cardURLs){
  const area=document.getElementById('outputArea');
  const fLabel={pdf:'PDF Sheets',png:'PNG Sheets (ZIP)',zip:'Card Images (ZIP)',pdfzip:'Individual PDFs (ZIP)'}[fmt]||fmt.toUpperCase();
  const isPDF=fmt==='pdf'||fmt==='pdfzip';
  area.innerHTML=`
    <div style="background:#f0fdf4;border:1.5px solid #22c55e;border-radius:10px;padding:1rem 1.2rem;margin-bottom:.6rem;">
      <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
        <div style="width:40px;height:40px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">✅</div>
        <div style="flex:1;min-width:140px;">
          <div style="font-weight:700;font-size:.9rem;color:#166534;">Ready! ${SA.excel.rows.length||1} card${(SA.excel.rows.length||1)>1?'s':''} generated</div>
          <div style="font-size:.74rem;color:#15803d;margin-top:.1rem;">${fLabel} — click Download to save</div>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
          <a href="${url}" download="${name}" style="font-size:.82rem;padding:.45rem 1rem;border-radius:7px;text-decoration:none;background:#2563eb;color:#fff;display:inline-flex;align-items:center;gap:.4rem;font-weight:700;box-shadow:0 2px 8px rgba(37,99,235,.3);">
            <i class="bi bi-download"></i> Download
          </a>
          ${isPDF?`<button onclick="doPrint('${url}')" style="font-size:.82rem;padding:.45rem 1rem;border-radius:7px;background:#fff;border:1.5px solid #22c55e;color:#166534;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;font-weight:600;">
            <i class="bi bi-printer-fill"></i> Print Now
          </button>`:''}
        </div>
      </div>
      ${isPDF?`<div style="margin-top:.65rem;padding:.45rem .65rem;background:#fff;border-radius:7px;border:1px solid #bbf7d0;font-size:.74rem;color:#374151;line-height:1.7;">
        <b style="color:#166534;"><i class="bi bi-info-circle"></i> How to print:</b>
        Download the PDF → Open it → Press <b>Ctrl+P</b> (or Cmd+P on Mac) → Set Paper to A4 → Scale: <b>100% (Actual Size)</b> → Print
      </div>`:`<div style="margin-top:.5rem;font-size:.72rem;color:#6b7280;">Extract the ZIP file to find your card images.</div>`}
    </div>`;
  // Individual ZIP button (always available regardless of format)
  if(cardURLs&&cardURLs.length>1){
    const zipRow=document.createElement('div');
    zipRow.style.cssText='display:flex;align-items:center;gap:.5rem;margin-bottom:.55rem;flex-wrap:wrap;';
    zipRow.innerHTML=`
      <button onclick="_downloadIndividualZIP()" style="padding:.38rem .85rem;border-radius:8px;background:#fff;border:1.5px solid #6366f1;color:#6366f1;font-size:.78rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:.35rem;">
        <i class="bi bi-file-earmark-zip"></i> Download All Individual Cards (ZIP)
      </button>
      <span style="font-size:.7rem;color:#9ca3af;">1 card = 1 JPG file · ${cardURLs.length} files</span>`;
    area.appendChild(zipRow);
  }
  // Thumbnail grid — all cards, clickable lightbox
  if(cardURLs&&cardURLs.length){
    const hdr=document.createElement('div');
    hdr.style.cssText='display:flex;align-items:center;justify-content:space-between;margin-bottom:.35rem;';
    hdr.innerHTML=`<span style="font-size:.74rem;font-weight:700;color:#374151;">${cardURLs.length} Cards — click any to view / download</span>`;
    area.appendChild(hdr);
    const grid=document.createElement('div');
    grid.style.cssText='display:grid;grid-template-columns:repeat(auto-fill,minmax(78px,1fr));gap:.4rem;max-height:310px;overflow-y:auto;padding:.2rem;';
    cardURLs.forEach((u,i)=>{
      const row=SA.excel.rows[i]||{};
      const nk=SA.excel.headers[0];
      const nm=nk?String(row[nk]||'').slice(0,12):'';
      const cell=document.createElement('div');
      cell.style.cssText='cursor:pointer;border-radius:7px;overflow:hidden;border:2px solid #e5e7eb;background:#f8fafc;transition:all .15s;';
      cell.onmouseenter=()=>{cell.style.borderColor='#6366f1';cell.style.transform='translateY(-2px)';cell.style.boxShadow='0 4px 12px rgba(99,102,241,.18)';};
      cell.onmouseleave=()=>{cell.style.borderColor='#e5e7eb';cell.style.transform='';cell.style.boxShadow='';};
      cell.onclick=()=>openCardLightbox(cardURLs,i);
      const img=document.createElement('img');
      img.src=u; img.alt='Card '+(i+1);
      img.style.cssText='width:100%;height:76px;object-fit:cover;display:block;';
      const lbl=document.createElement('div');
      lbl.style.cssText='font-size:.58rem;color:#6b7280;text-align:center;padding:.18rem .2rem .2rem;line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
      lbl.textContent=nm||(i+1);
      cell.appendChild(img); cell.appendChild(lbl);
      grid.appendChild(cell);
    });
    area.appendChild(grid);
  }
  area.scrollIntoView({behavior:'smooth',block:'nearest'});
}
function doPrint(url){
  const w=window.open(url,'_blank');
  if(w) setTimeout(()=>w.print(),800);
}

/* ══════════════════════════════════════
   CARD LIGHTBOX
══════════════════════════════════════ */
const _lb={urls:[],idx:0};

function openCardLightbox(urls,idx){
  _lb.urls=urls; _lb.idx=idx;
  const lb=document.getElementById('cardLB');
  lb.style.display='flex';
  document.body.style.overflow='hidden';
  _lbRender();
}
function lbClose(){
  const lb=document.getElementById('cardLB');
  lb.style.display='none';
  document.body.style.overflow='';
}
function lbNav(dir){
  const n=_lb.urls.length;
  _lb.idx=(_lb.idx+dir+n)%n;
  _lbRender();
}
function _lbRender(){
  const i=_lb.idx, n=_lb.urls.length;
  const u=_lb.urls[i];
  const row=SA.excel.rows[i]||{};
  // Student name from first header
  const nk=SA.excel.headers[0]||'';
  const nm=nk?String(row[nk]||''):'';
  document.getElementById('lbImg').src=u;
  document.getElementById('lbInfo').innerHTML=
    `<span style="color:#94a3b8;font-size:.72rem;">Card ${i+1} of ${n}</span>`+
    (nm?`<br><span style="font-size:.88rem;font-weight:700;color:#f1f5f9;">${nm}</span>`:'');
  // Prev/Next button disabled state
  const prev=document.getElementById('lbPrev'), next=document.getElementById('lbNext');
  if(prev) prev.style.opacity=n>1?'1':'0.3';
  if(next) next.style.opacity=n>1?'1':'0.3';
  // Dot indicator (max 20 dots)
  const dots=document.getElementById('lbDots');
  if(dots){
    if(n<=20){
      dots.innerHTML=Array.from({length:n},(_,k)=>
        `<span onclick="lbGoTo(${k})" style="width:${k===i?'18px':'7px'};height:7px;border-radius:4px;background:${k===i?'#6366f1':'rgba(255,255,255,.3)'};cursor:pointer;transition:all .2s;flex-shrink:0;"></span>`
      ).join('');
    } else {
      dots.textContent='';
    }
  }
}
function lbGoTo(idx){_lb.idx=idx;_lbRender();}
function lbDownloadSingle(){
  const i=_lb.idx;
  const u=_lb.urls[i];
  const row=SA.excel.rows[i]||{};
  const nk=SA.excel.headers[0]||'';
  const rawName=nk?String(row[nk]||''):'card';
  const safeName=rawName.replace(/[^a-z0-9_\-]/gi,'_').substring(0,30)||'card';
  const a=document.createElement('a');
  a.href=u; a.download=`card_${String(i+1).padStart(3,'0')}_${safeName}.jpg`;
  document.body.appendChild(a); a.click(); document.body.removeChild(a);
}
async function _downloadIndividualZIP(){
  if(!SA.gen.outURLs||!SA.gen.outURLs.length){ toast('Generate cards first','warn'); return; }
  toast('Building individual ZIP…','info');
  const url=await buildZIPOutput(SA.gen.outURLs,SA.gen.outBackURLs);
  const a=document.createElement('a');
  a.href=url; a.download='SmartIDs_individual_cards.zip';
  document.body.appendChild(a); a.click(); document.body.removeChild(a);
}
function setProgress(pct){
  const b=document.getElementById('genProgressBar');
  if(b) b.style.width=pct+'%';
}

/* ══════════════════════════════════════
   UTILITIES
══════════════════════════════════════ */
function toast(msg,type='info'){
  let t=document.getElementById('smToast');
  if(!t){
    t=document.createElement('div'); t.id='smToast';
    t.style.cssText='position:fixed;bottom:1.5rem;right:1.5rem;padding:.65rem 1.15rem;border-radius:10px;font-size:.83rem;font-weight:500;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.18);transition:opacity .35s;max-width:360px;pointer-events:none;';
    document.body.appendChild(t);
  }
  const C={ok:'background:#dcfce7;color:#166534;border:1px solid #86efac',error:'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5',warn:'background:#fef9c3;color:#854d0e;border:1px solid #fde047',info:'background:#eff6ff;color:#1e40af;border:1px solid #93c5fd'};
  t.style.cssText+=';'+(C[type]||C.info);
  t.textContent=msg; t.style.opacity='1';
  clearTimeout(t._tid); t._tid=setTimeout(()=>t.style.opacity='0',3300);
}

/* ══════════════════════════════════════
   DOM READY
══════════════════════════════════════ */
document.addEventListener('DOMContentLoaded',()=>{
  // Lightbox keyboard navigation
  document.addEventListener('keydown',e=>{
    const lb=document.getElementById('cardLB');
    if(!lb||lb.style.display==='none') return;
    if(e.key==='ArrowLeft'||e.key==='ArrowUp') lbNav(-1);
    else if(e.key==='ArrowRight'||e.key==='ArrowDown') lbNav(1);
    else if(e.key==='Escape') lbClose();
    else if(e.key==='d'||e.key==='D') lbDownloadSingle();
  });

  // Step 3 init
  refreshSzLabels();
  updateSzPreview();
  initPreviewInteract();
  // Activate default Fit button
  const pvFit=document.getElementById('pvBtnFit');
  if(pvFit) pvFit.style.opacity='1';

  // Gen layout controls — HTML uses cardScale (range), mTop/mBot/mLeft/mRight, gapX/gapY
  updateGenLayout();
  ['genPaper','mTop','mBot','mLeft','mRight','gapX','gapY'].forEach(id=>document.getElementById(id)?.addEventListener('change',updateGenLayout));
  document.getElementById('cardScale')?.addEventListener('input',updateGenLayout);

  // Color picker sync — bgColor oninput is in HTML; add canvas sync here
  document.getElementById('bgColor')?.addEventListener('input',e=>{
    const v=e.target.value;
    const h=document.getElementById('bgHex'); if(h) h.value=v;
    SA.project.bgColor=v;
    if(SA.canvas) SA.canvas.setBackgroundColor(v,SA.canvas.renderAll.bind(SA.canvas));
  });

  // Canvas drag handled via HTML ondrop/ondragover attributes — no duplicate listener needed

  // Property panel live-update inputs
  document.getElementById('pFont')?.addEventListener('change',e=>ap('fontFamily',e.target.value));
  document.getElementById('pSize')?.addEventListener('input',e=>ap('fontSize',+e.target.value));
  document.getElementById('pFill')?.addEventListener('input',e=>{document.getElementById('pFillH').value=e.target.value;ap('fill',e.target.value);});
  document.getElementById('pText')?.addEventListener('input',e=>ap('text',e.target.value));
  document.getElementById('pAlign')?.addEventListener('change',e=>ap('textAlign',e.target.value));
  document.getElementById('pRot')?.addEventListener('input',e=>{document.getElementById('pRotV').textContent=e.target.value+'°';ap('angle',+e.target.value);});
  document.getElementById('pOpa')?.addEventListener('input',e=>{document.getElementById('pOpaV').textContent=e.target.value+'%';ap('opacity',+e.target.value/100);});

  // Designer size bar
  document.getElementById('desW')?.addEventListener('change',resizeFromUI);
  document.getElementById('desH')?.addEventListener('change',resizeFromUI);

  // Keyboard shortcuts
  document.addEventListener('keydown',e=>{
    if(!SA.canvas) return;
    const tag=document.activeElement?.tagName;
    const typing=tag==='INPUT'||tag==='TEXTAREA';
    if((e.ctrlKey||e.metaKey)&&e.key==='z'&&!e.shiftKey){e.preventDefault();undoC();}
    if((e.ctrlKey||e.metaKey)&&(e.key==='y'||(e.key==='z'&&e.shiftKey))){e.preventDefault();redoC();}
    if(!typing&&(e.key==='Delete'||e.key==='Backspace')){
      const a=SA.canvas.getActiveObject();
      if(a){e.preventDefault();deleteSelected();}
    }
    if(!typing&&['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key)){
      const o=SA.canvas.getActiveObject();
      if(o){
        e.preventDefault();
        const d=e.shiftKey?10:1;
        if(e.key==='ArrowLeft')o.left-=d;
        if(e.key==='ArrowRight')o.left+=d;
        if(e.key==='ArrowUp')o.top-=d;
        if(e.key==='ArrowDown')o.top+=d;
        o.setCoords(); SA.canvas.renderAll();
      }
    }
  });
});

/* ══════════════════════════════════════
   PRINT CARD TOOL
══════════════════════════════════════ */
const PT = {
  imgURL: null,
  imgEl: null,
  cardW: 85.6,   // mm
  cardH: 54,
  paper: 'A4',
  orient: 'landscape',
  marginMm: 8,
  gapMm: 3,
  copies: 0,     // 0 = auto-fill
};
const PT_PAPERS = { A4:[210,297], A3:[297,420], A5:[148,210], Letter:[215.9,279.4] };

function openPrintTool(){
  document.getElementById('printToolOv').style.display='flex';
  ptUpdatePreview();
}
function closePrintTool(){
  document.getElementById('printToolOv').style.display='none';
}

/* Drag-drop for upload zone */
function ptDzOver(e){
  e.preventDefault();
  document.getElementById('ptDZ').classList.add('drag');
}
function ptDzLeave(){
  document.getElementById('ptDZ').classList.remove('drag');
}
function ptDzDrop(e){
  e.preventDefault();
  ptDzLeave();
  const f = e.dataTransfer.files[0];
  if(f && f.type.startsWith('image/')) _ptReadFile(f);
}
function ptLoadImg(inp){
  const f = inp.files[0];
  if(f) _ptReadFile(f);
}
function _ptReadFile(f){
  const reader = new FileReader();
  reader.onload = e => {
    const img = new Image();
    img.onload = () => {
      PT.imgURL = e.target.result;
      PT.imgEl  = img;
      const dz = document.getElementById('ptDZ');
      dz.classList.add('filled');
      document.getElementById('ptDzIco').className='bi bi-check-circle-fill';
      document.getElementById('ptDzIco').style.color='#10b981';
      document.getElementById('ptDzTitle').textContent = f.name;
      document.getElementById('ptDzSub').textContent = `${img.naturalWidth}×${img.naturalHeight}px`;
      const tw = document.getElementById('ptThumbWrap');
      document.getElementById('ptThumb').src = PT.imgURL;
      tw.style.display = '';
      ptUpdatePreview();
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(f);
}

/* Size presets */
function ptSetSize(w, h, el){
  PT.cardW = w; PT.cardH = h;
  document.querySelectorAll('#ptSzGrid .pt-szpill').forEach(p=>p.classList.remove('on'));
  if(el) el.classList.add('on');
  document.getElementById('ptCW').value = w;
  document.getElementById('ptCH').value = h;
  ptUpdatePreview();
}
function ptPickCustom(el){
  document.querySelectorAll('#ptSzGrid .pt-szpill').forEach(p=>p.classList.remove('on'));
  el.classList.add('on');
  PT.cardW = +(document.getElementById('ptCW').value || 85.6);
  PT.cardH = +(document.getElementById('ptCH').value || 54);
  ptUpdatePreview();
}
function ptOnCustomSize(){
  document.querySelectorAll('#ptSzGrid .pt-szpill').forEach(p=>p.classList.remove('on'));
  document.getElementById('ptCustomPill').classList.add('on');
  PT.cardW = +(document.getElementById('ptCW').value || 85.6);
  PT.cardH = +(document.getElementById('ptCH').value || 54);
  ptUpdatePreview();
}

/* Paper */
function ptSetPaper(p, el){
  PT.paper = p;
  document.querySelectorAll('[data-pt-paper]').forEach(x=>x.classList.remove('on'));
  el.classList.add('on');
  ptUpdatePreview();
}

/* Orientation */
function ptSetOrient(o){
  PT.orient = o;
  document.getElementById('ptOrLand').classList.toggle('on', o==='landscape');
  document.getElementById('ptOrPort').classList.toggle('on', o==='portrait');
  ptUpdatePreview();
}

/* Copies slider */
function ptOnCopiesChange(v){
  PT.copies = +v;
  document.getElementById('ptCopiesVal').textContent = +v===0 ? 'Auto-fill' : v+' cards';
  ptUpdatePreview();
}

/* Layout calc helper */
function _ptLayout(){
  const mm = PT_PAPERS[PT.paper] || PT_PAPERS.A4;
  let pw = mm[0], ph = mm[1];
  if(PT.orient==='landscape'){ pw=mm[1]; ph=mm[0]; }
  const margin = +(document.getElementById('ptMargin')?.value ?? 8);
  const gap    = +(document.getElementById('ptGap')?.value    ?? 3);
  const cw = PT.cardW, ch = PT.cardH;
  const cols = Math.max(1, Math.floor((pw - margin*2 + gap) / (cw + gap)));
  const rows = Math.max(1, Math.floor((ph - margin*2 + gap) / (ch + gap)));
  return { pw, ph, margin, gap, cw, ch, cols, rows, perSheet: cols*rows };
}

/* Canvas preview */
function ptUpdatePreview(){
  const cvs = document.getElementById('ptPreviewCanvas');
  const ctx = cvs.getContext('2d');
  const L = _ptLayout();
  PT.marginMm = L.margin; PT.gapMm = L.gap;

  const copies = PT.copies > 0 ? Math.min(PT.copies, L.perSheet) : L.perSheet;
  document.getElementById('ptCardsInfo').textContent =
    `${L.cols}×${L.rows} = ${L.perSheet} per sheet`;
  document.getElementById('ptPrevHint').textContent =
    `${L.pw}×${L.ph}mm paper · ${L.cw}×${L.ch}mm card · ${copies} cards printed`;

  const scale = Math.min(380/L.pw, 320/L.ph);
  cvs.width  = Math.round(L.pw * scale);
  cvs.height = Math.round(L.ph * scale);

  // Paper bg
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, cvs.width, cvs.height);
  ctx.strokeStyle = '#cbd5e1';
  ctx.lineWidth = 1;
  ctx.strokeRect(0.5, 0.5, cvs.width-1, cvs.height-1);

  const mPx = L.margin*scale, gPx = L.gap*scale;
  const cwPx = L.cw*scale, chPx = L.ch*scale;
  let drawn = 0;

  for(let r=0; r<L.rows; r++){
    for(let c=0; c<L.cols; c++){
      if(drawn >= copies) break;
      drawn++;
      const x = mPx + c*(cwPx+gPx);
      const y = mPx + r*(chPx+gPx);
      if(PT.imgEl){
        ctx.drawImage(PT.imgEl, x, y, cwPx, chPx);
        ctx.strokeStyle = '#94a3b8';
        ctx.lineWidth = 0.5;
        ctx.strokeRect(x, y, cwPx, chPx);
      } else {
        ctx.fillStyle = '#ede9fe';
        ctx.fillRect(x, y, cwPx, chPx);
        ctx.strokeStyle = '#a5b4fc';
        ctx.lineWidth = 0.7;
        ctx.strokeRect(x, y, cwPx, chPx);
        ctx.fillStyle = '#6366f1';
        ctx.font = `bold ${Math.max(6,Math.round(cwPx*0.11))}px Arial`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(L.cw+'×'+L.ch, x+cwPx/2, y+chPx/2);
      }
    }
  }
}

/* ── Print ── */
function ptDoPrint(){
  const L = _ptLayout();
  const copies = PT.copies > 0 ? Math.min(PT.copies, L.perSheet) : L.perSheet;
  if(!PT.imgURL){
    toast('Please upload a card image first','err');
    return;
  }

  // Build card cell HTML
  const cardCell = `<div class="c"><img src="${PT.imgURL}"></div>`;
  const cells = Array(copies).fill(cardCell).join('');

  const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
  @page { size: ${L.pw}mm ${L.ph}mm; margin: ${L.margin}mm; }
  *{ box-sizing:border-box; margin:0; padding:0; }
  body { display:flex; flex-wrap:wrap; gap:${L.gap}mm; align-content:flex-start; background:#fff; }
  .c { width:${L.cw}mm; height:${L.ch}mm; overflow:hidden; flex-shrink:0; }
  .c img { display:block; width:100%; height:100%; object-fit:fill; }
</style>
</head><body>${cells}</body></html>`;

  const w = window.open('','_blank','width=800,height=600');
  if(!w){ toast('Pop-up blocked — allow pop-ups and try again','err'); return; }
  w.document.open();
  w.document.write(html);
  w.document.close();
  w.onload = () => { w.focus(); w.print(); };
}

/* ── Gallery Picker (for Card Template) ── */
const _sgGalItems = new Map();
let _sgGalCb = null;

function openSgGalPicker(){ _sgGalCb = _sgGalLoadAsPt; _openSgGalPicker(); }
function _openSgGalPicker(){
  document.getElementById('galPickerOv').style.display='flex';
  _loadSgGalItems();
}
function closeSgGalPicker(){ document.getElementById('galPickerOv').style.display='none'; _sgGalCb=null; }

async function _loadSgGalItems(){
  const grid = document.getElementById('galPickerGrid');
  grid.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:2rem;color:#94a3b8;font-size:.82rem;"><i class="bi bi-arrow-clockwise" style="font-size:1.5rem;display:block;margin-bottom:.4rem;"></i>Loading…</div>';
  _sgGalItems.clear();
  try{
    const r = await fetch('<?= BASE_URL ?>/api/gallery_actions.php?action=list&limit=60');
    const d = await r.json();
    if(!d.success || !d.items?.length){
      grid.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:2.5rem;color:#94a3b8;"><i class="bi bi-collection" style="font-size:2rem;display:block;opacity:.3;margin-bottom:.5rem;"></i>No designs in gallery yet.<br><small>Design an ID card in the editor and save it to gallery first.</small></div>';
      return;
    }
    d.items.forEach(it => _sgGalItems.set(it.id, it));
    grid.innerHTML = d.items.map(it=>`
      <div onclick="_pickSgGalItem(${it.id})" style="cursor:pointer;border-radius:9px;overflow:hidden;border:2px solid #e5e7eb;background:#f8fafc;transition:border-color .15s;" onmouseenter="this.style.borderColor='#4f46e5'" onmouseleave="this.style.borderColor='#e5e7eb'">
        <div style="aspect-ratio:${it.canvas_width||856}/${it.canvas_height||540};background:#f3f4f6;overflow:hidden;">
          ${it.thumbnail?`<img src="${it.thumbnail}" style="width:100%;height:100%;object-fit:cover;">`:'<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><i class="bi bi-card-image" style="font-size:1.8rem;color:#d1d5db;"></i></div>'}
        </div>
        <div style="padding:.35rem .45rem;font-size:.68rem;font-weight:700;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${it.name}">${it.name}</div>
      </div>`).join('');
  }catch(e){
    grid.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:2rem;color:#dc2626;font-size:.8rem;">Failed to load gallery.</div>';
  }
}

/* Render a Fabric.js canvas_json to a JPEG data URL using a hidden off-screen canvas */
function _renderJsonToJpg(jsonStr, w, h){
  return new Promise((resolve, reject) => {
    const dispW = +w || 600, dispH = +h || 400;
    const tmpEl = document.createElement('canvas');
    tmpEl.width = dispW; tmpEl.height = dispH;
    // opacity:0 not visibility:hidden — visibility:hidden blocks canvas paint in some browsers
    tmpEl.style.cssText = 'position:fixed;left:-99999px;top:0;opacity:0;pointer-events:none;';
    document.body.appendChild(tmpEl);
    let tc = null;
    const cleanup = () => {
      try{ if(tc){ tc.dispose(); tc = null; } }catch(e){}
      try{ if(tmpEl.parentNode) tmpEl.parentNode.removeChild(tmpEl); }catch(e){}
    };
    const bail = setTimeout(() => { cleanup(); reject(new Error('render timeout')); }, 8000);
    try {
      // StaticCanvas: no event listeners, same render/export API as Canvas
      tc = new fabric.StaticCanvas(tmpEl, {width:dispW, height:dispH, enableRetinaScaling:false});
      tc.loadFromJSON(jsonStr, () => {
        // 400ms lets Fabric's async img-element loads (background data-URL) settle before export
        setTimeout(() => {
          clearTimeout(bail);
          try {
            tc.renderAll();
            // multiplier:2 → 2× pixel density output (≈507 DPI for CR80 at 300 DPI base)
            resolve(tc.toDataURL({format:'jpeg', quality:0.97, multiplier:2}));
          } catch(e){ reject(e); }
          finally { cleanup(); }
        }, 400);
      });
    } catch(e){
      clearTimeout(bail);
      cleanup();
      reject(e);
    }
  });
}

async function _pickSgGalItem(id){
  const it = _sgGalItems.get(id);
  if(!it){ toast('Design not found','warn'); return; }
  const w = it.canvas_width || 856, h = it.canvas_height || 540;
  // Capture callback NOW — closeSgGalPicker() nulls _sgGalCb, so we must save it first
  const cb = _sgGalCb;

  // Always fetch canvas_json — thumbnail (multiplier:0.5) is too low-res for 300 DPI printing
  toast('Loading gallery template…','ok');
  try {
    const r = await fetch('<?= BASE_URL ?>/api/gallery_actions.php?action=get&id=' + id);
    const d = await r.json();
    if(!d.success || !d.item?.canvas_json){ toast('Could not load template data','warn'); return; }
    const cw = d.item.canvas_width || w, ch = d.item.canvas_height || h;

    // Best path: extract backgroundImage.src from JSON — original full-resolution upload
    let parsed; try{ parsed = JSON.parse(d.item.canvas_json); }catch(e){ parsed = null; }
    const bgSrc = parsed?.backgroundImage?.src;
    if(bgSrc){ closeSgGalPicker(); if(cb) cb(bgSrc, it.name, cw, ch); return; }

    // Fallback: render full Fabric canvas at 2× (for designs with no image background)
    const jpg = await _renderJsonToJpg(d.item.canvas_json, cw, ch);
    closeSgGalPicker();
    if(cb) cb(jpg, it.name, cw, ch);
  } catch(e){ toast('Failed to render template','error'); }
}

function _sgGalLoadAsPt(url, name, cw, ch){
  PT.imgURL = url;
  const img = new Image();
  img.onload = ()=>{
    PT.imgEl = img;
    const dz = document.getElementById('ptDZ');
    dz.classList.add('filled');
    document.getElementById('ptDzIco').className  = 'bi bi-check-circle-fill';
    document.getElementById('ptDzIco').style.color = '#10b981';
    document.getElementById('ptDzTitle').textContent = name;
    document.getElementById('ptDzSub').textContent   = `${img.naturalWidth}×${img.naturalHeight}px · gallery`;
    document.getElementById('ptThumb').src = url;
    document.getElementById('ptThumbWrap').style.display = '';
    ptUpdatePreview();
    toast(`Gallery: "${name}" loaded as card template`, 'ok');
  };
  img.onerror = ()=>{ toast('Failed to load gallery image','error'); };
  img.src = url;
}

/* ── Gallery picker for main wizard step 3 template ── */
function openSgGalPickerForTpl(){
  _sgGalCb = function(url, name){ loadTemplateFromURL(url, name); };
  _openSgGalPicker();
}

function loadTemplateFromURL(url, name){
  const isBack = SA.project.sides==='double' && SA.project.activeSide3==='back';
  SA.project.templateURL = url;
  if(isBack) SA.project.backBgURL = url; else SA.project.frontBgURL = url;
  const img = new Image();
  img.onload = () => {
    SA._templateImg = img;
    if(isBack) SA._backTemplateImg = img; else SA._frontTemplateImg = img;
    _pvImgAR = img.naturalWidth / img.naturalHeight || 1;
    const {finalW, finalH, info} = autoDetectSz(img.naturalWidth, img.naturalHeight);
    SA.project.cardW = finalW; SA.project.cardH = finalH;
    highlightPreset(finalW, finalH);
    updateSzPreview();
    updateCardThumbs();
    showDpiQuality();
    document.getElementById('tplThumb').src = url;
    document.getElementById('tplDetectInfo').innerHTML =
      `<b>Detected:</b> ${finalW.toFixed(3)}" × ${finalH.toFixed(3)}" (${img.naturalWidth}×${img.naturalHeight}px)<br>${info}`;
    document.getElementById('tplPreviewArea').classList.remove('hidden');
    document.getElementById('dzTemplate').classList.add('filled');
    document.getElementById('templateOk').textContent = `✓ ${name}`;
    if(SA.canvas) resizeCanvas(finalW, finalH);
    toast(`Gallery: "${name}" loaded as template`, 'ok');
  };
  img.onerror = () => toast('Failed to load gallery image', 'error');
  img.src = url;
}

/* ── Data Collection auto-load (runs on DOMContentLoaded) ── */
document.addEventListener('DOMContentLoaded', function(){
  const raw = sessionStorage.getItem('dc_gen_school');
  if(!raw || !new URLSearchParams(location.search).has('from_dc')) return;
  try{
    const sc = JSON.parse(raw);
    if(!sc.columns?.length) return;
    const cols    = sc.columns;
    const headers = cols.map(c=>c.label||c.key);
    const rows    = sc.records.map((rec, idx)=>{
      let data = {};
      try{ data = JSON.parse(rec.data_json)||{}; }catch{}
      const row = {};
      cols.forEach(c=>{ row[c.label||c.key] = data[c.key]||''; });
      // Unique per record (DB id; row position as a last resort) — used to attach the right photo.
      Object.defineProperty(row, '__rid', { value: String(rec.id != null ? rec.id : 'i' + idx), enumerable: false, writable: true });
      return row;
    });
    SA.excel.headers = headers;
    SA.excel.rows    = rows;
    autoDetectColTypes();
    // Load DC photos (filenames in assets/uploads/dc_photos/), keyed by record id — NOT by name.
    let photoCount = 0;
    SA.dcPhotos = new Map();
    SA.photos.clear();
    sc.records.forEach((rec, idx) => {
      if(!rec.photo) return;
      const pURL = '<?= BASE_URL ?>/assets/uploads/dc_photos/' + rec.photo;
      const rid = rows[idx].__rid;
      SA.dcPhotos.set(rid, pURL);
      SA.photos.set('rid_' + rid, pURL);
      SA.photos.set('rid_' + rid + '.jpg', pURL);
      photoCount++;
    });
    buildExcelPreview();
    updateStep1Status();
    document.getElementById('dzExcel').classList.add('filled');
    document.getElementById('excelOk').textContent = `✓ ${sc.school_name} — ${rows.length} rows · ${headers.length} columns${photoCount ? ' · ' + photoCount + ' photos' : ''}`;
    sessionStorage.removeItem('dc_gen_school');
    toast(`Loaded ${rows.length} records from "${sc.school_name}"${photoCount ? ' · ' + photoCount + ' photos' : ''}`, 'ok');
  }catch(e){}
});
</script>

<!-- ── Gallery Picker Modal ── -->
<div id="galPickerOv" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.58);z-index:8000;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:14px;width:100%;max-width:740px;max-height:86vh;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,.25);">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.1rem;border-bottom:1.5px solid #e5e7eb;flex-shrink:0;">
      <div style="font-size:.9rem;font-weight:800;color:#1e293b;display:flex;align-items:center;gap:.45rem;"><i class="bi bi-collection-fill" style="color:#4f46e5;"></i> My Gallery — Select a Design</div>
      <button onclick="closeSgGalPicker()" style="width:28px;height:28px;border-radius:7px;border:none;background:#f1f5f9;color:#64748b;cursor:pointer;"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="galPickerGrid" style="flex:1;overflow-y:auto;padding:.9rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:.65rem;min-height:200px;"></div>
    <div style="padding:.6rem 1.1rem;border-top:1px solid #f1f5f9;font-size:.71rem;color:#94a3b8;text-align:center;flex-shrink:0;">Click a design to load it as the card template image · 2× upscaled for quality</div>
  </div>
</div>

<?php require_once __DIR__ . '/icard_footer.php'; ?>
