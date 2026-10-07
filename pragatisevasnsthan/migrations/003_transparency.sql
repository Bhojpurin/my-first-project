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
