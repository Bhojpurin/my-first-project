<?php
/**
 * Create the first Super Admin (run ONCE from command line).
 *
 *   XAMPP/Windows:  C:\xampp\php\php.exe cli\create_super_admin.php
 *   Linux/VPS:      php cli/create_super_admin.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

function ask(string $label): string { echo $label . ': '; return trim((string) fgets(STDIN)); }

echo "=== Create Super Admin ===\n";
$name  = ask('Full name');
$email = strtolower(ask('Email'));
$pass  = ask('Password (min 10 chars; typing will be visible)');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) exit("Invalid name/email.\n");
if (strlen($pass) < 10) exit("Password must be at least 10 characters.\n");

$exists = db_value('SELECT COUNT(*) FROM users WHERE email = ?', [$email]);
if ($exists) exit("This email already exists.\n");

db_query(
    "INSERT INTO users (role_id, name, email, password_hash)
     SELECT id, ?, ?, ? FROM roles WHERE slug = 'super_admin'",
    [$name, $email, hash_password($pass)]
);
echo "Done. Super Admin created: $email\nLogin: " . url('admin/login.php') . "\n";
