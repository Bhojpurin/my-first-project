<?php
/** admin/financial_docs.php - audit report, balance sheet, ITR, Form 10B ... (PDF) shown on transparency.php */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/crud.php';

crud_run([
    'table' => 'financial_docs', 'perm' => 'financial_docs.manage', 'file' => 'financial_docs.php', 'title' => 'Financial documents', 'noun' => 'Document',
    'upload' => ['dir' => 'finance', 'col' => 'file_path', 'exts' => ['pdf'], 'required' => true, 'maxbytes' => 10485760, 'label' => 'PDF file (max 10 MB)'],
    'order' => 'fy DESC, id DESC',
    'fields' => [
        ['title_en', 'Title (English)', 'text', ['required' => true]],
        ['title_hi', 'Title (हिन्दी)', 'text'],
        ['doc_type', 'Type', 'select', ['options' => [
            'audit_report' => 'Audit report', 'balance_sheet' => 'Balance sheet', 'income_expenditure' => 'Income & expenditure',
            'itr' => 'Income-tax return (ITR)', 'form_10b' => 'Form 10B', 'utilization_certificate' => 'Utilization certificate',
            'annual_report' => 'Annual report', 'other' => 'Other']]],
        ['fy', 'Financial year', 'text', ['required' => true, 'max' => 9, 'hint' => 'Format: 2025-26',
            'check' => fn($v) => preg_match('/^\d{4}-\d{2}$/', (string) $v) ? null : 'Financial year 2025-26 jaise format me likhein.']],
        ['status', 'Status', 'select', ['options' => ['published' => 'Published', 'hidden' => 'Hidden']]],
    ],
    'list' => [
        ['Title', fn($r) => '<strong>' . e($r['title_en']) . '</strong>'],
        ['FY', fn($r) => e($r['fy'])],
        ['Type', fn($r) => e(str_replace('_', ' ', $r['doc_type']))],
        ['File', fn($r) => '<a target="_blank" href="' . e(upload_url($r['file_path'])) . '">PDF</a>'],
        ['Status', fn($r) => crud_badge($r['status'])],
    ],
]);
