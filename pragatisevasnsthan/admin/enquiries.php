<?php
/** admin/enquiries.php - messages from the public Contact form */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/inbox.php';

inbox_run([
    'table' => 'enquiries', 'perm' => 'enquiries.manage', 'file' => 'enquiries.php', 'title' => 'Enquiries', 'noun' => 'Enquiry',
    'statuses' => ['new', 'replied', 'closed'], 'search' => ['name', 'email', 'mobile', 'subject', 'message'],
    'list' => [
        ['From', fn($r) => '<strong>' . e($r['name']) . '</strong><br><span class="muted">' . e($r['mobile'] ?: $r['email']) . '</span>'],
        ['Subject', fn($r) => e(str_limit((string) ($r['subject'] ?: $r['message']), 60))],
    ],
    'detail' => ['name' => 'Name', 'email' => 'Email', 'mobile' => 'Mobile', 'subject' => 'Subject', 'message' => 'Message', 'ip' => 'IP'],
    'auto_seen' => false,
]);
