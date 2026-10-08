<?php

declare(strict_types= 1);

class SimulationQueue
{
   private array $messages = [];

    public function count(): int
    {
        return count($this->messages);
    }
    
    public function enqueue(array $message): void
    {
        $this->messages[] = $message;
        usort($this->messages, function (array $a, array $b): int {
            if ($a['scheduled_at'] < $b['scheduled_at']) {
                return -1; // a is less than b, a should come first
            } elseif ($a['scheduled_at'] > $b['scheduled_at']) {
                return 1; // a is greater than b, b should come first
            }

            return 0; // a and b are equal, maintain their order
        });
    }

    public function dequeue(): ?array
    {
        if (empty($this->messages)) {
            return null;
        }

        return array_shift($this->messages);
    }

    public function peek(int $index): ?array
    {
        if ($index < 0 || $index >= count($this->messages)) {
            return null;
        }

        return $this->messages[$index];
    }




}