# Pragati Seva Sansthan - Step 1 + Step 2

Plain PHP 8.1+ (8.2/8.3 best) + MySQL. No framework, no Composer needed yet.

## XAMPP par chalane ke steps
1. Is folder ko `C:\xampp\htdocs\pragatisevasnsthan\` me rakho.
2. `.env.example` ko copy karke `.env` naam do. Andar check karo:
   - `APP_URL=http://localhost/pragatisevasnsthan`
   - `DB_NAME=pragatisnsthadb`  (jo database aapne banaya)
   - `DB_USER=root`, `DB_PASS=` (XAMPP default)
3. Database me `schema.sql` import ho chuka hai (Step 1). Dobara mat chalao.
4. Super Admin banao (sirf ek baar), command prompt me folder ke andar:
   `C:\xampp\php\php.exe cli\create_super_admin.php`
5. Browser: `http://localhost/pragatisevasnsthan/admin/login.php`

## Files (kaun kya karta hai)
- `includes/bootstrap.php`  : har page ke top par sirf yahi ek file include hoti hai
- `includes/config.php`     : .env padhna, timezone, error handling
- `includes/db.php`         : db(), db_query(), db_value(), db_rows()
- `includes/helpers.php`    : e(), url(), redirect(), flash(), inr(), setting()
- `includes/csrf.php`       : csrf_field(), csrf_check()
- `includes/audit.php`      : audit('action', 'entity', id)
- `includes/auth.php`       : require_login(), require_perm('x.y'), can('x.y')
- `includes/admin_header.php / admin_footer.php` : admin layout + sidebar menu
- `admin/login.php, logout.php, index.php`
- `assets/css/theme.css`    : colours/fonts (public site bhi yahi use karega)

## Naya admin page banane ka pattern
```php
require_once __DIR__ . '/../includes/bootstrap.php';
require_perm('news.manage');
$page_title = 'News'; $active = 'news.php';
require ROOT_PATH . '/includes/admin_header.php';
// ... page content ...
require ROOT_PATH . '/includes/admin_footer.php';
```
Sidebar me item "soon" se active karne ke liye `admin_header.php` me us row ka `false` ko `true` karo.

## Live server par jate waqt
- `.env` me `APP_DEBUG=0`, `APP_URL=https://pragatisevasnsthan.com`
- HTTPS zaruri. Cloudflare ke peeche ho to `TRUST_PROXY=1`
- `.htaccess` files (root, includes, cli, migrations, storage, uploads) delete mat karna

## Step 3 (News module + upload system)
New files:     admin/news.php, includes/forms.php, includes/upload.php
Changed files: includes/admin_header.php (News menu ON), assets/css/admin.css (list/form styles)

Naya module banana ho (Offers, Notices, Gallery ...): admin/news.php ko copy karo, upar wale 5 points badlo.
Uploads: `uploads/<module>/YYYY/MM/<random>.webp`. GD extension ON honi chahiye (XAMPP me php.ini: extension=gd).

## Step 4 - Public website (Hindi / English)
New files: `index.php` (Home), `campaigns.php`, `news.php`, `news_view.php`, `page.php`, `donate.php`,
`includes/lang.php`, `public_header.php`, `public_footer.php`, `campaign_card.php`, `news_card.php`, `assets/css/site.css`.
Language switch: `?lang=hi|en` (cookie). Only `published` news (publish date reached) and pages are public.
`donate.php` shows presets + bank/UPI details for now; Razorpay online payment comes in the next step.

## Step 5 - Admin: Pages, Home slider, Campaigns
New: `includes/crud.php` (one engine for list/add/edit/delete + image upload + audit) and
`admin/pages.php`, `admin/sliders.php`, `admin/campaigns.php` (each ~30 lines: only describes its fields).
`admin_header.php` menu: these 3 are now ON. New simple modules (notices, trustees, offers...) = copy a module file, change table/perm/fields.
To make the public Contact link work: Admin > Pages > Add, slug `contact`, status Published (About / Privacy / Refund / Terms already exist as drafts).

## Step 6 - Online donation (Razorpay), receipts, e-mail, Settings panel
**Credentials are entered by Super Admin in Admin > Settings** (Razorpay Key ID / Key Secret / Webhook Secret, SMTP). Secrets are
AES-256-GCM encrypted in the DB, never shown again (blank field = keep saved). Key file: `storage/app.key` (back it up with the DB!).

Setup order:
1. Admin > Settings > Trust profile: name, address, 80G no., receipt prefix, signatory.
2. Admin > Settings > Razorpay: paste Key ID + Secret (start with `rzp_test_`), Save, press "Test Razorpay connection".
3. Razorpay Dashboard > Webhooks: URL `https://YOURSITE/razorpay_webhook.php`, make a secret, tick payment.captured, payment.failed, order.paid, refund.processed; paste the same secret in Settings.
4. Admin > Settings > Email: SMTP host/port/user/password (Gmail: App Password), Save, send test email.
5. Live server cron (retries failed e-mails): `* * * * * php /path/cli/queue_worker.php`
6. Switching to live: replace test keys with `rzp_live_` keys + live webhook secret.

Flow: donate.php -> pay.php (Razorpay Checkout) -> payment_verify.php (signature check) -> thank_you.php; razorpay_webhook.php confirms
independently (idempotent). Receipt no. = PREFIX/FY/0001 (gapless per financial year), printable at receipt.php?c=CODE (Print -> Save as PDF).
Admin > Donations: filters, CSV export, offline (cash/cheque/bank) entry with receipt, resend receipt.
Not in this step: monthly recurring donations, donor OTP login, refunds from admin, SMS.

## Step 7 - Team, Transparency, Notices, Videos, Contact, Volunteers
Admin (all via `includes/crud.php`, new `includes/inbox.php` for incoming forms): `trustees.php`, `financial_docs.php` (PDF), `legal_docs.php`,
`notices.php` (PDF), `video.php` (YouTube ID), `enquiries.php`, `volunteers.php`. crud now supports a PDF/document upload column (`'upload' => [...]`).
Public: `team.php`, `transparency.php` (legal registrations + financial reports by year), `notices.php`, `videos.php`, `contact.php` (-> enquiries),
`volunteer.php` (-> volunteers). Header menu now has Transparency + Contact; footer lists Team/Notices/Videos/Volunteer and legal numbers marked "show in footer".
Still to build: Gallery, Audio, Trust Offers (+ applications), Admin users, Activity log pages, monthly donations, donor login.
