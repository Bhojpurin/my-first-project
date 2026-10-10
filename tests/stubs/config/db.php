<?php // TEST STUB — the real panel has its own config/db.php
class Database {
    private static $pdo = null;
    public static function connect(): PDO {
        if (!self::$pdo) {
            self::$pdo = new PDO('mysql:host=localhost;dbname=' . (getenv('BUS_TEST_DB') ?: 'sszone') . ';charset=utf8mb4',
                getenv('BUS_TEST_USER') ?: 'ss', getenv('BUS_TEST_PASS') ?: 'ss',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        }
        return self::$pdo;
    }
}
