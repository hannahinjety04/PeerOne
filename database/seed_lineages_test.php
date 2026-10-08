<?php
/** 
 * test: this file is going to create 1000 Authors + 1000 Lineages (one lineage per author).
 **/
 
 declare(strict_types=1); 
 
 require_once dirname(__DIR__) . '/includes/dbconnect.php'; 
 
 $pdo = db(); 
 $total = 1000;
 $passwordHash = '2y$10$testHashforSeedData';
 
 echo "Creating {$total} clients and lineages...\n";
 
 $insertClient = $pdo->prepare(
	'INSERT INTO Clients (email, passwordHash, isActive) VALUES (?, ?, 1)'
);

$insertLineage = $pdo->prepare(
	'INSERT INTO Lineages (clientID) VALUES (?)'
);

for ($i = 1; $i <= $total; $i++) {
    $email = "author{$i}@test.local";
    $passwordHash = '$2y$10$testHashforSeedData' . $i;

    $insertClient->execute([$email, $passwordHash]);
    $clientId = (int) $pdo->lastInsertId();
    $insertLineage->execute([$clientId]);

    if ($i % 100 === 0) {
        echo "  {$i} done...\n";
    }
} 
echo "Done: {$total} clients and {$total} lineages created. \n";