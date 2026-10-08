<?php 
 declare(strict_types=1); 
 
 /**
 * Proof of work helpers for priority messaging.
 *Score = leading zero bits in SHA 256
 *Receivers recompute score; never trust the sender's claimed score.
 */
 
 
 class ProofOfWork 
 { public static function canonicalBlob(array $event): string
	 { $payload = $event['payload'] ?? [];
	 if (!is_array($payload)) {
		 $payload = [];
	 }
	 ksort($payload);
	 
	 $data = [
		'sync_id' => (string)($event['sync_id'] ?? ''),
		'kind' => (string)($event['kind'] ?? ''),
		'payload' => $payload,
		'origin_node' => (string)($event['origin_node'] ?? ''),
		'created_at' => (string)($event['created_at'] ?? ''),
	];
	
	 $json = json_encode($data,JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	 return $json === false ? '' : $json;
	
	 }
	 
	 public static function hash(string $blob, string $nonce): string
	 { 
		return hash('sha256', $blob . ':' . $nonce);
	 }
	 
	 public static function scoreFromHash(string $hexHash): int 
	 {
		 $hexHash = strtolower(trim($hexHash));
		 $score = 0;
		 $len = strlen($hexHash);
		
		for ($i = 0; $i < $len; $i++) {
			$nibble = hexdec($hexHash[$i]);
			
			if ($nibble === 0) {
				$score += 4;  
				continue;
			} if ($nibble < 2) {
				$score += 3;
			} elseif ($nibble < 4) { 
				$score += 2; 
			} elseif ($nibble < 8) {
				$score += 1; 
			}
			break; 
		}
		return $score;
	 }
	 
	 public static function score(string $blob, string $nonce): int 
	 {
		return self::scoreFromHash(self::hash($blob, $nonce));
	 } 
	 
	 public static function mine(array $event, int $minScore, int $maxAttempts = 500000): array 
	 {
		 if ($minScore < 2) {
			 $minScore = 2; 
		 }
		 
		 $blob = self::canonicalBlob($event);
		 if ($blob === '') {
			 throw new InvalidArgumentException('Cannot mine PoW for empty event blob'); 
		 }
		 
		 for($i = 0; $i <$maxAttempts; $i++) {
			 $nonce = (string)$i;
			 $digest = self::hash($blob, $nonce);
			 $bits = self::scoreFromHash($digest);
			 if ($bits >= $minScore) {
				 return [
					'nonce' => $nonce,
					'score' => $bits,
					'hash' => $digest,
				]; 
			 }
		 }
		 
		 throw new RuntimeException('POW mining failed before maxAttempts=' . $maxAttempts);
	 }
	 
	 public static function verify(array $event, string $nonce, int $minScore): ?int
	 {
		 // Reject anything below the required minimum (Day 1 floor is 2).
		 if ($nonce === '' || $minScore < 2) {
			 return null;
		 }
		 
		 $blob = self::canonicalBlob($event);
		 if ($blob === '') {
			 return null;
		 } 
		 $bits = self::score($blob, $nonce);
		 return $bits >= $minScore ? $bits : null; 
	
	 }
	 
 }