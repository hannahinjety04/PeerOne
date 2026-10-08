<?php
/**
 * Load all lineages into RAM and show memory usage.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/dbconnect.php';

$pdo = db();

echo "Loading lineages into memory...\n";
$before = memory_get_usage(true);

$rows = $pdo->query(
    'SELECT lineageID, clientID FROM Lineages'
)->fetchAll(PDO::FETCH_ASSOC);

if ($rows === false) {
    fwrite(STDERR, "Query failed.\n");
    exit(1);
}

if ($rows === []) {
    echo "No lineages found. Run seed_lineages_test.php or seed_lineages_1m.php first.\n";
    exit(1);
}

// Memory representation: two maps for fast lookup
$byLineageId = [];
$byClientId = [];

foreach ($rows as $row) {
    $lineageId = (int) $row['lineageID'];
    $clientId = (int) $row['clientID'];

    $lineage = [
        'lineageID' => $lineageId,
        'clientID' => $clientId,
    ];

    $byLineageId[$lineageId] = $lineage;
    $byClientId[$clientId] = $lineage;
}

$after = memory_get_usage(true);
$peak = memory_get_peak_usage(true);

$count = count($byLineageId);
$usedMb = round(($after - $before) / 1048576, 2);
$peakMb = round($peak / 1048576, 2);

echo "Loaded {$count} lineages into RAM.\n";
echo "Approx memory used for load: {$usedMb} MB.\n";
echo "Peak PHP memory: {$peakMb} MB.\n";

$firstKey = array_key_first($byLineageId);
$sample = $byLineageId[$firstKey];
echo "Sample lookup by lineageID {$sample['lineageID']} -> clientID {$sample['clientID']}\n";
echo "Done.\n";
