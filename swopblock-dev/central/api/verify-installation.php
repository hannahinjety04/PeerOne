<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api_helpers.php';
require_once dirname(__DIR__) . '/includes/install_verify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'POST required'], 405);
}

$body = read_json_body();
$apiKey = trim((string)($body['apiKey'] ?? $_SERVER['HTTP_X_API_KEY'] ?? ''));
$peerInstallId = (int)($body['peerInstallID'] ?? 0);
$packageVersion = trim((string)($body['packageVersion'] ?? ''));
$packageFingerprint = trim((string)($body['packageFingerprint'] ?? ''));

if ($apiKey === '' || $peerInstallId <= 0) {
    json_response(['error' => 'apiKey and peerInstallID are required'], 400);
}

$self = find_installation_by_api_key($apiKey);
if (!$self) {
    json_response(['error' => 'Unknown node'], 401);
}

$peer = find_installation_by_id($peerInstallId);
if (!$peer) {
    json_response(['error' => 'Peer installation not found'], 404);
}

if ((int)$self['installID'] === (int)$peer['installID']) {
    json_response(['error' => 'Cannot verify an installation against itself'], 400);
}

if ($packageVersion !== '') {
    $self['packageVersion'] = $packageVersion;
}
if ($packageFingerprint !== '') {
    $self['packageFingerprint'] = $packageFingerprint;
    update_installation_fingerprint((int)$self['installID'], $packageFingerprint);
}

$result = compare_installations($self, $peer);
log_api_access((int)$self['installID'], '/api/verify-installation.php', 'POST', $result['verified'] ? 200 : 409);

json_response(array_merge(['ok' => true], $result), $result['verified'] ? 200 : 409);
