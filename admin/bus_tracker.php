<?php
// school/bus_tracker.php — School Bus Fleet Management & Live GPS Tracker
$pageTitle    = 'Bus Tracker';
$pageSubtitle = 'Fleet management, route assignments & live GPS tracking';

$pageExtraHead = <<<HTML
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<style>
:root { --primary:#2563eb; }
.bt-tabs    { display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:20px; overflow-x:auto; }
.bt-tab     { flex-shrink:0; padding:12px 22px; font-size:.87rem; font-weight:600; color:#6b7280;
              border:none; background:none; cursor:pointer; border-bottom:2px solid transparent;
              margin-bottom:-2px; transition:all .15s; display:flex; align-items:center; gap:7px; }
.bt-tab.active { color:#2563eb; border-bottom-color:#2563eb; }
.bt-tab:hover:not(.active) { color:#374151; background:#f9fafb; }
.bt-pane    { display:none; } .bt-pane.active { display:block; }
.bt-stats   { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin-bottom:20px; }
.bt-stat    { background:#fff; border-radius:12px; padding:16px 18px; border:1.5px solid #e5e7eb;
              box-shadow:0 1px 3px rgba(0,0,0,.05); }
.bt-stat-n  { font-size:1.8rem; font-weight:800; color:#1e293b; line-height:1; }
.bt-stat-l  { font-size:.75rem; color:#6b7280; margin-top:4px; font-weight:500; }
.bus-grid   { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:16px; }
.bus-card   { background:#fff; border-radius:14px; border:1.5px solid #e5e7eb; padding:16px;
              box-shadow:0 1px 3px rgba(0,0,0,.05); position:relative; }
.bus-card-top   { display:flex; align-items:flex-start; gap:12px; margin-bottom:12px; }
.bus-icon       { width:44px; height:44px; border-radius:10px; background:#eff6ff;
                  display:flex; align-items:center; justify-content:center;
                  font-size:1.3rem; color:#2563eb; flex-shrink:0; }
.bus-name       { font-weight:700; font-size:.93rem; color:#1e293b; }
.bus-num        { font-size:.78rem; color:#6b7280; margin-top:2px; }
.bus-meta       { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
.bt-badge       { display:inline-flex; align-items:center; gap:4px; padding:3px 9px;
                  border-radius:20px; font-size:.72rem; font-weight:600; }
.bt-badge.live  { background:#dcfce7; color:#16a34a; }
.bt-badge.recent{ background:#fef9c3; color:#92400e; }
.bt-badge.offline{ background:#fee2e2; color:#dc2626; }
.bt-badge.never { background:#f1f5f9; color:#94a3b8; }
.bt-badge.active{ background:#eff6ff; color:#2563eb; }
.bt-badge.cap   { background:#f3f4f6; color:#374151; }
.bus-foot       { display:flex; gap:8px; }
.gps-dot        { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:4px; }
.gps-dot.live   { background:#22c55e; animation:pulse 1.5s infinite; }
.gps-dot.recent { background:#f59e0b; }
.gps-dot.offline,.gps-dot.never { background:#d1d5db; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.route-table    { width:100%; border-collapse:collapse; }
.route-table th,.route-table td { padding:10px 12px; text-align:left; font-size:.83rem; border-bottom:1px solid #f1f5f9; }
.route-table th { background:#f8fafc; font-weight:600; color:#374151; font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; }
.route-table tr:hover td { background:#fafbff; }
.assign-badge   { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:8px; font-size:.78rem; font-weight:600; }
.assign-badge.yes { background:#dcfce7; color:#16a34a; }
.assign-badge.no  { background:#fef2f2; color:#dc2626; }
.map-wrap       { border-radius:14px; overflow:hidden; border:1.5px solid #e5e7eb; box-shadow:0 2px 8px rgba(0,0,0,.07); }
#liveMap        { height:520px; }
.map-layout     { display:grid; grid-template-columns:300px 1fr; gap:14px; }
.bl-trip  { font-size:.72rem; color:#334155; margin-top:4px; }
.bl-bar   { display:flex; height:6px; border-radius:4px; overflow:hidden; background:#e5e7eb; margin-top:4px; }
.bl-bar i { display:block; height:100%; }
.bl-halt  { font-size:.72rem; color:#b45309; margin-top:3px; font-weight:600; }
.bd-box   { border-top:1.5px solid #e5e7eb; margin-top:10px; padding-top:10px; font-size:.8rem; color:#334155; }
.bd-box h4 { margin:0 0 6px; font-size:.88rem; color:#1e293b; display:flex; align-items:center; gap:6px; }
.bd-row   { display:flex; gap:8px; align-items:center; padding:5px 4px; border-radius:7px; cursor:pointer; }
.bd-row:hover { background:#f8fafc; }
.bd-dot   { width:20px; height:20px; border-radius:50%; color:#fff; font-size:.66rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.bd-sec   { font-size:.7rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.04em; margin:10px 0 4px; }
.bd-kpi   { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; margin:6px 0; }
.bd-kpi div { background:#f8fafc; border-radius:8px; padding:6px; text-align:center; }
.bd-kpi b { display:block; font-size:1.05rem; }
.al-row  { display:flex; gap:8px; align-items:center; padding:4px 2px; cursor:pointer; border-radius:6px; }
.al-row:hover { background:#fef3c7; }
.al-row.new b { color:#b91c1c; }
.al-time { color:#92400e; font-size:.74rem; white-space:nowrap; }
.bus-list-panel { background:#fff; border-radius:12px; border:1.5px solid #e5e7eb; padding:12px;
                  height:520px; overflow-y:auto; }
.bus-list-item  { display:flex; align-items:center; gap:10px; padding:10px 8px; border-radius:10px;
                  cursor:pointer; border:1.5px solid transparent; margin-bottom:4px; transition:.12s; }
.bus-list-item:hover { background:#f8fafc; border-color:#e5e7eb; }
.bus-list-item.sel  { background:#eff6ff; border-color:#bfdbfe; }
.bl-name { font-weight:600; font-size:.83rem; color:#1e293b; }
.bl-info { font-size:.73rem; color:#6b7280; margin-top:2px; }
.stu-table { width:100%; border-collapse:collapse; }
.stu-table th,.stu-table td { padding:9px 12px; text-align:left; font-size:.82rem; border-bottom:1px solid #f1f5f9; }
.stu-table th { background:#f8fafc; font-weight:600; color:#374151; font-size:.77rem; }
.stu-table tr:hover td { background:#fafbff; }
.stu-photo { width:30px; height:30px; border-radius:50%; object-fit:cover; }
.stu-initials { width:30px; height:30px; border-radius:50%; background:#eff6ff; color:#2563eb; font-size:.75rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
.shift-sel { border:1.5px solid #e2e8f0; border-radius:7px; padding:4px 8px; font-size:.78rem; background:#fff; cursor:pointer; }
.modal-ov { display:none; position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:999; align-items:center; justify-content:center; padding:16px; }
.modal-ov.show { display:flex; }
.modal-box { background:#fff; border-radius:16px; width:100%; max-width:540px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.modal-head { padding:18px 20px 16px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; }
.modal-title { font-weight:700; font-size:1rem; color:#1e293b; }
.modal-body { padding:20px; }
.modal-foot { padding:14px 20px; border-top:1px solid #f1f5f9; display:flex; gap:10px; justify-content:flex-end; }
.m-close { background:none; border:none; font-size:1.3rem; color:#94a3b8; cursor:pointer; padding:4px; }
.m-close:hover { color:#374151; }
.fld { margin-bottom:14px; }
.fld label { display:block; font-size:.8rem; font-weight:600; color:#374151; margin-bottom:5px; }
.fld input,.fld select,.fld textarea { width:100%; border:1.5px solid #e2e8f0; border-radius:9px; padding:9px 12px; font-size:.84rem; color:#1e293b; background:#fff; box-sizing:border-box; }
.fld input:focus,.fld select:focus,.fld textarea:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
/* Mobile-first block-stacking by default, grid only from 768px up — a
   max-width grid-template-columns override on this exact pattern was
   found unreliable in real Chrome elsewhere in this project (Teachers/
   Staff-Salary pages), so the safe direction is used here from the start. */
.fld-row { display:flex; flex-direction:column; gap:0; }
.fld-row .fld { margin-bottom:14px; }
.fld-row .fld:last-child { margin-bottom:0; }
@media(min-width:768px){
  .fld-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .fld-row .fld { margin-bottom:0; }
}
.key-box { background:#0f172a; color:#86efac; border-radius:10px; padding:12px 14px; font-family:monospace; font-size:.83rem; word-break:break-all; margin-top:8px; position:relative; }
.key-copy { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:#1e3a5f; color:#bfdbfe; border:none; border-radius:6px; padding:4px 10px; font-size:.73rem; cursor:pointer; }
.key-copy:hover { background:#1e40af; }
.shift-section { background:#f8fafc; border-radius:10px; padding:14px; margin-top:8px; }
.info-box { background:#eff6ff; border:1.5px solid #bfdbfe; border-radius:10px; padding:12px 14px; font-size:.82rem; color:#1e40af; margin-top:8px; }
.info-box code { background:#dbeafe; border-radius:4px; padding:2px 6px; font-size:.78rem; word-break:break-all; }
.page-actions { display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap; align-items:center; }
.search-box { flex:1; max-width:280px; position:relative; }
.search-box input { width:100%; padding:8px 12px 8px 34px; border:1.5px solid #e2e8f0; border-radius:9px; font-size:.83rem; }
.search-box i { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94a3b8; }

/* In-app confirm + toast — replace raw browser confirm()/alert(), which
   render as jarring unstyled native dialogs inside the WebView app,
   breaking the "native app" illusion completely for every destructive
   action on this page. */
.bt-toast { position:fixed; left:50%; bottom:24px; transform:translateX(-50%) translateY(20px);
  background:#1e293b; color:#fff; padding:12px 18px; border-radius:12px; font-size:.85rem; font-weight:500;
  box-shadow:0 8px 24px rgba(0,0,0,.25); z-index:1100; opacity:0; pointer-events:none;
  transition:opacity .2s ease, transform .2s ease; max-width:90vw; }
.bt-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
.bt-toast.err { background:#dc2626; }
@media(max-width:640px) { .map-layout{grid-template-columns:1fr;} #liveMap{height:320px;} .bus-list-panel{height:auto;max-height:none;} }

/* ══════════════════════════════════════════════════════════════════════
   Mobile native pass — 4 tabs, each needed a different fix: the tab bar
   → segmented pills, stats → horizontal carousel, the two dense tables
   (Route Assignments 9 cols, Students 7 cols) → card lists, the fully
   custom modal system (not the shared .edu-modal — see .modal-ov above)
   → full-screen sheets, and the search+action row → stacked full-width.
   ══════════════════════════════════════════════════════════════════════ */
@media (max-width: 767px) {
  /* Tabs → native segmented-pill track */
  .bt-tabs { flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none;
    border-bottom:none; background:#f1f5f9; border-radius:12px; padding:4px; gap:4px; }
  .bt-tabs::-webkit-scrollbar { display:none; }
  .bt-tab { border-radius:9px; margin-bottom:0; border-bottom:none; padding:9px 14px; font-size:.82rem; }
  .bt-tab.active { background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.08); border-bottom:none; }

  /* Stats → horizontal scroll carousel, hidden scrollbar (matches every
     other converted page's stat row — a visible scrollbar here read as
     unpolished per earlier feedback on this exact pattern). */
  .bt-stats { display:flex; flex-wrap:nowrap; overflow-x:auto; scroll-snap-type:x mandatory;
    -webkit-overflow-scrolling:touch; gap:10px; scrollbar-width:none; }
  .bt-stats::-webkit-scrollbar { display:none; }
  .bt-stat { flex:0 0 42%; scroll-snap-align:start; }

  /* Search + Add button rows: full-width, stacked, no more empty spacer div */
  .page-actions { flex-direction:column; align-items:stretch; }
  .search-box { max-width:none; }
  .page-actions .edu-btn { width:100%; justify-content:center; }
  .page-actions select#routeFilter { width:100%; }

  /* Fleet card grid: single column reads better than a squeezed 2-up */
  .bus-grid { grid-template-columns:1fr; }
  .bus-foot .edu-btn { flex:1; justify-content:center; }

  /* ── Route Assignments + Students tables → native card list ── */
  #routeTable thead, #stuTable thead { display:none; }
  #routeTable tbody tr:not(:has(td[colspan])),
  #stuTable tbody tr:not(:has(td[colspan])) {
    display:block; background:#fff; border-radius:12px; padding:12px 14px;
    margin-bottom:10px; box-shadow:0 1px 3px rgba(15,23,42,.06); border:1px solid #f1f5f9;
  }
  #routeTable tbody tr:last-child, #stuTable tbody tr:last-child { margin-bottom:0; }
  #routeTable td, #stuTable td {
    display:flex; justify-content:space-between; align-items:center; gap:12px;
    padding:6px 0; border:none !important; text-align:right;
  }
  #routeTable td[data-label]::before, #stuTable td[data-label]::before {
    content:attr(data-label); font-weight:700; color:#6b7280; font-size:.68rem;
    text-transform:uppercase; letter-spacing:.04em; text-align:left; flex-shrink:0;
  }
  /* admin.css's global "freeze last column" rule (position:sticky, meant for
     horizontally-scrolled tables) fights any card-list conversion of a table —
     cancel it, same fix already needed on other pages that hit this. */
  #routeTable tbody td:last-child, #stuTable tbody td:last-child {
    position:static !important; box-shadow:none !important;
  }
  #routeTable td:last-child { justify-content:flex-end; flex-wrap:wrap; }
  #routeTable td:last-child .edu-btn { flex:1; justify-content:center; }

  /* Students table: row-number column isn't useful in a card, and the
     Student photo+name cell is the card's real identity — promote it to
     a header row instead of another generic label:value line. */
  #stuTable td[data-label="#"] { display:none; }
  #stuTable td[data-label="Student"] {
    display:block; padding-bottom:10px; margin-bottom:8px; border-bottom:1px solid #f8fafc;
  }
  #stuTable td[data-label="Student"]::before { content:none; }

  /* ── Live Map: bigger touch targets, legend wraps cleanly ── */
  .map-layout > div:first-child { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .bus-list-item { padding:12px 10px; }

  /* ── Custom modal system (not the shared .edu-modal) → full-screen sheet ── */
  .modal-ov { padding:0; align-items:stretch; }
  .modal-box { max-width:none !important; width:100%; max-height:none; height:100%;
    border-radius:0; margin:0; display:flex; flex-direction:column; }
  .modal-head { flex-shrink:0; position:sticky; top:0; background:#fff; z-index:2;
    padding-top:calc(18px + var(--sbnav-safe-t, 0px)); }
  .modal-body { flex:1; overflow-y:auto; -webkit-overflow-scrolling:touch; }
  .modal-foot { flex-shrink:0; position:sticky; bottom:0; background:#fff; z-index:2;
    padding-bottom:calc(14px + var(--sbnav-safe-b, 0px)); box-shadow:0 -2px 12px rgba(15,23,42,.05); flex-wrap:wrap; }
  .modal-foot .edu-btn { flex:1; justify-content:center; }

  .bt-toast { left:16px; right:16px; bottom:calc(var(--sbnav-h, 0px) + var(--sbnav-safe-b, 0px) + 16px);
    transform:translateY(20px); max-width:none; text-align:center; }
  .bt-toast.show { transform:translateY(0); }
}
</style>
HTML;

require_once __DIR__ . '/school_header.php';

$pdo      = Database::connect();
$schoolId = (int)$user['school_id'];
$csrf     = csrfToken();
$activeTab = in_array($_GET['tab'] ?? '', ['fleet','routes','map','students','trips']) ? $_GET['tab'] : 'fleet';

// Load all routes for dropdowns (safe: van_routes may not have is_active in all setups)
$allRoutes = [];
try {
    $rq = $pdo->prepare("SELECT id, route_name, from_location, to_location FROM van_routes WHERE school_id=? ORDER BY route_name");
    $rq->execute([$schoolId]);
    $allRoutes = $rq->fetchAll();
} catch(\Throwable $e){ $allRoutes = []; }

// Load all drivers (safe: post column may vary)
$allDrivers = [];
try {
    $dq = $pdo->prepare("SELECT t.id, u.name, u.phone FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.school_id=? AND t.status='active' AND LOWER(t.post)='driver' ORDER BY u.name");
    $dq->execute([$schoolId]);
    $allDrivers = $dq->fetchAll();
} catch(\Throwable $e){ $allDrivers = []; }

// Load fleet count for stats
$fleetCount = 0;
try {
    $fc = $pdo->prepare("SELECT COUNT(*) FROM school_buses WHERE school_id=?");
    $fc->execute([$schoolId]);
    $fleetCount = (int)$fc->fetchColumn();
} catch(\Throwable $e){ $fleetCount = 0; }
?>

<div style="padding:0 0 40px;">

<!-- ── Tab Bar ────────────────────────────────────────────────────────────── -->
<div class="bt-tabs">
  <button class="bt-tab <?= $activeTab==='fleet'   ?'active':'' ?>" onclick="switchTab('fleet',this)"><i class="bi bi-bus-front-fill"></i> Fleet</button>
  <button class="bt-tab <?= $activeTab==='routes'  ?'active':'' ?>" onclick="switchTab('routes',this)"><i class="bi bi-map-fill"></i> Route Assignments</button>
  <button class="bt-tab <?= $activeTab==='map'     ?'active':'' ?>" onclick="switchTab('map',this)"><i class="bi bi-geo-alt-fill"></i> Live Map</button>
  <button class="bt-tab <?= $activeTab==='students'?'active':'' ?>" onclick="switchTab('students',this)"><i class="bi bi-people-fill"></i> Students</button>
  <button class="bt-tab <?= $activeTab==='trips'   ?'active':'' ?>" onclick="switchTab('trips',this)"><i class="bi bi-clock-history"></i> Trips</button>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- FLEET TAB -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="pane_fleet" class="bt-pane <?= $activeTab==='fleet'?'active':'' ?>">

  <div class="bt-stats" id="fleetStats">
    <div class="bt-stat"><div class="bt-stat-n" id="statTotal">—</div><div class="bt-stat-l">Total Buses</div></div>
    <div class="bt-stat"><div class="bt-stat-n" id="statActive">—</div><div class="bt-stat-l">Active</div></div>
    <div class="bt-stat"><div class="bt-stat-n" id="statLive" style="color:#22c55e">—</div><div class="bt-stat-l">GPS Live Now</div></div>
    <div class="bt-stat"><div class="bt-stat-n" id="statOffline" style="color:#94a3b8">—</div><div class="bt-stat-l">Offline / No GPS</div></div>
  </div>

  <div class="page-actions">
    <div class="search-box"><i class="bi bi-search"></i><input type="text" id="busSearch" placeholder="Search buses…" oninput="filterBuses(this.value)"></div>
    <div style="flex:1;"></div>
    <button class="edu-btn edu-btn-secondary" onclick="openLinksModal()"><i class="bi bi-link-45deg"></i> Driver Links</button>
    <button class="edu-btn edu-btn-secondary" onclick="openBusModal()"><i class="bi bi-plus-circle"></i> Add Bus (quick)</button>
    <button class="edu-btn edu-btn-primary" onclick="openBusWizard()"><i class="bi bi-magic"></i> Setup Wizard</button>
  </div>

  <div class="bus-grid" id="busGrid">
    <div style="text-align:center;padding:40px;color:#94a3b8;grid-column:1/-1;"><i class="bi bi-hourglass-split" style="font-size:1.5rem;"></i><br>Loading fleet…</div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- ROUTES TAB -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="pane_routes" class="bt-pane <?= $activeTab==='routes'?'active':'' ?>">
  <div class="page-actions">
    <span style="font-size:.84rem;color:#6b7280;"><?= count($allRoutes) ?> routes from Fee → Van Setup</span>
    <div style="flex:1;"></div>
    <button class="edu-btn edu-btn-primary" onclick="openAssignModal()"><i class="bi bi-plus-circle"></i> Assign Bus to Route</button>
  </div>
  <div style="background:#fff;border-radius:14px;border:1.5px solid #e5e7eb;overflow:hidden;">
    <table class="route-table" id="routeTable">
      <thead><tr>
        <th>Route</th><th>Students</th><th>Bus</th><th>Driver</th>
        <th>Pickup</th><th>Drop</th><th>Shifts</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody id="routeTableBody">
        <tr><td colspan="9" style="text-align:center;padding:40px;color:#94a3b8;"><i class="bi bi-hourglass-split"></i> Loading…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- LIVE MAP TAB -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="pane_map" class="bt-pane <?= $activeTab==='map'?'active':'' ?>">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:8px;font-size:.82rem;color:#6b7280;">
      <span class="gps-dot live"></span> Live (&lt;2 min)
      <span class="gps-dot recent" style="margin-left:8px;"></span> Recent (&lt;5 min)
      <span class="gps-dot offline" style="margin-left:8px;"></span> Offline
    </div>
    <div style="flex:1;"></div>
    <span id="mapRefreshStatus" style="font-size:.77rem;color:#94a3b8;"></span>
    <button class="edu-btn edu-btn-sm edu-btn-secondary" onclick="fitAll()"><i class="bi bi-arrows-fullscreen"></i> Fit all</button>
    <button class="edu-btn edu-btn-sm edu-btn-secondary" onclick="refreshMap()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    <button class="edu-btn edu-btn-sm edu-btn-secondary" onclick="openAlertSettings()"><i class="bi bi-shield-exclamation"></i> Alert settings</button>
  </div>
 <div id="alertFeed" style="display:none;margin:0 0 10px;border:1.5px solid #fde68a;background:#fffbeb;border-radius:12px;padding:10px 12px;font-size:.82rem;"></div>
  <div id="watchdogBanner" style="display:none;margin:0 0 10px;padding:10px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:.85rem;"></div>
  <div class="map-layout">
    <div class="bus-list-panel" id="mapBusList">
      <div style="font-weight:600;font-size:.82rem;color:#374151;margin-bottom:8px;padding:4px 8px;">Buses</div>
      <div id="mapBusItems" style="color:#94a3b8;font-size:.82rem;padding:8px;">Loading…</div>
      <div id="busDetail"></div>
    </div>
    <div class="map-wrap"><div id="liveMap"></div></div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- TRIPS TAB -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="pane_trips" class="bt-pane <?= $activeTab==='trips'?'active':'' ?>">
  <div class="page-actions" style="flex-wrap:wrap;gap:8px;">
    <label style="font-size:.8rem;color:#6b7280;">From <input type="date" id="tripFrom" class="form-control" style="display:inline-block;width:auto;"></label>
    <label style="font-size:.8rem;color:#6b7280;">To <input type="date" id="tripTo" class="form-control" style="display:inline-block;width:auto;"></label>
    <select id="tripBus" class="form-control" style="width:auto;"><option value="">All buses</option></select>
    <button class="edu-btn edu-btn-primary" onclick="loadTrips()"><i class="bi bi-search"></i> Show</button>
    <div style="flex:1;"></div>
    <button class="edu-btn edu-btn-secondary" onclick="exportTrips()"><i class="bi bi-download"></i> CSV</button>
  </div>
  <div id="tripSummary" style="font-size:.84rem;color:#374151;margin:0 0 10px;"></div>
  <div style="background:#fff;border-radius:14px;border:1.5px solid #e5e7eb;overflow:auto;">
    <table class="route-table">
      <thead><tr><th>Bus</th><th>Shift</th><th>Start</th><th>Duration</th><th>Distance</th><th>Max speed</th><th>Halts</th><th>Students ✔/✖</th><th>Ended</th><th></th></tr></thead>
      <tbody id="tripBody"><tr><td colspan="10" style="text-align:center;padding:30px;color:#94a3b8;">Loading…</td></tr></tbody>
    </table>
  </div>
  <div id="tripMapWrap" style="display:none;margin-top:12px;">
    <div class="map-wrap"><div id="tripMap" style="height:380px;border-radius:12px;"></div></div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- STUDENTS TAB -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="pane_students" class="bt-pane <?= $activeTab==='students'?'active':'' ?>">
  <div class="page-actions">
    <select id="routeFilter" class="edu-select" style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.83rem;" onchange="loadStudents()">
      <option value="">All Routes</option>
      <?php foreach ($allRoutes as $r): ?>
      <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['route_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div style="flex:1;"></div>
    <span id="stuCount" style="font-size:.82rem;color:#6b7280;"></span>
  </div>
  <div style="background:#fff;border-radius:14px;border:1.5px solid #e5e7eb;overflow:auto;">
    <table class="stu-table" id="stuTable">
      <thead><tr>
        <th>#</th><th>Student</th><th>Class</th><th>Route</th>
        <th>Bus</th><th>Shift</th><th>Home 📍</th>
      </tr></thead>
      <tbody id="stuTableBody">
        <tr><td colspan="7" style="text-align:center;padding:40px;color:#94a3b8;"><i class="bi bi-hourglass-split"></i> Loading…</td></tr>
      </tbody>
    </table>
  </div>
</div>

</div><!-- /wrapper -->

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- MODALS -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->

<!-- Add/Edit Bus Modal -->
<div class="modal-ov" id="busModal" onclick="if(event.target===this)closeBusModal()">
  <div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-head">
      <div class="modal-title" id="busModalTitle"><i class="bi bi-bus-front-fill"></i> Add Bus</div>
      <button class="m-close" onclick="closeBusModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div id="busMsg" style="display:none;padding:10px 14px;border-radius:8px;font-size:.83rem;margin-bottom:14px;"></div>
      <input type="hidden" id="busId">
      <div class="fld-row">
        <div class="fld"><label>Bus Name *</label><input type="text" id="busName" placeholder="e.g. School Express 1"></div>
        <div class="fld"><label>Bus Number *</label><input type="text" id="busNumber" placeholder="e.g. UP32 AB 1234"></div>
      </div>
      <div class="fld-row">
        <div class="fld"><label>Capacity (seats)</label><input type="number" id="busCap" value="40" min="1" max="100"></div>
        <div class="fld"><label>Status</label>
          <select id="busStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
      </div>
      <div class="fld"><label>GPS Device ID <span style="color:#94a3b8;font-weight:400;">(optional — IMEI or device serial)</span></label>
        <input type="text" id="busDeviceId" placeholder="e.g. 861234567890123">
      </div>
      <div class="fld"><label>Notes</label>
        <textarea id="busNotes" rows="2" placeholder="Any notes about this bus…" style="resize:vertical;"></textarea>
      </div>
      <div id="apiKeySection" style="display:none;">
        <div style="font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px;">GPS API Key</div>
        <div class="key-box" id="apiKeyBox">—<button class="key-copy" onclick="copyKey()">Copy</button></div>
        <div class="info-box" style="margin-top:10px;">
          Configure your GPS device to POST to:<br>
          <code id="gpsEndpoint"><?= BASE_URL ?>/api/gps_update.php?key=API_KEY&lat=XX&lng=YY&speed=SS</code><br><br>
          Replace <strong>API_KEY</strong> with the key above.
          <button class="edu-btn edu-btn-sm edu-btn-secondary" style="margin-top:8px;" onclick="regenKey()"><i class="bi bi-arrow-repeat"></i> Regenerate Key</button>
        </div>

        <div id="gpsLiveStatus" style="margin-top:12px;padding:9px 12px;border-radius:9px;font-size:.8rem;background:#f1f5f9;color:#475569;">Checking GPS status…</div>
        <div id="gpsLocalWarn" style="display:none;margin-top:8px;padding:9px 12px;border-radius:9px;font-size:.76rem;background:#fffbeb;color:#92400e;border:1px solid #fde68a;">
          You are on <strong>localhost</strong>. A phone cannot reach this address. Use your live domain (https), or the PC's WiFi IP (e.g. http://192.168.x.x/sszone) for testing. The browser tracker below also needs <strong>https</strong>.
        </div>

        <div style="margin-top:14px;border-top:1px dashed #e2e8f0;padding-top:12px;">
          <div style="font-size:.8rem;font-weight:600;color:#374151;margin-bottom:4px;"><i class="bi bi-phone-vibrate"></i> Driver Link — no app needed</div>
          <div style="font-size:.76rem;color:#6b7280;margin-bottom:6px;">Driver ka phone ek baar <strong>pair</strong> hota hai (ek baar chalne wala link, 24 ghante). Link mein koi key nahi hoti, aur phone ko kabhi bhi hataya ja sakta hai.</div>
          <button type="button" class="edu-btn edu-btn-sm edu-btn-primary" onclick="closeBusModal();openLinksModal()"><i class="bi bi-link-45deg"></i> Driver Links kholein</button>
        </div>

        <div style="margin-top:14px;border-top:1px dashed #e2e8f0;padding-top:12px;">
          <button type="button" class="edu-btn edu-btn-sm edu-btn-secondary" onclick="togglePhoneGps()" id="phoneGpsToggleBtn">
            <i class="bi bi-phone"></i> No GPS device on this bus? Use a free phone app instead
          </button>
          <div id="phoneGpsPanel" style="display:none;margin-top:12px;">
            <div class="info-box" style="background:#f0fdf4;border-color:#bbf7d0;color:#166534;">
              Give the driver/conductor's Android phone to run in the background during the route. No coding, no extra cost.
              <ol style="margin:10px 0 4px;padding-left:18px;line-height:1.7;">
                <li><strong>GPSLogger for Android</strong> (free, no ads) — not on the Play Store, so download it directly on that phone:<br>
                  <a href="<?= BASE_URL ?>/apps/GPSLogger.apk" download class="edu-btn edu-btn-sm edu-btn-primary" style="margin-top:6px;display:inline-flex;"><i class="bi bi-download"></i> Download GPSLogger APK</a><br>
                  <span style="font-size:.76rem;color:#374151;">After downloading, tap the file → allow "Install unknown apps" if asked → Install. This warning is normal since it's not from the Play Store — the app itself is safe (open-source, well known). <a href="https://github.com/mendhak/gpslogger" target="_blank" rel="noopener" style="color:#166534;">Source code &amp; license (GPL v2)</a>.</span>
                </li>
                <li>Open GPSLogger → tap the <strong>≡ menu</strong> → <strong>Log to Custom URL</strong> → turn it ON.</li>
                <li>Tap <strong>Custom URL</strong> field and paste this exact URL (already has this bus's key filled in):</li>
              </ol>
              <div class="key-box" id="phoneGpsUrlBox" style="color:#86efac;">—<button class="key-copy" onclick="copyPhoneGpsUrl()">Copy</button></div>
              <ol start="4" style="margin:10px 0 4px;padding-left:18px;line-height:1.7;">
                <li>Go back → set <strong>Logging interval</strong> to about 30 seconds (Performance &amp; Options).</li>
                <li>Tap <strong>Start Logging</strong>. Turn OFF battery optimization for GPSLogger (Android will ask, or set it in phone Settings → Apps → GPSLogger → Battery) so it keeps running with the screen locked.</li>
              </ol>
              <div style="margin-top:6px;font-size:.78rem;color:#374151;">That's it — this bus will start showing up as GPS Live on the Live Map tab, exactly like a hardware tracker.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="edu-btn edu-btn-secondary" onclick="closeBusModal()">Cancel</button>
      <button class="edu-btn edu-btn-primary" onclick="saveBus()"><i class="bi bi-check2"></i> <span id="busSubmitLabel">Add Bus</span></button>
    </div>
  </div>
</div>

<!-- Driver Links Modal: one tracker link per bus -->
<div class="modal-ov" id="linksModal" onclick="if(event.target===this)closeLinksModal()">
  <div class="modal-box" style="max-width:640px;" onclick="event.stopPropagation()">
    <div class="modal-head">
      <div class="modal-title"><i class="bi bi-link-45deg"></i> Driver Tracker Links</div>
      <button class="m-close" onclick="closeLinksModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div style="font-size:.8rem;color:#6b7280;margin-bottom:10px;">
        Har driver ka phone <strong>ek baar pair</strong> hota hai: "link banayein" dabayein aur sirf us bus ke driver ko QR / WhatsApp se bhejein.
        Link <strong>24 ghante</strong> tak aur <strong>sirf ek phone</strong> par chalta hai — forward ho jaaye to bhi kisi aur ke kaam ka nahi. Phone jud jaane ke baad driver
        seedha page kholta hai, link ki zaroorat nahi. Neeche har bus ke jude hue phone dikhte hain; kisi ko bhi <strong>Hatayein</strong> kar sakte hain.
      </div>
      <div id="linksLocalWarn" style="display:none;margin-bottom:10px;padding:9px 12px;border-radius:9px;font-size:.76rem;background:#fffbeb;color:#92400e;border:1px solid #fde68a;">
        Aap panel <strong>localhost</strong> par chala rahe hain — yahan bane link phone par nahi khulenge (phone ke liye <strong>https</strong> wali live site chahiye).
        Live site par link apne aap aapke domain se banenge. Localhost par test ke liye isi computer ke Chrome mein link khol sakte hain.
      </div>
      <div id="linksList" style="display:flex;flex-direction:column;gap:10px;">Loading…</div>
    </div>
    <div class="modal-foot">
      <button class="edu-btn edu-btn-secondary" onclick="closeLinksModal()">Close</button>
    </div>
  </div>
</div>

<!-- Assign Bus to Route Modal -->
<div class="modal-ov" id="assignModal" onclick="if(event.target===this)closeAssignModal()">
  <div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-head">
      <div class="modal-title"><i class="bi bi-map-fill"></i> <span id="assignModalTitle">Assign Bus to Route</span></div>
      <button class="m-close" onclick="closeAssignModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div id="assignMsg" style="display:none;padding:10px 14px;border-radius:8px;font-size:.83rem;margin-bottom:14px;"></div>
      <input type="hidden" id="assignId">
      <div class="fld">
        <label>Route *</label>
        <select id="assignRoute">
          <option value="">— Select Route —</option>
          <?php foreach ($allRoutes as $r): ?>
          <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['route_name']) ?> (<?= htmlspecialchars($r['from_location'].' → '.$r['to_location']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fld-row">
        <div class="fld">
          <label>Bus *</label>
          <select id="assignBus"><option value="">— Select Bus —</option></select>
        </div>
        <div class="fld">
          <label>Driver</label>
          <select id="assignDriver">
            <option value="">— No Driver —</option>
            <?php foreach ($allDrivers as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?><?= $d['phone'] ? ' · '.$d['phone'] : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="font-size:.8rem;font-weight:600;color:#374151;margin-bottom:8px;">Schedule</div>
      <div class="fld-row">
        <div class="fld"><label>Pickup Time</label><input type="time" id="assignPickup"></div>
        <div class="fld"><label>Drop Time</label><input type="time" id="assignDrop"></div>
      </div>
      <div class="fld-row">
        <div class="fld">
          <label>Shifts</label>
          <select id="assignShifts" onchange="toggleShifts(this.value)">
            <option value="1">1 Shift</option>
            <?php for ($n = 2; $n <= 5; $n++): ?>
            <option value="<?= $n ?>"><?= $n ?> Shifts</option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="fld"><label>Status</label>
          <select id="assignStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
      </div>
      <?php for ($n = 2; $n <= 5; $n++): ?>
      <div id="shiftBlock<?= $n ?>" style="display:none;">
        <div class="shift-section">
          <div style="font-size:.8rem;font-weight:600;color:#374151;margin-bottom:8px;">Shift <?= $n ?> Times</div>
          <div class="fld-row">
            <div class="fld"><label>Pickup (Shift <?= $n ?>)</label><input type="time" id="assignPickup<?= $n ?>"></div>
            <div class="fld"><label>Drop (Shift <?= $n ?>)</label><input type="time" id="assignDrop<?= $n ?>"></div>
          </div>
        </div>
      </div>
      <?php endfor; ?>
    </div>
    <div class="modal-foot">
      <button class="edu-btn edu-btn-secondary" onclick="closeAssignModal()">Cancel</button>
      <button class="edu-btn edu-btn-primary" onclick="saveAssignment()"><i class="bi bi-check2"></i> <span id="assignSubmitLabel">Assign</span></button>
    </div>
  </div>
</div>

<!-- Alert settings (per school) -->
<div class="modal-ov" id="alertSetModal" onclick="if(event.target===this)closeAlertSettings()">
  <div class="modal-box" style="max-width:520px;" onclick="event.stopPropagation()">
    <div class="modal-head">
      <div class="modal-title"><i class="bi bi-shield-exclamation"></i> Bus safety alerts</div>
      <button class="m-close" onclick="closeAlertSettings()">&times;</button>
    </div>
    <div class="modal-body">
      <div id="alertSetMsg" style="display:none;padding:10px 14px;border-radius:8px;font-size:.83rem;margin-bottom:14px;"></div>
      <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:6px;">🚀 Overspeed</div>
      <div class="fld-row">
        <div class="fld"><label>Speed limit (km/h)</label><input type="number" id="asSpeed" min="20" max="120"></div>
        <div class="fld"><label>Kitni der tak (sec)</label><input type="number" id="asSpeedSec" min="5" max="300"></div>
      </div>
      <div style="font-size:.8rem;font-weight:700;color:#374151;margin:6px 0;">🏫 School gate (bus pahunchi / nikli)</div>
      <div class="fld-row">
        <div class="fld"><label>Latitude</label><input id="asLat" placeholder="e.g. 26.8467"></div>
        <div class="fld"><label>Longitude</label><input id="asLng" placeholder="e.g. 80.9462"></div>
      </div>
      <div class="fld-row">
        <div class="fld"><label>Radius (m)</label><input type="number" id="asRadius" min="50" max="1000"></div>
        <div class="fld" style="display:flex;align-items:flex-end;"><button type="button" class="edu-btn edu-btn-sm edu-btn-secondary" onclick="alertUseMapCenter()">📍 Live Map ke beech wali jagah lein</button></div>
      </div>
      <label style="display:flex;gap:8px;align-items:center;font-size:.82rem;margin:4px 0 12px;"><input type="checkbox" id="asParents"> Parents ko bhi batayein (subah "school pahunchi", dopahar "school se nikli")</label>
      <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:6px;">🧭 Raaste se hatna (roz ke seekhe hue raaste se)</div>
      <div class="fld-row">
        <div class="fld"><label>Kitna door (m)</label><input type="number" id="asDev" min="150" max="3000"></div>
        <div class="fld"><label>Kitni der tak (sec)</label><input type="number" id="asDevSec" min="30" max="900"></div>
      </div>
      <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:6px;">🛑 Raaste mein rukna</div>
      <div class="fld-row">
        <div class="fld"><label>Driver se kaaran poochhein (min baad)</label><input type="number" id="asHaltAsk" min="1" max="30"></div>
        <div class="fld"><label>Jawab na mile to admin alert (min)</label><input type="number" id="asHaltAdmin" min="2" max="60"></div>
      </div>
      <div class="info-box">Alerts sirf aapke school ke admin panel aur aapke school ke parents ko jaate hain — kisi bahari service (WhatsApp/SMS) ko nahi.</div>
    </div>
    <div class="modal-foot">
      <button class="edu-btn edu-btn-secondary" onclick="closeAlertSettings()">Cancel</button>
      <button class="edu-btn edu-btn-primary" onclick="saveAlertSettings()"><i class="bi bi-check2"></i> Save</button>
    </div>
  </div>
</div>

<!-- Broadcast to the parents of one shift -->
<div class="modal-ov" id="bcastModal" onclick="if(event.target===this)closeBroadcast()">
  <div class="modal-box" style="max-width:520px;" onclick="event.stopPropagation()">
    <div class="modal-head">
      <div class="modal-title"><i class="bi bi-megaphone"></i> Parents ko suchna</div>
      <button class="m-close" onclick="closeBroadcast()">&times;</button>
    </div>
    <div class="modal-body">
      <div id="bcastMsg" style="display:none;padding:10px 14px;border-radius:8px;font-size:.83rem;margin-bottom:14px;"></div>
      <div id="bcastWho" style="font-size:.84rem;color:#334155;margin-bottom:8px;"></div>
      <div class="fld"><label>Message (badal sakte hain)</label><textarea id="bcastText" rows="5" maxlength="500"></textarea></div>
      <div style="font-size:.75rem;color:#64748b;">Push notification + student portal ke Messages tab mein jayega. "Nahi aaye" mark kiye gaye bachchon ko nahi.</div>
    </div>
    <div class="modal-foot">
      <button class="edu-btn edu-btn-secondary" onclick="closeBroadcast()">Cancel</button>
      <button class="edu-btn edu-btn-primary" id="bcastSend" onclick="sendBroadcast()"><i class="bi bi-send"></i> Sabko bhejein</button>
    </div>
  </div>
</div>

<!-- Confirm Modal (replaces raw browser confirm()) -->
<div class="modal-ov" id="confirmModal" onclick="if(event.target===this)closeConfirmModal()">
  <div class="modal-box" style="max-width:420px;" onclick="event.stopPropagation()">
    <div class="modal-head">
      <div class="modal-title" id="confirmTitle">Confirm</div>
      <button class="m-close" onclick="closeConfirmModal()">&times;</button>
    </div>
    <div class="modal-body">
      <p id="confirmMessage" style="margin:0;color:#374151;font-size:.87rem;line-height:1.55;"></p>
    </div>
    <div class="modal-foot">
      <button class="edu-btn edu-btn-secondary" onclick="closeConfirmModal()">Cancel</button>
      <button class="edu-btn" id="confirmActionBtn" style="background:#dc3545;color:#fff;">Delete</button>
    </div>
  </div>
</div>

<!-- Toast (replaces raw browser alert()) -->
<div id="btToast" class="bt-toast"></div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
const CSRF    = <?= json_encode($csrf) ?>;
const MAX_SHIFTS = 5;
const API     = <?= json_encode(BASE_URL . '/api/bus_actions.php') ?>;
const GPS_URL = <?= json_encode(BASE_URL . '/api/gps_update.php') ?>;
const DRIVER_URL = <?= json_encode(BASE_URL . '/api/driver_tracker.php') ?>;
const SCHOOL  = <?= $schoolId ?>;
const IS_ADMIN = <?= ($_SESSION['role'] ?? '') === ROLE_SCHOOL_ADMIN ? 'true' : 'false' ?>;   // UI only — the API checks again

// ── Tab Switching ─────────────────────────────────────────────────────────────
function switchTab(name, btn) {
  document.querySelectorAll('.bt-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.bt-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('pane_' + name).classList.add('active');
  btn.classList.add('active');

  // The live-map tab polls the server every 15s — it used to keep polling
  // forever in the background even after switching to another tab (the
  // interval was started once in initMap() and never cleared), a real
  // battery/network drain on a phone. Now it only runs while Map is active.
  if (_mapInterval) { clearInterval(_mapInterval); _mapInterval = null; }

  if (name === 'fleet')    loadFleet();
  if (name === 'routes')   loadRoutes();
  if (name === 'map')      { initMap(); setTimeout(() => _map && _map.invalidateSize(), 60); refreshMap(); _mapInterval = setInterval(refreshMap, 15000); }
  if (name === 'students') loadStudents();
  if (name === 'trips')    initTrips();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function esc(s) { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
function fmt12(t) {
  if (!t) return '—';
  const [h,m] = t.split(':');
  const hh = +h; const mm = m;
  return (hh===0?12:hh>12?hh-12:hh)+':'+mm+(hh<12?'am':'pm');
}
async function api(action, data={}) {
  const fd = new FormData();
  fd.append('action', action);
  fd.append('csrf_token', CSRF);
  Object.entries(data).forEach(([k,v]) => fd.append(k, v));
  try {
    const r = await fetch(API, {method:'POST', body:fd, credentials:'same-origin'});
    const txt = await r.text();
    try { return JSON.parse(txt); }
    catch (e) { return {success:false, message: r.ok ? 'Unexpected server response. Please reload the page.' : 'Server error (' + r.status + ').'}; }
  } catch (e) {
    return {success:false, message:'Network error. Check your connection.'};
  }
}
function showMsg(el, ok, text) {
  el.style.display = 'block';
  el.style.background = ok ? '#dcfce7' : '#fef2f2';
  el.style.color = ok ? '#16a34a' : '#dc2626';
  el.style.border = ok ? '1.5px solid #bbf7d0' : '1.5px solid #fecaca';
  el.textContent = text;
}

// In-app confirm + toast — replace raw confirm()/alert() everywhere on this
// page so destructive actions and errors feel like part of the app instead
// of the browser interrupting it.
let _confirmCallback = null;
function showConfirm(title, message, onConfirm, actionLabel) {
  document.getElementById('confirmTitle').textContent = title;
  document.getElementById('confirmMessage').textContent = message;
  document.getElementById('confirmActionBtn').textContent = actionLabel || 'Delete';
  _confirmCallback = onConfirm;
  document.getElementById('confirmModal').classList.add('show');
}
function closeConfirmModal() {
  document.getElementById('confirmModal').classList.remove('show');
  _confirmCallback = null;
}
document.getElementById('confirmActionBtn').addEventListener('click', () => {
  const cb = _confirmCallback;
  closeConfirmModal();
  if (cb) cb();
});

let _toastTimer = null;
function showToast(msg, ok = true) {
  const el = document.getElementById('btToast');
  el.textContent = msg;
  el.classList.toggle('err', !ok);
  el.classList.add('show');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => el.classList.remove('show'), 3000);
}

// ════════════════════════════════════════════════════════════════════════
// FLEET
// ════════════════════════════════════════════════════════════════════════
let _buses = [];

async function loadFleet() {
  const r = await api('get_fleet');
  if (!r.success) return;
  _buses = r.buses || [];
  renderFleet(_buses);
}

function renderFleet(buses) {
  const live = buses.filter(b => b.gps_status === 'live').length;
  const active = buses.filter(b => b.status === 'active').length;
  const offline = buses.filter(b => !b.last_seen || b.gps_status === 'offline' || b.gps_status === 'never').length;
  document.getElementById('statTotal').textContent   = buses.length;
  document.getElementById('statActive').textContent  = active;
  document.getElementById('statLive').textContent    = live;
  document.getElementById('statOffline').textContent = offline;

  const grid = document.getElementById('busGrid');
  if (!buses.length) {
    grid.innerHTML = '<div style="text-align:center;padding:60px;color:#94a3b8;grid-column:1/-1;"><i class="bi bi-bus-front" style="font-size:2.5rem;"></i><br><br>No buses added yet.<br>Click <strong>Setup Wizard</strong> to add your first bus step by step.</div>';
    return;
  }
  grid.innerHTML = buses.map(b => {
    const gs = b.gps_status;
    const gLabel = gs==='live'?'GPS Live':gs==='recent'?'GPS Recent':gs==='offline'?'GPS Offline':'No GPS';
    const ageStr = b.gps_age != null ? (b.gps_age < 60 ? b.gps_age+'s ago' : Math.round(b.gps_age/60)+'m ago') : '';
    return `<div class="bus-card" data-name="${esc(b.bus_name)} ${esc(b.bus_number)}">
      <div class="bus-card-top">
        <div class="bus-icon"><i class="bi bi-bus-front-fill"></i></div>
        <div>
          <div class="bus-name">${esc(b.bus_name)}</div>
          <div class="bus-num">${esc(b.bus_number)}</div>
        </div>
        <div style="display:flex;align-items:flex-start;gap:4px;margin-left:auto;">
          <span class="gps-dot ${gs}"></span>
        </div>
      </div>
      <div class="bus-meta">
        <span class="bt-badge ${gs}">${gLabel}${ageStr?' · '+ageStr:''}</span>
        <span class="bt-badge cap"><i class="bi bi-people-fill"></i> ${b.capacity} seats</span>
        <span class="bt-badge active">${b.route_count} route${b.route_count!=1?'s':''}</span>
      </div>
      <div class="bus-foot">
        <button class="edu-btn edu-btn-sm edu-btn-secondary" onclick="openBusWizard(${b.id},5)" title="Setup health check"><i class="bi bi-heart-pulse"></i> Check</button>
        <button class="edu-btn edu-btn-sm edu-btn-secondary" onclick="editBusById(${b.id})"><i class="bi bi-pencil"></i> Edit</button>
        <button class="edu-btn edu-btn-sm" style="background:#fef2f2;color:#dc2626;border:1.5px solid #fecaca;" onclick="deleteBus(${b.id})"><i class="bi bi-trash"></i></button>
      </div>
    </div>`;
  }).join('');
}

function filterBuses(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.bus-card').forEach(c => {
    c.style.display = c.dataset.name.toLowerCase().includes(q) ? '' : 'none';
  });
}

// ── Bus Modal ─────────────────────────────────────────────────────────────────
let _editingBusId = null;
function openBusModal(bus=null) {
  _editingBusId = null;
  document.getElementById('busId').value = '';
  document.getElementById('busName').value = '';
  document.getElementById('busNumber').value = '';
  document.getElementById('busCap').value = 40;
  document.getElementById('busDeviceId').value = '';
  document.getElementById('busNotes').value = '';
  document.getElementById('busStatus').value = 'active';
  document.getElementById('busModalTitle').innerHTML = '<i class="bi bi-bus-front-fill"></i> Add Bus';
  document.getElementById('busSubmitLabel').textContent = 'Add Bus';
  document.getElementById('apiKeySection').style.display = 'none';
  document.getElementById('busMsg').style.display = 'none';
  document.getElementById('phoneGpsPanel').style.display = 'none';
  stopGpsStatusWatch();
  document.getElementById('busModal').classList.add('show');
}

function editBusById(id) {
  const b = _buses.find(x => x.id == id);
  if (b) editBus(b);
}

function editBus(b) {
  _editingBusId = b.id;
  document.getElementById('busId').value = b.id;
  document.getElementById('busName').value = b.bus_name;
  document.getElementById('busNumber').value = b.bus_number;
  document.getElementById('busCap').value = b.capacity;
  document.getElementById('busDeviceId').value = b.gps_device_id || '';
  document.getElementById('busNotes').value = b.notes || '';
  document.getElementById('busStatus').value = b.status;
  document.getElementById('busModalTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit Bus';
  document.getElementById('busSubmitLabel').textContent = 'Save Changes';
  document.getElementById('busMsg').style.display = 'none';
  document.getElementById('phoneGpsPanel').style.display = 'none';
  if (b.gps_api_key) {
    document.getElementById('apiKeySection').style.display = 'block';
    document.getElementById('apiKeyBox').childNodes[0].textContent = b.gps_api_key;
    setPhoneGpsKey(b.gps_api_key);
  } else {
    document.getElementById('apiKeySection').style.display = 'none';
  }
  document.getElementById('busModal').classList.add('show');
  if (b.gps_api_key) startGpsStatusWatch(b.id); else stopGpsStatusWatch();
}

function closeBusModal() { stopGpsStatusWatch(); document.getElementById('busModal').classList.remove('show'); }

async function saveBus() {
  const msg = document.getElementById('busMsg');
  const data = {
    id:         document.getElementById('busId').value,
    bus_name:   document.getElementById('busName').value.trim(),
    bus_number: document.getElementById('busNumber').value.trim(),
    capacity:   document.getElementById('busCap').value,
    gps_device_id: document.getElementById('busDeviceId').value.trim(),
    notes:      document.getElementById('busNotes').value.trim(),
    status:     document.getElementById('busStatus').value,
  };
  if (!data.bus_name) { showMsg(msg,false,'Bus name is required.'); return; }
  if (!data.bus_number) { showMsg(msg,false,'Bus number is required.'); return; }

  const r = await api('save_bus', data);
  if (!r.success) { showMsg(msg,false,r.message); return; }
  showMsg(msg,true,r.message);
  loadFleet();
  if (r.gps_api_key) {
    document.getElementById('apiKeySection').style.display = 'block';
    document.getElementById('apiKeyBox').childNodes[0].textContent = r.gps_api_key;
    setPhoneGpsKey(r.gps_api_key);
    document.getElementById('busSubmitLabel').textContent = 'Save Changes';
    document.getElementById('busId').value = r.id;
  } else {
    setTimeout(closeBusModal, 1200);
  }
}

function deleteBus(id) {
  const b = _buses.find(x => x.id == id);
  showConfirm('Delete Bus?', `Delete bus "${b ? b.bus_name : ''}"? This cannot be undone.`, async () => {
    const r = await api('delete_bus', {id});
    if (!r.success) { showToast(r.message, false); return; }
    showToast('Bus deleted.');
    loadFleet();
  });
}

// Shared copy helper with a working fallback — navigator.clipboard can be
// unavailable or its promise can reject in some WebViews with no console
// error at all, which previously left the button silently never updating
// (no crash, just no feedback and no actual copy). The hidden-textarea +
// execCommand path always works, matching icard/data_collect.php's pattern.
function copyTextWithFallback(text, btn) {
  const done = () => { if (btn) { btn.textContent = 'Copied!'; setTimeout(() => { btn.textContent = 'Copy'; }, 2000); } };
  const fail = () => { if (btn) { btn.textContent = 'Copy failed'; setTimeout(() => { btn.textContent = 'Copy'; }, 2000); } };
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(done).catch(() => copyTextFallback(text, done, fail));
  } else {
    copyTextFallback(text, done, fail);
  }
}
function copyTextFallback(text, done, fail) {
  const t = document.createElement('textarea');
  t.value = text;
  document.body.appendChild(t);
  t.select();
  try { document.execCommand('copy') ? done() : fail(); }
  catch (e) { fail(); }
  document.body.removeChild(t);
}

function copyKey() {
  const key = document.getElementById('apiKeyBox').childNodes[0].textContent;
  // Was document.querySelector('.key-copy') — matched whichever .key-copy button
  // happens to be first in the DOM (there are two: this one and the phone-GPS
  // URL's), only "working" by coincidence of markup order. Scoped properly here.
  const btn = document.querySelector('#apiKeyBox .key-copy');
  copyTextWithFallback(key, btn);
}

// BASE_URL is relative on many setups ("/sszone"), but a phone needs the full address.
function absUrl(u) {
  if (/^https?:\/\//i.test(u)) return u;
  return location.origin + (u.charAt(0) === '/' ? '' : '/') + u;
}
// su=ms: GPSLogger reports speed in m/s (server converts to km/h). acc = accuracy in metres.
function phoneGpsUrl(key) {
  return absUrl(GPS_URL) + '?key=' + key + '&lat=%LAT&lng=%LON&speed=%SPD&heading=%DIR&acc=%ACC&su=ms';
}
// (Old "?key=" driver links were removed: they exposed the bus key. Drivers pair their phone with a one-time link.)
function setPhoneGpsKey(key) {
  const box = document.getElementById('phoneGpsUrlBox');
  if (box) box.childNodes[0].textContent = key ? phoneGpsUrl(key) : '—';
  const ep = document.getElementById('gpsEndpoint');
  if (ep) ep.textContent = absUrl(GPS_URL) + '?key=API_KEY&lat=XX&lng=YY&speed=SS';
  const lw = document.getElementById('gpsLocalWarn');
  if (lw) lw.style.display = /^(localhost|127\.0\.0\.1)$/.test(location.hostname) ? 'block' : 'none';
}


// Live "is this bus sending GPS?" line inside the bus modal
let _gpsStatusTimer = null;
function stopGpsStatusWatch() { if (_gpsStatusTimer) { clearInterval(_gpsStatusTimer); _gpsStatusTimer = null; } }
function startGpsStatusWatch(busId) {
  stopGpsStatusWatch();
  const tick = async () => {
    const el = document.getElementById('gpsLiveStatus');
    if (!el || !document.getElementById('busModal').classList.contains('show')) { stopGpsStatusWatch(); return; }
    const r = await api('get_fleet');
    if (!r.success) return;
    const b = (r.buses || []).find(x => x.id == busId);
    if (!b) return;
    const age = b.gps_age != null ? parseInt(b.gps_age) : null;
    const t = age == null ? '' : (age < 60 ? age + ' sec' : Math.round(age / 60) + ' min');
    if (b.gps_status === 'live')        { el.style.background='#dcfce7'; el.style.color='#166534'; el.textContent = '✅ Connected — last location ' + t + ' ago'; }
    else if (b.gps_status === 'recent') { el.style.background='#fef9c3'; el.style.color='#854d0e'; el.textContent = '🟡 Last location ' + t + ' ago — check that tracking is still running on the phone'; }
    else if (b.gps_status === 'offline'){ el.style.background='#fee2e2'; el.style.color='#991b1b'; el.textContent = '🔴 No location for ' + t + ' — tracking has stopped or the phone has no internet'; }
    else                                { el.style.background='#f1f5f9'; el.style.color='#475569'; el.textContent = '⏳ No location received yet — start tracking on the phone'; }
  };
  tick();
  _gpsStatusTimer = setInterval(tick, 5000);
}
function togglePhoneGps() {
  const panel = document.getElementById('phoneGpsPanel');
  panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
function copyPhoneGpsUrl() {
  const url = document.getElementById('phoneGpsUrlBox').childNodes[0].textContent;
  const btn = document.querySelector('#phoneGpsUrlBox .key-copy');
  copyTextWithFallback(url, btn);
}

// ── Driver Links modal (one link per bus) ────────────────────────────────────
async function openLinksModal() {
  document.getElementById('linksLocalWarn').style.display = /^(localhost|127\.0\.0\.1)$/.test(location.hostname) ? 'block' : 'none';
  document.getElementById('linksModal').classList.add('show');
  document.getElementById('linksList').textContent = 'Loading…';
  await loadFleet();
  renderLinks();
}
function closeLinksModal() { document.getElementById('linksModal').classList.remove('show'); }

// Driver links are ONE-TIME pairing links (24 h): the phone that opens it first gets its own token, then the
// link is dead. No bus key is ever put in a link. Paired phones are listed per bus and can be removed.
let _pairLinks = {}, _devices = [];
function pairUrl(code) { return absUrl(DRIVER_URL) + '#p=' + code; }
async function renderLinks() {
  const el = document.getElementById('linksList');
  if (!_buses.length) { el.innerHTML = '<div style="color:#94a3b8;font-size:.85rem;">No buses yet. Add a bus first.</div>'; return; }
  const dv = await api('driver_devices');
  _devices = dv.devices || [];
  const badge = {live:['#dcfce7','#166534','GPS Live'], recent:['#fef9c3','#854d0e','Recent'], offline:['#fee2e2','#991b1b','Offline'], never:['#f1f5f9','#475569','No GPS yet']};
  el.innerHTML = _buses.map(b => {
    const bd = badge[b.gps_status] || badge.never;
    const phones = _devices.filter(d => d.bus_id == b.id);
    const ago = m => m < 2 ? 'abhi' : m < 60 ? m + ' min pehle' : m < 2880 ? Math.round(m / 60) + ' ghante pehle' : Math.round(m / 1440) + ' din pehle';
    const phoneHtml = phones.length ? phones.map(d => `<div style="display:flex;align-items:center;gap:8px;font-size:.76rem;padding:4px 0;">
        📱 <span style="flex:1">${esc(d.label || 'Phone')} · jode ${esc(String(d.created_at).slice(0, 10))} · last ${ago(+d.idle_min)}</span>
        ${IS_ADMIN ? `<button class="edu-btn edu-btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:2px 8px;" onclick="revokeDevice(${d.id})">Hatayein</button>` : ''}</div>`).join('')
      : '<div style="font-size:.76rem;color:#94a3b8;padding:4px 0;">Abhi koi phone juda nahi hai.</div>';
    const pl = _pairLinks[b.id];
    const linkHtml = pl ? `<div style="margin-top:8px;font-family:monospace;font-size:.72rem;background:#0f172a;color:#93c5fd;border-radius:8px;padding:8px 10px;word-break:break-all;">${esc(pairUrl(pl))}</div>
      <div style="font-size:.72rem;color:#b45309;margin-top:4px;">⏱ 24 ghante tak · sirf ek phone par chalega · sirf driver ko bhejein</div>
      <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">
        <button class="edu-btn edu-btn-sm edu-btn-secondary" onclick="copyPairLink(${b.id}, this)"><i class="bi bi-clipboard"></i> Copy</button>
        <button class="edu-btn edu-btn-sm edu-btn-secondary" onclick="sharePairLink(${b.id})" style="color:#16a34a;"><i class="bi bi-whatsapp"></i> WhatsApp</button>
        <button class="edu-btn edu-btn-sm edu-btn-secondary" onclick="toggleBusQr(${b.id}, this)"><i class="bi bi-qr-code"></i> QR</button>
      </div><div id="qr_${b.id}" style="display:none;margin-top:10px;text-align:center;"></div>` : '';
    return `<div style="border:1.5px solid #e5e7eb;border-radius:12px;padding:12px;">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <strong>${esc(b.bus_name)}</strong><span style="color:#94a3b8;font-size:.8rem;">${esc(b.bus_number)}</span>
        <span style="margin-left:auto;background:${bd[0]};color:${bd[1]};font-size:.7rem;font-weight:600;padding:2px 8px;border-radius:99px;">${bd[2]}</span>
        ${b.status !== 'active' ? '<span style="background:#fee2e2;color:#991b1b;font-size:.7rem;padding:2px 8px;border-radius:99px;">Inactive bus</span>' : ''}
      </div>
      <div style="margin-top:6px;">${phoneHtml}</div>
      ${IS_ADMIN && b.status === 'active' ? `<button class="edu-btn edu-btn-sm edu-btn-primary" style="margin-top:6px;" onclick="makePairLink(${b.id})"><i class="bi bi-link-45deg"></i> ${pl ? 'Naya link banayein' : 'Driver phone jodne ka link banayein'}</button>` : ''}
      ${linkHtml}</div>`;
  }).join('');
}
async function makePairLink(busId) {
  const r = await api('driver_pair_link', {bus_id: busId});
  if (!r.success) { showToast(r.message || 'Link nahi bana.', false); return; }
  _pairLinks[busId] = r.code;
  renderLinks();
}
function pairText(b) {
  return '🚌 ' + b.bus_name + ' (' + b.bus_number + ') — driver phone jodne ka link (sirf ek baar, 24 ghante tak; kisi ko forward na karein):\n'
    + pairUrl(_pairLinks[b.id]) + '\n\nChrome mein kholein → Location Allow → shift tick → "Trip Shuru Karein". Agli baar se seedha page khulega.';
}
function copyPairLink(id, btn) {
  if (!_pairLinks[id]) return;
  const orig = btn.innerHTML;
  copyTextWithFallback(pairUrl(_pairLinks[id]), null);
  btn.textContent = 'Copied!'; setTimeout(() => { btn.innerHTML = orig; }, 1800);
}
function sharePairLink(id) {
  const b = _buses.find(x => x.id == id); if (!b || !_pairLinks[id]) return;
  window.open('https://wa.me/?text=' + encodeURIComponent(pairText(b)), '_blank', 'noopener');
}
function copyAllLinks(btn) {
  showToast('Suraksha ke liye har bus ka link alag se banayein aur sirf us bus ke driver ko bhejein.', false);
}
function revokeDevice(id) {
  showConfirm('Phone hatayein?', 'Is phone se ab is bus ki location ya students nahi dikhenge. Dobara jodne ke liye naya link lagega.', async () => {
    const r = await api('driver_revoke', {id});
    showToast(r.message, r.success);
    renderLinks();
  }, 'Hatayein');
}
async function makeKeyForBus(id) {
  const r = await api('regen_key', {id});
  if (!r.success) { showToast(r.message || 'Could not generate link.', false); return; }
  await loadFleet(); renderLinks();
}
// QR code (loaded on demand; if the library can't load, the other buttons still work)
function loadQrLib(cb) {
  if (window.qrcode) return cb(true);
  const s = document.createElement('script');
  s.src = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js';
  s.onload = () => cb(!!window.qrcode); s.onerror = () => cb(false);
  document.head.appendChild(s);
}
function toggleBusQr(id, btn) {
  const box = document.getElementById('qr_' + id);
  if (!box) return;
  if (box.style.display === 'block') { box.style.display = 'none'; return; }
  if (!_pairLinks[id]) return;
  loadQrLib(ok => {
    if (!ok) { showToast('QR library could not load (check internet). Use Copy / WhatsApp instead.', false); return; }
    const qr = window.qrcode(0, 'M'); qr.addData(pairUrl(_pairLinks[id])); qr.make();
    box.innerHTML = qr.createSvgTag(5, 2) + '<div style="font-size:.72rem;color:#6b7280;margin-top:4px;">Driver ke phone ke camera se scan karein (ek hi baar chalega)</div>';
    box.style.display = 'block';
  });
}

function regenKey() {
  showConfirm('Regenerate GPS Key?', 'Regenerate GPS API key? The old key will stop working immediately.', async () => {
    const r = await api('regen_key', {id: document.getElementById('busId').value});
    if (!r.success) { showToast(r.message, false); return; }
    document.getElementById('apiKeyBox').childNodes[0].textContent = r.gps_api_key;
    setPhoneGpsKey(r.gps_api_key);
    showToast('GPS key regenerated. Update the new key on the GPS device / phone.');
  }, 'Regenerate');
}

// ════════════════════════════════════════════════════════════════════════
// ROUTES
// ════════════════════════════════════════════════════════════════════════
let _assignments = {};
let _routesData = [];

function shiftTimes(a, key) {
  if (!a) return '—';
  const cnt = parseInt(a.shift_count) || 1;
  const out = [];
  for (let n = 1; n <= cnt; n++) {
    const v = a[key + (n === 1 ? '' : n)];
    out.push(cnt > 1
      ? `<div style="white-space:nowrap;"><span style="color:#94a3b8;font-size:.7rem;">S${n}</span> ${v ? fmt12(v) : '—'}</div>`
      : (v ? fmt12(v) : '—'));
  }
  return out.join('');
}

async function loadRoutes() {
  const [aRes, bRes] = await Promise.all([api('get_assignments'), api('get_fleet')]);
  _assignments = {};
  (aRes.assignments || []).forEach(a => { _assignments[a.route_id] = a; });
  const busMap = {};
  (bRes.buses || []).forEach(b => { busMap[b.id] = b; });

  // Populate bus dropdown
  const sel = document.getElementById('assignBus');
  sel.innerHTML = '<option value="">— Select Bus —</option>' +
    (bRes.buses||[]).filter(b => b.status==='active' || Object.values(_assignments).some(a => a.bus_id==b.id)).map(b =>
      `<option value="${b.id}">${esc(b.bus_name)} (${esc(b.bus_number)})</option>`).join('');

  _routesData = <?= json_encode($allRoutes) ?>;
  const tbody = document.getElementById('routeTableBody');

  if (!_routesData.length) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">No van routes configured. Set up routes in Fees → Van Setup first.</td></tr>';
    return;
  }

  tbody.innerHTML = _routesData.map(r => {
    const a = _assignments[r.id];
    return `<tr>
      <td data-label="Route"><strong>${esc(r.route_name)}</strong><br><span style="font-size:.73rem;color:#94a3b8;">${esc(r.from_location)} → ${esc(r.to_location)}</span></td>
      <td data-label="Students"><span class="bt-badge cap">${a ? a.student_count : '?'} students</span></td>
      <td data-label="Bus">${a ? `<span style="font-weight:600;">${esc(a.bus_name)}</span><br><span style="font-size:.73rem;color:#94a3b8;">${esc(a.bus_number)}</span>` : '<span class="assign-badge no">Not assigned</span>'}</td>
      <td data-label="Driver">${a && a.driver_name ? esc(a.driver_name) : '<span style="color:#94a3b8;">—</span>'}</td>
      <td data-label="Pickup">${shiftTimes(a,'pickup_time')}</td>
      <td data-label="Drop">${shiftTimes(a,'drop_time')}</td>
      <td data-label="Shifts">${a ? a.shift_count+' shift'+(a.shift_count>1?'s':'') : '—'}</td>
      <td data-label="Status">${a ? `<span class="assign-badge ${a.status==='active'?'yes':'no'}">${a.status}</span>` : '—'}</td>
      <td>
        <button class="edu-btn edu-btn-sm edu-btn-secondary" onclick="editAssignment(${r.id})">${a?'<i class="bi bi-pencil"></i> Edit':'<i class="bi bi-plus-circle"></i> Assign'}</button>
        ${a ? `<button class="edu-btn edu-btn-sm" style="background:#fef2f2;color:#dc2626;border:1.5px solid #fecaca;margin-left:4px;" onclick="deleteAssignment(${a.id},${r.id})"><i class="bi bi-trash"></i></button>` : ''}
      </td>
    </tr>`;
  }).join('');
}

function openAssignModal() {
  document.getElementById('assignId').value = '';
  document.getElementById('assignRoute').value = '';
  document.getElementById('assignBus').value = '';
  document.getElementById('assignDriver').value = '';
  document.getElementById('assignPickup').value = '';
  document.getElementById('assignDrop').value = '';
  for (let n = 2; n <= MAX_SHIFTS; n++) {
    document.getElementById('assignPickup'+n).value = '';
    document.getElementById('assignDrop'+n).value = '';
  }
  document.getElementById('assignShifts').value = '1';
  document.getElementById('assignStatus').value = 'active';
  toggleShifts(1);
  document.getElementById('assignMsg').style.display = 'none';
  document.getElementById('assignModalTitle').textContent = 'Assign Bus to Route';
  document.getElementById('assignSubmitLabel').textContent = 'Assign';
  document.getElementById('assignModal').classList.add('show');
}

function editAssignment(routeId) {
  const a = _assignments[routeId];
  document.getElementById('assignId').value = a ? a.id : '';
  document.getElementById('assignRoute').value = routeId;
  document.getElementById('assignBus').value = a ? a.bus_id : '';
  document.getElementById('assignDriver').value = a ? (a.driver_id || '') : '';
  document.getElementById('assignPickup').value = a ? (a.pickup_time || '') : '';
  document.getElementById('assignDrop').value = a ? (a.drop_time || '') : '';
  for (let n = 2; n <= MAX_SHIFTS; n++) {
    document.getElementById('assignPickup'+n).value = a ? (a['pickup_time'+n] || '') : '';
    document.getElementById('assignDrop'+n).value   = a ? (a['drop_time'+n]   || '') : '';
  }
  document.getElementById('assignShifts').value = a ? a.shift_count : '1';
  document.getElementById('assignStatus').value = a ? a.status : 'active';
  toggleShifts(a ? a.shift_count : 1);
  document.getElementById('assignMsg').style.display = 'none';
  document.getElementById('assignModalTitle').textContent = a ? 'Edit Assignment' : 'Assign Bus to Route';
  document.getElementById('assignSubmitLabel').textContent = a ? 'Save Changes' : 'Assign';
  document.getElementById('assignModal').classList.add('show');
}

function closeAssignModal() { document.getElementById('assignModal').classList.remove('show'); }
function toggleShifts(v) {
  v = parseInt(v) || 1;
  for (let n = 2; n <= MAX_SHIFTS; n++)
    document.getElementById('shiftBlock'+n).style.display = n <= v ? 'block' : 'none';
}

async function saveAssignment() {
  const msg = document.getElementById('assignMsg');
  const data = {
    id:           document.getElementById('assignId').value,
    route_id:     document.getElementById('assignRoute').value,
    bus_id:       document.getElementById('assignBus').value,
    driver_id:    document.getElementById('assignDriver').value,
    pickup_time:  document.getElementById('assignPickup').value,
    drop_time:    document.getElementById('assignDrop').value,
    shift_count:  document.getElementById('assignShifts').value,
    status:       document.getElementById('assignStatus').value,
  };
  for (let n = 2; n <= MAX_SHIFTS; n++) {
    data['pickup_time'+n] = document.getElementById('assignPickup'+n).value;
    data['drop_time'+n]   = document.getElementById('assignDrop'+n).value;
  }
  if (!data.route_id) { showMsg(msg,false,'Please select a route.'); return; }
  if (!data.bus_id)   { showMsg(msg,false,'Please select a bus.'); return; }

  const r = await api('save_assignment', data);
  if (!r.success) { showMsg(msg,false,r.message); return; }
  showMsg(msg,true,r.message);
  setTimeout(() => { closeAssignModal(); loadRoutes(); }, 900);
}

function deleteAssignment(id, routeId) {
  const route = _routesData.find(x => x.id == routeId);
  showConfirm('Remove Assignment?', `Remove bus assignment for route "${route ? route.route_name : ''}"?`, async () => {
    const r = await api('delete_assignment', {id});
    if (!r.success) { showToast(r.message, false); return; }
    showToast('Assignment removed.');
    loadRoutes();
  }, 'Remove');
}

// ════════════════════════════════════════════════════════════════════════
// LIVE MAP
// ════════════════════════════════════════════════════════════════════════
let _map = null, _mapMarkers = {}, _mapInit = false, _mapInterval = null;
let _mapBusy = false, _mapFitted = false, _trailLayers = {}, _accCircles = {};

const iconColors = {live:'#22c55e', recent:'#f59e0b', offline:'#94a3b8', never:'#94a3b8'};

function busIcon(status) {
  const c = iconColors[status] || '#94a3b8';
  return L.divIcon({
    className: '',
    html: `<div style="background:${c};color:#fff;border-radius:50% 50% 50% 0;width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 2px 6px rgba(0,0,0,.3);transform:rotate(-45deg)"><span style="transform:rotate(45deg)">🚌</span></div>`,
    iconSize:[36,36], iconAnchor:[18,36], popupAnchor:[0,-36],
  });
}

function initMap() {
  if (_mapInit) return;
  _mapInit = true;
  _map = L.map('liveMap').setView([20.5937, 78.9629], 5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors', maxZoom:19,
  }).addTo(_map);
  // Polling interval is owned by switchTab() so it only runs while the Map tab is active.
  drawSchoolCircle();
}

// Pause polling while the browser tab is hidden; refresh immediately when it comes back.
document.addEventListener('visibilitychange', () => {
  if (!document.hidden && _mapInterval) refreshMap();
});

function fmtAge(age) {
  if (age == null) return 'No data';
  if (age < 60) return age + 's';
  if (age < 3600) return Math.round(age / 60) + 'm';
  return Math.round(age / 3600) + 'h';
}

function fitAll() {
  if (!_map) return;
  const pts = Object.values(_mapMarkers).map(m => m.getLatLng());
  if (pts.length === 1) _map.setView(pts[0], 15);
  else if (pts.length > 1) _map.fitBounds(pts, {padding:[40,40]});
}

async function refreshMap() {
  if (_mapBusy || document.hidden) return;
  _mapBusy = true;
  const statusEl = document.getElementById('mapRefreshStatus');
  try {
    const r = await api('get_live_locations');
    if (!r.success) { statusEl.textContent = 'Update failed' + (r.message ? ': ' + r.message : ''); return; }
    const buses = r.buses || [];
    window._lastBuses = buses;
    statusEl.textContent = 'Updated ' + new Date().toLocaleTimeString();

    renderAlertFeed(r.alerts || []);
    const wd = document.getElementById('watchdogBanner'), alerts = r.watchdog || [];
    wd.style.display = alerts.length ? 'block' : 'none';
    wd.innerHTML = alerts.map(a => '⚠️ <strong>' + esc(a.bus_name) + '</strong> (' + esc(a.bus_number) + ') chalni chahiye par ' +
      (parseInt(a.silent_min) || 0) + ' min se location nahi aa rahi — driver ka phone / GPS device check karein.').join('<br>');

    const seen = new Set();
    let listHtml = '';

    buses.forEach(b => {
      const age = b.age_seconds != null ? Math.max(0, parseInt(b.age_seconds)) : null;
      const gs  = b.gps_status || (age == null ? 'never' : age < 120 ? 'live' : age < 300 ? 'recent' : 'offline');
      const lat = parseFloat(b.lat), lng = parseFloat(b.lng);
      const hasLoc = b.lat != null && b.lng != null && isFinite(lat) && isFinite(lng) && !(lat === 0 && lng === 0);
      const ageStr = fmtAge(age);
      const routeTxt = b.route_name || 'No route';

      const t = b.trip;
      const still = gs === 'live' && b.still_seconds != null && +b.still_seconds >= 120 ? Math.round(b.still_seconds / 60) : 0;
      const tripHtml = t ? `<div class="bl-trip">🟢 Shift ${t.shift} · ✔ ${t.done} · ✖ ${t.absent} · ⏳ ${t.pending} baaki${t.next ? ' · Agla: <b>' + esc(t.next) + '</b>' : ''}</div>
          <div class="bl-bar"><i style="width:${t.total ? t.done / t.total * 100 : 0}%;background:#16a34a"></i><i style="width:${t.total ? t.absent / t.total * 100 : 0}%;background:#94a3b8"></i></div>` : '';
      listHtml += `<div class="bus-list-item${_selBus == b.id ? ' sel' : ''}" id="bli_${b.id}" onclick="focusBus(${b.id})">
        <span class="gps-dot ${gs}" style="margin-top:0;flex-shrink:0;"></span>
        <div style="flex:1;min-width:0;">
          <div class="bl-name">${esc(b.bus_name)}</div>
          <div class="bl-info">${esc(b.bus_number)} · ${esc(routeTxt)} · ${hasLoc ? ageStr : 'No GPS'}</div>
          ${tripHtml}${still ? `<div class="bl-halt">⏸ ${still} min se ruki hai</div>` : ''}
        </div>
      </div>`;

      if (!hasLoc || !_map) return;
      seen.add(String(b.id));

      const speedTxt = gs === 'live' ? Math.round(b.speed || 0) + ' km/h' : '—';
      const routesLbl = parseInt(b.route_count) > 1 ? 'Routes' : 'Route';
      const popupHtml = `<strong>${esc(b.bus_name)}</strong> (${esc(b.bus_number)})<br>
        ${routesLbl}: ${esc(b.route_name || '—')}<br>
        Driver: ${esc(b.driver_name || '—')}<br>
        Speed: ${speedTxt}${b.accuracy ? '<br>Accuracy: ±' + Math.round(b.accuracy) + ' m' : ''}<br>
        ${b.trip ? `<b>Shift ${b.trip.shift}:</b> ✔ ${b.trip.done} utha liye · ✖ ${b.trip.absent} · ⏳ ${b.trip.pending} baaki<br>` : ''}
        ${gs === 'live' && b.still_seconds >= 120 ? `<span style="color:#b45309;font-weight:600">⏸ ${Math.round(b.still_seconds / 60)} min se ruki hai</span><br>` : ''}
        <span style="font-size:.75rem;color:#6b7280;">Last update: ${hasLoc && age != null ? ageStr + ' ago' : 'No data'}</span><br>
        <button type="button" onclick="toggleTrail(${b.id}, this)" style="margin-top:6px;padding:3px 9px;border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc;cursor:pointer;font-size:.75rem;">${_trailLayers[b.id] ? 'Hide trail' : 'Show trail (2h)'}</button>`;

      if (_mapMarkers[b.id]) {
        _mapMarkers[b.id].setLatLng([lat, lng]).setIcon(busIcon(gs)).getPopup().setContent(popupHtml);
      } else {
        _mapMarkers[b.id] = L.marker([lat, lng], {icon: busIcon(gs)}).bindPopup(popupHtml).addTo(_map);
      }
    });

    // Accuracy circle (only for fresh fixes with a known accuracy)
    buses.forEach(b => {
      const id = String(b.id), acc = parseFloat(b.accuracy);
      const show = _mapMarkers[id] && b.gps_status === 'live' && isFinite(acc) && acc > 0;
      if (!show) { if (_accCircles[id]) { _map.removeLayer(_accCircles[id]); delete _accCircles[id]; } return; }
      const ll = _mapMarkers[id].getLatLng();
      if (_accCircles[id]) _accCircles[id].setLatLng(ll).setRadius(acc);
      else _accCircles[id] = L.circle(ll, {radius: acc, color:'#3b82f6', weight:1, fillColor:'#3b82f6', fillOpacity:.1, interactive:false}).addTo(_map);
    });

    // Remove markers/trails of buses that were deleted, deactivated, or lost their GPS fix
    Object.keys(_mapMarkers).forEach(id => {
      if (!seen.has(String(id))) {
        _map.removeLayer(_mapMarkers[id]);
        delete _mapMarkers[id];
        if (_accCircles[id]) { _map.removeLayer(_accCircles[id]); delete _accCircles[id]; }
        clearTrail(id);
      }
    });

    document.getElementById('mapBusItems').innerHTML =
      listHtml || '<div style="color:#94a3b8;font-size:.8rem;">No active buses configured.</div>';

    if (!_mapFitted && Object.keys(_mapMarkers).length) { fitAll(); _mapFitted = true; }

    // Keep any visible trails up to date
    Object.keys(_trailLayers).forEach(id => drawTrail(id));
    if (_selBus) loadBusDetail(_selBus);
  } finally {
    _mapBusy = false;
  }
}

function focusBus(id) {
  const m = _mapMarkers[id];
  _selBus = id;
  document.querySelectorAll('.bus-list-item').forEach(el => el.classList.toggle('sel', el.id === 'bli_' + id));
  loadBusDetail(id, true);
  if (!_map || !m) { showToast('No GPS location for this bus yet.', false); return; }
  _map.setView(m.getLatLng(), 15);
  m.openPopup();
}

// ── Safety alert feed (overspeed / school gate / off route / silent) ─────────
let _lastAlertId = null, _alertMk = null, _schoolCircle = null;
const AL_ICON = {overspeed:'🚀', school_arrive:'🏫', school_leave:'🚌', deviation:'🧭', silent:'📵', halt_report:'🛑', halt:'⏸', halt_resolved:'▶️'};
const AL_BCAST = {halt_report: 1, halt: 1, halt_resolved: 1, deviation: 1, silent: 1};   // alerts parents may need to hear about
function renderAlertFeed(list) {
  const box = document.getElementById('alertFeed');
  if (!list.length) { box.style.display = 'none'; return; }
  const maxId = Math.max(...list.map(a => +a.id));
  const fresh = _lastAlertId !== null ? list.filter(a => +a.id > _lastAlertId && a.type !== 'school_arrive' && a.type !== 'school_leave') : [];
  if (fresh.length) {
    try { playAlertTone(); } catch (e) {}
    if ('Notification' in window && Notification.permission === 'granted') fresh.slice(0, 3).forEach(a => new Notification('Bus alert', {body: a.message, tag: 'busalert' + a.id}));
  }
  _lastAlertId = maxId;
  const unseen = list.filter(a => !a.seen_at).length;
  box.style.display = 'block';
  box.innerHTML = `<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;"><b>🚨 Alerts (12 ghante)</b>
      ${unseen ? `<span style="background:#dc2626;color:#fff;border-radius:99px;padding:1px 8px;font-size:.72rem;">${unseen} naye</span>` : ''}
      <span style="flex:1"></span>
      ${'Notification' in window && Notification.permission === 'default' ? '<button class="edu-btn edu-btn-sm edu-btn-secondary" onclick="Notification.requestPermission()">🔔 Desktop alert ON</button>' : ''}
      ${unseen ? `<button class="edu-btn edu-btn-sm edu-btn-secondary" onclick="alertsSeen(${maxId})">✓ Sab dekh liya</button>` : ''}</div>`
    + list.slice(0, 8).map(a => `<div class="al-row${a.seen_at ? '' : ' new'}" onclick="showAlertOnMap(${+a.lat || 0},${+a.lng || 0})">
        <span>${AL_ICON[a.type] || '⚠️'}</span><b style="flex:1;font-weight:${a.seen_at ? 500 : 700}">${esc(a.message)}</b>
        ${IS_ADMIN && AL_BCAST[a.type] && a.trip_id ? (a.broadcast_at
          ? `<span style="font-size:.72rem;color:#166534;white-space:nowrap">✓ parents ko ${esc(String(a.broadcast_at).slice(11, 16))}</span>
             <button class="edu-btn edu-btn-sm edu-btn-secondary" style="padding:2px 8px" onclick="event.stopPropagation();openBroadcast(${a.id})">Update</button>`
          : `<button class="edu-btn edu-btn-sm edu-btn-primary" style="padding:3px 9px;white-space:nowrap" onclick="event.stopPropagation();openBroadcast(${a.id})">📣 Parents ko batayein</button>`) : ''}
        <span class="al-time">${esc(String(a.created_at).slice(11, 16))}</span></div>`).join('');
}
function playAlertTone() {
  const ctx = new (window.AudioContext || window.webkitAudioContext)(), t = ctx.currentTime;
  [0, .25].forEach(o => { const os = ctx.createOscillator(), g = ctx.createGain(); os.connect(g); g.connect(ctx.destination);
    os.frequency.value = 880; g.gain.setValueAtTime(.3, t + o); g.gain.exponentialRampToValueAtTime(.001, t + o + .2); os.start(t + o); os.stop(t + o + .2); });
  setTimeout(() => ctx.close(), 800);
}
let _bcast = null;
async function openBroadcast(alertId, busId) {
  const r = await api('broadcast_preview', alertId ? {alert_id: alertId} : {bus_id: busId});
  if (!r.success) { showToast(r.message, false); return; }
  _bcast = {alert_id: alertId || 0, bus_id: busId || 0};
  document.getElementById('bcastText').value = r.text;
  document.getElementById('bcastWho').innerHTML = r.shift
    ? `<b>${esc(r.bus.bus_name)}</b> · Shift ${r.shift} · <b>${r.count}</b> parents ko jayega` + (r.sent_before ? ` <span style="color:#b45309">(pehle ${esc(String(r.sent_before).slice(11, 16))} par bheja ja chuka hai)</span>` : '')
    : '<span style="color:#b91c1c">Is bus ki koi trip nahi chal rahi — message kisko jaye, pata nahi.</span>';
  document.getElementById('bcastSend').disabled = !r.shift || !r.count;
  document.getElementById('bcastMsg').style.display = 'none';
  document.getElementById('bcastModal').classList.add('show');
}
function closeBroadcast() { document.getElementById('bcastModal').classList.remove('show'); }
async function sendBroadcast() {
  const btn = document.getElementById('bcastSend'); btn.disabled = true;
  try {
    const r = await api('broadcast', Object.assign({text: document.getElementById('bcastText').value}, _bcast));
    showMsg(document.getElementById('bcastMsg'), r.success, r.message);
    if (r.success) { setTimeout(closeBroadcast, 1200); refreshMap(); } else btn.disabled = false;
  } catch (e) { btn.disabled = false; }
}
async function alertsSeen(upto) { await api('alerts_seen', {upto}); refreshMap(); }
function showAlertOnMap(lat, lng) {
  if (!_map || !lat) return;
  if (_alertMk) _map.removeLayer(_alertMk);
  _alertMk = L.circleMarker([lat, lng], {radius: 12, color:'#dc2626', weight:3, fillOpacity:.15}).addTo(_map);
  _map.setView([lat, lng], 16);
}
async function openAlertSettings() {
  const r = await api('get_alert_settings');
  const s = r.settings || {};
  document.getElementById('asSpeed').value = s.overspeed_kmh ?? 50;
  document.getElementById('asSpeedSec').value = s.overspeed_sec ?? 20;
  document.getElementById('asLat').value = s.school_lat ?? '';
  document.getElementById('asLng').value = s.school_lng ?? '';
  document.getElementById('asRadius').value = s.school_radius_m ?? 150;
  document.getElementById('asDev').value = s.deviation_m ?? 400;
  document.getElementById('asDevSec').value = s.deviation_sec ?? 90;
  document.getElementById('asParents').checked = !!+(s.notify_parents ?? 1);
  document.getElementById('asHaltAsk').value = s.halt_ask_min ?? 3;
  document.getElementById('asHaltAdmin').value = s.halt_admin_min ?? 8;
  document.getElementById('alertSetMsg').style.display = 'none';
  document.getElementById('alertSetModal').classList.add('show');
}
function closeAlertSettings() { document.getElementById('alertSetModal').classList.remove('show'); }
function alertUseMapCenter() {
  if (!_map) { showToast('Pehle Live Map kholein aur school par zoom karein.', false); return; }
  const c = _map.getCenter();
  document.getElementById('asLat').value = c.lat.toFixed(6);
  document.getElementById('asLng').value = c.lng.toFixed(6);
}
async function saveAlertSettings() {
  const v = id => document.getElementById(id).value.trim();
  const r = await api('save_alert_settings', {overspeed_kmh: v('asSpeed'), overspeed_sec: v('asSpeedSec'), school_lat: v('asLat'), school_lng: v('asLng'),
    school_radius_m: v('asRadius'), deviation_m: v('asDev'), deviation_sec: v('asDevSec'), notify_parents: document.getElementById('asParents').checked ? 1 : 0,
    halt_ask_min: v('asHaltAsk'), halt_admin_min: v('asHaltAdmin')});
  showMsg(document.getElementById('alertSetMsg'), r.success, r.message);
  if (r.success) { drawSchoolCircle(); setTimeout(closeAlertSettings, 900); }
}
async function drawSchoolCircle() {
  if (!_map) return;
  const r = await api('get_alert_settings'), s = r.settings || {};
  if (_schoolCircle) { _map.removeLayer(_schoolCircle); _schoolCircle = null; }
  if (s.school_lat != null && s.school_lng != null)
    _schoolCircle = L.circle([s.school_lat, s.school_lng], {radius: +s.school_radius_m || 150, color:'#7c3aed', weight:2, fillOpacity:.06, dashArray:'5 6'})
      .bindTooltip('🏫 School gate').addTo(_map);
}

// ── Selected bus: students of the running shift, halts, path, learned route ──
let _selBus = null, _detailLayer = null, _detailBusy = false;
const KIND_LBL = {pickup: '🌅 Pickup', drop: '🏫 Drop', any: ''};
function closeBusDetail() {
  _selBus = null;
  if (_detailLayer) { _map.removeLayer(_detailLayer); _detailLayer = null; }
  document.getElementById('busDetail').innerHTML = '';
  document.querySelectorAll('.bus-list-item').forEach(el => el.classList.remove('sel'));
}
async function loadBusDetail(id, fit) {
  if (_detailBusy) return;
  _detailBusy = true;
  let r;
  try { r = await api('get_bus_live_detail', {bus_id: id}); } finally { _detailBusy = false; }
  if (_selBus != id) return;
  const box = document.getElementById('busDetail');
  if (!r.success) { box.innerHTML = '<div class="bd-box" style="color:#b91c1c">' + esc(r.message || 'Error') + '</div>'; return; }
  if (!_map) return;
  if (_detailLayer) _map.removeLayer(_detailLayer);
  _detailLayer = L.layerGroup().addTo(_map);
  const L_ = _detailLayer, pts = [];
  const learn = r.learned;
  if (learn && learn.path && learn.path.length > 1) L.polyline(learn.path, {color:'#8b5cf6', weight:8, opacity:.25}).bindTooltip('Roz ka raasta (seekha hua)').addTo(L_);
  if (r.path && r.path.length > 1) { L.polyline(r.path, {color:'#2563eb', weight:4, opacity:.85}).bindTooltip('Aaj ki trip ka raasta').addTo(L_); r.path.forEach(p => pts.push(p)); }
  const col = {done:'#16a34a', absent:'#94a3b8', pending:'#2563eb'};
  const pend = r.stops.filter(s => s.status === 'pending').sort((a, b) => (a.seq ?? 1e9) - (b.seq ?? 1e9));
  const nextId = pend.length && pend[0].seq != null ? pend[0].id : null;
  r.stops.forEach(s => {
    const c = s.id === nextId ? '#f59e0b' : col[s.status] || '#2563eb';
    const st = s.status === 'done' ? '✔ ' + (s.by === 'auto' ? 'auto ' : '') + String(s.at || '').slice(11, 16) : s.status === 'absent' ? '✖ nahi aaya' : s.id === nextId ? 'agla stop' : 'baaki';
    L.circleMarker([s.lat, s.lng], {radius: s.id === nextId ? 9 : 7, color:'#fff', weight:2, fillColor:c, fillOpacity:1})
      .bindTooltip(esc(s.name) + ' (' + esc(s.cls) + ') — ' + st).addTo(L_);
    pts.push([s.lat, s.lng]);
  });
  (r.halts || []).forEach(h => L.circleMarker([h.lat, h.lng], {radius:8, color:'#b45309', weight:2, fillColor:'#fbbf24', fillOpacity:.9})
    .bindTooltip('⏸ ' + h.min + ' min ruki · ' + String(h.at).slice(11, 16)).addTo(L_));
  if (fit && pts.length > 1) _map.fitBounds(pts, {padding:[40,40], maxZoom:16});

  const n = {done:0, absent:0, pending:0}; r.stops.forEach(s => n[s.status]++);
  const total = r.stops.length + r.missing.length, waiting = n.pending + r.missing.length;
  const live = (window._lastBuses || []).find(b => b.id == id) || {};
  const still = live.gps_status === 'live' && live.still_seconds >= 120 ? Math.round(live.still_seconds / 60) : 0;
  const rows = r.stops.slice().sort((a, b) => ({pending:0, done:1, absent:2}[a.status] - {pending:0, done:1, absent:2}[b.status]) || ((a.seq ?? 1e9) - (b.seq ?? 1e9)))
    .map(s => {
      const c = s.id === nextId ? '#f59e0b' : col[s.status];
      const lab = s.status === 'done' ? '✔' : s.status === 'absent' ? '✖' : (s.seq ?? '•');
      const tail = s.status === 'done' ? String(s.at || '').slice(11, 16) + (s.by === 'auto' ? ' auto' : '') : s.status === 'absent' ? (s.by === 'parent' ? '👪 parent ne bataya' : 'nahi aaya') : s.id === nextId ? '<b style="color:#b45309">agla</b>' : '';
      return `<div class="bd-row" onclick="_map.setView([${s.lat},${s.lng}],17)"><span class="bd-dot" style="background:${c}">${lab}</span><span style="flex:1;min-width:0">${esc(s.name)} <small style="color:#94a3b8">${esc(s.cls)}</small></span><small>${tail}</small></div>`;
    }).join('');
  const halts = (r.halts || []).map(h => `<div class="bd-row" onclick="_map.setView([${h.lat},${h.lng}],17)"><span class="bd-dot" style="background:#fbbf24;color:#78350f">⏸</span><span style="flex:1">${String(h.at).slice(11, 16)}</span><small>${h.min} min</small></div>`).join('');
  const learnTxt = !learn ? 'Abhi tak koi trip poori nahi hui — trips se apne aap seekhega.'
    : learn.active ? `✅ ${learn.trips} trips se seekha · ${Math.round(learn.confidence * 100)}% pakka — driver ke phone par yahi kram default hai.`
    : `⏳ Seekh raha hai: ${Math.min(learn.trips, learn.need)}/${learn.need} trips · ${Math.round(learn.confidence * 100)}% ek jaisa`;
  document.getElementById('busDetail').innerHTML = `<div class="bd-box">
    <h4>${esc(r.bus.bus_name)} <small style="font-weight:500;color:#64748b">Shift ${r.shift} ${KIND_LBL[r.kind] || ''}</small>
      <button type="button" onclick="closeBusDetail()" style="margin-left:auto;border:0;background:none;font-size:1.1rem;cursor:pointer;color:#94a3b8">&times;</button></h4>
    ${r.trip ? `<div style="color:#16a34a;font-weight:600">🟢 Trip ${String(r.trip.started_at).slice(11, 16)} se chal rahi hai</div>` : '<div style="color:#64748b">Abhi koi trip nahi chal rahi (shift ' + r.shift + ' ke students dikh rahe hain)</div>'}
    ${still ? `<div class="bl-halt" style="font-size:.8rem">⏸ Abhi ${still} min se ek jagah ruki hai</div>` : ''}
    ${r.trip && IS_ADMIN ? `<button type="button" class="edu-btn edu-btn-sm edu-btn-primary" style="margin:6px 0" onclick="openBroadcast(0, ${id})">📣 Is shift ke sabhi parents ko message</button>` : ''}
    <div class="bd-kpi"><div><b style="color:#16a34a">${n.done}</b>utha liye</div><div><b style="color:#64748b">${n.absent}</b>nahi aaye</div><div><b style="color:#2563eb">${waiting}</b>baaki</div></div>
    <div class="bl-bar" style="height:8px"><i style="width:${total ? n.done / total * 100 : 0}%;background:#16a34a"></i><i style="width:${total ? n.absent / total * 100 : 0}%;background:#94a3b8"></i></div>
    ${r.missing.length ? `<div style="margin-top:6px;color:#b45309">⚠️ ${r.missing.length} ne ghar ki location nahi lagayi: ${r.missing.map(m => esc(m.name)).join(', ')}</div>` : ''}
    <div class="bd-sec">Students (${total})</div>${rows || '<div style="color:#94a3b8">—</div>'}
    ${r.trip ? `<div class="bd-sec">Aaj kahan ruki (2+ min)</div>${halts || '<div style="color:#94a3b8">Abhi tak koi lamba stop nahi</div>'}` : ''}
    <div class="bd-sec">Roz ka raasta</div><div>${learnTxt}</div>
    ${learn ? `<button type="button" class="edu-btn edu-btn-sm edu-btn-secondary" style="margin-top:6px" onclick="resetLearn(${id},${r.shift},'${r.kind}')">↺ Seekha hua raasta hatayein</button>` : ''}
  </div>`;
}
function resetLearn(id, shift, kind) {
  showConfirm('Roz ka raasta hatayein?', 'Route badal gaya ho tabhi hatayein. Agli trips se phir seekhega.', async () => {
    const r = await api('learn_reset', {bus_id: id, shift, kind});
    showToast(r.message, r.success);
    loadBusDetail(id);
  }, 'Hatayein');
}

function clearTrail(id) {
  if (_trailLayers[id]) { _map.removeLayer(_trailLayers[id]); delete _trailLayers[id]; }
}

async function drawTrail(id) {
  const r = await api('get_bus_trail', {bus_id: id, minutes: 120});
  if (!r.success || !_map) return false;
  const pts = (r.points || []).map(p => [parseFloat(p.lat), parseFloat(p.lng)]).filter(p => isFinite(p[0]) && isFinite(p[1]));
  clearTrail(id);
  if (pts.length < 2) return false;
  _trailLayers[id] = L.polyline(pts, {color:'#2563eb', weight:4, opacity:.7}).addTo(_map);
  return true;
}

async function toggleTrail(id, btn) {
  if (_trailLayers[id]) {
    clearTrail(id);
    if (btn) btn.textContent = 'Show trail (2h)';
    return;
  }
  const ok = await drawTrail(id);
  if (ok) { if (btn) btn.textContent = 'Hide trail'; }
  else showToast('No movement recorded in the last 2 hours.', false);
}

// ════════════════════════════════════════════════════════════════════════
// STUDENTS
// ════════════════════════════════════════════════════════════════════════
async function loadStudents() {
  const routeId = document.getElementById('routeFilter').value;
  const data = await api('get_route_students', routeId ? {route_id: routeId} : {});

  const tbody = document.getElementById('stuTableBody');
  const students = data.students || [];
  document.getElementById('stuCount').textContent = students.length + ' student' + (students.length!==1?'s':'');

  if (!students.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#94a3b8;">No students with bus assignment found. Assign students to van routes in Fees → Student Setup.</td></tr>';
    return;
  }

  tbody.innerHTML = students.map((s,i) => {
    const photoHtml = s.photo
      ? `<img src="<?= BASE_URL ?>/assets/uploads/photos/${esc(s.photo)}" class="stu-photo" onerror="this.style.display='none'">`
      : `<div class="stu-initials">${esc((s.name||'?').charAt(0))}</div>`;
    const shiftCount = parseInt(s.shift_count||1);
    const shiftSel = shiftCount > 1
      ? `<select class="shift-sel" onchange="setShift(${s.student_id},${s.route_id},this.value)">
           ${Array.from({length: Math.min(shiftCount, MAX_SHIFTS)}, (_, k) => k+1).map(n =>
             `<option value="${n}" ${Math.min(parseInt(s.shift_no)||1, shiftCount)===n?'selected':''}>Shift ${n}</option>`).join('')}
         </select>`
      : '<span style="color:#94a3b8;font-size:.78rem;">Single</span>';
    return `<tr>
      <td data-label="#" style="color:#94a3b8;">${i+1}</td>
      <td data-label="Student"><div style="display:flex;align-items:center;gap:8px;">${photoHtml}<div><div style="font-weight:600;">${esc(s.name)}</div><div style="font-size:.73rem;color:#94a3b8;">${esc(s.admission_no)}</div></div></div></td>
      <td data-label="Class">${esc(s.class_name||'')} ${esc(s.section_name||'')}</td>
      <td data-label="Route">${esc(s.route_name||'—')}</td>
      <td data-label="Bus">${s.bus_name?`<span style="font-weight:600;">${esc(s.bus_name)}</span><br><span style="font-size:.72rem;color:#94a3b8;">${esc(s.bus_number)}</span>`:'<span style="color:#f59e0b;">Not assigned</span>'}</td>
      <td data-label="Shift">${shiftSel}</td>
      <td data-label="Home 📍">${s.has_home>0?'<span style="color:#22c55e;">✓ Set</span>':'<span style="color:#94a3b8;">—</span>'}</td>
    </tr>`;
  }).join('');
}

async function setShift(studentId, routeId, shiftNo) {
  const r = await api('set_student_shift', {student_id:studentId, route_id:routeId, shift_no:shiftNo});
  if (!r.success) showToast(r.message, false);
}

// ── Trips report ──────────────────────────────────────────────────────────────
let _tripMap = null, _tripLayer = null, _tripsInit = false;
async function initTrips() {
  if (!_tripsInit) {
    _tripsInit = true;
    const d = new Date(), pad = n => String(n).padStart(2, '0'), iso = x => x.getFullYear() + '-' + pad(x.getMonth() + 1) + '-' + pad(x.getDate());
    document.getElementById('tripTo').value = iso(d);
    d.setDate(d.getDate() - 6);
    document.getElementById('tripFrom').value = iso(d);
    const r = await api('get_fleet');
    (r.buses || []).forEach(b => document.getElementById('tripBus').insertAdjacentHTML('beforeend', '<option value="' + b.id + '">' + esc(b.bus_name) + '</option>'));
  }
  loadTrips();
}
function tripParams() {
  return {from: document.getElementById('tripFrom').value, to: document.getElementById('tripTo').value, bus_id: document.getElementById('tripBus').value || 0};
}
function exportTrips() {
  const p = new URLSearchParams(Object.assign({action: 'export_trips'}, tripParams()));
  window.location = API + '?' + p.toString();
}
async function loadTrips() {
  const body = document.getElementById('tripBody');
  const r = await api('get_trips', tripParams());
  if (!r.success) { body.innerHTML = '<tr><td colspan="10" style="padding:24px;color:#b91c1c;">' + esc(r.message || 'Error') + '</td></tr>'; return; }
  const t = r.trips || [];
  const km = t.reduce((s, x) => s + (+x.distance_m || 0), 0) / 1000;
  document.getElementById('tripSummary').innerHTML = '<strong>' + t.length + '</strong> trips · <strong>' + km.toFixed(1) + ' km</strong> total';
  const why = {driver: 'Driver', auto_silent: 'Auto (signal gaya)', auto_old: 'Auto (bahut purani)'};
  body.innerHTML = t.length ? t.map(x => {
    const m = +x.minutes || 0;
    return '<tr><td><strong>' + esc(x.bus_name) + '</strong><br><small style="color:#94a3b8">' + esc(x.bus_number) + '</small></td>'
      + '<td>' + x.shift_no + '</td><td>' + esc((x.started_at || '').replace('T', ' ').slice(0, 16)) + '</td>'
      + '<td>' + Math.floor(m / 60) + 'h ' + (m % 60) + 'm</td>'
      + '<td>' + (x.distance_m != null ? (x.distance_m / 1000).toFixed(1) + ' km' : '—') + '</td>'
      + '<td>' + (x.max_speed_kmh != null ? Math.round(x.max_speed_kmh) + ' km/h' : '—') + '</td>'
      + '<td>' + (x.stop_count || 0) + '</td><td>' + (+x.picked || 0) + ' / ' + (+x.absent || 0) + '</td><td>' + (x.ended_at ? esc(why[x.end_reason] || x.end_reason) : '<span style="color:#16a34a">● chal rahi</span>') + '</td>'
      + '<td>' + (x.ended_at ? '<button class="edu-btn edu-btn-sm edu-btn-secondary" onclick="showTrip(' + x.id + ')"><i class="bi bi-map"></i> Map</button>' : '') + '</td></tr>';
  }).join('') : '<tr><td colspan="10" style="text-align:center;padding:30px;color:#94a3b8;">Is samay mein koi trip nahi mili.</td></tr>';
}
async function showTrip(id) {
  const r = await api('get_trip', {id});
  if (!r.success) { showToast(r.message, false); return; }
  document.getElementById('tripMapWrap').style.display = 'block';
  if (!_tripMap) {
    _tripMap = L.map('tripMap');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '© OpenStreetMap'}).addTo(_tripMap);
  }
  if (_tripLayer) _tripMap.removeLayer(_tripLayer);
  _tripLayer = L.layerGroup().addTo(_tripMap);
  const path = r.path || [];
  if (path.length) {
    const line = L.polyline(path, {color: '#2563eb', weight: 4}).addTo(_tripLayer);
    L.circleMarker(path[0], {radius: 7, color: '#16a34a', fillOpacity: 1}).bindTooltip('Start').addTo(_tripLayer);
    L.circleMarker(path[path.length - 1], {radius: 7, color: '#dc2626', fillOpacity: 1}).bindTooltip('End').addTo(_tripLayer);
    (r.stops || []).forEach(s => L.circleMarker([s.lat, s.lng], {radius: 6, color: '#f59e0b', fillOpacity: .9})
      .bindTooltip('Stop · ' + s.min + ' min · ' + String(s.at).slice(11, 16)).addTo(_tripLayer));
    _tripMap.fitBounds(line.getBounds(), {padding: [30, 30]});
  }
  setTimeout(() => _tripMap.invalidateSize(), 60);
  document.getElementById('tripMapWrap').scrollIntoView({behavior: 'smooth'});
}

// ── Init ──────────────────────────────────────────────────────────────────────
// Database setup check: if the automatic setup failed, show the admin exactly why (instead of errors later)
async function checkSchema(retry) {
  const r = await api('schema_status', retry ? {retry: 1} : {});
  let box = document.getElementById('schemaBanner');
  if (!r.success || r.ready) { if (box) box.remove(); if (retry && r.ready) { showToast('Database setup poora ho gaya ✔'); loadFleet(); } return; }
  if (!box) {
    box = document.createElement('div'); box.id = 'schemaBanner';
    box.style.cssText = 'margin:0 0 14px;padding:12px 14px;border-radius:12px;background:#fef2f2;border:1.5px solid #fecaca;color:#7f1d1d;font-size:.82rem;line-height:1.5;';
    const tabs = document.querySelector('.bt-tab'); (tabs ? tabs.parentNode : document.body).insertAdjacentElement('beforebegin', box);
  }
  box.innerHTML = '<b>⚠️ Bus GPS ka database setup adhoora hai</b> (v' + r.version + ' / v' + r.needed + '). Naye features (driver pairing, alerts, trips) iske bina nahi chalenge.'
    + (r.errors.length ? '<div style="margin-top:6px;font-family:monospace;font-size:.72rem;background:#fff;border:1px solid #fecaca;border-radius:8px;padding:8px;white-space:pre-wrap;max-height:180px;overflow:auto;">' + r.errors.map(esc).join('\n') + '</div>'
                       : '<div style="margin-top:4px">Kaaran nahi mila — "Dobara koshish" dabayein.</div>')
    + (IS_ADMIN ? '<button class="edu-btn edu-btn-sm edu-btn-primary" style="margin-top:8px" onclick="checkSchema(true)">↻ Dobara koshish karein</button>'
      + ' <span style="font-size:.74rem">Ye laal lines developer ko bhejein agar theek na ho.</span>' : '');
}

(function init() {
  checkSchema(false);
  const activePane = '<?= $activeTab ?>';
  if      (activePane === 'routes')   loadRoutes();  // loadRoutes fetches fleet internally
  else if (activePane === 'map')      { initMap(); refreshMap(); _mapInterval = setInterval(refreshMap, 15000); }
  else if (activePane === 'students') loadStudents();
  else if (activePane === 'trips')    initTrips();
  else                                loadFleet();   // default: fleet tab
})();
</script>

<?php require __DIR__ . '/bus_wizard.php'; ?>
<?php require_once __DIR__ . '/school_footer.php'; ?>