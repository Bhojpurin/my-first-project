<?php
/**
 * crud.php - ONE engine for simple admin modules (list + add + edit + delete + image + audit).
 * A module page only describes itself (see admin/pages.php, sliders.php, campaigns.php):
 *
 *   crud_run([
 *     'table' => 'campaigns', 'perm' => 'campaigns.manage', 'file' => 'campaigns.php',
 *     'title' => 'Campaigns', 'noun' => 'Campaign',
 *     'image_dir' => 'campaigns',            // optional: adds an image upload
 *     'image_required' => false,
 *     'slug_from' => 'title_en',             // optional: table has a unique `slug` column
 *     'order' => 'sort_order, id DESC',
 *     'fields' => [ ['name','Label','text|textarea|number|date|select|checkbox', [opts]] ... ],
 *     'list'   => [ ['Header', fn($row) => 'safe html'] ... ],
 *     'list_sql' => 'name, name_hi',          // columns to SELECT for the list (id + image + slug auto)
 *   ]);
 * Field opts: required(bool) max(int) options(array value=>label) rows(int) hint(string) half(bool)
 *             check(fn($value) => error string|null)
 */
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';

function crud_run(array $cfg): void
{
    $table  = $cfg['table'];
    if (!preg_match('/^[a-z_]+$/', $table)) throw new InvalidArgumentException('bad table');
    require_perm($cfg['perm']);

    $page_title = $cfg['title'];
    $active     = $cfg['file'];
    $noun       = $cfg['noun'];
    $imgDir     = $cfg['image_dir'] ?? null;
    $slugFrom   = $cfg['slug_from'] ?? null;
    $errors     = [];
    $form       = null;
    $action     = $_GET['action'] ?? 'list';

    $cols = array_map(fn($f) => $f[0], $cfg['fields']);
    if ($slugFrom) array_unshift($cols, 'slug');
    if ($imgDir)   $cols[] = 'image';

    // ---------- POST ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $do  = $_POST['do'] ?? '';
        $id  = (int) ($_POST['id'] ?? 0);
        $old = $id ? db_query("SELECT * FROM `$table` WHERE id = ?", [$id])->fetch() : null;
        if ($id && !$old) { flash('error', "$noun nahi mila."); redirect('admin/' . $cfg['file']); }

        if ($do === 'delete' && $old) {
            db_query("DELETE FROM `$table` WHERE id = ?", [$id]);
            if ($imgDir) delete_upload($old['image']);
            audit('delete', $table, $id, $old, null);
            flash('success', "$noun delete ho gaya.");
            redirect('admin/' . $cfg['file']);
        }

        if ($do === 'save') {
            $form = ['id' => $id];
            foreach ($cfg['fields'] as $f) {
                [$name, $label, $type] = $f;
                $o = $f[3] ?? [];
                $v = match ($type) {
                    'checkbox' => isset($_POST[$name]) ? 1 : 0,
                    'number'   => ($_POST[$name] ?? '') === '' ? 0 : max(0, (float) $_POST[$name]),
                    'select'   => array_key_exists($_POST[$name] ?? '', $o['options'] ?? []) ? (string) $_POST[$name] : (string) array_key_first($o['options']),
                    'date'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST[$name] ?? '') ? $_POST[$name] : null,
                    'textarea' => post_str($name, $o['max'] ?? 200000),
                    default    => post_str($name, $o['max'] ?? 255),
                };
                $form[$name] = $v;
                if (!empty($o['required']) && ($v === '' || $v === null)) $errors[] = "$label zaruri hai.";
                if (isset($o['check']) && ($err = $o['check']($v))) $errors[] = $err;
            }
            $form['image'] = $old['image'] ?? null;

            $newImage = null;
            if ($imgDir) {
                $up = save_upload('image', $imgDir);
                if (!$up['ok']) $errors[] = $up['error'];
                else $newImage = $up['path'];
                if (!$errors && !empty($cfg['image_required']) && !$newImage && (!$form['image'] || !empty($_POST['remove_image']))) {
                    $errors[] = 'Image zaruri hai.';
                }
            }

            if (!$errors) {
                if ($slugFrom) {
                    $slug = slugify(post_str('slug', 200) !== '' ? post_str('slug', 200) : (string) $form[$slugFrom]);
                    if ($slug === '') $slug = $table . '-' . date('Ymd-His');
                    $form['slug'] = unique_slug($table, $slug, $id);
                }
                $oldImage = $old['image'] ?? null;
                if ($imgDir) {
                    if ($newImage)                          $form['image'] = $newImage;
                    elseif (!empty($_POST['remove_image'])) $form['image'] = null;
                }
                $vals = array_map(fn($c) => $form[$c], $cols);
                if ($id) {
                    $set = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
                    db_query("UPDATE `$table` SET $set WHERE id = ?", array_merge($vals, [$id]));
                    if ($imgDir && $form['image'] !== $oldImage) delete_upload($oldImage);
                    audit('update', $table, $id, array_intersect_key($old, array_flip($cols)), array_intersect_key($form, array_flip($cols)));
                    flash('success', "$noun update ho gaya.");
                } else {
                    $names = implode(', ', array_map(fn($c) => "`$c`", $cols));
                    db_query("INSERT INTO `$table` ($names) VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ')', $vals);
                    audit('create', $table, (int) db()->lastInsertId(), null, array_intersect_key($form, array_flip($cols)));
                    flash('success', "$noun add ho gaya.");
                }
                redirect('admin/' . $cfg['file']);
            }
            if ($newImage) delete_upload($newImage);   // validation failed: no orphan file
            if ($slugFrom) $form['slug'] = post_str('slug', 200);
            $action = $id ? 'edit' : 'new';
        }
    }

    // ---------- GET ----------
    if ($action === 'new' && $form === null) {
        $form = ['id' => 0, 'image' => null, 'slug' => ''];
        foreach ($cfg['fields'] as $f) {
            $form[$f[0]] = $f[3]['default'] ?? ($f[2] === 'number' || $f[2] === 'checkbox' ? 0 : ($f[2] === 'select' ? array_key_first($f[3]['options']) : ''));
        }
    }
    if ($action === 'edit' && $form === null) {
        $form = db_query("SELECT * FROM `$table` WHERE id = ?", [(int) ($_GET['id'] ?? 0)])->fetch();
        if (!$form) { flash('error', "$noun nahi mila."); redirect('admin/' . $cfg['file']); }
    }

    if ($action === 'list') {
        $pg   = paginate((int) db_value("SELECT COUNT(*) FROM `$table`"), 20, (int) ($_GET['page'] ?? 1));
        $rows = db_rows("SELECT * FROM `$table` ORDER BY {$cfg['order']} LIMIT {$pg['per']} OFFSET {$pg['offset']}");
    }

    require ROOT_PATH . '/includes/admin_header.php';
    $self = $cfg['file'];
    if ($action === 'list'): ?>
      <div class="toolbar"><span></span><a class="btn btn-amber" href="?action=new">+ Add <?= e(strtolower($noun)) ?></a></div>
      <div class="card">
      <?php if (!$rows): ?><p class="muted">Abhi kuch nahi hai. "+ Add" se pehla record banayein.</p>
      <?php else: ?>
        <table class="t">
          <tr><?php if ($imgDir): ?><th style="width:64px"></th><?php endif; ?>
            <?php foreach ($cfg['list'] as $col): ?><th><?= e($col[0]) ?></th><?php endforeach; ?><th style="width:150px">Actions</th></tr>
          <?php foreach ($rows as $r): ?>
            <tr>
              <?php if ($imgDir): ?><td><?php if ($r['image']): ?><img class="thumb" src="<?= e(upload_url($r['image'])) ?>" alt=""><?php endif; ?></td><?php endif; ?>
              <?php foreach ($cfg['list'] as $col): ?><td><?= $col[1]($r) ?></td><?php endforeach; ?>
              <td class="actions">
                <a class="btn btn-ghost sm" href="?action=edit&id=<?= (int) $r['id'] ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Pakka delete karein?');">
                  <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button class="btn btn-danger sm" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
        <?= pager_html($pg) ?>
      <?php endif; ?>
      </div>
    <?php else:
      foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post" enctype="multipart/form-data" class="card form">
        <?= csrf_field() ?><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <h2><?= $form['id'] ? 'Edit' : 'Add' ?> <?= e(strtolower($noun)) ?></h2>
        <div class="grid2">
        <?php foreach ($cfg['fields'] as $f):
            [$name, $label, $type] = $f; $o = $f[3] ?? []; $val = $form[$name] ?? '';
            $wide = $type === 'textarea' || !empty($o['wide']); ?>
          <div class="field" <?= $wide ? 'style="grid-column:1/-1"' : '' ?>>
            <?php if ($type === 'checkbox'): ?>
              <label class="check"><input type="checkbox" name="<?= e($name) ?>" value="1" <?= $val ? 'checked' : '' ?>> <?= e($label) ?></label>
            <?php else: ?>
              <label><?= e($label) ?><?= !empty($o['required']) ? ' *' : '' ?></label>
              <?php if ($type === 'textarea'): ?>
                <textarea name="<?= e($name) ?>" rows="<?= (int) ($o['rows'] ?? 4) ?>"><?= e($val) ?></textarea>
              <?php elseif ($type === 'select'): ?>
                <select name="<?= e($name) ?>"><?php foreach ($o['options'] as $k => $l): ?>
                  <option value="<?= e($k) ?>" <?= (string) $val === (string) $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
              <?php else: ?>
                <input name="<?= e($name) ?>" value="<?= e($val) ?>" maxlength="<?= (int) ($o['max'] ?? 255) ?>"
                  <?= $type === 'number' ? 'type="number" min="0" step="any"' : ($type === 'date' ? 'type="date"' : '') ?> <?= !empty($o['required']) ? 'required' : '' ?>>
              <?php endif; ?>
            <?php endif; ?>
            <?php if (!empty($o['hint'])): ?><span class="hint"><?= e($o['hint']) ?></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if ($slugFrom): ?>
          <div class="field"><label>URL slug</label><input name="slug" value="<?= e($form['slug'] ?? '') ?>" maxlength="200" placeholder="khali chhodo to title se ban jayega"></div>
        <?php endif; ?>
        <?php if ($imgDir): ?>
          <div class="field"><label>Image (JPG / PNG / WebP, max 3 MB)<?= !empty($cfg['image_required']) ? ' *' : '' ?></label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <?php if (!empty($form['image'])): ?><div class="preview"><img src="<?= e(upload_url($form['image'])) ?>" alt="">
              <label class="check"><input type="checkbox" name="remove_image" value="1"> Image hata do</label></div><?php endif; ?></div>
        <?php endif; ?>
        </div>
        <div class="form-actions"><button class="btn btn-amber" type="submit">Save</button> <a class="btn btn-ghost" href="<?= e($self) ?>">Cancel</a></div>
      </form>
    <?php endif;
    require ROOT_PATH . '/includes/admin_footer.php';
}

/** Small coloured status badge for list columns. */
function crud_badge(string $status): string
{
    $cls = ['published' => 'b-green', 'active' => 'b-green', 'completed' => 'b-blue', 'draft' => 'b-grey',
            'inactive' => 'b-grey', 'closed' => 'b-grey'][$status] ?? 'b-grey';
    return '<span class="badge ' . $cls . '">' . e(ucfirst($status)) . '</span>';
}
