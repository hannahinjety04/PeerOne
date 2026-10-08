<?php

/** This file builds one random lineage-to-lineage message with delta and timestamp */

declare(strict_types= 1);

function createRandomLineageMessage(int $lineageMin, int $lineageMax): array
{
    $from = random_int($lineageMin, $lineageMax);
    do {
        $to = random_int($lineageMin, $lineageMax);
    } while ($to === $from);

    $deltaMs = random_int(0, 5000);
    $scheduledAt = microtime(true) + ($deltaMs / 1000);

    return [
        'from_lineage_id' => $from,
        'to_lineage_id' => $to,
        'delta_ms' => $deltaMs,
        'scheduled_at' => $scheduledAt,
        'message_id' => bin2hex(random_bytes(8)),
    ];
}