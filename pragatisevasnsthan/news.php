<?php
/** news.php - public news list with pagination */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';

$where = "status = 'published' AND publish_at <= NOW()";
$p = paginate((int) db_value("SELECT COUNT(*) FROM news WHERE $where"), 9, (int) ($_GET['page'] ?? 1));
$rows = db_rows("SELECT * FROM news WHERE $where ORDER BY publish_at DESC LIMIT {$p['per']} OFFSET {$p['offset']}");

$page_title = t('news'); $nav_active = 'news';
require ROOT_PATH . '/includes/public_header.php'; ?>
<section class="sec"><div class="wrap">
  <div class="sec-head"><h2><?= e(t('news')) ?></h2></div>
  <?php if (!$rows): ?><div class="empty"><?= e(t('no_news')) ?></div>
  <?php else: ?><div class="grid"><?php foreach ($rows as $n) include ROOT_PATH . '/includes/news_card.php'; ?></div>
  <?= pager_html($p, ['lang' => $_GET['lang'] ?? '']) ?><?php endif; ?>
</div></section>
<?php require ROOT_PATH . '/includes/public_footer.php';
