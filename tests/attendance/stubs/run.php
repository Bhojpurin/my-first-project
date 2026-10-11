<?php // php run.php <file> <post-json>   (env SESS = session json, GATE = X-Gate-Token header)
$_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
if (getenv('GATE')) $_SERVER['HTTP_X_GATE_TOKEN'] = getenv('GATE');
$_POST = json_decode($argv[2], true) + ['csrf_token' => 'ok'];
require __DIR__ . '/' . $argv[1];
