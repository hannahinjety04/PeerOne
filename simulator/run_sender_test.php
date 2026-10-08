<?php 
declare (strict_types= 1);

require_once __DIR__ . '/SimulationMessage.php';
require_once __DIR__ . '/SimulationQueue.php';
require_once __DIR__ . '/MessageCreator.php';
require_once __DIR__ . '/MessageSender.php';

$lineageMin = 1;
$lineageMax = 1000;

$queue = new SimulationQueue();
$creator = new MessageCreator($queue, $lineageMin, $lineageMax);

echo "Filling simulation queue to 1000...\n";
$creator->fillBurst();
echo "Queue before send: " . $queue->count() . "\n";

if ($queue->count() !== 1000) {
    echo "FAIL: expected 1000 messages before sending. \n";
    exit(1);
}

$nodeUrls = [
    'http://127.0.0.1:8080/sim-receive.php',
    'http://127.0.0.1:8082/sim-receive.php',
];
$sender = new MessageSender($queue, $nodeUrls);

$batchSize = 10; 
echo "Sending {$batchSize} messages...\n";

$results = $sender->processBatch($batchSize);

echo "Queue after send: " . $queue->count() . "\n";

$expected = 1000 - count($results);
if ($queue ->count() !== $expected) {
    echo "FAIL: expected {$expected} messages after sending. \n";
    exit(1);
}

$okCount = 0;

foreach ($results as $result) {
    $messageId = $result['message']['message_id'] ?? '?';
    $url = $result['url'];
    $ok = !empty($result['send']['ok']);

    if ($ok) {
        $okCount++;
    }
    echo " msg {$messageId} -> {$url} ok= " . ($ok ? 'true' : 'false') . "\n";
}

if($okCount !== count($results)) {
    echo "FAIL: only {$okCount} of " . count($results) . " messages sent successfully. \n";
    exit(1);    
}

echo "PASS: all messages sent successfully. \n";
echo "Done.\n";