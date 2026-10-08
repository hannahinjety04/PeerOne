<?php
/**
 * Second local node config (demo on port 8082).
 * Created by prepare-node2 / start-client-2 if missing.
 */
return [
    'db_path' => __DIR__ . '/data-node2/marketplace.db',
    'node_name' => 'local-node-2',
    'node_id' => 'node-local-2',
    'api_key' => 'change-me-node2',
    'genesis_url' => 'http://127.0.0.1:8080/api.php',
    'gossip_interval_seconds' => 60,
];
