<?php /** news card partial: expects $n (news row) */ ?>
<article class="card">
  <?php if ($n['image']): ?><img src="<?= e(upload_url($n['image'])) ?>" alt="" loading="lazy"><?php else: ?><div class="ph">📰</div><?php endif; ?>
  <div class="card-body">
    <span class="meta"><?= e(date('d M Y', strtotime($n['publish_at']))) ?></span>
    <h3><a href="<?= e(url('news_view.php?slug=' . urlencode($n['slug']))) ?>"><?= e(pick($n, 'title')) ?></a></h3>
    <p><?= e(pick($n, 'summary')) ?></p>
    <a class="btn btn-ghost" href="<?= e(url('news_view.php?slug=' . urlencode($n['slug']))) ?>"><?= e(t('read_more')) ?></a>
  </div>
</article>
