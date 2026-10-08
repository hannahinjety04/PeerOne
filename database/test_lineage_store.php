<?php 

/** test script that will run LineageStore tests */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/dbconnect.php';
require_once dirname(__DIR__) . '/includes/LineageStore.php';

$pdo = db();
$store = new LineageStore();
$store->load($pdo);

echo $store->count() . "\n";

$sample = $store->getByLineageId(2002);
if ($sample === null) {
    echo "No lineage found for lineageID 2002\n";
} else {
    echo "LineageID 2002 -> clientID {$sample['clientID']}\n";
}
$sampleClient = $store->getByClientId(2008);
if ($sampleClient === null) {
    echo "No lineage found for clientID 2008\n";
} else {
    echo "ClientID 2008 -> lineageID {$sampleClient['lineageID']}\n";
}           

echo"Done"; 