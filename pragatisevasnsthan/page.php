<?php
/** page.php?slug=about|privacy-policy|... - generic CMS page (published only) */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';

$slug = get_str('slug', 120);
$pg = db_query("SELECT * FROM pages WHERE slug = ? AND status = 'published'", [$slug])->fetch();
$nav_active = in_array($slug, ['about', 'contact'], true) ? $slug : '';
if (!$pg) {
    http_response_code(404);
    $page_title = t('not_found');
    require ROOT_PATH . '/includes/public_header.php';
    echo '<div class="article"><div class="empty">' . e(t('not_found')) . '</div></div>';
    require ROOT_PATH . '/includes/public_footer.php';
    exit;
}
$page_title = ($pg['meta_title'] ?: pick($pg, 'title')); $meta_desc = (string) $pg['meta_desc'];
require ROOT_PATH . '/includes/public_header.php'; ?>
<article class="article">
  <h1><?= e(pick($pg, 'title')) ?></h1>
  <?= text_to_html(pick($pg, 'content')) ?>
</article>
<?php require ROOT_PATH . '/includes/public_footer.php';
