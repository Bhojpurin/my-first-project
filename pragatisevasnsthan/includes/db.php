<?php
/**
 * db.php - one shared PDO connection + 3 tiny query helpers.
 * Always use prepared statements: db()->prepare('... WHERE id = ?')
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . env('DB_HOST', '127.0.0.1')
             . ';port=' . env('DB_PORT', '3306')
             . ';dbname=' . env('DB_NAME', '')
             . ';charset=utf8mb4';
        $pdo = new PDO($dsn, (string) env('DB_USER', 'root'), (string) env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+05:30'");   // same as PHP Asia/Kolkata
    }
    return $pdo;
}

/** Run a query, return PDOStatement. */
function db_query(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** First column of first row (COUNT, SUM ...). */
function db_value(string $sql, array $params = [])
{
    return db_query($sql, $params)->fetchColumn();
}

/** All rows. */
function db_rows(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}
