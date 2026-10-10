<?php
// admin/bus_wizard.php — Bus Setup Wizard (included by bus_tracker.php, before the footer).
// Steps: 1 Bus → 2 Tracking method → 3 Live test → 4 Route/driver/shifts → 5 Health check.
// Re-uses helpers of bus_tracker.php: api(), esc(), showToast(), absUrl(), pairUrl(), phoneGpsUrl(),
// copyTextWithFallback(), loadFleet(), loadRoutes(), switchTab(), _buses, BASE_URL constants.
// Needs: bus_actions.php (save_bus, save_assignment, get_unassigned_routes, get_drivers, wizard_check, wizard_status).
?>
<style>
.wz-steps { display:flex; gap:6px; padding:14px 20px 0; }
.wz-step { flex:1; text-align:center; font-size:.68rem; color:#94a3b8; font-weight:600; }
.wz-step i { display:flex; width:26px; height:26px; margin:0 auto 4px; border-radius:50%; background:#e2e8f0; color:#64748b; align-items:center; justify-content:center; font-style:normal; font-size:.78rem; }
.wz-step.on { color:#2563eb; } .wz-step.on i { background:#2563eb; color:#fff; }
.wz-step.done { color:#16a34a; } .wz-step.done i { background:#16a34a; color:#fff; }
.wz-pane { display:none; } .wz-pane.on { display:block; }
.wz-opt { display:block; border:2px solid #e2e8f0; border-radius:12px; padding:12px 14px; margin-bottom:10px; cursor:pointer; }
.wz-opt:hover { border-color:#bfdbfe; } .wz-opt.sel { border-color:#2563eb; background:#eff6ff; }
.wz-opt b { font-size:.9rem; color:#1e293b; } .wz-opt small { display:block; color:#64748b; font-size:.76rem; margin-top:3px; line-height:1.5; }
.wz-badge { display:inline-block; font-size:.65rem; padding:2px 7px; border-radius:20px; margin-left:6px; font-weight:700; vertical-align:middle; }
.wz-badge.g { background:#dcfce7; color:#166534; } .wz-badge.b { background:#dbeafe; color:#1e40af; } .wz-badge.y { background:#fef9c3; color:#854d0e; }
.wz-test { text-align:center; padding:18px 10px; border-radius:14px; background:#f1f5f9; color:#475569; }
.wz-test.ok { background:#dcfce7; color:#166534; } .wz-test.warn { background:#fef9c3; color:#854d0e; }
.wz-test .big { font-size:2.4rem; line-height:1; }
.wz-bar { height:8px; border-radius:6px; background:#e2e8f0; overflow:hidden; margin:10px auto 0; max-width:260px; }
.wz-bar > div { height:100%; width:0; background:#16a34a; transition:width .4s; }
.wz-chk { display:flex; gap:10px; padding:9px 0; border-bottom:1px solid #f1f5f9; font-size:.84rem; }
.wz-chk:last-child { border-bottom:0; } .wz-chk .ic { flex-shrink:0; width:22px; text-align:center; }
.wz-chk small { display:block; color:#64748b; font-size:.76rem; margin-top:2px; }
.wz-score { text-align:center; margin-bottom:12px; } .wz-score b { font-size:2rem; }
.wz-days { display:flex; flex-wrap:wrap; gap:6px; } .wz-days label { font-weight:500; border:1.5px solid #e2e8f0; border-radius:20px; padding:4px 11px; font-size:.78rem; cursor:pointer; margin:0; }
.wz-days input { display:none; } .wz-days input:checked + span { color:#2563eb; font-weight:700; }
.wz-days label:has(input:checked) { border-color:#2563eb; background:#eff6ff; }
.wz-trouble { text-align:left; font-size:.78rem; color:#854d0e; margin-top:10px; line-height:1.6; }
#wzMini { height:150px; border-radius:10px; margin-top:12px; display:none; }
</style>

<div class="modal-ov" id="wzModal">
  <div class="modal-box" style="max-width:600px;" onclick="event.stopPropagation()">
    <div class="modal-head">
      <div class="modal-title"><i class="bi bi-magic"></i> Bus Setup Wizard</div>
      <button class="m-close" onclick="wzClose()">&times;</button>
    </div>
    <div class="wz-steps" id="wzSteps"></div>
    <div class="modal-body">
      <div id="wzMsg" style="display:none;padding:10px 14px;border-radius:8px;font-size:.83rem;margin-bottom:14px;"></div>

      <!-- 1 BUS -->
      <div class="wz-pane" id="wz1">
        <div class="fld"><label>Kaunsi bus?</label>
          <select id="wzExisting" onchange="wzPickExisting()"><option value="">➕ Nayi bus banayein</option></select>
        </div>
        <div id="wzNewFields">
          <div class="fld-row">
            <div class="fld"><label>Bus ka naam *</label><input id="wzName" placeholder="jaise Bus 1 / Ganga Express"></div>
            <div class="fld"><label>Bus number *</label><input id="wzNumber" placeholder="UP32 AB 1234" style="text-transform:uppercase"></div>
          </div>
          <div class="fld"><label>Seats (capacity)</label><input type="number" id="wzCap" value="40" min="1"></div>
        </div>
        <div class="info-box">Bus save hote hi iska apna <strong>private GPS key</strong> ban jata hai. Agle step mein aap batayenge ki GPS kaise aayega.</div>
      </div>

      <!-- 2 METHOD -->
      <div class="wz-pane" id="wz2">
        <label class="wz-opt" data-m="browser" onclick="wzMethod('browser')"><b>📱 Driver ke phone ka link</b><span class="wz-badge g">sabse aasan</span>
          <small>Koi app nahi. Link kholo → Start dabao. Screen ON aur page khula rehna chahiye (lock karte hi ruk jata hai).</small></label>
        <label class="wz-opt" data-m="logger" onclick="wzMethod('logger')"><b>🛰️ GPSLogger app (Android)</b><span class="wz-badge b">screen lock pe bhi</span>
          <small>Free app background mein chalta hai. Battery settings ek baar theek karni padti hain.</small></label>
        <label class="wz-opt" data-m="device" onclick="wzMethod('device')"><b>📟 Hardware GPS device</b><span class="wz-badge y">sabse bharosemand</span>
          <small>Gaadi ki battery se juda 4G tracker. Device ki setting mein URL daalna hota hai.</small></label>
        <div id="wzHow" style="margin-top:14px;"></div>
      </div>

      <!-- 3 TEST -->
      <div class="wz-pane" id="wz3">
        <div class="wz-test" id="wzTest"><div class="big">⏳</div><div id="wzTestTxt" style="margin-top:8px;font-weight:600;">Start ka intezaar…</div>
          <div class="wz-bar"><div id="wzBar"></div></div>
          <div id="wzTestSub" style="font-size:.78rem;margin-top:8px;"></div>
        </div>
        <div id="wzMini"></div>
        <div class="wz-trouble" id="wzTrouble" style="display:none;">
          <strong>1 minute se location nahi aayi — ye check karein:</strong><br>
          • Link <strong>https://</strong> se khula hai? (http par GPS nahi chalta)<br>
          • Browser mein Location → <strong>Allow</strong>, aur phone ka GPS ON?<br>
          • Driver ne <strong>Trip Shuru Karein</strong> dabaya? Screen ON hai?<br>
          • Sahi bus ka link/key use kiya? (har bus ka alag hota hai)
        </div>
      </div>

      <!-- 4 ROUTE -->
      <div class="wz-pane" id="wz4">
        <div class="fld"><label>Route</label><select id="wzRoute"><option value="">— baad mein —</option></select></div>
        <div class="fld"><label>Driver</label><select id="wzDriver"><option value="">— koi nahi —</option></select></div>
        <div class="fld"><label>Chalne ke din</label>
          <div class="wz-days" id="wzDays"></div></div>
        <div class="fld"><label>Shifts (ek hi bus kitni baar chalti hai?)</label>
          <select id="wzShifts" onchange="wzRenderShifts()"><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option></select></div>
        <div id="wzShiftTimes"></div>
        <div class="info-box" id="wzRouteHint">Route skip kar sakte hain, par bina route ke students ko is bus ka map/alert nahi milega.</div>
      </div>

      <!-- 5 HEALTH -->
      <div class="wz-pane" id="wz5">
        <div class="wz-score"><b id="wzScore">—</b><div style="font-size:.78rem;color:#64748b;">Setup score</div></div>
        <div id="wzChecks"><div style="text-align:center;color:#94a3b8;padding:20px;">Check ho raha hai…</div></div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="edu-btn edu-btn-secondary" id="wzBack" onclick="wzGo(-1)">Peeche</button>
      <button class="edu-btn edu-btn-secondary" id="wzSkip" onclick="wzGo(1,true)">Abhi skip</button>
      <button class="edu-btn edu-btn-primary" id="wzNext" onclick="wzNext()">Aage</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js" crossorigin="anonymous"></script>
<script>
// ═══ Bus Setup Wizard ════════════════════════════════════════════════════════
const WZ_TITLES = ['Bus', 'Tracking', 'Test', 'Route', 'Check'];
const WZ_DAYS = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
let wz = {step: 1, bus: null, key: '', method: 'browser', timer: null, baseline: null, updates: 0, since: 0, lastAt: null, tested: false, miniMap: null, miniMarker: null, routeSaved: false};

function wzMsg(ok, t) { const m = document.getElementById('wzMsg'); m.style.display = t ? 'block' : 'none'; m.textContent = t || '';
  m.style.background = ok ? '#dcfce7' : '#fee2e2'; m.style.color = ok ? '#166534' : '#991b1b'; }

async function openBusWizard(busId, jumpTo) {
  wz = {step: 1, bus: null, key: '', method: 'browser', timer: null, baseline: null, updates: 0, since: 0, lastAt: null, tested: false, miniMap: wz.miniMap, miniMarker: null, routeSaved: false};
  if (!_buses.length) { const r = await api('get_fleet'); _buses = r.buses || []; }
  const sel = document.getElementById('wzExisting');
  sel.innerHTML = '<option value="">➕ Nayi bus banayein</option>' + _buses.map(b => '<option value="' + b.id + '">' + esc(b.bus_name) + ' (' + esc(b.bus_number) + ')</option>').join('');
  ['wzName','wzNumber'].forEach(i => document.getElementById(i).value = ''); document.getElementById('wzCap').value = 40;
  document.getElementById('wzDays').innerHTML = WZ_DAYS.map(d => '<label><input type="checkbox" value="' + d + '"' + (d !== 'Sun' ? ' checked' : '') + '><span>' + d + '</span></label>').join('');
  wzRenderShifts();
  wzMsg(true, '');
  document.getElementById('wzModal').classList.add('show');
  if (busId) { sel.value = busId; wzPickExisting(); }
  wzShow(busId && jumpTo ? jumpTo : 1);
}
function wzClose() { wzStopTest(); document.getElementById('wzModal').classList.remove('show'); loadFleet(); }

function wzPickExisting() {
  const id = document.getElementById('wzExisting').value;
  document.getElementById('wzNewFields').style.display = id ? 'none' : 'block';
  wz.bus = id ? _buses.find(b => b.id == id) : null;
  wz.key = wz.bus ? (wz.bus.gps_api_key || '') : '';
}

function wzShow(n) {
  wz.step = n;
  document.querySelectorAll('.wz-pane').forEach(p => p.classList.toggle('on', p.id === 'wz' + n));
  document.getElementById('wzSteps').innerHTML = WZ_TITLES.map((t, i) => '<div class="wz-step ' + (i + 1 === n ? 'on' : i + 1 < n ? 'done' : '') + '"><i>' + (i + 1 < n ? '✓' : i + 1) + '</i>' + t + '</div>').join('');
  document.getElementById('wzBack').style.visibility = n > 1 ? 'visible' : 'hidden';
  document.getElementById('wzSkip').style.display = (n === 3 || n === 4) ? '' : 'none';
  document.getElementById('wzNext').textContent = n === 5 ? 'Finish ✔' : n === 2 ? 'Test shuru karein' : 'Aage';
  wzMsg(true, '');
  if (n !== 3) wzStopTest();
  if (n === 2) wzMethod(wz.method);
  if (n === 3) wzStartTest();
  if (n === 4) wzLoadRouteOptions();
  if (n === 5) wzHealth();
}
function wzGo(d, skip) { wzShow(Math.max(1, Math.min(5, wz.step + d))); }

async function wzNext() {
  if (wz.step === 1) {
    if (!wz.bus) {
      const name = document.getElementById('wzName').value.trim(), num = document.getElementById('wzNumber').value.trim().toUpperCase();
      if (!name || !num) { wzMsg(false, 'Bus ka naam aur number daalein.'); return; }
      const r = await api('save_bus', {id: 0, bus_name: name, bus_number: num, capacity: document.getElementById('wzCap').value, status: 'active'});
      if (!r.success) { wzMsg(false, r.message); return; }
      wz.bus = {id: r.id, bus_name: name, bus_number: num}; wz.key = r.gps_api_key;
      showToast('Bus "' + name + '" ban gayi.');
    } else if (!wz.key) { wzMsg(false, 'Is bus ka key sirf admin dekh sakta hai.'); return; }
    wzShow(2);
  } else if (wz.step === 4) {
    if (!await wzSaveRoute()) return;
    wzShow(5);
  } else if (wz.step === 5) { wzClose(); showToast('Setup poora ✔'); }
  else wzShow(wz.step + 1);
}

// ── step 2: method instructions (+ QR / WhatsApp / copy) ──────────────────────
function wzMethod(m) {
  wz.method = m;
  document.querySelectorAll('.wz-opt').forEach(o => o.classList.toggle('sel', o.dataset.m === m));
  const logger = phoneGpsUrl(wz.key), busName = wz.bus ? wz.bus.bus_name : 'bus';
  const copyBtn = (id, txt) => '<div class="key-box" style="color:#86efac;" id="' + id + '">' + esc(txt) + '<button class="key-copy" onclick="copyTextWithFallback(document.getElementById(\'' + id + '\').childNodes[0].textContent,this)">Copy</button></div>';
  let h = '';
  if (m === 'browser') {
    h = '<div style="font-size:.82rem;line-height:1.6;color:#374151;">Driver ka phone ek baar <strong>pair</strong> hota hai: ek baar chalne wala link (24 ghante). Link mein koi key nahi hoti — forward ho jaaye to bhi doosre phone par nahi chalega.</div>'
      + '<button type="button" class="edu-btn edu-btn-primary" style="margin-top:10px;" onclick="wzMakePair()"><i class="bi bi-link-45deg"></i> Pairing link banayein</button>'
      + '<div id="wzPair" style="margin-top:12px;"></div>';
  } else if (m === 'logger') {
    h = '<div style="font-size:.82rem;line-height:1.7;color:#374151;">1. GPSLogger app install karein: <a href="' + <?= json_encode(BASE_URL . '/apps/GPSLogger.apk') ?> + '" download>APK download</a><br>'
      + '2. ≡ Menu → <strong>Log to Custom URL</strong> → ON → neeche wala URL paste karein:</div>' + copyBtn('wzLogger', logger)
      + '<div style="font-size:.82rem;line-height:1.7;color:#374151;margin-top:8px;">3. Logging interval ≈ <strong>30 sec</strong> · 4. <strong>Start Logging</strong> · 5. Phone Settings → Apps → GPSLogger → Battery → <strong>Unrestricted</strong> (warna screen lock par band ho jayega).</div>';
  } else {
    h = '<div style="font-size:.82rem;line-height:1.7;color:#374151;">Device ki settings mein ye <strong>HTTP URL</strong> daalein (GET/POST dono chalte hain):</div>'
      + copyBtn('wzDev', absUrl(GPS_URL) + '?key=' + wz.key + '&lat=%LAT&lng=%LON&speed=%SPD')
      + '<div style="font-size:.78rem;color:#64748b;margin-top:8px;line-height:1.6;">• Device agar header support kare to URL se key hata kar header <code>X-API-Key</code> mein daalein — key logs mein nahi dikhegi.<br>• Speed m/s mein ho to URL ke aakhir mein <code>&amp;su=ms</code> jodein. Update interval 10–30 sec rakhein.</div>';
  }
  document.getElementById('wzHow').innerHTML = h;
}
async function wzMakePair() {
  const r = await api('driver_pair_link', {bus_id: wz.bus.id});
  if (!r.success) { wzMsg(false, r.message); return; }
  const link = pairUrl(r.code), busName = wz.bus.bus_name;
  const wa = 'https://wa.me/?text=' + encodeURIComponent('🚌 ' + busName + ' — driver phone jodne ka link (sirf ek baar, 24 ghante; forward na karein):\n' + link + '\n\nChrome mein kholein → Location Allow → shift tick → "Trip Shuru Karein".');
  document.getElementById('wzPair').innerHTML = '<div class="key-box" style="color:#86efac;" id="wzLink">' + esc(link) + '<button class="key-copy" onclick="copyTextWithFallback(document.getElementById(\'wzLink\').childNodes[0].textContent,this)">Copy</button></div>'
    + '<div style="display:flex;gap:10px;align-items:center;margin-top:12px;flex-wrap:wrap;"><div id="wzQr" style="width:132px;height:132px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:4px;"></div>'
    + '<div style="flex:1;min-width:180px;font-size:.8rem;color:#475569;line-height:1.6;">Driver ke phone ka camera is <strong>QR</strong> par rakhein, ya link WhatsApp karein.<br>'
    + '<a class="edu-btn edu-btn-sm edu-btn-primary" style="margin-top:6px;display:inline-flex;" target="_blank" rel="noopener" href="' + wa + '"><i class="bi bi-whatsapp"></i> WhatsApp par bhejein</a></div></div>';
  try { const q = qrcode(0, 'M'); q.addData(link); q.make(); document.getElementById('wzQr').innerHTML = q.createSvgTag({cellSize: 4, margin: 0, scalable: true}); }
  catch (e) { document.getElementById('wzQr').innerHTML = '<div style="font-size:.7rem;color:#94a3b8;padding:8px;">QR load nahi hua — link copy karein</div>'; }
}

// ── step 3: live test ─────────────────────────────────────────────────────────
// Green only after NEW fixes arrive after the test began (an old, stale location can't fake a pass).
const WZ_NEED = 3;
function wzStopTest() { if (wz.timer) { clearInterval(wz.timer); wz.timer = null; } }
function wzSetTest(cls, icon, txt, sub) {
  const t = document.getElementById('wzTest'); t.className = 'wz-test ' + cls;
  t.querySelector('.big').textContent = icon; document.getElementById('wzTestTxt').textContent = txt; document.getElementById('wzTestSub').textContent = sub || '';
  document.getElementById('wzBar').style.width = Math.min(100, wz.updates / WZ_NEED * 100) + '%';
}
async function wzStartTest() {
  wzStopTest();
  wz.updates = 0; wz.tested = false; wz.since = Date.now();
  document.getElementById('wzTrouble').style.display = 'none';
  const first = await api('wizard_check', {bus_id: wz.bus.id});
  wz.baseline = first.fix ? first.fix.recorded_at : null; wz.lastAt = wz.baseline;
  wzSetTest('', '⏳', 'Driver se "Trip Shuru Karein" / Start dabwayein…', 'Hum nayi location ka intezaar kar rahe hain');
  const tick = async () => {
    if (!document.getElementById('wzModal').classList.contains('show') || wz.step !== 3) return wzStopTest();
    const r = await api('wizard_check', {bus_id: wz.bus.id});
    const f = r.success ? r.fix : null;
    if (f && f.recorded_at !== wz.lastAt) { wz.lastAt = f.recorded_at; wz.updates++; wzMini(f); }
    if (!wz.updates) {
      if (Date.now() - wz.since > 60000) document.getElementById('wzTrouble').style.display = 'block';
      return;
    }
    const accTxt = f.accuracy != null ? 'Accuracy ±' + Math.round(f.accuracy) + ' m' : 'Accuracy pata nahi';
    if (wz.updates >= WZ_NEED) {
      wz.tested = true;
      const poor = f.accuracy != null && f.accuracy > 60;
      wzSetTest(poor ? 'warn' : 'ok', poor ? '🟡' : '✅', poor ? 'Connect ho gaya, par GPS kamzor hai' : 'Perfect — GPS sahi aa raha hai!', accTxt + ' · ' + wz.updates + ' updates mile' + (poor ? ' · phone ko khule mein/shishe ke paas rakhein' : ''));
      document.getElementById('wzTrouble').style.display = 'none';
    } else wzSetTest('warn', '📡', 'Location aa rahi hai… (' + wz.updates + '/' + WZ_NEED + ')', accTxt);
  };
  wz.timer = setInterval(tick, 3000); tick();
}
function wzMini(f) {
  const box = document.getElementById('wzMini'); box.style.display = 'block';
  if (!wz.miniMap) { wz.miniMap = L.map('wzMini', {zoomControl: false, attributionControl: false}); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19}).addTo(wz.miniMap); }
  if (wz.miniMarker) wz.miniMarker.remove();
  wz.miniMarker = L.circleMarker([f.lat, f.lng], {radius: 8, color: '#2563eb', fillOpacity: .9}).addTo(wz.miniMap);
  wz.miniMap.setView([f.lat, f.lng], 16); setTimeout(() => wz.miniMap.invalidateSize(), 60);
}

// ── step 4: route / driver / shifts ───────────────────────────────────────────
async function wzLoadRouteOptions() {
  const [rr, dr] = await Promise.all([api('get_unassigned_routes'), api('get_drivers')]);
  const rs = document.getElementById('wzRoute'), ds = document.getElementById('wzDriver');
  rs.innerHTML = '<option value="">— baad mein —</option>' + (rr.routes || []).map(r => '<option value="' + r.id + '">' + esc(r.route_name) + ' · ' + r.student_count + ' students</option>').join('');
  ds.innerHTML = '<option value="">— koi nahi —</option>' + (dr.drivers || []).map(d => '<option value="' + d.id + '">' + esc(d.name) + (d.phone ? ' · ' + esc(d.phone) : '') + '</option>').join('');
  document.getElementById('wzRouteHint').textContent = (rr.routes || []).length ? 'Sirf wahi routes dikhte hain jinpar abhi koi bus nahi hai.' : 'Koi khali route nahi mila — Fee → Van Setup mein route banayein, ya skip karein.';
}
function wzRenderShifts() {
  const n = +document.getElementById('wzShifts').value, old = {};
  document.querySelectorAll('#wzShiftTimes input').forEach(i => old[i.id] = i.value);
  let h = '';
  for (let i = 1; i <= n; i++) {
    h += '<div class="shift-section" style="margin-bottom:8px;"><div style="font-size:.78rem;font-weight:600;color:#374151;margin-bottom:6px;">Shift ' + i + '</div><div class="fld-row">'
      + '<div class="fld"><label>Pickup (subah, ghar → school)</label><input type="time" id="wzP' + i + '" value="' + (old['wzP' + i] || '') + '"></div>'
      + '<div class="fld"><label>Drop (school → ghar)</label><input type="time" id="wzD' + i + '" value="' + (old['wzD' + i] || '') + '"></div></div></div>';
  }
  document.getElementById('wzShiftTimes').innerHTML = h;
}
async function wzSaveRoute() {
  const route = document.getElementById('wzRoute').value;
  if (!route) { return true; }   // skipped
  const n = +document.getElementById('wzShifts').value;
  const days = [...document.querySelectorAll('#wzDays input:checked')].map(i => i.value).join(',');
  if (!days) { wzMsg(false, 'Kam se kam ek din chunein.'); return false; }
  const data = {id: 0, route_id: route, bus_id: wz.bus.id, driver_id: document.getElementById('wzDriver').value, shift_count: n, days, status: 'active'};
  for (let i = 1; i <= n; i++) {
    const p = document.getElementById('wzP' + i).value, d = document.getElementById('wzD' + i).value;
    if (!p && !d) { wzMsg(false, 'Shift ' + i + ' ka kam se kam ek time bharein (time ke bina alerts kaam nahi karte).'); return false; }
    data[i === 1 ? 'pickup_time' : 'pickup_time' + i] = p; data[i === 1 ? 'drop_time' : 'drop_time' + i] = d;
  }
  const r = await api('save_assignment', data);
  if (!r.success) { wzMsg(false, r.message); return false; }
  wz.routeSaved = true; showToast('Route assign ho gaya.'); return true;
}

// ── step 5: health check ──────────────────────────────────────────────────────
async function wzHealth() {
  document.getElementById('wzChecks').innerHTML = '<div style="text-align:center;color:#94a3b8;padding:20px;">Check ho raha hai…</div>';
  const r = await api('wizard_status', {bus_id: wz.bus.id});
  if (!r.success) { document.getElementById('wzChecks').innerHTML = '<div style="color:#991b1b;padding:14px;">' + esc(r.message) + '</div>'; return; }
  const sc = document.getElementById('wzScore'); sc.textContent = r.score + '%'; sc.style.color = r.score >= 80 ? '#16a34a' : r.score >= 50 ? '#d97706' : '#dc2626';
  const ic = {ok: '✅', warn: '🟡', bad: '❌'};
  const fixes = {method: ['Tracking dobara chunein', 'wzGo(-3)'], test: ['Dobara test karein', 'wzGo(-2)'], route: ['Route/driver set karein', 'wzGo(-1)'], students: ['Students tab kholein', "wzClose();switchTab('students',document.querySelectorAll('.bt-tab')[3])"]};
  document.getElementById('wzChecks').innerHTML = r.checks.map(c => {
    const f = c.level !== 'ok' && fixes[c.fix] ? ' <a href="#" style="font-size:.76rem;" onclick="' + fixes[c.fix][1] + ';return false;">' + fixes[c.fix][0] + ' →</a>' : '';
    return '<div class="wz-chk"><div class="ic">' + ic[c.level] + '</div><div>' + esc(c.title) + (c.hint ? '<small>' + esc(c.hint) + f + '</small>' : (f ? '<small>' + f + '</small>' : '')) + '</div></div>';
  }).join('');
}
</script>
