<?php
declare(strict_types=1);

$year = (int)date('Y');
$config = [];
$configFile = __DIR__ . '/includes/config.php';
if (is_file($configFile)) {
    $config = require $configFile;
}
$isLocalSqlite = strtolower((string)($config['db_driver'] ?? 'mysql')) === 'sqlite';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isLocalSqlite ? 'Local Central (Dev)' : 'Crypto Marketplace — Install Server'; ?></title>
    <link rel="stylesheet" href="server.css">
</head>
<body>

<?php if ($isLocalSqlite): ?>
<main class="install-only">
    <div class="install-card">
        <p class="brand">Swopblock — Local Central</p>
        <h1>Your local central is running</h1>
        <p class="tagline">
            This is the development central on this computer (SQLite).
            It is not the public download site.
        </p>
        <a href="admin/login.php" class="btn-install">Open Admin Dashboard</a>
        <p class="fine-print">
            Login: owner@central.local / changeme123<br>
            Client node: start start-client.bat then register-client.bat<br>
            Client URL: http://127.0.0.1:8080
        </p>
    </div>
</main>
<?php else: ?>
<main class="install-only">
    <div class="install-card">
        <p class="brand">Crypto Marketplace</p>
        <h1>Install our server!</h1>
        <p class="tagline">
            Run a self-hosted node on your device to buy and sell crypto,
            match orders, and approve payments on your own machine.
        </p>
        <a href="install.php" class="btn-install">Install Server</a>
        <p class="fine-print">Downloads a server package. Requires PowerShell and PHP 8+ on your device.</p>
    </div>

    <div class="install-card">
        <p class="brand">Crypto Marketplace</p>
        <h1>Install the development environment!</h1>
        <p class="tagline">
            Set up a complete local development copy on your device — full project source,
            development tools, and scripts — to build, test, and improve the marketplace.
        </p>
        <a href="install-dev.php" class="btn-install">Install Development Tools</a>
        <p class="fine-print">Downloads a developer package. Intended for developers.</p>
    </div>
</main>
<?php endif; ?>

<footer>
    <p>&copy; <?php echo $year; ?> Swopblock LLC</p>
    <p style="margin-top:8px;font-size:0.8rem;"><a href="admin/login.php" style="color:#8b9cc0;">Central node login</a></p>
</footer>

</body>
</html>
