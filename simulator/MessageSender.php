<?php

declare(strict_types=1);

require_once __DIR__ . '/SimulationQueue.php';

class MessageSender
{ 
    private SimulationQueue $queue;
    private array $nodeUrls;

    public function __construct(SimulationQueue $queue, array $nodeUrls)
    {
        $this->queue = $queue;
        $this->nodeUrls = $nodeUrls;

        if(empty($this->nodeUrls)) {
            throw new InvalidArgumentException('nodeUrls cannot be empty. Need at least one URL');
        }
    }

    public function pickRandomNode(): string 
    {
        $max = count($this->nodeUrls) - 1;
        $index = random_int(0, $max);

        return $this->nodeUrls[$index];
    }

    public function sendHttp(string $url, array $message): array
    {
        $json = json_encode($message);
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => 5, // seconds
                'ignore_erros' => true,
            ],
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return [
                'ok' => false, 
                'error' => 'HTTP request failed',
                'url' => $url,
                ];
        }

        $decoded = json_decode($response, true);
        return [ 
            'ok' => true,
            'url' => $url,
            'response' => is_array($decoded) ? $decoded : ['raw' => $response],
        ];
    }

    public function processOne(): ?array
    { 
        $message = $this->queue->dequeue();

        if($message === null) {
            return null;
        }

        $url = $this->pickRandomNode();
        $send = $this->sendHttp($url, $message);

        return [ 
            'message' => $message,
            'url' => $url,
            'send' => $send,
        ];
    }

  public function processBatch (int $n): array 
  {
    $results = [];
    for ($i = 0; $i < $n; $i++) {
        $one = $this->processOne();
        if ($one === null) {
            break;
        }
        $results[] = $one;
    }
    return $results;

  }

}
