<?php
/** index.php - public Home page */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';

$slide = db_query("SELECT * FROM sliders WHERE status = 'active' ORDER BY sort_order, id LIMIT 1")->fetch();
$campaigns = db_rows("SELECT * FROM campaigns WHERE status = 'active' ORDER BY is_featured DESC, sort_order, id DESC LIMIT 3");
$news = db_rows("SELECT * FROM news WHERE status = 'published' AND publish_at <= NOW()
                 ORDER BY publish_at DESC LIMIT 3");

$nav_active = 'home';
require ROOT_PATH . '/includes/public_header.php';

$heroTitle = $slide ? pick($slide, 'title') : t('hero_default_title');
$heroSub   = $slide ? pick($slide, 'subtitle') : t('hero_default_sub');
$heroBg    = $slide && $slide['image'] ? "background-image:linear-gradient(rgba(11,31,24,.72),rgba(11,31,24,.72)),url('" . e(upload_url($slide['image'])) . "')" : '';
?>
<section class="hero" style="<?= $heroBg ?>">
  <div class="wrap">
    <h1><?= e($heroTitle) ?></h1>
    <p><?= e($heroSub) ?></p>
    <div class="btns">
      <a class="btn btn-amber" href="<?= e(url('donate.php')) ?>"><?= e(t('donate_now')) ?></a>
      <a class="btn btn-ghost" href="<?= e(url('campaigns.php')) ?>"><?= e(t('our_campaigns')) ?></a>
    </div>
  </div>
</section>

<section class="sec"><div class="wrap">
  <div class="sec-head"><h2><?= e(t('our_campaigns')) ?></h2><a href="<?= e(url('campaigns.php')) ?>"><?= e(t('all_campaigns')) ?> →</a></div>
  <?php if (!$campaigns): ?><div class="empty"><?= e(t('no_campaigns')) ?></div>
  <?php else: ?><div class="grid"><?php foreach ($campaigns as $c) include ROOT_PATH . '/includes/campaign_card.php'; ?></div><?php endif; ?>
</div></section>

<section class="sec" style="background:var(--paper)"><div class="wrap">
  <div class="sec-head"><h2><?= e(t('latest_news')) ?></h2><a href="<?= e(url('news.php')) ?>"><?= e(t('all_news')) ?> →</a></div>
  <?php if (!$news): ?><div class="empty"><?= e(t('no_news')) ?></div>
  <?php else: ?><div class="grid"><?php foreach ($news as $n) include ROOT_PATH . '/includes/news_card.php'; ?></div><?php endif; ?>
</div></section>
<?php require ROOT_PATH . '/includes/public_footer.php';
