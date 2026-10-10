<?php
// includes/bus_schema.php — the bus module creates / upgrades its own tables. No command line needed:
// the first request after a deploy (admin opening Bus Tracker, a driver phone, a GPS device, the cron) runs it.
//
// • A version number in bus_schema_version says whether anything is to do — normal requests cost one tiny query.
// • GET_LOCK makes sure only one request migrates even if many arrive at the same moment.
// • Every step is idempotent (CREATE IF NOT EXISTS, columns added only when missing), so a half-finished run
//   simply continues next time. The version is only raised when every step succeeded.
// • Bump BUS_SCHEMA_VERSION whenever database/bus_tracking.sql or the column list below changes.

const BUS_SCHEMA_VERSION = 8;

/** Columns added after the first release (tables created by older versions get them here). */
const BUS_SCHEMA_ADD_COLUMNS = [
    ['bus_live', 'still_since', 'DATETIME NULL'],
    ['bus_trip_stops', 'eta_at', 'DATETIME NULL'],
    ['bus_trip_stops', 'eta_notified', 'TINYINT NOT NULL DEFAULT 0'],
    ['bus_trips', 'kind', 'VARCHAR(6) NULL'],
    ['bus_alert_settings', 'halt_ask_min', 'SMALLINT NOT NULL DEFAULT 3'],
    ['bus_alert_settings', 'halt_admin_min', 'SMALLINT NOT NULL DEFAULT 8'],
    ['bus_alerts', 'ref_id', 'INT NULL'],
    ['bus_alerts', 'broadcast_at', 'DATETIME NULL'],
    ['bus_alerts', 'broadcast_n', 'INT NOT NULL DEFAULT 0'],
    ['bus_alert_state', 'halt_alerted_for', 'DATETIME NULL'],
    ['student_home_locations', 'note', 'VARCHAR(120) NULL'],          // landmark for the driver
    ['student_home_locations', 'bus_lost_at', 'DATETIME NULL'],       // privacy grace period
];

function busColumnExists(PDO $pdo, string $t, string $c): bool
{
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->execute([$t, $c]);
    return (bool)$q->fetchColumn();
}

/**
 * Run every migration step. $log receives one line per step (✔ / ✖). Returns true when all steps succeeded.
 */
function busRunMigration(PDO $pdo, ?callable $log = null): bool
{
    $log = $log ?: function ($l) {};
    $ok = true;
    $mode = $pdo->getAttribute(PDO::ATTR_ERRMODE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        // The panel's own GPS table needs "accuracy" before bus_live is filled from it
        try {
            if (!busColumnExists($pdo, 'bus_gps_locations', 'accuracy')) { $pdo->exec("ALTER TABLE `bus_gps_locations` ADD COLUMN `accuracy` DECIMAL(8,2) NULL"); $log('✔ added bus_gps_locations.accuracy'); }
        } catch (\Throwable $e) { $ok = false; $log('✖ bus_gps_locations.accuracy → ' . $e->getMessage()); }

        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents(__DIR__ . '/../database/bus_tracking.sql'));
        if ($sql === '') { $log('✖ database/bus_tracking.sql not found'); return false; }
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            try { $pdo->exec($stmt); $log('✔ ' . strtok($stmt, "\n")); }
            catch (\Throwable $e) { $ok = false; $log('✖ ' . strtok($stmt, "\n") . ' → ' . $e->getMessage()); }
        }
        foreach (BUS_SCHEMA_ADD_COLUMNS as [$t, $c, $def]) {
            try {
                if (busColumnExists($pdo, $t, $c)) { $log("✔ $t.$c exists"); continue; }
                $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
                $log("✔ added $t.$c");
            } catch (\Throwable $e) { $ok = false; $log("✖ $t.$c → " . $e->getMessage()); }
        }
        // bus_route_learn got a "kind" (pickup/drop) column in its primary key
        try {
            $q = $pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE()
                              AND TABLE_NAME='bus_route_learn' AND CONSTRAINT_NAME='PRIMARY' AND COLUMN_NAME='kind'");
            if (!(int)$q->fetchColumn()) {
                if (!busColumnExists($pdo, 'bus_route_learn', 'kind')) $pdo->exec("ALTER TABLE bus_route_learn ADD COLUMN kind VARCHAR(6) NOT NULL DEFAULT 'any' AFTER shift_no");
                $pdo->exec("ALTER TABLE bus_route_learn DROP PRIMARY KEY, ADD PRIMARY KEY (bus_id, shift_no, kind)");
                $log('✔ bus_route_learn primary key upgraded');
            }
        } catch (\Throwable $e) { $ok = false; $log('✖ bus_route_learn key → ' . $e->getMessage()); }
    } finally {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, $mode);
    }
    return $ok;
}

/** Make sure the bus tables are up to date. Cheap when they are (one query per request). Never throws. */
function busEnsureSchema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $v = (int)$pdo->query("SELECT MAX(v) FROM bus_schema_version")->fetchColumn();
        if ($v >= BUS_SCHEMA_VERSION) return;
    } catch (\Throwable $e) { /* table missing → first run */ }

    try {
        if ((int)$pdo->query("SELECT GET_LOCK('bus_schema_migrate', 25)")->fetchColumn() !== 1) return;   // someone else is on it
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS bus_schema_version (v INT NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB");
            $v = (int)$pdo->query("SELECT MAX(v) FROM bus_schema_version")->fetchColumn();
            if ($v >= BUS_SCHEMA_VERSION) return;                                       // done while we waited
            $lines = [];
            if (busRunMigration($pdo, function ($l) use (&$lines) { $lines[] = $l; })) {
                $pdo->exec("DELETE FROM bus_schema_version");
                $pdo->prepare("INSERT INTO bus_schema_version (v, updated_at) VALUES (?, NOW())")->execute([BUS_SCHEMA_VERSION]);
                error_log('bus_schema: upgraded to v' . BUS_SCHEMA_VERSION);
            } else {
                error_log("bus_schema: migration incomplete —\n" . implode("\n", array_filter($lines, function ($l) { return strpos($l, '✖') === 0; })));
            }
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('bus_schema_migrate')");
        }
    } catch (\Throwable $e) {
        error_log('bus_schema: ' . $e->getMessage());
    }
}
