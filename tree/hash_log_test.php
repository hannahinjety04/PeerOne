<?php

declare(strict_types=1);

require_once __DIR__ . '/TreeLog.php';

function chainText(?Update $newest): string {  // takes the newest update and gives back one line of text

    $parts = []; // an empty list for collecting each update's text
    for ($u = $newest; $u !== null; $u = $u->Previous) { // walks backward; starts at the newest update, keeps going while there is one, and steps to Previous each time 
        $parts[] = $u->seq . ':' . $u->label; // adds text like 4:second match to th elist
    }
    return implode(' <- ', $parts); // joins the list into one line, with <- between each item; <- means "points back to"

}

$path = __DIR__ . '/hash_test.bin'; // this test's log file
if (is_file($path)) { // if the file is left over from an earlier run
    unlink($path); // delete it (unlink) so every run starts fresh with the same results 
}

$log = new TreeLog($path); // creates a new log file; the file does not exist, so it writes the header with version 2
$u = null; // holds the newest update so far; it starts empty (null)
foreach (['boot', 'node online', 'first match synced'] as $i => $label) { // loops over the 3 labels; $i is the position in list, $label is in text
    $u = new SystemRoot($u, $label, $i + 1); // creates a new update that points back to $u; stores the new update in $u so next update points back to it
    $log->append($u); // adds new update to the end of the log; hashing happens here
                        // puts previous update's hash into the record, writes the record, computes record's hash so next update can store it
}
$before = file_get_contents($path); // takes a snap shot of the file's bytes after 3 updates

$log = new TreeLog($path); //the "restart"; loads 3 updates from the file, while loading it rechecks the hash chain
$log->append(new SystemRoot($log->newest(), 'second match', 4)); // adds a 4th update after the newest one rebuilt from the file
$after = file_get_contents($path); // takes a second snapshot with now 4 updates

echo substr($after, 0, strlen($before)) === $before ? "Old bytes unchanged\n" : "Old bytes changed\n"; // checks if the first bytes of the new file exactly equal to old file

$log = new TreeLog ($path); // second restart; loads all 4 updates and checks the entire hash chain again
echo "Chain: " . chainText($log->newest()) . "\n"; // prints the chain from the newest to oldest
echo "Offsets: " . implode(', ', $log->offsets()) . "\n"; // prints where each record starts 
echo "Newest hash: " . $log->newestHash() . "\n"; // prints the newest hash 

$tamperPath = __DIR__ . '/hash_tampered.bin'; // the location of a separate file for the damaged copy
$bytes = $after;  // copies the good file's bytes into $bytes
$spot = strpos($bytes, 'node online'); // finds the position where the text "node online" starts inside those bytes
$bytes[$spot] = 'N'; // changes the letter at the spot to "N" so it says "Node online" 
file_put_contents($tamperPath, $bytes);  // saves the damaged bytes into the tampered file

try { 
    new TreeLog($tamperPath); // tries to load the damaged file
    echo "Tamper NOT detected\n"; // only prints if line 48 did not throw an error, meaning the damage slipped through; this means protection failed
} catch (RuntimeException $e) { // run this instead of crashing if a RunTimeException was thrown; error gets stored in $e
    echo "Tamper detected: " . $e->getMessage() . "\n"; // getMessage() gives the error's text
}

