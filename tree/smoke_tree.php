<?php 

declare(strict_types= 1);

require_once __DIR__ .'/UpdateTree.php';

$root1 = new SystemRoot(null, 'boot', 1);
$root2 = new SystemRoot($root1, 'node online', 2);
$root3 = new SystemRoot($root2, 'first match synced', 3);

if ($root1->label !== 'boot' || $root1->seq !== 1) {
    echo "FAIL: root1 mutated\n";
    exit(1);
}

$tip = $root3;
$labels = [];
while ($tip !== null) {
    $labels[] = $tip->seq . ':' . $tip->label;
    $tip = $tip->Previous;
}

echo implode (' <- ', $labels) . "\n";

if($labels === ['3:first match synced', '2:node online', '1:boot']) {
    echo "progress ok\n";
} else {
    echo "progress broken\n";
}

