</main>
<footer class="s-foot">
  <div class="wrap foot-grid">
    <div>
      <h3><?= e($site_name) ?></h3>
      <?php if (setting('address') !== ''): ?><p><?= nl2br(e(setting('address'))) ?></p><?php endif; ?>
      <?php if (setting('contact_phone') !== ''): ?><p>📞 <?= e(setting('contact_phone')) ?></p><?php endif; ?>
      <?php if (setting('contact_email') !== ''): ?><p>✉ <?= e(setting('contact_email')) ?></p><?php endif; ?>
    </div>
    <div>
      <h3><?= e(t('quick_links')) ?></h3>
      <p><a href="<?= e(url('campaigns.php')) ?>"><?= e(t('campaigns')) ?></a></p>
      <p><a href="<?= e(url('news.php')) ?>"><?= e(t('news')) ?></a></p>
      <p><a href="<?= e(url('donate.php')) ?>"><?= e(t('donate_now')) ?></a></p>
      <p><a href="<?= e(url('page.php?slug=privacy-policy')) ?>"><?= e(t('privacy')) ?></a></p>
      <p><a href="<?= e(url('page.php?slug=refund-policy')) ?>"><?= e(t('refund')) ?></a></p>
      <p><a href="<?= e(url('page.php?slug=terms')) ?>"><?= e(t('terms')) ?></a></p>
    </div>
    <div>
      <?php foreach ([['registration_no', 'Reg. No.'], ['reg_12a_no', '12A'], ['reg_80g_no', '80G'], ['pan_no', 'PAN']] as [$k, $lbl]):
          if (setting($k) !== ''): ?><p><strong><?= e($lbl) ?>:</strong> <?= e(setting($k)) ?></p><?php endif;
      endforeach; ?>
      <?php foreach (['facebook', 'instagram', 'youtube'] as $s):
          $link = setting('social_' . $s);
          if (preg_match('#^https?://#i', $link)): ?><a class="social" rel="noopener" target="_blank" href="<?= e($link) ?>"><?= e(ucfirst($s)) ?></a> <?php endif;
      endforeach; ?>
    </div>
  </div>
  <div class="wrap copy">© <?= date('Y') ?> <?= e($site_name) ?></div>
</footer>
</body>
</html>
