<?php

declare(strict_types=1);

require_once __DIR__ . '/SimulationQueue.php';
require_once __DIR__ . '/SimulationMessage.php';

class MessageCreator
{
    private const TARGET_HIGH = 1000;  // stop creating at this size
    private const TARGET_LOW  = 900;   // start creating again at this size

    private SimulationQueue $queue;
    private int $lineageMin;
    private int $lineageMax;
    private bool $creating;

    public function __construct(SimulationQueue $queue, int $lineageMin, int $lineageMax)
    {
        $this->queue = $queue;
        $this->lineageMin = $lineageMin;
        $this->lineageMax = $lineageMax;
        $this->creating = true;  // start ready to fill
    }

    /**
     * One small step: look at queue size, update the on/off switch, maybe add one message.
     */
    public function tick(): void
    {
        $size = $this->queue->count();

        // If size >= 1000, stop creating
        if ($size >= self::TARGET_HIGH) {
            $this->creating = false;
        }

        // If size <= 900, start creating again
        if ($size <= self::TARGET_LOW) {
            $this->creating = true;
        }

        // If allowed to create and still under 1000, make one message
        if ($this->creating && $size < self::TARGET_HIGH) {
            // Use Piece 1 function (has scheduled_at) — NOT a stub without that key
            $message = createRandomLineageMessage($this->lineageMin, $this->lineageMax);
            $this->queue->enqueue($message);
        }
    }

    /**
     * Create as fast as possible until queue is full (1000).
     */
    public function fillBurst(): void
    {
        while ($this->queue->count() < self::TARGET_HIGH) {
            $this->tick();
        }
    }

    /**
     * Helper: check if currently allowed to create messages.
     */
    public function isCreating(): bool
    {
        return $this->creating;
    }
}
