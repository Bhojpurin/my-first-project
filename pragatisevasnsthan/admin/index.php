<?php
/**
 * admin/index.php - dashboard (summary cards).
 * Tables read: donations, enquiries, volunteers, offer_applications, audit_log
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_perm('dashboard.view');

$page_title = 'Dashboard';
$active = 'index.php';

$stats = [];
if (can('donations.view')) {
    $stats['today_amt'] = (float) db_value("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='success' AND DATE(paid_at)=CURDATE()");
    $stats['today_cnt'] = (int)   db_value("SELECT COUNT(*) FROM donations WHERE status='success' AND DATE(paid_at)=CURDATE()");
    $stats['month_amt'] = (float) db_value("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='success' AND paid_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')");
    $stats['month_cnt'] = (int)   db_value("SELECT COUNT(*) FROM donations WHERE status='success' AND paid_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')");
    $stats['pending']   = (int)   db_value("SELECT COUNT(*) FROM donations WHERE status='pending' AND created_at > (NOW() - INTERVAL 1 DAY)");
}
$new_enq  = can('enquiries.manage')  ? (int) db_value("SELECT COUNT(*) FROM enquiries WHERE status='new'") : null;
$new_vol  = can('volunteers.manage') ? (int) db_value("SELECT COUNT(*) FROM volunteers WHERE status='new'") : null;
$new_apps = can('offer_applications.manage') ? (int) db_value("SELECT COUNT(*) FROM offer_applications WHERE status='applied'") : null;

$recent = can('audit.view')
    ? db_rows('SELECT a.created_at, a.action, a.entity, a.ip, u.name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 8')
    : [];

require ROOT_PATH . '/includes/admin_header.php';
?>
<p class="muted">Namaste, <?= e(current_user()['name']) ?>. Yeh aaj ka summary hai.</p>

<div class="stats">
  <?php if (isset($stats['today_amt'])): ?>
    <div class="stat"><div class="label">Aaj ka daan</div><div class="value"><?= e(inr($stats['today_amt'])) ?></div><div class="sub"><?= $stats['today_cnt'] ?> donations</div></div>
    <div class="stat"><div class="label">Is mahine</div><div class="value"><?= e(inr($stats['month_amt'])) ?></div><div class="sub"><?= $stats['month_cnt'] ?> donations</div></div>
    <div class="stat <?= $stats['pending'] ? 'warn' : '' ?>"><div class="label">Pending payments (24h)</div><div class="value"><?= $stats['pending'] ?></div><div class="sub">Payment shuru hua, confirm nahi</div></div>
  <?php endif; ?>
  <?php if ($new_enq !== null): ?>
    <div class="stat <?= $new_enq ? 'warn' : '' ?>"><div class="label">Nayi enquiries</div><div class="value"><?= $new_enq ?></div><div class="sub">Reply baaki</div></div>
  <?php endif; ?>
  <?php if ($new_vol !== null): ?>
    <div class="stat"><div class="label">Naye volunteers</div><div class="value"><?= $new_vol ?></div><div class="sub">Contact karna baaki</div></div>
  <?php endif; ?>
  <?php if ($new_apps !== null): ?>
    <div class="stat <?= $new_apps ? 'warn' : '' ?>"><div class="label">Scheme applications</div><div class="value"><?= $new_apps ?></div><div class="sub">Review baaki</div></div>
  <?php endif; ?>
</div>

<?php if ($recent): ?>
<div class="card">
  <h2>Recent activity</h2>
  <table class="t">
    <tr><th>Time</th><th>User</th><th>Action</th><th>IP</th></tr>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><?= e(date('d M, h:i A', strtotime($r['created_at']))) ?></td>
        <td><?= e($r['name'] ?? '-') ?></td>
        <td><?= e($r['action']) ?><?= $r['entity'] ? ' · ' . e($r['entity']) : '' ?></td>
        <td><?= e($r['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <h2>Setup status</h2>
  <p>Login, roles aur permissions kaam kar rahe hain. Agle steps me content modules (news, pages, gallery...) aur donation system aayenge. Sidebar me "soon" wale items tab tak band rahenge.</p>
</div>
<?php require ROOT_PATH . '/includes/admin_footer.php';
