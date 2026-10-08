<?php
/**
 * CLI / browser setup for local SQLite central (development package).
 * Creates data/central.db from central_sqlite.sql and seeds the owner account.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$dataDir = $root . DIRECTORY_SEPARATOR . 'data';
$dbPath = $dataDir . DIRECTORY_SEPARATOR . 'central.db';
$schemaFile = __DIR__ . DIRECTORY_SEPARATOR . 'central_sqlite.sql';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

if (!is_file($schemaFile)) {
    fwrite(STDERR, "Missing schema: {$schemaFile}\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec(file_get_contents($schemaFile));

$email = 'owner@central.local';
$password = 'changeme123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$check = $pdo->prepare('SELECT clientID FROM Clients WHERE email = ? LIMIT 1');
$check->execute([$email]);
$existing = $check->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $upd = $pdo->prepare('UPDATE Clients SET passwordHash = ?, isActive = 1 WHERE clientID = ?');
    $upd->execute([$hash, (int)$existing['clientID']]);
    $msg = "Owner account updated.\n";
} else {
    $ins = $pdo->prepare('INSERT INTO Clients (email, passwordHash, isActive) VALUES (?, ?, 1)');
    $ins->execute([$email, $hash]);
    $msg = "Owner account created.\n";
}

$out = $msg
    . "Database: {$dbPath}\n"
    . "Email: {$email}\n"
    . "Password: {$password}\n"
    . "Admin login: /admin/login.php\n";

if (PHP_SAPI === 'cli') {
    echo $out;
} else {
    header('Content-Type: text/plain; charset=UTF-8');
    echo $out;
}
