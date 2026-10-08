<?php

declare(strict_types=1);

require_once __DIR__ . '/TreeFile.php';

function buildTree(): SystemRoot
{
    $root1 = new SystemRoot(null, 'boot', 1);
    $root2 = new SystemRoot($root1, 'node online', 2);
    return new SystemRoot($root2, 'first match synced', 3);
}

$fileA = __DIR__ . '/tree_a.bin';
$fileB = __DIR__ . '/tree_b.bin';

$sizeA = TreeFile::save(buildTree(), $fileA);
$sizeB = TreeFile::save(buildTree(), $fileB);

echo "tree_a.bin: {$sizeA} bytes\n";
echo "tree_b.bin: {$sizeB} bytes\n";

$bytes = file_get_contents($fileA);
$head = unpack('Vversion/Vcount', substr($bytes, 4, 8));
echo "magic=" . substr($bytes, 0 , 4) . " version={$head['version']} count={$head['count']}\n";

for ($i = 0; $i < $head['count']; $i++) {
    $entry = unpack('Vtype/Voffset', substr($bytes, 12 + ($i *8), 8));
    echo "Update {$i}: type={$entry['type']} offset={$entry['offset']}\n";
}

if (file_get_contents($fileA) === file_get_contents($fileB)) {
    echo "Files match\n";
}else {
    echo "Files don't match\n";
}