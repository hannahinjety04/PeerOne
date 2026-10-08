<?php
declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

function find_installation_by_id(int $installId): ?array
{
    $stmt = db()->prepare('SELECT * FROM Installations WHERE installID = ? LIMIT 1');
    $stmt->execute([$installId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function compare_installations(array $left, array $right): array
{
    $leftVersion = trim((string)($left['packageVersion'] ?? ''));
    $rightVersion = trim((string)($right['packageVersion'] ?? ''));
    $sameVersion = $leftVersion !== '' && $leftVersion === $rightVersion;

    $leftFp = trim((string)($left['packageFingerprint'] ?? ''));
    $rightFp = trim((string)($right['packageFingerprint'] ?? ''));
    $sameFingerprint = $leftFp !== '' && $rightFp !== '' && hash_equals($leftFp, $rightFp); //compares two fingerprints to ensure they equal the same hash

    $verified = $sameVersion && ($sameFingerprint || ($leftFp === '' && $rightFp === ''));

    return [
        'verified' => $verified,
        'samePackageVersion' => $sameVersion,
        'sameFingerprint' => $sameFingerprint,
        'left' => [
            'installID' => (int)$left['installID'],
            'installLabel' => (string)$left['installLabel'],
            'packageVersion' => $leftVersion,
            'packageFingerprint' => $leftFp !== '' ? $leftFp : null,
            'installStatus' => (string)$left['installStatus'],
        ],
        'right' => [
            'installID' => (int)$right['installID'],
            'installLabel' => (string)$right['installLabel'],
            'packageVersion' => $rightVersion,
            'packageFingerprint' => $rightFp !== '' ? $rightFp : null,
            'installStatus' => (string)$right['installStatus'],
        ],
        'message' => $verified
            ? 'Installations match the same package.'
            : 'Installations do not match.',
    ];
}

function update_installation_fingerprint(int $installId, string $fingerprint): void
{
    $stmt = db()->prepare('UPDATE Installations SET packageFingerprint = ? WHERE installID = ?');
    $stmt->execute([$fingerprint, $installId]);
}
