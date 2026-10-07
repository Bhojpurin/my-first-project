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
