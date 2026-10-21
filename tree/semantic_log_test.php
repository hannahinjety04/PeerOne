<?php

declare(strict_types=1);

require_once __DIR__ . '/TreeLog.php';

function chainText(?Update $newest): string
{
    $parts = [];
    for ($u = $newest; $u !== null; $u = $u->Previous) {
        $parts[] = $u->seq . ':' . $u->label;
    }
    return implode(' <- ', $parts);
}

$path = __DIR__ . '/semantic_test.bin';
if (is_file($path)) {
    unlink($path);
}

$log = new TreeLog($path);
$u = null;
foreach (['boot', 'node online', 'first match synced'] as $i => $label) {
    $u = new SystemRoot($u, $label, $i + 1);
    $log->append($u);
}
$before = file_get_contents($path);

$log = new TreeLog($path);
$log->append(new SystemRoot($log->newest(), 'second match', 4));
$after = file_get_contents($path);

echo substr($after, 0, strlen($before)) === $before ? "Old bytes unchanged\n" : "Old bytes changed\n";

$log = new TreeLog($path);
echo "Chain: " . chainText($log->newest()) . "\n";
echo "Offsets: " . implode(', ', $log->offsets()) . "\n";
foreach ($log->sections() as $i => $sizes) {
    echo "Record {$i}: structural={$sizes[0]} semantic={$sizes[1]} authorization={$sizes[2]}\n";
}
echo "Newest hash: " . $log->newestHash() . "\n";

$tamperPath = __DIR__ . '/semantic_tampered.bin';
$bytes = $after;
$spot = strpos($bytes, 'node online');
$bytes[$spot] = 'N';
file_put_contents($tamperPath, $bytes);

try {
    new TreeLog($tamperPath);
    echo "Tamper NOT detected\n";
} catch (RuntimeException $e) {
    echo "Tamper detected: " . $e->getMessage() . "\n";
}