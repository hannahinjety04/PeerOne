<?php

declare(strict_types=1);

require_once __DIR__ . '/TreeLog.php';

function chainText(?Update $newest): string  // makes chainText function; takes the newest update and gives back text (string)
{
    $parts = []; // starts an empty list to collect each update's text
    for ($u = $newest; $u !== null; $u = $u->Previous) { // start at newest update, keep going while there's still an update, after each round step back to previous update
        $parts[] = $u->seq . ':' . $u->label; // adds text to the list
    }
    return implode(' -> ', $parts); // joins the list into one file with the arrow between each item 
    
}

$path = __DIR__ . '/log_test.bin'; // the test log file's location 
if (is_file($path)) { // if the file is left over from earlier run, delete (unlink) so every run is fresh and log won't grow each time it is run
    unlink($path); //delete the file
}

$log = new TreeLog($path); // creates the log
$root1 = new SystemRoot(null, 'boot', 1); // makes the first update 
$log->append($root1); // adds first update to the end of log file 
$root2 = new SystemRoot($root1, 'node online', 2); // makes the second update, which points back to the first update ($root1)
$log->append($root2); // adds second update to the end of log file
$log->append(new SystemRoot ($root2, 'first match synced', 3)); // makes the third update, which points back to the second update ($root2) and adds it to the end of log file

$before = file_get_contents($path); // reads the file's current contents into a string variable ($before)
echo "After 3 appends: " . strlen($before) . " bytes\n"; // prints the file's size; strlen counts bytes

$log = new TreeLog($path); // makes a new TreeLog for the same file to pretend program is shut down and restarted
echo "Reloaded: " . $log->count() . " updates\n"; // prints how many updates it found
$log->append(new SystemRoot($log->newest(), 'second match', 4)); // makes a 4th update, points back to newest update in the log, then appends to log

$after = file_get_contents($path); // takes a second snapshot of the file now with 4 updates in it 
echo "After 4th updates: " . strlen($after) . " bytes\n"; // prints the new size of the file 

if (substr($after, 0, strlen($before)) === $before) { // cuts the first 89 bytes off the new file, the same length as the old file, then compares to $before
    echo "Old bytes unchanged\n"; //if they are the same, the old part of the file was never changed; 4th update only added bytes on the end
} else { // otherwise, something before the end was changed 
    echo "Old bytes changed\n"; 
}

$log = new TreeLog($path); // a second restart and loads all 4 updates from the file 
echo "Chain: " . chainText($log->newest()) . "\n"; // prints the whole chain from newest to oldest using chainText function 
echo "Offsets: " . implode(', ', $log->offsets()) . "\n"; // prints where each update starts in the file 

$copyPath = __DIR__ . '/log_copy.bin'; // makes a second file to copy the log onto 
if(is_file($copyPath)) { // if it is left over
    unlink($copyPath); // delete the file
}
$copy = new TreeLog($copyPath); // creates a new log for the copy file 
$u = null;  // holds the newest update so far; starts as null because there is nothing there yet
foreach (['boot', 'node online', 'first match synced', 'second match'] as $i => $label) { // loops over the 4 labels
    $u = new SystemRoot($u, $label, $i + 1); // makes a new update that points back to $u, the previous update; then puts the new update into $u
    $copy->append($u); // adds it to the copy log file 
}
 
if(file_get_contents($copyPath) === $after) { // reads the whole copy file and compares it with $after, the first file after its 4th update
    echo "Files match\n"; // if they match, the file comes out identical to the original log file
} else {
    echo "Files differ\n"; // otherwise, something about saving was not consistent 
}