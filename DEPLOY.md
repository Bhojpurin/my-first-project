# School Bus GPS — Deploy guide

Ye module aapke SSZone panel ke andar chalta hai. Panel ki core files (`config/`, `includes/session.php`, `includes/functions.php`,
`includes/push_sender.php`, `includes/school_message_helper.php`, `student/stu_guard.php`, `student/sw.js`) **is package mein nahi
hain** — wo aapke server par pehle se hain aur waise hi rahengi.

## 0. Backup (zaroori)
- Database ka poora backup (phpMyAdmin → Export).
- Neeche ki list wali jo files server par pehle se hain, unki copy.

## 1. Files upload karein (same folders mein)

| Folder | File | Nayi / Badli |
|---|---|---|
| `api/` | `bus_actions.php` | badli |
| `api/` | `bus_location.php` | badli |
| `api/` | `bus_trip.php` | **nayi** |
| `api/` | `driver_tracker.php` | badli (pura naya page) |
| `api/` | `driver_sw.js` | **nayi** (driver page ka offline cache) |
| `api/` | `gps_update.php` | badli |
| `api/` | `student_bus.php` | badli |
| `includes/` | `bus_alerts.php`, `bus_cache.php`, `bus_db.php`, `bus_schema.php`, `bus_halt.php`, `bus_message_hook.php`, `bus_notify.php`, `bus_privacy.php`, `bus_security.php`, `bus_trips.php`, `bus_watchdog.php` | **nayi** |
| `includes/` | `bus_proximity.php` | badli |
| `school/` * | `bus_tracker.php` | badli |
| `school/` * | `bus_wizard.php` | **nayi** (bus_tracker.php ke bagal mein) |
| `student/` | `index.php` | badli ** |
| `tools/` | `migrate.php`, `bus_selfcheck.php`, `bus_watchdog_cron.php`, `bus_privacy_cleanup.php`, `gps_simulator.php`, `.htaccess` | **nayi** |
| `database/` | `bus_tracking.sql`, `.htaccess` | **nayi** |

\* Jis folder mein aapki purani `bus_tracker.php` hai (URL `…/school/bus-tracker`), dono files wahi rakhein — `bus_tracker.php`
`school_header.php` / `school_footer.php` ko apne hi folder se leti hai.
\*\* `student/index.php` aapki bheji hui copy par bani hai. Agar us din ke baad aapne is file mein kuch aur badla hai, to seedha
overwrite na karein — mujhe nayi file bhejein, main badlaav jod dunga.

`tests/` folder **upload na karein** (sirf development ke liye).

## 2. Database update — apne aap
Kuch chalane ki zaroorat **nahi**. Files upload karke panel mein **Bus Tracker** kholein — pehli request par module apni
saari tables / columns khud bana leta hai (ek hi baar, kai log ek saath kholein to bhi). Agar fir bhi
"Database update nahi ho paaya" aaye, to DB user ko CREATE/ALTER permission chahiye; server ke PHP error log mein `bus_schema`
wali lines dekhein.

`tools/` folder **browser se nahi khulta (403 Forbidden — ye suraksha ke liye sahi hai)**. Hath se chalana ho to command line se:
- XAMPP: Control Panel → **Shell** button → `cd htdocs\sszone` → `php tools\migrate.php`
- Linux / cPanel Terminal: `cd public_html/sszone && php tools/migrate.php`

## 3. Self-check (optional, command line se)
```
php tools/bus_selfcheck.php
```
Sab ✔ hona chahiye. `!` (warning) ka matlab: chalega, par dekh lein (jaise BASE_URL https nahi).

## 4. Cron (har minute)
- Linux / cPanel → Cron Jobs: `* * * * * php /home/USER/public_html/sszone/tools/bus_watchdog_cron.php >/dev/null 2>&1`
- Windows (XAMPP) → Task Scheduler: har 1 minute `C:\xampp\php\php.exe C:\xampp\htdocs\sszone\tools\bus_watchdog_cron.php`

Ye ek hi cron sab karta hai: chup bus ka alert, bhooli hui trip band karna, aur har 15 min privacy safai.

## 5. Zaroori settings
- **HTTPS** zaroori hai — bina https ke phone GPS nahi deta.
- Optional, `config/constants.php` mein:
  - `define('BUS_ROUTER_URL', 'https://apna-osrm-server');` — zyada schools par apna routing server (default: public OSRM; `''` = band).
  - `define('BUS_DB_TIMEZONE', '+05:30');` — default IST hi hai; sirf India ke bahar ke school ke liye badlein.

## 6. Pehle din (har school ka admin)
1. **Live Map → Alert settings**: speed limit, school gate (map ko school par zoom karke "📍 Live Map ke beech wali jagah"), halt ke minute.
2. **Fleet → Driver Links**: har bus ke liye "Driver phone jodne ka link" → QR / WhatsApp se sirf us bus ke driver ko.
   Purane `?key=` links ab kaam nahi karte (suraksha).
3. Parents ko kahein: student portal → Bus tab → ghar ki location + "Background alerts" ON (+ optional landmark note).
4. Har bus par **Fleet → Check** (health score) dekhein.

## 7. Deploy ke baad jaanch (5 minute)
- [ ] Driver phone: link khula → "jud gaya" → shift tick → Trip Shuru → admin Live Map par bus + "✔ / ⏳ baaki" dikhe.
- [ ] Ek stop par ✔ → student portal mein "Driver marked your stop as done".
- [ ] Parent: Bus tab → "Bus nahi chahiye" (aaj) → driver page par wo stop ✖ "parent ne bataya".
- [ ] Driver: "⚠️ Problem" → kaaran bhejein → admin feed mein 🛑 + "📣 Parents ko batayein" → parent ke Messages mein "Auto: Bus suchna".
- [ ] Trip Khatam → Trips tab mein trip, km, ✔/✖.

## Bade paimaane par (500 schools / 20 lakh students)
`SCALING.md` dekhein — maapa hua load test, server plan aur production settings (Redis zaroori jab ek se zyada web server).

## Wapas purane version par (rollback)
Backup wali files wapas daalein. Nayi tables purane code ko nuksan nahi karti, unhe rehne dein.
