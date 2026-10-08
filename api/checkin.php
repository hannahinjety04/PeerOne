<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api_helpers.php';
require_once dirname(__DIR__) . '/includes/install_verify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'POST required'], 405);
}

$body = read_json_body();
$apiKey = trim((string)($body['apiKey'] ?? $_SERVER['HTTP_X_API_KEY'] ?? ''));
$packageFingerprint = trim((string)($body['packageFingerprint'] ?? ''));

if ($apiKey === '') {
    json_response(['error' => 'apiKey is required'], 400);
}

$install = find_installation_by_api_key($apiKey);
if (!$install) {
    json_response(['error' => 'Unknown node'], 401);
}

if ($install['installStatus'] === 'revoked' || $install['installStatus'] === 'disabled') {
    json_response(['error' => 'Node access revoked'], 403);
}

$ip = client_ip();
$now = date('Y-m-d H:i:s');
$activate = empty($install['activatedAt']);

$sql = '
    UPDATE Installations
    SET lastCheckinAt = ?,
        lastIPAddress = ?,
        installStatus = ?,
        activatedAt = COALESCE(activatedAt, ?)
    WHERE installID = ?
';
$stmt = db()->prepare($sql);
$stmt->execute([
    $now,
    $ip,
    'active',
    $now,
    (int)$install['installID'],
]);

if ($packageFingerprint !== '') {
    update_installation_fingerprint((int)$install['installID'], $packageFingerprint);
}

log_api_access((int)$install['installID'], '/api/checkin.php', 'POST', 200);

json_response([
    'ok' => true,
    'installID' => (int)$install['installID'],
    'installStatus' => 'active',
    'activated' => $activate,
    'checkedInAt' => $now,
]);
