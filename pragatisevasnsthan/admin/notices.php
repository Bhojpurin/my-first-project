<?php
/** admin/notices.php - notices, forms, brochures (PDF) shown on notices.php */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/crud.php';

crud_run([
    'table' => 'notices', 'perm' => 'notices.manage', 'file' => 'notices.php', 'title' => 'Notices / Downloads', 'noun' => 'Notice',
    'upload' => ['dir' => 'notices', 'col' => 'file_path', 'exts' => ['pdf'], 'required' => true, 'maxbytes' => 10485760, 'label' => 'PDF file (max 10 MB)'],
    'order' => 'notice_date DESC, id DESC',
    'fields' => [
        ['title_en', 'Title (English)', 'text', ['required' => true]],
        ['title_hi', 'Title (हिन्दी)', 'text'],
        ['notice_date', 'Date', 'date', ['required' => true, 'default' => date('Y-m-d')]],
        ['status', 'Status', 'select', ['options' => ['published' => 'Published', 'hidden' => 'Hidden']]],
    ],
    'list' => [
        ['Title', fn($r) => '<strong>' . e($r['title_en']) . '</strong>'],
        ['Date', fn($r) => e($r['notice_date'])],
        ['File', fn($r) => '<a target="_blank" href="' . e(upload_url($r['file_path'])) . '">PDF</a>'],
        ['Status', fn($r) => crud_badge($r['status'])],
    ],
]);
