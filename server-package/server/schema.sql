CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sync_id TEXT UNIQUE,
    side TEXT NOT NULL CHECK (side IN ('buy', 'sell')),
    asset TEXT NOT NULL,
    amount REAL NOT NULL CHECK (amount > 0),
    price REAL NOT NULL CHECK (price > 0),
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'matched', 'approved', 'rejected', 'cancelled')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS matches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sync_id TEXT UNIQUE,
    buy_order_id INTEGER NOT NULL,
    sell_order_id INTEGER NOT NULL,
    buy_sync_id TEXT,
    sell_sync_id TEXT,
    asset TEXT NOT NULL,
    amount REAL NOT NULL,
    price REAL NOT NULL,
    payment_status TEXT NOT NULL DEFAULT 'pending' CHECK (payment_status IN ('pending', 'approved', 'rejected')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (buy_order_id) REFERENCES orders(id),
    FOREIGN KEY (sell_order_id) REFERENCES orders(id)
);

CREATE TABLE IF NOT EXISTS sync_events (
    sync_id TEXT PRIMARY KEY,
    kind TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    origin_node TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS peers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url TEXT NOT NULL UNIQUE,
    label TEXT,
    is_genesis INTEGER NOT NULL DEFAULT 0,
    last_seen_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS node_meta (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS message_queue (
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
);

CREATE TABLE IF NOT EXISTS fee_ledger (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	message_id TEXT NOT NULL,
	amount REAL NOT NULL, 
	asset TEXT NOT NULL DEFAULT 'CRYPTO',
	to_node_id TEXT NOT NULL,
	created_at TEXT NOT NULL DEFAULT (datetime('now')),
	FOREIGN KEY (message_id) REFERENCES message_queue(message_id)
);

CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_asset ON orders(asset, side, status);
CREATE INDEX IF NOT EXISTS idx_sync_events_created ON sync_events(created_at);
CREATE INDEX IF NOT EXISTS idx_message_queue_priority
	ON message_queue(status, priority DESC, received_at ASC);
CREATE INDEX IF NOT EXISTS idx_message_queue_sync_id ON message_queue(sync_id);
CREATE INDEX IF NOT EXISTS idx_fee_ledger_node ON fee_ledger(to_node_id);
