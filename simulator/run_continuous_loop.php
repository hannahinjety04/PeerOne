<?php

declare(strict_types=1);

require_once __DIR__ . '/SimulationMessage.php';
require_once __DIR__ . '/SimulationQueue.php';
require_once __DIR__ . '/MessageCreator.php';
require_once __DIR__ . '/MessageSender.php';

$durationSeconds = 10;
$lineageMin = 1;
$lineageMax = 1000;

$nodeUrls = [
    'http://127.0.0.1:8080/sim-receive.php',
    'http://127.0.0.1:8082/sim-receive.php'
];

$queue = new SimulationQueue();
$creator = new MessageCreator($queue, $lineageMin, $lineageMax);
$sender = new MessageSender($queue, $nodeUrls);

echo "Filling simulation queue to 1000...\n";
$creator->fillBurst();
echo "Queue before send: " . $queue->count() . "\n";

$sentOk = 0;
$sent8080 = 0;
$sent8082 = 0;
$sentFail = 0;
$created = 0;
$lastPrint = 0;

$start = microtime(true);
$end = $start + $durationSeconds;

echo "Running loop for {$durationSeconds} seconds...\n";

while (microtime(true) < $end) {
    $before = $queue->count();
    $creator->tick();
    if ($queue->count() > $before) {
        $created++;
    }


    if ($queue->count() > 900) {
        $one = $sender->processOne();
        if ($one !== null) {
            if (!empty($one['send']['ok'])) {
                $sentOk++;
                if(str_contains($one['url'],':8080')) {
                    $sent8080++;
             } elseif (str_contains($one['url'], ':8082')) {
                 $sent8082++;
            } else {
                $sentFail++;
            }
        }
    }
}
$elapsed = microtime(true) - $start;
if ($elapsed - $lastPrint >= 2) {
    $lastPrint = $elapsed;
    echo "t=" . (int)$elapsed
        . " queue=" . $queue->count()
        . " sentOk={$sentOk}"
        . " sentFail={$sentFail}"
        . " 8080={$sent8080}"
        . " 8082={$sent8082}"
        . " created={$created}\n";
}
}


echo "=== Summary ===\n";
echo "Sent OK: {$sentOk}\n";
echo "Sent to 8080: {$sent8080}\n";
echo "Sent to 8082: {$sent8082}\n";
echo "Sent fail: {$sentFail}\n";
echo "Created during loop: {$created}\n";
echo "Final queue: " . $queue->count() . "\n";
echo "Done.\n";

if ($sentOk === 0) {
    echo "Node may not be running.\n";
    exit(1);
}

