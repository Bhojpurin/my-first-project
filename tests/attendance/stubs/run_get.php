<?php // php run_get.php <file> <query-string>   prints body to stdout, "HTTP <code>" to stderr
$_SERVER['REQUEST_METHOD'] = 'GET';
parse_str((string)($argv[2] ?? ''), $_GET);
register_shutdown_function(fn() => fwrite(STDERR, 'HTTP ' . (http_response_code() ?: 200)));
require __DIR__ . '/' . $argv[1];
