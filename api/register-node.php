<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'POST required'], 405);
}

$body = read_json_body();
$installLabel = trim((string)($body['installLabel'] ?? ''));
$packageVersion = trim((string)($body['packageVersion'] ?? ''));
$packageFingerprint = trim((string)($body['packageFingerprint'] ?? ''));
$config = require dirname(__DIR__) . '/includes/config.php';

if ($installLabel === '') {
    json_response(['error' => 'installLabel is required'], 400);
}

if ($packageVersion === '') {
    $packageVersion = (string)$config['package_version'];
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $clientId = create_node_client($installLabel);
    $apiKey = generate_api_key();
    $apiHash = password_hash($apiKey, PASSWORD_DEFAULT);
    $ip = client_ip();
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare('
        INSERT INTO Installations (
            clientID, installLabel, apiKey, apiHash, packageVersion, packageFingerprint,
            installStatus, downloadedAt, lastIPAddress
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $clientId,
        $installLabel,
        $apiKey,
        $apiHash,
        $packageVersion,
        $packageFingerprint !== '' ? $packageFingerprint : null,
        'pending',
        $now,
        $ip,
    ]);

    $installId = (int)$pdo->lastInsertId();
    log_api_access($installId, '/api/register-node.php', 'POST', 201);

    $pdo->commit();

    json_response([
        'ok' => true,
        'installID' => $installId,
        'apiKey' => $apiKey,
        'installStatus' => 'pending',
        'message' => 'Node registered. Save your apiKey — it is shown once. Call /api/checkin.php when online.',
    ], 201);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['error' => 'Registration failed'], 500);
}
