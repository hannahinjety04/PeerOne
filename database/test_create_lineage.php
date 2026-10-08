<?php

/** This file is a test script that will show lineage and author together */

declare(strict_types= 1);

require_once __DIR__ . '/../includes/api_helpers.php';

echo "Creating a new node client...\n";

$clientId = create_node_client('Lineage Test Client');
echo "New client created with ID: $clientId\n";

$pdo = db();

$stmt = $pdo->prepare('SELECT lineageID, clientID, createdAt FROM Lineages WHERE clientID = ? LIMIT 1');
$stmt->execute([$clientId]);
$lineage = $stmt->fetch();

if ($lineage === false) {
    echo "FAIL: No lineage found for clientID {$clientId}\n";
exit(1);
}

echo "SUCCESS: Lineage found. \n";  
echo "  lineageID: {$lineage['lineageID']}\n";
echo "  clientID: {$lineage['clientID']}\n";     
echo "  createdAt: {$lineage['createdAt']}\n";
echo "Done. \n";