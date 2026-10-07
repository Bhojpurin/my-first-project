<?php
/** admin/trustees.php - Trustees / Team members shown on public team.php */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/crud.php';

crud_run([
    'table' => 'trustees', 'perm' => 'trustees.manage', 'file' => 'trustees.php', 'title' => 'Trustees / Team', 'noun' => 'Member',
    'upload' => ['dir' => 'trustees', 'col' => 'photo', 'label' => 'Photo (JPG / PNG / WebP, max 3 MB)'],
    'order' => 'sort_order, id',
    'fields' => [
        ['name', 'Name', 'text', ['required' => true, 'max' => 120]],
        ['designation_en', 'Designation (English)', 'text', ['max' => 120]],
        ['designation_hi', 'Designation (हिन्दी)', 'text', ['max' => 120]],
        ['bio_en', 'About (English)', 'textarea', ['rows' => 4, 'max' => 3000]],
        ['bio_hi', 'About (हिन्दी)', 'textarea', ['rows' => 4, 'max' => 3000]],
        ['sort_order', 'Order (chhota number pehle)', 'number'],
        ['status', 'Status', 'select', ['options' => ['active' => 'Active', 'inactive' => 'Inactive']]],
    ],
    'list' => [
        ['Name', fn($r) => '<strong>' . e($r['name']) . '</strong>'],
        ['Designation', fn($r) => e($r['designation_en'] ?? '')],
        ['Order', fn($r) => (int) $r['sort_order']],
        ['Status', fn($r) => crud_badge($r['status'])],
    ],
]);
