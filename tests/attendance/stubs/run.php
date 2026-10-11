<?php // php run.php <file> <post-json>
$_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_POST = json_decode($argv[2], true) + ['csrf_token' => 'ok'];
require __DIR__ . '/' . $argv[1];
