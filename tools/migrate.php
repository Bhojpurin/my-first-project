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
    ['bus_trip_stops', 'eta_at', 'DATETIME NULL'],
    ['bus_trip_stops', 'eta_notified', 'TINYINT NOT NULL DEFAULT 0'],
    ['bus_trips', 'kind', 'VARCHAR(6) NULL'],
    ['student_home_locations', 'note', 'VARCHAR(120) NULL'],   // "mandir ke saamne, neela gate" — shown to the driver
];
foreach ($addCols as [$t, $c, $def]) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->execute([$t, $c]);
    if ((int)$q->fetchColumn()) { echo "✔ $t.$c exists\n"; continue; }
    try { $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def"); echo "✔ added $t.$c\n"; }
    catch (\Throwable $e) { echo "✖ $t.$c → ", $e->getMessage(), "\n"; }
}
// bus_route_learn got a "kind" (pickup/drop) column in its primary key
try {
    $q = $pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE()
                      AND TABLE_NAME='bus_route_learn' AND CONSTRAINT_NAME='PRIMARY' AND COLUMN_NAME='kind'");
    if (!(int)$q->fetchColumn()) {
        $c = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bus_route_learn' AND COLUMN_NAME='kind'");
        if (!(int)$c->fetchColumn()) $pdo->exec("ALTER TABLE bus_route_learn ADD COLUMN kind VARCHAR(6) NOT NULL DEFAULT 'any' AFTER shift_no");
        $pdo->exec("ALTER TABLE bus_route_learn DROP PRIMARY KEY, ADD PRIMARY KEY (bus_id, shift_no, kind)");
        echo "✔ bus_route_learn primary key upgraded\n";
    }
} catch (\Throwable $e) { echo "✖ bus_route_learn key → ", $e->getMessage(), "\n"; }

echo "Done.\n";
