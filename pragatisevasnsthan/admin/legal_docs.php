<?php
/** admin/legal_docs.php - registration, 12A, 80G, PAN, Darpan, CSR-1, FCRA certificates */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/crud.php';

crud_run([
    'table' => 'legal_docs', 'perm' => 'legal_docs.manage', 'file' => 'legal_docs.php', 'title' => 'Legal documents', 'noun' => 'Document',
    'upload' => ['dir' => 'legal', 'col' => 'file_path', 'exts' => ['pdf'], 'required' => false, 'maxbytes' => 10485760, 'label' => 'PDF certificate (max 10 MB)'],
    'order' => 'sort_order, id',
    'fields' => [
        ['doc_type', 'Type', 'select', ['options' => ['registration' => 'Registration', '12a' => '12A', '80g' => '80G', 'pan' => 'PAN',
            'darpan' => 'NITI Aayog Darpan', 'csr1' => 'CSR-1', 'fcra' => 'FCRA (sirf agar lagu ho)', 'other' => 'Other']]],
        ['title_en', 'Title (English)', 'text', ['required' => true]],
        ['title_hi', 'Title (हिन्दी)', 'text'],
        ['doc_no', 'Number / ID', 'text', ['max' => 80]],
        ['valid_from', 'Valid from', 'date'],
        ['valid_to', 'Valid to', 'date'],
        ['sort_order', 'Order (chhota number pehle)', 'number'],
        ['show_in_footer', 'Footer me number dikhayein', 'checkbox'],
        ['status', 'Status', 'select', ['options' => ['published' => 'Published', 'hidden' => 'Hidden']]],
    ],
    'list' => [
        ['Document', fn($r) => '<strong>' . e($r['title_en']) . '</strong>'],
        ['Type', fn($r) => e($r['doc_type'])],
        ['Number', fn($r) => e($r['doc_no'] ?? '')],
        ['Valid to', fn($r) => e($r['valid_to'] ?? '-')],
        ['Status', fn($r) => crud_badge($r['status'])],
    ],
]);
