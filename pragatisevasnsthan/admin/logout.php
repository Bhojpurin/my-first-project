<?php
/** admin/logout.php - POST only (so a stray link cannot log you out). */
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/index.php');
csrf_check();
auth_logout();
flash('success', 'Aap logout ho gaye.');
redirect('admin/login.php');
