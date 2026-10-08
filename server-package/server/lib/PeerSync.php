<?php
declare(strict_types=1);

/**
 * Peer marketplace sync (v1): event log + digests + pull/push.
 * Marketplace data only — never keys or credentials.
 */
class PeerSync
{
    public function __construct(
        private PDO $db,
        private string $nodeId
    ) {}

    public static function newSyncId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function getMeta(string $key, ?string $default = null): ?string
    {
        $stmt = $this->db->prepare('SELECT value FROM node_meta WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) {
            return $default;
        }
        return (string)$row['value'];
    }

    public function setMeta(string $key, string $value): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO node_meta (key, value) VALUES (?, ?)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value
        ');
        $stmt->execute([$key, $value]);
    }

    public function liveStatus(): string
    {
        return $this->getMeta('live_status', 'catching_up') ?? 'catching_up';
    }

    public function setLiveStatus(string $status): void
    {
        if (!in_array($status, ['catching_up', 'live'], true)) {
            throw new InvalidArgumentException('Invalid live status');
        }
        $this->setMeta('live_status', $status);
    }

    public function ensureNodeId(): string
    {
        $existing = $this->getMeta('node_id');
        if ($existing) {
            return $existing;
        }
        $this->setMeta('node_id', $this->nodeId);
        return $this->nodeId;
    }

    public function appendEvent(string $syncId, string $kind, array $payload, ?string $origin = null): void
    {
        $stmt = $this->db->prepare('
            INSERT OR IGNORE INTO sync_events (sync_id, kind, payload_json, origin_node)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([
            $syncId,
            $kind,
            json_encode($payload, JSON_UNESCAPED_SLASHES),
            $origin ?? $this->ensureNodeId(),
        ]);
    }

    /** @return list<string> */
    public function listDigests(): array
    {
        // rowid preserves dependency order when several events share a timestamp:
        // orders first, then matches, then approval/rejection events.
        $stmt = $this->db->query('SELECT sync_id FROM sync_events ORDER BY rowid ASC');
        $ids = [];
        foreach ($stmt->fetchAll() as $row) {
            $ids[] = (string)$row['sync_id'];
        }
        return $ids;
    }

    public function eventCount(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM sync_events')->fetchColumn();
    }

    /**
     * @param list<string> $ids
     * @return list<array{sync_id:string,kind:string,payload:array,origin_node:string,created_at:string}>
     */
    public function getEventsByIds(array $ids): array
    {
        $out = [];
        $stmt = $this->db->prepare('SELECT * FROM sync_events WHERE sync_id = ?');
        foreach ($ids as $id) {
            $id = (string)$id;
            if ($id === '') {
                continue;
            }
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) {
                continue;
            }
            $payload = json_decode((string)$row['payload_json'], true);
            $out[] = [
                'sync_id' => (string)$row['sync_id'],
                'kind' => (string)$row['kind'],
                'payload' => is_array($payload) ? $payload : [],
                'origin_node' => (string)$row['origin_node'],
                'created_at' => (string)$row['created_at'],
            ];
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $events
     * @return array{applied:int,skipped:int,errors:list<string>}
     */
    public function importEvents(array $events): array
    {
        $applied = 0;
        $skipped = 0;
        $errors = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                $skipped++;
                continue;
            }
            $syncId = (string)($event['sync_id'] ?? '');
            $kind = (string)($event['kind'] ?? '');
            if ($syncId === '' || $kind === '') {
                $skipped++;
                continue;
            }
            $exists = $this->db->prepare('SELECT 1 FROM sync_events WHERE sync_id = ?');
            $exists->execute([$syncId]);
            if ($exists->fetchColumn()) {
                $skipped++;
                continue;
            }

            $payload = $event['payload'] ?? [];
            if (!is_array($payload)) {
                $payload = [];
            }
            $origin = (string)($event['origin_node'] ?? 'peer');

            $this->db->beginTransaction();
            try {
                $this->applyEvent($syncId, $kind, $payload);
                $this->appendEvent($syncId, $kind, $payload, $origin);
                $this->db->commit();
                $applied++;
            } catch (Throwable $e) {
                $this->db->rollBack();
                $skipped++;
                $errors[] = $syncId . ': ' . $e->getMessage();
            }
        }
        return ['applied' => $applied, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function applyEvent(string $syncId, string $kind, array $payload): void
    {
        switch ($kind) {
            case 'order_created':
                $this->applyOrderCreated($syncId, $payload);
                break;
            case 'match_created':
                $this->applyMatchCreated($syncId, $payload);
                break;
            case 'match_approved':
                $this->applyMatchStatus($syncId, $payload, 'approved');
                break;
            case 'match_rejected':
                $this->applyMatchStatus($syncId, $payload, 'rejected');
                break;
            default:
                throw new RuntimeException('Unknown event kind: ' . $kind);
        }
    }

    private function applyOrderCreated(string $syncId, array $payload): void
    {
        $check = $this->db->prepare('SELECT id FROM orders WHERE sync_id = ?');
        $check->execute([$syncId]);
        if ($check->fetch()) {
            return;
        }
        $stmt = $this->db->prepare('
            INSERT INTO orders (sync_id, side, asset, amount, price, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $syncId,
            (string)($payload['side'] ?? ''),
            strtoupper((string)($payload['asset'] ?? '')),
            (float)($payload['amount'] ?? 0),
            (float)($payload['price'] ?? 0),
            (string)($payload['status'] ?? 'open'),
            (string)($payload['created_at'] ?? gmdate('Y-m-d H:i:s')),
        ]);
    }

    private function applyMatchCreated(string $syncId, array $payload): void
    {
        $check = $this->db->prepare('SELECT id FROM matches WHERE sync_id = ?');
        $check->execute([$syncId]);
        if ($check->fetch()) {
            return;
        }

        $buySync = (string)($payload['buy_sync_id'] ?? '');
        $sellSync = (string)($payload['sell_sync_id'] ?? '');
        $buyId = $this->localOrderIdBySync($buySync);
        $sellId = $this->localOrderIdBySync($sellSync);
        if ($buyId === null || $sellId === null) {
            throw new RuntimeException('Match references unknown orders');
        }

        $stmt = $this->db->prepare('
            INSERT INTO matches (sync_id, buy_order_id, sell_order_id, buy_sync_id, sell_sync_id, asset, amount, price, payment_status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $syncId,
            $buyId,
            $sellId,
            $buySync,
            $sellSync,
            (string)($payload['asset'] ?? ''),
            (float)($payload['amount'] ?? 0),
            (float)($payload['price'] ?? 0),
            (string)($payload['payment_status'] ?? 'pending'),
            (string)($payload['created_at'] ?? gmdate('Y-m-d H:i:s')),
        ]);

        $upd = $this->db->prepare("UPDATE orders SET status = 'matched' WHERE id IN (?, ?)");
        $upd->execute([$buyId, $sellId]);
    }

    private function applyMatchStatus(string $syncId, array $payload, string $status): void
    {
        $matchSync = (string)($payload['match_sync_id'] ?? $syncId);
        $stmt = $this->db->prepare('SELECT * FROM matches WHERE sync_id = ?');
        $stmt->execute([$matchSync]);
        $match = $stmt->fetch();
        if (!$match) {
            throw new RuntimeException('Match not found for status update');
        }
        $upd = $this->db->prepare('UPDATE matches SET payment_status = ? WHERE id = ?');
        $upd->execute([$status, $match['id']]);
        $orderStatus = $status === 'approved' ? 'approved' : 'rejected';
        $orderUpd = $this->db->prepare('UPDATE orders SET status = ? WHERE id IN (?, ?)');
        $orderUpd->execute([$orderStatus, $match['buy_order_id'], $match['sell_order_id']]);
    }

    private function localOrderIdBySync(string $syncId): ?int
    {
        if ($syncId === '') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT id FROM orders WHERE sync_id = ?');
        $stmt->execute([$syncId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }

    public function addPeer(string $url, string $label = '', bool $isGenesis = false): void
    {
        $url = rtrim(trim($url), '/');
        if ($url === '') {
            throw new InvalidArgumentException('Peer URL required');
        }
        $stmt = $this->db->prepare('
            INSERT INTO peers (url, label, is_genesis)
            VALUES (?, ?, ?)
            ON CONFLICT(url) DO UPDATE SET
                label = excluded.label,
                is_genesis = CASE WHEN excluded.is_genesis = 1 THEN 1 ELSE peers.is_genesis END
        ');
        $stmt->execute([$url, $label, $isGenesis ? 1 : 0]);
    }

    /** @return list<array<string,mixed>> */
    public function listPeers(): array
    {
        return $this->db->query('SELECT * FROM peers ORDER BY is_genesis DESC, id ASC')->fetchAll();
    }

    public function touchPeer(string $url): void
    {
        $stmt = $this->db->prepare('UPDATE peers SET last_seen_at = datetime(\'now\') WHERE url = ?');
        $stmt->execute([rtrim($url, '/')]);
    }

    public function pickPeerUrl(bool $preferGenesis): ?string
    {
        if ($preferGenesis) {
            $stmt = $this->db->query('SELECT url FROM peers WHERE is_genesis = 1 ORDER BY id ASC LIMIT 1');
            $row = $stmt->fetch();
            if ($row) {
                return (string)$row['url'];
            }
        }
        $peers = $this->listPeers();
        if ($peers === []) {
            return null;
        }
        // Rotate by minute bucket so we try a different peer over time
        $idx = ((int)floor(time() / 60)) % count($peers);
        return (string)$peers[$idx]['url'];
    }

    public function statusPayload(): array
    {
        return [
            'node_id' => $this->ensureNodeId(),
            'live_status' => $this->liveStatus(),
            'event_count' => $this->eventCount(),
            'peer_count' => count($this->listPeers()),
            'last_gossip_at' => $this->getMeta('last_gossip_at'),
            'last_gossip_result' => $this->getMeta('last_gossip_result', 'Never run'),
            'last_gossip_error' => $this->getMeta('last_gossip_error'),
            'digests' => $this->listDigests(),
        ];
    }

    public function recordGossipResult(string $result, ?string $error = null): void
    {
        $this->setMeta('last_gossip_at', gmdate('c'));
        $this->setMeta('last_gossip_result', $result);
        $this->setMeta('last_gossip_error', $error ?? '');
    }

    /**
     * HTTP helpers for gossip CLI.
     * @return array<string,mixed>
     */
    public static function httpGetJson(string $url): array
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new RuntimeException('GET failed: ' . $url);
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public static function httpPostJson(string $url, array $body): array
    {
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 15,
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $payload === false ? '{}' : $payload,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new RuntimeException('POST failed: ' . $url);
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function peerActionUrl(string $base, string $action): string
    {
        $base = trim($base);
        if ($base === '') {
            throw new InvalidArgumentException('Empty peer URL');
        }
        if (!str_contains($base, 'action=')) {
            $sep = str_contains($base, '?') ? '&' : '?';
            if (!str_ends_with($base, 'api.php') && !str_contains($base, '/api.php')) {
                $base = rtrim($base, '/') . '/api.php';
            }
            return $base . $sep . 'action=' . rawurlencode($action);
        }
        return $base;
    }
}
