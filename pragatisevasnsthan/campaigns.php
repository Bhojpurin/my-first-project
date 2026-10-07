<?php
/** campaigns.php - all active / completed campaigns */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';

$rows = db_rows("SELECT * FROM campaigns WHERE status IN ('active','completed') ORDER BY status = 'active' DESC, is_featured DESC, sort_order, id DESC");
$page_title = t('campaigns'); $nav_active = 'campaigns';
require ROOT_PATH . '/includes/public_header.php'; ?>
<section class="sec"><div class="wrap">
  <div class="sec-head"><h2><?= e(t('campaigns')) ?></h2></div>
  <?php if (!$rows): ?><div class="empty"><?= e(t('no_campaigns')) ?></div>
  <?php else: ?><div class="grid"><?php foreach ($rows as $c) include ROOT_PATH . '/includes/campaign_card.php'; ?></div><?php endif; ?>
</div></section>
<?php require ROOT_PATH . '/includes/public_footer.php';
