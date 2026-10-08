<?php
/**
 * Serves the development tools package as a zip download.
 * Package folder (must match zip contents 1:1):
 *   swopblock-dev/
 */
declare(strict_types=1);

$packageDir = __DIR__ . DIRECTORY_SEPARATOR . 'swopblock-dev';

if (!is_dir($packageDir)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h1>Package not found</h1>';
    echo '<p>The folder <code>swopblock-dev</code> must sit next to <code>download-dev.php</code>.</p>';
    echo '<p>PHP is looking here:<br><code>' . htmlspecialchars($packageDir) . '</code></p>';
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h1>Zip not supported</h1>';
    echo '<p>Enable <code>extension=zip</code> in php.ini and restart Apache.</p>';
    exit;
}

$zipPath = sys_get_temp_dir() . '/swopblock-dev-' . uniqid('', true) . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'Could not build development package.';
    exit;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($packageDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    $filePath = $file->getPathname();
    $relative = 'swopblock-dev/' . substr($filePath, strlen($packageDir) + 1);
    $relative = str_replace('\\', '/', $relative);

    if ($file->isDir()) {
        $zip->addEmptyDir($relative);
    } else {
        $zip->addFile($filePath, $relative);
    }
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="swopblock-development-tools.zip"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-store');

readfile($zipPath);
@unlink($zipPath);
