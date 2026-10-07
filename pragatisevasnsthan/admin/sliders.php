<?php
/** admin/sliders.php - Home page hero (the lowest sort order active slide is shown). */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/crud.php';

crud_run([
    'table' => 'sliders', 'perm' => 'sliders.manage', 'file' => 'sliders.php', 'title' => 'Home slider', 'noun' => 'Slide',
    'image_dir' => 'sliders', 'image_required' => true, 'order' => 'sort_order, id',
    'fields' => [
        ['title_en', 'Heading (English)', 'text', ['required' => true]],
        ['title_hi', 'Heading (हिन्दी)', 'text'],
        ['subtitle_en', 'Sub-text (English)', 'text', ['max' => 300]],
        ['subtitle_hi', 'Sub-text (हिन्दी)', 'text', ['max' => 300]],
        ['link_url', 'Button link', 'text', ['hint' => 'https://... ya khali', 'check' => fn($v) =>
            $v === '' || preg_match('#^(https?://|/)#i', $v) ? null : 'Link http(s):// ya / se shuru hona chahiye.']],
        ['button_text', 'Button text', 'text', ['max' => 60]],
        ['sort_order', 'Order (chhota number pehle)', 'number'],
        ['status', 'Status', 'select', ['options' => ['active' => 'Active', 'inactive' => 'Inactive']]],
    ],
    'list' => [
        ['Heading', fn($r) => '<strong>' . e($r['title_en']) . '</strong>' . ($r['title_hi'] ? '<br><span class="muted">' . e($r['title_hi']) . '</span>' : '')],
        ['Order', fn($r) => (int) $r['sort_order']],
        ['Status', fn($r) => crud_badge($r['status'])],
    ],
]);
