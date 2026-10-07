<?php
/**
 * admin_header.php - top of every admin page (sidebar + topbar).
 * Before including set:  $page_title = 'Dashboard';  $active = 'index.php';
 * Menu items marked 'ready' => false show as "soon" until that page is built.
 */
$u = current_user();
$page_title = $page_title ?? 'Admin';
$active = $active ?? '';

// [label, file, permission, ready?]  - a string row is a group heading
$menu = [
    ['Dashboard', 'index.php', 'dashboard.view', true],
    'Website content',
    ['Pages',            'pages.php',    'pages.manage',    true],
    ['Home slider',      'sliders.php',  'sliders.manage',  true],
    ['Trustees / Team',  'trustees.php', 'trustees.manage', true],
    ['News / Current Affairs', 'news.php', 'news.manage',   true],
    ['Trust Offers',     'offers.php',   'offers.manage',   false],
    ['Audio',            'audio.php',    'audio.manage',    false],
    ['Video',            'video.php',    'video.manage',    true],
    ['Gallery',          'gallery.php',  'gallery.manage',  false],
    ['Notices',          'notices.php',  'notices.manage',  true],
    'Transparency',
    ['Financial documents', 'financial_docs.php', 'financial_docs.manage', true],
    ['Legal documents',     'legal_docs.php',     'legal_docs.manage',     true],
    'Donations',
    ['Campaigns',        'campaigns.php', 'campaigns.manage', true],
    ['Donations',        'donations.php', 'donations.view',   true],
    'Requests',
    ['Offer applications', 'applications.php', 'offer_applications.manage', false],
    ['Enquiries',        'enquiries.php',  'enquiries.manage',  true],
    ['Volunteers',       'volunteers.php', 'volunteers.manage', true],
    'System',
    ['Admin users',      'users.php',    'users.manage',    false],
    ['Settings',         'settings.php', 'settings.manage', true],
    ['Activity log',     'audit.php',    'audit.view',      false],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page_title) ?> · Admin</title>
<link rel="stylesheet" href="<?= e(url('assets/css/theme.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body>
<div class="shell">
  <aside class="sidebar">
    <div class="brand"><span class="logo-mark">🌱</span> <?= e(setting('site_name_en', 'Pragati Seva Sansthan')) ?></div>
    <?php
    $pendingGroup = null;
    foreach ($menu as $item) {
        if (is_string($item)) { $pendingGroup = $item; continue; }   // remember heading, print only if a child is visible
        [$label, $file, $perm, $ready] = $item;
        if (!can($perm)) continue;
        if ($pendingGroup !== null) { echo '<div class="group">' . e($pendingGroup) . '</div>'; $pendingGroup = null; }
        if ($ready) {
            echo '<a href="' . e(url('admin/' . $file)) . '" class="' . ($active === $file ? 'active' : '') . '">' . e($label) . '</a>';
        } else {
            echo '<span class="soon">' . e($label) . ' <em>soon</em></span>';
        }
    }
    ?>
  </aside>
  <div class="main">
    <header class="topbar">
      <h1><?= e($page_title) ?></h1>
      <div class="userbox">
        <span><?= e($u['name'] ?? '') ?></span>
        <span class="role"><?= e($u['role_name'] ?? '') ?></span>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-ghost" type="submit">Logout</button>
        </form>
      </div>
    </header>
    <main class="content">
      <?php foreach (flash_get() as [$type, $msg]): ?>
        <div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div>
      <?php endforeach; ?>
