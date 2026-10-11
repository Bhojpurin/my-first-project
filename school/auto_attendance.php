<?php
// school/auto_attendance.php — Gate Console v2
// QR/Barcode/USB-scanner based auto attendance (students + staff)
$pageTitle    = 'Auto Attendance';
$pageSubtitle = 'Scan a student or staff ID card to mark check-in / check-out instantly';
require_once __DIR__ . '/school_header.php';

$pdo      = Database::connect();
$schoolId = $user['school_id'];
$csrf     = csrfToken();
$isAdmin  = ($user['role'] === ROLE_SCHOOL_ADMIN);

require_once __DIR__ . '/../includes/attendance_features.php';
require_once __DIR__ . '/../includes/attendance_core.php';
$FT   = attFeatures($pdo, (int)$schoolId);
$FEAT = $FT['effective'];

$canScanStudents = hasPermission('attendance', 'add') && $FEAT['students'];
$canScanStaff    = hasPermission('staff_attendance', 'add') && $FEAT['staff'];

if (!$canScanStudents && !$canScanStaff) {
    http_response_code(403);
    echo '<div style="padding:60px;text-align:center;font-family:sans-serif;"><div style="font-size:3rem;margin-bottom:16px;">🚫</div><h2 style="color:#b91c1c;margin-bottom:8px;">Access Denied</h2><p style="color:#64748b;">You do not have permission to mark attendance.</p></div>';
    require_once __DIR__ . '/school_footer.php'; exit;
}

$R   = AttCore::rules($pdo, (int)$schoolId);
$cfg = [
    'attendance_late_students'  => $R['late_students'],
    'attendance_late_staff'     => $R['late_staff'],
    'attendance_min_gap'        => $R['min_gap'],
    'attendance_notify_parents' => $R['notify_parents'] ? '1' : '0',
    'attendance_scan_start'     => $R['scan_start'],
    'attendance_scan_end'       => $R['scan_end'],
];
$fullDayTime = $R['full_day_time'];
$weeklyOff   = array_flip($R['weekly_off'] === '' ? [] : explode(',', $R['weekly_off']));
// Same URL style as this page (pretty URL or direct .php file).
$registerUrl = BASE_URL . (str_contains((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '.php')
    ? '/school/attendance_register.php' : '/school/attendance-register');
?>

<style>
/* ═══════════════════════ Gate Console v2 ═══════════════════════ */
.aa2{--bg:#f3f5fa;--card:#fff;--ink:#18233b;--mute:#667085;--line:#e3e8f1;--chip:#eef2f9;
  --brand:#2563eb;--ok:#059669;--warn:#d97706;--bad:#dc2626;--info:#0891b2;--idle:#64748b;
  --shadow:0 1px 3px rgba(16,24,40,.07);color:var(--ink);}
.aa2[data-eff=dark]{--bg:#0e1424;--card:#161e33;--ink:#e8edf8;--mute:#93a0ba;--line:#26314d;--chip:#1e2845;--shadow:none;}
.aa2 *{box-sizing:border-box;}
.aa2.kiosk{background:var(--bg);overflow:auto;padding:18px;}
.aa2 .num{font-variant-numeric:tabular-nums;}

.aa-card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 18px;margin-bottom:16px;box-shadow:var(--shadow);}
.aa-card h3{font-size:.95rem;font-weight:700;margin:0 0 12px;display:flex;align-items:center;gap:8px;color:var(--ink);}
.aa-help{font-size:.78rem;color:var(--mute);line-height:1.7;margin:8px 0 0;}

/* top bar */
.aa-top{display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:14px;}
.aa-gate{display:flex;flex-direction:column;gap:2px;min-width:0;}
.aa-gate label{font-size:.72rem;color:var(--mute);}
.aa-gate input{font-size:1.15rem;font-weight:700;border:0;border-bottom:1.5px dashed var(--line);background:transparent;color:var(--ink);padding:2px 0;width:min(260px,70vw);outline:none;}
.aa-gate input:focus{border-bottom-color:var(--brand);}
.aa-clock{text-align:right;line-height:1.1;}
.aa-clock b{font-size:1.9rem;font-weight:800;letter-spacing:-.02em;}
.aa-clock span{display:block;font-size:.78rem;color:var(--mute);margin-top:2px;}
.aa-pills{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.aa-pill{display:inline-flex;align-items:center;gap:6px;font-size:.76rem;font-weight:600;padding:5px 11px;border-radius:99px;background:var(--chip);color:var(--ink);border:0;}
.aa-pill i{font-size:.8rem;}
.aa-pill[data-tone=ok]{background:#05966916;color:var(--ok);} .aa-pill[data-tone=bad]{background:#dc262616;color:var(--bad);}
.aa-pill[data-tone=warn]{background:#d9770620;color:var(--warn);}
button.aa-pill{cursor:pointer;} button.aa-pill:hover{filter:brightness(.96);}

/* stats */
.aa-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:16px;}
.aa-stat{background:var(--card);border:1px solid var(--line);border-left:4px solid var(--c,var(--brand));border-radius:12px;padding:10px 12px;box-shadow:var(--shadow);}
.aa-stat b{display:block;font-size:1.55rem;font-weight:800;line-height:1.1;}
.aa-stat span{font-size:.76rem;color:var(--mute);}
.aa-stat b.pop{animation:aaPop .35s ease;}
@keyframes aaPop{40%{transform:scale(1.18);}}

/* layout */
.aa-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);gap:16px;align-items:start;}
.aa2.kiosk .aa-grid{grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);}

/* scanner */
.aa-stage{position:relative;aspect-ratio:4/3;max-height:64vh;width:100%;background:#0b1020;border-radius:16px;overflow:hidden;}
.aa2.kiosk .aa-stage{max-height:74vh;}
#qrReader{position:absolute;inset:0;border:0!important;}
#qrReader video{width:100%!important;height:100%!important;object-fit:cover;}
#qrReader img{display:none;}
.aa-idle{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:#c9d3ea;text-align:center;padding:20px;}
.aa-idle > i{font-size:3rem;opacity:.85;}
.aa-idle p{margin:0;font-size:.88rem;max-width:300px;line-height:1.5;}
.aa-stage.live .aa-idle{display:none;}
.aa-reticle{position:absolute;inset:0;display:none;place-items:center;pointer-events:none;}
.aa-stage.live .aa-reticle{display:grid;}
.aa-frame{position:relative;width:64%;aspect-ratio:1;}
.aa-frame i{position:absolute;width:11%;aspect-ratio:1;border:3px solid #fff;opacity:.95;}
.aa-frame i:nth-child(1){top:0;left:0;border-right:0;border-bottom:0;border-top-left-radius:10px;}
.aa-frame i:nth-child(2){top:0;right:0;border-left:0;border-bottom:0;border-top-right-radius:10px;}
.aa-frame i:nth-child(3){bottom:0;left:0;border-right:0;border-top:0;border-bottom-left-radius:10px;}
.aa-frame i:nth-child(4){bottom:0;right:0;border-left:0;border-top:0;border-bottom-right-radius:10px;}
.aa-frame::after{content:"";position:absolute;left:4%;right:4%;height:2px;background:#38bdf8;box-shadow:0 0 12px 2px #38bdf8aa;top:6%;animation:aaLaser 2.2s ease-in-out infinite alternate;}
.aa-stage.paused .aa-frame::after{animation-play-state:paused;opacity:.2;}
@keyframes aaLaser{to{top:92%;}}
.aa-live-tag{position:absolute;top:10px;left:10px;display:none;align-items:center;gap:6px;font-size:.72rem;font-weight:700;color:#fff;background:#0009;padding:4px 10px;border-radius:99px;}
.aa-live-tag::before{content:"";width:8px;height:8px;border-radius:50%;background:#ef4444;animation:aaBlink 1.2s infinite;}
.aa-stage.live .aa-live-tag{display:inline-flex;}
.aa-stage.paused .aa-live-tag::before{animation:none;background:#f59e0b;}
@keyframes aaBlink{50%{opacity:.25;}}

/* result flash (the one big moment) */
.aa-flash{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;text-align:center;padding:18px;color:#fff;background:var(--idle);opacity:0;pointer-events:none;transition:opacity .16s ease;z-index:5;}
.aa-flash.show{opacity:1;}
.aa-flash[data-tone=ok]{background:var(--ok);} .aa-flash[data-tone=warn]{background:var(--warn);}
.aa-flash[data-tone=bad]{background:var(--bad);} .aa-flash[data-tone=info]{background:var(--info);}
.aa-flash[data-tone=mute]{background:var(--idle);}
.aa-flash .av{width:104px;height:104px;border-radius:50%;object-fit:cover;border:4px solid #fff8;background:#fff3;display:grid;place-items:center;font-size:2.6rem;font-weight:800;}
.aa-flash h2{font-size:1.7rem;font-weight:800;margin:6px 0 0;color:#fff;line-height:1.15;}
.aa-flash .sub{font-size:.95rem;opacity:.92;}
.aa-flash .msg{font-size:.95rem;opacity:.95;max-width:380px;}
.aa-flash .bd{margin-top:8px;font-size:.88rem;font-weight:800;background:#fff2;padding:5px 16px;border-radius:99px;}
.aa-flash time{font-size:.8rem;opacity:.85;}
.aa2.kiosk .aa-flash h2{font-size:2.6rem;} .aa2.kiosk .aa-flash .av{width:150px;height:150px;font-size:3.6rem;}

/* controls */
.aa-ctrls{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:12px;}
.aa-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;text-decoration:none;font-size:.84rem;font-weight:600;padding:8px 14px;border-radius:10px;border:1px solid var(--line);background:var(--card);color:var(--ink);cursor:pointer;min-height:40px;}
.aa-btn:hover{background:var(--chip);} .aa-btn:disabled{opacity:.45;cursor:not-allowed;}
.aa-btn.primary{background:var(--brand);border-color:var(--brand);color:#fff;} .aa-btn.primary:hover{filter:brightness(1.08);background:var(--brand);}
.aa-btn.danger{color:var(--bad);}
.aa-btn.on{background:var(--brand);color:#fff;border-color:var(--brand);}
.aa-sel,.aa-in{font-size:.84rem;padding:8px 10px;border-radius:10px;border:1px solid var(--line);background:var(--card);color:var(--ink);min-height:40px;}
.aa-sel{max-width:210px;}
.aa-zoom{display:none;align-items:center;gap:8px;font-size:.78rem;color:var(--mute);flex:1 1 160px;}
.aa-zoom input{flex:1;}
.aa-status{min-height:1.3em;margin-top:10px;font-size:.84rem;color:var(--mute);}
.aa-status[data-tone=bad]{color:var(--bad);} .aa-status[data-tone=ok]{color:var(--ok);}
.aa-manual{display:flex;gap:8px;margin-top:12px;}
.aa-manual .aa-in{flex:1;min-width:0;}

/* feed */
.aa-feed-head{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;}
.aa-chip{font-size:.78rem;font-weight:600;padding:5px 12px;border-radius:99px;border:1px solid var(--line);background:var(--card);color:var(--mute);cursor:pointer;}
.aa-chip.on{background:var(--ink);color:var(--card);border-color:var(--ink);}
.aa-search{flex:1 1 140px;min-width:120px;}
#aaFeed{max-height:62vh;overflow:auto;padding-right:2px;}
.aa2.kiosk #aaFeed{max-height:70vh;}
.aa-empty{text-align:center;padding:30px 10px;color:var(--mute);font-size:.86rem;line-height:1.6;}
.aa-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border:1px solid var(--line);border-left:4px solid var(--c,var(--idle));border-radius:11px;margin-bottom:8px;background:var(--card);}
.aa-item[data-tone=ok]{--c:var(--ok);} .aa-item[data-tone=warn]{--c:var(--warn);} .aa-item[data-tone=bad]{--c:var(--bad);background:#dc26260d;}
.aa-item[data-tone=info]{--c:var(--info);} .aa-item[data-tone=mute]{--c:var(--idle);}
.aa-av{width:42px;height:42px;border-radius:50%;object-fit:cover;flex-shrink:0;background:var(--chip);display:grid;place-items:center;font-weight:700;color:var(--mute);}
.aa-item-main{flex:1;min-width:0;}
.aa-item-name{font-weight:700;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.aa-sub{font-weight:500;font-size:.76rem;color:var(--mute);margin-left:8px;}
.aa-item-msg{font-size:.78rem;color:var(--mute);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.aa-item-side{display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0;}
.aa-item-side time{font-size:.72rem;color:var(--mute);}
.aa-badge{font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:99px;white-space:nowrap;background:color-mix(in srgb,var(--c,var(--idle)) 15%,transparent);color:var(--c,var(--idle));}
.aa-badge[data-tone=ok]{--c:var(--ok);} .aa-badge[data-tone=warn]{--c:var(--warn);} .aa-badge[data-tone=bad]{--c:var(--bad);}
.aa-badge[data-tone=info]{--c:var(--info);} .aa-badge[data-tone=mute]{--c:var(--idle);}

/* admin timing */
.aa-timing{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.aa-timing .aa-in{width:140px;}

/* settings dialog */
.aa-dlg{position:fixed;inset:0;margin:auto;height:fit-content;max-height:92vh;border:1px solid var(--line);border-radius:16px;padding:0;width:min(520px,94vw);background:var(--card);color:var(--ink);box-shadow:0 20px 60px rgba(0,0,0,.3);}
.aa-dlg::backdrop{background:rgba(10,16,32,.55);}
.aa-dlg header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--line);font-weight:700;}
.aa-dlg .body{padding:6px 18px 14px;max-height:68vh;overflow:auto;}
.aa-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--line);font-size:.86rem;}
.aa-row:last-child{border-bottom:0;} .aa-row small{display:block;color:var(--mute);font-size:.74rem;margin-top:2px;}
.aa-row input[type=text]{width:150px;}
.aa-dlg footer{display:flex;gap:8px;justify-content:flex-end;padding:12px 18px;border-top:1px solid var(--line);}

/* rules + summary + misc (v2.1) */
.aa-rules{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:10px;}
.aa-rules label{display:flex;flex-direction:column;gap:4px;font-size:.78rem;color:var(--mute);}
.aa-rules .aa-in{width:100%;}
.aa-check{display:flex;align-items:center;gap:8px;font-size:.84rem;margin:4px 0 8px;}
.aa-sum-row{margin-bottom:14px;}
.aa-sum-top{display:flex;justify-content:space-between;gap:8px;font-size:.88rem;margin-bottom:6px;}
.aa-bar{height:10px;border-radius:99px;background:var(--chip);overflow:hidden;}
.aa-bar i{display:block;height:100%;background:var(--ok);border-radius:99px;transition:width .5s ease;}
.aa-sum-bits{display:flex;flex-wrap:wrap;gap:6px 16px;margin-top:7px;font-size:.78rem;color:var(--mute);}
.aa-sum-bits b{color:var(--ink);}
.aa-sum-note{font-size:.76rem;font-weight:600;color:var(--warn);}
.aa-seg{display:inline-flex;background:var(--chip);border-radius:10px;padding:3px;margin-bottom:10px;}
.aa-seg button{border:0;background:transparent;color:var(--mute);font-size:.8rem;font-weight:600;padding:6px 14px;border-radius:8px;cursor:pointer;}
.aa-seg button.on{background:var(--card);color:var(--ink);box-shadow:var(--shadow);}
.aa-undo{border:0;background:transparent;color:var(--bad);font-size:.72rem;font-weight:700;cursor:pointer;padding:2px 0;}
.aa-manual-wrap{position:relative;}
.aa-suggest{position:absolute;left:0;right:0;bottom:calc(100% + 6px);background:var(--card);border:1px solid var(--line);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.18);max-height:260px;overflow:auto;z-index:20;}
.aa-sg{display:flex;align-items:center;gap:10px;width:100%;text-align:left;border:0;background:transparent;color:var(--ink);padding:8px 12px;cursor:pointer;}
.aa-sg:hover{background:var(--chip);} .aa-sg small{display:block;color:var(--mute);font-size:.74rem;}
.aa-sg .aa-av{width:34px;height:34px;}
.aa-cls{display:flex;align-items:center;gap:10px;font-size:.84rem;padding:7px 0;border-bottom:1px solid var(--line);}
.aa-cls span:first-child{width:96px;flex-shrink:0;font-weight:600;} .aa-cls .aa-bar{flex:1;} .aa-cls span:last-child{width:64px;text-align:right;color:var(--mute);}

/* setup (feature switches) */
.aa-fgroup{margin:14px 0 4px;}
.aa-fg-title{font-size:.78rem;font-weight:700;color:var(--mute);margin-bottom:4px;}
.aa-fgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:8px;}
.aa-frow{display:flex;gap:10px;align-items:flex-start;padding:9px 12px;border:1px solid var(--line);border-radius:10px;background:var(--card);cursor:pointer;font-size:.86rem;}
.aa-frow small{display:block;color:var(--mute);font-size:.74rem;margin-top:2px;line-height:1.4;}
.aa-frow.locked{opacity:.55;cursor:not-allowed;}
.aa-frow input{margin-top:3px;}
.aa-code-sel{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-top:12px;}
.aa-code-sel label{display:flex;flex-direction:column;gap:4px;font-size:.78rem;color:var(--mute);}

/* collapsible admin area */
.aa-admin{margin-bottom:16px;}
.aa-admin > summary{cursor:pointer;list-style:none;font-weight:700;font-size:.95rem;padding:13px 16px;background:var(--card);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);display:flex;align-items:center;gap:8px;}
.aa-admin > summary::-webkit-details-marker{display:none;}
.aa-admin > summary span{font-weight:500;font-size:.78rem;color:var(--mute);}
.aa-admin[open] > summary{margin-bottom:14px;}

/* toast */
.aa-toast{position:fixed;left:50%;bottom:22px;transform:translateX(-50%) translateY(20px);background:#18233b;color:#fff;padding:10px 18px;border-radius:10px;font-size:.85rem;opacity:0;transition:.25s;z-index:99999;pointer-events:none;max-width:90vw;}
.aa-toast.show{opacity:1;transform:translateX(-50%);} .aa-toast[data-tone=bad]{background:var(--bad);} .aa-toast[data-tone=ok]{background:var(--ok);}
.aa-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);}
.aa2 :focus-visible{outline:2px solid var(--brand);outline-offset:2px;}

@media(max-width:991px){.aa-grid,.aa2.kiosk .aa-grid{grid-template-columns:minmax(0,1fr);}}
@media(max-width:767px){
  .aa-stats{grid-template-columns:repeat(3,minmax(0,1fr));}
  .aa-card{padding:14px;border-radius:12px;}
  .aa-clock b{font-size:1.5rem;}
  .aa-timing{flex-direction:column;align-items:stretch;} .aa-timing .aa-in,.aa-timing .aa-btn{width:100%;}
  .aa-ctrls .aa-btn.grow{flex:1 1 100%;}
  .aa-sel{max-width:100%;flex:1 1 100%;}
  .aa-stage{aspect-ratio:3/4;max-height:70vh;}
}
@media(prefers-reduced-motion:reduce){.aa-frame::after,.aa-live-tag::before{animation:none;} .aa-stat b.pop{animation:none;} .aa-flash{transition:none;}}
</style>

<div class="aa2" id="aa2" data-eff="light" data-sz-gate-page>
  <div id="pageAlert"></div>

  <!-- Top bar -->
  <div class="aa-top">
    <div class="aa-gate">
      <label for="aaGate">Gate / device name</label>
      <input id="aaGate" type="text" maxlength="40" value="Main Gate" autocomplete="off">
    </div>
    <div class="aa-pills">
      <span class="aa-pill" id="pillNet" data-tone="ok"><i class="bi bi-wifi"></i> <span>Online</span></span>
      <button class="aa-pill" id="pillQueue" type="button" style="display:none;" title="Scans saved offline. Tap to sync now."><i class="bi bi-cloud-arrow-up"></i> <span>0 waiting</span></button>
      <button class="aa-pill" id="pillDevice" type="button" data-tone="ok" style="display:none;" title="This browser is a registered gate device. Tap to unlink."><i class="bi bi-shield-check"></i> <span>Gate device</span></button>
      <button class="aa-pill" id="btnKiosk" type="button" title="Fullscreen kiosk mode (Alt+K)"><i class="bi bi-fullscreen"></i> Full</button>
      <button class="aa-pill" id="btnSettings" type="button"><i class="bi bi-gear"></i> Settings</button>
    </div>
    <div class="aa-clock"><b class="num" id="aaClock">--:--:--</b><span id="aaDate"></span></div>
  </div>

  <!-- Live counters for this device (today) -->
  <div class="aa-stats" aria-label="Today's scans on this device">
    <div class="aa-stat" style="--c:var(--brand)"><b class="num" id="stTotal">0</b><span>Scans today</span></div>
    <div class="aa-stat" style="--c:var(--ok)"><b class="num" id="stIn">0</b><span>Check-ins</span></div>
    <div class="aa-stat" style="--c:var(--info)"><b class="num" id="stOut">0</b><span>Check-outs</span></div>
    <div class="aa-stat" style="--c:var(--warn)"><b class="num" id="stHalf">0</b><span>Half days</span></div>
    <div class="aa-stat" style="--c:#7c3aed"><b class="num" id="stInside">0</b><span>Inside now</span></div>
    <div class="aa-stat" style="--c:var(--bad)"><b class="num" id="stIssues">0</b><span>Issues</span></div>
  </div>

  <div class="aa-grid">
    <!-- Scanner -->
    <div>
      <div class="aa-card">
        <h3><i class="bi bi-qr-code-scan" style="color:#2563eb;"></i> Scan ID card</h3>
        <div class="aa-stage" id="aaStage">
          <div id="qrReader"></div>
          <div class="aa-idle">
            <i class="bi bi-camera-video"></i>
            <p>Start the camera, then hold an ID card in front of it. QR codes and common barcodes work.</p>
            <button class="aa-btn primary" type="button" id="btnStartBig"><i class="bi bi-camera-video-fill"></i> Start scanning</button>
          </div>
          <div class="aa-reticle"><div class="aa-frame"><i></i><i></i><i></i><i></i></div></div>
          <span class="aa-live-tag" id="aaLiveTag">Live</span>
          <div class="aa-flash" id="aaFlash" data-tone="mute" aria-hidden="true"></div>
        </div>

        <div class="aa-ctrls" id="camCtrls">
          <button class="aa-btn primary" type="button" id="btnStart"><i class="bi bi-camera-video-fill"></i> Start</button>
          <button class="aa-btn danger" type="button" id="btnStop" style="display:none;"><i class="bi bi-stop-circle"></i> Stop</button>
          <button class="aa-btn" type="button" id="btnPause" style="display:none;"><i class="bi bi-pause-fill"></i> <span>Pause</span></button>
          <button class="aa-btn" type="button" id="btnTorch" style="display:none;"><i class="bi bi-lightning-charge"></i> Torch</button>
          <select class="aa-sel" id="selCam" style="display:none;" aria-label="Choose camera"></select>
          <label class="aa-zoom" id="zoomWrap"><i class="bi bi-zoom-in"></i><input type="range" id="rngZoom" aria-label="Zoom"></label>
          <button class="aa-btn" type="button" id="btnImage" title="Scan a code from a photo"><i class="bi bi-image"></i> From photo</button>
          <input type="file" id="fileImage" accept="image/*" hidden>
        </div>
        <div class="aa-status" id="aaStatus" data-tone="" role="status"></div>

        <div class="aa-manual-wrap"><form class="aa-manual" id="manualForm" autocomplete="off">
          <input class="aa-in" id="aaManual" type="text" inputmode="text" placeholder="Type an ID code, or search a name" aria-label="ID code">
          <button class="aa-btn" type="submit"><i class="bi bi-keyboard"></i> Mark</button>
        </form>
        <div class="aa-suggest" id="aaSuggest" hidden></div></div>
        <p class="aa-help">
          A USB or Bluetooth barcode scanner works too: just scan, no need to click anywhere.
          Several phones or gates can scan at the same time and write to the same attendance records.
          If someone forgot their ID card, mark them from
          <a href="<?= BASE_URL ?>/school/attendance" style="color:var(--brand);font-weight:600;">Attendance</a> or
          <a href="<?= BASE_URL ?>/school/staff-attendance" style="color:var(--brand);font-weight:600;">Staff Attendance</a>.
          Shortcuts: <b>Alt+S</b> start/stop, <b>Alt+K</b> kiosk, <b>Alt+M</b> mute, <b>Alt+/</b> manual entry.
        </p>
      </div>
    </div>

    <!-- Feed -->
    <div>
      <div class="aa-card">
        <h3><i class="bi bi-list-check" style="color:#059669;"></i> Today's scans</h3>
        <div class="aa-seg" role="tablist" aria-label="Scan list">
          <button type="button" class="on" data-scope="device">This device</button>
          <button type="button" data-scope="all">All gates <span id="allBadge"></span></button>
        </div>
        <div class="aa-feed-head">
          <button class="aa-chip on" type="button" data-f="all">All</button>
          <button class="aa-chip" type="button" data-f="in">Check-in</button>
          <button class="aa-chip" type="button" data-f="out">Check-out</button>
          <button class="aa-chip" type="button" data-f="issues">Issues</button>
          <button class="aa-chip" type="button" data-f="queued">Offline</button>
          <input class="aa-in aa-search" id="feedSearch" type="search" placeholder="Search name or code" aria-label="Search scans">
        </div>
        <div id="aaFeed"></div>
        <div class="aa-ctrls">
          <button class="aa-btn" type="button" id="btnCsv"><i class="bi bi-download"></i> Export CSV</button>
          <button class="aa-btn danger" type="button" id="btnClear"><i class="bi bi-trash3"></i> Clear list</button>
        </div>
        <p class="aa-help">"This device" is kept on this phone for today only. "All gates" shows scans from every device, live. The real attendance records are always saved on the server.</p>
      </div>
    </div>
  </div>

  <div class="aa-card" id="sumCard">
    <h3><i class="bi bi-people" style="color:#0891b2;"></i> School today
      <span class="aa-sum-note" id="sumNote"></span>
      <button class="aa-btn" type="button" id="btnSumRefresh" style="margin-left:auto;min-height:32px;padding:4px 10px;" aria-label="Refresh numbers"><i class="bi bi-arrow-clockwise"></i></button></h3>
    <div id="sumBody"><div class="aa-empty">Loading…</div></div>
    <div class="aa-ctrls">
      <button class="aa-btn" type="button" id="btnAnnounce"><i class="bi bi-megaphone"></i> Announce today's numbers</button>
      <button class="aa-btn" type="button" id="btnNotStu"><i class="bi bi-person-x"></i> Students not arrived</button>
      <button class="aa-btn" type="button" id="btnNotStaff"><i class="bi bi-person-x"></i> Staff not arrived</button>
      <button class="aa-btn" type="button" id="btnClasses"><i class="bi bi-diagram-3"></i> Class-wise</button>
      <button class="aa-btn" type="button" id="btnAttention"><i class="bi bi-exclamation-triangle"></i> Needs attention</button>
      <a class="aa-btn" id="lnkRegister" href="<?= e($registerUrl) ?>"><i class="bi bi-journal-text"></i> Monthly register</a>
      <?php if ($isAdmin && $FEAT['finalize_absent']): ?><button class="aa-btn danger" type="button" id="btnFinalize"><i class="bi bi-calendar-x"></i> Mark remaining absent</button><?php endif; ?>
    </div>
  </div>

  <?php if ($isAdmin): ?><details class="aa-admin"><summary><i class="bi bi-gear-wide-connected"></i> Setup &amp; rules for your school <span>(school admin only)</span></summary><?php endif; ?>
  <?php if ($isAdmin): ?>
  <?php $featGroups = []; foreach (ATT_FEATURES as $fk => $fm) { $featGroups[$fm[2]][$fk] = $fm; } ?>
  <div class="aa-card" id="setupCard">
    <h3><i class="bi bi-toggles" style="color:#2563eb;"></i> Setup for your school</h3>
    <p class="aa-help" style="margin-top:0;">Choose how your school wants to take attendance. Switch off anything you do not use; it disappears from the page for every teacher.</p>
    <div class="aa-ctrls" style="margin-top:8px;">
      <button class="aa-btn" type="button" data-preset="basic">Basic<span style="font-weight:400;color:var(--mute);">: one scan a day</span></button>
      <button class="aa-btn" type="button" data-preset="standard">Standard<span style="font-weight:400;color:var(--mute);">: check-in/out + late</span></button>
      <button class="aa-btn" type="button" data-preset="smart">Smart<span style="font-weight:400;color:var(--mute);">: everything</span></button>
    </div>
    <?php foreach ($featGroups as $gName => $items): ?>
    <div class="aa-fgroup"><div class="aa-fg-title"><?= e($gName) ?></div><div class="aa-fgrid">
      <?php foreach ($items as $fk => $fm): $isLocked = in_array($fk, $FT['locked'], true); ?>
      <label class="aa-frow<?= $isLocked ? ' locked' : '' ?>">
        <input type="checkbox" data-ft="<?= e($fk) ?>" <?= $FEAT[$fk] ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
        <span><b><?= e($fm[0]) ?></b><small><?= e($fm[1]) ?><?= $isLocked ? ' · Not included in your plan' : '' ?></small></span>
      </label>
      <?php endforeach; ?>
    </div></div>
    <?php endforeach; ?>
    <div class="aa-code-sel">
      <label>Student ID card holds
        <select class="aa-sel" id="selStuCode" style="max-width:none;">
          <?php foreach (ATT_STUDENT_CODE_FIELDS as $cf): ?><option value="<?= e($cf) ?>" <?= $FT['student_code_field'] === $cf ? 'selected' : '' ?>><?= e($cf) ?></option><?php endforeach; ?>
        </select></label>
      <label>Staff ID card holds
        <select class="aa-sel" id="selStfCode" style="max-width:none;">
          <?php foreach (ATT_STAFF_CODE_FIELDS as $cf): ?><option value="<?= e($cf) ?>" <?= $FT['staff_code_field'] === $cf ? 'selected' : '' ?>><?= e($cf) ?></option><?php endforeach; ?>
        </select></label>
    </div>
    <div class="aa-ctrls">
      <button class="aa-btn primary" type="button" id="btnSaveSetup"><i class="bi bi-check2-circle"></i> Save setup</button>
    </div>
    <p class="aa-help">The page reloads after saving. Choose a code field that exists in your data (for example rfid_uid only if you store RFID numbers there).</p>
  </div>
  <?php endif; ?>

  <?php if ($isAdmin): ?>
  <div class="aa-card">
    <h3><i class="bi bi-sliders" style="color:#7c3aed;"></i> Attendance rules</h3>
    <div class="aa-rules">
      <label>Full day cutoff<input type="time" id="fullDayTimeInput" class="aa-in" value="<?= e($fullDayTime) ?>"></label>
      <label>Late after (students)<input type="time" id="lateStudentsInput" class="aa-in" value="<?= e($cfg['attendance_late_students']) ?>"></label>
      <label>Late after (staff)<input type="time" id="lateStaffInput" class="aa-in" value="<?= e($cfg['attendance_late_staff']) ?>"></label>
      <label>Scanning opens<input type="time" id="scanStartInput" class="aa-in" value="<?= e($cfg['attendance_scan_start']) ?>"></label>
      <label>Scanning closes<input type="time" id="scanEndInput" class="aa-in" value="<?= e($cfg['attendance_scan_end']) ?>"></label>
      <label>Ignore re-scan for (minutes)<input type="number" min="1" max="60" id="minGapInput" class="aa-in" value="<?= (int)$cfg['attendance_min_gap'] ?>"></label>
    </div>
    <label class="aa-check"><input type="checkbox" id="notifyParentsInput" <?= $cfg['attendance_notify_parents'] === '1' ? 'checked' : '' ?>> Send parents a message on check-in and check-out</label>
    <div class="aa-check" style="flex-wrap:wrap;">
      <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="absentAlertInput" <?= $R['absent_alert'] ? 'checked' : '' ?>> Tell parents if their child has not reached school by</label>
      <input type="time" id="absentAlertTimeInput" class="aa-in" style="width:130px;" value="<?= e($R['absent_alert_time']) ?>">
    </div>
    <div class="aa-fgroup">
      <div class="aa-fg-title">Weekly off (no attendance, no absence alerts on these days)</div>
      <div class="aa-ctrls" style="margin-top:4px;">
        <?php foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $dn => $dl): ?>
        <label class="aa-check" style="margin:0 8px 0 0;"><input type="checkbox" data-wo="<?= $dn ?>" <?= isset($weeklyOff[(string)$dn]) ? 'checked' : '' ?>> <?= $dl ?></label>
        <?php endforeach; ?>
      </div>
      <div class="aa-ctrls" style="margin-top:4px;">
        <span class="aa-help" style="margin:0;">Or only these Saturdays of the month:</span>
        <?php foreach ([1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th'] as $k => $kl): ?>
        <label class="aa-check" style="margin:0 8px 0 0;"><input type="checkbox" data-wo="6:<?= $k ?>" <?= isset($weeklyOff["6:$k"]) ? 'checked' : '' ?>> <?= $kl ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="aa-rules" style="margin-top:10px;">
      <label>Teachers can change attendance up to (days back)<input type="number" min="0" max="365" id="editLockInput" class="aa-in" value="<?= (int)$R['edit_lock_days'] ?>"></label>
    </div>
    <label class="aa-check"><input type="checkbox" id="requireSignedInput" <?= $R['require_signed'] ? 'checked' : '' ?>> Only accept new secure (signed) ID cards at the gate</label>
    <div class="aa-ctrls">
      <button class="aa-btn primary" type="button" id="btnSaveTiming"><span class="edu-spinner" id="fdtSpinner" style="display:none;"></span> Save rules</button>
      <button class="aa-btn" type="button" data-fd="12:00">Cutoff 12:00</button>
      <button class="aa-btn" type="button" data-fd="13:00">Cutoff 13:00</button>
      <button class="aa-btn" type="button" data-fd="14:00">Cutoff 14:00</button>
    </div>
    <p class="aa-help">
      First scan of the day = <b>Check-in</b> (Present). Second scan = <b>Check-out</b>: at or after the full day cutoff marks a full day, earlier marks a <b>Half day</b>.
      A first scan after the "late after" time is marked <b>Late</b>. Leave "late after" or the scanning window empty to switch that rule off.
      Parent messages need the events <code>student_checkin</code> and <code>student_checkout</code> to be set up in your notifier.
      The "not reached school" message goes once a day, only on working days, and only if at least 30% of students
      have been marked that day (so an unmarked holiday never alerts every parent). It needs the event <code>student_not_arrived</code> in your notifier.
      Turn on "secure cards" only after every student and staff member has a newly printed card; old cards stop working at the gate (name search still works).
    </p>
  </div>

  <div class="aa-card">
    <h3><i class="bi bi-shield-lock" style="color:#dc2626;"></i> Lost ID card</h3>
    <div class="aa-manual" style="margin-top:0;">
      <input class="aa-in" id="revokeCodeInput" type="text" placeholder="ID code on the lost card (S:code / T:code if shared)" aria-label="ID code of lost card">
      <button class="aa-btn danger" type="button" id="btnRevoke"><i class="bi bi-slash-circle"></i> Block &amp; reissue</button>
    </div>
    <div class="aa-help" id="revokeResult"></div>
    <p class="aa-help">The old secure card stops working immediately. Print a new card whose QR holds the text shown above.
      Old unsigned cards cannot be blocked this way; turn on "Only accept secure cards" for that.</p>
  </div>
  <?php endif; ?>

  <?php if ($isAdmin): ?>
  <div class="aa-card">
    <h3><i class="bi bi-phone" style="color:#059669;"></i> Gate devices</h3>
    <p class="aa-help" style="margin-top:0;">Register the phone or tablet kept at the gate. It then keeps scanning all day, even after the login on it expires,
      and opens without internet. A gate device can only scan and see today's numbers. Remove it here if the phone is lost.</p>
    <div class="aa-ctrls" style="margin-top:6px;">
      <button class="aa-btn primary" type="button" id="btnRegDevice"><i class="bi bi-plus-circle"></i> Make this browser a gate device</button>
    </div>
    <div id="deviceList" style="margin-top:10px;"></div>
  </div>
  <?php endif; ?>

  <?php if ($isAdmin): ?></details><?php endif; ?>

  <div id="qrFileHost" hidden></div>
  <div class="aa-sr" aria-live="polite" id="aaLive"></div>
  <div class="aa-toast" id="aaToast"></div>

  <dialog class="aa-dlg" id="dlgList">
    <header><span id="listTitle"></span><button class="aa-btn" type="button" id="btnCloseList" aria-label="Close"><i class="bi bi-x-lg"></i></button></header>
    <div class="body" id="listBody"></div>
  </dialog>

  <!-- Settings -->
  <dialog class="aa-dlg" id="dlgSettings">
    <header>Scanner settings <button class="aa-btn" type="button" id="btnCloseSettings" aria-label="Close"><i class="bi bi-x-lg"></i></button></header>
    <div class="body">
      <div class="aa-row"><div>Sound<small>Beep on every scan. Different tones for success, warning and error.</small></div><input type="checkbox" data-pref="sound"></div>
      <div class="aa-row"><div>Smart voice<small>Speaks a personal message from real attendance data: absence days, late, birthday, monthly attendance.</small></div><input type="checkbox" data-pref="voice"></div>
      <div class="aa-row"><div>Voice language</div>
        <select class="aa-sel" data-pref="lang"><option value="en-IN">English</option><option value="hi-IN">हिन्दी</option></select></div>
      <div class="aa-row"><div>Voice<small>Pick the voice installed on this device.</small></div><select class="aa-sel" id="selVoice"></select></div>
      <div class="aa-row"><div>Voice speed</div>
        <select class="aa-sel" data-pref="rate" data-num><option value="0.85">Slow</option><option value="1">Normal</option><option value="1.15">Fast</option></select></div>
      <div class="aa-row"><div>Vibrate<small>On phones and tablets that support it.</small></div><input type="checkbox" data-pref="vibrate"></div>
      <div class="aa-row"><div>Big result card<small>Full-colour card over the camera after each scan.</small></div><input type="checkbox" data-pref="flash"></div>
      <div class="aa-row"><div>Result card stays for</div>
        <select class="aa-sel" data-pref="flashMs" data-num><option value="1200">1.2 s</option><option value="2200">2.2 s</option><option value="3500">3.5 s</option><option value="5000">5 s</option></select></div>
      <div class="aa-row"><div>Ignore same card for<small>Stops a card held in view from scanning twice.</small></div>
        <select class="aa-sel" data-pref="cooldown" data-num><option value="3">3 s</option><option value="6">6 s</option><option value="10">10 s</option><option value="20">20 s</option><option value="30">30 s</option></select></div>
      <div class="aa-row"><div>Camera speed<small>Higher is faster to detect, but uses more battery.</small></div>
        <select class="aa-sel" data-pref="fps" data-num><option value="10">Battery saver</option><option value="15">Balanced</option><option value="25">Fast</option></select></div>
      <div class="aa-row"><div>Keep screen awake<small>While the camera is running.</small></div><input type="checkbox" data-pref="keepAwake"></div>
      <div class="aa-row"><div>Start camera automatically<small>When this page opens, if camera permission was already given.</small></div><input type="checkbox" data-pref="autoStart"></div>
      <div class="aa-row"><div>Theme</div>
        <select class="aa-sel" data-pref="theme"><option value="light">Light</option><option value="dark">Dark</option><option value="auto">Match device</option></select></div>
    </div>
    <footer>
      <button class="aa-btn" type="button" id="btnTestVoice"><i class="bi bi-megaphone"></i> Test voice</button>
      <button class="aa-btn" type="button" id="btnTestSound"><i class="bi bi-volume-up"></i> Test sound</button>
      <button class="aa-btn" type="button" id="btnResetPrefs">Reset</button>
      <button class="aa-btn primary" type="button" id="btnDoneSettings">Done</button>
    </footer>
  </dialog>
</div>

<script src="<?= BASE_URL ?>/assets/vendor/html5-qrcode/html5-qrcode.min.js"></script>
<script>window.Html5Qrcode || document.write('<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"><\/script>');</script>
<script>
const csrfToken = <?= json_encode($csrf) ?>;
</script>
<script>
(() => {
'use strict';

const API = '<?= BASE_URL ?>/api/attendance_scan.php';
const FEAT = <?= json_encode($FEAT) ?>;
const PRESETS = <?= json_encode(ATT_PRESETS) ?>;
const $ = id => document.getElementById(id);
const root = $('aa2');
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const pad = n => String(n).padStart(2, '0');
const ymd = (d = new Date()) => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
const sqlTs = d => `${ymd(d)} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
const fmtTime = t => new Date(t).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10));
const jget = (k, d) => { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } };
const jset = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };

/* ───────── Gate device token (keeps scanning after the login expires) ───────── */
const GATE_KEY = 'aa_gate_device';
let gateDevice = jget(GATE_KEY, null);          // {token, name}
const gateHeaders = () => gateDevice && gateDevice.token ? {'X-Gate-Token': gateDevice.token} : {};
function swTell(msg) { try { navigator.serviceWorker && navigator.serviceWorker.ready.then(r => r.active && r.active.postMessage(msg)); } catch (e) {} }
function setGateDevice(d) {
  gateDevice = d; if (d) jset(GATE_KEY, d); else { try { localStorage.removeItem(GATE_KEY); } catch (e) {} }
  swTell({type:'gate-device', on:!!d}); if (!d) swTell({type:'gate-clear'});
  updateDeviceUI();
}
function updateDeviceUI() {
  const p = document.getElementById('pillDevice'); if (!p) return;
  p.style.display = gateDevice ? 'inline-flex' : 'none';
  if (gateDevice) p.querySelector('span').textContent = 'Gate device: ' + gateDevice.name;
}
function onAuthFail(data) {
  if (data && data.device_revoked && gateDevice) { setGateDevice(null); toast('This gate device was removed by the admin. Please log in.', 'bad'); }
}

/* ───────── Preferences ───────── */
const PREF_KEY = 'aa_prefs_v2';
const DEFAULTS = {sound:true, voice:true, voiceName:'', rate:1, vibrate:true, flash:true, flashMs:2200, cooldown:6, lang:'hi-IN',
                  theme:'light', gate:'Main Gate', cameraId:'', autoStart:false, keepAwake:true, fps:15};
let prefs = {...DEFAULTS, ...jget(PREF_KEY, {})};
const savePrefs = () => jset(PREF_KEY, prefs);

function applyTheme() {
  const eff = prefs.theme === 'auto' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : prefs.theme;
  root.dataset.eff = eff;
}

/* ───────── Toast / status ───────── */
let toastT;
function toast(msg, tone = '') {
  const t = $('aaToast'); t.textContent = msg; t.dataset.tone = tone; t.classList.add('show');
  clearTimeout(toastT); toastT = setTimeout(() => t.classList.remove('show'), 3200);
}
function setStatus(msg, tone = '') { const s = $('aaStatus'); s.textContent = msg; s.dataset.tone = tone; }
const idleMsg = () => scanning ? 'Hold a student or staff ID card in front of the camera.' : '';

/* ───────── Feed state (persisted per day) ───────── */
const FEED_KEY = 'aa_feed_' + ymd();
Object.keys(localStorage).forEach(k => { if (k.startsWith('aa_feed_') && k !== FEED_KEY) localStorage.removeItem(k); });
let feed = jget(FEED_KEY, []);
let filter = 'all', query = '', scope = 'device', allFeed = [];
const saveFeed = () => jset(FEED_KEY, feed.slice(0, 300));

/* ───────── Offline queue ───────── */
const QUEUE_KEY = 'aa_queue_v2';
const getQueue = () => jget(QUEUE_KEY, []);
const setQueue = q => jset(QUEUE_KEY, q);
function updateNetUI() {
  const on = navigator.onLine;
  const p = $('pillNet'); p.dataset.tone = on ? 'ok' : 'bad';
  p.querySelector('i').className = 'bi ' + (on ? 'bi-wifi' : 'bi-wifi-off');
  p.querySelector('span').textContent = on ? 'Online' : 'Offline — scans are saved';
  const n = getQueue().length, q = $('pillQueue');
  q.style.display = n ? 'inline-flex' : 'none';
  q.dataset.tone = 'warn';
  q.querySelector('span').textContent = n + ' waiting to sync';
}

/* ───────── Classification helpers ───────── */
function classify(d) {
  const e = (d && d.event) || '';
  if (e === 'in' || e === 'out') return e;
  if (e === 'duplicate' || e === 'complete') return 'duplicate';
  return d && d.success ? 'info' : 'error';
}
const toneOf = it => it.kind === 'in' ? (it.status === 'late' ? 'warn' : 'ok')
  : it.kind === 'out' ? (it.status === 'half_day' ? 'warn' : 'ok')
  : (it.kind === 'duplicate' || it.kind === 'undone') ? 'mute'
  : (it.kind === 'queued' || it.kind === 'info') ? 'info' : 'bad';
function badgeOf(it) {
  switch (it.kind) {
    case 'in': return it.status === 'late' ? 'Check-in · Late' : 'Check-in';
    case 'undone': return 'Undone';
    case 'out': return it.status === 'half_day' ? 'Check-out · Half day' : 'Check-out · Full day';
    case 'duplicate': return 'Already recorded';
    case 'queued': return 'Saved offline';
    case 'info': return 'Recorded';
    default: return it.name ? 'Failed' : 'Not recognized';
  }
}
const validPhoto = p => typeof p === 'string' && /^(https?:)?\/\/|^\/(?!\/)|^data:image\//.test(p);
const initialOf = it => (it.name || '?').trim().charAt(0).toUpperCase() || '?';
const avatar = it => validPhoto(it.photo)
  ? `<img class="aa-av" src="${esc(it.photo)}" alt="" loading="lazy">`
  : `<div class="aa-av">${esc(initialOf(it))}</div>`;

function fromResponse(d, code, at, source, uid) {
  d = d || {success:false, message:'Unexpected response from server.'};
  return {id:uid, scanId:d.scan_id || 0, t:at.getTime(), code, name:d.name || '',
          sub:d.sub || d.class || d.class_name || d.designation || d.subtitle || '',
          pid:(d.type || '') + ':' + String(d.person_id || d.name || code), photo:d.photo || '',
          message:d.message || '', kind:classify(d), status:d.status || '', ok:!!d.success, source, gate:prefs.gate, warning:d.warning || '',
          speech:d.speech || null, facts:d.facts || null};
}
const queuedItem = (code, at, uid, source) => ({id:uid, t:at.getTime(), code, name:'', sub:'', pid:code, photo:'',
  message:'No connection. Saved on this device and will sync automatically.', kind:'queued', status:'', ok:true, source, gate:prefs.gate});

/* ───────── Render: stats + feed ───────── */
function renderStats() {
  let inC = 0, outC = 0, half = 0, issues = 0;
  const state = new Map();
  for (let i = feed.length - 1; i >= 0; i--) {
    const it = feed[i];
    if (it.kind === 'in') { inC++; state.set(it.pid, 'in'); }
    else if (it.kind === 'out') { outC++; if (it.status === 'half_day') half++; state.set(it.pid, 'out'); }
    else if (it.kind === 'error') issues++;
  }
  let inside = 0; state.forEach(v => { if (v === 'in') inside++; });
  const set = (id, v) => { const el = $(id); if (el.textContent !== String(v)) { el.textContent = v; el.classList.remove('pop'); void el.offsetWidth; el.classList.add('pop'); } };
  set('stTotal', feed.length); set('stIn', inC); set('stOut', outC); set('stHalf', half); set('stInside', inside); set('stIssues', issues);
}
function itemHtml(it) {
  const tone = toneOf(it);
  const name = it.name || ('ID ' + String(it.code).slice(0, 28));
  const canUndo = FEAT.undo && it.scanId && (it.kind === 'in' || it.kind === 'out');
  return `<div class="aa-item" data-tone="${tone}">${avatar(it)}
    <div class="aa-item-main">
      <div class="aa-item-name">${esc(name)}${it.sub ? `<span class="aa-sub">${esc(it.sub)}</span>` : ''}</div>
      <div class="aa-item-msg">${esc(it.message)}</div>
    </div>
    <div class="aa-item-side"><span class="aa-badge" data-tone="${tone}">${esc(badgeOf(it))}</span>
      <time class="num">${fmtTime(it.t)}${it.synced ? ' · synced' : ''}</time>
      ${canUndo ? `<button type="button" class="aa-undo" data-undo="${it.scanId}">Undo</button>` : ''}</div></div>`;
}
function renderFeed() {
  const q = query.trim().toLowerCase();
  const src = scope === 'all' ? allFeed : feed;
  const rows = src.filter(it => {
    if (filter === 'in' && it.kind !== 'in') return false;
    if (filter === 'out' && it.kind !== 'out') return false;
    if (filter === 'issues' && it.kind !== 'error') return false;
    if (filter === 'queued' && it.kind !== 'queued') return false;
    if (q && !((it.name || '') + ' ' + it.code + ' ' + (it.sub || '')).toLowerCase().includes(q)) return false;
    return true;
  }).slice(0, 120);
  $('aaFeed').innerHTML = rows.length ? rows.map(itemHtml).join('')
    : `<div class="aa-empty">${src.length ? 'No scans match this filter.' : (scope === 'all' ? 'No scans from any gate yet today.' : 'No scans yet. Start the camera or scan with a barcode scanner.')}</div>`;
  renderStats();
}
function pushItem(it) {
  feed.unshift(it); saveFeed(); renderFeed(); notify(it); scheduleSummary();
}

/* ───────── Feedback: flash, sound, voice, vibrate ───────── */
let flashT;
function flash(it) {
  if (!prefs.flash) return;
  const el = $('aaFlash'), tone = toneOf(it);
  const av = validPhoto(it.photo) ? `<img class="av" src="${esc(it.photo)}" alt="">` : `<div class="av">${esc(initialOf(it))}</div>`;
  el.dataset.tone = tone;
  el.innerHTML = `${av}<h2>${esc(it.name || 'Unknown ID')}</h2>${it.sub ? `<div class="sub">${esc(it.sub)}</div>` : ''}
    <div class="msg">${esc(it.speech ? speechText(it) : it.message)}</div><div class="bd">${esc(badgeOf(it))}</div><time class="num">${fmtTime(it.t)}</time>`;
  el.classList.add('show');
  clearTimeout(flashT); flashT = setTimeout(() => el.classList.remove('show'), prefs.flashMs);
}
let actx;
function playTones(list) {
  try {
    actx = actx || new (window.AudioContext || window.webkitAudioContext)();
    if (actx.state === 'suspended') actx.resume();
    list.forEach(([f, at, dur]) => {
      const o = actx.createOscillator(), g = actx.createGain(), t0 = actx.currentTime + at;
      o.type = 'sine'; o.frequency.value = f; o.connect(g); g.connect(actx.destination);
      g.gain.setValueAtTime(0.22, t0); g.gain.exponentialRampToValueAtTime(0.001, t0 + dur);
      o.start(t0); o.stop(t0 + dur);
    });
  } catch (e) {}
}
const SOUNDS = {ok:[[880,0,.12],[1175,.13,.18]], warn:[[660,0,.14],[660,.18,.14]], info:[[740,0,.2]], mute:[[520,0,.16]], bad:[[300,0,.2],[240,.24,.28]]};
function speechText(it) {
  const hi = prefs.lang === 'hi-IN';
  if (it.speech && (hi ? it.speech.hi : it.speech.en)) return hi ? it.speech.hi : it.speech.en;
  const n = it.name ? it.name + (hi ? ', ' : '. ') : '';
  const T = hi
    ? {late:'चेक-इन दर्ज हुआ। आपको देर हो गई।', in:'चेक-इन दर्ज हो गया।', out:'चेक-आउट दर्ज हुआ। पूरा दिन।', half:'चेक-आउट दर्ज हुआ। आधा दिन।', duplicate:'पहले से दर्ज है।', queued:'ऑफ़लाइन सेव हुआ।', info:'दर्ज हो गया।', error:'आईडी पहचानी नहीं गई।', undone:'स्कैन वापस ले लिया गया।'}
    : {late:'Check-in recorded. You are late.', in:'Check-in recorded.', out:'Check-out recorded. Full day.', half:'Check-out recorded. Half day.', duplicate:'Already recorded.', queued:'Saved offline.', info:'Recorded.', error:'ID not recognised.', undone:'Scan undone.'};
  const key = it.kind === 'out' && it.status === 'half_day' ? 'half' : (it.kind === 'in' && it.status === 'late' ? 'late' : it.kind);
  return (it.kind === 'queued' || it.kind === 'error') ? T[key] : n + T[key];
}
let voiceList = [];
function loadVoices() { if ('speechSynthesis' in window) { voiceList = speechSynthesis.getVoices(); fillVoiceSelect(); } }
function pickVoice() {
  const lang = prefs.lang.toLowerCase(), pre = lang.slice(0, 2), norm = v => v.lang.replace('_', '-').toLowerCase();
  if (prefs.voiceName) { const v = voiceList.find(v => v.name === prefs.voiceName && norm(v).startsWith(pre)); if (v) return v; }
  return voiceList.find(v => norm(v) === lang) || voiceList.find(v => norm(v).startsWith(pre)) || null;
}
function fillVoiceSelect() {
  const sel = $('selVoice'); if (!sel) return;
  const pre = prefs.lang.slice(0, 2).toLowerCase();
  const vs = voiceList.filter(v => v.lang.replace('_', '-').toLowerCase().startsWith(pre));
  sel.innerHTML = '<option value="">Automatic</option>' + vs.map(v => `<option value="${esc(v.name)}">${esc(v.name)}</option>`).join('');
  sel.value = prefs.voiceName || ''; if (sel.value !== (prefs.voiceName || '')) sel.value = '';
}
const speechQ = []; let speaking = false;
function say(text, force) {
  if ((!prefs.voice && !force) || !('speechSynthesis' in window) || !text) return;
  speechQ.push(text); while (speechQ.length > 3) speechQ.shift();
  if (!speaking) nextSpeech();
}
function nextSpeech() {
  const text = speechQ.shift();
  if (!text) { speaking = false; return; }
  speaking = true;
  const u = new SpeechSynthesisUtterance(text); u.lang = prefs.lang; u.rate = prefs.rate || 1;
  const v = pickVoice(); if (v) u.voice = v;
  let done = false; const fin = () => { if (!done) { done = true; nextSpeech(); } };
  u.onend = fin; u.onerror = fin; setTimeout(fin, 20000);
  speechSynthesis.speak(u);
}
function speak(it) { say(speechText(it)); }
function announceSummary() {
  if (!lastSummary) { toast('Numbers are still loading.'); return; }
  const s = lastSummary.students, f = lastSummary.staff, hs = s.present + s.late + s.half_day, hf = f.present + f.late + f.half_day;
  const text = prefs.lang === 'hi-IN'
    ? `आज कुल ${s.total} विद्यार्थियों में से ${hs} उपस्थित हैं${s.absent ? `, और ${s.absent} अनुपस्थित हैं` : ''}${s.late ? `। ${s.late} विद्यार्थी देर से आए` : ''}। स्टाफ में ${f.total} में से ${hf} उपस्थित हैं${f.absent ? `, ${f.absent} अनुपस्थित` : ''}।`
    : `Today ${hs} of ${s.total} students are present${s.absent ? `, ${s.absent} absent` : ''}${s.late ? `, ${s.late} arrived late` : ''}. Among staff, ${hf} of ${f.total} are present${f.absent ? `, ${f.absent} absent` : ''}.`;
  say(text, true);
}
function notify(it) {
  const tone = toneOf(it);
  flash(it);
  if (prefs.sound) playTones(SOUNDS[tone] || SOUNDS.info);
  if (prefs.vibrate && navigator.vibrate) navigator.vibrate(tone === 'bad' ? [120, 60, 120] : 60);
  speak(it);
  if (it.warning) toast(it.warning);
  $('aaLive').textContent = `${it.name || 'Unknown ID'}: ${badgeOf(it)}`;
  setStatus(idleMsg());
}

/* ───────── Sending scans ───────── */
async function postScan(code, at, uid, source) {
  const fd = new FormData();
  fd.append('csrf_token', csrfToken); fd.append('action', 'scan'); fd.append('code', code);
  // age_ms = how long this scan waited on the device. The server subtracts it from ITS clock,
  // so a phone with a wrong clock can no longer shift attendance times.
  fd.append('scanned_at', sqlTs(at)); fd.append('age_ms', String(Math.max(0, Date.now() - at.getTime())));
  fd.append('gate', prefs.gate); fd.append('client_uid', uid); fd.append('source', source);
  const ctl = new AbortController(), to = setTimeout(() => ctl.abort(), 15000);
  try {
    const r = await fetch(API, {method:'POST', body:fd, credentials:'same-origin', signal:ctl.signal, headers:gateHeaders()});
    let data = null; try { data = await r.json(); } catch (e) {}
    if (r.status === 401) { onAuthFail(data); return {auth:true, data:{success:false, message:(data && data.message) || 'Session expired. Please log in again.'}}; }
    if (!data) return {data:{success:false, message:'Server error. Please try again.'}};
    return {data};
  } catch (e) { return {net:true}; }
  finally { clearTimeout(to); }
}
const recent = new Map();
async function handleCode(raw, source = 'camera') {
  const code = String(raw || '').trim();
  if (!code) return;
  const now = Date.now(), last = recent.get(code);
  const cd = ((source === 'manual' || source === 'search') ? Math.min(prefs.cooldown, 2) : prefs.cooldown) * 1000;
  if (last && now - last < cd) return;
  recent.set(code, now);
  if (recent.size > 500) recent.forEach((t, k) => { if (now - t > 60000) recent.delete(k); });
  setStatus('Processing…');
  const at = new Date(), uid = uuid();
  if (!navigator.onLine) { offlineOrFail(code, at, uid, source); return; }
  const res = await postScan(code, at, uid, source);
  if (res.net) { offlineOrFail(code, at, uid, source); return; }
  pushItem(fromResponse(res.data, code, at, source, uid));
}
function offlineOrFail(code, at, uid, source) {
  if (!FEAT.offline_queue) { toast('No connection. Offline mode is turned off for your school.', 'bad'); setStatus(idleMsg()); return; }
  enqueue(code, at, uid, source);
}
function enqueue(code, at, uid, source) {
  const q = getQueue(); q.push({code, at:at.getTime(), uid, source}); setQueue(q);
  updateNetUI(); pushItem(queuedItem(code, at, uid, source));
}
let flushing = false;
async function flushQueue(manual) {
  if (flushing) return;
  if (!navigator.onLine) { if (manual) toast('Still offline.', 'bad'); return; }
  const q = getQueue(); if (!q.length) return;
  flushing = true; let done = 0;
  try {
    while (q.length) {
      const job = q[0], at = new Date(job.at);
      const res = await postScan(job.code, at, job.uid, job.source);
      if (res.net) break;
      if (res.auth) { toast(res.data.message, 'bad'); break; }
      q.shift(); setQueue(q); done++;
      const it = fromResponse(res.data, job.code, at, 'sync', job.uid); it.synced = true;
      const idx = feed.findIndex(f => f.id === job.uid);
      if (idx >= 0) feed[idx] = it; else feed.unshift(it);
    }
  } finally {
    flushing = false; saveFeed(); renderFeed(); updateNetUI();
    if (done) toast(`${done} offline scan${done > 1 ? 's' : ''} synced.`, 'ok');
  }
}

/* ───────── Camera scanner ───────── */
const FORMATS = () => [Html5QrcodeSupportedFormats.QR_CODE, Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.CODE_39,
  Html5QrcodeSupportedFormats.EAN_13, Html5QrcodeSupportedFormats.EAN_8, Html5QrcodeSupportedFormats.ITF,
  Html5QrcodeSupportedFormats.DATA_MATRIX, Html5QrcodeSupportedFormats.UPC_A];
let qr = null, scanning = false, paused = false, torchOn = false, wl = null;

async function lockScreen() {
  if (!prefs.keepAwake || !('wakeLock' in navigator)) return;
  try { wl = await navigator.wakeLock.request('screen'); wl.addEventListener('release', () => { wl = null; }); } catch (e) {}
}
function unlockScreen() { try { wl && wl.release(); } catch (e) {} wl = null; }
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && scanning && !wl) lockScreen(); });

function setLiveUI(on) {
  $('aaStage').classList.toggle('live', on);
  $('aaStage').classList.remove('paused');
  $('btnStart').style.display = on ? 'none' : '';
  $('btnStop').style.display = on ? '' : 'none';
  $('btnPause').style.display = on ? '' : 'none';
  if (!on) { ['btnTorch', 'selCam'].forEach(i => $(i).style.display = 'none'); $('zoomWrap').style.display = 'none'; }
}
async function tryStart(cfg) {
  qr = new Html5Qrcode('qrReader', {formatsToSupport:FORMATS(), verbose:false, experimentalFeatures:{useBarCodeDetectorIfSupported:true}});
  await qr.start(cfg, {fps:prefs.fps}, txt => handleCode(txt, 'camera'), () => {});
}
async function startScanner() {
  if (scanning || !FEAT.camera) return;
  if (typeof Html5Qrcode === 'undefined') { setStatus('Scanner library failed to load. Check your internet connection and reload.', 'bad'); return; }
  setStatus('Starting camera…');
  try {
    try { await tryStart(prefs.cameraId ? {deviceId:{exact:prefs.cameraId}} : {facingMode:'environment'}); }
    catch (e) {
      if (!prefs.cameraId) throw e;
      prefs.cameraId = ''; savePrefs(); await tryStart({facingMode:'environment'});
    }
  } catch (e) {
    setStatus('Could not start the camera. Allow camera permission in the browser and try again.', 'bad');
    return;
  }
  scanning = true; paused = false; setLiveUI(true); setStatus(idleMsg());
  $('btnPause').querySelector('span').textContent = 'Pause';
  $('btnPause').querySelector('i').className = 'bi bi-pause-fill';
  lockScreen(); loadCameras(); setupCaps();
}
async function stopScanner() {
  if (!scanning || !qr) return;
  try { await qr.stop(); qr.clear(); } catch (e) {}
  scanning = false; paused = false; torchOn = false; unlockScreen(); setLiveUI(false); setStatus('');
}
async function togglePause() {
  if (!scanning || !qr) return;
  try {
    if (paused) { qr.resume(); } else { qr.pause(true); }
    paused = !paused;
  } catch (e) { return; }
  $('aaStage').classList.toggle('paused', paused);
  $('btnPause').querySelector('span').textContent = paused ? 'Resume' : 'Pause';
  $('btnPause').querySelector('i').className = 'bi ' + (paused ? 'bi-play-fill' : 'bi-pause-fill');
  setStatus(paused ? 'Paused. Tap Resume to scan again.' : idleMsg());
}
async function loadCameras() {
  try {
    const cams = await Html5Qrcode.getCameras();
    if (!cams || cams.length < 2) return;
    const sel = $('selCam');
    let active = ''; try { active = qr.getRunningTrackSettings().deviceId || ''; } catch (e) {}
    sel.innerHTML = cams.map((c, i) => `<option value="${esc(c.id)}">${esc(c.label || 'Camera ' + (i + 1))}</option>`).join('');
    sel.value = prefs.cameraId || active || cams[0].id;
    sel.style.display = '';
  } catch (e) {}
}
function setupCaps() {
  try {
    const caps = qr.getRunningTrackCameraCapabilities();
    const torch = caps.torchFeature();
    if (torch && torch.isSupported()) { $('btnTorch').style.display = ''; $('btnTorch').classList.remove('on'); }
    const z = caps.zoomFeature();
    if (z && z.isSupported()) {
      const r = $('rngZoom'); r.min = z.min(); r.max = z.max(); r.step = z.step(); r.value = z.value() || z.min();
      $('zoomWrap').style.display = 'flex';
    }
  } catch (e) {}
}
async function toggleTorch() {
  try {
    const t = qr.getRunningTrackCameraCapabilities().torchFeature();
    torchOn = !torchOn; await t.apply(torchOn); $('btnTorch').classList.toggle('on', torchOn);
  } catch (e) { toast('Torch is not available on this camera.', 'bad'); }
}

/* ───────── Scan from photo ───────── */
async function scanImage(file) {
  if (!file) return;
  if (typeof Html5Qrcode === 'undefined') { toast('Scanner library not loaded.', 'bad'); return; }
  try {
    const h = new Html5Qrcode('qrFileHost', {formatsToSupport:FORMATS(), verbose:false});
    const txt = await h.scanFile(file, false); try { h.clear(); } catch (e) {}
    handleCode(txt, 'image');
  } catch (e) { toast('No readable code found in that photo.', 'bad'); }
}

/* ───────── USB / Bluetooth barcode scanner (keyboard wedge) ───────── */
let wedgeBuf = '', wedgeLast = 0;
document.addEventListener('keydown', e => {
  if (e.altKey && !e.ctrlKey && !e.metaKey) {
    if (e.code === 'KeyS') { e.preventDefault(); scanning ? stopScanner() : startScanner(); }
    else if (e.code === 'KeyK') { e.preventDefault(); toggleKiosk(); }
    else if (e.code === 'KeyM') { e.preventDefault(); prefs.sound = !prefs.sound; savePrefs(); syncPrefUI(); toast(prefs.sound ? 'Sound on' : 'Sound muted'); }
    else if (e.code === 'Slash') { e.preventDefault(); $('aaManual').focus(); }
    return;
  }
  if (e.ctrlKey || e.metaKey) return;
  const tag = (e.target.tagName || '').toLowerCase();
  if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) return;
  if (!FEAT.usb_scanner) return;
  const now = performance.now();
  if (now - wedgeLast > 80) wedgeBuf = '';
  wedgeLast = now;
  if (e.key === 'Enter') {
    if (wedgeBuf.length >= 3) { e.preventDefault(); const c = wedgeBuf; wedgeBuf = ''; handleCode(c, 'scanner'); }
    wedgeBuf = ''; return;
  }
  if (e.key.length === 1) wedgeBuf += e.key;
});

/* ───────── Kiosk ───────── */
function toggleKiosk() {
  if (document.fullscreenElement) { document.exitFullscreen(); return; }
  if (root.requestFullscreen) root.requestFullscreen().catch(() => root.classList.toggle('kiosk'));
  else root.classList.toggle('kiosk');
}
document.addEventListener('fullscreenchange', () => {
  root.classList.toggle('kiosk', !!document.fullscreenElement);
  $('btnKiosk').querySelector('i').className = 'bi ' + (document.fullscreenElement ? 'bi-fullscreen-exit' : 'bi-fullscreen');
});

/* ───────── Export / clear ───────── */
function csvCell(v) { v = String(v ?? ''); if (/^[=+\-@\t\r]/.test(v)) v = "'" + v; return '"' + v.replace(/"/g, '""') + '"'; }
function exportCsv() {
  if (!feed.length) { toast('Nothing to export yet.', 'bad'); return; }
  const rows = [['Time', 'Code', 'Name', 'Class / Role', 'Result', 'Message', 'Gate', 'Source']];
  [...feed].reverse().forEach(it => rows.push([sqlTs(new Date(it.t)), it.code, it.name, it.sub, badgeOf(it), it.message, it.gate || '', it.source || '']));
  const blob = new Blob(['\ufeff' + rows.map(r => r.map(csvCell).join(',')).join('\n')], {type:'text/csv;charset=utf-8'});
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `scans-${ymd()}.csv`;
  document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
}

/* ───────── Settings UI ───────── */
function syncPrefUI() {
  document.querySelectorAll('[data-pref]').forEach(el => {
    const v = prefs[el.dataset.pref];
    if (el.type === 'checkbox') el.checked = !!v; else el.value = v;
  });
  $('aaGate').value = prefs.gate;
}
document.querySelectorAll('[data-pref]').forEach(el => el.addEventListener('change', () => {
  const k = el.dataset.pref;
  prefs[k] = el.type === 'checkbox' ? el.checked : (el.hasAttribute('data-num') ? Number(el.value) : el.value);
  savePrefs(); if (k === 'theme') applyTheme();
  if (k === 'lang') { prefs.voiceName = ''; savePrefs(); fillVoiceSelect(); }
  if (k === 'fps' && scanning) { stopScanner().then(startScanner); }
  if (k === 'keepAwake') { prefs.keepAwake ? (scanning && lockScreen()) : unlockScreen(); }
}));

/* ───────── Wire up ───────── */
$('btnStart').onclick = $('btnStartBig').onclick = startScanner;
$('btnStop').onclick = stopScanner;
$('btnPause').onclick = togglePause;
$('btnTorch').onclick = toggleTorch;
$('rngZoom').oninput = e => { try { qr.getRunningTrackCameraCapabilities().zoomFeature().apply(Number(e.target.value)); } catch (err) {} };
$('selCam').onchange = async e => { prefs.cameraId = e.target.value; savePrefs(); if (scanning) { await stopScanner(); await startScanner(); } };
$('btnImage').onclick = () => $('fileImage').click();
$('fileImage').onchange = e => { scanImage(e.target.files[0]); e.target.value = ''; };
$('manualForm').onsubmit = e => { e.preventDefault(); const i = $('aaManual'); handleCode(i.value, 'manual'); i.value = ''; i.focus(); };
$('btnKiosk').onclick = toggleKiosk;
$('btnSettings').onclick = () => { syncPrefUI(); fillVoiceSelect(); $('dlgSettings').showModal(); };
$('btnCloseSettings').onclick = $('btnDoneSettings').onclick = () => $('dlgSettings').close();
$('btnTestSound').onclick = () => playTones(SOUNDS.ok);
$('btnTestVoice').onclick = () => say(prefs.lang === 'hi-IN' ? 'अरुण कुमार, आपकी उपस्थिति दर्ज हो गई है। मन लगाकर पढ़ाई कीजिए।' : 'Arun Kumar, your attendance is marked. Study well today.', true);
$('selVoice').onchange = e => { prefs.voiceName = e.target.value; savePrefs(); };
if ('speechSynthesis' in window) { speechSynthesis.onvoiceschanged = loadVoices; loadVoices(); }
$('btnResetPrefs').onclick = () => { prefs = {...DEFAULTS}; savePrefs(); syncPrefUI(); applyTheme(); toast('Settings reset.'); };
$('aaGate').onchange = e => { prefs.gate = e.target.value.trim() || 'Main Gate'; e.target.value = prefs.gate; savePrefs(); };
$('btnCsv').onclick = exportCsv;
$('btnClear').onclick = () => {
  if (!feed.length || !confirm('Clear today\'s list on this device? Attendance already saved on the server is not affected.')) return;
  feed = []; saveFeed(); renderFeed();
};
$('pillQueue').onclick = () => flushQueue(true);
document.querySelectorAll('.aa-chip').forEach(c => c.onclick = () => {
  document.querySelectorAll('.aa-chip').forEach(x => x.classList.remove('on')); c.classList.add('on'); filter = c.dataset.f; renderFeed();
});
$('feedSearch').oninput = e => { query = e.target.value; renderFeed(); };
window.addEventListener('online', () => { updateNetUI(); flushQueue(); });
window.addEventListener('offline', updateNetUI);
setInterval(() => { if (navigator.onLine) flushQueue(); }, 20000);
matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', () => { if (prefs.theme === 'auto') applyTheme(); });
window.addEventListener('beforeunload', () => { if (scanning) try { qr.stop(); } catch (e) {} });

<?php if ($isAdmin): ?>
document.querySelectorAll('[data-fd]').forEach(b => b.onclick = () => { $('fullDayTimeInput').value = b.dataset.fd; });
$('btnSaveTiming').onclick = async () => {
  const sp = $('fdtSpinner'); sp.style.display = 'inline-block';
  const res = await callApi(form('save_settings', {
    full_day_time: $('fullDayTimeInput').value, late_students: $('lateStudentsInput').value, late_staff: $('lateStaffInput').value,
    scan_start: $('scanStartInput').value, scan_end: $('scanEndInput').value, min_gap: $('minGapInput').value,
    notify_parents: $('notifyParentsInput').checked ? '1' : '0',
    absent_alert: $('absentAlertInput').checked ? '1' : '0', absent_alert_time: $('absentAlertTimeInput').value,
    weekly_off: [...document.querySelectorAll('[data-wo]')].filter(c => c.checked).map(c => c.dataset.wo).join(','),
    edit_lock_days: $('editLockInput').value,
    require_signed: $('requireSignedInput').checked ? '1' : '0'}));
  sp.style.display = 'none';
  const d = res.data || {success:false, message:'No connection. Please try again.'};
  eduShowAlert('pageAlert', d.message, d.success ? 'success' : 'danger');
  if (d.success) loadSummary();
};
// "Sat" (every Saturday) and "only some Saturdays" are exclusive.
document.querySelectorAll('[data-wo]').forEach(c => c.addEventListener('change', () => {
  if (!c.checked) return;
  if (c.dataset.wo === '6') document.querySelectorAll('[data-wo^="6:"]').forEach(x => x.checked = false);
  else if (c.dataset.wo.startsWith('6:')) { const all = document.querySelector('[data-wo="6"]'); if (all) all.checked = false; }
}));
$('btnRevoke').onclick = async () => {
  const code = $('revokeCodeInput').value.trim();
  if (!code) { toast('Enter the ID code of the lost card.', 'bad'); return; }
  if (!confirm('Block the old card with code "' + code + '"? It will stop working at the gate immediately.')) return;
  const res = await callApi(form('revoke_card', {code}));
  const d = res.data || {success:false, message:'No connection. Please try again.'};
  $('revokeResult').innerHTML = d.success
    ? `${esc(d.message)}<br><b>New QR text:</b> <code style="user-select:all;word-break:break-all;">${esc(d.payload)}</code>`
    : `<span style="color:var(--bad)">${esc(d.message)}</span>`;
};
async function loadDevices() {
  const res = await callApi(form('device_list'));
  const box = $('deviceList'); if (!box || !res.data || !res.data.success) return;
  const ds = res.data.devices || [];
  box.innerHTML = ds.length ? ds.map(d => `<div class="aa-item" data-tone="${d.last_seen_at ? 'ok' : 'mute'}">
      <div class="aa-av"><i class="bi bi-phone"></i></div>
      <div class="aa-item-main"><div class="aa-item-name">${esc(d.name)}${gateDevice && gateDevice.id === Number(d.id) ? '<span class="aa-sub">this browser</span>' : ''}</div>
        <div class="aa-item-msg">Last seen: ${esc(d.last_seen_at || 'never')}${d.last_ip ? ' · ' + esc(d.last_ip) : ''}</div></div>
      <button class="aa-btn danger" type="button" data-rmdev="${Number(d.id)}">Remove</button></div>`).join('')
    : '<div class="aa-empty">No gate devices yet.</div>';
}
$('btnRegDevice').onclick = async () => {
  if (gateDevice && !confirm('This browser is already the gate device "' + gateDevice.name + '". Register it again?')) return;
  const name = (prompt('Name for this gate device:', prefs.gate || 'Main Gate') || '').trim();
  if (!name) return;
  const res = await callApi(form('device_register', {name}));
  const d = res.data || {success:false, message:'No connection. Please try again.'};
  if (d.success) {
    const id = Number(String(d.token).split('_')[1]) || 0;
    setGateDevice({token:d.token, name:d.name, id}); prefs.gate = d.name; savePrefs(); syncPrefUI(); loadDevices();
  }
  toast(d.message, d.success ? 'ok' : 'bad');
};
$('deviceList').addEventListener('click', async e => {
  const b = e.target.closest('[data-rmdev]'); if (!b) return;
  if (!confirm('Remove this gate device? It will stop scanning immediately.')) return;
  const id = Number(b.dataset.rmdev);
  const res = await callApi(form('device_revoke', {id}));
  const d = res.data || {success:false, message:'No connection. Please try again.'};
  toast(d.message, d.success ? 'ok' : 'bad');
  if (d.success && gateDevice && gateDevice.id === id) setGateDevice(null);
  loadDevices();
});
const fin = $('btnFinalize');
if (fin) fin.onclick = async () => {
  if (!confirm('Mark everyone who has no attendance yet today as Absent?\nIf they scan later they will be changed to Present.')) return;
  let res = await callApi(form('finalize_absent', {type:'both'}));
  if (res.data && res.data.needs_force && confirm(res.data.message + '\n\nContinue anyway?')) res = await callApi(form('finalize_absent', {type:'both', force:'1'}));
  const d = res.data || {success:false, message:'No connection. Please try again.'};
  if (!d.needs_force) { eduShowAlert('pageAlert', d.message, d.success ? 'success' : 'danger'); loadSummary(); }
};
<?php endif; ?>

/* ───────── API helper ───────── */
async function callApi(fd) {
  const ctl = new AbortController(), to = setTimeout(() => ctl.abort(), 15000);
  try {
    const r = await fetch(API, {method:'POST', body:fd, credentials:'same-origin', signal:ctl.signal, headers:gateHeaders()});
    let d = null; try { d = await r.json(); } catch (e) {}
    if (r.status === 401) { onAuthFail(d); return {auth:true, data:{success:false, message:(d && d.message) || 'Session expired. Please log in again.'}}; }
    return {data: d || {success:false, message:'Server error. Please try again.'}};
  } catch (e) { return {net:true}; }
  finally { clearTimeout(to); }
}
const form = (action, obj = {}) => {
  const fd = new FormData(); fd.append('csrf_token', csrfToken); fd.append('action', action);
  Object.entries(obj).forEach(([k, v]) => fd.append(k, v)); return fd;
};

/* ───────── School-wide summary ───────── */
let lastSummary = null, sumT;
const pct = (a, b) => b > 0 ? Math.round(a * 100 / b) : 0;
function sumRow(label, s) {
  const here = s.present + s.late + s.half_day, p = pct(here, s.total);
  const bits = [['Late', s.late], ['Half day', s.half_day], ['Absent', s.absent], ['On leave', s.leave], ['Not marked', s.not_marked]].concat(FEAT.checkout ? [['Inside now', s.inside]] : []);
  return `<div class="aa-sum-row"><div class="aa-sum-top"><b>${label}</b><span class="num">${here} of ${s.total} here · ${p}%</span></div>
    <div class="aa-bar"><i style="width:${p}%"></i></div>
    <div class="aa-sum-bits">${bits.map(([k, v]) => `<span><b class="num">${v}</b> ${k}</span>`).join('')}</div></div>`;
}
async function loadSummary() {
  const res = await callApi(form('summary'));
  if (res.net || !res.data || !res.data.success) {
    if (!lastSummary) $('sumBody').innerHTML = '<div class="aa-empty">Live school numbers are not available right now.</div>';
    return;
  }
  lastSummary = res.data;
  $('sumBody').innerHTML = (FEAT.students ? sumRow('Students', lastSummary.students) : '') + (FEAT.staff ? sumRow('Staff', lastSummary.staff) : '');
  $('sumNote').textContent = lastSummary.warning || '';
}
function scheduleSummary() { clearTimeout(sumT); sumT = setTimeout(loadSummary, 1500); }

function openList(title, html) { $('listTitle').textContent = title; $('listBody').innerHTML = html; if (!$('dlgList').open) $('dlgList').showModal(); }
async function showNotArrived(type) {
  openList(type === 'student' ? 'Students not arrived yet' : 'Staff not arrived yet', '<div class="aa-empty">Loading…</div>');
  const res = await callApi(form('not_arrived', {type}));
  if (res.net || !res.data || !res.data.success) { $('listBody').innerHTML = `<div class="aa-empty">${esc(res.data ? res.data.message : 'Could not load the list.')}</div>`; return; }
  const rows = res.data.rows || [];
  $('listBody').innerHTML = rows.length
    ? `<p class="aa-help" style="margin:8px 0">${rows.length}${res.data.truncated ? '+' : ''} people have no attendance yet today.</p>` + rows.map(r => {
        const tel = String(r.phone || '').replace(/[^\d+]/g, '');
        return `<div class="aa-item" data-tone="mute">${avatar({name:r.name, photo:''})}
          <div class="aa-item-main"><div class="aa-item-name">${esc(r.name)}${r.sub ? `<span class="aa-sub">${esc(r.sub)}</span>` : ''}</div><div class="aa-item-msg">${esc(r.code || '')}</div></div>
          ${tel ? `<a class="aa-btn" href="tel:${esc(tel)}" aria-label="Call guardian of ${esc(r.name)}"><i class="bi bi-telephone"></i> Call</a>` : ''}</div>`;
      }).join('')
    : '<div class="aa-empty">Everyone has been marked today.</div>';
}
async function showClasses() {
  openList('Class-wise attendance today', '<div class="aa-empty">Loading…</div>');
  if (!lastSummary) await loadSummary();
  const cl = (lastSummary && lastSummary.classes) || [];
  $('listBody').innerHTML = cl.length ? cl.map(c => `<div class="aa-cls"><span>${esc(c.label)}</span><div class="aa-bar"><i style="width:${pct(c.here, c.total)}%"></i></div><span class="num">${c.here}/${c.total}</span></div>`).join('')
    : '<div class="aa-empty">No class data yet.</div>';
}
async function showAttention() {
  openList('Students who need attention', '<div class="aa-empty">Loading…</div>');
  const res = await callApi(form('insights'));
  if (res.net || !res.data || !res.data.success) { $('listBody').innerHTML = `<div class="aa-empty">${esc(res.data ? res.data.message : 'Could not load the list.')}</div>`; return; }
  const rows = res.data.rows || [];
  const chip = ([k, v]) => k === 'streak' ? `<span class="aa-badge" data-tone="bad">Absent ${v} days in a row</span>`
    : k === 'low' ? `<span class="aa-badge" data-tone="warn">${v}% attendance</span>` : `<span class="aa-badge" data-tone="info">Late ${v} times</span>`;
  $('listBody').innerHTML = rows.length
    ? `<p class="aa-help" style="margin:8px 0">Last 30 working days. Below 75% can affect board exam eligibility.</p>` + rows.map(r => {
        const tel = String(r.phone || '').replace(/[^\d+]/g, '');
        return `<div class="aa-item" data-tone="${r.streak >= 3 ? 'bad' : 'warn'}">${avatar({name:r.name, photo:''})}
          <div class="aa-item-main"><div class="aa-item-name">${esc(r.name)}${r.sub ? `<span class="aa-sub">${esc(r.sub)}</span>` : ''}</div>
            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:3px;">${r.why.map(chip).join('')}</div></div>
          ${tel ? `<a class="aa-btn" href="tel:${esc(tel)}" aria-label="Call guardian of ${esc(r.name)}"><i class="bi bi-telephone"></i> Call</a>` : ''}</div>`;
      }).join('')
    : '<div class="aa-empty">Good news: no student needs attention right now.</div>';
}
$('btnAttention').onclick = showAttention;
$('btnAnnounce').onclick = announceSummary;
$('btnNotStu').onclick = () => showNotArrived('student');
$('btnNotStaff').onclick = () => showNotArrived('staff');
$('btnClasses').onclick = showClasses;
$('btnCloseList').onclick = () => $('dlgList').close();
$('btnSumRefresh').onclick = () => { loadSummary(); if (scope === 'all') loadAll(); };

/* ───────── All gates (live) ───────── */
async function loadAll() {
  const res = await callApi(form('recent'));
  if (res.net || !res.data || !res.data.success) return;
  allFeed = (res.data.items || []).map(r => ({
    id:'r' + r.scan_id, scanId:r.scan_id, t:Date.parse(String(r.scanned_at).replace(' ', 'T')), code:'', name:r.name || '', sub:r.sub || '',
    photo:r.photo || '', message:(r.gate ? r.gate + ' · ' : '') + (r.type === 'staff' ? 'Staff' : 'Student'),
    kind:r.scan_type, status:r.status || '', ok:true, pid:r.type + ':' + r.person_id, source:r.source || '', gate:r.gate || ''}));
  $('allBadge').textContent = allFeed.length ? `(${allFeed.length})` : '';
  if (scope === 'all') renderFeed();
}
document.querySelectorAll('[data-scope]').forEach(b => b.onclick = () => {
  document.querySelectorAll('[data-scope]').forEach(x => x.classList.remove('on')); b.classList.add('on');
  scope = b.dataset.scope; renderFeed(); if (scope === 'all') loadAll();
});
setInterval(() => { if (document.visibilityState === 'visible' && navigator.onLine) { if (scope === 'all') loadAll(); } }, 8000);
setInterval(() => { if (document.visibilityState === 'visible' && navigator.onLine) loadSummary(); }, 45000);

/* ───────── Undo ───────── */
async function undoScan(scanId) {
  if (!confirm('Undo this scan? The attendance it created will be reverted.')) return;
  const res = await callApi(form('undo', {scan_id:scanId}));
  if (res.net) { toast('No connection. Try again when online.', 'bad'); return; }
  const d = res.data; toast(d.message, d.success ? 'ok' : 'bad');
  if (d.success) {
    feed.forEach(it => { if (it.scanId === scanId) { it.kind = 'undone'; it.message = 'This scan was undone.'; } });
    saveFeed(); renderFeed(); loadSummary(); if (scope === 'all') loadAll();
  }
}
$('aaFeed').addEventListener('click', e => { const b = e.target.closest('[data-undo]'); if (b) undoScan(Number(b.dataset.undo)); });

/* ───────── Name search (typeahead) ───────── */
(() => {
  const inp = $('aaManual'), sug = $('aaSuggest'); let st, rs = [];
  inp.addEventListener('input', () => {
    clearTimeout(st); const v = inp.value.trim();
    if (v.length < 2 || !FEAT.name_search) { sug.hidden = true; return; }
    st = setTimeout(async () => {
      const res = await callApi(form('search', {q:v}));
      if (res.net || !res.data || !res.data.success || inp.value.trim() !== v) return;
      rs = res.data.results || [];
      sug.innerHTML = rs.map((r, i) => `<button type="button" class="aa-sg" data-i="${i}">${avatar({name:r.name, photo:r.photo})}<span><b>${esc(r.name)}</b><small>${esc(r.code)} · ${esc(r.sub || r.type)}</small></span></button>`).join('')
        || '<div class="aa-empty" style="padding:10px">No match</div>';
      sug.hidden = false;
    }, 250);
  });
  sug.addEventListener('click', e => {
    const b = e.target.closest('[data-i]'); if (!b) return;
    const r = rs[Number(b.dataset.i)]; sug.hidden = true; inp.value = ''; handleCode(r.scan_code || r.code, 'search');
  });
  $('manualForm').addEventListener('submit', () => { sug.hidden = true; });
  document.addEventListener('click', e => { if (!e.target.closest('.aa-manual-wrap')) sug.hidden = true; });
})();
/* ───────── Per-school features ───────── */
function applyFeatures() {
  const hide = id => { const el = $(id); if (el) el.style.display = 'none'; };
  const hideStat = id => { const el = $(id); if (el && el.closest('.aa-stat')) el.closest('.aa-stat').style.display = 'none'; };
  if (!FEAT.camera) { hide('aaStage'); hide('camCtrls'); }
  if (!FEAT.photo_scan) hide('btnImage');
  if (!FEAT.live_gates) { const sg = document.querySelector('.aa-seg'); if (sg) sg.style.display = 'none'; }
  if (!FEAT.class_progress) hide('btnClasses');
  if (!FEAT.checkout) { hideStat('stOut'); hideStat('stHalf'); hideStat('stInside'); }
  if (!FEAT.students) { hide('btnNotStu'); hide('btnAttention'); }
  if (!FEAT.staff) hide('btnNotStaff');
  if (!FEAT.name_search) { const i = $('aaManual'); if (i) i.placeholder = 'Type or paste an ID code, then press Enter'; }
}
<?php if ($isAdmin): ?>
document.querySelectorAll('[data-preset]').forEach(b => b.onclick = () => {
  const on = PRESETS[b.dataset.preset] || [];
  document.querySelectorAll('[data-ft]').forEach(c => { if (!c.disabled) c.checked = on.includes(c.dataset.ft); });
});
$('btnSaveSetup').onclick = async () => {
  const feats = {};
  document.querySelectorAll('[data-ft]').forEach(c => { if (!c.disabled) feats[c.dataset.ft] = c.checked; });
  const res = await callApi(form('save_settings', {features:JSON.stringify(feats), student_code_field:$('selStuCode').value, staff_code_field:$('selStfCode').value}));
  const d = res.data || {success:false, message:'No connection. Please try again.'};
  if (d.success) { toast('Setup saved. Reloading…', 'ok'); setTimeout(() => location.reload(), 700); }
  else eduShowAlert('pageAlert', d.message, 'danger');
};
<?php endif; ?>

/* clock */
function tick() {
  const d = new Date();
  $('aaClock').textContent = d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
  $('aaDate').textContent = d.toLocaleDateString([], {weekday:'long', day:'numeric', month:'long', year:'numeric'});
}
tick(); setInterval(tick, 1000);

/* gate device pill: tap to unlink this browser */
$('pillDevice').onclick = () => {
  if (gateDevice && confirm('Unlink this browser from gate device "' + gateDevice.name + '"? It will need a login to scan again.')) setGateDevice(null);
};

/* offline page: a service worker keeps this page (and its scripts) available without internet */
if ('serviceWorker' in navigator && window.isSecureContext) {
  navigator.serviceWorker.register('<?= BASE_URL ?>/school/gate-sw.js?page=' + encodeURIComponent(location.pathname), {scope:'<?= BASE_URL ?>/school/'})
    .then(() => swTell({type:'gate-device', on:!!gateDevice}))
    .catch(e => console.warn('Offline page not available:', e && e.message));
}

/* init */
applyFeatures(); applyTheme(); syncPrefUI(); renderFeed(); updateNetUI(); updateDeviceUI(); flushQueue(); loadSummary(); loadAll();
<?php if ($isAdmin): ?>loadDevices();<?php endif; ?>
if (prefs.autoStart && FEAT.camera) startScanner();
})();
</script>

<?php require_once __DIR__ . '/school_footer.php'; ?>