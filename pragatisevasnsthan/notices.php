<?php
/** notices.php - notices / downloads */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';
$rows = db_rows("SELECT * FROM notices WHERE status = 'published' ORDER BY notice_date DESC, id DESC");
$page_title = tx('सूचनाएँ / डाउनलोड', 'Notices & downloads'); $nav_active = '';
require ROOT_PATH . '/includes/public_header.php'; ?>
<section class="sec"><div class="wrap">
  <div class="sec-head"><h2><?= e(tx('सूचनाएँ / डाउनलोड', 'Notices & downloads')) ?></h2></div>
  <?php if (!$rows): ?><div class="empty"><?= e(tx('अभी कोई सूचना नहीं है।', 'No notices yet.')) ?></div>
  <?php else: ?><ul class="doclist"><?php foreach ($rows as $n): ?>
    <li><span class="meta"><?= e(date('d M Y', strtotime($n['notice_date']))) ?></span>
      <a target="_blank" href="<?= e(upload_url($n['file_path'])) ?>"><?= e(pick($n, 'title')) ?> ↓</a></li><?php endforeach; ?></ul><?php endif; ?>
</div></section>
<?php require ROOT_PATH . '/includes/public_footer.php';
