<?php 

/** this file loads lineages from MySQL into RAM, looks up lineages by lineageID and looks up authors by clientID */

declare(strict_types= 1);

class LineageStore {

private bool $loaded = false;
private array $byLineageId = [];
private array $byClientId = [];

public function load(PDO $pdo): void
{
   $rows =$pdo->query(
    'SELECT lineageID, clientID FROM Lineages'
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $this->byLineageId[$row['lineageID']] = $row;
    $this->byClientId[$row['clientID']] = $row;
}
$this->loaded = true;
}

public function getByLineageId(int $lineageId): ?array
{
    if (!$this->loaded) {
        throw new RuntimeException('Lineages not loaded. Call load() first.');
    }

    return $this->byLineageId[$lineageId] ?? null;
}

public function getByClientId(int $clientId): ?array
{
    if (!$this->loaded) {
        throw new RuntimeException('Lineages not loaded. Call load() first.');
    }

    return $this->byClientId[$clientId] ?? null;
}

public function count(): int
{
    if (!$this->loaded) {
        throw new RuntimeException('Lineages not loaded. Call load() first.');
    }

    return count($this->byLineageId);
}


public function createForClient(PDO $pdo, int $clientId): array
{
    $stmt = $pdo->prepare('INSERT INTO Lineages (clientID) VALUES (:clientID)');
    $stmt->execute([':clientID' => $clientId]);
    $lineageId = (int)$pdo->lastInsertId();

    $lineage = ['lineageID' => $lineageId, 'clientID' => $clientId];
    $this->byLineageId[$lineageId] = $lineage;
    $this->byClientId[$clientId] = $lineage;

    return $lineage;
}

}