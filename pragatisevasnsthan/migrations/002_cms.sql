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
