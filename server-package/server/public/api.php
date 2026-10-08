<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/OrderService.php';
require_once __DIR__ . '/../lib/PackageFingerprint.php';
require_once __DIR__ . '/../lib/PeerSync.php';
require_once __DIR__ . '/../lib/PriorityQueue.php';

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
$powMinScore = (int)($config['pow_min_score'] ?? 2);
$orders = new OrderService($db, $nodeId);
$sync = $orders->peerSync();
$queue = new PriorityQueue($db, $sync->ensureNodeId(), $powMinScore);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET' && $action === 'install-info') {
        echo json_encode([
            'packageVersion' => package_version(),
            'packageFingerprint' => package_fingerprint(dirname(__DIR__)),
        ]);
        exit;
    }

    if ($method === 'GET' && $action === 'orders') {
        echo json_encode(['orders' => $orders->listOrders()]);
        exit;
    }

    if ($method === 'GET' && $action === 'matches') {
        echo json_encode(['matches' => $orders->listMatches()]);
        exit;
    }

    if ($method === 'GET' && $action === 'peer-status') {
        echo json_encode($sync->statusPayload());
        exit;
    }

    if ($method === 'GET' && $action === 'peer-digests') {
        echo json_encode([
            'node_id' => $sync->ensureNodeId(),
            'ids' => $sync->listDigests(),
        ]);
        exit;
    }

    if ($method === 'POST' && $action === 'create') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $side = (string)($input['side'] ?? '');
        $asset = (string)($input['asset'] ?? '');
        $amount = (float)($input['amount'] ?? 0);
        $price = (float)($input['price'] ?? 0);

        if (!in_array($side, ['buy', 'sell'], true) || $asset === '' || $amount <= 0 || $price <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid order data']);
            exit;
        }

        $order = $orders->createOrder($side, $asset, $amount, $price);
        echo json_encode(['order' => $order]);
        exit;
    }

    if ($method === 'POST' && $action === 'approve') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $matchId = (int)($input['match_id'] ?? 0);
        if ($matchId <= 0 || !$orders->approveMatch($matchId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Could not approve match']);
            exit;
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'POST' && $action === 'reject') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $matchId = (int)($input['match_id'] ?? 0);
        if ($matchId <= 0 || !$orders->rejectMatch($matchId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Could not reject match']);
            exit;
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'POST' && $action === 'peer-pull') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids = $input['ids'] ?? [];
        if (!is_array($ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'ids array required']);
            exit;
        }
        echo json_encode(['events' => $sync->getEventsByIds($ids)]);
        exit;
    }

    if ($method === 'POST' && $action === 'peer-push') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $events = $input['events'] ?? [];
        if (!is_array($events)) {
            http_response_code(400);
            echo json_encode(['error' => 'events array required']);
            exit;
        }
		$queued = 0; 
		$rejected = 0; 
		$skipped = 0; 
		$errors = [];
		foreach ($events as $event) {
			if (!is_array($event)) {
				$rejected++;
				$errors[] = 'invalid event payload';
				continue;
			}
			$result = $queue->enqueue($event);
			if(!empty($result['ok'])) {
				if(($result['error'] ?? '') === 'already queued') {
					$skipped++;
				} else {
					$queued++;
				} 
			} elseif (($result['error'] ?? '') === 'event already published') {
				$skipped++;
			} else { 
				$rejected++; 
				$errors[] = (string)($event['sync_id'] ?? '?') . '; ' . ($result['error'] ?? 'rejected');
			}
		}
		echo json_encode([
			'ok' => true,
			'queued' => $queued,
			'skipped' => $skipped, 
			'rejected' => $rejected,
			'errors' => $errors,
			'applied' => 0,
		]);
		
			
        exit;
    }

    if ($method === 'POST' && $action === 'peer-add') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $url = (string)($input['url'] ?? '');
        $label = (string)($input['label'] ?? '');
        $isGenesis = !empty($input['is_genesis']);
        if ($url === '') {
            http_response_code(400);
            echo json_encode(['error' => 'url required']);
            exit;
        }
        $sync->addPeer($url, $label, $isGenesis);
        echo json_encode(['ok' => true, 'peers' => $sync->listPeers()]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
}
