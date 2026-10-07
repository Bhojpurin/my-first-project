<?php
/** team.php - trustees and team */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';
$rows = db_rows("SELECT * FROM trustees WHERE status = 'active' ORDER BY sort_order, id");
$page_title = tx('ट्रस्टी एवं टीम', 'Trustees & Team'); $nav_active = 'about';
require ROOT_PATH . '/includes/public_header.php'; ?>
<section class="sec"><div class="wrap">
  <div class="sec-head"><h2><?= e(tx('ट्रस्टी एवं टीम', 'Trustees & Team')) ?></h2></div>
  <?php if (!$rows): ?><div class="empty"><?= e(tx('जानकारी जल्द उपलब्ध होगी।', 'Information coming soon.')) ?></div>
  <?php else: ?><div class="grid"><?php foreach ($rows as $m): ?>
    <article class="card">
      <?php if ($m['photo']): ?><img src="<?= e(upload_url($m['photo'])) ?>" alt="<?= e($m['name']) ?>" loading="lazy" style="aspect-ratio:1/1"><?php else: ?><div class="ph" style="aspect-ratio:1/1">👤</div><?php endif; ?>
      <div class="card-body"><h3><?= e($m['name']) ?></h3>
        <span class="tag" style="align-self:flex-start"><?= e(pick($m, 'designation')) ?></span>
        <p><?= e(pick($m, 'bio')) ?></p></div>
    </article><?php endforeach; ?></div><?php endif; ?>
</div></section>
<?php require ROOT_PATH . '/includes/public_footer.php';
