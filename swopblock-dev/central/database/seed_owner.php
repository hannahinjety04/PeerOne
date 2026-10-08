<?php
/**
 * Run once in browser after importing server_schema.sql
 * Creates owner login: owner@central.local / changeme123
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/dbconnect.php';

header('Content-Type: text/plain; charset=UTF-8');

$email = 'owner@central.local';
$password = 'changeme123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$check = db()->prepare('SELECT clientID FROM Clients WHERE email = ? LIMIT 1');
$check->execute([$email]);
$existing = $check->fetch();

if ($existing) {
    $upd = db()->prepare('UPDATE Clients SET passwordHash = ?, isActive = 1 WHERE clientID = ?');
    $upd->execute([$hash, (int)$existing['clientID']]);
    echo "Owner account updated.\n";
} else {
    $ins = db()->prepare('INSERT INTO Clients (email, passwordHash, isActive) VALUES (?, ?, 1)');
    $ins->execute([$email, $hash]);
    echo "Owner account created.\n";
}

echo "Email: {$email}\n";
echo "Password: {$password}\n";
echo "Login at: ../admin/login.php\n";
