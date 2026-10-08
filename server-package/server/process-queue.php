<?php 

/* This file claims the message with the highest priority and
* processes that message first.
* handles one queued message per run
*/

declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/PeerSync.php';
require_once __DIR__ . '/lib/PriorityQueue.php';

$configFile = getenv('SWOPBLOCK_CONFIG') ?: (__DIR__ . '/config.php');
if (!is_file($configFile)) { 
	fwrite(STDERR, "Missing config.php - run setup.php first. \n");
	exit(1);
}
$config = require $configFile; 

$db = Database::connect($config['db_path']);
$nodeId = (string)($config['node_id'] ?? 'local_node');
$powMin = (int)($config['pow_min_score'] ?? 2);
$sync = new PeerSync($db, $nodeId);
$nodeId = $sync->ensureNodeId();
$queue = new PriorityQueue($db, $nodeId, $powMin);

$row = $queue->claimHighest();
if ($row === null) {
	echo "[" .date('c'). "] Queue empty - nothing to process. \n";
	exit(0);
}

$messageId = (string)$row['message_id'];
$syncId = (string)$row['sync_id'];
$kind = (string)$row['kind']; 
$fee = (float)$row['fee'];
$priority = (float)$row['priority'];
$payload = json_decode((string)$row['payload_json'], true);
if (!is_array($payload)) {
	$payload = [];
}
echo "[" . date('c') . "] Claiming message_id={$messageId} sync_id={$syncId}" .
	" kind={$kind} priority={$priority} fee={$fee}\n";

$event = [
	'sync_id' => $syncId,
	'kind' => $kind,
	'payload' => $payload,
	'origin_node' => (string)$row['origin_node'],
];

try {
	$result = $sync ->importEvents([$event]);
	
	if(($result['applied'] ?? 0) < 1 && ($result['errors'] ?? []) !== []) {
		$err = implode('; ', $result['errors']);
		$queue->markFailed($messageId, $err);
		fwrite(STDERR, " apply failed: {$err}\n");
		exit(1);
	}
	
	if (($result['applied'] ?? 0) >= 1 || ($result['skipped'] ?? 0) >= 1) {
		$queue->depositFee($messageId, $fee);
		$queue->markCompleted($messageId); 
		echo " applied=" . ($result['applied'] ?? 0) .
			" skipped= " . ($result['skipped'] ?? 0) . 
			" fee_deposited={$fee} CRYPTO to {$nodeId}\n";
			exit(0);
	}
	$queue->markFailed($messageId, 'import returned no applied/skipped');
	fwrite(STDERR, " unexpected import result\n");
	exit(1);
} catch (Throwable $e) {
	$queue->markFailed($messageId, $e->getMessage());
	fwrite(STDERR, " error: " . $e->getMessage(). "\n"); 
	exit(1);
}
	




