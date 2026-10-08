<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/OrderService.php';
require_once dirname(__DIR__) . '/lib/PriorityQueue.php';

$configFile = getenv('SWOPBLOCK_CONFIG') ?: (dirname(__DIR__) . '/config.php');
if (!file_exists($configFile)) {
    header('Location: setup-required.php');
    exit;
}

$config = require $configFile;
$db = Database::connect($config['db_path']);
$service = new OrderService($db, (string)($config['node_id'] ?? 'local-node'));
$powMinScore = (int)($config['pow_min_score'] ?? 2);


$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'create_order') {
        $side = (string)($_POST['side'] ?? '');
        $asset = trim((string)($_POST['asset'] ?? ''));
        $amount = (float)($_POST['amount'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);

        if (in_array($side, ['buy', 'sell'], true) && $asset !== '' && $amount > 0 && $price > 0) {
            $service->createOrder($side, $asset, $amount, $price);
            $message = 'Order created.';
        } else {
            $message = 'Invalid order.';
        }
    }

    if ($formAction === 'approve' && isset($_POST['match_id'])) {
        $service->approveMatch((int)$_POST['match_id']);
        $message = 'Trade approved.';
    }

    if ($formAction === 'reject' && isset($_POST['match_id'])) {
        $service->rejectMatch((int)$_POST['match_id']);
        $message = 'Trade rejected.';
    }
}

$orders = $service->listOrders();
$matches = $service->listMatches();
$sync = $service->peerSync();
$peerStatus = $sync->statusPayload();
$peers = $sync->listPeers();
$queue = new PriorityQueue($db, $sync->ensureNodeId(), $powMinScore);
$queueStatus = $queue->statusSummary();
$nodeName = htmlspecialchars((string)($config['node_name'] ?? 'local-node'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Marketplace Server</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Crypto Marketplace Server</h1>
    <p>Node: <?php echo $nodeName; ?> &mdash; running on your device</p>
</header>

<?php if ($message): ?>
<p class="flash"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<main>
    <section class="panel sync-panel">
        <div class="sync-heading">
            <h2>Peer sync status</h2>
            <span class="sync-state sync-state-<?php echo htmlspecialchars($peerStatus['live_status']); ?>">
                <?php echo htmlspecialchars($peerStatus['live_status']); ?>
            </span>
        </div>
        <div class="sync-grid">
            <p><strong>Node ID</strong><br><?php echo htmlspecialchars($peerStatus['node_id']); ?></p>
            <p><strong>Marketplace events</strong><br><?php echo (int)$peerStatus['event_count']; ?></p>
            <p><strong>Configured peers</strong><br><?php echo (int)$peerStatus['peer_count']; ?></p>
            <p><strong>Last gossip</strong><br><?php echo htmlspecialchars($peerStatus['last_gossip_at'] ?: 'Never'); ?></p>
        </div>
        <p class="sync-result">
            <?php echo htmlspecialchars($peerStatus['last_gossip_result']); ?>
        </p>
        <?php if ($peerStatus['last_gossip_error']): ?>
        <p class="sync-error"><?php echo htmlspecialchars($peerStatus['last_gossip_error']); ?></p>
        <?php endif; ?>
        <?php if ($peers): ?>
        <p class="api-hint">
            Peers:
            <?php
            echo htmlspecialchars(implode(', ', array_map(
                static fn(array $peer): string => (string)$peer['url'],
                $peers
            )));
            ?>
        </p>
        <?php else: ?>
        <p class="api-hint">No peer configured. This node can still be used locally.</p>
        <?php endif; ?>
    </section>
    <section class="panel queue-panel">
        <div class="sync-heading">
            <h2>Priority queue</h2>
        </div>
        <div class="sync-grid">
            <p><strong>Pending</strong><br><?php echo (int)$queueStatus['pending']; ?></p>
            <p><strong>Top priority</strong><br>
                <?php
                echo $queueStatus['top_priority'] === null
                    ? '—'
                    : htmlspecialchars((string)$queueStatus['top_priority']);
                ?>
            </p>
            <p><strong>Fees deposited</strong><br>
                <?php echo htmlspecialchars(number_format((float)$queueStatus['fees_deposited'], 4)); ?> CRYPTO
            </p>
            <p><strong>Completed / failed</strong><br>
                <?php echo (int)$queueStatus['completed']; ?> / <?php echo (int)$queueStatus['failed']; ?>
            </p>
        </div>
    </section>
	

    <section class="panel">
        <h2>New order</h2>
        <form method="post" class="order-form">
            <input type="hidden" name="form_action" value="create_order">
            <label>
                Side
                <select name="side" required>
                    <option value="buy">Buy</option>
                    <option value="sell">Sell</option>
                </select>
            </label>
            <label>
                Asset
                <input type="text" name="asset" placeholder="BTC" required>
            </label>
            <label>
                Amount
                <input type="number" name="amount" step="any" min="0.0001" required>
            </label>
            <label>
                Price (USD)
                <input type="number" name="price" step="any" min="0.01" required>
            </label>
            <button type="submit">Place order</button>
        </form>
    </section>

    <section class="panel">
        <h2>Open &amp; recent orders</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Side</th>
                    <th>Asset</th>
                    <th>Amount</th>
                    <th>Price</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                <tr><td colspan="6">No orders yet.</td></tr>
                <?php else: ?>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?php echo (int)$o['id']; ?></td>
                    <td><?php echo htmlspecialchars($o['side']); ?></td>
                    <td><?php echo htmlspecialchars($o['asset']); ?></td>
                    <td><?php echo htmlspecialchars((string)$o['amount']); ?></td>
                    <td><?php echo htmlspecialchars((string)$o['price']); ?></td>
                    <td><?php echo htmlspecialchars($o['status']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>Matched trades — approve payments</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Asset</th>
                    <th>Amount</th>
                    <th>Price</th>
                    <th>Payment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$matches): ?>
                <tr><td colspan="6">No matches yet.</td></tr>
                <?php else: ?>
                <?php foreach ($matches as $m): ?>
                <tr>
                    <td><?php echo (int)$m['id']; ?></td>
                    <td><?php echo htmlspecialchars($m['asset']); ?></td>
                    <td><?php echo htmlspecialchars((string)$m['amount']); ?></td>
                    <td><?php echo htmlspecialchars((string)$m['price']); ?></td>
                    <td><?php echo htmlspecialchars($m['payment_status']); ?></td>
                    <td>
                        <?php if ($m['payment_status'] === 'pending'): ?>
                        <form method="post" class="inline">
                            <input type="hidden" name="form_action" value="approve">
                            <input type="hidden" name="match_id" value="<?php echo (int)$m['id']; ?>">
                            <button type="submit" class="btn-approve">Approve</button>
                        </form>
                        <form method="post" class="inline">
                            <input type="hidden" name="form_action" value="reject">
                            <input type="hidden" name="match_id" value="<?php echo (int)$m['id']; ?>">
                            <button type="submit" class="btn-reject">Reject</button>
                        </form>
                        <?php else: ?>
                        &mdash;
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>JSON API</h2>
        <p class="api-hint">Other clients on the network can talk to this node:</p>
        <code>GET  /api.php?action=orders</code>
        <code>GET  /api.php?action=matches</code>
        <code>POST /api.php?action=create</code>
        <code>POST /api.php?action=approve</code>
        <code>POST /api.php?action=reject</code>
    </section>
</main>

</body>
</html>
