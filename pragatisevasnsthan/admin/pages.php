<?php
/** admin/pages.php - CMS pages (About, Contact, Privacy, Refund, Terms ...). Public: page.php?slug=... */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/crud.php';

crud_run([
    'table' => 'pages', 'perm' => 'pages.manage', 'file' => 'pages.php', 'title' => 'Pages', 'noun' => 'Page',
    'slug_from' => 'title_en', 'order' => 'title_en',
    'fields' => [
        ['title_en', 'Title (English)', 'text', ['required' => true]],
        ['title_hi', 'Title (हिन्दी)', 'text'],
        ['content_en', 'Content (English)', 'textarea', ['rows' => 10, 'hint' => 'Plain text: khali line = naya paragraph.']],
        ['content_hi', 'Content (हिन्दी)', 'textarea', ['rows' => 10]],
        ['meta_title', 'SEO title', 'text', ['max' => 160]],
        ['meta_desc', 'SEO description', 'text', ['max' => 300]],
        ['status', 'Status', 'select', ['options' => ['draft' => 'Draft (live nahi)', 'published' => 'Published']]],
    ],
    'list' => [
        ['Title', fn($r) => '<strong>' . e($r['title_en']) . '</strong>' . ($r['title_hi'] ? '<br><span class="muted">' . e($r['title_hi']) . '</span>' : '')],
        ['Slug', fn($r) => '<code>' . e($r['slug']) . '</code>'],
        ['Status', fn($r) => crud_badge($r['status'])],
    ],
]);
