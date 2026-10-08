<?php
/**
 * Sample client node config.
 * Copy to config.php on first setup (setup.php does this automatically).
 */
return [
    'db_path' => __DIR__ . '/data/marketplace.db',
    'node_name' => 'local-node',
    // setup.php generates and stores a unique ID when this is empty.
    'node_id' => '',
    'api_key' => 'change-me',
    // Genesis peer = another client node API base (local demo: http://127.0.0.1:8080/api.php)
    'genesis_url' => '',
    'gossip_interval_seconds' => 60,
	
	//Minimum leading zero bits required on peer-push PoW
	// Reject peer-push if verified PoW score is below this (minimum 2).
	'pow_min_score' => 2,
	
	//Default crypto fee attached to each outbound peer-push event
	'default_push_fee' => 1.0,
];
