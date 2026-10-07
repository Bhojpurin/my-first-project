<?php
/**
 * public_header.php - top of every public page.
 * Before including set:  $page_title (optional), $meta_desc (optional), $nav_active ('home','news',...)
 */
require_once ROOT_PATH . '/includes/lang.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/upload.php';

$lang        = lang();
$site_name   = $lang === 'hi' ? (setting('site_name_hi') ?: setting('site_name_en')) : setting('site_name_en');
$page_title  = isset($page_title) && $page_title !== '' ? $page_title . ' | ' . $site_name : $site_name;
$meta_desc   = $meta_desc ?? setting('site_tagline');
$nav_active  = $nav_active ?? '';
$nav = [
    'home'      => ['index.php',     t('home')],
    'about'     => ['page.php?slug=about', t('about')],
    'campaigns' => ['campaigns.php', t('campaigns')],
    'news'      => ['news.php',      t('news')],
    'contact'   => ['page.php?slug=contact', t('contact')],
];
// language switch keeps the same page: ?lang= is added to the current query
$other  = $lang === 'hi' ? 'en' : 'hi';
$q      = $_GET; $q['lang'] = $other;
$switch = strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?' . http_build_query($q);
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?></title>
  <?php if ($meta_desc !== ''): ?><meta name="description" content="<?= e($meta_desc) ?>"><?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Inter:wght@400;500;600&family=Noto+Sans+Devanagari:wght@400;600&display=swap">
  <link rel="stylesheet" href="<?= e(url('assets/css/theme.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/site.css')) ?>">
</head>
<body class="site">
<header class="s-head">
  <div class="wrap s-head-in">
    <a class="brand" href="<?= e(url('index.php')) ?>"><span class="brand-mark">प्र</span><span><?= e($site_name) ?></span></a>
    <input type="checkbox" id="navtoggle" class="navtoggle" hidden>
    <label for="navtoggle" class="burger" aria-label="Menu">☰</label>
    <nav class="s-nav">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= $nav_active === $key ? 'on' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="lang" href="<?= e($switch) ?>"><?= $lang === 'hi' ? 'EN' : 'हिं' ?></a>
      <a class="btn btn-amber" href="<?= e(url('donate.php')) ?>"><?= e(t('donate_now')) ?></a>
    </nav>
  </div>
</header>
<main class="s-main">
