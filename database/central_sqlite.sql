-- SQLite schema for local development central node (Option B)
-- Production cloud central continues to use MySQL (server_schema.sql)

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS Clients (
    clientID         INTEGER PRIMARY KEY AUTOINCREMENT,
    email            TEXT UNIQUE,
    phone            TEXT UNIQUE,
    passwordHash     TEXT,
    isActive         INTEGER NOT NULL DEFAULT 1,
    createdAt        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS Installations (
    installID          INTEGER PRIMARY KEY AUTOINCREMENT,
    clientID           INTEGER NOT NULL,
    installLabel       TEXT NOT NULL,
    apiKey             TEXT UNIQUE,
    apiHash            TEXT,
    packageVersion     TEXT,
    packageFingerprint TEXT,
    installStatus      TEXT NOT NULL DEFAULT 'pending',
    downloadedAt       TEXT NULL,
    activatedAt        TEXT NULL,
    lastCheckinAt      TEXT NULL,
    lastIPAddress      TEXT,
    createdAt          TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clientID) REFERENCES Clients(clientID)
);

CREATE TABLE IF NOT EXISTS Items (
    itemID           INTEGER PRIMARY KEY AUTOINCREMENT,
    itemName         TEXT NOT NULL,
    itemDescription  TEXT,
    itemPrice        REAL NOT NULL,
    isActive         INTEGER NOT NULL DEFAULT 1,
    createdAt        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS Willing_To_Sell (
    sellOfferID      INTEGER PRIMARY KEY AUTOINCREMENT,
    clientID         INTEGER NOT NULL,
    itemID           INTEGER NOT NULL,
    askingPrice      REAL NOT NULL,
    sellOfferStatus  TEXT NOT NULL DEFAULT 'open',
    createdAt        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    quantityFilled   INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (clientID) REFERENCES Clients(clientID),
    FOREIGN KEY (itemID) REFERENCES Items(itemID)
);

CREATE TABLE IF NOT EXISTS Willing_To_Buy (
    buyOfferID       INTEGER PRIMARY KEY AUTOINCREMENT,
    clientID         INTEGER NOT NULL,
    itemID           INTEGER NOT NULL,
    bidPrice         REAL NOT NULL,
    buyOfferStatus   TEXT NOT NULL DEFAULT 'open',
    createdAt        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    quantityFilled   INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (clientID) REFERENCES Clients(clientID),
    FOREIGN KEY (itemID) REFERENCES Items(itemID)
);

CREATE TABLE IF NOT EXISTS Matches (
    matchID          INTEGER PRIMARY KEY AUTOINCREMENT,
    sellOfferID      INTEGER NOT NULL,
    buyOfferID       INTEGER NOT NULL,
    itemID           INTEGER NOT NULL,
    matchedQuantity  INTEGER NOT NULL,
    matchedPrice     REAL NOT NULL,
    matchStatus      TEXT NOT NULL DEFAULT 'proposed',
    matchedAt        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sellOfferID) REFERENCES Willing_To_Sell(sellOfferID),
    FOREIGN KEY (buyOfferID) REFERENCES Willing_To_Buy(buyOfferID),
    FOREIGN KEY (itemID) REFERENCES Items(itemID)
);

CREATE TABLE IF NOT EXISTS Orders (
    orderID              INTEGER PRIMARY KEY AUTOINCREMENT,
    matchID              INTEGER NOT NULL,
    buyClientID          INTEGER NOT NULL,
    sellerClientID       INTEGER NOT NULL,
    itemID               INTEGER NOT NULL,
    quantity             INTEGER NOT NULL,
    unitPrice            REAL NOT NULL,
    totalAmount          REAL NOT NULL,
    buyerApprovalStatus  TEXT NOT NULL DEFAULT 'pending',
    buyerApprovedAt      TEXT NULL,
    sellerApprovalStatus TEXT NOT NULL DEFAULT 'pending',
    sellerApprovedAt     TEXT NULL,
    orderStatus          TEXT NOT NULL DEFAULT 'pending_approval',
    createdAt            TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt            TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (matchID) REFERENCES Matches(matchID),
    FOREIGN KEY (buyClientID) REFERENCES Clients(clientID),
    FOREIGN KEY (sellerClientID) REFERENCES Clients(clientID),
    FOREIGN KEY (itemID) REFERENCES Items(itemID)
);

CREATE TABLE IF NOT EXISTS Payments (
    paymentID             INTEGER PRIMARY KEY AUTOINCREMENT,
    orderID               INTEGER NOT NULL,
    payerClientID         INTEGER NOT NULL,
    payeeClientID         INTEGER NOT NULL,
    amount                REAL NOT NULL,
    paymentMethod         TEXT NOT NULL,
    paymentReference      TEXT,
    paymentApprovalStatus TEXT NOT NULL DEFAULT 'pending',
    approvedByID          INTEGER NULL,
    approvedAt            TEXT NULL,
    createdAt             TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt             TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (orderID) REFERENCES Orders(orderID),
    FOREIGN KEY (payerClientID) REFERENCES Clients(clientID),
    FOREIGN KEY (payeeClientID) REFERENCES Clients(clientID),
    FOREIGN KEY (approvedByID) REFERENCES Clients(clientID)
);

CREATE TABLE IF NOT EXISTS Transactions (
    transactionID     INTEGER PRIMARY KEY AUTOINCREMENT,
    orderID           INTEGER NOT NULL,
    paymentID         INTEGER NOT NULL,
    buyerClientID     INTEGER NOT NULL,
    sellerClientID    INTEGER NOT NULL,
    itemID            INTEGER NOT NULL,
    quantity          INTEGER NOT NULL,
    finalPrice        REAL NOT NULL,
    transactionStatus TEXT NOT NULL DEFAULT 'completed',
    completedAt       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (orderID) REFERENCES Orders(orderID),
    FOREIGN KEY (paymentID) REFERENCES Payments(paymentID),
    FOREIGN KEY (buyerClientID) REFERENCES Clients(clientID),
    FOREIGN KEY (sellerClientID) REFERENCES Clients(clientID),
    FOREIGN KEY (itemID) REFERENCES Items(itemID)
);

CREATE TABLE IF NOT EXISTS API_Access_Logs (
    logID        INTEGER PRIMARY KEY AUTOINCREMENT,
    installID    INTEGER NOT NULL,
    endpoint     TEXT NOT NULL,
    httpMethod   TEXT NOT NULL,
    responseCode INTEGER,
    requestedAt  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (installID) REFERENCES Installations(installID)
);

CREATE TABLE IF NOT EXISTS Lineages (
    lineageID        INTEGER PRIMARY KEY AUTOINCREMENT,
    clientID         INTEGER NOT NULL UNIQUE,
    createdAt        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clientID) REFERENCES Clients(clientID)
);
INSERT OR IGNORE INTO Items (itemID, itemName, itemDescription, itemPrice, isActive) VALUES
(1, 'BTC', 'Bitcoin', 62450.00, 1),
(2, 'ETH', 'Ethereum', 3415.50, 1),
(3, 'SOL', 'Solana', 142.75, 1);
