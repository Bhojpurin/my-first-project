<?php
// api/driver_tracker.php — browser-based GPS sender + stop map for ONE bus (no app install needed).
// Open on the driver's phone (Chrome):  https://YOUR-DOMAIN/api/driver_tracker.php?key=BUS_API_KEY
// Each bus has its own key → its own link. No login: the per-bus key is the auth.
//
// GPS:   warm-up for a good first fix · Kalman smoothing (speed-adaptive) · glitch/outlier rejection ·
//        parked-position hold (no GPS drift) · computed speed & heading · adaptive send rate + extra send on
//        turns / long moves · offline buffer with back-fill · wake-lock · stalled-GPS auto-restart ·
//        permission pre-check · battery/signal/network warnings.
// Stops: driver ticks the shift → map shows the route plan through every student's marked home (that shift
//        only) · next-stop card with distance / ETA / Google Maps navigation · voice + vibration when the
//        stop is near · "picked up / did not come" marking (auto-marked after the bus stood at the stop) ·
//        marks survive offline and reloads, and reach the admin report and the student portal.
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');   // the key is in the URL: never leak it to map tiles / Google Maps
$key = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($_GET['key'] ?? ''));
?><!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title>Bus GPS Tracker</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;background:#0f172a;color:#e2e8f0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;display:flex;justify-content:center;padding:max(12px,env(safe-area-inset-top)) 12px max(16px,env(safe-area-inset-bottom))}
  .wrap{width:100%;max-width:520px}
  h1{font-size:.9rem;margin:4px 0 2px;color:#94a3b8;font-weight:600}
  .top{display:flex;align-items:flex-end;gap:10px}
  .bus{font-size:1.4rem;font-weight:700}
  .num{color:#94a3b8;font-size:.85rem;margin-bottom:10px}
  .iconbtn{margin-left:auto;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:10px;padding:8px 10px;font-size:1rem;cursor:pointer}
  .pill{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:14px;background:#1e293b;font-weight:600;margin-bottom:8px}
  .dot{width:12px;height:12px;border-radius:50%;background:#64748b;flex-shrink:0}
  .pill.live{background:#14532d}.pill.live .dot{background:#4ade80;box-shadow:0 0 0 4px rgba(74,222,128,.25)}
  .pill.warn{background:#713f12}.pill.warn .dot{background:#facc15}
  .pill.err{background:#7f1d1d}.pill.err .dot{background:#f87171}
  button{font-family:inherit}
  .sec{font-size:.78rem;color:#94a3b8;font-weight:600;margin:12px 0 6px;text-transform:uppercase;letter-spacing:.04em}
  .shifts{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px}
  .shift{display:flex;gap:10px;align-items:flex-start;background:#1e293b;border:2px solid #334155;border-radius:14px;padding:12px;cursor:pointer;text-align:left;color:#e2e8f0}
  .shift .tick{width:22px;height:22px;border-radius:6px;border:2px solid #64748b;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:.9rem}
  .shift.sel{border-color:#22c55e;background:#052e16}.shift.sel .tick{background:#22c55e;border-color:#22c55e;color:#052e16}
  .shift.lock{opacity:.4;cursor:not-allowed}
  .shift b{display:block;font-size:1rem}.shift small{display:block;color:#94a3b8;font-size:.75rem;margin-top:2px;line-height:1.45}
  button#go{width:100%;margin:12px 0 8px;padding:20px;border:0;border-radius:18px;font-size:1.2rem;font-weight:700;color:#fff;background:#16a34a;cursor:pointer}
  button#go.stop{background:#dc2626}
  button#go:disabled{opacity:.5}
  #mapBox{position:relative;margin-top:8px;display:none}
  #map{height:min(52vh,460px);min-height:280px;border-radius:14px;background:#1e293b}
  .mapbtns{position:absolute;right:8px;top:8px;z-index:500;display:flex;flex-direction:column;gap:6px}
  .mapbtns button{background:#0f172aee;color:#e2e8f0;border:1px solid #334155;border-radius:10px;padding:8px 10px;font-size:.8rem;cursor:pointer}
  .mapbtns button.on{border-color:#22c55e;color:#4ade80}
  .next{margin-top:10px;background:#1e293b;border-radius:16px;padding:14px;border:2px solid #f59e0b}
  .next.here{border-color:#22c55e;background:#052e16}
  .next .lbl{font-size:.72rem;color:#fbbf24;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
  .next.here .lbl{color:#4ade80}
  .next .nm{font-size:1.35rem;font-weight:800;margin:4px 0 2px}
  .next .meta{color:#cbd5e1;font-size:.9rem}
  .acts{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
  .acts button,.acts a{flex:1;min-width:90px;text-align:center;text-decoration:none;padding:13px 8px;border-radius:12px;border:0;font-size:.95rem;font-weight:700;cursor:pointer;color:#fff}
  .b-done{background:#16a34a}.b-abs{background:#475569}.b-nav{background:#2563eb}.b-undo{background:#334155}
  .acts button:disabled{opacity:.45}
  .list{margin-top:6px;background:#1e293b;border-radius:14px;overflow:hidden}
  .row{display:flex;align-items:center;gap:10px;padding:11px 12px;border-bottom:1px solid #0f172a;cursor:pointer}
  .row:last-child{border-bottom:0}
  .no{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.8rem;flex-shrink:0;background:#2563eb;color:#fff}
  .no.nx{background:#f59e0b;color:#111}.no.dn{background:#16a34a}.no.ab{background:#475569}
  .row .t{flex:1;min-width:0}.row .t b{display:block;font-size:.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .row .t small{color:#94a3b8;font-size:.75rem}
  .row .d{font-size:.8rem;color:#cbd5e1;text-align:right;white-space:nowrap}
  .row.dim{opacity:.55}
  .warnbox{margin-top:8px;padding:10px 12px;border-radius:12px;background:#422006;color:#fde68a;font-size:.8rem;line-height:1.5}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}
  .box{background:#1e293b;border-radius:12px;padding:10px 12px}
  .box small{display:block;color:#94a3b8;font-size:.7rem;margin-bottom:2px}
  .box b{font-size:.95rem}
  .q-great{color:#4ade80}.q-good{color:#a3e635}.q-fair{color:#facc15}.q-poor{color:#f87171}
  .note{margin-top:14px;font-size:.78rem;line-height:1.55;color:#94a3b8}
  .note strong{color:#e2e8f0}
  #msg{margin-top:10px;font-size:.85rem;color:#fbbf24;min-height:1.2em}
  #queueInfo{margin-top:6px;font-size:.78rem;color:#93c5fd;min-height:1em}
  #permBox{display:none;margin-bottom:8px;padding:12px 14px;border-radius:14px;background:#7f1d1d;font-size:.84rem;line-height:1.6}
  #permBox b{display:block;font-size:.95rem;margin-bottom:4px}
  button#dimBtn{width:100%;margin-top:10px;padding:11px;border:1px solid #334155;border-radius:12px;background:transparent;color:#94a3b8;font-size:.88rem;cursor:pointer}
  #dim{display:none;position:fixed;inset:0;background:#000;z-index:2000;color:#334155;align-items:center;justify-content:center;flex-direction:column;text-align:center;font-size:.95rem;padding:20px}
  #dim.show{display:flex}
  #dim b{color:#475569;font-size:1.1rem;margin-bottom:6px}
  #dimNext{color:#a16207;font-size:1.3rem;font-weight:800;margin-top:14px}
  .leaflet-popup-content{margin:10px 12px;font-size:.85rem}
  .pp b{font-size:.95rem}.pp .acts{margin-top:8px}.pp .acts button,.pp .acts a{padding:9px 6px;font-size:.8rem;min-width:70px}
  .busic{width:34px;height:34px;border-radius:50%;background:#16a34a;border:3px solid #fff;box-shadow:0 2px 8px #0008;display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;font-weight:900}
  .stic{width:28px;height:28px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 5px #0009;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;color:#fff;background:#2563eb}
  .stic.nx{background:#f59e0b;color:#111;width:34px;height:34px;font-size:14px;animation:pulse 1.4s infinite}
  .stic.dn{background:#16a34a}.stic.ab{background:#64748b}
  @keyframes pulse{0%{box-shadow:0 0 0 0 #f59e0bcc}70%{box-shadow:0 0 0 14px #f59e0b00}100%{box-shadow:0 0 0 0 #f59e0b00}}
</style>
</head>
<body>
<div class="wrap">
  <h1>Bus GPS Tracker</h1>
  <div class="top">
    <div><div class="bus" id="busName">Loading…</div><div class="num" id="busNum">&nbsp;</div></div>
    <button class="iconbtn" id="voiceBtn" type="button" title="Awaaz">🔊</button>
  </div>

  <div id="permBox"></div>
  <div class="pill" id="state"><span class="dot"></span><span id="stateText">Band hai — shift chunkar Start dabayein</span></div>
  <div class="pill" id="tripBox" style="display:none"><span class="dot"></span><span id="tripText">Trip shuru nahi hui</span></div>

  <div id="shiftWrap" style="display:none">
    <div class="sec">1. Shift par tick karein</div>
    <div class="shifts" id="shifts"></div>
  </div>

  <button id="go" disabled>Trip Shuru Karein</button>

  <div id="mapBox">
    <div id="map"></div>
    <div class="mapbtns">
      <button type="button" id="followBtn" class="on">🎯 Bus</button>
      <button type="button" id="fitBtn">🗺️ Sab</button>
    </div>
  </div>

  <div id="nextCard" class="next" style="display:none"></div>
  <div id="stopWarn"></div>
  <div id="listWrap" style="display:none">
    <div class="sec" id="listTitle">Stops</div>
    <div class="list" id="stopList"></div>
  </div>

  <div id="queueInfo"></div>
  <div id="msg"></div>

  <div class="grid">
    <div class="box"><small>Aakhri location bheji</small><b id="ago">—</b></div>
    <div class="box"><small>Kitni baar bheji</small><b id="count">0</b></div>
    <div class="box"><small>GPS quality</small><b id="qual">—</b></div>
    <div class="box"><small>Speed</small><b id="spd">—</b></div>
    <div class="box"><small>Aaj ki doori</small><b id="km">0.0 km</b></div>
    <div class="box"><small>Battery</small><b id="batt">—</b></div>
  </div>
  <button id="dimBtn" type="button">🌙 Screen dim karein (battery bachao)</button>

  <div class="note">
    <strong>Kaise chalayein:</strong><br>
    1. Jo shift chalani hai us par <strong>tick</strong> karein → <strong>Trip Shuru Karein</strong>. Students ko "bus nikal gayi" ka message jata hai.<br>
    2. Map par har student ka ghar number ke saath dikhega. <span style="color:#fbbf24">Peela</span> = agla stop. Stop aane par phone bolega aur vibrate karega.<br>
    3. Bachcha baith gaya / utar gaya → <strong>✔ Ho gaya</strong>. Nahi aaya → <strong>✖ Nahi aaya</strong>. Bus stop par 8 sec se zyada ruki ho to aage badhte hi apne aap ✔ lag jata hai.<br>
    4. Kaam poora → <strong>Trip Khatam Karein</strong>.<br>
    <strong>Dhyan dein:</strong> screen ON rakhein, page band na karein · Location <strong>Allow</strong> · phone dashboard par shishe ke paas · map ki line seedhi doori hai, sadak ka raasta nahi — raasta ke liye <strong>🧭 Raasta</strong> dabayein · net na ho to location aur ✔ phone mein jama rehte hain.
  </div>
</div>

<div id="dim"><b>Tracking chalu hai</b><span id="dimInfo"></span><div id="dimNext"></div><br>Screen par tap karein — wapas dikhega</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
const KEY = <?= json_encode($key) ?>;
const ENDPOINT = 'gps_update.php';
const TRIP = 'bus_trip.php';

// ── Tuning ────────────────────────────────────────────────────────────────
const CFG = {
  ACC_GOOD: 50,          // m  — first fix must be at least this good (during warm-up)
  ACC_MAX: 120,          // m  — never use a fix worse than this
  WARMUP_MS: 25000,      // max wait for a good first fix
  MAX_SPEED_MS: 45,      // m/s (~160 km/h) — faster between two fixes = GPS glitch
  GLITCH_ACCEPT: 4,      // this many "glitches" in a row => it is real movement, accept
  QUEUE_MAX: 300,        // offline buffer size
  MAX_BACKFILL_S: 21000,
  STALL_MS: 20000,       // no GPS callback for this long while tracking → restart the GPS watch
  TURN_DEG: 30,          // heading change that triggers an extra send (keeps the map line on the road at turns)
  JUMP_SEND_M: 120,      // moved this far since the last send → send now
  APPROACH_M: 300,       // announce the next stop at this distance
  ARRIVE_M: 45,          // "at the stop" radius (+ up to 25 m of GPS accuracy)
  DWELL_S: 8,            // standing this long at a stop (slow) = stopped there
  LEAVE_M: 60,           // ...and once this much further away again → auto ✔
  STOPS_REFRESH_MS: 300000
};
const $ = id => document.getElementById(id);
const sleepMs = ms => new Promise(r => setTimeout(r, ms));
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

// ── State ─────────────────────────────────────────────────────────────────
let running = false, watchId = null, loopTimer = null, wakeLock = null;
let startedAt = 0, accepted = null, lastRaw = null, glitch = 0, lastPosAt = 0, lastRestart = 0;
let lastSentAt = 0, lastTryAt = 0, sent = 0, sending = false, flushing = false, lastSentHdg = null, lastSentPos = null;
let km = 0, lastHeading = 0, lastVibe = 0;
let queue = [];
let tripOpen = null;            // {id, shift, started_at} while a trip is running
let shifts = [], selShift = 1;
let stops = [], missing = [], order = [], orderFromPos = false, stopsLoadedAt = 0, stopsShift = null;
let marks = [];                 // stop marks waiting to reach the server (offline-safe)
let near = {};                  // per stop: {in, dwell, stopped, annArr, ann}
let voiceOn = true, follow = true;

function store(k, v) { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) {} }
function load(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
function setState(cls, text) { $('state').className = 'pill ' + cls; $('stateText').textContent = text; }
let msgUntil = 0;   // an important message (trip started / ended) stays visible for a while
function say(t, keepMs) {
  const now = Date.now();
  if (!t && now < msgUntil) return;
  $('msg').textContent = t || '';
  msgUntil = keepMs ? now + keepMs : 0;
}
function vibe(force) { const n = Date.now(); if (navigator.vibrate && (force || n - lastVibe > 60000)) { lastVibe = n; navigator.vibrate([200, 100, 200]); } }
function speak(t) {
  if (!voiceOn || !('speechSynthesis' in window)) return;
  try { const u = new SpeechSynthesisUtterance(t); u.lang = 'hi-IN'; u.rate = 0.95; speechSynthesis.cancel(); speechSynthesis.speak(u); } catch (e) {}
}
function fmtDist(m) { return m == null ? '—' : m < 1000 ? Math.round(m / 10) * 10 + ' m' : (m / 1000).toFixed(1) + ' km'; }

// ── Geometry ──────────────────────────────────────────────────────────────
function dist(a, b, c, d) {   // metres between (lat1,lng1) and (lat2,lng2)
  const R = 6371000, r = Math.PI / 180;
  const dp = (c - a) * r, dl = (d - b) * r;
  const h = Math.sin(dp / 2) ** 2 + Math.cos(a * r) * Math.cos(c * r) * Math.sin(dl / 2) ** 2;
  return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
}
function bearing(a, b, c, d) {
  const r = Math.PI / 180, y = Math.sin((d - b) * r) * Math.cos(c * r);
  const x = Math.cos(a * r) * Math.sin(c * r) - Math.sin(a * r) * Math.cos(c * r) * Math.cos((d - b) * r);
  return (Math.atan2(y, x) / r + 360) % 360;
}
function angDiff(a, b) { const d = Math.abs(a - b) % 360; return d > 180 ? 360 - d : d; }

// ── Kalman filter (smooths GPS jitter; trusts good fixes more, bad fixes less) ──
const kf = {
  q: 3, v: -1, lat: 0, lng: 0, t: 0,
  reset() { this.v = -1; },
  update(lat, lng, acc, t, speedMs) {
    acc = Math.max(acc || 10, 1);
    this.q = Math.min(40, Math.max(3, (speedMs || 0) * 1.2));   // faster vehicle => follow measurements more closely
    if (this.v < 0) { this.lat = lat; this.lng = lng; this.v = acc * acc; this.t = t; return; }
    const dt = t - this.t;
    if (dt > 0) { this.v += dt * this.q * this.q / 1000; this.t = t; }
    const k = this.v / (this.v + acc * acc);
    this.lat += k * (lat - this.lat);
    this.lng += k * (lng - this.lng);
    this.v = (1 - k) * this.v;
  }
};

// ── UI helpers ────────────────────────────────────────────────────────────
function qualityOf(acc) {
  if (acc == null) return ['—', ''];
  if (acc <= 10) return ['Bahut achhi ±' + Math.round(acc) + ' m', 'q-great'];
  if (acc <= 25) return ['Achhi ±' + Math.round(acc) + ' m', 'q-good'];
  if (acc <= 60) return ['Theek ±' + Math.round(acc) + ' m', 'q-fair'];
  return ['Kamzor ±' + Math.round(acc) + ' m', 'q-poor'];
}
function render() {
  const now = Date.now();
  $('ago').textContent = lastSentAt ? (s => s < 60 ? s + ' sec pehle' : Math.round(s / 60) + ' min pehle')(Math.round((now - lastSentAt) / 1000)) : '—';
  $('count').textContent = sent;
  const q = qualityOf(lastRaw ? lastRaw.acc : null);
  $('qual').textContent = q[0]; $('qual').className = q[1];
  $('spd').textContent = accepted ? Math.round(accepted.speed * 3.6) + ' km/h' : '—';
  $('km').textContent = km.toFixed(1) + ' km';
  const pm = marks.length ? ' · ' + marks.length + ' stop-mark net aane par jayenge' : '';
  $('queueInfo').textContent = queue.length || marks.length ? '📡 ' + queue.length + ' location' + pm + ' net aane par bheji jayengi' : '';
  $('dimInfo').textContent = lastSentAt ? 'Aakhri location ' + Math.round((now - lastSentAt) / 1000) + ' sec pehle' : '';
}

// ── Offline buffer ────────────────────────────────────────────────────────
function saveQueue() { store('trk_q_' + KEY, queue.length ? JSON.stringify(queue) : null); }
function loadQueue() { try { queue = JSON.parse(load('trk_q_' + KEY) || '[]').filter(p => p && isFinite(p.lat)); } catch (e) { queue = []; } }
function enqueue(p) {
  queue.push(p);
  if (queue.length > CFG.QUEUE_MAX) queue.splice(0, queue.length - CFG.QUEUE_MAX);
  saveQueue();
}

// ── Network ───────────────────────────────────────────────────────────────
// returns 'ok' | 'drop' (server said no, don't retry) | 'fail' (network problem, retry later)
async function post(p, ageSec) {
  const body = new URLSearchParams({
    key: KEY,
    lat: p.lat.toFixed(6), lng: p.lng.toFixed(6),
    speed: p.speed.toFixed(2), heading: p.heading.toFixed(1),
    acc: Math.round(p.acc || 0), su: 'ms'
  });
  if (ageSec >= 20) body.set('age', String(Math.round(ageSec)));
  const ctrl = new AbortController();
  const to = setTimeout(() => ctrl.abort(), 12000);
  try {
    const r = await fetch(ENDPOINT, {method: 'POST', body, signal: ctrl.signal, cache: 'no-store'});
    if (!r.ok) return 'fail';
    const j = await r.json();
    if (j.ok) return 'ok';
    if (/Server error/i.test(j.msg || '')) return 'fail';
    post.lastMsg = j.msg || '';
    return 'drop';
  } catch (e) {
    return 'fail';
  } finally {
    clearTimeout(to);
  }
}

async function flushQueue() {
  if (flushing || !queue.length) return;
  flushing = true;
  try {
    let n = 0;
    while (queue.length && n < 25) {
      const p = queue[0], age = (Date.now() - p.t) / 1000;
      if (age > CFG.MAX_BACKFILL_S) { queue.shift(); continue; }
      const res = await post(p, Math.max(20, age));
      if (res === 'fail') break;          // still offline — keep the rest
      queue.shift(); n++;                 // sent, or server refused it — either way don't retry
      await sleepMs(150);
    }
    saveQueue();
  } finally { flushing = false; render(); }
}

async function sendLive() {
  if (!running || !accepted || sending) return;
  sending = true;
  const p = Object.assign({}, accepted);
  lastTryAt = Date.now();
  try {
    const res = await post(p, 0);
    if (res === 'ok') {
      sent++; lastSentAt = Date.now(); lastSentHdg = p.heading; lastSentPos = p;
      setState('live', 'Live — location school ko mil rahi hai'); say('');
      flushQueue(); flushMarks();
    } else if (res === 'fail') {
      enqueue(p); lastSentPos = p;   // buffered; the next try waits for the normal interval (lastTryAt)
      setState('warn', 'Internet nahi hai — location jama ho rahi hai');
      vibe();
    } else if (!/Too frequent|Implausible/i.test(post.lastMsg || '')) {
      setState('warn', post.lastMsg || 'Server ne location nahi li');
    }
  } finally { sending = false; render(); }
}

// ── GPS processing ────────────────────────────────────────────────────────
function sendInterval() {
  const v = accepted ? accepted.speed : 0;     // m/s
  return v > 8 ? 5000 : v > 2 ? 8000 : 20000; // fast: 5s · moving: 8s · parked: 20s (keeps bus "live")
}
// Extra send on a turn or a long move, so the trail on everyone's map follows the real road.
function sendEarly(now) {
  if (!accepted || !lastSentPos || now - lastTryAt < 3000 || accepted.speed < 2) return false;
  if (lastSentHdg != null && angDiff(accepted.heading, lastSentHdg) >= CFG.TURN_DEG) return true;
  return dist(lastSentPos.lat, lastSentPos.lng, accepted.lat, accepted.lng) >= CFG.JUMP_SEND_M;
}

function onPos(p) {
  const c = p.coords, now = Date.now();
  lastPosAt = now;
  const raw = {
    lat: c.latitude, lng: c.longitude, acc: c.accuracy || 999,
    speed: (c.speed != null && isFinite(c.speed) && c.speed >= 0) ? c.speed : null,
    heading: (c.heading != null && isFinite(c.heading)) ? c.heading : null,
    t: Math.min(p.timestamp || now, now)
  };
  if (accepted && raw.t <= accepted.t) return;   // duplicate / out-of-order callback
  lastRaw = raw;

  // 1) warm-up: wait (a little) for a good first fix instead of sending a rough one
  if (!accepted && raw.acc > CFG.ACC_GOOD && now - startedAt < CFG.WARMUP_MS) {
    setState('warn', 'GPS lock ho raha hai… (±' + Math.round(raw.acc) + ' m)'); render(); return;
  }
  // 2) too inaccurate
  if (raw.acc > CFG.ACC_MAX) {
    setState('warn', 'GPS weak (±' + Math.round(raw.acc) + ' m) — khule mein jaayein'); render(); return;
  }
  // 3) glitch: impossible jump with a mediocre fix
  if (accepted) {
    const dt = Math.max(1, (raw.t - accepted.t) / 1000);
    const d = dist(accepted.lat, accepted.lng, raw.lat, raw.lng);
    if (d / dt > CFG.MAX_SPEED_MS && d > 100 && raw.acc > 20) {
      if (++glitch < CFG.GLITCH_ACCEPT) { render(); return; }
      kf.reset();                       // it kept happening: the bus really is there now
    }
    glitch = 0;
    if (dt > 60) kf.reset();            // long gap (tunnel / GPS off): don't drag the old estimate along
  }

  // 4) smooth (if the phone gives no speed, use the last known one so the filter doesn't lag behind)
  kf.update(raw.lat, raw.lng, raw.acc, raw.t, raw.speed != null ? raw.speed : (accepted ? accepted.speed : 0));
  let lat = kf.lat, lng = kf.lng, moved = 0, dt = 1;
  if (accepted) {
    dt = Math.max(0.5, (raw.t - accepted.t) / 1000);
    moved = dist(accepted.lat, accepted.lng, lat, lng);
  }

  // 5) speed: device value if present, else from distance / time (capped: one noisy pair must not show 150 km/h)
  let speed = raw.speed != null ? raw.speed : (accepted ? moved / dt : 0);
  if (!isFinite(speed) || speed < 0) speed = 0;
  speed = Math.min(speed, CFG.MAX_SPEED_MS);

  // 6) parked: hold the last position so GPS drift doesn't draw fake movement
  const still = speed < 0.8 && moved < Math.max(8, raw.acc * 0.6);
  if (accepted && still) { lat = accepted.lat; lng = accepted.lng; speed = 0; moved = 0; }

  // 7) heading: from device when moving, else from the track
  let heading = lastHeading;
  if (raw.heading != null && speed > 1) heading = raw.heading;
  else if (accepted && moved > 10) heading = bearing(accepted.lat, accepted.lng, lat, lng);
  lastHeading = heading;

  // 8) distance covered today
  if (accepted && !still && moved > 5 && moved < 2000) { km += moved / 1000; store('trk_km_' + KEY, JSON.stringify({d: new Date().toDateString(), km})); }

  const first = !accepted;
  accepted = {lat, lng, acc: raw.acc, speed, heading, t: raw.t};
  if (first && running) sendLive();   // first good fix goes out immediately
  if (first && stops.length && !orderFromPos) planOrder();
  drawBus(); checkStops(dt); renderStops();
  render();
}

function onErr(e) {
  if (e.code === 1) {
    stop();
    setState('err', 'Location permission band hai');
    showPermHelp('denied');
  } else if (e.code === 2) {
    setState('warn', 'GPS signal nahi mil raha');
    say('Khule aasman ke neeche jaayein aur phone ka Location (GPS) ON rakhein.');
    vibe();
  } else {
    setState('warn', 'GPS dhoondh rahe hain…');
  }
}

// One tick per second: decide when to send, watch for silent failures
function loop() {
  const now = Date.now();
  if (accepted && (now - lastTryAt >= sendInterval() || sendEarly(now))) sendLive();
  // Some phones silently stop delivering positions (power saving): restart the watch instead of just warning.
  if (running && now - Math.max(lastPosAt, startedAt) > CFG.STALL_MS && now - lastRestart > CFG.STALL_MS) {
    lastRestart = now; startWatch();
  }
  if (lastRaw && now - lastPosAt > 60000) { setState('warn', 'GPS se update nahi aa raha'); say('Phone ka Location ON hai? Page screen par khula rakhein.'); vibe(); }
  if (queue.length && !flushing && now - lastSentAt > 30000) flushQueue();
  if (marks.length && now % 15000 < 1000) flushMarks();
  if (shifts.length && now - stopsLoadedAt > CFG.STOPS_REFRESH_MS) loadStops();   // students may move their pin
  render();
}

function startWatch() {
  if (watchId !== null) navigator.geolocation.clearWatch(watchId);
  watchId = navigator.geolocation.watchPosition(onPos, onErr, {enableHighAccuracy: true, maximumAge: 0, timeout: 30000});
}

async function getWake() {
  try {
    if ('wakeLock' in navigator && !wakeLock) {
      wakeLock = await navigator.wakeLock.request('screen');
      wakeLock.addEventListener('release', () => { wakeLock = null; });
    }
  } catch (e) { /* unsupported / denied — tracker still works while the screen is on */ }
}

async function start() {
  if (!('geolocation' in navigator)) { say('Is browser mein GPS support nahi hai. Chrome use karein.'); return; }
  if (!window.isSecureContext) { showPermHelp('http'); return; }
  running = true; startedAt = Date.now(); accepted = null; glitch = 0; lastPosAt = 0; kf.reset();
  store('trk_on_' + KEY, '1');
  $('go').textContent = 'Trip Khatam Karein'; $('go').classList.add('stop');
  setState('warn', 'GPS dhoondh rahe hain…'); say(''); $('permBox').style.display = 'none';
  await getWake();
  startWatch();
  clearInterval(loopTimer);
  loopTimer = setInterval(loop, 1000);
  tripStartNow();
}

function stop() {
  running = false;
  store('trk_on_' + KEY, null);
  if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
  clearInterval(loopTimer);
  if (wakeLock) { try { wakeLock.release(); } catch (e) {} wakeLock = null; }
  $('dim').classList.remove('show');
  $('go').textContent = 'Trip Shuru Karein'; $('go').classList.remove('stop');
  setState('', 'Band hai — shift chunkar Start dabayein');
}

// ── Location permission help (before the driver is stuck) ─────────────────
function showPermHelp(kind) {
  const b = $('permBox');
  if (kind === 'http') {
    b.innerHTML = '<b>🔒 Ye link https:// se nahi khula</b>Phone par GPS sirf <strong>https://</strong> link par chalta hai. School admin se sahi (https) link lein.';
  } else if (kind === 'denied') {
    b.innerHTML = '<b>📍 Location ki permission band hai</b>'
      + '1. Address bar mein link ke baayein 🔒 / ⓘ icon dabayein<br>2. <strong>Permissions → Location → Allow</strong><br>'
      + '3. Phone ki Settings → Location <strong>ON</strong> (Mode: High accuracy)<br>4. Page reload karke Start dabayein'
      + '<br><button type="button" onclick="location.reload()" style="margin-top:8px;padding:9px 14px;border-radius:10px;border:0;background:#fff;color:#7f1d1d;font-weight:700">↻ Reload</button>';
  } else return;
  b.style.display = 'block';
}
async function checkPermission() {
  if (!window.isSecureContext) { showPermHelp('http'); return; }
  try {
    const st = await navigator.permissions.query({name: 'geolocation'});
    const upd = () => { if (st.state === 'denied') showPermHelp('denied'); else $('permBox').style.display = 'none'; };
    upd(); st.onchange = upd;
  } catch (e) { /* Permissions API missing: we find out on Start */ }
}

// ── Trip (start / stop) ───────────────────────────────────────────────────
async function tripCall(action, extra) {
  const body = new URLSearchParams(Object.assign({key: KEY, action}, extra || {}));
  const ctrl = new AbortController(), to = setTimeout(() => ctrl.abort(), 12000);
  try {
    const r = await fetch(TRIP, {method: 'POST', body, cache: 'no-store', signal: ctrl.signal});
    return await r.json();
  } catch (e) { return null; } finally { clearTimeout(to); }
}
function showTrip() {
  const b = $('tripBox');
  b.style.display = '';
  if (tripOpen) {
    b.className = 'pill live';
    $('tripText').textContent = 'Shift ' + tripOpen.shift + ' ki trip chal rahi hai (' + (tripOpen.started_at || '').slice(11, 16) + ' se)';
  } else {
    b.className = 'pill';
    $('tripText').textContent = 'Trip shuru nahi hui';
  }
  renderShifts();
}
let tripStarting = false;
async function tripStartNow() {
  if (tripOpen || tripStarting) return;
  tripStarting = true;
  let r;
  try { r = await tripCall('start', {shift: selShift}); } finally { tripStarting = false; }
  if (r && r.ok && r.trip) {
    tripOpen = r.trip; selShift = tripOpen.shift; showTrip();
    if (!r.already) {
      say(r.notified ? 'Trip shuru. ' + r.notified + ' students ko "bus nikal gayi" ka message gaya.' : 'Trip shuru ho gayi.', 15000);
      near = {};
    }
    await loadStops();
    if (!r.already) planOrder(true);
  } else {
    say('Trip shuru nahi ho paayi (internet?) — tracking chal rahi hai, net aate hi dobara koshish hogi.');
    setTimeout(() => { if (running && !tripOpen) tripStartNow(); }, 20000);
  }
}
async function tripStopNow() {
  await flushMarks();
  const r = await tripCall('stop');
  tripOpen = null; showTrip();
  if (r && r.ok && r.summary) {
    const s = r.summary;
    const dn = stops.filter(x => x.status === 'done').length, ab = stops.filter(x => x.status === 'absent').length;
    say('Trip khatam ✔  ' + s.distance_km + ' km · ' + s.minutes + ' min · max ' + Math.round(s.max_speed) + ' km/h · '
      + dn + ' bachche ✔' + (ab ? ' · ' + ab + ' nahi aaye' : ''), 60000);
  }
  stops.forEach(s => { s.status = 'pending'; }); near = {};
  loadStops();
}

// ── Shifts ────────────────────────────────────────────────────────────────
function renderShifts() {
  if (!shifts.length) return;
  $('shiftWrap').style.display = '';
  $('shifts').innerHTML = shifts.map(s => {
    const sel = s.no === selShift, lock = tripOpen && !sel;
    const t = [s.pickup && '🌅 Pickup ' + s.pickup, s.drop && '🏫 Drop ' + s.drop].filter(Boolean).join('<br>') || 'Time set nahi';
    return '<button type="button" class="shift' + (sel ? ' sel' : '') + (lock ? ' lock' : '') + '" data-s="' + s.no + '">'
      + '<span class="tick">' + (sel ? '✓' : '') + '</span><span><b>Shift ' + s.no + '</b><small>' + t + '<br>👦 ' + s.with_home + '/' + s.students + ' ghar marked</small></span></button>';
  }).join('');
  $('shifts').querySelectorAll('.shift').forEach(b => b.onclick = () => pickShift(+b.dataset.s));
}
function pickShift(n) {
  if (tripOpen && n !== tripOpen.shift) { say('Shift ' + tripOpen.shift + ' ki trip chal rahi hai. Shift badalne ke liye pehle Trip Khatam karein.'); vibe(true); return; }
  if (n === selShift && stopsShift === n) return;
  selShift = n; store('trk_shift_' + KEY, String(n));
  renderShifts(); near = {}; orderFromPos = false;
  loadStops();
}

// ── Stops (students' marked homes of the ticked shift) ────────────────────
async function loadStops() {
  stopsLoadedAt = Date.now();
  const r = await tripCall('stops', {shift: selShift});
  if (!r || !r.ok) { if (!stops.length) say('Students ki list nahi aayi (internet?) — thodi der mein dobara koshish hogi.'); stopsLoadedAt = Date.now() - CFG.STOPS_REFRESH_MS + 30000; return; }
  if (r.trip && !tripOpen) { tripOpen = r.trip; showTrip(); }
  const shiftChanged = stopsShift !== r.shift;
  stopsShift = r.shift; selShift = r.shift;
  // Local marks not yet on the server win over the server's (older) state
  const pend = {}; marks.forEach(m => { pend[m.id] = m.status; });
  stops = (r.stops || []).map(s => Object.assign(s, pend[s.id] ? {status: pend[s.id]} : {}));
  missing = r.missing || [];
  const ids = new Set(stops.map(s => s.id));
  const serverOrder = stops.filter(s => s.seq != null).sort((a, b) => a.seq - b.seq).map(s => s.id);
  if (!shiftChanged && order.length && order.every(id => ids.has(id)) && stops.every(s => order.includes(s.id))) {
    /* keep the current plan (same students) */
  } else if (tripOpen && serverOrder.length === stops.length && stops.length) {
    order = serverOrder; orderFromPos = true;       // reload in the middle of a trip: same order as before
  } else {
    planOrder();
  }
  drawStops(shiftChanged); renderStops(); renderWarn();
}

/**
 * Plan the visiting order of the pending stops: nearest-neighbour from the bus, then 2-opt to remove
 * crossings. Straight-line distances (no road data) — good enough to order nearby homes.
 * Done/absent stops keep their place at the end.
 */
function planOrder(push) {
  const pend = stops.filter(s => s.status === 'pending');
  let start = accepted ? {lat: accepted.lat, lng: accepted.lng} : null;
  orderFromPos = !!start;
  if (!start && pend.length) {   // no GPS yet: start from the stop farthest from the centre
    const c = pend.reduce((a, s) => ({lat: a.lat + s.lat / pend.length, lng: a.lng + s.lng / pend.length}), {lat: 0, lng: 0});
    start = pend.reduce((f, s) => dist(c.lat, c.lng, s.lat, s.lng) > dist(c.lat, c.lng, f.lat, f.lng) ? s : f, pend[0]);
  }
  const path = [], left = pend.slice();
  let cur = start;
  while (left.length) {
    let bi = 0, bd = Infinity;
    left.forEach((s, i) => { const d = dist(cur.lat, cur.lng, s.lat, s.lng); if (d < bd) { bd = d; bi = i; } });
    cur = left.splice(bi, 1)[0]; path.push(cur);
  }
  // 2-opt on an open path with a fixed start point
  const P = [start].concat(path), D = (a, b) => dist(P[a].lat, P[a].lng, P[b].lat, P[b].lng);
  for (let it = 0, better = true; better && it < 60; it++) {
    better = false;
    for (let i = 1; i < P.length - 1; i++) for (let k = i + 1; k < P.length; k++) {
      const before = D(i - 1, i) + (k + 1 < P.length ? D(k, k + 1) : 0);
      const after  = D(i - 1, k) + (k + 1 < P.length ? D(i, k + 1) : 0);
      if (after + 0.5 < before) { const seg = P.slice(i, k + 1).reverse(); P.splice(i, seg.length, ...seg); better = true; }
    }
  }
  order = P.slice(1).map(s => s.id).concat(stops.filter(s => s.status !== 'pending').map(s => s.id));
  drawStops(false); renderStops();
  if (tripOpen && (push || orderFromPos)) pushOrder();
}
let orderTimer = null;
function pushOrder() {
  clearTimeout(orderTimer);
  orderTimer = setTimeout(() => { if (tripOpen) tripCall('set_order', {order: order.join(',')}); }, 1500);
}

function stopById(id) { return stops.find(s => s.id === id); }
function orderedStops() { const m = {}; stops.forEach(s => m[s.id] = s); return order.map(id => m[id]).filter(Boolean); }
function nextStop() { return orderedStops().find(s => s.status === 'pending') || null; }
function distTo(s) { return accepted ? dist(accepted.lat, accepted.lng, s.lat, s.lng) : null; }

// Arrival / auto-done detection. Runs on every accepted GPS fix.
function checkStops(dt) {
  if (!accepted || !stops.length) return;
  const nx = nextStop(), acc = Math.min(accepted.acc || 0, 25), arriveR = CFG.ARRIVE_M + acc;
  stops.forEach(s => {
    if (s.status !== 'pending') return;
    const d = dist(accepted.lat, accepted.lng, s.lat, s.lng);
    const st = near[s.id] || (near[s.id] = {in: false, dwell: 0, stopped: false, annArr: false, ann: false});
    if (s === nx && !st.ann && d <= CFG.APPROACH_M && d > arriveR) {
      st.ann = true; vibe(true); speak('Agla stop ' + s.name + ', ' + Math.round(d / 10) * 10 + ' meter');
    }
    if (d <= arriveR) {
      st.in = true;
      if (!st.annArr) { st.annArr = true; vibe(true); speak(s.name + ' ka stop aa gaya'); }
      if (accepted.speed < 2.5) st.dwell += Math.min(dt, 5); else st.dwell = Math.max(0, st.dwell - dt);
      if (st.dwell >= CFG.DWELL_S) st.stopped = true;
    } else if (st.in && d > arriveR + CFG.LEAVE_M) {
      // Left the stop: if the bus really stood there, the child was picked up / dropped.
      if (st.stopped && tripOpen) { markStop(s.id, 'done', 'auto'); speak(s.name + ' ho gaya'); }
      st.in = false; st.dwell = 0; st.stopped = false; st.annArr = false;
    }
  });
}

// ── Marking (offline-safe) ────────────────────────────────────────────────
function saveMarks() { store('trk_marks_' + KEY, marks.length ? JSON.stringify(marks) : null); }
function loadMarks() { try { marks = JSON.parse(load('trk_marks_' + KEY) || '[]').filter(m => m && m.id && m.trip); } catch (e) { marks = []; } }
function markStop(id, status, by) {
  const s = stopById(id);
  if (!s) return;
  if (!tripOpen) { say('Pehle shift tick karke Trip Shuru Karein — tabhi ✔ lag sakta hai.'); vibe(true); return; }
  s.status = status; s.by = by || 'driver';
  marks = marks.filter(m => m.id !== id); marks.push({id, status, by: s.by, trip: tripOpen.id});
  saveMarks();
  if (status === 'pending') near[id] = null;
  // Re-plan the remaining stops from where the bus is now
  if (accepted) planOrder(true); else { order = order.filter(x => x !== id).concat(status === 'pending' ? [] : [id]); if (status === 'pending') order.unshift(id); pushOrder(); }
  if (map) map.closePopup();
  drawStops(false); renderStops(); render();
  flushMarks();
}
let marking = false;
async function flushMarks() {
  if (marking || !marks.length) return;
  marking = true;
  try {
    while (marks.length) {
      const m = marks[0];
      if (!tripOpen || m.trip !== tripOpen.id) { marks.shift(); continue; }   // trip already over
      const r = await tripCall('mark_stop', {student_id: m.id, status: m.status, by: m.by});
      if (!r) break;                                                          // offline — retry later
      if (!r.ok) say(r.msg || 'Mark save nahi hua');
      marks.shift();
    }
  } finally { saveMarks(); marking = false; render(); }
}

// ── Map ───────────────────────────────────────────────────────────────────
let map = null, busMk = null, accCirc = null, planLine = null, trail = null, stopMks = {}, trailPts = [];
function ensureMap() {
  if (map || typeof L === 'undefined') return !!map;
  $('mapBox').style.display = 'block';
  map = L.map('map', {zoomControl: true, attributionControl: false}).setView([20.59, 78.96], 5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19}).addTo(map);
  trail = L.polyline([], {color: '#22c55e', weight: 4, opacity: .8}).addTo(map);
  planLine = L.polyline([], {color: '#f59e0b', weight: 3, dashArray: '6 8', opacity: .9}).addTo(map);
  map.on('dragstart', () => setFollow(false));
  return true;
}
function setFollow(v) { follow = v; $('followBtn').classList.toggle('on', v); if (v && accepted && map) map.setView([accepted.lat, accepted.lng], Math.max(map.getZoom(), 16)); }
function fitAll() {
  if (!map) return;
  const pts = stops.map(s => [s.lat, s.lng]); if (accepted) pts.push([accepted.lat, accepted.lng]);
  if (pts.length > 1) { setFollow(false); map.fitBounds(pts, {padding: [30, 30], maxZoom: 17}); }
  else if (pts.length) map.setView(pts[0], 16);
}
function drawBus() {
  if (!accepted || !ensureMap()) return;
  const ll = [accepted.lat, accepted.lng];
  const icon = L.divIcon({className: '', iconSize: [34, 34], iconAnchor: [17, 17],
    html: '<div class="busic" style="transform:rotate(' + Math.round(accepted.heading) + 'deg)">▲</div>'});
  if (!busMk) { busMk = L.marker(ll, {icon, zIndexOffset: 1000}).addTo(map); accCirc = L.circle(ll, {radius: accepted.acc, color: '#22c55e', weight: 1, fillOpacity: .08}).addTo(map); map.setView(ll, 16); }
  else { busMk.setLatLng(ll).setIcon(icon); accCirc.setLatLng(ll).setRadius(accepted.acc); }
  const lp = trailPts[trailPts.length - 1];
  if (!lp || dist(lp[0], lp[1], ll[0], ll[1]) > 8) { trailPts.push(ll); if (trailPts.length > 800) trailPts.shift(); trail.setLatLngs(trailPts); }
  drawPlan();
  if (follow) map.panTo(ll, {animate: true});
}
function drawPlan() {
  if (!map) return;
  const pts = orderedStops().filter(s => s.status === 'pending').map(s => [s.lat, s.lng]);
  if (accepted) pts.unshift([accepted.lat, accepted.lng]);
  planLine.setLatLngs(pts);
}
function popupHtml(s) {
  const d = distTo(s), can = !!tripOpen;
  const nav = 'https://www.google.com/maps/dir/?api=1&travelmode=driving&destination=' + s.lat + ',' + s.lng;
  const st = s.status === 'done' ? '<span style="color:#16a34a">✔ ho gaya' + (s.by === 'auto' ? ' (auto)' : '') + '</span>' : s.status === 'absent' ? '<span style="color:#64748b">✖ nahi aaya</span>' : '';
  return '<div class="pp"><b>' + esc(s.name) + '</b> ' + esc(s.cls) + '<br>' + fmtDist(d) + ' door ' + st
    + '<div class="acts">' + (s.status === 'pending'
      ? '<button class="b-done"' + (can ? '' : ' disabled') + ' onclick="markStop(' + s.id + ',\'done\')">✔ Ho gaya</button><button class="b-abs"' + (can ? '' : ' disabled') + ' onclick="markStop(' + s.id + ',\'absent\')">✖ Nahi aaya</button>'
      : '<button class="b-undo" onclick="markStop(' + s.id + ',\'pending\')">↺ Wapas</button>')
    + '<a class="b-nav" target="_blank" rel="noopener noreferrer" href="' + nav + '">🧭 Raasta</a></div></div>';
}
function drawStops(fit) {
  if (!stops.length) { Object.values(stopMks).forEach(m => m.remove()); stopMks = {}; if (planLine) planLine.setLatLngs([]); return; }
  if (!ensureMap()) return;
  const nx = nextStop(), seqOf = {};
  orderedStops().filter(s => s.status === 'pending').forEach((s, i) => seqOf[s.id] = i + 1);
  const keep = new Set();
  stops.forEach(s => {
    keep.add(s.id);
    const cls = s.status === 'done' ? 'dn' : s.status === 'absent' ? 'ab' : s === nx ? 'nx' : '';
    const label = s.status === 'done' ? '✔' : s.status === 'absent' ? '✖' : seqOf[s.id];
    const sz = cls === 'nx' ? 34 : 28;
    const icon = L.divIcon({className: '', iconSize: [sz, sz], iconAnchor: [sz / 2, sz / 2], html: '<div class="stic ' + cls + '">' + label + '</div>'});
    let m = stopMks[s.id];
    if (!m) { m = stopMks[s.id] = L.marker([s.lat, s.lng], {icon}).addTo(map); m.bindPopup(''); m.on('popupopen', () => m.setPopupContent(popupHtml(stopById(s.id) || s))); }
    else m.setLatLng([s.lat, s.lng]).setIcon(icon);
    m.setZIndexOffset(cls === 'nx' ? 900 : cls ? 0 : 500);
  });
  Object.keys(stopMks).forEach(id => { if (!keep.has(+id)) { stopMks[id].remove(); delete stopMks[id]; } });
  drawPlan();
  if (fit && !accepted) fitAll();
}
function focusStop(id) {
  const s = stopById(id); if (!s || !map) return;
  setFollow(false); map.setView([s.lat, s.lng], 17); stopMks[id] && stopMks[id].openPopup();
  $('mapBox').scrollIntoView({behavior: 'smooth', block: 'start'});
}

// ── Next-stop card + list ─────────────────────────────────────────────────
function renderStops() {
  const card = $('nextCard'), list = $('stopList');
  if (!stops.length) { card.style.display = 'none'; $('listWrap').style.display = 'none'; $('dimNext').textContent = ''; return; }
  const os = orderedStops(), nx = nextStop(), can = !!tripOpen;
  const dn = stops.filter(s => s.status === 'done').length, ab = stops.filter(s => s.status === 'absent').length;
  if (nx) {
    const d = distTo(nx), here = d != null && d <= CFG.ARRIVE_M + Math.min(accepted.acc || 0, 25);
    const eta = d != null && accepted.speed > 2 ? Math.max(1, Math.round(d / accepted.speed / 60)) + ' min' : '';
    const pos = os.filter(s => s.status === 'pending').indexOf(nx) + 1;
    const nav = 'https://www.google.com/maps/dir/?api=1&travelmode=driving&destination=' + nx.lat + ',' + nx.lng;
    card.className = 'next' + (here ? ' here' : '');
    card.innerHTML = '<div class="lbl">' + (here ? '📍 Stop aa gaya' : 'Agla stop #' + pos) + '</div>'
      + '<div class="nm">' + esc(nx.name) + ' <span style="font-size:.85rem;color:#94a3b8;font-weight:600">' + esc(nx.cls) + '</span></div>'
      + '<div class="meta">' + (d == null ? 'GPS ka intezaar…' : fmtDist(d) + ' door' + (eta ? ' · ~' + eta : '')) + ' · ' + dn + '/' + stops.length + ' ✔' + (ab ? ' · ' + ab + ' ✖' : '') + '</div>'
      + '<div class="acts"><button class="b-done"' + (can ? '' : ' disabled') + ' onclick="markStop(' + nx.id + ',\'done\')">✔ Ho gaya</button>'
      + '<button class="b-abs"' + (can ? '' : ' disabled') + ' onclick="markStop(' + nx.id + ',\'absent\')">✖ Nahi aaya</button>'
      + '<a class="b-nav" target="_blank" rel="noopener noreferrer" href="' + nav + '">🧭 Raasta</a></div>'
      + (can ? '' : '<div style="font-size:.75rem;color:#94a3b8;margin-top:8px">✔ / ✖ trip shuru hone ke baad lagenge.</div>');
    $('dimNext').textContent = 'Agla: ' + nx.name + (d != null ? ' · ' + fmtDist(d) : '');
  } else {
    card.className = 'next here';
    card.innerHTML = '<div class="lbl">Sab stop poore ✔</div><div class="nm">' + dn + ' / ' + stops.length + ' ho gaye</div>'
      + '<div class="meta">' + (ab ? ab + ' nahi aaye · ' : '') + 'Kaam poora ho to Trip Khatam karein.</div>';
    $('dimNext').textContent = 'Sab stop poore ✔';
  }
  card.style.display = '';
  $('listWrap').style.display = '';
  $('listTitle').textContent = 'Shift ' + selShift + ' · ' + stops.length + ' stops (raaste ke kram se)';
  let i = 0;
  list.innerHTML = os.map(s => {
    const p = s.status === 'pending', cls = s.status === 'done' ? 'dn' : s.status === 'absent' ? 'ab' : s === nx ? 'nx' : '';
    const lab = s.status === 'done' ? '✔' : s.status === 'absent' ? '✖' : ++i;
    const tail = s.status === 'done' ? (s.by === 'auto' ? 'auto ✔' : '✔') : s.status === 'absent' ? 'nahi aaya' : fmtDist(distTo(s));
    return '<div class="row' + (p ? '' : ' dim') + '" onclick="focusStop(' + s.id + ')"><div class="no ' + cls + '">' + lab + '</div>'
      + '<div class="t"><b>' + esc(s.name) + '</b><small>' + esc(s.cls) + '</small></div><div class="d">' + tail + '</div></div>';
  }).join('');
}
function renderWarn() {
  const w = [];
  if (missing.length) w.push('⚠️ <strong>' + missing.length + ' students</strong> ne ghar ki location nahi lagayi (map par nahi dikhenge): '
    + missing.slice(0, 12).map(m => esc(m.name) + (m.cls ? ' (' + esc(m.cls) + ')' : '')).join(', ') + (missing.length > 12 ? ' …' : ''));
  // A pin far away from all the others is usually a mistake (set from school / wrong city)
  if (stops.length >= 3) {
    const c = stops.reduce((a, s) => ({lat: a.lat + s.lat / stops.length, lng: a.lng + s.lng / stops.length}), {lat: 0, lng: 0});
    const ds = stops.map(s => dist(c.lat, c.lng, s.lat, s.lng)).sort((a, b) => a - b), med = ds[Math.floor(ds.length / 2)];
    const odd = stops.filter(s => { const d = dist(c.lat, c.lng, s.lat, s.lng); return d > 15000 && d > med * 4; });
    if (odd.length) w.push('⚠️ In students ki location baaki sab se bahut door hai — shayad galat lagi hai: ' + odd.map(s => esc(s.name)).join(', ') + '. School ko batayein.');
  }
  if (stopsShift !== null && !stops.length && !missing.length) w.push('Is shift mein koi student nahi hai.');
  $('stopWarn').innerHTML = w.map(t => '<div class="warnbox">' + t + '</div>').join('');
}

// ── Page events ───────────────────────────────────────────────────────────
$('go').addEventListener('click', async () => {
  if (!running) { await start(); return; }
  const left = stops.filter(s => s.status === 'pending').length;
  if (!confirm('Trip khatam karein? Tracking band ho jayegi.' + (left ? '\n\n' + left + ' stop abhi baaki hain.' : ''))) return;
  await tripStopNow();
  stop();
});
$('dimBtn').addEventListener('click', () => { if (running) $('dim').classList.add('show'); else say('Pehle tracking chalu karein.'); });
$('dim').addEventListener('click', () => $('dim').classList.remove('show'));
$('followBtn').addEventListener('click', () => setFollow(!follow));
$('fitBtn').addEventListener('click', fitAll);
$('voiceBtn').addEventListener('click', () => {
  voiceOn = !voiceOn; store('trk_voice_' + KEY, voiceOn ? '1' : '0');
  $('voiceBtn').textContent = voiceOn ? '🔊' : '🔇';
  if (voiceOn) speak('Awaaz chalu');
});

// Back on the page (screen was off / app switched): re-take the wake lock, restart GPS watching
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible' && running) {
    getWake(); startWatch();
    say('Page background mein gaya tha — tracking ke liye screen ON aur page khula rakhein.');
  }
});
window.addEventListener('online', () => { if (queue.length) flushQueue(); flushMarks(); if (running && !tripOpen) tripStartNow(); });
window.addEventListener('beforeunload', e => { if (running) { e.preventDefault(); e.returnValue = ''; } });

if (navigator.getBattery) {
  navigator.getBattery().then(b => {
    const upd = () => {
      $('batt').textContent = Math.round(b.level * 100) + '%' + (b.charging ? ' ⚡' : '');
      if (running && !b.charging && b.level < 0.15) { say('Battery kam hai — charger lagayein, warna tracking ruk jayegi.'); vibe(); }
    };
    upd(); b.addEventListener('levelchange', upd); b.addEventListener('chargingchange', upd);
  }).catch(() => {});
}

// ── Boot ──────────────────────────────────────────────────────────────────
async function loadInfo() {
  if (!KEY) { setState('err', 'Link galat hai'); say('Is link mein bus ki key nahi hai. School admin se naya link lein.'); return 'bad'; }
  try {
    const r = await fetch(ENDPOINT + '?info=1&key=' + encodeURIComponent(KEY), {cache: 'no-store'});
    const j = await r.json();
    if (!j.ok) { setState('err', 'Key galat ya bus inactive'); say(j.msg || ''); $('busName').textContent = 'Bus nahi mili'; return 'bad'; }
    $('busName').textContent = j.bus_name;
    $('busNum').textContent = j.bus_number || '';
    const t = await tripCall('status');
    if (t && t.ok) {
      tripOpen = t.trip || null;
      shifts = t.shifts || [];
      const sv = +load('trk_shift_' + KEY);
      selShift = tripOpen ? tripOpen.shift : (shifts.some(s => s.no === sv) ? sv : 1);
      showTrip();
      if (shifts.length) await loadStops();
    }
    return 'ok';
  } catch (e) {
    $('busName').textContent = 'Bus';
    say('Server se connect nahi ho paaya — internet check karein.');
    return 'net';
  }
}

(async () => {
  try { const k = JSON.parse(load('trk_km_' + KEY) || 'null'); if (k && k.d === new Date().toDateString()) km = +k.km || 0; } catch (e) {}
  voiceOn = load('trk_voice_' + KEY) !== '0'; $('voiceBtn').textContent = voiceOn ? '🔊' : '🔇';
  loadQueue(); loadMarks();
  render();
  checkPermission();
  const st = await loadInfo();
  if (st !== 'bad') {
    $('go').disabled = false;
    if (queue.length) flushQueue();
    if (load('trk_on_' + KEY) === '1') start();   // page was reloaded/killed while tracking — resume
  }
})();
</script>
</body>
</html>
