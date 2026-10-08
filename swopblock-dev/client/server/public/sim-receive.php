<?php

declare(strict_types=1);

require_once dirname(__DIR__) .'/lib/Database.php';
require_once dirname(__DIR__) .'/lib/PeerSync.php';




header('Content-Type: application/json');

$configFile = getenv('SWOPBLOCK_CONFIG') ?: (dirname(__DIR__) . '/config.php');
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server not configured. Run setup first.']);
    exit;
}

$config = require $configFile;
$db = Database::connect($config['db_path']);
$nodeId = (string)($config['node_id'] ?? 'local-node');
$sync = new PeerSync($db, $nodeId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$raw = file_get_contents('php://input');

$message = json_decode($raw, true);

if (!is_array($message)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}


$syncId = (string)($message['message_id'] ?? '');
if ($syncId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing message_id']);
    exit;
}

$payload = [
    'from_lineage_id' => $message['from_lineage_id'] ?? null,
    'to_lineage_id' => $message['to_lineage_id'] ?? null,
    'delta_ms' => $message['delta_ms'] ?? null,
    'scheduled_at' => $message['scheduled_at'] ?? null,
    'message_id' => $syncId,
];

try {
    $sync->appendEvent($syncId, 'sim_message', $payload);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

http_response_code(200);
echo json_encode([
    'ok' => true,
    'received_message_id' => $syncId,
    'from' => $payload['from_lineage_id'] ?? null,
    'to' => $payload['to_lineage_id'] ?? null,
    'saved_to_sync' => true,
]);


