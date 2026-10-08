<?php
/**
 * Open this page in your browser to verify central node + install site setup.
 */
header('Content-Type: text/html; charset=UTF-8');

$root = __DIR__;
$packageDir = $root . '/server-package';

$requiredFiles = [
    'index.php',
    'install.php',
    'install.js',
    'download.php',
    'server.css',
    'includes/config.php',
    'includes/dbconnect.php',
    'api/register-node.php',
    'admin/login.php',
    'admin/dashboard.php',
    'database/server_schema.sql',
];

$requiredPackageFiles = [
    'server-package/install.ps1',
    'server-package/install.bat',
    'server-package/register-with-central.ps1',
];

$dbOk = false;
$dbError = '';
$installCount = null;

if (is_file($root . '/includes/dbconnect.php')) {
    try {
        require_once $root . '/includes/dbconnect.php';
        $dbOk = true;
        $installCount = (int)db()->query('SELECT COUNT(*) FROM Installations')->fetchColumn();
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

function ok(bool $pass): string {
    return $pass
        ? '<span style="color:#2ea86a;">OK</span>'
        : '<span style="color:#c44;">MISSING</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Check</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 680px; margin: 40px auto; padding: 0 20px; line-height: 1.5; }
        h1 { font-size: 1.4rem; }
        li { margin: 6px 0; }
        .box { background: #f4f4f4; padding: 16px; border-radius: 8px; margin: 16px 0; }
        code { background: #e8e8e8; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Setup check</h1>
    <p>Central node + public install site.</p>

    <div class="box">
        <strong>PHP version:</strong> <?php echo htmlspecialchars(PHP_VERSION); ?><br>
        <strong>ZipArchive:</strong> <?php echo ok(class_exists('ZipArchive')); ?><br>
        <strong>MySQL (server_schema):</strong> <?php echo $dbOk ? '<span style="color:#2ea86a;">OK</span>' : '<span style="color:#c44;">FAILED</span>'; ?><br>
        <?php if ($dbOk): ?>
        <strong>Installations in database:</strong> <?php echo (int)$installCount; ?><br>
        <?php elseif ($dbError): ?>
        <strong>DB error:</strong> <code><?php echo htmlspecialchars($dbError); ?></code><br>
        <?php endif; ?>
        <strong>Site folder:</strong><br>
        <code><?php echo htmlspecialchars($root); ?></code>
    </div>

    <?php if (!$dbOk): ?>
    <div class="box">
        <strong>Database setup:</strong>
        <ol>
            <li>Start MySQL in XAMPP</li>
            <li>Open phpMyAdmin → Import <code>database/server_schema.sql</code></li>
            <li>Visit <a href="database/seed_owner.php">database/seed_owner.php</a> once (creates owner login)</li>
            <li>Refresh this page</li>
        </ol>
    </div>
    <?php endif; ?>

    <h2>Central + public files</h2>
    <ul>
        <?php foreach ($requiredFiles as $file): ?>
        <li><?php echo ok(is_file($root . '/' . $file)); ?> <?php echo htmlspecialchars($file); ?></li>
        <?php endforeach; ?>
    </ul>

    <h2>server-package (client node zip)</h2>
    <ul>
        <li><?php echo ok(is_dir($packageDir)); ?> server-package/</li>
        <?php foreach ($requiredPackageFiles as $file): ?>
        <li><?php echo ok(is_file($root . '/' . $file)); ?> <?php echo htmlspecialchars($file); ?></li>
        <?php endforeach; ?>
    </ul>

    <p>
        <a href="index.php">Home</a> ·
        <a href="admin/login.php">Admin login</a> ·
        <a href="download.php">Test download</a>
    </p>
</body>
</html>
