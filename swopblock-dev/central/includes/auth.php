<?php
declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function admin_logged_in(): bool
{
    start_session();
    return !empty($_SESSION['admin_client_id']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function login_admin(string $email, string $password): bool
{
    $config = require __DIR__ . '/config.php';
    if (strcasecmp(trim($email), $config['admin_email']) !== 0) {
        return false;
    }

    $stmt = db()->prepare('SELECT clientID, passwordHash, isActive FROM Clients WHERE email = ? LIMIT 1');
    $stmt->execute([trim($email)]);
    $client = $stmt->fetch();

    if (!$client || !(bool)$client['isActive']) {
        return false;
    }

    $hash = (string)$client['passwordHash'];
    if ($hash === '' || !password_verify($password, $hash)) {
        return false;
    }

    start_session();
    $_SESSION['admin_client_id'] = (int)$client['clientID'];
    $_SESSION['admin_email'] = $email;
    return true;
}

function logout_admin(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
}

function admin_email(): string
{
    start_session();
    return (string)($_SESSION['admin_email'] ?? '');
}
