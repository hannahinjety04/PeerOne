<?php 

/** Backfills lineages for existing records */

declare(strict_types= 1);

require_once __DIR__ . '/../includes/dbconnect.php';
require_once __DIR__ . '/../includes/LineageStore.php';

$pdo = db();
$store = new LineageStore();

echo "Finding clients without lineages...\n";

$sql = '
    SELECT c.clientID 
    FROM Clients c
    LEFT JOIN Lineages l ON l.clientID = c.clientID
    WHERE l.lineageID IS NULL
';
$stmt = $pdo->query($sql);
$missing = $stmt->fetchAll(PDO::FETCH_COLUMN);

$count = count($missing);
echo "Found $count clients without lineages.\n";

if ($count > 0) {
    echo "Backfilling lineages...\n";
    foreach ($missing as $clientId) {
       $store->createForClient($pdo, $clientId);
        echo "Created lineage for clientID: $clientId\n";
    }
    echo "Backfill complete.\n";
} else {
    echo "No clients without lineages found. No action needed.\n";
}

$stillMissing = (int)$pdo->query('
                                SELECT COUNT(*) FROM Clients c 
                                LEFT JOIN Lineages l ON l.clientID = c.clientID 
                                WHERE l.lineageID IS NULL
                                ')->fetchColumn();
echo "After backfill, still missing lineages for $stillMissing clients.\n";