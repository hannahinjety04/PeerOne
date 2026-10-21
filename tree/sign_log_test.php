<?php

declare(strict_types=1);

require_once __DIR__ . '/TreeLog.php';

$publicKey = file_get_contents(__DIR__ . '/keys/lineage1.public.key');
$secretKey = file_get_contents(__DIR__ . '/keys/lineage1.secret.key');

$path = __DIR__ . '/sign_test.bin';
if (is_file($path)) {
    unlink($path);
}

$log = new TreeLog($path, $publicKey, $secretKey);
$u = null;
foreach (['boot', 'node online', 'first match synced'] as $i => $label) {
    $u = new SystemRoot($u, $label, $i + 1);
    $log->append($u);
}

echo "Offsets: " . implode(', ', $log->offsets()) . "\n";
foreach ($log->sections() as $i => $sizes) {
    echo "Record {$i}: structural={$sizes[0]} semantic={$sizes[1]} authorization={$sizes[2]}\n";
}

$reader = new TreeLog($path, $publicKey);
echo "Reader loaded {$reader->count()} updates\n";

try {
    $reader->append(new SystemRoot($reader->newest(), 'sneaky', 4));
    echo "Read-only NOT enforced\n";
} catch (RuntimeException $e) {
    echo "Read-only enforced: " . $e->getMessage() . "\n";
}

$otherSecret = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
try {
    new TreeLog($path, $publicKey, $otherSecret);
    echo "Wrong key NOT caught\n";
} catch (RuntimeException $e) {
    echo "Wrong key caught: " . $e->getMessage() . "\n";
}

function tryLoad(string $what, string $bytes, string $publicKey): void
{
    $path = __DIR__ . '/sign_tampered.bin';
    file_put_contents ($path, $bytes);
    try {
        new TreeLog($path, $publicKey);
        echo "{$what}: NOT caught\n";
    } catch (RuntimeException $e) {
        echo "{$what}: caught ->" . $e->getMessage() . "\n";
    }
}

$good = file_get_contents($path);

$bytes = $good;
$bytes[strpos($bytes, 'first match synced')] = 'F';
tryLoad('Newest update changed', $bytes, $publicKey);

$bytes = $good;
$bytes[strpos($bytes, 'node online')] = 'N';
tryLoad('Older update changed', $bytes, $publicKey);

$otherPublic = sodium_crypto_sign_publickey(sodium_crypto_sign_keypair());
tryLoad('Wrong public key', $good, $otherPublic);