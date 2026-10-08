<?php
declare(strict_types=1);

function package_fingerprint(string $root): string
{
    $files = [
        'schema.sql',
        'setup.php',
        'public/index.php',
        'public/api.php',
    ];

    $ctx = hash_init('sha256');
    foreach ($files as $file) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
        if (is_file($path)) {
            hash_update($ctx, (string)file_get_contents($path));
        }
    }

    return hash_final($ctx);
}

function package_version(): string
{
    return '1.0.0';
}
