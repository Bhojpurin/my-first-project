<?php
/** receipt.php?c=<verify code> - printable donation receipt (Print -> Save as PDF). Also works as public verification. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/forms.php';
require_once ROOT_PATH . '/includes/donations.php';

$r = receipt_by_code(get_str('c', 16));
if (!$r) { http_response_code(404); echo 'Receipt not found.'; exit; }
header('X-Robots-Tag: noindex, nofollow');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>Receipt <?= e($r['receipt_no']) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/receipt.css')) ?>"></head>
<body>
<div class="bar no-print"><button onclick="window.print()">🖨 Print / Save as PDF</button></div>
<?= receipt_html($r) ?>
</body></html>
