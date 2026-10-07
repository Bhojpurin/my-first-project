<?php
/** news_view.php?slug=... - single news article */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';

$n = db_query("SELECT n.*, c.name_en AS cat_en, c.name_hi AS cat_hi FROM news n
               LEFT JOIN news_categories c ON c.id = n.category_id
               WHERE n.slug = ? AND n.status = 'published' AND n.publish_at <= NOW()", [get_str('slug', 200)])->fetch();
if (!$n) {
    http_response_code(404);
    $page_title = t('not_found'); $nav_active = 'news';
    require ROOT_PATH . '/includes/public_header.php';
    echo '<div class="article"><div class="empty">' . e(t('not_found')) . '</div></div>';
    require ROOT_PATH . '/includes/public_footer.php';
    exit;
}
db_query('UPDATE news SET views = views + 1 WHERE id = ?', [$n['id']]);

$page_title = pick($n, 'title'); $meta_desc = pick($n, 'summary'); $nav_active = 'news';
require ROOT_PATH . '/includes/public_header.php'; ?>
<article class="article">
  <?php $cat = pick(['name_en' => $n['cat_en'], 'name_hi' => $n['cat_hi']], 'name'); if ($cat !== ''): ?><span class="tag"><?= e($cat) ?></span><?php endif; ?>
  <h1><?= e(pick($n, 'title')) ?></h1>
  <p class="meta"><?= e(date('d M Y', strtotime($n['publish_at']))) ?></p>
  <?php if ($n['image']): ?><img class="cover" src="<?= e(upload_url($n['image'])) ?>" alt=""><?php endif; ?>
  <?= text_to_html(pick($n, 'content')) ?>
  <p><a href="<?= e(url('news.php')) ?>">← <?= e(t('all_news')) ?></a></p>
</article>
<?php require ROOT_PATH . '/includes/public_footer.php';
