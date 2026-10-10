<?php // TEST STUB: records pushes instead of sending them
function sendProximityPush(array $sub, array $payload): array {
    file_put_contents(getenv('BUS_TEST_PUSHLOG') ?: sys_get_temp_dir() . '/bus_push.log', json_encode(['endpoint' => $sub['endpoint']] + $payload) . "\n", FILE_APPEND);
    return ['ok' => true];
}
