<?php
// school/attendance_register.php — monthly attendance register (inspection format), print / CSV.
// Pick a class (or Staff) and a month, press Show. Everything is counted on the server.
$pageTitle    = 'Attendance Register';
$pageSubtitle = 'Monthly register for a class or staff: print it or download it';
require_once __DIR__ . '/school_header.php';
$csrf = csrfToken();
?>
<style>
.ar{--card:#fff;--ink:#18233b;--mute:#667085;--line:#e3e8f1;--chip:#eef2f9;--brand:#2563eb;--ok:#059669;--warn:#d97706;--bad:#dc2626;color:var(--ink);}
.ar *{box-sizing:border-box;}
.ar-card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 18px;margin-bottom:16px;}
.ar-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;}
.ar-bar label{display:flex;flex-direction:column;gap:4px;font-size:.78rem;color:var(--mute);}
.ar-in{font-size:.95rem;padding:9px 10px;border-radius:10px;border:1px solid var(--line);background:var(--card);color:var(--ink);min-height:44px;min-width:180px;}
.ar-btn{display:inline-flex;align-items:center;gap:7px;font-size:.9rem;font-weight:600;padding:9px 16px;border-radius:10px;border:1px solid var(--line);background:var(--card);color:var(--ink);cursor:pointer;min-height:44px;}
.ar-btn.primary{background:var(--brand);border-color:var(--brand);color:#fff;}
.ar-btn:disabled{opacity:.45;cursor:not-allowed;}
.ar-msg{color:var(--mute);font-size:.88rem;padding:24px 0;text-align:center;}
.ar-sum{display:flex;flex-wrap:wrap;gap:8px 18px;font-size:.88rem;margin:4px 0 12px;}
.ar-sum b{font-variant-numeric:tabular-nums;}
.ar-legend{font-size:.76rem;color:var(--mute);margin-top:10px;line-height:1.7;}
.ar-wrap{overflow:auto;max-height:70vh;border:1px solid var(--line);border-radius:10px;}
.ar-t{border-collapse:collapse;font-size:.78rem;font-variant-numeric:tabular-nums;min-width:100%;}
.ar-t th,.ar-t td{border:1px solid var(--line);padding:4px 5px;text-align:center;white-space:nowrap;}
.ar-t thead th{position:sticky;top:0;background:var(--chip);z-index:2;font-weight:700;}
.ar-t .nm{text-align:left;position:sticky;left:0;background:var(--card);z-index:1;min-width:150px;}
.ar-t thead .nm{z-index:3;background:var(--chip);}
.ar-t th small{display:block;font-weight:500;color:var(--mute);font-size:.66rem;}
.ar-t td.A{color:var(--bad);font-weight:700;} .ar-t td.Lv{color:#7c3aed;font-weight:600;} .ar-t td.h{color:var(--warn);font-weight:700;}
.ar-t td.off,.ar-t th.off{background:#f1f5f9;color:#94a3b8;}
.ar-t tr.low td.pct{color:var(--bad);font-weight:800;}
.ar-t tfoot td{background:var(--chip);font-weight:700;}
.ar-head,.ar-sign{display:none;}
@media(max-width:767px){.ar-in,.ar-btn{flex:1 1 100%;}}
@media print{
  @page{size:A4 landscape;margin:8mm;}
  body *{visibility:hidden;}
  .ar-print,.ar-print *{visibility:visible;}
  .ar-print{position:absolute;left:0;top:0;width:100%;}
  .ar-wrap{overflow:visible;max-height:none;border:0;}
  .ar-t{font-size:7.5pt;} .ar-t th,.ar-t td{padding:1px 2px;border-color:#999;}
  .ar-t thead th{position:static;} .ar-t .nm{position:static;min-width:0;}
  .ar-head{display:block;text-align:center;margin-bottom:6px;font-size:11pt;}
  .ar-sign{display:flex;justify-content:space-between;margin-top:28px;font-size:9pt;}
  .ar-sign span{border-top:1px solid #333;padding-top:3px;min-width:160px;text-align:center;}
  .ar-noprint{display:none!important;}
}
</style>

<div class="ar">
  <div class="ar-card ar-noprint">
    <div class="ar-bar">
      <label>Class<select class="ar-in" id="arClass"><option value="">Loading…</option></select></label>
      <label>Month<input class="ar-in" type="month" id="arMonth" value="<?= date('Y-m') ?>" max="<?= date('Y-m') ?>"></label>
      <button class="ar-btn primary" type="button" id="arShow"><i class="bi bi-table"></i> Show</button>
      <button class="ar-btn" type="button" id="arPrint" disabled><i class="bi bi-printer"></i> Print</button>
      <button class="ar-btn" type="button" id="arCsv" disabled><i class="bi bi-download"></i> Excel (CSV)</button>
    </div>
  </div>

  <div class="ar-card ar-print">
    <div class="ar-head" id="arHead"></div>
    <div class="ar-sum" id="arSum"></div>
    <div id="arBody"><div class="ar-msg">Choose a class and a month, then press Show.</div></div>
    <div class="ar-legend">P = Present (late also counts as present) · ½ = Half day · A = Absent · Lv = Leave · W = Weekly off · H = Holiday · blank = not marked.
      Percentage = present days ÷ marked days. Red = below 75% (with at least 10 marked days).</div>
    <div class="ar-sign"><span>Class Teacher</span><span>Principal</span></div>
  </div>
</div>

<script>
(() => {
'use strict';
const API = '<?= BASE_URL ?>/api/attendance_reports.php';
const csrfToken = <?= json_encode($csrf) ?>;
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let last = null;

async function call(action, obj = {}) {
  const fd = new FormData(); fd.append('csrf_token', csrfToken); fd.append('action', action);
  Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
  try {
    const r = await fetch(API, {method:'POST', body:fd, credentials:'same-origin'});
    return await r.json();
  } catch (e) { return {success:false, message:'No connection. Please try again.'}; }
}

async function loadClasses() {
  const d = await call('classes');
  const sel = $('arClass');
  if (!d.success) { sel.innerHTML = `<option value="">${esc(d.message)}</option>`; return; }
  sel.innerHTML = (d.classes || []).map(c => `<option value="${esc(c.value)}">${esc(c.label)} (${c.count})</option>`).join('')
    + (d.staff ? '<option value="staff">Staff</option>' : '');
  try { const v = localStorage.getItem('ar_class'); if (v && [...sel.options].some(o => o.value === v)) sel.value = v; } catch (e) {}
}

const fmt = n => Number.isInteger(n) ? String(n) : n.toFixed(1);
function render(d) {
  const days = d.days;
  const head = `<tr><th class="nm">Name</th>${days.map(x => `<th class="${x.off ? 'off' : ''}">${x.d}<small>${esc(x.dow.slice(0, 2))}</small></th>`).join('')}
    <th>Present</th><th>Absent</th><th>Leave</th><th>%</th></tr>`;
  const body = d.rows.map(r => `<tr class="${r.low ? 'low' : ''}"><td class="nm">${esc(r.name)}${r.code ? ` <small style="color:var(--mute)">${esc(r.code)}</small>` : ''}</td>
    ${r.cells.map((c, i) => `<td class="${days[i].off && (c === 'W' || c === 'H') ? 'off' : c === '½' ? 'h' : esc(c)}">${esc(c)}</td>`).join('')}
    <td>${fmt(r.present)}</td><td>${r.absent}</td><td>${r.leave}</td><td class="pct">${r.pct === null ? '–' : r.pct + '%'}</td></tr>`).join('');
  const foot = `<tr><td class="nm">Present that day</td>${d.day_present.map((n, i) => `<td class="${days[i].off ? 'off' : ''}">${days[i].off || days[i].future ? '' : n}</td>`).join('')}<td colspan="4"></td></tr>`;
  $('arBody').innerHTML = d.rows.length
    ? `<div class="ar-wrap"><table class="ar-t"><thead>${head}</thead><tbody>${body}</tbody><tfoot>${foot}</tfoot></table></div>`
    : '<div class="ar-msg">No active students in this class.</div>';
  const withPct = d.rows.filter(r => r.pct !== null);
  const avg = withPct.length ? Math.round(withPct.reduce((a, r) => a + r.pct, 0) / withPct.length) : null;
  const low = d.rows.filter(r => r.low).length;
  $('arSum').innerHTML = `<span><b>${esc(d.title)}</b> · ${esc(d.month_label)}</span><span>Working days: <b>${d.working}</b></span>
    <span>${d.title === 'Staff' ? 'Staff' : 'Students'}: <b>${d.rows.length}</b></span>${avg !== null ? `<span>Average: <b>${avg}%</b></span>` : ''}
    ${low ? `<span style="color:var(--bad)">Below 75%: <b>${low}</b></span>` : ''}`;
  $('arHead').innerHTML = `<b>${esc(d.school)}</b><br>Attendance Register: ${esc(d.title)}, ${esc(d.month_label)}`;
  $('arPrint').disabled = $('arCsv').disabled = !d.rows.length;
}

$('arShow').onclick = async () => {
  const cls = $('arClass').value, month = $('arMonth').value;
  if (!cls || !month) return;
  try { localStorage.setItem('ar_class', cls); } catch (e) {}
  $('arBody').innerHTML = '<div class="ar-msg">Loading…</div>'; $('arSum').innerHTML = '';
  const d = await call('register', {class:cls, month});
  if (!d.success) { $('arBody').innerHTML = `<div class="ar-msg">${esc(d.message)}</div>`; last = null; $('arPrint').disabled = $('arCsv').disabled = true; return; }
  last = d; render(d);
};
$('arPrint').onclick = () => window.print();
$('arCsv').onclick = () => {
  if (!last) return;
  const cell = v => { v = String(v ?? ''); if (/^[=+\-@\t\r]/.test(v)) v = "'" + v; return '"' + v.replace(/"/g, '""') + '"'; };
  const rows = [[last.school], [`Attendance Register: ${last.title}, ${last.month_label}`], [],
    ['Name', 'Code', ...last.days.map(x => x.d), 'Present', 'Absent', 'Leave', '%']];
  last.rows.forEach(r => rows.push([r.name, r.code, ...r.cells, fmt(r.present), r.absent, r.leave, r.pct === null ? '' : r.pct]));
  const blob = new Blob(['﻿' + rows.map(r => r.map(cell).join(',')).join('\n')], {type:'text/csv;charset=utf-8'});
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob);
  a.download = `register-${last.title.replace(/\W+/g, '_')}-${$('arMonth').value}.csv`;
  document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
};
loadClasses();
})();
</script>
<?php require_once __DIR__ . '/school_footer.php'; ?>
