-- 001_core.sql : users, roles, permissions, settings, audit log, queue
SET NAMES utf8mb4;

CREATE TABLE roles (
  id        TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug      VARCHAR(30) NOT NULL UNIQUE,          -- super_admin | admin
  name      VARCHAR(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id        SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code      VARCHAR(60)  NOT NULL UNIQUE,         -- e.g. news.manage  (used in can('news.manage'))
  module    VARCHAR(40)  NOT NULL,
  label     VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id        TINYINT UNSIGNED  NOT NULL,
  permission_id  SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id               TINYINT UNSIGNED NOT NULL,
  name                  VARCHAR(100) NOT NULL,
  email                 VARCHAR(150) NOT NULL UNIQUE,
  mobile                VARCHAR(15)  NULL,
  password_hash         VARCHAR(255) NOT NULL,     -- password_hash(..., PASSWORD_ARGON2ID)
  totp_secret           VARCHAR(255) NULL,         -- encrypted
  totp_enabled          TINYINT(1)   NOT NULL DEFAULT 0,
  status                ENUM('active','disabled') NOT NULL DEFAULT 'active',
  must_change_password  TINYINT(1)   NOT NULL DEFAULT 0,
  failed_attempts       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until          DATETIME NULL,
  last_login_at         DATETIME NULL,
  last_login_ip         VARCHAR(45) NULL,
  created_by            INT UNSIGNED NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id)    REFERENCES roles(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-user override: granted=1 extra permission, granted=0 removed permission
CREATE TABLE user_permissions (
  user_id        INT UNSIGNED      NOT NULL,
  permission_id  SMALLINT UNSIGNED NOT NULL,
  granted        TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, permission_id),
  FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(150) NOT NULL,
  ip          VARCHAR(45)  NOT NULL,
  success     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip, created_at),
  KEY idx_email_time (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only: application code only INSERTs here, never UPDATE/DELETE
CREATE TABLE audit_log (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(60)  NOT NULL,              -- login, create, update, delete, export, refund ...
  entity      VARCHAR(60)  NULL,                  -- table / module name
  entity_id   VARCHAR(40)  NULL,
  old_data    JSON NULL,
  new_data    JSON NULL,
  ip          VARCHAR(45)  NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  KEY idx_entity (entity, entity_id),
  KEY idx_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  skey        VARCHAR(80) PRIMARY KEY,
  svalue      TEXT NULL,
  updated_by  INT UNSIGNED NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DB queue: email / SMS / PDF jobs. Worker = cron/queue_worker.php (every minute)
CREATE TABLE jobs (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_type      VARCHAR(60) NOT NULL,             -- send_receipt_email, send_sms ...
  payload       JSON NOT NULL,
  status        ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
  attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 5,
  available_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reserved_at   DATETIME NULL,
  finished_at   DATETIME NULL,
  last_error    TEXT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pick (status, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- 002_cms.sql : public website content (bilingual: _en / _hi)
SET NAMES utf8mb4;

CREATE TABLE pages (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(120) NOT NULL UNIQUE,
  title_en     VARCHAR(200) NOT NULL,
  title_hi     VARCHAR(200) NULL,
  content_en   MEDIUMTEXT NULL,
  content_hi   MEDIUMTEXT NULL,
  meta_title   VARCHAR(160) NULL,
  meta_desc    VARCHAR(300) NULL,
  status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_by   INT UNSIGNED NULL,
  updated_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sliders (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title_en     VARCHAR(200) NOT NULL,
  title_hi     VARCHAR(200) NULL,
  subtitle_en  VARCHAR(300) NULL,
  subtitle_hi  VARCHAR(300) NULL,
  image        VARCHAR(255) NOT NULL,
  link_url     VARCHAR(255) NULL,
  button_text  VARCHAR(60)  NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trustees (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(120) NOT NULL,
  designation_en  VARCHAR(120) NULL,
  designation_hi  VARCHAR(120) NULL,
  photo           VARCHAR(255) NULL,
  bio_en          TEXT NULL,
  bio_hi          TEXT NULL,
  sort_order      INT NOT NULL DEFAULT 0,
  status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Current Affairs / News / Events
CREATE TABLE news_categories (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug     VARCHAR(80) NOT NULL UNIQUE,
  name_en  VARCHAR(100) NOT NULL,
  name_hi  VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE news (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id  INT UNSIGNED NULL,
  slug         VARCHAR(200) NOT NULL UNIQUE,
  title_en     VARCHAR(255) NOT NULL,
  title_hi     VARCHAR(255) NULL,
  summary_en   VARCHAR(500) NULL,
  summary_hi   VARCHAR(500) NULL,
  content_en   MEDIUMTEXT NULL,
  content_hi   MEDIUMTEXT NULL,
  image        VARCHAR(255) NULL,
  publish_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- future date = scheduled publish
  status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  is_featured  TINYINT(1) NOT NULL DEFAULT 0,
  views        INT UNSIGNED NOT NULL DEFAULT 0,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_list (status, publish_at),
  FOREIGN KEY (category_id) REFERENCES news_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Trust ka Offer": schemes, camps, scholarships, training
CREATE TABLE offers (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(160) NOT NULL UNIQUE,
  title_en       VARCHAR(200) NOT NULL,
  title_hi       VARCHAR(200) NULL,
  summary_en     VARCHAR(500) NULL,
  summary_hi     VARCHAR(500) NULL,
  description_en MEDIUMTEXT NULL,
  description_hi MEDIUMTEXT NULL,
  eligibility    TEXT NULL,
  image          VARCHAR(255) NULL,
  last_date      DATE NULL,
  apply_enabled  TINYINT(1) NOT NULL DEFAULT 0,
  sort_order     INT NOT NULL DEFAULT 0,
  status         ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE offer_applications (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  offer_id       INT UNSIGNED NOT NULL,
  applicant_name VARCHAR(120) NOT NULL,
  mobile         VARCHAR(15)  NOT NULL,
  email          VARCHAR(150) NULL,
  address        TEXT NULL,
  details        JSON NULL,                       -- extra form fields
  document_path  VARCHAR(255) NULL,
  status         ENUM('applied','verified','approved','rejected','disbursed') NOT NULL DEFAULT 'applied',
  admin_note     TEXT NULL,
  reviewed_by    INT UNSIGNED NULL,
  ip             VARCHAR(45) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audio_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title_en     VARCHAR(200) NOT NULL,
  title_hi     VARCHAR(200) NULL,
  description  TEXT NULL,
  category     VARCHAR(80) NULL,
  file_path    VARCHAR(255) NOT NULL,
  duration_sec INT UNSIGNED NULL,
  transcript   MEDIUMTEXT NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  status       ENUM('published','hidden') NOT NULL DEFAULT 'published',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE video_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title_en     VARCHAR(200) NOT NULL,
  title_hi     VARCHAR(200) NULL,
  youtube_id   VARCHAR(20) NOT NULL,
  category     VARCHAR(80) NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  status       ENUM('published','hidden') NOT NULL DEFAULT 'published',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gallery_albums (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(160) NOT NULL UNIQUE,
  title_en    VARCHAR(200) NOT NULL,
  title_hi    VARCHAR(200) NULL,
  cover_image VARCHAR(255) NULL,
  event_date  DATE NULL,
  status      ENUM('published','hidden') NOT NULL DEFAULT 'published',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gallery_images (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  album_id   INT UNSIGNED NOT NULL,
  file_path  VARCHAR(255) NOT NULL,
  caption    VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notices (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title_en    VARCHAR(200) NOT NULL,
  title_hi    VARCHAR(200) NULL,
  file_path   VARCHAR(255) NOT NULL,
  notice_date DATE NOT NULL,
  status      ENUM('published','hidden') NOT NULL DEFAULT 'published',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enquiries (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(150) NULL,
  mobile     VARCHAR(15)  NULL,
  subject    VARCHAR(200) NULL,
  message    TEXT NOT NULL,
  ip         VARCHAR(45) NULL,
  status     ENUM('new','replied','closed') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE volunteers (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(120) NOT NULL,
  mobile       VARCHAR(15)  NOT NULL,
  email        VARCHAR(150) NULL,
  city         VARCHAR(100) NULL,
  skills       VARCHAR(255) NULL,
  availability VARCHAR(120) NULL,
  message      TEXT NULL,
  status       ENUM('new','contacted','active','inactive') NOT NULL DEFAULT 'new',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- 003_transparency.sql : financial + legal documents
SET NAMES utf8mb4;

CREATE TABLE financial_docs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title_en   VARCHAR(200) NOT NULL,
  title_hi   VARCHAR(200) NULL,
  doc_type   ENUM('audit_report','balance_sheet','income_expenditure','itr','form_10b',
                  'utilization_certificate','annual_report','other') NOT NULL DEFAULT 'other',
  fy         VARCHAR(9) NOT NULL,                 -- 2025-26
  file_path  VARCHAR(255) NOT NULL,
  status     ENUM('published','hidden') NOT NULL DEFAULT 'published',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fy (fy, doc_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legal_docs (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_type       ENUM('registration','12a','80g','pan','darpan','csr1','fcra','other') NOT NULL DEFAULT 'other',
  title_en       VARCHAR(200) NOT NULL,
  title_hi       VARCHAR(200) NULL,
  doc_no         VARCHAR(80) NULL,                -- registration / 80G number
  valid_from     DATE NULL,
  valid_to       DATE NULL,
  file_path      VARCHAR(255) NULL,
  show_in_footer TINYINT(1) NOT NULL DEFAULT 0,
  sort_order     INT NOT NULL DEFAULT 0,
  status         ENUM('published','hidden') NOT NULL DEFAULT 'published',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- 004_donations.sql : donors, campaigns, donations, receipts, payment events, refunds
SET NAMES utf8mb4;

CREATE TABLE donors (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  mobile     VARCHAR(15)  NOT NULL,
  email      VARCHAR(150) NULL,
  pan_enc    VARBINARY(255) NULL,                 -- AES-256 encrypted PAN (never plain text)
  pan_last4  CHAR(4) NULL,                        -- for display: XXXXXX1234
  address    VARCHAR(255) NULL,
  city       VARCHAR(100) NULL,
  state      VARCHAR(100) NULL,
  pincode    VARCHAR(10)  NULL,
  country    VARCHAR(60) NOT NULL DEFAULT 'India',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_mobile (mobile),
  KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campaigns (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(160) NOT NULL UNIQUE,
  title_en       VARCHAR(200) NOT NULL,
  title_hi       VARCHAR(200) NULL,
  summary_en     VARCHAR(500) NULL,
  summary_hi     VARCHAR(500) NULL,
  description_en MEDIUMTEXT NULL,
  description_hi MEDIUMTEXT NULL,
  image          VARCHAR(255) NULL,
  target_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
  raised_cache   DECIMAL(12,2) NOT NULL DEFAULT 0, -- recomputed from donations; display only
  start_date     DATE NULL,
  end_date       DATE NULL,
  status         ENUM('draft','active','completed','closed') NOT NULL DEFAULT 'draft',
  is_featured    TINYINT(1) NOT NULL DEFAULT 0,
  sort_order     INT NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE donations (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donor_id             INT UNSIGNED NOT NULL,
  campaign_id          INT UNSIGNED NULL,           -- NULL = general fund
  amount               DECIMAL(12,2) NOT NULL,
  currency             CHAR(3) NOT NULL DEFAULT 'INR',
  donation_type        ENUM('once','monthly') NOT NULL DEFAULT 'once',
  mode                 ENUM('online','offline') NOT NULL DEFAULT 'online',
  offline_method       ENUM('cash','cheque','bank_transfer','upi','other') NULL,
  reference_no         VARCHAR(80) NULL,            -- cheque no / UTR for offline
  status               ENUM('pending','success','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  is_anonymous         TINYINT(1) NOT NULL DEFAULT 0,
  dedication           VARCHAR(200) NULL,           -- in memory of / in honour of
  note                 VARCHAR(255) NULL,
  razorpay_order_id    VARCHAR(40) NULL,
  razorpay_payment_id  VARCHAR(40) NULL,
  payment_method       VARCHAR(30) NULL,            -- upi, card, netbanking, wallet
  utm_source           VARCHAR(80) NULL,
  ip                   VARCHAR(45) NULL,
  paid_at              DATETIME NULL,
  created_by           INT UNSIGNED NULL,           -- admin user for offline entries
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order   (razorpay_order_id),
  UNIQUE KEY uq_payment (razorpay_payment_id),      -- same payment can never be recorded twice
  KEY idx_status_paid (status, paid_at),
  KEY idx_donor (donor_id),
  KEY idx_campaign (campaign_id),
  FOREIGN KEY (donor_id)    REFERENCES donors(id),
  FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gapless receipt numbers per financial year. Always increment inside a transaction:
--   SELECT last_no FROM receipt_sequences WHERE fy=? FOR UPDATE;  then UPDATE ... last_no = last_no + 1
CREATE TABLE receipt_sequences (
  fy       VARCHAR(9) PRIMARY KEY,                  -- 2026-27
  last_no  INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE receipts (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donation_id    INT UNSIGNED NOT NULL,
  receipt_no     VARCHAR(30) NOT NULL,              -- PSS/2026-27/0001
  fy             VARCHAR(9)  NOT NULL,
  seq            INT UNSIGNED NOT NULL,
  verify_code    CHAR(16) NOT NULL,                 -- random; public check at /verify.php?c=...
  is_80g         TINYINT(1) NOT NULL DEFAULT 0,
  pdf_path       VARCHAR(255) NULL,
  status         ENUM('issued','cancelled') NOT NULL DEFAULT 'issued',
  cancel_reason  VARCHAR(255) NULL,
  cancelled_by   INT UNSIGNED NULL,
  cancelled_at   DATETIME NULL,
  emailed_at     DATETIME NULL,
  issued_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_receipt_no (receipt_no),
  UNIQUE KEY uq_fy_seq (fy, seq),
  UNIQUE KEY uq_verify (verify_code),
  UNIQUE KEY uq_donation (donation_id),             -- one receipt per donation
  FOREIGN KEY (donation_id)  REFERENCES donations(id),
  FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every webhook hit is stored. event_id unique = idempotency (duplicate delivery is ignored)
CREATE TABLE payment_events (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider         VARCHAR(20) NOT NULL DEFAULT 'razorpay',
  event_id         VARCHAR(100) NOT NULL,           -- header X-Razorpay-Event-Id
  event_type       VARCHAR(60)  NOT NULL,           -- payment.captured, payment.failed, refund.processed
  order_id         VARCHAR(40) NULL,
  payment_id       VARCHAR(40) NULL,
  signature_valid  TINYINT(1) NOT NULL DEFAULT 0,
  payload          JSON NOT NULL,
  processed        TINYINT(1) NOT NULL DEFAULT 0,
  processed_at     DATETIME NULL,
  error            TEXT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event (provider, event_id),
  KEY idx_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE refunds (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donation_id         INT UNSIGNED NOT NULL,
  razorpay_refund_id  VARCHAR(40) NULL,
  amount              DECIMAL(12,2) NOT NULL,
  reason              VARCHAR(255) NULL,
  status              ENUM('requested','approved','processed','rejected') NOT NULL DEFAULT 'requested',
  requested_by        INT UNSIGNED NULL,
  approved_by         INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (donation_id)  REFERENCES donations(id),
  FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (approved_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- 005_seed.sql : roles, permissions, default settings, base pages
SET NAMES utf8mb4;

INSERT INTO roles (slug, name) VALUES
 ('super_admin','Super Admin'),
 ('admin','Admin');

INSERT INTO permissions (code, module, label) VALUES
 ('dashboard.view',        'dashboard',  'Dashboard dekhna'),
 ('pages.manage',          'cms',        'Pages manage'),
 ('sliders.manage',        'cms',        'Home slider manage'),
 ('trustees.manage',       'cms',        'Trustees / Team manage'),
 ('news.manage',           'cms',        'News / Current Affairs manage'),
 ('offers.manage',         'cms',        'Trust Offers / Schemes manage'),
 ('offer_applications.manage','cms',     'Offer applications review'),
 ('audio.manage',          'media',      'Audio manage'),
 ('video.manage',          'media',      'Video manage'),
 ('gallery.manage',        'media',      'Gallery manage'),
 ('notices.manage',        'cms',        'Notices / Downloads manage'),
 ('financial_docs.manage', 'transparency','Financial documents manage'),
 ('legal_docs.manage',     'transparency','Legal documents manage'),
 ('campaigns.manage',      'donations',  'Campaigns manage'),
 ('donations.view',        'donations',  'Donations dekhna'),
 ('donations.offline_add', 'donations',  'Offline donation entry'),
 ('donations.export',      'donations',  'Donation / donor data export'),
 ('receipts.resend',       'donations',  'Receipt dobara bhejna'),
 ('receipts.cancel',       'donations',  'Receipt cancel karna'),
 ('refunds.approve',       'donations',  'Refund approve karna'),
 ('enquiries.manage',      'forms',      'Contact enquiries'),
 ('volunteers.manage',     'forms',      'Volunteers manage'),
 ('users.manage',          'system',     'Admin users manage'),
 ('settings.manage',       'system',     'Site / payment / SMTP settings'),
 ('audit.view',            'system',     'Activity / audit log dekhna'),
 ('backup.manage',         'system',     'Backup download / restore');

-- Super Admin = everything
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'super_admin';

-- Admin = content + day-to-day work. NO users, settings, audit, backup, refunds, receipt cancel, export
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.code IN ('dashboard.view','pages.manage','sliders.manage','trustees.manage','news.manage',
                'offers.manage','offer_applications.manage','audio.manage','video.manage',
                'gallery.manage','notices.manage','financial_docs.manage','legal_docs.manage',
                'campaigns.manage','donations.view','donations.offline_add','receipts.resend',
                'enquiries.manage','volunteers.manage')
WHERE r.slug = 'admin';

INSERT INTO settings (skey, svalue) VALUES
 ('site_name_en',        'Pragati Seva Sansthan'),
 ('site_name_hi',        'प्रगति सेवा संस्थान'),
 ('site_tagline',        ''),
 ('contact_email',       ''),
 ('contact_phone',       ''),
 ('address',             ''),
 ('registration_no',     ''),
 ('pan_no',              ''),
 ('reg_12a_no',          ''),
 ('reg_80g_no',          ''),
 ('reg_80g_valid_to',    ''),
 ('receipt_prefix',      'PSS'),
 ('min_donation',        '100'),
 ('donation_presets',    '501,1100,2100,5100'),
 ('razorpay_mode',       'test'),
 ('bank_name',           ''),
 ('bank_account_name',   ''),
 ('bank_account_no',     ''),
 ('bank_ifsc',           ''),
 ('upi_id',              ''),
 ('social_facebook',     ''),
 ('social_instagram',    ''),
 ('social_youtube',      ''),
 ('social_whatsapp',     ''),
 ('analytics_id',        '');
-- NOTE: Razorpay key/secret, SMTP password, encryption key go in .env, NOT in this table.

INSERT INTO pages (slug, title_en, title_hi, status) VALUES
 ('about',          'About Us',                 'हमारे बारे में',      'draft'),
 ('privacy-policy', 'Privacy Policy',           'गोपनीयता नीति',       'draft'),
 ('refund-policy',  'Donation & Refund Policy', 'दान और रिफंड नीति',   'draft'),
 ('terms',          'Terms & Conditions',       'नियम और शर्तें',      'draft');

INSERT INTO news_categories (slug, name_en, name_hi) VALUES
 ('current-affairs','Current Affairs','समसामयिकी'),
 ('events','Events','कार्यक्रम'),
 ('press','Press & Media','प्रेस');

-- Super Admin user is created ONCE manually (no default password in repo):
--   1) php -r "echo password_hash('YourStrongPassword', PASSWORD_ARGON2ID);"
--   2) INSERT INTO users (role_id,name,email,password_hash)
--      SELECT id,'Your Name','you@example.com','<paste hash>' FROM roles WHERE slug='super_admin';
