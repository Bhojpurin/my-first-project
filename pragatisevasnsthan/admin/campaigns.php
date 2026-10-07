<?php
/** admin/campaigns.php - fundraising campaigns shown on Home, Campaigns page and Donate page. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/crud.php';

crud_run([
    'table' => 'campaigns', 'perm' => 'campaigns.manage', 'file' => 'campaigns.php', 'title' => 'Campaigns', 'noun' => 'Campaign',
    'image_dir' => 'campaigns', 'slug_from' => 'title_en', 'order' => 'sort_order, id DESC',
    'fields' => [
        ['title_en', 'Title (English)', 'text', ['required' => true]],
        ['title_hi', 'Title (हिन्दी)', 'text'],
        ['summary_en', 'Short summary (English)', 'text', ['max' => 500]],
        ['summary_hi', 'Short summary (हिन्दी)', 'text', ['max' => 500]],
        ['description_en', 'Description (English)', 'textarea', ['rows' => 6]],
        ['description_hi', 'Description (हिन्दी)', 'textarea', ['rows' => 6]],
        ['target_amount', 'Target amount (₹)', 'number'],
        ['sort_order', 'Order (chhota number pehle)', 'number'],
        ['start_date', 'Start date', 'date'],
        ['end_date', 'End date', 'date'],
        ['status', 'Status', 'select', ['options' => ['draft' => 'Draft', 'active' => 'Active', 'completed' => 'Completed', 'closed' => 'Closed']]],
        ['is_featured', 'Home page par featured', 'checkbox'],
    ],
    'list' => [
        ['Campaign', fn($r) => '<strong>' . e($r['title_en']) . '</strong>' . ($r['is_featured'] ? ' <span class="badge b-amber">Featured</span>' : '')],
        ['Target', fn($r) => e(inr($r['target_amount']))],
        ['Raised', fn($r) => e(inr($r['raised_cache']))],
        ['Status', fn($r) => crud_badge($r['status'])],
    ],
]);
