<?php 

declare(strict_types=1);

require_once __DIR__ . '/ProofOfWork.php';

/**
* Priority queue file that will check messages for proof of work and if a fee is greater than 0. This file will score 
* priority, put messages in a waiting line, pull the highest priority messages, and mark messages as processing, pending, 
* failed, or completed.
*/

class PriorityQueue 
{ 

public function __construct(
	private PDO $db,
	private string $nodeId, 
	private int $powMinScore = 2
) {}


public function enqueue(array $event): array
{ 
	$syncID = (string)($event['sync_id'] ?? '');
	$kind = (string)($event['kind'] ?? '');
	$nonce = (string)($event['pow_nonce'] ?? '');
	$fee = (float)($event['fee'] ?? 0);
	
	if ($syncID === '' || $kind === '') {
		return ['ok' => false, 'error' => 'crypto fee must be greater than zero'];
	}

	if ($fee <= 0) {
		return ['ok' => false, 'error' => 'crypto fee must be greater than zero'];
	}
	
	$score = ProofOfWork::verify($event, $nonce, $this->powMinScore);
	if($score === null) {
		return ['ok' => false, 'error' => 'invalid or insufficient proof of work'];
	}
	
	$existsQueue = $this->db->prepare("
		SELECT message_id FROM message_queue
		WHERE sync_id = ? AND status IN ('pending', 'processing')
		LIMIT 1
	");
	$existsQueue->execute([$syncID]);
	$existingId = $existsQueue->fetchColumn(); 
	if ($existingId) { 
		return [ 
		'ok' => true,
		'message_id' => (string)$existingId,
		'priority' => null, 
		'error' => 'already queued',
	];
	}
	
	$priority = ((float)$score) * $fee; 
	$messageId = bin2hex(random_bytes(16)); 
	
	$payload = $event['payload'] ?? []; 
	if(!is_array($payload)) {
		$payload = []; 
	}
	$origin = (string)($event['origin_node'] ?? 'peer'); 
	$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
	if ($payloadJson === false) {
			return ['ok' => false, 'error' => 'invalid payload json'];
	}
	
	$stmt = $this->db->prepare('
		INSERT INTO message_queue (
			message_id, sync_id, kind, payload_json, origin_node, pow_nonce,
			pow_score, fee, priority, status 
		) VALUES (?, ?, ?, ?, ?, ?, ?, ? ,?,?)
	'); 
	$stmt->execute([
		$messageId,
		$syncID,
		$kind,
		$payloadJson,
		$origin,
		$nonce,
		$score,
		$fee,
		$priority,
		'pending',
	]);
	return [ 
		'ok' => true,
		'message_id' => $messageId,
		'priority' => $priority,
	]; 
}

public function claimHighest(): ?array
{
	$this->db->beginTransaction();
	try {
		$stmt = $this->db->query("
			SELECT * FROM message_queue
			WHERE status = 'pending'
			ORDER BY priority DESC, received_at ASC, message_id ASC
			LIMIT 1
		");
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if (!$row) { 
		$this->db->commit();
		return null; 
	}	
	$upd = $this->db->prepare("
		UPDATE message_queue 
		SET status = 'processing', processed_at = datetime('now'), error = null
		WHERE message_id = ? AND status = 'pending'
	");
	$upd->execute([(string)$row['message_id']]); 
	if ($upd->rowCount() !== 1) { 
		$this->db->rollBack();
		return null;
	}
	
	$this->db->commit();
	$row['status'] = 'processing';
	return $row;
	} catch (Throwable $e) {
		$this->db->rollBack();
		throw $e;
	}
	}

	public function markCompleted(string $messageId): void
	{
		$stmt = $this->db->prepare(" 
			UPDATE message_queue 
			SET status = 'completed', processed_at = datetime('now'), error = null
			WHERE message_id = ?
		");
		$stmt->execute([$messageId]);
	}
	
	public function markFailed(string $messageId, string $error): void
	{
		$stmt = $this->db->prepare("
			UPDATE message_queue 
			SET status = 'failed', processed_at = datetime('now'), error = ?
			WHERE message_id = ?
		");
		$stmt->execute([$error, $messageId]); 
	}
	
	public function depositFee(string $messageId, float $amount): void
	{ 
		if ($amount <= 0) {
			return;
		}
		$stmt = $this->db->prepare('
			INSERT INTO fee_ledger (message_id, amount, asset, to_node_id)
			VALUES(?,?,?,?)
		');
		$stmt->execute([$messageId, $amount, 'CRYPTO', $this->nodeId]); /** not real chain money, ask about this */
		
	}
	
	public function pendingCount(): int
	{ 
		return(int)$this->db->query("SELECT COUNT(*) FROM message_queue WHERE status = 'pending'")
			->fetchColumn();
	}
	
	public function topPriority(): ?float 
	{
		$value = $this->db->query("
			SELECT priority FROM message_queue 
			WHERE status = 'pending'
			ORDER BY priority DESC, received_at ASC
			LIMIT 1
		")->fetchColumn();
		return $value === false ? null : (float)$value;
	}
	
	public function feesDepositedTotal(): float
	{
		$stmt = $this->db->prepare(
			'SELECT COALESCE(SUM(amount), 0) FROM fee_ledger WHERE to_node_id = ?'
			
		);
		$stmt->execute([$this->nodeId]);
		return (float)$stmt->fetchColumn();
	}
	
	public function statusSummary(): array
	{
		$counts = [
		'pending' => 0, 
		'processing' => 0,
		'completed' => 0,
		'failed' => 0,
	];
	$rows = $this->db->query('SELECT status, COUNT(*) AS c FROM message_queue GROUP BY status')
		->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $row) {
		$status = (string)$row['status'];
		if (isset($counts[$status])) {
			$counts[$status] = (int)$row['c'];
		}
	}
	
	return [
		'pending' => $counts['pending'],
		'processing' => $counts['processing'],
		'completed' => $counts['completed'],
		'failed' => $counts['failed'],
		'top_priority' => $this->topPriority(),
		'fees_deposited' => $this->feesDepositedTotal(),
	];
	}
	
}

	
	
	
	