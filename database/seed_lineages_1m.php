<?php
/** 
 * test: this file is going to create 1000000 Authors + 1000000 Lineages (one lineage per author).
 **/
 
 declare(strict_types=1); 
 
 require_once dirname(__DIR__) . '/includes/dbconnect.php'; 
 
 $pdo = db(); 
 $total = 1000000;
 

 $pdo->exec(
    "DELETE FROM Lineages
     WHERE clientID IN (
       SELECT clientID FROM (
         SELECT clientID FROM Clients WHERE email LIKE 'author%@test.local'
       ) AS t
     )"
);
$pdo->exec("DELETE FROM Clients WHERE email LIKE 'author%@test.local'");
 
 echo "Creating {$total} clients and lineages...\n";
 
 $insertClient = $pdo->prepare(
	'INSERT INTO Clients (email, passwordHash, isActive) VALUES (?, ?, 1)'
);

$insertLineage = $pdo->prepare(
	'INSERT INTO Lineages (clientID) VALUES (?)'
);

$pdo->beginTransaction();   

for ($i = 1; $i <= $total; $i++) {
    $email = "author{$i}@test.local";
    $passwordHash = '$2y$10$testHashforSeedData' . $i;

    $insertClient->execute([$email, $passwordHash]);
    $clientId = (int) $pdo->lastInsertId();
    $insertLineage->execute([$clientId]);

    if ($i % 10000 === 0) {
        echo "  {$i} done...\n";
    
        $pdo->commit();
        $pdo->beginTransaction();
    }
}
$pdo->commit(); 
echo "Done: {$total} clients and {$total} lineages created. \n";