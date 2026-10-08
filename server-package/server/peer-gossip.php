<?php
/**
 * One gossip round: pick a peer, compare digests, pull/push missing marketplace events.
 * Usage: php peer-gossip.php
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/PeerSync.php';
require_once __DIR__ . '/lib/ProofOfWork.php';

$configFile = getenv('SWOPBLOCK_CONFIG') ?: (__DIR__ . '/config.php');
if (!is_file($configFile)) {
    fwrite(STDERR, "Missing config.php — run setup.php first.\n");
    exit(1);
}

$config = require $configFile;
$db = Database::connect($config['db_path']);
$nodeId = (string)($config['node_id'] ?? 'local-node');
$powMinScore = (int)($config['pow_min_score'] ?? 2);
$defaultPushFee = (float)($config['default_push_fee'] ?? 1.0);
$sync = new PeerSync($db, $nodeId);
$sync->ensureNodeId();

$genesis = trim((string)($config['genesis_url'] ?? ''));
if ($genesis !== '') {
    $sync->addPeer($genesis, 'genesis', true);
}

$status = $sync->liveStatus();
$preferGenesis = ($status === 'catching_up');
$peerUrl = $sync->pickPeerUrl($preferGenesis);

if ($peerUrl === null) {
    $message = 'No peers configured. Set genesis_url or POST peer-add.';
    echo "[" . date('c') . "] {$message}\n";
    if ($status === 'catching_up' && $genesis === '') {
        $sync->setLiveStatus('live');
        echo "No genesis set — marking node live.\n";
    }
    $sync->recordGossipResult('idle: no peers configured');
    exit(0);
}

echo "[" . date('c') . "] Gossip with {$peerUrl} (status={$status})\n";

try {
    $statusUrl = PeerSync::peerActionUrl($peerUrl, 'peer-status');
    $peerStatus = PeerSync::httpGetJson($statusUrl);
    $sync->touchPeer($peerUrl);
    echo "  peer node_id=" . ($peerStatus['node_id'] ?? '?') .
        " events=" . ($peerStatus['event_count'] ?? '?') .
        " live=" . ($peerStatus['live_status'] ?? '?') . "\n";

    $digestsUrl = PeerSync::peerActionUrl($peerUrl, 'peer-digests');
    $peerDigests = PeerSync::httpGetJson($digestsUrl);
    $theirIds = $peerDigests['ids'] ?? [];
    if (!is_array($theirIds)) {
        $theirIds = [];
    }
    $theirIds = array_map('strval', $theirIds);
    $myIds = $sync->listDigests();

    $missingMine = array_values(array_diff($theirIds, $myIds));
    $missingTheirs = array_values(array_diff($myIds, $theirIds));

    echo "  I am missing " . count($missingMine) . " event(s); peer is missing " . count($missingTheirs) . "\n";

    if ($missingMine !== []) {
        $pullUrl = PeerSync::peerActionUrl($peerUrl, 'peer-pull');
        $pulled = PeerSync::httpPostJson($pullUrl, ['ids' => $missingMine]);
        $events = $pulled['events'] ?? [];
        if (is_array($events) && $events !== []) {
            $result = $sync->importEvents($events);
            echo "  pulled applied={$result['applied']} skipped={$result['skipped']}\n";
        }
    }

    if ($missingTheirs !== [] && $sync->liveStatus() === 'live') {
        $pushUrl = PeerSync::peerActionUrl($peerUrl, 'peer-push');
        $events = $sync->getEventsByIds($missingTheirs);
        $enriched = [];
		foreach ($events as $event) {
			$fee = $defaultPushFee;
			if ($fee <= 0) {
				$fee = 1.0;
			}
			$mined = ProofOfWork::mine($event, $powMinScore);
			$event['pow_nonce'] = $mined['nonce'];
			$event['pow_score'] = $mined['score'];
			$event['fee'] = $fee;
			$enriched[] = $event;
		}
		if ($enriched !== []) {
			$pushed = PeerSync::httpPostJson($pushUrl, ['events' => $enriched]);
			echo " pushed queued=" , ($pushed['queue'] ?? '?') .
				" skipped=" . ($pushed['skipped'] ?? '?') . 
				" rejected=" . ($pushed['rejected'] ?? '?') . "\n";
		}
		
	} elseif ($missingTheirs !== [] && $preferGenesis) { 
		echo " still catching_up - not pushing to peers yet\n";
	}

    // Catch-up complete when we have everything genesis has (or empty missing)
    if ($preferGenesis && $missingMine === []) {
        $sync->setLiveStatus('live');
        echo "  catch-up complete — node is now live\n";
    }
    $sync->recordGossipResult(
        'ok: peer=' . $peerUrl .
        ' pulled=' . count($missingMine) .
        ' pushed=' . count($missingTheirs)
    );
} catch (Throwable $e) {
    $message = 'peer unavailable; retrying on the next scheduled round: ' . $e->getMessage();
    $sync->recordGossipResult('failed: peer=' . $peerUrl, $message);
    fwrite(STDERR, "  {$message}\n");
    exit(1);
}

echo "Done.\n";
