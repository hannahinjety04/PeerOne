<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/PeerSync.php';

$configFile = getenv('SWOPBLOCK_CONFIG') ?: (__DIR__ . '/config.php');
if (!file_exists($configFile)) {
    $sample = __DIR__ . '/config.sample.php';
    if (is_file($sample)) {
        copy($sample, $configFile);
    }
}

$config = require $configFile;
$dataDir = dirname($config['db_path']);
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$db = Database::connect($config['db_path']);
$schema = file_get_contents(__DIR__ . '/schema.sql');
$db->exec($schema);

// Migrate older DBs that predate sync columns / tables
$migrate = function (PDO $db, string $sql): void {
    try {
        $db->exec($sql);
    } catch (Throwable $e) {
        // column/table may already exist
    }
};

$migrate($db, 'ALTER TABLE orders ADD COLUMN sync_id TEXT');
$migrate($db, 'ALTER TABLE matches ADD COLUMN sync_id TEXT');
$migrate($db, 'ALTER TABLE matches ADD COLUMN buy_sync_id TEXT');
$migrate($db, 'ALTER TABLE matches ADD COLUMN sell_sync_id TEXT');
$migrate($db, 'CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_sync_id ON orders(sync_id)');
$migrate($db, 'CREATE UNIQUE INDEX IF NOT EXISTS idx_matches_sync_id ON matches(sync_id)');

$migrate($db, "CREATE TABLE IF NOT EXISTS message_queue (
	message_id TEXT PRIMARY KEY,
	sync_id TEXT NOT NULL,
	kind TEXT NOT NULL,
	payload_json TEXT NOT NULL,
	origin_node TEXT NOT NULL,
	pow_nonce TEXT NOT NULL,
	pow_score INTEGER NOT NULL,
	fee REAL NOT NULL,
	priority REAL NOT NULL,
	status TEXT NOT NULL DEFAULT 'pending'
		CHECK (status IN ('pending', 'processing', 'completed', 'failed')),
	received_at TEXT NOT NULL DEFAULT (datetime('now')),
	processed_at TEXT, 
	error TEXT
)");
$migrate($db, "CREATE TABLE IF NOT EXISTS fee_ledger (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	message_id TEXT NOT NULL,
	amount REAL NOT NULL, 
	asset TEXT NOT NULL DEFAULT 'CRYPTO',
	to_node_id TEXT NOT NULL,
	created_at TEXT NOT NULL DEFAULT (datetime('now')),
	FOREIGN KEY (message_id) REFERENCES message_queue(message_id)
)");
$migrate($db, 'CREATE INDEX IF NOT EXISTS idx_message_queue_priority ON message_queue(status, priority DESC, received_at ASC)');
$migrate($db, 'CREATE INDEX IF NOT EXISTS idx_message_queue_sync_id ON message_queue(sync_id)');
$migrate($db, 'CREATE INDEX IF NOT EXISTS idx_fee_ledger_node ON fee_ledger(to_node_id)');

$configuredNodeId = trim((string)($config['node_id'] ?? ''));
$nodeId = $configuredNodeId !== '' ? $configuredNodeId : ('node-' . bin2hex(random_bytes(8)));
$sync = new PeerSync($db, $nodeId);
$sync->ensureNodeId();
if ($sync->getMeta('live_status') === null) {
    $genesis = trim((string)($config['genesis_url'] ?? ''));
    $sync->setLiveStatus($genesis !== '' ? 'catching_up' : 'live');
}

$genesis = trim((string)($config['genesis_url'] ?? ''));
if ($genesis !== '') {
    $sync->addPeer($genesis, 'genesis', true);
}

// Backfill marketplace records created before peer gossip existed.
// This makes an upgraded node's existing history available to peers.
$oldOrders = $db->query(
    "SELECT * FROM orders WHERE sync_id IS NULL OR sync_id = '' ORDER BY id ASC"
)->fetchAll();
$setOrderSync = $db->prepare('UPDATE orders SET sync_id = ? WHERE id = ?');
foreach ($oldOrders as $order) {
    $syncId = PeerSync::newSyncId();
    $setOrderSync->execute([$syncId, $order['id']]);
    $sync->appendEvent($syncId, 'order_created', [
        'side' => (string)$order['side'],
        'asset' => (string)$order['asset'],
        'amount' => (float)$order['amount'],
        'price' => (float)$order['price'],
        'status' => (string)$order['status'],
        'created_at' => (string)$order['created_at'],
    ]);
}

$oldMatches = $db->query(
    "SELECT * FROM matches WHERE sync_id IS NULL OR sync_id = '' ORDER BY id ASC"
)->fetchAll();
$getOrderSync = $db->prepare('SELECT sync_id FROM orders WHERE id = ?');
$setMatchSync = $db->prepare(
    'UPDATE matches SET sync_id = ?, buy_sync_id = ?, sell_sync_id = ? WHERE id = ?'
);
foreach ($oldMatches as $match) {
    $getOrderSync->execute([$match['buy_order_id']]);
    $buySyncId = (string)($getOrderSync->fetchColumn() ?: '');
    $getOrderSync->execute([$match['sell_order_id']]);
    $sellSyncId = (string)($getOrderSync->fetchColumn() ?: '');
    if ($buySyncId === '' || $sellSyncId === '') {
        continue;
    }
    $syncId = PeerSync::newSyncId();
    $setMatchSync->execute([$syncId, $buySyncId, $sellSyncId, $match['id']]);
    $sync->appendEvent($syncId, 'match_created', [
        'buy_sync_id' => $buySyncId,
        'sell_sync_id' => $sellSyncId,
        'asset' => (string)$match['asset'],
        'amount' => (float)$match['amount'],
        'price' => (float)$match['price'],
        'payment_status' => (string)$match['payment_status'],
        'created_at' => (string)$match['created_at'],
    ]);
}

echo "Database ready at {$config['db_path']}\n";
echo "Node ID: " . $sync->ensureNodeId() . "\n";
echo "Live status: " . $sync->liveStatus() . "\n";
echo "Sync events: " . $sync->eventCount() . "\n";
