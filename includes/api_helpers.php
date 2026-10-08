<?php
declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/LineageStore.php';


function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function generate_api_key(): string
{
    return 'node-' . bin2hex(random_bytes(16));
}

function log_api_access(int $installId, string $endpoint, string $method, int $responseCode): void
{
    $stmt = db()->prepare('
        INSERT INTO API_Access_Logs (installID, endpoint, httpMethod, responseCode)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$installId, $endpoint, $method, $responseCode]);
}

function find_installation_by_api_key(string $apiKey): ?array
{
    $stmt = db()->prepare('SELECT * FROM Installations WHERE apiKey = ? LIMIT 1');
    $stmt->execute([$apiKey]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_node_client(string $installLabel): int
{
    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($installLabel)) ?: 'node';
    $slug = trim($slug, '-');
    $email = $slug . '-' . bin2hex(random_bytes(4)) . '@nodes.local';

    $stmt = db()->prepare('
        INSERT INTO Clients (email, passwordHash, isActive)
        VALUES (?, ?, 1)
    ');
    $stmt->execute([$email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);

    $clientId = (int)db()->lastInsertId();
    
    $store = new LineageStore();
    $store->createForClient(db(), $clientId);

    return $clientId;
}
