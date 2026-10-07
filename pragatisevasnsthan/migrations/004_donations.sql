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
