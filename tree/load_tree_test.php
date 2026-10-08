<?php

declare(strict_types=1);

require_once __DIR__ . '/TreeFile.php';

$original = __DIR__ . '/tree_a.bin';
$reloaded = __DIR__ . '/tree_reloaded.bin';

$newest = (new TreeFile())->load($original);

echo "Newest class: " . get_class($newest) . "\n";

$labels = [];
$current = $newest;
while ($current !== null) {
    $labels[] = $current->seq . ':' . $current->label;
    $current = $current->Previous;
}
echo implode(' <- ', $labels) . "\n";

TreeFile::save($newest, $reloaded);

if (file_get_contents($original) === file_get_contents($reloaded)) {
    echo "Round trip match\n";
} else {
    echo "Round trip DIFFERS\n";
}