<?php
/** admin/volunteers.php - volunteer sign-ups from the public Volunteer form */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once ROOT_PATH . '/includes/inbox.php';

inbox_run([
    'table' => 'volunteers', 'perm' => 'volunteers.manage', 'file' => 'volunteers.php', 'title' => 'Volunteers', 'noun' => 'Volunteer',
    'statuses' => ['new', 'contacted', 'active', 'inactive'], 'search' => ['name', 'mobile', 'email', 'city', 'skills'],
    'list' => [
        ['Name', fn($r) => '<strong>' . e($r['name']) . '</strong><br><span class="muted">' . e($r['mobile']) . '</span>'],
        ['City', fn($r) => e($r['city'] ?? '')],
        ['Skills', fn($r) => e(str_limit((string) $r['skills'], 50))],
    ],
    'detail' => ['name' => 'Name', 'mobile' => 'Mobile', 'email' => 'Email', 'city' => 'City', 'skills' => 'Skills', 'availability' => 'Availability', 'message' => 'Message'],
]);
