<?php
/** transparency.php - financial reports by year + legal registrations */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';
$types = ['audit_report' => ['ऑडिट रिपोर्ट', 'Audit report'], 'balance_sheet' => ['बैलेंस शीट', 'Balance sheet'], 'income_expenditure' => ['आय-व्यय विवरण', 'Income & expenditure'],
          'itr' => ['आयकर रिटर्न', 'Income-tax return'], 'form_10b' => ['फॉर्म 10B', 'Form 10B'], 'utilization_certificate' => ['उपयोगिता प्रमाणपत्र', 'Utilization certificate'],
          'annual_report' => ['वार्षिक रिपोर्ट', 'Annual report'], 'other' => ['अन्य', 'Other']];
$fin = db_rows("SELECT * FROM financial_docs WHERE status = 'published' ORDER BY fy DESC, id DESC");
$byFy = []; foreach ($fin as $d) $byFy[$d['fy']][] = $d;
$legal = db_rows("SELECT * FROM legal_docs WHERE status = 'published' ORDER BY sort_order, id");
$page_title = tx('पारदर्शिता', 'Transparency'); $nav_active = 'transparency';
require ROOT_PATH . '/includes/public_header.php'; ?>
<section class="sec"><div class="wrap">
  <div class="sec-head"><h2><?= e(tx('पंजीकरण एवं कानूनी दस्तावेज़', 'Registrations & legal documents')) ?></h2></div>
  <?php if (!$legal): ?><div class="empty"><?= e(tx('जानकारी जल्द उपलब्ध होगी।', 'Information coming soon.')) ?></div><?php else: ?>
  <div class="card" style="overflow:auto"><table class="tbl">
    <tr><th><?= e(tx('दस्तावेज़', 'Document')) ?></th><th><?= e(tx('संख्या', 'Number')) ?></th><th><?= e(tx('वैधता', 'Validity')) ?></th><th></th></tr>
    <?php foreach ($legal as $d): ?><tr><td><strong><?= e(pick($d, 'title')) ?></strong></td><td><?= e($d['doc_no'] ?? '-') ?></td>
      <td><?= e(trim(($d['valid_from'] ? date('d M Y', strtotime($d['valid_from'])) : '') . ($d['valid_to'] ? ' → ' . date('d M Y', strtotime($d['valid_to'])) : '')) ?: '-') ?></td>
      <td><?php if ($d['file_path']): ?><a target="_blank" href="<?= e(upload_url($d['file_path'])) ?>">PDF ↓</a><?php endif; ?></td></tr><?php endforeach; ?>
  </table></div><?php endif; ?>
</div></section>
<section class="sec" style="background:var(--paper)"><div class="wrap">
  <div class="sec-head"><h2><?= e(tx('वित्तीय रिपोर्ट', 'Financial reports')) ?></h2></div>
  <?php if (!$byFy): ?><div class="empty"><?= e(tx('रिपोर्ट जल्द उपलब्ध होंगी।', 'Reports coming soon.')) ?></div><?php endif; ?>
  <?php foreach ($byFy as $fy => $docs): ?>
    <h3><?= e(tx('वित्त वर्ष', 'Financial year')) ?> <?= e($fy) ?></h3>
    <ul class="doclist"><?php foreach ($docs as $d): ?>
      <li><span class="tag"><?= e($types[$d['doc_type']][lang() === 'hi' ? 0 : 1] ?? '') ?></span>
        <a target="_blank" href="<?= e(upload_url($d['file_path'])) ?>"><?= e(pick($d, 'title')) ?> ↓</a></li><?php endforeach; ?></ul>
  <?php endforeach; ?>
</div></section>
<?php require ROOT_PATH . '/includes/public_footer.php';
