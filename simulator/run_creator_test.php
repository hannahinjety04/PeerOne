<?php

/** Proves that fillBurst function counts messsages correctly (1000)
 * dequeues 100 messages if they are at 900 count then refills to 1000
 * fillBurst counts 1000 again and stops creating messages      
 */

declare(strict_types= 1);

require_once __DIR__ .'/SimulationMessage.php';
require_once __DIR__ .'/SimulationQueue.php';
require_once __DIR__ .'/MessageCreator.php';    

$lineageMin = 1;
$lineageMax = 1000;

$queue = new SimulationQueue();
$creator = new MessageCreator($queue, $lineageMin, $lineageMax);

echo "Filling to 1000...\n";
$creator->fillBurst();  
echo "Count: " . $queue->count() . "\n";        
echo "Creating?: " . ($creator->isCreating() ? 'Yes' : 'No') . "\n";

if($queue->count() !== 1000) {
    echo "Error: Expected count to be 1000 after fillBurst.\n";
    exit(1);
}   

echo "Draining 100 messages...\n";
for ($i = 0; $i < 100; $i++) {
    $queue->dequeue();
}
echo "After draining 100 messages, count: " . $queue->count() . "\n";

if ($queue->count() !== 900) {
    echo "FAIL: Expected count to be 900 after draining 100 messages.\n";
    exit(1);
} 
echo "Refilling to 1000...\n";
$creator->fillBurst();
echo "Count: " . $queue->count() . "\n";

if ($queue->count() !== 1000) {
    echo "FAIL: Expected count to be 1000 after refilling.\n";
    exit(1);
}   

echo "PASS: Step 1 creator loop works.\n";
echo "Done.\n";