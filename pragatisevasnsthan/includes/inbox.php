<?php
/**
 * inbox.php - engine for "incoming form" modules (enquiries, volunteers): list + filter, detail view,
 * change status, delete. Records are created by the public forms, never by admins.
 *   inbox_run(['table','perm','file','title','noun','statuses'=>[..],'search'=>[cols],'list'=>[[label,fn]],'detail'=>[col=>label]])
 */
require_once ROOT_PATH . '/includes/forms.php';

function inbox_run(array $cfg): void
{
    $t = $cfg['table'];
    if (!preg_match('/^[a-z_]+$/', $t)) throw new InvalidArgumentException('bad table');
    require_perm($cfg['perm']);
    $page_title = $cfg['title']; $active = $cfg['file'];
    $statuses = $cfg['statuses'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $id  = (int) ($_POST['id'] ?? 0);
        $old = db_query("SELECT * FROM `$t` WHERE id = ?", [$id])->fetch();
        if (!$old) { flash('error', 'Record nahi mila.'); redirect('admin/' . $cfg['file']); }
        if (($_POST['do'] ?? '') === 'status' && in_array($_POST['status'] ?? '', $statuses, true)) {
            db_query("UPDATE `$t` SET status = ? WHERE id = ?", [$_POST['status'], $id]);
            audit('update', $t, $id, ['status' => $old['status']], ['status' => $_POST['status']]);
            flash('success', 'Status update ho gaya.');
            redirect('admin/' . $cfg['file'] . '?action=view&id=' . $id);
        }
        if (($_POST['do'] ?? '') === 'delete') {
            db_query("DELETE FROM `$t` WHERE id = ?", [$id]);
            audit('delete', $t, $id, $old, null);
            flash('success', 'Record delete ho gaya.');
            redirect('admin/' . $cfg['file']);
        }
        redirect('admin/' . $cfg['file']);
    }

    $action = $_GET['action'] ?? 'list';
    if ($action === 'view') {
        $row = db_query("SELECT * FROM `$t` WHERE id = ?", [(int) ($_GET['id'] ?? 0)])->fetch();
        if (!$row) { flash('error', 'Record nahi mila.'); redirect('admin/' . $cfg['file']); }
        if ($row['status'] === $statuses[0] && !empty($cfg['auto_seen'])) {   // first open: new -> next status
            db_query("UPDATE `$t` SET status = ? WHERE id = ?", [$statuses[1], $row['id']]); $row['status'] = $statuses[1];
        }
    } else {
        $q = get_str('q'); $fs = get_str('status', 12);
        $where = ['1=1']; $params = [];
        if ($q !== '') { $like = []; foreach ($cfg['search'] as $c) { $like[] = "`$c` LIKE ?"; $params[] = "%$q%"; } $where[] = '(' . implode(' OR ', $like) . ')'; }
        if (in_array($fs, $statuses, true)) { $where[] = 'status = ?'; $params[] = $fs; }
        $w  = implode(' AND ', $where);
        $pg = paginate((int) db_value("SELECT COUNT(*) FROM `$t` WHERE $w", $params), 20, (int) ($_GET['page'] ?? 1));
        $rows = db_rows("SELECT * FROM `$t` WHERE $w ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
    }
    $badge = fn($s) => '<span class="badge ' . ($s === $statuses[0] ? 'b-amber' : (in_array($s, ['closed', 'inactive'], true) ? 'b-grey' : 'b-green')) . '">' . e(ucfirst($s)) . '</span>';

    require ROOT_PATH . '/includes/admin_header.php';
    if ($action === 'view'): ?>
      <div class="card"><h2><?= e($cfg['noun']) ?> #<?= (int) $row['id'] ?> <?= $badge($row['status']) ?></h2>
        <table class="t"><?php foreach ($cfg['detail'] as $col => $label): ?>
          <tr><th style="width:180px"><?= e($label) ?></th><td><?= nl2br(e((string) ($row[$col] ?? ''))) ?: '-' ?></td></tr><?php endforeach; ?>
          <tr><th>Received</th><td><?= e($row['created_at']) ?></td></tr></table>
        <div class="form-actions">
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="status"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <select name="status"><?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $row['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select>
            <button class="btn btn-amber" type="submit">Update status</button></form>
          <form method="post" style="display:inline" onsubmit="return confirm('Pakka delete karein?');"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button class="btn btn-danger" type="submit">Delete</button></form>
          <a class="btn btn-ghost" href="<?= e($cfg['file']) ?>">← Back</a></div></div>
    <?php else: ?>
      <div class="toolbar"><form method="get" class="filters"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search...">
        <select name="status"><option value="">All status</option><?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $fs === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select>
        <button class="btn btn-ghost" type="submit">Filter</button></form></div>
      <div class="card"><?php if (!$rows): ?><p class="muted">Abhi kuch nahi aaya.</p><?php else: ?>
        <table class="t"><tr><th>Received</th><?php foreach ($cfg['list'] as $c): ?><th><?= e($c[0]) ?></th><?php endforeach; ?><th>Status</th><th></th></tr>
          <?php foreach ($rows as $r): ?><tr><td><?= e(date('d M Y, h:i A', strtotime($r['created_at']))) ?></td>
            <?php foreach ($cfg['list'] as $c): ?><td><?= $c[1]($r) ?></td><?php endforeach; ?>
            <td><?= $badge($r['status']) ?></td><td><a class="btn btn-ghost sm" href="?action=view&id=<?= (int) $r['id'] ?>">Open</a></td></tr><?php endforeach; ?></table>
        <?= pager_html($pg, ['q' => $q, 'status' => $fs]) ?><?php endif; ?></div>
    <?php endif;
    require ROOT_PATH . '/includes/admin_footer.php';
}
