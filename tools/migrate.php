<?php
// tools/migrate.php — creates / upgrades the bus tracker tables. Safe to run any number of times.
//   php tools/migrate.php            (XAMPP: C:\\xampp\\php\\php.exe tools\\migrate.php)
// Runs database/bus_tracking.sql, then adds columns that newer versions need on tables created earlier
// (works on MySQL and MariaDB — no "ADD COLUMN IF NOT EXISTS" needed).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/../database/bus_tracking.sql'));
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    try { $pdo->exec($stmt); echo '✔ ', strtok($stmt, "\n"), "\n"; }
    catch (\Throwable $e) { echo '✖ ', strtok($stmt, "\n"), ' → ', $e->getMessage(), "\n"; }
}

$addCols = [
    ['bus_live', 'still_since', 'DATETIME NULL'],
    ['bus_gps_locations', 'accuracy', 'DECIMAL(8,2) NULL'],
];
foreach ($addCols as [$t, $c, $def]) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->execute([$t, $c]);
    if ((int)$q->fetchColumn()) { echo "✔ $t.$c exists\n"; continue; }
    try { $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def"); echo "✔ added $t.$c\n"; }
    catch (\Throwable $e) { echo "✖ $t.$c → ", $e->getMessage(), "\n"; }
}
echo "Done.\n";
