<?php
/**
 * Usage:
 *   php check_sim_events.php config.php
 *   php check_sim_events.php config-node2.php
 */
declare(strict_types=1);

$configName = $argv[1] ?? 'config.php';
$configFile = __DIR__ . DIRECTORY_SEPARATOR . $configName;

if (!is_file($configFile)) {
    fwrite(STDERR, "Missing config: {$configFile}\n");
    exit(1);
}

$config = require $configFile;
$dbPath = (string)($config['db_path'] ?? '');
echo "Config: {$configName}\n";
echo "DB: {$dbPath}\n";

if ($dbPath === '' || !is_file($dbPath)) {
    fwrite(STDERR, "Database file not found.\n");
    exit(1);
}

$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$total = (int)$db->query('SELECT COUNT(*) FROM sync_events')->fetchColumn();
echo "Total sync_events: {$total}\n";

echo "sim_message / sync-a* rows:\n";
$stmt = $db->query("
    SELECT sync_id, kind
    FROM sync_events
    WHERE kind = 'sim_message' OR sync_id LIKE 'sync-a%'
    ORDER BY rowid
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($rows === []) {
    echo "  (none)\n";
} else {
    foreach ($rows as $row) {
        echo "  {$row['sync_id']}  {$row['kind']}\n";
    }
}
