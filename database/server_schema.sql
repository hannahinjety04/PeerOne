-- Server Database Schema
-- Pairs up with a PHP backend, JavaScript, and JSON over the API


-- NOTES

-- CLIENTS include sellers and buyers (eg. buyer_client_id, buyer_seller_id) that all refer to the CLIENTS table.
-- INSTALLATIONS are self-hosted copies of the node a client downloads (installs) and runs on their own machine. Every installation recieves an API key  which the database  tell each install apart when they are requested. 
-- ITEMS is the main catalog containing all items that are to be sold and bought.  WILLING_TO_BUY and WILLING_TO_SELL  reference this table.  

-- The four steps of the buying and selling process:
	-- WILLING_TO_BUY and WILLING_TO_SELL: opens the dropdown menus created with PHP and JavaScript (opens offers)
	-- MATCHES: the system will pair a buy offer and sell offer together
	-- ORDERS: a match is a pending order; this table represents approvals of purchase
	-- PAYMENTS: tied to an order and represents approvals of payment
	-- TRANSACTIONS: the final record of both approvals completed

DROP DATABASE IF EXISTS server_schema;
CREATE DATABASE server_schema;
USE server_schema;

CREATE TABLE Clients(
    clientID         INT             NOT NULL    AUTO_INCREMENT,  -- the primary key of the Clients table; AUTO_INCREMENT means it automatically creates an ID;
    email            VARCHAR(150)    UNIQUE, -- the clients email address, must be unique across all clients
	phone		     VARCHAR(30)     UNIQUE, -- the clients phone number, must be unique across all clients
	passwordHash     VARCHAR(300)    UNIQUE, -- hashed login password that is generated with a PHP password hashing feature
	isActive 		 BOOLEAN         NOT NULL DEFAULT TRUE, -- determines whether the client's account is activated to log in/buy and sell
	createdAt		 TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP, -- records when the client's account was first created
	updatedAt		 TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP, -- records when the client's account was last modified. 
    PRIMARY KEY (clientID) -- declares the clientID attribute is the table's primary key
);

CREATE TABLE Installations (
	installID		 INT             NOT NULL    AUTO_INCREMENT, -- the primary key of the Installations table
	clientID         INT             NOT NULL, -- the foreign key that references Clients table to determine which client perfomed an installation
	installLabel     VARCHAR(200)    NOT NULL,  -- generated when client clicks install button
	apiKey           VARCHAR(80)     UNIQUE, -- a public key the installation uses to identify itself while calling central API
	apiHash          VARCHAR(300),  -- hashed key paired with the apiKey that is used to authenticate each installation request
	packageVersion   VARCHAR(30), -- version of the installed server
	packageFingerprint VARCHAR(64), -- this compares each node and verifies that all installations are the same
	installStatus    ENUM('pending', 'active', 'disabled', 'revoked')     NOT NULL DEFAULT 'pending', -- current state of the installation process (waiting to be activated, active, deactivated, revoked access)
	downloadedAt     TIMESTAMP       NULL, -- when the client installed the server on their machine
	activatedAt      TIMESTAMP       NULL, -- when the server first went live on client's side
	lastCheckinAt    TIMESTAMP       NULL, -- most recent time that the installation pinged the central server
	lastIPAddress    VARCHAR(50), -- IP address the install last connected from
	createdAt        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the installation record was recorded in the database
	PRIMARY KEY (installID), -- declares the installID attribute is the table's primary key
	FOREIGN KEY (clientID) REFERENCES Clients(clientID) -- clientID corresponds to a row in Clients table using clientID
);

CREATE TABLE Items (
	itemID           INT             NOT NULL     AUTO_INCREMENT, -- the primary key of the Items table
	itemName         VARCHAR(150)    NOT NULL, -- display name of the item 
	itemDescription  TEXT, -- a longer name describing the item to clarify what it is
	itemPrice        DECIMAL(12,2)   NOT NULL, -- the baseline price of the time and is shown as the default price in the buy and sell dropdown menus
	isActive         BOOLEAN         NOT NULL DEFAULT TRUE, -- determines whether or not this item is currently available as an option to buy or sell in dropdowns
	createdAt        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when this item was added to the database
	PRIMARY KEY (itemID) -- declares itemID attribute as the table's primary key
);

CREATE TABLE Willing_To_Sell (
	sellOfferID      INT             NOT NULL      AUTO_INCREMENT, -- the primary key of this table, unique ID for sell offers
	clientID         INT             NOT NULL, -- determines which client is willing to sell an item
	itemID           INT             NOT NULL, -- determines which item from the Items table the client is willing to sell
	askingPrice      DECIMAL(12, 2)  NOT NULL, -- the price a client is asking for the item
	sellOfferStatus  ENUM('open', 'partially_matched', 'matched', 'cancelled', 'expired') NOT NULL DEFAULT 'open', -- current state of the sell offer
	createdAT        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the offer was first made and added to the database
	updatedAT        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the offer was last modified 
	quantityFilled   INT             NOT NULL DEFAULT 0, -- how much of the sell offer has matched the buy offers so far
	PRIMARY KEY (sellOfferID), -- declares sellOfferID attribute as the table's primary key
	FOREIGN KEY (clientID) REFERENCES Clients (clientID), -- clientID corresponds to a row in Clients table using clientID
	FOREIGN KEY (itemID) REFERENCES Items (itemID) -- itemID corresponds to a row in Items table using itemID
);

CREATE TABLE Willing_To_Buy (
	buyOfferID        INT            NOT NULL       AUTO_INCREMENT, -- unique ID for buy offers, the primary key of the table
	clientID          INT            NOT NULL, -- determines which client is willing to buy the offer from the client selling the item
	itemID            INT            NOT NULL, -- which item from the Items table the client is willing to buy from selling client
	bidPrice          DECIMAL(12, 2) NOT NULL, -- the price the client is offering for this item
	buyOfferStatus    ENUM('open', 'partially_matched', 'matched', 'cancelled', 'expired') NOT NULL DEFAULT 'open', -- the current state of the offer
	createdAT         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the offer to buy was first made and added to the databas
	updatedAt         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the offer was last modified
	quantityFilled    INT            NOT NULL DEFAULT 0, -- how much of the offer has been matched against the sell offers for the item so far
	PRIMARY KEY (buyOfferID), -- declares buyOfferID attribute as the table's primary key
	FOREIGN KEY (clientID) REFERENCES Clients (clientID), -- clientID corresponds to a row in Clients table using clientID
	FOREIGN KEY (itemID) REFERENCES Items (itemID) -- itemID corresponds to a row in Items table using itemID
);

CREATE TABLE Matches (
	matchID           INT            NOT NULL       AUTO_INCREMENT, -- unique ID for each match; primary key for the table
	sellOfferID       INT            NOT NULL, -- determines which sell offer is being matched; PK of Willing_To_Sell
	buyOfferID        INT            NOT NULL, -- determins which buy offer is being matched with sell offer ; PK of Willing_To_Buy
	itemID            INT            NOT NULL, -- determines which item from dropdown menu is being sold and bought
	matchedQuantity   INT            NOT NULL, -- how much quantity the match covers
	matchedPrice      DECIMAL(12,2)  NOT NULL, -- the price the match was settled at; it may be settled on the original asking price or the bidding price
	matchStatus       ENUM ('proposed', 'confirmed', 'rejected', 'expired') NOT NULL DEFAULT 'proposed', -- current state of the match (whether both sides accepted, one side rejected, system proposed it, or it timed out
	matchedAt         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the match was created in the server and added to the database
	PRIMARY KEY (matchID), -- declares matchID attributed as the table's primary key
	FOREIGN KEY (sellOfferID) REFERENCES Willing_To_Sell (sellOfferID), -- sellOfferID corresponds with a row in Willing_To_Sell table using the PK sellOfferID
	FOREIGN KEY (buyOfferID)  REFERENCES Willing_To_Buy (buyOfferID), -- buyOfferID corresponds with a row in Willing_To_Buy table using the PK buyOfferID
	FOREIGN KEY (itemID) REFERENCES Items (itemID) -- itemID corresponds to a row in Items table using itemID
);

CREATE TABLE Orders (
	orderID           INT            NOT NULL       AUTO_INCREMENT, -- unique ID that tracks each order; the primary key for this table
	matchID           INT            NOT NULL, -- which confirmed match this order was created from 
	buyClientID       INT            NOT NULL, -- the client buying the order
	sellerClientID    INT            NOT NULL, -- client selling the order
	itemID            INT            NOT NULL, -- which item from the drop down this order is for
	quantity          INT            NOT NULL, -- the quantity of what is bought and sold in the order
	unitPrice         DECIMAL(12,2)  NOT NULL, -- agreed price per unit of this order
	totalAmount       DECIMAL(14,2)  NOT NULL, -- full order amount/value (quantity x unit price)
	buyerApprovalStatus              ENUM('pending', 'approved', 'decline') NOT NULL DEFAULT 'pending', -- whether the buyer has approved the order or not
	buyerApprovedAt   TIMESTAMP      NULL, -- when the buyer approved the order
	sellerApprovalStatus             ENUM('pending', 'approved', 'decline') NOT NULL DEFAULT 'pending', -- whether the seller has approved of the order or not
	sellerApprovedAt  TIMESTAMP      NULL, -- when the seller approved the order
	orderStatus       ENUM('pending_approval', 'approved', 'declined', 'cancelled') NOT NULL DEFAULT 'pending_approval', -- the current state of the order
	createdAt         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the order was created and stored in database
	updatedAt         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the order was last modified
	PRIMARY KEY (orderID), -- declares orderID as the table's primary key
	FOREIGN KEY (matchID) REFERENCES Matches (matchID), -- matchID corresponds to a row in Matches table using matchID
	FOREIGN KEY (buyClientID) REFERENCES Clients (clientID), -- buyClientID corresponds to a row in Client table using clientID; clientID is just renamed in this table, but uses the same ID from Clients
	FOREIGN KEY (sellerClientID) REFERENCES Clients (clientID), -- sellerClientID corresponds to a row in Client table using clientID; clientID is just renamed in this table, but uses the same ID from Clients
	FOREIGN KEY (itemID) REFERENCES Items(itemID) -- itemID corresponds to a row in Items table using itemID
);

CREATE TABLE Payments (
	paymentID          INT             NOT NULL        AUTO_INCREMENT, -- the unique ID for each payment; primary key of Payments table
	orderID            INT             NOT NULL, -- which order the payment is for
	payerClientID      INT             NOT NULL, -- the client who is sending the payment (buyer)
	payeeClientID      INT             NOT NULL, -- the client who is receiving the payment (seller)
	amount             DECIMAL(14,2)   NOT NULL, -- the amount of payment being processed/transferred
	paymentMethod      VARCHAR(100)    NOT NULL, -- how the payment was sent to payee
	paymentReference   VARCHAR(150), -- the external transaction ID  from the payment processor
	paymentApprovalStatus              ENUM('pending', 'approved', 'declined', 'refunded') NOT NULL DEFAULT 'pending', -- the current state of approval for payment
	approvedByID       INT             NULL, -- which client approved of the payment; this value is null until it has been approved
	approvedAt         TIMESTAMP       NULL, -- the time the payment was created; null until the payment has been approved
	createdAt          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the payment record was created
	updatedAt          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the payment record was modified
	PRIMARY KEY (paymentID), -- declares the primary key of the table as paymentID
	FOREIGN KEY (orderID) REFERENCES Orders(orderID), -- orderID corresponds to a row in Orders table using orderID
	FOREIGN KEY (payerClientID) REFERENCES Clients(clientID), -- payerClientID corresponds to a row in Client table using clientID; clientID is just renamed in this table, but uses the same ID from Clients
	FOREIGN KEY (payeeClientID) REFERENCES Clients (clientID), -- payeeClientID corresponds to a row in Client table using clientID; clientID is just renamed in this table, but uses the same ID from Clients
	FOREIGN KEY (approvedByID) REFERENCES Clients(clientID) -- approvedbyID corresponds to a row in Clients table using clientID
);

CREATE TABLE Transactions (
	transactionID      INT             NOT NULL         AUTO_INCREMENT, -- the unique ID for each transaction
	orderID            INT             NOT NULL, -- the order this transaction finalizes
	paymentID          INT             NOT NULL, -- the payment this transaction finalizes
	buyerClientID      INT             NOT NULL, -- the client who bought the item(s) in the order
	sellerClientID     INT             NOT NULL, -- the client who sold the item (s) included in the order
	itemID             INT             NOT NULL, -- the item that was bought and sold
	quantity           INT             NOT NULL, -- quantity finalized in each transaction 
	finalPrice         DECIMAL(14,2)   NOT NULL, -- final total value of the transaction 
	transactionStatus  ENUM('completed', 'reversed','disputed') NOT NULL DEFAULT 'completed', -- current state of transaction
	completedAt        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the transaction was fully processed or completed
	PRIMARY KEY (transactionID), -- declares the transactionID attribute as the table's primary key
	FOREIGN KEY (orderID) REFERENCES Orders (orderID), -- orderID corresponds to a row in Orders table
	FOREIGN KEY (paymentID) REFERENCES Payments (paymentID), -- paymentID matches a row in Payments table
	FOREIGN KEY (buyerClientID) REFERENCES Clients (clientID), -- buyerClientID corresponds to a row in Client table using clientID; clientID is just renamed in this table, but uses the same ID from Clients 
	FOREIGN KEY (sellerClientID) REFERENCES Clients (clientID),-- sellerClientID corresponds to a row in Client table using clientID; clientID is just renamed in this table, but uses the same ID from Clients
	FOREIGN KEY (itemID) REFERENCES Items(itemID) -- itemID corresponds to a row in Items table using itemID
);

CREATE TABLE API_Access_Logs (
	logID              INT             NOT NULL         AUTO_INCREMENT, -- the unique ID of each log entry
	installID          INT             NOT NULL, -- which installation made the API request
	endpoint           VARCHAR (150)   NOT NULL, -- which API endpoint or path was requested
	httpMethod         VARCHAR (20)    NOT NULL, -- the HTTP verb that was used for the request (e.g. GET, POST)
	responseCode       SMALLINT,  -- HTTP status code that was returned for the request
	requestedAt        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the API request was made
	PRIMARY KEY (logID), -- declares logID as the primary key of the table
	FOREIGN KEY (installID) REFERENCES Installations(installID)
);

CREATE TABLE Lineages ( 
	lineageID          INT             NOT NULL        AUTO_INCREMENT, -- the unique ID of each lineage 
	clientID           INT             NOT NULL        UNIQUE, -- which user corresponds to the lineage
	createdAt          TIMESTAMP       NOT NULL        DEFAULT CURRENT_TIMESTAMP, -- when was the lineage created
	PRIMARY KEY (lineageID), -- declares lineageID as the primary key of the table
	FOREIGN KEY (clientID) REFERENCES Clients (clientID) 
);

-- NOTE

-- INSERT INTO statements enter "seed data" or sample data into the database for each table above.  
-- Once real data is ready to be added these values
-- can be replaced with real data.  The values seen in these insert statements are placeholders.  

INSERT INTO Clients (email, phone, passwordHash, isActive) VALUES
('marcdelgado@gmail.com', '267-555-0142', '$2cb7$placeholderHashAAAAA', TRUE),
('jalvin@outlook.com', '917-555-0188', '$2cb7$placeholderHashBBBBB', TRUE),
('samjohnson@outlook.com',  '312-555-0173', '$2cb7$placeholderHashCCCC', TRUE),
('rhuang@outlook.com','650-555-0119', '$2cb7$placeholderHashDDDDD', TRUE);
 
INSERT INTO Installations (clientID, installLabel, apiKey, apiHash, packageVersion, installStatus, downloadedAt, activatedAt, lastCheckinAt, lastIPAddress) VALUES
(1, 'Northbridge Capital - Primary Trading Node', 'nb-key-7f1a9c2d3e4b5061', '$2y$10$apiHashAAAAAAAAAAAAA', '2.3.1', 'active',  '2026-04-02 09:00:00', '2026-04-02 09:14:00', '2026-06-25 22:10:00', '198.51.100.14'),
(2, 'Vantapoint OTC Desk Node','vp-key-2b8e4f6a1c9d0732', '$2y$10$apiHashBBBBBBBBBBBBB', '2.3.1', 'active',  '2026-04-10 13:30:00', '2026-04-10 13:41:00', '2026-06-25 21:55:00', '203.0.113.77'),
(3, 'Arcfield Trading - Backup Node','af-key-9d3f1a7b5e2c8094', '$2y$10$apiHashCCCCCCCCCCCCC', '2.2.8', 'pending', '2026-06-20 08:00:00', NULL,  NULL,  '210.6.7.311'),
(4, 'Harbor Settlement Node','dh-key-4a6c8e0b2d1f9357', '$2y$10$apiHashDDDDDDDDDDDD', '2.3.1', 'active',  '2026-05-18 16:00:00', '2026-05-18 16:09:00', '2026-06-20 20:40:00', '192.0.2.211');
 
INSERT INTO Items (itemName, itemDescription, itemPrice, isActive) VALUES
('BTC',  'Bitcoin', 62450.00, TRUE),
('ETH',  'Ethereum', 3415.50, TRUE),
('SOL',  'Solana', 142.75, TRUE);
 


/*
==========================================
Copyright © 2026 Swopblock LLC
==========================================
*/

