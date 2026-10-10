<?php
// api/driver_tracker.php — browser-based GPS sender for ONE bus (no app install needed).
// Open on the driver's phone (Chrome):  https://YOUR-DOMAIN/api/driver_tracker.php?key=BUS_API_KEY
// Each bus has its own key → its own link. No login: the per-bus key is the auth.
//
// Accuracy features: warm-up for a good first fix · Kalman smoothing (speed-adaptive) · glitch/outlier rejection ·
// parked-position hold (no GPS drift) · computed speed & heading · adaptive send rate · offline buffer with
// back-fill · wake-lock + auto-recovery · battery/signal/network warnings.
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$key = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($_GET['key'] ?? ''));
?><!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<meta name="robots" content="noindex,nofollow">
<title>Bus GPS Tracker</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;background:#0f172a;color:#e2e8f0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;display:flex;justify-content:center;padding:max(16px,env(safe-area-inset-top)) 16px max(16px,env(safe-area-inset-bottom))}
  .wrap{width:100%;max-width:440px}
  h1{font-size:1rem;margin:6px 0 2px;color:#94a3b8;font-weight:600}
  .bus{font-size:1.5rem;font-weight:700}
  .num{color:#94a3b8;font-size:.9rem;margin-bottom:14px}
  .pill{display:flex;align-items:center;gap:10px;padding:14px 16px;border-radius:14px;background:#1e293b;font-weight:600}
  .dot{width:12px;height:12px;border-radius:50%;background:#64748b;flex-shrink:0}
  .pill.live{background:#14532d}.pill.live .dot{background:#4ade80;box-shadow:0 0 0 4px rgba(74,222,128,.25)}
  .pill.warn{background:#713f12}.pill.warn .dot{background:#facc15}
  .pill.err{background:#7f1d1d}.pill.err .dot{background:#f87171}
  button{font-family:inherit}
  button#go{width:100%;margin:14px 0 10px;padding:22px;border:0;border-radius:18px;font-size:1.25rem;font-weight:700;color:#fff;background:#16a34a;cursor:pointer}
  button#go.stop{background:#dc2626}
  button#go:disabled{opacity:.5}
  button#dimBtn{width:100%;margin-bottom:12px;padding:12px;border:1px solid #334155;border-radius:12px;background:transparent;color:#94a3b8;font-size:.9rem;cursor:pointer}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .box{background:#1e293b;border-radius:12px;padding:12px}
  .box small{display:block;color:#94a3b8;font-size:.72rem;margin-bottom:2px}
  .box b{font-size:1rem}
  .q-great{color:#4ade80}.q-good{color:#a3e635}.q-fair{color:#facc15}.q-poor{color:#f87171}
  .note{margin-top:14px;font-size:.8rem;line-height:1.55;color:#94a3b8}
  .note strong{color:#e2e8f0}
  #msg{margin-top:12px;font-size:.85rem;color:#fbbf24;min-height:1.2em}
  #queueInfo{margin-top:6px;font-size:.78rem;color:#93c5fd;min-height:1em}
  #dim{display:none;position:fixed;inset:0;background:#000;z-index:50;color:#334155;align-items:center;justify-content:center;flex-direction:column;text-align:center;font-size:.95rem}
  #dim.show{display:flex}
  #dim b{color:#475569;font-size:1.1rem;margin-bottom:6px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Bus GPS Tracker</h1>
  <div class="bus" id="busName">Loading…</div>
  <div class="num" id="busNum">&nbsp;</div>

  <div class="pill" id="state"><span class="dot"></span><span id="stateText">Band hai — Start dabayein</span></div>
  <div class="pill" id="tripBox" style="display:none;margin-top:10px"><span class="dot"></span><span id="tripText">Trip shuru nahi hui</span></div>
  <div id="shiftRow" style="display:none;margin-top:10px">
    <label style="font-size:.85rem;color:#94a3b8">Kaunsi shift? </label>
    <select id="shiftSel" style="padding:10px;border-radius:10px;background:#1e293b;color:#e2e8f0;border:1px solid #334155;font-size:1rem"></select>
  </div>
  <button id="go" disabled>Trip Shuru Karein</button>
  <button id="dimBtn" type="button">🌙 Screen dim karein (battery bachao)</button>

  <div class="grid">
    <div class="box"><small>Aakhri location bheji</small><b id="ago">—</b></div>
    <div class="box"><small>Kitni baar bheji</small><b id="count">0</b></div>
    <div class="box"><small>GPS quality</small><b id="qual">—</b></div>
    <div class="box"><small>Speed</small><b id="spd">—</b></div>
    <div class="box"><small>Aaj ki doori</small><b id="km">0.0 km</b></div>
    <div class="box"><small>Battery</small><b id="batt">—</b></div>
  </div>
  <div id="queueInfo"></div>
  <div id="msg"></div>

  <div class="note">
    <strong>Zaroori baatein:</strong><br>
    • <strong>Trip Shuru</strong> dabate hi students ko "bus nikal gayi" ka message jata hai. Kaam poora hone par <strong>Trip Khatam</strong> dabayein — aaj ki doori aur stops yahin dikh jayenge.<br>
    • Tracking ke dauran <strong>screen ON</strong> rakhein aur is page ko band ya minimize na karein. Battery bachane ke liye "Screen dim karein" dabayein.<br>
    • Location ki permission <strong>Allow</strong> karein aur phone ka GPS (Location) ON rakhein.<br>
    • Internet na ho to location phone mein jama hoti rehti hai aur net aate hi apne aap bhej di jaati hai.<br>
    • Phone ko dashboard par khule mein lagayein (shishe ke paas) — GPS ka signal behtar aata hai.<br>
    • Screen lock karke chalane ke liye GPSLogger app use karein (school panel mein instructions hain).
  </div>
</div>

<div id="dim"><b>Tracking chalu hai</b><span id="dimInfo"></span><br><br>Screen par tap karein — wapas dikhega</div>

<script>
const KEY = <?= json_encode($key) ?>;
const ENDPOINT = 'gps_update.php';
const TRIP = 'bus_trip.php';
let tripOpen = null;   // {id, shift, started_at} while a trip is running

// ── Tuning ────────────────────────────────────────────────────────────────
const CFG = {
  ACC_GOOD: 50,        // m  — first fix must be at least this good (during warm-up)
  ACC_MAX: 120,        // m  — never use a fix worse than this
  WARMUP_MS: 25000,    // max wait for a good first fix
  MAX_SPEED_MS: 45,    // m/s (~160 km/h) — faster between two fixes = GPS glitch
  GLITCH_ACCEPT: 4,    // this many "glitches" in a row => it is real movement, accept
  QUEUE_MAX: 300,      // offline buffer size
  MAX_BACKFILL_S: 21000
};
const $ = id => document.getElementById(id);
const sleepMs = ms => new Promise(r => setTimeout(r, ms));

// ── State ─────────────────────────────────────────────────────────────────
let running = false, watchId = null, loopTimer = null, wakeLock = null;
let startedAt = 0, accepted = null, lastRaw = null, glitch = 0;
let lastSentAt = 0, sent = 0, sending = false, flushing = false;
let km = 0, lastHeading = 0, lastVibe = 0;
let queue = [];

function store(k, v) { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) {} }
function load(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
function setState(cls, text) { $('state').className = 'pill ' + cls; $('stateText').textContent = text; }
function say(t) { $('msg').textContent = t || ''; }
function vibe() { const n = Date.now(); if (navigator.vibrate && n - lastVibe > 60000) { lastVibe = n; navigator.vibrate([200, 100, 200]); } }

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
  $('queueInfo').textContent = queue.length ? '📡 ' + queue.length + ' location net aane par bheji jayengi' : '';
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
  try {
    const res = await post(p, 0);
    if (res === 'ok') {
      sent++; lastSentAt = Date.now();
      setState('live', 'Live — location school ko mil rahi hai'); say('');
      flushQueue();
    } else if (res === 'fail') {
      enqueue(p);
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

function onPos(p) {
  const c = p.coords, now = Date.now();
  const raw = {
    lat: c.latitude, lng: c.longitude, acc: c.accuracy || 999,
    speed: (c.speed != null && isFinite(c.speed)) ? c.speed : null,
    heading: (c.heading != null && isFinite(c.heading)) ? c.heading : null,
    t: p.timestamp || now
  };
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
  }

  // 4) smooth
  kf.update(raw.lat, raw.lng, raw.acc, raw.t, raw.speed);
  let lat = kf.lat, lng = kf.lng, moved = 0, dt = 1;
  if (accepted) {
    dt = Math.max(0.5, (raw.t - accepted.t) / 1000);
    moved = dist(accepted.lat, accepted.lng, lat, lng);
  }

  // 5) speed: device value if present, else from distance / time
  let speed = raw.speed != null ? raw.speed : (accepted ? moved / dt : 0);
  if (!isFinite(speed) || speed < 0) speed = 0;

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
  render();
  if (first) sendLive();   // first good fix goes out immediately
}

function onErr(e) {
  if (e.code === 1) {
    stop();
    setState('err', 'Location permission band hai');
    say('Browser ki Site settings mein Location ko Allow karein, phir dobara Start dabayein.');
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
  if (accepted && now - lastSentAt >= sendInterval()) sendLive();
  if (lastRaw && now - lastRaw.t > 60000) { setState('warn', 'GPS se update nahi aa raha'); say('Phone ka Location ON hai? Page screen par khula rakhein.'); vibe(); }
  if (queue.length && !flushing && now - lastSentAt > 30000) flushQueue();
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
  if (!window.isSecureContext) { say('GPS ke liye HTTPS zaroori hai. Link https:// se shuru hona chahiye.'); return; }
  running = true; startedAt = Date.now(); accepted = null; glitch = 0; kf.reset();
  store('trk_on_' + KEY, '1');
  $('go').textContent = 'Trip Khatam Karein'; $('go').classList.add('stop');
  setState('warn', 'GPS dhoondh rahe hain…'); say('');
  await getWake();
  startWatch();
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
  setState('', 'Band hai — Start dabayein');
}

// ── Trip (start / stop) ───────────────────────────────────────────────────
async function tripCall(action, extra) {
  const body = new URLSearchParams(Object.assign({key: KEY, action}, extra || {}));
  try {
    const r = await fetch(TRIP, {method: 'POST', body, cache: 'no-store'});
    return await r.json();
  } catch (e) { return null; }
}
function showTrip() {
  const b = $('tripBox');
  b.style.display = '';
  if (tripOpen) {
    b.className = 'pill live';
    $('tripText').textContent = 'Trip chal rahi hai (' + (tripOpen.started_at || '').slice(11, 16) + ' se)';
  } else {
    b.className = 'pill';
    $('tripText').textContent = 'Trip shuru nahi hui';
  }
}
async function tripStartNow() {
  if (tripOpen) return;
  const r = await tripCall('start', {shift: $('shiftSel').value || 1});
  if (r && r.ok && r.trip) {
    tripOpen = r.trip; showTrip();
    if (!r.already) say(r.notified ? 'Trip shuru. ' + r.notified + ' students ko "bus nikal gayi" ka message gaya.' : 'Trip shuru ho gayi.');
  } else {
    say('Trip shuru nahi ho paayi (internet?) — tracking phir bhi chal rahi hai.');
  }
}
async function tripStopNow() {
  const r = await tripCall('stop');
  tripOpen = null; showTrip();
  if (r && r.ok && r.summary) {
    const s = r.summary;
    say('Trip khatam ✔  ' + s.distance_km + ' km · ' + s.minutes + ' min · max ' + Math.round(s.max_speed) + ' km/h · ' + s.stops + ' stop');
  }
}

// ── Page events ───────────────────────────────────────────────────────────
$('go').addEventListener('click', async () => {
  if (!running) { await start(); return; }
  if (!confirm('Trip khatam karein? Tracking band ho jayegi.')) return;
  await tripStopNow();
  stop();
});
$('dimBtn').addEventListener('click', () => { if (running) $('dim').classList.add('show'); else say('Pehle tracking chalu karein.'); });
$('dim').addEventListener('click', () => $('dim').classList.remove('show'));

// Back on the page (screen was off / app switched): re-take the wake lock, restart GPS watching
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible' && running) {
    getWake(); startWatch();
    say('Page background mein gaya tha — tracking ke liye screen ON aur page khula rakhein.');
  }
});
window.addEventListener('online', () => { if (queue.length) flushQueue(); });
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
      if (t.shift_count > 1) {
        $('shiftSel').innerHTML = Array.from({length: t.shift_count}, (_, i) => '<option value="' + (i + 1) + '">Shift ' + (i + 1) + '</option>').join('');
        const sv = load('trk_shift_' + KEY); if (sv) $('shiftSel').value = sv;
        if (tripOpen) $('shiftSel').value = tripOpen.shift;
        $('shiftSel').onchange = () => store('trk_shift_' + KEY, $('shiftSel').value);
        $('shiftRow').style.display = '';
      }
      showTrip();
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
  loadQueue();
  render();
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