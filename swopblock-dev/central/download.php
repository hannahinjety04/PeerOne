<?php
/**
 * Serves the client server install package as a zip download.
 */

$packageDir = __DIR__ . DIRECTORY_SEPARATOR . 'server-package';

if (!is_dir($packageDir)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h1>Package not found</h1>';
    echo '<p>The folder <code>server-package</code> must sit in the <strong>same folder</strong> as <code>download.php</code>.</p>';
    echo '<p>PHP is looking here:<br><code>' . htmlspecialchars($packageDir) . '</code></p>';
    echo '<p>Copy the whole project (including <code>server-package</code>) into your XAMPP <code>htdocs</code> folder, not just install.php and download.php.</p>';
    echo '<p><a href="setup-check.php">Open setup-check.php</a> to see what is missing.</p>';
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h1>Zip not supported</h1>';
    echo '<p>PHP ZipArchive is disabled. In XAMPP, edit <code>php.ini</code> and uncomment:</p>';
    echo '<code>;extension=zip</code> → <code>extension=zip</code>';
    echo '<p>Then restart Apache in XAMPP.</p>';
    echo '<p><a href="setup-check.php">Open setup-check.php</a></p>';
    exit;
}

$zipPath = sys_get_temp_dir() . '/crypto-marketplace-server-' . uniqid() . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'Could not build package.';
    exit;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($packageDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    $filePath = $file->getPathname();
    $relative = 'swopblock-marketplace-server/' . substr($filePath, strlen($packageDir) + 1);
    $relative = str_replace('\\', '/', $relative);

    if ($file->isDir()) {
        $zip->addEmptyDir($relative);
    } else {
        $zip->addFile($filePath, $relative);
    }
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="crypto-marketplace-server.zip"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-store');

readfile($zipPath);
@unlink($zipPath);
