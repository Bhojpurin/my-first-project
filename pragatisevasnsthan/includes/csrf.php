<?php
/**
 * csrf.php - CSRF protection.
 * In every POST form:   <?= csrf_field() ?>
 * At top of POST handler: csrf_check();
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = (string) ($_POST['_csrf'] ?? '');
    if ($sent === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $sent)) {
        http_response_code(419);
        exit('Session expire ho gaya ya request galat hai. Page refresh karke dobara try karein.');
    }
}
