<?php
$name = $argv[1] ?? 'lineage1';
$dir = __DIR__ . '/keys';

if (!is_dir($dir)) {
    mkdir($dir);
}

$secretPath = "$dir/$name.secret.key";
$publicPath = "$dir/$name.public.key";

if (file_exists($secretPath)) {
    exit("Keys for $name already exist, not replacing them\n");
}

$pair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($pair);
$public = sodium_crypto_sign_publickey($pair);

file_put_contents($secretPath, $secret);
file_put_contents($publicPath, $public);

echo "Made keys for $name\n";
echo "Secret key: " . strlen($secret) . " bytes\n";
echo "Public key: " . strlen($public) . " bytes -> " . bin2hex($public) . "\n";

$signature = sodium_crypto_sign_detached('test', $secret);
echo "Signature: " . strlen($signature) . " bytes\n";
echo sodium_crypto_sign_verify_detached($signature, 'test', $public) ? "Keys match\n" : "Keys do NOT match\n";