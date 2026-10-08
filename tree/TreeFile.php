<?php

require_once __DIR__ . '/UpdateTree.php';

class TreeFile {

public const MAGIC = 'UTRE';
public const VERSION = 1;
public const NO_PREVIOUS = 0xFFFFFFFF;

public const TYPE_CODES = [
    'Update' => 1,
    'Lineage' => 2,
    'Node' => 3,
    'Network' => 4,
    'SystemRoot' => 5,
];

public static function save(Update $newest, string $path): int 

{
    $updates = [];
    $current = $newest;
    while ($current !== null) {
        $updates[] = $current;
        $current = $current->Previous;
    }
    $updates = array_reverse($updates);

    $indexOf = [];
    foreach ($updates as $i => $u) {
        $indexOf[spl_object_id($u)] = $i;
    }

    $count = count($updates);
    $offset = 12 + ($count * 8);

    $table = '';
    $records = '';
    foreach($updates as $u) {
        $class = get_class($u);
        if (!isset(self::TYPE_CODES[$class])) {
            throw new RuntimeException("No type code for {$class}");
        }

        $prev = $u->Previous === null
            ? self::NO_PREVIOUS
            : $indexOf[spl_object_id($u->Previous)];
        
        $record = pack('VVV', $u->seq, $prev, strlen($u->label )) . $u->label;

        $table .= pack('VV', self::TYPE_CODES[$class], $offset);
        $records .= $record; 
        $offset += strlen($record);
    }

    $bytes = self::MAGIC . pack('VV', self::VERSION, $count) . $table . $records;

    if(file_put_contents($path, $bytes) === false) {
        throw new RuntimeException("Could not write {$path}");
    }

    return strlen($bytes);
}
   public static function load(string $path): Update
{
    $buffer = file_get_contents($path);
    if ($buffer === false) {
        throw new RuntimeException("Could not read {$path}");
    }
    if (strlen($buffer) < 12 || substr($buffer, 0, 4) !== self::MAGIC) {
        throw new RuntimeException("Not a tree file: {$path}");
    }

    $head = unpack('Vversion/Vcount', substr($buffer, 4, 8));
    if ($head['version'] !== self::VERSION) {
        throw new RuntimeException("Unsupported version {$head['version']}");
    }
    $count = $head['count'];
    if ($count === 0) {
        throw new RuntimeException("File has no updates");
    }

    $classFor = array_flip(self::TYPE_CODES);
    $built = [];

    for ($i = 0; $i < $count; $i++) {
        $entry = unpack('Vtype/Voffset', substr($buffer, 12 + ($i * 8), 8));

        $class = $classFor[$entry['type']] ?? null;
        if ($class === null) {
            throw new RuntimeException("Unknown type code {$entry['type']}");
        }

        $rec = unpack('Vseq/Vprev/Vlen', substr($buffer, $entry['offset'], 12));
        $label = substr($buffer, $entry['offset'] + 12, $rec['len']);

        if ($rec['prev'] === self::NO_PREVIOUS) {
            $previous = null;
        } elseif ($rec['prev'] < $i) {
            $previous = $built[$rec['prev']];
        } else {
            throw new RuntimeException("Update {$i} points to a later update");
        }

        $built[$i] = new $class($previous, $label, $rec['seq']);
    }

    return $built[$count - 1];
}
}