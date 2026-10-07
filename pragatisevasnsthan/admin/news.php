<?php
/**
 * admin/news.php - News / Current Affairs  (list + add + edit + delete, all in ONE file)
 *
 * TEMPLATE for the other modules (offers, gallery, notices ...): copy this file and change
 *   1) require_perm('...')   2) table name   3) $cols list   4) form HTML   5) list columns
 *
 * Tables: news, news_categories        Uploads: uploads/news/YYYY/MM/
 * Public text is plain text: blank line = new paragraph (safe against script injection).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';
require_perm('news.manage');

$page_title = 'News / Current Affairs';
$active     = 'news.php';
$errors     = [];
$form       = null;                         // values shown in the add/edit form
$action     = $_GET['action'] ?? 'list';    // list | new | edit

// columns we write to the DB (id, views, created_* are handled separately)
$cols = ['category_id', 'slug', 'title_en', 'title_hi', 'summary_en', 'summary_hi',
         'content_en', 'content_hi', 'image', 'publish_at', 'status', 'is_featured'];

// ======================= POST: save / delete =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do  = $_POST['do'] ?? '';
    $id  = (int) ($_POST['id'] ?? 0);
    $old = $id ? db_query('SELECT * FROM news WHERE id = ?', [$id])->fetch() : null;
    if ($id && !$old) { flash('error', 'News nahi mili.'); redirect('admin/news.php'); }

    // ---- delete ----
    if ($do === 'delete' && $old) {
        db_query('DELETE FROM news WHERE id = ?', [$id]);
        delete_upload($old['image']);
        audit('delete', 'news', $id, $old, null);
        flash('success', 'News delete ho gayi.');
        redirect('admin/news.php');
    }

    // ---- save (new or edit) ----
    if ($do === 'save') {
        $form = [
            'id'          => $id,
            'category_id' => (int) ($_POST['category_id'] ?? 0) ?: null,
            'slug'        => post_str('slug', 200),
            'title_en'    => post_str('title_en', 255),
            'title_hi'    => post_str('title_hi', 255),
            'summary_en'  => post_str('summary_en', 500),
            'summary_hi'  => post_str('summary_hi', 500),
            'content_en'  => post_str('content_en', 200000),
            'content_hi'  => post_str('content_hi', 200000),
            'image'       => $old['image'] ?? null,
            'publish_at'  => parse_datetime(post_str('publish_at', 20)),
            'status'      => in_array($_POST['status'] ?? '', ['draft', 'published'], true) ? $_POST['status'] : 'draft',
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        ];

        if ($form['title_en'] === '') $errors[] = 'English title zaruri hai.';
        if ($form['category_id'] && !db_value('SELECT COUNT(*) FROM news_categories WHERE id = ?', [$form['category_id']])) {
            $errors[] = 'Category galat hai.';
        }

        // image: new upload / remove / keep
        $newImage = null;
        $up = save_upload('image', 'news');
        if (!$up['ok']) $errors[] = $up['error'];
        else $newImage = $up['path'];

        if (!$errors) {
            $slug = slugify($form['slug'] !== '' ? $form['slug'] : $form['title_en']);
            if ($slug === '') $slug = 'news-' . date('Ymd-His');
            $form['slug'] = unique_slug('news', $slug, $id);

            $oldImage = $old['image'] ?? null;
            if ($newImage)                          { $form['image'] = $newImage; }
            elseif (!empty($_POST['remove_image'])) { $form['image'] = null; }

            if ($id) {
                $set = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
                db_query("UPDATE news SET $set WHERE id = ?", array_merge(array_map(fn($c) => $form[$c], $cols), [$id]));
                if ($form['image'] !== $oldImage) delete_upload($oldImage);
                audit('update', 'news', $id, array_intersect_key($old, array_flip($cols)), array_intersect_key($form, array_flip($cols)));
                flash('success', 'News update ho gayi.');
            } else {
                $names = implode(', ', array_map(fn($c) => "`$c`", $cols));
                $marks = implode(', ', array_fill(0, count($cols), '?'));
                db_query("INSERT INTO news ($names, created_by) VALUES ($marks, ?)",
                         array_merge(array_map(fn($c) => $form[$c], $cols), [$_SESSION['uid']]));
                $newId = (int) db()->lastInsertId();
                audit('create', 'news', $newId, null, array_intersect_key($form, array_flip($cols)));
                flash('success', 'News add ho gayi.');
            }
            redirect('admin/news.php');
        }

        // validation failed: a freshly uploaded file must not stay orphaned
        if ($newImage) delete_upload($newImage);
        $action = $id ? 'edit' : 'new';
    }
}

// ======================= GET: prepare the view =======================
$categories = db_rows('SELECT id, name_en FROM news_categories ORDER BY name_en');

if ($action === 'new' && $form === null) {
    $form = ['id' => 0, 'category_id' => null, 'slug' => '', 'title_en' => '', 'title_hi' => '', 'summary_en' => '',
             'summary_hi' => '', 'content_en' => '', 'content_hi' => '', 'image' => null,
             'publish_at' => date('Y-m-d H:i:s'), 'status' => 'draft', 'is_featured' => 0];
}
if ($action === 'edit' && $form === null) {
    $form = db_query('SELECT * FROM news WHERE id = ?', [(int) ($_GET['id'] ?? 0)])->fetch();
    if (!$form) { flash('error', 'News nahi mili.'); redirect('admin/news.php'); }
}

// list filters
$q      = get_str('q');
$fstat  = in_array($_GET['status'] ?? '', ['draft', 'published'], true) ? $_GET['status'] : '';
$where  = ['1=1']; $params = [];
if ($q !== '')     { $where[] = '(n.title_en LIKE ? OR n.title_hi LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($fstat !== '') { $where[] = 'n.status = ?'; $params[] = $fstat; }
$whereSql = implode(' AND ', $where);

if ($action === 'list') {
    $total = (int) db_value("SELECT COUNT(*) FROM news n WHERE $whereSql", $params);
    $pg    = paginate($total, 15, (int) ($_GET['page'] ?? 1));
    $rows  = db_rows(
        "SELECT n.id, n.title_en, n.title_hi, n.image, n.status, n.publish_at, n.is_featured, c.name_en AS category
         FROM news n LEFT JOIN news_categories c ON c.id = n.category_id
         WHERE $whereSql ORDER BY n.publish_at DESC, n.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
}

require ROOT_PATH . '/includes/admin_header.php';
?>

<?php if ($action === 'list'): ?>
  <div class="toolbar">
    <form method="get" class="filters">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Title search...">
      <select name="status">
        <option value="">All status</option>
        <option value="published" <?= $fstat === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="draft" <?= $fstat === 'draft' ? 'selected' : '' ?>>Draft</option>
      </select>
      <button class="btn btn-ghost" type="submit">Filter</button>
    </form>
    <a class="btn btn-amber" href="?action=new">+ Add news</a>
  </div>

  <div class="card">
    <?php if (!$rows): ?>
      <p class="muted">Abhi koi news nahi hai. "+ Add news" se pehli news daalein.</p>
    <?php else: ?>
    <table class="t">
      <tr><th style="width:64px"></th><th>Title</th><th>Category</th><th>Status</th><th>Publish date</th><th style="width:150px">Actions</th></tr>
      <?php foreach ($rows as $r):
          $scheduled = $r['status'] === 'published' && strtotime($r['publish_at']) > time(); ?>
        <tr>
          <td><?php if ($r['image']): ?><img class="thumb" src="<?= e(upload_url($r['image'])) ?>" alt=""><?php endif; ?></td>
          <td><strong><?= e($r['title_en']) ?></strong>
              <?php if ($r['title_hi']): ?><br><span class="muted"><?= e($r['title_hi']) ?></span><?php endif; ?>
              <?php if ($r['is_featured']): ?> <span class="badge b-amber">Featured</span><?php endif; ?></td>
          <td><?= e($r['category'] ?? '-') ?></td>
          <td><?= $scheduled ? '<span class="badge b-blue">Scheduled</span>'
                 : ($r['status'] === 'published' ? '<span class="badge b-green">Published</span>' : '<span class="badge b-grey">Draft</span>') ?></td>
          <td><?= e(date('d M Y, h:i A', strtotime($r['publish_at']))) ?></td>
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
    <?= pager_html($pg, ['q' => $q, 'status' => $fstat]) ?>
    <?php endif; ?>
  </div>

<?php else: /* ---------- add / edit form ---------- */ ?>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" enctype="multipart/form-data" class="card form">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

    <h2><?= $form['id'] ? 'Edit news' : 'Add news' ?></h2>

    <div class="grid2">
      <div class="field"><label>Title (English) *</label><input name="title_en" value="<?= e($form['title_en']) ?>" required maxlength="255"></div>
      <div class="field"><label>Title (हिन्दी)</label><input name="title_hi" value="<?= e($form['title_hi']) ?>" maxlength="255"></div>
    </div>

    <div class="grid3">
      <div class="field"><label>Category</label>
        <select name="category_id">
          <option value="">- none -</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $form['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name_en']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label>Publish date &amp; time</label>
        <input type="datetime-local" name="publish_at" value="<?= e(date('Y-m-d\TH:i', strtotime($form['publish_at']))) ?>">
        <span class="hint">Future date = scheduled</span></div>
      <div class="field"><label>Status</label>
        <select name="status">
          <option value="draft" <?= $form['status'] === 'draft' ? 'selected' : '' ?>>Draft (live nahi)</option>
          <option value="published" <?= $form['status'] === 'published' ? 'selected' : '' ?>>Published</option>
        </select></div>
    </div>

    <div class="grid2">
      <div class="field"><label>Short summary (English)</label><textarea name="summary_en" rows="2" maxlength="500"><?= e($form['summary_en']) ?></textarea></div>
      <div class="field"><label>Short summary (हिन्दी)</label><textarea name="summary_hi" rows="2" maxlength="500"><?= e($form['summary_hi']) ?></textarea></div>
    </div>

    <div class="grid2">
      <div class="field"><label>Full content (English)</label><textarea name="content_en" rows="10"><?= e($form['content_en']) ?></textarea></div>
      <div class="field"><label>Full content (हिन्दी)</label><textarea name="content_hi" rows="10"><?= e($form['content_hi']) ?></textarea></div>
    </div>
    <p class="hint">Content plain text hai: khali line chhodne se naya paragraph banta hai.</p>

    <div class="grid2">
      <div class="field"><label>Image (JPG / PNG / WebP, max 3 MB)</label>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
        <?php if ($form['image']): ?>
          <div class="preview"><img src="<?= e(upload_url($form['image'])) ?>" alt="">
            <label class="check"><input type="checkbox" name="remove_image" value="1"> Image hata do</label></div>
        <?php endif; ?></div>
      <div class="field"><label>URL slug</label>
        <input name="slug" value="<?= e($form['slug']) ?>" maxlength="200" placeholder="khali chhodo to title se ban jayega">
        <label class="check"><input type="checkbox" name="is_featured" value="1" <?= $form['is_featured'] ? 'checked' : '' ?>> Home page par featured</label></div>
    </div>

    <div class="form-actions">
      <button class="btn btn-amber" type="submit">Save</button>
      <a class="btn btn-ghost" href="news.php">Cancel</a>
    </div>
  </form>
<?php endif; ?>

<?php require ROOT_PATH . '/includes/admin_footer.php';
