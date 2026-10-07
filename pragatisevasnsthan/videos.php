<?php
/** videos.php - YouTube videos (privacy-enhanced embed, loads only when played) */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';
$rows = db_rows("SELECT * FROM video_items WHERE status = 'published' ORDER BY sort_order, id DESC");
$page_title = tx('वीडियो', 'Videos'); $nav_active = '';
require ROOT_PATH . '/includes/public_header.php'; ?>
<section class="sec"><div class="wrap">
  <div class="sec-head"><h2><?= e(tx('वीडियो', 'Videos')) ?></h2></div>
  <?php $rows = array_filter($rows, fn($v) => preg_match('/^[A-Za-z0-9_-]{11}$/', $v['youtube_id']));
  if (!$rows): ?><div class="empty"><?= e(tx('अभी कोई वीडियो नहीं है।', 'No videos yet.')) ?></div>
  <?php else: ?><div class="grid"><?php foreach ($rows as $v): ?>
    <article class="card">
      <iframe style="width:100%;aspect-ratio:16/9;border:0" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"
              src="https://www.youtube-nocookie.com/embed/<?= e($v['youtube_id']) ?>" title="<?= e(pick($v, 'title')) ?>"></iframe>
      <div class="card-body"><h3><?= e(pick($v, 'title')) ?></h3></div>
    </article><?php endforeach; ?></div><?php endif; ?>
</div></section>
<?php require ROOT_PATH . '/includes/public_footer.php';
