<?php
// tools/migrate.php — creates / upgrades the bus tracker tables by hand and prints every step.
// Normally NOT needed: the module upgrades itself on the first request after a deploy (includes/bus_schema.php).
//   php tools/migrate.php            (XAMPP: C:\\xampp\\php\\php.exe tools\\migrate.php)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bus_schema.php';
$pdo = Database::connect();
$ok = busRunMigration($pdo, function ($l) { echo $l, "\n"; });
if ($ok) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bus_schema_version (v INT NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB");
    $pdo->exec("DELETE FROM bus_schema_version");
    $pdo->prepare("INSERT INTO bus_schema_version (v, updated_at) VALUES (?, NOW())")->execute([BUS_SCHEMA_VERSION]);
}
echo $ok ? "Done (schema v" . BUS_SCHEMA_VERSION . ").\n" : "Some steps failed — see ✖ lines above.\n";
exit($ok ? 0 : 1);
