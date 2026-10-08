<?php
declare(strict_types=1);

require_once __DIR__ . '/PeerSync.php';

class OrderService
{
    private PeerSync $sync;

    public function __construct(private PDO $db, private string $nodeId = 'local-node')
    {
        $this->sync = new PeerSync($db, $nodeId);
        $this->sync->ensureNodeId();
    }

    public function peerSync(): PeerSync
    {
        return $this->sync;
    }

    public function createOrder(string $side, string $asset, float $amount, float $price): array
    {
        $syncId = PeerSync::newSyncId();
        $asset = strtoupper(trim($asset));
        $createdAt = gmdate('Y-m-d H:i:s');

        $stmt = $this->db->prepare(
            'INSERT INTO orders (sync_id, side, asset, amount, price, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$syncId, $side, $asset, $amount, $price, 'open', $createdAt]);
        $orderId = (int)$this->db->lastInsertId();

        $this->sync->appendEvent($syncId, 'order_created', [
            'side' => $side,
            'asset' => $asset,
            'amount' => $amount,
            'price' => $price,
            'status' => 'open',
            'created_at' => $createdAt,
        ]);

        $this->tryMatch($orderId);

        return $this->getOrder($orderId) ?? [];
    }

    public function listOrders(): array
    {
        $stmt = $this->db->query('SELECT * FROM orders ORDER BY id DESC');
        return $stmt->fetchAll();
    }

    public function listMatches(): array
    {
        $sql = '
            SELECT m.*,
                   b.side AS buy_side, s.side AS sell_side
            FROM matches m
            JOIN orders b ON b.id = m.buy_order_id
            JOIN orders s ON s.id = m.sell_order_id
            ORDER BY m.id DESC
        ';
        return $this->db->query($sql)->fetchAll();
    }

    public function approveMatch(int $matchId): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM matches WHERE id = ? AND payment_status = ?');
        $stmt->execute([$matchId, 'pending']);
        $match = $stmt->fetch();
        if (!$match) {
            return false;
        }

        $eventId = PeerSync::newSyncId();
        $this->db->beginTransaction();
        try {
            $upd = $this->db->prepare('UPDATE matches SET payment_status = ? WHERE id = ?');
            $upd->execute(['approved', $matchId]);

            $orderUpd = $this->db->prepare('UPDATE orders SET status = ? WHERE id IN (?, ?)');
            $orderUpd->execute(['approved', $match['buy_order_id'], $match['sell_order_id']]);

            $this->sync->appendEvent($eventId, 'match_approved', [
                'match_sync_id' => (string)($match['sync_id'] ?? ''),
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function rejectMatch(int $matchId): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM matches WHERE id = ? AND payment_status = ?');
        $stmt->execute([$matchId, 'pending']);
        $match = $stmt->fetch();
        if (!$match) {
            return false;
        }

        $eventId = PeerSync::newSyncId();
        $this->db->beginTransaction();
        try {
            $upd = $this->db->prepare('UPDATE matches SET payment_status = ? WHERE id = ?');
            $upd->execute(['rejected', $matchId]);

            $orderUpd = $this->db->prepare('UPDATE orders SET status = ? WHERE id IN (?, ?)');
            $orderUpd->execute(['rejected', $match['buy_order_id'], $match['sell_order_id']]);

            $this->sync->appendEvent($eventId, 'match_rejected', [
                'match_sync_id' => (string)($match['sync_id'] ?? ''),
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function tryMatch(int $newOrderId): void
    {
        $order = $this->getOrder($newOrderId);
        if (!$order || $order['status'] !== 'open') {
            return;
        }

        $opposite = $order['side'] === 'buy' ? 'sell' : 'buy';
        $stmt = $this->db->prepare("
            SELECT * FROM orders
            WHERE side = ? AND asset = ? AND status = 'open' AND id != ?
            ORDER BY created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$opposite, $order['asset'], $newOrderId]);
        $counter = $stmt->fetch();
        if (!$counter) {
            return;
        }

        $buy = $order['side'] === 'buy' ? $order : $counter;
        $sell = $order['side'] === 'sell' ? $order : $counter;
        $amount = min((float)$buy['amount'], (float)$sell['amount']);
        $price = ((float)$buy['price'] + (float)$sell['price']) / 2;
        $matchSyncId = PeerSync::newSyncId();
        $createdAt = gmdate('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $ins = $this->db->prepare('
                INSERT INTO matches (sync_id, buy_order_id, sell_order_id, buy_sync_id, sell_sync_id, asset, amount, price, payment_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $ins->execute([
                $matchSyncId,
                $buy['id'],
                $sell['id'],
                (string)($buy['sync_id'] ?? ''),
                (string)($sell['sync_id'] ?? ''),
                $order['asset'],
                $amount,
                $price,
                'pending',
                $createdAt,
            ]);

            $upd = $this->db->prepare("UPDATE orders SET status = 'matched' WHERE id IN (?, ?)");
            $upd->execute([$buy['id'], $sell['id']]);

            $this->sync->appendEvent($matchSyncId, 'match_created', [
                'buy_sync_id' => (string)($buy['sync_id'] ?? ''),
                'sell_sync_id' => (string)($sell['sync_id'] ?? ''),
                'asset' => $order['asset'],
                'amount' => $amount,
                'price' => $price,
                'payment_status' => 'pending',
                'created_at' => $createdAt,
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function getOrder(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
