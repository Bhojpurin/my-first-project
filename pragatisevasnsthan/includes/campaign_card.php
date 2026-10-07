<?php /** campaign card partial: expects $c (campaigns row) */
$pct = $c['target_amount'] > 0 ? min(100, round($c['raised_cache'] / $c['target_amount'] * 100)) : 0; ?>
<article class="card">
  <?php if ($c['image']): ?><img src="<?= e(upload_url($c['image'])) ?>" alt="" loading="lazy"><?php else: ?><div class="ph">🌱</div><?php endif; ?>
  <div class="card-body">
    <h3><?= e(pick($c, 'title')) ?></h3>
    <p><?= e(pick($c, 'summary')) ?></p>
    <?php if ($c['target_amount'] > 0): ?>
      <div class="progress"><i style="width:<?= (int) $pct ?>%"></i></div>
      <div class="pgrow"><span><?= e(t('raised')) ?>: <?= e(inr($c['raised_cache'])) ?></span><span><?= e(t('goal')) ?>: <?= e(inr($c['target_amount'])) ?></span></div>
    <?php endif; ?>
    <a class="btn btn-amber" href="<?= e(url('donate.php?campaign=' . urlencode($c['slug']))) ?>"><?= e(t('donate_now')) ?></a>
  </div>
</article>
