<?php
/**
 * Builds and serves the development tools zip (central + client + installers).
 * Layout inside zip:
 *   swopblock-dev/
 *     install-dev.bat
 *     install-dev.ps1
 *     README.txt
 *     central/   (marketplace central PHP app)
 *     client/    (server-package contents)
 */
declare(strict_types=1);

$root = __DIR__;
$devScripts = $root . DIRECTORY_SEPARATOR . 'dev-package';
$clientPkg = $root . DIRECTORY_SEPARATOR . 'server-package';

if (!is_dir($devScripts) || !is_file($devScripts . DIRECTORY_SEPARATOR . 'install-dev.ps1')) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h1>Dev package scripts missing</h1>';
    echo '<p>Expected folder: <code>dev-package</code> with install-dev.ps1</p>';
    exit;
}

if (!is_dir($clientPkg)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h1>Client package missing</h1>';
    echo '<p>Expected folder: <code>server-package</code></p>';
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h1>Zip not supported</h1>';
    echo '<p>Enable extension=zip in php.ini and restart Apache.</p>';
    exit;
}

$excludeNames = [
    'server-package' => true,
    'dev-package' => true,
    'data' => true,
    '.git' => true,
    'node_modules' => true,
];

$excludeExactFiles = [
    'config.local.php' => true,
];

$zipPath = sys_get_temp_dir() . '/swopblock-dev-' . uniqid('', true) . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'Could not build development package.';
    exit;
}

$prefix = 'swopblock-dev';

// Root installer scripts
foreach (['install-dev.bat', 'install-dev.ps1', 'README.txt'] as $name) {
    $path = $devScripts . DIRECTORY_SEPARATOR . $name;
    if (is_file($path)) {
        $zip->addFile($path, $prefix . '/' . $name);
    }
}

/**
 * Add a directory tree under $zipPrefix inside the archive.
 */
$addTree = function (string $dir, string $zipPrefix) use ($zip, &$addTree, $excludeNames, $excludeExactFiles): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $full = $file->getPathname();
        $rel = substr($full, strlen($dir) + 1);
        $parts = preg_split('#[\\\\/]#', $rel);
        if ($parts && isset($excludeNames[$parts[0]])) {
            continue;
        }
        $base = basename($full);
        if (isset($excludeExactFiles[$base])) {
            continue;
        }
        if (preg_match('/\.(db|zip|log)$/i', $base)) {
            continue;
        }

        $entry = $zipPrefix . '/' . str_replace('\\', '/', $rel);
        if ($file->isDir()) {
            $zip->addEmptyDir($entry);
        } else {
            $zip->addFile($full, $entry);
        }
    }
};

// Central app (everything at site root except excluded trees)
$addTree($root, $prefix . '/central');

// Client package
$addTree($clientPkg, $prefix . '/client');

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="swopblock-development-tools.zip"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-store');

readfile($zipPath);
@unlink($zipPath);
