<?php
/** admin/video.php - YouTube videos shown on videos.php */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/crud.php';

crud_run([
    'table' => 'video_items', 'perm' => 'video.manage', 'file' => 'video.php', 'title' => 'Video', 'noun' => 'Video',
    'order' => 'sort_order, id DESC',
    'fields' => [
        ['title_en', 'Title (English)', 'text', ['required' => true]],
        ['title_hi', 'Title (हिन्दी)', 'text'],
        ['youtube_id', 'YouTube video ID', 'text', ['required' => true, 'max' => 11, 'hint' => 'Link me v= ke baad ke 11 akshar, jaise dQw4w9WgXcQ',
            'check' => fn($v) => preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $v) ? null : 'YouTube ID 11 akshar ki honi chahiye (poora link nahi).']],
        ['category', 'Category', 'text', ['max' => 80]],
        ['sort_order', 'Order (chhota number pehle)', 'number'],
        ['status', 'Status', 'select', ['options' => ['published' => 'Published', 'hidden' => 'Hidden']]],
    ],
    'list' => [
        ['Title', fn($r) => '<strong>' . e($r['title_en']) . '</strong>'],
        ['YouTube', fn($r) => '<a target="_blank" rel="noopener" href="https://www.youtube.com/watch?v=' . e($r['youtube_id']) . '">' . e($r['youtube_id']) . '</a>'],
        ['Status', fn($r) => crud_badge($r['status'])],
    ],
]);
