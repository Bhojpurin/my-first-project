<?php
/**
 * admin/login.php - login page for Super Admin and Admin.
 * Tables: users, roles, login_attempts, audit_log
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Cache-Control: no-store');

if (current_user()) redirect('admin/index.php');

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string) ($_POST['email'] ?? ''));
    [$ok, $msg] = auth_login($email, (string) ($_POST['password'] ?? ''));
    if ($ok) redirect('admin/index.php');
    $error = $msg;
}
$siteName = setting('site_name_en', 'Pragati Seva Sansthan');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login · <?= e($siteName) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/theme.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body>
<div class="login-wrap">
  <section class="login-brand">
    <div class="logo"><span class="logo-mark">🌱</span> <?= e($siteName) ?></div>
    <div>
      <h1>Pragati se service tak. Seva se shakti tak.</h1>
      <p>Trust management panel: content, donations, receipts aur transparency, sab ek jagah.</p>
    </div>
    <small>Authorised users only. Har login activity record hoti hai.</small>
  </section>

  <section class="login-form-side">
    <div class="login-card">
      <h2>Admin sign in</h2>
      <p class="muted">Apna email aur password daalein.</p>

      <?php foreach (flash_get() as [$type, $msg]): ?>
        <div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div>
      <?php endforeach; ?>
      <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" value="<?= e($email) ?>" required autofocus>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-amber" type="submit">Sign in</button>
      </form>
    </div>
  </section>
</div>
</body>
</html>
